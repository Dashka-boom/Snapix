<?php
session_start();
require './config/config.php';
require_once './includes/side-menu.php';
require_once './includes/hashtags.php';
require './includes/icons.php';
require './includes/post-actions.php';
require './includes/notifications.php';

function buildProfileUrl(int $profileUserId, int $currentUserId): string
{
    if ($profileUserId === $currentUserId) {
        return 'profile.php';
    }

    return 'user.php?id=' . $profileUserId;
}

function formatBlockedUntil(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);
    if (!$timestamp) {
        return '';
    }

    return date('d.m.Y H:i', $timestamp);
}

function ensureCommentModerationStorage(PDO $pdo): void
{
    try {
        $pdo->exec('ALTER TABLE comments ADD COLUMN attachment_url VARCHAR(255) NULL AFTER comment_text');
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) !== 1060) {
            throw $exception;
        }
    }

    try {
        $pdo->exec("ALTER TABLE comments ADD COLUMN attachment_type ENUM('image','gif') NULL AFTER attachment_url");
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) !== 1060) {
            throw $exception;
        }
    }

    try {
        $pdo->exec("ALTER TABLE comments ADD COLUMN status ENUM('published','pending_review','rejected') NOT NULL DEFAULT 'published' AFTER attachment_type");
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) !== 1060) {
            throw $exception;
        }
    }

    try {
        $pdo->exec('ALTER TABLE comments ADD COLUMN parent_comment_id BIGINT UNSIGNED NULL AFTER post_id');
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) !== 1060) {
            throw $exception;
        }
    }

    try {
        $pdo->exec('ALTER TABLE comments ADD KEY idx_comments_parent (parent_comment_id)');
    } catch (PDOException $exception) {
        if (!in_array(($exception->errorInfo[1] ?? null), [1061, 1060], true)) {
            throw $exception;
        }
    }

    try {
        $pdo->exec('ALTER TABLE comments ADD CONSTRAINT fk_comments_parent FOREIGN KEY (parent_comment_id) REFERENCES comments(id) ON DELETE CASCADE');
    } catch (PDOException $exception) {
        if (!in_array(($exception->errorInfo[1] ?? null), [1005, 1215, 1826], true)) {
            throw $exception;
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS moderation_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id BIGINT UNSIGNED NOT NULL,
            reason VARCHAR(255) NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            moderator_id BIGINT UNSIGNED NULL,
            PRIMARY KEY (id),
            KEY idx_moderation_queue_comment (comment_id),
            KEY idx_moderation_queue_status (status, created_at),
            CONSTRAINT fk_moderation_queue_comment FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comment_likes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_comment_likes_comment_user (comment_id, user_id),
            KEY idx_comment_likes_user (user_id),
            CONSTRAINT fk_comment_likes_comment FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            CONSTRAINT fk_comment_likes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}


function combineModerationResults(array ...$results): array
{
    $status = 'published';
    $reasons = [];

    foreach ($results as $result) {
        if (($result['status'] ?? 'published') === 'rejected') {
            $status = 'rejected';
        } elseif (($result['status'] ?? 'published') === 'pending_review' && $status !== 'rejected') {
            $status = 'pending_review';
        }

        foreach (($result['reasons'] ?? []) as $reason) {
            if ($reason !== '') {
                $reasons[] = $reason;
            }
        }
    }

    return [
        'status' => $status,
        'reasons' => array_values(array_unique($reasons)),
    ];
}

function moderateCommentText(string $text): array
{
    $normalized = mb_strtolower($text);
    $reasons = [];
    $status = 'published';

    if ($text === '') {
        return ['status' => $status, 'reasons' => []];
    }

    $rejectedPatterns = [
        '/\b(?:fuck|shit|bitch|asshole)\b/iu',
        '/(?:сука|бляд|хуй|пизд|еба|ёба|мудак|долбоеб|долбоёб)/iu',
        '/(?:убей\s+себя|убейся|сдохни|ненавижу\s+тебя)/iu',
    ];

    foreach ($rejectedPatterns as $pattern) {
        if (preg_match($pattern, $normalized)) {
            return ['status' => 'rejected', 'reasons' => ['Запрещённая или оскорбительная лексика']];
        }
    }

    $pendingPatterns = [
        '/(?:лох|идиот|тупой|дурак|урод)/iu' => 'Потенциально оскорбительное выражение',
        '/(?:казино|ставки|быстрый\s+заработок|крипта\s+доход)/iu' => 'Похоже на спам или рекламу',
    ];

    foreach ($pendingPatterns as $pattern => $reason) {
        if (preg_match($pattern, $normalized)) {
            $status = 'pending_review';
            $reasons[] = $reason;
        }
    }

    if (preg_match('/(.)\1{7,}/u', $text)) {
        $status = $status === 'rejected' ? $status : 'pending_review';
        $reasons[] = 'Слишком много одинаковых символов';
    }

    if (preg_match_all('/https?:\/\/|www\./iu', $text) > 2) {
        $status = $status === 'rejected' ? $status : 'pending_review';
        $reasons[] = 'Слишком много ссылок';
    }

    if (preg_match_all('/(?:https?:\/\/|www\.|t\.me\/|bit\.ly\/)/iu', $text) >= 2 && mb_strlen($text) < 80) {
        $status = $status === 'rejected' ? $status : 'pending_review';
        $reasons[] = 'Подозрение на спам';
    }

    return ['status' => $status, 'reasons' => array_values(array_unique($reasons))];
}

function moderationMessage(string $status): string
{
    if ($status === 'pending_review') {
        return 'Комментарий отправлен на проверку модератором';
    }

    if ($status === 'rejected') {
        return 'Комментарий отклонён, так как нарушает правила платформы';
    }

    return '';
}

function uploadCommentAttachment(array $file): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [
            'path' => null,
            'type' => null,
            'target_path' => null,
            'moderation' => ['status' => 'rejected', 'reasons' => ['Ошибка загрузки вложения']],
        ];
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    $originalName = (string) ($file['name'] ?? '');
    $maxSize = 8 * 1024 * 1024;

    if ($tmpPath === '' || !is_uploaded_file($tmpPath) || $size <= 0) {
        return [
            'path' => null,
            'type' => null,
            'target_path' => null,
            'moderation' => ['status' => 'rejected', 'reasons' => ['Некорректный файл вложения']],
        ];
    }

    if ($size > $maxSize) {
        return [
            'path' => null,
            'type' => null,
            'target_path' => null,
            'moderation' => ['status' => 'pending_review', 'reasons' => ['Слишком большой файл вложения']],
        ];
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($extension, $allowedExtensions, true)) {
        return [
            'path' => null,
            'type' => null,
            'target_path' => null,
            'moderation' => ['status' => 'rejected', 'reasons' => ['Запрещённый формат вложения']],
        ];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMimeTypes = [
        'image/jpeg' => ['ext' => 'jpg', 'type' => 'image'],
        'image/png' => ['ext' => 'png', 'type' => 'image'],
        'image/webp' => ['ext' => 'webp', 'type' => 'image'],
        'image/gif' => ['ext' => 'gif', 'type' => 'gif'],
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        return [
            'path' => null,
            'type' => null,
            'target_path' => null,
            'moderation' => ['status' => 'rejected', 'reasons' => ['Опасный или неподдерживаемый MIME type вложения']],
        ];
    }

    if (($extension === 'gif' && $mimeType !== 'image/gif') || ($extension !== 'gif' && $mimeType === 'image/gif')) {
        return [
            'path' => null,
            'type' => null,
            'target_path' => null,
            'moderation' => ['status' => 'rejected', 'reasons' => ['Расширение файла не совпадает с типом вложения']],
        ];
    }

    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        return [
            'path' => null,
            'type' => null,
            'target_path' => null,
            'moderation' => ['status' => 'rejected', 'reasons' => ['Файл не является изображением']],
        ];
    }

    $attachmentModeration = ['status' => 'published', 'reasons' => []];
    if ($size > 5 * 1024 * 1024 || (int) ($imageInfo[0] ?? 0) > 5000 || (int) ($imageInfo[1] ?? 0) > 5000) {
        $attachmentModeration = ['status' => 'pending_review', 'reasons' => ['Подозрительно большое вложение']];
    }

    $uploadDirectory = __DIR__ . '/uploads/comment_attachments';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true)) {
        snapix_send_post_action_error('attachment_directory_failed', 500);
    }

    $extension = $allowedMimeTypes[$mimeType]['ext'];
    $filename = 'comment_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $targetPath = $uploadDirectory . '/' . $filename;
    $publicPath = 'uploads/comment_attachments/' . $filename;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        snapix_send_post_action_error('attachment_save_failed', 500);
    }

    return [
        'path' => $publicPath,
        'type' => $allowedMimeTypes[$mimeType]['type'],
        'target_path' => $targetPath,
        'moderation' => $attachmentModeration,
    ];
}

