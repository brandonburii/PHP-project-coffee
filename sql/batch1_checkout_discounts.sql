-- ============================================================================
-- Batch 1: Checkout Discounts  (Reward Points + Discount Vouchers)
-- ----------------------------------------------------------------------------
-- Import this file into the `db10` database via phpMyAdmin (Import tab),
-- or run the statements in the SQL tab. Safe to run once.
-- ============================================================================

-- (1) Reward points balance stored on each user ------------------------------
ALTER TABLE `user`
    ADD COLUMN `points` INT NOT NULL DEFAULT 0;

-- (2) Discount / points tracking stored on each order ------------------------
ALTER TABLE `order`
    ADD COLUMN `subtotal`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `count`,
    ADD COLUMN `discount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `subtotal`,
    ADD COLUMN `points_earned` INT           NOT NULL DEFAULT 0,
    ADD COLUMN `points_used`   INT           NOT NULL DEFAULT 0,
    ADD COLUMN `voucher_code`  VARCHAR(20)   NULL;

-- (3) Discount vouchers (maintained by admin) --------------------------------
CREATE TABLE IF NOT EXISTS `voucher` (
    `code`   VARCHAR(20)             NOT NULL,
    `type`   ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    `value`  DECIMAL(10,2)           NOT NULL,
    `expiry` DATE                    NOT NULL,
    `active` TINYINT(1)              NOT NULL DEFAULT 1,
    PRIMARY KEY (`code`)
);

-- Optional sample vouchers ---------------------------------------------------
INSERT INTO `voucher` (`code`, `type`, `value`, `expiry`, `active`) VALUES
('WELCOME10', 'percent', 10.00, '2027-12-31', 1),
('SAVE5',     'fixed',    5.00, '2027-12-31', 1);
