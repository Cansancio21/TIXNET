<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Initialize user variables
$username = $_SESSION['username'];
$firstName = '';
$userType = '';
$avatarPath = 'default-avatar.png';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $username . '.png';

// Set avatar path
if (file_exists($userAvatar)) {
    $avatarPath = $userAvatar;
} else {
    $avatarPath = 'default-avatar.png';
}

// Fetch user details
if ($conn) {
    $sqlUser = "SELECT u_fname, u_type FROM tbl_user WHERE u_username = ?";
    $stmtUser = $conn->prepare($sqlUser);
    if ($stmtUser) {
        $stmtUser->bind_param("s", $username);
        $stmtUser->execute();
        $resultUser = $stmtUser->get_result();
        if ($resultUser->num_rows > 0) {
            $row = $resultUser->fetch_assoc();
            $firstName = $row['u_fname'];
            $userType = $row['u_type'];
        }
        $stmtUser->close();
    } else {
        $_SESSION['error'] = "Error preparing user query.";
    }
} else {
    $_SESSION['error'] = "Database connection failed.";
}

// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['tab'])) {
    $searchTerm = trim($_GET['search'] ?? '');
    $tab = $_GET['tab'] === 'archive' ? 'archive' : 'active';
    $page = isset($_GET['search_page']) ? (int)$_GET['search_page'] : 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;
    $output = '';

    $statusCondition = $tab === 'active' ? "a_status != 'Archived'" : "a_status = 'Archived'";

    if ($searchTerm === '') {
        $countSql = "SELECT COUNT(*) as total FROM tbl_assets WHERE $statusCondition";
        $countResult = $conn->query($countSql);
        $totalRecords = $countResult->fetch_assoc()['total'];
        $totalPages = ceil($totalRecords / $limit);

        $sql = "SELECT a_id, a_name, a_status, a_quantity, a_date 
                FROM tbl_assets 
                WHERE $statusCondition 
                ORDER BY a_date DESC 
                LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $offset, $limit);
    } else {
        $countSql = "SELECT COUNT(*) as total FROM tbl_assets 
                     WHERE $statusCondition AND (a_name LIKE ? OR a_status LIKE ? OR a_quantity LIKE ? OR a_date LIKE ?)";
        $countStmt = $conn->prepare($countSql);
        $searchWildcard = "%$searchTerm%";
        $countStmt->bind_param("ssss", $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalRecords = $countResult->fetch_assoc()['total'];
        $countStmt->close();

        $totalPages = ceil($totalRecords / $limit);

        $sql = "SELECT a_id, a_name, a_status, a_quantity, a_date 
                FROM tbl_assets 
                WHERE $statusCondition AND (a_name LIKE ? OR a_status LIKE ? OR a_quantity LIKE ? OR a_date LIKE ?)
                ORDER BY a_date DESC 
                LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssii", $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $offset, $limit);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output .= "<tr> 
                          <td>" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "</td> 
                          <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>  
                          <td>" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "</td>
                          <td>" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "</td>  
                          <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                          <td>";
            if ($userType !== 'technician') {
                if ($tab === 'active') {
                    $output .= "<a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                <a class='edit-btn' href='AssetE.php?id=" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "' title='Edit'><i class='fas fa-edit'></i></a>
                                <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>";
                } else {
                    $output .= "<a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                <a class='unarchive-btn' onclick=\"showUnarchiveModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                                <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>";
                }
            } else {
                $output .= "<a class='view-btn' onclick=\"showRestrictionMessage()\" title='View'><i class='fas fa-eye'></i></a>
                            <a class='edit-btn' onclick=\"showRestrictionMessage()\" title='Edit'><i class='fas fa-edit'></i></a>
                            <a class='archive-btn' onclick=\"showRestrictionMessage()\" title='Archive'><i class='fas fa-archive'></i></a>";
            }
            $output .= "</td></tr>";
        }
    } else {
        $output .= "<tr><td colspan='6'>No assets found.</td></tr>";
    }
    $stmt->close();

    $paginationId = $tab === 'active' ? 'active-pagination' : 'archived-pagination';
    $output .= "<script>updatePagination($page, $totalPages, '$tab', '$searchTerm', '$paginationId');</script>";
    echo $output;
    exit();
}

