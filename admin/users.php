<?php
$page_title = "Create Users";
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin role
require_role('admin');

$message = '';
$error = '';

$db = getDB();

// AJAX endpoints for dynamic member management
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax_action'] === 'get_members') {
        $account_id = $_GET['account_id'] ?? 0;
        $stmt = $db->prepare("
            SELECT u.id, u.full_name, u.username 
            FROM account_members am 
            JOIN users u ON am.user_id = u.id 
            WHERE am.account_id = ? 
            ORDER BY u.full_name
        ");
        $stmt->execute([$account_id]);
        $members = $stmt->fetchAll();
        echo json_encode(['success' => true, 'members' => $members, 'count' => count($members)]);
        exit;
    }
    
    if ($_GET['ajax_action'] === 'get_assigned_users') {
        // Get all users who are currently assigned to any account
        $stmt = $db->query("SELECT DISTINCT user_id FROM account_members");
        $assigned_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get all live sellers for reference
        $stmt = $db->prepare("SELECT id, full_name, username FROM users WHERE role = 'live_seller' AND status = 'active' ORDER BY full_name");
        $stmt->execute();
        $all_users = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true, 
            'assigned_user_ids' => $assigned_ids,
            'all_users' => $all_users
        ]);
        exit;
    }
    
    if ($_GET['ajax_action'] === 'add_member') {
        $account_id = $_POST['account_id'] ?? 0;
        $user_id = $_POST['user_id'] ?? 0;
        
        // Check if already exists
        $check = $db->prepare("SELECT id FROM account_members WHERE account_id = ? AND user_id = ?");
        $check->execute([$account_id, $user_id]);
        
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Member already in account']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO account_members (account_id, user_id) VALUES (?, ?)");
        if ($stmt->execute([$account_id, $user_id])) {
            echo json_encode(['success' => true, 'message' => 'Member added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add member']);
        }
        exit;
    }
    
    if ($_GET['ajax_action'] === 'remove_member') {
        $account_id = $_POST['account_id'] ?? 0;
        $user_id = $_POST['user_id'] ?? 0;
        
        $stmt = $db->prepare("DELETE FROM account_members WHERE account_id = ? AND user_id = ?");
        if ($stmt->execute([$account_id, $user_id])) {
            echo json_encode(['success' => true, 'message' => 'Member removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove member']);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create_user';

    if ($action === 'create_account') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf_token)) {
            $error = 'Invalid CSRF token. Please try again.';
        } else {
            $account_name = trim($_POST['account_name'] ?? '');
            $member_ids = $_POST['members'] ?? [];
            if ($account_name === '') {
                $error = 'Account name is required.';
            } else {
                // create slug
                $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($account_name));
                $baseSlug = $slug; $i = 1;
                while (true) {
                    $stmt = $db->prepare("SELECT id FROM accounts WHERE slug = ?");
                    $stmt->execute([$slug]);
                    if (!$stmt->fetch()) break;
                    $slug = $baseSlug . '-' . $i++;
                }
                $stmt = $db->prepare("INSERT INTO accounts (name, slug) VALUES (?, ?)");
                if ($stmt->execute([$account_name, $slug])) {
                    $account_id = $db->lastInsertId();
                    $insertMember = $db->prepare("INSERT INTO account_members (account_id, user_id) VALUES (?, ?)");
                    foreach ($member_ids as $uid) {
                        $insertMember->execute([$account_id, (int)$uid]);
                    }
                    $message = 'Account created successfully.';
                } else {
                    $error = 'Failed to create account.';
                }
            }
        }
    } elseif ($action === 'create_user') {
        // Create user (default action)
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf_token)) {
            $error = 'Invalid CSRF token. Please try again.';
        } else {
            $full_name = sanitize_input($_POST['full_name'] ?? '');
            $username = sanitize_input($_POST['username'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $experienced_status = sanitize_input($_POST['experienced_status'] ?? '');

            // Validation
            if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($experienced_status)) {
                $error = 'All fields are required.';
            } elseif (!validate_email($email)) {
                $error = 'Please enter a valid email address.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } else {
                // Check if username or email already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $error = 'Username or email already exists.';
                } else {
                    // Handle profile image upload
                    $profile_image = null;
                    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = __DIR__ . '/../uploads/profiles/';
                        if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
                        $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                        if (in_array(strtolower($file_extension), $allowed_extensions)) {
                            $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
                            $upload_path = $upload_dir . $new_filename;
                            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                                $profile_image = 'uploads/profiles/' . $new_filename;
                            } else {
                                $error = 'Failed to upload profile image.';
                            }
                        } else {
                            $error = 'Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.';
                        }
                    }

                    if (empty($error)) {
                        // Insert new user
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO users (username, email, password, role, full_name, profile_image, experienced_status, status) VALUES (?, ?, ?, 'live_seller', ?, ?, ?, 'active')";
                        $stmt = $db->prepare($sql);
                        if ($stmt->execute([$username, $email, $hashed_password, $full_name, $profile_image, $experienced_status])) {
                            $new_user_id = $db->lastInsertId();
                            log_activity($_SESSION['user_id'], 'create_user', "Created new user: $username");
                            $message = 'User created successfully!';
                            // Clear form data
                            $full_name = $username = $email = $experienced_status = '';
                        } else {
                            $error = 'Failed to create user. Please try again.';
                        }
                    }
                }
            }
        }
    }
}

// Handle edit and delete actions for accounts when posted to this page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_account' && isset($_POST['account_id'])) {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf)) {
            $error = 'Invalid CSRF token.';
        } else {
            $aid = (int)$_POST['account_id'];
            $stmt = $db->prepare("DELETE FROM accounts WHERE id = ?");
            if ($stmt->execute([$aid])) {
                $message = 'Account deleted.';
            } else {
                $error = 'Failed to delete account.';
            }
        }
    }
    // Edit account (update name and members)
    if ($action === 'edit_account' && isset($_POST['account_id'])) {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf)) {
            $error = 'Invalid CSRF token.';
        } else {
            $aid = (int)$_POST['account_id'];
            $new_name = trim($_POST['account_name'] ?? '');
            $new_members = $_POST['members'] ?? [];
            if ($new_name === '') {
                $error = 'Account name required.';
            } else {
                $stmt = $db->prepare("UPDATE accounts SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                if ($stmt->execute([$new_name, $aid])) {
                    // replace members: delete existing then insert new
                    $db->prepare("DELETE FROM account_members WHERE account_id = ?")->execute([$aid]);
                    $ins = $db->prepare("INSERT INTO account_members (account_id, user_id) VALUES (?, ?)");
                    foreach ($new_members as $uid) {
                        $ins->execute([$aid, (int)$uid]);
                    }
                    $message = 'Account updated.';
                } else {
                    $error = 'Failed to update account.';
                }
            }
        }
    }
    // Remove a member from an account
    if ($action === 'remove_member' && isset($_POST['account_id']) && isset($_POST['user_id'])) {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf)) {
            $error = 'Invalid CSRF token.';
        } else {
            $aid = (int)$_POST['account_id'];
            $uid = (int)$_POST['user_id'];
            $del = $db->prepare("DELETE FROM account_members WHERE account_id = ? AND user_id = ?");
            if ($del->execute([$aid, $uid])) {
                $message = 'Member removed from account.';
            } else {
                $error = 'Failed to remove member.';
            }
        }
    }
    // Add one or more members to an account
    if ($action === 'add_member' && isset($_POST['account_id'])) {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf)) {
            $error = 'Invalid CSRF token.';
        } else {
            $aid = (int)$_POST['account_id'];
            $new_members = $_POST['members'] ?? [];
            if (!is_array($new_members)) $new_members = [$new_members];
            $ins = $db->prepare("INSERT INTO account_members (account_id, user_id) VALUES (?, ?)");
            $check = $db->prepare("SELECT COUNT(*) FROM account_members WHERE account_id = ? AND user_id = ?");
            $added = 0;
            foreach ($new_members as $uid) {
                $uid = (int)$uid;
                $check->execute([$aid, $uid]);
                if ($check->fetchColumn()) continue; // already member
                if ($ins->execute([$aid, $uid])) $added++;
            }
            if ($added) {
                $message = 'Member(s) added to account.';
            } else {
                $error = 'No members were added (they may already belong to the account).';
            }
        }
    }
}

