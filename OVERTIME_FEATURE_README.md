# Overtime Feature Documentation

## Overview
The Overtime feature allows live sellers to submit overtime work after they've already submitted their daily attendance. This is a new addition to the schedule/attendance system that provides flexibility for workers to log additional hours.

## Database Setup

### New Table: `overtime`
A new table has been created to store overtime submissions. Run the migration:

```bash
mysql -u root -p tiktok_live_host < sql/add_overtime_table.sql
```

**Table Structure:**
- `id` - Primary key
- `seller_id` - Foreign key to users
- `attendance_id` - Link to the main attendance record for the day
- `overtime_date` - Date of overtime
- `duration_hours` - Duration of overtime (3 or 4 hours)
- `start_time` - Start time of overtime slot
- `end_time` - End time of overtime slot
- `solds_quantity` - Number of items sold during overtime
- `overtime_photo` - Path to proof photo upload
- `status` - Status (pending_approval, approved, rejected)
- `approved_by` - Admin user ID who approved
- `approved_at` - Timestamp of approval
- `rejection_reason` - Reason if rejected
- `notes` - Additional notes
- `created_at`, `updated_at` - Timestamps

## Features

### 1. Add Overtime Button
- Only visible when a user has submitted attendance for the day (status: approved or pending_approval)
- Located in the schedule page alongside the "Return to Dashboard" button
- Styled with an hourglass icon (⏱️) for clear identification

### 2. Duration Selection
Users can select one of two duration options:
- **3 Hours**: Available times are from 5 AM to 5 AM the next day
- **4 Hours**: Available times are from 6 AM to 6 AM the next day

### 3. Time Slot Selection
Based on the selected duration, users get a dynamic list of 2-hour interval slots:

**For 3-Hour Overtime:**
- 5:00 AM - 7:00 AM
- 7:00 AM - 9:00 AM
- 9:00 AM - 11:00 AM
- 11:00 AM - 1:00 PM
- 1:00 PM - 3:00 PM
- 3:00 PM - 5:00 PM
- 5:00 PM - 7:00 PM
- 7:00 PM - 9:00 PM
- 9:00 PM - 11:00 PM
- 11:00 PM - 1:00 AM
- 1:00 AM - 3:00 AM
- 3:00 AM - 5:00 AM

**For 4-Hour Overtime:**
- 6:00 AM - 8:00 AM
- 8:00 AM - 10:00 AM
- 10:00 AM - 12:00 PM
- 12:00 PM - 2:00 PM
- 2:00 PM - 4:00 PM
- 4:00 PM - 6:00 PM
- 6:00 PM - 8:00 PM
- 8:00 PM - 10:00 PM
- 10:00 PM - 12:00 AM
- 12:00 AM - 2:00 AM
- 2:00 AM - 4:00 AM
- 4:00 AM - 6:00 AM

### 4. Total Solds Field
- Optional field to record the number of items sold during overtime
- Accepts positive integers

### 5. Photo Upload
- **Required field** - Users must upload proof of their overtime work
- Supports image formats (jpg, jpeg, png, gif, etc.)
- Photos are stored in `uploads/overtime/` directory
- File naming convention: `overtime_[user_id]_[timestamp].[ext]`

### 6. Modal Form
- Beautiful modal popup with smooth animations
- Overlay background prevents interaction with page while modal is open
- Close button (×) in top-right corner
- Cancel and Submit buttons at the bottom
- Responsive design for mobile and tablet devices

## File Changes

### Modified Files:
1. **live-sellers/schedule.php**
   - Added "Add Overtime" button that appears after attendance submission
   - Added overtime modal HTML with form fields
   - Added PHP backend logic to handle `add_overtime` action
   - Added comprehensive JavaScript for modal management and form validation
   - Added time slot data structures for 3-hour and 4-hour overtime slots

2. **assets/css/live-seller.css**
   - Added complete overtime modal styling
   - Added responsive design for all screen sizes
   - Added animations for modal appearance and form interactions
   - Added button styling for overtime-specific buttons

### Created Files:
1. **sql/add_overtime_table.sql**
   - Database migration script for creating the overtime table

## User Workflow

1. **User submits daily attendance** on the schedule page
2. **After submission**, if attendance is approved or pending approval, the "Add Overtime" button appears
3. **User clicks "Add Overtime"** button
4. **Modal form opens** with the following steps:
   - Select duration (3 or 4 hours)
   - Select time slot (populated based on duration)
   - Enter total solds (optional)
   - Upload proof photo (required)
5. **User submits the form**
6. **Overtime record is created** with status `pending_approval`
7. **Admin reviews and approves/rejects** the overtime (handled in admin panel)

## File Upload Details

### Photo Upload Path:
`uploads/overtime/overtime_[user_id]_[timestamp].[extension]`

Example:
```
uploads/overtime/overtime_5_1759811824.jpg
```

The file path is stored in the database `overtime_photo` column.

## Validation

### Server-Side Validation:
1. Attendance record must exist for the user on that day
2. Duration must be either 3 or 4
3. Time slot must be valid for the selected duration
4. Photo file must be uploaded successfully
5. Only image files are accepted

### Client-Side Validation:
1. All required fields must be filled
2. Photo must be selected before submission
3. Time slot dropdown is disabled until duration is selected

## Status Workflow

Overtime submissions follow this workflow:

1. **pending_approval** - Initial state when submitted
2. **approved** - Admin has reviewed and approved
3. **rejected** - Admin has reviewed and rejected with reason

## Admin Review

Admins can review overtime submissions in the admin panel (implementation depends on your admin interface). They can:
- View all overtime submissions
- Approve or reject submissions
- Add notes or rejection reasons
- View seller information and proof photos

## Error Handling

The system includes comprehensive error handling for:
- Missing attendance record
- Invalid duration or time slot selection
- File upload failures
- Database errors

Error messages are displayed to users in the modal form.

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile responsive (tested on iOS and Android)
- Fallback for older browsers (graceful degradation)

## Future Enhancements

Potential improvements for future versions:
- Bulk overtime submissions
- Overtime duration limits per day
- Admin dashboard for overtime management
- Overtime payment calculations
- Mobile app integration
- Email notifications for approval/rejection
