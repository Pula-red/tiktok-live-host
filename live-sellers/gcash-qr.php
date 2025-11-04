<?php
require_once __DIR__ . '/../includes/functions.php';

// Require live seller role
require_role('live_seller');

// Get current user info
$current_user = get_logged_in_user();
$db = getDB();

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_qr') {
        $gcash_number = trim($_POST['gcash_number'] ?? '');
        $gcash_name = trim($_POST['gcash_name'] ?? '');
        
        // Validate inputs
        if (empty($gcash_number) || empty($gcash_name)) {
            $error_message = 'Please fill in all fields.';
        } elseif (!isset($_FILES['qr_code']) || $_FILES['qr_code']['error'] === UPLOAD_ERR_NO_FILE) {
            $error_message = 'Please upload a QR code image.';
        } else {
            // Validate file upload
            $file = $_FILES['qr_code'];
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
                $upload_dir = __DIR__ . '/../uploads/gcash/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'gcash_' . $current_user['id'] . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Check if user already has a QR code
                    $stmt = $db->prepare("SELECT id, qr_code_image FROM gcash_qr_codes WHERE user_id = ? AND is_active = 1");
                    $stmt->execute([$current_user['id']]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Delete old QR code file
                        $old_file = $upload_dir . basename($existing['qr_code_image']);
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                        
                        // Update existing record
                        $stmt = $db->prepare("
                            UPDATE gcash_qr_codes 
                            SET qr_code_image = ?, gcash_number = ?, gcash_name = ?, updated_at = CURRENT_TIMESTAMP 
                            WHERE id = ?
                        ");
                        $stmt->execute([$filename, $gcash_number, $gcash_name, $existing['id']]);
                        $success_message = 'GCash QR code updated successfully!';
                    } else {
                        // Insert new record
                        $stmt = $db->prepare("
                            INSERT INTO gcash_qr_codes (user_id, qr_code_image, gcash_number, gcash_name) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$current_user['id'], $filename, $gcash_number, $gcash_name]);
                        $success_message = 'GCash QR code uploaded successfully!';
                    }
                } else {
                    $error_message = 'Failed to save uploaded file. Please try again.';
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_qr') {
        // Delete QR code
        $stmt = $db->prepare("SELECT id, qr_code_image FROM gcash_qr_codes WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$current_user['id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Delete file
            $upload_dir = __DIR__ . '/../uploads/gcash/';
            $old_file = $upload_dir . basename($existing['qr_code_image']);
            if (file_exists($old_file)) {
                unlink($old_file);
            }
            
            // Soft delete (set is_active to 0)
            $stmt = $db->prepare("UPDATE gcash_qr_codes SET is_active = 0 WHERE id = ?");
            $stmt->execute([$existing['id']]);
            $success_message = 'GCash QR code deleted successfully!';
        }
    }
}

// Fetch current QR code
$stmt = $db->prepare("SELECT * FROM gcash_qr_codes WHERE user_id = ? AND is_active = 1");
$stmt->execute([$current_user['id']]);
$current_qr = $stmt->fetch();

$page_title = 'GCash QR Code';
include 'layout/header.php';
?>

