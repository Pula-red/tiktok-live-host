<?php
require_once __DIR__ . '/../includes/functions.php';

// Require live_seller role
require_role('live_seller');

// Get current user info
$current_user = get_logged_in_user();

// Get seller dashboard stats
$db = getDB();

// Get today's date with different cutoffs based on what user submitted
// 3-hour shifts: Reset at 5:00 AM (5 AM - 5 AM cycle)
// 4-hour shifts: Reset at 6:00 AM (6 AM - 6 AM cycle)
$current_hour = (int)date('H');
$current_time = date('H:i:s');

// First, check what duration the user submitted (if any)
$stmt = $db->prepare("
    SELECT ats.duration_hours, a.attendance_date
    FROM attendance a
    LEFT JOIN attendance_time_slots ats ON a.time_slot = ats.id
    WHERE a.seller_id = ? AND a.status != 'cancelled'
    ORDER BY a.attendance_date DESC, a.created_at DESC
    LIMIT 1
");
$stmt->execute([$current_user['id']]);
$last_attendance = $stmt->fetch();

// Determine the appropriate reset time based on last submitted duration
if ($last_attendance) {
    $last_duration = (int)$last_attendance['duration_hours'];
    
    if ($last_duration == 3) {
        // User submitted 3-hour shift, use 5 AM reset
        $reset_hour = 5;
        $reset_time_text = "5:00 AM";
    } else {
        // User submitted 4-hour shift (or other), use 6 AM reset
        $reset_hour = 6;
        $reset_time_text = "6:00 AM";
    }
    
    // Calculate today based on the user's submitted duration reset time
    if ($current_hour < $reset_hour) {
        $today = date('Y-m-d', strtotime('-1 day'));
    } else {
        $today = date('Y-m-d');
    }
    
    // Check if user has attendance for today (based on their duration's reset time)
    $stmt = $db->prepare("
        SELECT COUNT(*) as attendance_count 
        FROM attendance a
        WHERE a.seller_id = ? AND a.attendance_date = ? AND a.status != 'cancelled'
    ");
    $stmt->execute([$current_user['id'], $today]);
    $has_attendance_today = $stmt->fetchColumn() > 0;
} else {
    // No previous attendance, use default 5 AM reset (earliest slot)
    $reset_hour = 5;
    $reset_time_text = "5:00 AM";
    
    if ($current_hour < 5) {
        $today = date('Y-m-d', strtotime('-1 day'));
    } else {
        $today = date('Y-m-d');
    }
    
    $has_attendance_today = false;
}

// Force view date to always be today - users can only submit attendance for current date
$view_date = $today;

// Check for successful submission
$attendance_submitted = isset($_SESSION['attendance_submitted']) && $_SESSION['attendance_submitted'] === true;
if ($attendance_submitted) {
    unset($_SESSION['attendance_submitted']);
}

// Get attendance data for the viewed date
$stmt = $db->prepare("
    SELECT a.*, ats.name as slot_name, ats.duration_hours, ats.start_time, ats.end_time
    FROM attendance a
    LEFT JOIN attendance_time_slots ats ON a.time_slot = ats.id
    WHERE a.seller_id = ? AND a.attendance_date = ?
    ORDER BY ats.start_time
");
$stmt->execute([$current_user['id'], $view_date]);
$view_date_attendance = $stmt->fetchAll();

// Get available time slots
$stmt = $db->query("SELECT * FROM attendance_time_slots WHERE is_active = 1 ORDER BY start_time");
$available_slots = $stmt->fetchAll();

// Handle attendance actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'schedule_slot') {
        $slot_id = $_POST['slot_id'] ?? '';
        $attendance_date = $_POST['attendance_date'] ?? $today;
        $custom_slot_data = $_POST['custom_slot_data'] ?? '';
        
        // STRICT VALIDATION: Users can ONLY submit for today's date
        // Force attendance date to be today - ignore any other dates from the form
        $attendance_date = $today;
        
        if ($slot_id && $attendance_date && $custom_slot_data) {
            try {
                $slot_data = json_decode($custom_slot_data, true);
                
                    if ($slot_data && isset($slot_data['duration'], $slot_data['start_time'], $slot_data['end_time'], $slot_data['name'])) {
                    // First, check if a time slot with these exact times already exists
                    $stmt = $db->prepare("
                        SELECT id FROM attendance_time_slots 
                        WHERE start_time = ? AND end_time = ? AND duration_hours = ?
                    ");
                    $stmt->execute([$slot_data['start_time'], $slot_data['end_time'], $slot_data['duration']]);
                    $existing_slot = $stmt->fetch();
                    
                    if ($existing_slot) {
                        $time_slot_id = $existing_slot['id'];
                    } else {
                        // Create new time slot
                        $stmt = $db->prepare("
                            INSERT INTO attendance_time_slots (name, start_time, end_time, duration_hours, is_active)
                            VALUES (?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([
                            $slot_data['name'],
                            $slot_data['start_time'],
                            $slot_data['end_time'],
                            $slot_data['duration']
                        ]);
                        $time_slot_id = $db->lastInsertId();
                    }
                    
                    // Check if this seller already has this slot scheduled for the same date
                    $stmt = $db->prepare("
                        SELECT id FROM attendance 
                        WHERE seller_id = ? AND attendance_date = ? AND time_slot = ?
                    ");
                    $stmt->execute([$current_user['id'], $attendance_date, $time_slot_id]);
                    $existing_attendance = $stmt->fetch();
                    
                    if ($existing_attendance) {
                        $error_message = "You have already scheduled this time slot for the selected date.";
                    } else {
                        // Handle solds and photo upload (photo is required)
                        $solds_qty = isset($_POST['solds']) ? (int)$_POST['solds'] : 0;
                        $photo_path = null;
                        if (!isset($_FILES['sold_photo']) || $_FILES['sold_photo']['error'] !== UPLOAD_ERR_OK) {
                            $error_message = "Please upload your total sold photo before submitting.";
                            $photo_error = $error_message;
                        } else {
                            $upload_dir = __DIR__ . '/../uploads/attendance/';
                            if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
                            $ext = pathinfo($_FILES['sold_photo']['name'], PATHINFO_EXTENSION);
                            $filename = 'sold_' . $current_user['id'] . '_' . time() . '.' . $ext;
                            $dest = $upload_dir . $filename;
                            if (move_uploaded_file($_FILES['sold_photo']['tmp_name'], $dest)) {
                                $photo_path = 'uploads/attendance/' . $filename;
                            } else {
                                $error_message = "Failed to save uploaded photo. Please try again.";
                                $photo_error = $error_message;
                            }
                        }

                            // Compute hours_worked from slot start/end times (handle overnight shifts)
                            $hoursWorked = null;
                            if (!empty($slot_data['start_time']) && !empty($slot_data['end_time'])) {
                                try {
                                    $startDt = new DateTime($attendance_date . ' ' . $slot_data['start_time']);
                                    $endDt = new DateTime($attendance_date . ' ' . $slot_data['end_time']);
                                    if ($endDt <= $startDt) {
                                        // assume end time is next day (overnight shift)
                                        $endDt->modify('+1 day');
                                    }
                                    $diffSeconds = $endDt->getTimestamp() - $startDt->getTimestamp();
                                    $hoursWorked = round($diffSeconds / 3600, 2);
                                } catch (Exception $e) {
                                    // if anything goes wrong, leave hoursWorked as null
                                    $hoursWorked = null;
                                }
                            }

                        // Only insert if there is no validation error
                        if (empty($error_message)) {
                            // Schedule the attendance into new table (persist hours_worked)
                            // Set status to 'completed' since user is submitting with sold data and photo
                            $stmt = $db->prepare("
                                INSERT INTO attendance (seller_id, attendance_date, duration, time_slot, solds_quantity, total_sold_photo, hours_worked, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
                            ");
                            $stmt->execute([$current_user['id'], $attendance_date, $slot_data['duration'] . '-hour', $time_slot_id, $solds_qty, $photo_path, $hoursWorked]);

                            // Set session flag for successful submission
                            $_SESSION['attendance_submitted'] = true;

                            // Redirect to prevent form resubmission
                            header('Location: ' . $_SERVER['REQUEST_URI']);
                            exit;
                        }
                    }
                } else {
                    $error_message = "Invalid slot data provided.";
                }
            } catch (Exception $e) {
                $error_message = "Error scheduling slot: " . $e->getMessage();
            }
        } else {
            $error_message = "Please select both duration and time slot.";
        }
    } elseif ($action === 'cancel_slot') {
        $attendance_id = $_POST['attendance_id'] ?? '';
        if ($attendance_id) {
            $stmt = $db->prepare("
                UPDATE attendance 
                SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND seller_id = ? AND status = 'scheduled'
            ");
            $stmt->execute([$attendance_id, $current_user['id']]);
            $success_message = "Time slot cancelled successfully!";
        }
    }
    
    // Refresh attendance data after action
    $stmt = $db->prepare("
        SELECT a.*, ats.name as slot_name, ats.duration_hours, ats.start_time, ats.end_time
        FROM attendance a
        LEFT JOIN attendance_time_slots ats ON a.time_slot = ats.id
        WHERE a.seller_id = ? AND a.attendance_date = ?
        ORDER BY ats.start_time
    ");
    $stmt->execute([$current_user['id'], $view_date]);
    $view_date_attendance = $stmt->fetchAll();
}

// Get upcoming schedule (next 7 days)
$stmt = $db->prepare("
    SELECT a.*, ats.name as slot_name, ats.duration_hours, ats.start_time, ats.end_time
    FROM attendance a
    LEFT JOIN attendance_time_slots ats ON a.time_slot = ats.id
    WHERE a.seller_id = ? AND a.attendance_date BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)
    ORDER BY a.attendance_date, ats.start_time
");
$stmt->execute([$current_user['id'], $today, $today]);
$upcoming_schedule = $stmt->fetchAll();

$page_title = 'Schedule & Attendance';
include 'layout/header.php';
?>

<div class="schedule-container">
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Simple Schedule Form Layout -->
    <div class="simple-schedule-container">
        <?php if ($attendance_submitted): ?>
            <!-- Success Message with Dashboard Redirect -->
            <div class="attendance-success-card">
                <div class="success-icon">✅</div>
                <h2>Attendance Submitted Successfully!</h2>
                <p>Your attendance has been recorded for today. Thank you for your submission.</p>
                <div class="success-actions">
                    <a href="dashboard.php" class="btn btn-primary btn-large">
                        <span class="btn-icon">🏠</span>
                        Go to Dashboard
                    </a>
                </div>
            </div>
        <?php elseif ($has_attendance_today): ?>
            <!-- Already Submitted Message -->
            <div class="attendance-form-wrapper">
                <div class="attendance-form-card already-submitted">
                    <div class="form-body">
                        <div class="attendance-status-display">
                            <div class="status-icon-large">✅</div>
                            <div class="status-content">
                                <h3>Attendance Successfully Recorded</h3>
                                <div class="status-summary">
                                    <p class="main-message">Your daily attendance has been confirmed!</p>
                                    <p class="date-info">Submitted on <span class="highlight-date"><?php echo date('F j, Y'); ?></span></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="next-submission-info">
                            <div class="info-content-compact">
                                <span class="info-icon">🕐</span>
                                <div class="info-text">
                                    <?php 
                                    // Calculate next submission time based on submitted duration's reset time
                                    // Since user has already submitted for "today" (based on their reset time),
                                    // the next submission is always at the next reset time
                                    $current_hour = (int)date('H');
                                    
                                    if ($current_hour < $reset_hour) {
                                        // Before reset time - but they already submitted for "today"
                                        // So next submission is at the reset time (which marks the start of the actual calendar day)
                                        $actual_calendar_date = date('Y-m-d');
                                        $next_date = date('F j, Y', strtotime($actual_calendar_date));
                                        $next_day_text = "today";
                                    } else {
                                        // After reset time - next submission is tomorrow at reset time
                                        $next_date = date('F j, Y', strtotime('+1 day'));
                                        $next_day_text = "tomorrow";
                                    }
                                    ?>
                                    <p class="next-date">Next submission available <?php echo $next_day_text; ?>, <strong><?php echo $next_date; ?> at <?php echo $reset_time_text; ?></strong></p>
                                    <p class="thank-you">Your attendance resets at <strong><?php echo $reset_time_text; ?></strong> based on your <?php echo $last_duration; ?>-hour shift. Thank you for your participation!</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-footer">
                        <div class="single-action">
                            <a href="dashboard.php" class="btn btn-primary btn-dashboard">
                                <span class="btn-icon">🏠</span>
                                <span class="btn-text">Return to Dashboard</span>
                                <span class="btn-arrow">→</span>
                            </a>
                            <p class="footer-note">Continue managing your schedule from the dashboard</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Schedule Form -->
            <div class="schedule-form-card">
            <div class="form-header">
                <h2>Schedule Your Time Slot</h2>
                <p>Choose your preferred duration and time slot. We offer 3-hour and 4-hour shifts throughout the day.</p>
            </div>
            
                        <form method="POST" class="simple-schedule-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="schedule_slot">
                <input type="hidden" name="attendance_date" value="<?php echo $today; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Date:</label>
                        <div class="date-display-field">
                            <span class="date-icon">📅</span>
                            <span class="date-text"><?php echo date('l, F j, Y', strtotime($today)); ?></span>
                            <span class="date-badge">Today</span>
                        </div>
                        <p class="field-note">You can only submit attendance for today</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration_choice" class="required">Duration:</label>
                        <select id="duration_choice" name="duration_choice" required onchange="updateTimeSlots()">
                            <option value="">Select duration...</option>
                            <option value="3">3 Hours</option>
                            <option value="4">4 Hours</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="slot_id" class="required">Time Slot:</label>
                    <select id="slot_id" name="slot_id" required disabled>
                        <option value="">First select duration...</option>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label for="solds">Solds:</label>
                    <input type="number" id="solds" name="solds" placeholder="Enter total solds amount" min="0" step="1">
                </div>
                
                <div class="form-group full-width">
                    <label for="sold_photo">📱 Total Sold Photo:</label>
                    <div class="photo-upload-container" <?php echo isset($photo_error) ? "style='border:2px solid #ff6b6b; padding:8px; border-radius:6px;'" : ''; ?> >
                        <input type="file" id="sold_photo" name="sold_photo" accept="image/*" class="file-input">
                        <div class="upload-placeholder" onclick="document.getElementById('sold_photo').click()">
                            <span class="upload-icon">📷</span>
                            <p>Upload your total sold photo</p>
                            <span class="btn btn-outline">Choose Photo</span>
                        </div>
                        <div id="photo-error" class="field-error" style="color:#ff6b6b; margin-top:8px; display: <?php echo isset($photo_error) ? 'block' : 'none'; ?>; ">
                            <?php echo htmlspecialchars($photo_error ?? ''); ?>
                        </div>
                        <div id="photo-preview" class="photo-preview" style="display: none;">
                            <img id="preview-image" src="" alt="Preview">
                            <button type="button" class="remove-photo" onclick="removePhoto()">×</button>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-large" style="text-align: center; display: flex; align-items: center; justify-content: center;">
                    <span class="btn-icon">⏰</span>
                    Submit
                </button>
                
                <input type="hidden" id="custom_slot_data" name="custom_slot_data" value="">
            </form>
        </div>
        <?php endif; ?>
    </div>

<script>
// Define time slot data
const timeSlots = {
    3: [
        { value: "3_5am_8am", text: "5:00 AM - 8:00 AM", start_time: "05:00:00", end_time: "08:00:00" },
        { value: "3_8am_11am", text: "8:00 AM - 11:00 AM", start_time: "08:00:00", end_time: "11:00:00" },
        { value: "3_11am_2pm", text: "11:00 AM - 2:00 PM", start_time: "11:00:00", end_time: "14:00:00" },
        { value: "3_2pm_5pm", text: "2:00 PM - 5:00 PM", start_time: "14:00:00", end_time: "17:00:00" },
        { value: "3_5pm_8pm", text: "5:00 PM - 8:00 PM", start_time: "17:00:00", end_time: "20:00:00" },
        { value: "3_8pm_11pm", text: "8:00 PM - 11:00 PM", start_time: "20:00:00", end_time: "23:00:00" },
        { value: "3_11pm_2am", text: "11:00 PM - 2:00 AM", start_time: "23:00:00", end_time: "02:00:00" },
        { value: "3_2am_5am", text: "2:00 AM - 5:00 AM", start_time: "02:00:00", end_time: "05:00:00" }
    ],
    4: [
        { value: "4_6am_10am", text: "6:00 AM - 10:00 AM", start_time: "06:00:00", end_time: "10:00:00" },
        { value: "4_10am_2pm", text: "10:00 AM - 2:00 PM", start_time: "10:00:00", end_time: "14:00:00" },
        { value: "4_2pm_6pm", text: "2:00 PM - 6:00 PM", start_time: "14:00:00", end_time: "18:00:00" },
        { value: "4_6pm_10pm", text: "6:00 PM - 10:00 PM", start_time: "18:00:00", end_time: "22:00:00" },
        { value: "4_10pm_2am", text: "10:00 PM - 2:00 AM", start_time: "22:00:00", end_time: "02:00:00" },
        { value: "4_2am_6am", text: "2:00 AM - 6:00 AM", start_time: "02:00:00", end_time: "06:00:00" }
    ]
};

function updateTimeSlots() {
    const durationChoice = document.getElementById('duration_choice').value;
    const slotSelect = document.getElementById('slot_id');
    const customSlotData = document.getElementById('custom_slot_data');
    
    updateSlotDropdown(durationChoice, slotSelect, customSlotData);
}

function updateSlotDropdown(durationChoice, slotSelect, customSlotData) {
    // Clear existing options
    slotSelect.innerHTML = '<option value="">Select a time slot...</option>';
    
    if (durationChoice && timeSlots[durationChoice]) {
        slotSelect.disabled = false;
        
        timeSlots[durationChoice].forEach(slot => {
            const option = document.createElement('option');
            option.value = slot.value;
            option.textContent = slot.text;
            slotSelect.appendChild(option);
        });
    } else {
        slotSelect.disabled = true;
        slotSelect.innerHTML = '<option value="">First select duration...</option>';
    }
    
    // Update custom slot data when selection changes
    slotSelect.onchange = function() {
        const selectedSlot = timeSlots[durationChoice]?.find(slot => slot.value === this.value);
        if (selectedSlot) {
            customSlotData.value = JSON.stringify({
                duration: durationChoice,
                start_time: selectedSlot.start_time,
                end_time: selectedSlot.end_time,
                name: selectedSlot.text
            });
        } else {
            customSlotData.value = '';
        }
    };
}

// Auto-refresh attendance status every 30 seconds
setInterval(function() {
    // Check if there are any active sessions
    const liveIndicators = document.querySelectorAll('.live-indicator');
    if (liveIndicators.length > 0) {
        // Only refresh if there are active sessions to avoid unnecessary requests
        location.reload();
    }
}, 30000);

// Photo preview functionality
document.getElementById('sold_photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-image').src = e.target.result;
            document.getElementById('photo-preview').style.display = 'block';
            document.querySelector('.upload-placeholder').style.display = 'none';
            // clear inline photo error and red border when a file is chosen
            const photoErrorEl = document.getElementById('photo-error');
            const uploadDiv = document.querySelector('.photo-upload-container');
            if (photoErrorEl) { photoErrorEl.style.display = 'none'; photoErrorEl.textContent = ''; }
            if (uploadDiv) { uploadDiv.style.border = ''; }
        };
        reader.readAsDataURL(file);
    }
});

// Prevent form submit if no photo selected and show inline error
document.querySelector('.simple-schedule-form').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('sold_photo');
    const photoErrorEl = document.getElementById('photo-error');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        e.preventDefault();
        if (photoErrorEl) {
            photoErrorEl.textContent = 'Please upload your total sold photo before submitting the schedule.';
            photoErrorEl.style.display = 'block';
        } else {
            alert('Please upload your total sold photo before submitting the schedule.');
        }
        return false;
    } else {
        if (photoErrorEl) {
            photoErrorEl.style.display = 'none';
        }
    }
});

function removePhoto() {
    document.getElementById('sold_photo').value = '';
    document.getElementById('photo-preview').style.display = 'none';
    document.querySelector('.upload-placeholder').style.display = 'flex';
}
</script>

<?php include 'layout/footer.php'; ?>