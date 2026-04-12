<?php
session_start();
require './config/config.php';

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

$stmt = $pdo->prepare('SELECT id, login, email, bio, avatar, background_image FROM users WHERE id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

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
    $nextLogin = trim($_POST['login'] ?? '');
    $nextEmail = trim($_POST['email'] ?? '');
    $nextBio = trim($_POST['bio'] ?? '');

    $nextLogin = $nextLogin !== '' ? $nextLogin : $user['login'];
    $nextEmail = $nextEmail !== '' ? $nextEmail : $user['email'];

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

        $update = $pdo->prepare('UPDATE users SET login = :login, email = :email, bio = :bio, avatar = :avatar, background_image = :background WHERE id = :id');
        $update->execute([
            'login' => $nextLogin,
            'email' => $nextEmail,
            'bio' => $nextBio,
            'avatar' => $newAvatar,
            'background' => $newBackground,
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
    <link rel="stylesheet" href="index.css">
    <title>Snapix</title>
    <style>
        .edit-profile-page {
            max-width: 980px;
            margin: 1.25rem auto 2.5rem;
            padding: 0 1rem;
            display: grid;
            gap: 1rem;
        }

        .edit-preview-shell {
            overflow: hidden;
            border: 1px solid #d7e4f2;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        .edit-profile-cover {
            position: relative;
            min-height: 230px;
            background: linear-gradient(135deg, #f7fbff 0%, #fdf7ef 100%);
            background-size: cover;
            background-position: center;
        }

        .edit-profile-cover::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.08), rgba(15, 23, 42, 0.2));
        }

        .upload-trigger {
            position: absolute;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 999px;
            border: 0;
            background: rgba(15, 23, 42, 0.72);
            color: #fff;
            cursor: pointer;
            transition: transform .2s ease, background-color .2s ease;
        }

        .upload-trigger:hover,
        .upload-trigger:focus-visible {
            transform: translateY(-1px);
            background: rgba(15, 23, 42, 0.86);
            outline: none;
        }

        .upload-trigger.cover {
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .upload-trigger.cover:hover,
        .upload-trigger.cover:focus-visible {
            transform: translate(-50%, -53%);
        }

        .upload-trigger svg {
            width: 22px;
            height: 22px;
            fill: currentColor;
            pointer-events: none;
        }

        .edit-preview-summary {
            margin-top: -60px;
            margin-inline: 1rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 3;
            background: #fff;
            border-radius: 18px;
            border: 1px solid #e2ebf5;
            padding: 1rem;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 1rem;
            align-items: center;
        }

        .edit-avatar-shell {
            position: relative;
            width: 112px;
            height: 112px;
            border-radius: 50%;
            border: 4px solid #fff;
            background: #fff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.16);
        }

        .edit-avatar {
            width: 100%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(135deg, #d9efff 0%, #f3f4f6 100%);
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #334155;
            font-size: 1.9rem;
            font-weight: 700;
        }

        .upload-trigger.avatar {
            right: -2px;
            bottom: -2px;
            width: 40px;
            height: 40px;
            border: 2px solid #fff;
        }

        .summary-copy h1 {
            margin: 0;
            color: #0f172a;
            font-size: clamp(1.3rem, 2.5vw, 1.9rem);
        }

        .summary-copy p {
            margin: 0.35rem 0 0;
            color: #64748b;
        }

        .form-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #e2ebf5;
            padding: 1.15rem;
            display: grid;
            gap: 1rem;
        }

        .field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.8rem;
        }

        .field {
            display: grid;
            gap: 0.35rem;
        }

        .field.full {
            grid-column: span 2;
        }

        .field label {
            color: #334155;
            font-size: .92rem;
            font-weight: 600;
        }

        .field input,
        .field textarea {
            width: 100%;
            border-radius: 10px;
            border: 1px solid #d7e4f2;
            background: #fff;
            padding: 0.72rem 0.85rem;
            font: inherit;
            color: #0f172a;
        }

        .field textarea {
            min-height: 110px;
            resize: vertical;
        }

        .field input:focus,
        .field textarea:focus {
            outline: none;
            border-color: #0095f6;
            box-shadow: 0 0 0 3px rgba(0, 149, 246, .13);
        }

        .status {
            margin: 0;
            font-size: .95rem;
            color: #475569;
        }

        .status.is-success {
            color: #0284c7;
        }

        .status.is-error {
            color: #dc2626;
        }

        .actions {
            display: flex;
            gap: .7rem;
            justify-content: flex-end;
        }

        .file-input-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }

        @media (max-width: 760px) {
            .edit-preview-summary {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
            }

            .field-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            .actions {
                flex-direction: column;
            }

            .actions .secondary-link,
            .actions .primary-link {
                width: 100%;
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body data-page="edit-profile">
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">Snapix</a>
            <input type="text" class="search" placeholder="Поиск">
            <div class="menu">
                <a href="#">Reels</a>
                <a href="profile.php" class="user-avatar-link" aria-label="Открыть профиль">
                    <?php if (!empty($user['avatar'])): ?>
                        <span class="user-avatar" style="background-image: url('<?php echo htmlspecialchars($user['avatar']); ?>');"></span>
                    <?php else: ?>
                        <span class="user-avatar"><?php echo htmlspecialchars(mb_substr($user['login'], 0, 1)); ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </nav>
    </header>

    <main class="edit-profile-page">
        <section class="edit-preview-shell">
            <div class="edit-profile-cover" id="coverPreview" style="<?php echo $coverStyle; ?>">
                <input class="file-input-hidden" type="file" id="backgroundInput" name="background" accept="image/*" form="editProfileForm">
                <button type="button" class="upload-trigger cover" data-target-input="backgroundInput" aria-label="Изменить фон профиля">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 4.5 7.7 6H5.5A2.5 2.5 0 0 0 3 8.5v9A2.5 2.5 0 0 0 5.5 20h13a2.5 2.5 0 0 0 2.5-2.5v-9A2.5 2.5 0 0 0 18.5 6h-2.2L15 4.5H9Zm3 12a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Zm0-1.8a2.7 2.7 0 1 0 0-5.4 2.7 2.7 0 0 0 0 5.4Z"/></svg>
                </button>
            </div>
            <div class="edit-preview-summary">
                <div class="edit-avatar-shell">
                    <div class="edit-avatar" id="avatarPreview" style="<?php echo $avatarStyle; ?>"><?php echo empty($user['avatar']) ? htmlspecialchars(mb_substr($user['login'], 0, 1)) : ''; ?></div>
                    <input class="file-input-hidden" type="file" id="avatarInput" name="avatar" accept="image/*" form="editProfileForm">
                    <button type="button" class="upload-trigger avatar" data-target-input="avatarInput" aria-label="Изменить аватар">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 4.5 7.7 6H5.5A2.5 2.5 0 0 0 3 8.5v9A2.5 2.5 0 0 0 5.5 20h13a2.5 2.5 0 0 0 2.5-2.5v-9A2.5 2.5 0 0 0 18.5 6h-2.2L15 4.5H9Zm3 12a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Zm0-1.8a2.7 2.7 0 1 0 0-5.4 2.7 2.7 0 0 0 0 5.4Z"/></svg>
                    </button>
                </div>
                <div class="summary-copy">
                    <h1>Редактирование профиля</h1>
                    <p>Те же обложка и аватар, что и в профиле. Выберите новые изображения и сохраните изменения.</p>
                </div>
            </div>
        </section>

        <form id="editProfileForm" class="form-card" action="edit-profile.php" method="post" enctype="multipart/form-data">
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
            </div>

            <p class="status <?php echo $statusType; ?>"><?php echo htmlspecialchars($statusMessage); ?></p>

            <div class="actions">
                <a href="profile.php" class="secondary-link">Отмена</a>
                <button class="primary-link" type="submit">Сохранить изменения</button>
            </div>
        </form>
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
