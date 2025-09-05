<?php
session_start();
include 'db.php';

// Debug: Log session username and page access
error_log("AssignTech.php accessed at " . date('Y-m-d H:i:s') . " | Session username: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'Not set'));

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    error_log("No session username found, redirecting to index.php");
    header("Location: index.php");
    exit();
}

// Initialize user variables
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

// Fetch user data
$sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
$stmt = $conn->prepare($sqlUser);
if (!$stmt) {
    error_log("Prepare failed for user query: " . $conn->error);
    $_SESSION['error'] = "Database error occurred.";
    $firstName = 'Unknown';
    $lastName = '';
    $userType = 'staff';
} else {
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $resultUser = $stmt->get_result();
    if ($resultUser->num_rows > 0) {
        $row = $resultUser->fetch_assoc();
        $firstName = $row['u_fname'] ?: 'Unknown';
        $lastName = $row['u_lname'] ?: '';
        $userType = strtolower($row['u_type']) ?: 'staff';
        error_log("User fetched: username={$_SESSION['username']}, userType=$userType");
    } else {
        error_log("User not found for username: {$_SESSION['username']}");
        $_SESSION['error'] = "User not found.";
        $firstName = 'Unknown';
        $lastName = '';
        $userType = 'staff';
    }
    $stmt->close();
}

// Add technician_username and is_online columns if they don't exist
$sqlAlterRegular = "ALTER TABLE tbl_ticket ADD COLUMN IF NOT EXISTS technician_username VARCHAR(255) DEFAULT NULL";
$sqlAlterSupport = "ALTER TABLE tbl_supp_tickets ADD COLUMN IF NOT EXISTS technician_username VARCHAR(255) DEFAULT NULL";
$sqlAlterLoginStatus = "ALTER TABLE tbl_user ADD COLUMN IF NOT EXISTS is_online TINYINT(1) DEFAULT 1";
if (!$conn->query($sqlAlterRegular)) {
    error_log("Failed to alter tbl_ticket: " . $conn->error);
}
if (!$conn->query($sqlAlterSupport)) {
    error_log("Failed to alter tbl_supp_tickets: " . $conn->error);
}
if (!$conn->query($sqlAlterLoginStatus)) {
    error_log("Failed to alter tbl_user for is_online: " . $conn->error);
}

