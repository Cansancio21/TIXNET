<?php
include 'db.php'; // Include database connection
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if admin is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Fetch user data
$username = $_SESSION['username'];
$lastName = '';
$firstName = '';
$userType = '';
$avatarPath = 'default-avatar.png';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $username . '.png';

if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} else {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}

$avatarPath = $_SESSION['avatarPath'];

// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $page = isset($_GET['search_page']) ? max(1, (int)$_GET['search_page']) : 1;
    $logs_per_page = 10;
    $offset = ($page - 1) * $logs_per_page;
    $output = '';

    if ($searchTerm === '') {
        // Fetch default logs for the current page
        $countSql = "SELECT COUNT(*) as total FROM tbl_logs";
        $countResult = $conn->query($countSql);
        $total_logs = $countResult->fetch_assoc()['total'];
        $total_pages = ceil($total_logs / $logs_per_page);

        $sql = "SELECT l_stamp, l_type, l_description FROM tbl_logs ORDER BY l_stamp ASC LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $offset, $logs_per_page);
    } else {
        // Count total matching logs for pagination
        $countSql = "SELECT COUNT(*) as total FROM tbl_logs WHERE l_description LIKE ? OR l_stamp LIKE ? OR l_type LIKE ?";
        $countStmt = $conn->prepare($countSql);
        $searchWildcard = "%$searchTerm%";
        $countStmt->bind_param("sss", $searchWildcard, $searchWildcard, $searchWildcard);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $total_logs = $countResult->fetch_assoc()['total'];
        $countStmt->close();

        $total_pages = ceil($total_logs / $logs_per_page);

        // Fetch paginated search results
        $sql = "SELECT l_stamp, l_type, l_description FROM tbl_logs WHERE l_description LIKE ? OR l_stamp LIKE ? OR l_type LIKE ? ORDER BY l_stamp ASC LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $searchWildcard, $searchWildcard, $searchWildcard, $offset, $logs_per_page);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $type_display = $row['l_type'] ? 'user "' . htmlspecialchars($row['l_type']) . '"' : 'Unknown';
            if (!$row['l_type'] && $row['l_description'] === 'has successfully logged in') {
                error_log("Warning: l_type is NULL/empty for login log at {$row['l_stamp']}");
            }
            $output .= "<tr>
                          <td>" . htmlspecialchars($row['l_stamp']) . "</td>
                          <td>" . $type_display . "</td>
                          <td>" . htmlspecialchars($row['l_description']) . "</td>
                        </tr>";
        }
    } else {
        $output = "<tr><td colspan='3' style='text-align: center;'>No logs found</td></tr>";
    }
    $stmt->close();

    // Add pagination data
    $output .= "<script>
        updatePagination($page, $total_pages, '" . addslashes($searchTerm) . "');
    </script>";

    header('Content-Type: text/html; charset=UTF-8');
    echo $output;
    exit();
}

// Fetch user details from tbl_user
if ($conn) {
    $sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sqlUser);
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $resultUser = $stmt->get_result();

    if ($resultUser->num_rows > 0) {
        $row = $resultUser->fetch_assoc();
        $firstName = $row['u_fname'] ?: $username;
        $lastName = $row['u_lname'] ?: '';
        $userType = $row['u_type'];
    }
    $stmt->close();
}

// Set up pagination
$logs_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $logs_per_page;

// Get total number of logs
$total_logs_query = "SELECT COUNT(*) as total FROM tbl_logs";
$total_logs_result = $conn->query($total_logs_query);
$total_logs = $total_logs_result->fetch_assoc()['total'];
$total_pages = ceil($total_logs / $logs_per_page);

