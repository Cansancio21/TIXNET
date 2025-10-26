<?php
session_start();
include 'db.php';

// Include PHPMailer dependencies
require 'PHPmailer-master/PHPmailer-master/src/Exception.php';
require 'PHPmailer-master/PHPmailer-master/src/PHPMailer.php';
require 'PHPmailer-master/PHPmailer-master/src/SMTP.php';

// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Session and user validation
if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = "Please log in to access the technician dashboard.";
    header("Location: index.php");
    exit();
}

$sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
$stmt = $conn->prepare($sqlUser);
if (!$stmt) {
    $_SESSION['error'] = "Database error occurred. Please try again.";
    header("Location: index.php");
    exit();
}
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$resultUser = $stmt->get_result();
if ($resultUser->num_rows === 0) {
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
    $_SESSION['error'] = "Access denied. This page is for technicians only.";
    header("Location: index.php");
    exit();
}

// Unset any existing error session
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

// SIMPLIFIED Dashboard counts function - FIXED SUPPORT CLOSED COUNT
function fetchDashboardCounts($conn, $username) {
    $counts = [
        'openTickets' => 0,
        'closedTickets' => 0,
        'supportOpen' => 0,
        'supportClosed' => 0,
        'pendingTasks' => 0
    ];
    
    // Regular open tickets
    $sqlRegularOpen = "SELECT COUNT(*) FROM tbl_ticket WHERE technician_username = ? AND t_status = 'open'";
    if ($stmt = $conn->prepare($sqlRegularOpen)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($counts['openTickets']);
        $stmt->fetch();
        $stmt->close();
    }
    
    // Regular closed tickets
    $sqlRegularClosed = "SELECT COUNT(*) FROM tbl_ticket WHERE technician_username = ? AND t_status = 'Closed'";
    if ($stmt = $conn->prepare($sqlRegularClosed)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($counts['closedTickets']);
        $stmt->fetch();
        $stmt->close();
    }
    
    // Support open tickets
    $sqlSupportOpen = "SELECT COUNT(*) FROM tbl_supp_tickets WHERE technician_username = ? AND s_status = 'open'";
    if ($stmt = $conn->prepare($sqlSupportOpen)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($counts['supportOpen']);
        $stmt->fetch();
        $stmt->close();
    }
    
    // Support closed tickets - FIXED: Count technician's closed support tickets
    $sqlSupportClosed = "SELECT COUNT(*) FROM tbl_supp_tickets WHERE technician_username = ? AND s_status = 'Closed'";
    if ($stmt = $conn->prepare($sqlSupportClosed)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($counts['supportClosed']);
        $stmt->fetch();
        $stmt->close();
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

// Handle ticket actions - WITH EMAIL FUNCTIONALITY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $t_ref = trim($_POST['id'] ?? '');
    $ticket_type = trim($_POST['type'] ?? '');
    $submitted_csrf = $_POST['csrf_token'] ?? '';
    $technician_name = trim($firstName . ' ' . $lastName);
    
    // CSRF validation
    if ($submitted_csrf !== $csrfToken) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
        exit();
    }

    if (empty($t_ref) || empty($ticket_type)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
        exit();
    }

    if ($action === 'close') {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            if ($ticket_type === 'regular') {
                // First get the ticket details including customer email before closing
                $sqlSelect = "SELECT t.t_ref, t.t_aname, t.t_subject, t.t_details, t.t_status, c.c_email 
                             FROM tbl_ticket t
                             JOIN tbl_customer c ON t.t_aname = CONCAT(c.c_fname, ' ', c.c_lname)
                             WHERE t.t_ref = ? AND t.technician_username = ? AND t.t_status = 'open'";
                $stmtSelect = $conn->prepare($sqlSelect);
                $stmtSelect->bind_param("ss", $t_ref, $_SESSION['username']);
                $stmtSelect->execute();
                $resultSelect = $stmtSelect->get_result();
                
                if ($resultSelect->num_rows === 0) {
                    throw new Exception("No open regular ticket found to close");
                }
                
                $ticketData = $resultSelect->fetch_assoc();
                $stmtSelect->close();
                
                // Insert into closed tickets table
                $sqlInsert = "INSERT INTO tbl_close_regular (t_ref, t_aname, te_technician, t_subject, t_details, t_status, te_date) 
                             VALUES (?, ?, ?, ?, ?, 'Closed', NOW())";
                $stmtInsert = $conn->prepare($sqlInsert);
                $stmtInsert->bind_param("sssss", 
                    $ticketData['t_ref'],
                    $ticketData['t_aname'],
                    $technician_name,
                    $ticketData['t_subject'],
                    $ticketData['t_details']
                );
                $stmtInsert->execute();
                $stmtInsert->close();
                
                // Now close the original ticket
                $sql = "UPDATE tbl_ticket SET t_status = 'Closed' WHERE t_ref = ? AND technician_username = ? AND t_status = 'open'";
                
                // Send email notification to customer
                if (!empty($ticketData['c_email'])) {
                    $mail = new PHPMailer(true);
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'jonwilyammayormita@gmail.com';
                        $mail->Password = 'mqkcqkytlwurwlks';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        // Recipients
                        $mail->setFrom('jonwilyammayormita@gmail.com', 'TixNet System');
                        $mail->addAddress($ticketData['c_email'], $ticketData['t_aname']);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Your Ticket Has Been Resolved';
                        $mail->Body = "
                            <html>
                            <head>
                                <title>Ticket Resolution Confirmation</title>
                            </head>
                            <body>
                                <p>Dear {$ticketData['t_aname']},</p>
                                <p>We are pleased to inform you that your ticket (Ref# {$t_ref}) has been resolved by our technician, {$technician_name}.</p>
                                <p><strong>Ticket Details:</strong></p>
                                <p><strong>Ticket Ref:</strong> {$t_ref}</p>
                                <p><strong>Subject:</strong> {$ticketData['t_subject']}</p>
                                <p><strong>Message:</strong> {$ticketData['t_details']}</p>
                                <p><strong>Status:</strong> Closed</p>
                                <p>If you have any further questions or need additional assistance, please contact our support team.</p>
                                <p><a href='http://localhost/TIMSSS/index.php'>Visit TixNet System</a></p>
                                <p>Best regards,<br>TixNet System Administrator</p>
                            </body>
                            </html>
                        ";
                        $mail->AltBody = "Dear {$ticketData['t_aname']},\n\nWe are pleased to inform you that your ticket (Ref# {$t_ref}) has been resolved by our technician, {$technician_name}.\n\nTicket Details:\nTicket Ref: {$t_ref}\nSubject: {$ticketData['t_subject']}\nMessage: {$ticketData['t_details']}\nStatus: Closed\n\nIf you have any further questions or need additional assistance, please contact our support team at http://localhost/TIMSSS/index.php.\n\nBest regards,\nTixNet System Administrator";

                        // Send the email
                        $mail->send();
                    } catch (Exception $e) {
                        error_log("PHPMailer Error for regular ticket {$t_ref}: " . $mail->ErrorInfo);
                        // Don't throw exception for email failure - continue with ticket closure
                    }
                } else {
                    error_log("No customer email found for regular ticket {$t_ref}");
                }
                
            } else {
                // First get the support ticket details including customer email before closing
                $sqlSelect = "SELECT st.s_ref, st.c_id, c.c_fname, c.c_lname, st.s_subject, st.s_message, st.s_status, c.c_email 
                             FROM tbl_supp_tickets st 
                             JOIN tbl_customer c ON st.c_id = c.c_id 
                             WHERE st.s_ref = ? AND st.technician_username = ? AND st.s_status = 'open'";
                $stmtSelect = $conn->prepare($sqlSelect);
                $stmtSelect->bind_param("ss", $t_ref, $_SESSION['username']);
                $stmtSelect->execute();
                $resultSelect = $stmtSelect->get_result();
                
                if ($resultSelect->num_rows === 0) {
                    throw new Exception("No open support ticket found to close");
                }
                
                $ticketData = $resultSelect->fetch_assoc();
                $stmtSelect->close();
                
                // Insert into closed support tickets table
                $sqlInsert = "INSERT INTO tbl_close_supp (s_ref, c_id, te_technician, s_subject, s_message, s_status, s_date) 
                             VALUES (?, ?, ?, ?, ?, 'Closed', NOW())";
                $stmtInsert = $conn->prepare($sqlInsert);
                $stmtInsert->bind_param("sssss", 
                    $ticketData['s_ref'],
                    $ticketData['c_id'],
                    $technician_name,
                    $ticketData['s_subject'],
                    $ticketData['s_message']
                );
                $stmtInsert->execute();
                $stmtInsert->close();
                
                // Now close the original support ticket
                $sql = "UPDATE tbl_supp_tickets SET s_status = 'Closed' WHERE s_ref = ? AND technician_username = ? AND s_status = 'open'";
                
                // Send email notification to customer
                if (!empty($ticketData['c_email'])) {
                    $mail = new PHPMailer(true);
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'jonwilyammayormita@gmail.com';
                        $mail->Password = 'mqkcqkytlwurwlks';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        // Recipients
                        $mail->setFrom('jonwilyammayormita@gmail.com', 'TixNet System');
                        $mail->addAddress($ticketData['c_email'], $ticketData['c_fname'] . ' ' . $ticketData['c_lname']);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Your Support Ticket Has Been Resolved';
                        $mail->Body = "
                            <html>
                            <head>
                                <title>Support Ticket Resolution Confirmation</title>
                            </head>
                            <body>
                                <p>Dear {$ticketData['c_fname']} {$ticketData['c_lname']},</p>
                                <p>We are pleased to inform you that your support ticket (Ref# {$t_ref}) has been resolved by our technician, {$technician_name}.</p>
                                <p><strong>Ticket Details:</strong></p>
                                <p><strong>Ticket Ref:</strong> {$t_ref}</p>
                                <p><strong>Customer ID:</strong> {$ticketData['c_id']}</p>
                                <p><strong>Subject:</strong> {$ticketData['s_subject']}</p>
                                <p><strong>Message:</strong> {$ticketData['s_message']}</p>
                                <p><strong>Status:</strong> Closed</p>
                                <p>If you have any further questions or need additional assistance, please contact our support team.</p>
                                <p><a href='http://localhost/TIMSSS/index.php'>Visit TixNet System</a></p>
                                <p>Best regards,<br>TixNet System Administrator</p>
                            </body>
                            </html>
                        ";
                        $mail->AltBody = "Dear {$ticketData['c_fname']} {$ticketData['c_lname']},\n\nWe are pleased to inform you that your support ticket (Ref# {$t_ref}) has been resolved by our technician, {$technician_name}.\n\nTicket Details:\nTicket Ref: {$t_ref}\nCustomer ID: {$ticketData['c_id']}\nSubject: {$ticketData['s_subject']}\nMessage: {$ticketData['s_message']}\nStatus: Closed\n\nIf you have any further questions or need additional assistance, please contact our support team at http://localhost/TIMSSS/index.php.\n\nBest regards,\nTixNet System Administrator";

                        // Send the email
                        $mail->send();
                    } catch (Exception $e) {
                        error_log("PHPMailer Error for support ticket {$t_ref}: " . $mail->ErrorInfo);
                        // Don't throw exception for email failure - continue with ticket closure
                    }
                } else {
                    error_log("No customer email found for support ticket {$t_ref}");
                }
            }
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Database prepare error");
            }
            
            $stmt->bind_param("ss", $t_ref, $_SESSION['username']);
            $result = $stmt->execute();
            
            if ($result && $stmt->affected_rows > 0) {
                // Commit transaction
                $conn->commit();
                
                // Log the action
                $logDescription = "Ticket $t_ref closed by technician $firstName $lastName (Type: $ticket_type)";
                $logType = "Technician $firstName $lastName";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
                $logStmt = $conn->prepare($sqlLog);
                if ($logStmt) {
                    $logStmt->bind_param("ss", $logDescription, $logType);
                    $logStmt->execute();
                    $logStmt->close();
                }
                
                $updatedCounts = fetchDashboardCounts($conn, $_SESSION['username']);
                echo json_encode([
                    'success' => true,
                    'message' => 'Ticket closed successfully.',
                    'counts' => $updatedCounts
                ]);
            } else {
                $conn->rollback();
                throw new Exception("No tickets found to close or already closed");
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => 'Failed to close ticket: ' . $e->getMessage()]);
        }
        exit();
    
    } elseif ($action === 'delete') {
        try {
            if ($ticket_type === 'regular') {
                $sql = "DELETE FROM tbl_ticket WHERE t_ref = ? AND technician_username = ?";
            } else {
                $sql = "DELETE FROM tbl_supp_tickets WHERE s_ref = ? AND technician_username = ?";
            }
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Database prepare error");
            }
            
            $stmt->bind_param("ss", $t_ref, $_SESSION['username']);
            $result = $stmt->execute();
            
            if ($result && $stmt->affected_rows > 0) {
                // Log the action
                $logDescription = "Ticket $t_ref deleted by technician $firstName $lastName (Type: $ticket_type)";
                $logType = "Technician $firstName $lastName";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
                $logStmt = $conn->prepare($sqlLog);
                if ($logStmt) {
                    $logStmt->bind_param("ss", $logDescription, $logType);
                    $logStmt->execute();
                    $logStmt->close();
                }
                
                $updatedCounts = fetchDashboardCounts($conn, $_SESSION['username']);
                echo json_encode([
                    'success' => true,
                    'message' => 'Ticket deleted successfully.',
                    'counts' => $updatedCounts
                ]);
            } else {
                throw new Exception("No tickets found to delete");
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to delete ticket: ' . $e->getMessage()]);
        }
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    exit();
}

