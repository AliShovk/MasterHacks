-- phpMyAdmin SQL Dump
-- version 4.9.7
-- https://www.phpmyadmin.net/
--
-- Хост: 10.0.0.14:3306
-- Время создания: Фев 04 2026 г., 08:41
-- Версия сервера: 10.11.15-MariaDB-cll-lve-log
-- Версия PHP: 7.4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `masterhacks`
--
CREATE DATABASE IF NOT EXISTS `masterhacks` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
USE `masterhacks`;

-- --------------------------------------------------------

--
-- Структура таблицы `authors`
--

DROP TABLE IF EXISTS `authors`;
CREATE TABLE `authors` (
  `id` int(10) UNSIGNED NOT NULL,
  `telegram_id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(64) DEFAULT NULL,
  `first_name` varchar(128) NOT NULL DEFAULT '',
  `last_name` varchar(128) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `reputation_score` int(11) NOT NULL DEFAULT 0,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `last_active` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `authors`
--

INSERT INTO `authors` (`id`, `telegram_id`, `username`, `first_name`, `last_name`, `avatar_url`, `reputation_score`, `is_verified`, `last_active`, `created_at`, `updated_at`) VALUES
(1, 5405885462, 'AliBizCo', 'AliBiz', NULL, NULL, 410, 0, '2026-02-04 10:33:58', '2026-01-27 17:18:46', '2026-02-04 10:33:58'),
(2, 6726328452, 'Criptoniums', 'Crioti', 'Kri', NULL, 145, 0, '2026-02-03 17:49:19', '2026-01-27 17:27:55', '2026-02-03 17:49:23'),
(3, 6654223448, 'alina_858320051998', 'Алина', '🤎', NULL, 35, 0, '2026-02-03 21:35:46', '2026-01-27 18:24:36', '2026-02-03 21:35:46'),
(4, 6717391721, NULL, 'Oli', 'Shovkarov', NULL, 25, 0, '2026-01-29 14:55:38', '2026-01-29 14:39:47', '2026-01-29 14:55:38');

-- --------------------------------------------------------

--
-- Структура таблицы `comments`
--

DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `telegram_id` bigint(20) DEFAULT NULL,
  `author_name` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `comments`
--

INSERT INTO `comments` (`id`, `video_id`, `telegram_id`, `author_name`, `text`, `parent_id`, `created_at`) VALUES
(1, 102, NULL, 'Аноним', 'Роботы 💣', NULL, '2026-02-03 20:30:24');

-- --------------------------------------------------------

--
-- Структура таблицы `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `subscriber_telegram_id` bigint(20) NOT NULL,
  `author_telegram_id` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `subscriber_telegram_id`, `author_telegram_id`, `created_at`) VALUES
(1, 6726328452, 5405885462, '2026-02-03 12:42:22'),
(3, 5405885462, 6726328452, '2026-02-03 14:17:55'),
(5, 6654223448, 5405885462, '2026-02-03 18:36:27'),
(9, 6654223448, 6726328452, '2026-02-03 18:38:36');

-- --------------------------------------------------------

--
-- Структура таблицы `user_actions`
--

DROP TABLE IF EXISTS `user_actions`;
CREATE TABLE `user_actions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `telegram_id` bigint(20) UNSIGNED NOT NULL,
  `action_type` varchar(32) NOT NULL,
  `video_id` int(10) UNSIGNED DEFAULT NULL,
  `target_telegram_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `fingerprint` char(32) DEFAULT NULL,
  `points_earned` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `user_actions`
--

