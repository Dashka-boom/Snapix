<?php
session_start();
require './config/config.php';
require './includes/admin-auth.php';
require './includes/notifications.php';

$admin = requireAdminPanel($pdo);
$isAdmin = isAdmin($admin);
$isModerator = isModerator($admin);
$message = '';
$error = '';

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requireAdminCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || empty($_SESSION['admin_csrf_token']) || !hash_equals($_SESSION['admin_csrf_token'], $token)) {
        http_response_code(403);
        exit('Недействительный CSRF-токен.');
    }
}

function ensureAdminStorage(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_user_id BIGINT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            target_type VARCHAR(100) NULL,
            target_id BIGINT UNSIGNED NULL,
            details TEXT NULL,
            ip_address VARCHAR(64) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_logs_created_at (created_at),
            KEY idx_admin_logs_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $columnsStmt = $pdo->query('SHOW COLUMNS FROM admin_logs');
    $columns = [];
    foreach ($columnsStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[$column['Field']] = true;
    }

    $missingColumns = [];
    if (!isset($columns['target_type'])) {
        $missingColumns[] = 'ADD COLUMN target_type VARCHAR(100) NULL AFTER action';
    }
    if (!isset($columns['target_id'])) {
        $missingColumns[] = 'ADD COLUMN target_id BIGINT UNSIGNED NULL AFTER target_type';
    }
    if (!isset($columns['details'])) {
        $missingColumns[] = 'ADD COLUMN details TEXT NULL AFTER target_id';
    }
    if (!isset($columns['ip_address'])) {
        $missingColumns[] = 'ADD COLUMN ip_address VARCHAR(64) NULL AFTER details';
    }

    foreach ($missingColumns as $alterSql) {
        try {
            $pdo->exec('ALTER TABLE admin_logs ' . $alterSql);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1060) {
                throw $exception;
            }
        }
    }
}

function writeAdminLog(PDO $pdo, array $admin, string $action, ?string $targetType = null, ?int $targetId = null, string $details = ''): void
{
    $stmt = $pdo->prepare('
        INSERT INTO admin_logs (admin_user_id, action, target_type, target_id, details, ip_address)
        VALUES (:admin_user_id, :action, :target_type, :target_id, :details, :ip_address)
    ');
    $stmt->execute([
        'admin_user_id' => (int) ($admin['id'] ?? 0),
        'action' => mb_substr($action, 0, 100),
        'target_type' => $targetType !== null ? mb_substr($targetType, 0, 100) : null,
        'target_id' => $targetId,
        'details' => mb_substr($details, 0, 2000),
        'ip_address' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
    ]);
}

function adminBackupDirectory(): string
{
    return __DIR__ . '/backups';
}

function listAdminBackups(): array
{
    $directory = adminBackupDirectory();
    if (!is_dir($directory)) {
        return [];
    }

    $files = glob($directory . '/snapix_backup_*.sql') ?: [];
    usort($files, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));

    return array_map(static function (string $path): array {
        return [
            'name' => basename($path),
            'size' => filesize($path),
            'created_at' => date('d.m.Y H:i:s', filemtime($path)),
        ];
    }, $files);
}

function createDatabaseBackup(PDO $pdo): string
{
    $directory = adminBackupDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
        throw new RuntimeException('Не удалось создать папку backups.');
    }

    $filename = 'snapix_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $path = $directory . '/' . $filename;

    $handle = fopen($path, 'wb');
    if (!$handle) {
        throw new RuntimeException('Не удалось создать файл бэкапа.');
    }

    fwrite($handle, "-- Snapix database backup\n");
    fwrite($handle, "-- Created at: " . date('Y-m-d H:i:s') . "\n\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $safeTable = str_replace('`', '``', (string) $table);

        fwrite($handle, "DROP TABLE IF EXISTS `{$safeTable}`;\n");

        $createStmt = $pdo->query('SHOW CREATE TABLE `' . $safeTable . '`');
        $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
        $createSql = $createRow['Create Table'] ?? array_values($createRow)[1] ?? '';
        fwrite($handle, $createSql . ";\n\n");

        $rowsStmt = $pdo->query('SELECT * FROM `' . $safeTable . '`');
        while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', array_keys($row));
            $values = array_map(static fn($value): string => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row));
            fwrite($handle, 'INSERT INTO `' . $safeTable . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n");
        }

        fwrite($handle, "\n");
    }

    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);

    return $filename;
}

