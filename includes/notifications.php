<?php

function snapix_notification_excerpt(string $text, int $limit = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, max(0, $limit - 1))) . '…';
}

function snapix_actor_login(PDO $pdo, int $actorUserId): string
{
    $stmt = $pdo->prepare('SELECT login FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $actorUserId]);
    return (string) ($stmt->fetchColumn() ?: 'user');
}

function snapix_notify_admins(PDO $pdo, array $payload, ?int $excludeUserId = null): void
{
    $adminsStmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('admin', 'moderator') AND (:exclude_user_id IS NULL OR id <> :exclude_user_id)");
    $adminsStmt->execute(['exclude_user_id' => $excludeUserId]);

    foreach ($adminsStmt->fetchAll() as $adminRow) {
        $payload['target_user_id'] = (int) $adminRow['id'];
        snapix_create_notification($pdo, $payload);
    }
}

function snapix_create_notification(PDO $pdo, array $data): void
{
    $targetUserId = (int) ($data['target_user_id'] ?? 0);
    $actorUserId = isset($data['actor_user_id']) ? (int) $data['actor_user_id'] : null;
    $postId = isset($data['post_id']) ? (int) $data['post_id'] : null;
    $commentId = isset($data['comment_id']) ? (int) $data['comment_id'] : null;
    $reportId = isset($data['report_id']) ? (int) $data['report_id'] : null;
    $type = (string) ($data['notification_type'] ?? '');

    if ($targetUserId <= 0 || $type === '') {
        return;
    }

    if ($actorUserId !== null && $actorUserId > 0 && $actorUserId === $targetUserId) {
        return;
    }

    $message = trim((string) ($data['message'] ?? ''));
    $title = trim((string) ($data['title'] ?? 'Уведомление'));
    if ($message === '') {
        return;
    }

    if (!empty($data['dedupe_minutes'])) {
        $dedupeStmt = $pdo->prepare('
            SELECT id
            FROM user_notifications
            WHERE user_id = :user_id
              AND notification_type = :notification_type
              AND (actor_user_id <=> :actor_user_id)
              AND (post_id <=> :post_id)
              AND (comment_id <=> :comment_id)
              AND (report_id <=> :report_id)
              AND created_at >= DATE_SUB(NOW(), INTERVAL ' . (int) $data['dedupe_minutes'] . ' MINUTE)
            LIMIT 1
        ');
        $dedupeStmt->execute([
            'user_id' => $targetUserId,
            'notification_type' => $type,
            'actor_user_id' => $actorUserId ?: null,
            'post_id' => $postId ?: null,
            'comment_id' => $commentId ?: null,
            'report_id' => $reportId ?: null,
        ]);

        if ($dedupeStmt->fetchColumn()) {
            return;
        }
    }

    $insertStmt = $pdo->prepare('
        INSERT INTO user_notifications (
            user_id, actor_user_id, target_user_id, notification_type, post_id, comment_id, report_id,
            title, message, comment_text, report_reason
        ) VALUES (
            :user_id, :actor_user_id, :target_user_id, :notification_type, :post_id, :comment_id, :report_id,
            :title, :message, :comment_text, :report_reason
        )
    ');
    $insertStmt->execute([
        'user_id' => $targetUserId,
        'actor_user_id' => $actorUserId ?: null,
        'target_user_id' => $targetUserId,
        'notification_type' => mb_substr($type, 0, 50),
        'post_id' => $postId ?: null,
        'comment_id' => $commentId ?: null,
        'report_id' => $reportId ?: null,
        'title' => mb_substr($title, 0, 255),
        'message' => $message,
        'comment_text' => isset($data['comment_text']) ? mb_substr((string) $data['comment_text'], 0, 1000) : null,
        'report_reason' => isset($data['report_reason']) ? mb_substr((string) $data['report_reason'], 0, 1000) : null,
    ]);
}

function snapix_notify_post_action(PDO $pdo, int $postId, int $actorUserId, string $type, ?string $commentText = null, ?int $commentId = null): void
{
    $postStmt = $pdo->prepare('SELECT id, user_id FROM posts WHERE id = :id AND is_deleted = 0 LIMIT 1');
    $postStmt->execute(['id' => $postId]);
    $post = $postStmt->fetch();
    if (!$post) {
        return;
    }

    $ownerId = (int) $post['user_id'];
    if ($ownerId === $actorUserId) {
        return;
    }

    $login = snapix_actor_login($pdo, $actorUserId);
    $excerpt = $commentText !== null ? snapix_notification_excerpt($commentText, 120) : '';
    $messages = [
        'post_like' => '@' . $login . ' поставил(а) лайк вашей публикации',
        'post_comment' => '@' . $login . ' прокомментировал(а) вашу публикацию' . ($excerpt !== '' ? ': "' . $excerpt . '"' : ''),
        'post_repost' => '@' . $login . ' сделал(а) репост вашей публикации',
        'post_saved' => '@' . $login . ' добавил(а) вашу публикацию в избранное',
        'post_forward' => '@' . $login . ' отправил(а) вашу публикацию в сообщения',
    ];

    if (!isset($messages[$type])) {
        return;
    }

    snapix_create_notification($pdo, [
        'target_user_id' => $ownerId,
        'actor_user_id' => $actorUserId,
        'notification_type' => $type,
        'post_id' => $postId,
        'comment_id' => $commentId,
        'title' => 'Публикация',
        'message' => $messages[$type],
        'comment_text' => $commentText,
        'dedupe_minutes' => $type === 'post_comment' ? 0 : 10,
    ]);
}

function snapix_notify_comment_reply(PDO $pdo, int $parentCommentId, int $actorUserId, string $commentText, int $replyCommentId): void
{
    if ($parentCommentId <= 0 || $replyCommentId <= 0) {
        return;
    }

    $parentStmt = $pdo->prepare("\n        SELECT comments.id, comments.user_id, comments.post_id, comments.comment_text\n        FROM comments\n        INNER JOIN posts ON posts.id = comments.post_id\n        WHERE comments.id = :id\n          AND comments.is_deleted = 0\n          AND (comments.status = 'published' OR comments.status IS NULL)\n          AND posts.is_deleted = 0\n        LIMIT 1\n    ");
    $parentStmt->execute(['id' => $parentCommentId]);
    $parentComment = $parentStmt->fetch();
    if (!$parentComment) {
        return;
    }

    $targetUserId = (int) ($parentComment['user_id'] ?? 0);
    if ($targetUserId <= 0 || $targetUserId === $actorUserId) {
        return;
    }

    $login = snapix_actor_login($pdo, $actorUserId);
    $excerpt = snapix_notification_excerpt($commentText, 120);
    snapix_create_notification($pdo, [
        'target_user_id' => $targetUserId,
        'actor_user_id' => $actorUserId,
        'notification_type' => 'comment_reply',
        'post_id' => (int) ($parentComment['post_id'] ?? 0),
        'comment_id' => $replyCommentId,
        'title' => 'Комментарий',
        'message' => '@' . $login . ' ответил(а) на ваш комментарий' . ($excerpt !== '' ? ': "' . $excerpt . '"' : ''),
        'comment_text' => $commentText,
        'dedupe_minutes' => 0,
    ]);
}

function snapix_notify_comment_like(PDO $pdo, int $commentId, int $actorUserId): void
{
    if ($commentId <= 0) {
        return;
    }

    $commentStmt = $pdo->prepare("\n        SELECT comments.id, comments.user_id, comments.post_id, comments.comment_text\n        FROM comments\n        INNER JOIN posts ON posts.id = comments.post_id\n        WHERE comments.id = :id\n          AND comments.is_deleted = 0\n          AND (comments.status = 'published' OR comments.status IS NULL)\n          AND posts.is_deleted = 0\n        LIMIT 1\n    ");
    $commentStmt->execute(['id' => $commentId]);
    $comment = $commentStmt->fetch();
    if (!$comment) {
        return;
    }

    $targetUserId = (int) ($comment['user_id'] ?? 0);
    if ($targetUserId <= 0 || $targetUserId === $actorUserId) {
        return;
    }

    $login = snapix_actor_login($pdo, $actorUserId);
    snapix_create_notification($pdo, [
        'target_user_id' => $targetUserId,
        'actor_user_id' => $actorUserId,
        'notification_type' => 'comment_like',
        'post_id' => (int) ($comment['post_id'] ?? 0),
        'comment_id' => $commentId,
        'title' => 'Комментарий',
        'message' => '@' . $login . ' поставил(а) лайк вашему комментарию',
        'comment_text' => (string) ($comment['comment_text'] ?? ''),
        'dedupe_minutes' => 10,
    ]);
}
