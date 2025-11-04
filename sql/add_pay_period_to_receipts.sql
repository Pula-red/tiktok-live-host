-- Add pay period fields to payment_receipts table
-- This allows admin to specify which cut-off period each payment covers

ALTER TABLE payment_receipts 
ADD COLUMN pay_period_start DATE NULL AFTER payment_date,
ADD COLUMN pay_period_end DATE NULL AFTER pay_period_start,
ADD INDEX idx_pay_period (pay_period_start, pay_period_end);

-- Add comment to explain the fields
ALTER TABLE payment_receipts 
MODIFY COLUMN pay_period_start DATE NULL COMMENT 'Start date of the pay period this payment covers (e.g., 1st or 16th of month)',
MODIFY COLUMN pay_period_end DATE NULL COMMENT 'End date of the pay period this payment covers (e.g., 15th or last day of month)';
