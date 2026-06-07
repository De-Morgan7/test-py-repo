<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

function username_taken($conn, $username, $exclude_id)
{
    if ($username === '') {
        return false;
    }
    $u = mysqli_real_escape_string($conn, $username);
    $ex = (int)$exclude_id;
    $q = $conn->query("SELECT id FROM users WHERE username='$u' AND id != $ex LIMIT 1");
    return $q && $q->num_rows > 0;
}

$success = '';
$error = '';
$uid = (int)$_SESSION['user_id'];

if (isset($_POST['update_admin_login'])) {
    $row = $conn->query("SELECT * FROM users WHERE id='$uid' AND role='Admin' LIMIT 1")->fetch_assoc();
    if (!$row) {
        $error = 'Could not load your admin account.';
    } else {
        $new_username = trim($_POST['admin_new_username'] ?? '');
        $new_pass = $_POST['admin_new_password'] ?? '';
        $confirm = $_POST['admin_confirm_password'] ?? '';

        if ($new_username === '' && $new_pass === '' && $confirm === '') {
            $error = 'Enter a new username and/or password to update.';
        } else {
            $err_local = '';
            $uname = mysqli_real_escape_string($conn, $row['username']);
            if ($new_username !== '') {
                if (username_taken($conn, $new_username, $uid)) {
                    $err_local = 'That username is already in use.';
                } else {
                    $uname = mysqli_real_escape_string($conn, $new_username);
                }
            }
            $pass_esc = mysqli_real_escape_string($conn, $row['password']);
            if ($err_local === '' && ($new_pass !== '' || $confirm !== '')) {
                if ($new_pass !== $confirm) {
                    $err_local = 'New password and confirmation do not match.';
                } else {
                    $pass_esc = mysqli_real_escape_string($conn, $new_pass);
                }
            }
            if ($err_local === '') {
                $conn->query("UPDATE users SET username='$uname', password='$pass_esc' WHERE id='$uid'");
                $success = 'Your admin login was updated successfully.';
            } else {
                $error = $err_local;
            }
        }
    }
}

if (isset($_POST['update_teacher_login'])) {
    $tid = (int)($_POST['teacher_id'] ?? 0);
    $row = $conn->query("SELECT * FROM users WHERE id='$tid' AND role='Teacher' LIMIT 1")->fetch_assoc();
    if (!$row) {
        $error = 'Please select a valid teacher.';
    } else {
        $new_username = trim($_POST['teacher_new_username'] ?? '');
        $new_pass = $_POST['teacher_new_password'] ?? '';
        $confirm = $_POST['teacher_confirm_password'] ?? '';

        if ($new_username === '' && $new_pass === '' && $confirm === '') {
            $error = 'Enter a new username and/or password for the teacher.';
        } else {
            $err_local = '';
            $uname = mysqli_real_escape_string($conn, $row['username']);
            if ($new_username !== '') {
                if (username_taken($conn, $new_username, $tid)) {
                    $err_local = 'That username is already in use.';
                } else {
                    $uname = mysqli_real_escape_string($conn, $new_username);
                }
            }
            $pass_esc = mysqli_real_escape_string($conn, $row['password']);
            if ($err_local === '' && ($new_pass !== '' || $confirm !== '')) {
                if ($new_pass !== $confirm) {
                    $err_local = 'New password and confirmation do not match.';
                } else {
                    $pass_esc = mysqli_real_escape_string($conn, $new_pass);
                }
            }
            if ($err_local === '') {
                $conn->query("UPDATE users SET username='$uname', password='$pass_esc' WHERE id='$tid'");
                $success = 'Teacher login was updated successfully.';
            } else {
                $error = $err_local;
            }
        }
    }
}

