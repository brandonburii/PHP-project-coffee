-- ============================================================================
-- Loyalty & Rewards System
-- ----------------------------------------------------------------------------
-- Import into `db10` via phpMyAdmin AFTER batch1 (voucher + user.points).
-- If ALTER fails because columns already exist, skip that section.
-- ============================================================================

-- (1) Configurable settings (points conversion rates) ------------------------
CREATE TABLE IF NOT EXISTS `setting` (
    `key`   VARCHAR(50)  NOT NULL,
    `value` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`key`)
);

INSERT INTO `setting` (`key`, `value`) VALUES
('points_per_rm',  '1'),
('point_value_rm', '0.10')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- (2) Rewards catalog (redeem points for items) ------------------------------
CREATE TABLE IF NOT EXISTS `reward` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100)  NOT NULL,
    `description` VARCHAR(500)  NOT NULL,
    `photo`       VARCHAR(100)  NOT NULL,
    `points`      INT           NOT NULL,
    `stock`       INT           NOT NULL DEFAULT 0,
    `active`      TINYINT(1)    NOT NULL DEFAULT 1,
    `sort_order`  INT           NOT NULL DEFAULT 0
);

-- (3) Reward redemption history ----------------------------------------------
CREATE TABLE IF NOT EXISTS `reward_redemption` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT          NOT NULL,
    `reward_id`  INT          NOT NULL,
    `points`     INT          NOT NULL,
    `status`     ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'completed',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`)   REFERENCES `user`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`reward_id`) REFERENCES `reward`(`id`) ON DELETE CASCADE
);

-- (4) Extend voucher table (run once; ignore Duplicate column errors) --------
-- description, min_spend, start_date, max_usage, usage_count
ALTER TABLE `voucher`
    ADD COLUMN `description` VARCHAR(255)  NULL AFTER `code`,
    ADD COLUMN `min_spend`   DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `value`,
    ADD COLUMN `start_date`  DATE          NULL AFTER `min_spend`,
    ADD COLUMN `max_usage`   INT           NULL AFTER `expiry`,
    ADD COLUMN `usage_count` INT           NOT NULL DEFAULT 0 AFTER `max_usage`;

-- Backfill start_date / description for existing vouchers
UPDATE `voucher` SET `start_date` = CURDATE() WHERE `start_date` IS NULL;
UPDATE `voucher` SET `description` = CONCAT('Promo: ', `code`) WHERE `description` IS NULL OR `description` = '';

-- Sample rewards -------------------------------------------------------------
INSERT INTO `reward` (`name`, `description`, `photo`, `points`, `stock`, `active`, `sort_order`) VALUES
('Signature Latte',   'A free signature latte of your choice.',           '0.jpg',  500, 50, 1, 1),
('Matcha Latte',      'Redeem a refreshing matcha latte.',                '0.jpg',  800, 30, 1, 2),
('Cheesecake Slice',  'One slice of our house cheesecake.',               '0.jpg', 1200, 20, 1, 3),
('Coffee Tumbler',    'Branded specialty coffee tumbler.',                '0.jpg', 2500, 10, 1, 4);
