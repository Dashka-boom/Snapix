<?php
function snapix_side_profile_url(int $profileUserId, ?int $currentUserId): string
{
    if ($currentUserId !== null && $profileUserId === $currentUserId) {
        return 'profile.php';
    }

    return 'user.php?id=' . $profileUserId;
}

function snapix_side_fetch_notifications(?array $sideMenuUser): array
{
    global $pdo;

    $empty = [
        'requests' => [],
        'likes' => [],
        'comments' => [],
        'reposts' => [],
        'saved' => [],
        'complaints' => [],
    ];

    if (!$sideMenuUser || !isset($pdo)) {
        return $empty;
    }

    $userId = (int) ($sideMenuUser['id'] ?? 0);
    if ($userId <= 0) {
        return $empty;
    }

    $requestsStmt = $pdo->prepare("\n        SELECT followers.id, followers.created_at, users.id AS user_id, users.login, users.avatar\n        FROM followers\n        INNER JOIN users ON users.id = followers.follower_id\n        WHERE followers.following_id = :user_id AND followers.status = 'pending'\n        ORDER BY followers.created_at DESC\n        LIMIT 30\n    ");
    $requestsStmt->execute(['user_id' => $userId]);
    $empty['requests'] = $requestsStmt->fetchAll();

    $notificationStmt = $pdo->prepare("\n        SELECT\n            user_notifications.id,\n            user_notifications.actor_user_id,\n            user_notifications.notification_type,\n            user_notifications.post_id,\n            user_notifications.comment_id,\n            user_notifications.report_id,\n            user_notifications.title,\n            user_notifications.message,\n            user_notifications.comment_text,\n            user_notifications.report_reason,\n            user_notifications.created_at,\n            actor.id AS user_id,\n            actor.login,\n            actor.avatar,\n            moderation_reports.target_user_id AS reported_user_id,\n            reported_user.login AS reported_login\n        FROM user_notifications\n        LEFT JOIN users AS actor ON actor.id = user_notifications.actor_user_id\n        LEFT JOIN moderation_reports ON moderation_reports.id = user_notifications.report_id\n        LEFT JOIN users AS reported_user ON reported_user.id = moderation_reports.target_user_id\n        WHERE user_notifications.user_id = :user_id\n        ORDER BY user_notifications.created_at DESC, user_notifications.id DESC\n        LIMIT 120\n    ");
    $notificationStmt->execute(['user_id' => $userId]);

    $typeMap = [
        'post_like' => 'likes',
        'comment_like' => 'likes',
        'like' => 'likes',
        'likes' => 'likes',
        'post_comment' => 'comments',
        'comment_reply' => 'comments',
        'comment' => 'comments',
        'comments' => 'comments',
        'comment_deleted' => 'complaints',
        'comment_deleted_under_post' => 'complaints',
        'deleted_comment' => 'complaints',
        'post_repost' => 'reposts',
        'repost' => 'reposts',
        'reposts' => 'reposts',
        'post_saved' => 'saved',
        'saved' => 'saved',
        'favorite' => 'saved',
        'favourite' => 'saved',
        'post_forward' => 'reposts',
        'forward' => 'reposts',
        'report_post' => 'complaints',
        'report_comment' => 'complaints',
        'report_user' => 'complaints',
        'report' => 'complaints',
        'complaint' => 'complaints',
    ];
    $seenContext = [];

    foreach ($notificationStmt->fetchAll() as $notification) {
        $type = (string) ($notification['notification_type'] ?? '');
        $section = $typeMap[$type] ?? '';
        if ($section === '') {
            $legacyTitle = mb_strtolower((string) ($notification['title'] ?? ''));
            $legacyMessage = mb_strtolower((string) ($notification['message'] ?? ''));
            if (str_contains($legacyTitle . ' ' . $legacyMessage, 'коммент') && str_contains($legacyTitle . ' ' . $legacyMessage, 'удал')) {
                $section = 'complaints';
            } elseif (str_contains($legacyTitle . ' ' . $legacyMessage, 'коммент')) {
                $section = 'comments';
            } elseif (str_contains($legacyTitle . ' ' . $legacyMessage, 'лайк')) {
                $section = 'likes';
            } elseif (str_contains($legacyTitle . ' ' . $legacyMessage, 'репост')) {
                $section = 'reposts';
            } elseif (str_contains($legacyTitle . ' ' . $legacyMessage, 'избран')) {
                $section = 'saved';
            } else {
                $section = 'complaints';
            }
        }
        if (isset($empty[$section])) {
            $empty[$section][] = $notification;
        }

        $contextKey = snapix_side_notification_context_key($type, $notification);
        if ($contextKey !== '') {
            $seenContext[$contextKey] = true;
        }
    }

    snapix_side_append_legacy_activity_notifications($pdo, $empty, $userId, $seenContext);
    snapix_side_append_legacy_report_notifications($pdo, $empty, $userId);

    return $empty;
}



