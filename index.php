<?php
session_start();
require './config/config.php';

function buildProfileUrl(int $profileUserId, ?int $currentUserId): string
{
    if ($currentUserId !== null && $profileUserId === $currentUserId) {
        return 'profile.php';
    }

    return 'user.php?id=' . $profileUserId;
}

$user = null;
$pendingRequestsCount = 0;
$notifications = [];

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = :id AND status = 'pending'");
        $pendingStmt->execute(['id' => $user['id']]);
        $pendingRequestsCount = (int) $pendingStmt->fetchColumn();

        $notificationsStmt = $pdo->prepare('SELECT id, title, message, created_at FROM user_notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5');
        $notificationsStmt->execute(['user_id' => $user['id']]);
        $notifications = $notificationsStmt->fetchAll();
    }
}

$reportReasonsStmt = $pdo->query('SELECT id, label FROM moderation_reasons ORDER BY id ASC');
$reportReasons = $reportReasonsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $action = $_POST['action'] ?? '';
    $commentsPostId = 0;

    if ($action === 'mark_notifications_read') {
        $markReadStmt = $pdo->prepare('UPDATE user_notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
        $markReadStmt->execute(['user_id' => $user['id']]);

        header('Location: index.php');
        exit;
    }

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
            $repostId = $repostExistsStmt->fetchColumn();

            if (!$repostId) {
                $insertRepostStmt = $pdo->prepare('INSERT INTO reposts (user_id, post_id) VALUES (:user_id, :post_id)');
                $insertRepostStmt->execute([
                    'user_id' => $user['id'],
                    'post_id' => $postId,
                ]);
            }
        }
    }

    if ($commentsPostId > 0) {
        header('Location: index.php?comments_post=' . $commentsPostId);
        exit;
    }

    header('Location: index.php');
    exit;
}

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
        ) AS is_reposted
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.is_deleted = 0
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
                    <a href="connections.php?view=requests" class="notification-bell" aria-label="Открыть заявки">
                        <span class="notification-bell-icon">&#128276;</span>
                        <?php if ($pendingRequestsCount > 0): ?>
                            <span class="notification-badge"><?php echo $pendingRequestsCount; ?></span>
                        <?php endif; ?>
                    </a>
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
            <?php if ($user && $notifications): ?>
                <section class="card-surface" style="padding: 16px; margin-bottom: 16px;">
                    <div style="display:flex; justify-content: space-between; align-items: center; gap: 12px;">
                        <h2 style="margin: 0;">Уведомления</h2>
                        <form method="post">
                            <input type="hidden" name="action" value="mark_notifications_read">
                            <button type="submit" class="secondary-link">Отметить как прочитанные</button>
                        </form>
                    </div>
                    <div style="display:grid; gap: 10px; margin-top: 12px;">
                        <?php foreach ($notifications as $notification): ?>
                            <article style="background: #f8fafc; border:1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
                                <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                <p style="margin: 8px 0 0;"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <div class="section-heading">
                <h1>Лента публикаций</h1>
            </div>

            <?php if ($feedPosts): ?>
                <div class="feed-list">
                    <?php foreach ($feedPosts as $post): ?>
                        <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                        <?php $postReposters = $repostMap[(int) $post['id']] ?? []; ?>
                        <?php $authorProfileUrl = buildProfileUrl((int) $post['user_id'], $user ? (int) $user['id'] : null); ?>
                        <article class="feed-card card-surface">
                            <header class="feed-card-header">
                                <a href="<?php echo htmlspecialchars($authorProfileUrl); ?>" class="feed-author-avatar-link" aria-label="Открыть профиль <?php echo htmlspecialchars($post['login']); ?>">
                                    <div class="feed-author-avatar"<?php if (!empty($post['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($post['avatar']); ?>');"<?php endif; ?>>
                                        <?php if (empty($post['avatar'])): ?>
                                            <?php echo htmlspecialchars(mb_substr($post['login'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <div>
                                    <a href="<?php echo htmlspecialchars($authorProfileUrl); ?>" class="feed-author-name">
                                        <strong><?php echo htmlspecialchars($post['login']); ?></strong>
                                    </a>
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
                                    <div class="feed-card-stats">
                                        <span>Действия с публикацией</span>
                                    </div>

                                    <?php if ($user): ?>
                                        <div class="feed-card-buttons">
                                            <div class="feed-action-item">
                                                <form method="post" class="inline-action-form">
                                                    <input type="hidden" name="action" value="toggle_like">
                                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                    <button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><span aria-hidden="true">&#9829;</span></button>
                                                </form>
                                                <span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span>
                                            </div>

                                            <div class="feed-action-item">
                                                <button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><span aria-hidden="true">&#128172;</span></button>
                                                <span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span>
                                            </div>

                                            <div class="feed-action-item">
                                                <form method="post" class="inline-action-form">
                                                    <input type="hidden" name="action" value="toggle_save">
                                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                    <button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><span aria-hidden="true">&#128278;</span></button>
                                                </form>
                                                <span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span>
                                            </div>

                                            <div class="feed-action-item">
                                                <form method="post" class="inline-action-form">
                                                    <input type="hidden" name="action" value="add_repost">
                                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                    <button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><span aria-hidden="true">&#128257;</span></button>
                                                </form>
                                                <span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="feed-card-buttons">
                                            <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для лайка"><span aria-hidden="true">&#9829;</span></a><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                            <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><span aria-hidden="true">&#128172;</span></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                            <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для избранного"><span aria-hidden="true">&#128278;</span></a><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                            <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для репоста"><span aria-hidden="true">&#128257;</span></a><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($post['caption'])): ?>
                                    <div class="feed-card-caption">
                                        <?php echo nl2br(htmlspecialchars($post['caption'])); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($postReposters): ?>
                                    <p class="feed-reposts-note">Репостнули: <?php echo htmlspecialchars(implode(', ', $postReposters)); ?></p>
                                <?php endif; ?>

                                <div class="comments-modal<?php echo (isset($_GET['comments_post']) && (int) $_GET['comments_post'] === (int) $post['id']) ? ' is-open' : ''; ?>" id="comments-modal-<?php echo (int) $post['id']; ?>">
                                    <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-<?php echo (int) $post['id']; ?>"></div>
                                    <div class="comments-modal-dialog">
                                        <div class="comments-modal-header">
                                            <h3>Комментарии</h3>
                                            <button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-<?php echo (int) $post['id']; ?>" aria-label="Закрыть">&times;</button>
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
            <?php else: ?>
                <p class="empty-state">Лента публикаций пустая. Добавьте первую публикацию.</p>
            <?php endif; ?>
        </section>
    </main>
<script>
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
</script>
</body>
</html>