// AJAX endpoints for member operations (returns JSON)
if ((isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_members') || (isset($_POST['ajax_action']) && in_array($_POST['ajax_action'], ['add_member_ajax','remove_member_ajax']))) {
    header('Content-Type: application/json');
    $action = $_GET['ajax_action'] ?? ($_POST['ajax_action'] ?? '');

    if ($action === 'get_members') {
        $aid = (int)($_GET['account_id'] ?? 0);
        if (!$aid) { echo json_encode(['success'=>false,'message'=>'account_id required']); exit; }
        $mstmt = $db->prepare("SELECT u.id, u.full_name, u.username FROM account_members am JOIN users u ON am.user_id = u.id WHERE am.account_id = ? ORDER BY u.full_name");
        $mstmt->execute([$aid]);
        $members = $mstmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'members'=>$members,'count'=>count($members)]);
        exit;
    }

    // For POST actions, verify CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']); exit;
    }

    if ($action === 'add_member_ajax') {
        $aid = (int)($_POST['account_id'] ?? 0);
        $new_members = $_POST['members'] ?? [];
        if (!is_array($new_members)) $new_members = [$new_members];
        $ins = $db->prepare("INSERT INTO account_members (account_id, user_id) VALUES (?, ?)");
        $check = $db->prepare("SELECT COUNT(*) FROM account_members WHERE account_id = ? AND user_id = ?");
        $added = 0;
        foreach ($new_members as $uid) {
            $uid = (int)$uid;
            $check->execute([$aid, $uid]);
            if ($check->fetchColumn()) continue;
            if ($ins->execute([$aid, $uid])) $added++;
        }
        // return updated members
        $mstmt = $db->prepare("SELECT u.id, u.full_name, u.username FROM account_members am JOIN users u ON am.user_id = u.id WHERE am.account_id = ? ORDER BY u.full_name");
        $mstmt->execute([$aid]);
        $members = $mstmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'added'=>$added,'members'=>$members,'count'=>count($members)]);
        exit;
    }

    if ($action === 'remove_member_ajax') {
        $aid = (int)($_POST['account_id'] ?? 0);
        $uid = (int)($_POST['user_id'] ?? 0);
        $del = $db->prepare("DELETE FROM account_members WHERE account_id = ? AND user_id = ?");
        $ok = $del->execute([$aid, $uid]);
        $mstmt = $db->prepare("SELECT u.id, u.full_name, u.username FROM account_members am JOIN users u ON am.user_id = u.id WHERE am.account_id = ? ORDER BY u.full_name");
        $mstmt->execute([$aid]);
        $members = $mstmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=> (bool)$ok, 'members'=>$members,'count'=>count($members)]);
        exit;
    }
}

include __DIR__ . '/layout/header.php';
?>

<style>
/* Password toggle inside input on the right */
.password-input-wrapper { position: relative; }
.password-input-wrapper input[type="password"],
.password-input-wrapper input[type="text"] {
    padding-right: 48px; /* make room for the toggle */
}
.password-toggle {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    padding: 6px;
    color: inherit;
}
.password-toggle:focus { outline: none; }
.password-field .field-hint { margin-top: 6px; }
.account-card { cursor: pointer; padding: 10px; border-radius: 10px; background: rgba(255,255,255,0.02); margin: 6px; box-sizing: border-box; width:100%; }
.account-card:hover { background: rgba(255,255,255,0.03); }
.account-member-list { display: none; margin-top: 8px; }
.account-card.expanded .account-member-list { display: block; }
.member-row .member-action .btn { white-space: nowrap; }

/* Create Account container styling - matches Create User form */
.create-account-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 1.5rem;
    background: transparent;
}

.account-form-card {
    background: linear-gradient(135deg, rgba(30, 30, 35, 0.95), rgba(20, 20, 25, 0.95));
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.04);
}

/* Use same form grid as Create User */
.account-form-card .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin-top: 24px;
}

.account-form-card .form-field {
    display: flex;
    flex-direction: column;
}

.account-form-card .form-field label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1px;
    color: #9aa3ad;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.account-form-card .form-control {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    color: #e6eef8;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 14px;
    transition: all 0.3s ease;
    width: 100%;
}

.account-form-card .form-control:focus {
    outline: none;
    border-color: rgba(255, 45, 122, 0.4);
    box-shadow: 0 0 0 3px rgba(255, 45, 122, 0.1);
    background: rgba(255, 255, 255, 0.04);
}

.account-form-card select.form-control {
    min-height: 200px;
    padding: 8px 12px;
}

.account-form-card select.form-control option.already-assigned {
    color: #666;
    font-style: italic;
    background: rgba(255, 0, 0, 0.1);
}

.account-form-card select.form-control option:disabled {
    color: #555;
    opacity: 0.6;
}

.account-form-card .field-hint {
    font-size: 12px;
    color: #6b7280;
    margin-top: 6px;
}

.account-form-card .form-actions {
    margin-top: 32px;
    display: flex;
    gap: 12px;
}

/* Enhanced Section Headers */
.account-form-card .form-section-header,
.accounts-list .card-header {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15));
    border: 1px solid rgba(102, 126, 234, 0.3);
    padding: 1.5rem 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.account-form-card .form-section-header {
    border-radius: 12px;
}

.accounts-list .card-header {
    border-radius: 0;
}

.account-form-card .form-section-header::before,
.accounts-list .card-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #667eea, #764ba2, #667eea);
    background-size: 200% 100%;
    animation: shimmerGradient 3s linear infinite;
}

@keyframes shimmerGradient {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.account-form-card .form-section-header h3,
.accounts-list .card-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.account-form-card .form-section-header h3::before {
    content: '🏢';
    font-size: 1.8rem;
    display: inline-block;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
}

.accounts-list .card-header h3::before {
    content: '📋';
    font-size: 1.8rem;
    display: inline-block;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
}

/* Accounts List container styling - matches Create User form */
.accounts-list-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 1.5rem;
    background: transparent;
}

.accounts-list {
    background: linear-gradient(135deg, rgba(30, 30, 35, 0.95), rgba(20, 20, 25, 0.95));
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.04);
}

