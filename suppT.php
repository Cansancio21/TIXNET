<?php
session_start();
include 'db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start(); // Start output buffering to prevent stray output

// Check user authentication
$username = $_SESSION['username'] ?? '';
$userId = $_SESSION['userId'] ?? 0;
$userType = $_SESSION['user_type'] ?? '';
$isTechnician = $userType === 'technician';
$isCustomer = $userType === 'customer';

// Fetch user details
$firstName = '';
$lastName = '';
if ($isTechnician) {
    $sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sqlUser);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Database error.";
        header("Location: index.php");
        exit();
    }
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
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Database error.";
        header("Location: customerP.php");
        exit();
    }
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
$pendingTickets = [];
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
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $error = "Database error.";
    } else {
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
}

// Function to log actions to tbl_logs
function logAction($conn, $logType, $logDescription) {
    $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_type, l_description) VALUES (NOW(), ?, ?)";
    $stmtLog = $conn->prepare($sqlLog);
    if ($stmtLog) {
        $stmtLog->bind_param("ss", $logType, $logDescription);
        if (!$stmtLog->execute()) {
            error_log("Failed to log action: " . $stmtLog->error);
        }
        $stmtLog->close();
    } else {
        error_log("Failed to prepare log statement: " . $conn->error);
    }
}

// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'active';
    $searchPage = isset($_GET['search_page']) ? max(1, (int)$_GET['search_page']) : 1;
    $offset = ($searchPage - 1) * $ticketsPerPage;
    $searchLike = $searchTerm ? "%$searchTerm%" : null;

    // Count total tickets for pagination
    $sqlCount = "SELECT COUNT(*) as count FROM tbl_supp_tickets WHERE c_id = ? AND (technician_username IS NULL OR technician_username = '') AND ";
    if ($tab === 'active') {
        $sqlCount .= "s_status IN ('Open', 'Closed')";
    } else {
        $sqlCount .= "s_status = 'Archived'";
    }
    if ($searchTerm) {
        $sqlCount .= " AND (s_ref LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR CONCAT(c_fname, ' ', c_lname) LIKE ?)";
    }
    $stmtCount = $conn->prepare($sqlCount);
    if (!$stmtCount) {
        error_log("Count query prepare failed: " . $conn->error);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error']);
        exit();
    }
    if ($searchTerm) {
        $stmtCount->bind_param("issss", $filterCid, $searchLike, $searchLike, $searchLike, $searchLike);
    } else {
        $stmtCount->bind_param("i", $filterCid);
    }
    if (!$stmtCount->execute()) {
        error_log("Count query execute failed: " . $stmtCount->error);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error']);
        exit();
    }
    $resultCount = $stmtCount->get_result();
    $totalTickets = $resultCount->fetch_assoc()['count'] ?? 0;
    $stmtCount->close();
    $totalPages = max(1, ceil($totalTickets / $ticketsPerPage));

    // Fetch tickets
    $sql = "SELECT id, c_id, c_fname, c_lname, s_ref, s_subject, s_message, s_status AS status 
            FROM tbl_supp_tickets 
            WHERE c_id = ? AND (technician_username IS NULL OR technician_username = '') AND ";
    if ($tab === 'active') {
        $sql .= "s_status IN ('Open', 'Closed')";
    } else {
        $sql .= "s_status = 'Archived'";
    }
    if ($searchTerm) {
        $sql .= " AND (s_ref LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR CONCAT(c_fname, ' ', c_lname) LIKE ?)";
    }
    $sql .= " ORDER BY id ASC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Search query prepare failed: " . $conn->error);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error']);
        exit();
    }
    if ($searchTerm) {
        $stmt->bind_param("issssii", $filterCid, $searchLike, $searchLike, $searchLike, $searchLike, $ticketsPerPage, $offset);
    } else {
        $stmt->bind_param("iii", $filterCid, $ticketsPerPage, $offset);
    }
    if (!$stmt->execute()) {
        error_log("Search query execute failed: " . $stmt->error);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error']);
        exit();
    }
    $result = $stmt->get_result();
    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    $stmt->close();

    // Generate table content
    ob_start();
    if ($tickets) {
        foreach ($tickets as $row) {
            $displayStatus = ($tab === 'archived' && $row['status'] === 'Archived') ? 'Open' : ($row['status'] ?: 'Open');
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['s_ref'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($row['c_id'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown'); ?></td>
                <td><?php echo htmlspecialchars($row['s_subject'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($row['s_message'] ?: '-'); ?></td>
                <td class="status-<?php echo strtolower($displayStatus); ?>" 
                    onclick="<?php echo $isCustomer ? 'showStatusRestrictedMessage()' : "openCloseModal('{$row['id']}', '" . htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8') . "')"; ?>">
                    <?php echo htmlspecialchars($displayStatus); ?>
                </td>
                <td class="action-buttons">
                    <a class="view-btn" onclick="showViewModal('<?php echo $row['id']; ?>', '<?php echo $row['c_id']; ?>', '<?php echo addslashes($row['c_fname']); ?>', '<?php echo addslashes($row['c_lname']); ?>', '<?php echo addslashes($row['s_ref']); ?>', '<?php echo addslashes($row['s_subject']); ?>', '<?php echo addslashes($row['s_message']); ?>', '<?php echo $row['status']; ?>', '<?php echo $tab; ?>')" title="View"><i class="fas fa-eye"></i></a>
                    <?php if ($tab !== 'archived'): ?>
                        <a class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit"><i class="fas fa-edit"></i></a>
                    <?php endif; ?>
                    <a class="<?php echo $tab === 'archived' ? 'unarchive-btn' : 'archive-btn'; ?>" 
                       onclick="open<?php echo $tab === 'archived' ? 'Unarchive' : 'Archive'; ?>Modal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?>')" 
                       title="<?php echo $tab === 'archived' ? 'Unarchive' : 'Archive'; ?>">
                        <i class="fas fa-<?php echo $tab === 'archived' ? 'box-open' : 'archive'; ?>"></i>
                    </a>
                    <?php if ($tab === 'archived'): ?>
                        <a class="delete-btn" onclick="openDeleteModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?>')" title="Delete"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="7" class="empty-state">No ' . ($tab === 'active' ? 'active' : 'archived') . ' tickets found.</td></tr>';
    }
    $tableContent = ob_get_clean();

    // Generate pagination
    ob_start();
    ?>
    <div class="pagination" id="<?php echo $tab === 'active' ? 'activePagination' : 'archivedPagination'; ?>">
        <?php if ($searchPage > 1): ?>
            <a href="javascript:searchTickets('<?php echo addslashes($searchTerm); ?>', <?php echo $searchPage - 1; ?>)" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
        <?php else: ?>
            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
        <?php endif; ?>
        <span class="current-page">Page <?php echo $searchPage; ?> of <?php echo $totalPages; ?></span>
        <?php if ($searchPage < $totalPages): ?>
            <a href="javascript:searchTickets('<?php echo addslashes($searchTerm); ?>', <?php echo $searchPage + 1; ?>)" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
        <?php else: ?>
            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
    </div>
    <?php
    $paginationContent = ob_get_clean();

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'html' => $tableContent,
        'pagination' => $paginationContent,
        'currentPage' => $searchPage,
        'totalPages' => $totalPages,
        'searchTerm' => $searchTerm
    ]);
    $conn->close();
    exit();
}

// Handle create ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $c_id = $isCustomer ? $userId : $filterCid;
    $c_fname = $firstName;
    $c_lname = $lastName;
    $s_ref = 'ref#-' . date('d-m-Y') . '-' . rand(100000, 999999);
    $s_subject = trim($_POST['subject'] ?? '');
    $s_message = trim($_POST['message'] ?? '');
    $s_status = 'Pending';

    // Check for existing pending tickets
    $sqlCheckPending = "SELECT COUNT(*) as count FROM tbl_customer_ticket WHERE c_id = ? AND s_status = 'Pending'";
    $stmtCheckPending = $conn->prepare($sqlCheckPending);
    if ($stmtCheckPending) {
        $stmtCheckPending->bind_param("i", $c_id);
        $stmtCheckPending->execute();
        $resultCheckPending = $stmtCheckPending->get_result();
        $pendingCount = $resultCheckPending->fetch_assoc()['count'] ?? 0;
        $stmtCheckPending->close();
        if ($pendingCount > 0) {
            $_SESSION['error'] = "Cannot Create Ticket because there is a pending ticket in the tbl_customer_ticket.";
            header("Location: suppT.php" . ($filterCid ? "?c_id=$filterCid" : "") . "&tab=active&active_page=$activePage&archived_page=$archivedPage&refresh=" . time());
            exit();
        }
    } else {
        error_log("Prepare failed for checking pending tickets: " . $conn->error);
        $_SESSION['error'] = "Error checking for existing pending tickets.";
        header("Location: suppT.php" . ($filterCid ? "?c_id=$filterCid" : "") . "&tab=active&active_page=$activePage&archived_page=$archivedPage&refresh=" . time());
        exit();
    }

    if (empty($s_subject) || empty($s_message)) {
        $_SESSION['error'] = "Subject and message are required.";
    } else {
        $sql = "INSERT INTO tbl_customer_ticket (c_id, c_fname, c_lname, s_ref, s_subject, s_message, s_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for ticket creation: " . $conn->error);
            $_SESSION['error'] = "Error preparing query.";
        } else {
            $stmt->bind_param("issssss", $c_id, $c_fname, $c_lname, $s_ref, $s_subject, $s_message, $s_status);
            if ($stmt->execute()) {
                $ticketId = $stmt->insert_id;
                error_log("Ticket created successfully in tbl_customer_ticket with ID: $ticketId, s_ref: $s_ref");
                $logType = ($isCustomer ? "customer" : "technician") . " $c_fname $c_lname";
                $logDescription = "created ticket $s_ref";
                logAction($conn, $logType, $logDescription);
                $_SESSION['message'] = "Ticket created successfully! Awaiting review.";
            } else {
                error_log("Error creating ticket in tbl_customer_ticket: " . $stmt->error);
                $_SESSION['error'] = "Error creating ticket.";
            }
            $stmt->close();
        }
    }
    header("Location: suppT.php" . ($filterCid ? "?c_id=$filterCid" : "") . "&tab=active&active_page=$activePage&archived_page=$archivedPage&refresh=" . time());
    exit();
}

