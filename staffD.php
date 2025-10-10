<?php
session_start();
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Initialize variables
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
    error_log("Prepare failed for user fetch: " . $conn->error);
    $_SESSION['error'] = "Database error.";
    header("Location: index.php");
    exit();
}
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$resultUser = $stmt->get_result();
if ($resultUser->num_rows > 0) {
    $row = $resultUser->fetch_assoc();
    $firstName = $row['u_fname'] ?: 'Unknown';
    $lastName = $row['u_lname'] ?: '';
    $userType = strtolower($row['u_type']) ?: 'staff';
    error_log("User fetched: username={$_SESSION['username']}, userType=$userType");
    
    if ($userType === 'staff' && !isset($_SESSION['login_logged'])) {
        $logDescription = "Staff $firstName has successfully logged in";
        $logType = "Staff $firstName $lastName";
        $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
        $stmtLog = $conn->prepare($sqlLog);
        if ($stmtLog) {
            $stmtLog->bind_param("ss", $logDescription, $logType);
            $stmtLog->execute();
            $stmtLog->close();
            $_SESSION['login_logged'] = true;
        } else {
            error_log("Prepare failed for login log: " . $conn->error);
        }
    }
} else {
    error_log("User not found for username: {$_SESSION['username']}");
    $_SESSION['error'] = "User not found.";
    header("Location: index.php");
    exit();
}
$stmt->close();

// Fetch customers for filter and search
// Fetch customers for add/edit modals (unchanged)
$sqlCustomers = "SELECT c_fname, c_lname, c_email FROM tbl_customer ORDER BY c_fname, c_lname";
$resultCustomers = $conn->query($sqlCustomers);
$customers = [];
if ($resultCustomers && $resultCustomers->num_rows > 0) {
    while ($row = $resultCustomers->fetch_assoc()) {
        $customers[] = [
            'full_name' => $row['c_fname'] . ' ' . $row['c_lname'],
            'first_name' => $row['c_fname'],
            'last_name' => $row['c_lname'],
            'email' => $row['c_email']
        ];
    }
} else {
    error_log("No customers found in tbl_customer: " . ($resultCustomers ? 'Empty result' : $conn->error));
    $accountnameErr = "No customers available in the database. Please add customers first.";
}

// Fetch unique account names for filter based on tab
$accountNames = [];
$tab = isset($_GET['tab']) && $_GET['tab'] === 'archived' ? 'archived' : 'active';
if ($tab === 'archived') {
    $sqlAccountNames = "SELECT DISTINCT t_aname FROM tbl_ticket WHERE t_details LIKE 'ARCHIVED:%' ORDER BY t_aname";
} else {
    $sqlAccountNames = "SELECT DISTINCT t_aname FROM tbl_ticket WHERE t_details NOT LIKE 'ARCHIVED:%' ORDER BY t_aname";
}
$resultAccountNames = $conn->query($sqlAccountNames);
if ($resultAccountNames && $resultAccountNames->num_rows > 0) {
    while ($row = $resultAccountNames->fetch_assoc()) {
        $accountNames[] = $row['t_aname'];
    }
} else {
    error_log("No account names found in tbl_ticket for tab: $tab - " . ($resultAccountNames ? 'Empty result' : $conn->error));
}
// Initialize variables for add ticket validation
$accountname = $subject = $issuedetails = $ticketstatus = $t_ref = "";
$accountnameErr = $subjectErr = $issuedetailsErr = $ticketstatusErr = $t_refErr = "";

// Check for pre-filled account name from query parameter
if (isset($_GET['aname']) && !empty($_GET['aname'])) {
    $accountname = urldecode(trim($_GET['aname']));
    if (!preg_match("/^[a-zA-Z\s'-]+$/", $accountname)) {
        $accountnameErr = "Account Name contains invalid characters.";
        $accountname = "";
        $logDescription = "Invalid account name attempt: " . htmlspecialchars($accountname, ENT_QUOTES, 'UTF-8');
        $logType = "Staff $firstName $lastName";
        $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
        $stmtLog = $conn->prepare($sqlLog);
        if ($stmtLog) {
            $stmtLog->bind_param("ss", $logDescription, $logType);
            $stmtLog->execute();
            $stmtLog->close();
        }
    } elseif (empty($customers)) {
        $accountnameErr = "No customers available in the database.";
        $accountname = "";
    } else {
        $validCustomer = false;
        foreach ($customers as $customer) {
            if (strcasecmp($customer['full_name'], $accountname) === 0) {
                $validCustomer = true;
                $accountname = $customer['full_name'];
                break;
            }
        }
        if (!$validCustomer) {
            $accountnameErr = "Account Name does not exist in customer database.";
            $accountname = "";
            $logDescription = "Attempted to pre-fill invalid customer name: " . htmlspecialchars($accountname, ENT_QUOTES, 'UTF-8');
            $logType = "Staff $firstName $lastName";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            if ($stmtLog) {
                $stmtLog->bind_param("ss", $logDescription, $logType);
                $stmtLog->execute();
                $stmtLog->close();
            }
        }
    }
}

// Handle AJAX request for fetching customer email
if (isset($_GET['action']) && $_GET['action'] === 'get_customer_email') {
    header('Content-Type: application/json');
    $accountName = isset($_GET['account_name']) ? trim($_GET['account_name']) : '';

    if (empty($accountName)) {
        echo json_encode(['success' => false, 'error' => 'Account name is required.']);
        exit();
    }

    $sql = "SELECT c_email FROM tbl_customer WHERE CONCAT(c_fname, ' ', c_lname) = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for customer email fetch: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare statement.']);
        exit();
    }
    $stmt->bind_param("s", $accountName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['success' => true, 'email' => $row['c_email']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Customer not found.']);
    }
    $stmt->close();
    exit();
}

// Handle AJAX request for fetching ticket counts
if (isset($_GET['action']) && $_GET['action'] === 'get_counts') {
    header('Content-Type: application/json');
    $totalActiveQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details NOT LIKE 'ARCHIVED:%'";
    $totalActiveResult = $conn->query($totalActiveQuery);
    $totalActive = $totalActiveResult ? $totalActiveResult->fetch_assoc()['total'] : 0;

    $totalArchivedQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details LIKE 'ARCHIVED:%'";
    $totalArchivedResult = $conn->query($totalArchivedQuery);
    $totalArchived = $totalArchivedResult ? $totalArchivedResult->fetch_assoc()['total'] : 0;

    echo json_encode([
        'success' => true,
        'active' => $totalActive,
        'archived' => $totalArchived
    ]);
    exit();
}

