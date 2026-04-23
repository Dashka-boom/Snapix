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

function getDialogs(PDO $pdo, int $userId): array
{
    $dialogsStmt = $pdo->prepare('
        SELECT
            chats.id,
            partner.id AS partner_id,
            partner.login AS partner_login,
            partner.avatar AS partner_avatar,
            latest.message_text AS last_message,
            latest.created_at AS last_message_created_at,
            (
                SELECT COUNT(*)
                FROM messages unread
                WHERE unread.chat_id = chats.id
                  AND unread.sender_id != :user_id
                  AND unread.is_read = 0
            ) AS unread_count
        FROM chats
        INNER JOIN users AS partner ON partner.id = IF(chats.user_one_id = :user_id, chats.user_two_id, chats.user_one_id)
        LEFT JOIN messages AS latest ON latest.id = (
            SELECT m2.id
            FROM messages m2
            WHERE m2.chat_id = chats.id
            ORDER BY m2.created_at DESC, m2.id DESC
            LIMIT 1
        )
        WHERE chats.user_one_id = :user_id OR chats.user_two_id = :user_id
        ORDER BY COALESCE(latest.created_at, chats.created_at) DESC
    ');
    $dialogsStmt->execute(['user_id' => $userId]);

    return $dialogsStmt->fetchAll();
}

function getMessages(PDO $pdo, int $chatId, int $userId): array
{
    $markReadStmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE chat_id = :chat_id AND sender_id != :user_id AND is_read = 0');
    $markReadStmt->execute([
        'chat_id' => $chatId,
        'user_id' => $userId,
    ]);

    $messagesStmt = $pdo->prepare('
        SELECT id, sender_id, message_text, created_at
        FROM messages
        WHERE chat_id = :chat_id
        ORDER BY created_at ASC, id ASC
    ');
    $messagesStmt->execute(['chat_id' => $chatId]);

    $messages = [];
    foreach ($messagesStmt->fetchAll() as $message) {
        $messages[] = [
            'id' => (int) $message['id'],
            'sender_id' => (int) $message['sender_id'],
            'message_text' => $message['message_text'],
            'created_at' => $message['created_at'],
            'created_at_human' => date('d.m.Y H:i', strtotime((string) $message['created_at'])),
            'is_mine' => (int) $message['sender_id'] === $userId,
        ];
    }

    return $messages;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'poll';
$chatId = (int) ($_POST['chat_id'] ?? $_GET['chat_id'] ?? 0);

$chatBelongsToUser = false;
if ($chatId > 0) {
    $membershipStmt = $pdo->prepare('SELECT id FROM chats WHERE id = :chat_id AND (user_one_id = :user_id OR user_two_id = :user_id) LIMIT 1');
    $membershipStmt->execute([
        'chat_id' => $chatId,
        'user_id' => $currentUserId,
    ]);
    $chatBelongsToUser = (bool) $membershipStmt->fetchColumn();
}

if ($action === 'send') {
    if (!$chatBelongsToUser) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'chat_forbidden']);
        exit;
    }

    $messageText = trim((string) ($_POST['message_text'] ?? ''));
    if ($messageText === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'message_empty']);
        exit;
    }

    $insertStmt = $pdo->prepare('INSERT INTO messages (chat_id, sender_id, message_text, is_read) VALUES (:chat_id, :sender_id, :message_text, 0)');
    $insertStmt->execute([
        'chat_id' => $chatId,
        'sender_id' => $currentUserId,
        'message_text' => mb_substr($messageText, 0, 1000),
    ]);

    $touchChatStmt = $pdo->prepare('UPDATE chats SET updated_at = CURRENT_TIMESTAMP WHERE id = :chat_id');
    $touchChatStmt->execute(['chat_id' => $chatId]);
}

$messages = [];
if ($chatBelongsToUser) {
    $messages = getMessages($pdo, $chatId, $currentUserId);
}

$dialogs = getDialogs($pdo, $currentUserId);

$unreadTotalStmt = $pdo->prepare('SELECT COUNT(*) FROM messages m INNER JOIN chats c ON c.id = m.chat_id WHERE (c.user_one_id = :user_id OR c.user_two_id = :user_id) AND m.sender_id != :user_id AND m.is_read = 0');
$unreadTotalStmt->execute(['user_id' => $currentUserId]);
$unreadTotal = (int) $unreadTotalStmt->fetchColumn();

echo json_encode([
    'ok' => true,
    'messages' => $messages,
    'dialogs' => $dialogs,
    'unread_total' => $unreadTotal,
]);
