<?php
session_start();
include 'db.php';

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
    header("Location: AllCustomersT.php?page_customer=1");
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
} else {
    error_log("User not found for username: {$_SESSION['username']}");
    $_SESSION['error'] = "User not found.";
    header("Location: AllCustomersT.php?page_customer=1");
    exit();
}
$stmt->close();

// Fetch customers for filter dropdown
$sqlCustomers = "SELECT c_id, c_fname, c_lname FROM tbl_customer ORDER BY c_fname, c_lname";
$resultCustomers = $conn->query($sqlCustomers);
$customers = [];
if ($resultCustomers && $resultCustomers->num_rows > 0) {
    while ($row = $resultCustomers->fetch_assoc()) {
        $customers[] = [
            'c_id' => $row['c_id'],
            'full_name' => $row['c_fname'] . ' ' . $row['c_lname'],
            'first_name' => $row['c_fname'],
            'last_name' => $row['c_lname']
        ];
    }
} else {
    error_log("No customers found in tbl_customer: " . ($resultCustomers ? "No rows" : $conn->error));
}

// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $accountFilter = isset($_GET['account_filter']) ? trim($_GET['account_filter']) : '';
    $page = isset($_GET['search_page']) ? max(1, (int)$_GET['search_page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $output = '';

    $whereClauses = ["s_status = 'Pending'"];
    $params = [];
    $paramTypes = '';

    if ($searchTerm !== '') {
        $whereClauses[] = "(s_ref LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR CONCAT(c_fname, ' ', c_lname) LIKE ?)";
        $searchWildcard = "%$searchTerm%";
        $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
        $paramTypes .= 'ssss';
    }

    if ($accountFilter !== '') {
        $whereClauses[] = "CONCAT(c_fname, ' ', c_lname) = ?";
        $params[] = $accountFilter;
        $paramTypes .= 's';
    }

    $whereClause = implode(' AND ', $whereClauses);

    $countSql = "SELECT COUNT(*) as total FROM tbl_customer_ticket WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        error_log("Prepare failed for count query: " . $conn->error);
        echo json_encode(['error' => 'Database error']);
        exit();
    }
    if ($paramTypes) {
        $countStmt->bind_param($paramTypes, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    $totalPages = ceil($totalRecords / $limit);

    $sql = "SELECT s_ref, c_id, c_fname, c_lname, s_subject, s_message, s_status 
            FROM tbl_customer_ticket 
            WHERE $whereClause 
            ORDER BY s_ref ASC 
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for ticket query: " . $conn->error);
        echo json_encode(['error' => 'Database error']);
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
            $statusClass = 'status-' . strtolower($row['s_status']);
            $customerName = htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8');
            $output .= "<tr> 
                <td>" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . htmlspecialchars($row['c_id'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>$customerName</td> 
                <td>" . htmlspecialchars($row['s_subject'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . htmlspecialchars($row['s_message'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td class='$statusClass'>" . ucfirst(strtolower($row['s_status'])) . "</td>
                <td class='action-buttons'>
                    <a class='view-btn' href='#' onclick=\"showCustomerViewModal('" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['c_id'], ENT_QUOTES, 'UTF-8') . "', '$customerName', '" . htmlspecialchars($row['s_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_message'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_status'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                    <a class='action-btn check' href='#' onclick=\"approveTicket('" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "')\" title='Approve'><i class='fas fa-check'></i></a>
                    <a class='action-btn x' href='#' onclick=\"showRejectModal('" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "')\" title='Reject'><i class='fas fa-times'></i></a>
                </td></tr>";
        }
    } else {
        $output = "<tr><td colspan='7' style='text-align: center;'>No customer tickets found.</td></tr>";
    }
    $stmt->close();

    $response = [
        'html' => $output,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'searchTerm' => $searchTerm,
        'accountFilter' => $accountFilter
    ];
    echo json_encode($response);
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pageCustomer = isset($_GET['page_customer']) ? max(1, (int)$_GET['page_customer']) : 1;

    if (isset($_POST['approve_ticket'])) {
        $ticket_ref = $_POST['ticket_ref'];
        error_log("Approving ticket s_ref: $ticket_ref by staff: $firstName $lastName");

        // Start a transaction
        $conn->begin_transaction();

        try {
            // Fetch the pending ticket
            $sql = "SELECT s_ref, c_id, c_fname, c_lname, s_subject, s_message FROM tbl_customer_ticket WHERE s_ref = ? AND s_status = 'Pending'";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed for ticket fetch: " . $conn->error);
            }
            $stmt->bind_param("s", $ticket_ref);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Ticket not found or not pending.");
            }
            $ticket = $result->fetch_assoc();
            $stmt->close();

            // Check for duplicate s_ref in tbl_supp_tickets
            $sqlCheck = "SELECT s_ref FROM tbl_supp_tickets WHERE s_ref = ?";
            $stmtCheck = $conn->prepare($sqlCheck);
            if (!$stmtCheck) {
                throw new Exception("Prepare failed for duplicate check in tbl_supp_tickets: " . $conn->error);
            }
            $stmtCheck->bind_param("s", $ticket_ref);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();
            if ($resultCheck->num_rows > 0) {
                throw new Exception("Ticket reference already exists in support tickets.");
            }
            $stmtCheck->close();

            // Insert into tbl_supp_tickets
            $new_status = 'Open';
            $sqlInsert = "INSERT INTO tbl_supp_tickets (c_id, c_fname, c_lname, s_ref, s_subject, s_message, s_status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmtInsert = $conn->prepare($sqlInsert);
            if (!$stmtInsert) {
                throw new Exception("Prepare failed for inserting into tbl_supp_tickets: " . $conn->error);
            }
            $stmtInsert->bind_param("issssss", $ticket['c_id'], $ticket['c_fname'], $ticket['c_lname'], 
                                    $ticket['s_ref'], $ticket['s_subject'], $ticket['s_message'], $new_status);
            if (!$stmtInsert->execute()) {
                throw new Exception("Error inserting into tbl_supp_tickets: " . $stmtInsert->error);
            }
            error_log("Ticket inserted into tbl_supp_tickets with s_ref: $ticket_ref");
            $stmtInsert->close();

            // Update tbl_customer_ticket
            $sqlUpdate = "UPDATE tbl_customer_ticket SET s_status = 'Approved' WHERE s_ref = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            if (!$stmtUpdate) {
                throw new Exception("Prepare failed for updating tbl_customer_ticket: " . $conn->error);
            }
            $stmtUpdate->bind_param("s", $ticket_ref);
            if (!$stmtUpdate->execute()) {
                throw new Exception("Error updating customer ticket status: " . $stmtUpdate->error);
            }
            if ($stmtUpdate->affected_rows === 0) {
                throw new Exception("No ticket updated. Ticket may not exist or status already changed.");
            }
            $stmtUpdate->close();

            // Log the action
            $logDescription = "Staff $firstName $lastName approved customer ticket {$ticket['s_ref']} for customer {$ticket['c_fname']} {$ticket['c_lname']}";
            $logType = "Staff $firstName $lastName";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            if (!$stmtLog) {
                throw new Exception("Prepare failed for logging: " . $conn->error);
            }
            $stmtLog->bind_param("ss", $logDescription, $logType);
            if (!$stmtLog->execute()) {
                throw new Exception("Error logging action: " . $stmtLog->error);
            }
            $stmtLog->close();

            // Commit the transaction
            $conn->commit();
            $_SESSION['message'] = "Ticket approved successfully!";
        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
            error_log("Approval failed for s_ref $ticket_ref: " . $e->getMessage());
        }
        header("Location: AllCustomersT.php?page_customer=$pageCustomer");
        exit();
    } elseif (isset($_POST['reject_ticket'])) {
        $ticket_ref = $_POST['ticket_ref'];
        $remarks = trim($_POST['s_remarks'] ?? '');
        error_log("Rejecting ticket s_ref: $ticket_ref with remarks: $remarks");

        // Start a transaction
        $conn->begin_transaction();

        try {
            // Fetch ticket details for insertion and logging
            $sqlFetch = "SELECT s_ref, c_id, c_fname, c_lname, s_subject, s_message 
                         FROM tbl_customer_ticket 
                         WHERE s_ref = ? AND s_status = 'Pending'";
            $stmtFetch = $conn->prepare($sqlFetch);
            if (!$stmtFetch) {
                throw new Exception("Prepare failed for fetching ticket: " . $conn->error);
            }
            $stmtFetch->bind_param("s", $ticket_ref);
            $stmtFetch->execute();
            $resultFetch = $stmtFetch->get_result();
            if ($resultFetch->num_rows === 0) {
                throw new Exception("Ticket not found or not pending.");
            }
            $ticket = $resultFetch->fetch_assoc();
            $stmtFetch->close();

            // Check for duplicate s_ref in tbl_reject_ticket
            $sqlCheck = "SELECT s_ref FROM tbl_reject_ticket WHERE s_ref = ?";
            $stmtCheck = $conn->prepare($sqlCheck);
            if (!$stmtCheck) {
                throw new Exception("Prepare failed for duplicate check in tbl_reject_ticket: " . $conn->error);
            }
            $stmtCheck->bind_param("s", $ticket_ref);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();
            if ($resultCheck->num_rows > 0) {
                throw new Exception("Ticket reference already exists in rejected tickets.");
            }
            $stmtCheck->close();

            // Insert into tbl_reject_ticket
            $rejectedStatus = 'Rejected';
            $sqlInsert = "INSERT INTO tbl_reject_ticket (s_ref, c_id, c_fname, c_lname, s_subject, s_message, s_status, s_remarks) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtInsert = $conn->prepare($sqlInsert);
            if (!$stmtInsert) {
                throw new Exception("Prepare failed for inserting into tbl_reject_ticket: " . $conn->error);
            }
            $stmtInsert->bind_param("sissssss", $ticket['s_ref'], $ticket['c_id'], $ticket['c_fname'], $ticket['c_lname'], 
                                    $ticket['s_subject'], $ticket['s_message'], $rejectedStatus, $remarks);
            if (!$stmtInsert->execute()) {
                throw new Exception("Error inserting into tbl_reject_ticket: " . $stmtInsert->error);
            }
            error_log("Ticket inserted into tbl_reject_ticket with s_ref: $ticket_ref");
            $stmtInsert->close();

            // Update tbl_customer_ticket
            $sqlUpdate = "UPDATE tbl_customer_ticket SET s_status = 'Rejected' WHERE s_ref = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            if (!$stmtUpdate) {
                throw new Exception("Prepare failed for updating tbl_customer_ticket: " . $conn->error);
            }
            $stmtUpdate->bind_param("s", $ticket_ref);
            if (!$stmtUpdate->execute()) {
                throw new Exception("Error updating customer ticket status: " . $stmtUpdate->error);
            }
            if ($stmtUpdate->affected_rows === 0) {
                throw new Exception("No ticket updated. Ticket may not exist or status already changed.");
            }
            $stmtUpdate->close();

            // Log the action
            $logDescription = "Staff {$firstName} {$lastName} rejected customer ticket {$ticket['s_ref']} for customer {$ticket['c_fname']} {$ticket['c_lname']} with remarks: $remarks";
            $logType = "Staff {$firstName} {$lastName}";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            if (!$stmtLog) {
                throw new Exception("Prepare failed for logging: " . $conn->error);
            }
            $stmtLog->bind_param("ss", $logDescription, $logType);
            if (!$stmtLog->execute()) {
                throw new Exception("Error logging action: " . $stmtLog->error);
            }
            $stmtLog->close();

            // Commit the transaction
            $conn->commit();
            $_SESSION['message'] = "Ticket rejected successfully!";
        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
            error_log("Rejection failed for s_ref $ticket_ref: " . $e->getMessage());
        }
        header("Location: AllCustomersT.php?page_customer=$pageCustomer");
        exit();
    }

    header("Location: AllCustomersT.php?page_customer=$pageCustomer");
    exit();
}

