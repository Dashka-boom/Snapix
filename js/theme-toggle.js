(function () {
    const THEME_KEY = 'snapix_theme';
    const LIGHT_THEME = 'light';
    const DARK_THEME = 'dark';
    const DARK_PATH = 'icon/dark theme/';
    const LIGHT_PATH = 'icon/light theme/';

    const body = document.body;
    if (!body) return;

    const themeButton = document.querySelector('.side-menu-button');
    const themeButtonIcon = themeButton ? themeButton.querySelector('.side-menu-icon') : null;
    const themeButtonLabel = themeButton ? themeButton.querySelector('.side-menu-label') : null;

    function mapLightIconPath(darkPath) {
        if (!darkPath || !darkPath.includes(DARK_PATH)) return darkPath;
        return darkPath.replace(DARK_PATH, LIGHT_PATH);
    }

    function getDarkIconPath(src) {
        if (!src) return src;
        if (src.includes(DARK_PATH)) return src;
        if (src.includes(LIGHT_PATH)) return src.replace(LIGHT_PATH, DARK_PATH);
        return src;
    }

    function swapAllThemeIcons(theme) {
        const icons = document.querySelectorAll('img[src*="icon/dark theme/"], img[src*="icon/light theme/"]');

        icons.forEach((icon) => {
            const currentSrc = icon.getAttribute('src') || '';
            if (!currentSrc.includes(DARK_PATH) && !currentSrc.includes(LIGHT_PATH)) {
                return;
            }

            if (!icon.dataset.darkIcon) {
                icon.dataset.darkIcon = getDarkIconPath(currentSrc);
            }

            const darkIcon = icon.dataset.darkIcon;
            icon.setAttribute('src', theme === LIGHT_THEME ? mapLightIconPath(darkIcon) : darkIcon);
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
            themeButtonIcon.setAttribute('src', isLight ? 'icon/light theme/lighttheme.png' : 'icon/dark theme/dark theme.png');
        }
    }

    function applyTheme(theme) {
        const nextTheme = theme === LIGHT_THEME ? LIGHT_THEME : DARK_THEME;
        body.setAttribute('data-theme', nextTheme);
        body.classList.toggle('theme-light', nextTheme === LIGHT_THEME);
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

    const observer = new MutationObserver(function (mutations) {
        const theme = body.getAttribute('data-theme') === LIGHT_THEME ? LIGHT_THEME : DARK_THEME;
        for (const mutation of mutations) {
            if (mutation.type === 'childList' && (mutation.addedNodes.length || mutation.removedNodes.length)) {
                swapAllThemeIcons(theme);
                break;
            }
            if (mutation.type === 'attributes' && mutation.target instanceof HTMLImageElement && mutation.attributeName === 'src') {
                swapAllThemeIcons(theme);
                break;
            }
        }
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['src'],
    });
})();
