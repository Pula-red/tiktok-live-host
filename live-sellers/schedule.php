<?php
require_once __DIR__ . '/../includes/functions.php';

// Require live_seller role
require_role('live_seller');

// Get current user info
$current_user = get_logged_in_user();

// Get seller dashboard stats
$db = getDB();

// Get initial attendance status
$stmt = $db->prepare("
    SELECT id, status, rejection_reason, approved_at, approved_by, created_at
    FROM attendance 
    WHERE seller_id = ? 
    AND attendance_date = CURDATE()
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute([$current_user['id']]);
$latest_attendance = $stmt->fetch();

// Treat rejected attendance same as pending/approved - no new submissions until reset time
if ($latest_attendance && $latest_attendance['status'] === 'rejected') {
    $has_attendance_today = true;
}

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
    
    // Check if user has any attendance for today
    $stmt = $db->prepare("
        SELECT id, status, rejection_reason, approved_at, approved_by, created_at
        FROM attendance 
        WHERE seller_id = ? 
        AND attendance_date = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$current_user['id'], $today]);
    $latest_attendance = $stmt->fetch();
    
    // Determine if user can submit new attendance
    $has_attendance_today = false;
    if ($latest_attendance) {
        if ($latest_attendance['status'] === 'approved' || $latest_attendance['status'] === 'pending_approval') {
            $has_attendance_today = true;
        }
        // If status is rejected, allow new submission
        if ($latest_attendance['status'] === 'rejected') {
            $has_attendance_today = false;
        }
    }
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

