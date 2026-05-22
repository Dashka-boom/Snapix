<?php
session_start();
require './config/config.php';

function snapix_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    snapix_json_response(['success' => false, 'message' => 'Нужно войти в аккаунт.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    snapix_json_response(['success' => false, 'message' => 'Метод не поддерживается.'], 405);
}

$viewerId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$requestId = (int) ($_POST['request_id'] ?? 0);

if ($action === 'mark_moderation_notification_read') {
    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    if ($notificationId <= 0) {
        snapix_json_response(['success' => false, 'message' => 'Некорректное уведомление.'], 400);
    }

    $stmt = $pdo->prepare("
        UPDATE user_notifications
        SET is_read = 1
        WHERE id = :id AND user_id = :user_id AND is_read = 0
    " );
    $stmt->execute([
        'id' => $notificationId,
        'user_id' => $viewerId,
    ]);

    snapix_json_response([
        'success' => true,
        'message' => 'Уведомление помечено прочитанным.',
    ]);
}

if (!in_array($action, ['accept_follow_request', 'decline_follow_request'], true) || $requestId <= 0) {
    snapix_json_response(['success' => false, 'message' => 'Некорректная заявка.'], 400);
}

if ($action === 'accept_follow_request') {
    $stmt = $pdo->prepare("\n        UPDATE followers\n        SET status = 'accepted', declined_until = NULL\n        WHERE id = :id AND following_id = :following_id AND status = 'pending'\n    ");
    $stmt->execute([
        'id' => $requestId,
        'following_id' => $viewerId,
    ]);

    snapix_json_response([
        'success' => $stmt->rowCount() > 0,
        'message' => $stmt->rowCount() > 0 ? 'Заявка принята.' : 'Заявка уже обработана.',
        'status' => 'accepted',
    ], $stmt->rowCount() > 0 ? 200 : 409);
}

$stmt = $pdo->prepare("\n    UPDATE followers\n    SET status = 'declined', declined_until = DATE_ADD(NOW(), INTERVAL 3 DAY)\n    WHERE id = :id AND following_id = :following_id AND status = 'pending'\n");
$stmt->execute([
    'id' => $requestId,
    'following_id' => $viewerId,
]);

snapix_json_response([
    'success' => $stmt->rowCount() > 0,
    'message' => $stmt->rowCount() > 0 ? 'Заявка отклонена.' : 'Заявка уже обработана.',
    'status' => 'declined',
], $stmt->rowCount() > 0 ? 200 : 409);