// Fetch logs with pagination
$log_query = "SELECT l_stamp, l_type, l_description FROM tbl_logs ORDER BY l_stamp ASC LIMIT ?, ?";
$stmt = $conn->prepare($log_query);
$stmt->bind_param("ii", $offset, $logs_per_page);
$stmt->execute();
$logResult = $stmt->get_result();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs</title>
    <link rel="stylesheet" href="logs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <style>
        table th:nth-child(2), table td:nth-child(2) {
            width: 200px;
            text-align: left;
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
          <li><a href="regular_close.php"><i class="fas fa-ticket-alt icon"></i> <span>Regular Record</span></a></li>
          <li><a href="support_close.php"><i class="fas fa-ticket-alt icon"></i> <span>Support Record</span></a></li>
          <li><a href="logs.php" class="active"><i class="fas fa-file-alt icon"></i> <span>Logs</span></a></li>
          <li><a href="returnT.php"><i class="fas fa-undo icon"></i> <span>Returned Records</span></a></li>
          <li><a href="deployedT.php"><i class="fas fa-box icon"></i> <span>Deployed Records</span></a></li>
          <li><a href="AdminPayments.php"><i class="fas fa-credit-card icon"></i> <span>Payment Transactions</span></a></li>
         </ul>
      <footer>
       <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </footer>
    </div>

    <div class="container">
        <div class="upper">
            <h1>System Logs</h1>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search logs..." onkeyup="debouncedSearchLogs()">
                <span class="search-icon"><i class="fas fa-search"></i></span>
            </div>
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
                <a href="settings.php" class="settings-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>

        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Type</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody id="logs-tbody">
                    <?php if ($logResult->num_rows > 0): ?>
                        <?php while ($logRow = $logResult->fetch_assoc()): ?>
                            <?php
                            $type_display = $logRow['l_type'] ? 'user "' . htmlspecialchars($logRow['l_type']) . '"' : 'Unknown';
                            if (!$logRow['l_type'] && $logRow['l_description'] === 'has successfully logged in') {
                                error_log("Warning: l_type is NULL/empty for login log at {$logRow['l_stamp']}");
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($logRow['l_stamp']); ?></td>
                                <td><?php echo $type_display; ?></td>
                                <td><?php echo htmlspecialchars($logRow['l_description']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center;">No logs found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination" id="logs-pagination">
                <?php
                $paginationParams = [];
                if (isset($_GET['search']) && trim($_GET['search']) !== '') {
                    $paginationParams['search'] = trim($_GET['search']);
                }
                if ($current_page > 1) {
                    $paginationParams['page'] = $current_page - 1;
                    echo "<a href='logs.php?" . http_build_query($paginationParams) . "' class='pagination-link'><i class='fas fa-chevron-left'></i></a>";
                } else {
                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                }
                echo "<span class='current-page'>Page $current_page of $total_pages</span>";
                if ($current_page < $total_pages) {
                    $paginationParams['page'] = $current_page + 1;
                    echo "<a href='logs.php?" . http_build_query($paginationParams) . "' class='pagination-link'><i class='fas fa-chevron-right'></i></a>";
                } else {
                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<script>
    let currentSearchPage = 1;
    let defaultPage = <?php echo json_encode($current_page); ?>;

    // Debounce function to limit search calls
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

    function searchLogs(page = 1) {
        const searchTerm = document.getElementById('searchInput').value;
        const tbody = document.getElementById('logs-tbody');
        const paginationContainer = document.getElementById('logs-pagination');

        currentSearchPage = page;

        // Create XMLHttpRequest for AJAX
        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                tbody.innerHTML = xhr.responseText.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
            }
        };
        const params = new URLSearchParams();
        params.append('action', 'search');
        params.append('search', searchTerm);
        params.append('search_page', searchTerm ? page : defaultPage);
        xhr.open('GET', `logs.php?${params.toString()}`, true);
        xhr.send();
    }

    function updatePagination(currentPage, totalPages, searchTerm) {
        const paginationContainer = document.getElementById('logs-pagination');
        let paginationHtml = '';
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);

        if (currentPage > 1) {
            params.set('search_page', currentPage - 1);
            paginationHtml += `<a href="logs.php?${params.toString()}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
        } else {
            paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
        }

        paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

        if (currentPage < totalPages) {
            params.set('search_page', currentPage + 1);
            paginationHtml += `<a href="logs.php?${params.toString()}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
        } else {
            paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
        }

        paginationContainer.innerHTML = paginationHtml;

        // Update URL without reloading
        const newUrl = searchTerm ? `logs.php?${params.toString()}` : `logs.php?page=${currentPage}`;
        window.history.pushState({}, '', newUrl);
    }

    // Debounced search function
    const debouncedSearchLogs = debounce(searchLogs, 300);

    // Initialize search on page load if there's a search term
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('searchInput');
        if (searchInput.value) {
            searchLogs(currentSearchPage);
        }
    });
</script>

<?php
$conn->close();
?>
</body>
</html>