// Check if user has overtime for today
$stmt = $db->prepare("
    SELECT id, status FROM overtime 
    WHERE seller_id = ? AND overtime_date = ?
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$current_user['id'], $today]);
$today_overtime = $stmt->fetch();

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
    
    // Handle AJAX request for attendance end time and overtime slots
        if ($action === 'get_attendance_endtime') {
            header('Content-Type: application/json');
            
            // Get latest attendance with time slot info
            $stmt = $db->prepare("
                SELECT a.id, a.status, ats.start_time, ats.end_time, ats.name, ats.duration_hours
                FROM attendance a
                LEFT JOIN attendance_time_slots ats ON a.time_slot = ats.id
                WHERE a.seller_id = ? AND a.attendance_date = ?
                AND a.status IN ('approved', 'pending_approval')
                ORDER BY a.created_at DESC LIMIT 1
            ");
            $stmt->execute([$current_user['id'], $today]);
            $attendance = $stmt->fetch();
            
            if ($attendance) {
                $endTime = $attendance['end_time'];
                $slotName = $attendance['name'];
                
                // Format time display - show hours with AM/PM like "6-10 AM"
                $startTime = new DateTime('2000-01-01 ' . $attendance['start_time']);
                $endTimeObj = new DateTime('2000-01-01 ' . $endTime);
                $startHour = $startTime->format('g');
                $endHour = $endTimeObj->format('g');
                $endAMPM = $endTimeObj->format('A');
                $slotDisplay = $startHour . '-' . $endHour . ' ' . $endAMPM;
                
                echo json_encode([
                    'success' => true,
                    'attendance_id' => $attendance['id'],
                    'end_time' => $endTime,
                    'slot_display' => $slotDisplay,
                    'duration_hours' => $attendance['duration_hours']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No approved attendance found'
                ]);
            }
            exit;
        }
        
        // Handle AJAX request for overtime slots based on duration and attendance
        if ($action === 'get_overtime_slots') {
            header('Content-Type: application/json');
            $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
            
            // Get latest attendance with time slot info
            $stmt = $db->prepare("
                SELECT a.id, a.status, ats.start_time, ats.end_time, ats.name, ats.duration_hours
                FROM attendance a
                LEFT JOIN attendance_time_slots ats ON a.time_slot = ats.id
                WHERE a.seller_id = ? AND a.attendance_date = ?
                AND a.status IN ('approved', 'pending_approval')
                ORDER BY a.created_at DESC LIMIT 1
            ");
            $stmt->execute([$current_user['id'], $today]);
            $attendance = $stmt->fetch();
            
            if (!$attendance) {
                echo json_encode(['success' => false, 'message' => 'No approved attendance found']);
                exit;
            }
            
            // Define specific overtime slots based on attendance duration
            $overtime_slots_3hr = [
                ['start' => '08:00:00', 'end' => '10:00:00', 'display' => '8 AM - 10 AM'],
                ['start' => '11:00:00', 'end' => '13:00:00', 'display' => '11 AM - 1 PM'],
                ['start' => '14:00:00', 'end' => '16:00:00', 'display' => '2 PM - 4 PM'],
                ['start' => '17:00:00', 'end' => '19:00:00', 'display' => '5 PM - 7 PM'],
                ['start' => '20:00:00', 'end' => '22:00:00', 'display' => '8 PM - 10 PM'],
                ['start' => '23:00:00', 'end' => '01:00:00', 'display' => '11 PM - 1 AM'],
                ['start' => '02:00:00', 'end' => '04:00:00', 'display' => '2 AM - 4 AM'],
                ['start' => '05:00:00', 'end' => '07:00:00', 'display' => '5 AM - 7 AM'],
            ];
            
            $overtime_slots_4hr = [
                ['start' => '10:00:00', 'end' => '12:00:00', 'display' => '10 AM - 12 PM'],
                ['start' => '14:00:00', 'end' => '16:00:00', 'display' => '2 PM - 4 PM'],
                ['start' => '18:00:00', 'end' => '20:00:00', 'display' => '6 PM - 8 PM'],
                ['start' => '22:00:00', 'end' => '00:00:00', 'display' => '10 PM - 12 AM'],
                ['start' => '02:00:00', 'end' => '04:00:00', 'display' => '2 AM - 4 AM'],
                ['start' => '06:00:00', 'end' => '08:00:00', 'display' => '6 AM - 8 AM'],
            ];
            
            // Select the appropriate slots based on duration
            $selected_slots = ($duration == 4) ? $overtime_slots_4hr : $overtime_slots_3hr;
            
            // Get attendance end time for filtering
            $attendance_end_time = $attendance['end_time'];
            $end_time_seconds = strtotime("2000-01-01 " . $attendance_end_time);
            
            // Format slots for response, filtering to only show slots after attendance end time
            $overtime_slots = [];
            foreach ($selected_slots as $slot) {
                $slot_start = $slot['start'];
                $slot_start_seconds = strtotime("2000-01-01 " . $slot_start);
                
                // Compare times: if slot start >= attendance end time, include it
                // Also include early morning slots (2AM, 5AM, 6AM) as they're always after daytime shifts
                if ($slot_start_seconds >= $end_time_seconds || $slot_start_seconds < strtotime("2000-01-01 10:00:00")) {
                    $overtime_slots[] = [
                        'value' => 'overtime_' . $slot['start'] . '_' . $slot['end'],
                        'text' => $slot['display'],
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end']
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'slots' => $overtime_slots,
                'attendance_end' => date('g:i A', strtotime($attendance['end_time'])),
                'attendance_name' => $attendance['name']
            ]);
            exit;
        }    if ($action === 'schedule_slot') {
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
                    
                    // Check if this seller already has this slot scheduled for the same date (excluding rejected)
                    $stmt = $db->prepare("
                        SELECT id FROM attendance 
                        WHERE seller_id = ? 
                        AND attendance_date = ? 
                        AND time_slot = ? 
                        AND status NOT IN ('rejected')
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
                            // Check if there's any approved or pending attendance for today
                            $check_stmt = $db->prepare("
                                SELECT COUNT(*) 
                                FROM attendance 
                                WHERE seller_id = ? 
                                AND attendance_date = ? 
                                AND status IN ('approved', 'pending_approval')
                                AND id NOT IN (
                                    SELECT id FROM attendance 
                                    WHERE seller_id = ? 
                                    AND attendance_date = ? 
                                    AND status = 'rejected'
                                )
                            ");
                            $check_stmt->execute([$current_user['id'], $attendance_date, $current_user['id'], $attendance_date]);
                            $has_active_attendance = $check_stmt->fetchColumn() > 0;

                            if (!$has_active_attendance) {
                                // Schedule the attendance into new table (persist hours_worked)
                                // Set status to 'pending_approval' until admin approves
                                $stmt = $db->prepare("
                                    INSERT INTO attendance (seller_id, attendance_date, duration, time_slot, solds_quantity, total_sold_photo, hours_worked, status)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_approval')
                                ");
                                $stmt->execute([$current_user['id'], $attendance_date, $slot_data['duration'] . '-hour', $time_slot_id, $solds_qty, $photo_path, $hoursWorked]);

                                // Set session flag for successful submission
                                $_SESSION['attendance_submitted'] = true;
                            } else {
                                $error_message = "You already have an approved or pending attendance for today.";
                            }

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
    } elseif ($action === 'add_overtime') {
        $overtime_slot = $_POST['overtime_slot'] ?? '';
        $overtime_solds = isset($_POST['overtime_solds']) ? (int)$_POST['overtime_solds'] : 0;
        $attendance_id = isset($_POST['attendance_id']) ? (int)$_POST['attendance_id'] : 0;
        $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
        
        if (!$attendance_id) {
            $error_message = "No attendance record found. Please submit attendance first.";
        } elseif (!$duration || ($duration !== 3 && $duration !== 4)) {
            $error_message = "Invalid duration selected.";
        } elseif (!$overtime_slot) {
            $error_message = "Please select an overtime time slot.";
        } else {
            // Get the attendance date from the attendance record
            $attendance_stmt = $db->prepare("
                SELECT attendance_date FROM attendance WHERE id = ? AND seller_id = ?
            ");
            $attendance_stmt->execute([$attendance_id, $current_user['id']]);
            $attendance_record = $attendance_stmt->fetch();
            
            if (!$attendance_record) {
                $error_message = "Invalid attendance record. Please submit attendance first.";
            } else {
                // Use the attendance date for overtime record (since it's a continuation of that work day)
                $overtime_date = $attendance_record['attendance_date'];
                
                // Check if user already has overtime for this attendance date
                $check_stmt = $db->prepare("
                    SELECT COUNT(*) as count FROM overtime 
                    WHERE seller_id = ? AND overtime_date = ? AND status != 'rejected'
                ");
                $check_stmt->execute([$current_user['id'], $overtime_date]);
                $overtime_count = $check_stmt->fetch()['count'];
                
                if ($overtime_count > 0) {
                    $error_message = "You have already submitted overtime for this date. Only 1 overtime per day is allowed.";
                } else {
                    // Validate file upload
                    if (!isset($_FILES['overtime_photo']) || $_FILES['overtime_photo']['error'] !== UPLOAD_ERR_OK) {
                        $error_message = "Please upload your overtime proof photo before submitting.";
                    } else {
                        // Parse overtime slot to get start and end times
                        // Format: overtime_HH:MM:SS_HH:MM:SS
                        if (preg_match('/^overtime_(\d{2}):(\d{2}):(\d{2})_(\d{2}):(\d{2}):(\d{2})$/', $overtime_slot, $matches)) {
                            $overtime_start_time = $matches[1] . ':' . $matches[2] . ':' . $matches[3];
                            $overtime_end_time = $matches[4] . ':' . $matches[5] . ':' . $matches[6];
                            
                            $upload_dir = __DIR__ . '/../uploads/overtime/';
                            if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
                            
                            $ext = pathinfo($_FILES['overtime_photo']['name'], PATHINFO_EXTENSION);
                            $filename = 'overtime_' . $current_user['id'] . '_' . time() . '.' . $ext;
                            $dest = $upload_dir . $filename;
                            
                            if (move_uploaded_file($_FILES['overtime_photo']['tmp_name'], $dest)) {
                                $photo_path = 'uploads/overtime/' . $filename;
                                
                                // Insert into overtime table
                                $stmt = $db->prepare("
                                    INSERT INTO overtime (seller_id, attendance_id, overtime_date, duration_hours, start_time, end_time, solds_quantity, overtime_photo, status)
                                    VALUES (?, ?, ?, 2, ?, ?, ?, ?, 'pending_approval')
                                ");
                                
                                try {
                                    $stmt->execute([
                                        $current_user['id'],
                                        $attendance_id,
                                        $overtime_date,
                                        $overtime_start_time,
                                        $overtime_end_time,
                                        $overtime_solds,
                                        $photo_path
                                    ]);
                                    
                                    $success_message = "Overtime submitted successfully! It's pending admin approval.";
                                    // Redirect to prevent form resubmission
                                    header('Location: ' . $_SERVER['REQUEST_URI']);
                                    exit;
                                } catch (Exception $e) {
                                    $error_message = "Error submitting overtime: " . $e->getMessage();
                                }
                            } else {
                                $error_message = "Failed to save uploaded photo. Please try again.";
                            }
                        } else {
                            $error_message = "Invalid overtime slot format.";
                        }
                    }
                }
            }
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
                <div class="success-icon">⏳</div>
                <h2>Attendance Submitted Successfully!</h2>
                <p>Your attendance has been submitted and is pending admin approval. You will be notified once it's approved.</p>
                <div class="pending-note">
                    <p style="color: #666; margin-top: 10px; font-size: 0.9em;">
                        Note: Your attendance needs to be approved by an admin before it's officially recorded.
                    </p>
                </div>
                <div class="success-actions">
                    <a href="dashboard.php" class="btn btn-primary btn-large">
                        <span class="btn-icon">🏠</span>
                        Go to Dashboard
                    </a>
                </div>
            </div>
        <?php elseif ($has_attendance_today || ($latest_attendance && $latest_attendance['status'] !== 'rejected') || ($latest_attendance && $latest_attendance['status'] === 'rejected' && !isset($_GET['new_submission']))): ?>
            <!-- Already Submitted Message -->
            <div class="attendance-form-wrapper">
                <div class="attendance-form-card already-submitted">
                    <div class="form-body">
                        <div class="attendance-status-display <?php echo htmlspecialchars($latest_attendance['status']); ?>">
                            <?php
                            $today_status = $latest_attendance['status'];
                            $status_icon = '';
                            $status_title = '';
                            $status_message = '';
                            $status_class = '';
                            $show_resubmit = false;

                            if ($today_status === 'pending_approval') {
                                $status_icon = '⏳';
                                $status_title = 'Attendance Pending Approval';
                                $status_message = 'Your attendance is currently under review';
                                $status_class = 'pending';
                            } elseif ($today_status === 'approved') {
                                $status_icon = '✅';
                                $status_title = 'Attendance Approved';
                                $status_message = 'Your daily attendance has been confirmed!';
                                $status_class = 'approved';
                            } elseif ($today_status === 'rejected') {
                                $status_icon = '❌';
                                $status_title = 'Attendance Rejected';
                                $status_message = 'Your attendance submission was not approved.';
                                $status_class = 'rejected';
                                $show_resubmit = true;
                                $has_attendance_today = false;  // Allow new submission for rejected status
                            }
                            ?>
                            <div class="status-icon-large <?php echo $status_class; ?>"><?php echo $status_icon; ?></div>
                            <div class="status-content">
                                <h3><?php echo $status_title; ?></h3>
                                <div class="status-summary">
                                    <?php if ($today_status === 'rejected'): ?>
                                        <p class="main-message" style="color: #22c55e;"><?php echo $status_message; ?></p>
                                        <?php if (!empty($latest_attendance['rejection_reason'])): ?>
                                            <div class="rejection-reason-container" style="
                                                border-radius: 8px;
                                                padding: 12px 16px;
                                                margin: 15px 0;
                                            ">
                                                <div style="
                                                    color: #dc2626;
                                                    font-weight: 600;
                                                    margin-bottom: 4px;
                                                ">Reason:</div>
                                                <div style="
                                                    color: #dc2626;
                                                    font-size: 0.95em;
                                                    line-height: 1.4;
                                                "><?php echo htmlspecialchars($latest_attendance['rejection_reason']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <p class="date-info">Submitted on <span class="highlight-date"><?php echo isset($latest_attendance['created_at']) ? date('F j, Y', strtotime($latest_attendance['created_at'])) : date('F j, Y'); ?></span></p>
                                    <?php else: ?>
                                        <p class="main-message"><?php echo $status_message; ?></p>
                                        <p class="date-info">Submitted on <span class="highlight-date"><?php echo isset($latest_attendance['created_at']) ? date('F j, Y', strtotime($latest_attendance['created_at'])) : date('F j, Y'); ?></span></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($today_overtime): ?>
                            <?php 
                            $status_color = '';
                            $status_border = '';
                            $message = '';
                            
                            if ($today_overtime['status'] === 'pending_approval') {
                                $status_color = '#d97706'; // amber
                                $status_border = '#fcd34d';
                                $message = 'Your overtime is <span style="color: #f59e0b; font-weight: 700;">pending admin approval</span>';
                            } elseif ($today_overtime['status'] === 'approved') {
                                $status_color = '#059669'; // green
                                $status_border = '#86efac';
                                $message = 'Your overtime has been <span style="color: #10b981; font-weight: 700;">approved</span>';
                            } elseif ($today_overtime['status'] === 'rejected') {
                                $status_color = '#dc2626'; // red
                                $status_border = '#fca5a5';
                                $message = 'Your overtime was <span style="color: #ef4444; font-weight: 700;">rejected</span>';
                            }
                            ?>
                            <div style="margin: 1.5rem 0; padding: 1rem; background: white; border: 2px solid <?php echo $status_border; ?>; border-radius: 12px;">
                                <div style="display: flex; align-items: flex-start; gap: 1rem;">
                                    <span style="font-size: 1.8rem; flex-shrink: 0;">ℹ️</span>
                                    <div>
                                        <p style="margin: 0; color: <?php echo $status_color; ?>; font-weight: 700; font-size: 1.1rem;">Overtime Status</p>
                                        <p style="margin: 0.5rem 0 0 0; color: <?php echo $status_color; ?>; font-weight: 600;">
                                            <?php echo $message; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="next-submission-info">
                            <div class="info-content-compact">
                                <span class="info-icon">🕐</span>
                                <div class="info-text">
                                    <?php 
                                    // Calculate next submission time based on submitted duration's reset time
                                    $current_hour = (int)date('H');
                                    
                                    if ($current_hour < $reset_hour) {
                                        $actual_calendar_date = date('Y-m-d');
                                        $next_date = date('F j, Y', strtotime($actual_calendar_date));
                                        $next_day_text = "today";
                                    } else {
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
                        <div class="footer-actions" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <?php if (($today_status === 'pending_approval' || $today_status === 'approved') && !$today_overtime): ?>
                                <!-- Show Add Overtime when attendance is pending or approved and no overtime submitted -->
                                <button type="button" class="btn btn-secondary btn-overtime" onclick="openOvertimeModal()" style="flex: 1;">
                                    <span class="btn-icon">⏱️</span>
                                    <span class="btn-text">Add Overtime</span>
                                </button>
                            <?php else: ?>
                                <!-- Show Return to Dashboard for rejected or other statuses, or if overtime already submitted -->
                                <a href="dashboard.php" class="btn btn-primary btn-dashboard" style="flex: 1;">
                                    <span class="btn-icon">🏠</span>
                                    <span class="btn-text">Return to Dashboard</span>
                                    <span class="btn-arrow">→</span>
                                </a>
                            <?php endif; ?>
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
                            <p style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.6); margin: 0;">✓ Click to upload</p>
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
            </div>
        <?php endif; ?>
    </div>

<script>
// Overtime time slots data (no longer needed - will be generated dynamically)

function openOvertimeModal() {
    const modal = document.getElementById('overtimeModal');
    
    // Show modal first with loading state
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Fetch latest attendance details
    fetch('<?php echo $_SERVER["REQUEST_URI"]; ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_attendance_endtime'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        console.log('Attendance data received:', data);
        if (data.success) {
            document.getElementById('attendance_id').value = data.attendance_id;
            const slotElement = document.getElementById('yourAttendanceSlot');
            if (slotElement) {
                slotElement.textContent = data.slot_display;
            }
            // Display duration in the info section
            const durationDisplay = document.getElementById('shiftDurationDisplay');
            if (durationDisplay) {
                durationDisplay.textContent = data.duration_hours + ' Hours';
            }
            // Store duration in hidden fields for form submission
            const durationField = document.getElementById('duration');
            if (durationField) {
                durationField.value = data.duration_hours;
            }
            document.getElementById('duration_value').value = data.duration_hours;
            // Trigger the updateOvertimeSlots to load the correct slots immediately
            updateOvertimeSlots();
        } else {
            console.error('Error from server:', data.message);
            document.getElementById('yourAttendanceSlot').textContent = 'Error: ' + (data.message || 'Unable to load');
            alert(data.message || 'Unable to load attendance details');
        }
    })
    .catch(err => {
        console.error('Fetch error:', err);
        document.getElementById('yourAttendanceSlot').textContent = 'Error loading data';
        alert('Error loading attendance details: ' + err.message);
    });
}

function updateTimeSlots() {
    const duration = document.getElementById('duration_choice').value;
    const slotSelect = document.getElementById('slot_id');
    
    if (!duration) {
        slotSelect.innerHTML = '<option value="">First select duration...</option>';
        slotSelect.disabled = true;
        return;
    }
    
    // Get time slots for selected duration from the page data
    const allSlots = <?php echo json_encode($available_slots); ?>;
    const filteredSlots = allSlots.filter(slot => parseInt(slot.duration_hours) === parseInt(duration));
    
    if (filteredSlots.length === 0) {
        slotSelect.innerHTML = '<option value="">No time slots available</option>';
        slotSelect.disabled = true;
        return;
    }
    
    slotSelect.innerHTML = '<option value="">Select a time slot...</option>';
    slotSelect.disabled = false;
    
    filteredSlots.forEach(slot => {
        const option = document.createElement('option');
        option.value = slot.id;
        const startTime = new Date('2000-01-01 ' + slot.start_time).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        const endTime = new Date('2000-01-01 ' + slot.end_time).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        option.textContent = slot.name + ' (' + startTime + ' - ' + endTime + ')';
        slotSelect.appendChild(option);
    });
}

// Attendance photo preview
document.addEventListener('DOMContentLoaded', function() {
    const photoInput = document.getElementById('sold_photo');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-image').src = e.target.result;
                    document.getElementById('photo-preview').style.display = 'block';
                    document.querySelector('.upload-placeholder').style.display = 'none';
                    const photoErrorEl = document.getElementById('photo-error');
                    if (photoErrorEl) { 
                        photoErrorEl.style.display = 'none'; 
                        photoErrorEl.textContent = ''; 
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
});

function removePhoto() {
    document.getElementById('sold_photo').value = '';
    document.getElementById('photo-preview').style.display = 'none';
    document.querySelector('.upload-placeholder').style.display = 'flex';
}

// Handle attendance form submission - populate hidden slot data
document.addEventListener('DOMContentLoaded', function() {
    const attendanceForm = document.querySelector('.simple-schedule-form');
    if (attendanceForm) {
        attendanceForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default first
            
            const slotSelect = document.getElementById('slot_id');
            const durationSelect = document.getElementById('duration_choice');
            const customSlotData = document.getElementById('custom_slot_data');
            const photoInput = document.getElementById('sold_photo');
            
            // Validate selections
            if (!durationSelect.value) {
                alert('Please select a duration');
                return false;
            }
            
            if (!slotSelect.value) {
                alert('Please select a time slot');
                return false;
            }
            
            if (!photoInput.files || photoInput.files.length === 0) {
                alert('Please upload your total sold photo before submitting');
                return false;
            }
            
            // Get selected slot data from available slots
            const allSlots = <?php echo json_encode($available_slots); ?>;
            const selectedSlot = allSlots.find(slot => slot.id == slotSelect.value);
            
            if (selectedSlot) {
                const slotData = {
                    duration: durationSelect.value,
                    start_time: selectedSlot.start_time,
                    end_time: selectedSlot.end_time,
                    name: selectedSlot.name
                };
                customSlotData.value = JSON.stringify(slotData);
                
                // Now submit the form after populating the hidden field
                this.submit();
            } else {
                alert('Invalid slot selection');
                return false;
            }
        });
    }
});

function updateOvertimeSlots() {
    const duration = document.getElementById('duration').value;
    const slotSelect = document.getElementById('overtime_time_slot');
    
    if (!duration) {
        slotSelect.innerHTML = '<option value="">Select a time slot...</option>';
        return;
    }
    
    // Fetch overtime slots based on auto-populated duration
    fetch('<?php echo $_SERVER["REQUEST_URI"]; ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_overtime_slots&duration=' + duration
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            slotSelect.innerHTML = '<option value="">Select a time slot...</option>';
            
            data.slots.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot.value;
                option.textContent = slot.text;
                slotSelect.appendChild(option);
            });
            
            const durationText = duration == 4 ? '4-Hour' : '3-Hour';
            document.getElementById('slot-note').textContent = 
                'Select a 2-hour overtime slot for your ' + durationText + ' shift';
        } else {
            alert(data.message || 'Unable to load time slots');
            slotSelect.innerHTML = '<option value="">Select a time slot...</option>';
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error loading time slots');
    });
}

function selectOvertimeSlot() {
    const slotSelect = document.getElementById('overtime_time_slot');
    const selectedValue = slotSelect.value;
    document.getElementById('overtime_slot').value = selectedValue;
}

function closeOvertimeModal() {
    const modal = document.getElementById('overtimeModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('overtimeForm').reset();
    resetOvertimeForm();
}

// Overtime photo preview
document.addEventListener('DOMContentLoaded', function() {
    const overtimePhotoInput = document.getElementById('overtime_photo');
    if (overtimePhotoInput) {
        overtimePhotoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('overtime_preview_image').src = e.target.result;
                    document.getElementById('overtime_photo_preview').style.display = 'block';
                    const placeholders = document.querySelectorAll('.overtime-form .upload-placeholder');
                    placeholders.forEach(p => p.style.display = 'none');
                    const photoErrorEl = document.getElementById('overtime_photo_error');
                    if (photoErrorEl) { 
                        photoErrorEl.style.display = 'none'; 
                        photoErrorEl.textContent = ''; 
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
});

function removeOvertimePhoto() {
    document.getElementById('overtime_photo').value = '';
    document.getElementById('overtime_photo_preview').style.display = 'none';
    const placeholders = document.querySelectorAll('.overtime-form .upload-placeholder');
    placeholders.forEach(p => p.style.display = 'flex');
}

function resetOvertimeForm() {
    document.getElementById('overtime_time_slot').value = '';
    document.getElementById('overtime_time_slot').innerHTML = '<option value="">Select a time slot...</option>';
    document.getElementById('overtime_solds').value = '';
    document.getElementById('overtime_photo').value = '';
    document.getElementById('overtime_photo_preview').style.display = 'none';
    const placeholders = document.querySelectorAll('.overtime-form .upload-placeholder');
    placeholders.forEach(p => p.style.display = 'flex');
    document.getElementById('yourAttendanceSlot').textContent = 'Loading...';
    document.getElementById('shiftDurationDisplay').textContent = 'Loading...';
    document.getElementById('slot-note').textContent = 'Select a 2-hour overtime slot';
}

// Handle overtime form submission
document.getElementById('overtimeForm').addEventListener('submit', function(e) {
    // Set the duration value in hidden field before submitting
    const duration = document.getElementById('duration').value;
    document.getElementById('duration_value').value = duration;
    
    const fileInput = document.getElementById('overtime_photo');
    const photoErrorEl = document.getElementById('overtime_photo_error');
    
    // Check if photo is selected
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        e.preventDefault();
        if (photoErrorEl) {
            photoErrorEl.textContent = 'Please upload your overtime proof photo before submitting.';
            photoErrorEl.style.display = 'block';
        }
        return false;
    } else {
        if (photoErrorEl) {
            photoErrorEl.style.display = 'none';
        }
    }
});

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('overtimeModal');
    if (e.target === modal) {
        closeOvertimeModal();
    }
});
</script>

<!-- Overtime Modal -->
<div id="overtimeModal" class="overtime-modal" style="display: none;">
    <div class="overtime-modal-content">
        <form id="overtimeForm" method="POST" enctype="multipart/form-data" class="overtime-form">
            <input type="hidden" name="action" value="add_overtime">
            <input type="hidden" id="overtime_slot" name="overtime_slot" value="">
            <input type="hidden" id="attendance_id" name="attendance_id" value="">
            <input type="hidden" id="duration_value" name="duration" value="">
            <input type="hidden" id="duration" value="">
            
            <div class="modal-body">
                <!-- Modal Header in Body -->
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 1.5rem; margin: -2rem -2rem 1.5rem -2rem; background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15)); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span style="font-size: 2rem;">⏱️</span>
                        <h2 style="margin: 0; font-size: 1.8rem; font-weight: 700; color: #ffffff;">Add Overtime</h2>
                    </div>
                    <button type="button" class="modal-close" onclick="closeOvertimeModal()" style="background: transparent; border: none; font-size: 2.5rem; color: rgba(255, 255, 255, 0.6); cursor: pointer; padding: 0; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 10px; transition: all 0.2s ease;">×</button>
                </div>

                <!-- Shift Duration Info Box -->
                <div class="form-group">
                    <label>Shift Duration:</label>
                    <div style="padding: 1rem; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; color: #ffffff; font-size: 1.1rem; font-weight: 600;">
                        <span id="shiftDurationDisplay">Loading...</span>
                    </div>
                </div>

                <!-- Attendance Info Section -->
                <div class="form-group">
                    <label>Your Attendance Shift:</label>
                    <div style="padding: 1rem; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; color: #ffffff; font-size: 1.1rem; font-weight: 600;">
                        <span id="yourAttendanceSlot">Loading...</span>
                    </div>
                </div>

                <!-- Time Slot Selection -->
                <div class="form-group">
                    <label for="overtime_time_slot" class="required">Overtime Time Slot (2 Hours):</label>
                    <p class="field-hint" id="slot-note">Select a 2-hour overtime slot</p>
                    <select id="overtime_time_slot" name="overtime_time_slot" required onchange="selectOvertimeSlot()">
                        <option value="">Select a time slot...</option>
                    </select>
                </div>

                <!-- Total Solds -->
                <div class="form-group">
                    <label for="overtime_solds">Total Solds:</label>
                    <input type="number" id="overtime_solds" name="overtime_solds" placeholder="Enter total solds during overtime" min="0" step="1">
                </div>

                <!-- Photo Upload -->
                <div class="form-group">
                    <label for="overtime_photo" class="required">📱 Overtime Proof Photo:</label>
                    <p class="field-hint">Upload photo showing your earnings/activity during overtime</p>
                    <div class="photo-upload-container">
                        <input type="file" id="overtime_photo" name="overtime_photo" accept="image/*" class="file-input">
                        <div class="upload-placeholder" onclick="document.getElementById('overtime_photo').click()">
                            <span class="upload-icon">📷</span>
                            <p>Upload your overtime proof photo</p>
                            <p style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.6); margin: 0;">✓ Click to upload</p>
                        </div>
                        <div id="overtime_photo_error" class="field-error" style="color:#ff6b6b; margin-top:8px; display: none;">
                        </div>
                        <div id="overtime_photo_preview" class="photo-preview" style="display: none;">
                            <img id="overtime_preview_image" src="" alt="Preview">
                            <button type="button" class="remove-photo" onclick="removeOvertimePhoto()">×</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOvertimeModal()">
                    <span class="btn-icon">✕</span>
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <span class="btn-icon">✓</span>
                    Proceed
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Overtime Modal CSS -->
<style>
.overtime-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    backdrop-filter: blur(2px);
    animation: fadeIn 0.3s ease-out;
}

.overtime-modal-content {
    background: #1a1a2e;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    border: 1px solid rgba(255, 255, 255, 0.1);
    animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 2rem;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15));
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    position: sticky;
    top: 0;
    z-index: 10;
}

