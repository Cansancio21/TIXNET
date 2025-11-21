<?php
session_start();
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['userId'])) {
    header("Location: index.php");
    exit();
}

// Initialize user variables
$userId = $_SESSION['userId'];
$firstName = 'Unknown';
$lastName = '';
$userType = 'customer';
$isCustomer = true;

// Fetch customer data
$sqlCustomer = "SELECT c_id, c_fname, c_lname FROM tbl_customer WHERE c_id = ?";
$stmt = $conn->prepare($sqlCustomer);
$stmt->bind_param("i", $userId);
$stmt->execute();
$resultCustomer = $stmt->get_result();
if ($resultCustomer->num_rows > 0) {
    $row = $resultCustomer->fetch_assoc();
    $firstName = $row['c_fname'] ?: 'Unknown';
    $lastName = $row['c_lname'] ?: '';
    $customerId = $row['c_id'];
    error_log("Customer fetched: userId=$userId");
} else {
    error_log("Customer not found for userId: $userId");
    $_SESSION['error'] = "Customer not found.";
    header("Location: index.php");
    exit();
}
$stmt->close();

// Set timezone for Philippines
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// FIXED: Use SAME avatar path logic as other pages
$avatarFolder = 'Uploads/avatars/';
$avatarIdentifier = $isCustomer ? $userId : null;
$userAvatar = $avatarFolder . $avatarIdentifier . '.png';
$avatarPath = file_exists($userAvatar) ? $userAvatar . '?' . time() : 'default-avatar.png';
$_SESSION['avatarPath'] = $avatarPath;

// Fetch tickets with conversations for message badges - FIXED: Only show current customer's conversations
$sqlTicketsWithMessages = "SELECT DISTINCT ct.s_ref, ct.c_fname, ct.c_lname, ct.s_subject, ct.s_status, ct.c_id,
                          u.u_fname, u.u_lname, u.u_username,
                          (SELECT COUNT(*) FROM tbl_ticket_conversations 
                           WHERE ticket_ref = ct.s_ref 
                           AND sender_type = 'staff' 
                           AND (is_read IS NULL OR is_read = 0)) as unread_count
                          FROM tbl_customer_ticket ct
                          INNER JOIN tbl_ticket_conversations tc ON ct.s_ref = tc.ticket_ref
                          LEFT JOIN tbl_user u ON tc.sender_id = u.u_id
                          WHERE ct.c_id = ? 
                          AND ct.s_status = 'Declined'
                          AND tc.sender_type = 'staff'
                          GROUP BY ct.s_ref
                          ORDER BY tc.timestamp DESC";

$stmtActiveChats = $conn->prepare($sqlTicketsWithMessages);
$stmtActiveChats->bind_param("i", $customerId);
$stmtActiveChats->execute();
$resultActiveChats = $stmtActiveChats->get_result();
$ticketsWithMessages = [];
$totalUnread = 0;

while ($row = $resultActiveChats->fetch_assoc()) {
    $ticketsWithMessages[] = $row;
    $totalUnread += $row['unread_count'];
}
$stmtActiveChats->close();

// Handle check new messages AJAX
if (isset($_GET['action']) && $_GET['action'] === 'check_new_messages') {
    header('Content-Type: application/json');
    
    $sqlCheckMessages = "SELECT DISTINCT ct.s_ref, ct.c_fname, ct.c_lname, ct.s_subject, ct.s_status, ct.c_id,
                        u.u_fname, u.u_lname, u.u_username,
                        (SELECT COUNT(*) FROM tbl_ticket_conversations 
                         WHERE ticket_ref = ct.s_ref 
                         AND sender_type = 'staff' 
                         AND (is_read IS NULL OR is_read = 0)) as unread_count
                        FROM tbl_customer_ticket ct
                        INNER JOIN tbl_ticket_conversations tcon ON ct.s_ref = tcon.ticket_ref
                        LEFT JOIN tbl_user u ON tcon.sender_id = u.u_id
                        WHERE ct.c_id = ? 
                        AND ct.s_status = 'Declined'
                        AND tcon.sender_type = 'staff'
                        GROUP BY ct.s_ref";
    
    $stmtCheck = $conn->prepare($sqlCheckMessages);
    $stmtCheck->bind_param("i", $customerId);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    
    $tickets = [];
    $totalUnread = 0;
    
    while ($row = $resultCheck->fetch_assoc()) {
        $tickets[] = $row;
        $totalUnread += $row['unread_count'];
    }
    $stmtCheck->close();
    
    echo json_encode([
        'tickets' => $tickets,
        'totalUnread' => $totalUnread
    ]);
    exit();
}

