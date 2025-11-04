-- Add hourly rate column to users table
-- Salary calculation: Total Hours × Hourly Rate
-- Newbie rate: ₱125/hr | Tenured rate: ₱166/hr

ALTER TABLE users 
ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT 166.00;

-- Set rates based on experience level
UPDATE users 
SET hourly_rate = 125.00 
WHERE experienced_status = 'newbie';

UPDATE users 
SET hourly_rate = 166.00 
WHERE experienced_status = 'tenured';

-- Add index for faster queries
ALTER TABLE users ADD INDEX idx_hourly_rate (hourly_rate);

-- View rates by user:
-- SELECT id, full_name, experienced_status, hourly_rate FROM users ORDER BY hourly_rate;
