<?php
require_once __DIR__ . '/../includes/functions.php';

// Require admin role
require_role('admin');

// Get current user info
$current_user = get_logged_in_user();

// Get database connection
$db = getDB();

// Get current pay period
$current_period = get_current_pay_period();
$days_until_reset = get_days_until_reset();

// Check if filtering by specific pay period
$selected_period = null;
if (isset($_GET['period'])) {
    $period_parts = explode('|', $_GET['period']);
    if (count($period_parts) === 2) {
        $selected_period = [
            'start_date' => $period_parts[0],
            'end_date' => $period_parts[1],
            'period_name' => date('F j', strtotime($period_parts[0])) . ' - ' . date('j, Y', strtotime($period_parts[1]))
        ];
        $current_period = $selected_period;
    }
}

// Generate available pay periods (last 6 months)
function generate_pay_periods($months_back = 6) {
    $periods = [];
    $current_date = new DateTime();
    
    for ($i = 0; $i < $months_back; $i++) {
        $year = $current_date->format('Y');
        $month = $current_date->format('m');
        $month_name = $current_date->format('F');
        
        // First period: 1-15
        $periods[] = [
            'start_date' => "$year-$month-01",
            'end_date' => "$year-$month-15",
            'label' => "$month_name 1-15, $year",
            'value' => "$year-$month-01|$year-$month-15"
        ];
        
        // Second period: 16-end of month
        $last_day = $current_date->format('t');
        $periods[] = [
            'start_date' => "$year-$month-16",
            'end_date' => "$year-$month-$last_day",
            'label' => "$month_name 16-$last_day, $year",
            'value' => "$year-$month-16|$year-$month-$last_day"
        ];
        
        // Move to previous month
        $current_date->modify('-1 month');
    }
    
    return $periods;
}

// Generate available years, months, and periods for the hierarchical selector
function generate_hierarchical_periods($years_back = 2) {
    $periods_data = [];
    $current_date = new DateTime();
    
    // Generate data for the last X years
    for ($y = 0; $y <= $years_back; $y++) {
        $year = (int)$current_date->format('Y') - $y;
        $periods_data[$year] = [];
        
        // Determine which months to include
        $start_month = ($year == (int)date('Y')) ? (int)date('m') : 12;
        
        for ($m = $start_month; $m >= 1; $m--) {
            $month_name = date('F', mktime(0, 0, 0, $m, 1));
            $last_day = date('t', mktime(0, 0, 0, $m, 1, $year));
            
            $periods_data[$year][$m] = [
                'name' => $month_name,
                'periods' => [
                    [
                        'label' => '1-15',
                        'start_date' => sprintf('%d-%02d-01', $year, $m),
                        'end_date' => sprintf('%d-%02d-15', $year, $m),
                        'value' => sprintf('%d-%02d-01|%d-%02d-15', $year, $m, $year, $m)
                    ],
                    [
                        'label' => '16-' . $last_day,
                        'start_date' => sprintf('%d-%02d-16', $year, $m),
                        'end_date' => sprintf('%d-%02d-%s', $year, $m, $last_day),
                        'value' => sprintf('%d-%02d-16|%d-%02d-%s', $year, $m, $year, $m, $last_day)
                    ]
                ]
            ];
        }
    }
    
    return $periods_data;
}

$hierarchical_periods = generate_hierarchical_periods();

