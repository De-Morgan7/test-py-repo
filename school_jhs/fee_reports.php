<?php
session_start();
include 'db.php';
require_once 'fee_helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

$fees_ok = fees_tables_exist($conn);
$payments_ok = fee_payments_table_exists($conn);
$feeding_log_ok = feeding_fee_entries_table_exists($conn);

$cy = (int) date('Y');
$year_options = [];
for ($i = -2; $i <= 10; $i++) {
    $y = $cy + $i;
    $year_options[] = $y . '/' . ($y + 1);
}

$terms_list = ['First Term', 'Second Term', 'Third Term'];
$selected_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : fee_default_academic_year();
if (!in_array($selected_year, $year_options, true)) {
    $selected_year = fee_default_academic_year();
}
$term_filter = isset($_GET['term']) ? trim($_GET['term']) : '';
if ($term_filter !== '' && !in_array($term_filter, $terms_list, true)) {
    $term_filter = '';
}

$ay_esc = mysqli_real_escape_string($conn, $selected_year);

$settings = $fees_ok ? fee_settings_row($conn, $selected_year) : [
    'school_fee_expected' => 500,
    'feeding_fee_expected' => 0,
    'exam_fee_expected' => 0,
];

$student_count = 0;
$class_counts = [];
if ($fees_ok) {
$ay_r = mysqli_real_escape_string($conn, $selected_year);
$rc = $conn->query("SELECT class_name, COUNT(*) AS c FROM students 
    WHERE (status='Active' OR (status='Graduated' AND graduation_year >= '$ay_r'))
    GROUP BY class_name ORDER BY class_name");
while ($rc && $row = $rc->fetch_assoc()) {
    $class_counts[$row['class_name']] = (int) $row['c'];
    $student_count += (int) $row['c'];
}
}

$school_exp = (float) $settings['school_fee_expected'];
$exam_exp = (float) $settings['exam_fee_expected'];
// Total expected = all students × school rate + all students × exam rate (same as (school+exam)×N, clearer split).
$exp_school_all = $student_count * $school_exp;
$exp_exam_all = $student_count * $exam_exp;
$expected_full_year = $exp_school_all + $exp_exam_all;

$total_school_collected = 0;
$total_exam_collected = 0;
$total_feeding_collected = 0;
$class_totals = [];
$report_error = '';
$has_exam_col = student_fees_has_exam_column($conn);

if ($fees_ok) {
    $exam_sum_sql = $has_exam_col
        ? 'COALESCE(SUM(sf.exam_fee_paid), 0) AS exam_sum'
        : '0 AS exam_sum';
    $q = "
        SELECT s.class_name,
               COALESCE(SUM(sf.school_fee_paid), 0) AS school_sum,
               $exam_sum_sql,
               COALESCE(SUM(sf.feeding_fee_paid), 0) AS feeding_sum
        FROM students s
        LEFT JOIN student_fees sf ON " . fee_sql_student_id_eq('sf', 's') . " AND sf.academic_year='$ay_esc'
        GROUP BY s.class_name
        ORDER BY s.class_name
    ";
    $gr = $conn->query($q);
    if (!$gr) {
        $report_error = 'Could not load class totals: ' . htmlspecialchars($conn->error);
    }
    while ($gr && $row = $gr->fetch_assoc()) {
        $cn = $row['class_name'];
        $class_totals[$cn] = [
            'school' => (float) $row['school_sum'],
            'exam' => (float) $row['exam_sum'],
            'feeding' => (float) $row['feeding_sum'],
            'students' => isset($class_counts[$cn]) ? $class_counts[$cn] : 0,
        ];
        $total_school_collected += (float) $row['school_sum'];
        $total_exam_collected += (float) $row['exam_sum'];
        $total_feeding_collected += (float) $row['feeding_sum'];
    }
}

$total_collected_school_exam = $total_school_collected + $total_exam_collected;
$total_collected_year = $total_school_collected + $total_exam_collected + $total_feeding_collected;
$shortfall = max(0, $expected_full_year - $total_collected_school_exam);

$rem_school = max(0, $exp_school_all - $total_school_collected);
$rem_exam = max(0, $exp_exam_all - $total_exam_collected);

$year_log = ['school' => 0, 'exam' => 0, 'feeding' => 0];
if ($payments_ok) {
    $exclude_fp_feeding = $feeding_log_ok ? " AND fee_type <> 'feeding' " : '';
    $yrq = $conn->query("SELECT fee_type, SUM(amount) AS t FROM fee_payments WHERE academic_year='$ay_esc' $exclude_fp_feeding GROUP BY fee_type");
    if ($yrq) {
        while ($rw = $yrq->fetch_assoc()) {
            $year_log[$rw['fee_type']] = (float) $rw['t'];
        }
    }
}
if ($feeding_log_ok) {
    $yfe = $conn->query("
        SELECT COALESCE(SUM(e.amount), 0) AS t
        FROM feeding_fee_entries e
        INNER JOIN (
            SELECT student_id, payment_date, MAX(id) AS mid
            FROM feeding_fee_entries
            WHERE academic_year='$ay_esc'
            GROUP BY student_id, payment_date
        ) dedup ON dedup.mid = e.id
    ");
    if ($yfe && ($rfe = $yfe->fetch_assoc())) {
        $year_log['feeding'] = (float) $rfe['t'];
    }
}

$detail_date = isset($_GET['detail_date']) ? trim($_GET['detail_date']) : '';
if ($detail_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $detail_date)) {
    $detail_date = '';
}
$detail_lines = [];
if ($detail_date !== '') {
    $dd = mysqli_real_escape_string($conn, $detail_date);
    if ($payments_ok) {
        $term_detail_mix = '';
        if ($term_filter !== '') {
            $tf = mysqli_real_escape_string($conn, $term_filter);
            if ($feeding_log_ok) {
                $term_detail_mix = " AND (fp.fee_type IN ('school','exam') AND fp.term='$tf') ";
            } else {
                $term_detail_mix = " AND ((fp.fee_type IN ('school','exam') AND fp.term='$tf') OR fp.fee_type='feeding') ";
            }
        }
        $hide_fp_feeding = $feeding_log_ok ? " AND fp.fee_type <> 'feeding' " : '';
        $dq = "
            SELECT fp.id, fp.student_id, fp.fee_type, fp.amount, fp.payment_date, fp.term,
                   s.full_name, s.class_name
            FROM fee_payments fp
            INNER JOIN students s ON " . fee_sql_student_id_eq('s', 'fp') . "
            WHERE fp.academic_year='$ay_esc' AND fp.payment_date='$dd' $term_detail_mix $hide_fp_feeding
            ORDER BY fp.fee_type, s.full_name, fp.id
        ";
        $dr2 = $conn->query($dq);
        if (!$dr2) {
            $report_error = ($report_error ? $report_error . ' ' : '') . htmlspecialchars($conn->error);
        }
        while ($dr2 && $row = $dr2->fetch_assoc()) {
            $row['receipt_is_pp'] = true;
            $detail_lines[] = $row;
        }
    }
    if ($feeding_log_ok) {
        $fq = $conn->query("
            SELECT e.id, e.student_id, e.amount, e.payment_date, s.full_name, s.class_name
            FROM feeding_fee_entries e
            INNER JOIN students s ON " . fee_sql_student_id_eq('s', 'e') . "
            WHERE e.academic_year='$ay_esc' AND e.payment_date='$dd'
            ORDER BY s.class_name, s.full_name, e.id
        ");
        if (!$fq) {
            $report_error = ($report_error ? $report_error . ' ' : '') . htmlspecialchars($conn->error);
        }
        while ($fq && $fr = $fq->fetch_assoc()) {
            $detail_lines[] = [
                'id' => (int) $fr['id'],
                'student_id' => $fr['student_id'],
                'fee_type' => 'feeding',
                'amount' => $fr['amount'],
                'payment_date' => $fr['payment_date'],
                'term' => 'Daily register',
                'full_name' => $fr['full_name'],
                'class_name' => $fr['class_name'],
                'receipt_is_pp' => false,
            ];
        }
    }
    $type_order = ['school' => 0, 'exam' => 1, 'feeding' => 2];
    usort($detail_lines, function ($a, $b) use ($type_order) {
        $ta = $type_order[$a['fee_type']] ?? 9;
        $tb = $type_order[$b['fee_type']] ?? 9;
        if ($ta !== $tb) {
            return $ta <=> $tb;
        }
        $na = $a['full_name'] ?? '';
        $nb = $b['full_name'] ?? '';
        return strcmp($na, $nb);
    });
}

$log_sum_year = ($year_log['school'] ?? 0) + ($year_log['exam'] ?? 0) + ($year_log['feeding'] ?? 0);
$balances_but_no_log = $payments_ok && $total_collected_year >= 0.01 && abs($log_sum_year) < 0.01;

$url_params = ['academic_year' => $selected_year];
if ($term_filter !== '') {
    $url_params['term'] = $term_filter;
}

$term_school = 0;
$term_exam = 0;
$term_feeding = 0;
$daily_rows = [];

if ($payments_ok) {
    $term_sql_se = '';
    if ($term_filter !== '') {
        $tf = mysqli_real_escape_string($conn, $term_filter);
        $term_sql_se = " AND fp.term='$tf' ";
    }

    $sr = $conn->query("SELECT SUM(amount) AS total FROM fee_payments fp WHERE fp.academic_year='$ay_esc' AND fp.fee_type='school' $term_sql_se");
    if ($sr && ($rw = $sr->fetch_assoc())) {
        $term_school = (float) $rw['total'];
    }
    $sr2 = $conn->query("SELECT SUM(amount) AS total FROM fee_payments fp WHERE fp.academic_year='$ay_esc' AND fp.fee_type='exam' $term_sql_se");
    if ($sr2 && ($rw = $sr2->fetch_assoc())) {
        $term_exam = (float) $rw['total'];
    }
    if (!$feeding_log_ok) {
        $sr3 = $conn->query("SELECT SUM(amount) AS total FROM fee_payments fp WHERE fp.academic_year='$ay_esc' AND fp.fee_type='feeding'");
        if ($sr3 && ($rw = $sr3->fetch_assoc())) {
            $term_feeding = (float) $rw['total'];
        }
    }

    $daily_se_q = "
        SELECT fp.payment_date, fp.fee_type, SUM(fp.amount) AS day_total
        FROM fee_payments fp
        WHERE fp.academic_year='$ay_esc' AND fp.fee_type IN ('school','exam') $term_sql_se
        GROUP BY fp.payment_date, fp.fee_type
        ORDER BY fp.payment_date DESC
    ";
    $dr = $conn->query($daily_se_q);
    while ($dr && $row = $dr->fetch_assoc()) {
        $d = $row['payment_date'];
        if (!isset($daily_rows[$d])) {
            $daily_rows[$d] = ['school' => 0, 'feeding' => 0, 'exam' => 0];
        }
        $daily_rows[$d][$row['fee_type']] = (float) $row['day_total'];
    }

    if (!$feeding_log_ok) {
        $drf = $conn->query("SELECT payment_date, SUM(amount) AS day_total FROM fee_payments WHERE academic_year='$ay_esc' AND fee_type='feeding' GROUP BY payment_date");
        while ($drf && $row = $drf->fetch_assoc()) {
            $d = $row['payment_date'];
            if (!isset($daily_rows[$d])) {
                $daily_rows[$d] = ['school' => 0, 'feeding' => 0, 'exam' => 0];
            }
            $daily_rows[$d]['feeding'] = ($daily_rows[$d]['feeding'] ?? 0) + (float) $row['day_total'];
        }
    }
}

if ($feeding_log_ok) {
    $tfe = $conn->query("
        SELECT COALESCE(SUM(e.amount), 0) AS t
        FROM feeding_fee_entries e
        INNER JOIN (
            SELECT student_id, payment_date, MAX(id) AS mid
            FROM feeding_fee_entries
            WHERE academic_year='$ay_esc'
            GROUP BY student_id, payment_date
        ) dedup ON dedup.mid = e.id
    ");
    if ($tfe && ($rfe = $tfe->fetch_assoc())) {
        $term_feeding = (float) $rfe['t'];
    }

    $dfe = $conn->query("
        SELECT e.payment_date, SUM(e.amount) AS day_total
        FROM feeding_fee_entries e
        INNER JOIN (
            SELECT student_id, payment_date, MAX(id) AS mid
            FROM feeding_fee_entries
            WHERE academic_year='$ay_esc'
            GROUP BY student_id, payment_date
        ) dedup ON dedup.mid = e.id
        GROUP BY e.payment_date
    ");
    while ($dfe && $row = $dfe->fetch_assoc()) {
        $d = $row['payment_date'];
        if (!isset($daily_rows[$d])) {
            $daily_rows[$d] = ['school' => 0, 'feeding' => 0, 'exam' => 0];
        }
        $daily_rows[$d]['feeding'] = (float) $row['day_total'];
    }
}

ksort($daily_rows);
$daily_rows = array_reverse($daily_rows, true);

$expected_classes = [];
foreach ($class_counts as $cn => $cnt) {
    $expected_classes[$cn] = $cnt * ($school_exp + $exam_exp);
}

$term_label = $term_filter !== '' ? $term_filter : 'All terms';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JHS Portal — Fee reports</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }
        body.nav-open { overflow: hidden; }

        .sidebar-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 1001;
            opacity: 0; pointer-events: none; transition: opacity 0.25s ease;
        }
        html.compact .sidebar-overlay { display: block; }
        body.nav-open .sidebar-overlay { opacity: 1; pointer-events: auto; }

        .sidebar {
            width: 250px; max-width: min(280px, 86vw);
            background: linear-gradient(180deg, #1a73e8, #0d47a1); color: white;
            padding: 20px 0; position: fixed; left: 0; top: 0; height: 100vh; height: 100dvh;
            overflow-y: auto; z-index: 1002; transition: transform 0.25s ease;
            box-shadow: 4px 0 24px rgba(0,0,0,0.12);
        }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 20px; }
        .sidebar-logo h2 { font-size: 20px; }
        .sidebar-logo p { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        .nav-section { padding: 0 15px; margin-bottom: 10px; }
        .nav-section-title { font-size: 11px; text-transform: uppercase; opacity: 0.6; margin-bottom: 8px; padding-left: 10px; }
        .nav-link {
            display: flex; align-items: center; min-height: 44px; padding: 10px 15px;
            color: white; text-decoration: none; border-radius: 8px; margin-bottom: 4px;
            font-size: 14px;
        }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        .logout-btn {
            display: block; margin: 20px 15px 0; padding: 12px 15px;
            background: rgba(255,255,255,0.1); color: white; text-decoration: none;
            border-radius: 8px; text-align: center; font-size: 14px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .logout-btn:hover { background: #e53935; }

        .main { margin-left: 250px; padding: 30px; width: calc(100% - 250px); min-width: 0; }

        @media screen and (max-width: 1024px) {
            html.compact .main { margin-left: 0 !important; width: 100% !important; padding: 14px !important; }
            html.compact .sidebar { transform: translate3d(-100%, 0, 0) !important; box-shadow: none; }
            html.compact body.nav-open .sidebar { transform: translate3d(0, 0, 0) !important; box-shadow: 4px 0 24px rgba(0,0,0,0.12); }
        }

        .mobile-header {
            display: none; background: #1a73e8; color: white; padding: 12px 14px;
            justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000;
        }
        html.compact .mobile-header { display: flex; }
        .menu-toggle {
            background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.6);
            color: white; padding: 10px 14px; border-radius: 8px; cursor: pointer; font-weight: 600;
        }

        .top-bar {
            background: white; padding: 15px 25px; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
        }
        .top-bar h1 { font-size: 20px; color: #333; }

        .card {
            background: white; padding: 24px; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 22px;
        }
        .card h2 { font-size: 17px; color: #333; margin-bottom: 12px; }
        .hint { font-size: 13px; color: #666; margin-bottom: 16px; line-height: 1.5; }

        .filter-form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; margin-bottom: 8px; }
        .filter-form label { font-size: 13px; font-weight: 600; color: #555; display: block; margin-bottom: 6px; }
        .filter-form select { padding: 10px 12px; border-radius: 8px; border: 1px solid #ddd; font-size: 15px; min-width: 160px; }

        table.report { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 480px; }
        table.report th, table.report td { padding: 10px 12px; border: 1px solid #e0e0e0; text-align: left; }
        table.report th { background: #1a73e8; color: white; font-weight: 600; }
        table.report .num { text-align: right; font-variant-numeric: tabular-nums; }
        .wrap-table { overflow-x: auto; }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 8px;
        }
        .stat-box {
            border: 1px solid #e0e0e0; border-radius: 10px; padding: 16px;
            background: #fafafa;
        }
        .stat-box .lbl { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-box .val { font-size: 22px; font-weight: bold; color: #1a73e8; margin-top: 6px; }
        .stat-box .sub { font-size: 12px; color: #888; margin-top: 6px; }

        .warn-box {
            background: #fff8e1; border: 1px solid #ffc107; padding: 14px; border-radius: 8px;
            font-size: 14px; color: #5d4037; margin-bottom: 16px;
        }

        @media screen and (min-width: 1025px) {
            body.nav-open { overflow: auto; }
            .sidebar-overlay { display: none !important; opacity: 0 !important; pointer-events: none !important; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<div class="mobile-header">
    <span>🏫 BHIS PORTAL</span>
    <button type="button" class="menu-toggle" id="menuToggle" aria-expanded="false" aria-controls="sidebar">☰ Menu</button>
</div>

<div class="sidebar" id="sidebar" role="navigation">
    <div class="sidebar-logo">
        <h2>🏫 BHIS PORTAL</h2>
        <p><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></p>
        <p>Admin</p>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Main</div>
        <a href="admin.php" class="nav-link"><span>📊</span> Dashboard</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Students</div>
        <a href="add_student.php" class="nav-link"><span>➕</span> Add Student</a>
        <a href="view_students.php" class="nav-link"><span>👥</span> View Students</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Academics</div>
        <a href="view_marks.php" class="nav-link"><span>📝</span> View Marks</a>
        <a href="attendance_report.php" class="nav-link"><span>📋</span> Attendance Report</a>
        <a href="student_attendance_report.php" class="nav-link"><span>👤</span> Student Report</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Administration</div>
        <a href="manage_users.php" class="nav-link"><span>🔑</span> Manage logins</a>
        <a href="fees.php" class="nav-link"><span>💰</span> Student fees</a>
        <a href="feeding_fees.php" class="nav-link"><span>🍽</span> Daily feeding</a>
        <a href="feeding_report.php" class="nav-link"><span>📅</span> Feeding by date</a>
        <a href="fee_reports.php" class="nav-link active"><span>📈</span> Fee reports</a>
        <a href="fee_student_payments.php" class="nav-link"><span>📋</span> Payment history</a>
    </div>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>📈 Fee reports</h1>
        <div style="font-size:14px; color:#666;"><?php echo htmlspecialchars($selected_year); ?></div>
    </div>

    <?php if (!$fees_ok): ?>
        <div class="warn-box">Fee tables are missing. Run <code>schema_fees.sql</code> first.</div>
    <?php else: ?>

    <?php if ($report_error !== ''): ?>
        <div class="warn-box"><?php echo $report_error; ?></div>
    <?php endif; ?>

    <?php if (!$has_exam_col): ?>
        <div class="warn-box">
            The <code>exam_fee_paid</code> column is missing from <code>student_fees</code>. Run <code>schema_fees_v2.sql</code> in MySQL so exam fees and totals load correctly.
        </div>
    <?php endif; ?>

    <?php if ($balances_but_no_log): ?>
        <div class="warn-box">
            Student balances show money collected, but the <strong>payment log</strong> is empty for this year. That usually means fees were saved before the log was installed, or saves failed silently.
            Go to <a href="fees.php" style="color:#1565c0;font-weight:600;">Student fees</a>, pick this same academic year (<strong><?php echo htmlspecialchars($selected_year); ?></strong>), set <strong>payment date</strong> and <strong>term</strong>, then click <strong>Save fee payments</strong> again (you can re-save the same amounts) to create log lines for receipts and daily breakdowns.
        </div>
    <?php endif; ?>

    <form method="get" class="card filter-form">
        <div>
            <label for="academic_year">Academic year</label>
            <select name="academic_year" id="academic_year" onchange="this.form.submit()">
                <?php foreach ($year_options as $yo): ?>
                    <option value="<?php echo htmlspecialchars($yo); ?>" <?php echo $yo === $selected_year ? 'selected' : ''; ?>><?php echo htmlspecialchars($yo); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="term">Term (for daily / term collections)</label>
            <select name="term" id="term" onchange="this.form.submit()">
                <option value="">All terms</option>
                <?php foreach ($terms_list as $t): ?>
                    <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $term_filter === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <div class="card">
        <h2>Expected revenue vs collected (school &amp; exam)</h2>
        <p class="hint">
            <strong>Total expected (school + exam)</strong> is the sum of: (1) every enrolled student × the yearly <strong>school</strong> fee set for <?php echo htmlspecialchars($selected_year); ?> (GHS <?php echo number_format($school_exp, 2); ?> × <?php echo (int) $student_count; ?> = GHS <?php echo number_format($exp_school_all, 2); ?>), plus
            (2) every enrolled student × the yearly <strong>exam</strong> fee (GHS <?php echo number_format($exam_exp, 2); ?> × <?php echo (int) $student_count; ?> = GHS <?php echo number_format($exp_exam_all, 2); ?>).
            Feeding is tracked separately (daily register); year feeding collected: GHS <?php echo number_format($total_feeding_collected, 2); ?>.
        </p>
        <div class="stat-grid">
            <div class="stat-box">
                <div class="lbl">Expected if all <?php echo (int) $student_count; ?> students paid school + exam in full</div>
                <div class="val">GHS <?php echo number_format($expected_full_year, 2); ?></div>
                <div class="sub">Total school expected + total exam expected (same figure as the two boxes below)</div>
            </div>
            <div class="stat-box">
                <div class="lbl">Collected school + exam (year)</div>
                <div class="val">GHS <?php echo number_format($total_collected_school_exam, 2); ?></div>
                
            </div>
            <div class="stat-box">
                <div class="lbl">Remaining gap (school + exam)</div>
                <div class="val" style="color:<?php echo $shortfall > 0 ? '#c62828' : '#2e7d32'; ?>">GHS <?php echo number_format($shortfall, 2); ?></div>
                <div class="sub">Expected school+exam − collected school+exam</div>
            </div>
        </div>
     
    </div>

    <div class="card">
        <h2>Remaining to collect (by fee type)</h2>
       
        <div class="stat-grid">
            <div class="stat-box">
                <div class="lbl">School — expected</div>
                <div class="val">GHS <?php echo number_format($exp_school_all, 2); ?></div>
                <div class="sub">Collected: <?php echo number_format($total_school_collected, 2); ?> · Remaining: <strong style="color:#c62828"><?php echo number_format($rem_school, 2); ?></strong></div>
            </div>
            <div class="stat-box">
                <div class="lbl">Exam — expected</div>
                <div class="val">GHS <?php echo number_format($exp_exam_all, 2); ?></div>
                <div class="sub">Collected: <?php echo number_format($total_exam_collected, 2); ?> · Remaining: <strong style="color:#c62828"><?php echo number_format($rem_exam, 2); ?></strong></div>
            </div>
        </div>
        <p class="hint" style="margin-top:12px;">Feeding balances are not shown here — use <a href="feeding_report.php">Feeding by date</a> for daily collections.</p>
    </div>

    <div class="card">
        <h2>School &amp; exam collected by class</h2>
        <p class="hint">Totals from student fee balances for <?php echo htmlspecialchars($selected_year); ?>.</p>
        <div class="wrap-table">
            <table class="report">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th class="num">Students</th>
                        <th class="num">School fees collected</th>
                        <th class="num">Exam fees collected</th>
                        <th class="num">School + exam</th>
                        <th class="num">Expected (school + exam)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($class_counts as $cn => $cnt): ?>
                        <?php
                        $ct = isset($class_totals[$cn]) ? $class_totals[$cn] : ['school' => 0, 'exam' => 0, 'feeding' => 0];
                        $sx = (float) $ct['school'] + (float) $ct['exam'];
                        $exp_c = isset($expected_classes[$cn]) ? $expected_classes[$cn] : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cn); ?></td>
                            <td class="num"><?php echo (int) $cnt; ?></td>
                            <td class="num"><?php echo number_format($ct['school'], 2); ?></td>
                            <td class="num"><?php echo number_format($ct['exam'], 2); ?></td>
                            <td class="num"><strong><?php echo number_format($sx, 2); ?></strong></td>
                            <td class="num"><?php echo number_format($exp_c, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($class_counts)): ?>
                        <tr><td colspan="6">No students in the system.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Feeding &amp; all fees — collected by day</h2>
        <?php if (!$payments_ok && !$feeding_log_ok): ?>
            <div class="warn-box">
                Run <code>schema_fees_v2.sql</code> for school/exam payment logging, and <code>schema_feeding_daily.sql</code> for the daily feeding register.
            </div>
        <?php elseif (empty($daily_rows)): ?>
            <p class="hint">No entries yet for this year and filter. Record <a href="feeding_fees.php">daily feeding</a> or save school/exam fees on <strong>Student fees</strong> with a payment date.</p>
        <?php else: ?>
            <p class="hint">Click a <strong>date</strong> for the payment list and receipts (school/exam). Click <strong>feeding amount</strong> for that day&rsquo;s feeding list by class. <?php echo $term_filter !== '' ? 'School and exam columns use term <strong>' . htmlspecialchars($term_filter) . '</strong>; feeding includes every day for the year.' : ''; ?></p>
            <div class="wrap-table">
                <table class="report">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="num">Feeding (GHS)</th>
                            <th class="num">School (GHS)</th>
                            <th class="num">Exam (GHS)</th>
                            <th class="num">Day total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_rows as $date => $cols): ?>
                            <?php
                            $fd = (float) ($cols['feeding'] ?? 0);
                            $sd = (float) ($cols['school'] ?? 0);
                            $ed = (float) ($cols['exam'] ?? 0);
                            $daytot = $fd + $sd + $ed;
                            $dq = $url_params;
                            $dq['detail_date'] = $date;
                            $day_href = 'fee_reports.php?' . http_build_query($dq);
                            $feed_href = 'feeding_report.php?' . http_build_query(['academic_year' => $selected_year, 'date' => $date]);
                            ?>
                            <tr>
                                <td><a href="<?php echo htmlspecialchars($day_href); ?>"><?php echo htmlspecialchars($date); ?></a></td>
                                <td class="num"><?php if ($fd >= 0.005): ?><a href="<?php echo htmlspecialchars($feed_href); ?>"><?php echo number_format($fd, 2); ?></a><?php else: ?><?php echo number_format($fd, 2); ?><?php endif; ?></td>
                                <td class="num"><?php echo number_format($sd, 2); ?></td>
                                <td class="num"><?php echo number_format($ed, 2); ?></td>
                                <td class="num"><strong><?php echo number_format($daytot, 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($detail_date !== ''): ?>
    <div class="card" id="day-detail">
        <h2>Payments on <?php echo htmlspecialchars($detail_date); ?></h2>
        <?php if ($term_filter !== '' && $payments_ok): ?>
            <p class="hint">Term filter: <?php echo htmlspecialchars($term_filter); ?> (applies to school and exam from the payment log only). Feeding includes the daily register and legacy feeding lines for this date.
                <a href="fee_reports.php?<?php echo htmlspecialchars(http_build_query(['academic_year' => $selected_year, 'detail_date' => $detail_date])); ?>">Clear term for this date</a>
            </p>
        <?php endif; ?>
        <?php
        $clear_q = $url_params;
        $clear_href = 'fee_reports.php?' . http_build_query($clear_q);
        ?>
        <p class="hint"><a href="<?php echo htmlspecialchars($clear_href); ?>">Clear date filter</a>
            · <a href="<?php echo htmlspecialchars('feeding_report.php?' . http_build_query(['academic_year' => $selected_year, 'date' => $detail_date])); ?>">Feeding report for this date</a>
        </p>

        <?php if (empty($detail_lines)): ?>
            <p>No payment lines for this date<?php echo $term_filter !== '' ? ' and term' : ''; ?>.</p>
        <?php else: ?>
            <div class="wrap-table">
                <table class="report">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Type</th>
                            <th class="num">Amount (GHS)</th>
                            <th>Term</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail_lines as $dl): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dl['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($dl['class_name']); ?></td>
                                <td><?php echo htmlspecialchars(fee_payment_type_label($dl['fee_type'])); ?></td>
                                <td class="num"><?php echo number_format((float) $dl['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($dl['term']); ?></td>
                                <td><?php echo !empty($dl['receipt_is_pp']) ? '<a href="fee_receipt.php?id=' . (int) $dl['id'] . '" target="_blank" rel="noopener">Receipt</a>' : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
            $all_day_ids = array_map(function ($dl) {
                return (int) $dl['id'];
            }, array_filter($detail_lines, function ($dl) {
                return !empty($dl['receipt_is_pp']) && !empty($dl['id']);
            }));
            $batch_href = count($all_day_ids) ? 'fee_receipt.php?ids=' . implode(',', $all_day_ids) : '';
            ?>
            <?php if ($batch_href !== ''): ?>
            <p style="margin-top:14px;">
                <a href="<?php echo htmlspecialchars($batch_href); ?>" target="_blank" rel="noopener" style="font-weight:600;">Print all receipts for this day</a>
                (school / exam payment log only)
            </p>
            <?php endif; ?>
            <p class="hint">Tip: use <a href="fee_student_payments.php">student payment history</a> for every part payment with dates.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($payments_ok || $feeding_log_ok): ?>
    <div class="card">
        <h2>Collections from logs (<?php echo htmlspecialchars($term_label); ?>)</h2>
        <p class="hint">School and exam amounts come from <code>fee_payments</code><?php echo $term_filter !== '' ? ' for the selected term' : ' for the full year'; ?>. Feeding includes the <strong>daily feeding register</strong> (full year) plus any legacy feeding rows still in the payment log.</p>
        <div class="stat-grid">
            <div class="stat-box">
                <div class="lbl">School (logged)</div>
                <div class="val">GHS <?php echo number_format($term_school, 2); ?></div>
            </div>
            <div class="stat-box">
                <div class="lbl">Exam (logged)</div>
                <div class="val">GHS <?php echo number_format($term_exam, 2); ?></div>
            </div>
            <div class="stat-box">
                <div class="lbl">Feeding (logged)</div>
                <div class="val">GHS <?php echo number_format($term_feeding, 2); ?></div>
            </div>
            <div class="stat-box">
                <div class="lbl">Logged total</div>
                <div class="val">GHS <?php echo number_format($term_school + $term_exam + $term_feeding, 2); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
(function () {
    var mq = window.matchMedia('(max-width: 1024px)');
    function syncCompact() {
        var on = mq.matches;
        document.documentElement.classList.toggle('compact', on);
        if (!on) {
            document.body.classList.remove('nav-open');
            var t = document.getElementById('menuToggle');
            if (t) t.setAttribute('aria-expanded', 'false');
            var o = document.getElementById('sidebarOverlay');
            if (o) o.setAttribute('aria-hidden', 'true');
        }
    }
    syncCompact();
    if (mq.addEventListener) mq.addEventListener('change', syncCompact);
    else if (mq.addListener) mq.addListener(syncCompact);

    var body = document.body;
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var toggle = document.getElementById('menuToggle');

    function setOpen(open) {
        if (!toggle || !sidebar) return;
        body.classList.toggle('nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (overlay) overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    function closeNav() { setOpen(false); }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (!document.documentElement.classList.contains('compact')) return;
            setOpen(!body.classList.contains('nav-open'));
        });
    }
    if (overlay) overlay.addEventListener('click', closeNav);
    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                if (document.documentElement.classList.contains('compact')) closeNav();
            });
        });
    }
    window.addEventListener('resize', syncCompact);
})();
</script>

</body>
</html>
