# Overtime Form - Setup & Testing Guide

## Quick Setup

### Requirements
✅ Existing TikTok Live Host system running
✅ Database with `overtime` table created
✅ `uploads/overtime/` directory exists with write permissions
✅ Live seller user account for testing

### Steps to Activate

1. **Verify Database Table**
   ```sql
   SELECT * FROM overtime LIMIT 1;
   ```
   Should return the overtime table structure. If not, run:
   ```sql
   CREATE TABLE IF NOT EXISTS `overtime` (
       `id` INT NOT NULL AUTO_INCREMENT,
       `seller_id` INT NOT NULL,
       `attendance_id` INT NOT NULL,
       `overtime_date` date NOT NULL,
       `duration_hours` INT NOT NULL,
       `start_time` time NOT NULL,
       `end_time` time NOT NULL,
       `solds_quantity` INT DEFAULT 0,
       `overtime_photo` varchar(255) DEFAULT NULL,
       `status` enum('pending_approval','approved','rejected') NOT NULL DEFAULT 'pending_approval',
       `approved_by` INT DEFAULT NULL,
       `approved_at` timestamp NULL DEFAULT NULL,
       `rejection_reason` text DEFAULT NULL,
       `notes` text DEFAULT NULL,
       `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
       `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       PRIMARY KEY (`id`),
       KEY `idx_seller_id` (`seller_id`),
       KEY `idx_attendance_id` (`attendance_id`),
       KEY `idx_overtime_date` (`overtime_date`),
       KEY `idx_status` (`status`),
       FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
       FOREIGN KEY (`attendance_id`) REFERENCES `attendance`(`id`) ON DELETE CASCADE,
       FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```

2. **Verify Upload Directory**
   ```bash
   # Check if directory exists
   ls -la uploads/overtime/
   
   # If not, create it
   mkdir -p uploads/overtime/
   chmod 755 uploads/overtime/
   ```

3. **Verify Schedule File**
   - File should be at: `live-sellers/schedule.php`
   - Should contain overtime modal HTML
   - Should contain JavaScript functions
   - Should have CSS styling embedded

4. **Test the System**
   - See "Testing Procedures" section below

## Testing Procedures

### Test 1: Access the Form

**Objective**: Verify overtime modal appears when "Add Overtime" is clicked

**Steps**:
1. Login as a live seller
2. Go to `/live-sellers/schedule.php`
3. Submit an attendance record
4. Wait for admin to approve (or set status manually to 'approved' in database)
5. Return to schedule page
6. Click "Add Overtime" button
7. Modal should appear with smooth animation

**Expected Result**:
✅ Modal opens
✅ Attendance information displays correctly
✅ Duration dropdown is available
✅ Time slot dropdown is disabled (waiting for duration)
✅ All form fields are visible
✅ Photo upload area is ready

**If it fails**:
- Check browser console for JavaScript errors
- Verify `live-sellers/schedule.php` file is updated
- Check that attendance is actually approved status

---

### Test 2: Duration Selection

**Objective**: Verify time slots generate correctly when duration is selected

**Steps**:
1. Open overtime modal
2. Click Duration dropdown
3. Select "3-Hour Shift (Attended)"
4. Wait for AJAX request to complete
5. Check Time Slot dropdown

**Expected Result**:
✅ Duration dropdown shows options: "3-Hour Shift", "4-Hour Shift"
✅ Time Slot dropdown becomes enabled
✅ Time Slot dropdown populates with 2-hour options
✅ First option starts right after attendance ends
✅ Each slot is exactly 2 hours
✅ Up to 12 slots available
✅ Options format: "X:XX AM - X:XX PM"

**Example for 3-hour morning shift (5 AM - 8 AM)**:
- 8:00 AM - 10:00 AM
- 10:00 AM - 12:00 PM
- 12:00 PM - 2:00 PM
- etc.

**If slots don't appear**:
- Check browser console for AJAX errors
- Verify `get_overtime_slots` AJAX handler exists in schedule.php
- Check that attendance_id is being passed correctly

---

### Test 3: Time Slot Selection

**Objective**: Verify time slot selection works correctly

**Steps**:
1. Select duration (3 or 4 hours)
2. Wait for slots to populate
3. Click on "Overtime Time Slot" dropdown
4. Select a slot (e.g., "8:00 AM - 10:00 AM")

**Expected Result**:
✅ Selected slot appears in dropdown
✅ Hidden field `overtime_slot` is populated with value like "overtime_08:00:00_10:00:00"
✅ Selected slot is displayed

**If selection fails**:
- Check that `selectOvertimeSlot()` function exists
- Verify hidden field has correct ID: `overtime_slot`

---

### Test 4: Photo Upload

**Objective**: Verify photo upload and preview works

**Steps**:
1. Click on photo upload area
2. Select an image file from your computer
3. Verify preview appears
4. Try removing photo with × button
5. Upload another photo

