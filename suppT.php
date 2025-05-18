
<?php
session_start();
include 'db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check user authentication
$username = $_SESSION['username'] ?? '';
$userId = $_SESSION['userId'] ?? 0;
$userType = $_SESSION['user_type'] ?? '';
$isTechnician = $userType === 'technician';
$isCustomer = $userType === 'customer';

if (!$username || (!$isTechnician && !$isCustomer)) {
    $_SESSION['error'] = "Unauthorized access. Please log in.";
    header("Location: index.php");
    exit();
}

// Fetch user details
$firstName = '';
$lastName = '';
if ($isTechnician) {
    $sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sqlUser);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $resultUser = $stmt->get_result();
    if ($resultUser->num_rows > 0) {
        $row = $resultUser->fetch_assoc();
        $firstName = $row['u_fname'] ?: $username;
        $lastName = $row['u_lname'] ?: '';
        $userType = $row['u_type'] ?: 'technician';
    } else {
        $_SESSION['error'] = "Technician not found.";
        $stmt->close();
        header("Location: index.php");
        exit();
    }
    $stmt->close();
} elseif ($isCustomer) {
    $sqlUser = "SELECT c_fname, c_lname FROM tbl_customer WHERE c_id = ?";
    $stmt = $conn->prepare($sqlUser);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $resultUser = $stmt->get_result();
    if ($resultUser->num_rows > 0) {
        $row = $resultUser->fetch_assoc();
        $firstName = $row['c_fname'] ?: 'Customer';
        $lastName = $row['c_lname'] ?: '';
    } else {
        $_SESSION['error'] = "Customer not found for ID: $userId.";
        $stmt->close();
        header("Location: customerP.php");
        exit();
    }
    $stmt->close();
}

// Avatar handling
$avatarFolder = 'Uploads/avatars/';
$avatarIdentifier = $isCustomer ? $userId : $username;
$userAvatar = $avatarFolder . $avatarIdentifier . '.png';
$avatarPath = file_exists($userAvatar) ? $userAvatar . '?' . time() : 'default-avatar.png';
$_SESSION['avatarPath'] = $avatarPath;

// Initialize variables
$ticket = null;
$activeTickets = [];
$archivedTickets = [];
$error = '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'active';
$activePage = isset($_GET['active_page']) ? max(1, (int)$_GET['active_page']) : 1;
$archivedPage = isset($_GET['archived_page']) ? max(1, (int)$_GET['archived_page']) : 1;
$ticketsPerPage = 10;
$filterCid = $isCustomer ? $userId : (isset($_GET['c_id']) && is_numeric($_GET['c_id']) && $_GET['c_id'] > 0 ? (int)$_GET['c_id'] : 0);
$customerName = 'Unknown Customer';

// Validate customer ID
if ($filterCid == 0 && $isTechnician) {
    $error = "Invalid or missing customer ID.";
} else {
    $sql = "SELECT c_fname, c_lname FROM tbl_customer WHERE c_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $filterCid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $customerName = ($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Customer ID ' . $filterCid;
    } else {
        $error = "Customer ID $filterCid does not exist.";
        $filterCid = 0;
    }
    $stmt->close();
}

// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'active';
    $searchPage = isset($_GET['search_page']) ? max(1, (int)$_GET['search_page']) : 1;
    $offset = ($searchPage - 1) * $ticketsPerPage;
    $searchLike = $searchTerm ? "%$searchTerm%" : null;

    // Count total tickets for pagination
    $sqlCount = "SELECT COUNT(*) as count FROM tbl_supp_tickets WHERE c_id = ? AND ";
    if ($tab === 'active') {
        $sqlCount .= "s_status IN ('Open', 'Closed')";
    } else {
        $sqlCount .= "s_status = 'Archived'";
    }
    if ($searchTerm) {
        $sqlCount .= " AND (s_ref LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR CONCAT(c_fname, ' ', c_lname) LIKE ?)";
    }
    $stmtCount = $conn->prepare($sqlCount);
    if ($searchTerm) {
        $stmtCount->bind_param("issss", $filterCid, $searchLike, $searchLike, $searchLike, $searchLike);
    } else {
        $stmtCount->bind_param("i", $filterCid);
    }
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    $totalTickets = $resultCount->fetch_assoc()['count'] ?? 0;
    $stmtCount->close();
    $totalPages = max(1, ceil($totalTickets / $ticketsPerPage));

    // Fetch tickets
    $sql = "SELECT id, c_id, c_fname, c_lname, s_ref, s_subject, s_message, s_status AS status, s_date 
            FROM tbl_supp_tickets 
            WHERE c_id = ? AND ";
    if ($tab === 'active') {
        $sql .= "s_status IN ('Open', 'Closed')";
    } else {
        $sql .= "s_status = 'Archived'";
    }
    if ($searchTerm) {
        $sql .= " AND (s_ref LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR CONCAT(c_fname, ' ', c_lname) LIKE ?)";
    }
    $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if ($searchTerm) {
        $stmt->bind_param("issssii", $filterCid, $searchLike, $searchLike, $searchLike, $searchLike, $ticketsPerPage, $offset);
    } else {
        $stmt->bind_param("iii", $filterCid, $ticketsPerPage, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    $stmt->close();

    // Generate HTML for table body
    ob_start();
    if ($tickets) {
        foreach ($tickets as $row) {
            $displayMessage = preg_replace('/^ARCHIVED:/', '', $row['s_message'] ?: '-');
            $displayStatus = ($tab === 'archived' && $row['status'] === 'Archived') ? 'Open' : $row['status'];
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['s_ref'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($row['c_id'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown'); ?></td>
                <td><?php echo htmlspecialchars($row['s_subject'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($displayMessage); ?></td>
                <td><?php echo htmlspecialchars($row['s_date'] ?: '-'); ?></td>
                <td class="status-<?php echo strtolower($displayStatus ?: 'unknown'); ?> status-clickable" 
                    onclick="<?php echo $isCustomer ? 'showStatusRestrictedMessage()' : "openCloseModal('{$row['id']}', '" . htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8') . "')"; ?>">
                    <?php echo htmlspecialchars($displayStatus ?: 'Unknown'); ?>
                </td>
                <td class="action-buttons">
                    <a class="view-btn" onclick="showViewModal('<?php echo $row['id']; ?>', '<?php echo $row['c_id']; ?>', '<?php echo addslashes($row['c_fname']); ?>', '<?php echo addslashes($row['c_lname']); ?>', '<?php echo addslashes($row['s_ref']); ?>', '<?php echo addslashes($row['s_subject']); ?>', '<?php echo addslashes($displayMessage); ?>', '<?php echo $row['status']; ?>', '<?php echo $row['s_date']; ?>', '<?php echo $tab; ?>')" title="View"><i class="fas fa-eye"></i></a>
                    <?php if ($isCustomer): ?>
                        <a class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit"><i class="fas fa-edit"></i></a>
                        <a class="<?php echo $tab === 'archived' ? 'unarchive-btn' : 'archive-btn'; ?>" 
                           onclick="open<?php echo $tab === 'archived' ? 'Unarchive' : 'Archive'; ?>Modal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?>')" 
                           title="<?php echo $tab === 'archived' ? 'Unarchive' : 'Archive'; ?>">
                            <i class="fas fa-<?php echo $tab === 'archived' ? 'box-open' : 'archive'; ?>"></i>
                        </a>
                        <?php if ($tab === 'archived'): ?>
                            <a class="delete-btn" onclick="openDeleteModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?>')" title="Delete"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a class="edit-btn" onclick="showRestrictedMessage()" title="Edit"><i class="fas fa-edit"></i></a>
                        <a class="<?php echo $tab === 'archived' ? 'unarchive-btn' : 'archive-btn'; ?>" 
                           onclick="showRestrictedMessage()" 
                           title="<?php echo $tab === 'archived' ? 'Unarchive' : 'Archive'; ?>">
                            <i class="fas fa-<?php echo $tab === 'archived' ? 'box-open' : 'archive'; ?>"></i>
                        </a>
                        <?php if ($tab === 'archived'): ?>
                            <a class="delete-btn" onclick="showRestrictedMessage()" title="Delete"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
    } else {
        ?>
        <tr>
            <td colspan="8" class="empty-state">No <?php echo $tab === 'active' ? 'active' : 'archived'; ?> tickets found.</td>
        </tr>
        <?php
    }
    $html = ob_get_clean();

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'html' => $html,
        'currentPage' => $searchPage,
        'totalPages' => $totalPages,
        'searchTerm' => $searchTerm
    ]);
    $conn->close();
    exit();
}

// Handle create ticket (only for customers)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket']) && $isCustomer) {
    $c_id = $userId;
    $c_fname = $firstName;
    $c_lname = $lastName;
    $s_ref = $_POST['ref'] ?? '';
    $s_subject = trim($_POST['subject'] ?? '');
    $s_message = trim($_POST['message'] ?? '');
    $s_status = $_POST['s_status'] ?? 'Open';
    $s_date = $_POST['s_date'] ?? '';

    // Validate date
    $today = date('Y-m-d');
    if (empty($s_date) || !strtotime($s_date) || $s_date > $today) {
        $_SESSION['error'] = "Please select a valid date that is not in the future.";
    } elseif (empty($s_subject) || empty($s_message)) {
        $_SESSION['error'] = "Subject and message are required.";
    } elseif ($s_status !== 'Open') {
        $_SESSION['error'] = "Invalid ticket status. Only 'Open' is allowed.";
    } else {
        $sql = "INSERT INTO tbl_supp_tickets (c_id, c_fname, c_lname, s_ref, s_subject, s_message, s_status, s_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssss", $c_id, $c_fname, $c_lname, $s_ref, $s_subject, $s_message, $s_status, $s_date);
        if ($stmt->execute()) {
            error_log("Ticket created with ID: " . $stmt->insert_id . ", Status: $s_status, Date: $s_date");
            $_SESSION['message'] = "Ticket created successfully!";
        } else {
            error_log("Error creating ticket: " . $stmt->error);
            $_SESSION['error'] = "Error creating ticket.";
        }
        $stmt->close();
    }
    header("Location: suppT.php" . ($filterCid ? "?c_id=$filterCid" : "") . "&tab=$tab&active_page=$activePage&archived_page=$archivedPage&refresh=" . time());
    exit();
}

// Handle edit ticket (only for customers)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_ticket']) && $isCustomer) {
    $ticketId = (int)$_POST['t_id'];
    $accountName = trim($_POST['account_name'] ?? '');
    $nameParts = explode(' ', $accountName, 2);
    $c_fname = $nameParts[0] ?? '';
    $c_lname = $nameParts[1] ?? '';
    $s_ref = $_POST['s_ref'] ?? '';
    $s_subject = trim($_POST['s_subject'] ?? '');
    $s_message = trim($_POST['s_message'] ?? '');
    $s_status = $_POST['s_status'] ?? '';
    $s_date = $_POST['s_date'] ?? '';

    // Validate date
    $today = date('Y-m-d');
    if (empty($s_date) || !strtotime($s_date) || $s_date > $today) {
        $_SESSION['error'] = "Please select a valid date that is not in the future.";
        header("Location: suppT.php?tab=$tab&active_page=$activePage&archived_page=$archivedPage" . ($filterCid ? "&c_id=$filterCid" : ""));
        exit();
    } elseif (empty($s_subject) || empty($s_message)) {
        $_SESSION['error'] = "Subject and message are required.";
        header("Location: suppT.php?tab=$tab&active_page=$activePage&archived_page=$archivedPage" . ($filterCid ? "&c_id=$filterCid" : ""));
        exit();
    }

    // Fetch current ticket status
    $sqlFetch = "SELECT s_status AS status FROM tbl_supp_tickets WHERE id = ? AND c_id = ?";
    $stmtFetch = $conn->prepare($sqlFetch);
    $stmtFetch->bind_param("ii", $ticketId, $filterCid);
    $stmtFetch->execute();
    $resultFetch = $stmtFetch->get_result();
    if ($resultFetch->num_rows > 0) {
        $currentTicket = $resultFetch->fetch_assoc();
        // Prevent status changes for open or closed tickets
        if ($currentTicket['status'] === 'Open' || $currentTicket['status'] === 'Closed') {
            $s_status = $currentTicket['status'];
        }
    } else {
        $_SESSION['error'] = "Ticket not found or unauthorized.";
        $stmtFetch->close();
        header("Location: suppT.php?tab=$tab&active_page=$activePage&archived_page=$archivedPage" . ($filterCid ? "&c_id=$filterCid" : ""));
        exit();
    }
    $stmtFetch->close();

    $sql = "UPDATE tbl_supp_tickets SET c_fname = ?, c_lname = ?, s_ref = ?, s_subject = ?, s_message = ?, s_status = ?, s_date = ? 
            WHERE id = ? AND c_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssii", $c_fname, $c_lname, $s_ref, $s_subject, $s_message, $s_status, $s_date, $ticketId, $filterCid);
    if ($stmt->execute()) {
        error_log("Ticket ID $ticketId updated successfully");
        $_SESSION['message'] = "Ticket updated successfully!";
    } else {
        error_log("Error updating ticket ID $ticketId: " . $stmt->error);
        $_SESSION['error'] = "Error updating ticket.";
    }
    $stmt->close();
    header("Location: suppT.php" . ($filterCid ? "?c_id=$filterCid" : "") . "&tab=$tab&active_page=$activePage&archived_page=$archivedPage&refresh=" . time());
    exit();
}

// Handle archive/unarchive ticket (only for customers)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_ticket']) && $isCustomer) {
    $ticketId = (int)$_POST['t_id'];
    $newStatus = $_POST['archive_action'] === 'archive' ? 'Archived' : 'Open';
    $sql = "UPDATE tbl_supp_tickets SET s_status = ? WHERE id = ? AND c_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for archive/unarchive: " . $conn->error);
        $_SESSION['error'] = "Error preparing query.";
    } else {
        $stmt->bind_param("sii", $newStatus, $ticketId, $filterCid);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                error_log("Ticket ID $ticketId updated to status: $newStatus");
                $_SESSION['message'] = "Ticket " . ($newStatus === 'Archived' ? 'archived' : 'unarchived') . " successfully!";
            } else {
                error_log("No rows affected for ticket ID $ticketId, status: $newStatus");
                $_SESSION['error'] = "Ticket not found or status unchanged.";
            }
        } else {
            error_log("Execute failed for ticket ID $ticketId, status: $newStatus, error: " . $stmt->error);
            $_SESSION['error'] = "Error updating ticket status.";
        }
        $stmt->close();
    }
    $redirectTab = $tab;
    if ($tab === 'archived' && $newStatus === 'Open') {
        $redirectTab = 'active';
    }
    header("Location: suppT.php" . ($filterCid ? "?c_id=$filterCid" : "") . "&tab=$redirectTab&active_page=$activePage&archived_page=$archivedPage&refresh=" . time());
    exit();
}