// Handle AJAX search request with filters
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['tab'])) {
    header('Content-Type: application/json');
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $accountFilter = isset($_GET['account_filter']) ? trim($_GET['account_filter']) : '';
    $typeFilter = isset($_GET['type_filter']) ? trim($_GET['type_filter']) : '';
    $tab = $_GET['tab'] === 'archived' ? 'archived' : 'active';
    $page = isset($_GET['search_page']) ? max(1, (int)$_GET['search_page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $output = '';

    if ($tab === 'active') {
        $statusCondition = "t_details NOT LIKE 'ARCHIVED:%'";
    } else {
        $statusCondition = "t_details LIKE 'ARCHIVED:%'";
    }

    $whereClauses = [$statusCondition];
    $params = [];
    $paramTypes = '';

    if ($searchTerm !== '') {
        $whereClauses[] = "(t_ref LIKE ? OR t_aname LIKE ? OR t_subject LIKE ? OR t_status LIKE ? OR t_details LIKE ?)";
        $searchWildcard = "%$searchTerm%";
        $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
        $paramTypes .= 'sssss';
    }

    if ($accountFilter !== '') {
        $whereClauses[] = "t_aname = ?";
        $params[] = $accountFilter;
        $paramTypes .= 's';
    }

    if ($typeFilter !== '') {
        $whereClauses[] = "t_status = ?";
        $params[] = $typeFilter;
        $paramTypes .= 's';
    }

    $whereClause = implode(' AND ', $whereClauses);

    $countSql = "SELECT COUNT(*) as total FROM tbl_ticket WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare count statement.']);
        exit();
    }
    if ($paramTypes) {
        $countStmt->bind_param($paramTypes, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    $totalPages = max(1, ceil($totalRecords / $limit));

    $sql = "SELECT t_ref, t_aname, t_subject, t_status, t_details, technician_username 
            FROM tbl_ticket 
            WHERE $whereClause 
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare query statement.']);
        exit();
    }
    if ($paramTypes) {
        $stmt->bind_param($paramTypes . 'ii', ...array_merge($params, [$offset, $limit]));
    } else {
        $stmt->bind_param("ii", $offset, $limit);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $statusClass = 'status-' . strtolower($row['t_status']);
            $technicianDisplay = $row['technician_username'] ? htmlspecialchars($row['technician_username'], ENT_QUOTES, 'UTF-8') : 'Unassigned';  // Display technician or 'Unassigned'
            $output .= "<tr> 
                <td style='vertical-align: middle; text-align: left;'>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td class='$statusClass' style='cursor: pointer;' onclick=\"showCloseTicketModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_status'], ENT_QUOTES, 'UTF-8') . "')\">" . ucfirst(strtolower($row['t_status'])) . "</td>
                <td>" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . $technicianDisplay . "</td>  <!-- Added technician_username display -->
                <td class='action-buttons'>";
            if ($tab === 'active') {
                $output .= "
                    <a class='view-btn' href='#' onclick=\"showViewModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                    <a class='edit-btn' href='#' onclick=\"showEditTicketModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_details'], ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                    <a class='archive-btn' href='#' onclick=\"showArchiveModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>";
            } else {
                $output .= "
                    <a class='view-btn' href='#' onclick=\"showViewModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                    <a class='restore-btn' href='#' onclick=\"showRestoreModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                    <a class='delete-btn' href='#' onclick=\"showDeleteModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>";
            }
            $output .= "</td></tr>";
        }
    } else {
        $output = "<tr><td colspan='7' class='no-tickets'>No tickets found.</td></tr>";  // Updated colspan to 7 for new column
    }
    $stmt->close();

$paginationHTML = '';
if ($totalPages > 1) {
$paginationHTML .= $page > 1 
    ? '<a href="#" class="pagination-link" onclick="searchTickets(' . ($page - 1) . '); return false;"><i class="fas fa-chevron-left"></i></a>' 
    : '<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>';
$paginationHTML .= '<span class="current-page">Page ' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . ' of ' . htmlspecialchars($totalPages, ENT_QUOTES, 'UTF-8') . '</span>';
$paginationHTML .= $page < $totalPages 
    ? '<a href="#" class="pagination-link" onclick="searchTickets(' . ($page + 1) . '); return false;"><i class="fas fa-chevron-right"></i></a>' 
    : '<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>';
} else {
$paginationHTML = '<span class="current-page">Page 1 of 1</span>';
}
    echo json_encode([
        'success' => true,
        'html' => $output,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'tab' => $tab,
        'searchTerm' => $searchTerm,
        'accountFilter' => $accountFilter,
        'typeFilter' => $typeFilter,
        'paginationHTML' => $paginationHTML
    ]);
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pageActive = isset($_GET['page_active']) ? (int)$_GET['page_active'] : 1;
    $pageArchived = isset($_GET['page_archived']) ? (int)$_GET['page_archived'] : 1;
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'active';
if (isset($_POST['add_ticket'])) {
    $accountname = trim($_POST['account_name'] ?? '');
    $subject = trim($_POST['ticket_subject'] ?? '');
    $issuedetails = trim($_POST['ticket_details'] ?? '');
    $ticketstatus = 'Open'; // Default status
    $t_ref = trim($_POST['ticket_ref'] ?? '');
    $errors = [];

    // Validate account name
    if (empty($accountname)) {
        $errors['account_name'] = "Account Name is required.";
    } elseif (empty($customers)) {
        $errors['account_name'] = "No customers available in the database.";
    } else {
        $validCustomer = false;
        foreach ($customers as $customer) {
            if (strcasecmp($customer['full_name'], $accountname) === 0) {
                $validCustomer = true;
                break;
            }
        }
        if (!$validCustomer) {
            $errors['account_name'] = "Selected Account Name is not in the customer database.";
        }
    }

    // Validate subject
    if (empty($subject)) {
        $errors['ticket_subject'] = "Subject is required.";
    } elseif (!preg_match("/^[a-zA-Z\s-]+$/", $subject)) {
        $errors['ticket_subject'] = "Subject should only contain letters, spaces, or hyphens.";
    }

    // Validate ticket details
    if (empty($issuedetails)) {
        $errors['ticket_details'] = "Ticket Details are required.";
    }

    // Validate ticket reference
    if (empty($t_ref)) {
        $errors['ticket_ref'] = "Ticket Reference is required.";
    } elseif (!preg_match("/^ref#-\d{2}-\d{2}-\d{4}-\d{6}$/", $t_ref)) {
        $errors['ticket_ref'] = "Invalid Ticket Reference format. Expected format: ref#-MM-DD-YYYY-XXXXXX";
    } else {
        // Check for duplicate ticket reference
        $checkSql = "SELECT COUNT(*) FROM tbl_ticket WHERE t_ref = ?";
        $checkStmt = $conn->prepare($checkSql);
        if (!$checkStmt) {
            error_log("Prepare failed for ticket reference check: " . $conn->error);
            $errors['ticket_ref'] = "Database error while checking ticket reference.";
        } else {
            $checkStmt->bind_param("s", $t_ref);
            $checkStmt->execute();
            $checkStmt->bind_result($count);
            $checkStmt->fetch();
            $checkStmt->close();
            if ($count > 0) {
                $errors['ticket_ref'] = "Ticket Reference already exists.";
            }
        }
    }

    // If no errors, proceed with insertion
    if (empty($errors)) {
        $sql = "INSERT INTO tbl_ticket (t_ref, t_aname, t_details, t_subject, t_status, technician_username) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for ticket insert: " . $conn->error);
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => ['general' => 'Database error: Unable to prepare statement.']]);
                exit();
            }
            $_SESSION['error'] = "Database error: Unable to prepare statement.";
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }

        $technician_username = '';  // CHANGED: Set to empty string (unassigned initially)
        $stmt->bind_param("ssssss", $t_ref, $accountname, $issuedetails, $subject, $ticketstatus, $technician_username);
        if ($stmt->execute()) {
            // Log the action
            $logDescription = "Created ticket #$t_ref for customer $accountname";
            $logType = "Staff $firstName $lastName";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            if ($stmtLog) {
                $stmtLog->bind_param("ss", $logDescription, $logType);
                $stmtLog->execute();
                $stmtLog->close();
            } else {
                error_log("Prepare failed for log insert: " . $conn->error);
            }

            // Fetch updated counts
            $totalActiveQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details NOT LIKE 'ARCHIVED:%'";
            $totalActiveResult = $conn->query($totalActiveQuery);
            $totalActive = $totalActiveResult ? $totalActiveResult->fetch_assoc()['total'] : 0;

            $totalArchivedQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details LIKE 'ARCHIVED:%'";
            $totalArchivedResult = $conn->query($totalArchivedQuery);
            $totalArchived = $totalArchivedResult ? $totalArchivedResult->fetch_assoc()['total'] : 0;

            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Ticket has been registered successfully.',
                    'totalActive' => $totalActive,
                    'totalArchived' => $totalArchived
                ]);
                exit();
            }
            $_SESSION['error'] = "Ticket has been registered successfully.";
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        } else {
            error_log("Execution failed for ticket insert: " . $stmt->error);
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => ['general' => 'Database error: ' . $stmt->error]]);
                exit();
            }
            $_SESSION['error'] = "Database error: " . $stmt->error;
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
        $stmt->close();
    } else {
        if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }
        $_SESSION['error'] = implode(", ", array_values($errors));
        header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
        exit();
    }
} elseif (isset($_POST['edit_ticket'])) {
        $t_ref = trim($_POST['t_ref'] ?? '');
        $accountName = trim($_POST['account_name'] ?? '');
        $subject = trim($_POST['ticket_subject'] ?? '');
        $ticketStatus = trim($_POST['ticket_status'] ?? '');
        $ticketDetails = trim($_POST['ticket_details'] ?? '');
        $errors = [];

        $sql = "SELECT t_ref, t_aname, t_subject, t_status, t_details FROM tbl_ticket WHERE t_ref = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for ticket fetch: " . $conn->error);
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => ['general' => 'Database error: Unable to prepare statement.']]);
                exit();
            }
            $_SESSION['error'] = "Database error: Unable to prepare statement.";
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
        $stmt->bind_param("s", $t_ref);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $ticket = $result->fetch_assoc();
        } else {
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => ['general' => 'Ticket not found.']]);
                exit();
            }
            $_SESSION['error'] = "Ticket not found.";
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
        $stmt->close();

        if (empty($accountName)) {
            $errors['account_name'] = "Account Name is required.";
        } elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $accountName)) {
            $errors['account_name'] = "Account Name can only contain letters, spaces, hyphens, or apostrophes.";
        } elseif (empty($customers)) {
            $errors['account_name'] = "No customers available in the database.";
        } else {
            $validCustomer = false;
            foreach ($customers as $customer) {
                if (strcasecmp($customer['full_name'], $accountName) === 0) {
                    $validCustomer = true;
                    $accountName = $customer['full_name'];
                    break;
                }
            }
            if (!$validCustomer) {
                $errors['account_name'] = "Account Name does not exist in customer database.";
            }
        }

        if (empty($subject)) {
            $errors['ticket_subject'] = "Subject is required.";
        } elseif (!preg_match("/^[a-zA-Z\s-]+$/", $subject)) {
            $errors['ticket_subject'] = "Subject should not contain numbers or special characters.";
        }

        if (empty($ticketDetails)) {
            $errors['ticket_details'] = "Ticket Details are required.";
        }

        if (empty($ticketStatus)) {
            $errors['ticket_status'] = "Ticket Status is required.";
        } elseif (!in_array($ticketStatus, ['Open', 'Closed'])) {
            $errors['ticket_status'] = "Invalid ticket status.";
        }

        if (empty($errors)) {
            $logParts = [];
            if ($accountName !== $ticket['t_aname']) {
                $logParts[] = "account name";
            }
            if ($subject !== $ticket['t_subject']) {
                $logParts[] = "subject";
            }
            if ($ticketDetails !== $ticket['t_details']) {
                $logParts[] = "ticket details";
            }
            if ($ticketStatus !== $ticket['t_status']) {
                $logParts[] = "status";
            }

            $sqlUpdate = "UPDATE tbl_ticket SET t_aname = ?, t_subject = ?, t_status = ?, t_details = ? WHERE t_ref = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            if (!$stmtUpdate) {
                error_log("Prepare failed for ticket update: " . $conn->error);
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'errors' => ['general' => 'Database error: Unable to prepare statement.']]);
                    exit();
                }
                $_SESSION['error'] = "Database error: Unable to prepare statement.";
                header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
                exit();
            }
            $stmtUpdate->bind_param("sssss", $accountName, $subject, $ticketStatus, $ticketDetails, $t_ref);

            if ($stmtUpdate->execute()) {
                if (!empty($logParts)) {
                    $logDescription = "Staff $firstName $lastName edited ticket $t_ref " . implode(" and ", $logParts);
                    $logType = "Staff $firstName $lastName";
                    $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
                    $stmtLog = $conn->prepare($sqlLog);
                    if ($stmtLog) {
                        $stmtLog->bind_param("ss", $logDescription, $logType);
                        $stmtLog->execute();
                        $stmtLog->close();
                    }
                }
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Ticket updated successfully!']);
                    exit();
                }
                $_SESSION['message'] = "Ticket updated successfully!";
                header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
                exit();
            } else {
                error_log("Execution failed for ticket update: " . $stmtUpdate->error);
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'errors' => ['general' => 'Database error: ' . $stmtUpdate->error]]);
                    exit();
                }
                $_SESSION['error'] = "Database error: " . $stmtUpdate->error;
                header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
                exit();
            }
            $stmtUpdate->close();
        } else {
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit();
            }
            $_SESSION['error'] = implode(", ", $errors);
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
    } elseif (isset($_POST['archive_ticket'])) {
        $t_ref = trim($_POST['t_ref'] ?? '');
        $sqlCheck = "SELECT t_details FROM tbl_ticket WHERE t_ref = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        if (!$stmtCheck) {
            error_log("Prepare failed for ticket check: " . $conn->error);
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare statement.']);
                exit();
            }
            $_SESSION['error'] = "Database error: Unable to prepare statement.";
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
        $stmtCheck->bind_param("s", $t_ref);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        if ($resultCheck->num_rows == 0) {
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Ticket not found.']);
                exit();
            }
            $_SESSION['error'] = "Ticket not found.";
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
        $row = $resultCheck->fetch_assoc();
        if (strpos($row['t_details'], 'ARCHIVED:') === 0) {
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Ticket is already archived.']);
                exit();
            }
            $_SESSION['error'] = "Ticket is already archived.";
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
        $stmtCheck->close();

        $sql = "UPDATE tbl_ticket SET t_details = CONCAT('ARCHIVED:', t_details) WHERE t_ref = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for ticket archive: " . $conn->error);
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare statement.']);
                exit();
            }
            $_SESSION['error'] = "Database error: Unable to prepare statement.";
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
        $stmt->bind_param("s", $t_ref);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $logDescription = "Staff $firstName $lastName archived ticket $t_ref";
                $logType = "Staff $firstName $lastName";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
                $stmtLog = $conn->prepare($sqlLog);
                if ($stmtLog) {
                    $stmtLog->bind_param("ss", $logDescription, $logType);
                    $stmtLog->execute();
                    $stmtLog->close();
                }

                // Fetch updated counts
                $totalActiveQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details NOT LIKE 'ARCHIVED:%'";
                $totalActiveResult = $conn->query($totalActiveQuery);
                $totalActive = $totalActiveResult ? $totalActiveResult->fetch_assoc()['total'] : 0;

                $totalArchivedQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details LIKE 'ARCHIVED:%'";
                $totalArchivedResult = $conn->query($totalArchivedQuery);
                $totalArchived = $totalArchivedResult ? $totalArchivedResult->fetch_assoc()['total'] : 0;

                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Ticket archived successfully!',
                        'totalActive' => $totalActive,
                        'totalArchived' => $totalArchived
                    ]);
                    exit();
                }
                $_SESSION['message'] = "Ticket archived successfully!";
                header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
                exit();
            } else {
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'No changes made to the ticket.']);
                    exit();
                }
                $_SESSION['error'] = "No changes made to the ticket.";
                header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
                exit();
            }
        } else {
            error_log("Execution failed for ticket archive: " . $stmt->error);
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
                exit();
            }
            $_SESSION['error'] = "Database error: " . $stmt->error;
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
        $stmt->close();
    } elseif (isset($_POST['restore_ticket'])) {
        $t_ref = trim($_POST['t_ref'] ?? '');
        $sql = "UPDATE tbl_ticket SET t_details = REGEXP_REPLACE(t_details, '^ARCHIVED:', '') WHERE t_ref = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for ticket restore: " . $conn->error);
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare statement.']);
                exit();
            }
            $_SESSION['error'] = "Database error: Unable to prepare statement.";
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
        $stmt->bind_param("s", $t_ref);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $logDescription = "Staff $firstName $lastName unarchived ticket $t_ref";
                $logType = "Staff $firstName $lastName";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
                $stmtLog = $conn->prepare($sqlLog);
                if ($stmtLog) {
                    $stmtLog->bind_param("ss", $logDescription, $logType);
                    $stmtLog->execute();
                    $stmtLog->close();
                }

                // Fetch updated counts
                $totalActiveQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details NOT LIKE 'ARCHIVED:%'";
                $totalActiveResult = $conn->query($totalActiveQuery);
                $totalActive = $totalActiveResult ? $totalActiveResult->fetch_assoc()['total'] : 0;

                $totalArchivedQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details LIKE 'ARCHIVED:%'";
                $totalArchivedResult = $conn->query($totalArchivedQuery);
                $totalArchived = $totalArchivedResult ? $totalArchivedResult->fetch_assoc()['total'] : 0;

                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Ticket restored successfully!',
                        'totalActive' => $totalActive,
                        'totalArchived' => $totalArchived
                    ]);
                    exit();
                }
                $_SESSION['message'] = "Ticket restored successfully!";
                header("Location: staffD.php?tab=active&page_active=$pageActive&page_archived=$pageArchived");
                exit();
            } else {
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Ticket not found or already restored.']);
                    exit();
                }
                $_SESSION['error'] = "Ticket not found or already restored.";
                header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
                exit();
            }
        } else {
            error_log("Execution failed for ticket restore: " . $stmt->error);
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
                exit();
            }
            $_SESSION['error'] = "Database error: " . $stmt->error;
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
        $stmt->close();
    } elseif (isset($_POST['delete_ticket'])) {
        $t_ref = trim($_POST['t_ref'] ?? '');
        $sql = "DELETE FROM tbl_ticket WHERE t_ref = ? AND t_details LIKE 'ARCHIVED:%'";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for ticket delete: " . $conn->error);
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare statement.']);
                exit();
            }
            $_SESSION['error'] = "Database error: Unable to prepare statement.";
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
        $stmt->bind_param("s", $t_ref);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $logDescription = "Staff $firstName $lastName deleted ticket $t_ref";
                $logType = "Staff $firstName $lastName";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
                $stmtLog = $conn->prepare($sqlLog);
                if ($stmtLog) {
                    $stmtLog->bind_param("ss", $logDescription, $logType);
                    $stmtLog->execute();
                    $stmtLog->close();
                }

                // Fetch updated counts
                $totalActiveQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details NOT LIKE 'ARCHIVED:%'";
                $totalActiveResult = $conn->query($totalActiveQuery);
                $totalActive = $totalActiveResult ? $totalActiveResult->fetch_assoc()['total'] : 0;

                $totalArchivedQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details LIKE 'ARCHIVED:%'";
                $totalArchivedResult = $conn->query($totalArchivedQuery);
                $totalArchived = $totalArchivedResult ? $totalArchivedResult->fetch_assoc()['total'] : 0;

                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Ticket deleted successfully!',
                        'totalActive' => $totalActive,
                        'totalArchived' => $totalArchived
                    ]);
                    exit();
                }
                $_SESSION['message'] = "Ticket deleted successfully!";
                header("Location: staffD.php?tab=archived&page_active=$pageActive&page_archived=$pageArchived");
                exit();
            } else {
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Ticket not found or not archived.']);
                    exit();
                }
                $_SESSION['error'] = "Ticket not found or not archived.";
                header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
                exit();
            }
        } else {
            error_log("Execution failed for ticket delete: " . $stmt->error);
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
                exit();
            }
            $_SESSION['error'] = "Database error: " . $stmt->error;
            header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
            exit();
        }
        $stmt->close();
   } elseif (isset($_POST['close_ticket'])) {
    $t_ref = trim($_POST['t_ref'] ?? '');
    $sqlCheck = "SELECT t_status, technician_username FROM tbl_ticket WHERE t_ref = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    if (!$stmtCheck) {
        error_log("Prepare failed for ticket status check: " . $conn->error);
        if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare statement.']);
            exit();
        }
        $_SESSION['error'] = "Database error: Unable to prepare statement.";
        header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
        exit();
    }
    $stmtCheck->bind_param("s", $t_ref);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    if ($resultCheck->num_rows == 0) {
        if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Ticket not found.']);
            exit();
        }
        $_SESSION['error'] = "Ticket not found.";
        header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
        exit();
    }
    $row = $resultCheck->fetch_assoc();
    if ($row['t_status'] === 'Closed') {
        if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Ticket is already closed.']);
            exit();
        }
        $_SESSION['error'] = "Ticket is already closed.";
        header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
        exit();
    }
    $stmtCheck->close();

    // Check if ticket is assigned to a technician
    $technician_username = $row['technician_username'];
    
    $conn->begin_transaction();
    try {
        // If ticket is assigned to a technician, move to closed table and unassign
        if (!empty($technician_username)) {
            // Fetch ticket data for closing
            $sqlFetch = "SELECT t_aname, t_subject, t_details FROM tbl_ticket WHERE t_ref = ?";
            $stmtFetch = $conn->prepare($sqlFetch);
            if (!$stmtFetch) {
                throw new Exception("Prepare failed for fetch query: " . $conn->error);
            }
            $stmtFetch->bind_param("s", $t_ref);
            $stmtFetch->execute();
            $resultFetch = $stmtFetch->get_result();
            if ($resultFetch->num_rows === 0) {
                throw new Exception("Ticket not found for fetching: t_ref=$t_ref");
            }
            $ticketData = $resultFetch->fetch_assoc();
            $stmtFetch->close();

            // Insert into closed tickets table
            $closeDate = date('Y-m-d H:i:s');
            $sqlInsert = "INSERT INTO tbl_close_regular (t_ref, t_aname, t_subject, t_details, t_status, te_technician, te_date) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmtInsert = $conn->prepare($sqlInsert);
            if (!$stmtInsert) {
                throw new Exception("Prepare failed for insert query: " . $conn->error);
            }
            $status = 'closed';
            $stmtInsert->bind_param("sssssss", $t_ref, $ticketData['t_aname'], $ticketData['t_subject'], $ticketData['t_details'], $status, $technician_username, $closeDate);
            if (!$stmtInsert->execute()) {
                throw new Exception("Insert failed for tbl_close_regular: " . $stmtInsert->error);
            }
            $stmtInsert->close();

            // Update main ticket table - set status to Closed AND unassign technician
            $sql = "UPDATE tbl_ticket SET t_status = 'Closed', technician_username = NULL WHERE t_ref = ?";
        } else {
            // Ticket not assigned to technician, just close it
            $sql = "UPDATE tbl_ticket SET t_status = 'Closed' WHERE t_ref = ?";
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed for ticket close: " . $conn->error);
        }
        $stmt->bind_param("s", $t_ref);
        if (!$stmt->execute()) {
            throw new Exception("Execution failed for ticket close: " . $stmt->error);
        }

        $conn->commit();
        
        // Log the action
        $logDescription = "Staff $firstName $lastName closed ticket $t_ref" . 
                         (!empty($technician_username) ? " (was assigned to $technician_username)" : "");
        $logType = "Staff $firstName $lastName";
        $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
        $stmtLog = $conn->prepare($sqlLog);
        if ($stmtLog) {
            $stmtLog->bind_param("ss", $logDescription, $logType);
            $stmtLog->execute();
            $stmtLog->close();
        }

        // Fetch updated counts
        $totalActiveQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details NOT LIKE 'ARCHIVED:%'";
        $totalActiveResult = $conn->query($totalActiveQuery);
        $totalActive = $totalActiveResult ? $totalActiveResult->fetch_assoc()['total'] : 0;

        $totalArchivedQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details LIKE 'ARCHIVED:%'";
        $totalArchivedResult = $conn->query($totalArchivedQuery);
        $totalArchived = $totalArchivedResult ? $totalArchivedResult->fetch_assoc()['total'] : 0;

        if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Ticket closed successfully!' . 
                           (!empty($technician_username) ? ' Ticket unassigned from technician.' : ''),
                'totalActive' => $totalActive,
                'totalArchived' => $totalArchived
            ]);
            exit();
        }
        $_SESSION['message'] = "Ticket closed successfully!" . 
                             (!empty($technician_username) ? ' Ticket unassigned from technician.' : '');
        header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Close ticket failed: " . $e->getMessage());
        if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to close ticket: ' . $e->getMessage()]);
            exit();
        }
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
        exit();
    }
}

    if (!(isset($_POST['ajax']) && $_POST['ajax'] == 'true')) {
        header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
        exit();
    }
}

