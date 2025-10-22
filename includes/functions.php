<?php
require_once __DIR__ . '/database.php';

// Comprehensive session management
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
} elseif (session_status() === PHP_SESSION_DISABLED) {
    // Sessions are disabled, enable them
    ini_set('session.auto_start', 0);
    @session_start();
}

function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect('/tiktok-live-host/login.php');
    }
}

function require_role($required_role) {
    require_login();
    if ($_SESSION['user_role'] !== $required_role) {
        redirect('/tiktok-live-host/unauthorized.php');
    }
}

function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function log_activity($user_id, $action, $description = null) {
    $db = getDB();
    // If a numeric user_id was provided, verify the user exists. If not, set to null so FK won't fail.
    if (!empty($user_id)) {
        try {
            $check = $db->prepare("SELECT id FROM users WHERE id = ?");
            $check->execute([(int)$user_id]);
            $exists = (bool) $check->fetch();
            if (!$exists) {
                $user_id = null;
            }
        } catch (PDOException $e) {
            // If the check fails for some reason, fallback to null to avoid breaking logging.
            $user_id = null;
        }
    } else {
        $user_id = null;
    }

    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    try {
        $stmt->execute([
            $user_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        // Do not let activity logging break the application flow.
        // Log the error to the PHP error log for investigation.
        error_log('Activity log failed: ' . $e->getMessage());
    }
}

function authenticate_user($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        
        log_activity($user['id'], 'login', 'User logged in successfully');
        return true;
    }
    
    return false;
}

function logout_user() {
    if (is_logged_in()) {
        log_activity($_SESSION['user_id'], 'logout', 'User logged out');
    }
    
    session_destroy();
    
    // Only start new session if none exists
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
        session_regenerate_id(true);
    }
}

function get_user_dashboard_url($role) {
    $base_url = '/tiktok-live-host'; // Laragon project directory
    switch ($role) {
        case 'admin':
            return $base_url . '/admin/dashboard.php';
        case 'live_seller':
            return $base_url . '/live-sellers/dashboard.php';
        default:
            return $base_url . '/';
    }
}

/**
 * Get the current pay period dates
 * Returns array with 'start_date' and 'end_date'
 * Pay periods: 1-15 and 16-end of month
 */
function get_current_pay_period() {
    $current_day = (int)date('j');
    $current_month = date('Y-m');
    
    if ($current_day <= 15) {
        // First period: 1-15
        return [
            'start_date' => $current_month . '-01',
            'end_date' => $current_month . '-15',
            'period_name' => date('F j', strtotime($current_month . '-01')) . ' - ' . date('j, Y', strtotime($current_month . '-15'))
        ];
    } else {
        // Second period: 16-end of month
        $last_day = date('t'); // Last day of current month
        return [
            'start_date' => $current_month . '-16',
            'end_date' => $current_month . '-' . $last_day,
            'period_name' => date('F j', strtotime($current_month . '-16')) . ' - ' . date('j, Y', strtotime($current_month . '-' . $last_day))
        ];
    }
}

/**
 * Get a specific pay period by date
 * Returns array with 'start_date' and 'end_date'
 */
function get_pay_period_by_date($date) {
    $timestamp = strtotime($date);
    $day = (int)date('j', $timestamp);
    $month = date('Y-m', $timestamp);
    
    if ($day <= 15) {
        return [
            'start_date' => $month . '-01',
            'end_date' => $month . '-15',
            'period_name' => date('F j', strtotime($month . '-01')) . ' - ' . date('j, Y', strtotime($month . '-15'))
        ];
    } else {
        $last_day = date('t', $timestamp);
        return [
            'start_date' => $month . '-16',
            'end_date' => $month . '-' . $last_day,
            'period_name' => date('F j', strtotime($month . '-16')) . ' - ' . date('j, Y', strtotime($month . '-' . $last_day))
        ];
    }
}

/**
 * Get days remaining in current pay period
 */
function get_days_until_reset() {
    $period = get_current_pay_period();
    $end_date = strtotime($period['end_date']);
    $today = strtotime(date('Y-m-d'));
    $days = ceil(($end_date - $today) / 86400);
    return max(0, $days);
}
?>