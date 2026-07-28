-- ============================================================================
-- Batch 2: Permanent Shopping Cart
-- ----------------------------------------------------------------------------
-- Import into `db10` via phpMyAdmin (Import tab), or run in the SQL tab.
-- Safe to run once.
-- ============================================================================

-- Cart rows belong to a member; one row per product
CREATE TABLE IF NOT EXISTS `cart` (
    `user_id`    INT         NOT NULL,
    `product_id` VARCHAR(10) NOT NULL,
    `unit`       INT         NOT NULL DEFAULT 1,
    PRIMARY KEY (`user_id`, `product_id`),
    FOREIGN KEY (`user_id`)    REFERENCES `user`(`id`)     ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `product`(`id`)  ON DELETE CASCADE
);
