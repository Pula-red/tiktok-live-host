# Overtime Feature - Setup Checklist

## Pre-Deployment Checklist

### ✅ Code Changes
- [x] `live-sellers/schedule.php` - Updated with overtime button and modal
- [x] `assets/css/live-seller.css` - Added overtime modal styles
- [x] `sql/add_overtime_table.sql` - Database migration created

### ✅ Documentation
- [x] `OVERTIME_FEATURE_README.md` - Complete feature documentation
- [x] `OVERTIME_IMPLEMENTATION_GUIDE.md` - Setup and testing guide
- [x] `OVERTIME_CHANGES_SUMMARY.md` - Detailed change summary
- [x] This checklist document

---

## Deployment Steps

### Step 1: Database Migration
- [ ] Back up your database before running migration
- [ ] Run the SQL migration:
  ```bash
  mysql -u root -p tiktok_live_host < sql/add_overtime_table.sql
  ```
  OR paste contents of `sql/add_overtime_table.sql` in phpMyAdmin/HeidiSQL
- [ ] Verify the `overtime` table was created:
  ```sql
  SHOW TABLES LIKE 'overtime';
  DESCRIBE overtime;
  ```

### Step 2: File Permissions
- [ ] Ensure `uploads/` directory is writable by web server
- [ ] The `uploads/overtime/` directory will be created automatically on first file upload
- [ ] Verify web server has write permissions on `live-sellers/` directory

### Step 3: Code Deployment
- [ ] Deploy updated `live-sellers/schedule.php`
- [ ] Deploy updated `assets/css/live-seller.css`
- [ ] Clear any CSS/JS caches (browser cache, CDN, etc.)

### Step 4: Testing
- [ ] Test as Live Seller user
- [ ] Navigate to Schedule page
- [ ] Submit daily attendance
- [ ] Verify "Add Overtime" button appears
- [ ] Click button and verify modal opens
- [ ] Test duration selection (3 and 4 hours)
- [ ] Verify time slots populate correctly
- [ ] Fill out form and submit overtime
- [ ] Verify overtime record is created in database

---

## Feature Verification Checklist

### User Interface
- [ ] "Add Overtime" button appears after attendance submission
- [ ] Modal opens when button is clicked
- [ ] Modal closes with X button
- [ ] Modal closes when clicking outside
- [ ] Duration dropdown functions correctly
- [ ] Time slot dropdown populates based on duration
- [ ] Photo upload preview works
- [ ] Remove photo button works
- [ ] Form validates before submission
- [ ] Error messages display correctly

### Database
- [ ] `overtime` table exists
- [ ] Table has all required columns
- [ ] Foreign key constraints are active
- [ ] Indexes are created

### Data
- [ ] Overtime records are inserted correctly
- [ ] User ID and attendance ID are saved
- [ ] Time slots are saved with correct times
- [ ] Photos are saved to correct directory
- [ ] Status is set to 'pending_approval'

### File Uploads
- [ ] Photos are saved to `uploads/overtime/`
- [ ] File naming follows convention: `overtime_[user_id]_[timestamp].[ext]`
- [ ] File paths are stored correctly in database
- [ ] Different users can upload photos without conflicts

### Edge Cases
- [ ] User can't submit overtime without attendance
- [ ] User can't submit without selecting duration
- [ ] User can't submit without selecting time slot
- [ ] User can't submit without photo
- [ ] Form handles network errors gracefully
- [ ] File upload size limits work (if configured)

---

## Admin Implementation Checklist

These items are needed to complete the overtime management system:

### Admin Interface Required
- [ ] Create admin page: `admin/overtime-approval.php`
- [ ] List all overtime submissions
- [ ] Filter by:
  - [ ] Date range
  - [ ] Seller
  - [ ] Status (pending/approved/rejected)
- [ ] View overtime details:
  - [ ] Seller information
  - [ ] Uploaded proof photo
  - [ ] Time slot details
  - [ ] Solds quantity
- [ ] Approval actions:
  - [ ] Approve button
  - [ ] Reject button with reason field
- [ ] View approval history:
  - [ ] Who approved/rejected
  - [ ] When it was approved/rejected
  - [ ] Rejection reason (if rejected)