// Pagination setup
$limit = 10;
$pageActive = isset($_GET['page_active']) ? max(1, (int)$_GET['page_active']) : 1;
$offsetActive = ($pageActive - 1) * $limit;
$totalActiveQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details NOT LIKE 'ARCHIVED:%'";
$totalActiveResult = $conn->query($totalActiveQuery);
$totalActiveRow = $totalActiveResult ? $totalActiveResult->fetch_assoc() : ['total' => 0];
$totalActive = $totalActiveRow['total'];
$totalActivePages = max(1, ceil($totalActive / $limit));

$pageArchived = isset($_GET['page_archived']) ? max(1, (int)$_GET['page_archived']) : 1;
$offsetArchived = ($pageArchived - 1) * $limit;
$totalArchivedQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details LIKE 'ARCHIVED:%'";
$totalArchivedResult = $conn->query($totalArchivedQuery);
$totalArchivedRow = $totalArchivedResult ? $totalArchivedResult->fetch_assoc() : ['total' => 0];
$totalArchived = $totalArchivedRow['total'];
$totalArchivedPages = max(1, ceil($totalArchived / $limit));

// Fetch active tickets
$sqlActive = "SELECT t_ref, t_aname, t_subject, t_status, t_details, technician_username 
              FROM tbl_ticket 
              WHERE t_details NOT LIKE 'ARCHIVED:%'
              LIMIT ?, ?";