// Handle edit ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_ticket'])) {
    $ticketId = (int)$_POST['t_id'];
    $accountName = trim($_POST['account_name'] ?? '');
    $nameParts = explode(' ', $accountName, 2);
    $c_fname = $nameParts[0] ?? '';
    $c_lname = $nameParts[1] ?? '';
    $s_ref = $_POST['s_ref'] ?? '';
    $s_subject = trim($_POST['s_subject'] ?? '');
    $s_message = trim($_POST['s_message'] ?? '');
    $s_status = $_POST['s_status'] ?? '';

    if (empty($s_subject) || empty($s_message)) {
        $_SESSION['error'] = "Subject and message are required.";
        header("Location: suppT.php?tab=$tab&active_page=$activePage&archived_page=$archivedPage" . ($filterCid ? "&c_id=$filterCid" : ""));
        exit();
    }

    $sqlFetch = "SELECT s_status AS status, s_ref FROM tbl_supp_tickets WHERE id = ? AND c_id = ? AND (technician_username IS NULL OR technician_username = '')";
    $stmtFetch = $conn->prepare($sqlFetch);
    if (!$stmtFetch) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Database error.";
        header("Location: suppT.php?tab=$tab&active_page=$activePage&archived_page=$archivedPage" . ($filterCid ? "&c_id=$filterCid" : ""));
        exit();
    }
    $stmtFetch->bind_param("ii", $ticketId, $filterCid);
    $stmtFetch->execute();
    $resultFetch = $stmtFetch->get_result();
    if ($resultFetch->num_rows > 0) {
        $currentTicket = $resultFetch->fetch_assoc();
        if ($currentTicket['status'] === 'Open' || $currentTicket['status'] === 'Closed') {
            $s_status = $currentTicket['status'];
        }
        $s_ref = $currentTicket['s_ref'];
    } else {
        $_SESSION['error'] = "Ticket not found or unauthorized.";
        $stmtFetch->close();
        header("Location: suppT.php?tab=$tab&active_page=$activePage&archived_page=$archivedPage" . ($filterCid ? "&c_id=$filterCid" : ""));
        exit();
    }
    $stmtFetch->close();

    $sql = "UPDATE tbl_supp_tickets SET c_fname = ?, c_lname = ?, s_ref = ?, s_subject = ?, s_message = ?, s_status = ? 
            WHERE id = ? AND c_id = ? AND (technician_username IS NULL OR technician_username = '')";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Database error.";
        header("Location: suppT.php?tab=$tab&active_page=$activePage&archived_page=$archivedPage" . ($filterCid ? "&c_id=$filterCid" : ""));
        exit();
    }
    $stmt->bind_param("ssssssii", $c_fname, $c_lname, $s_ref, $s_subject, $s_message, $s_status, $ticketId, $filterCid);
    if ($stmt->execute()) {
        error_log("Ticket ID $ticketId updated successfully");
        $logType = ($isCustomer ? "customer" : "technician") . " $c_fname $c_lname";
        $logDescription = "edited ticket $s_ref";
        logAction($conn, $logType, $logDescription);
        $_SESSION['message'] = "Ticket updated successfully!";
    } else {
        error_log("Error updating ticket ID $ticketId: " . $stmt->error);
        $_SESSION['error'] = "Error updating ticket.";
    }
    $stmt->close();
    header("Location: suppT.php" . ($filterCid ? "?c_id=$filterCid" : "") . "&tab=$tab&active_page=$activePage&archived_page=$archivedPage&refresh=" . time());
    exit();
}

// Handle archive/unarchive ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_ticket'])) {
    $ticketId = (int)$_POST['t_id'];
    $archiveAction = $_POST['archive_action'] ?? '';
    $newStatus = $archiveAction === 'archive' ? 'Archived' : 'Open';

    $sqlFetch = "SELECT s_ref, c_fname, c_lname FROM tbl_supp_tickets WHERE id = ? AND c_id = ? AND (technician_username IS NULL OR technician_username = '')";
    $stmtFetch = $conn->prepare($sqlFetch);
    if (!$stmtFetch) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Database error.";
        header("Location: suppT.php?tab=$tab&active_page=$activePage&archived_page=$archivedPage" . ($filterCid ? "&c_id=$filterCid" : ""));
        exit();
    }
    $stmtFetch->bind_param("ii", $ticketId, $filterCid);
    $stmtFetch->execute();
    $resultFetch = $stmtFetch->get_result();
    if ($resultFetch->num_rows > 0) {
        $ticketData = $resultFetch->fetch_assoc();
        $s_ref = $ticketData['s_ref'];
        $c_fname = $ticketData['c_fname'];
        $c_lname = $ticketData['c_lname'];
    } else {
        $_SESSION['error'] = "Ticket not found or unauthorized.";
        $stmtFetch->close();
        header("Location: suppT.php?tab=$tab&active_page=$activePage&archived_page=$archivedPage" . ($filterCid ? "&c_id=$filterCid" : ""));
        exit();
    }
    $stmtFetch->close();

    $sql = "UPDATE tbl_supp_tickets SET s_status = ? WHERE id = ? AND c_id = ? AND (technician_username IS NULL OR technician_username = '')";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for archive/unarchive: " . $conn->error);
        $_SESSION['error'] = "Error preparing query.";
    } else {
        $stmt->bind_param("sii", $newStatus, $ticketId, $filterCid);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                error_log("Ticket ID $ticketId updated to status: $newStatus");
                $logType = ($isCustomer ? "customer" : "technician") . " $c_fname $c_lname";
                $logDescription = ($newStatus === 'Archived' ? 'archived' : 'unarchived') . " ticket $s_ref";
                logAction($conn, $logType, $logDescription);
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
        $sqlCount = "SELECT COUNT(*) as count FROM tbl_supp_tickets WHERE c_id = ? AND s_status IN ('Open', 'Closed') AND (technician_username IS NULL OR technician_username = '')";
        $stmtCount = $conn->prepare($sqlCount);
        $stmtCount->bind_param("i", $filterCid);
        $stmtCount->execute();
        $resultCount = $stmtCount->get_result();
        $totalActiveTickets = $resultCount->fetch_assoc()['count'] ?? 0;
        $stmtCount->close();
        $activePage = max(1, ceil($totalActiveTickets / $ticketsPerPage));
    }
    header("Location: suppT.php" . ($filterCid ? "?c_id=$filterCid" : "") . "&tab=$redirectTab&active_page=$activePage&archived_page=$archivedPage&refresh=" . time());
    exit();
}

