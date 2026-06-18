<?php
session_start();
require './config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');

    if ($login === '') {
        $error = 'Введите логин или email.';
    } else {
        $stmt = $pdo->prepare('SELECT id, login, email FROM users WHERE login = :login OR email = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $deleteOldStmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id');
            $deleteOldStmt->execute(['user_id' => (int) $user['id']]);

            $insertStmt = $pdo->prepare('
                INSERT INTO password_reset_tokens (user_id, token, expires_at)
                VALUES (:user_id, :token, :expires_at)
            ');
            $insertStmt->execute([
                'user_id' => (int) $user['id'],
                'token' => $token,
                'expires_at' => $expiresAt,
            ]);

            $resetLink = 'reset-password.php?token=' . urlencode($token);
        }

        $message = 'Если аккаунт найден, ссылка для восстановления будет создана.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/auth.css">
    <link rel="icon" href="icon/light theme/logo.png" type="image/png">
    <title>Snapix</title>
</head>
<body data-page="forgot-password">
    <main class="auth-page">
        <section class="auth-card">
            <a href="login.php" class="back-link" aria-label="Назад">
                <span aria-hidden="true">←</span>
            </a>

            <h1>Восстановление пароля</h1>
            <p class="subtitle">Введите логин или email, чтобы создать ссылку восстановления.</p>

            <form class="auth-form" method="post">
                <label>
                    <input type="text" name="login" placeholder="Логин или email" required>
                </label>

                <p class="form-status" aria-live="polite">
                    <?php echo htmlspecialchars($error ?: $message); ?>
                </p>

                <button type="submit" class="submit-btn">Создать ссылку</button>
            </form>

            <?php if ($resetLink): ?>
                <p class="switch-text">
                    Ссылка для восстановления:
                    <a href="<?php echo htmlspecialchars($resetLink); ?>" class="link">Сбросить пароль</a>
                </p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>