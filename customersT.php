<?php
session_start();
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Function to escape strings for JavaScript
function jsEscape($str) {
    return str_replace(
        ["\\", "'", "\"", "\n", "\r", "\t"],
        ["\\\\", "\\'", "\\\"", "\\n", "\\r", "\\t"],
        $str
    );
}

$firstName = '';
$lastName = '';
$userType = '';
$avatarPath = 'default-avatar.png';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $_SESSION['username'] . '.png';

if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} else {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$avatarPath = $_SESSION['avatarPath'];

// Handle AJAX requests
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'search' && isset($_GET['search'])) {
        $searchTerm = $_GET['search'];
        $tab = $_GET['tab'] ?? 'active';
        $page = isset($_GET['search_page']) ? (int)$_GET['search_page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $searchTerm = $conn->real_escape_string($searchTerm);
        $likeSearch = '%' . $searchTerm . '%';

        if ($tab === 'active') {
            $sqlCount = "SELECT COUNT(*) AS total FROM tbl_customer 
                         WHERE (c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL) 
                         AND (c_account_no LIKE ? OR c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ? OR c_coordinates LIKE ? OR c_plan LIKE ?)";
            $sql = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment, c_balance, c_startdate, c_nextdue, c_lastdue, c_nextbill, c_billstatus 
                    FROM tbl_customer 
                    WHERE (c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL) 
                    AND (c_account_no LIKE ? OR c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ? OR c_coordinates LIKE ? OR c_plan LIKE ?) 
                    LIMIT ?, ?";
        } else {
            $sqlCount = "SELECT COUNT(*) AS total FROM tbl_customer 
                         WHERE c_status LIKE 'ARCHIVED:%' 
                         AND (c_account_no LIKE ? OR c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ? OR c_coordinates LIKE ? OR c_plan LIKE ?)";
            $sql = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment, c_balance, c_startdate, c_nextdue, c_lastdue, c_nextbill, c_billstatus 
                    FROM tbl_customer 
                    WHERE c_status LIKE 'ARCHIVED:%' 
                    AND (c_account_no LIKE ? OR c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ? OR c_coordinates LIKE ? OR c_plan LIKE ?) 
                    LIMIT ?, ?";
        }

        // Get total count for pagination
        $stmtCount = $conn->prepare($sqlCount);
        $stmtCount->bind_param("sssssssss", $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch);
        $stmtCount->execute();
        $countResult = $stmtCount->get_result();
        $totalRow = $countResult->fetch_assoc();
        $total = $totalRow['total'];
        $totalPages = ceil($total / $limit);
        $stmtCount->close();

        // Fetch search results
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssii", $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $offset, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        ob_start();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $displayStatus = $tab === 'archived' ? preg_replace('/^ARCHIVED:/', '', $row['c_status']) : ($row['c_status'] ?? '');
                echo "<tr> 
                        <td>" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_purok'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_barangay'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_contact'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_coordinates'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_email'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td class='action-buttons'>";
                if ($tab === 'active') {
                    echo "
                        <a class='view-btn' onclick=\"showViewDetails('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_coordinates']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "', '" . jsEscape($row['c_balance'] ?? '0.00') . "', '" . jsEscape($row['c_startdate'] ?? '') . "', '" . jsEscape($row['c_nextdue'] ?? '') . "', '" . jsEscape($row['c_lastdue'] ?? '') . "', '" . jsEscape($row['c_nextbill'] ?? '') . "', '" . jsEscape($row['c_billstatus'] ?? 'Inactive') . "')\" title='View'><i class='fas fa-eye'></i></a>
                        <a class='edit-btn' href='editC.php?account_no=" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "' title='Edit'><i class='fas fa-edit'></i></a>
                        <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Archive'><i class='fas fa-archive'></i></a>";
                } else {
                    echo "
                        <a class='view-btn' onclick=\"showViewDetails('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_coordinates']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "', '" . jsEscape($row['c_balance'] ?? '0.00') . "', '" . jsEscape($row['c_startdate'] ?? '') . "', '" . jsEscape($row['c_nextdue'] ?? '') . "', '" . jsEscape($row['c_lastdue'] ?? '') . "', '" . jsEscape($row['c_nextbill'] ?? '') . "', '" . jsEscape($row['c_billstatus'] ?? 'Inactive') . "')\" title='View'><i class='fas fa-eye'></i></a>
                        <a class='unarchive-btn' onclick=\"showUnarchiveModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                        <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Delete'><i class='fas fa-trash'></i></a>";
                }
                echo "</td></tr>";
            }
        } else {
            echo "<tr><td colspan='10' style='text-align: center;'>No customers found.</td></tr>";
        }
        $tableRows = ob_get_clean();

        // Update pagination
        echo "<script>updatePagination($page, $totalPages, '$tab', '" . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . "');</script>";
        echo $tableRows;
        $stmt->close();
        $conn->close();
        exit();
    } elseif ($_GET['action'] === 'get_all_active_customers') {
        // Fetch all active customers
        $sql = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_plan, c_balance, c_startdate, c_nextdue, c_lastdue, c_nextbill, c_billstatus 
                FROM tbl_customer 
                WHERE c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL";
        $result = $conn->query($sql);
        $customers = [];

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $customers[] = [
                    'c_account_no' => $row['c_account_no'],
                    'c_fname' => $row['c_fname'],
                    'c_lname' => $row['c_lname'],
                    'c_purok' => $row['c_purok'],
                    'c_barangay' => $row['c_barangay'],
                    'c_contact' => $row['c_contact'],
                    'c_email' => $row['c_email'],
                    'c_coordinates' => $row['c_coordinates'],
                    'c_plan' => $row['c_plan'],
                    'c_balance' => $row['c_balance'],
                    'c_startdate' => $row['c_startdate'],
                    'c_nextdue' => $row['c_nextdue'],
                    'c_lastdue' => $row['c_lastdue'],
                    'c_nextbill' => $row['c_nextbill'],
                    'c_billstatus' => $row['c_billstatus']
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode($customers);
        $conn->close();
        exit();
    }
} else {
    // Debug: Log when action is not set
    error_log("No 'action' parameter provided in request to customersT.php");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pageActive = isset($_GET['page_active']) ? (int)$_GET['page_active'] : 1;
    $pageArchived = isset($_GET['page_archived']) ? (int)$_GET['page_archived'] : 1;
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'customers_active';
    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === 'true';

    if (isset($_POST['archive_customer'])) {
        $account_no = $_POST['c_account_no'];
        $sql = "SELECT c_status FROM tbl_customer WHERE c_account_no=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $account_no);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $current_rem = $row['c_status'] ?? '';
        $stmt->close();

        $new_rem = 'ARCHIVED:' . $current_rem;
        $sql = "UPDATE tbl_customer SET c_status=? WHERE c_account_no=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for archive: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ss", $new_rem, $account_no);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Customer archived successfully!";
        } else {
            $_SESSION['error'] = "Error archiving customer: " . $stmt->error;
            error_log("Error archiving customer account_no $account_no: " . $stmt->error);
        }
        $stmt->close();
        $tab = 'customers_archived';
    } elseif (isset($_POST['unarchive_customer'])) {
        $account_no = $_POST['c_account_no'];
        $sql = "SELECT c_status FROM tbl_customer WHERE c_account_no=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $account_no);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $current_rem = $row['c_status'] ?? '';
        $stmt->close();

        $new_rem = preg_replace('/^ARCHIVED:/', '', $current_rem);
        $sql = "UPDATE tbl_customer SET c_status=? WHERE c_account_no=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for unarchive: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ss", $new_rem, $account_no);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Customer unarchived successfully!";
        } else {
            $_SESSION['error'] = "Error unarchiving customer: " . $stmt->error;
            error_log("Error unarchiving customer account_no $account_no: " . $stmt->error);
        }
        $stmt->close();
        $tab = 'customers_active';
    } elseif (isset($_POST['delete_customer'])) {
        $account_no = $_POST['c_account_no'];
        $sql = "DELETE FROM tbl_customer WHERE c_account_no=? AND c_status LIKE 'ARCHIVED:%'";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for delete: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $account_no);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Customer deleted permanently!";
        } else {
            $_SESSION['error'] = "Error deleting customer: " . $stmt->error;
            error_log("Error deleting customer account_no $account_no: " . $stmt->error);
        }
        $stmt->close();
        $tab = 'customers_archived';
    } elseif (isset($_POST['activate_billing'])) {
        $account_no = $_POST['c_account_no'];
        $due_date = $_POST['due_date'];
        $advance_days = (int)$_POST['advance_days'];

        // Validate inputs
        if (empty($due_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
            $_SESSION['error'] = "Invalid due date format. Please use YYYY-MM-DD.";
        } elseif ($advance_days <= 0) {
            $_SESSION['error'] = "Advance days must be a positive number.";
        } else {
            // Calculate dates
            $start_date = date('Y-m-d'); // Current date
            $due_date_obj = new DateTime($due_date);
            $next_due = (clone $due_date_obj)->modify('+31 days');
            
            // Adjust for month-end (e.g., September 30 → October 1)
            $day = $due_date_obj->format('d');
            if ($day > 28) {
                $next_due->modify('first day of next month');
                if ($day == 31) {
                    $next_due->modify('-1 day');
                }
            }
            
            $next_due_date = $next_due->format('Y-m-d');
            $last_due_date = null; // Initially null
            $next_bill_date = $next_due_date; // Align with next due date
            $billing_status = 'Active';
            $balance = 0.00; // Default balance

            // Update database
            $sql = "UPDATE tbl_customer 
                    SET c_balance = ?, c_startdate = ?, c_nextdue = ?, c_lastdue = ?, c_nextbill = ?, c_billstatus = ? 
                    WHERE c_account_no = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("dssssss", $balance, $start_date, $next_due_date, $last_due_date, $next_bill_date, $billing_status, $account_no);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Billing activated successfully for account $account_no!";
                } else {
                    $_SESSION['error'] = "Error activating billing: " . $stmt->error;
                    error_log("Error activating billing for account_no $account_no: " . $stmt->error);
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "Prepare failed: " . $conn->error;
                error_log("Prepare failed for activate billing: " . $conn->error);
            }
        }
    }

    if (!$isAjax) {
        header("Location: customersT.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
        exit();
    }
}

