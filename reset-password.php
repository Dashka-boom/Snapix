<?php
session_start();
require './config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$message = '';
$validToken = false;

if ($token !== '') {
    $tokenStmt = $pdo->prepare('
        SELECT password_reset_tokens.id, password_reset_tokens.user_id, users.login
        FROM password_reset_tokens
        INNER JOIN users ON users.id = password_reset_tokens.user_id
        WHERE password_reset_tokens.token = :token
          AND password_reset_tokens.used_at IS NULL
          AND password_reset_tokens.expires_at > NOW()
        LIMIT 1
    ');
    $tokenStmt->execute(['token' => $token]);
    $resetData = $tokenStmt->fetch();

    if ($resetData) {
        $validToken = true;
    }
}

if (!$validToken) {
    $error = 'Ссылка восстановления недействительна или устарела.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (mb_strlen($password) < 6) {
        $error = 'Пароль должен быть не короче 6 символов.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Пароли не совпадают.';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();

        try {
            $updateUserStmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id LIMIT 1');
            $updateUserStmt->execute([
                'password' => $passwordHash,
                'id' => (int) $resetData['user_id'],
            ]);

            $updateTokenStmt = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id LIMIT 1');
            $updateTokenStmt->execute(['id' => (int) $resetData['id']]);

            $pdo->commit();
            $message = 'Пароль успешно изменён. Теперь можно войти.';
            $validToken = false;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $error = 'Не удалось изменить пароль.';
        }
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
<body data-page="reset-password">
    <main class="auth-page">
        <section class="auth-card">
            <a href="login.php" class="back-link" aria-label="Назад">
                <span aria-hidden="true">←</span>
            </a>

            <h1>Новый пароль</h1>

            <?php if ($validToken): ?>
                <form class="auth-form" method="post">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <label>
                        <input type="password" name="password" placeholder="Новый пароль" required>
                    </label>

                    <label>
                        <input type="password" name="password_confirm" placeholder="Повторите пароль" required>
                    </label>

                    <p class="form-status" aria-live="polite"><?php echo htmlspecialchars($error); ?></p>

                    <button type="submit" class="submit-btn">Сохранить пароль</button>
                </form>
            <?php else: ?>
                <p class="form-status" aria-live="polite">
                    <?php echo htmlspecialchars($message ?: $error); ?>
                </p>

                <p class="switch-text">
                    <a href="login.php" class="link">Вернуться ко входу</a>
                </p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>