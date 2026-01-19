# Overtime Feature - Visual Guide & User Experience

## User Interface Flow

### Step 1: Schedule Page (Initial State)
```
┌─────────────────────────────────────────────────────────────────┐
│  TikTok Live Host - Schedule & Attendance                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  [Schedule Form]                                                  │
│  - Date: Today (read-only)                                        │
│  - Duration: [3 Hours ▼]                                          │
│  - Time Slot: [First select duration...]                          │
│  - Total Solds: [___________]                                     │
│  - Upload Photo: [Choose Photo]                                   │
│                                                                   │
│  [Submit] Button                                                  │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Step 2: After Attendance Submission
```
┌─────────────────────────────────────────────────────────────────┐
│  TikTok Live Host - Schedule & Attendance                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ✅ Attendance Approved                                           │
│     Your attendance is confirmed!                                │
│     Submitted on: November 18, 2024                              │
│                                                                   │
│     Next submission available: Tomorrow at 5:00 AM               │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ [🏠 Return to Dashboard] [⏱️ Add Overtime]              │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Step 3: Click "Add Overtime" - Modal Opens
```
┌─────────────────────────────────────────────────────────────────┐
│                    MODAL OVERLAY (Dark)                          │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  Add Overtime                                          [×]  │  │
│  ├───────────────────────────────────────────────────────────┤  │
│  │                                                             │  │
│  │  Date:                                                      │  │
│  │  📅 Wednesday, November 18, 2024                            │  │
│  │                                                             │  │
│  │  Duration: *                                                │  │
│  │  [Select duration... ▼]                                     │  │
│  │  Choose your overtime duration                              │  │
│  │                                                             │  │
│  │  Time Slot: *                                               │  │
│  │  [First select duration... ▼]                               │  │
│  │  Available time slots for your selected duration             │  │
│  │                                                             │  │
│  │  Total Solds:                                               │  │
│  │  [_________________]                                        │  │
│  │                                                             │  │
│  │  📱 Proof Photo:                                             │  │
│  │  ┌─────────────────────────────────────────────────────┐   │  │
│  │  │  📷  Upload your overtime proof photo  [Choose]     │   │  │
│  │  └─────────────────────────────────────────────────────┘   │  │
│  │                                                             │  │
│  │  ┌──────────────┬─────────────────────────────────────┐   │  │
│  │  │   Cancel     │   ⏱️ Submit Overtime                │   │  │
│  │  └──────────────┴─────────────────────────────────────┘   │  │
│  │                                                             │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Step 4: Select Duration (3 Hours)
```
┌───────────────────────────────────────────────────────────────┐
│  Duration: *                                                   │
│  [3 Hours (5 AM - 5 AM) ▼]                                    │
│                                                                │
│  Time Slot: *                                                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │ 5:00 AM - 7:00 AM                                    ▼    │
│  ├──────────────────────────────────────────────────────┤    │
│  │ 5:00 AM - 7:00 AM                                        │
│  │ 7:00 AM - 9:00 AM                                        │
│  │ 9:00 AM - 11:00 AM                                       │
│  │ 11:00 AM - 1:00 PM                                       │
│  │ 1:00 PM - 3:00 PM                                        │
│  │ 3:00 PM - 5:00 PM                                        │
│  │ 5:00 PM - 7:00 PM                                        │
│  │ 7:00 PM - 9:00 PM                                        │
│  │ 9:00 PM - 11:00 PM                                       │
│  │ 11:00 PM - 1:00 AM                                       │
│  │ 1:00 AM - 3:00 AM                                        │
│  │ 3:00 AM - 5:00 AM                                        │
│  └──────────────────────────────────────────────────────┘    │
└───────────────────────────────────────────────────────────────┘
```

### Step 5: Select Time Slot
```
┌───────────────────────────────────────────────────────────────┐
│  Time Slot: *                                                  │
│  [9:00 AM - 11:00 AM ▼]                                       │
│  Available time slots for your selected duration                │
│                                                                │
│  Total Solds:                                                  │
│  [25]                                                          │
│                                                                │
│  📱 Proof Photo:                                                │
│  ┌───────────────────────────────────────────────────────┐    │
│  │                    [Proof Image]                      │    │
│  │  ┌─────────────────────────────────────────────────┐  │    │
│  │  │  [Image preview]                              [×] │  │    │
│  │  └─────────────────────────────────────────────────┘  │    │
│  └───────────────────────────────────────────────────────┘    │
│                                                                │
│  ┌──────────────┬─────────────────────────────────────┐      │
│  │   Cancel     │   ⏱️ Submit Overtime                │      │
│  └──────────────┴─────────────────────────────────────┘      │
└───────────────────────────────────────────────────────────────┘
```

### Step 6: Form Complete - Ready to Submit
```
All fields filled:
✓ Duration: 3 Hours
✓ Time Slot: 9:00 AM - 11:00 AM
✓ Total Solds: 25
✓ Photo: Uploaded (preview showing)

