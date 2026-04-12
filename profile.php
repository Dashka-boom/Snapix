<?php
session_start();
require './config/config.php';

const MAX_PROFILE_UPLOAD_SIZE = 5242880; // 5 MB

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function setProfileFlash(string $message, string $type): void
{
    $_SESSION['profile_flash'] = [
        'message' => $message,
        'type' => $type,
    ];
}

function validateProfileImage(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'Не удалось загрузить файл. Попробуйте ещё раз.'];
    }

    if (($file['size'] ?? 0) > MAX_PROFILE_UPLOAD_SIZE) {
        return [false, 'Файл слишком большой. Максимальный размер — 5 МБ.'];
    }

    $originalName = $file['name'] ?? '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, ['png', 'jpg'], true)) {
        return [false, 'Неверный формат файла. Разрешены только .png и .jpg.'];
    }

    $mimeType = mime_content_type($file['tmp_name']);
    if (!in_array($mimeType, ['image/png', 'image/jpeg'], true)) {
        return [false, 'Файл не является изображением PNG или JPG.'];
    }

    return [true, ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadType = $_POST['upload_type'] ?? '';
    $allowedTypes = [
        'avatar' => 'avatar',
        'cover' => 'profile_cover',
    ];

    if (!isset($allowedTypes[$uploadType])) {
        setProfileFlash('Неизвестный тип загружаемого изображения.', 'error');
        header('Location: profile.php');
        exit;
    }

    if (!isset($_FILES['profile_image'])) {
        setProfileFlash('Файл не найден. Выберите изображение и попробуйте снова.', 'error');
        header('Location: profile.php');
        exit;
    }

    [$isValid, $validationError] = validateProfileImage($_FILES['profile_image']);

    if (!$isValid) {
        setProfileFlash($validationError, 'error');
        header('Location: profile.php');
        exit;
    }

    $uploadDir = __DIR__ . '/uploads/profile';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
    $fileName = sprintf('%s_%s_%s.%s', $uploadType, $_SESSION['user_id'], bin2hex(random_bytes(10)), $extension);
    $targetFile = $uploadDir . '/' . $fileName;
    $publicPath = 'uploads/profile/' . $fileName;

    if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile)) {
        setProfileFlash('Не удалось сохранить изображение. Попробуйте позже.', 'error');
        header('Location: profile.php');
        exit;
    }

    $column = $allowedTypes[$uploadType];
    $updateStmt = $pdo->prepare("UPDATE users SET {$column} = :value WHERE id = :id");
    $updateStmt->execute([
        'value' => $publicPath,
        'id' => $_SESSION['user_id'],
    ]);

    setProfileFlash($uploadType === 'avatar' ? 'Аватарка успешно обновлена.' : 'Фон профиля успешно обновлён.', 'success');
    header('Location: profile.php');
    exit;
}

$flash = $_SESSION['profile_flash'] ?? null;
unset($_SESSION['profile_flash']);

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM followers WHERE follower_id = :id');
$stmt->execute(['id' => $user['id']]);
$followingCount = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM followers WHERE following_id = :id');
$stmt->execute(['id' => $user['id']]);
$followersCount = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = :id AND is_deleted = 0');
$stmt->execute(['id' => $user['id']]);
$postsCount = $stmt->fetchColumn();

