<?php
session_start();
require './config/config.php';

$user = null;

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="index.css">
    <title>Snapix</title>
</head>
<body data-page="home">
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">Snapix</a>

            <input type="text" class="search" placeholder="Поиск">

            <div class="menu">
                <a href="#">Reels</a>
                <?php if ($user): ?>
                    <div class="home-profile-menu" data-home-menu>
                        <a href="profile.php" class="user-avatar-link" aria-label="Открыть профиль">
                            <?php if (!empty($user['avatar'])): ?>
                                <span class="user-avatar" style="background-image: url('<?php echo htmlspecialchars($user['avatar']); ?>');"></span>
                            <?php else: ?>
                                <span class="user-avatar"><?php echo htmlspecialchars(mb_substr($user['login'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </a>
                        <button class="home-menu-toggle" type="button" aria-expanded="false" aria-label="Открыть меню пользователя" data-home-menu-toggle>
                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M5.4 7.5a1 1 0 0 1 .7.3L10 11.6l3.9-3.8a1 1 0 0 1 1.4 1.4l-4.6 4.5a1 1 0 0 1-1.4 0L4.7 9.2a1 1 0 0 1 .7-1.7Z"/>
                            </svg>
                        </button>
                        <div class="home-menu-dropdown" data-home-menu-dropdown hidden>
                            <a href="logout.php" class="home-menu-link">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M10 4a1 1 0 0 1 1 1v2h-2V6H6v12h3v-1h2v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5Zm6.3 4.3 3 3a1 1 0 0 1 0 1.4l-3 3-1.4-1.4 1.3-1.3H10v-2h6.2l-1.3-1.3 1.4-1.4Z"/>
                                </svg>
                                <span>Выйти</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="auth-actions">
                        <a href="login.php">Войти</a>
                        <a href="register.php" class="auth">Регистрация</a>
                    </div>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <?php if ($user): ?>
        <script>
            const homeMenu = document.querySelector('[data-home-menu]');
            const menuToggle = document.querySelector('[data-home-menu-toggle]');
            const menuDropdown = document.querySelector('[data-home-menu-dropdown]');

            if (homeMenu && menuToggle && menuDropdown) {
                const closeMenu = () => {
                    menuDropdown.hidden = true;
                    menuToggle.setAttribute('aria-expanded', 'false');
                };

                const openMenu = () => {
                    menuDropdown.hidden = false;
                    menuToggle.setAttribute('aria-expanded', 'true');
                };

                menuToggle.addEventListener('click', () => {
                    if (menuDropdown.hidden) {
                        openMenu();
                    } else {
                        closeMenu();
                    }
                });

                document.addEventListener('click', (event) => {
                    if (!homeMenu.contains(event.target)) {
                        closeMenu();
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeMenu();
                    }
                });
            }
        </script>
    <?php endif; ?>

</body>
</html>