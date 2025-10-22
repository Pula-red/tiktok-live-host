<?php
$page_title = "Update User";
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin role
require_role('admin');

$message = '';
$error = '';
$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    header('Location: user_management.php');
    exit;
}

// Get user data
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'User not found or cannot be edited.';
    header('Location: user_management.php');
    exit;
}

// Handle update action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $username = sanitize_input($_POST['username'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $experienced_status = sanitize_input($_POST['experienced_status'] ?? '');
        $status = sanitize_input($_POST['status'] ?? 'active');
        $password = $_POST['password'] ?? '';
        
        // Validation
        if (empty($full_name) || empty($username) || empty($email) || empty($experienced_status)) {
            $error = 'All fields except password are required.';
        } elseif (!validate_email($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (!empty($password) && strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            // Check if username or email already exists (excluding current user)
            $stmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $user_id]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                // Handle profile image upload
                $profile_image = null;
                $update_image = false;
                
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../uploads/profiles/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array(strtolower($file_extension), $allowed_extensions)) {
                        $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                            // Delete old profile image
                            $stmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
                            $stmt->execute([$user_id]);
                            $old_image = $stmt->fetchColumn();
                            
                            if ($old_image) {
                                $old_image_path = __DIR__ . '/../' . $old_image;
                                if (file_exists($old_image_path)) {
                                    unlink($old_image_path);
                                }
                            }
                            
                            $profile_image = 'uploads/profiles/' . $new_filename;
                            $update_image = true;
                        } else {
                            $error = 'Failed to upload profile image.';
                        }
                    } else {
                        $error = 'Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.';
                    }
                }
                
                if (empty($error)) {
                    // Build update query
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        if ($update_image) {
                            $sql = "UPDATE users SET username = ?, email = ?, password = ?, full_name = ?, profile_image = ?, experienced_status = ?, status = ? WHERE id = ?";
                            $params = [$username, $email, $hashed_password, $full_name, $profile_image, $experienced_status, $status, $user_id];
                        } else {
                            $sql = "UPDATE users SET username = ?, email = ?, password = ?, full_name = ?, experienced_status = ?, status = ? WHERE id = ?";
                            $params = [$username, $email, $hashed_password, $full_name, $experienced_status, $status, $user_id];
                        }
                    } else {
                        if ($update_image) {
                            $sql = "UPDATE users SET username = ?, email = ?, full_name = ?, profile_image = ?, experienced_status = ?, status = ? WHERE id = ?";
                            $params = [$username, $email, $full_name, $profile_image, $experienced_status, $status, $user_id];
                        } else {
                            $sql = "UPDATE users SET username = ?, email = ?, full_name = ?, experienced_status = ?, status = ? WHERE id = ?";
                            $params = [$username, $email, $full_name, $experienced_status, $status, $user_id];
                        }
                    }
                    
                    $stmt = $db->prepare($sql);
                    if ($stmt->execute($params)) {
                        log_activity($_SESSION['user_id'], 'update_user', "Updated user: $username");
                        $_SESSION['success_message'] = 'User updated successfully!';
                        header('Location: user_management.php');
                        exit;
                    } else {
                        $error = 'Failed to update user. Please try again.';
                    }
                }
            }
        }
        
        // Refresh user data after error
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
}

include __DIR__ . '/layout/header.php';
?>

