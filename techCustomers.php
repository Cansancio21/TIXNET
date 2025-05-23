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
                         AND (c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ?)";
            $sql = "SELECT c_id, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment 
                    FROM tbl_customer 
                    WHERE (c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL) 
                    AND (c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ?) 
                    LIMIT ?, ?";
        } else {
            $sqlCount = "SELECT COUNT(*) AS total FROM tbl_customer 
                         WHERE c_status LIKE 'ARCHIVED:%' 
                         AND (c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ?)";
            $sql = "SELECT c_id, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment 
                    FROM tbl_customer 
                    WHERE c_status LIKE 'ARCHIVED:%' 
                    AND (c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ?) 
                    LIMIT ?, ?";
        }

        // Get total count for pagination
        $stmtCount = $conn->prepare($sqlCount);
        $stmtCount->bind_param("ssssss", $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch);
        $stmtCount->execute();
        $countResult = $stmtCount->get_result();
        $totalRow = $countResult->fetch_assoc();
        $total = $totalRow['total'];
        $totalPages = ceil($total / $limit);
        $stmtCount->close();

        // Fetch search results
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssii", $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $offset, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        ob_start();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $displayStatus = $tab === 'archived' ? preg_replace('/^ARCHIVED:/', '', $row['c_status']) : ($row['c_status'] ?? '');
                echo "<tr> 
                        <td>{$row['c_id']}</td> 
                        <td>" . htmlspecialchars($row['c_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_purok'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_barangay'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_contact'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_email'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td class='action-buttons'>";
                if ($tab === 'active') {
                    echo "
                        <a class='view-btn' onclick=\"showViewModal('{$row['c_id']}', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "')\" title='View'><i class='fas fa-eye'></i></a>
                        <a class='edit-btn' href='editC.php?id=" . htmlspecialchars($row['c_id'], ENT_QUOTES, 'UTF-8') . "' title='Edit'><i class='fas fa-edit'></i></a>
                        <a class='archive-btn' onclick=\"showArchiveModal('{$row['c_id']}', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Archive'><i class='fas fa-archive'></i></a>";
                } else {
                    echo "
                        <a class='view-btn' onclick=\"showViewModal('{$row['c_id']}', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "')\" title='View'><i class='fas fa-eye'></i></a>
                        <a class='unarchive-btn' onclick=\"showUnarchiveModal('{$row['c_id']}', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                        <a class='delete-btn' onclick=\"showDeleteModal('{$row['c_id']}', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Delete'><i class='fas fa-trash'></i></a>";
                }
                echo "</td></tr>";
            }
        } else {
            echo "<tr><td colspan='8' style='text-align: center;'>No customers found.</td></tr>";
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
        $sql = "SELECT c_id, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email 
                FROM tbl_customer 
                WHERE c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL";
        $result = $conn->query($sql);
        $customers = [];

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $customers[] = [
                    'c_id' => $row['c_id'],
                    'c_fname' => $row['c_fname'],
                    'c_lname' => $row['c_lname'],
                    'c_purok' => $row['c_purok'],
                    'c_barangay' => $row['c_barangay'],
                    'c_contact' => $row['c_contact'],
                    'c_email' => $row['c_email']
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
        $id = $_POST['c_id'];
        $sql = "SELECT c_status FROM tbl_customer WHERE c_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $current_rem = $row['c_status'] ?? '';
        $stmt->close();

        $new_rem = 'ARCHIVED:' . $current_rem;
        $sql = "UPDATE tbl_customer SET c_status=? WHERE c_id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for archive: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("si", $new_rem, $id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Customer archived successfully!";
        } else {
            $_SESSION['error'] = "Error archiving customer: " . $stmt->error;
            error_log("Error archiving customer ID $id: " . $stmt->error);
        }
        $stmt->close();
        $tab = 'customers_archived';
    } elseif (isset($_POST['unarchive_customer'])) {
        $id = $_POST['c_id'];
        $sql = "SELECT c_status FROM tbl_customer WHERE c_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $current_rem = $row['c_status'] ?? '';
        $stmt->close();

        $new_rem = preg_replace('/^ARCHIVED:/', '', $current_rem);
        $sql = "UPDATE tbl_customer SET c_status=? WHERE c_id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for unarchive: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("si", $new_rem, $id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Customer unarchived successfully!";
        } else {
            $_SESSION['error'] = "Error unarchiving customer: " . $stmt->error;
            error_log("Error unarchiving customer ID $id: " . $stmt->error);
        }
        $stmt->close();
        $tab = 'customers_active';
    } elseif (isset($_POST['delete_customer'])) {
        $id = $_POST['c_id'];
        $sql = "DELETE FROM tbl_customer WHERE c_id=? AND c_status LIKE 'ARCHIVED:%'";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for delete: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Customer deleted permanently!";
        } else {
            $_SESSION['error'] = "Error deleting customer: " . $stmt->error;
            error_log("Error deleting customer ID $id: " . $stmt->error);
        }
        $stmt->close();
        $tab = 'customers_archived';
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
    $sqlActive = "SELECT c_id, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment 
                  FROM tbl_customer WHERE c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL LIMIT ?, ?";
    $stmtActive = $conn->prepare($sqlActive);
    $stmtActive->bind_param("ii", $offsetActive, $limit);
    $stmtActive->execute();
    $resultActive = $stmtActive->get_result();
    $stmtActive->close();

    // Fetch archived customers
    $sqlArchived = "SELECT c_id, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment 
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
    <link rel="stylesheet" href="techCustomers.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="technicianD.php"><img src="image/main.png" alt="Dashboard" class="icon" /> <span>Dashboard</span></a></li>
            <li><a href="techBorrowed.php"><img src="image/borrowed.png" alt="Borrowed Assets" class="icon" /> <span>Borrowed Assets</span></a></li>
            <li><a href="TechCustomers.php" class="active"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers</span></a></li>
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
                <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
        </div>

        <div class="table-box glass-container">
            <h2>TIMS Customers</h2>
                
                <table id="active-customers-table">
                    <thead>
                        <tr>
                            <th>Customer ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Purok</th>
                            <th>Barangay</th>
                            <th>Contact</th>
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
                                        <td>{$row['c_id']}</td> 
                                        <td>" . htmlspecialchars($row['c_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_purok'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_barangay'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_contact'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_email'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td class='action-buttons'>
                                            <a class='view-btn' onclick=\"showViewModal('{$row['c_id']}', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "')\" title='View'><i class='fas fa-eye'></i></a>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8' style='text-align: center;'>No active customers found.</td></tr>";
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
            </div>

            <div class="archived-customers" id="customers_archived" <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'customers_active') || !isset($_GET['tab']) ? 'style="display: none;"' : ''; ?>>
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
                <table id="archived-customers-table">
                    <thead>
                        <tr>
                            <th>Customer ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Purok</th>
                            <th>Barangay</th>
                            <th>Contact</th>
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
                                        <td>{$row['c_id']}</td> 
                                        <td>" . htmlspecialchars($row['c_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_purok'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_barangay'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_contact'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_email'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td class='action-buttons'>
                                            <a class='view-btn' onclick=\"showViewModal('{$row['c_id']}', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "')\" title='View'><i class='fas fa-eye'></i></a>
                                            <a class='unarchive-btn' onclick=\"showUnarchiveModal('{$row['c_id']}', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                                            <a class='delete-btn' onclick=\"showDeleteModal('{$row['c_id']}', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8' style='text-align: center;'>No archived customers found.</td></tr>";
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
            </div>
        </div>
    </div>