// Handle AJAX assign ticket request
if (isset($_POST['assign_ticket'])) {
    if (!isset($_POST['t_ref']) || !isset($_POST['technician_username']) || !isset($_POST['ticket_type'])) {
        error_log("Missing POST data: t_ref=" . ($_POST['t_ref'] ?? 'unset') . ", technician_username=" . ($_POST['technician_username'] ?? 'unset') . ", ticket_type=" . ($_POST['ticket_type'] ?? 'unset'));
        echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
        exit();
    }

    $t_ref = trim($_POST['t_ref']);
    $technician_username = trim($_POST['technician_username']);
    $ticket_type = trim($_POST['ticket_type']);

    if (!in_array($ticket_type, ['regular', 'support'])) {
        error_log("Invalid ticket_type: $ticket_type");
        echo json_encode(['success' => false, 'error' => 'Invalid ticket type.']);
        exit();
    }

    error_log("Attempting to assign ticket: t_ref=$t_ref, technician_username=$technician_username, ticket_type=$ticket_type");

    $sqlCheckTech = "SELECT is_online FROM tbl_user WHERE u_username = ? AND u_type = 'technician' AND u_status = 'active'";
    $stmtCheckTech = $conn->prepare($sqlCheckTech);
    if (!$stmtCheckTech) {
        error_log("Prepare failed for technician check: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error: Unable to verify technician.']);
        exit();
    }
    $stmtCheckTech->bind_param("s", $technician_username);
    $stmtCheckTech->execute();
    $stmtCheckTech->bind_result($is_online);
    $fetchResult = $stmtCheckTech->fetch();
    $stmtCheckTech->close();
    if (!$fetchResult) {
        error_log("Technician does not exist or is not active: $technician_username");
        echo json_encode(['success' => false, 'error' => 'Technician does not exist or is not active.']);
        exit();
    }
    if (!($is_online === null || $is_online == 1)) {
        error_log("Technician is unavailable: $technician_username");
        echo json_encode(['success' => false, 'error' => 'Cannot assign ticket because the technician is unavailable.']);
        exit();
    }

    $sqlCountAssigned = "SELECT 
        (SELECT COUNT(*) FROM tbl_ticket WHERE technician_username = ? AND t_status != 'Closed') +
        (SELECT COUNT(*) FROM tbl_supp_tickets WHERE technician_username = ? AND s_status != 'Closed') AS total";
    $stmtCountAssigned = $conn->prepare($sqlCountAssigned);
    if (!$stmtCountAssigned) {
        error_log("Prepare failed for count query: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error: Unable to check ticket count.']);
        exit();
    }
    $stmtCountAssigned->bind_param("ss", $technician_username, $technician_username);
    $stmtCountAssigned->execute();
    $stmtCountAssigned->bind_result($totalAssigned);
    $stmtCountAssigned->fetch();
    $stmtCountAssigned->close();

    if ($totalAssigned >= 5) {
        error_log("Technician $technician_username has reached max tickets: $totalAssigned");
        echo json_encode(['success' => false, 'error' => 'Technician has reached the maximum of 5 assigned tickets.']);
        exit();
    }

    $table = ($ticket_type === 'regular') ? 'tbl_ticket' : 'tbl_supp_tickets';
    $refColumn = ($ticket_type === 'regular') ? 't_ref' : 's_ref';
    $statusColumn = ($ticket_type === 'regular') ? 't_status' : 's_status';
    $sqlCheck = "SELECT COUNT(*) FROM $table WHERE $refColumn = ? AND (technician_username IS NULL OR technician_username = '') AND $statusColumn != 'Closed'";
    $stmtCheck = $conn->prepare($sqlCheck);
    if (!$stmtCheck) {
        error_log("Prepare failed for ticket check query: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error: Unable to verify ticket.']);
        exit();
    }
    $stmtCheck->bind_param("s", $t_ref);
    $stmtCheck->execute();
    $stmtCheck->bind_result($ticketExists);
    $stmtCheck->fetch();
    $stmtCheck->close();

    if ($ticketExists == 0) {
        error_log("Ticket does not exist or is already assigned/closed: t_ref=$t_ref, table=$table");
        echo json_encode(['success' => false, 'error' => 'Ticket does not exist or is already assigned/closed.']);
        exit();
    }

    $sqlAssign = ($ticket_type === 'regular') ?
        "UPDATE tbl_ticket SET technician_username = ? WHERE t_ref = ? AND t_status != 'Closed'" :
        "UPDATE tbl_supp_tickets SET technician_username = ? WHERE s_ref = ? AND s_status != 'Closed'";
    $stmtAssign = $conn->prepare($sqlAssign);
    if (!$stmtAssign) {
        error_log("Prepare failed for assign query: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare assignment query.']);
        exit();
    }
    $stmtAssign->bind_param("ss", $technician_username, $t_ref);
    if ($stmtAssign->execute()) {
        $logDescription = "Assigned ticket $t_ref to technician $technician_username by $firstName $lastName (Type: $ticket_type)";
        $logType = "Staff $firstName $lastName";
        $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
        $stmtLog = $conn->prepare($sqlLog);
        if ($stmtLog) {
            $stmtLog->bind_param("ss", $logDescription, $logType);
            $stmtLog->execute();
            $stmtLog->close();
        } else {
            error_log("Failed to prepare log query: " . $conn->error);
        }
        echo json_encode([
            'success' => true,
            'message' => 'Ticket assigned successfully.',
            't_ref' => $t_ref,
            'ticket_type' => $ticket_type
        ]);
    } else {
        error_log("Failed to execute assign query: " . $stmtAssign->error);
        echo json_encode(['success' => false, 'error' => 'Failed to assign ticket: ' . $stmtAssign->error]);
    }
    $stmtAssign->close();
    exit();
}

// Handle AJAX toggle status request
if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (!isset($_POST['username'])) {
        error_log("Missing username for toggle_status");
        echo json_encode(['success' => false, 'error' => 'Missing technician username.']);
        exit();
    }

    $username = trim($_POST['username']);
    error_log("Toggle status requested for username: $username");

    $sqlCheck = "SELECT is_online FROM tbl_user WHERE u_username = ? AND u_type = 'technician' AND u_status = 'active'";
    $stmtCheck = $conn->prepare($sqlCheck);
    if (!$stmtCheck) {
        error_log("Prepare failed for toggle status check: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error: Unable to verify technician.']);
        exit();
    }
    $stmtCheck->bind_param("s", $username);
    $stmtCheck->execute();
    $stmtCheck->bind_result($currentStatus);
    $fetchResult = $stmtCheck->fetch();
    $stmtCheck->close();

    if (!$fetchResult) {
        error_log("Technician not found or not active: $username");
        echo json_encode(['success' => false, 'error' => 'Technician not found or not active.']);
        exit();
    }

    $newStatus = ($currentStatus === null || $currentStatus == 1) ? 0 : 1;
    $sqlUpdate = "UPDATE tbl_user SET is_online = ? WHERE u_username = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    if (!$stmtUpdate) {
        error_log("Prepare failed for toggle status update: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error: Unable to update status.']);
        exit();
    }
    $stmtUpdate->bind_param("is", $newStatus, $username);
    if ($stmtUpdate->execute()) {
        $logDescription = "Toggled status for technician $username to " . ($newStatus ? 'Available' : 'Unavailable') . " by $firstName $lastName";
        $logType = "Staff $firstName $lastName";
        $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
        $stmtLog = $conn->prepare($sqlLog);
        if ($stmtLog) {
            $stmtLog->bind_param("ss", $logDescription, $logType);
            $stmtLog->execute();
            $stmtLog->close();
        } else {
            error_log("Failed to prepare log query: " . $conn->error);
        }
        echo json_encode(['success' => true, 'new_status' => $newStatus ? 'Available' : 'Unavailable']);
    } else {
        error_log("Failed to execute toggle status update: " . $stmtUpdate->error);
        echo json_encode(['success' => false, 'error' => 'Failed to update status: ' . $stmtUpdate->error]);
    }
    $stmtUpdate->close();
    $conn->close();
    exit();
}

// Handle AJAX ticket search for assign modal
if (isset($_GET['action']) && $_GET['action'] === 'search_tickets') {
    $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
    $searchTerm = $conn->real_escape_string($searchTerm);
    $likeSearch = '%' . $searchTerm . '%';

    $sql = "";
    $params = [];
    $paramTypes = "";

    if ($searchTerm && strtolower($searchTerm) === 'regular tickets') {
        $sql = "SELECT t_ref, IFNULL(t_aname, 'Unknown') AS display_name, 'regular' AS ticket_type 
                FROM tbl_ticket 
                WHERE t_status != 'Closed' AND (technician_username IS NULL OR technician_username = '')";
    } elseif ($searchTerm && strtolower($searchTerm) === 'support tickets') {
        $sql = "SELECT st.s_ref AS t_ref, IFNULL(CONCAT(c.c_fname, ' ', c.c_lname), 'Unknown') AS display_name, 'support' AS ticket_type 
                FROM tbl_supp_tickets st 
                JOIN tbl_customer c ON st.c_id = c.c_id 
                WHERE st.s_status != 'Closed' AND (st.technician_username IS NULL OR st.technician_username = '')";
    } else {
        $sql = "SELECT t_ref, display_name, ticket_type FROM (
                    SELECT t_ref, IFNULL(t_aname, 'Unknown') AS display_name, 'regular' AS ticket_type 
                    FROM tbl_ticket 
                    WHERE t_status != 'Closed' AND (technician_username IS NULL OR technician_username = '') " . ($searchTerm ? "AND t_ref LIKE ?" : "") . "
                    UNION
                    SELECT st.s_ref AS t_ref, IFNULL(CONCAT(c.c_fname, ' ', c.c_lname), 'Unknown') AS display_name, 'support' AS ticket_type 
                    FROM tbl_supp_tickets st 
                    JOIN tbl_customer c ON st.c_id = c.c_id 
                    WHERE st.s_status != 'Closed' AND (st.technician_username IS NULL OR st.technician_username = '') " . ($searchTerm ? "AND st.s_ref LIKE ?" : "") . "
                ) AS combined
                ORDER BY t_ref";
        $paramTypes = $searchTerm ? "ss" : "";
        $params = $searchTerm ? [$likeSearch, $likeSearch] : [];
    }

    error_log("Ticket search query: $sql, Params: " . json_encode($params));

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Search query prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit();
    }
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    error_log("Tickets returned: " . $result->num_rows);

    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            error_log("Ticket: " . json_encode($row));
            echo "<tr class='ticket-row' data-tref='" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "' data-ticket-type='" . htmlspecialchars($row['ticket_type'], ENT_QUOTES, 'UTF-8') . "' onclick=\"selectTicket('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['ticket_type'], ENT_QUOTES, 'UTF-8') . "')\" style='cursor: pointer;'>
                    <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['display_name'], ENT_QUOTES, 'UTF-8') . "</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='2' style='text-align: center;'>No active unassigned tickets found.</td></tr>";
    }
    $tableRows = ob_get_clean();

    echo json_encode(['success' => true, 'html' => $tableRows]);
    $stmt->close();
    $conn->close();
    exit();
}