function snapix_side_notification_context_key(string $type, array $item): string
{
    $actorId = (int) ($item['actor_user_id'] ?? $item['user_id'] ?? 0);
    $postId = (int) ($item['post_id'] ?? 0);
    $commentId = (int) ($item['comment_id'] ?? 0);

    if ($actorId <= 0 || $postId <= 0 || $type === '') {
        return '';
    }

    if (in_array($type, ['post_comment', 'comment_reply', 'comment_like', 'comment'], true)) {
        return $type . ':' . $actorId . ':' . $postId . ':' . $commentId;
    }

    return $type . ':' . $actorId . ':' . $postId;
}

function snapix_side_append_legacy_activity_notifications(PDO $pdo, array &$notifications, int $userId, array $seenContext): void
{
    $legacyQueries = [
        'likes' => [
            'type' => 'post_like',
            'message' => 'поставил(а) лайк вашей публикации',
            'sql' => "
                SELECT likes.id, likes.post_id, likes.user_id AS actor_user_id, likes.created_at, users.id AS user_id, users.login, users.avatar
                FROM likes
                LEFT JOIN posts ON posts.id = likes.post_id
                LEFT JOIN users ON users.id = likes.user_id
                WHERE posts.user_id = :user_id
                  AND posts.is_deleted = 0
                  AND likes.user_id <> :user_id
                ORDER BY likes.created_at DESC
                LIMIT 30
            ",
        ],
        'comments' => [
            'type' => 'post_comment',
            'message' => 'прокомментировал(а) вашу публикацию',
            'sql' => "
                SELECT comments.id, comments.post_id, comments.id AS comment_id, comments.comment_text, comments.user_id AS actor_user_id, comments.created_at, users.id AS user_id, users.login, users.avatar
                FROM comments
                LEFT JOIN posts ON posts.id = comments.post_id
                LEFT JOIN users ON users.id = comments.user_id
                WHERE posts.user_id = :user_id
                  AND posts.is_deleted = 0
                  AND comments.is_deleted = 0
                  AND (comments.status = 'published' OR comments.status IS NULL)
                  AND comments.user_id <> :user_id
                ORDER BY comments.created_at DESC
                LIMIT 30
            ",
        ],
        'reposts' => [
            'type' => 'post_repost',
            'message' => 'сделал(а) репост вашей публикации',
            'sql' => "
                SELECT reposts.id, reposts.post_id, reposts.user_id AS actor_user_id, reposts.created_at, users.id AS user_id, users.login, users.avatar
                FROM reposts
                LEFT JOIN posts ON posts.id = reposts.post_id
                LEFT JOIN users ON users.id = reposts.user_id
                WHERE posts.user_id = :user_id
                  AND posts.is_deleted = 0
                  AND reposts.user_id <> :user_id
                ORDER BY reposts.created_at DESC
                LIMIT 30
            ",
        ],
        'saved' => [
            'type' => 'post_saved',
            'message' => 'добавил(а) вашу публикацию в избранное',
            'sql' => "
                SELECT saved_posts.id, saved_posts.post_id, saved_posts.user_id AS actor_user_id, saved_posts.created_at, users.id AS user_id, users.login, users.avatar
                FROM saved_posts
                LEFT JOIN posts ON posts.id = saved_posts.post_id
                LEFT JOIN users ON users.id = saved_posts.user_id
                WHERE posts.user_id = :user_id
                  AND posts.is_deleted = 0
                  AND saved_posts.user_id <> :user_id
                ORDER BY saved_posts.created_at DESC
                LIMIT 30
            ",
        ],
    ];

    foreach ($legacyQueries as $section => $legacyQuery) {
        $stmt = $pdo->prepare($legacyQuery['sql']);
        $stmt->execute(['user_id' => $userId]);

        foreach ($stmt->fetchAll() as $legacyItem) {
            $legacyItem['notification_type'] = $legacyQuery['type'];
            $legacyItem['message'] = '@' . (string) ($legacyItem['login'] ?? 'user') . ' ' . $legacyQuery['message'];
            if ($section === 'comments' && (string) ($legacyItem['comment_text'] ?? '') !== '') {
                $legacyItem['message'] .= ': "' . mb_substr((string) $legacyItem['comment_text'], 0, 120) . '"';
            }
            $legacyItem['title'] = 'Уведомление';
            $contextKey = snapix_side_notification_context_key($legacyQuery['type'], $legacyItem);
            if ($contextKey !== '' && isset($seenContext[$contextKey])) {
                continue;
            }

            $notifications[$section][] = $legacyItem;
        }
    }
}


