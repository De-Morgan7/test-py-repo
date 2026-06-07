<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$success = "";
$error = "";

if (isset($_POST['add_student'])) {
	$enrollment = mysqli_real_escape_string($conn, $_POST['date_of_enrollment']);
    // Auto-generate Student ID
$last = $conn->query("SELECT student_id FROM students ORDER BY id DESC LIMIT 1")->fetch_assoc();
if ($last) {
    $last_num = intval(substr($last['student_id'], -3));
    $new_num = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
} else {
    $new_num = '001';
}
$year = date('Y');
$student_id = "JHS" . $year . $new_num;
    $full_name    = mysqli_real_escape_string($conn, $_POST['full_name']);
    $gender       = mysqli_real_escape_string($conn, $_POST['gender']);
    $dob          = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    $class_name   = mysqli_real_escape_string($conn, $_POST['class_name']);
    $guardian     = mysqli_real_escape_string($conn, $_POST['guardian_name']);
    $phone        = mysqli_real_escape_string($conn, $_POST['guardian_phone']);

    // Check if student ID already exists
    $check = $conn->query("SELECT id FROM students WHERE student_id='$student_id'");
    if ($check->num_rows > 0) {
        $error = "Student ID already exists!";
    } else {
        $query = "INSERT INTO students (student_id, full_name, gender, date_of_birth, class_name, guardian_name, guardian_phone)
                  VALUES ('$student_id', '$full_name', '$gender', '$dob', '$class_name', '$guardian', '$phone')";
        if ($conn->query($query)) {
            $success = "Student added successfully!";
        } else {
            $error = "Something went wrong. Try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>BHIS Portal - Add Student</title>
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

        .form-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 700px;
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        input, select {
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            outline: none;
            transition: 0.2s;
            width: 100%;
            min-height: 44px;
        }
        input:focus, select:focus { border-color: #1a73e8; box-shadow: 0 0 0 3px rgba(26,115,232,0.1); }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 25px;
        }
        .btn-submit {
            padding: 14px 28px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
            min-height: 48px;
        }
        .btn-submit:hover { background: #1557b0; }
        .btn-back {
            padding: 14px 22px;
            background: #f0f2f5;
            color: #333;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
        }
        .success-msg { background: #e8f5e9; color: #2e7d32; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2e7d32; font-size: 14px; line-height: 1.4; }
        .error-msg { background: #ffebee; color: #c62828; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c62828; font-size: 14px; line-height: 1.4; }
        .section-divider {
            font-size: 13px;
            font-weight: 700;
            color: #1a73e8;
            text-transform: uppercase;
            margin: 20px 0 10px;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 8px;
            grid-column: 1 / -1;
        }

        @media screen and (max-width: 1024px) {
            .top-bar { padding: 14px 16px; }
            .top-bar h1 { font-size: 18px; }
            .form-card { padding: 18px 16px; max-width: none; }
            .form-grid { grid-template-columns: 1fr; gap: 16px; }
            .form-actions { flex-direction: column; align-items: stretch; }
            .form-actions .btn-submit,
            .form-actions .btn-back { width: 100%; margin: 0; }
        }

        @media screen and (min-width: 1025px) {
            body.nav-open { overflow: auto; }
            .sidebar-overlay { display: none !important; opacity: 0 !important; pointer-events: none !important; }
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
        <p><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></p>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Main</div>
        <a href="admin.php" class="nav-link"><span>📊</span> Dashboard</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Students</div>
        <a href="add_student.php" class="nav-link active"><span>➕</span> Add Student</a>
        <a href="view_students.php" class="nav-link"><span>👥</span> View Students</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Academics</div>
        <a href="view_marks.php" class="nav-link"><span>📝</span> View Marks</a>
        <a href="attendance_report.php" class="nav-link"><span>📋</span> Attendance Report</a>
        <a href="student_attendance_report.php" class="nav-link"><span>👤</span> Student Report</a>
    </div>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>➕ Add New Student</h1>
        <div style="font-size:14px; color:#666;"><?php echo date('F j, Y'); ?></div>
    </div>

    <div class="form-card">
        <?php if($success): ?>
            <div class="success-msg">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="error-msg">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-grid">
                <div class="section-divider">Student Information</div>

              <?php
$last_preview = $conn->query("SELECT student_id FROM students ORDER BY id DESC LIMIT 1")->fetch_assoc();
if ($last_preview) {
    $last_num_p = intval(substr($last_preview['student_id'], -3));
    $next_num = str_pad($last_num_p + 1, 3, '0', STR_PAD_LEFT);
} else {
    $next_num = '001';
}
$next_id = "JHS" . date('Y') . $next_num;
?>
<div class="form-group">
    <label>Student ID (Auto-generated)</label>
    <input type="text" name="student_id" value="<?php echo htmlspecialchars($next_id); ?>" readonly
    style="background:#f0f2f5; color:#888; cursor:not-allowed;">
</div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Enter full name" required autocomplete="name">
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" required>
                        <option value="">-- Select Gender --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth">
                </div>
				<div class="form-group">
    <label>Date of Admission</label>
    <input type="date" name="date_of_enrollment" required>
</div>
                <div class="form-group">
                    <label>Class</label>
                    <select name="class_name" required>
                        <option value="">-- Select Class --</option>
                        <option value="JHS 1">JHS 1</option>
                        <option value="JHS 2">JHS 2</option>
                        <option value="JHS 3">JHS 3</option>
                    </select>
                </div>

                <div class="section-divider">Guardian Information</div>

                <div class="form-group">
                    <label>Guardian Name</label>
                    <input type="text" name="guardian_name" placeholder="Enter guardian name" autocomplete="name">
                </div>
                <div class="form-group">
                    <label>Guardian Phone</label>
                    <input type="tel" name="guardian_phone" placeholder="e.g. 0244123456" inputmode="tel" autocomplete="tel">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="add_student" class="btn-submit">➕ Add Student</button>
                <a href="view_students.php" class="btn-back">👥 View All Students</a>
            </div>
        </form>
    </div>
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