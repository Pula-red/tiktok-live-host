<?php
require_once __DIR__ . '/../includes/functions.php';

// Require live seller role
require_role('live_seller');

// Get current user info
$current_user = get_logged_in_user();
$db = getDB();

// Handle AJAX request
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    
    $year = isset($_GET['year']) && $_GET['year'] !== '' ? intval($_GET['year']) : null;
    $month = isset($_GET['month']) && $_GET['month'] !== '' ? intval($_GET['month']) : null;
    
    // Build query with filters
    $query = "
        SELECT 
            pr.*,
            a.full_name as admin_name,
            YEAR(pr.payment_date) as payment_year,
            MONTH(pr.payment_date) as payment_month
        FROM payment_receipts pr
        JOIN users a ON pr.admin_id = a.id
        WHERE pr.user_id = ?
    ";
    
    $params = [$current_user['id']];
    
    if ($year !== null) {
        $query .= " AND YEAR(pr.payment_date) = ?";
        $params[] = $year;
    }
    
    if ($month !== null) {
        $query .= " AND MONTH(pr.payment_date) = ?";
        $params[] = $month;
    }
    
    $query .= " ORDER BY pr.payment_date DESC, pr.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    // Generate HTML for payment cards
    ob_start();
    if (empty($payments)) {
        ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <p>No payments found</p>
            <small>Try selecting a different time period</small>
        </div>
        <?php
    } else {
        foreach ($payments as $payment):
        ?>
            <div class="payment-card" data-year="<?php echo $payment['payment_year']; ?>" data-month="<?php echo $payment['payment_month']; ?>">
                <div class="payment-header">
                    <div class="payment-amount">₱<?php echo number_format($payment['amount'], 0); ?></div>
                    <div class="payment-date">
                        <?php echo date('F j, Y', strtotime($payment['payment_date'])); ?>
                    </div>
                </div>
                
                <div class="payment-body">
                    <?php if (!empty($payment['pay_period_start']) && !empty($payment['pay_period_end'])): ?>
                        <div class="payment-detail">
                            <span class="detail-label">Pay Period:</span>
                            <span class="detail-value">
                                <?php 
                                $start = new DateTime($payment['pay_period_start']);
                                $end = new DateTime($payment['pay_period_end']);
                                echo $start->format('M j') . '-' . $end->format('j, Y');
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($payment['reference_number']): ?>
                        <div class="payment-detail">
                            <span class="detail-label">Reference Number:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['reference_number']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="payment-detail">
                        <span class="detail-label">Payment Method:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                    </div>
                    
                    <div class="payment-detail">
                        <span class="detail-label">Processed By:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($payment['admin_name']); ?></span>
                    </div>
                    
                    <?php if ($payment['notes']): ?>
                        <div class="payment-notes">
                            <span class="notes-label">Notes:</span>
                            <p class="notes-text"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="payment-footer">
                    <button class="btn-view-receipt" 
                            onclick="viewReceipt('<?php echo htmlspecialchars($payment['receipt_image']); ?>')">
                        <span class="btn-icon">🧾</span>
                        View Receipt
                    </button>
                    <div class="payment-time">
                        Uploaded: <?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?>
                    </div>
                </div>
            </div>
        <?php
        endforeach;
    }
    
    $html = ob_get_clean();
    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => count($payments)
    ]);
    exit;
}

// Fetch all payment records for the current user
$stmt = $db->prepare("
    SELECT 
        pr.*,
        a.full_name as admin_name,
        YEAR(pr.payment_date) as payment_year,
        MONTH(pr.payment_date) as payment_month
    FROM payment_receipts pr
    JOIN users a ON pr.admin_id = a.id
    WHERE pr.user_id = ?
    ORDER BY pr.payment_date DESC, pr.created_at DESC
");
$stmt->execute([$current_user['id']]);
$payments = $stmt->fetchAll();

// Get unique years and months
$years = array_unique(array_column($payments, 'payment_year'));
rsort($years);

// Calculate statistics
$total_payments = count($payments);
$total_received = array_sum(array_column($payments, 'amount'));

// Get latest payment
$latest_payment = !empty($payments) ? $payments[0] : null;

$page_title = 'Payment History';
include 'layout/header.php';
?>

<div class="payment-history-container">
    <div class="page-header">
        <h1>💰 Payment History</h1>
        <p>View all payments you've received</p>
    </div>

    <!-- Payment History -->
    <div class="history-card">
        <div class="history-header">
            <h2>📋 Your Payment Records</h2>
            <?php if (!empty($payments)): ?>
            <div class="filter-controls">
                <select id="yearFilter" class="filter-select" onchange="filterPayments()">
                    <option value="">All Years</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="monthFilter" class="filter-select" onchange="filterPayments()" disabled>
                    <option value="">Select Month</option>
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <div class="empty-icon">💳</div>
                <p>No payment records yet</p>
                <small>Your payment history will appear here once the admin processes payments</small>
            </div>
        <?php else: ?>
            <div class="payments-grid">
                <?php foreach ($payments as $payment): ?>
                    <div class="payment-card" data-year="<?php echo $payment['payment_year']; ?>" data-month="<?php echo $payment['payment_month']; ?>">
                        <div class="payment-header">
                            <div class="payment-amount">₱<?php echo number_format($payment['amount'], 0); ?></div>
                            <div class="payment-date">
                                <?php echo date('F j, Y', strtotime($payment['payment_date'])); ?>
                            </div>
                        </div>
                        
                        <div class="payment-body">
                            <?php if (!empty($payment['pay_period_start']) && !empty($payment['pay_period_end'])): ?>
                                <div class="payment-detail">
                                    <span class="detail-label">Pay Period:</span>
                                    <span class="detail-value">
                                        <?php 
                                        $start = new DateTime($payment['pay_period_start']);
                                        $end = new DateTime($payment['pay_period_end']);
                                        echo $start->format('M j') . '-' . $end->format('j, Y');
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($payment['reference_number']): ?>
                                <div class="payment-detail">
                                    <span class="detail-label">Reference Number:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($payment['reference_number']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="payment-detail">
                                <span class="detail-label">Payment Method:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                            </div>
                            
                            <div class="payment-detail">
                                <span class="detail-label">Processed By:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($payment['admin_name']); ?></span>
                            </div>
                            
                            <?php if ($payment['notes']): ?>
                                <div class="payment-notes">
                                    <span class="notes-label">Notes:</span>
                                    <p class="notes-text"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="payment-footer">
                            <button class="btn-view-receipt" 
                                    onclick="viewReceipt('<?php echo htmlspecialchars($payment['receipt_image']); ?>')">
                                <span class="btn-icon">🧾</span>
                                View Receipt
                            </button>
                            <div class="payment-time">
                                Uploaded: <?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for viewing receipt -->
<div id="receiptModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h3>Payment Receipt</h3>
        <img id="modalImage" src="" alt="Receipt">
    </div>
</div>

<style>
.payment-history-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.page-header {
    text-align: center;
    margin-bottom: 2rem;
}

.page-header h1 {
    font-size: 2.5rem;
    color: #f5f5f5;
    margin-bottom: 0.5rem;
}

.page-header p {
    color: #e8e8e8;
    font-size: 1.125rem;
    margin: 0;
}

.history-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
}

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
    flex-wrap: wrap;
    gap: 1rem;
}

