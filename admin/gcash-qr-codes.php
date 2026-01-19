<?php
require_once __DIR__ . '/../includes/functions.php';

// Require admin role
require_role('admin');

// Get current user info
$current_user = get_logged_in_user();
$db = getDB();

// Handle AJAX request to mark paycheck as sent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_paycheck') {
    header('Content-Type: application/json');

    $user_id = intval($_POST['user_id'] ?? 0);
    $period_start = trim($_POST['period_start'] ?? '');
    $period_end = trim($_POST['period_end'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);

    if ($user_id <= 0 || empty($period_start) || empty($period_end) || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    // Check if already marked as paid for this period
    $check = $db->prepare("SELECT COUNT(*) as cnt FROM payment_receipts WHERE user_id = ? AND pay_period_start = ? AND pay_period_end = ? AND status = 'completed'");
    $check->execute([$user_id, $period_start, $period_end]);
    $row = $check->fetch();
    if ($row && intval($row['cnt']) > 0) {
        echo json_encode(['success' => false, 'message' => 'Already paid for this period']);
        exit;
    }

    // Insert a payment record (receipt_image left blank for manual marking)
    $stmt = $db->prepare("INSERT INTO payment_receipts (user_id, admin_id, amount, receipt_image, payment_date, reference_number, notes, pay_period_start, pay_period_end, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
    $payment_date = date('Y-m-d');
    $notes = 'Marked as paid by admin via GCash QR Codes page';
    $receipt_image = '';
    try {
        $stmt->execute([$user_id, $current_user['id'], $amount, $receipt_image, $payment_date, null, $notes, $period_start, $period_end]);
        echo json_encode(['success' => true, 'message' => 'Paycheck marked as sent']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
    
// Fetch all users with their GCash QR codes
$stmt = $db->query("
    SELECT 
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.profile_image,
        u.experienced_status,
        g.id as qr_id,
        g.qr_code_image,
        g.gcash_number,
        g.gcash_name,
        g.uploaded_at,
        g.updated_at
    FROM users u
    LEFT JOIN gcash_qr_codes g ON u.id = g.user_id AND g.is_active = 1
    WHERE u.role = 'live_seller' AND u.status = 'active'
    ORDER BY u.full_name ASC
");
$users = $stmt->fetchAll();

// Separate users with and without QR codes
$users_with_qr = array_filter($users, function($user) {
    return $user['qr_id'] !== null;
});

$users_without_qr = array_filter($users, function($user) {
    return $user['qr_id'] === null;
});

$page_title = 'GCash QR Codes';
include 'layout/header.php';
?>

<div class="gcash-admin-container">
    <div class="page-header">
        <h1>💳 User GCash QR Codes</h1>
        <p>View all live seller GCash payment information</p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($users); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✓</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($users_with_qr); ?></div>
                <div class="stat-label">With QR Code</div>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">⚠</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($users_without_qr); ?></div>
                <div class="stat-label">Without QR Code</div>
            </div>
        </div>
    </div>

    <!-- Users with QR Codes -->
    <?php
    // Filters for payment cutoff
    $filter_year = $_GET['pay_year'] ?? '';
    $filter_month = $_GET['pay_month'] ?? '';
    $filter_cutoff = $_GET['pay_cutoff'] ?? ''; // '1-15' or '16-end'

    $period_start = '';
    $period_end = '';
    if (!empty($filter_year) && !empty($filter_month) && !empty($filter_cutoff)) {
        $year = intval($filter_year);
        $month = str_pad(intval($filter_month), 2, '0', STR_PAD_LEFT);
        if ($filter_cutoff === '1-15') {
            $period_start = "$year-$month-01";
            $period_end = "$year-$month-15";
        } elseif ($filter_cutoff === '16-end') {
            $last_day = date('t', strtotime("$year-$month-01"));
            $period_start = "$year-$month-16";
            $period_end = "$year-$month-$last_day";
        }
    }

    if (!empty($users_with_qr)): ?>
        <div class="section-card">
            <div class="section-header">
                <h2>✅GCash QR Codes</h2>
                <?php
                // compute cutoff label end if year/month selected
                $cutoff_end_label = '16-end';
                if (!empty($filter_year) && !empty($filter_month)) {
                    $ld = date('t', strtotime($filter_year . '-' . str_pad($filter_month, 2, '0', STR_PAD_LEFT) . '-01'));
                    $cutoff_end_label = '16-' . $ld;
                }   
                ?>
                <form method="GET" id="payPeriodForm" class="pay-period-form">
                    <span class="filter-label">Select Cut Off:</span>
                    <select name="pay_year" id="pay_year" onchange="this.form.submit()">
                        <option value="">All Years</option>
                        <?php $currentYear = date('Y'); ?>
                        <option value="<?php echo $currentYear; ?>" <?php echo ($filter_year == $currentYear) ? 'selected' : ''; ?>><?php echo $currentYear; ?></option>
                    </select>
                    <select name="pay_month" id="pay_month" onchange="this.form.submit()">
                        <option value="">All Months</option>
                        <?php for ($m = 1; $m <= 12; $m++): $label = date('F', mktime(0,0,0,$m,1)); ?>
                            <option value="<?php echo $m; ?>" <?php echo ($filter_month == $m) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="pay_cutoff" id="pay_cutoff" onchange="this.form.submit()">
                        <option value="">All Periods</option>
                        <option value="1-15" <?php echo ($filter_cutoff === '1-15') ? 'selected' : ''; ?>>1-15</option>
                        <option value="16-end" <?php echo ($filter_cutoff === '16-end') ? 'selected' : ''; ?>><?php echo htmlspecialchars($cutoff_end_label); ?></option>
                    </select>   
                </form>
            </div>
            <div class="users-grid">
                <?php foreach ($users_with_qr as $user): ?> 
                    <div class="user-card">
                        <span class="user-badge <?php echo $user['experienced_status']; ?>">
                            <?php echo ucfirst($user['experienced_status']); ?>
                        </span>
                        <div class="user-header">
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            </div>
                        </div>
                        
                        <div class="qr-preview">
                            <img src="../uploads/gcash/<?php echo htmlspecialchars($user['qr_code_image']); ?>" 
                                 alt="QR Code" 
                                 onclick="openModal('<?php echo htmlspecialchars($user['qr_code_image']); ?>', '<?php echo htmlspecialchars($user['full_name']); ?>')">
                        </div>
                        
                        <div class="qr-details">    
                            <div class="detail-row">
                                <span class="detail-label">📱 Number:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($user['gcash_number']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">👤 Name:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($user['gcash_name']); ?></span>
                            </div>
                            
                            <?php if ($period_start && $period_end):
                                // calculate hours and earnings for this user in the period (including approved overtime)
                                $stmt = $db->prepare("
                                    SELECT 
                                        COALESCE(SUM(a.hours_worked), 0) + COALESCE(SUM(o.duration_hours), 0) as total_hours
                                    FROM (SELECT ? as seller_id, ? as start_dt, ? as end_dt) filter_data
                                    LEFT JOIN attendance a ON a.seller_id = filter_data.seller_id 
                                        AND a.attendance_date BETWEEN filter_data.start_dt AND filter_data.end_dt AND a.status = 'approved'
                                    LEFT JOIN overtime o ON o.seller_id = filter_data.seller_id 
                                        AND o.overtime_date BETWEEN filter_data.start_dt AND filter_data.end_dt AND o.status = 'approved'
                                ");
                                $stmt->execute([$user['id'], $period_start, $period_end]);
                                $res = $stmt->fetch();
                                $total_hours = floatval($res['total_hours'] ?? 0);

                                $rateStmt = $db->prepare("SELECT hourly_rate FROM users WHERE id = ?");
                                $rateStmt->execute([$user['id']]);
                                $rateRow = $rateStmt->fetch();
                                $hourly_rate = floatval($rateRow['hourly_rate'] ?? 0);

                                $pay_amount = round($total_hours * $hourly_rate, 2);

                                // check if already paid
                                $paidChk = $db->prepare("SELECT COUNT(*) as cnt FROM payment_receipts WHERE user_id = ? AND pay_period_start = ? AND pay_period_end = ? AND status = 'completed'");
                                $paidChk->execute([$user['id'], $period_start, $period_end]);
                                $paidRow = $paidChk->fetch();
                                $alreadyPaid = intval($paidRow['cnt'] ?? 0) > 0;
                            ?>
                                <div class="detail-row">
                                    <span class="detail-label">🗓️ Pay Period:</span>
                                    <span class="detail-value">
                                        <?php echo date('M', strtotime($period_start)) . ' ' . date('j', strtotime($period_start)) . '-' . date('j', strtotime($period_end)); ?>
                                    </span>
                                </div>

                                <div class="detail-row">
                                    <span class="detail-label">💰 Total Salary:</span>      
                                    <span class="detail-value">₱<?php echo number_format($pay_amount, 2); ?></span>
                                </div>
                                <div style="margin-top:0.75rem; text-align:center;">
                                    <?php if ($alreadyPaid): ?>
                                        <button class="btn-open-upload" disabled>
                                            <span class="check-icon" aria-hidden="true">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1 14.5l-4-4 1.4-1.4 2.6 2.6 5.6-5.6L18 10l-7 6.5z"/>
                                                </svg>
                                            </span>
                                            Paycheck sent
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-open-upload" data-user-id="<?php echo $user['id']; ?>" data-amount="<?php echo $pay_amount; ?>" data-start="<?php echo $period_start; ?>" data-end="<?php echo $period_end; ?>">Upload Receipt</button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>        
            </div>
        </div>
    <?php endif; ?>

    <!-- Users without QR Codes -->
    <?php if (!empty($users_without_qr)): ?>
        <div class="section-card warning-section">
            <h2>⚠ Users without GCash QR Codes</h2>
            <div class="simple-list">
                <?php foreach ($users_without_qr as $user): ?>
                    <div class="list-item">
                        <div class="list-user-info">
                            <div class="list-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            <div class="list-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                        </div>
                        <span class="user-badge <?php echo $user['experienced_status']; ?>">
                            <?php echo ucfirst($user['experienced_status']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal for viewing full-size QR code -->
<div id="qrModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h3 id="modalTitle"></h3>
        <img id="modalImage" src="" alt="QR Code">
    </div>
</div>

<!-- Modal for uploading payment receipt -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeUploadModal()">&times;</span>
        <h3 id="uploadModalTitle">Upload Payment Receipt</h3>
        <form id="uploadReceiptForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_payment">
            <input type="hidden" name="ajax" value="1">
            <input type="hidden" name="user_id" id="upload_user_id">
            <input type="hidden" name="pay_period" id="upload_pay_period">
            <input type="hidden" name="payment_date" id="upload_payment_date" value="<?php echo date('Y-m-d'); ?>">
            <div style="margin:12px 0; text-align:left;">
                <label>Amount (₱)</label>
                <input type="text" name="amount" id="upload_amount" readonly style="width:100%; padding:8px; margin-top:6px; border-radius:6px; border:1px solid #cbd5e0; background:#f7fafc;" />
            </div>
            <div style="margin:12px 0; text-align:left;">
                <label>Receipt Image *</label>
                <input type="file" name="receipt_image" id="upload_receipt_image" accept="image/*" required style="width:100%; margin-top:6px;" />
            </div>
            <div style="margin:12px 0; text-align:left;">
                <label>Reference Number</label>
                <input type="text" name="reference_number" id="upload_reference" placeholder="GCash reference (optional)" style="width:100%; padding:8px; margin-top:6px; border-radius:6px; border:1px solid #cbd5e0;" />
            </div>
            <div style="margin:12px 0; text-align:left;">
                <label>Notes (optional)</label>
                <textarea name="notes" id="upload_notes" rows="3" style="width:100%; padding:8px; margin-top:6px; border-radius:6px; border:1px solid #cbd5e0;"></textarea>
            </div>
            <div style="text-align:center; margin-top:12px;">
                <button type="submit" id="uploadSubmit" class="btn-upload-receipt">Send Paycheck</button>
            </div>
        </form>
    </div>
</div>

<style>
.gcash-admin-container {
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
    color: white;
    margin-bottom: 0.5rem;
}

.page-header p {
    color: whitesmoke;
    font-size: 1.125rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
}

.stat-card.success {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
}

.stat-card.warning {
    background: linear-gradient(135deg, #fed7aa, #fbbf24);
}

.stat-icon {
    font-size: 2.5rem;
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #2d3748;
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    color: #4b5563;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.25rem;
}

.section-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    margin-bottom: 2rem;
}

.section-card h2 {
    font-size: 1.5rem;
    color: #2d3748;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

/* Header + pay period form layout */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}
.pay-period-form {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
.pay-period-form .filter-label {
    font-weight: 600;
    margin-right: 0.25rem;
}
.pay-period-form select {
    padding: 0.45rem 0.6rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: white;
}
.results-count {
    margin-left: 1rem;
    color: #4b5563;
    font-size: 0.95rem;
}

@media (max-width: 768px) {
    .section-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .pay-period-form {
        width: 100%;
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .pay-period-form select {
        flex: 1 1 48%;
        min-width: 140px;
    }
    .pay-period-form .filter-label {
        width: 100%;
        margin-bottom: 0.25rem;
    }
    .results-count {
        width: 100%;
        margin-top: 0.5rem;
    }
}

.warning-section {
    border: 2px solid #fbbf24;
}

.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.user-card {
    background: #f7fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    position: relative;
    transition: all 0.3s ease;
}

.user-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    transform: translateY(-2px);
}

.user-header {
    margin-bottom: 1rem;
}

.user-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.user-card .user-name {
    font-size: 1.125rem;
    font-weight: 700;
    color: #000000 !important;
    background-color: transparent;
}

.user-username {
    font-size: 0.875rem;
    color: #718096;
}

/* removed inline username display - kept class in case used elsewhere */

.user-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-top: 0.5rem;
}

.user-card .user-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    margin-top: 0;
    z-index: 3;
}

.user-badge.tenured {
    background: #fef3c7;
    color: #92400e;
}

.user-badge.newbie {
    background: #dbeafe;
    color: #1e40af;
}

.qr-preview {
    margin: 1rem 0;
    text-align: center;
}

.qr-preview img {
    width: 100%;
    max-width: 250px;
    height: auto;
    border-radius: 10px;
    border: 2px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.3s ease;
}

.qr-preview img:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    transform: scale(1.05);
}

.qr-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: white;
    border-radius: 8px;
}

.detail-label {
    font-size: 0.875rem;
    color: #718096;
    font-weight: 600;
}

.detail-value {
    font-size: 0.875rem;
    color: #2d3748;
    font-weight: 500;
}

.btn-filter {
    padding: 0.45rem 0.65rem;
    border-radius: 8px;
    border: none;
    background: #667eea;
    color: white;
    font-weight: 700;
    cursor: pointer;
}

.btn-paycheck {
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    font-weight: 700;
    cursor: pointer;
}

.btn-paycheck[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
    background: #9ca3af;
}

.btn-upload-receipt {
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    font-weight: 700;
    cursor: pointer;
}

.btn-upload-receipt[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
    background: #9ca3af;
}

.btn-open-upload {
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    font-weight: 700;
    cursor: pointer;
}

.btn-open-upload[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
    background: #9ca3af;
}

.btn-open-upload .check-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    margin-right: 0.5rem;
    vertical-align: middle;
}
.btn-open-upload .check-icon svg { display: block; }

.filter-label {
    font-weight: 700;
    color: #1f2937;
    margin-right: 8px;
}

#payPeriodForm select {
    min-width: 140px;
    padding: 0.55rem 0.7rem;
    border-radius: 8px;
}

.simple-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #fef3c7;
    border-radius: 10px;
    border: 1px solid #fbbf24;
}

.list-user-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.list-name {
    font-size: 1rem;
    font-weight: 600;
    color: #2d3748;
}

.list-username {
    font-size: 0.875rem;
    color: #718096;
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
    background-color: rgba(0, 0, 0, 0.6);
    animation: fadeIn 0.3s;
}

.modal-content {
    background-color: white;
    color: black;   
    margin: 4% auto;
    padding: 2.25rem;
    border-radius: 14px;
    max-width: 760px;
    width: calc(100% - 48px);
    text-align: center;
    position: relative;
    animation: slideDown 0.25s;
    box-shadow: 0 8px 30px rgba(0,0,0,0.45);
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

/* Modal form readability improvements */
#uploadModal .modal-content label {
    display: block;
    text-align: left;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 6px;
    font-size: 1rem;
}

#uploadModal .modal-content input[type="text"],
#uploadModal .modal-content input[type="file"],
#uploadModal .modal-content input[type="date"],
#uploadModal .modal-content textarea {
    width: 100%;
    font-size: 1rem;
    line-height: 1.4;
    padding: 0.75rem 0.9rem;
    border-radius: 8px;
    border: 1px solid #cbd5e0;
    background: #fff;
    color: #111827;
}

#uploadModal .modal-content textarea { resize: vertical; }

#uploadModal .modal-content .btn-upload-receipt {
    padding: 0.6rem 1rem;
    font-size: 1rem;
}

#uploadModal .modal-content #upload_amount {
    background: #f7fafc;
    font-weight: 700;
    color: #065f46;
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

@media (max-width: 768px) {
    .gcash-admin-container {
        padding: 1rem;
    }
    
    .users-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function openModal(image, userName) {
    const modal = document.getElementById('qrModal');
    const modalImg = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalTitle');
    
    modal.style.display = 'block';
    modalImg.src = '../uploads/gcash/' + image;
    modalTitle.textContent = userName + "'s GCash QR Code";
}

function closeModal() {
    document.getElementById('qrModal').style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('qrModal');
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

// Handle Upload Receipt opener button (open modal)
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-open-upload');
    if (!btn) return;

    // if disabled, do nothing
    if (btn.disabled) return;

    const userId = btn.getAttribute('data-user-id');
    const amount = btn.getAttribute('data-amount');
    const start = btn.getAttribute('data-start');
    const end = btn.getAttribute('data-end');

    // populate modal fields
    document.getElementById('upload_user_id').value = userId || '';
    document.getElementById('upload_amount').value = amount ? parseFloat(amount).toFixed(2) : '';
    document.getElementById('upload_pay_period').value = (start && end) ? (start + '|' + end) : '';
    document.getElementById('upload_receipt_image').value = '';
    document.getElementById('upload_reference').value = '';
    document.getElementById('upload_notes').value = '';

    // show modal
    const modal = document.getElementById('uploadModal');
    modal.style.display = 'block';
});

function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}

// Submit upload form via AJAX multipart to host-payments.php
const uploadForm = document.getElementById('uploadReceiptForm');
if (uploadForm) {
    uploadForm.addEventListener('submit', function(ev) {
        ev.preventDefault();

        const submitBtn = document.getElementById('uploadSubmit');
        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Sending...';

        const formData = new FormData(uploadForm);

        fetch('host-payments.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Payment receipt uploaded.');
                closeUploadModal();

                // update the corresponding opener button to disabled 'Paycheck sent'
                const uid = document.getElementById('upload_user_id').value;
                const btn = document.querySelector('.btn-open-upload[data-user-id="' + uid + '"]');
                if (btn) {
                    btn.innerHTML = '<span class="check-icon" aria-hidden="true">'
                        + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
                        + '<path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1 14.5l-4-4 1.4-1.4 2.6 2.6 5.6-5.6L18 10l-7 6.5z"/>'
                        + '</svg>'
                        + '</span>Paycheck sent';
                    btn.disabled = true;
                }

                // Optionally, refresh payment history page in a new tab
                // window.open('host-payments.php', '_blank');
            } else {
                alert(data.message || 'Failed to upload receipt.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Network or server error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
}
</script>

<?php include 'layout/footer.php'; ?>
