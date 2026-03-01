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