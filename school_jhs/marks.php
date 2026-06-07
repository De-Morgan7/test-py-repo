<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: login.php");
    exit();
}

$teacher_class = $_SESSION['assigned_class'];
$subjects = ['English Language', 'Mathematics', 'Integrated Science', 'Religious and Moral Education', 'Social Studies', 'French', 'Career Technology', 'Creative Art and Design', 'Ashanti Twi', 'Computing'];
$terms = ['First Term', 'Second Term', 'Third Term'];
$academic_year = get_active_year($conn);

$selected_term    = isset($_GET['term']) ? mysqli_real_escape_string($conn, $_GET['term']) : 'First Term';
$selected_subject = isset($_GET['subject']) ? mysqli_real_escape_string($conn, $_GET['subject']) : 'English';

$success = "";
$error   = "";

if (isset($_GET['saved'])) {
    $success = "Marks saved successfully!";
}

function getGrade($total) {
    if ($total >= 80) return 1;
    if ($total >= 70) return 2;
    if ($total >= 60) return 3;
    if ($total >= 55) return 4;
    if ($total >= 50) return 5;
    if ($total >= 45) return 6;
    if ($total >= 40) return 7;
    if ($total >= 35) return 8;
    return 9;
}

if (isset($_POST['save_marks'])) {
    $term    = mysqli_real_escape_string($conn, $_POST['term']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);

    foreach ($_POST['students'] as $student_id => $scores) {
        $student_id  = mysqli_real_escape_string($conn, $student_id);
        $class_score = floatval($scores['class_score']);
        $exam_score  = floatval($scores['exam_score']);

        if ($class_score > 30) $class_score = 30;
        if ($exam_score > 70)  $exam_score  = 70;
        if ($class_score < 0)  $class_score = 0;
        if ($exam_score < 0)   $exam_score  = 0;

        $total = $class_score + $exam_score;
        $grade = getGrade($total);

        $check = $conn->query("SELECT id FROM marks WHERE student_id='$student_id' AND subject='$subject' AND term='$term' AND academic_year='$academic_year' AND class_name='$teacher_class'");

        if ($check->num_rows > 0) {
            $conn->query("UPDATE marks SET ca1='$class_score', exam='$exam_score', total='$total', grade='$grade' WHERE student_id='$student_id' AND subject='$subject' AND term='$term' AND academic_year='$academic_year' AND class_name='$teacher_class'");
        } else {
            $conn->query("INSERT INTO marks (student_id, class_name, subject, ca1, exam, total, grade, term, academic_year) VALUES ('$student_id', '$teacher_class', '$subject', '$class_score', '$exam_score', '$total', '$grade', '$term', '$academic_year')");
        }
    }

    header("Location: marks.php?term=" . urlencode($term) . "&subject=" . urlencode($subject) . "&saved=1");
    exit();
}

