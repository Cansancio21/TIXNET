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
        $stmtLog->bind_param("ss", $logDescription, $logType);
        $stmtLog->execute();
        $stmtLog->close();
        $_SESSION['login_logged'] = true;
    }
} else {
    error_log("User not found for username: {$_SESSION['username']}");
    $_SESSION['error'] = "User not found.";
    header("Location: index.php");
    exit();
}
$stmt->close();

// Fetch customers for filter and search
$sqlCustomers = "SELECT c_fname, c_lname FROM tbl_customer ORDER BY c_fname, c_lname";
$resultCustomers = $conn->query($sqlCustomers);
$customers = [];
if ($resultCustomers->num_rows > 0) {
    while ($row = $resultCustomers->fetch_assoc()) {
        $customers[] = [
            'full_name' => $row['c_fname'] . ' ' . $row['c_lname'],
            'first_name' => $row['c_fname'],
            'last_name' => $row['c_lname']
        ];
    }
} else {
    error_log("No customers found in tbl_customer");
    $accountnameErr = "No customers available in the database. Please add customers first.";
}

// Initialize variables for add ticket validation
$accountname = $subject = $issuedetails = $issuetype = $ticketstatus = $t_ref = "";
$accountnameErr = $subjectErr = $issuedetailsErr = $issuetypeError = $ticketstatusErr = $t_refErr = "";
$hasError = false;

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
        $stmtLog->bind_param("ss", $logDescription, $logType);
        $stmtLog->execute();
        $stmtLog->close();
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
        $stmtLog->bind_param("ss", $logDescription, $logType);
        $stmtLog->execute();
        $stmtLog->close();
        }
    }
}

