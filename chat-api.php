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

function ensureChatSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $existingColumns = [];
    $columnsStmt = $pdo->query('SHOW COLUMNS FROM messages');
    foreach ($columnsStmt->fetchAll() as $column) {
        $existingColumns[(string) $column['Field']] = true;
    }

    $requiredMessageColumns = [
        'reply_to_message_id' => 'ALTER TABLE messages ADD COLUMN reply_to_message_id BIGINT UNSIGNED NULL AFTER post_id',
        'forwarded_from_message_id' => 'ALTER TABLE messages ADD COLUMN forwarded_from_message_id BIGINT UNSIGNED NULL AFTER reply_to_message_id',
        'deleted_for_all' => 'ALTER TABLE messages ADD COLUMN deleted_for_all TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read',
        'edited_at' => 'ALTER TABLE messages ADD COLUMN edited_at TIMESTAMP NULL DEFAULT NULL AFTER created_at',
    ];

    foreach ($requiredMessageColumns as $columnName => $sql) {
        if (!isset($existingColumns[$columnName])) {
            $pdo->exec($sql);
        }
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS message_hidden (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        message_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_message_hidden (message_id, user_id),
        KEY idx_message_hidden_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS pinned_messages (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        chat_id BIGINT UNSIGNED NOT NULL,
        message_id BIGINT UNSIGNED NOT NULL,
        pinned_by_user_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_pinned_message (chat_id, message_id),
        KEY idx_pinned_chat (chat_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

    $pdo->exec("CREATE TABLE IF NOT EXISTS message_reactions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        message_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        reaction VARCHAR(16) NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_message_reaction (message_id, user_id),
        KEY idx_message_reactions_message (message_id),
        KEY idx_message_reactions_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $ready = true;
}

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
        SELECT
            m.id, m.sender_id, m.message_text, m.post_id, m.created_at, m.edited_at, m.deleted_for_all,
            m.reply_to_message_id, m.forwarded_from_message_id
        FROM messages m
        LEFT JOIN message_hidden mh ON mh.message_id = m.id AND mh.user_id = :user_id
        WHERE m.chat_id = :chat_id
          AND mh.id IS NULL
        ORDER BY m.created_at ASC, m.id ASC
    ');
    $messagesStmt->execute([
        'chat_id' => $chatId,
        'user_id' => $userId,
    ]);

    $rawMessages = $messagesStmt->fetchAll();
    $sharedPostIds = [];

    foreach ($rawMessages as $message) {
        $text = (string) ($message['message_text'] ?? '');
        $legacyPostId = 0;
        if (str_starts_with($text, '[post_share]|')) {
            $parts = explode('|', $text);
            $legacyPostId = (int) ($parts[1] ?? 0);
        }
        $sharedPostId = (int) ($message['post_id'] ?? 0);
        if ($sharedPostId <= 0) {
            $sharedPostId = $legacyPostId;
        }
        if ($sharedPostId > 0) {
            $sharedPostIds[$sharedPostId] = $sharedPostId;
        }
    }

    $sharedPosts = [];
    if ($sharedPostIds) {
        $placeholders = implode(',', array_fill(0, count($sharedPostIds), '?'));
        $sharedPostsStmt = $pdo->prepare("
            SELECT
                posts.id,
                posts.caption,
                users.id AS author_id,
                users.login AS author_login,
                users.avatar AS author_avatar,
                post_media.media_type,
                post_media.media_url
            FROM posts
            INNER JOIN users ON users.id = posts.user_id
            LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
            WHERE posts.id IN ($placeholders)
        ");
        $sharedPostsStmt->execute(array_values($sharedPostIds));
        foreach ($sharedPostsStmt->fetchAll() as $sharedPost) {
            $sharedPosts[(int) $sharedPost['id']] = $sharedPost;
        }
    }

    $messages = [];
    foreach ($rawMessages as $message) {
        $text = (string) ($message['message_text'] ?? '');
        $sharedPost = null;
        $isPostShare = false;
        $legacyPostId = 0;
        if (str_starts_with($text, '[post_share]|')) {
            $isPostShare = true;
            $parts = explode('|', $text);
            $legacyPostId = (int) ($parts[1] ?? 0);
        }
        $sharedPostId = (int) ($message['post_id'] ?? 0);
        if ($sharedPostId <= 0) {
            $sharedPostId = $legacyPostId;
        }

        if ((int) $message['deleted_for_all'] === 1) {
            $text = 'Сообщение удалено';
            $sharedPost = null;
            $isPostShare = false;
        } elseif ($sharedPostId > 0 && isset($sharedPosts[$sharedPostId])) {
            $post = $sharedPosts[$sharedPostId];
            $sharedPost = [
                'id' => (int) $post['id'],
                'author_id' => (int) $post['author_id'],
                'author_login' => (string) $post['author_login'],
                'author_avatar' => (string) ($post['author_avatar'] ?? ''),
                'caption' => (string) ($post['caption'] ?? ''),
                'media_type' => (string) ($post['media_type'] ?? ''),
                'media_url' => (string) ($post['media_url'] ?? ''),
                'post_url' => 'post.php?id=' . (int) $post['id'],
            ];
            $isPostShare = true;
        } elseif ($isPostShare) {
            $text = 'Пересланная публикация недоступна';
        }

        $messages[] = [
            'id' => (int) $message['id'],
            'sender_id' => (int) $message['sender_id'],
            'message_text' => $text,
            'post_id' => $sharedPostId > 0 ? $sharedPostId : null,
            'created_at' => $message['created_at'],
            'created_at_human' => date('d.m.Y H:i', strtotime((string) $message['created_at'])),
            'is_mine' => (int) $message['sender_id'] === $userId,
            'is_post_share' => $isPostShare,
            'shared_post' => $sharedPost,
            'reply_to_message_id' => (int) ($message['reply_to_message_id'] ?? 0),
            'forwarded_from_message_id' => (int) ($message['forwarded_from_message_id'] ?? 0),
            'deleted_for_all' => (int) ($message['deleted_for_all'] ?? 0) === 1,
            'is_edited' => !empty($message['edited_at']),
            'reactions' => [],
            'my_reaction' => null,
        ];
    }

    $indexedMessages = [];
    $messageIds = [];
    foreach ($messages as $message) {
        $indexedMessages[(int) $message['id']] = $message;
        $messageIds[] = (int) $message['id'];
    }

    foreach ($messages as &$message) {
        $replyToId = (int) $message['reply_to_message_id'];
        $forwardedFromId = (int) $message['forwarded_from_message_id'];
        $message['reply_to'] = $replyToId > 0 && isset($indexedMessages[$replyToId]) ? $indexedMessages[$replyToId] : null;
        $message['forwarded_from'] = $forwardedFromId > 0 && isset($indexedMessages[$forwardedFromId]) ? $indexedMessages[$forwardedFromId] : null;
    }
    unset($message);

    if ($messageIds) {
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $reactionRowsStmt = $pdo->prepare("
            SELECT message_id, reaction, COUNT(*) AS total
            FROM message_reactions
            WHERE message_id IN ($placeholders)
            GROUP BY message_id, reaction
        ");
        $reactionRowsStmt->execute($messageIds);
        $reactionsMap = [];
        foreach ($reactionRowsStmt->fetchAll() as $reactionRow) {
            $messageId = (int) $reactionRow['message_id'];
            if (!isset($reactionsMap[$messageId])) {
                $reactionsMap[$messageId] = [];
            }
            $reactionsMap[$messageId][] = [
                'reaction' => (string) $reactionRow['reaction'],
                'count' => (int) $reactionRow['total'],
            ];
        }

        $userReactionsStmt = $pdo->prepare("
            SELECT message_id, reaction
            FROM message_reactions
            WHERE user_id = ?
              AND message_id IN ($placeholders)
        ");
        $userReactionsStmt->execute(array_merge([$userId], $messageIds));
        $myReactionMap = [];
        foreach ($userReactionsStmt->fetchAll() as $userReaction) {
            $myReactionMap[(int) $userReaction['message_id']] = (string) $userReaction['reaction'];
        }

        foreach ($messages as &$message) {
            $messageId = (int) $message['id'];
            $message['reactions'] = $reactionsMap[$messageId] ?? [];
            $message['my_reaction'] = $myReactionMap[$messageId] ?? null;
        }
        unset($message);
    }

    return $messages;
}

function getPinnedMessages(PDO $pdo, int $chatId, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT m.id, m.message_text, m.post_id, m.deleted_for_all
        FROM pinned_messages pm
        INNER JOIN messages m ON m.id = pm.message_id
        LEFT JOIN message_hidden mh ON mh.message_id = m.id AND mh.user_id = :user_id
        WHERE pm.chat_id = :chat_id
          AND mh.id IS NULL
        ORDER BY pm.created_at DESC
        LIMIT 5
    ');
    $stmt->execute([
        'chat_id' => $chatId,
        'user_id' => $userId,
    ]);

    return array_map(static function (array $item): array {
        return [
            'id' => (int) $item['id'],
            'message_text' => (int) $item['deleted_for_all'] === 1 ? 'Сообщение удалено' : (string) ($item['message_text'] ?? ''),
            'post_id' => (int) ($item['post_id'] ?? 0),
            'deleted_for_all' => (int) ($item['deleted_for_all'] ?? 0) === 1,
            'shared_post' => (int) ($item['post_id'] ?? 0) > 0,
        ];
    }, $stmt->fetchAll());
}

ensureChatSchema($pdo);

$action = $_POST['action'] ?? $_GET['action'] ?? 'poll';
$chatId = (int) ($_POST['chat_id'] ?? $_GET['chat_id'] ?? 0);
$createdMessageId = 0;

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
        echo json_encode(['ok' => false, 'error' => 'chat_forbidden', 'chat_id' => $chatId]);
        exit;
    }

    $messageText = trim((string) ($_POST['message_text'] ?? ''));
    $replyToMessageId = (int) ($_POST['reply_to_message_id'] ?? 0);

    if ($messageText === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'message_empty', 'chat_id' => $chatId]);
        exit;
    }

    // проверка reply
    if ($replyToMessageId > 0) {
        $replyExistsStmt = $pdo->prepare('
            SELECT id FROM messages 
            WHERE id = :message_id AND chat_id = :chat_id 
            LIMIT 1
        ');
        $replyExistsStmt->execute([
            'message_id' => $replyToMessageId,
            'chat_id' => $chatId,
        ]);

        if (!$replyExistsStmt->fetchColumn()) {
            $replyToMessageId = 0;
        }
    }

    $createdMessageId = 0;
    $maxRetries = 3;

    while ($maxRetries--) {
        try {
            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare('
                INSERT INTO messages (
                    chat_id, sender_id, message_text, post_id,
                    reply_to_message_id, forwarded_from_message_id,
                    is_read, deleted_for_all
                ) VALUES (
                    :chat_id, :sender_id, :message_text, :post_id,
                    :reply_to_message_id, NULL,
                    0, 0
                )
            ');

            $insertStmt->execute([
                'chat_id' => $chatId,
                'sender_id' => $currentUserId,
                'message_text' => mb_substr($messageText, 0, 1000),
                'post_id' => null,
                'reply_to_message_id' => $replyToMessageId > 0 ? $replyToMessageId : null,
            ]);

            $createdMessageId = (int) $pdo->lastInsertId();

            $touchChatStmt = $pdo->prepare('
                UPDATE chats 
                SET updated_at = CURRENT_TIMESTAMP 
                WHERE id = :chat_id
            ');
            $touchChatStmt->execute(['chat_id' => $chatId]);

            $pdo->commit();
            break;

        } 
        catch (PDOException $e) {
            $pdo->rollBack();

            // DEADLOCK RETRY
            if ($e->getCode() === '40001') {
                usleep(100000); // 0.1 сек
                continue;
            }

            // ЛОГ ошибки
            file_put_contents(
                __DIR__ . '/chat_error.log',
                date('Y-m-d H:i:s') . ' SEND ERROR: ' . $e->getMessage() . PHP_EOL,
                FILE_APPEND
            );

            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => 'message_insert_failed',
                'chat_id' => $chatId
            ]);
            exit;
        }
    }
    }


if ($action === 'edit' && $chatBelongsToUser) {
    $messageId = (int) ($_POST['message_id'] ?? 0);
    $messageText = trim((string) ($_POST['message_text'] ?? ''));

    $messageStmt = $pdo->prepare('SELECT sender_id, forwarded_from_message_id FROM messages WHERE id = :id AND chat_id = :chat_id LIMIT 1');
    $messageStmt->execute(['id' => $messageId, 'chat_id' => $chatId]);
    $message = $messageStmt->fetch();

    if (!$message || (int) $message['sender_id'] !== $currentUserId || (int) ($message['forwarded_from_message_id'] ?? 0) > 0) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'edit_forbidden']);
        exit;
    }
    if ($messageText === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'message_empty']);
        exit;
    }

    $pdo->prepare('UPDATE messages SET message_text = :message_text, edited_at = CURRENT_TIMESTAMP WHERE id = :id')
        ->execute([
            'message_text' => substr($messageText, 0, 1000),
            'id' => $messageId,
        ]);
}

