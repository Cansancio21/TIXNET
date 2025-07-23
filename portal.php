<?php
session_start(); // Start session to access user data
if (!isset($_SESSION['user'])) {
    header("Location: customerP.php"); // Redirect to login if not logged in
    exit();
}

$user = $_SESSION['user']; // Get user data from session
$username = $user['c_id']; // Using customer ID as identifier

// Initialize variables
$firstName = $user['c_fname'];
$userType = 'customer';
$avatarPath = 'default-avatar.png';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $username . '.png';

if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} else {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$avatarPath = $_SESSION['avatarPath'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal</title>
    <link rel="stylesheet" href="portalS.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="portal.php" class="active"><img src="image/main.png" alt="Dashboard" class="icon" /> <span>Dashboard</span></a></li>
            <li><a href="suppT.php"><img src="image/ticket.png" alt="Support Tickets" class="icon" /> <span>Support Tickets</span></a></li>
              <li><a href="reject_ticket.php"><img src="image/ticket.png" alt="Support Tickets" class="icon" /> <span>Reject Tickets</span></a></li>
        </ul>
        <footer>
            <a href="customerP.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>

    <div class="container">
        <div class="upper">
            <h1>Welcome, <?php echo htmlspecialchars($user['c_fname'] . ' ' . $user['c_lname'], ENT_QUOTES, 'UTF-8'); ?></h1>

            <div class="user-profile">
                <div class="user-icon">
                    <a href="images.php">
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
               
            </div>
        </div>

        <div class="table-box">
            <h2>Profile Information</h2>
            <hr class="title-line">
            <div class="flex-container">
                <!-- First Table: Basic Information -->
                <div class="flex-item">
                    <h3>Basic Information</h3>
                    <table>
                        <tr>
                            <th>Account No:</th>
                            <td><?php echo htmlspecialchars($user['c_account_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <th>First Name:</th>
                            <td><?php echo htmlspecialchars($user['c_fname'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <th>Last Name:</th>
                            <td><?php echo htmlspecialchars($user['c_lname'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <th>Purok:</th>
                            <td><?php echo htmlspecialchars($user['c_purok'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <th>Barangay:</th>
                            <td><?php echo htmlspecialchars($user['c_barangay'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Second Table: Contact Details -->
                <div class="flex-item">
                    <h3>Contact Details</h3>
                    <table>
                        <tr>
                            <th>Contact:</th>
                            <td><?php echo htmlspecialchars($user['c_contact'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?php echo htmlspecialchars($user['c_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                         <tr>
                            <th>Coordinates:</th>
                            <td><?php echo htmlspecialchars($user['c_coordinates'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <th>Subscription Date:</th>
                            <td><?php echo htmlspecialchars($user['c_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Third Table: Service Information -->
                <div class="flex-item">
                    <h3>Service Information</h3>
                    <table>
                        <tr>
                            <th>NAP Device:</th>
                            <td><?php echo htmlspecialchars($user['c_napname'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <th>NAP Port:</th>
                            <td><?php echo htmlspecialchars($user['c_napport'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <th>MAC Address:</th>
                            <td><?php echo htmlspecialchars($user['c_macaddress'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td><?php echo htmlspecialchars($user['c_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <th>Internet Plan:</th>
                            <td><?php echo htmlspecialchars($user['c_plan'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <th>Equipment:</th>
                            <td><?php echo htmlspecialchars($user['c_equipment'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            <hr class="title-line">
        </div>
    </div>
</div>
</body>
</html>