<?php
session_start(); // Start session for login management
include 'db.php';

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

// Initialize customer form variables
$firstname = $lastname = $contact = $email = $dob = "";
$napname = $napport = $macaddress = $status = "";
$purok = $barangay = $plan = $equipment = "";
$firstnameErr = $lastnameErr = $contactErr = $emailErr = $dobErr = "";
$napnameErr = $napportErr = $macaddressErr = $statusErr = "";
$purokErr = $barangayErr = $planErr = $equipmentErr = "";
$hasError = false;

// Handle customer registration form submission
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
    $plan = trim($_POST['plan'] ?? '');
    $equipment = trim($_POST['equipment'] ?? '');

    // Validate inputs
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
    if (empty($dob)) {
        $dobErr = "Date is required.";
        $hasError = true;
    }
    if (empty($status)) {
        $statusErr = "Status is required.";
        $hasError = true;
    }
    if (empty($email)) {
        $emailErr = "Email is required.";
        $hasError = true;
    }
    if (!preg_match("/^[a-zA-Z\s]+$/", $barangay)) {
        $barangayErr = "Barangay should contain letters only.";
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

    // Insert into database if no errors
    if (!$hasError) {
        $sql = "INSERT INTO tbl_customer (c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $_SESSION['error'] = "Prepare failed: " . $conn->error;
            error_log("SQL Prepare Error: " . $conn->error); // Log to server error log
            header("Location: addC.php");
            exit();
        }

        $stmt->bind_param("sssssssssssss", $firstname, $lastname, $purok, $barangay, $contact, $email, $dob, $napname, $napport, $macaddress, $status, $plan, $equipment);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Customer has been registered successfully. A confirmation email has been sent.";
            // Clear form variables
            $firstname = $lastname = $contact = $email = $dob = "";
            $napname = $napport = $macaddress = $status = "";
            $purok = $barangay = $plan = $equipment = "";
            header("Location: addC.php");
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
    <link rel="stylesheet" href="addsC.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="staffD.php"><img src="image/ticket.png" alt="Regular Tickets" class="icon" /> <span>Regular Tickets</span></a></li>
            <li><a href="assetsT.php"><img src="image/assets.png" alt="Assets" class="icon" /> <span>Assets</span></a></li>
            <li><a href="customersT.php"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers</span></a></li>
            <li><a href="borrowedStaff.php"><img src="image/borrowed.png" alt="Borrowed Assets" class="icon" /> <span>Borrowed Assets</span></a></li>
            <li><a href="addC.php" class="active"><img src="image/add.png" alt="Add Customer" class="icon" /> <span>Add Customer</span></a></li>
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
        </div>

        <div class="form-box glass-container">
            <h2>Customer Profile</h2>
            <hr class="title-line">
            <form action="" method="POST" id="customerForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="firstname">First Name <span class="required">*</span></label>
                        <input type="text" id="firstname" name="firstname" placeholder="e.g., John" value="<?php echo htmlspecialchars($firstname); ?>" required>
                        <span class="error"><?php echo $firstnameErr; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="lastname">Last Name <span class="required">*</span></label>
                        <input type="text" id="lastname" name="lastname" placeholder="e.g., Doe" value="<?php echo htmlspecialchars($lastname); ?>" required>
                        <span class="error"><?php echo $lastnameErr; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="purok">Purok Name</label>
                        <input type="text" id="purok" name="purok" placeholder="e.g., Purok 3" value="<?php echo htmlspecialchars($purok); ?>">
                        <span class="error"><?php echo $purokErr; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="barangay">Barangay <span class="required">*</span></label>
                        <input type="text" id="barangay" name="barangay" placeholder="e.g., San Isidro" value="<?php echo htmlspecialchars($barangay); ?>" required>
                        <span class="error"><?php echo $barangayErr; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="contact">Contact Number <span class="required">*</span></label>
                        <input type="text" id="contact" name="contact" placeholder="e.g., 09123456789" value="<?php echo htmlspecialchars($contact); ?>" required>
                        <span class="error"><?php echo $contactErr; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" placeholder="e.g., john.doe@example.com" value="<?php echo htmlspecialchars($email); ?>" required>
                        <span class="error"><?php echo $emailErr; ?></span>
                    </div>
                </div>

                <h2>Advance Profile</h2>
                <hr class="title-line">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="date">Subscription Date <span class="required">*</span></label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($dob); ?>" required>
                        <span class="error"><?php echo $dobErr; ?></span>
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
                        <span class="error"><?php echo $napnameErr; ?></span>
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
                        <span class="error"><?php echo $napportErr; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="macaddress">MAC Address <span class="required">*</span></label>
                        <input type="text" id="macaddress" name="macaddress" placeholder="e.g., 00:1A:2B:3C:4D:5E" value="<?php echo htmlspecialchars($macaddress); ?>" required>
                        <span class="error"><?php echo $macaddressErr; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="status">Customer Status <span class="required">*</span></label>
                        <select id="status" name="status" required>
                            <option value="" <?php echo ($status === '') ? 'selected' : ''; ?>>Select Status</option>
                            <option value="Active" <?php echo ($status === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo ($status === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <span class="error"><?php echo $statusErr; ?></span>
                    </div>
                </div>

                <h2>Service Details</h2>
                <hr class="title-line">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="plan">Internet Plan <span class="required">*</span></label>
                        <select id="plan" name="plan" required>
                            <option value="" <?php echo ($plan === '') ? 'selected' : ''; ?>>Select Plan</option>
                            <option value="25 Mbps" <?php echo ($plan === '25 Mbps') ? 'selected' : ''; ?>>25 Mbps</option>
                            <option value="50 Mbps" <?php echo ($plan === '50 Mbps') ? 'selected' : ''; ?>>50 Mbps</option>
                            <option value="100 Mbps" <?php echo ($plan === '100 Mbps') ? 'selected' : ''; ?>>100 Mbps</option>
                            <option value="1 Gbps" <?php echo ($plan === '1 Gbps') ? 'selected' : ''; ?>>1 Gbps</option>
                        </select>
                        <span class="error"><?php echo $planErr; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="equipment">Equipment <span class="required">*</span></label>
                        <select id="equipment" name="equipment" required>
                            <option value="" <?php echo ($equipment === '') ? 'selected' : ''; ?>>Select Equipment</option>
                            <option value="ISP-Provided Modem/Router" <?php echo ($equipment === 'ISP-Provided Modem/Router') ? 'selected' : ''; ?>>ISP-Provided Modem/Router</option>
                            <option value="Customer-Owned" <?php echo ($equipment === 'Customer-Owned') ? 'selected' : ''; ?>>Customer-Owned</option>
                        </select>
                        <span class="error"><?php echo $equipmentErr; ?></span>
                    </div>
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
    });
</script>
</body>
</html>

<?php
$conn->close();
?>