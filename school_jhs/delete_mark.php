<?php
include 'db.php';
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "DELETE FROM marks WHERE id = $id";
    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Mark Deleted'); window.location.href='admin.php?page=view_marks';</script>";
    }
}
?>