// AJAX endpoint for performance rankings
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_rankings') {
    header('Content-Type: application/json');
    
    // Use the same period logic for AJAX requests
    $ajax_period = $current_period;
    if (isset($_GET['period'])) {
        $period_parts = explode('|', $_GET['period']);
        if (count($period_parts) === 2) {
            $ajax_period = [
                'start_date' => $period_parts[0],
                'end_date' => $period_parts[1],
                'period_name' => date('F j', strtotime($period_parts[0])) . ' - ' . date('j, Y', strtotime($period_parts[1]))
            ];
        }
    }
    
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.username,
            u.experienced_status,
            u.profile_image,
            COALESCE(SUM(a.solds_quantity), 0) as total_sales,
            COALESCE(SUM(a.hours_worked), 0) as total_hours,
            COUNT(DISTINCT a.attendance_date) as working_days
        FROM users u
        LEFT JOIN attendance a ON u.id = a.seller_id 
            AND a.status = 'approved'
            AND a.attendance_date BETWEEN :start_date AND :end_date
        WHERE u.role = 'live_seller' AND u.status = 'active'
        GROUP BY u.id, u.full_name, u.username, u.experienced_status, u.profile_image
        ORDER BY total_sales DESC, total_hours DESC
    ");
    $stmt->execute([
        ':start_date' => $ajax_period['start_date'],
        ':end_date' => $ajax_period['end_date']
    ]);
    $user_rankings = $stmt->fetchAll();
    
    // Calculate hourly rates and total earned for each seller
    foreach ($user_rankings as &$seller) {
        $seller['hourly_rate'] = ($seller['experienced_status'] === 'tenured') ? 166 : 125;
        $seller['total_earned'] = $seller['total_hours'] * $seller['hourly_rate'];
    }
    unset($seller);
    
    // Calculate overall totals
    $total_sales = array_sum(array_column($user_rankings, 'total_sales'));
    $total_hours = array_sum(array_column($user_rankings, 'total_hours'));
    $total_earned = array_sum(array_column($user_rankings, 'total_earned'));
    $total_sellers = count($user_rankings);
    
    echo json_encode([
        'success' => true,
        'rankings' => $user_rankings,
        'totals' => [
            'total_sales' => $total_sales,
            'total_hours' => $total_hours,
            'total_earned' => $total_earned,
            'total_sellers' => $total_sellers
        ]
    ]);
    exit;
}

// Handle account creation and membership assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Account mutation handlers were moved to admin/users.php so account management
    // is centralized on the Create User page. This page only displays accounts now.
}

// Fetch all live sellers with their performance data for the current pay period
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.username,
        u.experienced_status,
        u.profile_image,
        COALESCE(SUM(a.solds_quantity), 0) as total_sales,
        COALESCE(SUM(a.hours_worked), 0) as total_hours,
        COUNT(DISTINCT a.attendance_date) as working_days
    FROM users u
    LEFT JOIN attendance a ON u.id = a.seller_id 
        AND a.status = 'approved'
        AND a.attendance_date BETWEEN :start_date AND :end_date
    WHERE u.role = 'live_seller' AND u.status = 'active'
    GROUP BY u.id, u.full_name, u.username, u.experienced_status, u.profile_image
    ORDER BY total_sales DESC, total_hours DESC
");
$stmt->execute([
    ':start_date' => $current_period['start_date'],
    ':end_date' => $current_period['end_date']
]);
$user_rankings = $stmt->fetchAll();

// Calculate hourly rates and total earned for each seller
foreach ($user_rankings as &$seller) {
    // Set hourly rate based on experience status
    $seller['hourly_rate'] = ($seller['experienced_status'] === 'tenured') ? 166 : 125;
    // Calculate total earned
    $seller['total_earned'] = $seller['total_hours'] * $seller['hourly_rate'];
}
unset($seller); // Break reference

// Calculate overall totals
$total_sales = array_sum(array_column($user_rankings, 'total_sales'));
$total_hours = array_sum(array_column($user_rankings, 'total_hours'));
$total_earned = array_sum(array_column($user_rankings, 'total_earned'));
$total_sellers = count($user_rankings);

$page_title = 'Admin Dashboard';
include 'layout/header.php';
?>



