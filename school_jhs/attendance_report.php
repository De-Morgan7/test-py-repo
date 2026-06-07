<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$academic_year = get_active_year($conn);
$selected_date = isset($_GET['date']) ? mysqli_real_escape_string($conn, $_GET['date']) : date('Y-m-d');
$selected_term = isset($_GET['term']) ? mysqli_real_escape_string($conn, $_GET['term']) : 'First Term';
$terms         = ['First Term', 'Second Term', 'Third Term'];
$classes       = ['JHS 1', 'JHS 2', 'JHS 3'];
$ay_esc        = mysqli_real_escape_string($conn, $academic_year);
$term_esc      = mysqli_real_escape_string($conn, $selected_term);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BHIS Portal - Attendance Report</title>
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
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 20px; }
        .sidebar-logo h2 { font-size: 20px; }
        .sidebar-logo p { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        .nav-section { padding: 0 15px; margin-bottom: 10px; }
        .nav-section-title { font-size: 11px; text-transform: uppercase; opacity: 0.6; margin-bottom: 8px; padding-left: 10px; }
        .nav-link { display: flex; align-items: center; min-height: 44px; padding: 10px 15px; color: white; text-decoration: none; border-radius: 8px; margin-bottom: 4px; transition: 0.2s; font-size: 14px; gap: 8px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        .logout-btn { display: block; margin: 20px 15px 0; padding: 12px 15px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 8px; text-align: center; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); min-height: 44px; }
        .logout-btn:hover { background: #e53935; }

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
        select, input[type="date"] { padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; background: white; min-height: 44px; width: 100%; }
        select:focus, input[type="date"]:focus { border-color: #1a73e8; }
        .btn-view { padding: 10px 25px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; min-height: 44px; }
        .btn-view:hover { background: #1557b0; }

        .class-section { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; overflow: hidden; }
        .class-header { padding: 14px 20px; background: linear-gradient(135deg,#1a73e8,#0d47a1); color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .class-header h2 { font-size: 16px; }
        .class-stats { display: flex; gap: 12px; flex-wrap: wrap; }
        .stat-pill { padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .stat-present { background: #e8f5e9; color: #2e7d32; }
        .stat-absent { background: #ffebee; color: #c62828; }

        .split-table { display: grid; grid-template-columns: 1fr 1fr; }
        .split-side { padding: 18px; }
        .split-side:first-child { border-right: 2px solid #f0f0f0; }
        .split-title { font-size: 14px; font-weight: 700; padding: 8px 0; margin-bottom: 10px; border-bottom: 2px solid; display: flex; align-items: center; gap: 8px; }
        .present-title { color: #2e7d32; border-color: #2e7d32; }
        .absent-title { color: #c62828; border-color: #c62828; }
        .student-item { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; margin-bottom: 6px; font-size: 14px; }
        .student-item.present { background: #f0fff4; color: #2e7d32; }
        .student-item.absent { background: #fff5f5; color: #c62828; }
        .student-num { font-size: 12px; opacity: 0.6; min-width: 20px; }
        .no-record { text-align: center; padding: 20px; color: #bbb; font-size: 14px; }
        .empty-class { padding: 25px; text-align: center; color: #999; font-size: 14px; }

        .term-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; }
        .term-pill { padding: 7px 16px; border-radius: 20px; border: 1px solid #ddd; text-decoration: none; font-size: 13px; font-weight: 600; color: #555; background: white; transition: 0.2s; }
        .term-pill.active { background: #1a73e8; color: white; border-color: #1a73e8; }

        @media screen and (max-width: 768px) {
            .filter-grid { grid-template-columns: 1fr; }
            .split-table { grid-template-columns: 1fr; }
            .split-side:first-child { border-right: none; border-bottom: 2px solid #f0f0f0; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="mobile-header">
    <span>🏫 JHS PORTAL</span>
    <button type="button" class="menu-toggle" id="menuToggle">☰ Menu</button>
</div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <h2>🏫 JHS PORTAL</h2>
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
        <a href="attendance_report.php" class="nav-link active">📋 Attendance Report</a>
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
        <a href="process_promotions.php" class="nav-link">🔄 Process Promotions</a>
    </div>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>📋 Attendance Report</h1>
        <span style="font-size:14px; color:#666;">Year: <b><?php echo $academic_year; ?></b></span>
    </div>

    <!-- Term Pills -->
    <div class="filter-card">
        <div class="term-pills">
            <?php foreach($terms as $t): ?>
            <a href="attendance_report.php?term=<?php echo urlencode($t); ?>&date=<?php echo $selected_date; ?>"
               class="term-pill <?php echo $selected_term===$t?'active':''; ?>">
                <?php echo $t; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <form method="GET">
            <input type="hidden" name="term" value="<?php echo htmlspecialchars($selected_term); ?>">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>📅 Select Date</label>
                    <input type="date" name="date" value="<?php echo $selected_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="filter-group" style="justify-content:flex-end;">
                    <button type="submit" class="btn-view">View Report</button>
                </div>
            </div>
        </form>
    </div>

    <?php foreach($classes as $class):
        $class_esc = mysqli_real_escape_string($conn, $class);
        $present = $conn->query("SELECT s.full_name FROM attendance a JOIN students s ON a.student_id = s.student_id WHERE a.class_name='$class_esc' AND a.status='Present' AND a.attendance_date='$selected_date' AND a.term='$term_esc' AND a.academic_year='$ay_esc' ORDER BY s.full_name");
        $absent  = $conn->query("SELECT s.full_name FROM attendance a JOIN students s ON a.student_id = s.student_id WHERE a.class_name='$class_esc' AND a.status='Absent' AND a.attendance_date='$selected_date' AND a.term='$term_esc' AND a.academic_year='$ay_esc' ORDER BY s.full_name");
        $p_count = $present->num_rows;
        $a_count = $absent->num_rows;
        $total   = $p_count + $a_count;
    ?>
    <div class="class-section">
        <div class="class-header">
            <h2>📚 <?php echo $class; ?> — <?php echo $selected_term; ?></h2>
            <div class="class-stats">
                <span class="stat-pill stat-present">✅ Present: <?php echo $p_count; ?></span>
                <span class="stat-pill stat-absent">❌ Absent: <?php echo $a_count; ?></span>
                <span style="font-size:13px; opacity:0.8;">Total: <?php echo $total; ?></span>
            </div>
        </div>

        <?php if($total == 0): ?>
            <div class="empty-class">No attendance record for <?php echo $class; ?> on this date in <?php echo $selected_term; ?>.</div>
        <?php else: ?>
        <div class="split-table">
            <div class="split-side">
                <div class="split-title present-title">✅ Present (<?php echo $p_count; ?>)</div>
                <?php if($p_count > 0): $i=1; while($row = $present->fetch_assoc()): ?>
                <div class="student-item present">
                    <span class="student-num"><?php echo $i++; ?>.</span>
                    <span><?php echo htmlspecialchars($row['full_name']); ?></span>
                </div>
                <?php endwhile; else: ?>
                <div class="no-record">No students present</div>
                <?php endif; ?>
            </div>
            <div class="split-side">
                <div class="split-title absent-title">❌ Absent (<?php echo $a_count; ?>)</div>
                <?php if($a_count > 0): $i=1; while($row = $absent->fetch_assoc()): ?>
                <div class="student-item absent">
                    <span class="student-num"><?php echo $i++; ?>.</span>
                    <span><?php echo htmlspecialchars($row['full_name']); ?></span>
                </div>
                <?php endwhile; else: ?>
                <div class="no-record">No students absent</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
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