<div class="update-user-container">
    <div class="page-header">
        <div class="header-content">
            <div class="header-icon">✏️</div>
            <div class="header-text">
                <h1>Update User</h1>
                <p>Edit user information and settings</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="user_management.php" class="btn btn-secondary">
                <span class="btn-icon">←</span>
                Back to User Management
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

    <div class="update-form-card">
        <div class="form-section-header">
            <h3>User Details</h3>
            <p class="form-subtitle">Update the information for <strong><?php echo htmlspecialchars($user['full_name']); ?></strong></p>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="update-user-form">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="update">
            
            <div class="form-grid">
                <div class="form-field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" 
                           value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    <small class="field-hint">Username must be unique</small>
                </div>
                
                <div class="form-field">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    <small class="field-hint">User's complete name</small>
                </div>
                
                <div class="form-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    <small class="field-hint">Valid email address</small>
                </div>
                
                <div class="form-field password-field">
                    <label for="password">New Password</label>
                    <div class="password-input-wrap">
                        <input type="password" id="password" name="password" minlength="6" aria-describedby="password-hint">
                        <button type="button" class="password-toggle" id="password-toggle" aria-label="Show password">👁️</button>
                    </div>
                    <small id="password-hint" class="field-hint">Leave empty to keep current password</small>
                </div>
                
                <div class="form-field">
                    <label for="experienced_status">Experience Status</label>
                    <select id="experienced_status" name="experienced_status" required>
                        <option value="newbie" <?php echo $user['experienced_status'] === 'newbie' ? 'selected' : ''; ?>>Newbie</option>
                        <option value="tenured" <?php echo $user['experienced_status'] === 'tenured' ? 'selected' : ''; ?>>Tenured</option>
                    </select>
                    <small class="field-hint">User's experience level</small>
                </div>
                
                <div class="form-field">
                    <label for="status">Account Status</label>
                    <select id="status" name="status" required>
                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <small class="field-hint">Enable or disable user account</small>
                </div>
                
                <div class="form-field file-field">
                    <label for="profile_image">Profile Image</label>
                    <?php if ($user['profile_image']): ?>
                        <div class="current-image">
                            <img src="../<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Current Profile">
                            <span class="image-label">Current Image</span>
                        </div>
                    <?php endif; ?>
                    <div class="file-upload-area">
                        <input type="file" id="profile_image" name="profile_image" 
                               accept="image/jpeg,image/jpg,image/png,image/gif">
                        <div class="file-upload-content">
                            <span class="upload-icon">📷</span>
                            <span class="upload-text">Choose new image to replace</span>
                        </div>
                    </div>
                    <small class="field-hint">Upload JPG, PNG, or GIF (optional)</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span class="btn-icon">💾</span>
                    Save Changes
                </button>
                <a href="user_management.php" class="btn btn-secondary">
                    <span class="btn-icon">✖</span>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// File upload preview functionality
document.getElementById('profile_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const uploadArea = this.closest('.file-upload-area');
    const uploadText = uploadArea.querySelector('.upload-text');
    
    if (file) {
        uploadText.textContent = file.name;
        uploadArea.classList.add('has-file');
    } else {
        uploadText.textContent = 'Choose new image to replace';
        uploadArea.classList.remove('has-file');
    }
});

// Form validation
document.querySelector('.update-user-form').addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    
    if (username.includes(' ')) {
        e.preventDefault();
        alert('Username cannot contain spaces');
        document.getElementById('username').focus();
        return;
    }
    
    if (password && password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long');
        document.getElementById('password').focus();
        return;
    }
});

// Password show/hide toggle
;(function() {
    const pwdInput = document.getElementById('password');
    const toggleBtn = document.getElementById('password-toggle');
    if (!pwdInput || !toggleBtn) return;

    toggleBtn.addEventListener('click', function() {
        const isPassword = pwdInput.type === 'password';
        pwdInput.type = isPassword ? 'text' : 'password';
        // Update icon and accessible label
        toggleBtn.textContent = isPassword ? '🙈' : '👁️';
        toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });

    // Optional: allow pressing Enter/Space on focused button to toggle (button already handles it)
})();
</script>

<style>
/* Minimal styles for password toggle alignment */
.password-input-wrap{position:relative;display:flex;align-items:center}
.password-input-wrap input[type="password"],
.password-input-wrap input[type="text"]{flex:1;padding-right:44px}
.password-toggle{position:absolute;right:6px;background:transparent;border:0;cursor:pointer;padding:6px;font-size:16px;line-height:1}
.password-toggle:focus{outline:2px solid #4d90fe;border-radius:4px}
</style>
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