// Handle search tickets - FIXED SEARCH FUNCTIONALITY
if (isset($_GET['action']) && $_GET['action'] === 'search_tickets') {
    header('Content-Type: application/json');
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'regular';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    try {
        if ($tab === 'regular') {
            $sqlCount = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE technician_username = ? AND t_status = 'open'";
            $sql = "SELECT t_ref, t_aname, t_subject, t_details, t_status FROM tbl_ticket WHERE technician_username = ? AND t_status = 'open'";
            
            if (!empty($searchTerm)) {
                $likeSearch = '%' . $searchTerm . '%';
                $sqlCount .= " AND (t_ref LIKE ? OR t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
                $sql .= " AND (t_ref LIKE ? OR t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
            }
            
            $sql .= " ORDER BY t_ref DESC LIMIT ?, ?";
        } else {
            $sqlCount = "SELECT COUNT(*) AS total FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id WHERE st.technician_username = ? AND st.s_status = 'open'";
            $sql = "SELECT st.s_ref AS t_ref, st.c_id, CONCAT(c.c_fname, ' ', c.c_lname) AS t_aname, st.s_subject AS t_subject, st.s_message AS t_details, st.s_status AS t_status
                    FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id
                    WHERE st.technician_username = ? AND st.s_status = 'open'";
            
            if (!empty($searchTerm)) {
                $likeSearch = '%' . $searchTerm . '%';
                $sqlCount .= " AND (st.s_ref LIKE ? OR c.c_fname LIKE ? OR c.c_lname LIKE ? OR st.s_subject LIKE ? OR st.s_message LIKE ?)";
                $sql .= " AND (st.s_ref LIKE ? OR c.c_fname LIKE ? OR c.c_lname LIKE ? OR st.s_subject LIKE ? OR st.s_message LIKE ?)";
            }
            
            $sql .= " ORDER BY st.s_ref DESC LIMIT ?, ?";
        }

        // Count query
        $stmtCount = $conn->prepare($sqlCount);
        if (!$stmtCount) {
            throw new Exception("Prepare failed for count query");
        }
        
        if (!empty($searchTerm)) {
            if ($tab === 'regular') {
                $stmtCount->bind_param("sssss", $_SESSION['username'], $likeSearch, $likeSearch, $likeSearch, $likeSearch);
            } else {
                $stmtCount->bind_param("ssssss", $_SESSION['username'], $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch);
            }
        } else {
            $stmtCount->bind_param("s", $_SESSION['username']);
        }
        
        $stmtCount->execute();
        $countResult = $stmtCount->get_result();
        $totalRow = $countResult->fetch_assoc();
        $total = $totalRow['total'];
        $totalPages = ceil($total / $limit);
        $stmtCount->close();

        // Data query
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed for ticket query");
        }
        
        if (!empty($searchTerm)) {
            if ($tab === 'regular') {
                $stmt->bind_param("sssssii", $_SESSION['username'], $likeSearch, $likeSearch, $likeSearch, $likeSearch, $offset, $limit);
            } else {
                $stmt->bind_param("ssssssii", $_SESSION['username'], $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $offset, $limit);
            }
        } else {
            $stmt->bind_param("sii", $_SESSION['username'], $offset, $limit);
        }
        
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
                echo "<td class='ellipsis-subject'>" . htmlspecialchars($row['t_subject'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td class='ellipsis-message'>" . htmlspecialchars($row['t_details'], ENT_QUOTES, 'UTF-8') . "</td>";
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
        
        echo json_encode([
            'success' => true,
            'html' => $tableRows,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'counts' => $updatedCounts
        ]);
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    
    $conn->close();
    exit();
}

// Fetch tickets for initial display
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

if ($tab === 'regular') {
    $sql = "SELECT t_ref, t_aname, t_subject, t_details, t_status FROM tbl_ticket WHERE technician_username = ? AND t_status = 'open' ORDER BY t_ref DESC LIMIT ?, ?";
    $sqlCount = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE technician_username = ? AND t_status = 'open'";
} else {
    $sql = "SELECT st.s_ref AS t_ref, st.c_id, CONCAT(c.c_fname, ' ', c.c_lname) AS t_aname, st.s_subject AS t_subject, st.s_message AS t_details, st.s_status AS t_status
            FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id
            WHERE st.technician_username = ? AND st.s_status = 'open' ORDER BY st.s_ref DESC LIMIT ?, ?";
    $sqlCount = "SELECT COUNT(*) AS total FROM tbl_supp_tickets st JOIN tbl_customer c ON st.c_id = c.c_id WHERE st.technician_username = ? AND st.s_status = 'open'";
}

$totalTickets = 0;
$stmtCount = $conn->prepare($sqlCount);
if ($stmtCount) {
    $stmtCount->bind_param("s", $_SESSION['username']);
    $stmtCount->execute();
    $stmtCount->bind_result($totalTickets);
    $stmtCount->fetch();
    $stmtCount->close();
}
$totalPages = ceil($totalTickets / $limit);

$result = false;
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("sii", $_SESSION['username'], $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
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
            <a href="technician_staff.php?action=logout" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                </a>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="dashboard-cards">
                <div class="card">
                    <i class="fas fa-tasks text-yellow-500"></i>
                    <div class="card-content">
                        <h3>Pending Tasks</h3>
                        <p><strong id="pendingTasksCount"><?php echo $pendingTasks; ?></strong></p>
                        <p>Regular Open: <span id="openTicketsCount"><?php echo $openTickets; ?></span> | Support Open: <span id="supportOpenCount"><?php echo $supportOpen; ?></span></p>
                    </div>
                </div>
                <div class="card">
                    <i class="fas fa-ticket-alt text-orange-500"></i>
                    <div class="card-content">
                        <h3>Regular Tickets</h3>
                        <p>Open: <span id="openTicketsCount2"><?php echo $openTickets; ?></span> | Closed: <span id="closedTicketsCount"><?php echo $closedTickets; ?></span></p>
                    </div>
                </div>
                <div class="card">
                    <i class="fas fa-headset text-blue-500"></i>
                    <div class="card-content">
                        <h3>Support Tickets</h3>
                        <p>Open: <span id="supportOpenCount2"><?php echo $supportOpen; ?></span> | Closed: <span id="supportClosedCount"><?php echo $supportClosed; ?></span></p>
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
                    <!-- Fixed Alert Container - ABSOLUTE POSITION -->
                    <div class="alert-container" id="tableAlerts">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
    </div>

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
                                    <th>Ticket Details</th>
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
                                                <td class='ellipsis-subject'>" . htmlspecialchars($row['t_subject'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                <td class='ellipsis-message'>" . htmlspecialchars($row['t_details'], ENT_QUOTES, 'UTF-8') . "</td>
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
                            if ($page > 1) {
                                echo "<a href='?tab=regular&page=" . ($page - 1) . "' class=\"pagination-link\"><i class=\"fas fa-chevron-left\"></i></a>";
                            } else {
                                echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                            }
                            echo "<span class='current-page'>Page $page of $totalPages</span>";
                            if ($page < $totalPages) {
                                echo "<a href='?tab=regular&page=" . ($page + 1) . "' class=\"pagination-link\"><i class=\"fas fa-chevron-right\"></i></a>";
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
                                    <th>Ticket Details</th>
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
                                                <td class='ellipsis-subject'>" . htmlspecialchars($row['t_subject'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                                                <td class='ellipsis-message'>" . htmlspecialchars($row['t_details'], ENT_QUOTES, 'UTF-8') . "</td>
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
                            if ($page > 1) {
                                echo "<a href='?tab=support&page=" . ($page - 1) . "' class=\"pagination-link\"><i class=\"fas fa-chevron-left\"></i></a>";
                            } else {
                                echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                            }
                            echo "<span class='current-page'>Page $page of $totalPages</span>";
                            if ($page < $totalPages) {
                                echo "<a href='?tab=support&page=" . ($page + 1) . "' class=\"pagination-link\"><i class=\"fas fa-chevron-right\"></i></a>";
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
            const alertContainer = document.getElementById('tableAlerts');
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