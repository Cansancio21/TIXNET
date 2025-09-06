<?php 
session_start();
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) { 
    header("Location: index.php");
    exit(); 
}

// Check if the customer account number is provided
if (!isset($_GET['account_no'])) {
    $_SESSION['error'] = "No customer account number provided.";
    header("Location: customersT.php");
    exit();
}

$accountNo = $_GET['account_no'];

// Fetch customer details based on the customer account number
$sql = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_coordinates, c_equipment 
        FROM tbl_customer WHERE c_account_no = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    $_SESSION['error'] = "Customer query preparation failed: " . $conn->error;
    header("Location: customersT.php");
    exit();
}
$stmt->bind_param("s", $accountNo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Customer not found.";
    header("Location: customersT.php");
    exit();
}
$customer = $result->fetch_assoc();
$stmt->close();

// Debug: Log the c_napport, c_plan, and c_coordinates values to inspect their format
error_log("c_napport value from database: '" . $customer['c_napport'] . "'");
error_log("c_plan value from database: '" . $customer['c_plan'] . "'");
error_log("c_coordinates value from database: '" . $customer['c_coordinates'] . "'");

// Initialize variables for user data
$username = $_SESSION['username'];
$lastName = '';
$firstName = '';
$userType = '';
$avatarPath = 'default-avatar.png';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $username . '.png';

