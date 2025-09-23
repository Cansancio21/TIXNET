<?php
session_start();
include 'db.php';

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
$customerFilter = isset($_GET['customer']) ? trim($_GET['customer']) : '';
$technicianFilter = isset($_GET['technician']) ? trim($_GET['technician']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalPages = 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'regular';
$resultTickets = null;
$customerNames = [];
$technicianNames = [];
$supportCustomerNames = [];
$supportTechnicianNames = [];
$totalRegularTickets = 0;
$totalSupportTickets = 0;

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

    // Count total tickets for each tab
    $sqlRegularCount = "SELECT COUNT(*) AS total FROM tbl_close_regular";
    $resultRegularCount = $conn->query($sqlRegularCount);
    $totalRegularTickets = $resultRegularCount->fetch_assoc()['total'] ?? 0;

    $sqlSupportCount = "SELECT COUNT(*) AS total FROM tbl_close_supp";
    $resultSupportCount = $conn->query($sqlSupportCount);
    $totalSupportTickets = $resultSupportCount->fetch_assoc()['total'] ?? 0;

    // Fetch unique customer names for regular tickets
    $sqlCustomers = "SELECT DISTINCT t_aname FROM tbl_close_regular WHERE t_aname IS NOT NULL AND t_aname != '' ORDER BY t_aname";
    $resultCustomers = $conn->query($sqlCustomers);
    while ($row = $resultCustomers->fetch_assoc()) {
        $customerNames[] = $row['t_aname'];
    }

    // Fetch unique technician names for regular tickets
    $sqlTechnicians = "SELECT DISTINCT te_technician FROM tbl_close_regular WHERE te_technician IS NOT NULL AND te_technician != '' ORDER BY te_technician";
    $resultTechnicians = $conn->query($sqlTechnicians);
    while ($row = $resultTechnicians->fetch_assoc()) {
        $technicianNames[] = $row['te_technician'];
    }

    // Fetch unique customer names for support tickets
    $sqlSupportCustomers = "SELECT DISTINCT CONCAT(c.c_fname, ' ', c.c_lname) as customer_name FROM tbl_close_supp s JOIN tbl_customer c ON s.c_id = c.c_id WHERE CONCAT(c.c_fname, ' ', c.c_lname) != '' ORDER BY customer_name";
    $resultSupportCustomers = $conn->query($sqlSupportCustomers);
    while ($row = $resultSupportCustomers->fetch_assoc()) {
        $supportCustomerNames[] = $row['customer_name'];
    }

    // Fetch unique technician names for support tickets
    $sqlSupportTechnicians = "SELECT DISTINCT te_technician FROM tbl_close_supp WHERE te_technician IS NOT NULL AND te_technician != '' ORDER BY te_technician";
    $resultSupportTechnicians = $conn->query($sqlSupportTechnicians);
    while ($row = $resultSupportTechnicians->fetch_assoc()) {
        $supportTechnicianNames[] = $row['te_technician'];
    }

    // Handle AJAX search request
    if (isset($_GET['action']) && $_GET['action'] === 'search') {
        $searchLike = $searchTerm ? "%$searchTerm%" : null;
        $params = [];
        $types = '';
        $whereClauses = [];

        if ($currentTab === 'regular') {
            // Build WHERE clause for regular tickets
            if ($searchTerm) {
                $whereClauses[] = "(t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR te_technician LIKE ? OR t_ref LIKE ?)";
                $params = array_fill(0, 5, $searchLike);
                $types .= 'sssss';
            }
            if ($customerFilter) {
                $whereClauses[] = "t_aname = ?";
                $params[] = $customerFilter;
                $types .= 's';
            }
            if ($technicianFilter) {
                $whereClauses[] = "te_technician = ?";
                $params[] = $technicianFilter;
                $types .= 's';
            }

            // Count total regular tickets
            $sqlTotal = "SELECT COUNT(*) AS total FROM tbl_close_regular";
            if ($whereClauses) {
                $sqlTotal .= " WHERE " . implode(' AND ', $whereClauses);
            }
            $stmtTotal = $conn->prepare($sqlTotal);
            if ($params) {
                $stmtTotal->bind_param($types, ...$params);
            }
            $stmtTotal->execute();
            $resultTotal = $stmtTotal->get_result();
            $totalTickets = $resultTotal->fetch_assoc()['total'] ?? 0;
            $stmtTotal->close();

            $totalPages = max(1, ceil($totalTickets / $limit));

            // Fetch regular tickets
            $sqlTickets = "SELECT t_ref, t_aname, te_technician, t_subject, t_status, t_details, te_date 
                           FROM tbl_close_regular";
            if ($whereClauses) {
                $sqlTickets .= " WHERE " . implode(' AND ', $whereClauses);
            }
            $sqlTickets .= " ORDER BY t_ref ASC LIMIT ? OFFSET ?";
            $stmtTickets = $conn->prepare($sqlTickets);
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
            $stmtTickets->bind_param($types, ...$params);
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
                    'closed_date' => $row['te_date'] ?? 'N/A'
                ], JSON_HEX_QUOT | JSON_HEX_TAG);
                echo "<tr>
                        <td>" . htmlspecialchars($row['t_ref']) . "</td>
                        <td>" . htmlspecialchars($row['t_aname'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['te_technician'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['t_subject'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['t_details'] ?? '') . "</td>
                        <td class='status-closed'>" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                        <td>" . htmlspecialchars($row['te_date'] ?? 'N/A') . "</td>
                        <td class='action-buttons'>
                            <span class='view-btn' onclick='showViewModal($ticketData)' title='View'><i class='fas fa-eye'></i></span>
                            <span class='delete-btn' onclick=\"openDeleteModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', 'regular')\" title='Delete'><i class='fas fa-trash'></i></span>
                        </td>
                      </tr>";
            }
            if ($resultTickets->num_rows === 0) {
                echo "<tr><td colspan='8'>No closed regular tickets found.</td></tr>";
            }
        } else {
            // Build WHERE clause for support tickets - FIXED: Added table aliases
            if ($searchTerm) {
                $whereClauses[] = "(CONCAT(c.c_fname, ' ', c.c_lname) LIKE ? OR s.s_subject LIKE ? OR s.s_message LIKE ? OR s.te_technician LIKE ? OR s.s_ref LIKE ?)";
                $params = array_fill(0, 5, $searchLike);
                $types .= 'sssss';
            }
            if ($customerFilter) {
                $whereClauses[] = "CONCAT(c.c_fname, ' ', c.c_lname) = ?";
                $params[] = $customerFilter;
                $types .= 's';
            }
            if ($technicianFilter) {
                $whereClauses[] = "s.te_technician = ?";
                $params[] = $technicianFilter;
                $types .= 's';
            }

            // Count total support tickets - FIXED: Added table aliases
            $sqlTotal = "SELECT COUNT(*) AS total FROM tbl_close_supp s JOIN tbl_customer c ON s.c_id = c.c_id";
            if ($whereClauses) {
                $sqlTotal .= " WHERE " . implode(' AND ', $whereClauses);
            }
            $stmtTotal = $conn->prepare($sqlTotal);
            if ($params) {
                $stmtTotal->bind_param($types, ...$params);
            }
            $stmtTotal->execute();
            $resultTotal = $stmtTotal->get_result();
            $totalTickets = $resultTotal->fetch_assoc()['total'] ?? 0;
            $stmtTotal->close();

            $totalPages = max(1, ceil($totalTickets / $limit));

            // Fetch support tickets - ALREADY HAD PROPER ALIASES
            $sqlTickets = "SELECT s.s_ref, CONCAT(c.c_fname, ' ', c.c_lname) as customer_name, s.te_technician, s.s_subject, s.s_status, s.s_message, s.s_date 
                           FROM tbl_close_supp s JOIN tbl_customer c ON s.c_id = c.c_id";
            if ($whereClauses) {
                $sqlTickets .= " WHERE " . implode(' AND ', $whereClauses);
            }
            $sqlTickets .= " ORDER BY s.s_ref ASC LIMIT ? OFFSET ?";
            $stmtTickets = $conn->prepare($sqlTickets);
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
            $stmtTickets->bind_param($types, ...$params);
            $stmtTickets->execute();
            $resultTickets = $stmtTickets->get_result();

            ob_start();
            while ($row = $resultTickets->fetch_assoc()) {
                $ticketData = json_encode([
                    'ref' => $row['s_ref'],
                    'aname' => $row['customer_name'] ?? '',
                    'technician' => $row['te_technician'] ?? '',
                    'subject' => $row['s_subject'] ?? '',
                    'details' => $row['s_message'] ?? '',
                    'status' => ucfirst(strtolower($row['s_status'] ?? '')),
                    'closed_date' => $row['s_date'] ?? 'N/A'
                ], JSON_HEX_QUOT | JSON_HEX_TAG);
                echo "<tr>
                        <td>" . htmlspecialchars($row['s_ref']) . "</td>
                        <td>" . htmlspecialchars($row['customer_name'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['te_technician'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['s_subject'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['s_message'] ?? '') . "</td>
                        <td class='status-closed'>" . ucfirst(strtolower($row['s_status'] ?? '')) . "</td>
                        <td>" . htmlspecialchars($row['s_date'] ?? 'N/A') . "</td>
                        <td class='action-buttons'>
                            <span class='view-btn' onclick='showSupportViewModal($ticketData)' title='View'><i class='fas fa-eye'></i></span>
                            <span class='delete-btn' onclick=\"openSupportDeleteModal('" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></span>
                        </td>
                      </tr>";
            }
            if ($resultTickets->num_rows === 0) {
                echo "<tr><td colspan='8'>No closed support tickets found.</td></tr>";
            }
        }

        $html = ob_get_clean();
        $stmtTickets->close();

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

    // Handle AJAX export data request - FIXED: Added table aliases for support
    if (isset($_GET['action']) && $_GET['action'] === 'export_data') {
        $searchLike = $searchTerm ? "%$searchTerm%" : null;
        $params = [];
        $types = '';
        $whereClauses = [];

        if ($currentTab === 'regular') {
            if ($searchTerm) {
                $whereClauses[] = "(t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR te_technician LIKE ? OR t_ref LIKE ?)";
                $params = array_fill(0, 5, $searchLike);
                $types .= 'sssss';
            }
            if ($customerFilter) {
                $whereClauses[] = "t_aname = ?";
                $params[] = $customerFilter;
                $types .= 's';
            }
            if ($technicianFilter) {
                $whereClauses[] = "te_technician = ?";
                $params[] = $technicianFilter;
                $types .= 's';
            }

            // Fetch all regular tickets for export
            $sqlTickets = "SELECT t_ref, t_aname, te_technician, t_subject, t_status, t_details, te_date 
                           FROM tbl_close_regular";
            if ($whereClauses) {
                $sqlTickets .= " WHERE " . implode(' AND ', $whereClauses);
            }
            $sqlTickets .= " ORDER BY t_ref ASC";
            $stmtTickets = $conn->prepare($sqlTickets);
            if ($params) {
                $stmtTickets->bind_param($types, ...$params);
            }
        } else {
            if ($searchTerm) {
                $whereClauses[] = "(CONCAT(c.c_fname, ' ', c.c_lname) LIKE ? OR s.s_subject LIKE ? OR s.s_message LIKE ? OR s.te_technician LIKE ? OR s.s_ref LIKE ?)";
                $params = array_fill(0, 5, $searchLike);
                $types .= 'sssss';
            }
            if ($customerFilter) {
                $whereClauses[] = "CONCAT(c.c_fname, ' ', c.c_lname) = ?";
                $params[] = $customerFilter;
                $types .= 's';
            }
            if ($technicianFilter) {
                $whereClauses[] = "s.te_technician = ?";
                $params[] = $technicianFilter;
                $types .= 's';
            }

            // Fetch all support tickets for export
            $sqlTickets = "SELECT s.s_ref, CONCAT(c.c_fname, ' ', c.c_lname) as customer_name, s.te_technician, s.s_subject, s.s_status, s.s_message, s.s_date 
                           FROM tbl_close_supp s JOIN tbl_customer c ON s.c_id = c.c_id";
            if ($whereClauses) {
                $sqlTickets .= " WHERE " . implode(' AND ', $whereClauses);
            }
            $sqlTickets .= " ORDER BY s.s_ref ASC";
            $stmtTickets = $conn->prepare($sqlTickets);
            if ($params) {
                $stmtTickets->bind_param($types, ...$params);
            }
        }

        $stmtTickets->execute();
        $resultTickets = $stmtTickets->get_result();

        $tickets = [];
        while ($row = $resultTickets->fetch_assoc()) {
            if ($currentTab === 'regular') {
                $tickets[] = [
                    'Ref#' => $row['t_ref'],
                    'Customer Name' => $row['t_aname'] ?? '',
                    'Technician' => $row['te_technician'] ?? '',
                    'Subject' => $row['t_subject'] ?? '',
                    'Details' => $row['t_details'] ?? '',
                    'Status' => ucfirst(strtolower($row['t_status'] ?? '')),
                    'Closed Date' => $row['te_date'] ?? 'N/A'
                ];
            } else {
                $tickets[] = [
                    'Ref#' => $row['s_ref'],
                    'Customer Name' => $row['customer_name'] ?? '',
                    'Technician' => $row['te_technician'] ?? '',
                    'Subject' => $row['s_subject'] ?? '',
                    'Details' => $row['s_message'] ?? '',
                    'Status' => ucfirst(strtolower($row['s_status'] ?? '')),
                    'Closed Date' => $row['s_date'] ?? 'N/A'
                ];
            }
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

        $ref = isset($_POST['ref']) ? trim($_POST['ref']) : '';
        $table = isset($_POST['table']) ? $_POST['table'] : '';
        
        if (empty($ref) || !in_array($table, ['regular', 'support'])) {
            throw new Exception('Invalid ticket reference or table');
        }

        if ($table === 'regular') {
            $sqlDelete = "DELETE FROM tbl_close_regular WHERE t_ref = ?";
        } else {
            $sqlDelete = "DELETE FROM tbl_close_supp WHERE s_ref = ?";
        }

        $stmtDelete = $conn->prepare($sqlDelete);
        $stmtDelete->bind_param("s", $ref);
        $stmtDelete->execute();

        if ($stmtDelete->affected_rows > 0) {
            // Log action
            $logDescription = "Admin $firstName $lastName deleted closed $table ticket Ref# $ref";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description) VALUES (NOW(), ?)";
            $stmtLog = $conn->prepare($sqlLog);
            $stmtLog->bind_param("s", $logDescription);
            $stmtLog->execute();
            $stmtLog->close();

            // Redirect to maintain pagination and filters
            $redirectParams = ['page' => $page, 'tab' => $currentTab];
            if ($searchTerm) {
                $redirectParams['search'] = $searchTerm;
            }
            if ($customerFilter) {
                $redirectParams['customer'] = $customerFilter;
            }
            if ($technicianFilter) {
                $redirectParams['technician'] = $technicianFilter;
            }
            header("Location: regular_close.php?" . http_build_query($redirectParams));
            exit;
        } else {
            throw new Exception('Failed to delete ticket.');
        }
        $stmtDelete->close();
    }

    // Initial page load: Fetch tickets based on current tab - FIXED: Added table aliases for support
    if ($currentTab === 'regular') {
        $searchLike = $searchTerm ? "%$searchTerm%" : null;
        $params = [];
        $types = '';
        $whereClauses = [];

        if ($searchTerm) {
            $whereClauses[] = "(t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ? OR te_technician LIKE ? OR t_ref LIKE ?)";
            $params = array_fill(0, 5, $searchLike);
            $types .= 'sssss';
        }
        if ($customerFilter) {
            $whereClauses[] = "t_aname = ?";
            $params[] = $customerFilter;
            $types .= 's';
        }
        if ($technicianFilter) {
            $whereClauses[] = "te_technician = ?";
            $params[] = $technicianFilter;
            $types .= 's';
        }

        // Count total regular tickets
        $sqlTotal = "SELECT COUNT(*) AS total FROM tbl_close_regular";
        if ($whereClauses) {
            $sqlTotal .= " WHERE " . implode(' AND ', $whereClauses);
        }
        $stmtTotal = $conn->prepare($sqlTotal);
        if ($params) {
            $stmtTotal->bind_param($types, ...$params);
        }
        $stmtTotal->execute();
        $resultTotal = $stmtTotal->get_result();
        $totalTickets = $resultTotal->fetch_assoc()['total'] ?? 0;
        $stmtTotal->close();

        $totalPages = max(1, ceil($totalTickets / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        // Fetch regular tickets
        $sqlTickets = "SELECT t_ref, t_aname, te_technician, t_subject, t_status, t_details, te_date 
                       FROM tbl_close_regular";
        if ($whereClauses) {
            $sqlTickets .= " WHERE " . implode(' AND ', $whereClauses);
        }
        $sqlTickets .= " ORDER BY t_ref ASC LIMIT ? OFFSET ?";
        $stmtTickets = $conn->prepare($sqlTickets);
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        $stmtTickets->bind_param($types, ...$params);
        $stmtTickets->execute();
        $resultTickets = $stmtTickets->get_result();
        $stmtTickets->close();
    } else {
        $searchLike = $searchTerm ? "%$searchTerm%" : null;
        $params = [];
        $types = '';
        $whereClauses = [];

        if ($searchTerm) {
            $whereClauses[] = "(CONCAT(c.c_fname, ' ', c.c_lname) LIKE ? OR s.s_subject LIKE ? OR s.s_message LIKE ? OR s.te_technician LIKE ? OR s.s_ref LIKE ?)";
            $params = array_fill(0, 5, $searchLike);
            $types .= 'sssss';
        }
        if ($customerFilter) {
            $whereClauses[] = "CONCAT(c.c_fname, ' ', c.c_lname) = ?";
            $params[] = $customerFilter;
            $types .= 's';
        }
        if ($technicianFilter) {
            $whereClauses[] = "s.te_technician = ?";
            $params[] = $technicianFilter;
            $types .= 's';
        }

        // Count total support tickets
        $sqlTotal = "SELECT COUNT(*) AS total FROM tbl_close_supp s JOIN tbl_customer c ON s.c_id = c.c_id";
        if ($whereClauses) {
            $sqlTotal .= " WHERE " . implode(' AND ', $whereClauses);
        }
        $stmtTotal = $conn->prepare($sqlTotal);
        if ($params) {
            $stmtTotal->bind_param($types, ...$params);
        }
        $stmtTotal->execute();
        $resultTotal = $stmtTotal->get_result();
        $totalTickets = $resultTotal->fetch_assoc()['total'] ?? 0;
        $stmtTotal->close();

        $totalPages = max(1, ceil($totalTickets / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        // Fetch support tickets
        $sqlTickets = "SELECT s.s_ref, CONCAT(c.c_fname, ' ', c.c_lname) as customer_name, s.te_technician, s.s_subject, s.s_status, s.s_message, s.s_date 
                       FROM tbl_close_supp s JOIN tbl_customer c ON s.c_id = c.c_id";
        if ($whereClauses) {
            $sqlTickets .= " WHERE " . implode(' AND ', $whereClauses);
        }
        $sqlTickets .= " ORDER BY s.s_ref ASC LIMIT ? OFFSET ?";
        $stmtTickets = $conn->prepare($sqlTickets);
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        $stmtTickets->bind_param($types, ...$params);
        $stmtTickets->execute();
        $resultTickets = $stmtTickets->get_result();
        $stmtTickets->close();
    }

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
    <title>Closed Tickets</title>
    <link rel="stylesheet" href="regular_closes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

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
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        .modal-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 1.5em;
        }
        .modal-body {
            margin: 20px 0;
        }
        .modal-footer {
            text-align: right;
            margin-top: 15px;
        }
        .modal-btn {
            padding: 8px 20px;
            border-radius: 20px;
            border: none;
            cursor: pointer;
            margin-left: 10px;
            transition: all 0.3s;
        }
        .modal-btn.cancel {
            background: var(--primary);
            color: var(--light);
        }
        .modal-btn.confirm {
            background: var(--primary);
            color: white;
        }
        .modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .modal-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .modal-form select {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        #customerFilterModal .modal-content,
        #technicianFilterModal .modal-content {
            margin-top: 165px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
         <ul>
          <li><a href="adminD.php"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
          <li><a href="viewU.php"><i class="fas fa-users icon"></i> <span>View Users</span></a></li>
          <li><a href="regular_close.php" class="active"><i class="fas fa-ticket-alt icon"></i> <span>Ticket Record</span></a></li>
          <li><a href="logs.php"><i class="fas fa-file-alt icon"></i> <span>Logs</span></a></li>
          <li><a href="returnT.php"><i class="fas fa-box icon"></i> <span>Asset Record</span></a></li>
          <li><a href="AdminPayments.php"><i class="fas fa-credit-card icon"></i> <span>Transactions</span></a></li>
         </ul>
      <footer>
       <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </footer>
    </div>
    <div class="container">
        <div class="upper">
            <h1>Closed Tickets</h1>
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

        <!-- Regular Tickets Tab -->
        <div id="regular-tab" class="tab-content <?php echo $currentTab === 'regular' ? 'active' : ''; ?>">
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
                <input type="hidden" name="action" id="formAction" value="delete">
                <input type="hidden" name="ref" id="formRef">
                <input type="hidden" name="table" id="formTable">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            </form>

            <div class="table-box">
                <h2>List of Closed Regular Tickets</h2>
                
                <!-- Tab Buttons - MOVED INSIDE table-box and below h2 -->
                <div class="tab-buttons">
                    <button class="tab-btn <?php echo $currentTab === 'regular' ? 'active' : ''; ?>" onclick="showTab('regular')">
                        Regular (<?php echo $totalRegularTickets; ?>)
                    </button>
                    <button class="tab-btn <?php echo $currentTab === 'support' ? 'active' : ''; ?>" onclick="showTab('support')">
                        Support (<?php echo $totalSupportTickets; ?>)
                    </button>
                </div>

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
                            <th>Customer Name<button class="filter-btn" onclick="showCustomerFilterModal()"><i class='bx bx-filter'></i></button></th>
                            <th>Technician<button class="filter-btn" onclick="showTechnicianFilterModal()"><i class='bx bx-filter'></i></button></th>
                            <th>Subject</th>
                            <th>Ticket Details</th>
                            <th>Status</th>
                            <th>Closed Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tickets-table-body">
                        <?php
                        if ($currentTab === 'regular' && $resultTickets !== null && $resultTickets->num_rows > 0) {
                            while ($row = $resultTickets->fetch_assoc()) {
                                $ticketData = json_encode([
                                    'ref' => $row['t_ref'],
                                    'aname' => $row['t_aname'] ?? '',
                                    'technician' => $row['te_technician'] ?? '',
                                    'subject' => $row['t_subject'] ?? '',
                                    'details' => $row['t_details'] ?? '',
                                    'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                                    'closed_date' => $row['te_date'] ?? 'N/A'
                                ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                echo "<tr>
                                        <td>" . htmlspecialchars($row['t_ref']) . "</td>
                                        <td>" . htmlspecialchars($row['t_aname'] ?? '') . "</td>
                                        <td>" . htmlspecialchars($row['te_technician'] ?? '') . "</td>
                                        <td>" . htmlspecialchars($row['t_subject'] ?? '') . "</td>
                                        <td>" . htmlspecialchars($row['t_details'] ?? '') . "</td>
                                        <td class='status-closed'>" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                        <td>" . htmlspecialchars($row['te_date'] ?? 'N/A') . "</td>
                                        <td class='action-buttons'>
                                            <span class='view-btn' onclick='showViewModal($ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                            <span class='delete-btn' onclick=\"openDeleteModal('" . htmlspecialchars($row['t_ref'], ENT_QUOTES, 'UTF-8') . "', 'regular')\" title='Delete'><i class='fas fa-trash'></i></span>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8'>No closed regular tickets found or an error occurred.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>

                <div class="pagination" id="tickets-pagination">
                    <?php
                    if ($currentTab === 'regular') {
                        $paginationParams = ['tab' => $currentTab];
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
                            echo "<a href='regular_close.php?" . http_build_query($paginationParams) . "' class='pagination-link'><i class='fas fa-chevron-left'></i></a>";
                        } else {
                            echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                        }
                        echo "<span class='current-page'>Page $page of $totalPages</span>";
                        if ($page < $totalPages) {
                            $paginationParams['page'] = $page + 1;
                            echo "<a href='regular_close.php?" . http_build_query($paginationParams) . "' class='pagination-link'><i class='fas fa-chevron-right'></i></a>";
                        } else {
                            echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                        }
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Support Tickets Tab -->
        <div id="support-tab" class="tab-content <?php echo $currentTab === 'support' ? 'active' : ''; ?>">
            <!-- View Modal -->
            <div id="viewSupportTicketModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Support Ticket Details</h2>
                    </div>
                    <div id="viewSupportTicketContent"></div>
                    <div class="modal-footer">
                        <button class="modal-btn cancel" onclick="closeModal('viewSupportTicketModal')">Close</button>
                    </div>
                </div>
            </div>

            <!-- Delete Modal -->
            <div id="deleteSupportModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Delete Support Ticket</h2>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete support ticket Ref# <span id="deleteSupportTicketRef"></span>?</p>
                    </div>
                    <div class="modal-footer">
                        <button class="modal-btn cancel" onclick="closeModal('deleteSupportModal')">Cancel</button>
                        <button class="modal-btn confirm" id="confirmDeleteSupportBtn" onclick="submitSupportDeleteAction()">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Support Customer Filter Modal -->
            <div id="supportCustomerFilterModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Filter by Customer</h2>
                    </div>
                    <form id="supportCustomerFilterForm" class="modal-form">
                        <label for="support_customer_filter">Select Customer Name</label>
                        <select name="support_customer_filter" id="support_customer_filter">
                            <option value="">All Customers</option>
                            <?php foreach ($supportCustomerNames as $customer): ?>
                                <option value="<?php echo htmlspecialchars($customer, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $customerFilter === $customer ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('supportCustomerFilterModal')">Cancel</button>
                            <button type="button" class="modal-btn confirm" onclick="applySupportCustomerFilter()">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Support Technician Filter Modal -->
            <div id="supportTechnicianFilterModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Filter by Technician</h2>
                    </div>
                    <form id="supportTechnicianFilterForm" class="modal-form">
                        <label for="support_technician_filter">Select Technician Name</label>
                        <select name="support_technician_filter" id="support_technician_filter">
                            <option value="">All Technicians</option>
                            <?php foreach ($supportTechnicianNames as $technician): ?>
                                <option value="<?php echo htmlspecialchars($technician, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $technicianFilter === $technician ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($technician, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('supportTechnicianFilterModal')">Cancel</button>
                            <button type="button" class="modal-btn confirm" onclick="applySupportTechnicianFilter()">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <form id="supportActionForm" method="POST" style="display: none;">
                <input type="hidden" name="action" id="supportFormAction" value="delete">
                <input type="hidden" name="ref" id="supportFormRef">
                <input type="hidden" name="table" id="supportFormTable" value="support">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            </form>

            <div class="table-box">
                <h2>List of Closed Support Tickets</h2>
                
                <!-- Tab Buttons - MOVED INSIDE table-box and below h2 -->
                <div class="tab-buttons">
                    <button class="tab-btn <?php echo $currentTab === 'regular' ? 'active' : ''; ?>" onclick="showTab('regular')">
                        Regular (<?php echo $totalRegularTickets; ?>)
                    </button>
                    <button class="tab-btn <?php echo $currentTab === 'support' ? 'active' : ''; ?>" onclick="showTab('support')">
                        Support (<?php echo $totalSupportTickets; ?>)
                    </button>
                </div>

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
                            <th>Customer Name<button class="filter-btn" onclick="showSupportCustomerFilterModal()"><i class='bx bx-filter'></i></button></th>
                            <th>Technician<button class="filter-btn" onclick="showSupportTechnicianFilterModal()"><i class='bx bx-filter'></i></button></th>
                            <th>Subject</th>
                            <th>Ticket Details</th>
                            <th>Status</th>
                            <th>Closed Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="support-tickets-table-body">
                        <?php
                        if ($currentTab === 'support' && $resultTickets !== null && $resultTickets->num_rows > 0) {
                            while ($row = $resultTickets->fetch_assoc()) {
                                $ticketData = json_encode([
                                    'ref' => $row['s_ref'],
                                    'aname' => $row['customer_name'] ?? '',
                                    'technician' => $row['te_technician'] ?? '',
                                    'subject' => $row['s_subject'] ?? '',
                                    'details' => $row['s_message'] ?? '',
                                    'status' => ucfirst(strtolower($row['s_status'] ?? '')),
                                    'closed_date' => $row['s_date'] ?? 'N/A'
                                ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                echo "<tr>
                                        <td>" . htmlspecialchars($row['s_ref']) . "</td>
                                        <td>" . htmlspecialchars($row['customer_name'] ?? '') . "</td>
                                        <td>" . htmlspecialchars($row['te_technician'] ?? '') . "</td>
                                        <td>" . htmlspecialchars($row['s_subject'] ?? '') . "</td>
                                        <td>" . htmlspecialchars($row['s_message'] ?? '') . "</td>
                                        <td class='status-closed'>" . ucfirst(strtolower($row['s_status'] ?? '')) . "</td>
                                        <td>" . htmlspecialchars($row['s_date'] ?? 'N/A') . "</td>
                                        <td class='action-buttons'>
                                            <span class='view-btn' onclick='showSupportViewModal($ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                            <span class='delete-btn' onclick=\"openSupportDeleteModal('" . htmlspecialchars($row['s_ref'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></span>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8'>No closed support tickets found or an error occurred.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>

                <div class="pagination" id="support-tickets-pagination">
                    <?php
                    if ($currentTab === 'support') {
                        $paginationParams = ['tab' => $currentTab];
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
                            echo "<a href='regular_close.php?" . http_build_query($paginationParams) . "' class='pagination-link'><i class='fas fa-chevron-left'></i></a>";
                        } else {
                            echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                        }
                        echo "<span class='current-page'>Page $page of $totalPages</span>";
                        if ($page < $totalPages) {
                            $paginationParams['page'] = $page + 1;
                            echo "<a href='regular_close.php?" . http_build_query($paginationParams) . "' class='pagination-link'><i class='fas fa-chevron-right'></i></a>";
                        } else {
                            echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                        }
                    }
                    ?>
                </div>
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

function showTab(tab) {
    // Update URL
    const params = new URLSearchParams(window.location.search);
    params.set('tab', tab);
    params.delete('page'); // Reset to first page
    window.location.href = `regular_close.php?${params.toString()}`;
}

function searchTickets(page = 1) {
    const searchTerm = document.getElementById('searchInput').value;
    const currentTab = '<?php echo $currentTab; ?>';
    const customerFilter = '<?php echo addslashes($customerFilter); ?>';
    const technicianFilter = '<?php echo addslashes($technicianFilter); ?>';
    const tbody = document.getElementById(currentTab === 'regular' ? 'tickets-table-body' : 'support-tickets-table-body');
    const paginationContainer = document.getElementById(currentTab === 'regular' ? 'tickets-pagination' : 'support-tickets-pagination');

    const params = new URLSearchParams();
    params.append('action', 'search');
    params.append('page', page);
    params.append('tab', currentTab);
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
                    updatePagination(response.currentPage, response.totalPages, response.searchTerm, response.customerFilter, response.technicianFilter, currentTab);
                    updateURL(response.currentPage, response.searchTerm, response.customerFilter, response.technicianFilter, currentTab);
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
    xhr.open('GET', `regular_close.php?${params.toString()}`, true);
    xhr.send();
}

function updatePagination(currentPage, totalPages, searchTerm, customerFilter, technicianFilter, tab) {
    const paginationContainer = document.getElementById(tab === 'regular' ? 'tickets-pagination' : 'support-tickets-pagination');
    let paginationHtml = '';
    const params = new URLSearchParams();
    params.append('tab', tab);
    if (searchTerm) params.append('search', searchTerm);
    if (customerFilter) params.append('customer', customerFilter);
    if (technicianFilter) params.append('technician', technicianFilter);

    if (currentPage > 1) {
        params.set('page', currentPage - 1);
        paginationHtml += `<a href="regular_close.php?${params.toString()}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        params.set('page', currentPage + 1);
        paginationHtml += `<a href="regular_close.php?${params.toString()}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

function updateURL(page, searchTerm, customerFilter, technicianFilter, tab) {
    const params = new URLSearchParams();
    params.append('tab', tab);
    params.append('page', page);
    if (searchTerm) params.append('search', searchTerm);
    if (customerFilter) params.append('customer', customerFilter);
    if (technicianFilter) params.append('technician', technicianFilter);
    const newUrl = `regular_close.php?${params.toString()}`;
    window.history.pushState({}, '', newUrl);
}

const debouncedSearchTickets = debounce(searchTickets, 300);

// Regular Tab Functions
function showViewModal(data) {
    const content = document.getElementById('viewTicketContent');
    content.innerHTML = `
        <p><strong>Ref#:</strong> ${data.ref}</p>
        <p><strong>Customer Name:</strong> ${data.aname}</p>
        <p><strong>Technician:</strong> ${data.technician}</p>
        <p><strong>Subject:</strong> ${data.subject}</p>
        <p><strong>Message:</strong> ${data.details}</p>
        <p><strong>Status:</strong> <span class="status-closed">${data.status}</span></p>
        <p><strong>Closed Date:</strong> ${data.closed_date}</p>
    `;
    document.getElementById('viewTicketModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function openDeleteModal(t_ref, table) {
    document.getElementById('deleteTicketRef').textContent = t_ref;
    document.getElementById('formRef').value = t_ref;
    document.getElementById('formTable').value = table;
    document.getElementById('deleteModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function submitDeleteAction() {
    const t_ref = document.getElementById('deleteTicketRef').textContent;
    const table = document.getElementById('formTable').value;
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Deleting...';
    
    document.getElementById('formAction').value = 'delete';
    document.getElementById('formRef').value = t_ref;
    document.getElementById('formTable').value = table;
    document.getElementById('actionForm').submit();
}

// Support Tab Functions
function showSupportViewModal(data) {
    const content = document.getElementById('viewSupportTicketContent');
    content.innerHTML = `
        <p><strong>Ref#:</strong> ${data.ref}</p>
        <p><strong>Customer Name:</strong> ${data.aname}</p>
        <p><strong>Technician:</strong> ${data.technician}</p>
        <p><strong>Subject:</strong> ${data.subject}</p>
        <p><strong>Message:</strong> ${data.details}</p>
        <p><strong>Status:</strong> <span class="status-closed">${data.status}</span></p>
        <p><strong>Closed Date:</strong> ${data.closed_date}</p>
    `;
    document.getElementById('viewSupportTicketModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function openSupportDeleteModal(s_ref) {
    document.getElementById('deleteSupportTicketRef').textContent = s_ref;
    document.getElementById('supportFormRef').value = s_ref;
    document.getElementById('deleteSupportModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function submitSupportDeleteAction() {
    const s_ref = document.getElementById('deleteSupportTicketRef').textContent;
    const confirmBtn = document.getElementById('confirmDeleteSupportBtn');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Deleting...';
    
    document.getElementById('supportFormAction').value = 'delete';
    document.getElementById('supportFormRef').value = s_ref;
    document.getElementById('supportActionForm').submit();
}

// Shared Modal Functions
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
    const confirmBtns = ['confirmDeleteBtn', 'confirmDeleteSupportBtn'];
    confirmBtns.forEach(btnId => {
        const btn = document.getElementById(btnId);
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Confirm';
        }
    });
}

// Regular Filter Functions
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
    const currentTab = '<?php echo $currentTab; ?>';
    const params = new URLSearchParams();
    params.append('page', 1);
    params.append('tab', currentTab);
    if (searchTerm) params.append('search', searchTerm);
    if (customerFilter) params.append('customer', customerFilter);
    if (technicianFilter) params.append('technician', technicianFilter);
    window.location.href = `regular_close.php?${params.toString()}`;
}

function applyTechnicianFilter() {
    const technicianFilter = document.getElementById('technician_filter').value;
    const searchTerm = document.getElementById('searchInput').value;
    const customerFilter = '<?php echo addslashes($customerFilter); ?>';
    const currentTab = '<?php echo $currentTab; ?>';
    const params = new URLSearchParams();
    params.append('page', 1);
    params.append('tab', currentTab);
    if (searchTerm) params.append('search', searchTerm);
    if (customerFilter) params.append('customer', customerFilter);
    if (technicianFilter) params.append('technician', technicianFilter);
    window.location.href = `regular_close.php?${params.toString()}`;
}

// Support Filter Functions
function showSupportCustomerFilterModal() {
    const modal = document.getElementById('supportCustomerFilterModal');
    modal.style.display = 'block';
    document.body.classList.add('modal-open');
}

function showSupportTechnicianFilterModal() {
    const modal = document.getElementById('supportTechnicianFilterModal');
    modal.style.display = 'block';
    document.body.classList.add('modal-open');
}

function applySupportCustomerFilter() {
    const customerFilter = document.getElementById('support_customer_filter').value;
    const searchTerm = document.getElementById('searchInput').value;
    const technicianFilter = '<?php echo addslashes($technicianFilter); ?>';
    const currentTab = 'support';
    const params = new URLSearchParams();
    params.append('page', 1);
    params.append('tab', currentTab);
    if (searchTerm) params.append('search', searchTerm);
    if (customerFilter) params.append('customer', customerFilter);
    if (technicianFilter) params.append('technician', technicianFilter);
    window.location.href = `regular_close.php?${params.toString()}`;
}

function applySupportTechnicianFilter() {
    const technicianFilter = document.getElementById('support_technician_filter').value;
    const searchTerm = document.getElementById('searchInput').value;
    const customerFilter = '<?php echo addslashes($customerFilter); ?>';
    const currentTab = 'support';
    const params = new URLSearchParams();
    params.append('page', 1);
    params.append('tab', currentTab);
    if (searchTerm) params.append('search', searchTerm);
    if (customerFilter) params.append('customer', customerFilter);
    if (technicianFilter) params.append('technician', technicianFilter);
    window.location.href = `regular_close.php?${params.toString()}`;
}

function exportTable(format) {
    const searchTerm = document.getElementById('searchInput').value;
    const currentTab = '<?php echo $currentTab; ?>';
    const customerFilter = '<?php echo addslashes($customerFilter); ?>';
    const technicianFilter = '<?php echo addslashes($technicianFilter); ?>';

    const params = new URLSearchParams();
    params.append('action', 'export_data');
    params.append('tab', currentTab);
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
                        const sheetName = currentTab === 'regular' ? 'Closed Regular Tickets' : 'Closed Support Tickets';
                        XLSX.utils.book_append_sheet(wb, ws, sheetName);
                        XLSX.writeFile(wb, `${currentTab}_closed_tickets.xlsx`);
                    } else if (format === 'csv') {
                        const ws = XLSX.utils.json_to_sheet(data);
                        const csv = XLSX.utils.sheet_to_csv(ws);
                        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                        const fileName = currentTab === 'regular' ? 'closed_regular_tickets.csv' : 'closed_support_tickets.csv';
                        saveAs(blob, fileName);
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
    xhr.open('GET', `regular_close.php?${params.toString()}`, true);
    xhr.send();
}
</script>
</body>
</html>