<?php
session_start();
require './config/config.php';
require './includes/post-actions.php';
require_once './includes/side-menu.php';

function clips_build_profile_url(int $profileUserId, ?int $currentUserId): string
{
    if ($currentUserId !== null && $profileUserId === $currentUserId) {
        return 'profile.php';
    }

    return 'user.php?id=' . $profileUserId;
}

function clips_extract_hashtags(string $caption): array
{
    preg_match_all('/#[\p{L}\p{N}_]+/u', $caption, $matches);

    return array_values(array_unique($matches[0] ?? []));
}


function clips_fetch_comments(PDO $pdo, int $postId, ?int $currentUserId): array
{
    $stmt = $pdo->prepare("
        SELECT 
            comments.id,
            comments.comment_text,
            comments.created_at,
            users.id AS user_id,
            users.login,
            users.avatar
        FROM comments
        INNER JOIN users ON users.id = comments.user_id
        WHERE comments.post_id = :post_id 
          AND comments.is_deleted = 0
        ORDER BY comments.created_at DESC, comments.id DESC
        LIMIT 80
    ");

    $stmt->execute([
        'post_id' => $postId
    ]);

    $rows = $stmt->fetchAll() ?: [];
    $comments = [];

    foreach ($rows as $row) {
        $comments[] = [
            'id' => (int) ($row['id'] ?? 0),
            'text' => (string) ($row['comment_text'] ?? ''),
            'login' => (string) ($row['login'] ?? ''),
            'profile_url' => clips_build_profile_url((int) ($row['user_id'] ?? 0), $currentUserId),
            'avatar' => (string) ($row['avatar'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'likes_count' => 0,
            'replies_count' => 0,
        ];
    }

    return $comments;
}

function clips_fetch_follow_relation_map(PDO $pdo, int $currentUserId): array
{
    $relationMap = [];
    if ($currentUserId <= 0) {
        return $relationMap;
    }

    $stmt = $pdo->prepare("
        SELECT following_id AS uid
        FROM followers
        WHERE follower_id = :user_id
          AND status = 'accepted'
          AND following_id <> :user_id

        UNION

        SELECT follower_id AS uid
        FROM followers
        WHERE following_id = :user_id
          AND status = 'accepted'
          AND follower_id <> :user_id
    ");
    $stmt->execute(['user_id' => $currentUserId]);

    foreach (($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) as $uid) {
        $relationMap[(int) $uid] = true;
    }

    return $relationMap;
}


function clips_fetch_post_stats(PDO $pdo, int $postId, int $ownerUserId): array
{
    $totalsStmt = $pdo->prepare('
        SELECT
            (SELECT COUNT(*) FROM likes WHERE post_id = :likes_post_id) AS likes_total,
            (SELECT COUNT(*) FROM comments WHERE post_id = :comments_post_id AND is_deleted = 0) AS comments_total,
            (SELECT COUNT(*) FROM reposts WHERE post_id = :reposts_post_id) AS reposts_total,
            (SELECT COUNT(*) FROM saved_posts WHERE post_id = :saves_post_id) AS saves_total
    ');
    $totalsStmt->execute([
        'likes_post_id' => $postId,
        'comments_post_id' => $postId,
        'reposts_post_id' => $postId,
        'saves_post_id' => $postId,
    ]);
    $totals = $totalsStmt->fetch() ?: [];

    $dailyStmt = $pdo->prepare('
        SELECT
            event_day,
            SUM(likes_count) AS likes,
            SUM(comments_count) AS comments,
            SUM(reposts_count) AS reposts,
            SUM(saves_count) AS saves
        FROM (
            SELECT DATE(created_at) AS event_day, COUNT(*) AS likes_count, 0 AS comments_count, 0 AS reposts_count, 0 AS saves_count
            FROM likes
            WHERE post_id = :daily_likes_post_id
            GROUP BY DATE(created_at)
            UNION ALL
            SELECT DATE(created_at) AS event_day, 0 AS likes_count, COUNT(*) AS comments_count, 0 AS reposts_count, 0 AS saves_count
            FROM comments
            WHERE post_id = :daily_comments_post_id AND is_deleted = 0
            GROUP BY DATE(created_at)
            UNION ALL
            SELECT DATE(created_at) AS event_day, 0 AS likes_count, 0 AS comments_count, COUNT(*) AS reposts_count, 0 AS saves_count
            FROM reposts
            WHERE post_id = :daily_reposts_post_id
            GROUP BY DATE(created_at)
            UNION ALL
            SELECT DATE(created_at) AS event_day, 0 AS likes_count, 0 AS comments_count, 0 AS reposts_count, COUNT(*) AS saves_count
            FROM saved_posts
            WHERE post_id = :daily_saves_post_id
            GROUP BY DATE(created_at)
        ) AS events
        WHERE event_day IS NOT NULL
        GROUP BY event_day
        ORDER BY event_day ASC
    ');
    $dailyStmt->execute([
        'daily_likes_post_id' => $postId,
        'daily_comments_post_id' => $postId,
        'daily_reposts_post_id' => $postId,
        'daily_saves_post_id' => $postId,
    ]);

    $daily = [];
    foreach (($dailyStmt->fetchAll() ?: []) as $row) {
        $daily[] = [
            'date' => (string) ($row['event_day'] ?? ''),
            'likes' => (int) ($row['likes'] ?? 0),
            'comments' => (int) ($row['comments'] ?? 0),
            'reposts' => (int) ($row['reposts'] ?? 0),
            'saves' => (int) ($row['saves'] ?? 0),
        ];
    }

    $topPostStmt = $pdo->prepare('
        SELECT
            posts.id,
            post_media.media_url,
            post_media.media_type,
            (
                COALESCE(likes_agg.likes_count, 0) +
                COALESCE(comments_agg.comments_count, 0) +
                COALESCE(reposts_agg.reposts_count, 0) +
                COALESCE(saves_agg.saves_count, 0)
            ) AS interactions_total
        FROM posts
        LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
        LEFT JOIN (
            SELECT post_id, COUNT(*) AS likes_count
            FROM likes
            GROUP BY post_id
        ) AS likes_agg ON likes_agg.post_id = posts.id
        LEFT JOIN (
            SELECT post_id, COUNT(*) AS comments_count
            FROM comments
            WHERE is_deleted = 0
            GROUP BY post_id
        ) AS comments_agg ON comments_agg.post_id = posts.id
        LEFT JOIN (
            SELECT post_id, COUNT(*) AS reposts_count
            FROM reposts
            GROUP BY post_id
        ) AS reposts_agg ON reposts_agg.post_id = posts.id
        LEFT JOIN (
            SELECT post_id, COUNT(*) AS saves_count
            FROM saved_posts
            GROUP BY post_id
        ) AS saves_agg ON saves_agg.post_id = posts.id
        WHERE posts.user_id = :owner_user_id
          AND posts.is_deleted = 0
        ORDER BY interactions_total DESC, posts.created_at DESC, posts.id DESC
        LIMIT 1
    ');
    $topPostStmt->execute(['owner_user_id' => $ownerUserId]);
    $topPost = $topPostStmt->fetch() ?: [];

    return [
        'likes_total' => (int) ($totals['likes_total'] ?? 0),
        'comments_total' => (int) ($totals['comments_total'] ?? 0),
        'reposts_total' => (int) ($totals['reposts_total'] ?? 0),
        'saves_total' => (int) ($totals['saves_total'] ?? 0),
        'daily' => $daily,
        'top_post' => $topPost ? [
            'id' => (int) ($topPost['id'] ?? 0),
            'media_url' => (string) ($topPost['media_url'] ?? ''),
            'media_type' => (string) ($topPost['media_type'] ?? ''),
            'interactions_total' => (int) ($topPost['interactions_total'] ?? 0),
        ] : [],
    ];
}

$user = null;

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT id, login, avatar, role FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = (int) ($_POST['post_id'] ?? 0);
    $isAjaxPostAction = snapix_is_ajax_request() && in_array($action, ['toggle_like', 'toggle_save', 'add_repost', 'hide_post', 'block_user', 'report_post', 'delete_post', 'toggle_follow_user', 'get_clip_post_stats'], true);
    $requiresAuth = in_array($action, ['toggle_like', 'toggle_save', 'add_repost', 'hide_post', 'block_user', 'report_post', 'add_comment', 'delete_post', 'toggle_follow_user', 'get_follow_relations', 'get_clip_post_stats'], true);

    if ($requiresAuth && !$user) {
        snapix_send_post_action_error('login_required', 401);
    }
    $ajaxExtra = [];
    $postExists = false;
    $postOwnerId = 0;

    if ($postId > 0) {
        $postExistsStmt = $pdo->prepare("
            SELECT posts.id, posts.user_id
            FROM posts
            INNER JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
            WHERE posts.id = :id
              AND posts.is_deleted = 0
              AND post_media.media_type = 'video'
            LIMIT 1
        ");
        $postExistsStmt->execute(['id' => $postId]);
        $postRow = $postExistsStmt->fetch();
        $postExists = (bool) $postRow;
        $postOwnerId = (int) ($postRow['user_id'] ?? 0);
    }

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

    if ($postExists && $action === 'add_repost') {
        $repostExistsStmt = $pdo->prepare('SELECT id FROM reposts WHERE user_id = :user_id AND post_id = :post_id');
        $repostExistsStmt->execute([
            'user_id' => $user['id'],
            'post_id' => $postId,
        ]);
        $repostId = $repostExistsStmt->fetchColumn();

        if ($repostId) {
            $deleteRepostStmt = $pdo->prepare('DELETE FROM reposts WHERE id = :id AND user_id = :user_id');
            $deleteRepostStmt->execute([
                'id' => (int) $repostId,
                'user_id' => $user['id'],
            ]);
            $ajaxExtra['reposted'] = false;
        } else {
            $insertRepostStmt = $pdo->prepare('INSERT IGNORE INTO reposts (user_id, post_id) VALUES (:user_id, :post_id)');
            $insertRepostStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);
            $ajaxExtra['reposted'] = true;
        }
    }

    if ($postExists && $action === 'hide_post') {
        $hideStmt = $pdo->prepare('INSERT IGNORE INTO hidden_posts (user_id, post_id) VALUES (:user_id, :post_id)');
        $hideStmt->execute([
            'user_id' => (int) $user['id'],
            'post_id' => $postId,
        ]);
        $ajaxExtra['hidden'] = true;
    }

    if ($postExists && $action === 'block_user' && $postOwnerId > 0 && $postOwnerId !== (int) $user['id']) {
        $blockUserStmt = $pdo->prepare('
            INSERT IGNORE INTO user_blocks (blocker_user_id, blocked_user_id)
            VALUES (:blocker_user_id, :blocked_user_id)
        ');
        $blockUserStmt->execute([
            'blocker_user_id' => (int) $user['id'],
            'blocked_user_id' => $postOwnerId,
        ]);
        $ajaxExtra['blocked'] = true;
        $ajaxExtra['blocked_user_id'] = $postOwnerId;
    }

    if ($postExists && $action === 'report_post' && $postOwnerId > 0 && $postOwnerId !== (int) $user['id']) {
        $reportReason = trim((string) ($_POST['report_reason'] ?? ''));
        $reportPostStmt = $pdo->prepare('
            INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text)
            VALUES (:reporter_user_id, :target_user_id, :reason_text)
        ');
        $reportPostStmt->execute([
            'reporter_user_id' => (int) $user['id'],
            'target_user_id' => $postOwnerId,
            'reason_text' => mb_substr($reportReason !== '' ? $reportReason : ('Жалоба на clips #' . $postId), 0, 1000),
        ]);
        $ajaxExtra['reported'] = true;
    }

    if ($postExists && $action === 'delete_post' && $postOwnerId === (int) $user['id']) {
        $pdo->prepare('UPDATE posts SET is_deleted = 1 WHERE id = :id LIMIT 1')->execute(['id' => $postId]);
        $ajaxExtra['deleted'] = true;
    }

    if ($action === 'get_clip_post_stats') {
        if ($postId <= 0 || !$postExists) {
            snapix_send_post_action_error('post_not_found', 404);
        }

        $canViewStats = $postOwnerId === (int) $user['id'] || in_array((string) ($user['role'] ?? ''), ['admin', 'moderator'], true);
        if (!$canViewStats) {
            snapix_send_post_action_error('forbidden', 403);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'stats' => clips_fetch_post_stats($pdo, $postId, $postOwnerId),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'get_comments') {
        if ($postId <= 0 || !$postExists) {
            snapix_send_post_action_error('post_not_found', 404);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'comments' => clips_fetch_comments($pdo, $postId, $user ? (int) $user['id'] : null),
        ]);
        exit;
    }

    if ($action === 'add_comment') {
        if (!$user) {
            snapix_send_post_action_error('login_required', 401);
        }

        if ($postId <= 0 || !$postExists) {
            snapix_send_post_action_error('post_not_found', 404);
        }

        $commentText = trim((string) ($_POST['comment_text'] ?? ''));

        if ($commentText === '') {
            snapix_send_post_action_error('empty_comment', 422);
        }

        $insertCommentStmt = $pdo->prepare('INSERT INTO comments (user_id, post_id, comment_text) VALUES (:user_id, :post_id, :comment_text)');
        $insertCommentStmt->execute([
            'user_id' => (int) $user['id'],
            'post_id' => $postId,
            'comment_text' => mb_substr($commentText, 0, 1000),
        ]);

        $comment = [
            'text' => mb_substr($commentText, 0, 1000),
            'login' => (string) $user['login'],
            'profile_url' => clips_build_profile_url((int) $user['id'], (int) $user['id']),
        ];

        snapix_send_post_action_json($pdo, $postId, (int) $user['id'], ['comment' => $comment]);
    }

    if ($action === 'get_follow_relations') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'relation_map' => clips_fetch_follow_relation_map($pdo, (int) $user['id']),
        ]);
        exit;
    }

    if ($action === 'toggle_follow_user') {
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        if ($targetUserId <= 0 || $targetUserId === (int) $user['id']) {
            snapix_send_post_action_error('follow_invalid_target', 422);
        }

        $existsStmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $existsStmt->execute(['id' => $targetUserId]);
        if (!$existsStmt->fetchColumn()) {
            snapix_send_post_action_error('follow_target_not_found', 404);
        }

        $relationStmt = $pdo->prepare("
            SELECT id
            FROM followers
            WHERE follower_id = :follower_id
              AND following_id = :following_id
            LIMIT 1
        ");
        $relationStmt->execute([
            'follower_id' => (int) $user['id'],
            'following_id' => $targetUserId,
        ]);
        $relationId = (int) $relationStmt->fetchColumn();

        if ($relationId > 0) {
            $pdo->prepare('DELETE FROM followers WHERE id = :id LIMIT 1')->execute(['id' => $relationId]);
            $isFollowing = false;
        } else {
            $pdo->prepare("
                INSERT IGNORE INTO followers (follower_id, following_id, status, declined_until)
                VALUES (:follower_id, :following_id, 'accepted', NULL)
            ")->execute([
                'follower_id' => (int) $user['id'],
                'following_id' => $targetUserId,
            ]);
            $isFollowing = true;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'is_following' => $isFollowing,
            'relation_map' => clips_fetch_follow_relation_map($pdo, (int) $user['id']),
        ]);
        exit;
    }

    if ($isAjaxPostAction) {
        if ($postId <= 0 || !$postExists) {
            snapix_send_post_action_error('post_not_found', 404);
        }

        snapix_send_post_action_json($pdo, $postId, (int) $user['id'], $ajaxExtra);
    }

    header('Location: clips.php');
    exit;
}

$currentUserId = (int) ($user['id'] ?? 0);
$relatedUserIds = clips_fetch_follow_relation_map($pdo, $currentUserId);
$canModerateClips = in_array((string) ($user['role'] ?? ''), ['admin', 'moderator'], true);

$clipsStmt = $pdo->query('
    SELECT
        posts.id,
        posts.caption,
        posts.created_at,
        users.id AS user_id,
        users.login,
        users.avatar,
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
            FROM saved_posts
            WHERE saved_posts.post_id = posts.id
        ) AS saves_count,
        (
            SELECT COUNT(*)
            FROM reposts
            WHERE reposts.post_id = posts.id
        ) AS reposts_count,
        (
            SELECT COUNT(*)
            FROM likes
            WHERE likes.post_id = posts.id
              AND likes.user_id = ' . $currentUserId . '
        ) AS is_liked,
        (
            SELECT COUNT(*)
            FROM saved_posts
            WHERE saved_posts.post_id = posts.id
              AND saved_posts.user_id = ' . $currentUserId . '
        ) AS is_saved,
        (
            SELECT COUNT(*)
            FROM reposts
            WHERE reposts.post_id = posts.id
              AND reposts.user_id = ' . $currentUserId . '
        ) AS is_reposted
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    INNER JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.is_deleted = 0
      AND post_media.media_type = \'video\'
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
    ORDER BY posts.created_at DESC, posts.id DESC
    LIMIT 60
');
$clipsRows = $clipsStmt->fetchAll() ?: [];

$clipsByCategory = [
    'recommended' => [],
    'following' => [],
    'authored' => [],
];

foreach ($clipsRows as $clip) {
    $clipUserId = (int) $clip['user_id'];
    $caption = (string) ($clip['caption'] ?? '');
    $hashtags = clips_extract_hashtags($caption);
    $description = trim(preg_replace('/#[\p{L}\p{N}_]+/u', '', $caption));

    $preparedClip = [
        'id' => (int) $clip['id'],
        'videoUrl' => (string) $clip['media_url'],
        'description' => $description,
        'hashtags' => $hashtags,
        'createdAt' => (string) $clip['created_at'],
        'author' => [
            'id' => $clipUserId,
            'login' => (string) $clip['login'],
            'avatar' => (string) ($clip['avatar'] ?? ''),
            'profileUrl' => clips_build_profile_url($clipUserId, $user ? (int) $user['id'] : null),
            'canFollow' => $currentUserId > 0 && $clipUserId !== $currentUserId && !isset($relatedUserIds[$clipUserId]),
        ],
        'counts' => [
            'likes' => (int) $clip['likes_count'],
            'comments' => (int) $clip['comments_count'],
            'saves' => (int) $clip['saves_count'],
            'reposts' => (int) $clip['reposts_count'],
        ],
        'state' => [
            'liked' => (int) $clip['is_liked'] > 0,
            'saved' => (int) $clip['is_saved'] > 0,
            'reposted' => (int) $clip['is_reposted'] > 0,
        ],
        'isOwn' => $clipUserId === $currentUserId,
    ];

    if ($clipUserId !== $currentUserId) {
        $clipsByCategory['recommended'][] = $preparedClip;
    }

    if ($currentUserId > 0 && $clipUserId === $currentUserId) {
        $clipsByCategory['authored'][] = $preparedClip;
    }

    if ($currentUserId > 0 && $clipUserId !== $currentUserId && isset($relatedUserIds[$clipUserId])) {
        $clipsByCategory['following'][] = $preparedClip;
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
<body data-page="clips" class="has-side-menu">
    <?php render_side_menu($user); ?>

    <main class="clips-page">
        <div class="home-feed-tabs clips-feed-tabs" role="tablist" aria-label="Категории Clips">
            <button type="button" class="home-feed-tab is-active" role="tab" aria-selected="true" data-clips-tab="recommended">Для вас</button>
            <button type="button" class="home-feed-tab" role="tab" aria-selected="false" data-clips-tab="following">Подписки</button>
            <button type="button" class="home-feed-tab" role="tab" aria-selected="false" data-clips-tab="authored">Авторское</button>
        </div>
        <?php
$hasAnyClips = !empty($clipsByCategory['recommended'])
    || !empty($clipsByCategory['following'])
    || !empty($clipsByCategory['authored']);
?>

<?php if ($hasAnyClips): ?>
            <section class="clips-shell" aria-label="Clips">
                <div class="clips-empty-state is-hidden" data-clips-empty-state></div>
                <div class="clips-info">
                    <div class="clips-author-row">
                        <a href="#" class="clips-author-link" data-clips-author-link>
                            <span class="clips-avatar" data-clips-avatar></span>
                            <span class="clips-author-meta">
                                <strong data-clips-login></strong>
                                <span data-clips-time></span>
                            </span>
                        </a>
                        <?php if ($user): ?>
                            <button type="button" class="clips-follow-link is-hidden" data-clips-follow-btn>Подписаться</button>
                        <?php endif; ?>
                        <div class="clips-menu-wrap">
                            <button type="button" class="clips-menu-toggle" data-clips-menu-toggle aria-label="Действия с clips">
                                <span></span>
                                <span></span>
                                <span></span>
                            </button>
                            <div class="post-menu clips-post-menu" data-clips-menu>
                                <div data-clips-menu-own class="is-hidden">
                                    <button type="button" class="post-menu-item post-menu-item-danger" data-clips-menu-action="delete_post">
                                        <img src="icon/trash.png" alt="">
                                        <span>Удалить</span>
                                    </button>
                                    <button type="button" class="post-menu-item" data-clips-menu-action="open_stats" data-clips-stats-btn data-post-id="">
                                        <img src="icon/dark theme/analytic.png" alt="">
                                        <span>Кто посмотрел пост</span>
                                    </button>
                                </div>
                                <div data-clips-menu-foreign class="is-hidden">
                                    <a href="#" class="post-menu-item" data-clips-menu-account>
                                        <img src="icon/dark theme/об аккаунте.png" alt="">
                                        <span>Об аккаунте</span>
                                    </a>
                                    <?php if ($canModerateClips): ?>
                                        <button type="button" class="post-menu-item" data-clips-menu-action="open_stats" data-clips-stats-btn data-post-id="">
                                            <img src="icon/dark theme/analytic.png" alt="">
                                            <span>Кто посмотрел пост</span>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($user): ?>
                                        <button type="button" class="post-menu-item" data-clips-menu-action="hide_post">
                                            <img src="icon/dark theme/dislike.png" alt="">
                                            <span>Мне не интересно</span>
                                        </button>
                                        <button type="button" class="post-menu-item" data-clips-menu-action="block_user">
                                            <img src="icon/dark theme/stop.png" alt="">
                                            <span data-clips-block-label>Добавить в чёрный список</span>
                                        </button>
                                    <?php else: ?>
                                        <a href="login.php" class="post-menu-item">
                                            <img src="icon/dark theme/dislike.png" alt="">
                                            <span>Мне не интересно</span>
                                        </a>
                                        <a href="login.php" class="post-menu-item">
                                            <img src="icon/dark theme/stop.png" alt="">
                                            <span data-clips-block-label>Добавить в чёрный список</span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($user): ?>
                                        <button type="button" class="post-menu-item post-menu-item-danger" data-clips-menu-action="report_post">
                                            <img src="icon/complaint.png" alt="">
                                            <span>Пожаловаться</span>
                                        </button>
                                    <?php else: ?>
                                        <a href="login.php" class="post-menu-item post-menu-item-danger">
                                            <img src="icon/complaint.png" alt="">
                                            <span>Пожаловаться</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="clips-description" data-clips-description></p>
                    <div class="clips-hashtags" data-clips-hashtags></div>
                </div>

                <div class="clips-video-wrap">
                    <video class="clips-video" data-clips-video controls playsinline autoplay loop muted></video>
                </div>

                <div class="clips-actions" aria-label="Действия с Clips">
                    <?php if ($user): ?>
                        <button type="button" class="clips-action-btn clips-like-btn" data-clips-action="toggle_like" aria-label="Лайк">
                            <img src="icon/dark theme/like.png" alt="">
                            <span data-clips-count="likes">0</span>
                        </button>
                        <a href="#" class="clips-action-btn clips-comment-btn" data-clips-comment-link aria-label="Комментарии">
                            <img src="icon/dark theme/comment.png" alt="">
                            <span data-clips-count="comments">0</span>
                        </a>
                        <button type="button" class="clips-action-btn clips-repost-btn" data-clips-action="add_repost" aria-label="Репост">
                            <img src="icon/dark theme/repost.png" alt="">
                            <span data-clips-count="reposts">0</span>
                        </button>
                        <button type="button" class="clips-action-btn clips-save-btn" data-clips-action="toggle_save" data-save-post-id="" aria-label="Избранное">
                            <img src="icon/dark theme/favourites.png" alt="">
                            <span data-clips-count="saves">0</span>
                        </button>
                    <?php else: ?>
                        <a href="login.php" class="clips-action-btn" aria-label="Войти для лайка"><img src="icon/dark theme/like.png" alt=""><span data-clips-count="likes">0</span></a>
                        <a href="#" class="clips-action-btn clips-comment-btn" data-clips-comment-link aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""><span data-clips-count="comments">0</span></a>
                        <a href="login.php" class="clips-action-btn" aria-label="Войти для репоста"><img src="icon/dark theme/repost.png" alt=""><span data-clips-count="reposts">0</span></a>
                        <a href="login.php" class="clips-action-btn" aria-label="Войти для избранного"><img src="icon/dark theme/favourites.png" alt=""><span data-clips-count="saves">0</span></a>
                    <?php endif; ?>
                </div>


                <div class="clips-comments-modal" data-clips-comments-modal>
                    <div class="clips-comments-modal-header">
                        <button type="button" class="clips-comments-close" data-clips-comments-close aria-label="Закрыть">×</button>
                        <h3>Комментарии</h3>
                        <span class="clips-comments-header-spacer" aria-hidden="true"></span>
                    </div>
                    <div class="clips-comments-modal-body" data-clips-comments-list>
                        <p class="comments-empty">Загрузка...</p>
                    </div>
                    <?php if ($user): ?>
                        <form class="clips-comments-form" data-clips-comments-form>
                            <textarea name="comment_text" rows="2" maxlength="1000" placeholder="Добавьте комментарий..."></textarea>
                            <button type="submit" class="clips-comments-send" aria-label="Отправить комментарий">➤</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="clips-comment-actions-modal" data-clips-comment-actions-modal>
                    <button type="button" class="clips-comment-actions-overlay" data-clips-comment-actions-close aria-label="Закрыть меню"></button>
                    <div class="post-menu clips-comment-actions-dialog" data-clips-comment-actions-dialog>
                        <button type="button" class="post-menu-item post-menu-item-danger" data-clips-comment-action="report_post">
                            <img src="icon/complaint.png" alt="">
                            <span>Пожаловаться</span>
                        </button>
                    </div>
                </div>
                <div class="clips-stats-modal" data-clips-stats-modal aria-hidden="true">
                    <button type="button" class="clips-stats-modal-overlay" data-clips-stats-close aria-label="Закрыть статистику"></button>
                    <div class="clips-stats-modal-dialog" role="dialog" aria-modal="true" aria-label="Статистика публикации">
                        <div class="clips-stats-modal-header">
                            <button type="button" class="clips-stats-modal-close" data-clips-stats-close aria-label="Закрыть">×</button>
                            <h3>Статистика публикации</h3>
                            <span class="clips-stats-header-spacer" aria-hidden="true"></span>
                        </div>
                        <div class="clips-stats-modal-body" data-clips-stats-content>
                            <p class="clips-stats-status">Загрузка...</p>
                        </div>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="clips-empty" data-clips-empty-fallback>
                <h1>Видео пока нет.</h1>
                <p>Найдите интересных людей</p>
            </section>
        <?php endif; ?>
    </main>
    <script>
    (function () {
        var clipsByCategory = <?php echo json_encode($clipsByCategory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var clips = clipsByCategory.recommended || [];
        var activeCategory = 'recommended';
        var categoryTabs = document.querySelectorAll('[data-clips-tab]');

        function setActiveCategory(nextCategory) {
            activeCategory = nextCategory;
            clips = clipsByCategory[nextCategory] || [];
            currentIndex = 0;
            categoryTabs.forEach(function (tab) {
                var isActive = tab.getAttribute('data-clips-tab') === nextCategory;
                tab.classList.toggle('is-active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            toggleEmptyState();
            if (clips.length) {
                renderClip(currentIndex);
            } else if (video) {
                video.pause();
                video.removeAttribute('src');
            }
        }

        categoryTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var nextCategory = tab.getAttribute('data-clips-tab') || 'recommended';
                if (activeCategory === nextCategory) {
                    return;
                }
                setActiveCategory(nextCategory);
            });
        });

        

        var currentIndex = 0;
        var lastNavigationAt = 0;
        var shell = document.querySelector('.clips-shell');
        if (!shell) {
            return;
        }
        var emptyState = document.querySelector('[data-clips-empty-state]');
        var infoBlock = document.querySelector('.clips-info');
        var videoWrap = document.querySelector('.clips-video-wrap');
        var actionsBlock = document.querySelector('.clips-actions');
        var navBlock = document.querySelector('.clips-nav');
        var video = document.querySelector('[data-clips-video]');
        var avatar = document.querySelector('[data-clips-avatar]');
        var authorLink = document.querySelector('[data-clips-author-link]');
        var login = document.querySelector('[data-clips-login]');
        var time = document.querySelector('[data-clips-time]');
        var description = document.querySelector('[data-clips-description]');
        var hashtags = document.querySelector('[data-clips-hashtags]');
        var clipsMenu = document.querySelector('[data-clips-menu]');
        var clipsMenuToggle = document.querySelector('[data-clips-menu-toggle]');
        var clipsMenuOwn = document.querySelector('[data-clips-menu-own]');
        var clipsMenuForeign = document.querySelector('[data-clips-menu-foreign]');
        var clipsMenuAccount = document.querySelector('[data-clips-menu-account]');
        var clipsBlockLabel = document.querySelector('[data-clips-block-label]');
        var clipsStatsButtons = document.querySelectorAll('[data-clips-stats-btn]');
        var statsModal = document.querySelector('[data-clips-stats-modal]');
        var statsContent = document.querySelector('[data-clips-stats-content]');
        var commentLinks = document.querySelectorAll('[data-clips-comment-link]');
        var commentsModal = document.querySelector('[data-clips-comments-modal]');
        var commentsList = document.querySelector('[data-clips-comments-list]');
        var commentsForm = document.querySelector('[data-clips-comments-form]');
        var isCommentsSubmitting = false;
        var commentsCache = [];
        var activeCommentIndex = -1;
        var counts = {
            likes: document.querySelector('[data-clips-count="likes"]'),
            comments: document.querySelector('[data-clips-count="comments"]'),
            reposts: document.querySelector('[data-clips-count="reposts"]'),
            saves: document.querySelector('[data-clips-count="saves"]')
        };
        var buttons = {
            like: document.querySelector('[data-clips-action="toggle_like"]'),
            repost: document.querySelector('[data-clips-action="add_repost"]'),
            save: document.querySelector('[data-clips-action="toggle_save"]')
        };
        var commentActionsModal = document.querySelector('[data-clips-comment-actions-modal]');
        var commentActionsDialog = document.querySelector('[data-clips-comment-actions-dialog]');
        var followButton = document.querySelector('[data-clips-follow-btn]');
        var followRelationMap = <?php echo json_encode($relatedUserIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || {};

        function getEmptyStateMarkup() {
            if (activeCategory === 'authored') {
                return '<h1>Видео пока нет.</h1>';
            }

            return '<h1>Видео пока нет.</h1><p>Найдите интересных людей</p>';
        }

        function toggleEmptyState() {
            var hasClips = clips.length > 0;

            if (emptyState) {
                emptyState.classList.toggle('is-hidden', hasClips);
                emptyState.innerHTML = hasClips ? '' : getEmptyStateMarkup();
            }

            var fallbackEmptyState = document.querySelector('[data-clips-empty-fallback]');
            if (fallbackEmptyState) {
                fallbackEmptyState.innerHTML = getEmptyStateMarkup();
            }

            [infoBlock, videoWrap, actionsBlock, navBlock].forEach(function (node) {
                if (!node) {
                    return;
                }
                node.classList.toggle('is-hidden', !hasClips);
            });
        }

        function formatCount(value) {
            return String(Number(value || 0));
        }

        function parseDate(value) {
            return new Date(String(value).replace(' ', 'T'));
        }

        function pad(value) {
            return String(value).padStart(2, '0');
        }

        function formatClipTime(value) {
            var date = parseDate(value);
            var now = new Date();
            var diff = now.getTime() - date.getTime();
            var minute = 60 * 1000;
            var hour = 60 * minute;
            var day = 24 * hour;

            if (!Number.isFinite(date.getTime())) {
                return '';
            }

            if (diff >= 0 && diff < hour) {
                return Math.max(1, Math.floor(diff / minute)) + ' мин';
            }

            if (diff >= 0 && diff < day) {
                return Math.floor(diff / hour) + ' ч';
            }

            var yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
            var clipDay = new Date(date.getFullYear(), date.getMonth(), date.getDate());

            if (clipDay.getTime() === yesterday.getTime()) {
                return '1 день назад';
            }

            return pad(date.getDate()) + '.' + pad(date.getMonth() + 1) + '.' + date.getFullYear() + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
        }

        function animateButton(button, isActive) {
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

        function renderClip(index) {
            var clip = clips[index];
            var isOwnClip = !!clip.isOwn;

            shell.classList.remove('is-visible');
            window.setTimeout(function () {
                video.pause();
                video.src = clip.videoUrl;
                video.load();
                video.play().catch(function () {});

                authorLink.href = clip.author.profileUrl;
                login.textContent = clip.author.login;
                time.textContent = formatClipTime(clip.createdAt);
                description.textContent = clip.description || '';

                if (followButton) {
                    var canFollowClipAuthor = !!clip.author.canFollow && !followRelationMap[String(clip.author.id)];
                    followButton.classList.toggle('is-hidden', !canFollowClipAuthor);
                    followButton.setAttribute('data-follow-user-id', String(clip.author.id));
                }

                if (clip.author.avatar) {
                    avatar.style.backgroundImage = "url('" + clip.author.avatar.replace(/'/g, "\\'") + "')";
                    avatar.textContent = '';
                    avatar.classList.add('has-avatar');
                } else {
                    avatar.style.backgroundImage = '';
                    avatar.textContent = (clip.author.login || 'S').slice(0, 1);
                    avatar.classList.remove('has-avatar');
                }

                hashtags.innerHTML = '';
                clip.hashtags.forEach(function (tag) {
                    var item = document.createElement('span');
                    item.textContent = tag;
                    hashtags.appendChild(item);
                });

                counts.likes.textContent = formatCount(clip.counts.likes);
                counts.comments.textContent = formatCount(clip.counts.comments);
                counts.reposts.textContent = formatCount(clip.counts.reposts);
                counts.saves.textContent = formatCount(clip.counts.saves);

                if (buttons.like) {
                    buttons.like.classList.toggle('is-liked', !!clip.state.liked);
                }
                if (buttons.repost) {
                    buttons.repost.classList.toggle('is-reposted', !!clip.state.reposted);
                }
                if (buttons.save) {
                    buttons.save.classList.toggle('is-saved', !!clip.state.saved);
                    buttons.save.setAttribute('data-save-post-id', String(clip.id));
                }

                if (clipsMenuAccount) {
                    clipsMenuAccount.href = clip.author.profileUrl;
                }
                if (clipsBlockLabel) {
                    clipsBlockLabel.textContent = 'Добавить ' + clip.author.login + ' в чёрный список';
                }
                clipsStatsButtons.forEach(function (button) {
                    button.setAttribute('data-post-id', String(clip.id));
                });
                if (clipsMenuOwn) {
                    clipsMenuOwn.classList.toggle('is-hidden', !isOwnClip);
                }
                if (clipsMenuForeign) {
                    clipsMenuForeign.classList.toggle('is-hidden', isOwnClip);
                }
                if (clipsMenu) {
                    clipsMenu.classList.remove('is-open');
                }

                shell.classList.add('is-visible');
            }, 110);
        }

        function applyRelationMap(nextMap) {
            followRelationMap = nextMap && typeof nextMap === 'object' ? nextMap : {};
            Object.keys(clipsByCategory).forEach(function (categoryKey) {
                (clipsByCategory[categoryKey] || []).forEach(function (clipItem) {
                    var authorId = String(clipItem.author.id);
                    clipItem.author.canFollow = !clipItem.isOwn && !followRelationMap[authorId];
                });
            });
        }

        function syncFollowRelations() {
            var formData = new FormData();
            formData.append('action', 'get_follow_relations');
            return fetch('clips.php', { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (!payload || !payload.ok) {
                        return;
                    }
                    applyRelationMap(payload.relation_map);
                    if (clips.length) {
                        renderClip(currentIndex);
                    }
                });
        }

        function goToClip(nextIndex) {
            if (nextIndex < 0 || nextIndex >= clips.length || nextIndex === currentIndex) {
                return;
            }

            currentIndex = nextIndex;
            renderClip(currentIndex);
        }

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (char) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]);
            });
        }

        function formatCommentDate(value) {
            var date = parseDate(value);
            if (!Number.isFinite(date.getTime())) {
                return '';
            }
            return pad(date.getDate()) + '.' + pad(date.getMonth() + 1) + '.' + String(date.getFullYear()).slice(-2);
        }

        function renderComments(items) {
            if (!commentsList) { return; }
            commentsCache = Array.isArray(items) ? items : [];
            if (!items.length) {
                commentsList.innerHTML = '<p class="comments-empty">Комментариев пока нет.</p>';
                return;
            }
            commentsList.innerHTML = items.map(function (comment, index) {
                var avatar = comment.avatar ? '<span class="clips-comment-avatar has-avatar" style="background-image:url(\'' + escapeHtml(comment.avatar) + '\')"></span>' : '<span class="clips-comment-avatar">' + escapeHtml((comment.login || 'S').slice(0,1)) + '</span>';
                return '<article class="clips-comment-item" data-comment-index="' + index + '">' +
                    '<div class="clips-comment-top">' +
                        '<div class="clips-comment-meta-left">' + avatar + '<div><a href="' + escapeHtml(comment.profile_url || '#') + '" class="comment-author"><strong>' + escapeHtml(comment.login || '') + '</strong></a><span class="clips-comment-date">' + escapeHtml(formatCommentDate(comment.created_at || '')) + '</span></div></div>' +
                        '<div class="clips-comment-meta-right"><button type="button" class="clips-comment-menu-btn" aria-label="Меню">•••</button><button type="button" class="clips-comment-like-btn" aria-label="Лайк комментария"><img src="icon/dark theme/like.png" alt=""></button><span class="clips-comment-like-count">' + escapeHtml(comment.likes_count || 0) + '</span></div>' +
                    '</div>' +
                    '<p>' + escapeHtml(comment.text || '') + '</p>' +
                    '<button type="button" class="clips-comment-replies-toggle" data-open="0">Смотреть ответы (' + escapeHtml(comment.replies_count || 1) + ')</button>' +
                    '<div class="clips-comment-replies" hidden><p class="clips-comment-reply">Ответы пока недоступны.</p></div>' +
                '</article>';
            }).join('');
        }


        function openCommentActionsModal(commentIndex) {
            if (!commentActionsModal || !clips.length) {
                return;
            }
            activeCommentIndex = commentIndex;
            commentActionsModal.classList.add('is-open');
        }

        function closeCommentActionsModal() {
            if (!commentActionsModal) {
                return;
            }
            activeCommentIndex = -1;
            commentActionsModal.classList.remove('is-open');
        }


        function formatStatsDate(value) {
            var parts = String(value || '').split('-');
            if (parts.length !== 3) {
                return value || '';
            }
            return parts[2] + '.' + parts[1] + '.' + parts[0];
        }

        function renderStatsLoading() {
            if (!statsContent) {
                return;
            }
            statsContent.innerHTML = '<p class="clips-stats-status">Загрузка...</p>';
        }

        function renderStatsError(message) {
            if (!statsContent) {
                return;
            }
            statsContent.innerHTML = '<p class="clips-stats-status is-error">' + escapeHtml(message || 'Не удалось загрузить статистику.') + '</p>';
        }

        function renderStats(stats) {
            if (!statsContent) {
                return;
            }
            var daily = Array.isArray(stats.daily) ? stats.daily : [];
            var dailyRows = daily.length ? daily.map(function (day) {
                return '<tr><td>' + escapeHtml(formatStatsDate(day.date)) + '</td><td>' + escapeHtml(day.likes || 0) + '</td><td>' + escapeHtml(day.comments || 0) + '</td><td>' + escapeHtml(day.reposts || 0) + '</td><td>' + escapeHtml(day.saves || 0) + '</td></tr>';
            }).join('') : '<tr><td colspan="5">Данных по дням пока нет.</td></tr>';
            var topPost = stats.top_post || {};
            var topPostMedia = topPost.media_url ? '<div class="clips-stats-top-thumb">' + (topPost.media_type === 'video' ? '<video src="' + escapeHtml(topPost.media_url) + '" muted playsinline></video>' : '<img src="' + escapeHtml(topPost.media_url) + '" alt="">') + '</div>' : '<div class="clips-stats-top-thumb is-empty">#</div>';
            var topPostMarkup = topPost.id
                ? '<div class="clips-stats-top-post">' + topPostMedia + '<div><strong>ID публикации: ' + escapeHtml(topPost.id) + '</strong><span>Всего взаимодействий: ' + escapeHtml(topPost.interactions_total || 0) + '</span></div></div>'
                : '<p class="clips-stats-status">Пока нет публикаций для сравнения.</p>';

            statsContent.innerHTML =
                '<p class="clips-stats-period">Период: всё время</p>' +
                '<div class="clips-stats-cards">' +
                    '<section class="clips-stats-card"><span>Всего лайков за период</span><strong>' + escapeHtml(stats.likes_total || 0) + '</strong></section>' +
                    '<section class="clips-stats-card"><span>Всего комментариев</span><strong>' + escapeHtml(stats.comments_total || 0) + '</strong></section>' +
                    '<section class="clips-stats-card"><span>Всего репостов</span><strong>' + escapeHtml(stats.reposts_total || 0) + '</strong></section>' +
                    '<section class="clips-stats-card"><span>Всего добавлений в избранное</span><strong>' + escapeHtml(stats.saves_total || 0) + '</strong></section>' +
                '</div>' +
                '<section class="clips-stats-section"><h4>Динамика по дням</h4><div class="clips-stats-table-wrap"><table class="clips-stats-table"><thead><tr><th>Дата</th><th>Лайки</th><th>Комментарии</th><th>Репосты</th><th>Избранное</th></tr></thead><tbody>' + dailyRows + '</tbody></table></div></section>' +
                '<section class="clips-stats-section"><h4>Пост с наибольшим количеством взаимодействий</h4>' + topPostMarkup + '</section>';
        }

        function closeStatsModal() {
            if (!statsModal) {
                return;
            }
            statsModal.classList.remove('is-open');
            statsModal.setAttribute('aria-hidden', 'true');
        }

        function openStatsModal(postId) {
            if (!statsModal || !statsContent || !postId) {
                return;
            }
            statsModal.classList.add('is-open');
            statsModal.setAttribute('aria-hidden', 'false');
            renderStatsLoading();
            var formData = new URLSearchParams();
            formData.set('action', 'get_clip_post_stats');
            formData.set('post_id', String(postId));
            fetch('clips.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json'
                },
                body: formData.toString()
            }).then(function (response) {
                if (!response.ok) {
                    return response.json().catch(function () { return {}; }).then(function (payload) {
                        throw new Error(payload.error || 'Не удалось загрузить статистику.');
                    });
                }
                return response.json();
            }).then(function (payload) {
                if (!payload || !payload.ok) {
                    renderStatsError('Не удалось загрузить статистику.');
                    return;
                }
                renderStats(payload.stats || {});
            }).catch(function (error) {
                renderStatsError(error && error.message ? error.message : 'Не удалось загрузить статистику.');
            });
        }

        commentsList.addEventListener('click', function (event) {
            var likeBtn = event.target.closest('.clips-comment-like-btn');
            if (likeBtn) {
                var count = likeBtn.parentElement.querySelector('.clips-comment-like-count');
                var liked = likeBtn.classList.toggle('is-liked');
                var value = Number(count.textContent || 0);
                count.textContent = String(Math.max(0, value + (liked ? 1 : -1)));
                return;
            }
            var menuBtn = event.target.closest('.clips-comment-menu-btn');
            if (menuBtn) {
                var container = menuBtn.closest('.clips-comment-item');
                var commentIndex = Number(container ? container.getAttribute('data-comment-index') : -1);
                if (commentIndex >= 0) {
                    openCommentActionsModal(commentIndex);
                }
                return;
            }
            var toggle = event.target.closest('.clips-comment-replies-toggle');
            if (toggle) {
                var replies = toggle.nextElementSibling;
                var isOpen = toggle.getAttribute('data-open') === '1';
                toggle.setAttribute('data-open', isOpen ? '0' : '1');
                toggle.textContent = isOpen ? 'Смотреть ответы (1)' : 'Скрыть ответы';
                if (replies) { replies.hidden = isOpen; }
            }
        });

        function openCommentsModal(triggerButton) {
            if (!commentsModal || !clips.length) { return; }
            var clip = clips[currentIndex];
            commentsModal.classList.add('is-open');
            if (shell) {
                shell.classList.add('comments-open');
            }
            commentsList.innerHTML = '<p class="comments-empty">Загрузка...</p>';
            sendClipAction('get_comments').then(function (data) {
                if (!data || !data.ok) {
                    commentsList.innerHTML = '<p class="comments-empty">Не удалось загрузить комментарии.</p>';
                    return;
                }

                renderComments(data.comments || []);
            }).catch(function () {
                commentsList.innerHTML = '<p class="comments-empty">Не удалось загрузить комментарии.</p>';
            });
        }

        function closeCommentsModal() {
            if (commentsModal) { commentsModal.classList.remove('is-open'); }
            closeCommentActionsModal();
            if (shell) {
                shell.classList.remove('comments-open');
            }
        }

        commentLinks.forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                if (commentsModal && commentsModal.classList.contains('is-open')) {
                    closeCommentsModal();
                    return;
                }
                openCommentsModal(link);
            });
        });

        var commentsClose = document.querySelector('[data-clips-comments-close]');
        if (commentsClose) { commentsClose.addEventListener('click', closeCommentsModal); }
        if (commentActionsModal) {
            commentActionsModal.addEventListener('click', function (event) {
                if (event.target.hasAttribute('data-clips-comment-actions-close')) {
                    closeCommentActionsModal();
                }
            });
        }
        if (commentActionsDialog) {
            commentActionsDialog.querySelectorAll('[data-clips-comment-action]').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (!clips.length) {
                        return;
                    }
                    var action = button.getAttribute('data-clips-comment-action');
                    var clip = clips[currentIndex];
                    var comment = commentsCache[activeCommentIndex] || null;

                    if (action === 'report_post' && window.SnapixReportModal) {
                        window.SnapixReportModal.open({
                            login: (clip.author && clip.author.login) ? clip.author.login : 'user',
                            userId: (clip.author && clip.author.id) ? clip.author.id : 0,
                            onSubmit: function (reason, api) {
                                sendClipAction('report_post', { report_reason: reason }).then(function (reportData) {
                                    if (!reportData || !reportData.ok) { return; }
                                    api.showSuccess();
                                });
                            }
                        });
                        closeCommentActionsModal();
                        return;
                    }

                    sendClipAction(action).then(function (data) {
                        if (!data || !data.ok) {
                            return;
                        }
                        closeCommentActionsModal();
                    }).catch(function () {});
                });
            });
        }

        if (commentsForm) {
            commentsForm.addEventListener('submit', function (event) {
                event.preventDefault();

                if (isCommentsSubmitting) {
                    return;
                }

                var textarea = commentsForm.querySelector('textarea[name="comment_text"]');
                var submitButton = commentsForm.querySelector('button[type="submit"]');
                var textValue = textarea ? textarea.value.trim() : '';

                if (!textarea || !textValue) {
                    return;
                }

                isCommentsSubmitting = true;
                if (submitButton) {
                    submitButton.disabled = true;
                }

                var clip = clips[currentIndex];
                var formData = new URLSearchParams();
                formData.set('action', 'add_comment');
                formData.set('post_id', clip.id);
                formData.set('comment_text', textValue);
                fetch('clips.php', { method:'POST', credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','Accept':'application/json'}, body: formData.toString() })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data || !data.ok) { return; }
                        textarea.value = '';
                        counts.comments.textContent = formatCount(data.comments_count);
                        clips[currentIndex].counts.comments = Number(data.comments_count || 0);
                        openCommentsModal();
                    })
                    .catch(function () {})
                    .finally(function () {
                        isCommentsSubmitting = false;
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                    });
            });
        }

        function navigateClip(direction) {
            var now = Date.now();

            if (now - lastNavigationAt < 420) {
                return;
            }

            if (direction < 0 && currentIndex <= 0) {
                return;
            }

            if (direction > 0 && currentIndex >= clips.length - 1) {
                return;
            }

            lastNavigationAt = now;
            goToClip(currentIndex + direction);
        }

        function sendClipAction(action, payload) {
            if (!clips.length) {
                return Promise.resolve({ ok: false });
            }
            var clip = clips[currentIndex];
            var formData = new URLSearchParams();
            formData.set('action', action);
            formData.set('post_id', clip.id);
            if (payload && typeof payload === 'object') {
                Object.keys(payload).forEach(function (key) {
                    formData.set(key, payload[key]);
                });
            }

            return fetch('clips.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json'
                },
                body: formData.toString()
            }).then(function (response) {
                if (!response.ok) {
                    return response.text().then(function () {
                        return { ok: false };
                    });
                }

                return response.json().catch(function () {
                    return { ok: false };
                });
            });
        }

        function removeCurrentClip() {
            clips.splice(currentIndex, 1);

            if (!clips.length) {
                window.location.reload();
                return;
            }

            if (currentIndex >= clips.length) {
                currentIndex = clips.length - 1;
            }

            renderClip(currentIndex);
        }


        function updateSavedStateEverywhere(postId, isSaved, savesCount) {
            var selectorPostId = String(postId || '');
            document.querySelectorAll('[data-save-post-id="' + selectorPostId + '"]').forEach(function (node) {
                node.classList.toggle('is-saved', !!isSaved);
            });
            if (typeof savesCount !== 'undefined' && counts.saves) {
                counts.saves.textContent = formatCount(savesCount);
            }
        }

        document.querySelector('[data-clips-prev]').addEventListener('click', function () {
            navigateClip(-1);
        });

        document.querySelector('[data-clips-next]').addEventListener('click', function () {
            navigateClip(1);
        });

        document.querySelectorAll('[data-clips-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-clips-action');

                sendClipAction(action)
                    .then(function (data) {
                        var clip = clips[currentIndex];

                        if (!data || !data.ok) {
                            return;
                        }

                        clip.counts.likes = data.likes_count;
                        clip.counts.saves = data.saves_count;
                        clip.counts.reposts = data.reposts_count;
                        clip.state.liked = !!data.liked;
                        clip.state.saved = !!data.saved;
                        clip.state.reposted = !!data.reposted;

                        counts.likes.textContent = formatCount(data.likes_count);
                        updateSavedStateEverywhere(clip.id, !!data.saved, data.saves_count);
                        counts.reposts.textContent = formatCount(data.reposts_count);

                        buttons.like.classList.toggle('is-liked', !!data.liked);
                        buttons.repost.classList.toggle('is-reposted', !!data.reposted);
                        buttons.save.classList.toggle('is-saved', !!data.saved);

                        if (action === 'toggle_like') {
                            animateButton(buttons.like, !!data.liked);
                        }
                        if (action === 'toggle_save') {
                            animateButton(buttons.save, !!data.saved);
                        }
                        if (action === 'add_repost') {
                            animateButton(buttons.repost, !!data.reposted);
                        }
                    })
                    .catch(function () {});
            });
        });

        if (clipsMenuToggle && clipsMenu) {
            clipsMenuToggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var isOpen = clipsMenu.classList.toggle('is-open');
                clipsMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        }

        document.querySelectorAll('[data-clips-menu-action]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var action = button.getAttribute('data-clips-menu-action');
                var clip = clips[currentIndex];

                if (action === 'open_stats') {
                    openStatsModal(button.getAttribute('data-post-id') || (clip ? clip.id : ''));
                    if (clipsMenu) {
                        clipsMenu.classList.remove('is-open');
                    }
                    if (clipsMenuToggle) {
                        clipsMenuToggle.setAttribute('aria-expanded', 'false');
                    }
                    return;
                }

                if (action === 'report_post' && window.SnapixReportModal) {
                    window.SnapixReportModal.open({
                        login: (clip.author && clip.author.login) ? clip.author.login : 'user',
                        userId: (clip.author && clip.author.id) ? clip.author.id : 0,
                        onSubmit: function (reason, api) {
                            sendClipAction('report_post', { report_reason: reason }).then(function (reportData) {
                                if (!reportData || !reportData.ok) { return; }
                                api.showSuccess();
                            });
                        }
                    });
                    if (clipsMenu) {
                        clipsMenu.classList.remove('is-open');
                    }
                    if (clipsMenuToggle) {
                        clipsMenuToggle.setAttribute('aria-expanded', 'false');
                    }
                    return;
                }

                sendClipAction(action)
                    .then(function (data) {
                        if (!data || !data.ok) {
                            return;
                        }

                        if (clipsMenu) {
                            clipsMenu.classList.remove('is-open');
                        }
                        if (clipsMenuToggle) {
                            clipsMenuToggle.setAttribute('aria-expanded', 'false');
                        }

                        if (action === 'hide_post' || action === 'delete_post') {
                            removeCurrentClip();
                        }

                        if (action === 'block_user') {
                            clips = clips.filter(function (item) {
                                return item.author.id !== clip.author.id;
                            });

                            if (!clips.length) {
                                window.location.reload();
                                return;
                            }

                            if (currentIndex >= clips.length) {
                                currentIndex = clips.length - 1;
                            }

                            renderClip(currentIndex);
                        }

                    })
                    .catch(function () {});
            });
        });

        document.querySelectorAll('[data-clips-stats-close]').forEach(function (button) {
            button.addEventListener('click', closeStatsModal);
        });


        document.addEventListener('click', function (event) {
            var wrap = event.target.closest('.clips-menu-wrap');

            if (!wrap && clipsMenu) {
                clipsMenu.classList.remove('is-open');
                if (clipsMenuToggle) {
                    clipsMenuToggle.setAttribute('aria-expanded', 'false');
                }
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && statsModal && statsModal.classList.contains('is-open')) {
                closeStatsModal();
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                navigateClip(-1);
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                navigateClip(1);
            }
        });

        document.addEventListener('wheel', function (event) {
            if (Math.abs(event.deltaY) < 20) {
                return;
            }

            event.preventDefault();
            navigateClip(event.deltaY > 0 ? 1 : -1);
        }, { passive: false });

        if (followButton) {
            followButton.addEventListener('click', function () {
                var clip = clips[currentIndex];
                if (!clip || !clip.author || !clip.author.id) {
                    return;
                }

                var formData = new FormData();
                formData.append('action', 'toggle_follow_user');
                formData.append('target_user_id', String(clip.author.id));
                fetch('clips.php', { method: 'POST', body: formData, credentials: 'same-origin' })
                    .then(function (response) { return response.json(); })
                    .then(function (payload) {
                        if (!payload || !payload.ok) {
                            return;
                        }
                        applyRelationMap(payload.relation_map || {});
                        if (clips.length) {
                            renderClip(currentIndex);
                        }
                        window.localStorage.setItem('snapix_follow_sync', String(Date.now()));
                    })
                    .catch(function () {});
            });

            window.addEventListener('focus', function () {
                syncFollowRelations().catch(function () {});
            });
            window.addEventListener('storage', function (event) {
                if (event && event.key === 'snapix_follow_sync') {
                    syncFollowRelations().catch(function () {});
                }
            });
        }

        toggleEmptyState();
        if (clips.length) {
            renderClip(currentIndex);
        }
    })();
    </script>
</body>
</html>
