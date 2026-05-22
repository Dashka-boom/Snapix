(function () {
    const THEME_KEY = 'snapix_theme';
    const LIGHT_THEME = 'light';
    const DARK_THEME = 'dark';

    const body = document.body;
    if (!body) return;

    const themeButton = document.querySelector('.side-menu-button');
    const themeButtonIcon = themeButton ? themeButton.querySelector('.side-menu-icon') : null;
    const themeButtonLabel = themeButton ? themeButton.querySelector('.side-menu-label') : null;

    function mapLightIconPath(darkPath) {
        if (!darkPath || !darkPath.includes('icon/dark theme/')) return darkPath;
        return darkPath.replace('icon/dark theme/', 'icon/ligth theme/');
    }

    function swapSideMenuIcons(theme) {
        const sideMenuIcons = document.querySelectorAll('.side-menu .side-menu-icon');
        sideMenuIcons.forEach((icon) => {
            const currentSrc = icon.getAttribute('src') || '';
            if (!currentSrc.includes('icon/dark theme/') && !currentSrc.includes('icon/ligth theme/')) {
                return;
            }

            if (!icon.dataset.darkIcon) {
                if (currentSrc.includes('icon/dark theme/')) {
                    icon.dataset.darkIcon = currentSrc;
                } else {
                    icon.dataset.darkIcon = currentSrc.replace('icon/ligth theme/', 'icon/dark theme/');
                }
            }

            const darkIcon = icon.dataset.darkIcon;
            if (theme === LIGHT_THEME) {
                icon.setAttribute('src', mapLightIconPath(darkIcon));
            } else {
                icon.setAttribute('src', darkIcon);
            }
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
            themeButtonIcon.setAttribute('src', isLight ? 'icon/dark theme/lighttheme.png' : 'icon/dark theme/dark theme.png');
        }
    }

    function applyTheme(theme) {
        const nextTheme = theme === LIGHT_THEME ? LIGHT_THEME : DARK_THEME;
        body.setAttribute('data-theme', nextTheme);
        body.classList.toggle('theme-light', nextTheme === LIGHT_THEME);
        swapSideMenuIcons(nextTheme);
        updateThemeButton(nextTheme);
    }

    function saveTheme(theme) {
        localStorage.setItem(THEME_KEY, theme);
    }

    const savedTheme = localStorage.getItem(THEME_KEY);
    applyTheme(savedTheme === LIGHT_THEME ? LIGHT_THEME : DARK_THEME);

    if (themeButton) {
        themeButton.addEventListener('click', function () {
            const currentTheme = body.getAttribute('data-theme') === LIGHT_THEME ? LIGHT_THEME : DARK_THEME;
            const nextTheme = currentTheme === LIGHT_THEME ? DARK_THEME : LIGHT_THEME;
            applyTheme(nextTheme);
            saveTheme(nextTheme);
        });
    }
})();
