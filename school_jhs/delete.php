<?php
include 'db.php';
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "DELETE FROM students WHERE id = $id";
    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Student Deleted'); window.location.href='admin.php?page=view';</script>";
    }
}
?>