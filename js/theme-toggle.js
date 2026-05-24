(function () {
    const THEME_KEY = 'snapix_theme';
    const LIGHT_THEME = 'light';
    const DARK_THEME = 'dark';

    const body = document.body;
    if (!body) return;

    const themeButton = document.querySelector('.side-menu-button[aria-label*="тема"], .side-menu-button[aria-label*="Тема"]');
    const themeButtonIcon = themeButton ? themeButton.querySelector('.side-menu-icon') : null;
    const themeButtonLabel = themeButton ? themeButton.querySelector('.side-menu-label') : null;
    const isAuthenticated = window.IS_AUTH === true;

    function toLightIconPath(path) {
        return path ? path.replace('icon/dark theme/', 'icon/light theme/') : path;
    }

    function toDarkIconPath(path) {
        return path ? path.replace('icon/light theme/', 'icon/dark theme/') : path;
    }

    function swapAllThemeIcons(theme) {
        const icons = document.querySelectorAll('img[src*="icon/dark theme/"], img[src*="icon/light theme/"]');

        icons.forEach((icon) => {
            const currentSrc = icon.getAttribute('src') || '';

            if (!icon.dataset.darkIcon) {
                icon.dataset.darkIcon = currentSrc.includes('icon/light theme/')
                    ? toDarkIconPath(currentSrc)
                    : currentSrc;
            }

            icon.setAttribute(
                'src',
                theme === LIGHT_THEME ? toLightIconPath(icon.dataset.darkIcon) : icon.dataset.darkIcon
            );
        });
    }

    function updateThemeButton(theme) {
        if (!themeButton) return;

        const isLight = theme === LIGHT_THEME;

        themeButton.setAttribute('aria-label', isLight ? 'Светлая тема' : 'Темная тема');

        if (themeButtonLabel) {
            themeButtonLabel.textContent = isLight ? 'Светлая тема' : 'Темная тема';
        }

        if (themeButtonIcon) {
            themeButtonIcon.setAttribute(
                'src',
                isLight ? 'icon/light theme/lighttheme.png' : 'icon/dark theme/dark theme.png'
            );
            themeButtonIcon.dataset.darkIcon = 'icon/dark theme/dark theme.png';
        }
    }

    function applyTheme(theme) {
        const nextTheme = theme === LIGHT_THEME ? LIGHT_THEME : DARK_THEME;
        const root = document.documentElement;

        body.setAttribute('data-theme', nextTheme);
        body.classList.toggle('theme-light', nextTheme === LIGHT_THEME);

        root.setAttribute('data-theme', nextTheme);
        root.classList.toggle('theme-light', nextTheme === LIGHT_THEME);

        swapAllThemeIcons(nextTheme);
        updateThemeButton(nextTheme);
    }

    function saveTheme(theme) {
        localStorage.setItem(THEME_KEY, theme);
    }

    if (!isAuthenticated) {
        applyTheme(DARK_THEME);
    } else {
        const savedTheme = localStorage.getItem(THEME_KEY);
        applyTheme(savedTheme === LIGHT_THEME ? LIGHT_THEME : DARK_THEME);
    }

    if (themeButton) {
        themeButton.addEventListener('click', function () {
            if (!isAuthenticated) {
                applyTheme(DARK_THEME);
                window.location.href = 'login.php';
                return;
            }

            const currentTheme = body.getAttribute('data-theme') === LIGHT_THEME ? LIGHT_THEME : DARK_THEME;
            const nextTheme = currentTheme === LIGHT_THEME ? DARK_THEME : LIGHT_THEME;

            applyTheme(nextTheme);
            saveTheme(nextTheme);
        });
    }
})();
