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

// Handle AJAX assign ticket request
if (isset($_POST['assign_ticket'])) {
    $t_ref = trim($_POST['t_ref']);
    $technician_email = trim($_POST['technician_email']);
    $ticket_type = trim($_POST['ticket_type']);

    // Check if ticket is already assigned to this technician
    $sqlCheck = "";
    if (strpos($ticket_type, ' ') === false) { // Regular ticket (t_aname is typically a single name)
        $sqlCheck = "SELECT COUNT(*) FROM tbl_ticket WHERE t_ref = ? AND technician_email = ?";
    } else { // Support ticket (c_fname + c_lname)
        $sqlCheck = "SELECT COUNT(*) FROM tbl_supp_tickets WHERE s_ref = ? AND technician_email = ?";
    }
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("ss", $t_ref, $technician_email);
    $stmtCheck->execute();
    $stmtCheck->bind_result($count);
    $stmtCheck->fetch();
    $stmtCheck->close();

    if ($count > 0) {
        echo json_encode(['success' => false, 'error' => 'This ticket is already assigned to this technician.']);
        exit();
    }

    // Update ticket with technician_email
    $sqlAssign = "";
    if (strpos($ticket_type, ' ') === false) {
        $sqlAssign = "UPDATE tbl_ticket SET technician_email = ? WHERE t_ref = ?";
    } else {
        $sqlAssign = "UPDATE tbl_supp_tickets SET technician_email = ? WHERE s_ref = ?";
    }
    $stmtAssign = $conn->prepare($sqlAssign);
    if (!$stmtAssign) {
        error_log("Assign query prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error occurred.']);
        exit();
    }
    $stmtAssign->bind_param("ss", $technician_email, $t_ref);
    if ($stmtAssign->execute()) {
        $logDescription = "Assigned ticket $t_ref to technician $technician_email by $firstName $lastName (Person: $ticket_type)";
        $logType = "Staff $firstName $lastName";
        $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
        $stmtLog = $conn->prepare($sqlLog);
        $stmtLog->bind_param("ss", $logDescription, $logType);
        $stmtLog->execute();
        $stmtLog->close();
        echo json_encode(['success' => true, 'message' => 'Ticket assigned successfully.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to assign ticket: ' . $stmtAssign->error]);
    }
    $stmtAssign->close();
    exit();
}

// Handle AJAX ticket search for assign modal
if (isset($_GET['action']) && $_GET['action'] === 'search_tickets') {
    $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

    $searchTerm = $conn->real_escape_string($searchTerm);
    $likeSearch = '%' . $searchTerm . '%';

    // Initialize query
    $sql = "";
    $params = [];
    $paramTypes = "";

    // Handle specific search terms or default to all tickets
    if ($searchTerm && strtolower($searchTerm) === 'regular tickets') {
        $sql = "SELECT t_ref, IFNULL(t_aname, 'Unknown') AS ticket_type 
                FROM tbl_ticket 
                WHERE t_details NOT LIKE 'ARCHIVED:%'";
    } elseif ($searchTerm && strtolower($searchTerm) === 'support tickets') {
        $sql = "SELECT st.s_ref AS t_ref, IFNULL(CONCAT(c.c_fname, ' ', c.c_lname), 'Unknown') AS ticket_type 
                FROM tbl_supp_tickets st 
                JOIN tbl_customer c ON st.c_id = c.c_id";
    } else {
        // Combined search across both tables (default to all if search term is empty)
        $sql = "SELECT t_ref, ticket_type FROM (
                    SELECT t_ref, IFNULL(t_aname, 'Unknown') AS ticket_type 
                    FROM tbl_ticket 
                    WHERE t_details NOT LIKE 'ARCHIVED:%' " . ($searchTerm ? "AND t_ref LIKE ?" : "") . "
                    UNION
                    SELECT st.s_ref AS t_ref, IFNULL(CONCAT(c.c_fname, ' ', c.c_lname), 'Unknown') AS ticket_type 
                    FROM tbl_supp_tickets st 
                    JOIN tbl_customer c ON st.c_id = c.c_id " . ($searchTerm ? "WHERE st.s_ref LIKE ?" : "") . "
                ) AS combined
                ORDER BY t_ref";
        $paramTypes = $searchTerm ? "ss" : "";
        $params = $searchTerm ? [$likeSearch, $likeSearch] : [];
    }

    // Debug: Log the query
    error_log("Ticket search query: $sql, Params: " . json_encode($params));

    // Fetch search results
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

    // Debug: Log number of rows returned
    error_log("Tickets returned: " . $result->num_rows);

    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Debug: Log each ticket
            error_log("Ticket: " . json_encode($row));
            echo "<tr class='ticket-row' onclick=\"selectTicket('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['ticket_type'], ENT_QUOTES, 'UTF-8') . "')\" style='cursor: pointer;'>
                    <td>" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['ticket_type'], ENT_QUOTES, 'UTF-8') . "</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='2' style='text-align: center;'>No tickets found.</td></tr>";
    }
    $tableRows = ob_get_clean();

    echo json_encode(['html' => $tableRows]);
    $stmt->close();
    $conn->close();
    exit();
}

