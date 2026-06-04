-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Май 05 2026 г., 16:45
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
(1, 6, 7, '2026-04-23 12:41:58', '2026-05-04 13:34:35'),
(2, 5, 6, '2026-04-23 12:45:56', '2026-05-04 13:36:04'),
(3, 5, 7, '2026-04-23 12:49:46', '2026-04-30 17:05:34');

-- --------------------------------------------------------

--
-- Структура таблицы `comments`
--

CREATE TABLE `comments` (
  `id` bigint UNSIGNED NOT NULL,
  `post_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `comment_text` text COLLATE utf8mb4_general_ci NOT NULL,
  `attachment_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `attachment_type` enum('image','gif') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('published','pending_review','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'published',
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
(12, 8, 7, 's', 0, '2026-04-23 09:25:25', '2026-04-23 09:25:25'),
(13, 11, 7, 'ььб', 0, '2026-04-29 15:01:33', '2026-04-29 15:01:33'),
(14, 12, 5, 'о как', 0, '2026-05-04 13:38:33', '2026-05-04 13:38:33');

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
(6, 5, 7, 'accepted', NULL, '2026-05-04 13:39:06');

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
(12, 7, 11, '2026-04-29 15:01:27'),
(14, 5, 12, '2026-05-04 13:38:28');

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

INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES
(1, 1, 7, 'привет', NULL, NULL, NULL, 1, 0, '2026-04-23 12:42:04', NULL),
(2, 1, 7, 'как дела', NULL, NULL, NULL, 1, 0, '2026-04-23 12:42:09', NULL),
(3, 1, 6, 'норм', NULL, NULL, NULL, 1, 0, '2026-04-23 12:42:44', NULL),
(4, 2, 5, 'але', NULL, NULL, NULL, 1, 0, '2026-04-23 12:46:02', NULL),
(5, 2, 6, 'але', NULL, NULL, NULL, 1, 0, '2026-04-23 12:46:14', NULL),
(6, 2, 6, 'РЕАЛЬНО РАБОТАЕТ', NULL, NULL, NULL, 1, 0, '2026-04-23 12:46:41', NULL),
(7, 2, 6, 'задержка 3 секунды', NULL, NULL, NULL, 1, 0, '2026-04-23 12:46:53', NULL),
(8, 3, 5, '???', NULL, NULL, NULL, 1, 0, '2026-04-23 12:49:50', NULL),
(9, 2, 5, 'ffff', NULL, NULL, NULL, 1, 0, '2026-04-23 12:51:18', NULL),
(10, 2, 6, 'sssss', NULL, NULL, NULL, 1, 0, '2026-04-23 12:51:34', NULL),
(11, 2, 5, 'привет', NULL, NULL, NULL, 1, 0, '2026-04-23 13:01:32', NULL),
(12, 2, 6, 'я крутая', NULL, NULL, NULL, 1, 0, '2026-04-23 13:02:20', NULL),
(13, 2, 6, 'оооо', NULL, NULL, NULL, 1, 0, '2026-04-23 13:02:27', NULL),
(14, 2, 6, 'ооо', NULL, NULL, NULL, 1, 0, '2026-04-23 13:02:34', NULL),
(15, 2, 6, 'ооо', NULL, NULL, NULL, 1, 0, '2026-04-23 13:02:45', NULL),
(22, 2, 5, '[post_share]|11|7|pashka-durashka', 11, NULL, NULL, 1, 0, '2026-04-26 11:28:00', NULL),
(23, 2, 5, '[post_share]|11|7|pashka-durashka', 11, NULL, NULL, 1, 0, '2026-04-26 11:38:13', NULL),
(24, 3, 5, '[post_share]|11|7|pashka-durashka', 11, NULL, NULL, 1, 0, '2026-04-26 11:42:46', NULL),
(25, 2, 5, 'НАКОНЕЦ-ТО', NULL, NULL, NULL, 1, 0, '2026-04-26 11:45:25', NULL),
(26, 3, 7, 'ghbdtn', NULL, NULL, NULL, 1, 0, '2026-04-26 14:02:58', NULL),
(27, 3, 7, 'и че', NULL, NULL, NULL, 1, 0, '2026-04-26 14:03:05', NULL),
(28, 3, 5, 'вв', NULL, NULL, NULL, 1, 0, '2026-04-26 14:03:42', NULL),
(29, 3, 5, 'вебсокет работает?', NULL, NULL, NULL, 1, 0, '2026-04-26 14:04:22', NULL),
(30, 3, 7, 'нет ошибка event', NULL, NULL, NULL, 1, 0, '2026-04-26 14:09:34', NULL),
(31, 3, 7, 'обидно', NULL, NULL, NULL, 1, 0, '2026-04-26 14:09:40', NULL),
(32, 3, 5, 'это да', NULL, NULL, NULL, 1, 0, '2026-04-26 16:21:01', NULL),
(33, 3, 5, 'а', NULL, NULL, NULL, 1, 0, '2026-04-26 16:23:47', NULL),
(34, 3, 7, 'б', NULL, NULL, NULL, 1, 0, '2026-04-26 16:24:01', NULL),
(35, 3, 5, 'в', NULL, NULL, NULL, 1, 0, '2026-04-26 16:24:03', NULL),
(36, 3, 5, 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, NULL, NULL, 1, 0, '2026-04-26 16:24:11', NULL),
(37, 3, 7, 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, NULL, NULL, 1, 0, '2026-04-26 16:41:03', NULL),
(38, 3, 5, 'в', NULL, NULL, NULL, 1, 0, '2026-04-26 16:41:22', NULL),
(39, 3, 7, 'ы', NULL, NULL, NULL, 1, 0, '2026-04-26 16:41:43', NULL),
(40, 3, 7, 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, NULL, NULL, 1, 0, '2026-04-26 16:41:52', NULL),
(41, 3, 7, 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, NULL, NULL, 1, 0, '2026-04-26 16:45:43', NULL),
(42, 3, 5, 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, NULL, NULL, 1, 0, '2026-04-26 16:45:47', NULL),
(43, 2, 5, '[post_share]|11|7|pashka-durashka', 11, NULL, NULL, 1, 0, '2026-04-28 10:47:07', NULL),
(44, 3, 5, 'привет', NULL, NULL, NULL, 1, 0, '2026-04-28 10:47:47', NULL),
(45, 3, 5, 'сообщение изменено', NULL, NULL, NULL, 1, 0, '2026-04-28 10:48:35', '2026-04-28 15:16:26'),
(46, 3, 5, 'Сообщение удалено', NULL, 45, NULL, 1, 1, '2026-04-28 15:17:13', NULL),
(47, 2, 5, 'сообщение изменено', NULL, NULL, 45, 1, 0, '2026-04-28 15:18:13', NULL),
(48, 2, 5, 'fggfg', NULL, NULL, NULL, 1, 0, '2026-04-29 12:20:48', NULL),
(49, 3, 5, 'аааааа', NULL, NULL, NULL, 1, 0, '2026-04-29 12:21:09', NULL),
(50, 3, 7, 'ааа', NULL, NULL, NULL, 1, 0, '2026-04-29 12:21:18', NULL),
(51, 3, 5, 'вввв', NULL, NULL, NULL, 1, 0, '2026-04-29 12:24:36', NULL),
(52, 3, 7, 'в', NULL, NULL, NULL, 1, 0, '2026-04-29 12:44:38', NULL),
(53, 3, 5, 'не работает снова', NULL, NULL, NULL, 1, 0, '2026-04-29 12:45:19', NULL),
(54, 3, 7, 'ага', NULL, NULL, NULL, 1, 0, '2026-04-29 12:45:23', NULL),
(55, 3, 5, 'так', NULL, NULL, NULL, 1, 0, '2026-04-29 12:45:41', NULL),
(56, 3, 7, 'с большими сообщениями может только работает', NULL, NULL, NULL, 1, 0, '2026-04-29 12:46:41', NULL),
(57, 3, 5, 'привет', NULL, NULL, NULL, 1, 0, '2026-04-29 12:48:02', NULL),
(58, 3, 7, 'привет', NULL, NULL, NULL, 1, 0, '2026-04-29 12:48:10', NULL),
(59, 3, 5, 'работает?', NULL, NULL, NULL, 1, 0, '2026-04-29 12:52:13', NULL),
(60, 3, 7, 'ну конечно', NULL, NULL, NULL, 1, 0, '2026-04-29 12:52:18', NULL),
(61, 3, 7, 'не работает', NULL, NULL, NULL, 1, 0, '2026-04-29 13:02:11', NULL),
(62, 3, 5, 'не работает', NULL, NULL, NULL, 1, 0, '2026-04-29 13:02:19', NULL),
(63, 3, 5, 'аааа', NULL, NULL, NULL, 1, 0, '2026-04-29 13:02:22', NULL),
(64, 3, 5, 'ааа', NULL, NULL, NULL, 1, 0, '2026-04-29 13:02:27', NULL),
(65, 3, 5, '<?php session_start(); require \'./config/config.php\';  header(\'Content-Type: application/json; charset=utf-8\');  if (!isset($_SESSION[\'user_id\'])) {     http_response_code(401);     echo json_encode([\'ok\' => false, \'error\' => \'auth_required\']);     exit; }  $currentUserId = (int) $_SESSION[\'user_id\'];  function getDialogs(PDO $pdo, int $userId): array {     $dialogsStmt = $pdo->prepare(\'         SELECT             chats.id,             partner.id AS partner_id,             partner.login AS partner_login,             partner.avatar AS partner_avatar,             latest.message_text AS last_message,             latest.created_at AS last_message_created_at,             (                 SELECT COUNT(*)                 FROM messages unread                 WHERE unread.chat_id = chats.id                   AND unread.sender_id != :user_id                   AND unread.is_read = 0             ) AS unread_count         FROM chats         INNER JOIN users AS partner ON partner.id = IF(chats.user', NULL, NULL, NULL, 1, 0, '2026-04-29 13:03:20', NULL),
(66, 3, 7, 'пппппппппппппп', NULL, NULL, NULL, 1, 0, '2026-04-29 13:03:48', NULL),
(67, 3, 5, 'ппппппппппппппп', NULL, NULL, NULL, 1, 0, '2026-04-29 13:14:59', NULL),
(68, 3, 7, 'зззззззззззззззззззз', NULL, NULL, NULL, 1, 0, '2026-04-29 13:15:06', NULL),
(69, 3, 5, 'фффффффффффффффффффффффффффффффффффффффффффф', NULL, NULL, NULL, 1, 0, '2026-04-29 14:55:53', NULL),
(70, 3, 5, 'в', NULL, NULL, NULL, 1, 0, '2026-04-29 14:56:44', NULL),
(71, 3, 7, 'в', NULL, NULL, NULL, 1, 0, '2026-04-29 14:57:01', NULL),
(72, 3, 7, 'а почему не сразу присылается', NULL, NULL, NULL, 1, 0, '2026-04-29 14:57:12', NULL),
(73, 3, 7, 'хз', NULL, NULL, NULL, 1, 0, '2026-04-29 14:57:22', NULL),
(74, 2, 5, '[post_share]|11|7|pashka-durashka', 11, NULL, NULL, 1, 0, '2026-04-29 15:06:11', NULL),
(75, 2, 6, 'привет', NULL, NULL, NULL, 1, 0, '2026-04-29 15:21:46', NULL),
(76, 2, 5, 'привет', NULL, NULL, NULL, 1, 0, '2026-04-29 15:21:52', NULL),
(77, 2, 6, 'и что опять', NULL, NULL, NULL, 1, 0, '2026-04-29 15:22:27', NULL),
(78, 2, 5, 'И ГДЕ', NULL, NULL, NULL, 1, 0, '2026-04-29 15:29:04', NULL),
(79, 2, 5, 'привет', NULL, NULL, NULL, 1, 0, '2026-04-29 15:47:27', NULL),
(80, 2, 6, 'привет', NULL, NULL, NULL, 1, 0, '2026-04-29 15:47:51', NULL),
(81, 2, 6, 'УРРААА', NULL, NULL, NULL, 1, 0, '2026-04-29 15:47:55', NULL),
(82, 2, 6, 'БОЖЕ', NULL, NULL, NULL, 1, 0, '2026-04-29 15:47:56', NULL),
(83, 2, 5, 'РАБОТАЕТ СНОВА', NULL, NULL, NULL, 1, 0, '2026-04-29 15:48:04', NULL),
(84, 1, 7, 'не норм', NULL, NULL, NULL, 1, 0, '2026-04-30 14:42:52', NULL),
(85, 1, 6, 'норм', NULL, NULL, NULL, 1, 0, '2026-04-30 14:43:02', NULL),
(86, 1, 6, 'да что такое то', NULL, NULL, NULL, 1, 0, '2026-04-30 14:43:11', NULL),
(87, 1, 7, 'хз', NULL, NULL, NULL, 1, 0, '2026-04-30 14:43:24', NULL),
(88, 1, 6, 'работай пожалуйста', NULL, NULL, NULL, 1, 0, '2026-04-30 14:44:12', NULL),
(89, 1, 7, 'я сейчас плакать буду', NULL, NULL, NULL, 1, 0, '2026-04-30 14:44:23', NULL),
(90, 1, 6, 'а', NULL, NULL, NULL, 1, 0, '2026-04-30 14:45:41', NULL),
(91, 1, 7, 'что ж ты вечно ломаешься', NULL, NULL, NULL, 1, 0, '2026-04-30 14:46:00', NULL),
(92, 2, 5, 'проверка', NULL, NULL, NULL, 1, 0, '2026-04-30 14:46:50', NULL),
(93, 2, 6, 'проверка', NULL, NULL, NULL, 1, 0, '2026-04-30 14:46:55', NULL),
(94, 2, 6, 'так ну', NULL, NULL, NULL, 1, 0, '2026-04-30 14:48:33', NULL),
(95, 2, 6, 'это дурдо', NULL, NULL, NULL, 1, 0, '2026-04-30 14:48:39', NULL),
(96, 2, 5, 'м', NULL, NULL, NULL, 1, 0, '2026-04-30 14:48:49', NULL),
(97, 2, 5, 'полнейший', NULL, NULL, NULL, 1, 0, '2026-04-30 14:48:55', NULL),
(98, 2, 6, 'ТАК', NULL, NULL, NULL, 1, 0, '2026-04-30 14:50:57', NULL),
(99, 2, 6, 'и что', NULL, NULL, NULL, 1, 0, '2026-04-30 14:51:02', NULL),
(100, 2, 6, 'ничего', NULL, NULL, NULL, 1, 0, '2026-04-30 14:56:19', NULL),
(101, 2, 5, 'и правда ничего', NULL, NULL, NULL, 1, 0, '2026-04-30 14:56:27', NULL),
(102, 2, 6, 'привет', NULL, NULL, NULL, 1, 0, '2026-04-30 15:14:10', NULL),
(103, 2, 5, 'привет', NULL, NULL, NULL, 1, 0, '2026-04-30 15:14:14', NULL),
(104, 3, 5, 'аааааааааааааааааа', NULL, NULL, NULL, 1, 0, '2026-04-30 15:39:02', NULL),
(105, 3, 7, 'аааааааааааааааааааааааааа', NULL, NULL, NULL, 1, 0, '2026-04-30 15:39:08', NULL),
(106, 3, 5, 'аааааааааааааааааааааааааааа', NULL, NULL, NULL, 1, 0, '2026-04-30 15:39:13', NULL),
(107, 3, 5, 'НУ ПОЖАЛУЙСТА', NULL, NULL, NULL, 1, 0, '2026-04-30 15:40:14', NULL),
(108, 3, 5, 'помогите', NULL, NULL, NULL, 1, 0, '2026-04-30 15:45:42', NULL),
(109, 3, 7, 'помогите', NULL, NULL, NULL, 1, 0, '2026-04-30 15:46:13', NULL),
(110, 3, 7, 'gjvjubnt', NULL, NULL, NULL, 1, 0, '2026-04-30 15:59:13', NULL),
(111, 3, 5, 'помогите', NULL, NULL, NULL, 1, 0, '2026-04-30 15:59:23', NULL),
(112, 3, 7, 'помогите', NULL, NULL, NULL, 1, 0, '2026-04-30 15:59:29', NULL),
(113, 3, 7, 'ну и что', NULL, NULL, NULL, 1, 0, '2026-04-30 15:59:41', NULL),
(114, 3, 7, 'что не так то', NULL, NULL, NULL, 1, 0, '2026-04-30 15:59:44', NULL),
(115, 3, 5, 'не знаю', NULL, NULL, NULL, 1, 0, '2026-04-30 16:08:06', NULL),
(116, 3, 7, 'але', NULL, NULL, NULL, 1, 0, '2026-04-30 16:08:16', NULL),
(117, 3, 5, 'але', NULL, NULL, NULL, 1, 0, '2026-04-30 16:11:28', NULL),
(118, 3, 5, 'мяу', NULL, NULL, NULL, 1, 0, '2026-04-30 16:18:21', NULL),
(119, 3, 5, 'привет', NULL, NULL, NULL, 1, 0, '2026-04-30 16:22:59', NULL),
(120, 3, 7, 'ну привет', NULL, NULL, NULL, 1, 0, '2026-04-30 16:23:17', NULL),
(121, 3, 7, 'ну наконец-то', NULL, NULL, NULL, 1, 0, '2026-04-30 16:23:24', NULL),
(122, 3, 5, 'ага', NULL, NULL, NULL, 1, 0, '2026-04-30 17:05:32', NULL),
(123, 3, 7, 'ага', NULL, NULL, NULL, 1, 0, '2026-04-30 17:05:34', NULL),
(124, 2, 6, 'адвда', NULL, NULL, NULL, 1, 0, '2026-05-04 12:03:17', NULL),
(125, 2, 5, 'авадад', NULL, NULL, NULL, 1, 0, '2026-05-04 12:03:20', NULL),
(126, 1, 7, 'м', NULL, NULL, NULL, 1, 0, '2026-05-04 12:07:33', NULL),
(127, 1, 7, 'в', NULL, NULL, NULL, 1, 0, '2026-05-04 12:07:54', NULL),
(128, 1, 6, 'а', NULL, NULL, NULL, 1, 0, '2026-05-04 12:07:55', NULL),
(129, 1, 6, 'в', NULL, NULL, NULL, 1, 0, '2026-05-04 12:09:03', NULL),
(130, 1, 7, 'ч', NULL, NULL, NULL, 1, 0, '2026-05-04 12:09:06', NULL),
(131, 1, 6, 'а', NULL, NULL, NULL, 0, 0, '2026-05-04 13:34:35', NULL),
(132, 2, 5, 'в', NULL, NULL, NULL, 1, 0, '2026-05-04 13:34:51', NULL),
(133, 2, 6, 'а', NULL, NULL, NULL, 1, 0, '2026-05-04 13:34:53', NULL),
(134, 2, 5, 'что не так то', NULL, NULL, 114, 0, 0, '2026-05-04 13:35:45', NULL),
(135, 2, 5, '???', NULL, NULL, 8, 0, 0, '2026-05-04 13:36:04', NULL);

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

--
-- Дамп данных таблицы `message_hidden`
--

INSERT INTO `message_hidden` (`id`, `message_id`, `user_id`, `created_at`) VALUES
(1, 46, 5, '2026-04-28 15:19:56'),
(2, 44, 5, '2026-04-28 15:20:30'),
(3, 46, 7, '2026-04-29 12:19:11');

-- --------------------------------------------------------

--
-- Структура таблицы `message_reactions`
--

CREATE TABLE `message_reactions` (
  `id` bigint UNSIGNED NOT NULL,
  `message_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `reaction` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `message_reactions`
--

INSERT INTO `message_reactions` (`id`, `message_id`, `user_id`, `reaction`, `created_at`) VALUES
(1, 43, 5, '😂', '2026-04-28 16:48:34'),
(2, 25, 5, '😮', '2026-04-28 16:48:37'),
(3, 45, 5, '❤️', '2026-04-28 17:05:50'),
(9, 114, 5, '🔥', '2026-05-04 13:35:39');

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

-- --------------------------------------------------------

--
-- Структура таблицы `moderation_queue`
--

CREATE TABLE `moderation_queue` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `comment_id` bigint UNSIGNED NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `moderator_id` bigint UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_moderation_queue_comment` (`comment_id`),
  KEY `idx_moderation_queue_status` (`status`,`created_at`),
  CONSTRAINT `fk_moderation_queue_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Дамп данных таблицы `pinned_messages`
--

INSERT INTO `pinned_messages` (`id`, `chat_id`, `message_id`, `pinned_by_user_id`, `created_at`) VALUES
(1, 3, 46, 5, '2026-04-28 15:18:06'),
(2, 2, 74, 5, '2026-04-29 15:07:26');

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
(11, 7, NULL, 0, '2026-04-21 17:42:16', '2026-04-21 17:42:16'),
(12, 5, 'Учусь', 0, '2026-05-04 13:37:18', '2026-05-04 13:37:18');

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
(6, 11, 'image', 'uploads/posts/3b2f914bcc72e0e15625f7125c6c181f.png', 1, '2026-04-21 17:42:16'),
(7, 12, 'image', 'uploads/posts/7b6674231a2d22fe1ec155eb606e59fe.png', 1, '2026-05-04 13:37:18');

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
(5, 7, 9, '2026-04-21 17:35:13'),
(12, 5, 11, '2026-04-29 18:31:06'),
(13, 6, 11, '2026-04-29 18:32:01'),
(14, 7, 11, '2026-05-04 12:09:28');

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
(10, 7, 8, '2026-04-23 10:11:16'),
(14, 5, 12, '2026-05-04 13:38:24');

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
  `actor_user_id` bigint UNSIGNED DEFAULT NULL,
  `target_user_id` bigint UNSIGNED DEFAULT NULL,
  `notification_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `post_id` bigint UNSIGNED DEFAULT NULL,
  `comment_id` bigint UNSIGNED DEFAULT NULL,
  `report_id` bigint UNSIGNED DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `comment_text` text COLLATE utf8mb4_general_ci,
  `report_reason` varchar(1000) COLLATE utf8mb4_general_ci DEFAULT NULL,
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
-- Индексы таблицы `pinned_messages`
--
ALTER TABLE `pinned_messages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pinned_message` (`chat_id`,`message_id`),
  ADD KEY `idx_pinned_messages_chat_id` (`chat_id`),
  ADD KEY `idx_pinned_messages_message_id` (`message_id`),
  ADD KEY `idx_pinned_messages_user_id` (`pinned_by_user_id`);

--
-- Индексы таблицы `pinned_posts`
--
ALTER TABLE `pinned_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pinned_post` (`user_id`,`post_id`),
  ADD KEY `idx_pinned_posts_user_id` (`user_id`),
  ADD KEY `idx_pinned_posts_post_id` (`post_id`);

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
  ADD KEY `idx_notifications_report` (`report_id`),
  ADD KEY `idx_notifications_type_post` (`notification_type`,`post_id`,`created_at`),
  ADD KEY `idx_notifications_actor` (`actor_user_id`),
  ADD KEY `idx_notifications_target` (`target_user_id`),
  ADD KEY `idx_notifications_post` (`post_id`),
  ADD KEY `idx_notifications_comment` (`comment_id`);

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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT для таблицы `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- AUTO_INCREMENT для таблицы `message_hidden`
--
ALTER TABLE `message_hidden`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `message_reactions`
--
ALTER TABLE `message_reactions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `moderation_reasons`
--
ALTER TABLE `moderation_reasons`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17529;

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
-- AUTO_INCREMENT для таблицы `pinned_messages`
--
ALTER TABLE `pinned_messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `pinned_posts`
--
ALTER TABLE `pinned_posts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `posts`
--
ALTER TABLE `posts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `post_media`
--
ALTER TABLE `post_media`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `reposts`
--
ALTER TABLE `reposts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT для таблицы `saved_posts`
--
ALTER TABLE `saved_posts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

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
  ADD CONSTRAINT `fk_messages_forwarded_from` FOREIGN KEY (`forwarded_from_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_messages_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_messages_reply_to` FOREIGN KEY (`reply_to_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
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
  ADD CONSTRAINT `message_reactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `message_reactions_ibfk_2` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

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
-- Ограничения внешнего ключа таблицы `pinned_messages`
--
ALTER TABLE `pinned_messages`
  ADD CONSTRAINT `fk_pinned_messages_chat` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pinned_messages_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pinned_messages_user` FOREIGN KEY (`pinned_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `pinned_posts`
--
ALTER TABLE `pinned_posts`
  ADD CONSTRAINT `fk_pinned_posts_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pinned_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
