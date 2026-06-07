<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: login.php");
    exit();
}

$teacher_class = $_SESSION['assigned_class'];
$teacher_id    = $_SESSION['user_id'];
$terms         = ['First Term', 'Second Term', 'Third Term'];
$academic_year = get_active_year($conn);

$next_class_map = [
    'JHS 1' => 'JHS 2',
    'JHS 2' => 'JHS 3',
    'JHS 3' => 'Graduated'
];
$next_class = $next_class_map[$teacher_class] ?? '';

$selected_term = isset($_GET['term']) ? mysqli_real_escape_string($conn, $_GET['term']) : 'Third Term';
$success = "";
$error   = "";

// Save promotion status + remarks
if (isset($_POST['save_promotions'])) {
    $term = mysqli_real_escape_string($conn, $_POST['term']);
    $ay   = mysqli_real_escape_string($conn, $academic_year);

    foreach ($_POST['students'] as $sid => $data) {
        $sid    = mysqli_real_escape_string($conn, $sid);
        $status = mysqli_real_escape_string($conn, $data['status'] ?? 'Promoted');
        $remark = mysqli_real_escape_string($conn, trim($data['remark'] ?? ''));

        // Determine next class
        if ($status === 'Promoted') {
            $nc = mysqli_real_escape_string($conn, $next_class);
        } elseif ($status === 'Graduated') {
            $nc = 'Graduated';
        } else {
            $nc = mysqli_real_escape_string($conn, $teacher_class);
        }

        // Save promotion
        $conn->query("INSERT INTO promotions (student_id, academic_year, status, current_class, next_class, marked_by)
            VALUES ('$sid', '$ay', '$status', '$teacher_class', '$nc', '$teacher_id')
            ON DUPLICATE KEY UPDATE status='$status', next_class='$nc', marked_by='$teacher_id'");

        // Save remark
        if ($remark !== '') {
            $conn->query("INSERT INTO student_remarks (student_id, academic_year, term, remark, teacher_id)
                VALUES ('$sid', '$ay', '$term', '$remark', '$teacher_id')
                ON DUPLICATE KEY UPDATE remark='$remark', teacher_id='$teacher_id'");
        }
    }
    $success = "Promotion status and remarks saved successfully!";
}

$students = $conn->query("SELECT * FROM students WHERE class_name='$teacher_class' AND status='Active' ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BHIS Portal - Promotion & Remarks</title>
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

        .main { margin-left: 250px; padding: 30px; width: calc(100% - 250px); min-width: 0; }

        @media screen and (max-width: 1024px) {
            html.compact .main { margin-left: 0 !important; width: 100% !important; padding: 14px !important; }
            html.compact .sidebar { transform: translate3d(-100%,0,0) !important; box-shadow: none; }
            html.compact body.nav-open .sidebar { transform: translate3d(0,0,0) !important; box-shadow: 4px 0 24px rgba(0,0,0,0.12); }
        }

        .mobile-header { display: none; background: #1a73e8; color: white; padding: 12px 14px; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; gap: 12px; }
        html.compact .mobile-header { display: flex; }
        .mobile-header span { font-weight: 600; font-size: 15px; }
        .menu-toggle { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.6); color: white; padding: 10px 14px; border-radius: 8px; cursor: pointer; font-weight: 600; min-height: 44px; }

        .top-bar { background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .top-bar h1 { font-size: 20px; color: #333; }

        .info-bar { background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 12px 18px; font-size: 13px; color: #1565c0; margin-bottom: 20px; line-height: 1.5; }

        .filter-card { background: white; padding: 20px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; max-width: 300px; }
        .filter-group label { font-size: 13px; font-weight: 600; color: #555; }
        select, textarea { padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; background: white; }
        select:focus, textarea:focus { border-color: #1a73e8; }

        /* Student cards — mobile-first */
        .students-grid { display: flex; flex-direction: column; gap: 16px; }
        .student-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .student-card-header { padding: 14px 18px; background: linear-gradient(135deg,#1a73e8,#0d47a1); color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .student-card-header .name { font-size: 15px; font-weight: 600; }
        .student-card-header .sid { font-size: 12px; opacity: 0.8; }
        .student-card-body { padding: 18px; }

        .promotion-options { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .promo-btn { flex: 1; min-width: 100px; padding: 10px 8px; border: 2px solid #ddd; border-radius: 8px; background: white; cursor: pointer; font-size: 13px; font-weight: 600; text-align: center; transition: 0.2s; color: #555; }
        .promo-btn:hover { border-color: #1a73e8; }
        .promo-btn.selected-promoted { background: #e8f5e9; border-color: #27ae60; color: #2e7d32; }
        .promo-btn.selected-repeated { background: #fff8e1; border-color: #f39c12; color: #e67e22; }
        .promo-btn.selected-graduated { background: #e3f2fd; border-color: #1a73e8; color: #1565c0; }

        .remark-group label { font-size: 13px; font-weight: 600; color: #555; display: block; margin-bottom: 6px; }
        .remark-group textarea { width: 100%; min-height: 80px; resize: vertical; font-size: 14px; }

        .quick-remarks { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
        .quick-remark-btn { padding: 5px 12px; border: 1px solid #ddd; border-radius: 20px; background: white; font-size: 12px; cursor: pointer; transition: 0.2s; color: #555; }
        .quick-remark-btn:hover { background: #e3f2fd; border-color: #1a73e8; color: #1a73e8; }

        .current-status { font-size: 12px; margin-top: 10px; padding: 6px 12px; border-radius: 20px; display: inline-block; }
        .status-promoted { background: #e8f5e9; color: #2e7d32; }
        .status-repeated { background: #fff8e1; color: #e67e22; }
        .status-graduated { background: #e3f2fd; color: #1565c0; }
        .status-none { background: #f5f5f5; color: #888; }

        .btn-save { width: 100%; padding: 15px; background: #27ae60; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 20px; min-height: 48px; }
        .btn-save:hover { background: #219a52; }

        .success-msg { background: #e8f5e9; color: #2e7d32; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2e7d32; }
        .error-msg { background: #ffebee; color: #c62828; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c62828; }

        .progress-bar-wrap { background: #f0f2f5; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; }
        .progress-bar-wrap .label { font-size: 13px; font-weight: 600; color: #555; margin-bottom: 8px; }
        .progress-bar { background: #ddd; border-radius: 20px; height: 10px; overflow: hidden; }
        .progress-fill { background: #27ae60; height: 100%; border-radius: 20px; transition: width 0.3s; }
        .progress-text { font-size: 12px; color: #888; margin-top: 6px; }

        @media screen and (min-width: 700px) {
            .students-grid { display: grid; grid-template-columns: 1fr 1fr; }
        }
        @media screen and (min-width: 1025px) {
            body.nav-open { overflow: auto; }
            .sidebar-overlay { display: none !important; }
            .students-grid { grid-template-columns: 1fr 1fr; }
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
        <p>Teacher — <?php echo $teacher_class; ?></p>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">My Class</div>
        <a href="admin.php" class="nav-link">📊 Dashboard</a>
        <a href="attendance.php" class="nav-link">✅ Mark Attendance</a>
        <a href="marks.php" class="nav-link">📝 Enter Marks</a>
        <a href="view_marks.php" class="nav-link">📊 View Marks</a>
        <a href="attendance_report.php" class="nav-link">📋 Attendance Report</a>
        <a href="view_students.php" class="nav-link">👥 View Students</a>
        <a href="promotion.php" class="nav-link active">🎓 Promotion & Remarks</a>
    </div>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>🎓 Promotion & Remarks</h1>
        <span style="font-size:14px; color:#666;">Class: <b><?php echo $teacher_class; ?></b> | Year: <b><?php echo $academic_year; ?></b></span>
    </div>

    <?php if($success): ?>
        <div class="success-msg">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="error-msg">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>

   <div class="info-bar">
    <?php if($selected_term === 'Third Term'): ?>
        ℹ️ <b>Third Term:</b> Mark each student's promotion status AND add a behavioral remark. Both will appear on the report card.
        <?php if($teacher_class === 'JHS 3'): ?>
        <br><b>JHS 3 students:</b> Select <b>Graduated</b> for those completing school.
        <?php else: ?>
        <br>Promoted students will move to <b><?php echo $next_class; ?></b> in the new academic year.
        <?php endif; ?>
    <?php else: ?>
        ℹ️ <b><?php echo $selected_term; ?>:</b> Add behavioral remarks for each student. These will appear on their report card. Promotion status is only available in Third Term.
    <?php endif; ?>
</div>

    <!-- Term Filter -->
    <div class="filter-card">
        <div class="filter-group">
            <label>📅 Select Term</label>
            <select onchange="window.location='promotion.php?term='+this.value">
                <?php foreach($terms as $t): ?>
                <option value="<?php echo $t; ?>" <?php echo $selected_term==$t?'selected':''; ?>><?php echo $t; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Progress Tracker -->
    <?php
    $total_students = $students->num_rows;
    $marked_count = 0;
    if($total_students > 0) {
        $mc = $conn->query("SELECT COUNT(*) as c FROM promotions WHERE academic_year='$academic_year' AND current_class='$teacher_class'");
        $marked_count = (int)$mc->fetch_assoc()['c'];
    }
    $progress_pct = $total_students > 0 ? round(($marked_count / $total_students) * 100) : 0;
    ?>
    <div class="progress-bar-wrap">
        <div class="label">📋 Promotion Progress</div>
        <div class="progress-bar">
            <div class="progress-fill" style="width:<?php echo $progress_pct; ?>%"></div>
        </div>
        <div class="progress-text"><?php echo $marked_count; ?> of <?php echo $total_students; ?> students marked (<?php echo $progress_pct; ?>%)</div>
    </div>

    <form method="POST">
        <input type="hidden" name="term" value="<?php echo htmlspecialchars($selected_term); ?>">

        <div class="students-grid">
        <?php
        $students->data_seek(0);
        while($row = $students->fetch_assoc()):
            $sid = $row['student_id'];

            // Get existing promotion
            $existing_promo = $conn->query("SELECT * FROM promotions WHERE student_id='$sid' AND academic_year='$academic_year'")->fetch_assoc();
            $existing_status = $existing_promo['status'] ?? '';

            // Get existing remark
            $sel_term_esc = mysqli_real_escape_string($conn, $selected_term);
            $existing_remark = $conn->query("SELECT remark FROM student_remarks WHERE student_id='$sid' AND academic_year='$academic_year' AND term='$sel_term_esc'")->fetch_assoc();
            $remark_text = $existing_remark['remark'] ?? '';
        ?>
        <div class="student-card">
            <div class="student-card-header">
                <div>
                    <div class="name"><?php echo htmlspecialchars($row['full_name']); ?></div>
                    <div class="sid">ID: <?php echo htmlspecialchars($sid); ?></div>
                </div>
                <?php if($existing_status): ?>
                <span class="current-status status-<?php echo strtolower($existing_status); ?>">
                    <?php
                    if($existing_status === 'Promoted') echo '✅ ' . $existing_status . ' → ' . $next_class;
                    elseif($existing_status === 'Repeated') echo '🔄 ' . $existing_status;
                    else echo '🎓 ' . $existing_status;
                    ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="student-card-body">

               <!-- Promotion Status — Third Term ONLY -->
<?php if($selected_term === 'Third Term'): ?>
<div style="margin-bottom:12px;">
    <div style="font-size:13px; font-weight:600; color:#555; margin-bottom:8px;">
        Promotion Status:
    </div>
    <div class="promotion-options">
        <button type="button"
            class="promo-btn <?php echo $existing_status==='Promoted'?'selected-promoted':''; ?>"
            onclick="selectStatus('<?php echo $sid; ?>', 'Promoted', this)">
            ✅ Promoted<br>
            <span style="font-size:11px; font-weight:400;">→ <?php echo $next_class !== 'Graduated' ? $next_class : 'Graduates'; ?></span>
        </button>
        <button type="button"
            class="promo-btn <?php echo $existing_status==='Repeated'?'selected-repeated':''; ?>"
            onclick="selectStatus('<?php echo $sid; ?>', 'Repeated', this)">
            🔄 Repeated<br>
            <span style="font-size:11px; font-weight:400;">Stays in <?php echo $teacher_class; ?></span>
        </button>
        <?php if($teacher_class === 'JHS 3'): ?>
        <button type="button"
            class="promo-btn <?php echo $existing_status==='Graduated'?'selected-graduated':''; ?>"
            onclick="selectStatus('<?php echo $sid; ?>', 'Graduated', this)">
            🎓 Graduated<br>
            <span style="font-size:11px; font-weight:400;">Leaves school</span>
        </button>
        <?php endif; ?>
    </div>
    <input type="hidden" name="students[<?php echo $sid; ?>][status]"
        id="status_<?php echo $sid; ?>"
        value="<?php echo htmlspecialchars($existing_status ?: 'Promoted'); ?>">
</div>
<?php else: ?>
<div style="background:#fff8e1; border:1px solid #f39c12; border-radius:8px; padding:10px 14px; margin-bottom:12px; font-size:13px; color:#e67e22;">
    ℹ️ Promotion status is only available in <b>Third Term</b>. Add remarks below.
</div>
<input type="hidden" name="students[<?php echo $sid; ?>][status]" value="">
<?php endif; ?>

                <!-- Behavioral Remark -->
                <div class="remark-group">
                    <label>📝 Behavioral Remark (<?php echo $selected_term; ?>):</label>
                    <div class="quick-remarks">
                        <button type="button" class="quick-remark-btn" onclick="setRemark('<?php echo $sid; ?>', 'Excellent behavior and conduct')">😊 Excellent</button>
                        <button type="button" class="quick-remark-btn" onclick="setRemark('<?php echo $sid; ?>', 'Good conduct, can improve in studies')">📚 Can Improve</button>
                        <button type="button" class="quick-remark-btn" onclick="setRemark('<?php echo $sid; ?>', 'Talented in Sports and Music')">⚽ Sports & Music</button>
                        <button type="button" class="quick-remark-btn" onclick="setRemark('<?php echo $sid; ?>', 'Highly energetic and participative')">⚡ Energetic</button>
                        <button type="button" class="quick-remark-btn" onclick="setRemark('<?php echo $sid; ?>', 'Needs to improve in discipline')">⚠️ Discipline</button>
                        <button type="button" class="quick-remark-btn" onclick="setRemark('<?php echo $sid; ?>', 'Very hardworking and dedicated')">💪 Hardworking</button>
                        <button type="button" class="quick-remark-btn" onclick="setRemark('<?php echo $sid; ?>', 'Brilliant and academically outstanding')">🌟 Outstanding</button>
                    </div>
                    <textarea name="students[<?php echo $sid; ?>][remark]"
                        id="remark_<?php echo $sid; ?>"
                        placeholder="Type a remark or click a quick option above..."><?php echo htmlspecialchars($remark_text); ?></textarea>
                </div>

            </div>
        </div>
        <?php endwhile; ?>
        </div>

        <button type="submit" name="save_promotions" class="btn-save">💾 Save All Promotions & Remarks</button>
    </form>
</div>

<script>
function selectStatus(sid, status, btn) {
    // Remove all selected classes from siblings
    var options = btn.closest('.promotion-options').querySelectorAll('.promo-btn');
    options.forEach(function(b) {
        b.classList.remove('selected-promoted', 'selected-repeated', 'selected-graduated');
    });
    // Add selected class
    if (status === 'Promoted') btn.classList.add('selected-promoted');
    else if (status === 'Repeated') btn.classList.add('selected-repeated');
    else if (status === 'Graduated') btn.classList.add('selected-graduated');
    // Set hidden input
    document.getElementById('status_' + sid).value = status;
}

function setRemark(sid, text) {
    document.getElementById('remark_' + sid).value = text;
}

// Sidebar toggle
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