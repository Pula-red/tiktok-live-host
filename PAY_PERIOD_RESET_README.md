# Pay Period Reset Feature Documentation

## Overview
Both the **Admin Dashboard** and **Live Sellers Dashboard** now automatically reset and filter data based on 15-day pay periods:
- **Period 1:** Day 1-15 of each month
- **Period 2:** Day 16 to end of month (28, 29, 30, or 31)

## Features Implemented

### 1. Automatic Pay Period Detection
The system automatically detects which pay period we're currently in based on today's date.

### 2. Data Filtering
Both dashboards now show **only** the performance data from the current pay period:
- Sales data
- Hours worked
- Working days
- Total earnings
- Rankings based on current period performance

### 3. Visual Pay Period Banner
A new banner at the top of both dashboards displays:
- **Current Pay Period**: Shows the date range (e.g., "October 1 - 15, 2025")
- **Countdown Timer**: Shows days remaining until the period resets
- **Information Note**: Explains the automatic 15-day reset cycle

### 4. Automatic Reset
- Data automatically filters to the new period when a new pay period begins
- No manual intervention required
- No data is deleted - historical data is preserved

## Technical Implementation

### New Functions (in `includes/functions.php`)

#### `get_current_pay_period()`
Returns the current pay period with:
- `start_date`: Beginning of current period (YYYY-MM-DD)
- `end_date`: End of current period (YYYY-MM-DD)
- `period_name`: Formatted display name

```php
$period = get_current_pay_period();
// Returns:
// [
//     'start_date' => '2025-10-01',
//     'end_date' => '2025-10-15',
//     'period_name' => 'October 1 - 15, 2025'
// ]
```

#### `get_pay_period_by_date($date)`
Gets the pay period for any specific date.

```php
$period = get_pay_period_by_date('2025-10-20');
// Returns period 16-31 for October
```

#### `get_days_until_reset()`
Returns the number of days remaining in the current pay period.

```php
$days = get_days_until_reset();
// Returns: 5 (if today is Oct 10)
```

### Database Query Changes

The dashboard query now includes date filtering:

```php
$stmt = $db->prepare("
    SELECT ...
    FROM users u
    LEFT JOIN attendance a ON u.id = a.seller_id 
        AND a.status IN ('completed', 'checked_in')
        AND a.attendance_date BETWEEN :start_date AND :end_date
    WHERE u.role = 'live_seller' AND u.status = 'active'
    ...
");
$stmt->execute([
    ':start_date' => $current_period['start_date'],
    ':end_date' => $current_period['end_date']
]);
```

## UI Components

### Pay Period Banner Structure
```html
<div class="pay-period-banner">
    <!-- Period Info Section -->
    <div class="period-info">
        <div class="period-icon">📅</div>
        <div class="period-details">
            <div class="period-title">Current Pay Period</div>
            <div class="period-dates">October 1 - 15, 2025</div>
        </div>
    </div>
    
    <!-- Countdown Section -->
    <div class="period-countdown">
        <div class="countdown-value">5</div>
        <div class="countdown-label">days until reset</div>
    </div>
    
    <!-- Information Section -->
    <div class="period-note">
        <span class="info-icon">ℹ️</span>
        <span>Data automatically resets every 15 days (1-15 & 16-end of month)</span>
    </div>
</div>
```

## Styling

### Banner Styling (in `assets/css/admin.css`)
- Gradient background with purple/blue theme
- Responsive design for mobile devices
- Glassmorphism effect with backdrop blur
- Animated countdown display

## Example Usage Timeline

### October 1-15, 2025
- Dashboard shows data from Oct 1-15 only
- Countdown shows "15 days until reset" on Oct 1
- Countdown shows "1 day until reset" on Oct 14

### October 16, 2025 (Reset Day)
- Dashboard automatically switches to show Oct 16-31 data
- All counters reset to 0
- Countdown shows "15 days until reset" (or fewer if month is shorter)

### November 1, 2025 (Next Period)
- Dashboard switches to Nov 1-15 data
- Pattern continues

## Benefits

1. **Accurate Pay Period Tracking**: Matches real payroll cycles
2. **No Manual Intervention**: Automatic reset eliminates human error
3. **Historical Data Preserved**: Past period data remains in database
4. **Clear Visibility**: Users always know current period and when reset occurs
5. **Flexible Reporting**: Can easily add features to view past periods

## Future Enhancements (Optional)

Consider adding:
1. **Period Selector**: Dropdown to view previous pay periods
2. **Period Comparison**: Compare current vs previous period performance
3. **Export by Period**: Generate reports for specific periods
4. **Period Notifications**: Alert admins when period is about to reset
5. **Custom Period Configuration**: Allow admin to change cutoff dates

## Testing Checklist

- [x] Dashboard shows correct date range in banner
- [x] Countdown timer displays accurately
- [x] Data filters correctly for current period
- [x] Banner is responsive on mobile devices
- [x] Styling matches overall theme
- [ ] Test on Oct 15 (last day of period)
- [ ] Test on Oct 16 (first day of new period)
- [ ] Test on months with 28, 29, 30, 31 days

## Files Modified

1. **includes/functions.php**
   - Added `get_current_pay_period()`
   - Added `get_pay_period_by_date()`
   - Added `get_days_until_reset()`

2. **admin/dashboard.php**
   - Updated SQL query with date filtering
   - Added pay period variables
   - Added pay period banner HTML

3. **live-sellers/dashboard.php**
   - Updated SQL queries with date filtering for all statistics
   - Added pay period variables
   - Added pay period banner HTML
   - Rankings now based on current period performance

4. **assets/css/admin.css**
   - Added `.pay-period-banner` styles
   - Added `.period-*` component styles
   - Added `.countdown-*` component styles
   - Added `.manage-users-btn` styles
   - Added responsive breakpoints

5. **assets/css/live-seller.css**
   - Added `.pay-period-banner` styles
   - Added `.period-*` component styles
   - Added `.countdown-*` component styles
   - Added responsive breakpoints

## Maintenance Notes

- No maintenance required for automatic reset
- Database query performance is optimized with date indexes
- Functions are reusable for other dashboards (live sellers, reports, etc.)
- All styling follows existing design system

## Support

For issues or questions:
1. Check that dates are being calculated correctly for edge cases (month end)
2. Verify database has `attendance_date` indexed for performance
3. Test across different timezones if applicable
4. Ensure PHP timezone is set correctly in server configuration