if ($action === 'delete' && $chatBelongsToUser) {
    $messageId = (int) ($_POST['message_id'] ?? 0);
    $deleteMode = (string) ($_POST['delete_mode'] ?? 'self');

    $messageStmt = $pdo->prepare('SELECT sender_id FROM messages WHERE id = :id AND chat_id = :chat_id LIMIT 1');
    $messageStmt->execute(['id' => $messageId, 'chat_id' => $chatId]);
    $message = $messageStmt->fetch();
    if (!$message) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'message_not_found']);
        exit;
    }

    if ($deleteMode === 'all' && (int) $message['sender_id'] === $currentUserId) {
        $pdo->prepare('UPDATE messages SET deleted_for_all = 1, message_text = "Сообщение удалено" WHERE id = :id')->execute(['id' => $messageId]);
    } else {
        $pdo->prepare('INSERT IGNORE INTO message_hidden (message_id, user_id) VALUES (:message_id, :user_id)')->execute([
            'message_id' => $messageId,
            'user_id' => $currentUserId,
        ]);
    }
}

if ($action === 'pin' && $chatBelongsToUser) {
    $messageId = (int) ($_POST['message_id'] ?? 0);
    $pdo->prepare('INSERT IGNORE INTO pinned_messages (chat_id, message_id, pinned_by_user_id) VALUES (:chat_id, :message_id, :user_id)')
        ->execute([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'user_id' => $currentUserId,
        ]);
}

