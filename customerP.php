<?php
session_start();
include 'db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accountNo = trim($_POST['accountNo']);
    $lastName = trim($_POST['lastName']);

    // Updated query to use c_account_no instead of c_id
    $sql = "SELECT * FROM tbl_customer WHERE c_account_no = ? AND c_lname = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        die("Database error. Please try again later.");
    }

    $stmt->bind_param("ss", $accountNo, $lastName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Store user data in session
        $_SESSION['user'] = $user;
        $_SESSION['user_type'] = 'customer';
        $_SESSION['userId'] = $user['c_id']; // Keep c_id for internal use
        $_SESSION['username'] = $user['c_fname'] . '_' . $user['c_lname'];

        // Log the successful login
        $username = $user['c_fname'] . ' ' . $user['c_lname'];
        $log_description = "has successfully logged in";
        $log_type = $username;
        
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

        header("Location: portal.php");
        exit();
    } else {
        echo "<script>alert('Invalid Account No or Last Name. Please try again.');</script>";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login</title>
    <link rel="stylesheet" href="customersP.css">
</head>
<body>
<div class="wrapper">
    <div class="form-container">
        <img src="image/customer.png" alt="Login Image" class="login-image">
        <div class="vertical-line"></div>
        <div class="form-content">
            <h1 class="login-title">Customer Portal</h1>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="accountNo">Account No:</label>
                    <input type="text" id="accountNo" name="accountNo" placeholder="Enter Account No." required>
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name:</label>
                    <input type="text" id="lastName" name="lastName" placeholder="Enter last name" required>
                </div>
                <button type="submit">Login</button>
                <p class="additional-info">Welcome to Tixnet Pro Customer Portal!</p>
            </form>
        </div>
    </div>
</div>
</body>
</html>