<?php
session_start();
include 'db.php'; // ← must be here first

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Handle year change
if (isset($_POST['change_year'])) {
    $new_year = mysqli_real_escape_string($conn, trim($_POST['new_active_year']));
    $conn->query("INSERT INTO school_settings (setting_key, setting_value)
        VALUES ('active_academic_year', '$new_year')
        ON DUPLICATE KEY UPDATE setting_value='$new_year'");
    $success = "✅ Active academic year changed to $new_year!";
}

$academic_year = get_active_year($conn);
$parts         = explode('/', $academic_year);
$start_yr      = (int)($parts[0] ?? date('Y'));
$new_year      = ($start_yr + 1) . '/' . ($start_yr + 2);

$success = "";
$error   = "";

// PROCESS PROMOTIONS
if (isset($_POST['process_promotions'])) {
    $ay = mysqli_real_escape_string($conn, $academic_year);

    // Get all unprocessed promotions
    $promos = $conn->query("SELECT p.*, s.full_name, s.class_name FROM promotions p JOIN students s ON p.student_id = s.student_id WHERE p.academic_year='$ay' AND p.processed=0");

    if (!$promos || $promos->num_rows === 0) {
        $error = "No unprocessed promotions found for $academic_year. Make sure teachers have marked promotion status in Third Term.";
    } else {
        $promoted_count  = 0;
        $repeated_count  = 0;
        $graduated_count = 0;

        while ($row = $promos->fetch_assoc()) {
            $sid    = mysqli_real_escape_string($conn, $row['student_id']);
            $status = $row['status'];
            $next   = mysqli_real_escape_string($conn, $row['next_class']);

            if ($status === 'Promoted') {
                // Move to next class
                $conn->query("UPDATE students SET class_name='$next' WHERE student_id='$sid'");
                $promoted_count++;
            } elseif ($status === 'Repeated') {
                // Stay in same class — no change needed
                $repeated_count++;
            } elseif ($status === 'Graduated') {
                // Mark as graduated
                $grad_year = mysqli_real_escape_string($conn, $academic_year);
                $conn->query("UPDATE students SET status='Graduated', graduation_year='$grad_year' WHERE student_id='$sid'");
                $graduated_count++;
            }

            // Mark promotion as processed
            $conn->query("UPDATE promotions SET processed=1 WHERE student_id='$sid' AND academic_year='$ay'");
        }

        $success = "✅ Promotions processed successfully! $promoted_count promoted, $repeated_count repeated, $graduated_count graduated.";
    }
}

// Get promotion summary
$ay_esc = mysqli_real_escape_string($conn, $academic_year);

$all_promos = $conn->query("
    SELECT p.student_id, p.status, p.current_class, p.next_class, p.processed,
           s.full_name, s.class_name
    FROM promotions p
    JOIN students s ON p.student_id = s.student_id
    WHERE p.academic_year='$ay_esc'
    ORDER BY p.current_class, p.status, s.full_name
");

$total_promos    = $all_promos ? $all_promos->num_rows : 0;
$already_done    = false;

// Check if already processed
$check_processed = $conn->query("SELECT COUNT(*) as c FROM promotions WHERE academic_year='$ay_esc' AND processed=1")->fetch_assoc()['c'];
$check_total     = $conn->query("SELECT COUNT(*) as c FROM promotions WHERE academic_year='$ay_esc'")->fetch_assoc()['c'];
if ($check_total > 0 && $check_processed == $check_total) {
    $already_done = true;
}

// Count by status
$count_promoted  = $conn->query("SELECT COUNT(*) as c FROM promotions WHERE academic_year='$ay_esc' AND status='Promoted'")->fetch_assoc()['c'];
$count_repeated  = $conn->query("SELECT COUNT(*) as c FROM promotions WHERE academic_year='$ay_esc' AND status='Repeated'")->fetch_assoc()['c'];
$count_graduated = $conn->query("SELECT COUNT(*) as c FROM promotions WHERE academic_year='$ay_esc' AND status='Graduated'")->fetch_assoc()['c'];
$count_unmarked  = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Active'")->fetch_assoc()['c'] - $total_promos;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BHIS Portal — Process Promotions</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        html, body { width: 100%; overflow-x: hidden; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }
        body.nav-open { overflow: hidden; }

        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 1001; opacity: 0; pointer-events: none; transition: opacity 0.25s ease; }
        html.compact .sidebar-overlay { display: block; }
        body.nav-open .sidebar-overlay { opacity: 1; pointer-events: auto; }

        .sidebar { width: 250px; max-width: min(280px,86vw); background: linear-gradient(180deg,#1a73e8,#0d47a1); color: white; padding: 20px 0; position: fixed; left: 0; top: 0; height: 100vh; height: 100dvh; overflow-y: auto; z-index: 1002; transition: transform 0.25s ease; box-shadow: 4px 0 24px rgba(0,0,0,0.12); }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 10px; }
        .sidebar-logo h2 { font-size: 20px; }
        .sidebar-logo p { font-size: 12px; opacity: 0.85; margin-top: 4px; }
        .nav-section { padding: 0 15px; margin-bottom: 10px; }
        .nav-section-title { font-size: 11px; text-transform: uppercase; opacity: 0.6; margin-bottom: 8px; padding-left: 10px; }
        .nav-link { display: flex; align-items: center; min-height: 44px; padding: 10px 15px; color: white; text-decoration: none; border-radius: 8px; margin-bottom: 4px; transition: 0.2s; font-size: 14px; gap: 8px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        .logout-btn { display: block; margin: 15px 15px 0; padding: 12px 15px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 8px; text-align: center; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); min-height: 44px; }
        .logout-btn:hover { background: #e53935; }

        .mobile-header { display: none; background: #1a73e8; color: white; padding: 12px 16px; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; gap: 12px; }
        html.compact .mobile-header { display: flex; }
        .mobile-header span { font-weight: 700; font-size: 16px; }
        .menu-toggle { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.6); color: white; padding: 10px 14px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; min-height: 44px; }

        .main { margin-left: 250px; padding: 25px; width: calc(100% - 250px); min-width: 0; }

        @media screen and (max-width: 1024px) {
            html.compact .main { margin-left: 0 !important; width: 100% !important; padding: 14px !important; }
            html.compact .sidebar { transform: translate3d(-100%,0,0) !important; box-shadow: none; }
            html.compact body.nav-open .sidebar { transform: translate3d(0,0,0) !important; box-shadow: 4px 0 24px rgba(0,0,0,0.12); }
        }
        @media screen and (min-width: 1025px) {
            body.nav-open { overflow: auto; }
            .sidebar-overlay { display: none !important; }
        }

        .top-bar { background: white; padding: 16px 22px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .top-bar h1 { font-size: 20px; color: #333; }

        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 25px; }
        .sum-card { background: white; border-radius: 12px; padding: 18px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; }
        .sum-card.green { border-top: 4px solid #27ae60; }
        .sum-card.orange { border-top: 4px solid #f39c12; }
        .sum-card.blue { border-top: 4px solid #1a73e8; }
        .sum-card.red { border-top: 4px solid #e53935; }
        .sum-card .val { font-size: 30px; font-weight: bold; color: #333; }
        .sum-card .lbl { font-size: 13px; color: #888; margin-top: 6px; }

        .info-card { background: white; border-radius: 12px; padding: 20px 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .info-card h2 { font-size: 16px; color: #333; margin-bottom: 10px; }

        .warn-box { background: #fff8e1; border: 1px solid #ffc107; border-radius: 8px; padding: 14px 18px; font-size: 14px; color: #5d4037; margin-bottom: 20px; line-height: 1.5; }
        .success-msg { background: #e8f5e9; color: #2e7d32; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2e7d32; font-size: 15px; }
        .error-msg { background: #ffebee; color: #c62828; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c62828; font-size: 14px; }
        .done-box { background: #e8f5e9; border: 2px solid #27ae60; border-radius: 8px; padding: 18px; font-size: 14px; color: #2e7d32; margin-bottom: 20px; text-align: center; }

        .table-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 20px; }
        .table-header { padding: 14px 20px; background: linear-gradient(135deg,#1a73e8,#0d47a1); color: white; font-size: 15px; font-weight: 600; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        thead tr { background: #f8f9fa; }
        th { padding: 12px 14px; text-align: left; font-size: 13px; color: #555; font-weight: 600; border-bottom: 2px solid #eee; white-space: nowrap; }
        td { padding: 11px 14px; font-size: 14px; color: #333; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        tr:hover { background: #fafafa; }

        .badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .badge-promoted { background: #e8f5e9; color: #2e7d32; }
        .badge-repeated { background: #fff8e1; color: #e67e22; }
        .badge-graduated { background: #e3f2fd; color: #1565c0; }
        .badge-done { background: #f5f5f5; color: #888; }

        .btn-process { width: 100%; padding: 16px; background: #27ae60; color: white; border: none; border-radius: 10px; font-size: 17px; font-weight: bold; cursor: pointer; transition: 0.2s; margin-top: 10px; }
        .btn-process:hover { background: #219a52; }
        .btn-process:disabled { background: #aaa; cursor: not-allowed; }

        .year-arrow { display: flex; align-items: center; justify-content: center; gap: 15px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; flex-wrap: wrap; }
        .year-box { text-align: center; padding: 15px 25px; border-radius: 10px; }
        .year-box.current { background: #e3f2fd; border: 2px solid #1a73e8; }
        .year-box.next { background: #e8f5e9; border: 2px solid #27ae60; }
        .year-box .yr { font-size: 20px; font-weight: bold; }
        .year-box .lbl { font-size: 12px; color: #888; margin-top: 4px; }
        .arrow { font-size: 30px; color: #1a73e8; }
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
        <p><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
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
        <a href="fees.php" class="nav-link">💰 Student Fees</a>
        <a href="feeding_fees.php" class="nav-link">🍽 Daily Feeding</a>
        <a href="feeding_report.php" class="nav-link">📅 Feeding by Date</a>
        <a href="fee_reports.php" class="nav-link">📈 Fee Reports</a>
        <a href="fee_student_payments.php" class="nav-link">📋 Payment History</a>
        <a href="process_promotions.php" class="nav-link active">🔄 Process Promotions</a>
    </div>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>🔄 Process Promotions</h1>
        <span style="font-size:14px; color:#666;">End of Academic Year</span>
    </div>

    <?php if($success): ?>
        <div class="success-msg"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="error-msg">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Year transition display -->
  <div class="year-arrow">
    <div class="year-box current">
        <div class="yr"><?php echo htmlspecialchars($academic_year); ?></div>
        <div class="lbl">📅 Active Academic Year</div>
    </div>
    <div class="arrow">→</div>
    <div class="year-box next">
        <div class="yr"><?php echo htmlspecialchars($new_year); ?></div>
        <div class="lbl">🆕 Next Academic Year</div>
    </div>
</div>

    <!-- Summary -->
    <div class="summary-grid">
        <div class="sum-card green">
            <div class="val"><?php echo $count_promoted; ?></div>
            <div class="lbl">✅ To be Promoted</div>
        </div>
        <div class="sum-card orange">
            <div class="val"><?php echo $count_repeated; ?></div>
            <div class="lbl">🔄 To Repeat</div>
        </div>
        <div class="sum-card blue">
            <div class="val"><?php echo $count_graduated; ?></div>
            <div class="lbl">🎓 Graduating</div>
        </div>
        <div class="sum-card red">
            <div class="val"><?php echo $count_unmarked; ?></div>
            <div class="lbl">⚠️ Not Yet Marked</div>
        </div>
    </div>

    <?php if($count_unmarked > 0): ?>
    <div class="warn-box">
        ⚠️ <b><?php echo $count_unmarked; ?> student(s)</b> have not been marked yet by their teachers.
        Make sure all teachers complete promotion marking in <b>Third Term</b> before processing.
    </div>
    <?php endif; ?>

    <?php if($already_done): ?>
    <div class="done-box">
        ✅ <b>Promotions for <?php echo $academic_year; ?> have already been processed.</b><br>
        Students have been moved to their new classes. Check <a href="view_students.php">View Students</a> to confirm.
    </div>
    <?php endif; ?>

    <!-- Promotion Preview Table -->
    <?php if($total_promos > 0): ?>
    <div class="table-card">
        <div class="table-header">
            📋 Promotion Summary — <?php echo $academic_year; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Current Class</th>
                        <th>Status</th>
                        <th>Moving To</th>
                        <th>Processed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    $all_promos->data_seek(0);
                    while($row = $all_promos->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><b><?php echo htmlspecialchars($row['full_name']); ?></b></td>
                        <td><?php echo htmlspecialchars($row['current_class']); ?></td>
                        <td>
                            <?php if($row['status'] === 'Promoted'): ?>
                                <span class="badge badge-promoted">✅ Promoted</span>
                            <?php elseif($row['status'] === 'Repeated'): ?>
                                <span class="badge badge-repeated">🔄 Repeated</span>
                            <?php else: ?>
                                <span class="badge badge-graduated">🎓 Graduated</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['status'] === 'Promoted'): ?>
                                <b style="color:#2e7d32;"><?php echo htmlspecialchars($row['next_class']); ?></b>
                            <?php elseif($row['status'] === 'Repeated'): ?>
                                <span style="color:#e67e22;"><?php echo htmlspecialchars($row['current_class']); ?> (same)</span>
                            <?php else: ?>
                                <span style="color:#1565c0;">Old Students Section</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['processed']): ?>
                                <span class="badge badge-done">✅ Done</span>
                            <?php else: ?>
                                <span class="badge" style="background:#fff8e1; color:#e67e22;">⏳ Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="info-card">
        <p style="text-align:center; padding:30px; color:#999;">
            No promotion records found for <?php echo $academic_year; ?>.<br>
            Teachers need to mark promotion status from the <b>Promotion & Remarks</b> page in Third Term.
        </p>
    </div>
    <?php endif; ?>
	
<!-- Change Active Academic Year -->
<div class="info-card" style="margin-bottom:20px;">
    <h2>📅 Set Active Academic Year</h2>
    <p style="font-size:14px; color:#666; margin-bottom:15px;">
        Current active year: <b style="color:#1a73e8;"><?php echo get_active_year($conn); ?></b><br>
        After processing promotions, change this to the new academic year so teachers work in the correct year.
    </p>
    <form method="POST" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div style="display:flex; flex-direction:column; gap:6px;">
            <label style="font-size:13px; font-weight:600; color:#555;">New Academic Year</label>
            <select name="new_active_year" style="padding:10px 14px; border:1px solid #ddd; border-radius:8px; font-size:15px; min-height:44px;">
                <?php
$start_opt = $start_yr - 1;
for($i = 0; $i <= 4; $i++) {
    $y   = $start_opt + $i;
    $opt = $y . '/' . ($y + 1);
                    $sel = $opt === get_active_year($conn) ? 'selected' : '';
                    echo "<option value='$opt' $sel>$opt</option>";
                }
                ?>
            </select>
        </div>
        <button type="submit" name="change_year" style="padding:10px 25px; background:#1a73e8; color:white; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; min-height:44px;">
            🔄 Switch to This Year
        </button>
    </form>
</div>
    <!-- Process Button -->
    <?php if(!$already_done && $total_promos > 0): ?>
    <div class="info-card">
        <h2>⚡ Process New Academic Year</h2>
        <p style="font-size:14px; color:#666; margin-bottom:15px; line-height:1.6;">
            Clicking the button below will:
            <br>✅ Move <b><?php echo $count_promoted; ?> promoted</b> students to their next class
            <br>🔄 Keep <b><?php echo $count_repeated; ?> repeated</b> students in the same class
            <br>🎓 Move <b><?php echo $count_graduated; ?> graduated</b> students to Old Students section
            <br><br>
            <b style="color:#e53935;">⚠️ This action cannot be undone. Make sure all teachers have completed promotion marking.</b>
        </p>
        <form method="POST" onsubmit="return confirm('Are you sure you want to process all promotions? This cannot be undone.');">
            <button type="submit" name="process_promotions" class="btn-process"
                <?php echo $count_unmarked > 0 ? '' : ''; ?>>
                🔄 Process All Promotions for <?php echo $academic_year; ?>
            </button>
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
        if (!on) document.body.classList.remove('nav-open');
    }
    syncCompact();
    if (mq.addEventListener) mq.addEventListener('change', syncCompact);

    var body    = document.body;
    var overlay = document.getElementById('sidebarOverlay');
    var toggle  = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');

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