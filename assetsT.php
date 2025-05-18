
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

// Handle add/edit/borrow/deploy asset requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && $userType !== 'technician') {
    $hasError = false;

    // Add Asset
    if (isset($_POST['add_asset'])) {
        $assetname = trim($_POST['asset_name'] ?? '');
        $assetstatus = trim($_POST['asset_status'] ?? '');
        $assetquantity = trim($_POST['asset_quantity'] ?? '');
        $assetdate = trim($_POST['date'] ?? '');
        $assetnameErr = $assetstatusErr = $assetquantityErr = $assetdateErr = "";

        // Validate inputs
        if (!preg_match("/^[a-zA-Z\s-]+$/", $assetname)) {
            $assetnameErr = "Asset Name should not contain numbers.";
            $hasError = true;
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
                    echo json_encode(['success' => true, 'message' => 'Asset registered successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to register asset.']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error.']);
            }
        } else {
            echo json_encode([
                'success' => false,
                'errors' => [
                    'asset_name' => $assetnameErr,
                    'asset_status' => $assetstatusErr,
                    'asset_quantity' => $assetquantityErr,
                    'date' => $assetdateErr
                ]
            ]);
        }
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

        // Validate inputs
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
                    echo json_encode(['success' => true, 'message' => 'Asset updated successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update asset.']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error.']);
            }
        } else {
            echo json_encode([
                'success' => false,
                'errors' => [
                    'asset_name' => $assetnameErr,
                    'asset_status' => $assetstatusErr,
                    'asset_quantity' => $assetquantityErr,
                    'date' => $assetdateErr
                ]
            ]);
        }
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

        // Validate inputs
        if (empty($asset_id)) {
            $errors['asset_id'] = "Please select an asset.";
        }
        if (empty($borrowquantity) || !is_numeric($borrowquantity) || $borrowquantity <= 0) {
            $errors['borrow_quantity'] = "Please enter a valid quantity (greater than 0).";
        }
        if (empty($borrow_techname) || !preg_match("/^[a-zA-Z\s-]+$/", $borrow_techname)) {
            $errors['tech_name'] = "Technician name is required and should not contain numbers.";
        }
        if (empty($borrow_techid)) {
            $errors['tech_id'] = "Technician ID is required.";
        }
        if (empty($borrowdate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $borrowdate)) {
            $errors['date'] = "Borrow date is required and must be valid.";
        }

        // Validate technician ID and name
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
                    $errors['tech_name'] = "Technician name does not match the ID.";
                }
            } else {
                $errors['tech_id'] = "Technician ID does not exist.";
            }
            $stmtCheckTechnician->close();
        }

        // Check if technician has borrowed assets
        if (empty($errors)) {
            $sqlCheckBorrowed = "SELECT SUM(b_quantity) AS total_borrowed FROM tbl_borrowed WHERE b_technician_id = ?";
            $stmtCheckBorrowed = $conn->prepare($sqlCheckBorrowed);
            $stmtCheckBorrowed->bind_param("s", $borrow_techid);
            $stmtCheckBorrowed->execute();
            $resultCheckBorrowed = $stmtCheckBorrowed->get_result();
            $row = $resultCheckBorrowed->fetch_assoc();

            if ($row['total_borrowed'] > 0) {
                $errors['tech_id'] = "Technician must return borrowed assets before borrowing again.";
            }
            $stmtCheckBorrowed->close();
        }

        // Validate asset and quantity
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
                    $errors['borrow_quantity'] = "Requested quantity ($borrowquantity) exceeds available stock ($availableQuantity).";
                }
            } else {
                $errors['asset_id'] = "Selected asset is not available.";
            }
            $stmtCheckAsset->close();
        }

        // If no errors, perform database operations in a transaction
        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // Insert into tbl_borrowed
                $sqlInsertBorrowed = "INSERT INTO tbl_borrowed (b_assets_name, b_quantity, b_technician_name, b_technician_id, b_date) 
                                      VALUES (?, ?, ?, ?, ?)";
                $stmtInsertBorrowed = $conn->prepare($sqlInsertBorrowed);
                $stmtInsertBorrowed->bind_param("sisis", $borrow_assetsname, $borrowquantity, $borrow_techname, $borrow_techid, $borrowdate);
                $stmtInsertBorrowed->execute();
                $stmtInsertBorrowed->close();

                // Insert into tbl_techborrowed
                $sqlInsertTechBorrowed = "INSERT INTO tbl_techborrowed (b_assets_name, b_quantity, b_technician_name, b_technician_id, b_date) 
                                          VALUES (?, ?, ?, ?, ?)";
                $stmtInsertTechBorrowed = $conn->prepare($sqlInsertTechBorrowed);
                $stmtInsertTechBorrowed->bind_param("sisis", $borrow_assetsname, $borrowquantity, $borrow_techname, $borrow_techid, $borrowdate);
                $stmtInsertTechBorrowed->execute();
                $stmtInsertTechBorrowed->close();

                // Update asset quantity
                $newQuantity = $availableQuantity - $borrowquantity;
                $sqlUpdate = "UPDATE tbl_assets SET a_quantity = ? WHERE a_id = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bind_param("ii", $newQuantity, $asset_id);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                // Commit transaction
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Asset borrowed successfully!']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Error borrowing asset: ' . $e->getMessage()]);
                error_log("Borrow error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'errors' => $errors]);
        }
        exit();
    }

    // Deploy Asset
    if (isset($_POST['deploy_asset'])) {
        $errors = [];
        $borrow_assetsname = trim($_POST['asset_name'] ?? '');
        $borrowquantity = trim($_POST['borrow_quantity'] ?? '');
        $borrow_techname = trim($_POST['tech_name'] ?? '');
        $borrow_techid = trim($_POST['tech_id'] ?? '');
        $borrowdate = trim($_POST['date'] ?? '');

        // Validate inputs
        if (empty($borrow_assetsname) || !preg_match("/^[a-zA-Z\s-]+$/", $borrow_assetsname)) {
            $errors['asset_name'] = "Asset Name is required and should not contain numbers.";
        }
        if (empty($borrowquantity) || !is_numeric($borrowquantity) || $borrowquantity <= 0) {
            $errors['borrow_quantity'] = "Please enter a valid quantity (greater than 0).";
        }
        if (empty($borrow_techname) || !preg_match("/^[a-zA-Z\s-]+$/", $borrow_techname)) {
            $errors['tech_name'] = "Technician name is required and should not contain numbers.";
        }
        if (empty($borrow_techid)) {
            $errors['tech_id'] = "Technician ID is required.";
        }
        if (empty($borrowdate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $borrowdate)) {
            $errors['date'] = "Deployment date is required and must be valid.";
        }

        // Validate technician ID and name
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
                    $errors['tech_name'] = "Technician name does not match the ID.";
                }
            } else {
                $errors['tech_id'] = "Technician ID does not exist.";
            }
            $stmtCheckTechnician->close();
        }

        // Check if asset exists and has sufficient quantity
        if (empty($errors)) {
            $sqlCheckAsset = "SELECT a_quantity FROM tbl_deployment_assets WHERE a_name = ?";
            $stmtCheckAsset = $conn->prepare($sqlCheckAsset);
            $stmtCheckAsset->bind_param("s", $borrow_assetsname);
            $stmtCheckAsset->execute();
            $resultCheckAsset = $stmtCheckAsset->get_result();

            if ($resultCheckAsset->num_rows > 0) {
                $row = $resultCheckAsset->fetch_assoc();
                $availableQuantity = $row['a_quantity'];

                if ($borrowquantity > $availableQuantity) {
                    $errors['borrow_quantity'] = "Requested quantity ($borrowquantity) exceeds available stock ($availableQuantity).";
                }
            } else {
                $errors['asset_name'] = "Asset not found in the deployment inventory.";
            }
            $stmtCheckAsset->close();
        }

        // If no errors, perform database operations in a transaction
        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // Insert into tbl_deployed
                $sqlInsert = "INSERT INTO tbl_deployed (d_assets_name, d_quantity, d_technician_name, d_technician_id, d_date) VALUES (?, ?, ?, ?, ?)";
                $stmtInsert = $conn->prepare($sqlInsert);
                $stmtInsert->bind_param("sisis", $borrow_assetsname, $borrowquantity, $borrow_techname, $borrow_techid, $borrowdate);
                $stmtInsert->execute();
                $stmtInsert->close();

                // Update asset quantity
                $newQuantity = $availableQuantity - $borrowquantity;
                $sqlUpdate = "UPDATE tbl_deployment_assets SET a_quantity = ? WHERE a_name = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bind_param("is", $newQuantity, $borrow_assetsname);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                // Commit transaction
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Asset deployed successfully!']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Error deploying asset: ' . $e->getMessage()]);
                error_log("Deploy error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'errors' => $errors]);
        }
        exit();
    }
}

