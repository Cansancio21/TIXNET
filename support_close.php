<?php
session_start();
include 'db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Check user authentication
$username = $_SESSION['username'] ?? '';
$userType = $_SESSION['user_type'] ?? '';

if (!$username || $userType !== 'admin') {
    $_SESSION['error'] = "Unauthorized access. Please log in as an admin.";
    header("Location: index.php");
    exit();
}

// Fetch user details
$firstName = '';
$lastName = '';
$sqlUser = "SELECT u_fname, u_lname FROM tbl_user WHERE u_username = ?";
$stmt = $conn->prepare($sqlUser);
$stmt->bind_param("s", $username);
$stmt->execute();
$resultUser = $stmt->get_result();
if ($resultUser->num_rows > 0) {
    $row = $resultUser->fetch_assoc();
    $firstName = $row['u_fname'] ?: $username;
    $lastName = $row['u_lname'] ?: '';
} else {
    $_SESSION['error'] = "User not found.";
    $stmt->close();
    header("Location: index.php");
    exit();
}
$stmt->close();

// Avatar handling
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $username . '.png';
if (!isset($_SESSION['avatarPath'])) {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$cleanAvatarPath = preg_replace('/\?\d+$/', '', $_SESSION['avatarPath']);
if (file_exists($userAvatar)) {
    if ($cleanAvatarPath !== $userAvatar) {
        $_SESSION['avatarPath'] = $userAvatar . '?' . time();
    }
} elseif (!file_exists($cleanAvatarPath)) {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$avatarPath = $_SESSION['avatarPath'];

// Handle delete action with CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid request";
        header("Location: support_close.php");
        exit();
    }

    $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_SANITIZE_STRING);
    if (empty($ticketId)) {
        $_SESSION['error'] = "Invalid ticket ID";
        header("Location: support_close.php");
        exit();
    }

    $sql = "DELETE FROM tbl_close_supp WHERE s_ref = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $ticketId);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Ticket deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting ticket: " . $conn->error;
    }
    $stmt->close();
    header("Location: support_close.php");
    exit();
}

// Pagination
$limit = 10; // Tickets per page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total closed tickets with prepared statement
$sqlCount = "SELECT COUNT(*) as total FROM tbl_close_supp WHERE s_status = 'Closed'";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$totalTickets = $resultCount->fetch_assoc()['total'];
$totalPages = ceil($totalTickets / $limit);
$stmtCount->close();

