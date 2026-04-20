<?php
session_start();
require './config/config.php';

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
    }
}

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

    $targetUserStmt = $pdo->prepare('SELECT id, is_private FROM users WHERE id = :id');
    $targetUserStmt->execute(['id' => $targetUserId]);
    $targetUser = $targetUserStmt->fetch();

    if ($targetUser) {
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
            SELECT posts.*, post_media.media_url, post_media.media_type
            FROM posts
            LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
            WHERE posts.user_id = :id AND posts.is_deleted = 0
            ORDER BY posts.created_at DESC
        ');
        $stmt->execute(['id' => $profileUser['id']]);
        $posts = $stmt->fetchAll();
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
<body data-page="user-profile">
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">Snapix</a>
            <input type="text" class="search" placeholder="Поиск">
            <div class="menu">
                <a href="#">Reels</a>
                <?php if ($currentUser): ?>
                    <a href="connections.php?view=requests" class="notification-bell" aria-label="Открыть заявки" data-notification-toggle>
                        <span class="notification-bell-icon">&#128276;</span>
                        <?php if ($pendingRequestsCount > 0): ?>
                            <span class="notification-badge"><?php echo $pendingRequestsCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="create-post.php" class="header-plus-btn" aria-label="Добавить публикацию">+</a>
                    <a href="profile.php" class="user-avatar-link" aria-label="Открыть профиль">
                        <?php if (!empty($currentUser['avatar'])): ?>
                            <span class="user-avatar" style="background-image: url('<?php echo htmlspecialchars($currentUser['avatar']); ?>');"></span>
                        <?php else: ?>
                            <span class="user-avatar"><?php echo htmlspecialchars(mb_substr($currentUser['login'], 0, 1)); ?></span>
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
                            <article class="post-card">
                                <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                    <video class="post-card-media" controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                                <?php elseif (!empty($post['media_url'])): ?>
                                    <div class="post-card-media" style="background-image: url('<?php echo htmlspecialchars($post['media_url']); ?>');"></div>
                                <?php else: ?>
                                    <div class="post-card-media"></div>
                                <?php endif; ?>
                                <div class="post-card-copy">
                                    <p><?php echo htmlspecialchars($post['caption'] ?: 'Без подписи'); ?></p>
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
