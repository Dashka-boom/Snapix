<?php
session_start();
require './config/config.php';
require './includes/icons.php';
require './includes/post-actions.php';
require './includes/notifications.php';
require_once './includes/side-menu.php';

function buildProfileUrl(int $profileUserId, ?int $currentUserId): string
{
    if ($currentUserId !== null && $profileUserId === $currentUserId) {
        return 'profile.php';
    }

    return 'user.php?id=' . $profileUserId;
}

function ensureCommentAttachmentStorage(PDO $pdo): void
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
        if (!in_array(($exception->errorInfo[1] ?? null), [1005, 1060, 1215, 1826], true)) {
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

ensureCommentAttachmentStorage($pdo);

$user = null;
$shareRecipients = [];

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT id, login, avatar, role FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
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
    }
}

$reportReasonsStmt = $pdo->query('SELECT id, label FROM moderation_reasons ORDER BY id ASC');
$reportReasons = $reportReasonsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $action = $_POST['action'] ?? '';
    $commentsPostId = 0;
    $isAjaxPostAction = snapix_is_ajax_request() && in_array($action, ['toggle_like', 'toggle_save', 'add_comment', 'add_repost', 'delete_comment', 'report_comment', 'toggle_comment_like', 'get_post_counts', 'modal_follow_author'], true);
    $ajaxExtra = [];

    if ($action === 'modal_follow_author') {
        $targetUserId = (int) ($_POST['author_id'] ?? 0);

        if (!$user || $targetUserId <= 0 || $targetUserId === (int) $user['id']) {
            snapix_send_post_action_error('invalid_target_user', 400);
        }

        $targetStmt = $pdo->prepare('SELECT id, is_private FROM users WHERE id = :id LIMIT 1');
        $targetStmt->execute(['id' => $targetUserId]);
        $targetUser = $targetStmt->fetch();

        if (!$targetUser) {
            snapix_send_post_action_error('user_not_found', 404);
        }

        $relationStmt = $pdo->prepare('SELECT id, status, declined_until FROM followers WHERE follower_id = :follower_id AND following_id = :following_id LIMIT 1');
        $relationStmt->execute([
            'follower_id' => (int) $user['id'],
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

        $nextStatus = !empty($targetUser['is_private']) ? 'pending' : 'accepted';

        if (!$relation) {
            $insertFollowStmt = $pdo->prepare('INSERT INTO followers (follower_id, following_id, status, declined_until) VALUES (:follower_id, :following_id, :status, NULL)');
            $insertFollowStmt->execute([
                'follower_id' => (int) $user['id'],
                'following_id' => $targetUserId,
                'status' => $nextStatus,
            ]);
        } elseif ($relation['status'] !== 'accepted') {
            $updateFollowStmt = $pdo->prepare('UPDATE followers SET status = :status, declined_until = NULL WHERE id = :id');
            $updateFollowStmt->execute([
                'status' => $nextStatus,
                'id' => (int) $relation['id'],
            ]);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'status' => $nextStatus]);
        exit;
    }

    if ($action === 'report_comment') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        $reportReason = trim((string) ($_POST['reason'] ?? ($_POST['report_reason'] ?? $_POST['custom_reason'] ?? '')));
        $allowedReasons = ['Спам', 'Оскорбления или ненависть', 'Насилие', 'Ложная информация', 'Нежелательный контент', 'Нарушение авторских прав', 'Другое'];

        if ($commentId <= 0) {
            snapix_send_post_action_error('invalid_comment', 422);
        }
        if ($reportReason === '') {
            snapix_send_post_action_error('invalid_report_reason', 422);
        }
        if (!in_array($reportReason, $allowedReasons, true)) {
            $reportReason = mb_substr($reportReason, 0, 1000);
        }

        $commentStmt = $pdo->prepare("SELECT comments.id, comments.post_id, comments.user_id, comments.comment_text, posts.user_id AS post_owner_id FROM comments INNER JOIN posts ON posts.id = comments.post_id WHERE comments.id = :id AND comments.is_deleted = 0 AND (comments.status = 'published' OR comments.status IS NULL) LIMIT 1");
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
        snapix_notify_admins($pdo, [
            'actor_user_id' => (int) $user['id'],
            'notification_type' => 'report_comment',
            'post_id' => (int) $comment['post_id'],
            'comment_id' => $commentId,
            'report_id' => $reportId,
            'title' => 'Жалоба на комментарий',
            'message' => 'Поступила жалоба на комментарий под публикацией',
            'comment_text' => (string) ($comment['comment_text'] ?? ''),
            'report_reason' => $reportReasonText,
            'dedupe_minutes' => 10,
        ], (int) $user['id']);

        if ($isAjaxPostAction) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'message' => 'Жалоба отправлена']);
            exit;
        }

        header('Location: index.php');
        exit;
    }

    if ($action === 'delete_comment') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        if ($commentId <= 0) {
            snapix_send_post_action_error('invalid_comment', 422);
        }

        $commentStmt = $pdo->prepare("SELECT comments.id, comments.post_id, comments.user_id, comments.attachment_url, posts.user_id AS post_owner_id FROM comments INNER JOIN posts ON posts.id = comments.post_id WHERE comments.id = :id AND comments.is_deleted = 0 AND (comments.status = 'published' OR comments.status IS NULL) LIMIT 1");
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

        $deleteCommentStmt = $pdo->prepare('DELETE FROM comments WHERE id = :id LIMIT 1');
        $deleteCommentStmt->execute(['id' => $commentId]);

        $attachmentUrl = (string) ($comment['attachment_url'] ?? '');
        if ($attachmentUrl !== '' && str_starts_with($attachmentUrl, 'uploads/comment_attachments/')) {
            $attachmentPath = __DIR__ . '/' . $attachmentUrl;
            if (is_file($attachmentPath)) {
                unlink($attachmentPath);
            }
        }

        if ($isAjaxPostAction) {
            snapix_send_post_action_json($pdo, (int) $comment['post_id'], (int) $user['id'], [
                'deleted_comment_id' => $commentId,
            ]);
        }

        header('Location: index.php');
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

    $postId = (int) ($_POST['post_id'] ?? 0);
    $ownerId = (int) ($_POST['owner_id'] ?? 0);
    $postExists = false;

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
            $attachment = isset($_FILES['attachment']) ? uploadCommentAttachment($_FILES['attachment']) : null;

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

            if ($commentText !== '' || $attachment) {
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
                    'id' => $commentId,
                    'post_id' => $postId,
                    'parent_comment_id' => $parentCommentId,
                    'post_owner_id' => $postOwnerId,
                    'user_id' => (int) $user['id'],
                    'login' => (string) $user['login'],
                    'avatar_url' => (string) ($user['avatar'] ?? ''),
                    'avatar' => (string) ($user['avatar'] ?? ''),
                    'profile_url' => buildProfileUrl((int) $user['id'], (int) $user['id']),
                    'comment_text' => $commentValue,
                    'text' => $commentValue,
                    'attachment_url' => $attachment['path'] ?? '',
                    'attachment_type' => $attachment['type'] ?? '',
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
            $deletePostStmt = $pdo->prepare('UPDATE posts SET is_deleted = 1 WHERE id = :id AND user_id = :user_id');
            $deletePostStmt->execute([
                'id' => $postId,
                'user_id' => $user['id'],
            ]);
        }

        if ($postExists && $action === 'pin_post' && $ownerId === (int) $user['id']) {
            $pinExistsStmt = $pdo->prepare('SELECT id FROM pinned_posts WHERE user_id = :user_id AND post_id = :post_id');
            $pinExistsStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);

            if (!$pinExistsStmt->fetchColumn()) {
                $pinPostStmt = $pdo->prepare('INSERT INTO pinned_posts (user_id, post_id) VALUES (:user_id, :post_id)');
                $pinPostStmt->execute([
                    'user_id' => $user['id'],
                    'post_id' => $postId,
                ]);
            }
        }

        if ($postExists && $action === 'hide_post' && $ownerId !== (int) $user['id']) {
            $hideStmt = $pdo->prepare('INSERT IGNORE INTO hidden_posts (user_id, post_id) VALUES (:user_id, :post_id)');
            $hideStmt->execute([
                'user_id' => $user['id'],
                'post_id' => $postId,
            ]);
        }

        if ($postExists && $action === 'report_post' && $ownerId !== (int) $user['id']) {
            $reportPostStmt = $pdo->prepare('
                INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text)
                VALUES (:reporter_user_id, :target_user_id, :reason_text)
            ');
            $reportReason = trim((string) ($_POST['report_reason'] ?? ''));
            $reportReasonText = mb_substr($reportReason !== '' ? $reportReason : ('Жалоба на пост #' . $postId), 0, 1000);
            $reportPostStmt->execute([
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
            $reportUserStmt = $pdo->prepare('
                INSERT INTO moderation_reports (reporter_user_id, target_user_id, reason_text)
                VALUES (:reporter_user_id, :target_user_id, :reason_text)
            ');
            $reportReasonText = 'Жалоба на пользователя через пост #' . $postId;
            $reportUserStmt->execute([
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

        if ($postExists && $action === 'block_user' && $ownerId > 0 && $ownerId !== (int) $user['id']) {
            $blockUserStmt = $pdo->prepare('
                INSERT IGNORE INTO user_blocks (blocker_user_id, blocked_user_id)
                VALUES (:blocker_user_id, :blocked_user_id)
            ');
            $blockUserStmt->execute([
                'blocker_user_id' => (int) $user['id'],
                'blocked_user_id' => $ownerId,
            ]);
        }
    }

    if ($isAjaxPostAction) {
        if ($postId <= 0 || !$postExists) {
            snapix_send_post_action_error('post_not_found', 404);
        }

        snapix_send_post_action_json($pdo, $postId, (int) $user['id'], $ajaxExtra);
    }

    if ($commentsPostId > 0) {
        header('Location: index.php?comments_post=' . $commentsPostId);
        exit;
    }

    header('Location: index.php');
    exit;
}

$currentUserId = (int) ($user['id'] ?? 0);

$feedStmt = $pdo->query('
    SELECT
        posts.id,
        posts.caption,
        posts.created_at,
        users.id AS user_id,
        users.login,
        users.avatar,
        post_media.media_type,
        post_media.media_url,
        (
            SELECT COUNT(*)
            FROM likes
            WHERE likes.post_id = posts.id
        ) AS likes_count,
        (
            SELECT COUNT(*)
            FROM comments
            WHERE comments.post_id = posts.id AND comments.is_deleted = 0 AND (comments.status = \'published\' OR comments.status IS NULL)
        ) AS comments_count,
        (
            SELECT COUNT(*)
            FROM likes
            WHERE likes.post_id = posts.id
              AND likes.user_id = ' . (int) ($user['id'] ?? 0) . '
        ) AS is_liked,
        (
            SELECT COUNT(*)
            FROM saved_posts
            WHERE saved_posts.post_id = posts.id
        ) AS saves_count,
        (
            SELECT COUNT(*)
            FROM saved_posts
            WHERE saved_posts.post_id = posts.id
              AND saved_posts.user_id = ' . (int) ($user['id'] ?? 0) . '
        ) AS is_saved,
        (
            SELECT COUNT(*)
            FROM reposts
            WHERE reposts.post_id = posts.id
        ) AS reposts_count,
        (
            SELECT COUNT(*)
            FROM messages
            WHERE messages.post_id = posts.id
        ) AS shares_count,
        (
            SELECT COUNT(*)
            FROM reposts
            WHERE reposts.post_id = posts.id
              AND reposts.user_id = ' . (int) ($user['id'] ?? 0) . '
        ) AS is_reposted,
        (
            SELECT COUNT(*)
            FROM pinned_posts
            WHERE pinned_posts.post_id = posts.id
              AND pinned_posts.user_id = ' . $currentUserId . '
        ) AS is_pinned,
        EXISTS(
            SELECT 1
            FROM followers current_follow
            WHERE current_follow.follower_id = ' . $currentUserId . '
              AND current_follow.following_id = posts.user_id
              AND current_follow.status = \'accepted\'
        ) AS is_following_author,
        EXISTS(
            SELECT 1
            FROM followers author_follow
            WHERE author_follow.follower_id = posts.user_id
              AND author_follow.following_id = ' . $currentUserId . '
              AND author_follow.status = \'accepted\'
        ) AS is_author_follower
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    LEFT JOIN post_media ON post_media.post_id = posts.id AND post_media.position = 1
    WHERE posts.is_deleted = 0
      AND posts.user_id <> ' . $currentUserId . '
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
    ORDER BY posts.created_at DESC
    LIMIT 40
');
$feedPosts = $feedStmt->fetchAll();

$commentMap = [];
$repostMap = [];

if ($feedPosts) {
    $postIds = array_map(static fn($post): int => (int) $post['id'], $feedPosts);
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));

    $commentsStmt = $pdo->prepare(" 
        SELECT
            comments.id,
            comments.post_id,
            comments.parent_comment_id,
            comments.comment_text,
            comments.attachment_url,
            comments.attachment_type,
            comments.created_at,
            comment_posts.user_id AS post_owner_id,
            users.id AS user_id,
            users.login,
            users.avatar,
            (SELECT COUNT(*) FROM comment_likes WHERE comment_likes.comment_id = comments.id) AS likes_count,
            (SELECT COUNT(*) FROM comment_likes WHERE comment_likes.comment_id = comments.id AND comment_likes.user_id = {$currentUserId}) AS is_liked
        FROM comments
        INNER JOIN posts AS comment_posts ON comment_posts.id = comments.post_id
        INNER JOIN users ON users.id = comments.user_id
        WHERE comments.is_deleted = 0
          AND (comments.status = 'published' OR comments.status IS NULL)
          AND comments.post_id IN ($placeholders)
        ORDER BY comments.post_id ASC, comments.created_at DESC, comments.id DESC
    ");
    $commentsStmt->execute($postIds);

    foreach ($commentsStmt->fetchAll() as $comment) {
        $currentPostId = (int) $comment['post_id'];

        if (!isset($commentMap[$currentPostId])) {
            $commentMap[$currentPostId] = [];
        }

        $comment['comment_id'] = (int) $comment['id'];
        $comment['parent_comment_id'] = (int) ($comment['parent_comment_id'] ?? 0);
        $comment['post_owner_id'] = (int) ($comment['post_owner_id'] ?? 0);
        $comment['avatar_url'] = (string) ($comment['avatar'] ?? '');
        $comment['profile_url'] = buildProfileUrl((int) $comment['user_id'], $user ? (int) $user['id'] : null);
        $comment['text'] = (string) ($comment['comment_text'] ?? '');
        $comment['attachment_url'] = (string) ($comment['attachment_url'] ?? '');
        $comment['attachment_type'] = (string) ($comment['attachment_type'] ?? '');
        $comment['likes_count'] = (int) ($comment['likes_count'] ?? 0);
        $comment['is_liked'] = (int) ($comment['is_liked'] ?? 0) > 0;
        $comment['created_at'] = !empty($comment['created_at']) ? date('d.m.Y H:i', strtotime((string) $comment['created_at'])) : '';
        $commentMap[$currentPostId][] = $comment;
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
<body data-page="home">
    <?php render_side_menu($user); ?>

    <div class="home-feed-tabs" role="tablist" aria-label="Переключатель ленты">
        <button type="button" class="home-feed-tab is-active" role="tab" aria-selected="true" data-feed-tab="for-you">Для вас</button>
        <button type="button" class="home-feed-tab" role="tab" aria-selected="false" data-feed-tab="following">Подписки</button>
    </div>

    <main data-feed-content="for-you">
        <div class="main-feed-layout">
            <section class="feed-wrap" aria-label="Лента публикаций">

                <?php if ($feedPosts): ?>
                    <div class="feed-list feed-posts-grid">
                    <?php foreach ($feedPosts as $post): ?>
                        <?php $postComments = $commentMap[(int) $post['id']] ?? []; ?>
                        <?php $postReposters = $repostMap[(int) $post['id']] ?? []; ?>
                        <?php $authorProfileUrl = $user ? buildProfileUrl((int) $post['user_id'], (int) $user['id']) : 'login.php'; ?>
                        <?php $feedScope = (int) $post['is_following_author'] > 0 ? 'following' : 'for-you'; ?>
                        <article class="feed-card card-surface" id="post-<?php echo (int) $post['id']; ?>" data-post-card-id="<?php echo (int) $post['id']; ?>" data-post-id="<?php echo (int) $post['id']; ?>" data-post-author-id="<?php echo (int) ($post['user_id'] ?? 0); ?>" data-post-media-url="<?php echo htmlspecialchars((string) ($post['media_url'] ?? '')); ?>" data-post-media-type="<?php echo htmlspecialchars((string) ($post['media_type'] ?? 'image')); ?>" data-post-author-login="<?php echo htmlspecialchars((string) ($post['login'] ?? '')); ?>" data-post-author-avatar="<?php echo htmlspecialchars((string) ($post['avatar'] ?? '')); ?>" data-post-likes-count="<?php echo (int) ($post['likes_count'] ?? 0); ?>" data-post-comments-count="<?php echo (int) ($post['comments_count'] ?? 0); ?>" data-post-reposts-count="<?php echo (int) ($post['reposts_count'] ?? 0); ?>" data-post-shares-count="<?php echo (int) ($post['shares_count'] ?? 0); ?>" data-post-saves-count="<?php echo (int) ($post['saves_count'] ?? 0); ?>" data-post-liked="<?php echo (int) $post['is_liked'] > 0 ? '1' : '0'; ?>" data-post-saved="<?php echo (int) $post['is_saved'] > 0 ? '1' : '0'; ?>" data-post-reposted="<?php echo (int) $post['is_reposted'] > 0 ? '1' : '0'; ?>" data-post-is-following-author="<?php echo (int) $post['is_following_author'] > 0 ? '1' : '0'; ?>" data-feed-scope="<?php echo htmlspecialchars($feedScope); ?>">
                            <header class="feed-card-header">
                                <div class="feed-header-main">
                                    <a href="<?php echo htmlspecialchars($authorProfileUrl); ?>" class="feed-author-avatar-link" aria-label="Открыть профиль <?php echo htmlspecialchars($post['login']); ?>">
                                        <div class="feed-author-avatar"<?php if (!empty($post['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($post['avatar']); ?>');"<?php endif; ?>>
                                            <?php if (empty($post['avatar'])): ?>
                                                <?php echo htmlspecialchars(mb_substr($post['login'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                    <div class="feed-author-line">
                                        <a href="<?php echo htmlspecialchars($authorProfileUrl); ?>" class="feed-author-name">
                                            <strong><?php echo htmlspecialchars($post['login']); ?></strong>
                                        </a>
                                        <?php if ($user): ?>
                                        <div class="post-menu-wrap">
                                            <button type="button" class="post-menu-toggle" data-post-menu="post-menu-<?php echo (int) $post['id']; ?>" aria-label="Действия с публикацией"><?php echo snapix_icon('more-horizontal'); ?></button>
                                            <div class="post-menu" id="post-menu-<?php echo (int) $post['id']; ?>">
                                                <a href="<?php echo htmlspecialchars($authorProfileUrl); ?>" class="post-menu-item">
                                                    <img src="icon/dark theme/об аккаунте.png" alt="">
                                                    <span>Об аккаунте</span>
                                                </a>
                                                <?php if ($user): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="hide_post">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <input type="hidden" name="owner_id" value="<?php echo (int) $post['user_id']; ?>">
                                                        <button type="submit" class="post-menu-item">
                                                            <img src="icon/dark theme/dislike.png" alt="">
                                                            <span>Мне не интересно</span>
                                                        </button>
                                                    </form>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="block_user">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <input type="hidden" name="owner_id" value="<?php echo (int) $post['user_id']; ?>">
                                                        <button type="submit" class="post-menu-item">
                                                            <img src="icon/dark theme/stop.png" alt="">
                                                            <span>Добавить <?php echo htmlspecialchars($post['login']); ?> в чёрный список</span>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <a href="login.php" class="post-menu-item">
                                                        <img src="icon/dark theme/dislike.png" alt="">
                                                        <span>Мне не интересно</span>
                                                    </a>
                                                    <a href="login.php" class="post-menu-item">
                                                        <img src="icon/dark theme/stop.png" alt="">
                                                        <span>Добавить <?php echo htmlspecialchars($post['login']); ?> в чёрный список</span>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="post-menu-item js-copy-post-link" data-post-url="<?php echo htmlspecialchars('post.php?id=' . (int) $post['id'], ENT_QUOTES); ?>">
                                                    <img src="icon/dark theme/copy.png" alt="">
                                                    <span>Поделиться</span>
                                                </button>
                                                <?php if ($user): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="report_post">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <input type="hidden" name="owner_id" value="<?php echo (int) $post['user_id']; ?>">
                                                        <button type="submit" class="post-menu-item post-menu-item-danger" data-report-trigger data-report-login="<?php echo htmlspecialchars((string) ($post['login'] ?? 'user')); ?>" data-report-user-id="<?php echo (int) $post['user_id']; ?>">
                                                            <img src="icon/complaint.png" alt="">
                                                            <span>Пожаловаться</span>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <a href="login.php" class="post-menu-item post-menu-item-danger">
                                                        <img src="icon/complaint.png" alt="">
                                                        <span>Пожаловаться</span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </header>

                            <div class="feed-card-media">
                                <span class="post-type-badge" aria-hidden="true">
                                    <img class="home-post-type-icon <?php echo (($post['media_type'] ?? '') === 'video') ? 'home-post-type-video-icon' : 'home-post-type-image-icon'; ?>" src="<?php echo (($post['media_type'] ?? '') === 'video') ? 'icon/light theme/video.png' : 'icon/light theme/images.png'; ?>" alt="">
                                </span>
                                <?php if (($post['media_type'] ?? '') === 'video' && !empty($post['media_url'])): ?>
                                    <video controls preload="metadata" src="<?php echo htmlspecialchars($post['media_url']); ?>"></video>
                                <?php elseif (!empty($post['media_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="Публикация пользователя <?php echo htmlspecialchars($post['login']); ?>">
                                <?php else: ?>
                                    <div class="feed-card-media-placeholder">Медиа не доступно</div>
                                <?php endif; ?>

                                <div class="post-hover-overlay">
                                    <?php if ($user): ?>
                                        <div class="profile-hover-action-item">
                                            <form method="post" class="inline-action-form profile-hover-action-form">
                                                <input type="hidden" name="action" value="toggle_like">
                                                <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                <button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-like-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк">
                                                    <img class="home-hover-like-icon" src="icon/dark theme/like.png" alt="Лайк">
                                                </button>
                                            </form>
                                            <span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span>
                                        </div>
                                        <div class="profile-hover-action-item">
                                            <form method="post" class="inline-action-form profile-hover-action-form">
                                                <input type="hidden" name="action" value="add_repost">
                                                <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                <button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-repost-btn<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост">
                                                    <img class="home-hover-repost-icon" src="icon/dark theme/repost.png" alt="Репост">
                                                </button>
                                            </form>
                                            <span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span>
                                        </div>
                                        <div class="profile-hover-action-item">
                                            <form method="post" class="inline-action-form profile-hover-action-form">
                                                <input type="hidden" name="action" value="toggle_save">
                                                <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                <button type="submit" class="feed-action-btn profile-hover-action-btn profile-hover-save-btn<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное">
                                                    <img class="home-hover-save-icon" src="icon/dark theme/favourites.png" alt="Избранное">
                                                </button>
                                            </form>
                                            <span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="profile-hover-action-item"><a href="login.php" class="feed-action-btn profile-hover-action-btn" aria-label="Войти для лайка"><img class="home-hover-like-icon" src="icon/dark theme/like.png" alt="Лайк"></a><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                        <div class="profile-hover-action-item"><a href="login.php" class="feed-action-btn profile-hover-action-btn" aria-label="Войти для репоста"><img class="home-hover-repost-icon" src="icon/dark theme/repost.png" alt="Репост"></a><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                        <div class="profile-hover-action-item"><a href="login.php" class="feed-action-btn profile-hover-action-btn" aria-label="Войти для избранного"><img class="home-hover-save-icon" src="icon/dark theme/favourites.png" alt="Избранное"></a><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="feed-card-body">
                                <div class="feed-card-actions">

                                    <?php if ($user): ?>
                                        <div class="feed-card-buttons">
                                            <div class="feed-card-buttons-group feed-card-buttons-left">
                                                <div class="feed-action-item">
                                                    <form method="post" class="inline-action-form">
                                                        <input type="hidden" name="action" value="toggle_like">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <button type="submit" class="feed-action-btn feed-icon-btn<?php echo (int) $post['is_liked'] > 0 ? ' is-active' : ''; ?>" aria-label="Лайк"><img src="icon/dark theme/like.png" alt=""></button>
                                                    </form>
                                                    <span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span>
                                                </div>

                                                <div class="feed-action-item">
                                                    <button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button>
                                                    <span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span>
                                                </div>

                                                <div class="feed-action-item">
                                                    <form method="post" class="inline-action-form">
                                                        <input type="hidden" name="action" value="add_repost">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-repost<?php echo (int) $post['is_reposted'] > 0 ? ' is-reposted' : ''; ?>" aria-label="Репост"><img src="icon/dark theme/repost.png" alt=""></button>
                                                    </form>
                                                    <span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span>
                                                </div>
                                            </div>

                                            <div class="feed-card-buttons-group feed-card-buttons-right">
                                                <div class="feed-action-item">
                                                    <form method="post" class="inline-action-form">
                                                        <input type="hidden" name="action" value="toggle_save">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <button type="submit" class="feed-action-btn feed-icon-btn feed-action-btn-save<?php echo (int) $post['is_saved'] > 0 ? ' is-saved' : ''; ?>" aria-label="Избранное"><img src="icon/dark theme/favourites.png" alt=""></button>
                                                    </form>
                                                    <span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span>
                                                </div>

                                                <div class="feed-action-item">
                                                    <button type="button" class="feed-action-btn feed-icon-btn js-open-share-modal" data-post-id="<?php echo (int) $post['id']; ?>" aria-label="Отправить в сообщения"><img src="icon/dark theme/share.png" alt=""></button>
                                                    <span class="feed-action-count" data-post-id="<?php echo (int) $post['id']; ?>" data-post-count="shares"><?php echo (int) ($post['shares_count'] ?? 0); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="feed-card-buttons">
                                            <div class="feed-card-buttons-group feed-card-buttons-left">
                                                <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для лайка"><img src="icon/dark theme/like.png" alt=""></a><span class="feed-action-count"><?php echo (int) $post['likes_count']; ?></span></div>
                                                <div class="feed-action-item"><button type="button" class="feed-action-btn feed-icon-btn js-open-comments-modal" data-modal="comments-modal-<?php echo (int) $post['id']; ?>" aria-label="Комментарии"><img src="icon/dark theme/comment.png" alt=""></button><span class="feed-action-count"><?php echo (int) $post['comments_count']; ?></span></div>
                                                <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для репоста"><img src="icon/dark theme/repost.png" alt=""></a><span class="feed-action-count"><?php echo (int) $post['reposts_count']; ?></span></div>
                                            </div>
                                            <div class="feed-card-buttons-group feed-card-buttons-right">
                                                <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для избранного"><img src="icon/dark theme/favourites.png" alt=""></a><span class="feed-action-count"><?php echo (int) $post['saves_count']; ?></span></div>
                                                <div class="feed-action-item"><a href="login.php" class="feed-action-btn feed-icon-btn" aria-label="Войти для отправки в сообщения"><img src="icon/dark theme/share.png" alt=""></a><span class="feed-action-count" data-post-id="<?php echo (int) $post['id']; ?>" data-post-count="shares"><?php echo (int) ($post['shares_count'] ?? 0); ?></span></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($post['caption'])): ?>
                                    <div class="feed-card-caption">
                                        <?php echo nl2br(htmlspecialchars($post['caption'])); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="comments-modal" id="comments-modal-<?php echo (int) $post['id']; ?>">
                                    <div class="comments-modal-overlay js-close-comments-modal" data-modal="comments-modal-<?php echo (int) $post['id']; ?>"></div>
                                    <div class="comments-modal-dialog">
                                        <div class="comments-modal-header">
                                            <h3>Комментарии</h3>
                                            <button type="button" class="feed-action-btn feed-icon-btn js-close-comments-modal" data-modal="comments-modal-<?php echo (int) $post['id']; ?>" aria-label="Закрыть"><?php echo snapix_icon('x'); ?></button>
                                        </div>
                                        <div class="comments-modal-body">
                                            <?php if ($postComments): ?>
                                                <?php foreach ($postComments as $comment): ?>
                                                    <?php $commentProfileUrl = buildProfileUrl((int) $comment['user_id'], $user ? (int) $user['id'] : null); ?>
                                                    <div class="comment-item">
                                                        <a href="<?php echo htmlspecialchars($commentProfileUrl); ?>" class="comment-author"><strong><?php echo htmlspecialchars($comment['login']); ?></strong></a>
                                                        <p><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="comments-empty">Пока нет комментариев.</p>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($user): ?>
                                            <form method="post" class="comment-form comments-modal-form">
                                                <input type="hidden" name="action" value="add_comment">
                                                <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                <textarea name="comment_text" rows="2" maxlength="1000" placeholder="Напишите комментарий..."></textarea>
                                                <button type="submit" class="primary-link">Отправить</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                    <p class="empty-state feed-tab-empty is-hidden" data-feed-empty="for-you">В разделе «Для вас» пока нет новых публикаций.</p>
                    <p class="empty-state feed-tab-empty is-hidden" data-feed-empty="following">В подписках пока нет публикаций.</p>
                <?php else: ?>
                    <p class="empty-state">Лента публикаций пустая. Добавьте первую публикацию.</p>
                <?php endif; ?>
            </section>

            <aside class="home-right-sidebar" aria-label="Правая колонка главной страницы">
                <?php if ($user): ?>
                    <section class="home-sidebar-card home-sidebar-user">
                        <a href="profile.php" class="home-sidebar-user-link">
                            <span class="home-sidebar-avatar"<?php if (!empty($user['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($user['avatar']); ?>');"<?php endif; ?>>
                                <?php if (empty($user['avatar'])): ?><?php echo htmlspecialchars(mb_substr($user['login'], 0, 1)); ?><?php endif; ?>
                            </span>
                            <span class="home-sidebar-user-meta">
                                <strong><?php echo htmlspecialchars($user['login']); ?></strong>
                                <small>Текущий пользователь</small>
                            </span>
                        </a>
                    </section>
                <?php endif; ?>

                <section class="home-sidebar-card home-sidebar-topics">
                    <h2>Популярные темы</h2>
                    <div class="home-sidebar-hashtags" aria-label="Популярные хештеги">
                        <a href="search.php?q=%23snapix">#snapix</a>
                        <a href="search.php?q=%23photo">#photo</a>
                        <a href="search.php?q=%23travel">#travel</a>
                        <a href="search.php?q=%23style">#style</a>
                        <a href="search.php?q=%23art">#art</a>
                    </div>
                </section>

                <nav class="home-sidebar-card home-sidebar-links" aria-label="Юридические ссылки">
                    <a href="privacy.html">Политика конфиденциальности</a>
                    <a href="terms.html">Условия использования</a>
                </nav>

                <p class="home-sidebar-copyright">© <?php echo date('Y'); ?> Snapix</p>
            </aside>
        </div>
    </main>
<div class="profile-post-viewer" id="profilePostViewer" aria-hidden="true">
    <div class="profile-post-viewer-overlay" data-post-viewer-close></div>
    <div class="profile-post-viewer-dialog" role="dialog" aria-modal="true" aria-label="Просмотр публикации">
        <div class="post-viewer-menu-wrap" data-post-viewer-menu-wrap>
            <button type="button" class="post-viewer-menu-toggle" data-post-viewer-menu-toggle aria-label="Действия с публикацией" aria-expanded="false">•••</button>
            <div class="post-viewer-menu" data-post-viewer-menu hidden></div>
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
            <?php if ($user): ?>
                <form method="post" class="profile-post-viewer-input-row" id="profilePostViewerCommentForm">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="post_id" value="">
                    <input type="hidden" name="parent_comment_id" value="">
                    <button type="button" class="profile-post-viewer-round-btn" id="profilePostViewerAttachmentButton" aria-label="Прикрепить фото"><img class="icon-dark" src="icon/dark theme/paper clip.png" alt="Скрепка"><img class="icon-light" src="icon/light theme/paper clip.png" alt=""></button>
                    <input class="profile-post-viewer-file-input" id="profilePostViewerAttachmentInput" type="file" accept="image/gif,image/jpeg,image/png,image/webp" hidden>
                    <div class="profile-post-viewer-emoji-wrap">
                        <button type="button" class="profile-post-viewer-round-btn" id="profilePostViewerEmojiButton" aria-label="Выбрать эмодзи" aria-expanded="false" aria-controls="profilePostViewerEmojiPicker"><img class="icon-dark" src="icon/dark theme/add stickers.png" alt=""><img class="icon-light" src="icon/light theme/add stickers.png" alt=""></button>
                        <div class="profile-post-viewer-emoji-picker" id="profilePostViewerEmojiPicker" hidden>
                            <button type="button" data-emoji="😀">😀</button><button type="button" data-emoji="😂">😂</button><button type="button" data-emoji="😍">😍</button><button type="button" data-emoji="🥰">🥰</button><button type="button" data-emoji="😎">😎</button><button type="button" data-emoji="👍">👍</button><button type="button" data-emoji="🔥">🔥</button><button type="button" data-emoji="❤️">❤️</button>
                        </div>
                    </div>
                    <div class="profile-post-viewer-input-shell" id="profilePostViewerInputShell"><div class="profile-post-viewer-attachment-preview" id="profilePostViewerAttachmentPreview" hidden><img src="" alt="Предпросмотр вложения"><button type="button" id="profilePostViewerAttachmentRemove" aria-label="Удалить вложение">×</button></div><textarea class="profile-post-viewer-input" name="comment_text" rows="1" maxlength="1000" placeholder="Добавить комментарий" aria-label="Добавить комментарий"></textarea><button type="submit" class="profile-post-viewer-send-btn" aria-label="Отправить"><img src="icon/message.png" alt=""></button></div>
                </form>
            <?php else: ?>
                <div class="profile-post-viewer-input-row"><a class="profile-post-viewer-login-link" href="login.php">Войдите, чтобы комментировать</a></div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<?php if ($user): ?>
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
<?php endif; ?>
<script src="js/post-sync.js"></script>
<script>
window.snapixProfileComments = <?php echo json_encode($commentMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
(function () {
    var viewer = document.getElementById('profilePostViewer');
    var mediaHost = document.getElementById('profilePostViewerMedia');
    var avatar = document.getElementById('profilePostViewerAvatar');
    var login = document.getElementById('profilePostViewerLogin');
    var follow = document.getElementById('profilePostViewerFollow');
    var commentsHost = document.getElementById('profilePostViewerComments');
    var commentForm = document.getElementById('profilePostViewerCommentForm');
    var postViewerMenuToggle = viewer.querySelector('[data-post-viewer-menu-toggle]');
    var postViewerMenu = viewer.querySelector('[data-post-viewer-menu]');
    var currentUserId = <?php echo (int) ($user['id'] ?? 0); ?>;
    var currentUserRole = <?php echo json_encode((string) ($user['role'] ?? 'user'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    var actionToEndpoint = { like: 'toggle_like', repost: 'add_repost', save: 'toggle_save' };

    if (!viewer || !mediaHost) {
        return;
    }

    function closePostViewerMenu() {
        if (!postViewerMenu || !postViewerMenuToggle) return;
        postViewerMenu.hidden = true;
        postViewerMenuToggle.setAttribute('aria-expanded', 'false');
    }

    function togglePostViewerMenu(event) {
        event.preventDefault();
        event.stopPropagation();
        if (!postViewerMenu || !postViewerMenuToggle) return;
        var shouldOpen = postViewerMenu.hidden;
        postViewerMenu.hidden = !shouldOpen;
        postViewerMenuToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    }

    function postComments(postId) {
        return (window.snapixProfileComments || {})[String(postId)] || [];
    }

    function isOwnComment(comment) {
        return !!currentUserId && !!comment && Number(comment.user_id || 0) === Number(currentUserId);
    }

    function canReportComment(comment) {
        return !!currentUserId && !!comment && !isOwnComment(comment);
    }

    function canDeleteComment(comment) {
        if (!currentUserId || !comment) return false;
        if (['admin', 'moderator'].indexOf(currentUserRole) !== -1) return true;
        if (isOwnComment(comment)) return true;
        return Number(comment.post_owner_id || 0) === Number(currentUserId);
    }

    function removeCommentFromCache(postId, commentId) {
        var comments = postComments(postId);
        var idsToRemove = [String(commentId)];
        var added = true;

        while (added) {
            added = false;
            comments.forEach(function (comment) {
                var id = String(comment.comment_id || comment.id || '');
                var parentId = String(comment.parent_comment_id || '');
                if (parentId && idsToRemove.indexOf(parentId) !== -1 && idsToRemove.indexOf(id) === -1) {
                    idsToRemove.push(id);
                    added = true;
                }
            });
        }

        window.snapixProfileComments = window.snapixProfileComments || {};
        window.snapixProfileComments[String(postId)] = comments.filter(function (comment) {
            return idsToRemove.indexOf(String(comment.comment_id || comment.id || '')) === -1;
        });
    }

    function updateCommentLikeCache(commentId, isLiked, likesCount) {
        Object.keys(window.snapixProfileComments || {}).forEach(function (postId) {
            (window.snapixProfileComments[postId] || []).forEach(function (comment) {
                if (String(comment.comment_id || comment.id || '') === String(commentId)) {
                    comment.is_liked = !!isLiked;
                    comment.likes_count = Number(likesCount || 0);
                }
            });
        });
    }

    function closeCommentMenus(exceptMenu) {
        document.querySelectorAll('#profilePostViewer .profile-viewer-comment-menu.is-open').forEach(function (menu) {
            if (!exceptMenu || menu !== exceptMenu) {
                menu.classList.remove('is-open');
            }
        });
    }

    function escapeText(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
        });
    }

    function buildViewerCommentNode(comment, repliesByParent, postId) {
        var row = document.createElement('article');
        row.className = 'profile-viewer-comment';
        row.setAttribute('data-comment-id', String(comment.comment_id || comment.id || ''));
        row.setAttribute('data-post-id', String(comment.post_id || postId));
        row.setAttribute('data-parent-comment-id', String(comment.parent_comment_id || ''));

        var profileUrl = comment.profile_url || (comment.user_id ? 'user.php?id=' + encodeURIComponent(String(comment.user_id)) : 'profile.php');
        var avatarNode = document.createElement('a');
        avatarNode.className = 'profile-viewer-comment-avatar';
        avatarNode.href = profileUrl;
        var avatarUrl = comment.avatar_url || comment.avatar || '';
        if (avatarUrl) {
            avatarNode.style.backgroundImage = "url('" + String(avatarUrl).replace(/'/g, "\'") + "')";
            avatarNode.textContent = '';
        } else {
            avatarNode.textContent = String(comment.login || '?').slice(0, 1).toUpperCase();
        }

        var body = document.createElement('div');
        body.className = 'profile-viewer-comment-body';

        var loginLink = document.createElement('a');
        loginLink.className = 'profile-viewer-comment-login';
        loginLink.href = profileUrl;
        loginLink.textContent = comment.login || '';
        body.appendChild(loginLink);

        var commentText = typeof comment.comment_text !== 'undefined' ? comment.comment_text : (comment.text || '');
        if (commentText) {
            var text = document.createElement('div');
            text.className = 'profile-viewer-comment-text';
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
        meta.className = 'profile-viewer-comment-meta';

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
        if (likeCount) likeCount.textContent = String(Number(comment.likes_count || 0));
        meta.appendChild(likeButton);

        if (canDeleteComment(comment) || canReportComment(comment)) {
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
            meta.appendChild(menu);
        }

        body.appendChild(meta);
        row.appendChild(avatarNode);
        row.appendChild(body);

        var replies = repliesByParent ? (repliesByParent[String(comment.comment_id || comment.id || '')] || []) : [];
        if (replies.length) {
            var repliesWrap = document.createElement('div');
            repliesWrap.className = 'profile-viewer-comment-replies';
            replies.forEach(function (reply) {
                repliesWrap.appendChild(buildViewerCommentNode(reply, repliesByParent, postId));
            });
            row.appendChild(repliesWrap);
        }

        return row;
    }

    function splitCommentsByParent(comments) {
        var ids = {};
        comments.forEach(function (comment) {
            ids[String(comment.comment_id || comment.id || '')] = true;
        });

        var roots = [];
        var repliesByParent = {};
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

    function renderComments(postId) {
        if (!commentsHost) {
            return;
        }
        var comments = postComments(postId).slice().reverse();
        commentsHost.innerHTML = '';
        commentsHost.classList.toggle('has-comments', comments.length > 0);

        if (!comments.length) {
            commentsHost.innerHTML = '<p class="profile-post-viewer-empty">Комментариев нет</p>';
            return;
        }

        var grouped = splitCommentsByParent(comments);
        grouped.roots.forEach(function (comment) {
            commentsHost.appendChild(buildViewerCommentNode(comment, grouped.repliesByParent, postId));
        });
    }

    window.snapixRenderViewerComments = renderComments;
    window.snapixAppendViewerComment = function (comment) {
        var postId = comment && comment.post_id ? String(comment.post_id) : (viewer.dataset.postId || '');
        if (!postId || !comment) return;
        if (!window.snapixProfileComments[String(postId)]) {
            window.snapixProfileComments[String(postId)] = [];
        }
        window.snapixProfileComments[String(postId)].push(comment);
        renderComments(postId);
        if (commentsHost) commentsHost.scrollTop = commentsHost.scrollHeight;
    };

    function setViewerCounts(card) {
        var map = {
            viewerLikesCount: card.dataset.postLikesCount || '0',
            viewerCommentsCount: card.dataset.postCommentsCount || '0',
            viewerRepostsCount: card.dataset.postRepostsCount || '0',
            viewerSharesCount: card.dataset.postSharesCount || '0',
            viewerSavesCount: card.dataset.postSavesCount || '0'
        };
        Object.keys(map).forEach(function (id) {
            var node = document.getElementById(id);
            if (node) node.textContent = map[id];
        });
    }

    function actionStateFromCard(card, action) {
        var selector = action === 'like' ? '.profile-hover-like-btn' : (action === 'repost' ? '.profile-hover-repost-btn' : '.profile-hover-save-btn');
        var button = card.querySelector(selector);
        if (action === 'like') return card.dataset.postLiked === '1' || !!(button && button.classList.contains('is-active'));
        if (action === 'repost') return card.dataset.postReposted === '1' || !!(button && button.classList.contains('is-reposted'));
        if (action === 'save') return card.dataset.postSaved === '1' || !!(button && button.classList.contains('is-saved'));
        return false;
    }

    function applyViewerState(data, animateAction) {
        if (!data) return;
        var likeButton = viewer.querySelector('[data-post-action="like"]');
        var repostButton = viewer.querySelector('[data-post-action="repost"]');
        var saveButton = viewer.querySelector('[data-post-action="save"]');
        if (likeButton && typeof data.liked !== 'undefined') likeButton.classList.toggle('is-active', !!data.liked);
        if (repostButton && typeof data.reposted !== 'undefined') repostButton.classList.toggle('is-reposted', !!data.reposted);
        if (saveButton && typeof data.saved !== 'undefined') saveButton.classList.toggle('is-saved', !!data.saved);
        ['likes', 'comments', 'reposts', 'shares', 'saves'].forEach(function (key) {
            var node = document.querySelector('#profilePostViewer [data-post-count="' + key + '"]');
            var value = data[key + '_count'];
            if (node && typeof value !== 'undefined') node.textContent = String(Number(value || 0));
        });
        if (animateAction) {
            var animated = viewer.querySelector('[data-post-action="' + animateAction + '"]');
            if (animated) {
                animated.classList.remove('is-activating');
                void animated.offsetWidth;
                animated.classList.add('is-activating');
                window.setTimeout(function () { animated.classList.remove('is-activating'); }, 260);
            }
        }
    }

    function syncCardDataset(postId, data) {
        document.querySelectorAll('[data-post-id="' + postId + '"]').forEach(function (node) {
            if (!node.dataset) return;
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

    function openViewer(card) {
        closePostViewerMenu();
        var postId = card.dataset.postId || '';
        var mediaUrl = card.dataset.postMediaUrl || '';
        var mediaType = card.dataset.postMediaType || 'image';
        mediaHost.innerHTML = '';
        if (mediaType === 'video') {
            var video = document.createElement('video');
            video.src = mediaUrl;
            video.controls = true;
            video.playsInline = true;
            mediaHost.appendChild(video);
        } else {
            var img = document.createElement('img');
            img.src = mediaUrl;
            img.alt = 'Публикация';
            mediaHost.appendChild(img);
        }

        login.textContent = card.dataset.postAuthorLogin || '';
        var avatarUrl = card.dataset.postAuthorAvatar || '';
        avatar.style.backgroundImage = avatarUrl ? "url('" + avatarUrl.replace(/'/g, "\\'") + "')" : '';
        avatar.textContent = avatarUrl ? '' : (login.textContent || '?').slice(0, 1).toUpperCase();
        var authorId = Number(card.dataset.postAuthorId || 0);
        var authorUrl = currentUserId && authorId === currentUserId ? 'profile.php' : 'user.php?id=' + encodeURIComponent(String(authorId));
        var avatarLink = document.getElementById('profilePostViewerAvatarLink');
        var loginLink = document.getElementById('profilePostViewerLoginLink');
        if (avatarLink) avatarLink.href = authorUrl;
        if (loginLink) loginLink.href = authorUrl;
        if (follow) {
            follow.hidden = !currentUserId || currentUserId === authorId || card.dataset.postIsFollowingAuthor === '1';
            follow.disabled = false;
            follow.classList.remove('is-following');
            follow.dataset.authorId = String(authorId || '');
        }

        viewer.dataset.postId = postId;
        viewer.dataset.postAuthorId = card.dataset.postAuthorId || '';
        viewer.querySelectorAll('[data-post-action], [data-post-count], .profile-post-viewer-metrics').forEach(function (node) {
            node.setAttribute('data-post-id', postId);
        });
        if (commentForm) {
            var postIdInput = commentForm.querySelector('input[name="post_id"]');
            var parentInput = commentForm.querySelector('input[name="parent_comment_id"]');
            var textInput = commentForm.querySelector('[name="comment_text"]');
            if (postIdInput) postIdInput.value = postId;
            if (parentInput) parentInput.value = '';
            if (textInput) textInput.value = '';
            if (window.snapixClearViewerAttachment) window.snapixClearViewerAttachment();
        }
        setViewerCounts(card);
        viewer.querySelector('[data-post-action="like"]')?.classList.toggle('is-active', actionStateFromCard(card, 'like'));
        viewer.querySelector('[data-post-action="repost"]')?.classList.toggle('is-reposted', actionStateFromCard(card, 'repost'));
        viewer.querySelector('[data-post-action="save"]')?.classList.toggle('is-saved', actionStateFromCard(card, 'save'));
        renderComments(postId);
        viewer.classList.add('is-open');
        viewer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('is-modal-open');
        document.body.style.overflow = 'hidden';
    }

    function closeViewer() {
        closePostViewerMenu();
        viewer.classList.remove('is-open');
        viewer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('is-modal-open');
        document.body.style.overflow = '';
        mediaHost.innerHTML = '';
    }

    window.snapixOpenPostViewer = openViewer;

    document.querySelectorAll('body[data-page="home"] .feed-posts-grid .feed-card[data-post-id]').forEach(function (card) {
        card.addEventListener('click', function (event) {
            if (event.target.closest('button, form, a, input, textarea, select, label, .profile-hover-action-item, .post-menu-wrap')) {
                return;
            }
            openViewer(card);
        });
    });

    var linkedPostId = new URLSearchParams(window.location.search).get('open_post') || new URLSearchParams(window.location.search).get('comments_post');
    if (linkedPostId) {
        var safeLinkedPostId = String(linkedPostId).replace(/[^0-9]/g, '');
        var linkedCard = safeLinkedPostId ? document.querySelector('body[data-page="home"] .feed-posts-grid .feed-card[data-post-id="' + safeLinkedPostId + '"]') : null;
        if (linkedCard) openViewer(linkedCard);
    }

    if (postViewerMenuToggle) {
        postViewerMenuToggle.addEventListener('click', togglePostViewerMenu);
    }
    viewer.querySelectorAll('[data-post-viewer-close]').forEach(function (node) {
        node.addEventListener('click', closeViewer);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && viewer.classList.contains('is-open')) closeViewer();
    });

    document.addEventListener('click', function (event) {
        if (viewer.classList.contains('is-open') && !(event.target.closest && event.target.closest('#profilePostViewer [data-post-viewer-menu-wrap]'))) {
            closePostViewerMenu();
        }
        closeCommentMenus(event.target.closest ? event.target.closest('#profilePostViewer .profile-viewer-comment-menu') : null);

        var replyButton = event.target.closest ? event.target.closest('#profilePostViewer .profile-viewer-comment-reply') : null;
        if (replyButton) {
            event.preventDefault();
            var replyCommentNode = replyButton.closest('.profile-viewer-comment');
            var replyCommentId = replyCommentNode ? replyCommentNode.getAttribute('data-comment-id') : '';
            var replyLogin = replyButton.dataset.replyLogin || '';
            if (!commentForm || !replyCommentId) return;

            var parentInput = commentForm.querySelector('input[name="parent_comment_id"]');
            var textInput = commentForm.querySelector('[name="comment_text"]');
            if (parentInput) parentInput.value = replyCommentId;
            if (textInput) {
                var prefix = replyLogin ? '@' + replyLogin + ' ' : '';
                if (prefix && textInput.value.indexOf(prefix) !== 0) {
                    textInput.value = prefix + textInput.value.replace(/^@\S+\s+/, '');
                }
                textInput.focus();
                var cursor = textInput.value.length;
                if (textInput.setSelectionRange) {
                    textInput.setSelectionRange(cursor, cursor);
                }
            }
            return;
        }

        var likeButton = event.target.closest ? event.target.closest('#profilePostViewer .profile-viewer-comment-like') : null;
        if (likeButton) {
            event.preventDefault();
            if (!currentUserId) return;
            var likeCommentNode = likeButton.closest('.profile-viewer-comment');
            var likeCommentId = likeCommentNode ? likeCommentNode.getAttribute('data-comment-id') : '';
            if (!likeCommentId || likeButton.disabled) return;

            var likeParams = new URLSearchParams();
            likeParams.set('action', 'toggle_comment_like');
            likeParams.set('comment_id', likeCommentId);
            likeButton.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json'
                },
                body: likeParams.toString()
            }).then(function (response) { return response.json(); }).then(function (data) {
                if (!data || !data.ok) return;
                likeButton.classList.toggle('is-active', !!data.liked);
                var countNode = likeButton.querySelector('[data-comment-like-count]');
                if (countNode) countNode.textContent = String(Number(data.comment_likes_count || 0));
                updateCommentLikeCache(likeCommentId, data.liked, data.comment_likes_count);
            }).catch(function () {}).finally(function () {
                likeButton.disabled = false;
            });
            return;
        }

        var toggle = event.target.closest ? event.target.closest('#profilePostViewer .profile-viewer-comment-menu-toggle') : null;
        if (toggle) {
            event.preventDefault();
            var menu = toggle.closest('.profile-viewer-comment-menu');
            if (menu) {
                var isOpen = menu.classList.contains('is-open');
                closeCommentMenus(menu);
                menu.classList.toggle('is-open', !isOpen);
            }
            return;
        }

        var reportButton = event.target.closest ? event.target.closest('#profilePostViewer .profile-viewer-comment-report') : null;
        if (reportButton) {
            event.preventDefault();
            var reportCommentNode = reportButton.closest('.profile-viewer-comment');
            var reportCommentId = reportCommentNode ? reportCommentNode.getAttribute('data-comment-id') : '';
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
                        fetch(window.location.href, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                'Accept': 'application/json'
                            },
                            body: params.toString()
                        }).then(function (response) { return response.json(); }).then(function (data) {
                            if (!data || !data.ok) return;
                            api.showSuccess();
                            window.setTimeout(function () {
                                if (api.close) api.close();
                            }, 900);
                        }).catch(function () {});
                    }
                });
            }
            return;
        }

        var deleteButton = event.target.closest ? event.target.closest('#profilePostViewer .profile-viewer-comment-delete') : null;
        if (!deleteButton) return;

        event.preventDefault();
        var commentNode = deleteButton.closest('.profile-viewer-comment');
        var commentId = commentNode ? commentNode.getAttribute('data-comment-id') : '';
        var postId = commentNode ? (commentNode.getAttribute('data-post-id') || viewer.dataset.postId || '') : '';
        if (!commentId || !postId) return;

        var params = new URLSearchParams();
        params.set('action', 'delete_comment');
        params.set('comment_id', commentId);

        fetch(window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json'
            },
            body: params.toString()
        }).then(function (response) { return response.json(); }).then(function (data) {
            if (!data || !data.ok) return;
            removeCommentFromCache(postId, commentId);
            renderComments(postId);
            syncCardDataset(postId, data);
            applyViewerState(data);
            if (window.SnapixPostSync && window.SnapixPostSync.syncPostState) {
                window.SnapixPostSync.syncPostState(postId, data, 'delete_comment', true);
            }
        }).catch(function () {});
    });

    function showViewerModerationMessage(message, isError) {
        if (!commentForm || !message) return;
        var messageNode = commentForm.querySelector('.profile-post-viewer-moderation-message');
        if (!messageNode) {
            messageNode = document.createElement('p');
            messageNode.className = 'profile-post-viewer-moderation-message';
            commentForm.insertBefore(messageNode, commentForm.firstChild);
        }
        messageNode.textContent = message;
        messageNode.classList.toggle('is-error', !!isError);
        window.setTimeout(function () {
            if (messageNode && messageNode.parentNode) {
                messageNode.remove();
            }
        }, 4000);
    }

    function sendPostAction(postId, action) {
        return fetch(window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json'
            },
            body: new URLSearchParams({ post_id: postId, action: action }).toString()
        }).then(function (response) { return response.json(); });
    }

    viewer.querySelectorAll('[data-post-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            var postId = button.getAttribute('data-post-id') || viewer.dataset.postId || '';
            var action = button.getAttribute('data-post-action') || '';
            if (!postId) return;
            if (action === 'comment') {
                var input = commentForm ? commentForm.querySelector('[name="comment_text"]') : null;
                if (input) input.focus();
                return;
            }
            if (action === 'share') {
                if (window.snapixOpenShareModal) window.snapixOpenShareModal(postId);
                return;
            }
            if (!actionToEndpoint[action]) return;
            sendPostAction(postId, actionToEndpoint[action]).then(function (data) {
                if (!data || !data.ok) return;
                syncCardDataset(postId, data);
                applyViewerState(data, action);
                if (window.SnapixPostSync && window.SnapixPostSync.syncPostState) {
                    window.SnapixPostSync.syncPostState(postId, data, actionToEndpoint[action], true);
                }
            }).catch(function () {});
        });
    });

    if (commentForm) {
        var commentInput = commentForm.querySelector('[name="comment_text"]');
        var commentSendButton = commentForm.querySelector('.profile-post-viewer-send-btn');

        function setCommentSubmitting(isSubmitting) {
            commentForm.dataset.snapixSubmitting = isSubmitting ? '1' : '0';
            if (commentSendButton) {
                commentSendButton.disabled = isSubmitting;
            }
        }

        function submitViewerComment() {
            if (commentForm.dataset.snapixSubmitting === '1') return;

            var input = commentInput || commentForm.querySelector('[name="comment_text"]');
            var postIdInput = commentForm.querySelector('input[name="post_id"]');
            var parentInput = commentForm.querySelector('input[name="parent_comment_id"]');
            var text = input ? input.value.trim() : '';
            var postId = postIdInput ? postIdInput.value : '';
            var parentCommentId = parentInput ? parentInput.value : '';
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

            setCommentSubmitting(true);
            fetch(window.location.href, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            }).then(function (response) { return response.json(); }).then(function (data) {
                if (!data || !data.ok) return;
                if (data.moderation_status && data.moderation_status !== 'published') {
                    showViewerModerationMessage(data.moderation_message || 'Комментарий отправлен на модерацию', data.moderation_status === 'rejected');
                }
                if (input) input.value = '';
                if (parentInput) parentInput.value = '';
                if (window.snapixClearViewerAttachment) window.snapixClearViewerAttachment();
                if (data.comment) {
                    window.snapixAppendViewerComment(data.comment);
                }
                syncCardDataset(postId, data);
                applyViewerState(data);
                if (window.SnapixPostSync && window.SnapixPostSync.syncPostState) {
                    window.SnapixPostSync.syncPostState(postId, data, 'add_comment', true);
                }
            }).catch(function () {}).finally(function () {
                setCommentSubmitting(false);
            });
        }

        commentForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitViewerComment();
        });

        if (commentSendButton) {
            commentSendButton.addEventListener('click', function (event) {
                event.preventDefault();
                submitViewerComment();
            });
        }

        if (commentInput) {
            commentInput.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
                event.preventDefault();
                submitViewerComment();
            });
        }
    }

    if (follow) {
        follow.addEventListener('click', function () {
            var authorId = follow.dataset.authorId || viewer.dataset.postAuthorId || '';
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
            }).then(function (response) { return response.json(); }).then(function (data) {
                if (!data || !data.ok) {
                    follow.disabled = false;
                    follow.classList.remove('is-following');
                    return;
                }
                document.querySelectorAll('[data-post-author-id="' + authorId + '"]').forEach(function (node) {
                    if (node.dataset) node.dataset.postIsFollowingAuthor = '1';
                });
                window.setTimeout(function () {
                    follow.hidden = true;
                    follow.disabled = false;
                    follow.classList.remove('is-following');
                }, 900);
            }).catch(function () {
                follow.disabled = false;
                follow.classList.remove('is-following');
            });
        });
    }

    (function () {
        var attachmentButton = document.getElementById('profilePostViewerAttachmentButton');
        var attachmentInput = document.getElementById('profilePostViewerAttachmentInput');
        var preview = document.getElementById('profilePostViewerAttachmentPreview');
        var previewImage = preview ? preview.querySelector('img') : null;
        var removeButton = document.getElementById('profilePostViewerAttachmentRemove');
        var inputShell = document.getElementById('profilePostViewerInputShell');
        var allowedTypes = ['image/gif', 'image/jpeg', 'image/png', 'image/webp'];
        var allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'webp'];
        var previewUrl = '';
        var selectedAttachmentFile = null;

        if (!attachmentButton || !attachmentInput || !preview || !previewImage || !removeButton || !inputShell) {
            return;
        }

        function clearAttachment() {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = '';
            }
            selectedAttachmentFile = null;
            attachmentInput.value = '';
            previewImage.removeAttribute('src');
            preview.hidden = true;
            inputShell.classList.remove('has-attachment');
        }

        window.snapixClearViewerAttachment = clearAttachment;
        window.snapixGetViewerAttachment = function () {
            return selectedAttachmentFile ? {file: selectedAttachmentFile} : null;
        };

        attachmentButton.addEventListener('click', function () {
            attachmentInput.click();
        });

        attachmentInput.addEventListener('change', function () {
            var file = attachmentInput.files && attachmentInput.files[0] ? attachmentInput.files[0] : null;
            if (!file) {
                clearAttachment();
                return;
            }

            var extension = (file.name.split('.').pop() || '').toLowerCase();
            if ((file.type && allowedTypes.indexOf(file.type) === -1) || allowedExtensions.indexOf(extension) === -1) {
                clearAttachment();
                return;
            }

            selectedAttachmentFile = file;
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
            previewUrl = URL.createObjectURL(file);
            previewImage.src = previewUrl;
            preview.hidden = false;
            inputShell.classList.add('has-attachment');
        });

        removeButton.addEventListener('click', clearAttachment);
    })();

})();
(function () {
    var tabs = document.querySelectorAll('.home-feed-tab');
    var feedContent = document.querySelector('[data-feed-content]');
    var feedCards = document.querySelectorAll('[data-feed-scope]');
    var emptyStates = document.querySelectorAll('[data-feed-empty]');

    if (!tabs.length || !feedContent) {
        return;
    }

    function updateFeedVisibility(scope) {
        var visibleCount = 0;

        feedCards.forEach(function (card) {
            var isVisible = card.getAttribute('data-feed-scope') === scope;
            card.classList.toggle('is-hidden', !isVisible);

            if (isVisible) {
                visibleCount += 1;
            }
        });

        emptyStates.forEach(function (emptyState) {
            emptyState.classList.toggle('is-hidden', emptyState.getAttribute('data-feed-empty') !== scope || visibleCount > 0);
        });
    }

    function activateTab(tab) {
        if (tab.classList.contains('is-active')) {
            return;
        }

        tabs.forEach(function (item) {
            item.classList.remove('is-active');
            item.setAttribute('aria-selected', 'false');
        });

        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');
        var scope = tab.getAttribute('data-feed-tab') || 'for-you';
        feedContent.setAttribute('data-feed-content', scope);
        updateFeedVisibility(scope);
        feedContent.classList.add('is-switching');
        window.setTimeout(function () {
            feedContent.classList.remove('is-switching');
        }, 180);
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateTab(tab);
        });

        tab.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activateTab(tab);
            }
        });
    });

    updateFeedVisibility(feedContent.getAttribute('data-feed-content') || 'for-you');
})();