$admin_me = $conn->query("SELECT username, full_name FROM users WHERE id='$uid' LIMIT 1")->fetch_assoc();
$teachers_result = $conn->query("SELECT id, full_name, username, assigned_class FROM users WHERE role='Teacher' ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>BHIS Portal - Manage logins</title>
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
            margin-bottom: 24px;
        }
        .form-card h2 { font-size: 17px; color: #333; margin-bottom: 8px; }
        .hint { font-size: 13px; color: #666; margin-bottom: 18px; line-height: 1.4; }
        .current-login { font-size: 14px; color: #1a73e8; margin-bottom: 16px; font-weight: 600; }
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
        .success-msg { background: #e8f5e9; color: #2e7d32; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2e7d32; font-size: 14px; line-height: 1.4; }
        .error-msg { background: #ffebee; color: #c62828; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c62828; font-size: 14px; line-height: 1.4; }

        @media screen and (max-width: 1024px) {
            .top-bar { padding: 14px 16px; }
            .top-bar h1 { font-size: 18px; }
            .form-card { padding: 18px 16px; max-width: none; }
            .form-grid { grid-template-columns: 1fr; gap: 16px; }
            .form-actions { flex-direction: column; align-items: stretch; }
            .form-actions .btn-submit { width: 100%; margin: 0; }
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
        <a href="add_student.php" class="nav-link"><span>➕</span> Add Student</a>
        <a href="view_students.php" class="nav-link"><span>👥</span> View Students</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Academics</div>
        <a href="view_marks.php" class="nav-link"><span>📝</span> View Marks</a>
        <a href="attendance_report.php" class="nav-link"><span>📋</span> Attendance Report</a>
        <a href="student_attendance_report.php" class="nav-link"><span>👤</span> Student Report</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Administration</div>
        <a href="manage_users.php" class="nav-link active"><span>🔑</span> Manage logins</a>
        <a href="fees.php" class="nav-link"><span>💰</span> Student fees</a>
        <a href="feeding_fees.php" class="nav-link"><span>🍽</span> Daily feeding</a>
        <a href="feeding_report.php" class="nav-link"><span>📅</span> Feeding by date</a>
        <a href="fee_reports.php" class="nav-link"><span>📈</span> Fee reports</a>
    </div>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>🔑 Manage logins</h1>
        <div style="font-size:14px; color:#666;"><?php echo date('F j, Y'); ?></div>
    </div>

    <?php if ($success): ?>
        <div class="success-msg">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="form-card">
        <h2>Your admin login</h2>
        <p class="hint">Change your own username and/or password. Leave a field blank to keep the current value.</p>
        <?php if ($admin_me): ?>
            <p class="current-login">Current username: <?php echo htmlspecialchars($admin_me['username']); ?></p>
        <?php endif; ?>
        <form method="post">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="admin_new_username">New username (optional)</label>
                    <input type="text" id="admin_new_username" name="admin_new_username" autocomplete="username" placeholder="Leave blank to keep current">
                </div>
                <div class="form-group">
                    <label for="admin_new_password">New password (optional)</label>
                    <input type="password" id="admin_new_password" name="admin_new_password" autocomplete="new-password" placeholder="Leave blank to keep current">
                </div>
                <div class="form-group">
                    <label for="admin_confirm_password">Confirm new password</label>
                    <input type="password" id="admin_confirm_password" name="admin_confirm_password" autocomplete="new-password" placeholder="Required if changing password">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="update_admin_login" value="1" class="btn-submit">Save admin login</button>
            </div>
        </form>
    </div>

    <div class="form-card">
        <h2>Teacher logins</h2>
        <p class="hint">Reset a teacher&rsquo;s username and/or password. Only users with the Teacher role appear here.</p>
        <form method="post">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="teacher_id">Teacher</label>
                    <select id="teacher_id" name="teacher_id" required>
                        <option value="">— Select teacher —</option>
                        <?php
                        if ($teachers_result) {
                            while ($t = $teachers_result->fetch_assoc()) {
                                $label = htmlspecialchars($t['full_name'] . ' (' . $t['assigned_class'] . ') — ' . $t['username']);
                                echo '<option value="' . (int)$t['id'] . '">' . $label . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label for="teacher_new_username">New username (optional)</label>
                    <input type="text" id="teacher_new_username" name="teacher_new_username" autocomplete="off" placeholder="Leave blank to keep current">
                </div>
                <div class="form-group">
                    <label for="teacher_new_password">New password (optional)</label>
                    <input type="password" id="teacher_new_password" name="teacher_new_password" autocomplete="new-password" placeholder="Leave blank to keep current">
                </div>
                <div class="form-group">
                    <label for="teacher_confirm_password">Confirm new password</label>
                    <input type="password" id="teacher_confirm_password" name="teacher_confirm_password" autocomplete="new-password" placeholder="Required if changing password">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="update_teacher_login" value="1" class="btn-submit">Save teacher login</button>
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
