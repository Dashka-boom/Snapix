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


function userHasPublicFavourites(array $profileUser): bool
{
    foreach (['is_favourites_public', 'favourites_public', 'is_favorites_public', 'favorites_public', 'saved_posts_public', 'is_saved_posts_public'] as $column) {
        if (array_key_exists($column, $profileUser)) {
            return (int) $profileUser[$column] === 1;
        }
    }

    return false;
}

function renderOtherProfilePostGrid(array $posts, array $profileUser, ?array $currentUser, array $commentMap, string $modalPrefix): void
{
    ?>
    <div class="posts-grid profile-media-grid">
        <?php foreach ($posts as $post): ?>
            <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
            <?php $authorLogin = (string) ($post['author_login'] ?? $profileUser['login']); ?>
            <?php $authorAvatar = (string) ($post['author_avatar'] ?? $profileUser['avatar'] ?? ''); ?>
            <article class="post-card" id="post-<?php echo (int) $post['id']; ?>" data-post-card-id="<?php echo (int) $post['id']; ?>" data-post-id="<?php echo (int) $post['id']; ?>" data-post-author-id="<?php echo (int) ($post['author_user_id'] ?? $post['user_id'] ?? 0); ?>" data-post-media-url="<?php echo htmlspecialchars((string) ($post['media_url'] ?? '')); ?>" data-post-media-type="<?php echo htmlspecialchars((string) ($post['media_type'] ?? 'image')); ?>" data-post-author-login="<?php echo htmlspecialchars($authorLogin); ?>" data-post-author-avatar="<?php echo htmlspecialchars($authorAvatar); ?>" data-post-likes-count="<?php echo (int) ($post['likes_count'] ?? 0); ?>" data-post-comments-count="<?php echo (int) ($post['comments_count'] ?? 0); ?>" data-post-reposts-count="<?php echo (int) ($post['reposts_count'] ?? 0); ?>" data-post-shares-count="<?php echo (int) ($post['shares_count'] ?? 0); ?>" data-post-saves-count="<?php echo (int) ($post['saves_count'] ?? 0); ?>" data-post-liked="<?php echo (int) ($post['is_liked'] ?? 0) > 0 ? '1' : '0'; ?>" data-post-saved="<?php echo (int) ($post['is_saved'] ?? 0) > 0 ? '1' : '0'; ?>" data-post-reposted="<?php echo (int) ($post['is_reposted'] ?? 0) > 0 ? '1' : '0'; ?>" data-post-viewer-follow-status="<?php echo htmlspecialchars((string) ($post['viewer_follow_status'] ?? '')); ?>">
                <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                    <video class="post-card-media" preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                <?php elseif (!empty($post['media_url'])): ?>
                    <div class="post-card-media" style="background-image: url('<?php echo htmlspecialchars($post['media_url']); ?>');"></div>
                <?php else: ?>
                    <div class="post-card-media"></div>
                <?php endif; ?>

                <span class="post-type-badge" aria-label="<?php echo (($post['media_type'] ?? '') === 'video') ? 'Видео' : 'Фото'; ?>">
                    <img src="icon/dark theme/<?php echo (($post['media_type'] ?? '') === 'video') ? 'video' : 'images'; ?>.png" alt="">
                </span>

                <div class="post-hover-overlay" aria-hidden="true">
                    <div class="profile-hover-action-item">
                        <?php if ($currentUser): ?>
                            <form method="post" class="inline-action-form profile-hover-action-form"><input type="hidden" name="action" value="toggle_like"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-like-btn<?php echo (int) ($post['is_liked'] ?? 0) > 0 ? ' is-active' : ''; ?>" data-hover-like-post-id="<?php echo (int) $post['id']; ?>" aria-label="Лайк"><img src="icon/dark theme/like.png" alt="Лайк"></button></form>
                        <?php else: ?>
                            <a href="login.php" class="feed-action-btn profile-hover-action-btn profile-hover-like-btn" aria-label="Войти для лайка"><img src="icon/dark theme/like.png" alt="Лайк"></a>
                        <?php endif; ?>
                        <span class="feed-action-count"><?php echo (int) ($post['likes_count'] ?? 0); ?></span>
                    </div>
                    <div class="profile-hover-action-item">
                        <?php if ($currentUser): ?>
                            <form method="post" class="inline-action-form profile-hover-action-form"><input type="hidden" name="action" value="add_repost"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-repost-btn<?php echo (int) ($post['is_reposted'] ?? 0) > 0 ? ' is-reposted' : ''; ?>" data-hover-repost-post-id="<?php echo (int) $post['id']; ?>" aria-label="Репост"><img src="icon/dark theme/repost.png" alt="Репост"></button></form>
                        <?php else: ?>
                            <a href="login.php" class="feed-action-btn profile-hover-action-btn profile-hover-repost-btn" aria-label="Войти для репоста"><img src="icon/dark theme/repost.png" alt="Репост"></a>
                        <?php endif; ?>
                        <span class="feed-action-count"><?php echo (int) ($post['reposts_count'] ?? 0); ?></span>
                    </div>
                    <div class="profile-hover-action-item">
                        <?php if ($currentUser): ?>
                            <form method="post" class="inline-action-form profile-hover-action-form"><input type="hidden" name="action" value="toggle_save"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-save-btn<?php echo (int) ($post['is_saved'] ?? 0) > 0 ? ' is-saved' : ''; ?>" data-hover-save-post-id="<?php echo (int) $post['id']; ?>" aria-label="Избранное"><img src="icon/dark theme/favourites.png" alt="Избранное"></button></form>
                        <?php else: ?>
                            <a href="login.php" class="feed-action-btn profile-hover-action-btn profile-hover-save-btn" aria-label="Войти для избранного"><img src="icon/dark theme/favourites.png" alt="Избранное"></a>
                        <?php endif; ?>
                        <span class="feed-action-count"><?php echo (int) ($post['saves_count'] ?? 0); ?></span>
                    </div>
                </div>

                <div class="post-card-copy">
                    <div class="feed-card-header"><div class="feed-header-main"><strong><?php echo htmlspecialchars($authorLogin); ?></strong></div></div>
                    <div class="feed-card-buttons" style="margin-top: 10px;">
                        <?php if ($currentUser): ?>
                            <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_like"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) ($post['is_liked'] ?? 0) > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><img src="icon/dark theme/like.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) ($post['likes_count'] ?? 0); ?></span></div>
                            <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-<?php echo htmlspecialchars($modalPrefix); ?>-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button><span class="feed-action-count"><?php echo (int) ($post['comments_count'] ?? 0); ?></span></div>
                            <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_save"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) ($post['is_saved'] ?? 0) > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><img src="icon/dark theme/favourites.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) ($post['saves_count'] ?? 0); ?></span></div>
                            <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="add_repost"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) ($post['is_reposted'] ?? 0) > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><img src="icon/dark theme/repost.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) ($post['reposts_count'] ?? 0); ?></span></div>
                            <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-share-modal" data-post-id="<?php echo (int) $post['id']; ?>" data-post-action="share" aria-label="Отправить в сообщения"><img src="icon/dark theme/share.png" alt=""></button><span class="feed-action-count" data-post-id="<?php echo (int) $post['id']; ?>" data-post-count="shares"><?php echo (int) ($post['shares_count'] ?? 0); ?></span></div>
                        <?php endif; ?>
                    </div>
                    <div class="comments-modal<?php echo (isset($_GET['comments_post']) && (int) $_GET['comments_post'] === (int) $post['id']) ? ' is-open' : ''; ?>" id="comments-modal-<?php echo htmlspecialchars($modalPrefix); ?>-<?php echo (int) $post['id']; ?>">
                        <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-<?php echo htmlspecialchars($modalPrefix); ?>-<?php echo (int) $post['id']; ?>"></div>
                        <div class="comments-modal-dialog"><div class="comments-modal-header"><h3>Комментарии</h3><button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-<?php echo htmlspecialchars($modalPrefix); ?>-<?php echo (int) $post['id']; ?>" aria-label="Закрыть"><?php echo snapix_icon('x'); ?></button></div><div class="comments-modal-body">
                            <?php if ($postComments): ?><?php foreach ($postComments as $comment): ?><div class="comment-item"><strong><?php echo htmlspecialchars($comment['login']); ?></strong><p><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p></div><?php endforeach; ?><?php else: ?><p class="comments-empty">Пока нет комментариев.</p><?php endif; ?>
                        </div><?php if ($currentUser): ?><form method="post" class="comment-form comments-modal-form"><input type="hidden" name="action" value="add_comment"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><textarea name="comment_text" rows="2" maxlength="1000" placeholder="Напишите комментарий..."></textarea><button type="submit" class="primary-link">Отправить</button></form><?php endif; ?></div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
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
                    'comment_id' => $commentId,
                    'login' => (string) $currentUser['login'],
                    'profile_url' => profileDestination((int) $currentUser['id'], (int) $currentUser['id']),
                    'avatar_url' => (string) ($currentUser['avatar'] ?? ''),
                    'text' => $commentValue,
                    'created_at' => date('d.m.Y H:i'),
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
$repostedPosts = [];
$savedPosts = [];
$isFavouritesPublic = false;
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
    $isFavouritesPublic = userHasPublicFavourites($profileUser);

    if ($canViewPrivateProfile) {
        $stmt = $pdo->prepare('
            SELECT posts.*, post_media.media_url, post_media.media_type,
                   users.id AS author_user_id, users.login AS author_login, users.avatar AS author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS likes_count,
                   (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.is_deleted = 0) AS comments_count,
                   (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id) AS reposts_count,
                   (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.user_id = :viewer_id) AS is_liked,
                   (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id) AS saves_count,
                   (SELECT COUNT(*) FROM messages WHERE messages.post_id = posts.id) AS shares_count,
                   (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id AND saved_posts.user_id = :viewer_id) AS is_saved,
                   (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id AND reposts.user_id = :viewer_id) AS is_reposted,
                   (SELECT status FROM followers WHERE follower_id = :viewer_id AND following_id = users.id LIMIT 1) AS viewer_follow_status
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
        $postStatsSql = "
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS likes_count,
            (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.is_deleted = 0) AS comments_count,
            (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id) AS reposts_count,
            (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id) AS saves_count,
            (SELECT COUNT(*) FROM messages WHERE messages.post_id = posts.id) AS shares_count,
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.user_id = :viewer_id) AS is_liked,
            (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id AND saved_posts.user_id = :viewer_id) AS is_saved,
            (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id AND reposts.user_id = :viewer_id) AS is_reposted,
            (SELECT status FROM followers WHERE follower_id = :viewer_id AND following_id = users.id LIMIT 1) AS viewer_follow_status
        ";

        $stmt = $pdo->prepare("
            SELECT posts.*, post_media.media_url, post_media.media_type,
                   users.id AS author_user_id, users.login AS author_login, users.avatar AS author_avatar,
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
            'id' => $profileUser['id'],
            'viewer_id' => (int) ($currentUser['id'] ?? 0),
        ]);
        $repostedPosts = $stmt->fetchAll();

        if ($isFavouritesPublic) {
            $stmt = $pdo->prepare("
                SELECT posts.*, post_media.media_url, post_media.media_type,
                       users.id AS author_user_id, users.login AS author_login, users.avatar AS author_avatar,
                       $postStatsSql
                FROM saved_posts
                INNER JOIN posts ON posts.id = saved_posts.post_id AND posts.is_deleted = 0
                INNER JOIN users ON users.id = posts.user_id
                LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
                WHERE saved_posts.user_id = :id
                ORDER BY saved_posts.created_at DESC
            ");
            $stmt->execute([
                'id' => $profileUser['id'],
                'viewer_id' => (int) ($currentUser['id'] ?? 0),
            ]);
            $savedPosts = $stmt->fetchAll();
        }
    }
}

