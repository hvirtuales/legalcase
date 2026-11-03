-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 06-07-2026 a las 06:39:19
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `cuentas_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `company_id` int(11) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `token` varchar(500) NOT NULL,
  `abilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`abilities`)),
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `app_settings`
--

CREATE TABLE `app_settings` (
  `id` int(11) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','image','boolean') NOT NULL DEFAULT 'text',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `app_settings`
--

INSERT INTO `app_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `created_at`, `updated_at`) VALUES
(1, 'app_name', '', 'text', NULL, '2025-12-19 17:38:58'),
(2, 'app_description', 'Sistema completo de autenticación multi-método con gestión de usuarios, roles y permisos', 'text', NULL, '2025-12-19 17:38:58'),
(3, 'login_image', '/uploads/settings/login_1766001015.jpg', 'image', NULL, '2025-12-17 19:50:15'),
(4, 'login_background', 'solid', 'text', NULL, '2025-12-19 17:38:58'),
(5, 'favicon', '', 'image', NULL, '2025-12-16 22:22:24'),
(6, 'auth_methods_enabled', '{\"password\":true,\"magic_link\":true,\"oauth\":false,\"ldap\":false,\"api_token\":true}', 'text', NULL, '2025-12-16 22:22:24'),
(19, 'public_registration_enabled', '1', 'boolean', '2025-12-17 18:27:05', '2025-12-19 17:38:58'),
(20, 'auth_magic_link_enabled', '0', 'boolean', '2025-12-17 18:27:05', '2025-12-19 17:38:58'),
(21, 'auth_oauth_enabled', '0', 'boolean', '2025-12-17 18:27:05', '2025-12-19 17:38:58'),
(22, 'auth_max_attempts', '5', 'text', '2025-12-17 18:27:05', '2025-12-19 17:38:58'),
(23, 'bg_color_primary', '#fafafa', 'text', '2025-12-17 19:50:15', '2025-12-19 17:38:58'),
(24, 'bg_color_secondary', '#000000', 'text', '2025-12-17 19:50:15', '2025-12-19 17:38:58'),
(25, 'background_image', '/uploads/settings/bg_1766001016.jpg', 'image', '2025-12-17 19:50:16', '2025-12-17 19:50:16'),
(26, 'theme_color_primary', '#EC4899', '', NULL, '2025-12-19 17:38:58'),
(27, 'theme_color_secondary', '#b90b26', '', NULL, NULL),
(28, 'support_phone', '573143271001', 'text', '2025-12-19 17:30:58', '2025-12-19 17:38:58'),
(29, 'support_email', 'johnmanuelleon@gmail.com', 'text', '2025-12-19 17:30:58', '2025-12-19 17:38:58'),
(30, 'app_logo', '/uploads/settings/logo_1766165458.png', 'image', '2025-12-19 17:30:59', '2025-12-19 17:30:59'),
(31, 'smtp_host', '', 'text', '2025-12-19 17:38:58', '2025-12-19 17:38:58'),
(32, 'smtp_port', '587', 'text', '2025-12-19 17:38:58', '2025-12-19 17:38:58'),
(33, 'smtp_user', '', 'text', '2025-12-19 17:38:58', '2025-12-19 17:38:58'),
(34, 'smtp_crypto', '', 'text', '2025-12-19 17:38:58', '2025-12-19 17:38:58'),
(35, 'smtp_from_email', '', 'text', '2025-12-19 17:38:58', '2025-12-19 17:38:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `event` varchar(50) NOT NULL,
  `auditable_type` varchar(100) NOT NULL,
  `auditable_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `url` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `event`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `url`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:12:48'),
(2, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 14:16:43\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 14:16:43\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":null,\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 14:16:43\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 14:16:43\",\"updated_by\":null}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:16:43'),
(3, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:16:43'),
(4, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:28:26'),
(5, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 14:29:00\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 14:29:00\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":null,\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 14:29:00\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 14:29:00\",\"updated_by\":null}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:29:00'),
(6, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:29:00'),
(7, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:31:55'),
(8, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 14:32:46\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 14:32:46\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":null,\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 14:32:46\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 14:32:46\",\"updated_by\":null}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:32:46'),
(9, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:32:46'),
(10, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:42:42'),
(11, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 14:43:04\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 14:43:04\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":null,\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 14:43:04\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 14:43:04\",\"updated_by\":null}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:43:04'),
(12, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:43:04'),
(13, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:45:00'),
(14, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 14:54:36\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 14:54:36\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":null,\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 14:54:36\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 14:54:36\",\"updated_by\":null}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:54:36'),
(15, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36 Edg/141.0.0.0', '2025-12-17 14:54:36'),
(16, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 15:05:45'),
(17, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 15:15:58\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 15:15:58\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":null,\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 15:15:58\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 15:15:58\",\"updated_by\":null}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 15:15:58'),
(18, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 15:15:58'),
(19, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:06:06'),
(20, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:07:09\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:07:09\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":null,\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 16:07:09\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 16:07:09\",\"updated_by\":null}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:07:09'),
(21, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:07:09'),
(22, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:19:41'),
(23, NULL, 'created', 'App\\Models\\UserModel', 3, NULL, '{\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":true,\"auth_method\":\"password\",\"preferred_language\":\"es\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-17 16:20:36\"}', '/code/index.php/register', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:20:36'),
(24, 3, 'user_registered', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/index.php/register', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:20:36'),
(25, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:20:53\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-17 16:20:53\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":null,\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 16:20:53\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 16:20:53\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:20:53'),
(26, 3, 'login', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:20:53'),
(27, 3, 'logout', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:21:59'),
(28, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:22:04\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:22:04\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":null,\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 16:22:04\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 16:22:04\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:22:04'),
(29, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:22:04'),
(30, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:22:04\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:33:00\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"first_name\":\"Super\",\"last_name\":\"Admin\",\"email\":\"admin@example.com\",\"preferred_language\":\"es\",\"updated_at\":\"2025-12-17 16:33:00\",\"updated_by\":1}', '/code/index.php/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:33:00'),
(31, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:22:04\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:33:37\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"en\",\"updated_at\":\"2025-12-17 16:33:37\",\"updated_by\":1}', '/code/index.php/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:33:37'),
(32, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"en\"}', '/code/index.php/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:33:37'),
(33, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:22:04\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:33:42\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-17 16:33:42\",\"updated_by\":1}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:33:42'),
(34, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"es\"}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:33:42'),
(35, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:35:13'),
(36, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:35:27\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:35:27\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 16:35:27\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 16:35:27\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:35:27'),
(37, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:35:27'),
(38, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:35:27\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:36:09\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-17 16:36:09\",\"updated_by\":1}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:36:09'),
(39, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"es\"}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:36:09'),
(40, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:35:27\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:36:12\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"en\",\"updated_at\":\"2025-12-17 16:36:12\",\"updated_by\":1}', '/code/index.php/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:36:12'),
(41, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"en\"}', '/code/index.php/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:36:12'),
(42, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:35:27\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:36:22\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-17 16:36:22\",\"updated_by\":1}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:36:22'),
(43, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"es\"}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:36:22'),
(44, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:35:27\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:36:24\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"en\",\"updated_at\":\"2025-12-17 16:36:24\",\"updated_by\":1}', '/code/index.php/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:36:24'),
(45, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"en\"}', '/code/index.php/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:36:24'),
(46, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:35:27\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:44:27\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-17 16:44:27\",\"updated_by\":1}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:44:27'),
(47, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"es\"}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:44:27'),
(48, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:35:27\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:44:30\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"en\",\"updated_at\":\"2025-12-17 16:44:30\",\"updated_by\":1}', '/code/index.php/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:44:30'),
(49, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"en\"}', '/code/index.php/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:44:30'),
(50, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:35:27\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:48:28\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-17 16:48:28\",\"updated_by\":1}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:48:28'),
(51, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"es\"}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:48:28'),
(52, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:35:27\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:48:33\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-17 16:48:33\",\"updated_by\":1}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:48:33'),
(53, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"es\"}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:48:33'),
(54, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:35:27\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 16:49:25\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"en\",\"updated_at\":\"2025-12-17 16:49:25\",\"updated_by\":1}', '/code/index.php/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:49:25'),
(55, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"en\"}', '/code/index.php/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:49:25'),
(56, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:52:59'),
(57, NULL, 'created', 'App\\Models\\UserModel', 4, NULL, '{\"username\":\"lmendoza\",\"email\":\"lmendoza@gmail.com\",\"first_name\":\"Luis Manuel\",\"last_name\":\"Mendoza\",\"is_active\":true,\"auth_method\":\"password\",\"preferred_language\":\"es\",\"created_at\":\"2025-12-17 16:56:53\",\"updated_at\":\"2025-12-17 16:56:53\"}', '/code/index.php/register', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:56:53'),
(58, 4, 'user_registered', 'App\\Models\\UserModel', 4, NULL, NULL, 'http://localhost/code/index.php/register', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:56:53'),
(59, NULL, 'updated', 'App\\Models\\UserModel', 4, '{\"id\":\"4\",\"username\":\"lmendoza\",\"email\":\"lmendoza@gmail.com\",\"first_name\":\"Luis Manuel\",\"last_name\":\"Mendoza\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:57:11\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-17 16:56:53\",\"updated_at\":\"2025-12-17 16:57:11\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":null,\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 16:57:11\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 16:57:11\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:57:11'),
(60, 4, 'login', 'App\\Models\\UserModel', 4, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:57:11'),
(61, 4, 'updated', 'App\\Models\\UserModel', 4, '{\"id\":\"4\",\"username\":\"lmendoza\",\"email\":\"lmendoza@gmail.com\",\"first_name\":\"Luis Manuel\",\"last_name\":\"Mendoza\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:57:11\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-17 16:56:53\",\"updated_at\":\"2025-12-17 16:59:12\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"4\",\"deleted_by\":null}', '{\"first_name\":\"Luis Manuel\",\"last_name\":\"Mendoza\",\"email\":\"lmendoza@gmail.com\",\"preferred_language\":\"es\",\"updated_at\":\"2025-12-17 16:59:12\",\"updated_by\":4}', '/code/index.php/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:59:12'),
(62, 4, 'updated', 'App\\Models\\UserModel', 4, '{\"id\":\"4\",\"username\":\"lmendoza\",\"email\":\"lmendoza@gmail.com\",\"first_name\":\"Luis Manuel\",\"last_name\":\"Mendoza\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:57:11\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-17 16:56:53\",\"updated_at\":\"2025-12-17 16:59:29\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"4\",\"deleted_by\":null}', '{\"first_name\":\"Luis Manuel\",\"last_name\":\"Mendoza\",\"email\":\"lmendoza@gmail.com\",\"preferred_language\":\"en\",\"updated_at\":\"2025-12-17 16:59:29\",\"updated_by\":4}', '/code/index.php/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:59:29'),
(63, 4, 'logout', 'App\\Models\\UserModel', 4, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:59:33'),
(64, NULL, 'updated', 'App\\Models\\UserModel', 4, '{\"id\":\"4\",\"username\":\"lmendoza\",\"email\":\"lmendoza@gmail.com\",\"first_name\":\"Luis Manuel\",\"last_name\":\"Mendoza\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:59:53\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-17 16:56:53\",\"updated_at\":\"2025-12-17 16:59:53\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"4\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 16:59:53\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 16:59:53\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:59:53'),
(65, 4, 'login', 'App\\Models\\UserModel', 4, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 16:59:53'),
(66, 4, 'updated', 'App\\Models\\UserModel', 4, '{\"id\":\"4\",\"username\":\"lmendoza\",\"email\":\"lmendoza2@gmail.com\",\"first_name\":\"Luis Manuel2\",\"last_name\":\"Mendoza2\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 16:59:53\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-17 16:56:53\",\"updated_at\":\"2025-12-17 17:00:22\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"4\",\"deleted_by\":null}', '{\"first_name\":\"Luis Manuel2\",\"last_name\":\"Mendoza2\",\"email\":\"lmendoza2@gmail.com\",\"preferred_language\":\"en\",\"updated_at\":\"2025-12-17 17:00:22\",\"updated_by\":4}', '/code/index.php/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:00:22'),
(67, 4, 'logout', 'App\\Models\\UserModel', 4, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:00:28'),
(68, NULL, 'updated', 'App\\Models\\UserModel', 4, '{\"id\":\"4\",\"username\":\"lmendoza\",\"email\":\"lmendoza2@gmail.com\",\"first_name\":\"Luis Manuel2\",\"last_name\":\"Mendoza2\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 17:00:51\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-17 16:56:53\",\"updated_at\":\"2025-12-17 17:00:51\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"4\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 17:00:51\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 17:00:51\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:00:51'),
(69, 4, 'login', 'App\\Models\\UserModel', 4, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:00:51'),
(70, 4, 'logout', 'App\\Models\\UserModel', 4, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:02:37'),
(71, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 17:02:46\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 17:02:46\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 17:02:46\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 17:02:46\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:02:46'),
(72, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:02:46'),
(73, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 17:02:46\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 17:02:54\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-17 17:02:54\",\"updated_by\":1}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:02:54'),
(74, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"es\"}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:02:54'),
(75, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:10:29'),
(76, NULL, 'updated', 'App\\Models\\UserModel', 4, '{\"id\":\"4\",\"username\":\"lmendoza\",\"email\":\"lmendoza2@gmail.com\",\"first_name\":\"Luis Manuel2\",\"last_name\":\"Mendoza2\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 17:10:40\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-17 16:56:53\",\"updated_at\":\"2025-12-17 17:10:40\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"4\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 17:10:40\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 17:10:40\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:10:40'),
(77, 4, 'login', 'App\\Models\\UserModel', 4, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:10:40'),
(78, 4, 'logout', 'App\\Models\\UserModel', 4, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:21:23'),
(79, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 17:21:30\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 17:21:30\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 17:21:30\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 17:21:30\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:21:30'),
(80, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:21:30'),
(81, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:23:22'),
(82, NULL, 'updated', 'App\\Models\\UserModel', 4, '{\"id\":\"4\",\"username\":\"lmendoza\",\"email\":\"lmendoza2@gmail.com\",\"first_name\":\"Luis Manuel2\",\"last_name\":\"Mendoza2\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 17:23:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-17 16:56:53\",\"updated_at\":\"2025-12-17 17:23:32\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"4\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 17:23:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 17:23:32\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:23:32'),
(83, 4, 'login', 'App\\Models\\UserModel', 4, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:23:32'),
(84, 4, 'logout', 'App\\Models\\UserModel', 4, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:23:51'),
(85, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 17:24:13\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 17:24:13\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 17:24:13\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 17:24:13\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:24:13'),
(86, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 17:24:13'),
(87, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 18:01:42'),
(88, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 18:01:49\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-17 18:01:49\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":null,\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 18:01:49\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 18:01:49\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 18:01:49'),
(89, 3, 'login', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 18:01:49'),
(90, 3, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 18:01:49\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-17 18:21:08\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"preferred_language\":\"en\",\"updated_at\":\"2025-12-17 18:21:08\",\"updated_by\":3}', '/code/index.php/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 18:21:08'),
(91, 3, 'language_changed', 'App\\Models\\UserModel', 3, NULL, '{\"language\":\"en\"}', '/code/index.php/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 18:21:08'),
(92, 3, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 18:01:49\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-17 18:21:12\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-17 18:21:12\",\"updated_by\":3}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 18:21:12'),
(93, 3, 'language_changed', 'App\\Models\\UserModel', 3, NULL, '{\"language\":\"es\"}', '/code/index.php/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 18:21:12'),
(94, 3, 'logout', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 18:21:25'),
(95, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 18:21:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 18:21:32\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 18:21:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 18:21:32\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 18:21:32'),
(96, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 18:21:32');
INSERT INTO `audit_logs` (`id`, `user_id`, `event`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `url`, `ip_address`, `user_agent`, `created_at`) VALUES
(97, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 18:27:10'),
(98, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 19:22:41\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 19:22:41\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 19:22:41\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 19:22:41\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 19:22:41'),
(99, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 19:22:41'),
(100, 1, 'created', 'App\\Models\\RoleModel', 9, NULL, '{\"name\":\"Usuario invitado\",\"description\":\"Este usuario no puede ver nada\",\"created_at\":\"2025-12-17 19:38:29\",\"updated_at\":\"2025-12-17 19:38:29\",\"created_by\":1}', '/code/index.php/roles/create', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 19:38:29'),
(101, 1, 'updated', 'App\\Models\\RoleModel', 9, '{\"id\":\"9\",\"name\":\"Usuario invitado 2\",\"description\":\"Este usuario no puede ver nada\",\"created_at\":\"2025-12-17 19:38:29\",\"updated_at\":\"2025-12-17 19:41:14\",\"created_by\":\"1\",\"updated_by\":\"1\"}', '{\"name\":\"Usuario invitado 2\",\"description\":\"Este usuario no puede ver nada\",\"updated_at\":\"2025-12-17 19:41:14\",\"updated_by\":1}', '/code/index.php/roles/edit/9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 19:41:14'),
(102, 1, 'updated', 'App\\Models\\RoleModel', 9, '{\"id\":\"9\",\"name\":\"Usuario invitado\",\"description\":\"Este usuario no puede ver nada\",\"created_at\":\"2025-12-17 19:38:29\",\"updated_at\":\"2025-12-17 19:41:22\",\"created_by\":\"1\",\"updated_by\":\"1\"}', '{\"name\":\"Usuario invitado\",\"description\":\"Este usuario no puede ver nada\",\"updated_at\":\"2025-12-17 19:41:22\",\"updated_by\":1}', '/code/index.php/roles/edit/9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 19:41:22'),
(103, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 19:50:22'),
(104, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 19:53:49\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 19:53:49\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 19:53:49\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 19:53:49\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 19:53:49'),
(105, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 19:53:49'),
(106, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 19:54:09'),
(107, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 19:53:49\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"1\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 20:01:27\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"failed_login_attempts\":1,\"updated_at\":\"2025-12-17 20:01:27\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 20:01:27'),
(108, 1, 'login_failed', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 20:01:27'),
(109, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 20:01:33\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 20:01:33\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 20:01:33\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 20:01:33\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 20:01:33'),
(110, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 20:01:33'),
(111, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 20:01:55'),
(112, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-17 20:02:07\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-17 20:02:07\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-17 20:02:07\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-17 20:02:07\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 20:02:07'),
(113, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 20:02:07'),
(114, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-17 20:02:30'),
(115, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 13:13:24\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 13:13:24\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 13:13:24\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 13:13:24\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 13:13:24'),
(116, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 13:13:24'),
(117, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 13:23:19'),
(118, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 13:25:30\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 13:25:30\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 13:25:30\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 13:25:30\"}', '/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 13:25:30'),
(119, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 13:25:30'),
(120, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/index.php/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 13:44:09'),
(121, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 13:56:28\",\"last_login_ip\":\"192.168.1.10\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 13:56:28\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 13:56:28\",\"last_login_ip\":\"192.168.1.10\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 13:56:28\"}', '/code/login', '192.168.1.10', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.2 Mobile/15E148 Safari/604.1', '2025-12-18 13:56:28'),
(122, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://192.168.1.14/code/login', '192.168.1.10', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.2 Mobile/15E148 Safari/604.1', '2025-12-18 13:56:28'),
(123, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 14:05:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 14:05:32\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 14:05:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 14:05:32\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 14:05:32'),
(124, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 14:05:32'),
(125, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 14:07:48'),
(126, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 14:12:55\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 14:12:55\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 14:12:55\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 14:12:55\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 14:12:55'),
(127, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 14:12:55'),
(128, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 16:21:09\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 16:21:09\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 16:21:09\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 16:21:09\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 16:21:09'),
(129, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 16:21:09'),
(130, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 16:21:09\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 16:23:02\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"en\",\"updated_at\":\"2025-12-18 16:23:02\",\"updated_by\":1}', '/code/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 16:23:02'),
(131, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"en\"}', '/code/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 16:23:02'),
(132, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 16:21:09\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 16:23:14\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-18 16:23:14\",\"updated_by\":1}', '/code/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 16:23:14'),
(133, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"es\"}', '/code/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 16:23:14'),
(134, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 16:21:09\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 16:23:24\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-18 16:23:24\",\"updated_by\":1}', '/code/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 16:23:24'),
(135, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"es\"}', '/code/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 16:23:24'),
(136, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 16:28:58'),
(137, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 16:29:17\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 16:29:17\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 16:29:17\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 16:29:17\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 16:29:17'),
(138, 3, 'login', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 16:29:17'),
(139, 3, 'logout', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 17:14:38'),
(140, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 19:42:06\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 19:42:06\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 19:42:06\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 19:42:06\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 19:42:06'),
(141, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 19:42:06'),
(142, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 20:10:47'),
(143, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 21:03:34\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 21:03:34\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 21:03:34\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 21:03:34\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 21:03:34'),
(144, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 21:03:34'),
(145, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 21:25:26'),
(146, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 21:25:34\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 21:25:34\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 21:25:34\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 21:25:34\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 21:25:34'),
(147, 3, 'login', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 21:25:34'),
(148, 3, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 21:25:34\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 21:25:47\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"email\":\"rolegarita@hotmail.com\",\"preferred_language\":\"es\",\"updated_at\":\"2025-12-18 21:25:47\",\"updated_by\":3}', '/code/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 21:25:47'),
(149, 3, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 21:25:34\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 22:06:15\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"email\":\"rolegarita@hotmail.com\",\"preferred_language\":\"es\",\"updated_at\":\"2025-12-18 22:06:15\",\"updated_by\":3}', '/code/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:06:15'),
(150, 3, 'logout', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:06:28'),
(151, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:06:36\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 22:06:36\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 22:06:36\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 22:06:36\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:06:36'),
(152, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:06:36'),
(153, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:10:15'),
(154, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:10:24\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 22:10:24\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 22:10:24\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 22:10:24\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:10:24'),
(155, 3, 'login', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:10:24'),
(156, 3, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:10:24\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 22:10:36\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"email\":\"rolegarita@hotmail.com\",\"preferred_language\":\"es\",\"updated_at\":\"2025-12-18 22:10:36\",\"updated_by\":3}', '/code/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:10:36'),
(157, 3, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:10:24\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 22:11:01\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"email\":\"rolegarita@hotmail.com\",\"preferred_language\":\"es\",\"updated_at\":\"2025-12-18 22:11:01\",\"updated_by\":3}', '/code/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:11:01'),
(158, 3, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:10:24\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 22:13:29\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"email\":\"rolegarita@hotmail.com\",\"preferred_language\":\"es\",\"updated_at\":\"2025-12-18 22:13:29\",\"updated_by\":3}', '/code/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:13:29'),
(159, 3, 'logout', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:13:45'),
(160, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:13:56\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 22:13:56\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 22:13:56\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 22:13:56\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:13:56'),
(161, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:13:56'),
(162, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:14:40'),
(163, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:14:52\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 22:14:52\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 22:14:52\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 22:14:52\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:14:52'),
(164, 3, 'login', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:14:52'),
(165, 3, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:14:52\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 22:15:04\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"email\":\"rolegarita@hotmail.com\",\"preferred_language\":\"es\",\"updated_at\":\"2025-12-18 22:15:04\",\"updated_by\":3}', '/code/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:15:04'),
(166, 3, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:14:52\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 22:17:19\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"email\":\"rolegarita@hotmail.com\",\"preferred_language\":\"es\",\"updated_at\":\"2025-12-18 22:17:19\",\"updated_by\":3}', '/code/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:17:19'),
(167, 3, 'logout', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:18:07'),
(168, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:19:29\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 22:19:29\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 22:19:29\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 22:19:29\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:19:29'),
(169, 3, 'login', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:19:29'),
(170, 3, 'logout', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:22:12'),
(171, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:25:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 22:25:32\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 22:25:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 22:25:32\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:25:32'),
(172, 3, 'login', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:25:32'),
(173, 3, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:25:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":\"#F97316\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2025-12-18 22:25:41\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null}', '{\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"email\":\"rolegarita@hotmail.com\",\"preferred_language\":\"es\",\"theme_color\":\"#F97316\",\"updated_at\":\"2025-12-18 22:25:41\",\"updated_by\":3}', '/code/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:25:41'),
(174, 3, 'logout', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:25:57'),
(175, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:26:28\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 22:26:28\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 22:26:28\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 22:26:28\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:26:28'),
(176, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:26:28'),
(177, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:26:28\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":\"#e10e30\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 22:26:41\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"first_name\":\"Super\",\"last_name\":\"Admin\",\"email\":\"admin@example.com\",\"preferred_language\":\"es\",\"theme_color\":\"#e10e30\",\"updated_at\":\"2025-12-18 22:26:41\",\"updated_by\":1}', '/code/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:26:41'),
(178, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:26:47'),
(179, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:32:08\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":\"#e10e30\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-18 22:32:08\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-18 22:32:08\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-18 22:32:08\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:32:08'),
(180, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-18 22:32:08'),
(181, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 17:05:03\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":\"#e10e30\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 17:05:03\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-19 17:05:03\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-19 17:05:03\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:05:03'),
(182, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:05:03'),
(183, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 17:05:03\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"theme_color\":\"#e10e30\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 17:12:16\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"en\",\"updated_at\":\"2025-12-19 17:12:16\",\"updated_by\":1}', '/code/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:12:16'),
(184, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"en\"}', '/code/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:12:16'),
(185, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 17:05:03\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"theme_color\":\"#EAB308\",\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 17:14:21\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"first_name\":\"Super\",\"last_name\":\"Admin\",\"email\":\"admin@example.com\",\"preferred_language\":\"en\",\"theme_color\":\"#EAB308\",\"updated_at\":\"2025-12-19 17:14:21\",\"updated_by\":1}', '/code/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:14:21'),
(186, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 17:05:03\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 17:14:31\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"first_name\":\"Super\",\"last_name\":\"Admin\",\"email\":\"admin@example.com\",\"preferred_language\":\"en\",\"theme_color\":null,\"updated_at\":\"2025-12-19 17:14:31\",\"updated_by\":1}', '/code/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:14:31');
INSERT INTO `audit_logs` (`id`, `user_id`, `event`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `url`, `ip_address`, `user_agent`, `created_at`) VALUES
(187, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 17:05:03\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 17:14:37\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"first_name\":\"Super\",\"last_name\":\"Admin\",\"email\":\"admin@example.com\",\"preferred_language\":\"en\",\"theme_color\":null,\"updated_at\":\"2025-12-19 17:14:37\",\"updated_by\":1}', '/code/profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:14:37'),
(188, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 17:05:03\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 17:16:29\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-19 17:16:29\",\"updated_by\":1}', '/code/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:16:29'),
(189, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"es\"}', '/code/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:16:29'),
(190, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:26:26'),
(191, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 17:27:08\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 17:27:08\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-19 17:27:08\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-19 17:27:08\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:27:08'),
(192, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:27:08'),
(193, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 17:27:08\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 17:30:02\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-19 17:30:02\",\"updated_by\":1}', '/code/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:30:02'),
(194, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"es\"}', '/code/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:30:02'),
(195, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 17:27:08\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"en\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 17:30:05\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"en\",\"updated_at\":\"2025-12-19 17:30:05\",\"updated_by\":1}', '/code/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:30:05'),
(196, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"en\"}', '/code/language/en', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:30:05'),
(197, 1, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 17:27:08\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 17:30:22\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"preferred_language\":\"es\",\"updated_at\":\"2025-12-19 17:30:22\",\"updated_by\":1}', '/code/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:30:22'),
(198, 1, 'language_changed', 'App\\Models\\UserModel', 1, NULL, '{\"language\":\"es\"}', '/code/language/es', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:30:22'),
(199, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:32:45'),
(200, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 17:38:36\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 17:38:36\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-19 17:38:36\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-19 17:38:36\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:38:36'),
(201, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:38:36'),
(202, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 17:42:15'),
(203, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 19:04:30\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 19:04:30\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-19 19:04:30\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-19 19:04:30\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 19:04:30'),
(204, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 19:04:30'),
(205, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 19:04:55'),
(206, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-19 21:10:10\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2025-12-19 21:10:10\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2025-12-19 21:10:10\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2025-12-19 21:10:10\"}', '/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 21:10:10'),
(207, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost/code/login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-12-19 21:10:10'),
(208, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2026-07-05 19:48:50\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2026-07-05 19:48:50\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2026-07-05 19:48:50\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2026-07-05 19:48:50\"}', '/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-05 19:48:50'),
(209, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://cuentas.h-v.co/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-05 19:48:51'),
(210, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2026-07-05 23:35:24\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2026-07-05 23:35:24\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"last_login_at\":\"2026-07-05 23:35:24\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2026-07-05 23:35:24\"}', '/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-05 23:35:24'),
(211, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://cuentas.h-v.co/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-05 23:35:24'),
(212, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"is_active\":\"1\",\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2026-07-05 23:35:24\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":\"1\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2026-07-05 23:42:39\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null}', '{\"failed_login_attempts\":1,\"updated_at\":\"2026-07-05 23:42:39\"}', '/%5C/login', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; es-CO) WindowsPowerShell/5.1.19041.3803', '2026-07-05 23:42:39'),
(213, 1, 'login_failed', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost:8899/%5C/login', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; es-CO) WindowsPowerShell/5.1.19041.3803', '2026-07-05 23:42:39'),
(214, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"phone\":null,\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2026-07-06 00:16:44\",\"last_login_ip\":\"190.254.131.124\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2026-07-06 00:16:44\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"last_login_at\":\"2026-07-06 00:16:44\",\"last_login_ip\":\"190.254.131.124\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2026-07-06 00:16:44\"}', '/login', '190.254.131.124', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-07-06 00:16:44'),
(215, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'https://cuentas.h-v.co/login', '190.254.131.124', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-07-06 00:16:44'),
(224, NULL, 'created', 'App\\Models\\PlanModel', 3, NULL, '{\"name\":\"ZZ API Test\",\"service_days\":10,\"price_amount\":0,\"is_active\":1,\"created_at\":\"2026-07-06 01:48:54\",\"updated_at\":\"2026-07-06 01:48:54\"}', '/%5C/api/v1/plans', '::1', 'curl/8.18.0', '2026-07-06 01:48:54'),
(225, NULL, 'deleted', 'App\\Models\\PlanModel', 3, NULL, NULL, '/%5C/api/v1/plans/3', '::1', 'curl/8.18.0', '2026-07-06 01:48:55'),
(231, 1, 'created', 'App\\Models\\CompanyModel', 7, NULL, '{\"name\":\"ZZ Valid Co\",\"plan_id\":1,\"owner_id\":1,\"is_active\":1,\"created_at\":\"2026-07-06 02:14:55\",\"updated_at\":\"2026-07-06 02:14:55\",\"created_by\":1}', '/C:%5Cxampp%5Chtdocs%5Ccuentas/', '0.0.0.0', '', '2026-07-06 02:14:55'),
(232, 1, 'created', 'App\\Models\\SubscriptionModel', 5, NULL, '{\"company_id\":7,\"plan_id\":1,\"status\":\"active\",\"start_date\":\"2026-07-06 02:14:55\",\"end_date\":\"2026-08-05 02:14:55\",\"created_at\":\"2026-07-06 02:14:55\",\"updated_at\":\"2026-07-06 02:14:55\",\"created_by\":1}', '/C:%5Cxampp%5Chtdocs%5Ccuentas/', '0.0.0.0', '', '2026-07-06 02:14:55'),
(233, 1, 'created', 'App\\Models\\CompanyModel', 8, NULL, '{\"name\":\"ZZ Expired Co\",\"plan_id\":1,\"owner_id\":1,\"is_active\":1,\"created_at\":\"2026-07-06 02:14:55\",\"updated_at\":\"2026-07-06 02:14:55\",\"created_by\":1}', '/C:%5Cxampp%5Chtdocs%5Ccuentas/', '0.0.0.0', '', '2026-07-06 02:14:55'),
(234, 1, 'created', 'App\\Models\\SubscriptionModel', 6, NULL, '{\"company_id\":8,\"plan_id\":1,\"status\":\"active\",\"start_date\":\"2026-07-04 22:28:15\",\"end_date\":\"2026-07-05 02:14:55\",\"created_at\":\"2026-07-06 02:14:55\",\"updated_at\":\"2026-07-06 02:14:55\",\"created_by\":1}', '/C:%5Cxampp%5Chtdocs%5Ccuentas/', '0.0.0.0', '', '2026-07-06 02:14:55'),
(235, NULL, 'updated', 'App\\Models\\SubscriptionModel', 6, '{\"id\":\"6\",\"company_id\":\"8\",\"plan_id\":\"1\",\"start_date\":\"2026-07-04 22:28:15\",\"end_date\":\"2026-07-05 02:14:55\",\"status\":\"expired\",\"created_at\":\"2026-07-06 02:14:55\",\"updated_at\":\"2026-07-06 02:14:57\",\"created_by\":\"1\",\"updated_by\":null}', '{\"status\":\"expired\",\"updated_at\":\"2026-07-06 02:14:57\"}', '/%5C/api/v1/me', '::1', 'curl/8.18.0', '2026-07-06 02:14:57'),
(236, 1, 'created', 'App\\Models\\CompanyModel', 9, NULL, '{\"name\":\"ZZ Users Co\",\"plan_id\":1,\"owner_id\":1,\"is_active\":1,\"created_at\":\"2026-07-06 02:25:57\",\"updated_at\":\"2026-07-06 02:25:57\",\"created_by\":1}', '/C:%5Cxampp%5Chtdocs%5Ccuentas/', '0.0.0.0', '', '2026-07-06 02:25:57'),
(237, 1, 'created', 'App\\Models\\SubscriptionModel', 7, NULL, '{\"company_id\":9,\"plan_id\":1,\"status\":\"active\",\"start_date\":\"2026-07-06 02:25:57\",\"end_date\":\"2026-08-05 02:25:57\",\"created_at\":\"2026-07-06 02:25:57\",\"updated_at\":\"2026-07-06 02:25:57\",\"created_by\":1}', '/C:%5Cxampp%5Chtdocs%5Ccuentas/', '0.0.0.0', '', '2026-07-06 02:25:57'),
(238, 1, 'created', 'App\\Models\\UserModel', 7, NULL, '{\"username\":\"zzuA_5e81cd\",\"email\":\"zzuA_5e81cd@ex.com\",\"first_name\":\"Ana\",\"last_name\":\"A\",\"is_active\":1,\"auth_method\":\"password\",\"created_at\":\"2026-07-06 02:25:57\",\"updated_at\":\"2026-07-06 02:25:57\",\"created_by\":1}', '/C:%5Cxampp%5Chtdocs%5Ccuentas/', '0.0.0.0', '', '2026-07-06 02:25:58'),
(239, 1, 'created', 'App\\Models\\UserModel', 8, NULL, '{\"username\":\"zzuB_5e81cd\",\"email\":\"zzuB_5e81cd@ex.com\",\"first_name\":\"Beto\",\"last_name\":\"B\",\"is_active\":1,\"auth_method\":\"password\",\"created_at\":\"2026-07-06 02:25:58\",\"updated_at\":\"2026-07-06 02:25:58\",\"created_by\":1}', '/C:%5Cxampp%5Chtdocs%5Ccuentas/', '0.0.0.0', '', '2026-07-06 02:25:58'),
(240, 1, 'updated', 'App\\Models\\CompanyModel', 9, '{\"id\":\"9\",\"name\":\"ZZ Users Co\",\"nit\":\"\",\"slug\":\"zz-users-co\",\"logo\":null,\"website\":\"\",\"address\":\"\",\"description\":\"\",\"brand_color\":\"#e10e30\",\"contact_email\":\"\",\"contact_phone\":\"\",\"whatsapp\":\"\",\"plan_id\":\"1\",\"owner_id\":\"4\",\"is_active\":\"1\",\"created_at\":\"2026-07-06 02:25:57\",\"updated_at\":\"2026-07-06 02:26:56\",\"deleted_at\":null,\"created_by\":\"1\",\"updated_by\":\"1\",\"deleted_by\":null}', '{\"name\":\"ZZ Users Co\",\"nit\":\"\",\"slug\":\"zz-users-co\",\"website\":\"\",\"address\":\"\",\"description\":\"\",\"brand_color\":\"#e10e30\",\"contact_email\":\"\",\"contact_phone\":\"\",\"whatsapp\":\"\",\"plan_id\":1,\"is_active\":1,\"owner_id\":4,\"updated_at\":\"2026-07-06 02:26:56\",\"updated_by\":1}', '/companies/edit/9', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 02:26:56'),
(241, 1, 'updated', 'App\\Models\\SubscriptionModel', 7, '{\"id\":\"7\",\"company_id\":\"9\",\"plan_id\":\"1\",\"start_date\":\"2026-07-06 00:00:00\",\"end_date\":\"2026-08-05 00:00:00\",\"status\":\"active\",\"created_at\":\"2026-07-06 02:25:57\",\"updated_at\":\"2026-07-06 02:26:56\",\"created_by\":\"1\",\"updated_by\":\"1\"}', '{\"plan_id\":1,\"status\":\"active\",\"start_date\":\"2026-07-06\",\"end_date\":\"2026-08-05\",\"updated_at\":\"2026-07-06 02:26:56\",\"updated_by\":1}', '/companies/edit/9', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 02:26:56'),
(242, NULL, 'created', 'App\\Models\\UserModel', 9, NULL, '{\"username\":\"zznew_5e81cd\",\"email\":\"zznew_5e81cd@ex.com\",\"first_name\":\"Nuevo\",\"last_name\":\"N\",\"auth_method\":\"password\",\"is_active\":1,\"created_at\":\"2026-07-06 02:27:49\",\"updated_at\":\"2026-07-06 02:27:49\"}', '/%5C/api/v1/users', '::1', 'curl/8.18.0', '2026-07-06 02:27:49'),
(245, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"phone\":null,\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2026-07-06 00:16:44\",\"last_login_ip\":\"190.254.131.124\",\"failed_login_attempts\":\"1\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2026-07-06 03:03:29\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"failed_login_attempts\":1,\"updated_at\":\"2026-07-06 03:03:29\"}', '/%5C/login', '::1', 'curl/8.18.0', '2026-07-06 03:03:29'),
(246, 1, 'login_failed', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://localhost:8899/%5C/login', '::1', 'curl/8.18.0', '2026-07-06 03:03:29'),
(290, 1, 'created', 'App\\Models\\CompanyModel', 14, NULL, '{\"name\":\"Herramientas Virtuales\",\"nit\":\"900719705\",\"slug\":\"herramientas-virtuales\",\"website\":\"https:\\/\\/herramientasvirtuales.co\",\"address\":\"Cra 8 # 16 - 88\",\"description\":\"Soluciones integrales en tecnolog\\u00eda\",\"brand_color\":\"#e10e30\",\"contact_email\":\"comercial@herramientasvirtuales.co\",\"contact_phone\":\"3143271001\",\"whatsapp\":\"3143271001\",\"plan_id\":1,\"is_active\":1,\"logo\":\"\\/uploads\\/companies\\/company_1783308658.jpg\",\"owner_id\":null,\"created_at\":\"2026-07-06 03:30:58\",\"updated_at\":\"2026-07-06 03:30:58\",\"created_by\":1}', '/companies/create', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:30:58'),
(291, 1, 'updated', 'App\\Models\\CompanyModel', 14, '{\"id\":\"14\",\"name\":\"Herramientas Virtuales\",\"nit\":\"900719705\",\"slug\":\"herramientas-virtuales\",\"logo\":\"\\/uploads\\/companies\\/company_1783308658.jpg\",\"website\":\"https:\\/\\/herramientasvirtuales.co\",\"address\":\"Cra 8 # 16 - 88\",\"description\":\"Soluciones integrales en tecnolog\\u00eda\",\"brand_color\":\"#e10e30\",\"contact_email\":\"comercial@herramientasvirtuales.co\",\"contact_phone\":\"3143271001\",\"whatsapp\":\"3143271001\",\"plan_id\":\"1\",\"owner_id\":\"3\",\"is_active\":\"1\",\"created_at\":\"2026-07-06 03:30:58\",\"updated_at\":\"2026-07-06 03:30:58\",\"deleted_at\":null,\"created_by\":\"1\",\"updated_by\":\"1\",\"deleted_by\":null}', '{\"owner_id\":3,\"updated_at\":\"2026-07-06 03:30:58\",\"updated_by\":1}', '/companies/create', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:30:58'),
(292, 1, 'created', 'App\\Models\\SubscriptionModel', 9, NULL, '{\"company_id\":14,\"plan_id\":1,\"start_date\":\"2026-07-06 03:30:58\",\"end_date\":\"2026-08-05 03:30:58\",\"status\":\"trial\",\"created_at\":\"2026-07-06 03:30:58\",\"updated_at\":\"2026-07-06 03:30:58\",\"created_by\":1}', '/companies/create', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:30:58'),
(293, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://cuentas.h-v.co/logout', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:33:56'),
(294, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"phone\":null,\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:25:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"1\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":\"#F97316\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2026-07-06 03:34:03\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"failed_login_attempts\":1,\"updated_at\":\"2026-07-06 03:34:03\"}', '/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:34:03'),
(295, 3, 'login_failed', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://cuentas.h-v.co/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:34:03'),
(296, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"phone\":null,\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:25:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"2\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":\"#F97316\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2026-07-06 03:34:08\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"failed_login_attempts\":2,\"updated_at\":\"2026-07-06 03:34:08\"}', '/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:34:08'),
(297, 3, 'login_failed', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://cuentas.h-v.co/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:34:08'),
(298, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"phone\":null,\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:25:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"3\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":\"#F97316\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2026-07-06 03:34:12\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"failed_login_attempts\":3,\"updated_at\":\"2026-07-06 03:34:12\"}', '/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:34:12'),
(299, 3, 'login_failed', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://cuentas.h-v.co/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:34:12'),
(300, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"phone\":null,\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:25:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"4\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":\"#F97316\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2026-07-06 03:34:16\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"failed_login_attempts\":4,\"updated_at\":\"2026-07-06 03:34:16\"}', '/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:34:16'),
(301, 3, 'login_failed', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://cuentas.h-v.co/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:34:16'),
(302, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"phone\":null,\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:25:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"5\",\"locked_until\":\"2026-07-06 03:49:25\",\"preferred_language\":\"es\",\"theme_color\":\"#F97316\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2026-07-06 03:34:25\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"failed_login_attempts\":5,\"locked_until\":\"2026-07-06 03:49:25\",\"updated_at\":\"2026-07-06 03:34:25\"}', '/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:34:25'),
(303, 3, 'login_failed', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://cuentas.h-v.co/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:34:25'),
(304, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"phone\":null,\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2026-07-06 03:34:41\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2026-07-06 03:34:41\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"last_login_at\":\"2026-07-06 03:34:41\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2026-07-06 03:34:41\"}', '/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:34:41'),
(305, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://cuentas.h-v.co/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:34:41'),
(325, 1, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"phone\":\"\",\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2025-12-18 22:25:32\",\"last_login_ip\":\"::1\",\"failed_login_attempts\":\"5\",\"locked_until\":\"2026-07-06 03:49:25\",\"preferred_language\":\"es\",\"theme_color\":\"#F97316\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2026-07-06 03:46:37\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"phone\":\"\",\"is_active\":true,\"preferred_language\":\"es\",\"updated_at\":\"2026-07-06 03:46:37\",\"updated_by\":1}', '/users/edit/3', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:46:37'),
(326, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://cuentas.h-v.co/logout', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:46:44'),
(327, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"phone\":null,\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2026-07-06 03:47:14\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2026-07-06 03:47:14\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"last_login_at\":\"2026-07-06 03:47:14\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2026-07-06 03:47:14\"}', '/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:47:14'),
(328, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://cuentas.h-v.co/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:47:14'),
(329, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://cuentas.h-v.co/logout', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 03:48:26'),
(330, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"phone\":\"\",\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2026-07-06 04:01:09\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":\"#F97316\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2026-07-06 04:01:09\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"last_login_at\":\"2026-07-06 04:01:09\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2026-07-06 04:01:09\"}', '/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 04:01:09'),
(331, 3, 'login', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://cuentas.h-v.co/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 04:01:09'),
(332, 3, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"phone\":\"573105626959\",\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2026-07-06 04:01:09\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":\"#374151\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2026-07-06 04:02:54\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"email\":\"rolegarita@hotmail.com\",\"phone\":\"573105626959\",\"preferred_language\":\"es\",\"theme_color\":\"#374151\",\"updated_at\":\"2026-07-06 04:02:54\",\"updated_by\":3}', '/profile', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 04:02:54'),
(333, 3, 'logout', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://cuentas.h-v.co/logout', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 04:03:51'),
(334, NULL, 'updated', 'App\\Models\\UserModel', 1, '{\"id\":\"1\",\"username\":\"admin\",\"email\":\"admin@example.com\",\"first_name\":\"Super\",\"last_name\":\"Admin\",\"phone\":null,\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2026-07-06 04:04:03\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":null,\"created_at\":\"2025-12-16 22:22:23\",\"updated_at\":\"2026-07-06 04:04:03\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"1\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"last_login_at\":\"2026-07-06 04:04:03\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2026-07-06 04:04:03\"}', '/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 04:04:03'),
(335, 1, 'login', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://cuentas.h-v.co/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 04:04:03'),
(336, 1, 'logout', 'App\\Models\\UserModel', 1, NULL, NULL, 'http://cuentas.h-v.co/logout', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 04:05:13'),
(337, NULL, 'updated', 'App\\Models\\UserModel', 3, '{\"id\":\"3\",\"username\":\"rleon\",\"email\":\"rolegarita@hotmail.com\",\"first_name\":\"Rosalba \",\"last_name\":\"Le\\u00f3n\",\"phone\":\"573105626959\",\"is_active\":\"1\",\"reset_hash\":null,\"auth_method\":\"password\",\"two_factor_enabled\":\"0\",\"last_login_at\":\"2026-07-06 04:05:21\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":\"0\",\"locked_until\":null,\"preferred_language\":\"es\",\"theme_color\":\"#374151\",\"created_at\":\"2025-12-17 16:20:36\",\"updated_at\":\"2026-07-06 04:05:21\",\"deleted_at\":null,\"created_by\":null,\"updated_by\":\"3\",\"deleted_by\":null,\"reset_expires_at\":null}', '{\"last_login_at\":\"2026-07-06 04:05:21\",\"last_login_ip\":\"192.168.40.1\",\"failed_login_attempts\":0,\"locked_until\":null,\"updated_at\":\"2026-07-06 04:05:21\"}', '/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 04:05:21'),
(338, 3, 'login', 'App\\Models\\UserModel', 3, NULL, NULL, 'http://cuentas.h-v.co/login', '192.168.40.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-06 04:05:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auth_providers`
--

CREATE TABLE `auth_providers` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `provider` varchar(50) NOT NULL,
  `provider_user_id` varchar(255) NOT NULL,
  `provider_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`provider_data`)),
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `companies`
--

CREATE TABLE `companies` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `nit` varchar(40) DEFAULT NULL,
  `slug` varchar(160) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `brand_color` varchar(9) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `whatsapp` varchar(40) DEFAULT NULL,
  `plan_id` int(11) UNSIGNED DEFAULT NULL,
  `owner_id` int(11) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `companies`
--

INSERT INTO `companies` (`id`, `name`, `nit`, `slug`, `logo`, `website`, `address`, `description`, `brand_color`, `contact_email`, `contact_phone`, `whatsapp`, `plan_id`, `owner_id`, `is_active`, `created_at`, `updated_at`, `deleted_at`, `created_by`, `updated_by`, `deleted_by`) VALUES
(14, 'Herramientas Virtuales', '900719705', 'herramientas-virtuales', '/uploads/companies/company_1783308658.jpg', 'https://herramientasvirtuales.co', 'Cra 8 # 16 - 88', 'Soluciones integrales en tecnología', '#e10e30', 'comercial@herramientasvirtuales.co', '3143271001', '3143271001', 1, 3, 1, '2026-07-06 03:30:58', '2026-07-06 03:30:58', NULL, 1, 1, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `company_users`
--

CREATE TABLE `company_users` (
  `id` int(11) UNSIGNED NOT NULL,
  `company_id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `company_users`
--

INSERT INTO `company_users` (`id`, `company_id`, `user_id`, `is_admin`, `created_at`, `updated_at`, `created_by`) VALUES
(5, 14, 3, 1, '2026-07-06 03:30:58', '2026-07-06 03:30:58', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `magic_links`
--

CREATE TABLE `magic_links` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `migrations`
--

CREATE TABLE `migrations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `migrations`
--

INSERT INTO `migrations` (`id`, `version`, `class`, `group`, `namespace`, `time`, `batch`) VALUES
(1, '2024_12_16_000001', 'App\\Database\\Migrations\\CreateUsersTable', 'default', 'App', 1765923661, 1),
(2, '2024_12_16_000002', 'App\\Database\\Migrations\\CreateRolesTable', 'default', 'App', 1765923661, 1),
(3, '2024_12_16_000003', 'App\\Database\\Migrations\\CreatePermissionsTable', 'default', 'App', 1765923661, 1),
(4, '2024_12_16_000004', 'App\\Database\\Migrations\\CreateRolePermissionsTable', 'default', 'App', 1765923661, 1),
(5, '2024_12_16_000005', 'App\\Database\\Migrations\\CreateUserRolesTable', 'default', 'App', 1765923661, 1),
(6, '2024_12_16_000006', 'App\\Database\\Migrations\\CreateAppSettingsTable', 'default', 'App', 1765923661, 1),
(7, '2024_12_16_000007', 'App\\Database\\Migrations\\CreateAuthProvidersTable', 'default', 'App', 1765923661, 1),
(8, '2024_12_16_000008', 'App\\Database\\Migrations\\CreateMagicLinksTable', 'default', 'App', 1765923691, 2),
(9, '2024_12_16_000009', 'App\\Database\\Migrations\\CreateApiTokensTable', 'default', 'App', 1765923691, 2),
(10, '2024_12_16_000010', 'App\\Database\\Migrations\\CreateTwoFactorAuthTable', 'default', 'App', 1765923691, 2),
(11, '2024_12_16_000012', 'App\\Database\\Migrations\\CreateAuditLogsTable', 'default', 'App', 1765923691, 2),
(12, '2025-12-17-182630', 'App\\Database\\Migrations\\AddCreatedAtToAppSettings', 'default', 'App', 1765996005, 3),
(13, '2025-12-18-195900', 'App\\Database\\Migrations\\AddThemeSettings', 'default', 'App', 1766087981, 4),
(14, '2025-12-18-210000', 'App\\Database\\Migrations\\AddUserThemePreference', 'default', 'App', 1766092479, 5),
(15, '2025-12-19-123000', 'App\\Database\\Migrations\\AddResetTokenColumns', 'default', 'App', 1783296685, 6),
(16, '2026-07-05-000000', 'App\\Database\\Migrations\\AddPhoneToUsers', 'default', 'App', 1783296685, 6),
(17, '2026-07-05-010000', 'App\\Database\\Migrations\\CreatePlansTable', 'default', 'App', 1783299465, 7),
(18, '2026-07-05-010100', 'App\\Database\\Migrations\\CreateCompaniesTable', 'default', 'App', 1783299465, 7),
(19, '2026-07-05-010200', 'App\\Database\\Migrations\\CreateSubscriptionsTable', 'default', 'App', 1783299465, 7),
(20, '2026-07-05-010300', 'App\\Database\\Migrations\\AddCompanyIdToUsers', 'default', 'App', 1783299465, 7),
(21, '2026-07-05-010400', 'App\\Database\\Migrations\\SeedCompanyPlanPermissions', 'default', 'App', 1783299465, 7),
(22, '2026-07-05-010500', 'App\\Database\\Migrations\\DropCompanyIdFromUsers', 'default', 'App', 1783300623, 8),
(23, '2026-07-05-010600', 'App\\Database\\Migrations\\DropUnusedPlanLimits', 'default', 'App', 1783300623, 8),
(24, '2026-07-05-020000', 'App\\Database\\Migrations\\SeedApiPermissions', 'default', 'App', 1783302158, 9),
(25, '2026-07-05-030000', 'App\\Database\\Migrations\\AddCompanyIdToApiTokens', 'default', 'App', 1783303775, 10),
(26, '2026-07-05-040000', 'App\\Database\\Migrations\\CreateCompanyUsersTable', 'default', 'App', 1783304711, 11),
(27, '2026-07-05-050000', 'App\\Database\\Migrations\\EncryptExistingSecrets', 'default', 'App', 1783306913, 12);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`, `module`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'users.view', 'Ver listado de usuarios', 'users', '2025-12-16 22:22:22', NULL, NULL, NULL),
(2, 'users.create', 'Crear nuevos usuarios', 'users', '2025-12-16 22:22:22', NULL, NULL, NULL),
(3, 'users.edit', 'Editar usuarios existentes', 'users', '2025-12-16 22:22:22', NULL, NULL, NULL),
(4, 'users.delete', 'Eliminar usuarios', 'users', '2025-12-16 22:22:22', NULL, NULL, NULL),
(5, 'roles.view', 'Ver listado de roles', 'roles', '2025-12-16 22:22:22', NULL, NULL, NULL),
(6, 'roles.create', 'Crear nuevos roles', 'roles', '2025-12-16 22:22:22', NULL, NULL, NULL),
(7, 'roles.edit', 'Editar roles existentes', 'roles', '2025-12-16 22:22:22', NULL, NULL, NULL),
(8, 'roles.delete', 'Eliminar roles', 'roles', '2025-12-16 22:22:22', NULL, NULL, NULL),
(9, 'permissions.view', 'Ver listado de permisos', 'permissions', '2025-12-16 22:22:22', NULL, NULL, NULL),
(10, 'permissions.create', 'Crear nuevos permisos', 'permissions', '2025-12-16 22:22:22', NULL, NULL, NULL),
(11, 'permissions.edit', 'Editar permisos existentes', 'permissions', '2025-12-16 22:22:22', NULL, NULL, NULL),
(12, 'permissions.delete', 'Eliminar permisos', 'permissions', '2025-12-16 22:22:22', NULL, NULL, NULL),
(13, 'settings.view', 'Ver configuración del sistema', 'settings', '2025-12-16 22:22:22', NULL, NULL, NULL),
(14, 'settings.edit', 'Editar configuración del sistema', 'settings', '2025-12-16 22:22:22', NULL, NULL, NULL),
(15, 'audit.view', 'Ver registros de auditoría', 'audit', '2025-12-16 22:22:22', NULL, NULL, NULL),
(16, 'audit.export', 'Exportar registros de auditoría', 'audit', '2025-12-16 22:22:22', NULL, NULL, NULL),
(33, 'companies.view', 'Ver listado de empresas', 'companies', '2026-07-06 00:57:45', NULL, NULL, NULL),
(34, 'companies.create', 'Crear empresas', 'companies', '2026-07-06 00:57:45', NULL, NULL, NULL),
(35, 'companies.edit', 'Editar empresas', 'companies', '2026-07-06 00:57:45', NULL, NULL, NULL),
(36, 'companies.delete', 'Eliminar empresas', 'companies', '2026-07-06 00:57:45', NULL, NULL, NULL),
(37, 'plans.view', 'Ver listado de planes', 'plans', '2026-07-06 00:57:45', NULL, NULL, NULL),
(38, 'plans.create', 'Crear planes', 'plans', '2026-07-06 00:57:45', NULL, NULL, NULL),
(39, 'plans.edit', 'Editar planes', 'plans', '2026-07-06 00:57:45', NULL, NULL, NULL),
(40, 'plans.delete', 'Eliminar planes', 'plans', '2026-07-06 00:57:45', NULL, NULL, NULL),
(41, 'api.view', 'Ver gestor de API, documentación y tokens', 'api', '2026-07-06 01:42:38', NULL, NULL, NULL),
(42, 'api.create', 'Crear tokens de API', 'api', '2026-07-06 01:42:38', NULL, NULL, NULL),
(43, 'api.delete', 'Revocar tokens de API', 'api', '2026-07-06 01:42:38', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plans`
--

CREATE TABLE `plans` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `max_companies` int(11) NOT NULL DEFAULT 0,
  `service_days` int(11) NOT NULL DEFAULT 30,
  `price_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_currency` varchar(3) NOT NULL DEFAULT 'USD',
  `features` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `plans`
--

INSERT INTO `plans` (`id`, `name`, `description`, `max_companies`, `service_days`, `price_amount`, `price_currency`, `features`, `is_active`, `created_at`, `updated_at`, `deleted_at`, `created_by`, `updated_by`, `deleted_by`) VALUES
(1, 'Trial', 'Plan de prueba gratuito', 1, 30, 0.00, 'USD', NULL, 1, '2026-07-06 00:58:38', NULL, NULL, NULL, NULL, NULL),
(2, 'Profesional', 'Plan profesional anual', 1, 365, 49.00, 'USD', NULL, 1, '2026-07-06 00:58:38', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'Super Admin', 'Acceso total al sistema con todos los permisos', '2025-12-16 22:22:21', NULL, NULL, NULL),
(2, 'Admin', 'Administrador con gestión de usuarios y configuración', '2025-12-16 22:22:21', NULL, NULL, NULL),
(3, 'Auditor', 'Acceso de solo lectura a registros de auditoría', '2025-12-16 22:22:21', NULL, NULL, NULL),
(4, 'Usuario', 'Usuario estándar con acceso básico al sistema', '2025-12-16 22:22:21', NULL, NULL, NULL),
(9, 'Usuario invitado', 'Este usuario no puede ver nada', '2025-12-17 19:38:29', '2025-12-17 19:41:22', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) UNSIGNED NOT NULL,
  `role_id` int(11) UNSIGNED NOT NULL,
  `permission_id` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 1, 3),
(4, 1, 4),
(5, 1, 5),
(6, 1, 6),
(7, 1, 7),
(8, 1, 8),
(9, 1, 9),
(10, 1, 10),
(11, 1, 11),
(12, 1, 12),
(13, 1, 13),
(14, 1, 14),
(15, 1, 15),
(16, 1, 16),
(35, 1, 33),
(36, 1, 34),
(37, 1, 35),
(38, 1, 36),
(39, 1, 37),
(40, 1, 38),
(41, 1, 39),
(42, 1, 40),
(51, 1, 41),
(52, 1, 42),
(53, 1, 43),
(58, 2, 15),
(57, 2, 16),
(62, 2, 33),
(59, 2, 34),
(61, 2, 35),
(60, 2, 36),
(29, 3, 15),
(30, 3, 16);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) UNSIGNED NOT NULL,
  `company_id` int(11) UNSIGNED NOT NULL,
  `plan_id` int(11) UNSIGNED DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` enum('trial','active','expired','cancelled') NOT NULL DEFAULT 'trial',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `company_id`, `plan_id`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(9, 14, 1, '2026-07-06 03:30:58', '2026-08-05 03:30:58', 'trial', '2026-07-06 03:30:58', '2026-07-06 03:30:58', 1, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `two_factor_auth`
--

CREATE TABLE `two_factor_auth` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `secret` varchar(255) NOT NULL,
  `recovery_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recovery_codes`)),
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `reset_hash` varchar(255) DEFAULT NULL,
  `auth_method` enum('password','magic_link','oauth','ldap') NOT NULL DEFAULT 'password',
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `preferred_language` varchar(5) NOT NULL DEFAULT 'es',
  `theme_color` varchar(7) DEFAULT NULL COMMENT 'User personal theme color preference in hex format (#RRGGBB)',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  `reset_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `phone`, `is_active`, `reset_hash`, `auth_method`, `two_factor_enabled`, `last_login_at`, `last_login_ip`, `failed_login_attempts`, `locked_until`, `preferred_language`, `theme_color`, `created_at`, `updated_at`, `deleted_at`, `created_by`, `updated_by`, `deleted_by`, `reset_expires_at`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$EQpvNTZXfa0gzomF506KOe.6g2sXQZaAf9lVU1ZiIGlg.WYn78qcu', 'Super', 'Admin', NULL, 1, NULL, 'password', 0, '2026-07-06 04:04:03', '192.168.40.1', 0, NULL, 'es', NULL, '2025-12-16 22:22:23', '2026-07-06 04:04:03', NULL, NULL, 1, NULL, NULL),
(3, 'rleon', 'rolegarita@hotmail.com', '$2y$10$A42/RQ.m7BOo5KM9EWhZ.ejydKUMaJ.4GmA8RYfzdCwV5BS5X0BHe', 'Rosalba ', 'León', '573105626959', 1, NULL, 'password', 0, '2026-07-06 04:05:21', '192.168.40.1', 0, NULL, 'es', '#374151', '2025-12-17 16:20:36', '2026-07-06 04:05:21', NULL, NULL, 3, NULL, NULL),
(4, 'lmendoza', 'lmendoza2@gmail.com', '$2y$10$rr5ifxBsn2jFLAXIL3ozsuYISewhjAQJX8jH03xLcS8NtNahhfUc.', 'Luis Manuel2', 'Mendoza2', NULL, 1, NULL, 'password', 0, '2025-12-17 17:23:32', '::1', 0, NULL, 'en', NULL, '2025-12-17 16:56:53', '2025-12-17 17:23:32', NULL, NULL, 4, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `role_id` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `user_roles`
--

INSERT INTO `user_roles` (`id`, `user_id`, `role_id`) VALUES
(12, 1, 1),
(15, 3, 2),
(6, 3, 3),
(7, 3, 4),
(14, 4, 2),
(4, 4, 3),
(5, 4, 4);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `api_tokens_user_id_foreign` (`user_id`),
  ADD KEY `api_tokens_created_by_foreign` (`created_by`);

--
-- Indices de la tabla `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indices de la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `auditable_type_auditable_id` (`auditable_type`,`auditable_id`),
  ADD KEY `event` (`event`),
  ADD KEY `created_at` (`created_at`);

--
-- Indices de la tabla `auth_providers`
--
ALTER TABLE `auth_providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id_provider` (`user_id`,`provider`),
  ADD KEY `auth_providers_created_by_foreign` (`created_by`);

--
-- Indices de la tabla `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `slug` (`slug`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indices de la tabla `company_users`
--
ALTER TABLE `company_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `company_id_user_id` (`company_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `magic_links`
--
ALTER TABLE `magic_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `magic_links_user_id_foreign` (`user_id`),
  ADD KEY `email` (`email`);

--
-- Indices de la tabla `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `permissions_created_by_foreign` (`created_by`),
  ADD KEY `permissions_updated_by_foreign` (`updated_by`),
  ADD KEY `module` (`module`);

--
-- Indices de la tabla `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `roles_created_by_foreign` (`created_by`),
  ADD KEY `roles_updated_by_foreign` (`updated_by`);

--
-- Indices de la tabla `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_id_permission_id` (`role_id`,`permission_id`),
  ADD KEY `role_permissions_permission_id_foreign` (`permission_id`);

--
-- Indices de la tabla `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indices de la tabla `two_factor_auth`
--
ALTER TABLE `two_factor_auth`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `two_factor_auth_created_by_foreign` (`created_by`),
  ADD KEY `two_factor_auth_updated_by_foreign` (`updated_by`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `users_created_by_foreign` (`created_by`),
  ADD KEY `users_updated_by_foreign` (`updated_by`),
  ADD KEY `users_deleted_by_foreign` (`deleted_by`),
  ADD KEY `created_at` (`created_at`);

--
-- Indices de la tabla `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id_role_id` (`user_id`,`role_id`),
  ADD KEY `user_roles_role_id_foreign` (`role_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `app_settings`
--
ALTER TABLE `app_settings`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=345;

--
-- AUTO_INCREMENT de la tabla `auth_providers`
--
ALTER TABLE `auth_providers`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `company_users`
--
ALTER TABLE `company_users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `magic_links`
--
ALTER TABLE `magic_links`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT de la tabla `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT de la tabla `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT de la tabla `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `two_factor_auth`
--
ALTER TABLE `two_factor_auth`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD CONSTRAINT `api_tokens_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  ADD CONSTRAINT `api_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Filtros para la tabla `auth_providers`
--
ALTER TABLE `auth_providers`
  ADD CONSTRAINT `auth_providers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  ADD CONSTRAINT `auth_providers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `magic_links`
--
ALTER TABLE `magic_links`
  ADD CONSTRAINT `magic_links_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  ADD CONSTRAINT `permissions_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Filtros para la tabla `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `roles_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  ADD CONSTRAINT `roles_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Filtros para la tabla `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `two_factor_auth`
--
ALTER TABLE `two_factor_auth`
  ADD CONSTRAINT `two_factor_auth_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  ADD CONSTRAINT `two_factor_auth_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  ADD CONSTRAINT `two_factor_auth_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  ADD CONSTRAINT `users_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  ADD CONSTRAINT `users_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Filtros para la tabla `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
