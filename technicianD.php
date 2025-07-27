<?php
session_start();
include 'db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 0); // Suppress errors in production to prevent JSON corruption
error_reporting(E_ALL);

// Check if user is logged in and is a technician
if (!isset($_SESSION['username'])) {
    error_log("No session username set, redirecting to index.php");
    $_SESSION['error'] = "Please log in to access the technician dashboard.";
    header("Location: index.php");
    exit();
}

// Fetch user data to verify technician role
$sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
$stmt = $conn->prepare($sqlUser);
if (!$stmt) {
    error_log("Prepare failed for user query: " . $conn->error);
    $_SESSION['error'] = "Database error occurred. Please try again.";
    header("Location: index.php");
    exit();
}
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$resultUser = $stmt->get_result();
if ($resultUser->num_rows === 0) {
    error_log("User not found for username: {$_SESSION['username']}");
    $_SESSION['error'] = "User account not found.";
    header("Location: index.php");
    exit();
}
$row = $resultUser->fetch_assoc();
$firstName = $row['u_fname'] ?: 'Unknown';
$lastName = $row['u_lname'] ?: '';
$userType = trim(strtolower($row['u_type'])) ?: 'unknown';
$stmt->close();

if ($userType !== 'technician') {
    error_log("User is not a technician: username={$_SESSION['username']}, u_type=$userType");
    $_SESSION['error'] = "Access denied. This page is for technicians only.";
    header("Location: index.php");
    exit();
}
error_log("Technician logged in: username={$_SESSION['username']}, u_type=$userType");