$stmtActive = $conn->prepare($sqlActive);
if ($stmtActive) {
    $stmtActive->bind_param("ii", $offsetActive, $limit);
    $stmtActive->execute();
    $resultActive = $stmtActive->get_result();
    $stmtActive->close();
} else {
    error_log("Prepare failed for active tickets: " . $conn->error);
    $resultActive = false;
}

// Fetch archived tickets
$sqlArchived = "SELECT t_ref, t_aname, t_subject, t_status, t_details, technician_username 
                FROM tbl_ticket 
                WHERE t_details LIKE 'ARCHIVED:%' 
                LIMIT ?, ?";
$stmtArchived = $conn->prepare($sqlArchived);
if ($stmtArchived) {
    $stmtArchived->bind_param("ii", $offsetArchived, $limit);
    $stmtArchived->execute();
    $resultArchived = $stmtArchived->get_result();
    $stmtArchived->close();
} else {
    error_log("Prepare failed for archived tickets: " . $conn->error);
    $resultArchived = false;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | Ticket Reports</title>
    <link rel="stylesheet" href="staffssD.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">   
    <style>
        /* Header Controls and Tab Buttons */
        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            position: relative;
        }

        .tab-buttons {
            display: flex;
            gap: 10px;
        }

        .tab-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
            position: relative;
            background-color: #f5f5f5;
            color: #333;
            margin-top: 30%;
        }

        .tab-btn.active {
            background-color: #4CAF50;
            color: white;
        }

        /* Tab Badge */
        .tab-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff5722;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            transition: background-color 0.3s ease;
        }

        .tab-content {
            margin-top: 15px;
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .filter-btn {
            background: transparent !important;
            border: none;
            cursor: pointer;
            font-size: 15px;
            color: var(--light, #f5f8fc);
            margin-left: 5px;
            vertical-align: middle;
            padding: 0;
            outline: none;
        }

        .filter-btn:hover {
            color: var(--primary-dark, hsl(211, 45.70%, 84.10%));
            background: transparent !important;
        }

        th .filter-btn {
            background: transparent !important;
        }

        .autocomplete-container {
            position: relative;
            width: 100%;
        }

        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            z-index: 1000;
            display: none;
        }

        .autocomplete-suggestion {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 14px;
            color: #333;
        }

        .autocomplete-suggestion:hover {
            background: #f0f0f0;
        }

        .modal-form input[type="text"], 
        .modal-form input[type="email"],
        .modal-form textarea {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .modal-form input[type="email"] {
            background-color: #f9f9f9;
        }

        .modal-form #account_name {
            box-shadow: none;
        }

        .modal-form textarea {
            height: 100px;
            resize: vertical;
        }

        .error {
            color: red;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }

        /* Center the no tickets message */
        .no-tickets {
            text-align: center !important;
            padding: 20px !important;
            font-style: italic;
            color: #666;
        }

        /* Center the ticket reference column */
        table td:first-child {
            text-align: center !important;
            vertical-align: middle;
        }

        /* Center all table cells */
        table td {
            text-align: center;
            vertical-align: middle;
        }

        /* Keep the action buttons aligned as they were */
        .action-buttons {
            text-align: center;
            white-space: nowrap;
        }

       .pagination {
display: flex;
justify-content: center;
align-items: center;
margin-top: auto;
padding-top: 20px;
}

        .pagination-link {
              display: inline-flex;
align-items: center;
justify-content: center;
width: 40px;
height: 40px;
border-radius: 50%;
background: white;
color: var(--primary);
text-decoration: none;
margin: 0 5px;
box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
transition: all 0.3s;
        }

        .pagination-link:hover {
background: var(--primary);
color: white;
transform: translateY(-2px);
box-shadow: 0 6px 15px rgba(108, 92, 231, 0.3);
        }

       .pagination-link.disabled {
color: #ccc;
pointer-events: none;
box-shadow: none;
}

.current-page {
margin: 0 15px;
font-weight: 500;
color: var(--dark);
}

    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
         <ul>
           <li><a href="staffD.php" class="active"><i class="fas fa-ticket-alt icon"></i> <span>Regular Tickets</span></a></li>
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
            <h1>Ticket Reports</h1>
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
            <div class="username">
                Welcome to TIXNET, <?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>!
                <i class="fas fa-user-shield admin-icon"></i>
            </div>

            <div class="header-controls">
                <div class="tab-buttons">
                    <button class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'archived') ? '' : 'active'; ?>" onclick="showTab('active')">Active (<?php echo $totalActive; ?>)</button>
                    <button class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'archived') ? 'active' : ''; ?>" onclick="showTab('archived')">Archive 
                        <?php if ($totalArchived > 0): ?>
                            <span class="tab-badge"><?php echo $totalArchived; ?></span>
                        <?php endif; ?>
                    </button>
                </div>
                <button class="add-user-btn" onclick="showAddTicketModal()">
                    <i class="fas fa-ticket-alt"></i> Add Ticket
                </button>
            </div>

                <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search tickets..." onkeyup="debouncedSearchTickets()">
                <span class="search-icon"><i class="fas fa-search"></i></span>
                </div>

    <div id="active-tickets" class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'archived') ? '' : 'active'; ?>">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Ticket Ref</th>
                        <th>Account Name <button class="filter-btn" onclick="showAccountFilterModal('active')" title="Filter by Account Name"><i class='bx bx-filter'></i></button></th>
                        <th>Subject</th>
                        <th>Status <button class="filter-btn" onclick="showTypeFilterModal('active')" title="Filter by Type"><i class='bx bx-filter'></i></button></th>
                        <th>Ticket Details</th>
                        <th>Technician</th> <!-- Added column for technician_username -->
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="active-table-body">
                    <?php
                    if ($resultActive && $resultActive->num_rows > 0) {
                        while ($row = $resultActive->fetch_assoc()) {
                            $statusClass = 'status-' . strtolower($row['t_status']);
                            $technicianDisplay = $row['technician_username'] ? htmlspecialchars($row['technician_username'], ENT_QUOTES, 'UTF-8') : 'Unassigned';
                            echo "<tr> 
                                    <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td> 
                                    <td>" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                    <td>" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "</td> 
                                    <td class='$statusClass' style='cursor: pointer;' onclick=\"showCloseTicketModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_status'], ENT_QUOTES, 'UTF-8') . "')\">" . ucfirst(strtolower($row['t_status'])) . "</td>
                                    <td>" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . $technicianDisplay . "</td> <!-- Display technician -->
                                    <td class='action-buttons'>
                                        <a class='view-btn' href='#' onclick=\"showViewModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                        <a class='edit-btn' href='#' onclick=\"showEditTicketModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_details'], ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                                        <a class='archive-btn' href='#' onclick=\"showArchiveModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>
                                    </td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='no-tickets'>No active tickets found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
                <div class="pagination" id="active-pagination">
                    <?php if ($pageActive > 1): ?>
                        <a href="?tab=active&page_active=<?php echo $pageActive - 1; ?>&page_archived=<?php echo $pageArchived; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <span class="current-page">Page <?php echo $pageActive; ?> of <?php echo $totalActivePages; ?></span>
                    <?php if ($pageActive < $totalActivePages): ?>
                        <a href="?tab=active&page_active=<?php echo $pageActive + 1; ?>&page_archived=<?php echo $pageArchived; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>

        
    <div id="archived-tickets" class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'archived') ? 'active' : ''; ?>">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Ticket Ref</th>
                        <th>Account Name <button class="filter-btn" onclick="showAccountFilterModal('archived')" title="Filter by Account Name"><i class='bx bx-filter'></i></button></th>
                        <th>Subject</th>
                        <th>Status <button class="filter-btn" onclick="showTypeFilterModal('archived')" title="Filter by Type"><i class='bx bx-filter'></i></button></th>
                        <th>Ticket Details</th>
                        <th>Technician</th> <!-- Added column for technician_username -->
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="archived-table-body">
                    <?php
                    if ($resultArchived && $resultArchived->num_rows > 0) {
                        while ($row = $resultArchived->fetch_assoc()) {
                            $technicianDisplay = $row['technician_username'] ? htmlspecialchars($row['technician_username'], ENT_QUOTES, 'UTF-8') : 'Unassigned';
                            echo "<tr> 
                                    <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td> 
                                    <td>" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                    <td>" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "</td> 
                                    <td class='status-" . strtolower($row['t_status']) . "'>" . ucfirst(strtolower($row['t_status'])) . "</td>
                                    <td>" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . $technicianDisplay . "</td> <!-- Display technician -->
                                    <td class='action-buttons'>
                                        <a class='view-btn' href='#' onclick=\"showViewModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                        <a class='restore-btn' href='#' onclick=\"showRestoreModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                                        <a class='delete-btn' href='#' onclick=\"showDeleteModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                                    </td>
                                </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='no-tickets'>No archived tickets found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

                <div class="pagination" id="archived-pagination">
                    <?php if ($pageArchived > 1): ?>
                        <a href="?tab=archived&page_active=<?php echo $pageActive; ?>&page_archived=<?php echo $pageArchived - 1; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <span class="current-page">Page <?php echo $pageArchived; ?> of <?php echo $totalArchivedPages; ?></span>
                    <?php if ($pageArchived < $totalArchivedPages): ?>
                        <a href="?tab=archived&page_active=<?php echo $pageActive; ?>&page_archived=<?php echo $pageArchived + 1; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Ticket Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ticket Details</h2>
        </div>
        <div id="viewContent" class="view-details"></div>
        <div class="modal-footer">
            <button class="modal-btn cancel" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- Archive Ticket Modal -->
<div id="archiveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Archive Ticket</h2>
        </div>
        <p>Are you sure you want to archive ticket <span id="archiveTicketRef"></span> for <span id="archiveTicketName"></span>?</p>
        <form method="POST" id="archiveForm">
            <input type="hidden" name="t_ref" id="archiveTicketId">
            <input type="hidden" name="archive_ticket" value="1">
            <input type="hidden" name="ajax" value="true">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('archiveModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Archive</button>
            </div>
        </form>
    </div>
</div>

<!-- Restore Ticket Modal -->
<div id="restoreModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Restore Ticket</h2>        </div>
        <p>Are you sure you want to restore ticket <span id="restoreTicketRef"></span> for <span id="restoreTicketName"></span>?</p>
        <form method="POST" id="restoreForm">
            <input type="hidden" name="t_ref" id="restoreTicketId">
            <input type="hidden" name="restore_ticket" value="1">
            <input type="hidden" name="ajax" value="true">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('restoreModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Restore</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Ticket Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Delete Ticket</h2>
        </div>
        <p>Are you sure you want to permanently delete ticket <span id="deleteTicketRef"></span> for <span id="deleteTicketName"></span>? This action cannot be undone.</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="t_ref" id="deleteTicketId">
            <input type="hidden" name="delete_ticket" value="1">
            <input type="hidden" name="ajax" value="true">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Delete</button>
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
        <input type="hidden" name="t_ref" id="editTicketRef">
        <input type="hidden" name="ajax" value="true">

        <label for="edit_account_name">Account Name</label>
        <?php if (empty($customers)): ?>
            <p class="error">No customers available. Please add customers in the Customers section.</p>
            <input type="text" name="account_name" id="edit_account_name" disabled placeholder="No customers available">
        <?php else: ?>
            <div class="autocomplete-container">
                <input type="text" name="account_name" id="edit_account_name" placeholder="Search for a customer..." required>
                <div class="autocomplete-suggestions" id="edit_account_name_suggestions"></div>
            </div>
        <?php endif; ?>
        <span class="error" id="edit_account_name_error"></span>

        <!-- REMOVED CUSTOMER EMAIL FIELD -->

        <label for="edit_ticket_subject">Subject</label>
        <input type="text" name="ticket_subject" id="edit_ticket_subject" required>
        <span class="error" id="edit_ticket_subject_error"></span>

        <label for="edit_ticket_details">Ticket Details</label>
        <textarea name="ticket_details" id="edit_ticket_details" required></textarea>
        <span class="error" id="edit_ticket_details_error"></span>

        <label for="edit_ticket_status">Ticket Status</label>
        <select name="ticket_status" id="edit_ticket_status" required>
            <option value="Open">Open</option>
            <option value="Closed">Closed</option>
        </select>
        <span class="error" id="edit_ticket_status_error"></span>

        <div class="modal-footer">
            <button type="button" class="modal-btn cancel" onclick="closeModal('editTicketModal')">Cancel</button>
            <button type="submit" class="modal-btn confirm" <?php echo empty($customers) ? 'disabled' : ''; ?>>Save Changes</button>
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
        <p>Are you sure you want to close ticket <span id="closeTicketRef"></span> for <span id="closeTicketName"></span>?</p>
        <form method="POST" id="closeTicketForm">
            <input type="hidden" name="t_ref" id="closeTicketId">
            <input type="hidden" name="close_ticket" value="1">
            <input type="hidden" name="ajax" value="true">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('closeTicketModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Close Ticket</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Ticket Modal -->
<div id="addTicketModal" class="modal">
<div class="modal-content">
    <div class="modal-header">
        <h2>Add New Ticket</h2>
    </div>
    <form method="POST" id="addTicketForm" class="modal-form">
        <input type="hidden" name="add_ticket" value="1">
        <input type="hidden" name="ajax" value="true">

        <label for="ticket_ref">Ticket Reference</label>
        <input type="text" name="ticket_ref" id="ticket_ref" readonly>
        <span class="error" id="ticket_ref_error"><?php echo htmlspecialchars($t_refErr, ENT_QUOTES, 'UTF-8'); ?></span>

        <label for="account_name">Account Name</label>
        <?php if (empty($customers)): ?>
            <p class="error">No customers available. Please add customers in the Customers section.</p>
            <input type="text" name="account_name" id="account_name" disabled placeholder="No customers available">
        <?php else: ?>
            <div class="autocomplete-container">
                <input type="text" name="account_name" id="account_name" placeholder="Search for a customer..." value="<?php echo htmlspecialchars($accountname, ENT_QUOTES, 'UTF-8'); ?>" required>
                <div class="autocomplete-suggestions" id="account_name_suggestions"></div>
            </div>
        <?php endif; ?>
        <span class="error" id="account_name_error"><?php echo htmlspecialchars($accountnameErr, ENT_QUOTES, 'UTF-8'); ?></span>

        <!-- REMOVED CUSTOMER EMAIL FIELD -->

        <label for="ticket_subject">Subject</label>
        <input type="text" name="ticket_subject" id="ticket_subject" value="<?php echo htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'); ?>" required>
        <span class="error" id="ticket_subject_error"><?php echo htmlspecialchars($subjectErr, ENT_QUOTES, 'UTF-8'); ?></span>

        <label for="ticket_details">Ticket Details</label>
        <textarea name="ticket_details" id="ticket_details" required><?php echo htmlspecialchars($issuedetails, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <span class="error" id="ticket_details_error"><?php echo htmlspecialchars($issuedetailsErr, ENT_QUOTES, 'UTF-8'); ?></span>

        <label for="ticket_status">Ticket Status</label>
        <input type="text" name="ticket_status" id="ticket_status" value="Open" readonly>
        <span class="error" id="ticket_status_error"><?php echo htmlspecialchars($ticketstatusErr, ENT_QUOTES, 'UTF-8'); ?></span>

        <div class="modal-footer">
            <button type="button" class="modal-btn cancel" onclick="closeModal('addTicketModal')">Cancel</button>
            <button type="submit" class="modal-btn confirm" <?php echo empty($customers) ? 'disabled' : ''; ?>>Add Ticket</button>
        </div>
    </form>
</div>
</div>


<!-- Account Filter Modal -->
<!-- Account Filter Modal -->
<div id="accountFilterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Filter by Account Name</h2>
        </div>
        <form id="accountFilterForm" class="modal-form">
            <label for="account_filter">Select Account Name</label>
            <select name="account_filter" id="account_filter">
                <option value="">All Accounts</option>
                <?php foreach ($accountNames as $accountName): ?>
                    <option value="<?php echo htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="tab" id="accountFilterTab" value="<?php echo htmlspecialchars($tab, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('accountFilterModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Type Filter Modal -->
<div id="typeFilterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Filter by Type</h2>
        </div>
        <form id="typeFilterForm" class="modal-form">
            <label for="type_filter">Select Type</label>
            <select name="type_filter" id="type_filter">
                <option value="">All Types</option>
                <option value="Open">Open</option>
                <option value="Closed">Closed</option>
            </select>
            <input type="hidden" name="tab" id="typeFilterTab">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('typeFilterModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Apply Filter</button>
            </div>
        </form>
    </div>
</div>



<script>
let currentTab = '<?php echo isset($_GET['tab']) && $_GET['tab'] === 'archived' ? 'archived' : 'active'; ?>';
let currentPage = {
    active: <?php echo $pageActive; ?>,
    archived: <?php echo $pageArchived; ?>
};
let searchTerm = '';
let accountFilter = '';
let typeFilter = '';

function generateTicketRef() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const year = now.getFullYear();
    const randomNum = String(Math.floor(Math.random() * 1000000)).padStart(6, '0');
    return `ref#-${month}-${day}-${year}-${randomNum}`;
}

function showTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(tab + '-tickets').classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.tab-btn[onclick="showTab('${tab}')"]`).classList.add('active');
    searchTickets(1);
    updateURL();
}

function updateURL() {
    const url = new URL(window.location);
    url.searchParams.set('tab', currentTab);
    url.searchParams.set('page_active', currentPage.active);
    url.searchParams.set('page_archived', currentPage.archived);
    window.history.replaceState({}, '', url);
}

function showModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    if (modalId === 'addTicketModal' || modalId === 'editTicketModal') {
        document.getElementById(modalId === 'addTicketModal' ? 'addTicketForm' : 'editTicketForm').reset();
        document.querySelectorAll(`#${modalId} .error`).forEach(error => error.textContent = '');
        if (modalId === 'addTicketModal') {
            document.getElementById('ticket_ref').value = generateTicketRef();
        }
    }
}

function showViewModal(ref, name, subject, status, details) {
    const content = `
        <p><strong>Ticket Reference:</strong> ${ref}</p>
        <p><strong>Account Name:</strong> ${name}</p>
        <p><strong>Subject:</strong> ${subject}</p>
        <p><strong>Status:</strong> ${status}</p>
        <p><strong>Details:</strong> ${details}</p>
    `;
    document.getElementById('viewContent').innerHTML = content;
    showModal('viewModal');
}

function showAddTicketModal() {
    document.getElementById('ticket_ref').value = generateTicketRef();
    showModal('addTicketModal');
}

function showEditTicketModal(ref, name, subject, status, details) {
    document.getElementById('editTicketRef').value = ref;
    document.getElementById('edit_account_name').value = name;
    document.getElementById('edit_ticket_subject').value = subject;
    document.getElementById('edit_ticket_status').value = status;
    document.getElementById('edit_ticket_details').value = details;
    showModal('editTicketModal');
}

function showArchiveModal(ref, name) {
    document.getElementById('archiveTicketRef').textContent = ref;
    document.getElementById('archiveTicketName').textContent = name;
    document.getElementById('archiveTicketId').value = ref;
    showModal('archiveModal');
}

function showRestoreModal(ref, name) {
    document.getElementById('restoreTicketRef').textContent = ref;
    document.getElementById('restoreTicketName').textContent = name;
    document.getElementById('restoreTicketId').value = ref;
    showModal('restoreModal');
}

function showDeleteModal(ref, name) {
    document.getElementById('deleteTicketRef').textContent = ref;
    document.getElementById('deleteTicketName').textContent = name;
    document.getElementById('deleteTicketId').value = ref;
    showModal('deleteModal');
}

function showCloseTicketModal(ref, name, status) {
    if (status.toLowerCase() === 'closed') {
        showAlert('This ticket is already closed.', 'error');
        return;
    }
    document.getElementById('closeTicketRef').textContent = ref;
    document.getElementById('closeTicketName').textContent = name;
    document.getElementById('closeTicketId').value = ref;
    showModal('closeTicketModal');
}

function showAccountFilterModal(tab) {
    currentTab = tab;
    document.getElementById('accountFilterTab').value = tab;
    showModal('accountFilterModal');
}

function showTypeFilterModal(tab) {
    currentTab = tab;
    document.getElementById('typeFilterTab').value = tab;
    showModal('typeFilterModal');
}

function applyAccountFilter() {
    accountFilter = document.getElementById('account_filter').value;
    closeModal('accountFilterModal');
    searchTickets(1);
}

function applyTypeFilter() {
    typeFilter = document.getElementById('type_filter').value;
    closeModal('typeFilterModal');
    searchTickets(1);
}

function updateTabCounts(active, archived) {
    document.querySelector(`.tab-btn[onclick="showTab('active')"]`).innerHTML = `Active (${active})`;
    const archiveBtn = document.querySelector(`.tab-btn[onclick="showTab('archived')"]`);
    archiveBtn.innerHTML = 'Archive';
    if (archived > 0) {
        archiveBtn.innerHTML += ` <span class="tab-badge">${archived}</span>`;
    }
}

function showAlert(message, type) {
    const alertContainer = document.querySelector('.alert-container');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    alertContainer.innerHTML = '';
    alertContainer.appendChild(alert);
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, 3000);
}

function searchTickets(page) {
    currentPage[currentTab] = page;
    searchTerm = document.getElementById('searchInput').value;
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `staffD.php?action=search&tab=${currentTab}&search=${encodeURIComponent(searchTerm)}&account_filter=${encodeURIComponent(accountFilter)}&type_filter=${encodeURIComponent(typeFilter)}&search_page=${page}`, true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    const tableBody = document.getElementById(`${currentTab}-table-body`);
                    tableBody.innerHTML = response.html;
                    const pagination = document.getElementById(`${currentTab}-pagination`);
                    pagination.innerHTML = response.paginationHTML;
                    updateURL();
                    // Reattach event listeners to pagination links
                    const paginationLinks = document.querySelectorAll(`#${currentTab}-pagination .pagination-link:not(.disabled)`);
                    paginationLinks.forEach(link => {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            const onclickAttr = this.getAttribute('onclick');
                            if (onclickAttr) {
                                const match = onclickAttr.match(/searchTickets\((\d+)\)/);
                                if (match) {
                                    const targetPage = parseInt(match[1]);
                                    searchTickets(targetPage);
                                }
                            }
                        });
                    });
                } else {
                    showAlert('Error: ' + (response.error || 'Failed to fetch tickets.'), 'error');
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                showAlert('Error processing response.', 'error');
            }
        } else {
            showAlert('Error fetching tickets.', 'error');
        }
    };
    xhr.send();
}



function handleFormSubmissionSuccess(response, modalId) {
    closeModal(modalId);
    showAlert(response.message, 'success');
    updateTabCounts(response.totalActive, response.totalArchived);
    
    // Refresh the current view with the same page
    searchTickets(currentPage[currentTab]);
}

function debouncedSearchTickets() {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => searchTickets(1), 500);
}