<div class="compact-admin-dashboard">
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="header-info">
                <h1>Live Host Performance Dashboard</h1>
                <p>Track and analyze approved live seller performance metrics</p>
            </div>
            <div class="header-stats">
                <div class="stat-badge">
                    <span class="stat-value"><?php echo $total_sellers; ?></span>
                    <span class="stat-label">Active Sellers</span>
                </div>
                <button class="manage-users-btn" onclick="location.href='users.php'">
                    <span class="btn-icon">👥</span>
                    Create User 
                </button>
            </div>
        </div>

            <?php
            // Fetch accounts and compute initial metrics based on shift-specific reset times
            // This makes the display match real-time visibility:
            // - 3-hour shift members: visible until 5 AM, then reset
            // - 4-hour shift members: visible until 6 AM, then reset
            $current_hour = (int)date('H');
            $today = date('Y-m-d');
            
            $stmt = $db->query("SELECT * FROM accounts ORDER BY name");
            $all_accounts = $stmt->fetchAll();
            foreach ($all_accounts as &$acct) {
                // members
                $s = $db->prepare("SELECT u.id, u.full_name, u.username FROM account_members am JOIN users u ON am.user_id = u.id WHERE am.account_id = ?");
                $s->execute([$acct['id']]);
                $acct['members'] = $s->fetchAll();

                // compute totals based on each member's shift-specific reset time
                $acct_sales = 0;
                $acct_hours = 0.0;
                $acct_contribs = [];
                
                foreach ($acct['members'] as $member) {
                    $member_id = $member['id'];
                    
                    // Get member's last attendance to determine their shift type
                    $lastStmt = $db->prepare("
                        SELECT ats.duration_hours 
                        FROM attendance a
                        LEFT JOIN attendance_time_slots ats ON a.time_slot = ats.id
                        WHERE a.seller_id = ? AND a.status != 'cancelled'
                        ORDER BY a.attendance_date DESC, a.created_at DESC
                        LIMIT 1
                    ");
                    $lastStmt->execute([$member_id]);
                    $lastAttendance = $lastStmt->fetch();
                    
                    // Determine this member's reset hour
                    $member_reset_hour = 5; // default to 3-hour shift reset
                    if ($lastAttendance) {
                        $duration = (int)$lastAttendance['duration_hours'];
                        if ($duration == 4) {
                            $member_reset_hour = 6;
                        } else {
                            $member_reset_hour = 5;
                        }
                    }
                    
                    // Calculate this member's "today" based on their reset hour
                    if ($current_hour < $member_reset_hour) {
                        $member_today = date('Y-m-d', strtotime('-1 day'));
                    } else {
                        $member_today = date('Y-m-d');
                    }
                    
                    // Get this member's attendance for their "today"
                    $memberStmt = $db->prepare("
                        SELECT SUM(solds_quantity) as sales, SUM(hours_worked) as hours 
                        FROM attendance 
                        WHERE seller_id = ? AND attendance_date = ? AND status = 'approved'
                    ");
                    $memberStmt->execute([$member_id, $member_today]);
                    $memberData = $memberStmt->fetch();
                    
                    if ($memberData && ($memberData['sales'] > 0 || $memberData['hours'] > 0)) {
                        $acct_sales += (int)$memberData['sales'];
                        $acct_hours += (float)$memberData['hours'];
                        $acct_contribs[] = $member['full_name'] . ' (@' . $member['username'] . ')';
                    }
                }
                
                $acct['_initial_sales'] = $acct_sales;
                $acct['_initial_hours'] = $acct_hours;
                $acct['_initial_contribs'] = $acct_contribs;
            }
            unset($acct);

            // Accounts read-only panel (dashboard)
            ?>
            <div class="accounts-panel dashboard-accounts">
                <div class="card large-card">
                    <div class="card-header">
                        <h3>Accounts Today</h3>
                        <div class="date-filter-container">
                            <label for="accounts-date-filter">Filter by Date:</label>
                            <input type="date" id="accounts-date-filter" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                            <button type="button" id="apply-date-filter" class="btn-filter">Apply</button>
                            <button type="button" id="reset-date-filter" class="btn-filter-reset">Today</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($all_accounts)): ?>
                            <p>No accounts configured yet. Create accounts in Create User page.</p>
                        <?php else: ?>
                            <div id="accounts-list">
                                <?php foreach ($all_accounts as $acct): ?>
                                    <div class="account-card" data-account-id="<?php echo $acct['id']; ?>">
                                        <div class="account-card-top">
                                                <div class="account-meta">
                                                    <div class="account-name"><?php echo htmlspecialchars($acct['name']); ?></div>
                                                    <div class="account-members">Members: <?php echo count($acct['members']); ?></div>

                                                        <div class="account-stats-vert">
                                                            <div class="stat">
                                                                <div class="stat-label">Total Sales</div>
                                                                <div class="stat-value sales" id="acct-<?php echo $acct['id']; ?>-sales"><?php echo (int)($acct['_initial_sales'] ?? 0); ?></div>
                                                            </div>
                                                            <div class="stat">
                                                                <div class="stat-label">Total Hours</div>
                                                                <div class="stat-value hours" id="acct-<?php echo $acct['id']; ?>-hours"><?php echo number_format((float)($acct['_initial_hours'] ?? 0),1); ?>h</div>
                                                            </div>
                                                        </div>
                                                </div>
                                            </div>
                                            <div class="contributors-list">
                                                <details class="contributors-details" id="acct-<?php echo $acct['id']; ?>-contributors">
                                                    <summary>Contributors (<?php echo !empty($acct['_initial_contribs']) ? count($acct['_initial_contribs']) : 0; ?>)</summary>
                                                    <?php if (!empty($acct['_initial_contribs'])): ?>
                                                        <ul class="contributors-ul">
                                                        <?php foreach ($acct['_initial_contribs'] as $c): ?>
                                                            <li><?php echo htmlspecialchars($c); ?></li>
                                                        <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <div class="no-contrib">No contributors today</div>
                                                    <?php endif; ?>
                                                </details>
                                            </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Performance Ranking Card -->
            <div class="ranking-card">  
            <div class="card-header">
                <div class="title-section">
                    <h2>🏆 Sales Rankings</h2>
                    <p>Sorted by highest total sales</p>
                </div>
                
                <!-- Pay Period Selector and Info -->
                <div class="header-period-info">
                    <div class="period-selector-container">
                        <div class="period-selector-wrapper">
                            <label class="period-selector-label">📊 PAY PERIOD:</label>
                            <div class="cascading-selectors">
                                <select id="year-selector" class="period-dropdown">
                                    <option value="">Current Period</option>
                                    <?php foreach ($hierarchical_periods as $year => $months): ?>
                                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <select id="month-selector" class="period-dropdown" style="display: none;">
                                    <option value="">Select Month</option>
                                </select>
                                
                                <select id="period-selector" class="period-dropdown" style="display: none;">
                                    <option value="">Select Period</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="period-badge">
                        <div class="period-icon-small">📅</div>
                        <div class="period-text">
                            <span class="period-label"><?php echo $selected_period ? 'Selected Period:' : 'Current Period:'; ?></span>
                            <span class="period-value"><?php echo $current_period['period_name']; ?></span>
                        </div>
                    </div>
                    
                    <div class="period-countdown-small">
                        <div class="countdown-number"><?php echo $days_until_reset; ?></div>
                        <div class="countdown-text">day<?php echo $days_until_reset != 1 ? 's' : ''; ?> left</div>
                    </div>
                </div>
            </div>

            <div class="performance-table">
                <!-- Table Headers -->
                <div class="table-headers">
                    <div class="header-col user-col">RANK & USER</div>
                    <div class="header-col exp-col">EXPERIENCE</div>
                    <div class="header-col sales-col">TOTAL SALES</div>
                    <div class="header-col hours-col">HOURS WORKED</div>
                    <div class="header-col rate-col">HOURLY RATE</div>
                    <div class="header-col earned-col">TOTAL SALARY</div>
                </div>  

                <!-- User Rows -->
                <div class="table-body">
                    <?php if (empty($user_rankings)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <p>No performance data available</p>
                            <small>User performance will appear here once they start working</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($user_rankings as $index => $user): ?>
                            <?php $rank = $index + 1; ?>
                            <div class="user-row <?php echo $rank <= 3 ? 'top-performer' : ''; ?>">
                                <!-- User Info -->
                                <div class="user-info-cell">
                                    <div class="rank-badge rank-<?php echo $rank; ?>">
                                        <?php if ($rank <= 3): ?>
                                            <?php echo $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : '🥉'); ?>
                                        <?php else: ?>
                                            #<?php echo $rank; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-profile">
                                        <div class="user-details">
                                            <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                            <div class="user-handle">@<?php echo htmlspecialchars($user['username']); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Experience Status -->
                                <div class="exp-cell">
                                    <span class="exp-badge <?php echo $user['experienced_status']; ?>">
                                        <?php echo ucfirst($user['experienced_status']); ?>
                                    </span>
                                </div>

                                <!-- Sales Info -->
                                <div class="sales-cell">
                                    <div class="primary-value"><?php echo number_format($user['total_sales']); ?></div>
                                    <div class="secondary-value">items sold</div>
                                </div>

                                <!-- Hours Info -->
                                <div class="hours-cell">
                                    <div class="primary-value"><?php echo number_format($user['total_hours'], 1); ?>h</div>
                                    <div class="secondary-value"><?php echo $user['working_days']; ?> days</div>
                                </div>

                                <!-- Rate Info -->
                                <div class="rate-cell">
                                    <div class="primary-value">₱<?php echo number_format($user['hourly_rate']); ?></div>
                                    <div class="secondary-value">per hour</div>
                                </div>

                                <!-- Earned Info -->
                                <div class="earned-cell highlight">
                                    <div class="primary-value">₱<?php echo number_format($user['total_earned'], 2); ?></div>
                                    <div class="secondary-value">total salary</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Overall Totals -->
                <div class="totals-section">
                    <div class="totals-left">
                        <div class="totals-title">Overall Totals</div>
                        <div class="totals-subtitle"><?php echo $total_sellers; ?> Active Sellers</div>
                    </div>
                    <div class="totals-spacer"></div>
                    <div class="totals-sales">
                        <div class="totals-value"><?php echo number_format($total_sales); ?></div>
                        <div class="totals-label">TOTAL SALES</div>
                    </div>
                    <div class="totals-spacer"></div>
                    <div class="totals-spacer"></div>
                    <div class="totals-earned">
                        <div class="totals-value">₱<?php echo number_format($total_earned, 2); ?></div>
                        <div class="totals-label">Overall SALARY</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Period Selector Styling */
.period-selector-container {
    display: flex;
    align-items: stretch;
    padding: 0.5rem 0.85rem;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
    border: 1px solid rgba(102, 126, 234, 0.4);
    border-radius: 10px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.period-selector-container:hover {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.25), rgba(118, 75, 162, 0.25));
    border-color: rgba(102, 126, 234, 0.5);
}

