<?php
session_start();
include 'db.php';
require_once 'fee_helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

$success = '';
$error   = '';
$fees_ok = fees_tables_exist($conn);

if ($fees_ok && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $_SESSION['fee_payment_nonce'] = bin2hex(random_bytes(16));
}
if (isset($_GET['fee_saved']) && $_GET['fee_saved'] === '1') {
    $success = 'Student fee payments were saved.';
}

$cy           = (int) date('Y');
$year_options = [];
for ($i = -2; $i <= 10; $i++) {
    $y              = $cy + $i;
    $year_options[] = $y . '/' . ($y + 1);
}

$selected_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : fee_default_academic_year();
if (!in_array($selected_year, $year_options, true)) {
    $selected_year = fee_default_academic_year();
}

$allowed_terms = ['First Term', 'Second Term', 'Third Term'];
$view_term     = isset($_GET['view_term']) && in_array($_GET['view_term'], $allowed_terms, true)
                 ? $_GET['view_term'] : 'First Term';

$classes    = ['JHS 1', 'JHS 2', 'JHS 3'];
$class_get  = isset($_GET['class']) ? trim((string) $_GET['class']) : '';
if ($class_get === '' || !in_array($class_get, $classes, true)) {
    $class_get = '';
}
$class_filter = $class_get !== '' ? mysqli_real_escape_string($conn, $class_get) : '';