// Handle AJAX search request for technicians
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['search'])) {
    $searchTerm = $_GET['search'];
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $searchTerm = $conn->real_escape_string($searchTerm);
    $likeSearch = '%' . $searchTerm . '%';

    $sqlCount = "SELECT COUNT(*) AS total FROM tbl_user 
                 WHERE u_type = 'technician' AND u_status = 'active' 
                 AND (u_fname LIKE ? OR u_lname LIKE ? OR u_email LIKE ?)";
    $sql = "SELECT u_fname, u_lname, u_email, u_type, u_status 
            FROM tbl_user 
            WHERE u_type = 'technician' AND u_status = 'active' 
            AND (u_fname LIKE ? OR u_lname LIKE ? OR u_email LIKE ?)
            LIMIT ?, ?";

    // Get total count for pagination
    $stmtCount = $conn->prepare($sqlCount);
    if (!$stmtCount) {
        error_log("Count query prepare failed: " . $conn->error);
        exit();
    }
    $stmtCount->bind_param("sss", $likeSearch, $likeSearch, $likeSearch);
    $stmtCount->execute();
    $countResult = $stmtCount->get_result();
    $totalRow = $countResult->fetch_assoc();
    $total = $totalRow['total'];
    $totalPages = ceil($total / $limit);
    $stmtCount->close();

    // Fetch search results
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Search query prepare failed: " . $conn->error);
        exit();
    }
    $stmt->bind_param("sssii", $likeSearch, $likeSearch, $likeSearch, $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $statusDisplay = ($row['u_status'] === 'active') ? 'Available' : htmlspecialchars($row['u_status'], ENT_QUOTES, 'UTF-8');
            $technicianName = htmlspecialchars($row['u_fname'] . ' ' . $row['u_lname'], ENT_QUOTES, 'UTF-8');
            echo "<tr>
                    <td>" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['u_type'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . $statusDisplay . "</td>
                    <td class='action-buttons'>
                        <a class='view-btn' href='#' onclick=\"showTechnicianViewModal('" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_type'], ENT_QUOTES, 'UTF-8') . "', '" . $statusDisplay . "')\" title='View Technician'><i class='fas fa-eye'></i></a>
                        <a class='assign-btn' href='#' onclick=\"showAssignTicketModal('" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "', '" . $technicianName . "')\" title='Assign Ticket'><i class='fas fa-user-plus'></i></a>
                    </td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='6' style='text-align: center;'>No technicians found.</td></tr>";
    }
    $tableRows = ob_get_clean();

    // Update pagination
    echo "<script>updatePagination($page, $totalPages);</script>";
    echo $tableRows;
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

