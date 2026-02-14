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