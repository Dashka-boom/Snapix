(function () {
    var modal = document.querySelector('[data-moderation-alert]');
    var backdrop = document.querySelector('[data-moderation-alert-backdrop]');
    var closeButton = document.querySelector('[data-moderation-alert-close]');

    if (!modal || !closeButton) {
        return;
    }

    function closeModal() {
        modal.classList.remove('is-open');
        if (backdrop) {
            backdrop.classList.remove('is-open');
        }
    }

    closeButton.addEventListener('click', function () {
        var notificationId = modal.getAttribute('data-notification-id');
        var formData = new FormData();
        formData.append('action', 'mark_moderation_notification_read');
        formData.append('notification_id', notificationId || '0');

        fetch('notification-action.php', {
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
                throw new Error(data && data.message ? data.message : 'Не удалось обновить уведомление.');
            }
            closeModal();
        }).catch(function (error) {
            window.alert(error.message || 'Не удалось закрыть предупреждение.');
        });
    });
})();
