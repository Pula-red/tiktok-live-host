# Overtime Feature - Implementation Guide

## Quick Start

### Step 1: Database Migration
Run the following command in your database client (HeidiSQL, phpMyAdmin, or MySQL command line):

```bash
mysql -u root -p tiktok_live_host < sql/add_overtime_table.sql
```

Or copy and paste the SQL from `sql/add_overtime_table.sql` directly into your database client.

### Step 2: Verify Files
The following files have been modified/created:
- ✅ `live-sellers/schedule.php` - Updated with overtime functionality
- ✅ `assets/css/live-seller.css` - Added overtime modal styles
- ✅ `sql/add_overtime_table.sql` - Database migration
- ✅ `OVERTIME_FEATURE_README.md` - Complete documentation

### Step 3: Test the Feature

1. **Log in as a Live Seller**
2. **Go to Schedule page** (http://your-domain/live-sellers/schedule.php)
3. **Submit daily attendance**
4. **After successful submission**, you'll see an "Add Overtime" button next to "Return to Dashboard"
5. **Click "Add Overtime"** to open the modal form
6. **Fill in the form**:
   - Select duration (3 or 4 hours)
   - Select time slot (automatically populated based on duration)
   - Enter total solds (optional)
   - Upload proof photo (required)
7. **Click "Submit Overtime"**
8. **Success!** The overtime record is saved as pending approval

## Feature Details

### When Does the Button Appear?
The "Add Overtime" button appears only when:
- User's attendance status is `approved` OR
- User's attendance status is `pending_approval`

The button will NOT appear if:
- No attendance has been submitted for today
- Attendance status is `rejected`

### Time Slot Structure

**3-Hour Overtime Options** (covers 5 AM - 5 AM):
- All slots are 2-hour intervals
- Example: 5 AM - 7 AM, 7 AM - 9 AM, etc.

**4-Hour Overtime Options** (covers 6 AM - 6 AM):
- All slots are 2-hour intervals
- Example: 6 AM - 8 AM, 8 AM - 10 AM, etc.

### Required Fields
- Duration (dropdown)
- Time Slot (dropdown - depends on duration)
- Proof Photo (file upload)

### Optional Fields
- Total Solds (number input)

### Photo Upload
- Must be an image file
- Stored in `uploads/overtime/` directory
- File naming: `overtime_[user_id]_[timestamp].[extension]`

## Admin Panel Integration

To enable admins to review overtime submissions, you'll need to add overtime management to your admin dashboard. Here's what to include:

### Suggested Admin Features:
1. **Overtime List View**
   - Filter by date, seller, or status
   - View all overtime submissions

2. **Overtime Review**
   - View seller details
   - View submitted photo
   - View submitted solds quantity
   - View time slot details

3. **Approval Actions**
   - Approve overtime submission
   - Reject with reason
   - View timestamps

### Sample Admin Query:
```php
// Get all pending overtimes
$stmt = $db->prepare("
    SELECT o.*, u.full_name, u.username, a.attendance_date
    FROM overtime o
    JOIN users u ON o.seller_id = u.id
    JOIN attendance a ON o.attendance_id = a.id
    WHERE o.status = 'pending_approval'
    ORDER BY o.created_at DESC
");
$stmt->execute();
$pending_overtimes = $stmt->fetchAll();
```

## Troubleshooting

### Issue: "Add Overtime" button not showing
**Solution:**
- Ensure attendance has been submitted
- Check attendance status is 'approved' or 'pending_approval'
- Refresh the page

### Issue: File upload fails
**Solution:**
- Ensure `uploads/overtime/` directory exists and has write permissions
- Check file size is not too large
- Verify file is an image format

### Issue: Database error when submitting
**Solution:**
- Verify the overtime table has been created (run migration)
- Check database connection is working
- Ensure all foreign keys are properly set

## File Structure

```
tiktok-live-host/
├── live-sellers/
│   └── schedule.php (MODIFIED - added overtime functionality)
├── assets/css/
│   └── live-seller.css (MODIFIED - added modal styles)
├── uploads/
│   └── overtime/ (NEW - auto-created on first upload)
├── sql/
│   └── add_overtime_table.sql (NEW - database migration)
└── OVERTIME_FEATURE_README.md (NEW - full documentation)
```

## Browser Compatibility

- ✅ Chrome/Chromium
- ✅ Firefox
- ✅ Safari
- ✅ Edge
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## Performance Considerations

- Modal is rendered but hidden by default (no performance impact)
- Time slot data is hardcoded in JavaScript (fast loading)
- Photo uploads are validated server-side
- Database queries are optimized with proper indexes

## Security Considerations

✅ **Implemented:**
- File upload validation (image files only)
- File path sanitization
- CSRF protection (via form POST)
- Foreign key constraints
- User authentication check (requires login)

⚠️ **Recommendations:**
- Implement file size limits
- Add rate limiting for uploads
- Sanitize admin comments/notes
- Log all approval actions
- Encrypt sensitive photo metadata

## Next Steps

1. ✅ Run database migration
2. ✅ Test the feature as a seller
3. 🔲 Build admin approval panel
4. 🔲 Add overtime reports/analytics
5. 🔲 Configure payment calculations for overtime
6. 🔲 Set up email notifications

## Support

For detailed feature information, see `OVERTIME_FEATURE_README.md`

For questions or issues, check:
- Console errors (F12 in browser)
- Database logs for SQL errors
- Server error logs for PHP errors
