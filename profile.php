<?php
session_start();
require './config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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
        <section class="profile-cover card-surface<?php echo !empty($user['background_image']) ? ' has-image' : ''; ?>"<?php if (!empty($user['background_image'])): ?> style="background-image: url('<?php echo htmlspecialchars($user['background_image']); ?>');"<?php endif; ?>></section>

        <section class="profile-summary card-surface">
            <div class="profile-header">
                <div class="profile-avatar-shell">
                    <?php if (!empty($user['avatar'])): ?>
                        <div class="profile-avatar" style="background-image: url('<?php echo htmlspecialchars($user['avatar']); ?>');"></div>
                    <?php else: ?>
                        <div class="profile-avatar"><?php echo htmlspecialchars(mb_substr($user['login'], 0, 1)); ?></div>
                    <?php endif; ?>
                </div>

                <div class="profile-main">
                    <h1 class="profile-username"><?php echo htmlspecialchars($user['login']); ?></h1>
                    <?php if (!empty($user['bio'])): ?>
                        <p class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
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

                <a href="edit-profile.php" class="secondary-link profile-edit-btn">Изменить профиль</a>
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
</body>
</html>