document.querySelectorAll('.js-open-comments-modal').forEach(function (button) {
    button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var card = button.closest('[data-post-id]');
        if (card && window.snapixOpenPostViewer) {
            window.snapixOpenPostViewer(card);
            var input = document.querySelector('#profilePostViewerCommentForm [name="comment_text"]');
            if (input) input.focus();
        }
    });
});

document.querySelectorAll('.js-close-comments-modal').forEach(function (button) {
    button.addEventListener('click', function () {
        var modalId = button.getAttribute('data-modal');
        var modal = modalId ? document.getElementById(modalId) : null;
        if (modal) {
            modal.classList.remove('is-open');
        }
    });
});

document.querySelectorAll('.post-menu-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        var menuId = button.getAttribute('data-post-menu');
        var menu = menuId ? document.getElementById(menuId) : null;
        if (menu) {
            menu.classList.toggle('is-open');
        }
    });
});

(function () {
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

    function updateActionState(form, data) {
        var actionInput = form.querySelector('input[name="action"]');
        var action = actionInput ? actionInput.value : '';
        var item = form.closest('.feed-action-item');
        var button = form.querySelector('.feed-action-btn');
        var countNode = item ? item.querySelector('.feed-action-count') : null;

        if (action === 'toggle_like') {
            if (button) {
                button.classList.toggle('is-active', !!data.liked);
                animateActiveIcon(button, !!data.liked);
            }
            setCount(countNode, data.likes_count);
        }

        if (action === 'toggle_save') {
            if (button) {
                button.classList.toggle('is-saved', !!data.saved);
                animateActiveIcon(button, !!data.saved);
            }
            setCount(countNode, data.saves_count);
        }

        if (action === 'add_repost') {
            if (button) {
                button.classList.toggle('is-reposted', !!data.reposted);
                animateActiveIcon(button, !!data.reposted);
            }
            setCount(countNode, data.reposts_count);
        }
    }

    document.querySelectorAll('form.inline-action-form').forEach(function (form) {
        var actionInput = form.querySelector('input[name="action"]');
        var action = actionInput ? actionInput.value : '';

        if (['toggle_like', 'toggle_save', 'add_repost'].indexOf(action) === -1) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            sendPostActionForm(form)
                .then(function (data) {
                    if (!data || !data.ok) {
                        return;
                    }
                    updateActionState(form, data);
                })
                .catch(function () {});
        });
    });

    document.querySelectorAll('form.comments-modal-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            sendPostActionForm(form)
                .then(function (data) {
                    if (!data || !data.ok) {
                        return;
                    }

                    var textarea = form.querySelector('textarea[name="comment_text"]');
                    var modal = form.closest('.comments-modal');
                    var body = modal ? modal.querySelector('.comments-modal-body') : null;

                    if (body && data.comment) {
                        var empty = body.querySelector('.comments-empty');
                        if (empty) {
                            empty.remove();
                        }

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

                    if (textarea) {
                        textarea.value = '';
                    }

                    if (modal && modal.id) {
                        document.querySelectorAll('.js-open-comments-modal[data-modal="' + modal.id + '"]').forEach(function (button) {
                            var countNode = button.closest('.feed-action-item') ? button.closest('.feed-action-item').querySelector('.feed-action-count') : null;
                            setCount(countNode, data.comments_count);
                        });
                    }
                })
                .catch(function () {});
        });
    });
})();