// Save fee standards
if ($fees_ok && isset($_POST['save_fee_standards'])) {
    $ay          = mysqli_real_escape_string($conn, trim($_POST['standards_academic_year'] ?? ''));
    $school_exp  = max(0, floatval($_POST['school_fee_expected'] ?? 500));
    $feeding_exp = max(0, floatval($_POST['feeding_fee_expected'] ?? 0));
    $exam_exp    = max(0, floatval($_POST['exam_fee_expected'] ?? 0));
    if ($ay === '') {
        $error = 'Academic year is required.';
    } else {
        $conn->query("INSERT INTO fee_settings (academic_year, school_fee_expected, feeding_fee_expected, exam_fee_expected)
            VALUES ('$ay', '$school_exp', '$feeding_exp', '$exam_exp')
            ON DUPLICATE KEY UPDATE school_fee_expected='$school_exp', feeding_fee_expected='$feeding_exp', exam_fee_expected='$exam_exp'");
        $success       = 'Fee amounts for the year were saved.';
        $selected_year = $ay;
    }
}

// Save student fees
if ($fees_ok && isset($_POST['save_student_fees'])) {
    $ay       = mysqli_real_escape_string($conn, trim($_POST['payments_academic_year'] ?? ''));
    $pt_raw   = trim($_POST['payment_term'] ?? 'First Term');
    $pay_term = in_array($pt_raw, $allowed_terms, true) ? $pt_raw : 'First Term';
    $pay_term_esc = mysqli_real_escape_string($conn, $pay_term);

    $pd_raw = trim($_POST['payment_date'] ?? '');
    if ($pd_raw === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pd_raw)) {
        $pay_date_esc = mysqli_real_escape_string($conn, date('Y-m-d'));
    } else {
        $pay_date_esc = mysqli_real_escape_string($conn, $pd_raw);
    }

    $log_payments    = fee_payments_table_exists($conn);
    $school_add_post = [];
    if (isset($_POST['school_payment_add']) && is_array($_POST['school_payment_add'])) {
        foreach ($_POST['school_payment_add'] as $k => $v) {
            $school_add_post[trim((string) $k)] = $v;
        }
    }
    $exam_add_post = [];
    if (isset($_POST['exam_payment_add']) && is_array($_POST['exam_payment_add'])) {
        foreach ($_POST['exam_payment_add'] as $k => $v) {
            $exam_add_post[trim((string) $k)] = $v;
        }
    }
    $payment_sid_keys = array_values(array_unique(array_merge(array_keys($school_add_post), array_keys($exam_add_post))));

    if ($ay === '') {
        $error = 'Academic year is missing.';
    } elseif (empty($payment_sid_keys)) {
        $error = 'No fee data submitted.';
    } else {
        $any_add = false;
        foreach ($payment_sid_keys as $psk) {
            $sa = max(0, floatval($school_add_post[$psk] ?? 0));
            $ea = max(0, floatval($exam_add_post[$psk] ?? 0));
            if ($sa >= 0.005 || $ea >= 0.005) { $any_add = true; break; }
        }
        if (!$any_add) {
            $error = 'Enter a school or exam payment for at least one student.';
        } elseif (strlen((string)($_SESSION['fee_payment_nonce'] ?? '')) < 8
            || !isset($_POST['fee_payment_nonce'])
            || !is_string($_POST['fee_payment_nonce'])
            || !hash_equals((string)$_SESSION['fee_payment_nonce'], (string)$_POST['fee_payment_nonce'])
        ) {
            $error = 'This payment form expired or was already submitted. Refresh the page and try again.';
        } else {
            unset($_SESSION['fee_payment_nonce']);
            if (!$conn->begin_transaction()) {
                $error = 'Could not start fee save transaction: ' . htmlspecialchars($conn->error);
                $_SESSION['fee_payment_nonce'] = bin2hex(random_bytes(16));
            } else {
                $c_sf = SCHOOL_FEE_STRING_COLLATION;
                foreach ($payment_sid_keys as $raw_sid_key) {
                    $school_add = max(0, floatval($school_add_post[$raw_sid_key] ?? 0));
                    $exam_add   = max(0, floatval($exam_add_post[$raw_sid_key] ?? 0));
                    $raw_sid    = trim((string) $raw_sid_key);
                    $sid        = mysqli_real_escape_string($conn, $raw_sid);
                    if ($school_add < 0.005 && $exam_add < 0.005) continue;

                    $sid_db_esc = $sid;
                    $sid_can = $conn->query("SELECT student_id FROM students WHERE student_id COLLATE {$c_sf} = '$sid' COLLATE {$c_sf} LIMIT 1");
                    if ($sid_can && ($sid_row = $sid_can->fetch_assoc())) {
                        $sid_db_esc = mysqli_real_escape_string($conn, $sid_row['student_id']);
                    }

                    $old_row = $conn->query("SELECT school_fee_paid, feeding_fee_paid, exam_fee_paid FROM student_fees WHERE student_id COLLATE {$c_sf} = '$sid_db_esc' COLLATE {$c_sf} AND academic_year='$ay' LIMIT 1")->fetch_assoc();
                    $old_s   = $old_row ? (float)($old_row['school_fee_paid'] ?? 0) : 0;
                    $old_f   = $old_row ? (float)($old_row['feeding_fee_paid'] ?? 0) : 0;
                    $old_e   = $old_row ? (float)($old_row['exam_fee_paid'] ?? 0) : 0;

                    $new_s     = $old_s + $school_add;
                    $new_e     = $old_e + $exam_add;
                    $new_s_esc = mysqli_real_escape_string($conn, (string) $new_s);
                    $new_e_esc = mysqli_real_escape_string($conn, (string) $new_e);
                    $old_f_esc = mysqli_real_escape_string($conn, (string) $old_f);

                    $upd = $conn->query("INSERT INTO student_fees (student_id, academic_year, school_fee_paid, feeding_fee_paid, exam_fee_paid)
                        VALUES ('$sid_db_esc', '$ay', '$new_s_esc', '$old_f_esc', '$new_e_esc')
                        ON DUPLICATE KEY UPDATE school_fee_paid='$new_s_esc', exam_fee_paid='$new_e_esc'");
                    if (!$upd) {
                        $error = 'Could not save fee balances: ' . htmlspecialchars($conn->error);
                        break;
                    }

                    if ($log_payments) {
                        if ($school_add >= 0.005) {
                            $d   = mysqli_real_escape_string($conn, (string) $school_add);
                            $ins = $conn->query("INSERT INTO fee_payments (student_id, academic_year, term, fee_type, amount, payment_date)
                                VALUES ('$sid_db_esc', '$ay', '$pay_term_esc', 'school', '$d', '$pay_date_esc')");
                            if (!$ins && $error === '') $error = 'Payment log failed: ' . htmlspecialchars($conn->error);
                        }
                        if ($error === '' && $exam_add >= 0.005) {
                            $d   = mysqli_real_escape_string($conn, (string) $exam_add);
                            $ins = $conn->query("INSERT INTO fee_payments (student_id, academic_year, term, fee_type, amount, payment_date)
                                VALUES ('$sid_db_esc', '$ay', '$pay_term_esc', 'exam', '$d', '$pay_date_esc')");
                            if (!$ins && $error === '') $error = 'Payment log failed: ' . htmlspecialchars($conn->error);
                        }
                    }
                    if ($error !== '') break;
                }
                if ($error === '') {
                    $conn->commit();
                    $redirect_params = [
                        'academic_year' => trim($_POST['payments_academic_year'] ?? $selected_year),
                        'view_term'     => $pay_term,
                        'fee_saved'     => '1',
                    ];
                    $ret_class = trim((string)($_POST['fee_return_class'] ?? ''));
                    if ($ret_class !== '' && in_array($ret_class, $classes, true)) {
                        $redirect_params['class'] = $ret_class;
                    }
                    header('Location: fees.php?' . http_build_query($redirect_params));
                    exit;
                }
                $conn->rollback();
                $_SESSION['fee_payment_nonce'] = bin2hex(random_bytes(16));
            }
        }
        $selected_year = trim($_POST['payments_academic_year'] ?? $selected_year);
    }
}

$settings = $fees_ok ? fee_settings_row($conn, $selected_year) : ['school_fee_expected' => 500, 'feeding_fee_expected' => 0, 'exam_fee_expected' => 0];

$students_for_table = null;
if ($fees_ok) {
    $ay_esc = mysqli_real_escape_string($conn, $selected_year);
    if ($class_filter !== '') {
        $sql = "SELECT student_id, full_name, class_name FROM students
                WHERE class_name='$class_filter'
                AND (status='Active' OR (status='Graduated' AND graduation_year >= '$ay_esc'))
                ORDER BY class_name, full_name";
    } else {
        $sql = "SELECT student_id, full_name, class_name FROM students
                WHERE (status='Active' OR (status='Graduated' AND graduation_year >= '$ay_esc'))
                ORDER BY class_name, full_name";
    }
    $students_for_table = $conn->query($sql);
}

function fee_balance($expected, $paid) {
    return max(0, (float)$expected - (float)$paid);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JHS Portal - Student fees</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        html, body { width: 100%; max-width: 100%; overflow-x: hidden; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }
        body.nav-open { overflow: hidden; }

        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 1001; opacity: 0; pointer-events: none; transition: opacity 0.25s ease; }
        html.compact .sidebar-overlay { display: block; }
        body.nav-open .sidebar-overlay { opacity: 1; pointer-events: auto; }

        .sidebar { width: 250px; max-width: min(280px,86vw); background: linear-gradient(180deg,#1a73e8,#0d47a1); color: white; padding: 20px 0; position: fixed; left: 0; top: 0; height: 100vh; height: 100dvh; overflow-y: auto; z-index: 1002; transition: transform 0.25s ease; box-shadow: 4px 0 24px rgba(0,0,0,0.12); }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 20px; }
        .sidebar-logo h2 { font-size: 20px; }
        .sidebar-logo p { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        .nav-section { padding: 0 15px; margin-bottom: 10px; }
        .nav-section-title { font-size: 11px; text-transform: uppercase; opacity: 0.6; margin-bottom: 8px; padding-left: 10px; }
        .nav-link { display: flex; align-items: center; min-height: 44px; padding: 10px 15px; color: white; text-decoration: none; border-radius: 8px; margin-bottom: 4px; transition: 0.2s; font-size: 14px; gap: 8px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        .logout-btn { display: block; margin: 20px 15px 0; padding: 12px 15px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 8px; text-align: center; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); min-height: 44px; }
        .logout-btn:hover { background: #e53935; }

        .main { margin-left: 250px; padding: 30px; width: calc(100% - 250px); min-width: 0; }

        @media screen and (max-width: 1024px) {
            html.compact .main { margin-left: 0 !important; width: 100% !important; padding: 14px !important; }
            html.compact .sidebar { transform: translate3d(-100%,0,0) !important; box-shadow: none; }
            html.compact body.nav-open .sidebar { transform: translate3d(0,0,0) !important; box-shadow: 4px 0 24px rgba(0,0,0,0.12); }
        }
        @media screen and (min-width: 1025px) {
            body.nav-open { overflow: auto; }
            .sidebar-overlay { display: none !important; opacity: 0 !important; pointer-events: none !important; }
        }

        .mobile-header { display: none; background: #1a73e8; color: white; padding: 12px 14px; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; gap: 12px; }
        html.compact .mobile-header { display: flex; }
        .mobile-header span { font-weight: 600; font-size: 15px; }
        .menu-toggle { flex-shrink: 0; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.6); color: white; padding: 10px 14px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; min-height: 44px; }

        .top-bar { background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .top-bar h1 { font-size: 20px; color: #333; }

        .form-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .form-card h2 { font-size: 17px; color: #333; margin-bottom: 8px; }
        .hint { font-size: 13px; color: #666; margin-bottom: 18px; line-height: 1.5; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        label { font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        input, select { padding: 12px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; width: 100%; min-height: 44px; outline: none; background: white; }
        input:focus, select:focus { border-color: #1a73e8; }
        .form-actions { margin-top: 20px; display: flex; flex-wrap: wrap; gap: 10px; }
        .btn-submit { padding: 13px 28px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer; min-height: 48px; }
        .btn-submit:hover { background: #1557b0; }

        .success-msg { background: #e8f5e9; color: #2e7d32; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2e7d32; font-size: 14px; }
        .error-msg { background: #ffebee; color: #c62828; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c62828; font-size: 14px; }
        .install-box { background: #fff8e1; border: 1px solid #ffc107; color: #5d4037; padding: 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.5; }

        .filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; margin-bottom: 20px; }
        .filter-row .form-group { min-width: 150px; flex: 1; }

        .term-info-bar { background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 12px 18px; font-size: 13px; color: #1565c0; margin-bottom: 15px; }

        .fee-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table.fee-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 900px; }
        .fee-table th { background: #1a73e8; color: white; padding: 10px 8px; text-align: left; border: 1px solid #1557b0; white-space: nowrap; }
        .fee-table th.exam-col { background: #6a1b9a; border-color: #4a148c; }
        .fee-table td { padding: 8px; border: 1px solid #e0e0e0; vertical-align: middle; }
        .fee-table input[type="number"] { width: 100%; min-width: 0; padding: 8px 10px; font-size: 15px; border: 1px solid #ddd; border-radius: 6px; }
        .fee-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .fee-table .owe { color: #c62828; font-weight: 600; }
        .fee-table .ok { color: #2e7d32; }

        @media screen and (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-card { padding: 16px; }
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
        <p><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></p>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Main</div>
        <a href="admin.php" class="nav-link">📊 Dashboard</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Students</div>
        <a href="add_student.php" class="nav-link">➕ Add Student</a>
        <a href="view_students.php" class="nav-link">👥 View Students</a>
        <a href="old_students.php" class="nav-link">🎓 Old Students</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Academics</div>
        <a href="view_marks.php" class="nav-link">📝 View Marks</a>
        <a href="attendance_report.php" class="nav-link">📋 Attendance Report</a>
        <a href="student_attendance_report.php" class="nav-link">👤 Student Report</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Administration</div>
        <a href="manage_users.php" class="nav-link">🔑 Manage Logins</a>
        <a href="fees.php" class="nav-link active">💰 Student Fees</a>
        <a href="feeding_fees.php" class="nav-link">🍽 Daily Feeding</a>
        <a href="feeding_report.php" class="nav-link">📅 Feeding by Date</a>
        <a href="fee_reports.php" class="nav-link">📈 Fee Reports</a>
        <a href="fee_student_payments.php" class="nav-link">📋 Payment History</a>
        <a href="term_settings.php" class="nav-link">📅 Term Settings</a>
        <a href="process_promotions.php" class="nav-link">🔄 Process Promotions</a>
    </div>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>💰 Student Fees</h1>
        <div style="font-size:14px; color:#666;"><?php echo date('F j, Y'); ?></div>
    </div>

    <?php if ($success): ?>
        <div class="success-msg">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error-msg">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!$fees_ok): ?>
        <div class="install-box">
            <strong>Database setup required.</strong> Fee tables are not installed yet. Run <code>schema_fees.sql</code> then <code>schema_fees_v2.sql</code> in phpMyAdmin.
        </div>
    <?php endif; ?>

    <?php if ($fees_ok): ?>

    <!-- Expected Fee Settings -->
    <div class="form-card">
        <h2>📋 Expected Fees for <?php echo htmlspecialchars($selected_year); ?></h2>
        <p class="hint">
            <b>School fee</b> is paid <b>once per academic year</b>.
            <b>Exam fee</b> is paid <b>each term</b> (amount shown is per term).
        </p>
        <form method="post">
            <input type="hidden" name="standards_academic_year" value="<?php echo htmlspecialchars($selected_year); ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>School Fee Expected — Full Year (GHS)</label>
                    <input type="number" step="0.01" min="0" name="school_fee_expected"
                        value="<?php echo htmlspecialchars((string)$settings['school_fee_expected']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Exam Fee Expected — Per Term (GHS)</label>
                    <input type="number" step="0.01" min="0" name="exam_fee_expected"
                        value="<?php echo htmlspecialchars((string)$settings['exam_fee_expected']); ?>">
                </div>
                <div class="form-group">
                    <label>Feeding Fee Expected (GHS)</label>
                    <input type="number" step="0.01" min="0" name="feeding_fee_expected"
                        value="<?php echo htmlspecialchars((string)$settings['feeding_fee_expected']); ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="save_fee_standards" value="1" class="btn-submit">
                    💾 Save Expected Amounts for <?php echo htmlspecialchars($selected_year); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Filter -->
    <div class="form-card">
        <h2>💳 Record & View Student Fees</h2>

        <form method="get" class="filter-row">
            <div class="form-group">
                <label>📅 Academic Year</label>
                <select name="academic_year" onchange="this.form.submit()">
                    <?php foreach ($year_options as $yo): ?>
                    <option value="<?php echo htmlspecialchars($yo); ?>" <?php echo $yo===$selected_year?'selected':''; ?>>
                        <?php echo htmlspecialchars($yo); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>📚 View Exam Fees For Term</label>
                <select name="view_term" onchange="this.form.submit()">
                    <?php foreach ($allowed_terms as $tm): ?>
                    <option value="<?php echo htmlspecialchars($tm); ?>" <?php echo $view_term===$tm?'selected':''; ?>>
                        <?php echo htmlspecialchars($tm); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>🏫 Class</label>
                <select name="class" onchange="this.form.submit()">
                    <option value="">All classes</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $class_get===$c?'selected':''; ?>>
                        <?php echo htmlspecialchars($c); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <div class="term-info-bar">
            ℹ️ <b>School fees</b> show total paid for the whole year <b><?php echo htmlspecialchars($selected_year); ?></b>.
            &nbsp;|&nbsp;
            <b>Exam fees</b> show only <b><?php echo htmlspecialchars($view_term); ?></b> payments.
            Switch the term above to view other terms' exam fees.
        </div>

        <!-- Payment Form -->
        <form method="post" id="feePaymentsSaveForm" autocomplete="off">
            <input type="hidden" name="payments_academic_year" value="<?php echo htmlspecialchars($selected_year); ?>">
            <input type="hidden" name="fee_payment_nonce" value="<?php echo htmlspecialchars($_SESSION['fee_payment_nonce'] ?? ''); ?>">
            <input type="hidden" name="fee_return_class" value="<?php echo htmlspecialchars($class_get); ?>">

            <div class="form-grid" style="margin-bottom:18px; max-width:720px;">
                <div class="form-group">
                    <label>📅 Recording Payment for Term</label>
                    <select name="payment_term">
                        <?php foreach ($allowed_terms as $tm): ?>
                        <option value="<?php echo htmlspecialchars($tm); ?>"
                            <?php echo $view_term===$tm?'selected':''; ?>>
                            <?php echo htmlspecialchars($tm); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>📅 Payment Date</label>
                    <input type="date" name="payment_date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                </div>
            </div>

            <div class="fee-table-wrap">
                <table class="fee-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th class="num">School Fee Paid (Year)</th>
                            <th>Pay School Now (GHS)</th>
                            <th class="num">School Fee Balance</th>
                            <th class="num exam-col">Exam Fee Paid (<?php echo htmlspecialchars($view_term); ?>)</th>
                            <th class="exam-col">Pay Exam Now (GHS)</th>
                            <th class="num exam-col">Exam Fee Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $school_exp = (float)$settings['school_fee_expected'];
                    $exam_exp   = (float)$settings['exam_fee_expected'];
                    $view_term_esc = mysqli_real_escape_string($conn, $view_term);
                    $ay_esc_t      = mysqli_real_escape_string($conn, $selected_year);

                    if ($students_for_table && $students_for_table->num_rows > 0):
                        while ($st = $students_for_table->fetch_assoc()):
                            $sid  = $st['student_id'];
                            $paid = student_fees_row($conn, $sid, $selected_year);

                            // School fee — whole academic year
                            $s_bal   = fee_balance($school_exp, $paid['school_fee_paid']);
                            $s_class = $s_bal > 0 ? 'owe' : 'ok';

                            // Exam fee — selected term only
                            $sid_esc = mysqli_real_escape_string($conn, $sid);
                            $exam_paid_this_term = (float)$conn->query("
                                SELECT COALESCE(SUM(amount),0) AS t
                                FROM fee_payments
                                WHERE student_id='$sid_esc'
                                AND academic_year='$ay_esc_t'
                                AND fee_type='exam'
                                AND term='$view_term_esc'
                            ")->fetch_assoc()['t'];

                            $e_bal   = fee_balance($exam_exp, $exam_paid_this_term);
                            $e_class = ($exam_exp > 0 && $e_bal > 0) ? 'owe' : 'ok';
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($st['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($st['class_name']); ?></td>
                            <td class="num"><?php echo number_format((float)$paid['school_fee_paid'], 2); ?></td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                    name="school_payment_add[<?php echo htmlspecialchars($sid, ENT_QUOTES); ?>]"
                                    value="" placeholder="0" autocomplete="off">
                            </td>
                            <td class="num <?php echo $s_class; ?>"><?php echo number_format($s_bal, 2); ?></td>
                            <td class="num"><?php echo number_format($exam_paid_this_term, 2); ?></td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                    name="exam_payment_add[<?php echo htmlspecialchars($sid, ENT_QUOTES); ?>]"
                                    value="" placeholder="0" autocomplete="off">
                            </td>
                            <td class="num <?php echo $e_class; ?>"><?php echo $exam_exp > 0 ? number_format($e_bal, 2) : '—'; ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:20px; color:#999;">No students found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions" style="margin-top:16px;">
                <input type="hidden" name="save_student_fees" value="1">
                <button type="submit" class="btn-submit">💾 Add Payments to Totals</button>
            </div>
        </form>
    </div>

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
        }
    }
    syncCompact();
    if (mq.addEventListener) mq.addEventListener('change', syncCompact);
    else if (mq.addListener) mq.addListener(syncCompact);

    var body    = document.body;
    var overlay = document.getElementById('sidebarOverlay');
    var toggle  = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');

    function setOpen(open) {
        if (!toggle || !sidebar) return;
        body.classList.toggle('nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
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

    var feePayForm = document.getElementById('feePaymentsSaveForm');
    if (feePayForm) {
        feePayForm.addEventListener('submit', function () {
            var btn = feePayForm.querySelector('button[type="submit"]');
            if (btn) { btn.textContent = 'Saving…'; btn.setAttribute('aria-busy', 'true'); }
        });
    }
})();
</script>
</body>
</html>