// Handle delete ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    $ticketId = (int)$_POST['t_id'];

    $sqlFetch = "SELECT s_ref, c_fname, c_lname FROM tbl_supp_tickets WHERE id = ? AND c_id = ? AND s_status = 'Archived' AND (technician_username IS NULL OR technician_username = '')";
    $stmtFetch = $conn->prepare($sqlFetch);
    if (!$stmtFetch) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Database error.";
        header("Location: suppT.php?tab=archived&active_page=$activePage&archived_page=$archivedPage" . ($filterCid ? "&c_id=$filterCid" : ""));
        exit();
    }
    $stmtFetch->bind_param("ii", $ticketId, $filterCid);
    $stmtFetch->execute();
    $resultFetch = $stmtFetch->get_result();
    if ($resultFetch->num_rows > 0) {
        $ticketData = $resultFetch->fetch_assoc();
        $s_ref = $ticketData['s_ref'];
        $c_fname = $ticketData['c_fname'];
        $c_lname = $ticketData['c_lname'];
    } else {
        $_SESSION['error'] = "Ticket not found, not archived, or unauthorized.";
        $stmtFetch->close();
        header("Location: suppT.php?tab=archived&active_page=$activePage&archived_page=$archivedPage" . ($filterCid ? "&c_id=$filterCid" : ""));
        exit();
    }
    $stmtFetch->close();

    $sql = "DELETE FROM tbl_supp_tickets WHERE id = ? AND c_id = ? AND s_status = 'Archived' AND (technician_username IS NULL OR technician_username = '')";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Database error.";
        header("Location: suppT.php?tab=archived&active_page=$activePage&archived_page=$archivedPage" . ($filterCid ? "&c_id=$filterCid" : ""));
        exit();
    }
    $stmt->bind_param("ii", $ticketId, $filterCid);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        error_log("Ticket ID $ticketId deleted successfully");
        $logType = ($isCustomer ? "customer" : "technician") . " $c_fname $c_lname";
        $logDescription = "deleted ticket $s_ref";
        logAction($conn, $logType, $logDescription);
        $_SESSION['message'] = "Ticket deleted successfully!";
    } else {
        error_log("Error deleting ticket ID $ticketId: " . $stmt->error);
        $_SESSION['error'] = "Error deleting ticket: Invalid ticket or not archived.";
    }
    $stmt->close();
    header("Location: suppT.php" . ($filterCid ? "?c_id=$filterCid" : "") . "&tab=archived&active_page=$activePage&archived_page=$archivedPage&refresh=" . time());
    exit();
}

// Handle close ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_ticket'])) {
    $ticketId = (int)$_POST['t_id'];
    $inputCid = (int)$_POST['customer_id'];
    $sql = "UPDATE tbl_supp_tickets SET s_status = 'Closed' WHERE id = ? AND c_id = ? AND (technician_username IS NULL OR technician_username = '')";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Database error.";
        header("Location: suppT.php" . ($filterCid ? "?c_id=$filterCid" : "") . "&tab=$tab&active_page=$activePage&archived_page=$archivedPage&refresh=" . time());
        exit();
    }
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

// Fetch pending tickets
$pendingTickets = [];
if ($filterCid != 0) {
    $sqlPending = "SELECT s_ref FROM tbl_customer_ticket WHERE c_id = ? AND s_status = 'Pending'";
    $stmtPending = $conn->prepare($sqlPending);
    if (!$stmtPending) {
        error_log("Prepare failed for pending tickets: " . $conn->error);
        $_SESSION['error'] = "Error preparing query for pending tickets.";
    } else {
        $stmtPending->bind_param("i", $filterCid);
        if ($stmtPending->execute()) {
            $resultPending = $stmtPending->get_result();
            while ($row = $resultPending->fetch_assoc()) {
                $pendingTickets[] = $row;
            }
        } else {
            error_log("Execute failed for pending tickets: " . $stmtPending->error);
            $_SESSION['error'] = "Error executing query for pending tickets.";
        }
        $stmtPending->close();
    }
}

