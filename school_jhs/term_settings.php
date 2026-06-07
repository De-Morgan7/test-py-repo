<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$academic_year = get_active_year($conn);
$ay_esc        = mysqli_real_escape_string($conn, $academic_year);
$terms         = ['First Term', 'Second Term', 'Third Term'];
$success       = "";
$error         = "";

// Save term dates
if (isset($_POST['save_term'])) {
    $term       = mysqli_real_escape_string($conn, $_POST['term']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date   = mysqli_real_escape_string($conn, $_POST['end_date']);

    if ($start_date >= $end_date) {
        $error = "End date must be after start date.";
    } else {
        $conn->query("INSERT INTO term_settings (academic_year, term, start_date, end_date)
            VALUES ('$ay_esc', '$term', '$start_date', '$end_date')
            ON DUPLICATE KEY UPDATE start_date='$start_date', end_date='$end_date'");
        $success = "$term dates saved successfully!";
    }
}

// Add holiday
if (isset($_POST['add_holiday'])) {
    $hdate = mysqli_real_escape_string($conn, $_POST['holiday_date']);
    $hname = mysqli_real_escape_string($conn, trim($_POST['holiday_name']));
    if ($hdate && $hname) {
        $conn->query("INSERT IGNORE INTO school_holidays (holiday_date, holiday_name, academic_year)
            VALUES ('$hdate', '$hname', '$ay_esc')");
        $success = "Holiday '$hname' added successfully!";
    }
}

// Delete holiday
if (isset($_GET['delete_holiday'])) {
    $hid = (int)$_GET['delete_holiday'];
    $conn->query("DELETE FROM school_holidays WHERE id=$hid AND academic_year='$ay_esc'");
    header("Location: term_settings.php?deleted=1");
    exit();
}

// Get all term settings
$term_data = [];
foreach ($terms as $t) {
    $term_data[$t] = get_term_settings($conn, $academic_year, $t);
}

// Get holidays
$holidays = $conn->query("SELECT * FROM school_holidays WHERE academic_year='$ay_esc' ORDER BY holiday_date");

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BHIS Portal — Term Settings</title>
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

        .terms-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .term-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .term-card-header { padding: 14px 20px; color: white; font-size: 15px; font-weight: 600; }
        .term-1 { background: linear-gradient(135deg,#1a73e8,#0d47a1); }
        .term-2 { background: linear-gradient(135deg,#7b1fa2,#4a148c); }
        .term-3 { background: linear-gradient(135deg,#27ae60,#1e8449); }
        .term-card-body { padding: 20px; }

        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; float: right; }
        .status-active    { background: #e8f5e9; color: #2e7d32; }
        .status-concluded { background: #f5f5f5; color: #888; }
        .status-upcoming  { background: #fff8e1; color: #e67e22; }
        .status-notset    { background: #ffebee; color: #c62828; }

        .date-info { font-size: 13px; color: #555; margin-bottom: 15px; line-height: 1.8; }
        .date-info b { color: #333; }

        .form-group { margin-bottom: 14px; }
        .form-group label { font-size: 13px; font-weight: 600; color: #555; display: block; margin-bottom: 6px; }
        input[type="date"], input[type="text"] { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; min-height: 44px; }
        input:focus { border-color: #1a73e8; }
        .btn-save { width: 100%; padding: 12px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; min-height: 44px; }
        .btn-save:hover { background: #1557b0; }

        .success-msg { background: #e8f5e9; color: #2e7d32; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2e7d32; }
        .error-msg { background: #ffebee; color: #c62828; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c62828; }

        .holiday-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px; }
        .holiday-header { padding: 14px 20px; background: linear-gradient(135deg,#e53935,#b71c1c); color: white; font-size: 15px; font-weight: 600; }
        .holiday-body { padding: 20px; }
        .holiday-grid { display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px; align-items: end; margin-bottom: 20px; }
        .fixed-holidays { background: #fff8e1; border-radius: 8px; padding: 14px; font-size: 13px; color: #5d4037; margin-bottom: 15px; line-height: 1.8; }
        .holiday-list { display: flex; flex-direction: column; gap: 8px; }
        .holiday-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #fff5f5; border-radius: 8px; font-size: 14px; }
        .holiday-item .hdate { color: #c62828; font-weight: 600; }
        .holiday-item .hname { color: #333; flex: 1; margin: 0 12px; }
        .btn-del { padding: 5px 12px; background: #ffebee; color: #c62828; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; text-decoration: none; }
        .btn-del:hover { background: #e53935; color: white; }
        .btn-add { padding: 12px 20px; background: #e53935; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; min-height: 44px; white-space: nowrap; }
        .btn-add:hover { background: #b71c1c; }

        .info-box { background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 14px 18px; font-size: 13px; color: #1565c0; margin-bottom: 20px; line-height: 1.6; }

        @media screen and (max-width: 768px) {
            .holiday-grid { grid-template-columns: 1fr; }
            .terms-grid { grid-template-columns: 1fr; }
        }
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
        <a href="term_settings.php" class="nav-link active">📅 Term Settings</a>
        <a href="fees.php" class="nav-link">💰 Student Fees</a>
        <a href="fee_reports.php" class="nav-link">📈 Fee Reports</a>
        <a href="fee_student_payments.php" class="nav-link">📋 Payment History</a>
        <a href="process_promotions.php" class="nav-link">🔄 Process Promotions</a>
    </div>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>📅 Term Settings</h1>
        <span style="font-size:14px; color:#666;">Year: <b><?php echo $academic_year; ?></b></span>
    </div>

    <?php if($success): ?>
        <div class="success-msg">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="error-msg">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if(isset($_GET['deleted'])): ?>
        <div class="success-msg">✅ Holiday removed.</div>
    <?php endif; ?>

    <div class="info-box">
        ℹ️ Set the <b>start and end dates</b> for each term. Teachers can only mark attendance on <b>weekdays within the active term's dates</b>. Term 2 unlocks automatically after Term 1 ends, and Term 3 unlocks after Term 2 ends. Weekends and Ghana public holidays are excluded automatically.
    </div>

    <!-- Term Date Cards -->
    <div class="terms-grid">
        <?php foreach($terms as $index => $term):
            $td       = $term_data[$term];
            $colors   = ['term-1','term-2','term-3'];
            $color    = $colors[$index];

            if (!$td) {
                $status_label = 'Not Set';
                $status_class = 'status-notset';
            } elseif ($today < $td['start_date']) {
                $status_label = 'Upcoming';
                $status_class = 'status-upcoming';
            } elseif ($today > $td['end_date']) {
                $status_label = 'Concluded';
                $status_class = 'status-concluded';
            } else {
                $status_label = 'Active';
                $status_class = 'status-active';
            }
        ?>
        <div class="term-card">
            <div class="term-card-header <?php echo $color; ?>">
                📚 <?php echo $term; ?>
                <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
            </div>
            <div class="term-card-body">
                <?php if($td): ?>
                <div class="date-info">
                    <b>Start:</b> <?php echo date('F j, Y', strtotime($td['start_date'])); ?><br>
                    <b>End:</b> <?php echo date('F j, Y', strtotime($td['end_date'])); ?><br>
                    <?php if($today >= $td['start_date'] && $today <= $td['end_date']): ?>
                    <b style="color:#27ae60;">✅ Currently Active</b>
                    <?php elseif($today > $td['end_date']): ?>
                    <b style="color:#888;">✅ Concluded</b>
                    <?php else: ?>
                    <b style="color:#e67e22;">⏳ Starts in <?php echo (strtotime($td['start_date']) - strtotime($today)) / 86400; ?> days</b>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p style="color:#999; font-size:13px; margin-bottom:15px;">No dates set yet for this term.</p>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="term" value="<?php echo $term; ?>">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $td['start_date'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?php echo $td['end_date'] ?? ''; ?>" required>
                    </div>
                    <button type="submit" name="save_term" class="btn-save">💾 Save <?php echo $term; ?> Dates</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Ghana Public Holidays -->
    <div class="holiday-card">
        <div class="holiday-header">Ghana Public Holidays — <?php echo $academic_year; ?></div>
        <div class="holiday-body">

            <div class="fixed-holidays">
                <b>Automatically excluded fixed holidays:</b><br>
                📅 Jan 1 — New Year's Day &nbsp;|&nbsp;
                📅 Mar 6 — Independence Day &nbsp;|&nbsp;
                📅 May 1 — Workers' Day &nbsp;|&nbsp;
                📅 Jul 1 — Republic Day<br>
                📅 Aug 4 — Founders Day &nbsp;|&nbsp;
                📅 Sep 21 — Kwame Nkrumah Day &nbsp;|&nbsp;
                📅 Dec 25 — Christmas Day &nbsp;|&nbsp;
                📅 Dec 26 — Boxing Day
            </div>

            <p style="font-size:13px; font-weight:600; color:#555; margin-bottom:12px;">Add Variable Holidays (Easter, Eid, Farmers Day etc.):</p>
            <form method="POST">
                <div class="holiday-grid">
                    <div class="form-group" style="margin:0;">
                        <label>Holiday Date</label>
                        <input type="date" name="holiday_date" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Holiday Name</label>
                        <input type="text" name="holiday_name" placeholder="e.g. Good Friday" required>
                    </div>
                    <button type="submit" name="add_holiday" class="btn-add">➕ Add</button>
                </div>
            </form>

            <?php if($holidays && $holidays->num_rows > 0): ?>
            <div class="holiday-list">
                <?php while($h = $holidays->fetch_assoc()): ?>
                <div class="holiday-item">
                    <span class="hdate">📅 <?php echo date('F j, Y', strtotime($h['holiday_date'])); ?></span>
                    <span class="hname"><?php echo htmlspecialchars($h['holiday_name']); ?></span>
                    <a href="term_settings.php?delete_holiday=<?php echo $h['id']; ?>" class="btn-del"
                       onclick="return confirm('Remove this holiday?')">🗑️ Remove</a>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <p style="color:#999; font-size:13px; text-align:center; padding:20px;">No custom holidays added yet.</p>
            <?php endif; ?>
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