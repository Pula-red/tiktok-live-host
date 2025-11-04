# Host Payments Feature

## Overview
This feature allows admins to upload payment receipts for live sellers who have registered their GCash QR codes. Admins can track all payments made to hosts with detailed records and receipt images.

## Database Setup

1. **Run the SQL migration** to create the `payment_receipts` table:
   ```sql
   -- Execute this file in your database
   source sql/add_payment_receipts_table.sql
   ```

   Or manually run:
   ```sql
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
   ```

2. **Ensure upload directory exists** and has proper permissions:
   ```
   uploads/payment_receipts/
   ```
   The directory will be auto-created when an admin first uploads a receipt.

## Features

### For Admins (`admin/host-payments.php`)
- **Upload Payment Receipts:**
  - Select user (only shows users with GCash QR codes)
  - Enter payment amount
  - Select payment date
  - Upload receipt image (screenshot of GCash transaction)
  - Add reference number (optional)
  - Add notes (optional)

- **Dashboard Statistics:**
  - Total number of payments
  - Total amount paid
  - Number of unique users paid

- **Payment History:**
  - View all payment records in a table
  - See payment date, user, amount, reference number
  - View receipt images in modal popup
  - Delete payment records
  - Track which admin made each payment

- **User Information:**
  - When selecting a user, displays their GCash number and name
  - Helps verify payment is sent to correct account

## Navigation

### Admin Menu
- New menu item added: **Payment → Host Payments** (placed before GCash QR Codes)

## File Structure
```
admin/
  └── host-payments.php         # Main payment upload page

sql/
  └── add_payment_receipts_table.sql  # Database migration

uploads/
  └── payment_receipts/         # Receipt storage directory
```

## Usage Instructions

### For Admins:

#### Uploading a Payment Receipt:
1. Login to admin account
2. Navigate to **Payment → Host Payments**
3. Click "Show Form" to expand the upload form
4. Fill in the details:
   - **Select User:** Choose the live seller from dropdown
   - **Amount:** Enter the payment amount (e.g., 5000.00)
   - **Payment Date:** Select when payment was made
   - **Reference Number:** Enter GCash reference (optional)
   - **Receipt Image:** Upload screenshot of GCash transaction
   - **Notes:** Add any additional information (optional)
5. Click "Upload Payment Receipt"
6. Receipt will be saved and displayed in payment history

#### Viewing Payment History:
1. Scroll down to "Payment History" section
2. View all payments in table format
3. Click "View Receipt" to see full-size image
4. Use delete button (🗑️) to remove records if needed

#### Statistics Dashboard:
- Top of page shows:
  - Total number of payments made
  - Total amount paid across all users
  - Number of unique users who received payments

## Security Features
- Admin-only access (requires admin role)
- File type validation (only images)
- File size limit (5MB max)
- Unique filename generation
- SQL injection protection via prepared statements
- Tracks which admin made each payment
- Soft delete capability

## Data Tracking
Each payment record includes:
- User who received payment
- Admin who made the payment
- Payment amount
- Payment date
- Receipt image
- Reference number
- Notes
- Timestamps (created, updated)
- Status (completed by default)

## Integration with GCash QR Codes
- Only users with active GCash QR codes appear in the user dropdown
- When selecting a user, their GCash number and account name are displayed
- This ensures payments are sent to the correct GCash account

## Benefits
1. **Accountability:** Track which admin made each payment
2. **Transparency:** Receipt images provide proof of payment
3. **Organization:** All payment records in one place
4. **History:** Complete payment history with dates and amounts
5. **Verification:** Reference numbers for tracking GCash transactions
6. **Reporting:** Statistics for total payments and amounts

## Future Enhancements (Optional)
- Export payment history to Excel/PDF
- Payment reminders/scheduling
- Batch payment upload
- Payment status tracking (pending, completed, failed)
- Payment reports by date range
- Email notifications to users when payment is made
- Integration with accounting systems
- Payment approval workflow
