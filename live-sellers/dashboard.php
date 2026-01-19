<?php
require_once __DIR__ . '/../includes/functions.php';

// Require live_seller role
require_role('live_seller');

// Get database connection
$db = getDB();

// Get current pay period
$current_period = get_current_pay_period();
$days_until_reset = get_days_until_reset();

// AJAX endpoint for live seller dashboard data
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_seller_stats') {
    header('Content-Type: application/json');
    
    $seller_id = $_SESSION['user_id'];
    
    // Get user's stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT attendance_date) as total_working_days,
            COALESCE(SUM(hours_worked), 0) as total_working_hours,
            COALESCE(SUM(solds_quantity), 0) as total_sales
        FROM attendance 
        WHERE seller_id = ? AND status = 'approved'
            AND attendance_date BETWEEN ? AND ?
    ");
    $stmt->execute([$seller_id, $current_period['start_date'], $current_period['end_date']]);
    $user_stats = $stmt->fetch();
    
    // Get user's overtime stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as overtime_count,
            COALESCE(SUM(solds_quantity), 0) as overtime_sales,
            COALESCE(SUM(duration_hours), 0) as overtime_hours
        FROM overtime 
        WHERE seller_id = ? AND status = 'approved'
            AND overtime_date BETWEEN ? AND ?
    ");
    $stmt->execute([$seller_id, $current_period['start_date'], $current_period['end_date']]);
    $user_overtime = $stmt->fetch();
    
    // Combine stats: attendance + overtime
    $user_stats['total_working_hours'] = (float)$user_stats['total_working_hours'] + (float)$user_overtime['overtime_hours'];
    $user_stats['total_sales'] = (int)$user_stats['total_sales'] + (int)$user_overtime['overtime_sales'];
    
    // Get all users rankings (including overtime)
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.username,
            u.profile_image,
            COUNT(DISTINCT a.attendance_date) as working_days,
            COALESCE(SUM(a.hours_worked), 0) + COALESCE(SUM(o.duration_hours), 0) as working_hours,
            COALESCE(SUM(a.solds_quantity), 0) + COALESCE(SUM(o.solds_quantity), 0) as total_sales
        FROM users u
        LEFT JOIN attendance a ON u.id = a.seller_id 
            AND a.status = 'approved'
            AND a.attendance_date BETWEEN ? AND ?
        LEFT JOIN overtime o ON u.id = o.seller_id
            AND o.status = 'approved'
            AND o.overtime_date BETWEEN ? AND ?
        WHERE u.role = 'live_seller' AND u.status = 'active'
        GROUP BY u.id, u.full_name, u.username, u.profile_image
        ORDER BY total_sales DESC, working_hours DESC, working_days DESC
    ");
    $stmt->execute([$current_period['start_date'], $current_period['end_date'], $current_period['start_date'], $current_period['end_date']]);
    $all_users = $stmt->fetchAll();
    
    // Find current user's rank
    $current_rank = 0;
    foreach ($all_users as $index => $user) {
        if ($user['id'] == $seller_id) {
            $current_rank = $index + 1;
            break;
        }
    }
    
    echo json_encode([
        'success' => true,
        'user_stats' => [
            'working_days' => (int)$user_stats['total_working_days'],
            'working_hours' => (float)$user_stats['total_working_hours'],
            'total_sales' => (int)$user_stats['total_sales'],
            'current_rank' => $current_rank,
            'total_users' => count($all_users)
        ],
        'all_rankings' => $all_users
    ]);
    exit;
}

// Helper function to get correct profile image path
function get_profile_image_path($profile_image) {
    if (empty($profile_image)) {
        return '';
    }
    // If it already contains 'uploads/profiles/', just prepend ../
    if (strpos($profile_image, 'uploads/profiles/') === 0) {
        return '../' . $profile_image;
    }
    // Otherwise, it's just the filename, so add the full path
    return '../uploads/profiles/' . $profile_image;
}

// Generate a rounded-square gradient SVG avatar with user silhouette icon
function generate_profile_svg_data_uri($initialOrName, $size = 56) {
        $w = (int)$size;
        $h = $w;
        // User silhouette icon path - centered and scaled
        $iconScale = 0.5;
        $iconSize = $w * $iconScale;
        $iconOffset = ($w - $iconSize) / 2;
        
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}">
    <defs>
        <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
            <stop offset="0%" stop-color="#6b5cff"/>
            <stop offset="50%" stop-color="#8b62f2"/>
            <stop offset="100%" stop-color="#6b9bff"/>
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

