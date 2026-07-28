-- ============================================================================
-- Batch 3: Product Stock History + Low Stock Notification
-- ----------------------------------------------------------------------------
-- Import into `db10` via phpMyAdmin (Import tab), or run in the SQL tab.
-- Safe to run once.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `stock_history` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` VARCHAR(10)  NOT NULL,
    `action`     ENUM('added','edited','sold') NOT NULL,
    `old_stock`  INT          NOT NULL,
    `new_stock`  INT          NOT NULL,
    `change_qty` INT          NOT NULL,
    `user_id`    INT          NULL,
    `username`   VARCHAR(100) NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `product`(`id`) ON DELETE CASCADE
);
