# Auto-Calculate Earnings Feature

## Overview
The Host Payments page now automatically calculates and fills the payment amount based on the selected user's attendance records for the chosen pay period.

## How It Works

### 1. Database Schema Update
First, run the SQL migration to add hourly rate fields to the users table:
```sql
-- File: sql/add_hourly_rate_to_users.sql
ALTER TABLE users 
ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT 166.00 COMMENT 'Hourly rate for attendance';

ALTER TABLE users 
ADD COLUMN items_sold_rate DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Rate per item sold';
```

**To apply this migration:**
```bash
# Using MySQL command line
mysql -u root -p tiktok_live_host < sql/add_hourly_rate_to_users.sql

# Or import via phpMyAdmin
# 1. Open phpMyAdmin
# 2. Select tiktok_live_host database
# 3. Go to Import tab
# 4. Choose sql/add_hourly_rate_to_users.sql
# 5. Click Go
```

### 2. Backend Calculation Logic
The `host-payments.php` file includes an AJAX endpoint that calculates earnings:

**Endpoint:** `?action=calculate_earnings`

**Parameters:**
- `user_id` - The seller's ID
- `pay_period` - Format: "YYYY-MM-DD_YYYY-MM-DD" (start_end dates)

**Calculation Formula:**
```
Hours Earnings = SUM(hours_worked) × hourly_rate
Items Earnings = SUM(solds_quantity) × items_sold_rate
Total Earnings = Hours Earnings + Items Earnings
```

**Data Source:**
- Queries the `attendance` table for approved records within the pay period date range
- Uses `status = 'approved'` filter to only count verified attendance

**Response Example:**
```json
{
  "success": true,
  "total_hours": 3.0,
  "total_items": 23,
  "hourly_rate": "166.00",
  "total_earnings": 498.00,
  "breakdown_text": "Based on attendance records"
}
```

### 3. Frontend Auto-Fill Behavior

**When does it trigger?**
1. When admin selects a user from the dropdown (immediately after GCash info appears)
2. When a pay period filter is already selected (on page load)

**User Experience:**
```
1. Admin selects pay period filter → Form shows users who haven't been paid yet
2. Admin clicks "Show Form" button → Form expands
3. Admin selects a user from dropdown
   ↓
4. GCash information displays (number, name, QR code)
5. Amount field auto-fills with calculated earnings
6. Breakdown shows:
   - "23 items" badge
   - "3.0h worked" badge  
   - "@ ₱166/h" rate badge
   - Full description: "Based on attendance records"
```

**Visual Indicators:**
- **Loading State:** Spinner animation with "Calculating..." text
- **Success State:** Green background with breakdown stats
- **No Records:** "No attendance records found for this period"
- **Error State:** Red text showing error message

