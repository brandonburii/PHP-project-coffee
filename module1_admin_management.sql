-- ============================================================================
-- Module 1: Admin Management + Authorization support
-- ============================================================================

ALTER TABLE `user`
    ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `points`,
    ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `active`;

UPDATE `user` SET `active` = 1 WHERE `active` IS NULL;
