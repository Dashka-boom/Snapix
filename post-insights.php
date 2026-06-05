<?php
session_start();
require './config/config.php';
require_once './includes/side-menu.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$viewerStmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id LIMIT 1');
$viewerStmt->execute(['id' => $_SESSION['user_id']]);
$viewer = $viewerStmt->fetch();

if (!$viewer) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$postId = (int) ($_GET['post_id'] ?? 0);
if ($postId <= 0) {
    header('Location: profile.php');
    exit;
}

$postStmt = $pdo->prepare('
    SELECT posts.id, posts.user_id, users.login
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    WHERE posts.id = :id AND posts.is_deleted = 0
    LIMIT 1
');
$postStmt->execute(['id' => $postId]);
$post = $postStmt->fetch();

if (!$post || (int) $post['user_id'] !== (int) $_SESSION['user_id']) {
    header('Location: profile.php');
    exit;
}

$likesStmt = $pdo->prepare('
    SELECT users.id, users.login, users.avatar, likes.created_at
    FROM likes
    INNER JOIN users ON users.id = likes.user_id
    WHERE likes.post_id = :post_id
    ORDER BY likes.created_at DESC
');
$likesStmt->execute(['post_id' => $postId]);
$likes = $likesStmt->fetchAll();

$repostsStmt = $pdo->prepare('
    SELECT users.id, users.login, users.avatar, reposts.created_at
    FROM reposts
    INNER JOIN users ON users.id = reposts.user_id
    WHERE reposts.post_id = :post_id
    ORDER BY reposts.created_at DESC
');
$repostsStmt->execute(['post_id' => $postId]);
$reposts = $repostsStmt->fetchAll();
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
<body class="has-side-menu">
    <?php render_side_menu($viewer); ?>

    <div class="page-glass-nav" aria-hidden="true"></div>

    <main class="profile-page">
        <section class="profile-posts card-surface">
            <div class="section-heading">
                <h1>Статистика поста</h1>
                <a href="profile.php" class="secondary-link">Назад</a>
            </div>

            <h2>Лайкнули (<?php echo count($likes); ?>)</h2>
            <?php if ($likes): ?>
                <div class="request-list">
                    <?php foreach ($likes as $like): ?>
                        <article class="request-card">
                            <span class="request-user">
                                <span class="request-avatar"<?php if (!empty($like['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($like['avatar']); ?>');"<?php endif; ?>>
                                    <?php if (empty($like['avatar'])): ?><?php echo htmlspecialchars(mb_substr($like['login'], 0, 1)); ?><?php endif; ?>
                                </span>
                                <span class="request-copy"><strong><?php echo htmlspecialchars($like['login']); ?></strong></span>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Пока никто не лайкнул.</p>
            <?php endif; ?>

            <h2 style="margin-top:18px;">Репостнули (<?php echo count($reposts); ?>)</h2>
            <?php if ($reposts): ?>
                <div class="request-list">
                    <?php foreach ($reposts as $repost): ?>
                        <article class="request-card">
                            <span class="request-user">
                                <span class="request-avatar"<?php if (!empty($repost['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($repost['avatar']); ?>');"<?php endif; ?>>
                                    <?php if (empty($repost['avatar'])): ?><?php echo htmlspecialchars(mb_substr($repost['login'], 0, 1)); ?><?php endif; ?>
                                </span>
                                <span class="request-copy"><strong><?php echo htmlspecialchars($repost['login']); ?></strong></span>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Пока никто не репостнул.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
