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

// Fetch unique asset names and statuses
$uniqueAssetNames = [];
$uniqueAssetStatuses = [];

if ($conn) {
    $sqlNames = "SELECT DISTINCT a_name FROM tbl_assets ORDER BY a_name";
    $resultNames = $conn->query($sqlNames);
    if ($resultNames) {
        while ($row = $resultNames->fetch_assoc()) {
            $uniqueAssetNames[] = $row['a_name'];
        }
    }

    $sqlStatuses = "SELECT DISTINCT a_status FROM tbl_assets WHERE a_status != 'Archived' ORDER BY a_status";
    $resultStatuses = $conn->query($sqlStatuses);
    if ($resultStatuses) {
        while ($row = $resultStatuses->fetch_assoc()) {
            $uniqueAssetStatuses[] = $row['a_status'];
        }
    }
}

// Handle add/edit/borrow/deploy asset requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hasError = false;

    // Add Asset
    if (isset($_POST['add_asset'])) {
        $assetname = trim($_POST['asset_name'] ?? '');
        $assetstatus = trim($_POST['asset_status'] ?? '');
        $assetquantity = trim($_POST['asset_quantity'] ?? '');
        $assetdate = trim($_POST['date'] ?? '');
        $assetnameErr = $assetstatusErr = $assetquantityErr = $assetdateErr = "";

        if (!preg_match("/^[a-zA-Z\s-]+$/", $assetname)) {
            $assetnameErr = "Asset Name should not contain numbers.";
            $hasError = true;
        }
        if (empty($assetnameErr)) {
            $sqlCheck = "SELECT a_id FROM tbl_assets WHERE a_name = ?";
            $stmtCheck = $conn->prepare($sqlCheck);
            if ($stmtCheck) {
                $stmtCheck->bind_param("s", $assetname);
                $stmtCheck->execute();
                $resultCheck = $stmtCheck->get_result();
                if ($resultCheck->num_rows > 0) {
                    $assetnameErr = "Asset name already exists.";
                    $hasError = true;
                }
                $stmtCheck->close();
            } else {
                $assetnameErr = "Database error while checking asset name.";
                $hasError = true;
            }
        }
        if (!in_array($assetstatus, ['Borrowing', 'Deployment'])) {
            $assetstatusErr = "Invalid asset status.";
            $hasError = true;
        }
        if (!is_numeric($assetquantity) || $assetquantity < 0) {
            $assetquantityErr = "Quantity must be a non-negative number.";
            $hasError = true;
        }
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $assetdate)) {
            $assetdateErr = "Invalid date format.";
            $hasError = true;
        }

        if (!$hasError) {
            $sql = "INSERT INTO tbl_assets (a_name, a_status, a_quantity, a_date) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssis", $assetname, $assetstatus, $assetquantity, $assetdate);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Asset registered successfully.";
                    $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
                    $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
                    header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
                    exit();
                } else {
                    $_SESSION['error'] = "Failed to register asset.";
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "Database error.";
            }
        } else {
            $_SESSION['error'] = implode(" ", array_filter([$assetnameErr, $assetstatusErr, $assetquantityErr, $assetdateErr]));
        }
        $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
        $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
        header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
        exit();
    }

    // Edit Asset
    if (isset($_POST['edit_asset'])) {
        $assetId = (int)$_POST['a_id'];
        $assetname = trim($_POST['asset_name'] ?? '');
        $assetstatus = trim($_POST['asset_status'] ?? '');
        $assetquantity = trim($_POST['asset_quantity'] ?? '');
        $assetdate = trim($_POST['date'] ?? '');
        $assetnameErr = $assetstatusErr = $assetquantityErr = $assetdateErr = "";

        if (!preg_match("/^[a-zA-Z\s-]+$/", $assetname)) {
            $assetnameErr = "Asset Name should not contain numbers.";
            $hasError = true;
        }
        if (!in_array($assetstatus, ['Borrowing', 'Deployment', 'Archived'])) {
            $assetstatusErr = "Invalid asset status.";
            $hasError = true;
        }
        if (!is_numeric($assetquantity) || $assetquantity < 0) {
            $assetquantityErr = "Quantity must be a non-negative number.";
            $hasError = true;
        }
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $assetdate)) {
            $assetdateErr = "Invalid date format.";
            $hasError = true;
        }

        if (!$hasError) {
            $sql = "UPDATE tbl_assets SET a_name = ?, a_status = ?, a_quantity = ?, a_date = ? WHERE a_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssisi", $assetname, $assetstatus, $assetquantity, $assetdate, $assetId);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Asset updated successfully.";
                    $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
                    $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
                    header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
                    exit();
                } else {
                    $_SESSION['error'] = "Failed to update asset.";
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "Database error.";
            }
        } else {
            $_SESSION['error'] = implode(" ", array_filter([$assetnameErr, $assetstatusErr, $assetquantityErr, $assetdateErr]));
        }
        $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
        $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
        header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
        exit();
    }

    // Borrow Asset
    if (isset($_POST['borrow_asset'])) {
        $errors = [];
        $asset_id = trim($_POST['asset_id'] ?? '');
        $borrowquantity = trim($_POST['borrow_quantity'] ?? '');
        $borrow_techname = trim($_POST['tech_name'] ?? '');
        $borrow_techid = trim($_POST['tech_id'] ?? '');
        $borrowdate = trim($_POST['date'] ?? '');

        if (empty($asset_id)) {
            $errors[] = "Please select an asset.";
        }
        if (empty($borrowquantity) || !is_numeric($borrowquantity) || $borrowquantity <= 0) {
            $errors[] = "Please enter a valid quantity (greater than 0).";
        }
        if (empty($borrow_techname) || !preg_match("/^[a-zA-Z\s-]+$/", $borrow_techname)) {
            $errors[] = "Technician name is required and should not contain numbers.";
        }
        if (empty($borrow_techid)) {
            $errors[] = "Technician ID is required.";
        }
        if (empty($borrowdate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $borrowdate)) {
            $errors[] = "Borrow date is required and must be valid.";
        }

        if (empty($errors)) {
            $sqlCheckTechnician = "SELECT u_fname, u_lname FROM tbl_user WHERE u_id = ?";
            $stmtCheckTechnician = $conn->prepare($sqlCheckTechnician);
            $stmtCheckTechnician->bind_param("s", $borrow_techid);
            $stmtCheckTechnician->execute();
            $resultCheckTechnician = $stmtCheckTechnician->get_result();

            if ($resultCheckTechnician->num_rows > 0) {
                $row = $resultCheckTechnician->fetch_assoc();
                $fullName = trim($row['u_fname'] . ' ' . $row['u_lname']);
                if (strcasecmp($fullName, $borrow_techname) !== 0) {
                    $errors[] = "Technician name does not match the ID.";
                }
            } else {
                $errors[] = "Technician ID does not exist.";
            }
            $stmtCheckTechnician->close();
        }

        if (empty($errors)) {
            $sqlCheckBorrowed = "SELECT SUM(b_quantity) AS total_borrowed FROM tbl_borrowed WHERE b_technician_id = ?";
            $stmtCheckBorrowed = $conn->prepare($sqlCheckBorrowed);
            $stmtCheckBorrowed->bind_param("s", $borrow_techid);
            $stmtCheckBorrowed->execute();
            $resultCheckBorrowed = $stmtCheckBorrowed->get_result();
            $row = $resultCheckBorrowed->fetch_assoc();

            if ($row['total_borrowed'] > 0) {
                $errors[] = "Technician must return borrowed assets before borrowing again.";
            }
            $stmtCheckBorrowed->close();
        }

        if (empty($errors)) {
            $sqlCheckAsset = "SELECT a_name, a_quantity FROM tbl_assets WHERE a_id = ? AND a_status != 'Archived'";
            $stmtCheckAsset = $conn->prepare($sqlCheckAsset);
            $stmtCheckAsset->bind_param("i", $asset_id);
            $stmtCheckAsset->execute();
            $resultCheckAsset = $stmtCheckAsset->get_result();

            if ($resultCheckAsset->num_rows > 0) {
                $row = $resultCheckAsset->fetch_assoc();
                $borrow_assetsname = $row['a_name'];
                $availableQuantity = $row['a_quantity'];

                if ($borrowquantity > $availableQuantity) {
                    $errors[] = "Requested quantity ($borrowquantity) exceeds available stock ($availableQuantity).";
                }
            } else {
                $errors[] = "Selected asset is not available.";
            }
            $stmtCheckAsset->close();
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $sqlInsertBorrowed = "INSERT INTO tbl_borrowed (b_assets_name, b_quantity, b_technician_name, b_technician_id, b_date) 
                                      VALUES (?, ?, ?, ?, ?)";
                $stmtInsertBorrowed = $conn->prepare($sqlInsertBorrowed);
                $stmtInsertBorrowed->bind_param("sisis", $borrow_assetsname, $borrowquantity, $borrow_techname, $borrow_techid, $borrowdate);
                $stmtInsertBorrowed->execute();
                $stmtInsertBorrowed->close();

                $sqlInsertTechBorrowed = "INSERT INTO tbl_techborrowed (b_assets_name, b_quantity, b_technician_name, b_technician_id, b_date) 
                                          VALUES (?, ?, ?, ?, ?)";
                $stmtInsertTechBorrowed = $conn->prepare($sqlInsertTechBorrowed);
                $stmtInsertTechBorrowed->bind_param("sisis", $borrow_assetsname, $borrowquantity, $borrow_techname, $borrow_techid, $borrowdate);
                $stmtInsertTechBorrowed->execute();
                $stmtInsertTechBorrowed->close();

                $newQuantity = $availableQuantity - $borrowquantity;
                $sqlUpdate = "UPDATE tbl_assets SET a_quantity = ? WHERE a_id = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bind_param("ii", $newQuantity, $asset_id);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                $conn->commit();
                $_SESSION['message'] = "Asset borrowed successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Error borrowing asset: " . $e->getMessage();
                error_log("Borrow error: " . $e->getMessage());
            }
        } else {
            $_SESSION['error'] = implode(" ", $errors);
        }
        $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
        $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
        header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
        exit();
    }

    // Deploy Asset
    if (isset($_POST['deploy_asset'])) {
        $errors = [];
        $asset_id = trim($_POST['asset_id'] ?? '');
        $borrowquantity = trim($_POST['borrow_quantity'] ?? '');
        $borrow_techname = trim($_POST['tech_name'] ?? '');
        $borrow_techid = trim($_POST['tech_id'] ?? '');
        $borrowdate = trim($_POST['date'] ?? '');

        if (empty($asset_id)) {
            $errors[] = "Please select an asset.";
        }
        if (empty($borrowquantity) || !is_numeric($borrowquantity) || $borrowquantity <= 0) {
            $errors[] = "Please enter a valid quantity (greater than 0).";
        }
        if (empty($borrow_techname) || !preg_match("/^[a-zA-Z\s-]+$/", $borrow_techname)) {
            $errors[] = "Technician name is required and should not contain numbers.";
        }
        if (empty($borrow_techid)) {
            $errors[] = "Technician ID is required.";
        }
        if (empty($borrowdate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $borrowdate)) {
            $errors[] = "Deployment date is required and must be valid.";
        }

        if (empty($errors)) {
            $sqlCheckTechnician = "SELECT u_fname, u_lname FROM tbl_user WHERE u_id = ?";
            $stmtCheckTechnician = $conn->prepare($sqlCheckTechnician);
            $stmtCheckTechnician->bind_param("s", $borrow_techid);
            $stmtCheckTechnician->execute();
            $resultCheckTechnician = $stmtCheckTechnician->get_result();

            if ($resultCheckTechnician->num_rows > 0) {
                $row = $resultCheckTechnician->fetch_assoc();
                $fullName = trim($row['u_fname'] . ' ' . $row['u_lname']);
                if (strcasecmp($fullName, $borrow_techname) !== 0) {
                    $errors[] = "Technician name does not match the ID.";
                }
            } else {
                $errors[] = "Technician ID does not exist.";
            }
            $stmtCheckTechnician->close();
        }

        if (empty($errors)) {
            $sqlCheckAsset = "SELECT a_name, a_quantity FROM tbl_assets WHERE a_id = ? AND a_status = 'Deployment'";
            $stmtCheckAsset = $conn->prepare($sqlCheckAsset);
            $stmtCheckAsset->bind_param("i", $asset_id);
            $stmtCheckAsset->execute();
            $resultCheckAsset = $stmtCheckAsset->get_result();

            if ($resultCheckAsset->num_rows > 0) {
                $row = $resultCheckAsset->fetch_assoc();
                $borrow_assetsname = $row['a_name'];
                $availableQuantity = $row['a_quantity'];

                if ($borrowquantity > $availableQuantity) {
                    $errors[] = "Requested quantity ($borrowquantity) exceeds available stock ($availableQuantity).";
                }
            } else {
                $errors[] = "Selected asset is not available or not a deployment asset.";
            }
            $stmtCheckAsset->close();
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $sqlInsert = "INSERT INTO tbl_deployed (d_assets_name, d_quantity, d_technician_name, d_technician_id, d_date) VALUES (?, ?, ?, ?, ?)";
                $stmtInsert = $conn->prepare($sqlInsert);
                $stmtInsert->bind_param("sisis", $borrow_assetsname, $borrowquantity, $borrow_techname, $borrow_techid, $borrowdate);
                $stmtInsert->execute();
                $stmtInsert->close();

                $newQuantity = $availableQuantity - $borrowquantity;
                $sqlUpdate = "UPDATE tbl_assets SET a_quantity = ? WHERE a_id = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bind_param("ii", $newQuantity, $asset_id);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                $conn->commit();
                $_SESSION['message'] = "Asset deployed successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Error deploying asset: " . $e->getMessage();
                error_log("Deploy error: " . $e->getMessage());
            }
        } else {
            $_SESSION['error'] = implode(" ", $errors);
        }
        $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
        $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
        header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
        exit();
    }

    // Handle archive/unarchive/delete requests
    if (isset($_POST['archive_asset'])) {
        $assetId = (int)$_POST['a_id'];
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
        $assetId = (int)$_POST['a_id'];
        $sql = "UPDATE tbl_assets SET a_status = 'Borrowing' WHERE a_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $assetId);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Asset unarchived successfully!";
        } else {
            $_SESSION['error'] = "Error unarchiving asset.";
        }
        $stmt->close();
    } elseif (isset($_POST['delete_asset'])) {
        $assetId = (int)$_POST['a_id'];
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
}

