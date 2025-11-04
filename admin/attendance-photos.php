<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';

// Require admin role
require_role('admin');

// Get current user info
$current_user = get_logged_in_user();

// Get database connection
try {
    $db = getDB();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Log current working directory and file path for debugging
error_log("Current directory: " . __DIR__);
error_log("Functions file path: " . __DIR__ . '/../includes/functions.php');

// Get filter parameters
$selected_user = $_GET['user_id'] ?? 'all';
$selected_date = $_GET['date'] ?? '';
$selected_month = $_GET['month'] ?? date('Y-m');

// Fetch all live sellers for the filter dropdown
$stmt = $db->query("SELECT id, full_name FROM users WHERE role = 'live_seller' AND status = 'active' ORDER BY full_name");
$all_sellers = $stmt->fetchAll();

// Build the query based on filters
$query = "
    SELECT 
        a.id,
        a.attendance_date,
        a.solds_quantity,
        a.total_sold_photo,
        a.created_at,
        a.hours_worked,
        u.id as user_id,
        u.full_name,
        u.profile_image,
        u.experienced_status,
        ats.name as slot_name,
        ats.start_time,
        ats.end_time,
        ats.duration_hours
    FROM attendance a
    LEFT JOIN users u ON a.seller_id = u.id
    LEFT JOIN attendance_time_slots ats ON a.time_slot = ats.id
    WHERE a.status = 'approved'
    AND a.total_sold_photo IS NOT NULL
";

$params = [];

// Apply user filter
if ($selected_user !== 'all') {
    $query .= " AND u.id = :user_id";
    $params[':user_id'] = $selected_user;
}

// Apply date filter (specific date)
if (!empty($selected_date)) {
    $query .= " AND a.attendance_date = :date";
    $params[':date'] = $selected_date;
} elseif (!empty($selected_month)) {
    // Apply month filter
    $query .= " AND DATE_FORMAT(a.attendance_date, '%Y-%m') = :month";
    $params[':month'] = $selected_month;
}

$query .= " ORDER BY a.attendance_date DESC, a.created_at DESC";

try {
    $stmt = $db->prepare($query);
    if (!$stmt->execute($params)) {
        error_log("Query execution failed: " . print_r($stmt->errorInfo(), true));
        die("Query execution failed. Check error log for details.");
    }
    $attendance_records = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("A database error occurred: " . $e->getMessage());
}

// Get statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT a.seller_id) as total_users,
        COUNT(a.id) as total_submissions,
        SUM(a.solds_quantity) as total_sales,
        DATE(MIN(a.attendance_date)) as earliest_date,
        DATE(MAX(a.attendance_date)) as latest_date
    FROM attendance a
    WHERE a.status = 'approved'
    AND a.total_sold_photo IS NOT NULL
";

if ($selected_user !== 'all') {
    $stats_query .= " AND a.seller_id = :user_id";
}
if (!empty($selected_date)) {
    $stats_query .= " AND a.attendance_date = :date";
} elseif (!empty($selected_month)) {
    $stats_query .= " AND DATE_FORMAT(a.attendance_date, '%Y-%m') = :month";
}

$stmt = $db->prepare($stats_query);
$stmt->execute($params);
$stats = $stmt->fetch();

$page_title = 'Attendance Photos';
include 'layout/header.php';
?>

<style>
.attendance-photos-container {
    padding: 2rem;
}

.photos-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
}

.photos-header h2 {
    margin: 0 0 0.5rem 0;
    font-size: 1.75rem;
    font-weight: 700;
}

.photos-header p {
    margin: 0;
    opacity: 0.9;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    display: block;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.85rem;
    opacity: 0.85;
    text-transform: uppercase;
    letter-spacing: 0.5px;
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
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-weight: 600;
    color: #2d3748;
    font-size: 0.9rem;
}

.filter-group select,
.filter-group input {
    padding: 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.2s;
    background: white;
    color: #2d3748;
    font-weight: 500;
}

