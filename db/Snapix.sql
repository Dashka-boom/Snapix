-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Апр 28 2026 г., 13:58
-- Версия сервера: 8.0.30
-- Версия PHP: 8.1.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `Snapix`
--

-- --------------------------------------------------------

--
-- Структура таблицы `chats`
--

CREATE TABLE `chats` (
  `id` bigint UNSIGNED NOT NULL,
  `user_one_id` bigint UNSIGNED NOT NULL,
  `user_two_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `chats`
--

INSERT INTO `chats` (`id`, `user_one_id`, `user_two_id`, `created_at`, `updated_at`) VALUES
(1, 6, 7, '2026-04-23 12:41:58', '2026-04-23 12:42:44'),
(2, 5, 6, '2026-04-23 12:45:56', '2026-04-28 10:47:07'),
(3, 5, 7, '2026-04-23 12:49:46', '2026-04-28 10:48:35');

-- --------------------------------------------------------

--
-- Структура таблицы `comments`
--

CREATE TABLE `comments` (
  `id` bigint UNSIGNED NOT NULL,
  `post_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `comment_text` text COLLATE utf8mb4_general_ci NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `comments`
--

INSERT INTO `comments` (`id`, `post_id`, `user_id`, `comment_text`, `is_deleted`, `created_at`, `updated_at`) VALUES
(1, 6, 6, 'омгд', 0, '2026-04-20 13:36:45', '2026-04-20 13:36:45'),
(2, 6, 5, 'пиздючки', 0, '2026-04-20 13:38:13', '2026-04-20 13:38:13'),
(3, 7, 6, 'сука', 1, '2026-04-20 13:40:05', '2026-04-20 19:18:41'),
(4, 9, 6, 'ф', 0, '2026-04-21 17:08:06', '2026-04-21 17:08:06'),
(5, 8, 6, 'ы', 0, '2026-04-21 17:08:15', '2026-04-21 17:08:15'),
(6, 8, 6, 'ы', 0, '2026-04-21 17:08:17', '2026-04-21 17:08:17'),
(7, 8, 6, 'в', 0, '2026-04-21 17:08:18', '2026-04-21 17:08:18'),
(8, 8, 6, 'ы', 0, '2026-04-21 17:08:22', '2026-04-21 17:08:22'),
(9, 8, 6, 'ы', 0, '2026-04-21 17:08:24', '2026-04-21 17:08:24'),
(10, 8, 6, 'ы', 0, '2026-04-21 17:08:25', '2026-04-21 17:08:25'),
(11, 8, 6, 'ы', 0, '2026-04-21 17:08:25', '2026-04-21 17:08:25'),
(12, 8, 7, 's', 0, '2026-04-23 09:25:25', '2026-04-23 09:25:25');

-- --------------------------------------------------------

--
-- Структура таблицы `followers`
--

CREATE TABLE `followers` (
  `id` bigint UNSIGNED NOT NULL,
  `follower_id` bigint UNSIGNED NOT NULL,
  `following_id` bigint UNSIGNED NOT NULL,
  `status` enum('pending','accepted','declined') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'accepted',
  `declined_until` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Дамп данных таблицы `followers`
--

INSERT INTO `followers` (`id`, `follower_id`, `following_id`, `status`, `declined_until`, `created_at`) VALUES
(1, 5, 6, 'accepted', NULL, '2026-04-20 16:30:43'),
(4, 6, 5, 'accepted', NULL, '2026-04-21 16:48:53'),
(5, 5, 7, 'accepted', NULL, '2026-04-23 13:15:48');

-- --------------------------------------------------------

--
-- Структура таблицы `follow_requests`
--

CREATE TABLE `follow_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `sender_id` bigint UNSIGNED NOT NULL,
  `receiver_id` bigint UNSIGNED NOT NULL,
  `status` enum('pending','accepted','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Структура таблицы `hidden_posts`
--

CREATE TABLE `hidden_posts` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `post_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `hidden_posts`
--

INSERT INTO `hidden_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES
(1, 7, 9, '2026-04-21 17:37:46'),
(2, 5, 8, '2026-04-28 10:45:49');

-- --------------------------------------------------------

--
-- Структура таблицы `likes`
--

CREATE TABLE `likes` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `post_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `likes`
--

INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES
(2, 6, 6, '2026-04-20 13:37:19'),
(3, 6, 7, '2026-04-20 13:39:56'),
(4, 5, 7, '2026-04-20 13:44:49'),
(6, 5, 8, '2026-04-21 13:48:44'),
(8, 7, 9, '2026-04-21 17:35:07'),
(10, 7, 11, '2026-04-23 09:51:14');

-- --------------------------------------------------------

--
-- Структура таблицы `messages`
--

CREATE TABLE `messages` (
  `id` bigint UNSIGNED NOT NULL,
  `chat_id` bigint UNSIGNED NOT NULL,
  `sender_id` bigint UNSIGNED NOT NULL,
  `message_text` text COLLATE utf8mb4_general_ci,
  `post_id` bigint UNSIGNED DEFAULT NULL,
  `reply_to_message_id` bigint UNSIGNED DEFAULT NULL,
  `forwarded_from_message_id` bigint UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_for_all` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `edited_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `messages`
--

INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `post_id`, `is_read`, `created_at`) VALUES
(1, 1, 7, 'привет', NULL, 1, '2026-04-23 12:42:04'),
(2, 1, 7, 'как дела', NULL, 1, '2026-04-23 12:42:09'),
(3, 1, 6, 'норм', NULL, 1, '2026-04-23 12:42:44'),
(4, 2, 5, 'але', NULL, 1, '2026-04-23 12:46:02'),
(5, 2, 6, 'але', NULL, 1, '2026-04-23 12:46:14'),
(6, 2, 6, 'РЕАЛЬНО РАБОТАЕТ', NULL, 1, '2026-04-23 12:46:41'),
(7, 2, 6, 'задержка 3 секунды', NULL, 1, '2026-04-23 12:46:53'),
(8, 3, 5, '???', NULL, 1, '2026-04-23 12:49:50'),
(9, 2, 5, 'ffff', NULL, 1, '2026-04-23 12:51:18'),
(10, 2, 6, 'sssss', NULL, 1, '2026-04-23 12:51:34'),
(11, 2, 5, 'привет', NULL, 1, '2026-04-23 13:01:32'),
(12, 2, 6, 'я крутая', NULL, 1, '2026-04-23 13:02:20'),
(13, 2, 6, 'оооо', NULL, 1, '2026-04-23 13:02:27'),
(14, 2, 6, 'ооо', NULL, 1, '2026-04-23 13:02:34'),
(15, 2, 6, 'ооо', NULL, 1, '2026-04-23 13:02:45'),
(22, 2, 5, '[post_share]|11|7|pashka-durashka', 11, 0, '2026-04-26 11:28:00'),
(23, 2, 5, '[post_share]|11|7|pashka-durashka', 11, 0, '2026-04-26 11:38:13'),
(24, 3, 5, '[post_share]|11|7|pashka-durashka', 11, 1, '2026-04-26 11:42:46'),
(25, 2, 5, 'НАКОНЕЦ-ТО', NULL, 0, '2026-04-26 11:45:25'),
(26, 3, 7, 'ghbdtn', NULL, 1, '2026-04-26 14:02:58'),
(27, 3, 7, 'и че', NULL, 1, '2026-04-26 14:03:05'),
(28, 3, 5, 'вв', NULL, 1, '2026-04-26 14:03:42'),
(29, 3, 5, 'вебсокет работает?', NULL, 1, '2026-04-26 14:04:22'),
(30, 3, 7, 'нет ошибка event', NULL, 1, '2026-04-26 14:09:34'),
(31, 3, 7, 'обидно', NULL, 1, '2026-04-26 14:09:40'),
(32, 3, 5, 'это да', NULL, 1, '2026-04-26 16:21:01'),
(33, 3, 5, 'а', NULL, 1, '2026-04-26 16:23:47'),
(34, 3, 7, 'б', NULL, 1, '2026-04-26 16:24:01'),
(35, 3, 5, 'в', NULL, 1, '2026-04-26 16:24:03'),
(36, 3, 5, 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, 1, '2026-04-26 16:24:11'),
(37, 3, 7, 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, 1, '2026-04-26 16:41:03'),
(38, 3, 5, 'в', NULL, 1, '2026-04-26 16:41:22'),
(39, 3, 7, 'ы', NULL, 1, '2026-04-26 16:41:43'),
(40, 3, 7, 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, 1, '2026-04-26 16:41:52'),
(41, 3, 7, 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, 1, '2026-04-26 16:45:43'),
(42, 3, 5, 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, 1, '2026-04-26 16:45:47'),
(43, 2, 5, '[post_share]|11|7|pashka-durashka', 11, 0, '2026-04-28 10:47:07'),
(44, 3, 5, 'привет', NULL, 0, '2026-04-28 10:47:47'),
(45, 3, 5, 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, 0, '2026-04-28 10:48:35');

-- --------------------------------------------------------

--
-- Структура таблицы `message_hidden`
--

CREATE TABLE `message_hidden` (
  `id` bigint UNSIGNED NOT NULL,
  `message_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `message_reactions`
--

CREATE TABLE `message_reactions` (
  `id` bigint UNSIGNED NOT NULL,
  `message_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `reaction` varchar(16) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `moderation_reasons`
--

CREATE TABLE `moderation_reasons` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `moderation_reasons`
--

INSERT INTO `moderation_reasons` (`id`, `code`, `label`, `created_at`) VALUES
(1, 'spam', 'Спам', '2026-04-20 19:06:28'),
(2, 'abuse', 'Оскорбления', '2026-04-20 19:06:28'),
(3, 'hate', 'Разжигание ненависти', '2026-04-20 19:06:28'),
(4, 'fraud', 'Мошенничество', '2026-04-20 19:06:28'),
(5, 'other', 'Другое', '2026-04-20 19:06:28');

-- --------------------------------------------------------

--
-- Структура таблицы `moderation_reports`
--

CREATE TABLE `moderation_reports` (
  `id` bigint UNSIGNED NOT NULL,
  `reporter_user_id` bigint UNSIGNED NOT NULL,
  `target_user_id` bigint UNSIGNED DEFAULT NULL,
  `target_comment_id` bigint UNSIGNED DEFAULT NULL,
  `reason_id` bigint UNSIGNED DEFAULT NULL,
  `reason_text` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('open','reviewed','resolved') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `moderation_reports`
--

INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES
(1, 7, 5, NULL, NULL, 'Жалоба на пользователя через пост #9', 'open', '2026-04-21 17:37:42'),
(2, 7, 5, NULL, NULL, 'Жалоба на пользователя через пост #9', 'open', '2026-04-21 17:37:44'),
(3, 7, 5, NULL, NULL, 'Жалоба на пост #9', 'open', '2026-04-21 17:37:45'),
(4, 7, 6, NULL, NULL, 'Жалоба на пользователя через пост #8', 'open', '2026-04-21 17:40:11'),
(5, 5, 6, NULL, NULL, 'Жалоба на пост #8', 'open', '2026-04-28 10:45:40'),
(6, 5, 6, NULL, NULL, 'Жалоба на пользователя через пост #8', 'open', '2026-04-28 10:45:44');

-- --------------------------------------------------------

--
-- Структура таблицы `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `pinned_posts`
--

CREATE TABLE `pinned_posts` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `post_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `pinned_messages`
--

CREATE TABLE `pinned_messages` (
  `id` bigint UNSIGNED NOT NULL,
  `chat_id` bigint UNSIGNED NOT NULL,
  `message_id` bigint UNSIGNED NOT NULL,
  `pinned_by_user_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `pinned_posts`
--

INSERT INTO `pinned_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES
(1, 7, 11, '2026-04-21 17:42:23'),
(3, 5, 9, '2026-04-28 10:46:07');

-- --------------------------------------------------------

--
-- Структура таблицы `posts`
--

CREATE TABLE `posts` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `caption` text COLLATE utf8mb4_general_ci,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `posts`
--

INSERT INTO `posts` (`id`, `user_id`, `caption`, `is_deleted`, `created_at`, `updated_at`) VALUES
(6, 5, 'это я и федя', 1, '2026-04-16 18:57:40', '2026-04-20 15:02:12'),
(7, 6, 'Это я и кто', 1, '2026-04-20 13:39:02', '2026-04-20 16:12:44'),
(8, 6, NULL, 0, '2026-04-20 16:13:06', '2026-04-20 16:13:06'),
(9, 5, 'тт', 1, '2026-04-20 16:37:48', '2026-04-28 10:46:54'),
(10, 7, NULL, 1, '2026-04-21 17:36:51', '2026-04-21 17:37:35'),
(11, 7, NULL, 0, '2026-04-21 17:42:16', '2026-04-21 17:42:16');

-- --------------------------------------------------------

--
-- Структура таблицы `post_media`
--

CREATE TABLE `post_media` (
  `id` bigint UNSIGNED NOT NULL,
  `post_id` bigint UNSIGNED NOT NULL,
  `media_type` enum('image','video') COLLATE utf8mb4_general_ci NOT NULL,
  `media_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `position` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `post_media`
--

INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES
(1, 6, 'image', 'uploads/posts/6854720ca7d93b2c7b06c4804a5e64b7.png', 1, '2026-04-16 18:57:40'),
(2, 7, 'image', 'uploads/posts/ee4acbf8934fcdd6a6003368c5bed612.jpg', 1, '2026-04-20 13:39:02'),
(3, 8, 'video', 'uploads/posts/c509476f351de5ec46483472444fdda4.mp4', 1, '2026-04-20 16:13:06'),
(4, 9, 'image', 'uploads/posts/9a6abc74244d25d0202f96dd60770475.png', 1, '2026-04-20 16:37:48'),
(5, 10, 'image', 'uploads/posts/e83db0b20fa22adac6c04632c8edc012.png', 1, '2026-04-21 17:36:51'),
(6, 11, 'image', 'uploads/posts/3b2f914bcc72e0e15625f7125c6c181f.png', 1, '2026-04-21 17:42:16');

-- --------------------------------------------------------

--
-- Структура таблицы `reposts`
--

CREATE TABLE `reposts` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `post_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `reposts`
--

INSERT INTO `reposts` (`id`, `user_id`, `post_id`, `created_at`) VALUES
(1, 5, 9, '2026-04-21 15:07:17'),
(2, 5, 8, '2026-04-21 15:07:34'),
(3, 6, 9, '2026-04-21 16:48:57'),
(4, 6, 8, '2026-04-21 16:49:00'),
(5, 7, 9, '2026-04-21 17:35:13'),
(6, 7, 8, '2026-04-23 09:25:31'),
(7, 7, 11, '2026-04-23 09:25:42'),
(8, 5, 11, '2026-04-23 13:07:36');

-- --------------------------------------------------------

--
-- Структура таблицы `saved_posts`
--

CREATE TABLE `saved_posts` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `post_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `saved_posts`
--

INSERT INTO `saved_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES
(2, 6, 7, '2026-04-20 13:42:45'),
(3, 5, 6, '2026-04-20 14:18:22'),
(4, 5, 7, '2026-04-20 14:18:24'),
(7, 5, 9, '2026-04-21 16:43:46'),
(10, 7, 8, '2026-04-23 10:11:16');

-- --------------------------------------------------------

--
-- Структура таблицы `sessions`
--

CREATE TABLE `sessions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `login` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `birth_date` date NOT NULL,
  `gender` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `city` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT '0',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_general_ci,
  `background_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `role` enum('user','admin') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `login`, `email`, `birth_date`, `gender`, `city`, `is_private`, `password`, `avatar`, `bio`, `background_image`, `website`, `is_active`, `created_at`, `updated_at`, `role`) VALUES
(5, 'omg', 'OMG@gmail.com', '2010-02-02', 'Другой', 'мяукутс', 0, '$2y$10$Je6rl0HiLCPnXZnvJYysP.9A1BvEtpgH1uy6si37h8ZfljAQFZm8a', 'uploads/profile/avatar_05f5800e5597b058fc3f022f.jpg', 'меов', 'uploads/profile/background_50197b8107e20fc947565994.jpg', 'http://snapix/profile.php', 1, '2026-04-11 19:22:51', '2026-04-20 19:18:05', 'admin'),
(6, 's', 's@gmail.com', '1007-02-02', NULL, NULL, 0, '$2y$10$o1pbbSnLB1.Hzm0OTA.U4.GyVnr6Tij9KfpvVYbZnh3xq5YtVZE36', 'uploads/profile/avatar_7bcf2dca5bd7a412b0eafb04.jpg', '', 'uploads/profile/background_c45bc0daaacb7a034ce2d7f4.png', NULL, 1, '2026-04-20 13:36:26', '2026-04-20 13:37:11', 'user'),
(7, 'pashka-durashka', 'pashka@gmail.com', '2008-02-02', NULL, NULL, 0, '$2y$10$IILTqw/kiYP1HV2LYMpp0ub9qlsT3I423y59ojnTrZJRrs37m2zaC', NULL, NULL, NULL, NULL, 1, '2026-04-21 17:33:54', '2026-04-21 17:33:54', 'user');

-- --------------------------------------------------------

--
-- Структура таблицы `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `report_id` bigint UNSIGNED DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `user_id`, `report_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 6, NULL, 'Комментарий удалён', 'Комментарий удалён администратором за нарушение правил.\nПричина: Разжигание ненависти.\nПредупреждение: при следующем нарушении аккаунт будет удалён.', 1, '2026-04-20 19:18:41');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_chats_user_pair` (`user_one_id`,`user_two_id`),
  ADD KEY `idx_chats_user_two` (`user_two_id`);

--
-- Индексы таблицы `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_comments_post_id` (`post_id`),
  ADD KEY `idx_comments_user_id` (`user_id`);

--
-- Индексы таблицы `followers`
--
ALTER TABLE `followers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_follow` (`follower_id`,`following_id`),
  ADD KEY `idx_followers_follower_id` (`follower_id`),
  ADD KEY `idx_followers_following_id` (`following_id`);

--
-- Индексы таблицы `follow_requests`
--
ALTER TABLE `follow_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_follow_request` (`sender_id`,`receiver_id`),
  ADD KEY `idx_follow_requests_sender` (`sender_id`),
  ADD KEY `idx_follow_requests_receiver` (`receiver_id`);

--
-- Индексы таблицы `hidden_posts`
--
ALTER TABLE `hidden_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hidden_post` (`user_id`,`post_id`),
  ADD KEY `idx_hidden_posts_user_id` (`user_id`),
  ADD KEY `idx_hidden_posts_post_id` (`post_id`);

--
-- Индексы таблицы `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_like` (`user_id`,`post_id`),
  ADD KEY `idx_likes_user_id` (`user_id`),
  ADD KEY `idx_likes_post_id` (`post_id`);

--
-- Индексы таблицы `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_chat_created` (`chat_id`,`created_at`),
  ADD KEY `idx_messages_sender_created` (`sender_id`,`created_at`),
  ADD KEY `idx_messages_chat_read` (`chat_id`,`is_read`),
  ADD KEY `idx_messages_post` (`post_id`),
  ADD KEY `idx_messages_reply` (`reply_to_message_id`),
  ADD KEY `idx_messages_forwarded_from` (`forwarded_from_message_id`);

--
-- Индексы таблицы `message_hidden`
--
ALTER TABLE `message_hidden`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_message_hidden` (`message_id`,`user_id`),
  ADD KEY `idx_message_hidden_user_id` (`user_id`);

--
-- Индексы таблицы `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_message_reaction` (`message_id`,`user_id`),
  ADD KEY `idx_message_reactions_message` (`message_id`),
  ADD KEY `idx_message_reactions_user` (`user_id`);

--
-- Индексы таблицы `moderation_reasons`
--
ALTER TABLE `moderation_reasons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_moderation_reasons_code` (`code`);

--
-- Индексы таблицы `moderation_reports`
--
ALTER TABLE `moderation_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reports_reporter` (`reporter_user_id`),
  ADD KEY `idx_reports_target_user` (`target_user_id`),
  ADD KEY `idx_reports_target_comment` (`target_comment_id`),
  ADD KEY `idx_reports_reason` (`reason_id`),
  ADD KEY `idx_reports_status_created` (`status`,`created_at`);

--
-- Индексы таблицы `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_password_reset_user_id` (`user_id`);

--
-- Индексы таблицы `pinned_posts`
--
ALTER TABLE `pinned_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pinned_post` (`user_id`,`post_id`),
  ADD KEY `idx_pinned_posts_user_id` (`user_id`),
  ADD KEY `idx_pinned_posts_post_id` (`post_id`);

--
-- Индексы таблицы `pinned_messages`
--
ALTER TABLE `pinned_messages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pinned_message` (`chat_id`,`message_id`),
  ADD KEY `idx_pinned_messages_chat_id` (`chat_id`),
  ADD KEY `idx_pinned_messages_message_id` (`message_id`),
  ADD KEY `idx_pinned_messages_user_id` (`pinned_by_user_id`);

--
-- Индексы таблицы `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_posts_user_id` (`user_id`);

--
-- Индексы таблицы `post_media`
--
ALTER TABLE `post_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_post_media_post_id` (`post_id`);

--
-- Индексы таблицы `reposts`
--
ALTER TABLE `reposts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reposts_post` (`post_id`),
  ADD KEY `fk_reposts_user` (`user_id`);

--
-- Индексы таблицы `saved_posts`
--
ALTER TABLE `saved_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_saved_post` (`user_id`,`post_id`),
  ADD KEY `idx_saved_posts_user_id` (`user_id`),
  ADD KEY `idx_saved_posts_post_id` (`post_id`);

--
-- Индексы таблицы `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_sessions_user_id` (`user_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`login`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Индексы таблицы `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`is_read`,`created_at`),
  ADD KEY `idx_notifications_report` (`report_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `chats`
--
ALTER TABLE `chats`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `comments`
--
ALTER TABLE `comments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `followers`
--
ALTER TABLE `followers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `follow_requests`
--
ALTER TABLE `follow_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `hidden_posts`
--
ALTER TABLE `hidden_posts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `likes`
--
ALTER TABLE `likes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT для таблицы `message_hidden`
--
ALTER TABLE `message_hidden`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `message_reactions`
--
ALTER TABLE `message_reactions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `moderation_reasons`
--
ALTER TABLE `moderation_reasons`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12019;

--
-- AUTO_INCREMENT для таблицы `moderation_reports`
--
ALTER TABLE `moderation_reports`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `pinned_posts`
--
ALTER TABLE `pinned_posts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `pinned_messages`
--
ALTER TABLE `pinned_messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `posts`
--
ALTER TABLE `posts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `post_media`
--
ALTER TABLE `post_media`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `reposts`
--
ALTER TABLE `reposts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `saved_posts`
--
ALTER TABLE `saved_posts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `chats`
--
ALTER TABLE `chats`
  ADD CONSTRAINT `fk_chats_user_one` FOREIGN KEY (`user_one_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chats_user_two` FOREIGN KEY (`user_two_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `followers`
--
ALTER TABLE `followers`
  ADD CONSTRAINT `fk_followers_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_followers_following` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `follow_requests`
--
ALTER TABLE `follow_requests`
  ADD CONSTRAINT `fk_follow_requests_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_follow_requests_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `hidden_posts`
--
ALTER TABLE `hidden_posts`
  ADD CONSTRAINT `fk_hidden_posts_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hidden_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `fk_likes_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_chat` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_messages_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_messages_reply_to` FOREIGN KEY (`reply_to_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_messages_forwarded_from` FOREIGN KEY (`forwarded_from_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `message_hidden`
--
ALTER TABLE `message_hidden`
  ADD CONSTRAINT `fk_message_hidden_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_message_hidden_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD CONSTRAINT `fk_message_reactions_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_message_reactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `moderation_reports`
--
ALTER TABLE `moderation_reports`
  ADD CONSTRAINT `fk_reports_reason` FOREIGN KEY (`reason_id`) REFERENCES `moderation_reasons` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reports_target_comment` FOREIGN KEY (`target_comment_id`) REFERENCES `comments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reports_target_user` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `pinned_posts`
--
ALTER TABLE `pinned_posts`
  ADD CONSTRAINT `fk_pinned_posts_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pinned_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `pinned_messages`
--
ALTER TABLE `pinned_messages`
  ADD CONSTRAINT `fk_pinned_messages_chat` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pinned_messages_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pinned_messages_user` FOREIGN KEY (`pinned_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `post_media`
--
ALTER TABLE `post_media`
  ADD CONSTRAINT `fk_post_media_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `reposts`
--
ALTER TABLE `reposts`
  ADD CONSTRAINT `fk_reposts_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reposts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `saved_posts`
--
ALTER TABLE `saved_posts`
  ADD CONSTRAINT `fk_saved_posts_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_saved_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `fk_notifications_report` FOREIGN KEY (`report_id`) REFERENCES `moderation_reports` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
