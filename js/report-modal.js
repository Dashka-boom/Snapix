(function () {
    var modal = document.querySelector('[data-report-modal]');
    if (!modal) return;
    var closeButtons = modal.querySelectorAll('[data-report-modal-close]');
    var submitButton = modal.querySelector('[data-report-submit]');
    var radios = modal.querySelectorAll('input[name="report_reason"]');
    var successBlock = modal.querySelector('[data-report-success]');
    var formBlock = modal.querySelector('[data-report-form]');
    var blockButton = modal.querySelector('[data-report-block]');
    var targetForm = null;
    var onSubmitCallback = null;
    var targetUserId = 0;
    var targetLogin = '';

    function selectedReason() {
        var checked = modal.querySelector('input[name="report_reason"]:checked');
        return checked ? checked.value : '';
    }
    function syncSubmitState() {
        if (submitButton) submitButton.disabled = !selectedReason();
    }
    function open(form, login, userId, onSubmit) {
        targetForm = form || null;
        onSubmitCallback = typeof onSubmit === 'function' ? onSubmit : null;
        targetUserId = Number(userId || 0);
        targetLogin = login || '';
        if (blockButton) blockButton.textContent = 'Заблокировать @' + (targetLogin || 'user');
        modal.classList.add('is-open');
        document.body.classList.add('is-report-modal-open');
        formBlock.hidden = false;
        successBlock.hidden = true;
        radios.forEach(function (radio) { radio.checked = false; });
        syncSubmitState();
    }
    function close() {
        modal.classList.remove('is-open');
        document.body.classList.remove('is-report-modal-open');
    }
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-report-trigger]');
        if (!trigger) return;
        var form = trigger.closest('form');
        if (!form) return;
        e.preventDefault();
        open(form, trigger.getAttribute('data-report-login') || '', trigger.getAttribute('data-report-user-id') || '0');
    });
    closeButtons.forEach(function (btn) { btn.addEventListener('click', close); });
    radios.forEach(function (radio) { radio.addEventListener('change', syncSubmitState); });
        if (submitButton) {
        submitButton.addEventListener('click', function () {
            var reason = selectedReason();
            if (!reason) return;
            if (onSubmitCallback) {
                onSubmitCallback(reason, { close: close, showSuccess: function () { formBlock.hidden = true; successBlock.hidden = false; } });
                return;
            }
            if (!targetForm) return;
            var hidden = targetForm.querySelector('input[name="report_reason"]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'report_reason';
                targetForm.appendChild(hidden);
            }
            hidden.value = reason;
            targetForm.submit();
            formBlock.hidden = true;
            successBlock.hidden = false;
        });
    }
    if (blockButton) {
        blockButton.addEventListener('click', function () {
            if (!targetUserId) return;
            var formData = new URLSearchParams();
            formData.set('action', 'block_user');
            formData.set('owner_id', String(targetUserId));
            fetch(window.location.pathname, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: formData.toString() })
                .finally(close);
        });
    }
    window.SnapixReportModal = {
        open: function (opts) {
            opts = opts || {};
            open(null, opts.login || '', opts.userId || 0, opts.onSubmit || null);
        },
        close: close
    };
})();