// Handle AJAX search request for technicians
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $searchTerm = $conn->real_escape_string($searchTerm);
    $likeSearch = '%' . $searchTerm . '%';

    $sqlCount = "SELECT COUNT(*) AS total FROM tbl_user 
                 WHERE u_type = 'technician' AND u_status = 'active' 
                 AND (u_fname LIKE ? OR u_lname LIKE ? OR u_email LIKE ?)";
    $sql = "SELECT u.u_fname, u.u_lname, u.u_email, u.u_type, u.u_status, u.u_username, u.is_online,
                   (SELECT COUNT(*) FROM tbl_ticket t WHERE t.technician_username = u.u_username AND t.t_status != 'Closed') +
                   (SELECT COUNT(*) FROM tbl_supp_tickets st WHERE st.technician_username = u.u_username AND st.s_status != 'Closed') AS open_tickets,
                   (SELECT COUNT(*) FROM tbl_ticket t WHERE t.technician_username = u.u_username AND t.t_status = 'Closed') +
                   (SELECT COUNT(*) FROM tbl_supp_tickets st WHERE st.technician_username = u.u_username AND st.s_status = 'Closed') AS closed_tickets
            FROM tbl_user u
            WHERE u.u_type = 'technician' AND u_status = 'active' 
            AND (u_fname LIKE ? OR u_lname LIKE ? OR u_email LIKE ?)
            ORDER BY u.u_fname, u.u_lname
            LIMIT ?, ?";

    $stmtCount = $conn->prepare($sqlCount);
    if (!$stmtCount) {
        error_log("Count query prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit();
    }
    $stmtCount->bind_param("sss", $likeSearch, $likeSearch, $likeSearch);
    $stmtCount->execute();
    $countResult = $stmtCount->get_result();
    $totalRow = $countResult->fetch_assoc();
    $total = $totalRow['total'];
    $totalPages = ceil($total / $limit);
    $stmtCount->close();

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Search query prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit();
    }
    $stmt->bind_param("sssii", $likeSearch, $likeSearch, $likeSearch, $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $statusDisplay = ($row['is_online'] === null || $row['is_online'] == 1) ? 'Available' : 'Unavailable';
            $badgeColor = ($row['is_online'] === null || $row['is_online'] == 1) ? '#00b894' : '#d63031';
            $technicianName = htmlspecialchars($row['u_fname'] . ' ' . $row['u_lname'], ENT_QUOTES, 'UTF-8');
            $assignDisabled = ($row['is_online'] === null || $row['is_online'] == 1) ? '' : 'disabled';
            echo "<tr data-username='" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "' data-status='" . $statusDisplay . "'>
                    <td>" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['u_type'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>
                        <div class='status-container'>
                            <span class='status-badge' style='background-color: $badgeColor'>" . $statusDisplay . "</span>
                            <div class='ticket-counts'>
                                <span>Open: " . htmlspecialchars($row['open_tickets'], ENT_QUOTES, 'UTF-8') . "</span>
                                <span>Closed: " . htmlspecialchars($row['closed_tickets'], ENT_QUOTES, 'UTF-8') . "</span>
                            </div>
                        </div>
                    </td>
                    <td class='action-buttons'>
                        <a class='toggle-btn' href='#' onclick=\"toggleTechnicianStatus('" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "')\" title='Toggle Status'><i class='fas fa-sync-alt'></i></a>
                        <a class='assign-btn $assignDisabled' href='#' onclick=\"handleAssignClick('" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "', '" . $technicianName . "', '" . $statusDisplay . "')\" title='Assign Ticket'><i class='fas fa-user-plus'></i></a>
                    </td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='6' style='text-align: center;'>No technicians found.</td></tr>";
    }
    $tableRows = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $tableRows,
        'page' => $page,
        'totalPages' => $totalPages
    ]);
    $stmt->close();
    $conn->close();
    exit();
}

