<?php
$host = 'localhost';
$dbName = 'Snapix';
$dbUser = 'root';
$dbPassword = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPassword
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    try {
        $pdo->exec("ALTER TABLE followers ADD COLUMN status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'accepted' AFTER following_id");
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1060) {
            throw $e;
        }
    }

    try {
        $pdo->exec("ALTER TABLE followers ADD COLUMN declined_until DATETIME DEFAULT NULL AFTER status");
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1060) {
            throw $e;
        }
    }

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER password");
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1060) {
            throw $e;
        }
    }

    try {
        $pdo->exec("ALTER TABLE comments ADD COLUMN status ENUM('published','pending_review','rejected') NOT NULL DEFAULT 'published' AFTER is_deleted");
    } catch (PDOException $e) {
        if (!in_array(($e->errorInfo[1] ?? null), [1060, 1146], true)) {
            throw $e;
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS moderation_reasons (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(100) NOT NULL,
            label VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_moderation_reasons_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS moderation_reports (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reporter_user_id BIGINT UNSIGNED NOT NULL,
            target_user_id BIGINT UNSIGNED DEFAULT NULL,
            target_comment_id BIGINT UNSIGNED DEFAULT NULL,
            reason_id BIGINT UNSIGNED DEFAULT NULL,
            reason_text VARCHAR(1000) NOT NULL,
            status ENUM('open','reviewed','resolved') NOT NULL DEFAULT 'open',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_reports_reporter (reporter_user_id),
            KEY idx_reports_target_user (target_user_id),
            KEY idx_reports_target_comment (target_comment_id),
            KEY idx_reports_reason (reason_id),
            KEY idx_reports_status_created (status, created_at),
            CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_reports_target_user FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_reports_target_comment FOREIGN KEY (target_comment_id) REFERENCES comments(id) ON DELETE SET NULL,
            CONSTRAINT fk_reports_reason FOREIGN KEY (reason_id) REFERENCES moderation_reasons(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            report_id BIGINT UNSIGNED DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notifications_user_read (user_id, is_read, created_at),
            KEY idx_notifications_report (report_id),
            CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_notifications_report FOREIGN KEY (report_id) REFERENCES moderation_reports(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_blocks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            blocker_user_id BIGINT UNSIGNED NOT NULL,
            blocked_user_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_blocks_pair (blocker_user_id, blocked_user_id),
            KEY idx_user_blocks_blocker (blocker_user_id),
            KEY idx_user_blocks_blocked (blocked_user_id),
            CONSTRAINT fk_user_blocks_blocker FOREIGN KEY (blocker_user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_blocks_blocked FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $pdo->exec("
        INSERT INTO moderation_reasons (code, label) VALUES
            ('spam', 'Спам'),
            ('abuse', 'Оскорбления'),
            ('hate', 'Разжигание ненависти'),
            ('fraud', 'Мошенничество'),
            ('other', 'Другое')
        ON DUPLICATE KEY UPDATE label = VALUES(label)
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chats (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_one_id BIGINT UNSIGNED NOT NULL,
            user_two_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_chats_user_pair (user_one_id, user_two_id),
            KEY idx_chats_user_two (user_two_id),
            CONSTRAINT fk_chats_user_one FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_chats_user_two FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            chat_id BIGINT UNSIGNED NOT NULL,
            sender_id BIGINT UNSIGNED NOT NULL,
            message_text TEXT NULL,
            post_id BIGINT UNSIGNED DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_messages_chat_created (chat_id, created_at),
            KEY idx_messages_sender_created (sender_id, created_at),
            KEY idx_messages_chat_read (chat_id, is_read),
            KEY idx_messages_post (post_id),
            CONSTRAINT fk_messages_chat FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
            CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_messages_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    try {
        $pdo->exec("ALTER TABLE messages ADD COLUMN post_id BIGINT UNSIGNED DEFAULT NULL AFTER message_text");
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1060) {
            throw $e;
        }
    }

    try {
        $pdo->exec("ALTER TABLE messages MODIFY COLUMN message_text TEXT NULL");
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1832) {
            throw $e;
        }
    }

    try {
        $pdo->exec("ALTER TABLE messages ADD KEY idx_messages_post (post_id)");
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1061) {
            throw $e;
        }
    }

    try {
        $pdo->exec("ALTER TABLE messages ADD CONSTRAINT fk_messages_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1826 && ($e->errorInfo[1] ?? null) !== 1005) {
            throw $e;
        }
    }
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}
?>
