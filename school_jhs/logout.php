<?php
session_start();

// destroy session
$_SESSION = [];
session_destroy();

// always redirect immediately
header("Location: login.php");
exit();
?>