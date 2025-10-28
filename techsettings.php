<?php 
session_start();
include 'db.php';

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Check if the user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_type'])) { 
    header("Location: index.php");
    exit(); 
}

$username = $_SESSION['username'];
$userType = $_SESSION['user_type'];
$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;

// Log session data for debugging
error_log("Session data: userId=$userId, username=$username, userType=$userType");

// Check if c_password column exists for customers
if ($userType === 'customer') {
    $result = $conn->query("SHOW COLUMNS FROM tbl_customer LIKE 'c_password'");
    if ($result->num_rows == 0) {
        error_log("c_password column missing in tbl_customer");
        echo "<script>alert('Database error: c_password column is missing in tbl_customer. Please contact the administrator.');</script>";
        exit();
    }
} else {
    $result = $conn->query("SHOW COLUMNS FROM tbl_user LIKE 'u_password'");
    if ($result->num_rows == 0) {
        error_log("u_password column missing in tbl_user");
        echo "<script>alert('Database error: u_password column is missing in tbl_user. Please contact the administrator.');</script>";
        exit();
    }
}

// Fetch user details from database
if ($userType === 'customer') {
    $stmt = $conn->prepare("SELECT c_fname, c_lname, c_address, c_contact, c_email, c_status FROM tbl_customer WHERE c_id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $stmt->bind_result($firstName, $lastName, $address, $contact, $email, $status);
    $fetched = $stmt->fetch();
    error_log("Customer fetch: " . ($fetched ? "Success, c_id=$userId" : "Failed, c_id=$userId not found"));
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT u_fname, u_lname, u_email, u_type, u_status FROM tbl_user WHERE u_username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($firstName, $lastName, $email, $userTypeDb, $status);
    $fetched = $stmt->fetch();
    error_log("User fetch: " . ($fetched ? "Success, username=$username" : "Failed, username=$username not found"));
    $stmt->close();
}

// Default avatar
$avatarPath = 'default-avatar.png'; 
$avatarFolder = 'Uploads/avatars/';

// Ensure the avatars directory exists
if (!is_dir($avatarFolder)) {
    mkdir($avatarFolder, 0777, true);
}

// Check if user has a custom avatar
$avatarId = $userType === 'customer' ? $userId : $username;
$userAvatar = $avatarFolder . $avatarId . '.png';
if (file_exists($userAvatar)) {
    $avatarPath = $userAvatar . '?' . time();
}

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $uploadFile = $_FILES['avatar'];
    $targetFile = $avatarFolder . $avatarId . '.png'; 
    $imageFileType = strtolower(pathinfo($uploadFile['name'], PATHINFO_EXTENSION));

    if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif', 'jfif'])) {
        if (move_uploaded_file($uploadFile['tmp_name'], $targetFile)) {
            $_SESSION['avatarPath'] = 'Uploads/avatars/' . $avatarId . '.png' . '?' . time();
            header("Location: settings.php");
            exit();
        } else {
            echo "<script>alert('Error uploading avatar.');</script>";
        }
    } else {
        echo "<script>alert('Invalid image format. Please upload JPG, PNG, or GIF images.');</script>";
    }
}