**Expected Result**:
✅ File selection dialog opens
✅ After selecting, image preview appears
✅ Upload placeholder hides
✅ Remove button (×) appears
✅ Clicking × removes photo
✅ Can upload again

**If upload fails**:
- Check file is valid image format (jpg, png, gif, etc.)
- Verify `uploads/overtime/` directory has write permissions
- Check browser console for JavaScript errors

---

### Test 5: Form Validation

**Objective**: Verify form validates before submission

**Steps**:

**5a: Missing Duration**
1. Open overtime modal
2. Skip duration selection
3. Try to click "Proceed"
4. Should show error

**Expected**: Alert or error message "Please select duration"

**5b: Missing Time Slot**
1. Select duration (enables time slot dropdown)
2. Skip time slot selection
3. Try to click "Proceed"
4. Should show error

**Expected**: Alert or error message "Please select time slot"

**5c: Missing Photo**
1. Select duration and time slot
2. Skip photo upload
3. Click "Proceed"
4. Should show error

**Expected**: Error message in modal "Please upload your overtime proof photo"

**5d: All Fields Valid**
1. Select duration
2. Select time slot
3. Upload photo
4. Click "Proceed"
5. Should submit successfully

**Expected**: Form submits, database record created, success message displays

---

### Test 6: Database Record Creation

**Objective**: Verify overtime record is created correctly in database

**Steps**:
1. Complete full form submission
2. Check database directly

**SQL Command**:
```sql
SELECT * FROM overtime 
WHERE seller_id = [USER_ID] 
ORDER BY created_at DESC 
LIMIT 1;
```

**Expected Result**:
- Record exists in `overtime` table
- `seller_id`: matches logged-in user
- `attendance_id`: matches their attendance record
- `overtime_date`: today's date
- `duration_hours`: 2 (always)
- `start_time`: matches selected slot start
- `end_time`: matches selected slot end
- `solds_quantity`: value entered (or 0 if blank)
- `overtime_photo`: file path like "uploads/overtime/overtime_5_1699999999.jpg"
- `status`: "pending_approval"
- `created_at`: current timestamp

**If record not created**:
- Check database connection
- Verify `add_overtime` action handler exists
- Check for SQL errors in error log

---

### Test 7: Photo File Storage

**Objective**: Verify photo file is saved correctly

**Steps**:
1. Submit overtime form with photo
2. Check file system

**Command**:
```bash
ls -la uploads/overtime/
```

**Expected Result**:
- File exists like: `overtime_5_1699999999.jpg`
- File is readable image format
- File size matches original upload
- Permissions allow reading

**If file not found**:
- Check `uploads/overtime/` directory exists
- Verify directory has write permissions (755)
- Check PHP error logs for upload errors

---

### Test 8: Duplicate Prevention

**Objective**: Verify system prevents duplicate overtime same day

**Steps**:
1. Submit overtime for today successfully
2. Click "Add Overtime" again
3. Try to submit another overtime for same day

**Expected Result**:
❌ Error message: "You have already submitted overtime for today. Only 1 overtime per day is allowed."
❌ Form does not submit
❌ Modal remains open for correction

**If not prevented**:
- Check validation code in `add_overtime` handler
- Verify database query checks `overtime_date` and `status != 'rejected'`

---

### Test 9: Mobile Responsiveness

**Objective**: Verify form works on mobile devices

**Steps**:
1. Open on mobile browser or use browser dev tools (F12)
2. Set viewport to mobile size (375px width)
3. Open overtime modal
4. Test all form interactions
5. Try form submission

**Expected Result**:
✅ Modal is readable on small screen
✅ All buttons are touchable (44px+ minimum)
✅ Vertical layout on mobile (buttons stack)
✅ Inputs are properly sized
✅ Scrolling works if content exceeds viewport
✅ Form submission works on mobile

**If responsive issues**:
- Check CSS media queries in modal styling
- Verify button sizes meet accessibility standards
- Test with actual mobile device, not just emulator

---

### Test 10: Error Handling

**Objective**: Verify error messages are helpful and clear

**Test scenarios**:

**10a: File Upload Error**
1. Rename an image to .txt extension
2. Try to upload
3. Should show appropriate error

**Expected**: Error message about invalid file type

**10b: Large File**
1. Try to upload very large file (>10MB)
2. Should show error

**Expected**: Error message about file size

**10c: No Attendance**
1. New user with no attendance
2. Try to open overtime modal

**Expected**: Error message "No approved attendance found"

**10d: Network Error**
1. Disconnect internet while form loads
2. Try to select duration

**Expected**: Error message about network issue

---

## Browser Testing

### Test on Multiple Browsers