// Handle mark as read AJAX
if (isset($_GET['action']) && $_GET['action'] === 'mark_as_read') {
    $ticket_ref = $_GET['ticket_ref'] ?? '';
    if (!empty($ticket_ref)) {
        // Verify the customer owns this ticket
        $verifySql = "SELECT c_id FROM tbl_customer_ticket WHERE s_ref = ? AND c_id = ?";
        $verifyStmt = $conn->prepare($verifySql);
        $verifyStmt->bind_param("si", $ticket_ref, $customerId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        
        if ($verifyResult->num_rows > 0) {
            // Mark all staff messages as read for this ticket
            $updateSql = "UPDATE tbl_ticket_conversations SET is_read = 1 
                          WHERE ticket_ref = ? AND sender_type = 'staff'";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("s", $ticket_ref);
            
            if ($updateStmt->execute()) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to mark as read']);
            }
            $updateStmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Access denied']);
        }
        $verifyStmt->close();
    }
    exit();
}

// Handle conversation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $ticket_ref = $_POST['ticket_ref'] ?? '';
    $message = trim($_POST['message'] ?? '');
    $sender_type = $_POST['sender_type'] ?? 'customer';
    
    if (!empty($ticket_ref) && !empty($message)) {
        // Verify the customer owns this ticket
        $verifySql = "SELECT c_id FROM tbl_customer_ticket WHERE s_ref = ? AND c_id = ?";
        $verifyStmt = $conn->prepare($verifySql);
        $verifyStmt->bind_param("si", $ticket_ref, $customerId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        
        if ($verifyResult->num_rows > 0) {
            // Store timestamp in Philippines time
            $current_timestamp = date('Y-m-d H:i:s');
            
            $convSql = "INSERT INTO tbl_ticket_conversations (ticket_ref, sender_type, sender_id, message, timestamp) VALUES (?, ?, ?, ?, ?)";
            $convStmt = $conn->prepare($convSql);
            $convStmt->bind_param("ssiss", $ticket_ref, $sender_type, $customerId, $message, $current_timestamp);
            
            if ($convStmt->execute()) {
                echo json_encode(['status' => 'success', 'timestamp' => $current_timestamp]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to send message.']);
            }
            $convStmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'You don\'t have permission to access this ticket.']);
        }
        $verifyStmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Ticket reference and message are required.']);
    }
    exit();
}

