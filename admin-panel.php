<?php
session_start();
require './config/config.php';
require './includes/admin-auth.php';
require './includes/notifications.php';

$admin = requireAdmin($pdo);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_comment') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        $moderationReason = trim($_POST['moderation_reason'] ?? 'Нарушение правил сообщества');
        $reportId = (int) ($_POST['report_id'] ?? 0);

        if ($commentId <= 0) {
            $error = 'Некорректный комментарий.';
        } else {
            $pdo->beginTransaction();
            try {
                $commentOwnerStmt = $pdo->prepare('SELECT id, post_id, user_id, comment_text FROM comments WHERE id = :id AND is_deleted = 0 LIMIT 1');
                $commentOwnerStmt->execute(['id' => $commentId]);
                $comment = $commentOwnerStmt->fetch();

                if (!$comment) {
                    $pdo->rollBack();
                    $error = 'Комментарий уже удалён или не найден.';
                } else {
                    $deleteCommentStmt = $pdo->prepare('UPDATE comments SET is_deleted = 1 WHERE id = :id LIMIT 1');
                    $deleteCommentStmt->execute(['id' => $commentId]);

                    if ($reportId > 0) {
                        $markReportStmt = $pdo->prepare("UPDATE moderation_reports SET status = 'resolved' WHERE id = :id");
                        $markReportStmt->execute(['id' => $reportId]);
                    }

                    $reasonText = mb_substr($moderationReason, 0, 900);
                    $commentText = (string) ($comment['comment_text'] ?? '');
                    $notificationText = 'Ваш комментарий';
                    if ($commentText !== '') {
                        $notificationText .= ' "' . snapix_notification_excerpt($commentText, 120) . '"';
                    }
                    $notificationText .= ' под публикацией #' . (int) ($comment['post_id'] ?? 0) . ' был удалён. Причина: ' . $reasonText . ".\n"
                        . 'Предупреждение: при следующем нарушении аккаунт будет удалён.';

                    snapix_create_notification($pdo, [
                        'target_user_id' => (int) $comment['user_id'],
                        'actor_user_id' => (int) $admin['id'],
                        'notification_type' => 'comment_deleted',
                        'post_id' => (int) ($comment['post_id'] ?? 0),
                        'comment_id' => (int) $comment['id'],
                        'report_id' => $reportId > 0 ? $reportId : null,
                        'title' => 'Комментарий удалён',
                        'message' => $notificationText,
                        'comment_text' => $commentText,
                        'report_reason' => $reasonText,
                    ]);

                    $pdo->commit();
                    $message = 'Комментарий удалён, уведомление отправлено пользователю.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Не удалось удалить комментарий.';
            }
        }
    }

    if ($action === 'delete_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            $error = 'Некорректный пользователь.';
        } elseif ($userId === (int) $admin['id']) {
            $error = 'Нельзя удалить текущего администратора.';
        } else {
            $pdo->beginTransaction();
            try {
                $postIdsStmt = $pdo->prepare('SELECT id FROM posts WHERE user_id = :user_id');
                $postIdsStmt->execute(['user_id' => $userId]);
                $postIds = array_map('intval', $postIdsStmt->fetchAll(PDO::FETCH_COLUMN));

                if ($postIds) {
                    $placeholders = implode(',', array_fill(0, count($postIds), '?'));

                    $deletePostMediaStmt = $pdo->prepare("DELETE FROM post_media WHERE post_id IN ($placeholders)");
                    $deletePostMediaStmt->execute($postIds);

                    $deleteLikesByPostsStmt = $pdo->prepare("DELETE FROM likes WHERE post_id IN ($placeholders)");
                    $deleteLikesByPostsStmt->execute($postIds);

                    $deleteSavedByPostsStmt = $pdo->prepare("DELETE FROM saved_posts WHERE post_id IN ($placeholders)");
                    $deleteSavedByPostsStmt->execute($postIds);

                    $deleteCommentsByPostsStmt = $pdo->prepare("DELETE FROM comments WHERE post_id IN ($placeholders)");
                    $deleteCommentsByPostsStmt->execute($postIds);
                }

                $deleteUserPostsStmt = $pdo->prepare('DELETE FROM posts WHERE user_id = :user_id');
                $deleteUserPostsStmt->execute(['user_id' => $userId]);

                $deleteUserLikesStmt = $pdo->prepare('DELETE FROM likes WHERE user_id = :user_id');
                $deleteUserLikesStmt->execute(['user_id' => $userId]);

                $deleteUserSavedStmt = $pdo->prepare('DELETE FROM saved_posts WHERE user_id = :user_id');
                $deleteUserSavedStmt->execute(['user_id' => $userId]);

                $deleteUserCommentsStmt = $pdo->prepare('DELETE FROM comments WHERE user_id = :user_id');
                $deleteUserCommentsStmt->execute(['user_id' => $userId]);

                $deleteFollowersStmt = $pdo->prepare('DELETE FROM followers WHERE follower_id = :user_id OR following_id = :user_id');
                $deleteFollowersStmt->execute(['user_id' => $userId]);

                $deleteFollowRequestsStmt = $pdo->prepare('DELETE FROM follow_requests WHERE sender_id = :user_id OR receiver_id = :user_id');
                $deleteFollowRequestsStmt->execute(['user_id' => $userId]);

                $deleteSessionsStmt = $pdo->prepare('DELETE FROM sessions WHERE user_id = :user_id');
                $deleteSessionsStmt->execute(['user_id' => $userId]);

                $deleteResetTokensStmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id');
                $deleteResetTokensStmt->execute(['user_id' => $userId]);

                $deleteReportsStmt = $pdo->prepare('DELETE FROM moderation_reports WHERE reporter_user_id = :user_id OR target_user_id = :user_id');
                $deleteReportsStmt->execute(['user_id' => $userId]);

                $deleteNotificationsStmt = $pdo->prepare('DELETE FROM user_notifications WHERE user_id = :user_id');
                $deleteNotificationsStmt->execute(['user_id' => $userId]);

                $deleteUserStmt = $pdo->prepare('DELETE FROM users WHERE id = :id LIMIT 1');
                $deleteUserStmt->execute(['id' => $userId]);

                if ($deleteUserStmt->rowCount() > 0) {
                    $pdo->commit();
                    $message = 'Пользователь удалён.';
                } else {
                    $pdo->rollBack();
                    $error = 'Пользователь не найден.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Не удалось удалить пользователя.';
            }
        }
    }

    if ($action === 'delete_post') {
        $postId = (int) ($_POST['post_id'] ?? 0);

        if ($postId <= 0) {
            $error = 'Некорректная публикация.';
        } else {
            $pdo->beginTransaction();
            try {
                $deletePostMediaStmt = $pdo->prepare('DELETE FROM post_media WHERE post_id = :post_id');
                $deletePostMediaStmt->execute(['post_id' => $postId]);

                $deleteLikesStmt = $pdo->prepare('DELETE FROM likes WHERE post_id = :post_id');
                $deleteLikesStmt->execute(['post_id' => $postId]);

                $deleteSavedStmt = $pdo->prepare('DELETE FROM saved_posts WHERE post_id = :post_id');
                $deleteSavedStmt->execute(['post_id' => $postId]);

                $deleteCommentsStmt = $pdo->prepare('DELETE FROM comments WHERE post_id = :post_id');
                $deleteCommentsStmt->execute(['post_id' => $postId]);

                $deletePostStmt = $pdo->prepare('DELETE FROM posts WHERE id = :id LIMIT 1');
                $deletePostStmt->execute(['id' => $postId]);

                if ($deletePostStmt->rowCount() > 0) {
                    $pdo->commit();
                    $message = 'Публикация удалена.';
                } else {
                    $pdo->rollBack();
                    $error = 'Публикация не найдена.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Не удалось удалить публикацию.';
            }
        }
    }
}

