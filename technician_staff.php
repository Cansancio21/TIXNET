<?php
session_start();
include 'db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Query to check username and password in tbl_user
    $sql = "SELECT u_id, u_fname, u_lname, u_username, u_password, u_type, u_status FROM tbl_user WHERE u_username = ? AND u_type IN ('staff', 'technician')";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        die("Database error. Please try again later.");
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['u_password'])) {
            if (strtolower($user['u_status']) === 'pending') {
                echo "<script>alert('Your account is pending. Please contact an administrator.');</script>";
            } elseif (strtolower($user['u_status']) === 'active') {
                // Store user data in session
                $_SESSION['user'] = $user;
                $_SESSION['user_type'] = $user['u_type'];
                $_SESSION['userId'] = $user['u_id'];
                $_SESSION['username'] = $user['u_username'];

                // REMOVED ALL is_online UPDATES
                error_log("User $username logged in successfully (Type: {$user['u_type']})");

                // Log the successful login
                $log_description = "has successfully logged in";
                $log_type = $user['u_fname'] . ' ' . $user['u_lname'];
                
                $log_stmt = $conn->prepare("INSERT INTO tbl_logs (l_description, l_type, l_stamp) VALUES (?, ?, NOW())");
                if (!$log_stmt) {
                    error_log("Log prepare failed: " . $conn->error);
                    die("Logging error. Please try again later.");
                }
                $log_stmt->bind_param("ss", $log_description, $log_type);
                if (!$log_stmt->execute()) {
                    error_log("Log execute failed: " . $log_stmt->error);
                } else {
                    error_log("Logged: l_type='$log_type', l_description='$log_description'");
                }
                $log_stmt->close();

                // Redirect based on user type
                if ($user['u_type'] === 'staff') {
                    header("Location: staffD.php");
                } elseif ($user['u_type'] === 'technician') {
                    header("Location: technicianD.php");
                }
                exit();
            }
        } else {
            echo "<script>alert('Invalid username or password. Please try again.');</script>";
        }
    } else {
        echo "<script>alert('Invalid username or password. Please try again.');</script>";
    }

    $stmt->close();
    $conn->close();
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // REMOVED ALL is_online UPDATES
    if (isset($_SESSION['username']) && isset($_SESSION['user_type'])) {
        error_log("User {$_SESSION['username']} ({$_SESSION['user_type']}) logged out");
    }
    session_unset();
    session_destroy();
    header("Location: technician_staff.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Portal</title>
    <link rel="stylesheet" href="technician_stafff.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
<div class="wrapper">
    <div class="form-container">
        <div class="logo-container">
            <h2>
                <img src="image/portal.png" alt="TixNet Icon" class="side-left">
                TixNet Pro
            </h2>
        </div>
        <div class="vertical-line"></div>
        <div class="form-content">
            <h1 class="login-title">User Portal</h1>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <div class="input-box">
                        <input type="text" id="username" name="username" placeholder="Enter Username" required>
                        <i class="bx bxs-user user-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <div class="input-box">
                        <input type="password" id="password" name="password" placeholder="Enter Password" required>
                        <i class="bx bxs-lock-alt password-icon" id="toggleLoginPassword" style="cursor: pointer;"></i>
                    </div>
                </div>
                <button type="submit">Login</button>
                <p class="additional-info">Welcome to TixNet Pro Technician & Staff Portal!</p>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.getElementById('toggleLoginPassword');
    const passwordInput = document.getElementById('password');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('bxs-lock-alt');
            this.classList.toggle('bxs-lock-open-alt');
        });
    }
});
</script>
</body>
</html>