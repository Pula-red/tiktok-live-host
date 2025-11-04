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

$page_title = 'Host Payments';
include 'layout/header.php';
?>

<div class="payments-container">
    <div class="page-header">
        <h1>💰 Host Payments</h1>
        <p>Upload payment receipts for live sellers</p>
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

    <!-- Upload Payment Form -->
    <div class="form-card">
        <div class="card-header-section">
            <div class="header-left">
                <h2>💰 Host Payment</h2>
                <form method="GET" id="filterForm" class="inline-filter">
                    <label for="filter_period">📅 Choose Pay Period:</label>
                    <select id="filter_period" name="filter_period" onchange="this.form.submit()">
                        <option value="">All Users (No Filter)</option>
                        <?php foreach ($pay_periods as $period): ?>
                            <option value="<?php echo htmlspecialchars($period['value']); ?>"
                                    <?php echo ($selected_period === $period['value']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($period['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($selected_period): ?>
                        <a href="?" class="clear-filter" title="Clear Filter">✕</a>
                    <?php endif; ?>
                </form>
            </div>
            <button type="button" class="btn-toggle" onclick="toggleForm()" id="toggleBtn">
                <span id="toggleIcon">▼</span> Show Form
            </button>
        </div>
        
        <?php if ($selected_period && empty($users_with_gcash)): ?>
            <div style="padding: 40px; text-align: center; color: #718096;">
                <div style="font-size: 48px; margin-bottom: 10px;">✅</div>
                <h3 style="color: #48bb78; margin-bottom: 10px;">All users have been paid!</h3>
                <p>All active live sellers have already received payment for this pay period.</p>
                <a href="?" style="display: inline-block; margin-top: 15px; color: #667eea; text-decoration: none;">
                    ← Back to all users
                </a>
            </div>
        <?php else: ?>
        <form method="POST" enctype="multipart/form-data" class="payment-form" id="paymentForm" style="display: none;">
            <input type="hidden" name="action" value="add_payment">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="user_id">Select User *</label>
                    <select id="user_id" name="user_id" required onchange="updateUserInfo()">
                        <option value="">-- Select Live Seller --</option>
                        <?php foreach ($users_with_gcash as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    data-gcash="<?php echo htmlspecialchars($user['gcash_number']); ?>"
                                    data-name="<?php echo htmlspecialchars($user['gcash_name']); ?>"
                                    data-qr="<?php echo htmlspecialchars($user['qr_code_image']); ?>">
                                <?php echo htmlspecialchars($user['full_name']); ?> (@<?php echo htmlspecialchars($user['username']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="userGcashInfo" class="gcash-info" style="display: none;">
                        <small>GCash: <strong id="gcashNumber"></strong> - <strong id="gcashName"></strong></small>
                    </div>
                    <button type="button" id="viewQrBtn" class="btn-view-qr" onclick="viewUserQRCode()" style="display: none;">
                        <span class="qr-icon">⬜</span> View GCash QR Code
                    </button>
                    <img id="qrCodeImage" src="" alt="GCash QR Code" style="display: none;">
                </div>

                <div class="form-group">
                    <label for="amount">Amount (₱) *</label>
                    <input type="number" id="amount" name="amount" 
                           placeholder="0.00" step="0.01" min="0.01" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="payment_date">Payment Date *</label>
                    <input type="date" id="payment_date" name="payment_date" 
                           value="<?php echo date('Y-m-d'); ?>" required readonly 
                           style="background-color: #f7fafc; cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label for="reference_number">Reference Number</label>
                    <input type="text" id="reference_number" name="reference_number" 
                           placeholder="GCash reference number (optional)" maxlength="100">
                </div>
            </div>

            <div class="form-group">
                <!-- Hidden field to store pay period from filter -->
                <input type="hidden" id="pay_period" name="pay_period" value="<?php echo htmlspecialchars($selected_period); ?>">
            </div>

            <div class="form-group">
                <label for="receipt_image">Receipt Image *</label>
                <div class="file-upload-wrapper">
                    <input type="file" id="receipt_image" name="receipt_image" 
                           accept="image/jpeg,image/jpg,image/png,image/gif" 
                           required onchange="previewImage(this)">
                    <div class="file-upload-info">
                        <span class="upload-icon">📁</span>
                        <span class="upload-text">Click to select receipt image</span>
                        <span class="upload-hint">JPG, PNG, GIF (Max 5MB)</span>
                    </div>
                </div>
                <div id="imagePreview" class="image-preview" style="display: none;">
                    <img id="previewImg" src="" alt="Preview">
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Notes (Optional)</label>
                <textarea id="notes" name="notes" rows="3" 
                          placeholder="Add any additional notes about this payment..."></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <span class="btn-icon">💾</span>
                    Upload Payment Receipt
                </button>
                <button type="reset" class="btn-reset">Clear Form</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Payment History -->
    <div class="history-card">
        <div class="history-header-section">
            <h2>📋 Payment History</h2>
            
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
});

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
    
    // When year changes, reset month and period, then submit
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
    
    // Submit the form to reload with new filters
    form.submit();
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
    } else {
        gcashInfo.style.display = 'none';
        viewQrBtn.style.display = 'none';
    }
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