// Fetch technicians
$sqlTechnicians = "SELECT u_fname, u_lname, u_email, u_type, u_status 
                   FROM tbl_user 
                   WHERE u_type = 'technician' AND u_status = 'active' 
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
/* Modal Styling */

.modal-header h2 {
    font-size: 13px;
    margin: 0 0 6px;
}

.modal-search-container {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
}

.modal-search-container input {
    flex: 1;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 5px;
    margin-right: 5px;
    font-size: 11px;
}

.modal-search-container .search-icon {
    font-size: 11px;
    color: #666;
}

/* Table Box Styling for Assign Ticket Modal */
#assignTicketModal .table-box {
    width: 100%;
    max-height: 120px; /* Further reduced to ensure footer visibility */
    overflow-y: auto; /* Scroll only the table */
    margin-bottom: 6px;
    flex-grow: 1; /* Allow table to take available space */
}

#assignTicketModal #tickets-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    margin-top: 10px;
}

#assignTicketModal #tickets-table thead {
    position: sticky;
    top: 0;
    background: linear-gradient(135deg, var(--primary, #6c5ce7), var(--secondary, #a29bfe));
    color: white;
    z-index: 1;
}

#assignTicketModal #tickets-table th,
#assignTicketModal #tickets-table td {
    padding: 6px;
    border-bottom: 1px solid #ddd;
    text-align: left;
    white-space: nowrap;
}

#assignTicketModal #tickets-table th:first-child,
#assignTicketModal #tickets-table td:first-child {
    min-width: 150px;
}

#assignTicketModal #tickets-table th:last-child,
#assignTicketModal #tickets-table td:last-child {
    min-width: 150px;
}

.ticket-row:hover {
    background-color: rgba(108, 92, 231, 0.05);
}

.ticket-row.selected {
    background-color: #d4edda;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 5px;
    padding: 8px 0; /* Increased padding for visibility */
    border-top: 1px solid #ddd; /* Visual separation */
    flex-shrink: 0; /* Prevent footer from being compressed */
    background: white; /* Ensure footer is visible */
    z-index: 2; /* Ensure footer stays above other content */
}

.modal-btn {
    padding: 6px 16px; /* Slightly larger for visibility */
    border-radius: 20px;
    border: none;
    cursor: pointer;
    font-size: 11px;
    transition: all 0.3s;
}

.modal-btn.confirm {
    background: var(--primary, #28a745); /* Green for Assign button */
    color: white;
}

.modal-btn.cancel {
    background: var(--secondary, #dc3545); /* Red for Cancel button */
    color: white;
}

.modal-btn.confirm:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.modal-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
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
            <li><a href="AssignTech.php" class="active"><img src="image/add.png" alt="Technicians" class="icon" /> <span>Technicians</span></a></li>
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
        </div>

        <div class="table-box glass-container">
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
                            $statusDisplay = ($row['u_status'] === 'active') ? 'Available' : htmlspecialchars($row['u_status'], ENT_QUOTES, 'UTF-8');
                            $technicianName = htmlspecialchars($row['u_fname'] . ' ' . $row['u_lname'], ENT_QUOTES, 'UTF-8');
                            echo "<tr>
                                    <td>" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . htmlspecialchars($row['u_type'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . $statusDisplay . "</td>
                                    <td class='action-buttons'>
                                        <a class='view-btn' href='#' onclick=\"showTechnicianViewModal('" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_type'], ENT_QUOTES, 'UTF-8') . "', '" . $statusDisplay . "')\" title='View Technician'><i class='fas fa-eye'></i></a>
                                        <a class='assign-btn' href='#' onclick=\"showAssignTicketModal('" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "', '" . $technicianName . "')\" title='Assign Ticket'><i class='fas fa-user-plus'></i></a>
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

<!-- Technician View Modal -->
<div id="technicianViewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Technician Details</h2>
        </div>
        <div id="technicianViewContent" class="view-details"></div>
        <div class="modal-footer">
            <button class="modal-btn cancel" onclick="closeModal('technicianViewModal')">Close</button>
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
            <input type="text" id="ticketSearchInput" placeholder="Search tickets by reference, 'Regular Tickets', or 'Support Tickets'..." onkeyup="debouncedSearchTickets()">
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
            <input type="hidden" name="technician_email" id="assignTechnicianEmail">
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
    // Handle alert messages
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 1s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 1000);
        }, 10000);
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