function snapix_side_append_legacy_report_notifications(PDO $pdo, array &$notifications, int $userId): void
{
    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
    $roleStmt->execute(['id' => $userId]);
    $viewerRole = (string) ($roleStmt->fetchColumn() ?: 'user');
    if (!in_array($viewerRole, ['admin', 'moderator'], true)) {
        return;
    }

    $seenReportIds = [];
    foreach ($notifications['complaints'] as $complaint) {
        $reportId = (int) ($complaint['report_id'] ?? 0);
        if ($reportId > 0) {
            $seenReportIds[$reportId] = true;
        }
    }

    $reportsStmt = $pdo->prepare("
        SELECT
            moderation_reports.id AS report_id,
            moderation_reports.target_comment_id AS comment_id,
            moderation_reports.reason_text AS report_reason,
            moderation_reports.created_at,
            comments.comment_text,
            comments.post_id,
            reporter.id AS user_id,
            reporter.id AS actor_user_id,
            reporter.login,
            reporter.avatar,
            target.id AS reported_user_id,
            target.login AS reported_login
        FROM moderation_reports
        LEFT JOIN users AS reporter ON reporter.id = moderation_reports.reporter_user_id
        LEFT JOIN users AS target ON target.id = moderation_reports.target_user_id
        LEFT JOIN comments ON comments.id = moderation_reports.target_comment_id
        ORDER BY moderation_reports.created_at DESC, moderation_reports.id DESC
        LIMIT 60
    ");
    $reportsStmt->execute();

    foreach ($reportsStmt->fetchAll() as $report) {
        $reportId = (int) ($report['report_id'] ?? 0);
        if ($reportId > 0 && isset($seenReportIds[$reportId])) {
            continue;
        }

        $reason = (string) ($report['report_reason'] ?? '');
        $commentId = (int) ($report['comment_id'] ?? 0);
        $postId = (int) ($report['post_id'] ?? 0);
        if ($postId <= 0 && preg_match('/(?:пост|публикац[^#]*)\s*#\s*(\d+)/iu', $reason, $matches)) {
            $postId = (int) $matches[1];
        }

        $isUserReport = preg_match('/пользовател/u', mb_strtolower($reason)) === 1 && $commentId <= 0;
        if ($commentId > 0) {
            $report['notification_type'] = 'report_comment';
            $report['message'] = 'Поступила жалоба на комментарий под публикацией';
            $report['title'] = 'Жалоба на комментарий';
        } elseif ($isUserReport) {
            $report['notification_type'] = 'report_user';
            $report['message'] = 'Поступила жалоба на пользователя';
            $report['title'] = 'Жалоба на пользователя';
        } else {
            $report['notification_type'] = 'report_post';
            $report['message'] = 'Поступила жалоба на публикацию';
            $report['title'] = 'Жалоба на публикацию';
        }

        $report['id'] = 'legacy-report-' . $reportId;
        $report['post_id'] = $postId > 0 ? $postId : null;
        $report['report_reason'] = $reason;
        $notifications['complaints'][] = $report;
    }
}

function snapix_side_fetch_unread_moderation_notification(?array $sideMenuUser): ?array
{
    global $pdo;

    if (!$sideMenuUser || !isset($pdo)) {
        return null;
    }

    $userId = (int) ($sideMenuUser['id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT id, title, message, created_at
        FROM user_notifications
        WHERE user_id = :user_id
          AND is_read = 0
          AND title = 'Комментарий удалён'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute(['user_id' => $userId]);
    $notification = $stmt->fetch();

    return $notification ?: null;
}

function snapix_side_render_avatar(array $item, int $viewerId): void
{
    $login = (string) ($item['login'] ?? 'Пользователь');
    $avatar = (string) ($item['avatar'] ?? '');
    ?>
    <a href="<?php echo htmlspecialchars(snapix_side_profile_url((int) $item['user_id'], $viewerId), ENT_QUOTES); ?>" class="notifications-drawer-avatar" aria-label="Открыть профиль <?php echo htmlspecialchars($login, ENT_QUOTES); ?>"<?php if ($avatar !== ''): ?> style="background-image: url('<?php echo htmlspecialchars($avatar, ENT_QUOTES); ?>');"<?php endif; ?>>
        <?php if ($avatar === ''): ?><?php echo htmlspecialchars(mb_substr($login, 0, 1)); ?><?php endif; ?>
    </a>
    <?php
}


function snapix_side_notification_date(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d.m.Y H:i', $timestamp) : $value;
}

function snapix_side_render_notification_card(array $item, int $viewerId): void
{
    $actorId = (int) ($item['user_id'] ?? 0);
    $login = (string) ($item['login'] ?? 'Snapix');
    $avatar = (string) ($item['avatar'] ?? '');
    $postId = (int) ($item['post_id'] ?? 0);
    $message = (string) ($item['message'] ?? 'Уведомление');
    $commentText = trim((string) ($item['comment_text'] ?? ''));
    $reportReason = trim((string) ($item['report_reason'] ?? ''));
    $reportedUserId = (int) ($item['reported_user_id'] ?? 0);
    $reportedLogin = (string) ($item['reported_login'] ?? '');
    ?>
    <article class="notifications-drawer-item">
        <?php if ($actorId > 0): ?>
            <a href="<?php echo htmlspecialchars(snapix_side_profile_url($actorId, $viewerId), ENT_QUOTES); ?>" class="notifications-drawer-avatar" aria-label="Открыть профиль <?php echo htmlspecialchars($login, ENT_QUOTES); ?>"<?php if ($avatar !== ''): ?> style="background-image: url('<?php echo htmlspecialchars($avatar, ENT_QUOTES); ?>');"<?php endif; ?>>
                <?php if ($avatar === ''): ?><?php echo htmlspecialchars(mb_substr($login, 0, 1)); ?><?php endif; ?>
            </a>
        <?php else: ?>
            <span class="notifications-drawer-avatar notifications-drawer-avatar-system">S</span>
        <?php endif; ?>
        <div class="notifications-drawer-copy">
            <?php if ($actorId > 0): ?>
                <a href="<?php echo htmlspecialchars(snapix_side_profile_url($actorId, $viewerId), ENT_QUOTES); ?>" class="notifications-drawer-login">@<?php echo htmlspecialchars($login); ?></a>
            <?php else: ?>
                <p class="notifications-drawer-title"><?php echo htmlspecialchars((string) ($item['title'] ?? 'Уведомление')); ?></p>
            <?php endif; ?>
            <p><?php echo nl2br(htmlspecialchars($message)); ?></p>
            <?php if ($commentText !== ''): ?>
                <p class="notifications-drawer-context">Комментарий: “<?php echo htmlspecialchars(mb_substr($commentText, 0, 140)); ?><?php echo mb_strlen($commentText) > 140 ? '…' : ''; ?>”</p>
            <?php endif; ?>
            <?php if ($reportReason !== ''): ?>
                <p class="notifications-drawer-context">Причина: <?php echo htmlspecialchars($reportReason); ?></p>
            <?php endif; ?>
            <?php if ($reportedUserId > 0): ?>
                <a class="notifications-drawer-link" href="<?php echo htmlspecialchars(snapix_side_profile_url($reportedUserId, $viewerId), ENT_QUOTES); ?>">Открыть профиль<?php echo $reportedLogin !== '' ? ' @' . htmlspecialchars($reportedLogin) : ''; ?></a>
            <?php endif; ?>
            <?php if ($postId > 0): ?>
                <a class="notifications-drawer-link" href="post.php?id=<?php echo $postId; ?>">Открыть публикацию</a>
            <?php endif; ?>
            <?php if (!empty($item['created_at'])): ?>
                <time class="notifications-drawer-date" datetime="<?php echo htmlspecialchars((string) $item['created_at'], ENT_QUOTES); ?>"><?php echo htmlspecialchars(snapix_side_notification_date((string) $item['created_at'])); ?></time>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

function render_notifications_drawer(?array $sideMenuUser = null): void
{
    $viewerId = $sideMenuUser ? (int) ($sideMenuUser['id'] ?? 0) : 0;
    $notifications = snapix_side_fetch_notifications($sideMenuUser);
    $tabs = [
        'requests' => 'Заявки',
        'likes' => 'Лайки',
        'comments' => 'Комментарии',
        'reposts' => 'Репосты',
        'saved' => 'Избранные',
        'complaints' => 'Жалобы',
    ];
    ?>
    <section class="notifications-drawer" id="notificationsDrawer" aria-label="Уведомления" aria-hidden="true">
        <header class="notifications-drawer-header">
            <div>
                <h2>Уведомления</h2>
            </div>
            <button type="button" class="notifications-drawer-close" data-notifications-close aria-label="Закрыть уведомления">×</button>
        </header>

        <div class="notifications-drawer-tabs" role="tablist" aria-label="Разделы уведомлений">
            <?php foreach ($tabs as $key => $label): ?>
                <button type="button" class="notifications-drawer-tab<?php echo $key === 'requests' ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $key === 'requests' ? 'true' : 'false'; ?>" data-notification-tab="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label); ?></button>
            <?php endforeach; ?>
            <span class="notifications-drawer-tab-indicator" aria-hidden="true"></span>
        </div>

        <div class="notifications-drawer-body">
            <?php if (!$sideMenuUser): ?>
                <?php foreach (array_keys($tabs) as $index => $key): ?>
                    <div class="notifications-drawer-panel<?php echo $index === 0 ? ' is-active' : ''; ?>" data-notification-panel="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>">
                        <p class="notifications-drawer-empty">Войдите в аккаунт, чтобы увидеть уведомления.</p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="notifications-drawer-panel is-active" data-notification-panel="requests">
                    <?php if ($notifications['requests']): ?>
                        <div class="notifications-drawer-list">
                            <?php foreach ($notifications['requests'] as $request): ?>
                                <article class="notifications-drawer-item" data-request-card="<?php echo (int) $request['id']; ?>">
                                    <?php snapix_side_render_avatar($request, $viewerId); ?>
                                    <div class="notifications-drawer-copy">
                                        <a href="<?php echo htmlspecialchars(snapix_side_profile_url((int) $request['user_id'], $viewerId)); ?>" class="notifications-drawer-login"><?php echo htmlspecialchars($request['login']); ?></a>
                                        <p>Этот пользователь хочет подписаться на вас.</p>
                                        <div class="notifications-drawer-actions">
                                            <form method="post" action="notification-action.php" data-notification-request-form>
                                                <input type="hidden" name="action" value="accept_follow_request">
                                                <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                                <button type="submit" class="notifications-drawer-action">Принять</button>
                                            </form>
                                            <form method="post" action="notification-action.php" data-notification-request-form>
                                                <input type="hidden" name="action" value="decline_follow_request">
                                                <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                                <button type="submit" class="notifications-drawer-action">Отклонить</button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="notifications-drawer-empty">Новых заявок пока нет.</p>
                    <?php endif; ?>
                </div>

                <?php foreach (['likes', 'reposts', 'saved', 'complaints', 'comments'] as $section): ?>
                    <div class="notifications-drawer-panel" data-notification-panel="<?php echo htmlspecialchars($section, ENT_QUOTES); ?>">
                        <?php if ($notifications[$section]): ?>
                            <div class="notifications-drawer-list">
                                <?php foreach ($notifications[$section] as $item): ?>
                                    <?php snapix_side_render_notification_card($item, $viewerId); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="notifications-drawer-empty"><?php echo $section === 'complaints' ? 'Жалоб пока нет.' : 'Здесь пока нет уведомлений.'; ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <div class="notifications-drawer-backdrop" data-notifications-backdrop aria-hidden="true"></div>
    <?php
}

function render_side_menu(?array $sideMenuUser = null): void
{
    $profileUrl = $sideMenuUser ? 'profile.php' : 'login.php';
    $profileName = $sideMenuUser ? (string) ($sideMenuUser['login'] ?? 'Профиль') : 'Войти';
    $avatar = $sideMenuUser['avatar'] ?? '';
    $clipsUrl = $sideMenuUser ? 'clips.php' : 'login.php';
    $notificationsUrl = $sideMenuUser ? 'connections.php?view=requests' : 'login.php';
    ?>
    <aside class="side-menu" aria-label="Основное меню">
        <button type="button" class="side-menu-toggle" aria-label="Меню">
            <img src="icon/dark theme/menu.png" alt="" class="side-menu-icon">
            <span class="side-menu-label">Меню</span>
        </button>

        <nav class="side-menu-nav" aria-label="Навигация по сайту">
            <a href="index.php" class="side-menu-item" aria-label="Главная">
                <img src="icon/dark theme/logo.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Главная</span>
            </a>
            <a href="<?php echo htmlspecialchars($clipsUrl, ENT_QUOTES); ?>" class="side-menu-item" aria-label="Clips">
                <img src="icon/dark theme/Clips.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Clips</span>
            </a>
            <a href="<?php echo htmlspecialchars($notificationsUrl, ENT_QUOTES); ?>" class="side-menu-item" aria-label="Уведомления"<?php if ($sideMenuUser): ?> data-notifications-trigger aria-controls="notificationsDrawer" aria-expanded="false"<?php endif; ?>>
                <img src="icon/dark theme/notification.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Уведомления</span>
            </a>
            <a href="search.php" class="side-menu-item" aria-label="Поиск" data-search-trigger aria-controls="searchDrawer" aria-expanded="false">
                <img src="icon/dark theme/search.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Поиск</span>
            </a>
            <a href="chat.php" class="side-menu-item" aria-label="Чат">
                <img src="icon/dark theme/chat.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Чат</span>
            </a>
            <a href="profile.php" class="side-menu-item" aria-label="Закладки">
                <img src="icon/dark theme/favourites.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Закладки</span>
            </a>
            <button type="button" class="side-menu-item side-menu-button" aria-label="Темная тема">
                <img src="icon/dark theme/dark theme.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Темная тема</span>
            </button>
            <a href="#" class="side-menu-item" aria-label="Интересное">
                <img src="icon/dark theme/new.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Интересное</span>
            </a>
            <a href="edit-profile.php" class="side-menu-item" aria-label="Настройки">
                <img src="icon/dark theme/settings.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Настройки</span>
            </a>
        </nav>

        <a href="<?php echo htmlspecialchars($profileUrl, ENT_QUOTES); ?>" class="side-menu-profile" aria-label="Профиль пользователя">
            <?php if ($avatar !== ''): ?>
                <span class="side-menu-avatar" style="background-image: url('<?php echo htmlspecialchars((string) $avatar, ENT_QUOTES); ?>');"></span>
            <?php else: ?>
                <span class="side-menu-avatar"><?php echo htmlspecialchars(mb_substr($profileName, 0, 1)); ?></span>
            <?php endif; ?>
            <span class="side-menu-label side-menu-profile-name"><?php echo htmlspecialchars($profileName); ?></span>
        </a>
        <?php if ($sideMenuUser): ?>
            <button type="button" class="side-menu-account-toggle" aria-label="Открыть меню аккаунта">•••</button>
            <div class="side-menu-account-modal" role="dialog" aria-label="Меню аккаунта">
                <a href="login.php">Поменять аккаунт</a>
                <a href="logout.php">Выйти из учётной записи</a>
            </div>
        <?php endif; ?>
    </aside>
    <?php render_notifications_drawer($sideMenuUser); ?>
    <section class="search-drawer" id="searchDrawer" aria-label="Поиск" aria-hidden="true">
        <header class="search-drawer-header">
            <h2>Поиск</h2>
            <button type="button" class="search-drawer-close" data-search-close aria-label="Закрыть поиск">×</button>
        </header>

        <div class="search-drawer-input-wrap">
            <input type="search" class="search-drawer-input" placeholder="Поиск" aria-label="Поиск">
        </div>

        <div class="search-tabs" role="tablist" aria-label="Категории поиска">
            <button type="button" class="search-tab is-active" data-search-tab="users" role="tab" aria-selected="true">
                Пользователи
            </button>
            <button type="button" class="search-tab" data-search-tab="hashtags" role="tab" aria-selected="false">
                Хештеги
            </button>
        </div>

        <div class="search-results">
            <div class="search-results-panel is-active" data-search-panel="users"></div>
            <div class="search-results-panel" data-search-panel="hashtags"></div>
        </div>
    </section>
    <div class="search-drawer-backdrop" data-search-backdrop aria-hidden="true"></div>

    <?php $moderationAlert = snapix_side_fetch_unread_moderation_notification($sideMenuUser); ?>
    <?php if ($moderationAlert): ?>
        <div class="moderation-alert-backdrop is-open" data-moderation-alert-backdrop></div>
        <section class="moderation-alert-modal is-open" data-moderation-alert data-notification-id="<?php echo (int) $moderationAlert['id']; ?>" aria-label="Предупреждение модерации" role="dialog" aria-modal="true">
            <header class="moderation-alert-header">
                <h2><?php echo htmlspecialchars((string) ($moderationAlert['title'] ?? 'Комментарий удалён')); ?></h2>
            </header>
            <div class="moderation-alert-body">
                <p><?php echo nl2br(htmlspecialchars((string) ($moderationAlert['message'] ?? ''))); ?></p>
            </div>
            <button type="button" class="moderation-alert-action" data-moderation-alert-close>Понятно</button>
        </section>
    <?php endif; ?>
    <div class="report-modal-backdrop" data-report-modal>
        <section class="report-modal-dialog" role="dialog" aria-modal="true" aria-label="Пожаловаться">
            <button type="button" class="report-modal-overlay" data-report-modal-close aria-label="Закрыть"></button>
            <div class="report-modal-content">
                <button type="button" class="report-modal-close" data-report-modal-close aria-label="Закрыть">×</button>
                <div data-report-form>
                    <h2>Пожаловаться</h2>
                    <p>Почему вы хотите пожаловаться на этот контент?</p>
                    <label class="report-reason"><input type="radio" name="report_reason" value="Спам"><span>Спам</span></label>
                    <label class="report-reason"><input type="radio" name="report_reason" value="Оскорбления или ненависть"><span>Оскорбления или ненависть</span></label>
                    <label class="report-reason"><input type="radio" name="report_reason" value="Насилие"><span>Насилие</span></label>
                    <label class="report-reason"><input type="radio" name="report_reason" value="Ложная информация"><span>Ложная информация</span></label>
                    <label class="report-reason"><input type="radio" name="report_reason" value="Нежелательный контент"><span>Нежелательный контент</span></label>
                    <label class="report-reason"><input type="radio" name="report_reason" value="Нарушение авторских прав"><span>Нарушение авторских прав</span></label>
                    <label class="report-reason"><input type="radio" name="report_reason" value="Другое"><span>Другое</span></label>
                    <div class="report-custom-reason-wrap" data-report-custom-reason-wrap hidden>
                        <input type="text" class="report-custom-reason-input" data-report-custom-reason-input maxlength="1000" placeholder="Опишите причину">
                    </div>
                    <button type="button" class="report-submit-btn" data-report-submit disabled>Отправить</button>
                </div>
                <div data-report-success hidden>
                    <h1>Спасибо за обращение</h1>
                    <p>Мы рассмотрим вашу жалобу и примем соответствующие меры в случае обнаружения нарушения правил сообщества. Автор не получит уведомление о том, что вы пожаловались на его контент.</p>
                    <button type="button" class="report-block-btn" data-report-block><span class="report-block-dot" aria-hidden="true"></span><span>Заблокировать @user</span></button>
                </div>
            </div>
        </section>
    </div>

    <script>
        window.IS_AUTH = <?php echo $sideMenuUser ? 'true' : 'false'; ?>;
    </script>
    <script src="js/notifications-drawer.js" defer></script>
    <script src="js/search-drawer.js" defer></script>
    <script src="js/moderation-alert.js" defer></script>
    <script src="js/report-modal.js" defer></script>
    <script src="js/theme-toggle.js" defer></script>
    <?php
}