INSERT INTO `user_actions` (`id`, `telegram_id`, `action_type`, `video_id`, `target_telegram_id`, `ip_address`, `user_agent`, `fingerprint`, `points_earned`, `created_at`) VALUES
(1, 5405885462, 'register', NULL, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 10, '2026-01-27 17:18:46'),
(2, 5405885462, 'upload', 1, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-27 17:24:23'),
(3, 6726328452, 'register', NULL, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 10, '2026-01-27 17:27:55'),
(4, 6726328452, 'upload', 2, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-27 17:28:22'),
(5, 5405885462, 'upload', 3, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-27 17:39:10'),
(6, 6654223448, 'register', NULL, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 10, '2026-01-27 18:24:36'),
(7, 6654223448, 'upload', 4, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-27 18:25:28'),
(8, 6654223448, 'upload', 5, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-27 18:29:26'),
(9, 6726328452, 'upload', 6, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 09:57:06'),
(10, 5405885462, 'upload', 7, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 11:24:35'),
(11, 6726328452, 'upload', 8, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 12:39:50'),
(12, 5405885462, 'upload', 9, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 13:31:01'),
(13, 5405885462, 'upload', 10, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 13:31:22'),
(14, 5405885462, 'upload', 11, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 13:31:38'),
(15, 5405885462, 'upload', 12, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 13:32:06'),
(16, 5405885462, 'upload', 13, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 13:32:13'),
(17, 5405885462, 'upload', 14, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 13:42:11'),
(18, 5405885462, 'upload', 15, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 13:42:33'),
(19, 5405885462, 'upload', 16, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 14:03:45'),
(20, 5405885462, 'upload', 17, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 14:03:51'),
(21, 5405885462, 'upload', 18, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 14:03:59'),
(22, 6726328452, 'upload', 19, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 15:07:11'),
(23, 6726328452, 'upload', 20, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 15:07:36'),
(24, 6726328452, 'upload', 21, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 15:07:46'),
(25, 6726328452, 'upload', 22, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 15:08:15'),
(26, 6726328452, 'upload', 23, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 15:08:21'),
(27, 6726328452, 'upload', 24, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 15:08:35'),
(28, 6726328452, 'upload', 25, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 15:34:55'),
(29, 6726328452, 'upload', 26, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 15:35:28'),
(30, 5405885462, 'upload', 27, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 21:16:26'),
(31, 5405885462, 'upload', 28, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 21:17:26'),
(32, 5405885462, 'upload', 29, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 21:17:47'),
(33, 5405885462, 'upload', 30, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 21:17:52'),
(34, 5405885462, 'upload', 31, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 21:18:36'),
(35, 5405885462, 'upload', 32, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 21:18:57'),
(36, 5405885462, 'upload', 33, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 21:19:29'),
(37, 5405885462, 'upload', 34, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 21:32:58'),
(38, 5405885462, 'upload', 35, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 21:33:33'),
(39, 5405885462, 'upload', 36, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 22:21:10'),
(40, 5405885462, 'upload', 37, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 22:37:12'),
(41, 5405885462, 'upload', 38, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 22:39:28'),
(42, 5405885462, 'upload', 39, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-28 22:41:50'),
(43, 6717391721, 'register', NULL, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 10, '2026-01-29 14:39:47'),
(44, 6717391721, 'upload', 40, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-29 14:54:19'),
(45, 6654223448, 'upload', 41, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-29 19:46:29'),
(46, 5405885462, 'upload', 42, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-30 11:56:51'),
(47, 5405885462, 'upload', 43, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-30 12:08:47'),
(48, 5405885462, 'upload', 44, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-30 12:11:18'),
(49, 5405885462, 'upload', 45, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-30 12:11:21'),
(50, 5405885462, 'upload', 46, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-30 12:11:26'),
(51, 5405885462, 'upload', 47, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-30 12:13:36'),
(52, 5405885462, 'upload', 48, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-30 12:13:47'),
(53, 5405885462, 'upload', 49, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-30 12:13:53'),
(54, 5405885462, 'upload', 50, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-30 12:21:47'),
(55, 5405885462, 'upload', 51, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-30 12:26:33'),
(56, 5405885462, 'upload', 52, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-01-30 12:32:18'),
(57, 5405885462, 'upload', 53, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 13:15:59'),
(58, 5405885462, 'upload', 54, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 13:22:11'),
(59, 5405885462, 'upload', 55, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 13:27:45'),
(60, 5405885462, 'upload', 56, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 13:31:30'),
(61, 5405885462, 'upload', 57, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 13:36:55'),
(62, 5405885462, 'upload', 58, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 13:50:46'),
(63, 5405885462, 'upload', 59, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 14:00:42'),
(64, 5405885462, 'upload', 60, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 14:06:39'),
(65, 5405885462, 'upload', 61, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 14:16:15'),
(66, 5405885462, 'upload', 62, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 14:19:37'),
(67, 5405885462, 'upload', 63, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 14:19:38'),
(68, 5405885462, 'upload', 64, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 14:19:48'),
(69, 5405885462, 'upload', 65, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 14:19:57'),
(70, 5405885462, 'upload', 66, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 14:40:35'),
(71, 5405885462, 'upload', 67, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 14:41:10'),
(72, 5405885462, 'upload', 68, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 14:41:29'),
(73, 6726328452, 'upload', 69, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 14:48:07'),
(74, 6726328452, 'upload', 70, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 15:04:15'),
(75, 0, 'like', 70, NULL, '95.153.168.39', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/125.0.6422.80 Mobile/15E148 Safari/604.1', '0149f1b3e63b0475b3933b25cc48ece1', 0, '2026-02-03 15:05:34'),
(76, 0, 'like', 70, NULL, '95.153.168.39', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/125.0.6422.80 Mobile/15E148 Safari/604.1', '0149f1b3e63b0475b3933b25cc48ece1', 0, '2026-02-03 15:05:35'),
(77, 0, 'like', 70, NULL, '95.153.168.39', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/125.0.6422.80 Mobile/15E148 Safari/604.1', '0149f1b3e63b0475b3933b25cc48ece1', 0, '2026-02-03 15:05:38'),
(78, 0, 'like', 70, NULL, '95.153.168.39', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/125.0.6422.80 Mobile/15E148 Safari/604.1', '0149f1b3e63b0475b3933b25cc48ece1', 0, '2026-02-03 15:05:41'),
(79, 5405885462, 'upload', 71, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 15:11:37'),
(80, 5405885462, 'upload', 72, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 15:20:16'),
(81, 6726328452, 'upload', 73, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 15:21:06'),
(82, 0, 'like', 73, NULL, '85.174.180.71', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/17.5 Mobile/15A5370a Safari/602.1', '125f82b69884a150f7bcf3b1fb371e78', 0, '2026-02-03 15:23:51'),
(83, 6726328452, 'upload', 74, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 15:34:50'),
(84, 5405885462, 'upload', 75, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 15:41:39'),
(85, 6726328452, 'upload', 76, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 15:49:57'),
(86, 6726328452, 'upload', 77, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 16:02:58'),
(87, 6726328452, 'upload', 78, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 16:53:17'),
(88, 5405885462, 'upload', 79, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 16:57:54'),
(89, 0, 'like', 78, NULL, '95.153.168.39', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.8.5 Mobile/19H394 Safari/604.1', '2805501dd18f239fe094772e5bc882e5', 0, '2026-02-03 17:03:49'),
(90, 6726328452, 'upload', 80, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 17:28:25'),
(91, 6726328452, 'upload', 81, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 17:44:38'),
(92, 6726328452, 'upload', 82, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 17:46:18'),
(93, 6726328452, 'upload', 83, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 17:46:23'),
(94, 6726328452, 'upload', 84, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 17:47:26'),
(95, 6726328452, 'upload', 85, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 17:48:06'),
(96, 6726328452, 'upload', 86, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 17:49:23'),
(97, 5405885462, 'upload', 87, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 17:50:56'),
(98, 5405885462, 'upload', 88, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 17:55:54'),
(99, 5405885462, 'upload', 89, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 17:55:58'),
(100, 5405885462, 'upload', 90, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 17:56:28'),
(101, 5405885462, 'upload', 91, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 17:56:39'),
(102, 0, 'like', 82, NULL, '85.174.180.71', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'e57581cdfd39045e6450f59f87664289', 0, '2026-02-03 18:27:29'),
(103, 0, 'like', 82, NULL, '85.174.180.71', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'e57581cdfd39045e6450f59f87664289', 0, '2026-02-03 18:27:34'),
(104, 0, 'like', 82, NULL, '85.174.180.71', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'e57581cdfd39045e6450f59f87664289', 0, '2026-02-03 18:27:34'),
(105, 0, 'like', 82, NULL, '85.174.180.71', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'e57581cdfd39045e6450f59f87664289', 0, '2026-02-03 18:27:34'),
(106, 5405885462, 'like', 89, NULL, '85.174.180.71', 'Mozilla/5.0 (Linux; Android 13; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.59 Safari/537.36', '2253e984a8c9327dd30a07072f70edfd', 0, '2026-02-03 18:30:32'),
(107, 5405885462, 'like', 83, NULL, '85.174.180.71', 'Mozilla/5.0 (Linux; Android 13; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.59 Safari/537.36', '2253e984a8c9327dd30a07072f70edfd', 0, '2026-02-03 18:30:43'),
(108, 5405885462, 'like', 87, NULL, '85.174.180.71', 'Mozilla/5.0 (Linux; Android 13; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.59 Safari/537.36', '2253e984a8c9327dd30a07072f70edfd', 0, '2026-02-03 18:30:51'),
(109, 5405885462, 'upload', 92, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 18:34:01'),
(110, 5405885462, 'upload', 93, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 18:34:26'),
(111, 5405885462, 'upload', 94, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 18:35:38'),
(112, 5405885462, 'upload', 95, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 18:38:31'),
(113, 5405885462, 'upload', 96, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 18:40:52'),
(114, 5405885462, 'upload', 97, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 18:41:24'),
(115, 5405885462, 'upload', 98, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 18:55:02'),
(116, 5405885462, 'upload', 99, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 18:55:29'),
(117, 5405885462, 'upload', 100, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 18:56:43'),
(118, 0, 'like', 92, NULL, '95.153.168.139', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/125.0.6422.80 Mobile/15E148 Safari/604.1', '95c5c836de82920922c6666817bcdf38', 0, '2026-02-03 19:08:11'),
(119, 0, 'like', 96, NULL, '95.153.168.139', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/125.0.6422.80 Mobile/15E148 Safari/604.1', '95c5c836de82920922c6666817bcdf38', 0, '2026-02-03 19:08:55'),
(120, 0, 'like', 96, NULL, '95.153.168.139', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/125.0.6422.80 Mobile/15E148 Safari/604.1', '95c5c836de82920922c6666817bcdf38', 0, '2026-02-03 19:08:55'),
(121, 0, 'like', 96, NULL, '95.153.168.139', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/125.0.6422.80 Mobile/15E148 Safari/604.1', '95c5c836de82920922c6666817bcdf38', 0, '2026-02-03 19:08:55'),
(122, 0, 'like', 96, NULL, '95.153.168.139', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/125.0.6422.80 Mobile/15E148 Safari/604.1', '95c5c836de82920922c6666817bcdf38', 0, '2026-02-03 19:08:55'),
(123, 0, 'like', 87, NULL, '85.174.180.71', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'e57581cdfd39045e6450f59f87664289', 0, '2026-02-03 19:46:26'),
(124, 5405885462, 'upload', 101, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 20:46:47'),
(125, 5405885462, 'upload', 102, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 20:49:56'),
(126, 5405885462, 'upload', 103, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 20:53:03'),
(127, 5405885462, 'upload', 104, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 21:04:48'),
(128, 5405885462, 'upload', 105, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 21:06:49'),
(129, 5405885462, 'upload', 106, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 21:11:49'),
(130, 5405885462, 'upload', 107, NULL, '91.108.5.136', NULL, '823b285613536e3c7f1cf686161c4722', 5, '2026-02-03 21:19:52'),
(131, 0, 'like', 99, NULL, '195.189.96.64', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.2 Mobile/15E148 Safari/604.1', '1b12c37b0d1c71fa8a0c362892193c8f', 0, '2026-02-03 21:35:27'),
(132, 0, 'like', 86, NULL, '85.174.180.71', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/125.0.6422.80 Mobile/15E148 Safari/604.1', '9c51e0d20ceb047219fdf14281e03db5', 0, '2026-02-03 23:25:25'),
(133, 0, 'like', 91, NULL, '85.174.180.71', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/125.0.6422.80 Mobile/15E148 Safari/604.1', 'ad7670423b8bdb2d9eff2b2a3a846d0c', 0, '2026-02-04 09:42:32');

-- --------------------------------------------------------

--
-- Структура таблицы `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `telegram_id` bigint(20) NOT NULL,
  `token` varchar(64) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `videos`
--

DROP TABLE IF EXISTS `videos`;
CREATE TABLE `videos` (
  `id` int(10) UNSIGNED NOT NULL,
  `telegram_id` bigint(20) UNSIGNED NOT NULL,
  `file_hash` char(32) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_type` enum('video','image') NOT NULL DEFAULT 'video',
  `description` text DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `likes` int(11) NOT NULL DEFAULT 0,
  `comments_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `published_at` datetime DEFAULT NULL,
  `views` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `videos`
--

INSERT INTO `videos` (`id`, `telegram_id`, `file_hash`, `filename`, `file_type`, `description`, `duration`, `status`, `likes`, `comments_count`, `created_at`, `published_at`, `views`) VALUES
(52, 5405885462, '22192f63a7b3aed2d903c9bff12e962a', 'video_1769765538_5405885462_22192f63a7b3aed2d903c9bff12e962a.mp4', 'video', NULL, 6, 'approved', 0, 0, '2026-01-30 12:32:18', '2026-01-30 12:40:08', 0),
(53, 5405885462, '79aed1f4fdf9fe3b4a29354c711b960b', 'video_1770113759_5405885462_79aed1f4fdf9fe3b4a29354c711b960b.mp4', 'video', NULL, 12, 'approved', 0, 0, '2026-02-03 13:15:59', '2026-02-03 13:16:24', 0),
(54, 5405885462, 'b4bf76844ae7c8b7a17bafdc61f7ba52', 'video_1770114131_5405885462_b4bf76844ae7c8b7a17bafdc61f7ba52.mp4', 'video', NULL, 14, 'approved', 0, 0, '2026-02-03 13:22:11', '2026-02-03 13:22:29', 0),
(55, 5405885462, '7dc9bb11ee44a08a1546a0cb72564915', 'video_1770114465_5405885462_7dc9bb11ee44a08a1546a0cb72564915.mp4', 'video', NULL, 8, 'approved', 0, 0, '2026-02-03 13:27:45', '2026-02-03 13:28:04', 0),
(56, 5405885462, '0f745dfc5849ef23058bc3a5d8775abb', 'video_1770114690_5405885462_0f745dfc5849ef23058bc3a5d8775abb.mp4', 'video', NULL, 12, 'approved', 0, 0, '2026-02-03 13:31:30', '2026-02-03 13:31:46', 0),
(57, 5405885462, 'd3dfe0ddfbad307dc2cc5fb60752b919', 'video_1770115015_5405885462_d3dfe0ddfbad307dc2cc5fb60752b919.mp4', 'video', NULL, 9, 'approved', 0, 0, '2026-02-03 13:36:55', '2026-02-03 13:37:12', 0),
(58, 5405885462, '50e3a7070a8f52ca6153379b60c55000', 'video_1770115846_5405885462_50e3a7070a8f52ca6153379b60c55000.mp4', 'video', NULL, 6, 'approved', 0, 0, '2026-02-03 13:50:46', '2026-02-03 13:51:01', 0),
(59, 5405885462, '050994c4b73913ed468b24a680f269bd', 'video_1770116442_5405885462_050994c4b73913ed468b24a680f269bd.mp4', 'video', NULL, 7, 'approved', 0, 0, '2026-02-03 14:00:42', '2026-02-03 14:00:54', 0),
(60, 5405885462, '0646a4d4c65b5ea710841cae30d96504', 'video_1770116799_5405885462_0646a4d4c65b5ea710841cae30d96504.mp4', 'video', NULL, 16, 'approved', 0, 0, '2026-02-03 14:06:39', '2026-02-03 14:06:49', 0),
(61, 5405885462, '9ea4f0fab7e1f42b996efc31c3e41ee6', 'video_1770117375_5405885462_9ea4f0fab7e1f42b996efc31c3e41ee6.mp4', 'video', NULL, 5, 'approved', 0, 0, '2026-02-03 14:16:15', '2026-02-03 14:16:28', 0),
(62, 5405885462, '19befc09652513ec91d378fd2a5c36fe', 'video_1770117577_5405885462_19befc09652513ec91d378fd2a5c36fe.mp4', 'video', NULL, 16, 'approved', 0, 0, '2026-02-03 14:19:37', '2026-02-03 14:20:18', 0),
(63, 5405885462, '9c26b67260504a474635159e9e6e1cbe', 'video_1770117578_5405885462_9c26b67260504a474635159e9e6e1cbe.mp4', 'video', NULL, 9, 'approved', 0, 0, '2026-02-03 14:19:38', '2026-02-03 14:20:15', 0),
(64, 5405885462, '130e796cb890f0118bde0ddf4e3daa36', 'video_1770117588_5405885462_130e796cb890f0118bde0ddf4e3daa36.mp4', 'video', NULL, 21, 'approved', 0, 0, '2026-02-03 14:19:48', '2026-02-03 14:20:12', 0),
(65, 5405885462, 'd7dbe837e207bdc25cdbdc16b777bb25', 'video_1770117597_5405885462_d7dbe837e207bdc25cdbdc16b777bb25.mp4', 'video', NULL, 5, 'approved', 0, 0, '2026-02-03 14:19:57', '2026-02-03 14:20:09', 0),
(66, 5405885462, 'c4bd0fb1720e473d740b988667b087a4', 'video_1770118835_5405885462_c4bd0fb1720e473d740b988667b087a4.mp4', 'video', NULL, 22, 'approved', 0, 0, '2026-02-03 14:40:35', '2026-02-03 14:41:08', 0),
(67, 5405885462, '12d9e6a8810aa7377f490481134fd5b4', 'video_1770118870_5405885462_12d9e6a8810aa7377f490481134fd5b4.mp4', 'video', NULL, 18, 'approved', 0, 0, '2026-02-03 14:41:10', '2026-02-03 14:41:28', 0),
(68, 5405885462, '337f32909a2d8a2c50b802c2e999ec95', 'video_1770118889_5405885462_337f32909a2d8a2c50b802c2e999ec95.mp4', 'video', NULL, 23, 'approved', 0, 0, '2026-02-03 14:41:29', '2026-02-03 14:43:21', 0),
(69, 6726328452, '1464308538fa93c5d69af1809900a049', 'video_1770119287_6726328452_1464308538fa93c5d69af1809900a049.mp4', 'video', NULL, 16, 'approved', 0, 0, '2026-02-03 14:48:07', '2026-02-03 14:48:31', 0),
(70, 6726328452, '77b1a550f4470b8c53e9721dc54f11f3', 'video_1770120255_6726328452_77b1a550f4470b8c53e9721dc54f11f3.mp4', 'video', NULL, 26, 'approved', 4, 0, '2026-02-03 15:04:15', '2026-02-03 15:04:30', 0),
(71, 5405885462, '3ffe8ab585ef74b07794f8206071def8', 'video_1770120697_5405885462_3ffe8ab585ef74b07794f8206071def8.mp4', 'video', NULL, 16, 'approved', 0, 0, '2026-02-03 15:11:37', '2026-02-03 15:11:50', 0),
(72, 5405885462, '27c582b1dfbbf7d53592f9353d214ce4', 'video_1770121216_5405885462_27c582b1dfbbf7d53592f9353d214ce4.mp4', 'video', NULL, 20, 'approved', 0, 0, '2026-02-03 15:20:16', '2026-02-03 15:20:22', 0),
(73, 6726328452, '0fbc3085767c65f8036a40fd39afab9f', 'video_1770121266_6726328452_0fbc3085767c65f8036a40fd39afab9f.mp4', 'video', NULL, 11, 'approved', 1, 0, '2026-02-03 15:21:06', '2026-02-03 15:21:15', 0),
(74, 6726328452, 'cd254703bcb4aefebc7090d8afc6496e', 'video_1770122090_6726328452_cd254703bcb4aefebc7090d8afc6496e.mp4', 'video', NULL, 8, 'approved', 0, 0, '2026-02-03 15:34:50', '2026-02-03 15:35:22', 0),
(75, 5405885462, '1f8063508f7624b5c8400a61b929c752', 'video_1770122499_5405885462_1f8063508f7624b5c8400a61b929c752.mp4', 'video', NULL, 8, 'approved', 0, 0, '2026-02-03 15:41:39', '2026-02-03 15:41:50', 0),
(76, 6726328452, 'e4cfe33528c0c2f2aec5a9605cb0f608', 'video_1770122997_6726328452_e4cfe33528c0c2f2aec5a9605cb0f608.mp4', 'video', NULL, 16, 'approved', 0, 0, '2026-02-03 15:49:57', '2026-02-03 15:50:09', 0),
(77, 6726328452, '8ded926121178ef073fadcdbba6b95bd', 'video_1770123778_6726328452_8ded926121178ef073fadcdbba6b95bd.mp4', 'video', NULL, 16, 'approved', 0, 0, '2026-02-03 16:02:58', '2026-02-03 16:03:24', 0),
(80, 6726328452, 'a7dc06dd4c1e9ef7e7e75eced8fa4175', 'video_1770128905_6726328452_a7dc06dd4c1e9ef7e7e75eced8fa4175.mp4', 'video', NULL, 19, 'approved', 0, 0, '2026-02-03 17:28:25', '2026-02-03 17:28:38', 0),
(81, 6726328452, '913fd708b60f2b1c2996e04c3638f127', 'video_1770129878_6726328452_913fd708b60f2b1c2996e04c3638f127.mp4', 'video', NULL, 7, 'approved', 0, 0, '2026-02-03 17:44:38', '2026-02-03 18:27:05', 0),
(82, 6726328452, '5a4c31dacb48936cf17387be3b7c33e0', 'video_1770129978_6726328452_5a4c31dacb48936cf17387be3b7c33e0.mp4', 'video', NULL, 9, 'approved', 4, 0, '2026-02-03 17:46:18', '2026-02-03 18:27:07', 0),
(83, 6726328452, '03671d2b8f0b897f7a597d47cd53f528', 'video_1770129983_6726328452_03671d2b8f0b897f7a597d47cd53f528.mp4', 'video', NULL, 11, 'approved', 1, 0, '2026-02-03 17:46:23', '2026-02-03 18:27:01', 0),
(84, 6726328452, '8de07466a66ebbef5c004bdeafb8442a', 'video_1770130046_6726328452_8de07466a66ebbef5c004bdeafb8442a.mp4', 'video', NULL, 13, 'approved', 0, 0, '2026-02-03 17:47:26', '2026-02-03 18:26:59', 0),
(85, 6726328452, 'c6c4c000756f496ffe8ef4b986f9a347', 'video_1770130086_6726328452_c6c4c000756f496ffe8ef4b986f9a347.mp4', 'video', NULL, 11, 'approved', 0, 0, '2026-02-03 17:48:06', '2026-02-03 18:26:57', 0),
(86, 6726328452, '9a1a13f06961436bc0c996b623b5bcca', 'video_1770130163_6726328452_9a1a13f06961436bc0c996b623b5bcca.mp4', 'video', NULL, 9, 'approved', 1, 0, '2026-02-03 17:49:23', '2026-02-03 18:26:55', 0),
(87, 5405885462, '678c336bcef4efbd5f7359e633c87ac9', 'video_1770130256_5405885462_678c336bcef4efbd5f7359e633c87ac9.mp4', 'video', NULL, 30, 'approved', 2, 0, '2026-02-03 17:50:56', '2026-02-03 18:26:54', 0),
(88, 5405885462, '1339c4c5550b3ec4b5db6e34460f1ce9', 'video_1770130554_5405885462_1339c4c5550b3ec4b5db6e34460f1ce9.mp4', 'video', NULL, 22, 'approved', 0, 0, '2026-02-03 17:55:54', '2026-02-03 18:26:35', 0),
(89, 5405885462, '8d9f5a42e629dc6e8e9e5c114c9874ee', 'video_1770130558_5405885462_8d9f5a42e629dc6e8e9e5c114c9874ee.mp4', 'video', NULL, 7, 'approved', 1, 0, '2026-02-03 17:55:58', '2026-02-03 18:27:03', 0),
(90, 5405885462, '4af48c8bc4aa1e3e22d90e8f27225e2d', 'video_1770130588_5405885462_4af48c8bc4aa1e3e22d90e8f27225e2d.mp4', 'video', NULL, 15, 'approved', 0, 0, '2026-02-03 17:56:28', '2026-02-03 18:26:38', 0),
(91, 5405885462, '50f376ddc4a71895b80061a3faaa1075', 'video_1770130599_5405885462_50f376ddc4a71895b80061a3faaa1075.mp4', 'video', NULL, 17, 'approved', 1, 0, '2026-02-03 17:56:39', '2026-02-03 18:26:48', 0),
(92, 5405885462, 'f47b59f6a6246eed9a5be60bbcbbfda2', 'video_1770132841_5405885462_f47b59f6a6246eed9a5be60bbcbbfda2.mp4', 'video', NULL, 14, 'approved', 1, 0, '2026-02-03 18:34:01', '2026-02-03 18:42:20', 0),
(93, 5405885462, '7990895c37bc2d438717983cd8a9614c', 'video_1770132866_5405885462_7990895c37bc2d438717983cd8a9614c.mp4', 'video', NULL, 27, 'approved', 0, 0, '2026-02-03 18:34:26', '2026-02-03 18:42:18', 0),
(94, 5405885462, 'f97b6fe719332d51c378d1b534b8aa09', 'video_1770132938_5405885462_f97b6fe719332d51c378d1b534b8aa09.mp4', 'video', NULL, 32, 'approved', 0, 0, '2026-02-03 18:35:38', '2026-02-03 18:42:15', 0),
(95, 5405885462, '627e4894b1dcaa4d90743ffd46e9d4a1', 'video_1770133111_5405885462_627e4894b1dcaa4d90743ffd46e9d4a1.mp4', 'video', NULL, 22, 'approved', 0, 0, '2026-02-03 18:38:31', '2026-02-03 18:42:13', 0),
(96, 5405885462, '9e9528c4fcf5380c75e7a02d36eb47fe', 'video_1770133252_5405885462_9e9528c4fcf5380c75e7a02d36eb47fe.mp4', 'video', NULL, 23, 'approved', 4, 0, '2026-02-03 18:40:52', '2026-02-03 18:42:10', 0),
(97, 5405885462, '831c2a02fdb7882c144e723c66112c3a', 'video_1770133284_5405885462_831c2a02fdb7882c144e723c66112c3a.mp4', 'video', NULL, 9, 'approved', 0, 0, '2026-02-03 18:41:24', '2026-02-03 18:42:09', 0),
(98, 5405885462, '1978b069015c2ef55e98eaee33ff380a', 'video_1770134102_5405885462_1978b069015c2ef55e98eaee33ff380a.mp4', 'video', NULL, 28, 'approved', 0, 0, '2026-02-03 18:55:02', '2026-02-03 19:24:07', 0),
(99, 5405885462, 'cf4d62e2c13203b4d8aacffe64085ddb', 'video_1770134129_5405885462_cf4d62e2c13203b4d8aacffe64085ddb.mp4', 'video', NULL, 14, 'approved', 1, 0, '2026-02-03 18:55:29', '2026-02-03 19:24:09', 0),
(100, 5405885462, '3a8cdd9af844b26b10396fc9af988b5a', 'video_1770134203_5405885462_3a8cdd9af844b26b10396fc9af988b5a.mp4', 'video', NULL, 12, 'approved', 0, 0, '2026-02-03 18:56:43', '2026-02-03 19:24:04', 0),
(101, 5405885462, 'bf4cf5687411e8bb07dbacd7df81e629', 'video_1770140807_5405885462_bf4cf5687411e8bb07dbacd7df81e629.mp4', 'video', NULL, 14, 'approved', 0, 0, '2026-02-03 20:46:47', '2026-02-03 23:21:27', 4),
(102, 5405885462, 'ae9f94ede578cbac8716e7be1c89d421', 'video_1770140996_5405885462_ae9f94ede578cbac8716e7be1c89d421.mp4', 'video', NULL, 32, 'approved', 0, 1, '2026-02-03 20:49:56', '2026-02-03 23:21:04', 3),
(103, 5405885462, '37938cc0ce484226aa09d621d794f144', 'video_1770141183_5405885462_37938cc0ce484226aa09d621d794f144.mp4', 'video', NULL, 24, 'approved', 0, 0, '2026-02-03 20:53:03', '2026-02-03 23:20:56', 3),
(104, 5405885462, '78a427315322eb4012f28b3fbd1de1d6', 'video_1770141888_5405885462_78a427315322eb4012f28b3fbd1de1d6.mp4', 'video', NULL, 27, 'approved', 0, 0, '2026-02-03 21:04:48', '2026-02-03 23:20:25', 2),
(105, 5405885462, '94ff41b92ce659d2bef030acc54e1522', 'video_1770142009_5405885462_94ff41b92ce659d2bef030acc54e1522.mp4', 'video', NULL, 26, 'approved', 0, 0, '2026-02-03 21:06:49', '2026-02-03 23:20:11', 1),
(106, 5405885462, 'b2b4509daca43000bacacfa33ca2abd1', 'video_1770142309_5405885462_b2b4509daca43000bacacfa33ca2abd1.mp4', 'video', NULL, 14, 'approved', 0, 0, '2026-02-03 21:11:49', '2026-02-03 23:19:47', 0),
(107, 5405885462, 'f38a6ba4a1c8422ec553c1dfce5c88d1', 'video_1770142792_5405885462_f38a6ba4a1c8422ec553c1dfce5c88d1.mp4', 'video', NULL, 23, 'approved', 0, 0, '2026-02-03 21:19:52', '2026-02-03 23:19:55', 0);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `authors`
--
ALTER TABLE `authors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_authors_telegram_id` (`telegram_id`),
  ADD KEY `idx_authors_username` (`username`);

--
-- Индексы таблицы `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_video` (`video_id`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Индексы таблицы `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_subscription` (`subscriber_telegram_id`,`author_telegram_id`),
  ADD KEY `idx_subscriber` (`subscriber_telegram_id`),
  ADD KEY `idx_author` (`author_telegram_id`);

--
-- Индексы таблицы `user_actions`
--
ALTER TABLE `user_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_actions_telegram_id` (`telegram_id`),
  ADD KEY `idx_user_actions_video_id` (`video_id`),
  ADD KEY `idx_user_actions_created_at` (`created_at`);

--
-- Индексы таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_telegram_id` (`telegram_id`),
  ADD UNIQUE KEY `uniq_token` (`token`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Индексы таблицы `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_videos_file_hash` (`file_hash`),
  ADD KEY `idx_videos_status_date` (`status`,`created_at`),
  ADD KEY `idx_videos_telegram_id` (`telegram_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `authors`
--
ALTER TABLE `authors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `user_actions`
--
ALTER TABLE `user_actions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT для таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT для таблицы `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