// Handle archive/unarchive/delete requests (restricted for technicians)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $userType !== 'technician') {
    if (isset($_POST['archive_asset'])) {
        $assetId = $_POST['a_id'];
        $sql = "UPDATE tbl_assets SET a_status = 'Archived' WHERE a_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $assetId);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Asset archived successfully!";
        } else {
            $_SESSION['error'] = "Error archiving asset.";
        }
        $stmt->close();
    } elseif (isset($_POST['unarchive_asset'])) {
        $assetId = $_POST['a_id'];
        $sql = "UPDATE tbl_assets SET a_status = 'Available' WHERE a_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $assetId);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Asset unarchived successfully!";
        } else {
            $_SESSION['error'] = "Error unarchiving asset.";
        }
        $stmt->close();
    } elseif (isset($_POST['delete_asset'])) {
        $assetId = $_POST['a_id'];
        $sql = "DELETE FROM tbl_assets WHERE a_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $assetId);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Asset deleted permanently!";
        } else {
            $_SESSION['error'] = "Error deleting asset.";
        }
        $stmt->close();
    }
    
    $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
    $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
    header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
    exit();
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && $userType === 'technician') {
    $_SESSION['error'] = "Only staff can add, view, edit, or archive assets.";
    $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
    $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
    header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
    exit();
}

// Pagination settings
$limit = 5;

// Active Assets Pagination
$activePage = isset($_GET['active_page']) ? (int)$_GET['active_page'] : 1;
$activeOffset = ($activePage - 1) * $limit;

// Archived Assets Pagination
$archivedPage = isset($_GET['archived_page']) ? (int)$_GET['archived_page'] : 1;
$archivedOffset = ($archivedPage - 1) * $limit;

