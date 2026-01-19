<?php
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../../config/config.php';
}

$current_user = get_logged_in_user();

// Helper function to get correct profile image path
function get_profile_image_path_header($profile_image) {
    if (empty($profile_image)) {
        return '';
    }
    // Pages inside live-sellers/ are one level deeper, so prepend ../
    if (strpos($profile_image, 'uploads/profiles/') === 0) {
        return '../' . $profile_image;
    }
    return '../uploads/profiles/' . $profile_image;
}

// Generate a rounded-square gradient SVG avatar with user silhouette icon
function generate_profile_svg_data_uri_header($initialOrName, $size = 56) {
    $w = (int)$size;
    $h = $w;
    $iconScale = 0.5;
    $iconSize = $w * $iconScale;
    $iconOffset = ($w - $iconSize) / 2;
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}">
    <defs>
        <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
            <stop offset="0%" stop-color="#2a2a2a"/>
            <stop offset="50%" stop-color="#3a3a3a"/>
            <stop offset="100%" stop-color="#222323"/>
        </linearGradient>
    </defs>
    <rect rx="12" ry="12" width="{$w}" height="{$h}" fill="url(#g)"/>
    <g transform="translate({$iconOffset}, {$iconOffset}) scale({$iconScale})">
        <circle cx="28" cy="20" r="12" fill="#ffffff" opacity="0.95"/>
        <path d="M 14 56 C 14 45, 20 38, 28 38 C 36 38, 42 45, 42 56 Z" fill="#ffffff" opacity="0.95"/>
    </g>
</svg>
SVG;

    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

