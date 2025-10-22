-- TikTok Live Host Agency Database Schema
-- Compatible with Laragon/HeidiSQL

-- Create database
CREATE DATABASE IF NOT EXISTS `tiktok_live_host` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tiktok_live_host`;

-- Users table
CREATE TABLE `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL UNIQUE,
    `email` varchar(100) NOT NULL UNIQUE,
    `password` varchar(255) NOT NULL,
    `role` enum('admin', 'live_seller') NOT NULL DEFAULT 'live_seller',
    `full_name` varchar(100) NOT NULL,
    `profile_image` varchar(255) DEFAULT NULL,
    `experienced_status` enum('newbie', 'tenured') NOT NULL DEFAULT 'newbie',
    `status` enum('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_username` (`username`),
    KEY `idx_email` (`email`),
    KEY `idx_role` (`role`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table
CREATE TABLE `activity_logs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance time slots table
CREATE TABLE `attendance_time_slots` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `duration_hours` decimal(3,1) NOT NULL,
    `start_time` time NOT NULL,
    `end_time` time NOT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_duration` (`duration_hours`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Insert default admin user (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `role`, `full_name`, `experienced_status`, `status`) VALUES
('admin', 'admin@tiktok-live-host.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 'tenured', 'active');

-- Attendance table (stores seller attendance / schedule submissions)
    CREATE TABLE `attendance` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `seller_id` INT NOT NULL,
        `attendance_date` date NOT NULL,
        `duration` enum('3-hour','4-hour') NOT NULL,
        `time_slot` varchar(100) NOT NULL,
        `solds_quantity` INT DEFAULT 0,
        `hours_worked` decimal(4,2) DEFAULT NULL,
        `total_sold_photo` varchar(255) DEFAULT NULL,
        `check_in_time` time DEFAULT NULL,
        `check_out_time` time DEFAULT NULL,
        `status` enum('scheduled','in_progress','checked_in','completed','cancelled') NOT NULL DEFAULT 'scheduled',
        `notes` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_seller_id` (`seller_id`),
        KEY `idx_attendance_date` (`attendance_date`),
        KEY `idx_status` (`status`),
        FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 

        -- Accounts table: group sellers under an account (e.g., Snappyclo2nd)
        CREATE TABLE `accounts` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(120) NOT NULL UNIQUE,
            `notes` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        -- Account members: link users to accounts
        CREATE TABLE `account_members` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `account_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `role` VARCHAR(50) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_account_id` (`account_id`),
            KEY `idx_user_id` (`user_id`),
            CONSTRAINT `fk_account_members_account` FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_account_members_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;