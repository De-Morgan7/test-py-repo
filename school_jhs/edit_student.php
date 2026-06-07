<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$student_id = mysqli_real_escape_string($conn, $_GET['id']);
$student = $conn->query("SELECT * FROM students WHERE student_id='$student_id'")->fetch_assoc();

if (!$student) {
    header("Location: view_students.php");
    exit();
}

$success = "";
$error = "";

if (isset($_POST['update_student'])) {
    $full_name  = mysqli_real_escape_string($conn, $_POST['full_name']);
    $gender     = mysqli_real_escape_string($conn, $_POST['gender']);
    $dob        = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    $class_name = mysqli_real_escape_string($conn, $_POST['class_name']);
    $guardian   = mysqli_real_escape_string($conn, $_POST['guardian_name']);
    $phone      = mysqli_real_escape_string($conn, $_POST['guardian_phone']);

    $query = "UPDATE students SET 
                full_name='$full_name',
                gender='$gender',
                date_of_birth='$dob',
                class_name='$class_name',
                guardian_name='$guardian',
                guardian_phone='$phone'
              WHERE student_id='$student_id'";

    if ($conn->query($query)) {
        $success = "Student updated successfully!";
        $student = $conn->query("SELECT * FROM students WHERE student_id='$student_id'")->fetch_assoc();
    } else {
        $error = "Something went wrong. Try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JHS Portal - Edit Student</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: linear-gradient(180deg, #1a73e8, #0d47a1); color: white; padding: 20px 0; position: fixed; height: 100vh; }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 20px; }
        .sidebar-logo h2 { font-size: 20px; }
        .sidebar-logo p { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        .nav-section { padding: 0 15px; margin-bottom: 10px; }
        .nav-section-title { font-size: 11px; text-transform: uppercase; opacity: 0.6; margin-bottom: 8px; padding-left: 10px; }
        .nav-link { display: block; padding: 10px 15px; color: white; text-decoration: none; border-radius: 8px; margin-bottom: 4px; transition: 0.2s; font-size: 14px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        .logout-btn { display: block; margin: 20px 15px 0; padding: 10px 15px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 8px; text-align: center; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); }
        .logout-btn:hover { background: #e53935; }
        .main { margin-left: 250px; padding: 30px; width: calc(100% - 250px); }
        .top-bar { background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 20px; color: #333; }
        .form-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); max-width: 700px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        label { font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        input, select { padding: 11px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; transition: 0.2s; }
        input:focus, select:focus { border-color: #1a73e8; box-shadow: 0 0 0 3px rgba(26,115,232,0.1); }
        input[readonly] { background: #f0f2f5; color: #888; cursor: not-allowed; }
        .btn-submit { margin-top: 25px; padding: 13px 35px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-submit:hover { background: #1557b0; }
        .btn-back { margin-top: 25px; margin-left: 10px; padding: 13px 25px; background: #f0f2f5; color: #333; border: none; border-radius: 8px; font-size: 15px; cursor: pointer; text-decoration: none; display: inline-block; }
        .success-msg { background: #e8f5e9; color: #2e7d32; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2e7d32; font-size: 14px; }
        .error-msg { background: #ffebee; color: #c62828; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c62828; font-size: 14px; }
        .section-divider { font-size: 13px; font-weight: 700; color: #1a73e8; text-transform: uppercase; margin: 20px 0 10px; border-bottom: 2px solid #f0f2f5; padding-bottom: 8px; grid-column: 1 / -1; }
        .id-badge { display: inline-block; background: #e3f2fd; color: #1565c0; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-logo">
        <h2>🏫 BHIS PORTAL</h2>
        <p><?php echo $_SESSION['full_name']; ?></p>
        <p><?php echo $_SESSION['role']; ?></p>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Main</div>
        <a href="admin.php" class="nav-link"><span>📊</span> Dashboard</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-title">Students</div>
        <a href="add_student.php" class="nav-link"><span>➕</span> Add Student</a>
        <a href="view_students.php" class="nav-link active"><span>👥</span> View Students</a>
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
        <h1>✏️ Edit Student</h1>
        <span class="id-badge">ID: <?php echo $student['student_id']; ?></span>
    </div>

    <div class="form-card">
        <?php if($success): ?>
            <div class="success-msg">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="error-msg">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-grid">
                <div class="section-divider">Student Information</div>
                <div class="form-group">
                    <label>Student ID</label>
                    <input type="text" value="<?php echo $student['student_id']; ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?php echo $student['full_name']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" required>
                        <option value="Male" <?php echo $student['gender']=='Male'?'selected':''; ?>>Male</option>
                        <option value="Female" <?php echo $student['gender']=='Female'?'selected':''; ?>>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?php echo $student['date_of_birth']; ?>">
                </div>
                <div class="form-group">
                    <label>Class</label>
                    <select name="class_name" required>
                        <option value="JHS 1" <?php echo $student['class_name']=='JHS 1'?'selected':''; ?>>JHS 1</option>
                        <option value="JHS 2" <?php echo $student['class_name']=='JHS 2'?'selected':''; ?>>JHS 2</option>
                        <option value="JHS 3" <?php echo $student['class_name']=='JHS 3'?'selected':''; ?>>JHS 3</option>
                    </select>
                </div>
                <div class="section-divider">Guardian Information</div>
                <div class="form-group">
                    <label>Guardian Name</label>
                    <input type="text" name="guardian_name" value="<?php echo $student['guardian_name']; ?>">
                </div>
                <div class="form-group">
                    <label>Guardian Phone</label>
                    <input type="text" name="guardian_phone" value="<?php echo $student['guardian_phone']; ?>">
                </div>
            </div>
            <button type="submit" name="update_student" class="btn-submit">💾 Save Changes</button>
            <a href="view_students.php" class="btn-back">← Back to Students</a>
        </form>
    </div>
</div>

</body>
</html>