# Overtime Form - Quick Reference Guide

## Modal Form Layout

```
╔════════════════════════════════════════════════════════════╗
║  ⏱️ Add Overtime                                      [×]  ║
║════════════════════════════════════════════════════════════║
║                                                            ║
║  📋 Your Attendance Shift:                               ║
║     [5:00 AM - 8:00 AM (3 Hour Shift)]                  ║
║                                                            ║
║  ┌─ Shift Duration (required) ─────────────────────────┐ ║
║  │ Your overtime is always 2 hours                      │ ║
║  │ [Select your attendance shift duration...]          │ ║
║  │  ├─ 3-Hour Shift (Attended)                         │ ║
║  │  └─ 4-Hour Shift (Attended)                         │ ║
║  └────────────────────────────────────────────────────┘  ║
║                                                            ║
║  ┌─ Overtime Time Slot (required) ─────────────────────┐ ║
║  │ First select your attendance duration...             │ ║
║  │ [First select duration...]                          │ ║
║  └────────────────────────────────────────────────────┘  ║
║  (Populates after duration selected)                      ║
║                                                            ║
║  ┌─ Total Solds ──────────────────────────────────────┐  ║
║  │ [Enter total solds during overtime...]              │  ║
║  └────────────────────────────────────────────────────┘  ║
║                                                            ║
║  ┌─ 📱 Overtime Proof Photo (required) ────────────────┐ ║
║  │ Upload photo showing your earnings/activity         │ ║
║  │                                                      │ ║
║  │        📷                                            │ ║
║  │   Upload your overtime                              │ ║
║  │   proof photo                                        │ ║
║  │   [Choose Photo]                                    │ ║
║  └────────────────────────────────────────────────────┘  ║
║                                                            ║
║════════════════════════════════════════════════════════════║
║              [Cancel]                  [✓ Proceed]        ║
╚════════════════════════════════════════════════════════════╝
```

## Time Slot Generation Example

### Scenario 1: 3-Hour Shift
```
Attendance: 5:00 AM - 8:00 AM (3 hours)
Attendance Ends: 8:00 AM

Generated 2-Hour Overtime Slots:
├─ 8:00 AM - 10:00 AM
├─ 10:00 AM - 12:00 PM
├─ 12:00 PM - 2:00 PM
├─ 2:00 PM - 4:00 PM
├─ 4:00 PM - 6:00 PM
├─ 6:00 PM - 8:00 PM
├─ 8:00 PM - 10:00 PM
├─ 10:00 PM - 12:00 AM (next day)
├─ 12:00 AM - 2:00 AM (next day)
└─ ... (up to 12 slots total)
```

### Scenario 2: 4-Hour Shift
```
Attendance: 2:00 PM - 6:00 PM (4 hours)
Attendance Ends: 6:00 PM

Generated 2-Hour Overtime Slots:
├─ 6:00 PM - 8:00 PM
├─ 8:00 PM - 10:00 PM
├─ 10:00 PM - 12:00 AM (next day)
├─ 12:00 AM - 2:00 AM (next day)
├─ 2:00 AM - 4:00 AM (next day)
├─ 4:00 AM - 6:00 AM (next day)
└─ ... (up to 12 slots total)
```

## Form Submission Flow

```
START: Approved Attendance Displayed
   ↓
USER: Clicks "Add Overtime" Button
   ↓
SYSTEM: 
  ├─ Fetches approved attendance info
  ├─ Displays modal
  └─ Resets form fields
   ↓
USER: Selects Duration (3-hour or 4-hour)
   ↓
SYSTEM:
  ├─ Makes AJAX request: get_overtime_slots
  ├─ Receives 2-hour slot options
  └─ Populates time slot dropdown
   ↓
USER: Selects Overtime Time Slot
   ↓
USER: (Optional) Enters Total Solds
   ↓
USER: Uploads Overtime Proof Photo
   ↓
SYSTEM: Shows photo preview
   ↓
USER: Clicks "Proceed" Button
   ↓
SYSTEM: Validates
  ├─ Duration selected? ✓
  ├─ Time slot selected? ✓
  └─ Photo uploaded? ✓
   ↓
SYSTEM: Submits form with action=add_overtime
   ↓
BACKEND: Processes add_overtime action
  ├─ Validates attendance exists
  ├─ Checks for duplicate overtime today
  ├─ Saves uploaded photo
  ├─ Inserts overtime record (status: pending_approval)
  └─ Displays success message
   ↓
END: Overtime submitted successfully
```

