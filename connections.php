<?php
declare(strict_types=1);

session_start();

require './config/config.php';
require_once './includes/side-menu.php';

const MAX_CONNECTION_ROWS = 200;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function profileLink(int $profileUserId, ?int $currentUserId): string
{
    if ($currentUserId !== null && $profileUserId === $currentUserId) {
        return 'profile.php';
    }

    return 'user.php?id=' . $profileUserId;
}

function safeRedirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function getIntParam(string $key, int $default = 0): int
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;

    if (is_array($value)) {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_INT, [
        'options' => [
            'default' => $default,
            'min_range' => 1,
        ],
    ]);
}

function getStringParam(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;

    if (is_array($value)) {
        return $default;
    }

    return trim((string) $value);
}

function requireValidCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Недействительный CSRF-токен.');
    }
}

function ensurePostRateLimit(): void
{
    $now = time();
    $last = (int) ($_SESSION['connections_last_post_at'] ?? 0);

    if ($last > 0 && ($now - $last) < 1) {
        http_response_code(429);
        exit('Слишком много действий. Попробуйте позже.');
    }

    $_SESSION['connections_last_post_at'] = $now;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['user_id']) || !filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT)) {
    safeRedirect('login.php');
}

$currentUserId = (int) $_SESSION['user_id'];

$viewerStmt = $pdo->prepare('
    SELECT id, login, avatar
    FROM users
    WHERE id = :id
    LIMIT 1
');
$viewerStmt->execute(['id' => $currentUserId]);
$viewer = $viewerStmt->fetch();

if (!$viewer) {
    session_unset();
    session_destroy();
    safeRedirect('login.php');
}

$view = getStringParam('view', 'requests');
$allowedViews = ['requests', 'followers', 'following'];

if (!in_array($view, $allowedViews, true)) {
    $view = 'requests';
}

$targetUserId = getIntParam('user_id', (int) $viewer['id']);

$targetUserStmt = $pdo->prepare('
    SELECT id, login, avatar
    FROM users
    WHERE id = :id
    LIMIT 1
');
$targetUserStmt->execute(['id' => $targetUserId]);
$targetUser = $targetUserStmt->fetch();

if (!$targetUser) {
    safeRedirect('profile.php');
}

$isOwnPage = (int) $targetUser['id'] === (int) $viewer['id'];

if (!$isOwnPage && $view === 'requests') {
    $view = 'followers';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    ensurePostRateLimit();

    $action = getStringParam('action');
    $requestId = getIntParam('request_id');
    $followerUserId = getIntParam('follower_user_id');

    if (!$isOwnPage) {
        http_response_code(403);
        exit('Недостаточно прав.');
    }

    if ($action === 'accept_follow_request' && $requestId > 0) {
        $stmt = $pdo->prepare("
            UPDATE followers
            SET status = 'accepted', declined_until = NULL
            WHERE id = :id
              AND following_id = :following_id
              AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $requestId,
            'following_id' => (int) $viewer['id'],
        ]);

        safeRedirect('connections.php?view=requests&request_accepted=1');
    }

    if ($action === 'decline_follow_request' && $requestId > 0) {
        $stmt = $pdo->prepare("
            UPDATE followers
            SET status = 'declined', declined_until = DATE_ADD(NOW(), INTERVAL 3 DAY)
            WHERE id = :id
              AND following_id = :following_id
              AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $requestId,
            'following_id' => (int) $viewer['id'],
        ]);

        safeRedirect('connections.php?view=requests&request_declined=1');
    }

    if ($action === 'remove_follower' && $followerUserId > 0) {
        $stmt = $pdo->prepare("
            DELETE FROM followers
            WHERE follower_id = :follower_id
              AND following_id = :following_id
              AND status = 'accepted'
            LIMIT 1
        ");
        $stmt->execute([
            'follower_id' => $followerUserId,
            'following_id' => (int) $viewer['id'],
        ]);

        safeRedirect('connections.php?view=followers&follower_removed=1');
    }

    http_response_code(400);
    exit('Некорректное действие.');
}

$pendingRequestsCountStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM followers
    WHERE following_id = :id
      AND status = 'pending'
");
$pendingRequestsCountStmt->execute(['id' => (int) $viewer['id']]);
$pendingRequestsCount = (int) $pendingRequestsCountStmt->fetchColumn();

$requests = [];
$followers = [];
$following = [];

if ($isOwnPage) {
    $stmt = $pdo->prepare("
        SELECT followers.id, followers.declined_until, users.id AS user_id, users.login, users.avatar
        FROM followers
        INNER JOIN users ON users.id = followers.follower_id
        WHERE followers.following_id = :id
          AND followers.status = 'pending'
        ORDER BY followers.created_at DESC
        LIMIT " . MAX_CONNECTION_ROWS
    );
    $stmt->execute(['id' => (int) $viewer['id']]);
    $requests = $stmt->fetchAll();
}

$stmt = $pdo->prepare("
    SELECT users.id, users.login, users.avatar
    FROM followers
    INNER JOIN users ON users.id = followers.follower_id
    WHERE followers.following_id = :id
      AND followers.status = 'accepted'
    ORDER BY users.login ASC
    LIMIT " . MAX_CONNECTION_ROWS
);
$stmt->execute(['id' => (int) $targetUser['id']]);
$followers = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT users.id, users.login, users.avatar
    FROM followers
    INNER JOIN users ON users.id = followers.following_id
    WHERE followers.follower_id = :id
      AND followers.status = 'accepted'
    ORDER BY users.login ASC
    LIMIT " . MAX_CONNECTION_ROWS
);
$stmt->execute(['id' => (int) $targetUser['id']]);
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
    <link rel="icon" href="icon/light theme/logo.png" type="image/png">
    <title>Snapix</title>