// Get current user info with experienced_status
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

// Get current pay period
$current_period = get_current_pay_period();
$days_until_reset = get_days_until_reset();

// Get seller dashboard stats for current pay period

// Get user's total working days
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT attendance_date) as total_working_days 
    FROM attendance 
    WHERE seller_id = ? AND status = 'approved'
        AND attendance_date BETWEEN ? AND ?
");
$stmt->execute([$current_user['id'], $current_period['start_date'], $current_period['end_date']]);
$user_working_days = $stmt->fetch()['total_working_days'] ?? 0;

// Get user's total working hours
$stmt = $db->prepare("
    SELECT COALESCE(SUM(hours_worked), 0) as total_working_hours
    FROM attendance
    WHERE seller_id = ? AND status = 'approved'
        AND attendance_date BETWEEN ? AND ?
");
$stmt->execute([$current_user['id'], $current_period['start_date'], $current_period['end_date']]);
$user_working_hours = $stmt->fetch()['total_working_hours'] ?? 0;

// Get user's total sales (attendance + overtime)
$stmt = $db->prepare("
    SELECT COALESCE(SUM(solds_quantity), 0) as total_sales
    FROM attendance
    WHERE seller_id = ? AND status = 'approved'
        AND attendance_date BETWEEN ? AND ?
");
$stmt->execute([$current_user['id'], $current_period['start_date'], $current_period['end_date']]);
$attendance_sales = $stmt->fetch()['total_sales'] ?? 0;

$stmt = $db->prepare("
    SELECT COALESCE(SUM(solds_quantity), 0) as total_sales
    FROM overtime
    WHERE seller_id = ? AND status = 'approved'
        AND overtime_date BETWEEN ? AND ?
");
$stmt->execute([$current_user['id'], $current_period['start_date'], $current_period['end_date']]);
$overtime_sales = $stmt->fetch()['total_sales'] ?? 0;

$user_total_sales = $attendance_sales + $overtime_sales;

// Get all users with their stats for ranking (including overtime)
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.username,
        u.profile_image,
        COUNT(DISTINCT a.attendance_date) as working_days,
        COALESCE(SUM(a.hours_worked), 0) + COALESCE(SUM(o.duration_hours), 0) as working_hours,
        COALESCE(SUM(a.solds_quantity), 0) + COALESCE(SUM(o.solds_quantity), 0) as total_sales
    FROM users u
    LEFT JOIN attendance a ON u.id = a.seller_id 
        AND a.status = 'approved'
        AND a.attendance_date BETWEEN ? AND ?
    LEFT JOIN overtime o ON u.id = o.seller_id
        AND o.status = 'approved'
        AND o.overtime_date BETWEEN ? AND ?
    WHERE u.role = 'live_seller' AND u.status = 'active'
    GROUP BY u.id, u.full_name
    ORDER BY total_sales DESC, working_hours DESC, working_days DESC
");
$stmt->execute([$current_period['start_date'], $current_period['end_date'], $current_period['start_date'], $current_period['end_date']]);
$all_users_rankings = $stmt->fetchAll();

// Find current user's rank
$current_user_rank = 0;
foreach ($all_users_rankings as $index => $user) {
    if ($user['id'] == $current_user['id']) {
        $current_user_rank = $index + 1;
        break;
    }
}

$page_title = 'Live Seller Dashboard';
include 'layout/header.php';
?>

<div class="enhanced-dashboard">
    <!-- User Performance Card/Form -->
    <div class="user-performance-card">
        <div class="card-header-section">
            <div class="header-left">
                <div class="header-icon profile-badge">
                    <?php
                        // Show uploaded profile if available, otherwise SVG avatar
                        if (!empty($current_user['profile_image'])) {
                            $img_src = get_profile_image_path($current_user['profile_image']);
                        } else {
                            $img_src = generate_profile_svg_data_uri($current_user['full_name'], 56);
                        }
                    ?>
                    <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Profile" class="profile-badge-img">
                </div>
                <div class="header-text">
                    <h2 class="card-title">My Performance Dashboard</h2>
                    <p class="card-subtitle">Track your progress and achievements</p>
                </div>
            </div>
            <div class="header-right">
                <!-- Compact Pay Period Info -->
                <div class="compact-period-info">
                    <div class="period-badge">
                        <div class="period-icon-small">📅</div>
                        <div class="period-text">
                            <span class="period-label">CURRENT PERIOD:</span>
                            <span class="period-value"><?php echo $current_period['period_name']; ?></span>
                        </div>
                    </div>
                    <div class="countdown-badge">
                        <div class="countdown-number"><?php echo $days_until_reset; ?></div>
                        <div class="countdown-text">DAYS LEFT</div>
                    </div>
                </div>
            </div>
        </div>
               
        <div class="performance-form">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">👤</span>
                        Full Name
                    </label>
                    <div class="form-value">
                        <?php echo htmlspecialchars($current_user['full_name']); ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">🎯</span>
                        Experience Status
                    </label>
                    <div class="form-value">
                        <span class="role-badge-inline <?php echo $current_user['experienced_status'] === 'tenured' ? 'tenured-badge' : 'newbie-badge'; ?>">
                            <?php echo ucfirst($current_user['experienced_status']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">📅</span>
                        Total Working Days
                    </label>
                    <div class="form-value highlight-value">
                        <span class="value-number" id="user-working-days"><?php echo number_format($user_working_days); ?></span>
                        <span class="value-unit">days</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">⏰</span>
                        Total Working Hours
                    </label>
                    <div class="form-value highlight-value">
                        <span class="value-number" id="user-working-hours"><?php echo number_format($user_working_hours, 1); ?></span>
                        <span class="value-unit">hours</span>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">💰</span>
                        Total Sales
                    </label>
                    <div class="form-value highlight-value sales-value">
                        <span class="value-number" id="user-total-sales"><?php echo number_format($user_total_sales); ?></span>
                        <span class="value-unit">items</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">🏆</span>
                        Current Ranking
                    </label>
                    <div class="form-value highlight-value rank-value">
                        <span class="rank-display" id="user-rank-display">
                            <span class="rank-number">#<?php echo $current_user_rank; ?></span>
                            <span class="rank-total">of <?php echo count($all_users_rankings); ?></span>
                        </span>
                        <span class="rank-crown" id="user-rank-crown" style="display: <?php echo $current_user_rank <= 3 ? 'inline' : 'none'; ?>;">👑</span>
                    </div>
                </div>
            </div>
            
            <div class="performance-footer">
                <div class="performance-message" id="performance-message">
                    <?php if ($current_user_rank == 1): ?>
                        <span class="message-icon">🎉</span>
                        <span class="message-text">Congratulations! You're currently ranked #1!</span>
                    <?php elseif ($current_user_rank <= 3): ?>
                        <span class="message-icon">🌟</span>
                        <span class="message-text">Amazing! You're in the top 3 performers!</span>
                    <?php elseif ($current_user_rank <= 10): ?>
                        <span class="message-icon">💪</span>
                        <span class="message-text">Great job! You're in the top 10!</span>
                    <?php else: ?>
                        <span class="message-icon">🚀</span>
                        <span class="message-text">Keep pushing! You can climb higher!</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Live Sellers Ranking -->
    <div class="ranking-section">
        <div class="section-header">
            <div class="header-content">
                <h2 class="section-title">
                    Live Host Ranking
                </h2>
            </div>
        </div>
        
        <!-- Congratulations Banner -->
        <div class="congratulations-banner">
            <div class="congrats-text">CONGRATULATIONS!</div>
            <div class="congrats-subtitle">Live Ranking Highest Solds</div>
            <div class="congrats-date">Month of <?php echo date('F Y'); ?></div>
        </div>
        
        <!-- Enhanced Top 3 Podium -->
        <div class="podium-container">
            <div class="podium-background">
                <div class="bg-sparkle"></div>
                <div class="bg-rays"></div>
            </div>
            
            <div class="podium-positions" id="podium-positions">
                <?php if (count($all_users_rankings) >= 2): ?>
                    <!-- Rank 2 -->
                    <div class="podium-position rank-2 animate-rise" style="animation-delay: 0.3s">
                        <div class="position-platform">
                            <div class="platform-height silver-platform"></div>
                            <div class="platform-base">2</div>
                        </div>
                        <div class="contestant-info">
                            <div class="profile-circle silver-circle">
                                <div class="circle-glow silver-glow"></div>
                                <div class="avatar-content">
                                    <?php
                                        if (!empty($all_users_rankings[1]['profile_image'])) {
                                            $rank2_img = get_profile_image_path($all_users_rankings[1]['profile_image']);
                                        } else {
                                            $rank2_img = generate_profile_svg_data_uri($all_users_rankings[1]['username'] ?? $all_users_rankings[1]['full_name'], 120);
                                        }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($rank2_img); ?>" alt="Profile" class="profile-image" style="width:120px;height:120px;border-radius:50%;object-fit:cover;">
                                </div>
                                <div class="medal-overlay silver-medal">🥈</div>
                            </div>
                            <div class="contestant-details">
                                <h4 class="contestant-name"><?php echo htmlspecialchars($all_users_rankings[1]['username'] ?? $all_users_rankings[1]['full_name']); ?></h4>
                                <div class="performance-stats">
                                    <span class="hours">💰 <?php echo number_format($all_users_rankings[1]['total_sales']); ?> sales</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (count($all_users_rankings) >= 1): ?>
                    <!-- Rank 1 -->
                    <div class="podium-position rank-1 animate-rise" style="animation-delay: 0.1s">
                        <div class="champion-crown">
                            <span class="crown-icon">👑</span>
                            <div class="crown-sparkle"></div>
                        </div>
                        <div class="position-platform">
                            <div class="platform-height gold-platform"></div>
                            <div class="platform-base champion">1</div>
                        </div>
                        <div class="contestant-info">
                            <div class="profile-circle gold-circle">
                                <div class="circle-glow gold-glow"></div>
                                <div class="avatar-content">
                                    <?php
                                        if (!empty($all_users_rankings[0]['profile_image'])) {
                                            $rank1_img = get_profile_image_path($all_users_rankings[0]['profile_image']);
                                        } else {
                                            $rank1_img = generate_profile_svg_data_uri($all_users_rankings[0]['username'] ?? $all_users_rankings[0]['full_name'], 160);
                                        }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($rank1_img); ?>" alt="Profile" class="profile-image" style="width:160px;height:160px;border-radius:50%;object-fit:cover;">
                                </div>
                                <div class="medal-overlay gold-medal">🥇</div>
                            </div>
                            <div class="contestant-details">
                                <h4 class="contestant-name champion-name"><?php echo htmlspecialchars($all_users_rankings[0]['username'] ?? $all_users_rankings[0]['full_name']); ?></h4>
                                <div class="performance-stats">
                                    <span class="hours">💰 <?php echo number_format($all_users_rankings[0]['total_sales']); ?> sales</span>
                                </div>
                                <div class="champion-badge">🎯 Top Seller</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (count($all_users_rankings) >= 3): ?>
                    <!-- Rank 3 -->
                    <div class="podium-position rank-3 animate-rise" style="animation-delay: 0.5s">
                        <div class="position-platform">
                            <div class="platform-height bronze-platform"></div>
                            <div class="platform-base">3</div>
                        </div>
                        <div class="contestant-info">
                            <div class="profile-circle bronze-circle">
                                <div class="circle-glow bronze-glow"></div>
                                <div class="avatar-content">
                                    <?php
                                        if (!empty($all_users_rankings[2]['profile_image'])) {
                                            $rank3_img = get_profile_image_path($all_users_rankings[2]['profile_image']);
                                        } else {
                                            $rank3_img = generate_profile_svg_data_uri($all_users_rankings[2]['username'] ?? $all_users_rankings[2]['full_name'], 120);
                                        }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($rank3_img); ?>" alt="Profile" class="profile-image" style="width:120px;height:120px;border-radius:50%;object-fit:cover;">
                                </div>
                                <div class="medal-overlay bronze-medal">🥉</div>
                            </div>
                            <div class="contestant-details">
                                <h4 class="contestant-name"><?php echo htmlspecialchars($all_users_rankings[2]['username'] ?? $all_users_rankings[2]['full_name']); ?></h4>
                                <div class="performance-stats">
                                    <span class="hours">💰 <?php echo number_format($all_users_rankings[2]['total_sales']); ?> sales</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Enhanced Remaining Rankings -->
        <?php if (count($all_users_rankings) > 3): ?>
            <div class="extended-rankings">
                <div class="rankings-header">
                    <h3 class="rankings-title">
                        <span class="title-icon">📊</span>
                        Complete Leaderboard
                    </h3>
                    <div class="rankings-count">
                        <span class="count"><?php echo count($all_users_rankings) - 3; ?></span>
                        <span class="label">more sellers</span>
                    </div>
                </div>
                
                <div class="rankings-list">
                    <?php for ($i = 3; $i < count($all_users_rankings); $i++): ?>
                        <?php $user = $all_users_rankings[$i]; ?>
                        <?php $isCurrentUser = $user['id'] == $current_user['id']; ?>
                        <div class="ranking-row <?php echo $isCurrentUser ? 'current-user-row' : ''; ?> animate-slide-in" style="animation-delay: <?php echo ($i - 3) * 0.05; ?>s">
                            <?php if ($isCurrentUser): ?>
                                <div class="user-highlight">
                                    <span class="highlight-label">You</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="rank-position">
                                <span class="rank-number"><?php echo $i + 1; ?></span>
                                <?php if ($i + 1 <= 10): ?>
                                    <span class="top-ten-badge">TOP 10</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="user-profile">
                                        <div class="user-details">
                                            <h4 class="user-name"><?php echo htmlspecialchars($user['username'] ?? $user['full_name']); ?></h4>
                                    <div class="user-metrics">
                                        <div class="metric">
                                            <span class="metric-icon">💰</span>
                                            <span class="metric-value"><?php echo number_format($user['total_sales']); ?> sales</span>
                                        </div>
                                        <div class="metric">
                                            <span class="metric-icon">⏰</span>
                                            <span class="metric-value"><?php echo number_format($user['working_hours'], 1); ?>h</span>
                                        </div>
                                        <div class="metric">
                                            <span class="metric-icon">📅</span>
                                            <span class="metric-value"><?php echo number_format($user['working_days']); ?> days</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="performance-indicator">
                                <?php 
                                $performance_percent = $user['total_sales'] > 0 && $all_users_rankings[0]['total_sales'] > 0 ? 
                                    min(100, ($user['total_sales'] / $all_users_rankings[0]['total_sales']) * 100) : 0;
                                ?>
                                <div class="progress-ring">
                                    <svg class="progress-svg" width="40" height="40">
                                        <circle class="progress-circle-bg" cx="20" cy="20" r="15"></circle>
                                        <circle class="progress-circle" cx="20" cy="20" r="15" 
                                                style="stroke-dasharray: <?php echo $performance_percent * 0.94; ?> 100"></circle>
                                    </svg>
                                    <span class="progress-text"><?php echo round($performance_percent); ?>%</span>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'layout/footer.php'; ?>

<style>
/* Profile badge: rounded square gradient similar to attachments */
.profile-badge{width:56px;height:56px;border-radius:12px;flex:0 0 56px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#6b5cff 0%,#8b62f2 50%,#6b9bff 100%);box-shadow:0 6px 18px rgba(66,57,129,0.25);}
.profile-badge-img{width:100%;height:100%;object-fit:cover;display:block}
.profile-badge-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
.profile-initial{color:#fff;font-weight:700;font-size:20px;filter:drop-shadow(0 2px 6px rgba(0,0,0,0.4))}
.header-left{display:flex;align-items:center;gap:12px}
</style>

<script>
// AJAX Auto-refresh for seller dashboard
function refreshSellerDashboard() {
    fetch('dashboard.php?ajax_action=get_seller_stats')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            
            // Update user stats
            var stats = data.user_stats;
            
            // Update working days
            var daysEl = document.getElementById('user-working-days');
            if (daysEl) daysEl.textContent = formatNumber(stats.working_days);
            
            // Update working hours
            var hoursEl = document.getElementById('user-working-hours');
            if (hoursEl) hoursEl.textContent = formatNumber(stats.working_hours, 1);
            
            // Update total sales
            var salesEl = document.getElementById('user-total-sales');
            if (salesEl) salesEl.textContent = formatNumber(stats.total_sales);
            
            // Update rank
            var rankEl = document.getElementById('user-rank-display');
            if (rankEl) {
                rankEl.innerHTML = `
                    <span class="rank-number">#${stats.current_rank}</span>
                    <span class="rank-total">of ${stats.total_users}</span>
                `;
            }
            
            // Update crown visibility
            var crownEl = document.getElementById('user-rank-crown');
            if (crownEl) {
                crownEl.style.display = stats.current_rank <= 3 ? 'inline' : 'none';
            }
            
            // Update performance message
            var messageEl = document.getElementById('performance-message');
            if (messageEl) {
                var messageHTML = '';
                if (stats.current_rank == 1) {
                    messageHTML = '<span class="message-icon">🎉</span><span class="message-text">Congratulations! You\'re currently ranked #1!</span>';
                } else if (stats.current_rank <= 3) {
                    messageHTML = '<span class="message-icon">🌟</span><span class="message-text">Amazing! You\'re in the top 3 performers!</span>';
                } else if (stats.current_rank <= 10) {
                    messageHTML = '<span class="message-icon">💪</span><span class="message-text">Great job! You\'re in the top 10!</span>';
                } else {
                    messageHTML = '<span class="message-icon">🚀</span><span class="message-text">Keep pushing! You can climb higher!</span>';
                }
                messageEl.innerHTML = messageHTML;
            }
            
            // Update podium (top 3)
            updatePodium(data.all_rankings);
        })
        .catch(e => console.error('Dashboard refresh error:', e));
}

function updatePodium(rankings) {
    var podiumContainer = document.getElementById('podium-positions');
    if (!podiumContainer) return;
    
    // Clear current podium
    podiumContainer.innerHTML = '';
    
    // Rank 2 (if exists)
    if (rankings.length >= 2) {
        var rank2El = createPodiumPosition(rankings[1], 2, 'silver', '🥈', '0.3s');
        podiumContainer.appendChild(rank2El);
    }
    
    // Rank 1 (if exists)
    if (rankings.length >= 1) {
        var rank1El = createPodiumPosition(rankings[0], 1, 'gold', '🥇', '0.1s', true);
        podiumContainer.appendChild(rank1El);
    }
    
    // Rank 3 (if exists)
    if (rankings.length >= 3) {
        var rank3El = createPodiumPosition(rankings[2], 3, 'bronze', '🥉', '0.5s');
        podiumContainer.appendChild(rank3El);
    }
}

function createPodiumPosition(user, rank, medal, emoji, delay, isChampion) {
    var div = document.createElement('div');
    div.className = 'podium-position rank-' + rank + ' animate-rise';
    div.style.animationDelay = delay;
    
    // Handle profile image path like the PHP function does
    var profileImg;
    if (user.profile_image) {
        if (user.profile_image.indexOf('uploads/profiles/') === 0) {
            profileImg = '../' + user.profile_image;
        } else {
            profileImg = '../uploads/profiles/' + user.profile_image;
        }
    } else {
        profileImg = generateSVGDataUri(user.username || user.full_name, 120);
    }
    
    var html = '';
    
    if (isChampion) {
        html += '<div class="champion-crown"><span class="crown-icon">👑</span><div class="crown-sparkle"></div></div>';
    }
    
    html += `
        <div class="position-platform">
            <div class="platform-height ${medal}-platform"></div>
            <div class="platform-base ${isChampion ? 'champion' : ''}">${rank}</div>
        </div>
        <div class="contestant-info">
            <div class="profile-circle ${medal}-circle">
                <div class="circle-glow ${medal}-glow"></div>
                <div class="avatar-content">
                    <img src="${escapeHtml(profileImg)}" alt="Profile" class="profile-image" style="width:${isChampion ? 160 : 120}px;height:${isChampion ? 160 : 120}px;border-radius:50%;object-fit:cover;">
                </div>
                <div class="medal-overlay ${medal}-medal">${emoji}</div>
            </div>
            <div class="contestant-details">
                <h4 class="contestant-name ${isChampion ? 'champion-name' : ''}">${escapeHtml(user.username || user.full_name)}</h4>
                <div class="performance-stats">
                    <span class="hours">💰 ${formatNumber(user.total_sales)} sales</span>
                </div>
                ${isChampion ? '<div class="champion-badge">🎯 Top Seller</div>' : ''}
            </div>
        </div>
    `;
    
    div.innerHTML = html;
    return div;
}

function generateSVGDataUri(name, size) {
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" viewBox="0 0 ' + size + ' ' + size + '">' +
        '<defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1">' +
        '<stop offset="0%" stop-color="#6b5cff"/><stop offset="50%" stop-color="#8b62f2"/><stop offset="100%" stop-color="#6b9bff"/>' +
        '</linearGradient></defs>' +
        '<rect rx="12" ry="12" width="' + size + '" height="' + size + '" fill="url(#g)"/>' +
        '<g transform="translate(' + (size * 0.25) + ', ' + (size * 0.25) + ') scale(0.5)">' +
        '<circle cx="28" cy="20" r="12" fill="#ffffff" opacity="0.95"/>' +
        '<path d="M 14 56 C 14 45, 20 38, 28 38 C 36 38, 42 45, 42 56 Z" fill="#ffffff" opacity="0.95"/>' +
        '</g></svg>';
    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
}

function formatNumber(num, decimals) {
    if (decimals !== undefined) {
        return parseFloat(num).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }
    return parseFloat(num).toLocaleString('en-US');
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Start periodic refresh (every 10 seconds)
refreshSellerDashboard();
setInterval(refreshSellerDashboard, 10000);
</script>