// Pagination setup for technicians
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$totalQuery = "SELECT COUNT(*) AS total FROM tbl_user WHERE u_type = 'technician' AND u_status = 'active'";
$totalResult = $conn->query($totalQuery);
if (!$totalResult) {
    error_log("Total query failed: " . $conn->error);
    $_SESSION['error'] = "Database error occurred.";
} else {
    $totalRow = $totalResult->fetch_assoc();
    $total = $totalRow['total'];
}
$totalPages = ceil($total / $limit);

$sqlTechnicians = "SELECT 
    u.u_fname, 
    u.u_lname, 
    u.u_email, 
    u.u_type, 
    u.u_status, 
    u.u_username,
    u.is_online,
    (SELECT COUNT(*) FROM tbl_ticket t WHERE t.technician_username = u.u_username AND t.t_status != 'Closed') +
    (SELECT COUNT(*) FROM tbl_supp_tickets st WHERE st.technician_username = u.u_username AND st.s_status != 'Closed') AS open_tickets,
    (SELECT COUNT(*) FROM tbl_ticket t WHERE t.technician_username = u.u_username AND t.t_status = 'Closed') +
    (SELECT COUNT(*) FROM tbl_supp_tickets st WHERE st.technician_username = u.u_username AND st.s_status = 'Closed') AS closed_tickets
