<?php
session_start();
include 'db.php';
require 'PHPmailer-master/PHPmailer-master/src/Exception.php';
require 'PHPmailer-master/PHPmailer-master/src/PHPMailer.php';
require 'PHPmailer-master/PHPmailer-master/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Session and user validation
if (!isset($_SESSION['username'])) {
    error_log("No session username set, redirecting to index.php");
    $_SESSION['error'] = "Please log in to access the technician dashboard.";
    header("Location: index.php");
    exit();
}

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

// Unset any existing error session to prevent displaying old messages
unset($_SESSION['error']);

// Avatar handling
$avatarPath = 'default-avatar.png';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $_SESSION['username'] . '.png';
if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} else {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$avatarPath = $_SESSION['avatarPath'];

// Ensure table structure
$sqlAlterRegular = "ALTER TABLE tbl_ticket ADD COLUMN IF NOT EXISTS technician_username VARCHAR(255) DEFAULT NULL, ADD COLUMN IF NOT EXISTS archive_status VARCHAR(20) DEFAULT 'active'";
$sqlAlterSupport = "ALTER TABLE tbl_supp_tickets ADD COLUMN IF NOT EXISTS technician_username VARCHAR(255) DEFAULT NULL, ADD COLUMN IF NOT EXISTS archive_status VARCHAR(20) DEFAULT 'active'";
$sqlAlterCloseRegular = "ALTER TABLE tbl_close_regular ADD COLUMN IF NOT EXISTS t_status VARCHAR(20) DEFAULT 'closed', ADD COLUMN IF NOT EXISTS te_date DATETIME";
$sqlAlterCloseSupp = "ALTER TABLE tbl_close_supp ADD COLUMN IF NOT EXISTS s_status VARCHAR(20) DEFAULT 'closed', ADD COLUMN IF NOT EXISTS s_date DATETIME";

if (!$conn->query($sqlAlterRegular)) {
    error_log("Failed to alter tbl_ticket: " . $conn->error);
}
if (!$conn->query($sqlAlterSupport)) {
    error_log("Failed to alter tbl_supp_tickets: " . $conn->error);
}
if (!$conn->query($sqlAlterCloseRegular)) {
    error_log("Failed to alter tbl_close_regular: " . $conn->error);
}
if (!$conn->query($sqlAlterCloseSupp)) {
    error_log("Failed to alter tbl_close_supp: " . $conn->error);
}

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'regular';
$validTabs = ['regular', 'support'];
if (!in_array($tab, $validTabs)) {
    $tab = 'regular';
}

function fetchDashboardCounts($conn, $username) {
    $counts = [];
    // Regular open tickets
    $sqlRegularOpen = "SELECT COUNT(*) FROM tbl_ticket WHERE technician_username = ? AND t_status = 'open' AND archive_status = 'active'";
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

    // Regular closed tickets
    $sqlRegularClosed = "SELECT COUNT(*) FROM tbl_close_regular WHERE te_technician = ?";
    $stmtRegularClosed = $conn->prepare($sqlRegularClosed);
    if ($stmtRegularClosed) {
        $stmtRegularClosed->bind_param("s", $username);
        $stmtRegularClosed->execute();
        $stmtRegularClosed->bind_result($counts['closedTickets']);
        $stmtRegularClosed->fetch();
        $stmtRegularClosed->close();
    } else {
        error_log("Prepare failed for regular closed tickets count: " . $conn->error);
        $counts['closedTickets'] = 0;
    }

    // Support open tickets
    $sqlSupportOpen = "SELECT COUNT(*) FROM tbl_supp_tickets WHERE technician_username = ? AND s_status = 'open' AND archive_status = 'active'";
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

    // Support closed tickets
    $sqlSupportClosed = "SELECT COUNT(*) FROM tbl_close_supp WHERE te_technician = ?";
    $stmtSupportClosed = $conn->prepare($sqlSupportClosed);
    if ($stmtSupportClosed) {
        $stmtSupportClosed->bind_param("s", $username);
        $stmtSupportClosed->execute();
        $stmtSupportClosed->bind_result($counts['supportClosed']);
        $stmtSupportClosed->fetch();
        $stmtSupportClosed->close();
    } else {
        error_log("Prepare failed for support closed tickets count: " . $conn->error);
        $counts['supportClosed'] = 0;
    }

    $counts['pendingTasks'] = $counts['openTickets'] + $counts['supportOpen'];
    return $counts;
}

$counts = fetchDashboardCounts($conn, $_SESSION['username']);
$openTickets = $counts['openTickets'];
$closedTickets = $counts['closedTickets'];
$supportOpen = $counts['supportOpen'];
$supportClosed = $counts['supportClosed'];
$pendingTasks = $counts['pendingTasks'];

// Handle ticket actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    ob_start();
    $action = $_POST['action'];
    $t_ref = trim($_POST['id'] ?? '');
    $ticket_type = trim($_POST['type'] ?? '');
    $submitted_csrf = $_POST['csrf_token'] ?? '';
    
    if ($submitted_csrf !== $csrfToken) {
        error_log("CSRF token mismatch: action=$action, t_ref=$t_ref, ticket_type=$ticket_type");
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

    if ($action === 'close') {
        $conn->begin_transaction();
        try {
            // Explicitly get current timestamp
            $closeDate = date('Y-m-d H:i:s');
            error_log("Attempting to close ticket: t_ref=$t_ref, ticket_type=$ticket_type, close_date=$closeDate");

            if ($ticket_type === 'regular') {
                // Fetch ticket data
                $sqlFetch = "SELECT t_aname, t_subject, t_details FROM tbl_ticket WHERE t_ref = ? AND technician_username = ?";
                $stmtFetch = $conn->prepare($sqlFetch);
                if (!$stmtFetch) {
                    throw new Exception("Prepare failed for fetch query (regular ticket): " . $conn->error);
                }
                $stmtFetch->bind_param("ss", $t_ref, $_SESSION['username']);
                $stmtFetch->execute();
                $result = $stmtFetch->get_result();
                if ($result->num_rows === 0) {
                    throw new Exception("Ticket not found: t_ref=$t_ref");
                }
                $ticket = $result->fetch_assoc();
                $stmtFetch->close();

                // Insert into tbl_close_regular with status and close date
                $sqlInsert = "INSERT INTO tbl_close_regular (t_ref, t_aname, t_subject, t_details, t_status, te_technician, te_date) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmtInsert = $conn->prepare($sqlInsert);
                if (!$stmtInsert) {
                    throw new Exception("Prepare failed for insert query (tbl_close_regular): " . $conn->error);
                }
                $status = 'closed';
                $stmtInsert->bind_param("sssssss", $t_ref, $ticket['t_aname'], $ticket['t_subject'], $ticket['t_details'], $status, $_SESSION['username'], $closeDate);
                if (!$stmtInsert->execute()) {
                    throw new Exception("Insert failed for tbl_close_regular: " . $stmtInsert->error);
                }
                $stmtInsert->close();

                // Update the status to 'Closed' in the main table
                $sqlUpdate = "UPDATE tbl_ticket SET t_status = 'Closed' WHERE t_ref = ? AND technician_username = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                if (!$stmtUpdate) {
                    throw new Exception("Prepare failed for update query (tbl_ticket): " . $conn->error);
                }
                $stmtUpdate->bind_param("ss", $t_ref, $_SESSION['username']);
                if (!$stmtUpdate->execute()) {
                    throw new Exception("Update failed in tbl_ticket: " . $stmtUpdate->error);
                }
                $stmtUpdate->close();
                
            } else { // Support ticket
                // Fetch support ticket data
                $sqlFetch = "SELECT st.c_id, CONCAT(c.c_fname, ' ', c.c_lname) AS c_name, st.s_subject, st.s_message 
                             FROM tbl_supp_tickets st 
                             JOIN tbl_customer c ON st.c_id = c.c_id 
                             WHERE st.s_ref = ? AND st.technician_username = ?";
                $stmtFetch = $conn->prepare($sqlFetch);
                if (!$stmtFetch) {
                    throw new Exception("Prepare failed for fetch query (support ticket): " . $conn->error);
                }
                $stmtFetch->bind_param("ss", $t_ref, $_SESSION['username']);
                $stmtFetch->execute();
                $result = $stmtFetch->get_result();
                if ($result->num_rows === 0) {
                    throw new Exception("Support ticket not found: s_ref=$t_ref");
                }
                $ticket = $result->fetch_assoc();
                $stmtFetch->close();

                // Insert into tbl_close_supp with status and close date
                $sqlInsert = "INSERT INTO tbl_close_supp (s_ref, c_id, c_fname, c_lname, s_subject, s_message, s_status, te_technician, s_date) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmtInsert = $conn->prepare($sqlInsert);
                if (!$stmtInsert) {
                    throw new Exception("Prepare failed for insert query (tbl_close_supp): " . $conn->error);
                }
                $status = 'closed';
                // Split c_name into c_fname and c_lname
                $nameParts = explode(' ', trim($ticket['c_name']), 2);
                $c_fname = $nameParts[0] ?? '';
                $c_lname = $nameParts[1] ?? '';
                $stmtInsert->bind_param("sisssssss", 
                    $t_ref, 
                    $ticket['c_id'], 
                    $c_fname, 
                    $c_lname, 
                    $ticket['s_subject'], 
                    $ticket['s_message'], 
                    $status, 
                    $_SESSION['username'], 
                    $closeDate
                );
                if (!$stmtInsert->execute()) {
                    throw new Exception("Insert failed for tbl_close_supp: " . $stmtInsert->error);
                }
                $stmtInsert->close();

                // Update the status to 'Closed' in tbl_supp_tickets AND unset technician_username
                $sqlUpdate = "UPDATE tbl_supp_tickets SET s_status = 'Closed', technician_username = NULL WHERE s_ref = ? AND technician_username = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                if (!$stmtUpdate) {
                    throw new Exception("Prepare failed for update query (tbl_supp_tickets): " . $conn->error);
                }
                $stmtUpdate->bind_param("ss", $t_ref, $_SESSION['username']);
                if (!$stmtUpdate->execute()) {
                    throw new Exception("Update failed in tbl_supp_tickets: " . $stmtUpdate->error);
                } else {
                    error_log("Support ticket unassigned successfully for s_ref=$t_ref"); // Extra log for debugging
                }
                $stmtUpdate->close();
            }

            // Log the action
            $logDescription = "Ticket $t_ref closed by technician $firstName $lastName (Type: $ticket_type, Close Date: $closeDate)";
            $logType = "Technician $firstName $lastName";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            if ($stmtLog) {
                $stmtLog->bind_param("ss", $logDescription, $logType);
                $stmtLog->execute();
                $stmtLog->close();
            } else {
                error_log("Failed to prepare log query: " . $conn->error);
            }

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
            error_log("Close ticket failed: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to close ticket: ' . $e->getMessage()]);
            http_response_code(500);
        }
        exit();
    
    } elseif ($action === 'delete') {
        $table = ($ticket_type === 'regular') ? 'tbl_ticket' : 'tbl_supp_tickets';
        $refColumn = ($ticket_type === 'regular') ? 't_ref' : 's_ref';
        
        $sqlDelete = "DELETE FROM $table WHERE $refColumn = ? AND technician_username = ?";
        $stmtDelete = $conn->prepare($sqlDelete);
        if (!$stmtDelete) {
            error_log("Prepare failed for delete query: " . $conn->error);
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare delete query.']);
            http_response_code(500);
            exit();
        }
        $stmtDelete->bind_param("ss", $t_ref, $_SESSION['username']);
        if ($stmtDelete->execute() && $stmtDelete->affected_rows > 0) {
            $logDescription = "Ticket $t_ref deleted by technician $firstName $lastName (Type: $ticket_type)";
            $logType = "Technician $firstName $lastName";
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
            error_log("Failed to delete ticket or no rows affected: t_ref=$t_ref");
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'No tickets found to delete.']);
            http_response_code(404);
        }
        $stmtDelete->close();
        exit();
    }
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    http_response_code(400);
    exit();
}