// Handle get conversation AJAX - FIXED: Only mark as read when requested
if (isset($_GET['action']) && $_GET['action'] === 'get_conversation') {
    $ticket_ref = $_GET['ticket_ref'] ?? '';
    $mark_as_read = isset($_GET['mark_as_read']) ? (bool)$_GET['mark_as_read'] : false;
    
    if (!empty($ticket_ref)) {
        // Verify the customer owns this ticket
        $verifySql = "SELECT c_id FROM tbl_customer_ticket WHERE s_ref = ? AND c_id = ?";
        $verifyStmt = $conn->prepare($verifySql);
        $verifyStmt->bind_param("si", $ticket_ref, $customerId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        
        if ($verifyResult->num_rows > 0) {
            // ONLY mark as read when specifically requested (when customer opens conversation)
            if ($mark_as_read) {
                $updateSql = "UPDATE tbl_ticket_conversations SET is_read = 1 
                              WHERE ticket_ref = ? AND sender_type = 'staff'";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("s", $ticket_ref);
                $updateStmt->execute();
                $updateStmt->close();
            }
            
            $convSql = "SELECT tc.*, 
                       CASE 
                           WHEN tc.sender_type = 'staff' THEN CONCAT(u.u_fname, ' ', u.u_lname)
                           ELSE CONCAT(c.c_fname, ' ', c.c_lname)
                       END as sender_name,
                       tc.sender_type,
                       u.u_fname as staff_fname,
                       u.u_lname as staff_lname,
                       u.u_username as staff_username,
                       c.c_fname as customer_fname,
                       c.c_lname as customer_lname,
                       c.c_id as customer_id
                       FROM tbl_ticket_conversations tc
                       LEFT JOIN tbl_user u ON tc.sender_id = u.u_id AND tc.sender_type = 'staff'
                       LEFT JOIN tbl_customer c ON tc.sender_id = c.c_id AND tc.sender_type = 'customer'
                       WHERE tc.ticket_ref = ? 
                       ORDER BY CAST(tc.timestamp AS DATETIME) ASC";
            $convStmt = $conn->prepare($convSql);
            $convStmt->bind_param("s", $ticket_ref);
            $convStmt->execute();
            $convResult = $convStmt->get_result();
            
            $conversation = '';
            while ($row = $convResult->fetch_assoc()) {
                // FIXED: Proper timestamp handling with Philippines timezone
                $timestamp = $row['timestamp'];
                
                // Always show actual timestamp in Philippines time
                $displayTime = date('M j, Y g:i A'); // Current Philippines time
                
                if (!empty($timestamp) && $timestamp != '0000-00-00 00:00:00') {
                    $parsedTime = strtotime($timestamp);
                    if ($parsedTime !== false && $parsedTime > 0) {
                        // Convert to Philippines time format
                        $displayTime = date('M j, Y g:i A', $parsedTime);
                    }
                }
                
                if ($row['sender_type'] === 'staff') {
                    // Staff message - LEFT side
                    $senderName = $row['sender_name'] ?: 'Staff';
                    
                    // FIXED: PROPER STAFF AVATAR LOGIC WITH VISIBLE DEFAULT - SAME AS AllCustomersT.php
                    $staffAvatarPath = 'Uploads/avatars/' . $row['staff_username'] . '.png';
                    $cleanStaffAvatarPath = preg_replace('/\?\d+$/', '', $staffAvatarPath);
                    $hasStaffAvatar = file_exists($cleanStaffAvatarPath) && is_file($cleanStaffAvatarPath);
                    
                    $staffAvatarHtml = '<i class="fas fa-user-circle" style="font-size: 24px; color: #28a745;"></i>';
                    if ($hasStaffAvatar) {
                        $staffAvatarHtml = "<img src='$staffAvatarPath?" . time() . "' alt='Staff Avatar' style='width: 100%; height: 100%; object-fit: cover;'>";
                    }
                    
                    $conversation .= "
                    <div class='message staff-message'>
                        <div class='message-avatar staff-avatar'>
                            $staffAvatarHtml
                        </div>
                        <div class='message-content-wrapper'>
                            <div class='message-header'>
                                <strong>$senderName</strong>
                            </div>
                            <div class='message-content'>
                                " . nl2br(htmlspecialchars($row['message'])) . "
                            </div>
                            <div class='message-time'>$displayTime</div>
                        </div>
                    </div>";
                } else {
                    // Customer message - RIGHT side  
                    $senderName = $row['sender_name'] ?: 'You';
                    $customerId = $row['customer_id'];
                    
                    // FIXED: PROPER CUSTOMER AVATAR LOGIC WITH VISIBLE DEFAULT - SAME AS AllCustomersT.php
                    $customerAvatarPath = 'Uploads/avatars/' . $customerId . '.png';
                    $cleanCustomerAvatarPath = preg_replace('/\?\d+$/', '', $customerAvatarPath);
                    $hasCustomerAvatar = file_exists($cleanCustomerAvatarPath) && is_file($cleanCustomerAvatarPath);
                    
                    $customerAvatarHtml = '<i class="fas fa-user-circle" style="font-size: 24px; color: #6c757d;"></i>';
                    if ($hasCustomerAvatar) {
                        $customerAvatarHtml = "<img src='$customerAvatarPath?" . time() . "' alt='Customer Avatar' style='width: 100%; height: 100%; object-fit: cover;'>";
                    }
                    
                    $conversation .= "
                    <div class='message customer-message'>
                        <div class='message-avatar customer-avatar'>
                            $customerAvatarHtml
                        </div>
                        <div class='message-content-wrapper'>
                            <div class='message-header'>
                                <strong>$senderName</strong>
                            </div>
                            <div class='message-content'>
                                " . nl2br(htmlspecialchars($row['message'])) . "
                            </div>
                            <div class='message-time'>$displayTime</div>
                        </div>
                    </div>";
                }
            }
            
            if (empty($conversation)) {
                $conversation = "<div style='text-align: center; color: #666; padding: 20px;'>No messages yet. Start the conversation with staff!</div>";
            }
            
            echo $conversation;
            $convStmt->close();
        } else {
            echo "<div style='text-align: center; color: red; padding: 20px;'>Access denied to this ticket.</div>";
        }
        $verifyStmt->close();
        exit();
    }
}
// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $page = isset($_GET['search_page']) ? max(1, (int)$_GET['search_page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $output = '';

    $whereClauses = ["c_id = ?", "s_status = 'Declined'"];
    $params = [$customerId];
    $paramTypes = 'i';

    if ($searchTerm !== '') {
        $whereClauses[] = "(s_ref LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_remarks LIKE ?)";
        $searchWildcard = "%$searchTerm%";
        $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
        $paramTypes .= 'ssss';
    }

    $whereClause = implode(' AND ', $whereClauses);

    $countSql = "SELECT COUNT(*) as total FROM tbl_customer_ticket WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param($paramTypes, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    $totalPages = ceil($totalRecords / $limit);

    $sql = "SELECT s_ref, s_subject, s_message, s_status, c_id, s_remarks 
            FROM tbl_customer_ticket 
            WHERE $whereClause 
            ORDER BY s_ref ASC 
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($paramTypes . 'ii', ...array_merge($params, [$offset, $limit]));
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $statusClass = 'status-' . strtolower($row['s_status']);
            $accountName = $firstName . ' ' . $lastName;
            $remarks = htmlspecialchars($row['s_remarks'] ?: '', ENT_QUOTES, 'UTF-8');
            $output .= "<tr> 
                <td>" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['s_subject'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . htmlspecialchars($row['s_message'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td class='$statusClass'>" . ucfirst(strtolower($row['s_status'])) . "</td>
                <td class='action-buttons'>
                    <a class='view-btn' href='#' onclick=\"showViewModal('" . htmlspecialchars($row['c_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_message'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_status'], ENT_QUOTES, 'UTF-8') . "', '" . $remarks . "')\" title='View'><i class='fas fa-eye'></i></a>
                </td></tr>";
        }
    } else {
        $output = "<tr><td colspan='6' class='empty-state'>No Declined tickets found.</td></tr>";
    }
    $stmt->close();

    $response = [
        'html' => $output,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'searchTerm' => $searchTerm
    ];
    echo json_encode($response);
    exit();
}

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$totalQuery = "SELECT COUNT(*) AS total FROM tbl_customer_ticket WHERE c_id = ? AND s_status = 'Declined'";
$totalStmt = $conn->prepare($totalQuery);
$totalStmt->bind_param("i", $customerId);
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$total = $totalRow['total'];
$totalPages = ceil($total / $limit);
$totalStmt->close();