FROM tbl_user u
WHERE u.u_type = 'technician' AND u_status = 'active' 
ORDER BY u.u_fname, u.u_lname
LIMIT ?, ?";
$stmtTechnicians = $conn->prepare($sqlTechnicians);
if (!$stmtTechnicians) {
    error_log("Technicians query prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error occurred.";
} else {
    $stmtTechnicians->bind_param("ii", $offset, $limit);
    $stmtTechnicians->execute();
    $resultTechnicians = $stmtTechnicians->get_result();
    $stmtTechnicians->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | Technicians</title>
    <link rel="stylesheet" href="AssignTech.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3d28e2;
            --secondary: #372fa7;
        }

        .status-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-height: 60px;
        }

        .status-badge {
            font-weight: 500;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 12px;
            width: fit-content;
        }

        .ticket-counts {
            display: flex;
            gap: 8px;
            font-size: 11px;
            color: #666;
        }

        .ticket-counts span {
            white-space: nowrap;
        }

        .toggle-btn, .assign-btn {
            background: #007bff;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 5px;
            margin-right: 5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }

        .toggle-btn:hover, .assign-btn:hover:not(.disabled) {
            background: #0056b3;
        }

        .toggle-btn i, .assign-btn i {
            font-size: 16px;
        }

        .assign-btn.disabled {
            pointer-events: none;
            opacity: 0.6;
        }

        #tickets-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .ticket-row.selected {
            background-color: #e0f7fa;
        }

        /* Button styling */
        .modal-btn.confirm:disabled {
            background-color: #cccccc !important;
            opacity: 0.6 !important;
            cursor: not-allowed;
        }

        .modal-btn.cancel {
            background-color: var(--primary); /* Use theme secondary color */
            opacity: 1;
            cursor: pointer;
        }

        .modal-btn.confirm {
            background-color: var(--primary); /* Use theme primary color */
            opacity: 1;
        }

     
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="staffD.php"><img src="image/ticket.png" alt="Regular Tickets" class="icon" /> <span>Regular Tickets</span></a></li>
            <li><a href="assetsT.php"><img src="image/assets.png" alt="Assets" class="icon" /> <span>Assets</span></a></li>
            <li><a href="AllCustomersT.php"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers Ticket</span></a></li>
            <li><a href="customersT.php"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers</span></a></li>
            <li><a href="borrowedStaff.php"><img src="image/borrowed.png" alt="Borrowed Assets" class="icon" /> <span>Borrowed Assets</span></a></li>
            <li><a href="addC.php"><img src="image/add.png" alt="Add Customer" class="icon" /> <span>Add Customer</span></a></li>
            <li><a href="AssignTech.php" class="active"><img src="image/technician.png" alt="Technicians" class="icon" /> <span>Technicians</span></a></li>
            <li><a href="Payments.php"><img src="image/transactions.png" alt="Payment Transactions" class="icon" /> <span>Payment Transactions</span></a></li>
        </ul>
        <footer>
            <a href="index.php?action=logout" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>

    <div class="container">
        <div class="upper">
            <h1>Technicians</h1>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search technicians..." onkeyup="debouncedSearchTechnicians()">
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
            <h2>Technician Status</h2>
            <table id="technicians-table">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="technicians-tbody">
                    <?php
                    if ($resultTechnicians && $resultTechnicians->num_rows > 0) {
                        while ($row = $resultTechnicians->fetch_assoc()) {
                            $statusDisplay = ($row['is_online'] === null || $row['is_online'] == 1) ? 'Available' : 'Unavailable';
                            $badgeColor = ($row['is_online'] === null || $row['is_online'] == 1) ? '#00b894' : '#d63031';
                            $technicianName = htmlspecialchars($row['u_fname'] . ' ' . $row['u_lname'], ENT_QUOTES, 'UTF-8');
                            $assignDisabled = ($row['is_online'] === null || $row['is_online'] == 1) ? '' : 'disabled';
                            echo "<tr data-username='" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "' data-status='" . $statusDisplay . "'>
                                    <td>" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . htmlspecialchars($row['u_type'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>
                                        <div class='status-container'>
                                            <span class='status-badge' style='background-color: $badgeColor'>" . $statusDisplay . "</span>
                                            <div class='ticket-counts'>
                                                <span>Open: " . htmlspecialchars($row['open_tickets'], ENT_QUOTES, 'UTF-8') . "</span>
                                                <span>Closed: " . htmlspecialchars($row['closed_tickets'], ENT_QUOTES, 'UTF-8') . "</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class='action-buttons'>
                                        <a class='toggle-btn' href='#' onclick=\"toggleTechnicianStatus('" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "')\" title='Toggle Status'><i class='fas fa-sync-alt'></i></a>
                                        <a class='assign-btn $assignDisabled' href='#' onclick=\"handleAssignClick('" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "', '" . $technicianName . "', '" . $statusDisplay . "')\" title='Assign Ticket'><i class='fas fa-user-plus'></i></a>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align: center;'>No technicians found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <div class="pagination" id="technicians-pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?>
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <span class="current-page">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Assign Ticket Modal -->
<div id="assignTicketModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign Ticket to <span id="assignTechnicianName"></span></h2>
        </div>
        <div class="modal-search-container">
            <input type="text" id="ticketSearchInput" placeholder="Search active tickets by reference, 'Regular Tickets', or 'Support Tickets'..." onkeyup="debouncedSearchTickets()">
            <span class="search-icon"><i class="fas fa-search"></i></span>
        </div>
        <div class="table-box">
            <table id="tickets-table">
                <thead>
                    <tr>
                        <th>Ticket No</th>
                        <th>Name</th>
                    </tr>
                </thead>
                <tbody id="tickets-tbody"></tbody>
            </table>
        </div>
        <form id="assignTicketForm" method="POST">
            <input type="hidden" name="assign_ticket" value="1">
            <input type="hidden" name="t_ref" id="assignTicketRef">
            <input type="hidden" name="technician_username" id="assignTechnicianUsername">
            <input type="hidden" name="ticket_type" id="assignTicketType">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('assignTicketModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm" id="assignButton" disabled>Assign</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 1s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 1000);
        }, 5000);
    });

    // Poll for status updates every 30 seconds
    setInterval(searchTechnicians, 30000);
});

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

