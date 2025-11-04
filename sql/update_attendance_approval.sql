-- Add approval-related fields to attendance table
ALTER TABLE `attendance` 
    MODIFY COLUMN `status` enum('pending_approval','approved','rejected','scheduled','in_progress','checked_in','completed','cancelled') NOT NULL DEFAULT 'pending_approval',
    ADD COLUMN `approved_by` INT DEFAULT NULL AFTER `status`,
    ADD COLUMN `approved_at` timestamp NULL DEFAULT NULL AFTER `approved_by`,
    ADD COLUMN `rejection_reason` text DEFAULT NULL AFTER `approved_at`,
    ADD FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Update existing records to approved state since they were already in the system
UPDATE `attendance` SET `status` = 'approved' WHERE `status` = 'completed';

-- Create index for faster pending attendance queries
CREATE INDEX `idx_attendance_approval` ON `attendance` (`status`, `attendance_date`);