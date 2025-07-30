-- =====================================================================
--                          SSO Panel Schema (Corrected Version)
--      Generated based on the provided database dump to ensure full compatibility.
-- =====================================================================

--
-- Table structure for table `deleted_staff`
--
CREATE TABLE IF NOT EXISTS `deleted_staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_id` int(11) DEFAULT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `discord_id` varchar(255) DEFAULT NULL,
  `discord_id2` varchar(255) DEFAULT NULL,
  `steam_id` varchar(255) DEFAULT NULL,
  `permissions` varchar(255) DEFAULT NULL,
  `joined_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `delete_reason` varchar(255) DEFAULT NULL,
  `deleted_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `login_attempts` (Corrected Structure)
--
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(50) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `attempt_time` datetime DEFAULT NULL,
  `viewed` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `passkey_credentials`
--
CREATE TABLE IF NOT EXISTS `passkey_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `registration_requests`
--
CREATE TABLE IF NOT EXISTS `registration_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `discord_id` varchar(255) DEFAULT NULL,
  `steam_id` varchar(255) DEFAULT NULL,
  `tracking_code` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `processed_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `settings`
--
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `staff-manage`
--
CREATE TABLE IF NOT EXISTS `staff-manage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `discord_id` varchar(255) DEFAULT NULL,
  `discord_id2` varchar(255) DEFAULT NULL,
  `steam_id` varchar(255) DEFAULT NULL,
  `tracking_code` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT NULL,
  `is_verify` tinyint(1) DEFAULT NULL,
  `discord_conn` tinyint(1) DEFAULT NULL,
  `permissions` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `users`
--
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `is_owner` tinyint(1) DEFAULT NULL,
  `has_user_panel` tinyint(1) DEFAULT NULL,
  `is_staff` tinyint(1) DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `suspended_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `staff_permissions`
--
CREATE TABLE IF NOT EXISTS `staff_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `has_user_panel` tinyint(1) NOT NULL DEFAULT 0,
  `is_owner` tinyint(1) NOT NULL DEFAULT 0,
  `has_developer_access` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_dashboard` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_users` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_staff` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_permissions` tinyint(1) NOT NULL DEFAULT 0,
  `can_create_user` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_requests` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_archive` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_chart` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_alerts` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_settings` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_finance` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id_unique` (`staff_id`),
  CONSTRAINT `staff_permissions_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff-manage` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `user_permissions`
--
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `has_user_panel` tinyint(1) NOT NULL DEFAULT 0,
  `is_owner` tinyint(1) NOT NULL DEFAULT 0,
  `has_developer_access` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_dashboard` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_users` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_staff` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_permissions` tinyint(1) NOT NULL DEFAULT 0,
  `can_create_user` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_requests` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_archive` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_chart` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_alerts` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_settings` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_finance` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_unique` (`user_id`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Seeding new setting for `vui_theme`
--
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('vui_theme', 'vui-theme-default');
--
-- Seeding initial data for table `settings`
--
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('app_admin_panel_url','https://your-domain.com/admin'),
('app_base_url','https://your-domain.com'),
('app_panel_url','https://your-domain.com/Dashboard'),
('app_token_dir','/var/www/sso-system/tokens'),
('login_notice_enabled','1'),
('login_notice_expiry',NULL),
('login_notice_text','به پنل خوش آمدید.'),
('pterodactyl_api_key_application',''),
('pterodactyl_api_key_client',''),
('pterodactyl_server_id',''),
('pterodactyl_url','');