// Fetch rejected tickets
$sqlTickets = "SELECT s_ref, s_subject, s_message, s_status, c_id, s_remarks 
               FROM tbl_customer_ticket 
               WHERE c_id = ? AND s_status = 'Declined'
               ORDER BY s_ref ASC 
               LIMIT ?, ?";
$stmtTickets = $conn->prepare($sqlTickets);
$stmtTickets->bind_param("iii", $customerId, $offset, $limit);
$stmtTickets->execute();
$resultTickets = $stmtTickets->get_result();
$stmtTickets->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejected Tickets</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="reject_tickets.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    

</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
        <li><a href="portal.php"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
        <li><a href="suppT.php"><i class="fas fa-ticket-alt icon"></i> <span>Support Tickets</span></a></li>
        <li><a href="reject_ticket.php" class="active"><i class="fas fa-times-circle icon"></i> <span>Declined Tickets</span></a></li>   
        </ul>
        <footer>
            <a href="CustomerP.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>
    <div class="container">
        <div class="upper glass-container">
            <h1>Declined Tickets</h1>
            <div class="user-profile">
            <a href="images.php">
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
        </a>
                <div class="user-details">
                    <span><?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <small><?php echo htmlspecialchars(ucfirst($userType), ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
            </div>
        </div>

       <div class="table-box">
    <h2>Declined Tickets</h2>
    
    <!-- Alert Container - MOVED INSIDE TABLE BOX -->
    <div class="alert-container" id="alertContainer"></div>
    
    <!-- Active Chats Button - BELOW ALERTS -->
    <div class="active-chats-btn-container">
        <button class="active-chats-btn" onclick="showActiveChatsModal()">
            <i class="fas fa-comments"></i>
            Active Chats
            <?php 
            $totalUnread = 0;
            foreach ($ticketsWithMessages as $ticket) {
                $totalUnread += (int)$ticket['unread_count'];
            }
            if ($totalUnread > 0): ?>
                <span class="chat-badge" id="globalChatBadge"><?php echo $totalUnread; ?></span>
            <?php endif; ?>
        </button>
    </div>
    
    <div class="search-container">
        <input type="text" class="search-bar" id="searchInput" placeholder="Search tickets..." onkeyup="debouncedSearchTickets()">
        <span class="search-icon"><i class="fas fa-search"></i></span>
    </div>
    
    <div class="table-wrapper">
       
                <table id="rejected-tickets-table">
                    <thead>
                        <tr>
                            <th>Ticket No</th>
                            <th>Account Name</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="rejected-table-body">
                        <?php
                        if ($resultTickets->num_rows > 0) {
                            while ($row = $resultTickets->fetch_assoc()) {
                                $statusClass = 'status-' . strtolower($row['s_status']);
                                $accountName = $firstName . ' ' . $lastName;
                                $remarks = htmlspecialchars($row['s_remarks'] ?: '', ENT_QUOTES, 'UTF-8');
                                echo "<tr> 
                                    <td>" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "</td> 
                                    <td>" . htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . htmlspecialchars($row['s_subject'], ENT_QUOTES, 'UTF-8') . "</td> 
                                    <td>" . htmlspecialchars($row['s_message'], ENT_QUOTES, 'UTF-8') . "</td> 
                                    <td class='$statusClass'>" . ucfirst(strtolower($row['s_status'])) . "</td>
                                    <td class='action-buttons'>
                                        <a class='view-btn' href='#' onclick=\"showViewModal('" . htmlspecialchars($row['c_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_message'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_status'], ENT_QUOTES, 'UTF-8') . "', '" . $remarks . "')\" title='View'><i class='fas fa-eye'></i></a>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='empty-state'>No Declined tickets found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination" id="pagination">
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

<!-- ORIGINAL MODAL (View Ticket) -->
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

<!-- CHAT MODAL -->
<div id="activeChatsModal" class="chat-modal">
    <div class="chat-modal-content" style="max-width: 550px; height: 75vh;">
        <div class="chat-modal-header">
            <h2><i class="fas fa-comments"></i> Staff Conversations</h2>
            <button class="chat-close-btn" onclick="closeModal('activeChatsModal')">&times;</button>
        </div>
        <div class="chat-modal-body">
            <!-- Horizontal Staff Names -->
        <!-- Horizontal Staff Names -->
<div class="customer-names-container">
    <?php if (!empty($ticketsWithMessages)): ?>
        <?php foreach ($ticketsWithMessages as $index => $ticket): ?>
            <?php
            // FIXED: Proper staff avatar logic for tabs - same as AllCustomersT.php
            $staffAvatarPath = 'Uploads/avatars/' . $ticket['u_username'] . '.png';
            $cleanStaffAvatarPath = preg_replace('/\?\d+$/', '', $staffAvatarPath);
            $hasStaffAvatar = file_exists($cleanStaffAvatarPath) && is_file($cleanStaffAvatarPath);
            ?>
            <div class="customer-name-tab <?php echo $index === 0 ? 'active' : ''; ?>" 
                 onclick="switchConversation('<?php echo $ticket['s_ref']; ?>', this)"
                 data-ticket-ref="<?php echo $ticket['s_ref']; ?>"
                 data-unread-count="<?php echo $ticket['unread_count']; ?>">
                <div class="customer-avatar">
                    <?php if ($hasStaffAvatar): ?>
                        <img src="<?php echo $staffAvatarPath . '?' . time(); ?>" alt="Staff Avatar">
                    <?php else: ?>
                        <i class="fas fa-user-circle"></i>
                    <?php endif; ?>
                </div>
                <span class="customer-name-text"><?php echo $ticket['u_fname'] . ' ' . $ticket['u_lname']; ?></span>
                <?php if ($ticket['unread_count'] > 0): ?>
                    <span class="customer-badge" id="badge-<?php echo $ticket['s_ref']; ?>"><?php echo $ticket['unread_count']; ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align: center; color: #666; width: 100%;">
            No active conversations
        </div>
    <?php endif; ?>
</div>
            
            <!-- Conversation Area -->
            <div class="chat-messages-container">
                <?php if (!empty($ticketsWithMessages)): ?>
                    <?php foreach ($ticketsWithMessages as $index => $ticket): ?>
                        <div class="customer-conversation <?php echo $index === 0 ? 'active' : ''; ?>" id="conversation-<?php echo $ticket['s_ref']; ?>">
                            <div class="conversation-messages" id="messages-<?php echo $ticket['s_ref']; ?>">
                                <div style="text-align: center; color: #666; padding: 20px;">
                                    <i class="fas fa-spinner fa-spin"></i> Loading messages...
                                </div>
                            </div>
                            
                            <div class="message-input-container">
                                <form class="conversation-form" method="POST" data-ticket-ref="<?php echo $ticket['s_ref']; ?>">
                                    <input type="hidden" name="ticket_ref" value="<?php echo $ticket['s_ref']; ?>">
                                    <input type="hidden" name="action" value="send_message">
                                    <input type="hidden" name="sender_type" value="customer">
                                    <textarea name="message" placeholder="Type your response to <?php echo htmlspecialchars($ticket['u_fname']); ?>..." required></textarea>
                                    <div class="button-container">
                                        <button type="submit" class="send-btn">
                                            <i class="fas fa-paper-plane"></i> Send to <?php echo htmlspecialchars($ticket['u_fname']); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-chats">
                        <i class="fas fa-comment-slash"></i>
                        <p>No active conversations</p>
                        <p style="font-size: 12px; margin-top: 10px;">Conversations will appear here when staff sends you messages</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Store unread state in session storage
function saveUnreadState(totalUnread) {
    sessionStorage.setItem('reject_ticket_unread', totalUnread);
}

function getUnreadState() {
    return parseInt(sessionStorage.getItem('reject_ticket_unread')) || 0;
}

function updateBadgeDisplay() {
    const totalUnread = getUnreadState();
    const activeChatsBtn = document.querySelector('.active-chats-btn');
    let globalBadge = document.getElementById('globalChatBadge');
    
    if (totalUnread > 0) {
        if (!globalBadge && activeChatsBtn) {
            // Create global badge if it doesn't exist
            globalBadge = document.createElement('span');
            globalBadge.className = 'chat-badge';
            globalBadge.id = 'globalChatBadge';
            globalBadge.textContent = totalUnread;
            activeChatsBtn.appendChild(globalBadge);
        } else if (globalBadge) {
            globalBadge.textContent = totalUnread;
        }
    } else if (globalBadge) {
        globalBadge.remove();
    }
}

// Update badges from server response
function updateBadgesFromServer(tickets, totalUnread) {
    console.log('Updating badges from server:', { tickets, totalUnread });
    
    // Save to session storage
    saveUnreadState(totalUnread);
    
    // Update display
    updateBadgeDisplay();

    // Update individual staff badges
    if (tickets && Array.isArray(tickets)) {
        tickets.forEach(ticket => {
            const badgeElement = document.getElementById(`badge-${ticket.s_ref}`);
            if (ticket.unread_count > 0) {
                if (!badgeElement) {
                    // Find the tab and add badge
                    const tab = document.querySelector(`[data-ticket-ref="${ticket.s_ref}"]`);
                    if (tab) {
                        const badge = document.createElement('span');
                        badge.className = 'customer-badge';
                        badge.id = `badge-${ticket.s_ref}`;
                        badge.textContent = ticket.unread_count;
                        tab.appendChild(badge);
                    }
                } else {
                    badgeElement.textContent = ticket.unread_count;
                }
            } else if (badgeElement) {
                badgeElement.remove();
            }
        });
    }
}

// DEBUG FUNCTION: Check badge status
function debugBadges() {
    console.log('=== BADGE DEBUG ===');
    console.log('Global Badge:', document.getElementById('globalChatBadge')?.textContent || 'None');
    console.log('Individual Badges:');
    document.querySelectorAll('.customer-badge').forEach(badge => {
        console.log('-', badge.id, ':', badge.textContent);
    });
    console.log('Session Storage:', getUnreadState());
    console.log('==================');
}

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

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function showViewModal(c_id, accountName, s_ref, s_subject, s_message, s_status, s_remarks) {
    const content = `
        <p><strong>Customer ID:</strong> ${c_id}</p>
        <p><strong>Account Name:</strong> ${accountName}</p>
        <p><strong>Ticket Ref:</strong> ${s_ref}</p>
        <p><strong>Subject:</strong> ${s_subject}</p>
        <p><strong>Message:</strong> ${s_message}</p>
        <p><strong>Staff Remarks:</strong> ${s_remarks || 'None'}</p>
        <p><strong>Status:</strong> ${s_status}</p>
    `;
    document.getElementById('viewContent').innerHTML = content;
    document.getElementById('viewModal').style.display = 'block';
}

function showActiveChatsModal() {
    document.getElementById('activeChatsModal').style.display = 'block';
    
    // Load first conversation when modal opens and clear its badge
    <?php if (!empty($ticketsWithMessages)): ?>
        const firstTicketRef = '<?php echo $ticketsWithMessages[0]['s_ref']; ?>';
        loadConversation(firstTicketRef, true); // Mark as read when modal opens
        clearBadge(firstTicketRef);
        
        // Also mark as read on server
        markAsRead(firstTicketRef);
    <?php endif; ?>
    
    // Re-initialize chat forms when modal opens
    setTimeout(initializeChatForms, 100);
}

function switchConversation(ticketRef, element) {
    // Remove active class from all tabs
    document.querySelectorAll('.customer-name-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Add active class to clicked tab
    element.classList.add('active');
    
    // Hide all conversations
    document.querySelectorAll('.customer-conversation').forEach(conv => {
        conv.classList.remove('active');
    });
    
    // Show selected conversation
    const conversation = document.getElementById(`conversation-${ticketRef}`);
    if (conversation) {
        conversation.classList.add('active');
    }
    
    // Load conversation if not already loaded - MARK AS READ when customer actively clicks
    loadConversation(ticketRef, true);
    
    // Clear the badge for this conversation
    clearBadge(ticketRef);
}

function loadConversation(ticketRef, markAsRead = false) {
    const messagesContainer = document.getElementById(`messages-${ticketRef}`);
    if (!messagesContainer) return;
    
    let url = `reject_ticket.php?action=get_conversation&ticket_ref=${encodeURIComponent(ticketRef)}&t=${new Date().getTime()}`;
    if (markAsRead) {
        url += '&mark_as_read=1';
    }
    
    const xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            messagesContainer.innerHTML = xhr.responseText;
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Mark messages as read and clear badge only when requested
            if (markAsRead) {
                markAsRead(ticketRef);
            }
        }
    };
    xhr.send();
}

function markAsRead(ticketRef) {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `reject_ticket.php?action=mark_as_read&ticket_ref=${encodeURIComponent(ticketRef)}`, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            clearBadge(ticketRef);
        }
    };
    xhr.send();
}

function clearBadge(ticketRef) {
    // Remove badge from staff tab
    const badgeElement = document.getElementById(`badge-${ticketRef}`);
    if (badgeElement) {
        badgeElement.remove();
    }
    
    // Update global badge count in session storage
    const currentUnread = getUnreadState();
    if (currentUnread > 0) {
        saveUnreadState(currentUnread - 1);
        updateBadgeDisplay();
    }
}

function updateGlobalBadge() {
    let totalUnread = 0;
    document.querySelectorAll('.customer-badge').forEach(badge => {
        totalUnread += parseInt(badge.textContent) || 0;
    });
    
    // Save to session storage
    saveUnreadState(totalUnread);
    updateBadgeDisplay();
}

function initializeChatForms() {
    document.querySelectorAll('.conversation-form').forEach(form => {
        // Remove existing event listeners to prevent duplicates
        form.removeEventListener('submit', handleFormSubmit);
        // Add new event listener
        form.addEventListener('submit', handleFormSubmit);
    });
    console.log('Chat forms initialized');
}

function handleFormSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const ticketRef = form.getAttribute('data-ticket-ref');
    const messageText = form.querySelector('textarea').value.trim();
    
    if (!messageText) {
        showNotification('Please enter a message', 'error');
        return;
    }
    
    // Show sending state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    submitBtn.disabled = true;
    
    // Add the message immediately to the UI (optimistic update)
    const messagesContainer = document.getElementById(`messages-${ticketRef}`);
    const tempId = 'temp-' + Date.now();
    
    // FIXED: Use same avatar logic as PHP with proper default icon
    const customerAvatarElement = document.querySelector('.user-icon');
    let customerAvatarHtml = '<i class="fas fa-user-circle" style="font-size: 24px; color: #6c757d;"></i>';
    if (customerAvatarElement) {
        const customerAvatarImg = customerAvatarElement.querySelector('img');
        if (customerAvatarImg) {
            customerAvatarHtml = `<img src="${customerAvatarImg.src}" alt="Customer Avatar" style="width: 100%; height: 100%; object-fit: cover;">`;
        }
    }
    
    const tempMessage = `
        <div id="${tempId}" class="message customer-message">
            <div class="message-avatar customer-avatar">
                ${customerAvatarHtml}
            </div>
            <div class="message-content-wrapper">
                <div class="message-header">
                    <strong>You</strong>
                </div>
                <div class="message-content">
                    ${messageText.replace(/\n/g, '<br>')}
                </div>
                <div class="message-time">Sending...</div>
            </div>
        </div>`;
    
    if (messagesContainer) {
        messagesContainer.innerHTML += tempMessage;
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Clear the textarea
    form.querySelector('textarea').value = '';
    
    // Send the message via AJAX
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'reject_ticket.php', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.status === 'success') {
                        // SUCCESS: Update the temporary message
                        const tempElement = document.getElementById(tempId);
                        if (tempElement) {
                            const timeElement = tempElement.querySelector('.message-time');
                            if (timeElement) {
                                timeElement.textContent = 'Sent';
                                timeElement.style.color = '#28a745';
                            }
                            
                            // Remove the temp ID so it becomes permanent
                            tempElement.removeAttribute('id');
                            
                            // Reload conversation after 1 second to get correct timestamp from PHP
                            setTimeout(() => {
                                loadConversation(ticketRef, false);
                            }, 1000);
                        }
                        showNotification('Message sent successfully!', 'success');
                    } else {
                        throw new Error(response.message || 'Failed to send message');
                    }
                } catch (e) {
                    // If response is not JSON (legacy behavior), mark as sent and reload
                    const tempElement = document.getElementById(tempId);
                    if (tempElement) {
                        const timeElement = tempElement.querySelector('.message-time');
                        timeElement.textContent = 'Sent';
                        timeElement.style.color = '#28a745';
                        tempElement.removeAttribute('id');
                        
                        setTimeout(() => {
                            loadConversation(ticketRef, false);
                        }, 1000);
                    }
                    showNotification('Message sent successfully!', 'success');
                }
            } else {
                // ERROR: Mark as failed
                const tempElement = document.getElementById(tempId);
                if (tempElement) {
                    const timeElement = tempElement.querySelector('.message-time');
                    timeElement.innerHTML = 'Failed - <a href="#" onclick="retrySendMessage(this)">Retry</a>';
                    timeElement.style.color = '#dc3545';
                }
                showNotification('Failed to send message. Please try again.', 'error');
            }
            
            // Re-enable button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    };
    
    xhr.send(formData);
}