```
Browser          Version  Status
─────────────────────────────────
Chrome           Latest   ✅ Test
Firefox          Latest   ✅ Test
Safari           Latest   ✅ Test
Edge             Latest   ✅ Test
Mobile Chrome    Latest   ✅ Test
Mobile Safari    Latest   ✅ Test
```

### Browser Console Checks

Open browser console (F12) and verify:
- ✅ No JavaScript errors
- ✅ AJAX requests show in Network tab
- ✅ All functions are defined
- ✅ No console warnings

**Check console for**:
```javascript
// Should be defined:
openOvertimeModal()
closeOvertimeModal()
updateOvertimeSlots()
selectOvertimeSlot()
removeOvertimePhoto()
resetOvertimeForm()
```

---

## Performance Testing

### Page Load
- [ ] Schedule page loads in < 3 seconds
- [ ] Modal opens in < 1 second
- [ ] Time slots generate in < 2 seconds

### Form Submission
- [ ] Form submits in < 5 seconds
- [ ] Photo upload completes in reasonable time
- [ ] Success message displays promptly

---

## Security Testing

### CSRF Protection
- [ ] Form includes CSRF token
- [ ] Token is validated on submission
- [ ] Invalid token is rejected

### File Upload Security
- [ ] Only image files accepted
- [ ] File size limits enforced
- [ ] Filename sanitized (no path traversal)
- [ ] File stored outside web root

### Database Security
- [ ] SQL injection attempts blocked
- [ ] Invalid user IDs rejected
- [ ] Foreign key constraints enforced

### Authorization
- [ ] Non-logged-in users cannot access
- [ ] Non-live-seller users cannot access
- [ ] Users cannot submit others' overtime

---

## Troubleshooting Guide

| Issue | Solution |
|-------|----------|
| Modal won't open | Check browser console, verify attendance is approved |
| No time slots appear | Select duration first, check AJAX response |
| Photo won't upload | Check file format, verify directory permissions |
| Form won't submit | Ensure all required fields filled, check console |
| "Duplicate overtime" error | Only one overtime per day allowed, check database |
| File not saved | Verify `uploads/overtime/` exists and is writable |
| Database error | Check database connection, verify table structure |
| Page not found (404) | Verify file path is correct, check URL |

---

## Testing Checklist

```
[ ] Database table `overtime` exists
[ ] Directory `uploads/overtime/` exists and writable
[ ] File `live-sellers/schedule.php` is updated
[ ] Modal HTML is present in file
[ ] Modal CSS is present in file
[ ] Modal JavaScript is present in file
[ ] Can open modal from approved attendance
[ ] Duration selection works
[ ] Time slots generate correctly
[ ] Photo upload works
[ ] Form validation prevents incomplete submission
[ ] Form submission creates database record
[ ] Photo file is saved to disk
[ ] Success message displays
[ ] Duplicate overtime is prevented
[ ] Mobile responsiveness works
[ ] No browser console errors
[ ] CSRF protection in place
[ ] File upload security implemented
```

---

## Quick Test Script

If you need a quick test, use this SQL to set up test data:

```sql
-- Insert test user (if needed)
INSERT INTO users (username, email, password, role, status, full_name, hourly_rate) 
VALUES ('testseller', 'test@example.com', '[hashed_password]', 'live_seller', 'active', 'Test Seller', 250)
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id);

-- Get user ID
SET @user_id = LAST_INSERT_ID();

-- Insert test attendance
INSERT INTO attendance (seller_id, attendance_date, duration, time_slot, solds_quantity, total_sold_photo, status)
VALUES (@user_id, CURDATE(), '3-hour', 1, 100, 'uploads/attendance/test.jpg', 'approved');

SET @attendance_id = LAST_INSERT_ID();

-- Verify attendance was created
SELECT * FROM attendance WHERE id = @attendance_id;

-- Now you can test overtime form with this attendance record
```

---

## Support & Escalation

If tests fail:

1. **Check logs**:
   - PHP error log: `php_errors.log`
   - MySQL error log: Database connection errors
   - Browser console: JavaScript errors

2. **Verify configuration**:
   - Database connection working
   - Upload directory permissions
   - File integrity

3. **Review code**:
   - Check schedule.php for all functions
   - Verify AJAX endpoints exist
   - Check database queries

4. **Contact support**:
   - Include error messages
   - Provide browser and PHP versions
   - Share relevant code snippets

---

## Documentation References

For more details, see:
- `OVERTIME_FORM_IMPLEMENTATION.md` - Technical details
- `OVERTIME_FORM_QUICK_REFERENCE.md` - Visual guide
- `OVERTIME_FORM_VISUAL_WALKTHROUGH.md` - User experience
- `OVERTIME_FORM_SUMMARY.md` - Overview

---

## Sign-Off

- [ ] All tests passed
- [ ] Documentation reviewed
- [ ] System ready for production
- [ ] Date: _______________
- [ ] Tester: _______________

System is ready to deploy when all tests pass!