// Handle AJAX search request with filters
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['tab'])) {
    $searchTerm = trim($_GET['search'] ?? '');
    $filterName = trim($_GET['filter_name'] ?? '');
    $filterStatus = trim($_GET['filter_status'] ?? '');
    $tab = $_GET['tab'] === 'archive' ? 'archive' : 'active';
    $page = isset($_GET['search_page']) ? (int)$_GET['search_page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $output = '';

    $statusCondition = $tab === 'active' ? "a_status != 'Archived'" : "a_status = 'Archived'";
    $params = [];
    $types = '';
    $whereClauses = [$statusCondition];

    if ($searchTerm !== '') {
        $whereClauses[] = "(a_name LIKE ? OR a_status LIKE ? OR a_quantity LIKE ? OR a_date LIKE ?)";
        $searchWildcard = "%$searchTerm%";
        $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
        $types .= 'ssss';
    }

    if ($filterName !== '') {
        $whereClauses[] = "a_name = ?";
        $params[] = $filterName;
        $types .= 's';
    }

    if ($filterStatus !== '') {
        $whereClauses[] = "a_status = ?";
        $params[] = $filterStatus;
        $types .= 's';
    }

    $whereClause = implode(' AND ', $whereClauses);

    $countSql = "SELECT COUNT(*) as total FROM tbl_assets WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = ceil($totalRecords / $limit);

    $sql = "SELECT a_id, a_name, a_status, a_quantity, a_date 
            FROM tbl_assets 
            WHERE $whereClause 
            ORDER BY a_id ASC 
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $params[] = $offset;
    $params[] = $limit;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
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
            if ($tab === 'active') {
                $output .= "<a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                            <a class='edit-btn' onclick=\"showEditAssetModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                            <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>";
            } else {
                $output .= "<a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                            <a class='unarchive-btn' onclick=\"showUnarchiveModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                            <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>";
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

// Handle AJAX export data request
if (isset($_GET['action']) && $_GET['action'] === 'export_data' && isset($_GET['tab'])) {
    $searchTerm = trim($_GET['search'] ?? '');
    $filterName = trim($_GET['filter_name'] ?? '');
    $filterStatus = trim($_GET['filter_status'] ?? '');
    $tab = $_GET['tab'] === 'archive' ? 'archive' : 'active';

    $statusCondition = $tab === 'active' ? "a_status != 'Archived'" : "a_status = 'Archived'";
    $params = [];
    $types = '';
    $whereClauses = [$statusCondition];

    if ($searchTerm !== '') {
        $whereClauses[] = "(a_name LIKE ? OR a_status LIKE ? OR a_quantity LIKE ? OR a_date LIKE ?)";
        $searchWildcard = "%$searchTerm%";
        $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
        $types .= 'ssss';
    }

    if ($filterName !== '') {
        $whereClauses[] = "a_name = ?";
        $params[] = $filterName;
        $types .= 's';
    }

    if ($filterStatus !== '') {
        $whereClauses[] = "a_status = ?";
        $params[] = $filterStatus;
        $types .= 's';
    }

    $whereClause = implode(' AND ', $whereClauses);

    // Fetch all assets for export
    $sql = "SELECT a_id, a_name, a_status, a_quantity, a_date 
            FROM tbl_assets 
            WHERE $whereClause 
            ORDER BY a_id ASC";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $assets = [];
    while ($row = $result->fetch_assoc()) {
        $assets[] = [
            'Asset ID' => $row['a_id'],
            'Asset Name' => $row['a_name'],
            'Status' => $row['a_status'],
            'Quantity' => $row['a_quantity'],
            'Date Registered' => $row['a_date'],
        ];
    }
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['data' => $assets]);
    exit();
}

// Pagination settings
$limit = 10;

// Active Assets Pagination
$activePage = isset($_GET['active_page']) ? (int)$_GET['active_page'] : 1;
$activeOffset = ($activePage - 1) * $limit;

// Archived Assets Pagination
$archivedPage = isset($_GET['archived_page']) ? (int)$_GET['archived_page'] : 1;
$archivedOffset = ($archivedPage - 1) * $limit;

// Fetch available assets for borrow modal
$sqlAssets = "SELECT a_id, a_name, a_quantity FROM tbl_assets WHERE a_status != 'Archived' AND a_quantity > 0";
$resultAssets = $conn->query($sqlAssets);

// Fetch available deployment assets for deploy modal
$sqlDeployAssets = "SELECT a_id, a_name, a_quantity FROM tbl_assets WHERE a_status = 'Deployment' AND a_quantity > 0";
$resultDeployAssets = $conn->query($sqlDeployAssets);

if ($conn) {
    $activeCountQuery = "SELECT COUNT(*) as total FROM tbl_assets WHERE a_status != 'Archived'";
    $activeCountResult = $conn->query($activeCountQuery);
    $totalActive = $activeCountResult ? $activeCountResult->fetch_assoc()['total'] : 0;
    $totalActivePages = ceil($totalActive / $limit);

    $sqlActive = "SELECT a_id, a_name, a_status, a_quantity, a_date FROM tbl_assets WHERE a_status != 'Archived' ORDER BY a_id ASC LIMIT ?, ?";
    $stmtActive = $conn->prepare($sqlActive);
    $stmtActive->bind_param("ii", $activeOffset, $limit);
    $stmtActive->execute();
    $resultActive = $stmtActive->get_result();
    $stmtActive->close();

    $archivedCountQuery = "SELECT COUNT(*) as total FROM tbl_assets WHERE a_status = 'Archived'";
    $archivedCountResult = $conn->query($archivedCountQuery);
    $totalArchived = $archivedCountResult ? $archivedCountResult->fetch_assoc()['total'] : 0;
    $totalArchivedPages = ceil($totalArchived / $limit);

    $sqlArchived = "SELECT a_id, a_name, a_status, a_quantity, a_date FROM tbl_assets WHERE a_status = 'Archived' ORDER BY a_id ASC LIMIT ?, ?";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

    <style>
        .filter-btn {
            background: transparent !important;
            border: none;
            cursor: pointer;
            font-size: 15px;
            color: #f5f8fc;
            margin-left: 5px;
            vertical-align: middle;
            padding: 0;
            outline: none;
        }
        .filter-btn:hover {
            color: hsl(211, 45.70%, 84.10%);
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
            <li><a href="staffD.php"><img src="image/ticket.png" alt="Regular Tickets" class="icon" /> <span>Regular Tickets</span></a></li>
            <li><a href="assetsT.php" class="active"><img src="image/assets.png" alt="Assets" class="icon" /> <span>Assets</span></a></li>
            <li><a href="AllCustomersT.php"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers Ticket</span></a></li>
            <li><a href="customersT.php"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers</span></a></li>
            <li><a href="borrowedStaff.php"><img src="image/borrowed.png" alt="Borrowed Assets" class="icon" /> <span>Borrowed Assets</span></a></li>
            <li><a href="addC.php"><img src="image/add.png" alt="Add Customer" class="icon" /> <span>Add Customer</span></a></li>
             <li><a href="AssignTech.php"><img src="image/technician.png" alt="Technicians" class="icon" /> <span>Technicians</span></a></li>
            <li><a href="Payments.php"><img src="image/transactions.png" alt="Payment Transactions" class="icon" /> <span>Payment Transactions</span></a></li>
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
                    <div class="button-group">
                        <a href="#" class="borrow-btn" onclick="showBorrowAssetModal()"><i class="fas fa-plus"></i> Borrow</a>
                        <a href="#" class="deploy-btn" onclick="showDeployAssetModal()"><i class="fas fa-cogs"></i> Deploy</a>
                        <a href="#" class="add-btn" onclick="showAddAssetModal()"><i class="fas fa-plus"></i> Add Asset</a>
                        <div class="export-container">
                            <button class="export-btn"><i class="fas fa-download"></i> Export</button>
                            <div class="export-dropdown">
                                <button onclick="exportTable('excel')">Excel</button>
                                <button onclick="exportTable('csv')">CSV</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="assets-active" class="tab-content">
                    <table id="assets-table">
                        <thead>
                            <tr>
                                <th>Asset ID</th>
                                <th>
                                    Asset Name
                                    <button class="filter-btn" onclick="showAssetNameFilterModal('active')" title="Filter by Asset Name">
                                        <i class='bx bx-filter'></i>
                                    </button>
                                </th>
                                <th>
                                    Asset Status
                                    <button class="filter-btn" onclick="showAssetStatusFilterModal('active')" title="Filter by Asset Status">
                                        <i class='bx bx-filter'></i>
                                    </button>
                                </th>
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
                                            <td>
                                                <a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                                <a class='edit-btn' onclick=\"showEditAssetModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                                                <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>
                                            </td></tr>"; 
                                } 
                            } else { 
                                echo "<tr><td colspan='6'>No active assets found.</td></tr>"; 
                            } 
                            ?>
                        </tbody>
                    </table>
                    <div class="pagination" id="active-pagination">
                        <?php if ($activePage > 1): ?>
                            <a href="javascript:searchAssets(<?php echo $activePage - 1; ?>, 'active')" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>
                        <span class="current-page">Page <?php echo $activePage; ?> of <?php echo $totalActivePages; ?></span>
                        <?php if ($activePage < $totalActivePages): ?>
                            <a href="javascript:searchAssets(<?php echo $activePage + 1; ?>, 'active')" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
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
                                <th>
                                    Asset Name
                                    <button class="filter-btn" onclick="showAssetNameFilterModal('archive')" title="Filter by Asset Name">
                                        <i class='bx bx-filter'></i>
                                    </button>
                                </th>
                                <th>
                                    Asset Status
                                    <button class="filter-btn" onclick="showAssetStatusFilterModal('archive')" title="Filter by Asset Status">
                                        <i class='bx bx-filter'></i>
                                    </button>
                                </th>
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
                                            <td>
                                                <a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                                <a class='unarchive-btn' onclick=\"showUnarchiveModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                                                <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                                            </td></tr>"; 
                                } 
                            } else { 
                                echo "<tr><td colspan='6'>No archived assets found.</td></tr>"; 
                            } 
                            ?>
                        </tbody>
                    </table>
                    <div class="pagination" id="archived-pagination">
                        <?php if ($archivedPage > 1): ?>
                            <a href="javascript:searchAssets(<?php echo $archivedPage - 1; ?>, 'archive')" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>
                        <span class="current-page">Page <?php echo $archivedPage; ?> of <?php echo $totalArchivedPages; ?></span>
                        <?php if ($archivedPage < $totalArchivedPages): ?>
                            <a href="javascript:searchAssets(<?php echo $archivedPage + 1; ?>, 'archive')" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                            <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
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

        <!-- Add Asset Modal -->
        <div id="addAssetModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add New Asset</h2>
                </div>
                <form method="POST" id="addAssetForm" class="modal-form">
                    <input type="hidden" name="add_asset" value="1">
                    <div class="form-group">
                        <label for="asset_name">Asset Name:</label>
                        <input type="text" id="asset_name" name="asset_name" placeholder="Asset Name" required>
                        <span class="error" id="add_asset_name_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="asset_quantity">Asset Quantity to Register:</label>
                        <input type="text" id="asset_quantity" name="asset_quantity" placeholder="Asset quantity" required>
                        <span class="error" id="add_asset_quantity_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="asset_status">Asset Status:</label>
                        <select id="asset_status" name="asset_status" required>
                            <option value="Borrowing">Borrowing</option>
                            <option value="Deployment">Deployment</option>
                        </select>
                        <span class="error" id="add_asset_status_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="date">Date Registered:</label>
                        <input type="date" id="date" name="date" required>
                        <span class="error" id="add_date_error"></span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('addAssetModal')">Cancel</button>
                        <button type="submit" class="modal-btn confirm">Add Asset</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Asset Modal -->
        <div id="editAssetModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Asset</h2>
                </div>
                <form method="POST" id="editAssetForm" class="modal-form">
                    <input type="hidden" name="edit_asset" value="1">
                    <input type="hidden" name="a_id" id="edit_a_id">
                    <div class="form-group">
                        <label for="edit_asset_name">Asset Name:</label>
                        <input type="text" id="edit_asset_name" name="asset_name" required>
                        <span class="error" id="edit_asset_name_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="edit_asset_quantity">Quantity:</label>
                        <input type="number" id="edit_asset_quantity" name="asset_quantity" required>
                        <span class="error" id="edit_asset_quantity_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="edit_asset_status">Status:</label>
                        <select id="edit_asset_status" name="asset_status" required>
                            <option value="Borrowing">Borrowing</option>
                            <option value="Deployment">Deployment</option>
                        </select>
                        <span class="error" id="edit_asset_status_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="edit_date">Date Registered:</label>
                        <input type="date" id="edit_date" name="date" required>
                        <span class="error" id="edit_date_error"></span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('editAssetModal')">Cancel</button>
                        <button type="submit" class="modal-btn confirm">Update</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Borrow Asset Modal -->
        <div id="borrowAssetModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Borrow Asset</h2>
                </div>
                <form method="POST" id="borrowAssetForm" class="modal-form">
                    <input type="hidden" name="borrow_asset" value="1">
                    <div class="form-group">
                        <label for="asset_id">Asset Name</label>
                        <select id="asset_id" name="asset_id" required>
                            <option value="">Select Asset</option>
                            <?php 
                            $resultAssets->data_seek(0);
                            while ($row = $resultAssets->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . " (Available: " . $row['a_quantity'] . ")"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <span class="error" id="borrow_asset_id_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="borrow_quantity">Quantity to Borrow</label>
                        <input type="text" id="borrow_quantity" name="borrow_quantity" placeholder="Quantity" required>
                        <span class="error" id="borrow_borrow_quantity_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="tech_name">Technician Name</label>
                        <input type="text" id="tech_name" name="tech_name" placeholder="Technician Name" required>
                        <span class="error" id="borrow_tech_name_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="tech_id">Technician ID</label>
                        <input type="text" id="tech_id" name="tech_id" placeholder="Technician ID" required>
                        <span class="error" id="borrow_tech_id_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="date">Date Borrowed</label>
                        <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        <span class="error" id="borrow_date_error"></span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('borrowAssetModal')">Cancel</button>
                        <button type="submit" class="modal-btn confirm">Borrow Asset</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Deploy Asset Modal -->
        <div id="deployAssetModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Deploy Asset</h2>
                </div>
                <form method="POST" id="deployAssetForm" class="modal-form">
                    <input type="hidden" name="deploy_asset" value="1">
                    <div class="form-group">
                        <label for="asset_id">Asset Name</label>
                        <select id="asset_id" name="asset_id" required>
                            <option value="">Select Asset</option>
                            <?php 
                            if ($resultDeployAssets && $resultDeployAssets->num_rows > 0) {
                                $resultDeployAssets->data_seek(0);
                                while ($row = $resultDeployAssets->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . " (Available: " . $row['a_quantity'] . ")"; ?>
                                    </option>
                                <?php endwhile; 
                            } else { ?>
                                <option value="" disabled>No deployment assets available</option>
                            <?php } ?>
                        </select>
                        <span class="error" id="deploy_asset_id_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="borrow_quantity">Quantity to Deploy</label>
                        <input type="text" id="borrow_quantity" name="borrow_quantity" placeholder="Quantity" required>
                        <span class="error" id="deploy_borrow_quantity_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="tech_name">Technician Name</label>
                        <input type="text" id="tech_name" name="tech_name" placeholder="Technician Name" required>
                        <span class="error" id="deploy_tech_name_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="tech_id">Technician ID</label>
                        <input type="text" id="tech_id" name="tech_id" placeholder="Technician ID" required>
                        <span class="error" id="deploy_tech_id_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="date">Date for Deployment</label>
                        <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        <span class="error" id="deploy_date_error"></span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('deployAssetModal')">Cancel</button>
                        <button type="submit" class="modal-btn confirm">Deploy Asset</button>
                    </div>
                </form>
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

        <!-- Asset Name Filter Modal -->
        <div id="assetNameFilterModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Filter by Asset Name</h2>
                </div>
                <form id="assetNameFilterForm" class="modal-form">
                    <input type="hidden" name="tab" id="assetNameFilterTab">
                    <label for="asset_name_filter">Select Asset Name</label>
                    <select name="asset_name_filter" id="asset_name_filter">
                        <option value="">All Assets</option>
                        <?php foreach ($uniqueAssetNames as $name): ?>
                            <option value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('assetNameFilterModal')">Cancel</button>
                        <button type="button" class="modal-btn confirm" onclick="applyAssetNameFilter()">Apply Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Asset Status Filter Modal -->
        <div id="assetStatusFilterModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Filter by Asset Status</h2>
                </div>
                <form id="assetStatusFilterForm" class="modal-form">
                    <input type="hidden" name="tab" id="assetStatusFilterTab">
                    <label for="asset_status_filter">Select Asset Status</label>
                    <select name="asset_status_filter" id="asset_status_filter">
                        <option value="">All Statuses</option>
                        <?php foreach ($uniqueAssetStatuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('assetStatusFilterModal')">Cancel</button>
                        <button type="button" class="modal-btn confirm" onclick="applyAssetStatusFilter()">Apply Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    showAssetTab('active');
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('alert-hidden');
            setTimeout(() => alert.remove(), 500);
        }, 2000);
    });
    const searchInput = document.getElementById('searchInput');
    if (searchInput.value) {
        searchAssets(1);
    }
});

