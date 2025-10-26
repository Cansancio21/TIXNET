<?php
session_start(); // Start session for login management
include 'db.php';

// Handle AJAX status check
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && !isset($_POST['login']) && !isset($_POST['firstname'])) {
    header('Content-Type: application/json');
    $username = trim($_POST['username']);
    
    $sql = "SELECT u_status FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        echo json_encode(['status' => $row['u_status']]);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
    
    $stmt->close();
    exit;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

// Initialize variables as empty
$firstname = $lastname = $email = $username = "";
$type = $status = ""; 

$firstnameErr = $lastnameErr = $loginError = $passwordError = $usernameError = "";
$emailErr = $typeErr = $statusErr = $generalError = "";
$hasError = false;

// User Registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['firstname'])) {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $status = trim($_POST['status'] ?? '');

    // Validate firstname (should not contain numbers)
    if (empty($firstname) || !preg_match("/^[a-zA-Z\s-]+$/", $firstname)) {
        $firstnameErr = "Firstname should not contain numbers and is required.";
        $hasError = true;
    }

    // Validate lastname (should not contain numbers)
    if (empty($lastname) || !preg_match("/^[a-zA-Z\s-]+$/", $lastname)) {   
        $lastnameErr = "Lastname should not contain numbers and is required.";
        $hasError = true;
    }

    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailErr = "Valid email is required.";
        $hasError = true;
    }

    // Check if username already exists
    if (empty($username)) {
        $usernameError = "Username is required.";
        $hasError = true;
    } else {
        $sql = "SELECT u_id FROM tbl_user WHERE u_username = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $generalError = "Database prepare error: " . $conn->error;
            $hasError = true;
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $usernameError = "Username already exists.";
                $hasError = true;
            }
            $stmt->close();
        }
    }

    // Validate password (must contain letters, numbers, and special characters)
    if (empty($password) || !preg_match("/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $password)) {
        $passwordError = "Password is weak.";
        $hasError = true;
    } else {
        // Hash the password if validation passes
        $password = password_hash($password, PASSWORD_BCRYPT);
    }

    // Validate type
    if (empty($type) || !in_array($type, ['admin', 'staff', 'technician'])) {
        $typeErr = "Please select a valid user type.";
        $hasError = true;
    }

    // Validate status
    if (empty($status) || !in_array($status, ['pending', 'active'])) {
        $statusErr = "Please select a valid status.";
        $hasError = true;
    }

    if (!$hasError) {
        $sql = "INSERT INTO tbl_user (u_fname, u_lname, u_email, u_username, u_password, u_type, u_status)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $generalError = "Database prepare error: " . $conn->error;
            $hasError = true;
        } else {
            $stmt->bind_param("sssssss", $firstname, $lastname, $email, $username, $password, $type, $status);
            if ($stmt->execute()) {
                error_log("User registered: $username, type: $type");
                header("Location: index.php?success=Registration+successful");
                exit();
            } else {
                $generalError = "Database execution error: " . $stmt->error;
                $hasError = true;
                error_log("Registration failed: " . $stmt->error);
            }
            $stmt->close();
        }
    } else {
        error_log("Registration failed due to validation errors: " . json_encode([
            'firstnameErr' => $firstnameErr,
            'lastnameErr' => $lastnameErr,
            'emailErr' => $emailErr,
            'usernameError' => $usernameError,
            'passwordError' => $passwordError,
            'typeErr' => $typeErr,
            'statusErr' => $statusErr
        ]));
    }
}

// Initialize status message variable
$statusMessage = "";
$statusClass = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Reset errors
    $loginError = "";
    $passwordError = "";
    $userExists = false;

    // Check if username exists
    $sql = "SELECT u_id, u_username, u_password, u_type, u_status FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $generalError = "Database prepare error: " . $conn->error;
    } else {
        $stmt->bind_param("s", $username);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $userExists = true;
                $row = $result->fetch_assoc();

                if (strtolower($row['u_status']) === "pending") {
                    $statusMessage = "Your account is pending.";
                    $statusClass = "pending";
                } elseif (strtolower($row['u_status']) === "active") {
                    $statusMessage = "Your account is active.";
                    $statusClass = "active";
                }

                if (strtolower($row['u_status']) === "pending") {
                    // Let status message show
                } elseif (password_verify($password, $row['u_password'])) {
                    // Store user info in session
                    $_SESSION['username'] = $row['u_username'];
                    $_SESSION['userId'] = $row['u_id'];
                    $_SESSION['user_type'] = $row['u_type'];
                    $_SESSION['logged_in'] = true;

                    // Redirect based on user type - ONLY ADMIN
                    if ($row['u_type'] == 'admin') {
                        header("Location: adminD.php");
                        exit();
                    }
                    // Removed staff and technician redirects
                } else {
                    $passwordError = "Incorrect password. Try again.";
                }
            } else {
                $loginError = "Incorrect username. Try again.";
            }
        } else {
            $generalError = "Database execution error: " . $stmt->error;
        }
        $stmt->close();
    }

    // If user doesn't exist and password was provided
    if (!$userExists && !empty($password)) {
        $passwordError = "Incorrect password. Try again.";
    }
}

