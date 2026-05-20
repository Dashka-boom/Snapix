<?php
session_start();
require './config/config.php';
require './includes/post-actions.php';
require_once './includes/side-menu.php';

function clips_build_profile_url(int $profileUserId, ?int $currentUserId): string
{
    if ($currentUserId !== null && $profileUserId === $currentUserId) {
        return 'profile.php';
    }

    return 'user.php?id=' . $profileUserId;
}

function clips_extract_hashtags(string $caption): array
{
    preg_match_all('/#[\p{L}\p{N}_]+/u', $caption, $matches);

    return array_values(array_unique($matches[0] ?? []));
}


function clips_fetch_comments(PDO $pdo, int $postId, ?int $currentUserId): array
{
    $stmt = $pdo->prepare('\n        SELECT comments.comment_text, comments.created_at, users.id AS user_id, users.login\n        FROM comments\n        INNER JOIN users ON users.id = comments.user_id\n        WHERE comments.post_id = :post_id AND comments.is_deleted = 0\n        ORDER BY comments.created_at DESC, comments.id DESC\n        LIMIT 80\n    ');
    $stmt->execute(['post_id' => $postId]);
    $rows = $stmt->fetchAll() ?: [];
    $comments = [];

    foreach ($rows as $row) {
        $comments[] = [
            'text' => (string) ($row['comment_text'] ?? ''),
            'login' => (string) ($row['login'] ?? ''),
            'profile_url' => clips_build_profile_url((int) ($row['user_id'] ?? 0), $currentUserId),
        ];
    }

    return $comments;
}