### Admin Database Queries
- [ ] SELECT pending overtimes
- [ ] UPDATE overtime status to approved
- [ ] UPDATE overtime status to rejected
- [ ] Track approved_by and approved_at

### Admin Reports (Optional)
- [ ] Overtime by seller
- [ ] Overtime by date
- [ ] Approval rates
- [ ] Total overtime hours

---

## Troubleshooting Checklist

### Button Not Appearing
- [ ] Verify user is logged in as live_seller
- [ ] Verify attendance was submitted
- [ ] Check attendance status is 'approved' or 'pending_approval'
- [ ] Clear browser cache
- [ ] Check browser console for errors (F12)

### Modal Not Opening
- [ ] Check browser console for JavaScript errors
- [ ] Verify CSS file is loaded (should show in Network tab)
- [ ] Check that `openOvertimeModal()` function exists
- [ ] Verify modal HTML is present in page source

### Form Not Submitting
- [ ] Verify photo is selected
- [ ] Check browser console for validation errors
- [ ] Verify form action is set to 'add_overtime'
- [ ] Check server error logs for PHP errors
- [ ] Verify database connection is working

### Photo Upload Failing
- [ ] Check `uploads/` directory permissions
- [ ] Verify file is an image format
- [ ] Check file size is reasonable
- [ ] Verify `uploads/overtime/` can be created
- [ ] Check server error logs

### Database Issues
- [ ] Verify `overtime` table exists
- [ ] Check table structure matches SQL file
- [ ] Verify foreign keys are correct
- [ ] Check user IDs exist in `users` table
- [ ] Check attendance IDs exist in `attendance` table

---

## Rollback Plan

If issues occur, rollback by:

1. **Remove new code changes** - Revert to previous version of:
   - `live-sellers/schedule.php`
   - `assets/css/live-seller.css`

2. **Keep the database** (or drop if needed):
   ```sql
   DROP TABLE IF EXISTS overtime;
   ```

3. **Clear uploads** (optional):
   - Delete contents of `uploads/overtime/` directory

4. **Clear caches**:
   - Browser cache
   - Server-side caches
   - CDN caches (if applicable)

---

## Security Verification

- [ ] File upload validation is working
- [ ] Only image files are accepted
- [ ] File paths are sanitized
- [ ] User authentication is required
- [ ] Users can only submit their own overtime
- [ ] Database constraints are enforced
- [ ] No SQL injection vulnerabilities
- [ ] No file upload vulnerabilities

---

## Performance Verification

- [ ] Page load time is acceptable
- [ ] Modal opens smoothly (< 1 second)
- [ ] Form submission is fast (< 2 seconds)
- [ ] No memory leaks in JavaScript
- [ ] Database queries are optimized
- [ ] File uploads don't timeout

---

## Browser Compatibility Check

- [ ] Chrome (desktop)
- [ ] Firefox (desktop)
- [ ] Safari (desktop)
- [ ] Edge (desktop)
- [ ] Chrome Mobile (Android)
- [ ] Safari Mobile (iOS)

---

## Documentation Review

- [ ] All documentation files are present
- [ ] Instructions are clear and accurate
- [ ] Code examples are correct
- [ ] Database schema is documented
- [ ] File upload paths are documented
- [ ] Error messages are documented

---

## Post-Launch Checklist

After deploying to production:

- [ ] Monitor error logs for issues
- [ ] Test core functionality again
- [ ] Verify backups are working
- [ ] Check database performance
- [ ] Monitor file upload storage usage
- [ ] Gather user feedback
- [ ] Plan next features based on feedback

---

## Notes

**Deployment Date:** _______________

**Deployed By:** _______________

**Database Backup Location:** _______________

**Known Issues/Limitations:**
- Admin approval interface not yet implemented (planned for next update)
- Overtime payment calculations not yet configured
- No email notifications for approval (planned for future)

**Future Enhancements:**
- Bulk overtime management for admins
- Overtime payment calculations
- Email notifications
- Mobile app integration
- Overtime reports and analytics

---

## Sign-Off

- [ ] Testing completed successfully
- [ ] All checklist items verified
- [ ] Code review passed
- [ ] Ready for production deployment

**Tester Name:** _______________

**Date:** _______________

**Approved By:** _______________
