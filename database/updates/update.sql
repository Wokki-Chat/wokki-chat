-- /database/updates/update.sql
DROP TABLE IF EXISTS `notifications`;

CREATE TABLE `notifications` (
    `id` CHAR(36) PRIMARY KEY,
    `user_id` INT NOT NULL,
    `server_id` CHAR(36),
    `channel_id` CHAR(36),
    `contact_id` CHAR(36),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE ideas ADD COLUMN github_issue_number INT NULL;
ALTER TABLE ideas ADD COLUMN github_issue_node_id VARCHAR(100) NULL;
ALTER TABLE ideas ADD COLUMN github_project_item_id VARCHAR(100) NULL;
ALTER TABLE ideas ADD COLUMN github_assignees JSON NULL;

CREATE INDEX idx_ideas_github_issue_number ON ideas (github_issue_number);

-- 2/27/2026

ALTER TABLE assets
ADD COLUMN original_name TEXT,
ADD COLUMN mime_type VARCHAR(255);

-- 3/1/2026

DROP TABLE IF EXISTS `oauth_authorization_codes`;
DROP TABLE IF EXISTS `oauth_client_redirect_uris`;
DROP TABLE IF EXISTS `oauth_clients`;

CREATE TABLE `oauth_clients` (
    `id` CHAR(36) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `client_id` CHAR(36) NOT NULL UNIQUE,
    `client_secret` VARCHAR(255) NOT NULL,
    `bot_id` CHAR(36) NOT NULL,
    `grant_types` VARCHAR(255) NOT NULL DEFAULT 'authorization_code',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `revoked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_client_id` (`client_id`),
    INDEX `idx_bot_id` (`bot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `oauth_client_redirect_uris` (
    `id` CHAR(36) NOT NULL,
    `client_id` CHAR(36) NOT NULL,
    `uri` VARCHAR(2048) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_client_id` (`client_id`),
    FOREIGN KEY (`client_id`) REFERENCES `oauth_clients`(`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `oauth_authorization_codes` (
    `id` CHAR(36) NOT NULL,
    `code` VARCHAR(255) NOT NULL UNIQUE,
    `client_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `redirect_uri` VARCHAR(2048) NOT NULL,
    `scopes` VARCHAR(1024) NULL,
    `code_challenge` VARCHAR(255) NULL,
    `code_challenge_method` VARCHAR(10) NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_code` (`code`),
    INDEX `idx_client_id` (`client_id`),
    FOREIGN KEY (`client_id`) REFERENCES `oauth_clients`(`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `user_tokens`
ADD COLUMN `client_id` CHAR(36) NULL,
ADD COLUMN `scopes` VARCHAR(1024) NULL;

ALTER TABLE `user_tokens`
ADD INDEX `idx_client_id` (`client_id`);

ALTER TABLE `user_tokens`
ADD FOREIGN KEY (`client_id`) REFERENCES `oauth_clients`(`id`) ON DELETE SET NULL;

-- 3/2/2026
ALTER TABLE ideas ADD COLUMN type ENUM('idea', 'bug', 'api_request') DEFAULT 'idea' AFTER status;
ALTER TABLE ideas ADD COLUMN is_private BOOLEAN DEFAULT FALSE AFTER type;