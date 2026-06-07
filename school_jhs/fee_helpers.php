<?php

/**
 * Fee tracking helpers (fee_settings, student_fees, fee_payments).
 * Run schema_fees.sql (and schema_fees_v2.sql for upgrades) in MySQL.
 */

/** Same as fee tables in schema_fees.sql; avoids collation errors vs MySQL 8 default (utf8mb4_0900_ai_ci). */
if (!defined('SCHOOL_FEE_STRING_COLLATION')) {
    define('SCHOOL_FEE_STRING_COLLATION', 'utf8mb4_unicode_ci');
}

/**
 * SQL fragment: equality on student_id across tables that may use different collations.
 *
 * @param string $aliasA First table alias (e.g. "sf")
 * @param string $aliasB Second table alias (e.g. "s")
 */
function fee_sql_student_id_eq($aliasA, $aliasB)
{
    $c = SCHOOL_FEE_STRING_COLLATION;
    return "{$aliasA}.student_id COLLATE {$c} = {$aliasB}.student_id COLLATE {$c}";
}

function fees_tables_exist($conn)
{
    $r = @$conn->query("SHOW TABLES LIKE 'student_fees'");
    return $r && $r->num_rows > 0;
}

function fee_payments_table_exists($conn)
{
    $r = @$conn->query("SHOW TABLES LIKE 'fee_payments'");
    return $r && $r->num_rows > 0;
}

/** True if feeding_fee_entries exists (schema_feeding_daily.sql). */
function feeding_fee_entries_table_exists($conn)
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $r = @$conn->query("SHOW TABLES LIKE 'feeding_fee_entries'");
    $cache = $r && $r->num_rows > 0;
    return $cache;
}

/** True if student_fees has exam_fee_paid (schema_fees_v2 applied). */
function student_fees_has_exam_column($conn)
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $r = @$conn->query("SHOW COLUMNS FROM student_fees LIKE 'exam_fee_paid'");
    $cache = $r && $r->num_rows > 0;
    return $cache;
}

function fee_payment_type_label($type)
{
    $map = ['school' => 'School fee', 'feeding' => 'Feeding fee', 'exam' => 'Exam fee'];
    return isset($map[$type]) ? $map[$type] : $type;
}

/**
 * Expected annual amount for one fee type (from fee_settings).
 *
 * @param array $settings Row from fee_settings_row()
 */
function fee_expected_for_type(array $settings, $fee_type)
{
    $fee_type = strtolower(trim((string) $fee_type));
    if ($fee_type === 'school') {
        return (float) $settings['school_fee_expected'];
    }
    if ($fee_type === 'exam') {
        return (float) $settings['exam_fee_expected'];
    }
    if ($fee_type === 'feeding') {
        return (float) $settings['feeding_fee_expected'];
    }
    return 0.0;
}

/**
 * Part vs full payment for one fee_payments line (cumulative log order by date, then id).
 *
 * @return array{label: string, remaining: ?float, expected: float, after: float, detail: string}
 */
