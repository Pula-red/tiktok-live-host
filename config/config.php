<?php
// Database configuration for Laragon
define('DB_HOST', 'localhost');
define('DB_NAME', 'tiktok_live_host');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('SITE_NAME', 'TikTok Live Host Team');
define('SITE_URL', 'http://localhost/tiktok-live-host');        
define('SITE_ROOT', dirname(__DIR__));

// Security configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 6);

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting (disable in production)      
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    // Disable auto session start to prevent conflicts
    ini_set('session.auto_start', 0);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
    ini_set('session.use_strict_mode', 1);
}
?>