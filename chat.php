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
    'chat_id' => $chatId,
    'user_id' => $userId,
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

$forwardRecipientsStmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.login, u.avatar
    FROM users u
    WHERE u.id != :user_id
    ORDER BY u.login ASC
");
$forwardRecipientsStmt->execute(['user_id' => $currentUser['id']]);
$forwardRecipients = $forwardRecipientsStmt->fetchAll();
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
                    <div class="chat-pinned" id="chat-pinned"></div>
                    <div class="chat-messages" id="chat-messages"></div>
                    <form class="chat-send-form" id="chat-send-form">
                        <div class="chat-reply-box is-hidden" id="chat-reply-box">
                            <div class="chat-reply-box-content">
                                <strong>Ответ на сообщение</strong>
                                <p id="chat-reply-text"></p>
                            </div>
                            <button type="button" class="chat-reply-close" id="chat-reply-close" aria-label="Отменить ответ">×</button>
                        </div>
                        <input type="text" id="chat-message-input" maxlength="1000" placeholder="Введите сообщение" autocomplete="off">
                        <button type="submit">Отправить</button>
                    </form>
                <?php endif; ?>
            </section>
        </section>
    </main>
<?php if ($activeDialog): ?>
<div class="chat-forward-modal is-hidden" id="chat-forward-modal">
    <div class="chat-forward-modal-card">
        <div class="chat-forward-head">
            <h3>Переслать сообщение</h3>
            <button type="button" id="chat-forward-close" aria-label="Закрыть">×</button>
        </div>
        <div class="chat-forward-list" id="chat-forward-list">
            <?php foreach ($forwardRecipients as $recipient): ?>
                <button
                    type="button"
                    class="chat-forward-user"
                    data-user-id="<?php echo (int) $recipient['id']; ?>"
                >
                    <span class="dialog-avatar"<?php if (!empty($recipient['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($recipient['avatar']); ?>');"<?php endif; ?>>
                        <?php if (empty($recipient['avatar'])): ?><?php echo htmlspecialchars(mb_substr($recipient['login'], 0, 1)); ?><?php endif; ?>
                    </span>
                    <span><?php echo htmlspecialchars($recipient['login']); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="chat-reaction-panel is-hidden" id="chat-reaction-panel"></div>
<?php endif; ?>

<script>
(function () {
    var activeChatId = Number(document.body.getAttribute('data-active-chat-id') || 0);
    var messageList = document.getElementById('chat-messages');
    var dialogList = document.getElementById('dialog-list');
    var sendForm = document.getElementById('chat-send-form');
    var messageInput = document.getElementById('chat-message-input');
    var pinnedBox = document.getElementById('chat-pinned');
    var replyBox = document.getElementById('chat-reply-box');
    var replyText = document.getElementById('chat-reply-text');
    var replyClose = document.getElementById('chat-reply-close');
    var forwardModal = document.getElementById('chat-forward-modal');
    var forwardClose = document.getElementById('chat-forward-close');
    var forwardList = document.getElementById('chat-forward-list');
    var badge = document.getElementById('header-chat-badge');
    var reactionPanel = document.getElementById('chat-reaction-panel');
    var lastError = '';
    var replyingToMessage = null;
    var forwardingMessageId = 0;
    var socket = null;
    var socketReady = false;
    var socketConnecting = false;
    var socketUrlIndex = 0;
    var socketUrls = ['ws://' + window.location.hostname + ':8090'];
    var fallbackTimeoutId = null;
    var reactionEmojis = ['❤️', '😂', '👍', '🔥', '😢', '😮'];
    var activeReactionMessageId = 0;
    var lastMessageRenderHash = '';


    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                '\'': '&#039;'
            }[char] || char;
        });
    }

    function truncateForQuote(text) {
        return text.length > 120 ? text.slice(0, 117) + '...' : text;
    }

    function getMessagePreview(item) {
        if (item.shared_post) {
            return 'Публикация';
        }
        if (item.deleted_for_all) {
            return 'Сообщение удалено';
        }
        return truncateForQuote(item.message_text || '');
    }

    function renderPinned(pinnedItems) {
        if (!pinnedBox) {
            return;
        }
        if (!Array.isArray(pinnedItems) || !pinnedItems.length) {
            pinnedBox.classList.add('is-hidden');
            pinnedBox.innerHTML = '';
            return;
        }
        pinnedBox.classList.remove('is-hidden');
        pinnedBox.innerHTML = pinnedItems.map(function (item) {
            return '<button type="button" class="chat-pinned-item" data-scroll-message-id="' + Number(item.id) + '">' +
                '<span>📌</span><span>' + escapeHtml(getMessagePreview(item)) + '</span>' +
                '</button>';
        }).join('');
    }

    function buildMessageRowHtml(item) {
            var sideClass = item.is_mine ? 'is-mine' : 'is-theirs';
            var messageBody = escapeHtml(item.message_text);
            if (messageBody.indexOf('[post_share]|') === 0) {
                messageBody = 'Пересланная публикация недоступна';
            }
            var bodyHtml = '<p>' + messageBody + '</p>';
            if (item.shared_post) {
                var post = item.shared_post;
                var mediaHtml = '';
                if (post.media_url) {
                    if (post.media_type === 'video') {
                        mediaHtml = '<video class=\"chat-shared-media\" src=\"' + escapeHtml(post.media_url) + '\" muted playsinline></video>';
                    } else {
                        mediaHtml = '<img class=\"chat-shared-media\" src=\"' + escapeHtml(post.media_url) + '\" alt=\"Публикация\">';
                    }
                }
                var caption = post.caption ? '<p class=\"chat-shared-caption\">' + escapeHtml(post.caption) + '</p>' : '';
                var authorLogin = post.author_login || '?';
                var avatarHtml = post.author_avatar
                    ? '<span class="chat-shared-avatar" style="background-image: url(\'' + escapeHtml(post.author_avatar) + '\');"></span>'
                    : '<span class=\"chat-shared-avatar\">' + escapeHtml(authorLogin.slice(0, 1)) + '</span>';
                messageBody = '' +
                    '<a class=\"chat-shared-card\" href=\"' + escapeHtml(post.post_url) + '\">' +
                        '<span class=\"chat-shared-head\">' +
                            avatarHtml +
                            '<strong class=\"chat-shared-author\">' + escapeHtml(authorLogin) + '</strong>' +
                        '</span>' +
                        mediaHtml +
                        caption +
                    '</a>';
                bodyHtml = '<div class=\"chat-message-body chat-message-body-card\">' + messageBody + '</div>';
            }
            var replyHtml = '';
            if (item.reply_to) {
                replyHtml = '<button type="button" class="chat-reply-chip" data-scroll-message-id="' + Number(item.reply_to.id) + '">' +
                    '<small>Ответ</small><span>' + escapeHtml(getMessagePreview(item.reply_to)) + '</span></button>';
            }

            var forwardedHtml = item.forwarded_from
                ? '<div class="chat-forwarded-label">Переслано</div>'
                : '';
            var editedHtml = item.is_edited ? '<em class="chat-edited-label">изменено</em>' : '';
            var reactionTrigger = '<button type="button" class="chat-reaction-trigger" data-message-id="' + Number(item.id) + '" aria-label="Выбрать реакцию">🙂</button>';
            var menuButton = '<button type="button" class="chat-message-menu-trigger" data-message-id="' + Number(item.id) + '" aria-label="Действия с сообщением">⋯</button>';
            var menuHtml = '<div class="chat-message-menu" data-menu-for="' + Number(item.id) + '"></div>';
            var actionsHtml = '<div class="chat-message-actions">' + reactionTrigger + menuButton + '</div>';

            var reactionsHtml = '<div class="chat-message-reactions" data-reactions-for="' + Number(item.id) + '"></div>';
            return '<div class="chat-message-row ' + sideClass + '" data-message-row-id="' + Number(item.id) + '">' +
                actionsHtml + menuHtml +
                '<div class="chat-message ' + sideClass + '" data-message-id="' + Number(item.id) + '">' +
                replyHtml + forwardedHtml + bodyHtml +
                '<time>' + escapeHtml(item.created_at_human) + ' ' + editedHtml + '</time>' + reactionsHtml +
                '</div>' +
                '</div>';
    }

    function appendMessage(item) {
        if (!messageList || !item || Number(item.id || 0) <= 0) {
            return;
        }
        if (document.querySelector('.chat-message[data-message-id="' + Number(item.id) + '"]')) {
            return;
        }
        if (messageList.querySelector('.chat-empty')) {
            messageList.innerHTML = '';
        }
        messageList.insertAdjacentHTML('beforeend', buildMessageRowHtml(item));
        renderReactionBadges(item);
        messageList.scrollTop = messageList.scrollHeight;
    }

    function renderMessages(items) {
        if (!messageList) {
            return;
        }
        if (!items.length) {
            messageList.innerHTML = '<p class="chat-empty">Сообщений пока нет.</p>';
            return;
        }
        messageList.innerHTML = items.map(buildMessageRowHtml).join('');

        updateReactionsFromPayload(items);
        messageList.scrollTop = messageList.scrollHeight;
    }

    function renderReactionBadges(message) {
        var box = document.querySelector('[data-reactions-for="' + Number(message.id) + '"]');
        if (!box) {
            return;
        }
        var reactions = Array.isArray(message.reactions) ? message.reactions : [];
        if (!reactions.length) {
            box.innerHTML = '';
            return;
        }
        box.innerHTML = reactions.map(function (item) {
            var activeClass = message.my_reaction === item.reaction ? ' is-active' : '';
            return '<button type="button" class="chat-reaction-badge' + activeClass + '" data-react-message-id="' + Number(message.id) + '" data-reaction="' + escapeHtml(item.reaction) + '">' +
                '<span>' + escapeHtml(item.reaction) + '</span><span>' + Number(item.count) + '</span></button>';
        }).join('');
    }

    function updateReactionsFromPayload(items) {
        (items || []).forEach(function (item) {
            renderReactionBadges(item);
        });
    }

    function buildMessageRenderHash(items) {
        return (items || []).map(function (item) {
            return [item.id, item.message_text, item.deleted_for_all ? 1 : 0, item.is_edited ? 1 : 0, item.reply_to_message_id, item.forwarded_from_message_id].join(':');
        }).join('|');
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

    function refreshChatState() {
        fetch('chat-api.php?action=poll&chat_id=' + activeChatId, { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok) {
                    lastError = data.error || 'poll_failed';
                    console.error('Chat poll error:', data);
                    return;
                }
                if (Array.isArray(data.messages)) {
                    var nextHash = buildMessageRenderHash(data.messages);
                    if (nextHash !== lastMessageRenderHash) {
                        lastMessageRenderHash = nextHash;
                        renderMessages(data.messages);
                    } else {
                        updateReactionsFromPayload(data.messages);
                    }
                }
                renderPinned(data.pinned_messages || []);
                if (Array.isArray(data.dialogs)) {
                    renderDialogs(data.dialogs);
                    if (activeChatId <= 0 && data.dialogs.length > 0) {
                        activeChatId = Number(data.dialogs[0].id || 0);
                    }
                }
                updateBadge(Number(data.unread_total || 0));
            })
            .catch(function (error) {
                lastError = 'poll_request_failed';
                console.error('Chat poll request failed:', error);
            });
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
            if (replyingToMessage) {
                body.set('reply_to_message_id', String(replyingToMessage.id));
            }

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
                console.log('SEND RESPONSE:', data);
                    if (data.ok) {
                        messageInput.value = '';
                        clearReply();
                        if (Number(data.message_id || 0) > 0) {
                            notifySocketAboutNewMessage(Number(data.message_id));
                        } else {
                            refreshChatState();
                        }
                    } else {
                        lastError = data.error || 'send_failed';
                        console.error('Chat send error:', data);
                    }
                })
                .catch(function (error) {
                    lastError = 'send_request_failed';
                    console.error('Chat send request failed:', error);
                });
        });
    }

    function clearReply() {
        replyingToMessage = null;
        if (!replyBox) {
            return;
        }
        replyBox.classList.add('is-hidden');
        if (replyText) {
            replyText.textContent = '';
        }
    }

    function showReactionPanel(messageId, anchorElement) {
        if (!reactionPanel || !anchorElement) {
            return;
        }
        activeReactionMessageId = Number(messageId);
        reactionPanel.innerHTML = reactionEmojis.map(function (emoji) {
            return '<button type="button" class="chat-reaction-option" data-panel-reaction="' + emoji + '">' + emoji + '</button>';
        }).join('') + '<button type="button" class="chat-reaction-option chat-reaction-option-more" disabled>+</button>';

        var rect = anchorElement.getBoundingClientRect();
        reactionPanel.classList.remove('is-hidden', 'is-bottom');
        reactionPanel.style.left = Math.max(8, rect.left + (rect.width / 2) - 118) + 'px';
        reactionPanel.style.top = (rect.top - 50) + 'px';
        if (rect.top < 60) {
            reactionPanel.classList.add('is-bottom');
            reactionPanel.style.top = (rect.bottom + 8) + 'px';
        }
    }

    function hideReactionPanel() {
        activeReactionMessageId = 0;
        if (reactionPanel) {
            reactionPanel.classList.add('is-hidden');
        }
    }

    function animateReaction(messageId, emoji) {
        var messageNode = document.querySelector('.chat-message[data-message-id="' + Number(messageId) + '"]');
        if (!messageNode) {
            return;
        }
        var blast = document.createElement('span');
        blast.className = 'chat-reaction-burst';
        blast.textContent = emoji;
        messageNode.appendChild(blast);
        setTimeout(function () {
            blast.remove();
        }, 650);
    }

    if (replyClose) {
        replyClose.addEventListener('click', clearReply);
    }

    function openMenu(trigger, message) {
        closeMenus();
        var menu = document.querySelector('[data-menu-for="' + message.id + '"]');
        if (!menu) {
            return;
        }
        var canEdit = !!message.is_mine && !message.forwarded_from && !message.deleted_for_all;
        var items = [];
        if (canEdit) {
            items.push({ action: 'edit', label: 'Редактировать', icon: '✏️' });
        }
        items.push({ action: 'delete', label: 'Удалить', icon: '🗑️' });
        items.push({ action: 'pin', label: 'Закрепить', icon: '📌' });
        items.push({ action: 'reply', label: 'Ответить', icon: '↩️' });
        items.push({ action: 'forward', label: 'Переслать', icon: '➡️' });
        items.push({ action: 'copy', label: 'Копировать', icon: '📋' });

        menu.innerHTML = items.map(function (item) {
            return '<button type="button" class="chat-message-menu-item" data-action="' + item.action + '" data-message-id="' + Number(message.id) + '">' +
                '<span>' + escapeHtml(item.label) + '</span><span>' + item.icon + '</span></button>';
        }).join('');
        menu.classList.add('is-open');
    }

    function closeMenus() {
        Array.prototype.forEach.call(document.querySelectorAll('.chat-message-menu.is-open'), function (menu) {
            menu.classList.remove('is-open');
            menu.innerHTML = '';
        });
    }

    function getMessageById(id) {
        var row = document.querySelector('.chat-message[data-message-id="' + Number(id) + '"]');
        if (!row) {
            return null;
        }
        return {
            id: Number(id),
            is_mine: row.classList.contains('is-mine')
        };
    }

    function sendMessageAction(action, messageId, extra) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('chat_id', String(activeChatId));
        body.set('message_id', String(messageId));
        Object.keys(extra || {}).forEach(function (key) {
            body.set(key, String(extra[key]));
        });
        return fetch('chat-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (response) { return response.json(); });
    }

    document.addEventListener('click', function (event) {
        var reactionTrigger = event.target.closest('.chat-reaction-trigger');
        if (reactionTrigger) {
            var reactionMessageIdFromIcon = Number(reactionTrigger.getAttribute('data-message-id'));
            if (reactionMessageIdFromIcon > 0) {
                showReactionPanel(reactionMessageIdFromIcon, reactionTrigger);
            }
            return;
        }

        var menuTrigger = event.target.closest('.chat-message-menu-trigger');
        if (menuTrigger) {
            var messageId = Number(menuTrigger.getAttribute('data-message-id'));
            fetch('chat-api.php?action=poll&chat_id=' + activeChatId, { credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    var message = (data.messages || []).find(function (item) { return Number(item.id) === messageId; });
                    if (message) {
                        openMenu(menuTrigger, message);
                    }
                });
            return;
        }

        var menuItem = event.target.closest('.chat-message-menu-item');
        if (menuItem) {
            var messageId = Number(menuItem.getAttribute('data-message-id'));
            var action = menuItem.getAttribute('data-action');
            if (action === 'copy') {
                var messageNode = document.querySelector('.chat-message[data-message-id="' + messageId + '"] p');
                var text = messageNode ? messageNode.textContent : '';
                navigator.clipboard.writeText(text || '');
                closeMenus();
                return;
            }
            if (action === 'reply') {
                var replyNode = document.querySelector('.chat-message[data-message-id="' + messageId + '"] p');
                replyingToMessage = { id: messageId, text: replyNode ? replyNode.textContent : '' };
                if (replyBox && replyText) {
                    replyText.textContent = truncateForQuote(replyingToMessage.text);
                    replyBox.classList.remove('is-hidden');
                }
                closeMenus();
                return;
            }
            if (action === 'edit') {
                var newText = prompt('Изменить сообщение:');
                if (!newText) {
                    closeMenus();
                    return;
                }
                sendMessageAction('edit', messageId, { message_text: newText.trim() }).then(refreshChatState);
                closeMenus();
                return;
            }
            if (action === 'delete') {
                var mode = confirm('Удалить у всех? Нажмите "Отмена", чтобы удалить только у себя.') ? 'all' : 'self';
                sendMessageAction('delete', messageId, { delete_mode: mode }).then(refreshChatState);
                closeMenus();
                return;
            }
            if (action === 'pin') {
                sendMessageAction('pin', messageId, {}).then(refreshChatState);
                closeMenus();
                return;
            }
            if (action === 'forward') {
                forwardingMessageId = messageId;
                if (forwardModal) {
                    forwardModal.classList.remove('is-hidden');
                }
                closeMenus();
                return;
            }
        }

        var reactionBadge = event.target.closest('.chat-reaction-badge');
        if (reactionBadge) {
            var reactionMessageId = Number(reactionBadge.getAttribute('data-react-message-id'));
            var reaction = reactionBadge.getAttribute('data-reaction');
            sendMessageAction('react', reactionMessageId, { reaction: reaction }).then(function () {
                animateReaction(reactionMessageId, reaction);
                refreshChatState();
            });
            return;
        }

        var panelReaction = event.target.closest('[data-panel-reaction]');
        if (panelReaction && activeReactionMessageId > 0) {
            var panelEmoji = panelReaction.getAttribute('data-panel-reaction');
            sendMessageAction('react', activeReactionMessageId, { reaction: panelEmoji }).then(function () {
                animateReaction(activeReactionMessageId, panelEmoji);
                refreshChatState();
            });
            hideReactionPanel();
            return;
        }

        var scrollTo = event.target.closest('[data-scroll-message-id]');
        if (scrollTo) {
            var targetId = Number(scrollTo.getAttribute('data-scroll-message-id'));
            var target = document.querySelector('.chat-message[data-message-id="' + targetId + '"]');
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target.classList.add('is-highlighted');
                setTimeout(function () { target.classList.remove('is-highlighted'); }, 1200);
            }
            return;
        }

        if (!event.target.closest('.chat-message-menu')) {
            closeMenus();
        }
        if (!event.target.closest('.chat-reaction-panel')) {
            hideReactionPanel();
        }
    });

    if (forwardClose) {
        forwardClose.addEventListener('click', function () {
            forwardingMessageId = 0;
            forwardModal.classList.add('is-hidden');
        });
    }

    if (forwardList) {
        forwardList.addEventListener('click', function (event) {
            var userButton = event.target.closest('.chat-forward-user');
            if (!userButton || !forwardingMessageId) {
                return;
            }
            var recipientId = Number(userButton.getAttribute('data-user-id'));
            sendMessageAction('forward', forwardingMessageId, { receiver_id: recipientId }).then(function () {
                forwardingMessageId = 0;
                if (forwardModal) {
                    forwardModal.classList.add('is-hidden');
                }
                refreshChatState();
            });
        });
    }

    function startFallbackPolling() {
        if (fallbackTimeoutId !== null) {
            return;
        }
        fallbackTimeoutId = window.setTimeout(function () {
            fallbackTimeoutId = null;
            refreshChatState();
            startFallbackPolling();
        }, 3000);
    }

    function stopFallbackPolling() {
        if (fallbackTimeoutId === null) {
            return;
        }
        clearTimeout(fallbackTimeoutId);
        fallbackTimeoutId = null;
    }

 function notifySocketAboutNewMessage(messageId) {
    console.log('WS TRY SEND', messageId, socket, socket?.readyState);

    if (!activeChatId) return;

    var payload = JSON.stringify({
        type: 'new_message',
        chat_id: activeChatId,
        message_id: Number(messageId || 0)
    });

    if (socket && socket.readyState === WebSocket.OPEN) {
        console.log('WS SENT');
        socket.send(payload);
    } else {
        console.log('WS NOT READY');
    }
}

    function initWebSocket() {
        if (!window.WebSocket || !activeChatId) {
            startFallbackPolling();
            return;
        }

        socketConnecting = true;
        connectWebSocketByIndex(0);
    }

    function connectWebSocketByIndex(index) {
        if (!window.WebSocket || !activeChatId) {
            socketConnecting = false;
            startFallbackPolling();
            return;
        }

        if (index >= socketUrls.length) {
            socketConnecting = false;
            startFallbackPolling();
            return;
        }

        socketUrlIndex = index;
        var wsUrl = socketUrls[index];
        var opened = false;

        try {
            socket = new WebSocket(wsUrl);
        } catch (error) {
            console.error('WebSocket init failed (' + wsUrl + '):', error);
            connectWebSocketByIndex(index + 1);
            return;
        }

        socket.addEventListener('open', function () {
            console.log('WS OPEN');
            opened = true;
            socketConnecting = false;
            socketReady = true;
            stopFallbackPolling();

            socket.send(JSON.stringify({
                type: 'auth',
                user_id: Number(document.body.getAttribute('data-user-id') || 0),
                chat_id: activeChatId
            }));
        });

        socket.addEventListener('message', function (event) {
            var data = null;

            try {
                data = JSON.parse(event.data);
            } catch (error) {
                console.error('Invalid WebSocket message:', event.data, error);
                return;
            }

            if (data.type === 'new_message' && Number(data.chat_id) === activeChatId && data.message) {
                appendMessage(data.message);
                return;
            }

            if (data.type === 'error') {
                console.error('WebSocket server error:', data.error);
            }
        });

        socket.addEventListener('close', function () {
            socketReady = false;
            if (socketConnecting || !opened) {
                connectWebSocketByIndex(socketUrlIndex + 1);
                return;
            }
            startFallbackPolling();
        });

        socket.addEventListener('error', function (error) {
            socketReady = false;
            console.error('WebSocket error (' + wsUrl + '):', error);
            if (!opened) {
                connectWebSocketByIndex(socketUrlIndex + 1);
                return;
            }
            startFallbackPolling();
        });
    }

    refreshChatState();
    initWebSocket();
})();
</script>
</body>
</html>