$user = null;

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT id, login, avatar FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        snapix_send_post_action_error('login_required', 401);
    }

    $action = $_POST['action'] ?? '';
    $postId = (int) ($_POST['post_id'] ?? 0);
    $isAjaxPostAction = snapix_is_ajax_request() && in_array($action, ['toggle_like', 'toggle_save', 'add_repost', 'hide_post', 'block_user', 'report_post'], true);
    $ajaxExtra = [];
    $postExists = false;
    $postOwnerId = 0;

    if ($postId > 0) {
        $postExistsStmt = $pdo->prepare("
            SELECT posts.id, posts.user_id
            FROM posts
            INNER JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
            WHERE posts.id = :id
              AND posts.is_deleted = 0
              AND post_media.media_type = 'video'
            LIMIT 1
        ");
        $postExistsStmt->execute(['id' => $postId]);
        $postRow = $postExistsStmt->fetch();
        $postExists = (bool) $postRow;
        $postOwnerId = (int) ($postRow['user_id'] ?? 0);
    }

    if ($postExists && $action === 'toggle_like') {
        $likeExistsStmt = $pdo->prepare('SELECT id FROM likes WHERE user_id = :user_id AND post_id = :post_id');
        $likeExistsStmt->execute([
            'user_id' => $user['id'],
            'post_id' => $postId,
        ]);
        $likeId = $likeExistsStmt->fetchColumn();

        if ($likeId) {
            $deleteLikeStmt = $pdo->prepare('DELETE FROM likes WHERE id = :id');
            $deleteLikeStmt->execute(['id' => $likeId]);
            $ajaxExtra['liked'] = false;
        } else {
            $insertLikeStmt = $pdo->prepare('INSERT INTO likes (user_id, post_id) VALUES (:user_id, :post_id)');
            $insertLikeStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);
            $ajaxExtra['liked'] = true;
        }
    }

    if ($postExists && $action === 'toggle_save') {
        $saveExistsStmt = $pdo->prepare('SELECT id FROM saved_posts WHERE user_id = :user_id AND post_id = :post_id');
        $saveExistsStmt->execute([
            'user_id' => $user['id'],
            'post_id' => $postId,
        ]);
        $saveId = $saveExistsStmt->fetchColumn();

        if ($saveId) {
            $deleteSaveStmt = $pdo->prepare('DELETE FROM saved_posts WHERE id = :id');
            $deleteSaveStmt->execute(['id' => $saveId]);
            $ajaxExtra['saved'] = false;
        } else {
            $insertSaveStmt = $pdo->prepare('INSERT INTO saved_posts (user_id, post_id) VALUES (:user_id, :post_id)');
            $insertSaveStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);
            $ajaxExtra['saved'] = true;
        }
    }

    if ($postExists && $action === 'add_repost') {
        $repostExistsStmt = $pdo->prepare('SELECT id FROM reposts WHERE user_id = :user_id AND post_id = :post_id');
        $repostExistsStmt->execute([
            'user_id' => $user['id'],
            'post_id' => $postId,
        ]);
        $repostId = $repostExistsStmt->fetchColumn();

        if ($repostId) {
            $deleteRepostStmt = $pdo->prepare('DELETE FROM reposts WHERE id = :id AND user_id = :user_id');
            $deleteRepostStmt->execute([
                'id' => (int) $repostId,
                'user_id' => $user['id'],
            ]);
            $ajaxExtra['reposted'] = false;
        } else {
            $insertRepostStmt = $pdo->prepare('INSERT IGNORE INTO reposts (user_id, post_id) VALUES (:user_id, :post_id)');
            $insertRepostStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);
            $ajaxExtra['reposted'] = true;
        }
    }

    if ($postExists && $action === 'hide_post') {
        $hideStmt = $pdo->prepare('INSERT IGNORE INTO hidden_posts (user_id, post_id) VALUES (:user_id, :post_id)');
        $hideStmt->execute([
            'user_id' => (int) $user['id'],
            'post_id' => $postId,
        ]);
        $ajaxExtra['hidden'] = true;
    }

    if ($postExists && $action === 'block_user' && $postOwnerId > 0 && $postOwnerId !== (int) $user['id']) {
        $blockUserStmt = $pdo->prepare('
            INSERT IGNORE INTO user_blocks (blocker_user_id, blocked_user_id)
            VALUES (:blocker_user_id, :blocked_user_id)
        ');
        $blockUserStmt->execute([
            'blocker_user_id' => (int) $user['id'],
            'blocked_user_id' => $postOwnerId,
        ]);
        $ajaxExtra['blocked'] = true;
        $ajaxExtra['blocked_user_id'] = $postOwnerId;
    }

    if ($postExists && $action === 'report_post' && $postOwnerId > 0 && $postOwnerId !== (int) $user['id']) {
        $reportPostStmt = $pdo->prepare('
            INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text)
            VALUES (:reporter_user_id, :target_user_id, :reason_text)
        ');
        $reportPostStmt->execute([
            'reporter_user_id' => (int) $user['id'],
            'target_user_id' => $postOwnerId,
            'reason_text' => 'Жалоба на clips #' . $postId,
        ]);
        $ajaxExtra['reported'] = true;
    }

    if ($action === 'get_comments') {
        if ($postId <= 0 || !$postExists) {
            snapix_send_post_action_error('post_not_found', 404);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'comments' => clips_fetch_comments($pdo, $postId, $user ? (int) $user['id'] : null),
        ]);
        exit;
    }

    if ($action === 'add_comment') {
        if (!$user) {
            snapix_send_post_action_error('login_required', 401);
        }

        if ($postId <= 0 || !$postExists) {
            snapix_send_post_action_error('post_not_found', 404);
        }

        $commentText = trim((string) ($_POST['comment_text'] ?? ''));

        if ($commentText === '') {
            snapix_send_post_action_error('empty_comment', 422);
        }

        $insertCommentStmt = $pdo->prepare('INSERT INTO comments (user_id, post_id, comment_text) VALUES (:user_id, :post_id, :comment_text)');
        $insertCommentStmt->execute([
            'user_id' => (int) $user['id'],
            'post_id' => $postId,
            'comment_text' => mb_substr($commentText, 0, 1000),
        ]);

        $comment = [
            'text' => mb_substr($commentText, 0, 1000),
            'login' => (string) $user['login'],
            'profile_url' => clips_build_profile_url((int) $user['id'], (int) $user['id']),
        ];

        snapix_send_post_action_json($pdo, $postId, (int) $user['id'], ['comment' => $comment]);
    }

    if ($isAjaxPostAction) {
        if ($postId <= 0 || !$postExists) {
            snapix_send_post_action_error('post_not_found', 404);
        }

        snapix_send_post_action_json($pdo, $postId, (int) $user['id'], $ajaxExtra);
    }

    header('Location: clips.php');
    exit;
}

