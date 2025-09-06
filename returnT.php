<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) { 
    header("Location: index.php");
    exit(); 
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Get user details for the header
$username = $_SESSION['username'];
$firstName = '';
$lastName = '';
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

// Handle AJAX export data request
if (isset($_GET['action']) && $_GET['action'] === 'export_data') {
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $assetNameFilter = isset($_GET['asset_name']) ? trim($_GET['asset_name']) : '';
    $technicianNameFilter = isset($_GET['technician_name']) ? trim($_GET['technician_name']) : '';

    // Build the WHERE clause dynamically
    $whereClauses = ['a_status = ?'];
    $params = ['Returned'];
    $paramTypes = 's';

    if ($searchTerm !== '') {
        $whereClauses[] = "(a_ref_no LIKE ? OR a_name LIKE ? OR tech_name LIKE ? OR tech_id LIKE ? OR a_serial_no LIKE ? OR a_date LIKE ? OR a_return_date LIKE ?)";
        $searchWildcard = "%$searchTerm%";
        $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
        $paramTypes .= 'sssssss';
    }

    if ($assetNameFilter !== '') {
        $whereClauses[] = "a_name = ?";
        $params[] = $assetNameFilter;
        $paramTypes .= 's';
    }

    if ($technicianNameFilter !== '') {
        $whereClauses[] = "tech_name = ?";
        $params[] = $technicianNameFilter;
        $paramTypes .= 's';
    }

    $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // Fetch all records for export (no limit/offset)
    $sqlExport = "SELECT a_id, a_ref_no, a_name, tech_name, tech_id, a_serial_no, a_date, a_return_date 
                  FROM tbl_asset_status $whereClause 
                  ORDER BY a_return_date DESC";
    $stmtExport = $conn->prepare($sqlExport);
    if ($paramTypes !== '') {
        $stmtExport->bind_param($paramTypes, ...$params);
    }
    $stmtExport->execute();
    $resultExport = $stmtExport->get_result();

    $records = [];
    while ($row = $resultExport->fetch_assoc()) {
        $records[] = [
            'Asset Ref No.' => $row['a_ref_no'] ?? '',
            'Asset Name' => $row['a_name'] ?? '',
            'Technician Name' => $row['tech_name'] ?? '',
            'Technician ID' => $row['tech_id'] ?? '',
            'Asset Serial No.' => $row['a_serial_no'] === '0' || empty($row['a_serial_no']) ? '' : $row['a_serial_no'],
            'Date Borrowed' => $row['a_date'] ?? '-',
            'Date Returned' => $row['a_return_date'] ?? '-'
        ];
    }
    $stmtExport->close();

    header('Content-Type: application/json');
    echo json_encode(['data' => $records]);
    exit;
}

// Handle AJAX search request with filters
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $page = isset($_GET['search_page']) ? max(1, (int)$_GET['search_page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $assetNameFilter = isset($_GET['asset_name']) ? trim($_GET['asset_name']) : '';
    $technicianNameFilter = isset($_GET['technician_name']) ? trim($_GET['technician_name']) : '';

    // Build the WHERE clause dynamically
    $whereClauses = ['a_status = ?'];
    $params = ['Returned'];
    $paramTypes = 's';

    if ($searchTerm !== '') {
        $whereClauses[] = "(a_ref_no LIKE ? OR a_name LIKE ? OR tech_name LIKE ? OR tech_id LIKE ? OR a_serial_no LIKE ? OR a_date LIKE ? OR a_return_date LIKE ?)";
        $searchWildcard = "%$searchTerm%";
        $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
        $paramTypes .= 'sssssss';
    }

    if ($assetNameFilter !== '') {
        $whereClauses[] = "a_name = ?";
        $params[] = $assetNameFilter;
        $paramTypes .= 's';
    }

    if ($technicianNameFilter !== '') {
        $whereClauses[] = "tech_name = ?";
        $params[] = $technicianNameFilter;
        $paramTypes .= 's';
    }

    $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // Count total matching records for pagination
    $countSql = "SELECT COUNT(*) as total FROM tbl_asset_status $whereClause";
    $countStmt = $conn->prepare($countSql);
    if ($paramTypes !== '') {
        $countStmt->bind_param($paramTypes, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = max(1, ceil($totalRecords / $limit));

    // Fetch paginated search results
    $sql = "SELECT a_id, a_ref_no, a_name, tech_name, tech_id, a_serial_no, a_date, a_return_date 
            FROM tbl_asset_status 
            $whereClause 
            ORDER BY a_return_date DESC 
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if ($paramTypes !== '') {
        $params[] = $offset;
        $params[] = $limit;
        $paramTypes .= 'ii';
        $stmt->bind_param($paramTypes, ...$params);
    } else {
        $stmt->bind_param("ii", $offset, $limit);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $serialNo = ($row['a_serial_no'] === '0' || empty($row['a_serial_no'])) ? '' : htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8');
            $ticketData = json_encode([
                'id' => $row['a_id'],
                'ref_no' => $row['a_ref_no'] ?? '',
                'asset_name' => $row['a_name'] ?? '',
                'technician_name' => $row['tech_name'] ?? '',
                'technician_id' => $row['tech_id'] ?? '',
                'serial_no' => $serialNo,
                'date_borrowed' => $row['a_date'] ?? '-',
                'date_returned' => $row['a_return_date'] ?? '-'
            ], JSON_HEX_QUOT | JSON_HEX_TAG);
            echo "<tr> 
                    <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td> 
                    <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>  
                    <td>" . htmlspecialchars($row['tech_name'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['tech_id'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . $serialNo . "</td>
                    <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['a_return_date'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td class='action-buttons'>
                        <span class='view-btn' onclick='showViewModal($ticketData)' title='View'><i class='fas fa-eye'></i></span>
                    </td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='8' style='text-align: center;'>No returned assets found.</td></tr>";
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
    exit();
}

// Get user info for header
if ($conn) {
    $sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sqlUser);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $resultUser = $stmt->get_result();

    if ($resultUser->num_rows > 0) {
        $row = $resultUser->fetch_assoc();
        $firstName = $row['u_fname'];
        $lastName = $row['u_lname'];
        $userType = $row['u_type'];
    }
    $stmt->close();

    // Fetch unique asset names and technician names for filter modals
    $assetNamesQuery = "SELECT DISTINCT a_name FROM tbl_asset_status WHERE a_status = 'Returned' ORDER BY a_name";
    $assetNamesResult = $conn->query($assetNamesQuery);
    $assetNames = [];
    while ($row = $assetNamesResult->fetch_assoc()) {
        $assetNames[] = $row['a_name'];
    }

    $technicianNamesQuery = "SELECT DISTINCT tech_name FROM tbl_asset_status WHERE a_status = 'Returned' ORDER BY tech_name";
    $technicianNamesResult = $conn->query($technicianNamesQuery);
    $technicianNames = [];
    while ($row = $technicianNamesResult->fetch_assoc()) {
        $technicianNames[] = $row['tech_name'];
    }

    // Pagination setup
    $limit = 10;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $assetNameFilter = isset($_GET['asset_name']) ? trim($_GET['asset_name']) : '';
    $technicianNameFilter = isset($_GET['technician_name']) ? trim($_GET['technician_name']) : '';

    // Build the WHERE clause dynamically
    $whereClauses = ['a_status = ?'];
    $params = ['Returned'];
    $paramTypes = 's';

    if ($searchTerm !== '') {
        $whereClauses[] = "(a_ref_no LIKE ? OR a_name LIKE ? OR tech_name LIKE ? OR tech_id LIKE ? OR a_serial_no LIKE ? OR a_date LIKE ? OR a_return_date LIKE ?)";
        $searchWildcard = "%$searchTerm%";
        $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
        $paramTypes .= 'sssssss';
    }

    if ($assetNameFilter !== '') {
        $whereClauses[] = "a_name = ?";
        $params[] = $assetNameFilter;
        $paramTypes .= 's';
    }

    if ($technicianNameFilter !== '') {
        $whereClauses[] = "tech_name = ?";
        $params[] = $technicianNameFilter;
        $paramTypes .= 's';
    }

    $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // Count total records for pagination
    $countSql = "SELECT COUNT(*) as total FROM tbl_asset_status $whereClause";
    $countStmt = $conn->prepare($countSql);
    if ($paramTypes !== '') {
        $countStmt->bind_param($paramTypes, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = max(1, ceil($totalRecords / $limit));
    $page = min($page, $totalPages); // Ensure page doesn't exceed total pages
    $offset = ($page - 1) * $limit;

    // Main query with pagination
    $sqlBorrowed = "SELECT a_id, a_ref_no, a_name, tech_name, tech_id, a_serial_no, a_date, a_return_date 
                    FROM tbl_asset_status 
                    $whereClause 
                    ORDER BY a_return_date DESC 
                    LIMIT ?, ?";
    $stmt = $conn->prepare($sqlBorrowed);
    if ($paramTypes !== '') {
        $params[] = $offset;
        $params[] = $limit;
        $paramTypes .= 'ii';
        $stmt->bind_param($paramTypes, ...$params);
    } else {
        $stmt->bind_param("ii", $offset, $limit);
    }
    $stmt->execute();
    $resultBorrowed = $stmt->get_result();
    $stmt->close();
} else {
    echo "Database connection failed.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returned Assets</title>
    <link rel="stylesheet" href="returnTT.css"> 
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

    
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
          <li><a href="adminD.php"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
          <li><a href="viewU.php"><i class="fas fa-users icon"></i> <span>View Users</span></a></li>
          <li><a href="regular_close.php"><i class="fas fa-ticket-alt icon"></i> <span>Regular Record</span></a></li>
          <li><a href="support_close.php"><i class="fas fa-ticket-alt icon"></i> <span>Support Record</span></a></li>
          <li><a href="logs.php"><i class="fas fa-file-alt icon"></i> <span>Logs</span></a></li>
          <li><a href="returnT.php" class="active"><i class="fas fa-undo icon"></i> <span>Returned Records</span></a></li>
          <li><a href="deployedT.php"><i class="fas fa-box icon"></i> <span>Deployed Records</span></a></li>
          <li><a href="AdminPayments.php"><i class="fas fa-credit-card icon"></i> <span>Payment Transactions</span></a></li>
         </ul>
      <footer>
       <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </footer>
    </div>

    <div class="container">
        <div class="upper">
            <h1>Returned Assets</h1>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search returned assets..." value="<?php echo htmlspecialchars($searchTerm); ?>" onkeyup="debouncedSearchReturned()">
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

        <div class="table-box glass-container">
            <h2>List of Returned Assets</h2>
            
            <div class="action-buttons">
                <div class="export-container">
                    <button class="action-btn export-btn"><i class="fas fa-download"></i> Export</button>
                    <div class="export-dropdown">
                        <button onclick="exportTable('excel')">Excel</button>
                        <button onclick="exportTable('csv')">CSV</button>
                    </div>
                </div>
            </div>
            
            <table id="returned-assets-table">
                <thead>
                    <tr>
                        <th>Asset Ref No.</th>
                        <th>Asset Name <button class="filter-btn" onclick="showAssetFilterModal()" title="Filter by Asset Name"><i class='bx bx-filter'></i></button></th>
                        <th>Technician Name <button class="filter-btn" onclick="showTechnicianFilterModal()" title="Filter by Technician Name"><i class='bx bx-filter'></i></button></th>
                        <th>Technician ID</th>
                        <th>Asset Serial No.</th>
                        <th>Date Borrowed</th>
                        <th>Date Returned</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php 
                    if ($resultBorrowed && $resultBorrowed->num_rows > 0) { 
                        while ($row = $resultBorrowed->fetch_assoc()) {
                            $serialNo = ($row['a_serial_no'] === '0' || empty($row['a_serial_no'])) ? '' : htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8');
                            $ticketData = json_encode([
                                'id' => $row['a_id'],
                                'ref_no' => $row['a_ref_no'] ?? '',
                                'asset_name' => $row['a_name'] ?? '',
                                'technician_name' => $row['tech_name'] ?? '',
                                'technician_id' => $row['tech_id'] ?? '',
                                'serial_no' => $serialNo,
                                'date_borrowed' => $row['a_date'] ?? '-',
                                'date_returned' => $row['a_return_date'] ?? '-'
                            ], JSON_HEX_QUOT | JSON_HEX_TAG);
                            echo "<tr> 
                                    <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td> 
                                    <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>  
                                    <td>" . htmlspecialchars($row['tech_name'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . htmlspecialchars($row['tech_id'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . $serialNo . "</td>
                                    <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . htmlspecialchars($row['a_return_date'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td class='action-buttons'>
                                        <span class='view-btn' onclick='showViewModal($ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                    </td>
                                  </tr>";
                        } 
                    } else { 
                        echo "<tr><td colspan='8' style='text-align: center;'>No returned assets found.</td></tr>"; 
                    } 
                    ?>
                </tbody>
            </table>

            <div class="pagination" id="returned-pagination">
                <?php
                $paginationParams = [];
                if ($searchTerm) {
                    $paginationParams['search'] = urlencode($searchTerm);
                }
                if ($assetNameFilter) {
                    $paginationParams['asset_name'] = urlencode($assetNameFilter);
                }
                if ($technicianNameFilter) {
                    $paginationParams['technician_name'] = urlencode($technicianNameFilter);
                }
                if ($page > 1) {
                    $paginationParams['page'] = $page - 1;
                    echo "<a href='javascript:searchReturned(" . ($page - 1) . ")' class='pagination-link'><i class='fas fa-chevron-left'></i></a>";
                } else {
                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                }
                echo "<span class='current-page'>Page $page of $totalPages</span>";
                if ($page < $totalPages) {
                    $paginationParams['page'] = $page + 1;
                    echo "<a href='javascript:searchReturned(" . ($page + 1) . ")' class='pagination-link'><i class='fas fa-chevron-right'></i></a>";
                } else {
                    echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- View Asset Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Returned Asset</h2>
        </div>
        <div id="viewContent"></div>
        <div class="modal-footer">
            <button class="modal-btn cancel" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- Asset Name Filter Modal -->
<div id="assetFilterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Filter by Asset Name</h2>
        </div>
        <form method="GET" id="assetFilterForm" class="modal-form">
            <input type="hidden" name="ajax" value="true">
            <div class="form-group">
                <label for="filter_asset_name">Asset Name</label>
                <select name="asset_name" id="filter_asset_name">
                    <option value="">All Assets</option>
                    <?php foreach ($assetNames as $name): ?>
                        <option value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $assetNameFilter === $name ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('assetFilterModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Technician Name Filter Modal -->
<div id="technicianFilterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Filter by Technician Name</h2>
        </div>
        <form method="GET" id="technicianFilterForm" class="modal-form">
            <input type="hidden" name="ajax" value="true">
            <div class="form-group">
                <label for="filter_technician_name">Technician Name</label>
                <select name="technician_name" id="filter_technician_name">
                    <option value="">All Technicians</option>
                    <?php foreach ($technicianNames as $name): ?>
                        <option value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $technicianNameFilter === $name ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('technicianFilterModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentSearchPage = 1;
let defaultPage = <?php echo json_encode($page); ?>;
let updateInterval = null;
let currentAssetFilter = '<?php echo htmlspecialchars($assetNameFilter); ?>';
let currentTechnicianFilter = '<?php echo htmlspecialchars($technicianNameFilter); ?>';

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

function searchReturned(page = 1) {
    const searchTerm = document.getElementById('searchInput').value;
    const tbody = document.getElementById('tableBody');
    const paginationContainer = document.getElementById('returned-pagination');

    currentSearchPage = page;

    // Create XMLHttpRequest for AJAX
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
    let url = `returnT.php?action=search&search=${encodeURIComponent(searchTerm)}&search_page=${page}`;
    if (currentAssetFilter) {
        url += `&asset_name=${encodeURIComponent(currentAssetFilter)}`;
    }
    if (currentTechnicianFilter) {
        url += `&technician_name=${encodeURIComponent(currentTechnicianFilter)}`;
    }
    xhr.open('GET', url, true);
    xhr.send();
}

function updatePagination(currentPage, totalPages, searchTerm) {
    const paginationContainer = document.getElementById('returned-pagination');
    let paginationHtml = '';

    if (currentPage > 1) {
        paginationHtml += `<a href="javascript:searchReturned(${currentPage - 1})" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="javascript:searchReturned(${currentPage + 1})" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

// Debounced search function
const debouncedSearchReturned = debounce(searchReturned, 300);

function showViewModal(data) {
    document.getElementById('viewContent').innerHTML = `
        <div class="view-details">
            <p><strong>Asset Ref No.:</strong> ${data.ref_no}</p>
            <p><strong>Asset Name:</strong> ${data.asset_name}</p>
            <p><strong>Technician Name:</strong> ${data.technician_name}</p>
            <p><strong>Technician ID:</strong> ${data.technician_id}</p>
            <p><strong>Asset Serial No.:</strong> ${data.serial_no}</p>
            <p><strong>Date Borrowed:</strong> ${data.date_borrowed}</p>
            <p><strong>Date Returned:</strong> ${data.date_returned}</p>
        </div>
    `;
    document.getElementById('viewModal').style.display = 'block';
}

function showAssetFilterModal() {
    document.getElementById('filter_asset_name').value = currentAssetFilter;
    document.getElementById('assetFilterModal').style.display = 'block';
}

function showTechnicianFilterModal() {
    document.getElementById('filter_technician_name').value = currentTechnicianFilter;
    document.getElementById('technicianFilterModal').style.display = 'block';
}

function updateTable() {
    const searchTerm = document.getElementById('searchInput').value;
    if (searchTerm || currentAssetFilter || currentTechnicianFilter) {
        searchReturned(currentSearchPage);
    } else {
        let url = `returnT.php?page=${defaultPage}`;
        if (currentAssetFilter) {
            url += `&asset_name=${encodeURIComponent(currentAssetFilter)}`;
        }
        if (currentTechnicianFilter) {
            url += `&technician_name=${encodeURIComponent(currentTechnicianFilter)}`;
        }
        fetch(url)
            .then(response => response.text())
            .then(data => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const newTableBody = doc.querySelector('#tableBody');
                const currentTableBody = document.querySelector('#tableBody');
                currentTableBody.innerHTML = newTableBody.innerHTML;
            })
            .catch(error => console.error('Error updating table:', error));
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    if (modalId === 'assetFilterModal') {
        document.getElementById('assetFilterForm').reset();
    } else if (modalId === 'technicianFilterModal') {
        document.getElementById('technicianFilterForm').reset();
    }
}

function exportTable(format) {
    const searchTerm = document.getElementById('searchInput').value;

    let url = `returnT.php?action=export_data&search=${encodeURIComponent(searchTerm)}`;
    if (currentAssetFilter) {
        url += `&asset_name=${encodeURIComponent(currentAssetFilter)}`;
    }
    if (currentTechnicianFilter) {
        url += `&technician_name=${encodeURIComponent(currentTechnicianFilter)}`;
    }

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                const data = response.data;

                if (format === 'excel') {
                    // Create Excel file
                    const ws = XLSX.utils.json_to_sheet(data);
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Returned Assets');
                    XLSX.writeFile(wb, 'returned_assets.xlsx');
                } else if (format === 'csv') {
                    // Create CSV file
                    const ws = XLSX.utils.sheet_to_csv(data);
                    const blob = new Blob([ws], { type: 'text/csv;charset=utf-8;' });
                    saveAs(blob, 'returned_assets.csv');
                }
            } catch (e) {
                console.error('Error during export:', e);
                alert('Error exporting data: ' + e.message);
            }
        }
    };
    xhr.open('GET', url, true);
    xhr.send();
}

window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
});

// Handle asset filter form submission
document.getElementById('assetFilterForm').addEventListener('submit', function(e) {
    e.preventDefault();
    currentAssetFilter = document.getElementById('filter_asset_name').value;
    closeModal('assetFilterModal');
    searchReturned(1); // Reset to page 1 when applying filters
});

// Handle technician filter form submission
document.getElementById('technicianFilterForm').addEventListener('submit', function(e) {
    e.preventDefault();
    currentTechnicianFilter = document.getElementById('filter_technician_name').value;
    closeModal('technicianFilterModal');
    searchReturned(1); // Reset to page 1 when applying filters
});

// Initialize auto-update table every 30 seconds
document.addEventListener('DOMContentLoaded', () => {
    updateInterval = setInterval(updateTable, 30000);

    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('alert-hidden');
            setTimeout(() => alert.remove(), 500);
        }, 2000);
    });

    // Initialize search on page load if there's a search term or filter
    const searchInput = document.getElementById('searchInput');
    if (searchInput.value || currentAssetFilter || currentTechnicianFilter) {
        searchReturned();
    }
});

// Clear interval when leaving the page
window.addEventListener('beforeunload', () => {
    if (updateInterval) {
        clearInterval(updateInterval);
    }
});
</script>
</body>
</html>

<?php 
$conn->close();
?>