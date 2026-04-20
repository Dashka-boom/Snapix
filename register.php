<?php
session_start();
require './config/config.php';

$error = '';
$today = new DateTime('today');
$minimumBirthDate = (clone $today)->modify('-13 years');
$maximumBirthDateValue = $minimumBirthDate->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $birthDate = $_POST['birth_date'] ?? '';

    if ($login === '' || $email === '' || $password === '' || $birthDate === '') {
        $error = 'Заполните все поля.';
    } else {
        $birthDateObject = DateTime::createFromFormat('Y-m-d', $birthDate);
        $birthDateErrors = DateTime::getLastErrors();

        if ($birthDateErrors === false) {
            $birthDateErrors = [
                'warning_count' => 0,
                'error_count' => 0
            ];
        }

        if (
            !$birthDateObject ||
            $birthDateObject->format('Y-m-d') !== $birthDate ||
            $birthDateErrors['warning_count'] > 0 ||
            $birthDateErrors['error_count'] > 0
        ) {
            $error = 'Укажите корректную дату рождения.';
        } elseif ($birthDateObject > $today) {
            $error = 'Дата рождения не может быть позже текущей даты.';
        } elseif ($birthDateObject > $minimumBirthDate) {
            $error = 'Регистрация доступна только пользователям от 13 лет';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE login = :login OR email = :email LIMIT 1');
            $stmt->execute([
                'login' => $login,
                'email' => $email
            ]);

            if ($stmt->fetch()) {
                $error = 'Пользователь с таким логином или email уже существует.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare('INSERT INTO users (login, email, password, birth_date) VALUES (:login, :email, :password, :birth_date)');
                $stmt->execute([
                    'login' => $login,
                    'email' => $email,
                    'password' => $passwordHash,
                    'birth_date' => $birthDate
                ]);

                header('Location: login.php');
                exit;
            }
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
    <title>Регистрация</title>
</head>
<body data-page="register">
    <main class="auth-page">
        <section class="auth-card">
            <a href="index.php" class="back-link" aria-label="Вернуться на главную">
                <span aria-hidden="true">←</span>
            </a>

            <h1>Создание аккаунта</h1>
            <p class="subtitle">Зарегистрируйтесь, чтобы пользоваться Snapix.</p>

            <form class="auth-form" method="post" action="" id="register-form" novalidate>
                <label>
                    <input type="text" name="login" placeholder="Логин" value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>" required>
                </label>

                <label>
                    <input type="email" name="email" placeholder="Электронный адрес" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </label>

                <label>
                    <input
                        type="date"
                        name="birth_date"
                        id="birth_date"
                        value="<?php echo htmlspecialchars($_POST['birth_date'] ?? ''); ?>"
                        max="<?php echo htmlspecialchars($maximumBirthDateValue); ?>"
                        required
                    >
                </label>

                <label>
                    <input type="password" name="password" placeholder="Пароль" required>
                </label>

                <p class="form-status" id="form-status" aria-live="polite"><?php echo htmlspecialchars($error); ?></p>
                <button type="submit" class="submit-btn">Зарегистрироваться</button>
            </form>

            <div class="divider"><span>или</span></div>

            <p class="switch-text">
                Уже есть аккаунт?
                <a href="login.php" class="link">Войти</a>
            </p>
        </section>
    </main>

    <script>
        const registerForm = document.getElementById('register-form');
        const birthDateInput = document.getElementById('birth_date');
        const formStatus = document.getElementById('form-status');
        const minAllowedBirthDate = '<?php echo htmlspecialchars($maximumBirthDateValue, ENT_QUOTES); ?>';

        function validateBirthDate() {
            const value = birthDateInput.value;

            birthDateInput.setCustomValidity('');
            birthDateInput.classList.remove('input-error');

            if (!value) {
                return true;
            }

            const selectedDate = new Date(value + 'T00:00:00');
            const allowedDate = new Date(minAllowedBirthDate + 'T00:00:00');

            if (Number.isNaN(selectedDate.getTime())) {
                birthDateInput.setCustomValidity('Укажите корректную дату рождения.');
            } else if (selectedDate > allowedDate) {
                birthDateInput.setCustomValidity('Регистрация доступна только пользователям от 13 лет.');
            }

            if (!birthDateInput.checkValidity()) {
                birthDateInput.classList.add('input-error');
                formStatus.textContent = birthDateInput.validationMessage;
                return false;
            }

            formStatus.textContent = '';
            return true;
        }

        birthDateInput.addEventListener('input', validateBirthDate);
        birthDateInput.addEventListener('change', validateBirthDate);

        registerForm.addEventListener('submit', function (event) {
            if (!validateBirthDate() || !registerForm.checkValidity()) {
                event.preventDefault();

                if (!formStatus.textContent) {
                    formStatus.textContent = 'Проверьте заполнение формы и укажите корректную дату рождения.';
                }
            }
        });
    </script>
</body>
</html>
