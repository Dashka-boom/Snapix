(function () {
    const THEME_KEY = 'snapix_theme';
    const LIGHT_THEME = 'light';
    const DARK_THEME = 'dark';

    const body = document.body;
    if (!body) return;

    const themeButton = document.querySelector('.side-menu-button');
    const themeButtonIcon = themeButton ? themeButton.querySelector('.side-menu-icon') : null;
    const themeButtonLabel = themeButton ? themeButton.querySelector('.side-menu-label') : null;

    function toLightIconPath(path) {
        if (!path || !path.includes('icon/dark theme/')) return path;
        return path.replace('icon/dark theme/', 'icon/light theme/');
    }

    function toDarkIconPath(path) {
        if (!path || !path.includes('icon/light theme/')) return path;
        return path.replace('icon/light theme/', 'icon/dark theme/');
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

            const darkIcon = icon.dataset.darkIcon;

            if (theme === LIGHT_THEME) {
                icon.setAttribute('src', toLightIconPath(darkIcon));
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
            themeButtonIcon.setAttribute(
                'src',
                isLight
                    ? 'icon/light theme/lighttheme.png'
                    : 'icon/dark theme/dark theme.png'
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