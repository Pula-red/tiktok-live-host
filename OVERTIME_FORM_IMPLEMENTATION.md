# Overtime Form Implementation Guide

## Overview
A new interactive overtime form modal has been implemented that allows users to add overtime shifts after their approved attendance. The form intelligently generates 2-hour time slot options based on the user's approved attendance end time.

## Features Implemented

### 1. **Overtime Modal Dialog**
- Modern popup form that appears when "Add Overtime" button is clicked
- Displays user's current attendance shift information
- Fully responsive design for desktop and mobile devices
- Smooth animations and backdrop blur effect

### 2. **Dynamic Time Slot Generation**
- **Time Slot Duration**: Fixed 2-hour overtime shifts
- **Smart Scheduling**: Time slots start immediately after the user's approved attendance ends
- **Example Workflow**:
  - User attends 5:00 AM - 8:00 AM shift (3 hours)
  - Overtime slot options: 8:00 AM - 10:00 AM, 10:00 AM - 12:00 PM, 12:00 PM - 2:00 PM, etc.
  - System generates up to 12 potential 2-hour overtime slots

### 3. **Form Fields**

#### Attendance Info Section
- **Display**: Shows the user's current attendance shift details
- **Read-only**: Information is fetched from the database and displayed for reference

#### Duration Selection
- **Label**: "Shift Duration"
- **Options**: 
  - 3-Hour Shift (Attended)
  - 4-Hour Shift (Attended)
- **Purpose**: Determines which overtime slots are available
- **Behavior**: Dynamically populates time slot options when changed

#### Time Slot Selection
- **Label**: "Overtime Time Slot (2 Hours)"
- **Dynamic Options**: Populated based on:
  - Attendance end time from approved attendance
  - Duration selected (3-hour or 4-hour shift)
- **Display Format**: "X:XX AM - X:XX PM" with overnight day indicator if applicable
- **Default**: Disabled until duration is selected

#### Total Solds Input
- **Type**: Number input
- **Optional**: Users can leave blank or enter their sales count
- **Placeholder**: "Enter total solds during overtime"

#### Photo Upload
- **Required**: Yes (form cannot be submitted without photo)
- **Type**: Image file upload
- **Purpose**: Proof of overtime work
- **Features**:
  - Drag-and-drop or click to select
  - Image preview with remove option
  - Validation on form submission

### 4. **Form Actions**

#### Submit Flow
1. User clicks "Add Overtime" button on attendance approval page
2. Modal opens with attendance information pre-filled
3. User selects their shift duration (3-hour or 4-hour)
4. Time slot options populate automatically
5. User selects desired overtime time slot
6. User enters total solds (optional)
7. User uploads overtime proof photo (required)
8. User clicks "Proceed" button
9. Form validates all required fields
10. Data submitted to backend with `action=add_overtime`

#### Validation Rules
- **Duration**: Must be selected (3 or 4 hours)
- **Time Slot**: Must be selected
- **Photo**: Must be uploaded before submission
- **Solds**: Optional, defaults to 0 if not provided

### 5. **Backend Processing**

#### AJAX Request: `get_attendance_endtime`
- **Purpose**: Retrieves user's approved attendance information
- **Returns**: 
  ```json
  {
    "success": true,
    "attendance_id": 123,
    "end_time": "08:00:00",
    "slot_display": "5:00 AM - 8:00 AM (3 Hour Shift)",
    "duration_hours": 3
  }
  ```

#### AJAX Request: `get_overtime_slots`
- **Purpose**: Generates 2-hour time slot options based on attendance end time
- **Parameters**: 
  - `duration`: 3 or 4 (attendance duration)
- **Returns**:
  ```json
  {
    "success": true,
    "slots": [
      {
        "value": "overtime_08:00:00_10:00:00",
        "text": "8:00 AM - 10:00 AM",
        "start_time": "08:00:00",
        "end_time": "10:00:00"
      },
      ...
    ],
    "attendance_end": "8:00 AM",
    "attendance_name": "5:00 AM - 8:00 AM"
  }
  ```

#### Form Submission: `add_overtime`
- **Method**: POST with multipart/form-data
- **Fields**:
  - `action`: "add_overtime"
  - `attendance_id`: ID of approved attendance
  - `duration`: 3 or 4 (from selected shift)
  - `overtime_slot`: Selected time slot value
  - `overtime_solds`: Total solds during overtime
  - `overtime_photo`: Uploaded image file