// Handle archive/unarchive/delete requests (restricted for technicians)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $userType !== 'technician') {
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
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && $userType === 'technician') {
    $_SESSION['error'] = "Only staff can add, edit, deploy, or archive assets.";
    $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
    $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
    header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
    exit();
}

// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['tab'])) {
    $searchTerm = trim($_GET['search'] ?? '');
    $tab = $_GET['tab'] === 'archive' ? 'archive' : 'active';
    $page = isset($_GET['search_page']) ? (int)$_GET['search_page'] : 1;
    $limit = 10;
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
                ORDER BY a_id ASC 
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
                ORDER BY a_id ASC 
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
                                <a class='edit-btn' onclick=\"showEditAssetModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
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

if ($conn) {
    // Active Assets
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

    // Archived Assets
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
            <li><a href="staffD.php"><img src="image/ticket.png" alt="Regular Tickets" class="icon" /> <span>Regular Tickets</span></a></li>
            <li><a href="assetsT.php" class="active"><img src="image/assets.png" alt="Assets" class="icon" /> <span>Assets</span></a></li>
            <li><a href="customersT.php"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers</span></a></li>
            <li><a href="borrowedStaff.php"><img src="image/borrowed.png" alt="Borrowed Assets" class="icon" /> <span>Borrowed Assets</span></a></li>
            <li><a href="addC.php"><img src="image/add.png" alt="Add Customer" class="icon" /> <span>Add Customer</span></a></li>
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
                    <a href="#" class="add-btn" onclick="showAddAssetModal()"><i class="fas fa-plus"></i> Add Asset</a>
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
                                              <a class='edit-btn' onclick=\"showEditAssetModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
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
                <?php if ($userType !== 'technician'): ?>
                    <a href="#" class="borrow-btn" onclick="showBorrowAssetModal()"><i class="fas fa-plus"></i> Borrow</a>
                    <a href="#" class="deploy-btn" onclick="showDeployAssetModal()"><i class="fas fa-cogs"></i> Deploy</a>
                <?php else: ?>
                    <a href="#" class="borrow-btn disabled" onclick="showRestrictionMessage()"><i class="fas fa-plus"></i> Borrow</a>
                    <a href="#" class="deploy-btn disabled" onclick="showRestrictionMessage()"><i class="fas fa-cogs"></i> Deploy</a>
                <?php endif; ?>
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
                    <input type="hidden" name="ajax" value="true">
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
                    <input type="hidden" name="ajax" value="true">
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
                            <option value="Archived">Archived</option>
                        </select>
                        <span class="error" id="edit_asset_status_error"></span>
                    </div>
                    <div class="form-group">
                        <label for="date">Date Registered:</label>
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
                    <input type="hidden" name="ajax" value="true">
                    <div class="form-group">
                        <label for="asset_id">Asset Name</label>
                        <select id="asset_id" name="asset_id" required>
                            <option value="">Select Asset</option>
                            <?php 
                            $resultAssets->data_seek(0); // Reset result pointer
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
                    <input type="hidden" name="ajax" value="true">
                    <div class="form-group">
                        <label for="asset_name">Asset Name</label>
                        <input type="text" id="asset_name" name="asset_name" placeholder="Asset Name" required>
                        <span class="error" id="deploy_asset_name_error"></span>
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
    searchAssets(1, tab);
}