document.getElementById('addTicketForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'staffD.php', true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    handleFormSubmissionSuccess(response, 'addTicketModal');
                } else {
                    Object.keys(response.errors).forEach(field => {
                        document.getElementById(`${field}_error`).textContent = response.errors[field];
                    });
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                showAlert('Error processing response.', 'error');
            }
        } else {
            showAlert('Error submitting form.', 'error');
        }
    };
    xhr.send(formData);
});

document.getElementById('editTicketForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'staffD.php', true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // Fetch updated counts after edit
                    fetchCounts();
                    handleFormSubmissionSuccess(response, 'editTicketModal');
                } else {
                    Object.keys(response.errors).forEach(field => {
                        document.getElementById(`edit_${field}_error`).textContent = response.errors[field];
                    });
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                showAlert('Error processing response.', 'error');
            }
        } else {
            showAlert('Error submitting form.', 'error');
        }
    };
    xhr.send(formData);
});
document.getElementById('archiveForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'staffD.php', true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    handleFormSubmissionSuccess(response, 'archiveModal');
                } else {
                    showAlert('Error: ' + response.error, 'error');
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                showAlert('Error processing response.', 'error');
            }
        } else {
            showAlert('Error archiving ticket.', 'error');
        }
    };
    xhr.send(formData);
});

