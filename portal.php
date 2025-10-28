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

// Calculate Advance Billing days
$advanceDays = '';
if (!empty($user['c_nextdue']) && !empty($user['c_nextbill'])) {
    $nextDueDate = new DateTime($user['c_nextdue']);
    $nextBillDate = new DateTime($user['c_nextbill']);
    $interval = $nextDueDate->diff($nextBillDate);
    $advanceDays = $interval->days . ' days before next due date';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal</title>
    <link rel="stylesheet" href="portals.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
        <li><a href="portal.php" class="active"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
        <li><a href="suppT.php"><i class="fas fa-ticket-alt icon"></i> <span>Support Tickets</span></a></li>
        <li><a href="reject_ticket.php"><i class="fas fa-times-circle icon"></i> <span>Declined Tickets</span></a></li>   
        </ul>
        <footer>
            <a href="CustomerP.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
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

        <div class="table-box glass-container">
            <h2>Profile Information</h2>
            <hr class="title-line">
            <div class="customer-details-container">
                <div class="customer-details-inner">
                    <div class="customer-details-column">
                        <h3><i class="fas fa-user"></i> Account Details</h3>
                        <h4 class="account-no-header">Account No.: <span class="account-no-value"><?php echo htmlspecialchars($user['c_account_no'], ENT_QUOTES, 'UTF-8'); ?></span></h4>
                        <div class="account-details-content">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['c_fname'] . ' ' . $user['c_lname'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Purok:</strong> <?php echo htmlspecialchars($user['c_purok'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Barangay:</strong> <?php echo htmlspecialchars($user['c_barangay'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Contact:</strong> <?php echo htmlspecialchars($user['c_contact'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['c_email'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Coordinates:</strong> <?php echo htmlspecialchars($user['c_coordinates'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Customer Status:</strong> <?php echo htmlspecialchars($user['c_status'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                    <div class="customer-details-column">
                        <h3><i class="fas fa-info-circle"></i> Subscription Details</h3>
                        <div class="subscription-details-content">
                            <p><strong>Subscription Date:</strong> <?php echo htmlspecialchars($user['c_date'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Product Plan:</strong> <?php echo htmlspecialchars($user['c_plan'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Equipment:</strong> <?php echo htmlspecialchars($user['c_equipment'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>NAP Name:</strong> <?php echo htmlspecialchars($user['c_napname'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>NAP Port:</strong> <?php echo htmlspecialchars($user['c_napport'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>MAC Address:</strong> <?php echo htmlspecialchars($user['c_macaddress'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                    <div class="customer-details-column">
                        <h3><i class="fas fa-cogs"></i> Service Details</h3>
                        <h4 class="balance-header">Balance: <span class="balance-value"><?php echo htmlspecialchars($user['c_balance'] ? number_format($user['c_balance'], 2) : '0.00', ENT_QUOTES, 'UTF-8'); ?></span></h4>
                        <div class="service-details-content">
                            <p><strong>Start Date:</strong> <?php echo htmlspecialchars($user['c_startdate'] ?: '', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Next Due Date:</strong> <?php echo htmlspecialchars($user['c_nextdue'] ?: '', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Last Due Date:</strong> <?php echo htmlspecialchars($user['c_lastdue'] ?: '', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Next Bill Date:</strong> <?php echo htmlspecialchars($user['c_nextbill'] ?: '', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Billing Status:</strong> <?php echo htmlspecialchars($user['c_billstatus'] ?: 'Inactive', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="title-line">
        </div>
    </div>
</div>
<script>
// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const upperHeader = document.querySelector('.upper');
    const mobileMenuToggle = document.createElement('button');
    mobileMenuToggle.className = 'mobile-menu-toggle';
    mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';
    mobileMenuToggle.setAttribute('aria-label', 'Toggle menu');
    
    // Insert the toggle button at the beginning of the header
    upperHeader.insertBefore(mobileMenuToggle, upperHeader.firstChild);
    
    const sidebar = document.querySelector('.sidebar');
    
    mobileMenuToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        // Toggle menu icon
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('active')) {
            icon.className = 'fas fa-times';
        } else {
            icon.className = 'fas fa-bars';
        }
    });
    
    // Close sidebar when clicking on a link (on mobile)
    const sidebarLinks = document.querySelectorAll('.sidebar a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                mobileMenuToggle.querySelector('i').className = 'fas fa-bars';
            }
        });
    });
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
            if (!sidebar.contains(event.target) && !mobileMenuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
                mobileMenuToggle.querySelector('i').className = 'fas fa-bars';
            }
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            mobileMenuToggle.querySelector('i').className = 'fas fa-bars';
        }
    });
});
</script>
</body>
</html>