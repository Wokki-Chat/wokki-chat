-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 14, 2026 at 01:39 PM
-- Server version: 10.11.14-MariaDB-0+deb12u2
-- PHP Version: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `wokki20_chat`
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `id` int(11) NOT NULL,
  `saved_name` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bots`
--

CREATE TABLE `bots` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `bot_token` varchar(512) DEFAULT NULL,
  `status` enum('online','offline') NOT NULL DEFAULT 'offline',
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bot_commands`
--

CREATE TABLE `bot_commands` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bot_id` varchar(255) NOT NULL,
  `command` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp(),
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `channels`
--

CREATE TABLE `channels` (
  `channel_id` char(36) NOT NULL,
  `channel_name` text DEFAULT NULL,
  `channel_type` text DEFAULT NULL,
  `channel_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `channel_updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `channel_group_id` char(36) DEFAULT NULL,
  `server_id` char(36) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT NULL,
  `channel_index` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `channel_groups`
--

CREATE TABLE `channel_groups` (
  `channel_group_id` char(36) NOT NULL,
  `channel_group_name` text DEFAULT NULL,
  `channel_group_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `channel_group_updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `server_id` char(36) DEFAULT NULL,
  `channel_group_index` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `contact_id` char(36) NOT NULL,
  `contact_name` text DEFAULT NULL,
  `contact_picture` text DEFAULT NULL,
  `contact_created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_message_sent` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_requests`
--

