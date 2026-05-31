<?php
session_start();
require './config/config.php';
require './includes/icons.php';
require './includes/post-actions.php';
require_once './includes/side-menu.php';

function buildProfileUrl(int $profileUserId, ?int $currentUserId): string
{
    if ($currentUserId !== null && $profileUserId === $currentUserId) {
        return 'profile.php';
    }

    return 'user.php?id=' . $profileUserId;
}

$user = null;
$shareRecipients = [];

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
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
    }
}

$reportReasonsStmt = $pdo->query('SELECT id, label FROM moderation_reasons ORDER BY id ASC');
$reportReasons = $reportReasonsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $action = $_POST['action'] ?? '';
    $commentsPostId = 0;
    $isAjaxPostAction = snapix_is_ajax_request() && in_array($action, ['toggle_like', 'toggle_save', 'add_comment', 'add_repost', 'get_post_counts'], true);
    $ajaxExtra = [];

    if ($action === 'report_comment') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        $reasonId = (int) ($_POST['reason_id'] ?? 0);
        $customReason = trim($_POST['custom_reason'] ?? '');

        $commentStmt = $pdo->prepare('SELECT id, user_id FROM comments WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $commentStmt->execute(['id' => $commentId]);
        $comment = $commentStmt->fetch();

        if ($comment && (int) $comment['user_id'] !== (int) $user['id'] && ($reasonId > 0 || $customReason !== '')) {
            $insertReportStmt = $pdo->prepare('
                INSERT INTO moderation_reports (reporter_user_id, target_user_id, target_comment_id, reason_id, reason_text)
                VALUES (:reporter_user_id, :target_user_id, :target_comment_id, :reason_id, :reason_text)
            ');
            $insertReportStmt->execute([
                'reporter_user_id' => $user['id'],
                'target_user_id' => (int) $comment['user_id'],
                'target_comment_id' => $commentId,
                'reason_id' => $reasonId > 0 ? $reasonId : null,
                'reason_text' => mb_substr($customReason !== '' ? $customReason : 'Нарушение правил сообщества', 0, 1000),
            ]);
        }

        header('Location: index.php');
        exit;
    }

    $postId = (int) ($_POST['post_id'] ?? 0);
    $ownerId = (int) ($_POST['owner_id'] ?? 0);
    $postExists = false;

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

        if ($postExists && $action === 'delete_post' && $ownerId === (int) $user['id']) {
            $deletePostStmt = $pdo->prepare('UPDATE posts SET is_deleted = 1 WHERE id = :id AND user_id = :user_id');
            $deletePostStmt->execute([
                'id' => $postId,
                'user_id' => $user['id'],
            ]);
        }

        if ($postExists && $action === 'pin_post' && $ownerId === (int) $user['id']) {
            $pinExistsStmt = $pdo->prepare('SELECT id FROM pinned_posts WHERE user_id = :user_id AND post_id = :post_id');
            $pinExistsStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);

            if (!$pinExistsStmt->fetchColumn()) {
                $pinPostStmt = $pdo->prepare('INSERT INTO pinned_posts (user_id, post_id) VALUES (:user_id, :post_id)');
                $pinPostStmt->execute([
                    'user_id' => $user['id'],
                    'post_id' => $postId,
                ]);
            }
        }

        if ($postExists && $action === 'hide_post' && $ownerId !== (int) $user['id']) {
            $hideStmt = $pdo->prepare('INSERT IGNORE INTO hidden_posts (user_id, post_id) VALUES (:user_id, :post_id)');
            $hideStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);
        }

        if ($postExists && $action === 'report_post' && $ownerId !== (int) $user['id']) {
            $reportPostStmt = $pdo->prepare('
                INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text)
                VALUES (:reporter_user_id, :target_user_id, :reason_text)
            ');
            $reportReason = trim((string) ($_POST['report_reason'] ?? ''));
            $reportPostStmt->execute([
                'reporter_user_id' => $user['id'],
                'target_user_id' => $ownerId > 0 ? $ownerId : null,
                'reason_text' => mb_substr($reportReason !== '' ? $reportReason : ('Жалоба на пост #' . $postId), 0, 1000),
            ]);
        }

        if ($postExists && $action === 'report_post_user' && $ownerId !== (int) $user['id']) {
            $reportUserStmt = $pdo->prepare('
                INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text)
                VALUES (:reporter_user_id, :target_user_id, :reason_text)
            ');
            $reportUserStmt->execute([
                'reporter_user_id' => $user['id'],
                'target_user_id' => $ownerId > 0 ? $ownerId : null,
                'reason_text' => 'Жалоба на пользователя через пост #' . $postId,
            ]);
        }

        if ($postExists && $action === 'block_user' && $ownerId > 0 && $ownerId !== (int) $user['id']) {
            $blockUserStmt = $pdo->prepare('
                INSERT IGNORE INTO user_blocks (blocker_user_id, blocked_user_id)
                VALUES (:blocker_user_id, :blocked_user_id)
            ');
            $blockUserStmt->execute([
                'blocker_user_id' => (int) $user['id'],
                'blocked_user_id' => $ownerId,
            ]);
        }
    }

    if ($isAjaxPostAction) {
        if ($postId <= 0 || !$postExists) {
            snapix_send_post_action_error('post_not_found', 404);
        }

        snapix_send_post_action_json($pdo, $postId, (int) $user['id'], $ajaxExtra);
    }

    if ($commentsPostId > 0) {
        header('Location: index.php?comments_post=' . $commentsPostId);
        exit;
    }

    header('Location: index.php');
    exit;
}

