-- Snapix database backup
-- Created at: 2026-06-09 04:03:42

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `admin_logs`;
CREATE TABLE `admin_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `target_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `target_id` bigint unsigned DEFAULT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `ip_address` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_logs_created_at` (`created_at`),
  KEY `idx_admin_logs_action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admin_logs` (`id`, `admin_user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('1', '5', 'export_logs', 'admin_logs', NULL, '', '127.0.0.1', '2026-06-09 04:01:54');
INSERT INTO `admin_logs` (`id`, `admin_user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('2', '5', 'export_logs', 'admin_logs', NULL, '', '127.0.0.1', '2026-06-09 04:03:34');

DROP TABLE IF EXISTS `chats`;
CREATE TABLE `chats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_one_id` bigint unsigned NOT NULL,
  `user_two_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chats_user_pair` (`user_one_id`,`user_two_id`),
  KEY `idx_chats_user_two` (`user_two_id`),
  CONSTRAINT `fk_chats_user_one` FOREIGN KEY (`user_one_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chats_user_two` FOREIGN KEY (`user_two_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `chats` (`id`, `user_one_id`, `user_two_id`, `created_at`, `updated_at`) VALUES ('1', '6', '7', '2026-04-23 15:41:58', '2026-05-22 00:13:47');
INSERT INTO `chats` (`id`, `user_one_id`, `user_two_id`, `created_at`, `updated_at`) VALUES ('2', '5', '6', '2026-04-23 15:45:56', '2026-06-08 22:39:11');
INSERT INTO `chats` (`id`, `user_one_id`, `user_two_id`, `created_at`, `updated_at`) VALUES ('3', '5', '7', '2026-04-23 15:49:46', '2026-06-07 19:56:25');

DROP TABLE IF EXISTS `clips_post_settings`;
CREATE TABLE `clips_post_settings` (
  `post_id` bigint unsigned NOT NULL,
  `comments_closed` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `clips_post_settings` (`post_id`, `comments_closed`, `updated_at`) VALUES ('8', '1', '2026-06-07 21:48:55');
INSERT INTO `clips_post_settings` (`post_id`, `comments_closed`, `updated_at`) VALUES ('17', '1', '2026-06-07 23:52:07');

DROP TABLE IF EXISTS `comment_likes`;
CREATE TABLE `comment_likes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comment_likes_comment_user` (`comment_id`,`user_id`),
  KEY `idx_comment_likes_user` (`user_id`),
  CONSTRAINT `fk_comment_likes_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comment_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint unsigned NOT NULL,
  `parent_comment_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `comment_text` text COLLATE utf8mb4_general_ci NOT NULL,
  `attachment_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `attachment_type` enum('image','gif') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('published','pending_review','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'published',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comments_post_id` (`post_id`),
  KEY `idx_comments_user_id` (`user_id`),
  KEY `idx_comments_parent` (`parent_comment_id`),
  KEY `idx_user_comments_parent` (`parent_comment_id`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_comments_parent` FOREIGN KEY (`parent_comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('1', '6', NULL, '6', 'омгд', NULL, NULL, '0', 'published', '2026-04-20 16:36:45', '2026-04-20 16:36:45');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('2', '6', NULL, '5', 'пиздючки', NULL, NULL, '1', 'published', '2026-04-20 16:38:13', '2026-05-20 14:40:43');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('3', '7', NULL, '6', 'сука', NULL, NULL, '1', 'published', '2026-04-20 16:40:05', '2026-04-20 22:18:41');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('4', '9', NULL, '6', 'ф', NULL, NULL, '0', 'published', '2026-04-21 20:08:06', '2026-04-21 20:08:06');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('5', '8', NULL, '6', 'ы', NULL, NULL, '0', 'published', '2026-04-21 20:08:15', '2026-04-21 20:08:15');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('6', '8', NULL, '6', 'ы', NULL, NULL, '0', 'published', '2026-04-21 20:08:17', '2026-04-21 20:08:17');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('7', '8', NULL, '6', 'в', NULL, NULL, '0', 'published', '2026-04-21 20:08:18', '2026-04-21 20:08:18');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('8', '8', NULL, '6', 'ы', NULL, NULL, '0', 'published', '2026-04-21 20:08:22', '2026-04-21 20:08:22');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('9', '8', NULL, '6', 'ы', NULL, NULL, '0', 'published', '2026-04-21 20:08:24', '2026-04-21 20:08:24');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('10', '8', NULL, '6', 'ы', NULL, NULL, '0', 'published', '2026-04-21 20:08:25', '2026-04-21 20:08:25');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('11', '8', NULL, '6', 'ы', NULL, NULL, '0', 'published', '2026-04-21 20:08:25', '2026-04-21 20:08:25');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('12', '8', NULL, '7', 's', NULL, NULL, '0', 'published', '2026-04-23 12:25:25', '2026-04-23 12:25:25');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('13', '11', NULL, '7', 'ььб', NULL, NULL, '0', 'published', '2026-04-29 18:01:33', '2026-04-29 18:01:33');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('14', '12', NULL, '5', 'о как', NULL, NULL, '0', 'published', '2026-05-04 16:38:33', '2026-05-04 16:38:33');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('15', '11', NULL, '7', 'в', NULL, NULL, '0', 'published', '2026-05-17 18:56:14', '2026-05-17 18:56:14');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('16', '12', NULL, '5', 'м', NULL, NULL, '0', 'published', '2026-05-17 20:06:23', '2026-05-17 20:06:23');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('17', '8', NULL, '7', 'аа', NULL, NULL, '0', 'published', '2026-05-20 15:13:13', '2026-05-20 15:13:13');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('18', '8', NULL, '7', 'аа', NULL, NULL, '0', 'published', '2026-05-20 15:13:13', '2026-05-20 15:13:13');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('19', '8', NULL, '7', 'аа', NULL, NULL, '0', 'published', '2026-05-20 15:13:13', '2026-05-20 15:13:13');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('20', '8', NULL, '7', 'аа', NULL, NULL, '0', 'published', '2026-05-20 15:13:13', '2026-05-20 15:13:13');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('21', '8', NULL, '7', 'аа', NULL, NULL, '0', 'published', '2026-05-20 15:13:13', '2026-05-20 15:13:13');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('22', '8', NULL, '7', 'аа', NULL, NULL, '0', 'published', '2026-05-20 15:13:13', '2026-05-20 15:13:13');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('23', '8', NULL, '7', 'аа', NULL, NULL, '0', 'published', '2026-05-20 15:13:13', '2026-05-20 15:13:13');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('24', '8', NULL, '7', 'а', NULL, NULL, '0', 'published', '2026-05-20 15:13:16', '2026-05-20 15:13:16');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('25', '8', NULL, '7', 'а', NULL, NULL, '0', 'published', '2026-05-20 15:13:16', '2026-05-20 15:13:16');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('26', '8', NULL, '7', 'а', NULL, NULL, '0', 'published', '2026-05-20 15:13:16', '2026-05-20 15:13:16');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('27', '8', NULL, '7', 'а', NULL, NULL, '0', 'published', '2026-05-20 15:13:16', '2026-05-20 15:13:16');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('28', '8', NULL, '7', 'а', NULL, NULL, '0', 'published', '2026-05-20 15:13:16', '2026-05-20 15:13:16');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('29', '8', NULL, '7', 'а', NULL, NULL, '0', 'published', '2026-05-20 15:13:16', '2026-05-20 15:13:16');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('30', '8', NULL, '7', 'а', NULL, NULL, '0', 'published', '2026-05-20 15:13:16', '2026-05-20 15:13:16');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('31', '8', NULL, '7', 'а', NULL, NULL, '0', 'published', '2026-05-20 15:13:32', '2026-05-20 15:13:32');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('32', '13', NULL, '7', 'в', NULL, NULL, '0', 'published', '2026-05-20 15:13:43', '2026-05-20 15:13:43');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('33', '13', NULL, '7', 'в', NULL, NULL, '0', 'published', '2026-05-20 15:13:43', '2026-05-20 15:13:43');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('34', '13', NULL, '7', 'а', NULL, NULL, '0', 'published', '2026-05-20 15:17:43', '2026-05-20 15:17:43');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('35', '13', NULL, '7', 'в', NULL, NULL, '0', 'published', '2026-05-20 15:17:44', '2026-05-20 15:17:44');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('36', '13', NULL, '7', 'в', NULL, NULL, '0', 'published', '2026-05-20 15:17:45', '2026-05-20 15:17:45');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('37', '8', NULL, '7', 'а', NULL, NULL, '0', 'published', '2026-05-20 15:17:48', '2026-05-20 15:17:48');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('38', '16', NULL, '6', 'ььь', NULL, NULL, '0', 'published', '2026-05-30 19:54:02', '2026-05-30 19:54:02');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('39', '16', NULL, '6', '😂', NULL, NULL, '0', 'published', '2026-05-31 16:37:32', '2026-05-31 16:37:32');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('40', '16', NULL, '6', '❤️', NULL, NULL, '0', 'published', '2026-05-31 17:11:49', '2026-05-31 17:11:49');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('41', '16', NULL, '6', 'р', NULL, NULL, '0', 'published', '2026-05-31 17:12:12', '2026-05-31 17:12:12');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('42', '16', NULL, '6', '🔥', NULL, NULL, '0', 'published', '2026-05-31 17:24:45', '2026-05-31 17:24:45');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('43', '16', NULL, '6', 'а', NULL, NULL, '0', 'published', '2026-05-31 17:24:54', '2026-05-31 17:24:54');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('44', '16', NULL, '6', '', 'uploads/comment_attachments/comment_1780238523_337be2df7f45938a.gif', 'gif', '0', 'published', '2026-05-31 17:42:03', '2026-05-31 17:42:03');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('45', '16', NULL, '6', '', 'uploads/comment_attachments/comment_1780238556_f10ee2806dbd5813.png', 'image', '0', 'published', '2026-05-31 17:42:36', '2026-05-31 17:42:36');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('46', '16', NULL, '6', 'убей себя', NULL, NULL, '0', 'rejected', '2026-05-31 18:46:08', '2026-05-31 18:46:08');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('47', '16', NULL, '6', 'пошел нахуй', NULL, NULL, '0', 'rejected', '2026-05-31 18:47:12', '2026-05-31 18:47:12');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('48', '16', NULL, '6', 'убей себя', NULL, NULL, '0', 'rejected', '2026-05-31 18:47:20', '2026-05-31 18:47:20');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('49', '16', NULL, '6', 'пиздец', NULL, NULL, '0', 'rejected', '2026-05-31 18:47:31', '2026-05-31 18:47:31');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('50', '16', NULL, '6', 'блядтоа', NULL, NULL, '0', 'rejected', '2026-05-31 18:47:43', '2026-05-31 18:47:43');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('51', '16', NULL, '6', 'fuck', NULL, NULL, '0', 'rejected', '2026-05-31 18:47:49', '2026-05-31 18:47:49');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('53', '16', '45', '6', '@s аааа', NULL, NULL, '0', 'published', '2026-05-31 21:56:51', '2026-05-31 21:56:51');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('54', '16', '38', '6', '@s ааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, NULL, '0', 'pending_review', '2026-05-31 22:16:40', '2026-05-31 22:16:40');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('55', '16', '38', '6', '@s я считаю что это не так ведь это не честно по отношению к автору. Арт крутой и это факт, это такой стиль и автор прекрасно это передает', NULL, NULL, '0', 'published', '2026-05-31 22:17:33', '2026-05-31 22:17:33');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('56', '16', NULL, '5', 'ааааааааа', NULL, NULL, '0', 'published', '2026-05-31 22:36:13', '2026-05-31 22:36:13');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('59', '16', NULL, '6', 'пиздец', NULL, NULL, '0', 'rejected', '2026-05-31 22:36:57', '2026-05-31 22:36:57');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('60', '16', NULL, '6', 'убейся', NULL, NULL, '0', 'rejected', '2026-05-31 22:44:00', '2026-05-31 22:44:00');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('68', '12', NULL, '6', 'убейся', NULL, NULL, '0', 'rejected', '2026-06-03 19:01:15', '2026-06-03 19:01:15');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('80', '12', '14', '6', '@omg ы', NULL, NULL, '0', 'published', '2026-06-04 17:55:51', '2026-06-04 17:55:51');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('81', '12', '16', '6', '@omg ы', NULL, NULL, '0', 'published', '2026-06-04 17:55:54', '2026-06-04 17:55:54');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('82', '12', '81', '6', '@s', 'uploads/comment_attachments/comment_1780586720_a83b907c34030f7e.gif', 'gif', '0', 'published', '2026-06-04 18:25:20', '2026-06-04 18:25:20');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('84', '17', NULL, '6', 'мяу', NULL, NULL, '0', 'published', '2026-06-07 23:52:13', '2026-06-07 23:52:13');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('85', '17', NULL, '6', 'мяу', NULL, NULL, '0', 'published', '2026-06-07 23:52:24', '2026-06-07 23:52:24');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('86', '18', NULL, '7', 'с', NULL, NULL, '0', 'published', '2026-06-08 14:59:29', '2026-06-08 14:59:29');
INSERT INTO `comments` (`id`, `post_id`, `parent_comment_id`, `user_id`, `comment_text`, `attachment_url`, `attachment_type`, `is_deleted`, `status`, `created_at`, `updated_at`) VALUES ('87', '18', NULL, '6', 'с', NULL, NULL, '0', 'published', '2026-06-08 23:17:23', '2026-06-08 23:17:23');

DROP TABLE IF EXISTS `follow_requests`;
CREATE TABLE `follow_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint unsigned NOT NULL,
  `receiver_id` bigint unsigned NOT NULL,
  `status` enum('pending','accepted','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_follow_request` (`sender_id`,`receiver_id`),
  KEY `idx_follow_requests_sender` (`sender_id`),
  KEY `idx_follow_requests_receiver` (`receiver_id`),
  CONSTRAINT `fk_follow_requests_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_follow_requests_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_no_self_request` CHECK ((`sender_id` <> `receiver_id`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `followers`;
CREATE TABLE `followers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `follower_id` bigint unsigned NOT NULL,
  `following_id` bigint unsigned NOT NULL,
  `status` enum('pending','accepted','declined') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'accepted',
  `declined_until` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_follow` (`follower_id`,`following_id`),
  KEY `idx_followers_follower_id` (`follower_id`),
  KEY `idx_followers_following_id` (`following_id`),
  CONSTRAINT `fk_followers_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_followers_following` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_no_self_follow` CHECK ((`follower_id` <> `following_id`))
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `followers` (`id`, `follower_id`, `following_id`, `status`, `declined_until`, `created_at`) VALUES ('28', '6', '7', 'accepted', NULL, '2026-06-03 22:26:24');
INSERT INTO `followers` (`id`, `follower_id`, `following_id`, `status`, `declined_until`, `created_at`) VALUES ('37', '5', '7', 'accepted', NULL, '2026-06-07 16:31:39');

DROP TABLE IF EXISTS `hidden_posts`;
CREATE TABLE `hidden_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `post_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hidden_post` (`user_id`,`post_id`),
  KEY `idx_hidden_posts_user_id` (`user_id`),
  KEY `idx_hidden_posts_post_id` (`post_id`),
  CONSTRAINT `fk_hidden_posts_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hidden_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `hidden_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('1', '7', '9', '2026-04-21 20:37:46');
INSERT INTO `hidden_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('2', '5', '8', '2026-04-28 13:45:49');
INSERT INTO `hidden_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('3', '7', '12', '2026-05-18 11:27:42');
INSERT INTO `hidden_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('4', '6', '13', '2026-05-20 16:45:13');
INSERT INTO `hidden_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('5', '7', '16', '2026-06-08 15:19:32');

DROP TABLE IF EXISTS `likes`;
CREATE TABLE `likes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `post_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_like` (`user_id`,`post_id`),
  KEY `idx_likes_user_id` (`user_id`),
  KEY `idx_likes_post_id` (`post_id`),
  CONSTRAINT `fk_likes_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('2', '6', '6', '2026-04-20 16:37:19');
INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('3', '6', '7', '2026-04-20 16:39:56');
INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('4', '5', '7', '2026-04-20 16:44:49');
INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('6', '5', '8', '2026-04-21 16:48:44');
INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('8', '7', '9', '2026-04-21 20:35:07');
INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('38', '7', '13', '2026-05-20 15:13:41');
INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('88', '7', '18', '2026-06-08 13:43:31');
INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('89', '7', '11', '2026-06-08 14:53:18');
INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('90', '7', '19', '2026-06-08 16:23:39');
INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('92', '5', '20', '2026-06-08 21:50:06');
INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('93', '5', '12', '2026-06-08 21:50:08');
INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('94', '6', '18', '2026-06-08 23:15:10');

DROP TABLE IF EXISTS `message_hidden`;
CREATE TABLE `message_hidden` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_message_hidden` (`message_id`,`user_id`),
  KEY `idx_message_hidden_user_id` (`user_id`),
  CONSTRAINT `fk_message_hidden_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_hidden_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `message_hidden` (`id`, `message_id`, `user_id`, `created_at`) VALUES ('1', '46', '5', '2026-04-28 18:19:56');
INSERT INTO `message_hidden` (`id`, `message_id`, `user_id`, `created_at`) VALUES ('2', '44', '5', '2026-04-28 18:20:30');
INSERT INTO `message_hidden` (`id`, `message_id`, `user_id`, `created_at`) VALUES ('3', '46', '7', '2026-04-29 15:19:11');
INSERT INTO `message_hidden` (`id`, `message_id`, `user_id`, `created_at`) VALUES ('4', '121', '5', '2026-06-07 14:49:34');
INSERT INTO `message_hidden` (`id`, `message_id`, `user_id`, `created_at`) VALUES ('5', '120', '5', '2026-06-07 15:16:10');
INSERT INTO `message_hidden` (`id`, `message_id`, `user_id`, `created_at`) VALUES ('6', '119', '5', '2026-06-07 15:26:04');
INSERT INTO `message_hidden` (`id`, `message_id`, `user_id`, `created_at`) VALUES ('7', '123', '5', '2026-06-07 16:33:03');

DROP TABLE IF EXISTS `message_reactions`;
CREATE TABLE `message_reactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `reaction` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_message_reaction` (`message_id`,`user_id`),
  KEY `idx_message_reactions_message` (`message_id`),
  KEY `idx_message_reactions_user` (`user_id`),
  CONSTRAINT `message_reactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `message_reactions_ibfk_2` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `message_reactions` (`id`, `message_id`, `user_id`, `reaction`, `created_at`) VALUES ('1', '43', '5', '😂', '2026-04-28 19:48:34');
INSERT INTO `message_reactions` (`id`, `message_id`, `user_id`, `reaction`, `created_at`) VALUES ('2', '25', '5', '😮', '2026-04-28 19:48:37');
INSERT INTO `message_reactions` (`id`, `message_id`, `user_id`, `reaction`, `created_at`) VALUES ('3', '45', '5', '❤️', '2026-04-28 20:05:50');
INSERT INTO `message_reactions` (`id`, `message_id`, `user_id`, `reaction`, `created_at`) VALUES ('9', '114', '5', '🔥', '2026-05-04 16:35:39');
INSERT INTO `message_reactions` (`id`, `message_id`, `user_id`, `reaction`, `created_at`) VALUES ('10', '110', '5', '❤️', '2026-06-07 14:40:08');
INSERT INTO `message_reactions` (`id`, `message_id`, `user_id`, `reaction`, `created_at`) VALUES ('13', '113', '5', '👍', '2026-06-07 14:59:42');

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint unsigned NOT NULL,
  `sender_id` bigint unsigned NOT NULL,
  `message_text` text COLLATE utf8mb4_general_ci,
  `attachment_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `attachment_type` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `post_id` bigint unsigned DEFAULT NULL,
  `reply_to_message_id` bigint unsigned DEFAULT NULL,
  `forwarded_from_message_id` bigint unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_for_all` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `edited_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_messages_chat_created` (`chat_id`,`created_at`),
  KEY `idx_messages_sender_created` (`sender_id`,`created_at`),
  KEY `idx_messages_chat_read` (`chat_id`,`is_read`),
  KEY `idx_messages_post` (`post_id`),
  KEY `idx_messages_reply` (`reply_to_message_id`),
  KEY `idx_messages_forwarded_from` (`forwarded_from_message_id`),
  CONSTRAINT `fk_messages_chat` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_forwarded_from` FOREIGN KEY (`forwarded_from_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_reply_to` FOREIGN KEY (`reply_to_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=150 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('1', '1', '7', 'привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 15:42:04', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('2', '1', '7', 'как дела', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 15:42:09', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('3', '1', '6', 'норм', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 15:42:44', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('4', '2', '5', 'але', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 15:46:02', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('5', '2', '6', 'але', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 15:46:14', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('6', '2', '6', 'РЕАЛЬНО РАБОТАЕТ', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 15:46:41', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('7', '2', '6', 'задержка 3 секунды', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 15:46:53', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('8', '3', '5', '???', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 15:49:50', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('9', '2', '5', 'ffff', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 15:51:18', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('10', '2', '6', 'sssss', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 15:51:34', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('11', '2', '5', 'привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 16:01:32', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('12', '2', '6', 'я крутая', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 16:02:20', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('13', '2', '6', 'оооо', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 16:02:27', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('14', '2', '6', 'ооо', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 16:02:34', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('15', '2', '6', 'ооо', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-23 16:02:45', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('22', '2', '5', '[post_share]|11|7|pashka-durashka', NULL, NULL, '11', NULL, NULL, '1', '0', '2026-04-26 14:28:00', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('23', '2', '5', '[post_share]|11|7|pashka-durashka', NULL, NULL, '11', NULL, NULL, '1', '0', '2026-04-26 14:38:13', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('24', '3', '5', '[post_share]|11|7|pashka-durashka', NULL, NULL, '11', NULL, NULL, '1', '0', '2026-04-26 14:42:46', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('25', '2', '5', 'НАКОНЕЦ-ТО', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 14:45:25', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('26', '3', '7', 'ghbdtn', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 17:02:58', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('27', '3', '7', 'и че', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 17:03:05', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('28', '3', '5', 'вв', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 17:03:42', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('29', '3', '5', 'вебсокет работает?', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 17:04:22', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('30', '3', '7', 'нет ошибка event', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 17:09:34', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('31', '3', '7', 'обидно', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 17:09:40', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('32', '3', '5', 'это да', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 19:21:01', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('33', '3', '5', 'а', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 19:23:47', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('34', '3', '7', 'б', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 19:24:01', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('35', '3', '5', 'в', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 19:24:03', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('36', '3', '5', 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 19:24:11', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('37', '3', '7', 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 19:41:03', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('38', '3', '5', 'в', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 19:41:22', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('39', '3', '7', 'ы', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 19:41:43', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('40', '3', '7', 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 19:41:52', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('41', '3', '7', 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 19:45:43', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('42', '3', '5', 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-26 19:45:47', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('43', '2', '5', '[post_share]|11|7|pashka-durashka', NULL, NULL, '11', NULL, NULL, '1', '0', '2026-04-28 13:47:07', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('44', '3', '5', 'привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-28 13:47:47', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('45', '3', '5', 'сообщение изменено', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-28 13:48:35', '2026-04-28 18:16:26');
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('46', '3', '5', 'Сообщение удалено', NULL, NULL, NULL, '45', NULL, '1', '1', '2026-04-28 18:17:13', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('47', '2', '5', 'сообщение изменено', NULL, NULL, NULL, NULL, '45', '1', '0', '2026-04-28 18:18:13', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('48', '2', '5', 'fggfg', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:20:48', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('49', '3', '5', 'аааааа', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:21:09', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('50', '3', '7', 'ааа', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:21:18', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('51', '3', '5', 'вввв', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:24:36', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('52', '3', '7', 'в', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:44:38', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('53', '3', '5', 'не работает снова', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:45:19', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('54', '3', '7', 'ага', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:45:23', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('55', '3', '5', 'так', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:45:41', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('56', '3', '7', 'с большими сообщениями может только работает', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:46:41', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('57', '3', '5', 'привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:48:02', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('58', '3', '7', 'привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:48:10', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('59', '3', '5', 'работает?', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:52:13', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('60', '3', '7', 'ну конечно', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 15:52:18', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('61', '3', '7', 'не работает', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 16:02:11', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('62', '3', '5', 'не работает', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 16:02:19', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('63', '3', '5', 'аааа', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 16:02:22', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('64', '3', '5', 'ааа', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 16:02:27', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('65', '3', '5', '<?php session_start(); require \'./config/config.php\';  header(\'Content-Type: application/json; charset=utf-8\');  if (!isset($_SESSION[\'user_id\'])) {     http_response_code(401);     echo json_encode([\'ok\' => false, \'error\' => \'auth_required\']);     exit; }  $currentUserId = (int) $_SESSION[\'user_id\'];  function getDialogs(PDO $pdo, int $userId): array {     $dialogsStmt = $pdo->prepare(\'         SELECT             chats.id,             partner.id AS partner_id,             partner.login AS partner_login,             partner.avatar AS partner_avatar,             latest.message_text AS last_message,             latest.created_at AS last_message_created_at,             (                 SELECT COUNT(*)                 FROM messages unread                 WHERE unread.chat_id = chats.id                   AND unread.sender_id != :user_id                   AND unread.is_read = 0             ) AS unread_count         FROM chats         INNER JOIN users AS partner ON partner.id = IF(chats.user', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 16:03:20', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('66', '3', '7', 'пппппппппппппп', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 16:03:48', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('67', '3', '5', 'ппппппппппппппп', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 16:14:59', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('68', '3', '7', 'зззззззззззззззззззз', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 16:15:06', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('69', '3', '5', 'фффффффффффффффффффффффффффффффффффффффффффф', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 17:55:53', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('70', '3', '5', 'в', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 17:56:44', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('71', '3', '7', 'в', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 17:57:01', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('72', '3', '7', 'а почему не сразу присылается', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 17:57:12', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('73', '3', '7', 'хз', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 17:57:22', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('74', '2', '5', '[post_share]|11|7|pashka-durashka', NULL, NULL, '11', NULL, NULL, '1', '0', '2026-04-29 18:06:11', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('75', '2', '6', 'привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 18:21:46', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('76', '2', '5', 'привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 18:21:52', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('77', '2', '6', 'и что опять', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 18:22:27', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('78', '2', '5', 'И ГДЕ', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 18:29:04', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('79', '2', '5', 'привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 18:47:27', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('80', '2', '6', 'привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 18:47:51', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('81', '2', '6', 'УРРААА', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 18:47:55', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('82', '2', '6', 'БОЖЕ', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 18:47:56', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('83', '2', '5', 'РАБОТАЕТ СНОВА', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-29 18:48:04', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('84', '1', '7', 'не норм', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:42:52', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('85', '1', '6', 'норм', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:43:02', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('86', '1', '6', 'да что такое то', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:43:11', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('87', '1', '7', 'хз', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:43:24', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('88', '1', '6', 'работай пожалуйста', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:44:12', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('89', '1', '7', 'я сейчас плакать буду', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:44:23', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('90', '1', '6', 'а', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:45:41', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('91', '1', '7', 'что ж ты вечно ломаешься', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:46:00', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('92', '2', '5', 'проверка', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:46:50', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('93', '2', '6', 'проверка', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:46:55', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('94', '2', '6', 'так ну', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:48:33', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('95', '2', '6', 'это дурдо', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:48:39', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('96', '2', '5', 'м', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:48:49', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('97', '2', '5', 'полнейший', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:48:55', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('98', '2', '6', 'ТАК', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:50:57', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('99', '2', '6', 'и что', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:51:02', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('100', '2', '6', 'ничего', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:56:19', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('101', '2', '5', 'и правда ничего', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 17:56:27', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('102', '2', '6', 'привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:14:10', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('103', '2', '5', 'привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:14:14', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('104', '3', '5', 'аааааааааааааааааа', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:39:02', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('105', '3', '7', 'аааааааааааааааааааааааааа', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:39:08', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('106', '3', '5', 'аааааааааааааааааааааааааааа', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:39:13', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('107', '3', '5', 'НУ ПОЖАЛУЙСТА', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:40:14', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('108', '3', '5', 'помогите', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:45:42', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('109', '3', '7', 'помогите', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:46:13', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('110', '3', '7', 'gjvjubnt', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:59:13', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('111', '3', '5', 'помогите', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:59:23', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('112', '3', '7', 'помогите', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:59:29', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('113', '3', '7', 'ну и что', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:59:41', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('114', '3', '7', 'что не так то', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 18:59:44', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('115', '3', '5', 'не знаю', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 19:08:06', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('116', '3', '7', 'але', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 19:08:16', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('117', '3', '5', 'але', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 19:11:28', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('118', '3', '5', 'мяу', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 19:18:21', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('119', '3', '5', 'привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 19:22:59', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('120', '3', '7', 'ну привет', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 19:23:17', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('121', '3', '7', 'ну наконец-то', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 19:23:24', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('122', '3', '5', 'ага', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 20:05:32', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('123', '3', '7', 'ага', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-04-30 20:05:34', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('124', '2', '6', 'адвда', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-04 15:03:17', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('125', '2', '5', 'авадад', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-04 15:03:20', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('126', '1', '7', 'м', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-04 15:07:33', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('127', '1', '7', 'в', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-04 15:07:54', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('128', '1', '6', 'а', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-04 15:07:55', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('129', '1', '6', 'в', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-04 15:09:03', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('130', '1', '7', 'ч', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-04 15:09:06', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('131', '1', '6', 'а', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-04 16:34:35', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('132', '2', '5', 'в', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-04 16:34:51', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('133', '2', '6', 'а', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-04 16:34:53', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('134', '2', '5', 'что не так то', NULL, NULL, NULL, NULL, '114', '1', '0', '2026-05-04 16:35:45', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('135', '2', '5', '???', NULL, NULL, NULL, NULL, '8', '1', '0', '2026-05-04 16:36:04', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('136', '2', '5', '#20B2AA 68C5DB 99FFE0 пурпур - #5B2A62 73BA9B', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-06 21:22:26', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('137', '3', '5', '[post_share]|12|5|omg', NULL, NULL, '12', NULL, NULL, '1', '0', '2026-05-17 20:06:19', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('138', '1', '7', '[post_share]|8|6|s', NULL, NULL, '8', NULL, NULL, '1', '0', '2026-05-22 00:13:47', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('139', '3', '5', 'прив', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-27 19:45:05', '2026-06-07 15:04:34');
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('140', '3', '5', 'проверка WebSocket', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-05-27 19:47:56', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('141', '3', '5', '❤️❤️🎉😢', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-06-07 17:18:34', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('142', '3', '5', 'мяу', NULL, NULL, NULL, '116', NULL, '1', '0', '2026-06-07 17:48:53', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('143', '3', '5', '', 'uploads/chat_attachments/message_1780844192_67c99f7c6cd40a9b.pdf', 'document', NULL, NULL, NULL, '1', '0', '2026-06-07 17:56:32', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('144', '3', '5', 'чч', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-06-07 18:19:54', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('145', '3', '5', 'ввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввввв', NULL, NULL, NULL, NULL, NULL, '1', '0', '2026-06-07 19:56:25', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('146', '2', '6', 'мяу', NULL, NULL, NULL, NULL, NULL, '0', '0', '2026-06-08 16:45:57', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('147', '2', '6', 'аааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааааа', NULL, NULL, NULL, NULL, NULL, '0', '0', '2026-06-08 17:31:58', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('148', '2', '6', '😍', 'uploads/chat_attachments/message_1780929128_4e5f31b2ca5a923b.gif', 'gif', NULL, NULL, NULL, '0', '0', '2026-06-08 17:32:08', NULL);
INSERT INTO `messages` (`id`, `chat_id`, `sender_id`, `message_text`, `attachment_url`, `attachment_type`, `post_id`, `reply_to_message_id`, `forwarded_from_message_id`, `is_read`, `deleted_for_all`, `created_at`, `edited_at`) VALUES ('149', '2', '6', 'привет', NULL, NULL, NULL, NULL, NULL, '0', '0', '2026-06-08 22:39:11', NULL);

DROP TABLE IF EXISTS `moderation_queue`;
CREATE TABLE `moderation_queue` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint unsigned NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `moderator_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_moderation_queue_comment` (`comment_id`),
  KEY `idx_moderation_queue_status` (`status`,`created_at`),
  CONSTRAINT `fk_moderation_queue_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `moderation_queue` (`id`, `comment_id`, `reason`, `status`, `created_at`, `reviewed_at`, `moderator_id`) VALUES ('1', '54', 'Слишком много одинаковых символов', 'pending', '2026-05-31 22:16:40', NULL, NULL);

DROP TABLE IF EXISTS `moderation_reasons`;
CREATE TABLE `moderation_reasons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_moderation_reasons_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=96581 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `moderation_reasons` (`id`, `code`, `label`, `created_at`) VALUES ('1', 'spam', 'Спам', '2026-04-20 22:06:28');
INSERT INTO `moderation_reasons` (`id`, `code`, `label`, `created_at`) VALUES ('2', 'abuse', 'Оскорбления', '2026-04-20 22:06:28');
INSERT INTO `moderation_reasons` (`id`, `code`, `label`, `created_at`) VALUES ('3', 'hate', 'Разжигание ненависти', '2026-04-20 22:06:28');
INSERT INTO `moderation_reasons` (`id`, `code`, `label`, `created_at`) VALUES ('4', 'fraud', 'Мошенничество', '2026-04-20 22:06:28');
INSERT INTO `moderation_reasons` (`id`, `code`, `label`, `created_at`) VALUES ('5', 'other', 'Другое', '2026-04-20 22:06:28');

DROP TABLE IF EXISTS `moderation_reports`;
CREATE TABLE `moderation_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reporter_user_id` bigint unsigned NOT NULL,
  `target_user_id` bigint unsigned DEFAULT NULL,
  `target_comment_id` bigint unsigned DEFAULT NULL,
  `reason_id` bigint unsigned DEFAULT NULL,
  `reason_text` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('open','reviewed','resolved') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reports_reporter` (`reporter_user_id`),
  KEY `idx_reports_target_user` (`target_user_id`),
  KEY `idx_reports_target_comment` (`target_comment_id`),
  KEY `idx_reports_reason` (`reason_id`),
  KEY `idx_reports_status_created` (`status`,`created_at`),
  CONSTRAINT `fk_reports_reason` FOREIGN KEY (`reason_id`) REFERENCES `moderation_reasons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_target_comment` FOREIGN KEY (`target_comment_id`) REFERENCES `comments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_target_user` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('1', '7', '5', NULL, NULL, 'Жалоба на пользователя через пост #9', 'open', '2026-04-21 20:37:42');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('2', '7', '5', NULL, NULL, 'Жалоба на пользователя через пост #9', 'open', '2026-04-21 20:37:44');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('3', '7', '5', NULL, NULL, 'Жалоба на пост #9', 'open', '2026-04-21 20:37:45');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('4', '7', '6', NULL, NULL, 'Жалоба на пользователя через пост #8', 'open', '2026-04-21 20:40:11');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('5', '5', '6', NULL, NULL, 'Жалоба на пост #8', 'open', '2026-04-28 13:45:40');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('6', '5', '6', NULL, NULL, 'Жалоба на пользователя через пост #8', 'open', '2026-04-28 13:45:44');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('7', '7', '6', NULL, NULL, 'Жалоба на clips #8', 'open', '2026-05-21 14:27:52');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('8', '7', '6', NULL, NULL, 'Жалоба на clips #8', 'open', '2026-05-21 14:27:55');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('9', '7', '6', NULL, NULL, 'Насилие', 'open', '2026-05-22 19:39:12');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('10', '6', '5', '56', NULL, 'Другое', 'open', '2026-05-31 23:03:27');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('11', '6', '5', '16', NULL, 'Нарушение авторских прав', 'open', '2026-06-04 17:33:14');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('12', '6', '5', '14', NULL, 'Нарушение авторских прав', 'open', '2026-06-04 17:33:20');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('13', '7', '6', NULL, NULL, 'мяу', 'open', '2026-06-08 15:19:27');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('14', '7', '6', NULL, NULL, 'Нарушение авторских прав', 'open', '2026-06-08 16:24:14');
INSERT INTO `moderation_reports` (`id`, `reporter_user_id`, `target_user_id`, `target_comment_id`, `reason_id`, `reason_text`, `status`, `created_at`) VALUES ('15', '6', '7', NULL, NULL, 'Нарушение авторских прав', 'open', '2026-06-08 23:23:29');

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_password_reset_user_id` (`user_id`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `pinned_messages`;
CREATE TABLE `pinned_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint unsigned NOT NULL,
  `message_id` bigint unsigned NOT NULL,
  `pinned_by_user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pinned_message` (`chat_id`,`message_id`),
  KEY `idx_pinned_messages_chat_id` (`chat_id`),
  KEY `idx_pinned_messages_message_id` (`message_id`),
  KEY `idx_pinned_messages_user_id` (`pinned_by_user_id`),
  CONSTRAINT `fk_pinned_messages_chat` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pinned_messages_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pinned_messages_user` FOREIGN KEY (`pinned_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `pinned_posts`;
CREATE TABLE `pinned_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `post_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pinned_post` (`user_id`,`post_id`),
  KEY `idx_pinned_posts_user_id` (`user_id`),
  KEY `idx_pinned_posts_post_id` (`post_id`),
  CONSTRAINT `fk_pinned_posts_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pinned_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pinned_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('1', '7', '11', '2026-04-21 20:42:23');
INSERT INTO `pinned_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('3', '5', '9', '2026-04-28 13:46:07');
INSERT INTO `pinned_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('7', '6', '8', '2026-06-05 16:41:00');

DROP TABLE IF EXISTS `post_media`;
CREATE TABLE `post_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint unsigned NOT NULL,
  `media_type` enum('image','video') COLLATE utf8mb4_general_ci NOT NULL,
  `media_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `position` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_media_post_id` (`post_id`),
  CONSTRAINT `fk_post_media_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('1', '6', 'image', 'uploads/posts/6854720ca7d93b2c7b06c4804a5e64b7.png', '1', '2026-04-16 21:57:40');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('2', '7', 'image', 'uploads/posts/ee4acbf8934fcdd6a6003368c5bed612.jpg', '1', '2026-04-20 16:39:02');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('3', '8', 'video', 'uploads/posts/c509476f351de5ec46483472444fdda4.mp4', '1', '2026-04-20 19:13:06');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('4', '9', 'image', 'uploads/posts/9a6abc74244d25d0202f96dd60770475.png', '1', '2026-04-20 19:37:48');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('5', '10', 'image', 'uploads/posts/e83db0b20fa22adac6c04632c8edc012.png', '1', '2026-04-21 20:36:51');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('6', '11', 'image', 'uploads/posts/3b2f914bcc72e0e15625f7125c6c181f.png', '1', '2026-04-21 20:42:16');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('7', '12', 'image', 'uploads/posts/7b6674231a2d22fe1ec155eb606e59fe.png', '1', '2026-05-04 16:37:18');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('8', '13', 'video', 'uploads/posts/453a5b19d1390d8aeebfd8b02cce811f.mp4', '1', '2026-05-17 21:23:31');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('9', '14', 'image', 'uploads/posts/989b33a9472ebdfa7edeb3966fb8275b.png', '1', '2026-05-24 18:22:57');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('10', '15', 'image', 'uploads/posts/c6422f64cf349cfb5b9ec6f015e68224.png', '1', '2026-05-24 18:23:25');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('11', '16', 'image', 'uploads/posts/fc7c67fd622e2e08db52e3a7397eeb12.png', '1', '2026-05-24 18:23:33');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('12', '17', 'video', 'uploads/posts/f258abdd27a91eed75044174dabf2b6e.mp4', '1', '2026-06-07 22:10:37');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('13', '18', 'video', 'uploads/posts/9064cd3f9dd71afc3e93ecdacb095720.mp4', '1', '2026-06-08 13:41:07');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('14', '19', 'image', 'uploads/posts/d6630a8799ed70f687c405f9ecca81d9.png', '1', '2026-06-08 16:15:39');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('15', '20', 'image', 'uploads/posts/cc2ba95c4eb5ed2410f0144b6bef5590.png', '1', '2026-06-08 21:48:08');
INSERT INTO `post_media` (`id`, `post_id`, `media_type`, `media_url`, `position`, `created_at`) VALUES ('16', '21', 'image', 'uploads/posts/643ebc377a51b5075f5958c02ee0f2a3.png', '1', '2026-06-09 01:22:01');

DROP TABLE IF EXISTS `posts`;
CREATE TABLE `posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `caption` text COLLATE utf8mb4_general_ci,
  `hashtags` text COLLATE utf8mb4_general_ci,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_posts_user_id` (`user_id`),
  CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('6', '5', 'это я и федя', NULL, '1', '2026-04-16 21:57:40', '2026-04-20 18:02:12');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('7', '6', 'Это я и кто', NULL, '1', '2026-04-20 16:39:02', '2026-04-20 19:12:44');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('8', '6', 'ааааа', NULL, '1', '2026-04-20 19:13:06', '2026-06-07 22:05:03');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('9', '5', 'тт', NULL, '1', '2026-04-20 19:37:48', '2026-04-28 13:46:54');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('10', '7', NULL, NULL, '1', '2026-04-21 20:36:51', '2026-04-21 20:37:35');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('11', '7', NULL, NULL, '1', '2026-04-21 20:42:16', '2026-06-08 14:53:27');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('12', '5', 'Учусь', NULL, '0', '2026-05-04 16:37:18', '2026-05-04 16:37:18');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('13', '5', NULL, NULL, '1', '2026-05-17 21:23:31', '2026-05-21 14:34:37');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('14', '6', 'ы', NULL, '0', '2026-05-24 18:22:57', '2026-05-24 18:22:57');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('15', '6', NULL, NULL, '0', '2026-05-24 18:23:25', '2026-05-24 18:23:25');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('16', '6', NULL, NULL, '0', '2026-05-24 18:23:33', '2026-05-24 18:23:33');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('17', '6', NULL, NULL, '1', '2026-06-07 22:10:37', '2026-06-08 18:14:50');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('18', '7', 'Готэм', '#готэм', '0', '2026-06-08 13:41:07', '2026-06-09 01:19:04');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('19', '7', 'мяу', NULL, '0', '2026-06-08 16:15:39', '2026-06-08 16:15:39');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('20', '5', 'мяу', NULL, '0', '2026-06-08 21:48:08', '2026-06-08 21:48:08');
INSERT INTO `posts` (`id`, `user_id`, `caption`, `hashtags`, `is_deleted`, `created_at`, `updated_at`) VALUES ('21', '9', 'смешарики', '#чтото', '0', '2026-06-09 01:22:01', '2026-06-09 01:22:41');

DROP TABLE IF EXISTS `reposts`;
CREATE TABLE `reposts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `post_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_reposts_post` (`post_id`),
  KEY `fk_reposts_user` (`user_id`),
  CONSTRAINT `fk_reposts_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reposts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `reposts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('1', '5', '9', '2026-04-21 18:07:17');
INSERT INTO `reposts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('2', '5', '8', '2026-04-21 18:07:34');
INSERT INTO `reposts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('3', '6', '9', '2026-04-21 19:48:57');
INSERT INTO `reposts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('5', '7', '9', '2026-04-21 20:35:13');
INSERT INTO `reposts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('36', '7', '16', '2026-05-24 23:12:16');
INSERT INTO `reposts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('43', '6', '11', '2026-06-02 21:49:30');
INSERT INTO `reposts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('57', '5', '20', '2026-06-08 21:50:06');
INSERT INTO `reposts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('58', '5', '12', '2026-06-08 21:50:08');
INSERT INTO `reposts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('59', '6', '18', '2026-06-08 23:15:11');

DROP TABLE IF EXISTS `saved_posts`;
CREATE TABLE `saved_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `post_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saved_post` (`user_id`,`post_id`),
  KEY `idx_saved_posts_user_id` (`user_id`),
  KEY `idx_saved_posts_post_id` (`post_id`),
  CONSTRAINT `fk_saved_posts_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_saved_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `saved_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('2', '6', '7', '2026-04-20 16:42:45');
INSERT INTO `saved_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('3', '5', '6', '2026-04-20 17:18:22');
INSERT INTO `saved_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('4', '5', '7', '2026-04-20 17:18:24');
INSERT INTO `saved_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('7', '5', '9', '2026-04-21 19:43:46');
INSERT INTO `saved_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('19', '5', '11', '2026-05-17 20:06:13');
INSERT INTO `saved_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('38', '6', '8', '2026-05-24 21:26:20');
INSERT INTO `saved_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('62', '5', '20', '2026-06-08 21:50:06');
INSERT INTO `saved_posts` (`id`, `user_id`, `post_id`, `created_at`) VALUES ('63', '5', '12', '2026-06-08 21:50:09');

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_sessions_user_id` (`user_id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `user_blocks`;
CREATE TABLE `user_blocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `blocker_user_id` bigint unsigned NOT NULL,
  `blocked_user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_blocks_pair` (`blocker_user_id`,`blocked_user_id`),
  KEY `idx_user_blocks_blocker` (`blocker_user_id`),
  KEY `idx_user_blocks_blocked` (`blocked_user_id`),
  CONSTRAINT `fk_user_blocks_blocked` FOREIGN KEY (`blocked_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_blocks_blocker` FOREIGN KEY (`blocker_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user_blocks` (`id`, `blocker_user_id`, `blocked_user_id`, `created_at`) VALUES ('1', '7', '6', '2026-06-08 15:19:45');

DROP TABLE IF EXISTS `user_notifications`;
CREATE TABLE `user_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `target_user_id` bigint unsigned DEFAULT NULL,
  `notification_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `post_id` bigint unsigned DEFAULT NULL,
  `comment_id` bigint unsigned DEFAULT NULL,
  `report_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `comment_text` text COLLATE utf8mb4_general_ci,
  `report_reason` varchar(1000) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`,`is_read`,`created_at`),
  KEY `idx_notifications_report` (`report_id`),
  KEY `idx_notifications_type_post` (`notification_type`,`post_id`,`created_at`),
  KEY `idx_notifications_actor` (`actor_user_id`),
  KEY `idx_notifications_target` (`target_user_id`),
  KEY `idx_notifications_post` (`post_id`),
  KEY `idx_notifications_comment` (`comment_id`),
  CONSTRAINT `fk_notifications_report` FOREIGN KEY (`report_id`) REFERENCES `moderation_reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('1', '6', NULL, NULL, NULL, NULL, NULL, NULL, 'Комментарий удалён', 'Комментарий удалён администратором за нарушение правил.\nПричина: Разжигание ненависти.\nПредупреждение: при следующем нарушении аккаунт будет удалён.', NULL, NULL, '1', '2026-04-20 22:18:41');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('2', '5', NULL, NULL, NULL, NULL, NULL, NULL, 'Комментарий удалён', 'Комментарий удалён администратором за нарушение правил.\nПричина: Разжигание ненависти.\nПредупреждение: при следующем нарушении аккаунт будет удалён.', NULL, NULL, '1', '2026-05-20 14:40:43');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('3', '5', '6', '5', 'post_like', '12', NULL, NULL, 'Публикация', '@s поставил(а) лайк вашей публикации', NULL, NULL, '0', '2026-06-02 21:19:45');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('4', '7', '6', '7', 'post_like', '11', NULL, NULL, 'Публикация', '@s поставил(а) лайк вашей публикации', NULL, NULL, '0', '2026-06-02 21:49:28');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('5', '7', '6', '7', 'post_repost', '11', NULL, NULL, 'Публикация', '@s сделал(а) репост вашей публикации', NULL, NULL, '0', '2026-06-02 21:49:30');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('6', '7', '6', '7', 'post_saved', '11', NULL, NULL, 'Публикация', '@s добавил(а) вашу публикацию в избранное', NULL, NULL, '0', '2026-06-02 21:49:30');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('7', '5', '6', '5', 'post_like', '12', NULL, NULL, 'Публикация', '@s поставил(а) лайк вашей публикации', NULL, NULL, '0', '2026-06-02 22:25:36');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('8', '5', '6', '5', 'post_repost', '12', NULL, NULL, 'Публикация', '@s сделал(а) репост вашей публикации', NULL, NULL, '0', '2026-06-02 22:25:37');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('9', '5', '6', '5', 'post_saved', '12', NULL, NULL, 'Публикация', '@s добавил(а) вашу публикацию в избранное', NULL, NULL, '0', '2026-06-02 22:25:38');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('10', '5', '6', '5', 'post_like', '12', NULL, NULL, 'Публикация', '@s поставил(а) лайк вашей публикации', NULL, NULL, '0', '2026-06-03 15:58:39');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('11', '5', '6', '5', 'post_repost', '12', NULL, NULL, 'Публикация', '@s сделал(а) репост вашей публикации', NULL, NULL, '0', '2026-06-03 15:58:43');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('12', '5', '6', '5', 'post_saved', '12', NULL, NULL, 'Публикация', '@s добавил(а) вашу публикацию в избранное', NULL, NULL, '0', '2026-06-03 15:58:44');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('13', '5', '6', '5', 'post_comment', '12', '61', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"ввв\"', 'ввв', NULL, '0', '2026-06-03 15:59:15');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('14', '5', '6', '5', 'post_comment', '12', '62', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"😂\"', '😂', NULL, '0', '2026-06-03 15:59:23');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('15', '5', '6', '5', 'post_comment', '12', '63', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию', '', NULL, '0', '2026-06-03 17:07:02');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('16', '5', '6', '5', 'post_comment', '12', '64', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"c\"', 'c', NULL, '0', '2026-06-03 17:12:48');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('17', '5', '6', '5', 'post_comment', '12', '65', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию', '', NULL, '0', '2026-06-03 18:51:18');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('18', '5', '6', '5', 'post_comment', '12', '66', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"убей\"', 'убей', NULL, '0', '2026-06-03 19:00:40');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('19', '5', '6', '5', 'post_comment', '12', '67', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"пиздец\"', 'пиздец', NULL, '0', '2026-06-03 19:00:45');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('20', '5', '6', '5', 'post_comment', '12', '69', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"@omg мяу\"', '@omg мяу', NULL, '0', '2026-06-03 19:20:52');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('21', '5', '6', '5', 'post_comment', '12', '70', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"@omg\"', '@omg', NULL, '0', '2026-06-03 22:14:32');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('22', '5', '6', '5', 'post_comment', '12', '71', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"@s ы\"', '@s ы', NULL, '0', '2026-06-04 14:58:19');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('23', '5', '6', '5', 'post_comment', '12', '72', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"@s\"', '@s', NULL, '0', '2026-06-04 14:58:26');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('24', '5', '6', '5', 'post_comment', '12', '73', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"@omg\"', '@omg', NULL, '0', '2026-06-04 14:58:38');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('25', '5', '6', '5', 'post_like', '12', NULL, NULL, 'Публикация', '@s поставил(а) лайк вашей публикации', NULL, NULL, '0', '2026-06-04 14:59:28');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('26', '5', '6', '5', 'post_repost', '12', NULL, NULL, 'Публикация', '@s сделал(а) репост вашей публикации', NULL, NULL, '0', '2026-06-04 14:59:31');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('27', '5', '6', '5', 'post_saved', '12', NULL, NULL, 'Публикация', '@s добавил(а) вашу публикацию в избранное', NULL, NULL, '0', '2026-06-04 14:59:39');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('28', '5', '6', '5', 'post_comment', '12', '74', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"🔥\"', '🔥', NULL, '0', '2026-06-04 15:06:00');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('29', '5', '6', '5', 'post_comment', '12', '75', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"ы\"', 'ы', NULL, '0', '2026-06-04 15:11:56');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('30', '5', '6', '5', 'post_comment', '12', '76', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию', '', NULL, '0', '2026-06-04 15:23:37');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('31', '5', '6', '5', 'post_comment', '12', '77', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"@s\"', '@s', NULL, '0', '2026-06-04 15:23:44');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('32', '5', '6', '5', 'post_comment', '12', '78', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"@s f\"', '@s f', NULL, '0', '2026-06-04 16:28:23');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('33', '5', '6', '5', 'post_comment', '12', '79', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"f\"', 'f', NULL, '0', '2026-06-04 17:17:16');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('34', '5', '6', '5', 'report_comment', '12', '16', '11', 'Жалоба на комментарий', 'Поступила жалоба на комментарий под публикацией', 'м', 'Нарушение авторских прав', '0', '2026-06-04 17:33:14');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('35', '5', '6', '5', 'report_comment', '12', '14', '12', 'Жалоба на комментарий', 'Поступила жалоба на комментарий под публикацией', 'о как', 'Нарушение авторских прав', '0', '2026-06-04 17:33:20');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('36', '5', '6', '5', 'post_repost', '12', NULL, NULL, 'Публикация', '@s сделал(а) репост вашей публикации', NULL, NULL, '0', '2026-06-04 17:48:53');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('37', '5', '6', '5', 'post_comment', '12', '80', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"@omg ы\"', '@omg ы', NULL, '0', '2026-06-04 17:55:51');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('38', '5', '6', '5', 'comment_reply', '12', '80', NULL, 'Комментарий', '@s ответил(а) на ваш комментарий: \"@omg ы\"', '@omg ы', NULL, '0', '2026-06-04 17:55:51');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('39', '5', '6', '5', 'post_comment', '12', '81', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"@omg ы\"', '@omg ы', NULL, '0', '2026-06-04 17:55:54');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('40', '5', '6', '5', 'comment_reply', '12', '81', NULL, 'Комментарий', '@s ответил(а) на ваш комментарий: \"@omg ы\"', '@omg ы', NULL, '0', '2026-06-04 17:55:54');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('41', '5', '6', '5', 'post_comment', '12', '82', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"@s\"', '@s', NULL, '0', '2026-06-04 18:25:20');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('42', '5', '6', '5', 'post_saved', '12', NULL, NULL, 'Публикация', '@s добавил(а) вашу публикацию в избранное', NULL, NULL, '0', '2026-06-04 20:02:48');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('43', '5', '6', '5', 'post_repost', '12', NULL, NULL, 'Публикация', '@s сделал(а) репост вашей публикации', NULL, NULL, '0', '2026-06-04 20:25:34');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('44', '5', '6', '5', 'post_saved', '12', NULL, NULL, 'Публикация', '@s добавил(а) вашу публикацию в избранное', NULL, NULL, '0', '2026-06-04 20:25:35');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('45', '5', '6', '5', 'post_comment', '12', '83', NULL, 'Публикация', '@s прокомментировал(а) вашу публикацию: \"@s ыыы\"', '@s ыыы', NULL, '0', '2026-06-04 20:45:56');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('46', '5', '6', '5', 'post_repost', '12', NULL, NULL, 'Публикация', '@s сделал(а) репост вашей публикации', NULL, NULL, '0', '2026-06-05 16:18:19');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('47', '5', '6', '5', 'post_saved', '12', NULL, NULL, 'Публикация', '@s добавил(а) вашу публикацию в избранное', NULL, NULL, '0', '2026-06-05 16:18:28');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('48', '5', '6', '5', 'post_like', '12', NULL, NULL, 'Публикация', '@s поставил(а) лайк вашей публикации', NULL, NULL, '0', '2026-06-05 16:25:47');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('49', '5', '6', '5', 'post_like', '12', NULL, NULL, 'Публикация', '@s поставил(а) лайк вашей публикации', NULL, NULL, '0', '2026-06-05 16:40:39');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('50', '5', '7', '5', 'report_post', '16', NULL, '13', 'Жалоба на публикацию', 'Поступила жалоба на публикацию', NULL, 'мяу', '0', '2026-06-08 15:19:27');
INSERT INTO `user_notifications` (`id`, `user_id`, `actor_user_id`, `target_user_id`, `notification_type`, `post_id`, `comment_id`, `report_id`, `title`, `message`, `comment_text`, `report_reason`, `is_read`, `created_at`) VALUES ('51', '5', '7', '5', 'report_post', '16', NULL, '14', 'Жалоба на публикацию', 'Поступила жалоба на публикацию', NULL, 'Нарушение авторских прав', '0', '2026-06-08 16:24:14');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
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
  `role` enum('user','admin') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`login`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `login`, `email`, `birth_date`, `gender`, `city`, `is_private`, `password`, `avatar`, `bio`, `background_image`, `website`, `is_active`, `created_at`, `updated_at`, `role`) VALUES ('5', 'omg', 'OMG@gmail.com', '2010-02-02', 'Другой', 'мяукутс', '0', '$2y$10$Je6rl0HiLCPnXZnvJYysP.9A1BvEtpgH1uy6si37h8ZfljAQFZm8a', 'uploads/profile/avatar_05f5800e5597b058fc3f022f.jpg', 'меов', 'uploads/profile/background_50197b8107e20fc947565994.jpg', 'http://snapix/profile.php', '1', '2026-04-11 22:22:51', '2026-04-20 22:18:05', 'admin');
INSERT INTO `users` (`id`, `login`, `email`, `birth_date`, `gender`, `city`, `is_private`, `password`, `avatar`, `bio`, `background_image`, `website`, `is_active`, `created_at`, `updated_at`, `role`) VALUES ('6', 's', 's@gmail.com', '2004-02-02', 'Женский', NULL, '0', '$2y$10$o1pbbSnLB1.Hzm0OTA.U4.GyVnr6Tij9KfpvVYbZnh3xq5YtVZE36', 'uploads/profile/avatar_f264834d4456224b0476a573.png', 'не расскажу', 'uploads/profile/background_c45bc0daaacb7a034ce2d7f4.png', NULL, '1', '2026-04-20 16:36:26', '2026-06-08 22:10:17', 'user');
INSERT INTO `users` (`id`, `login`, `email`, `birth_date`, `gender`, `city`, `is_private`, `password`, `avatar`, `bio`, `background_image`, `website`, `is_active`, `created_at`, `updated_at`, `role`) VALUES ('7', 'pashka-durashka', 'pashka@gmail.com', '2008-02-02', NULL, NULL, '0', '$2y$10$IILTqw/kiYP1HV2LYMpp0ub9qlsT3I423y59ojnTrZJRrs37m2zaC', NULL, NULL, NULL, NULL, '1', '2026-04-21 20:33:54', '2026-04-21 20:33:54', 'user');
INSERT INTO `users` (`id`, `login`, `email`, `birth_date`, `gender`, `city`, `is_private`, `password`, `avatar`, `bio`, `background_image`, `website`, `is_active`, `created_at`, `updated_at`, `role`) VALUES ('8', 'N', 'n@com', '2008-02-02', NULL, NULL, '0', '$2y$10$wQhS8eu6t495xhD4cSoNKuj8SXOgAWn3hHLQOM0r8C0KvqgnWHGZK', NULL, NULL, NULL, NULL, '1', '2026-05-07 17:31:03', '2026-05-07 17:31:03', 'user');
INSERT INTO `users` (`id`, `login`, `email`, `birth_date`, `gender`, `city`, `is_private`, `password`, `avatar`, `bio`, `background_image`, `website`, `is_active`, `created_at`, `updated_at`, `role`) VALUES ('9', 'meow', 'meow@gmail.com', '2000-02-02', NULL, NULL, '0', '$2y$10$rWfjDXTvYcCUnTUqeDPisOBkNxIEto6KzVM7ZCJLDXegTD2dxyLSa', 'uploads/profile/avatar_b96eb3c01c0b13220c36e864.gif', '', 'uploads/profile/background_e5a934ea70ce2b34cd1487f9.png', NULL, '1', '2026-06-08 21:28:23', '2026-06-08 21:29:27', 'user');

SET FOREIGN_KEY_CHECKS=1;
