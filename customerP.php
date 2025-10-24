<?php
session_start();
include 'db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize error variables
$accountNoError = "";
$lastNameError = "";

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accountNo = trim($_POST['accountNo']);
    $lastName = trim($_POST['lastName']);
    
    // Basic validation
    $isValid = true;
    
    if (empty($accountNo)) {
        $accountNoError = "Account number is required.";
        $isValid = false;
    }
    
    if (empty($lastName)) {
        $lastNameError = "Last name is required.";
        $isValid = false;
    }
    
    if ($isValid) {
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

            $stmt->close();
            $conn->close();
            
            header("Location: portal.php");
            exit();
        } else {
            // Check which field is incorrect
            $checkAccountSql = "SELECT * FROM tbl_customer WHERE c_account_no = ?";
            $checkAccountStmt = $conn->prepare($checkAccountSql);
            $checkAccountStmt->bind_param("s", $accountNo);
            $checkAccountStmt->execute();
            $accountResult = $checkAccountStmt->get_result();
            
            $checkLastNameSql = "SELECT * FROM tbl_customer WHERE c_lname = ?";
            $checkLastNameStmt = $conn->prepare($checkLastNameSql);
            $checkLastNameStmt->bind_param("s", $lastName);
            $checkLastNameStmt->execute();
            $lastNameResult = $checkLastNameStmt->get_result();
            
            $accountExists = $accountResult->num_rows > 0;
            $lastNameExists = $lastNameResult->num_rows > 0;
            
            if (!$accountExists && !$lastNameExists) {
                // Both are incorrect
                $accountNoError = "Incorrect account number. Please try again.";
                $lastNameError = "Incorrect last name. Please try again.";
            } elseif (!$accountExists) {
                // Only account number is incorrect
                $accountNoError = "Incorrect account number. Please try again.";
            } else {
                // Only last name is incorrect
                $lastNameError = "Incorrect last name. Please try again.";
            }
            
            $checkAccountStmt->close();
            $checkLastNameStmt->close();
        }

        $stmt->close();
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login</title>
    <link rel="stylesheet" href="customerss.css">
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
            <h1 class="login-title">Customer Portal</h1>
            <form action="" method="POST">
         <div class="form-group">
    <label for="accountNo">Account No:</label>
    <div class="input-box">
        <input type="text" id="accountNo" name="accountNo" placeholder="Enter Account No." value="<?php echo isset($_POST['accountNo']) ? htmlspecialchars($_POST['accountNo']) : ''; ?>" required>
        <i class="bx bxs-id-card account-icon"></i>
    </div>
    <?php if (!empty($accountNoError)): ?>
        <p class="error-message"><?php echo $accountNoError; ?></p>
    <?php endif; ?>
</div>
<div class="form-group">
    <label for="lastName">Last Name:</label>
    <div class="input-box">
        <input type="text" id="lastName" name="lastName" placeholder="Enter last name" value="<?php echo isset($_POST['lastName']) ? htmlspecialchars($_POST['lastName']) : ''; ?>" required>
        <i class="bx bxs-user user-icon"></i>
    </div>
    <?php if (!empty($lastNameError)): ?>
        <p class="error-message"><?php echo $lastNameError; ?></p>
    <?php endif; ?>
</div>
                <button type="submit">Login</button>
                <p class="additional-info">Welcome to Tixnet Pro Customer Portal!</p>
            </form>
        </div>
    </div>
</div>
</body>
</html>