function searchTechnicians(page = 1) {
    const searchTerm = document.getElementById('searchInput').value;
    const tbody = document.getElementById('technicians-tbody');
    const paginationContainer = document.getElementById('technicians-pagination');

    fetch(`AssignTech.php?action=search&search=${encodeURIComponent(searchTerm)}&page=${page}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (!data.html.includes('<tr')) {
                console.error('Invalid response: Expected table rows, got:', data.html);
                tbody.innerHTML = '<tr><td colspan="6">Error: Invalid server response.</td></tr>';
                return;
            }
            tbody.innerHTML = data.html;
            updatePagination(data.page, data.totalPages);
        } else {
            console.error('Search error:', data.error);
            tbody.innerHTML = '<tr><td colspan="6">Error loading technicians: ' + data.error + '</td></tr>';
        }
    })
    .catch(error => {
        console.error('Error searching technicians:', error);
        tbody.innerHTML = '<tr><td colspan="6">Error loading technicians: ' + error.message + '</td></tr>';
    });
}

const debouncedSearchTechnicians = debounce(searchTechnicians, 300);
const debouncedSearchTickets = debounce(searchTickets, 300);

function updatePagination(currentPage, totalPages) {
    const paginationContainer = document.getElementById('technicians-pagination');
    let paginationHtml = '';

    if (currentPage > 1) {
        paginationHtml += `<a href="javascript:searchTechnicians(${currentPage - 1})" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="javascript:searchTechnicians(${currentPage + 1})" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    if (modalId === 'assignTicketModal') {
        document.getElementById('ticketSearchInput').value = '';
        document.getElementById('tickets-tbody').innerHTML = '';
        document.getElementById('assignTicketRef').value = '';
        document.getElementById('assignTicketType').value = '';
        const assignButton = document.getElementById('assignButton');
        assignButton.disabled = true;
        assignButton.style.backgroundColor = '#cccccc';
        assignButton.style.opacity = '0.6';
    }
}

function toggleTechnicianStatus(username) {
    const alertContainer = document.querySelector('.alert-container');
    fetch('AssignTech.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_status&username=${encodeURIComponent(username)}`
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const row = document.querySelector(`tr[data-username='${CSS.escape(username)}']`);
            if (row) {
                const badge = row.querySelector('.status-badge');
                badge.textContent = data.new_status;
                badge.style.backgroundColor = data.new_status === 'Available' ? '#00b894' : '#d63031';
                row.setAttribute('data-status', data.new_status);
                const assignButton = row.querySelector('.assign-btn');
                if (data.new_status === 'Available') {
                    assignButton.classList.remove('disabled');
                    assignButton.style.pointerEvents = 'auto';
                    assignButton.style.opacity = '1';
                } else {
                    assignButton.classList.add('disabled');
                    assignButton.style.pointerEvents = 'none';
                    assignButton.style.opacity = '0.6';
                }
            }
            const successAlert = document.createElement('div');
            successAlert.className = 'alert alert-success';
            successAlert.textContent = `Status for technician ${username} updated to ${data.new_status}.`;
            alertContainer.appendChild(successAlert);
            setTimeout(() => {
                successAlert.style.transition = 'opacity 1s ease-out';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 1000);
            }, 5000);
        } else {
            console.error('Toggle status error:', data.error);
            const errorAlert = document.createElement('div');
            errorAlert.className = 'alert alert-error';
            errorAlert.textContent = data.error;
            alertContainer.appendChild(errorAlert);
            setTimeout(() => {
                errorAlert.style.transition = 'opacity 1s ease-out';
                errorAlert.style.opacity = '0';
                setTimeout(() => errorAlert.remove(), 1000);
            }, 5000);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        const errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-error';
        errorAlert.textContent = 'An error occurred while toggling the status.';
        alertContainer.appendChild(errorAlert);
        setTimeout(() => {
            errorAlert.style.transition = 'opacity 1s ease-out';
            errorAlert.style.opacity = '0';
            setTimeout(() => errorAlert.remove(), 1000);
        }, 5000);
    });
}