$students = $conn->query("SELECT * FROM students WHERE class_name='$teacher_class' AND status='Active' ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>BHIS Portal - Enter Marks</title>
    <style>
        /* BASE STYLES */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; overflow-x: hidden; }
        
        /* SIDEBAR PC */
        .sidebar { width: 250px; background: linear-gradient(180deg, #1a73e8, #0d47a1); color: white; padding: 20px 0; position: fixed; height: 100vh; overflow-y: auto; z-index: 1000; transition: 0.3s; }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 20px; }
        .nav-link { display: block; padding: 12px 15px; color: white; text-decoration: none; border-radius: 8px; margin: 0 15px 4px; transition: 0.2s; font-size: 14px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        
        /* MAIN CONTENT AREA */
        .main { margin-left: 250px; padding: 30px; width: calc(100% - 250px); transition: 0.3s; }
        
        /* MOBILE TOP HEADER (Hidden on PC) */
        .mobile-header { display: none; background: #1a73e8; color: white; padding: 15px; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1001; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .menu-toggle { background: none; border: 1px solid white; color: white; padding: 5px 10px; border-radius: 4px; cursor: pointer; }

        /* CARDS & UI */
        .top-bar, .filter-card, .table-card, .subject-progress { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .top-bar { padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .filter-card { padding: 20px; }
        .filter-grid { display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end; }
        
        /* TABLE HANDLING */
        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; text-align: center; }
        .score-input { width: 70px; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; text-align: center; } /* 16px prevents iOS zoom */
        
        /* MOBILE RESPONSIVENESS */
        @media (max-width: 992px) {
            .sidebar { left: -250px; } /* Hide sidebar */
            .sidebar.active { left: 0; }
            .main { margin-left: 0; width: 100%; padding: 15px; }
            .mobile-header { display: flex; }
            .filter-grid { grid-template-columns: 1fr; } /* Stack filters */
            .btn-filter, .btn-save { width: 100%; }
            .top-bar h1 { font-size: 18px; }
        }

        /* Utility Styles */
        .grade-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; white-space: nowrap; }
        .grade-1, .grade-2 { background: #e8f5e9; color: #2e7d32; }
        .grade-3, .grade-4 { background: #e3f2fd; color: #1565c0; }
        .grade-5, .grade-6 { background: #fff8e1; color: #f57f17; }
        .grade-7, .grade-8 { background: #fce4ec; color: #880e4f; }
        .grade-9 { background: #ffebee; color: #c62828; }
        .btn-save { margin: 20px; padding: 15px; background: #27ae60; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .subject-pills { display: flex; flex-wrap: wrap; gap: 8px; padding: 15px; }
        .subject-pill { padding: 6px 12px; border-radius: 20px; font-size: 11px; border: 1px solid #ddd; }
        .subject-pill.current { background: #1a73e8; color: white; border-color: #1a73e8; }
        .subject-pill.done { background: #e8f5e9; color: #2e7d32; }
    </style>
</head>
<body>

<!-- Mobile Menu Header -->
<div class="mobile-header">
    <span>🏫 BHIS PORTAL</span>
    <button class="menu-toggle" onclick="toggleSidebar()">☰ Menu</button>
</div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <h2>🏫 BHIS PORTAL</h2>
        <p><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
        <p>Teacher - <?php echo $teacher_class; ?></p>
    </div>
    <div class="nav-section">
        <a href="admin.php" class="nav-link">📊 Dashboard</a>
        <a href="attendance.php" class="nav-link">✅ Mark Attendance</a>
        <a href="marks.php" class="nav-link active">📝 Enter Marks</a>
        <a href="view_marks.php" class="nav-link">📊 View Marks</a>
        <a href="view_students.php" class="nav-link">👥 View Students</a>
    </div>
    <a href="logout.php" style="text-decoration:none;"><div style="margin:20px; padding:10px; border:1px solid rgba(255,255,255,0.3); border-radius:8px; text-align:center; color:white;">🚪 Logout</div></a>
</div>

<div class="main">
    <div class="top-bar">
        <h1>📝 Enter Marks</h1>
        <span style="font-size:13px; color:#666;"><b><?php echo $teacher_class; ?></b> | <b><?php echo $academic_year; ?></b></span>
    </div>

    <?php if($success): ?>
        <div style="background:#e8f5e9; color:#2e7d32; padding:15px; border-radius:8px; margin-bottom:15px; border-left:5px solid #2e7d32;">✅ <?php echo $success; ?></div>
    <?php endif; ?>

    <div class="filter-card">
        <form method="GET">
            <div class="filter-grid">
                <div style="display:flex; flex-direction:column; gap:5px;">
                    <label style="font-size:12px; font-weight:bold;">Term</label>
                    <select name="term" style="padding:12px; border-radius:8px; border:1px solid #ddd; font-size:16px;">
                        <?php foreach($terms as $term): ?>
                        <option value="<?php echo $term; ?>" <?php echo $selected_term==$term?'selected':''; ?>><?php echo $term; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; flex-direction:column; gap:5px;">
                    <label style="font-size:12px; font-weight:bold;">Subject</label>
                    <select name="subject" style="padding:12px; border-radius:8px; border:1px solid #ddd; font-size:16px;">
                        <?php foreach($subjects as $subject): ?>
                        <option value="<?php echo $subject; ?>" <?php echo $selected_subject==$subject?'selected':''; ?>><?php echo $subject; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filter" style="padding:12px 25px; background:#1a73e8; color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">Load Students</button>
            </div>
        </form>
    </div>

    <div class="subject-progress">
        <div class="subject-pills">
            <?php foreach($subjects as $sub):
                $has_marks = $conn->query("SELECT COUNT(*) as c FROM marks WHERE class_name='$teacher_class' AND subject='$sub' AND term='$selected_term' AND academic_year='$academic_year'")->fetch_assoc()['c'];
                $pill_class = $sub == $selected_subject ? 'current' : ($has_marks > 0 ? 'done' : '');
            ?>
            <span class="subject-pill <?php echo $pill_class; ?>"><?php echo ($has_marks > 0 ? '✅ ' : '') . $sub; ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="term" value="<?php echo htmlspecialchars($selected_term); ?>">
        <input type="hidden" name="subject" value="<?php echo htmlspecialchars($selected_subject); ?>">

        <div class="table-card">
            <div style="padding:15px; background:#1a73e8; color:white; border-radius:12px 12px 0 0;">
                <h2 style="font-size:16px;">📚 <?php echo $selected_subject; ?> — <?php echo $selected_term; ?></h2>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr style="background:#f8f9fa;">
                            <th style="text-align:left;">Student Name</th>
                            <th>Class (30)</th>
                            <th>Exam (70)</th>
                            <th>Total</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $students->fetch_assoc()):
                            $sid = $row['student_id'];
                            $existing = $conn->query("SELECT * FROM marks WHERE student_id='$sid' AND subject='$selected_subject' AND term='$selected_term' AND academic_year='$academic_year' AND class_name='$teacher_class'")->fetch_assoc();
                            $total = $existing['total'] ?? 0;
                            $grade = $existing['grade'] ?? '';
                        ?>
                        <tr id="row_<?php echo $sid; ?>">
                            <td style="text-align:left;"><b><?php echo $row['full_name']; ?></b></td>
                            <td><input type="number" name="students[<?php echo $sid; ?>][class_score]" id="cs_<?php echo $sid; ?>" class="score-input" value="<?php echo $existing['ca1'] ?? ''; ?>" min="0" max="30" step="0.5" oninput="calcTotal('<?php echo $sid; ?>')"></td>
                            <td><input type="number" name="students[<?php echo $sid; ?>][exam_score]" id="es_<?php echo $sid; ?>" class="score-input" value="<?php echo $existing['exam'] ?? ''; ?>" min="0" max="70" step="0.5" oninput="calcTotal('<?php echo $sid; ?>')"></td>
                            <td><span id="total_<?php echo $sid; ?>" style="font-weight:bold;"><?php echo $total ?: '--'; ?></span></td>
                            <td><span class="grade-badge <?php echo $grade ? 'grade-'.$grade : ''; ?>" id="grade_<?php echo $sid; ?>"><?php echo $grade ? 'Grade '.$grade : '--'; ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" name="save_marks" class="btn-save">💾 Save Marks</button>
        </div>
    </form>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
}

function getGrade(total) {
    if (total >= 80) return 1; if (total >= 70) return 2; if (total >= 60) return 3;
    if (total >= 55) return 4; if (total >= 50) return 5; if (total >= 45) return 6;
    if (total >= 40) return 7; if (total >= 35) return 8; return 9;
}

function getGradeClass(grade) {
    if (grade <= 2) return 'grade-1'; if (grade <= 4) return 'grade-3';
    if (grade <= 6) return 'grade-5'; if (grade <= 8) return 'grade-7'; return 'grade-9';
}

function calcTotal(studentId) {
    const csVal = parseFloat(document.getElementById('cs_' + studentId).value) || 0;
    const esVal = parseFloat(document.getElementById('es_' + studentId).value) || 0;
    const total = Math.min(100, csVal + esVal);
    const grade = getGrade(total);
    
    document.getElementById('total_' + studentId).textContent = total.toFixed(1);
    const gradeEl = document.getElementById('grade_' + studentId);
    gradeEl.textContent = 'Grade ' + grade;
    gradeEl.className = 'grade-badge ' + getGradeClass(grade);
}
</script>
</body>
</html>