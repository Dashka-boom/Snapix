<?php
session_start();
require './config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Введите логин или email и пароль.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE login = :login OR email = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: index.php');
            exit;
        }

        $error = 'Неверный логин/email или пароль.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/auth.css">
    <title>Вход</title>
</head>
<body data-page="login">
    <main class="auth-page">
        <section class="auth-card">
            <a href="index.php" class="back-link" aria-label="Вернуться на главную">
                <span aria-hidden="true">←</span>
            </a>
            <h1>Вход в аккаунт</h1>
            <p class="subtitle">Войдите по логину или email.</p>

            <form class="auth-form" method="post" action="">
                <label>
                    <input type="text" name="login" placeholder="Логин или адрес электронной почты" value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>" required>
                </label>

                <label>
                    <input type="password" name="password" placeholder="Пароль" required>
                </label>

                <p class="form-status" aria-live="polite"><?php echo htmlspecialchars($error); ?></p>
                <button type="submit" class="submit-btn">Войти</button>
            </form>

            <div class="divider"><span>или</span></div>

            <p class="switch-text">
                Ещё нет аккаунта?
                <a href="register.php" class="link">Создать профиль</a>
            </p>
        </section>
    </main>
</body>
</html>
