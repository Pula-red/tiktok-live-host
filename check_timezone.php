<?php
require_once __DIR__ . '/includes/functions.php';
$db = getDB();

// Check PHP timezone
echo "PHP timezone: " . date_default_timezone_get() . "\n";
echo "PHP time: " . date('Y-m-d H:i:s') . "\n\n";

// Check MySQL timezone
$stmt = $db->query("SELECT @@global.time_zone as global_tz, @@session.time_zone as session_tz");
$tz = $stmt->fetch();
echo "MySQL global timezone: " . ($tz['global_tz'] ?: 'SYSTEM') . "\n";
echo "MySQL session timezone: " . ($tz['session_tz'] ?: 'SYSTEM') . "\n";
echo "MySQL current time: ";
$stmt = $db->query("SELECT NOW() as now");
echo $stmt->fetch()['now'] . "\n";