// Get user avatar image - show uploaded profile if available, otherwise SVG avatar
if (!empty($current_user['profile_image'])) {
    $user_avatar_img = get_profile_image_path_header($current_user['profile_image']);
} else {
    $user_avatar_img = generate_profile_svg_data_uri_header($current_user['full_name'], 42);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?> Live Seller</title>
    <?php $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '/tiktok-live-host' ; ?>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/live-seller.css?v=<?php echo time(); ?>">
    <link rel="icon" href="<?php echo $base; ?>/assets/images/favicon.ico" type="image/x-icon">
    <style>
        /* Grayscale critical CSS for live-seller sidebar and topbar */
        .live-seller-layout .sidebar {
            background: #000000 !important;
            border-right: 1px solid rgba(255,255,255,0.06) !important;
            box-shadow: 4px 0 20px rgba(0,0,0,0.6) !important;
        }
        .live-seller-layout .sidebar-header {
            border-bottom: 1px solid rgba(255,255,255,0.05) !important;
            background: rgba(0,0,0,0.6) !important;
        }
        .live-seller-layout .sidebar .logo-icon {
            background: linear-gradient(135deg,#1b1b1b,#2a2a2a) !important;
            box-shadow: 0 4px 12px rgba(255,255,255,0.04) !important;
        }
        .live-seller-layout .sidebar-footer {
            border-top: 1px solid rgba(255,255,255,0.05) !important;
            background: rgba(0,0,0,0.6) !important;
        }
        /* Force images to display properly in avatars */
        .live-seller-layout .sidebar-footer .user-avatar {
            position: relative !important;
            overflow: visible !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .live-seller-layout .sidebar-footer .user-avatar img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            z-index: 10 !important;
            border-radius: 11px !important;
        }
        .live-seller-layout .user-menu-toggle .user-avatar {
            position: relative !important;
            overflow: visible !important;
        }
        .live-seller-layout .user-menu-toggle .user-avatar img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
        }
        .live-seller-layout .nav-link { color: rgba(255,255,255,0.6) !important; }
        .live-seller-layout .nav-link:hover { background: rgba(255,255,255,0.03) !important; color: rgba(255,255,255,0.95) !important; }
        .live-seller-layout .nav-link.active {
            background: rgba(255,255,255,0.035) !important;
            color: var(--light-text) !important;
            box-shadow: 0 0 8px rgba(255,255,255,0.04) !important;
        }
        .live-seller-layout .nav-link.active::before { background: linear-gradient(180deg,#4a4a4a,#6a6a6a) !important; }
        .live-seller-layout .nav-section-title { color: rgba(255,255,255,0.5) !important; }
        /* Sidebar footer: pin to bottom and align avatar left */
        .live-seller-layout .sidebar { display:flex; flex-direction:column; }
        .live-seller-layout .sidebar .sidebar-nav { flex:1 1 auto; }
        .live-seller-layout .sidebar-footer { 
            flex:0 0 auto; 
            padding: 12px 14px !important; 
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        .live-seller-layout .sidebar-footer .user-info { 
            display:flex !important; 
            align-items:center !important; 
            gap:10px !important;
            visibility: visible !important;
        }
        .live-seller-layout .sidebar-footer .user-avatar { 
            width:44px !important; 
            height:44px !important; 
            border-radius:10px !important; 
            overflow:hidden !important;
            flex-shrink: 0 !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            background: linear-gradient(135deg, var(--gray-700), var(--gray-600)) !important;
        }
        .live-seller-layout .sidebar-footer .user-avatar img { 
            width:100% !important; 
            height:100% !important; 
            object-fit:cover !important; 
            display:block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        .live-seller-layout .sidebar-footer .user-details {
            display: block !important;
            visibility: visible !important;
        }
        .live-seller-layout .sidebar-footer .user-name,
        .live-seller-layout .sidebar-footer .user-role {
            display: block !important;
            visibility: visible !important;
        }
        /* Top-right compact user button: ensure avatar is visible and aligned */
        .topbar .user-menu-toggle {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px;
            padding: 8px 12px !important;
        }
        .topbar .user-menu-toggle .user-avatar {
            width: 36px !important;
            height: 36px !important;
            border-radius: 10px !important;
            overflow: hidden !important;
            display: inline-block !important;
            background: transparent !important;
        }
        .topbar .user-menu-toggle .user-avatar img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block !important;
        }
        /* Hide the large avatar inside the dropdown to avoid duplication */
        .user-dropdown .dropdown-avatar { display: none !important; }
    </style>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/gray-theme.css?v=<?php echo time(); ?>">
</head>
<body class="admin-layout live-seller-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <!-- Storefront icon -->
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M3 7h18l-1.5 13.5a1.5 1.5 0 01-1.5 1.5H6a1.5 1.5 0 01-1.5-1.5L3 7z" stroke="#ffffff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7 7V5a5 5 0 0110 0v2" stroke="#ffffff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <span>Live Seller</span>
            </div>
        </div>

        <nav class="sidebar-nav">       
            <ul class="nav-menu">
                <li class="nav-section-title">Main</li>
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">🏠</span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-section-title">Work</li>
                <li class="nav-item">
                    <a href="schedule.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'schedule.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">📅</span>
                        <span class="nav-text">My Schedule</span>
                    </a>
                </li>
                
                <li class="nav-section-title">Payment</li>
                <li class="nav-item">
                    <a href="gcash-qr.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'gcash-qr.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">💳</span>
                        <span class="nav-text">GCash QR Code</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="payment-history.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'payment-history.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">💰</span>
                        <span class="nav-text">Payment History</span>
                    </a>
                </li>
                
                <li class="nav-section-title">Reports</li>
                <li class="nav-item">
                    <a href="history.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'history.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">History</span>
                    </a>
                </li>
                
            </ul>
        </nav>

        <div class="sidebar-footer">    
            <div class="user-info">
                <div class="user-avatar">
                    <?php
                        // Determine if user has an uploaded profile image
                        if (!empty($current_user['profile_image'])) {
                            // Build the path to the profile image
                            $profile_path = $current_user['profile_image'];
                            
                            // If it already includes the full path, use it directly
                            if (strpos($profile_path, 'uploads/profiles/') === 0) {
                                $sidebar_avatar_url = '../' . $profile_path;
                            } else {
                                // Otherwise, prepend the uploads/profiles/ path
                                $sidebar_avatar_url = '../uploads/profiles/' . basename($profile_path);
                            }
                            
                            // Check if file exists
                            $file_check = __DIR__ . '/../../' . ltrim(str_replace('../', '', $sidebar_avatar_url), '/');
                            
                            if (file_exists($file_check)) {
                                // Show the uploaded image
                                echo '<img src="' . htmlspecialchars($sidebar_avatar_url) . '" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:11px;display:block;">';
                            } else {
                                // Show SVG avatar as fallback
                                echo '<img src="' . generate_profile_svg_data_uri_header($current_user['full_name'], 44) . '" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:11px;display:block;">';
                            }
                        } else {
                            // No profile image - show SVG avatar
                            echo '<img src="' . generate_profile_svg_data_uri_header($current_user['full_name'], 44) . '" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:11px;display:block;">';
                        }
                    ?>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></p>
                    <p class="user-role">Live Seller</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <h1 class="page-title"><?php echo isset($page_title) ? $page_title : 'Live Seller Panel'; ?></h1>
            </div>

            <div class="topbar-right">
                <div class="topbar-actions">
                    <!-- Live Status Indicator -->
                  
                    
                    <div class="user-menu">
                        <button class="user-menu-toggle" id="userMenuToggle">
                            <div class="user-avatar">
                                <img src="<?php echo htmlspecialchars($user_avatar_img); ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($current_user['username']); ?></span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        
                        <div class="user-dropdown" id="userDropdown">
                            <div class="dropdown-user-info">
                                <div class="dropdown-avatar">
                                    <img src="<?php echo htmlspecialchars($user_avatar_img); ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">
                                </div>
                                <div class="dropdown-user-details">
                                    <div class="dropdown-user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></div>
                                    <div class="dropdown-user-role">Live Seller</div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="../logout.php" class="dropdown-item">
                                <span class="item-icon">🚪</span>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="content-wrapper">