if ($conn) {
    // Fetch user data
    $sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sqlUser);
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

    // Pagination setup
    $limit = 10;
    // Active customers
    $pageActive = isset($_GET['page_active']) ? (int)$_GET['page_active'] : 1;
    $offsetActive = ($pageActive - 1) * $limit;
    $totalActiveQuery = "SELECT COUNT(*) AS total FROM tbl_customer WHERE c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL";
    $totalActiveResult = $conn->query($totalActiveQuery);
    $totalActiveRow = $totalActiveResult->fetch_assoc();
    $totalActive = $totalActiveRow['total'];
    $totalActivePages = ceil($totalActive / $limit);

    // Archived customers
    $pageArchived = isset($_GET['page_archived']) ? (int)$_GET['page_archived'] : 1;
    $offsetArchived = ($pageArchived - 1) * $limit;
    $totalArchivedQuery = "SELECT COUNT(*) AS total FROM tbl_customer WHERE c_status LIKE 'ARCHIVED:%'";
    $totalArchivedResult = $conn->query($totalArchivedQuery);
    $totalArchivedRow = $totalArchivedResult->fetch_assoc();
    $totalArchived = $totalArchivedRow['total'];
    $totalArchivedPages = ceil($totalArchived / $limit);

    // Fetch active customers
    $sqlActive = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment, c_balance, c_startdate, c_nextdue, c_lastdue, c_nextbill, c_billstatus 
                  FROM tbl_customer WHERE c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL LIMIT ?, ?";
    $stmtActive = $conn->prepare($sqlActive);
    $stmtActive->bind_param("ii", $offsetActive, $limit);
    $stmtActive->execute();
    $resultActive = $stmtActive->get_result();
    $stmtActive->close();

    // Fetch archived customers
    $sqlArchived = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment, c_balance, c_startdate, c_nextdue, c_lastdue, c_nextbill, c_billstatus 
                    FROM tbl_customer WHERE c_status LIKE 'ARCHIVED:%' LIMIT ?, ?";
    $stmtArchived = $conn->prepare($sqlArchived);
    $stmtArchived->bind_param("ii", $offsetArchived, $limit);
    $stmtArchived->execute();
    $resultArchived = $stmtArchived->get_result();
    $stmtArchived->close();
} else {
    $_SESSION['error'] = "Database connection failed.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Customers</title>
    <link rel="stylesheet" href="customersT.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="TixNet Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="staffD.php"><img src="image/ticket.png" alt="Regular Tickets" class="icon" /> <span>Regular Tickets</span></a></li>
            <li><a href="assetsT.php"><img src="image/assets.png" alt="Assets" class="icon" /> <span>Assets</span></a></li>
            <li><a href="AllCustomersT.php"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers Ticket</span></a></li>
            <li><a href="customersT.php" class="active"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers</span></a></li>
            <li><a href="borrowedStaff.php"><img src="image/borrowed.png" alt="Borrowed Assets" class="icon" /> <span>Borrowed Assets</span></a></li>
            <li><a href="addC.php"><img src="image/add.png" alt="Add Customer" class="icon" /> <span>Add Customer</span></a></li>
            <li><a href="AssignTech.php"><img src="image/add.png" alt="Technicians" class="icon" /> <span>Technicians</span></a></li>
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>

    <div class="container">
        <div class="upper"> 
            <h1>Customers Info</h1>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search customers..." onkeyup="debouncedSearchCustomers()">
                <span class="search-icon"><i class="fas fa-search"></i></span>
            </div>
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

        <div class="table-box glass-container">
            <h2>Connected Customers</h2>
            <div class="tab-buttons">
                <button class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'customers_active') || !isset($_GET['tab']) ? 'active' : ''; ?>" onclick="showTab('customers_active')">
                    Active (<?php echo $totalActive; ?>)
                </button>
                <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'customers_archived' ? 'active' : ''; ?>" onclick="showTab('customers_archived')">
                    Archived
                    <?php if ($totalArchived > 0): ?>
                        <span class="tab-badge"><?php echo $totalArchived; ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="customer-actions">
                <form action="addC.php" method="get" style="display: inline;">
                    <button type="submit" class="add-user-btn"><i class="fas fa-user-plus"></i> Add Customer</button>
                </form>
                <div class="export-container">
                    <button class="action-btn export-btn"><i class="fas fa-download"></i> Export</button>
                    <div class="export-dropdown">
                        <button onclick="exportTable('excel')">Excel</button>
                        <button onclick="exportTable('csv')">CSV</button>
                    </div>
                </div>
            </div>
            <div class="active-customers" id="customers_active" <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'customers_archived') ? 'style="display: none;"' : ''; ?>>
                <table id="active-customers-table">
                    <thead>
                        <tr>
                            <th>Account No.</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Purok</th>
                            <th>Barangay</th>
                            <th>Contact</th>
                            <th>Coordinates</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="active-customers-tbody">
                        <?php
                        if ($resultActive->num_rows > 0) {
                            while ($row = $resultActive->fetch_assoc()) {
                                $displayStatus = ($row['c_status'] ?? '');
                                echo "<tr> 
                                        <td>" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_purok'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_barangay'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_contact'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td>" . htmlspecialchars($row['c_coordinates'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_email'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td class='action-buttons'>
                                            <a class='view-btn' onclick=\"showViewDetails('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_coordinates']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "', '" . jsEscape($row['c_balance'] ?? '0.00') . "', '" . jsEscape($row['c_startdate'] ?? '') . "', '" . jsEscape($row['c_nextdue'] ?? '') . "', '" . jsEscape($row['c_lastdue'] ?? '') . "', '" . jsEscape($row['c_nextbill'] ?? '') . "', '" . jsEscape($row['c_billstatus'] ?? 'Inactive') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                            <a class='edit-btn' href='editC.php?account_no=" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "' title='Edit'><i class='fas fa-edit'></i></a>
                                            <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Archive'><i class='fas fa-archive'></i></a>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='10' style='text-align: center;'>No active customers found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <div class="pagination" id="active-customers-pagination">
                    <?php if ($pageActive > 1): ?>
                        <a href="?tab=customers_active&page_active=<?php echo $pageActive - 1; ?>&page_archived=<?php echo $pageArchived; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <span class="current-page">Page <?php echo $pageActive; ?> of <?php echo $totalActivePages; ?></span>
                    <?php if ($pageActive < $totalActivePages): ?>
                        <a href="?tab=customers_active&page_active=<?php echo $pageActive + 1; ?>&page_archived=<?php echo $pageArchived; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <!-- Customer Details Section -->
                <div id="customerDetailsActive" class="customer-details-section" style="display: none;">
                    <div id="customerDetailsContentActive" class="customer-details"></div>
                </div>
            </div>

            <div class="archived-customers" id="customers_archived" <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'customers_active') || !isset($_GET['tab']) ? 'style="display: none;"' : ''; ?>>
                <table id="archived-customers-table">
                    <thead>
                        <tr>
                            <th>Account No.</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Purok</th>
                            <th>Barangay</th>
                            <th>Contact</th>
                            <th>Coordinates</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="archived-customers-tbody">
                        <?php
                        if ($resultArchived->num_rows > 0) {
                            while ($row = $resultArchived->fetch_assoc()) {
                                $displayStatus = preg_replace('/^ARCHIVED:/', '', $row['c_status']);
                                echo "<tr> 
                                        <td>" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_purok'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_barangay'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_contact'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td>" . htmlspecialchars($row['c_coordinates'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_email'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td class='action-buttons'>
                                            <a class='view-btn' onclick=\"showViewDetails('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_coordinates']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "', '" . jsEscape($row['c_balance'] ?? '0.00') . "', '" . jsEscape($row['c_startdate'] ?? '') . "', '" . jsEscape($row['c_nextdue'] ?? '') . "', '" . jsEscape($row['c_lastdue'] ?? '') . "', '" . jsEscape($row['c_nextbill'] ?? '') . "', '" . jsEscape($row['c_billstatus'] ?? 'Inactive') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                            <a class='unarchive-btn' onclick=\"showUnarchiveModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                                            <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='9' style='text-align: center;'>No archived customers found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <div class="pagination" id="archived-customers-pagination">
                    <?php if ($pageArchived > 1): ?>
                        <a href="?tab=customers_archived&page_active=<?php echo $pageActive; ?>&page_archived=<?php echo $pageArchived - 1; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <span class="current-page">Page <?php echo $pageArchived; ?> of <?php echo $totalArchivedPages; ?></span>
                    <?php if ($pageArchived < $totalArchivedPages): ?>
                        <a href="?tab=customers_archived&page_active=<?php echo $pageActive; ?>&page_archived=<?php echo $pageArchived + 1; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <!-- Customer Details Section -->
                <div id="customerDetailsArchived" class="customer-details-section" style="display: none;">
                    <div id="customerDetailsContentArchived" class="customer-details"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Archive Customer Modal -->
<div id="archiveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Archive Customer</h2>
        </div>
        <p>Are you sure you want to archive <span id="archiveCustomerName"></span>?</p>
        <form method="POST" id="archiveForm">
            <input type="hidden" name="c_account_no" id="archiveCustomerId">
            <input type="hidden" name="archive_customer" value="1">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('archiveModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Archive</button>
            </div>
        </form>
    </div>
</div>

<!-- Unarchive Customer Modal -->
<div id="unarchiveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Unarchive Customer</h2>
        </div>
        <p>Are you sure you want to unarchive <span id="unarchiveCustomerName"></span>?</p>
        <form method="POST" id="unarchiveForm">
            <input type="hidden" name="c_account_no" id="unarchiveCustomerId">
            <input type="hidden" name="unarchive_customer" value="1">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('unarchiveModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Unarchive</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Customer Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Delete Customer</h2>
        </div>
        <p>Are you sure you want to permanently delete <span id="deleteCustomerName"></span>? This action cannot be undone.</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="c_account_no" id="deleteCustomerId">
            <input type="hidden" name="delete_customer" value="1">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Activate Billing Modal -->
<div id="activateBillingModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Activate Billing</h2>
        </div>
        <form method="POST" id="activateBillingForm">
            <input type="hidden" name="c_account_no" id="activateBillingCustomerId">
            <p>Activate Billing Status for <span id="activateBillingCustomerName"></span></p>
            <div class="modal-form">
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" readonly>
                <label for="due_date">Due Date:</label>
                <input type="date" id="due_date" name="due_date" required onchange="calculateNextDueDate()">
                <label for="advance_days">Advance Billing:</label>
                <input type="number" id="advance_days" name="advance_days" min="1" required placeholder="Enter number of days">
                <p class="billing-note"><strong>Note:</strong> The next due date is calculated as 31 days from the due date. If the due date is July 23, 2025, the next due date is August 23, 2025. If the due date is September 30, 2025, the next due date will be October 1, 2025, as September 31 does not exist.</p>
                <input type="hidden" name="activate_billing" value="1">
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('activateBillingModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm">Activate</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let currentSearchPage = 1;
let updateInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || 'customers_active';
    showTab(tab);

    // Handle alert messages disappearing after 10 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 1s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 1000);
        }, 10000);
    });

    // Initialize search on page load if there's a search term
    const searchInput = document.getElementById('searchInput');
    if (searchInput.value) {
        searchCustomers();
    }

    // Start auto-update table
    updateInterval = setInterval(updateTable, 30000);
});

// Clear interval when leaving the page
window.addEventListener('beforeunload', () => {
    if (updateInterval) {
        clearInterval(updateInterval);
    }
});

function showTab(tab) {
    const activeSection = document.getElementById('customers_active');
    const archivedSection = document.getElementById('customers_archived');
    const tabButtons = document.querySelectorAll('.tab-buttons .tab-btn');

    // Remove active class from all buttons
    tabButtons.forEach(button => button.classList.remove('active'));

    // Add active class to the clicked button
    const targetButton = Array.from(tabButtons).find(button => button.getAttribute('onclick').includes(`showTab('${tab}')`));
    if (targetButton) {
        targetButton.classList.add('active');
    }

    // Show/hide sections
    if (tab === 'customers_active') {
        activeSection.style.display = 'block';
        archivedSection.style.display = 'none';
        document.getElementById('customerDetailsArchived').style.display = 'none';
    } else if (tab === 'customers_archived') {
        activeSection.style.display = 'none';
        archivedSection.style.display = 'block';
        document.getElementById('customerDetailsActive').style.display = 'none';
    }

    // Update URL
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('tab', tab);
    history.replaceState(null, '', '?' + urlParams.toString());

    // Refresh table content
    updateTable();
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function searchCustomers(page = 1) {
    const searchTerm = document.getElementById('searchInput').value;
    const activeTab = document.querySelector('.tab-btn.active').textContent.toLowerCase();
    const tab = activeTab.includes('active') ? 'active' : 'archived';
    const tbody = tab === 'active' ? document.getElementById('active-customers-tbody') : document.getElementById('archived-customers-tbody');
    const defaultPageToUse = tab === 'active' ? <?php echo $pageActive; ?> : <?php echo $pageArchived; ?>;

    currentSearchPage = page;

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            tbody.innerHTML = xhr.responseText.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        }
    };
    xhr.open('GET', `customersT.php?action=search&search=${encodeURIComponent(searchTerm)}&tab=${tab}&search_page=${searchTerm ? page : defaultPageToUse}`, true);
    xhr.send();
}

function updatePagination(currentPage, totalPages, tab, searchTerm) {
    const paginationContainer = tab === 'active' ? document.getElementById('active-customers-pagination') : document.getElementById('archived-customers-pagination');
    let paginationHtml = '';

    if (currentPage > 1) {
        paginationHtml += `<a href="javascript:searchCustomers(${currentPage - 1})" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="javascript:searchCustomers(${currentPage + 1})" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

const debouncedSearchCustomers = debounce(searchCustomers, 300);

function calculateNextDueDate() {
    const dueDateInput = document.getElementById('due_date').value;
    const nextDueDateInput = document.getElementById('next_due_date');
    
    if (dueDateInput) {
        const dueDate = new Date(dueDateInput);
        const nextDue = new Date(dueDate);
        nextDue.setDate(dueDate.getDate() + 31);
        
        // Adjust for month-end
        const day = dueDate.getDate();
        if (day > 28) {
            const nextMonth = nextDue.getMonth();
            nextDue.setDate(1);
            nextDue.setMonth(nextMonth + 1);
            if (day === 31) {
                nextDue.setDate(nextDue.getDate() - 1);
            }
        }
        
        nextDueDateInput.value = nextDue.toISOString().split('T')[0];
    } else {
        nextDueDateInput.value = '';
    }
}

function showViewDetails(account_no, fname, lname, purok, barangay, contact, email, coordinates, date, napname, napport, macaddress, status, plan, equipment, balance, startdate, nextdue, lastdue, nextbill, billstatus) {
    const tab = document.getElementById('customers_active').style.display !== 'none' ? 'active' : 'archived';
    const detailsSection = document.getElementById(`customerDetails${tab === 'active' ? 'Active' : 'Archived'}`);
    const contentDiv = document.getElementById(`customerDetailsContent${tab === 'active' ? 'Active' : 'Archived'}`);

    contentDiv.innerHTML = `
    <div class="customer-details-container">
        <div class="customer-details-inner">
            <div class="customer-details-column">
                <h3><i class="fas fa-user"></i> Account Details</h3>
                <h4 class="account-no-header">Account No.: <span class="account-no-value">${account_no}</span></h4>
                <div class="account-details-content">
                    <p><strong>Name:</strong> ${fname} ${lname}</p>
                    <p><strong>Purok:</strong> ${purok || 'N/A'}</p>
                    <p><strong>Barangay:</strong> ${barangay || 'N/A'}</p>
                    <p><strong>Contact:</strong> ${contact || 'N/A'}</p>
                    <p><strong>Email:</strong> ${email || 'N/A'}</p>
                    <p><strong>Coordinates:</strong> ${coordinates || 'N/A'}</p>
                    <p><strong>Customer Status:</strong> ${status || 'N/A'}</p>
                </div>
            </div>
            <div class="subscription-details-column">
                <h3><i class="fas fa-info-circle"></i> Subscription Details</h3>
                <p><strong>Subscription Date:</strong> ${date || 'N/A'}</p>
                <p><strong>Product Plan:</strong> ${plan || 'N/A'}</p>
                <p><strong>Equipment:</strong> ${equipment || 'N/A'}</p>
                <p><strong>NAP Name:</strong> ${napname || 'N/A'}</p>
                <p><strong>NAP Port:</strong> ${napport || 'N/A'}</p>
                <p><strong>MAC Address:</strong> ${macaddress || 'N/A'}</p>
            </div>
        </div>
        <div class="service-details-inner">
            <div class="customer-details-column">
                <h3><i class="fas fa-cogs"></i> Service Details</h3>
                <h4 class="balance-header">Balance: <span class="balance-value">${balance ? parseFloat(balance).toFixed(2) : '0.00'}</span></h4>
                <p><strong>Start Date:</strong> ${startdate || ''}</p>
                <p><strong>Next Due Date:</strong> ${nextdue || ''}</p>
                <p><strong>Last Due Date:</strong> ${lastdue || ''}</p>
                <p><strong>Next Bill Date:</strong> ${nextbill || ''}</p>
                <p><strong>Billing Status:</strong> ${billstatus || 'Inactive'}</p>
                <a class='activate-btn' onclick="showActivateBillingModal('${account_no}', '${fname} ${lname}')" title='Activate'><i class='fas fa-play'></i></a>
            </div>
        </div>
        <button class="details-btn cancel" onclick="hideViewDetails('customerDetails${tab === 'active' ? 'Active' : 'Archived'}')">Cancel</button>
    </div>
    `;
    detailsSection.style.display = 'block';
}

function hideViewDetails(sectionId) {
    document.getElementById(sectionId).style.display = 'none';
}

function showArchiveModal(account_no, name) {
    document.getElementById('archiveCustomerId').value = account_no;
    document.getElementById('archiveCustomerName').innerText = name;
    document.getElementById('archiveModal').style.display = 'block';
}

function showUnarchiveModal(account_no, name) {
    document.getElementById('unarchiveCustomerId').value = account_no;
    document.getElementById('unarchiveCustomerName').innerText = name;
    document.getElementById('unarchiveModal').style.display = 'block';
}

function showDeleteModal(account_no, name) {
    document.getElementById('deleteCustomerId').value = account_no;
    document.getElementById('deleteCustomerName').innerText = name;
    document.getElementById('deleteModal').style.display = 'block';
}

function showActivateBillingModal(account_no, name) {
    document.getElementById('activateBillingCustomerId').value = account_no;
    document.getElementById('activateBillingCustomerName').innerText = name;
    document.getElementById('activateBillingModal').style.display = 'block';
}

function exportTable(format) {
    const tab = document.getElementById('customers_active').style.display !== 'none' ? 'active' : 'archived';
    const table = tab === 'active' ? document.getElementById('active-customers-table') : document.getElementById('archived-customers-table');

    // Define headers
    const headers = [
        'Customer Account No.', 'First Name', 'Last Name', 'Purok', 'Barangay', 
        'Contact', 'Email', 'Coordinates', 'Product Plan', 'Balance', 
        'Start Date', 'Next Due Date', 'Last Due Date', 'Next Bill Date', 'Billing Status'
    ];

    if (format === 'excel' && tab === 'active') {
        // Fetch all active customers for Excel export
        fetch('customersT.php?action=get_all_active_customers')
            .then(response => response.json())
            .then(customers => {
                let data = [headers];
                customers.forEach(customer => {
                    data.push([
                        customer.c_account_no,
                        customer.c_fname,
                        customer.c_lname,
                        customer.c_purok || '',
                        customer.c_barangay || '',
                        customer.c_contact || '',
                        customer.c_email || '',
                        customer.c_coordinates || '',
                        customer.c_plan || '',
                        customer.c_balance ? parseFloat(customer.c_balance).toFixed(2) : '0.00',
                        customer.c_startdate || '',
                        customer.c_nextdue || '',
                        customer.c_lastdue || '',
                        customer.c_nextbill || '',
                        customer.c_billstatus || 'Inactive'
                    ]);
                });

                // Create Excel file
                const worksheet = XLSX.utils.aoa_to_sheet(data);
                const workbook = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(workbook, worksheet, 'Customers');
                XLSX.writeFile(workbook, `Customers_active_${new Date().toISOString().slice(0,10)}.xlsx`);
            })
            .catch(error => {
                console.error('Error fetching all customers:', error);
                alert('Failed to export all customers. Please try again.');
            });
    } else {
        // CSV export or archived tab Excel export (use visible table)
        const rows = table.querySelectorAll('tbody tr');
        let data = [headers];

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length > 1) { // Ensure it's not an empty row
                const rowData = [
                    cells[0].textContent.trim(), // Customer Account No.
                    cells[1].textContent.trim(), // First Name
                    cells[2].textContent.trim(), // Last Name
                    cells[3].textContent.trim(), // Purok
                    cells[4].textContent.trim(), // Barangay
                    cells[5].textContent.trim(), // Contact
                    cells[6].textContent.trim(), // Email
                    cells[7].textContent.trim(), // Coordinates
                    cells[8].textContent.trim()  // Product Plan
                    // Note: Billing fields are not in the table, so they are not included in CSV for archived tab
                ];
                data.push(rowData);
            }
        });

        if (format === 'excel') {
            // Create Excel file for archived tab
            const worksheet = XLSX.utils.aoa_to_sheet(data);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Customers');
            XLSX.writeFile(workbook, `Customers_${tab}_${new Date().toISOString().slice(0,10)}.xlsx`);
        } else if (format === 'csv') {
            // Create CSV file
            let csvContent = data.map(row => row.map(cell => `"${cell.replace(/"/g, '""')}"`).join(',')).join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `Customers_${tab}_${new Date().toISOString().slice(0,10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }
    }
}

function updateTable() {
    const searchTerm = document.getElementById('searchInput').value;
    const activeTab = document.querySelector('.tab-btn.active').textContent.toLowerCase();
    const tab = activeTab.includes('active') ? 'active' : 'archived';
    const tbody = tab === 'active' ? document.getElementById('active-customers-tbody') : document.getElementById('archived-customers-tbody');
    const defaultPageToUse = tab === 'active' ? <?php echo $pageActive; ?> : <?php echo $pageArchived; ?>;

    if (searchTerm) {
        searchCustomers(currentSearchPage);
    } else {
        fetch(`customersT.php?tab=${tab === 'active' ? 'customers_active' : 'customers_archived'}&page_active=${<?php echo $pageActive; ?>}&page_archived=${<?php echo $pageArchived; ?>}`)
            .then(response => response.text())
            .then(data => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const newTableBody = tab === 'active' ? doc.querySelector('#active-customers-tbody') : doc.querySelector('#archived-customers-tbody');
                const currentTableBody = tab === 'active' ? document.querySelector('#active-customers-tbody') : document.querySelector('#archived-customers-tbody');
                currentTableBody.innerHTML = newTableBody.innerHTML;
            })
            .catch(error => console.error('Error updating table:', error));
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = 'none';
    }
}
</script>

</body>
</html>

<?php $conn->close(); ?>