// Handle AJAX search request with filters
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['tab'])) {
    header('Content-Type: application/json');
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $accountFilter = isset($_GET['account_filter']) ? trim($_GET['account_filter']) : '';
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

    $whereClause = implode(' AND ', $whereClauses);

    $countSql = "SELECT COUNT(*) as total FROM tbl_ticket WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    if ($paramTypes) {
        $countStmt->bind_param($paramTypes, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    $totalPages = ceil($totalRecords / $limit);

    // Fetch paginated search results
    $sql = "SELECT t_ref, t_aname, t_subject, t_status, t_details 
            FROM tbl_ticket 
            WHERE $whereClause 
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
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
            $output .= "<tr> 
                <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td class='$statusClass'>" . ucfirst(strtolower($row['t_status'])) . "</td>
                <td>" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "</td>
                <td class='action-buttons'>";
            $output .= "<a class='view-btn' href='#' onclick=\"showViewModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>";
            if ($tab === 'active') {
                $output .= "<a class='edit-btn' onclick=\"showEditTicketModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "', '{$row['t_status']}', '" . htmlspecialchars($row['t_details'], ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                            <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>";
            } else {
                $output .= "<a class='restore-btn' onclick=\"showRestoreModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                            <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>";
            }
            $output .= "</td></tr>";
        }
    } else {
        $output = "<tr><td colspan='6' style='text-align: center;'>No tickets found.</td></tr>";
    }
    $stmt->close();

    $response = [
        'html' => $output,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'tab' => $tab,
        'searchTerm' => $searchTerm,
        'accountFilter' => $accountFilter
    ];
    echo json_encode($response);
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pageActive = isset($_GET['page_active']) ? (int)$_GET['page_active'] : 1;
    $pageArchived = isset($_GET['page_archived']) ? (int)$_GET['page_archived'] : 1;
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'active';

    if (isset($_POST['add_ticket'])) {
        $accountname = trim($_POST['account_name']);
        $subject = trim($_POST['ticket_subject']);
        $issuedetails = trim($_POST['ticket_details']);
        $ticketstatus = 'Open';
        $t_ref = trim($_POST['ticket_ref']);

        if (empty($accountname)) {
            $accountnameErr = "Account Name is required.";
            $hasError = true;
        } elseif (empty($customers)) {
            $accountnameErr = "No customers available in the database.";
            $hasError = true;
        } else {
            $validCustomer = false;
            foreach ($customers as $customer) {
                if ($customer['full_name'] === $accountname) {
                    $validCustomer = true;
                    break;
                }
            }
            if (!$validCustomer) {
                $accountnameErr = "Selected Account Name is invalid.";
                $hasError = true;
            }
        }

        if (empty($subject)) {
            $subjectErr = "Subject is required.";
            $hasError = true;
        } elseif (!preg_match("/^[a-zA-Z\s-]+$/", $subject)) {
            $subjectErr = "Subject should not contain numbers or special characters.";
            $hasError = true;
        }

        if (empty($issuedetails)) {
            $issuedetailsErr = "Ticket Details are required.";
            $hasError = true;
        }

        if (empty($t_ref)) {
            $t_refErr = "Ticket Reference is required.";
            $hasError = true;
        } elseif (!preg_match("/^ref#-\d{2}-\d{2}-\d{4}-\d{6}$/", $t_ref)) {
            $t_refErr = "Invalid Ticket Reference format.";
            $hasError = true;
        } else {
            $checkSql = "SELECT COUNT(*) FROM tbl_ticket WHERE t_ref = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("s", $t_ref);
            $checkStmt->execute();
            $checkStmt->bind_result($count);
            $checkStmt->fetch();
            $checkStmt->close();
            if ($count > 0) {
                $t_refErr = "Ticket Reference already exists.";
                $hasError = true;
            }
        }

        if (!$hasError) {
            $sql = "INSERT INTO tbl_ticket (t_ref, t_aname, t_details, t_subject, t_status) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'errors' => ['general' => 'Prepare failed: ' . $conn->error]]);
                    exit();
                }
                die("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssss", $t_ref, $accountname, $issuedetails, $subject, $ticketstatus);
            if ($stmt->execute()) {
                $logDescription = "Created ticket #$t_ref for customer $accountname";
                $logType = "Staff $firstName $lastName";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
                $stmtLog = $conn->prepare($sqlLog);
                $stmtLog->bind_param("ss", $logDescription, $logType);
                $stmtLog->execute();
                $stmtLog->close();
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Ticket has been registered successfully.']);
                    exit();
                }
                $_SESSION['message'] = "Ticket has been registered successfully.";
                header("Location: staffD.php?tab=$tab&page_active=$pageActive&page_archived=$pageArchived");
                exit();
            } else {
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'errors' => ['general' => 'Execution failed: ' . $stmt->error]]);
                    exit();
                }
                die("Execution failed: " . $stmt->error);
            }
            $stmt->close();
        } else {
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'errors' => [
                        'account_name' => $accountnameErr,
                        'ticket_subject' => $subjectErr,
                        'ticket_details' => $issuedetailsErr,
                        'ticket_ref' => $t_refErr
                    ]
                ]);
                exit();
            }
        }
    } elseif (isset($_POST['edit_ticket'])) {
        $t_ref = trim($_POST['t_ref']);
        $accountName = trim($_POST['account_name']);
        $subject = trim($_POST['ticket_subject']);
        $ticketStatus = trim($_POST['ticket_status']);
        $ticketDetails = trim($_POST['ticket_details']);
        $errors = [];

        // Fetch existing ticket
        $sql = "SELECT t_ref, t_aname, t_subject, t_status, t_details FROM tbl_ticket WHERE t_ref = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => ['general' => 'Database error: ' . $conn->error]]);
                exit();
            }
            error_log("Prepare failed: " . $conn->error);
            $_SESSION['error'] = "Database error.";
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

        // Validate account name
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

        // Validate subject
        if (empty($subject)) {
            $errors['ticket_subject'] = "Subject is required.";
        } elseif (!preg_match("/^[a-zA-Z\s-]+$/", $subject)) {
            $errors['ticket_subject'] = "Subject should not contain numbers or special characters.";
        }

        // Validate ticket details
        if (empty($ticketDetails)) {
            $errors['ticket_details'] = "Ticket Details are required.";
        }

        // Proceed if no errors
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
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'errors' => ['general' => 'Prepare failed: ' . $conn->error]]);
                    exit();
                }
                error_log("Prepare failed: " . $conn->error);
                $_SESSION['error'] = "Database error.";
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
                    $stmtLog->bind_param("ss", $logDescription, $logType);
                    $stmtLog->execute();
                    $stmtLog->close();
                }
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Ticket updated successfully!']);
                    exit();
                }
                $_SESSION['message'] = "Ticket updated successfully!";
            } else {
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'errors' => ['general' => 'Error updating ticket: ' . $stmtUpdate->error]]);
                    exit();
                }
                $_SESSION['error'] = "Error updating ticket: " . $stmtUpdate->error;
            }
            $stmtUpdate->close();
        } else {
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit();
            }
            $_SESSION['error'] = implode(", ", $errors);
        }
    } elseif (isset($_POST['archive_ticket'])) {
        $t_ref = $_POST['t_ref'];
        $sql = "UPDATE tbl_ticket SET t_details = CONCAT('ARCHIVED:', t_details) WHERE t_ref=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $t_ref);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Ticket archived successfully!";
            $logDescription = "Staff $firstName $lastName archived ticket $t_ref";
            $logType = "Staff $firstName $lastName";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            $stmtLog->bind_param("ss", $logDescription, $logType);
            $stmtLog->execute();
            $stmtLog->close();
        } else {
            $_SESSION['error'] = "Error archiving ticket: " . $stmt->error;
            error_log("Error archiving ticket: " . $stmt->error);
        }
        $stmt->close();
    } elseif (isset($_POST['restore_ticket'])) {
        $t_ref = $_POST['t_ref'];
        $sql = "UPDATE tbl_ticket SET t_details = REGEXP_REPLACE(t_details, '^ARCHIVED:', '') WHERE t_ref=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $t_ref);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Ticket restored successfully!";
            $logDescription = "Staff $firstName $lastName unarchived ticket $t_ref";
            $logType = "Staff $firstName $lastName";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            $stmtLog->bind_param("ss", $logDescription, $logType);
            $stmtLog->execute();
            $stmtLog->close();
        } else {
            $_SESSION['error'] = "Error restoring ticket: " . $stmt->error;
            error_log("Error restoring ticket: " . $stmt->error);
        }
        $stmt->close();
        $tab = 'active';
    } elseif (isset($_POST['delete_ticket'])) {
        $t_ref = $_POST['t_ref'];
        $sql = "DELETE FROM tbl_ticket WHERE t_ref=? AND t_details LIKE 'ARCHIVED:%'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $t_ref);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['message'] = "Ticket deleted successfully!";
                $logDescription = "Staff $firstName $lastName deleted ticket $t_ref";
                $logType = "Staff $firstName $lastName";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
                $stmtLog = $conn->prepare($sqlLog);
                $stmtLog->bind_param("ss", $logDescription, $logType);
                $stmtLog->execute();
                $stmtLog->close();
            } else {
                $_SESSION['error'] = "Ticket not found or not archived.";
                error_log("Ticket not found or not archived for t_ref: $t_ref");
            }
        } else {
            $_SESSION['error'] = "Error deleting ticket: " . $stmt->error;
            error_log("Error deleting ticket: " . $stmt->error);
        }
        $stmt->close();
        $tab = 'archived';
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
$totalActiveRow = $totalActiveResult->fetch_assoc();
$totalActive = $totalActiveRow['total'];
$totalActivePages = ceil($totalActive / $limit);

