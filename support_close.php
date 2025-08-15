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

   // Verify user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['userId'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header("Location: index.php");
    exit();
}


    // Initialize variables
    $firstName = $lastName = '';
    $avatarPath = 'default-avatar.png';
    $avatarFolder = 'Uploads/avatars/';
    $userAvatar = $avatarFolder . $username . '.png';
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $customerFilter = isset($_GET['customer']) ? trim($_GET['customer']) : '';
    $technicianFilter = isset($_GET['technician']) ? trim($_GET['technician']) : '';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 10; // Tickets per page
    $offset = ($page - 1) * $limit;
    $totalPages = 1; // Default to prevent undefined variable
    $result = null; // Initialize to prevent undefined variable warnings
    $customerNames = [];
    $technicianNames = [];

    // Set avatar path
    if (file_exists($userAvatar)) {
        $_SESSION['avatarPath'] = $userAvatar . '?' . time();
    } else {
        $_SESSION['avatarPath'] = 'default-avatar.png';
    }
    $avatarPath = $_SESSION['avatarPath'];

    if (!$conn) {
        die("Database connection failed.");
    }

    try {
        // Fetch user details
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
            throw new Exception("User not found.");
        }
        $stmt->close();

        // Fetch unique customer names
        $sqlCustomers = "SELECT DISTINCT CONCAT(c_fname, ' ', c_lname) AS customer_name 
                        FROM tbl_close_supp 
                        WHERE s_status = 'Closed' AND c_fname IS NOT NULL AND c_lname IS NOT NULL 
                        ORDER BY customer_name";
        $resultCustomers = $conn->query($sqlCustomers);
        while ($row = $resultCustomers->fetch_assoc()) {
            $customerNames[] = $row['customer_name'];
        }

        // Fetch unique technician names
        $sqlTechnicians = "SELECT DISTINCT te_technician 
                        FROM tbl_close_supp 
                        WHERE s_status = 'Closed' AND te_technician IS NOT NULL AND te_technician != '' 
                        ORDER BY te_technician";
        $resultTechnicians = $conn->query($sqlTechnicians);
        while ($row = $resultTechnicians->fetch_assoc()) {
            $technicianNames[] = $row['te_technician'];
        }

        // Handle AJAX search request
        if (isset($_GET['action']) && $_GET['action'] === 'search') {
            $searchLike = $searchTerm ? "%$searchTerm%" : null;
            $params = [];
            $types = '';
            $whereClauses = ["s_status = 'Closed'"];

            // Build WHERE clause
            if ($searchTerm) {
                $whereClauses[] = "(s_ref LIKE ? OR c_id LIKE ? OR CONCAT(c_fname, ' ', c_lname) LIKE ? OR te_technician LIKE ? OR s_subject LIKE ? OR s_message LIKE ?)";
                $params = array_fill(0, 6, $searchLike);
                $types .= 'ssssss';
            }
            if ($customerFilter) {
                $whereClauses[] = "CONCAT(c_fname, ' ', c_lname) = ?";
                $params[] = $customerFilter;
                $types .= 's';
            }
            if ($technicianFilter) {
                $whereClauses[] = "te_technician = ?";
                $params[] = $technicianFilter;
                $types .= 's';
            }

            // Count total closed tickets
            $sqlCount = "SELECT COUNT(*) as total FROM tbl_close_supp";
            if ($whereClauses) {
                $sqlCount .= " WHERE " . implode(' AND ', $whereClauses);
            }
            $stmtCount = $conn->prepare($sqlCount);
            if ($params) {
                $stmtCount->bind_param($types, ...$params);
            }
            $stmtCount->execute();
            $resultCount = $stmtCount->get_result();
            $totalTickets = $resultCount->fetch_assoc()['total'] ?? 0;
            $totalPages = max(1, ceil($totalTickets / $limit));
            $stmtCount->close();

            // Fetch closed support tickets
            $sql = "SELECT s_ref, c_id, c_fname, c_lname, te_technician, s_subject, s_message, s_status, s_date 
                    FROM tbl_close_supp";
            if ($whereClauses) {
                $sql .= " WHERE " . implode(' AND ', $whereClauses);
            }
            $sql .= " ORDER BY s_date DESC LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($sql);
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
            $stmt->bind_param($types, ...$params);
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
                    'date_closed' => $row['s_date'] ?? ''
                ], JSON_HEX_QUOT | JSON_HEX_TAG);
                echo "<tr>
                        <td>" . htmlspecialchars($row['s_ref']) . "</td>
                        <td>" . htmlspecialchars($row['c_id']) . "</td>
                        <td>" . htmlspecialchars(($row['c_fname'] ?? '') . ' ' . ($row['c_lname'] ?? '')) . "</td>
                        <td>" . htmlspecialchars($row['te_technician'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['s_subject'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['s_message'] ?? '') . "</td>
                        <td class='status-closed'>" . htmlspecialchars($row['s_status'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['s_date'] ?? '') . "</td>
                        <td class='action-buttons'>
                            <span class='view-btn' onclick='showViewModal($ticketData)' title='View'><i class='fas fa-eye'></i></span>
                            <span class='delete-btn' onclick=\"openDeleteModal('" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></span>
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
                'searchTerm' => $searchTerm,
                'customerFilter' => $customerFilter,
                'technicianFilter' => $technicianFilter
            ]);
            exit;
        }

        // Handle AJAX export data request
        if (isset($_GET['action']) && $_GET['action'] === 'export_data') {
            $searchLike = $searchTerm ? "%$searchTerm%" : null;
            $params = [];
            $types = '';
            $whereClauses = ["s_status = 'Closed'"];

            if ($searchTerm) {
                $whereClauses[] = "(s_ref LIKE ? OR c_id LIKE ? OR CONCAT(c_fname, ' ', c_lname) LIKE ? OR te_technician LIKE ? OR s_subject LIKE ? OR s_message LIKE ?)";
                $params = array_fill(0, 6, $searchLike);
                $types .= 'ssssss';
            }
            if ($customerFilter) {
                $whereClauses[] = "CONCAT(c_fname, ' ', c_lname) = ?";
                $params[] = $customerFilter;
                $types .= 's';
            }
            if ($technicianFilter) {
                $whereClauses[] = "te_technician = ?";
                $params[] = $technicianFilter;
                $types .= 's';
            }

            // Fetch all tickets for export
            $sqlTickets = "SELECT s_ref, c_id, c_fname, c_lname, te_technician, s_subject, s_message, s_status, s_date 
                        FROM tbl_close_supp";
            if ($whereClauses) {
                $sqlTickets .= " WHERE " . implode(' AND ', $whereClauses);
            }
            $sqlTickets .= " ORDER BY s_date DESC";
            $stmtTickets = $conn->prepare($sqlTickets);
            if ($params) {
                $stmtTickets->bind_param($types, ...$params);
            }
            $stmtTickets->execute();
            $resultTickets = $stmtTickets->get_result();

            $tickets = [];
            while ($row = $resultTickets->fetch_assoc()) {
                $tickets[] = [
                    'Ticket No.' => $row['s_ref'],
                    'Customer ID' => $row['c_id'],
                    'Customer Name' => ($row['c_fname'] ?? '') . ' ' . ($row['c_lname'] ?? ''),
                    'Technician' => $row['te_technician'] ?? '',
                    'Subject' => $row['s_subject'] ?? '',
                    'Details' => $row['s_message'] ?? '',
                    'Status' => $row['s_status'] ?? '',
                    'Date Closed' => $row['s_date'] ?? ''
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

            $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_SANITIZE_STRING);
            if (empty($ticketId)) {
                throw new Exception('Invalid ticket ID');
            }

            $sql = "DELETE FROM tbl_close_supp WHERE s_ref = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $ticketId);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                // Log action
                $logDescription = "Admin $username deleted closed support ticket ID $ticketId";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description) VALUES (NOW(), ?)";
                $stmtLog = $conn->prepare($sqlLog);
                $stmtLog->bind_param("s", $logDescription);
                $stmtLog->execute();
                $stmtLog->close();

                // Redirect to maintain pagination and filters
                $redirectParams = ['page' => $page];
                if ($searchTerm) {
                    $redirectParams['search'] = $searchTerm;
                }
                if ($customerFilter) {
                    $redirectParams['customer'] = $customerFilter;
                }
                if ($technicianFilter) {
                    $redirectParams['technician'] = $technicianFilter;
                }
                header("Location: support_close.php?" . http_build_query($redirectParams));
                exit;
            } else {
                throw new Exception('Error deleting ticket');
            }
            $stmt->close();
        }

        // Initial page load: Fetch tickets
        $searchLike = $searchTerm ? "%$searchTerm%" : null;
        $params = [];
        $types = '';
        $whereClauses = ["s_status = 'Closed'"];

        if ($searchTerm) {
            $whereClauses[] = "(s_ref LIKE ? OR c_id LIKE ? OR CONCAT(c_fname, ' ', c_lname) LIKE ? OR te_technician LIKE ? OR s_subject LIKE ? OR s_message LIKE ?)";
            $params = array_fill(0, 6, $searchLike);
            $types .= 'ssssss';
        }
        if ($customerFilter) {
            $whereClauses[] = "CONCAT(c_fname, ' ', c_lname) = ?";
            $params[] = $customerFilter;
            $types .= 's';
        }
        if ($technicianFilter) {
            $whereClauses[] = "te_technician = ?";
            $params[] = $technicianFilter;
            $types .= 's';
        }

        // Count total closed tickets
        $sqlCount = "SELECT COUNT(*) as total FROM tbl_close_supp";
        if ($whereClauses) {
            $sqlCount .= " WHERE " . implode(' AND ', $whereClauses);
        }
        $stmtCount = $conn->prepare($sqlCount);
        if ($params) {
            $stmtCount->bind_param($types, ...$params);
        }
        $stmtCount->execute();
        $resultCount = $stmtCount->get_result();
        $totalTickets = $resultCount->fetch_assoc()['total'] ?? 0;
        $totalPages = max(1, ceil($totalTickets / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;
        $stmtCount->close();

        // Fetch closed support tickets
        $closedTickets = [];
        $sql = "SELECT s_ref, c_id, c_fname, c_lname, te_technician, s_subject, s_message, s_status, s_date 
                FROM tbl_close_supp";
        if ($whereClauses) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        $sql .= " ORDER BY s_date DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $closedTickets[] = $row;
        }
        $stmt->close();

    } catch (Exception $e) {
        $errorMessage = htmlspecialchars($e->getMessage());
        echo "<script>alert('Error: $errorMessage');</script>";
        error_log("Error in support_close.php: " . $e->getMessage());
    }

    $conn->close();
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard | Support Tickets Record</title>
        <link rel="stylesheet" href="support_close.css"> 
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
        <style>
            .status-closed {
                color: var(--danger);
            }
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
            th .filter-btn {
                background: transparent !important;
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
                <li><a href="regular_close.php"><img src="image/ticket.png" alt="Regular Record" class="icon" /> <span>Regular Record</span></a></li>
                <li><a href="support_close.php" class="active"><img src="image/ticket.png" alt="Supports Record" class="icon" /> <span>Support Record</span></a></li>
                <li><a href="logs.php"><img src="image/log.png" alt="Logs" class="icon" /> <span>Logs</span></a></li>
                <li><a href="returnT.php"><img src="image/record.png" alt="Returned Records" class="icon" /> <span>Returned Records</span></a></li>
                <li><a href="deployedT.php"><img src="image/record.png" alt="Deployed Records" class="icon" /> <span>Deployed Records</span></a></li>
                <li><a href="AdminPayments.php"><img src="image/transactions.png" alt="Payment Transactions" class="icon" /> <span>Payment Transactions</span></a></li>
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

            <!-- Customer Filter Modal -->
            <div id="customerFilterModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Filter by Customer</h2>
                    </div>
                    <form id="customerFilterForm" class="modal-form">
                        <label for="customer_filter">Select Customer Name</label>
                        <select name="customer_filter" id="customer_filter">
                            <option value="">All Customers</option>
                            <?php foreach ($customerNames as $customer): ?>
                                <option value="<?php echo htmlspecialchars($customer, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $customerFilter === $customer ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('customerFilterModal')">Cancel</button>
                            <button type="button" class="modal-btn confirm" onclick="applyCustomerFilter()">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Technician Filter Modal -->
            <div id="technicianFilterModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Filter by Technician</h2>
                    </div>
                    <form id="technicianFilterForm" class="modal-form">
                        <label for="technician_filter">Select Technician Name</label>
                        <select name="technician_filter" id="technician_filter">
                            <option value="">All Technicians</option>
                            <?php foreach ($technicianNames as $technician): ?>
                                <option value="<?php echo htmlspecialchars($technician, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $technicianFilter === $technician ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($technician, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('technicianFilterModal')">Cancel</button>
                            <button type="button" class="modal-btn confirm" onclick="applyTechnicianFilter()">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <form id="actionForm" method="POST" style="display: none;">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="ticket_id" id="formTicketId">
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
                            <th>Ticket No.</th>
                            <th>ID</th>
                            <th>Customer Name<button class="filter-btn" onclick="showCustomerFilterModal()"><i class='bx bx-filter'></i></button></th>
                            <th>Technician<button class="filter-btn" onclick="showTechnicianFilterModal()"><i class='bx bx-filter'></i></button></th>
                            <th>Subject</th>
                            <th>Ticket Details</th>
                            <th>Status</th>
                            <th>Date Closed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tickets-table-body">
                        <?php if ($result === null || $result->num_rows === 0): ?>
                            <tr>
                                <td colspan="9">No closed support tickets found or an error occurred.</td>
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
                                    'date_closed' => $ticket['s_date'] ?? ''
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
                                    <td><?php echo htmlspecialchars($ticket['s_date'] ?? ''); ?></td>
                                    <td class="action-buttons">
                                        <span class="view-btn" onclick='showViewModal(<?php echo $ticketData; ?>)' title="View"><i class="fas fa-eye"></i></span>
                                        <span class="delete-btn" onclick="openDeleteModal('<?php echo htmlspecialchars($ticket['s_ref'], ENT_QUOTES, 'UTF-8'); ?>')" title="Delete"><i class="fas fa-trash"></i></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination" id="tickets-pagination">
                    <?php
                    $paginationParams = [];
                    if ($searchTerm) {
                        $paginationParams['search'] = $searchTerm;
                    }
                    if ($customerFilter) {
                        $paginationParams['customer'] = $customerFilter;
                    }
                    if ($technicianFilter) {
                        $paginationParams['technician'] = $technicianFilter;
                    }
                    if ($page > 1) {
                        $paginationParams['page'] = $page - 1;
                        echo "<a href='support_close.php?" . http_build_query($paginationParams) . "' class='pagination-link'><i class='fas fa-chevron-left'></i></a>";
                    } else {
                        echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                    }
                    echo "<span class='current-page'>Page $page of $totalPages</span>";
                    if ($page < $totalPages) {
                        $paginationParams['page'] = $page + 1;
                        echo "<a href='support_close.php?" . http_build_query($paginationParams) . "' class='pagination-link'><i class='fas fa-chevron-right'></i></a>";
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
        const customerFilter = '<?php echo addslashes($customerFilter); ?>';
        const technicianFilter = '<?php echo addslashes($technicianFilter); ?>';
        const tbody = document.getElementById('tickets-table-body');
        const paginationContainer = document.getElementById('tickets-pagination');

        const params = new URLSearchParams();
        params.append('action', 'search');
        params.append('page', page);
        if (searchTerm) params.append('search', searchTerm);
        if (customerFilter) params.append('customer', customerFilter);
        if (technicianFilter) params.append('technician', technicianFilter);

        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        tbody.innerHTML = response.html;
                        updatePagination(response.currentPage, response.totalPages, response.searchTerm, response.customerFilter, response.technicianFilter);
                        updateURL(response.currentPage, response.searchTerm, response.customerFilter, response.technicianFilter);
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
        xhr.open('GET', `support_close.php?${params.toString()}`, true);
        xhr.send();
    }

    function updatePagination(currentPage, totalPages, searchTerm, customerFilter, technicianFilter) {
        const paginationContainer = document.getElementById('tickets-pagination');
        let paginationHtml = '';
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (customerFilter) params.append('customer', customerFilter);
        if (technicianFilter) params.append('technician', technicianFilter);

        if (currentPage > 1) {
            params.set('page', currentPage - 1);
            paginationHtml += `<a href="support_close.php?${params.toString()}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
        } else {
            paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
        }

        paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

        if (currentPage < totalPages) {
            params.set('page', currentPage + 1);
            paginationHtml += `<a href="support_close.php?${params.toString()}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
        } else {
            paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
        }

        paginationContainer.innerHTML = paginationHtml;
    }

    function updateURL(page, searchTerm, customerFilter, technicianFilter) {
        const params = new URLSearchParams();
        params.append('page', page);
        if (searchTerm) params.append('search', searchTerm);
        if (customerFilter) params.append('customer', customerFilter);
        if (technicianFilter) params.append('technician', technicianFilter);
        const newUrl = `support_close.php?${params.toString()}`;
        window.history.pushState({}, '', newUrl);
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
            <p><strong>Date Closed:</strong> ${data.date_closed}</p>
        `;
        document.getElementById('viewTicketModal').style.display = 'block';
        document.body.classList.add('modal-open');
    }

    function openDeleteModal(ticketId) {
        document.getElementById('deleteTicketId').textContent = ticketId;
        document.getElementById('formTicketId').value = ticketId;
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

    function showCustomerFilterModal() {
        const modal = document.getElementById('customerFilterModal');
        modal.style.display = 'block';
        document.body.classList.add('modal-open');
    }

    function showTechnicianFilterModal() {
        const modal = document.getElementById('technicianFilterModal');
        modal.style.display = 'block';
        document.body.classList.add('modal-open');
    }

    function applyCustomerFilter() {
        const customerFilter = document.getElementById('customer_filter').value;
        const searchTerm = document.getElementById('searchInput').value;
        const technicianFilter = '<?php echo addslashes($technicianFilter); ?>';
        const params = new URLSearchParams();
        params.append('page', 1);
        if (searchTerm) params.append('search', searchTerm);
        if (customerFilter) params.append('customer', customerFilter);
        if (technicianFilter) params.append('technician', technicianFilter);
        window.location.href = `support_close.php?${params.toString()}`;
    }

    function applyTechnicianFilter() {
        const technicianFilter = document.getElementById('technician_filter').value;
        const searchTerm = document.getElementById('searchInput').value;
        const customerFilter = '<?php echo addslashes($customerFilter); ?>';
        const params = new URLSearchParams();
        params.append('page', 1);
        if (searchTerm) params.append('search', searchTerm);
        if (customerFilter) params.append('customer', customerFilter);
        if (technicianFilter) params.append('technician', technicianFilter);
        window.location.href = `support_close.php?${params.toString()}`;
    }

    function exportTable(format) {
        const searchTerm = document.getElementById('searchInput').value;
        const customerFilter = '<?php echo addslashes($customerFilter); ?>';
        const technicianFilter = '<?php echo addslashes($technicianFilter); ?>';

        const params = new URLSearchParams();
        params.append('action', 'export_data');
        if (searchTerm) params.append('search', searchTerm);
        if (customerFilter) params.append('customer', customerFilter);
        if (technicianFilter) params.append('technician', technicianFilter);

        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        const data = response.data;

                        if (format === 'excel') {
                            const ws = XLSX.utils.json_to_sheet(data);
                            const wb = XLSX.utils.book_new();
                            XLSX.utils.book_append_sheet(wb, ws, 'Closed Support Tickets');
                            XLSX.writeFile(wb, 'closed_support_tickets.xlsx');
                        } else if (format === 'csv') {
                            const ws = XLSX.utils.json_to_sheet(data);
                            const csv = XLSX.utils.sheet_to_csv(ws);
                            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                            saveAs(blob, 'closed_support_tickets.csv');
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
        xhr.open('GET', `support_close.php?${params.toString()}`, true);
        xhr.send();
    }
    </script>
    </body>
    </html>