Ready to submit!
```

### Step 7: Submit and Success
```
After clicking Submit button:

1. Form submits to server
2. Validation checks:
   - Attendance exists ✓
   - Duration valid ✓
   - Time slot valid ✓
   - Photo uploaded ✓

3. Database insert:
   - INSERT INTO overtime
   - seller_id, attendance_id, times, solds, photo
   - status = 'pending_approval'

4. Success message:
   "Overtime submitted successfully! 
    It's pending admin approval."

5. Page refreshes or modal closes
```

---

## Time Slot Coverage

### 3-Hour Overtime (5 AM to 5 AM cycle)
Every day covers 24 hours in 2-hour slots:

```
5 AM ├─── 5-7 AM ───┤
     ├─── 7-9 AM ───┤
     ├─ 9-11 AM ────┤
     ├─ 11-1 PM ────┤
     ├─── 1-3 PM ───┤
     ├─── 3-5 PM ───┤
     ├─── 5-7 PM ───┤
     ├─── 7-9 PM ───┤
     ├─ 9-11 PM ────┤
     ├─ 11-1 AM ────┤
     ├─── 1-3 AM ───┤
     ├─── 3-5 AM ───┤
5 AM └──────────────┘
```

**Total slots: 12**

### 4-Hour Overtime (6 AM to 6 AM cycle)
Every day covers 24 hours in 2-hour slots:

```
6 AM ├─── 6-8 AM ───┤
     ├─ 8-10 AM ────┤
     ├─10-12 PM ────┤
     ├─12-2 PM ─────┤
     ├─── 2-4 PM ───┤
     ├─── 4-6 PM ───┤
     ├─── 6-8 PM ───┤
     ├─ 8-10 PM ────┤
     ├─10-12 AM ────┤
     ├─12-2 AM ─────┤
     ├─── 2-4 AM ───┤
     ├─── 4-6 AM ───┤
6 AM └──────────────┘
```

**Total slots: 12**

---

## Button State Transitions

```
┌─────────────────────────────────────────┐
│  Before Attendance Submission            │
├─────────────────────────────────────────┤
│                                          │
│  [Submit] Attendance form                │
│                                          │
│  "Add Overtime" button: NOT VISIBLE      │
│                                          │
└─────────────────────────────────────────┘
                    ↓ (After submission)
┌─────────────────────────────────────────┐
│  After Attendance Submission             │
├─────────────────────────────────────────┤
│  Status: ✅ Approved (or ⏳ Pending)     │
│                                          │
│  ┌─────────────────────────────────────┐│
│  │ [🏠 Dashboard] [⏱️ Add Overtime]    ││
│  └─────────────────────────────────────┘│
│                                          │
│  "Add Overtime" button: VISIBLE          │
│                                          │
└─────────────────────────────────────────┘
                    ↓ (Click Add Overtime)
┌─────────────────────────────────────────┐
│  Modal Opens                             │
├─────────────────────────────────────────┤
│                                          │
│  ┌─────────────────────────────────┐   │
│  │  Add Overtime Modal Form         │   │
│  │  - Duration selector             │   │
│  │  - Time slot selector            │   │
│  │  - Solds field                   │   │
│  │  - Photo upload                  │   │
│  │  - Submit button                 │   │
│  └─────────────────────────────────┘   │
│                                          │
│  "Add Overtime" button: DISABLED         │
│                                          │
└─────────────────────────────────────────┘
                    ↓ (Submit form)
