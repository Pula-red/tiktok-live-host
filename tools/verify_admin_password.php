<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT password FROM users WHERE username = ? LIMIT 1");
    $stmt->execute(['admin']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "No admin user found.\n";
        exit(1);
    }

    $hash = $row['password'];
    $plain = 'admin123';

    if (password_verify($plain, $hash)) {
        echo "Password verification SUCCESS: 'admin123' matches stored hash.\n";
    } else {
        echo "Password verification FAILED: 'admin123' does NOT match stored hash.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
