<?php
/**
 * Feeding fee totals for one calendar date, grouped by class (daily register).
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
$payments_ok = fee_payments_table_exists($conn);

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

$report_date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $report_date)) {
    $report_date = date('Y-m-d');
}

$ay_esc = mysqli_real_escape_string($conn, $selected_year);
$date_esc = mysqli_real_escape_string($conn, $report_date);

$feeding_query_error = '';
$other_years_same_date = [];

/**
 * Merge rows so each student_id appears once (sums amounts for that date).
 *
 * @param array $into mutates
 */
function feeding_report_merge_into(array &$into, array $rows)
{
    foreach ($rows as $rw) {
        $sid = $rw['student_id'];
        if (!isset($into[$sid])) {
            $into[$sid] = [
                'student_id' => $sid,
                'full_name' => $rw['full_name'] ?? '',
                'class_name' => $rw['class_name'] ?? '',
                'amount' => 0.0,
            ];
        }
        $into[$sid]['amount'] += (float) ($rw['amount'] ?? 0);
    }
}

$rows_new_raw = [];
if ($table_ok) {
    // One figure per student per day: if duplicate rows exist (older saves), use the latest row only — not SUM(amount).
    $q = $conn->query("
        SELECT e.student_id, s.full_name, s.class_name, e.amount AS amount
        FROM feeding_fee_entries e
        INNER JOIN students s ON " . fee_sql_student_id_eq('s', 'e') . "
        INNER JOIN (
            SELECT student_id, MAX(id) AS last_id
            FROM feeding_fee_entries
            WHERE academic_year='$ay_esc' AND payment_date='$date_esc'
            GROUP BY student_id
        ) last ON last.last_id = e.id
        ORDER BY s.class_name, s.full_name
    ");
    if (!$q) {
        $feeding_query_error = $conn->error;
    }
    while ($q && $rw = $q->fetch_assoc()) {
        $rows_new_raw[] = $rw;
    }
    if ($q && empty($rows_new_raw)) {
        $oy = $conn->query("
            SELECT academic_year, SUM(amount) AS t, COUNT(*) AS n
            FROM feeding_fee_entries
            WHERE payment_date='$date_esc'
            GROUP BY academic_year
            ORDER BY academic_year
        ");
        if ($oy) {
            while ($r = $oy->fetch_assoc()) {
                $other_years_same_date[] = $r;
            }
        }
    }
}

$rows_legacy_raw = [];
if ($payments_ok) {
    $q2 = $conn->query("
        SELECT fp.student_id, s.full_name, s.class_name, fp.amount AS amount
        FROM fee_payments fp
        INNER JOIN students s ON " . fee_sql_student_id_eq('s', 'fp') . "
        INNER JOIN (
            SELECT student_id, MAX(id) AS last_id
            FROM fee_payments
            WHERE academic_year='$ay_esc' AND payment_date='$date_esc' AND fee_type='feeding'
            GROUP BY student_id
        ) last ON last.last_id = fp.id
        ORDER BY s.class_name, s.full_name
    ");
    while ($q2 && $rw = $q2->fetch_assoc()) {
        $rows_legacy_raw[] = $rw;
    }
}

$sids_in_daily_register = [];
foreach ($rows_new_raw as $rw) {
    $sids_in_daily_register[$rw['student_id']] = true;
}

$merged_by_student = [];
feeding_report_merge_into($merged_by_student, $rows_new_raw);
foreach ($rows_legacy_raw as $rw) {
    if ($table_ok && isset($sids_in_daily_register[$rw['student_id']])) {
        continue;
    }
    feeding_report_merge_into($merged_by_student, [$rw]);
}

function feeding_report_group_by_class(array $rows)
{
    $out = [];
    foreach ($rows as $r) {
        $cn = $r['class_name'] ?? '';
        if (!isset($out[$cn])) {
            $out[$cn] = ['subtotal' => 0.0, 'lines' => []];
        }
        $out[$cn]['lines'][] = $r;
        $out[$cn]['subtotal'] += (float) $r['amount'];
    }
    foreach ($out as &$block) {
        usort($block['lines'], function ($a, $b) {
            return strcmp($a['full_name'] ?? '', $b['full_name'] ?? '');
        });
    }
    unset($block);
    ksort($out);
    return $out;
}

$by_class_merged = feeding_report_group_by_class(array_values($merged_by_student));
$grand_total = 0.0;
foreach ($merged_by_student as $st) {
    $grand_total += (float) $st['amount'];
}

$report_date_long = strtotime($report_date) ? date('l, F j, Y', strtotime($report_date)) : $report_date;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feeding fees by date</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 24px; }
        .card { background: white; max-width: 920px; margin: 0 auto; padding: 24px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        h1 { font-size: 20px; margin-bottom: 8px; }
        h2 { font-size: 16px; margin: 22px 0 10px; color: #333; }
        label { font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px; }
        select, input[type="date"] { padding: 10px 12px; font-size: 15px; border-radius: 8px; border: 1px solid #ddd; }
        .row { margin-bottom: 18px; display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 8px; }
        th, td { padding: 10px 12px; border: 1px solid #e0e0e0; text-align: left; }
        th { background: #0d47a1; color: white; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .hint { color: #666; font-size: 14px; margin-bottom: 16px; line-height: 1.5; }
        a { color: #1a73e8; }
        .nav { margin-bottom: 16px; }
        .nav a { margin-right: 16px; }
        .stat { font-size: 22px; font-weight: bold; color: #0d47a1; margin: 8px 0 4px; }
        .warn-box { background: #fff8e1; border: 1px solid #ffc107; padding: 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; color: #5d4037; }
        .btn { padding: 10px 18px; background: #0d47a1; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .class-block { margin-bottom: 20px; border: 1px solid #e0e0e0; border-radius: 10px; overflow: hidden; }
        .class-head { background: #e3f2fd; padding: 10px 14px; font-weight: 600; display: flex; justify-content: space-between; }
        .daily-total-footer {
            margin-top: 28px;
            padding: 18px 20px;
            border-radius: 12px;
            background: linear-gradient(135deg, #0d47a1 0%, #1565c0 100%);
            color: #fff;
            box-shadow: 0 4px 14px rgba(13, 71, 161, 0.25);
        }
        .daily-total-footer .lbl { display: block; font-size: 14px; opacity: 0.92; margin-bottom: 6px; }
        .daily-total-footer .amt { font-size: 28px; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: 0.02em; }
        .daily-total-footer .sub { margin: 12px 0 0; font-size: 13px; opacity: 0.88; line-height: 1.45; }
    </style>
</head>
<body>
<div class="card">
   <div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
    <a href="admin.php" style="padding:10px 18px; background:#1a73e8; color:white; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">📊 Dashboard</a>
    <a href="feeding_fees.php" style="padding:10px 18px; background:#e3f2fd; color:#1a73e8; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">🍽 Record Feeding</a>
</div>

    <h1>Feeding collected by date</h1>
    <p class="hint">Each student appears <strong>once</strong> per date. The daily register (from <a href="feeding_fees.php">Daily feeding</a>) is the source of truth when it exists; older <code>fee_payments</code> feeding lines for the same person and date are not added again. The <strong>academic year</strong> must match what you chose when recording.</p>

    <?php if ($feeding_query_error !== ''): ?>
        <div class="warn-box">Could not load feeding register: <?php echo htmlspecialchars($feeding_query_error); ?></div>
    <?php endif; ?>

    <?php if (!$fees_ok): ?>
        <div class="warn-box">Install <code>schema_fees.sql</code> first.</div>
    <?php elseif (!$table_ok && !$payments_ok): ?>
        <div class="warn-box">Run <code>schema_feeding_daily.sql</code> for the daily register, or <code>schema_fees_v2.sql</code> for the older payment log.</div>
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
            <label for="date">Date</label>
            <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($report_date); ?>">
        </div>
        <div>
            <button type="submit" class="btn" style="margin-top:22px;">Show report</button>
        </div>
    </form>

    <p class="hint">Combined total feeding recorded for <strong><?php echo htmlspecialchars($report_date); ?></strong> (year <?php echo htmlspecialchars($selected_year); ?>). A larger summary also appears at the <strong>bottom</strong> of the page after the class lists.</p>
    <div class="stat">GHS <?php echo number_format($grand_total, 2); ?></div>

    <?php if ($table_ok && empty($rows_new_raw) && !empty($other_years_same_date)): ?>
        <div class="warn-box">
            There are feeding entries on <strong><?php echo htmlspecialchars($report_date); ?></strong> under a <em>different</em> academic year than the one selected above. Pick the matching year in the dropdown:
            <?php foreach ($other_years_same_date as $oyr): ?>
                <?php
                $oy_label = $oyr['academic_year'];
                $oy_href = 'feeding_report.php?' . http_build_query(['academic_year' => $oy_label, 'date' => $report_date]);
                ?>
                <br><a href="<?php echo htmlspecialchars($oy_href); ?>"><?php echo htmlspecialchars($oy_label); ?></a>
                (<?php echo (int) $oyr['n']; ?> line(s), GHS <?php echo number_format((float) $oyr['t'], 2); ?>)
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($by_class_merged)): ?>
        <h2>Students who paid feeding</h2>
        <p class="hint">One row per student for <?php echo htmlspecialchars($report_date); ?> (academic year <?php echo htmlspecialchars($selected_year); ?>).</p>
        <?php foreach ($by_class_merged as $class_name => $block): ?>
            <div class="class-block">
                <div class="class-head">
                    <span><?php echo htmlspecialchars($class_name !== '' ? $class_name : '(No class)'); ?></span>
                    <span>GHS <?php echo number_format($block['subtotal'], 2); ?></span>
                </div>
                <table>
                    <thead>
                        <tr><th>Student</th><th class="num">Amount (GHS)</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($block['lines'] as $ln): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ln['full_name']); ?></td>
                                <td class="num"><?php echo number_format((float) $ln['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php elseif ($table_ok || $payments_ok): ?>
        <p>No feeding recorded for this date and academic year.</p>
    <?php endif; ?>

    <?php if ($grand_total >= 0.005): ?>
        <div class="daily-total-footer">
            <span class="lbl">Total feeding fee collected on <?php echo htmlspecialchars($report_date_long); ?></span>
            <span class="amt">GHS <?php echo number_format($grand_total, 2); ?></span>
            <p class="sub">Total is the sum of each student&rsquo;s amount for this date (legacy feeding payments are included only when there is no daily-register row for that student).</p>
        </div>
    <?php endif; ?>

    <?php if ($grand_total < 0.005): ?>
        <p style="margin-top:16px;color:#666;">Nothing recorded for this date. Record payments on <a href="feeding_fees.php">Daily feeding</a>.</p>
        <p class="hint" style="margin-top:10px;">If you only updated the <strong>feeding</strong> column on <a href="fees.php">Student fees</a>, that updates the year balance for reports but does <strong>not</strong> store who paid on which day. Use <strong>Daily feeding</strong> for a dated list of students.</p>
    <?php endif; ?>

    <?php endif; ?>
</div>
</body>
</html>
