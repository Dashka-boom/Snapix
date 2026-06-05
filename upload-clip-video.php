<?php
declare(strict_types=1);

ini_set('display_errors', '0');

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

require './config/config.php';

header('Content-Type: application/json; charset=utf-8');

$maxFileSize = 200 * 1024 * 1024;
$targetWidth = 720;
$targetHeight = 1280;

$ffmpegBinary = getenv('FFMPEG_PATH') ?: 'ffmpeg';
$ffprobeBinary = getenv('FFPROBE_PATH') ?: 'ffprobe';

$projectRoot = __DIR__;
$originalDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'videos' . DIRECTORY_SEPARATOR . 'original';
$encodedDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'videos' . DIRECTORY_SEPARATOR . 'encoded';
$logDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'videos' . DIRECTORY_SEPARATOR . 'logs';
$logFile = $logDirectory . DIRECTORY_SEPARATOR . 'ffmpeg.log';

$allowedExtensions = ['mp4', 'webm', 'mov'];
$allowedMimeTypes = [
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
    'video/quicktime' => 'mov',
];

function clips_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clips_log_error(string $message): void
{
    global $logDirectory, $logFile;

    if (!is_dir($logDirectory)) {
        mkdir($logDirectory, 0775, true);
    }

    error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, $logFile);
}

function clips_prepare_directory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
        clips_json_response([
            'success' => false,
            'error' => 'Не удалось создать папку для загрузки.',
        ], 500);
    }
}

function clips_public_path(string $absolutePath): string
{
    $relativePath = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $absolutePath);

    return str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
}

function clips_detect_duration(string $videoPath, string $ffprobeBinary): ?float
{
    $command = escapeshellcmd($ffprobeBinary)
        . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
        . escapeshellarg($videoPath);

    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);

    if ($returnCode !== 0 || empty($output[0]) || !is_numeric(trim($output[0]))) {
        clips_log_error('ffprobe error: ' . implode(PHP_EOL, $output));

        return null;
    }

    return round((float) trim($output[0]), 3);
}

function clips_encode_video(string $sourcePath, string $targetPath, string $ffmpegBinary, int $width, int $height): void
{
    $videoFilter = sprintf(
        'scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:black,fps=30',
        $width,
        $height,
        $width,
        $height
    );

    $command = escapeshellcmd($ffmpegBinary)
        . ' -y'
        . ' -i ' . escapeshellarg($sourcePath)
        . ' -map 0:v:0 -map 0:a?'
        . ' -vf ' . escapeshellarg($videoFilter)
        . ' -r 30'
        . ' -c:v libx264'
        . ' -preset medium'
        . ' -crf 23'
        . ' -pix_fmt yuv420p'
        . ' -c:a aac'
        . ' -b:a 128k'
        . ' -movflags +faststart'
        . ' ' . escapeshellarg($targetPath);

    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);

    if ($returnCode !== 0 || !is_file($targetPath)) {
        clips_log_error('ffmpeg error: ' . implode(PHP_EOL, $output));
        clips_json_response([
            'success' => false,
            'error' => 'Не удалось обработать видео через ffmpeg.',
        ], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    clips_json_response([
        'success' => false,
        'error' => 'Метод не поддерживается. Используйте POST.',
    ], 405);
}

if (!isset($_FILES['video'])) {
    clips_json_response([
        'success' => false,
        'error' => 'Файл video не передан.',
    ], 422);
}

$uploadedFile = $_FILES['video'];

if (!isset($uploadedFile['error']) || is_array($uploadedFile['error'])) {
    clips_json_response([
        'success' => false,
        'error' => 'Некорректная структура загруженного файла.',
    ], 422);
}

if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
    clips_json_response([
        'success' => false,
        'error' => 'Ошибка загрузки файла. Код: ' . (int) $uploadedFile['error'],
    ], 422);
}

$temporaryPath = (string) $uploadedFile['tmp_name'];
$originalName = (string) ($uploadedFile['name'] ?? '');
$uploadedSize = (int) ($uploadedFile['size'] ?? 0);