.history-card h2 {
    font-size: 1.5rem;
    color: #2d3748;
    margin: 0;
}

.filter-controls {
    display: flex;
    gap: 0.75rem;
}

.filter-select {
    padding: 0.5rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    color: #2d3748;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 140px;
}

.filter-select:hover {
    border-color: #667eea;
}

.filter-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-select:disabled {
    background: #f7fafc;
    cursor: not-allowed;
    opacity: 0.6;
}

.loading-state {
    text-align: center;
    padding: 4rem 1rem;
}

.spinner {
    border: 4px solid #f3f4f6;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-state p {
    color: #4b5563;
    font-size: 1rem;
    font-weight: 500;
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

.no-results-message {
    grid-column: 1 / -1;
}

.payments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.payment-card {
    background: linear-gradient(135deg, #f7fafc, #edf2f7);
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.payment-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    transform: translateY(-4px);
}

.payment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.payment-amount {
    font-size: 2rem;
    font-weight: 800;
    color: #10b981;
    line-height: 1;
}

.payment-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    color: #4b5563;
    font-weight: 600;
}

.date-icon {
    font-size: 1.2rem;
}

.payment-body {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.payment-detail {
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
    font-weight: 600;
}

.payment-notes {
    background: #fefce8;
    border: 1px solid #fde047;
    border-radius: 8px;
    padding: 0.75rem;
    margin-top: 0.5rem;
}

.notes-label {
    font-size: 0.75rem;
    color: #854d0e;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.notes-text {
    font-size: 0.875rem;
    color: #713f12;
    margin: 0.5rem 0 0 0;
    line-height: 1.5;
}

.payment-footer {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 2px solid #e2e8f0;
}

.btn-view-receipt {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-view-receipt:hover {
    background: linear-gradient(135deg, #5568d3, #6a3f8f);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-icon {
    font-size: 1.2rem;
}

.payment-time {
    text-align: center;
    font-size: 0.75rem;
    color: #9ca3af;
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

.modal-content h3 {
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

@media (max-width: 768px) {
    .payment-history-container {
        padding: 1rem;
    }
    
    .history-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-controls {
        flex-direction: column;
        width: 100%;
    }
    
    .filter-select {
        width: 100%;
    }
    
    .payments-grid {
        grid-template-columns: 1fr;
    }
    
    .payment-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .payment-amount {
        font-size: 1.75rem;
    }
}
</style>

<script>
function filterPayments() {
    const yearFilter = document.getElementById('yearFilter');
    const monthFilter = document.getElementById('monthFilter');
    const selectedYear = yearFilter.value;
    const selectedMonth = monthFilter.value;
    const paymentsGrid = document.querySelector('.payments-grid');
    
    // Enable/disable month filter based on year selection
    if (selectedYear) {
        monthFilter.disabled = false;
    } else {
        monthFilter.disabled = true;
        monthFilter.value = '';
    }
    
    // Show loading state
    paymentsGrid.innerHTML = '<div class="loading-state"><div class="spinner"></div><p>Loading payments...</p></div>';
    
    // Build URL with parameters
    const params = new URLSearchParams({
        ajax: '1'
    });
    
    if (selectedYear) {
        params.append('year', selectedYear);
    }
    
    if (selectedMonth) {
        params.append('month', selectedMonth);
    }
    
    // Fetch filtered data
    fetch('?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                paymentsGrid.innerHTML = data.html;
            } else {
                paymentsGrid.innerHTML = '<div class="empty-state"><div class="empty-icon">❌</div><p>Error loading payments</p></div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            paymentsGrid.innerHTML = '<div class="empty-state"><div class="empty-icon">❌</div><p>Error loading payments</p><small>Please try again</small></div>';
        });
}

function viewReceipt(image) {
    const modal = document.getElementById('receiptModal');
    const modalImg = document.getElementById('modalImage');
    
    modal.style.display = 'block';
    modalImg.src = '../uploads/payment_receipts/' + image;
}

function closeModal() {
    document.getElementById('receiptModal').style.display = 'none';
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
