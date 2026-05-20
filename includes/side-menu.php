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

    $likesStmt = $pdo->prepare("\n        SELECT likes.id, likes.created_at, users.id AS user_id, users.login, users.avatar\n        FROM likes\n        INNER JOIN posts ON posts.id = likes.post_id\n        INNER JOIN users ON users.id = likes.user_id\n        WHERE posts.user_id = :user_id\n          AND posts.is_deleted = 0\n          AND likes.user_id <> :user_id\n        ORDER BY likes.created_at DESC\n        LIMIT 30\n    ");
    $likesStmt->execute(['user_id' => $userId]);
    $empty['likes'] = $likesStmt->fetchAll();

    $commentsStmt = $pdo->prepare("\n        SELECT comments.id, comments.comment_text, comments.created_at, users.id AS user_id, users.login, users.avatar\n        FROM comments\n        INNER JOIN posts ON posts.id = comments.post_id\n        INNER JOIN users ON users.id = comments.user_id\n        WHERE posts.user_id = :user_id\n          AND posts.is_deleted = 0\n          AND comments.is_deleted = 0\n          AND comments.user_id <> :user_id\n        ORDER BY comments.created_at DESC\n        LIMIT 30\n    ");
    $commentsStmt->execute(['user_id' => $userId]);
    $empty['comments'] = $commentsStmt->fetchAll();

    $repostsStmt = $pdo->prepare("\n        SELECT reposts.id, reposts.created_at, users.id AS user_id, users.login, users.avatar\n        FROM reposts\n        INNER JOIN posts ON posts.id = reposts.post_id\n        INNER JOIN users ON users.id = reposts.user_id\n        WHERE posts.user_id = :user_id\n          AND posts.is_deleted = 0\n          AND reposts.user_id <> :user_id\n        ORDER BY reposts.created_at DESC\n        LIMIT 30\n    ");
    $repostsStmt->execute(['user_id' => $userId]);
    $empty['reposts'] = $repostsStmt->fetchAll();

    $savedStmt = $pdo->prepare("\n        SELECT saved_posts.id, saved_posts.created_at, users.id AS user_id, users.login, users.avatar\n        FROM saved_posts\n        INNER JOIN posts ON posts.id = saved_posts.post_id\n        INNER JOIN users ON users.id = saved_posts.user_id\n        WHERE posts.user_id = :user_id\n          AND posts.is_deleted = 0\n          AND saved_posts.user_id <> :user_id\n        ORDER BY saved_posts.created_at DESC\n        LIMIT 30\n    ");
    $savedStmt->execute(['user_id' => $userId]);
    $empty['saved'] = $savedStmt->fetchAll();

    $complaintsStmt = $pdo->prepare("
        SELECT user_notifications.id, user_notifications.title, user_notifications.message, user_notifications.created_at
        FROM user_notifications
        WHERE user_notifications.user_id = :user_id
        ORDER BY user_notifications.created_at DESC
        LIMIT 30
    ");
    $complaintsStmt->execute(['user_id' => $userId]);
    $empty['complaints'] = $complaintsStmt->fetchAll();

    return $empty;
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

                <?php
                $simpleSections = [
                    'likes' => 'поставил(а) лайк вашей публикации',
                    'reposts' => 'сделал(а) репост вашей публикации',
                    'saved' => 'добавил(а) вашу публикацию в избранное',
                ];
                foreach ($simpleSections as $section => $text):
                ?>
                    <div class="notifications-drawer-panel" data-notification-panel="<?php echo htmlspecialchars($section, ENT_QUOTES); ?>">
                        <?php if ($notifications[$section]): ?>
                            <div class="notifications-drawer-list">
                                <?php foreach ($notifications[$section] as $item): ?>
                                    <article class="notifications-drawer-item">
                                        <?php snapix_side_render_avatar($item, $viewerId); ?>
                                        <div class="notifications-drawer-copy">
                                            <a href="<?php echo htmlspecialchars(snapix_side_profile_url((int) $item['user_id'], $viewerId)); ?>" class="notifications-drawer-login"><?php echo htmlspecialchars($item['login']); ?></a>
                                            <p><?php echo htmlspecialchars($text); ?>.</p>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="notifications-drawer-empty">Здесь пока нет уведомлений.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>


                <div class="notifications-drawer-panel" data-notification-panel="complaints">
                    <?php if ($notifications['complaints']): ?>
                        <div class="notifications-drawer-list">
                            <?php foreach ($notifications['complaints'] as $complaint): ?>
                                <article class="notifications-drawer-item">
                                    <div class="notifications-drawer-copy">
                                        <p class="notifications-drawer-title"><?php echo htmlspecialchars((string) ($complaint['title'] ?? 'Жалоба')); ?></p>
                                        <p><?php echo nl2br(htmlspecialchars((string) ($complaint['message'] ?? ''))); ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="notifications-drawer-empty">Жалоб пока нет.</p>
                    <?php endif; ?>
                </div>

                <div class="notifications-drawer-panel" data-notification-panel="comments">
                    <?php if ($notifications['comments']): ?>
                        <div class="notifications-drawer-list">
                            <?php foreach ($notifications['comments'] as $comment): ?>
                                <article class="notifications-drawer-item">
                                    <?php snapix_side_render_avatar($comment, $viewerId); ?>
                                    <div class="notifications-drawer-copy">
                                        <a href="<?php echo htmlspecialchars(snapix_side_profile_url((int) $comment['user_id'], $viewerId)); ?>" class="notifications-drawer-login"><?php echo htmlspecialchars($comment['login']); ?></a>
                                        <p><?php echo htmlspecialchars(mb_substr((string) $comment['comment_text'], 0, 120)); ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="notifications-drawer-empty">Новых комментариев пока нет.</p>
                    <?php endif; ?>
                </div>
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
                <img src="icon/logo.png" alt="" class="side-menu-icon">
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
            <a href="#" class="side-menu-item" aria-label="Поиск">
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
    <script src="js/notifications-drawer.js" defer></script>
    <?php
}
