<?php
/**
 * Pay Period Data Migration Tool
 * 
 * This script helps update existing payment records with pay period information.
 * Run this AFTER executing the add_pay_period_to_receipts.sql migration.
 * 
 * USAGE:
 * 1. Access this file from your browser: http://your-domain/tools/migrate_pay_periods.php
 * 2. Review the list of payments without period data
 * 3. Use the form to manually assign periods to each payment
 */

require_once __DIR__ . '/../includes/functions.php';
require_role('admin'); // Only admins can run this

$db = getDB();
$current_user = get_logged_in_user();

$message = '';
$message_type = '';

// Handle period assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_period'])) {
    $payment_id = intval($_POST['payment_id']);
    $pay_period = trim($_POST['pay_period']);
    
    if (!empty($pay_period)) {
        list($start, $end) = explode('|', $pay_period);
        
        $stmt = $db->prepare("
            UPDATE payment_receipts 
            SET pay_period_start = ?, pay_period_end = ?
            WHERE id = ?
        ");
        
        if ($stmt->execute([$start, $end, $payment_id])) {
            $message = "Payment #{$payment_id} updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Error updating payment #{$payment_id}";
            $message_type = 'error';
        }
    }
}

// Get payments without period data
$stmt = $db->query("
    SELECT 
        pr.id,
        pr.amount,
        pr.payment_date,
        pr.reference_number,
        u.full_name,
        u.username
    FROM payment_receipts pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.pay_period_start IS NULL
        OR pr.pay_period_end IS NULL
    ORDER BY pr.payment_date DESC
");
$payments_without_period = $stmt->fetchAll();

// Generate pay periods for the form (last 12 months)
function generate_migration_periods() {
    $periods = [];
    
    for ($i = 0; $i < 12; $i++) {
        $date = new DateTime();
        $date->modify("-$i months");
        
        $year = $date->format('Y');
        $month = $date->format('m');
        $month_name = $date->format('F');
        
        // First period
        $start1 = "$year-$month-01";
        $end1 = "$year-$month-15";
        $periods[] = [
            'value' => "$start1|$end1",
            'label' => "$month_name 1-15, $year"
        ];
        
        // Second period
        $last_day = $date->format('t');
        $start2 = "$year-$month-16";
        $end2 = "$year-$month-$last_day";
        $periods[] = [
            'value' => "$start2|$end2",
            'label' => "$month_name 16-$last_day, $year"
        ];
    }
    
    return $periods;
}

$migration_periods = generate_migration_periods();

// Get total counts
$total_stmt = $db->query("SELECT COUNT(*) as total FROM payment_receipts");
$total_count = $total_stmt->fetch()['total'];
$missing_count = count($payments_without_period);
$complete_count = $total_count - $missing_count;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Period Migration Tool</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-card.success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }
        
        .stat-card.warning {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .message.error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .payment-row:hover {
            background: #f7fafc;
        }
        
        .amount {
            font-weight: bold;
            color: #48bb78;
        }
        
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }
        
        .empty-state h2 {
            color: #48bb78;
            margin-bottom: 10px;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Pay Period Migration Tool</h1>
        <p class="subtitle">Update existing payment records with pay period information</p>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_count; ?></div>
                <div class="stat-label">Total Payments</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number"><?php echo $complete_count; ?></div>
                <div class="stat-label">With Period Data</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $missing_count; ?></div>
                <div class="stat-label">Need Migration</div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($payments_without_period)): ?>
            <div class="empty-state">
                <h2>✅ All Done!</h2>
                <p>All payment records have been assigned to pay periods.</p>
                <p>No migration needed.</p>
                <a href="../admin/host-payments.php" class="back-link">← Back to Host Payments</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Live Seller</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Reference</th>
                        <th>Assign Pay Period</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments_without_period as $payment): ?>
                        <tr class="payment-row">
                            <td>#<?php echo $payment['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($payment['full_name']); ?></strong><br>
                                <small>@<?php echo htmlspecialchars($payment['username']); ?></small>
                            </td>
                            <td class="amount">₱<?php echo number_format($payment['amount'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($payment['reference_number'] ?: '-'); ?></td>
                            <td>
                                <form method="POST" style="display: flex; gap: 10px;">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <select name="pay_period" required>
                                        <option value="">-- Select Period --</option>
                                        <?php foreach ($migration_periods as $period): ?>
                                            <option value="<?php echo htmlspecialchars($period['value']); ?>">
                                                <?php echo htmlspecialchars($period['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                            </td>
                            <td>
                                    <button type="submit" name="update_period" class="btn btn-primary">
                                        Update
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <a href="../admin/host-payments.php" class="back-link">← Back to Host Payments</a>
        <?php endif; ?>
    </div>
</body>
</html>
