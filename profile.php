<?php
session_start();
require './config/config.php';

function buildProfileUrl(int $profileUserId, int $currentUserId): string
{
    if ($profileUserId === $currentUserId) {
        return 'profile.php';
    }

    return 'user.php?id=' . $profileUserId;
}

function formatBlockedUntil(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);
    if (!$timestamp) {
        return '';
    }

    return date('d.m.Y H:i', $timestamp);
}

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

$panel = $_GET['panel'] ?? '';
$allowedPanels = ['followers', 'following', 'requests'];
if (!in_array($panel, $allowedPanels, true)) {
    $panel = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = (int) ($_POST['post_id'] ?? 0);
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $redirectPanel = $_POST['redirect_panel'] ?? $panel;

    if ($action === 'delete_post' && $postId > 0) {
        $deleteStmt = $pdo->prepare('UPDATE posts SET is_deleted = 1 WHERE id = :id AND user_id = :user_id');
        $deleteStmt->execute([
            'id' => $postId,
            'user_id' => $user['id'],
        ]);

        header('Location: profile.php?post_deleted=1');
        exit;
    }

    if ($action === 'accept_follow_request' && $requestId > 0) {
        $acceptStmt = $pdo->prepare("
            UPDATE followers
            SET status = 'accepted', declined_until = NULL
            WHERE id = :id AND following_id = :following_id AND status = 'pending'
        ");
        $acceptStmt->execute([
            'id' => $requestId,
            'following_id' => $user['id'],
        ]);

        header('Location: profile.php?panel=requests&request_accepted=1#requests-panel');
        exit;
    }

    if ($action === 'decline_follow_request' && $requestId > 0) {
        $declineStmt = $pdo->prepare("
            UPDATE followers
            SET status = 'declined', declined_until = DATE_ADD(NOW(), INTERVAL 3 DAY)
            WHERE id = :id AND following_id = :following_id AND status = 'pending'
        ");
        $declineStmt->execute([
            'id' => $requestId,
            'following_id' => $user['id'],
        ]);

        header('Location: profile.php?panel=requests&request_declined=1#requests-panel');
        exit;
    }

    if ($action === 'show_panel' && in_array($redirectPanel, $allowedPanels, true)) {
        header('Location: profile.php?panel=' . $redirectPanel . '#connections-panel');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE follower_id = :id AND status = 'accepted'");
$stmt->execute(['id' => $user['id']]);
$followingCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = :id AND status = 'accepted'");
$stmt->execute(['id' => $user['id']]);
$followersCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = :id AND status = 'pending'");
$stmt->execute(['id' => $user['id']]);
$pendingRequestsCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = :id AND is_deleted = 0');
$stmt->execute(['id' => $user['id']]);
$postsCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM saved_posts WHERE user_id = :id');
$stmt->execute(['id' => $user['id']]);
$savedPostsCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('
    SELECT posts.*, post_media.media_url, post_media.media_type
    FROM posts
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.user_id = :id AND posts.is_deleted = 0
    ORDER BY posts.created_at DESC
');
$stmt->execute(['id' => $user['id']]);
$posts = $stmt->fetchAll();

$stmt = $pdo->prepare('
    SELECT posts.*, post_media.media_url, post_media.media_type, users.id AS user_id, users.login
    FROM saved_posts
    INNER JOIN posts ON posts.id = saved_posts.post_id AND posts.is_deleted = 0
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE saved_posts.user_id = :id
    ORDER BY saved_posts.created_at DESC
');
$stmt->execute(['id' => $user['id']]);
$savedPosts = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT followers.id, followers.created_at, users.id AS user_id, users.login, users.avatar
    FROM followers
    INNER JOIN users ON users.id = followers.follower_id
    WHERE followers.following_id = :id AND followers.status = 'pending'
    ORDER BY followers.created_at DESC
");
$stmt->execute(['id' => $user['id']]);
$pendingRequests = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT users.id, users.login, users.avatar
    FROM followers
    INNER JOIN users ON users.id = followers.follower_id
    WHERE followers.following_id = :id AND followers.status = 'accepted'
    ORDER BY users.login ASC
");
$stmt->execute(['id' => $user['id']]);
$followersList = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT users.id, users.login, users.avatar
    FROM followers
    INNER JOIN users ON users.id = followers.following_id
    WHERE followers.follower_id = :id AND followers.status = 'accepted'
    ORDER BY users.login ASC
");
$stmt->execute(['id' => $user['id']]);
$followingList = $stmt->fetchAll();

$postCreated = isset($_GET['post_created']) && $_GET['post_created'] === '1';
$postUpdated = isset($_GET['post_updated']) && $_GET['post_updated'] === '1';
$postDeleted = isset($_GET['post_deleted']) && $_GET['post_deleted'] === '1';
$requestAccepted = isset($_GET['request_accepted']) && $_GET['request_accepted'] === '1';
$requestDeclined = isset($_GET['request_declined']) && $_GET['request_declined'] === '1';
$showRequestsBlock = $panel === 'requests' || !empty($pendingRequests) || $requestAccepted || $requestDeclined;
$showFollowersPanel = $panel === 'followers';
$showFollowingPanel = $panel === 'following';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <title>Snapix</title>
</head>
<body data-page="profile">
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">Snapix</a>
            <input type="text" class="search" placeholder="Поиск">
            <div class="menu">
                <a href="#">Reels</a>
                <a href="connections.php?view=requests" class="notification-bell" data-notification-toggle aria-label="Открыть заявки">
                    <span class="notification-bell-icon">&#128276;</span>
                    <?php if ($pendingRequestsCount > 0): ?>
                        <span class="notification-badge"><?php echo $pendingRequestsCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="profile.php" class="user-avatar-link" aria-label="Открыть профиль">
                    <?php if (!empty($user['avatar'])): ?>
                        <span class="user-avatar" style="background-image: url('<?php echo htmlspecialchars($user['avatar']); ?>');"></span>
                    <?php else: ?>
                        <span class="user-avatar"><?php echo htmlspecialchars(mb_substr($user['login'], 0, 1)); ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </nav>
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
                    <div class="profile-name-row">
                        <h1 class="profile-username"><?php echo htmlspecialchars($user['login']); ?></h1>
                        <a href="create-post.php" class="profile-create-btn" aria-label="Создать публикацию">+</a>
                    </div>

                    <?php if (!empty($user['bio'])): ?>
                        <p class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                    <?php endif; ?>

                    <div class="profile-metrics">
                        <a href="connections.php?view=following" class="profile-metric profile-metric-link">
                            <strong><?php echo $followingCount; ?></strong>
                            <span>Подписки</span>
                        </a>
                        <a href="connections.php?view=followers" class="profile-metric profile-metric-link">
                            <strong><?php echo $followersCount; ?></strong>
                            <span>Подписчики</span>
                        </a>
                        <div class="profile-metric">
                            <strong><?php echo $postsCount; ?></strong>
                            <span>Публикации</span>
                        </div>
                        <div class="profile-metric">
                            <strong><?php echo $savedPostsCount; ?></strong>
                            <span>Избранное</span>
                        </div>
                    </div>
                </div>

                <div class="profile-actions">
                    <a href="edit-profile.php" class="secondary-link profile-edit-btn">Изменить профиль</a>
                    <a href="logout.php" class="secondary-link profile-logout-btn">Выйти</a>
                </div>
            </div>
        </section>

        <div class="notification-popover" id="notificationPopover">
            <div class="notification-popover-header">
                <strong>Заявки</strong>
                <a href="connections.php?view=requests">Открыть все</a>
            </div>

            <?php if ($pendingRequests): ?>
                <div class="notification-popover-list">
                    <?php foreach (array_slice($pendingRequests, 0, 5) as $request): ?>
                        <article class="notification-popover-item">
                            <a href="<?php echo htmlspecialchars(buildProfileUrl((int) $request['user_id'], (int) $user['id'])); ?>" class="request-user">
                                <span class="request-avatar"<?php if (!empty($request['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($request['avatar']); ?>');"<?php endif; ?>>
                                    <?php if (empty($request['avatar'])): ?>
                                        <?php echo htmlspecialchars(mb_substr($request['login'], 0, 1)); ?>
                                    <?php endif; ?>
                                </span>
                                <span class="request-copy">
                                    <strong><?php echo htmlspecialchars($request['login']); ?></strong>
                                    <span>Хочет подружиться с вами</span>
                                </span>
                            </a>
                            <div class="notification-popover-actions">
                                <form method="post">
                                    <input type="hidden" name="action" value="accept_follow_request">
                                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                    <button type="submit" class="primary-link">Принять</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="action" value="decline_follow_request">
                                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                    <button type="submit" class="secondary-link">Отклонить</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="notification-popover-empty">Новых заявок нет.</p>
            <?php endif; ?>
        </div>

        <?php if ($showRequestsBlock): ?>
            <section id="requests-panel" class="profile-posts card-surface">
                <div class="section-heading">
                    <h2>Заявки в подписчики</h2>
                </div>

                <?php if ($requestAccepted): ?>
                    <p class="form-status is-success">Заявка принята. Пользователь добавлен в подписчики.</p>
                <?php endif; ?>

                <?php if ($requestDeclined): ?>
                    <p class="form-status is-success">Заявка отклонена. Повторная заявка временно заблокирована.</p>
                <?php endif; ?>

                <?php if ($pendingRequests): ?>
                    <div class="request-list">
                        <?php foreach ($pendingRequests as $request): ?>
                            <article class="request-card">
                                <a href="<?php echo htmlspecialchars(buildProfileUrl((int) $request['user_id'], (int) $user['id'])); ?>" class="request-user">
                                    <span class="request-avatar"<?php if (!empty($request['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($request['avatar']); ?>');"<?php endif; ?>>
                                        <?php if (empty($request['avatar'])): ?>
                                            <?php echo htmlspecialchars(mb_substr($request['login'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="request-copy">
                                        <strong><?php echo htmlspecialchars($request['login']); ?></strong>
                                        <span>Этот пользователь хочет подружиться с вами</span>
                                    </span>
                                </a>

                                <div class="request-actions">
                                    <form method="post">
                                        <input type="hidden" name="action" value="accept_follow_request">
                                        <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                        <button type="submit" class="primary-link">Принять</button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="action" value="decline_follow_request">
                                        <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                        <button type="submit" class="secondary-link">Отклонить</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">Новых заявок пока нет.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($showFollowersPanel || $showFollowingPanel): ?>
            <section id="connections-panel" class="profile-posts card-surface">
                <div class="section-heading">
                    <h2><?php echo $showFollowersPanel ? 'Подписчики' : 'Подписки'; ?></h2>
                </div>

                <?php $connectionList = $showFollowersPanel ? $followersList : $followingList; ?>
                <?php if ($connectionList): ?>
                    <div class="request-list">
                        <?php foreach ($connectionList as $connectionUser): ?>
                            <a href="<?php echo htmlspecialchars(buildProfileUrl((int) $connectionUser['id'], (int) $user['id'])); ?>" class="request-card request-card-link">
                                <span class="request-user">
                                    <span class="request-avatar"<?php if (!empty($connectionUser['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($connectionUser['avatar']); ?>');"<?php endif; ?>>
                                        <?php if (empty($connectionUser['avatar'])): ?>
                                            <?php echo htmlspecialchars(mb_substr($connectionUser['login'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="request-copy">
                                        <strong><?php echo htmlspecialchars($connectionUser['login']); ?></strong>
                                        <span><?php echo $showFollowersPanel ? 'Подписан на вас' : 'Вы подписаны'; ?></span>
                                    </span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state"><?php echo $showFollowersPanel ? 'Подписчиков пока нет.' : 'Подписок пока нет.'; ?></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="profile-posts card-surface">
            <div class="section-heading">
                <h2>Публикации</h2>
            </div>

            <?php if ($postCreated): ?>
                <p class="form-status is-success">Публикация успешно добавлена.</p>
            <?php endif; ?>

            <?php if ($postUpdated): ?>
                <p class="form-status is-success">Публикация успешно обновлена.</p>
            <?php endif; ?>

            <?php if ($postDeleted): ?>
                <p class="form-status is-success">Публикация удалена.</p>
            <?php endif; ?>

            <?php if ($posts): ?>
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
                                <div class="post-card-actions">
                                    <a href="edit-post.php?id=<?php echo (int) $post['id']; ?>" class="secondary-link post-card-btn">Редактировать</a>
                                    <form method="post" class="post-card-delete-form" onsubmit="return confirm('Удалить эту публикацию?');">
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                        <button type="submit" class="post-card-btn post-card-btn-delete">Удалить</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Пока нет публикаций. Добавьте первую публикацию.</p>
            <?php endif; ?>
        </section>

        <section class="profile-posts card-surface">
            <div class="section-heading">
                <h2>Избранное</h2>
            </div>

            <?php if ($savedPosts): ?>
                <div class="posts-grid">
                    <?php foreach ($savedPosts as $post): ?>
                        <article class="post-card">
                            <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                <video class="post-card-media" controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                            <?php elseif (!empty($post['media_url'])): ?>
                                <div class="post-card-media" style="background-image: url('<?php echo htmlspecialchars($post['media_url']); ?>');"></div>
                            <?php else: ?>
                                <div class="post-card-media"></div>
                            <?php endif; ?>
                            <div class="post-card-copy">
                                <a href="<?php echo htmlspecialchars(buildProfileUrl((int) $post['user_id'], (int) $user['id'])); ?>" class="saved-post-author">
                                    <?php echo htmlspecialchars($post['login']); ?>
                                </a>
                                <p><?php echo htmlspecialchars($post['caption'] ?: 'Без подписи'); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Здесь будут публикации, которые вы добавите в избранное.</p>
            <?php endif; ?>
        </section>
    </main>
    <script>
        (() => {
            const bell = document.querySelector('[data-notification-toggle]');
            const popover = document.getElementById('notificationPopover');

            if (!bell || !popover) return;

            bell.addEventListener('click', (event) => {
                event.preventDefault();
                popover.classList.toggle('is-open');
            });

            document.addEventListener('click', (event) => {
                if (!popover.classList.contains('is-open')) return;
                if (popover.contains(event.target) || bell.contains(event.target)) return;
                popover.classList.remove('is-open');
            });
        })();
    </script>
</body>
</html>

