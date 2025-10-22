<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, role, full_name, status, password FROM users WHERE username = ? LIMIT 1");
    $stmt->execute(['admin']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "No admin user found (username 'admin').\n";
        exit(0);
    }

    // Print non-sensitive info; mask the password hash
    $maskedHash = substr($row['password'], 0, 10) . '...' . substr($row['password'], -10);
    echo "Admin user found:\n";
    echo "ID: " . $row['id'] . "\n";
    echo "Username: " . $row['username'] . "\n";
    echo "Email: " . $row['email'] . "\n";
    echo "Role: " . $row['role'] . "\n";
    echo "Full name: " . $row['full_name'] . "\n";
    echo "Status: " . $row['status'] . "\n";
    echo "Password hash (masked): " . $maskedHash . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
