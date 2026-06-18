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
$users = [];
$activeSearchTab = $searchHashtag !== '' ? 'hashtags' : 'users';

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

if ($query !== '') {
    $userSearchStmt = $pdo->prepare('
        SELECT id, login, avatar
        FROM users
        WHERE login LIKE :query
        ORDER BY login ASC
        LIMIT 40
    ');
    $userSearchStmt->execute([
        'query' => '%' . $query . '%'
    ]);
    $users = $userSearchStmt->fetchAll();
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

    <main class="hashtag-search-page search-page">
        <section class="hashtag-search-card search-page-card card-surface">
            <form class="hashtag-search-form search-page-form" method="get" action="search.php">
                <div class="hashtag-search-row search-page-input-row">
                    <input id="hashtag-search-input" type="search" name="q" maxlength="300" placeholder="Поиск" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Поиск">
                    <button type="submit" class="primary-link">Поиск</button>
                </div>
            </form>

            <div class="search-tabs" role="tablist" aria-label="Категории поиска">
                <button type="button" class="search-tab<?php echo $activeSearchTab === 'users' ? ' is-active' : ''; ?>" data-search-tab="users" role="tab" aria-selected="<?php echo $activeSearchTab === 'users' ? 'true' : 'false'; ?>">
                    Пользователи
                </button>

                <button type="button" class="search-tab<?php echo $activeSearchTab === 'hashtags' ? ' is-active' : ''; ?>" data-search-tab="hashtags" role="tab" aria-selected="<?php echo $activeSearchTab === 'hashtags' ? 'true' : 'false'; ?>">
                    Хештеги
                </button>
            </div>

            <div class="search-results">
                <div class="search-results-panel<?php echo $activeSearchTab === 'users' ? ' is-active' : ''; ?>" data-search-panel="users">
    <?php if ($query !== ''): ?>
        <?php if ($users): ?>
            <div class="search-users-grid">
                <?php foreach ($users as $foundUser): ?>
                    <a
                        href="<?php echo $user && (int) $user['id'] === (int) $foundUser['id'] ? 'profile.php' : 'user.php?id=' . (int) $foundUser['id']; ?>"
                        class="search-user-card card-surface"
                    >
                        <span
                            class="search-user-avatar"
                            <?php if (!empty($foundUser['avatar'])): ?>
                                style="background-image: url('<?php echo htmlspecialchars((string) $foundUser['avatar'], ENT_QUOTES, 'UTF-8'); ?>');"
                            <?php endif; ?>
                        >
                            <?php if (empty($foundUser['avatar'])): ?>
                                <?php echo htmlspecialchars(mb_substr((string) $foundUser['login'], 0, 1), ENT_QUOTES, 'UTF-8'); ?>
                            <?php endif; ?>
                        </span>

                        <span class="search-user-login">
                            <?php echo htmlspecialchars((string) $foundUser['login'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="hashtag-search-empty">Пользователи не найдены.</p>
        <?php endif; ?>
    <?php else: ?>
        <p class="hashtag-search-empty">Введите запрос, чтобы найти пользователей.</p>
    <?php endif; ?>
</div>
                <div class="search-results-panel<?php echo $activeSearchTab === 'hashtags' ? ' is-active' : ''; ?>" data-search-panel="hashtags">
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
                    <?php else: ?>
                        <p class="hashtag-search-empty">Введите хештег, например #snapix.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
    <script>
        document.querySelectorAll('[data-search-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var tabName = tab.getAttribute('data-search-tab');
                document.querySelectorAll('[data-search-tab]').forEach(function (item) {
                    var isActive = item.getAttribute('data-search-tab') === tabName;
                    item.classList.toggle('is-active', isActive);
                    item.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                document.querySelectorAll('[data-search-panel]').forEach(function (panel) {
                    panel.classList.toggle('is-active', panel.getAttribute('data-search-panel') === tabName);
                });
            });
        });
    </script>
</body>
</html>