CREATE TABLE `contact_requests` (
  `id` int(11) NOT NULL,
  `contact_id` char(36) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_users`
--

CREATE TABLE `contact_users` (
  `contact_id` char(36) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `direct_messages`
--

CREATE TABLE `direct_messages` (
  `id` char(36) NOT NULL,
  `message` text DEFAULT NULL,
  `from_id` int(11) DEFAULT NULL,
  `to_id` int(11) DEFAULT NULL,
  `created_at` datetime(6) DEFAULT NULL,
  `updated_at` datetime(6) DEFAULT NULL,
  `edited` tinyint(1) DEFAULT 0,
  `parent_message_id` varchar(255) DEFAULT NULL,
  `assets` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_codes`
--

CREATE TABLE `email_verification_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code` varchar(255) NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `expiry_date` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `friends`
--

CREATE TABLE `friends` (
  `user_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ideas`
--

CREATE TABLE `ideas` (
  `id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `image_path` varchar(512) DEFAULT NULL,
  `status` enum('voting','planned','implemented') DEFAULT 'voting'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `idea_votes`
--

CREATE TABLE `idea_votes` (
  `id` int(11) NOT NULL,
  `idea_id` char(36) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invites`
--

CREATE TABLE `invites` (
  `id` int(11) NOT NULL,
  `code` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `server_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Kudos`
--

CREATE TABLE `Kudos` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `kudo_amount` int(11) NOT NULL,
  `kudo_created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

CREATE TABLE `maintenance` (
  `id` int(11) NOT NULL,
  `type` enum('maintenance','warning') NOT NULL,
  `message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` char(36) NOT NULL,
  `message` text DEFAULT NULL,
  `sent_by` int(11) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) DEFAULT NULL,
  `edited` tinyint(1) NOT NULL DEFAULT 0,
  `server_id` char(36) DEFAULT NULL,
  `channel_id` char(36) DEFAULT NULL,
  `parent_message_id` varchar(255) DEFAULT NULL,
  `assets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assets`)),
  `sent_by_bot` char(36) DEFAULT NULL,
  `command` text DEFAULT NULL,
  `command_user_id` varchar(255) DEFAULT NULL,
  `embed` longtext DEFAULT NULL,
  `contact_id` char(36) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `message_reactions`
--

CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL,
  `message_id` char(36) DEFAULT NULL,
  `reaction` text DEFAULT NULL,
  `super_reaction` tinyint(1) DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `bot_id` char(36) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `user_id` int(11) NOT NULL,
  `server_id` char(36) DEFAULT NULL,
  `channel_id` char(36) DEFAULT NULL,
  `contact_id` char(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profile_widgets`
--

CREATE TABLE `profile_widgets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `widget_name` varchar(255) NOT NULL,
  `widget_access_token` text DEFAULT NULL,
  `widget_refresh_token` text DEFAULT NULL,
  `show_on_profile` tinyint(1) DEFAULT 1,
  `widget_access_token_valid_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` char(36) NOT NULL,
  `send_messages` tinyint(1) DEFAULT 0,
  `view_channels` tinyint(1) DEFAULT 0,
  `manage_channels` tinyint(1) DEFAULT 0,
  `manage_server` tinyint(1) DEFAULT 0,
  `manage_roles` tinyint(1) DEFAULT 0,
  `kick_members` tinyint(1) DEFAULT 0,
  `ban_members` tinyint(1) DEFAULT 0,
  `mute_members` tinyint(1) DEFAULT 0,
  `manage_groups` tinyint(1) DEFAULT 0,
  `read_message_history` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `servers`
--

CREATE TABLE `servers` (
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`channels`)),
  `channel_groups` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`channel_groups`)),
  `created_by` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`roles`)),
  `join_without_invite` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `server_type` enum('normal','community') NOT NULL DEFAULT 'normal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `server_members`
--

CREATE TABLE `server_members` (
  `id` bigint(20) NOT NULL,
  `server_id` char(36) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `bot_id` char(36) DEFAULT NULL,
  `joined_at` timestamp NULL DEFAULT current_timestamp(),
  `position` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `server_roles`
--

CREATE TABLE `server_roles` (
  `role_id` char(36) NOT NULL,
  `role_name` varchar(255) NOT NULL,
  `role_color` varchar(7) DEFAULT NULL,
  `server_id` char(36) NOT NULL,
  `add_on_join` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tag_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `tag_icon` varchar(255) DEFAULT NULL,
  `tag_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` text NOT NULL,
  `password_hash` text NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `nickname` varchar(100) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `status` enum('online','offline','idle','busy') NOT NULL DEFAULT 'offline',
  `status_manually_set` tinyint(1) DEFAULT 0,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`roles`)),
  `premium` tinyint(1) DEFAULT 0,
  `premium_expires_at` datetime DEFAULT NULL,
  `premium_know` tinyint(1) DEFAULT 0,
  `profile_picture` text DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `is_staff` tinyint(1) NOT NULL DEFAULT 0,
  `is_developer` tinyint(1) NOT NULL DEFAULT 0,
  `added_chat_account_once` int(11) DEFAULT 0,
  `profile_color_primary` char(7) DEFAULT NULL,
  `profile_color_accent` char(7) DEFAULT NULL,
  `needs_investigation` tinyint(1) DEFAULT 0,
  `profile_banner` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_connections`
--

CREATE TABLE `user_connections` (
  `id` int(11) NOT NULL,
  `connection_name` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `connection_user_id` varchar(255) DEFAULT NULL,
  `connection_user_name` varchar(255) DEFAULT NULL,
  `connection_user_url` varchar(255) DEFAULT NULL,
  `connected_at` datetime NOT NULL DEFAULT current_timestamp(),
  `connection_user_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_server_roles`
--

CREATE TABLE `user_server_roles` (
  `id` bigint(20) NOT NULL,
  `server_id` char(36) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `bot_id` char(36) DEFAULT NULL,
  `role_id` char(36) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_tokens`
--

CREATE TABLE `user_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `access_token` varchar(512) NOT NULL,
  `refresh_token` varchar(512) NOT NULL,
  `access_token_expires_at` datetime NOT NULL,
  `refresh_token_expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bots`
--
ALTER TABLE `bots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `bot_commands`
--
ALTER TABLE `bot_commands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_command_per_bot` (`bot_id`,`command`) USING HASH;

--
-- Indexes for table `channels`
--
ALTER TABLE `channels`
  ADD PRIMARY KEY (`channel_id`),
  ADD KEY `channel_group_id` (`channel_group_id`);

--
-- Indexes for table `channel_groups`
--
ALTER TABLE `channel_groups`
  ADD PRIMARY KEY (`channel_group_id`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`contact_id`);

--
-- Indexes for table `contact_requests`
--
ALTER TABLE `contact_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_users`
--
ALTER TABLE `contact_users`
  ADD PRIMARY KEY (`contact_id`,`user_id`);

--
-- Indexes for table `direct_messages`
--
ALTER TABLE `direct_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_from_id` (`from_id`),
  ADD KEY `fk_to_id` (`to_id`),
  ADD KEY `fk_parent_message_id` (`parent_message_id`);

--
-- Indexes for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`user_id`,`friend_id`),
  ADD KEY `fk_friend` (`friend_id`);

--
-- Indexes for table `ideas`
--
ALTER TABLE `ideas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `idea_votes`
--
ALTER TABLE `idea_votes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invites`
--
ALTER TABLE `invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `Kudos`
--
ALTER TABLE `Kudos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sent_by` (`sent_by`),
  ADD KEY `fk_parent_message` (`parent_message_id`),
  ADD KEY `fk_sent_by_bot` (`sent_by_bot`);

--
-- Indexes for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `profile_widgets`
--
ALTER TABLE `profile_widgets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `servers`
--
ALTER TABLE `servers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `server_members`
--
ALTER TABLE `server_members`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `server_roles`
--
ALTER TABLE `server_roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`tag_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`) USING HASH;

--
-- Indexes for table `user_connections`
--
ALTER TABLE `user_connections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_connection` (`user_id`,`connection_user_id`,`connection_user_name`);

--
-- Indexes for table `user_server_roles`
--
ALTER TABLE `user_server_roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bot_commands`
--
ALTER TABLE `bot_commands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_requests`
--
ALTER TABLE `contact_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `idea_votes`
--
ALTER TABLE `idea_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invites`
--
ALTER TABLE `invites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Kudos`
--
ALTER TABLE `Kudos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance`
--
ALTER TABLE `maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_reactions`
--
ALTER TABLE `message_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profile_widgets`
--
ALTER TABLE `profile_widgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `server_members`
--
ALTER TABLE `server_members`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_connections`
--
ALTER TABLE `user_connections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_server_roles`
--
ALTER TABLE `user_server_roles`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_tokens`
--
ALTER TABLE `user_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bots`
--
ALTER TABLE `bots`
  ADD CONSTRAINT `bots_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `channels`
--
ALTER TABLE `channels`
  ADD CONSTRAINT `channels_ibfk_1` FOREIGN KEY (`channel_group_id`) REFERENCES `channel_groups` (`channel_group_id`);

--
-- Constraints for table `direct_messages`
--
ALTER TABLE `direct_messages`
  ADD CONSTRAINT `fk_from_id` FOREIGN KEY (`from_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_parent_message_id` FOREIGN KEY (`parent_message_id`) REFERENCES `direct_messages` (`id`),
  ADD CONSTRAINT `fk_to_id` FOREIGN KEY (`to_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  ADD CONSTRAINT `email_verification_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `friends`
--
ALTER TABLE `friends`
  ADD CONSTRAINT `fk_friend` FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);


--
-- Constraints for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