┌─────────────────────────────────────────┐
│  Overtime Submitted                      │
├─────────────────────────────────────────┤
│                                          │
│  "Overtime submitted successfully!       │
│   It's pending admin approval."          │
│                                          │
│  Database Status: pending_approval      │
│                                          │
│  Awaiting admin approval...              │
│                                          │
└─────────────────────────────────────────┘
```

---

## Form Validation Rules

```
┌─────────────────────────────────────────────┐
│  FORM VALIDATION FLOW                        │
├─────────────────────────────────────────────┤
│                                              │
│  User fills form...                          │
│         ↓                                    │
│  ├─ Duration selected? ✓                   │
│  │  NO → Disable time slot                 │
│  │  YES → Enable time slot                 │
│  │                                          │
│  ├─ Time slot selected? ✓                  │
│  │  NO → Cannot submit                     │
│  │  YES → OK                               │
│  │                                          │
│  ├─ Photo uploaded? ✓                      │
│  │  NO → Show error "Please upload photo"  │
│  │  YES → Allow submission                 │
│  │                                          │
│  └─ Total Solds optional ✓                 │
│     Can be left empty                      │
│                                              │
│         ↓                                    │
│  Submit button clicked                      │
│         ↓                                    │
│  ├─ Client-side validation:                │
│  │  - Photo exists? ✓                      │
│  │  - Form data valid? ✓                   │
│  │                                          │
│  ├─ If errors → Show inline message        │
│  ├─ If OK → Submit form (POST)             │
│  │                                          │
│  └─ Server-side validation:                │
│     - Check all fields again                │
│     - Validate file upload                  │
│     - Check database constraints            │
│     - Insert record or return error         │
│                                              │
└─────────────────────────────────────────────┘
```

---

## Color Scheme

### Modal Elements:
```
Background:    Linear gradient (#1a1d2e → #16171f)
Borders:       Purple (#667eea with 0.3 opacity)
Text:          White (#ffffff)
Labels:        Light text (rgba(255, 255, 255, 0.9))
Inputs:        Dark with light borders
Focus:         Purple glow (rgba(102, 126, 234, 0.1))
Buttons:       Gradient purple (135deg, #667eea → #764ba2)
Hover:         Darker purple with glow
Close Button:  Purple background on hover
Upload Area:   Dashed purple border
Error Text:    Red (#ff6b6b)
```

---

## Responsive Behavior

### Desktop (1200px+)
```
Modal: 600px wide
Buttons: Side-by-side at bottom
Form fields: Full width
Photo preview: 200px height
```

### Tablet (768px - 1199px)
```
Modal: 95vw with max 600px
Buttons: Side-by-side (adjusted)
Form fields: Full width
Photo preview: 180px height
```

### Mobile (< 768px)
```
Modal: 95vw
Buttons: Stacked vertically
Form fields: Full width
Photo preview: 150px height
Modal padding: Reduced
Font sizes: Slightly smaller
```

---

## Accessibility Features

✓ Clear labels for all fields
✓ Required field indicators (*)
✓ Error messages in clear language
✓ High contrast text/background
✓ Large click targets for buttons
✓ Keyboard navigation support
✓ Focus indicators on inputs
✓ Modal focus trap
✓ ARIA labels (can be added)
✓ Screen reader friendly

---

## Animations

### Modal Appearance
```
Fade in: 0.3s ease-out
Overlay: opacity 0 → 1

Content: 
Slide up: 0.3s ease-out
translateY: 30px → 0
opacity: 0 → 1
```

### Button Hover
```
Transform: translateY(-2px)
Box-shadow: 0 4px 15px → 0 6px 25px
Duration: 0.2s
```

### Input Focus
```
Background: rgba(255,255,255, 0.08) → 0.12
Border-color: rgba(102,126,234, 0.3) → 0.6
Box-shadow: 0 0 0 3px rgba(102,126,234, 0.1)
Duration: 0.2s
```

---

## Error States

### Missing Photo
```
Photo-upload-container:
  border-color: #ff6b6b (red)
  border-width: 2px
  
Error message displays:
  "Please upload your overtime proof 
   photo before submitting."
```

### Invalid Selection
```
Time slot dropdown disabled until:
  ✓ Duration is selected
  ✓ Valid duration value
```

### Server Errors
```
Alert message displays:
  - "No attendance record found..."
  - "Failed to save uploaded photo..."
  - "Error submitting overtime..."
  
Form remains open for correction
```

---

This visual guide provides a complete picture of the user experience when using the overtime feature!