#### Database Operation
- **Table**: `overtime`
- **Status**: `pending_approval` (default)
- **Fields Populated**:
  - `seller_id`: Current user ID
  - `attendance_id`: Linked attendance record
  - `overtime_date`: Today's date
  - `duration_hours`: 2 (fixed)
  - `start_time`: From selected slot
  - `end_time`: From selected slot
  - `solds_quantity`: From form input
  - `overtime_photo`: Uploaded file path
  - `created_at`: Current timestamp

### 6. **User Experience Flow**

```
Approved Attendance
        ↓
   [Add Overtime Button]
        ↓
   Modal Opens
   - Shows attendance shift
   - Displays info message about 2-hour slots
        ↓
   [Select Duration]
   - 3-hour or 4-hour
        ↓
   [Select Time Slot]
   - Auto-populated with 2-hour options
   - Starting from attendance end time
        ↓
   [Enter Solds (Optional)]
        ↓
   [Upload Overtime Photo (Required)]
   - Preview before submit
        ↓
   [Proceed Button]
        ↓
   Form Validation
        ↓
   Success Message
   Overtime submitted for admin approval
```

## Technical Details

### File Modifications
- **File**: `live-sellers/schedule.php`
- **Changes**:
  - Added overtime modal HTML structure
  - Added modal styling with CSS
  - Added/updated JavaScript functions for:
    - `openOvertimeModal()`: Fetches attendance info and opens modal
    - `updateOvertimeSlots()`: Generates 2-hour slots based on duration
    - `selectOvertimeSlot()`: Updates hidden form field with selected slot
    - `closeOvertimeModal()`: Closes modal and resets form
    - `removeOvertimePhoto()`: Removes uploaded photo
    - `resetOvertimeForm()`: Clears all form fields

### Styling Features
- **Color Scheme**: Matches existing admin panel (purple-blue gradient)
- **Animations**: 
  - Fade-in backdrop
  - Slide-up modal entrance
  - Smooth transitions on all interactive elements
- **Responsive Design**: Adapts to mobile and desktop screens
- **Dark Theme**: Consistent with application's dark UI

### Time Slot Logic
```php
// Attendance ends at: 08:00:00
// Generate 2-hour slots:
// Slot 1: 08:00:00 - 10:00:00
// Slot 2: 10:00:00 - 12:00:00
// Slot 3: 12:00:00 - 14:00:00
// ... (up to 12 slots = 24 hours)
```

## Security Considerations

1. **CSRF Protection**: Form includes CSRF token via session
2. **File Upload Validation**: 
   - Image file type check
   - File size limits enforced
   - Stored in `uploads/overtime/` directory
3. **Database Constraints**:
   - Foreign key validation
   - One overtime per day limit per user
   - Enum status field for approved/rejected/pending
4. **Input Sanitization**: All user inputs sanitized before database insertion
5. **Authorization**: Only `live_seller` role can access overtime form

## Error Handling

### Validation Errors
- **No approved attendance**: "No approved attendance found"
- **Missing duration**: "Please select your attendance shift duration"
- **Missing time slot**: "Please select an overtime time slot"
- **Missing photo**: "Please upload your overtime proof photo before submitting"
- **Duplicate overtime**: "You have already submitted overtime for today. Only 1 overtime per day is allowed."

### File Upload Errors
- **No file uploaded**: Validation error shown in modal
- **Upload failed**: "Failed to save uploaded photo. Please try again."

## Testing Checklist

- [ ] Modal opens when "Add Overtime" button clicked
- [ ] Attendance shift info displays correctly
- [ ] Duration dropdown works and filters slots
- [ ] Time slots generate correctly based on attendance end time
- [ ] All slots are 2 hours duration
- [ ] Time slot options include day boundary indicator (next day)
- [ ] Photo upload preview works
- [ ] Photo can be removed
- [ ] Form validation prevents submission without photo
- [ ] Form submission works and creates overtime record
- [ ] Success message displays after submission
- [ ] Modal closes on cancel
- [ ] Modal closes on outside click
- [ ] Responsive design works on mobile
- [ ] Error messages display appropriately

## Future Enhancements

1. **Recurring Overtime**: Allow scheduling multiple overtime shifts
2. **Overtime Limits**: Set maximum overtime hours per week/month
3. **Pre-approved Slots**: Admin can set available overtime slots
4. **Notifications**: Email/SMS notification when overtime approved
5. **Analytics**: Track overtime trends by user/date
6. **Bulk Upload**: Allow uploading multiple overtime records at once

## Notes

- Overtime is always **2 hours fixed** duration
- Overtime must be **same day** as approved attendance
- Overtime **cannot start during** approved attendance period
- Overtime **starts immediately after** attendance ends
- Only **one overtime per day** is allowed
- Overtime requires **proof photo** (mandatory)
- Overtime status is **pending_approval** by default
- Admin must approve overtime before it's officially recorded
