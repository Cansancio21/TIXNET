<?php
session_start();
include 'db.php';

// Enable error reporting for debugging (remove in production)
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

// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    $limit = 10; // Tickets per page
    $page = isset($_GET['search_page']) ? max(1, (int)$_GET['search_page']) : 1;
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $offset = ($page - 1) * $limit;
    $searchLike = $searchTerm ? "%$searchTerm%" : null;

    // Count total closed tickets
    $sqlCount = "SELECT COUNT(*) as total FROM tbl_close_supp WHERE s_status = 'Closed'";
    if ($searchTerm) {
        $sqlCount .= " AND (s_ref LIKE ? OR c_id LIKE ? OR CONCAT(c_fname, ' ', c_lname) LIKE ? OR te_technician LIKE ? OR s_subject LIKE ? OR s_message LIKE ?)";
    }
    $stmtCount = $conn->prepare($sqlCount);
    if ($searchTerm) {
        $stmtCount->bind_param("ssssss", $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    $totalTickets = $resultCount->fetch_assoc()['total'];
    $totalPages = max(1, ceil($totalTickets / $limit));
    $stmtCount->close();

    // Fetch closed support tickets
    $sql = "SELECT s_ref, c_id, c_fname, c_lname, te_technician, s_subject, s_message, s_status, s_date 
            FROM tbl_close_supp 
            WHERE s_status = 'Closed'";
    if ($searchTerm) {
        $sql .= " AND (s_ref LIKE ? OR c_id LIKE ? OR CONCAT(c_fname, ' ', c_lname) LIKE ? OR te_technician LIKE ? OR s_subject LIKE ? OR s_message LIKE ?)";
    }
    $sql .= " ORDER BY s_date DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if ($searchTerm) {
        $stmt->bind_param("ssssssii", $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $limit, $offset);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    ob_start();
    while ($row = $result->fetch_assoc()) {
        $ticketData = json_encode([
            'ref' => $row['s_ref'],
            'c_id' => $row['c_id'],
            'c_name' => ($row['c_fname'] ?? '') . ' ' . ($row['c_lname'] ?? ''),
            'technician' => $row['te_technician'] ?? '',
            'subject' => $row['s_subject'] ?? '',
            'message' => $row['s_message'] ?? '',
            'status' => $row['s_status'] ?? '',
            'date' => $row['s_date'] ?? '-'
        ], JSON_HEX_QUOT | JSON_HEX_TAG);
        echo "<tr>
                <td>" . htmlspecialchars($row['s_ref']) . "</td>
                <td>" . htmlspecialchars($row['c_id']) . "</td>
                <td>" . htmlspecialchars(($row['c_fname'] ?? '') . ' ' . ($row['c_lname'] ?? '')) . "</td>
                <td>" . htmlspecialchars($row['te_technician'] ?? '') . "</td>
                <td>" . htmlspecialchars($row['s_subject'] ?? '') . "</td>
                <td>" . htmlspecialchars($row['s_message'] ?? '') . "</td>
                <td class='status-closed'>" . htmlspecialchars($row['s_status'] ?? '') . "</td>
                <td>" . htmlspecialchars($row['s_date'] ?? '-') . "</td>
                <td class='action-buttons'>
                    <span class='view-btn' onclick='showViewModal($ticketData)' title='View'><i class='fas fa-eye'></i></span>
                    <span class='delete-btn' onclick=\"openDeleteModal('" . htmlspecialchars($row['s_ref']) . "')\" title='Delete'><i class='fas fa-trash'></i></span>
                </td>
              </tr>";
    }
    if ($result->num_rows === 0) {
        echo "<tr><td colspan='9'>No closed support tickets found.</td></tr>";
    }
    $html = ob_get_clean();
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode([
        'html' => $html,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'searchTerm' => $searchTerm
    ]);
    exit;
}

// Handle delete action with CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid CSRF token";
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
        // Log action
        $logDescription = "Admin $username deleted closed support ticket ID $ticketId";
        $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description) VALUES (NOW(), ?)";
        $stmtLog = $conn->prepare($sqlLog);
        $stmtLog->bind_param("s", $logDescription);
        $stmtLog->execute();
        $stmtLog->close();

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
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Count total closed tickets
$sqlCount = "SELECT COUNT(*) as total FROM tbl_close_supp WHERE s_status = 'Closed'";
if ($searchTerm) {
    $sqlCount .= " AND (s_ref LIKE ? OR c_id LIKE ? OR CONCAT(c_fname, ' ', c_lname) LIKE ? OR te_technician LIKE ? OR s_subject LIKE ? OR s_message LIKE ?)";
}
$stmtCount = $conn->prepare($sqlCount);
if ($searchTerm) {
    $searchLike = "%$searchTerm%";
    $stmtCount->bind_param("ssssss", $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
}
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$totalTickets = $resultCount->fetch_assoc()['total'];
$totalPages = max(1, ceil($totalTickets / $limit));
$page = min($page, $totalPages); // Ensure page doesn't exceed total pages
$offset = ($page - 1) * $limit; // Recalculate offset if page was adjusted
$stmtCount->close();

// Fetch closed support tickets
$closedTickets = [];
$sql = "SELECT s_ref, c_id, c_fname, c_lname, te_technician, s_subject, s_message, s_status, s_date 
        FROM tbl_close_supp 
        WHERE s_status = 'Closed'";
if ($searchTerm) {
    $sql .= " AND (s_ref LIKE ? OR c_id LIKE ? OR CONCAT(c_fname, ' ', c_lname) LIKE ? OR te_technician LIKE ? OR s_subject LIKE ? OR s_message LIKE ?)";
}
$sql .= " ORDER BY s_date DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($searchTerm) {
    $stmt->bind_param("ssssssii", $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
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
    <link rel="stylesheet" href="regular_supps.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <style>
        .status-closed {
            color: var(--danger);
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="adminD.php"><img src="image/main.png" alt="Dashboard" class="icon" /> <span>Dashboard</span></a></li>
            <li><a href="viewU.php"><img src="image/users.png" alt="View Users" class="icon" /> <span>View Users</span></a></li>
            <li><a href="regular_close.php"><img src="image/ticket.png" alt="Regular Record" class="icon" /> <span>Regular Record</span></a></li>
            <li><a href="support_close.php" class="active"><img src="image/ticket.png" alt="Supports Record" class="icon" /> <span>Support Record</span></a></li>
            <li><a href="logs.php"><img src="image/log.png" alt="Logs" class="icon" /> <span>Logs</span></a></li>
            <li><a href="returnT.php"><img src="image/record.png" alt="Returned Records" class="icon" /> <span>Returned Records</span></a></li>
            <li><a href="deployedT.php"><img src="image/record.png" alt="Deployed Records" class="icon" /> <span>Deployed Records</span></a></li>
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>
    <div class="container">
        <div class="upper">
            <h1>Closed Support Tickets</h1>
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

        <!-- Display Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert success">
                <?php echo htmlspecialchars($_SESSION['message']); ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error">
                <?php echo htmlspecialchars($_SESSION['error']); ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- View Modal -->
        <div id="viewTicketModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Ticket Details</h2>
                </div>
                <div id="viewTicketContent"></div>
                <div class="modal-footer">
                    <button class="modal-btn cancel" onclick="closeModal('viewTicketModal')">Close</button>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Delete Ticket</h2>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete ticket #<span id="deleteTicketId"></span>?</p>
                </div>
                <div class="modal-footer">
                    <button class="modal-btn cancel" onclick="closeModal('deleteModal')">Cancel</button>
                    <button class="modal-btn confirm" id="confirmDeleteBtn" onclick="submitDeleteAction()">Confirm</button>
                </div>
            </div>
        </div>

        <form id="actionForm" method="POST" style="display: none;">
            <input type="hidden" name="action" id="formAction">
            <input type="hidden" name="ticket_id" id="formTicketId">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        </form>

        <div class="table-box">
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th>Ticket No.</th>
                        <th>Customer ID</th>
                        <th>Customer Name</th>
                        <th>Technician</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tickets-table-body">
                    <?php if (empty($closedTickets)): ?>
                        <tr>
                            <td colspan="9">No closed support tickets found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($closedTickets as $ticket): ?>
                            <?php
                            $ticketData = json_encode([
                                'ref' => $ticket['s_ref'],
                                'c_id' => $ticket['c_id'],
                                'c_name' => ($ticket['c_fname'] ?? '') . ' ' . ($ticket['c_lname'] ?? ''),
                                'technician' => $ticket['te_technician'] ?? '',
                                'subject' => $ticket['s_subject'] ?? '',
                                'message' => $ticket['s_message'] ?? '',
                                'status' => $ticket['s_status'] ?? '',
                                'date' => $ticket['s_date'] ?? '-'
                            ], JSON_HEX_QUOT | JSON_HEX_TAG);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ticket['s_ref']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['c_id']); ?></td>
                                <td><?php echo htmlspecialchars(($ticket['c_fname'] ?? '') . ' ' . ($ticket['c_lname'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($ticket['te_technician'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($ticket['s_subject'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($ticket['s_message'] ?? ''); ?></td>
                                <td class="status-closed"><?php echo htmlspecialchars($ticket['s_status'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($ticket['s_date'] ?? '-'); ?></td>
                                <td class="action-buttons">
                                    <span class="view-btn" onclick='showViewModal(<?php echo $ticketData; ?>)' title="View"><i class="fas fa-eye"></i></span>
                                    <span class="delete-btn" onclick="openDeleteModal('<?php echo htmlspecialchars($ticket['s_ref']); ?>')" title="Delete"><i class="fas fa-trash"></i></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination" id="tickets-pagination">
                <?php
                $paginationParams = $searchTerm ? "&search=" . urlencode($searchTerm) : "";
                if ($page > 1) {
                    echo "<a href='javascript:searchTickets(" . ($page - 1) . ")' class='pagination-link'><i class='fas fa-chevron-left'></i></a>";
                } else {
                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                }
                echo "<span class='current-page'>Page $page of $totalPages</span>";
                if ($page < $totalPages) {
                    echo "<a href='javascript:searchTickets(" . ($page + 1) . ")' class='pagination-link'><i class='fas fa-chevron-right'></i></a>";
                } else {
                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<script>
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
    const searchTerm = document.getElementById('searchInput').value;
    const tbody = document.getElementById('tickets-table-body');
    const paginationContainer = document.getElementById('tickets-pagination');

    console.log(`Searching tickets: page=${page}, searchTerm=${searchTerm}`);

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                tbody.innerHTML = response.html;
                updatePagination(response.currentPage, response.totalPages, response.searchTerm);
            } catch (e) {
                console.error('Error parsing JSON:', e, xhr.responseText);
            }
        }
    };
    xhr.open('GET', `support_close.php?action=search&search=${encodeURIComponent(searchTerm)}&search_page=${page}`, true);
    xhr.send();
}

function updatePagination(currentPage, totalPages, searchTerm) {
    const paginationContainer = document.getElementById('tickets-pagination');
    let paginationHtml = '';

    if (currentPage > 1) {
        paginationHtml += `<a href="javascript:searchTickets(${currentPage - 1})" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="javascript:searchTickets(${currentPage + 1})" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

const debouncedSearchTickets = debounce(searchTickets, 300);

function showViewModal(data) {
    const content = document.getElementById('viewTicketContent');
    const statusClass = `status-${data.status.toLowerCase().replace(' ', '-')}`;
    content.innerHTML = `
        <p><strong>Ticket No.:</strong> ${data.ref}</p>
        <p><strong>Customer ID:</strong> ${data.c_id}</p>
        <p><strong>Customer Name:</strong> ${data.c_name}</p>
        <p><strong>Technician:</strong> ${data.technician}</p>
        <p><strong>Subject:</strong> ${data.subject}</p>
        <p><strong>Message:</strong> ${data.message}</p>
        <p><strong>Status:</strong> <span class="${statusClass}">${data.status}</span></p>
        <p><strong>Date:</strong> ${data.date}</p>
    `;
    document.getElementById('viewTicketModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function openDeleteModal(ticketId) {
    document.getElementById('deleteTicketId').textContent = ticketId;
    document.getElementById('deleteModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function submitDeleteAction() {
    const ticketId = document.getElementById('deleteTicketId').textContent;
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Deleting...';
    
    document.getElementById('formAction').value = 'delete';
    document.getElementById('formTicketId').value = ticketId;
    document.getElementById('actionForm').submit();
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Confirm';
    }
}
</script>
</body>
</html>