$stmt = $pdo->prepare('
    SELECT posts.*, post_media.media_url
    FROM posts
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.user_id = :id AND posts.is_deleted = 0
    ORDER BY posts.created_at DESC
');
$stmt->execute(['id' => $user['id']]);
$posts = $stmt->fetchAll();

$profileCover = $user['profile_cover'] ?? null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="index.css">
    <title>Snapix</title>
</head>
<body data-page="profile">
    <header class="profile-sticky-nav">
        <div class="profile-nav-inner">
            <a href="index.php" class="profile-nav-back" aria-label="На главную">←</a>
            <div class="profile-nav-title">Профиль</div>
        </div>
    </header>

    <main class="profile-page">
        <?php if ($flash): ?>
            <p class="form-status profile-upload-status <?php echo $flash['type'] === 'success' ? 'is-success' : ''; ?>"><?php echo htmlspecialchars($flash['message']); ?></p>
        <?php endif; ?>

        <section class="profile-cover card-surface <?php echo !empty($profileCover) ? 'has-image' : ''; ?>" <?php if (!empty($profileCover)): ?>style="background-image: url('<?php echo htmlspecialchars($profileCover); ?>');"<?php endif; ?>>
            <form class="profile-upload-form profile-cover-upload" method="post" enctype="multipart/form-data">
                <input type="hidden" name="upload_type" value="cover">
                <input id="profile-cover-input" class="profile-file-input" type="file" name="profile_image" accept=".png,.jpg,image/png,image/jpeg">
                <button type="button" class="profile-upload-trigger" data-upload-target="profile-cover-input">Изменить фон</button>
            </form>
        </section>

        <section class="profile-summary card-surface">
            <div class="profile-header">
                <div class="profile-avatar-shell">
                    <form class="profile-upload-form profile-avatar-upload" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="upload_type" value="avatar">
                        <input id="profile-avatar-input" class="profile-file-input" type="file" name="profile_image" accept=".png,.jpg,image/png,image/jpeg">
                        <?php if (!empty($user['avatar'])): ?>
                            <button type="button" class="profile-avatar profile-upload-trigger" style="background-image: url('<?php echo htmlspecialchars($user['avatar']); ?>');" data-upload-target="profile-avatar-input" aria-label="Изменить аватарку"></button>
                        <?php else: ?>
                            <button type="button" class="profile-avatar profile-upload-trigger" data-upload-target="profile-avatar-input" aria-label="Изменить аватарку"><?php echo htmlspecialchars(mb_substr($user['login'], 0, 1)); ?></button>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="profile-main">
                    <h1 class="profile-username"><?php echo htmlspecialchars($user['login']); ?></h1>

                    <div class="profile-metrics">
                        <div class="profile-metric">
                            <strong><?php echo $followingCount; ?></strong>
                            <span>Подписки</span>
                        </div>
                        <div class="profile-metric">
                            <strong><?php echo $followersCount; ?></strong>
                            <span>Подписчики</span>
                        </div>
                        <div class="profile-metric">
                            <strong><?php echo $postsCount; ?></strong>
                            <span>Публикации</span>
                        </div>
                    </div>

                    <?php if (!empty($user['bio'])): ?>
                        <p class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                    <?php endif; ?>
                </div>

                <a href="edit-profile.html" class="secondary-link profile-edit-btn">Изменить профиль</a>
            </div>
        </section>

        <section class="profile-posts card-surface">
            <div class="section-heading">
                <h2>Публикации</h2>
            </div>

            <?php if ($posts): ?>
                <div class="posts-grid">
                    <?php foreach ($posts as $post): ?>
                        <article class="post-card">
                            <?php if (!empty($post['media_url'])): ?>
                                <div class="post-card-media" style="background-image: url('<?php echo htmlspecialchars($post['media_url']); ?>');"></div>
                            <?php else: ?>
                                <div class="post-card-media"></div>
                            <?php endif; ?>
                            <div class="post-card-copy">
                                <p><?php echo htmlspecialchars($post['caption'] ?: 'Без подписи'); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Пока нет публикаций. Добавьте первую публикацию</p>
            <?php endif; ?>
        </section>
    </main>

    <script>
        document.querySelectorAll('.profile-upload-trigger').forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.dataset.uploadTarget;
                const input = document.getElementById(targetId);
                if (input) {
                    input.click();
                }
            });
        });

        document.querySelectorAll('.profile-file-input').forEach((input) => {
            input.addEventListener('change', () => {
                if (input.files && input.files.length > 0) {
                    input.form.submit();
                }
            });
        });
    </script>
</body>
</html>
