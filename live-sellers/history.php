<?php
require_once __DIR__ . '/../includes/functions.php';

// Require live seller role
require_role('live_seller');

// Get current user info
$current_user = get_logged_in_user();
$db = getDB();

// Generate years with pay periods
function generate_years_data() {
    $current_year = date('Y');
    $start_year = $current_year - 1;
    $years = [];
    
    for ($year = $current_year; $year >= $start_year; $year--) {
        $years[] = $year;
    }
    
    return $years;
}

$available_years = generate_years_data();

// Get months for a selected year
function get_months_for_year($year) {
    $current_year = date('Y');
    $current_month = date('n');
    $months = [];
    
    $start_month = ($year == $current_year) ? $current_month : 12;
    
    for ($month = $start_month; $month >= 1; $month--) {
        $months[] = [
            'number' => $month,
            'name' => date('F', strtotime("$year-$month-01"))
        ];
    }
    
    return $months;
}

// Get periods for a selected month
function get_periods_for_month($year, $month) {
    $periods = [];
    $current_date = time();
    $last_day = date('t', strtotime("$year-$month-01"));
    
    // First period: 1st to 15th
    $period_start_1 = "$year-$month-01";
    $period_end_1 = "$year-$month-15";
    
    if (strtotime($period_end_1) < $current_date) {
        $periods[] = [
            'label' => '1-15',
            'start' => $period_start_1,
            'end' => $period_end_1,
            'period' => 1
        ];
    }
    
    // Second period: 16th to end of month
    $period_start_2 = "$year-$month-16";
    $period_end_2 = "$year-$month-$last_day";
    
    if (strtotime($period_end_2) < $current_date) {
        $periods[] = [
            'label' => "16-$last_day",
            'start' => $period_start_2,
            'end' => $period_end_2,
            'period' => 2
        ];
    }
    
    return $periods;
}

// Get selected filters
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : null;
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : null;

// Get data based on selection
$selected_months = [];
$periods_data = [];

if ($selected_year) {
    $selected_months = get_months_for_year($selected_year);
}