// Initialize avatar
$avatarPath = 'default-avatar.png';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $_SESSION['username'] . '.png';
if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} else {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$avatarPath = $_SESSION['avatarPath'];

// Ensure technician_username column exists
$sqlAlterRegular = "ALTER TABLE tbl_ticket ADD COLUMN IF NOT EXISTS technician_username VARCHAR(255) DEFAULT NULL";
$sqlAlterSupport = "ALTER TABLE tbl_supp_tickets ADD COLUMN IF NOT EXISTS technician_username VARCHAR(255) DEFAULT NULL";
if (!$conn->query($sqlAlterRegular)) {
    error_log("Failed to alter tbl_ticket: " . $conn->error);
}
if (!$conn->query($sqlAlterSupport)) {
    error_log("Failed to alter tbl_supp_tickets: " . $conn->error);
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Determine current tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'regular';
$validTabs = ['regular', 'regularArchived', 'support', 'supportArchived'];
if (!in_array($tab, $validTabs)) {
    $tab = 'regular';
}

// Function to fetch dashboard counts
function fetchDashboardCounts($conn, $username) {
    $counts = [];

    // Regular Tickets
    $sqlRegularOpen = "SELECT COUNT(*) FROM tbl_ticket WHERE technician_username = ? AND t_status = 'open' AND t_details NOT LIKE 'ARCHIVED:%'";
    $stmtRegularOpen = $conn->prepare($sqlRegularOpen);
    if ($stmtRegularOpen) {
        $stmtRegularOpen->bind_param("s", $username);
        $stmtRegularOpen->execute();
        $stmtRegularOpen->bind_result($counts['openTickets']);
        $stmtRegularOpen->fetch();
        $stmtRegularOpen->close();
    } else {
        error_log("Prepare failed for regular open tickets count: " . $conn->error);
        $counts['openTickets'] = 0;
    }

    $sqlRegularClosed = "SELECT COUNT(*) FROM tbl_close_regular WHERE te_technician = ?";
    $stmtRegularClosed = $conn->prepare($sqlRegularClosed);
    if ($stmtRegularClosed) {
        $technician_name = $_SESSION['username'];
        $stmtRegularClosed->bind_param("s", $technician_name);
        $stmtRegularClosed->execute();
        $stmtRegularClosed->bind_result($counts['closedTickets']);
        $stmtRegularClosed->fetch();
        $stmtRegularClosed->close();
    } else {
        error_log("Prepare failed for regular closed tickets count: " . $conn->error);
        $counts['closedTickets'] = 0;
    }

    $sqlRegularArchived = "SELECT COUNT(*) FROM tbl_ticket WHERE technician_username = ? AND t_details LIKE 'ARCHIVED:%'";
    $stmtRegularArchived = $conn->prepare($sqlRegularArchived);
    if ($stmtRegularArchived) {
        $stmtRegularArchived->bind_param("s", $username);
        $stmtRegularArchived->execute();
        $stmtRegularArchived->bind_result($counts['archivedRegular']);
        $stmtRegularArchived->fetch();
        $stmtRegularArchived->close();
    } else {
        error_log("Prepare failed for regular archived tickets count: " . $conn->error);
        $counts['archivedRegular'] = 0;
    }

    // Support Tickets
    $sqlSupportOpen = "SELECT COUNT(*) FROM tbl_supp_tickets WHERE technician_username = ? AND s_status = 'open' AND s_message NOT LIKE 'ARCHIVED:%'";
    $stmtSupportOpen = $conn->prepare($sqlSupportOpen);
    if ($stmtSupportOpen) {
        $stmtSupportOpen->bind_param("s", $username);
        $stmtSupportOpen->execute();
        $stmtSupportOpen->bind_result($counts['supportOpen']);
        $stmtSupportOpen->fetch();
        $stmtSupportOpen->close();
    } else {
        error_log("Prepare failed for support open tickets count: " . $conn->error);
        $counts['supportOpen'] = 0;
    }

    $sqlSupportClosed = "SELECT COUNT(*) FROM tbl_close_supp WHERE te_technician = ?";
    $stmtSupportClosed = $conn->prepare($sqlSupportClosed);
    if ($stmtSupportClosed) {
        $technician_name = $_SESSION['username'];
        $stmtSupportClosed->bind_param("s", $technician_name);
        $stmtSupportClosed->execute();
        $stmtSupportClosed->bind_result($counts['supportClosed']);
        $stmtSupportClosed->fetch();
        $stmtSupportClosed->close();
    } else {
        error_log("Prepare failed for support closed tickets count: " . $conn->error);
        $counts['supportClosed'] = 0;
    }

    $sqlSupportArchived = "SELECT COUNT(*) FROM tbl_supp_tickets WHERE technician_username = ? AND s_message LIKE 'ARCHIVED:%'";
    $stmtSupportArchived = $conn->prepare($sqlSupportArchived);
    if ($stmtSupportArchived) {
        $stmtSupportArchived->bind_param("s", $username);
        $stmtSupportArchived->execute();
        $stmtSupportArchived->bind_result($counts['archivedSupport']);
        $stmtSupportArchived->fetch();
        $stmtSupportArchived->close();
    } else {
        error_log("Prepare failed for support archived tickets count: " . $conn->error);
        $counts['archivedSupport'] = 0;
    }

    $counts['pendingTasks'] = $counts['openTickets'] + $counts['supportOpen'];
    return $counts;
}

// Initial dashboard counts
$counts = fetchDashboardCounts($conn, $_SESSION['username']);
$openTickets = $counts['openTickets'];
$closedTickets = $counts['closedTickets'];
$archivedRegular = $counts['archivedRegular'];
$supportOpen = $counts['supportOpen'];
$supportClosed = $counts['supportClosed'];
$archivedSupport = $counts['archivedSupport'];
$pendingTasks = $counts['pendingTasks'];

// Handle AJAX actions (close, archive, unarchive, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json'); // Ensure JSON response
    ob_start(); // Start output buffering to catch any stray output
    $action = $_POST['action'];
    $t_ref = trim($_POST['id'] ?? '');
    $ticket_type = trim($_POST['type'] ?? '');
    $technician_name = trim($firstName . ' ' . $lastName);
    $submitted_csrf = $_POST['csrf_token'] ?? '';

    if ($submitted_csrf !== $csrfToken) {
        error_log("CSRF token mismatch: action=$action, t_ref=$t_ref");
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
        exit();
    }

    if (empty($t_ref) || empty($ticket_type)) {
        error_log("Missing action data: action=$action, t_ref=$t_ref, ticket_type=$ticket_type");
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
        exit();
    }

    $table = ($ticket_type === 'regular') ? 'tbl_ticket' : 'tbl_supp_tickets';
    $refColumn = ($ticket_type === 'regular') ? 't_ref' : 's_ref';
    $detailsColumn = ($ticket_type === 'regular') ? 't_details' : 's_message';
    $statusColumn = ($ticket_type === 'regular') ? 't_status' : 's_status';

    // Verify ticket exists and is assigned to technician
    $sqlCheck = "SELECT COUNT(*) FROM $table WHERE $refColumn = ? AND technician_username = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    if (!$stmtCheck) {
        error_log("Prepare failed for ticket check query: " . $conn->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Database error: Unable to verify ticket.']);
        exit();
    }
    $stmtCheck->bind_param("ss", $t_ref, $_SESSION['username']);
    $stmtCheck->execute();
    $stmtCheck->bind_result($ticketExists);
    $stmtCheck->fetch();
    $stmtCheck->close();

    if ($ticketExists == 0) {
        error_log("Ticket does not exist or not assigned to technician: t_ref=$t_ref, technician_username={$_SESSION['username']}");
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Ticket does not exist or is not assigned to you.']);
        exit();
    }

    if ($action === 'close') {
        if ($ticket_type === 'regular') {
            // Begin transaction for regular ticket
            $conn->begin_transaction();
            try {
                // Fetch ticket details
                $sqlFetch = "SELECT t_aname, t_subject, t_details, t_status FROM tbl_ticket WHERE t_ref = ? AND technician_username = ?";
                $stmtFetch = $conn->prepare($sqlFetch);
                $stmtFetch->bind_param("ss", $t_ref, $_SESSION['username']);
                $stmtFetch->execute();
                $resultFetch = $stmtFetch->get_result();
                $ticket = $resultFetch->fetch_assoc();
                $stmtFetch->close();

                if (!$ticket) {
                    throw new Exception("Ticket not found for ref: $t_ref");
                }

                // Insert into tbl_close_regular without "Closed by" prefix
                $sqlInsert = "INSERT INTO tbl_close_regular (t_ref, t_aname, te_technician, t_subject, t_status, t_details, te_date)
                              VALUES (?, ?, ?, ?, 'closed', ?, NOW())";
                $stmtInsert = $conn->prepare($sqlInsert);
                $stmtInsert->bind_param("sssss", $t_ref, $ticket['t_aname'], $technician_name, $ticket['t_subject'], $ticket['t_details']);
                $stmtInsert->execute();
                $stmtInsert->close();

                // Delete from tbl_ticket
                $sqlDelete = "DELETE FROM tbl_ticket WHERE t_ref = ? AND technician_username = ?";
                $stmtDelete = $conn->prepare($sqlDelete);
                $stmtDelete->bind_param("ss", $t_ref, $_SESSION['username']);
                $stmtDelete->execute();
                $stmtDelete->close();

                // Log action
                $logDescription = "Ticket $t_ref closed by technician $technician_name (Type: $ticket_type)";
                $logType = "Technician $technician_name";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
                $stmtLog = $conn->prepare($sqlLog);
                $stmtLog->bind_param("ss", $logDescription, $logType);
                $stmtLog->execute();
                $stmtLog->close();

                $conn->commit();
                $updatedCounts = fetchDashboardCounts($conn, $_SESSION['username']);
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Ticket closed successfully.',
                    'counts' => $updatedCounts
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Failed to close regular ticket: " . $e->getMessage());
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Failed to close ticket: ' . $e->getMessage()]);
            }
            exit();
        } else {
            // Begin transaction for support ticket
            $conn->begin_transaction();
            try {
                // Fetch ticket details
                $sqlFetch = "SELECT st.c_id, c.c_fname, c.c_lname, st.s_subject, st.s_message, st.s_status 
                             FROM tbl_supp_tickets st 
                             JOIN tbl_customer c ON st.c_id = c.c_id 
                             WHERE st.s_ref = ? AND st.technician_username = ?";
                $stmtFetch = $conn->prepare($sqlFetch);
                $stmtFetch->bind_param("ss", $t_ref, $_SESSION['username']);
                $stmtFetch->execute();
                $resultFetch = $stmtFetch->get_result();
                $ticket = $resultFetch->fetch_assoc();
                $stmtFetch->close();

                if (!$ticket) {
                    throw new Exception("Support ticket not found for ref: $t_ref");
                }

                // Insert into tbl_close_supp without "Closed by" prefix
                $sqlInsert = "INSERT INTO tbl_close_supp (s_ref, c_id, c_fname, c_lname, te_technician, s_subject, s_message, s_status, s_date)
                              VALUES (?, ?, ?, ?, ?, ?, ?, 'closed', NOW())";
                $stmtInsert = $conn->prepare($sqlInsert);
                $stmtInsert->bind_param("sssssss", $t_ref, $ticket['c_id'], $ticket['c_fname'], $ticket['c_lname'], $technician_name, $ticket['s_subject'], $ticket['s_message']);
                $stmtInsert->execute();
                $stmtInsert->close();

                // Delete from tbl_supp_tickets
                $sqlDelete = "DELETE FROM tbl_supp_tickets WHERE s_ref = ? AND technician_username = ?";
                $stmtDelete = $conn->prepare($sqlDelete);
                $stmtDelete->bind_param("ss", $t_ref, $_SESSION['username']);
                $stmtDelete->execute();
                $stmtDelete->close();

                // Log action
                $logDescription = "Support ticket $t_ref closed by technician $technician_name";
                $logType = "Technician $technician_name";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
                $stmtLog = $conn->prepare($sqlLog);
                $stmtLog->bind_param("ss", $logDescription, $logType);
                $stmtLog->execute();
                $stmtLog->close();

                $conn->commit();
                $updatedCounts = fetchDashboardCounts($conn, $_SESSION['username']);
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Support ticket closed successfully.',
                    'counts' => $updatedCounts
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Failed to close support ticket: " . $e->getMessage());
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Failed to close support ticket: ' . $e->getMessage()]);
            }
            exit();
        }
    } elseif ($action === 'archive') {
        $sqlArchive = "UPDATE $table SET $detailsColumn = CONCAT('ARCHIVED:', $detailsColumn) WHERE $refColumn = ? AND technician_username = ? AND $detailsColumn NOT LIKE 'ARCHIVED:%'";
        $stmtArchive = $conn->prepare($sqlArchive);
        if (!$stmtArchive) {
            error_log("Prepare failed for archive query: " . $conn->error);
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare archive query.']);
            exit();
        }
        $stmtArchive->bind_param("ss", $t_ref, $_SESSION['username']);
        if ($stmtArchive->execute()) {
            $logDescription = "Ticket $t_ref archived by technician $technician_name (Type: $ticket_type)";
            $logType = "Technician $technician_name";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            if ($stmtLog) {
                $stmtLog->bind_param("ss", $logDescription, $logType);
                $stmtLog->execute();
                $stmtLog->close();
            }
            $updatedCounts = fetchDashboardCounts($conn, $_SESSION['username']);
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Ticket archived successfully.',
                'counts' => $updatedCounts
            ]);
        } else {
            error_log("Failed to execute archive query: " . $stmtArchive->error);
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to archive ticket: ' . $stmtArchive->error]);
        }
        $stmtArchive->close();
        exit();
    } elseif ($action === 'unarchive') {
        $sqlUnarchive = "UPDATE $table SET $detailsColumn = REPLACE($detailsColumn, 'ARCHIVED:', '') WHERE $refColumn = ? AND technician_username = ? AND $detailsColumn LIKE 'ARCHIVED:%'";
        $stmtUnarchive = $conn->prepare($sqlUnarchive);
        if (!$stmtUnarchive) {
            error_log("Prepare failed for unarchive query: " . $conn->error);
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare unarchive query.']);
            exit();
        }
        $stmtUnarchive->bind_param("ss", $t_ref, $_SESSION['username']);
        if ($stmtUnarchive->execute()) {
            $logDescription = "Ticket $t_ref unarchived by technician $technician_name (Type: $ticket_type)";
            $logType = "Technician $technician_name";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            if ($stmtLog) {
                $stmtLog->bind_param("ss", $logDescription, $logType);
                $stmtLog->execute();
                $stmtLog->close();
            }
            $updatedCounts = fetchDashboardCounts($conn, $_SESSION['username']);
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Ticket unarchived successfully.',
                'counts' => $updatedCounts
            ]);
        } else {
            error_log("Failed to execute unarchive query: " . $stmtUnarchive->error);
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to unarchive ticket: ' . $stmtUnarchive->error]);
        }
        $stmtUnarchive->close();
        exit();
    } elseif ($action === 'delete') {
        $sqlDelete = "DELETE FROM $table WHERE $refColumn = ? AND technician_username = ?";
        $stmtDelete = $conn->prepare($sqlDelete);
        if (!$stmtDelete) {
            error_log("Prepare failed for delete query: " . $conn->error);
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare delete query.']);
            exit();
        }
        $stmtDelete->bind_param("ss", $t_ref, $_SESSION['username']);
        if ($stmtDelete->execute()) {
            $logDescription = "Ticket $t_ref deleted by technician $technician_name (Type: $ticket_type)";
            $logType = "Technician $technician_name";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            if ($stmtLog) {
                $stmtLog->bind_param("ss", $logDescription, $logType);
                $stmtLog->execute();
                $stmtLog->close();
            }
            $updatedCounts = fetchDashboardCounts($conn, $_SESSION['username']);
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Ticket deleted successfully.',
                'counts' => $updatedCounts
            ]);
        } else {
            error_log("Failed to execute delete query: " . $stmtDelete->error);
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to delete ticket: ' . $stmtDelete->error]);
        }
        $stmtDelete->close();
        exit();
    }
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    exit();
}

