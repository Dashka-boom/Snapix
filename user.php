<?php
session_start();
require './config/config.php';
require_once './includes/side-menu.php';
require './includes/icons.php';
require './includes/post-actions.php';
require './includes/notifications.php';

function profileDestination(int $targetUserId, ?int $currentUserId): string
{
    if ($currentUserId !== null && $targetUserId === $currentUserId) {
        return 'profile.php';
    }

    return 'user.php?id=' . $targetUserId;
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

$currentUser = null;
$pendingRequestsCount = 0;
$pendingRequestsPreview = [];
$reportReasons = [];
$unreadMessagesCount = 0;
$shareRecipients = [];

if (isset($_SESSION['user_id'])) {
    $viewerStmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id');
    $viewerStmt->execute(['id' => $_SESSION['user_id']]);
    $currentUser = $viewerStmt->fetch();

    if ($currentUser) {
        $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = :id AND status = 'pending'");
        $pendingStmt->execute(['id' => $currentUser['id']]);
        $pendingRequestsCount = (int) $pendingStmt->fetchColumn();

        $pendingPreviewStmt = $pdo->prepare("
            SELECT followers.id, users.id AS user_id, users.login, users.avatar
            FROM followers
            INNER JOIN users ON users.id = followers.follower_id
            WHERE followers.following_id = :id AND followers.status = 'pending'
            ORDER BY followers.created_at DESC
            LIMIT 5
        ");
        $pendingPreviewStmt->execute(['id' => $currentUser['id']]);
        $pendingRequestsPreview = $pendingPreviewStmt->fetchAll();

        $unreadMessagesStmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM messages m
            INNER JOIN chats c ON c.id = m.chat_id
            WHERE (c.user_one_id = :user_id OR c.user_two_id = :user_id)
              AND m.sender_id != :user_id
              AND m.is_read = 0
        ');
        $unreadMessagesStmt->execute(['user_id' => $currentUser['id']]);
        $unreadMessagesCount = (int) $unreadMessagesStmt->fetchColumn();

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
        $shareRecipientsStmt->execute(['user_id' => $currentUser['id']]);
        $shareRecipients = $shareRecipientsStmt->fetchAll();
    }
}

$reportReasonsStmt = $pdo->query('SELECT id, label FROM moderation_reasons ORDER BY id ASC');
$reportReasons = $reportReasonsStmt->fetchAll();

$targetUserId = (int) ($_GET['id'] ?? $_POST['target_user_id'] ?? 0);

if ($targetUserId <= 0) {
    header('Location: index.php');
    exit;
}

if ($currentUser && $targetUserId === (int) $currentUser['id']) {
    header('Location: profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser) {
    $action = $_POST['action'] ?? '';
    $postId = (int) ($_POST['post_id'] ?? 0);
    $commentsPostId = 0;
    $isAjaxPostAction = snapix_is_ajax_request() && in_array($action, ['toggle_like', 'toggle_save', 'add_comment', 'add_repost', 'get_post_counts'], true);
    $ajaxExtra = [];
    $ownerId = (int) ($_POST['owner_id'] ?? 0);
    $postExists = false;

    $targetUserStmt = $pdo->prepare('SELECT id, is_private FROM users WHERE id = :id');
    $targetUserStmt->execute(['id' => $targetUserId]);
    $targetUser = $targetUserStmt->fetch();

    if ($postId > 0) {
        $postExistsStmt = $pdo->prepare('SELECT id, user_id FROM posts WHERE id = :id AND is_deleted = 0');
        $postExistsStmt->execute(['id' => $postId]);
        $postRow = $postExistsStmt->fetch();
        $postExists = (bool) $postRow;
        $postOwnerId = $postExists ? (int) $postRow['user_id'] : 0;
        if ($ownerId <= 0) {
            $ownerId = $postOwnerId;
        }

        if ($postExists && $action === 'toggle_like') {
            $likeExistsStmt = $pdo->prepare('SELECT id FROM likes WHERE user_id = :user_id AND post_id = :post_id');
            $likeExistsStmt->execute([
                'user_id' => $currentUser['id'],
                'post_id' => $postId,
            ]);
            $likeId = $likeExistsStmt->fetchColumn();
            if ($likeId) {
                $pdo->prepare('DELETE FROM likes WHERE id = :id')->execute(['id' => $likeId]);
                $ajaxExtra['liked'] = false;
            } else {
                $pdo->prepare('INSERT INTO likes (user_id, post_id) VALUES (:user_id, :post_id)')->execute([
                    'user_id' => $currentUser['id'],
                    'post_id' => $postId,
                ]);
                $ajaxExtra['liked'] = true;
                snapix_notify_post_action($pdo, $postId, (int) $currentUser['id'], 'post_like');
            }
        }

        if ($postExists && $action === 'toggle_save') {
            $saveExistsStmt = $pdo->prepare('SELECT id FROM saved_posts WHERE user_id = :user_id AND post_id = :post_id');
            $saveExistsStmt->execute([
                'user_id' => $currentUser['id'],
                'post_id' => $postId,
            ]);
            $saveId = $saveExistsStmt->fetchColumn();
            if ($saveId) {
                $pdo->prepare('DELETE FROM saved_posts WHERE id = :id')->execute(['id' => $saveId]);
                $ajaxExtra['saved'] = false;
            } else {
                $pdo->prepare('INSERT INTO saved_posts (user_id, post_id) VALUES (:user_id, :post_id)')->execute([
                    'user_id' => $currentUser['id'],
                    'post_id' => $postId,
                ]);
                $ajaxExtra['saved'] = true;
                snapix_notify_post_action($pdo, $postId, (int) $currentUser['id'], 'post_saved');
            }
        }

        if ($postExists && $action === 'add_comment') {
            $commentText = trim($_POST['comment_text'] ?? '');
            if ($commentText !== '') {
                $commentValue = mb_substr($commentText, 0, 1000);
                $pdo->prepare('INSERT INTO comments (post_id, user_id, comment_text) VALUES (:post_id, :user_id, :comment_text)')->execute([
                    'post_id' => $postId,
                    'user_id' => $currentUser['id'],
                    'comment_text' => $commentValue,
                ]);
                $commentId = (int) $pdo->lastInsertId();
                snapix_notify_post_action($pdo, $postId, (int) $currentUser['id'], 'post_comment', $commentValue, $commentId);
                $commentsPostId = $postId;
                $ajaxExtra['comment'] = [
                    'login' => (string) $currentUser['login'],
                    'profile_url' => profileDestination((int) $currentUser['id'], (int) $currentUser['id']),
                    'text' => $commentValue,
                ];
            } elseif ($isAjaxPostAction) {
                snapix_send_post_action_error('empty_comment');
            }
        }

        if ($postExists && $action === 'add_repost') {
            $repostExistsStmt = $pdo->prepare('SELECT id FROM reposts WHERE user_id = :user_id AND post_id = :post_id');
            $repostExistsStmt->execute([
                'user_id' => $currentUser['id'],
                'post_id' => $postId,
            ]);
            $repostId = $repostExistsStmt->fetchColumn();
            if (!$repostId) {
                $pdo->prepare('INSERT IGNORE INTO reposts (user_id, post_id) VALUES (:user_id, :post_id)')->execute([
                    'user_id' => $currentUser['id'],
                    'post_id' => $postId,
                ]);
                $isRepostedNow = true;
                snapix_notify_post_action($pdo, $postId, (int) $currentUser['id'], 'post_repost');
            } else {
                $pdo->prepare('DELETE FROM reposts WHERE id = :id AND user_id = :user_id')->execute([
                    'id' => (int) $repostId,
                    'user_id' => $currentUser['id'],
                ]);
                $isRepostedNow = false;
            }

            $ajaxExtra['reposted'] = $isRepostedNow;
        }

        if ($postExists && $action === 'hide_post' && $ownerId !== (int) $currentUser['id']) {
            $pdo->prepare('INSERT IGNORE INTO hidden_posts (user_id, post_id) VALUES (:user_id, :post_id)')
                ->execute(['user_id' => $currentUser['id'], 'post_id' => $postId]);
        }

        if ($postExists && $action === 'report_post' && $ownerId !== (int) $currentUser['id']) {
            $reportReason = trim((string) ($_POST['report_reason'] ?? ''));
            $reportReasonText = mb_substr($reportReason !== '' ? $reportReason : ('Жалоба на пост #' . $postId), 0, 1000);
            $pdo->prepare('INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text) VALUES (:reporter_user_id, :target_user_id, :reason_text)')
                ->execute([
                    'reporter_user_id' => $currentUser['id'],
                    'target_user_id' => $ownerId > 0 ? $ownerId : null,
                    'reason_text' => $reportReasonText,
                ]);
            $reportId = (int) $pdo->lastInsertId();
            snapix_notify_admins($pdo, [
                'actor_user_id' => (int) $currentUser['id'],
                'notification_type' => 'report_post',
                'post_id' => $postId,
                'report_id' => $reportId,
                'title' => 'Жалоба на публикацию',
                'message' => 'Поступила жалоба на публикацию',
                'report_reason' => $reportReasonText,
                'dedupe_minutes' => 10,
            ], (int) $currentUser['id']);
        }

        if ($postExists && $action === 'report_post_user' && $ownerId !== (int) $currentUser['id']) {
            $reportReasonText = 'Жалоба на пользователя через пост #' . $postId;
            $pdo->prepare('INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text) VALUES (:reporter_user_id, :target_user_id, :reason_text)')
                ->execute([
                    'reporter_user_id' => $currentUser['id'],
                    'target_user_id' => $ownerId > 0 ? $ownerId : null,
                    'reason_text' => $reportReasonText,
                ]);
            $reportId = (int) $pdo->lastInsertId();
            snapix_notify_admins($pdo, [
                'actor_user_id' => (int) $currentUser['id'],
                'notification_type' => 'report_user',
                'post_id' => $postId,
                'report_id' => $reportId,
                'title' => 'Жалоба на пользователя',
                'message' => 'Поступила жалоба на пользователя',
                'report_reason' => $reportReasonText,
                'dedupe_minutes' => 10,
            ], (int) $currentUser['id']);
        }

        if ($isAjaxPostAction) {
            if (!$postExists) {
                snapix_send_post_action_error('post_not_found', 404);
            }

            snapix_send_post_action_json($pdo, $postId, (int) $currentUser['id'], $ajaxExtra);
        }

        if ($commentsPostId > 0) {
            header('Location: user.php?id=' . $targetUserId . '&comments_post=' . $commentsPostId);
            exit;
        }

        header('Location: user.php?id=' . $targetUserId);
        exit;
    }

    if ($targetUser) {
        if ($action === 'report_user') {
            $reasonId = (int) ($_POST['reason_id'] ?? 0);
            $customReason = trim($_POST['custom_reason'] ?? '');

            if ((int) $targetUser['id'] !== (int) $currentUser['id'] && ($reasonId > 0 || $customReason !== '')) {
                $insertReportStmt = $pdo->prepare('
                    INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_id, reason_text)
                    VALUES (:reporter_user_id, :target_user_id, :reason_id, :reason_text)
                ');
                $reportReasonText = mb_substr($customReason !== '' ? $customReason : 'Нарушение правил сообщества', 0, 1000);
                $insertReportStmt->execute([
                    'reporter_user_id' => $currentUser['id'],
                    'target_user_id' => $targetUserId,
                    'reason_id' => $reasonId > 0 ? $reasonId : null,
                    'reason_text' => $reportReasonText,
                ]);
                $reportId = (int) $pdo->lastInsertId();
                snapix_notify_admins($pdo, [
                    'actor_user_id' => (int) $currentUser['id'],
                    'notification_type' => 'report_user',
                    'report_id' => $reportId,
                    'title' => 'Жалоба на пользователя',
                    'message' => 'Поступила жалоба на пользователя',
                    'report_reason' => $reportReasonText,
                    'dedupe_minutes' => 10,
                ], (int) $currentUser['id']);
            }
        }

        $relationStmt = $pdo->prepare('
            SELECT id, status, declined_until
            FROM followers
            WHERE follower_id = :follower_id AND following_id = :following_id
            LIMIT 1
        ');
        $relationStmt->execute([
            'follower_id' => $currentUser['id'],
            'following_id' => $targetUserId,
        ]);
        $relation = $relationStmt->fetch();

        if ($action === 'toggle_follow') {
            $isBlocked = $relation
                && $relation['status'] === 'declined'
                && !empty($relation['declined_until'])
                && strtotime((string) $relation['declined_until']) > time();

            if ($isBlocked) {
                header('Location: user.php?id=' . $targetUserId . '&follow_blocked=1');
                exit;
            }

            if ($relation && $relation['status'] === 'accepted') {
                $deleteStmt = $pdo->prepare('DELETE FROM followers WHERE id = :id');
                $deleteStmt->execute(['id' => $relation['id']]);
            } else {
                $nextStatus = !empty($targetUser['is_private']) ? 'pending' : 'accepted';

                if ($relation) {
                    $updateStmt = $pdo->prepare('
                        UPDATE followers
                        SET status = :status, declined_until = NULL
                        WHERE id = :id
                    ');
                    $updateStmt->execute([
                        'status' => $nextStatus,
                        'id' => $relation['id'],
                    ]);
                } else {
                    $insertStmt = $pdo->prepare('
                        INSERT INTO followers (follower_id, following_id, status, declined_until)
                        VALUES (:follower_id, :following_id, :status, NULL)
                    ');
                    $insertStmt->execute([
                        'follower_id' => $currentUser['id'],
                        'following_id' => $targetUserId,
                        'status' => $nextStatus,
                    ]);
                }
            }
        }

        if ($action === 'cancel_follow_request' && $relation && $relation['status'] === 'pending') {
            $deleteStmt = $pdo->prepare('DELETE FROM followers WHERE id = :id');
            $deleteStmt->execute(['id' => $relation['id']]);
        }
    }

    header('Location: user.php?id=' . $targetUserId);
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
$followStatus = null;
$followDeclinedUntil = null;
$canViewPrivateProfile = false;
$isFollowBlocked = false;

if ($profileUser) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE follower_id = :id AND status = 'accepted'");
    $stmt->execute(['id' => $profileUser['id']]);
    $followingCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = :id AND status = 'accepted'");
    $stmt->execute(['id' => $profileUser['id']]);
    $followersCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = :id AND is_deleted = 0');
    $stmt->execute(['id' => $profileUser['id']]);
    $postsCount = (int) $stmt->fetchColumn();

    $isPrivateProfile = !empty($profileUser['is_private']);

    if ($currentUser) {
        $stmt = $pdo->prepare('
            SELECT status, declined_until
            FROM followers
            WHERE follower_id = :follower_id AND following_id = :following_id
            LIMIT 1
        ');
        $stmt->execute([
            'follower_id' => $currentUser['id'],
            'following_id' => $profileUser['id'],
        ]);
        $followRelation = $stmt->fetch();
        if ($followRelation) {
            $followStatus = $followRelation['status'];
            $followDeclinedUntil = $followRelation['declined_until'];
            $isFollowBlocked = $followStatus === 'declined'
                && !empty($followDeclinedUntil)
                && strtotime((string) $followDeclinedUntil) > time();
        }
    }

    $canViewPrivateProfile = !$isPrivateProfile || $followStatus === 'accepted';

    if ($canViewPrivateProfile) {
        $stmt = $pdo->prepare('
            SELECT posts.*, post_media.media_url, post_media.media_type,
                   users.id AS author_user_id, users.login AS author_login,
                   (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS likes_count,
                   (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.is_deleted = 0) AS comments_count,
                   (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id) AS reposts_count,
                   (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.user_id = :viewer_id) AS is_liked,
                   (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id) AS saves_count,
                   (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id AND saved_posts.user_id = :viewer_id) AS is_saved,
                   (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id AND reposts.user_id = :viewer_id) AS is_reposted
            FROM posts
            INNER JOIN users ON users.id = posts.user_id
            LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
            WHERE posts.user_id = :id AND posts.is_deleted = 0
            ORDER BY posts.created_at DESC
        ');
        $stmt->execute([
            'id' => $profileUser['id'],
            'viewer_id' => (int) ($currentUser['id'] ?? 0),
        ]);
        $posts = $stmt->fetchAll();
    }
}

$commentMap = [];
$repostMap = [];
if ($posts) {
    $postIds = array_values(array_unique(array_map(static fn($post): int => (int) $post['id'], $posts)));
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

$followBlockedMessage = isset($_GET['follow_blocked']) && $_GET['follow_blocked'] === '1';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <title>Snapix</title>
</head>
<body data-page="user-profile" class="has-side-menu">
    <?php render_side_menu($currentUser); ?>

    <div class="page-glass-nav" aria-hidden="true"></div>

    <main class="profile-page">
        <div class="notification-popover" id="notificationPopover">
            <div class="notification-popover-header">
                <strong>Заявки</strong>
                <a href="connections.php?view=requests">Открыть все</a>
            </div>

            <?php if ($pendingRequestsPreview): ?>
                <div class="notification-popover-list">
                    <?php foreach ($pendingRequestsPreview as $request): ?>
                        <article class="notification-popover-item">
                            <a href="<?php echo htmlspecialchars(profileDestination((int) $request['user_id'], $currentUser ? (int) $currentUser['id'] : null)); ?>" class="request-user">
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
                                <a href="connections.php?view=requests" class="secondary-link">Открыть</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="notification-popover-empty">Новых заявок нет.</p>
            <?php endif; ?>
        </div>

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
                            <a href="connections.php?view=following&user_id=<?php echo (int) $profileUser['id']; ?>" class="profile-metric profile-metric-link">
                                <strong><?php echo $followingCount; ?></strong>
                                <span>Подписки</span>
                            </a>
                            <a href="connections.php?view=followers&user_id=<?php echo (int) $profileUser['id']; ?>" class="profile-metric profile-metric-link">
                                <strong><?php echo $followersCount; ?></strong>
                                <span>Подписчики</span>
                            </a>
                            <div class="profile-metric">
                                <strong><?php echo $postsCount; ?></strong>
                                <span>Публикации</span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <?php if ($followBlockedMessage || $isFollowBlocked): ?>
                            <div class="follow-state-card">
                                <strong>Заявка временно недоступна</strong>
                                <p>Повторную заявку можно будет отправить после <?php echo htmlspecialchars(formatBlockedUntil($followDeclinedUntil)); ?>.</p>
                            </div>
                        <?php elseif ($currentUser): ?>
                            <?php if ($followStatus === 'accepted'): ?>
                                <form method="post" class="follow-action-form">
                                    <input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>">
                                    <input type="hidden" name="action" value="toggle_follow">
                                    <button type="submit" class="secondary-link">Отписаться</button>
                                </form>
                            <?php elseif ($followStatus === 'pending'): ?>
                                <div class="follow-state-card">
                                    <strong>Заявка отправлена</strong>
                                    <p>Этот пользователь получит сообщение, что вы хотите подружиться с ним.</p>
                                    <form method="post" class="follow-action-form">
                                        <input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>">
                                        <input type="hidden" name="action" value="cancel_follow_request">
                                        <button type="submit" class="secondary-link">Отменить заявку</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <form method="post" class="follow-action-form">
                                    <input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>">
                                    <input type="hidden" name="action" value="toggle_follow">
                                    <button type="submit" class="primary-link">
                                        <?php echo $isPrivateProfile ? 'Хочу подружиться' : 'Подписаться'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="login.php" class="secondary-link profile-edit-btn">Войти</a>
                        <?php endif; ?>

                        <?php if ($currentUser && (int) $currentUser['id'] !== (int) $profileUser['id']): ?>
                            <a href="chat.php?user_id=<?php echo (int) $profileUser['id']; ?>" class="primary-link profile-edit-btn" style="margin-top: 10px;">Написать сообщение</a>
                            <form method="post" class="follow-action-form" style="display:grid; gap:8px; margin-top: 10px;">
                                <input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>">
                                <input type="hidden" name="action" value="report_user">
                                <select name="reason_id">
                                    <option value="">Причина жалобы</option>
                                    <?php foreach ($reportReasons as $reason): ?>
                                        <option value="<?php echo (int) $reason['id']; ?>"><?php echo htmlspecialchars($reason['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="custom_reason" maxlength="1000" placeholder="Или своя причина">
                                <button type="submit" class="secondary-link">Пожаловаться на пользователя</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="profile-posts card-surface">
                <div class="section-heading">
                    <h2>Публикации</h2>
                </div>

                <?php if ($isPrivateProfile && !$canViewPrivateProfile): ?>
                    <div class="private-profile-notice">
                        <h3>Этот пользователь считает это слишком личным</h3>
                        <p>Профиль закрыт. Отправьте заявку в подписчики, и после принятия сможете смотреть публикации.</p>
                    </div>
                <?php elseif ($posts): ?>
                    <div class="posts-grid">
                        <?php foreach ($posts as $post): ?>
                            <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                            <?php $postReposters = $repostMap[(int) $post['id']] ?? []; ?>
                            <article class="post-card" id="post-<?php echo (int) $post['id']; ?>" data-post-id="<?php echo (int) $post['id']; ?>" data-post-likes-count="<?php echo (int) ($post['likes_count'] ?? 0); ?>" data-post-comments-count="<?php echo (int) ($post['comments_count'] ?? 0); ?>" data-post-reposts-count="<?php echo (int) ($post['reposts_count'] ?? 0); ?>" data-post-saves-count="<?php echo (int) ($post['saves_count'] ?? 0); ?>">
                                <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                    <video class="post-card-media" controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                                <?php elseif (!empty($post['media_url'])): ?>
                                    <div class="post-card-media" style="background-image: url('<?php echo htmlspecialchars($post['media_url']); ?>');"></div>
                                <?php else: ?>
                                    <div class="post-card-media"></div>
                                <?php endif; ?>
                                <div class="post-card-copy">
                                    <div class="feed-card-header">
                                        <div class="feed-header-main"><strong><?php echo htmlspecialchars($profileUser['login']); ?></strong></div>
                                        <div class="post-menu-wrap">
                                            <button type="button" class="post-menu-toggle" data-post-menu="user-post-menu-<?php echo (int) $post['id']; ?>" aria-label="Действия с публикацией"><?php echo snapix_icon('more-horizontal'); ?></button>
                                            <div class="post-menu" id="user-post-menu-<?php echo (int) $post['id']; ?>">
                                                <?php if ($currentUser): ?>
                                                    <form method="post"><input type="hidden" name="action" value="report_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $profileUser['id']; ?>"><button type="submit" data-report-trigger data-report-login="<?php echo htmlspecialchars((string) ($profileUser['login'] ?? 'user')); ?>" data-report-user-id="<?php echo (int) $profileUser['id']; ?>">Жалоба на пост</button></form>
                                                    <form method="post"><input type="hidden" name="action" value="report_post_user"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $profileUser['id']; ?>"><button type="submit">Жалоба на пользователя</button></form>
                                                    <form method="post"><input type="hidden" name="action" value="hide_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $profileUser['id']; ?>"><button type="submit">Мне не интересна эта публикация</button></form>
                                                <?php else: ?>
                                                    <a href="login.php">Войти, чтобы отправить жалобу</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($currentUser): ?>
                                        <div class="feed-card-buttons" style="margin-top: 10px;">
                                            <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_like"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><img src="icon/dark theme/like.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                            <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-user-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                            <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_save"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><img src="icon/dark theme/favourites.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                            <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="add_repost"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><img src="icon/dark theme/repost.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                            <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-share-modal" data-post-id="<?php echo (int) $post['id']; ?>" aria-label="Отправить в сообщения"><img src="icon/dark theme/share.png" alt=""></button></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="feed-card-buttons" style="margin-top: 10px;">
                                            <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для лайка"><img src="icon/dark theme/like.png" alt=""></a><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                            <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-user-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                            <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для избранного"><img src="icon/dark theme/favourites.png" alt=""></a><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                            <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для репоста"><img src="icon/dark theme/repost.png" alt=""></a><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                            <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для отправки в сообщения"><img src="icon/dark theme/share.png" alt=""></a></div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="comments-modal<?php echo (isset($_GET['comments_post']) && (int) $_GET['comments_post'] === (int) $post['id']) ? ' is-open' : ''; ?>" id="comments-modal-user-<?php echo (int) $post['id']; ?>">
                                        <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-user-<?php echo (int) $post['id']; ?>"></div>
                                        <div class="comments-modal-dialog">
                                            <div class="comments-modal-header">
                                                <h3>Комментарии</h3>
                                                <button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-user-<?php echo (int) $post['id']; ?>" aria-label="Закрыть"><?php echo snapix_icon('x'); ?></button>
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
                                            <?php if ($currentUser): ?>
                                                <form method="post" class="comment-form comments-modal-form">
                                                    <input type="hidden" name="action" value="add_comment">
                                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                    <input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>">
                                                    <textarea name="comment_text" rows="2" maxlength="1000" placeholder="Напишите комментарий..."></textarea>
                                                    <button type="submit" class="primary-link">Отправить</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
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
    <?php if ($currentUser): ?>
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
    <?php endif; ?>

    <script src="js/post-sync.js"></script>
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