</head>
<body data-page="connections" class="has-side-menu">
    <?php render_side_menu($viewer); ?>

    <div class="page-glass-nav" aria-hidden="true"></div>

    <main class="profile-page">
        <section class="profile-posts card-surface">
            <div class="section-heading">
                <h1><?php echo $view === 'requests' ? 'Заявки' : ($view === 'followers' ? 'Смотрители' : 'Смотримые'); ?></h1>
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
                <a href="connections.php?view=followers<?php echo !$isOwnPage ? '&user_id=' . (int) $targetUser['id'] : ''; ?>" class="connections-tab<?php echo $view === 'followers' ? ' is-active' : ''; ?>">Смотрители</a>
                <a href="connections.php?view=following<?php echo !$isOwnPage ? '&user_id=' . (int) $targetUser['id'] : ''; ?>" class="connections-tab<?php echo $view === 'following' ? ' is-active' : ''; ?>">Смотримые</a>
            </div>

            <?php if ($requestAccepted): ?>
                <p class="form-status is-success">Заявка принята.</p>
            <?php endif; ?>

            <?php if ($requestDeclined): ?>
                <p class="form-status is-success">Заявка отклонена. Повторная заявка будет недоступна 3 дня.</p>
            <?php endif; ?>

            <?php if ($followerRemoved): ?>
                <p class="form-status is-success">Смотритель удалён.</p>
            <?php endif; ?>

            <?php if ($view === 'requests'): ?>
                <?php if ($isOwnPage && $requests): ?>
                    <div class="request-list">
                        <?php foreach ($requests as $request): ?>
                            <article class="request-card">
                                <a href="<?php echo e(profileLink((int) $request['user_id'], (int) $viewer['id'])); ?>" class="request-user">
                                    <span class="request-avatar"<?php if (!empty($request['avatar'])): ?> style="background-image: url('<?php echo e($request['avatar']); ?>');"<?php endif; ?>>
                                        <?php if (empty($request['avatar'])): ?>
                                            <?php echo e(mb_substr($request['login'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="request-copy">
                                        <strong><?php echo e($request['login']); ?></strong>
                                        <span>Этот пользователь хочет подружиться с вами</span>
                                    </span>
                                </a>
                                <div class="request-actions">
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="accept_follow_request">
                                        <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                        <input type="hidden" name="view" value="requests">
                                        <button type="submit" class="primary-link">Принять</button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
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
                                <a href="<?php echo e(profileLink((int) $follower['id'], (int) $viewer['id'])); ?>" class="request-user">
                                    <span class="request-avatar"<?php if (!empty($follower['avatar'])): ?> style="background-image: url('<?php echo e($follower['avatar']); ?>');"<?php endif; ?>>
                                        <?php if (empty($follower['avatar'])): ?>
                                            <?php echo e(mb_substr($follower['login'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="request-copy">
                                        <strong><?php echo e($follower['login']); ?></strong>
                                        <span>Смотрит <?php echo $isOwnPage ? 'вас' : 'этого пользователя'; ?></span>
                                    </span>
                                </a>
                                <?php if ($isOwnPage): ?>
                                    <div class="request-actions">
                                        <form method="post" onsubmit="return confirm('Удалить этого смотрителя?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
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
                    <p class="empty-state">Смотрителей  пока нет.</p>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($following): ?>
                    <div class="request-list">
                        <?php foreach ($following as $followUser): ?>
                            <a href="<?php echo e(profileLink((int) $followUser['id'], (int) $viewer['id'])); ?>" class="request-card request-card-link">
                                <span class="request-user">
                                    <span class="request-avatar"<?php if (!empty($followUser['avatar'])): ?> style="background-image: url('<?php echo e($followUser['avatar']); ?>');"<?php endif; ?>>
                                        <?php if (empty($followUser['avatar'])): ?>
                                            <?php echo e(mb_substr($followUser['login'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="request-copy">
                                        <strong><?php echo e($followUser['login']); ?></strong>
                                        <span>Смотрите друг друга</span>
                                    </span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">Смотримых пока нет.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