if ($action === 'forward' && $chatBelongsToUser) {
    $messageId = (int) ($_POST['message_id'] ?? 0);
    $receiverId = (int) ($_POST['receiver_id'] ?? 0);

    if ($receiverId <= 0 || $receiverId === $currentUserId) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'invalid_receiver']);
        exit;
    }

    $sourceMessageStmt = $pdo->prepare('SELECT message_text, post_id FROM messages WHERE id = :id AND chat_id = :chat_id LIMIT 1');
    $sourceMessageStmt->execute(['id' => $messageId, 'chat_id' => $chatId]);
    $source = $sourceMessageStmt->fetch();
    if (!$source) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'message_not_found']);
        exit;
    }

    $userOne = min($currentUserId, $receiverId);
    $userTwo = max($currentUserId, $receiverId);

    $chatStmt = $pdo->prepare('SELECT id FROM chats WHERE user_one_id = :user_one_id AND user_two_id = :user_two_id LIMIT 1');
    $chatStmt->execute([
        'user_one_id' => $userOne,
        'user_two_id' => $userTwo,
    ]);
    $targetChatId = (int) $chatStmt->fetchColumn();

    if ($targetChatId <= 0) {
        $createChatStmt = $pdo->prepare('INSERT INTO chats (user_one_id, user_two_id) VALUES (:user_one_id, :user_two_id)');
        $createChatStmt->execute([
            'user_one_id' => $userOne,
            'user_two_id' => $userTwo,
        ]);
        $targetChatId = (int) $pdo->lastInsertId();
    }

    $pdo->prepare('INSERT INTO messages (chat_id, sender_id, message_text, post_id, reply_to_message_id, forwarded_from_message_id, is_read, deleted_for_all) VALUES (:chat_id, :sender_id, :message_text, :post_id, NULL, :forwarded_from_message_id, 0, 0)')
        ->execute([
            'chat_id' => $targetChatId,
            'sender_id' => $currentUserId,
            'message_text' => substr((string) ($source['message_text'] ?? ''), 0, 1000),
            'post_id' => (int) ($source['post_id'] ?? 0) > 0 ? (int) $source['post_id'] : null,
            'forwarded_from_message_id' => $messageId,
        ]);

    $pdo->prepare('UPDATE chats SET updated_at = CURRENT_TIMESTAMP WHERE id = :chat_id')->execute(['chat_id' => $targetChatId]);
}

