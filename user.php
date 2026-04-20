<?php
session_start();
require './config/config.php';

function profileDestination(int $targetUserId, ?int $currentUserId): string
{
    if ($currentUserId !== null && $targetUserId === $currentUserId) {
        return 'profile.php';
    }

    return 'user.php?id=' . $targetUserId;
}

$currentUser = null;

if (isset($_SESSION['user_id'])) {
    $viewerStmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id');
    $viewerStmt->execute(['id' => $_SESSION['user_id']]);
    $currentUser = $viewerStmt->fetch();
}

$targetUserId = (int) ($_GET['id'] ?? 0);

if ($targetUserId <= 0) {
    header('Location: index.php');
    exit;
}

if ($currentUser && $targetUserId === (int) $currentUser['id']) {
    header('Location: profile.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $targetUserId]);
$profileUser = $stmt->fetch();

if (!$profileUser) {
    http_response_code(404);
}

$followingCount = 0;
$followersCount = 0;
$postsCount = 0;
$posts = [];
$isPrivateProfile = false;

if ($profileUser) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM followers WHERE follower_id = :id');
    $stmt->execute(['id' => $profileUser['id']]);
    $followingCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM followers WHERE following_id = :id');
    $stmt->execute(['id' => $profileUser['id']]);
    $followersCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = :id AND is_deleted = 0');
    $stmt->execute(['id' => $profileUser['id']]);
    $postsCount = (int) $stmt->fetchColumn();

    $isPrivateProfile = !empty($profileUser['is_private']);

    if (!$isPrivateProfile) {
        $stmt = $pdo->prepare('
            SELECT posts.*, post_media.media_url, post_media.media_type
            FROM posts
            LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
            WHERE posts.user_id = :id AND posts.is_deleted = 0
            ORDER BY posts.created_at DESC
        ');
        $stmt->execute(['id' => $profileUser['id']]);
        $posts = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <title><?php echo $profileUser ? htmlspecialchars($profileUser['login']) . ' - Snapix' : 'Профиль не найден - Snapix'; ?></title>
</head>
<body data-page="user-profile">
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">Snapix</a>
            <input type="text" class="search" placeholder="Поиск">
            <div class="menu">
                <a href="#">Reels</a>
                <?php if ($currentUser): ?>
                    <a href="create-post.php" class="header-plus-btn" aria-label="Добавить публикацию">+</a>
                    <a href="profile.php" class="user-avatar-link" aria-label="Открыть профиль">
                        <?php if (!empty($currentUser['avatar'])): ?>
                            <span class="user-avatar" style="background-image: url('<?php echo htmlspecialchars($currentUser['avatar']); ?>');"></span>
                        <?php else: ?>
                            <span class="user-avatar"><?php echo htmlspecialchars(mb_substr($currentUser['login'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </a>
                <?php else: ?>
                    <div class="auth-actions">
                        <a href="login.php">Войти</a>
                        <a href="register.php" class="auth">Регистрация</a>
                    </div>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main class="profile-page">
        <?php if (!$profileUser): ?>
            <section class="profile-posts card-surface">
                <div class="section-heading">
                    <h1>Профиль не найден</h1>
                </div>
                <p class="empty-state">Похоже, этого пользователя не существует или ссылка устарела.</p>
            </section>
        <?php else: ?>
            <section class="profile-cover card-surface<?php echo !empty($profileUser['background_image']) ? ' has-image' : ''; ?>"<?php if (!empty($profileUser['background_image'])): ?> style="background-image: url('<?php echo htmlspecialchars($profileUser['background_image']); ?>');"<?php endif; ?>></section>

            <section class="profile-summary card-surface">
                <div class="profile-header">
                    <div class="profile-avatar-shell">
                        <?php if (!empty($profileUser['avatar'])): ?>
                            <div class="profile-avatar" style="background-image: url('<?php echo htmlspecialchars($profileUser['avatar']); ?>');"></div>
                        <?php else: ?>
                            <div class="profile-avatar"><?php echo htmlspecialchars(mb_substr($profileUser['login'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="profile-main">
                        <div class="profile-name-row">
                            <h1 class="profile-username"><?php echo htmlspecialchars($profileUser['login']); ?></h1>
                        </div>

                        <?php if (!empty($profileUser['bio'])): ?>
                            <p class="profile-bio"><?php echo nl2br(htmlspecialchars($profileUser['bio'])); ?></p>
                        <?php endif; ?>

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
                    </div>

                    <div class="profile-actions">
                        <a href="index.php" class="secondary-link profile-edit-btn">Назад в ленту</a>
                    </div>
                </div>
            </section>

            <section class="profile-posts card-surface">
                <div class="section-heading">
                    <h2>Публикации</h2>
                </div>

                <?php if ($isPrivateProfile): ?>
                    <div class="private-profile-notice">
                        <h3>Этот пользователь считает это слишком личным</h3>
                        <p>Профиль закрыт, поэтому публикации сейчас недоступны для просмотра.</p>
                    </div>
                <?php elseif ($posts): ?>
                    <div class="posts-grid">
                        <?php foreach ($posts as $post): ?>
                            <article class="post-card">
                                <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                    <video class="post-card-media" controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                                <?php elseif (!empty($post['media_url'])): ?>
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
                    <p class="empty-state">У этого пользователя пока нет публикаций.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
