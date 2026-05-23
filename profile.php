<?php
session_start();
require './config/config.php';
require_once './includes/side-menu.php';
require './includes/icons.php';
require './includes/post-actions.php';

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
    $isAjaxPostAction = snapix_is_ajax_request() && in_array($action, ['toggle_like', 'toggle_save', 'add_comment', 'add_repost'], true);
    $ajaxExtra = [];
    $postId = (int) ($_POST['post_id'] ?? 0);
    $ownerId = (int) ($_POST['owner_id'] ?? 0);
    $postExists = false;
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
                $ajaxExtra['liked'] = false;
            } else {
                $insertLikeStmt = $pdo->prepare('INSERT INTO likes (user_id, post_id) VALUES (:user_id, :post_id)');
                $insertLikeStmt->execute([
                    'user_id' => $user['id'],
                    'post_id' => $postId,
                ]);
                $ajaxExtra['liked'] = true;
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
                $ajaxExtra['saved'] = false;
            } else {
                $insertSaveStmt = $pdo->prepare('INSERT INTO saved_posts (user_id, post_id) VALUES (:user_id, :post_id)');
                $insertSaveStmt->execute([
                    'user_id' => $user['id'],
                    'post_id' => $postId,
                ]);
                $ajaxExtra['saved'] = true;
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
                $ajaxExtra['comment'] = [
                    'login' => (string) $user['login'],
                    'profile_url' => buildProfileUrl((int) $user['id'], (int) $user['id']),
                    'text' => mb_substr($commentText, 0, 1000),
                ];
            } elseif ($isAjaxPostAction) {
                snapix_send_post_action_error('empty_comment');
            }
        }

        if ($postExists && $action === 'add_repost') {
            $repostExistsStmt = $pdo->prepare('SELECT id FROM reposts WHERE user_id = :user_id AND post_id = :post_id');
            $repostExistsStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);
            $repostId = $repostExistsStmt->fetchColumn();
            if (!$repostId) {
                $insertRepostStmt = $pdo->prepare('INSERT IGNORE INTO reposts (user_id, post_id) VALUES (:user_id, :post_id)');
                $insertRepostStmt->execute([
                    'user_id' => $user['id'],
                    'post_id' => $postId,
                ]);
                $isRepostedNow = true;
            } else {
                $deleteRepostStmt = $pdo->prepare('DELETE FROM reposts WHERE id = :id AND user_id = :user_id');
                $deleteRepostStmt->execute([
                    'id' => (int) $repostId,
                    'user_id' => $user['id'],
                ]);
                $isRepostedNow = false;
            }

            $ajaxExtra['reposted'] = $isRepostedNow;
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
            $reportReason = trim((string) ($_POST['report_reason'] ?? ''));
            $pdo->prepare('INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text) VALUES (:reporter_user_id, :target_user_id, :reason_text)')
                ->execute([
                    'reporter_user_id' => $user['id'],
                    'target_user_id' => $ownerId > 0 ? $ownerId : null,
                    'reason_text' => mb_substr($reportReason !== '' ? $reportReason : ('Жалоба на пост #' . $postId), 0, 1000),
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

        if ($isAjaxPostAction) {
            if (!$postExists) {
                snapix_send_post_action_error('post_not_found', 404);
            }

            snapix_send_post_action_json($pdo, $postId, (int) $user['id'], $ajaxExtra);
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

$stmt = $pdo->prepare('
    SELECT COUNT(*)
    FROM messages m
    INNER JOIN chats c ON c.id = m.chat_id
    WHERE (c.user_one_id = :user_id OR c.user_two_id = :user_id)
      AND m.sender_id != :user_id
      AND m.is_read = 0
');
$stmt->execute(['user_id' => $user['id']]);
$unreadMessagesCount = (int) $stmt->fetchColumn();

$shareRecipientsStmt = $pdo->prepare("
    SELECT
        users.id,
        users.login,
        users.avatar,
        EXISTS(
            SELECT 1
            FROM followers reverse_follow
            WHERE reverse_follow.follower_id = users.id
              AND reverse_follow.following_id = :user_id
              AND reverse_follow.status = 'accepted'
        ) AS is_mutual
    FROM followers
    INNER JOIN users ON users.id = followers.following_id
    WHERE followers.follower_id = :user_id
      AND followers.status = 'accepted'
    ORDER BY is_mutual DESC, users.login ASC
");
$shareRecipientsStmt->execute(['user_id' => $user['id']]);
$shareRecipients = $shareRecipientsStmt->fetchAll();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = :id AND is_deleted = 0');
$stmt->execute(['id' => $user['id']]);
$postsCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM saved_posts WHERE user_id = :id');
$stmt->execute(['id' => $user['id']]);
$savedPostsCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM reposts WHERE user_id = :id');
$stmt->execute(['id' => $user['id']]);
$repostsCount = (int) $stmt->fetchColumn();

$postStatsSql = "
    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS likes_count,
    (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.is_deleted = 0) AS comments_count,
    (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id) AS reposts_count,
    (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id) AS saves_count,
    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.user_id = :viewer_id) AS is_liked,
    (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id AND saved_posts.user_id = :viewer_id) AS is_saved,
    (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id AND reposts.user_id = :viewer_id) AS is_reposted,
    (SELECT COUNT(*) FROM pinned_posts WHERE pinned_posts.post_id = posts.id AND pinned_posts.user_id = :viewer_id) AS is_pinned
";

$stmt = $pdo->prepare("
    SELECT posts.*, post_media.media_url, post_media.media_type,
           users.id AS author_user_id, users.login AS author_login,
           $postStatsSql
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.user_id = :id AND posts.is_deleted = 0
    ORDER BY posts.created_at DESC
");
$stmt->execute([
    'id' => $user['id'],
    'viewer_id' => $user['id'],
]);
$posts = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT posts.*, post_media.media_url, post_media.media_type, users.id AS author_user_id, users.login AS author_login,
           $postStatsSql
    FROM saved_posts
    INNER JOIN posts ON posts.id = saved_posts.post_id AND posts.is_deleted = 0
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE saved_posts.user_id = :id
    ORDER BY saved_posts.created_at DESC
");
$stmt->execute([
    'id' => $user['id'],
    'viewer_id' => $user['id'],
]);
$savedPosts = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        posts.id,
        posts.user_id AS author_user_id,
        posts.caption,
        posts.created_at,
        post_media.media_url,
        post_media.media_type,
        users.login AS author_login,
        users.avatar AS author_avatar,
        $postStatsSql
    FROM (
        SELECT post_id, MAX(created_at) AS last_repost_at
        FROM reposts
        WHERE user_id = :id
        GROUP BY post_id
    ) user_reposts
    INNER JOIN posts ON posts.id = user_reposts.post_id AND posts.is_deleted = 0
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    ORDER BY user_reposts.last_repost_at DESC
");
$stmt->execute([
    'id' => $user['id'],
    'viewer_id' => $user['id'],
]);
$repostedPosts = $stmt->fetchAll();

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
<body data-page="profile" class="has-side-menu">
    <?php render_side_menu($user); ?>

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
                        <?php if (!empty($user['is_private'])): ?>
                            <img src="icon/light theme/closed account.png" alt="Закрытый профиль" class="profile-private-icon">
                        <?php endif; ?>
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
                    <a href="create-post.php" class="secondary-link profile-logout-btn">Добавить публикацию</a>
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
            <div class="profile-post-tabs" role="tablist" aria-label="Разделы профиля">
                <button type="button" class="profile-post-tab is-active" data-profile-tab-button="publications">Посты</button>
                <button type="button" class="profile-post-tab" data-profile-tab-button="reposts">Репосты</button>
                <button type="button" class="profile-post-tab" data-profile-tab-button="favourites">Избранное</button>
                <button type="button" class="profile-post-tab" data-profile-tab-button="likes">Нравится</button>
                <button type="button" class="profile-post-tab" data-profile-tab-button="archives">Архивы</button>
            </div>
        </section>

        <section class="profile-posts card-surface" data-profile-tab-panel="publications">
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
                <div class="posts-grid profile-media-grid">
                    <?php foreach ($posts as $post): ?>
                        <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                        <?php $postReposters = $repostMap[(int) $post['id']] ?? []; ?>
                        <article class="post-card" id="post-<?php echo (int) $post['id']; ?>">
                            <span class="post-type-badge" aria-hidden="true">
                                <img src="<?php echo (($post['media_type'] ?? '') === 'video') ? 'icon/dark theme/video.png' : 'icon/dark theme/images.png'; ?>" alt="">
                            </span>
                            <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                <video class="post-card-media" controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                            <?php elseif (!empty($post['media_url'])): ?>
                                <div class="post-card-media" style="background-image: url('<?php echo htmlspecialchars($post['media_url']); ?>');"></div>
                            <?php else: ?>
                                <div class="post-card-media"></div>
                            <?php endif; ?>
                            <div class="post-hover-overlay" aria-hidden="true">
                                <img src="icon/dark theme/like.png" alt="">
                                <img src="icon/dark theme/repost.png" alt="">
                                <img src="icon/dark theme/favourites.png" alt="">
                            </div>

                            <div class="post-card-copy">
                                <div class="feed-card-header">
                                    <div class="feed-header-main"><strong><?php echo htmlspecialchars($user['login']); ?></strong></div>
                                    <div class="post-menu-wrap">
                                        <button type="button" class="post-menu-toggle" data-post-menu="profile-post-menu-<?php echo (int) $post['id']; ?>" aria-label="Действия с публикацией"><?php echo snapix_icon('more-horizontal'); ?></button>
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
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_like"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><img src="icon/dark theme/like.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-profile-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_save"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><img src="icon/dark theme/favourites.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="add_repost"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><img src="icon/dark theme/repost.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-share-modal" data-post-id="<?php echo (int) $post['id']; ?>" aria-label="Отправить в сообщения"><img src="icon/dark theme/share.png" alt=""></button></div>
                                </div>
                                <?php if ($postReposters): ?>
                                    <p class="feed-reposts-note">Репостнули: <?php echo htmlspecialchars(implode(', ', $postReposters)); ?></p>
                                <?php endif; ?>
                                <div class="comments-modal<?php echo (isset($_GET['comments_post']) && (int) $_GET['comments_post'] === (int) $post['id']) ? ' is-open' : ''; ?>" id="comments-modal-profile-<?php echo (int) $post['id']; ?>">
                                    <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-profile-<?php echo (int) $post['id']; ?>"></div>
                                    <div class="comments-modal-dialog">
                                        <div class="comments-modal-header">
                                            <h3>Комментарии</h3>
                                            <button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-profile-<?php echo (int) $post['id']; ?>" aria-label="Закрыть"><?php echo snapix_icon('x'); ?></button>
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

        <section class="profile-posts card-surface" data-profile-tab-panel="reposts" hidden>
            <div class="section-heading">
                <h2>Репосты</h2>
            </div>
            <?php if ($repostedPosts): ?>
                <div class="posts-grid">
                    <?php foreach ($repostedPosts as $post): ?>
                        <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                        <?php $postReposters = $repostMap[(int) $post['id']] ?? []; ?>
                        <article class="post-card" id="repost-<?php echo (int) $post['id']; ?>">
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
                                </div>
                                <a href="<?php echo htmlspecialchars(buildProfileUrl((int) $post['author_user_id'], (int) $user['id'])); ?>" class="saved-post-author">
                                    <?php echo htmlspecialchars($post['author_login']); ?>
                                </a>
                                <?php if (!empty($post['caption'])): ?><p><?php echo nl2br(htmlspecialchars($post['caption'])); ?></p><?php endif; ?>
                                <div class="feed-card-buttons" style="margin-top: 10px;">
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_like"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><img src="icon/dark theme/like.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-repost-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_save"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><img src="icon/dark theme/favourites.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="add_repost"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><img src="icon/dark theme/repost.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-share-modal" data-post-id="<?php echo (int) $post['id']; ?>" aria-label="Отправить в сообщения"><img src="icon/dark theme/share.png" alt=""></button></div>
                                </div>
                                <?php if ($postReposters): ?>
                                    <p class="feed-reposts-note">Репостнули: <?php echo htmlspecialchars(implode(', ', $postReposters)); ?></p>
                                <?php endif; ?>
                                <div class="comments-modal<?php echo (isset($_GET['comments_post']) && (int) $_GET['comments_post'] === (int) $post['id']) ? ' is-open' : ''; ?>" id="comments-modal-repost-<?php echo (int) $post['id']; ?>">
                                    <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-repost-<?php echo (int) $post['id']; ?>"></div>
                                    <div class="comments-modal-dialog">
                                        <div class="comments-modal-header">
                                            <h3>Комментарии</h3>
                                            <button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-repost-<?php echo (int) $post['id']; ?>" aria-label="Закрыть"><?php echo snapix_icon('x'); ?></button>
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
            <?php endif; ?>
        </section>

        <section class="profile-posts card-surface" data-profile-tab-panel="favourites" hidden>
            <div class="section-heading">
                <h2>Избранное</h2>
            </div>

            <?php if ($savedPosts): ?>
                <div class="posts-grid">
                    <?php foreach ($savedPosts as $post): ?>
                        <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                        <?php $postReposters = $repostMap[(int) $post['id']] ?? []; ?>
                        <article class="post-card" id="post-<?php echo (int) $post['id']; ?>">
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
                                        <button type="button" class="post-menu-toggle" data-post-menu="profile-saved-post-menu-<?php echo (int) $post['id']; ?>" aria-label="Действия с публикацией"><?php echo snapix_icon('more-horizontal'); ?></button>
                                        <div class="post-menu" id="profile-saved-post-menu-<?php echo (int) $post['id']; ?>">
                                            <?php if ((int) $post['author_user_id'] === (int) $user['id']): ?>
                                                <form method="post"><input type="hidden" name="action" value="delete_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $user['id']; ?>"><button type="submit">Удалить пост</button></form>
                                                <a href="edit-post.php?id=<?php echo (int) $post['id']; ?>">Редактировать пост</a>
                                                <form method="post"><input type="hidden" name="action" value="pin_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $user['id']; ?>"><button type="submit"><?php echo (int) $post['is_pinned'] > 0 ? 'Уже закреплено' : 'Закрепить пост в личном профиле'; ?></button></form>
                                                <a href="post-insights.php?post_id=<?php echo (int) $post['id']; ?>">Кто посмотрел пост</a>
                                            <?php else: ?>
                                                <form method="post"><input type="hidden" name="action" value="report_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $post['author_user_id']; ?>"><button type="submit" data-report-trigger data-report-login="<?php echo htmlspecialchars((string) ($post['author_login'] ?? 'user')); ?>" data-report-user-id="<?php echo (int) $post['author_user_id']; ?>">Жалоба на пост</button></form>
                                                <form method="post"><input type="hidden" name="action" value="report_post_user"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $post['author_user_id']; ?>"><button type="submit">Жалоба на пользователя</button></form>
                                                <form method="post"><input type="hidden" name="action" value="hide_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $post['author_user_id']; ?>"><button type="submit">Мне не интересна эта публикация</button></form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars(buildProfileUrl((int) $post['author_user_id'], (int) $user['id'])); ?>" class="saved-post-author">
                                    <?php echo htmlspecialchars($post['author_login']); ?>
                                </a>

                                <div class="feed-card-buttons" style="margin-top: 10px;">
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_like"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><img src="icon/dark theme/like.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-saved-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_save"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><img src="icon/dark theme/favourites.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="add_repost"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><img src="icon/dark theme/repost.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-share-modal" data-post-id="<?php echo (int) $post['id']; ?>" aria-label="Отправить в сообщения"><img src="icon/dark theme/share.png" alt=""></button></div>
                                </div>
                                <div class="comments-modal<?php echo (isset($_GET['comments_post']) && (int) $_GET['comments_post'] === (int) $post['id']) ? ' is-open' : ''; ?>" id="comments-modal-saved-<?php echo (int) $post['id']; ?>">
                                    <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-saved-<?php echo (int) $post['id']; ?>"></div>
                                    <div class="comments-modal-dialog">
                                        <div class="comments-modal-header">
                                            <h3>Комментарии</h3>
                                            <button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-saved-<?php echo (int) $post['id']; ?>" aria-label="Закрыть"><?php echo snapix_icon('x'); ?></button>
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
        <section class="profile-posts card-surface" data-profile-tab-panel="likes" hidden>
            <div class="section-heading"><h2>Нравится</h2></div>
            <p class="empty-state">Понравившиеся публикации появятся здесь.</p>
        </section>
        <section class="profile-posts card-surface" data-profile-tab-panel="archives" hidden>
            <div class="section-heading"><h2>Архивы</h2></div>
            <p class="empty-state">Архивов пока нет.</p>
        </section>

    </main>
    <div class="share-modal" id="share-post-modal">
        <div class="share-modal-overlay js-close-share-modal"></div>
        <div class="share-modal-dialog">
            <div class="share-modal-header">
                <h3>Отправить публикацию</h3>
                <button type="button" class="feed-action-btn feed-icon-btn js-close-share-modal" aria-label="Закрыть"><?php echo snapix_icon('x'); ?></button>
            </div>
            <div class="share-modal-body">
                <?php if ($shareRecipients): ?>
                    <?php foreach ($shareRecipients as $recipient): ?>
                        <div class="share-recipient-row">
                            <span class="share-recipient-user">
                                <span class="share-recipient-avatar"<?php if (!empty($recipient['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($recipient['avatar']); ?>');"<?php endif; ?>>
                                    <?php if (empty($recipient['avatar'])): ?><?php echo htmlspecialchars(mb_substr($recipient['login'], 0, 1)); ?><?php endif; ?>
                                </span>
                                <span>
                                    <?php echo htmlspecialchars($recipient['login']); ?>
                                    <?php if ((int) $recipient['is_mutual'] === 1): ?><small class="share-relation-note">взаимно</small><?php endif; ?>
                                </span>
                            </span>
                            <button type="button" class="share-send-btn js-share-send-btn" data-recipient-id="<?php echo (int) $recipient['id']; ?>">Отправить</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="comments-empty">Нет подходящих получателей. Подпишитесь на пользователей.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
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

        (() => {
            const tabButtons = document.querySelectorAll('[data-profile-tab-button]');
            const tabPanels = document.querySelectorAll('[data-profile-tab-panel]');
            if (!tabButtons.length || !tabPanels.length) {
                return;
            }
            tabButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const tab = button.getAttribute('data-profile-tab-button');
                    tabButtons.forEach((item) => item.classList.toggle('is-active', item === button));
                    tabPanels.forEach((panel) => {
                        panel.hidden = panel.getAttribute('data-profile-tab-panel') !== tab;
                    });
                });
            });
        })();

        document.querySelectorAll('.post-menu-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const menuId = button.getAttribute('data-post-menu');
                const menu = menuId ? document.getElementById(menuId) : null;
                if (menu) {
                    menu.classList.toggle('is-open');
                }
            });
        });

        (function () {
    function sendPostActionForm(form) {
        var formData = new FormData(form);
        return fetch(form.getAttribute('action') || window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json'
            },
            body: new URLSearchParams(formData).toString()
        }).then(function (response) {
            return response.json();
        });
    }

    function setCount(node, value) {
        if (node && typeof value !== 'undefined') {
            node.textContent = String(Number(value || 0));
        }
    }

    function animateActiveIcon(button, isActive) {
        if (!button || !isActive) {
            return;
        }

        button.classList.remove('is-activating');
        void button.offsetWidth;
        button.classList.add('is-activating');

        window.setTimeout(function () {
            button.classList.remove('is-activating');
        }, 260);
    }

    function updateActionState(form, data) {
        var actionInput = form.querySelector('input[name="action"]');
        var action = actionInput ? actionInput.value : '';
        var item = form.closest('.feed-action-item');
        var button = form.querySelector('.feed-action-btn');
        var countNode = item ? item.querySelector('.feed-action-count') : null;

        if (action === 'toggle_like') {
            if (button) {
                button.classList.toggle('is-active', !!data.liked);
                animateActiveIcon(button, !!data.liked);
            }
            setCount(countNode, data.likes_count);
        }

        if (action === 'toggle_save') {
            if (button) {
                button.classList.toggle('is-saved', !!data.saved);
                animateActiveIcon(button, !!data.saved);
            }
            setCount(countNode, data.saves_count);
        }

        if (action === 'add_repost') {
            if (button) {
                button.classList.toggle('is-reposted', !!data.reposted);
                animateActiveIcon(button, !!data.reposted);
            }
            setCount(countNode, data.reposts_count);
        }
    }

    document.querySelectorAll('form.inline-action-form').forEach(function (form) {
        var actionInput = form.querySelector('input[name="action"]');
        var action = actionInput ? actionInput.value : '';

        if (['toggle_like', 'toggle_save', 'add_repost'].indexOf(action) === -1) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            sendPostActionForm(form)
                .then(function (data) {
                    if (!data || !data.ok) {
                        return;
                    }
                    updateActionState(form, data);
                })
                .catch(function () {});
        });
    });

    document.querySelectorAll('form.comments-modal-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            sendPostActionForm(form)
                .then(function (data) {
                    if (!data || !data.ok) {
                        return;
                    }

                    var textarea = form.querySelector('textarea[name="comment_text"]');
                    var modal = form.closest('.comments-modal');
                    var body = modal ? modal.querySelector('.comments-modal-body') : null;

                    if (body && data.comment) {
                        var empty = body.querySelector('.comments-empty');
                        if (empty) {
                            empty.remove();
                        }

                        var item = document.createElement('div');
                        item.className = 'comment-item';

                        var author = document.createElement('a');
                        author.className = 'comment-author';
                        author.href = data.comment.profile_url || 'profile.php';

                        var strong = document.createElement('strong');
                        strong.textContent = data.comment.login || '';
                        author.appendChild(strong);

                        var text = document.createElement('p');
                        text.textContent = data.comment.text || '';

                        item.appendChild(author);
                        item.appendChild(text);
                        body.insertBefore(item, body.firstChild);
                    }

                    if (textarea) {
                        textarea.value = '';
                    }

                    if (modal && modal.id) {
                        document.querySelectorAll('.js-open-comments-modal[data-modal="' + modal.id + '"]').forEach(function (button) {
                            var countNode = button.closest('.feed-action-item') ? button.closest('.feed-action-item').querySelector('.feed-action-count') : null;
                            setCount(countNode, data.comments_count);
                        });
                    }
                })
                .catch(function () {});
        });
    });
})();