.period-selector-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
}

.period-selector-label {
    font-size: 0.65rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    white-space: nowrap;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
}

.cascading-selectors {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.period-dropdown {
    padding: 0.45rem 1.8rem 0.45rem 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 7px;
    color: white;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 110px;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
    background-size: 1em;
}

.period-dropdown:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.3);
}

.period-dropdown:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(102, 126, 234, 0.6);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
}

.period-dropdown option {
    background: #2c3e50;
    color: white;
    padding: 0.5rem;
}

.period-dropdown optgroup {
    background: #1a252f;
    color: rgba(255, 255, 255, 0.6);
    font-weight: 600;
    font-size: 0.85rem;
}

/* Adjust header period info to accommodate selector */
.header-period-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .header-period-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .period-selector-container {
        width: 100%;
    }
    
    .cascading-selectors {
        flex: 1;
        width: 100%;
    }
    
    .period-dropdown {
        flex: 1;
        min-width: 0;
    }
}

@media (max-width: 768px) {
    .period-selector-container {
        width: 100%;
    }
    
    .period-selector-wrapper {
        width: 100%;
    }
    
    .period-selector-label {
        text-align: left;
    }
    
    .cascading-selectors {
        flex-direction: column;
        width: 100%;
    }
    
    .period-dropdown {
        width: 100%;
    }
    
    .header-period-info {
        width: 100%;
    }
    
    .period-badge {
        width: 100%;
    }
}

