<?php
/**
 * Printable fee receipt(s) for parents — use browser Print → Save as PDF to share.
 */
session_start();
include 'db.php';
require_once 'fee_helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

if (!fee_payments_table_exists($conn)) {
    die('Payment log not installed. Run schema_fees_v2.sql.');
}

$ids_raw = isset($_GET['ids']) ? trim($_GET['ids']) : '';
$id_single = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$ids = [];
if ($ids_raw !== '') {
    foreach (explode(',', $ids_raw) as $p) {
        $i = (int) trim($p);
        if ($i > 0) {
            $ids[] = $i;
        }
    }
    $ids = array_unique($ids);
} elseif ($id_single > 0) {
    $ids = [$id_single];
}

if (empty($ids)) {
    die('Missing receipt id. Open Fee reports → click a date → Receipt next to a payment.');
}

$lines = [];
foreach ($ids as $pid) {
    $pid = (int) $pid;
    $r = $conn->query("
        SELECT fp.*, s.full_name, s.class_name, s.guardian_name
        FROM fee_payments fp
        INNER JOIN students s ON " . fee_sql_student_id_eq('s', 'fp') . "
        WHERE fp.id=$pid
        LIMIT 1
    ");
    if ($r && $row = $r->fetch_assoc()) {
        $lines[] = $row;
    }
}

if (empty($lines)) {
    die('Receipt not found.');
}

$groups = [];
foreach ($lines as $ln) {
    $sid = $ln['student_id'];
    if (!isset($groups[$sid])) {
        $groups[$sid] = [];
    }
    $groups[$sid][] = $ln;
}

$payment_history_href = 'fee_student_payments.php?' . http_build_query([
    'student_id'    => $lines[0]['student_id'],
    'academic_year' => $lines[0]['academic_year'],
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Fee receipt #<?php echo (int) $lines[0]['id']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #f0f2f5;
            padding: 16px;
            padding-bottom: 32px;
            color: #222;
        }
        .toolbar { max-width: 750px; margin: 0 auto 16px; }
        .print-btn {
            background: #1a73e8;
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            max-width: 100%;
            min-height: 48px;
            font-weight: 600;
        }
        .print-btn:hover { background: #1557b0; }
        .back {
            display: block;
            margin-top: 10px;
            text-align: center;
            color: #1a73e8;
            font-size: 14px;
        }
        .card {
            background: white;
            max-width: 750px;
            margin: 0 auto;
            padding: 24px 18px;
            border: 2px solid #333;
        }
        .card.page-break { margin-top: 28px; page-break-before: always; }
        .school-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px double #333;
            padding-bottom: 15px;
        }
        .school-header h1 {
            font-size: clamp(16px, 4.5vw, 22px);
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1.25;
        }
        .school-header h2 {
            font-size: clamp(14px, 3.8vw, 16px);
            color: #555;
            margin-top: 8px;
        }
        .school-header p {
            font-size: 12px;
            color: #777;
            margin-top: 6px;
            line-height: 1.4;
        }
        .report-title {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            text-decoration: underline;
            margin: 15px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .student-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            padding: 14px;
            border-radius: 4px;
        }
        .info-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 8px; font-size: 13px; }
        .info-label { font-weight: bold; min-width: 100px; flex: 0 0 auto; }
        .info-value { border-bottom: 1px solid #999; flex: 1 1 160px; min-width: 0; word-break: break-word; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 20px; }
        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            min-width: 620px;
        }
        table.items th, table.items td {
            border: 1px solid #999;
            padding: 8px 6px;
            vertical-align: middle;
        }
        table.items th {
            background: #1a73e8;
            color: white;
            text-align: center;
            font-size: 11px;
            line-height: 1.25;
            font-weight: 600;
        }
        table.items td { text-align: center; }
        table.items td.num { text-align: right; font-variant-numeric: tabular-nums; }
        table.items tbody tr:nth-child(even) { background: #f9f9f9; }
        .balance-ok { font-weight: bold; color: #1b5e20; }
        .bal-num { font-variant-numeric: tabular-nums; font-weight: 600; }
        .bal-muted { color: #888; }
        .bal-cell { vertical-align: middle; line-height: 1.35; }
        .receipt-note {
            margin-top: 16px;
            font-size: 13px;
            color: #444;
            line-height: 1.5;
            padding-top: 14px;
            border-top: 1px solid #ccc;
        }

        /* ONLY CASHIER SIGNATURE */
        .signature-section {
            margin-top: 28px;
        }
        .sign-box {
            display: inline-block;
            text-align: center;
            min-width: 200px;
        }
        .sign-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 6px;
            font-size: 12px;
            line-height: 1.3;
        }

        .footer-note {
            margin-top: 18px;
            font-size: 11px;
            color: #666;
            text-align: center;
            line-height: 1.45;
        }

        @media (max-width: 640px) {
            body { padding: 12px; }
            .card { padding: 18px 12px; }
            .student-info { grid-template-columns: 1fr; }
            .sign-box { min-width: 160px; }
        }

        @media print {
            body { background: white; padding: 0; }
            .toolbar { display: none; }
            .card { border: 2px solid #333; box-shadow: none; max-width: none; padding: 30px; }
            .card.page-break { margin-top: 0; }
            .table-wrap { overflow: visible; }
            table.items { min-width: 0; font-size: 13px; }
            table.items th { font-size: inherit; }
            .student-info { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <a href="<?php echo htmlspecialchars($payment_history_href); ?>" class="back">← Payment history</a>
</div>

<?php
$gi = 0;
foreach ($groups as $_sid => $g_lines):
    $gi++;
    $first      = $g_lines[0];
    $card_class = 'card' . ($gi > 1 ? ' page-break' : '');

    $unique_dates = array_unique(array_map(function ($gl) {
        return $gl['payment_date'];
    }, $g_lines));
    sort($unique_dates);

    $dates_formatted = [];
    foreach ($unique_dates as $ud) {
        $ts = strtotime($ud);
        $dates_formatted[] = $ts ? date('F j, Y', $ts) : $ud;
    }
    $dates_line = implode(' · ', $dates_formatted);
?>
<div class="<?php echo htmlspecialchars($card_class); ?>">

    <div class="school-header">
        <h1>🏫 Beverly Hills International School</h1>
        <h2>Official Fee Receipt</h2>
        <p>Academic year: <?php echo htmlspecialchars($first['academic_year']); ?> &nbsp;|&nbsp; Term: <?php echo htmlspecialchars($first['term']); ?></p>
    </div>

    <div class="report-title">Payment Record</div>

    <div class="student-info">
        <div class="info-row">
            <span class="info-label">Student:</span>
            <span class="info-value"><?php echo htmlspecialchars($first['full_name']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Student ID:</span>
            <span class="info-value"><?php echo htmlspecialchars($first['student_id']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Class:</span>
            <span class="info-value"><?php echo htmlspecialchars($first['class_name']); ?></span>
        </div>
        <?php if (!empty($first['guardian_name'])): ?>
        <div class="info-row">
            <span class="info-label">Parent / Guardian:</span>
            <span class="info-value"><?php echo htmlspecialchars($first['guardian_name']); ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row" style="grid-column: 1 / -1;">
            <span class="info-label">Payment date<?php echo count($unique_dates) > 1 ? 's' : ''; ?>:</span>
            <span class="info-value"><?php echo htmlspecialchars($dates_line); ?></span>
        </div>
    </div>

    <div class="table-wrap">
        <table class="items">
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <th>Date Paid</th>
                    <th>Type</th>
                    <th class="num">Amount Due (GHS)</th>
                    <th class="num">Total Paid (GHS)</th>
                    <th class="num">Balance (GHS)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($g_lines as $ln):
                    $pd_fmt   = strtotime($ln['payment_date']) ? date('F j, Y', strtotime($ln['payment_date'])) : $ln['payment_date'];
$fee_type = $ln['fee_type'];
$ay_r     = mysqli_real_escape_string($conn, $ln['academic_year']);
$sid_r    = mysqli_real_escape_string($conn, $ln['student_id']);
$ft_r     = mysqli_real_escape_string($conn, $fee_type);
$c        = defined('SCHOOL_FEE_STRING_COLLATION') ? SCHOOL_FEE_STRING_COLLATION : 'utf8mb4_general_ci';

// Get expected amount from settings for this fee type
$settings_row = fee_settings_row($conn, $ln['academic_year']);
if ($fee_type === 'school') {
    $exp = (float)($settings_row['school_fee_expected'] ?? 500);
} elseif ($fee_type === 'exam') {
    $exp = (float)($settings_row['exam_fee_expected'] ?? 0);
} else {
    $exp = 0;
}

// Calculate total paid for THIS fee type only
$paid_q = $conn->query("
    SELECT COALESCE(SUM(amount), 0) AS total_paid
    FROM fee_payments
    WHERE student_id COLLATE {$c} = '$sid_r' COLLATE {$c}
      AND academic_year = '$ay_r'
      AND fee_type = '$ft_r'
");
$total_paid = $paid_q ? (float)$paid_q->fetch_assoc()['total_paid'] : 0;

$due_cell  = $exp >= 0.01 ? number_format($exp, 2) : '—';
$paid_cell = number_format($total_paid, 2);
$rem       = $exp >= 0.01 ? $exp - $total_paid : null;

if ($rem === null) {
    $bal_html = '<span class="bal-muted">—</span>';
} elseif ($rem <= 0) {
    $bal_html = '<span class="bal-num">0.00</span><br><span class="balance-ok">Fully paid</span>';
} else {
    $bal_html = '<span class="bal-num">' . number_format($rem, 2) . '</span>';
}
                ?>
                <tr>
                    <td><?php echo (int) $ln['id']; ?></td>
                    <td><?php echo htmlspecialchars($pd_fmt); ?></td>
                    <td><?php echo htmlspecialchars(fee_payment_type_label($ln['fee_type'])); ?></td>
                    <td class="num"><?php echo htmlspecialchars($due_cell); ?></td>
                    <td class="num"><?php echo htmlspecialchars($paid_cell); ?></td>
                    <td class="num bal-cell"><?php echo $bal_html; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    

    <!-- CASHIER SIGNATURE ONLY -->
    <div class="signature-section">
        <div class="sign-box">
            <div class="sign-line">Cashier / Accounts</div>
        </div>
    </div>

    <p class="footer-note">Use Print → &ldquo;Save as PDF&rdquo; to keep a copy. Thank you.</p>

</div>
<?php endforeach; ?>

</body>
</html>