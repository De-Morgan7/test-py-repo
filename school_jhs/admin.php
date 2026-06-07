<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role           = $_SESSION['role'];
$full_name      = $_SESSION['full_name'];
$assigned_class = $_SESSION['assigned_class'] ?? '';
$today          = date('Y-m-d');

// Admin stats
$total_students = $conn->query("SELECT COUNT(*) as total FROM students WHERE status='Active'")->fetch_assoc()['total'];
$jhs1_count     = $conn->query("SELECT COUNT(*) as total FROM students WHERE class_name='JHS 1' AND status='Active'")->fetch_assoc()['total'];
$jhs2_count     = $conn->query("SELECT COUNT(*) as total FROM students WHERE class_name='JHS 2' AND status='Active'")->fetch_assoc()['total'];
$jhs3_count     = $conn->query("SELECT COUNT(*) as total FROM students WHERE class_name='JHS 3' AND status='Active'")->fetch_assoc()['total'];
$present_today  = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE status='Present' AND attendance_date='$today'")->fetch_assoc()['total'];
$absent_today   = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE status='Absent' AND attendance_date='$today'")->fetch_assoc()['total'];

// Teacher stats
$my_students = 0;
$my_present  = 0;
$my_absent   = 0;
if ($role === 'Teacher' && $assigned_class) {
    $ac          = mysqli_real_escape_string($conn, $assigned_class);
    $my_students = $conn->query("SELECT COUNT(*) as total FROM students WHERE class_name='$ac' AND status='Active'")->fetch_assoc()['total'];
    $my_present  = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE class_name='$ac' AND status='Present' AND attendance_date='$today'")->fetch_assoc()['total'];
    $my_absent   = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE class_name='$ac' AND status='Absent' AND attendance_date='$today'")->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BHIS Portal Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        html, body { width: 100%; overflow-x: hidden; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }
        body.nav-open { overflow: hidden; }

        /* SIDEBAR OVERLAY */
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 1001; opacity: 0; pointer-events: none; transition: opacity 0.25s ease; }
        html.compact .sidebar-overlay { display: block; }
        body.nav-open .sidebar-overlay { opacity: 1; pointer-events: auto; }

        /* SIDEBAR */
        .sidebar { width: 250px; max-width: min(280px, 86vw); background: linear-gradient(180deg, #1a73e8, #0d47a1); color: white; padding: 20px 0; position: fixed; left: 0; top: 0; height: 100vh; height: 100dvh; overflow-y: auto; z-index: 1002; transition: transform 0.25s ease; box-shadow: 4px 0 24px rgba(0,0,0,0.12); }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 10px; }
        .sidebar-logo h2 { font-size: 20px; }
        .sidebar-logo p { font-size: 12px; opacity: 0.85; margin-top: 4px; }
        .nav-section { padding: 0 15px; margin-bottom: 10px; }
        .nav-section-title { font-size: 11px; text-transform: uppercase; opacity: 0.6; margin-bottom: 8px; padding-left: 10px; letter-spacing: 0.5px; }
        .nav-link { display: flex; align-items: center; min-height: 44px; padding: 10px 15px; color: white; text-decoration: none; border-radius: 8px; margin-bottom: 4px; transition: 0.2s; font-size: 14px; gap: 8px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        .logout-btn { display: block; margin: 15px 15px 0; padding: 12px 15px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 8px; text-align: center; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); min-height: 44px; transition: 0.2s; }
        .logout-btn:hover { background: #e53935; }

        /* MOBILE HEADER */
        .mobile-header { display: none; background: #1a73e8; color: white; padding: 12px 16px; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.15); gap: 12px; }
        html.compact .mobile-header { display: flex; }
        .mobile-header span { font-weight: 700; font-size: 16px; }
        .menu-toggle { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.6); color: white; padding: 10px 14px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; min-height: 44px; }

        /* MAIN */
        .main { margin-left: 250px; padding: 25px; width: calc(100% - 250px); min-width: 0; }

        @media screen and (max-width: 1024px) {
            html.compact .main { margin-left: 0 !important; width: 100% !important; padding: 14px !important; }
            html.compact .sidebar { transform: translate3d(-100%, 0, 0) !important; box-shadow: none; }
            html.compact body.nav-open .sidebar { transform: translate3d(0, 0, 0) !important; box-shadow: 4px 0 24px rgba(0,0,0,0.12); }
        }
        @media screen and (min-width: 1025px) {
            body.nav-open { overflow: auto; }
            .sidebar-overlay { display: none !important; }
        }

        /* TOP BAR */
        .top-bar { background: white; padding: 16px 22px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .top-bar h1 { font-size: 20px; color: #333; }
        .user-info { font-size: 14px; color: #666; }
        .user-info b { color: #1a73e8; }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; margin-bottom: 22px; }
        .stat-card { background: white; padding: 22px 18px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-top: 5px solid #1a73e8; }
        .stat-card.green { border-top-color: #27ae60; }
        .stat-card.red { border-top-color: #e53935; }
        .stat-card.orange { border-top-color: #f39c12; }
        .number { font-size: 34px; font-weight: bold; color: #333; }
        .label { margin-top: 8px; font-size: 14px; color: #777; }

        /* CLASS CARDS */
        .class-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; margin-bottom: 22px; }
        .class-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .class-card h3 { color: #1a73e8; margin-bottom: 12px; font-size: 16px; }
        .count { font-size: 30px; font-weight: bold; color: #333; }
        .sub { font-size: 13px; color: #777; margin-top: 4px; }

        /* QUICK ACTIONS */
        .section-title { font-size: 17px; margin-bottom: 15px; color: #333; font-weight: 600; }
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
        .action-card { background: white; padding: 22px 16px; border-radius: 12px; text-decoration: none; color: #333; text-align: center; transition: 0.3s; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .action-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .icon { font-size: 36px; margin-bottom: 10px; }
        .title { font-weight: bold; margin-bottom: 5px; font-size: 14px; }
        .desc { font-size: 12px; color: #777; }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- MOBILE HEADER -->
<div class="mobile-header">
    <span>🏫 BHIS PORTAL</span>
    <button type="button" class="menu-toggle" id="menuToggle">☰ Menu</button>
</div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <h2>🏫 BHIS PORTAL</h2>
        <p><?php echo htmlspecialchars($full_name); ?></p>
        <p><?php echo htmlspecialchars($role); ?></p>
    </div>

    <?php if($role === 'Admin'): ?>
    <div class="nav-section">
        <div class="nav-section-title">Main</div>
        <a href="admin.php" class="nav-link active">📊 Dashboard</a>
		<a href="term_settings.php" class="nav-link">📅 Term Settings</a>
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
        <a href="process_promotions.php" class="nav-link">🔄 Process Promotions</a>
    </div>

    <?php else: ?>
    <div class="nav-section">
        <div class="nav-section-title">My Class: <?php echo htmlspecialchars($assigned_class); ?></div>
        <a href="admin.php" class="nav-link active">📊 Dashboard</a>
        <a href="attendance.php" class="nav-link">✅ Mark Attendance</a>
        <a href="marks.php" class="nav-link">📝 Enter Marks</a>
        <a href="attendance_report.php" class="nav-link">📋 Attendance Report</a>
        <a href="view_marks.php" class="nav-link">📊 View Marks</a>
        <a href="view_students.php" class="nav-link">👥 View Students</a>
        <a href="promotion.php" class="nav-link">🎓 Promotion & Remarks</a>
    </div>
    <?php endif; ?>

    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<!-- MAIN CONTENT -->
<div class="main">

    <div class="top-bar">
        <h1>Welcome, <?php echo htmlspecialchars($full_name); ?> 👋</h1>
        <div class="user-info">
            Logged in as: <b><?php echo htmlspecialchars($role); ?></b> &nbsp;|&nbsp; <?php echo date('F j, Y'); ?>
        </div>
    </div>

    <?php if($role === 'Admin'): ?>
    <!-- ADMIN DASHBOARD -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $total_students; ?></div>
            <div class="label">Total Students</div>
        </div>
        <div class="stat-card green">
            <div class="number"><?php echo $present_today; ?></div>
            <div class="label">Present Today</div>
        </div>
        <div class="stat-card red">
            <div class="number"><?php echo $absent_today; ?></div>
            <div class="label">Absent Today</div>
        </div>
        <div class="stat-card orange">
            <div class="number">3</div>
            <div class="label">Active Classes</div>
        </div>
    </div>

    <div class="class-grid">
        <div class="class-card">
            <h3>📚 JHS 1</h3>
            <div class="count"><?php echo $jhs1_count; ?></div>
            <div class="sub">students enrolled</div>
        </div>
        <div class="class-card">
            <h3>📚 JHS 2</h3>
            <div class="count"><?php echo $jhs2_count; ?></div>
            <div class="sub">students enrolled</div>
        </div>
        <div class="class-card">
            <h3>📚 JHS 3</h3>
            <div class="count"><?php echo $jhs3_count; ?></div>
            <div class="sub">students enrolled</div>
        </div>
    </div>

    <p class="section-title">Quick Actions</p>
    <div class="actions-grid">
        <a href="add_student.php" class="action-card">
            <div class="icon">➕</div>
            <div class="title">Add Student</div>
            <div class="desc">Register new student</div>
        </a>
        <a href="view_students.php" class="action-card">
            <div class="icon">👥</div>
            <div class="title">View Students</div>
            <div class="desc">Browse all students</div>
        </a>
        <a href="attendance_report.php" class="action-card">
            <div class="icon">📋</div>
            <div class="title">Attendance Report</div>
            <div class="desc">View class attendance</div>
        </a>
        <a href="view_marks.php" class="action-card">
            <div class="icon">📝</div>
            <div class="title">View Marks</div>
            <div class="desc">Check student grades</div>
        </a>
        <a href="fees.php" class="action-card">
            <div class="icon">💰</div>
            <div class="title">Student Fees</div>
            <div class="desc">School, exam & feeding</div>
        </a>
        <a href="fee_reports.php" class="action-card">
            <div class="icon">📈</div>
            <div class="title">Fee Reports</div>
            <div class="desc">Daily totals & revenue</div>
        </a>
        <a href="manage_users.php" class="action-card">
            <div class="icon">🔑</div>
            <div class="title">Manage Logins</div>
            <div class="desc">Reset passwords</div>
        </a>
        <a href="process_promotions.php" class="action-card">
            <div class="icon">🔄</div>
            <div class="title">Process Promotions</div>
            <div class="desc">New academic year</div>
        </a>
        <a href="old_students.php" class="action-card">
            <div class="icon">🎓</div>
            <div class="title">Old Students</div>
            <div class="desc">View graduates</div>
        </a>
    </div>

    <?php else: ?>
    <!-- TEACHER DASHBOARD -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $my_students; ?></div>
            <div class="label">My Students</div>
        </div>
        <div class="stat-card green">
            <div class="number"><?php echo $my_present; ?></div>
            <div class="label">Present Today</div>
        </div>
        <div class="stat-card red">
            <div class="number"><?php echo $my_absent; ?></div>
            <div class="label">Absent Today</div>
        </div>
        <div class="stat-card orange">
            <div class="number"><?php echo htmlspecialchars($assigned_class); ?></div>
            <div class="label">My Class</div>
        </div>
    </div>

    <p class="section-title">Quick Actions</p>
    <div class="actions-grid">
        <a href="attendance.php" class="action-card">
            <div class="icon">✅</div>
            <div class="title">Mark Attendance</div>
            <div class="desc">Take today's attendance</div>
        </a>
        <a href="marks.php" class="action-card">
            <div class="icon">📝</div>
            <div class="title">Enter Marks</div>
            <div class="desc">Add student scores</div>
        </a>
        <a href="attendance_report.php" class="action-card">
            <div class="icon">📋</div>
            <div class="title">Attendance Report</div>
            <div class="desc">View class attendance</div>
        </a>
        <a href="view_marks.php" class="action-card">
            <div class="icon">📊</div>
            <div class="title">View Marks</div>
            <div class="desc">Check student grades</div>
        </a>
        <a href="promotion.php" class="action-card">
            <div class="icon">🎓</div>
            <div class="title">Promotion & Remarks</div>
            <div class="desc">Mark student promotions</div>
        </a>
        <a href="view_students.php" class="action-card">
            <div class="icon">👥</div>
            <div class="title">View Students</div>
            <div class="desc">My class students</div>
        </a>
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