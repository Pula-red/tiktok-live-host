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
    <meta name="description" content="Professional TikTok Live streaming team connecting brands with live sellers for maximum engagement and sales.">
    <link rel="stylesheet" href="assets/css/style.css"> 
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
</head>
<body>
    <!-- Animated Background -->
    <div class="hero-bg"></div>

    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <a href="/" class="logo">
                <div class="logo-icon">
                    <img src="tik-tok.png" alt="TikTok" style="width: 28px; height: 28px; object-fit: contain;">
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
            
            <h1 class="hero-title">
                Amplify Your Brand with<br>
                <span style="background: linear-gradient(135deg, #25f4ee, #00bcd4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Live Commerce</span>
            </h1>
            
            <p class="hero-subtitle">
                Connect with professional live sellers and transform your products into engaging TikTok Live experiences. 
                Drive sales, build community, and maximize your brand's reach.
            </p>
            
            <div class="cta-buttons">
                <a href="login.php" class="cta-btn cta-primary">Get Started</a>
                <a href="#features" class="cta-btn cta-secondary">Learn More</a>
            </div>
        </div>
    </main>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">     
            
            <div class="features-grid">
                <div class="feature-card fade-in">
                    <div class="feature-icon">🎯</div>
                    <h3 class="feature-title">Targeted Audience</h3>
                    <p class="feature-description">
                        Reach your ideal customers through our network of specialized live sellers who understand your target market.
                    </p>
                </div>
                
                <div class="feature-card fade-in">
                    <div class="feature-icon">📊</div>
                    <h3 class="feature-title">Real-time Analytics</h3>
                    <p class="feature-description">
                        Track engagement, sales, and performance metrics in real-time to optimize your live streaming strategy.
                    </p>
                </div>
                
                <div class="feature-card fade-in">
                    <div class="feature-icon">💎</div>
                    <h3 class="feature-title">Premium Quality</h3>
                    <p class="feature-description">
                        Work with vetted, professional live sellers who deliver high-quality content and authentic brand representation.
                    </p>
                </div>
                
                <div class="feature-card fade-in">
                    <div class="feature-icon">🚀</div>
                    <h3 class="feature-title">Boost Sales</h3>
                    <p class="feature-description">
                        Increase conversion rates through interactive live demonstrations and real-time customer engagement.
                    </p>
                </div>
                
                <div class="feature-card fade-in">
                    <div class="feature-icon">🛡️</div>
                    <h3 class="feature-title">Secure Platform</h3>
                    <p class="feature-description">
                        Enterprise-grade security with encrypted transactions and protected user data for peace of mind.
                    </p>
                </div>
                
                <div class="feature-card fade-in">
                    <div class="feature-icon">⚡</div>
                    <h3 class="feature-title">Fast Setup</h3>
                    <p class="feature-description">
                        Get your live streaming campaigns up and running in minutes with our streamlined onboarding process.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- JavaScript for animations -->
    <script src="assets/js/main.js"></script>
</body>
</html>