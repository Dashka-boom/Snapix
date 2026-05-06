<?php
session_start();
require './config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$viewerStmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id LIMIT 1');
$viewerStmt->execute(['id' => $_SESSION['user_id']]);
$user = $viewerStmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$postId = (int) ($_GET['id'] ?? $_POST['post_id'] ?? 0);

if ($postId <= 0) {
    header('Location: profile.php');
    exit;
}

$postStmt = $pdo->prepare('
    SELECT posts.id, posts.caption, post_media.media_type, post_media.media_url
    FROM posts
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.id = :id AND posts.user_id = :user_id AND posts.is_deleted = 0
    LIMIT 1
');
$postStmt->execute([
    'id' => $postId,
    'user_id' => $user['id'],
]);
$post = $postStmt->fetch();

if (!$post) {
    header('Location: profile.php');
    exit;
}

$error = '';
$caption = (string) ($post['caption'] ?? '');

function removePostFile(?string $publicPath): void
{
    if (!$publicPath) {
        return;
    }

    $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $publicPath);
    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function storePostMedia(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Выберите фото или видео для публикации.');
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Не удалось загрузить файл. Попробуйте снова.');
    }

    $tmpPath = $file['tmp_name'] ?? '';
    $size = (int) ($file['size'] ?? 0);

    if ($size <= 0 || $size > 50 * 1024 * 1024) {
        throw new RuntimeException('Размер файла должен быть от 1 байта до 50 МБ.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    $allowedTypes = [
        'image/jpeg' => ['type' => 'image', 'ext' => 'jpg'],
        'image/png' => ['type' => 'image', 'ext' => 'png'],
        'image/webp' => ['type' => 'image', 'ext' => 'webp'],
        'video/mp4' => ['type' => 'video', 'ext' => 'mp4'],
        'video/webm' => ['type' => 'video', 'ext' => 'webm'],
        'video/quicktime' => ['type' => 'video', 'ext' => 'mov'],
    ];

    if (!isset($allowedTypes[$mimeType])) {
        throw new RuntimeException('Поддерживаются только JPG, PNG, WEBP, MP4, WEBM или MOV.');
    }

    $uploadDirectory = __DIR__ . '/uploads/posts';

    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Не удалось подготовить папку для загрузки.');
    }

    $fileToken = bin2hex(random_bytes(16));
    $extension = $allowedTypes[$mimeType]['ext'];
    $mediaType = $allowedTypes[$mimeType]['type'];

    $filename = $fileToken . '.' . $extension;
    $targetPath = $uploadDirectory . '/' . $filename;
    $publicPath = 'uploads/posts/' . $filename;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Не удалось сохранить файл на сервере.');
    }

    return [
        'media_type' => $mediaType,
        'media_url' => $publicPath,
        'target_path' => $targetPath,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $caption = trim($_POST['caption'] ?? '');
    $newMedia = null;
    $oldMediaUrl = (string) ($post['media_url'] ?? '');

    try {
        if (isset($_FILES['media']) && ($_FILES['media']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newMedia = storePostMedia($_FILES['media']);
        }

        $pdo->beginTransaction();

        $updatePostStmt = $pdo->prepare('UPDATE posts SET caption = :caption WHERE id = :id AND user_id = :user_id');
        $updatePostStmt->execute([
            'caption' => $caption !== '' ? $caption : null,
            'id' => $post['id'],
            'user_id' => $user['id'],
        ]);

        if ($newMedia !== null) {
            $mediaExistsStmt = $pdo->prepare('SELECT id FROM post_media WHERE post_id = :post_id AND position = 1 LIMIT 1');
            $mediaExistsStmt->execute(['post_id' => $post['id']]);
            $mediaId = $mediaExistsStmt->fetchColumn();

            if ($mediaId) {
                $updateMediaStmt = $pdo->prepare('
                    UPDATE post_media
                    SET media_type = :media_type, media_url = :media_url
                    WHERE id = :id
                ');
                $updateMediaStmt->execute([
                    'media_type' => $newMedia['media_type'],
                    'media_url' => $newMedia['media_url'],
                    'id' => $mediaId,
                ]);
            } else {
                $insertMediaStmt = $pdo->prepare('
                    INSERT INTO post_media (post_id, media_type, media_url, position)
                    VALUES (:post_id, :media_type, :media_url, 1)
                ');
                $insertMediaStmt->execute([
                    'post_id' => $post['id'],
                    'media_type' => $newMedia['media_type'],
                    'media_url' => $newMedia['media_url'],
                ]);
            }
        }

        $pdo->commit();

        if ($newMedia !== null && $oldMediaUrl !== '' && $oldMediaUrl !== $newMedia['media_url']) {
            removePostFile($oldMediaUrl);
        }

        header('Location: profile.php?post_updated=1');
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($newMedia !== null && isset($newMedia['target_path']) && is_file($newMedia['target_path'])) {
            @unlink($newMedia['target_path']);
        }

        $error = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Не удалось обновить публикацию. Попробуйте снова.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <title>Snapix</title>
</head>
<body data-page="edit-post">
    <header class="profile-sticky-nav">
        <div class="profile-nav-inner">
            <a href="profile.php" class="profile-nav-back" aria-label="Назад в профиль">←</a>
            <div class="profile-nav-title">Редактирование публикации</div>
        </div>
    </header>

    <main class="create-post-page">
        <section class="create-post-card card-surface">
            <h1>Редактировать публикацию</h1>
            <p class="create-post-subtitle">Можно изменить подпись и при необходимости заменить фото или видео.</p>

            <div class="edit-post-preview">
                <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                    <video controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                <?php elseif (!empty($post['media_url'])): ?>
                    <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="Текущая публикация">
                <?php else: ?>
                    <div class="feed-card-media-placeholder">Медиа недоступно</div>
                <?php endif; ?>
            </div>

            <form method="post" enctype="multipart/form-data" class="create-post-form">
                <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">

                <div class="field">
                    <label for="media">Новое фото или видео</label>
                    <input id="media" name="media" type="file" accept="image/*,video/*">
                </div>

                <div class="field">
                    <label for="caption">Подпись</label>
                    <textarea id="caption" name="caption" rows="4" maxlength="1200" placeholder="Расскажите, что на публикации..."><?php echo htmlspecialchars($caption); ?></textarea>
                </div>

                <p class="form-status<?php echo $error === '' ? '' : ' is-error'; ?>" aria-live="polite"><?php echo htmlspecialchars($error); ?></p>

                <div class="form-actions">
                    <button type="submit" class="primary-link">Сохранить изменения</button>
                    <a class="secondary-link" href="profile.php">Отмена</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
