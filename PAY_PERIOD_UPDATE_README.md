# Pay Period Update - README

## Overview
This update adds **Pay Period** tracking to the payment system. Now when admins upload payment receipts, they can specify which cut-off period (1-15 or 16-end of month) the payment covers.

## Problem Solved
Previously, the History page showed ₱0.00 for salary because:
- Payments were recorded with `payment_date` (when admin uploaded the receipt)
- But the query searched for payments DURING the work period
- Since payments are uploaded AFTER the period ends, they weren't found

## Solution
Added two new fields to `payment_receipts` table:
- `pay_period_start` - Start date of the pay period (e.g., 2025-10-01)
- `pay_period_end` - End date of the pay period (e.g., 2025-10-15)

## Installation Steps

### Step 1: Run Database Migration
Execute the SQL file to add the new columns:

```bash
# Option 1: Using phpMyAdmin
1. Open phpMyAdmin
2. Select your database
3. Go to SQL tab
4. Copy and paste the content from: sql/add_pay_period_to_receipts.sql
5. Click "Go"

# Option 2: Using MySQL command line
mysql -u your_username -p your_database < sql/add_pay_period_to_receipts.sql
```

### Step 2: Test the Changes
1. Go to Admin Panel > Host Payments
2. Click "Show Form"
3. You'll see a new **"Pay Period (Cut-off)"** dropdown
4. Select a user and amount
5. Select the pay period this payment covers
6. Upload the receipt
7. Submit the form

### Step 3: Verify in History Page
1. Login as a Live Seller
2. Go to History page
3. Select a year and month
4. You should now see the correct salary amounts for periods where payments were made

## Changes Made

### Files Modified:
1. **admin/host-payments.php**
   - Added `generate_pay_periods()` function (generates last 6 months of pay periods)
   - Added pay period dropdown to the form
   - Updated form validation to require pay period
   - Modified INSERT query to include `pay_period_start` and `pay_period_end`

2. **live-sellers/history.php**
   - Updated salary query to use `pay_period_start` and `pay_period_end` instead of `payment_date BETWEEN`
   - Now correctly matches payments to their corresponding pay periods

3. **sql/add_pay_period_to_receipts.sql** (NEW)
   - Migration script to add new columns to database
   - Adds index for faster queries

## How It Works Now

### Admin Workflow:
1. Admin goes to Host Payments page
2. Selects a live seller
3. Enters payment amount
4. **NEW:** Selects which pay period this payment is for (e.g., "October 1-15, 2025")
5. Uploads receipt image
6. System saves payment with explicit period linkage

### Live Seller View:
1. Live seller goes to History page
2. Selects year and month
3. System displays two cards (1-15 and 16-end)
4. **NEW:** Each card shows correct salary by matching `pay_period_start` and `pay_period_end`
5. No more ₱0.00 issues!

## Database Schema

```sql
payment_receipts
├── id (Primary Key)
├── user_id
├── admin_id
├── amount
├── receipt_image
├── payment_date (when admin uploaded)
├── pay_period_start (NEW - which period this covers)
├── pay_period_end (NEW - which period this covers)
├── reference_number
├── notes
├── status
└── created_at
```

## Important Notes

### For Existing Data:
- Old payment records will have NULL values for `pay_period_start` and `pay_period_end`
- They won't show up in History page until you manually update them or re-upload
- If you need to migrate old data, you can manually UPDATE the records in phpMyAdmin

### Manual Data Migration Example:
```sql
-- If you know a payment was for October 1-15, 2025:
UPDATE payment_receipts 
SET pay_period_start = '2025-10-01', 
    pay_period_end = '2025-10-15'
WHERE id = 123;  -- Replace with actual payment ID
```

### Pay Period Generation:
- Dropdown shows last 6 months of pay periods (12 periods total)
- Automatically calculates the last day of each month (28/29/30/31)
- Format: "Month DD-DD, YYYY" (e.g., "October 1-15, 2025")

## Testing Checklist

- [ ] Database migration runs without errors
- [ ] Host Payments form shows "Pay Period" dropdown
- [ ] Pay Period dropdown has last 6 months (12 options)
- [ ] Form validation requires pay period selection
- [ ] Payment uploads successfully with period data
- [ ] History page shows correct salary amounts
- [ ] Old payments (without period) don't cause errors
- [ ] Both periods (1-15 and 16-end) work correctly

## Rollback Instructions

If you need to revert these changes:

```sql
-- Remove the columns
ALTER TABLE payment_receipts 
DROP COLUMN pay_period_start,
DROP COLUMN pay_period_end,
DROP INDEX idx_pay_period;
```

Then restore the old file versions from your backup.

## Support

If you encounter any issues:
1. Check that the SQL migration ran successfully
2. Verify the columns exist: `DESCRIBE payment_receipts;`
3. Check for PHP errors in your error log
4. Ensure at least one payment has been uploaded with the new period fields

## Future Enhancements

Possible improvements:
- Add bulk update tool for migrating old payment data
- Add period summary in payment list
- Export payments by pay period
- Add period filter to payment history
