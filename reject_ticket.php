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

// Set avatar path
$avatarFolder = 'Uploads/avatars/';
$avatarIdentifier = $isCustomer ? $userId : null;
$userAvatar = $avatarFolder . $avatarIdentifier . '.png';
$avatarPath = file_exists($userAvatar) ? $userAvatar . '?' . time() : 'default-avatar.png';
$_SESSION['avatarPath'] = $avatarPath;

// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $page = isset($_GET['search_page']) ? max(1, (int)$_GET['search_page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $output = '';

    $whereClauses = ["c_id = ?", "s_status = 'Declined'"];
    $params = [$userId];
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
                <td>" . htmlspecialchars($row['c_id'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['s_subject'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . htmlspecialchars($row['s_message'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . $remarks . "</td>
                <td class='$statusClass'>" . ucfirst(strtolower($row['s_status'])) . "</td>
                <td class='action-buttons'>
                    <a class='view-btn' href='#' onclick=\"showViewModal('" . htmlspecialchars($row['c_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_message'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_status'], ENT_QUOTES, 'UTF-8') . "', '" . $remarks . "')\" title='View'><i class='fas fa-eye'></i></a>
                </td></tr>";
        }
    } else {
        $output = "<tr><td colspan='8' class='empty-state'>No Declined tickets found.</td></tr>";
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
$totalStmt->bind_param("i", $userId);
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
$stmtTickets->bind_param("iii", $userId, $offset, $limit);
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
    <link rel="stylesheet" href="reject_ticketss.css">

     <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <style>
        .table-box {
            display: block;
            width: 100%;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            margin-top: 20px;
        }
        .rejected-tickets table {
            width: 100%;
            border-collapse: collapse;
        }
    
        .status-rejected {
            color: red;
        }
    
        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
        .alert.fade-out {
            opacity: 0;
            transition: opacity 0.5s ease;
        }
    </style>
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
                <div class="user-details">
                    <span><?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <small><?php echo htmlspecialchars(ucfirst($userType), ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
            </div>
        </div>

        <div class="alert-container" id="alertContainer"></div>

     <div class="table-box">
    <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search tickets..." onkeyup="debouncedSearchTickets()">
                <span class="search-icon"><i class="fas fa-search"></i></span>
            </div>
    <div class="rejected-tickets">
        <div class="table-wrapper">
            <table id="rejected-tickets-table">
                <thead>
                    <tr>
                        <th>Ticket No</th>
                        <th>Customer ID</th>
                        <th>Account Name</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Remarks</th>
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
                                <td>" . htmlspecialchars($row['c_id'], ENT_QUOTES, 'UTF-8') . "</td>
                                <td>" . htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8') . "</td>
                                <td>" . htmlspecialchars($row['s_subject'], ENT_QUOTES, 'UTF-8') . "</td> 
                                <td>" . htmlspecialchars($row['s_message'], ENT_QUOTES, 'UTF-8') . "</td> 
                                <td>" . $remarks . "</td>
                                <td class='$statusClass'>" . ucfirst(strtolower($row['s_status'])) . "</td>
                                <td class='action-buttons'>
                                    <a class='view-btn' href='#' onclick=\"showViewModal('" . htmlspecialchars($row['c_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_subject'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_message'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['s_status'], ENT_QUOTES, 'UTF-8') . "', '" . $remarks . "')\" title='View'><i class='fas fa-eye'></i></a>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' class='empty-state'>No rejected tickets found.</td></tr>";
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

function showViewModal(c_id, accountName, s_ref, s_subject, s_message, s_status, s_remarks) {
    const content = `
        <p><strong>Customer ID:</strong> ${c_id}</p>
        <p><strong>Account Name:</strong> ${accountName}</p>
        <p><strong>Ticket Ref:</strong> ${s_ref}</p>
        <p><strong>Subject:</strong> ${s_subject}</p>
        <p><strong>Message:</strong> ${s_message}</p>
        <p><strong>Remarks:</strong> ${s_remarks || 'None'}</p>
        <p><strong>Status:</strong> ${s_status}</p>
    `;
    document.getElementById('viewContent').innerHTML = content;
    document.getElementById('viewModal').style.display = 'block';
}

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
        ? `<a href="#" class="pagination-link" onclick="fetchTickets(${currentPage - 1}, '${searchTerm}')"><i class="fas fa-chevron-left"></i></a>`
        : `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    
    const nextLink = currentPage < totalPages 
        ? `<a href="#" class="pagination-link" onclick="fetchTickets(${currentPage + 1}, '${searchTerm}')"><i class="fas fa-chevron-right"></i></a>`
        : `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    
    pagination.innerHTML = `
        ${prevLink}
        <span class="current-page">Page ${currentPage} of ${totalPages}</span>
        ${nextLink}
    `;
}

// Handle session error notifications
window.addEventListener('DOMContentLoaded', () => {
    <?php if (isset($_SESSION['error'])): ?>
        showNotification("<?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>", 'error');
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});
</script>
</body>
</html>
