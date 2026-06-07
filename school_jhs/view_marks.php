<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$subjects = ['English Language', 'Mathematics', 'Integrated Science', 'Religious and Moral Education', 'Social Studies', 'French', 'Career Technology', 'Creative Art and Design', 'Ashanti Twi', 'Computing'];
$terms = ['First Term', 'Second Term', 'Third Term'];
$academic_year = get_active_year($conn);

$selected_term  = isset($_GET['term']) ? mysqli_real_escape_string($conn, $_GET['term']) : 'First Term';
$selected_class = $role === 'Teacher' ? $_SESSION['assigned_class'] : (isset($_GET['class']) ? mysqli_real_escape_string($conn, $_GET['class']) : 'JHS 1');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Beverly Hills International School - View Marks</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        html, body { width: 100%; max-width: 100%; overflow-x: hidden; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }
        body.nav-open { overflow: hidden; }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 1001;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        html.compact .sidebar-overlay { display: block; }
        body.nav-open .sidebar-overlay { opacity: 1; pointer-events: auto; }

        .sidebar {
            width: 250px;
            max-width: min(280px, 86vw);
            background: linear-gradient(180deg, #1a73e8, #0d47a1);
            color: white;
            padding: 20px 0;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            height: 100dvh;
            overflow-y: auto;
            z-index: 1002;
            transition: transform 0.25s ease;
            -webkit-overflow-scrolling: touch;
            box-shadow: 4px 0 24px rgba(0,0,0,0.12);
        }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 20px; }
        .sidebar-logo h2 { font-size: 20px; }
        .sidebar-logo p { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        .nav-section { padding: 0 15px; margin-bottom: 10px; }
        .nav-section-title { font-size: 11px; text-transform: uppercase; opacity: 0.6; margin-bottom: 8px; padding-left: 10px; }
        .nav-link {
            display: flex;
            align-items: center;
            min-height: 44px;
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 4px;
            transition: 0.2s;
            font-size: 14px;
        }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        .logout-btn {
            display: block;
            margin: 20px 15px 0;
            padding: 12px 15px;
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            border: 1px solid rgba(255,255,255,0.3);
            min-height: 44px;
            line-height: 1.2;
        }
        .logout-btn:hover { background: #e53935; }

        .main {
            margin-left: 250px;
            padding: 30px;
            width: calc(100% - 250px);
            min-width: 0;
        }

        @media screen and (max-width: 1024px) {
            html.compact .main {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                padding: 14px !important;
            }
            html.compact .sidebar {
                transform: translate3d(-100%, 0, 0) !important;
                box-shadow: none;
            }
            html.compact body.nav-open .sidebar {
                transform: translate3d(0, 0, 0) !important;
                box-shadow: 4px 0 24px rgba(0,0,0,0.12);
            }
        }

        .mobile-header {
            display: none;
            background: #1a73e8;
            color: white;
            padding: 12px 14px;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            gap: 12px;
        }
        html.compact .mobile-header { display: flex; }
        .mobile-header span { font-weight: 600; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .menu-toggle {
            flex-shrink: 0;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.6);
            color: white;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            min-height: 44px;
        }

        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .top-bar h1 { font-size: 20px; color: #333; line-height: 1.3; }

        .filter-card {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 13px; font-weight: 600; color: #555; }
        select {
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            outline: none;
            width: 100%;
            min-height: 44px;
            background: #fff;
        }
        select:focus { border-color: #1a73e8; }
        .btn-filter {
            padding: 12px 25px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            min-height: 44px;
            width: 100%;
        }
        .btn-filter:hover { background: #1557b0; }

        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            overflow: hidden;
        }
        .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table-header h2 { font-size: 16px; line-height: 1.35; }

        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        thead tr { background: #f8f9fa; }
        th {
            padding: 12px 10px;
            text-align: center;
            font-size: 12px;
            color: #555;
            font-weight: 600;
            border-bottom: 2px solid #eee;
            white-space: nowrap;
        }
        th.left, td.left { text-align: left; }
        td {
            padding: 10px;
            font-size: 13px;
            color: #333;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
            vertical-align: middle;
        }
        tr:hover { background: #fafafa; }

        /* Sticky first two columns while scrolling horizontally */
        .sticky-pos {
            position: sticky;
            left: 0;
            z-index: 2;
            background: #fff;
            box-shadow: 4px 0 8px -4px rgba(0,0,0,0.12);
            min-width: 5.5rem;
        }
        thead .sticky-pos { background: #f8f9fa; z-index: 4; }
        .sticky-name {
            position: sticky;
            left: 5.5rem;
            z-index: 1;
            background: #fff;
            box-shadow: 4px 0 8px -4px rgba(0,0,0,0.12);
            min-width: 9rem;
            max-width: 12rem;
        }
        thead .sticky-name { background: #f8f9fa; z-index: 3; }
        tr:hover .sticky-pos,
        tr:hover .sticky-name { background: #fafafa; }
        thead tr:hover .sticky-pos,
        thead tr:hover .sticky-name { background: #f8f9fa; }

        .grade-badge { padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-block; }
        .grade-1, .grade-2 { background: #e8f5e9; color: #2e7d32; }
        .grade-3, .grade-4 { background: #e3f2fd; color: #1565c0; }
        .grade-5, .grade-6 { background: #fff8e1; color: #f57f17; }
        .grade-7, .grade-8 { background: #fce4ec; color: #880e4f; }
        .grade-9 { background: #ffebee; color: #c62828; }

        .position-badge { padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 700; background: #fff8e1; color: #e67e22; }
        .position-1 { background: #fff9c4; color: #f9a825; }
        .position-2 { background: #f5f5f5; color: #616161; }
        .position-3 { background: #fbe9e7; color: #bf360c; }

        .btn-report {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            background: #1a73e8;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            min-height: 40px;
        }
        .btn-report:hover { background: #1557b0; }

        .empty-state { text-align: center; padding: 40px 20px; color: #999; }

        @media screen and (max-width: 1024px) {
            .filter-card { padding: 16px; }
            .top-bar { padding: 14px 16px; }
            .top-bar h1 { font-size: 18px; }
        }

        @media screen and (min-width: 1025px) {
            body.nav-open { overflow: auto; }
            .sidebar-overlay { display: none !important; opacity: 0 !important; pointer-events: none !important; }
            .btn-filter { width: auto; }
            .filter-grid { grid-template-columns: <?php echo $role === 'Admin' ? '1fr 1fr auto' : '1fr auto'; ?>; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<div class="mobile-header">
    <span>🏫 BHIS PORTAL</span>
    <button type="button" class="menu-toggle" id="menuToggle" aria-expanded="false" aria-controls="sidebar">☰ Menu</button>
</div>

<div class="sidebar" id="sidebar" role="navigation" aria-label="Main menu">
    <div class="sidebar-logo">
        <h2>🏫 BHIS PORTAL</h2>
        <p><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></p>
        <p><?php echo htmlspecialchars($role); ?></p>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Main</div>
        <a href="admin.php" class="nav-link"><span>📊</span> Dashboard</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Students</div>
        <?php if($role === 'Admin'): ?>
        <a href="add_student.php" class="nav-link"><span>➕</span> Add Student</a>
        <?php endif; ?>
        <a href="view_students.php" class="nav-link"><span>👥</span> View Students</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Academics</div>
        <?php if($role === 'Teacher'): ?>
        <a href="attendance.php" class="nav-link"><span>✅</span> Mark Attendance</a>
        <a href="marks.php" class="nav-link"><span>📝</span> Enter Marks</a>
        <?php endif; ?>
        <a href="view_marks.php" class="nav-link active"><span>📊</span> View Marks</a>
        <a href="attendance_report.php" class="nav-link"><span>📋</span> Attendance Report</a>
    </div>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>📊 View Marks & Positions</h1>
        <span style="font-size:14px; color:#666;">Year: <b><?php echo htmlspecialchars($academic_year); ?></b></span>
    </div>

    <div class="filter-card">
        <form method="GET">
            <div class="filter-grid">
                <?php if($role === 'Admin'): ?>
                <div class="filter-group">
                    <label>📚 Class</label>
                    <select name="class">
                        <option value="JHS 1" <?php echo $selected_class=='JHS 1'?'selected':''; ?>>JHS 1</option>
                        <option value="JHS 2" <?php echo $selected_class=='JHS 2'?'selected':''; ?>>JHS 2</option>
                        <option value="JHS 3" <?php echo $selected_class=='JHS 3'?'selected':''; ?>>JHS 3</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="filter-group">
                    <label>📅 Term</label>
                    <select name="term">
                        <?php foreach($terms as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $selected_term==$t?'selected':''; ?>><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group" style="justify-content:flex-end;">
                    <button type="submit" class="btn-filter">View</button>
                </div>
            </div>
        </form>
    </div>

    <?php
   $students = $conn->query("SELECT * FROM students WHERE class_name='$selected_class' AND status='Active' ORDER BY full_name");

    $student_totals = [];
    while($s = $students->fetch_assoc()) {
        $sid = $s['student_id'];
        $total_res = $conn->query("SELECT SUM(total) as grand_total FROM marks WHERE student_id='$sid' AND class_name='$selected_class' AND term='$selected_term' AND academic_year='$academic_year'")->fetch_assoc();
        $student_totals[$sid] = [
            'full_name'   => $s['full_name'],
            'grand_total' => floatval($total_res['grand_total'] ?? 0)
        ];
    }

    uasort($student_totals, fn($a, $b) => $b['grand_total'] <=> $a['grand_total']);

    $positions = [];
    $rank = 1;
    $i = 0;
    $prev_total = null;
    foreach($student_totals as $sid => $data) {
        $i++;
        if($prev_total !== null && $data['grand_total'] < $prev_total) {
            $rank = $i;
        }
        $positions[$sid] = $rank;
        $prev_total = $data['grand_total'];
    }

    function ordinal($n) {
        $s = ['th','st','nd','rd'];
        $v = $n % 100;
        return $n . ($s[($v-20)%10] ?? $s[min($v,3)]);
    }

    if(empty($student_totals)): ?>
    <div class="table-card">
        <div class="empty-state">
            <p style="font-size:40px;">📝</p>
            <p>No marks found for <?php echo htmlspecialchars($selected_class); ?> — <?php echo htmlspecialchars($selected_term); ?>.</p>
        </div>
    </div>
    <?php else: ?>

    <div class="table-card">
        <div class="table-header">
            <h2>📚 <?php echo htmlspecialchars($selected_class); ?> — <?php echo htmlspecialchars($selected_term); ?> Results</h2>
            <span style="font-size:13px; opacity:0.8;"><?php echo count($student_totals); ?> students</span>
        </div>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th class="left sticky-pos">Position</th>
                    <th class="left sticky-name">Student Name</th>
                    <?php foreach($subjects as $sub): ?>
                    <th><?php echo htmlspecialchars(substr($sub, 0, 4)); ?></th>
                    <?php endforeach; ?>
                    <th>Grand Total</th>
                    <th>Report Card</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($student_totals as $sid => $data):
                    $pos = $positions[$sid];
                    $pos_class = $pos == 1 ? 'position-1' : ($pos == 2 ? 'position-2' : ($pos == 3 ? 'position-3' : 'position-badge'));
                ?>
                <tr>
                    <td class="sticky-pos"><span class="<?php echo $pos_class; ?> position-badge"><?php echo htmlspecialchars(ordinal($pos)); ?></span></td>
                    <td class="left sticky-name"><b><?php echo htmlspecialchars($data['full_name']); ?></b></td>
                    <?php foreach($subjects as $sub):
                        $mark = $conn->query("SELECT total, grade FROM marks WHERE student_id='$sid' AND subject='$sub' AND term='$selected_term' AND academic_year='$academic_year'")->fetch_assoc();
                        $total = $mark['total'] ?? '--';
                        $grade = $mark['grade'] ?? '';
                    ?>
                    <td>
                        <?php if($grade): ?>
                        <span class="grade-badge grade-<?php echo (int)$grade; ?>"><?php echo htmlspecialchars($total); ?></span>
                        <?php else: ?>
                        <span style="color:#ccc;">--</span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                    <td><b><?php echo $data['grand_total'] > 0 ? htmlspecialchars($data['grand_total']) : '--'; ?></b></td>
                    <td>
                        <a href="report_card.php?student_id=<?php echo urlencode($sid); ?>&term=<?php echo urlencode($selected_term); ?>&class=<?php echo urlencode($selected_class); ?>"
                           class="btn-report" target="_blank" rel="noopener">🖨️ Print</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
        if (!on) {
            document.body.classList.remove('nav-open');
            var t = document.getElementById('menuToggle');
            if (t) t.setAttribute('aria-expanded', 'false');
            var o = document.getElementById('sidebarOverlay');
            if (o) o.setAttribute('aria-hidden', 'true');
        }
    }

    syncCompact();
    if (mq.addEventListener) mq.addEventListener('change', syncCompact);
    else if (mq.addListener) mq.addListener(syncCompact);

    var body = document.body;
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var toggle = document.getElementById('menuToggle');

    function setOpen(open) {
        if (!toggle || !sidebar) return;
        body.classList.toggle('nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (overlay) overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    function closeNav() { setOpen(false); }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (!document.documentElement.classList.contains('compact')) return;
            setOpen(!body.classList.contains('nav-open'));
        });
    }
    if (overlay) overlay.addEventListener('click', closeNav);
    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                if (document.documentElement.classList.contains('compact')) closeNav();
            });
        });
    }
    window.addEventListener('resize', syncCompact);
})();
</script>

</body>
</html>