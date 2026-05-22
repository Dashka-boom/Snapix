<?php
session_start();
require './config/config.php';
require './includes/admin-auth.php';

$currentUser = getCurrentUser($pdo);
if ($currentUser && isAdmin($currentUser)) {
    header('Location: admin-panel.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Введите логин/email и пароль администратора.';
    } else {
        $stmt = $pdo->prepare("SELECT id, login, password, role FROM users WHERE (login = :login OR email = :login) AND role = 'admin' LIMIT 1");
        $stmt->execute(['login' => $login]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['user_id'] = $admin['id'];
            header('Location: admin-panel.php');
            exit;
        }

        $error = 'Доступ запрещён: неверные данные или нет прав администратора.';
    }
}
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
    <main class="admin-auth-page">
        <section class="admin-card">
            <h1>Вход в админ-панель</h1>
            <p class="subtitle">Только для пользователей с ролью admin.</p>

            <form method="post" class="admin-form">
                <label>
                    <input type="text" name="login" placeholder="Логин или email" value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>" required>
                </label>

                <label>
                    <input type="password" name="password" placeholder="Пароль" required>
                </label>

                <p class="form-status"><?php echo htmlspecialchars($error); ?></p>
                <button type="submit">Войти в админку</button>
            </form>

            <a href="index.php" class="back-link">← Вернуться на главную</a>
        </section>
    </main>
</body>
</html>