ensureCommentModerationStorage($pdo);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$panel = $_GET['panel'] ?? '';
$allowedPanels = ['followers', 'following', 'requests'];
if (!in_array($panel, $allowedPanels, true)) {
    $panel = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $commentsPostId = 0;
    $isAjaxPostAction = snapix_is_ajax_request() && in_array($action, ['toggle_like', 'toggle_save', 'add_comment', 'delete_post', 'hide_post', 'block_user', 'report_post', 'delete_comment', 'edit_comment', 'report_comment', 'toggle_comment_like', 'add_repost', 'modal_follow_author'], true);
    $ajaxExtra = [];
    $postId = (int) ($_POST['post_id'] ?? 0);
    $ownerId = (int) ($_POST['owner_id'] ?? 0);
    $postExists = false;
    $postOwnerId = 0;
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $redirectPanel = $_POST['redirect_panel'] ?? $panel;

    if ($action === 'delete_post' && $postId > 0 && !snapix_is_ajax_request()) {
        $deleteStmt = $pdo->prepare('UPDATE posts SET is_deleted = 1 WHERE id = :id AND user_id = :user_id');
        $deleteStmt->execute([
            'id' => $postId,
            'user_id' => $user['id'],
        ]);

        header('Location: profile.php?post_deleted=1');
        exit;
    }

    if ($action === 'accept_follow_request' && $requestId > 0) {
        $acceptStmt = $pdo->prepare("
            UPDATE followers
            SET status = 'accepted', declined_until = NULL
            WHERE id = :id AND following_id = :following_id AND status = 'pending'
        ");
        $acceptStmt->execute([
            'id' => $requestId,
            'following_id' => $user['id'],
        ]);

        header('Location: profile.php?panel=requests&request_accepted=1#requests-panel');
        exit;
    }

    if ($action === 'decline_follow_request' && $requestId > 0) {
        $declineStmt = $pdo->prepare("
            UPDATE followers
            SET status = 'declined', declined_until = DATE_ADD(NOW(), INTERVAL 3 DAY)
            WHERE id = :id AND following_id = :following_id AND status = 'pending'
        ");
        $declineStmt->execute([
            'id' => $requestId,
            'following_id' => $user['id'],
        ]);

        header('Location: profile.php?panel=requests&request_declined=1#requests-panel');
        exit;
    }

    if ($action === 'show_panel' && in_array($redirectPanel, $allowedPanels, true)) {
        header('Location: profile.php?panel=' . $redirectPanel . '#connections-panel');
        exit;
    }

    if ($action === 'delete_comment') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        if ($commentId <= 0) {
            snapix_send_post_action_error('invalid_comment', 422);
        }

        $commentStmt = $pdo->prepare('SELECT comments.id, comments.post_id, comments.user_id, comments.comment_text, comments.attachment_url, posts.user_id AS post_owner_id FROM comments INNER JOIN posts ON posts.id = comments.post_id WHERE comments.id = :id LIMIT 1');
        $commentStmt->execute(['id' => $commentId]);
        $comment = $commentStmt->fetch();
        if (!$comment) {
            snapix_send_post_action_error('comment_not_found', 404);
        }

        $canModerateComments = in_array((string) ($user['role'] ?? ''), ['admin', 'moderator'], true);
        $isPostOwner = (int) ($comment['post_owner_id'] ?? 0) === (int) $user['id'];
        if ((int) $comment['user_id'] !== (int) $user['id'] && !$isPostOwner && !$canModerateComments) {
            snapix_send_post_action_error('comment_delete_forbidden', 403);
        }

        $deletedCommentText = (string) ($comment['comment_text'] ?? '');
        $deleteCommentStmt = $pdo->prepare('DELETE FROM comments WHERE id = :id LIMIT 1');
        $deleteCommentStmt->execute(['id' => $commentId]);

        $attachmentUrl = (string) ($comment['attachment_url'] ?? '');
        if ($attachmentUrl !== '' && str_starts_with($attachmentUrl, 'uploads/comment_attachments/')) {
            $attachmentPath = __DIR__ . '/' . $attachmentUrl;
            if (is_file($attachmentPath)) {
                unlink($attachmentPath);
            }
        }

        if ((int) $comment['user_id'] !== (int) $user['id']) {
            $message = 'Ваш комментарий под публикацией #' . (int) $comment['post_id'] . ' был удалён';
            if ($deletedCommentText !== '') {
                $message = 'Ваш комментарий "' . snapix_notification_excerpt($deletedCommentText, 120) . '" под публикацией #' . (int) $comment['post_id'] . ' был удалён.';
            }

            snapix_create_notification($pdo, [
                'target_user_id' => (int) $comment['user_id'],
                'actor_user_id' => (int) $user['id'],
                'notification_type' => 'comment_deleted',
                'post_id' => (int) $comment['post_id'],
                'comment_id' => $commentId,
                'title' => 'Комментарий удалён',
                'message' => $message,
                'comment_text' => $deletedCommentText,
            ]);
        }

        if ($isPostOwner && (int) $comment['user_id'] !== (int) $comment['post_owner_id']) {
            snapix_create_notification($pdo, [
                'target_user_id' => (int) $comment['post_owner_id'],
                'actor_user_id' => (int) $user['id'],
                'notification_type' => 'comment_deleted_under_post',
                'post_id' => (int) $comment['post_id'],
                'comment_id' => $commentId,
                'title' => 'Комментарий удалён',
                'message' => 'Комментарий под вашей публикацией #' . (int) $comment['post_id'] . ' был удалён',
                'comment_text' => $deletedCommentText,
            ]);
        }

        if ($isAjaxPostAction) {
            snapix_send_post_action_json($pdo, (int) $comment['post_id'], (int) $user['id'], [
                'deleted_comment_id' => $commentId,
            ]);
        }

        header('Location: profile.php');
        exit;
    }


    if ($action === 'report_comment') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        $reportReason = trim((string) ($_POST['reason'] ?? ''));
        $allowedReasons = ['Спам', 'Оскорбления или ненависть', 'Насилие', 'Ложная информация', 'Нежелательный контент', 'Нарушение авторских прав', 'Другое'];
        if ($commentId <= 0) {
            snapix_send_post_action_error('invalid_comment', 422);
        }
        if (!in_array($reportReason, $allowedReasons, true)) {
            snapix_send_post_action_error('invalid_report_reason', 422);
        }

        $commentStmt = $pdo->prepare("SELECT comments.id, comments.user_id, comments.post_id FROM comments INNER JOIN posts ON posts.id = comments.post_id WHERE comments.id = :id AND comments.is_deleted = 0 AND (comments.status = 'published' OR comments.status IS NULL) LIMIT 1");
        $commentStmt->execute(['id' => $commentId]);
        $comment = $commentStmt->fetch();
        if (!$comment) {
            snapix_send_post_action_error('comment_not_found', 404);
        }
        if ((int) $comment['user_id'] === (int) $user['id']) {
            snapix_send_post_action_error('own_comment_report_forbidden', 403);
        }

        $duplicateReportStmt = $pdo->prepare('SELECT id FROM moderation_reports WHERE reporter_user_id = :reporter_user_id AND target_comment_id = :target_comment_id LIMIT 1');
        $duplicateReportStmt->execute([
            'reporter_user_id' => $user['id'],
            'target_comment_id' => $commentId,
        ]);
        if ($duplicateReportStmt->fetchColumn()) {
            snapix_send_post_action_error('duplicate_comment_report', 409);
        }

        $insertReportStmt = $pdo->prepare('INSERT INTO moderation_reports (reporter_user_id, target_user_id, target_comment_id, reason_text) VALUES (:reporter_user_id, :target_user_id, :target_comment_id, :reason_text)');
        $reportReasonText = mb_substr($reportReason, 0, 1000);
        $insertReportStmt->execute([
            'reporter_user_id' => $user['id'],
            'target_user_id' => (int) $comment['user_id'],
            'target_comment_id' => $commentId,
            'reason_text' => $reportReasonText,
        ]);
        $reportId = (int) $pdo->lastInsertId();
        $commentTextStmt = $pdo->prepare('SELECT comment_text FROM comments WHERE id = :id LIMIT 1');
        $commentTextStmt->execute(['id' => $commentId]);
        $reportedCommentText = (string) ($commentTextStmt->fetchColumn() ?: '');
        snapix_notify_admins($pdo, [
            'actor_user_id' => (int) $user['id'],
            'notification_type' => 'report_comment',
            'post_id' => (int) $comment['post_id'],
            'comment_id' => $commentId,
            'report_id' => $reportId,
            'title' => 'Жалоба на комментарий',
            'message' => 'Поступила жалоба на комментарий под публикацией',
            'comment_text' => $reportedCommentText,
            'report_reason' => $reportReasonText,
            'dedupe_minutes' => 10,
        ], (int) $user['id']);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'comment_id' => $commentId,
            'message' => 'Жалоба отправлена',
        ]);
        exit;
    }

    if ($action === 'edit_comment') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        $commentText = trim($_POST['comment_text'] ?? '');
        if ($commentId <= 0) {
            snapix_send_post_action_error('invalid_comment', 422);
        }
        if ($commentText === '') {
            snapix_send_post_action_error('empty_comment', 422);
        }

        $commentStmt = $pdo->prepare("SELECT id, post_id, user_id FROM comments WHERE id = :id AND is_deleted = 0 AND (status = 'published' OR status IS NULL) LIMIT 1");
        $commentStmt->execute(['id' => $commentId]);
        $comment = $commentStmt->fetch();
        if (!$comment) {
            snapix_send_post_action_error('comment_not_found', 404);
        }
        if ((int) $comment['user_id'] !== (int) $user['id']) {
            snapix_send_post_action_error('comment_edit_forbidden', 403);
        }

        $commentValue = mb_substr($commentText, 0, 1000);
        $updateCommentStmt = $pdo->prepare('UPDATE comments SET comment_text = :comment_text WHERE id = :id AND user_id = :user_id LIMIT 1');
        $updateCommentStmt->execute([
            'comment_text' => $commentValue,
            'id' => $commentId,
            'user_id' => $user['id'],
        ]);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'comment_id' => $commentId,
            'post_id' => (int) $comment['post_id'],
            'comment_text' => $commentValue,
            'text' => $commentValue,
        ]);
        exit;
    }

    if ($action === 'toggle_comment_like') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        if ($commentId <= 0) {
            snapix_send_post_action_error('invalid_comment', 422);
        }

        $commentStmt = $pdo->prepare("SELECT id FROM comments WHERE id = :id AND is_deleted = 0 AND (status = 'published' OR status IS NULL) LIMIT 1");
        $commentStmt->execute(['id' => $commentId]);
        if (!$commentStmt->fetchColumn()) {
            snapix_send_post_action_error('comment_not_found', 404);
        }

        $likeStmt = $pdo->prepare('SELECT id FROM comment_likes WHERE comment_id = :comment_id AND user_id = :user_id LIMIT 1');
        $likeStmt->execute([
            'comment_id' => $commentId,
            'user_id' => $user['id'],
        ]);
        $likeId = $likeStmt->fetchColumn();

        if ($likeId) {
            $pdo->prepare('DELETE FROM comment_likes WHERE id = :id')->execute(['id' => $likeId]);
            $liked = false;
        } else {
            $pdo->prepare('INSERT INTO comment_likes (comment_id, user_id) VALUES (:comment_id, :user_id)')->execute([
                'comment_id' => $commentId,
                'user_id' => $user['id'],
            ]);
            $liked = true;
            snapix_notify_comment_like($pdo, $commentId, (int) $user['id']);
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM comment_likes WHERE comment_id = :comment_id');
        $countStmt->execute(['comment_id' => $commentId]);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'comment_id' => $commentId,
            'liked' => $liked,
            'comment_likes_count' => (int) $countStmt->fetchColumn(),
        ]);
        exit;
    }

    if ($action === 'modal_follow_author') {
        $targetUserId = (int) ($_POST['author_id'] ?? 0);
        if ($targetUserId <= 0 || $targetUserId === (int) $user['id']) {
            snapix_send_post_action_error('invalid_target_user', 400);
        }

        $targetStmt = $pdo->prepare('SELECT id, is_private FROM users WHERE id = :id');
        $targetStmt->execute(['id' => $targetUserId]);
        $targetUser = $targetStmt->fetch();
        if (!$targetUser) {
            snapix_send_post_action_error('user_not_found', 404);
        }

        $relationStmt = $pdo->prepare('
            SELECT id, status, declined_until
            FROM followers
            WHERE follower_id = :follower_id AND following_id = :following_id
            LIMIT 1
        ');
        $relationStmt->execute([
            'follower_id' => $user['id'],
            'following_id' => $targetUserId,
        ]);
        $relation = $relationStmt->fetch();

        $isBlocked = $relation
            && $relation['status'] === 'declined'
            && !empty($relation['declined_until'])
            && strtotime((string) $relation['declined_until']) > time();
        if ($isBlocked) {
            snapix_send_post_action_error('follow_blocked', 403);
        }

        if (!$relation || $relation['status'] !== 'accepted') {
            $nextStatus = !empty($targetUser['is_private']) ? 'pending' : 'accepted';
            if ($relation) {
                $pdo->prepare('UPDATE followers SET status = :status, declined_until = NULL WHERE id = :id')
                    ->execute(['status' => $nextStatus, 'id' => $relation['id']]);
            } else {
                $pdo->prepare('INSERT INTO followers (follower_id, following_id, status, declined_until) VALUES (:follower_id, :following_id, :status, NULL)')
                    ->execute([
                        'follower_id' => $user['id'],
                        'following_id' => $targetUserId,
                        'status' => $nextStatus,
                    ]);
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($postId > 0) {
        $postExistsStmt = $pdo->prepare('SELECT id, user_id FROM posts WHERE id = :id AND is_deleted = 0');
        $postExistsStmt->execute(['id' => $postId]);
        $postRow = $postExistsStmt->fetch();
        $postExists = (bool) $postRow;
        $postOwnerId = $postExists ? (int) $postRow['user_id'] : 0;
        if ($ownerId <= 0) {
            $ownerId = $postOwnerId;
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
                snapix_notify_post_action($pdo, $postId, (int) $user['id'], 'post_like');
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
                snapix_notify_post_action($pdo, $postId, (int) $user['id'], 'post_saved');
            }
        }

        if ($postExists && $action === 'add_comment') {
            $commentText = trim($_POST['comment_text'] ?? '');
            $parentCommentId = max(0, (int) ($_POST['parent_comment_id'] ?? 0));
            $attachment = uploadCommentAttachment($_FILES['attachment'] ?? ['error' => UPLOAD_ERR_NO_FILE]);

            if ($parentCommentId > 0) {
                $parentCommentStmt = $pdo->prepare("SELECT id FROM comments WHERE id = :id AND post_id = :post_id AND is_deleted = 0 AND (status = 'published' OR status IS NULL) LIMIT 1");
                $parentCommentStmt->execute([
                    'id' => $parentCommentId,
                    'post_id' => $postId,
                ]);
                if (!$parentCommentStmt->fetchColumn()) {
                    if ($attachment && !empty($attachment['target_path']) && is_file($attachment['target_path'])) {
                        unlink($attachment['target_path']);
                    }
                    if ($isAjaxPostAction) {
                        snapix_send_post_action_error('invalid_parent_comment');
                    }
                    $parentCommentId = 0;
                }
            }

            if ($commentText !== '' || $attachment !== null) {
                $commentValue = mb_substr($commentText, 0, 1000);
                $moderation = combineModerationResults(
                    moderateCommentText($commentValue),
                    $attachment['moderation'] ?? ['status' => 'published', 'reasons' => []]
                );
                $commentStatus = $moderation['status'];
                $moderationReason = implode('; ', $moderation['reasons']);
                if ($moderationReason === '') {
                    $moderationReason = $commentStatus === 'pending_review' ? 'Требуется ручная проверка' : 'Нарушение правил платформы';
                }

                if ($commentStatus === 'rejected' && $attachment && !empty($attachment['target_path']) && is_file($attachment['target_path'])) {
                    unlink($attachment['target_path']);
                    $attachment['path'] = null;
                    $attachment['type'] = null;
                    $attachment['target_path'] = null;
                }

                $insertCommentStmt = $pdo->prepare('INSERT INTO comments (post_id, parent_comment_id, user_id, comment_text, attachment_url, attachment_type, status) VALUES (:post_id, :parent_comment_id, :user_id, :comment_text, :attachment_url, :attachment_type, :status)');

                try {
                    $insertCommentStmt->execute([
                        'post_id' => $postId,
                        'parent_comment_id' => $parentCommentId > 0 ? $parentCommentId : null,
                        'user_id' => $user['id'],
                        'comment_text' => $commentValue,
                        'attachment_url' => $commentStatus === 'rejected' ? null : ($attachment['path'] ?? null),
                        'attachment_type' => $commentStatus === 'rejected' ? null : ($attachment['type'] ?? null),
                        'status' => $commentStatus,
                    ]);
                } catch (Throwable $exception) {
                    if ($attachment && !empty($attachment['target_path']) && is_file($attachment['target_path'])) {
                        unlink($attachment['target_path']);
                    }
                    throw $exception;
                }

                $commentId = (int) $pdo->lastInsertId();
                if ($commentStatus === 'published') {
                    snapix_notify_post_action($pdo, $postId, (int) $user['id'], 'post_comment', $commentValue, $commentId);
                    if ($parentCommentId > 0) {
                        snapix_notify_comment_reply($pdo, $parentCommentId, (int) $user['id'], $commentValue, $commentId);
                    }
                }
                if ($commentStatus === 'pending_review') {
                    $queueStmt = $pdo->prepare('INSERT INTO moderation_queue (comment_id, reason) VALUES (:comment_id, :reason)');
                    $queueStmt->execute([
                        'comment_id' => $commentId,
                        'reason' => mb_substr($moderationReason, 0, 255),
                    ]);
                }

                $commentsPostId = $commentStatus === 'published' ? $postId : 0;
                $ajaxExtra['moderation_status'] = $commentStatus;
                $ajaxExtra['moderation_message'] = moderationMessage($commentStatus);
                $ajaxExtra['comment'] = $commentStatus === 'published' ? [
                    'comment_id' => $commentId,
                    'post_id' => $postId,
                    'post_owner_id' => $postOwnerId,
                    'parent_comment_id' => $parentCommentId,
                    'user_id' => (int) $user['id'],
                    'login' => (string) $user['login'],
                    'avatar_url' => (string) ($user['avatar'] ?? ''),
                    'profile_url' => buildProfileUrl((int) $user['id'], (int) $user['id']),
                    'comment_text' => $commentValue,
                    'text' => $commentValue,
                    'attachment_url' => $attachment['path'] ?? null,
                    'attachment_type' => $attachment['type'] ?? null,
                    'created_at' => 'только что',
                    'status' => $commentStatus,
                    'likes_count' => 0,
                    'is_liked' => false,
                ] : null;
            } elseif ($isAjaxPostAction) {
                snapix_send_post_action_error('empty_comment');
            }
        }

        if ($postExists && $action === 'add_repost') {
            $repostExistsStmt = $pdo->prepare('SELECT id FROM reposts WHERE user_id = :user_id AND post_id = :post_id');
            $repostExistsStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);
            $repostId = $repostExistsStmt->fetchColumn();
            if (!$repostId) {
                $insertRepostStmt = $pdo->prepare('INSERT IGNORE INTO reposts (user_id, post_id) VALUES (:user_id, :post_id)');
                $insertRepostStmt->execute([
                    'user_id' => $user['id'],
                    'post_id' => $postId,
                ]);
                $isRepostedNow = true;
                snapix_notify_post_action($pdo, $postId, (int) $user['id'], 'post_repost');
            } else {
                $deleteRepostStmt = $pdo->prepare('DELETE FROM reposts WHERE id = :id AND user_id = :user_id');
                $deleteRepostStmt->execute([
                    'id' => (int) $repostId,
                    'user_id' => $user['id'],
                ]);
                $isRepostedNow = false;
            }

            $ajaxExtra['reposted'] = $isRepostedNow;
        }

        if ($postExists && $action === 'delete_post' && $ownerId === (int) $user['id']) {
            $deleteStmt = $pdo->prepare('UPDATE posts SET is_deleted = 1 WHERE id = :id AND user_id = :user_id');
            $deleteStmt->execute([
                'id' => $postId,
                'user_id' => $user['id'],
            ]);
        }

        if ($postExists && $action === 'pin_post' && $ownerId === (int) $user['id']) {
            $pdo->prepare('INSERT IGNORE INTO pinned_posts (user_id, post_id) VALUES (:user_id, :post_id)')
                ->execute(['user_id' => $user['id'], 'post_id' => $postId]);
        }

        if ($postExists && $action === 'hide_post' && $ownerId !== (int) $user['id']) {
            $pdo->prepare('INSERT IGNORE INTO hidden_posts (user_id, post_id) VALUES (:user_id, :post_id)')
                ->execute(['user_id' => $user['id'], 'post_id' => $postId]);
        }

        if ($postExists && $action === 'block_user' && $ownerId > 0 && $ownerId !== (int) $user['id']) {
            $pdo->prepare('INSERT IGNORE INTO user_blocks (blocker_user_id, blocked_user_id) VALUES (:blocker_user_id, :blocked_user_id)')
                ->execute(['blocker_user_id' => (int) $user['id'], 'blocked_user_id' => $ownerId]);
        }

        if ($postExists && $action === 'report_post' && $ownerId !== (int) $user['id']) {
            $reportReason = trim((string) ($_POST['report_reason'] ?? ''));
            $reportReasonText = mb_substr($reportReason !== '' ? $reportReason : ('Жалоба на пост #' . $postId), 0, 1000);
            $pdo->prepare('INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text) VALUES (:reporter_user_id, :target_user_id, :reason_text)')
                ->execute([
                    'reporter_user_id' => $user['id'],
                    'target_user_id' => $ownerId > 0 ? $ownerId : null,
                    'reason_text' => $reportReasonText,
                ]);
            $reportId = (int) $pdo->lastInsertId();
            snapix_notify_admins($pdo, [
                'actor_user_id' => (int) $user['id'],
                'notification_type' => 'report_post',
                'post_id' => $postId,
                'report_id' => $reportId,
                'title' => 'Жалоба на публикацию',
                'message' => 'Поступила жалоба на публикацию',
                'report_reason' => $reportReasonText,
                'dedupe_minutes' => 10,
            ], (int) $user['id']);
        }

        if ($postExists && $action === 'report_post_user' && $ownerId !== (int) $user['id']) {
            $reportReasonText = 'Жалоба на пользователя через пост #' . $postId;
            $pdo->prepare('INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text) VALUES (:reporter_user_id, :target_user_id, :reason_text)')
                ->execute([
                    'reporter_user_id' => $user['id'],
                    'target_user_id' => $ownerId > 0 ? $ownerId : null,
                    'reason_text' => $reportReasonText,
                ]);
            $reportId = (int) $pdo->lastInsertId();
            snapix_notify_admins($pdo, [
                'actor_user_id' => (int) $user['id'],
                'notification_type' => 'report_user',
                'post_id' => $postId,
                'report_id' => $reportId,
                'title' => 'Жалоба на пользователя',
                'message' => 'Поступила жалоба на пользователя',
                'report_reason' => $reportReasonText,
                'dedupe_minutes' => 10,
            ], (int) $user['id']);
        }

        if ($isAjaxPostAction) {
            if (!$postExists) {
                snapix_send_post_action_error('post_not_found', 404);
            }

            snapix_send_post_action_json($pdo, $postId, (int) $user['id'], $ajaxExtra);
        }

        if ($commentsPostId > 0) {
            header('Location: profile.php?comments_post=' . $commentsPostId);
            exit;
        }

        header('Location: profile.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE follower_id = :id AND status = 'accepted'");
$stmt->execute(['id' => $user['id']]);
$followingCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = :id AND status = 'accepted'");
$stmt->execute(['id' => $user['id']]);
$followersCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = :id AND status = 'pending'");
$stmt->execute(['id' => $user['id']]);
$pendingRequestsCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('
    SELECT COUNT(*)
    FROM messages m
    INNER JOIN chats c ON c.id = m.chat_id
    WHERE (c.user_one_id = :user_id OR c.user_two_id = :user_id)
      AND m.sender_id != :user_id
      AND m.is_read = 0
');
$stmt->execute(['user_id' => $user['id']]);
$unreadMessagesCount = (int) $stmt->fetchColumn();

$shareRecipientsStmt = $pdo->prepare("
    SELECT
        users.id,
        users.login,
        users.avatar,
        EXISTS(
            SELECT 1
            FROM followers reverse_follow
            WHERE reverse_follow.follower_id = users.id
              AND reverse_follow.following_id = :user_id
              AND reverse_follow.status = 'accepted'
        ) AS is_mutual
    FROM followers
    INNER JOIN users ON users.id = followers.following_id
    WHERE followers.follower_id = :user_id
      AND followers.status = 'accepted'
    ORDER BY is_mutual DESC, users.login ASC
");
$shareRecipientsStmt->execute(['user_id' => $user['id']]);
$shareRecipients = $shareRecipientsStmt->fetchAll();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = :id AND is_deleted = 0');
$stmt->execute(['id' => $user['id']]);
$postsCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM saved_posts WHERE user_id = :id');
$stmt->execute(['id' => $user['id']]);
$savedPostsCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM reposts WHERE user_id = :id');
$stmt->execute(['id' => $user['id']]);
$repostsCount = (int) $stmt->fetchColumn();

$postStatsSql = "
    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS likes_count,
    (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.is_deleted = 0 AND (comments.status = 'published' OR comments.status IS NULL)) AS comments_count,
    (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id) AS reposts_count,
    (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id) AS saves_count,
    (SELECT COUNT(*) FROM messages WHERE messages.post_id = posts.id) AS shares_count,
    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.user_id = :viewer_id) AS is_liked,
    (SELECT COUNT(*) FROM saved_posts WHERE saved_posts.post_id = posts.id AND saved_posts.user_id = :viewer_id) AS is_saved,
    (SELECT COUNT(*) FROM reposts WHERE reposts.post_id = posts.id AND reposts.user_id = :viewer_id) AS is_reposted,
    (SELECT COUNT(*) FROM pinned_posts WHERE pinned_posts.post_id = posts.id AND pinned_posts.user_id = :viewer_id) AS is_pinned,
    (SELECT status FROM followers WHERE follower_id = :viewer_id AND following_id = users.id LIMIT 1) AS viewer_follow_status
";

$stmt = $pdo->prepare("
    SELECT posts.*, post_media.media_url, post_media.media_type,
           users.id AS author_user_id, users.login AS author_login,
           $postStatsSql
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.user_id = :id AND posts.is_deleted = 0
    ORDER BY posts.created_at DESC
");
$stmt->execute([
    'id' => $user['id'],
    'viewer_id' => $user['id'],
]);
$posts = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT posts.*, post_media.media_url, post_media.media_type, users.id AS author_user_id, users.login AS author_login,
           $postStatsSql
    FROM saved_posts
    INNER JOIN posts ON posts.id = saved_posts.post_id AND posts.is_deleted = 0
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE saved_posts.user_id = :id
    ORDER BY saved_posts.created_at DESC
");
$stmt->execute([
    'id' => $user['id'],
    'viewer_id' => $user['id'],
]);
$savedPosts = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        posts.id,
        posts.user_id AS author_user_id,
        posts.caption,
        posts.created_at,
        post_media.media_url,
        post_media.media_type,
        users.login AS author_login,
        users.avatar AS author_avatar,
        $postStatsSql
    FROM (
        SELECT post_id, MAX(created_at) AS last_repost_at
        FROM reposts
        WHERE user_id = :id
        GROUP BY post_id
    ) user_reposts
    INNER JOIN posts ON posts.id = user_reposts.post_id AND posts.is_deleted = 0
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    ORDER BY user_reposts.last_repost_at DESC
");
$stmt->execute([
    'id' => $user['id'],
    'viewer_id' => $user['id'],
]);
$repostedPosts = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT followers.id, followers.created_at, users.id AS user_id, users.login, users.avatar
    FROM followers
    INNER JOIN users ON users.id = followers.follower_id
    WHERE followers.following_id = :id AND followers.status = 'pending'
    ORDER BY followers.created_at DESC
");
$stmt->execute(['id' => $user['id']]);
$pendingRequests = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT users.id, users.login, users.avatar
    FROM followers
    INNER JOIN users ON users.id = followers.follower_id
    WHERE followers.following_id = :id AND followers.status = 'accepted'
    ORDER BY users.login ASC
");
$stmt->execute(['id' => $user['id']]);
$followersList = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT users.id, users.login, users.avatar
    FROM followers
    INNER JOIN users ON users.id = followers.following_id
    WHERE followers.follower_id = :id AND followers.status = 'accepted'
    ORDER BY users.login ASC
");
$stmt->execute(['id' => $user['id']]);
$followingList = $stmt->fetchAll();

$commentMap = [];
$viewerCommentMap = [];
$repostMap = [];
$allProfilePosts = array_merge($posts, $savedPosts, $repostedPosts);
if ($allProfilePosts) {
    $postIds = array_values(array_unique(array_map(static fn($post): int => (int) $post['id'], $allProfilePosts)));
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));

    $viewerUserId = (int) $user['id'];
    $commentsStmt = $pdo->prepare("
        SELECT comments.id, comments.post_id, comment_posts.user_id AS post_owner_id, comments.parent_comment_id, comments.comment_text, comments.attachment_url, comments.attachment_type, comments.created_at, users.id AS user_id, users.login, users.avatar,
               (SELECT COUNT(*) FROM comment_likes WHERE comment_likes.comment_id = comments.id) AS likes_count,
               (SELECT COUNT(*) FROM comment_likes WHERE comment_likes.comment_id = comments.id AND comment_likes.user_id = {$viewerUserId}) AS is_liked
        FROM comments
        INNER JOIN users ON users.id = comments.user_id
        INNER JOIN posts comment_posts ON comment_posts.id = comments.post_id
        WHERE comments.is_deleted = 0
          AND (comments.status = 'published' OR comments.status IS NULL)
          AND comments.post_id IN ($placeholders)
        ORDER BY comments.post_id ASC, comments.created_at DESC, comments.id DESC
    ");
    $commentsParams = $postIds;
    $commentsStmt->execute($commentsParams);
    foreach ($commentsStmt->fetchAll() as $comment) {
        $currentPostId = (int) $comment['post_id'];
        if (!isset($commentMap[$currentPostId])) {
            $commentMap[$currentPostId] = [];
        }
        $commentMap[$currentPostId][] = $comment;
        if (!isset($viewerCommentMap[$currentPostId])) {
            $viewerCommentMap[$currentPostId] = [];
        }
        $viewerCommentMap[$currentPostId][] = [
            'comment_id' => (int) $comment['id'],
            'post_id' => $currentPostId,
            'post_owner_id' => (int) ($comment['post_owner_id'] ?? 0),
            'parent_comment_id' => (int) ($comment['parent_comment_id'] ?? 0),
            'user_id' => (int) $comment['user_id'],
            'login' => (string) $comment['login'],
            'avatar_url' => (string) ($comment['avatar'] ?? ''),
            'profile_url' => buildProfileUrl((int) $comment['user_id'], (int) $user['id']),
            'comment_text' => (string) $comment['comment_text'],
            'text' => (string) $comment['comment_text'],
            'attachment_url' => (string) ($comment['attachment_url'] ?? ''),
            'attachment_type' => (string) ($comment['attachment_type'] ?? ''),
            'created_at' => !empty($comment['created_at']) ? date('d.m.Y H:i', strtotime((string) $comment['created_at'])) : '',
            'status' => 'published',
            'likes_count' => (int) ($comment['likes_count'] ?? 0),
            'is_liked' => (int) ($comment['is_liked'] ?? 0) > 0,
        ];
    }

    $repostsStmt = $pdo->prepare("
        SELECT reposts.post_id, users.login
        FROM reposts
        INNER JOIN users ON users.id = reposts.user_id
        WHERE reposts.post_id IN ($placeholders)
        ORDER BY reposts.created_at DESC, reposts.id DESC
    ");
    $repostsStmt->execute($postIds);
    foreach ($repostsStmt->fetchAll() as $repost) {
        $currentPostId = (int) $repost['post_id'];
        if (!isset($repostMap[$currentPostId])) {
            $repostMap[$currentPostId] = [];
        }
        if (count($repostMap[$currentPostId]) < 3) {
            $repostMap[$currentPostId][] = $repost['login'];
        }
    }
}

$postCreated = isset($_GET['post_created']) && $_GET['post_created'] === '1';
$postUpdated = isset($_GET['post_updated']) && $_GET['post_updated'] === '1';
$postDeleted = isset($_GET['post_deleted']) && $_GET['post_deleted'] === '1';
$requestAccepted = isset($_GET['request_accepted']) && $_GET['request_accepted'] === '1';
$requestDeclined = isset($_GET['request_declined']) && $_GET['request_declined'] === '1';
$showRequestsBlock = $panel === 'requests' || !empty($pendingRequests) || $requestAccepted || $requestDeclined;
$showFollowersPanel = $panel === 'followers';
$showFollowingPanel = $panel === 'following';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <link rel="icon" href="icon/light theme/logo.png" type="image/png">
    <title>Snapix</title>
</head>
<body data-page="profile" class="has-side-menu">
    <?php render_side_menu($user); ?>

    <main class="profile-page">
        <section class="profile-hero">
            <section class="profile-cover card-surface<?php echo !empty($user['background_image']) ? ' has-image' : ''; ?>"<?php if (!empty($user['background_image'])): ?> style="background-image: url('<?php echo htmlspecialchars($user['background_image']); ?>');"<?php endif; ?>></section>

            <section class="profile-summary">
                <div class="profile-avatar-shell">
                        <?php if (!empty($user['avatar'])): ?>
                            <div class="profile-avatar" style="background-image: url('<?php echo htmlspecialchars($user['avatar']); ?>');"></div>
                        <?php else: ?>
                            <div class="profile-avatar"><?php echo htmlspecialchars(mb_substr($user['login'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                <div class="profile-header">

                <div class="profile-main">
                    <div class="profile-name-row">
                        <h1 class="profile-username"><?php echo htmlspecialchars($user['login']); ?></h1>
                        <?php if (!empty($user['is_private'])): ?>
                            <img src="icon/light theme/closed account.png" alt="Закрытый профиль" class="profile-private-icon">
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($user['bio'])): ?>
                        <p class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                    <?php endif; ?>

                    <div class="profile-metrics">
                        <span><strong><?php echo $postsCount; ?></strong> публикаций</span>
                        <a href="connections.php?view=followers" class="profile-metric-inline-link"><strong><?php echo $followersCount; ?></strong> смотрители</a>
                        <a href="connections.php?view=following" class="profile-metric-inline-link"><strong><?php echo $followingCount; ?></strong> смотримые</a>
                    </div>
                    <a href="create-post.php" class="profile-create-btn" aria-label="Создать публикацию">+</a>
                </div>

                <div class="profile-actions">
                    <a href="edit-profile.php" class="secondary-link profile-edit-btn">Изменить профиль</a>
                    <a href="create-post.php" class="secondary-link profile-logout-btn">Добавить публикацию</a>
                </div>
                </div>
            </section>

            <section class="profile-tabs-line">
                <div class="profile-post-tabs home-feed-tabs" role="tablist" aria-label="Разделы профиля">
                    <button type="button" class="profile-post-tab home-feed-tab is-active" data-profile-tab-button="publications">Посты</button>
                    <button type="button" class="profile-post-tab home-feed-tab" data-profile-tab-button="reposts">Репосты</button>
                    <button type="button" class="profile-post-tab home-feed-tab" data-profile-tab-button="favourites">Избранное</button>
                    <button type="button" class="profile-post-tab home-feed-tab" data-profile-tab-button="likes">Нравится</button>
                    <button type="button" class="profile-post-tab home-feed-tab" data-profile-tab-button="archives">Архивы</button>
                </div>
            </section>
        </section>

        <div class="notification-popover" id="notificationPopover">
            <div class="notification-popover-header">
                <strong>Заявки</strong>
                <a href="connections.php?view=requests">Открыть все</a>
            </div>

            <?php if ($pendingRequests): ?>
                <div class="notification-popover-list">
                    <?php foreach (array_slice($pendingRequests, 0, 5) as $request): ?>
                        <article class="notification-popover-item">
                            <a href="<?php echo htmlspecialchars(buildProfileUrl((int) $request['user_id'], (int) $user['id'])); ?>" class="request-user">
                                <span class="request-avatar"<?php if (!empty($request['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($request['avatar']); ?>');"<?php endif; ?>>
                                    <?php if (empty($request['avatar'])): ?>
                                        <?php echo htmlspecialchars(mb_substr($request['login'], 0, 1)); ?>
                                    <?php endif; ?>
                                </span>
                                <span class="request-copy">
                                    <strong><?php echo htmlspecialchars($request['login']); ?></strong>
                                    <span>Хочет подружиться с вами</span>
                                </span>
                            </a>
                            <div class="notification-popover-actions">
                                <form method="post">
                                    <input type="hidden" name="action" value="accept_follow_request">
                                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                    <button type="submit" class="primary-link">Принять</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="action" value="decline_follow_request">
                                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                    <button type="submit" class="secondary-link">Отклонить</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="notification-popover-empty">Новых заявок нет.</p>
            <?php endif; ?>
        </div>

        <?php if ($showRequestsBlock): ?>
            <section id="requests-panel" class="profile-posts card-surface">
                <div class="section-heading">
                    <h2>Заявки в подписчики</h2>
                </div>

                <?php if ($requestAccepted): ?>
                    <p class="form-status is-success">Заявка принята. Пользователь добавлен в подписчики.</p>
                <?php endif; ?>

                <?php if ($requestDeclined): ?>
                    <p class="form-status is-success">Заявка отклонена. Повторная заявка временно заблокирована.</p>
                <?php endif; ?>

                <?php if ($pendingRequests): ?>
                    <div class="request-list">
                        <?php foreach ($pendingRequests as $request): ?>
                            <article class="request-card">
                                <a href="<?php echo htmlspecialchars(buildProfileUrl((int) $request['user_id'], (int) $user['id'])); ?>" class="request-user">
                                    <span class="request-avatar"<?php if (!empty($request['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($request['avatar']); ?>');"<?php endif; ?>>
                                        <?php if (empty($request['avatar'])): ?>
                                            <?php echo htmlspecialchars(mb_substr($request['login'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="request-copy">
                                        <strong><?php echo htmlspecialchars($request['login']); ?></strong>
                                        <span>Этот пользователь хочет подружиться с вами</span>
                                    </span>
                                </a>

                                <div class="request-actions">
                                    <form method="post">
                                        <input type="hidden" name="action" value="accept_follow_request">
                                        <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                        <button type="submit" class="primary-link">Принять</button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="action" value="decline_follow_request">
                                        <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                        <button type="submit" class="secondary-link">Отклонить</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">Новых заявок пока нет.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($showFollowersPanel || $showFollowingPanel): ?>
            <section id="connections-panel" class="profile-posts card-surface">
                <div class="section-heading">
                    <h2><?php echo $showFollowersPanel ? 'Подписчики' : 'Подписки'; ?></h2>
                </div>

                <?php $connectionList = $showFollowersPanel ? $followersList : $followingList; ?>
                <?php if ($connectionList): ?>
                    <div class="request-list">
                        <?php foreach ($connectionList as $connectionUser): ?>
                            <a href="<?php echo htmlspecialchars(buildProfileUrl((int) $connectionUser['id'], (int) $user['id'])); ?>" class="request-card request-card-link">
                                <span class="request-user">
                                    <span class="request-avatar"<?php if (!empty($connectionUser['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($connectionUser['avatar']); ?>');"<?php endif; ?>>
                                        <?php if (empty($connectionUser['avatar'])): ?>
                                            <?php echo htmlspecialchars(mb_substr($connectionUser['login'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="request-copy">
                                        <strong><?php echo htmlspecialchars($connectionUser['login']); ?></strong>
                                        <span><?php echo $showFollowersPanel ? 'Подписан на вас' : 'Вы подписаны'; ?></span>
                                    </span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state"><?php echo $showFollowersPanel ? 'Подписчиков пока нет.' : 'Подписок пока нет.'; ?></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="profile-posts profile-posts-stream" data-profile-tab-panel="publications">

            <?php if ($postCreated): ?>
                <p class="form-status is-success">Публикация успешно добавлена.</p>
            <?php endif; ?>

            <?php if ($postUpdated): ?>
                <p class="form-status is-success">Публикация успешно обновлена.</p>
            <?php endif; ?>

            <?php if ($postDeleted): ?>
                <p class="form-status is-success">Публикация удалена.</p>
            <?php endif; ?>

            <?php if ($posts): ?>
                <div class="posts-grid profile-media-grid">
                    <?php foreach ($posts as $post): ?>
                        <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                        <?php $postReposters = $repostMap[(int) $post['id']] ?? []; ?>
                        <article class="post-card" id="post-<?php echo (int) $post['id']; ?>" data-post-card-id="<?php echo (int) $post['id']; ?>" data-post-id="<?php echo (int) $post['id']; ?>" data-post-author-id="<?php echo (int) ($post['author_user_id'] ?? $post['user_id'] ?? 0); ?>" data-post-media-url="<?php echo htmlspecialchars((string) ($post['media_url'] ?? '')); ?>" data-post-media-type="<?php echo htmlspecialchars((string) ($post['media_type'] ?? 'image')); ?>" data-post-author-login="<?php echo htmlspecialchars((string) ($post['author_login'] ?? $user['login'])); ?>" data-post-author-avatar="<?php echo htmlspecialchars((string) ($post['author_avatar'] ?? $user['avatar'] ?? '')); ?>" data-post-caption="<?php echo htmlspecialchars((string) ($post['caption'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-post-hashtags="<?php echo htmlspecialchars(implode(' ', snapix_split_safe_hashtags((string) ($post['hashtags'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>" data-post-likes-count="<?php echo (int) ($post['likes_count'] ?? 0); ?>" data-post-comments-count="<?php echo (int) ($post['comments_count'] ?? 0); ?>" data-post-reposts-count="<?php echo (int) ($post['reposts_count'] ?? 0); ?>" data-post-shares-count="<?php echo (int) ($post['shares_count'] ?? 0); ?>" data-post-saves-count="<?php echo (int) ($post['saves_count'] ?? 0); ?>" data-post-liked="<?php echo (int) $post['is_liked'] > 0 ? '1' : '0'; ?>" data-post-saved="<?php echo (int) $post['is_saved'] > 0 ? '1' : '0'; ?>" data-post-reposted="<?php echo (int) $post['is_reposted'] > 0 ? '1' : '0'; ?>" data-post-is-following-author="<?php echo in_array((string) ($post['viewer_follow_status'] ?? ''), ['accepted', 'pending'], true) ? '1' : '0'; ?>" data-post-viewer-follow-status="<?php echo htmlspecialchars((string) ($post['viewer_follow_status'] ?? '')); ?>">
                            <span class="post-type-badge" aria-hidden="true">
                                <img src="<?php echo (($post['media_type'] ?? '') === 'video') ? 'icon/light theme/video.png' : 'icon/light theme/images.png'; ?>" alt="">
                            </span>
                            <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                <video class="post-card-media" controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                            <?php elseif (!empty($post['media_url'])): ?>
                                <div class="post-card-media" style="background-image: url('<?php echo htmlspecialchars($post['media_url']); ?>');"></div>
                            <?php else: ?>
                                <div class="post-card-media"></div>
                            <?php endif; ?>
                            <div class="post-hover-overlay">
                                <div class="profile-hover-action-item">
                                <form method="post" class="inline-action-form profile-hover-action-form">
                                    <input type="hidden" name="action" value="toggle_like">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                    <button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-like-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" data-hover-like-post-id="<?php echo (int) $post['id']; ?>" aria-label="Лайк">
                                        <img src="icon/dark theme/like.png" alt="Лайк">
                                    </button>
                                </form>
                                <span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span>
                            </div>
                                <div class="profile-hover-action-item">
                                <form method="post" class="inline-action-form profile-hover-action-form">
                                    <input type="hidden" name="action" value="add_repost">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                    <button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-repost-btn<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" data-hover-repost-post-id="<?php echo (int) $post['id']; ?>" aria-label="Репост">
                                        <img src="icon/dark theme/repost.png" alt="Репост">
                                    </button>
                                </form>
                                <span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span>
                            </div>
                                <div class="profile-hover-action-item">
                                <form method="post" class="inline-action-form profile-hover-action-form">
                                    <input type="hidden" name="action" value="toggle_save">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                    <button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-save-btn<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" data-hover-save-post-id="<?php echo (int) $post['id']; ?>" aria-label="Избранное">
                                        <img src="icon/dark theme/favourites.png" alt="Избранное">
                                    </button>
                                </form>
                                <span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span>
                            </div>
                            </div>

                            <div class="post-card-copy">
                                <div class="feed-card-header">
                                    <div class="feed-header-main"><strong><?php echo htmlspecialchars($user['login']); ?></strong></div>
                                    <div class="post-menu-wrap">
                                        <button type="button" class="post-menu-toggle" data-post-menu="profile-post-menu-<?php echo (int) $post['id']; ?>" aria-label="Действия с публикацией"><?php echo snapix_icon('more-horizontal'); ?></button>
                                        <div class="post-menu" id="profile-post-menu-<?php echo (int) $post['id']; ?>">
                                            <form method="post"><input type="hidden" name="action" value="delete_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $user['id']; ?>"><button type="submit">Удалить пост</button></form>
                                            <a href="edit-post.php?id=<?php echo (int) $post['id']; ?>">Редактировать пост</a>
                                            <form method="post"><input type="hidden" name="action" value="pin_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $user['id']; ?>"><button type="submit"><?php echo (int) $post['is_pinned'] > 0 ? 'Уже закреплено' : 'Закрепить пост в личном профиле'; ?></button></form>
                                            <a href="post-insights.php?post_id=<?php echo (int) $post['id']; ?>">Кто посмотрел пост</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="feed-card-stats"><span>Действия с публикацией</span></div>
                                <div class="feed-card-buttons" style="margin-top: 10px;">
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_like"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><img src="icon/dark theme/like.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-profile-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_save"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><img src="icon/dark theme/favourites.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="add_repost"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><img src="icon/dark theme/repost.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-share-modal" data-post-id="<?php echo (int) $post['id']; ?>" data-post-action="share" aria-label="Отправить в сообщения"><img src="icon/dark theme/share.png" alt=""></button><span class="feed-action-count" data-post-id="<?php echo (int) $post['id']; ?>" data-post-count="shares"><?php echo (int) ($post['shares_count'] ?? 0); ?></span></div>
                                </div>
                                <?php if ($postReposters): ?>
                                    <p class="feed-reposts-note">Репостнули: <?php echo htmlspecialchars(implode(', ', $postReposters)); ?></p>
                                <?php endif; ?>
                                <div class="comments-modal" id="comments-modal-profile-<?php echo (int) $post['id']; ?>">
                                    <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-profile-<?php echo (int) $post['id']; ?>"></div>
                                    <div class="comments-modal-dialog">
                                        <div class="comments-modal-header">
                                            <h3>Комментарии</h3>
                                            <button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-profile-<?php echo (int) $post['id']; ?>" aria-label="Закрыть"><?php echo snapix_icon('x'); ?></button>
                                        </div>
                                        <div class="comments-modal-body">
                                    <?php if ($postComments): ?>
                                        <?php foreach ($postComments as $comment): ?>
                                            <div class="comment-item">
                                                <strong><?php echo htmlspecialchars($comment['login']); ?></strong>
                                                <p><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="comments-empty">Пока нет комментариев.</p>
                                    <?php endif; ?>
                                        </div>
                                        <form method="post" class="comment-form comments-modal-form">
                                            <input type="hidden" name="action" value="add_comment">
                                            <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                            <textarea name="comment_text" rows="2" maxlength="1000" placeholder="Напишите комментарий..."></textarea>
                                            <button type="submit" class="primary-link">Отправить</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Пока нет публикаций. Добавьте первую публикацию.</p>
            <?php endif; ?>
        </section>

        <section class="profile-posts card-surface" data-profile-tab-panel="reposts" hidden>
            <?php if ($repostedPosts): ?>
                <div class="posts-grid profile-media-grid">
                    <?php foreach ($repostedPosts as $post): ?>
                        <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                        <?php $postReposters = $repostMap[(int) $post['id']] ?? []; ?>
                        <article class="post-card" id="repost-<?php echo (int) $post['id']; ?>" data-post-card-id="<?php echo (int) $post['id']; ?>" data-post-id="<?php echo (int) $post['id']; ?>" data-post-author-id="<?php echo (int) ($post['author_user_id'] ?? $post['user_id'] ?? 0); ?>" data-post-media-url="<?php echo htmlspecialchars((string) ($post['media_url'] ?? '')); ?>" data-post-media-type="<?php echo htmlspecialchars((string) ($post['media_type'] ?? 'image')); ?>" data-post-author-login="<?php echo htmlspecialchars((string) ($post['author_login'] ?? $user['login'])); ?>" data-post-author-avatar="<?php echo htmlspecialchars((string) ($post['author_avatar'] ?? $user['avatar'] ?? '')); ?>" data-post-caption="<?php echo htmlspecialchars((string) ($post['caption'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-post-hashtags="<?php echo htmlspecialchars(implode(' ', snapix_split_safe_hashtags((string) ($post['hashtags'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>" data-post-likes-count="<?php echo (int) ($post['likes_count'] ?? 0); ?>" data-post-comments-count="<?php echo (int) ($post['comments_count'] ?? 0); ?>" data-post-reposts-count="<?php echo (int) ($post['reposts_count'] ?? 0); ?>" data-post-shares-count="<?php echo (int) ($post['shares_count'] ?? 0); ?>" data-post-saves-count="<?php echo (int) ($post['saves_count'] ?? 0); ?>" data-post-liked="<?php echo (int) $post['is_liked'] > 0 ? '1' : '0'; ?>" data-post-saved="<?php echo (int) $post['is_saved'] > 0 ? '1' : '0'; ?>" data-post-reposted="<?php echo (int) $post['is_reposted'] > 0 ? '1' : '0'; ?>" data-post-is-following-author="<?php echo in_array((string) ($post['viewer_follow_status'] ?? ''), ['accepted', 'pending'], true) ? '1' : '0'; ?>" data-post-viewer-follow-status="<?php echo htmlspecialchars((string) ($post['viewer_follow_status'] ?? '')); ?>">
                            <span class="post-type-badge" aria-hidden="true">
                                <img src="<?php echo (($post['media_type'] ?? '') === 'video') ? 'icon/light theme/video.png' : 'icon/light theme/images.png'; ?>" alt="">
                            </span>
                            <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                <video class="post-card-media" controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                            <?php elseif (!empty($post['media_url'])): ?>
                                <div class="post-card-media" style="background-image: url('<?php echo htmlspecialchars($post['media_url']); ?>');"></div>
                            <?php else: ?>
                                <div class="post-card-media"></div>
                            <?php endif; ?>
                            <div class="post-hover-overlay">
                                <div class="profile-hover-action-item">
                                <form method="post" class="inline-action-form profile-hover-action-form">
                                    <input type="hidden" name="action" value="toggle_like">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                    <button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-like-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" data-hover-like-post-id="<?php echo (int) $post['id']; ?>" aria-label="Лайк">
                                        <img src="icon/dark theme/like.png" alt="Лайк">
                                    </button>
                                </form>
                                <span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span>
                            </div>
                                <div class="profile-hover-action-item">
                                <form method="post" class="inline-action-form profile-hover-action-form">
                                    <input type="hidden" name="action" value="add_repost">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                    <button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-repost-btn<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" data-hover-repost-post-id="<?php echo (int) $post['id']; ?>" aria-label="Репост">
                                        <img src="icon/dark theme/repost.png" alt="Репост">
                                    </button>
                                </form>
                                <span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span>
                            </div>
                                <div class="profile-hover-action-item">
                                <form method="post" class="inline-action-form profile-hover-action-form">
                                    <input type="hidden" name="action" value="toggle_save">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                    <button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-save-btn<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" data-hover-save-post-id="<?php echo (int) $post['id']; ?>" aria-label="Избранное">
                                        <img src="icon/dark theme/favourites.png" alt="Избранное">
                                    </button>
                                </form>
                                <span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span>
                            </div>
                            </div>
                            <div class="post-card-copy">
                                <div class="feed-card-header">
                                    <div class="feed-header-main"><strong><?php echo htmlspecialchars($post['author_login']); ?></strong></div>
                                </div>
                                <a href="<?php echo htmlspecialchars(buildProfileUrl((int) $post['author_user_id'], (int) $user['id'])); ?>" class="saved-post-author">
                                    <?php echo htmlspecialchars($post['author_login']); ?>
                                </a>
                                <?php if (!empty($post['caption'])): ?><p><?php echo nl2br(htmlspecialchars($post['caption'])); ?></p><?php endif; ?>
                                <div class="feed-card-buttons" style="margin-top: 10px;">
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_like"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><img src="icon/dark theme/like.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-repost-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_save"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><img src="icon/dark theme/favourites.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="add_repost"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><img src="icon/dark theme/repost.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-share-modal" data-post-id="<?php echo (int) $post['id']; ?>" data-post-action="share" aria-label="Отправить в сообщения"><img src="icon/dark theme/share.png" alt=""></button><span class="feed-action-count" data-post-id="<?php echo (int) $post['id']; ?>" data-post-count="shares"><?php echo (int) ($post['shares_count'] ?? 0); ?></span></div>
                                </div>
                                <?php if ($postReposters): ?>
                                    <p class="feed-reposts-note">Репостнули: <?php echo htmlspecialchars(implode(', ', $postReposters)); ?></p>
                                <?php endif; ?>
                                <div class="comments-modal" id="comments-modal-repost-<?php echo (int) $post['id']; ?>">
                                    <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-repost-<?php echo (int) $post['id']; ?>"></div>
                                    <div class="comments-modal-dialog">
                                        <div class="comments-modal-header">
                                            <h3>Комментарии</h3>
                                            <button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-repost-<?php echo (int) $post['id']; ?>" aria-label="Закрыть"><?php echo snapix_icon('x'); ?></button>
                                        </div>
                                        <div class="comments-modal-body">
                                            <?php if ($postComments): ?>
                                                <?php foreach ($postComments as $comment): ?>
                                                    <div class="comment-item">
                                                        <strong><?php echo htmlspecialchars($comment['login']); ?></strong>
                                                        <p><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="comments-empty">Пока нет комментариев.</p>
                                            <?php endif; ?>
                                        </div>
                                        <form method="post" class="comment-form comments-modal-form">
                                            <input type="hidden" name="action" value="add_comment">
                                            <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                            <textarea name="comment_text" rows="2" maxlength="1000" placeholder="Напишите комментарий..."></textarea>
                                            <button type="submit" class="primary-link">Отправить</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="profile-posts card-surface" data-profile-tab-panel="favourites" hidden>

            <?php if ($savedPosts): ?>
                <div class="posts-grid profile-media-grid">
                    <?php foreach ($savedPosts as $post): ?>
                        <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                        <?php $postReposters = $repostMap[(int) $post['id']] ?? []; ?>
                        <article class="post-card" id="post-<?php echo (int) $post['id']; ?>" data-post-card-id="<?php echo (int) $post['id']; ?>" data-post-id="<?php echo (int) $post['id']; ?>" data-post-author-id="<?php echo (int) ($post['author_user_id'] ?? $post['user_id'] ?? 0); ?>" data-post-media-url="<?php echo htmlspecialchars((string) ($post['media_url'] ?? '')); ?>" data-post-media-type="<?php echo htmlspecialchars((string) ($post['media_type'] ?? 'image')); ?>" data-post-author-login="<?php echo htmlspecialchars((string) ($post['author_login'] ?? $user['login'])); ?>" data-post-author-avatar="<?php echo htmlspecialchars((string) ($post['author_avatar'] ?? $user['avatar'] ?? '')); ?>" data-post-caption="<?php echo htmlspecialchars((string) ($post['caption'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-post-hashtags="<?php echo htmlspecialchars(implode(' ', snapix_split_safe_hashtags((string) ($post['hashtags'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>" data-post-likes-count="<?php echo (int) ($post['likes_count'] ?? 0); ?>" data-post-comments-count="<?php echo (int) ($post['comments_count'] ?? 0); ?>" data-post-reposts-count="<?php echo (int) ($post['reposts_count'] ?? 0); ?>" data-post-shares-count="<?php echo (int) ($post['shares_count'] ?? 0); ?>" data-post-saves-count="<?php echo (int) ($post['saves_count'] ?? 0); ?>" data-post-liked="<?php echo (int) $post['is_liked'] > 0 ? '1' : '0'; ?>" data-post-saved="<?php echo (int) $post['is_saved'] > 0 ? '1' : '0'; ?>" data-post-reposted="<?php echo (int) $post['is_reposted'] > 0 ? '1' : '0'; ?>" data-post-is-following-author="<?php echo in_array((string) ($post['viewer_follow_status'] ?? ''), ['accepted', 'pending'], true) ? '1' : '0'; ?>" data-post-viewer-follow-status="<?php echo htmlspecialchars((string) ($post['viewer_follow_status'] ?? '')); ?>">
                            <span class="post-type-badge" aria-hidden="true">
                                <img src="<?php echo (($post['media_type'] ?? '') === 'video') ? 'icon/light theme/video.png' : 'icon/light theme/images.png'; ?>" alt="">
                            </span>
                            <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                <video class="post-card-media" controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                            <?php elseif (!empty($post['media_url'])): ?>
                                <div class="post-card-media" style="background-image: url('<?php echo htmlspecialchars($post['media_url']); ?>');"></div>
                            <?php else: ?>
                                <div class="post-card-media"></div>
                            <?php endif; ?>
                            <div class="post-hover-overlay">
                                <div class="profile-hover-action-item">
                                <form method="post" class="inline-action-form profile-hover-action-form">
                                    <input type="hidden" name="action" value="toggle_like">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                    <button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-like-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" data-hover-like-post-id="<?php echo (int) $post['id']; ?>" aria-label="Лайк">
                                        <img src="icon/dark theme/like.png" alt="Лайк">
                                    </button>
                                </form>
                                <span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span>
                            </div>
                                <div class="profile-hover-action-item">
                                <form method="post" class="inline-action-form profile-hover-action-form">
                                    <input type="hidden" name="action" value="add_repost">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                    <button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-repost-btn<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" data-hover-repost-post-id="<?php echo (int) $post['id']; ?>" aria-label="Репост">
                                        <img src="icon/dark theme/repost.png" alt="Репост">
                                    </button>
                                </form>
                                <span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span>
                            </div>
                                <div class="profile-hover-action-item">
                                <form method="post" class="inline-action-form profile-hover-action-form">
                                    <input type="hidden" name="action" value="toggle_save">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                    <button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-save-btn<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" data-hover-save-post-id="<?php echo (int) $post['id']; ?>" aria-label="Избранное">
                                        <img src="icon/dark theme/favourites.png" alt="Избранное">
                                    </button>
                                </form>
                                <span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span>
                            </div>
                            </div>
                            <div class="post-card-copy">
                                <div class="feed-card-header">
                                    <div class="feed-header-main"><strong><?php echo htmlspecialchars($post['author_login']); ?></strong></div>
                                    <div class="post-menu-wrap">
                                        <button type="button" class="post-menu-toggle" data-post-menu="profile-saved-post-menu-<?php echo (int) $post['id']; ?>" aria-label="Действия с публикацией"><?php echo snapix_icon('more-horizontal'); ?></button>
                                        <div class="post-menu" id="profile-saved-post-menu-<?php echo (int) $post['id']; ?>">
                                            <?php if ((int) $post['author_user_id'] === (int) $user['id']): ?>
                                                <form method="post"><input type="hidden" name="action" value="delete_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $user['id']; ?>"><button type="submit">Удалить пост</button></form>
                                                <a href="edit-post.php?id=<?php echo (int) $post['id']; ?>">Редактировать пост</a>
                                                <form method="post"><input type="hidden" name="action" value="pin_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $user['id']; ?>"><button type="submit"><?php echo (int) $post['is_pinned'] > 0 ? 'Уже закреплено' : 'Закрепить пост в личном профиле'; ?></button></form>
                                                <a href="post-insights.php?post_id=<?php echo (int) $post['id']; ?>">Кто посмотрел пост</a>
                                            <?php else: ?>
                                                <form method="post"><input type="hidden" name="action" value="report_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $post['author_user_id']; ?>"><button type="submit" data-report-trigger data-report-login="<?php echo htmlspecialchars((string) ($post['author_login'] ?? 'user')); ?>" data-report-user-id="<?php echo (int) $post['author_user_id']; ?>">Жалоба на пост</button></form>
                                                <form method="post"><input type="hidden" name="action" value="report_post_user"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $post['author_user_id']; ?>"><button type="submit">Жалоба на пользователя</button></form>
                                                <form method="post"><input type="hidden" name="action" value="hide_post"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><input type="hidden" name="owner_id" value="<?php echo (int) $post['author_user_id']; ?>"><button type="submit">Мне не интересна эта публикация</button></form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars(buildProfileUrl((int) $post['author_user_id'], (int) $user['id'])); ?>" class="saved-post-author">
                                    <?php echo htmlspecialchars($post['author_login']); ?>
                                </a>

                                <div class="feed-card-buttons" style="margin-top: 10px;">
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_like"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><img src="icon/dark theme/like.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-saved-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="toggle_save"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><img src="icon/dark theme/favourites.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                    <div class="feed-action-item"><form method="post" class="inline-action-form"><input type="hidden" name="action" value="add_repost"><input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>"><button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><img src="icon/dark theme/repost.png" alt=""></button></form><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                    <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-share-modal" data-post-id="<?php echo (int) $post['id']; ?>" data-post-action="share" aria-label="Отправить в сообщения"><img src="icon/dark theme/share.png" alt=""></button><span class="feed-action-count" data-post-id="<?php echo (int) $post['id']; ?>" data-post-count="shares"><?php echo (int) ($post['shares_count'] ?? 0); ?></span></div>
                                </div>
                                <div class="comments-modal" id="comments-modal-saved-<?php echo (int) $post['id']; ?>">
                                    <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-saved-<?php echo (int) $post['id']; ?>"></div>
                                    <div class="comments-modal-dialog">
                                        <div class="comments-modal-header">
                                            <h3>Комментарии</h3>
                                            <button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-saved-<?php echo (int) $post['id']; ?>" aria-label="Закрыть"><?php echo snapix_icon('x'); ?></button>
                                        </div>
                                        <div class="comments-modal-body">
                                    <?php if ($postComments): ?>
                                        <?php foreach ($postComments as $comment): ?>
                                            <div class="comment-item">
                                                <strong><?php echo htmlspecialchars($comment['login']); ?></strong>
                                                <p><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="comments-empty">Пока нет комментариев.</p>
                                    <?php endif; ?>
                                        </div>
                                        <form method="post" class="comment-form comments-modal-form">
                                            <input type="hidden" name="action" value="add_comment">
                                            <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                            <textarea name="comment_text" rows="2" maxlength="1000" placeholder="Напишите комментарий..."></textarea>
                                            <button type="submit" class="primary-link">Отправить</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Здесь будут публикации, которые вы добавите в избранное</p>
            <?php endif; ?>
        </section>
        <section class="profile-posts card-surface" data-profile-tab-panel="likes" hidden>
            <p class="empty-state">Понравившиеся публикации появятся здесь</p>
        </section>
        <section class="profile-posts card-surface" data-profile-tab-panel="archives" hidden>
            <p class="empty-state">Здесь будут архивы историй</p>
        </section>

    </main>
    <div class="profile-post-viewer" id="profilePostViewer" aria-hidden="true">
        <div class="profile-post-viewer-overlay" data-post-viewer-close></div>
        <div class="profile-post-viewer-dialog" role="dialog" aria-modal="true" aria-label="Просмотр публикации">
            <div class="post-viewer-menu-wrap" data-post-viewer-menu-wrap>
                <button type="button" class="post-viewer-menu-toggle" data-post-viewer-menu-toggle aria-label="Действия с публикацией" aria-expanded="false">•••</button>
                <div class="post-viewer-menu" data-post-viewer-menu hidden>
                <div class="post-viewer-menu-own is-hidden">
                    <button type="button" class="post-viewer-menu-item" data-post-viewer-menu-action="edit_post">
                        <img src="icon/dark theme/edd.png" alt="">
                        <span>Редактировать</span>
                    </button>
                    <button type="button" class="post-viewer-menu-item" data-post-viewer-menu-action="open_stats">
                        <img src="icon/dark theme/analytic.png" alt="">
                        <span>Кто посмотрел пост</span>
                    </button>
                    <button type="button" class="post-viewer-menu-item post-viewer-menu-item-danger" data-post-viewer-menu-action="delete_post">
                        <img src="icon/trash.png" alt="">
                        <span>Удалить пост</span>
                    </button>
                </div>
                <div class="post-viewer-menu-foreign is-hidden">
                    <button type="button" class="post-viewer-menu-item" data-post-viewer-menu-action="hide_post">
                        <img src="icon/dark theme/dislike.png" alt="">
                        <span>Не интересно</span>
                    </button>
                    <button type="button" class="post-viewer-menu-item" data-post-viewer-menu-action="block_user">
                        <img src="icon/dark theme/stop.png" alt="">
                        <span data-post-viewer-block-label>Добавить пользователя в чёрный список</span>
                    </button>
                    <button type="button" class="post-viewer-menu-item post-viewer-menu-item-danger" data-post-viewer-menu-action="report_post">
                        <img src="icon/complaint.png" alt="">
                        <span>Пожаловаться</span>
                    </button>
                </div>
            </div>
            </div>
            <button type="button" class="profile-post-viewer-close" data-post-viewer-close aria-label="Закрыть">×</button>
            <div class="profile-post-viewer-media" id="profilePostViewerMedia"></div>
            <aside class="profile-post-viewer-side">
                <header class="profile-post-viewer-head">
                    <div class="profile-post-viewer-author">
                        <a href="#" class="profile-post-viewer-author-link" id="profilePostViewerAvatarLink"><span class="profile-post-viewer-avatar" id="profilePostViewerAvatar"></span></a>
                        <a href="#" class="profile-post-viewer-login-link-name" id="profilePostViewerLoginLink"><strong id="profilePostViewerLogin"></strong></a>
                        <button type="button" class="profile-post-viewer-follow" id="profilePostViewerFollow">Подписаться</button>
                    </div>
                </header>
                <div class="post-viewer-caption" data-post-viewer-caption hidden></div>
                <div class="post-viewer-hashtags" data-post-viewer-hashtags hidden></div>
                <div class="profile-post-viewer-comments" id="profilePostViewerComments">
                    <p class="profile-post-viewer-empty">Комментариев нет</p>
                </div>
                <div class="profile-post-viewer-metrics" data-post-id="">
                    <div class="profile-post-viewer-metric"><button type="button" class="profile-post-viewer-action" data-post-id="" data-post-action="like" aria-label="Лайк"><img class="icon-dark" src="icon/dark theme/like.png" alt=""><img class="icon-light" src="icon/light theme/like.png" alt=""></button><span id="viewerLikesCount" data-post-id="" data-post-count="likes">0</span></div>
                    <div class="profile-post-viewer-metric"><button type="button" class="profile-post-viewer-action" data-post-id="" data-post-action="comment" aria-label="Комментарии"><img class="icon-dark" src="icon/dark theme/comment.png" alt=""><img class="icon-light" src="icon/light theme/comment.png" alt=""></button><span id="viewerCommentsCount" data-post-id="" data-post-count="comments">0</span></div>
                    <div class="profile-post-viewer-metric"><button type="button" class="profile-post-viewer-action" data-post-id="" data-post-action="repost" aria-label="Репост"><img class="icon-dark" src="icon/dark theme/repost.png" alt=""><img class="icon-light" src="icon/light theme/repost.png" alt=""></button><span id="viewerRepostsCount" data-post-id="" data-post-count="reposts">0</span></div>
                    <div class="profile-post-viewer-metric"><button type="button" class="profile-post-viewer-action" data-post-id="" data-post-action="share" aria-label="Отправить в сообщения"><img class="icon-dark" src="icon/dark theme/share.png" alt=""><img class="icon-light" src="icon/light theme/share.png" alt=""></button><span id="viewerSharesCount" data-post-id="" data-post-count="shares">0</span></div>
                    <div class="profile-post-viewer-metric"><button type="button" class="profile-post-viewer-action" data-post-id="" data-post-action="save" aria-label="Избранное"><img class="icon-dark" src="icon/dark theme/favourites.png" alt=""><img class="icon-light" src="icon/light theme/favourites.png" alt=""></button><span id="viewerSavesCount" data-post-id="" data-post-count="saves">0</span></div>
                </div>
                <form method="post" class="profile-post-viewer-input-row" id="profilePostViewerCommentForm">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="post_id" value="">
                    <input type="hidden" name="parent_comment_id" value="">
                    <button type="button" class="profile-post-viewer-round-btn" id="profilePostViewerAttachmentButton" aria-label="Прикрепить фото"><img class="icon-dark" src="icon/dark theme/paper clip.png" alt=""><img class="icon-light" src="icon/light theme/paper clip.png" alt=""></button>
                    <input class="profile-post-viewer-file-input" id="profilePostViewerAttachmentInput" type="file" accept="image/gif,image/jpeg,image/png,image/webp" hidden>
                    <div class="profile-post-viewer-emoji-wrap">
                        <button type="button" class="profile-post-viewer-round-btn" id="profilePostViewerEmojiButton" aria-label="Выбрать эмодзи" aria-expanded="false" aria-controls="profilePostViewerEmojiPicker"><img class="icon-dark" src="icon/dark theme/add stickers.png" alt=""><img class="icon-light" src="icon/light theme/add stickers.png" alt=""></button>
                        <div class="profile-post-viewer-emoji-picker" id="profilePostViewerEmojiPicker" hidden>
                            <button type="button" data-emoji="😀">😀</button>
                            <button type="button" data-emoji="😂">😂</button>
                            <button type="button" data-emoji="😍">😍</button>
                            <button type="button" data-emoji="🥰">🥰</button>
                            <button type="button" data-emoji="😎">😎</button>
                            <button type="button" data-emoji="😢">😢</button>
                            <button type="button" data-emoji="😡">😡</button>
                            <button type="button" data-emoji="👍">👍</button>
                            <button type="button" data-emoji="🔥">🔥</button>
                            <button type="button" data-emoji="❤️">❤️</button>
                            <button type="button" data-emoji="✨">✨</button>
                            <button type="button" data-emoji="🎉">🎉</button>
                        </div>
                    </div>
                    <div class="profile-post-viewer-input-shell" id="profilePostViewerInputShell"><div class="profile-post-viewer-attachment-preview" id="profilePostViewerAttachmentPreview" hidden><img src="" alt="Предпросмотр вложения"><button type="button" id="profilePostViewerAttachmentRemove" aria-label="Удалить вложение">×</button></div><textarea class="profile-post-viewer-input" name="comment_text" rows="1" maxlength="1000" placeholder="Добавить комментарий" aria-label="Добавить комментарий"></textarea><button type="submit" class="profile-post-viewer-send-btn" aria-label="Отправить"><img src="icon/message.png" alt=""></button></div>
                </form>
            </aside>
        </div>
    </div>


    <div class="share-modal" id="share-post-modal">
        <div class="share-modal-overlay js-close-share-modal"></div>
        <div class="share-modal-dialog">
            <div class="share-modal-header">
                <h3>Отправить публикацию</h3>
                <button type="button" class="feed-action-btn feed-icon-btn js-close-share-modal" aria-label="Закрыть"><?php echo snapix_icon('x'); ?></button>
            </div>
            <div class="share-modal-body">
                <?php if ($shareRecipients): ?>
                    <?php foreach ($shareRecipients as $recipient): ?>
                        <div class="share-recipient-row">
                            <span class="share-recipient-user">
                                <span class="share-recipient-avatar"<?php if (!empty($recipient['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($recipient['avatar']); ?>');"<?php endif; ?>>
                                    <?php if (empty($recipient['avatar'])): ?><?php echo htmlspecialchars(mb_substr($recipient['login'], 0, 1)); ?><?php endif; ?>
                                </span>
                                <span>
                                    <?php echo htmlspecialchars($recipient['login']); ?>
                                    <?php if ((int) $recipient['is_mutual'] === 1): ?><small class="share-relation-note">взаимно</small><?php endif; ?>
                                </span>
                            </span>
                            <button type="button" class="share-send-btn js-share-send-btn" data-recipient-id="<?php echo (int) $recipient['id']; ?>">Отправить</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="comments-empty">Нет подходящих получателей. Подпишитесь на пользователей.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="js/post-sync.js"></script>
<script>
        window.snapixProfileComments = <?php echo json_encode($viewerCommentMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.snapixCurrentUser = <?php echo json_encode(['id' => (int) $user['id'], 'role' => (string) ($user['role'] ?? 'user')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        (() => {
            const bell = document.querySelector('[data-notification-toggle]');
            const popover = document.getElementById('notificationPopover');

            if (!bell || !popover) return;

            bell.addEventListener('click', (event) => {
                event.preventDefault();
                popover.classList.toggle('is-open');
            });

            document.addEventListener('click', (event) => {
                if (!popover.classList.contains('is-open')) return;
                if (popover.contains(event.target) || bell.contains(event.target)) return;
                popover.classList.remove('is-open');
            });
        })();

        (() => {
            function canEditComment(comment) {
                var currentUser = window.snapixCurrentUser || {};
                return Number(comment.user_id || 0) === Number(currentUser.id || 0);
            }

            function isOwnComment(comment) {
                var currentUser = window.snapixCurrentUser || {};
                return Number(comment.user_id || 0) === Number(currentUser.id || 0);
            }

            function canReportComment(comment) {
                return !isOwnComment(comment);
            }

            function canDeleteComment(comment) {
                var currentUser = window.snapixCurrentUser || {};
                var role = currentUser.role || '';
                var isPostOwner = Number(comment.post_owner_id || 0) === Number(currentUser.id || 0);
                return canEditComment(comment) || isPostOwner || role === 'admin' || role === 'moderator';
            }

            function showEmptyCommentsIfNeeded(commentsHost) {
                if (!commentsHost || commentsHost.querySelector('.profile-viewer-comment')) return;
                commentsHost.classList.remove('has-comments');
                commentsHost.innerHTML = '<p class="profile-post-viewer-empty">Комментариев нет</p>';
            }

            function removeCommentFromCache(postId, commentId) {
                var key = String(postId || '');
                var comments = (window.snapixProfileComments || {})[key] || [];
                var idsToRemove = [Number(commentId || 0)];
                var changed = true;
                while (changed) {
                    changed = false;
                    comments.forEach(function (comment) {
                        var currentId = Number(comment.comment_id || 0);
                        var parentId = Number(comment.parent_comment_id || 0);
                        if (idsToRemove.indexOf(parentId) !== -1 && idsToRemove.indexOf(currentId) === -1) {
                            idsToRemove.push(currentId);
                            changed = true;
                        }
                    });
                }
                window.snapixProfileComments[key] = comments.filter(function (comment) {
                    return idsToRemove.indexOf(Number(comment.comment_id || 0)) === -1;
                });
            }

            function updateCommentLikeCache(commentId, isLiked, likesCount) {
                Object.keys(window.snapixProfileComments || {}).forEach(function (postId) {
                    (window.snapixProfileComments[postId] || []).forEach(function (comment) {
                        if (Number(comment.comment_id || 0) === Number(commentId || 0)) {
                            comment.is_liked = !!isLiked;
                            comment.likes_count = Number(likesCount || 0);
                        }
                    });
                });
            }

            function updateCommentTextCache(commentId, commentText) {
                Object.keys(window.snapixProfileComments || {}).forEach(function (postId) {
                    (window.snapixProfileComments[postId] || []).forEach(function (comment) {
                        if (Number(comment.comment_id || 0) === Number(commentId || 0)) {
                            comment.comment_text = commentText;
                            comment.text = commentText;
                        }
                    });
                });
            }

            function buildAvatar(comment) {
                var avatar = document.createElement('a');
                avatar.className = 'profile-viewer-comment-avatar';
                avatar.href = comment.profile_url || 'profile.php';
                var avatarUrl = comment.avatar_url || '';
                if (avatarUrl) {
                    avatar.style.backgroundImage = "url('" + String(avatarUrl).replace(/'/g, "\\'") + "')";
                    avatar.textContent = '';
                } else {
                    avatar.textContent = (comment.login || '?').slice(0, 1).toUpperCase();
                }
                return avatar;
            }

            function buildViewerComment(comment, repliesByParent) {
                var item = document.createElement('div');
                item.className = 'profile-viewer-comment';
                item.dataset.commentId = String(comment.comment_id || '');
                item.dataset.postId = String(comment.post_id || '');
                item.dataset.parentCommentId = String(comment.parent_comment_id || '');

                item.appendChild(buildAvatar(comment));

                var body = document.createElement('div');
                body.className = 'profile-viewer-comment-body';

                var header = document.createElement('div');
                header.className = 'profile-viewer-comment-header profile-viewer-comment-top';
                var login = document.createElement('a');
                login.className = 'profile-viewer-comment-login';
                login.href = comment.profile_url || 'profile.php';
                login.textContent = comment.login || '';
                header.appendChild(login);
                body.appendChild(header);

                var commentText = typeof comment.comment_text !== 'undefined' ? comment.comment_text : (comment.text || '');
                if (commentText) {
                    var text = document.createElement('div');
                    text.className = 'profile-viewer-comment-text';
                    text.dataset.commentText = '1';
                    text.textContent = commentText;
                    body.appendChild(text);
                }

                if (comment.attachment_url) {
                    var attachment = document.createElement('div');
                    attachment.className = 'profile-viewer-comment-attachment';
                    if (comment.attachment_type) {
                        attachment.setAttribute('data-attachment-type', comment.attachment_type);
                    }
                    var image = document.createElement('img');
                    image.src = comment.attachment_url;
                    image.alt = '';
                    attachment.appendChild(image);
                    body.appendChild(attachment);
                }

                var meta = document.createElement('div');
                meta.className = 'profile-viewer-comment-footer profile-viewer-comment-meta';

                var date = document.createElement('span');
                date.className = 'profile-viewer-comment-date';
                date.textContent = comment.created_at || 'только что';
                meta.appendChild(date);

                var replyButton = document.createElement('button');
                replyButton.type = 'button';
                replyButton.className = 'profile-viewer-comment-reply';
                replyButton.dataset.replyLogin = comment.login || '';
                replyButton.textContent = 'Ответить';
                meta.appendChild(replyButton);

                var likeButton = document.createElement('button');
                likeButton.type = 'button';
                likeButton.className = 'profile-viewer-comment-like' + (comment.is_liked ? ' is-active' : '');
                likeButton.setAttribute('aria-label', 'Лайк комментария');
                likeButton.innerHTML = '<img src="icon/dark theme/like.png" alt=""><span data-comment-like-count></span>';
                var likeCount = likeButton.querySelector('[data-comment-like-count]');
                likeCount.textContent = String(Number(comment.likes_count || 0));
                meta.appendChild(likeButton);

                if (canDeleteComment(comment) || canEditComment(comment) || canReportComment(comment)) {
                    var menu = document.createElement('div');
                    menu.className = 'profile-viewer-comment-menu';

                    var toggle = document.createElement('button');
                    toggle.type = 'button';
                    toggle.className = 'profile-viewer-comment-menu-toggle';
                    toggle.setAttribute('aria-label', 'Действия с комментарием');
                    toggle.textContent = '⋯';
                    menu.appendChild(toggle);

                    var panel = document.createElement('div');
                    panel.className = 'profile-viewer-comment-menu-panel';

                    if (canEditComment(comment)) {
                        var editButton = document.createElement('button');
                        editButton.type = 'button';
                        editButton.className = 'profile-viewer-comment-edit';
                        editButton.textContent = 'Редактировать';
                        panel.appendChild(editButton);
                    }

                    if (canDeleteComment(comment)) {
                        var deleteButton = document.createElement('button');
                        deleteButton.type = 'button';
                        deleteButton.className = 'profile-viewer-comment-delete';
                        deleteButton.textContent = 'Удалить комментарий';
                        panel.appendChild(deleteButton);
                    }

                    if (canReportComment(comment)) {
                        var reportButton = document.createElement('button');
                        reportButton.type = 'button';
                        reportButton.className = 'profile-viewer-comment-report';
                        reportButton.dataset.reportLogin = comment.login || '';
                        reportButton.dataset.reportUserId = String(comment.user_id || '');
                        reportButton.textContent = 'Пожаловаться';
                        panel.appendChild(reportButton);
                    }

                    menu.appendChild(panel);
                    header.appendChild(menu);
                }

                body.appendChild(meta);

                item.appendChild(body);

                var replies = repliesByParent ? (repliesByParent[String(comment.comment_id || '')] || []) : [];
                if (replies.length) {
                    var repliesWrap = document.createElement('div');
                    repliesWrap.className = 'profile-viewer-comment-replies';
                    replies.forEach(function (reply) {
                        repliesWrap.appendChild(buildViewerComment(reply, repliesByParent));
                    });
                    item.appendChild(repliesWrap);
                }

                return item;
            }

            function splitCommentsByParent(comments) {
                var repliesByParent = {};
                var ids = {};
                comments.forEach(function (comment) {
                    ids[String(comment.comment_id || '')] = true;
                });
                var roots = [];
                comments.forEach(function (comment) {
                    var parentId = String(comment.parent_comment_id || '');
                    if (parentId && parentId !== '0' && ids[parentId]) {
                        repliesByParent[parentId] = repliesByParent[parentId] || [];
                        repliesByParent[parentId].push(comment);
                    } else {
                        roots.push(comment);
                    }
                });
                return {roots: roots, repliesByParent: repliesByParent};
            }

            window.snapixAppendViewerComment = function (comment) {
                var commentsHost = document.getElementById('profilePostViewerComments');
                if (!commentsHost || !comment) return;

                var empty = commentsHost.querySelector('.profile-post-viewer-empty');
                if (empty) empty.remove();
                commentsHost.classList.add('has-comments');
                var parentId = Number(comment.parent_comment_id || 0);
                if (parentId > 0) {
                    var parentNode = commentsHost.querySelector('.profile-viewer-comment[data-comment-id="' + String(parentId) + '"]');
                    if (parentNode) {
                        var repliesWrap = null;
                        Array.prototype.forEach.call(parentNode.children, function (child) {
                            if (child.classList && child.classList.contains('profile-viewer-comment-replies')) {
                                repliesWrap = child;
                            }
                        });
                        if (!repliesWrap) {
                            repliesWrap = document.createElement('div');
                            repliesWrap.className = 'profile-viewer-comment-replies';
                            parentNode.appendChild(repliesWrap);
                        }
                        repliesWrap.appendChild(buildViewerComment(comment));
                    } else {
                        commentsHost.appendChild(buildViewerComment(comment));
                    }
                } else {
                    commentsHost.appendChild(buildViewerComment(comment));
                }
                commentsHost.scrollTop = commentsHost.scrollHeight;
            };

            window.snapixRenderViewerComments = function (postId) {
                var commentsHost = document.getElementById('profilePostViewerComments');
                if (!commentsHost) return;

                commentsHost.classList.remove('has-comments');
                commentsHost.innerHTML = '';

                var comments = (window.snapixProfileComments || {})[String(postId)] || [];
                if (!comments.length) {
                    commentsHost.innerHTML = '<p class="profile-post-viewer-empty">Комментариев нет</p>';
                    return;
                }

                commentsHost.classList.add('has-comments');
                var grouped = splitCommentsByParent(comments);
                grouped.roots.forEach(function (comment) {
                    commentsHost.appendChild(buildViewerComment(comment, grouped.repliesByParent));
                });
            };


            document.addEventListener('click', function (event) {
                document.querySelectorAll('.profile-viewer-comment-menu.is-open').forEach(function (menu) {
                    if (!menu.contains(event.target)) {
                        menu.classList.remove('is-open');
                    }
                });

                var toggle = event.target.closest('.profile-viewer-comment-menu-toggle');
                if (toggle) {
                    event.preventDefault();
                    var menu = toggle.closest('.profile-viewer-comment-menu');
                    if (menu) menu.classList.toggle('is-open');
                    return;
                }

                var replyButton = event.target.closest('.profile-viewer-comment-reply');
                if (replyButton) {
                    event.preventDefault();
                    var replyCommentNode = replyButton.closest('.profile-viewer-comment');
                    var replyCommentId = replyCommentNode ? replyCommentNode.dataset.commentId : '';
                    var replyLogin = replyButton.dataset.replyLogin || '';
                    var form = document.getElementById('profilePostViewerCommentForm');
                    if (!form || !replyCommentId) return;
                    var parentInput = form.querySelector('input[name="parent_comment_id"]');
                    var textInput = form.querySelector('[name="comment_text"]');
                    if (parentInput) parentInput.value = replyCommentId;
                    if (textInput) {
                        var prefix = replyLogin ? '@' + replyLogin + ' ' : '';
                        if (prefix && textInput.value.indexOf(prefix) !== 0) {
                            var start = textInput.selectionStart || 0;
                            var end = textInput.selectionEnd || start;
                            var value = textInput.value;
                            textInput.value = value.slice(0, start) + prefix + value.slice(end);
                            var cursor = start + prefix.length;
                            textInput.setSelectionRange(cursor, cursor);
                        }
                        textInput.focus();
                    }
                    return;
                }

                var likeButton = event.target.closest('.profile-viewer-comment-like');
                if (likeButton) {
                    event.preventDefault();
                    var likeCommentNode = likeButton.closest('.profile-viewer-comment');
                    var likeCommentId = likeCommentNode ? likeCommentNode.dataset.commentId : '';
                    if (!likeCommentId) return;

                    var likeParams = new URLSearchParams();
                    likeParams.set('action', 'toggle_comment_like');
                    likeParams.set('comment_id', likeCommentId);

                    fetch(window.location.pathname || 'profile.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            'Accept': 'application/json'
                        },
                        body: likeParams.toString()
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (!data || !data.ok) return;
                            likeButton.classList.toggle('is-active', !!data.liked);
                            var countNode = likeButton.querySelector('[data-comment-like-count]');
                            if (countNode) countNode.textContent = String(Number(data.comment_likes_count || 0));
                            updateCommentLikeCache(likeCommentId, data.liked, data.comment_likes_count);
                        })
                        .catch(function () {});
                    return;
                }

                var reportButton = event.target.closest('.profile-viewer-comment-report');
                if (reportButton) {
                    event.preventDefault();
                    var reportCommentNode = reportButton.closest('.profile-viewer-comment');
                    var reportCommentId = reportCommentNode ? reportCommentNode.dataset.commentId : '';
                    var reportLogin = reportButton.dataset.reportLogin || '';
                    var reportUserId = reportButton.dataset.reportUserId || '';
                    var openMenu = reportButton.closest('.profile-viewer-comment-menu');
                    if (openMenu) openMenu.classList.remove('is-open');
                    if (reportCommentId && window.SnapixReportModal) {
                        window.SnapixReportModal.open({
                            login: reportLogin || 'user',
                            userId: reportUserId || 0,
                            onSubmit: function (reason, api) {
                                var params = new URLSearchParams();
                                params.set('action', 'report_comment');
                                params.set('comment_id', reportCommentId);
                                params.set('reason', reason);
                                fetch(window.location.pathname || 'profile.php', {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                        'Accept': 'application/json'
                                    },
                                    body: params.toString()
                                })
                                    .then(function (response) { return response.json(); })
                                    .then(function (data) {
                                        if (!data || !data.ok) return;
                                        api.showSuccess();
                                    })
                                    .catch(function () {});
                            }
                        });
                    }
                    return;
                }

                var editButton = event.target.closest('.profile-viewer-comment-edit');
                if (editButton) {
                    event.preventDefault();
                    var editCommentNode = editButton.closest('.profile-viewer-comment');
                    var editCommentId = editCommentNode ? editCommentNode.dataset.commentId : '';
                    var editBody = editCommentNode ? editCommentNode.querySelector('.profile-viewer-comment-body') : null;
                    var editTextNode = editBody ? editBody.querySelector('[data-comment-text]') : null;
                    var currentText = editTextNode ? editTextNode.textContent : '';
                    var nextText = window.prompt('Редактировать комментарий', currentText);
                    if (nextText === null) return;
                    nextText = nextText.trim();
                    if (!editCommentId || !nextText || nextText === currentText.trim()) return;

                    var editParams = new URLSearchParams();
                    editParams.set('action', 'edit_comment');
                    editParams.set('comment_id', editCommentId);
                    editParams.set('comment_text', nextText);

                    fetch(window.location.pathname || 'profile.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            'Accept': 'application/json'
                        },
                        body: editParams.toString()
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (!data || !data.ok) return;
                            var updatedText = data.comment_text || nextText;
                            if (!editTextNode) {
                                editTextNode = document.createElement('div');
                                editTextNode.className = 'profile-viewer-comment-text';
                                editTextNode.dataset.commentText = '1';
                                var body = editBody || (editCommentNode ? editCommentNode.querySelector('.profile-viewer-comment-body') : null);
                                var meta = body ? body.querySelector('.profile-viewer-comment-meta') : null;
                                if (body) body.insertBefore(editTextNode, meta || null);
                            }
                            if (editTextNode) editTextNode.textContent = updatedText;
                            updateCommentTextCache(editCommentId, updatedText);
                            var openMenu = editButton.closest('.profile-viewer-comment-menu');
                            if (openMenu) openMenu.classList.remove('is-open');
                        })
                        .catch(function () {});
                    return;
                }

                var deleteButton = event.target.closest('.profile-viewer-comment-delete');
                if (!deleteButton) return;

                event.preventDefault();
                var commentNode = deleteButton.closest('.profile-viewer-comment');
                var commentsHost = commentNode ? commentNode.closest('#profilePostViewerComments') : null;
                var commentId = commentNode ? commentNode.dataset.commentId : '';
                var postId = commentNode ? (commentNode.dataset.postId || document.getElementById('profilePostViewer')?.dataset.postId || '') : '';
                if (!commentId || !postId) return;

                var params = new URLSearchParams();
                params.set('action', 'delete_comment');
                params.set('comment_id', commentId);

                fetch(window.location.pathname || 'profile.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'Accept': 'application/json'
                    },
                    body: params.toString()
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data || !data.ok) return;
                        removeCommentFromCache(postId, commentId);
                        if (commentNode) commentNode.remove();
                        showEmptyCommentsIfNeeded(commentsHost);
                        if (window.snapixSyncPostState) {
                            window.snapixSyncPostState(postId, data);
                        }
                    })
                    .catch(function () {});
            });
        })();


        (() => {
            const viewer = document.getElementById('profilePostViewer');
            const mediaHost = document.getElementById('profilePostViewerMedia');
            const avatar = document.getElementById('profilePostViewerAvatar');
            const login = document.getElementById('profilePostViewerLogin');
            const avatarLink = document.getElementById('profilePostViewerAvatarLink');
            const loginLink = document.getElementById('profilePostViewerLoginLink');
            const follow = document.getElementById('profilePostViewerFollow');
            const likes = document.getElementById('viewerLikesCount');
            const comments = document.getElementById('viewerCommentsCount');
            const reposts = document.getElementById('viewerRepostsCount');
            const shares = document.getElementById('viewerSharesCount');
            const saves = document.getElementById('viewerSavesCount');
            const viewerComments = document.getElementById('profilePostViewerComments');
            const postViewerCaption = viewer ? viewer.querySelector('[data-post-viewer-caption]') : null;
            const postViewerHashtags = viewer ? viewer.querySelector('[data-post-viewer-hashtags]') : null;
            const commentForm = document.getElementById('profilePostViewerCommentForm');
            const postViewerMenuToggle = viewer ? viewer.querySelector('[data-post-viewer-menu-toggle]') : null;
            const postViewerMenu = viewer ? viewer.querySelector('[data-post-viewer-menu]') : null;
            const postViewerMenuOwn = viewer ? viewer.querySelector('.post-viewer-menu-own') : null;
            const postViewerMenuForeign = viewer ? viewer.querySelector('.post-viewer-menu-foreign') : null;
            const postViewerBlockLabel = viewer ? viewer.querySelector('[data-post-viewer-block-label]') : null;
            if (!viewer || !mediaHost) return;

            const closePostViewerMenu = () => {
                if (!postViewerMenu || !postViewerMenuToggle) return;
                postViewerMenu.hidden = true;
                postViewerMenuToggle.setAttribute('aria-expanded', 'false');
            };

            const togglePostViewerMenu = (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (!postViewerMenu || !postViewerMenuToggle) return;
                const shouldOpen = postViewerMenu.hidden;
                postViewerMenu.hidden = !shouldOpen;
                postViewerMenuToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            };

            const updatePostViewerMenu = (card, authorId) => {
                const currentUserId = Number(<?php echo (int) $user['id']; ?>);
                const isOwnPost = !!currentUserId && Number(authorId || 0) === currentUserId;
                if (postViewerMenuOwn) postViewerMenuOwn.classList.toggle('is-hidden', !isOwnPost);
                if (postViewerMenuForeign) postViewerMenuForeign.classList.toggle('is-hidden', isOwnPost);
                if (postViewerBlockLabel) {
                    const authorLogin = (card.dataset.postAuthorLogin || '').trim() || 'пользователя';
                    postViewerBlockLabel.textContent = `Добавить ${authorLogin} в чёрный список`;
                }
            };


            function escapeStatsHtml(value) {
                return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
            }

            function ensurePostViewerStatsModal() {
                let modal = document.querySelector('[data-post-viewer-stats-modal]');
                if (modal) return modal;
                modal = document.createElement('div');
                modal.className = 'clips-stats-modal';
                modal.setAttribute('data-post-viewer-stats-modal', '');
                modal.setAttribute('aria-hidden', 'true');
                modal.innerHTML = '<button type="button" class="clips-stats-modal-overlay" data-post-viewer-stats-close aria-label="Закрыть статистику"></button>' +
                    '<div class="clips-stats-modal-dialog" role="dialog" aria-modal="true" aria-label="Статистика публикации">' +
                        '<div class="clips-stats-modal-header"><button type="button" class="clips-stats-modal-close" data-post-viewer-stats-close aria-label="Закрыть">×</button><h3>Статистика публикации</h3><span class="clips-stats-header-spacer" aria-hidden="true"></span></div>' +
                        '<div class="clips-stats-modal-body" data-post-viewer-stats-content><p class="clips-stats-status">Загрузка...</p></div>' +
                    '</div>';
                document.body.appendChild(modal);
                modal.querySelectorAll('[data-post-viewer-stats-close]').forEach((button) => button.addEventListener('click', closePostViewerStatsModal));
                return modal;
            }

            function closePostViewerStatsModal() {
                const modal = document.querySelector('[data-post-viewer-stats-modal]');
                if (!modal) return;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            }

            function renderPostViewerStats(stats) {
                const daily = Array.isArray(stats.daily) ? stats.daily : [];
                const dailyRows = daily.length ? daily.map((row) => '<tr><td>' + escapeStatsHtml(row.date || '') + '</td><td>' + escapeStatsHtml(row.likes || 0) + '</td><td>' + escapeStatsHtml(row.comments || 0) + '</td><td>' + escapeStatsHtml(row.reposts || 0) + '</td><td>' + escapeStatsHtml(row.saves || 0) + '</td></tr>').join('') : '<tr><td colspan="5">Данных за период пока нет</td></tr>';
                const topPost = stats.top_post || {};
                const topPostMedia = topPost.media_url ? '<div class="clips-stats-top-thumb">' + (topPost.media_type === 'video' ? '<video src="' + escapeStatsHtml(topPost.media_url) + '" muted playsinline></video>' : '<img src="' + escapeStatsHtml(topPost.media_url) + '" alt="">') + '</div>' : '<div class="clips-stats-top-thumb is-empty">#</div>';
                const topPostMarkup = topPost.id ? '<div class="clips-stats-top-post">' + topPostMedia + '<div><strong>ID публикации: ' + escapeStatsHtml(topPost.id) + '</strong><span>Всего взаимодействий: ' + escapeStatsHtml(topPost.interactions_total || 0) + '</span></div></div>' : '<p class="clips-stats-status">Пока нет публикаций для сравнения.</p>';
                return '<p class="clips-stats-period">Период: всё время</p>' +
                    '<div class="clips-stats-cards">' +
                        '<section class="clips-stats-card"><span>Всего лайков за период</span><strong>' + escapeStatsHtml(stats.likes_total || 0) + '</strong></section>' +
                        '<section class="clips-stats-card"><span>Всего комментариев</span><strong>' + escapeStatsHtml(stats.comments_total || 0) + '</strong></section>' +
                        '<section class="clips-stats-card"><span>Всего репостов</span><strong>' + escapeStatsHtml(stats.reposts_total || 0) + '</strong></section>' +
                        '<section class="clips-stats-card"><span>Всего добавлений в избранное</span><strong>' + escapeStatsHtml(stats.saves_total || 0) + '</strong></section>' +
                    '</div>' +
                    '<section class="clips-stats-section"><h4>Динамика по дням</h4><div class="clips-stats-table-wrap"><table class="clips-stats-table"><thead><tr><th>Дата</th><th>Лайки</th><th>Комментарии</th><th>Репосты</th><th>Избранное</th></tr></thead><tbody>' + dailyRows + '</tbody></table></div></section>' +
                    '<section class="clips-stats-section"><h4>Пост с наибольшим количеством взаимодействий</h4>' + topPostMarkup + '</section>';
            }

            function openPostViewerStatsModal(postId) {
                if (typeof window.openStatsModal === 'function') {
                    window.openStatsModal(postId);
                    return;
                }
                const modal = ensurePostViewerStatsModal();
                const content = modal.querySelector('[data-post-viewer-stats-content]');
                if (content) content.innerHTML = '<p class="clips-stats-status">Загрузка...</p>';
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                const formData = new FormData();
                formData.set('action', 'get_clip_post_stats');
                formData.set('post_id', postId);
                fetch('clips.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: formData })
                    .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                    .then((result) => {
                        if (!result.ok || !result.data || !result.data.ok) throw new Error((result.data && result.data.error) || 'Не удалось загрузить статистику.');
                        if (content) content.innerHTML = renderPostViewerStats(result.data.stats || {});
                    })
                    .catch((error) => {
                        if (content) content.innerHTML = '<p class="clips-stats-status is-error">' + escapeStatsHtml(error.message || 'Не удалось загрузить статистику.') + '</p>';
                    });
            }

            function removePostCardsFromPage(postId) {
                let removed = false;
                document.querySelectorAll('.feed-card[data-post-id="' + postId + '"], .post-card[data-post-id="' + postId + '"]').forEach((card) => {
                    card.remove();
                    removed = true;
                });
                if (!removed) window.location.reload();
            }

            function removePostCardsByAuthor(authorId) {
                let removed = false;
                document.querySelectorAll('.feed-card[data-post-author-id="' + authorId + '"], .post-card[data-post-author-id="' + authorId + '"]').forEach((card) => {
                    card.remove();
                    removed = true;
                });
                if (!removed) window.location.reload();
            }

            function sendPostViewerAction(action, extra = {}) {
                const formData = new FormData();
                formData.set('action', action);
                formData.set('post_id', viewer.dataset.postId || '');
                formData.set('owner_id', viewer.dataset.postAuthorId || '');
                Object.keys(extra || {}).forEach((key) => formData.set(key, extra[key]));
                return fetch(window.location.href, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: formData })
                    .then((response) => response.json().catch(() => ({})).then((data) => {
                        if (!response.ok || (data && data.ok === false)) throw new Error((data && data.error) || 'Не удалось выполнить действие.');
                        return data;
                    }));
            }

            function deleteCurrentViewerPost(postId) {
                if (!confirm('Удалить публикацию?')) return;
                sendPostViewerAction('delete_post')
                    .then(() => {
                        closeViewer();
                        removePostCardsFromPage(postId);
                    })
                    .catch((error) => alert(error.message || 'Не удалось удалить публикацию.'));
            }

            function hideCurrentViewerPost(postId) {
                sendPostViewerAction('hide_post')
                    .then(() => {
                        closePostViewerMenu();
                        closeViewer();
                        removePostCardsFromPage(postId);
                    })
                    .catch((error) => alert(error.message || 'Не удалось скрыть публикацию.'));
            }

            function blockCurrentViewerAuthor(authorId) {
                sendPostViewerAction('block_user', { target_user_id: authorId })
                    .then(() => {
                        closePostViewerMenu();
                        closeViewer();
                        removePostCardsByAuthor(authorId);
                    })
                    .catch((error) => alert(error.message || 'Не удалось добавить пользователя в чёрный список.'));
            }

            function reportCurrentViewerPost(postId, authorId, authorLogin) {
                if (!window.SnapixReportModal) return;
                window.SnapixReportModal.open({
                    login: authorLogin || 'user',
                    userId: authorId || 0,
                    postId: postId,
                    onSubmit: (reason, api) => {
                        sendPostViewerAction('report_post', { report_reason: reason }).then((data) => {
                            if (!data || data.ok === false) return;
                            if (api && typeof api.showSuccess === 'function') api.showSuccess();
                        });
                    }
                });
            }

            const closeViewer = () => {
                closePostViewerMenu();
                viewer.classList.remove('is-open');
                document.body.classList.remove('is-modal-open');
                document.body.style.overflow = '';
                mediaHost.innerHTML = '';
            };

            window.snapixOpenPostViewer = (card) => card?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

            function renderPostViewerHashtags(container, hashtags) {
                if (!container) return;
                container.innerHTML = '';
                const tags = (hashtags || '').trim().split(/\s+/).filter(Boolean);
                container.hidden = tags.length === 0;
                tags.forEach((tag) => {
                    const link = document.createElement('a');
                    link.href = 'search.php?q=' + encodeURIComponent(tag);
                    link.textContent = tag;
                    container.appendChild(link);
                });
            }

            document.querySelectorAll('.profile-media-grid .post-card').forEach((card) => {
                card.addEventListener('click', (event) => {
                    if (event.target.closest('button, a, form, .profile-hover-action-item, .post-menu-wrap')) return;
                    closePostViewerMenu();
                    const mediaUrl = card.dataset.postMediaUrl || '';
                    const mediaType = card.dataset.postMediaType || 'image';
                    mediaHost.innerHTML = '';
                    if (mediaType === 'video') {
                        const video = document.createElement('video');
                        video.src = mediaUrl;
                        video.controls = true;
                        video.playsInline = true;
                        mediaHost.appendChild(video);
                    } else {
                        const img = document.createElement('img');
                        img.src = mediaUrl;
                        img.alt = 'Публикация';
                        mediaHost.appendChild(img);
                    }
                    login.textContent = card.dataset.postAuthorLogin || '';
                    const avatarUrl = card.dataset.postAuthorAvatar || '';
                    avatar.style.backgroundImage = avatarUrl ? `url('${avatarUrl}')` : '';
                    avatar.textContent = avatarUrl ? '' : (login.textContent || '?').slice(0, 1).toUpperCase();
                    const authorId = Number(card.dataset.postAuthorId || 0);
                    const authorUrl = authorId === <?php echo (int) $user['id']; ?> ? 'profile.php' : 'user.php?id=' + encodeURIComponent(String(authorId));
                    if (avatarLink) avatarLink.href = authorUrl;
                    if (loginLink) loginLink.href = authorUrl;
                    const caption = card.dataset.postCaption || '';
                    if (postViewerCaption) {
                        postViewerCaption.textContent = caption;
                        postViewerCaption.hidden = caption.trim() === '';
                    }
                    renderPostViewerHashtags(postViewerHashtags, card.dataset.postHashtags || '');
                    updatePostViewerMenu(card, authorId);
                    if (follow) {
                        follow.hidden = !authorId || authorId === <?php echo (int) $user['id']; ?> || card.dataset.postIsFollowingAuthor === '1';
                        follow.disabled = false;
                        follow.classList.remove('is-following');
                        follow.dataset.authorId = String(authorId || '');
                    }
                    const postId = card.dataset.postId || '';
                    if (window.snapixRenderViewerComments) window.snapixRenderViewerComments(postId);
                    viewer.dataset.postId = postId;
                    viewer.dataset.postAuthorId = card.dataset.postAuthorId || '';
                    viewer.querySelectorAll('[data-post-action], [data-post-count], .profile-post-viewer-metrics').forEach((node) => {
                        node.setAttribute('data-post-id', postId);
                    });
                    if (commentForm) {
                        const postIdInput = commentForm.querySelector('input[name="post_id"]');
                        const parentCommentInput = commentForm.querySelector('input[name="parent_comment_id"]');
                        const commentInput = commentForm.querySelector('[name="comment_text"]');
                        if (postIdInput) postIdInput.value = postId;
                        if (parentCommentInput) parentCommentInput.value = '';
                        if (commentInput) commentInput.value = '';
                        if (window.snapixClearViewerAttachment) window.snapixClearViewerAttachment();
                    }
                    likes.textContent = card.dataset.postLikesCount || '0';
                    comments.textContent = card.dataset.postCommentsCount || '0';
                    reposts.textContent = card.dataset.postRepostsCount || '0';
                    shares.textContent = card.dataset.postSharesCount || '0';
                    saves.textContent = card.dataset.postSavesCount || '0';
                    viewer.querySelector('[data-post-action="like"]')?.classList.toggle('is-active', card.dataset.postLiked === '1');
                    viewer.querySelector('[data-post-action="save"]')?.classList.toggle('is-saved', card.dataset.postSaved === '1');
                    viewer.querySelector('[data-post-action="repost"]')?.classList.toggle('is-reposted', card.dataset.postReposted === '1');
                    viewer.classList.add('is-open');
                    document.body.classList.add('is-modal-open');
                    document.body.style.overflow = 'hidden';
                });
            });

            const linkedPostId = new URLSearchParams(window.location.search).get('open_post') || new URLSearchParams(window.location.search).get('comments_post');
            if (linkedPostId) {
                const safeLinkedPostId = String(linkedPostId).replace(/[^0-9]/g, '');
                const linkedCard = safeLinkedPostId ? document.querySelector('.profile-media-grid .post-card[data-post-id="' + safeLinkedPostId + '"]') : null;
                if (linkedCard) linkedCard.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
            }

            if (postViewerMenuToggle) {
                postViewerMenuToggle.addEventListener('click', togglePostViewerMenu);
            }
            viewer.querySelectorAll('[data-post-viewer-close]').forEach((node) => node.addEventListener('click', closeViewer));
            if (postViewerMenu) {
                postViewerMenu.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-post-viewer-menu-action]');
                    if (!button) return;
                    event.preventDefault();
                    event.stopPropagation();
                    const action = button.getAttribute('data-post-viewer-menu-action');
                    const postId = viewer.dataset.postId || '';
                    const authorId = viewer.dataset.postAuthorId || '';
                    const currentUserId = Number((window.snapixCurrentUser || {}).id || 0);
                    const isOwnPost = !!currentUserId && Number(authorId || 0) === currentUserId;
                    if (!postId) return;
                    if (['edit_post', 'open_stats', 'delete_post'].indexOf(action) !== -1) {
                        if (!isOwnPost) return;
                        closePostViewerMenu();
                        if (action === 'edit_post') {
                            window.location.href = 'edit-post.php?id=' + encodeURIComponent(postId);
                            return;
                        }
                        if (action === 'open_stats') {
                            openPostViewerStatsModal(postId);
                            return;
                        }
                        if (action === 'delete_post') {
                            deleteCurrentViewerPost(postId);
                        }
                        return;
                    }
                    if (['hide_post', 'block_user', 'report_post'].indexOf(action) !== -1) {
                        if (isOwnPost) return;
                        if (action === 'hide_post') {
                            hideCurrentViewerPost(postId);
                            return;
                        }
                        if (action === 'block_user') {
                            blockCurrentViewerAuthor(authorId);
                            return;
                        }
                        if (action === 'report_post') {
                            closePostViewerMenu();
                            reportCurrentViewerPost(postId, authorId, login ? login.textContent : '');
                        }
                    }
                });
            }
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && viewer.classList.contains('is-open')) closeViewer();
            });
            document.addEventListener('click', (event) => {
                if (viewer.classList.contains('is-open') && !event.target.closest('#profilePostViewer [data-post-viewer-menu-wrap]')) {
                    closePostViewerMenu();
                }
            });
            if (follow) {
                follow.addEventListener('click', () => {
                    const authorId = follow.dataset.authorId || viewer.dataset.postAuthorId || '';
                    if (!authorId || follow.disabled) return;
                    follow.disabled = true;
                    follow.classList.remove('is-following');
                    void follow.offsetWidth;
                    follow.classList.add('is-following');
                    fetch(window.location.href, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            'Accept': 'application/json'
                        },
                        body: new URLSearchParams({ action: 'modal_follow_author', author_id: authorId }).toString()
                    }).then((response) => response.json()).then((data) => {
                        if (!data || !data.ok) {
                            follow.disabled = false;
                            follow.classList.remove('is-following');
                            return;
                        }
                        document.querySelectorAll('[data-post-author-id="' + authorId + '"]').forEach((node) => {
                            if (node.dataset) {
                                node.dataset.postViewerFollowStatus = data.status || 'accepted';
                                node.dataset.postIsFollowingAuthor = '1';
                            }
                        });
                        window.setTimeout(() => {
                            follow.hidden = true;
                            follow.disabled = false;
                            follow.classList.remove('is-following');
                        }, 900);
                    }).catch(() => {
                        follow.disabled = false;
                        follow.classList.remove('is-following');
                    });
                });
            }
        })();

        (() => {
            const emojiButton = document.getElementById('profilePostViewerEmojiButton');
            const emojiPicker = document.getElementById('profilePostViewerEmojiPicker');
            const commentForm = document.getElementById('profilePostViewerCommentForm');
            const commentInput = commentForm ? commentForm.querySelector('[name="comment_text"]') : null;
            if (!emojiButton || !emojiPicker || !commentInput) return;

            const closeEmojiPicker = () => {
                emojiPicker.hidden = true;
                emojiButton.setAttribute('aria-expanded', 'false');
            };

            const openEmojiPicker = () => {
                emojiPicker.hidden = false;
                emojiButton.setAttribute('aria-expanded', 'true');
            };

            emojiButton.addEventListener('click', (event) => {
                event.stopPropagation();
                if (emojiPicker.hidden) {
                    openEmojiPicker();
                } else {
                    closeEmojiPicker();
                }
            });

            emojiPicker.addEventListener('click', (event) => {
                const emoji = event.target.closest('[data-emoji]')?.getAttribute('data-emoji');
                if (!emoji) return;

                const start = commentInput.selectionStart ?? commentInput.value.length;
                const end = commentInput.selectionEnd ?? commentInput.value.length;
                commentInput.value = commentInput.value.slice(0, start) + emoji + commentInput.value.slice(end);
                const nextCursorPosition = start + emoji.length;
                commentInput.focus();
                commentInput.setSelectionRange(nextCursorPosition, nextCursorPosition);
                closeEmojiPicker();
            });

            document.addEventListener('click', (event) => {
                if (emojiPicker.hidden || emojiPicker.contains(event.target) || emojiButton.contains(event.target)) return;
                closeEmojiPicker();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeEmojiPicker();
            });
        })();

        (() => {
            const attachmentButton = document.getElementById('profilePostViewerAttachmentButton');
            const attachmentInput = document.getElementById('profilePostViewerAttachmentInput');
            const preview = document.getElementById('profilePostViewerAttachmentPreview');
            const previewImage = preview ? preview.querySelector('img') : null;
            const removeButton = document.getElementById('profilePostViewerAttachmentRemove');
            const inputShell = document.getElementById('profilePostViewerInputShell');
            const allowedTypes = ['image/gif', 'image/jpeg', 'image/png', 'image/webp'];
            const allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'webp'];
            let previewUrl = '';
            let selectedAttachmentFile = null;
            let selectedAttachmentType = '';

            if (!attachmentButton || !attachmentInput || !preview || !previewImage || !removeButton || !inputShell) return;

            const clearAttachment = () => {
                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                    previewUrl = '';
                }
                selectedAttachmentFile = null;
                selectedAttachmentType = '';
                attachmentInput.value = '';
                previewImage.removeAttribute('src');
                preview.hidden = true;
                inputShell.classList.remove('has-attachment');
            };

            window.snapixClearViewerAttachment = clearAttachment;
            window.snapixGetViewerAttachment = () => {
                if (!selectedAttachmentFile) return null;
                return {
                    file: selectedAttachmentFile,
                    attachment_type: selectedAttachmentType
                };
            };

            attachmentButton.addEventListener('click', () => {
                attachmentInput.click();
            });

            attachmentInput.addEventListener('change', () => {
                const file = attachmentInput.files && attachmentInput.files[0] ? attachmentInput.files[0] : null;
                if (!file) {
                    clearAttachment();
                    return;
                }

                const extension = (file.name.split('.').pop() || '').toLowerCase();
                if ((file.type && allowedTypes.indexOf(file.type) === -1) || allowedExtensions.indexOf(extension) === -1) {
                    clearAttachment();
                    return;
                }

                selectedAttachmentFile = file;
                selectedAttachmentType = extension === 'gif' ? 'gif' : 'image';
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                previewUrl = URL.createObjectURL(file);
                previewImage.src = previewUrl;
                preview.hidden = false;
                inputShell.classList.add('has-attachment');
            });

            removeButton.addEventListener('click', clearAttachment);
        })();

        document.querySelectorAll('.js-open-comments-modal').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const card = button.closest('[data-post-id]');
                if (card) {
                    card.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
                    const input = document.querySelector('#profilePostViewerCommentForm [name="comment_text"]');
                    if (input) input.focus();
                }
            });
        });

        document.querySelectorAll('.js-close-comments-modal').forEach((button) => {
            button.addEventListener('click', () => {
                const modalId = button.getAttribute('data-modal');
                const modal = modalId ? document.getElementById(modalId) : null;
                if (modal) {
                    modal.classList.remove('is-open');
                }
            });
        });

        (() => {
            const tabButtons = document.querySelectorAll('[data-profile-tab-button]');
            const tabPanels = document.querySelectorAll('[data-profile-tab-panel]');
            if (!tabButtons.length || !tabPanels.length) {
                return;
            }
            tabButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const tab = button.getAttribute('data-profile-tab-button');
                    tabButtons.forEach((item) => item.classList.toggle('is-active', item === button));
                    tabPanels.forEach((panel) => {
                        panel.hidden = panel.getAttribute('data-profile-tab-panel') !== tab;
                    });
                });
            });
        })();

        document.querySelectorAll('.post-menu-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const menuId = button.getAttribute('data-post-menu');
                const menu = menuId ? document.getElementById(menuId) : null;
                if (menu) {
                    menu.classList.toggle('is-open');
                }
            });
        });

        (function () {
    var postActionEndpoints = {
        like: 'toggle_like',
        save: 'toggle_save',
        repost: 'add_repost'
    };
    var countKeys = {
        likes: 'likes_count',
        comments: 'comments_count',
        reposts: 'reposts_count',
        shares: 'shares_count',
        saves: 'saves_count'
    };

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(String(value));
        }
        return String(value).replace(/"/g, '\\"');
    }

    function getFormAction(form) {
        var actionInput = form ? form.querySelector('input[name="action"]') : null;
        var action = actionInput ? actionInput.value : '';
        if (action === 'toggle_like') return 'like';
        if (action === 'toggle_save') return 'save';
        if (action === 'add_repost') return 'repost';
        return '';
    }

    function getFormPostId(form) {
        var postIdInput = form ? form.querySelector('input[name="post_id"]') : null;
        return postIdInput ? postIdInput.value : '';
    }

    function inferPostId(node) {
        var direct = node ? node.getAttribute('data-post-id') : '';
        if (direct) return direct;
        var owner = node ? node.closest('[data-post-id]') : null;
        if (owner && owner.getAttribute('data-post-id')) return owner.getAttribute('data-post-id');
        var form = node ? node.closest('form') : null;
        if (form) return getFormPostId(form);
        var modalId = node ? node.getAttribute('data-modal') : '';
        var match = modalId ? modalId.match(/(\d+)$/) : null;
        return match ? match[1] : '';
    }

    function setCount(node, value) {
        if (node && typeof value !== 'undefined') {
            node.textContent = String(Number(value || 0));
        }
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

    function updateActionButton(button, data) {
        var action = button ? button.getAttribute('data-post-action') : '';
        if (!action) return;

        if (action === 'like' && typeof data.liked !== 'undefined') {
            button.classList.toggle('is-active', !!data.liked);
            animateActiveIcon(button, !!data.liked);
        }
        if (action === 'save' && typeof data.saved !== 'undefined') {
            button.classList.toggle('is-saved', !!data.saved);
            animateActiveIcon(button, !!data.saved);
        }
        if (action === 'repost' && typeof data.reposted !== 'undefined') {
            button.classList.toggle('is-reposted', !!data.reposted);
            animateActiveIcon(button, !!data.reposted);
        }
    }

    function syncPostState(postId, data) {
        if (!postId || !data) return;
        var escapedPostId = cssEscape(postId);

        document.querySelectorAll('[data-post-id="' + escapedPostId + '"][data-post-count]').forEach(function (node) {
            var key = countKeys[node.getAttribute('data-post-count')];
            if (key) setCount(node, data[key]);
        });

        document.querySelectorAll('[data-post-id="' + escapedPostId + '"][data-post-action]').forEach(function (button) {
            updateActionButton(button, data);
        });

        document.querySelectorAll('[data-post-id="' + escapedPostId + '"]').forEach(function (node) {
            if (typeof data.likes_count !== 'undefined') node.dataset.postLikesCount = String(data.likes_count);
            if (typeof data.comments_count !== 'undefined') node.dataset.postCommentsCount = String(data.comments_count);
            if (typeof data.reposts_count !== 'undefined') node.dataset.postRepostsCount = String(data.reposts_count);
            if (typeof data.shares_count !== 'undefined') node.dataset.postSharesCount = String(data.shares_count);
            if (typeof data.saves_count !== 'undefined') node.dataset.postSavesCount = String(data.saves_count);
            if (typeof data.liked !== 'undefined') node.dataset.postLiked = data.liked ? '1' : '0';
            if (typeof data.saved !== 'undefined') node.dataset.postSaved = data.saved ? '1' : '0';
            if (typeof data.reposted !== 'undefined') node.dataset.postReposted = data.reposted ? '1' : '0';
        });
    }

    function sendPostAction(postId, endpointAction, extraData) {
        var params = new URLSearchParams(extraData || {});
        params.set('action', endpointAction);
        params.set('post_id', String(postId));

        return fetch(window.location.pathname || 'profile.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json'
            },
            body: params.toString()
        }).then(function (response) {
            return response.json();
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

    function initPostDataAttributes() {
        document.querySelectorAll('form.inline-action-form').forEach(function (form) {
            var action = getFormAction(form);
            var postId = getFormPostId(form);
            var button = form.querySelector('.feed-action-btn');
            var countNode = form.closest('.feed-action-item, .profile-hover-action-item') ? form.closest('.feed-action-item, .profile-hover-action-item').querySelector('.feed-action-count') : null;
            if (button && action && postId) {
                button.setAttribute('data-post-id', postId);
                button.setAttribute('data-post-action', action);
            }
            if (countNode && action && postId) {
                countNode.setAttribute('data-post-id', postId);
                countNode.setAttribute('data-post-count', action === 'like' ? 'likes' : (action === 'save' ? 'saves' : 'reposts'));
            }
        });

        document.querySelectorAll('.js-open-comments-modal').forEach(function (button) {
            var postId = inferPostId(button);
            var countNode = button.closest('.feed-action-item') ? button.closest('.feed-action-item').querySelector('.feed-action-count') : null;
            if (postId) {
                button.setAttribute('data-post-id', postId);
                button.setAttribute('data-post-action', 'comment');
                if (countNode) {
                    countNode.setAttribute('data-post-id', postId);
                    countNode.setAttribute('data-post-count', 'comments');
                }
            }
        });

        document.querySelectorAll('.js-open-share-modal').forEach(function (button) {
            var postId = inferPostId(button);
            var countNode = button.closest('.feed-action-item') ? button.closest('.feed-action-item').querySelector('.feed-action-count') : null;
            if (postId) {
                button.setAttribute('data-post-id', postId);
                button.setAttribute('data-post-action', 'share');
                if (countNode) {
                    countNode.setAttribute('data-post-id', postId);
                    countNode.setAttribute('data-post-count', 'shares');
                }
            }
        });
    }

    initPostDataAttributes();

    document.querySelectorAll('form.inline-action-form').forEach(function (form) {
        var action = getFormAction(form);
        if (!action) return;

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            sendPostActionForm(form)
                .then(function (data) {
                    if (!data || !data.ok) return;
                    syncPostState(getFormPostId(form), data);
                })
                .catch(function () {});
        });
    });

    document.querySelectorAll('[data-post-action="like"], [data-post-action="save"], [data-post-action="repost"], [data-post-action="comment"], [data-post-action="share"]').forEach(function (button) {
        if (button.closest('form.inline-action-form') || button.classList.contains('js-open-comments-modal') || button.classList.contains('js-open-share-modal')) {
            return;
        }

        button.addEventListener('click', function () {
            var action = button.getAttribute('data-post-action');
            var postId = button.getAttribute('data-post-id') || inferPostId(button);
            if (!postId) return;

            if (action === 'comment') {
                var input = document.querySelector('#profilePostViewerCommentForm [name="comment_text"]');
                if (input) input.focus();
                return;
            }

            if (action === 'share') {
                window.snapixOpenShareModal(postId);
                return;
            }

            var endpointAction = postActionEndpoints[action];
            if (!endpointAction) return;

            sendPostAction(postId, endpointAction)
                .then(function (data) {
                    if (!data || !data.ok) return;
                    syncPostState(postId, data);
                })
                .catch(function () {});
        });
    });

    document.querySelectorAll('form.comments-modal-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            sendPostActionForm(form)
                .then(function (data) {
                    if (!data || !data.ok) return;

                    var textarea = form.querySelector('textarea[name="comment_text"]');
                    var modal = form.closest('.comments-modal');
                    var body = modal ? modal.querySelector('.comments-modal-body') : null;
                    var postId = getFormPostId(form);

                    if (body && data.comment) {
                        var empty = body.querySelector('.comments-empty');
                        if (empty) empty.remove();

                        var item = document.createElement('div');
                        item.className = 'comment-item';

                        var author = document.createElement('a');
                        author.className = 'comment-author';
                        author.href = data.comment.profile_url || 'profile.php';

                        var strong = document.createElement('strong');
                        strong.textContent = data.comment.login || '';
                        author.appendChild(strong);

                        var text = document.createElement('p');
                        text.textContent = data.comment.text || '';

                        item.appendChild(author);
                        item.appendChild(text);
                        body.insertBefore(item, body.firstChild);
                    }

                    if (textarea) textarea.value = '';
                    syncPostState(postId, data);
                })
                .catch(function () {});
        });
    });

    var viewerCommentForm = document.getElementById('profilePostViewerCommentForm');
    function appendViewerComment(comment) {
        if (window.snapixAppendViewerComment) {
            window.snapixAppendViewerComment(comment);
        }
    }

    function showViewerModerationMessage(message, isError) {
        if (!viewerCommentForm || !message) return;
        var messageNode = viewerCommentForm.querySelector('.profile-post-viewer-moderation-message');
        if (!messageNode) {
            messageNode = document.createElement('p');
            messageNode.className = 'profile-post-viewer-moderation-message';
            viewerCommentForm.insertBefore(messageNode, viewerCommentForm.firstChild);
        }
        messageNode.textContent = message;
        messageNode.classList.toggle('is-error', !!isError);
        window.setTimeout(function () {
            if (messageNode && messageNode.parentNode) {
                messageNode.remove();
            }
        }, 4000);
    }

    if (viewerCommentForm) {
        var viewerCommentInput = viewerCommentForm.querySelector('[name="comment_text"]');

        if (viewerCommentInput) {
            viewerCommentInput.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' || event.shiftKey) return;
                event.preventDefault();
                if (viewerCommentForm.requestSubmit) {
                    viewerCommentForm.requestSubmit();
                } else {
                    viewerCommentForm.dispatchEvent(new Event('submit', {cancelable: true}));
                }
            });
        }

        viewerCommentForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var input = viewerCommentForm.querySelector('[name="comment_text"]');
            var postIdInput = viewerCommentForm.querySelector('input[name="post_id"]');
            var parentCommentInput = viewerCommentForm.querySelector('input[name="parent_comment_id"]');
            var postId = postIdInput ? postIdInput.value : '';
            var parentCommentId = parentCommentInput ? parentCommentInput.value : '';
            var text = input ? input.value.trim() : '';
            var pendingAttachment = window.snapixGetViewerAttachment ? window.snapixGetViewerAttachment() : null;
            if (!postId || (!text && !pendingAttachment)) return;

            var formData = new FormData();
            formData.set('action', 'add_comment');
            formData.set('post_id', postId);
            formData.set('comment_text', text);
            if (parentCommentId) {
                formData.set('parent_comment_id', parentCommentId);
            }
            if (pendingAttachment && pendingAttachment.file) {
                formData.set('attachment', pendingAttachment.file, pendingAttachment.file.name);
            }

            fetch(window.location.pathname || 'profile.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.ok) return;
                    if (data.moderation_status && data.moderation_status !== 'published') {
                        showViewerModerationMessage(data.moderation_message || 'Комментарий отправлен на модерацию', data.moderation_status === 'rejected');
                    }
                    if (data.comment) {
                        window.snapixProfileComments = window.snapixProfileComments || {};
                        window.snapixProfileComments[String(postId)] = window.snapixProfileComments[String(postId)] || [];
                        window.snapixProfileComments[String(postId)].push(data.comment);
                    }
                    if (input) input.value = '';
                    if (parentCommentInput) parentCommentInput.value = '';
                    if (window.snapixClearViewerAttachment) window.snapixClearViewerAttachment();
                    if (data.comment) {
                        appendViewerComment(data.comment);
                    }
                    syncPostState(postId, data);
                })
                .catch(function () {});
        });
    }

    window.snapixSyncPostState = syncPostState;
    window.snapixSetPostCount = setCount;
})();

document.addEventListener('click', (event) => {
            document.querySelectorAll('.post-menu').forEach((menu) => {
                const wrap = menu.closest('.post-menu-wrap');
                if (wrap && !wrap.contains(event.target)) {
                    menu.classList.remove('is-open');
                }
            });
        });

        (() => {
            const modal = document.getElementById('share-post-modal');
            if (!modal) return;
            let activePostId = 0;

            window.snapixOpenShareModal = (postId) => {
                activePostId = Number(postId || 0);
                if (activePostId) {
                    modal.classList.add('is-open');
                }
            };

            document.querySelectorAll('.js-open-share-modal').forEach((button) => {
                button.addEventListener('click', () => {
                    window.snapixOpenShareModal(button.getAttribute('data-post-id'));
                });
            });

            modal.querySelectorAll('.js-close-share-modal').forEach((button) => {
                button.addEventListener('click', () => {
                    modal.classList.remove('is-open');
                });
            });

            modal.querySelectorAll('.js-share-send-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    if (!activePostId) return;

                    const params = new URLSearchParams();
                    params.set('post_id', String(activePostId));
                    params.set('receiver_id', String(button.getAttribute('data-recipient-id')));

                    fetch('share-post.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: params.toString()
                    })
                        .then((response) => response.json())
                        .then((data) => {
                            if (!data.ok) return;
                            if (window.snapixSyncPostState) {
                                window.snapixSyncPostState(String(activePostId), data);
                            }
                            button.textContent = 'Отправлено';
                            button.disabled = true;
                            setTimeout(() => {
                                button.textContent = 'Отправить';
                                button.disabled = false;
                                modal.classList.remove('is-open');
                            }, 700);
                        })
                        .catch(() => {});
                });
            });
        })();
    </script>
</body>
</html>