// Handle delete ticket (only for customers, only archived tickets)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket']) && $isCustomer) {
    $ticketId = (int)$_POST['t_id'];
    $sql = "DELETE FROM tbl_supp_tickets WHERE id = ? AND c_id = ? AND s_status = 'Archived'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $ticketId, $filterCid);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        error_log("Ticket ID $ticketId deleted successfully");
        $_SESSION['message'] = "Ticket deleted successfully!";
    } else {
        error_log("Error deleting ticket ID $ticketId: " . $stmt->error);
        $_SESSION['error'] = "Error deleting ticket: Invalid ticket or not archived.";
    }
    $stmt->close();
    header("Location: suppT.php" . ($filterCid ? "?c_id=$filterCid" : "") . "&tab=archived&active_page=$activePage&archived_page=$archivedPage&refresh=" . time());
    exit();
}

// Handle close ticket (only for technicians)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_ticket']) && $isTechnician) {
    $ticketId = (int)$_POST['t_id'];
    $inputCid = (int)$_POST['customer_id'];
    $sql = "UPDATE tbl_supp_tickets SET s_status = 'Closed' WHERE id = ? AND c_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $ticketId, $inputCid);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        error_log("Ticket ID $ticketId closed successfully");
        $_SESSION['message'] = "Ticket closed successfully!";
    } else {
        error_log("Error closing ticket ID $ticketId for customer ID $inputCid: " . $stmt->error);
        $_SESSION['error'] = "Error closing ticket: Invalid ticket or customer ID.";
    }
    $stmt->close();
    header("Location: suppT.php" . ($filterCid ? "?c_id=$filterCid" : "") . "&tab=$tab&active_page=$activePage&archived_page=$archivedPage&refresh=" . time());
    exit();
}