function fee_receipt_line_analysis($conn, $student_id, $academic_year, $fee_type, $payment_date, $payment_id, $line_amount)
{
    $settings = fee_settings_row($conn, $academic_year);
    $expected = fee_expected_for_type($settings, $fee_type);
    $sid_esc = mysqli_real_escape_string($conn, $student_id);
    $ay_esc = mysqli_real_escape_string($conn, $academic_year);
    $ft_esc = mysqli_real_escape_string($conn, $fee_type);
    $pd_esc = mysqli_real_escape_string($conn, $payment_date);
    $pid = (int) $payment_id;
    $line_amount = (float) $line_amount;

    $q = $conn->query("SELECT COALESCE(SUM(amount), 0) AS s FROM fee_payments
        WHERE student_id='$sid_esc' AND academic_year='$ay_esc' AND fee_type='$ft_esc'
        AND (payment_date < '$pd_esc' OR (payment_date = '$pd_esc' AND id < $pid))");
    $before = 0.0;
    if ($q && ($r = $q->fetch_assoc())) {
        $before = (float) $r['s'];
    }
    $after = $before + $line_amount;

    if ($expected < 0.01) {
        return [
            'label' => '—',
            'remaining' => null,
            'expected' => $expected,
            'after' => $after,
            'detail' => 'No yearly rate is set for this fee type on the receipt.',
        ];
    }

    $remaining = max(0.0, $expected - $after);
    if ($remaining < 0.01) {
        return [
            'label' => 'Full payment',
            'remaining' => 0.0,
            'expected' => $expected,
            'after' => $after,
            'detail' => 'Year fee cleared after this payment.',
        ];
    }

    return [
        'label' => 'Part payment',
        'remaining' => $remaining,
        'expected' => $expected,
        'after' => $after,
        'detail' => 'Balance still owed for this fee type for the year.',
    ];
}

/** Same pattern as report_card / view_marks current academic year label */
function fee_default_academic_year()
{
    $y = (int) date('Y');
    return $y . '/' . ($y + 1);
}

/**
 * Expected amounts for the year (defaults if no row in fee_settings).
 *
 * @return array{school_fee_expected: float, feeding_fee_expected: float, exam_fee_expected: float}
 */
function fee_settings_row($conn, $academic_year)
{
    $ay = mysqli_real_escape_string($conn, trim((string) $academic_year));
    $row = $conn->query("SELECT * FROM fee_settings WHERE academic_year='$ay' LIMIT 1")->fetch_assoc();
    if (!$row) {
        return [
            'school_fee_expected' => 500.0,
            'feeding_fee_expected' => 0.0,
            'exam_fee_expected' => 0.0,
        ];
    }
    return [
        'school_fee_expected' => (float) $row['school_fee_expected'],
        'feeding_fee_expected' => (float) $row['feeding_fee_expected'],
        'exam_fee_expected' => isset($row['exam_fee_expected']) ? (float) $row['exam_fee_expected'] : 0.0,
    ];
}

/**
 * @return array{school_fee_paid: float, feeding_fee_paid: float, exam_fee_paid: float}
 */
function student_fees_row($conn, $student_id, $academic_year)
{
    $sid = mysqli_real_escape_string($conn, trim((string) $student_id));
    $ay = mysqli_real_escape_string($conn, trim((string) $academic_year));
    $c = SCHOOL_FEE_STRING_COLLATION;
    $row = $conn->query("SELECT * FROM student_fees WHERE student_id COLLATE {$c} = '$sid' COLLATE {$c} AND academic_year='$ay' LIMIT 1")->fetch_assoc();
    if (!$row) {
        return ['school_fee_paid' => 0.0, 'feeding_fee_paid' => 0.0, 'exam_fee_paid' => 0.0];
    }
    return [
        'school_fee_paid' => (float) $row['school_fee_paid'],
        'feeding_fee_paid' => (float) $row['feeding_fee_paid'],
        'exam_fee_paid' => isset($row['exam_fee_paid']) ? (float) $row['exam_fee_paid'] : 0.0,
    ];
}

/**
 * Receipt totals aligned with the Student fees page: paid amounts come from student_fees,
 * not from summing fee_payments (the log can be incomplete for older or manual entries).
 *
 * @return array{expected: float, paid: float, remaining: ?float, fully_paid: bool}
 */
function fee_receipt_totals_from_student_fees($conn, $student_id, $academic_year, $fee_type)
{
    $student_id = trim((string) $student_id);
    $academic_year = trim((string) $academic_year);
    $fee_type = strtolower(trim((string) $fee_type));

    $settings = fee_settings_row($conn, $academic_year);
    $expected = fee_expected_for_type($settings, $fee_type);

    // If school yearly amount is missing in settings (0), use standard 500 so receipts show a clear "amount due".
    if ($fee_type === 'school' && $expected < 0.01) {
        $expected = 500.0;
    }

    $row = student_fees_row($conn, $student_id, $academic_year);

    if ($fee_type === 'school') {
        $paid_sf = (float) $row['school_fee_paid'];
    } elseif ($fee_type === 'exam') {
        $paid_sf = (float) $row['exam_fee_paid'];
    } elseif ($fee_type === 'feeding') {
        $paid_sf = (float) $row['feeding_fee_paid'];
    } else {
        $paid_sf = 0.0;
    }

    $paid_log = 0.0;
    if (fee_payments_table_exists($conn)) {
        $sid2 = mysqli_real_escape_string($conn, $student_id);
        $ay2 = mysqli_real_escape_string($conn, $academic_year);
        $ft2 = mysqli_real_escape_string($conn, $fee_type);
        $c = SCHOOL_FEE_STRING_COLLATION;
        $qs = $conn->query("
            SELECT COALESCE(SUM(fp.amount), 0) AS s
            FROM fee_payments fp
            WHERE fp.student_id COLLATE {$c} = '{$sid2}' COLLATE {$c}
              AND fp.academic_year = '{$ay2}'
              AND fp.fee_type = '{$ft2}'
        ");
        if ($qs && ($rw = $qs->fetch_assoc())) {
            $paid_log = (float) $rw['s'];
        }
    }

    $paid = max($paid_sf, $paid_log);

    if ($expected < 0.01) {
        return [
            'expected' => $expected,
            'paid' => $paid,
            'remaining' => null,
            'fully_paid' => false,
        ];
    }

    $remaining = max(0.0, $expected - $paid);

    return [
        'expected' => $expected,
        'paid' => $paid,
        'remaining' => $remaining,
        'fully_paid' => $remaining < 0.005,
    ];
}