// Fetch closed support tickets
$closedTickets = [];
$sql = "SELECT s_ref, c_id, te_technician, c_fname, c_lname, s_subject, s_message, s_status, s_date 
        FROM tbl_close_supp 
        WHERE s_status = 'Closed' 
        ORDER BY s_date DESC 
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $closedTickets[] = $row;
    }
    $stmt->close();
} else {
    error_log("Prepare failed for closed support tickets: " . $conn->error);
    $_SESSION['error'] = "Error fetching tickets.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Support Tickets Record</title>
    <link rel="stylesheet" href="regular_supp.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            position: relative;
        }
        .modal-content h2 {
            margin-top: 0;
        }
        .close-modal {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 20px;
            cursor: pointer;
        }
        .action-buttons a, .action-buttons form {
            display: inline-block;
            margin-right: 5px;
        }
        .action-buttons a {
            color: #007bff;
            text-decoration: none;
        }
        .action-buttons a:hover {
            text-decoration: underline;
        }
        .action-buttons form button {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 0;
        }
        .action-buttons form button:hover {
            text-decoration: underline;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination-link {
            display: inline-block;
            padding: 5px 10px;
            margin: 0 5px;
            text-decoration: none;
            color: #007bff;
        }
        .pagination-link.disabled {
            color: #ccc;
            pointer-events: none;
        }
        .current-page {
            margin: 0 10px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="adminD.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="viewU.php"><i class="fas fa-users"></i> <span>View Users</span></a></li>
            <li><a href="regular_close.php"><i class="fas fa-wrench"></i> <span>Regulars Record</span></a></li>
            <li><a href="support_close.php" class="active"><i class="fas fa-wrench"></i> <span>Supports Record</span></a></li>
            <li><a href="logs.php"><i class="fas fa-file-alt"></i> <span>View Logs</span></a></li>
            <li><a href="borrowedT.php"><i class="fas fa-book"></i> <span>Borrowed Records</span></a></li>
            <li><a href="returnT.php"><i class="fas fa-undo"></i> <span>Returned Records</span></a></li>
            <li><a href="deployedT.php"><i class="fas fa-rocket"></i> <span>Deploy Records</span></a></li>
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>

    <div class="container">
        <div class="upper"> 
            <h1>Closed Support Tickets</h1>
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
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
        </div>

        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th>Ticket No</th>
                        <th>Customer ID</th>
                        <th>Customer Name</th>
                        <th>Technician</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ticketTable">
                    <?php if ($closedTickets): ?>
                        <?php foreach ($closedTickets as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['s_ref'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['c_id'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars(($row['c_fname'] . ' ' . $row['c_lname']) ?: 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($row['te_technician'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['s_subject'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars(preg_replace('/^ARCHIVED:/', '', $row['s_message'] ?: '-')); ?></td>
                                <td><?php echo htmlspecialchars($row['s_date'] ?: '-'); ?></td>
                                <td class="status-closed"><?php echo htmlspecialchars($row['s_status'] ?: 'Closed'); ?></td>
                                <td class="action-buttons">
                                    <a href="javascript:void(0)" onclick="viewTicket(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)">View</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this ticket?');">
                                        <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($row['s_ref']); ?>">
                                        <input type="hidden" name="delete_ticket" value="1">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="empty-state">No closed support tickets found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination" id="ticket-pagination">
                <?php if ($page > 1): ?>
                    <a href="javascript:searchTickets(<?php echo $page - 1; ?>)" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?>
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>

                <span class="current-page">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

                <?php if ($page < $totalPages): ?>
                    <a href="javascript:searchTickets(<?php echo $page + 1; ?>)" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- View Ticket Modal -->
<div id="viewTicketModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal()">×</span>
        <h2>Ticket Details</h2>
        <p><strong>Ticket No:</strong> <span id="modal-s_ref"></span></p>
        <p><strong>Customer ID:</strong> <span id="modal-c_id"></span></p>
        <p><strong>Customer Name:</strong> <span id="modal-c_name"></span></p>
        <p><strong>Technician:</strong> <span id="modal-te_technician"></span></p>
        <p><strong>Subject:</strong> <span id="modal-s_subject"></span></p>
        <p><strong>Message:</strong> <span id="modal-s_message"></span></p>
        <p><strong>Date:</strong> <span id="modal-s_date"></span></p>
        <p><strong>Status:</strong> <span id="modal-s_status"></span></p>
    </div>
</div>

<script>
function debouncedSearchTickets() {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(searchTickets, 300);
}

function searchTickets(page = 1) {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const table = document.getElementById('ticketTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        let match = false;
        for (let j = 0; j < cells.length; j++) {
            if (cells[j] && cells[j].textContent.toLowerCase().includes(input)) {
                match = true;
                break;
            }
        }
        rows[i].style.display = match ? '' : 'none';
    }

    // Update URL for pagination
    window.history.pushState({}, '', `support_close.php?page=${page}`);
    window.location.reload(); // Reload to fetch new page data
}

function viewTicket(ticket) {
    document.getElementById('modal-s_ref').textContent = ticket.s_ref || '-';
    document.getElementById('modal-c_id').textContent = ticket.c_id || '-';
    document.getElementById('modal-c_name').textContent = (ticket.c_fname + ' ' + ticket.c_lname) || 'Unknown';
    document.getElementById('modal-te_technician').textContent = ticket.te_technician || '-';
    document.getElementById('modal-s_subject').textContent = ticket.s_subject || '-';
    document.getElementById('modal-s_message').textContent = ticket.s_message ? ticket.s_message.replace(/^ARCHIVED:/, '') : '-';
    document.getElementById('modal-s_date').textContent = ticket.s_date || '-';
    document.getElementById('modal-s_status').textContent = ticket.s_status || 'Closed';
    document.getElementById('viewTicketModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('viewTicketModal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('alert-hidden');
            setTimeout(() => alert.remove(), 500);
        }, 2000);
    });
});
</script>
</body>
</html>