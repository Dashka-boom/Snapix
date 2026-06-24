<?php
session_start();
require './config/config.php';
require_once './includes/side-menu.php';
require './includes/icons.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN background_image VARCHAR(255) DEFAULT NULL AFTER avatar");
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? null) !== 1060) {
        throw $e;
    }
}

$stmt = $pdo->prepare('SELECT id, login, email, birth_date, gender, city, avatar, bio, background_image, website, gender, city, is_private FROM users WHERE id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$unreadMessagesStmt = $pdo->prepare('
    SELECT COUNT(*)
    FROM messages m
    INNER JOIN chats c ON c.id = m.chat_id
    WHERE (c.user_one_id = :user_id OR c.user_two_id = :user_id)
      AND m.sender_id != :user_id
      AND m.is_read = 0
');
$unreadMessagesStmt->execute(['user_id' => $user['id']]);
$unreadMessagesCount = (int) $unreadMessagesStmt->fetchColumn();

$statusMessage = '';
$statusType = '';

function saveUploadedImage(array $file, string $prefix): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Не удалось загрузить файл. Попробуйте снова.');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Файл слишком большой. Максимум 5MB.');
    }

    $tmpPath = $file['tmp_name'] ?? '';
    $mime = mime_content_type($tmpPath);

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => null,
    };

    if ($ext === null) {
        throw new RuntimeException('Поддерживаются только JPG, PNG, WEBP или GIF.');
    }

    $uploadDir = __DIR__ . '/uploads/profile';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Не удалось создать папку загрузок.');
    }

    $filename = sprintf('%s_%s.%s', $prefix, bin2hex(random_bytes(12)), $ext);
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $destination)) {
        throw new RuntimeException('Не удалось сохранить изображение.');
    }

    return 'uploads/profile/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $statusMessage = 'Сессия истекла. Обновите страницу и попробуйте снова.';
        $statusType = 'is-error';
    } elseif (($_POST['action'] ?? '') === 'unblock_user') {
        $blockedUserId = (int) ($_POST['blocked_user_id'] ?? 0);

        if ($blockedUserId > 0) {
            $deleteBlockStmt = $pdo->prepare('
                DELETE FROM user_blocks
                WHERE blocker_user_id = :current_user_id
                  AND blocked_user_id = :blocked_user_id
            ');
            $deleteBlockStmt->execute([
                'current_user_id' => (int) $user['id'],
                'blocked_user_id' => $blockedUserId,
            ]);
        }

        $statusMessage = 'Пользователь разблокирован.';
        $statusType = 'is-success';
    } else {
        $nextLogin = trim($_POST['login'] ?? '');
        $nextEmail = trim($_POST['email'] ?? '');
        $nextBio = trim($_POST['bio'] ?? '');
        $nextWebsite = trim($_POST['website'] ?? '');
        $nextBirthDate = trim($_POST['birth_date'] ?? '');
        $nextGender = trim($_POST['gender'] ?? '');
        $nextCity = trim($_POST['city'] ?? '');
        $nextIsPrivate = isset($_POST['is_private']) ? 1 : 0;

        $nextLogin = $nextLogin !== '' ? $nextLogin : $user['login'];
        $nextEmail = $nextEmail !== '' ? $nextEmail : $user['email'];

        if ($nextBirthDate !== '') {
            $birthDateObj = DateTime::createFromFormat('Y-m-d', $nextBirthDate);
            $today = new DateTime();

            if (!$birthDateObj || $birthDateObj->format('Y-m-d') !== $nextBirthDate) {
                $statusMessage = 'Введите корректную дату рождения.';
                $statusType = 'is-error';
            } elseif ($birthDateObj > $today) {
                $statusMessage = 'Дата рождения не может быть в будущем.';
                $statusType = 'is-error';
            } else {
                $age = $today->diff($birthDateObj)->y;

                if ($age < 13) {
                    $statusMessage = 'Пользователю должно быть не меньше 13 лет.';
                    $statusType = 'is-error';
                }
            }
        }

        if ($statusType !== 'is-error') {
            try {
                $newAvatar = $user['avatar'];
                $newBackground = $user['background_image'];

                $uploadedAvatar = saveUploadedImage($_FILES['avatar'] ?? [], 'avatar');
                if ($uploadedAvatar !== null) {
                    $newAvatar = $uploadedAvatar;
                }

                $uploadedBackground = saveUploadedImage($_FILES['background'] ?? [], 'background');
                if ($uploadedBackground !== null) {
                    $newBackground = $uploadedBackground;
                }

                $update = $pdo->prepare('
                    UPDATE users 
                    SET 
                        login = :login,
                        email = :email,
                        bio = :bio,
                        avatar = :avatar,
                        background_image = :background,
                        website = :website,
                        birth_date = :birth_date,
                        gender = :gender,
                        city = :city,
                        is_private = :is_private
                    WHERE id = :id
                ');

                $update->execute([
                    'login' => $nextLogin,
                    'email' => $nextEmail,
                    'bio' => $nextBio,
                    'avatar' => $newAvatar,
                    'background' => $newBackground,
                    'website' => $nextWebsite !== '' ? $nextWebsite : null,
                    'birth_date' => $nextBirthDate !== '' ? $nextBirthDate : null,
                    'gender' => $nextGender !== '' ? $nextGender : null,
                    'city' => $nextCity !== '' ? $nextCity : null,
                    'is_private' => $nextIsPrivate,
                    'id' => $user['id'],
                ]);

                $stmt->execute(['id' => $_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $statusMessage = 'Профиль обновлён.';
                $statusType = 'is-success';
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? null) === 1062) {
                    $statusMessage = 'Логин или электронная почта уже заняты.';
                } else {
                    $statusMessage = 'Не удалось сохранить профиль. Попробуйте позже.';
                }
                $statusType = 'is-error';
            } catch (Throwable $e) {
                $statusMessage = $e->getMessage();
                $statusType = 'is-error';
            }
        }
    }
}

$blockedUsersStmt = $pdo->prepare('
    SELECT users.id, users.login, users.avatar, user_blocks.created_at
    FROM user_blocks
    INNER JOIN users ON users.id = user_blocks.blocked_user_id
    WHERE user_blocks.blocker_user_id = :current_user_id
    ORDER BY user_blocks.created_at DESC
');
$blockedUsersStmt->execute(['current_user_id' => (int) $user['id']]);
$blockedUsers = $blockedUsersStmt->fetchAll(PDO::FETCH_ASSOC);

$avatarStyle = !empty($user['avatar'])
    ? "background-image: url('" . htmlspecialchars($user['avatar'], ENT_QUOTES) . "');"
    : '';

$coverStyle = !empty($user['background_image'])
    ? "background-image: url('" . htmlspecialchars($user['background_image'], ENT_QUOTES) . "');"
    : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/edit-profile.css">
    <link rel="icon" href="icon/light theme/logo.png" type="image/png">
    <title>Snapix</title>
</head>
<body data-page="edit-profile" class="has-side-menu">
    <?php render_side_menu($user); ?>

    <div class="page-glass-nav" aria-hidden="true"></div>

    <main class="edit-profile-page">
        <section class="edit-preview-shell">
            <div class="edit-profile-cover" id="coverPreview" style="<?php echo $coverStyle; ?>">
                <input class="file-input-hidden" type="file" id="backgroundInput" name="background" accept="image/*" form="editProfileForm">
                <button type="button" class="upload-trigger cover" data-target-input="backgroundInput" aria-label="Изменить фон профиля">
                    <?php echo snapix_icon('camera'); ?>
                </button>
            </div>
            <div class="edit-preview-summary">
                <div class="edit-avatar-shell">
                    <div class="edit-avatar" id="avatarPreview" style="<?php echo $avatarStyle; ?>"><?php echo empty($user['avatar']) ? htmlspecialchars(mb_substr($user['login'], 0, 1)) : ''; ?></div>
                    <input class="file-input-hidden" type="file" id="avatarInput" name="avatar" accept="image/*" form="editProfileForm">
                    <button type="button" class="upload-trigger avatar" data-target-input="avatarInput" aria-label="Изменить аватар">
                        <?php echo snapix_icon('camera'); ?>
                    </button>
                </div>
                <div class="summary-copy">
                    <h1>Редактирование профиля</h1>
                </div>
            </div>
        </section>

        <form id="editProfileForm" class="form-card" action="edit-profile.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="field-grid">
                <div class="field">
                    <label for="login">Логин</label>
                    <input id="login" type="text" name="login" value="<?php echo htmlspecialchars($user['login']); ?>" placeholder="Логин можно оставить пустым">
                </div>
                <div class="field">
                    <label for="email">Электронная почта</label>
                    <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="Почту можно оставить пустой">
                </div>
                <div class="field full">
                    <label for="bio">О себе</label>
                    <textarea id="bio" name="bio" placeholder="Расскажите о себе"><?php echo htmlspecialchars((string) ($user['bio'] ?? '')); ?></textarea>
                </div>

                <div class="field">
    <label for="city">Город</label>
    <input id="city" type="text" name="city"
        value="<?php echo htmlspecialchars((string)($user['city'] ?? '')); ?>"
        placeholder="Введите город">
</div>

<div class="field">
    <label for="birth_date">Дата рождения</label>
    <input id="birth_date" type="date" name="birth_date"
        value="<?php echo htmlspecialchars((string)($user['birth_date'] ?? '')); ?>">
</div>

<div class="field">
    <label for="gender">Пол</label>
    <select id="gender" name="gender">
        <option value="">Не выбрано</option>
        <option value="Женский" <?php echo (($user['gender'] ?? '') === 'Женский') ? 'selected' : ''; ?>>Женский</option>
        <option value="Мужской" <?php echo (($user['gender'] ?? '') === 'Мужской') ? 'selected' : ''; ?>>Мужской</option>
        <option value="Другой" <?php echo (($user['gender'] ?? '') === 'Другой') ? 'selected' : ''; ?>>Другой</option>
    </select>
</div>

<div class="field full">
    <label for="website">Сайт</label>
    <input id="website" type="url" name="website"
        value="<?php echo htmlspecialchars((string)($user['website'] ?? '')); ?>"
        placeholder="https://example.com">
</div>

<div class="field full">
    <div class="profile-private-row">
        <input type="checkbox" id="is_private" name="is_private"
            <?php echo !empty($user['is_private']) ? 'checked' : ''; ?>>

        <label for="is_private">
            Закрытый аккаунт
        </label>
    </div>
</div>
            </div>

            <p class="status <?php echo $statusType; ?>"><?php echo htmlspecialchars($statusMessage); ?></p>

            <div class="actions">
                <a href="profile.php" class="secondary-link">Отмена</a>
                <button class="primary-link" type="submit">Сохранить изменения</button>
            </div>
        </form>

        <section class="form-card blocked-users-card" aria-labelledby="blockedUsersTitle">
            <div class="blocked-users-header">
                <h2 id="blockedUsersTitle">Чёрный список</h2>
            </div>

            <?php if ($blockedUsers): ?>
                <div class="blocked-users-list">
                    <?php foreach ($blockedUsers as $blockedUser): ?>
                        <article class="blocked-user-row">
                            <div class="blocked-user-main">
                                <span class="blocked-user-avatar"<?php if (!empty($blockedUser['avatar'])): ?> style="background-image: url('<?php echo htmlspecialchars($blockedUser['avatar'], ENT_QUOTES, 'UTF-8'); ?>');"<?php endif; ?>>
                                    <?php if (empty($blockedUser['avatar'])): ?><?php echo htmlspecialchars(mb_substr((string) $blockedUser['login'], 0, 1)); ?><?php endif; ?>
                                </span>
                                <strong><?php echo htmlspecialchars((string) $blockedUser['login']); ?></strong>
                            </div>
                            <form method="post" action="edit-profile.php" class="blocked-user-action">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="unblock_user">
                                <input type="hidden" name="blocked_user_id" value="<?php echo (int) $blockedUser['id']; ?>">
                                <button type="submit" class="secondary-link">Разблокировать</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="blocked-users-empty">Чёрный список пуст</p>
            <?php endif; ?>
        </section>
    </main>

    <script>
        const updatePreview = (inputId, previewId, mode) => {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);

            if (!input || !preview) return;

            input.addEventListener('change', () => {
                const [file] = input.files || [];
                if (!file) return;

                if (input.dataset.objectUrl) {
                    URL.revokeObjectURL(input.dataset.objectUrl);
                }

                const objectUrl = URL.createObjectURL(file);
                input.dataset.objectUrl = objectUrl;
                preview.style.backgroundImage = `url('${objectUrl}')`;

                if (mode === 'avatar') {
                    preview.textContent = '';
                }
            });
        };

        document.querySelectorAll('[data-target-input]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.targetInput);
                input?.click();
            });
        });

        updatePreview('avatarInput', 'avatarPreview', 'avatar');
        updatePreview('backgroundInput', 'coverPreview', 'cover');
    </script>
</body>
</html>
