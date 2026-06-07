<?php
/**
 * All logged payments for one student — school + exam fees with receipt links.
 */
session_start();
include 'db.php';
require_once 'fee_helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

$payments_ok    = fee_payments_table_exists($conn);
$feeding_log_ok = feeding_fee_entries_table_exists($conn);
$has_exam_col   = student_fees_has_exam_column($conn);

$cy = (int) date('Y');
$year_options = [];
for ($i = -2; $i <= 10; $i++) {
    $y = $cy + $i;
    $year_options[] = $y . '/' . ($y + 1);
}

$selected_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : fee_default_academic_year();
if (!in_array($selected_year, $year_options, true)) {
    $selected_year = fee_default_academic_year();
}

$student_id     = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$student_id_esc = mysqli_real_escape_string($conn, $student_id);
$ay_esc         = mysqli_real_escape_string($conn, $selected_year);

$student = null;
if ($student_id !== '') {
    $c       = SCHOOL_FEE_STRING_COLLATION;
    $student = $conn->query("
        SELECT student_id, full_name, class_name, guardian_name
        FROM students
        WHERE student_id COLLATE {$c} = '$student_id_esc' COLLATE {$c}
        LIMIT 1
    ")->fetch_assoc();
}

// Fetch all logged payments (school + exam + feeding)
$rows = [];
if ($payments_ok && $student) {
    $c = SCHOOL_FEE_STRING_COLLATION;
    $q = $conn->query("
        SELECT id, fee_type, amount, payment_date, term, academic_year, created_at
        FROM fee_payments
        WHERE student_id COLLATE {$c} = '$student_id_esc' COLLATE {$c}
          AND academic_year = '$ay_esc'
        ORDER BY payment_date DESC, id DESC
    ");
    while ($q && $row = $q->fetch_assoc()) {
        $rows[] = $row;
    }
}

// Also get totals directly from student_fees for summary
$sf_summary = null;
if ($student) {
    $c          = SCHOOL_FEE_STRING_COLLATION;
    $exam_col   = $has_exam_col ? 'COALESCE(exam_fee_paid, 0)' : '0';
    $sf_summary = $conn->query("
        SELECT school_fee_paid, $exam_col AS exam_fee_paid, feeding_fee_paid
        FROM student_fees
        WHERE student_id COLLATE {$c} = '$student_id_esc' COLLATE {$c}
          AND academic_year = '$ay_esc'
        LIMIT 1
    ")->fetch_assoc();
}

// Get expected amounts from settings
$settings   = fees_tables_exist($conn) ? fee_settings_row($conn, $selected_year) : ['school_fee_expected' => 500, 'exam_fee_expected' => 0];
$school_exp = (float)($settings['school_fee_expected'] ?? 500);
$exam_exp   = (float)($settings['exam_fee_expected'] ?? 0);

// Separate rows by type
$school_rows  = array_filter($rows, fn($r) => $r['fee_type'] === 'school');
$exam_rows    = array_filter($rows, fn($r) => $r['fee_type'] === 'exam');
$feeding_rows = array_filter($rows, fn($r) => $r['fee_type'] === 'feeding');

$total_school_logged  = array_sum(array_column($school_rows,  'amount'));
$total_exam_logged    = array_sum(array_column($exam_rows,    'amount'));
$total_feeding_logged = array_sum(array_column($feeding_rows, 'amount'));

$all_students = $conn->query('SELECT student_id, full_name, class_name FROM students ORDER BY class_name, full_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JHS Portal — Student Payment History</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }
        body.nav-open { overflow: hidden; }

        .sidebar-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45);
            z-index: 1001; opacity: 0; pointer-events: none; transition: opacity 0.25s ease;
        }
        html.compact .sidebar-overlay { display: block; }
        body.nav-open .sidebar-overlay { opacity: 1; pointer-events: auto; }

        .sidebar {
            width: 250px; max-width: min(280px, 86vw);
            background: linear-gradient(180deg, #1a73e8, #0d47a1);
            color: white; padding: 20px 0; position: fixed;
            left: 0; top: 0; height: 100vh; height: 100dvh;
            overflow-y: auto; z-index: 1002; transition: transform 0.25s ease;
            box-shadow: 4px 0 24px rgba(0,0,0,0.12);
        }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 20px; }
        .sidebar-logo h2 { font-size: 20px; }
        .sidebar-logo p { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        .nav-section { padding: 0 15px; margin-bottom: 10px; }
        .nav-section-title { font-size: 11px; text-transform: uppercase; opacity: 0.6; margin-bottom: 8px; padding-left: 10px; }
        .nav-link { display: flex; align-items: center; min-height: 44px; padding: 10px 15px; color: white; text-decoration: none; border-radius: 8px; margin-bottom: 4px; font-size: 14px; gap: 8px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        .logout-btn { display: block; margin: 20px 15px 0; padding: 12px 15px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 8px; text-align: center; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); min-height: 44px; }
        .logout-btn:hover { background: #e53935; }

        .main { margin-left: 250px; padding: 30px; width: calc(100% - 250px); min-width: 0; }

        @media screen and (max-width: 1024px) {
            html.compact .main { margin-left: 0 !important; width: 100% !important; padding: 14px !important; }
            html.compact .sidebar { transform: translate3d(-100%, 0, 0) !important; box-shadow: none; }
            html.compact body.nav-open .sidebar { transform: translate3d(0,0,0) !important; box-shadow: 4px 0 24px rgba(0,0,0,0.12); }
        }

        .mobile-header { display: none; background: #1a73e8; color: white; padding: 12px 14px; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; gap: 12px; }
        html.compact .mobile-header { display: flex; }
        .mobile-header span { font-weight: 600; font-size: 15px; }
        .menu-toggle { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.6); color: white; padding: 10px 14px; border-radius: 8px; cursor: pointer; font-weight: 600; min-height: 44px; }

        .top-bar { background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .top-bar h1 { font-size: 20px; color: #333; }

        .filter-card { background: white; padding: 20px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .filter-grid { display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 13px; font-weight: 600; color: #555; }
        select { padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; outline: none; background: white; min-height: 44px; }
        select:focus { border-color: #1a73e8; }
        .btn-show { padding: 10px 25px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; min-height: 44px; }
        .btn-show:hover { background: #1557b0; }

        /* Summary cards */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 25px; }
        .sum-card { background: white; border-radius: 12px; padding: 18px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .sum-card.blue { border-top: 4px solid #1a73e8; }
        .sum-card.green { border-top: 4px solid #27ae60; }
        .sum-card.orange { border-top: 4px solid #f39c12; }
        .sum-card .lbl { font-size: 12px; color: #888; text-transform: uppercase; }
        .sum-card .val { font-size: 22px; font-weight: bold; color: #333; margin-top: 6px; }
        .sum-card .bal { font-size: 13px; margin-top: 4px; }
        .bal-ok { color: #27ae60; font-weight: 600; }
        .bal-due { color: #e53935; font-weight: 600; }

        /* Section headers */
        .section-title { background: linear-gradient(135deg, #1a73e8, #0d47a1); color: white; padding: 12px 20px; border-radius: 10px 10px 0 0; font-size: 15px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .table-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 20px; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 480px; }
        thead tr { background: #f8f9fa; }
        th { padding: 12px 14px; text-align: left; font-size: 13px; color: #555; font-weight: 600; border-bottom: 2px solid #eee; white-space: nowrap; }
        td { padding: 11px 14px; font-size: 14px; color: #333; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        tr:hover { background: #fafafa; }

        .badge-school { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #e3f2fd; color: #1565c0; }
        .badge-exam { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #f3e5f5; color: #6a1b9a; }
        .badge-feeding { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #e8f5e9; color: #2e7d32; }

        .btn-receipt { padding: 6px 14px; background: #1a73e8; color: white; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .btn-receipt:hover { background: #1557b0; }

        .empty-state { text-align: center; padding: 40px; color: #999; font-size: 14px; }
        .warn-box { background: #fff8e1; border: 1px solid #ffc107; padding: 14px; border-radius: 8px; font-size: 14px; color: #5d4037; margin-bottom: 16px; }
        .info-bar { background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 12px 18px; font-size: 13px; color: #1565c0; margin-bottom: 20px; }

        .student-info-bar { background: white; padding: 16px 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .student-info-bar .name { font-size: 17px; font-weight: bold; color: #333; }
        .student-info-bar .meta { font-size: 13px; color: #888; }

        @media screen and (max-width: 1024px) {
            .filter-grid { grid-template-columns: 1fr; }
            .btn-show { width: 100%; }
            .top-bar { padding: 14px 16px; }
            .filter-card { padding: 16px; }
        }
        @media screen and (min-width: 1025px) {
            body.nav-open { overflow: auto; }
            .sidebar-overlay { display: none !important; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="mobile-header">
    <span>🏫 BHIS PORTAL</span>
    <button type="button" class="menu-toggle" id="menuToggle">☰ Menu</button>
</div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <h2>🏫 BHIS PORTAL</h2>
        <p><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></p>
        <p>Admin</p>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Main</div>
        <a href="admin.php" class="nav-link">📊 Dashboard</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Students</div>
        <a href="add_student.php" class="nav-link">➕ Add Student</a>
        <a href="view_students.php" class="nav-link">👥 View Students</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Academics</div>
        <a href="view_marks.php" class="nav-link">📝 View Marks</a>
        <a href="attendance_report.php" class="nav-link">📋 Attendance Report</a>
        <a href="student_attendance_report.php" class="nav-link">👤 Student Report</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Administration</div>
        <a href="manage_users.php" class="nav-link">🔑 Manage logins</a>
        <a href="fees.php" class="nav-link">💰 Student fees</a>
        <a href="feeding_fees.php" class="nav-link">🍽 Daily feeding</a>
        <a href="feeding_report.php" class="nav-link">📅 Feeding by date</a>
        <a href="fee_reports.php" class="nav-link">📈 Fee reports</a>
        <a href="fee_student_payments.php" class="nav-link active">📋 Payment history</a>
    </div>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>📋 Student Payment History</h1>
        <div style="font-size:14px; color:#666;"><?php echo htmlspecialchars($selected_year); ?></div>
    </div>

    <?php if (!$payments_ok): ?>
        <div class="warn-box">Run <code>schema_fees_v2.sql</code> to enable the payment log.</div>
    <?php else: ?>

    <!-- Filter Form -->
    <div class="filter-card">
        <form method="GET">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>👤 Select Student</label>
                    <select name="student_id" required>
                        <option value="">— Choose student —</option>
                        <?php while ($all_students && $s = $all_students->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($s['student_id']); ?>"
                            <?php echo $student_id === $s['student_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['full_name'] . ' (' . $s['class_name'] . ') — ' . $s['student_id']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>📅 Academic Year</label>
                    <select name="academic_year">
                        <?php foreach ($year_options as $yo): ?>
                        <option value="<?php echo htmlspecialchars($yo); ?>" <?php echo $yo === $selected_year ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($yo); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group" style="justify-content:flex-end;">
                    <button type="submit" class="btn-show">🔍 Show History</button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($student): ?>

    <!-- Student Info Bar -->
    <div class="student-info-bar">
        <div>
            <div class="name"><?php echo htmlspecialchars($student['full_name']); ?></div>
            <div class="meta">
                <?php echo htmlspecialchars($student['class_name']); ?> &nbsp;|&nbsp;
                ID: <?php echo htmlspecialchars($student['student_id']); ?>
                <?php if (!empty($student['guardian_name'])): ?>
                &nbsp;|&nbsp; Guardian: <?php echo htmlspecialchars($student['guardian_name']); ?>
                <?php endif; ?>
            </div>
        </div>
        <div style="font-size:13px; color:#888;"><?php echo htmlspecialchars($selected_year); ?></div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="sum-card blue">
            <div class="lbl">School Fees Paid</div>
            <div class="val">GHS <?php echo number_format($total_school_logged, 2); ?></div>
            <?php $school_bal = $school_exp - $total_school_logged; ?>
            <div class="bal <?php echo $school_bal <= 0 ? 'bal-ok' : 'bal-due'; ?>">
                <?php echo $school_bal <= 0 ? '✅ Fully Paid' : 'Balance: GHS ' . number_format($school_bal, 2); ?>
            </div>
        </div>
        <div class="sum-card orange">
            <div class="lbl">Exam Fees Paid</div>
            <div class="val">GHS <?php echo number_format($total_exam_logged, 2); ?></div>
            <?php $exam_bal = $exam_exp - $total_exam_logged; ?>
            <div class="bal <?php echo $exam_bal <= 0 ? 'bal-ok' : 'bal-due'; ?>">
                <?php echo $exam_bal <= 0 ? '✅ Fully Paid' : ($exam_exp > 0 ? 'Balance: GHS ' . number_format($exam_bal, 2) : '—'); ?>
            </div>
        </div>
        <div class="sum-card green">
            <div class="lbl">Total Paid</div>
            <div class="val">GHS <?php echo number_format($total_school_logged + $total_exam_logged + $total_feeding_logged, 2); ?></div>
            <div class="bal" style="color:#888;">All types combined</div>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="table-card">
            <div class="empty-state">
                <p style="font-size:36px; margin-bottom:10px;">📋</p>
                <p>No logged payments found for this student in <?php echo htmlspecialchars($selected_year); ?>.</p>
                <p style="margin-top:8px;">Go to <a href="fees.php">Student Fees</a> and use <b>Pay school now</b> / <b>Pay exam now</b> to record payments.</p>
            </div>
        </div>
    <?php else: ?>

    <div class="info-bar">
        ℹ️ Each row below is one payment entry. Click <b>🧾 Receipt</b> to open a printable PDF receipt for that payment.
    </div>

    <!-- SCHOOL FEES SECTION -->
    <div class="table-card">
        <div class="section-title">
            💰 School Fee Payments
            <span style="font-size:13px; opacity:0.85;">Total: GHS <?php echo number_format($total_school_logged, 2); ?></span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Term</th>
                        <th>Type</th>
                        <th class="num">Amount (GHS)</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($school_rows)): ?>
                    <tr><td colspan="5" class="empty-state">No school fee payments recorded yet.</td></tr>
                    <?php else: foreach ($school_rows as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('F j, Y', strtotime($r['payment_date']))); ?></td>
                        <td><?php echo htmlspecialchars($r['term']); ?></td>
                        <td><span class="badge-school">School Fee</span></td>
                        <td class="num"><?php echo number_format((float)$r['amount'], 2); ?></td>
                        <td>
                            <a href="fee_receipt.php?id=<?php echo (int)$r['id']; ?>" target="_blank" class="btn-receipt">🧾 Receipt</a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- EXAM FEES SECTION -->
    <div class="table-card">
        <div class="section-title" style="background: linear-gradient(135deg, #6a1b9a, #4a148c);">
            📝 Exam Fee Payments
            <span style="font-size:13px; opacity:0.85;">Total: GHS <?php echo number_format($total_exam_logged, 2); ?></span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Term</th>
                        <th>Type</th>
                        <th class="num">Amount (GHS)</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exam_rows)): ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            No exam fee payments recorded yet.
                            <?php if ($has_exam_col && $sf_summary && (float)$sf_summary['exam_fee_paid'] > 0): ?>
                            <br><span style="color:#e67e22; font-size:13px;">⚠️ GHS <?php echo number_format((float)$sf_summary['exam_fee_paid'], 2); ?> found in student_fees but not logged as a receipt entry. Go to <a href="fees.php">Student Fees</a> and use <b>Pay exam now</b> to log it properly.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: foreach ($exam_rows as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('F j, Y', strtotime($r['payment_date']))); ?></td>
                        <td><?php echo htmlspecialchars($r['term']); ?></td>
                        <td><span class="badge-exam">Exam Fee</span></td>
                        <td class="num"><?php echo number_format((float)$r['amount'], 2); ?></td>
                        <td>
                            <a href="fee_receipt.php?id=<?php echo (int)$r['id']; ?>" target="_blank" class="btn-receipt">🧾 Receipt</a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FEEDING FEES SECTION (if any) -->
    <?php if (!empty($feeding_rows)): ?>
    <div class="table-card">
        <div class="section-title" style="background: linear-gradient(135deg, #27ae60, #1e8449);">
            🍽 Feeding Fee Payments
            <span style="font-size:13px; opacity:0.85;">Total: GHS <?php echo number_format($total_feeding_logged, 2); ?></span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Term</th>
                        <th>Type</th>
                        <th class="num">Amount (GHS)</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feeding_rows as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('F j, Y', strtotime($r['payment_date']))); ?></td>
                        <td><?php echo htmlspecialchars($r['term']); ?></td>
                        <td><span class="badge-feeding">Feeding</span></td>
                        <td class="num"><?php echo number_format((float)$r['amount'], 2); ?></td>
                        <td>
                            <a href="fee_receipt.php?id=<?php echo (int)$r['id']; ?>" target="_blank" class="btn-receipt">🧾 Receipt</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // end empty rows check ?>
    <?php endif; // end student check ?>
    <?php endif; // end payments_ok check ?>
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
        }
    }
    syncCompact();
    if (mq.addEventListener) mq.addEventListener('change', syncCompact);
    else if (mq.addListener) mq.addListener(syncCompact);

    var body     = document.body;
    var overlay  = document.getElementById('sidebarOverlay');
    var toggle   = document.getElementById('menuToggle');
    var sidebar  = document.getElementById('sidebar');

    function setOpen(open) {
        body.classList.toggle('nav-open', open);
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (overlay) overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    if (toggle) toggle.addEventListener('click', function () {
        if (!document.documentElement.classList.contains('compact')) return;
        setOpen(!body.classList.contains('nav-open'));
    });
    if (overlay) overlay.addEventListener('click', function () { setOpen(false); });
    if (sidebar) sidebar.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () {
            if (document.documentElement.classList.contains('compact')) setOpen(false);
        });
    });
})();
</script>

</body>
</html>