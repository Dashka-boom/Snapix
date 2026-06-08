(function () {
    var drawer = document.getElementById('searchDrawer');
    var backdrop = document.querySelector('[data-search-backdrop]');
    var triggers = document.querySelectorAll('[data-search-trigger]');
    var closeButtons = document.querySelectorAll('[data-search-close]');

    if (!drawer || !triggers.length) {
        return;
    }

    function setTriggerState(isOpen) {
        triggers.forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    function openSearch() {
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        if (backdrop) {
            backdrop.classList.add('is-open');
        }
        setTriggerState(true);
        window.setTimeout(function () {
            var input = drawer.querySelector('.search-drawer-input');
            if (input) input.focus();
        }, 80);
    }

    function closeSearch() {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        if (backdrop) {
            backdrop.classList.remove('is-open');
        }
        setTriggerState(false);
    }

    triggers.forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            if (drawer.classList.contains('is-open')) {
                closeSearch();
            } else {
                openSearch();
            }
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', closeSearch);
    });

    if (backdrop) {
        backdrop.addEventListener('click', closeSearch);
    }

    document.addEventListener('click', function (event) {
        if (!drawer.classList.contains('is-open')) {
            return;
        }

        if (drawer.contains(event.target) || event.target.closest('[data-search-trigger]')) {
            return;
        }

        closeSearch();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSearch();
        }
    });

    var tabs = drawer.querySelectorAll('[data-search-tab]');
    var panels = drawer.querySelectorAll('[data-search-panel]');

    function activateTab(tabName) {
        tabs.forEach(function (tab) {
            var isActive = tab.getAttribute('data-search-tab') === tabName;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panels.forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-search-panel') === tabName);
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var tabName = tab.getAttribute('data-search-tab');
            if (tabName) {
                activateTab(tabName);
            }
        });
    });
}());