document.getElementById('restoreForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'staffD.php', true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    handleFormSubmissionSuccess(response, 'restoreModal');
                } else {
                    showAlert('Error: ' + response.error, 'error');
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                showAlert('Error processing response.', 'error');
            }
        } else {
            showAlert('Error restoring ticket.', 'error');
        }
    };
    xhr.send(formData);
});

document.getElementById('deleteForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'staffD.php', true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    handleFormSubmissionSuccess(response, 'deleteModal');
                } else {
                    showAlert('Error: ' + response.error, 'error');
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                showAlert('Error processing response.', 'error');
            }
        } else {
            showAlert('Error deleting ticket.', 'error');
        }
    };
    xhr.send(formData);
});

document.getElementById('closeTicketForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'staffD.php', true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    handleFormSubmissionSuccess(response, 'closeTicketModal');
                } else {
                    showAlert('Error: ' + response.error, 'error');
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                showAlert('Error processing response.', 'error');
            }
        } else {
            showAlert('Error closing ticket.', 'error');
        }
    };
    xhr.send(formData);
});

const customers = <?php echo json_encode(array_column($customers, 'full_name')); ?>;

function setupAutocomplete(inputId, suggestionsId) {
    const input = document.getElementById(inputId);
    const suggestionsContainer = document.getElementById(suggestionsId);

    input.addEventListener('input', function () {
        const query = this.value.toLowerCase();
        suggestionsContainer.innerHTML = '';
        if (query.length === 0) {
            suggestionsContainer.style.display = 'none';
            return;
        }

        const matches = customers.filter(customer => customer.toLowerCase().includes(query));
        if (matches.length === 0) {
            suggestionsContainer.style.display = 'none';
            return;
        }

        matches.forEach(match => {
            const div = document.createElement('div');
            div.classList.add('autocomplete-suggestion');
            div.textContent = match;
            div.addEventListener('click', () => {
                input.value = match;
                suggestionsContainer.style.display = 'none';
                if (inputId === 'account_name') { // Only fetch email for add modal
                    fetchCustomerEmail(match, 'addTicketForm');
                }
            });
            suggestionsContainer.appendChild(div);
        });
        suggestionsContainer.style.display = 'block';
    });

    document.addEventListener('click', function (e) {
        if (!suggestionsContainer.contains(e.target) && e.target !== input) {
            suggestionsContainer.style.display = 'none';
        }
    });
}


setupAutocomplete('account_name', 'account_name_suggestions');
setupAutocomplete('edit_account_name', 'edit_account_name_suggestions');

document.getElementById('accountFilterForm').addEventListener('submit', function (e) {
    e.preventDefault();
    applyAccountFilter();
});

document.getElementById('typeFilterForm').addEventListener('submit', function (e) {
    e.preventDefault();
    applyTypeFilter();
});
function fetchCounts() {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'staffD.php?action=get_counts', true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                console.log('Counts response:', response); // Add this for debugging
                if (response.success) {
                    updateTabCounts(response.active, response.archived);
                }
            } catch (e) {
                console.error('Error parsing counts response:', e);
            }
        }
    };
    xhr.send();
}

document.addEventListener('DOMContentLoaded', function() {
    attachPaginationListeners();
    fetchCounts();
});
</script>
</body>
</html>