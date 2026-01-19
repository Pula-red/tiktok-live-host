# Overtime Feature - Quick Reference Guide

## 🚀 Quick Start (5 minutes)

### Step 1: Run Database Migration
```bash
mysql -u root -p tiktok_live_host < sql/add_overtime_table.sql
```

### Step 2: Files Already Updated
- ✅ `live-sellers/schedule.php` - Feature implemented
- ✅ `assets/css/live-seller.css` - Styling added
- ✅ `sql/add_overtime_table.sql` - Database schema ready

### Step 3: Test It Out
1. Log in as live seller
2. Go to Schedule page
3. Submit daily attendance
4. Click "Add Overtime" button
5. Fill form and submit

---

## 📋 Quick Reference

### File Locations

| File | Purpose | Status |
|------|---------|--------|
| `live-sellers/schedule.php` | Main feature code | ✅ Updated |
| `assets/css/live-seller.css` | Styling & animations | ✅ Updated |
| `sql/add_overtime_table.sql` | Database migration | ✅ Created |

### Documentation Files

| File | Purpose |
|------|---------|
| `OVERTIME_COMPLETION_SUMMARY.md` | This is the summary - start here! |
| `OVERTIME_IMPLEMENTATION_GUIDE.md` | Step-by-step setup guide |
| `OVERTIME_FEATURE_README.md` | Complete documentation |
| `OVERTIME_CHANGES_SUMMARY.md` | Detailed change log |
| `OVERTIME_DEPLOYMENT_CHECKLIST.md` | Testing & deployment checklist |
| `OVERTIME_VISUAL_GUIDE.md` | UI flows and design details |

---

## ⚙️ Technical Details

### Database Table: `overtime`

Key columns:
- `seller_id` - Who submitted
- `attendance_id` - Links to daily attendance
- `duration_hours` - 3 or 4 hours
- `start_time`, `end_time` - Time slot
- `solds_quantity` - Items sold
- `overtime_photo` - File path
- `status` - pending_approval, approved, rejected

### Time Slots

**3-Hour Overtime** (12 slots from 5 AM to 5 AM):
```
5-7am, 7-9am, 9-11am, 11-1pm, 1-3pm, 3-5pm,
5-7pm, 7-9pm, 9-11pm, 11-1am, 1-3am, 3-5am
```

**4-Hour Overtime** (12 slots from 6 AM to 6 AM):
```
6-8am, 8-10am, 10-12pm, 12-2pm, 2-4pm, 4-6pm,
6-8pm, 8-10pm, 10-12am, 12-2am, 2-4am, 4-6am
```

---

## 🎯 User Flow

```
1. Submit Attendance
         ↓
2. "Add Overtime" Button Appears
         ↓
3. Click Button → Modal Opens
         ↓
4. Select Duration (3 or 4 hours)
         ↓
5. Select Time Slot (2-hour intervals)
         ↓
6. Enter Total Solds (optional)
         ↓
7. Upload Proof Photo (required)
         ↓
8. Click Submit
         ↓
9. Record Created → Pending Admin Approval
```

---

## 📝 Form Fields

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Date | Display | - | Same as attendance date |
| Duration | Select | Yes | 3 or 4 hours |
| Time Slot | Select | Yes | Depends on duration |
| Total Solds | Number | No | Optional quantity |
| Photo | File | Yes | Image file required |

---

## 🔧 Troubleshooting Quick Answers

**Q: Button not showing?**
A: Verify attendance is approved/pending. Refresh page.

**Q: File upload failing?**
A: Check file is image format. Verify uploads folder permissions.

**Q: Database errors?**
A: Run migration first: `mysql -u root -p < sql/add_overtime_table.sql`

**Q: Modal not opening?**
A: Check browser console (F12) for JS errors. Clear cache.

---

## 🎨 Styling Quick Reference

### Colors
- Background: Dark gradient (#1a1d2e)
- Primary: Purple (#667eea)
- Accent: Orange (#fbbf24)
- Error: Red (#ff6b6b)
- Text: White

### Responsive Sizes
- Desktop: Full layout
- Tablet (768px): Adjusted
- Mobile (480px): Stacked buttons

---

## 🔐 Key Security Points

✅ Files uploaded to `uploads/overtime/`
✅ Unique filenames prevent overwrite
✅ Photo required - enforced validation
✅ Users can only submit their own overtime
✅ Server-side validation on all data
✅ Database constraints enforced

---

## 📊 Common Queries

### Get all pending overtimes:
```sql
SELECT * FROM overtime 
WHERE status = 'pending_approval'
ORDER BY created_at DESC;
```

### Get overtime by seller:
```sql
SELECT * FROM overtime 
WHERE seller_id = ? 
ORDER BY overtime_date DESC;
```

### Check if user can add overtime:
```sql
SELECT id FROM attendance 
WHERE seller_id = ? 
AND attendance_date = CURDATE()
AND status IN ('approved', 'pending_approval');
```

---

## 📱 Mobile Support

- ✅ Responsive modal
- ✅ Touch-friendly buttons
- ✅ Mobile-optimized form
- ✅ Works on iOS Safari
- ✅ Works on Chrome Mobile
- ✅ Portrait & landscape

---

## ⏱️ Time Estimates

| Task | Time |
|------|------|
| Database migration | < 1 min |
| Testing feature | 5-10 min |
| Full deployment | 15-30 min |
| Admin integration | 2-4 hours |

---

## 🎓 Key Concepts

### Duration
The overtime shift length: 3 or 4 hours

### Time Slot
A 2-hour window within the duration cycle
- 3-hour slots: 5 AM to 5 AM (12 slots)
- 4-hour slots: 6 AM to 6 AM (12 slots)

### Status
- `pending_approval` - Awaiting admin review
- `approved` - Admin approved the overtime
- `rejected` - Admin rejected (with reason)

### Proof Photo
Required image file showing work/sales proof
- Stored in `uploads/overtime/`
- Linked in overtime_photo column
- Viewable by admin during approval

---

## 🚨 Important Notes

⚠️ **Must do first**: Run database migration
⚠️ **Upload directory**: Will auto-create, ensure permissions
⚠️ **Photo required**: Form won't submit without it
⚠️ **Admin panel**: Not included in this release (next phase)

---

## ✅ Verification Checklist

Before going live:

- [ ] Database migrated successfully
- [ ] "Add Overtime" button appears after attendance
- [ ] Modal opens and closes smoothly
- [ ] Duration selector works
- [ ] Time slots change with duration
- [ ] Form validates (requires photo)
- [ ] Photo upload works
- [ ] Form submits without errors
- [ ] Data saved in database
- [ ] Status is "pending_approval"

---

## 🎯 Next Steps for Admin Panel

When building the admin approval interface:

1. Create `admin/overtime-approval.php`
2. List all pending overtimes
3. Show seller details and photo
4. Add approve/reject buttons
5. Update database status on action
6. Send notifications (optional)

---

## 📞 Quick Help

### Reset Test Data
```sql
-- Delete test overtimes
DELETE FROM overtime WHERE seller_id = ?;

-- Verify deletion
SELECT COUNT(*) FROM overtime;
```

### View Latest Overtimes
```sql
SELECT * FROM overtime 
ORDER BY created_at DESC 
LIMIT 10;
```

### Check Uploads
```bash
# List uploaded files
ls -la uploads/overtime/

# Check file permissions
chmod 755 uploads/overtime/
```

---

## 🎉 You're All Set!

The overtime feature is ready to use. Follow the quick start steps and you're good to go!

For detailed information, see the comprehensive documentation files.

**Happy deploying!** 🚀