if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} else {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$avatarPath = $_SESSION['avatarPath'];

// Fetch user data from the database
if (!$conn) {
    $_SESSION['error'] = "Database connection failed: " . mysqli_connect_error();
    header("Location: editC.php?account_no=$accountNo");
    exit();
}

$sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
$stmt = $conn->prepare($sqlUser);
if (!$stmt) {
    $_SESSION['error'] = "User query preparation failed: " . $conn->error;
    header("Location: editC.php?account_no=$accountNo");
    exit();
}
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$resultUser = $stmt->get_result();

if ($resultUser->num_rows > 0) {
    $row = $resultUser->fetch_assoc();
    $firstName = $row['u_fname'];
    $lastName = $row['u_lname'];
    $userType = $row['u_type'];
}
$stmt->close();

// Initialize form variables and error messages
$firstname = $customer['c_fname'];
$lastname = $customer['c_lname'];
$contact = $customer['c_contact'];
$email = $customer['c_email'];
$dob = $customer['c_date'];
$napname = $customer['c_napname'];
$napport = trim((string)$customer['c_napport']);
$macaddress = $customer['c_macaddress'];
$status = $customer['c_status'];
$purok = $customer['c_purok'];
$barangay = $customer['c_barangay'];
$coordinates = $customer['c_coordinates'];
$plan = trim((string)$customer['c_plan']);
$equipment = $customer['c_equipment'];

$firstnameErr = $lastnameErr = $contactErr = $emailErr = $dobErr = "";
$napnameErr = $napportErr = $macaddressErr = $statusErr = "";
$purokErr = $barangayErr = $coordinatesErr = $planErr = $equipmentErr = "";
$hasError = false;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dob = trim($_POST['date'] ?? '');
    $napname = trim($_POST['napname'] ?? '');
    $napport = trim($_POST['napport'] ?? '');
    $macaddress = trim($_POST['macaddress'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $purok = trim($_POST['purok'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $coordinates = trim($_POST['coordinates'] ?? '');
    $plan = trim($_POST['plan'] ?? '');
    $equipment = trim($_POST['equipment'] ?? '');

    // Validate inputs
    if (!preg_match("/^[a-zA-Z\s-]+$/", $firstname)) {
        $firstnameErr = "First Name should not contain numbers or special characters.";
        $hasError = true;
    }
    if (!preg_match("/^[a-zA-Z\s-]+$/", $lastname)) {
        $lastnameErr = "Last Name should not contain numbers or special characters.";
        $hasError = true;
    }
    if (!preg_match("/^[0-9]{10,11}$/", $contact)) {
        $contactErr = "Contact must be a valid 10-11 digit phone number.";
        $hasError = true;
    }
    if (!preg_match("/^[a-zA-Z0-9:-]+$/", $macaddress)) {
        $macaddressErr = "MAC Address should not contain special characters except colon or hyphen.";
        $hasError = true;
    }
    if (empty($dob)) {
        $dobErr = "Subscription Date is required.";
        $hasError = true;
    }
    if (empty($status)) {
        $statusErr = "Customer Status is required.";
        $hasError = true;
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailErr = "Valid email is required.";
        $hasError = true;
    }
    if (!preg_match("/^[a-zA-Z\s]+$/", $barangay)) {
        $barangayErr = "Barangay should contain letters only.";
        $hasError = true;
    }
    if (!preg_match("/^-?\d{1,3}\.\d{5,},\s*-?\d{1,3}\.\d{5,}$/", $coordinates)) {
        $coordinatesErr = "Coordinates must be in the format 'latitude,longitude' (e.g., 14.12345,121.67890).";
        $hasError = true;
    }
    if (empty($plan)) {
        $planErr = "Internet Plan is required.";
        $hasError = true;
    }
    if (empty($equipment)) {
        $equipmentErr = "Equipment is required.";
        $hasError = true;
    }

    // Update database if no errors
    if (!$hasError) {
        $sqlUpdate = "UPDATE tbl_customer SET c_fname = ?, c_lname = ?, c_purok = ?, c_barangay = ?, c_contact = ?, c_email = ?, c_date = ?, c_napname = ?, c_napport = ?, c_macaddress = ?, c_status = ?, c_plan = ?, c_coordinates = ?, c_equipment = ? WHERE c_account_no = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        if (!$stmtUpdate) {
            $_SESSION['error'] = "Prepare failed: " . $conn->error;
            error_log("SQL Prepare Error: " . $conn->error);
            header("Location: editC.php?account_no=$accountNo");
            exit();
        }

        $stmtUpdate->bind_param("sssssssssssssss", $firstname, $lastname, $purok, $barangay, $contact, $email, $dob, $napname, $napport, $macaddress, $status, $plan, $coordinates, $equipment, $accountNo);

        if ($stmtUpdate->execute()) {
            $_SESSION['message'] = "Customer updated successfully.";
            header("Location: customersT.php");
            exit();
        } else {
            $_SESSION['error'] = "Execution failed: " . $stmtUpdate->error;
            error_log("SQL Execution Error: " . $stmtUpdate->error);
            header("Location: editC.php?account_no=$accountNo");
            exit();
        }
        $stmtUpdate->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer</title>
    <link rel="stylesheet" href="editCC.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="TixNet Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
        <li><a href="staffD.php"><i class="fas fa-ticket-alt icon"></i> <span>Regular Tickets</span></a></li>
        <li><a href="assetsT.php"><i class="fas fa-boxes icon"></i> <span>Assets</span></a></li>
        <li><a href="AllCustomersT.php"><i class="fas fa-clipboard-check icon"></i> <span>Customers Ticket</span></a></li>
        <li><a href="customersT.php"><i class="fas fa-user-friends icon"></i> <span>Customers</span></a></li>
        <li><a href="borrowedStaff.php"><i class="fas fa-hand-holding icon"></i> <span>Borrowed Assets</span></a></li>
        <li><a href="addC.php"><i class="fas fa-user-plus icon"></i> <span>Add Customer</span></a></li>
        <li><a href="AssignTech.php"><i class="fas fa-tools icon"></i> <span>Technicians</span></a></li>
        <li><a href="Payments.php"><i class="fas fa-credit-card icon"></i> <span>Payment Transactions</span></a></li>
    </ul>
    <footer>
        <a href="technician_staff.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </footer>
    </div>

    <div class="container">
        <div class="upper"> 
            <h1>Edit Customer</h1>
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
                    <span>Settings</span>
                </a>
            </div>
        </div>
          
        <div class="alert-container">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
        </div>

        <div class="form-box glass-container">
            <h2>Customer Profile</h2>
            <hr class="title-line">
            <form action="" method="POST" id="customerForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="accountNo">Customer Account No.</label>
                        <input type="text" id="accountNo" name="accountNo" value="<?php echo htmlspecialchars($accountNo, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="firstname">First Name <span class="required">*</span></label>
                        <input type="text" id="firstname" name="firstname" placeholder="e.g., John" value="<?php echo htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($firstnameErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="lastname">Last Name <span class="required">*</span></label>
                        <input type="text" id="lastname" name="lastname" placeholder="e.g., Doe" value="<?php echo htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($lastnameErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="purok">Purok Name</label>
                        <input type="text" id="purok" name="purok" placeholder="e.g., Purok 3" value="<?php echo htmlspecialchars($purok, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="error"><?php echo htmlspecialchars($purokErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="barangay">Barangay <span class="required">*</span></label>
                        <input type="text" id="barangay" name="barangay" placeholder="e.g., San Isidro" value="<?php echo htmlspecialchars($barangay, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($barangayErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="contact">Contact Number <span class="required">*</span></label>
                        <input type="text" id="contact" name="contact" placeholder="e.g., 09123456789" value="<?php echo htmlspecialchars($contact, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($contactErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" placeholder="e.g., john.doe@example.com" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($emailErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                     <div class="form-group">
                        <label for="coordinates">Coordinates <span class="required">*</span></label>
                        <input type="text" id="coordinates" name="coordinates" placeholder="e.g., 14.12345,121.67890" value="<?php echo htmlspecialchars($coordinates, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($coordinatesErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>

                <h2>Advance Profile</h2>
                <hr class="title-line">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="date">Subscription Date <span class="required">*</span></label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($dob, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($dobErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="napname">NAP Device <span class="required">*</span></label>
                        <select id="napname" name="napname" required>
                            <option value="" <?php echo ($napname === '') ? 'selected' : ''; ?>>Select NAP Device</option>
                            <option value="Lp1 Np1" <?php echo ($napname === 'Lp1 Np1') ? 'selected' : ''; ?>>Lp1 Np1</option>
                            <option value="Lp1 Np2" <?php echo ($napname === 'Lp1 Np2') ? 'selected' : ''; ?>>Lp1 Np2</option>
                            <option value="Lp1 Np3" <?php echo ($napname === 'Lp1 Np3') ? 'selected' : ''; ?>>Lp1 Np3</option>
                            <option value="Lp1 Np4" <?php echo ($napname === 'Lp1 Np4') ? 'selected' : ''; ?>>Lp1 Np4</option>
                            <option value="Lp1 Np5" <?php echo ($napname === 'Lp1 Np5') ? 'selected' : ''; ?>>Lp1 Np5</option>
                            <option value="Lp1 Np6" <?php echo ($napname === 'Lp1 Np6') ? 'selected' : ''; ?>>Lp1 Np6</option>
                            <option value="Lp1 Np7" <?php echo ($napname === 'Lp1 Np7') ? 'selected' : ''; ?>>Lp1 Np7</option>
                            <option value="Lp1 Np8" <?php echo ($napname === 'Lp1 Np8') ? 'selected' : ''; ?>>Lp1 Np8</option>
                        </select>
                        <span class="error"><?php echo htmlspecialchars($napnameErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="napport">NAP Port <span class="required">*</span></label>
                        <select id="napport" name="napport" required>
                            <option value="" <?php echo ($napport === '') ? 'selected' : ''; ?>>Select NAP Port</option>
                            <option value="1" <?php echo ($napport === '1') ? 'selected' : ''; ?>>1</option>
                            <option value="2" <?php echo ($napport === '2') ? 'selected' : ''; ?>>2</option>
                            <option value="3" <?php echo ($napport === '3') ? 'selected' : ''; ?>>3</option>
                            <option value="4" <?php echo ($napport === '4') ? 'selected' : ''; ?>>4</option>
                            <option value="5" <?php echo ($napport === '5') ? 'selected' : ''; ?>>5</option>
                            <option value="6" <?php echo ($napport === '6') ? 'selected' : ''; ?>>6</option>
                            <option value="7" <?php echo ($napport === '7') ? 'selected' : ''; ?>>7</option>
                            <option value="8" <?php echo ($napport === '8') ? 'selected' : ''; ?>>8</option>
                        </select>
                        <span class="error"><?php echo htmlspecialchars($napportErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="macaddress">MAC Address <span class="required">*</span></label>
                        <input type="text" id="macaddress" name="macaddress" placeholder="e.g., 00:1A:2B:3C:4D:5E" value="<?php echo htmlspecialchars($macaddress, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($macaddressErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="status">Customer Status <span class="required">*</span></label>
                        <select id="status" name="status" required>
                            <option value="" <?php echo ($status === '') ? 'selected' : ''; ?>>Select Status</option>
                            <option value="Active" <?php echo ($status === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo ($status === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <span class="error"><?php echo htmlspecialchars($statusErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>

                <h2>Service Details</h2>
                <hr class="title-line">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="plan">Internet Plan <span class="required">*</span></label>
                        <select id="plan" name="plan" required>
                            <option value="" <?php echo ($plan === '') ? 'selected' : ''; ?>>Select Plan</option>
                            <option value="Plan 999" <?php echo ($plan === 'Plan 999') ? 'selected' : ''; ?>>Plan 999</option>
                            <option value="Plan 1499" <?php echo ($plan === 'Plan 1499') ? 'selected' : ''; ?>>Plan 1499</option>
                            <option value="Plan 1999" <?php echo ($plan === 'Plan 1999') ? 'selected' : ''; ?>>Plan 1999</option>
                            <option value="Plan 2999" <?php echo ($plan === 'Plan 2999') ? 'selected' : ''; ?>>Plan 2999</option>
                        </select>
                        <span class="error"><?php echo htmlspecialchars($planErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="equipment">Equipment <span class="required">*</span></label>
                        <select id="equipment" name="equipment" required>
                            <option value="" <?php echo ($equipment === '') ? 'selected' : ''; ?>>Select Equipment</option>
                            <option value="ISP-Provided Modem/Router" <?php echo ($equipment === 'ISP-Provided Modem/Router') ? 'selected' : ''; ?>>ISP-Provided Modem/Router</option>
                            <option value="Customer-Owned" <?php echo ($equipment === 'Customer-Owned') ? 'selected' : ''; ?>>Customer-Owned</option>
                        </select>
                        <span class="error"><?php echo htmlspecialchars($equipmentErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>

                <div class="button-container">
                    <button type="submit" id="submitBtn">Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Prevent multiple form submissions
    document.getElementById('customerForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Updating...';
    });

    // Fade out success message after 10 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.transition = 'opacity 1s ease-out';
                successAlert.style.opacity = '0';
                setTimeout(() => {
                    successAlert.remove();
                }, 1000);
            }, 10000);
        }
    });
</script>
</body>
</html>

<?php
$conn->close();
?>