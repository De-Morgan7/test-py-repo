<?php
session_start();
include 'db.php';
require_once 'fee_helpers.php';

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$student_id    = mysqli_real_escape_string($conn, urldecode($_GET['student_id'] ?? ''));
$selected_term = mysqli_real_escape_string($conn, urldecode($_GET['term'] ?? ''));
$selected_class= mysqli_real_escape_string($conn, urldecode($_GET['class'] ?? ''));
$academic_year = get_active_year($conn);

$subjects = ['English Language', 'Mathematics', 'Integrated Science', 'Religious and Moral Education', 'Social Studies', 'French', 'Career Technology', 'Creative Art and Design', 'Ashanti Twi', 'Computing'];

$student = $conn->query("SELECT * FROM students WHERE student_id='$student_id'")->fetch_assoc();
if (!$student) { die("Student not found."); }

$marks = [];
$grand_total = 0;
foreach($subjects as $sub) {
    $m = $conn->query("SELECT * FROM marks WHERE student_id='$student_id' AND subject='$sub' AND term='$selected_term' AND academic_year='$academic_year'")->fetch_assoc();
    $marks[$sub] = $m;
    $grand_total += floatval($m['total'] ?? 0);
}

$all_students = $conn->query("SELECT student_id FROM students WHERE class_name='$selected_class'");
$totals = [];
while($s = $all_students->fetch_assoc()) {
    $sid = $s['student_id'];
    $t = $conn->query("SELECT SUM(total) as gt FROM marks WHERE student_id='$sid' AND class_name='$selected_class' AND term='$selected_term' AND academic_year='$academic_year'")->fetch_assoc();
    $totals[$sid] = floatval($t['gt'] ?? 0);
}
arsort($totals);
$position = 1;
foreach($totals as $sid => $total) {
    if($sid == $student_id) break;
    $position++;
}
$total_students = count($totals);

function ordinal($n) {
    $s = ['th','st','nd','rd'];
    $v = $n % 100;
    return $n . ($s[($v-20)%10] ?? $s[min($v,3)]);
}

function getGradeRemark($grade) {
    $remarks = [1=>'Excellent', 2=>'Very Good', 3=>'Good', 4=>'Credit', 5=>'Credit', 6=>'Pass', 7=>'Pass', 8=>'Fail', 9=>'Fail'];
    return $remarks[$grade] ?? '';
}
// Get remark for this term
$remark_row = $conn->query("SELECT remark FROM student_remarks WHERE student_id='$student_id' AND academic_year='$academic_year' AND term='$selected_term'")->fetch_assoc();
$student_remark = $remark_row['remark'] ?? '';

// Get promotion status (Third Term only)
$promotion_row = $conn->query("SELECT status, next_class FROM promotions WHERE student_id='$student_id' AND academic_year='$academic_year'")->fetch_assoc();
$promo_status = $promotion_row['status'] ?? '';
$promo_next   = $promotion_row['next_class'] ?? '';
// Get attendance for this specific term and academic year only
$term_esc_rc = mysqli_real_escape_string($conn, $selected_term);
$ay_esc_rc   = mysqli_real_escape_string($conn, $academic_year);
$class_esc_rc = mysqli_real_escape_string($conn, $selected_class);

// Total school days in this term for this class
$term_days_res = $conn->query("SELECT COUNT(DISTINCT attendance_date) as total_days FROM attendance WHERE class_name='$class_esc_rc' AND term='$term_esc_rc' AND academic_year='$ay_esc_rc'");
$total_days    = $term_days_res->fetch_assoc()['total_days'] ?? 0;

// Days this student was present in this term
$days_present_res = $conn->query("SELECT COUNT(*) as present FROM attendance WHERE student_id='$student_id' AND term='$term_esc_rc' AND academic_year='$ay_esc_rc' AND status='Present'");
$days_present     = $days_present_res->fetch_assoc()['present'] ?? 0;

