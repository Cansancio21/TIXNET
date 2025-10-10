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
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
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
            $output .= "<tr class='log-row'>
                          <td class='timestamp-cell'>" . htmlspecialchars($row['l_stamp']) . "</td>
                          <td class='type-cell'>" . $type_display . "</td>
                          <td class='description-cell'>" . htmlspecialchars($row['l_description']) . "</td>
                        </tr>";
        }
    } else {
        $output = "<tr class='log-row'>
                      <td colspan='3' class='no-logs-cell' style='text-align: center;'>No logs found</td>
                   </tr>";
    }
    $stmt->close();

    // Return JSON response with table HTML and pagination data
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'table_html' => $output,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'search_term' => $searchTerm
    ]);
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
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($current_page - 1) * $logs_per_page;

// Get total number of logs based on search
if ($search_term !== '') {
    $countSql = "SELECT COUNT(*) as total FROM tbl_logs WHERE l_description LIKE ? OR l_stamp LIKE ? OR l_type LIKE ?";
    $countStmt = $conn->prepare($countSql);
    $searchWildcard = "%$search_term%";
    $countStmt->bind_param("sss", $searchWildcard, $searchWildcard, $searchWildcard);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total_logs = $countResult->fetch_assoc()['total'];
    $countStmt->close();
} else {
    $total_logs_query = "SELECT COUNT(*) as total FROM tbl_logs";
    $total_logs_result = $conn->query($total_logs_query);
    $total_logs = $total_logs_result->fetch_assoc()['total'];
}

$total_pages = ceil($total_logs / $logs_per_page);

