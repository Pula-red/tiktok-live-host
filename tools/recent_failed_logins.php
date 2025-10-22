<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, user_id, action, description, ip_address, created_at FROM activity_logs WHERE action = 'failed_login' ORDER BY created_at DESC LIMIT 25");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "No recent failed_login entries found.\n";
        exit(0);
    }

    foreach ($rows as $r) {
        echo "[{$r['created_at']}] ID: {$r['id']} | user_id: {$r['user_id']} | action: {$r['action']} | ip: {$r['ip_address']}\n";
        echo "  desc: {$r['description']}\n";
        echo str_repeat('-', 60) . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