function showRestrictionMessage() {
    alert("Only staff can add, view, edit, borrow, deploy, or archive assets.");
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

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function searchAssets(page = 1, tab = null) {
    const searchTerm = document.getElementById('searchInput').value;
    const activeTab = tab || (document.getElementById('assets-active').style.display !== 'none' ? 'active' : 'archive');
    const tbody = document.getElementById(activeTab === 'active' ? 'assets-table-body' : 'archived-assets-table-body');

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
    xhr.open('GET', `assetsT.php?action=search&tab=${activeTab}&search=${encodeURIComponent(searchTerm)}&search_page=${page}`, true);
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

// Handle Add/Edit/Borrow/Deploy Asset Form Submissions
document.getElementById('addAssetForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('alert-success', data.message);
            closeModal('addAssetModal');
            searchAssets(1);
        } else {
            if (data.errors) {
                for (const [field, error] of Object.entries(data.errors)) {
                    document.getElementById(`add_${field}_error`).textContent = error;
                }
            } else {
                showAlert('alert-error', data.message);
            }
        }
    })
    .catch(error => {
        showAlert('alert-error', 'An error occurred.');
        console.error(error);
    });
});

document.getElementById('editAssetForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('alert-success', data.message);
            closeModal('editAssetModal');
            searchAssets(1);
        } else {
            if (data.errors) {
                for (const [field, error] of Object.entries(data.errors)) {
                    document.getElementById(`edit_${field}_error`).textContent = error;
                }
            } else {
                showAlert('alert-error', data.message);
            }
        }
    })
    .catch(error => {
        showAlert('alert-error', 'An error occurred.');
        console.error(error);
    });
});

document.getElementById('borrowAssetForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('alert-success', data.message);
            closeModal('borrowAssetModal');
            searchAssets(1);
        } else {
            if (data.errors) {
                for (const [field, error] of Object.entries(data.errors)) {
                    document.getElementById(`borrow_${field}_error`).textContent = error;
                }
            } else {
                showAlert('alert-error', data.message);
            }
        }
    })
    .catch(error => {
        showAlert('alert-error', 'An error occurred.');
        console.error(error);
    });
});

document.getElementById('deployAssetForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('alert-success', data.message);
            closeModal('deployAssetModal');
            searchAssets(1);
        } else {
            if (data.errors) {
                for (const [field, error] of Object.entries(data.errors)) {
                    document.getElementById(`deploy_${field}_error`).textContent = error;
                }
            } else {
                showAlert('alert-error', data.message);
            }
        }
    })
    .catch(error => {
        showAlert('alert-error', 'An error occurred.');
        console.error(error);
    });
});

function showAlert(type, message) {
    const alertContainer = document.querySelector('.alert-container');
    const alert = document.createElement('div');
    alert.className = `alert ${type}`;
    alert.textContent = message;
    alertContainer.appendChild(alert);
    setTimeout(() => {
        alert.classList.add('alert-hidden');
        setTimeout(() => alert.remove(), 500);
    }, 2000);
}
</script>
</body>
</html>

<?php $conn->close(); ?>