// Handle search tickets
if (isset($_GET['action']) && $_GET['action'] === 'search_tickets') {
    header('Content-Type: application/json');
    ob_start();
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
        $sqlCount = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE technician_username = ? AND t_status = 'open' AND archive_status = 'active'";
        $sql = "SELECT t_ref, t_aname, t_subject, t_details, t_status FROM tbl_ticket WHERE technician_username = ? AND t_status = 'open' AND archive_status = 'active'";
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
        $sqlCount = "SELECT COUNT(*) AS total FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id WHERE st.technician_username = ? AND st.s_status = 'open' AND st.archive_status = 'active'";
        $sql = "SELECT st.s_ref AS t_ref, st.c_id, CONCAT(c.c_fname, ' ', c.c_lname) AS t_aname, st.s_subject AS t_subject, st.s_message AS t_details, st.s_status AS t_status
                FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id
                WHERE st.technician_username = ? AND st.s_status = 'open' AND st.archive_status = 'active'";
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

    $stmtCount = $conn->prepare($sqlCount);
    if (!$stmtCount) {
        error_log("Prepare failed for count query: " . $conn->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare count query.']);
        http_response_code(500);
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

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for ticket query: " . $conn->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Database error: Unable to prepare ticket query.']);
        http_response_code(500);
        exit();
    }
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $ticketData = json_encode([
                'ref' => $row['t_ref'],
                'c_id' => $row['c_id'] ?? '',
                'aname' => $row['t_aname'] ?? '',
                'subject' => $row['t_subject'] ?? '',
                'details' => $row['t_details'],
                'status' => ucfirst(strtolower($row['t_status'] ?? ''))
            ], JSON_HEX_QUOT | JSON_HEX_TAG);
            $status = trim(strtolower($row['t_status'] ?? ''));
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td>";
            if ($tab === 'support') {
                echo "<td>" . htmlspecialchars($row['c_id'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            }
            echo "<td>" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['t_subject'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['t_details'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) .
                 ($status === 'open' ? " clickable' onclick='openCloseModal(\"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "\", \"$tab\")'" : "'") .
                 ">" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>";
            echo "<td class='action-buttons'>";
            echo "<span class='view-btn btn btn-primary' onclick='showViewModal(\"$tab\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>";
            echo "<span class='delete-btn btn btn-danger' onclick='openModal(\"delete\", \"$tab\", {\"ref\": \"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Delete'><i class='fas fa-trash'></i></span>";
            echo "</td></tr>";
        }
    } else {
        $colspan = ($tab === 'support') ? 7 : 6;
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

// Fetch tickets for initial display
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch tickets for initial display
if ($tab === 'regular') {
    $sql = "SELECT t_ref, t_aname, t_subject, t_details, t_status FROM tbl_ticket WHERE technician_username = ? AND t_status = 'open' AND archive_status = 'active' LIMIT ?, ?";
    $sqlCount = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE technician_username = ? AND t_status = 'open' AND archive_status = 'active'";
} else {
    $sql = "SELECT st.s_ref AS t_ref, st.c_id, CONCAT(c.c_fname, ' ', c.c_lname) AS t_aname, st.s_subject AS t_subject, st.s_message AS t_details, st.s_status AS t_status
            FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id
            WHERE st.technician_username = ? AND st.s_status = 'open' AND st.archive_status = 'active' LIMIT ?, ?";
    $sqlCount = "SELECT COUNT(*) AS total FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id WHERE st.technician_username = ? AND st.s_status = 'open' AND st.archive_status = 'active'";
}

$stmtCount = $conn->prepare($sqlCount);
if ($stmtCount) {
    $stmtCount->bind_param("s", $_SESSION['username']);
    $stmtCount->execute();
    $stmtCount->bind_result($totalTickets);
    $stmtCount->fetch();
    $stmtCount->close();
} else {
    error_log("Prepare failed for tickets count query: " . $conn->error);
    $totalTickets = 0;
}
$totalPages = ceil($totalTickets / $limit);

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("sii", $_SESSION['username'], $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    error_log("Prepare failed for tickets query: " . $conn->error);
    $result = false;
    $totalTickets = 0;
    $totalPages = 1;
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
    <div class="sidebar">
        <h2><img src="image/logo.png" alt="TixNet Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="technicianD.php" class="active"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
            <li><a href="techBorrowed.php"><i class="fas fa-hand-holding icon"></i> <span>Borrowed Assets</span></a></li>
            <li><a href="TechCustomers.php"><i class="fas fa-user-friends icon"></i> <span>Customers</span></a></li>
        </ul>
        <footer>
            <a href="technician_staff.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>

    <div class="container">
        <div class="upper">
            <h1>Technician Dashboard</h1>
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
                    </div>
                </div>
                <div class="card">
                    <i class="fas fa-headset text-blue-500"></i>
                    <div class="card-content">
                        <h3>Support Tickets</h3>
                        <p>Open: <span id="supportOpenCount2"><?php echo htmlspecialchars($supportOpen, ENT_QUOTES, 'UTF-8'); ?></span> | Closed: <span id="supportClosedCount"><?php echo htmlspecialchars($supportClosed, ENT_QUOTES, 'UTF-8'); ?></span></p>
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
                <div class="table-box">
                    <div class="main-tab-buttons">
                        <button class="tab-button <?php echo $tab === 'regular' ? 'active' : ''; ?>" onclick="openTab('regularTickets', 'regular')">Regular Tickets</button>
                        <button class="tab-button <?php echo $tab === 'support' ? 'active' : ''; ?>" onclick="openTab('supportTickets', 'support')">Support Tickets</button>
                    </div>

                    <div class="search-container">
                    <input type="text" class="search-bar" id="searchInput" placeholder="Search tickets..." onkeyup="debouncedSearchTickets()">
                    <span class="search-icon"><i class="fas fa-search"></i></span>
                    </div>

                    <div id="regularTickets" class="tab-content <?php echo $tab === 'regular' ? 'active' : ''; ?>">
                        <table class="tickets-table" id="regular-tickets">
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
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $ticketData = json_encode([
                                            'ref' => $row['t_ref'],
                                            'aname' => $row['t_aname'] ?? '',
                                            'subject' => $row['t_subject'] ?? '',
                                            'details' => $row['t_details'],
                                            'status' => ucfirst(strtolower($row['t_status'] ?? ''))
                                        ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                        $status = trim(strtolower($row['t_status'] ?? ''));
                                        echo "<tr>
                                                <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['t_aname'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['t_subject'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['t_details'], ENT_QUOTES, 'UTF-8') . "</td>
                                                <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . 
                                                ($status === 'open' ? " clickable' onclick='openCloseModal(\"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "\", \"regular\")'" : "'") . 
                                                ">" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                                <td class='action-buttons'>
                                                    <span class='view-btn btn btn-primary' onclick='showViewModal(\"regular\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                                    <span class='delete-btn btn btn-danger' onclick='openModal(\"delete\", \"regular\", {\"ref\": \"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Delete'><i class='fas fa-trash'></i></span>
                                                </td>
                                              </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6'>No regular tickets found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        <div class="pagination" id="regular-pagination">
                            <?php
                            $paginationParams = "&search=" . urlencode($searchTerm ?? '');
                            if ($page > 1) {
                                echo "<a href='?tab=regular&page=" . ($page - 1) . "$paginationParams' class=\"pagination-link\"><i class=\"fas fa-chevron-left\"></i></a>";
                            } else {
                                echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                            }
                            echo "<span class='current-page'>Page $page of $totalPages</span>";
                            if ($page < $totalPages) {
                                echo "<a href='?tab=regular&page=" . ($page + 1) . "$paginationParams' class=\"pagination-link\"><i class=\"fas fa-chevron-right\"></i></a>";
                            } else {
                                echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                            }
                            ?>
                        </div>
                    </div>

                    <div id="supportTickets" class="tab-content <?php echo $tab === 'support' ? 'active' : ''; ?>">
                        <table class="tickets-table" id="support-tickets">
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
                                if ($tab === 'support' && $result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $ticketData = json_encode([
                                            'ref' => $row['t_ref'],
                                            'c_id' => $row['c_id'] ?? '',
                                            'aname' => $row['t_aname'] ?? '',
                                            'subject' => $row['t_subject'] ?? '',
                                            'details' => $row['t_details'],
                                            'status' => ucfirst(strtolower($row['t_status'] ?? ''))
                                        ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                        $status = trim(strtolower($row['t_status'] ?? ''));
                                        echo "<tr>
                                                <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['c_id'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['t_aname'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['t_subject'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['t_details'], ENT_QUOTES, 'UTF-8') . "</td>
                                                <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . 
                                                ($status === 'open' ? " clickable' onclick='openCloseModal(\"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "\", \"support\")'" : "'") . 
                                                ">" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                                <td class='action-buttons'>
                                                    <span class='view-btn btn btn-primary' onclick='showViewModal(\"support\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                                    <span class='delete-btn btn btn-danger' onclick='openModal(\"delete\", \"support\", {\"ref\": \"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Delete'><i class='fas fa-trash'></i></span>
                                                </td>
                                              </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7'>No support tickets found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        <div class="pagination" id="support-pagination">
                            <?php
                            $paginationParams = "&search=" . urlencode($searchTerm ?? '');
                            if ($page > 1) {
                                echo "<a href='?tab=support&page=" . ($page - 1) . "$paginationParams' class=\"pagination-link\"><i class=\"fas fa-chevron-left\"></i></a>";
                            } else {
                                echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                            }
                            echo "<span class='current-page'>Page $page of $totalPages</span>";
                            if ($page < $totalPages) {
                                echo "<a href='?tab=support&page=" . ($page + 1) . "$paginationParams' class=\"pagination-link\"><i class=\"fas fa-chevron-right\"></i></a>";
                            } else {
                                echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Close Ticket Modal -->
        <div id="closeTicketModal" class="modal close-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Close Ticket</h2>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to close ticket <span id="closeTicketRef"></span> for <span id="closeCustomerName"></span>?</p>
                </div>
                <div class="modal-footer">
                    <button class="modal-btn cancel" onclick="closeModal('closeTicketModal')">Cancel</button>
                    <button class="modal-btn confirm" id="confirmCloseBtn" onclick="submitAction('close')">Confirm</button>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div id="actionModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="actionModalTitle"></h2>
                </div>
                <div class="modal-body">
                    <p id="actionModalMessage"></p>
                </div>
                <div class="modal-footer">
                    <button class="modal-btn cancel" onclick="closeModal('actionModal')">Cancel</button>
                    <button class="modal-btn confirm" id="confirmActionBtn" onclick="submitAction()">Confirm</button>
                </div>
            </div>
        </div>

        <!-- View Ticket Modal -->
        <div id="viewTicketModal" class="modal view-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>View Ticket</h2>
                </div>
                <div class="modal-body">
                    <p><strong>Ticket Ref:</strong> <span id="viewTicketRef"></span></p>
                    <?php if ($tab === 'support'): ?>
                        <p><strong>Customer ID:</strong> <span id="viewCustomerId"></span></p>
                    <?php endif; ?>
                    <p><strong>Customer Name:</strong> <span id="viewCustomerName"></span></p>
                    <p><strong>Subject:</strong> <span id="viewSubject"></span></p>
                    <p><strong>Message:</strong> <span id="viewDetails"></span></p>
                    <p><strong>Status:</strong> <span id="viewStatus"></span></p>
                </div>
                <div class="modal-footer">
                    <button class="modal-btn cancel" onclick="closeModal('viewTicketModal')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentAction = '';
        let currentTicketType = '';
        let currentTicketId = '';

        function openTab(tabId, tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.main-tab-buttons .tab-button').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`.main-tab-buttons .tab-button[onclick*="${tabName}"]`).classList.add('active');
            searchTickets(1, tabName);
        }

        function openModal(action, ticketType, ticket) {
            currentAction = action;
            currentTicketType = ticketType;
            currentTicketId = ticket.ref;
            const modal = document.getElementById('actionModal');
            const title = document.getElementById('actionModalTitle');
            const message = document.getElementById('actionModalMessage');
            title.textContent = action.charAt(0).toUpperCase() + action.slice(1) + ' Ticket';
            message.textContent = `Are you sure you want to ${action} ticket ${ticket.ref}?`;
            modal.className = `modal ${action}-modal`;
            modal.style.display = 'block';
        }

        function openCloseModal(ref, customerName, ticketType) {
            currentTicketId = ref;
            currentTicketType = ticketType;
            document.getElementById('closeTicketRef').textContent = ref;
            document.getElementById('closeCustomerName').textContent = customerName;
            document.getElementById('closeTicketModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            currentAction = '';
            currentTicketId = '';
            currentTicketType = '';
        }

        function showViewModal(ticketType, ticketData) {
            document.getElementById('viewTicketRef').textContent = ticketData.ref;
            if (ticketType === 'support') {
                document.getElementById('viewCustomerId').textContent = ticketData.c_id || 'N/A';
            }
            document.getElementById('viewCustomerName').textContent = ticketData.aname || 'N/A';
            document.getElementById('viewSubject').textContent = ticketData.subject || 'N/A';
            document.getElementById('viewDetails').textContent = ticketData.details || 'N/A';
            document.getElementById('viewStatus').textContent = ticketData.status || 'N/A';
            document.getElementById('viewTicketModal').style.display = 'block';
        }

        function submitAction(actionOverride) {
            const action = actionOverride || currentAction;
            if (!action || !currentTicketId || !currentTicketType) {
                showAlert('error', 'Invalid action or ticket data.');
                return;
            }

            const confirmBtn = action === 'close' ? document.getElementById('confirmCloseBtn') : document.getElementById('confirmActionBtn');
            confirmBtn.disabled = true;
            confirmBtn.setAttribute('data-loading', 'true');
            confirmBtn.textContent = 'Processing...';

            const form = document.getElementById('actionForm');
            document.getElementById('actionFormAction').value = action;
            document.getElementById('actionFormId').value = currentTicketId;
            document.getElementById('actionFormType').value = currentTicketType;

            const formData = new FormData(form);
            const timeout = setTimeout(() => {
                confirmBtn.disabled = false;
                confirmBtn.removeAttribute('data-loading');
                confirmBtn.textContent = 'Confirm';
                showAlert('error', 'Request timed out. Please try again.');
            }, 10000);

            fetch('technicianD.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                clearTimeout(timeout);
                if (!response.ok) {
                    return response.text().then(text => {
                        let errorMsg = `Network error: ${response.status}`;
                        try {
                            const errorData = JSON.parse(text);
                            if (errorData.error) {
                                errorMsg = errorData.error;
                            }
                        } catch (e) {
                            if (text) {
                                errorMsg = text;
                            }
                        }
                        throw new Error(errorMsg);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('AJAX Response:', data);
                if (data.success) {
                    showAlert('success', data.message);
                    updateCounts(data.counts);
                    searchTickets(1, currentTicketType);
                    closeModal(action === 'close' ? 'closeTicketModal' : 'actionModal');
                } else {
                    showAlert('error', data.error || 'An error occurred while processing the request.');
                }
            })
            .catch(error => {
                clearTimeout(timeout);
                console.error('AJAX Error:', error);
                showAlert('error', `Failed to process request: ${error.message}`);
            })
            .finally(() => {
                confirmBtn.disabled = false;
                confirmBtn.removeAttribute('data-loading');
                confirmBtn.textContent = 'Confirm';
            });
        }

        function updateCounts(counts) {
            document.getElementById('pendingTasksCount').textContent = counts.pendingTasks || 0;
            document.getElementById('openTicketsCount').textContent = counts.openTickets || 0;
            document.getElementById('openTicketsCount2').textContent = counts.openTickets || 0;
            document.getElementById('closedTicketsCount').textContent = counts.closedTickets || 0;
            document.getElementById('supportOpenCount').textContent = counts.supportOpen || 0;
            document.getElementById('supportOpenCount2').textContent = counts.supportOpen || 0;
            document.getElementById('supportClosedCount').textContent = counts.supportClosed || 0;
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
            }, 5000);
        }

        function searchTickets(page, tab) {
            const searchTerm = document.getElementById('searchInput').value;
            const tbodyId = tab === 'regular' ? 'regular_tbody' : 'support_tbody';
            const paginationId = tab === 'regular' ? 'regular-pagination' : 'support-pagination';

            fetch(`technicianD.php?action=search_tickets&tab=${tab}&page=${page}&search=${encodeURIComponent(searchTerm)}`)
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`Network response was not ok: Status ${response.status}, ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Search Tickets Response:', data);
                    if (data.success) {
                        document.getElementById(tbodyId).innerHTML = data.html;
                        updateCounts(data.counts);
                        const pagination = document.getElementById(paginationId);
                        let paginationHTML = '';
                        if (data.currentPage > 1) {
                            paginationHTML += `<a href="?tab=${tab}&page=${data.currentPage - 1}&search=${encodeURIComponent(searchTerm)}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
                        } else {
                            paginationHTML += `<span class="pagination-link disabled"><i class='fas fa-chevron-left'></i></span>`;
                        }
                        paginationHTML += `<span class="current-page">Page ${data.currentPage} of ${data.totalPages}</span>`;
                        if (data.currentPage < data.totalPages) {
                            paginationHTML += `<a href="?tab=${tab}&page=${data.currentPage + 1}&search=${encodeURIComponent(searchTerm)}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
                        } else {
                            paginationHTML += `<span class="pagination-link disabled"><i class='fas fa-chevron-right'></i></span>`;
                        }
                        pagination.innerHTML = paginationHTML;
                    } else {
                        showAlert('error', data.error || 'Failed to fetch tickets.');
                    }
                })
                .catch(error => {
                    console.error('Search Tickets Error:', error);
                    showAlert('error', `Failed to fetch tickets: ${error.message}`);
                });
        }

        const debouncedSearchTickets = debounce(() => {
            const tab = document.querySelector('.tab-content.active').id.replace('Tickets', '');
            searchTickets(1, tab);
        }, 300);

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

        window.onclick = function(event) {
            if (event.target.classList.contains('modal') && event.target.id !== 'closeTicketModal') {
                closeModal(event.target.id);
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            openTab('<?php echo $tab === 'regular' ? 'regularTickets' : 'supportTickets'; ?>', '<?php echo $tab; ?>');
        });
    </script>
</body>
</html>