### 4. Amount Field Styling
The amount input is now **readonly** with special styling:
- Green background (#f0fdf4)
- Bold green text (#059669)
- Larger font size (1.1rem)
- Cannot be manually edited (calculated automatically)

## File Changes

### Modified Files:
1. **admin/host-payments.php**
   - Added AJAX endpoint for `calculate_earnings` action (lines 103-168)
   - Modified amount input to readonly with earnings breakdown UI (lines 572-585)
   - Added CSS for earnings display components (lines 1063-1130)
   - Added `calculateEarnings()` JavaScript function (lines 1755-1832)
   - Modified `updateUserInfo()` to trigger earnings calculation (lines 1755)
   - Added initialization on page load for pre-selected users

2. **sql/add_hourly_rate_to_users.sql** (NEW)
   - Migration file to add `hourly_rate` and `items_sold_rate` columns

## Testing Checklist

### Prerequisites:
- [ ] Run the SQL migration to add hourly_rate columns
- [ ] Update at least one user's hourly_rate (default is ₱166.00)
- [ ] Ensure attendance records exist with `status = 'approved'`

### Test Scenarios:

#### Scenario 1: Normal Flow
1. Go to Host Payments page
2. Select a pay period filter (e.g., "Nov 16-30, 2025")
3. Click "Show Form"
4. Select a user who has attendance records
5. **Expected Result:**
   - GCash info displays
   - Amount auto-fills (e.g., ₱498)
   - Breakdown shows stats: "23 items, 3.0h @ ₱166/h"

#### Scenario 2: No Attendance Records
1. Select pay period filter
2. Select user with NO approved attendance for that period
3. **Expected Result:**
   - Amount field stays empty or shows 0
   - Message: "No attendance records found for this period"

#### Scenario 3: Page Reload with User Selected
1. Select user and wait for earnings to load
2. Refresh the page (F5)
3. **Expected Result:**
   - Form resets (no resubmission dialog)
   - If pay period still selected, form shows with calculation ready

#### Scenario 4: Change User
1. Select User A → earnings calculate
2. Change dropdown to User B
3. **Expected Result:**
   - Loading spinner appears
   - New calculation for User B displays
   - Previous User A data is replaced

## Database Dependencies

### Required Tables:
1. **users**
   - `id` (Primary Key)
   - `hourly_rate` (NEW - DECIMAL(10,2))
   - `items_sold_rate` (NEW - DECIMAL(10,2))

2. **attendance**
   - `seller_id` (Foreign Key to users.id)
   - `date` (DATE)
   - `hours_worked` (DECIMAL)
   - `solds_quantity` (INT)
   - `status` (ENUM - must be 'approved')

3. **payment_receipts**
   - `seller_id` (Foreign Key)
   - `pay_period_start` (DATE)
   - `pay_period_end` (DATE)

### Sample Data for Testing:
```sql
-- Update a user's hourly rate
UPDATE users 
SET hourly_rate = 166.00, 
    items_sold_rate = 0.00 
WHERE id = 1;

-- Insert test attendance record
INSERT INTO attendance (seller_id, date, hours_worked, solds_quantity, status, created_at)
VALUES (1, '2025-01-20', 3.0, 23, 'approved', NOW());

-- Check existing attendance
SELECT 
    u.full_name,
    a.date,
    a.hours_worked,
    a.solds_quantity,
    a.status,
    (a.hours_worked * u.hourly_rate) as hours_earnings
FROM attendance a
JOIN users u ON a.seller_id = u.id
WHERE a.status = 'approved'
ORDER BY a.date DESC;
```

## Troubleshooting

### Issue: Amount doesn't auto-fill
**Check:**
1. Browser console for JavaScript errors
2. Network tab for failed AJAX request
3. Verify pay period is in correct format (YYYY-MM-DD_YYYY-MM-DD)
4. Check user has `hourly_rate` set in database
5. Verify attendance records exist with `status = 'approved'`

### Issue: Shows "No attendance records"
**Check:**
1. Attendance table has records for the user
2. Dates fall within the pay period range
3. Status is 'approved' (pending/rejected won't count)
4. seller_id matches user_id

### Issue: Calculation seems wrong
**Debug:**
```sql
-- Check user's rate
SELECT id, full_name, hourly_rate, items_sold_rate 
FROM users WHERE id = X;

-- Check attendance for pay period
SELECT 
    date,
    hours_worked,
    solds_quantity,
    status
FROM attendance 
WHERE seller_id = X 
  AND date BETWEEN 'YYYY-MM-DD' AND 'YYYY-MM-DD'
  AND status = 'approved';

-- Manual calculation
SELECT 
    SUM(hours_worked) as total_hours,
    SUM(solds_quantity) as total_items,
    SUM(hours_worked) * 166 as hours_earnings,
    SUM(solds_quantity) * 0 as items_earnings
FROM attendance 
WHERE seller_id = X 
  AND date BETWEEN 'YYYY-MM-DD' AND 'YYYY-MM-DD'
  AND status = 'approved';
```

## Future Enhancements

### Possible Improvements:
1. **Editable Override:** Allow admin to manually adjust calculated amount with confirmation
2. **Breakdown Details:** Link to view individual attendance records that were included
3. **Rate History:** Track changes to hourly_rate over time
4. **Bonus/Deduction:** Add fields to adjust final amount (e.g., +bonus, -advance payment)
5. **Multiple Rates:** Support different rates for different time periods or roles
6. **Attendance Preview:** Show attendance summary before calculating payment

## Security Notes

- Amount field is readonly to prevent tampering
- Calculation happens server-side (not client-side)
- SQL uses parameterized queries to prevent injection
- Only approved attendance records are counted
- Validates user exists and has gcash_number before calculating

## Performance Considerations

- Calculation is triggered only when needed (user selection change)
- Results are not cached (always fresh from database)
- Query uses indexes on `seller_id`, `date`, and `status` columns
- AJAX prevents full page reloads

---

**Last Updated:** January 2025
**Related Files:** 
- `admin/host-payments.php`
- `sql/add_hourly_rate_to_users.sql`
- `includes/database.php`
