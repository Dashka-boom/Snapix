<?php
session_start();
require './config/config.php';
require_once './includes/side-menu.php';
require_once './includes/hashtags.php';

$user = null;

if (isset($_SESSION['user_id'])) {
    $userStmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => $_SESSION['user_id']]);
    $user = $userStmt->fetch() ?: null;
}

$query = trim((string) ($_GET['q'] ?? ''));
$searchHashtag = snapix_normalize_hashtag_search_query($query);

$posts = [];

if ($searchHashtag !== '') {
    $searchStmt = $pdo->prepare('
        SELECT
            posts.id,
            posts.caption,
            posts.hashtags,
            posts.created_at,
            users.id AS user_id,
            users.login,
            users.avatar,
            post_media.media_type,
            post_media.media_url
        FROM posts
        INNER JOIN users ON users.id = posts.user_id
        LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
        WHERE posts.is_deleted = 0
          AND LOCATE(:hashtag, CONCAT(" ", posts.hashtags, " ")) > 0
        ORDER BY posts.created_at DESC
        LIMIT 60
    ');
    $searchStmt->execute(['hashtag' => ' ' . $searchHashtag . ' ']);
    $posts = $searchStmt->fetchAll();
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
<body data-page="search" class="has-side-menu">
    <?php render_side_menu($user); ?>

    <main class="hashtag-search-page">
        <section class="hashtag-search-card card-surface">
            <h1>Поиск</h1>
            <p class="hashtag-search-subtitle">Ищите публикации по сохранённым хештегам.</p>

            <form class="hashtag-search-form" method="get" action="search.php">
                <label for="hashtag-search-input">Хештег</label>
                <div class="hashtag-search-row">
                    <input id="hashtag-search-input" type="text" name="q" maxlength="300" placeholder="#snapix" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="primary-link">Найти</button>
                </div>
            </form>
        </section>

        <?php if ($searchHashtag !== ''): ?>
            <section class="hashtag-search-results" aria-label="Результаты поиска">
                <h2>Публикации по хештегу <?php echo htmlspecialchars($searchHashtag); ?></h2>

                <?php if ($posts): ?>
                    <div class="hashtag-search-grid">
                        <?php foreach ($posts as $post): ?>
                            <article class="hashtag-search-post card-surface">
                                <a href="post.php?id=<?php echo (int) $post['id']; ?>" class="hashtag-search-post-media" aria-label="Открыть публикацию">
                                    <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                        <video src="<?php echo htmlspecialchars((string) $post['media_url']); ?>" preload="metadata" muted></video>
                                    <?php elseif (!empty($post['media_url'])): ?>
                                        <img src="<?php echo htmlspecialchars((string) $post['media_url']); ?>" alt="Публикация">
                                    <?php else: ?>
                                        <span>Нет медиа</span>
                                    <?php endif; ?>
                                </a>
                                <div class="hashtag-search-post-body">
                                    <a href="<?php echo $user && (int) $user['id'] === (int) $post['user_id'] ? 'profile.php' : 'user.php?id=' . (int) $post['user_id']; ?>" class="hashtag-search-author">
                                        <?php echo htmlspecialchars((string) $post['login']); ?>
                                    </a>
                                    <?php if (!empty($post['caption'])): ?>
                                        <p><?php echo nl2br(htmlspecialchars((string) $post['caption'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($post['hashtags'])): ?>
                                        <div class="post-viewer-hashtags hashtag-search-tags">
                                            <?php foreach (snapix_split_safe_hashtags((string) $post['hashtags']) as $tag): ?>
                                                <a href="search.php?q=<?php echo urlencode($tag); ?>"><?php echo htmlspecialchars($tag); ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="hashtag-search-empty">По этому хештегу публикаций пока нет.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