if ($conn) {
    // Active Assets
    $activeCountQuery = "SELECT COUNT(*) as total FROM tbl_assets WHERE a_status != 'Archived'";
    $activeCountResult = $conn->query($activeCountQuery);
    $totalActive = $activeCountResult ? $activeCountResult->fetch_assoc()['total'] : 0;
    $totalActivePages = ceil($totalActive / $limit);

    $sqlActive = "SELECT a_id, a_name, a_status, a_quantity, a_date FROM tbl_assets WHERE a_status != 'Archived' ORDER BY a_date DESC LIMIT ?, ?";
    $stmtActive = $conn->prepare($sqlActive);
    $stmtActive->bind_param("ii", $activeOffset, $limit);
    $stmtActive->execute();
    $resultActive = $stmtActive->get_result();
    $stmtActive->close();

    // Archived Assets
    $archivedCountQuery = "SELECT COUNT(*) as total FROM tbl_assets WHERE a_status = 'Archived'";
    $archivedCountResult = $conn->query($archivedCountQuery);
    $totalArchived = $archivedCountResult ? $archivedCountResult->fetch_assoc()['total'] : 0;
    $totalArchivedPages = ceil($totalArchived / $limit);

    $sqlArchived = "SELECT a_id, a_name, a_status, a_quantity, a_date FROM tbl_assets WHERE a_status = 'Archived' ORDER BY a_date DESC LIMIT ?, ?";
    $stmtArchived = $conn->prepare($sqlArchived);
    $stmtArchived->bind_param("ii", $archivedOffset, $limit);
    $stmtArchived->execute();
    $resultArchived = $stmtArchived->get_result();
    $stmtArchived->close();
} else {
    $_SESSION['error'] = "Database connection failed.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Management</title>
    <link rel="stylesheet" href="assetT.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="staffD.php"><img src="https://img.icons8.com/plasticine/100/ticket.png" alt="ticket"/><span>View Tickets</span></a></li>
            <li><a href="assetsT.php" class="active"><img src="https://img.icons8.com/matisse/100/view.png" alt="view"/><span>View Assets</span></a></li>
            <li><a href="customersT.php"><img src="https://img.icons8.com/color/48/conference-skin-type-7.png" alt="conference-skin-type-7"/> <span>View Customers</span></a></li>
            <li><a href="borrowedStaff.php"><i class="fas fa-book"></i> <span>Borrowed Assets</span></a></li>
            <li><a href="addC.php"><img src="https://img.icons8.com/officel/40/add-user-male.png" alt="add-user-male"/><span>Add Customer</span></a></li>
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>

    <div class="container">
        <div class="upper"> 
            <h1>Asset Management</h1>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search assets..." onkeyup="debouncedSearchAssets()">
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
            <div class="assets">
                <h2>All Assets</h2>
                <div class="header-controls">
                    <div class="tab-buttons">
                        <button class="tab-btn active" onclick="showAssetTab('active')">Active (<?php echo $totalActive; ?>)</button>
                        <button class="tab-btn" onclick="showAssetTab('archive')">Archive 
                            <?php if ($totalArchived > 0): ?>
                                <span class="tab-badge"><?php echo $totalArchived; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
                <?php if ($userType !== 'technician'): ?>
                    <a href="registerAssets.php" class="add-btn"><i class="fas fa-plus"></i> Add Asset</a>
                <?php else: ?>
                    <a href="#" class="add-btn disabled" onclick="showRestrictionMessage()"><i class="fas fa-plus"></i> Add Asset</a>
                <?php endif; ?>
                <a href="exportAssets.php" class="export-btn"><i class="fas fa-download"></i> Export</a>
                <div id="assets-active" class="tab-content">
                    <table id="assets-table">
                        <thead>
                            <tr>
                                <th>Asset ID</th>
                                <th>Asset Name</th>
                                <th>Asset Status</th>
                                <th>Asset Quantity</th>
                                <th>Date Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="assets-table-body">
                            <?php 
                            if ($resultActive && $resultActive->num_rows > 0) { 
                                while ($row = $resultActive->fetch_assoc()) { 
                                    echo "<tr> 
                                            <td>" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "</td> 
                                            <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>  
                                            <td>" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "</td>
                                            <td>" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "</td>  
                                            <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                                            <td>";
                                    if ($userType !== 'technician') {
                                        echo "<a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                              <a class='edit-btn' href='BorrowE.php?id=" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "' title='Edit'><i class='fas fa-edit'></i></a>
                                              <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>";
                                    } else {
                                        echo "<a class='view-btn' onclick=\"showRestrictionMessage()\" title='View'><i class='fas fa-eye'></i></a>
                                              <a class='edit-btn' onclick=\"showRestrictionMessage()\" title='Edit'><i class='fas fa-edit'></i></a>
                                              <a class='archive-btn' onclick=\"showRestrictionMessage()\" title='Archive'><i class='fas fa-archive'></i></a>";
                                    }
                                    echo "</td></tr>"; 
                                } 
                            } else { 
                                echo "<tr><td colspan='6'>No active assets found.</td></tr>"; 
                            } 
                            ?>
                        </tbody>
                    </table>
                    <div class="pagination" id="active-pagination">
                        <?php if ($activePage > 1): ?>
                            <a href="?active_page=<?php echo $activePage - 1; ?>&archived_page=<?php echo $archivedPage; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>
                        <span class="current-page">Page <?php echo $activePage; ?> of <?php echo $totalActivePages; ?></span>
                        <?php if ($activePage < $totalActivePages): ?>
                            <a href="?active_page=<?php echo $activePage + 1; ?>&archived_page=<?php echo $archivedPage; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div id="assets-archive" class="tab-content" style="display: none;">
                    <table id="archived-assets-table">
                        <thead>
                            <tr>
                                <th>Asset ID</th>
                                <th>Asset Name</th>
                                <th>Asset Status</th>
                                <th>Asset Quantity</th>
                                <th>Date Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="archived-assets-table-body">
                            <?php 
                            if ($resultArchived && $resultArchived->num_rows > 0) { 
                                while ($row = $resultArchived->fetch_assoc()) { 
                                    echo "<tr> 
                                            <td>" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "</td> 
                                            <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>  
                                            <td>" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "</td>
                                            <td>" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "</td>  
                                            <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                                            <td>";
                                    if ($userType !== 'technician') {
                                        echo "<a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                              <a class='unarchive-btn' onclick=\"showUnarchiveModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                                              <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>";
                                    } else {
                                        echo "<a class='view-btn' onclick=\"showRestrictionMessage()\" title='View'><i class='fas fa-eye'></i></a>
                                              <a class='unarchive-btn' onclick=\"showRestrictionMessage()\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                                              <a class='delete-btn' onclick=\"showRestrictionMessage()\" title='Delete'><i class='fas fa-trash'></i></a>";
                                    }
                                    echo "</td></tr>"; 
                                } 
                            } else { 
                                echo "<tr><td colspan='6'>No archived assets found.</td></tr>"; 
                            } 
                            ?>
                        </tbody>
                    </table>
                    <div class="pagination" id="archived-pagination">
                        <?php if ($archivedPage > 1): ?>
                            <a href="?active_page=<?php echo $activePage; ?>&archived_page=<?php echo $archivedPage - 1; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>
                        <span class="current-page">Page <?php echo $archivedPage; ?> of <?php echo $totalArchivedPages; ?></span>
                        <?php if ($archivedPage < $totalArchivedPages): ?>
                            <a href="?active_page=<?php echo $activePage; ?>&archived_page=<?php echo $archivedPage + 1; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="borrowA.php" class="borrow-btn"><i class="fas fa-plus"></i> Borrow</a>
                <a href="deployA.php" class="deploy-btn"><i class="fas fa-cogs"></i> Deploy</a>
            </div>
        </div>

        <!-- Asset View Modal -->
        <div id="assetViewModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Asset Details</h2>
                </div>
                <div id="assetViewContent"></div>
                <div class="modal-footer">
                    <button class="modal-btn cancel" onclick="closeModal('assetViewModal')">Close</button>
                </div>
            </div>
        </div>

        <!-- Archive Confirmation Modal -->
        <div id="archiveModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Archive Asset</h2>
                </div>
                <p>Are you sure you want to archive "<span id="archiveAssetName"></span>"?</p>
                <form method="POST" id="archiveForm">
                    <input type="hidden" name="a_id" id="archiveAssetId">
                    <input type="hidden" name="archive_asset" value="1">
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('archiveModal')">Cancel</button>
                        <button type="submit" class="modal-btn confirm">Archive</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Unarchive Confirmation Modal -->
        <div id="unarchiveModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Unarchive Asset</h2>
                </div>
                <p>Are you sure you want to unarchive "<span id="unarchiveAssetName"></span>"?</p>
                <form method="POST" id="unarchiveForm">
                    <input type="hidden" name="a_id" id="unarchiveAssetId">
                    <input type="hidden" name="unarchive_asset" value="1">
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('unarchiveModal')">Cancel</button>
                        <button type="submit" class="modal-btn confirm">Unarchive</button>
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
                <p>Are you sure you want to permanently delete "<span id="deleteAssetName"></span>"? This action cannot be undone.</p>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="a_id" id="deleteAssetId">
                    <input type="hidden" name="delete_asset" value="1">
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('deleteModal')">Cancel</button>
                        <button type="submit" class="modal-btn confirm">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Show active assets by default
    showAssetTab('active');

    // Handle alert messages disappearing after 2 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('alert-hidden');
            setTimeout(() => alert.remove(), 500);
        }, 2000);
    });

    // Initialize search if there's a search term
    const searchInput = document.getElementById('searchInput');
    if (searchInput.value) {
        searchAssets();
    }
});