// Handle AJAX ticket search
if (isset($_GET['action']) && $_GET['action'] === 'search_tickets') {
    header('Content-Type: application/json');
    ob_start(); // Start output buffering
    $searchTerm = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'regular';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $likeSearch = '%' . $searchTerm . '%';
    $sql = "";
    $sqlCount = "";
    $params = [$_SESSION['username']];
    $paramTypes = "s";

    if ($tab === 'regular') {
        $sqlCount = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE technician_username = ? AND t_status IN ('open', 'closed') AND t_details NOT LIKE 'ARCHIVED:%'";
        $sql = "SELECT t_ref, t_aname, t_subject, t_details, t_status FROM tbl_ticket WHERE technician_username = ? AND t_status IN ('open', 'closed') AND t_details NOT LIKE 'ARCHIVED:%'";
        if ($searchTerm) {
            $sql .= " AND (t_ref LIKE ? OR t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
            $sqlCount .= " AND (t_ref LIKE ? OR t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
            $params = array_merge($params, [$likeSearch, $likeSearch, $likeSearch, $likeSearch]);
            $paramTypes .= "ssss";
        }
        $sql .= " LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $paramTypes .= "ii";
    } elseif ($tab === 'regularArchived') {
        $sqlCount = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE technician_username = ? AND t_details LIKE 'ARCHIVED:%'";
        $sql = "SELECT t_ref, t_aname, t_subject, t_details, t_status FROM tbl_ticket WHERE technician_username = ? AND t_details LIKE 'ARCHIVED:%'";
        if ($searchTerm) {
            $sql .= " AND (t_ref LIKE ? OR t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
            $sqlCount .= " AND (t_ref LIKE ? OR t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
            $params = array_merge($params, [$likeSearch, $likeSearch, $likeSearch, $likeSearch]);
            $paramTypes .= "ssss";
        }
        $sql .= " LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $paramTypes .= "ii";
    } elseif ($tab === 'support') {
        $sqlCount = "SELECT COUNT(*) AS total FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id WHERE st.technician_username = ? AND st.s_status IN ('open', 'closed') AND st.s_message NOT LIKE 'ARCHIVED:%'";
        $sql = "SELECT st.s_ref AS t_ref, st.c_id, CONCAT(c.c_fname, ' ', c.c_lname) AS t_aname, st.s_subject AS t_subject, st.s_message AS t_details, st.s_status AS t_status 
                FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id 
                WHERE st.technician_username = ? AND st.s_status IN ('open', 'closed') AND st.s_message NOT LIKE 'ARCHIVED:%'";
        if ($searchTerm) {
            $sql .= " AND (st.s_ref LIKE ? OR c.c_fname LIKE ? OR c.c_lname LIKE ? OR st.s_subject LIKE ? OR st.s_message LIKE ?)";
            $sqlCount .= " AND (st.s_ref LIKE ? OR c.c_fname LIKE ? OR c.c_lname LIKE ? OR st.s_subject LIKE ? OR st.s_message LIKE ?)";
            $params = array_merge($params, [$likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch]);
            $paramTypes .= "sssss";
        }
        $sql .= " LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $paramTypes .= "ii";
    } elseif ($tab === 'supportArchived') {
        $sqlCount = "SELECT COUNT(*) AS total FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id WHERE st.technician_username = ? AND st.s_message LIKE 'ARCHIVED:%'";
        $sql = "SELECT st.s_ref AS t_ref, st.c_id, CONCAT(c.c_fname, ' ', c.c_lname) AS t_aname, st.s_subject AS t_subject, st.s_message AS t_details, st.s_status AS t_status 
                FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id 
                WHERE st.technician_username = ? AND st.s_message LIKE 'ARCHIVED:%'";
        if ($searchTerm) {
            $sql .= " AND (st.s_ref LIKE ? OR c.c_fname LIKE ? OR c.c_lname LIKE ? OR st.s_subject LIKE ? OR st.s_message LIKE ?)";
            $sqlCount .= " AND (st.s_ref LIKE ? OR c.c_fname LIKE ? OR c.c_lname LIKE ? OR st.s_subject LIKE ? OR st.s_message LIKE ?)";
            $params = array_merge($params, [$likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch]);
            $paramTypes .= "sssss";
        }
        $sql .= " LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $paramTypes .= "ii";
    }

    // Get total count for pagination
    $stmtCount = $conn->prepare($sqlCount);
    if (!$stmtCount) {
        error_log("Prepare failed for count query: " . $conn->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit();
    }
    $countParams = array_slice($params, 0, strpos($paramTypes, 'ii') ?: count($params));
    $countParamTypes = substr($paramTypes, 0, strpos($paramTypes, 'ii') ?: strlen($paramTypes));
    if (!empty($countParams)) {
        $stmtCount->bind_param($countParamTypes, ...$countParams);
    }
    $stmtCount->execute();
    $countResult = $stmtCount->get_result();
    $totalRow = $countResult->fetch_assoc();
    $total = $totalRow['total'];
    $totalPages = ceil($total / $limit);
    $stmtCount->close();

    // Fetch tickets
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for ticket query: " . $conn->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit();
    }
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $display_details = preg_replace('/^ARCHIVED:/', '', $row['t_details']);
            $isArchived = ($tab === 'regularArchived' || $tab === 'supportArchived');
            $ticketData = json_encode([
                'ref' => $row['t_ref'],
                'c_id' => $row['c_id'] ?? '',
                'aname' => $row['t_aname'] ?? '',
                'subject' => $row['t_subject'] ?? '',
                'details' => $display_details,
                'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                'isArchived' => $isArchived
            ], JSON_HEX_QUOT | JSON_HEX_TAG);
            $status = trim(strtolower($row['t_status'] ?? '')); // Normalize status
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td>";
            if ($tab === 'support' || $tab === 'supportArchived') {
                echo "<td>" . htmlspecialchars($row['c_id'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            }
            echo "<td>" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['t_subject'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($display_details, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . 
                 ($status === 'open' && !$isArchived ? " clickable' onclick='openCloseModal(\"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "\", \"$tab\")'" : "'") . 
                 ">" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>";
            echo "<td class='action-buttons'>";
            echo "<span class='view-btn' onclick='showViewModal(\"$tab\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>";
            if (!$isArchived) {
                echo "<span class='archive-btn' onclick='openModal(\"archive\", \"$tab\", {\"ref\": \"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Archive'><i class='fas fa-archive'></i></span>";
            } else {
                echo "<span class='unarchive-btn' onclick='openModal(\"unarchive\", \"$tab\", {\"ref\": \"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Unarchive'><i class='fas fa-box-open'></i></span>";
                echo "<span class='delete-btn' onclick='openModal(\"delete\", \"$tab\", {\"ref\": \"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Delete'><i class='fas fa-trash'></i></span>";
            }
            echo "</td></tr>";
        }
    } else {
        $colspan = ($tab === 'support' || $tab === 'supportArchived') ? 7 : 6;
        echo "<tr><td colspan='$colspan' style='text-align: center;'>No tickets found.</td></tr>";
    }
    $tableRows = ob_get_clean();

    $updatedCounts = fetchDashboardCounts($conn, $_SESSION['username']);
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'html' => $tableRows,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'counts' => $updatedCounts
    ]);
    $stmt->close();
    $conn->close();
    exit();
}

