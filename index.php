<?php
session_start();
require './config/config.php';

$user = null;

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
}

$feedStmt = $pdo->query('
    SELECT
        posts.id,
        posts.caption,
        posts.created_at,
        users.login,
        users.avatar,
        post_media.media_type,
        post_media.media_url
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.is_deleted = 0
    ORDER BY posts.created_at DESC
    LIMIT 40
');
$feedPosts = $feedStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <title>Snapix</title>
</head>
<body data-page="home">
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">Snapix</a>

            <input type="text" class="search" placeholder="Поиск">

            <div class="menu">
                <a href="#">Reels</a>
                <?php if ($user): ?>
                     <a href="create-post.php" class="header-plus-btn" aria-label="Добавить публикацию">+</a>
                    <a href="profile.php" class="user-avatar-link" aria-label="Открыть профиль">
                        <?php if (!empty($user['avatar'])): ?>
                            <span class="user-avatar" style="background-image: url('<?php echo htmlspecialchars($user['avatar']); ?>');"></span>
                        <?php else: ?>
                            <span class="user-avatar"><?php echo htmlspecialchars(mb_substr($user['login'], 0, 1)); ?></span>
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
      <main>
        <section class="feed-wrap">
            <div class="section-heading">
                <h1>Лента публикаций</h1>
            </div>

            <?php if ($feedPosts): ?>
                <div class="feed-list">
                    <?php foreach ($feedPosts as $post): ?>
                        <article class="feed-card card-surface">
                            <header class="feed-card-header">
                                <div class="feed-author-avatar"<?php if (!empty($post['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($post['avatar']); ?>');"<?php endif; ?>>
                                    <?php if (empty($post['avatar'])): ?>
                                        <?php echo htmlspecialchars(mb_substr($post['login'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($post['login']); ?></strong>
                                </div>
                            </header>

                            <div class="feed-card-media">
                                <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                    <video controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                                <?php elseif (!empty($post['media_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="Публикация пользователя <?php echo htmlspecialchars($post['login']); ?>">
                                <?php else: ?>
                                    <div class="feed-card-media-placeholder">Медиа недоступно</div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($post['caption'])): ?>
                                <div class="feed-card-caption">
                                    <?php echo nl2br(htmlspecialchars($post['caption'])); ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Лента пока пустая. Добавьте первую публикацию.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>