if ($uploadedSize <= 0 || $uploadedSize > $maxFileSize) {
    clips_json_response([
        'success' => false,
        'error' => 'Размер видео должен быть от 1 байта до 200 МБ.',
    ], 422);
}

if (!is_uploaded_file($temporaryPath)) {
    clips_json_response([
        'success' => false,
        'error' => 'Файл не был загружен через HTTP POST.',
    ], 422);
}

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions, true)) {
    clips_json_response([
        'success' => false,
        'error' => 'Поддерживаются только mp4, webm и mov.',
    ], 422);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? finfo_file($finfo, $temporaryPath) : false;

if ($finfo) {
    finfo_close($finfo);
}

if (!is_string($mimeType) || !isset($allowedMimeTypes[$mimeType])) {
    clips_json_response([
        'success' => false,
        'error' => 'MIME-тип видео не поддерживается.',
    ], 422);
}

if ($allowedMimeTypes[$mimeType] !== $extension) {
    clips_json_response([
        'success' => false,
        'error' => 'Расширение файла не соответствует MIME-типу.',
    ], 422);
}

clips_prepare_directory($originalDirectory);
clips_prepare_directory($encodedDirectory);
clips_prepare_directory($logDirectory);

$videoId = bin2hex(random_bytes(16));
$originalFilename = $videoId . '.' . $extension;
$encodedFilename = $videoId . '.mp4';
$originalPath = $originalDirectory . DIRECTORY_SEPARATOR . $originalFilename;
$encodedPath = $encodedDirectory . DIRECTORY_SEPARATOR . $encodedFilename;

if (!move_uploaded_file($temporaryPath, $originalPath)) {
    clips_json_response([
        'success' => false,
        'error' => 'Не удалось сохранить оригинал видео.',
    ], 500);
}

clips_encode_video($originalPath, $encodedPath, $ffmpegBinary, $targetWidth, $targetHeight);

$duration = clips_detect_duration($encodedPath, $ffprobeBinary);
$encodedSize = is_file($encodedPath) ? filesize($encodedPath) : false;

if ($encodedSize === false || $encodedSize <= 0) {
    clips_json_response([
        'success' => false,
        'error' => 'Готовое видео не найдено или пустое.',
    ], 500);
}

$originalUrl = clips_public_path($originalPath);
$encodedUrl = clips_public_path($encodedPath);
$postId = null;

if (isset($_POST['save_to_db']) && $_POST['save_to_db'] === '1') {
    if (!isset($_SESSION['user_id'])) {
        clips_json_response([
            'success' => false,
            'error' => 'Для сохранения в post_media нужна авторизация.',
        ], 401);
    }

    $caption = trim((string) ($_POST['caption'] ?? ''));

    try {
        $pdo->beginTransaction();

        $insertPostStmt = $pdo->prepare('INSERT INTO posts (user_id, caption) VALUES (:user_id, :caption)');
        $insertPostStmt->execute([
            'user_id' => (int) $_SESSION['user_id'],
            'caption' => $caption !== '' ? $caption : null,
        ]);

        $postId = (int) $pdo->lastInsertId();

        $insertMediaStmt = $pdo->prepare('
            INSERT INTO post_media (post_id, media_type, media_url, position)
            VALUES (:post_id, :media_type, :media_url, 1)
        ');
        $insertMediaStmt->execute([
            'post_id' => $postId,
            'media_type' => 'video',
            'media_url' => $encodedUrl,
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        clips_log_error('database error: ' . $exception->getMessage());
        clips_json_response([
            'success' => false,
            'error' => 'Видео обработано, но не удалось сохранить запись в БД.',
            'video_id' => $videoId,
            'original_url' => $originalUrl,
            'encoded_url' => $encodedUrl,
            'original_size' => filesize($originalPath),
            'encoded_size' => $encodedSize,
            'duration' => $duration,
        ], 500);
    }
}

clips_json_response([
    'success' => true,
    'video_id' => $videoId,
    'post_id' => $postId,
    'original_url' => $originalUrl,
    'encoded_url' => $encodedUrl,
    'original_size' => filesize($originalPath),
    'encoded_size' => $encodedSize,
    'duration' => $duration,
]);