function handleAssignClick(technicianUsername, technicianName, status) {
    if (status === 'Unavailable') {
        const alertContainer = document.querySelector('.alert-container');
        const errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-error';
        errorAlert.textContent = 'Cannot assign ticket because the technician is unavailable.';
        alertContainer.appendChild(errorAlert);
        setTimeout(() => {
            errorAlert.style.transition = 'opacity 1s ease-out';
            errorAlert.style.opacity = '0';
            setTimeout(() => errorAlert.remove(), 1000);
        }, 5000);
        return;
    }
    document.getElementById('assignTechnicianUsername').value = technicianUsername;
    document.getElementById('assignTechnicianName').textContent = technicianName;
    document.getElementById('assignTicketModal').style.display = 'block';
    
    // Initialize assign button as disabled with gray styling
    const assignButton = document.getElementById('assignButton');
    assignButton.disabled = true;
    assignButton.style.backgroundColor = '#cccccc';
    assignButton.style.opacity = '0.6';
    
    // Clear previous selections
    document.getElementById('assignTicketRef').value = '';
    document.getElementById('assignTicketType').value = '';
    document.getElementById('ticketSearchInput').value = '';
    document.getElementById('tickets-tbody').innerHTML = '';
    
    searchTickets();
}

function selectTicket(t_ref, ticket_type) {
    if (!['regular', 'support'].includes(ticket_type)) {
        console.error(`Invalid ticket_type: ${ticket_type}`);
        return;
    }
    document.getElementById('assignTicketRef').value = t_ref;
    document.getElementById('assignTicketType').value = ticket_type;
    const assignButton = document.getElementById('assignButton');
    assignButton.disabled = false;
    assignButton.style.backgroundColor = 'var(--primary)';
    assignButton.style.opacity = '1';

    const rows = document.querySelectorAll('.ticket-row');
    rows.forEach(row => row.classList.remove('selected'));
    const selectedRow = Array.from(rows).find(row => row.getAttribute('data-tref') === t_ref);
    if (selectedRow) selectedRow.classList.add('selected');
}