function showAssetTab(tab) {
    const activeContent = document.getElementById('assets-active');
    const archiveContent = document.getElementById('assets-archive');
    const buttons = document.querySelectorAll('.assets .tab-buttons .tab-btn');

    if (tab === 'active') {
        activeContent.style.display = 'block';
        archiveContent.style.display = 'none';
    } else {
        activeContent.style.display = 'none';
        archiveContent.style.display = 'block';
    }

    buttons.forEach(button => {
        button.classList.remove('active');
        if (button.getAttribute('onclick').includes(tab)) {
            button.classList.add('active');
        }
    });

    // Trigger search to refresh the table
    searchAssets();
}

function showRestrictionMessage() {
    alert("Only staff can add, view, edit, or archive assets.");
}

function showAssetViewModal(id, name, status, quantity, date) {
    document.getElementById('assetViewContent').innerHTML = `
        <div class="asset-details">
            <p><strong>Asset ID:</strong> ${id}</p>
            <p><strong>Asset Name:</strong> ${name}</p>
            <p><strong>Status:</strong> ${status}</p>
            <p><strong>Quantity:</strong> ${quantity}</p>
            <p><strong>Date Registered:</strong> ${date}</p>
        </div>
    `;
    document.getElementById('assetViewModal').style.display = 'block';
}

