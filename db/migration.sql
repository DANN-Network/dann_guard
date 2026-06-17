-- DANN-GUARD: Database migration for restricted_admin and protect_permissions
-- Run: mysql -u root panel < migration.sql

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS `restricted_admin` tinyint(1) NOT NULL DEFAULT '0' AFTER `root_admin`,
    ADD COLUMN IF NOT EXISTS `protect_permissions` text DEFAULT NULL AFTER `restricted_admin`,
    ADD COLUMN IF NOT EXISTS `created_by_admin_id` int(11) DEFAULT NULL AFTER `protect_permissions`;
