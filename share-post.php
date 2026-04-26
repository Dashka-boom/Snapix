<?php
session_start();
require './config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$postId = (int) ($_POST['post_id'] ?? 0);
$receiverId = (int) ($_POST['receiver_id'] ?? 0);

if ($postId <= 0 || $receiverId <= 0 || $receiverId === $currentUserId) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit;
}

$allowedRecipientStmt = $pdo->prepare("SELECT 1 FROM followers WHERE follower_id = :current_user_id AND following_id = :receiver_id AND status = 'accepted' LIMIT 1");
$allowedRecipientStmt->execute([
    'current_user_id' => $currentUserId,
    'receiver_id' => $receiverId,
]);

if (!$allowedRecipientStmt->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'recipient_not_allowed']);
    exit;
}

$postStmt = $pdo->prepare('SELECT posts.id, posts.user_id, users.login FROM posts INNER JOIN users ON users.id = posts.user_id WHERE posts.id = :post_id AND posts.is_deleted = 0 LIMIT 1');
$postStmt->execute(['post_id' => $postId]);
$post = $postStmt->fetch();

if (!$post) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'post_not_found']);
    exit;
}

$userOne = min($currentUserId, $receiverId);
$userTwo = max($currentUserId, $receiverId);

$chatStmt = $pdo->prepare('SELECT id FROM chats WHERE user_one_id = :user_one_id AND user_two_id = :user_two_id LIMIT 1');
$chatStmt->execute([
    'user_one_id' => $userOne,
    'user_two_id' => $userTwo,
]);
$chatId = (int) $chatStmt->fetchColumn();

if ($chatId <= 0) {
    $createChatStmt = $pdo->prepare('INSERT INTO chats (user_one_id, user_two_id) VALUES (:user_one_id, :user_two_id)');
    $createChatStmt->execute([
        'user_one_id' => $userOne,
        'user_two_id' => $userTwo,
    ]);
    $chatId = (int) $pdo->lastInsertId();
}

$messageText = '[post_share]|' . (int) $post['id'] . '|' . (int) $post['user_id'] . '|' . str_replace('|', ' ', (string) $post['login']);

try {
    $insertMessageStmt = $pdo->prepare('INSERT INTO messages (chat_id, sender_id, message_text, post_id, is_read) VALUES (:chat_id, :sender_id, :message_text, :post_id, 0)');
    $insertMessageStmt->execute([
        'chat_id' => $chatId,
        'sender_id' => $currentUserId,
        'message_text' => $messageText,
        'post_id' => (int) $post['id'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'share_insert_failed',
        'chat_id' => $chatId,
        'details' => $e->getMessage(),
    ]);
    exit;
}

$pdo->prepare('UPDATE chats SET updated_at = CURRENT_TIMESTAMP WHERE id = :chat_id')->execute(['chat_id' => $chatId]);

echo json_encode(['ok' => true]);
