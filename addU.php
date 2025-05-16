<?php
session_start(); // Start session
include 'db.php'; // Your database connection

// Include PHPMailer classes
require 'PHPmailer-master/PHPmailer-master/src/Exception.php';
require 'PHPmailer-master/PHPmailer-master/src/PHPMailer.php';
require 'PHPmailer-master/PHPmailer-master/src/SMTP.php';

// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$firstnameErr = $lastnameErr = $emailErr = $usernameErr = $passwordErr = "";
$firstname = $lastname = $email = $username = $password = $type = $status = "";
$hasError = false;

// Check if tbl_user exists
$result = $conn->query("SHOW TABLES LIKE 'tbl_user'");
if ($result->num_rows == 0) {
    die("Error: Table 'tbl_user' does not exist in the 'task_management' database.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $type = trim($_POST['type']);
    $status = trim($_POST['status']);

    // Validation (email format validation removed)
    if (!preg_match("/^[a-zA-Z\s-]+$/", $firstname)) {
        $firstnameErr = "Firstname should not contain numbers.";
        $hasError = true;
    }

    if (!preg_match("/^[a-zA-Z\s-]+$/", $lastname)) {
        $lastnameErr = "Lastname should not contain numbers.";
        $hasError = true;
    }

    if (empty($email)) {
        $emailErr = "Email is required.";
        $hasError = true;
    }

    if (empty($username)) {
        $usernameErr = "Username is required.";
        $hasError = true;
    }

    if (!preg_match("/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $password)) {
        $passwordErr = "Password must be at least 8 characters with letters, numbers, and special characters.";
        $hasError = true;
    }

    if (empty($type)) {
        $hasError = true;
    }

    if (empty($status)) {
        $hasError = true;
    }

    if (!$hasError) {
        // Log submission for debugging
        $log = date('Y-m-d H:i:s') . " - Attempted to add user: $firstname $lastname, $email, $username\n";
        file_put_contents('user_submission_log.txt', $log, FILE_APPEND);

        // Hash the password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO tbl_user (u_fname, u_lname, u_email, u_username, u_password, u_type, u_status)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("sssssss", $firstname, $lastname, $email, $username, $passwordHash, $type, $status);

        if ($stmt->execute()) {
            // Send the confirmation email using PHPMailer
            $mail = new PHPMailer(true); // Enable exceptions

            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'jonwilyammayormita@gmail.com'; // Your Gmail address
                $mail->Password = 'mqkcqkytlwurwlks'; // Your Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Recipients
                $mail->setFrom('jonwilyammayormita@gmail.com', 'Your Website');
                $mail->addAddress($email, "$firstname $lastname");

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Your Account Has Been Created';
                $mail->Body = "
                    <html>
                    <head>
                        <title>Your Account Details</title>
                    </head>
                    <body>
                        <p>Dear $firstname $lastname,</p>
                        <p>Your account has been successfully created. Here are your login credentials:</p>
                        <p><strong>Username:</strong> $username</p>
                        <p><strong>Password:</strong> $password</p>
                        <p>Please use these credentials to log in to our system by clicking the link below:</p>
                        <p><a href='http://localhost/TIMSSS/index.php'>Login Page</a></p>
                        <p>For security reasons, we recommend changing your password after first login.</p>
                        <p>Best regards,<br>Your System Administrator</p>
                    </body>
                    </html>
                ";
                $mail->AltBody = "Dear $firstname $lastname,\n\nYour account has been successfully created. Here are your login credentials:\nUsername: $username\nPassword: $password\n\nPlease use these credentials to log in to our system at http://localhost/TIMSSS/index.php\n\nFor security reasons, we recommend changing your password after first login.\n\nBest regards,\nYour System Administrator";

                // Send the email
                $mail->send();
                
                // Store success message in session and redirect (PRG pattern)
                $_SESSION['message'] = "User has been registered successfully. A confirmation email has been sent.";
                header("Location: viewU.php");
                exit();
            } catch (Exception $e) {
                // Store error message in session and redirect
                $_SESSION['message'] = "User registered, but error sending confirmation email: " . $mail->ErrorInfo;
                header("Location: viewU.php");
                exit();
            }
        } else {
            die("Execution failed: " . $stmt->error);
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User</title>
    <link rel="stylesheet" href="addUs.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <a href="viewU.php" class="back-icon">
                <i class='bx bx-arrow-back'></i>
            </a>
            <h1>Add New User</h1>
            <div class="alert-container">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
            </div>
            <form method="POST" action="" class="form" id="addUserForm">
                <div class="form-row">
                    <label for="firstname">First Name:</label>
                    <div class="input-box">
                        <input type="text" id="firstname" name="firstname" placeholder="First Name" value="<?php echo htmlspecialchars($firstname); ?>">
                    </div>
                    <span class="error"><?php echo $firstnameErr; ?></span>
                </div>
                <div class="form-row">
                    <label for="lastname">Last Name:</label>
                    <div class="input-box">
                        <input type="text" id="lastname" name="lastname" placeholder="Last Name" value="<?php echo htmlspecialchars($lastname); ?>">
                    </div>
                    <span class="error"><?php echo $lastnameErr; ?></span>
                </div>
                <div class="form-row">
                    <label for="email">Email:</label>
                    <div class="input-box">
                        <input type="text" id="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                    <span class="error"><?php echo $emailErr; ?></span>
                </div>
                <div class="form-row">
                    <label for="username">Username:</label>
                    <div class="input-box">
                        <input type="text" id="username" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username); ?>">
                    </div>
                    <span class="error"><?php echo $usernameErr; ?></span>
                </div>
                <div class="form-row">
                    <label for="password">Password:</label>
                    <div class="input-box">
                        <input type="password" id="password" name="password" placeholder="Password">
                        <i class='bx bxs-lock-alt password-icon' id="togglePassword" style="cursor: pointer;"></i>
                    </div>
                    <span class="error"><?php echo $passwordErr; ?></span>
                    <span id="passwordError"></span>
                </div>
                <div class="form-row">
                    <label for="type">User Type:</label>
                    <div class="input-box">
                        <select id="type" name="type" required>
                            <option value="">Select Type</option>
                            <option value="admin" <?php if($type == 'admin') echo 'selected'; ?>>Admin</option>
                            <option value="staff" <?php if($type == 'staff') echo 'selected'; ?>>Staff</option>
                            <option value="technician" <?php if($type == 'technician') echo 'selected'; ?>>Technician</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label for="status">Account Status:</label>
                    <div class="input-box">
                        <select id="status" name="status" required>
                            <option value="">Select Status</option>
                            <option value="active" <?php if($status == 'active') echo 'selected'; ?>>Active</option>
                            <option value="pending" <?php if($status == 'pending') echo 'selected'; ?>>Pending</option>
                        </select>
                    </div>
                </div>
                <div class="button-container">
                    <button type="submit" id="submitBtn">Add User</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('bxs-lock-alt');
            this.classList.toggle('bxs-lock-open-alt');
        });

        // Function to validate password strength
        function validatePassword() {
            const passwordInput = document.getElementById('password');
            const passwordError = document.getElementById('passwordError');
            const password = passwordInput.value;

            // Regular expression for strong password
            const strongPasswordPattern = /^(?=.*[a-zA-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;

            if (password === '') {
                passwordError.textContent = '';
            } else if (strongPasswordPattern.test(password)) {
                passwordError.textContent = "Password is strong.";
                passwordError.style.color = "green";
            } else {
                passwordError.textContent = "Password is weak.";
                passwordError.style.color = "red";
            }
        }

        // Add event listener for real-time password validation
        document.getElementById('password').addEventListener('input', validatePassword);

        // Prevent multiple form submissions
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true; // Disable button to prevent multiple clicks
            submitBtn.textContent = 'Submitting...'; // Update button text
        });
    </script>
</body>
</html>