.modal-title {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.modal-title::before {
    content: '⏱️';
    font-size: 2rem;
}

.modal-close {
    background: transparent;
    border: none;
    font-size: 2.5rem;
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    padding: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.modal-body {
    padding: 2rem;
}

.info-section {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem;
    background: rgba(102, 126, 234, 0.1);
    border: 1px solid rgba(102, 126, 234, 0.3);
    border-radius: 16px;
    margin-bottom: 2rem;
}

.section-icon {
    font-size: 2.5rem;
    flex-shrink: 0;
}

.section-content {
    flex: 1;
}

.section-label {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.section-value {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: #ffffff;
    background: rgba(255, 255, 255, 0.05);
    padding: 0.75rem 1rem;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.overtime-form .form-group {
    margin-bottom: 2rem;
}

.overtime-form label {
    display: block;
    margin-bottom: 0.75rem;
    font-size: 1rem;
    font-weight: 600;
    color: #ffffff;
}

.overtime-form label.required::after {
    content: ' *';
    color: #ff6b6b;
}

.field-hint {
    margin: -0.5rem 0 1rem 0;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    font-style: italic;
}

.overtime-form select,
.overtime-form input[type="number"] {
    width: 100%;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: #ffffff;
    font-size: 1rem;
    transition: all 0.3s ease;
    font-family: inherit;
}

.overtime-form select:focus,
.overtime-form input[type="number"]:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(102, 126, 234, 0.5);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.overtime-form select:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.overtime-form option {
    background: #1a1a2e;
    color: #ffffff;
}

.overtime-form .photo-upload-container {
    position: relative;
    border: 2px dashed rgba(102, 126, 234, 0.3);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.overtime-form .photo-upload-container:hover {
    border-color: rgba(102, 126, 234, 0.6);
    background: rgba(102, 126, 234, 0.05);
}

.overtime-form .upload-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    cursor: pointer;
    text-align: center;
}

.overtime-form .upload-icon {
    font-size: 3rem;
    display: block;
}

.overtime-form .upload-placeholder p {
    margin: 0;
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 500;
}

.overtime-form .btn-outline {
    background: transparent;
    border: 1.5px solid rgba(102, 126, 234, 0.5);
    color: #667eea;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.overtime-form .btn-outline:hover {
    background: rgba(102, 126, 234, 0.1);
    border-color: rgba(102, 126, 234, 0.8);
}

.overtime-form .photo-preview {
    position: relative;
    margin-top: 1rem;
    border-radius: 12px;
    overflow: hidden;
    max-height: 250px;
}

.overtime-form .photo-preview img {
    width: 100%;
    height: auto;
    display: block;
}

.overtime-form .remove-photo {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 36px;
    height: 36px;
    background: rgba(0, 0, 0, 0.7);
    border: none;
    border-radius: 50%;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.overtime-form .remove-photo:hover {
    background: rgba(0, 0, 0, 0.9);
    transform: scale(1.1);
}

.modal-footer {
    display: flex;
    gap: 1rem;
    padding: 2rem;
    background: transparent;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    justify-content: flex-end;
}

.modal-footer .btn {
    min-width: 120px;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.modal-footer .btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.8);
}

.modal-footer .btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff;
}

.modal-footer .btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.modal-footer .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.field-error {
    color: #ff6b6b;
    font-size: 0.9rem;
    margin-top: 0.5rem;
    display: none;
}

/* File input hidden */
.overtime-form .file-input {
    display: none;
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Scrollbar styling for modal */
.overtime-modal-content::-webkit-scrollbar {
    width: 8px;
}

.overtime-modal-content::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.overtime-modal-content::-webkit-scrollbar-thumb {
    background: rgba(102, 126, 234, 0.3);
    border-radius: 4px;
}

.overtime-modal-content::-webkit-scrollbar-thumb:hover {
    background: rgba(102, 126, 234, 0.5);
}

/* Responsive */
@media (max-width: 600px) {
    .overtime-modal-content {
        width: 95%;
        max-height: 95vh;
        border-radius: 16px;
    }
    
    .modal-header {
        padding: 1.5rem;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        flex-direction: column;
        padding: 1.5rem;
    }
    
    .modal-footer .btn {
        width: 100%;
        min-width: auto;
    }
    
    .modal-title {
        font-size: 1.5rem;
    }
    
    .overtime-form .photo-upload-container {
        padding: 1.2rem !important;
        border: 2px dashed rgba(102, 126, 234, 0.4) !important;
        min-height: 180px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
    }
    
    .overtime-form .upload-placeholder {
        gap: 0.75rem;
    }
    
    .overtime-form .upload-icon {
        font-size: 2.5rem;
    }
    
    .overtime-form .upload-placeholder p {
        font-size: 0.95rem;
        line-height: 1.4;
        margin: 0;
    }
    
    .overtime-form .photo-preview {
        max-height: 200px;
    }
    
    .overtime-form .remove-photo {
        width: 32px;
        height: 32px;
        font-size: 1.2rem;
    }
    
    .overtime-form .form-group {
        margin-bottom: 1.5rem;
    }
    
    .overtime-form label {
        font-size: 0.95rem;
        margin-bottom: 0.6rem;
    }
    
    .field-hint {
        font-size: 0.8rem;
        margin: -0.3rem 0 0.8rem 0;
    }
    
    .overtime-form select,
    .overtime-form input[type="number"] {
        padding: 0.85rem;
        font-size: 0.95rem;
    }
    
    /* Attendance form responsive styles */
    .photo-upload-container {
        padding: 1.2rem !important;
        border: 2px dashed rgba(102, 126, 234, 0.4) !important;
        min-height: 180px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .upload-placeholder {
        gap: 0.75rem;
    }
    
    .upload-icon {
        font-size: 2.5rem;
    }
    
    .upload-placeholder p {
        font-size: 0.95rem;
        line-height: 1.4;
    }
    
    .photo-preview {
        max-height: 200px;
    }
    
    .remove-photo {
        width: 32px;
        height: 32px;
        font-size: 1.2rem;
    }
}
</style>

<?php include 'layout/footer.php'; ?>