## Database Impact

### New Overtime Record Created
```sql
INSERT INTO overtime (
  seller_id,           -- Current user ID
  attendance_id,       -- Linked attendance record
  overtime_date,       -- Today's date
  duration_hours,      -- Always 2
  start_time,          -- From selected slot
  end_time,            -- From selected slot
  solds_quantity,      -- From form or 0
  overtime_photo,      -- File path
  status,              -- 'pending_approval'
  created_at,          -- Current timestamp
  updated_at           -- Current timestamp
)
```

## Error Messages

| Error | Trigger | Resolution |
|-------|---------|-----------|
| "No approved attendance found" | No approved attendance for today | Submit attendance first |
| "Please select your attendance shift duration" | Duration field empty | Choose 3-hour or 4-hour |
| "Please select an overtime time slot" | Time slot not selected | Choose from available slots |
| "Please upload your overtime proof photo before submitting" | Photo field empty | Select and upload image |
| "You have already submitted overtime for today" | Overtime already exists | Only one overtime per day |
| "Failed to save uploaded photo" | Upload failed | Try again with different file |
| "Invalid overtime slot format" | System error | Contact administrator |

## Styling Details

### Color Scheme
- **Primary**: #667eea (Purple-Blue)
- **Secondary**: #764ba2 (Purple)
- **Background**: #1a1a2e (Dark)
- **Text**: #ffffff (White)
- **Error**: #ff6b6b (Red)
- **Success**: #4ade80 (Green)

### Font Sizes
- Modal Title: 1.8rem
- Form Labels: 1rem (600 weight)
- Form Hints: 0.85rem (italic)
- Input Fields: 1rem

### Spacing
- Modal Padding: 2rem (header/body/footer)
- Form Groups: 2rem margin
- Input Fields: 1rem padding

## Browser Compatibility

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## Accessibility Features

- Semantic HTML structure
- ARIA labels on form controls
- Keyboard navigation support
- Focus states on interactive elements
- Color contrast compliance
- Mobile-friendly touch targets (min 44px)

## Performance Considerations

- Modal CSS embedded (no external file needed)
- AJAX requests minimize page reloads
- Image preview handled client-side
- File validation before upload
- Efficient DOM queries

## Security Features

1. **CSRF Protection**: Session-based token
2. **File Upload**: 
   - Type validation (image only)
   - Size limits enforced
   - Unique filename generation
3. **Database**: 
   - Prepared statements
   - Foreign key constraints
   - Input sanitization
4. **Authorization**: Role-based access control

## Mobile Responsiveness

- **Desktop**: Full-width form in modal (max 600px)
- **Tablet**: Adjusted padding and margins
- **Mobile**: 
  - Vertical button layout (flex column)
  - Full-width inputs
  - Larger touch targets
  - Optimized spacing

## Testing Scenarios

### Positive Test Cases
✅ User with approved 3-hour attendance can submit overtime
✅ User with approved 4-hour attendance can submit overtime
✅ 2-hour time slots generate correctly
✅ Photo upload and preview works
✅ Form submission creates pending overtime record
✅ Modal closes after successful submission

### Negative Test Cases
✅ Cannot submit without duration selection
✅ Cannot submit without time slot selection
✅ Cannot submit without photo upload
✅ Cannot submit duplicate overtime (same day)
✅ Cannot access without approved attendance
✅ Invalid file upload handled gracefully

### Edge Cases
✅ Overnight time slots display correctly
✅ Very early morning attendance (12 AM - 3 AM)
✅ Very late evening attendance (6 PM - 9 PM)
✅ Large number of generated slots (12 max)
✅ Form reset between multiple submissions
✅ Modal close on backdrop click
✅ Modal close on close button
✅ Modal close on cancel button

## Maintenance Notes

- Modal HTML is inline in schedule.php
- CSS is embedded in `<style>` tag before footer
- JavaScript functions defined in main `<script>` tag
- No external dependencies required
- Update time slot generation if overtime duration changes
- Modify color variables in CSS if theme changes