// Fetch ticket counts
$totalActiveTickets = 0;
$totalArchivedTickets = 0;
if ($filterCid != 0) {
    $sqlCount = "SELECT s_status, COUNT(*) as count FROM tbl_supp_tickets WHERE c_id = ? AND (technician_username IS NULL OR technician_username = '') GROUP BY s_status";
    $stmt = $conn->prepare($sqlCount);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $error = "Database error.";
    } else {
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
        $sql = "SELECT id, c_id, c_fname, c_lname, s_ref, s_subject, s_message, s_status AS status 
                FROM tbl_supp_tickets 
                WHERE id = ? AND c_id = ? AND (technician_username IS NULL OR technician_username = '')
                ORDER BY id ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            $error = "Database error.";
        } else {
            $stmt->bind_param("ii", $ticketId, $filterCid);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $ticket = $result->fetch_assoc();
            } else {
                $error = "No ticket found with ID $ticketId for customer ID $filterCid.";
            }
            $stmt->close();
        }
    } else {
        // Active tickets
        $sql = "SELECT id, c_id, c_fname, c_lname, s_ref, s_subject, s_message, s_status AS status 
                FROM tbl_supp_tickets 
                WHERE s_status IN ('Open', 'Closed') AND c_id = ? AND (technician_username IS NULL OR technician_username = '') 
                ORDER BY id ASC LIMIT ? OFFSET ?";
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
        $sql = "SELECT id, c_id, c_fname, c_lname, s_ref, s_subject, s_message, s_status AS status 
                FROM tbl_supp_tickets 
                WHERE s_status = 'Archived' AND c_id = ? AND (technician_username IS NULL OR technician_username = '') 
                ORDER BY id ASC LIMIT ? OFFSET ?";
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
    <link rel="stylesheet" href="suppT.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
        <li><a href="portal.php"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
        <li><a href="suppT.php" class="active"><i class="fas fa-ticket-alt icon"></i> <span>Support Tickets</span></a></li>
        <li><a href="reject_ticket.php"><i class="fas fa-times-circle icon"></i> <span>Declined Tickets</span></a></li>   
        </ul>
        <footer>
            <a href="CustomerP.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
            </div>
        </div>

        <div class="alert-container" id="alertContainer"></div>

        <?php if ($filterCid != 0): ?>
            <!-- Active Tickets Table -->
            <div class="table-box <?php echo $tab === 'active' ? 'active' : ''; ?>" id="activeTable">
                <div class="tab-buttons">
                    <button class="tab-btn <?php echo $tab === 'active' ? 'active' : ''; ?>" onclick="showTab('active')" <?php echo !empty($pendingTickets) ? 'disabled' : ''; ?>>Active (<?php echo $totalActiveTickets; ?>)</button>
                    <button class="tab-btn <?php echo $tab === 'archived' ? 'active' : ''; ?>" onclick="showTab('archived')" <?php echo !empty($pendingTickets) ? 'disabled' : ''; ?>>
                        Archived <?php if ($totalArchivedTickets > 0): ?><span class="tab-badge"><?php echo $totalArchivedTickets; ?></span><?php endif; ?>
                    </button>
                    <button type="button" class="create-ticket-btn" onclick="openModal()">Create Ticket</button>
                </div>
                <?php if (isset($_GET['id']) && $ticket): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket No</th>
                                <th>Customer ID</th>
                                <th>Customer Name</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo htmlspecialchars($ticket['s_ref'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($ticket['c_id'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars(($ticket['c_fname'] . ' ' . $ticket['c_lname']) ?: 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($ticket['s_subject'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($ticket['s_message'] ?: '-'); ?></td>
                                <td class="status-<?php echo strtolower($ticket['status'] ?: 'open'); ?>">
                                    <?php echo htmlspecialchars($ticket['status'] ?: 'Open'); ?>
                                </td>
                                <td class="action-buttons">
                                    <a class="view-btn" onclick="showViewModal('<?php echo $ticket['id']; ?>', '<?php echo $ticket['c_id']; ?>', '<?php echo addslashes($ticket['c_fname']); ?>', '<?php echo addslashes($ticket['c_lname']); ?>', '<?php echo addslashes($ticket['s_ref']); ?>', '<?php echo addslashes($ticket['s_subject']); ?>', '<?php echo addslashes($ticket['s_message']); ?>', '<?php echo $ticket['status']; ?>', '<?php echo $tab; ?>')" title="View"><i class="fas fa-eye"></i></a>
                                    <?php if ($tab !== 'archived'): ?>
                                        <a class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($ticket), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                    <a class="<?php echo $tab === 'archived' ? 'unarchive-btn' : 'archive-btn'; ?>" 
                                       onclick="open<?php echo $tab === 'archived' ? 'Unarchive' : 'Archive'; ?>Modal('<?php echo $ticket['id']; ?>', '<?php echo htmlspecialchars(($ticket['c_fname'] . ' ' . $ticket['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?>')" 
                                       title="<?php echo $tab === 'archived' ? 'Unarchive' : 'Archive'; ?>">
                                        <i class="fas fa-<?php echo $tab === 'archived' ? 'box-open' : 'archive'; ?>"></i>
                                    </a>
                                    <?php if ($tab === 'archived'): ?>
                                        <a class="delete-btn" onclick="openDeleteModal('<?php echo $ticket['id']; ?>', '<?php echo htmlspecialchars(($ticket['c_fname'] . ' ' . $ticket['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?>')" title="Delete"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php elseif (!empty($pendingTickets)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Reported Ticket</th>
                            </tr>
                        </thead>
                        <tbody id="activeTableBody">
                            <?php foreach ($pendingTickets as $row): ?>
                                <tr>
                                    <td>Ticket Ref No: <?php echo htmlspecialchars($row['s_ref'] ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket No</th>
                                <th>Customer ID</th>
                                <th>Customer Name</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="activeTableBody">
                            <?php if ($activeTickets): ?>
                                <?php foreach ($activeTickets as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['s_ref'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['c_id'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($row['s_subject'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['s_message'] ?: '-'); ?></td>
                                        <td class="status-<?php echo strtolower($row['status'] ?: 'open'); ?> status-clickable" 
                                            onclick="<?php echo $isCustomer ? 'showStatusRestrictedMessage()' : "openCloseModal('{$row['id']}', '" . htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8') . "')"; ?>">
                                            <?php echo htmlspecialchars($row['status'] ?: 'Open'); ?>
                                        </td>
                                        <td class="action-buttons">
                                            <a class="view-btn" onclick="showViewModal('<?php echo $row['id']; ?>', '<?php echo $row['c_id']; ?>', '<?php echo addslashes($row['c_fname']); ?>', '<?php echo addslashes($row['c_lname']); ?>', '<?php echo addslashes($row['s_ref']); ?>', '<?php echo addslashes($row['s_subject']); ?>', '<?php echo addslashes($row['s_message']); ?>', '<?php echo $row['status']; ?>', '<?php echo $tab; ?>')" title="View"><i class="fas fa-eye"></i></a>
                                            <a class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a class="archive-btn" 
                                               onclick="openArchiveModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?>')" 
                                               title="Archive">
                                                <i class="fas fa-archive"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">No active or unofficially approved tickets found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="pagination" id="activePagination">
                        <?php if ($activePage > 1): ?>
                            <a href="javascript:searchTickets('', <?php echo $activePage - 1; ?>)" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>
                        <span class="current-page">Page <?php echo $activePage; ?> of <?php echo $totalActivePages; ?></span>
                        <?php if ($activePage < $totalActivePages): ?>
                            <a href="javascript:searchTickets('', <?php echo $activePage + 1; ?>)" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Archived Tickets Table -->
            <div class="table-box <?php echo $tab === 'archived' ? 'active' : ''; ?>" id="archivedTable">
                <div class="tab-buttons">
                    <button class="tab-btn <?php echo $tab === 'active' ? 'active' : ''; ?>" onclick="showTab('active')" <?php echo !empty($pendingTickets) ? 'disabled' : ''; ?>>Active (<?php echo $totalActiveTickets; ?>)</button>
                    <button class="tab-btn <?php echo $tab === 'archived' ? 'active' : ''; ?>" onclick="showTab('archived')" <?php echo !empty($pendingTickets) ? 'disabled' : ''; ?>>
                        Archived <?php if ($totalArchivedTickets > 0): ?><span class="tab-badge"><?php echo $totalArchivedTickets; ?></span><?php endif; ?>
                    </button>
                    <button type="button" class="create-ticket-btn" onclick="openModal()">Create Ticket</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Ticket No</th>
                            <th>Customer ID</th>
                            <th>Customer Name</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="archivedTableBody">
                        <?php if ($archivedTickets): ?>
                            <?php foreach ($archivedTickets as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['s_ref'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['c_id'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($row['s_subject'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['s_message'] ?: '-'); ?></td>
                                    <td class="status-open">Open</td>
                                    <td class="action-buttons">
                                        <a class="view-btn" onclick="showViewModal('<?php echo $row['id']; ?>', '<?php echo $row['c_id']; ?>', '<?php echo addslashes($row['c_fname']); ?>', '<?php echo addslashes($row['c_lname']); ?>', '<?php echo addslashes($row['s_ref']); ?>', '<?php echo addslashes($row['s_subject']); ?>', '<?php echo addslashes($row['s_message']); ?>', '<?php echo $row['status']; ?>', '<?php echo $tab; ?>')" title="View"><i class="fas fa-eye"></i></a>
                                        <a class="unarchive-btn" 
                                           onclick="openUnarchiveModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?>')" 
                                           title="Unarchive">
                                            <i class="fas fa-box-open"></i>
                                        </a>
                                        <a class="delete-btn" onclick="openDeleteModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?>')" title="Delete"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">No archived tickets found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="pagination" id="archivedPagination">
                    <?php if ($archivedPage > 1): ?>
                        <a href="javascript:searchTickets('', <?php echo $archivedPage - 1; ?>)" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <span class="current-page">Page <?php echo $archivedPage; ?> of <?php echo $totalArchivedPages; ?></span>
                    <?php if ($archivedPage < $totalArchivedPages): ?>
                        <a href="javascript:searchTickets('', <?php echo $archivedPage + 1; ?>)" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Ticket Modal -->
<div id="addTicketModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Ticket</h2>
        </div>
        <form method="POST" id="addTicketForm" class="modal-form" action="suppT.php">
            <input type="hidden" name="create_ticket" value="1">
            <input type="hidden" name="c_id" value="<?php echo htmlspecialchars($filterCid); ?>">
            <label for="ticket_ref">Reference No</label>
            <input type="text" name="ref" id="ticket_ref" value="ref#-<?php echo date('d-m-Y') . '-' . rand(100000, 999999); ?>" readonly>
            <span class="error" id="ticket_ref_error"></span>
            <label for="account_name">Customer Name</label>
            <input type="text" name="account_name" id="account_name" value="<?php echo htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?>" readonly>
            <span class="error" id="account_name_error"></span>
            <label for="ticket_subject">Subject</label>
            <input type="text" name="subject" id="ticket_subject" required>
            <span class="error" id="ticket_subject_error"></span>
            <label for="ticket_details">Message</label>
            <textarea name="message" id="ticket_details" required></textarea>
            <span class="error" id="ticket_details_error"></span>
            <label for="ticket_status">Status</label>
            <input type="text" name="s_status" id="ticket_status" value="Pending" readonly>
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
            <input type="hidden" name="t_id" id="edit_ticket_id">
            <input type="hidden" name="c_id" id="edit_customer_id">
            <label for="edit_ticket_ref">Reference No</label>
            <input type="text" name="s_ref" id="edit_ticket_ref" readonly>
            <span class="error" id="edit_ticket_ref_error"></span>
            <label for="edit_account_name">Customer Name</label>
            <input type="text" name="account_name" id="edit_account_name" readonly>
            <span class="error" id="edit_account_name_error"></span>
            <label for="edit_ticket_subject">Subject</label>
            <input type="text" name="s_subject" id="edit_ticket_subject" required>
            <span class="error" id="edit_ticket_subject_error"></span>
            <label for="edit_ticket_details">Message</label>
            <textarea name="s_message" id="edit_ticket_details" required></textarea>
            <span class="error" id="edit_ticket_details_error"></span>
            <label for="edit_ticket_status">Status</label>
            <input type="text" name="s_status" id="edit_ticket_status" readonly>
            <span class="error" id="edit_ticket_status_error"></span>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('editTicketModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Update Ticket</button>
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

<!-- Archive Ticket Modal -->
<div id="archiveTicketModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Archive Ticket</h2>
        </div>
        <form method="POST" id="archiveTicketForm" class="modal-form">
            <input type="hidden" name="archive_ticket" value="1">
            <input type="hidden" name="t_id" id="archive_ticket_id">
            <input type="hidden" name="archive_action" value="archive">
            <p>Are you sure you want to archive ticket for <span id="archive_customer_name"></span>?</p>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('archiveTicketModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Archive</button>
            </div>
        </form>
    </div>
</div>

<!-- Unarchive Ticket Modal -->
<div id="unarchiveTicketModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Unarchive Ticket</h2>
        </div>
        <form method="POST" id="unarchiveTicketForm" class="modal-form">
            <input type="hidden" name="archive_ticket" value="1">
            <input type="hidden" name="t_id" id="unarchive_ticket_id">
            <input type="hidden" name="archive_action" value="unarchive">
            <p>Are you sure you want to unarchive ticket for <span id="unarchive_customer_name"></span>?</p>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('unarchiveTicketModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Unarchive</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Ticket Modal -->
<div id="deleteTicketModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Delete Ticket</h2>
        </div>
        <form method="POST" id="deleteTicketForm" class="modal-form">
            <input type="hidden" name="delete_ticket" value="1">
            <input type="hidden" name="t_id" id="delete_ticket_id">
            <p>Are you sure you want to delete ticket for <span id="delete_customer_name"></span>? This action cannot be undone.</p>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('deleteTicketModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Close Ticket Modal -->
<div id="closeTicketModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Close Ticket</h2>
        </div>
        <form method="POST" id="closeTicketForm" class="modal-form">
            <input type="hidden" name="close_ticket" value="1">
            <input type="hidden" name="t_id" id="close_ticket_id">
            <input type="hidden" name="customer_id" id="close_customer_id">
            <p>Are you sure you want to close ticket for <span id="close_customer_name"></span>?</p>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('closeTicketModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Close</button>
            </div>
        </form>
    </div>
</div>

<script>
function showNotification(message, type) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = '';
    
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    
    container.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 500);
    }, 3000);
}

let currentTab = '<?php echo $tab; ?>';
let currentActivePage = <?php echo $activePage; ?>;
let currentArchivedPage = <?php echo $archivedPage; ?>;
let currentSearchTerm = '';
function showTab(tab) {
    // Prevent tab switching if pending tickets are present
    <?php if (!empty($pendingTickets)): ?>
        showNotification('Cannot switch tabs while there are pending tickets.', 'error');
        return;
    <?php endif; ?>
    const activeTable = document.getElementById('activeTable');
    const archivedTable = document.getElementById('archivedTable');
    
    activeTable.style.display = tab === 'active' ? 'block' : 'none';
    archivedTable.style.display = tab === 'archived' ? 'block' : 'none';

    const activeTableButtons = activeTable.querySelector('.tab-buttons');
    const archivedTableButtons = archivedTable.querySelector('.tab-buttons');
    const activeBtn = activeTableButtons.querySelector('.tab-btn:nth-child(1)');
    const archivedBtn = activeTableButtons.querySelector('.tab-btn:nth-child(2)');
    const archivedTableActiveBtn = archivedTableButtons.querySelector('.tab-btn:nth-child(1)');
    const archivedTableArchivedBtn = archivedTableButtons.querySelector('.tab-btn:nth-child(2)');

    activeBtn.classList.remove('active');
    archivedBtn.classList.remove('active');
    archivedTableActiveBtn.classList.remove('active');
    archivedTableArchivedBtn.classList.remove('active');

    if (tab === 'active') {
        activeBtn.classList.add('active');
    } else {
        archivedTableArchivedBtn.classList.add('active');
    }

    currentTab = tab;
    currentActivePage = tab === 'active' ? <?php echo $activePage; ?> : currentActivePage;
    currentArchivedPage = tab === 'archived' ? <?php echo $archivedPage; ?> : currentArchivedPage;
    searchTickets(currentSearchTerm, tab === 'active' ? currentActivePage : currentArchivedPage);
}

function openModal() {
    // Check for pending tickets before opening the modal
    <?php if (!empty($pendingTickets)): ?>
        showNotification('Cannot Create Ticket because there is a reported pending ticket.', 'error');
        return;
    <?php endif; ?>
    document.getElementById('addTicketModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    clearErrors();
}

function clearErrors() {
    document.querySelectorAll('.error').forEach(span => span.textContent = '');
}

function openEditModal(ticket) {
    document.getElementById('edit_ticket_id').value = ticket.id;
    document.getElementById('edit_customer_id').value = ticket.c_id;
    document.getElementById('edit_account_name').value = `${ticket.c_fname} ${ticket.c_lname}`.trim() || 'Unknown';
    document.getElementById('edit_ticket_ref').value = ticket.s_ref || '';
    document.getElementById('edit_ticket_subject').value = ticket.s_subject || '';
    document.getElementById('edit_ticket_details').value = ticket.s_message || '';
    document.getElementById('edit_ticket_status').value = ticket.status || 'Open';
    document.getElementById('editTicketModal').style.display = 'block';
}

function showViewModal(id, c_id, c_fname, c_lname, s_ref, s_subject, s_message, status, tab) {
    const safeId = htmlspecialchars(id);
    const safeCid = htmlspecialchars(c_id);
    const safeFname = htmlspecialchars(c_fname);
    const safeLname = htmlspecialchars(c_lname);
    const safeRef = htmlspecialchars(s_ref);
    const safeSubject = htmlspecialchars(s_subject);
    const safeMessage = htmlspecialchars(s_message);
    const safeStatus = htmlspecialchars(status);
    const displayStatus = (tab === 'archived' && status === 'Archived') ? 'Open' : (status || 'Open');
    const statusClass = (tab === 'archived' && status === 'Archived') ? 'open' : (status ? status.toLowerCase() : 'open');

    const viewContent = document.getElementById('viewContent');
    viewContent.innerHTML = `
        <p><strong>Reference No:</strong> ${safeRef}</p>
        <p><strong>Customer ID:</strong> ${safeCid}</p>
        <p><strong>First Name:</strong> ${safeFname}</p>
        <p><strong>Last Name:</strong> ${safeLname}</p>
        <p><strong>Subject:</strong> ${safeSubject}</p>
        <p><strong>Message:</strong> ${safeMessage}</p>
        <p><strong>Status:</strong> <span class="status-${statusClass}">${displayStatus}</span></p>
    `;
    document.getElementById('viewModal').style.display = 'block';
}

function openArchiveModal(id, customerName) {
    document.getElementById('archive_ticket_id').value = id;
    document.getElementById('archive_customer_name').textContent = customerName || 'Unknown';
    document.getElementById('archiveTicketModal').style.display = 'block';
}

function openUnarchiveModal(id, customerName) {
    document.getElementById('unarchive_ticket_id').value = id;
    document.getElementById('unarchive_customer_name').textContent = customerName || 'Unknown';
    document.getElementById('unarchiveTicketModal').style.display = 'block';
}

function openDeleteModal(id, customerName) {
    document.getElementById('delete_ticket_id').value = id;
    document.getElementById('delete_customer_name').textContent = customerName || 'Unknown';
    document.getElementById('deleteTicketModal').style.display = 'block';
}

function openCloseModal(id, customerName) {
    document.getElementById('close_ticket_id').value = id;
    document.getElementById('close_customer_id').value = '<?php echo $filterCid; ?>';
    document.getElementById('close_customer_name').textContent = customerName || 'Unknown';
    document.getElementById('closeTicketModal').style.display = 'block';
}

function showStatusRestrictedMessage() {
    showNotification('Customers cannot change ticket status.', 'error');
}

function htmlspecialchars(str) {
    if (str == null || typeof str !== 'string') {
        return str;
    }
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

function searchTickets(searchTerm, page) {
    currentSearchTerm = searchTerm;
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `suppT.php?action=search&tab=${currentTab}&search=${encodeURIComponent(searchTerm)}&search_page=${page}&c_id=<?php echo $filterCid; ?>`, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.error) {
                    showNotification(response.error, 'error');
                    return;
                }
                const tableBody = currentTab === 'active' ? document.getElementById('activeTableBody') : document.getElementById('archivedTableBody');
                const pagination = currentTab === 'active' ? document.getElementById('activePagination') : document.getElementById('archivedPagination');
                tableBody.innerHTML = response.html;
                pagination.innerHTML = response.pagination;
                if (currentTab === 'active') {
                    currentActivePage = response.currentPage;
                } else {
                    currentArchivedPage = response.currentPage;
                }
            } catch (e) {
                console.error('Error parsing JSON:', xhr.responseText);
                showNotification('Error loading tickets.', 'error');
            }
        } else {
            console.error('Request failed with status:', xhr.status);
            showNotification('Error loading tickets.', 'error');
        }
    };
    xhr.onerror = function() {
        console.error('Request error');
        showNotification('Network error occurred.', 'error');
    };
    xhr.send();
}

function debouncedSearchTickets() {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        const searchTerm = document.getElementById('searchInput').value;
        searchTickets(searchTerm, currentTab === 'active' ? currentActivePage : currentArchivedPage);
    }, 300);
}

window.addEventListener('DOMContentLoaded', () => {
    <?php if (isset($_SESSION['message'])): ?>
        showNotification("<?php echo htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); ?>", 'success');
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        showNotification("<?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>", 'error');
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if ($error): ?>
        showNotification("<?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>", 'error');
    <?php endif; ?>
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>
