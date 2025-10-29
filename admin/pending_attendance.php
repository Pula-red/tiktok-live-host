<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

// Get database connection
$db = getDB();

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendance_id = $_POST['attendance_id'] ?? null;
    $action = $_POST['action'] ?? null;
    $rejection_reason = $_POST['rejection_reason'] ?? null;
    
    if ($attendance_id && $action) {
        try {
            if ($action === 'approve') {
                $stmt = $db->prepare("
                    UPDATE attendance 
                    SET status = 'approved',
                        approved_by = ?,
                        approved_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND status = 'pending_approval'
                ");
                $stmt->execute([$_SESSION['user_id'], $attendance_id]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = "Attendance approved successfully.";
                }
            } 
            elseif ($action === 'reject') {
                $stmt = $db->prepare("
                    UPDATE attendance 
                    SET status = 'rejected',
                        approved_by = ?,
                        approved_at = CURRENT_TIMESTAMP,
                        rejection_reason = ?
                    WHERE id = ? AND status = 'pending_approval'
                ");
                $stmt->execute([$_SESSION['user_id'], $rejection_reason, $attendance_id]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = "Attendance rejected successfully.";
                }
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
        }
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending_approval';
$date_filter = $_GET['date'] ?? date('Y-m-d');

// Fetch pending attendance records
$query = "
    SELECT 
        a.*,
        u.full_name as seller_name,
        u.username,
        u.experienced_status,
        ats.name as slot_name,
        ats.start_time,
        ats.end_time,
        approver.full_name as approved_by_name
    FROM attendance a
    LEFT JOIN users u ON a.seller_id = u.id
    LEFT JOIN attendance_time_slots ats ON a.time_slot = ats.id
    LEFT JOIN users approver ON a.approved_by = approver.id
    WHERE 1=1
";

$params = [];

if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $query .= " AND a.attendance_date = ?";
    $params[] = $date_filter;
}

$query .= " ORDER BY a.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll();

$page_title = 'Attendance Approval';
include 'layout/header.php';
?>

<style>
.approval-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.filters-section {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-weight: 700;
    color: #000000;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.filter-group select,
.filter-group input {
    padding: 0.75rem;
    border: 1px solid #cbd5e0;
    border-radius: 8px;
    background: white;
    font-size: 1rem;
    color: #000000;
}

.attendance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.attendance-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}

.card-header {
    padding: 1rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.seller-avatar {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.25rem;
}

.seller-info h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #1a202c;
    font-weight: 600;
}

.status-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending_approval {
    background: #fef3c7;
    color: #92400e;
}

.status-approved {
    background: #def7ec;
    color: #046c4e;
}

.status-rejected {
    background: #fde8e8;
    color: #9b1c1c;
}

.card-body {
    padding: 1rem;
}

.details-grid {
    display: grid;
    gap: 0.75rem;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
}

.detail-label {
    color: #4a5568; 
    min-width: 100px;
    font-weight: 500;
}

.detail-value {
    color: #000000;
    font-weight: 500;
}

.photo-preview {
    margin: 1rem 0;
    position: relative;
    padding-top: 75%;
    background: #f8fafc;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
}

.photo-preview img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-footer {
    padding: 1rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}

.approval-actions {
    display: flex;
    gap: 0.75rem;
}

.btn-approve,
.btn-reject {
    flex: 1;
    padding: 0.75rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.btn-approve {
    background: #0fa968;
    color: white;
}

.btn-approve:hover {
    background: #047857;
}

.btn-reject {
    background: #ff5757;
    color: white;
}

.btn-reject:hover {
    background: #dc2626;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    padding: 2rem;
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    width: 100%;
    max-width: 500px;
}

.modal-header {
    margin-bottom: 1.5rem;
}

.modal-header h3 {
    margin: 0;
    color: #000000;
    font-weight: 600;
}

.modal-body {
    margin-bottom: 1.5rem;
    color: #000000;
}

.modal-body textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    min-height: 100px;
    margin-top: 0.5rem;
}

.modal-footer {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.btn-cancel {
    padding: 0.75rem 1.5rem;
    border: 1px solid #e2e8f0;
    background: white;
    border-radius: 6px;
    cursor: pointer;
}

.btn-submit {
    padding: 0.75rem 1.5rem;
    background: #ff5757;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

/* Photo modal */
.photo-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 1000;
    padding: 2rem;
}

.photo-modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-modal-content {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 50%;
}

.approval-actions {
    display: flex;
    gap: 0.75rem;
}

.approval-actions form {
    flex: 1;
}

.btn-approve,
.btn-reject {
    width: 100%;
    padding: 0.75rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s;
}
</style>

<div class="approval-container">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success_message'];
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <?php 
            echo $_SESSION['error_message'];
            unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <div class="filters-section">
        <form method="GET" class="filters-grid">
            <div class="filter-group">
                <label for="status">Status</label>
                <select name="status" id="status" onchange="this.form.submit()">
                    <option value="pending_approval" <?php echo $status_filter === 'pending_approval' ? 'selected' : ''; ?>>Pending Approval</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date">Date</label>
                <input type="date" name="date" id="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>

    <div class="attendance-grid">
        <?php foreach ($attendance_records as $record): ?>
            <div class="attendance-card" data-status="<?php echo htmlspecialchars($record['status']); ?>">
                <div class="card-header">
                    <div class="seller-avatar">
                        <?php echo strtoupper(substr($record['seller_name'], 0, 1)); ?>
                    </div>
                    <div class="seller-info">
                        <h3><?php echo htmlspecialchars($record['seller_name']); ?></h3>
                        <div class="status-badge status-<?php echo $record['status']; ?>">
                            <?php 
                            $status = $record['status'];
                            if ($status === 'pending_approval') {
                                echo 'Pending';
                            } else {
                                echo ucfirst($status);
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="detail-label">📅 Date:</span>
                            <span class="detail-value"><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">⏰ Time Slot:</span>
                            <span class="detail-value">
                                <?php 
                                $start = date('g:i A', strtotime($record['start_time']));
                                $end = date('g:i A', strtotime($record['end_time']));
                                echo "$start - $end";
                                ?>
                            </span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">💰 Sales:</span>
                            <span class="detail-value"><?php echo number_format($record['solds_quantity']); ?> items</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">⚡ Duration:</span>
                            <span class="detail-value"><?php echo number_format($record['hours_worked'], 1); ?> hours</span>
                        </div>
                        
                        <?php if ($record['status'] !== 'pending_approval'): ?>
                            <div class="detail-row">
                                <span class="detail-label">👤 Processed at:</span>
                                <span class="detail-value"><?php echo date('M j, g:i A', strtotime($record['approved_at'])); ?></span>
                            </div>
                            
                            <?php if ($record['status'] === 'rejected' && $record['rejection_reason']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">❌ Reason:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($record['rejection_reason']); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($record['total_sold_photo']): ?>
                        <div class="photo-preview" onclick="openPhotoModal('../<?php echo htmlspecialchars($record['total_sold_photo']); ?>')">
                            <img src="../<?php echo htmlspecialchars($record['total_sold_photo']); ?>" 
                                 alt="Total Sold Photo"
                                 onerror="this.src='../assets/images/no-image.png'">
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($record['status'] === 'pending_approval'): ?>
                    <div class="card-footer">
                        <div class="approval-actions">
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-approve">
                                    <span>✓</span> Approve
                                </button>
                            </form>
                            
                            <button type="button" class="btn-reject" onclick="openRejectModal(<?php echo $record['id']; ?>)">
                                <span>×</span> Reject
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($attendance_records)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                <h3>No attendance records found</h3>
                <p>No attendance records match your current filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Confirmation Modal -->
<div class="modal" id="rejectModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Rejection</h3>
        </div>
        <div class="modal-body">
            <p>Please provide a reason for rejecting this attendance:</p>
            <textarea id="rejectionReason" name="rejection_reason" placeholder="Enter rejection reason..." required></textarea>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
            <button type="button" class="btn-submit" onclick="submitRejection()">Confirm Rejection</button>
        </div>
    </div>
</div>

<!-- Photo Modal -->
<div class="photo-modal" id="photoModal">
    <span class="modal-close" onclick="closePhotoModal()">×</span>
    <img class="photo-modal-content" id="modalImage">
</div>

<script>
// Store the current attendance ID for rejection
let currentAttendanceId = null;
function openPhotoModal(imageSrc) {
    const modal = document.getElementById('photoModal');
    const modalImg = document.getElementById('modalImage');
    modal.classList.add('active');
    modalImg.src = imageSrc;
}

function closePhotoModal() {
    document.getElementById('photoModal').classList.remove('active');
}

function openRejectModal(attendanceId) {
    currentAttendanceId = attendanceId;
    document.getElementById('rejectionReason').value = '';
    document.getElementById('rejectModal').classList.add('active');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('active');
    currentAttendanceId = null;
}

function submitRejection() {
    const reason = document.getElementById('rejectionReason').value.trim();
    if (!reason) {
        alert('Please provide a reason for rejection');
        return;
    }

    // Create and submit the form
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';

    const attendanceInput = document.createElement('input');
    attendanceInput.type = 'hidden';
    attendanceInput.name = 'attendance_id';
    attendanceInput.value = currentAttendanceId;

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'reject';

    const reasonInput = document.createElement('input');
    reasonInput.type = 'hidden';
    reasonInput.name = 'rejection_reason';
    reasonInput.value = reason;

    form.appendChild(attendanceInput);
    form.appendChild(actionInput);
    form.appendChild(reasonInput);
    document.body.appendChild(form);
    form.submit();
}

// Close modals on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePhotoModal();
        closeRejectModal();
    }
});
</script>

<?php include 'layout/footer.php'; ?>