/* Date Filter Container Styling */
.date-filter-container {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.date-filter-container label {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.date-filter-container input[type="date"] {
    padding: 0.65rem 1rem;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    color: white;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
    cursor: pointer;
    min-width: 165px;
}

.date-filter-container input[type="date"]:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.25);
}

.date-filter-container input[type="date"]:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(102, 126, 234, 0.5);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Calendar icon color */
.date-filter-container input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1) opacity(0.7);
    cursor: pointer;
    transition: opacity 0.3s ease;
}

.date-filter-container input[type="date"]::-webkit-calendar-picker-indicator:hover {
    filter: invert(1) opacity(1);
}

.btn-filter,
.btn-filter-reset {
    padding: 0.65rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    letter-spacing: 0.02em;
}

.btn-filter {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-filter:hover {
    background: linear-gradient(135deg, #5568d3, #6a3f8f);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
    transform: translateY(-2px);
}

.btn-filter:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.btn-filter-reset {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-filter-reset:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

.btn-filter-reset:active {
    transform: translateY(0);
}

/* Responsive date filter */
@media (max-width: 768px) {
    .date-filter-container {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
        padding-top: 0.5rem;
    }
    
    .date-filter-container label {
        text-align: center;
    }
    
    .date-filter-container input[type="date"],
    .btn-filter,
    .btn-filter-reset {
        width: 100%;
    }
}

/* Real-time update animations */
.accounts-panel {
    transition: opacity 0.3s ease;
}

.stat-value.value-updated {
    animation: pulseGreen 0.6s ease-out;
}

@keyframes pulseGreen {
    0% {
        transform: scale(1);
        color: inherit;
    }
    50% {
        transform: scale(1.15);
        color: #2ecc71;
        text-shadow: 0 0 10px rgba(46, 204, 113, 0.5);
    }
    100% {
        transform: scale(1);
        color: inherit;
    }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<?php include 'layout/footer.php'; ?>

<script>
// Hierarchical periods data from PHP
const periodsData = <?php echo json_encode($hierarchical_periods); ?>;

// Get selector elements
const yearSelector = document.getElementById('year-selector');
const monthSelector = document.getElementById('month-selector');
const periodSelector = document.getElementById('period-selector');

let selectedYear = null;
let selectedMonth = null;

// Year selector handler
yearSelector.addEventListener('change', function() {
    const year = this.value;
    
    if (!year) {
        // Reset to current period
        monthSelector.style.display = 'none';
        periodSelector.style.display = 'none';
        monthSelector.value = '';
        periodSelector.value = '';
        
        // Reload page without period parameter
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.delete('period');
        window.location.href = currentUrl.toString();
        return;
    }
    
    selectedYear = year;
    
    // Populate month selector
    monthSelector.innerHTML = '<option value="">Select Month</option>';
    const months = periodsData[year];
    
    for (const [monthNum, monthData] of Object.entries(months)) {
        const option = document.createElement('option');
        option.value = monthNum;
        option.textContent = monthData.name;
        monthSelector.appendChild(option);
    }
    
    // Show month selector, hide period selector
    monthSelector.style.display = 'block';
    periodSelector.style.display = 'none';
    periodSelector.value = '';
});

// Month selector handler
monthSelector.addEventListener('change', function() {
    const month = this.value;
    
    if (!month || !selectedYear) {
        periodSelector.style.display = 'none';
        periodSelector.value = '';
        return;
    }
    
    selectedMonth = month;
    
    // Populate period selector
    periodSelector.innerHTML = '<option value="">Select Period</option>';
    const periods = periodsData[selectedYear][month].periods;
    
    periods.forEach(period => {
        const option = document.createElement('option');
        option.value = period.value;
        option.textContent = period.label;
        periodSelector.appendChild(option);
    });
    
    // Show period selector
    periodSelector.style.display = 'block';
});

// Period selector handler (final selection)
periodSelector.addEventListener('change', function() {
    const selectedValue = this.value;
    
    if (!selectedValue) return;
    
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('period', selectedValue);
    window.location.href = currentUrl.toString();
});

// Initialize selectors if there's a selected period
<?php if ($selected_period): ?>
    // Parse the current period to set the dropdowns
    const currentPeriod = '<?php echo $selected_period['start_date'] . '|' . $selected_period['end_date']; ?>';
    const [startDate] = currentPeriod.split('|');
    const [year, month] = startDate.split('-');
    
    // Set year
    yearSelector.value = year;
    selectedYear = year;
    
    // Populate and set month
    const months = periodsData[year];
    monthSelector.innerHTML = '<option value="">Select Month</option>';
    for (const [monthNum, monthData] of Object.entries(months)) {
        const option = document.createElement('option');
        option.value = monthNum;
        option.textContent = monthData.name;
        if (monthNum == month) option.selected = true;
        monthSelector.appendChild(option);
    }
    monthSelector.style.display = 'block';
    selectedMonth = month;
    
    // Populate and set period
    const periods = periodsData[year][month].periods;
    periodSelector.innerHTML = '<option value="">Select Period</option>';
    periods.forEach(period => {
        const option = document.createElement('option');
        option.value = period.value;
        option.textContent = period.label;
        if (period.value === currentPeriod) option.selected = true;
        periodSelector.appendChild(option);
    });
    periodSelector.style.display = 'block';
<?php endif; ?>

// Current filter date
let currentFilterDate = null;
let pollingInterval = null;

// Poll the accounts metrics endpoint more frequently for real-time updates
function fetchAccountsMetrics(showVisualFeedback = false) {
    const url = currentFilterDate 
        ? 'accounts_metrics.php?date=' + encodeURIComponent(currentFilterDate)
        : 'accounts_metrics.php';
    
    console.log('[' + new Date().toLocaleTimeString() + '] Fetching accounts metrics...');
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!data || !data.accounts) return;
            
            console.log('[' + new Date().toLocaleTimeString() + '] Accounts data updated:', data.accounts.length, 'accounts');
            
            // Update header title based on filter
            const headerTitle = document.querySelector('.accounts-panel .card-header h3');
            if (headerTitle) {
                if (data.is_filtering) {
                    const formattedDate = new Date(data.date + 'T00:00:00').toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                    headerTitle.textContent = 'Accounts - ' + formattedDate;
                } else {
                    headerTitle.textContent = 'Accounts Today';
                }
            }
            
            // When filtering, hide accounts that are not in the response
            if (data.is_filtering) {
                const allAccountCards = document.querySelectorAll('.account-card');
                const returnedIds = data.accounts.map(a => a.id);
                
                allAccountCards.forEach(function(card) {
                    const accountId = parseInt(card.getAttribute('data-account-id'));
                    if (!returnedIds.includes(accountId)) {
                        card.style.display = 'none';
                    } else {
                        card.style.display = '';
                    }
                });
            } else {
                // When not filtering, show all accounts
                const allAccountCards = document.querySelectorAll('.account-card');
                allAccountCards.forEach(function(card) {
                    card.style.display = '';
                });
            }
            
            data.accounts.forEach(function(a) {
                var sid = 'acct-' + a.id + '-sales';
                var hid = 'acct-' + a.id + '-hours';
                var cid = 'acct-' + a.id + '-contributors';
                var elS = document.getElementById(sid);
                var elH = document.getElementById(hid);
                var elC = document.getElementById(cid);
                
                if (elS) {
                    var newSales = a.sales != null ? a.sales : 0;
                    var oldSales = parseInt(elS.textContent) || 0;
                    if (newSales !== oldSales) {
                        elS.textContent = newSales;
                        elS.classList.add('sales', 'value-updated');
                        setTimeout(() => elS.classList.remove('value-updated'), 1000);
                    }
                }
                if (elH) {
                    var newHours = (a.hours != null ? parseFloat(a.hours).toFixed(1) : '0.0') + 'h';
                    if (elH.textContent !== newHours) {
                        elH.textContent = newHours;
                        elH.classList.add('hours', 'value-updated');
                        setTimeout(() => elH.classList.remove('value-updated'), 1000);
                    }
                }
                if (elC) {
                    // populate contributors details content
                    if (a.contributors && a.contributors.length) {
                        var list = document.createElement('ul');
                        list.className = 'contributors-ul';
                        a.contributors.forEach(function(x){
                            var li = document.createElement('li'); li.textContent = x; list.appendChild(li);
                        });
                        // remove existing nodes inside details (except summary)
                        while (elC.lastChild && elC.lastChild.nodeName.toLowerCase() !== 'summary') {
                            elC.removeChild(elC.lastChild);
                        }
                        elC.appendChild(list);
                        // update summary count
                        var s = elC.querySelector('summary'); if (s) s.textContent = 'Contributors (' + a.contributors.length + ')';
                    } else {
                        while (elC.lastChild && elC.lastChild.nodeName.toLowerCase() !== 'summary') {
                            elC.removeChild(elC.lastChild);
                        }
                        var no = document.createElement('div'); no.className='no-contrib'; no.textContent = 'No contributors for this date';
                        elC.appendChild(no);
                        var s = elC.querySelector('summary'); if (s) s.textContent = 'Contributors (0)';
                    }
                }
            });
        }).catch(e => {
            console.error('accounts metrics error', e);
        });
}

// Handle date filter apply
document.getElementById('apply-date-filter').addEventListener('click', function() {
    const dateInput = document.getElementById('accounts-date-filter');
    currentFilterDate = dateInput.value;
    
    // Stop polling when filtering
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
    
    fetchAccountsMetrics();
});

// Handle date filter reset (back to today)
document.getElementById('reset-date-filter').addEventListener('click', function() {
    const dateInput = document.getElementById('accounts-date-filter');
    dateInput.value = new Date().toISOString().split('T')[0];
    currentFilterDate = null;
    
    // Restart polling when viewing today with more frequent updates
    if (pollingInterval) clearInterval(pollingInterval);
    pollingInterval = setInterval(fetchAccountsMetrics, 3000); // Update every 3 seconds
    
    fetchAccountsMetrics();
});

// initial fetch and periodic polling (every 3 seconds for near real-time updates)
fetchAccountsMetrics();
// Only enable polling if viewing current period (not historical)
<?php if (!$selected_period): ?>
pollingInterval = setInterval(fetchAccountsMetrics, 3000);
<?php endif; ?>

// Refresh Performance Rankings via AJAX
function refreshPerformanceRankings() {
    const currentUrl = new URL(window.location.href);
    const ajaxUrl = new URL('dashboard.php', window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')));
    ajaxUrl.searchParams.set('ajax_action', 'get_rankings');
    
    // Pass the period parameter if it exists
    if (currentUrl.searchParams.has('period')) {
        ajaxUrl.searchParams.set('period', currentUrl.searchParams.get('period'));
    }
    
    fetch(ajaxUrl.toString())
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.rankings) return;
            
            var tableBody = document.querySelector('.performance-table .table-body');
            if (!tableBody) return;
            
            // Clear current content
            tableBody.innerHTML = '';
            
            if (data.rankings.length === 0) {
                // Show empty state
                tableBody.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <p>No performance data available</p>
                        <small>User performance will appear here once they start working</small>
                    </div>
                `;
            } else {
                // Rebuild user rows
                data.rankings.forEach(function(user, index) {
                    var rank = index + 1;
                    var isTopPerformer = rank <= 3;
                    
                    var rankDisplay = '';
                    if (rank === 1) rankDisplay = '🥇';
                    else if (rank === 2) rankDisplay = '🥈';
                    else if (rank === 3) rankDisplay = '🥉';
                    else rankDisplay = '#' + rank;
                    
                    var userRow = document.createElement('div');
                    userRow.className = 'user-row' + (isTopPerformer ? ' top-performer' : '');
                    userRow.setAttribute('data-user-id', user.id);
                    
                    userRow.innerHTML = `
                        <div class="user-info-cell">
                            <div class="rank-badge rank-${rank}">
                                ${rankDisplay}
                            </div>
                            <div class="user-profile">
                                <div class="user-details">
                                    <div class="user-name">${escapeHtml(user.full_name)}</div>
                                    <div class="user-handle">@${escapeHtml(user.username)}</div>
                                </div>
                            </div>
                        </div>
                        <div class="exp-cell">
                            <span class="exp-badge ${user.experienced_status}">
                                ${user.experienced_status.charAt(0).toUpperCase() + user.experienced_status.slice(1)}
                            </span>
                        </div>
                        <div class="sales-cell">
                            <div class="primary-value">${formatNumber(user.total_sales)}</div>
                            <div class="secondary-value">items sold</div>
                        </div>
                        <div class="hours-cell">
                            <div class="primary-value">${formatNumber(user.total_hours, 1)}h</div>
                            <div class="secondary-value">${user.working_days} days</div>
                        </div>
                        <div class="rate-cell">
                            <div class="primary-value">₱${formatNumber(user.hourly_rate)}</div>
                            <div class="secondary-value">per hour</div>
                        </div>
                        <div class="earned-cell highlight">
                            <div class="primary-value">₱${formatNumber(user.total_earned, 2)}</div>
                            <div class="secondary-value">total salary</div>
                        </div>
                    `;
                    
                    tableBody.appendChild(userRow);
                });
            }
            
            // Update totals section
            if (data.totals) {
                var totalsSubtitle = document.querySelector('.totals-subtitle');
                if (totalsSubtitle) {
                    totalsSubtitle.textContent = data.totals.total_sellers + ' Active Sellers';
                }
                
                var totalsSalesValue = document.querySelector('.totals-sales .totals-value');
                if (totalsSalesValue) {
                    totalsSalesValue.textContent = formatNumber(data.totals.total_sales);
                }
                
                var totalsEarnedValue = document.querySelector('.totals-earned .totals-value');
                if (totalsEarnedValue) {
                    totalsEarnedValue.textContent = '₱' + formatNumber(data.totals.total_earned, 2);
                }
            }
        })
        .catch(e => console.error('Performance rankings error:', e));
}

// Helper functions for formatting
function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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

// Start periodic refresh for performance rankings (every 10 seconds)
refreshPerformanceRankings();
// Only enable auto-refresh if viewing current period (not historical)
<?php if (!$selected_period): ?>
setInterval(refreshPerformanceRankings, 10000);
<?php endif; ?>
</script>