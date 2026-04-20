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
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}
?>
