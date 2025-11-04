# Hourly Rate Setup Guide

## Salary Calculation Formula
```
Total Salary = Total Hours Worked × Hourly Rate
```

## Rate Structure
- **Newbie Sellers:** ₱125 per hour
- **Tenured Sellers:** ₱166 per hour

## Setup Instructions

### Step 1: Run the SQL Migration
Execute the SQL file to add the `hourly_rate` column and set initial rates:

```bash
# Using MySQL command line
mysql -u root -p tiktok_live_host < sql/add_hourly_rate_to_users.sql

# Or using Laragon Terminal
cd c:\laragon\www\tiktok-live-host
mysql -u root tiktok_live_host < sql\add_hourly_rate_to_users.sql
```

This will:
1. Add `hourly_rate` column to `users` table
2. Set ₱125 for all newbie sellers
3. Set ₱166 for all tenured sellers
4. Add an index for faster queries

### Step 2: Verify Rates Were Set
Run this query to check:

```sql
SELECT 
    id,
    full_name,
    experienced_status,
    hourly_rate
FROM users
ORDER BY experienced_status, full_name;
```

### Step 3: How to Use in Payment Form

1. **Select Pay Period** - Choose the date range to calculate for
2. **Show Form** - Click the "Show Form" button
3. **Select User** - Choose a live seller from dropdown
4. **Auto-Calculate** - System will:
   - Fetch all approved attendance records in the pay period
   - Calculate: `SUM(hours_worked) × user's hourly_rate`
   - Display amount in the Amount field
   - Show breakdown: "3.0 hours × ₱125/hr = ₱375"

### Example Calculations

#### Newbie Seller (₱125/hr)
```
User: John Doe (Newbie)
Pay Period: Nov 1-15, 2025

Attendance Records:
- Nov 2: 2.5 hours (approved)
- Nov 5: 3.0 hours (approved)
- Nov 8: 2.0 hours (approved)
- Nov 12: 1.5 hours (pending) ← Not counted

Total: 7.5 hours × ₱125 = ₱937.50
Display: ₱938 (rounded, no decimals)
```

#### Tenured Seller (₱166/hr)
```
User: Jane Smith (Tenured)
Pay Period: Nov 1-15, 2025

Attendance Records:
- Nov 3: 4.0 hours (approved)
- Nov 7: 3.5 hours (approved)
- Nov 10: 4.0 hours (approved)

Total: 11.5 hours × ₱166 = ₱1,909.00
Display: ₱1,909
```

## Manual Rate Adjustments

### Change a Specific User's Rate
```sql
-- Set custom rate for a specific user
UPDATE users 
SET hourly_rate = 150.00 
WHERE id = 5;
```

### Change All Newbie Rates
```sql
-- Update all newbie sellers to new rate
UPDATE users 
SET hourly_rate = 130.00 
WHERE experienced_status = 'newbie';
```

### Change All Tenured Rates
```sql
-- Update all tenured sellers to new rate
UPDATE users 
SET hourly_rate = 180.00 
WHERE experienced_status = 'tenured';
```

## Troubleshooting

### Amount shows "0.00"
**Possible causes:**
1. No approved attendance records in the selected pay period
2. User's hourly_rate is NULL or 0
3. All attendance records are "pending" or "rejected"

**Solution:**
```sql
-- Check user's rate
SELECT id, full_name, hourly_rate 
FROM users 
WHERE id = [USER_ID];

-- Check attendance records
SELECT 
    attendance_date,
    hours_worked,
    status
FROM attendance
WHERE seller_id = [USER_ID]
  AND attendance_date BETWEEN '[START_DATE]' AND '[END_DATE]'
ORDER BY attendance_date;
```

### "Failed to calculate" error
**Check:**
1. Database connection is working
2. Pay period format is correct (YYYY-MM-DD|YYYY-MM-DD)
3. User exists in database
4. Browser console for JavaScript errors (F12)

### Rate not updating after SQL change
**Solution:**
1. Refresh the page (F5)
2. Clear browser cache (Ctrl+Shift+Delete)
3. Verify database update was successful:
   ```sql
   SELECT hourly_rate FROM users WHERE id = [USER_ID];
   ```

## Data Flow

```
User Selection
    ↓
JavaScript calls calculateEarnings()
    ↓
AJAX POST to host-payments.php?action=calculate_earnings
    ↓
PHP queries:
  1. Get user's hourly_rate from users table
  2. Get SUM(hours_worked) from attendance table
     WHERE status = 'approved' AND date in range
    ↓
Calculate: total_hours × hourly_rate
    ↓
Return JSON response
    ↓
JavaScript updates:
  - Amount field: ₱1,909
  - Breakdown: "11.5 hours × ₱166/hr = ₱1,909"
```

## Important Notes

- **Only approved attendance** counts toward salary
- **Pending/rejected records** are excluded
- **Hours are summed** across all approved days in the pay period
- **Amount field is readonly** - cannot be manually edited
- **Decimal points removed** from display (₱498 not ₱498.00)
- **Calculation is real-time** - happens when user is selected

## Database Schema

### users table
```sql
CREATE TABLE users (
    id INT PRIMARY KEY,
    full_name VARCHAR(255),
    experienced_status ENUM('newbie', 'tenured'),
    hourly_rate DECIMAL(10,2) DEFAULT 166.00 COMMENT 'Hourly rate',
    -- other columns...
);
```

### attendance table
```sql
CREATE TABLE attendance (
    id INT PRIMARY KEY,
    seller_id INT,
    attendance_date DATE,
    hours_worked DECIMAL(5,2),
    status ENUM('pending', 'approved', 'rejected'),
    -- other columns...
);
```

---

**Last Updated:** January 2025  
**Related Files:** 
- `admin/host-payments.php` - Payment form with auto-calculation
- `sql/add_hourly_rate_to_users.sql` - Database migration
