<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) { 
    header("Location: index.php");
    exit(); 
}

// Initialize variables for edit form
$return_assetsname = $return_quantity = $return_techname = $return_techid = $return_date = "";
$return_assetsnameErr = $return_quantityErr = $return_technameErr = $return_techidErr = "";

// Handle edit request via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_asset']) && isset($_POST['r_id'])) {
    $id = (int)$_POST['r_id'];
    $return_assetsname = trim($_POST['asset_name']);
    $return_quantity = trim($_POST['return_quantity']);
    $return_techname = trim($_POST['tech_name']);
    $return_techid = trim($_POST['tech_id']);
    $return_date = trim($_POST['date']);

    // Basic validation
    $errors = [];
    $return_quantity = filter_var($return_quantity, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if (empty($return_assetsname)) {
        $errors[] = "Asset name is required.";
    }
    if ($return_quantity === false) {
        $errors[] = "Quantity must be a positive integer.";
    }
    if (empty($return_techname)) {
        $errors[] = "Technician name is required.";
    }
    if (empty($return_techid)) {
        $errors[] = "Technician ID is required.";
    }
    if (empty($return_date) || !strtotime($return_date)) {
        $errors[] = "Valid return date is required.";
    }

    if (empty($errors)) {
        $sqlUpdate = "UPDATE tbl_returned SET r_assets_name = ?, r_quantity = ?, r_technician_name = ?, r_technician_id = ?, r_date = ? WHERE r_id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("sisssi", $return_assetsname, $return_quantity, $return_techname, $return_techid, $return_date, $id);

        if ($stmtUpdate->execute()) {
            $response = ['status' => 'success', 'message' => 'Record updated successfully!'];
        } else {
            $response = ['status' => 'error', 'message' => 'Error updating record: ' . $conn->error];
        }
        $stmtUpdate->close();
    } else {
        $response = ['status' => 'error', 'message' => implode(' ', $errors)];
    }

    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    } else {
        $_SESSION[$response['status'] == 'success' ? 'message' : 'error'] = $response['message'];
        header("Location: returnT.php?updated=true");
        exit();
    }
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_asset']) && isset($_POST['r_id'])) {
    $id = (int)$_POST['r_id'];
    
    $sql = "DELETE FROM tbl_returned WHERE r_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Record deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting record: " . $conn->error;
    }
    
    $stmt->close();
    header("Location: returnT.php");
    exit();
}

// Get user details for the header
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