function retrySendMessage(element) {
    // Find the message and resend
    const messageElement = element.closest('.message');
    const messageContent = messageElement.querySelector('.message-content').textContent;
    const form = messageElement.closest('.customer-conversation').querySelector('.conversation-form');
    
    // Set the message in the form and submit
    form.querySelector('textarea').value = messageContent;
    form.dispatchEvent(new Event('submit'));
    
    // Remove the failed message
    messageElement.remove();
}

// Add periodic message checking
function checkForNewMessages() {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'reject_ticket.php?action=check_new_messages', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                console.log('New messages check response:', response);
                updateBadgesFromServer(response.tickets, response.totalUnread);
            } catch (e) {
                console.error('Error checking for new messages:', e);
            }
        }
    };
    xhr.send();
}

// Check for new messages every 30 seconds
setInterval(checkForNewMessages, 30000);

let searchTimeout;
function debouncedSearchTickets() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const searchTerm = document.getElementById('searchInput').value;
        fetchTickets(1, searchTerm);
    }, 300);
}

function fetchTickets(page, searchTerm = '') {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `reject_ticket.php?action=search&search_page=${page}&search=${encodeURIComponent(searchTerm)}`, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            document.getElementById('rejected-table-body').innerHTML = response.html;
            updatePagination(response.currentPage, response.totalPages, searchTerm);
        }
    };
    xhr.send();
}