document.addEventListener('click', function (event) {
    document.querySelectorAll('.post-menu').forEach(function (menu) {
        var wrap = menu.closest('.post-menu-wrap');
        if (wrap && !wrap.contains(event.target)) {
            menu.classList.remove('is-open');
        }
    });
});

document.querySelectorAll('.js-copy-post-link').forEach(function (button) {
    button.addEventListener('click', function () {
        var postUrl = button.getAttribute('data-post-url') || '';
        var absoluteUrl = new URL(postUrl, window.location.href).toString();
        var label = button.querySelector('span');
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
    });
});

document.querySelectorAll('.js-post-menu-feedback').forEach(function (button) {
    button.addEventListener('click', function () {
        var label = button.querySelector('span');
        var defaultText = label ? label.textContent : '';

        if (label) {
            label.textContent = button.getAttribute('data-feedback') || 'Готово';
            window.setTimeout(function () {
                label.textContent = defaultText;
            }, 1600);
        }
    });
});

(function () {
    var modal = document.getElementById('share-post-modal');
    if (!modal) {
        return;
    }

    var activePostId = 0;

    function openShareModal(postId) {
        activePostId = Number(postId || 0);
        if (activePostId) {
            modal.classList.add('is-open');
        }
    }

    window.snapixOpenShareModal = openShareModal;

    document.querySelectorAll('.js-open-share-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            openShareModal(button.getAttribute('data-post-id') || 0);
        });
    });

    modal.querySelectorAll('.js-close-share-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            modal.classList.remove('is-open');
        });
    });

    modal.querySelectorAll('.js-share-send-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!activePostId) {
                return;
            }
            var params = new URLSearchParams();
            params.set('post_id', String(activePostId));
            params.set('receiver_id', String(button.getAttribute('data-recipient-id')));

            fetch('share-post.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok) {
                    return;
                }
                if (typeof data.shares_count !== 'undefined') {
                    document.querySelectorAll('[data-post-id="' + activePostId + '"][data-post-count="shares"]').forEach(function (countNode) {
                        countNode.textContent = String(Number(data.shares_count || 0));
                    });
                    document.querySelectorAll('[data-post-id="' + activePostId + '"]').forEach(function (node) {
                        if (node.dataset) {
                            node.dataset.postSharesCount = String(Number(data.shares_count || 0));
                        }
                    });
                }
                button.textContent = 'Отправлено';
                button.disabled = true;
                setTimeout(function () {
                    button.textContent = 'Отправить';
                    button.disabled = false;
                    modal.classList.remove('is-open');
                }, 700);
            })
            .catch(function () {});
        });
    });
})();
</script>
</body>
</html>
