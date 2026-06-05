<?php
session_start();
require './config/config.php';
require_once './includes/side-menu.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$error = '';
$caption = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $caption = trim($_POST['caption'] ?? '');

    if (!isset($_FILES['media']) || $_FILES['media']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Выберите фото или видео для публикации.';
    } elseif ($_FILES['media']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Не удалось загрузить файл. Попробуйте снова.';
    } else {
        $tmpPath = $_FILES['media']['tmp_name'];
        $originalName = $_FILES['media']['name'] ?? '';
        $size = (int) ($_FILES['media']['size'] ?? 0);

        if ($size <= 0 || $size > 50 * 1024 * 1024) {
            $error = 'Размер файла должен быть от 1 байта до 50 МБ.';
        } else {
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
                $error = 'Поддерживаются только JPG, PNG, WEBP, MP4, WEBM или MOV.';
            } else {
                $uploadDirectory = __DIR__ . '/uploads/posts';

                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true)) {
                    $error = 'Не удалось подготовить папку для загрузки.';
                } else {
                    $fileToken = bin2hex(random_bytes(16));
                    $extension = $allowedTypes[$mimeType]['ext'];
                    $mediaType = $allowedTypes[$mimeType]['type'];

                    $filename = $fileToken . '.' . $extension;
                    $targetPath = $uploadDirectory . '/' . $filename;
                    $publicPath = 'uploads/posts/' . $filename;

                    if (!move_uploaded_file($tmpPath, $targetPath)) {
                        $error = 'Не удалось сохранить файл на сервере.';
                    } else {
                        try {
                            $pdo->beginTransaction();

                            $insertPostStmt = $pdo->prepare('INSERT INTO posts (user_id, caption) VALUES (:user_id, :caption)');
                            $insertPostStmt->execute([
                                'user_id' => $user['id'],
                                'caption' => $caption !== '' ? $caption : null,
                            ]);

                            $postId = (int) $pdo->lastInsertId();

                            $insertMediaStmt = $pdo->prepare('INSERT INTO post_media (post_id, media_type, media_url, position) VALUES (:post_id, :media_type, :media_url, 1)');
                            $insertMediaStmt->execute([
                                'post_id' => $postId,
                                'media_type' => $mediaType,
                                'media_url' => $publicPath,
                            ]);

                            $pdo->commit();
                            header('Location: profile.php?post_created=1');
                            exit;
                        } catch (Throwable $exception) {
                            $pdo->rollBack();
                            if (is_file($targetPath)) {
                                unlink($targetPath);
                            }
                            $error = 'Не удалось сохранить публикацию. Попробуйте снова.';
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <link rel="icon" href="icon/light theme/logo.png" type="image/png">
    <title>Snapix</title>
</head>
<body data-page="create-post" class="has-side-menu">
    <?php render_side_menu($user); ?>

    <div class="page-glass-nav" aria-hidden="true"></div>

    <main class="create-post-page">
        <section class="create-post-card card-surface">
            <h1>Создать публикацию</h1>
            <p class="create-post-subtitle">Добавьте фото или видео и подпись к публикации</p>

            <form method="post" enctype="multipart/form-data" class="create-post-form">
                <div class="field">
                    <label for="media">Фото или видео</label>
                    <input id="media" name="media" type="file" accept="image/*,video/*" required>
                </div>

                <div class="field">
                    <label for="caption">Подпись</label>
                    <textarea id="caption" name="caption" rows="4" maxlength="1200" placeholder="Расскажите, что на публикации..."><?php echo htmlspecialchars($caption); ?></textarea>
                </div>

                <p class="form-status<?php echo $error === '' ? '' : ' is-error'; ?>" aria-live="polite"><?php echo htmlspecialchars($error); ?></p>

                <div class="form-actions">
                    <button type="submit" class="primary-link">Опубликовать</button>
                    <a class="secondary-link" href="profile.php">Отмена</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
