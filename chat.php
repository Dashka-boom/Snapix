<?php
session_start();
require './config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserStmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id LIMIT 1');
$currentUserStmt->execute(['id' => (int) $_SESSION['user_id']]);
$currentUser = $currentUserStmt->fetch();

if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

function getOrCreateChat(PDO $pdo, int $firstUserId, int $secondUserId): int
{
    $userOne = min($firstUserId, $secondUserId);
    $userTwo = max($firstUserId, $secondUserId);

    $existingStmt = $pdo->prepare('SELECT id FROM chats WHERE user_one_id = :user_one_id AND user_two_id = :user_two_id LIMIT 1');
    $existingStmt->execute([
        'user_one_id' => $userOne,
        'user_two_id' => $userTwo,
    ]);

    $chatId = (int) $existingStmt->fetchColumn();
    if ($chatId > 0) {
        return $chatId;
    }

    $insertStmt = $pdo->prepare('INSERT INTO chats (user_one_id, user_two_id) VALUES (:user_one_id, :user_two_id)');
    $insertStmt->execute([
        'user_one_id' => $userOne,
        'user_two_id' => $userTwo,
    ]);

    return (int) $pdo->lastInsertId();
}

$targetUserId = (int) ($_GET['user_id'] ?? 0);
$activeChatId = (int) ($_GET['chat_id'] ?? 0);
$error = '';

if ($targetUserId > 0 && $targetUserId !== (int) $currentUser['id']) {
    $targetExistsStmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $targetExistsStmt->execute(['id' => $targetUserId]);

    if ($targetExistsStmt->fetchColumn()) {
        $chatId = getOrCreateChat($pdo, (int) $currentUser['id'], $targetUserId);
        header('Location: chat.php?chat_id=' . $chatId);
        exit;
    }

    $error = 'Пользователь не найден.';
}

if ($activeChatId > 0) {
    $membershipStmt = $pdo->prepare('SELECT id FROM chats WHERE id = :chat_id AND (user_one_id = :user_id OR user_two_id = :user_id) LIMIT 1');
    $membershipStmt->execute([
        'chat_id' => $activeChatId,
        'user_id' => $currentUser['id'],
    ]);

    if (!$membershipStmt->fetchColumn()) {
        $activeChatId = 0;
        $error = 'Диалог недоступен.';
    } else {
        $markReadStmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE chat_id = :chat_id AND sender_id != :user_id AND is_read = 0');
        $markReadStmt->execute([
            'chat_id' => $activeChatId,
            'user_id' => $currentUser['id'],
        ]);
    }
}

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
              AND unread.sender_id != :current_user_id
              AND unread.is_read = 0
        ) AS unread_count
    FROM chats
    INNER JOIN users AS partner ON partner.id = IF(chats.user_one_id = :current_user_id, chats.user_two_id, chats.user_one_id)
    LEFT JOIN messages AS latest ON latest.id = (
        SELECT m2.id
        FROM messages m2
        WHERE m2.chat_id = chats.id
        ORDER BY m2.created_at DESC, m2.id DESC
        LIMIT 1
    )
    WHERE chats.user_one_id = :current_user_id OR chats.user_two_id = :current_user_id
    ORDER BY COALESCE(latest.created_at, chats.created_at) DESC
');
$dialogsStmt->execute(['current_user_id' => $currentUser['id']]);
$dialogs = $dialogsStmt->fetchAll();

$unreadTotalStmt = $pdo->prepare('SELECT COUNT(*) FROM messages m INNER JOIN chats c ON c.id = m.chat_id WHERE (c.user_one_id = :user_id OR c.user_two_id = :user_id) AND m.sender_id != :user_id AND m.is_read = 0');
$unreadTotalStmt->execute(['user_id' => $currentUser['id']]);
$unreadTotal = (int) $unreadTotalStmt->fetchColumn();

$activeDialog = null;
foreach ($dialogs as $dialog) {
    if ((int) $dialog['id'] === $activeChatId) {
        $activeDialog = $dialog;
        break;
    }
}

