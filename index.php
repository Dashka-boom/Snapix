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

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = :id AND status = 'pending'");
        $pendingStmt->execute(['id' => $user['id']]);
        $pendingRequestsCount = (int) $pendingStmt->fetchColumn();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $action = $_POST['action'] ?? '';
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
                $insertCommentStmt = $pdo->prepare('
                    INSERT INTO comments (post_id, user_id, comment_text)
                    VALUES (:post_id, :user_id, :comment_text)
                ');
                $insertCommentStmt->execute([
                    'post_id' => $postId,
                    'user_id' => $user['id'],
                    'comment_text' => mb_substr($commentText, 0, 1000),
                ]);
            }
        }
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
              AND saved_posts.user_id = ' . (int) ($user['id'] ?? 0) . '
        ) AS is_saved
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.is_deleted = 0
    ORDER BY posts.created_at DESC
    LIMIT 40
');
$feedPosts = $feedStmt->fetchAll();

$commentMap = [];

if ($feedPosts) {
    $postIds = array_map(static fn ($post): int => (int) $post['id'], $feedPosts);
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

        if (count($commentMap[$currentPostId]) < 3) {
            array_unshift($commentMap[$currentPostId], $comment);
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
            <div class="section-heading">
                <h1>Лента публикаций</h1>
            </div>

            <?php if ($feedPosts): ?>
                <div class="feed-list">
                    <?php foreach ($feedPosts as $post): ?>
                        <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                        <?php $authorProfileUrl = buildProfileUrl((int) $post['user_id'], $user ? (int) $user['id'] : null); ?>
                        <article class="feed-card card-surface">
                            <header class="feed-card-header">
                                <a href="<?php echo htmlspecialchars($authorProfileUrl); ?>" class="feed-author-avatar-link" aria-label="РћС‚РєСЂС‹С‚СЊ РїСЂРѕС„РёР»СЊ <?php echo htmlspecialchars($post['login']); ?>">
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
                                        <span><?php echo (int) $post['likes_count']; ?> лайков</span>
                                        <span><?php echo (int) $post['comments_count']; ?> комментариев</span>
                                    </div>

                                    <?php if ($user): ?>
                                        <div class="feed-card-buttons">
                                            <form method="post" class="inline-action-form">
                                                <input type="hidden" name="action" value="toggle_like">
                                                <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                <button type="submit" class="feed-action-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>">
                                                    <?php echo (int) $post['is_liked'] > 0 ? 'Убрать лайк' : 'Лайк'; ?>
                                                </button>
                                            </form>

                                            <form method="post" class="inline-action-form">
                                                <input type="hidden" name="action" value="toggle_save">
                                                <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                <button type="submit" class="feed-action-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>">
                                                    <?php echo (int) $post['is_saved'] > 0 ? 'В избранном' : 'В избранное'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($post['caption'])): ?>
                                    <div class="feed-card-caption">
                                        <?php echo nl2br(htmlspecialchars($post['caption'])); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="feed-card-comments">
                                    <?php if ($postComments): ?>
                                        <?php foreach ($postComments as $comment): ?>
                                            <?php $commentProfileUrl = buildProfileUrl((int) $comment['user_id'], $user ? (int) $user['id'] : null); ?>
                                            <div class="comment-item">
                                                <a href="<?php echo htmlspecialchars($commentProfileUrl); ?>" class="comment-author">
                                                    <strong><?php echo htmlspecialchars($comment['login']); ?></strong>
                                                </a>
                                                <p><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="comments-empty">Пока нет комментариев.</p>
                                    <?php endif; ?>
                                </div>

                                <?php if ($user): ?>
                                    <form method="post" class="comment-form">
                                        <input type="hidden" name="action" value="add_comment">
                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                        <textarea name="comment_text" rows="2" maxlength="1000" placeholder="Напишите комментарий..."></textarea>
                                        <button type="submit" class="primary-link">Комментировать</button>
                                    </form>
                                <?php else: ?>
                                    <p class="comments-login-hint">Чтобы лайкать и комментировать, войдите в аккаунт.</p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Лента публикаций пустая. Добавьте первую публикацию.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>