$currentUserId = (int) ($user['id'] ?? 0);
$clipsStmt = $pdo->query('
    SELECT
        posts.id,
        posts.caption,
        posts.created_at,
        users.id AS user_id,
        users.login,
        users.avatar,
        post_media.media_url,
        (
            SELECT COUNT(*)
            FROM likes
            WHERE likes.post_id = posts.id
        ) AS likes_count,
        (
            SELECT COUNT(*)
            FROM comments
            WHERE comments.post_id = posts.id AND comments.is_deleted = 0
        ) AS comments_count,
        (
            SELECT COUNT(*)
            FROM saved_posts
            WHERE saved_posts.post_id = posts.id
        ) AS saves_count,
        (
            SELECT COUNT(*)
            FROM reposts
            WHERE reposts.post_id = posts.id
        ) AS reposts_count,
        (
            SELECT COUNT(*)
            FROM likes
            WHERE likes.post_id = posts.id
              AND likes.user_id = ' . $currentUserId . '
        ) AS is_liked,
        (
            SELECT COUNT(*)
            FROM saved_posts
            WHERE saved_posts.post_id = posts.id
              AND saved_posts.user_id = ' . $currentUserId . '
        ) AS is_saved,
        (
            SELECT COUNT(*)
            FROM reposts
            WHERE reposts.post_id = posts.id
              AND reposts.user_id = ' . $currentUserId . '
        ) AS is_reposted
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    INNER JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.is_deleted = 0
      AND post_media.media_type = \'video\'
      AND posts.id NOT IN (
          SELECT hidden_posts.post_id
          FROM hidden_posts
          WHERE hidden_posts.user_id = ' . $currentUserId . '
      )
      AND posts.user_id NOT IN (
          SELECT user_blocks.blocked_user_id
          FROM user_blocks
          WHERE user_blocks.blocker_user_id = ' . $currentUserId . '
      )
    ORDER BY posts.created_at DESC, posts.id DESC
    LIMIT 60
');
$clipsRows = $clipsStmt->fetchAll();
$clips = [];

foreach ($clipsRows as $clip) {
    $caption = (string) ($clip['caption'] ?? '');
    $hashtags = clips_extract_hashtags($caption);
    $description = trim(preg_replace('/#[\p{L}\p{N}_]+/u', '', $caption));

    $clips[] = [
        'id' => (int) $clip['id'],
        'videoUrl' => (string) $clip['media_url'],
        'description' => $description,
        'hashtags' => $hashtags,
        'createdAt' => (string) $clip['created_at'],
        'author' => [
            'id' => (int) $clip['user_id'],
            'login' => (string) $clip['login'],
            'avatar' => (string) ($clip['avatar'] ?? ''),
            'profileUrl' => clips_build_profile_url((int) $clip['user_id'], $user ? (int) $user['id'] : null),
        ],
        'counts' => [
            'likes' => (int) $clip['likes_count'],
            'comments' => (int) $clip['comments_count'],
            'saves' => (int) $clip['saves_count'],
            'reposts' => (int) $clip['reposts_count'],
        ],
        'state' => [
            'liked' => (int) $clip['is_liked'] > 0,
            'saved' => (int) $clip['is_saved'] > 0,
            'reposted' => (int) $clip['is_reposted'] > 0,
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <title>Snapix</title>
</head>
<body data-page="clips" class="has-side-menu">
    <?php render_side_menu($user); ?>

    <main class="clips-page">
        <?php if ($clips): ?>
            <section class="clips-shell" aria-label="Clips">
                <div class="clips-info">
                    <div class="clips-author-row">
                        <a href="#" class="clips-author-link" data-clips-author-link>
                            <span class="clips-avatar" data-clips-avatar></span>
                            <span class="clips-author-meta">
                                <strong data-clips-login></strong>
                                <span data-clips-time></span>
                            </span>
                        </a>
                        <div class="clips-menu-wrap">
                            <button type="button" class="clips-menu-toggle" data-clips-menu-toggle aria-label="Действия с clips">
                                <span></span>
                                <span></span>
                                <span></span>
                            </button>
                            <div class="post-menu clips-post-menu" data-clips-menu>
                                <a href="#" class="post-menu-item" data-clips-menu-account>
                                    <img src="icon/dark theme/об аккаунте.png" alt="">
                                    <span>Об аккаунте</span>
                                </a>
                                <?php if ($user): ?>
                                    <button type="button" class="post-menu-item" data-clips-menu-action="hide_post">
                                        <img src="icon/dark theme/dislike.png" alt="">
                                        <span>Мне не интересно</span>
                                    </button>
                                    <button type="button" class="post-menu-item" data-clips-menu-action="block_user">
                                        <img src="icon/dark theme/stop.png" alt="">
                                        <span data-clips-block-label>Добавить в чёрный список</span>
                                    </button>
                                <?php else: ?>
                                    <a href="login.php" class="post-menu-item">
                                        <img src="icon/dark theme/dislike.png" alt="">
                                        <span>Мне не интересно</span>
                                    </a>
                                    <a href="login.php" class="post-menu-item">
                                        <img src="icon/dark theme/stop.png" alt="">
                                        <span data-clips-block-label>Добавить в чёрный список</span>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="post-menu-item" data-clips-copy-link>
                                    <img src="icon/dark theme/copy.png" alt="">
                                    <span>Поделиться</span>
                                </button>
                                <?php if ($user): ?>
                                    <button type="button" class="post-menu-item post-menu-item-danger" data-clips-menu-action="report_post">
                                        <img src="icon/complaint.png" alt="">
                                        <span>Пожаловаться</span>
                                    </button>
                                <?php else: ?>
                                    <a href="login.php" class="post-menu-item post-menu-item-danger">
                                        <img src="icon/complaint.png" alt="">
                                        <span>Пожаловаться</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <p class="clips-description" data-clips-description></p>
                    <div class="clips-hashtags" data-clips-hashtags></div>
                </div>

                <div class="clips-video-wrap">
                    <video class="clips-video" data-clips-video controls playsinline autoplay loop muted></video>
                </div>

                <div class="clips-actions" aria-label="Действия с Clips">
                    <?php if ($user): ?>
                        <button type="button" class="clips-action-btn clips-like-btn" data-clips-action="toggle_like" aria-label="Лайк">
                            <img src="icon/dark theme/like.png" alt="">
                            <span data-clips-count="likes">0</span>
                        </button>
                        <a href="#" class="clips-action-btn clips-comment-btn" data-clips-comment-link aria-label="Комментарии">
                            <img src="icon/dark theme/comment.png" alt="">
                            <span data-clips-count="comments">0</span>
                        </a>
                        <button type="button" class="clips-action-btn clips-repost-btn" data-clips-action="add_repost" aria-label="Репост">
                            <img src="icon/dark theme/repost.png" alt="">
                            <span data-clips-count="reposts">0</span>
                        </button>
                        <button type="button" class="clips-action-btn clips-save-btn" data-clips-action="toggle_save" aria-label="Избранное">
                            <img src="icon/dark theme/favourites.png" alt="">
                            <span data-clips-count="saves">0</span>
                        </button>
                    <?php else: ?>
                        <a href="login.php" class="clips-action-btn" aria-label="Войти для лайка"><img src="icon/dark theme/like.png" alt=""><span data-clips-count="likes">0</span></a>
                        <a href="#" class="clips-action-btn clips-comment-btn" data-clips-comment-link aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""><span data-clips-count="comments">0</span></a>
                        <a href="login.php" class="clips-action-btn" aria-label="Войти для репоста"><img src="icon/dark theme/repost.png" alt=""><span data-clips-count="reposts">0</span></a>
                        <a href="login.php" class="clips-action-btn" aria-label="Войти для избранного"><img src="icon/dark theme/favourites.png" alt=""><span data-clips-count="saves">0</span></a>
                    <?php endif; ?>
                </div>


                <div class="clips-comments-modal" data-clips-comments-modal>
                    <div class="clips-comments-modal-header">
                        <h3>Комментарии</h3>
                        <button type="button" class="clips-comments-close" data-clips-comments-close aria-label="Закрыть">×</button>
                    </div>
                    <div class="clips-comments-modal-body" data-clips-comments-list>
                        <p class="comments-empty">Загрузка...</p>
                    </div>
                    <?php if ($user): ?>
                        <form class="clips-comments-form" data-clips-comments-form>
                            <textarea name="comment_text" rows="2" maxlength="1000" placeholder="Напишите комментарий..."></textarea>
                            <button type="submit" class="primary-link">Отправить</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="clips-nav" aria-label="Навигация Clips">
                    <button type="button" class="clips-nav-btn" data-clips-prev aria-label="Предыдущий clips">↑</button>
                    <button type="button" class="clips-nav-btn" data-clips-next aria-label="Следующий clips">↓</button>
                </div>
            </section>
        <?php else: ?>
            <section class="clips-empty">
                <h1>Clips</h1>
                <p>Видео пока нет.</p>
            </section>
        <?php endif; ?>
    </main>

    <script>
    (function () {
        var clips = <?php echo json_encode($clips, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        if (!clips.length) {
            return;
        }

        var currentIndex = 0;
        var lastNavigationAt = 0;
        var shell = document.querySelector('.clips-shell');
        var video = document.querySelector('[data-clips-video]');
        var avatar = document.querySelector('[data-clips-avatar]');
        var authorLink = document.querySelector('[data-clips-author-link]');
        var login = document.querySelector('[data-clips-login]');
        var time = document.querySelector('[data-clips-time]');
        var description = document.querySelector('[data-clips-description]');
        var hashtags = document.querySelector('[data-clips-hashtags]');
        var clipsMenu = document.querySelector('[data-clips-menu]');
        var clipsMenuToggle = document.querySelector('[data-clips-menu-toggle]');
        var clipsMenuAccount = document.querySelector('[data-clips-menu-account]');
        var clipsBlockLabel = document.querySelector('[data-clips-block-label]');
        var clipsCopyLink = document.querySelector('[data-clips-copy-link]');
        var commentLinks = document.querySelectorAll('[data-clips-comment-link]');
        var commentsModal = document.querySelector('[data-clips-comments-modal]');
        var commentsList = document.querySelector('[data-clips-comments-list]');
        var commentsForm = document.querySelector('[data-clips-comments-form]');
        var counts = {
            likes: document.querySelector('[data-clips-count="likes"]'),
            comments: document.querySelector('[data-clips-count="comments"]'),
            reposts: document.querySelector('[data-clips-count="reposts"]'),
            saves: document.querySelector('[data-clips-count="saves"]')
        };
        var buttons = {
            like: document.querySelector('[data-clips-action="toggle_like"]'),
            repost: document.querySelector('[data-clips-action="add_repost"]'),
            save: document.querySelector('[data-clips-action="toggle_save"]')
        };

        function formatCount(value) {
            return String(Number(value || 0));
        }

        function parseDate(value) {
            return new Date(String(value).replace(' ', 'T'));
        }

        function pad(value) {
            return String(value).padStart(2, '0');
        }

        function formatClipTime(value) {
            var date = parseDate(value);
            var now = new Date();
            var diff = now.getTime() - date.getTime();
            var minute = 60 * 1000;
            var hour = 60 * minute;
            var day = 24 * hour;

            if (!Number.isFinite(date.getTime())) {
                return '';
            }

            if (diff >= 0 && diff < hour) {
                return Math.max(1, Math.floor(diff / minute)) + ' мин';
            }

            if (diff >= 0 && diff < day) {
                return Math.floor(diff / hour) + ' ч';
            }

            var yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
            var clipDay = new Date(date.getFullYear(), date.getMonth(), date.getDate());

            if (clipDay.getTime() === yesterday.getTime()) {
                return '1 день назад';
            }

            return pad(date.getDate()) + '.' + pad(date.getMonth() + 1) + '.' + date.getFullYear() + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
        }

        function animateButton(button, isActive) {
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

        function renderClip(index) {
            var clip = clips[index];

            shell.classList.remove('is-visible');
            window.setTimeout(function () {
                video.pause();
                video.src = clip.videoUrl;
                video.load();
                video.play().catch(function () {});

                authorLink.href = clip.author.profileUrl;
                login.textContent = clip.author.login;
                time.textContent = formatClipTime(clip.createdAt);
                description.textContent = clip.description || '';

                if (clip.author.avatar) {
                    avatar.style.backgroundImage = "url('" + clip.author.avatar.replace(/'/g, "\\'") + "')";
                    avatar.textContent = '';
                    avatar.classList.add('has-avatar');
                } else {
                    avatar.style.backgroundImage = '';
                    avatar.textContent = (clip.author.login || 'S').slice(0, 1);
                    avatar.classList.remove('has-avatar');
                }

                hashtags.innerHTML = '';
                clip.hashtags.forEach(function (tag) {
                    var item = document.createElement('span');
                    item.textContent = tag;
                    hashtags.appendChild(item);
                });

                counts.likes.textContent = formatCount(clip.counts.likes);
                counts.comments.textContent = formatCount(clip.counts.comments);
                counts.reposts.textContent = formatCount(clip.counts.reposts);
                counts.saves.textContent = formatCount(clip.counts.saves);

                if (buttons.like) {
                    buttons.like.classList.toggle('is-liked', !!clip.state.liked);
                }
                if (buttons.repost) {
                    buttons.repost.classList.toggle('is-reposted', !!clip.state.reposted);
                }
                if (buttons.save) {
                    buttons.save.classList.toggle('is-saved', !!clip.state.saved);
                }

                if (clipsMenuAccount) {
                    clipsMenuAccount.href = clip.author.profileUrl;
                }
                if (clipsBlockLabel) {
                    clipsBlockLabel.textContent = 'Добавить ' + clip.author.login + ' в чёрный список';
                }
                if (clipsMenu) {
                    clipsMenu.classList.remove('is-open');
                }

                shell.classList.add('is-visible');
            }, 110);
        }

        function goToClip(nextIndex) {
            if (nextIndex < 0 || nextIndex >= clips.length || nextIndex === currentIndex) {
                return;
            }

            currentIndex = nextIndex;
    
        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (char) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]);
            });
        }

        function renderComments(items) {
            if (!commentsList) { return; }
            if (!items.length) {
                commentsList.innerHTML = '<p class="comments-empty">Пока нет комментариев.</p>';
                return;
            }
            commentsList.innerHTML = items.map(function (comment) {
                return '<div class="comment-item"><a class="comment-author" href="' + escapeHtml(comment.profile_url || '#') + '"><strong>' + escapeHtml(comment.login || '') + '</strong></a><p>' + escapeHtml(comment.text || '') + '</p></div>';
            }).join('');
        }

        function openCommentsModal(triggerButton) {
            if (!commentsModal) { return; }
            var clip = clips[currentIndex];
            commentsModal.classList.add('is-open');
            if (triggerButton) {
                var rect = triggerButton.getBoundingClientRect();
                commentsModal.style.top = Math.max(16, rect.top - 12) + 'px';
                commentsModal.style.left = (rect.right + 12) + 'px';
            }
            commentsList.innerHTML = '<p class="comments-empty">Загрузка...</p>';
            sendClipAction('get_comments').then(function (data) {
                if (data && data.ok) { renderComments(data.comments || []); }
            }).catch(function () {});
        }

        function closeCommentsModal() {
            if (commentsModal) { commentsModal.classList.remove('is-open'); }
        }

        commentLinks.forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                openCommentsModal(link);
            });
        });

        var commentsClose = document.querySelector('[data-clips-comments-close]');
        if (commentsClose) { commentsClose.addEventListener('click', closeCommentsModal); }

        if (commentsForm) {
            commentsForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var textarea = commentsForm.querySelector('textarea[name="comment_text"]');
                if (!textarea || !textarea.value.trim()) { return; }
                var clip = clips[currentIndex];
                var formData = new URLSearchParams();
                formData.set('action', 'add_comment');
                formData.set('post_id', clip.id);
                formData.set('comment_text', textarea.value);
                fetch('clips.php', { method:'POST', credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','Accept':'application/json'}, body: formData.toString() })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data || !data.ok) { return; }
                        textarea.value = '';
                        counts.comments.textContent = formatCount(data.comments_count);
                        clips[currentIndex].counts.comments = Number(data.comments_count || 0);
                        openCommentsModal();
                    })
                    .catch(function () {});
            });
        }

        renderClip(currentIndex);
        }

        function navigateClip(direction) {
            var now = Date.now();

            if (now - lastNavigationAt < 420) {
                return;
            }

            if (direction < 0 && currentIndex <= 0) {
                return;
            }

            if (direction > 0 && currentIndex >= clips.length - 1) {
                return;
            }

            lastNavigationAt = now;
            goToClip(currentIndex + direction);
        }

        function sendClipAction(action) {
            var clip = clips[currentIndex];
            var formData = new URLSearchParams();
            formData.set('action', action);
            formData.set('post_id', clip.id);

            return fetch('clips.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json'
                },
                body: formData.toString()
            }).then(function (response) {
                return response.json();
            });
        }

        function removeCurrentClip() {
            clips.splice(currentIndex, 1);

            if (!clips.length) {
                window.location.reload();
                return;
            }

            if (currentIndex >= clips.length) {
                currentIndex = clips.length - 1;
            }

            renderClip(currentIndex);
        }

        function copyCurrentClipLink(button) {
            var clip = clips[currentIndex];
            var absoluteUrl = new URL('post.php?id=' + clip.id, window.location.href).toString();
            var label = button ? button.querySelector('span') : null;
            var defaultText = label ? label.textContent : '';

            function markCopied() {
                if (label) {
                    label.textContent = 'Ссылка скопирована';
                    window.setTimeout(function () {
                        label.textContent = defaultText;
                    }, 1400);
                }
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(absoluteUrl).then(markCopied).catch(function () {});
                return;
            }

            var field = document.createElement('textarea');
            field.value = absoluteUrl;
            field.setAttribute('readonly', 'readonly');
            field.style.position = 'fixed';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();
            try {
                document.execCommand('copy');
                markCopied();
            } catch (error) {}
            field.remove();
        }

        document.querySelector('[data-clips-prev]').addEventListener('click', function () {
            navigateClip(-1);
        });

        document.querySelector('[data-clips-next]').addEventListener('click', function () {
            navigateClip(1);
        });

        document.querySelectorAll('[data-clips-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-clips-action');

                sendClipAction(action)
                    .then(function (data) {
                        var clip = clips[currentIndex];

                        if (!data || !data.ok) {
                            return;
                        }

                        clip.counts.likes = data.likes_count;
                        clip.counts.saves = data.saves_count;
                        clip.counts.reposts = data.reposts_count;
                        clip.state.liked = !!data.liked;
                        clip.state.saved = !!data.saved;
                        clip.state.reposted = !!data.reposted;

                        counts.likes.textContent = formatCount(data.likes_count);
                        counts.saves.textContent = formatCount(data.saves_count);
                        counts.reposts.textContent = formatCount(data.reposts_count);

                        buttons.like.classList.toggle('is-liked', !!data.liked);
                        buttons.repost.classList.toggle('is-reposted', !!data.reposted);
                        buttons.save.classList.toggle('is-saved', !!data.saved);

                        if (action === 'toggle_like') {
                            animateButton(buttons.like, !!data.liked);
                        }
                        if (action === 'toggle_save') {
                            animateButton(buttons.save, !!data.saved);
                        }
                        if (action === 'add_repost') {
                            animateButton(buttons.repost, !!data.reposted);
                        }
                    })
                    .catch(function () {});
            });
        });

        if (clipsMenuToggle && clipsMenu) {
            clipsMenuToggle.addEventListener('click', function () {
                clipsMenu.classList.toggle('is-open');
            });
        }

        document.querySelectorAll('[data-clips-menu-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-clips-menu-action');
                var clip = clips[currentIndex];

                sendClipAction(action)
                    .then(function (data) {
                        if (!data || !data.ok) {
                            return;
                        }

                        if (clipsMenu) {
                            clipsMenu.classList.remove('is-open');
                        }

                        if (action === 'hide_post') {
                            removeCurrentClip();
                        }

                        if (action === 'block_user') {
                            clips = clips.filter(function (item) {
                                return item.author.id !== clip.author.id;
                            });

                            if (!clips.length) {
                                window.location.reload();
                                return;
                            }

                            if (currentIndex >= clips.length) {
                                currentIndex = clips.length - 1;
                            }

                            renderClip(currentIndex);
                        }

                        if (action === 'report_post') {
                            var label = button.querySelector('span');
                            var defaultText = label ? label.textContent : '';

                            if (label) {
                                label.textContent = 'Жалоба отправлена';
                                window.setTimeout(function () {
                                    label.textContent = defaultText;
                                }, 1400);
                            }
                        }
                    })
                    .catch(function () {});
            });
        });

        if (clipsCopyLink) {
            clipsCopyLink.addEventListener('click', function () {
                copyCurrentClipLink(clipsCopyLink);
            });
        }

        document.addEventListener('click', function (event) {
            var wrap = event.target.closest('.clips-menu-wrap');

            if (!wrap && clipsMenu) {
                clipsMenu.classList.remove('is-open');
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                navigateClip(-1);
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                navigateClip(1);
            }
        });

        document.addEventListener('wheel', function (event) {
            if (Math.abs(event.deltaY) < 20) {
                return;
            }

            event.preventDefault();
            navigateClip(event.deltaY > 0 ? 1 : -1);
        }, { passive: false });

        renderClip(currentIndex);
    })();
    </script>
</body>
</html>
