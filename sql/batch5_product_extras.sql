-- ============================================================================
-- Batch 5: Recently Viewed, Comparison, Tags, Flash Sale
-- ----------------------------------------------------------------------------
-- Import into `db10` via phpMyAdmin (Import tab), or run in the SQL tab.
-- Safe to run once.
-- ============================================================================

ALTER TABLE `product`
    ADD COLUMN `origin`     VARCHAR(100)   NULL AFTER `description`,
    ADD COLUMN `roast`      VARCHAR(50)    NULL AFTER `origin`,
    ADD COLUMN `tag`        VARCHAR(20)    NULL AFTER `roast`,
    ADD COLUMN `sale_price` DECIMAL(10,2)  NULL AFTER `price`,
    ADD COLUMN `sale_start` DATETIME       NULL AFTER `sale_price`,
    ADD COLUMN `sale_end`   DATETIME       NULL AFTER `sale_start`;
