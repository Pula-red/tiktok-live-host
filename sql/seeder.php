<?php
/**
 * Database Seeder for TikTok Live Host Agency
 * This file contains the default users with plain text passwords
 * Passwords will be hashed when stored in the database
<?php
/**
 * Database Seeder for TikTok Live Host Agency
 *
 * This script can seed attendance time slots, ensure an admin account exists,
 * and seed demo products/sales data. It is idempotent for the admin user.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';

// Default attendance time slots
// 3-hour shifts: 5am-8am, 8am-11am, 11am-2pm, 2pm-5pm, 5pm-8pm, 8pm-11pm, 11pm-2am, 2am-5am
// 4-hour shifts: 6am-10am, 10am-2pm, 2pm-6pm, 6pm-10pm, 10pm-2am, 2am-6am
$default_time_slots = [
    // 3-hour shifts
    ['name' => '5 AM - 8 AM', 'duration_hours' => 3.0, 'start_time' => '05:00:00', 'end_time' => '08:00:00'],
    ['name' => '8 AM - 11 AM', 'duration_hours' => 3.0, 'start_time' => '08:00:00', 'end_time' => '11:00:00'],
    ['name' => '11 AM - 2 PM', 'duration_hours' => 3.0, 'start_time' => '11:00:00', 'end_time' => '14:00:00'],
    ['name' => '2 PM - 5 PM', 'duration_hours' => 3.0, 'start_time' => '14:00:00', 'end_time' => '17:00:00'],
    ['name' => '5 PM - 8 PM', 'duration_hours' => 3.0, 'start_time' => '17:00:00', 'end_time' => '20:00:00'],
    ['name' => '8 PM - 11 PM', 'duration_hours' => 3.0, 'start_time' => '20:00:00', 'end_time' => '23:00:00'],
    ['name' => '11 PM - 2 AM', 'duration_hours' => 3.0, 'start_time' => '23:00:00', 'end_time' => '02:00:00'],
    ['name' => '2 AM - 5 AM', 'duration_hours' => 3.0, 'start_time' => '02:00:00', 'end_time' => '05:00:00'],
    
    // 4-hour shifts
    ['name' => '6 AM - 10 AM', 'duration_hours' => 4.0, 'start_time' => '06:00:00', 'end_time' => '10:00:00'],
    ['name' => '10 AM - 2 PM', 'duration_hours' => 4.0, 'start_time' => '10:00:00', 'end_time' => '14:00:00'],
    ['name' => '2 PM - 6 PM', 'duration_hours' => 4.0, 'start_time' => '14:00:00', 'end_time' => '18:00:00'],
    ['name' => '6 PM - 10 PM', 'duration_hours' => 4.0, 'start_time' => '18:00:00', 'end_time' => '22:00:00'],
    ['name' => '10 PM - 2 AM', 'duration_hours' => 4.0, 'start_time' => '22:00:00', 'end_time' => '02:00:00'],
    ['name' => '2 AM - 6 AM', 'duration_hours' => 4.0, 'start_time' => '02:00:00', 'end_time' => '06:00:00'],
];

function seedAdminUser() {
    $db = getDB();

    $adminUsername = 'admin';
    $adminEmail = 'admin@gmail.com';
    $adminPlain = 'admin123';
    $adminRole = 'admin';
    $adminFullName = 'System Administrator';
    $adminStatus = 'active';

    try {
        $check = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $check->execute([$adminUsername]);
        $existing = $check->fetch();

        $hashed_password = password_hash($adminPlain, PASSWORD_DEFAULT);

        if ($existing) {
            $update = $db->prepare("UPDATE users SET email = ?, password = ?, role = ?, full_name = ?, status = ? WHERE id = ?");
            $update->execute([$adminEmail, $hashed_password, $adminRole, $adminFullName, $adminStatus, $existing['id']]);
            echo "✓ Updated existing admin user (username: {$adminUsername})\n";
        } else {
            $insert = $db->prepare("INSERT INTO users (username, email, password, role, full_name, status) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->execute([$adminUsername, $adminEmail, $hashed_password, $adminRole, $adminFullName, $adminStatus]);
            echo "✓ Created admin user: {$adminUsername} (Password: {$adminPlain})\n";
        }

        echo "Admin user ensured. You can now login with username 'admin' and password 'admin123'.\n";
    } catch (PDOException $e) {
        echo "Error creating/updating admin user: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function seedTimeSlots() {
    global $default_time_slots;
    $db = getDB();

    try {
        echo "Seeding attendance time slots...\n";
        $db->exec("DELETE FROM attendance_time_slots");
        $db->exec("ALTER TABLE attendance_time_slots AUTO_INCREMENT = 1");

        $stmt = $db->prepare("INSERT INTO attendance_time_slots (name, duration_hours, start_time, end_time, is_active) VALUES (?, ?, ?, ?, 1)");
        foreach ($default_time_slots as $slot) {
            $stmt->execute([$slot['name'], $slot['duration_hours'], $slot['start_time'], $slot['end_time']]);
            echo "✓ Created time slot: {$slot['name']}\n";
        }
        echo "Time slots seeded successfully!\n";
    } catch (PDOException $e) {
        echo "Error seeding time slots: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function seedSalesData() {
    $db = getDB();
    try {
        echo "Seeding products and sales data...\n";
        $db->exec("DELETE FROM live_host_sales");
        $db->exec("DELETE FROM live_host_daily_summary");
        $db->exec("DELETE FROM products");

        $db->exec("ALTER TABLE live_host_sales AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE live_host_daily_summary AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE products AUTO_INCREMENT = 1");

        $products = [
            ['Wireless Bluetooth Headphones', 'High-quality wireless headphones with noise cancellation', 89.99, 'Electronics', 'WBH001', 50],
            ['Smartphone Case', 'Protective case for smartphones with multiple colors', 19.99, 'Accessories', 'SPC001', 100],
            ['LED Desk Lamp', 'Adjustable LED desk lamp with USB charging port', 45.99, 'Home & Office', 'LDL001', 30],
            ['Fitness Tracker', 'Smart fitness tracker with heart rate monitor', 129.99, 'Health & Fitness', 'FT001', 25],
            ['Portable Power Bank', '10000mAh portable charger with fast charging', 34.99, 'Electronics', 'PPB001', 75],
            ['Skincare Set', 'Complete skincare routine set for all skin types', 79.99, 'Beauty', 'SKS001', 40],
            ['Coffee Tumbler', 'Insulated travel coffee tumbler 16oz', 24.99, 'Kitchen', 'CT001', 60],
            ['Yoga Mat', 'Non-slip exercise yoga mat with carrying strap', 39.99, 'Health & Fitness', 'YM001', 35]
        ];

        $stmt = $db->prepare("INSERT INTO products (name, description, price, category, sku, stock_quantity, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        foreach ($products as $product) {
            $stmt->execute($product);
        }

        $sales = [
            [2, 1, 'Wireless Bluetooth Headphones', 2, 89.99, 179.98, 15.00, 26.99, '2025-10-01 14:30:00'],
            [2, 2, 'Smartphone Case', 5, 19.99, 99.95, 10.00, 9.99, '2025-10-01 15:15:00'],
            [2, 3, 'LED Desk Lamp', 1, 45.99, 45.99, 12.00, 5.52, '2025-10-01 16:45:00'],
            [3, 4, 'Fitness Tracker', 3, 129.99, 389.97, 20.00, 77.99, '2025-10-01 19:20:00'],
            [3, 5, 'Portable Power Bank', 4, 34.99, 139.96, 10.00, 13.99, '2025-10-01 20:10:00'],
            [2, 6, 'Skincare Set', 2, 79.99, 159.98, 15.00, 23.99, '2025-10-02 10:30:00'],
            [3, 7, 'Coffee Tumbler', 6, 24.99, 149.94, 8.00, 11.99, '2025-10-02 11:45:00'],
            [2, 8, 'Yoga Mat', 1, 39.99, 39.99, 10.00, 3.99, '2025-10-02 13:20:00']
        ];

        $stmt = $db->prepare("INSERT INTO live_host_sales (seller_id, product_id, product_name, quantity, unit_price, total_amount, commission_rate, commission_amount, sale_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')");
        foreach ($sales as $sale) {
            $stmt->execute($sale);
        }

        $summaries = [
            [2, '2025-10-01', 325.92, 8, 40.50, 6.0, 2],
            [3, '2025-10-01', 529.93, 7, 91.98, 4.0, 1],
            [2, '2025-10-02', 199.97, 3, 27.98, 3.0, 1],
            [3, '2025-10-02', 149.94, 6, 11.99, 2.0, 1]
        ];

        $stmt = $db->prepare("INSERT INTO live_host_daily_summary (seller_id, summary_date, total_sales, total_items_sold, total_commission, hours_worked, streams_count) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($summaries as $summary) {
            $stmt->execute($summary);
        }

        echo "✓ Created " . count($products) . " products\n";
        echo "✓ Created " . count($sales) . " sales records\n";
        echo "✓ Created " . count($summaries) . " daily summaries\n";

        echo "Sales data seeded successfully!\n";

    } catch (Exception $e) {
        echo "Error seeding sales data: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function showCredentials() {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "DEFAULT ADMIN CREDENTIALS:\n";
    echo str_repeat("=", 60) . "\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'show';

    switch ($action) {
        case 'seed':
            seedTimeSlots();
            seedAdminUser();
            seedSalesData();
            showCredentials();
            break;

        case 'users':
            echo "Seeding users (admin only)...\n";
            seedAdminUser();
            break;

        case 'slots':
            echo "Seeding time slots only...\n";
            seedTimeSlots();
            break;

        case 'sales':
            echo "Seeding products and sales data only...\n";
            seedSalesData();
            break;

        case 'show':
        default:
            showCredentials();
            break;

        case 'help':
            echo "Usage: php seeder.php [action]\n";
            echo "Actions:\n";
            echo "  show   - Display credentials only (default)\n";
            echo "  seed   - Seed database with time slots, admin user, and sales data\n";
            echo "  users  - Create/update admin user only\n";
            echo "  slots  - Seed attendance time slots only\n";
            echo "  sales  - Seed products and sales data only\n";
            echo "  help   - Show this help message\n";
            break;
    }
} else {
    echo "<h1>Seeder</h1><p>Run this from the command line: php seeder.php seed</p>";
}

?>