if ($action === 'react' && $chatBelongsToUser) {
    $messageId = (int) ($_POST['message_id'] ?? 0);
    $reaction = trim((string) ($_POST['reaction'] ?? ''));
    $allowedReactions = ['❤️', '😂', '👍', '🔥', '😢', '😮'];

    if (!in_array($reaction, $allowedReactions, true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'invalid_reaction']);
        exit;
    }

    $existsStmt = $pdo->prepare('SELECT id FROM messages WHERE id = :message_id AND chat_id = :chat_id LIMIT 1');
    $existsStmt->execute([
        'message_id' => $messageId,
        'chat_id' => $chatId,
    ]);
    if (!$existsStmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'message_not_found']);
        exit;
    }

    $currentReactionStmt = $pdo->prepare('SELECT reaction FROM message_reactions WHERE message_id = :message_id AND user_id = :user_id LIMIT 1');
    $currentReactionStmt->execute([
        'message_id' => $messageId,
        'user_id' => $currentUserId,
    ]);
    $currentReaction = (string) ($currentReactionStmt->fetchColumn() ?: '');

    if ($currentReaction === $reaction) {
        $pdo->prepare('DELETE FROM message_reactions WHERE message_id = :message_id AND user_id = :user_id')->execute([
            'message_id' => $messageId,
            'user_id' => $currentUserId,
        ]);
    } elseif ($currentReaction !== '') {
        $pdo->prepare('UPDATE message_reactions SET reaction = :reaction, created_at = CURRENT_TIMESTAMP WHERE message_id = :message_id AND user_id = :user_id')->execute([
            'reaction' => $reaction,
            'message_id' => $messageId,
            'user_id' => $currentUserId,
        ]);
    } else {
        $pdo->prepare('INSERT INTO message_reactions (message_id, user_id, reaction) VALUES (:message_id, :user_id, :reaction)')->execute([
            'message_id' => $messageId,
            'user_id' => $currentUserId,
            'reaction' => $reaction,
        ]);
    }
}

$messages = [];
$pinnedMessages = [];
if ($chatBelongsToUser) {
    $messages = getMessages($pdo, $chatId, $currentUserId);
    $pinnedMessages = getPinnedMessages($pdo, $chatId, $currentUserId);
}

$dialogs = getDialogs($pdo, $currentUserId);

$unreadTotalStmt = $pdo->prepare('SELECT COUNT(*) FROM messages m INNER JOIN chats c ON c.id = m.chat_id WHERE (c.user_one_id = :user_id OR c.user_two_id = :user_id) AND m.sender_id != :user_id AND m.is_read = 0');
$unreadTotalStmt->execute(['user_id' => $currentUserId]);
$unreadTotal = (int) $unreadTotalStmt->fetchColumn();

echo json_encode([
    'ok' => true,
    'message_id' => $action === 'send' ? ($createdMessageId ?? 0) : 0,
    'messages' => $messages,
    'dialogs' => $dialogs,
    'pinned_messages' => $pinnedMessages,
    'unread_total' => $unreadTotal,
    'chat_id' => $chatId,
    'chat_exists' => $chatBelongsToUser,
]);
