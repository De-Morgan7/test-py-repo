<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: login.php");
    exit();
}

$teacher_class = $_SESSION['assigned_class'];
$today         = date('Y-m-d');
$academic_year = get_active_year($conn);
$terms         = ['First Term', 'Second Term', 'Third Term'];
$ay_esc        = mysqli_real_escape_string($conn, $academic_year);
$class_esc     = mysqli_real_escape_string($conn, $teacher_class);

// Get selected term
$selected_term = isset($_GET['term']) ? $_GET['term'] : '';
if (!in_array($selected_term, $terms)) $selected_term = 'First Term';
$term_esc = mysqli_real_escape_string($conn, $selected_term);

// Check if teacher can mark attendance for selected term
$check = can_mark_attendance($conn, $academic_year, $selected_term);
$can_mark = $check['allowed'];
$block_reason = $check['reason'];

// Check if already marked today for this term
$already_taken = false;
if ($can_mark) {
    $already_taken = (int)$conn->query("SELECT COUNT(*) as c FROM attendance WHERE class_name='$class_esc' AND attendance_date='$today' AND term='$term_esc' AND academic_year='$ay_esc'")->fetch_assoc()['c'] > 0;
}

$success = "";

// Save attendance
if ($can_mark && !$already_taken && isset($_POST['submit_attendance'])) {
    $posted_term = mysqli_real_escape_string($conn, $_POST['selected_term'] ?? $selected_term);
    foreach ($_POST['status'] as $student_id => $status) {
        $sid = mysqli_real_escape_string($conn, $student_id);
        $st  = mysqli_real_escape_string($conn, $status);
        $conn->query("INSERT INTO attendance (student_id, class_name, status, attendance_date, term, academic_year)
            VALUES ('$sid', '$class_esc', '$st', '$today', '$posted_term', '$ay_esc')");
    }
    $already_taken = true;
    $success = "✅ Attendance saved for $selected_term!";
}

$students = $conn->query("SELECT * FROM students WHERE class_name='$class_esc' AND status='Active' ORDER BY full_name");

// Get term date info for display
$term_info = get_term_settings($conn, $academic_year, $selected_term);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Beverly Hills International School - Mark Attendance</title>
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

        .term-selector { background: white; padding: 20px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .term-selector h3 { font-size: 14px; font-weight: 600; color: #555; margin-bottom: 12px; }
        .term-pills { display: flex; gap: 10px; flex-wrap: wrap; }
        .term-pill { padding: 10px 20px; border-radius: 20px; border: 2px solid #ddd; text-decoration: none; font-size: 14px; font-weight: 600; color: #555; background: white; transition: 0.2s; position: relative; }
        .term-pill:hover { border-color: #1a73e8; }
        .term-pill.active-first  { background: #e3f2fd; border-color: #1a73e8; color: #1a73e8; }
        .term-pill.active-second { background: #f3e5f5; border-color: #7b1fa2; color: #7b1fa2; }
        .term-pill.active-third  { background: #e8f5e9; border-color: #27ae60; color: #27ae60; }
        .term-pill.locked { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
        .lock-icon { font-size: 11px; }

        .term-dates { font-size: 12px; color: #888; margin-top: 10px; }

        .block-box { border-radius: 12px; padding: 25px; text-align: center; margin-bottom: 20px; }
        .block-box.weekend  { background: #f3e5f5; border: 2px solid #7b1fa2; }
        .block-box.holiday  { background: #fff8e1; border: 2px solid #f39c12; }
        .block-box.locked   { background: #ffebee; border: 2px solid #e53935; }
        .block-box.notset   { background: #e3f2fd; border: 2px solid #1a73e8; }
        .block-box.done     { background: #fff8e1; border: 2px solid #f39c12; }
        .block-box .icon { font-size: 45px; margin-bottom: 10px; }
        .block-box h2 { font-size: 18px; margin-bottom: 8px; }
        .block-box p { font-size: 14px; color: #666; }

        .info-bar { background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 12px 18px; font-size: 13px; color: #1565c0; margin-bottom: 20px; }

        .table-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .table-header { padding: 14px 20px; background: linear-gradient(135deg,#1a73e8,#0d47a1); color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .table-header h2 { font-size: 15px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 400px; }
        thead tr { background: #f8f9fa; }
        th { padding: 12px 16px; text-align: left; font-size: 13px; color: #555; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 11px 16px; font-size: 14px; color: #333; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        tr:hover { background: #fafafa; }

        .radio-group { display: flex; gap: 15px; flex-wrap: wrap; }
        .radio-label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 14px; padding: 6px 14px; border-radius: 20px; border: 1px solid #ddd; transition: 0.2s; }
        .radio-label.present { border-color: #27ae60; color: #27ae60; }
        .radio-label.absent  { border-color: #e53935; color: #e53935; }

        .mark-all { display: flex; gap: 10px; padding: 14px 18px; background: #f8f9fa; border-bottom: 1px solid #eee; flex-wrap: wrap; align-items: center; }
        .mark-all span { font-size: 13px; font-weight: 600; color: #555; }
        .btn-mark { padding: 7px 16px; border: none; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: 600; transition: 0.2s; }
        .btn-mark-present { background: #e8f5e9; color: #27ae60; }
        .btn-mark-present:hover { background: #27ae60; color: white; }
        .btn-mark-absent { background: #ffebee; color: #e53935; }
        .btn-mark-absent:hover { background: #e53935; color: white; }

        .btn-save { width: 100%; padding: 15px; background: #27ae60; color: white; border: none; border-radius: 0 0 12px 12px; font-size: 16px; font-weight: bold; cursor: pointer; min-height: 48px; }
        .btn-save:hover { background: #219a52; }

        .success-msg { background: #e8f5e9; color: #2e7d32; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2e7d32; }
        .badge-present { padding: 5px 14px; border-radius: 20px; background: #e8f5e9; color: #2e7d32; font-size: 13px; font-weight: 600; }
        .badge-absent  { padding: 5px 14px; border-radius: 20px; background: #ffebee; color: #c62828; font-size: 13px; font-weight: 600; }

        .stats-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
        .stat-pill { padding: 10px 18px; border-radius: 20px; font-size: 14px; font-weight: 600; }
        .stat-pill.blue  { background: #e3f2fd; color: #1565c0; }
        .stat-pill.green { background: #e8f5e9; color: #2e7d32; }
        .stat-pill.red   { background: #ffebee; color: #c62828; }
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
        <a href="attendance.php" class="nav-link active">✅ Mark Attendance</a>
        <a href="marks.php" class="nav-link">📝 Enter Marks</a>
        <a href="attendance_report.php" class="nav-link">📋 Attendance Report</a>
        <a href="view_marks.php" class="nav-link">📊 View Marks</a>
        <a href="view_students.php" class="nav-link">👥 View Students</a>
        <a href="promotion.php" class="nav-link">🎓 Promotion & Remarks</a>
    </div>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>✅ Mark Attendance</h1>
        <span style="font-size:14px; color:#666;">
            📅 <?php echo date('l, F j, Y'); ?> | <b><?php echo $academic_year; ?></b>
        </span>
    </div>

    <?php if($success): ?>
        <div class="success-msg"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Term Selector -->
    <div class="term-selector">
        <h3>📅 Select Term:</h3>
        <div class="term-pills">
            <?php
            $terms_order = ['First Term' => 1, 'Second Term' => 2, 'Third Term' => 3];
            foreach($terms as $t):
                $tc       = 'term-pill';
                $t_info   = get_term_settings($conn, $academic_year, $t);
                $is_locked = false;
                $lock_msg  = '';

                // Check if this term is locked
                $t_num = $terms_order[$t];
                if ($t_num > 1) {
                    $prev_term = array_search($t_num - 1, $terms_order);
                    $prev_info = get_term_settings($conn, $academic_year, $prev_term);
                    if (!$prev_info || $today <= $prev_info['end_date']) {
                        $is_locked = true;
                        $lock_msg  = '🔒';
                    }
                }
                if (!$t_info) $is_locked = true;

                if ($t === $selected_term) {
                    if($t === 'First Term') $tc .= ' active-first';
                    elseif($t === 'Second Term') $tc .= ' active-second';
                    else $tc .= ' active-third';
                }
                if($is_locked) $tc .= ' locked';
            ?>
            <a href="attendance.php?term=<?php echo urlencode($t); ?>"
               class="<?php echo $tc; ?>"
               <?php echo $is_locked ? 'title="Previous term not concluded yet"' : ''; ?>>
                <?php echo $lock_msg; ?> <?php echo $t; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if($term_info): ?>
        <div class="term-dates">
            📅 <?php echo $selected_term; ?>: <b><?php echo date('F j', strtotime($term_info['start_date'])); ?></b>
            to <b><?php echo date('F j, Y', strtotime($term_info['end_date'])); ?></b>
        </div>
        <?php endif; ?>
    </div>

    <?php if(!$can_mark): ?>
    <!-- BLOCKED -->
    <?php
    $box_class = 'notset';
    $icon = '📅';
    $title = 'Attendance Locked';
    if (is_weekend($today)) { $box_class = 'weekend'; $icon = '🏖️'; $title = 'Weekend — No School'; }
    elseif (is_ghana_holiday($conn, $today, $academic_year)) { $box_class = 'holiday'; $icon = '🇬🇭'; $title = 'Public Holiday'; }
    elseif (!$term_info) { $box_class = 'notset'; $icon = '⚙️'; $title = 'Term Dates Not Set'; }
    elseif ($today > $term_info['end_date']) { $box_class = 'locked'; $icon = '🔒'; $title = 'Term Concluded'; }
    elseif ($today < $term_info['start_date']) { $box_class = 'locked'; $icon = '⏳'; $title = 'Term Not Started Yet'; }
    else { $box_class = 'locked'; $icon = '🔒'; $title = 'Attendance Locked'; }
    ?>
    <div class="block-box <?php echo $box_class; ?>">
        <div class="icon"><?php echo $icon; ?></div>
        <h2><?php echo $title; ?></h2>
        <p><?php echo $block_reason; ?></p>
        <?php if(!$term_info): ?>
        <p style="margin-top:10px; font-size:13px;">Ask the admin to set term dates in <b>Term Settings</b>.</p>
        <?php endif; ?>
    </div>

    <?php elseif($already_taken && !$success): ?>
    <!-- Already marked today -->
    <div class="block-box done">
        <div class="icon">🔒</div>
        <h2>Attendance Already Taken</h2>
        <p><?php echo $selected_term; ?> attendance for <b><?php echo $teacher_class; ?></b> has already been marked today.</p>
    </div>

    <?php
    $present_count = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE class_name='$class_esc' AND attendance_date='$today' AND term='$term_esc' AND academic_year='$ay_esc' AND status='Present'")->fetch_assoc()['c'];
    $absent_count  = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE class_name='$class_esc' AND attendance_date='$today' AND term='$term_esc' AND academic_year='$ay_esc' AND status='Absent'")->fetch_assoc()['c'];
    ?>
    <div class="stats-row">
        <span class="stat-pill blue">👥 Total: <?php echo $students->num_rows; ?></span>
        <span class="stat-pill green">✅ Present: <?php echo $present_count; ?></span>
        <span class="stat-pill red">❌ Absent: <?php echo $absent_count; ?></span>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h2>📋 Today's Attendance — <?php echo $selected_term; ?></h2>
            <span style="font-size:13px; opacity:0.8;"><?php echo date('F j, Y'); ?></span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>#</th><th>Student ID</th><th>Full Name</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php
                    $records = $conn->query("SELECT a.student_id, s.full_name, a.status FROM attendance a JOIN students s ON a.student_id = s.student_id WHERE a.class_name='$class_esc' AND a.attendance_date='$today' AND a.term='$term_esc' AND a.academic_year='$ay_esc' ORDER BY s.full_name");
                    $i = 1;
                    while($row = $records->fetch_assoc()): ?>
                    <tr style="background:<?php echo $row['status']==='Present'?'#f0fff4':'#fff5f5'; ?>">
                        <td><?php echo $i++; ?></td>
                        <td><b><?php echo htmlspecialchars($row['student_id']); ?></b></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><span class="<?php echo $row['status']==='Present'?'badge-present':'badge-absent'; ?>"><?php echo $row['status']==='Present'?'✅ Present':'❌ Absent'; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    <!-- Mark Attendance Form -->
    <div class="info-bar">
        ℹ️ Marking <b><?php echo $selected_term; ?></b> attendance for <b><?php echo date('l, F j, Y'); ?></b>.
        This is a valid school day within the term dates.
    </div>

    <div class="stats-row">
        <span class="stat-pill blue">👥 Students: <?php echo $students->num_rows; ?></span>
        <span class="stat-pill green">📅 <?php echo $selected_term; ?></span>
    </div>

    <form method="POST">
        <input type="hidden" name="selected_term" value="<?php echo htmlspecialchars($selected_term); ?>">
        <div class="table-card">
            <div class="table-header">
                <h2>✅ <?php echo $selected_term; ?> Attendance — <?php echo $teacher_class; ?></h2>
                <span style="font-size:13px; opacity:0.8;"><?php echo date('F j, Y'); ?></span>
            </div>
            <div class="mark-all">
                <span>Mark All:</span>
                <button type="button" class="btn-mark btn-mark-present" onclick="markAll('Present')">✅ All Present</button>
                <button type="button" class="btn-mark btn-mark-absent" onclick="markAll('Absent')">❌ All Absent</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>#</th><th>Student ID</th><th>Full Name</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        $students->data_seek(0);
                        while($row = $students->fetch_assoc()):
                        ?>
                        <tr id="row_<?php echo $row['student_id']; ?>">
                            <td><?php echo $i++; ?></td>
                            <td><b><?php echo htmlspecialchars($row['student_id']); ?></b></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td>
                                <div class="radio-group">
                                    <label class="radio-label present">
                                        <input type="radio" name="status[<?php echo $row['student_id']; ?>]" value="Present" required onchange="highlightRow('<?php echo $row['student_id']; ?>', 'Present')"> Present
                                    </label>
                                    <label class="radio-label absent">
                                        <input type="radio" name="status[<?php echo $row['student_id']; ?>]" value="Absent" onchange="highlightRow('<?php echo $row['student_id']; ?>', 'Absent')"> Absent
                                    </label>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" name="submit_attendance" class="btn-save">💾 Save <?php echo $selected_term; ?> Attendance</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
function markAll(status) {
    document.querySelectorAll('input[type="radio"][value="'+status+'"]').forEach(function(r){
        r.checked = true;
        var sid = r.name.match(/\[(.+)\]/)[1];
        highlightRow(sid, status);
    });
}
function highlightRow(sid, status) {
    var row = document.getElementById('row_'+sid);
    if(row) row.style.background = status==='Present'?'#f0fff4':'#fff5f5';
}
(function(){
    var mq = window.matchMedia('(max-width: 1024px)');
    function syncCompact(){ var on=mq.matches; document.documentElement.classList.toggle('compact',on); if(!on) document.body.classList.remove('nav-open'); }
    syncCompact();
    if(mq.addEventListener) mq.addEventListener('change',syncCompact);
    var body=document.body, overlay=document.getElementById('sidebarOverlay'), toggle=document.getElementById('menuToggle'), sidebar=document.getElementById('sidebar');
    function setOpen(open){ body.classList.toggle('nav-open',open); if(toggle) toggle.setAttribute('aria-expanded',open?'true':'false'); if(overlay) overlay.setAttribute('aria-hidden',open?'false':'true'); }
    if(toggle) toggle.addEventListener('click',function(){ if(!document.documentElement.classList.contains('compact')) return; setOpen(!body.classList.contains('nav-open')); });
    if(overlay) overlay.addEventListener('click',function(){ setOpen(false); });
    if(sidebar) sidebar.querySelectorAll('a').forEach(function(a){ a.addEventListener('click',function(){ if(document.documentElement.classList.contains('compact')) setOpen(false); }); });
})();
</script>
</body>
</html>