function restoreDatabaseBackup(PDO $pdo, string $tmpPath): void
{
    if (!is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Файл бэкапа не загружен.');
    }

    $sql = file_get_contents($tmpPath);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Файл бэкапа пустой.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '' || str_starts_with($statement, '--')) {
                continue;
            }

            $pdo->exec($statement);
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        throw $exception;
    }
}

ensureAdminStorage($pdo);

if ($isAdmin && isset($_GET['download_backup'])) {
    $backupName = basename((string) $_GET['download_backup']);
    $backupPath = adminBackupDirectory() . '/' . $backupName;

    if (!preg_match('/^snapix_backup_[\w\-]+\.sql$/', $backupName) || !is_file($backupPath)) {
        http_response_code(404);
        exit('Файл бэкапа не найден.');
    }

    writeAdminLog($pdo, $admin, 'export_backup', 'backup', null, $backupName);

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $backupName . '"');
    header('Content-Length: ' . filesize($backupPath));
    readfile($backupPath);
    exit;
}

if ($isAdmin && isset($_GET['export_logs'])) {
    writeAdminLog($pdo, $admin, 'export_logs', 'admin_logs');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="snapix_admin_logs_' . date('Y-m-d_H-i-s') . '.csv"');

    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Администратор', 'Действие', 'Тип объекта', 'ID объекта', 'Детали', 'IP', 'Дата'], ';');

    $logsStmt = $pdo->query("
        SELECT admin_logs.*, users.login AS admin_login
        FROM admin_logs
        LEFT JOIN users ON users.id = admin_logs.admin_user_id
        ORDER BY admin_logs.created_at DESC
        LIMIT 5000
    ");

    foreach ($logsStmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
        fputcsv($output, [
            $log['id'],
            $log['admin_login'] ?? '',
            $log['action'],
            $log['target_type'],
            $log['target_id'],
            $log['details'],
            $log['ip_address'],
            $log['created_at'],
        ], ';');
    }

    fclose($output);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf();

    $action = $_POST['action'] ?? '';


    if ($action === 'update_user_role') {
        if (!$isAdmin) {
            $error = 'Недостаточно прав. Назначать роли может только администратор.';
        } else {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $newRole = (string) ($_POST['role'] ?? '');
            $allowedRoles = ['user', 'moderator', 'admin'];

            if ($userId <= 0 || !in_array($newRole, $allowedRoles, true)) {
                $error = 'Некорректные данные роли.';
            } elseif ($userId === (int) $admin['id'] && $newRole !== 'admin') {
                $error = 'Нельзя снять роль администратора с текущего аккаунта.';
            } else {
                $roleStmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id LIMIT 1');
                $roleStmt->execute([
                    'role' => $newRole,
                    'id' => $userId,
                ]);

                writeAdminLog($pdo, $admin, 'update_user_role', 'user', $userId, 'Новая роль: ' . $newRole);
                $message = 'Роль пользователя обновлена.';
            }
        }
    }

    if ($action === 'create_backup') {
        if (!$isAdmin) {
            $error = 'Недостаточно прав. Создавать бэкапы может только администратор.';
        } else {
            try {
                $backupName = createDatabaseBackup($pdo);
                writeAdminLog($pdo, $admin, 'create_backup', 'backup', null, $backupName);
                $message = 'Бэкап создан: ' . $backupName;
            } catch (Throwable $exception) {
                $error = 'Не удалось создать бэкап.';
            }
        }
    }

    if ($action === 'restore_backup') {
        if (!$isAdmin) {
            $error = 'Недостаточно прав. Восстанавливать БД может только администратор.';
        } else {
            $file = $_FILES['backup_file'] ?? null;
            $originalName = (string) ($file['name'] ?? '');

            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $error = 'Выберите SQL-файл бэкапа.';
            } elseif (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'sql') {
                $error = 'Можно загрузить только файл .sql.';
            } elseif ((int) ($file['size'] ?? 0) > 50 * 1024 * 1024) {
                $error = 'Файл бэкапа слишком большой.';
            } else {
                try {
                    restoreDatabaseBackup($pdo, (string) $file['tmp_name']);
                    writeAdminLog($pdo, $admin, 'restore_backup', 'backup', null, $originalName);
                    $message = 'База данных восстановлена из бэкапа.';
                } catch (Throwable $exception) {
                    $error = 'Не удалось восстановить базу данных из бэкапа.';
                }
            }
        }
    }

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
                    $deleteCommentStmt = $pdo->prepare('DELETE FROM comments WHERE id = :id LIMIT 1');
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
                    writeAdminLog($pdo, $admin, 'delete_comment', 'comment', $commentId, $reasonText);
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
    if (!$isAdmin) {
        $error = 'Недостаточно прав. Удалять пользователей может только администратор.';
    } else {
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
                    writeAdminLog($pdo, $admin, 'delete_user', 'user', $userId);
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
}
if ($action === 'delete_comment') {
    $commentId = (int) ($_POST['comment_id'] ?? 0);

    if ($commentId <= 0) {
        $error = 'Некорректный комментарий.';
    } else {
        $deleteCommentStmt = $pdo->prepare('
            DELETE FROM comments
            WHERE id = :id
            LIMIT 1
        ');
        $deleteCommentStmt->execute(['id' => $commentId]);

        if ($deleteCommentStmt->rowCount() > 0) {
            $message = 'Комментарий удалён.';
        } else {
            $error = 'Комментарий не найден.';
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

$adminLogsStmt = $pdo->query("
    SELECT admin_logs.*, users.login AS admin_login
    FROM admin_logs
    LEFT JOIN users ON users.id = admin_logs.admin_user_id
    ORDER BY admin_logs.created_at DESC
    LIMIT 300
");
$adminLogs = $adminLogsStmt->fetchAll();
$backupFiles = $isAdmin ? listAdminBackups() : [];

$section = $_GET['section'] ?? 'reports';
$allowedSections = ['reports', 'comments', 'posts', 'users', 'roles', 'backups', 'logs'];
if (!in_array($section, $allowedSections, true)) {
    $section = 'reports';
}
if (in_array($section, ['users', 'roles', 'backups', 'logs'], true) && !$isAdmin) {
    $section = 'reports';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="icon" href="icon/light theme/logo.png" type="image/png">
    <title>Snapix</title>
</head>
<body>
    <main class="admin-panel-page">
        <header class="admin-header">
            <div>
                <h1><?php echo $isAdmin ? 'Админ-панель' : 'Панель модератора'; ?></h1>
                <p>Вы вошли как: <strong><?php echo htmlspecialchars($admin['login']); ?></strong></p>
            </div>
            <div class="admin-nav">
                <a href="index.php">На сайт</a>
                <a href="logout.php">Выйти</a>
            </div>
        </header>
<nav class="admin-tabs" aria-label="Разделы панели">
    <a href="admin-panel.php?section=reports" class="admin-tab <?php echo $section === 'reports' ? 'is-active' : ''; ?>">Жалобы</a>
    <a href="admin-panel.php?section=comments" class="admin-tab <?php echo $section === 'comments' ? 'is-active' : ''; ?>">Комментарии</a>
    <a href="admin-panel.php?section=posts" class="admin-tab <?php echo $section === 'posts' ? 'is-active' : ''; ?>">Публикации</a>

    <?php if ($isAdmin): ?>
        <a href="admin-panel.php?section=users" class="admin-tab <?php echo $section === 'users' ? 'is-active' : ''; ?>">Пользователи</a>
        <a href="admin-panel.php?section=roles" class="admin-tab <?php echo $section === 'roles' ? 'is-active' : ''; ?>">Роли</a>
        <a href="admin-panel.php?section=backups" class="admin-tab <?php echo $section === 'backups' ? 'is-active' : ''; ?>">Бэкапы</a>
        <a href="admin-panel.php?section=logs" class="admin-tab <?php echo $section === 'logs' ? 'is-active' : ''; ?>">Логи</a>
    <?php endif; ?>
</nav>
        <?php if ($message): ?><p class="status-message status-success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
        <?php if ($error): ?><p class="status-message status-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <?php if ($section === 'reports'): ?>
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
                                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf_token']); ?>">
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
<?php endif; ?>

<?php if ($section === 'comments'): ?>
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
                                <td class="admin-comments-text"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></td>
                                <td><?php echo htmlspecialchars((string) $comment['created_at']); ?></td>
                                <td><?php echo (int) $comment['is_deleted'] === 1 ? 'Удалён' : 'Активен'; ?></td>
                                <td>
    <form method="post" class="inline-form" style="display:grid; gap:6px;" onsubmit="return confirm('Удалить этот комментарий?');">
        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf_token']); ?>">
        <input type="hidden" name="action" value="delete_comment">
        <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
        <input type="text" name="moderation_reason" placeholder="Причина удаления" maxlength="900" value="Нарушение правил сообщества">
        <button type="submit" class="danger-btn">Удалить</button>
    </form>
</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
<?php endif; ?>

<?php if ($section === 'users' && $isAdmin): ?>
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
                                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf_token']); ?>">
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
<?php endif; ?>


<?php if ($section === 'roles' && $isAdmin): ?>
<section class="admin-section">
    <h2>Назначение ролей</h2>
    <p class="muted">Администратор может назначать роли user, moderator и admin. Модератор не имеет доступа к этому разделу.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Логин</th><th>Email</th><th>Текущая роль</th><th>Новая роль</th><th>Действие</th></tr></thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo (int) $user['id']; ?></td>
                        <td><?php echo e($user['login']); ?></td>
                        <td><?php echo e($user['email']); ?></td>
                        <td><?php echo e((string) $user['role']); ?></td>
                        <td>
                            <form method="post" class="inline-form admin-role-form">
                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf_token']); ?>">
                                <input type="hidden" name="action" value="update_user_role">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                <select name="role" <?php echo (int) $user['id'] === (int) $admin['id'] ? 'disabled' : ''; ?>>
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>user</option>
                                    <option value="moderator" <?php echo $user['role'] === 'moderator' ? 'selected' : ''; ?>>moderator</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>admin</option>
                                </select>
                        </td>
                        <td>
                                <?php if ((int) $user['id'] !== (int) $admin['id']): ?>
                                    <button type="submit" class="admin-small-btn">Сохранить</button>
                                <?php else: ?>
                                    <span class="muted">Текущий админ</span>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($section === 'backups' && $isAdmin): ?>
<section class="admin-section">
    <h2>Бэкапы базы данных</h2>
    <p class="muted">Создание, скачивание и восстановление SQL-бэкапа. Перед восстановлением лучше создать новый бэкап текущей базы.</p>

    <div class="admin-tools-grid">
        <form method="post" class="admin-tool-card">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf_token']); ?>">
            <input type="hidden" name="action" value="create_backup">
            <h3>Создать бэкап</h3>
            <p>Сохраняет структуру и данные таблиц в папку <code>backups</code>.</p>
            <button type="submit" class="admin-small-btn">Создать бэкап</button>
        </form>

        <form method="post" enctype="multipart/form-data" class="admin-tool-card" onsubmit="return confirm('Восстановление заменит данные в БД. Продолжить?');">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf_token']); ?>">
            <input type="hidden" name="action" value="restore_backup">
            <h3>Восстановить из бэкапа</h3>
            <p>Загрузите файл .sql, созданный в этой панели.</p>
            <input type="file" name="backup_file" accept=".sql" required>
            <button type="submit" class="danger-btn">Восстановить БД</button>
        </form>
    </div>

    <h3>Файлы бэкапов</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Файл</th><th>Размер</th><th>Дата</th><th>Экспорт</th></tr></thead>
            <tbody>
                <?php if ($backupFiles): ?>
                    <?php foreach ($backupFiles as $backup): ?>
                        <tr>
                            <td><?php echo e($backup['name']); ?></td>
                            <td><?php echo number_format((int) $backup['size'] / 1024, 1, '.', ' '); ?> КБ</td>
                            <td><?php echo e($backup['created_at']); ?></td>
                            <td><a class="admin-small-btn" href="admin-panel.php?download_backup=<?php echo urlencode($backup['name']); ?>">Скачать</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="muted">Бэкапов пока нет.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($section === 'logs' && $isAdmin): ?>
<section class="admin-section">
    <h2>Логи админ-панели</h2>
    <p class="muted">Здесь фиксируются действия администратора: смена ролей, удаление, создание и экспорт бэкапов.</p>
    <p><a class="admin-small-btn" href="admin-panel.php?export_logs=1">Экспортировать логи CSV</a></p>

    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Админ</th><th>Действие</th><th>Объект</th><th>Детали</th><th>IP</th><th>Дата</th></tr></thead>
            <tbody>
                <?php foreach ($adminLogs as $log): ?>
                    <tr>
                        <td><?php echo (int) $log['id']; ?></td>
                        <td><?php echo e((string) ($log['admin_login'] ?? '')); ?></td>
                        <td><?php echo e((string) $log['action']); ?></td>
                        <td><?php echo e((string) ($log['target_type'] ?? '')); ?> <?php echo !empty($log['target_id']) ? '#' . (int) $log['target_id'] : ''; ?></td>
                        <td><?php echo e((string) ($log['details'] ?? '')); ?></td>
                        <td><?php echo e((string) ($log['ip_address'] ?? '')); ?></td>
                        <td><?php echo e((string) ($log['created_at'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($section === 'posts'): ?>
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
                                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf_token']); ?>">
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
        <?php endif; ?>
    </main>
</body>
</html>