$currentUserId = (int) ($user['id'] ?? 0);

$feedStmt = $pdo->query('
    SELECT
        posts.id,
        posts.caption,
        posts.created_at,
        users.id AS user_id,
        users.login,
        users.avatar,
        post_media.media_type,
        post_media.media_url,
        (
            SELECT COUNT(*)
            FROM likes
            WHERE likes.post_id = posts.id
        ) AS likes_count,
        (
            SELECT COUNT(*)
            FROM comments
            WHERE comments.post_id = posts.id AND comments.is_deleted = 0
        ) AS comments_count,
        (
            SELECT COUNT(*)
            FROM likes
            WHERE likes.post_id = posts.id
              AND likes.user_id = ' . (int) ($user['id'] ?? 0) . '
        ) AS is_liked,
        (
            SELECT COUNT(*)
            FROM saved_posts
            WHERE saved_posts.post_id = posts.id
        ) AS saves_count,
        (
            SELECT COUNT(*)
            FROM saved_posts
            WHERE saved_posts.post_id = posts.id
              AND saved_posts.user_id = ' . (int) ($user['id'] ?? 0) . '
        ) AS is_saved,
        (
            SELECT COUNT(*)
            FROM reposts
            WHERE reposts.post_id = posts.id
        ) AS reposts_count,
        (
            SELECT COUNT(*)
            FROM reposts
            WHERE reposts.post_id = posts.id
              AND reposts.user_id = ' . (int) ($user['id'] ?? 0) . '
        ) AS is_reposted,
        (
            SELECT COUNT(*)
            FROM pinned_posts
            WHERE pinned_posts.post_id = posts.id
              AND pinned_posts.user_id = ' . $currentUserId . '
        ) AS is_pinned,
        EXISTS(
            SELECT 1
            FROM followers current_follow
            WHERE current_follow.follower_id = ' . $currentUserId . '
              AND current_follow.following_id = posts.user_id
              AND current_follow.status = \'accepted\'
        ) AS is_following_author,
        EXISTS(
            SELECT 1
            FROM followers author_follow
            WHERE author_follow.follower_id = posts.user_id
              AND author_follow.following_id = ' . $currentUserId . '
              AND author_follow.status = \'accepted\'
        ) AS is_author_follower
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.is_deleted = 0
      AND posts.user_id <> ' . $currentUserId . '
      AND posts.id NOT IN (
          SELECT hidden_posts.post_id
          FROM hidden_posts
          WHERE hidden_posts.user_id = ' . $currentUserId . '
      )
      AND posts.user_id NOT IN (
          SELECT user_blocks.blocked_user_id
          FROM user_blocks
          WHERE user_blocks.blocker_user_id = ' . $currentUserId . '
      )
    ORDER BY posts.created_at DESC
    LIMIT 40
');
$feedPosts = $feedStmt->fetchAll();

$commentMap = [];
$repostMap = [];

if ($feedPosts) {
    $postIds = array_map(static fn($post): int => (int) $post['id'], $feedPosts);
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));

    $commentsStmt = $pdo->prepare(" 
        SELECT
            comments.id,
            comments.post_id,
            comments.comment_text,
            comments.created_at,
            users.id AS user_id,
            users.login,
            users.avatar
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
<body data-page="home">
    <?php render_side_menu($user); ?>

    <div class="home-feed-tabs" role="tablist" aria-label="Переключатель ленты">
        <button type="button" class="home-feed-tab is-active" role="tab" aria-selected="true" data-feed-tab="for-you">Для вас</button>
        <button type="button" class="home-feed-tab" role="tab" aria-selected="false" data-feed-tab="following">Подписки</button>
    </div>

    <main data-feed-content="for-you">
        <section class="feed-wrap">

            <?php if ($feedPosts): ?>
                <div class="feed-list">
                    <?php foreach ($feedPosts as $post): ?>
                        <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                        <?php $postReposters = $repostMap[(int) $post['id']] ?? []; ?>
                        <?php $authorProfileUrl = $user ? buildProfileUrl((int) $post['user_id'], (int) $user['id']) : 'login.php'; ?>
                        <?php $feedScope = (int) $post['is_following_author'] > 0 ? 'following' : 'for-you'; ?>
                        <article class="feed-card card-surface" id="post-<?php echo (int) $post['id']; ?>" data-post-id="<?php echo (int) $post['id']; ?>" data-post-likes-count="<?php echo (int) ($post['likes_count'] ?? 0); ?>" data-post-comments-count="<?php echo (int) ($post['comments_count'] ?? 0); ?>" data-post-reposts-count="<?php echo (int) ($post['reposts_count'] ?? 0); ?>" data-post-saves-count="<?php echo (int) ($post['saves_count'] ?? 0); ?>" data-feed-scope="<?php echo htmlspecialchars($feedScope); ?>">
                            <header class="feed-card-header">
                                <div class="feed-header-main">
                                    <a href="<?php echo htmlspecialchars($authorProfileUrl); ?>" class="feed-author-avatar-link" aria-label="Открыть профиль <?php echo htmlspecialchars($post['login']); ?>">
                                        <div class="feed-author-avatar"<?php if (!empty($post['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($post['avatar']); ?>');"<?php endif; ?>>
                                            <?php if (empty($post['avatar'])): ?>
                                                <?php echo htmlspecialchars(mb_substr($post['login'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                    <div class="feed-author-line">
                                        <a href="<?php echo htmlspecialchars($authorProfileUrl); ?>" class="feed-author-name">
                                            <strong><?php echo htmlspecialchars($post['login']); ?></strong>
                                        </a>
                                        <?php if ($user): ?>
                                        <div class="post-menu-wrap">
                                            <button type="button" class="post-menu-toggle" data-post-menu="post-menu-<?php echo (int) $post['id']; ?>" aria-label="Действия с публикацией"><?php echo snapix_icon('more-horizontal'); ?></button>
                                            <div class="post-menu" id="post-menu-<?php echo (int) $post['id']; ?>">
                                                <a href="<?php echo htmlspecialchars($authorProfileUrl); ?>" class="post-menu-item">
                                                    <img src="icon/dark theme/об аккаунте.png" alt="">
                                                    <span>Об аккаунте</span>
                                                </a>
                                                <?php if ($user): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="hide_post">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <input type="hidden" name="owner_id" value="<?php echo (int) $post['user_id']; ?>">
                                                        <button type="submit" class="post-menu-item">
                                                            <img src="icon/dark theme/dislike.png" alt="">
                                                            <span>Мне не интересно</span>
                                                        </button>
                                                    </form>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="block_user">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <input type="hidden" name="owner_id" value="<?php echo (int) $post['user_id']; ?>">
                                                        <button type="submit" class="post-menu-item">
                                                            <img src="icon/dark theme/stop.png" alt="">
                                                            <span>Добавить <?php echo htmlspecialchars($post['login']); ?> в чёрный список</span>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <a href="login.php" class="post-menu-item">
                                                        <img src="icon/dark theme/dislike.png" alt="">
                                                        <span>Мне не интересно</span>
                                                    </a>
                                                    <a href="login.php" class="post-menu-item">
                                                        <img src="icon/dark theme/stop.png" alt="">
                                                        <span>Добавить <?php echo htmlspecialchars($post['login']); ?> в чёрный список</span>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="post-menu-item js-copy-post-link" data-post-url="<?php echo htmlspecialchars('post.php?id=' . (int) $post['id'], ENT_QUOTES); ?>">
                                                    <img src="icon/dark theme/copy.png" alt="">
                                                    <span>Поделиться</span>
                                                </button>
                                                <?php if ($user): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="report_post">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <input type="hidden" name="owner_id" value="<?php echo (int) $post['user_id']; ?>">
                                                        <button type="submit" class="post-menu-item post-menu-item-danger" data-report-trigger data-report-login="<?php echo htmlspecialchars((string) ($post['login'] ?? 'user')); ?>" data-report-user-id="<?php echo (int) $post['user_id']; ?>">
                                                            <img src="icon/complaint.png" alt="">
                                                            <span>Пожаловаться</span>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <a href="login.php" class="post-menu-item post-menu-item-danger">
                                                        <img src="icon/complaint.png" alt="">
                                                        <span>Пожаловаться</span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </header>

                            <div class="feed-card-media">
                                <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                    <video controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                                <?php elseif (!empty($post['media_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="Публикация пользователя <?php echo htmlspecialchars($post['login']); ?>">
                                <?php else: ?>
                                    <div class="feed-card-media-placeholder">Медиа не доступно</div>
                                <?php endif; ?>
                            </div>

                            <div class="feed-card-body">
                                <div class="feed-card-actions">

                                    <?php if ($user): ?>
                                        <div class="feed-card-buttons">
                                            <div class="feed-card-buttons-group feed-card-buttons-left">
                                                <div class="feed-action-item">
                                                    <form method="post" class="inline-action-form">
                                                        <input type="hidden" name="action" value="toggle_like">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><img src="icon/dark theme/like.png" alt=""></button>
                                                    </form>
                                                    <span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span>
                                                </div>

                                                <div class="feed-action-item">
                                                    <button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button>
                                                    <span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span>
                                                </div>

                                                <div class="feed-action-item">
                                                    <form method="post" class="inline-action-form">
                                                        <input type="hidden" name="action" value="add_repost">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><img src="icon/dark theme/repost.png" alt=""></button>
                                                    </form>
                                                    <span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span>
                                                </div>
                                            </div>

                                            <div class="feed-card-buttons-group feed-card-buttons-right">
                                                <div class="feed-action-item">
                                                    <form method="post" class="inline-action-form">
                                                        <input type="hidden" name="action" value="toggle_save">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><img src="icon/dark theme/favourites.png" alt=""></button>
                                                    </form>
                                                    <span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span>
                                                </div>

                                                <div class="feed-action-item">
                                                    <button type="button" class="feed-action-btn feed-icon-btn js-open-share-modal" data-post-id="<?php echo (int) $post['id']; ?>" aria-label="Отправить в сообщения"><img src="icon/dark theme/share.png" alt=""></button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="feed-card-buttons">
                                            <div class="feed-card-buttons-group feed-card-buttons-left">
                                                <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для лайка"><img src="icon/dark theme/like.png" alt=""></a><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                                <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                                <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для репоста"><img src="icon/dark theme/repost.png" alt=""></a><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                            </div>
                                            <div class="feed-card-buttons-group feed-card-buttons-right">
                                                <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для избранного"><img src="icon/dark theme/favourites.png" alt=""></a><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                                <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для отправки в сообщения"><img src="icon/dark theme/share.png" alt=""></a></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($post['caption'])): ?>
                                    <div class="feed-card-caption">
                                        <?php echo nl2br(htmlspecialchars($post['caption'])); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="comments-modal<?php echo (isset($_GET['comments_post']) && (int) $_GET['comments_post'] === (int) $post['id']) ? ' is-open' : ''; ?>" id="comments-modal-<?php echo (int) $post['id']; ?>">
                                    <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-<?php echo (int) $post['id']; ?>"></div>
                                    <div class="comments-modal-dialog">
                                        <div class="comments-modal-header">
                                            <h3>Комментарии</h3>
                                            <button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-<?php echo (int) $post['id']; ?>" aria-label="Закрыть"><?php echo snapix_icon('x'); ?></button>
                                        </div>
                                        <div class="comments-modal-body">
                                            <?php if ($postComments): ?>
                                                <?php foreach ($postComments as $comment): ?>
                                                    <?php $commentProfileUrl = buildProfileUrl((int) $comment['user_id'], $user ? (int) $user['id'] : null); ?>
                                                    <div class="comment-item">
                                                        <a href="<?php echo htmlspecialchars($commentProfileUrl); ?>" class="comment-author"><strong><?php echo htmlspecialchars($comment['login']); ?></strong></a>
                                                        <p><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="comments-empty">Пока нет комментариев.</p>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($user): ?>
                                            <form method="post" class="comment-form comments-modal-form">
                                                <input type="hidden" name="action" value="add_comment">
                                                <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
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
                <p class="empty-state feed-tab-empty is-hidden" data-feed-empty="for-you">В разделе «Для вас» пока нет новых публикаций.</p>
                <p class="empty-state feed-tab-empty is-hidden" data-feed-empty="following">В подписках пока нет публикаций.</p>
            <?php else: ?>
                <p class="empty-state">Лента публикаций пустая. Добавьте первую публикацию.</p>
            <?php endif; ?>
        </section>
    </main>
<?php if ($user): ?>
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
(function () {
    var tabs = document.querySelectorAll('.home-feed-tab');
    var feedContent = document.querySelector('[data-feed-content]');
    var feedCards = document.querySelectorAll('[data-feed-scope]');
    var emptyStates = document.querySelectorAll('[data-feed-empty]');

    if (!tabs.length || !feedContent) {
        return;
    }

    function updateFeedVisibility(scope) {
        var visibleCount = 0;

        feedCards.forEach(function (card) {
            var isVisible = card.getAttribute('data-feed-scope') === scope;
            card.classList.toggle('is-hidden', !isVisible);

            if (isVisible) {
                visibleCount += 1;
            }
        });

        emptyStates.forEach(function (emptyState) {
            emptyState.classList.toggle('is-hidden', emptyState.getAttribute('data-feed-empty') !== scope || visibleCount > 0);
        });
    }

    function activateTab(tab) {
        if (tab.classList.contains('is-active')) {
            return;
        }

        tabs.forEach(function (item) {
            item.classList.remove('is-active');
            item.setAttribute('aria-selected', 'false');
        });

        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');
        var scope = tab.getAttribute('data-feed-tab') || 'for-you';
        feedContent.setAttribute('data-feed-content', scope);
        updateFeedVisibility(scope);
        feedContent.classList.add('is-switching');
        window.setTimeout(function () {
            feedContent.classList.remove('is-switching');
        }, 180);
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateTab(tab);
        });

        tab.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activateTab(tab);
            }
        });
    });

    updateFeedVisibility(feedContent.getAttribute('data-feed-content') || 'for-you');
})();

document.querySelectorAll('.js-open-comments-modal').forEach(function (button) {
    button.addEventListener('click', function () {
        var modalId = button.getAttribute('data-modal');
        var modal = modalId ? document.getElementById(modalId) : null;
        if (modal) {
            modal.classList.add('is-open');
        }
    });
});

document.querySelectorAll('.js-close-comments-modal').forEach(function (button) {
    button.addEventListener('click', function () {
        var modalId = button.getAttribute('data-modal');
        var modal = modalId ? document.getElementById(modalId) : null;
        if (modal) {
            modal.classList.remove('is-open');
        }
    });
});

document.querySelectorAll('.post-menu-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        var menuId = button.getAttribute('data-post-menu');
        var menu = menuId ? document.getElementById(menuId) : null;
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

document.addEventListener('click', function (event) {
    document.querySelectorAll('.post-menu').forEach(function (menu) {
        var wrap = menu.closest('.post-menu-wrap');
        if (wrap && !wrap.contains(event.target)) {
            menu.classList.remove('is-open');
        }
    });
});

document.querySelectorAll('.js-copy-post-link').forEach(function (button) {
    button.addEventListener('click', function () {
        var postUrl = button.getAttribute('data-post-url') || '';
        var absoluteUrl = new URL(postUrl, window.location.href).toString();
        var label = button.querySelector('span');
        var defaultText = label ? label.textContent : '';

        function markCopied() {
            if (label) {
                label.textContent = 'Ссылка скопирована';
                window.setTimeout(function () {
                    label.textContent = defaultText;
                }, 1400);
            }
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(absoluteUrl).then(markCopied).catch(function () {});
            return;
        }

        var field = document.createElement('textarea');
        field.value = absoluteUrl;
        field.setAttribute('readonly', 'readonly');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        try {
            document.execCommand('copy');
            markCopied();
        } catch (error) {}
        field.remove();
    });
});

document.querySelectorAll('.js-post-menu-feedback').forEach(function (button) {
    button.addEventListener('click', function () {
        var label = button.querySelector('span');
        var defaultText = label ? label.textContent : '';

        if (label) {
            label.textContent = button.getAttribute('data-feedback') || 'Готово';
            window.setTimeout(function () {
                label.textContent = defaultText;
            }, 1600);
        }
    });
});

(function () {
    var modal = document.getElementById('share-post-modal');
    if (!modal) {
        return;
    }

    var activePostId = 0;

    document.querySelectorAll('.js-open-share-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            activePostId = Number(button.getAttribute('data-post-id') || 0);
            modal.classList.add('is-open');
        });
    });

    modal.querySelectorAll('.js-close-share-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            modal.classList.remove('is-open');
        });
    });

    modal.querySelectorAll('.js-share-send-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!activePostId) {
                return;
            }
            var params = new URLSearchParams();
            params.set('post_id', String(activePostId));
            params.set('receiver_id', String(button.getAttribute('data-recipient-id')));

            fetch('share-post.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok) {
                    return;
                }
                button.textContent = 'Отправлено';
                button.disabled = true;
                setTimeout(function () {
                    button.textContent = 'Отправить';
                    button.disabled = false;
                    modal.classList.remove('is-open');
                }, 700);
            })
            .catch(function () {});
        });
    });
})();
</script>
</body>
</html>
