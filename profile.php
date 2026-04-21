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
    $commentsPostId = 0;
    $postId = (int) ($_POST['post_id'] ?? 0);
    $ownerId = (int) ($_POST['owner_id'] ?? 0);
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

    if ($postId > 0) {
        $postExistsStmt = $pdo->prepare('SELECT id FROM posts WHERE id = :id AND is_deleted = 0');
        $postExistsStmt->execute(['id' => $postId]);
        $postExists = (bool) $postExistsStmt->fetchColumn();

        if ($postExists && $action === 'toggle_like') {
            $likeExistsStmt = $pdo->prepare('SELECT id FROM likes WHERE user_id = :user_id AND post_id = :post_id');
            $likeExistsStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);
            $likeId = $likeExistsStmt->fetchColumn();

            if ($likeId) {
                $deleteLikeStmt = $pdo->prepare('DELETE FROM likes WHERE id = :id');
                $deleteLikeStmt->execute(['id' => $likeId]);
            } else {
                $insertLikeStmt = $pdo->prepare('INSERT INTO likes (user_id, post_id) VALUES (:user_id, :post_id)');
                $insertLikeStmt->execute([
                    'user_id' => $user['id'],
                    'post_id' => $postId,
                ]);
            }
        }

        if ($postExists && $action === 'toggle_save') {
            $saveExistsStmt = $pdo->prepare('SELECT id FROM saved_posts WHERE user_id = :user_id AND post_id = :post_id');
            $saveExistsStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);
            $saveId = $saveExistsStmt->fetchColumn();

            if ($saveId) {
                $deleteSaveStmt = $pdo->prepare('DELETE FROM saved_posts WHERE id = :id');
                $deleteSaveStmt->execute(['id' => $saveId]);
            } else {
                $insertSaveStmt = $pdo->prepare('INSERT INTO saved_posts (user_id, post_id) VALUES (:user_id, :post_id)');
                $insertSaveStmt->execute([
                    'user_id' => $user['id'],
                    'post_id' => $postId,
                ]);
            }
        }

        if ($postExists && $action === 'add_comment') {
            $commentText = trim($_POST['comment_text'] ?? '');
            if ($commentText !== '') {
                $insertCommentStmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, comment_text) VALUES (:post_id, :user_id, :comment_text)');
                $insertCommentStmt->execute([
                    'post_id' => $postId,
                    'user_id' => $user['id'],
                    'comment_text' => mb_substr($commentText, 0, 1000),
                ]);
                $commentsPostId = $postId;
            }
        }

        if ($postExists && $action === 'add_repost') {
            $repostExistsStmt = $pdo->prepare('SELECT id FROM reposts WHERE user_id = :user_id AND post_id = :post_id');
            $repostExistsStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);
            if (!$repostExistsStmt->fetchColumn()) {
                $insertRepostStmt = $pdo->prepare('INSERT INTO reposts (user_id, post_id) VALUES (:user_id, :post_id)');
                $insertRepostStmt->execute([
                    'user_id' => $user['id'],
                    'post_id' => $postId,
                ]);
            }
        }

        if ($postExists && $action === 'pin_post' && $ownerId === (int) $user['id']) {
            $pdo->prepare('INSERT IGNORE INTO pinned_posts (user_id, post_id) VALUES (:user_id, :post_id)')
                ->execute(['user_id' => $user['id'], 'post_id' => $postId]);
        }

        if ($postExists && $action === 'hide_post' && $ownerId !== (int) $user['id']) {
            $pdo->prepare('INSERT IGNORE INTO hidden_posts (user_id, post_id) VALUES (:user_id, :post_id)')
                ->execute(['user_id' => $user['id'], 'post_id' => $postId]);
        }

        if ($postExists && $action === 'report_post' && $ownerId !== (int) $user['id']) {
            $pdo->prepare('INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text) VALUES (:reporter_user_id, :target_user_id, :reason_text)')
                ->execute([
                    'reporter_user_id' => $user['id'],
                    'target_user_id' => $ownerId > 0 ? $ownerId : null,
                    'reason_text' => 'Жалоба на пост #' . $postId,
                ]);
        }

        if ($postExists && $action === 'report_post_user' && $ownerId !== (int) $user['id']) {
            $pdo->prepare('INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text) VALUES (:reporter_user_id, :target_user_id, :reason_text)')
                ->execute([
                    'reporter_user_id' => $user['id'],
                    'target_user_id' => $ownerId > 0 ? $ownerId : null,
                    'reason_text' => 'Жалоба на пользователя через пост #' . $postId,
                ]);
        }

        if ($commentsPostId > 0) {
            header('Location: profile.php?comments_post=' . $commentsPostId);
            exit;
        }

        header('Location: profile.php');
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
    SELECT posts.*, post_media.media_url, post_media.media_type,
           users.id AS author_user_id, users.login AS author_login,
           (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS likes_count,
           (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.is_deleted = 0) AS comments_count,
           (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id) AS reposts_count,
           (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.user_id = :viewer_id) AS is_liked,
           (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id) AS saves_count,
           (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id AND saved_posts.user_id = :viewer_id) AS is_saved,
           (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id AND reposts.user_id = :viewer_id) AS is_reposted,
           (SELECT COUNT(*) FROM pinned_posts WHERE pinned_posts.post_id = posts.id AND pinned_posts.user_id = :viewer_id) AS is_pinned
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.user_id = :id AND posts.is_deleted = 0
    ORDER BY posts.created_at DESC
');
$stmt->execute([
    'id' => $user['id'],
    'viewer_id' => $user['id'],
]);
$posts = $stmt->fetchAll();

$stmt = $pdo->prepare('
    SELECT posts.*, post_media.media_url, post_media.media_type, users.id AS author_user_id, users.login AS author_login,
           (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS likes_count,
           (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.is_deleted = 0) AS comments_count,
           (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id) AS reposts_count,
           (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.user_id = :viewer_id) AS is_liked,
           (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id) AS saves_count,
           (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id AND saved_posts.user_id = :viewer_id) AS is_saved,
           (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id AND reposts.user_id = :viewer_id) AS is_reposted,
           (SELECT COUNT(*) FROM pinned_posts WHERE pinned_posts.post_id = posts.id AND pinned_posts.user_id = :viewer_id) AS is_pinned
    FROM saved_posts
    INNER JOIN posts ON posts.id = saved_posts.post_id AND posts.is_deleted = 0
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE saved_posts.user_id = :id
    ORDER BY saved_posts.created_at DESC
');
$stmt->execute([
    'id' => $user['id'],
    'viewer_id' => $user['id'],
]);
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

$commentMap = [];
$repostMap = [];
$allProfilePosts = array_merge($posts, $savedPosts);
if ($allProfilePosts) {
    $postIds = array_values(array_unique(array_map(static fn($post): int => (int) $post['id'], $allProfilePosts)));
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));

    $commentsStmt = $pdo->prepare("
        SELECT comments.id, comments.post_id, comments.comment_text, users.id AS user_id, users.login
        FROM comments
        INNER JOIN users ON users.id = comments.user_id
        WHERE comments.is_deleted = 0
          AND comments.post_id IN ($placeholders)
        ORDER BY comments.post_id ASC, comments.created_at DESC, comments.id DESC
    ");
    $commentsStmt->execute($postIds);
    foreach ($commentsStmt->fetchAll() as $comment) {
        $currentPostId = (int) $comment['post_id'];
        if (!isset($commentMap[$currentPostId])) {
            $commentMap[$currentPostId] = [];
        }
        $commentMap[$currentPostId][] = $comment;
    }

    $repostsStmt = $pdo->prepare("
        SELECT reposts.post_id, users.login
        FROM reposts
        INNER JOIN users ON users.id = reposts.user_id
        WHERE reposts.post_id IN ($placeholders)
        ORDER BY reposts.created_at DESC, reposts.id DESC
    ");
    $repostsStmt->execute($postIds);
    foreach ($repostsStmt->fetchAll() as $repost) {
        $currentPostId = (int) $repost['post_id'];
        if (!isset($repostMap[$currentPostId])) {
            $repostMap[$currentPostId] = [];
        }
        if (count($repostMap[$currentPostId]) < 3) {
            $repostMap[$currentPostId][] = $repost['login'];
        }
    }
}

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
                        <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                        <?php $postReposters = $repostMap[(int) $post['id']] ?? []; ?>
                        <article class="post-card">
                            <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                <video class="post-card-media" controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                            <?php elseif (!empty($post['media_url'])): ?>
                                <div class="post-card-media" style="background-image: url('<?php echo htmlspecialchars($post['media_url']); ?>');"></div>
                            <?php else: ?>
                                <div class="post-card-media"></div>
                            <?php endif; ?>

                            <div class="post-card-copy">
                                <div class="feed-card-header">
                                    <div class="feed-header-main"><strong><?php echo htmlspecialchars($user['login']); ?></strong></div>
                                    <div class="post-menu-wrap">
                                        <button type="button" class="post-menu-toggle" data-post-menu="profile-post-menu-<?php echo (int) $post['id']; ?>" aria-label="Действия с публикацией">&#8942;</button>
                                        <div class="post-menu" id="profile-post-menu-<?php echo (int) $post['id']; ?>">
                                            <form method="post"><input type="hidden" name="action" value="delete_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $user['id']; ?>"><button type="submit">Удалить пост</button></form>
                                            <a href="edit-post.php?id=<?php echo (int) $post['id']; ?>">Редактировать пост</a>
                                            <form method="post"><input type="hidden" name="action" value="pin_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $user['id']; ?>"><button type="submit"><?php echo (int) $post['is_pinned'] > 0 ? 'Уже закреплено' : 'Закрепить пост в личном профиле'; ?></button></form>
                                            <a href="post-insights.php?post_id=<?php echo (int) $post['id']; ?>">Кто посмотрел пост</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="feed-card-stats"><span>Действия с публикацией</span></div>
                                <div class="feed-card-buttons" style="margin-top: 10px;">
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_like"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><span aria-hidden="true">&#9829;</span></button></form><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-profile-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><span aria-hidden="true">&#128172;</span></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_save"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><span aria-hidden="true">&#128278;</span></button></form><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="add_repost"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><span aria-hidden="true">&#128257;</span></button></form><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                </div>
                                <?php if ($postReposters): ?>
                                    <p class="feed-reposts-note">Репостнули: <?php echo htmlspecialchars(implode(', ', $postReposters)); ?></p>
                                <?php endif; ?>
                                <div class="comments-modal<?php echo (isset($_GET['comments_post']) && (int) $_GET['comments_post'] === (int) $post['id']) ? ' is-open' : ''; ?>" id="comments-modal-profile-<?php echo (int) $post['id']; ?>">
                                    <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-profile-<?php echo (int) $post['id']; ?>"></div>
                                    <div class="comments-modal-dialog">
                                        <div class="comments-modal-header">
                                            <h3>Комментарии</h3>
                                            <button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-profile-<?php echo (int) $post['id']; ?>" aria-label="Закрыть">&times;</button>
                                        </div>
                                        <div class="comments-modal-body">
                                    <?php if ($postComments): ?>
                                        <?php foreach ($postComments as $comment): ?>
                                            <div class="comment-item">
                                                <strong><?php echo htmlspecialchars($comment['login']); ?></strong>
                                                <p><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="comments-empty">Пока нет комментариев.</p>
                                    <?php endif; ?>
                                        </div>
                                        <form method="post" class="comment-form comments-modal-form">
                                            <input type="hidden" name="action" value="add_comment">
                                            <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                            <textarea name="comment_text" rows="2" maxlength="1000" placeholder="Напишите комментарий..."></textarea>
                                            <button type="submit" class="primary-link">Отправить</button>
                                        </form>
                                    </div>
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
                        <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                        <?php $postReposters = $repostMap[(int) $post['id']] ?? []; ?>
                        <article class="post-card">
                            <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                <video class="post-card-media" controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                            <?php elseif (!empty($post['media_url'])): ?>
                                <div class="post-card-media" style="background-image: url('<?php echo htmlspecialchars($post['media_url']); ?>');"></div>
                            <?php else: ?>
                                <div class="post-card-media"></div>
                            <?php endif; ?>
                            <div class="post-card-copy">
                                <div class="feed-card-header">
                                    <div class="feed-header-main"><strong><?php echo htmlspecialchars($post['author_login']); ?></strong></div>
                                    <div class="post-menu-wrap">
                                        <button type="button" class="post-menu-toggle" data-post-menu="profile-saved-post-menu-<?php echo (int) $post['id']; ?>" aria-label="Действия с публикацией">&#8942;</button>
                                        <div class="post-menu" id="profile-saved-post-menu-<?php echo (int) $post['id']; ?>">
                                            <?php if ((int) $post['author_user_id'] === (int) $user['id']): ?>
                                                <form method="post"><input type="hidden" name="action" value="delete_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $user['id']; ?>"><button type="submit">Удалить пост</button></form>
                                                <a href="edit-post.php?id=<?php echo (int) $post['id']; ?>">Редактировать пост</a>
                                                <form method="post"><input type="hidden" name="action" value="pin_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $user['id']; ?>"><button type="submit"><?php echo (int) $post['is_pinned'] > 0 ? 'Уже закреплено' : 'Закрепить пост в личном профиле'; ?></button></form>
                                                <a href="post-insights.php?post_id=<?php echo (int) $post['id']; ?>">Кто посмотрел пост</a>
                                            <?php else: ?>
                                                <form method="post"><input type="hidden" name="action" value="report_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $post['author_user_id']; ?>"><button type="submit">Жалоба на пост</button></form>
                                                <form method="post"><input type="hidden" name="action" value="report_post_user"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $post['author_user_id']; ?>"><button type="submit">Жалоба на пользователя</button></form>
                                                <form method="post"><input type="hidden" name="action" value="hide_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $post['author_user_id']; ?>"><button type="submit">Мне не интересна эта публикация</button></form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars(buildProfileUrl((int) $post['author_user_id'], (int) $user['id'])); ?>" class="saved-post-author">
                                    <?php echo htmlspecialchars($post['author_login']); ?>
                                </a>
                                <p><?php echo htmlspecialchars($post['caption'] ?: 'Без подписи'); ?></p>
                                <div class="feed-card-stats"><span>Действия с публикацией</span></div>
                                <div class="feed-card-buttons" style="margin-top: 10px;">
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_like"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><span aria-hidden="true">&#9829;</span></button></form><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-saved-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><span aria-hidden="true">&#128172;</span></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_save"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><span aria-hidden="true">&#128278;</span></button></form><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="add_repost"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><span aria-hidden="true">&#128257;</span></button></form><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                </div>
                                <?php if ($postReposters): ?>
                                    <p class="feed-reposts-note">Репостнули: <?php echo htmlspecialchars(implode(', ', $postReposters)); ?></p>
                                <?php endif; ?>
                                <div class="comments-modal<?php echo (isset($_GET['comments_post']) && (int) $_GET['comments_post'] === (int) $post['id']) ? ' is-open' : ''; ?>" id="comments-modal-saved-<?php echo (int) $post['id']; ?>">
                                    <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-saved-<?php echo (int) $post['id']; ?>"></div>
                                    <div class="comments-modal-dialog">
                                        <div class="comments-modal-header">
                                            <h3>Комментарии</h3>
                                            <button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-saved-<?php echo (int) $post['id']; ?>" aria-label="Закрыть">&times;</button>
                                        </div>
                                        <div class="comments-modal-body">
                                    <?php if ($postComments): ?>
                                        <?php foreach ($postComments as $comment): ?>
                                            <div class="comment-item">
                                                <strong><?php echo htmlspecialchars($comment['login']); ?></strong>
                                                <p><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="comments-empty">Пока нет комментариев.</p>
                                    <?php endif; ?>
                                        </div>
                                        <form method="post" class="comment-form comments-modal-form">
                                            <input type="hidden" name="action" value="add_comment">
                                            <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                            <textarea name="comment_text" rows="2" maxlength="1000" placeholder="Напишите комментарий..."></textarea>
                                            <button type="submit" class="primary-link">Отправить</button>
                                        </form>
                                    </div>
                                </div>
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

        document.querySelectorAll('.js-open-comments-modal').forEach((button) => {
            button.addEventListener('click', () => {
                const modalId = button.getAttribute('data-modal');
                const modal = modalId ? document.getElementById(modalId) : null;
                if (modal) {
                    modal.classList.add('is-open');
                }
            });
        });

        document.querySelectorAll('.js-close-comments-modal').forEach((button) => {
            button.addEventListener('click', () => {
                const modalId = button.getAttribute('data-modal');
                const modal = modalId ? document.getElementById(modalId) : null;
                if (modal) {
                    modal.classList.remove('is-open');
                }
            });
        });

        document.querySelectorAll('.post-menu-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const menuId = button.getAttribute('data-post-menu');
                const menu = menuId ? document.getElementById(menuId) : null;
                if (menu) {
                    menu.classList.toggle('is-open');
                }
            });
        });

        document.addEventListener('click', (event) => {
            document.querySelectorAll('.post-menu').forEach((menu) => {
                const wrap = menu.closest('.post-menu-wrap');
                if (wrap && !wrap.contains(event.target)) {
                    menu.classList.remove('is-open');
                }
            });
        });
    </script>
</body>
</html>