$usersStmt = $pdo->query('SELECT id, login, email, role, created_at FROM users ORDER BY created_at DESC');
$users = $usersStmt->fetchAll();

$postsStmt = $pdo->query(' 
    SELECT posts.id, posts.caption, posts.created_at, users.id AS user_id, users.login
    FROM posts
    INNER JOIN users ON users.id = posts.user_id
    ORDER BY posts.created_at DESC
');
$posts = $postsStmt->fetchAll();

$commentsStmt = $pdo->query(" 
    SELECT comments.id, comments.comment_text, comments.created_at, comments.is_deleted, users.login
    FROM comments
    INNER JOIN users ON users.id = comments.user_id
    ORDER BY comments.created_at DESC
    LIMIT 200
");
$comments = $commentsStmt->fetchAll();

$reportsStmt = $pdo->query(" 
    SELECT
        moderation_reports.id,
        moderation_reports.target_comment_id,
        moderation_reports.reason_text,
        moderation_reports.status,
        moderation_reports.created_at,
        reporter.login AS reporter_login,
        target.login AS target_login,
        comments.comment_text AS target_comment_text
    FROM moderation_reports
    INNER JOIN users AS reporter ON reporter.id = moderation_reports.reporter_user_id
    LEFT JOIN users AS target ON target.id = moderation_reports.target_user_id
    LEFT JOIN comments ON comments.id = moderation_reports.target_comment_id
    ORDER BY moderation_reports.created_at DESC
    LIMIT 200
");
$reports = $reportsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/admin.css">
    <title>Snapix</title>
</head>
<body>
    <main class="admin-panel-page">
        <header class="admin-header">
            <div>
                <h1>Админ-панель Snapix</h1>
                <p>Вы вошли как: <strong><?php echo htmlspecialchars($admin['login']); ?></strong></p>
            </div>
            <div class="admin-nav">
                <a href="index.php">На сайт</a>
                <a href="logout.php">Выйти</a>
            </div>
        </header>

        <?php if ($message): ?><p class="status-message status-success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
        <?php if ($error): ?><p class="status-message status-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <section class="admin-section">
            <h2>Жалобы</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Кто пожаловался</th>
                            <th>На кого / что</th>
                            <th>Причина</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo (int) $report['id']; ?></td>
                                <td><?php echo htmlspecialchars($report['reporter_login']); ?></td>
                                <td>
                                    <?php if (!empty($report['target_comment_text'])): ?>
                                        Комментарий пользователя <?php echo htmlspecialchars((string) ($report['target_login'] ?? 'неизвестно')); ?>:
                                        <br>
                                        <em><?php echo htmlspecialchars(mb_substr((string) $report['target_comment_text'], 0, 140)); ?></em>
                                    <?php else: ?>
                                        Пользователь: <?php echo htmlspecialchars((string) ($report['target_login'] ?? 'неизвестно')); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($report['reason_text']); ?></td>
                                <td><?php echo htmlspecialchars((string) $report['created_at']); ?></td>
                                <td>
                                    <?php if (!empty($report['target_comment_text'])): ?>
                                        <form method="post" class="inline-form" style="display:grid; gap:6px;">
                                            <input type="hidden" name="action" value="delete_comment">
                                            <input type="hidden" name="report_id" value="<?php echo (int) $report['id']; ?>">
                                            <input type="hidden" name="comment_id" value="<?php echo (int) $report['target_comment_id']; ?>">
                                            <input type="text" name="moderation_reason" value="<?php echo htmlspecialchars($report['reason_text']); ?>" maxlength="900">
                                            <button type="submit" class="danger-btn">Удалить комментарий</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">Только просмотр</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-section">
            <h2>Комментарии</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Автор</th>
                            <th>Текст</th>
                            <th>Дата</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comments as $comment): ?>
                            <tr>
                                <td><?php echo (int) $comment['id']; ?></td>
                                <td><?php echo htmlspecialchars($comment['login']); ?></td>
                                <td><?php echo htmlspecialchars((string) $comment['comment_text']); ?></td>
                                <td><?php echo htmlspecialchars((string) $comment['created_at']); ?></td>
                                <td><?php echo (int) $comment['is_deleted'] === 1 ? 'Удалён' : 'Активен'; ?></td>
                                <td>
                                    <?php if ((int) $comment['is_deleted'] === 0): ?>
                                        <form method="post" class="inline-form" style="display:grid; gap:6px;">
                                            <input type="hidden" name="action" value="delete_comment">
                                            <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                                            <input type="text" name="moderation_reason" placeholder="Причина удаления" maxlength="900" required>
                                            <button type="submit" class="danger-btn">Удалить</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">Уже удалён</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-section">
            <h2>Пользователи</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Логин</th><th>Email</th><th>Роль</th><th>Дата создания</th><th>Действия</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo (int) $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['login']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars((string) $user['role']); ?></td>
                                <td><?php echo htmlspecialchars((string) $user['created_at']); ?></td>
                                <td>
                                    <?php if ((int) $user['id'] !== (int) $admin['id']): ?>
                                        <form method="post" class="inline-form" onsubmit="return confirm('Удалить пользователя?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                            <button type="submit" class="danger-btn">Удалить</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">Текущий админ</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-section">
            <h2>Публикации</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Автор</th><th>Описание</th><th>Дата</th><th>Действия</th></tr></thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td><?php echo (int) $post['id']; ?></td>
                                <td><a href="user.php?id=<?php echo (int) $post['user_id']; ?>"><?php echo htmlspecialchars($post['login']); ?></a></td>
                                <td><?php echo htmlspecialchars((string) ($post['caption'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) $post['created_at']); ?></td>
                                <td>
                                    <form method="post" class="inline-form" onsubmit="return confirm('Удалить публикацию?');">
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                        <button type="submit" class="danger-btn">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