$commentMap = [];
$viewerCommentMap = [];
$repostMap = [];
if ($posts || $repostedPosts || $savedPosts) {
    $allVisiblePosts = array_merge($posts, $repostedPosts, $savedPosts);
    $postIds = array_values(array_unique(array_map(static fn($post): int => (int) $post['id'], $allVisiblePosts)));
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));

    $commentsStmt = $pdo->prepare("
        SELECT comments.id, comments.post_id, comment_posts.user_id AS post_owner_id, comments.comment_text, comments.created_at, users.id AS user_id, users.login, users.avatar
        FROM comments
        INNER JOIN users ON users.id = comments.user_id
        INNER JOIN posts comment_posts ON comment_posts.id = comments.post_id
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
        if (!isset($viewerCommentMap[$currentPostId])) {
            $viewerCommentMap[$currentPostId] = [];
        }
        $viewerCommentMap[$currentPostId][] = [
            'comment_id' => (int) $comment['id'],
            'post_id' => $currentPostId,
            'post_owner_id' => (int) ($comment['post_owner_id'] ?? 0),
            'parent_comment_id' => 0,
            'user_id' => (int) $comment['user_id'],
            'login' => (string) $comment['login'],
            'avatar_url' => (string) ($comment['avatar'] ?? ''),
            'profile_url' => profileDestination((int) $comment['user_id'], $currentUser ? (int) $currentUser['id'] : null),
            'comment_text' => (string) $comment['comment_text'],
            'text' => (string) $comment['comment_text'],
            'attachment_url' => '',
            'attachment_type' => '',
            'created_at' => !empty($comment['created_at']) ? date('d.m.Y H:i', strtotime((string) $comment['created_at'])) : '',
            'status' => 'published',
            'likes_count' => 0,
            'is_liked' => false,
        ];
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
    <link rel="icon" href="icon/light theme/logo.png" type="image/png">
    <title>Snapix</title>
</head>
<body data-page="profile" data-profile-owner="other" class="has-side-menu">
    <?php render_side_menu($currentUser); ?>

    <div class="page-glass-nav" aria-hidden="true"></div>

    <main class="profile-page profile-page--other">
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
                                    <?php if (empty($request['avatar'])): ?><?php echo htmlspecialchars(mb_substr($request['login'], 0, 1)); ?><?php endif; ?>
                                </span>
                                <span class="request-copy"><strong><?php echo htmlspecialchars($request['login']); ?></strong><span>Хочет подружиться с вами</span></span>
                            </a>
                            <div class="notification-popover-actions"><a href="connections.php?view=requests" class="secondary-link">Открыть</a></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="notification-popover-empty">Новых заявок нет.</p>
            <?php endif; ?>
        </div>

        <?php if (!$profileUser): ?>
            <section class="profile-posts card-surface"><div class="section-heading"><h1>Профиль не найден</h1></div><p class="empty-state">Похоже, этого пользователя не существует или ссылка устарела.</p></section>
        <?php else: ?>
            <section class="profile-hero">
                <section class="profile-cover card-surface<?php echo !empty($profileUser['background_image']) ? ' has-image' : ''; ?>"<?php if (!empty($profileUser['background_image'])): ?> style="background-image: url('<?php echo htmlspecialchars($profileUser['background_image']); ?>');"<?php endif; ?>></section>

                <section class="profile-summary">
                    <div class="profile-avatar-shell">
                        <?php if (!empty($profileUser['avatar'])): ?>
                            <div class="profile-avatar" style="background-image: url('<?php echo htmlspecialchars($profileUser['avatar']); ?>');"></div>
                        <?php else: ?>
                            <div class="profile-avatar"><?php echo htmlspecialchars(mb_substr($profileUser['login'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-header">
                        <div class="profile-main">
                            <div class="profile-name-row">
                                <h1 class="profile-username"><?php echo htmlspecialchars($profileUser['login']); ?></h1>
                                <?php if ($isPrivateProfile): ?><img src="icon/light theme/closed account.png" alt="Закрытый профиль" class="profile-private-icon"><?php endif; ?>
                            </div>
                            <?php if (!empty($profileUser['bio'])): ?><p class="profile-bio"><?php echo nl2br(htmlspecialchars($profileUser['bio'])); ?></p><?php endif; ?>
                            <div class="profile-metrics">
                                <span><strong><?php echo $postsCount; ?></strong> публикаций</span>
                                <a href="connections.php?view=followers&user_id=<?php echo (int) $profileUser['id']; ?>" class="profile-metric-inline-link"><strong><?php echo $followersCount; ?></strong> смотрители</a>
                                <a href="connections.php?view=following&user_id=<?php echo (int) $profileUser['id']; ?>" class="profile-metric-inline-link"><strong><?php echo $followingCount; ?></strong> смотримые</a>
                            </div>
                            <?php if ($currentUser): ?>
                                <a href="chat.php?user_id=<?php echo (int) $profileUser['id']; ?>" class="profile-create-btn" aria-label="Написать сообщение">+</a>
                            <?php endif; ?>
                        </div>

                        <div class="profile-actions">
                            <?php if ($followBlockedMessage || $isFollowBlocked): ?>
                                <div class="follow-state-card"><strong>Заявка временно недоступна</strong><p>Повторную заявку можно будет отправить после <?php echo htmlspecialchars(formatBlockedUntil($followDeclinedUntil)); ?>.</p></div>
                            <?php elseif ($currentUser): ?>
                                <?php if ($followStatus === 'accepted'): ?>
                                    <form method="post" class="follow-action-form"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><input type="hidden" name="action" value="toggle_follow"><button type="submit" class="secondary-link profile-edit-btn profile-follow-btn">Отписаться</button></form>
                                <?php elseif ($followStatus === 'pending'): ?>
                                    <form method="post" class="follow-action-form"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><input type="hidden" name="action" value="cancel_follow_request"><button type="submit" class="secondary-link profile-edit-btn profile-follow-btn">Заявка отправлена</button></form>
                                <?php else: ?>
                                    <form method="post" class="follow-action-form"><input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>"><input type="hidden" name="action" value="toggle_follow"><button type="submit" class="primary-link profile-edit-btn profile-follow-btn"><?php echo $isPrivateProfile ? 'Подписаться' : 'Подписаться'; ?></button></form>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="login.php" class="secondary-link profile-edit-btn">Войти</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <?php if (!($isPrivateProfile && !$canViewPrivateProfile)): ?>
                    <section class="profile-tabs-line">
                        <div class="profile-post-tabs home-feed-tabs" role="tablist" aria-label="Разделы профиля">
                            <button type="button" class="profile-post-tab home-feed-tab is-active" data-profile-tab-button="publications">Посты</button>
                            <button type="button" class="profile-post-tab home-feed-tab" data-profile-tab-button="reposts">Репосты</button>
                            <?php if ($isFavouritesPublic): ?><button type="button" class="profile-post-tab home-feed-tab" data-profile-tab-button="favourites">Избранное</button><?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </section>

            <?php if ($isPrivateProfile && !$canViewPrivateProfile): ?>
                <section class="profile-posts card-surface"><div class="private-profile-notice"><h3>Профиль закрыт</h3><p>Отправьте заявку в подписчики, и после принятия сможете смотреть публикации.</p></div></section>
            <?php else: ?>
                <section class="profile-posts card-surface" data-profile-tab-panel="publications">
                    <?php if ($posts): ?><?php renderOtherProfilePostGrid($posts, $profileUser, $currentUser, $commentMap, 'user-posts'); ?><?php else: ?><p class="empty-state">У этого пользователя пока нет публикаций.</p><?php endif; ?>
                </section>
                <section class="profile-posts card-surface" data-profile-tab-panel="reposts" hidden>
                    <?php if ($repostedPosts): ?><?php renderOtherProfilePostGrid($repostedPosts, $profileUser, $currentUser, $commentMap, 'user-reposts'); ?><?php else: ?><p class="empty-state">У этого пользователя пока нет репостов.</p><?php endif; ?>
                </section>
                <?php if ($isFavouritesPublic): ?>
                    <section class="profile-posts card-surface" data-profile-tab-panel="favourites" hidden>
                        <?php if ($savedPosts): ?><?php renderOtherProfilePostGrid($savedPosts, $profileUser, $currentUser, $commentMap, 'user-favourites'); ?><?php else: ?><p class="empty-state">В открытом избранном пока нет публикаций.</p><?php endif; ?>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php if ($profileUser && !($isPrivateProfile && !$canViewPrivateProfile)): ?>
        <div class="profile-post-viewer" id="profilePostViewer" aria-hidden="true">
            <div class="profile-post-viewer-overlay" data-post-viewer-close></div>
            <div class="profile-post-viewer-dialog" role="dialog" aria-modal="true" aria-label="Просмотр публикации">
                <button type="button" class="profile-post-viewer-close" data-post-viewer-close aria-label="Закрыть">×</button>
                <div class="profile-post-viewer-media" id="profilePostViewerMedia"></div>
                <aside class="profile-post-viewer-side">
                    <header class="profile-post-viewer-head">
                        <div class="profile-post-viewer-author">
                            <a href="#" class="profile-post-viewer-author-link" id="profilePostViewerAvatarLink"><span class="profile-post-viewer-avatar" id="profilePostViewerAvatar"></span></a>
                            <a href="#" class="profile-post-viewer-login-link-name" id="profilePostViewerLoginLink"><strong id="profilePostViewerLogin"></strong></a>
                            <button type="button" class="profile-post-viewer-follow" id="profilePostViewerFollow">Подписаться</button>
                        </div>
                        <button type="button" class="profile-post-viewer-more" aria-label="Ещё">•••</button>
                    </header>
                    <div class="profile-post-viewer-comments" id="profilePostViewerComments">
                        <p class="profile-post-viewer-empty">Комментариев нет</p>
                    </div>
                    <div class="profile-post-viewer-metrics" data-post-id="">
                        <div class="profile-post-viewer-metric"><button type="button" class="profile-post-viewer-action" data-post-id="" data-post-action="like" aria-label="Лайк"><img class="icon-dark" src="icon/dark theme/like.png" alt=""><img class="icon-light" src="icon/light theme/like.png" alt=""></button><span id="viewerLikesCount" data-post-id="" data-post-count="likes">0</span></div>
                        <div class="profile-post-viewer-metric"><button type="button" class="profile-post-viewer-action" data-post-id="" data-post-action="comment" aria-label="Комментарии"><img class="icon-dark" src="icon/dark theme/comment.png" alt=""><img class="icon-light" src="icon/light theme/comment.png" alt=""></button><span id="viewerCommentsCount" data-post-id="" data-post-count="comments">0</span></div>
                        <div class="profile-post-viewer-metric"><button type="button" class="profile-post-viewer-action" data-post-id="" data-post-action="repost" aria-label="Репост"><img class="icon-dark" src="icon/dark theme/repost.png" alt=""><img class="icon-light" src="icon/light theme/repost.png" alt=""></button><span id="viewerRepostsCount" data-post-id="" data-post-count="reposts">0</span></div>
                        <div class="profile-post-viewer-metric"><button type="button" class="profile-post-viewer-action" data-post-id="" data-post-action="share" aria-label="Отправить в сообщения"><img class="icon-dark" src="icon/dark theme/share.png" alt=""><img class="icon-light" src="icon/light theme/share.png" alt=""></button><span id="viewerSharesCount" data-post-id="" data-post-count="shares">0</span></div>
                        <div class="profile-post-viewer-metric"><button type="button" class="profile-post-viewer-action" data-post-id="" data-post-action="save" aria-label="Избранное"><img class="icon-dark" src="icon/dark theme/favourites.png" alt=""><img class="icon-light" src="icon/light theme/favourites.png" alt=""></button><span id="viewerSavesCount" data-post-id="" data-post-count="saves">0</span></div>
                    </div>
                    <?php if ($currentUser): ?>
                        <form method="post" class="profile-post-viewer-input-row" id="profilePostViewerCommentForm">
                            <input type="hidden" name="action" value="add_comment">
                            <input type="hidden" name="post_id" value="">
                            <input type="hidden" name="parent_comment_id" value="">
                            <input type="hidden" name="target_user_id" value="<?php echo (int) $profileUser['id']; ?>">
                            <button type="button" class="profile-post-viewer-round-btn" id="profilePostViewerAttachmentButton" aria-label="Прикрепить фото"><img class="icon-dark" src="icon/dark theme/paper clip.png" alt=""><img class="icon-light" src="icon/light theme/paper clip.png" alt=""></button>
                            <input class="profile-post-viewer-file-input" id="profilePostViewerAttachmentInput" type="file" accept="image/gif,image/jpeg,image/png,image/webp" hidden>
                            <div class="profile-post-viewer-emoji-wrap">
                                <button type="button" class="profile-post-viewer-round-btn" id="profilePostViewerEmojiButton" aria-label="Выбрать эмодзи" aria-expanded="false" aria-controls="profilePostViewerEmojiPicker"><img class="icon-dark" src="icon/dark theme/add stickers.png" alt=""><img class="icon-light" src="icon/light theme/add stickers.png" alt=""></button>
                                <div class="profile-post-viewer-emoji-picker" id="profilePostViewerEmojiPicker" hidden>
                                    <button type="button" data-emoji="😀">😀</button><button type="button" data-emoji="😂">😂</button><button type="button" data-emoji="😍">😍</button><button type="button" data-emoji="🥰">🥰</button><button type="button" data-emoji="😎">😎</button><button type="button" data-emoji="😢">😢</button><button type="button" data-emoji="😡">😡</button><button type="button" data-emoji="👍">👍</button><button type="button" data-emoji="🔥">🔥</button><button type="button" data-emoji="❤️">❤️</button>
                                </div>
                            </div>
                            <div class="profile-post-viewer-input-shell" id="profilePostViewerInputShell"><div class="profile-post-viewer-attachment-preview" id="profilePostViewerAttachmentPreview" hidden><img src="" alt="Предпросмотр вложения"><button type="button" id="profilePostViewerAttachmentRemove" aria-label="Удалить вложение">×</button></div><textarea class="profile-post-viewer-input" name="comment_text" rows="1" maxlength="1000" placeholder="Добавить комментарий" aria-label="Добавить комментарий"></textarea><button type="submit" class="profile-post-viewer-send-btn" aria-label="Отправить"><img src="icon/message.png" alt=""></button></div>
                        </form>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    <?php endif; ?>
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
        window.snapixProfileComments = <?php echo json_encode($viewerCommentMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.snapixCurrentUser = <?php echo json_encode(['id' => (int) ($currentUser['id'] ?? 0), 'role' => (string) ($currentUser['role'] ?? 'user')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
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

        document.querySelectorAll('[data-profile-tab-button]').forEach((button) => {
            button.addEventListener('click', () => {
                const tab = button.getAttribute('data-profile-tab-button');
                document.querySelectorAll('[data-profile-tab-button]').forEach((item) => {
                    item.classList.toggle('is-active', item === button);
                });
                document.querySelectorAll('[data-profile-tab-panel]').forEach((panel) => {
                    panel.hidden = panel.getAttribute('data-profile-tab-panel') !== tab;
                });
            });
        });

        (() => {
            const viewer = document.getElementById('profilePostViewer');
            const mediaHost = document.getElementById('profilePostViewerMedia');
            const avatar = document.getElementById('profilePostViewerAvatar');
            const login = document.getElementById('profilePostViewerLogin');
            const avatarLink = document.getElementById('profilePostViewerAvatarLink');
            const loginLink = document.getElementById('profilePostViewerLoginLink');
            const follow = document.getElementById('profilePostViewerFollow');
            const likes = document.getElementById('viewerLikesCount');
            const comments = document.getElementById('viewerCommentsCount');
            const reposts = document.getElementById('viewerRepostsCount');
            const shares = document.getElementById('viewerSharesCount');
            const saves = document.getElementById('viewerSavesCount');
            const commentsHost = document.getElementById('profilePostViewerComments');
            const commentForm = document.getElementById('profilePostViewerCommentForm');
            if (!viewer || !mediaHost) return;

            function closeViewer() {
                viewer.classList.remove('is-open');
                viewer.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('is-modal-open');
                document.body.style.overflow = '';
                mediaHost.innerHTML = '';
            }

            function commentAuthorInitial(comment) {
                return String(comment.login || '?').slice(0, 1).toUpperCase();
            }

            function buildViewerComment(comment) {
                const item = document.createElement('article');
                item.className = 'profile-viewer-comment';
                item.dataset.commentId = String(comment.comment_id || '');
                item.dataset.postId = String(comment.post_id || '');

                const avatarLinkNode = document.createElement('a');
                avatarLinkNode.className = 'profile-viewer-comment-avatar';
                avatarLinkNode.href = comment.profile_url || '#';
                if (comment.avatar_url) {
                    avatarLinkNode.style.backgroundImage = 'url(' + comment.avatar_url + ')';
                } else {
                    avatarLinkNode.textContent = commentAuthorInitial(comment);
                }

                const body = document.createElement('div');
                body.className = 'profile-viewer-comment-body';

                const top = document.createElement('div');
                top.className = 'profile-viewer-comment-top';
                const loginNode = document.createElement('a');
                loginNode.className = 'profile-viewer-comment-login';
                loginNode.href = comment.profile_url || '#';
                loginNode.textContent = comment.login || '';
                top.appendChild(loginNode);

                const text = document.createElement('div');
                text.className = 'profile-viewer-comment-text';
                text.dataset.commentText = '1';
                text.textContent = comment.comment_text || comment.text || '';

                body.appendChild(top);
                body.appendChild(text);

                if (comment.attachment_url) {
                    const attachment = document.createElement('a');
                    attachment.className = 'profile-viewer-comment-attachment';
                    attachment.href = comment.attachment_url;
                    attachment.target = '_blank';
                    attachment.rel = 'noopener';
                    const attachmentImg = document.createElement('img');
                    attachmentImg.src = comment.attachment_url;
                    attachmentImg.alt = 'Вложение комментария';
                    attachment.appendChild(attachmentImg);
                    body.appendChild(attachment);
                }

                const meta = document.createElement('div');
                meta.className = 'profile-viewer-comment-meta';
                const date = document.createElement('span');
                date.className = 'profile-viewer-comment-date';
                date.textContent = comment.created_at || '';
                meta.appendChild(date);

                const reply = document.createElement('button');
                reply.type = 'button';
                reply.className = 'profile-viewer-comment-reply';
                reply.dataset.replyLogin = comment.login || '';
                reply.textContent = 'Ответить';
                meta.appendChild(reply);

                const like = document.createElement('button');
                like.type = 'button';
                like.className = 'profile-viewer-comment-like' + (comment.is_liked ? ' is-active' : '');
                like.dataset.commentId = String(comment.comment_id || '');
                like.setAttribute('aria-label', 'Лайк комментария');
                like.innerHTML = '<img src="icon/dark theme/like.png" alt=""><span>' + Number(comment.likes_count || 0) + '</span>';
                meta.appendChild(like);

                const menu = document.createElement('div');
                menu.className = 'profile-viewer-comment-menu';
                menu.innerHTML = '<button type="button" class="profile-viewer-comment-menu-toggle" aria-label="Действия с комментарием">•••</button><div class="profile-viewer-comment-menu-panel"><button type="button" class="profile-viewer-comment-report">Пожаловаться</button></div>';
                meta.appendChild(menu);

                body.appendChild(meta);
                item.appendChild(avatarLinkNode);
                item.appendChild(body);
                return item;
            }

            window.snapixRenderViewerComments = function (postId) {
                if (!commentsHost) return;
                const list = (window.snapixProfileComments || {})[String(postId || '')] || [];
                commentsHost.innerHTML = '';
                commentsHost.classList.toggle('has-comments', list.length > 0);
                if (!list.length) {
                    commentsHost.innerHTML = '<p class="profile-post-viewer-empty">Комментариев нет</p>';
                    return;
                }
                list.forEach((comment) => commentsHost.appendChild(buildViewerComment(comment)));
            };

            function openViewer(card) {
                const mediaUrl = card.dataset.postMediaUrl || '';
                const mediaType = card.dataset.postMediaType || 'image';
                mediaHost.innerHTML = '';
                if (mediaType === 'video') {
                    const video = document.createElement('video');
                    video.src = mediaUrl;
                    video.controls = true;
                    video.playsInline = true;
                    mediaHost.appendChild(video);
                } else {
                    const img = document.createElement('img');
                    img.src = mediaUrl;
                    img.alt = 'Публикация';
                    mediaHost.appendChild(img);
                }

                if (login) login.textContent = card.dataset.postAuthorLogin || '';
                const avatarUrl = card.dataset.postAuthorAvatar || '';
                if (avatar) {
                    avatar.style.backgroundImage = avatarUrl ? `url('${avatarUrl}')` : '';
                    avatar.textContent = avatarUrl ? '' : ((card.dataset.postAuthorLogin || '?').slice(0, 1).toUpperCase());
                }

                const authorId = Number(card.dataset.postAuthorId || 0);
                const currentUserId = Number((window.snapixCurrentUser || {}).id || 0);
                const authorUrl = authorId && authorId === currentUserId ? 'profile.php' : 'user.php?id=' + encodeURIComponent(String(authorId));
                if (avatarLink) avatarLink.href = authorUrl;
                if (loginLink) loginLink.href = authorUrl;
                if (follow) {
                    follow.hidden = !authorId || authorId === currentUserId || ['accepted', 'pending'].indexOf(card.dataset.postViewerFollowStatus || '') !== -1;
                    follow.disabled = false;
                    follow.dataset.authorId = String(authorId || '');
                }

                const postId = card.dataset.postId || '';
                window.snapixRenderViewerComments(postId);
                viewer.dataset.postId = postId;
                viewer.dataset.postAuthorId = card.dataset.postAuthorId || '';
                viewer.querySelectorAll('[data-post-action], [data-post-count], .profile-post-viewer-metrics').forEach((node) => node.setAttribute('data-post-id', postId));
                if (commentForm) {
                    const postIdInput = commentForm.querySelector('input[name="post_id"]');
                    const parentCommentInput = commentForm.querySelector('input[name="parent_comment_id"]');
                    const commentInput = commentForm.querySelector('[name="comment_text"]');
                    if (postIdInput) postIdInput.value = postId;
                    if (parentCommentInput) parentCommentInput.value = '';
                    if (commentInput) commentInput.value = '';
                }
                if (likes) likes.textContent = card.dataset.postLikesCount || '0';
                if (comments) comments.textContent = card.dataset.postCommentsCount || '0';
                if (reposts) reposts.textContent = card.dataset.postRepostsCount || '0';
                if (shares) shares.textContent = card.dataset.postSharesCount || '0';
                if (saves) saves.textContent = card.dataset.postSavesCount || '0';
                viewer.querySelector('[data-post-action="like"]')?.classList.toggle('is-active', card.dataset.postLiked === '1');
                viewer.querySelector('[data-post-action="save"]')?.classList.toggle('is-saved', card.dataset.postSaved === '1');
                viewer.querySelector('[data-post-action="repost"]')?.classList.toggle('is-reposted', card.dataset.postReposted === '1');
                viewer.classList.add('is-open');
                viewer.setAttribute('aria-hidden', 'false');
                document.body.classList.add('is-modal-open');
                document.body.style.overflow = 'hidden';
            }

            document.querySelectorAll('.profile-media-grid .post-card').forEach((card) => {
                card.addEventListener('click', (event) => {
                    if (event.target.closest('button, a, form, .post-hover-overlay, .post-menu-wrap, .comments-modal')) return;
                    openViewer(card);
                });
            });

            viewer.querySelectorAll('[data-post-viewer-close]').forEach((node) => node.addEventListener('click', closeViewer));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && viewer.classList.contains('is-open')) closeViewer();
            });

            viewer.querySelectorAll('[data-post-action]').forEach((button) => {
                button.addEventListener('click', () => {
                    const postId = viewer.dataset.postId || '';
                    const card = document.querySelector('.post-card[data-post-id="' + postId + '"]');
                    const action = button.getAttribute('data-post-action');
                    if (action === 'comment') {
                        commentForm?.querySelector('textarea[name="comment_text"]')?.focus();
                        return;
                    }
                    if (action === 'share') {
                        card?.querySelector('.js-open-share-modal')?.click();
                        return;
                    }
                    const formAction = action === 'like' ? 'toggle_like' : (action === 'save' ? 'toggle_save' : 'add_repost');
                    const form = card ? Array.from(card.querySelectorAll('form.inline-action-form')).find((item) => item.querySelector('input[name="action"]')?.value === formAction) : null;
                    form?.requestSubmit();
                });
            });

            commentsHost?.addEventListener('click', (event) => {
                const menuToggle = event.target.closest('.profile-viewer-comment-menu-toggle');
                if (menuToggle) {
                    const menu = menuToggle.closest('.profile-viewer-comment-menu');
                    menu?.classList.toggle('is-open');
                    return;
                }
                const replyButton = event.target.closest('.profile-viewer-comment-reply');
                if (replyButton) {
                    const input = commentForm?.querySelector('[name="comment_text"]');
                    if (input) {
                        input.value = '@' + (replyButton.dataset.replyLogin || '') + ' ';
                        input.focus();
                    }
                }
            });

            const emojiButton = document.getElementById('profilePostViewerEmojiButton');
            const emojiPicker = document.getElementById('profilePostViewerEmojiPicker');
            const attachmentButton = document.getElementById('profilePostViewerAttachmentButton');
            const attachmentInput = document.getElementById('profilePostViewerAttachmentInput');
            const attachmentPreview = document.getElementById('profilePostViewerAttachmentPreview');
            const attachmentRemove = document.getElementById('profilePostViewerAttachmentRemove');
            emojiButton?.addEventListener('click', () => {
                if (!emojiPicker) return;
                emojiPicker.hidden = !emojiPicker.hidden;
                emojiButton.setAttribute('aria-expanded', emojiPicker.hidden ? 'false' : 'true');
            });
            emojiPicker?.querySelectorAll('[data-emoji]').forEach((button) => {
                button.addEventListener('click', () => {
                    const input = commentForm?.querySelector('[name="comment_text"]');
                    if (!input) return;
                    input.value += button.dataset.emoji || '';
                    input.focus();
                    emojiPicker.hidden = true;
                    emojiButton?.setAttribute('aria-expanded', 'false');
                });
            });
            attachmentButton?.addEventListener('click', () => attachmentInput?.click());
            attachmentInput?.addEventListener('change', () => {
                const file = attachmentInput.files && attachmentInput.files[0];
                const img = attachmentPreview?.querySelector('img');
                if (!file || !img || !attachmentPreview) return;
                img.src = URL.createObjectURL(file);
                attachmentPreview.hidden = false;
            });
            attachmentRemove?.addEventListener('click', () => {
                if (attachmentInput) attachmentInput.value = '';
                if (attachmentPreview) attachmentPreview.hidden = true;
            });

            commentForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                const formData = new FormData(commentForm);
                fetch(commentForm.getAttribute('action') || window.location.href, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json'},
                    body: new URLSearchParams(formData).toString()
                }).then((response) => response.json()).then((data) => {
                    if (!data || !data.ok) return;
                    const postId = formData.get('post_id') || viewer.dataset.postId || '';
                    if (data.comment) {
                        if (!window.snapixProfileComments[String(postId)]) window.snapixProfileComments[String(postId)] = [];
                        window.snapixProfileComments[String(postId)].unshift({
                            comment_id: data.comment.comment_id || Date.now(),
                            post_id: postId,
                            user_id: (window.snapixCurrentUser || {}).id || 0,
                            login: data.comment.login || '',
                            profile_url: data.comment.profile_url || 'profile.php',
                            avatar_url: data.comment.avatar_url || '',
                            comment_text: data.comment.text || '',
                            text: data.comment.text || '',
                            created_at: data.comment.created_at || '',
                            likes_count: 0,
                            is_liked: false
                        });
                        window.snapixRenderViewerComments(postId);
                    }
                    commentForm.querySelector('[name="comment_text"]').value = '';
                    if (window.snapixSyncPostState) window.snapixSyncPostState(postId, data);
                }).catch(() => {});
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
        var item = form.closest('.feed-action-item, .profile-hover-action-item');
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