// Pagination setup
$limit = 10;

// Regular Active Tickets
$regularActivePage = isset($_GET['regularActivePage']) ? max(1, (int)$_GET['regularActivePage']) : 1;
$offsetRegularActive = ($regularActivePage - 1) * $limit;
$sqlRegularActive = "SELECT t_ref, t_aname, t_subject, t_details, t_status FROM tbl_ticket WHERE technician_username = ? AND t_status IN ('open', 'closed') AND t_details NOT LIKE 'ARCHIVED:%' LIMIT ?, ?";
$stmtRegularActive = $conn->prepare($sqlRegularActive);
if ($stmtRegularActive) {
    $stmtRegularActive->bind_param("sii", $_SESSION['username'], $offsetRegularActive, $limit);
    $stmtRegularActive->execute();
    $resultRegularActive = $stmtRegularActive->get_result();
    
    $sqlCountRegularActive = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE technician_username = ? AND t_status IN ('open', 'closed') AND t_details NOT LIKE 'ARCHIVED:%'";
    $stmtCountRegularActive = $conn->prepare($sqlCountRegularActive);
    if ($stmtCountRegularActive) {
        $stmtCountRegularActive->bind_param("s", $_SESSION['username']);
        $stmtCountRegularActive->execute();
        $stmtCountRegularActive->bind_result($totalRegularActive);
        $stmtCountRegularActive->fetch();
        $stmtCountRegularActive->close();
    } else {
        error_log("Prepare failed for regular active tickets count query: " . $conn->error);
        $totalRegularActive = 0;
    }
    
    $totalRegularActivePages = ceil($totalRegularActive / $limit);
    $stmtRegularActive->close();
} else {
    error_log("Prepare failed for regular active tickets query: " . $conn->error);
    $totalRegularActive = 0;
    $totalRegularActivePages = 1;
}

// Regular Archived Tickets
$regularArchivedPage = isset($_GET['regularArchivedPage']) ? max(1, (int)$_GET['regularArchivedPage']) : 1;
$offsetRegularArchived = ($regularArchivedPage - 1) * $limit;
$sqlRegularArchived = "SELECT t_ref, t_aname, t_subject, t_details, t_status FROM tbl_ticket WHERE technician_username = ? AND t_details LIKE 'ARCHIVED:%' LIMIT ?, ?";
$stmtRegularArchived = $conn->prepare($sqlRegularArchived);
if ($stmtRegularArchived) {
    $stmtRegularArchived->bind_param("sii", $_SESSION['username'], $offsetRegularArchived, $limit);
    $stmtRegularArchived->execute();
    $resultRegularArchived = $stmtRegularArchived->get_result();
    
    $sqlCountRegularArchived = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE technician_username = ? AND t_details LIKE 'ARCHIVED:%'";
    $stmtCountRegularArchived = $conn->prepare($sqlCountRegularArchived);
    if ($stmtCountRegularArchived) {
        $stmtCountRegularArchived->bind_param("s", $_SESSION['username']);
        $stmtCountRegularArchived->execute();
        $stmtCountRegularArchived->bind_result($totalRegularArchived);
        $stmtCountRegularArchived->fetch();
        $stmtCountRegularArchived->close();
    } else {
        error_log("Prepare failed for regular archived tickets count query: " . $conn->error);
        $totalRegularArchived = 0;
    }
    
    $totalRegularArchivedPages = ceil($totalRegularArchived / $limit);
    $stmtRegularArchived->close();
} else {
    error_log("Prepare failed for regular archived tickets query: " . $conn->error);
    $totalRegularArchived = 0;
    $totalRegularArchivedPages = 1;
}

// Support Active Tickets
$supportActivePage = isset($_GET['supportActivePage']) ? max(1, (int)$_GET['supportActivePage']) : 1;
$offsetSupportActive = ($supportActivePage - 1) * $limit;
$sqlSupportActive = "SELECT st.s_ref AS t_ref, st.c_id, CONCAT(c.c_fname, ' ', c.c_lname) AS t_aname, st.s_subject AS t_subject, st.s_message AS t_details, st.s_status AS t_status 
                     FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id 
                     WHERE st.technician_username = ? AND st.s_status IN ('open', 'closed') AND st.s_message NOT LIKE 'ARCHIVED:%' LIMIT ?, ?";