// Pagination setup
$limit = 10;
$pageCustomer = isset($_GET['page_customer']) ? max(1, (int)$_GET['page_customer']) : 1;
$offsetCustomer = ($pageCustomer - 1) * $limit;
$totalCustomerQuery = "SELECT COUNT(*) AS total FROM tbl_customer_ticket WHERE s_status = 'Pending'";
$totalCustomerResult = $conn->query($totalCustomerQuery);
if (!$totalCustomerResult) {
    error_log("Error in total customer query: " . $conn->error);
    $_SESSION['error'] = "Database error occurred.";
    header("Location: AllCustomersT.php?page_customer=$pageCustomer");
    exit();
}
$totalCustomerRow = $totalCustomerResult->fetch_assoc();
$totalCustomer = $totalCustomerRow['total'];
$totalCustomerPages = ceil($totalCustomer / $limit);

// Fetch customer tickets
$sqlCustomer = "SELECT s_ref, c_id, c_fname, c_lname, s_subject, s_message, s_status 
                FROM tbl_customer_ticket 
                WHERE s_status = 'Pending'
                ORDER BY s_ref ASC 
                LIMIT ?, ?";
$stmtCustomer = $conn->prepare($sqlCustomer);
if (!$stmtCustomer) {
    error_log("Prepare failed for customer tickets query: " . $conn->error);
    $_SESSION['error'] = "Database error occurred.";
    header("Location: AllCustomersT.php?page_customer=$pageCustomer");
    exit();
}
$stmtCustomer->bind_param("ii", $offsetCustomer, $limit);
$stmtCustomer->execute();
$resultCustomer = $stmtCustomer->get_result();
$stmtCustomer->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | Customer Tickets</title>
    <link rel="stylesheet" href="AllCustomer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <style>
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
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .action-btn {
            padding: 6px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: background-color 0.2s;
            text-align: center;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
        }
        .action-btn.check {
            background-color: #28a745;
        }
        .action-btn.x {
            background-color: #dc3545;
        }
        .action-btn:hover:not(:disabled) {
            opacity: 0.8;
        }
        .modal-form textarea {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            height: 100px;
            resize: vertical;
        }
        @media (max-width: 600px) {
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            .action-btn {
                width: 25px;
                height: 25px;
                font-size: 12px;
            }
            .alert {
                font-size: 12px;
                padding: 8px 12px;
            }
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
            <li><a href="AllCustomersT.php" class="active"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers Ticket</span></a></li>
            <li><a href="customersT.php"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers</span></a></li>
            <li><a href="borrowedStaff.php"><img src="image/borrowed.png" alt="Borrowed Assets" class="icon" /> <span>Borrowed Assets</span></a></li>
            <li><a href="addC.php"><img src="image/add.png" alt="Add Customer" class="icon" /> <span>Add Customer</span></a></li>
            <li><a href="AssignTech.php"><img src="image/technician.png" alt="Technicians" class="icon" /> <span>Technicians</span></a></li>
            <li><a href="Payments.php"><img src="image/transactions.png" alt="Payment Transactions" class="icon" /> <span>Payment Transactions</span></a></li>

        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>

    <div class="container">
        <div class="upper">
            <h1>Customer Tickets</h1>
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

        <div class="alert-container" id="alertContainer"></div>

        <div class="table-box glass-container">
            <h2>Ticket Approval</h2>
            <div class="username"></div>
            <div class="customer-tickets active">
                <table id="customer-tickets-table">
                    <thead>
                        <tr>
                            <th>Ticket No</th>
                            <th>ID</th>
                            <th>Customer <button class="filter-btn" onclick="showAccountFilterModal('customer')" title="Filter by Customer Name"><i class='bx bx-filter'></i></button></th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="customer-table-body">
                        <?php
                        if ($resultCustomer->num_rows > 0) {
                            while ($row = $resultCustomer->fetch_assoc()) {
                                $statusClass = 'status-' . strtolower($row['s_status']);
                                $customerName = htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown', ENT_QUOTES, 'UTF-8');
                                echo "<tr> 
                                        <td>" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_id'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>$customerName</td> 
                                        <td>" . htmlspecialchars($row['s_subject'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['s_message'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td class='$statusClass'>" . ucfirst(strtolower($row['s_status'])) . "</td>
                                        <td class='action-buttons'>
                                            <a class='view-btn' href='#' onclick=\"showCustomerViewModal('" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['c_id'], ENT_QUOTES, 'UTF-8') . "', '$customerName', '" . htmlspecialchars($row['s_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_message'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_status'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                            <a class='action-btn check' href='#' onclick=\"approveTicket('" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "')\" title='Approve'><i class='fas fa-check'></i></a>
                                            <a class='action-btn x' href='#' onclick=\"showRejectModal('" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "')\" title='Decline'><i class='fas fa-times'></i></a>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' style='text-align: center;'>No customer tickets found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <div class="pagination" id="customer-pagination">
                    <?php if ($pageCustomer > 1): ?>
                        <a href="?page_customer=<?php echo $pageCustomer - 1; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <span class="current-page">Page <?php echo $pageCustomer; ?> of <?php echo $totalCustomerPages; ?></span>
                    <?php if ($pageCustomer < $totalCustomerPages): ?>
                        <a href="?page_customer=<?php echo $pageCustomer + 1; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customer View Ticket Modal -->
<div id="customerViewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Customer Ticket Details</h2>
        </div>
        <div id="customerViewContent" class="view-details"></div>
        <div class="modal-footer">
            <button class="modal-btn cancel" onclick="closeModal('customerViewModal')">Close</button>
        </div>
    </div>
</div>

<!-- Reject Ticket Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Decline Ticket</h2>
        </div>
        <form method="POST" id="rejectForm" class="modal-form">
            <input type="hidden" name="ticket_ref" id="rejectTicketRef">
            <label for="s_remarks">Remarks:</label>
            <textarea name="s_remarks" id="s_remarks" placeholder="Enter reason for Decline" required></textarea>
            <input type="hidden" name="reject_ticket" value="1">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Decline</button>
            </div>
        </form>
    </div>
</div>

<!-- Account Filter Modal -->
<div id="accountFilterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Filter by Customer Name</h2>
        </div>
        <form id="accountFilterForm" class="modal-form">
            <input type="hidden" name="tab" id="accountFilterTab" value="customer">
            <label for="account_filter">Select Customer Name</label>
            <select name="account_filter" id="account_filter">
                <option value="">All Customers</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('accountFilterModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Apply Filter</button>
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

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function showCustomerViewModal(s_ref, s_ref2, c_id, customerName, s_subject, s_message, s_status) {
    const content = `
        <p><strong>Ticket Ref:</strong> ${s_ref}</p>
        <p><strong>Customer ID:</strong> ${c_id}</p>
        <p><strong>Customer Name:</strong> ${customerName}</p>
        <p><strong>Subject:</strong> ${s_subject}</p>
        <p><strong>Message:</strong> ${s_message}</p>
        <p><strong>Status:</strong> ${s_status}</p>
    `;
    document.getElementById('customerViewContent').innerHTML = content;
    document.getElementById('customerViewModal').style.display = 'block';
}

function showRejectModal(s_ref) {
    document.getElementById('rejectTicketRef').value = s_ref;
    document.getElementById('s_remarks').value = '';
    document.getElementById('rejectModal').style.display = 'block';
}

function approveTicket(s_ref) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'AllCustomersT.php?page_customer=<?php echo $pageCustomer; ?>';
    const ticketInput = document.createElement('input');
    ticketInput.type = 'hidden';
    ticketInput.name = 'ticket_ref';
    ticketInput.value = s_ref;
    form.appendChild(ticketInput);
    const approveInput = document.createElement('input');
    approveInput.type = 'hidden';
    approveInput.name = 'approve_ticket';
    approveInput.value = '1';
    form.appendChild(approveInput);
    document.body.appendChild(form);
    form.submit();
}

let searchTimeout;
function debouncedSearchTickets() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const searchTerm = document.getElementById('searchInput').value;
        const accountFilter = document.getElementById('account_filter') ? document.getElementById('account_filter').value : '';
        fetchTickets(1, searchTerm, accountFilter);
    }, 300);
}

function fetchTickets(page, searchTerm = '', accountFilter = '') {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `AllCustomersT.php?action=search&search_page=${page}&search=${encodeURIComponent(searchTerm)}&account_filter=${encodeURIComponent(accountFilter)}`, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.error) {
                showNotification(response.error, 'error');
            } else {
                document.getElementById('customer-table-body').innerHTML = response.html;
                updatePagination(response.currentPage, response.totalPages, searchTerm, accountFilter);
            }
        }
    };
    xhr.send();
}

