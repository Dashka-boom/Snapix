<?php

function snapix_is_ajax_request(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function snapix_post_action_counts(PDO $pdo, int $postId, int $userId): array
{
    $countsStmt = $pdo->prepare('
        SELECT
            (SELECT COUNT(*) FROM likes WHERE post_id = :likes_post_id) AS likes_count,
            (SELECT COUNT(*) FROM comments WHERE post_id = :comments_post_id AND is_deleted = 0) AS comments_count,
            (SELECT COUNT(*) FROM saved_posts WHERE post_id = :saves_post_id) AS saves_count,
            (SELECT COUNT(*) FROM reposts WHERE post_id = :reposts_post_id) AS reposts_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = :liked_post_id AND user_id = :liked_user_id) AS is_liked,
            (SELECT COUNT(*) FROM saved_posts WHERE post_id = :saved_post_id AND user_id = :saved_user_id) AS is_saved,
            (SELECT COUNT(*) FROM reposts WHERE post_id = :reposted_post_id AND user_id = :reposted_user_id) AS is_reposted
    ');
    $countsStmt->execute([
        'likes_post_id' => $postId,
        'comments_post_id' => $postId,
        'saves_post_id' => $postId,
        'reposts_post_id' => $postId,
        'liked_post_id' => $postId,
        'liked_user_id' => $userId,
        'saved_post_id' => $postId,
        'saved_user_id' => $userId,
        'reposted_post_id' => $postId,
        'reposted_user_id' => $userId,
    ]);
    $counts = $countsStmt->fetch() ?: [];

    return [
        'likes_count' => (int) ($counts['likes_count'] ?? 0),
        'comments_count' => (int) ($counts['comments_count'] ?? 0),
        'saves_count' => (int) ($counts['saves_count'] ?? 0),
        'reposts_count' => (int) ($counts['reposts_count'] ?? 0),
        'liked' => (int) ($counts['is_liked'] ?? 0) > 0,
        'saved' => (int) ($counts['is_saved'] ?? 0) > 0,
        'reposted' => (int) ($counts['is_reposted'] ?? 0) > 0,
    ];
}

function snapix_send_post_action_json(PDO $pdo, int $postId, int $userId, array $extra = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], snapix_post_action_counts($pdo, $postId, $userId), $extra));
    exit;
}

function snapix_send_post_action_error(string $error, int $statusCode = 422): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}
