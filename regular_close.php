
<?php
session_start();
include 'db.php'; // Include your database connection file

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Verify user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['userId'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header("Location: index.php");
    exit();
}

// Check user authentication
$username = $_SESSION['username'] ?? '';
$userType = $_SESSION['user_type'] ?? '';

if (!$username || $userType !== 'admin') {
    $_SESSION['error'] = "Unauthorized access. Please log in as an admin.";
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['userId'];

// Initialize variables
$firstName = $lastName = $userType = '';
$avatarPath = 'default-avatar.png';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $username . '.png';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalPages = 1; // Default to 1 to avoid undefined variable in pagination
$limit = 10; // Tickets per page
$offset = ($page - 1) * $limit;
$resultTickets = null; // Initialize to prevent undefined variable warning

// Set avatar path
if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} else {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$avatarPath = $_SESSION['avatarPath'];

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if (!$conn) {
    die("Database connection failed.");
}

try {
    // Fetch user details
    $sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sqlUser);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $resultUser = $stmt->get_result();

    if ($resultUser->num_rows > 0) {
        $row = $resultUser->fetch_assoc();
        $firstName = $row['u_fname'] ?? '';
        $lastName = $row['u_lname'] ?? '';
        $userType = $row['u_type'] ?? '';
    } else {
        throw new Exception("User not found.");
    }
    $stmt->close();

    // Handle AJAX search request
    if (isset($_GET['action']) && $_GET['action'] === 'search') {
        $searchLike = $searchTerm ? "%$searchTerm%" : null;

        // Count total tickets
        $sqlTotal = "SELECT COUNT(*) AS total FROM tbl_close_regular";
        if ($searchTerm) {
            $sqlTotal .= " WHERE t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR te_technician LIKE ? OR t_ref LIKE ?";
        }
        $stmtTotal = $conn->prepare($sqlTotal);
        if ($searchTerm) {
            $stmtTotal->bind_param("sssss", $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
        }
        $stmtTotal->execute();
        $resultTotal = $stmtTotal->get_result();
        $totalTickets = $resultTotal->fetch_assoc()['total'] ?? 0;
        $stmtTotal->close();

        $totalPages = max(1, ceil($totalTickets / $limit));

        // Fetch tickets
        $sqlTickets = "SELECT t_ref, t_aname, te_technician, t_subject, t_status, t_details 
                       FROM tbl_close_regular";
        if ($searchTerm) {
            $sqlTickets .= " WHERE t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR te_technician LIKE ? OR t_ref LIKE ?";
        }
        $sqlTickets .= " ORDER BY t_ref ASC LIMIT ? OFFSET ?";
        $stmtTickets = $conn->prepare($sqlTickets);
        if ($searchTerm) {
            $stmtTickets->bind_param("sssssii", $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $limit, $offset);
        } else {
            $stmtTickets->bind_param("ii", $limit, $offset);
        }
        $stmtTickets->execute();
        $resultTickets = $stmtTickets->get_result();

        ob_start();
        while ($row = $resultTickets->fetch_assoc()) {
            $ticketData = json_encode([
                'ref' => $row['t_ref'],
                'aname' => $row['t_aname'] ?? '',
                'technician' => $row['te_technician'] ?? '',
                'subject' => $row['t_subject'] ?? '',
                'details' => $row['t_details'] ?? '',
                'status' => ucfirst(strtolower($row['t_status'] ?? '')),
            ], JSON_HEX_QUOT | JSON_HEX_TAG);
            echo "<tr>
                    <td>" . htmlspecialchars($row['t_ref']) . "</td>
                    <td>" . htmlspecialchars($row['t_aname'] ?? '') . "</td>
                    <td>" . htmlspecialchars($row['te_technician'] ?? '') . "</td>
                    <td>" . htmlspecialchars($row['t_subject'] ?? '') . "</td>
                    <td>" . htmlspecialchars($row['t_details'] ?? '') . "</td>
                    <td class='status-closed'>" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                    <td class='action-buttons'>
                        <span class='view-btn' onclick='showViewModal($ticketData)' title='View'><i class='fas fa-eye'></i></span>
                        <span class='delete-btn' onclick=\"openDeleteModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></span>
                    </td>
                  </tr>";
        }
        if ($resultTickets->num_rows === 0) {
            echo "<tr><td colspan='7'>No closed regular tickets found.</td></tr>";
        }
        $html = ob_get_clean();
        $stmtTickets->close();

        header('Content-Type: application/json');
        echo json_encode([
            'html' => $html,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'searchTerm' => $searchTerm
        ]);
        exit;
    }

    // Handle AJAX export data request
    if (isset($_GET['action']) && $_GET['action'] === 'export_data') {
        $searchLike = $searchTerm ? "%$searchTerm%" : null;

        // Fetch all tickets for export
        $sqlTickets = "SELECT t_ref, t_aname, te_technician, t_subject, t_status, t_details 
                       FROM tbl_close_regular";
        if ($searchTerm) {
            $sqlTickets .= " WHERE t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR te_technician LIKE ? OR t_ref LIKE ?";
        }
        $sqlTickets .= " ORDER BY t_ref ASC";
        $stmtTickets = $conn->prepare($sqlTickets);
        if ($searchTerm) {
            $stmtTickets->bind_param("sssss", $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
        }
        $stmtTickets->execute();
        $resultTickets = $stmtTickets->get_result();

        $tickets = [];
        while ($row = $resultTickets->fetch_assoc()) {
            $tickets[] = [
                'Ref#' => $row['t_ref'],
                'Customer Name' => $row['t_aname'] ?? '',
                'Technician' => $row['te_technician'] ?? '',
                'Subject' => $row['t_subject'] ?? '',
                'Details' => $row['t_details'] ?? '',
                'Status' => ucfirst(strtolower($row['t_status'] ?? '')),
            ];
        }
        $stmtTickets->close();

        header('Content-Type: application/json');
        echo json_encode(['data' => $tickets]);
        exit;
    }

    // Handle delete action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token');
        }

        $t_ref = isset($_POST['t_ref']) ? trim($_POST['t_ref']) : '';
        if (empty($t_ref)) {
            throw new Exception('Invalid ticket reference');
        }

        // Delete ticket
        $sqlDelete = "DELETE FROM tbl_close_regular WHERE t_ref = ?";
        $stmtDelete = $conn->prepare($sqlDelete);
        $stmtDelete->bind_param("s", $t_ref);
        $stmtDelete->execute();

        if ($stmtDelete->affected_rows > 0) {
            // Log action
            $logDescription = "Technician $firstName $lastName deleted closed regular ticket Ref# $t_ref";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description) VALUES (NOW(), ?)";
            $stmtLog = $conn->prepare($sqlLog);
            $stmtLog->bind_param("s", $logDescription);
            $stmtLog->execute();
            $stmtLog->close();

            // Redirect to maintain pagination and search
            $redirectParams = ['page' => $page];
            if ($searchTerm) {
                $redirectParams['search'] = $searchTerm;
            }
            header("Location: regular_close.php?" . http_build_query($redirectParams));
            exit;
        } else {
            throw new Exception('Failed to delete ticket.');
        }
        $stmtDelete->close();
    }

    // Initial page load: Fetch tickets
    $searchLike = $searchTerm ? "%$searchTerm%" : null;

    // Count total tickets
    $sqlTotal = "SELECT COUNT(*) AS total FROM tbl_close_regular";
    if ($searchTerm) {
        $sqlTotal .= " WHERE t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR te_technician LIKE ? OR t_ref LIKE ?";
    }
    $stmtTotal = $conn->prepare($sqlTotal);
    if ($searchTerm) {
        $stmtTotal->bind_param("sssss", $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtTotal->execute();
    $resultTotal = $stmtTotal->get_result();
    $totalTickets = $resultTotal->fetch_assoc()['total'] ?? 0;
    $stmtTotal->close();

    // Calculate pagination
    $totalPages = max(1, ceil($totalTickets / $limit));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $limit;

    // Fetch tickets
    $sqlTickets = "SELECT t_ref, t_aname, te_technician, t_subject, t_status, t_details 
                   FROM tbl_close_regular";
    if ($searchTerm) {
        $sqlTickets .= " WHERE t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR te_technician LIKE ? OR t_ref LIKE ?";
    }
    $sqlTickets .= " ORDER BY t_ref ASC LIMIT ? OFFSET ?";
    $stmtTickets = $conn->prepare($sqlTickets);
    if ($searchTerm) {
        $stmtTickets->bind_param("sssssii", $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $limit, $offset);
    } else {
        $stmtTickets->bind_param("ii", $limit, $offset);
    }
    $stmtTickets->execute();
    $resultTickets = $stmtTickets->get_result();
    $stmtTickets->close();

} catch (Exception $e) {
    $errorMessage = htmlspecialchars($e->getMessage());
    echo "<script>alert('Error: $errorMessage');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Closed Regular Tickets</title>
    <link rel="stylesheet" href="regular_close.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <!-- Libraries for export functionality -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <style>
        .status-closed {
            color: var(--danger);
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="adminD.php"><img src="image/main.png" alt="Dashboard" class="icon" /> <span>Dashboard</span></a></li>
            <li><a href="viewU.php"><img src="image/users.png" alt="View Users" class="icon" /> <span>View Users</span></a></li>
            <li><a href="regular_close.php" class="active"><img src="image/ticket.png" alt="Regular Record" class="icon" /> <span>Regular Record</span></a></li>
            <li><a href="support_close.php"><img src="image/ticket.png" alt="Supports Record" class="icon" /> <span>Support Record</span></a></li>
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
            <h1>Closed Regular Tickets</h1>
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
                    <p>Are you sure you want to delete ticket Ref# <span id="deleteTicketRef"></span>?</p>
                </div>
                <div class="modal-footer">
                    <button class="modal-btn cancel" onclick="closeModal('deleteModal')">Cancel</button>
                    <button class="modal-btn confirm" id="confirmDeleteBtn" onclick="submitDeleteAction()">Confirm</button>
                </div>
            </div>
        </div>

        <form id="actionForm" method="POST" style="display: none;">
            <input type="hidden" name="action" id="formAction">
            <input type="hidden" name="t_ref" id="formRef">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        </form>

        <div class="table-box">
            <div class="action-buttons">
                <div class="export-container">
                    <button class="action-btn export-btn"><i class="fas fa-download"></i> Export</button>
                    <div class="export-dropdown">
                        <button onclick="exportTable('excel')">Excel</button>
                        <button onclick="exportTable('csv')">CSV</button>
                    </div>
                </div>
            </div>

            <table class="tickets-table">
                <thead>
                    <tr>
                        <th>Ticket No</th>
                        <th>Customer Name</th>
                        <th>Technician</th>
                        <th>Subject</th>
                        <th>Ticket Details</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tickets-table-body">
                    <?php
                    if ($resultTickets !== null && $resultTickets->num_rows > 0) {
                        while ($row = $resultTickets->fetch_assoc()) {
                            $ticketData = json_encode([
                                'ref' => $row['t_ref'],
                                'aname' => $row['t_aname'] ?? '',
                                'technician' => $row['te_technician'] ?? '',
                                'subject' => $row['t_subject'] ?? '',
                                'details' => $row['t_details'] ?? '',
                                'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                            ], JSON_HEX_QUOT | JSON_HEX_TAG);
                            echo "<tr>
                                    <td>" . htmlspecialchars($row['t_ref']) . "</td>
                                    <td>" . htmlspecialchars($row['t_aname'] ?? '') . "</td>
                                    <td>" . htmlspecialchars($row['te_technician'] ?? '') . "</td>
                                    <td>" . htmlspecialchars($row['t_subject'] ?? '') . "</td>
                                    <td>" . htmlspecialchars($row['t_details'] ?? '') . "</td>
                                    <td class='status-closed'>" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                    <td class='action-buttons'>
                                        <span class='view-btn' onclick='showViewModal($ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                        <span class='delete-btn' onclick=\"openDeleteModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></span>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7'>No closed regular tickets found or an error occurred.</td></tr>";
                    }
                    ?>
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

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    tbody.innerHTML = response.html;
                    updatePagination(response.currentPage, response.totalPages, response.searchTerm);
                } catch (e) {
                    console.error('Error parsing JSON:', e, xhr.responseText);
                    alert('Error loading tickets. Please try again.');
                }
            } else {
                console.error('Search request failed:', xhr.status, xhr.statusText);
                alert('Error loading tickets. Please try again.');
            }
        }
    };
    xhr.open('GET', `regular_close.php?action=search&search=${encodeURIComponent(searchTerm)}&search_page=${page}`, true);
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
    content.innerHTML = `
        <p><strong>Ref#:</strong> ${data.ref}</p>
        <p><strong>Customer Name:</strong> ${data.aname}</p>
        <p><strong>Technician:</strong> ${data.technician}</p>
        <p><strong>Subject:</strong> ${data.subject}</p>
        <p><strong>Message:</strong> ${data.details}</p>
        <p><strong>Status:</strong> <span class="status-closed">${data.status}</span></p>
    `;
    document.getElementById('viewTicketModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function openDeleteModal(t_ref) {
    document.getElementById('deleteTicketRef').textContent = t_ref;
    document.getElementById('formRef').value = t_ref;
    document.getElementById('deleteModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function submitDeleteAction() {
    const t_ref = document.getElementById('deleteTicketRef').textContent;
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Deleting...';
    
    document.getElementById('formAction').value = 'delete';
    document.getElementById('formRef').value = t_ref;
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

function exportTable(format) {
    const searchTerm = document.getElementById('searchInput').value;

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if ( xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    const data = response.data;

                    if (format === 'excel') {
                        const ws = XLSX.utils.json_to_sheet(data);
                        const wb = XLSX.utils.book_new();
                        XLSX.utils.book_append_sheet(wb, ws, 'Closed Tickets');
                        XLSX.writeFile(wb, 'closed_regular_tickets.xlsx');
                    } else if (format === 'csv') {
                        const ws = XLSX.utils.json_to_sheet(data);
                        const csv = XLSX.utils.sheet_to_csv(ws);
                        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                        saveAs(blob, 'closed_regular_tickets.csv');
                    }
                } catch (e) {
                    console.error('Error during export:', e);
                    alert('Error exporting data: ' + e.message);
                }
            } else {
                console.error('Export request failed:', xhr.status, xhr.statusText);
                alert('Error exporting data. Please try again.');
            }
        }
    };
    xhr.open('GET', `regular_close.php?action=export_data&search=${encodeURIComponent(searchTerm)}`, true);
    xhr.send();
}
</script>
</body>
</html>