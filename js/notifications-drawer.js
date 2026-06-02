(function () {
    var drawer = document.getElementById('notificationsDrawer');
    var backdrop = document.querySelector('[data-notifications-backdrop]');
    var triggers = document.querySelectorAll('[data-notifications-trigger]');
    var closeButtons = document.querySelectorAll('[data-notifications-close]');

    if (!drawer || !triggers.length) {
        return;
    }

    function setTriggerState(isOpen) {
        triggers.forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    function openDrawer() {
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        if (backdrop) {
            backdrop.classList.add('is-open');
        }
        setTriggerState(true);
        window.setTimeout(scheduleIndicatorUpdate, 40);
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        if (backdrop) {
            backdrop.classList.remove('is-open');
        }
        setTriggerState(false);
    }

    function toggleDrawer(event) {
        event.preventDefault();
        if (drawer.classList.contains('is-open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    }

    triggers.forEach(function (trigger) {
        trigger.addEventListener('click', toggleDrawer);
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', closeDrawer);
    });

    if (backdrop) {
        backdrop.addEventListener('click', closeDrawer);
    }

    document.addEventListener('click', function (event) {
        if (!drawer.classList.contains('is-open')) {
            return;
        }

        var clickedTrigger = event.target.closest('[data-notifications-trigger]');
        if (drawer.contains(event.target) || clickedTrigger) {
            return;
        }

        closeDrawer();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDrawer();
        }
    });

    var tabsWrap = drawer.querySelector('.notifications-drawer-tabs');
    var tabs = drawer.querySelectorAll('[data-notification-tab]');
    var panels = drawer.querySelectorAll('[data-notification-panel]');
    var indicator = drawer.querySelector('.notifications-drawer-tab-indicator');

    function updateIndicator() {
        if (!tabsWrap || !indicator) {
            return;
        }

        var activeTab = drawer.querySelector('[data-notification-tab].is-active');
        if (!activeTab) {
            return;
        }

        var wrapRect = tabsWrap.getBoundingClientRect();
        var tabRect = activeTab.getBoundingClientRect();
        indicator.style.width = tabRect.width + 'px';
        indicator.style.transform = 'translateX(' + (tabRect.left - wrapRect.left + tabsWrap.scrollLeft) + 'px)';
        indicator.style.opacity = '1';
    }

    function scheduleIndicatorUpdate() {
        window.requestAnimationFrame(function () {
            updateIndicator();
        });
    }

    function activateTab(tab) {
        var tabName = tab.getAttribute('data-notification-tab');
        if (!tabName) {
            return;
        }

        tabs.forEach(function (item) {
            var isActive = item === tab;
            item.classList.toggle('is-active', isActive);
            item.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panels.forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-notification-panel') === tabName);
        });

        scheduleIndicatorUpdate();
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateTab(tab);
        });
    });

    window.addEventListener('resize', scheduleIndicatorUpdate);
    if (tabsWrap) {
        tabsWrap.addEventListener('scroll', scheduleIndicatorUpdate);
    }
    scheduleIndicatorUpdate();

    drawer.querySelectorAll('[data-notification-request-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var button = form.querySelector('button[type="submit"]');
            var formData = new FormData(form);
            if (button) {
                button.disabled = true;
            }

            fetch(form.getAttribute('action') || 'notification-action.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (!data || !data.success) {
                    throw new Error(data && data.message ? data.message : 'Не удалось обработать заявку.');
                }

                var card = form.closest('[data-request-card]');
                var list = form.closest('.notifications-drawer-list');
                if (card) {
                    card.classList.add('is-removing');
                    window.setTimeout(function () {
                        card.remove();
                        if (list && !list.querySelector('[data-request-card]')) {
                            list.outerHTML = '<p class="notifications-drawer-empty">Новых заявок пока нет.</p>';
                        }
                    }, 180);
                }
            }).catch(function (error) {
                if (button) {
                    button.disabled = false;
                }
                window.alert(error.message || 'Не удалось обработать заявку.');
            });
        });
    });
})();
