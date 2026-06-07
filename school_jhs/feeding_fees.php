<?php
/**
 * Record feeding fees paid on a specific day (one row per student per save).
 */
session_start();
include 'db.php';
require_once 'fee_helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

$fees_ok = fees_tables_exist($conn);
$table_ok = feeding_fee_entries_table_exists($conn);

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

$classes = ['JHS 1', 'JHS 2', 'JHS 3'];
$class_get = isset($_GET['class']) ? trim($_GET['class']) : '';
if ($class_get !== '' && !in_array($class_get, $classes, true)) {
    $class_get = '';
}
$class_filter = $class_get !== '' ? mysqli_real_escape_string($conn, $class_get) : '';

$feeding_date = isset($_GET['feeding_date']) ? trim($_GET['feeding_date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $feeding_date)) {
    $feeding_date = date('Y-m-d');
}

$success = '';
$error = '';

if ($fees_ok && $table_ok && isset($_POST['save_feeding_daily'])) {
    $ay = mysqli_real_escape_string($conn, trim($_POST['feeding_academic_year'] ?? ''));
    $pd_raw = trim($_POST['feeding_payment_date'] ?? '');
    if ($pd_raw === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pd_raw)) {
        $error = 'Choose a valid payment date.';
    } elseif ($ay === '') {
        $error = 'Academic year is required.';
    } elseif (empty($_POST['feeding_amount']) || !is_array($_POST['feeding_amount'])) {
        $error = 'No amounts submitted.';
    } else {
        $pd_esc = mysqli_real_escape_string($conn, $pd_raw);
        $has_exam = student_fees_has_exam_column($conn);
        $conn->begin_transaction();
        foreach ($_POST['feeding_amount'] as $raw_sid => $val) {
            $amt = max(0, floatval($val));
            if ($amt < 0.005) {
                continue;
            }
            $sid = mysqli_real_escape_string($conn, $raw_sid);
            $amt_esc = mysqli_real_escape_string($conn, (string) $amt);

            // One amount per student per calendar date: remove any prior lines for this date before inserting.
            $prev_sum = 0.0;
            $prev_q = $conn->query("SELECT COALESCE(SUM(amount), 0) AS s FROM feeding_fee_entries WHERE student_id='$sid' AND academic_year='$ay' AND payment_date='$pd_esc'");
            if ($prev_q && ($pr = $prev_q->fetch_assoc())) {
                $prev_sum = (float) $pr['s'];
            }
            if (!$conn->query("DELETE FROM feeding_fee_entries WHERE student_id='$sid' AND academic_year='$ay' AND payment_date='$pd_esc'")) {
                $error = 'Could not reset feeding entry for this date: ' . htmlspecialchars($conn->error);
                break;
            }

            $ins = $conn->query("INSERT INTO feeding_fee_entries (student_id, academic_year, amount, payment_date)
                VALUES ('$sid', '$ay', '$amt_esc', '$pd_esc')");
            if (!$ins) {
                $error = 'Could not save feeding entry: ' . htmlspecialchars($conn->error);
                break;
            }

            $old_row = $conn->query("SELECT school_fee_paid, feeding_fee_paid, exam_fee_paid FROM student_fees WHERE student_id='$sid' AND academic_year='$ay' LIMIT 1")->fetch_assoc();
            $old_s = $old_row ? (float) $old_row['school_fee_paid'] : 0;
            $old_f = $old_row ? (float) $old_row['feeding_fee_paid'] : 0;
            $old_e = ($old_row && isset($old_row['exam_fee_paid'])) ? (float) $old_row['exam_fee_paid'] : 0;
            $new_f = $old_f - $prev_sum + $amt;
            $s_esc = mysqli_real_escape_string($conn, (string) $old_s);
            $f_esc = mysqli_real_escape_string($conn, (string) $new_f);
            $e_esc = mysqli_real_escape_string($conn, (string) $old_e);

            if ($has_exam) {
                $upd = $conn->query("INSERT INTO student_fees (student_id, academic_year, school_fee_paid, feeding_fee_paid, exam_fee_paid)
                    VALUES ('$sid', '$ay', '$s_esc', '$f_esc', '$e_esc')
                    ON DUPLICATE KEY UPDATE feeding_fee_paid='$f_esc'");
            } else {
                $upd = $conn->query("INSERT INTO student_fees (student_id, academic_year, school_fee_paid, feeding_fee_paid)
                    VALUES ('$sid', '$ay', '$s_esc', '$f_esc')
                    ON DUPLICATE KEY UPDATE feeding_fee_paid='$f_esc'");
            }
            if (!$upd) {
                $error = 'Entry saved but student balance failed: ' . htmlspecialchars($conn->error);
                break;
            }
        }
        if ($error === '') {
            $conn->commit();
            $ay_plain = trim($_POST['feeding_academic_year'] ?? '');
            header('Location: feeding_report.php?' . http_build_query([
                'academic_year' => $ay_plain,
                'date' => $pd_raw,
            ]));
            exit;
        }
        $conn->rollback();
    }
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily feeding fees</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 24px; }
        .card { background: white; max-width: 960px; margin: 0 auto; padding: 24px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        h1 { font-size: 20px; margin-bottom: 8px; }
        label { font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px; }
        select, input[type="date"], input[type="number"] { padding: 10px 12px; font-size: 15px; border-radius: 8px; border: 1px solid #ddd; }
        .row { margin-bottom: 18px; display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 16px; }
        th, td { padding: 10px 12px; border: 1px solid #e0e0e0; text-align: left; }
        th { background: #0d47a1; color: white; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .hint { color: #666; font-size: 14px; margin-bottom: 16px; line-height: 1.5; }
        a { color: #1a73e8; }
        .nav { margin-bottom: 16px; }
        .nav a { margin-right: 16px; }
        .success-msg { background: #e8f5e9; border: 1px solid #4caf50; color: #1b5e20; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; }
        .error-msg { background: #ffebee; border: 1px solid #e57373; color: #b71c1c; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; }
        .warn-box { background: #fff8e1; border: 1px solid #ffc107; padding: 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; color: #5d4037; }
        .btn { padding: 12px 22px; background: #0d47a1; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 15px; }
        .btn:hover { background: #1565c0; }
    </style>
</head>
<body>
<div class="card">
    <div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
    <a href="admin.php" style="padding:10px 18px; background:#1a73e8; color:white; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">📊 Dashboard</a>
    <a href="feeding_report.php" style="padding:10px 18px; background:#e3f2fd; color:#1a73e8; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">📅 Feeding by Date</a>
</div>
    <h1>Daily feeding fees</h1>
    <p class="hint">Enter what each student paid <strong>for the selected calendar date</strong>. Saving again for the same date <strong>replaces</strong> that student&rsquo;s amount for that day (no duplicate lines). The year feeding total is adjusted by the difference. Use <a href="feeding_report.php">Feeding by date</a> to review.</p>

    <?php if ($success): ?>
        <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!$fees_ok): ?>
        <div class="warn-box">Install fee tables first: run <code>schema_fees.sql</code> in MySQL.</div>
    <?php elseif (!$table_ok): ?>
        <div class="warn-box">
            The daily feeding table is not installed yet. In phpMyAdmin, select database <code>school_jhs</code> and run <code>schema_feeding_daily.sql</code>, then reload this page.
        </div>
    <?php else: ?>

    <form method="get" class="row">
        <div>
            <label for="academic_year">Academic year</label>
            <select name="academic_year" id="academic_year" onchange="this.form.submit()">
                <?php foreach ($year_options as $yo): ?>
                    <option value="<?php echo htmlspecialchars($yo); ?>" <?php echo $yo === $selected_year ? 'selected' : ''; ?>><?php echo htmlspecialchars($yo); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="class">Class</label>
            <select name="class" id="class" onchange="this.form.submit()">
                <option value="">All classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $class_get === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="feeding_date">Date you are recording</label>
            <input type="date" name="feeding_date" id="feeding_date" value="<?php echo htmlspecialchars($feeding_date); ?>">
        </div>
        <div>
            <button type="submit" class="btn" style="margin-top:22px;">Load list</button>
        </div>
    </form>

    <?php
    $feed_params = ['academic_year' => $selected_year, 'feeding_date' => $feeding_date];
    if ($class_get !== '') {
        $feed_params['class'] = $class_get;
    }
    $feeding_form_action = 'feeding_fees.php?' . http_build_query($feed_params);
    ?>
    <form method="post" action="<?php echo htmlspecialchars($feeding_form_action); ?>">
        <input type="hidden" name="feeding_academic_year" value="<?php echo htmlspecialchars($selected_year); ?>">
        <input type="hidden" name="feeding_payment_date" value="<?php echo htmlspecialchars($feeding_date); ?>">
        <div class="wrap-table" style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Class</th>
                        <th class="num">Amount paid this date (GHS)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($students_for_table && $students_for_table->num_rows > 0) {
                        while ($st = $students_for_table->fetch_assoc()) {
                            $sid = $st['student_id'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($st['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($st['class_name']); ?></td>
                                <td class="num">
                                    <input type="number" step="0.01" min="0" name="feeding_amount[<?php echo htmlspecialchars($sid); ?>]" value="" placeholder="0" style="width:120px;text-align:right;" aria-label="Feeding amount for <?php echo htmlspecialchars($st['full_name']); ?>">
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="3">No students for this filter.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <p style="margin-top:18px;">
            <button type="submit" name="save_feeding_daily" value="1" class="btn">Save feeding  <?php echo htmlspecialchars($feeding_date); ?></button>
        </p>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
