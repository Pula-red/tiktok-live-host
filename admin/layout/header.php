<?php
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../../config/config.php';
}

$current_user = get_logged_in_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?> Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/user-management.css?v=<?php echo time(); ?>">
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <style>
        /* Critical CSS to ensure sidebar renders correctly immediately */
        .admin-layout .sidebar {
            background: linear-gradient(180deg, #1a1d2e 0%, #16171f 100%) !important;
            border-right: 1px solid rgba(255, 255, 255, 0.08) !important;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3) !important;
        }
        .admin-layout .sidebar-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.06) !important;
            background: rgba(20, 22, 32, 0.5) !important;
        }
        .admin-layout .sidebar .logo-icon {
            background: linear-gradient(135deg, #667eea, #764ba2) !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25) !important;
        }
        .admin-layout .sidebar-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.06) !important;
            background: rgba(20, 22, 32, 0.5) !important;
        }
        .admin-layout .sidebar-footer .user-avatar {
            width: 42px !important;
            height: 42px !important;
            background: linear-gradient(135deg, #667eea, #764ba2) !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25) !important;
            border: 2px solid rgba(102, 126, 234, 0.2) !important;
            border-radius: 11px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 800 !important;
            color: white !important;
            font-size: 1.1rem !important;
        }
        .admin-layout .user-menu-toggle .user-avatar {
            width: 36px !important;
            height: 36px !important;
            border-radius: 10px !important;
            background: linear-gradient(135deg, #667eea, #764ba2) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #ffffff !important;
            font-size: 0.9rem !important;
            font-weight: 800 !important;
        }
        .admin-layout .dropdown-avatar {
            width: 42px !important;
            height: 42px !important;
            border-radius: 10px !important;
            background: linear-gradient(135deg, #667eea, #764ba2) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 800 !important;
            color: white !important;
            font-size: 1.1rem !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3) !important;
            border: 2px solid rgba(102, 126, 234, 0.2) !important;
        }
        .admin-layout .nav-link {
            color: rgba(255, 255, 255, 0.5) !important;
        }
        .admin-layout .nav-link.active {
            background: rgba(102, 126, 234, 0.15) !important;
            color: #8b94e7 !important;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.1) !important;
        }
        .admin-layout .nav-link.active::before {
            background: linear-gradient(180deg, #667eea, #764ba2) !important;
        }
        .admin-layout .nav-section-title {
            color: rgba(139, 148, 195, 0.6) !important;
        }
    </style>
</head>
<body class="admin-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <img src="../tik-tok.png" alt="TikTok" style="width: 28px; height: 28px; object-fit: contain;">
                </div>
                <span>Admin Panel</span>
            </div>
        </div>      

        <nav class="sidebar-nav">
            <ul class="nav-menu">
                <li class="nav-section-title">Overview</li>
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">🏠</span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-section-title">User Management</li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">➕</span>
                        <span class="nav-text">Create User</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="user_management.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'user_management.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">User Management</span>
                    </a>
                </li>
                
                <li class="nav-section-title">Attendance</li>
                <li class="nav-item">
                    <a href="attendance-photos.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'attendance-photos.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">📸</span>
                        <span class="nav-text">Attendance Photos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pending_attendance.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'pending_attendance.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">✓</span>
                        <span class="nav-text">Pending Attendance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pending_overtime.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'pending_overtime.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">⏱️</span>
                        <span class="nav-text">Pending Overtime</span>
                    </a>
                </li>
                
                <li class="nav-section-title">Payment</li>
                <li class="nav-item">
                    <a href="host-payments.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'host-payments.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">💰</span>
                        <span class="nav-text">Payment History</span>                   
                    </a>
                </li>
                <li class="nav-item">
                    <a href="gcash-qr-codes.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'gcash-qr-codes.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">💳</span>
                        <span class="nav-text">GCash QR Codes</span>
                    </a>
                </li>            
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></p>
                    <p class="user-role">Administrator</p>
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
                <h1 class="page-title"><?php echo isset($page_title) ? $page_title : 'Admin Panel'; ?></h1>
            </div>

            <div class="topbar-right">
                <div class="topbar-actions">
                  
                    
                    <div class="user-menu">
                        <button class="user-menu-toggle" id="userMenuToggle">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($current_user['username']); ?></span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        
                        <div class="user-dropdown" id="userDropdown">
                            <div class="dropdown-user-info">
                                <div class="dropdown-avatar">
                                    <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
                                </div>
                                <div class="dropdown-user-details">
                                    <div class="dropdown-user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></div>
                                    <div class="dropdown-user-role">Administrator</div>
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