function updatePagination(currentPage, totalPages, searchTerm, accountFilter) {
    const pagination = document.getElementById('customer-pagination');
    pagination.innerHTML = '';
    
    const prevLink = currentPage > 1 
        ? `<a href="#" class="pagination-link" onclick="fetchTickets(${currentPage - 1}, '${searchTerm}', '${accountFilter}')"><i class="fas fa-chevron-left"></i></a>`
        : `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    
    const nextLink = currentPage < totalPages 
        ? `<a href="#" class="pagination-link" onclick="fetchTickets(${currentPage + 1}, '${searchTerm}', '${accountFilter}')"><i class="fas fa-chevron-right"></i></a>`
        : `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    
    pagination.innerHTML = `
        ${prevLink}
        <span class="current-page">Page ${currentPage} of ${totalPages}</span>
        ${nextLink}
    `;
}

document.getElementById('accountFilterForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const accountFilter = document.getElementById('account_filter').value;
    fetchTickets(1, document.getElementById('searchInput').value, accountFilter);
    closeModal('accountFilterModal');
});

// Handle session messages
<?php if (isset($_SESSION['message'])): ?>
    showNotification("<?php echo htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); ?>", 'success');
    // Refresh table after showing success message
    setTimeout(() => {
        fetchTickets(<?php echo $pageCustomer; ?>, document.getElementById('searchInput').value, document.getElementById('account_filter')?.value || '');
    }, 3000);
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    showNotification("<?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>", 'error');
    // Refresh table after showing error message
    setTimeout(() => {
        fetchTickets(<?php echo $pageCustomer; ?>, document.getElementById('searchInput').value, document.getElementById('account_filter')?.value || '');
    }, 3000);
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>

</body>
</html>