// Handle AJAX search request with filters
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $page = isset($_GET['search_page']) ? (int)$_GET['search_page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $assetNameFilter = isset($_GET['asset_name']) ? trim($_GET['asset_name']) : '';
    $technicianNameFilter = isset($_GET['technician_name']) ? trim($_GET['technician_name']) : '';
    $output = '';

    // Build the WHERE clause dynamically
    $whereClauses = [];
    $params = [];
    $paramTypes = '';

    if ($searchTerm !== '') {
        $whereClauses[] = "(r_assets_name LIKE ? OR r_technician_name LIKE ? OR r_technician_id LIKE ? OR r_date LIKE ?)";
        $searchWildcard = "%$searchTerm%";
        $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
        $paramTypes .= 'ssss';
    }

    if ($assetNameFilter !== '') {
        $whereClauses[] = "r_assets_name = ?";
        $params[] = $assetNameFilter;
        $paramTypes .= 's';
    }

    if ($technicianNameFilter !== '') {
        $whereClauses[] = "r_technician_name = ?";
        $params[] = $technicianNameFilter;
        $paramTypes .= 's';
    }

    $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // Count total matching records for pagination
    $countSql = "SELECT COUNT(*) as total FROM tbl_returned $whereClause";
    $countStmt = $conn->prepare($countSql);
    if ($paramTypes !== '') {
        $countStmt->bind_param($paramTypes, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = ceil($totalRecords / $limit);

    // Fetch paginated search results
    $sql = "SELECT r_id, r_assets_name, r_quantity, r_technician_name, r_technician_id, r_date 
            FROM tbl_returned 
            $whereClause 
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

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output .= "<tr> 
                          <td>{$row['r_id']}</td> 
                          <td>" . htmlspecialchars($row['r_assets_name'], ENT_QUOTES, 'UTF-8') . "</td>  
                          <td>{$row['r_quantity']}</td>
                          <td>" . htmlspecialchars($row['r_technician_name'], ENT_QUOTES, 'UTF-8') . "</td>
                          <td>{$row['r_technician_id']}</td>    
                          <td>{$row['r_date']}</td> 
                          <td class='action-buttons'>
                              <a class='view-btn' onclick=\"showViewModal('{$row['r_id']}', '" . htmlspecialchars($row['r_assets_name'], ENT_QUOTES, 'UTF-8') . "', '{$row['r_quantity']}', '" . htmlspecialchars($row['r_technician_name'], ENT_QUOTES, 'UTF-8') . "', '{$row['r_technician_id']}', '{$row['r_date']}')\" title='View'><i class='fas fa-eye'></i></a>
                              <a class='edit-btn' onclick=\"showEditModal('{$row['r_id']}', '" . htmlspecialchars($row['r_assets_name'], ENT_QUOTES, 'UTF-8') . "', '{$row['r_quantity']}', '" . htmlspecialchars($row['r_technician_name'], ENT_QUOTES, 'UTF-8') . "', '{$row['r_technician_id']}', '{$row['r_date']}')\" title='Edit'><i class='fas fa-edit'></i></a>
                              <a class='delete-btn' onclick=\"showDeleteModal('{$row['r_id']}', '" . htmlspecialchars($row['r_assets_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                          </td>
                        </tr>";
        }
    } else {
        $output = "<tr><td colspan='7' style='text-align: center;'>No returned assets found.</td></tr>";
    }
    $stmt->close();

    // Add pagination data
    $output .= "<script>
        updatePagination($page, $totalPages, '$searchTerm');
    </script>";

    echo $output;
    exit();
}

// Check for success messages
if (isset($_GET['deleted']) && $_GET['deleted'] == 'true') {
    $_SESSION['message'] = "Record deleted successfully!";
}
if (isset($_GET['updated']) && $_GET['updated'] == 'true') {
    $_SESSION['message'] = "Record updated successfully!";
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

    // Fetch unique asset names and technician names for filter modals
    $assetNamesQuery = "SELECT DISTINCT r_assets_name FROM tbl_returned ORDER BY r_assets_name";
    $assetNamesResult = $conn->query($assetNamesQuery);
    $assetNames = [];
    while ($row = $assetNamesResult->fetch_assoc()) {
        $assetNames[] = $row['r_assets_name'];
    }

    $technicianNamesQuery = "SELECT DISTINCT r_technician_name FROM tbl_returned ORDER BY r_technician_name";
    $technicianNamesResult = $conn->query($technicianNamesQuery);
    $technicianNames = [];
    while ($row = $technicianNamesResult->fetch_assoc()) {
        $technicianNames[] = $row['r_technician_name'];
    }

    // Pagination setup
    $limit = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    // Count total records for pagination
    $countQuery = "SELECT COUNT(*) as total FROM tbl_returned";
    $countResult = $conn->query($countQuery);
    $totalRecords = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($totalRecords / $limit);

    // Main query with pagination
    $sqlBorrowed = "SELECT r_id, r_assets_name, r_quantity, r_technician_name, r_technician_id, r_date FROM tbl_returned LIMIT ?, ?";
    $stmt = $conn->prepare($sqlBorrowed);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ii", $offset, $limit);
    $stmt->execute();
    $resultBorrowed = $stmt->get_result();
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
    <link rel="stylesheet" href="returnsT.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>

    .filter-btn {
        background: transparent !important; /* Explicitly transparent, overrides other styles */
        border: none;
        cursor: pointer;
        font-size: 15px;
        color: var(--light, #f5f8fc); /* White text */
        margin-left: 5px;
        vertical-align: middle;
        padding: 0; /* Remove any padding that might show background */
        outline: none; /* Remove any outline that might affect appearance */
    }
    .filter-btn:hover {
        color: var(--primary-dark, hsl(211, 45.70%, 84.10%)); /* White shade for hover */
        background: transparent !important; /* Ensure no background on hover */
    }
    /* Neutralize potential th background interference */
    th .filter-btn {
        background: transparent !important;
    }
</style>
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
            <li><a href="support_close.php"><img src="image/ticket.png" alt="Supports Record" class="icon" /> <span>Support Record</span></a></li>
            <li><a href="logs.php"><img src="image/log.png" alt="Logs" class="icon" /> <span>Logs</span></a></li>
            <li><a href="returnT.php" class="active"><img src="image/record.png" alt="Returned Records" class="icon" /> <span>Returned Records</span></a></li>
            <li><a href="deployedT.php"><img src="image/record.png" alt="Deployed Records" class="icon" /> <span>Deployed Records</span></a></li>
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>

    <div class="container">
        <div class="upper">
            <h1>Returned Assets</h1>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search returned assets..." onkeyup="debouncedSearchReturned()">
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
                <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
        </div>

        <div class="table-box glass-container">
            <?php if ($userType === 'admin'): ?>
                <div class="username">
                    Welcome, <?php echo htmlspecialchars($firstName); ?>!
                    <i class="fas fa-user-shield admin-icon"></i>
                </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <button class="action-btn export-btn"><i class="fas fa-download"></i> Export</button>
            </div>
            
            <table id="returned-assets-table">
                <thead>
                    <tr>
                        <th>Return ID</th>
                        <th>Asset Name <button class="filter-btn" onclick="showAssetFilterModal()" title="Filter by Asset Name"><i class='bx bx-filter'></i></button></th>
                        <th>Quantity</th>
                        <th>Technician Name <button class="filter-btn" onclick="showTechnicianFilterModal()" title="Filter by Technician Name"><i class='bx bx-filter'></i></button></th>
                        <th>Technician ID</th>
                        <th>Return Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php 
                    if ($resultBorrowed && $resultBorrowed->num_rows > 0) { 
                        while ($row = $resultBorrowed->fetch_assoc()) { 
                            echo "<tr> 
                                    <td>{$row['r_id']}</td> 
                                    <td>" . htmlspecialchars($row['r_assets_name'], ENT_QUOTES, 'UTF-8') . "</td>  
                                    <td>{$row['r_quantity']}</td>
                                    <td>" . htmlspecialchars($row['r_technician_name'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>{$row['r_technician_id']}</td>    
                                    <td>{$row['r_date']}</td> 
                                    <td class='action-buttons'>
                                        <a class='view-btn' onclick=\"showViewModal('{$row['r_id']}', '" . htmlspecialchars($row['r_assets_name'], ENT_QUOTES, 'UTF-8') . "', '{$row['r_quantity']}', '" . htmlspecialchars($row['r_technician_name'], ENT_QUOTES, 'UTF-8') . "', '{$row['r_technician_id']}', '{$row['r_date']}')\" title='View'><i class='fas fa-eye'></i></a>
                                        <a class='edit-btn' onclick=\"showEditModal('{$row['r_id']}', '" . htmlspecialchars($row['r_assets_name'], ENT_QUOTES, 'UTF-8') . "', '{$row['r_quantity']}', '" . htmlspecialchars($row['r_technician_name'], ENT_QUOTES, 'UTF-8') . "', '{$row['r_technician_id']}', '{$row['r_date']}')\" title='Edit'><i class='fas fa-edit'></i></a>
                                        <a class='delete-btn' onclick=\"showDeleteModal('{$row['r_id']}', '" . htmlspecialchars($row['r_assets_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                                    </td>
                                  </tr>"; 
                        } 
                    } else { 
                        echo "<tr><td colspan='7' style='text-align: center;'>No returned assets found.</td></tr>"; 
                    } 
                    ?>
                </tbody>
            </table>

            <div class="pagination" id="returned-pagination">
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

<!-- Edit Asset Modal -->
<div id="editAssetModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Returned Asset</h2>
        </div>
        <form method="POST" id="editAssetForm" class="modal-form">
            <input type="hidden" name="edit_asset" value="1">
            <input type="hidden" name="ajax" value="true">
            <input type="hidden" name="r_id" id="edit_r_id">
            <label for="edit_asset_name">Asset Name</label>
            <input type="text" name="asset_name" id="edit_asset_name" required>
            <label for="edit_return_quantity">Quantity</label>
            <input type="number" name="return_quantity" id="edit_return_quantity" min="1" required>
            <label for="edit_tech_name">Technician Name</label>
            <input type="text" name="tech_name" id="edit_tech_name" required>
            <label for="edit_tech_id">Technician ID</label>
            <input type="text" name="tech_id" id="edit_tech_id" required>
            <label for="edit_date">Return Date</label>
            <input type="date" name="date" id="edit_date" required>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('editAssetModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Update Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Delete Asset</h2>
        </div>
        <p>Are you sure you want to delete the returned asset: <span id="deleteAssetName"></span>?</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="r_id" id="deleteAssetId">
            <input type="hidden" name="delete_asset" value="1">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Delete</button>
            </div>
        </form>
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
                        <option value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
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
                        <option value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
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
let currentAssetFilter = '<?php echo isset($_GET['asset_name']) ? $_GET['asset_name'] : ''; ?>';
let currentTechnicianFilter = '<?php echo isset($_GET['technician_name']) ? $_GET['technician_name'] : ''; ?>';

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
            tbody.innerHTML = xhr.responseText.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        }
    };
    let url = `returnT.php?action=search&search=${encodeURIComponent(searchTerm)}&search_page=${searchTerm || currentAssetFilter || currentTechnicianFilter ? page : defaultPage}`;
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

function showViewModal(id, name, quantity, techName, techId, date) {
    document.getElementById('viewContent').innerHTML = `
        <div class="view-details">
            <p><strong>Return ID:</strong> ${id}</p>
            <p><strong>Asset Name:</strong> ${name}</p>
            <p><strong>Quantity:</strong> ${quantity}</p>
            <p><strong>Technician Name:</strong> ${techName}</p>
            <p><strong>Technician ID:</strong> ${techId}</p>
            <p><strong>Return Date:</strong> ${date}</p>
        </div>
    `;
    document.getElementById('viewModal').style.display = 'block';
}

function showEditModal(id, name, quantity, techName, techId, date) {
    document.getElementById('edit_r_id').value = id;
    document.getElementById('edit_asset_name').value = name;
    document.getElementById('edit_return_quantity').value = quantity;
    document.getElementById('edit_tech_name').value = techName;
    document.getElementById('edit_tech_id').value = techId;
    document.getElementById('edit_date').value = date;
    document.getElementById('editAssetModal').style.display = 'block';
}

function showDeleteModal(id, name) {
    document.getElementById('deleteAssetName').textContent = name || 'Unknown Asset';
    document.getElementById('deleteAssetId').value = id;
    document.getElementById('deleteModal').style.display = 'block';
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
        fetch(`returnT.php?page=${defaultPage}`)
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

window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
});

// Handle edit form submission via AJAX
document.getElementById('editAssetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('returnT.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const alertContainer = document.querySelector('.alert-container');
        const alert = document.createElement('div');
        alert.className = `alert alert-${data.status}`;
        alert.textContent = data.message;
        alertContainer.appendChild(alert);

        setTimeout(() => {
            alert.classList.add('alert-hidden');
            setTimeout(() => alert.remove(), 500);
        }, 2000);

        if (data.status === 'success') {
            closeModal('editAssetModal');
            updateTable();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const alertContainer = document.querySelector('.alert-container');
        const alert = document.createElement('div');
        alert.className = 'alert alert-error';
        alert.textContent = 'An error occurred while updating the record.';
        alertContainer.appendChild(alert);

        setTimeout(() => {
            alert.classList.add('alert-hidden');
            setTimeout(() => alert.remove(), 500);
        }, 2000);
    });
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