function searchTechnicians(page = 1) {
    const searchTerm = document.getElementById('searchInput').value;
    const tbody = document.getElementById('technicians-tbody');

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            tbody.innerHTML = xhr.responseText.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        }
    };
    xhr.open('GET', `AssignTech.php?action=search&search=${encodeURIComponent(searchTerm)}&page=${page}`, true);
    xhr.send();
}

function searchTickets() {
    const searchTerm = document.getElementById('ticketSearchInput').value;
    const tbody = document.getElementById('tickets-tbody');

    fetch(`AssignTech.php?action=search_tickets&search=${encodeURIComponent(searchTerm)}`)
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = data.html;
        })
        .catch(error => {
            console.error('Error searching tickets:', error);
            tbody.innerHTML = '<tr><td colspan="2">Error loading tickets.</td></tr>';
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
        document.getElementById('assignButton').disabled = true;
    }
}

function showTechnicianViewModal(firstName, lastName, email, type, status) {
    const content = `
        <p><strong>First Name:</strong> ${firstName}</p>
        <p><strong>Last Name:</strong> ${lastName}</p>
        <p><strong>Email:</strong> ${email}</p>
        <p><strong>Type:</strong> ${type}</p>
        <p><strong>Status:</strong> ${status}</p>
    `;
    document.getElementById('technicianViewContent').innerHTML = content;
    document.getElementById('technicianViewModal').style.display = 'block';
}

function showAssignTicketModal(technicianEmail, technicianName) {
    document.getElementById('assignTechnicianEmail').value = technicianEmail;
    document.getElementById('assignTechnicianName').textContent = technicianName;
    document.getElementById('assignTicketModal').style.display = 'block';
    searchTickets();
}

function selectTicket(t_ref, ticket_type) {
    document.getElementById('assignTicketRef').value = t_ref;
    document.getElementById('assignTicketType').value = ticket_type;
    document.getElementById('assignButton').disabled = false;

    // Highlight selected row
    const rows = document.querySelectorAll('.ticket-row');
    rows.forEach(row => row.classList.remove('selected'));
    const selectedRow = Array.from(rows).find(row => row.querySelector('td').textContent === t_ref);
    if (selectedRow) selectedRow.classList.add('selected');
}

document.getElementById('assignTicketForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('AssignTech.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const alertContainer = document.querySelector('.alert-container');
        if (data.success) {
            closeModal('assignTicketModal');
            const successAlert = document.createElement('div');
            successAlert.className = 'alert alert-success';
            successAlert.textContent = data.message;
            alertContainer.appendChild(successAlert);
            setTimeout(() => {
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 1000);
            }, 5000);
        } else {
            const errorAlert = document.createElement('div');
            errorAlert.className = 'alert alert-error';
            errorAlert.textContent = data.error;
            alertContainer.appendChild(errorAlert);
            setTimeout(() => {
                errorAlert.style.opacity = '0';
                setTimeout(() => errorAlert.remove(), 1000);
            }, 5000);
        }
    })
    .catch(error => {
        console.error('Error assigning ticket:', error);
        const alertContainer = document.querySelector('.alert-container');
        const errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-error';
        errorAlert.textContent = 'An error occurred while assigning the ticket.';
        alertContainer.appendChild(errorAlert);
        setTimeout(() => {
            errorAlert.style.opacity = '0';
            setTimeout(() => errorAlert.remove(), 1000);
        }, 5000);
    });
});
</script>
</body>
</html>

