-- Optional: link rewards to existing products (also applied via ensure_reward_product_id_column() in _base.php)
ALTER TABLE `reward`
    ADD COLUMN `product_id` CHAR(4) NULL DEFAULT NULL AFTER `id`,
    ADD KEY `idx_reward_product_id` (`product_id`);

ALTER TABLE `reward`
    ADD CONSTRAINT `reward_ibfk_product`
    FOREIGN KEY (`product_id`) REFERENCES `product` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;
