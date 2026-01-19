# Overtime Form - Visual Walkthrough

## User Journey

### Step 1: View Approved Attendance
```
┌─────────────────────────────────────────────────────┐
│ ✅ Attendance Approved                              │
│                                                      │
│ Your Daily Attendance                                │
│ 5:00 AM - 8:00 AM (3 Hour Shift)                    │
│                                                      │
│ Solds: 150 units                                    │
│ Photo: Uploaded ✓                                   │
│ Status: Approved ✅                                 │
│                                                      │
│ ┌──────────────────────────────────────────────────┐
│ │ Overtime Status: Not Submitted                   │
│ │ You can now add overtime for today!              │
│ └──────────────────────────────────────────────────┘
│                                                      │
│ [← Return to Dashboard]    [Add Overtime ⏱️ →]      │
└─────────────────────────────────────────────────────┘
```

### Step 2: Click "Add Overtime" Button
```
User clicks "Add Overtime ⏱️" button
↓
Modal opens with smooth animation
```

### Step 3: Overtime Modal Opens
```
╔═════════════════════════════════════════════════════╗
║  ⏱️ Add Overtime                               [×]  ║
╠═════════════════════════════════════════════════════╣
║                                                     ║
║  📋 Your Attendance Shift:                         ║
║     ┌───────────────────────────────────────┐      ║
║     │ 5:00 AM - 8:00 AM (3 Hour Shift)    │      ║
║     └───────────────────────────────────────┘      ║
║                                                     ║
║  Shift Duration (required) *                       ║
║  Your overtime is always 2 hours                   ║
║  ┌───────────────────────────────────────────┐   ║
║  │ Select your attendance shift duration... │   ║
║  │ ▼                                         │   ║
║  │ ├─ 3-Hour Shift (Attended)              │   ║
║  │ └─ 4-Hour Shift (Attended)              │   ║
║  └───────────────────────────────────────────┘   ║
║                                                     ║
║  Overtime Time Slot (required) *                  ║
║  First select your attendance duration...         ║
║  ┌───────────────────────────────────────────┐   ║
║  │ First select duration...                  │   ║
║  │ ▼                                         │   ║
║  │ [Disabled - Select duration first]      │   ║
║  └───────────────────────────────────────────┘   ║
║                                                     ║
║  Total Solds                                      ║
║  ┌───────────────────────────────────────────┐   ║
║  │ Enter total solds during overtime...  [0]│   ║
║  └───────────────────────────────────────────┘   ║
║                                                     ║
║  📱 Overtime Proof Photo (required) *             ║
║  Upload photo showing your earnings/activity     ║
║  ┌───────────────────────────────────────────┐   ║
║  │           📷                               │   ║
║  │   Upload your overtime proof photo         │   ║
║  │         [Choose Photo]                     │   ║
║  └───────────────────────────────────────────┘   ║
║                                                     ║
╠═════════════════════════════════════════════════════╣
║              [Cancel]         [✓ Proceed]          ║
╚═════════════════════════════════════════════════════╝
```

### Step 4: Select Duration
```
User selects: "3-Hour Shift (Attended)"

↓ System makes AJAX request ↓

Result:
- Duration: 3 hours ✓
- Reference time: 5:00 AM - 8:00 AM ✓
- Overtime starts after: 8:00 AM ✓
```

### Step 5: Time Slots Populate
```
System receives 2-hour overtime slot options:

⏱️ Overtime Time Slot (required) *
First select your attendance duration...
┌─────────────────────────────────────────┐
│ Select a time slot...                   │
│ ▼                                       │
│ ├─ 8:00 AM - 10:00 AM                 │  ← Immediately after
│ ├─ 10:00 AM - 12:00 PM                │     attendance ends
│ ├─ 12:00 PM - 2:00 PM                 │
│ ├─ 2:00 PM - 4:00 PM                  │
│ ├─ 4:00 PM - 6:00 PM                  │
│ ├─ 6:00 PM - 8:00 PM                  │
│ ├─ 8:00 PM - 10:00 PM                 │
│ ├─ 10:00 PM - 12:00 AM (next day)     │  ← Overnight indicator
│ └─ [+ more options...]                 │
└─────────────────────────────────────────┘

All slots are exactly 2 hours
Starting from 8:00 AM (when attendance ends)
```

### Step 6: User Selects Time Slot
```
User selects: "2:00 PM - 4:00 PM"

Selection confirmed:
- Overtime starts: 2:00 PM ✓
- Overtime ends: 4:00 PM ✓
- Duration: 2 hours ✓
- Same day: Yes ✓
```

### Step 7: Enter Optional Details
```
User (optionally) enters:

Total Solds
┌─────────────────────────────────┐
│ 45                              │  ← User typed 45
└─────────────────────────────────┘

Note: This is optional, defaults to 0 if left blank
```