</div>

<!-- View Customer Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Customer Details</h2>
        </div>
        <div id="viewContent"></div>
        <div class="modal-footer">
            <button class="modal-btn cancel" onclick="closeModal('viewModal')">Close</button>
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
            <input type="hidden" name="c_id" id="archiveCustomerId">
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
            <input type="hidden" name="c_id" id="unarchiveCustomerId">
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
            <input type="hidden" name="c_id" id="deleteCustomerId">
            <input type="hidden" name="delete_customer" value="1">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Delete</button>
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
    const tabButtons = document.querySelectorAll('.tab-btn');

    tabButtons.forEach(button => button.classList.remove('active'));
    const activeButton = Array.from(tabButtons).find(button => button.onclick.toString().includes(`showTab('${tab}')`));
    if (activeButton) {
        activeButton.classList.add('active');
    }

    if (tab === 'customers_active') {
        activeSection.style.display = 'block';
        archivedSection.style.display = 'none';
    } else if (tab === 'customers_archived') {
        activeSection.style.display = 'none';
        archivedSection.style.display = 'block';
    }

    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('tab', tab);
    history.replaceState(null, '', '?' + urlParams.toString());
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

function showViewModal(id, fname, lname, purok, barangay, contact, email, date, napname, napport, macaddress, status, plan, equipment) {
    document.getElementById('viewContent').innerHTML = `
        <div class="customer-details">
            <h3>Customer Profile</h3>
            <p><strong>ID:</strong> ${id}</p>
            <p><strong>Name:</strong> ${fname} ${lname}</p>
            <p><strong>Purok:</strong> ${purok || 'N/A'}</p>
            <p><strong>Barangay:</strong> ${barangay || 'N/A'}</p>
            <p><strong>Contact:</strong> ${contact || 'N/A'}</p>
            <p><strong>Email:</strong> ${email || 'N/A'}</p>
            <h3>Advance Profile</h3>
            <p><strong>Subscription Date:</strong> ${date || 'N/A'}</p>
            <p><strong>NAP Name:</strong> ${napname || 'N/A'}</p>
            <p><strong>NAP Port:</strong> ${napport || 'N/A'}</p>
            <p><strong>MAC Address:</strong> ${macaddress || 'N/A'}</p>
            <p><strong>Customer Status:</strong> ${status || 'N/A'}</p>
            <h3>Service Details</h3>
            <p><strong>Internet Plan:</strong> ${plan || 'N/A'}</p>
            <p><strong>Equipment:</strong> ${equipment || 'N/A'}</p>
        </div>
    `;
    document.getElementById('viewModal').style.display = 'block';
}