// Fetch logs with pagination
if ($search_term !== '') {
    $log_query = "SELECT l_stamp, l_type, l_description FROM tbl_logs WHERE l_description LIKE ? OR l_stamp LIKE ? OR l_type LIKE ? ORDER BY l_stamp ASC LIMIT ?, ?";
    $stmt = $conn->prepare($log_query);
    $searchWildcard = "%$search_term%";
    $stmt->bind_param("sssii", $searchWildcard, $searchWildcard, $searchWildcard, $offset, $logs_per_page);
} else {
    $log_query = "SELECT l_stamp, l_type, l_description FROM tbl_logs ORDER BY l_stamp ASC LIMIT ?, ?";
    $stmt = $conn->prepare($log_query);
    $stmt->bind_param("ii", $offset, $logs_per_page);
}

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
    <link rel="stylesheet" href="logss.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="adminD.php"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
            <li><a href="viewU.php"><i class="fas fa-users icon"></i> <span>View Users</span></a></li>
            <li><a href="regular_close.php"><i class="fas fa-ticket-alt icon"></i> <span>Ticket Record</span></a></li>
            <li><a href="logs.php" class="active"><i class="fas fa-file-alt icon"></i> <span>Logs</span></a></li>
            <li><a href="returnT.php"><i class="fas fa-box icon"></i> <span>Asset Record</span></a></li>
            <li><a href="AdminPayments.php"><i class="fas fa-credit-card icon"></i> <span>Transactions</span></a></li>
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>

    <div class="container">
        <div class="upper">
            <h1>System Logs</h1>
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
            <h2>User Activities</h2>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search logs..." onkeyup="debouncedSearchLogs()" value="<?php echo htmlspecialchars($search_term); ?>">
                <span class="search-icon"><i class="fas fa-search"></i></span>
            </div>
            <table id="logs-table">
                <thead>
                    <tr>
                        <th class="timestamp-header">Timestamp</th>
                        <th class="type-header">Type</th>
                        <th class="description-header">Description</th>
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
                            <tr class="log-row">
                                <td class="timestamp-cell"><?php echo htmlspecialchars($logRow['l_stamp']); ?></td>
                                <td class="type-cell"><?php echo $type_display; ?></td>
                                <td class="description-cell"><?php echo htmlspecialchars($logRow['l_description']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr class="log-row">
                            <td colspan="3" class="no-logs-cell">No logs found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination" id="logs-pagination">
                <?php
                $paginationParams = ['page' => $current_page];
                if ($search_term !== '') {
                    $paginationParams['search'] = $search_term;
                }
                
                // Previous button
                if ($current_page > 1) {
                    $prevParams = $paginationParams;
                    $prevParams['page'] = $current_page - 1;
                    $prevQuery = http_build_query($prevParams);
                    echo "<a href='logs.php?{$prevQuery}' class='pagination-link' onclick='loadPage(" . ($current_page - 1) . ", \"" . htmlspecialchars($search_term, ENT_QUOTES) . "\"); return false;'><i class='fas fa-chevron-left'></i></a>";
                } else {
                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                }
                
                // Page info
                echo "<span class='current-page'>Page {$current_page} of {$total_pages}</span>";
                
                // Next button
                if ($current_page < $total_pages) {
                    $nextParams = $paginationParams;
                    $nextParams['page'] = $current_page + 1;
                    $nextQuery = http_build_query($nextParams);
                    echo "<a href='logs.php?{$nextQuery}' class='pagination-link' onclick='loadPage(" . ($current_page + 1) . ", \"" . htmlspecialchars($search_term, ENT_QUOTES) . "\"); return false;'><i class='fas fa-chevron-right'></i></a>";
                } else {
                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<script>
    let currentPage = <?php echo json_encode($current_page); ?>;
    let currentSearchTerm = <?php echo json_encode($search_term); ?>;

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

    // Apply consistent table styling
    function applyTableStyles() {
        const table = document.getElementById('logs-table');
        const rows = document.querySelectorAll('.log-row');
        
        if (table) {
            table.style.tableLayout = 'fixed';
            table.style.width = '100%';
        }
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length === 3) {
                cells[0].style.textAlign = 'left';
                cells[0].style.width = '25%';
                cells[0].style.padding = '12px 15px';
                cells[0].style.minWidth = '150px';
                
                cells[1].style.textAlign = 'left';
                cells[1].style.width = '20%';
                cells[1].style.padding = '12px 15px';
                cells[1].style.maxWidth = '200px';
                cells[1].style.minWidth = '120px';
                
                cells[2].style.textAlign = 'left';
                cells[2].style.width = '55%';
                cells[2].style.padding = '12px 15px';
                cells[2].style.minWidth = '200px';
                cells[2].style.wordWrap = 'break-word';
                cells[2].style.overflowWrap = 'break-word';
            } else if (cells.length === 1) {
                cells[0].style.textAlign = 'center';
                cells[0].style.padding = '20px';
                cells[0].style.width = '100%';
            }
        });
    }

    function loadPage(page, searchTerm = '') {
        currentPage = page;
        if (searchTerm !== '') {
            currentSearchTerm = searchTerm;
        }
        searchLogs(page, currentSearchTerm);
        return false;
    }

    function searchLogs(page = 1, searchTerm = '') {
        const tbody = document.getElementById('logs-tbody');
        const paginationContainer = document.getElementById('logs-pagination');

        // Show loading state
        tbody.innerHTML = '<tr class="log-row"><td colspan="3" style="text-align: center; padding: 20px;">Loading...</td></tr>';

        // Create XMLHttpRequest for AJAX
        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        tbody.innerHTML = response.table_html;
                        
                        // Update pagination
                        updatePagination(response.current_page, response.total_pages, response.search_term);
                        
                        // Re-apply table styles
                        applyTableStyles();
                        
                        // Update URL
                        updateURL(response.current_page, response.search_term);
                        
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        tbody.innerHTML = '<tr class="log-row"><td colspan="3" style="text-align: center; padding: 20px; color: red;">Error loading data</td></tr>';
                    }
                } else {
                    console.error('Error loading logs:', xhr.status);
                    tbody.innerHTML = '<tr class="log-row"><td colspan="3" style="text-align: center; padding: 20px; color: red;">Error loading data</td></tr>';
                }
            }
        };
        
        const params = new URLSearchParams();
        params.append('action', 'search');
        params.append('search', searchTerm);
        params.append('page', page);
        
        xhr.open('GET', `logs.php?${params.toString()}`, true);
        xhr.send();
    }

    function updatePagination(currentPage, totalPages, searchTerm) {
        const paginationContainer = document.getElementById('logs-pagination');
        let paginationHtml = '';
        
        currentPage = parseInt(currentPage);
        totalPages = parseInt(totalPages);

        // Previous button
        if (currentPage > 1) {
            paginationHtml += `<a href="#" class="pagination-link" onclick='loadPage(${currentPage - 1}, "${searchTerm}"); return false;'><i class="fas fa-chevron-left"></i></a>`;
        } else {
            paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
        }

        // Page info
        paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

        // Next button
        if (currentPage < totalPages) {
            paginationHtml += `<a href="#" class="pagination-link" onclick='loadPage(${currentPage + 1}, "${searchTerm}"); return false;'><i class="fas fa-chevron-right"></i></a>`;
        } else {
            paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
        }

        paginationContainer.innerHTML = paginationHtml;
    }

    function updateURL(page, searchTerm) {
        const params = new URLSearchParams();
        if (searchTerm) {
            params.append('search', searchTerm);
        }
        params.append('page', page);
        
        const newUrl = `logs.php?${params.toString()}`;
        window.history.pushState({page: page, search: searchTerm}, '', newUrl);
    }

    // Debounced search function
    const debouncedSearchLogs = debounce(function() {
        const searchValue = document.getElementById('searchInput').value.trim();
        loadPage(1, searchValue);
    }, 300);

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        applyTableStyles();
        
        // Set initial search input value
        if (currentSearchTerm) {
            document.getElementById('searchInput').value = currentSearchTerm;
        }
    });

    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(event) {
        if (event.state) {
            const { page, search } = event.state;
            document.getElementById('searchInput').value = search || '';
            currentSearchTerm = search || '';
            loadPage(page, search || '');
        } else {
            // Fallback for older browsers
            const urlParams = new URLSearchParams(window.location.search);
            const searchTerm = urlParams.get('search') || '';
            const page = parseInt(urlParams.get('page')) || 1;
            document.getElementById('searchInput').value = searchTerm;
            loadPage(page, searchTerm);
        }
    });
</script>

<?php
$conn->close();
?>
</body>
</html>