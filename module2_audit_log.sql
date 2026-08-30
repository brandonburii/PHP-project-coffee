-- ============================================================================
-- Module 2: Advanced Audit Logging + Before/After Change Tracking
-- ============================================================================

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NULL,
    `username` VARCHAR(100) NULL,
    `role` VARCHAR(100) NULL,
    `module` VARCHAR(100) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `before_data` LONGTEXT NULL,
    `after_data` LONGTEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_user_id` (`user_id`),
    KEY `idx_audit_module` (`module`),
    KEY `idx_audit_action` (`action`),
    KEY `idx_audit_created_at` (`created_at`),
    CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