/* Compact accounts container */
.accounts-scroll { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-start; }
.accounts-list .large-card .card-body { padding-bottom:18px; }
.account-name { font-size:18px; font-weight:700; }
.account-members { color: #b9c0c7; font-size:13px; margin-top:6px; }
.account-actions { margin-top:10px; }


.btn-primary { background: #ff2d7a; border-color: #ff2d7a; color: #fff; }
.btn-outline { background:#fff; color:#111; border-radius:8px; padding:8px 14px; }
.btn-danger { background:#ff5a5a; color:#fff; border-radius:8px; padding:8px 14px; }

/* Responsive tweaks */
@media (max-width: 900px) {
    .account-card { min-width: 100%; max-width: 100%; margin: 8px 0; }
}

/* Vertical card layout */
.account-card-vertical { display:flex; flex-direction:column; gap:12px; padding:18px; min-height:220px; }
.account-card-vertical .account-top { display:block; }
.account-metrics-vertical { border-top:1px solid rgba(255,255,255,0.02); padding-top:10px; color:#9aa3ad; font-size:12px; }
.metrics-label { font-size:11px; text-transform:uppercase; color:#6f7780; margin-bottom:6px; }
.metrics-sales { color:#2ecc71; font-weight:700; font-size:18px; margin-bottom:8px; }
.metrics-hours { color:#6fb8ff; font-weight:700; font-size:16px; }
.account-footer { display:flex; justify-content:space-between; align-items:center; margin-top:auto; }
.card-actions button { margin-left:8px; }
.contributors { background:rgba(255,255,255,0.02); padding:8px 12px; border-radius:8px; }



.accounts-scroll { gap:8px; }
.account-card { padding: 10px 12px; border-radius: 10px; background: rgba(255,255,255,0.01); box-shadow: none; width:100%; }
.account-card .account-top { display:flex; justify-content:space-between; align-items:center; gap:10px; position:relative; padding-right:64px; }
.account-name { font-size:0.98rem; font-weight:700; color:#fff; }
.account-members { font-size:0.82rem; color:#fff; background:#0b5cff; padding:4px 8px; border-radius:6px; position:absolute; right:8px; top:8px; font-weight:700; }
.member-badge { display:inline-block; background:#0b5cff; color:#fff; padding:6px 10px; border-radius:8px; font-weight:700; font-size:0.82rem; margin-top:6px; }
.member-list .member-row { padding:6px 8px; background: rgba(255,255,255,0.01); border-radius:6px; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center; }
.member-list .member-row .member-action .btn { padding:6px 8px; font-size:0.82rem; }

/* Make member-list scrollable when it has many members */
.member-list {
    max-height: 300px;
    overflow-y: auto;
    padding-right: 8px;
    margin-top: 10px;
}

/* Custom scrollbar for member list */
.member-list::-webkit-scrollbar {
    width: 8px;
}

.member-list::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}

.member-list::-webkit-scrollbar-thumb {
    background: rgba(255, 75, 134, 0.5);
    border-radius: 4px;
}

.member-list::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 75, 134, 0.7);
}

/* Also make the select dropdown scrollable */
.account-edit-form select.form-control[multiple] {
    max-height: 200px;
    overflow-y: auto;
}

.account-edit-form select.form-control[multiple]::-webkit-scrollbar {
    width: 8px;
}

.account-edit-form select.form-control[multiple]::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}

.account-edit-form select.form-control[multiple]::-webkit-scrollbar-thumb {
    background: rgba(255, 75, 134, 0.5);
    border-radius: 4px;
}

.account-edit-form select.form-control[multiple]::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 75, 134, 0.7);
}

/* Edit form inside card (compact) */
.account-edit-form { background: linear-gradient(180deg, rgba(255,255,255,0.01), rgba(255,255,255,0.005)); padding:10px; border-radius:8px; margin-top:10px; }
.account-edit-form .form-group { margin-bottom:8px; }
.account-edit-form label { font-size:0.82rem; color:#cbd5e1; margin-bottom:6px; display:block; }
.account-edit-form input.form-control, .account-edit-form select.form-control { padding:8px 10px; font-size:0.9rem; border-radius:6px; background: rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.03); color:#e6eef8; transition: opacity 0.3s ease; }
.account-edit-form select.form-control option.already-assigned { color: #666; font-style: italic; background: rgba(255,0,0,0.1); }
.account-edit-form select.form-control option:disabled { color: #555; opacity: 0.6; }
.account-edit-form .form-actions { display:flex; gap:10px; justify-content:flex-start; margin-top:6px; }
.account-edit-form .btn-primary { background: linear-gradient(90deg,#ff2d7a,#ff4ea3); border:none; color:white; padding:10px 18px; border-radius:12px; box-shadow: 0 8px 24px rgba(255,45,122,0.18); }
.account-edit-form .btn-secondary { background: rgba(0,0,0,0.35); border:1px solid rgba(255,255,255,0.03); color:#e6eef8; padding:8px 14px; border-radius:10px; }

/* Make the create form visually compact and aligned with account list height */
.accounts-grid { align-items: stretch; }
.accounts-form .card, .accounts-list .card { height: 100%; }

/* Remove old grid styles - now using vertical layout */
@media (max-width: 900px) {
    .accounts-scroll { grid-template-columns: 1fr; }
}
</style>

<div class="create-user-container">
    <div class="form-header">
        <div class="header-icon">👥</div>
        <div class="header-content">
            <h1>Create New User</h1>
            <p>Add new users to your TikTok Live Host Team.</p>
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

    <div class="user-form-card">
        <div class="form-section-header">
            <h3>User Information</h3>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="compact-user-form">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="form-grid">
                <div class="form-field">
                    <label for="username">USER NAME</label>
                    <input type="text" id="username" name="username" 
                           placeholder="Enter username" 
                           value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                    <small class="field-hint">Username must be unique and contain no spaces</small>
                </div>
                
                <div class="form-field">
                    <label for="full_name">FULL NAME</label>
                    <input type="text" id="full_name" name="full_name" 
                           placeholder="Enter full name" 
                           value="<?php echo htmlspecialchars($full_name ?? ''); ?>" required>
                    <small class="field-hint">Enter the user's complete name</small>
                </div>
                
                <div class="form-field">
                    <label for="email">EMAIL</label>
                    <input type="email" id="email" name="email" 
                           placeholder="Enter email address" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                    <small class="field-hint">Valid email address for login and notifications</small>
                </div>
                
                <div class="form-field password-field">
                    <label for="password">PASSWORD</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" 
                               placeholder="Enter password" minlength="6" required>
                        <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password">👁️</button>
                    </div>
                    <small class="field-hint">Password must be at least 6 characters long</small>
                </div>
                
                <div class="form-field">
                    <label for="experienced_status">Experience Status</label>
                    <select id="experienced_status" name="experienced_status" required>
                        <option value="">Select experience status</option>
                        <option value="newbie" <?php echo (($experienced_status ?? '') === 'newbie') ? 'selected' : ''; ?>>Newbie</option>
                        <option value="tenured" <?php echo (($experienced_status ?? '') === 'tenured') ? 'selected' : ''; ?>>Tenured</option>
                    </select>
                    <small class="field-hint">Choose the user's experience level</small>
                </div>
                
                <div class="form-field file-field">
                    <label for="profile_image">PROFILE IMAGE</label>
                    <div class="file-upload-area">
                        <input type="file" id="profile_image" name="profile_image" 
                               accept="image/jpeg,image/jpg,image/png,image/gif">
                        <div class="file-upload-content">
                            <span class="upload-icon">📷</span>
                            <span class="upload-text">Choose image file</span>
                        </div>
                    </div>
                    <small class="field-hint">Upload a profile picture (JPG, PNG, GIF)</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-create">
                    <span class="btn-icon">👤</span>
                    Create User
                </button>
                <button type="reset" class="btn-reset">
                    <span class="btn-icon">🔄</span>
                    Reset Form
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// --- Accounts management ---
// Fetch live sellers for member selection and existing accounts
$stmt = $db->prepare("SELECT id, full_name, username FROM users WHERE role = 'live_seller' AND status = 'active' ORDER BY full_name");
$stmt->execute();
$live_sellers = $stmt->fetchAll();

// Get list of users who are already in an account
$stmt = $db->query("SELECT DISTINCT user_id FROM account_members");
$assigned_user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $db->query("SELECT * FROM accounts ORDER BY name");
$accounts = $stmt->fetchAll();
?>

<!-- Create Account Form -->
<div class="create-account-container">
    <div class="account-form-card">
        <div class="form-section-header">
            <h3>Create Account</h3>
        </div>
        
        <form method="POST" action="users.php" id="createAccountForm">
            <input type="hidden" name="action" value="create_account">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="form-grid">
                <div class="form-field">
                    <label for="account_name">ACCOUNT NAME</label>
                    <input type="text" id="account_name" name="account_name" class="form-control" 
                           placeholder="Enter account name" required>
                    <small class="field-hint">Choose a unique name for this account</small>
                </div>
                
                <div class="form-field">
                    <label for="createAccountMembers">TEAM MEMBERS</label>
                    <select name="members[]" multiple size="8" class="form-control" id="createAccountMembers">
                        <?php foreach ($live_sellers as $u): ?>
                            <?php 
                            $is_assigned = in_array($u['id'], $assigned_user_ids);
                            $label = htmlspecialchars($u['full_name'] . ' (@' . $u['username'] . ')');
                            if ($is_assigned) {
                                $label .= ' [Already in an account]';
                            }
                            ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $is_assigned ? 'disabled class="already-assigned"' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint">Hold Ctrl (Cmd on Mac) to select multiple members</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-create">
                    <span class="btn-icon">🏢</span>
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Accounts List -->
<div class="accounts-list-container">
    <div class="accounts-list">
            <div class="card small-card">
                <div class="card-header"><h3>Account List</h3></div>
                <div class="card-body">
                    <form method="POST" action="users.php" id="accountsListForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <div id="accountsListExtras" style="display:none;"></div>
                        <?php if (empty($accounts)): ?>
                            <div class="member-empty">No accounts configured yet.</div>
                        <?php else: ?>
                                <div class="accounts-scroll">
                                <?php foreach ($accounts as $acct): ?>
                                    <?php
                                        // calculate today's totals for this account
                                        $s2 = $db->prepare("SELECT u.id FROM account_members am JOIN users u ON am.user_id = u.id WHERE am.account_id = ?");
                                        $s2->execute([$acct['id']]);
                                        $ids = $s2->fetchAll(PDO::FETCH_COLUMN);
                                        $sales_n = 0; $hours_n = 0.0;
                                        if (!empty($ids)) {
                                            $in = implode(',', array_fill(0, count($ids), '?'));
                                            $q = $db->prepare("SELECT SUM(solds_quantity) as sales, SUM(hours_worked) as hours FROM attendance WHERE seller_id IN ($in) AND (attendance_date = ? OR DATE(created_at) = ?) AND status IN ('completed', 'checked_in', 'pending_approval', 'approved')");
                                            $params = $ids; $params[] = date('Y-m-d'); $params[] = date('Y-m-d');
                                            $q->execute($params);
                                            $r = $q->fetch();
                                            $sales_n = (int)($r['sales'] ?? 0);
                                            $hours_n = (float)($r['hours'] ?? 0);
                                        }
                                    ?>
                                    <div class="account-row">
                                        <div class="account-card account-card-vertical" onclick="toggleAccountCard(this);">
                                            <div class="account-top">
                                                <div style="display:flex; flex-direction:column;">
                                                    <div class="account-name"><?php echo htmlspecialchars($acct['name']); ?></div>
                                                    <div class="member-badge">Members: <?php $s = $db->prepare("SELECT COUNT(*) FROM account_members WHERE account_id = ?"); $s->execute([$acct['id']]); echo (int)$s->fetchColumn(); ?></div>
                                                </div>
                                            </div>
                                            <div class="account-footer">
                                                    <div class="card-actions">
                                                    <button class="btn btn-outline" onclick="event.stopPropagation(); openAccountModal('<?php echo $acct['id']; ?>', this.closest('.account-row')); return false;">Edit</button>
                                                    <button class="btn btn-danger" onclick="event.stopPropagation(); openDeleteModal(<?php echo $acct['id']; ?>, '<?php echo addslashes(htmlspecialchars($acct['name'])); ?>'); return false;">Delete</button>
                                                </div>
                                            </div>
                                            <!-- hidden edit form area -->
                                            <div class="account-edit-form" id="edit-form-<?php echo $acct['id']; ?>" style="display:none;">
                                                <div>
                                                    <input type="hidden" id="edit_account_id_<?php echo $acct['id']; ?>" value="<?php echo $acct['id']; ?>">
                                                    <div class="form-group">
                                                        <label>Account name</label>
                                                        <input type="text" id="edit_account_name_<?php echo $acct['id']; ?>" class="form-control" value="<?php echo htmlspecialchars($acct['name']); ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Members</label>
                                                        <select id="edit_account_members_<?php echo $acct['id']; ?>" multiple size="6" class="form-control">
                                                            <?php foreach ($live_sellers as $u): ?>
                                                                <?php 
                                                                // Check if user is in THIS account
                                                                $m = $db->prepare("SELECT COUNT(*) FROM account_members WHERE account_id = ? AND user_id = ?");
                                                                $m->execute([$acct['id'], $u['id']]);
                                                                $is_in_this_account = (bool)$m->fetchColumn();
                                                                
                                                                // Check if user is in ANY OTHER account
                                                                $m2 = $db->prepare("SELECT COUNT(*) FROM account_members WHERE account_id != ? AND user_id = ?");
                                                                $m2->execute([$acct['id'], $u['id']]);
                                                                $is_in_other_account = (bool)$m2->fetchColumn();
                                                                
                                                                $label = htmlspecialchars($u['full_name'].' (@'.$u['username'].')');
                                                                if ($is_in_other_account) {
                                                                    $label .= ' [Already in another account]';
                                                                }
                                                                ?>
                                                                <option value="<?php echo $u['id']; ?>" 
                                                                    <?php echo $is_in_this_account ? 'selected' : ''; ?>
                                                                    <?php echo $is_in_other_account ? 'disabled class="already-assigned"' : ''; ?>
                                                                ><?php echo $label; ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-actions">
                                                        <button type="button" class="btn btn-primary" onclick="submitEditAccount(<?php echo $acct['id']; ?>);">Save</button>
                                                        <button type="button" class="btn btn-secondary" onclick="toggleEditForm(<?php echo $acct['id']; ?>)">Cancel</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php
                                                // render members inside the card, hidden until expanded
                                                $mstmt2 = $db->prepare("SELECT u.id, u.full_name, u.username FROM account_members am JOIN users u ON am.user_id = u.id WHERE am.account_id = ? ORDER BY u.full_name");
                                                $mstmt2->execute([$acct['id']]);
                                                $members_in = $mstmt2->fetchAll();
                                            ?>
                                            <div class="member-list">
                                                <?php if (empty($members_in)): ?>
                                                    <div class="member-row">No members</div>
                                                <?php else: ?>
                                                    <?php foreach ($members_in as $mem): ?>
                                                        <div class="member-row">
                                                            <div class="member-info"><?php echo htmlspecialchars($mem['full_name'].' (@'.$mem['username'].')'); ?></div>
                                                            <div class="member-action"><button class="btn btn-outline" type="button" onclick="event.stopPropagation(); openConfirmRemoveMember(<?php echo $acct['id']; ?>, <?php echo $mem['id']; ?>, '<?php echo addslashes(htmlspecialchars($mem['full_name'])); ?>', '<?php echo addslashes(htmlspecialchars($acct['name'])); ?>'); return false;">Remove</button></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
    </div>
</div>
<!-- End accounts list -->


<script>
function toggleEditForm(id) {
    var el = document.getElementById('edit-form-' + id);
    if (!el) return;
    if (el.style.display === 'none' || el.style.display === '') {
        el.style.display = 'block';
    } else {
        el.style.display = 'none';
    }
}
</script>

<!-- View-only Members Modal -->
<div id="viewModal" class="modal" style="display:none;">
    <div class="modal-backdrop" onclick="closeViewModal()"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="viewModalAccountName">Members</h3>
            <button class="modal-close" onclick="closeViewModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="modal-section-label">Members</div>
            <div id="viewModalMembersList"></div>
        </div>
    </div>
</div>

<style>
/* Slightly different look to distinguish view-only modal */
#viewModal .modal-dialog { width:520px; max-width:94%; }
#viewModal .modal-body { padding-top:6px; }
#viewModalMembersList .member-row { padding:10px; background: rgba(255,255,255,0.01); border-radius:8px; margin-bottom:8px; color:#e6eef8; }
#viewModal .modal-header h3 { color:#fff; }
/* label above the member list in view/edit modals */
.modal-section-label { font-weight:700; color:#fff; font-size:1rem; margin-bottom:8px; }

/* Make view modal scrollable - limit height and add scroll */
#viewModalMembersList {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 8px;
}

/* Custom scrollbar for view modal */
#viewModalMembersList::-webkit-scrollbar {
    width: 8px;
}

#viewModalMembersList::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}

#viewModalMembersList::-webkit-scrollbar-thumb {
    background: rgba(255, 75, 134, 0.5);
    border-radius: 4px;
}

#viewModalMembersList::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 75, 134, 0.7);
}
</style>

<script>
function openViewModal(accountId, rowEl) {
    if (!accountId) return;
    var acctRow = rowEl || document.querySelector('[data-account-id="'+accountId+'"]');
    var acctName = acctRow ? (acctRow.querySelector('.account-name') ? acctRow.querySelector('.account-name').innerText : 'Members') : 'Members';
    
    document.getElementById('viewModalAccountName').innerText = acctName + ' (Members: …)';
    var list = document.getElementById('viewModalMembersList');
    list.innerHTML = '<div class="member-row">Loading...</div>';
    
    // Fetch members via AJAX for real-time data
    fetch('users.php?ajax_action=get_members&account_id=' + encodeURIComponent(accountId), { credentials: 'same-origin' })
        .then(function(res){ return res.json(); })
        .then(function(data){
            if (!data.success) { 
                list.innerHTML = '<div class="member-row">Error loading members</div>'; 
                return; 
            }
            
            list.innerHTML = '';
            if (data.members && data.members.length) {
                data.members.forEach(function(m){
                    var div = document.createElement('div');
                    div.className = 'member-row';
                    div.textContent = m.full_name + ' (@' + m.username + ')';
                    list.appendChild(div);
                });
            } else {
                list.innerHTML = '<div class="member-row">No members</div>';
            }
            
            document.getElementById('viewModalAccountName').innerText = acctName + ' (Members: ' + (data.count || 0) + ')';
        })
        .catch(function(err){ 
            list.innerHTML = '<div class="member-row">Error loading members</div>'; 
            console.error(err); 
        });
    
    document.getElementById('viewModal').style.display = 'flex';
    var dlg = document.querySelector('#viewModal .modal-dialog');
    if (dlg) { dlg.classList.remove('opening'); void dlg.offsetWidth; dlg.classList.add('opening'); }
}

function closeViewModal(){ document.getElementById('viewModal').style.display = 'none'; }
</script>

<script>
// Toggle clickable account card to show/hide members
function toggleAccountCard(el) {
    // Open a read-only modal showing members for this account
    if (!el) return;
    var row = el.closest('.account-row');   
    if (!row) return;
    var acctId = row.getAttribute('data-account-id');
    openViewModal(acctId, row);
}

// Ensure clicking inside forms/buttons won't toggle the card; handled inline via stopPropagation on buttons and forms
</script>

<script>
function clearExtras() {
    const extras = document.getElementById('accountsListExtras');
    extras.innerHTML = '';
}

function addHidden(name, value) {
    const extras = document.getElementById('accountsListExtras');
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    extras.appendChild(input);
}

function submitEditAccount(aid) {
    clearExtras();
    addHidden('action', 'edit_account');
    addHidden('csrf_token', '<?php echo generate_csrf_token(); ?>');
    addHidden('account_id', aid);
    const nameEl = document.getElementById('edit_account_name_' + aid);
    addHidden('account_name', nameEl ? nameEl.value : '');
    const membersEl = document.getElementById('edit_account_members_' + aid);
    if (membersEl) {
        for (let i=0;i<membersEl.options.length;i++) {
            if (membersEl.options[i].selected) addHidden('members[]', membersEl.options[i].value);
        }
    }
    document.getElementById('accountsListForm').submit();
}

function submitRemoveMember(aid, uid) {
    clearExtras();
    addHidden('action', 'remove_member');
    addHidden('csrf_token', '<?php echo generate_csrf_token(); ?>');
    addHidden('account_id', aid);
    addHidden('user_id', uid);
    document.getElementById('accountsListForm').submit();
}

function submitDeleteAccount(aid) {
    clearExtras();
    addHidden('action', 'delete_account');
    addHidden('csrf_token', '<?php echo generate_csrf_token(); ?>');
    addHidden('account_id', aid);
    document.getElementById('accountsListForm').submit();
}
</script>

<script>
// AJAX helpers for adding/removing members
function updateCardMemberCount(accountId, newCount) {
    var row = document.querySelector('.account-row[data-account-id="'+accountId+'"]');
    if (!row) return;
    var badge = row.querySelector('.member-badge');
    if (!badge) return;
    badge.textContent = 'Members: ' + newCount;
}

// Fetch current assigned users and update all dropdowns
function refreshAssignedUsersGlobally() {
    // Add a subtle visual indicator that refresh is happening (optional)
    var allSelects = document.querySelectorAll('select[name="members[]"], [id^="edit_account_members_"], #modalAddMembers');
    allSelects.forEach(function(sel) {
        if (sel) sel.style.opacity = '0.6';
    });
    
    fetch('users.php?ajax_action=get_assigned_users', { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data.success) {
                // Restore opacity
                allSelects.forEach(function(sel) {
                    if (sel) sel.style.opacity = '1';
                });
                return;
            }
            
            var assignedIds = data.assigned_user_ids || [];
            
            // Update the Create Account form dropdown
            updateDropdownOptions('#createAccountMembers', assignedIds, null);
            
            // Update all Edit Account form dropdowns
            document.querySelectorAll('[id^="edit_account_members_"]').forEach(function(select){
                var accountIdMatch = select.id.match(/edit_account_members_(\d+)/);
                var currentAccountId = accountIdMatch ? accountIdMatch[1] : null;
                updateDropdownOptions(select, assignedIds, currentAccountId);
            });
            
            // Update modal dropdown (preserve original HTML for future resets)
            var modalSelect = document.getElementById('modalAddMembers');
            if (modalSelect) {
                // Save the updated version as the new "original"
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = modalSelect.dataset.origHtml || modalSelect.innerHTML;
                var tempSelect = tempDiv.querySelector('select') || tempDiv;
                
                // Rebuild the original HTML with current assignment status
                var newOrigHtml = '';
                data.all_users.forEach(function(user){
                    var isAssigned = assignedIds.indexOf(String(user.id)) !== -1 || assignedIds.indexOf(parseInt(user.id)) !== -1;
                    var label = user.full_name + ' (@' + user.username + ')';
                    if (isAssigned) {
                        label += ' [Already in an account]';
                    }
                    var disabled = isAssigned ? ' disabled class="already-assigned"' : '';
                    var dataAssigned = isAssigned ? '1' : '0';
                    newOrigHtml += '<option value="' + user.id + '"' + disabled + ' data-assigned="' + dataAssigned + '">' + escapeHtml(label) + '</option>';
                });
                
                modalSelect.dataset.origHtml = newOrigHtml;
            }
            
            // Restore opacity after update
            allSelects.forEach(function(sel) {
                if (sel) sel.style.opacity = '1';
            });
        })
        .catch(function(err){ 
            // Restore opacity on error
            allSelects.forEach(function(sel) {
                if (sel) sel.style.opacity = '1';
            });
        });
}

// Update a specific dropdown's options based on assigned users
function updateDropdownOptions(selectElement, assignedIds, currentAccountId) {
    var select = typeof selectElement === 'string' ? document.querySelector(selectElement) : selectElement;
    if (!select) {
        return;
    }
    
    // Store current selections
    var selectedValues = [];
    for (var i = 0; i < select.options.length; i++) {
        if (select.options[i].selected) {
            selectedValues.push(select.options[i].value);
        }
    }
    
    // Update each option
    var disabledCount = 0;
    for (var i = 0; i < select.options.length; i++) {
        var option = select.options[i];
        var userId = option.value;
        var isAssigned = assignedIds.indexOf(String(userId)) !== -1 || assignedIds.indexOf(parseInt(userId)) !== -1;
        
        // Get base user info (strip existing labels)
        var baseText = option.textContent.replace(/\s*\[Already in (an |another )?account\]/, '');
        
        if (currentAccountId) {
            // For edit forms: check if user is in THIS account vs OTHER accounts
            var isInThisAccount = option.selected;
            var isInOtherAccount = isAssigned && !isInThisAccount;
            
            if (isInOtherAccount) {
                option.disabled = true;
                option.className = 'already-assigned';
                option.textContent = baseText + ' [Already in another account]';
                disabledCount++;
            } else {
                option.disabled = false;
                option.className = '';
                option.textContent = baseText;
            }
        } else {
            // For create form: disable all assigned users
            if (isAssigned) {
                option.disabled = true;
                option.className = 'already-assigned';
                option.textContent = baseText + ' [Already in an account]';
                disabledCount++;
            } else {
                option.disabled = false;
                option.className = '';
                option.textContent = baseText;
            }
        }
    }
    
    // Restore selections (only for enabled options)
    for (var i = 0; i < select.options.length; i++) {
        var option = select.options[i];
        if (selectedValues.indexOf(option.value) !== -1 && !option.disabled) {
            option.selected = true;
        }
    }
}

function addMembersAjax(accountId, members) {
    var form = new FormData();
    form.append('ajax_action','add_member_ajax');
    form.append('csrf_token','<?php echo generate_csrf_token(); ?>');
    form.append('account_id', accountId);
    members.forEach(function(m){ form.append('members[]', m); });

    fetch('users.php', { method:'POST', body: form, credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data.success) { alert('Failed to add members: ' + (data.message || 'unknown')); return; }
            // update modal members list
            var list = document.getElementById('modalMembersList');
            list.innerHTML = '';
            if (data.members && data.members.length) {
                data.members.forEach(function(m){
                    var rowDiv = document.createElement('div'); rowDiv.className = 'member-row';
                    var left = document.createElement('div'); left.textContent = m.full_name + ' (@' + m.username + ')';
                    var right = document.createElement('div');
                    var rm = document.createElement('button'); rm.className = 'btn btn-outline'; rm.textContent = 'Remove';
                    rm.addEventListener('click', function(e){ e.preventDefault(); var acctNameEl = document.getElementById('modalAccountName'); var acctNameText = acctNameEl ? acctNameEl.innerText.replace(/\s*\(Members:.*\)$/,'') : ''; openConfirmRemoveMember(accountId, m.id, m.full_name, acctNameText); });
                    right.appendChild(rm);
                    rowDiv.appendChild(left); rowDiv.appendChild(right);
                    list.appendChild(rowDiv);
                });
            } else {
                list.innerHTML = '<div class="member-row">No members</div>';
            }
            // update counts on modal header and card badge
            var header = document.getElementById('modalAccountName'); if (header) header.innerText = header.innerText.replace(/\(Members:.*\)/,'(Members: ' + (data.count||0) + ')');
            updateCardMemberCount(accountId, data.count || 0);
            // refresh addSelect: restore original, then remove members of THIS account
            var addSelect = document.getElementById('modalAddMembers'); 
            if (addSelect && addSelect.dataset.origHtml) {
                addSelect.innerHTML = addSelect.dataset.origHtml;
                for (var i = addSelect.options.length - 1; i >= 0; i--) { 
                    var opt = addSelect.options[i]; 
                    if (data.members.some(function(mm){ return String(mm.id) === String(opt.value); })) {
                        addSelect.remove(i);
                    }
                }
            }
            
            // IMPORTANT: Refresh all dropdowns globally to update disabled states
            refreshAssignedUsersGlobally();
        })
        .catch(function(err){ alert('Error adding members'); });
}

function removeMemberAjax(accountId, userId) {
    var form = new FormData();
    form.append('ajax_action','remove_member_ajax');
    form.append('csrf_token','<?php echo generate_csrf_token(); ?>');
    form.append('account_id', accountId);
    form.append('user_id', userId);

    fetch('users.php', { method:'POST', body: form, credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data.success) { alert('Failed to remove member'); return; }
            var list = document.getElementById('modalMembersList');
            list.innerHTML = '';
            if (data.members && data.members.length) {
                data.members.forEach(function(m){
                    var rowDiv = document.createElement('div'); rowDiv.className = 'member-row';
                    var left = document.createElement('div'); left.textContent = m.full_name + ' (@' + m.username + ')';
                    var right = document.createElement('div');
                    var rm = document.createElement('button'); rm.className = 'btn btn-outline'; rm.textContent = 'Remove';
                    rm.addEventListener('click', function(e){ e.preventDefault(); var acctNameEl = document.getElementById('modalAccountName'); var acctNameText = acctNameEl ? acctNameEl.innerText.replace(/\s*\(Members:.*\)$/,'') : ''; openConfirmRemoveMember(accountId, m.id, m.full_name, acctNameText); });
                    right.appendChild(rm);
                    rowDiv.appendChild(left); rowDiv.appendChild(right);
                    list.appendChild(rowDiv);
                });
            } else {
                list.innerHTML = '<div class="member-row">No members</div>';
            }
            updateCardMemberCount(accountId, data.count || 0);
            // restore addSelect options and remove those that are now members of THIS account
            var addSelect = document.getElementById('modalAddMembers'); 
            if (addSelect && addSelect.dataset.origHtml) {
                addSelect.innerHTML = addSelect.dataset.origHtml;
                for (var i = addSelect.options.length - 1; i >= 0; i--) { 
                    var opt = addSelect.options[i]; 
                    if (data.members.some(function(mm){ return String(mm.id) === String(opt.value); })) {
                        addSelect.remove(i);
                    }
                }
            }
            
            // IMPORTANT: Refresh all dropdowns globally to update disabled states
            refreshAssignedUsersGlobally();
        })
        .catch(function(err){ alert('Error removing member'); });
}
</script>

<!-- Account modal -->
<div id="accountModal" class="modal" style="display:none;">
    <div class="modal-backdrop" onclick="closeAccountModal()"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="modalAccountName">Account</h3>
            <button class="modal-close" onclick="closeAccountModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="modal-section-label">Members</div>
            <div id="modalMembersList"></div>

            <div class="modal-add">
                <label for="modalAddMembers">Add members</label>
                <select id="modalAddMembers" multiple size="6" class="form-control">
                    <?php foreach ($live_sellers as $u): ?>
                        <?php 
                        $is_assigned = in_array($u['id'], $assigned_user_ids);
                        $label = htmlspecialchars($u['full_name'].' (@'.$u['username'].')');
                        if ($is_assigned) {
                            $label .= ' [Already in an account]';
                        }
                        ?>
                        <option value="<?php echo $u['id']; ?>" 
                            <?php echo $is_assigned ? 'disabled class="already-assigned"' : ''; ?>
                            data-assigned="<?php echo $is_assigned ? '1' : '0'; ?>"
                        ><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="margin-top:8px; display:flex; gap:8px;">
                    <button class="btn btn-primary" id="modalAddBtn">Add selected</button>
                    <button class="btn btn-secondary" onclick="closeAccountModal(); return false;">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display:none;">
    <div class="modal-backdrop" onclick="closeDeleteModal()"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="modal-close" onclick="closeDeleteModal()">✕</button>
        </div>
        <div class="modal-body delete-modal-body">
            <div style="text-align:center; margin:18px 0;">
                <div style="font-size:56px;">⚠️</div>
                <p style="margin-top:12px;">Are you sure you want to delete account <strong id="deleteTargetName"></strong>?</p>
                <p style="color:#cbd5e1;">This action cannot be undone.</p>
            </div>
            <div style="display:flex; gap:12px; justify-content:center; padding-top:12px;">
                <button class="btn btn-secondary" onclick="closeDeleteModal(); return false;">Cancel</button>
                <button id="deleteConfirmBtn" class="btn btn-danger">Delete Account</button>
            </div>
        </div>
    </div>
</div>

<!-- Generic Confirm Modal for member remove -->
<div id="confirmModal" class="modal" style="display:none;">
    <div class="modal-backdrop" onclick="closeConfirmModal()"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="confirmModalTitle">Confirm</h3>
            <button class="modal-close" onclick="closeConfirmModal()">✕</button>
        </div>
        <div class="modal-body">
            <div style="text-align:center; margin:18px 0;">
                <p id="confirmModalMessage"></p>
            </div>
            <div style="display:flex; gap:12px; justify-content:center; padding-top:12px;">
                <button class="btn btn-secondary" onclick="closeConfirmModal(); return false;">Cancel</button>
                <button id="confirmModalBtn" class="btn btn-danger">Confirm</button>
            </div>
        </div>
    </div>
</div>

<style>
.modal { position: fixed; inset:0; z-index:1200; display:flex; align-items:center; justify-content:center; }
.modal-backdrop { position:absolute; inset:0; background:rgba(0,0,0,0.6); }
.modal-dialog { position:relative; width:680px; max-width:96%; background:linear-gradient(180deg,#0f1113,#0a0b0d); border-radius:12px; border:1px solid rgba(255,255,255,0.04); padding:14px; box-shadow:0 12px 40px rgba(0,0,0,0.6); }
.modal-dialog.opening { animation: modalIn 220ms cubic-bezier(.2,.8,.2,1); }
@keyframes modalIn {
    from { transform: translateY(8px) scale(0.995); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.modal-header h3 { margin:0; color:#fff; }
.modal-close { background:transparent; border:none; color:#cbd5e1; font-size:18px; cursor:pointer; }
.modal-body { color:#cbd5e1; }
#modal .modal-section-label, .modal-section-label { font-weight:700; color:#fff; font-size:1rem; margin-bottom:8px; }
#modalMembersList .member-row { padding:8px; background:rgba(255,255,255,0.02); border-radius:8px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; }
.modal-add label { display:block; margin-bottom:6px; color:#cbd5e1; }
.modal-add .form-control { width:100%; background: rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.03); color:#e6eef8; border-radius:8px; padding:8px; transition: opacity 0.3s ease; }

/* Make accountModal member list scrollable */
#modalMembersList {
    max-height: 300px;
    overflow-y: auto;
    padding-right: 8px;
    margin-bottom: 16px;
}

/* Custom scrollbar for accountModal member list */
#modalMembersList::-webkit-scrollbar {
    width: 8px;
}

#modalMembersList::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}

#modalMembersList::-webkit-scrollbar-thumb {
    background: rgba(255, 75, 134, 0.5);
    border-radius: 4px;
}

#modalMembersList::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 75, 134, 0.7);
}

/* Make the Add Members dropdown scrollable */
#modalAddMembers {
    max-height: 200px;
    overflow-y: auto;
}

#modalAddMembers::-webkit-scrollbar {
    width: 8px;
}

#modalAddMembers::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}

#modalAddMembers::-webkit-scrollbar-thumb {
    background: rgba(255, 75, 134, 0.5);
    border-radius: 4px;
}

#modalAddMembers::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 75, 134, 0.7);
}

/* Style disabled options in modal */
#modalAddMembers option.already-assigned {
    color: #666;
    font-style: italic;
    background: rgba(255,0,0,0.1);
}

#modalAddMembers option:disabled {
    color: #555;
    opacity: 0.6;
}

/* Delete modal tweaks */
#deleteModal .modal-dialog { max-width:520px; }
#deleteModal .modal-body.delete-modal-body { padding-top:6px; }
#deleteModal .btn-danger { background:#ff5a5a; border:none; color:#fff; padding:10px 18px; border-radius:10px; }
#deleteModal .btn-secondary {
    background: linear-gradient(180deg,#0f1113,#0b0c0e);
    border: 1px solid rgba(255,255,255,0.06);
    color: #ffffff;
    padding: 10px 18px;
    border-radius: 10px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.45);
}
</style>

<script>
// AJAX-enabled modal open: fetch members then populate modal and add-select
function openAccountModal(accountId, rowEl) {
    accountId = accountId || (rowEl && rowEl.getAttribute && rowEl.getAttribute('data-account-id')) || null;
    var acctRow = rowEl || (accountId ? document.querySelector('[data-account-id="'+accountId+'"]') : null);
    var acctName = acctRow ? (acctRow.querySelector('.account-name') ? acctRow.querySelector('.account-name').innerText : 'Account') : 'Account';

    var header = document.getElementById('modalAccountName');
    var list = document.getElementById('modalMembersList');
    var addSelect = document.getElementById('modalAddMembers');
    if (!header || !list || !addSelect) return;

    header.innerText = acctName + ' (Members: …)';
    list.innerHTML = '<div class="member-row">Loading...</div>';

    // fetch members via AJAX
    fetch('users.php?ajax_action=get_members&account_id=' + encodeURIComponent(accountId), { credentials: 'same-origin' })
        .then(function(res){ return res.json(); })
        .then(function(data){
            if (!data.success) { list.innerHTML = '<div class="member-row">Error loading members</div>'; return; }
            // populate members list
            list.innerHTML = '';
            var existingIds = [];
            if (data.members && data.members.length) {
                data.members.forEach(function(m){
                    existingIds.push(String(m.id));
                    var rowDiv = document.createElement('div'); rowDiv.className = 'member-row';
                    var left = document.createElement('div'); left.textContent = m.full_name + ' (@' + m.username + ')';
                    var right = document.createElement('div');
                    var rm = document.createElement('button'); rm.className = 'btn btn-outline'; rm.textContent = 'Remove';
                    rm.addEventListener('click', function(e){ e.preventDefault(); openConfirmRemoveMember(accountId, m.id, m.full_name, acctName); });
                    right.appendChild(rm);
                    rowDiv.appendChild(left); rowDiv.appendChild(right);
                    list.appendChild(rowDiv);
                });
            } else {
                list.innerHTML = '<div class="member-row">No members</div>';
            }

            header.innerText = acctName + ' (Members: ' + (data.count || 0) + ')';

            // populate addSelect (restore original then remove existing members from THIS account)
            if (addSelect.dataset.origHtml) addSelect.innerHTML = addSelect.dataset.origHtml; else addSelect.dataset.origHtml = addSelect.innerHTML;
            for (var i = addSelect.options.length - 1; i >= 0; i--) {
                var opt = addSelect.options[i]; 
                // Remove if already in THIS account
                if (existingIds.indexOf(opt.value) !== -1) {
                    addSelect.remove(i);
                }
            }
        })
        .catch(function(err){ list.innerHTML = '<div class="member-row">Error</div>'; });

    // guard add button to use AJAX
    var addBtn = document.getElementById('modalAddBtn');
    if (addBtn) {
        addBtn.onclick = function(e){ e.preventDefault(); var sel = document.getElementById('modalAddMembers'); var selected = []; for (var j=0;j<sel.options.length;j++) if (sel.options[j].selected) selected.push(sel.options[j].value); if (selected.length === 0) { alert('Select at least one member to add'); return; } addMembersAjax(accountId, selected); };
    }

    var modal = document.getElementById('accountModal');
    if (modal) { modal.style.display = 'flex'; var dlg = modal.querySelector('.modal-dialog'); if (dlg) { dlg.classList.remove('opening'); void dlg.offsetWidth; dlg.classList.add('opening'); } }
}

function closeAccountModal(){
    var sel = document.getElementById('modalAddMembers');
    if (sel && sel.dataset.origHtml) sel.innerHTML = sel.dataset.origHtml;
    document.getElementById('accountModal').style.display = 'none';
}

// Delete modal control
function openDeleteModal(accountId, accountName) {
    var modal = document.getElementById('deleteModal');
    if (!modal) return;
    document.getElementById('deleteTargetName').innerText = accountName;
    modal.style.display = 'flex';
    var dlg = modal.querySelector('.modal-dialog'); if (dlg) { dlg.classList.remove('opening'); void dlg.offsetWidth; dlg.classList.add('opening'); }
    var confirmBtn = document.getElementById('deleteConfirmBtn');
    if (confirmBtn) {
        confirmBtn.onclick = function(e){ e.preventDefault(); closeDeleteModal(); submitDeleteAccount(accountId); };
    }
}

function closeDeleteModal(){
    var modal = document.getElementById('deleteModal');
    if (!modal) return;
    modal.style.display = 'none';
}

// Generic confirm modal handlers
function openConfirmRemoveMember(accountId, userId, userName, accountName) {
    var modal = document.getElementById('confirmModal');
    if (!modal) return;
    var msg = 'Remove ' + userName + ' from account ' + accountName + '?';
    document.getElementById('confirmModalTitle').innerText = 'Confirm Remove';
    document.getElementById('confirmModalMessage').innerText = msg;
    modal.style.display = 'flex';
    var dlg = modal.querySelector('.modal-dialog'); if (dlg) { dlg.classList.remove('opening'); void dlg.offsetWidth; dlg.classList.add('opening'); }
    var btn = document.getElementById('confirmModalBtn');
    if (btn) {
        btn.onclick = function(e){ e.preventDefault(); closeConfirmModal(); submitRemoveMember(accountId, userId); };
    }
}

function closeConfirmModal(){
    var modal = document.getElementById('confirmModal'); if (!modal) return; modal.style.display = 'none';
}

// Helper function for HTML escaping
function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Helper function to update the blue member count badge on the card
function updateCardMemberBadge(accountId) {
    fetch('users.php?ajax_action=get_members&account_id=' + encodeURIComponent(accountId), { credentials: 'same-origin' })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                var row = document.querySelector('[data-account-id="' + accountId + '"]');
                if (row) {
                    var badge = row.querySelector('.member-badge');
                    if (badge) {
                        badge.textContent = 'Members: ' + (data.count || 0);
                    }
                }
            }
        })
        .catch(function(err) {  });
}

