<?php
session_start();
require './config/config.php';
require_once './includes/side-menu.php';

function profileLink(int $profileUserId, ?int $currentUserId): string
{
    if ($currentUserId !== null && $profileUserId === $currentUserId) {
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

$viewerStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$viewerStmt->execute(['id' => $_SESSION['user_id']]);
$viewer = $viewerStmt->fetch();

if (!$viewer) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$view = $_GET['view'] ?? $_POST['view'] ?? 'requests';
$allowedViews = ['requests', 'followers', 'following'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'requests';
}

$targetUserId = (int) ($_GET['user_id'] ?? $_POST['user_id'] ?? $viewer['id']);
$targetUserStmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id');
$targetUserStmt->execute(['id' => $targetUserId]);
$targetUser = $targetUserStmt->fetch();

if (!$targetUser) {
    header('Location: profile.php');
    exit;
}

$isOwnPage = (int) $targetUser['id'] === (int) $viewer['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $followerUserId = (int) ($_POST['follower_user_id'] ?? 0);

    if ($isOwnPage && $action === 'accept_follow_request' && $requestId > 0) {
        $stmt = $pdo->prepare("
            UPDATE followers
            SET status = 'accepted', declined_until = NULL
            WHERE id = :id AND following_id = :following_id AND status = 'pending'
        ");
        $stmt->execute([
            'id' => $requestId,
            'following_id' => $viewer['id'],
        ]);

        header('Location: connections.php?view=requests&request_accepted=1');
        exit;
    }

    if ($isOwnPage && $action === 'decline_follow_request' && $requestId > 0) {
        $stmt = $pdo->prepare("
            UPDATE followers
            SET status = 'declined', declined_until = DATE_ADD(NOW(), INTERVAL 3 DAY)
            WHERE id = :id AND following_id = :following_id AND status = 'pending'
        ");
        $stmt->execute([
            'id' => $requestId,
            'following_id' => $viewer['id'],
        ]);

        header('Location: connections.php?view=requests&request_declined=1');
        exit;
    }

    if ($isOwnPage && $action === 'remove_follower' && $followerUserId > 0) {
        $stmt = $pdo->prepare("
            DELETE FROM followers
            WHERE follower_id = :follower_id AND following_id = :following_id AND status = 'accepted'
        ");
        $stmt->execute([
            'follower_id' => $followerUserId,
            'following_id' => $viewer['id'],
        ]);

        header('Location: connections.php?view=followers&follower_removed=1');
        exit;
    }
}

$pendingRequestsCountStmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = :id AND status = 'pending'");
$pendingRequestsCountStmt->execute(['id' => $viewer['id']]);
$pendingRequestsCount = (int) $pendingRequestsCountStmt->fetchColumn();

$requests = [];
$followers = [];
$following = [];

if ($isOwnPage) {
    $stmt = $pdo->prepare("
        SELECT followers.id, followers.declined_until, users.id AS user_id, users.login, users.avatar
        FROM followers
        INNER JOIN users ON users.id = followers.follower_id
        WHERE followers.following_id = :id AND followers.status = 'pending'
        ORDER BY followers.created_at DESC
    ");
    $stmt->execute(['id' => $viewer['id']]);
    $requests = $stmt->fetchAll();
}

$stmt = $pdo->prepare("
    SELECT users.id, users.login, users.avatar
    FROM followers
    INNER JOIN users ON users.id = followers.follower_id
    WHERE followers.following_id = :id AND followers.status = 'accepted'
    ORDER BY users.login ASC
");
$stmt->execute(['id' => $targetUser['id']]);
$followers = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT users.id, users.login, users.avatar
    FROM followers
    INNER JOIN users ON users.id = followers.following_id
    WHERE followers.follower_id = :id AND followers.status = 'accepted'
    ORDER BY users.login ASC
");
$stmt->execute(['id' => $targetUser['id']]);
$following = $stmt->fetchAll();

$requestAccepted = isset($_GET['request_accepted']) && $_GET['request_accepted'] === '1';
$requestDeclined = isset($_GET['request_declined']) && $_GET['request_declined'] === '1';
$followerRemoved = isset($_GET['follower_removed']) && $_GET['follower_removed'] === '1';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <title>Snapix</title>
</head>
<body data-page="connections" class="has-side-menu">
    <?php render_side_menu($viewer); ?>

    <div class="page-glass-nav" aria-hidden="true"></div>

    <main class="profile-page">
        <section class="profile-posts card-surface">
            <div class="section-heading">
                <h1><?php echo $view === 'requests' ? 'Заявки' : ($view === 'followers' ? 'Подписчики' : 'Подписки'); ?></h1>
            </div>

            <div class="connections-tabs">
                <?php if ($isOwnPage): ?>
                    <a href="connections.php?view=requests" class="connections-tab<?php echo $view === 'requests' ? ' is-active' : ''; ?>">
                        Заявки
                        <?php if ($pendingRequestsCount > 0): ?>
                            <span class="notification-badge"><?php echo $pendingRequestsCount; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <a href="connections.php?view=followers<?php echo !$isOwnPage ? '&user_id=' . (int) $targetUser['id'] : ''; ?>" class="connections-tab<?php echo $view === 'followers' ? ' is-active' : ''; ?>">Подписчики</a>
                <a href="connections.php?view=following<?php echo !$isOwnPage ? '&user_id=' . (int) $targetUser['id'] : ''; ?>" class="connections-tab<?php echo $view === 'following' ? ' is-active' : ''; ?>">Подписки</a>
            </div>

            <?php if ($requestAccepted): ?>
                <p class="form-status is-success">Заявка принята.</p>
            <?php endif; ?>

            <?php if ($requestDeclined): ?>
                <p class="form-status is-success">Заявка отклонена. Повторная заявка будет недоступна 3 дня.</p>
            <?php endif; ?>

            <?php if ($followerRemoved): ?>
                <p class="form-status is-success">Подписчик удалён.</p>
            <?php endif; ?>

            <?php if ($view === 'requests'): ?>
                <?php if ($isOwnPage && $requests): ?>
                    <div class="request-list">
                        <?php foreach ($requests as $request): ?>
                            <article class="request-card">
                                <a href="<?php echo htmlspecialchars(profileLink((int) $request['user_id'], (int) $viewer['id'])); ?>" class="request-user">
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
                                        <input type="hidden" name="view" value="requests">
                                        <button type="submit" class="primary-link">Принять</button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="action" value="decline_follow_request">
                                        <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                        <input type="hidden" name="view" value="requests">
                                        <button type="submit" class="secondary-link">Отклонить</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">Новых заявок пока нет.</p>
                <?php endif; ?>
            <?php elseif ($view === 'followers'): ?>
                <?php if ($followers): ?>
                    <div class="request-list">
                        <?php foreach ($followers as $follower): ?>
                            <article class="request-card">
                                <a href="<?php echo htmlspecialchars(profileLink((int) $follower['id'], (int) $viewer['id'])); ?>" class="request-user">
                                    <span class="request-avatar"<?php if (!empty($follower['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($follower['avatar']); ?>');"<?php endif; ?>>
                                        <?php if (empty($follower['avatar'])): ?>
                                            <?php echo htmlspecialchars(mb_substr($follower['login'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="request-copy">
                                        <strong><?php echo htmlspecialchars($follower['login']); ?></strong>
                                        <span>Подписан на <?php echo $isOwnPage ? 'вас' : 'этого пользователя'; ?></span>
                                    </span>
                                </a>
                                <?php if ($isOwnPage): ?>
                                    <div class="request-actions">
                                        <form method="post" onsubmit="return confirm('Удалить этого подписчика?');">
                                            <input type="hidden" name="action" value="remove_follower">
                                            <input type="hidden" name="follower_user_id" value="<?php echo (int) $follower['id']; ?>">
                                            <input type="hidden" name="view" value="followers">
                                            <button type="submit" class="secondary-link">Удалить</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">Подписчиков пока нет.</p>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($following): ?>
                    <div class="request-list">
                        <?php foreach ($following as $followUser): ?>
                            <a href="<?php echo htmlspecialchars(profileLink((int) $followUser['id'], (int) $viewer['id'])); ?>" class="request-card request-card-link">
                                <span class="request-user">
                                    <span class="request-avatar"<?php if (!empty($followUser['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($followUser['avatar']); ?>');"<?php endif; ?>>
                                        <?php if (empty($followUser['avatar'])): ?>
                                            <?php echo htmlspecialchars(mb_substr($followUser['login'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="request-copy">
                                        <strong><?php echo htmlspecialchars($followUser['login']); ?></strong>
                                        <span>В списке подписок</span>
                                    </span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">Подписок пока нет.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
