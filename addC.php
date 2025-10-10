<?php
session_start(); // Start session for login management
include 'db.php';

// Include PHPMailer classes
require 'PHPmailer-master/PHPmailer-master/src/Exception.php';
require 'PHPmailer-master/PHPmailer-master/src/PHPMailer.php';
require 'PHPmailer-master/PHPmailer-master/src/SMTP.php';

// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Initialize variables for user data
$username = $_SESSION['username'];
$lastName = '';
$firstName = '';
$userType = '';
$avatarPath = 'default-avatar.png';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $username . '.png';

if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time(); // Prevent caching issues
} else {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$avatarPath = $_SESSION['avatarPath'];

// Fetch user data from the database
if (!$conn) {
    $_SESSION['error'] = "Database connection failed: " . mysqli_connect_error();
    header("Location: addC.php");
    exit();
}

$sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
$stmt = $conn->prepare($sqlUser);
if (!$stmt) {
    $_SESSION['error'] = "User query preparation failed: " . $conn->error;
    header("Location: addC.php");
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

// Set the subscription date to today's date or tomorrow's date dynamically
// Option 1: Use today's date
$currentDate = date('m/d/Y'); // Format: mm/dd/yyyy (e.g., 07/21/2025)
// Option 2: Use tomorrow's date (uncomment the line below if preferred)
// $currentDate = date('m/d/Y', strtotime('+1 day')); // Format: mm/dd/yyyy (e.g., 07/22/2025)
error_log("Form date set to: $currentDate"); // Debug log to confirm date

// Generate random 8-digit Customer Account Number
$accountNo = sprintf("%08d", mt_rand(10000000, 99999999));

// Initialize customer form variables
$accountNoErr = "";
$firstname = $lastname = $contact = $email = $coordinates = $dob = "";
$napname = $napport = $macaddress = $status = "";
$purok = $barangay = $planType = $plan = $equipment = "";
$accountNoErr = $firstnameErr = $lastnameErr = $contactErr = $emailErr = $coordinatesErr = "";
$napnameErr = $napportErr = $macaddressErr = $statusErr = "";
$purokErr = $barangayErr = $planTypeErr = $planErr = $equipmentErr = "";
$hasError = false;

// Handle customer registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accountNo = trim($_POST['accountNo'] ?? '');
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $coordinates = trim($_POST['coordinates'] ?? '');
    $dob = trim($_POST['date'] ?? ''); // Expecting mm/dd/yyyy
    $napname = trim($_POST['napname'] ?? '');
    $napport = trim($_POST['napport'] ?? '');
    $macaddress = trim($_POST['macaddress'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $purok = trim($_POST['purok'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $planType = trim($_POST['planType'] ?? '');
    $plan = trim($_POST['plan'] ?? '');
    $equipment = trim($_POST['equipment'] ?? '');

    // Validate inputs
    if (!preg_match("/^[0-9]{8}$/", $accountNo)) {
        $accountNoErr = "Account Number must be an 8-digit number.";
        $hasError = true;
    }
    if (!preg_match("/^[a-zA-Z\s-]+$/", $firstname)) {
        $firstnameErr = "First Name should not contain numbers.";
        $hasError = true;
    }
    if (!preg_match("/^[0-9]+$/", $contact)) {
        $contactErr = "Contact must contain numbers only.";
        $hasError = true;
    }
    if (!preg_match("/^[a-zA-Z\s-]+$/", $lastname)) {
        $lastnameErr = "Last Name should not contain numbers.";
        $hasError = true;
    }
    if (!preg_match("/^[a-zA-Z0-9:-]+$/", $macaddress)) {
        $macaddressErr = "Mac Address should not contain special characters.";
        $hasError = true;
    }
    if (empty($status)) {
        $statusErr = "Status is required.";
        $hasError = true;
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailErr = "Valid email is required.";
        $hasError = true;
    }
    if (empty($coordinates)) {
        $coordinatesErr = "Coordinates are required.";
        $hasError = true;
    }
    if (!preg_match("/^[a-zA-Z\s]+$/", $barangay)) {
        $barangayErr = "Barangay should contain letters only.";
        $hasError = true;
    }
    if (empty($planType) || !in_array($planType, ['Business Plan', 'Residential Plan'], true)) {
        $planTypeErr = "Plan Type must be either 'Business Plan' or 'Residential Plan'.";
        $hasError = true;
    }
    if (empty($plan)) {
        $planErr = "Product Plan is required.";
        $hasError = true;
    } else {
        // Validate product plan based on plan type
        $validBusinessPlans = ['Plan 999', 'Plan 1499', 'Plan 1799', 'Plan 1999', 'Plan 2500', 'Plan 3500'];
        $validResidentialPlans = ['Plan 799', 'Plan 999', 'Plan 1299', 'Plan 1499'];
        if ($planType === 'Business Plan' && !in_array($plan, $validBusinessPlans)) {
            $planErr = "Invalid Business Plan selected.";
            $hasError = true;
        } elseif ($planType === 'Residential Plan' && !in_array($plan, $validResidentialPlans)) {
            $planErr = "Invalid Residential Plan selected.";
            $hasError = true;
        }
    }
    if (empty($equipment)) {
        $equipmentErr = "Equipment is required.";
        $hasError = true;
    }
    if (empty($napname)) {
        $napnameErr = "NAP Device is required.";
        $hasError = true;
    }
    if (empty($napport)) {
        $napportErr = "NAP Port is required.";
        $hasError = true;
    }

    // Check if account number already exists
    $sqlCheckAccount = "SELECT c_account_no FROM tbl_customer WHERE c_account_no = ?";
    $stmtCheck = $conn->prepare($sqlCheckAccount);
    $stmtCheck->bind_param("s", $accountNo);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    if ($resultCheck->num_rows > 0) {
        $accountNoErr = "Account Number already exists.";
        $hasError = true;
    }
    $stmtCheck->close();

    // Insert into database if no errors
    if (!$hasError) {
        // Convert date to MySQL format (YYYY-MM-DD)
        $mysqlDate = DateTime::createFromFormat('m/d/Y', $dob)->format('Y-m-d');
        error_log("Database date set to: $mysqlDate"); // Debug log for database date

        $sql = "INSERT INTO tbl_customer (c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $_SESSION['error'] = "Prepare failed: " . $conn->error;
            error_log("SQL Prepare Error: " . $conn->error); // Log to server error log
            header("Location: addC.php");
            exit();
        }

        $stmt->bind_param("sssssssssssssss", $accountNo, $firstname, $lastname, $purok, $barangay, $contact, $email, $coordinates, $mysqlDate, $napname, $napport, $macaddress, $status, $plan, $equipment);

        if ($stmt->execute()) {
            // Send confirmation email using PHPMailer
            try {
                $mail = new PHPMailer(true); // Enable exceptions
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'jonwilyammayormita@gmail.com'; // Your Gmail address
                $mail->Password = 'mqkcqkytlwurwlks'; // Your Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Recipients
                $mail->setFrom('jonwilyammayormita@gmail.com', 'TixNet Pro');
                $mail->addAddress($email, "$firstname $lastname");

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Welcome to TixNet Pro!';
                $mail->Body = "
                    <html>
                    <head>
                        <title>Welcome to TixNet Pro</title>
                    </head>
                    <body>
                        <p>Dear $firstname $lastname,</p>
                        <p>Thank you for registering with TixNet Pro. Your account details are:</p>
                        <p><strong>Customer Account No.:</strong> $accountNo</p>
                        <p><strong>Last Name:</strong> $lastname</p>
                        <p><strong>Plan:</strong> $plan</p>
                        <p><strong>Coordinates:</strong> $coordinates</p>
                        <p>Please use these credentials to log in to our customer portal by clicking the link below:</p>
                        <p><a href='http://localhost/TIMSSS/customerP.php'>Customer Portal</a></p>
                        <p>Enter your Customer Account No. and Last Name to access your account.</p>
                        <p>Best regards,<br>Team Jupiter</p>
                    </body>
                    </html>
                ";
                $mail->AltBody = "Dear $firstname $lastname,\n\nThank you for registering with TixNet Pro. Your account details are:\nCustomer Account No.: $accountNo\nLast Name: $lastname\nPlan: $plan\nCoordinates: $coordinates\n\nPlease use these credentials to log in to our customer portal at http://localhost/TIMSSS/customerP.php\n\nBest regards,\nTixNet Pro Team";

                // Send the email
                $mail->send();
                $_SESSION['message'] = "Customer has been registered successfully. A confirmation email has been sent to $email.";
            } catch (Exception $e) {
                $_SESSION['error'] = "Customer registered, but failed to send confirmation email: " . $mail->ErrorInfo;
                error_log("PHPMailer Error: " . $mail->ErrorInfo); // Log email error
            }

            // Clear form variables
            $accountNo = sprintf("%08d", mt_rand(10000000, 99999999)); // Generate new account number
            $firstname = $lastname = $contact = $email = $coordinates = $dob = "";
            $napname = $napport = $macaddress = $status = "";
            $purok = $barangay = $planType = $plan = $equipment = "";
            header("Location: customersT.php");
            exit();
        } else {
            $_SESSION['error'] = "Execution failed: " . $stmt->error;
            error_log("SQL Execution Error: " . $stmt->error); // Log to server error log
            header("Location: addC.php");
            exit();
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
    <title>Add Customer</title>
    <link rel="stylesheet" href="addCC.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
        <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">

        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

    <style>
        #productPlanContainer {
            display: none;
            margin-top: 20px;
        }
        .form-group select[required] + .error:empty::before {
            content: "This field is required.";
            color: red;
            font-size: 12px;
            display: none;
        }
        .form-group select[required]:invalid + .error::before {
            display: block;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h2><img src="image/logo.png" alt="TixNet Icon" class="sidebar-icon">TixNet Pro</h2>
    <ul>
        <li><a href="staffD.php"><i class="fas fa-ticket-alt icon"></i> <span>Regular Tickets</span></a></li>
        <li><a href="assetsT.php"><i class="fas fa-boxes icon"></i> <span>Assets</span></a></li>
        <li><a href="AllCustomersT.php"><i class="fas fa-clipboard-check icon"></i> <span>Customers Ticket</span></a></li>
        <li><a href="customersT.php"><i class="fas fa-user-friends icon"></i> <span>Customers</span></a></li>
        <li><a href="AssignTech.php"><i class="fas fa-tools icon"></i> <span>Technicians</span></a></li>
        <li><a href="Payments.php"><i class="fas fa-credit-card icon"></i> <span>Transactions</span></a></li>
    </ul>
    <footer>
        <a href="technician_staff.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </footer>
    </div>

    <div class="container">
        <div class="upper"> 
            <h1>Add Customer</h1>
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
                        <label for="accountNo">Customer Account No. <span class="required">*</span></label>
                        <input type="text" id="accountNo" name="accountNo" value="<?php echo htmlspecialchars($accountNo, ENT_QUOTES, 'UTF-8'); ?>" readonly required>
                        <span class="error"><?php echo htmlspecialchars($accountNoErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="firstname">First Name <span class="required">*</span></label>
                        <input type="text" id="firstname" name="firstname" placeholder="Enter First Name" value="<?php echo htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($firstnameErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="lastname">Last Name <span class="required">*</span></label>
                        <input type="text" id="lastname" name="lastname" placeholder="Enter Last Name" value="<?php echo htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($lastnameErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="purok">Purok Name</label>
                        <input type="text" id="purok" name="purok" placeholder="Enter Purok Name" value="<?php echo htmlspecialchars($purok, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="error"><?php echo htmlspecialchars($purokErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="barangay">Barangay <span class="required">*</span></label>
                        <input type="text" id="barangay" name="barangay" placeholder="Enter Barangay" value="<?php echo htmlspecialchars($barangay, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($barangayErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="contact">Contact Number <span class="required">*</span></label>
                        <input type="text" id="contact" name="contact" placeholder="Enter Contact Number" value="<?php echo htmlspecialchars($contact, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($contactErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" placeholder="Enter Email Address" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($emailErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="coordinates">Coordinates <span class="required">*</span></label>
                        <input type="text" id="coordinates" name="coordinates" placeholder="Enter Coordinates (e.g., lat,lon)" value="<?php echo htmlspecialchars($coordinates, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="error"><?php echo htmlspecialchars($coordinatesErr, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>

                <h2>Advance Profile</h2>
                <hr class="title-line">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="date">Subscription Date <span class="required">*</span></label>
                        <input type="text" id="date" name="date" value="<?php echo htmlspecialchars($currentDate, ENT_QUOTES, 'UTF-8'); ?>" readonly required>
                        <span class="error"></span>
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
                        <input type="text" id="macaddress" name="macaddress" placeholder="Enter Mac Address" value="<?php echo htmlspecialchars($macaddress, ENT_QUOTES, 'UTF-8'); ?>" required>
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
                        <label for="planType">Internet Plan Type <span class="required">*</span></label>
                        <select id="planType" name="planType" required>
                            <option value="" <?php echo ($planType === '') ? 'selected' : ''; ?>>Select Plan Type</option>
                            <option value="Business Plan" <?php echo ($planType === 'Business Plan') ? 'selected' : ''; ?>>Business Plan</option>
                            <option value="Residential Plan" <?php echo ($planType === 'Residential Plan') ? 'selected' : ''; ?>>Residential Plan</option>
                        </select>
                        <span class="error"><?php echo htmlspecialchars($planTypeErr, ENT_QUOTES, 'UTF-8'); ?></span>
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
                <div class="form-group" id="productPlanContainer">
                    <label for="plan">Product Plan <span class="required">*</span></label>
                    <select id="plan" name="plan" required>
                        <option value="" <?php echo ($plan === '') ? 'selected' : ''; ?>>Select Product Plan</option>
                        <!-- Options populated by JavaScript -->
                    </select>
                    <span class="error"><?php echo htmlspecialchars($planErr, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="button-container">
                    <button type="submit" id="submitBtn">Submit Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Prevent multiple form submissions and reset form on successful submission
    document.getElementById('customerForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
        
        // Reset form if no validation errors
        <?php if (!$hasError && $_SERVER["REQUEST_METHOD"] == "POST"): ?>
            this.reset();
            document.getElementById('date').value = '<?php echo htmlspecialchars($currentDate, ENT_QUOTES, 'UTF-8'); ?>';
            document.getElementById('accountNo').value = '<?php echo htmlspecialchars(sprintf("%08d", mt_rand(10000000, 99999999)), ENT_QUOTES, 'UTF-8'); ?>';
            document.getElementById('productPlanContainer').style.display = 'none';
            document.getElementById('plan').innerHTML = '<option value="" selected>Select Product Plan</option>';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Application';
        <?php endif; ?>
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
                }, 1000); // Remove after fade-out completes
            }, 10000); // 10 seconds
        }

        // Handle plan type selection
        const planTypeSelect = document.getElementById('planType');
        const productPlanContainer = document.getElementById('productPlanContainer');
        const productPlanSelect = document.getElementById('plan');
        const businessPlans = [
            { value: 'Plan 999', text: 'Plan 999 (15 Mbps)' },
            { value: 'Plan 1499', text: 'Plan 1499 (25 Mbps)' },
            { value: 'Plan 1799', text: 'Plan 1799 (35 Mbps)' },
            { value: 'Plan 1999', text: 'Plan 1999 (50 Mbps)' },
            { value: 'Plan 2500', text: 'Plan 2500 (70 Mbps)' },
            { value: 'Plan 3500', text: 'Plan 3500 (100 Mbps)' }
        ];
        const residentialPlans = [
            { value: 'Plan 799', text: 'Plan 799 (20 Mbps)' },
            { value: 'Plan 999', text: 'Plan 999 (50 Mbps)' },
            { value: 'Plan 1299', text: 'Plan 1299 (100 Mbps)' },
            { value: 'Plan 1499', text: 'Plan 1499 (150 Mbps)' }
        ];

        function updateProductPlanOptions() {
            const planType = planTypeSelect.value;
            productPlanSelect.innerHTML = '<option value="" selected>Select Product Plan</option>';
            
            if (planType === 'Business Plan') {
                businessPlans.forEach(plan => {
                    const option = document.createElement('option');
                    option.value = plan.value;
                    option.textContent = plan.text;
                    if (plan.value === '<?php echo addslashes($plan); ?>') {
                        option.selected = true;
                    }
                    productPlanSelect.appendChild(option);
                });
                productPlanContainer.style.display = 'block';
            } else if (planType === 'Residential Plan') {
                residentialPlans.forEach(plan => {
                    const option = document.createElement('option');
                    option.value = plan.value;
                    option.textContent = plan.text;
                    if (plan.value === '<?php echo addslashes($plan); ?>') {
                        option.selected = true;
                    }
                    productPlanSelect.appendChild(option);
                });
                productPlanContainer.style.display = 'block';
            } else {
                productPlanContainer.style.display = 'none';
            }
        }

        // Initialize product plan dropdown based on existing planType
        updateProductPlanOptions();

        // Update product plan dropdown on planType change
        planTypeSelect.addEventListener('change', updateProductPlanOptions);
    });
</script>
</body>
</html>

<?php
$conn->close();
?>