// AJAX function to add multiple members
function addMembersAjax(accountId, userIds) {
    if (!userIds || userIds.length === 0) return;
    
    // Show loading state
    var addBtn = document.getElementById('modalAddBtn');
    var originalBtnText = addBtn.textContent;
    addBtn.disabled = true;
    addBtn.textContent = 'Adding...';
    
    var completed = 0;
    var failed = 0;
    
    userIds.forEach(function(userId) {
        var formData = new FormData();
        formData.append('account_id', accountId);
        formData.append('user_id', userId);
        
        fetch('users.php?ajax_action=add_member', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            completed++;
            if (!data.success) failed++;
            
            // When all requests complete, refresh the modal and badge
            if (completed === userIds.length) {
                addBtn.disabled = false;
                addBtn.textContent = originalBtnText;
                
                if (failed > 0) {
                    alert('Added ' + (completed - failed) + ' member(s). ' + failed + ' failed.');
                }
                // Refresh the modal to show updated list
                var row = document.querySelector('[data-account-id="' + accountId + '"]');
                openAccountModal(accountId, row);
                
                // Update the blue badge on the card
                updateCardMemberBadge(accountId);
                
                // IMPORTANT: Refresh all dropdowns globally
                refreshAssignedUsersGlobally();
            }
        })
        .catch(function(err) {
            completed++;
            failed++;
            
            if (completed === userIds.length) {
                addBtn.disabled = false;
                addBtn.textContent = originalBtnText;
                alert('Some members could not be added.');
                var row = document.querySelector('[data-account-id="' + accountId + '"]');
                openAccountModal(accountId, row);
                
                // Update the blue badge on the card
                updateCardMemberBadge(accountId);
                
                // IMPORTANT: Refresh all dropdowns globally
                refreshAssignedUsersGlobally();
            }
        });
    });
}

