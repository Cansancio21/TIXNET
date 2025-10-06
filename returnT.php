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

// Initialize variables
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'returned';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$assetNameFilter = isset($_GET['asset_name']) ? trim($_GET['asset_name']) : '';
$technicianNameFilter = isset($_GET['technician_name']) ? trim($_GET['technician_name']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$totalPages = 1;
$resultAssets = null;
$assetNames = [];
$technicianNames = [];
$deployedAssetNames = [];
$deployedTechnicianNames = [];
$totalReturnedAssets = 0;
$totalDeployedAssets = 0;


// In the export_data action
if (isset($_GET['action']) && $_GET['action'] === 'export_data') {
    $status = $currentTab === 'returned' ? 'Returned' : 'Deployed';
    $searchLike = $searchTerm ? "%$searchTerm%" : null;
    $params = [];
    $types = '';
    $whereClauses = [];

    $whereClauses[] = "a_status = ?";
    $params[] = $status;
    $types .= 's';

    if ($searchTerm) {
        $whereClauses[] = "(a_ref_no LIKE ? OR a_name LIKE ? OR tech_name LIKE ? OR tech_id LIKE ? OR a_serial_no LIKE ? OR a_date LIKE ? OR a_return_date LIKE ?)";
        $params = array_merge($params, array_fill(0, 7, $searchLike));
        $types .= 'sssssss';
    }
    if ($assetNameFilter) {
        $whereClauses[] = "a_name = ?";
        $params[] = $assetNameFilter;
        $types .= 's';
    }
    if ($technicianNameFilter) {
        $whereClauses[] = "tech_name = ?";
        $params[] = $technicianNameFilter;
        $types .= 's';
    }

    $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $orderBy = $currentTab === 'returned' ? 'a_return_date' : 'a_date';
    $sqlExport = "SELECT a_id, a_ref_no, a_name, tech_name, tech_id, a_serial_no, a_date, a_return_date 
                  FROM tbl_asset_status $whereClause 
                  ORDER BY $orderBy DESC";
    $stmtExport = $conn->prepare($sqlExport);
    if (!$stmtExport) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Database query preparation failed']);
        exit;
    }
    if ($types !== '') {
        $stmtExport->bind_param($types, ...$params);
    }
    if (!$stmtExport->execute()) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Database query execution failed']);
        exit;
    }
    $resultExport = $stmtExport->get_result();

    $records = [];
    while ($row = $resultExport->fetch_assoc()) {
        $records[] = [
            'Asset Ref No.' => $row['a_ref_no'] ?? '',
            'Asset Name' => $row['a_name'] ?? '',
            'Technician Name' => $row['tech_name'] ?? '',
            'Technician ID' => $row['tech_id'] ?? '',
            'Asset Serial No.' => $row['a_serial_no'] === '0' || empty($row['a_serial_no']) ? '' : $row['a_serial_no'],
            'Date Borrowed/Deployed' => $row['a_date'] ?? '-',
            'Date Returned' => $currentTab === 'returned' ? ($row['a_return_date'] ?? '-') : '-'
        ];
    }
    $stmtExport->close();

    header('Content-Type: application/json');
    echo json_encode(['data' => $records]);
    exit;
}