// Function to get period data
function get_period_data($db, $user_id, $period) {
    // Fetch attendance data for the selected period
    $stmt = $db->prepare("
        SELECT 
            attendance_date,
            check_in_time as first_check_in,
            check_out_time as last_check_out,
            solds_quantity as daily_sales,
            hours_worked,
            1 as shifts_count
        FROM attendance
        WHERE seller_id = ?
            AND status = 'approved'
            AND attendance_date BETWEEN ? AND ?
        ORDER BY attendance_date ASC
    ");
    $stmt->execute([
        $user_id,
        $period['start'],
        $period['end']
    ]);
    $attendance_records = $stmt->fetchAll();
    
    // Get payment data for this period using pay_period_start and pay_period_end
    $payment_stmt = $db->prepare("
        SELECT SUM(amount) as total_salary
        FROM payment_receipts
        WHERE user_id = ?
            AND pay_period_start = ?
            AND pay_period_end = ?
            AND status = 'completed'
    ");
    $payment_stmt->execute([
        $user_id,
        $period['start'],
        $period['end']
    ]);
    $payment_result = $payment_stmt->fetch();
    $total_salary = floatval($payment_result['total_salary'] ?? 0);
    
    // Calculate totals
    $total_sales = 0;
    $total_hours = 0;
    $days_worked = count($attendance_records);
    
    foreach ($attendance_records as $record) {
        $total_sales += $record['daily_sales'];
        $total_hours += floatval($record['hours_worked'] ?? 0);
    }
    
    return [
        'total_sales' => $total_sales,
        'total_hours' => $total_hours,
        'days_worked' => $days_worked,
        'total_salary' => $total_salary,
        'attendance_records' => $attendance_records,
        'period_info' => $period
    ];
}

if ($selected_year && $selected_month) {
    $all_periods = get_periods_for_month($selected_year, $selected_month);
    
    // Get data for both periods
    foreach ($all_periods as $period) {
        $periods_data[] = get_period_data($db, $current_user['id'], $period);
    }
}

$page_title = 'Performance History';
include 'layout/header.php';
?>

<div class="history-container">
    <div class="page-header">
        <h1>📊 Performance History</h1>
        <p>View your past performance for each pay period</p>
    </div>

    <!-- Header Section -->
    <div class="content-header">
        <div class="header-title">
            <span class="title-icon">📋</span>
            <h2>Your Performance Records</h2>
        </div>
        
        <form method="GET" action="" id="filterForm" class="filter-controls">
            <!-- Year Selector -->
            <select name="year" id="yearSelect" class="filter-dropdown" onchange="document.getElementById('filterForm').submit()">
                <option value="">All Years</option>
                <?php foreach ($available_years as $year): ?>
                    <option value="<?php echo $year; ?>" <?php echo ($selected_year == $year) ? 'selected' : ''; ?>>
                        <?php echo $year; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Month Selector -->
            <select name="month" id="monthSelect" class="filter-dropdown" 
                    <?php echo !$selected_year ? 'disabled' : ''; ?>
                    onchange="document.getElementById('filterForm').submit()">
                <option value="">Select Month</option>
                <?php if ($selected_year):
                    foreach ($selected_months as $month): ?>
                        <option value="<?php echo $month['number']; ?>" <?php echo ($selected_month == $month['number']) ? 'selected' : ''; ?>>
                            <?php echo $month['name']; ?>
                        </option>
                    <?php endforeach;
                endif; ?>
            </select>
        </form>
    </div>

    <?php if ($selected_year && $selected_month && !empty($periods_data)): ?>
        <!-- Cards Grid -->
        <div class="payment-cards-container">
            <?php foreach ($periods_data as $period_data): ?>
                <?php 
                $period_info = $period_data['period_info'];
                $month_name = date('F', strtotime("$selected_year-$selected_month-01"));
                $period_label = "$month_name {$period_info['label']}, $selected_year";
                ?>
                
                <!-- Performance Summary Card -->
                <div class="payment-card">
                    <!-- Amount and Date Header -->
                    <div class="card-amount-section">
                        <div class="amount-display">₱<?php echo number_format($period_data['total_salary'], 2); ?></div>
                        <div class="date-badge">
                            📅 <?php echo $period_label; ?>
                        </div>
                    </div>

                    <!-- Card Details -->
                    <div class="card-details">
                        <div class="detail-row">
                            <span class="label-text">Sales (Quantity):</span>
                            <span class="value-text"><?php echo number_format($period_data['total_sales'], 0); ?> items</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="label-text">Hours Worked:</span>
                            <span class="value-text"><?php echo number_format($period_data['total_hours'], 1); ?>h</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="label-text">Days Worked:</span>
                            <span class="value-text"><?php echo $period_data['days_worked']; ?> days</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif (!$selected_year): ?>
        <div class="select-period-message">
            <div class="message-icon">👆</div>
            <h3>Select a Year to Start</h3>
            <p>Choose a year from the dropdown above to view available months</p>
        </div>
    <?php elseif (!$selected_month): ?>
        <div class="select-period-message">
            <div class="message-icon">📆</div>
            <h3>Select a Month</h3>
            <p>Choose a month to view your performance for both pay periods</p>
        </div>
    <?php endif; ?>
</div>

<style>
.history-container {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.page-header {
    text-align: center;
    margin-bottom: 3rem;
}

.page-header h1 {
    font-size: 2.5rem;
    color: white;
    margin-bottom: 0.5rem;
}

.page-header p {
    color: whitesmoke;
    font-size: 1.125rem;
}

/* Content Header - Matches Payment Records */
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2rem 2.5rem;
    background: white;
    border-radius: 12px 12px 0 0;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 0;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.title-icon {
    font-size: 1.75rem;
}

.header-title h2 {
    font-size: 1.75rem;
    color: #374151;
    font-weight: 700;
    margin: 0;
}

.filter-controls {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.filter-dropdown {
    padding: 0.625rem 2.5rem 0.625rem 1rem;
    font-size: 0.95rem;
    font-weight: 500;
    color: #374151;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    min-width: 150px;
}

.filter-dropdown:hover:not(:disabled) {
    border-color: #9ca3af;
    background-color: #f9fafb;
}

.filter-dropdown:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.filter-dropdown:disabled {
    background-color: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
    opacity: 0.6;
}

/* Payment Cards Container */
.payment-cards-container {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2rem;
    padding: 2rem 2.5rem;
    background: white;
    border-radius: 0 0 12px 12px;
}

/* Payment Card Style */
.payment-card {
    background: linear-gradient(135deg, #f7f9fc 0%, #eef2f7 100%);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.payment-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

/* Amount Section */
.card-amount-section {
    margin-bottom: 1.5rem;
}

.amount-display {
    font-size: 2.5rem;
    font-weight: 800;
    color: #10b981;
    margin-bottom: 0.75rem;
    line-height: 1;
}

.date-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    padding: 0.5rem 0.875rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
}

/* Card Details Section */
.card-details {
    background: white;
    border-radius: 8px;
    padding: 0;
    overflow: hidden;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e5e7eb;
}

.detail-row:last-child {
    border-bottom: none;
}

.label-text {
    font-size: 0.9375rem;
    color: #6b7280;
    font-weight: 500;
}

.value-text {
    font-size: 1rem;
    color: #111827;
    font-weight: 700;
}

.select-period-message,
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 0 0 12px 12px;
}

.message-icon,
.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.select-period-message h3 {
    font-size: 1.5rem;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.select-period-message p,
.empty-state p {
    font-size: 1.125rem;
    color: #718096;
}

.empty-state small {
    color: #9ca3af;
}

@media (max-width: 768px) {
    .history-container {
        padding: 1rem;
    }

    .content-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
        padding: 1.5rem;
    }
    
    .filter-controls {
        width: 100%;
        flex-direction: column;
    }
    
    .filter-dropdown {
        width: 100%;
    }

    .payment-cards-container {
        grid-template-columns: 1fr;
        gap: 1.5rem;
        padding: 1.5rem;
    }

    .payment-card {
        padding: 1.5rem;
    }

    .amount-display {
        font-size: 2rem;
    }
    
    .detail-row {
        padding: 0.875rem 1rem;
    }
    
    .label-text {
        font-size: 0.875rem;
    }
    
    .value-text {
        font-size: 0.9375rem;
    }
}
</style>

<?php include 'layout/footer.php'; ?>
