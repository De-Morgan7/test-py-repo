<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];

if (isset($_GET['delete']) && $role === 'Admin') {

    $del_id = mysqli_real_escape_string($conn, $_GET['delete']);

    $conn->query("
        DELETE FROM students 
        WHERE student_id='$del_id'
    ");

    header("Location: view_students.php?deleted=1");
    exit();
}

$filter = isset($_GET['class'])
    ? mysqli_real_escape_string($conn, $_GET['class'])
    : '';

if ($role === 'Teacher') {
    $filter = $_SESSION['assigned_class'];
}

if ($filter) {
    $students = $conn->query("SELECT * FROM students WHERE class_name='$filter' AND status='Active' ORDER BY full_name");
} else {
    $students = $conn->query("SELECT * FROM students WHERE status='Active' ORDER BY class_name, full_name");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>View Students</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Segoe UI',sans-serif;
    background:#f0f2f5;
}

/* SIDEBAR */

.sidebar{
    width:250px;
    background:linear-gradient(180deg,#1a73e8,#0d47a1);
    color:white;
    position:fixed;
    top:0;
    left:0;
    height:100vh;
    overflow-y:auto;
    padding:20px 0;
}

.sidebar-logo{
    text-align:center;
    padding:20px;
    border-bottom:1px solid rgba(255,255,255,0.2);
}

.sidebar-logo h2{
    font-size:22px;
}

.sidebar-logo p{
    font-size:13px;
    opacity:0.8;
    margin-top:5px;
}

.nav-section{
    padding:15px;
}

.nav-section-title{
    font-size:11px;
    text-transform:uppercase;
    opacity:0.7;
    margin-bottom:10px;
}

.nav-link{
    display:block;
    padding:12px 15px;
    border-radius:8px;
    color:white;
    text-decoration:none;
    margin-bottom:8px;
    transition:0.3s;
}

.nav-link:hover,
.nav-link.active{
    background:rgba(255,255,255,0.2);
}

.logout-btn{
    display:block;
    margin:20px 15px;
    padding:12px;
    text-align:center;
    border-radius:8px;
    background:#e53935;
    color:white;
    text-decoration:none;
}

/* MAIN */

.main{
    margin-left:250px;
    padding:25px;
}

/* TOP BAR */

.top-bar{
    background:white;
    padding:20px;
    border-radius:12px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
}

.top-bar h1{
    font-size:24px;
}

.add-btn{
    background:#1a73e8;
    color:white;
    padding:12px 18px;
    border-radius:8px;
    text-decoration:none;
    font-size:14px;
    font-weight:bold;
}

/* FILTER */

.filter-bar{
    background:white;
    padding:18px;
    border-radius:12px;
    margin-bottom:20px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
}

.filter-btn{
    padding:10px 16px;
    border-radius:20px;
    border:1px solid #ddd;
    text-decoration:none;
    color:#333;
    font-size:13px;
    transition:0.3s;
}

.filter-btn:hover,
.filter-btn.active{
    background:#1a73e8;
    color:white;
    border-color:#1a73e8;
}

/* SUCCESS */

.success-msg{
    background:#e8f5e9;
    color:#2e7d32;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
}

/* TABLE */

.table-card{
    background:white;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
}

.table-wrapper{
    overflow-x:auto;
}

table{
    width:100%;
    min-width:1100px;
    border-collapse:collapse;
}

thead{
    background:#f8f9fa;
}

th{
    padding:14px;
    text-align:left;
    font-size:13px;
    border-bottom:2px solid #eee;
    white-space:nowrap;
}

td{
    padding:14px;
    border-bottom:1px solid #eee;
    font-size:14px;
    white-space:nowrap;
}

tr:hover{
    background:#fafafa;
}

/* BADGES */

.badge{
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
    font-weight:bold;
}

.badge-jhs1{
    background:#e3f2fd;
    color:#1565c0;
}

.badge-jhs2{
    background:#f3e5f5;
    color:#6a1b9a;
}

.badge-jhs3{
    background:#e8f5e9;
    color:#2e7d32;
}

.badge-male{
    background:#e3f2fd;
    color:#1565c0;
}

.badge-female{
    background:#fce4ec;
    color:#880e4f;
}

/* ACTIONS */

.action-buttons{
    display:flex;
    gap:8px;
}

.btn-edit{
    padding:8px 12px;
    border-radius:6px;
    background:#e8f5e9;
    color:#2e7d32;
    text-decoration:none;
    font-size:12px;
    font-weight:bold;
}

.btn-delete{
    padding:8px 12px;
    border-radius:6px;
    background:#ffebee;
    color:#c62828;
    text-decoration:none;
    font-size:12px;
    font-weight:bold;
}

/* EMPTY */

.empty-state{
    text-align:center;
    padding:60px;
    color:#999;
}

.empty-state .icon{
    font-size:55px;
    margin-bottom:15px;
}

/* MOBILE */

@media(max-width:768px){

    .sidebar{
        width:100%;
        position:relative;
        height:auto;
    }

    .main{
        margin-left:0;
        padding:15px;
    }

    .top-bar{
        flex-direction:column;
        align-items:flex-start;
        gap:15px;
    }

    .top-bar h1{
        font-size:22px;
    }

    .add-btn{
        width:100%;
        text-align:center;
    }

    .filter-bar{
        flex-direction:column;
        align-items:flex-start;
    }

    .filter-btn{
        width:100%;
        text-align:center;
    }

    table{
        min-width:1000px;
    }

}

</style>

</head>

<body>

<!-- SIDEBAR -->

<div class="sidebar">

    <div class="sidebar-logo">

        <h2>🏫 BHIS PORTAL</h2>

        <p><?php echo $_SESSION['full_name']; ?></p>

        <p><?php echo $role; ?></p>

    </div>

    <div class="nav-section">

        <div class="nav-section-title">
            Main
        </div>

        <a href="admin.php" class="nav-link">
            📊 Dashboard
        </a>

    </div>

    <div class="nav-section">

        <div class="nav-section-title">
            Students
        </div>

        <?php if($role === 'Admin'): ?>

        <a href="add_student.php" class="nav-link">
            ➕ Add Student
        </a>

        <?php endif; ?>

        <a href="view_students.php"
           class="nav-link active">
            👥 View Students
        </a>

    </div>

    <div class="nav-section">

        <div class="nav-section-title">
            Academics
        </div>

        <a href="view_marks.php" class="nav-link">
            📝 View Marks
        </a>

        <a href="attendance_report.php" class="nav-link">
            📋 Attendance Report
        </a>

    </div>

    <a href="logout.php"
       class="logout-btn">
       🚪 Logout
    </a>

</div>

<!-- MAIN -->

<div class="main">

    <div class="top-bar">

        <h1>👥 Students</h1>

        <?php if($role === 'Admin'): ?>

        <a href="add_student.php"
           class="add-btn">
           ➕ Add Student
        </a>

        <?php endif; ?>

    </div>

<?php if(isset($_GET['deleted'])): ?>

<div class="success-msg">
    ✅ Student deleted successfully.
</div>

<?php endif; ?>

<?php if($role === 'Admin'): ?>

<div class="filter-bar">

    <strong>Filter Class:</strong>

    <a href="view_students.php"
       class="filter-btn <?php echo !$filter ? 'active' : ''; ?>">
       All
    </a>

    <a href="view_students.php?class=JHS 1"
       class="filter-btn <?php echo $filter=='JHS 1' ? 'active' : ''; ?>">
       JHS 1
    </a>

    <a href="view_students.php?class=JHS 2"
       class="filter-btn <?php echo $filter=='JHS 2' ? 'active' : ''; ?>">
       JHS 2
    </a>

    <a href="view_students.php?class=JHS 3"
       class="filter-btn <?php echo $filter=='JHS 3' ? 'active' : ''; ?>">
       JHS 3
    </a>

</div>

<?php endif; ?>

<div class="table-card">

<div class="table-wrapper">

<table>

<thead>

<tr>

    <th>#</th>

    <th>Student ID</th>

    <th>Full Name</th>

    <th>Gender</th>

    <th>Class</th>

    <th>Guardian</th>

    <th>Phone</th>

    <th>Admitted</th>

<?php if($role === 'Admin'): ?>

    <th>Action</th>

<?php endif; ?>

</tr>

</thead>

<tbody>

<?php

if($students && $students->num_rows > 0):

$i = 1;

while($row = $students->fetch_assoc()):

$class = $row['class_name'];

$badge =
    $class == 'JHS 1'
    ? 'badge-jhs1'
    : ($class == 'JHS 2'
        ? 'badge-jhs2'
        : 'badge-jhs3');

?>

<tr>

<td><?php echo $i++; ?></td>

<td>
    <b><?php echo $row['student_id']; ?></b>
</td>

<td>
    <?php echo $row['full_name']; ?>
</td>

<td>

<span class="badge <?php echo $row['gender']=='Male' ? 'badge-male' : 'badge-female'; ?>">

<?php echo $row['gender']; ?>

</span>

</td>

<td>

<span class="badge <?php echo $badge; ?>">

<?php echo $class; ?>

</span>

</td>

<td>
    <?php echo $row['guardian_name']; ?>
</td>

<td>
    <?php echo $row['guardian_phone']; ?>
</td>

<td>
    <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
</td>

<?php if($role === 'Admin'): ?>

<td>

<div class="action-buttons">

<a href="edit_student.php?id=<?php echo urlencode($row['student_id']); ?>"
   class="btn-edit">
   ✏️ Edit
</a>

<a href="view_students.php?delete=<?php echo $row['student_id']; ?>"
   class="btn-delete"
   onclick="return confirm('Delete <?php echo $row['full_name']; ?>?')">

   🗑️ Delete

</a>

</div>

</td>

<?php endif; ?>

</tr>

<?php

endwhile;

else:

?>

<tr>

<td colspan="9">

<div class="empty-state">

<div class="icon">👥</div>

<p>No students found.</p>

</div>

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>