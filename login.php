<?php
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(get_user_dashboard_url($_SESSION['user_role']));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            if (authenticate_user($username, $password)) {
                redirect(get_user_dashboard_url($_SESSION['user_role']));
            } else {
                $error = 'Invalid username or password.';
                log_activity(null, 'failed_login', "Failed login attempt for username: $username");
            }
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
</head>
<body>
    <!-- Animated Background -->
    <div class="hero-bg"></div>

    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <a href="/tiktok-live-host/" class="logo">
                <div class="logo-icon">
                    <img src="tik-tok.png" alt="TikTok" style="width: 28px; height: 28px; object-fit: contain;">
                </div>
                <span><?php echo SITE_NAME; ?></span>
            </a>
            <a href="/tiktok-live-host/" class="back-btn">← Back to Home</a>
        </nav>
    </header>

    <!-- Login Form -->
    <main class="login-container">
        <div class="login-form-wrapper">
            <div class="login-header">
                <div class="login-icon">🔐</div>
                <h1>Welcome Back</h1>
                <p>Sign in to your account to continue</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">⚠️</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✅</span>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="login-form" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        required 
                        autocomplete="username"
                        placeholder="Enter your username"
                    >
                    <div class="form-icon">👤</div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        autocomplete="current-password"
                        placeholder="Enter your password"
                    >
                    <div class="form-icon">🔒</div>
                    <button type="button" class="password-toggle" onclick="togglePassword()">👁️</button>
                </div>

                <button type="submit" class="login-btn">
                    <span class="btn-text">Sign In</span>
                    <div class="btn-loader"></div>
                </button>
            </form> 
                
                <div class="login-info">
                    <p>Secure login with encrypted data transmission</p>
                </div>
            </div>
        </div>
    </main>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        function fillDemo(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Add visual feedback
            const demoButtons = document.querySelectorAll('.demo-btn');
            demoButtons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            setTimeout(() => {
                event.target.classList.remove('active');
            }, 2000);
        }

        // Form submission animation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.login-btn');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoader = submitBtn.querySelector('.btn-loader');
            
            submitBtn.disabled = true;
            btnText.style.opacity = '0';
            btnLoader.style.display = 'block';
        });

        // Add floating animation to form
        document.addEventListener('DOMContentLoaded', function() {
            const formWrapper = document.querySelector('.login-form-wrapper');
            formWrapper.style.animation = 'fadeInUp 0.8s ease-out';
        });
    </script>
</body>
</html>