function showAssetTab(tab) {
    const activeContent = document.getElementById('assets-active');
    const archiveContent = document.getElementById('assets-archive');
    const buttons = document.querySelectorAll('.tab-buttons .tab-btn');

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
    searchAssets(1, tab);
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

function showAddAssetModal() {
    document.getElementById('addAssetForm').reset();
    document.querySelectorAll('#addAssetForm .error').forEach(el => el.textContent = '');
    document.getElementById('addAssetModal').style.display = 'block';
}

function showEditAssetModal(id, name, status, quantity, date) {
    document.getElementById('edit_a_id').value = id;
    document.getElementById('edit_asset_name').value = name;
    document.getElementById('edit_asset_quantity').value = quantity;
    document.getElementById('edit_date').value = date;
    const statusSelect = document.getElementById('edit_asset_status');
    const validStatuses = ['Borrowing', 'Deployment', 'Archived'];
    statusSelect.value = validStatuses.includes(status) ? status : 'Borrowing';
    document.querySelectorAll('#editAssetForm .error').forEach(el => el.textContent = '');
    document.getElementById('editAssetModal').style.display = 'block';
}

function showBorrowAssetModal() {
    document.getElementById('borrowAssetForm').reset();
    document.querySelectorAll('#borrowAssetForm .error').forEach(el => el.textContent = '');
    document.getElementById('borrowAssetForm').querySelector('#date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('borrowAssetModal').style.display = 'block';
}

function showDeployAssetModal() {
    document.getElementById('deployAssetForm').reset();
    document.querySelectorAll('#deployAssetForm .error').forEach(el => el.textContent = '');
    document.getElementById('deployAssetForm').querySelector('#date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('deployAssetModal').style.display = 'block';
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

function showAssetNameFilterModal(tab) {
    document.getElementById('assetNameFilterTab').value = tab;
    document.getElementById('assetNameFilterModal').style.display = 'block';
}

function showAssetStatusFilterModal(tab) {
    document.getElementById('assetStatusFilterTab').value = tab;
    document.getElementById('assetStatusFilterModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function applyAssetNameFilter() {
    const tab = document.getElementById('assetNameFilterTab').value;
    const nameSelect = document.getElementById('asset_name_filter');
    const selectedName = nameSelect.value;
    applyFilter(tab, selectedName, null);
    closeModal('assetNameFilterModal');
}

function applyAssetStatusFilter() {
    const tab = document.getElementById('assetStatusFilterTab').value;
    const statusSelect = document.getElementById('asset_status_filter');
    const selectedStatus = statusSelect.value;
    applyFilter(tab, null, selectedStatus);
    closeModal('assetStatusFilterModal');
}

function applyFilter(tab, selectedName = null, selectedStatus = null) {
    const currentName = selectedName !== null ? selectedName : (window.currentFilters?.name || '');
    const currentStatus = selectedStatus !== null ? selectedStatus : (window.currentFilters?.status || '');
    window.currentFilters = { name: currentName, status: currentStatus };
    searchAssets(1, tab, currentName, currentStatus);
}

function searchAssets(page = 1, tab = 'active', filterName = '', filterStatus = '') {
    const searchTerm = document.getElementById('searchInput').value;
    const tbody = document.getElementById(tab === 'active' ? 'assets-table-body' : 'archived-assets-table-body');

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
    const url = `assetsT.php?action=search&tab=${tab}&search=${encodeURIComponent(searchTerm)}&search_page=${page}` +
                `&filter_name=${encodeURIComponent(filterName)}&filter_status=${encodeURIComponent(filterStatus)}`;
    xhr.open('GET', url, true);
    xhr.send();
}

function updatePagination(currentPage, totalPages, tab, searchTerm, paginationId) {
    const paginationContainer = document.getElementById(paginationId);
    let paginationHtml = '';

    if (currentPage > 1) {
        paginationHtml += `<a href="javascript:searchAssets(${currentPage - 1}, '${tab}')" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="javascript:searchAssets(${currentPage + 1}, '${tab}')" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

function exportTable(format) {
    const searchTerm = document.getElementById('searchInput').value;
    const filterName = window.currentFilters?.name || '';
    const filterStatus = window.currentFilters?.status || '';
    const tab = document.getElementById('assets-active').style.display !== 'none' ? 'active' : 'archive';

    const params = new URLSearchParams();
    params.append('action', 'export_data');
    params.append('tab', tab);
    if (searchTerm) params.append('search', searchTerm);
    if (filterName) params.append('filter_name', filterName);
    if (filterStatus) params.append('filter_status', filterStatus);

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
                        XLSX.utils.book_append_sheet(wb, ws, tab === 'active' ? 'Active Assets' : 'Archived Assets');
                        XLSX.writeFile(wb, `${tab}_assets.xlsx`);
                    } else if (format === 'csv') {
                        const ws = XLSX.utils.json_to_sheet(data);
                        const csv = XLSX.utils.sheet_to_csv(ws);
                        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                        saveAs(blob, `${tab}_assets.csv`);
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
    xhr.open('GET', `assetsT.php?${params.toString()}`, true);
    xhr.send();
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

const debouncedSearchAssets = debounce((page) => searchAssets(page), 300);
</script>
</body>
</html>