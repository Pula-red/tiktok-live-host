# GCash QR Code Feature

## Overview
This feature allows live sellers to upload their GCash QR codes for payment processing, and admins can view all uploaded QR codes in one place.

## Database Setup

1. **Run the SQL migration** to create the `gcash_qr_codes` table:
   ```sql
   -- Execute this file in your database
   sql/add_gcash_qr_table.sql
   ```

   Or manually run:
   ```sql
   CREATE TABLE IF NOT EXISTS `gcash_qr_codes` (
       `id` INT NOT NULL AUTO_INCREMENT,
       `user_id` INT NOT NULL,
       `qr_code_image` VARCHAR(255) NOT NULL,
       `gcash_number` VARCHAR(15) DEFAULT NULL,
       `gcash_name` VARCHAR(100) DEFAULT NULL,
       `is_active` TINYINT(1) NOT NULL DEFAULT 1,
       `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
       `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       PRIMARY KEY (`id`),
       KEY `idx_user_id` (`user_id`),
       KEY `idx_active` (`is_active`),
       CONSTRAINT `fk_gcash_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```

2. **Ensure upload directory exists** and has proper permissions:
   ```
   uploads/gcash/
   ```
   The directory will be auto-created when a user first uploads, but you can manually create it:
   ```bash
   mkdir -p uploads/gcash
   chmod 755 uploads/gcash
   ```

## Features

### For Live Sellers (`live-sellers/gcash-qr.php`)
- Upload GCash QR code image
- Enter GCash number and account name
- View current uploaded QR code
- Update existing QR code
- Delete QR code
- Image preview before upload
- File validation (JPG, PNG, GIF, max 5MB)

### For Admins (`admin/gcash-qr-codes.php`)
- View all live sellers' GCash QR codes
- Statistics showing:
  - Total users
  - Users with QR codes
  - Users without QR codes
- Visual grid display of all QR codes
- Click to view full-size QR code in modal
- List of users who haven't uploaded QR codes yet
- Shows GCash number, account name, and upload date

## Navigation

### Live Seller Menu
- New menu item added: **Payment → GCash QR Code**

### Admin Menu
- New menu item added: **Payment → GCash QR Codes**

## File Structure
```
live-sellers/
  └── gcash-qr.php          # User upload page

admin/
  └── gcash-qr-codes.php    # Admin view page

sql/
  └── add_gcash_qr_table.sql # Database migration

uploads/
  └── gcash/                # QR code storage directory
```

## Usage Instructions

### For Live Sellers:
1. Login to live seller account
2. Navigate to **Payment → GCash QR Code**
3. Fill in:
   - GCash Number (e.g., 0917 123 4567)
   - Account Name (as registered in GCash)
   - Upload QR code image
4. Click "Upload QR Code"
5. QR code will be saved and visible to admins

### For Admins:
1. Login to admin account
2. Navigate to **Payment → GCash QR Codes**
3. View all users' QR codes in a grid
4. Click any QR code to view full size
5. See which users haven't uploaded yet

## Security Features
- File type validation (only images)
- File size limit (5MB max)
- Unique filename generation
- SQL injection protection via prepared statements
- Proper file permissions
- Soft delete (preserves data history)

## Updates and Modifications
- Users can update their QR code anytime
- Old QR code images are automatically deleted when updated
- Only one active QR code per user
- Admins have read-only access (no edit/delete permissions)

## Future Enhancements (Optional)
- Export QR codes to PDF
- Send reminders to users without QR codes
- QR code verification status
- Payment history linked to QR codes
- Bulk download option for admins