// AJAX function to remove a member
function submitRemoveMember(accountId, userId) {
    var formData = new FormData();
    formData.append('account_id', accountId);
    formData.append('user_id', userId);
    
    // Show loading in modal
    var list = document.getElementById('modalMembersList');
    var originalContent = list.innerHTML;
    
    fetch('users.php?ajax_action=remove_member', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            // Refresh the modal to show updated list
            var row = document.querySelector('[data-account-id="' + accountId + '"]');
            openAccountModal(accountId, row);
            
            // Update the blue badge on the card
            updateCardMemberBadge(accountId);
            
            // IMPORTANT: Refresh all dropdowns globally
            refreshAssignedUsersGlobally();
        } else {
            alert('Failed to remove member: ' + (data.message || 'Unknown error'));
            list.innerHTML = originalContent;
        }
    })
    .catch(function(err) {
        alert('Error removing member');
        list.innerHTML = originalContent;
    });
}

// Attach data-account-id attributes for each account-row at load
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.account-row').forEach(function(r){
        var idInput = r.querySelector('input[id^="edit_account_id_"]');
        if (idInput) r.setAttribute('data-account-id', idInput.value);
        // make the card clickable (open view modal) even if it has no members
        var cardEl = r.querySelector('.account-card');
        if (cardEl) {
            cardEl.addEventListener('click', function(e){
                // don't open modal when clicking action buttons or form controls inside the card
                if (e.target.closest('button') || e.target.closest('a') || e.target.closest('select') || e.target.closest('input')) return;
                openViewModal(r.getAttribute('data-account-id'), r);
            });
        }
        // add data-user-id to remove buttons in member list for modal ease
        r.querySelectorAll('.member-row .member-action button').forEach(function(btn){
            var uid = btn.getAttribute('onclick') || '';
            // try to parse user id from onclick string if present
            var m = uid.match(/submitRemoveMember\((\d+),\s*(\d+)\)/);
            if (m) btn.setAttribute('data-user-id', m[2]);
            btn.setAttribute('data-account-id', r.getAttribute('data-account-id'));
        });
        // wire Edit button to open edit modal reliably
        var editBtn = r.querySelector('.card-actions .btn-outline');
        if (editBtn) {
            editBtn.addEventListener('click', function(e){
                e.stopPropagation();
                var aid = r.getAttribute('data-account-id');
                openAccountModal(aid, r);
                return false;
            });
        }
    });
    
    // Always refresh assigned users on page load to ensure dropdowns are up to date
    // Slight delay to ensure DOM is fully ready
    setTimeout(function() {
        refreshAssignedUsersGlobally();
    }, 200);
});
</script>
    
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
        uploadText.textContent = 'Choose image file';
        uploadArea.classList.remove('has-file');
    }
});

