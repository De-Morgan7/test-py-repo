<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role          = $_SESSION['role'];
$academic_year = get_active_year($conn);
$ay_esc        = mysqli_real_escape_string($conn, $academic_year);
$terms         = ['First Term', 'Second Term', 'Third Term'];

$selected_class   = $role === 'Teacher' ? $_SESSION['assigned_class'] : (isset($_GET['class']) ? mysqli_real_escape_string($conn, $_GET['class']) : 'JHS 1');
$selected_student = isset($_GET['student_id']) ? mysqli_real_escape_string($conn, $_GET['student_id']) : '';
$selected_term    = isset($_GET['term']) ? mysqli_real_escape_string($conn, $_GET['term']) : 'First Term';

if (!in_array($selected_term, $terms)) $selected_term = 'First Term';

// Get active students in class
$students     = $conn->query("SELECT * FROM students WHERE class_name='$selected_class' AND status='Active' ORDER BY full_name");
$old_students = $conn->query("SELECT * FROM students WHERE class_name='$selected_class' AND status='Graduated' ORDER BY full_name");

// Get attendance records for selected student and term
$attendance_records = [];
$total_days         = 0;
$days_present       = 0;
$days_absent        = 0;
$student_info       = null;

if ($selected_student) {
    $student_info = $conn->query("SELECT * FROM students WHERE student_id='$selected_student'")->fetch_assoc();

    $records = $conn->query("
        SELECT * FROM attendance
        WHERE student_id='$selected_student'
        AND term='$selected_term'
        AND academic_year='$ay_esc'
        ORDER BY attendance_date ASC
    ");

    while ($row = $records->fetch_assoc()) {
        $attendance_records[] = $row;
        $total_days++;
        if ($row['status'] === 'Present') $days_present++;
        else $days_absent++;
    }
}

$percentage = ($total_days > 0) ? round(($days_present / $total_days) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JHS Portal - Student Attendance Report</title>
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

        .filter-card { background: white; padding: 20px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .filter-card h3 { font-size: 14px; font-weight: 600; color: #555; margin-bottom: 12px; }

        .term-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; }
        .term-pill { padding: 8px 18px; border-radius: 20px; border: 2px solid #ddd; text-decoration: none; font-size: 13px; font-weight: 600; color: #555; background: white; transition: 0.2s; }
        .term-pill.active-first  { background: #e3f2fd; border-color: #1a73e8; color: #1a73e8; }
        .term-pill.active-second { background: #f3e5f5; border-color: #7b1fa2; color: #7b1fa2; }
        .term-pill.active-third  { background: #e8f5e9; border-color: #27ae60; color: #27ae60; }

        .class-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; }
        .class-pill { padding: 8px 18px; border-radius: 20px; border: 1px solid #ddd; text-decoration: none; font-size: 13px; font-weight: 600; color: #555; background: white; transition: 0.2s; }
        .class-pill:hover, .class-pill.active { background: #1a73e8; color: white; border-color: #1a73e8; }

        .filter-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px; }
        .filter-group label { font-size: 13px; font-weight: 600; color: #555; }
        select { padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; background: white; min-height: 44px; width: 100%; max-width: 500px; }
        select:focus { border-color: #1a73e8; }
        .btn-view { padding: 12px 25px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; min-height: 44px; }
        .btn-view:hover { background: #1557b0; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); gap: 16px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 18px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; }
        .stat-card.blue  { border-top: 4px solid #1a73e8; }
        .stat-card.green { border-top: 4px solid #27ae60; }
        .stat-card.red   { border-top: 4px solid #e53935; }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #333; }
        .stat-card .lbl { font-size: 13px; color: #888; margin-top: 5px; }
        .attendance-bar { background: #f0f2f5; border-radius: 20px; height: 10px; margin-top: 8px; overflow: hidden; }
        .attendance-fill { background: #27ae60; height: 100%; border-radius: 20px; }

        .summary-bar { background: white; padding: 14px 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .summary-bar .name { font-size: 16px; font-weight: bold; color: #333; }
        .summary-bar .meta { font-size: 13px; color: #888; }
        .percentage { font-size: 20px; font-weight: bold; color: #27ae60; }

        .table-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .table-header { padding: 14px 20px; background: linear-gradient(135deg,#1a73e8,#0d47a1); color: white; }
        .table-header h2 { font-size: 15px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 350px; }
        thead tr { background: #f8f9fa; }
        th { padding: 12px 14px; text-align: left; font-size: 13px; color: #555; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 11px 14px; font-size: 14px; color: #333; border-bottom: 1px solid #f0f0f0; }
        tr:hover { background: #fafafa; }
        .badge-present { padding: 5px 14px; border-radius: 20px; background: #e8f5e9; color: #2e7d32; font-size: 13px; font-weight: 600; }
        .badge-absent  { padding: 5px 14px; border-radius: 20px; background: #ffebee; color: #c62828; font-size: 13px; font-weight: 600; }
        .empty-state { text-align: center; padding: 50px; color: #999; }
        .info-bar { background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 12px 18px; font-size: 13px; color: #1565c0; margin-bottom: 15px; }
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
        <p><?php echo $role; ?></p>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Main</div>
        <a href="admin.php" class="nav-link">📊 Dashboard</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Students</div>
        <?php if($role === 'Admin'): ?>
        <a href="add_student.php" class="nav-link">➕ Add Student</a>
        <a href="old_students.php" class="nav-link">🎓 Old Students</a>
        <?php endif; ?>
        <a href="view_students.php" class="nav-link">👥 View Students</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Academics</div>
        <?php if($role === 'Teacher'): ?>
        <a href="attendance.php" class="nav-link">✅ Mark Attendance</a>
        <a href="marks.php" class="nav-link">📝 Enter Marks</a>
        <?php endif; ?>
        <a href="view_marks.php" class="nav-link">📊 View Marks</a>
        <a href="attendance_report.php" class="nav-link">📋 Attendance Report</a>
        <a href="student_attendance_report.php" class="nav-link active">👤 Student Attendance</a>
    </div>
    <?php if($role === 'Admin'): ?>
    <div class="nav-section">
        <div class="nav-section-title">Administration</div>
        <a href="manage_users.php" class="nav-link">🔑 Manage Logins</a>
        <a href="fees.php" class="nav-link">💰 Student Fees</a>
        <a href="fee_reports.php" class="nav-link">📈 Fee Reports</a>
        <a href="fee_student_payments.php" class="nav-link">📋 Payment History</a>
        <a href="process_promotions.php" class="nav-link">🔄 Process Promotions</a>
    </div>
    <?php endif; ?>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>👤 Student Attendance Report</h1>
        <span style="font-size:14px; color:#666;">Year: <b><?php echo $academic_year; ?></b></span>
    </div>

    <!-- Term Selection -->
    <div class="filter-card">
        <h3>Step 1 — Select Term</h3>
        <div class="term-pills">
            <?php foreach($terms as $t):
                $tc = 'term-pill';
                if($t === $selected_term) {
                    if($t === 'First Term') $tc .= ' active-first';
                    elseif($t === 'Second Term') $tc .= ' active-second';
                    else $tc .= ' active-third';
                }
            ?>
            <a href="student_attendance_report.php?term=<?php echo urlencode($t); ?>&class=<?php echo urlencode($selected_class); ?>"
               class="<?php echo $tc; ?>">
                <?php echo $t; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if($role === 'Admin'): ?>
        <h3 style="margin-bottom:10px;">Step 2 — Select Class</h3>
        <div class="class-pills">
            <?php foreach(['JHS 1','JHS 2','JHS 3'] as $c): ?>
            <a href="student_attendance_report.php?term=<?php echo urlencode($selected_term); ?>&class=<?php echo urlencode($c); ?>"
               class="class-pill <?php echo $selected_class===$c?'active':''; ?>">
               📚 <?php echo $c; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h3 style="margin-bottom:10px;">Step <?php echo $role==='Admin'?'3':'2'; ?> — Select Student</h3>
        <div class="info-bar">
            ℹ️ Showing attendance for <b><?php echo $selected_term; ?></b> — <b><?php echo $academic_year; ?></b>
        </div>
        <form method="GET">
            <input type="hidden" name="term" value="<?php echo htmlspecialchars($selected_term); ?>">
            <input type="hidden" name="class" value="<?php echo htmlspecialchars($selected_class); ?>">
            <div class="filter-group">
                <label>👤 Student Name</label>
                <select name="student_id">
                    <option value="">-- Select Student --</option>
                    <?php if($students && $students->num_rows > 0): ?>
                    <optgroup label="✅ Active Students">
                        <?php while($s = $students->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($s['student_id']); ?>"
                            <?php echo $selected_student===$s['student_id']?'selected':''; ?>>
                            <?php echo htmlspecialchars($s['full_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </optgroup>
                    <?php endif; ?>
                    <?php if($old_students && $old_students->num_rows > 0): ?>
                    <optgroup label="🎓 Old Students">
                        <?php while($s = $old_students->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($s['student_id']); ?>"
                            <?php echo $selected_student===$s['student_id']?'selected':''; ?>>
                            <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo htmlspecialchars($s['graduation_year']); ?>)
                        </option>
                        <?php endwhile; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            <button type="submit" class="btn-view">📋 View Report</button>
        </form>
    </div>

    <!-- Results -->
    <?php if($selected_student && $student_info): ?>

    <?php if(!empty($attendance_records)): ?>
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="number"><?php echo $total_days; ?></div>
            <div class="lbl">Total School Days</div>
        </div>
        <div class="stat-card green">
            <div class="number"><?php echo $days_present; ?></div>
            <div class="lbl">Days Present</div>
            <div class="attendance-bar">
                <div class="attendance-fill" style="width:<?php echo $percentage; ?>%"></div>
            </div>
        </div>
        <div class="stat-card red">
            <div class="number"><?php echo $days_absent; ?></div>
            <div class="lbl">Days Absent</div>
        </div>
    </div>

    <div class="summary-bar">
        <div>
            <div class="name"><?php echo htmlspecialchars($student_info['full_name']); ?></div>
            <div class="meta">
                <?php echo htmlspecialchars($student_info['class_name']); ?> &nbsp;|&nbsp;
                <?php echo htmlspecialchars($selected_term); ?> &nbsp;|&nbsp;
                <?php echo htmlspecialchars($academic_year); ?>
            </div>
        </div>
        <div class="percentage"><?php echo $percentage; ?>% Attendance</div>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h2>📅 <?php echo htmlspecialchars($selected_term); ?> Attendance — <?php echo htmlspecialchars($student_info['full_name']); ?></h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach($attendance_records as $rec): ?>
                    <tr style="background:<?php echo $rec['status']==='Present'?'#f0fff4':'#fff5f5'; ?>">
                        <td><?php echo $i++; ?></td>
                        <td><?php echo date('F j, Y', strtotime($rec['attendance_date'])); ?></td>
                        <td><?php echo date('l', strtotime($rec['attendance_date'])); ?></td>
                        <td>
                            <span class="<?php echo $rec['status']==='Present'?'badge-present':'badge-absent'; ?>">
                                <?php echo $rec['status']==='Present'?'✅ Present':'❌ Absent'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    <div class="table-card">
        <div class="empty-state">
            <p style="font-size:36px;">📋</p>
            <p>No attendance records found for <b><?php echo htmlspecialchars($student_info['full_name']); ?></b> in <b><?php echo $selected_term; ?></b>.</p>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="table-card">
        <div class="empty-state">
            <p style="font-size:36px;">👤</p>
            <p>Select a term and student above to view their attendance report.</p>
        </div>
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