<?php
session_start();
include 'db.php'; // Include your database connection file

// Verify user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['userId'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
$userId = $_SESSION['userId'];

// Initialize variables to prevent undefined errors
$firstName = $lastName = $userType = '';
$avatarPath = 'default-avatar.png';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $username . '.png';
$openTickets = $closedTickets = $supportOpen = $supportClosed = 0;
$archivedRegular = $archivedSupport = 0;
$pendingTasks = 0;
$totalRegularActive = $totalRegularArchived = 0;
$totalSupportActive = $totalSupportArchived = 0;
$totalRegularActivePages = $totalRegularArchivedPages = 1;
$totalSupportActivePages = $totalSupportArchivedPages = 1;
$resultRegularActive = $resultRegularArchived = null;
$resultSupportActive = $resultSupportArchived = null;
$errorMessage = '';

// Set avatar path
if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} else {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$avatarPath = $_SESSION['avatarPath'];

// Pagination settings
$limit = 10; // Tickets per page
$regularActivePage = isset($_GET['regularActivePage']) ? max(1, (int)$_GET['regularActivePage']) : 1;
$supportActivePage = isset($_GET['supportActivePage']) ? max(1, (int)$_GET['supportActivePage']) : 1;
$regularArchivedPage = isset($_GET['regularArchivedPage']) ? max(1, (int)$_GET['regularArchivedPage']) : 1;
$supportArchivedPage = isset($_GET['supportArchivedPage']) ? max(1, (int)$_GET['supportArchivedPage']) : 1;
$tab = $_GET['tab'] ?? 'regular';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Check database connection
if (!$conn || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn ? $conn->connect_error : "No connection object"));
    die("Database connection failed. Please check your database settings.");
}

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/TIXNET/php_errors.log');