$stmtSupportActive = $conn->prepare($sqlSupportActive);
if ($stmtSupportActive) {
    $stmtSupportActive->bind_param("sii", $_SESSION['username'], $offsetSupportActive, $limit);
    $stmtSupportActive->execute();
    $resultSupportActive = $stmtSupportActive->get_result();
    
    $sqlCountSupportActive = "SELECT COUNT(*) AS total FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id WHERE st.technician_username = ? AND st.s_status IN ('open', 'closed') AND st.s_message NOT LIKE 'ARCHIVED:%'";
    $stmtCountSupportActive = $conn->prepare($sqlCountSupportActive);
    if ($stmtCountSupportActive) {
        $stmtCountSupportActive->bind_param("s", $_SESSION['username']);
        $stmtCountSupportActive->execute();
        $stmtCountSupportActive->bind_result($totalSupportActive);
        $stmtCountSupportActive->fetch();
        $stmtCountSupportActive->close();
    } else {
        error_log("Prepare failed for support active tickets count query: " . $conn->error);
        $totalSupportActive = 0;
    }
    
    $totalSupportActivePages = ceil($totalSupportActive / $limit);
    $stmtSupportActive->close();
} else {
    error_log("Prepare failed for support active tickets query: " . $conn->error);
    $totalSupportActive = 0;
    $totalSupportActivePages = 1;
}

// Support Archived Tickets
$supportArchivedPage = isset($_GET['supportArchivedPage']) ? max(1, (int)$_GET['supportArchivedPage']) : 1;
$offsetSupportArchived = ($supportArchivedPage - 1) * $limit;
$sqlSupportArchived = "SELECT st.s_ref AS t_ref, st.c_id, CONCAT(c.c_fname, ' ', c.c_lname) AS t_aname, st.s_subject AS t_subject, st.s_message AS t_details, st.s_status AS t_status 
                       FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id 
                       WHERE st.technician_username = ? AND st.s_message LIKE 'ARCHIVED:%' LIMIT ?, ?";