$pageArchived = isset($_GET['page_archived']) ? max(1, (int)$_GET['page_archived']) : 1;
$offsetArchived = ($pageArchived - 1) * $limit;
$totalArchivedQuery = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details LIKE 'ARCHIVED:%'";
$totalArchivedResult = $conn->query($totalArchivedQuery);
$totalArchivedRow = $totalArchivedResult->fetch_assoc();
$totalArchived = $totalArchivedRow['total'];
$totalArchivedPages = ceil($totalArchived / $limit);

// Fetch active tickets
$sqlActive = "SELECT t_ref, t_aname, t_subject, t_status, t_details 
              FROM tbl_ticket WHERE t_details NOT LIKE 'ARCHIVED:%' LIMIT ?, ?";
$stmtActive = $conn->prepare($sqlActive);
$stmtActive->bind_param("ii", $offsetActive, $limit);
$stmtActive->execute();
$resultActive = $stmtActive->get_result();
$stmtActive->close();

// Fetch archived tickets
$sqlArchived = "SELECT t_ref, t_aname, t_subject, t_status, t_details 
                FROM tbl_ticket WHERE t_details LIKE 'ARCHIVED:%' LIMIT ?, ?";
$stmtArchived = $conn->prepare($sqlArchived);
$stmtArchived->bind_param("ii", $offsetArchived, $limit);
$stmtArchived->execute();
$resultArchived = $stmtArchived->get_result();
$stmtArchived->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | Ticket Reports</title>
    <link rel="stylesheet" href="staffsDD.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <style>
        /* Tab Navigation Styles */
        .tab-navigation {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
            position: relative;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            position: relative;
            transition: all 0.3s ease;
            margin-right: 5px;
        }
        
        .tab-btn:hover {
            color: var(--primary);
        }
        
        .tab-btn.active {
            color: var(--primary);
            font-weight: 600;
        }
        
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: var(--primary);
        }
        
        .tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .tab-badge {
            background-color: var(--danger);
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 12px;
            margin-left: 5px;
        }
        
        .add-user-btn {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(108, 92, 231, 0.3);
            border: none;
            outline: none;
            font-size: 14px;
        }
        
        .add-user-btn:hover {
            background-color: #7494ec;
            transform: translateY(-50%) scale(1.05);
            box-shadow: 0 6px 20px rgba(108, 92, 231, 0.4);
        }
        
        .add-user-btn i {
            margin-right: 8px;
        }
        
        .table-container {
            overflow-x: auto;
            margin-bottom: 20px;
        }
        
        /* Filter button styles */
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
        
        /* Autocomplete styles */
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
        
        /* Modal form styles */
        .modal-form input[type="text"], 
        .modal-form textarea {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
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
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="staffD.php" class="active"><img src="image/ticket.png" alt="Regular Tickets" class="icon" /> <span>Regular Tickets</span></a></li>
            <li><a href="assetsT.php"><img src="image/assets.png" alt="Assets" class="icon" /> <span>Assets</span></a></li>
            <li><a href="AllCustomersT.php"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers Ticket</span></a></li>
            <li><a href="customersT.php"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers</span></a></li>
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
            <h1>Ticket Reports</h1>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search tickets..." onkeyup="debouncedSearchTickets()">
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
            <div class="username">
                Welcome to TIXNET, <?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>!
                <i class="fas fa-user-shield admin-icon"></i>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button class="tab-btn active" onclick="showTab('active')">
                    Active Tickets
                    <?php if ($totalActive > 0): ?>
                        <span class="tab-badge"><?php echo $totalActive; ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-btn" onclick="showTab('archived')">
                    Archived Tickets
                    <?php if ($totalArchived > 0): ?>
                        <span class="tab-badge"><?php echo $totalArchived; ?></span>
                    <?php endif; ?>
                </button>
                <button class="add-user-btn" onclick="showAddTicketModal()">
                    <i class="fas fa-ticket-alt"></i> Add New Ticket
                </button>
            </div>

            <!-- Active Tickets Tab -->
            <div id="active-tickets" class="tab-content active">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket Ref</th>
                                <th>Account Name <button class="filter-btn" onclick="showAccountFilterModal('active')" title="Filter by Account Name"><i class='bx bx-filter'></i></button></th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Ticket Details</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="active-table-body">
                            <?php
                            if ($resultActive->num_rows > 0) {
                                while ($row = $resultActive->fetch_assoc()) {
                                    $statusClass = 'status-' . strtolower($row['t_status']);
                                    echo "<tr> 
                                            <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td> 
                                            <td>" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                            <td>" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "</td> 
                                            <td class='$statusClass'>" . ucfirst(strtolower($row['t_status'])) . "</td>
                                            <td>" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "</td>
                                            <td class='action-buttons'>
                                                <a class='view-btn' href='#' onclick=\"showViewModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                                <a class='edit-btn' onclick=\"showEditTicketModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "', '{$row['t_status']}', '" . htmlspecialchars($row['t_details'], ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                                                <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>
                                            </td>
                                          </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align: center;'>No active tickets found.</td></tr>";
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

            <!-- Archived Tickets Tab -->
            <div id="archived-tickets" class="tab-content">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket Ref</th>
                                <th>Account Name <button class="filter-btn" onclick="showAccountFilterModal('archived')" title="Filter by Account Name"><i class='bx bx-filter'></i></button></th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Ticket Details</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="archived-table-body">
                            <?php
                            if ($resultArchived->num_rows > 0) {
                                while ($row = $resultArchived->fetch_assoc()) {
                                    echo "<tr> 
                                            <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td> 
                                            <td>" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                            <td>" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "</td> 
                                            <td class='status-" . strtolower($row['t_status']) . "'>" . ucfirst(strtolower($row['t_status'])) . "</td>
                                            <td>" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "</td>
                                            <td class='action-buttons'>
                                                <a class='view-btn' href='#' onclick=\"showViewModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['t_details']), ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                                <a class='restore-btn' onclick=\"showRestoreModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                                                <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                                            </td>
                                          </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align: center;'>No archived tickets found.</td></tr>";
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
            <h2>Restore Ticket</h2>
        </div>
        <p>Are you sure you want to restore ticket <span id="restoreTicketRef"></span> for <span id="restoreTicketName"></span>?</p>
        <form method="POST" id="restoreForm">
            <input type="hidden" name="t_ref" id="restoreTicketId">
            <input type="hidden" name="restore_ticket" value="1">
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
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Delete</button>
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

<!-- Edit Ticket Modal -->
<div id="editTicketModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Ticket</h2>
        </div>
        <form method="POST" id="editTicketForm" class="modal-form">
            <input type="hidden" name="edit_ticket" value="1">
            <input type="hidden" name="ajax" value="true">
            <input type="hidden" name="t_ref" id="edit_t_ref">

            <label for="edit_ticket_ref">Ticket Reference</label>
            <input type="text" name="ticket_ref" id="edit_ticket_ref" readonly>
            <span class="error" id="edit_ticket_ref_error"></span>

            <label for="edit_account_name">Account Name</label>
            <input type="text" name="account_name" id="edit_account_name" required>
            <span class="error" id="edit_account_name_error"></span>

            <label for="edit_ticket_subject">Subject</label>
            <input type="text" name="ticket_subject" id="edit_ticket_subject" required>
            <span class="error" id="edit_ticket_subject_error"></span>

            <label for="edit_ticket_status">Ticket Status</label>
            <input type="text" name="ticket_status" id="edit_ticket_status" required>
            <span class="error" id="edit_ticket_status_error"></span>

            <label for="edit_ticket_details">Ticket Details</label>
            <textarea name="ticket_details" id="edit_ticket_details" required></textarea>
            <span class="error" id="edit_ticket_details_error"></span>

            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('editTicketModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Update Ticket</button>
            </div>
        </form>
    </div>
</div>

<!-- Account Filter Modal -->
<div id="accountFilterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Filter by Account Name</h2>
        </div>
        <form id="accountFilterForm" class="modal-form">
            <input type="hidden" name="tab" id="accountFilterTab">
            <label for="account_filter">Select Account Name</label>
            <select name="account_filter" id="account_filter">
                <option value="">All Accounts</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('accountFilterModal')">Cancel</button>
                <button type="button" class="modal-btn confirm" onclick="applyAccountFilter()">Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<div id="modalBackground" class="modal-background"></div>

<script>
let currentSearchPage = 1;
let defaultPageActive = <?php echo json_encode($pageActive); ?>;
let defaultPageArchived = <?php echo json_encode($pageArchived); ?>;
let currentAccountFilter = '';
const userType = '<?php echo $userType; ?>';
const customers = <?php echo json_encode(array_column($customers, 'full_name')); ?>;

document.addEventListener('DOMContentLoaded', () => {
    console.log('Page loaded, initializing staffD.php, userType=' + userType);
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || 'active';
    showTab(tab);

    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('alert-hidden');
            setTimeout(() => alert.remove(), 500);
        }, 2000);
    });

    const searchInput = document.getElementById('searchInput');
    if (searchInput.value || currentAccountFilter) {
        console.log('Search input or account filter present, triggering searchTickets');
        searchTickets(tab === 'active' ? defaultPageActive : defaultPageArchived, tab);
    }

    // Initialize autocomplete for account name
    const accountInput = document.getElementById('account_name');
    const suggestionsContainer = document.getElementById('account_name_suggestions');
    if (accountInput && suggestionsContainer) {
        accountInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            suggestionsContainer.innerHTML = '';
            if (query.length === 0) {
                suggestionsContainer.style.display = 'none';
                return;
            }

            const matches = customers.filter(customer => 
                customer.toLowerCase().includes(query)
            );

            if (matches.length > 0) {
                matches.forEach(match => {
                    const suggestion = document.createElement('div');
                    suggestion.className = 'autocomplete-suggestion';
                    suggestion.textContent = match;
                    suggestion.addEventListener('click', () => {
                        accountInput.value = match;
                        suggestionsContainer.innerHTML = '';
                        suggestionsContainer.style.display = 'none';
                    });
                    suggestionsContainer.appendChild(suggestion);
                });
                suggestionsContainer.style.display = 'block';
            } else {
                suggestionsContainer.style.display = 'none';
            }
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!accountInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.style.display = 'none';
            }
        });

        // Hide suggestions when pressing Enter
        accountInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                suggestionsContainer.style.display = 'none';
            }
        });
    }

    document.getElementById('addTicketForm').addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('Submitting Add Ticket Form');
        const form = this;
        const formData = new FormData(form);

        document.querySelectorAll('#addTicketForm .error').forEach(span => span.textContent = '');

        fetch('staffD.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                closeModal('addTicketModal');
                const alertContainer = document.querySelector('.alert-container');
                const successAlert = document.createElement('div');
                successAlert.className = 'alert alert-success';
                successAlert.textContent = data.message;
                alertContainer.appendChild(successAlert);
                setTimeout(() => {
                    successAlert.classList.add('alert-hidden');
                    setTimeout(() => successAlert.remove(), 500);
                }, 2000);
                searchTickets(defaultPageActive, 'active');
            } else {
                for (const [field, error] of Object.entries(data.errors || {})) {
                    if (error) {
                        const errorSpan = document.getElementById(`${field}_error`);
                        if (errorSpan) {
                            errorSpan.textContent = error;
                        }
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error in addTicketForm submission:', error);
            const alertContainer = document.querySelector('.alert-container');
            const alert = document.createElement('div');
            alert.className = 'alert alert-error';
            alert.textContent = 'An error occurred while adding the ticket.';
            alertContainer.appendChild(alert);
            setTimeout(() => {
                alert.classList.add('alert-hidden');
                setTimeout(() => alert.remove(), 500);
            }, 2000);
        });
    });

    document.getElementById('editTicketForm').addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('Submitting Edit Ticket Form');
        const formData = new FormData(this);

        document.querySelectorAll('#editTicketForm .error').forEach(span => span.textContent = '');

        fetch('staffD.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                closeModal('editTicketModal');
                const alertContainer = document.querySelector('.alert-container');
                const successAlert = document.createElement('div');
                successAlert.className = 'alert alert-success';
                successAlert.textContent = data.message;
                alertContainer.appendChild(successAlert);
                setTimeout(() => {
                    successAlert.classList.add('alert-hidden');
                    setTimeout(() => successAlert.remove(), 500);
                }, 2000);
                searchTickets(defaultPageActive, 'active');
            } else {
                for (const [field, error] of Object.entries(data.errors || {})) {
                    if (error) {
                        const errorSpan = document.getElementById(`edit_${field}_error`);
                        if (errorSpan) {
                            errorSpan.textContent = error;
                        }
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error in editTicketForm submission:', error);
            const alertContainer = document.querySelector('.alert-container');
            const alert = document.createElement('div');
            alert.className = 'alert alert-error';
            alert.textContent = 'An error occurred while updating the ticket.';
            alertContainer.appendChild(alert);
            setTimeout(() => {
                alert.classList.add('alert-hidden');
                setTimeout(() => alert.remove(), 500);
            }, 2000);
        });
    });
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

