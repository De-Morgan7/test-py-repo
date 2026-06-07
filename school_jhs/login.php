<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';
$error = "";

// ✅ Safe session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['login'])) {

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password' LIMIT 1";

    $result = $conn->query($query);

    if (!$result) {
        die("Query Error: " . $conn->error);
    }

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        // 🔐 SESSION DATA
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['assigned_class'] = $user['assigned_class'];

        // 🚀 REDIRECT
        header("Location: admin.php");
        exit();

    } else {
        $error = "❌ Invalid Username or Password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- ✅ IMPORTANT FOR MOBILE -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>BHIS Portal Login</title>

    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1a73e8, #0d47a1);

            display:flex;
            justify-content:center;
            align-items:center;

            min-height:100vh;
            padding:20px;
        }

        .box{

            background:white;

            width:100%;
            max-width:400px;

            padding:40px 30px;

            border-radius:15px;

            box-shadow:0 10px 25px rgba(0,0,0,0.2);

            text-align:center;
        }

        .logo{
            font-size:55px;
            margin-bottom:10px;
        }

        h2{
            color:#1a73e8;
            margin-bottom:8px;
        }

        .subtitle{
            color:#777;
            font-size:14px;
            margin-bottom:25px;
        }

        .error{
            background:#ffe5e5;
            color:#d60000;
            padding:10px;
            border-radius:6px;
            margin-bottom:15px;
            font-size:14px;
        }

        .input-group{
            margin-bottom:15px;
            text-align:left;
        }

        label{
            display:block;
            margin-bottom:6px;
            font-size:14px;
            font-weight:bold;
            color:#444;
        }

        input{

            width:100%;

            padding:12px;

            border:1px solid #ccc;

            border-radius:8px;

            font-size:15px;

            outline:none;

            transition:0.3s;
        }

        input:focus{
            border-color:#1a73e8;
            box-shadow:0 0 5px rgba(26,115,232,0.3);
        }

        button{

            width:100%;

            padding:13px;

            border:none;

            background:#1a73e8;

            color:white;

            border-radius:8px;

            font-size:16px;

            cursor:pointer;

            transition:0.3s;
        }

        button:hover{
            background:#0d47a1;
        }

        .footer{
            margin-top:20px;
            font-size:13px;
            color:#777;
        }

        /* ✅ MOBILE IMPROVEMENTS */
        @media(max-width:480px){

            .box{
                padding:30px 20px;
            }

            h2{
                font-size:22px;
            }

            .logo{
                font-size:45px;
            }

            input{
                font-size:16px;
            }

            button{
                font-size:15px;
            }
        }

    </style>
</head>

<body>

<div class="box">

<div class="school-header">
    <img src="logo.png" alt="School Logo" style="height:150px; margin-bottom:8px;">
    <h1>Beverly Hills International School</h1>

    <p class="subtitle">Login to continue</p>

    <?php if ($error): ?>
        <div class="error">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="input-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="Enter username" required>
        </div>

        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter password" required>
        </div>

        <button type="submit" name="login">
            🔐 Login
        </button>

    </form>

    <div class="footer">
        School Management System
    </div>

</div>

</body>
</html>