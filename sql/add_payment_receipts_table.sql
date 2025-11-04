-- Payment receipts table for tracking admin payments to users
CREATE TABLE IF NOT EXISTS `payment_receipts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `admin_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `receipt_image` VARCHAR(255) NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT 'GCash',
    `reference_number` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'completed',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_admin_id` (`admin_id`),
    KEY `idx_payment_date` (`payment_date`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payment_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