// Form reset handler
document.querySelector('.btn-reset').addEventListener('click', function(e) {
    e.preventDefault();
    
    // Reset form
    document.querySelector('.compact-user-form').reset();
    
    // Reset file upload area
    const uploadArea = document.querySelector('.file-upload-area');
    const uploadText = uploadArea.querySelector('.upload-text');
    uploadText.textContent = 'Choose image file';
    uploadArea.classList.remove('has-file');
});

// Form validation enhancement
document.querySelector('.compact-user-form').addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    
    // Check for spaces in username
    if (username.includes(' ')) {
        e.preventDefault();
        alert('Username cannot contain spaces');
        document.getElementById('username').focus();
        return;
    }
    
    // Check password length
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long');
        document.getElementById('password').focus();
        return;
    }
});

// Password show/hide toggle
(function() {
    const pwdInput = document.getElementById('password');
    const toggleBtn = document.getElementById('passwordToggle');
    const form = document.querySelector('.compact-user-form');

    if (!pwdInput || !toggleBtn || !form) return;

    toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (pwdInput.type === 'password') {
            pwdInput.type = 'text';
            toggleBtn.textContent = '🙈';
            toggleBtn.setAttribute('aria-label', 'Hide password');
        } else {
            pwdInput.type = 'password';
            toggleBtn.textContent = '👁️';
            toggleBtn.setAttribute('aria-label', 'Show password');
        }
    });

    // Ensure reset restores masked state and icon
    form.addEventListener('reset', function() {
        setTimeout(() => {
            pwdInput.type = 'password';
            toggleBtn.textContent = '👁️';
            toggleBtn.setAttribute('aria-label', 'Show password');
        }, 0);
    });
})();
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>