try {
    // Fetch user details
    $sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sqlUser);
    if (!$stmt) {
        throw new Exception("Prepare failed for user query: " . $conn->error);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $resultUser = $stmt->get_result();
    if ($resultUser->num_rows > 0) {
        $row = $resultUser->fetch_assoc();
        $firstName = $row['u_fname'] ?? '';
        $lastName = $row['u_lname'] ?? '';
        $userType = $row['u_type'] ?? '';
    } else {
        error_log("No user found for username: $username");
    }
    $stmt->close();

    // Prepare search term
    $searchLike = $searchTerm ? "%$searchTerm%" : null;

    // Count regular open tickets
    $sqlOpenTickets = "SELECT COUNT(*) AS openTickets FROM tbl_ticket 
                      WHERE t_status = 'open' 
                      AND (t_details NOT LIKE 'ARCHIVED:%' OR t_details IS NULL) 
                      AND t_status != 'archived'";
    if ($searchTerm) {
        $sqlOpenTickets .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR t_ref LIKE ?)";
    }
    $stmtOpenTickets = $conn->prepare($sqlOpenTickets);
    if (!$stmtOpenTickets) {
        throw new Exception("Prepare failed for open tickets: " . $conn->error);
    }
    if ($searchTerm) {
        $stmtOpenTickets->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtOpenTickets->execute();
    $resultOpenTickets = $stmtOpenTickets->get_result();
    $openTickets = $resultOpenTickets->fetch_assoc()['openTickets'] ?? 0;
    $stmtOpenTickets->close();

    // Count regular closed tickets
    $sqlClosedTickets = "SELECT COUNT(*) AS closedTickets FROM tbl_ticket 
                        WHERE t_status = 'closed' 
                        AND (t_details NOT LIKE 'ARCHIVED:%' OR t_details IS NULL) 
                        AND t_status != 'archived'";
    if ($searchTerm) {
        $sqlClosedTickets .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR t_ref LIKE ?)";
    }
    $stmtClosedTickets = $conn->prepare($sqlClosedTickets);
    if (!$stmtClosedTickets) {
        throw new Exception("Prepare failed for closed tickets: " . $conn->error);
    }
    if ($searchTerm) {
        $stmtClosedTickets->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtClosedTickets->execute();
    $resultClosedTickets = $stmtClosedTickets->get_result();
    $closedTickets = $resultClosedTickets->fetch_assoc()['closedTickets'] ?? 0;
    $stmtClosedTickets->close();

    // Count archived regular tickets
    $sqlArchivedRegular = "SELECT COUNT(*) AS archivedTickets FROM tbl_ticket 
                          WHERE t_details LIKE 'ARCHIVED:%' 
                          AND t_status != 'archived'";
    if ($searchTerm) {
        $sqlArchivedRegular .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR t_ref LIKE ?)";
    }
    $stmtArchivedRegular = $conn->prepare($sqlArchivedRegular);
    if (!$stmtArchivedRegular) {
        throw new Exception("Prepare failed for archived regular tickets: " . $conn->error);
    }
    if ($searchTerm) {
        $stmtArchivedRegular->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtArchivedRegular->execute();
    $resultArchivedRegular = $stmtArchivedRegular->get_result();
    $archivedRegular = $resultArchivedRegular->fetch_assoc()['archivedTickets'] ?? 0;
    $stmtArchivedRegular->close();

    // Count support open tickets
    $sqlSupportOpen = "SELECT COUNT(*) AS supportOpen FROM tbl_supp_tickets 
                      WHERE s_status = 'Open' 
                      AND (s_message NOT LIKE 'ARCHIVED:%' OR s_message IS NULL)";
    if ($searchTerm) {
        $sqlSupportOpen .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
    }
    $stmtSupportOpen = $conn->prepare($sqlSupportOpen);
    if (!$stmtSupportOpen) {
        throw new Exception("Prepare failed for support open tickets: " . $conn->error);
    }
    if ($searchTerm) {
        $stmtSupportOpen->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtSupportOpen->execute();
    $resultSupportOpen = $stmtSupportOpen->get_result();
    $supportOpen = $resultSupportOpen->fetch_assoc()['supportOpen'] ?? 0;
    $stmtSupportOpen->close();

    // Count support closed tickets
    $sqlSupportClosed = "SELECT COUNT(*) AS supportClosed FROM tbl_supp_tickets 
                        WHERE s_status = 'Closed' 
                        AND (s_message NOT LIKE 'ARCHIVED:%' OR s_message IS NULL)";
    if ($searchTerm) {
        $sqlSupportClosed .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
    }
    $stmtSupportClosed = $conn->prepare($sqlSupportClosed);
    if (!$stmtSupportClosed) {
        throw new Exception("Prepare failed for support closed tickets: " . $conn->error);
    }
    if ($searchTerm) {
        $stmtSupportClosed->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtSupportClosed->execute();
    $resultSupportClosed = $stmtSupportClosed->get_result();
    $supportClosed = $resultSupportClosed->fetch_assoc()['supportClosed'] ?? 0;
    $stmtSupportClosed->close();

    // Count archived support tickets
    $sqlArchivedSupport = "SELECT COUNT(*) AS archivedSupport FROM tbl_supp_tickets 
                          WHERE s_message LIKE 'ARCHIVED:%'";
    if ($searchTerm) {
        $sqlArchivedSupport .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
    }
    $stmtArchivedSupport = $conn->prepare($sqlArchivedSupport);
    if (!$stmtArchivedSupport) {
        throw new Exception("Prepare failed for archived support tickets: " . $conn->error);
    }
    if ($searchTerm) {
        $stmtArchivedSupport->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtArchivedSupport->execute();
    $resultArchivedSupport = $stmtArchivedSupport->get_result();
    $archivedSupport = $resultArchivedSupport->fetch_assoc()['archivedSupport'] ?? 0;
    $stmtArchivedSupport->close();

    // Calculate pending tasks
    $pendingTasks = $openTickets + $supportOpen;

    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                throw new Exception('Invalid CSRF token');
            }

            if (!isset($_POST['action'])) {
                throw new Exception('No action specified');
            }

            $action = trim($_POST['action']);
            $id = isset($_POST['id']) ? trim($_POST['id']) : '';
            $type = isset($_POST['type']) ? trim($_POST['type']) : '';

            if (empty($id)) {
                throw new Exception('Invalid ticket ID');
            }

            if (!in_array($type, ['regular', 'support'])) {
                throw new Exception('Invalid ticket type');
            }

            if ($action === 'close' && isset($_POST['technicianFullName'])) {
                $technicianFullName = trim($_POST['technicianFullName']);
                $targetTab = $type === 'regular' ? 'regular' : 'support';

                // Validate technician's full name
                $sql = "SELECT u_fname, u_lname FROM tbl_user WHERE u_username = ? AND u_type = 'technician'";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed for technician validation: " . $conn->error);
                }
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $expectedFullName = trim($row['u_fname'] . ' ' . $row['u_lname']);
                    if (strtolower($technicianFullName) !== strtolower($expectedFullName)) {
                        throw new Exception('Invalid full name. Please enter your correct technician name');
                    }
                } else {
                    throw new Exception("technician \"$firstName $lastName\"");
                }
                $stmt->close();

                if ($type === 'regular') {
                    // Fetch ticket details
                    $sql = "SELECT t_ref, t_aname, t_subject, t_details 
                           FROM tbl_ticket 
                           WHERE t_ref = ? AND t_status = 'open' AND t_status != 'archived' AND t_details NOT LIKE 'ARCHIVED:%'";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Prepare failed for regular ticket fetch: " . $conn->error);
                    }
                    $stmt->bind_param("s", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $ticket = $result->fetch_assoc();
                        $stmt->close();

                        // Insert into closed tickets table
                        $sqlInsert = "INSERT INTO tbl_close_regular (t_ref, t_aname, te_technician, t_subject, t_status, t_details) 
                                     VALUES (?, ?, ?, ?, 'Closed', ?)";
                        $stmtInsert = $conn->prepare($sqlInsert);
                        if (!$stmtInsert) {
                            throw new Exception("Prepare failed for insert closed regular: " . $conn->error);
                        }
                        $stmtInsert->bind_param("sssss", $ticket['t_ref'], $ticket['t_aname'], $technicianFullName, 
                                              $ticket['t_subject'], $ticket['t_details']);
                        $stmtInsert->execute();
                        $stmtInsert->close();

                        // Delete from open tickets
                        $sqlDelete = "DELETE FROM tbl_ticket WHERE t_ref = ?";
                        $stmtDelete = $conn->prepare($sqlDelete);
                        if (!$stmtDelete) {
                            throw new Exception("Prepare failed for delete regular ticket: " . $conn->error);
                        }
                        $stmtDelete->bind_param("s", $id);
                        $stmtDelete->execute();
                        $stmtDelete->close();

                        // Log action
                        $logType = $firstName . ' ' . $lastName;
                        $logDescription = "closed regular ticket ref#$id";
                        $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_type, l_description) VALUES (NOW(), ?, ?)";
                        $stmtLog = $conn->prepare($sqlLog);
                        if (!$stmtLog) {
                            throw new Exception("Prepare failed for log: " . $conn->error);
                        }
                        $stmtLog->bind_param("ss", $logType, $logDescription);
                        $stmtLog->execute();
                        $stmtLog->close();
                    } else {
                        throw new Exception('Ticket not found, already closed, or archived');
                    }
                } else {
                    // Handle support ticket closure
                    $sql = "SELECT s_ref, c_id, c_fname, c_lname, s_subject, s_message 
                           FROM tbl_supp_tickets 
                           WHERE s_ref = ? AND s_status = 'Open' AND s_message NOT LIKE 'ARCHIVED:%'";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Prepare failed for support ticket fetch: " . $conn->error);
                    }
                    $stmt->bind_param("s", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $ticket = $result->fetch_assoc();
                        $stmt->close();

                        // Insert into closed support tickets table
                        $sqlInsert = "INSERT INTO tbl_close_supp (s_ref, c_id, c_fname, c_lname, te_technician, s_subject, s_message, s_status) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Closed')";
                        $stmtInsert = $conn->prepare($sqlInsert);
                        if (!$stmtInsert) {
                            throw new Exception("Prepare failed for insert closed support: " . $conn->error);
                        }
                        $stmtInsert->bind_param("sisssss", $ticket['s_ref'], $ticket['c_id'], $ticket['c_fname'], 
                                              $ticket['c_lname'], $technicianFullName, $ticket['s_subject'], 
                                              $ticket['s_message']);
                        $stmtInsert->execute();
                        $stmtInsert->close();

                        // Delete from open support tickets
                        $sqlDelete = "DELETE FROM tbl_supp_tickets WHERE s_ref = ?";
                        $stmtDelete = $conn->prepare($sqlDelete);
                        if (!$stmtDelete) {
                            throw new Exception("Prepare failed for delete support ticket: " . $conn->error);
                        }
                        $stmtDelete->bind_param("s", $id);
                        $stmtDelete->execute();
                        $stmtDelete->close();

                        // Log action
                        $logType = $firstName . ' ' . $lastName;
                        $logDescription = "closed support ticket ref#$id";
                        $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_type, l_description) VALUES (NOW(), ?, ?)";
                        $stmtLog = $conn->prepare($sqlLog);
                        if (!$stmtLog) {
                            throw new Exception("Prepare failed for log: " . $conn->error);
                        }
                        $stmtLog->bind_param("ss", $logType, $logDescription);
                        $stmtLog->execute();
                        $stmtLog->close();
                    } else {
                        throw new Exception('Ticket not found, already closed, or archived');
                    }
                }

                // Prepare redirect URL
                $redirectParams = [
                    'tab' => $targetTab,
                    'regularActivePage' => $regularActivePage,
                    'supportActivePage' => $supportActivePage,
                    'regularArchivedPage' => $regularArchivedPage,
                    'supportArchivedPage' => $supportArchivedPage
                ];

                if ($searchTerm) {
                    $redirectParams['search'] = $searchTerm;
                }

                header("Location: technicianD.php?" . http_build_query($redirectParams));
                exit;
            } elseif (in_array($action, ['archive', 'unarchive', 'delete'])) {
                // Set target tab: archive goes to archived tab, unarchive to active, delete stays in archived
                $targetTab = 'regularArchived'; // Default for regular tickets
                if ($type === 'support') {
                    $targetTab = 'supportArchived';
                }
                if ($action === 'unarchive') {
                    $targetTab = $type === 'regular' ? 'regular' : 'support';
                } elseif ($action === 'archive') {
                    $targetTab = $type === 'regular' ? 'regularArchived' : 'supportArchived';
                }

                if ($action === 'archive') {
                    if ($type === 'regular') {
                        $sql = "UPDATE tbl_ticket SET t_details = CONCAT('ARCHIVED:', t_details) 
                               WHERE t_ref = ? AND t_status != 'archived' AND t_details NOT LIKE 'ARCHIVED:%'";
                    } else {
                        $sql = "UPDATE tbl_supp_tickets SET s_message = CONCAT('ARCHIVED:', s_message) 
                               WHERE s_ref = ? AND s_message NOT LIKE 'ARCHIVED:%'";
                    }
                } elseif ($action === 'unarchive') {
                    if ($type === 'regular') {
                        $sql = "UPDATE tbl_ticket SET t_details = REPLACE(t_details, 'ARCHIVED:', '') 
                               WHERE t_ref = ? AND t_details LIKE 'ARCHIVED:%'";
                    } else {
                        $sql = "UPDATE tbl_supp_tickets SET s_message = REPLACE(s_message, 'ARCHIVED:', '') 
                               WHERE s_ref = ? AND s_message LIKE 'ARCHIVED:%'";
                    }
                } elseif ($action === 'delete') {
                    if ($type === 'regular') {
                        $sql = "DELETE FROM tbl_ticket WHERE t_ref = ? AND t_details LIKE 'ARCHIVED:%'";
                    } else {
                        $sql = "DELETE FROM tbl_supp_tickets WHERE s_ref = ? AND s_message LIKE 'ARCHIVED:%'";
                    }
                }

                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    error_log("Prepare failed for $action on $type ticket ref#$id: " . $conn->error);
                    throw new Exception("Prepare failed for $action: " . $conn->error);
                }
                $stmt->bind_param("s", $id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        // Log action
                        $logType = $firstName . ' ' . $lastName;
                        $logDescription = $action . "ed " . $type . " ticket ref#$id";
                        $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_type, l_description) VALUES (NOW(), ?, ?)";
                        $stmtLog = $conn->prepare($sqlLog);
                        if (!$stmtLog) {
                            error_log("Prepare failed for log: " . $conn->error);
                            throw new Exception("Prepare failed for log: " . $conn->error);
                        }
                        $stmtLog->bind_param("ss", $logType, $logDescription);
                        $stmtLog->execute();
                        $stmtLog->close();

                        // Prepare redirect URL
                        $redirectParams = [
                            'tab' => $targetTab,
                            'regularActivePage' => $targetTab === 'regular' ? 1 : $regularActivePage,
                            'supportActivePage' => $targetTab === 'support' ? 1 : $supportActivePage,
                            'regularArchivedPage' => $targetTab === 'regularArchived' ? 1 : $regularArchivedPage,
                            'supportArchivedPage' => $targetTab === 'supportArchived' ? 1 : $supportArchivedPage
                        ];

                        if ($searchTerm) {
                            $redirectParams['search'] = $searchTerm;
                        }

                        header("Location: technicianD.php?" . http_build_query($redirectParams));
                        exit;
                    } else {
                        error_log("No rows affected for $action on $type ticket ref#$id");
                        throw new Exception("No changes made. Ticket may already be in the requested state or does not exist");
                    }
                } else {
                    error_log("Execute failed for $action on $type ticket ref#$id: " . $stmt->error);
                    throw new Exception("Failed to execute $action on ticket");
                }
                $stmt->close();
            } else {
                throw new Exception('Invalid action specified');
            }
        } catch (Exception $e) {
            $errorMessage = "Action failed: " . $e->getMessage();
            error_log("POST action error: " . $e->getMessage());
        }
    }

    // Fetch ticket data for display
    // Regular Active Tickets
    $sqlTotalRegularActive = "SELECT COUNT(*) AS total FROM tbl_ticket 
                            WHERE (t_details NOT LIKE 'ARCHIVED:%' OR t_details IS NULL) 
                            AND t_status != 'archived'";
    if ($searchTerm) {
        $sqlTotalRegularActive .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR t_ref LIKE ?)";
    }
    $stmtTotalRegularActive = $conn->prepare($sqlTotalRegularActive);
    if (!$stmtTotalRegularActive) {
        $errorMessage .= "Prepare failed for total regular active: " . $conn->error . " ";
        error_log("Prepare failed for total regular active: " . $conn->error);
    } else {
        if ($searchTerm) {
            $stmtTotalRegularActive->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
        }
        $stmtTotalRegularActive->execute();
        $resultTotalRegularActive = $stmtTotalRegularActive->get_result();
        $totalRegularActive = $resultTotalRegularActive->fetch_assoc()['total'] ?? 0;
        $stmtTotalRegularActive->close();

        $totalRegularActivePages = max(1, ceil($totalRegularActive / $limit));
        $regularActivePage = min($regularActivePage, $totalRegularActivePages);
        $regularActiveOffset = max(0, ($regularActivePage - 1) * $limit);

        $sqlRegularActive = "SELECT t_ref, t_aname, t_subject, t_details, t_status 
                           FROM tbl_ticket 
                           WHERE (t_details NOT LIKE 'ARCHIVED:%' OR t_details IS NULL) 
                           AND t_status != 'archived'";
        if ($searchTerm) {
            $sqlRegularActive .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR t_ref LIKE ?)";
        }
        $sqlRegularActive .= " ORDER BY t_ref ASC LIMIT ? OFFSET ?";
        $stmtRegularActive = $conn->prepare($sqlRegularActive);
        if (!$stmtRegularActive) {
            $errorMessage .= "Prepare failed for regular active tickets: " . $conn->error . " ";
            error_log("Prepare failed for regular active tickets: " . $conn->error);
        } else {
            if ($searchTerm) {
                $stmtRegularActive->bind_param("ssssii", $searchLike, $searchLike, $searchLike, $searchLike, $limit, $regularActiveOffset);
            } else {
                $stmtRegularActive->bind_param("ii", $limit, $regularActiveOffset);
            }
            $stmtRegularActive->execute();
            $resultRegularActive = $stmtRegularActive->get_result();
            $stmtRegularActive->close();
        }
    }

    // Support Active Tickets
    $sqlTotalSupportActive = "SELECT COUNT(*) AS total FROM tbl_supp_tickets 
                            WHERE (s_message NOT LIKE 'ARCHIVED:%' OR s_message IS NULL) 
                            AND s_status IN ('Open', 'Closed')";
    if ($searchTerm) {
        $sqlTotalSupportActive .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
    }
    $stmtTotalSupportActive = $conn->prepare($sqlTotalSupportActive);
    if (!$stmtTotalSupportActive) {
        $errorMessage .= "Prepare failed for total support active: " . $conn->error . " ";
        error_log("Prepare failed for total support active: " . $conn->error);
    } else {
        if ($searchTerm) {
            $stmtTotalSupportActive->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
        }
        $stmtTotalSupportActive->execute();
        $resultTotalSupportActive = $stmtTotalSupportActive->get_result();
        $totalSupportActive = $resultTotalSupportActive->fetch_assoc()['total'] ?? 0;
        $stmtTotalSupportActive->close();

        $totalSupportActivePages = max(1, ceil($totalSupportActive / $limit));
        $supportActivePage = min($supportActivePage, $totalSupportActivePages);
        $supportActiveOffset = max(0, ($supportActivePage - 1) * $limit);

        $sqlSupportActive = "SELECT s_ref, c_id, c_fname, c_lname, s_subject, s_message AS t_details, s_status AS t_status 
                           FROM tbl_supp_tickets 
                           WHERE (s_message NOT LIKE 'ARCHIVED:%' OR s_message IS NULL) 
                           AND s_status IN ('Open', 'Closed')";
        if ($searchTerm) {
            $sqlSupportActive .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
        }
        $sqlSupportActive .= " ORDER BY s_ref DESC LIMIT ? OFFSET ?";
        $stmtSupportActive = $conn->prepare($sqlSupportActive);
        if (!$stmtSupportActive) {
            $errorMessage .= "Prepare failed for support active tickets: " . $conn->error . " ";
            error_log("Prepare failed for support active tickets: " . $conn->error);
        } else {
            if ($searchTerm) {
                $stmtSupportActive->bind_param("ssssii", $searchLike, $searchLike, $searchLike, $searchLike, $limit, $supportActiveOffset);
            } else {
                $stmtSupportActive->bind_param("ii", $limit, $supportActiveOffset);
            }
            $stmtSupportActive->execute();
            $resultSupportActive = $stmtSupportActive->get_result();
            $stmtSupportActive->close();
        }
    }

    // Regular Archived Tickets
    $sqlTotalRegularArchived = "SELECT COUNT(*) AS total FROM tbl_ticket 
                              WHERE t_details LIKE 'ARCHIVED:%' 
                              AND t_status != 'archived'";
    if ($searchTerm) {
        $sqlTotalRegularArchived .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR t_ref LIKE ?)";
    }
    $stmtTotalRegularArchived = $conn->prepare($sqlTotalRegularArchived);
    if (!$stmtTotalRegularArchived) {
        $errorMessage .= "Prepare failed for total regular archived: " . $conn->error . " ";
        error_log("Prepare failed for total regular archived: " . $conn->error);
    } else {
        if ($searchTerm) {
            $stmtTotalRegularArchived->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
        }
        $stmtTotalRegularArchived->execute();
        $resultTotalRegularArchived = $stmtTotalRegularArchived->get_result();
        $totalRegularArchived = $resultTotalRegularArchived->fetch_assoc()['total'] ?? 0;
        $stmtTotalRegularArchived->close();

        $totalRegularArchivedPages = max(1, ceil($totalRegularArchived / $limit));
        $regularArchivedPage = min($regularArchivedPage, $totalRegularArchivedPages);
        $regularArchivedOffset = max(0, ($regularArchivedPage - 1) * $limit);

        $sqlRegularArchived = "SELECT t_ref, t_aname, t_subject, t_details, t_status 
                             FROM tbl_ticket 
                             WHERE t_details LIKE 'ARCHIVED:%' 
                             AND t_status != 'archived'";
        if ($searchTerm) {
            $sqlRegularArchived .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR t_ref LIKE ?)";
        }
        $sqlRegularArchived .= " ORDER BY t_ref ASC LIMIT ? OFFSET ?";
        $stmtRegularArchived = $conn->prepare($sqlRegularArchived);
        if (!$stmtRegularArchived) {
            $errorMessage .= "Prepare failed for regular archived tickets: " . $conn->error . " ";
            error_log("Prepare failed for regular archived tickets: " . $conn->error);
        } else {
            if ($searchTerm) {
                $stmtRegularArchived->bind_param("ssssii", $searchLike, $searchLike, $searchLike, $searchLike, $limit, $regularArchivedOffset);
            } else {
                $stmtRegularArchived->bind_param("ii", $limit, $regularArchivedOffset);
            }
            $stmtRegularArchived->execute();
            $resultRegularArchived = $stmtRegularArchived->get_result();
            $stmtRegularArchived->close();
        }
    }

    // Support Archived Tickets
    $sqlTotalSupportArchived = "SELECT COUNT(*) AS total FROM tbl_supp_tickets 
                              WHERE s_message LIKE 'ARCHIVED:%'";
    if ($searchTerm) {
        $sqlTotalSupportArchived .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
    }
    $stmtTotalSupportArchived = $conn->prepare($sqlTotalSupportArchived);
    if (!$stmtTotalSupportArchived) {
        $errorMessage .= "Prepare failed for total support archived: " . $conn->error . " ";
        error_log("Prepare failed for total support archived: " . $conn->error);
    } else {
        if ($searchTerm) {
            $stmtTotalSupportArchived->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
        }
        $stmtTotalSupportArchived->execute();
        $resultTotalSupportArchived = $stmtTotalSupportArchived->get_result();
        $totalSupportArchived = $resultTotalSupportArchived->fetch_assoc()['total'] ?? 0;
        $stmtTotalSupportArchived->close();

        $totalSupportArchivedPages = max(1, ceil($totalSupportArchived / $limit));
        $supportArchivedPage = min($supportArchivedPage, $totalSupportArchivedPages);
        $supportArchivedOffset = max(0, ($supportArchivedPage - 1) * $limit);

        $sqlSupportArchived = "SELECT s_ref, c_id, c_fname, c_lname, s_subject, s_message AS t_details, s_status AS t_status 
                             FROM tbl_supp_tickets 
                             WHERE s_message LIKE 'ARCHIVED:%'";
        if ($searchTerm) {
            $sqlSupportArchived .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
        }
        $sqlSupportArchived .= " ORDER BY s_ref DESC LIMIT ? OFFSET ?";
        $stmtSupportArchived = $conn->prepare($sqlSupportArchived);
        if (!$stmtSupportArchived) {
            $errorMessage .= "Prepare failed for support archived tickets: " . $conn->error . " ";
            error_log("Prepare failed for support archived tickets: " . $conn->error);
        } else {
            if ($searchTerm) {
                $stmtSupportArchived->bind_param("ssssii", $searchLike, $searchLike, $searchLike, $searchLike, $limit, $supportArchivedOffset);
            } else {
                $stmtSupportArchived->bind_param("ii", $limit, $supportArchivedOffset);
            }
            $stmtSupportArchived->execute();
            $resultSupportArchived = $stmtSupportArchived->get_result();
            $stmtSupportArchived->close();
        }
    }

} catch (Exception $e) {
    $errorMessage .= "Main query block failed: " . $e->getMessage();
    error_log("Main query block error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISP Technician Dashboard</title>
    <link rel="stylesheet" href="technicianSD.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>
    <div class="container">
        <div class="upper">
            <h1>Technician Dashboard</h1>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search tickets..." value="<?php echo htmlspecialchars($searchTerm); ?>" onkeyup="debouncedSearchTickets()">
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

        <!-- Display errors if any -->
        <?php if ($errorMessage): ?>
            <div class="error-message" style="color: red; margin: 10px 0;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <i class="fas fa-tasks text-yellow-500"></i>
                <div class="card-content">
                    <h3>Pending Tasks</h3>
                    <p><strong><?php echo $pendingTasks; ?></strong></p>
                    <p>Regular Open: <?php echo $openTickets; ?> | Support Open: <?php echo $supportOpen; ?></p>
                </div>
            </div>
            <div class="card">
                <i class="fas fa-ticket-alt text-orange-500"></i>
                <div class="card-content">
                    <h3>Regular Tickets</h3>
                    <p>Open: <?php echo $openTickets; ?> | Closed: <?php echo $closedTickets; ?></p>
                    <p>Archived: <?php echo $archivedRegular; ?></p>
                </div>
            </div>
            <div class="card">
                <i class="fas fa-headset text-blue-500"></i>
                <div class="card-content">
                    <h3>Support Tickets</h3>
                    <p>Open: <?php echo $supportOpen; ?> | Closed: <?php echo $supportClosed; ?></p>
                    <p>Archived: <?php echo $archivedSupport; ?></p>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <div id="viewTicketModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Ticket Details</h2>
                </div>
                <div id="viewTicketContent"></div>
                <div class="modal-footer"></div>
            </div>
        </div>

        <div id="actionModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"></div>
                <div class="modal-body"></div>
                <div class="modal-footer"></div>
            </div>
        </div>

        <div id="closeModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Close Ticket</h2>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to close ticket ref#<span id="closeTicketIdDisplay"></span> for <span id="closeTicketName"></span>?</p>
                    <form method="POST" id="closeForm">
                        <input type="hidden" name="action" value="close">
                        <input type="hidden" name="id" id="closeFormId">
                        <input type="hidden" name="type" id="closeFormType">
                        <input type="hidden" name="technicianFullName" id="closeFormTechnicianFullName" value="<?php echo htmlspecialchars($firstName . ' ' . $lastName); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('closeModal')">Cancel</button>
                            <button type="submit" class="modal-btn confirm">Confirm</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <form id="actionForm" method="POST" style="display: none;">
            <input type="hidden" name="action" id="actionFormAction">
            <input type="hidden" name="id" id="actionFormId">
            <input type="hidden" name="type" id="actionFormType">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        </form>

        <div class="tab-container">
            <!-- Main Tabs -->
            <div class="main-tab-buttons">
                <button class="tab-button <?php echo in_array($tab, ['regular', 'regularArchived']) ? 'active' : ''; ?>" onclick="openMainTab('regularTickets', '<?php echo $tab === 'regularArchived' ? 'regularArchived' : 'regular'; ?>')">Regular Tickets</button>
                <button class="tab-button <?php echo in_array($tab, ['support', 'supportArchived']) ? 'active' : ''; ?>" onclick="openMainTab('supportTickets', '<?php echo $tab === 'supportArchived' ? 'supportArchived' : 'support'; ?>')">Support Tickets</button>
            </div>

            <!-- Regular Tickets -->
            <div id="regularTickets" class="main-tab-content <?php echo in_array($tab, ['regular', 'regularArchived']) ? 'active' : ''; ?>">
                <div class="table-box">
                    <div class="sub-tab-buttons">
                        <button class="tab-button <?php echo $tab === 'regular' ? 'active' : ''; ?>" onclick="openSubTab('regularTicketsContent', 'regular')">Active (<?php echo $totalRegularActive; ?>)</button>
                        <button class="tab-button <?php echo $tab === 'regularArchived' ? 'active' : ''; ?>" onclick="openSubTab('regularArchivedTicketsContent', 'regularArchived')">
                            Archived 
                            <?php if ($totalRegularArchived > 0): ?>
                                <span class="tab-badge"><?php echo $totalRegularArchived; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- Active Regular Tickets -->
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
                            <tbody>
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
                                            'isArchived' => strpos($row['t_details'] ?? '', 'ARCHIVED:') === 0
                                        ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                        echo "<tr>
                                                <td>" . htmlspecialchars($row['t_ref']) . "</td>
                                                <td>" . htmlspecialchars($row['t_aname'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($row['t_subject'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($display_details) . "</td>
                                                <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . 
                                                (strtolower($row['t_status']) === 'open' ? " clickable' onclick='openCloseModal(\"" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "\", \"regular\")'" : "'") . 
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
                            $paginationParams = "&search=" . urlencode($searchTerm);
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

                    <!-- Archived Regular Tickets -->
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
                            <tbody>
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
                                                <td>" . htmlspecialchars($row['t_ref']) . "</td>
                                                <td>" . htmlspecialchars($row['t_aname'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($row['t_subject'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($display_details) . "</td>
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
                            $paginationParams = "&search=" . urlencode($searchTerm);
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

            <!-- Support Tickets -->
            <div id="supportTickets" class="main-tab-content <?php echo in_array($tab, ['support', 'supportArchived']) ? 'active' : ''; ?>">
                <div class="table-box">
                    <div class="sub-tab-buttons">
                        <button class="tab-button <?php echo $tab === 'support' ? 'active' : ''; ?>" onclick="openSubTab('supportTicketsContent', 'support')">Active (<?php echo $totalSupportActive; ?>)</button>
                        <button class="tab-button <?php echo $tab === 'supportArchived' ? 'active' : ''; ?>" onclick="openSubTab('supportArchivedTicketsContent', 'supportArchived')">
                            Archived 
                            <?php if ($totalSupportArchived > 0): ?>
                                <span class="tab-badge"><?php echo $totalSupportArchived; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- Active Support Tickets -->
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
                            <tbody>
                                <?php
                                if ($resultSupportActive && $resultSupportActive->num_rows > 0) {
                                    while ($row = $resultSupportActive->fetch_assoc()) {
                                        $display_details = preg_replace('/^ARCHIVED:/', '', $row['t_details'] ?? '');
                                        $ticketData = json_encode([
                                            'ref' => $row['s_ref'],
                                            'c_id' => $row['c_id'] ?? '',
                                            'aname' => ($row['c_fname'] ?? '') . ' ' . ($row['c_lname'] ?? ''),
                                            'subject' => $row['s_subject'] ?? '',
                                            'details' => $display_details,
                                            'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                                            'isArchived' => strpos($row['t_details'] ?? '', 'ARCHIVED:') === 0
                                        ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                        echo "<tr>
                                                <td>" . htmlspecialchars($row['s_ref']) . "</td>
                                                <td>" . htmlspecialchars($row['c_id'] ?? '') . "</td>
                                                <td>" . htmlspecialchars(($row['c_fname'] ?? '') . ' ' . ($row['c_lname'] ?? '')) . "</td>
                                                <td>" . htmlspecialchars($row['s_subject'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($display_details) . "</td>
                                                <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . 
                                                (strtolower($row['t_status']) === 'open' ? " clickable' onclick='openCloseModal(\"" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars(($row['c_fname'] ?? '') . ' ' . ($row['c_lname'] ?? ''), ENT_QUOTES, 'UTF-8') . "\", \"support\")'" : "'") . 
                                                ">" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                                <td class='action-buttons'>
                                                    <span class='view-btn' onclick='showViewModal(\"support\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                                    <span class='archive-btn' onclick='openModal(\"archive\", \"support\", {\"ref\": \"" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Archive'><i class='fas fa-archive'></i></span>
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
                            $paginationParams = "&search=" . urlencode($searchTerm);
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

                    <!-- Archived Support Tickets -->
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
                            <tbody>
                                <?php
                                if ($resultSupportArchived && $resultSupportArchived->num_rows > 0) {
                                    while ($row = $resultSupportArchived->fetch_assoc()) {
                                        $display_details = preg_replace('/^ARCHIVED:/', '', $row['t_details'] ?? '');
                                        $ticketData = json_encode([
                                            'ref' => $row['s_ref'],
                                            'c_id' => $row['c_id'] ?? '',
                                            'aname' => ($row['c_fname'] ?? '') . ' ' . ($row['c_lname'] ?? ''),
                                            'subject' => $row['s_subject'] ?? '',
                                            'details' => $display_details,
                                            'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                                            'isArchived' => true
                                        ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                        echo "<tr>
                                                <td>" . htmlspecialchars($row['s_ref']) . "</td>
                                                <td>" . htmlspecialchars($row['c_id'] ?? '') . "</td>
                                                <td>" . htmlspecialchars(($row['c_fname'] ?? '') . ' ' . ($row['c_lname'] ?? '')) . "</td>
                                                <td>" . htmlspecialchars($row['s_subject'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($display_details) . "</td>
                                                <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . "'>" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                                <td class='action-buttons'>
                                                    <span class='view-btn' onclick='showViewModal(\"support\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                                    <span class='unarchive-btn' onclick='openModal(\"unarchive\", \"support\", {\"ref\": \"" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Unarchive'><i class='fas fa-box-open'></i></span>
                                                    <span class='delete-btn' onclick='openModal(\"delete\", \"support\", {\"ref\": \"" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "\"})' title='Delete'><i class='fas fa-trash'></i></span>
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
                            $paginationParams = "&search=" . urlencode($searchTerm);
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

<script>
function openMainTab(tabName, subTab) {
    try {
        const mainTabContents = document.getElementsByClassName('main-tab-content');
        for (let i = 0; i < mainTabContents.length; i++) {
            mainTabContents[i].classList.remove('active');
        }
        const mainTabButtons = document.getElementsByClassName('main-tab-buttons')[0].getElementsByClassName('tab-button');
        for (let i = 0; i < mainTabButtons.length; i++) {
            mainTabButtons[i].classList.remove('active');
        }
        document.getElementById(tabName).classList.add('active');
        const activeMainButton = document.querySelector(`[onclick="openMainTab('${tabName}', '${subTab}')"]`);
        if (activeMainButton) {
            activeMainButton.classList.add('active');
        }
        openSubTab(subTab === 'regular' ? 'regularTicketsContent' : 
                   subTab === 'regularArchived' ? 'regularArchivedTicketsContent' : 
                   subTab === 'support' ? 'supportTicketsContent' : 
                   'supportArchivedTicketsContent', subTab);
    } catch (e) {
        console.error('Error in openMainTab:', e);
    }
}

function openSubTab(contentId, tabParam) {
    try {
        const mainTabId = contentId.startsWith('regular') ? 'regularTickets' : 'supportTickets';
        const mainTab = document.getElementById(mainTabId);
        const subTabContents = mainTab.getElementsByClassName('sub-tab-content');
        for (let i = 0; i < subTabContents.length; i++) {
            subTabContents[i].classList.remove('active');
        }
        const subTabButtons = mainTab.getElementsByClassName('sub-tab-buttons')[0].getElementsByClassName('tab-button');
        for (let i = 0; i < subTabButtons.length; i++) {
            subTabButtons[i].classList.remove('active');
        }
        const contentElement = document.getElementById(contentId);
        if (contentElement) {
            contentElement.classList.add('active');
        }
        const activeButton = mainTab.querySelector(`[onclick="openSubTab('${contentId}', '${tabParam}')"]`);
        if (activeButton) {
            activeButton.classList.add('active');
        }
        const url = new URL(window.location);
        url.searchParams.set('tab', tabParam);
        try {
            window.history.pushState({}, '', url);
        } catch (e) {
            console.warn('pushState not supported, falling back to location.assign');
            window.location.assign(url);
        }
    } catch (e) {
        console.error('Error in openSubTab:', e);
    }
}

function closeModal(modalId) {
    try {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
    } catch (e) {
        console.error('Error in closeModal:', e);
    }
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

function searchTickets(page = 1) {
    try {
        const searchTerm = document.getElementById('searchInput').value;
        const activeTab = document.querySelector('.sub-tab-content.active');
        if (!activeTab) {
            console.error('No active tab found');
            return;
        }
        const tab = activeTab.id.includes('regularTicketsContent') ? 'regular' :
                    activeTab.id.includes('regularArchivedTicketsContent') ? 'regularArchived' :
                    activeTab.id.includes('supportTicketsContent') ? 'support' : 'supportArchived';
        
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        if (searchTerm) {
            url.searchParams.set('search', searchTerm);
        } else {
            url.searchParams.delete('search');
        }
        url.searchParams.delete('regularActivePage');
        url.searchParams.delete('regularArchivedPage');
        url.searchParams.delete('supportActivePage');
        url.searchParams.delete('supportArchivedPage');
        
        if (tab === 'regular') {
            url.searchParams.set('regularActivePage', page);
        } else if (tab === 'regularArchived') {
            url.searchParams.set('regularArchivedPage', page);
        } else if (tab === 'support') {
            url.searchParams.set('supportActivePage', page);
        } else if (tab === 'supportArchived') {
            url.searchParams.set('supportArchivedPage', page);
        }
        
        window.location.href = url.toString();
    } catch (e) {
        console.error('Error in searchTickets:', e);
    }
}

const debouncedSearchTickets = debounce(searchTickets, 300);

function showViewModal(type, data) {
    try {
        const content = document.getElementById('viewTicketContent');
        const footer = document.getElementById('viewTicketModal').querySelector('.modal-footer');
        const statusClass = `status-${data.status.toLowerCase().replace(' ', '-')}`;
        let html = '';
        if (type === 'regular') {
            html = `
                <p><strong>Ticket Ref:</strong> ${data.ref}</p>
                <p><strong>Customer Name:</strong> ${data.aname}</p>
                <p><strong>Subject:</strong> ${data.subject}</p>
                <p><strong>Message:</strong> ${data.details}</p>
                <p><strong>Status:</strong> <span class="${statusClass}">${data.status}</span></p>
            `;
            footer.innerHTML = `
                ${data.status.toLowerCase() === 'open' && !data.isArchived ? `<button class="modal-btn confirm" onclick="openCloseModal('${data.ref}', '${data.aname}', 'regular')">Close Ticket</button>` : ''}
                <button class="modal-btn cancel" onclick="closeModal('viewTicketModal')">Close</button>
            `;
        } else {
            html = `
                <p><strong>Ticket Ref:</strong> ${data.ref}</p>
                <p><strong>Customer ID:</strong> ${data.c_id}</p>
                <p><strong>Customer Name:</strong> ${data.aname}</p>
                <p><strong>Subject:</strong> ${data.subject}</p>
                <p><strong>Message:</strong> ${data.details}</p>
                <p><strong>Status:</strong> <span class="${statusClass}">${data.status}</span></p>
            `;
            footer.innerHTML = `
                ${data.status.toLowerCase() === 'open' && !data.isArchived ? `<button class="modal-btn confirm" onclick="openCloseModal('${data.ref}', '${data.aname}', 'support')">Close Ticket</button>` : ''}
                <button class="modal-btn cancel" onclick="closeModal('viewTicketModal')">Close</button>
            `;
        }
        content.innerHTML = html;
        document.getElementById('viewTicketModal').style.display = 'block';
        document.body.classList.add('modal-open');
    } catch (e) {
        console.error('Error in showViewModal:', e);
    }
}

function openModal(action, type, data) {
    try {
        const modal = document.getElementById('actionModal');
        const modalHeader = modal.querySelector('.modal-header');
        const modalBody = modal.querySelector('.modal-body');
        const modalFooter = modal.querySelector('.modal-footer');

        const actionText = action.charAt(0).toUpperCase() + action.slice(1);
        modalHeader.innerHTML = `<h2>${actionText} Ticket ref#${data.ref}</h2>`;
        modalBody.innerHTML = `<p>Are you sure you want to ${action} this ticket?</p>`;
        modalFooter.innerHTML = `
            <button class="modal-btn cancel" onclick="closeModal('actionModal')">Cancel</button>
            <button class="modal-btn confirm" onclick="submitAction('${action}', '${type}', '${data.ref}')">Confirm</button>
        `;

        modal.style.display = 'block';
        document.body.classList.add('modal-open');
    } catch (e) {
        console.error('Error in openModal:', e);
    }
}

function openCloseModal(ref, aname, type) {
    try {
        document.getElementById('closeTicketIdDisplay').textContent = ref;
        document.getElementById('closeTicketName').textContent = aname;
        document.getElementById('closeFormId').value = ref;
        document.getElementById('closeFormType').value = type;
        document.getElementById('closeModal').style.display = 'block';
        document.body.classList.add('modal-open');
    } catch (e) {
        console.error('Error in openCloseModal:', e);
    }
}

function submitAction(action, type, ref) {
    try {
        document.getElementById('actionFormAction').value = action;
        document.getElementById('actionFormId').value = ref;
        document.getElementById('actionFormType').value = type;
        document.getElementById('actionForm').submit();
    } catch (e) {
        console.error('Error in submitAction:', e);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab') || 'regular';
        const mainTab = tab === 'regular' || tab === 'regularArchived' ? 'regularTickets' : 'supportTickets';
        openMainTab(mainTab, tab);
        const subTabContentId = tab === 'regular' ? 'regularTicketsContent' :
                               tab === 'regularArchived' ? 'regularArchivedTicketsContent' :
                               tab === 'support' ? 'supportTicketsContent' : 'supportArchivedTicketsContent';
        openSubTab(subTabContentId, tab);
    } catch (e) {
        console.error('Error in DOMContentLoaded:', e);
    }
});
</script>
</body>
</html>