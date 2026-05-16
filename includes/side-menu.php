<?php
function render_side_menu(?array $sideMenuUser = null): void
{
    $profileUrl = $sideMenuUser ? 'profile.php' : 'login.php';
    $profileName = $sideMenuUser ? (string) ($sideMenuUser['login'] ?? 'Профиль') : 'Войти';
    $avatar = $sideMenuUser['avatar'] ?? '';
    ?>
    <aside class="side-menu" aria-label="Основное меню">
        <button type="button" class="side-menu-toggle" aria-label="Меню">
            <img src="icon/menu.png" alt="" class="side-menu-icon">
            <span class="side-menu-label">Меню</span>
        </button>

        <nav class="side-menu-nav" aria-label="Навигация по сайту">
            <a href="index.php" class="side-menu-item" aria-label="Главная">
                <img src="icon/logo.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Главная</span>
            </a>
            <a href="#" class="side-menu-item" aria-label="Clips">
                <img src="icon/Group.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Clips</span>
            </a>
            <a href="connections.php?view=requests" class="side-menu-item" aria-label="Уведомления">
                <img src="icon/notification.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Уведомления</span>
            </a>
            <a href="#" class="side-menu-item" aria-label="Поиск">
                <img src="icon/search.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Поиск</span>
            </a>
            <a href="chat.php" class="side-menu-item" aria-label="Чат">
                <img src="icon/chat.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Чат</span>
            </a>
            <a href="profile.php" class="side-menu-item" aria-label="Закладки">
                <img src="icon/favourites.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Закладки</span>
            </a>
            <button type="button" class="side-menu-item side-menu-button" aria-label="Темная тема">
                <img src="icon/dark theme.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Темная тема</span>
            </button>
            <a href="#" class="side-menu-item" aria-label="Интересное">
                <img src="icon/new.png" alt="" class="side-menu-icon">
                <span class="side-menu-label">Интересное</span>
            </a>
            <a href="edit-profile.php" class="side-menu-item" aria-label="Настройки">
                <img src="icon/settting.png" alt="" class="side-menu-icon">
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
        <button type="button" class="side-menu-account-toggle" aria-label="Открыть меню аккаунта">•••</button>
        <div class="side-menu-account-modal" role="dialog" aria-label="Меню аккаунта">
            <a href="login.php">Поменять аккаунт</a>
            <a href="logout.php">Выйти из учётной записи</a>
        </div>
    </aside>
    <?php
}
