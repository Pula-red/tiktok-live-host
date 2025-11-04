<?php
require_once __DIR__ . '/../includes/functions.php';

// Require admin role
require_role('admin');

// Get current user info
$current_user = get_logged_in_user();
$db = getDB();

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
    <?php if (!empty($users_with_qr)): ?>
        <div class="section-card">
            <h2>✅ Users with GCash QR Codes</h2>
            <div class="users-grid">
                <?php foreach ($users_with_qr as $user): ?>
                    <div class="user-card">
                        <div class="user-header">
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                <div class="user-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                                <span class="user-badge <?php echo $user['experienced_status']; ?>">
                                    <?php echo ucfirst($user['experienced_status']); ?>
                                </span>
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
                            <div class="detail-row">
                                <span class="detail-label">📅 Uploaded:</span>
                                <span class="detail-value"><?php echo date('M j, Y', strtotime($user['uploaded_at'])); ?></span>
                            </div>
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

.user-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-top: 0.5rem;
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
</script>

<?php include 'layout/footer.php'; ?>
