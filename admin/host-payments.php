<?php
require_once __DIR__ . '/../includes/functions.php';

// Require admin role
require_role('admin');

// Get current user info
$current_user = get_logged_in_user();
$db = getDB();

// Handle AJAX form submission
if (isset($_POST['ajax']) && $_POST['ajax'] === '1' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_payment') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_date = trim($_POST['payment_date'] ?? '');
        $reference_number = trim($_POST['reference_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $pay_period = trim($_POST['pay_period'] ?? '');
        
        // Parse pay period
        $pay_period_start = null;
        $pay_period_end = null;
        if (!empty($pay_period)) {
            list($pay_period_start, $pay_period_end) = explode('|', $pay_period);
        }
        
        // Validate inputs
        if ($user_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select a user.']);
            exit;
        } elseif ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid amount.']);
            exit;
        } elseif (empty($payment_date)) {
            echo json_encode(['success' => false, 'message' => 'Please select a payment date.']);
            exit;
        } elseif (empty($pay_period)) {
            echo json_encode(['success' => false, 'message' => 'Please select a pay period.']);
            exit;
        } elseif (!isset($_FILES['receipt_image']) || $_FILES['receipt_image']['error'] === UPLOAD_ERR_NO_FILE) {
            echo json_encode(['success' => false, 'message' => 'Please upload a receipt image.']);
            exit;
        }
        
        // Validate file upload
        $file = $_FILES['receipt_image'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Error uploading file. Please try again.']);
            exit;
        } elseif (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.']);
            exit;
        } elseif ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'File size too large. Maximum 5MB allowed.']);
            exit;
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/../uploads/payment_receipts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'receipt_' . $user_id . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Insert payment record
            $stmt = $db->prepare("
                INSERT INTO payment_receipts 
                (user_id, admin_id, amount, receipt_image, payment_date, reference_number, notes, pay_period_start, pay_period_end) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id, 
                $current_user['id'], 
                $amount, 
                $filename, 
                $payment_date, 
                $reference_number, 
                $notes,
                $pay_period_start,
                $pay_period_end
            ]);
            echo json_encode(['success' => true, 'message' => 'Payment receipt uploaded successfully!']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file. Please try again.']);
            exit;
        }
    }
}