// Check for success message
$successMessage = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration & Login</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <div class="container <?php echo ($hasError) ? 'active' : ''; ?>">
        <!-- Login Form -->
        <div class="form-box login">
            <form action="" method="POST">
                <h1>Login</h1>
                <?php if (!empty($successMessage)) { ?>
                    <div class="status-message success"><?php echo $successMessage; ?></div>
                <?php } ?>
                <?php if (!empty($statusMessage)) { ?>
                    <div class="status-message <?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($statusMessage); ?>
                    </div>
                <?php } ?>
                <?php if (!empty($generalError)) { ?>
                    <div class="status-message pending"><?php echo htmlspecialchars($generalError); ?></div>
                <?php } ?>
                <div class="input-box">
                    <input type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username); ?>" required>
                    <i class="bx bxs-user user-icon"></i>
                    <?php if (!empty($loginError)) { ?>
                        <p class="error-message"><?php echo htmlspecialchars($loginError); ?></p>
                    <?php } ?>
                </div>
                <div class="input-box">
                    <input type="password" id="loginPassword" name="password" placeholder="Password" required>
                    <i class="bx bxs-lock-alt password-icon" id="toggleLoginPassword" style="cursor: pointer;"></i>
                    <?php if (!empty($passwordError)) { ?>
                        <p class="error-message"><?php echo htmlspecialchars($passwordError); ?></p>
                    <?php } ?>
                </div> 
                <button type="submit" name="login" class="btn">Login</button>
            </form>
        </div>

        <!-- Registration Form -->
        <div class="form-box register">
            <form action="" method="POST">
                <h1>Registration</h1>
                <?php if (!empty($generalError)) { ?>
                    <div class="error-message"><?php echo htmlspecialchars($generalError); ?></div>
                <?php } ?>
                <div class="input-box">
                    <input type="text" name="firstname" placeholder="Firstname" value="<?php echo htmlspecialchars($firstname); ?>" required>
                    <i class="bx bxs-user firstname-icon"></i>
                    <?php if (!empty($firstnameErr)) { ?>
                        <span class="error"><?php echo $firstnameErr; ?></span>
                    <?php } ?>
                </div>
                <div class="input-box">
                    <input type="text" name="lastname" placeholder="Lastname" value="<?php echo htmlspecialchars($lastname); ?>" required>
                    <i class="bx bxs-user lastname-icon"></i>
                    <?php if (!empty($lastnameErr)) { ?>
                        <span class="error"><?php echo $lastnameErr; ?></span>
                    <?php } ?>
                </div>
                <div class="input-box">
                    <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" required>
                    <i class="bx bxs-envelope email-icon"></i>
                    <?php if (!empty($emailErr)) { ?>
                        <span class="error"><?php echo $emailErr; ?></span>
                    <?php } ?>
                </div>
                <div class="input-box">
                    <input type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username); ?>" required>
                    <i class="bx bxs-user username-icon"></i>
                    <?php if (!empty($usernameError)) { ?>
                        <span class="error"><?php echo $usernameError; ?></span>
                    <?php } ?>
                </div>            
                <div class="input-box">
                    <input type="password" id="password" name="password" placeholder="Password" oninput="validatePassword()" required>
                    <span id="passwordError" class="error"><?php echo $passwordError; ?></span>
                    <i class='bx bxs-lock-alt password-icon' id="togglePassword" style="cursor: pointer;"></i>
                </div>
                <div class="input-box">
                    <select name="type" required>
                        <option value="admin" <?php if ($type == 'admin') echo 'selected'; ?>>Admin</option>
                    </select>
                    <i class='bx bxs-user type-icon'></i>
                    <?php if (!empty($typeErr)) { ?>
                        <span class="error"><?php echo $typeErr; ?></span>
                    <?php } ?>
                </div>
                <div class="input-box">
                    <select name="status" required>
                        <option value="" disabled selected>Select Status</option>
                        <option value="pending" <?php if ($status == 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="active" <?php if ($status == 'active') echo 'selected'; ?>>Active</option>
                    </select>
                    <i class='bx bxs-check-circle status-icon'></i>
                    <?php if (!empty($statusErr)) { ?>
                        <span class="error"><?php echo $statusErr; ?></span>
                    <?php } ?>
                </div>
                <button type="submit" class="btn">Register</button>
            </form>
        </div>

        <!-- Toggle Panels -->
        <div class="toggle-box">
            <div class="toggle-panel toggle-left">
                <h1>Hello Welcome!</h1>
                <p>Don't have an account?</p>
                <button class="btn register-btn">Register</button>
            </div>
            <div class="toggle-panel toggle-right">
                <h1>Welcome Back!</h1>
                <p>Already have an account?</p>
                <button class="btn login-btn">Login</button>
            </div>
        </div>
    </div>

    <script>
        // Function to validate password strength
        function validatePassword() {
            const passwordInput = document.getElementById('password');
            const passwordError = document.getElementById('passwordError');
            const password = passwordInput.value;

            const strongPasswordPattern = /^(?=.*[a-zA-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            if (strongPasswordPattern.test(password)) {
                passwordError.textContent = "Password is strong.";
                passwordError.style.color = "green";
            } else {
                passwordError.textContent = "Password is weak.";
                passwordError.style.color = "red";
            }
        }

        // Toggle password visibility
        document.addEventListener('DOMContentLoaded', function () {
            // Password visibility toggles
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const loginPasswordInput = document.getElementById('loginPassword');
            const toggleLoginPassword = document.getElementById('toggleLoginPassword');

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.classList.remove('bxs-lock-alt', 'bx-show');
                    this.classList.add(type === 'password' ? 'bxs-lock-alt' : 'bx-show');
                });
            }

            if (toggleLoginPassword && loginPasswordInput) {
                toggleLoginPassword.addEventListener('click', function () {
                    const type = loginPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    loginPasswordInput.setAttribute('type', type);
                    this.classList.remove('bxs-lock-alt', 'bx-show');
                    this.classList.add(type === 'password' ? 'bxs-lock-alt' : 'bx-show');
                });
            }

            // Fade out status messages for active and success
            const statusMessages = document.querySelectorAll('.status-message.active, .status-message.success');
            statusMessages.forEach(statusMessage => {
                setTimeout(() => {
                    statusMessage.classList.add('fade-out');
                    setTimeout(() => {
                        statusMessage.style.display = 'none';
                    }, 500);
                }, 3000);
            });

            // Ensure login view on success message
            if (window.location.search.includes('success')) {
                const container = document.querySelector('.container');
                if (container) {
                    container.classList.remove('active');
                }
            }

            // Toggle between Login & Register
            const container = document.querySelector(".container");
            const registerBtn = document.querySelector(".register-btn");
            const loginBtn = document.querySelector(".login-btn");

            if (container && registerBtn && loginBtn) {
                registerBtn.addEventListener("click", () => {
                    container.classList.add("active");
                });

                loginBtn.addEventListener("click", () => {
                    container.classList.remove("active");
                });
            }

            // Poll for status updates
            function checkStatus() {
                const usernameInput = document.querySelector('input[name="username"]');
                if (usernameInput && usernameInput.value) {
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'username=' + encodeURIComponent(usernameInput.value)
                    })
                    .then(response => response.json())
                    .then(data => {
                        const statusDiv = document.querySelector('.status-message');
                        if (data.status) {
                            const newStatus = data.status.toLowerCase();
                            const message = newStatus === 'pending' ? 'Your account is pending.' : 'Your account is active.';
                            const statusClass = newStatus === 'pending' ? 'pending' : 'active';

                            if (!statusDiv) {
                                const newDiv = document.createElement('div');
                                newDiv.className = `status-message ${statusClass}`;
                                newDiv.textContent = message;
                                document.querySelector('.form-box.login h1').insertAdjacentElement('afterend', newDiv);
                                if (newStatus === 'active') {
                                    setTimeout(() => {
                                        newDiv.classList.add('fade-out');
                                        setTimeout(() => {
                                            newDiv.style.display = 'none';
                                        }, 500);
                                    }, 3000);
                                }
                            } else if (statusDiv.textContent !== message) {
                                statusDiv.textContent = message;
                                statusDiv.className = `status-message ${statusClass}`;
                                statusDiv.style.display = 'block';
                                statusDiv.classList.remove('fade-out');
                                if (newStatus === 'active') {
                                    setTimeout(() => {
                                        statusDiv.classList.add('fade-out');
                                        setTimeout(() => {
                                            statusDiv.style.display = 'none';
                                        }, 500);
                                    }, 3000);
                                }
                            }
                        }
                    })
                    .catch(error => console.error('Error checking status:', error));
                }
            }

            setInterval(checkStatus, 2000);
        });
    </script>
</body>
</html>