function searchTickets() {
    const searchTerm = document.getElementById('ticketSearchInput').value;
    const tbody = document.getElementById('tickets-tbody');

    fetch(`AssignTech.php?action=search_tickets&search=${encodeURIComponent(searchTerm)}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            tbody.innerHTML = data.html;
        } else {
            console.error('Error loading tickets:', data.error);
            tbody.innerHTML = '<tr><td colspan="2">Error loading tickets: ' + data.error + '</td></tr>';
        }
    })
    .catch(error => {
        console.error('Error searching tickets:', error);
        tbody.innerHTML = '<tr><td colspan="2">Error loading tickets: ' + error.message + '</td></tr>';
    });
}

document.getElementById('assignTicketForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const alertContainer = document.querySelector('.alert-container');
    alertContainer.innerHTML = '';

    const formData = new FormData(this);

    fetch('AssignTech.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const t_ref = data.t_ref;
            const row = document.querySelector(`.ticket-row[data-tref='${CSS.escape(t_ref)}']`);
            if (row) {
                row.remove();
                console.log(`Removed ticket row with t_ref: ${t_ref}`);
            } else {
                console.log(`Ticket row with t_ref: ${t_ref} not found`);
            }
            document.getElementById('assignTicketRef').value = '';
            document.getElementById('assignTicketType').value = '';
            const assignButton = document.getElementById('assignButton');
            assignButton.disabled = true;
            assignButton.style.backgroundColor = '#cccccc';
            assignButton.style.opacity = '0.6';
            searchTickets();
            closeModal('assignTicketModal');
            const successAlert = document.createElement('div');
            successAlert.className = 'alert alert-success';
            successAlert.textContent = data.message;
            alertContainer.appendChild(successAlert);
            setTimeout(() => {
                successAlert.style.transition = 'opacity 1s ease-out';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 1000);
            }, 5000);
            searchTechnicians();
        } else {
            console.error('Assignment error:', data.error);
            const errorAlert = document.createElement('div');
            errorAlert.className = 'alert alert-error';
            errorAlert.textContent = data.error;
            alertContainer.appendChild(errorAlert);
            setTimeout(() => {
                errorAlert.style.transition = 'opacity 1s ease-out';
                errorAlert.style.opacity = '0';
                setTimeout(() => errorAlert.remove(), 1000);
            }, 5000);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        const errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-error';
        errorAlert.textContent = 'An error occurred while assigning the ticket. Check console for details.';
        alertContainer.appendChild(errorAlert);
        setTimeout(() => {
            errorAlert.style.transition = 'opacity 1s ease-out';
            errorAlert.style.opacity = '0';
            setTimeout(() => errorAlert.remove(), 1000);
        }, 5000);
    });
});
</script>
</body>
</html>
