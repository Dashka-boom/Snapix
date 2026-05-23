<?php

function getCurrentUser(PDO $pdo): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, login, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function isAdmin(array $user): bool
{
    return isset($user['role']) && $user['role'] === 'admin';
}

function requireAdmin(PDO $pdo): array
{
    $user = getCurrentUser($pdo);

    if (!$user || !isAdmin($user)) {
        header('Location: admin-login.php');
        exit;
    }

    return $user;
}
