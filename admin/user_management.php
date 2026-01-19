<?php
$page_title = "User Management";
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin role
require_role('admin');

$message = '';
$error = '';

// Handle delete action
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['user_id'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        $user_id = (int)$_POST['user_id'];
        
        // Prevent deleting yourself
        if ($user_id === $_SESSION['user_id']) {
            $error = 'You cannot delete your own account.';
        } else {
            $db = getDB();
            
            // Get user info before deleting
            $stmt = $db->prepare("SELECT username, profile_image FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Delete profile image if exists
                if ($user['profile_image']) {
                    $image_path = __DIR__ . '/../' . $user['profile_image'];
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                
                // Delete user
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    log_activity($_SESSION['user_id'], 'delete_user', "Deleted user: {$user['username']}");
                    $message = 'User deleted successfully!';
                } else {
                    $error = 'Failed to delete user. Please try again.';
                }
            } else {
                $error = 'User not found.';
            }
        }
    }
}

// Check for success message from Update_user.php
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Check for error message
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get all users (exclude admins)
$db = getDB();
$search = $_GET['search'] ?? '';
$experience_filter = $_GET['experience'] ?? '';

$sql = "SELECT * FROM users WHERE role != 'admin'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($experience_filter)) {
    $sql .= " AND experienced_status = ?";
    $params[] = $experience_filter;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

include __DIR__ . '/layout/header.php';
?>

<div class="user-management-container">
    <div class="page-header">
        <div class="header-content">
            <div class="header-icon">👥</div>
            <div class="header-text">
                <h1>User Management</h1>
                <p>Manage live sellers in your Live Host Team</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="users.php" class="btn btn-primary">
                <span class="btn-icon">➕</span>
                Create New User
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✓</span>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <span class="alert-icon">⚠</span>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" placeholder="Search by name, username, or email..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <label for="experience">Experience</label>
                <select id="experience" name="experience">
                    <option value="">All Experience</option>
                    <option value="newbie" <?php echo $experience_filter === 'newbie' ? 'selected' : ''; ?>>Newbie</option>
                    <option value="tenured" <?php echo $experience_filter === 'tenured' ? 'selected' : ''; ?>>Tenured</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-filter">
                    <span class="btn-icon">🔍</span>
                    Filter
                </button>
                <a href="user_management.php" class="btn btn-reset">
                    <span class="btn-icon">🔄</span>
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="users-table-card">
        <div class="table-header">
            <h3>Live Sellers List (<?php echo count($users); ?>)</h3>
        </div>
        
        <div class="table-responsive">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Experience</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="no-data">
                                <div class="no-data-content">
                                    <span class="no-data-icon">📭</span>
                                    <p>No users found</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $user['experienced_status']; ?>">
                                        <?php echo ucfirst($user['experienced_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="Update_user.php?id=<?php echo $user['id']; ?>" class="btn-action btn-edit" data-tooltip="Edit User" aria-label="Edit <?php echo htmlspecialchars($user['full_name']); ?>">
                                            <span class="action-icon">✏️</span>
                                        </a>
                                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                            <button class="btn-action btn-delete" data-tooltip="Delete User" aria-label="Delete <?php echo htmlspecialchars($user['full_name']); ?>" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                                <span class="action-icon">🗑️</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header">
            <h2>Confirm Delete</h2>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        
        <form id="deleteUserForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" id="delete_user_id">
            
            <div class="modal-body">
                <div class="delete-warning">
                    <span class="warning-icon">⚠️</span>
                    <p>Are you sure you want to delete user <strong id="delete_username"></strong>?</p>
                    <p class="warning-text">This action cannot be undone.</p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <span class="btn-icon">🗑️</span>
                    Delete User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Delete Modal Functions
function confirmDelete(userId, username) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('delete_username').textContent = username;
    
    document.getElementById('deleteModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const deleteModal = document.getElementById('deleteModal');
    
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDeleteModal();
    }
});
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