function updatePagination(currentPage, totalPages, searchTerm) {
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';
    
    const prevLink = currentPage > 1 
        ? `<a href="#" class="pagination-link" onclick="fetchTickets(${currentPage - 1}, '${searchTerm}'); return false;"><i class="fas fa-chevron-left"></i></a>`
        : `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    
    const nextLink = currentPage < totalPages 
        ? `<a href="#" class="pagination-link" onclick="fetchTickets(${currentPage + 1}, '${searchTerm}'); return false;"><i class="fas fa-chevron-right"></i></a>`
        : `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    
    pagination.innerHTML = `
        ${prevLink}
        <span class="current-page">Page ${currentPage} of ${totalPages}</span>
        ${nextLink}
    `;
}

document.addEventListener('DOMContentLoaded', function() {
    const upperHeader = document.querySelector('.upper');
    const mobileMenuToggle = document.createElement('button');
    mobileMenuToggle.className = 'mobile-menu-toggle';
    mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';
    mobileMenuToggle.setAttribute('aria-label', 'Toggle menu');
    
    // Insert the toggle button at the beginning of the header
    upperHeader.insertBefore(mobileMenuToggle, upperHeader.firstChild);
    
    const sidebar = document.querySelector('.sidebar');
    
    mobileMenuToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        // Toggle menu icon
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('active')) {
            icon.className = 'fas fa-times';
        } else {
            icon.className = 'fas fa-bars';
        }
    });
    
    // Close sidebar when clicking on a link (on mobile)
    const sidebarLinks = document.querySelectorAll('.sidebar a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                mobileMenuToggle.querySelector('i').className = 'fas fa-bars';
            }
        });
    });
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
            if (!sidebar.contains(event.target) && !mobileMenuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
                mobileMenuToggle.querySelector('i').className = 'fas fa-bars';
            }
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            mobileMenuToggle.querySelector('i').className = 'fas fa-bars';
        }
    });
    
    // Restore badge state from session storage immediately
    updateBadgeDisplay();
    
    // Load conversations for active tabs (don't mark as read on page load)
    const activeTabs = document.querySelectorAll('.customer-name-tab.active');
    activeTabs.forEach(tab => {
        const ticketRef = tab.getAttribute('data-ticket-ref');
        if (ticketRef) {
            loadConversation(ticketRef, false); // DON'T mark as read on page load
        }
    });

    // Initialize conversation forms
    initializeChatForms();
    
    // Start checking for new messages
    checkForNewMessages();
    
    // Debug initial state
    console.log('DOM loaded - Initial badge state from session:', getUnreadState());
    debugBadges();
});

// Force badge check when page becomes visible
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        checkForNewMessages();
    }
});

// Manual badge check function (for testing)
function manualBadgeCheck() {
    console.log('Manual badge check triggered');
    checkForNewMessages();
}

// Handle session error notifications
window.addEventListener('DOMContentLoaded', () => {
    <?php if (isset($_SESSION['error'])): ?>
        showNotification("<?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>", 'error');
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['message'])): ?>
        showNotification("<?php echo htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); ?>", 'success');
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
});
</script>
</body>
</html> 