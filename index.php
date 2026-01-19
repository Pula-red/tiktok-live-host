<?php
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(get_user_dashboard_url($_SESSION['user_role']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Professional Live Streaming Team</title>  
    <meta name="description" content="Professional live streaming team connecting brands with live sellers for maximum engagement and sales;">
    <link rel="stylesheet" href="assets/css/style.css"> 
    <link rel="stylesheet" href="assets/css/gray-theme.css?v=<?php echo time(); ?>">
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <style>
        /* Full-page gray gradient (match login) */
        body, .hero, .hero-bg {
            background: linear-gradient(180deg, #2b2b2b 0%, #525252 60%, #808080 100%) !important;
            color: #e6e6e6 !important;
        }

        /* Make the animated hero background cover the viewport and stay behind content */
        .hero-bg {
            position: fixed !important;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            z-index: -1 !important;
            background-repeat: no-repeat !important;
            background-attachment: fixed !important;
            filter: none !important;
        }

        .hero-content { z-index: 2; position: relative; }

        /* Ensure hero title renders white (override theme gradients) */
        .hero-title, .hero-title span {
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            background: none !important;
            background-image: none !important;
            -webkit-background-clip: unset !important;
            background-clip: unset !important;
            mix-blend-mode: normal !important;
            text-shadow: 0 3px 14px rgba(0,0,0,0.75) !important;
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="hero-bg"></div>

    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <a href="/" class="logo">
                <div class="logo-icon">
                    <!-- Shopping bag icon for Live Commerce -->
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M6 2h12l1 4H5l1-4z" fill="#ffffff" opacity="0.0"/>
                        <path d="M6 7h12l-1 13a1 1 0 01-1 1H8a1 1 0 01-1-1L6 7z" stroke="#ffffff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 7V6a3 3 0 016 0v1" stroke="#ffffff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <span><?php echo SITE_NAME; ?></span>
            </a>
            <ul class="nav-links">
                <li><a href="#features">Features</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li> 
            </ul>
            <a href="login.php" class="login-btn">Login</a>
        </nav>
    </header>

    <!-- Hero Section -->
    <main class="hero">
        <div class="hero-content">
            <div class="live-badge">
                <div class="live-indicator"></div>
                <span>LIVE STREAMING TEAM</span>
            </div>
            
            <h1 class="hero-title" style="color:#ffffff !important; -webkit-text-fill-color:#ffffff !important; background-image:none !important; background:none !important; -webkit-background-clip:unset !important; background-clip:unset !important; mix-blend-mode:normal !important; text-shadow:0 2px 10px rgba(0,0,0,0.6) !important; font-weight:800 !important; margin:0;">
                Amplify Your Brand with<br>
                <span style="color:inherit !important; -webkit-text-fill-color:inherit !important; display:inline-block;">Live Commerce</span>
            </h1>
            
            <p class="hero-subtitle">
                Connect with professional live sellers and transform your products into engaging live streaming experiences. 
                Drive sales, build community, and maximize your brand's reach.
            </p>
            
            <div class="cta-buttons">
                <a href="login.php" class="cta-btn cta-primary">Get Started</a>
                <a href="#features" class="cta-btn cta-secondary">Learn More</a>
            </div>
        </div>
    </main>

    <!-- Features section removed -->

    <!-- JavaScript for animations -->
    <script src="assets/js/main.js"></script>
</body>
</html>