.filter-group select option {
    color: #2d3748;
    background: white;
    padding: 0.5rem;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-actions {
    display: flex;
    gap: 0.75rem;
    align-items: flex-end;
}

.btn-filter {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-reset {
    padding: 0.75rem 1.5rem;
    background: #e2e8f0;
    color: #2d3748;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-reset:hover {
    background: #cbd5e0;
}

.photos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.photo-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    transition: all 0.3s;
}

.photo-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.photo-card-header {
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar-small {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 800;
    font-size: 1.1rem;
}

.user-info-small {
    flex: 1;
}

.user-name-small {
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 0.25rem;
}

.user-role-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-tenured {
    background: linear-gradient(135deg, #f093fb, #f5576c);
    color: white;
}

.badge-newbie {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: white;
}

.photo-image-container {
    position: relative;
    width: 100%;
    padding-top: 75%; /* 4:3 aspect ratio */
    background: #f7fafc;
    overflow: hidden;
    cursor: pointer;
}

.photo-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-card-body {
    padding: 1rem;
}

.photo-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.detail-icon {
    font-size: 1.1rem;
}

.detail-label {
    color: #718096;
    font-weight: 500;
}

.detail-value {
    color: #2d3748;
    font-weight: 600;
    margin-left: auto;
}

.no-results {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.no-results-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.no-results h3 {
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.no-results p {
    color: #718096;
}

/* Modal for viewing full image */
.photo-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    justify-content: center;
    align-items: center;
}

.photo-modal.active {
    display: flex;
}

.photo-modal-content {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

.photo-modal-close {
    position: absolute;
    top: 20px;
    right: 40px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    z-index: 10001;
}
</style>

<div class="attendance-photos-container">
    <div class="photos-header">
        <h2>📸 Approved Attendance Photos</h2>
        <p>View approved attendance submissions with verified sales data</p>
        
        <div class="stats-cards">
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($stats['total_users'] ?? 0); ?></span>
                <span class="stat-label">Users</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($stats['total_submissions'] ?? 0); ?></span>
                <span class="stat-label">Submissions</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($stats['total_sales'] ?? 0); ?></span>
                <span class="stat-label">Total Sales</span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="user_id">👤 Filter by User</label>
                    <select name="user_id" id="user_id">
                        <option value="all" <?php echo $selected_user === 'all' ? 'selected' : ''; ?>>All Users</option>
                        <?php foreach ($all_sellers as $seller): ?>
                            <option value="<?php echo $seller['id']; ?>" <?php echo $selected_user == $seller['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($seller['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="month">📅 Filter by Month</label>
                    <input type="month" name="month" id="month" value="<?php echo htmlspecialchars($selected_month); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date">📆 Filter by Specific Date</label>
                    <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($selected_date); ?>">
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-filter">🔍 Apply Filters</button>
                <button type="button" class="btn-reset" onclick="window.location.href='attendance-photos.php'">🔄 Reset</button>
            </div>
        </form>
    </div>

    <!-- Photos Grid -->
    <?php if (count($attendance_records) > 0): ?>
        <div class="photos-grid">
            <?php foreach ($attendance_records as $record): ?>
                <div class="photo-card">
                    <div class="photo-card-header">
                        <div class="user-avatar-small">
                            <?php echo strtoupper(substr($record['full_name'], 0, 1)); ?>
                        </div>
                        <div class="user-info-small">
                            <div class="user-name-small"><?php echo htmlspecialchars($record['full_name']); ?></div>
                            <span class="user-role-badge <?php echo $record['experienced_status'] === 'tenured' ? 'badge-tenured' : 'badge-newbie'; ?>">
                                <?php echo ucfirst($record['experienced_status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="photo-image-container" onclick="openPhotoModal('../<?php echo htmlspecialchars($record['total_sold_photo']); ?>')">
                        <img src="../<?php echo htmlspecialchars($record['total_sold_photo']); ?>" 
                             alt="Attendance Photo" 
                             class="photo-image"
                             onerror="this.src='../assets/images/no-image.png'">
                    </div>
                    
                    <div class="photo-card-body">
                        <div class="photo-details">
                            <div class="detail-row">
                                <span class="detail-icon">📅</span>
                                <span class="detail-label">Date:</span>
                                <span class="detail-value"><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-icon">⏰</span>
                                <span class="detail-label">Time Slot:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($record['slot_name'] ?? 'N/A'); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-icon">💰</span>
                                <span class="detail-label">Sales:</span>
                                <span class="detail-value"><?php echo number_format($record['solds_quantity']); ?> items</span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-icon">🕐</span>
                                <span class="detail-label">Hours:</span>
                                <span class="detail-value"><?php echo number_format($record['hours_worked'], 1); ?>h</span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-icon">📤</span>
                                <span class="detail-label">Submitted:</span>
                                <span class="detail-value"><?php echo date('M j, g:i A', strtotime($record['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-results">
            <div class="no-results-icon">📭</div>
            <h3>No Approved Photos Found</h3>
            <p>No approved attendance photos match your current filters. Try adjusting your search criteria or check the pending submissions page.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Photo Modal -->
<div class="photo-modal" id="photoModal" onclick="closePhotoModal()">
    <span class="photo-modal-close">&times;</span>
    <img class="photo-modal-content" id="modalImage">
</div>

<script>
function openPhotoModal(imageSrc) {
    const modal = document.getElementById('photoModal');
    const modalImg = document.getElementById('modalImage');
    modal.classList.add('active');
    modalImg.src = imageSrc;
}

function closePhotoModal() {
    const modal = document.getElementById('photoModal');
    modal.classList.remove('active');
}

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePhotoModal();
    }
});
</script>

<?php include 'layout/footer.php'; ?>