$stmtSupportArchived = $conn->prepare($sqlSupportArchived);
if ($stmtSupportArchived) {
    $stmtSupportArchived->bind_param("sii", $_SESSION['username'], $offsetSupportArchived, $limit);
    $stmtSupportArchived->execute();
    $resultSupportArchived = $stmtSupportArchived->get_result();
    
    $sqlCountSupportArchived = "SELECT COUNT(*) AS total FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id WHERE st.technician_username = ? AND st.s_message LIKE 'ARCHIVED:%'";
    $stmtCountSupportArchived = $conn->prepare($sqlCountSupportArchived);
    if ($stmtCountSupportArchived) {
        $stmtCountSupportArchived->bind_param("s", $_SESSION['username']);
        $stmtCountSupportArchived->execute();
        $stmtCountSupportArchived->bind_result($totalSupportArchived);
        $stmtCountSupportArchived->fetch();
        $stmtCountSupportArchived->close();
    } else {
        error_log("Prepare failed for support archived tickets count query: " . $conn->error);
        $totalSupportArchived = 0;
    }
    
    $totalSupportArchivedPages = ceil($totalSupportArchived / $limit);
    $stmtSupportArchived->close();
} else {
    error_log("Prepare failed for support archived tickets query: " . $conn->error);
    $totalSupportArchived = 0;
    $totalSupportArchivedPages = 1;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard</title>
    <link rel="stylesheet" href="technicianD.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="technicianD.php" class="active"><img src="image/main.png" alt="Dashboard" class="icon" /> <span>Dashboard</span></a></li>
            <li><a href="techBorrowed.php"><img src="image/borrowed.png" alt="Borrowed Assets" class="icon" /> <span>Borrowed Assets</span></a></li>
            <li><a href="TechCustomers.php"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers</span></a></li>
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>

    <div class="container">
        <div class="upper">
            <h1>Technician Dashboard</h1>
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

        <div class="dashboard-content">
            <div class="dashboard-cards">
                <div class="card">
                    <i class="fas fa-tasks text-yellow-500"></i>
                    <div class="card-content">
                        <h3>Pending Tasks</h3>
                        <p><strong id="pendingTasksCount"><?php echo htmlspecialchars($pendingTasks, ENT_QUOTES, 'UTF-8'); ?></strong></p>
                        <p>Regular Open: <span id="openTicketsCount"><?php echo htmlspecialchars($openTickets, ENT_QUOTES, 'UTF-8'); ?></span> | Support Open: <span id="supportOpenCount"><?php echo htmlspecialchars($supportOpen, ENT_QUOTES, 'UTF-8'); ?></span></p>
                    </div>
                </div>
                <div class="card">
                    <i class="fas fa-ticket-alt text-orange-500"></i>
                    <div class="card-content">
                        <h3>Regular Tickets</h3>
                        <p>Open: <span id="openTicketsCount2"><?php echo htmlspecialchars($openTickets, ENT_QUOTES, 'UTF-8'); ?></span> | Closed: <span id="closedTicketsCount"><?php echo htmlspecialchars($closedTickets, ENT_QUOTES, 'UTF-8'); ?></span></p>
                        <p>Archived: <span id="archivedRegularCount"><?php echo htmlspecialchars($archivedRegular, ENT_QUOTES, 'UTF-8'); ?></span></p>
                    </div>
                </div>
                <div class="card">
                    <i class="fas fa-headset text-blue-500"></i>
                    <div class="card-content">
                        <h3>Support Tickets</h3>
                        <p>Open: <span id="supportOpenCount2"><?php echo htmlspecialchars($supportOpen, ENT_QUOTES, 'UTF-8'); ?></span> | Closed: <span id="supportClosedCount"><?php echo htmlspecialchars($supportClosed, ENT_QUOTES, 'UTF-8'); ?></span></p>
                        <p>Archived: <span id="archivedSupportCount"><?php echo htmlspecialchars($archivedSupport, ENT_QUOTES, 'UTF-8'); ?></span></p>
                    </div>
                </div>
            </div>

            <form id="actionForm" method="POST" style="display: none;">
                <input type="hidden" name="action" id="actionFormAction">
                <input type="hidden" name="id" id="actionFormId">
                <input type="hidden" name="type" id="actionFormType">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            </form>

            <div class="tab-container">
                <div class="main-tab-buttons">
                    <button class="tab-button <?php echo in_array($tab, ['regular', 'regularArchived']) ? 'active' : ''; ?>" onclick="openMainTab('regularTickets', '<?php echo $tab === 'regularArchived' ? 'regularArchived' : 'regular'; ?>')">Regular Tickets</button>
                    <button class="tab-button <?php echo in_array($tab, ['support', 'supportArchived']) ? 'active' : ''; ?>" onclick="openMainTab('supportTickets', '<?php echo $tab === 'supportArchived' ? 'supportArchived' : 'support'; ?>')">Support Tickets</button>
                </div>

                <div id="regularTickets" class="main-tab-content <?php echo in_array($tab, ['regular', 'regularArchived']) ? 'active' : ''; ?>">
                    <div class="table-box">
                        <div class="sub-tab-buttons">
                            <button class="tab-button <?php echo $tab === 'regular' ? 'active' : ''; ?>" onclick="openSubTab('regularTicketsContent', 'regular')">Active (<?php echo htmlspecialchars($totalRegularActive, ENT_QUOTES, 'UTF-8'); ?>)</button>
                            <button class="tab-button <?php echo $tab === 'regularArchived' ? 'active' : ''; ?>" onclick="openSubTab('regularArchivedTicketsContent', 'regularArchived')">
                                Archived 
                                <?php if ($totalRegularArchived > 0): ?>
                                    <span class="tab-badge"><?php echo htmlspecialchars($totalRegularArchived, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </button>
                        </div>

                        <div id="regularTicketsContent" class="sub-tab-content <?php echo $tab === 'regular' ? 'active' : ''; ?>">
                            <table class="tickets-table" id="regular-active-tickets">
                                <thead>
                                    <tr>
                                        <th>Ticket Ref</th>
                                        <th>Customer Name</th>
                                        <th>Subject</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="regular_tbody">
                                    <?php
                                    if ($resultRegularActive && $resultRegularActive->num_rows > 0) {
                                        while ($row = $resultRegularActive->fetch_assoc()) {
                                            $display_details = preg_replace('/^ARCHIVED:/', '', $row['t_details'] ?? '');
                                            $ticketData = json_encode([
                                                'ref' => $row['t_ref'],
                                                'aname' => $row['t_aname'] ?? '',
                                                'subject' => $row['t_subject'] ?? '',
                                                'details' => $display_details,
                                                'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                                                'isArchived' => false
                                            ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                            $status = trim(strtolower($row['t_status'] ?? '')); // Normalize status
                                            echo "<tr>
                                                    <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($row['t_aname'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($row['t_subject'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($display_details, ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . 
                                                    ($status === 'open' ? " clickable' onclick='openCloseModal(\"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "\", \"regular\")'" : "'") . 
                                                    ">" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                                    <td class='action-buttons'>
                                                        <span class='view-btn' onclick='showViewModal(\"regular\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                                        <span class='archive-btn' onclick='openModal(\"archive\", \"regular\", {\"ref\": \"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Archive'><i class='fas fa-archive'></i></span>
                                                    </td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6'>No active regular tickets found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                            <div class="pagination" id="regular-active-pagination">
                                <?php
                                $paginationParams = "&search=" . urlencode($searchTerm ?? '');
                                if ($regularActivePage > 1) {
                                    echo "<a href='?tab=regular®ularActivePage=" . ($regularActivePage - 1) . "$paginationParams' class=\"pagination-link\"><i class=\"fas fa-chevron-left\"></i></a>";
                                } else {
                                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                                }
                                echo "<span class='current-page'>Page $regularActivePage of $totalRegularActivePages</span>";
                                if ($regularActivePage < $totalRegularActivePages) {
                                    echo "<a href='?tab=regular®ularActivePage=" . ($regularActivePage + 1) . "$paginationParams' class=\"pagination-link\"><i class=\"fas fa-chevron-right\"></i></a>";
                                } else {
                                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                                }
                                ?>
                            </div>
                        </div>

                        <div id="regularArchivedTicketsContent" class="sub-tab-content <?php echo $tab === 'regularArchived' ? 'active' : ''; ?>">
                            <table class="tickets-table" id="regular-archived-tickets">
                                <thead>
                                    <tr>
                                        <th>Ticket Ref</th>
                                        <th>Customer Name</th>
                                        <th>Subject</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="regular_archived_tbody">
                                    <?php
                                    if ($resultRegularArchived && $resultRegularArchived->num_rows > 0) {
                                        while ($row = $resultRegularArchived->fetch_assoc()) {
                                            $display_details = preg_replace('/^ARCHIVED:/', '', $row['t_details'] ?? '');
                                            $ticketData = json_encode([
                                                'ref' => $row['t_ref'],
                                                'aname' => $row['t_aname'] ?? '',
                                                'subject' => $row['t_subject'] ?? '',
                                                'details' => $display_details,
                                                'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                                                'isArchived' => true
                                            ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                            echo "<tr>
                                                    <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($row['t_aname'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($row['t_subject'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($display_details, ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . "'>" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                                    <td class='action-buttons'>
                                                        <span class='view-btn' onclick='showViewModal(\"regular\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                                        <span class='unarchive-btn' onclick='openModal(\"unarchive\", \"regular\", {\"ref\": \"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Unarchive'><i class='fas fa-box-open'></i></span>
                                                        <span class='delete-btn' onclick='openModal(\"delete\", \"regular\", {\"ref\": \"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Delete'><i class='fas fa-trash'></i></span>
                                                    </td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6'>No archived regular tickets found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                            <div class="pagination" id="regular-archived-pagination">
                                <?php
                                $paginationParams = "&search=" . urlencode($searchTerm ?? '');
                                if ($regularArchivedPage > 1) {
                                    echo "<a href='?tab=regularArchived®ularArchivedPage=" . ($regularArchivedPage - 1) . "$paginationParams' class=\"pagination-link\"><i class=\"fas fa-chevron-left\"></i></a>";
                                } else {
                                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                                }
                                echo "<span class='current-page'>Page $regularArchivedPage of $totalRegularArchivedPages</span>";
                                if ($regularArchivedPage < $totalRegularArchivedPages) {
                                    echo "<a href='?tab=regularArchived®ularArchivedPage=" . ($regularArchivedPage + 1) . "$paginationParams' class=\"pagination-link\"><i class=\"fas fa-chevron-right\"></i></a>";
                                } else {
                                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="supportTickets" class="main-tab-content <?php echo in_array($tab, ['support', 'supportArchived']) ? 'active' : ''; ?>">
                    <div class="table-box">
                        <div class="sub-tab-buttons">
                            <button class="tab-button <?php echo $tab === 'support' ? 'active' : ''; ?>" onclick="openSubTab('supportTicketsContent', 'support')">Active (<?php echo htmlspecialchars($totalSupportActive, ENT_QUOTES, 'UTF-8'); ?>)</button>
                            <button class="tab-button <?php echo $tab === 'supportArchived' ? 'active' : ''; ?>" onclick="openSubTab('supportArchivedTicketsContent', 'supportArchived')">
                                Archived 
                                <?php if ($totalSupportArchived > 0): ?>
                                    <span class="tab-badge"><?php echo htmlspecialchars($totalSupportArchived, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </button>
                        </div>

                        <div id="supportTicketsContent" class="sub-tab-content <?php echo $tab === 'support' ? 'active' : ''; ?>">
                            <table class="tickets-table" id="support-active-tickets">
                                <thead>
                                    <tr>
                                        <th>Ticket Ref</th>
                                        <th>Customer ID</th>
                                        <th>Customer Name</th>
                                        <th>Subject</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="support_tbody">
                                    <?php
                                    if ($resultSupportActive && $resultSupportActive->num_rows > 0) {
                                        while ($row = $resultSupportActive->fetch_assoc()) {
                                            $display_details = preg_replace('/^ARCHIVED:/', '', $row['t_details'] ?? '');
                                            $ticketData = json_encode([
                                                'ref' => $row['t_ref'],
                                                'c_id' => $row['c_id'] ?? '',
                                                'aname' => $row['t_aname'] ?? '',
                                                'subject' => $row['t_subject'] ?? '',
                                                'details' => $display_details,
                                                'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                                                'isArchived' => false
                                            ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                            $status = trim(strtolower($row['t_status'] ?? '')); // Normalize status
                                            echo "<tr>
                                                    <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($row['c_id'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($row['t_aname'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($row['t_subject'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($display_details, ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . 
                                                    ($status === 'open' ? " clickable' onclick='openCloseModal(\"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "\", \"support\")'" : "'") . 
                                                    ">" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                                    <td class='action-buttons'>
                                                        <span class='view-btn' onclick='showViewModal(\"support\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                                        <span class='archive-btn' onclick='openModal(\"archive\", \"support\", {\"ref\": \"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Archive'><i class='fas fa-archive'></i></span>
                                                    </td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7'>No active support tickets found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                            <div class="pagination" id="support-active-pagination">
                                <?php
                                $paginationParams = "&search=" . urlencode($searchTerm ?? '');
                                if ($supportActivePage > 1) {
                                    echo "<a href='?tab=support&supportActivePage=" . ($supportActivePage - 1) . "$paginationParams' class=\"pagination-link\"><i class=\"fas fa-chevron-left\"></i></a>";
                                } else {
                                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                                }
                                echo "<span class='current-page'>Page $supportActivePage of $totalSupportActivePages</span>";
                                if ($supportActivePage < $totalSupportActivePages) {
                                    echo "<a href='?tab=support&supportActivePage=" . ($supportActivePage + 1) . "$paginationParams' class=\"pagination-link\"><i class=\"fas fa-chevron-right\"></i></a>";
                                } else {
                                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                                }
                                ?>
                            </div>
                        </div>

                        <div id="supportArchivedTicketsContent" class="sub-tab-content <?php echo $tab === 'supportArchived' ? 'active' : ''; ?>">
                            <table class="tickets-table" id="support-archived-tickets">
                                <thead>
                                    <tr>
                                        <th>Ticket Ref</th>
                                        <th>Customer ID</th>
                                        <th>Customer Name</th>
                                        <th>Subject</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="support_archived_tbody">
                                    <?php
                                    if ($resultSupportArchived && $resultSupportArchived->num_rows > 0) {
                                        while ($row = $resultSupportArchived->fetch_assoc()) {
                                            $display_details = preg_replace('/^ARCHIVED:/', '', $row['t_details'] ?? '');
                                            $ticketData = json_encode([
                                                'ref' => $row['t_ref'],
                                                'c_id' => $row['c_id'] ?? '',
                                                'aname' => $row['t_aname'] ?? '',
                                                'subject' => $row['t_subject'] ?? '',
                                                'details' => $display_details,
                                                'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                                                'isArchived' => true
                                            ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                            echo "<tr>
                                                    <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($row['c_id'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($row['t_aname'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($row['t_subject'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td>" . htmlspecialchars($display_details, ENT_QUOTES, 'UTF-8') . "</td>
                                                    <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . "'>" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                                    <td class='action-buttons'>
                                                        <span class='view-btn' onclick='showViewModal(\"support\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                                        <span class='unarchive-btn' onclick='openModal(\"unarchive\", \"support\", {\"ref\": \"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Unarchive'><i class='fas fa-box-open'></i></span>
                                                        <span class='delete-btn' onclick='openModal(\"delete\", \"support\", {\"ref\": \"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Delete'><i class='fas fa-trash'></i></span>
                                                    </td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7'>No archived support tickets found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                            <div class="pagination" id="support-archived-pagination">
                                <?php
                                $paginationParams = "&search=" . urlencode($searchTerm ?? '');
                                if ($supportArchivedPage > 1) {
                                    echo "<a href='?tab=supportArchived&supportArchivedPage=" . ($supportArchivedPage - 1) . "$paginationParams' class=\"pagination-link\"><i class=\"fas fa-chevron-left\"></i></a>";
                                } else {
                                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                                }
                                echo "<span class='current-page'>Page $supportArchivedPage of $totalSupportArchivedPages</span>";
                                if ($supportArchivedPage < $totalSupportArchivedPages) {
                                    echo "<a href='?tab=supportArchived&supportArchivedPage=" . ($supportArchivedPage + 1) . "$paginationParams' class=\"pagination-link\"><i class=\"fas fa-chevron-right\"></i></a>";
                                } else {
                                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="ticketViewModal" class="modal view-modal">
    <div class="modal-content glass-container">
        <div class="modal-header">
            <h2>Ticket Details</h2>
        </div>
        <div id="ticketViewContent" class="view-details"></div>
        <div class="modal-footer">
            <button class="modal-btn cancel" onclick="closeModal('ticketViewModal')">Close</button>
        </div>
    </div>
</div>

<!-- Action Modal (Close, Archive, Unarchive, Delete) -->
<div id="actionModal" class="modal close-modal">
    <div class="modal-content glass-container">
        <div class="modal-header">
            <h2 id="actionModalTitle"></h2>
        </div>
        <p id="actionModalMessage"></p>
        <form id="actionModalForm" method="POST">
            <input type="hidden" name="action" id="modalAction">
            <input type="hidden" name="id" id="modalId">
            <input type="hidden" name="type" id="modalType">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('actionModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- Close Modal -->
<div id="closeModal" class="modal close-modal">
    <div class="modal-content glass-container">
        <div class="modal-header">
            <h2>Close Ticket</h2>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to close ticket ref#<span id="closeTicketIdDisplay"></span> for <span id="closeTicketName"></span>?</p>
            <form method="POST" id="closeForm">
                <input type="hidden" name="action" value="close">
                <input type="hidden" name="id" id="closeFormId">
                <input type="hidden" name="type" id="closeFormType">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('closeModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('alert-hidden');
            setTimeout(() => alert.remove(), 500);
        }, 10000);
    });

    const mainTabButtons = document.querySelectorAll('.main-tab-buttons .tab-button');
    const mainTabContents = document.querySelectorAll('.main-tab-content');
    const subTabButtons = document.querySelectorAll('.sub-tab-buttons .tab-button');
    const subTabContents = document.querySelectorAll('.sub-tab-content');

    mainTabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabId = button.getAttribute('onclick').match(/'([^']+)'/)[1];
            const subTab = button.getAttribute('onclick').match(/'([^']+)'/)[2];
            openMainTab(tabId, subTab);
        });
    });

    subTabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabId = button.getAttribute('onclick').match(/'([^']+)'/)[1];
            const subTab = button.getAttribute('onclick').match(/'([^']+)'/)[2];
            openSubTab(tabId, subTab);
        });
    });

    // Initialize the active tab
    const activeMainTab = document.querySelector('.main-tab-buttons .tab-button.active');
    if (activeMainTab) {
        const tabId = activeMainTab.getAttribute('onclick').match(/'([^']+)'/)[1];
        const subTab = activeMainTab.getAttribute('onclick').match(/'([^']+)'/)[2];
        openMainTab(tabId, subTab);
    }
});

function openMainTab(tabId, subTab) {
    document.querySelectorAll('.main-tab-buttons .tab-button').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.main-tab-content').forEach(content => content.classList.remove('active'));
    document.querySelector(`.main-tab-buttons .tab-button[onclick*="${tabId}"]`).classList.add('active');
    document.getElementById(tabId).classList.add('active');
    openSubTab(tabId === 'regularTickets' ? 'regularTicketsContent' : 'supportTicketsContent', subTab);
}

function openSubTab(tabId, subTab) {
    document.querySelectorAll('.sub-tab-buttons .tab-button').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.sub-tab-content').forEach(content => content.classList.remove('active'));
    document.querySelector(`.sub-tab-buttons .tab-button[onclick*="${tabId}"]`).classList.add('active');
    document.getElementById(tabId).classList.add('active');
    searchTickets(subTab);
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

const debouncedSearchTickets = debounce(searchTickets, 300);

function searchTickets(tab = '<?php echo $tab; ?>') {
    const searchInput = document.getElementById('searchInput').value;
    const tbodyId = tab === 'regular' ? 'regular_tbody' : 
                    tab === 'regularArchived' ? 'regular_archived_tbody' : 
                    tab === 'support' ? 'support_tbody' : 
                    'support_archived_tbody';
    const paginationId = tab === 'regular' ? 'regular-active-pagination' : 
                        tab === 'regularArchived' ? 'regular-archived-pagination' : 
                        tab === 'support' ? 'support-active-pagination' : 
                        'support-archived-pagination';
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `technicianD.php?action=search_tickets&tab=${tab}&search=${encodeURIComponent(searchInput)}&page=1`, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            try {
                if (xhr.status === 200) {
                    let response;
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {
                        console.error('Failed to parse JSON response:', xhr.responseText, e);
                        showAlert('error', 'Invalid server response. Please try again.');
                        return;
                    }

                    if (response.success) {
                        document.getElementById(tbodyId).innerHTML = response.html || '<tr><td colspan="' + (tab.includes('support') ? 7 : 6) + '">No tickets found.</td></tr>';
                        updatePagination(paginationId, response.currentPage, response.totalPages, tab, searchInput);
                        updateDashboardCounts(response.counts);
                    } else {
                        console.error('Server returned success: false:', response.error);
                        showAlert('error', response.error || 'Failed to fetch tickets.');
                    }
                } else {
                    console.error('AJAX request failed with status:', xhr.status, xhr.statusText);
                    showAlert('error', 'Error fetching tickets: Server error ' + xhr.status);
                }
            } catch (e) {
                console.error('Error in searchTickets:', e);
                showAlert('error', 'An unexpected error occurred while fetching tickets.');
            }
        }
    };

    xhr.onerror = function () {
        console.error('Network error during searchTickets');
        showAlert('error', 'Network error while fetching tickets.');
    };

    xhr.send();
}

function updatePagination(paginationId, currentPage, totalPages, tab, search) {
    const pagination = document.getElementById(paginationId);
    if (!pagination) return;

    let paginationHtml = '';
    const searchParam = search ? `&search=${encodeURIComponent(search)}` : '';
    const pageParam = tab === 'regular' ? 'regularActivePage' :
                     tab === 'regularArchived' ? 'regularArchivedPage' :
                     tab === 'support' ? 'supportActivePage' : 'supportArchivedPage';

    if (currentPage > 1) {
        paginationHtml += `<a href="?tab=${tab}&${pageParam}=${currentPage - 1}${searchParam}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="?tab=${tab}&${pageParam}=${currentPage + 1}${searchParam}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    pagination.innerHTML = paginationHtml;
}

function updateDashboardCounts(counts) {
    if (!counts) return;
    document.getElementById('pendingTasksCount').textContent = counts.pendingTasks || 0;
    document.getElementById('openTicketsCount').textContent = counts.openTickets || 0;
    document.getElementById('openTicketsCount2').textContent = counts.openTickets || 0;
    document.getElementById('closedTicketsCount').textContent = counts.closedTickets || 0;
    document.getElementById('archivedRegularCount').textContent = counts.archivedRegular || 0;
    document.getElementById('supportOpenCount').textContent = counts.supportOpen || 0;
    document.getElementById('supportOpenCount2').textContent = counts.supportOpen || 0;
    document.getElementById('supportClosedCount').textContent = counts.supportClosed || 0;
    document.getElementById('archivedSupportCount').textContent = counts.archivedSupport || 0;
}

function showAlert(type, message) {
    const alertContainer = document.querySelector('.alert-container');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    alertContainer.appendChild(alert);
    setTimeout(() => {
        alert.classList.add('alert-hidden');
        setTimeout(() => alert.remove(), 500);
    }, 10000);
}

function openModal(action, ticketType, data) {
    const modal = document.getElementById('actionModal');
    const modalTitle = document.getElementById('actionModalTitle');
    const modalMessage = document.getElementById('actionModalMessage');
    const modalAction = document.getElementById('modalAction');
    const modalId = document.getElementById('modalId');
    const modalType = document.getElementById('modalType');

    modalTitle.textContent = {
        'archive': 'Archive Ticket',
        'unarchive': 'Unarchive Ticket',
        'delete': 'Delete Ticket'
    }[action] || 'Action';
    modalMessage.textContent = `Are you sure you want to ${action} ticket ref#${data.ref}?`;
    modalAction.value = action;
    modalId.value = data.ref;
    modalType.value = ticketType;

    modal.style.display = 'block';
}

function openCloseModal(ref, name, ticketType) {
    const modal = document.getElementById('closeModal');
    document.getElementById('closeTicketIdDisplay').textContent = ref;
    document.getElementById('closeTicketName').textContent = name;
    document.getElementById('closeFormId').value = ref;
    document.getElementById('closeFormType').value = ticketType;
    modal.style.display = 'block';
}

function showViewModal(ticketType, ticketData) {
    const modal = document.getElementById('ticketViewModal');
    const content = document.getElementById('ticketViewContent');
    let html = `
        <p><strong>Ticket Ref:</strong> ${ticketData.ref}</p>
        ${ticketType.includes('support') ? `<p><strong>Customer ID:</strong> ${ticketData.c_id || 'N/A'}</p>` : ''}
        <p><strong>Customer Name:</strong> ${ticketData.aname || 'N/A'}</p>
        <p><strong>Subject:</strong> ${ticketData.subject || 'N/A'}</p>
        <p><strong>Message:</strong> ${ticketData.details || 'N/A'}</p>
        <p><strong>Status:</strong> ${ticketData.status || 'N/A'}</p>
    `;
    content.innerHTML = html;
    modal.style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

document.getElementById('actionModalForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'technicianD.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            try {
                if (xhr.status === 200) {
                    let response;
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {
                        console.error('Failed to parse JSON response:', xhr.responseText, e);
                        showAlert('error', 'Invalid server response. Please try again.');
                        return;
                    }

                    if (response.success) {
                        showAlert('success', response.message || 'Action completed successfully.');
                        updateDashboardCounts(response.counts);
                        const ticketType = formData.get('type');
                        const currentTab = ticketType === 'regular' ? (response.message.includes('unarchive') ? 'regular' : ticketType) :
                                          ticketType === 'support' ? (response.message.includes('unarchive') ? 'support' : ticketType) :
                                          '<?php echo $tab; ?>';
                        searchTickets(currentTab);
                        closeModal('actionModal');
                    } else {
                        console.error('Server returned success: false:', response.error);
                        showAlert('error', response.error || 'Action failed.');
                    }
                } else {
                    console.error('AJAX request failed with status:', xhr.status, xhr.statusText);
                    showAlert('error', 'Error performing action: Server error ' + xhr.status);
                }
            } catch (e) {
                console.error('Error in actionModalForm:', e);
                showAlert('error', 'An unexpected error occurred.');
            }
        }
    };

    xhr.onerror = function () {
        console.error('Network error during actionModalForm');
        showAlert('error', 'Network error during action.');
    };

    xhr.send(formData);
});

document.getElementById('closeForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'technicianD.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            try {
                if (xhr.status === 200) {
                    let response;
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {
                        console.error('Failed to parse JSON response:', xhr.responseText, e);
                        showAlert('error', 'Invalid server response. Please try again.');
                        return;
                    }

                    if (response.success) {
                        showAlert('success', response.message || 'Ticket closed successfully.');
                        updateDashboardCounts(response.counts);
                        const ticketType = formData.get('type');
                        searchTickets(ticketType);
                        closeModal('closeModal');
                    } else {
                        console.error('Server returned success: false:', response.error);
                        showAlert('error', response.error || 'Failed to close ticket.');
                    }
                } else {
                    console.error('AJAX request failed with status:', xhr.status, xhr.statusText);
                    showAlert('error', 'Error closing ticket: Server error ' + xhr.status);
                }
            } catch (e) {
                console.error('Error in closeForm:', e);
                showAlert('error', 'An unexpected error occurred while closing the ticket.');
            }
        }
    };

    xhr.onerror = function () {
        console.error('Network error during closeForm');
        showAlert('error', 'Network error while closing ticket.');
    };

    xhr.send(formData);
});

// Initialize the active tab on page load
searchTickets('<?php echo $tab; ?>');
</script>

</body>
</html>
   