### Step 8: Upload Photo
```
User clicks on photo upload area
or
drags photo to upload box

Result:
┌──────────────────────────────┐
│    📷                         │
│  [Original placeholder]      │
│     Upload your overtime      │
│     proof photo               │
│    [Choose Photo]             │
└──────────────────────────────┘

↓ User selects image file ↓

┌──────────────────────────────┐
│  ┌──────────────────────────┐ │
│  │ [Photo Preview Image]    │ │
│  │ Shows uploaded image     │ │
│  │ [×] to remove           │ │
│  └──────────────────────────┘ │
└──────────────────────────────┘
```

### Step 9: Complete Form Review
```
╔═════════════════════════════════════════════════════╗
║  ⏱️ Add Overtime                               [×]  ║
╠═════════════════════════════════════════════════════╣
║                                                     ║
║  📋 Your Attendance Shift:                         ║
║     ┌───────────────────────────────────────┐      ║
║     │ 5:00 AM - 8:00 AM (3 Hour Shift)    │      ║
║     └───────────────────────────────────────┘      ║
║                                                     ║
║  Shift Duration (required) *                       ║
║  Your overtime is always 2 hours                   ║
║  ┌───────────────────────────────────────────┐   ║
║  │ 3-Hour Shift (Attended)              [✓] │   ║ ← Selected
║  └───────────────────────────────────────────┘   ║
║                                                     ║
║  Overtime Time Slot (required) *                  ║
║  Select a 2-hour time slot starting after your   ║
║  attendance (8:00 AM)                             ║
║  ┌───────────────────────────────────────────┐   ║
║  │ 2:00 PM - 4:00 PM                    [✓] │   ║ ← Selected
║  └───────────────────────────────────────────┘   ║
║                                                     ║
║  Total Solds                                      ║
║  ┌───────────────────────────────────────────┐   ║
║  │ 45                                       │   ║ ← Entered
║  └───────────────────────────────────────────┘   ║
║                                                     ║
║  📱 Overtime Proof Photo (required) *             ║
║  Upload photo showing your earnings/activity     ║
║  ┌───────────────────────────────────────────┐   ║
║  │ ┌─────────────────────────────────┐ [×]  │   ║
║  │ │ [Screenshot/Photo]              │      │   ║
║  │ │ Photo: screen_1234567890.jpg    │      │   ║ ← Uploaded
║  │ └─────────────────────────────────┘      │   ║
║  └───────────────────────────────────────────┘   ║
║                                                     ║
╠═════════════════════════════════════════════════════╣
║              [Cancel]         [✓ Proceed]          ║
╚═════════════════════════════════════════════════════╝

All required fields filled: ✓
Ready to submit!
```

### Step 10: Click "Proceed" Button
```
User clicks [✓ Proceed] button

↓ Form Validation ↓

Checks:
✓ Duration selected? YES
✓ Time slot selected? YES
✓ Photo uploaded? YES

Result: VALID ✓
Proceed with submission
```

### Step 11: Form Submission
```
System submits form with:

POST /tiktok-live-host/live-sellers/schedule.php

Form Data:
├─ action: "add_overtime"
├─ attendance_id: 12345
├─ duration: 3
├─ overtime_slot: "overtime_14:00:00_16:00:00"
├─ overtime_solds: 45
└─ overtime_photo: [file binary data]

↓ Server Processing ↓
```

### Step 12: Backend Processing
```
Server:
1. Validates attendance exists and is approved ✓
2. Checks no duplicate overtime today ✓
3. Saves uploaded photo ✓
   └─ Location: uploads/overtime/overtime_5_1699999999.jpg
4. Creates overtime record ✓
   └─ Database: overtime table
   └─ Status: pending_approval
5. Redirects back to page

Database Record Created:
├─ seller_id: 5
├─ attendance_id: 12345
├─ overtime_date: 2024-11-18
├─ duration_hours: 2
├─ start_time: 14:00:00 (2:00 PM)
├─ end_time: 16:00:00 (4:00 PM)
├─ solds_quantity: 45
├─ overtime_photo: "uploads/overtime/overtime_5_1699999999.jpg"
├─ status: "pending_approval"
└─ created_at: 2024-11-18 15:30:45
```

### Step 13: Success
```
┌─────────────────────────────────────────────────────┐
│ ✅ Overtime Submitted Successfully!                 │
│                                                      │
│ Your overtime has been submitted for admin approval.│
│ You will be notified once the admin reviews your    │
│ submission.                                          │
│                                                      │
│ Overtime Details:                                    │
│ • Date: November 18, 2024                          │
│ • Time: 2:00 PM - 4:00 PM (2 hours)               │
│ • Solds: 45 units                                  │
│ • Status: Pending Admin Approval ⏳                │
│                                                      │
│              [← Return to Dashboard]                │
└─────────────────────────────────────────────────────┘
```

## Form State Transitions

