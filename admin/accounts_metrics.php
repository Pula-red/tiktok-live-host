<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');
$db = getDB();

// Get the requested date filter
$filter_date = $_GET['date'] ?? null;
$is_filtering = !empty($filter_date) && $filter_date !== date('Y-m-d');

// Calculate the current "display date" based on shift-based reset logic
// This makes accounts show live data that respects each member's reset time
$current_hour = (int)date('H');

// For live display, we need to determine what "today" means for each shift type
// 3-hour shifts: reset at 5 AM
// 4-hour shifts: reset at 6 AM

// We'll show data for members based on their current visibility window
$date = $filter_date ?: date('Y-m-d');

// fetch accounts and compute totals for the date
$stmt = $db->query("SELECT * FROM accounts ORDER BY name");
$accounts = $stmt->fetchAll();
$out = ['date' => $date, 'is_filtering' => $is_filtering, 'accounts' => []];
foreach ($accounts as $acct) {
    // get member ids
    $s = $db->prepare("SELECT user_id FROM account_members WHERE account_id = ?");
    $s->execute([$acct['id']]);
    $ids = $s->fetchAll(PDO::FETCH_COLUMN);

    $sales = 0;
    $hours = 0.0;
    $contributors = [];
    if (!empty($ids)) {
        // For each member, check their last attendance to determine their shift type
        // and calculate what "today" means for them based on current time
        foreach ($ids as $member_id) {
            $member_today = $date;
            
            // Only use shift-based logic if we're looking at "today" (not filtering by a past date)
            if (!$is_filtering) {
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
            }
            
            // Get this member's attendance for their "today"
            $memberStmt = $db->prepare("
                SELECT SUM(solds_quantity) as sales, SUM(hours_worked) as hours 
                FROM attendance 
                WHERE seller_id = ? AND attendance_date = ? AND status IN ('completed','checked_in')
            ");
            $memberStmt->execute([$member_id, $member_today]);
            $memberData = $memberStmt->fetch();
            
            if ($memberData && ($memberData['sales'] > 0 || $memberData['hours'] > 0)) {
                $sales += (int)$memberData['sales'];
                $hours += (float)$memberData['hours'];
                
                // Get member name
                $userStmt = $db->prepare("SELECT full_name, username FROM users WHERE id = ?");
                $userStmt->execute([$member_id]);
                $usr = $userStmt->fetch();
                if ($usr) {
                    $contributors[] = $usr['full_name'] . ' (@' . $usr['username'] . ')';
                } else {
                    $contributors[] = (int)$member_id;
                }
            }
        }
    }

    // Only include accounts that have data when filtering
    // When viewing "today", show all accounts even if they have no contributors yet
    if (!$is_filtering || !empty($contributors) || $sales > 0 || $hours > 0) {
        $out['accounts'][] = [
            'id' => $acct['id'],
            'name' => $acct['name'],
            'sales' => $sales,
            'hours' => $hours,
            'contributors' => $contributors,
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($out);