function searchTickets(page = 1, tab = null) {
    const searchTerm = document.getElementById('searchInput').value;
    const activeSection = document.querySelector('.active-tickets');
    const currentTab = tab || (activeSection.classList.contains('active') ? 'active' : 'archived');
    const tbody = currentTab === 'active' ? document.getElementById('active-table-body') : document.getElementById('archived-table-body');
    const paginationContainer = currentTab === 'active' ? document.getElementById('active-pagination') : document.getElementById('archived-pagination');
    const defaultPage = currentTab === 'active' ? defaultPageActive : defaultPageArchived;

    currentSearchPage = page;

    console.log(`Searching tickets: tab=${currentTab}, page=${page}, searchTerm=${searchTerm}, accountFilter=${currentAccountFilter}`);

    fetch(`staffD.php?action=search&tab=${currentTab}&search=${encodeURIComponent(searchTerm)}&search_page=${page}&account_filter=${encodeURIComponent(currentAccountFilter)}`)
        .then(response => response.json())
        .then(response => {
            tbody.innerHTML = response.html;
            updatePagination(response.currentPage, response.totalPages, response.searchTerm, currentTab);
            if (currentTab === 'active') {
                defaultPageActive = response.currentPage;
            } else {
                defaultPageArchived = response.currentPage;
            }
        })
        .catch(error => {
            console.error('Error in searchTickets:', error);
            tbody.innerHTML = '<tr><td colspan="6">Error loading tickets.</td></tr>';
        });
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

function showAccountFilterModal(tab) {
    document.getElementById('accountFilterTab').value = tab;
    document.getElementById('account_filter').value = currentAccountFilter;
    document.getElementById('accountFilterModal').style.display = 'block';
}

function applyAccountFilter() {
    currentAccountFilter = document.getElementById('account_filter').value;
    const tab = document.getElementById('accountFilterTab').value;
    closeModal('accountFilterModal');
    searchTickets(1, tab);
}

function showTab(tab) {
    console.log('Switching to tab:', tab);
    const activeSection = document.getElementById('active-tickets');
    const archivedSection = document.getElementById('archived-tickets');
    const activeBtn = document.querySelector('.tab-btn[onclick="showTab(\'active\')"]');
    const archivedBtn = document.querySelector('.tab-btn[onclick="showTab(\'archived\')"]');

    if (tab === 'active') {
        activeSection.classList.add('active');
        archivedSection.classList.remove('active');
        activeBtn.classList.add('active');
        archivedBtn.classList.remove('active');
        currentSearchPage = defaultPageActive;
    } else if (tab === 'archived') {
        activeSection.classList.remove('active');
        archivedSection.classList.add('active');
        activeBtn.classList.remove('active');
        archivedBtn.classList.add('active');
        currentSearchPage = defaultPageArchived;
    }

    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('tab', tab);
    history.replaceState(null, '', '?' + urlParams.toString());

    if (document.getElementById('searchInput').value || currentAccountFilter) {
        searchTickets(currentSearchPage, tab);
    }
}

function showViewModal(ref, aname, subject, status, details) {
    console.log(`Opening view modal for ticket ref=${ref}, aname=${aname}, subject=${subject}, status=${status}, details=${details}`);
    try {
        const viewModal = document.getElementById('viewModal');
        const viewContent = document.getElementById('viewContent');
        if (!viewModal || !viewContent) {
            console.error('View modal or content element not found');
            alert('Error: Modal elements are missing.');
            return;
        }

        const escapeHTML = (str) => {
            if (typeof str !== 'string') {
                console.warn(`Non-string value passed to escapeHTML: ${str}`);
                return '';
            }
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        };

        viewContent.innerHTML = `
            <p><strong>Ticket Ref:</strong> ${escapeHTML(ref)}</p>
            <p><strong>Account Name:</strong> ${escapeHTML(aname)}</p>
            <p><strong>Subject:</strong> ${escapeHTML(subject)}</p>
            <p><strong>Ticket Status:</strong> <span class="status-${escapeHTML(status.toLowerCase())}">${escapeHTML(status)}</span></p>
            <p><strong>Ticket Details:</strong> ${escapeHTML(details)}</p>
        `;
        viewModal.style.display = 'block';
        console.log('View modal displayed successfully');
    } catch (error) {
        console.error('Error in showViewModal:', error);
        alert('An error occurred while opening the view modal.');
    }
}

function showArchiveModal(ref, aname) {
    document.getElementById('archiveTicketId').value = ref;
    document.getElementById('archiveTicketRef').innerText = ref;
    document.getElementById('archiveTicketName').innerText = aname;
    document.getElementById('archiveModal').style.display = 'block';
}

function showRestoreModal(ref, aname) {
    document.getElementById('restoreTicketId').value = ref;
    document.getElementById('restoreTicketRef').innerText = ref;
    document.getElementById('restoreTicketName').innerText = aname;
    document.getElementById('restoreModal').style.display = 'block';
}

function showDeleteModal(ref, aname) {
    document.getElementById('deleteTicketId').value = ref;
    document.getElementById('deleteTicketRef').innerText = ref;
    document.getElementById('deleteTicketName').innerText = aname;
    document.getElementById('deleteModal').style.display = 'block';
}

function showEditTicketModal(ref, aname, subject, status, details) {
    document.getElementById('edit_t_ref').value = ref;
    document.getElementById('edit_ticket_ref').value = ref;
    document.getElementById('edit_account_name').value = aname;
    document.getElementById('edit_ticket_subject').value = subject;
    document.getElementById('edit_ticket_status').value = status;
    document.getElementById('edit_ticket_details').value = details.replace(/^ARCHIVED:/, '');
    document.querySelectorAll('#editTicketForm .error').forEach(span => span.textContent = '');
    document.getElementById('editTicketModal').style.display = 'block';
}

function showAddTicketModal() {
    console.log('Opening Add Ticket Modal');
    const form = document.getElementById('addTicketForm');
    form.reset();
    document.querySelectorAll('#addTicketForm .error').forEach(span => span.textContent = '');
    const accountInput = document.getElementById('account_name');
    const suggestionsContainer = document.getElementById('account_name_suggestions');
    if (accountInput && suggestionsContainer) {
        accountInput.value = '<?php echo htmlspecialchars($accountname, ENT_QUOTES, 'UTF-8'); ?>';
        suggestionsContainer.innerHTML = '';
        suggestionsContainer.style.display = 'none';
    }

    const date = new Date();
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const uniqueNumber = Math.floor(100000 + Math.random() * 900000);
    const ref = `ref#-${day}-${month}-${year}-${uniqueNumber}`;
    document.getElementById('ticket_ref').value = ref;
    document.getElementById('ticket_status').value = 'Open';
    document.getElementById('addTicketModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    const suggestionsContainer = document.getElementById('account_name_suggestions');
    if (suggestionsContainer) {
        suggestionsContainer.innerHTML = '';
        suggestionsContainer.style.display = 'none';
    }
}
</script>
</body>
</html>