function showArchiveModal(id, name) {
    document.getElementById('archiveCustomerId').value = id;
    document.getElementById('archiveCustomerName').innerText = name;
    document.getElementById('archiveModal').style.display = 'block';
}

function showUnarchiveModal(id, name) {
    document.getElementById('unarchiveCustomerId').value = id;
    document.getElementById('unarchiveCustomerName').innerText = name;
    document.getElementById('unarchiveModal').style.display = 'block';
}

function showDeleteModal(id, name) {
    document.getElementById('deleteCustomerId').value = id;
    document.getElementById('deleteCustomerName').innerText = name;
    document.getElementById('deleteModal').style.display = 'block';
}

function exportTable(format) {
    const tab = document.getElementById('customers_active').style.display !== 'none' ? 'active' : 'archived';
    const table = tab === 'active' ? document.getElementById('active-customers-table') : document.getElementById('archived-customers-table');

    // Define headers
    const headers = [
        'Customer ID', 'First Name', 'Last Name', 'Purok', 'Barangay', 
        'Contact', 'Email'
    ];

    if (format === 'excel' && tab === 'active') {
        // Fetch all active customers for Excel export
        fetch('customersT.php?action=get_all_active_customers')
            .then(response => response.json())
            .then(customers => {
                let data = [headers];
                customers.forEach(customer => {
                    data.push([
                        customer.c_id,
                        customer.c_fname,
                        customer.c_lname,
                        customer.c_purok || '',
                        customer.c_barangay || '',
                        customer.c_contact || '',
                        customer.c_email || ''
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
                    cells[0].textContent.trim(), // Customer ID
                    cells[1].textContent.trim(), // First Name
                    cells[2].textContent.trim(), // Last Name
                    cells[3].textContent.trim(), // Purok
                    cells[4].textContent.trim(), // Barangay
                    cells[5].textContent.trim(), // Contact
                    cells[6].textContent.trim()  // Email
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