// Handle account information and password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data: " . print_r($_POST, true));

    $errors = [];
    $success = false;

    // Handle account information update
    if (isset($_POST['first_name'], $_POST['last_name'], $_POST['email'])) {
        $newFirstName = $_POST['first_name'];
        $newLastName = $_POST['last_name'];
        $newEmail = $_POST['email'];

        if ($userType === 'customer') {
            $newAddress = $_POST['address'] ?? '';
            $newContact = $_POST['contact'] ?? '';
            $stmt = $conn->prepare("UPDATE tbl_customer SET c_fname = ?, c_lname = ?, c_address = ?, c_contact = ?, c_email = ? WHERE c_id = ?");
            $stmt->bind_param("ssssss", $newFirstName, $newLastName, $newAddress, $newContact, $newEmail, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE tbl_user SET u_fname = ?, u_lname = ?, u_email = ? WHERE u_username = ?");
            $stmt->bind_param("ssss", $newFirstName, $newLastName, $newEmail, $username);
        }

        if ($stmt->execute()) {
            error_log("Account information updated for user: " . ($userType === 'customer' ? $userId : $username) . ", rows affected: " . $stmt->affected_rows);
            $success = true;
        } else {
            error_log("Account update failed: " . $stmt->error);
            $errors[] = "Error updating account information: " . $stmt->error;
        }
        $stmt->close();
    }

    // Handle password change
    if (isset($_POST['old_password'], $_POST['new_password'], $_POST['confirm_password']) && !empty($_POST['old_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        $oldPassword = $_POST['old_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Fetch current password
        if ($userType === 'customer') {
            $stmt = $conn->prepare("SELECT c_password FROM tbl_customer WHERE c_id = ?");
            $stmt->bind_param("s", $userId);
        } else {
            $stmt = $conn->prepare("SELECT u_password FROM tbl_user WHERE u_username = ?");
            $stmt->bind_param("s", $username);
        }
        $result = $stmt->execute();
        error_log("Password fetch query executed: " . ($result ? 'Success' : 'Failed'));

        if ($result) {
            $stmt->bind_result($storedPassword);
            $fetched = $stmt->fetch();
            error_log("Stored password: " . ($fetched ? ($storedPassword ? 'Retrieved' : 'Empty') : 'Not fetched'));
            $stmt->close();

            if ($fetched && $storedPassword) {
                if (password_verify($oldPassword, $storedPassword)) {
                    if ($newPassword === $confirmPassword) {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        error_log("New hashed password: " . $hashedPassword);
                        if ($userType === 'customer') {
                            $stmt = $conn->prepare("UPDATE tbl_customer SET c_password = ? WHERE c_id = ?");
                            $stmt->bind_param("ss", $hashedPassword, $userId);
                        } else {
                            $stmt = $conn->prepare("UPDATE tbl_user SET u_password = ? WHERE u_username = ?");
                            $stmt->bind_param("ss", $hashedPassword, $username);
                        }
                        $result = $stmt->execute();
                        $affectedRows = $stmt->affected_rows;
                        error_log("Password update query executed: " . ($result ? "Success, rows affected: $affectedRows" : "Failed"));
                        if ($result && $affectedRows > 0) {
                            $_SESSION['password_updated'] = true;
                            error_log("Password updated successfully for user: " . ($userType === 'customer' ? $userId : $username));
                            $success = true;
                        } else {
                            error_log("Password update failed: " . $stmt->error . ", rows affected: $affectedRows");
                            $errors[] = "Error updating password: " . ($stmt->error ?: "No rows updated, check user ID or username.");
                        }
                        $stmt->close();
                    } else {
                        $errors[] = "New passwords do not match.";
                    }
                } else {
                    error_log("Password verification failed for user: " . ($userType === 'customer' ? $userId : $username));
                    $errors[] = "Incorrect old password.";
                }
            } else {
                error_log("No password found for user: " . ($userType === 'customer' ? $userId : $username));
                $errors[] = "No password found for this user.";
            }
        } else {
            error_log("Password fetch query failed: " . $stmt->error);
            $errors[] = "Error fetching password: " . $stmt->error;
            $stmt->close();
        }
    }

    // Handle errors and success
    if (!empty($errors)) {
        echo "<script>alert('" . addslashes(implode("\\n", $errors)) . "');</script>";
    }
    if ($success) {
        // Redirect based on user type
        if ($userType === 'staff' || $userType === 'technician') {
            header("Location: technician_staff.php");
        } else {
            header("Location: index.php");
        }
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="techsetting.css">
    <style>
        .table-box {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .input-box {
            position: relative;
            margin-bottom: 15px;
        }
        .input-box label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
        }
        .input-box i {
            position: absolute;
            right: 10px;
            top: 65%;
            transform: translateY(-50%);
            color: #777;
            font-size: 18px;
            z-index: 1;
        }
        .input-box input {
            width: 100%;
            max-width: 800px;
            padding: 10px 40px 10px 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 14px;
            background: var(--light);
            outline: none;
            margin: 0 auto;
        }
        .input-box input:focus {
            border-color: var(--primary);
        }
        .input-box .toggle-password {
            position: absolute;
            right: 10px;
            top: 65%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 18px;
            z-index: 1;
        }
        .btn {
            padding: 10px 20px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 200px;
            margin: 10px auto 0;
            display: block;
        }
        .btn:hover {
            background-color: var(--secondary);
        }
        .section-header {
            grid-column: 1 / -1;
            margin: 1px 0;
            font-size: 18px;
            color: var(--primary);
            margin-left: 50px;
        }
        .avatar-container {
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
            margin-bottom: 15px;
        }
        .avatar-container .user-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--primary);
        }
        .avatar-container .user-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-container .user-icon i {
            font-size: 40px;
            color: var(--primary);
        }
        .button-container {
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
        }
    </style>
    <script>
        function togglePasswordVisibility(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            icon.classList.toggle('bx-show');
            icon.classList.toggle('bx-hide');
        }

        function showRestrictedMessage() {
            alert('Access restricted for technicians.');
        }

        function toggleSupportInput() {
            const inputDiv = document.getElementById('supportTicketInput');
            inputDiv.style.display = inputDiv.style.display === 'none' ? 'block' : 'none';
        }

        function goToSupportTicket() {
            const customerId = document.getElementById('supportCustomerId').value;
            if (customerId) {
                window.location.href = 'support_tickets.php?customer_id=' + encodeURIComponent(customerId);
            } else {
                alert('Please enter a Customer ID.');
            }
        }

        function debouncedSearchUsers() {
            console.log('Search triggered');
        }
    </script>
</head>
<body>  
    <div class="wrapper">
        <div class="sidebar">
            <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
            <ul>
                <?php if ($userType === 'customer'): ?>
                    <li><a href="portal.php"><img src="image/main.png" alt="Dashboard" class="icon" /> <span>Dashboard</span></a></li>
                    <li><a href="suppT.php" class="active"><img src="image/ticket.png" alt="Support Tickets" class="icon" /> <span>Support Tickets</span></a></li>
                <?php elseif ($userType === 'admin'): ?>
                    <li><a href="adminD.php"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
                    <li><a href="viewU.php"><i class="fas fa-users icon"></i> <span>View Users</span></a></li>
                    <li><a href="regular_close.php"><i class="fas fa-ticket-alt icon"></i> <span>Ticket Record</span></a></li>
                    <li><a href="logs.php"><i class="fas fa-file-alt icon"></i> <span>Logs</span></a></li>
                    <li><a href="returnT.php"><i class="fas fa-box icon"></i> <span>Asset Record</span></a></li>
                    <li><a href="AdminPayments.php"><i class="fas fa-credit-card icon"></i> <span>Transactions</span></a></li>
                <?php elseif ($userType === 'staff'): ?>
                     <li><a href="staffD.php"><i class="fas fa-ticket-alt icon"></i> <span>Regular Tickets</span></a></li>
                     <li><a href="assetsT.php"><i class="fas fa-boxes icon"></i> <span>Assets</span></a></li>
                     <li><a href="AllCustomersT.php"><i class="fas fa-clipboard-check icon"></i> <span>Customer Ticket</span></a></li>
                     <li><a href="customersT.php"><i class="fas fa-user-friends icon"></i> <span>Customers</span></a></li> 
                     <li><a href="AssignTech.php"><i class="fas fa-tools icon"></i> <span>Technicians</span></a></li>
                     <li><a href="Payments.php"><i class="fas fa-credit-card icon"></i> <span>Transactions</span></a></li>
                     <?php elseif ($userType === 'technician'): ?>
                    <li><a href="technicianD.php"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
                    <li><a href="techBorrowed.php"><i class="fas fa-hand-holding icon"></i> <span>Borrowed Assets</span></a></li>
                    <li><a href="TechCustomers.php"><i class="fas fa-user-friends icon"></i> <span>Customers</span></a></li>
            <?php endif; ?>
            </ul>
            <footer>
    <?php if ($userType === 'technician' || $userType === 'staff'): ?>
        <a href="technician_staff.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
    <?php else: ?>
        <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
    <?php endif; ?>
</footer>
        </div>

        <div class="container">
            <div class="upper"> 
                <h1>Account Settings</h1>
                <div class="user-profile">
                    <div class="user-icon">
                        <a href="image.php">
                            <?php 
                            $cleanAvatarPath = preg_replace('/\?\d+$/', '', $avatarPath);
                            if (!empty($avatarPath) && file_exists($cleanAvatarPath)) {
                                echo "<img src='" . htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8') . "' alt='User Avatar'>";
                            } else {
                                echo "<i class='fas fa-user-circle'></i>";
                            }
                            ?>
                        </a>
                    </div>
                    <div class="user-details">
                        <span><?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?></span>
                        <small><?php echo htmlspecialchars(ucfirst($userType), ENT_QUOTES, 'UTF-8'); ?></small>
                    </div>
                    <a href="settings.php" class="settings-link">
                        <i class="fas fa-cog"></i>
                  
                    </a>
                </div>
            </div>

            <div class="account-settings">
                <form action="" method="POST" enctype="multipart/form-data">
                    <section class="account-section">
                        <div class="table-box">
                            <div class="avatar-container">
                                <div class="user-icon">
                                    <?php 
                                    $cleanAvatarPath = preg_replace('/\?\d+$/', '', $avatarPath);
                                    if (!empty($avatarPath) && file_exists($cleanAvatarPath)) {
                                        echo "<img src='" . htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8') . "' alt='User Avatar'>";
                                    } else {
                                        echo "<i class='fas fa-user-circle'></i>";
                                    }
                                    ?>
                                </div>
                            </div>
                            <h2 class="section-header">Account Information</h2>
                            <div class="input-box">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($firstName); ?>" placeholder="First Name" required>
                                <i class='bx bxs-user'></i>
                            </div>
                            <div class="input-box">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>" placeholder="Last Name" required>
                                <i class='bx bxs-user'></i>
                            </div>
                            <?php if ($userType === 'customer'): ?>
                                <div class="input-box">
                                    <label for="address">Address</label>
                                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($address ?? ''); ?>" placeholder="Address" required>
                                    <i class='bx bxs-home'></i>
                                </div>
                                <div class="input-box">
                                    <label for="contact">Contact</label>
                                    <input type="tel" id="contact" name="contact" value="<?php echo htmlspecialchars($contact ?? ''); ?>" placeholder="Contact Number" required>
                                    <i class='bx bxs-phone'></i>
                                </div>
                            <?php endif; ?>
                            <div class="input-box">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Email" required>
                                <i class='bx bxs-envelope'></i>
                            </div>
                            <div class="input-box">
                                <label for="status">Status</label>
                                <input type="text" id="status" name="status" value="<?php echo htmlspecialchars(ucfirst($status)); ?>" placeholder="Status" disabled>
                                <i class='bx bxs-check-circle'></i>
                            </div>
                            <?php if ($userType !== 'customer'): ?>
                                <div class="input-box">
                                    <label for="type">Type</label>
                                    <input type="text" id="type" name="type" value="<?php echo htmlspecialchars(ucfirst($userType)); ?>" placeholder="Type" disabled>
                                    <i class='bx bxs-shield'></i>
                                </div>
                            <?php endif; ?>
                            <h2 class="section-header">Change Password</h2>
                            <div class="input-box">
                                <label for="old_password">Old Password</label>
                                <input type="password" id="old_password" name="old_password" placeholder="Old Password">
                                <i class='bx bx-show toggle-password' id="toggleOldPassword" onclick="togglePasswordVisibility('old_password', 'toggleOldPassword')"></i>
                            </div>
                            <div class="input-box">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" placeholder="New Password">
                                <i class='bx bx-show toggle-password' id="toggleNewPassword" onclick="togglePasswordVisibility('new_password', 'toggleNewPassword')"></i>
                            </div>
                            <div class="input-box">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password">
                                <i class='bx bx-show toggle-password' id="toggleConfirmPassword" onclick="togglePasswordVisibility('confirm_password', 'toggleConfirmPassword')"></i>
                            </div>
                            <div class="button-container">
                                <button type="submit" class="btn">Save Changes</button>
                            </div>
                        </div>
                    </section>
                </form>
            </div>
        </div>
    </div>
</body>
</html>