document.addEventListener('click', (event) => {
            document.querySelectorAll('.post-menu').forEach((menu) => {
                const wrap = menu.closest('.post-menu-wrap');
                if (wrap && !wrap.contains(event.target)) {
                    menu.classList.remove('is-open');
                }
            });
        });

        (() => {
            const modal = document.getElementById('share-post-modal');
            if (!modal) return;
            let activePostId = 0;

            document.querySelectorAll('.js-open-share-modal').forEach((button) => {
                button.addEventListener('click', () => {
                    activePostId = Number(button.getAttribute('data-post-id') || 0);
                    modal.classList.add('is-open');
                });
            });

            modal.querySelectorAll('.js-close-share-modal').forEach((button) => {
                button.addEventListener('click', () => {
                    modal.classList.remove('is-open');
                });
            });

            modal.querySelectorAll('.js-share-send-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    if (!activePostId) return;

                    const params = new URLSearchParams();
                    params.set('post_id', String(activePostId));
                    params.set('receiver_id', String(button.getAttribute('data-recipient-id')));

                    fetch('share-post.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: params.toString()
                    })
                        .then((response) => response.json())
                        .then((data) => {
                            if (!data.ok) return;
                            button.textContent = 'Отправлено';
                            button.disabled = true;
                            setTimeout(() => {
                                button.textContent = 'Отправить';
                                button.disabled = false;
                                modal.classList.remove('is-open');
                            }, 700);
                        })
                        .catch(() => {});
                });
            });
        })();
    </script>
</body>
</html>
