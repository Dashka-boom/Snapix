<?php
session_start();
require './config/config.php';

$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $viewerStmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id LIMIT 1');
    $viewerStmt->execute(['id' => (int) $_SESSION['user_id']]);
    $currentUser = $viewerStmt->fetch();
}

$postId = (int) ($_GET['id'] ?? 0);
if ($postId <= 0) {
    header('Location: index.php');
    exit;
}

$postStmt = $pdo->prepare('
    SELECT
        posts.id,
        posts.caption,
        posts.created_at,
        users.id AS author_id,
        users.login AS author_login,
        users.avatar AS author_avatar,
        post_media.media_type,
        post_media.media_url
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.id = :id AND posts.is_deleted = 0
    LIMIT 1
');
$postStmt->execute(['id' => $postId]);
$post = $postStmt->fetch();

if (!$post) {
    header('Location: index.php');
    exit;
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
<body data-page="post">
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">Snapix</a>
            <input type="text" class="search" placeholder="Поиск" disabled>
            <div class="menu">
                <a href="index.php">Лента</a>
                <?php if ($currentUser): ?>
                    <a href="profile.php" class="user-avatar-link" aria-label="Открыть профиль">
                        <?php if (!empty($currentUser['avatar'])): ?>
                            <span class="user-avatar" style="background-image: url('<?php echo htmlspecialchars($currentUser['avatar']); ?>');"></span>
                        <?php else: ?>
                            <span class="user-avatar"><?php echo htmlspecialchars(mb_substr($currentUser['login'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </a>
                <?php else: ?>
                    <a href="login.php">Войти</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main>
        <section class="feed-wrap">
            <article class="feed-card card-surface" id="post-<?php echo (int) $post['id']; ?>">
                <header class="feed-card-header">
                    <div class="feed-header-main">
                        <a href="user.php?id=<?php echo (int) $post['author_id']; ?>" class="feed-author-avatar-link" aria-label="Открыть профиль <?php echo htmlspecialchars($post['author_login']); ?>">
                            <div class="feed-author-avatar"<?php if (!empty($post['author_avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($post['author_avatar']); ?>');"<?php endif; ?>>
                                <?php if (empty($post['author_avatar'])): ?>
                                    <?php echo htmlspecialchars(mb_substr($post['author_login'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                        </a>
                        <div>
                            <a href="user.php?id=<?php echo (int) $post['author_id']; ?>" class="feed-author-name"><strong><?php echo htmlspecialchars($post['author_login']); ?></strong></a>
                        </div>
                    </div>
                </header>
                <div class="feed-card-media">
                    <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                        <video controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                    <?php elseif (!empty($post['media_url'])): ?>
                        <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="Публикация <?php echo htmlspecialchars($post['author_login']); ?>">
                    <?php else: ?>
                        <div class="feed-card-media-placeholder">Медиа не доступно</div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($post['caption'])): ?>
                    <div class="feed-card-body">
                        <div class="feed-card-caption"><?php echo nl2br(htmlspecialchars($post['caption'])); ?></div>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</body>
</html>