// Fetch ticket counts
$totalActiveTickets = 0;
$totalArchivedTickets = 0;
if ($filterCid != 0) {
    $sqlCount = "SELECT s_status, COUNT(*) as count FROM tbl_supp_tickets WHERE c_id = ? GROUP BY s_status";
    $stmt = $conn->prepare($sqlCount);
    $stmt->bind_param("i", $filterCid);
    $stmt->execute();
    $resultCount = $stmt->get_result();
    while ($row = $resultCount->fetch_assoc()) {
        if (strtolower($row['s_status']) === 'archived') {
            $totalArchivedTickets = $row['count'];
        } else {
            $totalActiveTickets += $row['count'];
        }
    }
    $stmt->close();
}

// Calculate pagination
$totalActivePages = ceil($totalActiveTickets / $ticketsPerPage) ?: 1;
$totalArchivedPages = ceil($totalArchivedTickets / $ticketsPerPage) ?: 1;
$activePage = max(1, min($totalActivePages, $activePage));
$archivedPage = max(1, min($totalArchivedPages, $archivedPage));
$activeOffset = ($activePage - 1) * $ticketsPerPage;
$archivedOffset = ($archivedPage - 1) * $ticketsPerPage;

// Fetch tickets
if ($filterCid != 0) {
    if (isset($_GET['id']) && is_numeric($_GET['id']) && $_GET['id'] > 0) {
        $ticketId = (int)$_GET['id'];
        $sql = "SELECT id, c_id, c_fname, c_lname, s_ref, s_subject, s_message, s_status AS status, s_date 
                FROM tbl_supp_tickets 
                WHERE id = ? AND c_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $ticketId, $filterCid);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $ticket = $result->fetch_assoc();
        } else {
            $error = "No ticket found with ID $ticketId for customer ID $filterCid.";
        }
        $stmt->close();
    } else {
        // Active tickets (Open and Closed)
        $sql = "SELECT id, c_id, c_fname, c_lname, s_ref, s_subject, s_message, s_status AS status, s_date 
                FROM tbl_supp_tickets 
                WHERE s_status IN ('Open', 'Closed') AND c_id = ? 
                ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for active tickets: " . $conn->error);
            $_SESSION['error'] = "Error preparing query for active tickets.";
        } else {
            $stmt->bind_param("iii", $filterCid, $ticketsPerPage, $activeOffset);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $activeTickets[] = $row;
                }
            } else {
                error_log("Execute failed for active tickets: " . $stmt->error);
                $_SESSION['error'] = "Error executing query for active tickets.";
            }
            $stmt->close();
        }

        // Archived tickets
        $sql = "SELECT id, c_id, c_fname, c_lname, s_ref, s_subject, s_message, s_status AS status, s_date 
                FROM tbl_supp_tickets 
                WHERE s_status = 'Archived' AND c_id = ? 
                ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for archived tickets: " . $conn->error);
            $_SESSION['error'] = "Error preparing query for archived tickets.";
        } else {
            $stmt->bind_param("iii", $filterCid, $ticketsPerPage, $archivedOffset);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $archivedTickets[] = $row;
                }
            } else {
                error_log("Execute failed for archived tickets: " . $stmt->error);
                $_SESSION['error'] = "Error executing query for archived tickets.";
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Ticket Details - Customer ID <?php echo htmlspecialchars($filterCid); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="suppsT.css">
    <style>
        /* Status colors for view modal and tables */
        .status-open {
            color: #00b894;
            font-weight: 500;
        }
        .status-closed {
            color: #f44336;
            font-weight: 500;
        }
        .modal-form input[readonly], .modal-form textarea[readonly] {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        .empty-state {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <?php if ($isTechnician): ?>
                <li><a href="technicianD.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="staffD.php"><i class="fas fa-users"></i> <span>Regular Tickets</span></a></li>
                <li><a href="technicianD.php"><i class="fas fa-file-archive"></i> <span>Support Tickets</span></a></li>
                <li><a href="assetsT.php"><i class="fas fa-box"></i> <span>View Assets</span></a></li>
                <li><a href="techBorrowed.php"><i class="fas fa-box-open"></i> <span>Borrowed Records</span></a></li>
            <?php else: ?>
                <li><a href="portal.php"><img src="image/main.png" alt="Dashboard" class="icon" /> <span>Dashboard</span></a></li>
                <li><a href="suppT.php" class="active"><img src="image/ticket.png" alt="Support Tickets" class="icon" /> <span>Support Tickets</span></a></li>
            <?php endif; ?>
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </footer>
    </div>
    <div class="container">
        <div class="upper glass-container">
            <h1>Support Tickets</h1>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search tickets..." onkeyup="debouncedSearchTickets()">
                <span class="search-icon"><i class="fas fa-search"></i></span>
            </div>
            <div class="user-profile">
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
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        </div>

        <?php if ($filterCid != 0): ?>
            <div class="tab-buttons">
                <button class="tab-btn <?php echo $tab === 'active' ? 'active' : ''; ?>" onclick="showTab('active')">Active (<?php echo $totalActiveTickets; ?>)</button>
                <button class="tab-btn <?php echo $tab === 'archived' ? 'active' : ''; ?>" onclick="showTab('archived')">
                    Archived <?php if ($totalArchivedTickets > 0): ?><span class="tab-badge"><?php echo $totalArchivedTickets; ?></span><?php endif; ?>
                </button>
                <button type="button" class="create-ticket-btn" onclick="<?php echo $isTechnician ? 'showRestrictedMessage()' : 'openModal()'; ?>">Create Ticket</button>
            </div>

            <!-- Active Tickets Table -->
            <div class="table-box <?php echo $tab === 'active' ? 'active' : ''; ?>" id="activeTable">
                <?php if (isset($_GET['id']) && $ticket): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket No</th>
                                <th>Customer ID</th>
                                <th>Customer Name</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo htmlspecialchars($ticket['s_ref'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($ticket['c_id']); ?></td>
                                <td><?php echo htmlspecialchars(($ticket['c_fname'] . ' ' . $ticket['c_lname']) ?: 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($ticket['s_subject'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $ticket['s_message'] ?: '-')); ?></td>
                                <td><?php echo htmlspecialchars($ticket['s_date'] ?: '-'); ?></td>
                                <td class="status-<?php echo strtolower($ticket['status'] ?: 'unknown'); ?> status-clickable" 
                                    onclick="<?php echo $isCustomer ? 'showStatusRestrictedMessage()' : "openCloseModal('{$ticket['id']}', '" . htmlspecialchars(($ticket['c_fname'] . ' ' . $ticket['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8') . "')"; ?>">
                                    <?php echo htmlspecialchars($ticket['status'] ?: 'Unknown'); ?>
                                </td>
                                <td class="action-buttons">
                                    <a class="view-btn" onclick="showViewModal('<?php echo $ticket['id']; ?>', '<?php echo $ticket['c_id']; ?>', '<?php echo addslashes($ticket['c_fname']); ?>', '<?php echo addslashes($ticket['c_lname']); ?>', '<?php echo addslashes($ticket['s_ref']); ?>', '<?php echo addslashes($ticket['s_subject']); ?>', '<?php echo addslashes(preg_replace('/^ARCHIVED:/', '', $ticket['s_message'])); ?>', '<?php echo $ticket['status']; ?>', '<?php echo $ticket['s_date']; ?>', '<?php echo $tab; ?>')" title="View"><i class="fas fa-eye"></i></a>
                                    <a class="edit-btn" onclick="<?php echo $isTechnician ? 'showRestrictedMessage()' : 'openEditModal(' . htmlspecialchars(json_encode($ticket), ENT_QUOTES, 'UTF-8') . ')'; ?>" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a class="<?php echo $ticket['status'] === 'Archived' ? 'unarchive-btn' : 'archive-btn'; ?>" 
                                       onclick="<?php echo $isTechnician ? 'showRestrictedMessage()' : 'open' . ($ticket['status'] === 'Archived' ? 'Unarchive' : 'Archive') . 'Modal(\'' . $ticket['id'] . '\', \'' . htmlspecialchars(($ticket['c_fname'] . ' ' . $ticket['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8') . '\')'; ?>" 
                                       title="<?php echo $ticket['status'] === 'Archived' ? 'Unarchive' : 'Archive'; ?>">
                                        <i class="fas fa-<?php echo $ticket['status'] === 'Archived' ? 'box-open' : 'archive'; ?>"></i>
                                    </a>
                                    <?php if ($isCustomer && $ticket['status'] === 'Archived'): ?>
                                        <a class="delete-btn" onclick="openDeleteModal('<?php echo $ticket['id']; ?>', '<?php echo htmlspecialchars(($ticket['c_fname'] . ' ' . $ticket['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?>')" title="Delete"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php elseif ($tab === 'active'): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket No</th>
                                <th>Customer ID</th>
                                <th>Customer Name</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="active-table-body">
                            <?php if ($activeTickets): ?>
                                <?php foreach ($activeTickets as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['s_ref'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['c_id'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($row['s_subject'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['s_message'] ?: '-')); ?></td>
                                        <td><?php echo htmlspecialchars($row['s_date'] ?: '-'); ?></td>
                                        <td class="status-<?php echo strtolower($row['status'] ?: 'unknown'); ?> status-clickable" 
                                            onclick="<?php echo $isCustomer ? 'showStatusRestrictedMessage()' : "openCloseModal('{$row['id']}', '" . htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8') . "')"; ?>">
                                            <?php echo htmlspecialchars($row['status'] ?: 'Unknown'); ?>
                                        </td>
                                        <td class="action-buttons">
                                            <a class="view-btn" onclick="showViewModal('<?php echo $row['id']; ?>', '<?php echo $row['c_id']; ?>', '<?php echo addslashes($row['c_fname']); ?>', '<?php echo addslashes($row['c_lname']); ?>', '<?php echo addslashes($row['s_ref']); ?>', '<?php echo addslashes($row['s_subject']); ?>', '<?php echo addslashes(preg_replace('/^ARCHIVED:/', '', $row['s_message'])); ?>', '<?php echo $row['status']; ?>', '<?php echo $row['s_date']; ?>', 'active')" title="View"><i class="fas fa-eye"></i></a>
                                            <a class="edit-btn" onclick="<?php echo $isTechnician ? 'showRestrictedMessage()' : 'openEditModal(' . htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') . ')'; ?>" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a class="archive-btn" 
                                               onclick="<?php echo $isTechnician ? 'showRestrictedMessage()' : 'openArchiveModal(\'' . $row['id'] . '\', \'' . htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8') . '\')'; ?>" 
                                               title="Archive">
                                                <i class="fas fa-archive"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">No active tickets found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="pagination" id="active-pagination">
                        <?php if ($activePage > 1): ?>
                            <a href="?active_page=<?php echo $activePage - 1; ?>&archived_page=<?php echo $archivedPage; ?>&tab=active<?php echo $filterCid ? '&c_id=' . $filterCid : ''; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>
                        <span class="current-page">Page <?php echo $activePage; ?> of <?php echo $totalActivePages; ?></span>
                        <?php if ($activePage < $totalActivePages): ?>
                            <a href="?active_page=<?php echo $activePage + 1; ?>&archived_page=<?php echo $archivedPage; ?>&tab=active<?php echo $filterCid ? '&c_id=' . $filterCid : ''; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Archived Tickets Table -->
            <div class="table-box <?php echo $tab === 'archived' ? 'active' : ''; ?>" id="archivedTable">
                <?php if ($tab === 'archived'): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket No</th>
                                <th>Customer ID</th>
                                <th>Customer Name</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="archived-table-body">
                            <?php if ($archivedTickets): ?>
                                <?php foreach ($archivedTickets as $row): ?>
                                    <?php $displayStatus = ($row['status'] === 'Archived') ? 'Open' : $row['status']; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['s_ref'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['c_id'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($row['s_subject'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['s_message'] ?: '-')); ?></td>
                                        <td><?php echo htmlspecialchars($row['s_date'] ?: '-'); ?></td>
                                        <td class="status-<?php echo strtolower($displayStatus ?: 'unknown'); ?>">
                                            <?php echo ucfirst(strtolower($displayStatus ?: 'Unknown')); ?>
                                        </td>
                                        <td class="action-buttons">
                                            <?php if ($isCustomer): ?>
                                                <a class="view-btn" onclick="showViewModal('<?php echo $row['id']; ?>', '<?php echo $row['c_id']; ?>', '<?php echo addslashes($row['c_fname']); ?>', '<?php echo addslashes($row['c_lname']); ?>', '<?php echo addslashes($row['s_ref']); ?>', '<?php echo addslashes($row['s_subject']); ?>', '<?php echo addslashes(preg_replace('/^ARCHIVED:/', '', $row['s_message'])); ?>', '<?php echo $row['status']; ?>', '<?php echo $row['s_date']; ?>', 'archived')" title="View"><i class="fas fa-eye"></i></a>
                                                <a class="unarchive-btn" onclick="openUnarchiveModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?>')" title="Unarchive"><i class="fas fa-box-open"></i></a>
                                                <a class="delete-btn" onclick="openDeleteModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?>')" title="Delete"><i class="fas fa-trash"></i></a>
                                            <?php else: ?>
                                                <a class="view-btn" onclick="showRestrictedMessage()" title="View"><i class="fas fa-eye"></i></a>
                                                <a class="unarchive-btn" onclick="showRestrictedMessage()" title="Unarchive"><i class="fas fa-box-open"></i></a>
                                                <a class="delete-btn" onclick="showRestrictedMessage()" title="Delete"><i class="fas fa-trash"></i></a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">No archived tickets found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="pagination" id="archived-pagination">
                        <?php if ($archivedPage > 1): ?>
                            <a href="?active_page=<?php echo $activePage; ?>&archived_page=<?php echo $archivedPage - 1; ?>&tab=archived<?php echo $filterCid ? '&c_id=' . $filterCid : ''; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>
                        <span class="current-page">Page <?php echo $archivedPage; ?> of <?php echo $totalArchivedPages; ?></span>
                        <?php if ($archivedPage < $totalArchivedPages): ?>
                            <a href="?active_page=<?php echo $activePage; ?>&archived_page=<?php echo $archivedPage + 1; ?>&tab=archived<?php echo $filterCid ? '&c_id=' . $filterCid : ''; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Modal Background -->
            <div id="modalBackground"></div>

            <!-- Add Ticket Modal -->
            <div id="addTicketModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Add New Ticket</h2>
                    </div>
                    <form method="POST" id="addTicketForm" class="modal-form">
                        <input type="hidden" name="create_ticket" value="1">
                        <input type="hidden" name="c_id" value="<?php echo htmlspecialchars($userId); ?>">
                        <label for="ticket_ref">Reference No</label>
                        <input type="text" name="ref" id="ticket_ref" readonly>
                        <span class="error" id="ticket_ref_error"></span>
                        <label for="account_name">Customer Name</label>
                        <input type="text" name="account_name" id="account_name" value="<?php echo htmlspecialchars($firstName . ' ' . $lastName, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        <span class="error" id="account_name_error"></span>
                        <label for="ticket_subject">Subject</label>
                        <input type="text" name="subject" id="ticket_subject" required>
                        <span class="error" id="ticket_subject_error"></span>
                        <label for="ticket_details">Message</label>
                        <textarea name="message" id="ticket_details" required></textarea>
                        <span class="error" id="ticket_details_error"></span>
                        <label for="ticket_date">Date</label>
                        <input type="date" name="s_date" id="ticket_date" required>
                        <span class="error" id="ticket_date_error"></span>
                        <label for="ticket_status">Status</label>
                        <input type="text" name="s_status" id="ticket_status" value="Open" readonly>
                        <span class="error" id="ticket_status_error"></span>
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('addTicketModal')">Cancel</button>
                            <button type="submit" class="modal-btn confirm">Report Ticket</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Ticket Modal -->
            <div id="editTicketModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Edit Ticket</h2>
                    </div>
                    <form method="POST" id="editTicketForm" class="modal-form">
                        <input type="hidden" name="edit_ticket" value="1">
                        <input type="hidden" name="t_id" id="edit_t_id">
                        <label for="edit_ticket_ref">Reference No</label>
                        <input type="text" name="s_ref" id="edit_ticket_ref" readonly>
                        <span class="error" id="edit_ticket_ref_error"></span>
                        <label for="edit_account_name">Customer Name</label>
                        <input type="text" name="account_name" id="edit_account_name" required>
                        <span class="error" id="edit_account_name_error"></span>
                        <label for="edit_ticket_subject">Subject</label>
                        <input type="text" name="s_subject" id="edit_ticket_subject" required>
                        <span class="error" id="edit_ticket_subject_error"></span>
                        <label for="edit_ticket_details">Message</label>
                        <textarea name="s_message" id="edit_ticket_details" required></textarea>
                        <span class="error" id="edit_ticket_details_error"></span>
                        <label for="edit_date">Date</label>
                        <input type="date" name="s_date" id="edit_date" required>
                        <span class="error" id="edit_date_error"></span>
                        <label for="edit_ticket_status">Ticket Status</label>
                        <input type="text" name="s_status" id="edit_ticket_status" readonly>
                        <span class="error" id="edit_ticket_status_error"></span>
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('editTicketModal')">Cancel</button>
                            <button type="submit" class="modal-btn confirm">Update Ticket</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Archive Ticket Modal -->
            <div id="archiveModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span onclick="closeModal('archiveModal')" class="close">×</span>
                    <div class="modal-header">
                        <h2>Archive Ticket</h2>
                    </div>
                    <p>Are you sure you want to archive ticket <span id="archiveTicketIdDisplay"></span> for <span id="archiveTicketName"></span>?</p>
                    <form class="modal-form" id="archiveForm" method="POST">
                        <input type="hidden" name="t_id" id="archiveTicketId">
                        <input type="hidden" name="archive_ticket" value="1">
                        <input type="hidden" name="archive_action" value="archive">
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('archiveModal')">Cancel</button>
                            <button type="submit" class="modal-btn confirm">Archive Ticket</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Unarchive Ticket Modal -->
            <div id="unarchiveModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span onclick="closeModal('unarchiveModal')" class="close">×</span>
                    <div class="modal-header">
                        <h2>Unarchive Ticket</h2>
                    </div>
                    <p>Are you sure you want to unarchive ticket <span id="unarchiveTicketIdDisplay"></span> for <span id="unarchiveTicketName"></span>?</p>
                    <form class="modal-form" id="unarchiveForm" method="POST">
                        <input type="hidden" name="t_id" id="unarchiveTicketId">
                        <input type="hidden" name="archive_ticket" value="1">
                        <input type="hidden" name="archive_action" value="unarchive">
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('unarchiveModal')">Cancel</button>
                            <button type="submit" class="modal-btn confirm">Unarchive Ticket</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Ticket Modal -->
            <div id="deleteModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span onclick="closeModal('deleteModal')" class="close">×</span>
                    <div class="modal-header">
                        <h2>Delete Ticket</h2>
                    </div>
                    <p>Are you sure you want to delete ticket <span id="deleteTicketIdDisplay"></span> for <span id="deleteTicketName"></span>?</p>
                    <form class="modal-form" id="deleteForm" method="POST">
                        <input type="hidden" name="t_id" id="deleteTicketId">
                        <input type="hidden" name="delete_ticket" value="1">
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('deleteModal')">Cancel</button>
                            <button type="submit" class="modal-btn delete">Delete Ticket</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Close Ticket Modal -->
            <div id="closeTicketModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span onclick="closeModal('closeTicketModal')" class="close">×</span>
                    <div class="modal-header">
                        <h2>Close Ticket</h2>
                    </div>
                    <p>Confirm closing ticket ID <span id="closeTicketIdDisplay"></span> for <span id="closeTicketName"></span>?</p>
                    <form class="modal-form" id="closeTicketForm" method="POST">
                        <input type="hidden" name="t_id" id="closeTicketId">
                        <input type="hidden" name="close_ticket" value="1">
                        <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($filterCid); ?>">
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('closeTicketModal')">Cancel</button>
                            <button type="submit" class="modal-btn confirm">Close Ticket</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View Ticket Modal -->
            <div id="viewModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Ticket Details</h2>
                    </div>
                    <div id="viewContent"></div>
                    <div class="modal-footer">
                        <button class="modal-btn cancel" onclick="closeModal('viewModal')">Close</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || 'active';
    showTab(tab, false);

    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('alert-hidden');
            setTimeout(() => alert.remove(), 500);
        }, 2000);
    });
});

function showTab(tab, forceRefresh = true) {
    const activeTable = document.getElementById('activeTable');
    const archivedTable = document.getElementById('archivedTable');
    const allTabButtons = document.querySelectorAll('.tab-btn');

    if (tab === 'active') {
        activeTable.classList.add('active');
        archivedTable.classList.remove('active');
    } else {
        activeTable.classList.remove('active');
        archivedTable.classList.add('active');
    }

    allTabButtons.forEach(button => {
        const buttonTab = button.getAttribute('onclick').match(/'([^']+)'/)[1];
        button.classList.toggle('active', buttonTab === tab);
    });

    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = urlParams.get('tab') || 'active';
    const currentCid = urlParams.get('c_id') || '<?php echo $filterCid; ?>';
    const currentActivePage = urlParams.get('active_page') || '<?php echo $activePage; ?>';
    const currentArchivedPage = urlParams.get('archived_page') || '<?php echo $archivedPage; ?>';

    if (forceRefresh && (tab !== currentTab || currentCid !== '<?php echo $filterCid; ?>' || 
        currentActivePage !== '<?php echo $activePage; ?>' || currentArchivedPage !== '<?php echo $archivedPage; ?>')) {
        const newUrl = `suppT.php?tab=${tab}&c_id=<?php echo $filterCid; ?>&active_page=${currentActivePage}&archived_page=${currentArchivedPage}`;
        window.location.href = newUrl;
    } else {
        // Trigger search to refresh table content when switching tabs
        searchTickets(1, tab);
    }
}

function openModal() {
    const date = new Date();
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const uniqueNumber = Math.floor(100000 + Math.random() * 900000);
    const ref = `ref#-${day}-${month}-${year}-${uniqueNumber}`;

    document.getElementById('ticket_ref').value = ref;
    document.getElementById('ticket_subject').value = '';
    document.getElementById('ticket_details').value = '';
    document.getElementById('ticket_date').value = '';
    document.getElementById('addTicketModal').style.display = 'block';
    document.getElementById('modalBackground').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.getElementById('modalBackground').style.display = 'none';
}

function openEditModal(ticket) {
    document.getElementById('edit_t_id').value = ticket.id;
    document.getElementById('edit_account_name').value = `${ticket.c_fname} ${ticket.c_lname}`.trim() || 'Unknown';
    document.getElementById('edit_ticket_ref').value = ticket.s_ref || '';
    document.getElementById('edit_ticket_subject').value = ticket.s_subject || '';
    document.getElementById('edit_ticket_details').value = ticket.s_message || '';
    document.getElementById('edit_date').value = ticket.s_date || '';
    document.getElementById('edit_ticket_status').value = ticket.status || 'Open';
    document.getElementById('editTicketModal').style.display = 'block';
    document.getElementById('modalBackground').style.display = 'block';
}

function openArchiveModal(id, name) {
    document.getElementById('archiveTicketId').value = id;
    document.getElementById('archiveTicketIdDisplay').textContent = id;
    document.getElementById('archiveTicketName').textContent = name;
    document.getElementById('archiveModal').style.display = 'block';
    document.getElementById('modalBackground').style.display = 'block';
}

function openUnarchiveModal(id, name) {
    document.getElementById('unarchiveTicketId').value = id;
    document.getElementById('unarchiveTicketIdDisplay').textContent = id;
    document.getElementById('unarchiveTicketName').textContent = name;
    document.getElementById('unarchiveModal').style.display = 'block';
    document.getElementById('modalBackground').style.display = 'block';
}

function openDeleteModal(id, name) {
    document.getElementById('deleteTicketId').value = id;
    document.getElementById('deleteTicketIdDisplay').textContent = id;
    document.getElementById('deleteTicketName').textContent = name;
    document.getElementById('deleteModal').style.display = 'block';
    document.getElementById('modalBackground').style.display = 'block';
}

function openCloseModal(id, name) {
    document.getElementById('closeTicketId').value = id;
    document.getElementById('closeTicketIdDisplay').textContent = id;
    document.getElementById('closeTicketName').textContent = name;
    document.getElementById('closeTicketModal').style.display = 'block';
    document.getElementById('modalBackground').style.display = 'block';
}

function showViewModal(id, c_id, c_fname, c_lname, s_ref, s_subject, s_message, s_status, s_date, tab) {
    const displayStatus = tab === 'archived' ? 'Open' : (s_status || 'Unknown');
    document.getElementById('viewContent').innerHTML = `
        <p><strong>Reference No:</strong> ${s_ref || '-'}</p>
        <p><strong>Customer ID:</strong> ${c_id}</p>
        <p><strong>Customer Name:</strong> ${c_fname} ${c_lname}</p>
        <p><strong>Subject:</strong> ${s_subject || '-'}</p>
        <p><strong>Message:</strong> ${s_message || '-'}</p>
        <p><strong>Date:</strong> ${s_date || '-'}</p>
        <p><strong>Status:</strong> <span class="status-${displayStatus.toLowerCase()}">${displayStatus}</span></p>
    `;
    document.getElementById('viewModal').style.display = 'block';
    document.getElementById('modalBackground').style.display = 'block';
}

function showRestrictedMessage() {
    alert('This action is restricted to customers only.');
}

function showStatusRestrictedMessage() {
    alert('Only technicians can close tickets.');
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

let defaultPageActive = <?php echo $activePage; ?>;
let defaultPageArchived = <?php echo $archivedPage; ?>;
let currentSearchPage = 1;

function searchTickets(page = 1, tab = null) {
    const searchTerm = document.getElementById('searchInput').value;
    const activeTable = document.getElementById('activeTable');
    const currentTab = tab || (activeTable.classList.contains('active') ? 'active' : 'archived');
    const tbody = currentTab === 'active' ? document.getElementById('active-table-body') : document.getElementById('archived-table-body');
    const paginationContainer = currentTab === 'active' ? document.getElementById('active-pagination') : document.getElementById('archived-pagination');
    const defaultPage = currentTab === 'active' ? defaultPageActive : defaultPageArchived;

    currentSearchPage = page;

    console.log(`Searching tickets: tab=${currentTab}, page=${page}, searchTerm=${searchTerm}, c_id=<?php echo $filterCid; ?>`);

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    tbody.innerHTML = response.html;
                    updatePagination(response.currentPage, response.totalPages, response.searchTerm, currentTab);
                    // Update default page variables
                    if (currentTab === 'active') {
                        defaultPageActive = response.currentPage;
                    } else {
                        defaultPageArchived = response.currentPage;
                    }
                } catch (e) {
                    console.error('Error parsing JSON:', e, xhr.responseText);
                    tbody.innerHTML = '<tr><td colspan="8" class="empty-state">Error loading tickets.</td></tr>';
                }
            } else {
                console.error('Search request failed:', xhr.status, xhr.statusText);
                tbody.innerHTML = '<tr><td colspan="8" class="empty-state">Error loading tickets.</td></tr>';
            }
        }
    };
    xhr.open('GET', `suppT.php?action=search&tab=${currentTab}&search=${encodeURIComponent(searchTerm)}&search_page=${page}&c_id=<?php echo $filterCid; ?>`, true);
    xhr.send();
}

function updatePagination(currentPage, totalPages, searchTerm, tab) {
    const paginationContainer = document.getElementById(tab === 'active' ? 'active-pagination' : 'archived-pagination');
    let paginationHtml = '';

    if (currentPage > 1) {
        paginationHtml += `<a href="javascript:searchTickets(${currentPage - 1}, '${tab}')" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="javascript:searchTickets(${currentPage + 1}, '${tab}')" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

const debouncedSearchTickets = debounce((page, tab) => searchTickets(page, tab), 300);
</script>
</body>
</html>