// Handle AJAX search request with filters
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    $status = $currentTab === 'returned' ? 'Returned' : 'Deployed';
    $searchLike = $searchTerm ? "%$searchTerm%" : null;
    $params = [];
    $types = '';
    $whereClauses = [];

    $whereClauses[] = "a_status = ?";
    $params[] = $status;
    $types .= 's';

    if ($searchTerm) {
        $whereClauses[] = "(a_ref_no LIKE ? OR a_name LIKE ? OR tech_name LIKE ? OR tech_id LIKE ? OR a_serial_no LIKE ? OR a_date LIKE ? OR a_return_date LIKE ?)";
        $params = array_merge($params, array_fill(0, 7, $searchLike));
        $types .= 'sssssss';
    }
    if ($assetNameFilter) {
        $whereClauses[] = "a_name = ?";
        $params[] = $assetNameFilter;
        $types .= 's';
    }
    if ($technicianNameFilter) {
        $whereClauses[] = "tech_name = ?";
        $params[] = $technicianNameFilter;
        $types .= 's';
    }

    $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // Count total matching records for pagination
    $countSql = "SELECT COUNT(*) as total FROM tbl_asset_status $whereClause";
    $countStmt = $conn->prepare($countSql);
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = max(1, ceil($totalRecords / $limit));

    // Fetch paginated search results
    $orderBy = $currentTab === 'returned' ? 'a_return_date' : 'a_date';
    $sql = "SELECT a_id, a_ref_no, a_name, tech_name, tech_id, a_serial_no, a_date, a_return_date 
            FROM tbl_asset_status 
            $whereClause 
            ORDER BY $orderBy DESC 
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $params[] = $offset;
    $params[] = $limit;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $serialNo = ($row['a_serial_no'] === '0' || empty($row['a_serial_no'])) ? '' : htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8');
            $assetData = json_encode([
                'id' => $row['a_id'],
                'ref_no' => $row['a_ref_no'] ?? '',
                'asset_name' => $row['a_name'] ?? '',
                'technician_name' => $row['tech_name'] ?? '',
                'technician_id' => $row['tech_id'] ?? '',
                'serial_no' => $serialNo,
                'date_deployed' => $row['a_date'] ?? '-',
                'date_returned' => $row['a_return_date'] ?? '-'
            ], JSON_HEX_QUOT | JSON_HEX_TAG);
            $dateColumn = $currentTab === 'returned' ? htmlspecialchars($row['a_return_date'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8');
            echo "<tr> 
                    <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td> 
                    <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>  
                    <td>" . htmlspecialchars($row['tech_name'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['tech_id'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . $serialNo . "</td>
                    <td>" . $dateColumn . "</td>
                    <td class='action-buttons'>
                        <span class='view-btn' onclick='showViewModal($assetData)' title='View'><i class='fas fa-eye'></i></span>
                    </td>
                  </tr>";
        }
    } else {
        $statusText = $currentTab === 'returned' ? 'returned' : 'deployed';
        echo "<tr><td colspan='7' style='text-align: center;'>No $statusText assets found.</td></tr>";
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

    // Count total assets for each tab
    $sqlReturnedCount = "SELECT COUNT(*) as total FROM tbl_asset_status WHERE a_status = 'Returned'";
    $resultReturnedCount = $conn->query($sqlReturnedCount);
    $totalReturnedAssets = $resultReturnedCount->fetch_assoc()['total'] ?? 0;

    $sqlDeployedCount = "SELECT COUNT(*) as total FROM tbl_asset_status WHERE a_status = 'Deployed'";
    $resultDeployedCount = $conn->query($sqlDeployedCount);
    $totalDeployedAssets = $resultDeployedCount->fetch_assoc()['total'] ?? 0;

    // Fetch unique asset names and technician names for returned assets
    $assetNamesQuery = "SELECT DISTINCT a_name FROM tbl_asset_status WHERE a_status = 'Returned' ORDER BY a_name";
    $assetNamesResult = $conn->query($assetNamesQuery);
    while ($row = $assetNamesResult->fetch_assoc()) {
        $assetNames[] = $row['a_name'];
    }

    $technicianNamesQuery = "SELECT DISTINCT tech_name FROM tbl_asset_status WHERE a_status = 'Returned' ORDER BY tech_name";
    $technicianNamesResult = $conn->query($technicianNamesQuery);
    while ($row = $technicianNamesResult->fetch_assoc()) {
        $technicianNames[] = $row['tech_name'];
    }

    // Fetch unique asset names and technician names for deployed assets
    $deployedAssetNamesQuery = "SELECT DISTINCT a_name FROM tbl_asset_status WHERE a_status = 'Deployed' ORDER BY a_name";
    $deployedAssetNamesResult = $conn->query($deployedAssetNamesQuery);
    while ($row = $deployedAssetNamesResult->fetch_assoc()) {
        $deployedAssetNames[] = $row['a_name'];
    }

    $deployedTechnicianNamesQuery = "SELECT DISTINCT tech_name FROM tbl_asset_status WHERE a_status = 'Deployed' ORDER BY tech_name";
    $deployedTechnicianNamesResult = $conn->query($deployedTechnicianNamesQuery);
    while ($row = $deployedTechnicianNamesResult->fetch_assoc()) {
        $deployedTechnicianNames[] = $row['tech_name'];
    }

    // Build the WHERE clause dynamically
    $searchLike = $searchTerm ? "%$searchTerm%" : null;
    $params = [];
    $types = '';
    $whereClauses = [];

    $status = $currentTab === 'returned' ? 'Returned' : 'Deployed';
    $whereClauses[] = "a_status = ?";
    $params[] = $status;
    $types .= 's';

    if ($searchTerm) {
        $whereClauses[] = "(a_ref_no LIKE ? OR a_name LIKE ? OR tech_name LIKE ? OR tech_id LIKE ? OR a_serial_no LIKE ? OR a_date LIKE ? OR a_return_date LIKE ?)";
        $params = array_merge($params, array_fill(0, 7, $searchLike));
        $types .= 'sssssss';
    }
    if ($assetNameFilter) {
        $whereClauses[] = "a_name = ?";
        $params[] = $assetNameFilter;
        $types .= 's';
    }
    if ($technicianNameFilter) {
        $whereClauses[] = "tech_name = ?";
        $params[] = $technicianNameFilter;
        $types .= 's';
    }

    $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // Count total records for pagination
    $countSql = "SELECT COUNT(*) as total FROM tbl_asset_status $whereClause";
    $countStmt = $conn->prepare($countSql);
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = max(1, ceil($totalRecords / $limit));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $limit;

    // Main query with pagination
    $orderBy = $currentTab === 'returned' ? 'a_return_date' : 'a_date';
    $sqlAssets = "SELECT a_id, a_ref_no, a_name, tech_name, tech_id, a_serial_no, a_date, a_return_date 
                  FROM tbl_asset_status 
                  $whereClause 
                  ORDER BY $orderBy DESC 
                  LIMIT ?, ?";
    $stmt = $conn->prepare($sqlAssets);
    $params[] = $offset;
    $params[] = $limit;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resultAssets = $stmt->get_result();
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
    <title>Asset Records</title>
    <link rel="stylesheet" href="returnsT.css"> 
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
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        body.modal-open {
            overflow: hidden;
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
        #returnedAssetFilterModal .modal-content,
        #returnedTechnicianFilterModal .modal-content,
        #deployedAssetFilterModal .modal-content,
        #deployedTechnicianFilterModal .modal-content {
            margin-top: 165px;
        }
        /* Separate search inputs for each tab */
        #returned-search-input, #deployed-search-input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 14px;
            background: var(--light);
            color: var(--dark);
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
         <ul>
          <li><a href="adminD.php"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
          <li><a href="viewU.php"><i class="fas fa-users icon"></i> <span>View Users</span></a></li>
          <li><a href="regular_close.php"><i class="fas fa-ticket-alt icon"></i> <span>Ticket Record</span></a></li>
          <li><a href="logs.php"><i class="fas fa-file-alt icon"></i> <span>Logs</span></a></li>
          <li><a href="returnT.php" class="active"><i class="fas fa-box icon"></i> <span>Asset Record</span></a></li>
          <li><a href="AdminPayments.php"><i class="fas fa-credit-card icon"></i> <span>Transactions</span></a></li>
         </ul>
      <footer>
       <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </footer>
    </div>

    <div class="container">
        <div class="upper">
            <h1>Asset Records</h1>
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

        <!-- Returned Assets Tab -->
        <div id="returned-tab" class="tab-content <?php echo $currentTab === 'returned' ? 'active' : ''; ?>">
            <!-- View Modal -->
            <div id="viewReturnedModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Returned Asset Details</h2>
                    </div>
                    <div id="viewReturnedContent"></div>
                    <div class="modal-footer">
                        <button class="modal-btn cancel" onclick="closeModal('viewReturnedModal')">Close</button>
                    </div>
                </div>
            </div>

            <!-- Returned Asset Filter Modal -->
            <div id="returnedAssetFilterModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Filter by Asset Name</h2>
                    </div>
                    <form id="returnedAssetFilterForm" class="modal-form">
                        <label for="returned_filter_asset_name">Asset Name</label>
                        <select name="asset_name" id="returned_filter_asset_name">
                            <option value="">All Assets</option>
                            <?php foreach ($assetNames as $name): ?>
                                <option value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $assetNameFilter === $name ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('returnedAssetFilterModal')">Cancel</button>
                            <button type="button" class="modal-btn confirm" onclick="applyReturnedAssetFilter()">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Returned Technician Filter Modal -->
            <div id="returnedTechnicianFilterModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Filter by Technician Name</h2>
                    </div>
                    <form id="returnedTechnicianFilterForm" class="modal-form">
                        <label for="returned_filter_technician_name">Technician Name</label>
                        <select name="technician_name" id="returned_filter_technician_name">
                            <option value="">All Technicians</option>
                            <?php foreach ($technicianNames as $name): ?>
                                <option value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $technicianNameFilter === $name ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('returnedTechnicianFilterModal')">Cancel</button>
                            <button type="button" class="modal-btn confirm" onclick="applyReturnedTechnicianFilter()">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-box glass-container">
                <h2>List of Returned Assets</h2>
                
                <!-- Tab Buttons -->
                <div class="tab-buttons">
                    <button class="tab-btn <?php echo $currentTab === 'returned' ? 'active' : ''; ?>" onclick="showTab('returned')">
                        Returned (<?php echo $totalReturnedAssets; ?>)
                    </button>
                    <button class="tab-btn <?php echo $currentTab === 'deployed' ? 'active' : ''; ?>" onclick="showTab('deployed')">
                        Deployed (<?php echo $totalDeployedAssets; ?>)
                    </button>
                </div>

                <div class="search-container">
                    <input type="text" class="search-bar" id="returned-search-input" placeholder="Search returned assets..." value="<?php echo htmlspecialchars($searchTerm); ?>" onkeyup="debouncedSearchReturnedAssets()">
                    <span class="search-icon"><i class="fas fa-search"></i></span>
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
                
                <table id="returned-assets-table">
                    <thead>
                        <tr>
                            <th>Asset Ref No.</th>
                            <th>Asset Name <button class="filter-btn" onclick="showReturnedAssetFilterModal()" title="Filter by Asset Name"><i class='bx bx-filter'></i></button></th>
                            <th>Technician Name <button class="filter-btn" onclick="showReturnedTechnicianFilterModal()" title="Filter by Technician Name"><i class='bx bx-filter'></i></button></th>
                            <th>Technician ID</th>
                            <th>Asset Serial No.</th>
                            <th>Date Returned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="returned-table-body">
                        <?php 
                        if ($currentTab === 'returned' && $resultAssets && $resultAssets->num_rows > 0) { 
                            while ($row = $resultAssets->fetch_assoc()) {
                                $serialNo = ($row['a_serial_no'] === '0' || empty($row['a_serial_no'])) ? '' : htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8');
                                $assetData = json_encode([
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
                                        <td>" . htmlspecialchars($row['a_return_date'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td class='action-buttons'>
                                            <span class='view-btn' onclick='showReturnedViewModal($assetData)' title='View'><i class='fas fa-eye'></i></span>
                                        </td>
                                      </tr>";
                            } 
                        } else if ($currentTab === 'returned') { 
                            echo "<tr><td colspan='7' style='text-align: center;'>No returned assets found.</td></tr>"; 
                        } 
                        ?>
                    </tbody>
                </table>

                <div class="pagination" id="returned-pagination">
                    <?php
                    if ($currentTab === 'returned') {
                        $paginationParams = ['tab' => $currentTab];
                        if ($searchTerm) $paginationParams['search'] = $searchTerm;
                        if ($assetNameFilter) $paginationParams['asset_name'] = $assetNameFilter;
                        if ($technicianNameFilter) $paginationParams['technician_name'] = $technicianNameFilter;
                        if ($page > 1) {
                            $paginationParams['page'] = $page - 1;
                            echo "<a href='returnT.php?" . http_build_query($paginationParams) . "' class='pagination-link'><i class='fas fa-chevron-left'></i></a>";
                        } else {
                            echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                        }
                        echo "<span class='current-page'>Page $page of $totalPages</span>";
                        if ($page < $totalPages) {
                            $paginationParams['page'] = $page + 1;
                            echo "<a href='returnT.php?" . http_build_query($paginationParams) . "' class='pagination-link'><i class='fas fa-chevron-right'></i></a>";
                        } else {
                            echo "<span class='pagination-link disabled'><i class='fas fa-chevron-right'></i></span>";
                        }
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Deployed Assets Tab -->
        <div id="deployed-tab" class="tab-content <?php echo $currentTab === 'deployed' ? 'active' : ''; ?>">
            <!-- View Modal -->
            <div id="viewDeployedModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Deployed Asset Details</h2>
                    </div>
                    <div id="viewDeployedContent"></div>
                    <div class="modal-footer">
                        <button class="modal-btn cancel" onclick="closeModal('viewDeployedModal')">Close</button>
                    </div>
                </div>
            </div>

            <!-- Deployed Asset Filter Modal -->
            <div id="deployedAssetFilterModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Filter by Asset Name</h2>
                    </div>
                    <form id="deployedAssetFilterForm" class="modal-form">
                        <label for="deployed_filter_asset_name">Asset Name</label>
                        <select name="asset_name" id="deployed_filter_asset_name">
                            <option value="">All Assets</option>
                            <?php foreach ($deployedAssetNames as $name): ?>
                                <option value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $assetNameFilter === $name ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('deployedAssetFilterModal')">Cancel</button>
                            <button type="button" class="modal-btn confirm" onclick="applyDeployedAssetFilter()">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Deployed Technician Filter Modal -->
            <div id="deployedTechnicianFilterModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Filter by Technician Name</h2>
                    </div>
                    <form id="deployedTechnicianFilterForm" class="modal-form">
                        <label for="deployed_filter_technician_name">Technician Name</label>
                        <select name="technician_name" id="deployed_filter_technician_name">
                            <option value="">All Technicians</option>
                            <?php foreach ($deployedTechnicianNames as $name): ?>
                                <option value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $technicianNameFilter === $name ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('deployedTechnicianFilterModal')">Cancel</button>
                            <button type="button" class="modal-btn confirm" onclick="applyDeployedTechnicianFilter()">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-box glass-container">
                <h2>List of Deployed Assets</h2>
                
                <!-- Tab Buttons -->
                <div class="tab-buttons">
                    <button class="tab-btn <?php echo $currentTab === 'returned' ? 'active' : ''; ?>" onclick="showTab('returned')">
                        Returned (<?php echo $totalReturnedAssets; ?>)
                    </button>
                    <button class="tab-btn <?php echo $currentTab === 'deployed' ? 'active' : ''; ?>" onclick="showTab('deployed')">
                        Deployed (<?php echo $totalDeployedAssets; ?>)
                    </button>
                </div>

                <div class="search-container">
                    <input type="text" class="search-bar" id="deployed-search-input" placeholder="Search deployed assets..." value="<?php echo htmlspecialchars($searchTerm); ?>" onkeyup="debouncedSearchDeployedAssets()">
                    <span class="search-icon"><i class="fas fa-search"></i></span>
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
                
                <table id="deployed-assets-table">
                    <thead>
                        <tr>
                            <th>Asset Ref No.</th>
                            <th>Asset Name <button class="filter-btn" onclick="showDeployedAssetFilterModal()" title="Filter by Asset Name"><i class='bx bx-filter'></i></button></th>
                            <th>Technician Name <button class="filter-btn" onclick="showDeployedTechnicianFilterModal()" title="Filter by Technician Name"><i class='bx bx-filter'></i></button></th>
                            <th>Technician ID</th>
                            <th>Asset Serial No.</th>
                            <th>Date Deployed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="deployed-table-body">
                        <?php 
                        if ($currentTab === 'deployed' && $resultAssets && $resultAssets->num_rows > 0) { 
                            while ($row = $resultAssets->fetch_assoc()) {
                                $serialNo = ($row['a_serial_no'] === '0' || empty($row['a_serial_no'])) ? '' : htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8');
                                $assetData = json_encode([
                                    'id' => $row['a_id'],
                                    'ref_no' => $row['a_ref_no'] ?? '',
                                    'asset_name' => $row['a_name'] ?? '',
                                    'technician_name' => $row['tech_name'] ?? '',
                                    'technician_id' => $row['tech_id'] ?? '',
                                    'serial_no' => $serialNo,
                                    'date_deployed' => $row['a_date'] ?? '-',
                                    'date_returned' => $row['a_return_date'] ?? '-'
                                ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                echo "<tr> 
                                        <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>  
                                        <td>" . htmlspecialchars($row['tech_name'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td>" . htmlspecialchars($row['tech_id'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td>" . $serialNo . "</td>
                                        <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td class='action-buttons'>
                                            <span class='view-btn' onclick='showDeployedViewModal($assetData)' title='View'><i class='fas fa-eye'></i></span>
                                        </td>
                                      </tr>";
                            } 
                        } else if ($currentTab === 'deployed') { 
                            echo "<tr><td colspan='7' style='text-align: center;'>No deployed assets found.</td></tr>"; 
                        } 
                        ?>
                    </tbody>
                </table>

                <div class="pagination" id="deployed-pagination">
                    <?php
                    if ($currentTab === 'deployed') {
                        $paginationParams = ['tab' => $currentTab];
                        if ($searchTerm) $paginationParams['search'] = $searchTerm;
                        if ($assetNameFilter) $paginationParams['asset_name'] = $assetNameFilter;
                        if ($technicianNameFilter) $paginationParams['technician_name'] = $technicianNameFilter;
                        if ($page > 1) {
                            $paginationParams['page'] = $page - 1;
                            echo "<a href='returnT.php?" . http_build_query($paginationParams) . "' class='pagination-link'><i class='fas fa-chevron-left'></i></a>";
                        } else {
                            echo "<span class='pagination-link disabled'><i class='fas fa-chevron-left'></i></span>";
                        }
                        echo "<span class='current-page'>Page $page of $totalPages</span>";
                        if ($page < $totalPages) {
                            $paginationParams['page'] = $page + 1;
                            echo "<a href='returnT.php?" . http_build_query($paginationParams) . "' class='pagination-link'><i class='fas fa-chevron-right'></i></a>";
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
let currentSearchPage = 1;
let defaultPage = <?php echo json_encode($page); ?>;
let updateInterval = null;
let currentAssetFilter = '<?php echo addslashes($assetNameFilter); ?>';
let currentTechnicianFilter = '<?php echo addslashes($technicianNameFilter); ?>';
const currentTab = '<?php echo $currentTab; ?>';

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

function showTab(tab) {
    // Update URL
    const params = new URLSearchParams(window.location.search);
    params.set('tab', tab);
    params.delete('page'); // Reset to first page
    window.location.href = `returnT.php?${params.toString()}`;
}

function searchReturnedAssets(page = 1) {
    const searchTerm = document.getElementById('returned-search-input').value;
    const tbody = document.getElementById('returned-table-body');
    const paginationContainer = document.getElementById('returned-pagination');

    currentSearchPage = page;

    // Create XMLHttpRequest for AJAX
    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                tbody.innerHTML = response.html;
                updateReturnedPagination(response.currentPage, response.totalPages, response.searchTerm);
            } catch (e) {
                console.error('Error parsing JSON:', e, xhr.responseText);
                alert('Error loading assets. Please try again.');
            }
        }
    };
    let url = `returnT.php?action=search&tab=returned&search=${encodeURIComponent(searchTerm)}&page=${page}`;
    if (currentAssetFilter) {
        url += `&asset_name=${encodeURIComponent(currentAssetFilter)}`;
    }
    if (currentTechnicianFilter) {
        url += `&technician_name=${encodeURIComponent(currentTechnicianFilter)}`;
    }
    xhr.open('GET', url, true);
    xhr.send();
}

function searchDeployedAssets(page = 1) {
    const searchTerm = document.getElementById('deployed-search-input').value;
    const tbody = document.getElementById('deployed-table-body');
    const paginationContainer = document.getElementById('deployed-pagination');

    currentSearchPage = page;

    // Create XMLHttpRequest for AJAX
    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                tbody.innerHTML = response.html;
                updateDeployedPagination(response.currentPage, response.totalPages, response.searchTerm);
            } catch (e) {
                console.error('Error parsing JSON:', e, xhr.responseText);
                alert('Error loading assets. Please try again.');
            }
        }
    };
    let url = `returnT.php?action=search&tab=deployed&search=${encodeURIComponent(searchTerm)}&page=${page}`;
    if (currentAssetFilter) {
        url += `&asset_name=${encodeURIComponent(currentAssetFilter)}`;
    }
    if (currentTechnicianFilter) {
        url += `&technician_name=${encodeURIComponent(currentTechnicianFilter)}`;
    }
    xhr.open('GET', url, true);
    xhr.send();
}

function updateReturnedPagination(currentPage, totalPages, searchTerm) {
    const paginationContainer = document.getElementById('returned-pagination');
    let paginationHtml = '';

    if (currentPage > 1) {
        paginationHtml += `<a href="javascript:searchReturnedAssets(${currentPage - 1})" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="javascript:searchReturnedAssets(${currentPage + 1})" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

function updateDeployedPagination(currentPage, totalPages, searchTerm) {
    const paginationContainer = document.getElementById('deployed-pagination');
    let paginationHtml = '';

    if (currentPage > 1) {
        paginationHtml += `<a href="javascript:searchDeployedAssets(${currentPage - 1})" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="javascript:searchDeployedAssets(${currentPage + 1})" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

// Debounced search functions
const debouncedSearchReturnedAssets = debounce(searchReturnedAssets, 300);
const debouncedSearchDeployedAssets = debounce(searchDeployedAssets, 300);

// Returned Tab Functions
function showReturnedViewModal(data) {
    const content = document.getElementById('viewReturnedContent');
    content.innerHTML = `
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
    document.getElementById('viewReturnedModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function showReturnedAssetFilterModal() {
    document.getElementById('returned_filter_asset_name').value = currentAssetFilter;
    document.getElementById('returnedAssetFilterModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function showReturnedTechnicianFilterModal() {
    document.getElementById('returned_filter_technician_name').value = currentTechnicianFilter;
    document.getElementById('returnedTechnicianFilterModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function applyReturnedAssetFilter() {
    currentAssetFilter = document.getElementById('returned_filter_asset_name').value;
    closeModal('returnedAssetFilterModal');
    searchReturnedAssets(1); // Reset to page 1 when applying filters
}

function applyReturnedTechnicianFilter() {
    currentTechnicianFilter = document.getElementById('returned_filter_technician_name').value;
    closeModal('returnedTechnicianFilterModal');
    searchReturnedAssets(1); // Reset to page 1 when applying filters
}

// Deployed Tab Functions
function showDeployedViewModal(data) {
    const content = document.getElementById('viewDeployedContent');
    content.innerHTML = `
        <div class="view-details">
            <p><strong>Asset Ref No.:</strong> ${data.ref_no}</p>
            <p><strong>Asset Name:</strong> ${data.asset_name}</p>
            <p><strong>Technician Name:</strong> ${data.technician_name}</p>
            <p><strong>Technician ID:</strong> ${data.technician_id}</p>
            <p><strong>Asset Serial No.:</strong> ${data.serial_no}</p>
            <p><strong>Date Deployed:</strong> ${data.date_deployed}</p>
        </div>
    `;
    document.getElementById('viewDeployedModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function showDeployedAssetFilterModal() {
    document.getElementById('deployed_filter_asset_name').value = currentAssetFilter;
    document.getElementById('deployedAssetFilterModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function showDeployedTechnicianFilterModal() {
    document.getElementById('deployed_filter_technician_name').value = currentTechnicianFilter;
    document.getElementById('deployedTechnicianFilterModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function applyDeployedAssetFilter() {
    currentAssetFilter = document.getElementById('deployed_filter_asset_name').value;
    closeModal('deployedAssetFilterModal');
    searchDeployedAssets(1); // Reset to page 1 when applying filters
}

function applyDeployedTechnicianFilter() {
    currentTechnicianFilter = document.getElementById('deployed_filter_technician_name').value;
    closeModal('deployedTechnicianFilterModal');
    searchDeployedAssets(1); // Reset to page 1 when applying filters
}

// Shared Modal Functions
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
}

function exportTable(format) {
    const currentTab = '<?php echo $currentTab; ?>';
    let searchTerm = '';
    if (currentTab === 'returned') {
        searchTerm = document.getElementById('returned-search-input').value;
    } else {
        searchTerm = document.getElementById('deployed-search-input').value;
    }

    let url = `returnT.php?action=export_data&tab=${currentTab}&search=${encodeURIComponent(searchTerm)}`;
    if (currentAssetFilter) {
        url += `&asset_name=${encodeURIComponent(currentAssetFilter)}`;
    }
    if (currentTechnicianFilter) {
        url += `&technician_name=${encodeURIComponent(currentTechnicianFilter)}`;
    }

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    const data = response.data;

                    if (format === 'excel') {
                        // Create Excel file
                        const ws = XLSX.utils.json_to_sheet(data);
                        const wb = XLSX.utils.book_new();
                        const sheetName = currentTab === 'returned' ? 'Returned Assets' : 'Deployed Assets';
                        XLSX.utils.book_append_sheet(wb, ws, sheetName);
                        XLSX.writeFile(wb, `${currentTab}_assets.xlsx`);
                    } else if (format === 'csv') {
                        // Create CSV file
                        const ws = XLSX.utils.json_to_sheet(data);
                        const csv = XLSX.utils.sheet_to_csv(ws);
                        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                        const fileName = currentTab === 'returned' ? 'returned_assets.csv' : 'deployed_assets.csv';
                        saveAs(blob, fileName);
                    }
                } catch (e) {
                    console.error('Error parsing response:', e, xhr.responseText);
                    alert('Error processing export data. Please check your connection and try again.');
                }
            } else {
                console.error('Export request failed:', xhr.status, xhr.statusText, xhr.responseText);
                alert('Error exporting data: Server returned status ' + xhr.status + '. Please try again.');
            }
        }
    };
    xhr.open('GET', url, true);
    xhr.send();
}

window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
});

// Initialize auto-update table every 30 seconds
document.addEventListener('DOMContentLoaded', () => {
    updateInterval = setInterval(() => {
        const currentTab = '<?php echo $currentTab; ?>';
        let searchTerm = '';
        if (currentTab === 'returned') {
            searchTerm = document.getElementById('returned-search-input').value;
            if (searchTerm || currentAssetFilter || currentTechnicianFilter) {
                searchReturnedAssets(currentSearchPage);
            }
        } else {
            searchTerm = document.getElementById('deployed-search-input').value;
            if (searchTerm || currentAssetFilter || currentTechnicianFilter) {
                searchDeployedAssets(currentSearchPage);
            }
        }
    }, 30000);

    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('alert-hidden');
            setTimeout(() => alert.remove(), 500);
        }, 2000);
    });

    // Initialize search on page load if there's a search term or filter
    const currentTab = '<?php echo $currentTab; ?>';
    if (currentTab === 'returned') {
        const searchInput = document.getElementById('returned-search-input');
        if (searchInput.value || currentAssetFilter || currentTechnicianFilter) {
            searchReturnedAssets();
        }
    } else {
        const searchInput = document.getElementById('deployed-search-input');
        if (searchInput.value || currentAssetFilter || currentTechnicianFilter) {
            searchDeployedAssets();
        }
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
if (isset($conn)) {
    $conn->close();
}
?>