(function () {
    var ACTIONS = ['toggle_like', 'toggle_save', 'add_repost', 'add_comment'];
    var channel = null;
    var pageId = String(Date.now()) + '-' + Math.random().toString(16).slice(2);

    if ('BroadcastChannel' in window) {
        channel = new BroadcastChannel('snapix-post-sync');
        channel.addEventListener('message', function (event) {
            var payload = event.data || {};
            if (!payload || payload.source === pageId || !payload.postId || !payload.data) {
                return;
            }
            syncPostState(String(payload.postId), payload.data, payload.action || '', false);
        });
    }

    function sendPostActionForm(form) {
        var formData = new FormData(form);
        return fetch(form.getAttribute('action') || window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json'
            },
            body: new URLSearchParams(formData).toString()
        }).then(function (response) {
            return response.json();
        });
    }

    function setCount(node, value) {
        if (node && typeof value !== 'undefined') {
            node.textContent = String(Number(value || 0));
        }
    }

    function getFormAction(form) {
        var actionInput = form ? form.querySelector('input[name="action"]') : null;
        return actionInput ? actionInput.value : '';
    }

    function getFormPostId(form) {
        var postIdInput = form ? form.querySelector('input[name="post_id"]') : null;
        return postIdInput ? postIdInput.value : '';
    }

    function animateActiveIcon(button, isActive) {
        if (!button || !isActive) {
            return;
        }
        button.classList.remove('is-activating');
        void button.offsetWidth;
        button.classList.add('is-activating');
        window.setTimeout(function () {
            button.classList.remove('is-activating');
        }, 260);
    }

    function countKeyForAction(action) {
        if (action === 'toggle_like') return 'likes_count';
        if (action === 'toggle_save') return 'saves_count';
        if (action === 'add_repost') return 'reposts_count';
        if (action === 'add_comment') return 'comments_count';
        return '';
    }

    function applyStateToForm(form, data, sourceAction) {
        var action = getFormAction(form);
        var item = form.closest('.feed-action-item') || form.closest('.profile-hover-action-item');
        var button = form.querySelector('.feed-action-btn, .profile-hover-action-btn, .clips-action-btn');
        var countNode = item ? item.querySelector('.feed-action-count, .clips-action-count') : null;
        var countKey = countKeyForAction(action);

        if (action === 'toggle_like') {
            if (button) {
                button.classList.toggle('is-active', !!data.liked);
                animateActiveIcon(button, !!data.liked && sourceAction === action);
            }
        }

        if (action === 'toggle_save') {
            if (button) {
                button.classList.toggle('is-saved', !!data.saved);
                animateActiveIcon(button, !!data.saved && sourceAction === action);
            }
        }

        if (action === 'add_repost') {
            if (button) {
                button.classList.toggle('is-reposted', !!data.reposted);
                animateActiveIcon(button, !!data.reposted && sourceAction === action);
            }
        }

        if (countKey) {
            setCount(countNode, data[countKey]);
        }
    }

    function modalPostId(button) {
        var modalId = button.getAttribute('data-modal') || '';
        var match = modalId.match(/(\d+)$/);
        return match ? match[1] : '';
    }

    function syncCommentButtons(postId, data) {
        document.querySelectorAll('.js-open-comments-modal').forEach(function (button) {
            var buttonPostId = button.getAttribute('data-post-id') || modalPostId(button);
            if (String(buttonPostId) !== String(postId)) {
                return;
            }
            var item = button.closest('.feed-action-item') || button.closest('.profile-hover-action-item');
            var countNode = item ? item.querySelector('.feed-action-count, .clips-action-count') : null;
            setCount(countNode, data.comments_count);
        });
    }

    function syncPostCards(postId, data) {
        document.querySelectorAll('[data-post-id]').forEach(function (node) {
            if (String(node.getAttribute('data-post-id') || '') !== String(postId)) {
                return;
            }
            if (typeof data.likes_count !== 'undefined') node.dataset.postLikesCount = String(data.likes_count);
            if (typeof data.comments_count !== 'undefined') node.dataset.postCommentsCount = String(data.comments_count);
            if (typeof data.reposts_count !== 'undefined') node.dataset.postRepostsCount = String(data.reposts_count);
            if (typeof data.saves_count !== 'undefined') node.dataset.postSavesCount = String(data.saves_count);
        });
    }

    function syncProfileViewer(postId, data) {
        var viewer = document.getElementById('profilePostViewer');
        if (!viewer || !viewer.classList.contains('is-open') || String(viewer.dataset.postId || '') !== String(postId)) {
            return;
        }
        setCount(document.getElementById('viewerLikesCount'), data.likes_count);
        setCount(document.getElementById('viewerCommentsCount'), data.comments_count);
        setCount(document.getElementById('viewerRepostsCount'), data.reposts_count);
        setCount(document.getElementById('viewerSavesCount'), data.saves_count);
    }

    function syncPostState(postId, data, sourceAction, broadcast) {
        if (!postId || !data) {
            return;
        }

        document.querySelectorAll('form.inline-action-form input[name="post_id"]').forEach(function (input) {
            if (String(input.value || '') !== String(postId)) {
                return;
            }
            var form = input.closest('form.inline-action-form');
            if (form) {
                applyStateToForm(form, data, sourceAction || '');
            }
        });

        syncCommentButtons(postId, data);
        syncPostCards(postId, data);
        syncProfileViewer(postId, data);

        if (broadcast && channel) {
            channel.postMessage({
                source: pageId,
                postId: String(postId),
                action: sourceAction || '',
                data: data
            });
        }
    }

    function createCommentNode(comment) {
        var item = document.createElement('div');
        item.className = 'comment-item';

        var author = document.createElement('a');
        author.className = 'comment-author';
        author.href = comment.profile_url || 'profile.php';

        var strong = document.createElement('strong');
        strong.textContent = comment.login || '';
        author.appendChild(strong);

        var text = document.createElement('p');
        text.textContent = comment.text || '';

        item.appendChild(author);
        item.appendChild(text);
        return item;
    }

    function appendCommentToBody(body, comment) {
        if (!body || !comment) {
            return;
        }
        var empty = body.querySelector('.comments-empty');
        if (empty) {
            empty.remove();
        }
        body.insertBefore(createCommentNode(comment), body.firstChild);
    }

    function appendComment(form, data, postId) {
        var textarea = form.querySelector('textarea[name="comment_text"]');

        if (data.comment) {
            document.querySelectorAll('.comments-modal').forEach(function (modal) {
                var idPostId = modal.id ? (modal.id.match(/(\d+)$/) || [])[1] : '';
                if (String(idPostId || '') === String(postId)) {
                    appendCommentToBody(modal.querySelector('.comments-modal-body'), data.comment);
                }
            });
        }

        if (textarea) {
            textarea.value = '';
        }
    }


    var countRefreshAt = Object.create(null);

    function refreshPostCounts(postId) {
        var now = Date.now();
        if (!postId || countRefreshAt[postId] && now - countRefreshAt[postId] < 5000) {
            return;
        }
        countRefreshAt[postId] = now;
        var body = new URLSearchParams({
            action: 'get_post_counts',
            post_id: String(postId)
        }).toString();
        fetch(window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json'
            },
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (!data || !data.ok) {
                return;
            }
            syncPostState(postId, data, 'get_post_counts', false);
        }).catch(function () {});
    }

    document.addEventListener('submit', function (event) {
        var form = event.target && event.target.closest ? event.target.closest('form.inline-action-form, form.comments-modal-form') : null;
        if (!form) {
            return;
        }

        var action = getFormAction(form);
        if (ACTIONS.indexOf(action) === -1) {
            return;
        }

        var postId = getFormPostId(form);
        if (!postId) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        if (form.dataset.snapixSubmitting === '1') {
            return;
        }
        form.dataset.snapixSubmitting = '1';

        sendPostActionForm(form)
            .then(function (data) {
                if (!data || !data.ok) {
                    return;
                }
                if (action === 'add_comment') {
                    appendComment(form, data, postId);
                }
                syncPostState(postId, data, action, true);
            })
            .catch(function () {})
            .finally(function () {
                form.dataset.snapixSubmitting = '0';
            });
    }, true);


    document.addEventListener('pointerenter', function (event) {
        var node = event.target && event.target.closest ? event.target.closest('[data-post-id]') : null;
        if (!node) {
            return;
        }
        refreshPostCounts(node.getAttribute('data-post-id'));
    }, true);

    window.SnapixPostSync = {
        syncPostState: syncPostState
    };
})();