$fees_enabled = fees_tables_exist($conn);
$school_fee_expected = 500;
$exam_fee_expected = 0;
$feeding_fee_expected = 0;
$school_fee_paid = 0;
$exam_fee_paid = 0;
$feeding_fee_paid = 0;
$school_fee_owed = 0;
$exam_fee_owed = 0;
$feeding_fee_owed = 0;
if ($fees_enabled) {
    $fee_set = fee_settings_row($conn, $academic_year);
    $school_fee_expected = (float) $fee_set['school_fee_expected'];
    $exam_fee_expected = (float) $fee_set['exam_fee_expected'];
    $feeding_fee_expected = (float) $fee_set['feeding_fee_expected'];
    $fee_paid = student_fees_row($conn, $student_id, $academic_year);
    $school_fee_paid = (float) $fee_paid['school_fee_paid'];
    $exam_fee_paid = (float) $fee_paid['exam_fee_paid'];
    $feeding_fee_paid = (float) $fee_paid['feeding_fee_paid'];
    $school_fee_owed = max(0, $school_fee_expected - $school_fee_paid);
    $exam_fee_owed = $exam_fee_expected > 0 ? max(0, $exam_fee_expected - $exam_fee_paid) : 0;
    $feeding_fee_owed = $feeding_fee_expected > 0 ? max(0, $feeding_fee_expected - $feeding_fee_paid) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Report Card - <?php echo htmlspecialchars($student['full_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { font-family: 'Times New Roman', Times, serif; background: #f0f2f5; padding: 16px; padding-bottom: 32px; }
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
        .card {
            background: white;
            max-width: 750px;
            margin: 0 auto;
            padding: 24px 18px;
            border: 2px solid #333;
        }
        .school-header { text-align: center; margin-bottom: 12px; border-bottom: 3px double #333; padding-bottom: 8px; }
        .school-header h1 { font-size: clamp(16px, 4.5vw, 20px); text-transform: uppercase; letter-spacing: 1px; line-height: 1.2; }
        .school-header h2 { font-size: clamp(13px, 3.5vw, 15px); color: #555; margin-top: 4px; }
        .school-header p { font-size: 11px; color: #777; margin-top: 3px; line-height: 1.3; }
        .report-title { text-align: center; font-size: 13px; font-weight: bold; text-decoration: underline; margin: 10px 0; text-transform: uppercase; letter-spacing: 1px; }
        .student-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
        }
        .info-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 6px; font-size: 12px; }
        .info-label { font-weight: bold; min-width: 95px; flex: 0 0 auto; }
        .info-value { border-bottom: 1px solid #999; flex: 1 1 140px; min-width: 0; word-break: break-word; }

        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; min-width: 520px; }
        th { background: #1a73e8; color: white; padding: 6px 4px; text-align: center; border: 1px solid #999; font-size: 10px; line-height: 1.2; }
        td { padding: 5px 4px; border: 1px solid #ddd; text-align: center; vertical-align: middle; }
        td.subject { text-align: left; font-weight: 500; }
        tr:nth-child(even) { background: #f9f9f9; }

        .summary-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 12px; }
        .summary-box { border: 1px solid #ddd; border-radius: 6px; padding: 10px 8px; text-align: center; }
        .summary-box .label { font-size: 10px; color: #777; text-transform: uppercase; }
        .summary-box .value { font-size: clamp(16px, 4.5vw, 20px); font-weight: bold; color: #1a73e8; margin-top: 3px; }
        .summary-box .sub { font-size: 11px; color: #999; margin-top: 3px; }
        .attendance-bar { background: #f0f2f5; border-radius: 20px; height: 8px; margin-top: 6px; overflow: hidden; }
        .attendance-fill { background: #27ae60; height: 100%; border-radius: 20px; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-top: 16px; }
        .sign-box { text-align: center; }
        .sign-line { border-top: 1px solid #333; margin-top: 28px; padding-top: 4px; font-size: 10px; line-height: 1.2; }

        .grade-key {
            font-size: 10px;
            color: #666;
            border: 1px solid #eee;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 10px;
            line-height: 1.3;
            word-break: break-word;
        }
        .grade-key b { color: #333; }

        .fee-alert {
            background: #ffebee;
            border: 2px solid #c62828;
            color: #b71c1c;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 12px;
            line-height: 1.3;
        }
        .fee-alert strong { display: block; margin-bottom: 4px; font-size: 13px; }
        .fee-panel {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 10px;
            font-size: 12px;
            line-height: 1.4;
            background: #fafafa;
        }
        .fee-panel h3 { font-size: 13px; color: #333; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .fee-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 8px 16px; }
        .fee-row { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; border-bottom: 1px solid #eee; padding: 4px 0; }
        .fee-row:last-child { border-bottom: none; }
        .fee-owe { color: #c62828; font-weight: bold; }

        .remarks-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 12px; }
        .remarks-table td { border: 1px solid #ddd; padding: 8px; }
        .remarks-table b { font-size: 13px; }

        @media (max-width: 640px) {
            body { padding: 12px; }
            .card { padding: 18px 12px; }
            .student-info { grid-template-columns: 1fr; }
            .summary-grid { grid-template-columns: 1fr; }
            .signatures { grid-template-columns: 1fr; gap: 8px; }
            .sign-line { margin-top: 24px; }
            .fee-grid { grid-template-columns: 1fr; }
        }

        @media print {
    @page {
        size: A4;
        margin: 6mm;
    }

    body {
        background: white;
        padding: 0;
        margin: 0;
    }

    .toolbar {
        display: none;
    }

    .card {
        border: 2px solid #333;
        box-shadow: none;
        max-width: none;
        padding: 12px 14px;
        margin: 0;
    }

    .school-header {
        margin-bottom: 8px;
        padding-bottom: 6px;
    }

    .school-header h1 {
        font-size: 16px;
        line-height: 1.1;
    }

    .school-header h2 {
        font-size: 13px;
        margin-top: 2px;
    }

    .school-header p {
        font-size: 10px;
        margin-top: 2px;
    }

    .report-title {
        margin: 8px 0;
        font-size: 12px;
    }

    .student-info {
        margin-bottom: 8px;
        padding: 8px;
        gap: 6px;
        font-size: 11px;
    }

    .info-row {
        font-size: 11px;
    }

    .info-label {
        min-width: 90px;
    }

    .table-wrap {
        overflow: visible;
        margin-bottom: 8px;
    }

    table {
        min-width: 0;
        font-size: 10px;
    }

    th {
        padding: 4px 3px;
        font-size: 9px;
    }

    td {
        padding: 3px 3px;
    }

    .grade-key {
        font-size: 9px;
        padding: 6px;
        margin-bottom: 8px;
        line-height: 1.2;
    }

    .fee-alert {
        padding: 6px 8px;
        margin-bottom: 8px;
        font-size: 10px;
    }

    .fee-panel {
        padding: 8px 10px;
        margin-bottom: 8px;
        font-size: 10px;
    }

    .fee-row {
        padding: 2px 0;
    }

    .remarks-table {
        margin-bottom: 8px;
        font-size: 11px;
    }

    .remarks-table td {
        padding: 6px;
    }

    .remarks-table b {
        font-size: 12px;
    }

    .summary-grid {
        gap: 6px;
        margin-bottom: 10px;
    }

    .summary-box {
        padding: 7px 5px;
    }

    .summary-box .label {
        font-size: 9px;
    }

    .summary-box .value {
        font-size: 16px;
        margin-top: 1px;
    }

    .summary-box .sub {
        font-size: 9px;
        margin-top: 1px;
    }

    .attendance-bar {
        height: 6px;
        margin-top: 3px;
    }

    .signatures {
        gap: 8px;
        margin-top: 10px;
    }

    .sign-line {
        margin-top: 18px;
        padding-top: 3px;
        font-size: 9px;
    }

    .fee-alert,
    .fee-panel {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
</div>

<div class="card">
  <div class="school-header">
    <img src="logo.png" alt="School Logo" style="height:100px; margin-bottom:4px;">
    <h1>Beverly Hills International School</h1>
    <h2>Terminal Report Card</h2>
    <p>Academic Year: <?php echo htmlspecialchars($academic_year); ?> &nbsp;|&nbsp; <?php echo htmlspecialchars($selected_term); ?></p>
  </div>

  <div class="report-title">Student Academic Report</div>

  <div class="student-info">
    <div class="info-row">
      <span class="info-label">Student Name:</span>
      <span class="info-value"><?php echo htmlspecialchars(strtoupper($student['full_name'])); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Student ID:</span>
      <span class="info-value"><?php echo htmlspecialchars($student['student_id']); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Class:</span>
      <span class="info-value"><?php echo htmlspecialchars($selected_class); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Gender:</span>
      <span class="info-value"><?php echo htmlspecialchars($student['gender'] ?? ''); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Term:</span>
      <span class="info-value"><?php echo htmlspecialchars($selected_term); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Academic Year:</span>
      <span class="info-value"><?php echo htmlspecialchars($academic_year); ?></span>
    </div>
  </div>

  <?php if ($fees_enabled && $school_fee_owed > 0): ?>
  <div class="fee-alert">
    <strong>Outstanding school fees</strong>
    This student owes <strong>GHS <?php echo number_format($school_fee_owed, 2); ?></strong> in school fees for <?php echo htmlspecialchars($academic_year); ?> (expected GHS <?php echo number_format($school_fee_expected, 2); ?>; paid GHS <?php echo number_format($school_fee_paid, 2); ?>).
  </div>
  <?php endif; ?>

  <?php if ($fees_enabled && $exam_fee_expected > 0 && $exam_fee_owed > 0): ?>
  <div class="fee-alert">
    <strong>Outstanding exam fees</strong>
    This student owes <strong>GHS <?php echo number_format($exam_fee_owed, 2); ?></strong> in exam fees for <?php echo htmlspecialchars($academic_year); ?> (expected GHS <?php echo number_format($exam_fee_expected, 2); ?>; paid GHS <?php echo number_format($exam_fee_paid, 2); ?>).
  </div>
  <?php endif; ?>

  <?php if ($fees_enabled): ?>
  <div class="fee-panel">
    <h3>Fees (<?php echo htmlspecialchars($academic_year); ?>)</h3>
    <div class="fee-grid">
      <div>
        <div class="fee-row"><span>School fee (expected)</span><span>GHS <?php echo number_format($school_fee_expected, 2); ?></span></div>
        <div class="fee-row"><span>School fee (paid)</span><span>GHS <?php echo number_format($school_fee_paid, 2); ?></span></div>
        <div class="fee-row"><span>School fee (balance)</span><span class="<?php echo $school_fee_owed > 0 ? 'fee-owe' : ''; ?>">GHS <?php echo number_format($school_fee_owed, 2); ?></span></div>
      </div>
      <div>
        <?php if ($exam_fee_expected > 0): ?>
        <div class="fee-row"><span>Exam fee (expected)</span><span>GHS <?php echo number_format($exam_fee_expected, 2); ?></span></div>
        <div class="fee-row"><span>Exam fee (paid)</span><span>GHS <?php echo number_format($exam_fee_paid, 2); ?></span></div>
        <div class="fee-row"><span>Exam fee (balance)</span><span class="<?php echo $exam_fee_owed > 0 ? 'fee-owe' : ''; ?>">GHS <?php echo number_format($exam_fee_owed, 2); ?></span></div>
        <?php else: ?>
        <div class="fee-row"><span>Exam fee</span><span>Not set / N/A</span></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th style="text-align:left; width:28%;">Subject</th>
        <th>Class Score<br><small>(30)</small></th>
        <th>Exam Score<br><small>(70)</small></th>
        <th>Total<br><small>(100)</small></th>
        <th>Grade</th>
        <th>Remark</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($subjects as $sub):
        $m = $marks[$sub];
        $total = $m['total'] ?? '--';
        $grade = $m['grade'] ?? '--';
        $class_score = $m['ca1'] ?? '--';
        $exam_score  = $m['exam'] ?? '--';
        $remark = $grade !== '--' ? getGradeRemark((int)$grade) : '--';
      ?>
      <tr>
        <td class="subject"><?php echo htmlspecialchars($sub); ?></td>
        <td><?php echo htmlspecialchars((string)$class_score); ?></td>
        <td><?php echo htmlspecialchars((string)$exam_score); ?></td>
        <td><b><?php echo htmlspecialchars((string)$total); ?></b></td>
        <td><b><?php echo htmlspecialchars((string)$grade); ?></b></td>
        <td><?php echo htmlspecialchars($remark); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <div class="grade-key">
    <b>Grade Key:</b> 1=Excellent (80-100) | 2=Very Good (70-79) | 3=Good (60-69) | 4=Credit (55-59) | 5=Credit (50-54) | 6=Pass (45-49) | 7=Pass (40-44) | 8=Fail (35-39) | 9=Fail (0-34)
  </div>

  <table class="remarks-table">
    <tr>
      <td style="width:50%;">
        <b>Teacher's Remark:</b><br>
        <span style="font-style:italic; color:#444; font-size:11px;">
          <?php echo $student_remark !== '' ? htmlspecialchars($student_remark) : 'No remark added.'; ?>
        </span>
      </td>
      <td style="width:50%;">
        <b>Promotion Status:</b><br>
        <?php if($selected_term === 'Third Term' && $promo_status !== ''): ?>
          <?php if($promo_status === 'Promoted'): ?>
            <span style="color:#2e7d32; font-weight:bold; font-size:11px;">✅ Promoted to <?php echo htmlspecialchars($promo_next); ?></span>
          <?php elseif($promo_status === 'Repeated'): ?>
            <span style="color:#e67e22; font-weight:bold; font-size:11px;">🔄 Repeated</span>
          <?php elseif($promo_status === 'Graduated'): ?>
            <span style="color:#1565c0; font-weight:bold; font-size:11px;">🎓 Graduated</span>
          <?php endif; ?>
        <?php elseif($selected_term === 'Third Term'): ?>
          <span style="color:#999; font-style:italic; font-size:11px;">Not yet marked</span>
        <?php else: ?>
          <span style="color:#999; font-style:italic; font-size:11px;">Available in Third Term only</span>
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <div class="summary-grid">
    <div class="summary-box">
      <div class="label">Grand Total</div>
      <div class="value"><?php echo htmlspecialchars((string)$grand_total); ?></div>
      <div class="sub">out of <?php echo count($subjects) * 100; ?></div>
    </div>
    <div class="summary-box">
      <div class="label">Class Position</div>
      <div class="value"><?php echo htmlspecialchars(ordinal($position)); ?></div>
      <div class="sub">out of <?php echo (int)$total_students; ?></div>
    </div>
    <div class="summary-box">
      <div class="label">Attendance</div>
      <div class="value"><?php echo (int)$days_present; ?>/<?php echo (int)$total_days; ?></div>
      <div class="sub">days present</div>
      <?php if($total_days > 0): ?>
      <div class="attendance-bar">
        <div class="attendance-fill" style="width:<?php echo (int)round(($days_present/$total_days)*100); ?>%"></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="signatures">
    <div class="sign-box">
      <div class="sign-line">Class Teacher</div>
    </div>
    <div class="sign-box">
      <div class="sign-line">Headmaster/Headmistress</div>
    </div>
    <div class="sign-box">
      <div class="sign-line">Parent/Guardian</div>
    </div>
  </div>
</div>

</body>
</html>