<?php
$conn = mysqli_connect(
    'sql303.infinityfree.com',
    'if0_41972020',     // Your Infinity Free username
    'Ablorh2005',    // REPLACE THIS WITH YOUR PASSWORD FROM INFINITY FREE
    'if0_41972020_bhis'              // Your Infinity Free database name
);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Match fee schema (utf8mb4_unicode_ci) so literals and fee columns compare cleanly on MySQL 8.
$conn->set_charset('utf8mb4');
$conn->query("SET collation_connection = 'utf8mb4_unicode_ci'");

function get_active_year($conn) {
    $r = $conn->query("SELECT setting_value FROM school_settings WHERE setting_key='active_academic_year' LIMIT 1")->fetch_assoc();
    if ($r) return $r['setting_value'];
    $y = date('Y');
    return $y . '/' . ($y + 1);
}

// Get current active term based on today's date
function get_active_term($conn, $academic_year) {
    $ay  = mysqli_real_escape_string($conn, $academic_year);
    $today = date('Y-m-d');
    $r = $conn->query("SELECT * FROM term_settings WHERE academic_year='$ay' AND start_date <= '$today' AND end_date >= '$today' LIMIT 1");
    if ($r && $r->num_rows > 0) return $r->fetch_assoc();
    return null;
}

// Get term settings for a specific term
function get_term_settings($conn, $academic_year, $term) {
    $ay = mysqli_real_escape_string($conn, $academic_year);
    $t  = mysqli_real_escape_string($conn, $term);
    $r  = $conn->query("SELECT * FROM term_settings WHERE academic_year='$ay' AND term='$t' LIMIT 1");
    if ($r && $r->num_rows > 0) return $r->fetch_assoc();
    return null;
}

// Check if a date is a weekend
function is_weekend($date) {
    $day = date('N', strtotime($date));
    return $day >= 6;
}

// Check if a date is a Ghana public holiday
function is_ghana_holiday($conn, $date, $academic_year) {
    $ay  = mysqli_real_escape_string($conn, $academic_year);
    $d   = mysqli_real_escape_string($conn, $date);

    // Check custom holidays in database
    $r = $conn->query("SELECT id FROM school_holidays WHERE holiday_date='$d' AND academic_year='$ay' LIMIT 1");
    if ($r && $r->num_rows > 0) return true;

    // Ghana fixed public holidays (month-day)
    $month_day = date('m-d', strtotime($date));
    $fixed_holidays = [
        '01-01', // New Year's Day
        '03-06', // Independence Day
        '05-01', // Workers' Day
        '07-01', // Republic Day
        '08-04', // Founders Day
        '09-21', // Kwame Nkrumah Memorial Day
        '12-25', // Christmas Day
        '12-26', // Boxing Day
    ];
    return in_array($month_day, $fixed_holidays);
}

// Check if teacher can mark attendance for a given term today
function can_mark_attendance($conn, $academic_year, $term) {
    $today    = date('Y-m-d');
    $settings = get_term_settings($conn, $academic_year, $term);

    if (!$settings) return ['allowed' => false, 'reason' => 'Term dates have not been set by admin yet.'];

    // Check if term has concluded (end date passed)
    if ($today > $settings['end_date']) {
        return ['allowed' => false, 'reason' => "This term ended on " . date('F j, Y', strtotime($settings['end_date'])) . "."];
    }

    // Check if term has started
    if ($today < $settings['start_date']) {
        return ['allowed' => false, 'reason' => "This term starts on " . date('F j, Y', strtotime($settings['start_date'])) . "."];
    }

    // Check if previous term is concluded
    $terms_order = ['First Term' => 1, 'Second Term' => 2, 'Third Term' => 3];
    $term_num    = $terms_order[$term] ?? 1;

    if ($term_num > 1) {
        $prev_term = array_search($term_num - 1, $terms_order);
        $prev      = get_term_settings($conn, $academic_year, $prev_term);
        if ($prev && date('Y-m-d') <= $prev['end_date']) {
            return ['allowed' => false, 'reason' => "$prev_term has not concluded yet. It ends on " . date('F j, Y', strtotime($prev['end_date'])) . "."];
        }
    }

    // Check if today is a weekend
    if (is_weekend($today)) {
        return ['allowed' => false, 'reason' => "Today is a weekend. Attendance can only be marked on weekdays (Monday–Friday)."];
    }

    // Check if today is a public holiday
    if (is_ghana_holiday($conn, $today, $academic_year)) {
        return ['allowed' => false, 'reason' => "Today is a public holiday. No attendance marking on holidays."];
    }

    return ['allowed' => true, 'reason' => ''];
}

// Count valid school days in a term (excluding weekends and holidays)
function count_school_days($conn, $academic_year, $term, $class_name) {
    $ay    = mysqli_real_escape_string($conn, $academic_year);
    $t     = mysqli_real_escape_string($conn, $term);
    $class = mysqli_real_escape_string($conn, $class_name);
    $r     = $conn->query("SELECT COUNT(DISTINCT attendance_date) as days FROM attendance WHERE academic_year='$ay' AND term='$t' AND class_name='$class'");
    if ($r) return (int)$r->fetch_assoc()['days'];
    return 0;
}
?>