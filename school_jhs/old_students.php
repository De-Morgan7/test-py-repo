<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Filter by graduation year
$years = [];
$yr = $conn->query("SELECT DISTINCT graduation_year FROM students WHERE status='Graduated' AND graduation_year IS NOT NULL ORDER BY graduation_year DESC");
while ($yr && $row = $yr->fetch_assoc()) {
    $years[] = $row['graduation_year'];
}

$selected_year = isset($_GET['year']) ? mysqli_real_escape_string($conn, $_GET['year']) : ($years[0] ?? '');

if ($selected_year) {
    $graduates = $conn->query("SELECT * FROM students WHERE status='Graduated' AND graduation_year='$selected_year' ORDER BY full_name");
} else {
    $graduates = $conn->query("SELECT * FROM students WHERE status='Graduated' ORDER BY graduation_year DESC, full_name");
}

$total_graduates = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Graduated'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BHIS Portal — Old Students</title>
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

        .stat-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; border-top: 4px solid #1a73e8; margin-bottom: 20px; display: inline-block; min-width: 160px; }
        .stat-card .number { font-size: 34px; font-weight: bold; color: #333; }
        .stat-card .label { font-size: 13px; color: #888; margin-top: 6px; }

        .filter-card { background: white; padding: 18px 22px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .filter-card label { font-size: 13px; font-weight: 600; color: #555; }
        .year-pills { display: flex; gap: 8px; flex-wrap: wrap; }
        .year-pill { padding: 8px 18px; border-radius: 20px; border: 1px solid #ddd; text-decoration: none; font-size: 13px; font-weight: 600; color: #555; background: white; transition: 0.2s; }
        .year-pill:hover, .year-pill.active { background: #1a73e8; color: white; border-color: #1a73e8; }

        .table-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .table-header { padding: 14px 20px; background: linear-gradient(135deg,#1a73e8,#0d47a1); color: white; display: flex; justify-content: space-between; align-items: center; }
        .table-header h2 { font-size: 15px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 550px; }
        thead tr { background: #f8f9fa; }
        th { padding: 12px 14px; text-align: left; font-size: 13px; color: #555; font-weight: 600; border-bottom: 2px solid #eee; white-space: nowrap; }
        td { padding: 11px 14px; font-size: 14px; color: #333; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        tr:hover { background: #fafafa; }

        .badge-grad { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #e3f2fd; color: #1565c0; }
        .empty-state { text-align: center; padding: 50px; color: #999; }
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
        <a href="old_students.php" class="nav-link active">🎓 Old Students</a>
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
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>🎓 Old Students (Graduates)</h1>
        <span style="font-size:14px; color:#666;"><?php echo date('F j, Y'); ?></span>
    </div>

    <!-- Total stat -->
    <div class="stat-card" style="margin-bottom:20px;">
        <div class="number"><?php echo $total_graduates; ?></div>
        <div class="label">Total Graduates</div>
    </div>

    <!-- Year filter -->
    <?php if(!empty($years)): ?>
    <div class="filter-card">
        <label>Filter by Graduation Year:</label>
        <div class="year-pills">
            <a href="old_students.php" class="year-pill <?php echo $selected_year===''?'active':''; ?>">All Years</a>
            <?php foreach($years as $y): ?>
            <a href="old_students.php?year=<?php echo urlencode($y); ?>"
               class="year-pill <?php echo $selected_year===$y?'active':''; ?>">
               <?php echo htmlspecialchars($y); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Graduates table -->
    <div class="table-card">
        <div class="table-header">
            <h2>🎓 <?php echo $selected_year ? 'Class of ' . htmlspecialchars($selected_year) : 'All Graduates'; ?></h2>
            <span style="font-size:13px; opacity:0.8;">
                <?php echo $graduates ? $graduates->num_rows : 0; ?> students
            </span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Gender</th>
                        <th>Guardian</th>
                        <th>Phone</th>
                        <th>Graduation Year</th>
                        <th>Admitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($graduates && $graduates->num_rows > 0):
                        $i = 1;
                        while($row = $graduates->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><b><?php echo htmlspecialchars($row['student_id']); ?></b></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['gender']); ?></td>
                        <td><?php echo htmlspecialchars($row['guardian_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['guardian_phone']); ?></td>
                        <td><span class="badge-grad">🎓 <?php echo htmlspecialchars($row['graduation_year']); ?></span></td>
                        <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                    </tr>
                    <?php endwhile;
                    else: ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <p style="font-size:40px;">🎓</p>
                                <p>No graduates found<?php echo $selected_year ? ' for ' . htmlspecialchars($selected_year) : ''; ?>.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
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