// Handle AJAX request to calculate earnings
if (isset($_POST['action']) && $_POST['action'] === 'calculate_earnings') {
    header('Content-Type: application/json');
    
    try {
        $user_id = intval($_POST['user_id'] ?? 0);
        $pay_period = trim($_POST['pay_period'] ?? '');
        
        if ($user_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        
        if (empty($pay_period)) {
            echo json_encode(['success' => false, 'message' => 'Pay period is required']);
            exit;
        }
        
        // Parse pay period (format: YYYY-MM-DD|YYYY-MM-DD)
        $period_parts = explode('|', $pay_period);
        if (count($period_parts) !== 2) {
            echo json_encode(['success' => false, 'message' => 'Invalid pay period format. Expected: YYYY-MM-DD|YYYY-MM-DD']);
            exit;
        }
        list($period_start, $period_end) = $period_parts;
        
        // Validate date formats
        if (!strtotime($period_start) || !strtotime($period_end)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format in pay period']);
            exit;
        }
        
        // Get user's hourly rate
        $stmt = $db->prepare("SELECT hourly_rate, full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Calculate earnings from approved attendance + overtime (both hours × rate)
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN a.id IS NOT NULL THEN a.hours_worked ELSE 0 END), 0) +
                COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN o.duration_hours ELSE 0 END), 0) as total_hours,
                COUNT(DISTINCT a.attendance_date) as total_days
            FROM (SELECT ? as seller_id, ? as start_dt, ? as end_dt) filter_data
            LEFT JOIN attendance a ON a.seller_id = filter_data.seller_id 
                AND a.attendance_date BETWEEN filter_data.start_dt AND filter_data.end_dt AND a.status = 'approved'
            LEFT JOIN overtime o ON o.seller_id = filter_data.seller_id 
                AND o.overtime_date BETWEEN filter_data.start_dt AND filter_data.end_dt AND o.status = 'approved'
        ");
        $stmt->execute([$user_id, $period_start, $period_end]);
        $earnings = $stmt->fetch();
        
        $total_hours = floatval($earnings['total_hours'] ?? 0);
        $total_days = intval($earnings['total_days'] ?? 0);
        
        // Use hourly_rate from database (125 for newbie, 166 for tenured)
        $hourly_rate = floatval($user['hourly_rate'] ?? 166.00);
        
        // Calculate total earnings: hours × hourly rate only
        $total_earnings = $total_hours * $hourly_rate;
        
        echo json_encode([
            'success' => true,
            'user_name' => $user['full_name'],
            'total_hours' => $total_hours,
            'total_hours_formatted' => number_format($total_hours, 1),
            'total_days' => $total_days,
            'hourly_rate' => $hourly_rate,
            'hourly_rate_formatted' => number_format($hourly_rate, 0),
            'total_earnings' => round($total_earnings, 2),
            'total_earnings_formatted' => number_format($total_earnings, 0),
            'breakdown_text' => sprintf(
                '%.1f hours × ₱%s/hr = ₱%s',
                $total_hours,
                number_format($hourly_rate, 0),
                number_format($total_earnings, 0)
            )
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle form submission (kept for backwards compatibility but won't be used with AJAX)
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_payment') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_date = trim($_POST['payment_date'] ?? '');
        $reference_number = trim($_POST['reference_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $pay_period = trim($_POST['pay_period'] ?? '');
        
        // Parse pay period
        $pay_period_start = null;
        $pay_period_end = null;
        if (!empty($pay_period)) {
            list($pay_period_start, $pay_period_end) = explode('|', $pay_period);
        }
        
        // Validate inputs
        if ($user_id <= 0) {
            $error_message = 'Please select a user.';
        } elseif ($amount <= 0) {
            $error_message = 'Please enter a valid amount.';
        } elseif (empty($payment_date)) {
            $error_message = 'Please select a payment date.';
        } elseif (empty($pay_period)) {
            $error_message = 'Please select a pay period.';
        } elseif (!isset($_FILES['receipt_image']) || $_FILES['receipt_image']['error'] === UPLOAD_ERR_NO_FILE) {
            $error_message = 'Please upload a receipt image.';
        } else {
            // Validate file upload
            $file = $_FILES['receipt_image'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error_message = 'Error uploading file. Please try again.';
            } elseif (!in_array($file['type'], $allowed_types)) {
                $error_message = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
            } elseif ($file['size'] > $max_size) {
                $error_message = 'File size too large. Maximum 5MB allowed.';
            } else {
                // Create uploads directory if it doesn't exist
                $upload_dir = __DIR__ . '/../uploads/payment_receipts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'receipt_' . $user_id . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Insert payment record
                    $stmt = $db->prepare("
                        INSERT INTO payment_receipts 
                        (user_id, admin_id, amount, receipt_image, payment_date, reference_number, notes, pay_period_start, pay_period_end) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user_id, 
                        $current_user['id'], 
                        $amount, 
                        $filename, 
                        $payment_date, 
                        $reference_number, 
                        $notes,
                        $pay_period_start,
                        $pay_period_end
                    ]);
                    $success_message = 'Payment receipt uploaded successfully!';
                } else {
                    $error_message = 'Failed to save uploaded file. Please try again.';
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_payment') {
        $payment_id = intval($_POST['payment_id'] ?? 0);
        
        if ($payment_id > 0) {
            // Get payment record
            $stmt = $db->prepare("SELECT receipt_image FROM payment_receipts WHERE id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch();
            
            if ($payment) {
                // Delete file
                $upload_dir = __DIR__ . '/../uploads/payment_receipts/';
                $file_path = $upload_dir . $payment['receipt_image'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // Delete record
                $stmt = $db->prepare("DELETE FROM payment_receipts WHERE id = ?");
                $stmt->execute([$payment_id]);
                $success_message = 'Payment receipt deleted successfully!';
            }
        }
    }
}

// Function to generate pay periods (current month and previous month)
function generate_pay_periods() {
    $periods = [];
    $current_date = new DateTime();
    
    // Generate periods for previous month
    $prev_date = clone $current_date;
    $prev_date->modify('-1 month');
    
    $prev_year = $prev_date->format('Y');
    $prev_month = $prev_date->format('m');
    $prev_month_name = $prev_date->format('F');
    $prev_last_day = $prev_date->format('t');
    
    // Previous month - First period: 1st to 15th
    $periods[] = [
        'value' => "$prev_year-$prev_month-01|$prev_year-$prev_month-15",
        'label' => "$prev_month_name 1-15, $prev_year"
    ];
    
    // Previous month - Second period: 16th to last day
    $periods[] = [
        'value' => "$prev_year-$prev_month-16|$prev_year-$prev_month-$prev_last_day",
        'label' => "$prev_month_name 16-$prev_last_day, $prev_year"
    ];
    
    // Generate periods for current month
    $year = $current_date->format('Y');
    $month = $current_date->format('m');
    $month_name = $current_date->format('F');
    $last_day = $current_date->format('t');
    
    // Current month - First period: 1st to 15th
    $periods[] = [
        'value' => "$year-$month-01|$year-$month-15",
        'label' => "$month_name 1-15, $year"
    ];
    
    // Current month - Second period: 16th to last day
    $periods[] = [
        'value' => "$year-$month-16|$year-$month-$last_day",
        'label' => "$month_name 16-$last_day, $year"
    ];
    
    return $periods;
}

$pay_periods = generate_pay_periods();

// Get selected pay period from request (if filtering)
$selected_period = $_GET['filter_period'] ?? '';
$filter_start = null;
$filter_end = null;
if (!empty($selected_period)) {
    list($filter_start, $filter_end) = explode('|', $selected_period);
}

// Fetch all users with GCash QR codes
// If a pay period is selected, exclude users who already have payments for that period
if ($filter_start && $filter_end) {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.username,
            u.experienced_status,
            g.gcash_number,
            g.gcash_name,
            g.qr_code_image
        FROM users u
        INNER JOIN gcash_qr_codes g ON u.id = g.user_id AND g.is_active = 1
        WHERE u.role = 'live_seller' 
            AND u.status = 'active'
            AND u.id NOT IN (
                SELECT user_id 
                FROM payment_receipts 
                WHERE pay_period_start = ? 
                    AND pay_period_end = ?
                    AND status = 'completed'
            )
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$filter_start, $filter_end]);
    $users_with_gcash = $stmt->fetchAll();
} else {
    // No filter - show all users
    $stmt = $db->query("
        SELECT 
            u.id,
            u.full_name,
            u.username,
            u.experienced_status,
            g.gcash_number,
            g.gcash_name,
            g.qr_code_image
        FROM users u
        INNER JOIN gcash_qr_codes g ON u.id = g.user_id AND g.is_active = 1
        WHERE u.role = 'live_seller' AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $users_with_gcash = $stmt->fetchAll();
}

// Get filter parameters for payment history
$filter_history_year = $_GET['history_year'] ?? '';
$filter_history_month = $_GET['history_month'] ?? '';
$filter_history_period = $_GET['history_period'] ?? '';

// Build the query for payment records with filters
$where_conditions = [];
$params = [];

if (!empty($filter_history_year)) {
    $where_conditions[] = "YEAR(pr.payment_date) = ?";
    $params[] = $filter_history_year;
}

if (!empty($filter_history_month)) {
    $where_conditions[] = "MONTH(pr.payment_date) = ?";
    $params[] = $filter_history_month;
}

if (!empty($filter_history_period)) {
    list($period_start, $period_end) = explode('|', $filter_history_period);
    $where_conditions[] = "pr.pay_period_start = ? AND pr.pay_period_end = ?";
    $params[] = $period_start;
    $params[] = $period_end;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Fetch payment records with filters
$query = "
    SELECT 
        pr.*,
        u.full_name as user_name,
        u.username,
        u.experienced_status,
        a.full_name as admin_name
    FROM payment_receipts pr
    JOIN users u ON pr.user_id = u.id
    JOIN users a ON pr.admin_id = a.id
    $where_clause
    ORDER BY pr.payment_date DESC, pr.created_at DESC
";

if (!empty($params)) {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
} else {
    $stmt = $db->query($query);
    $payments = $stmt->fetchAll();
}

// Get unique years and months from all payments for filter dropdowns
$all_payments_query = $db->query("
    SELECT DISTINCT 
        YEAR(payment_date) as year,
        MONTH(payment_date) as month,
        pay_period_start,
        pay_period_end
    FROM payment_receipts
    ORDER BY year DESC, month DESC
");
$all_payment_data = $all_payments_query->fetchAll();

$available_years = array_unique(array_column($all_payment_data, 'year'));
$available_months = [];
if (!empty($filter_history_year)) {
    foreach ($all_payment_data as $data) {
        if ($data['year'] == $filter_history_year) {
            $available_months[] = $data['month'];
        }
    }
    $available_months = array_unique($available_months);
    sort($available_months);
}

$available_periods = [];
if (!empty($filter_history_year) && !empty($filter_history_month)) {
    foreach ($all_payment_data as $data) {
        if ($data['year'] == $filter_history_year && $data['month'] == $filter_history_month) {
            if (!empty($data['pay_period_start']) && !empty($data['pay_period_end'])) {
                $period_value = $data['pay_period_start'] . '|' . $data['pay_period_end'];
                $start_date = new DateTime($data['pay_period_start']);
                $end_date = new DateTime($data['pay_period_end']);
                $period_label = $start_date->format('F j') . ' - ' . $end_date->format('j, Y');
                $available_periods[$period_value] = $period_label;
            }
        }
    }
}

// Calculate statistics
$total_payments = count($payments);
$total_amount = array_sum(array_column($payments, 'amount'));
$unique_users = count(array_unique(array_column($payments, 'user_id')));

$page_title = 'Payment History';
include 'layout/header.php';
?>

<div class="payments-container">
    <div class="page-header">
        <h1>📋 Payment History</h1>
        <p>View uploaded payment receipts and history</p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✓</span>
            <span><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <span class="alert-icon">✕</span>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <!-- Account Payment Overview -->
    <div class="accounts-overview-card">
        <div class="card-header-section">
            <div class="header-left">
                <h2>🏷️ Account Payment Overview</h2>
            </div>
            <div class="inline-filter">
                <label for="account_year">Pay Period:</label>
                <form method="GET" id="accountPeriodForm">
                    <?php
                    // Derive available years and months from generated pay periods
                    $years = [];
                    $months = [];
                    foreach ($pay_periods as $pp) {
                        // value format: YYYY-MM-DD|YYYY-MM-DD
                        $parts = explode('|', $pp['value']);
                        if (count($parts) >= 1) {
                            $start = $parts[0];
                            $dt = DateTime::createFromFormat('Y-m-d', $start);
                            if ($dt) {
                                $y = $dt->format('Y');
                                $m = $dt->format('m');
                                $years[$y] = true;
                                $months[$m] = $dt->format('F');
                            }
                        }
                    }
                    krsort($years);
                    ?>

                    <select id="account_year" name="account_year" onchange="onAccountPeriodChange()">
                        <option value="">Year</option>
                        <?php foreach (array_keys($years) as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo (isset($_GET['account_year']) && $_GET['account_year']==$y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="account_month" name="account_month" onchange="onAccountPeriodChange()" <?php echo empty($_GET['account_year']) ? 'disabled' : ''; ?>>
                        <option value="">Month</option>
                        <?php foreach ($months as $mnum => $mname): ?>
                            <option value="<?php echo $mnum; ?>" <?php echo (isset($_GET['account_month']) && $_GET['account_month']==$mnum) ? 'selected' : ''; ?>><?php echo $mname; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                    // Determine a sensible label for the '16-end' cutoff using selected year/month or current month
                    $cutoff_label_end = '16-end';
                    $sel_year = $_GET['account_year'] ?? '';
                    $sel_month = $_GET['account_month'] ?? '';
                    if (!empty($sel_year) && !empty($sel_month)) {
                        // Ensure month is two digits
                        $mm = str_pad($sel_month, 2, '0', STR_PAD_LEFT);
                        $dt_last = DateTime::createFromFormat('Y-m-d', "$sel_year-$mm-01");
                        if ($dt_last) {
                            $lastDay = $dt_last->format('t');
                            $cutoff_label_end = '16-' . $lastDay;
                        }
                    } else {
                        // Fallback to current month
                        $now = new DateTime();
                        $cutoff_label_end = '16-' . $now->format('t');
                    }
                    ?>
                    <select id="account_cutoff" name="account_cutoff" onchange="onAccountPeriodChange()" <?php echo empty($_GET['account_month']) ? 'disabled' : ''; ?>>
                        <option value="">Cut Off</option>
                        <option value="1" <?php echo (isset($_GET['account_cutoff']) && $_GET['account_cutoff']=='1') ? 'selected' : ''; ?>>1-15</option>
                        <option value="2" <?php echo (isset($_GET['account_cutoff']) && $_GET['account_cutoff']=='2') ? 'selected' : ''; ?>><?php echo htmlspecialchars($cutoff_label_end); ?></option>
                    </select>

                    <input type="hidden" id="filter_period" name="filter_period" value="<?php echo isset($_GET['filter_period']) ? htmlspecialchars($_GET['filter_period']) : ''; ?>">
                </form>
            </div>
        </div>

        <?php
        // Prepare account overview data only if a period is selected
        $accounts_overview = [];
        if (!empty($filter_start) && !empty($filter_end)) {
            // Fetch accounts
            $accStmt = $db->query("SELECT id, name FROM accounts ORDER BY name ASC");
            $accounts = $accStmt->fetchAll();

            foreach ($accounts as $acc) {
                // Get members
                $mStmt = $db->prepare("SELECT user_id FROM account_members WHERE account_id = ?");
                $mStmt->execute([$acc['id']]);
                $members = $mStmt->fetchAll(PDO::FETCH_COLUMN);

                $memberCount = count($members);
                $totalPaycheck = 0.0;
                $paidAmount = 0.0;
                $paidCount = 0;

                if ($memberCount > 0) {
                    // Calculate total earnings for members using attendance and hourly_rate
                    $in_placeholders = implode(',', array_fill(0, count($members), '?'));
                    // Build parameters: period_start, period_end, then member ids
                    $params = array_merge([$filter_start, $filter_end], $members);

                    $sql = "SELECT u.id, u.hourly_rate, 
                                COALESCE(SUM(a.hours_worked),0) + COALESCE(SUM(o.duration_hours),0) as total_hours
                            FROM users u
                            LEFT JOIN attendance a ON a.seller_id = u.id AND a.attendance_date BETWEEN ? AND ? AND a.status = 'approved'
                            LEFT JOIN overtime o ON o.seller_id = u.id AND o.overtime_date BETWEEN ? AND ? AND o.status = 'approved'
                            WHERE u.id IN ($in_placeholders)
                            GROUP BY u.id";
                    $stmt2 = $db->prepare($sql);
                    // Add period dates twice (once for attendance, once for overtime)
                    $params_with_overtime = array_merge([$filter_start, $filter_end, $filter_start, $filter_end], $members);
                    $stmt2->execute($params_with_overtime);
                    $rows = $stmt2->fetchAll();
                    foreach ($rows as $r) {
                        $hours = floatval($r['total_hours'] ?? 0);
                        $rate = floatval($r['hourly_rate'] ?? 0);
                        $totalPaycheck += $hours * $rate;
                    }

                    // Get payments made for these members in the period
                    $payParams = $members;
                    $payParams[] = $filter_start;
                    $payParams[] = $filter_end;
                    $in_placeholders2 = implode(',', array_fill(0, count($members), '?'));
                    $sqlPaid = "SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as paid_sum
                                FROM payment_receipts
                                WHERE user_id IN ($in_placeholders2)
                                  AND pay_period_start = ?
                                  AND pay_period_end = ?
                                  AND status = 'completed'";
                    $stmt3 = $db->prepare($sqlPaid);
                    $stmt3->execute($payParams);
                    $paidRow = $stmt3->fetch();
                    $paidCount = intval($paidRow['cnt'] ?? 0);
                    $paidAmount = floatval($paidRow['paid_sum'] ?? 0);
                }

                $accounts_overview[] = [
                    'id' => $acc['id'],
                    'name' => $acc['name'],
                    'members' => $memberCount,
                    'total_paycheck' => round($totalPaycheck, 2),
                    'paid_amount' => round($paidAmount, 2),
                    'paid_count' => $paidCount
                ];
            }
        }
        ?>

        <?php if (empty($filter_start) || empty($filter_end)): ?>
            <div class="empty-state">
                <p>Select a pay period above to view account-level pay totals.</p>
            </div>
        <?php else: ?>
            <div class="accounts-grid">
                <?php foreach ($accounts_overview as $ao): ?>
                    <?php $remaining = max(0, $ao['total_paycheck'] - $ao['paid_amount']); ?>
                    <div class="account-card">
                        <div class="account-card-header">
                            <div class="account-name"><?php echo htmlspecialchars($ao['name']); ?></div>
                            <div class="account-members">Members: <?php echo $ao['members']; ?></div>
                        </div>
                        <div class="account-stats">
                            <div class="stat-box">
                                <div class="stat-box-label">TOTAL PAYCHECK:</div>
                                <div class="stat-box-value">₱<?php echo number_format($ao['total_paycheck'], 2); ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-box-label">PAID:</div>
                                <div class="stat-box-value">₱<?php echo number_format($ao['paid_amount'], 2); ?> <span class="stat-box-count">(<?php echo $ao['paid_count']; ?>)</span></div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-box-label">REMAINING:</div>    
                                <div class="stat-box-value">₱<?php echo number_format($remaining, 2); ?></div>
                            </div>
                        </div>
                        <div class="account-progress">
                            <?php $percent = ($ao['total_paycheck'] > 0) ? min(100, round(($ao['paid_amount'] / $ao['total_paycheck']) * 100)) : 0; ?>
                            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div></div>
                            <div class="progress-text"><?php echo $percent; ?>% paid</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

        <script>
        function onAccountPeriodChange() {
            var yearEl = document.getElementById('account_year');
            var monthEl = document.getElementById('account_month');
            var cutoffEl = document.getElementById('account_cutoff');
            var filterInput = document.getElementById('filter_period');
            if (!yearEl || !monthEl || !cutoffEl || !filterInput) return;

            var y = yearEl.value;
            var m = monthEl.value;
            var cutoff = cutoffEl.value;

            // Enable/disable dependent selects
            monthEl.disabled = !y;
            if (!y) {
                monthEl.value = '';
            }
            cutoffEl.disabled = !m;
            if (!m) {
                cutoffEl.value = '';
            }

            // Update cutoff label to show actual month end
            updateCutoffLabel();

            if (!y || !m || !cutoff) {
                // clear filter if incomplete
                filterInput.value = '';
                return;
            }

            var mm = m.length === 1 ? '0' + m : m;
            var lastDay = new Date(parseInt(y,10), parseInt(mm,10), 0).getDate();
            var start, end;
            if (cutoff === '1') {
                start = y + '-' + mm + '-01';
                end = y + '-' + mm + '-15';
            } else {
                start = y + '-' + mm + '-16';
                end = y + '-' + mm + '-' + lastDay;
            }

            filterInput.value = start + '|' + end;

            // Build new URL preserving other query params
            var params = new URLSearchParams(window.location.search);
            if (filterInput.value) {
                params.set('filter_period', filterInput.value);
                params.set('account_year', y);
                params.set('account_month', m);
                params.set('account_cutoff', cutoff);
            } else {
                params.delete('filter_period');
                params.delete('account_year');
                params.delete('account_month');
                params.delete('account_cutoff');
            }
            var newUrl = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');

            // Fetch and replace the accounts overview section via AJAX
            fetchAndReplaceSection(newUrl, '.accounts-overview-card');
            // Update browser URL
            history.pushState(null, '', newUrl);
        }

        document.addEventListener('DOMContentLoaded', function() {
            var filterInput = document.getElementById('filter_period');
            if (!filterInput) return;
            var filterVal = filterInput.value || '';
            if (!filterVal) return;
            var parts = filterVal.split('|');
            if (parts.length !== 2) return;
            var start = parts[0];
            var p = start.split('-');
            if (p.length < 3) return;
            var y = p[0];
            var m = p[1];
            var d = p[2];
            var cutoff = (parseInt(d,10) <= 15) ? '1' : '2';
            var yearEl = document.getElementById('account_year');
            var monthEl = document.getElementById('account_month');
            var cutoffEl = document.getElementById('account_cutoff');
            if (yearEl) yearEl.value = y;
            if (monthEl) monthEl.value = m;
            if (cutoffEl) cutoffEl.value = cutoff;
            // Ensure cutoff label matches selected month/year on load
            updateCutoffLabel();
        });

        function updateCutoffLabel() {
            var yearEl = document.getElementById('account_year');
            var monthEl = document.getElementById('account_month');
            var cutoffEl = document.getElementById('account_cutoff');
            if (!cutoffEl) return;

            var y = yearEl ? yearEl.value : '';
            var m = monthEl ? monthEl.value : '';

            var optionEnd = cutoffEl.querySelector('option[value="2"]');
            if (!optionEnd) return;

            if (!y || !m) {
                optionEnd.textContent = '16-end';
                return;
            }

            // Ensure month is two digits
            var mm = String(m).padStart(2, '0');
            var lastDay = new Date(parseInt(y,10), parseInt(mm,10), 0).getDate();
            optionEnd.textContent = '16-' + lastDay;
        }
        </script>

    <!-- Upload form removed per request -->

    <!-- Payment History -->
    <div class="history-card">
        <div class="history-header-section">
            <h2>📋 Users Payment Overview</h2>  
            
            <!-- History Filters -->
            <form method="GET" id="historyFilterForm" class="history-filters">
                <!-- Preserve pay period form filter if exists -->
                <?php if (!empty($_GET['filter_period'])): ?>
                    <input type="hidden" name="filter_period" value="<?php echo htmlspecialchars($_GET['filter_period']); ?>">
                <?php endif; ?>
                
                <div class="filter-wrapper">
                    <label class="filter-main-label"> Filter by:</label>
                    <div class="filter-controls">
                        <select id="history_year" name="history_year" onchange="updateHistoryFilters()">
                            <option value="">All Years</option>
                            <?php foreach ($available_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($filter_history_year == $year) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select id="history_month" name="history_month" onchange="updateHistoryFilters()" 
                                <?php echo empty($filter_history_year) ? 'disabled' : ''; ?>>
                            <option value="">All Months</option>
                            <?php if (!empty($available_months)): ?>
                                <?php 
                                $month_names = ['', 'January', 'February', 'March', 'April', 'May', 'June', 
                                              'July', 'August', 'September', 'October', 'November', 'December'];
                                foreach ($available_months as $month): 
                                ?>
                                    <option value="<?php echo $month; ?>" <?php echo ($filter_history_month == $month) ? 'selected' : ''; ?>>
                                        <?php echo $month_names[$month]; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        
                        <select id="history_period" name="history_period" onchange="this.form.submit()" 
                                <?php echo (empty($filter_history_year) || empty($filter_history_month)) ? 'disabled' : ''; ?>>
                            <option value="">All Periods</option>
                            <?php foreach ($available_periods as $period_value => $period_label): ?>
                                <option value="<?php echo htmlspecialchars($period_value); ?>" 
                                        <?php echo ($filter_history_period == $period_value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($period_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if (!empty($filter_history_year) || !empty($filter_history_month) || !empty($filter_history_period)): ?>
                            <a href="?<?php echo !empty($_GET['filter_period']) ? 'filter_period=' . urlencode($_GET['filter_period']) : ''; ?>" class="btn-clear-history-filter" title="Clear filters">
                                ✕
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <div class="empty-icon">💳</div>
                <p>No payment records found</p>
                <small><?php echo (!empty($filter_history_year) || !empty($filter_history_month) || !empty($filter_history_period)) ? 'Try adjusting your filters' : 'Upload your first payment receipt above'; ?></small>
            </div>
        <?php else: ?>
            <div class="payments-table-container">
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-cell-name"><?php echo htmlspecialchars($payment['user_name']); ?></div>
                                        <div class="user-cell-username">@<?php echo htmlspecialchars($payment['username']); ?></div>
                                    </div>
                                </td>
                                <td class="amount-cell">₱<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo $payment['reference_number'] ? htmlspecialchars($payment['reference_number']) : '-'; ?></td>
                                <td>
                                    <button class="btn-view-receipt" 
                                            onclick="viewReceipt('<?php echo htmlspecialchars($payment['receipt_image']); ?>', '<?php echo htmlspecialchars($payment['user_name']); ?>')">
                                        View Receipt
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for viewing receipt -->
<div id="receiptModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h3 id="modalTitle"></h3>
        <img id="modalImage" src="" alt="Receipt">
    </div>
</div>

<!-- Delete Confirmation Form (hidden) -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete_payment">
    <input type="hidden" name="payment_id" id="deletePaymentId">
</form>

<style>
.payments-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.page-header {
    text-align: center;
    margin-bottom: 2rem;
}

.page-header h1 {
    font-size: 2.5rem;
    color:whitesmoke;
    margin-bottom: 0.5rem;
}

.page-header p {
    color: whitesmoke;
    font-size: 1.125rem;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease-out;
    transition: opacity 0.3s ease;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.alert-icon {
    font-size: 1.5rem;
    font-weight: bold;
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.form-card,
.history-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    margin-bottom: 2rem;
}

.card-header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
    gap: 1rem;
    flex-wrap: wrap;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    flex: 1;
}

.card-header-section h2 {
    font-size: 1.5rem;
    color: #2d3748;
    margin: 0;
}

.inline-filter {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.inline-filter label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #4b5563;
    white-space: nowrap;
}

.inline-filter select {
    padding: 0.5rem 0.75rem;
    border: 1px solid #cbd5e0;
    border-radius: 6px;
    font-size: 0.875rem;
    color: #2d3748;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 180px;
}

.inline-filter select:hover {
    border-color: #667eea;
}

.inline-filter select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.clear-filter {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: #ef4444;
    color: white;
    text-decoration: none;
    border-radius: 50%;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.clear-filter:hover {
    background: #dc2626;
    transform: scale(1.1);
}

.user-count {
    color: #718096;
    font-size: 0.8rem;
    font-weight: 500;
}

.btn-toggle {
    padding: 0.5rem 1rem;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-toggle:hover {
    background: #5568d3;
}

.payment-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-size: 0.95rem;
    font-weight: 600;
    color: #2d3748;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group input[type="date"],
.form-group select,
.form-group textarea {
    padding: 0.75rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.gcash-info {
    padding: 0.5rem;
    background: #f0fdf4;
    border-radius: 6px;
    margin-top: 0.5rem;
}

.gcash-info small {
    color: #065f46;
}

.btn-view-qr {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding: 0.75rem 1.25rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
}

.btn-view-qr:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
}

.btn-view-qr:active {
    transform: translateY(0);
}

.qr-icon {
    font-size: 1.2rem;
}

.file-upload-wrapper {
    position: relative;
}

.file-upload-wrapper input[type="file"] {
    position: absolute;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}

.file-upload-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 2rem;
    border: 2px dashed #cbd5e0;
    border-radius: 10px;
    background: #f7fafc;
    transition: all 0.3s ease;
}

.file-upload-wrapper:hover .file-upload-info {
    border-color: #667eea;
    background: #edf2f7;
}

.upload-icon {
    font-size: 2.5rem;
}

.upload-text {
    font-size: 1rem;
    color: #2d3748;
    font-weight: 500;
}

.upload-hint {
    font-size: 0.875rem;
    color: #718096;
}

.image-preview {
    margin-top: 1rem;
    text-align: center;
}

.image-preview img {
    max-width: 300px;
    height: auto;
    border-radius: 10px;
    border: 2px solid #e2e8f0;
}

.earnings-breakdown {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    border: 2px solid #10b981;
    border-radius: 10px;
    margin-top: 0.75rem;
    animation: slideIn 0.3s ease-out;
}

.breakdown-icon {
    font-size: 2rem;
    line-height: 1;
}

.breakdown-details {
    flex: 1;
}

.breakdown-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #065f46;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.breakdown-text {
    font-size: 0.95rem;
    font-weight: 600;
    color: #047857;
    margin-bottom: 0.5rem;
}

.breakdown-stats {
    font-size: 0.8rem;
    color: #059669;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.breakdown-stats span,
.breakdown-stat {
    background: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
}

.earnings-loading {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: #f0f9ff;
    border-radius: 8px;
    margin-top: 0.5rem;
    color: #0369a1;
    font-size: 0.875rem;
}

.spinner-small {
    border: 2px solid #e0f2fe;
    border-top: 2px solid #0369a1;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    animation: spin 1s linear infinite;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 1rem;
}

.btn-submit,
.btn-reset {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-submit {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-submit:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-reset {
    background: #9ca3af;
    color: white;
}

.btn-reset:hover {
    background: #6b7280;
}

.history-card h2 {
    font-size: 1.5rem;
    color: #2d3748;
    margin: 0;
}

.history-header-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
    gap: 1rem;
}

.history-filters {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.filter-wrapper {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.filter-main-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #2d3748;
    white-space: nowrap;
}

.filter-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-controls select {
    padding: 0.4rem 0.6rem;
    border: 1px solid #cbd5e0;
    border-radius: 6px;
    font-size: 0.8rem;
    color: #2d3748;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 100px;
}

.filter-controls select:hover:not(:disabled) {
    border-color: #667eea;
}

.filter-controls select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-controls select:disabled {
    background: #f7fafc;
    cursor: not-allowed;
    opacity: 0.6;
}

.btn-clear-history-filter {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: #ef4444;
    color: white;
    text-decoration: none;
    border-radius: 50%;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-clear-history-filter:hover {
    background: #dc2626;
    transform: scale(1.1);
}

.results-count {
    color: #718096;
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
    padding: 0.4rem 0.75rem;
    background: #f7fafc;
    border-radius: 6px;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-state p {
    font-size: 1.25rem;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.empty-state small {
    color: #718096;
}

.payments-table-container {
    overflow-x: auto;
}

.payments-table {
    width: 100%;
    border-collapse: collapse;
}

/* Accounts overview styles */
.accounts-overview-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.06);
    margin-bottom: 1.5rem;
}
.accounts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}
.account-card {
    background: #f8fafc;
    border: 1px solid #e6edf3;
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    min-height: 180px;
    position: relative;
    box-sizing: border-box;
    overflow: visible;
    box-shadow: 0 6px 18px rgba(15,23,42,0.06);
}
.account-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.account-card-header .account-name { font-size: 1.05rem; }
.account-name {
    font-weight: 700;
    color: #111827;
}
.account-members {
    font-size: 0.85rem;
    color: #6b7280;
}
.account-stats {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    align-items: stretch;
}
.account-stats, .account-stats > * { min-width: 0; }
.stat-box {
    background: white;
    border-radius: 12px;
    padding: 0.85rem 1rem;
    border: 1px solid #eef2f7;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    box-sizing: border-box;
    min-height: 80px;
    align-items: flex-start;
    justify-content: center;
    box-shadow: 0 4px 10px rgba(15,23,42,0.04);
}
.stat-box-label {
    font-size: 0.78rem;
    color: #6b7280;
    font-weight: 800;
    text-transform: uppercase;
}
.stat-box-value {
    font-size: 1.48rem;
    font-weight: 900;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.05;
}
.stat-box .stat-box-count { opacity: 0.9; }
.stat-box-count { display:block; font-size:0.9rem; color:#6b7280; font-weight:700; margin-top:6px; }
.account-progress { display:flex; flex-direction:column; gap:0.35rem; margin-top:0.75rem; }
.progress-bar { background:#e6eef8; height:12px; border-radius:8px; overflow:hidden; }
.progress-fill { height:100%; background:linear-gradient(90deg,#60a5fa,#7c3aed); border-radius:8px; }
.progress-text { font-size:0.85rem; color:#475569; font-weight:600; }

@media (max-width: 640px) {
    .accounts-grid {
        grid-template-columns: 1fr;
    }
    .account-card {
        padding: 0.75rem;
    }
    .account-stats {
        grid-template-columns: 1fr;
    }
    .stat-box { padding: 0.5rem; }
}

.payments-table thead {
    background: #f7fafc;
}

.payments-table th {
    padding: 1rem;
    text-align: left;
    font-size: 0.875rem;
    font-weight: 700;
    color: #2d3748;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
}

.payments-table td {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
    color: #2d3748;
}

.user-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.user-cell-name {
    font-weight: 600;
    color: #000000;
}

.user-cell-username {
    font-size: 0.875rem;
    color: #4b5563;
}

.amount-cell {
    font-weight: 700;
    color: #10b981;
    font-size: 1.1rem;
}

.btn-view-receipt {
    padding: 0.5rem 1rem;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-view-receipt:hover {
    background: #5568d3;
    transform: translateY(-2px);
}

.btn-delete-small {
    padding: 0.5rem;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-delete-small:hover {
    background: #dc2626;
    transform: scale(1.1);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    animation: fadeIn 0.3s;
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 2rem;
    border-radius: 16px;
    max-width: 600px;
    text-align: center;
    position: relative;
    animation: slideDown 0.3s;
}

.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 2rem;
    font-weight: bold;
    color: #718096;
    cursor: pointer;
    transition: color 0.3s;
}

.modal-close:hover {
    color: #2d3748;
}

#modalTitle {
    font-size: 1.5rem;
    color: #2d3748;
    margin-bottom: 1rem;
}

#modalImage {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .payments-container {
        padding: 1rem;
    }
    
    .card-header-section {
        flex-direction: column;
        align-items: stretch;
    }
    
    .header-left {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .inline-filter {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    
    .inline-filter label {
        font-size: 0.875rem;
    }
    
    .inline-filter select {
        width: 100%;
    }
    
    .user-count {
        text-align: center;
        padding: 0.5rem;
        background: #f7fafc;
        border-radius: 6px;
    }
    
    .btn-toggle {
        width: 100%;
    }
    
    .form-card,
    .history-card {
        padding: 1rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn-submit,
    .btn-reset {
        width: 100%;
        justify-content: center;
    }
    
    .history-header-section {
        flex-direction: column;
        align-items: stretch;
    }
    
    .history-filters {
        width: 100%;
    }
    
    .filter-wrapper {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    
    .filter-main-label {
        font-size: 0.8rem;
    }
    
    .filter-controls {
        flex-wrap: wrap;
        width: 100%;
    }
    
    .filter-controls select {
        flex: 1;
        min-width: 90px;
    }
    
    .btn-clear-history-filter {
        width: 32px;
        height: 32px;
    }
    
    .results-count {
        text-align: center;
        width: 100%;
    }
    
    .payments-table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .payments-table {
        min-width: 600px;
    }
    
    .payments-table th,
    .payments-table td {
        padding: 0.75rem 0.5rem;
        font-size: 0.875rem;
    }
}

@media (max-width: 480px) {
    .page-header h1 {
        font-size: 1.75rem;
    }
    
    .page-header p {
        font-size: 1rem;
    }
    
    .card-header-section h2 {
        font-size: 1.25rem;
    }
    
    .form-card,
    .history-card {
        padding: 0.75rem;
        border-radius: 12px;
    }
    
    .btn-toggle {
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
    }
    
    .payments-table {
        min-width: 500px;
    }
}
</style>

<script>
// Handle payment form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    const paymentForm = document.getElementById('paymentForm');
    
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('ajax', '1');
            
            const submitBtn = this.querySelector('.btn-submit');
            const originalBtnText = submitBtn.innerHTML;
            
            // Disable submit button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="btn-icon">⏳</span> Uploading...';
            
            // Remove any existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                // Create alert message
                const alertDiv = document.createElement('div');
                alertDiv.className = data.success ? 'alert alert-success' : 'alert alert-error';
                alertDiv.innerHTML = `
                    <span class="alert-icon">${data.success ? '✓' : '✕'}</span>
                    <span>${data.message}</span>
                `;
                
                // Insert alert before the form card
                const formCard = document.querySelector('.form-card');
                formCard.parentNode.insertBefore(alertDiv, formCard);
                
                // Scroll to alert
                alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                if (data.success) {
                    // Reset form
                    paymentForm.reset();
                    document.getElementById('imagePreview').style.display = 'none';
                    document.getElementById('userGcashInfo').style.display = 'none';
                    document.getElementById('viewQrBtn').style.display = 'none';
                    
                    // Reload page after 2 seconds to show new payment
                    setTimeout(function() {
                        window.location.href = window.location.pathname + (window.location.search || '');
                    }, 2000);
                }
                
                // Auto-hide alert after 5 seconds
                setTimeout(function() {
                    alertDiv.style.opacity = '0';
                    setTimeout(() => alertDiv.remove(), 300);
                }, 5000);
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-error';
                alertDiv.innerHTML = `
                    <span class="alert-icon">✕</span>
                    <span>An error occurred. Please try again.</span>
                `;
                
                const formCard = document.querySelector('.form-card');
                formCard.parentNode.insertBefore(alertDiv, formCard);
                alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    }
    
    // Initialize earnings calculation on page load if user and pay period are set
    const userSelect = document.getElementById('user_id');
    const payPeriodInput = document.getElementById('pay_period');
    
    if (userSelect && payPeriodInput && userSelect.value && payPeriodInput.value) {
        calculateEarnings();
    }
});

// Helper to fetch a page and replace a selector's element with the new content
function fetchAndReplaceSection(url, selector) {
    var container = document.querySelector(selector);
    if (!container) return;

    // Show a loading state
    var originalHTML = container.innerHTML;
    container.innerHTML = '<div class="empty-state"><p>Loading...</p></div>';

    fetch(url, { credentials: 'same-origin' })
        .then(function(resp) { return resp.text(); })
        .then(function(htmlText) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(htmlText, 'text/html');
            var newEl = doc.querySelector(selector);
            if (newEl) {
                container.replaceWith(newEl);
            } else {
                // If selector not found, restore original
                container.innerHTML = originalHTML;
            }
        })
        .catch(function(err) {
            console.error('AJAX load failed', err);
            container.innerHTML = originalHTML;
        });
}

function toggleForm() {
    const form = document.getElementById('paymentForm');
    const btn = document.getElementById('toggleBtn');
    const icon = document.getElementById('toggleIcon');
    
    if (form.style.display === 'none') {
        form.style.display = 'flex';
        btn.innerHTML = '<span id="toggleIcon">▲</span> Hide Form';
    } else {
        form.style.display = 'none';
        btn.innerHTML = '<span id="toggleIcon">▼</span> Show Form';
    }
}

function updateHistoryFilters() {
    const form = document.getElementById('historyFilterForm');
    const yearSelect = document.getElementById('history_year');
    const monthSelect = document.getElementById('history_month');
    const periodSelect = document.getElementById('history_period');

    // When year changes, reset month and period
    if (yearSelect.value) {
        monthSelect.disabled = false;
        if (!monthSelect.value) {
            periodSelect.disabled = true;
            periodSelect.value = '';
        }
    } else {
        monthSelect.disabled = true;
        monthSelect.value = '';
        periodSelect.disabled = true;
        periodSelect.value = '';
    }

    // When month changes, enable period
    if (monthSelect.value) {
        periodSelect.disabled = false;
    } else {
        periodSelect.disabled = true;
        periodSelect.value = '';
    }

    // Build query params and fetch history card via AJAX
    var params = new URLSearchParams(window.location.search);
    if (yearSelect.value) params.set('history_year', yearSelect.value); else params.delete('history_year');
    if (monthSelect.value) params.set('history_month', monthSelect.value); else params.delete('history_month');
    if (periodSelect.value) params.set('history_period', periodSelect.value); else params.delete('history_period');

    // Preserve filter_period if present
    var fp = (new URLSearchParams(window.location.search)).get('filter_period');
    if (fp) params.set('filter_period', fp);

    var newUrl = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
    fetchAndReplaceSection(newUrl, '.history-card');
    history.pushState(null, '', newUrl);
}

function updateUserInfo() {
    const select = document.getElementById('user_id');
    const selectedOption = select.options[select.selectedIndex];
    const gcashInfo = document.getElementById('userGcashInfo');
    const viewQrBtn = document.getElementById('viewQrBtn');
    const qrCodeImage = document.getElementById('qrCodeImage');
    
    if (selectedOption.value) {
        const gcashNumber = selectedOption.getAttribute('data-gcash');
        const gcashName = selectedOption.getAttribute('data-name');
        const qrCodePath = selectedOption.getAttribute('data-qr');
        
        document.getElementById('gcashNumber').textContent = gcashNumber;
        document.getElementById('gcashName').textContent = gcashName;
        gcashInfo.style.display = 'block';
        
        if (qrCodePath) {
            qrCodeImage.src = '../uploads/gcash/' + qrCodePath;
            viewQrBtn.style.display = 'inline-flex';
        } else {
            viewQrBtn.style.display = 'none';
        }
        
        // Calculate earnings after displaying user info
        calculateEarnings();
    } else {
        gcashInfo.style.display = 'none';
        viewQrBtn.style.display = 'none';
        
        // Clear amount field
        document.getElementById('amount').value = '';
    }
}

function calculateEarnings() {
    const userSelect = document.getElementById('user_id');
    const payPeriodInput = document.getElementById('pay_period');
    const amountInput = document.getElementById('amount');
    
    const userId = userSelect.value;
    const payPeriod = payPeriodInput.value;
    
    if (!userId || !payPeriod) {
        amountInput.value = '';
        return;
    }
    
    // Disable field while calculating
    amountInput.disabled = true;
    amountInput.style.opacity = '0.6';
    amountInput.placeholder = 'Calculating...';
    
    // Fetch earnings data
    const formData = new FormData();
    formData.append('action', 'calculate_earnings');
    formData.append('user_id', userId);
    formData.append('pay_period', payPeriod);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        amountInput.disabled = false;
        amountInput.style.opacity = '1';
        amountInput.placeholder = '0.00';
        
        if (data.success) {
            // Update amount field with UNFORMATTED number (no commas)
            amountInput.value = data.total_earnings;
            amountInput.style.color = '#059669';
        } else {
            amountInput.value = '0';
            amountInput.style.color = '#dc2626';
            alert('Failed to calculate earnings: ' + data.message);
        }
    })
    .catch(error => {
        amountInput.disabled = false;
        amountInput.style.opacity = '1';
        amountInput.placeholder = 'Error';
        amountInput.value = '';
        amountInput.style.color = '#dc2626';
        alert('Network error: ' + error.message);
    });
}

function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

function viewReceipt(image, userName) {
    const modal = document.getElementById('receiptModal');
    const modalImg = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalTitle');
    
    modal.style.display = 'block';
    modalImg.src = '../uploads/payment_receipts/' + image;
    modalTitle.textContent = 'Payment Receipt - ' + userName;
}

function viewUserQRCode() {
    const qrCodeImage = document.getElementById('qrCodeImage');
    const select = document.getElementById('user_id');
    const selectedOption = select.options[select.selectedIndex];
    const userName = selectedOption.text;
    const modal = document.getElementById('receiptModal');
    const modalImg = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalTitle');
    
    modal.style.display = 'block';
    modalImg.src = qrCodeImage.src;
    modalTitle.textContent = 'GCash QR Code - ' + userName;
}

function closeModal() {
    document.getElementById('receiptModal').style.display = 'none';
}

function confirmDelete(paymentId) {
    if (confirm('Are you sure you want to delete this payment record? This action cannot be undone.')) {
        document.getElementById('deletePaymentId').value = paymentId;
        document.getElementById('deleteForm').submit();
    }
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('receiptModal');
    if (event.target == modal) {
        closeModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php include 'layout/footer.php'; ?>