function showArchiveModal(id, name) {
    document.getElementById('archiveAssetName').textContent = name;
    document.getElementById('archiveAssetId').value = id;
    document.getElementById('archiveModal').style.display = 'block';
}

function showUnarchiveModal(id, name) {
    document.getElementById('unarchiveAssetName').textContent = name;
    document.getElementById('unarchiveAssetId').value = id;
    document.getElementById('unarchiveModal').style.display = 'block';
}

function showDeleteModal(id, name) {
    document.getElementById('deleteAssetName').textContent = name;
    document.getElementById('deleteAssetId').value = id;
    document.getElementById('deleteModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Default page numbers for pagination
const defaultActivePage = <?php echo $activePage; ?>;
const defaultArchivedPage = <?php echo $archivedPage; ?>;
let currentSearchPage = 1;

function searchAssets(page = 1) {
    const searchTerm = document.getElementById('searchInput').value;
    const activeTab = document.getElementById('assets-active').style.display !== 'none';
    const tab = activeTab ? 'active' : 'archive';
    const tbody = document.getElementById(activeTab ? 'assets-table-body' : 'archived-assets-table-body');
    const paginationContainer = document.getElementById(activeTab ? 'active-pagination' : 'archived-pagination');
    const defaultPageToUse = activeTab ? defaultActivePage : defaultArchivedPage;

    currentSearchPage = page;

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            tbody.innerHTML = xhr.responseText.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
            const scripts = xhr.responseText.match(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi);
            if (scripts) {
                scripts.forEach(script => {
                    const scriptContent = script.replace(/<\/?script>/g, '');
                    eval(scriptContent);
                });
            }
        }
    };
    xhr.open('GET', `assetsT.php?action=search&tab=${tab}&search=${encodeURIComponent(searchTerm)}&search_page=${searchTerm ? page : defaultPageToUse}`, true);
    xhr.send();
}

function updatePagination(currentPage, totalPages, tab, searchTerm, paginationId) {
    const paginationContainer = document.getElementById(paginationId);
    let paginationHtml = '';

    if (currentPage > 1) {
        paginationHtml += `<a href="javascript:searchAssets(${currentPage - 1})" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="javascript:searchAssets(${currentPage + 1})" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

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

const debouncedSearchAssets = debounce(searchAssets, 300);
</script>
</body>
</html>

<?php $conn->close(); ?>