<div class="gcash-qr-container">
    <div class="page-header">
        <h1>💳 GCash QR Code</h1>
        <p>Upload your GCash QR code for payment processing</p>
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

    <div class="gcash-content">
        <?php if ($current_qr): ?>
            <!-- Display Current QR Code -->
            <div class="current-qr-card">
                <h2>Your Current GCash QR Code</h2>
                <div class="qr-display">
                    <img src="../uploads/gcash/<?php echo htmlspecialchars($current_qr['qr_code_image']); ?>" 
                         alt="GCash QR Code" class="qr-image">
                    <div class="qr-info">
                        <div class="info-row">
                            <span class="label">GCash Number:</span>
                            <span class="value"><?php echo htmlspecialchars($current_qr['gcash_number']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Account Name:</span>
                            <span class="value"><?php echo htmlspecialchars($current_qr['gcash_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Uploaded:</span>
                            <span class="value"><?php echo date('F j, Y g:i A', strtotime($current_qr['uploaded_at'])); ?></span>
                        </div>
                    </div>
                </div>
                <div class="qr-actions">
                    <button type="button" class="btn-update" onclick="showUpdateForm()">Update QR Code</button>
                    <button type="button" class="btn-delete" onclick="confirmDelete()">Delete QR Code</button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Upload/Update Form -->
        <div class="upload-form-card" id="uploadFormCard" style="<?php echo $current_qr ? 'display: none;' : ''; ?>">
            <h2><?php echo $current_qr ? 'Update' : 'Upload'; ?> GCash QR Code</h2>
            <form method="POST" enctype="multipart/form-data" class="gcash-form">
                <input type="hidden" name="action" value="upload_qr">
                
                <div class="form-group">
                    <label for="gcash_number">GCash Number *</label>
                    <input type="text" id="gcash_number" name="gcash_number" 
                           placeholder="09XX XXX XXXX" 
                           value="<?php echo $current_qr ? htmlspecialchars($current_qr['gcash_number']) : ''; ?>"
                           required maxlength="15">
                </div>

                <div class="form-group">
                    <label for="gcash_name">Account Name *</label>
                    <input type="text" id="gcash_name" name="gcash_name" 
                           placeholder="Full name as registered in GCash"
                           value="<?php echo $current_qr ? htmlspecialchars($current_qr['gcash_name']) : ''; ?>"
                           required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="qr_code">QR Code Image *</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="qr_code" name="qr_code" 
                               accept="image/jpeg,image/jpg,image/png,image/gif" 
                               required onchange="previewImage(this)">
                        <div class="file-upload-info">
                            <span class="upload-icon">📁</span>
                            <span class="upload-text">Click to select image</span>
                            <span class="upload-hint">JPG, PNG, GIF (Max 5MB)</span>
                        </div>
                    </div>
                    <div id="imagePreview" class="image-preview" style="display: none;">
                        <img id="previewImg" src="" alt="Preview">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <span class="btn-icon">💾</span>
                        <?php echo $current_qr ? 'Update' : 'Upload'; ?> QR Code
                    </button>
                    <?php if ($current_qr): ?>
                        <button type="button" class="btn-cancel" onclick="hideUpdateForm()">Cancel</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Delete Confirmation Form (hidden) -->
        <form method="POST" id="deleteForm" style="display: none;">
            <input type="hidden" name="action" value="delete_qr">
        </form>
    </div>
</div>

<style>
.gcash-qr-container {
    max-width: 900px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.page-header {
    text-align: center;
    margin-bottom: 2rem;
}

.page-header h1 {
    font-size: 2rem;
    color: whitesmoke;
    margin-bottom: 0.5rem;
}

.page-header p {
    color: #e8e8e8;
    font-size: 1rem;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease-out;
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

.gcash-content {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.current-qr-card,
.upload-form-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
}

.current-qr-card h2,
.upload-form-card h2 {
    font-size: 1.5rem;
    color: #2d3748;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.qr-display {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.qr-image {
    width: 100%;
    max-width: 350px;
    height: auto;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.qr-info {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-row {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-row .label {
    font-size: 0.875rem;
    color: #718096;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-row .value {
    font-size: 1.125rem;
    color: #2d3748;
    font-weight: 500;
}

.qr-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.btn-update,
.btn-delete,
.btn-submit,
.btn-cancel {
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

.btn-update {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-update:hover {
    background: linear-gradient(135deg, #5568d3, #6a3f8f);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.gcash-form {
    display: flex;
    flex-direction: column;
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

.form-group input[type="text"] {
    padding: 0.75rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-group input[type="text"]:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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

.btn-submit {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-submit:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-cancel {
    background: #9ca3af;
    color: white;
}

.btn-cancel:hover {
    background: #6b7280;
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
    .qr-display {
        grid-template-columns: 1fr;
    }
    
    .qr-actions {
        flex-direction: column;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn-update,
    .btn-delete,
    .btn-submit,
    .btn-cancel {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
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

function showUpdateForm() {
    document.getElementById('uploadFormCard').style.display = 'block';
    document.getElementById('uploadFormCard').scrollIntoView({ behavior: 'smooth' });
}

function hideUpdateForm() {
    document.getElementById('uploadFormCard').style.display = 'none';
}

function confirmDelete() {
    if (confirm('Are you sure you want to delete your GCash QR code? This action cannot be undone.')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include 'layout/footer.php'; ?>