### Initial State
```
┌─────────────────┐
│ Modal Closed    │
└────────┬────────┘
         │ User clicks "Add Overtime"
         ↓
┌─────────────────────────────┐
│ Modal Open (Loading)        │
│ - Fetching attendance info  │
└────────┬────────────────────┘
         │ Data loaded
         ↓
┌─────────────────────────────┐
│ Modal Ready (Step 1)        │
│ - Duration dropdown only    │
│ - Time slot disabled        │
│ - Photo upload ready        │
└────────┬────────────────────┘
```

### After Duration Selection
```
┌─────────────────────────────┐
│ Modal Ready (Step 2)        │
│ - Duration selected ✓       │
│ - Time slot enabled ✓       │
│ - Options populated ✓       │
└────────┬────────────────────┘
```

### After Time Slot Selection
```
┌─────────────────────────────┐
│ Modal Ready (Step 3)        │
│ - Duration selected ✓       │
│ - Time slot selected ✓      │
│ - Photo upload ready        │
│ - Solds input ready         │
└────────┬────────────────────┘
```

### After Photo Upload
```
┌─────────────────────────────┐
│ Modal Ready (Submit)        │
│ - All required fields ✓     │
│ - Form valid ✓              │
│ - Proceed button enabled ✓  │
└────────┬────────────────────┘
         │ User clicks Proceed
         ↓
┌─────────────────────────────┐
│ Submitting...               │
│ - Validating form           │
│ - Uploading photo           │
│ - Creating record           │
└────────┬────────────────────┘
         │ Success
         ↓
┌─────────────────────────────┐
│ Success!                    │
│ - Record created            │
│ - Status: pending_approval  │
│ - Show success message      │
└─────────────────────────────┘
```

## Example Scenarios

### Scenario A: 3-Hour Morning Shift
```
Attendance:      5:00 AM - 8:00 AM
Attendance Ends: 8:00 AM

Overtime Options:
├─ 8:00 AM - 10:00 AM     ← Right after attendance
├─ 10:00 AM - 12:00 PM
├─ 12:00 PM - 2:00 PM
├─ 2:00 PM - 4:00 PM
└─ ... (more options)

User chooses: 8:00 AM - 10:00 AM
Result: Continuous work 5:00 AM - 10:00 AM (5 hours total)
```

### Scenario B: 4-Hour Evening Shift
```
Attendance:      2:00 PM - 6:00 PM
Attendance Ends: 6:00 PM

Overtime Options:
├─ 6:00 PM - 8:00 PM      ← Right after attendance
├─ 8:00 PM - 10:00 PM
├─ 10:00 PM - 12:00 AM (next day)
└─ ... (more options)

User chooses: 8:00 PM - 10:00 PM
Result: Overtime after 2-hour rest, 8:00 PM - 10:00 PM
```

### Scenario C: Overnight Shift
```
Attendance:      10:00 PM - 1:00 AM (next day) - 3 hours
Attendance Ends: 1:00 AM

Overtime Options:
├─ 1:00 AM - 3:00 AM
├─ 3:00 AM - 5:00 AM
├─ 5:00 AM - 7:00 AM
└─ ... (spans into next day)

User chooses: 5:00 AM - 7:00 AM
Result: Complete night and morning shift, work continues into new calendar day
```

## Validation Rules Visual

```
Form Submission Flowchart:

START
  │
  ├─ User clicks Proceed
  │
  ├─ Validate: Duration selected?
  │  └─ NO → Error: "Select duration" → STOP
  │  └─ YES ↓
  │
  ├─ Validate: Time slot selected?
  │  └─ NO → Error: "Select time slot" → STOP
  │  └─ YES ↓
  │
  ├─ Validate: Photo uploaded?
  │  └─ NO → Error: "Upload photo" → STOP
  │  └─ YES ↓
  │
  ├─ Submit form
  │
  ├─ Server validates:
  │  ├─ Attendance exists? ✓
  │  ├─ Attendance approved? ✓
  │  ├─ No duplicate today? ✓
  │  └─ Photo saved? ✓
  │
  └─ END: Success!
```

## Mobile Experience

### Compact Mobile View
```
┌──────────────────────────┐
│ ⏱️ Add Overtime      [×] │
├──────────────────────────┤
│                          │
│ 📋 Attendance:           │
│ 5:00 AM - 8:00 AM       │
│                          │
│ ⏳ Duration *           │
│ [Select duration...]    │
│                          │
│ ⏰ Time Slot *          │
│ [First select...]       │
│                          │
│ 📊 Solds                │
│ [Enter amount...]       │
│                          │
│ 📱 Photo *              │
│ [Upload...]             │
│                          │
│ ┌────────┬────────────┐  │
│ │Cancel  │   Proceed  │  │
│ └────────┴────────────┘  │
└──────────────────────────┘
```

---

This visual walkthrough shows the complete user experience of the overtime form system!