if (!$activeDialog && !empty($dialogs)) {
    $activeDialog = $dialogs[0];
    $activeChatId = (int) $activeDialog['id'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snapix</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/chat.css">
</head>
<body data-page="chat" data-user-id="<?php echo (int) $currentUser['id']; ?>" data-active-chat-id="<?php echo (int) $activeChatId; ?>">
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">Snapix</a>
            <input type="text" class="search" placeholder="Поиск" disabled>
            <div class="menu">
                <a href="index.php">Лента</a>
                <a href="chat.php" class="notification-bell" aria-label="Открыть чаты">
                    <span class="notification-bell-icon">✉️</span>
                    <?php if ($unreadTotal > 0): ?><span class="notification-badge" id="header-chat-badge"><?php echo $unreadTotal; ?></span><?php else: ?><span class="notification-badge is-hidden" id="header-chat-badge">0</span><?php endif; ?>
                </a>
                <a href="profile.php" class="user-avatar-link" aria-label="Открыть профиль">
                    <?php if (!empty($currentUser['avatar'])): ?>
                        <span class="user-avatar" style="background-image: url('<?php echo htmlspecialchars($currentUser['avatar']); ?>');"></span>
                    <?php else: ?>
                        <span class="user-avatar"><?php echo htmlspecialchars(mb_substr($currentUser['login'], 0, 1)); ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </nav>
    </header>

    <main class="chat-page">
        <section class="chat-shell card-surface">
            <aside class="chat-dialogs">
                <h1>Сообщения</h1>
                <?php if ($error !== ''): ?><p class="chat-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
                <?php if (!$dialogs): ?>
                    <p class="chat-empty">Диалогов пока нет. Откройте профиль пользователя и начните чат.</p>
                <?php else: ?>
                    <div class="dialog-list" id="dialog-list">
                        <?php foreach ($dialogs as $dialog): ?>
                            <a href="chat.php?chat_id=<?php echo (int) $dialog['id']; ?>" class="dialog-item<?php echo (int) $dialog['id'] === $activeChatId ? ' is-active' : ''; ?>" data-chat-id="<?php echo (int) $dialog['id']; ?>">
                                <span class="dialog-avatar"<?php if (!empty($dialog['partner_avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($dialog['partner_avatar']); ?>');"<?php endif; ?>>
                                    <?php if (empty($dialog['partner_avatar'])): ?><?php echo htmlspecialchars(mb_substr($dialog['partner_login'], 0, 1)); ?><?php endif; ?>
                                </span>
                                <span class="dialog-content">
                                    <strong><?php echo htmlspecialchars($dialog['partner_login']); ?></strong>
                                    <small><?php echo htmlspecialchars($dialog['last_message'] ?? 'Нет сообщений'); ?></small>
                                </span>
                                <?php if ((int) $dialog['unread_count'] > 0): ?>
                                    <span class="dialog-unread"><?php echo (int) $dialog['unread_count']; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>

            <section class="chat-thread" id="chat-thread" data-chat-id="<?php echo (int) $activeChatId; ?>">
                <?php if (!$activeDialog): ?>
                    <div class="chat-placeholder">Выберите диалог слева.</div>
                <?php else: ?>
                    <div class="chat-thread-header">
                        <h2><?php echo htmlspecialchars($activeDialog['partner_login']); ?></h2>
                    </div>
                    <div class="chat-messages" id="chat-messages"></div>
                    <form class="chat-send-form" id="chat-send-form">
                        <input type="text" id="chat-message-input" maxlength="1000" placeholder="Введите сообщение" autocomplete="off">
                        <button type="submit">Отправить</button>
                    </form>
                <?php endif; ?>
            </section>
        </section>
    </main>

<script>
(function () {
    var activeChatId = Number(document.body.getAttribute('data-active-chat-id') || 0);
    var messageList = document.getElementById('chat-messages');
    var dialogList = document.getElementById('dialog-list');
    var sendForm = document.getElementById('chat-send-form');
    var messageInput = document.getElementById('chat-message-input');
    var badge = document.getElementById('header-chat-badge');

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function renderMessages(items) {
        if (!messageList) {
            return;
        }

        if (!items.length) {
            messageList.innerHTML = '<p class="chat-empty">Сообщений пока нет.</p>';
            return;
        }

        messageList.innerHTML = items.map(function (item) {
            var sideClass = item.is_mine ? 'is-mine' : 'is-theirs';
            var messageBody = escapeHtml(item.message_text);
            if (item.message_text.indexOf('[post_share]|') === 0) {
                var parts = item.message_text.split('|');
                var postId = Number(parts[1] || 0);
                var authorId = Number(parts[2] || 0);
                var authorLogin = parts[3] || '';
                if (postId > 0 && authorId > 0) {
                    messageBody = '<a class=\"chat-shared-post\" href=\"user.php?id=' + authorId + '#post-' + postId + '\">Публикация @' + escapeHtml(authorLogin) + ' #' + postId + '</a>';
                }
            }
            return '<div class="chat-message ' + sideClass + '">' +
                '<p>' + messageBody + '</p>' +
                '<time>' + escapeHtml(item.created_at_human) + '</time>' +
                '</div>';
        }).join('');

        messageList.scrollTop = messageList.scrollHeight;
    }

    function renderDialogs(items) {
        if (!dialogList) {
            return;
        }

        dialogList.innerHTML = items.map(function (dialog) {
            var avatar = dialog.partner_avatar
                ? '<span class="dialog-avatar" style="background-image: url(\'' + escapeHtml(dialog.partner_avatar) + '\');"></span>'
                : '<span class="dialog-avatar">' + escapeHtml(dialog.partner_login.slice(0, 1)) + '</span>';
            var unread = dialog.unread_count > 0 ? '<span class="dialog-unread">' + dialog.unread_count + '</span>' : '';
            var activeClass = Number(dialog.id) === activeChatId ? ' is-active' : '';

            return '<a href="chat.php?chat_id=' + Number(dialog.id) + '" class="dialog-item' + activeClass + '" data-chat-id="' + Number(dialog.id) + '">' +
                avatar +
                '<span class="dialog-content"><strong>' + escapeHtml(dialog.partner_login) + '</strong><small>' + escapeHtml(dialog.last_message || 'Нет сообщений') + '</small></span>' +
                unread +
                '</a>';
        }).join('');
    }

    function updateBadge(count) {
        if (!badge) {
            return;
        }

        badge.textContent = String(count);
        badge.classList.toggle('is-hidden', count <= 0);
    }

    function poll() {
        fetch('chat-api.php?action=poll&chat_id=' + activeChatId, { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok) {
                    return;
                }
                if (Array.isArray(data.messages)) {
                    renderMessages(data.messages);
                }
                if (Array.isArray(data.dialogs)) {
                    renderDialogs(data.dialogs);
                }
                updateBadge(Number(data.unread_total || 0));
            })
            .catch(function () {});
    }

    if (sendForm && messageInput) {
        sendForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var messageText = messageInput.value.trim();

            if (messageText === '' || !activeChatId) {
                return;
            }

            var body = new URLSearchParams();
            body.set('action', 'send');
            body.set('chat_id', String(activeChatId));
            body.set('message_text', messageText);

            fetch('chat-api.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body.toString()
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.ok) {
                        messageInput.value = '';
                        poll();
                    }
                })
                .catch(function () {});
        });
    }

    if (activeChatId > 0) {
        poll();
        setInterval(poll, 3000);
    }
})();
</script>
</body>
</html>
