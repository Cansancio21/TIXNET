<?php
    session_start();
    include 'db.php';

    if (!isset($_SESSION['username'])) {
        header("Location: index.php");
        exit();
    }

    $username = $_SESSION['username'];
    $firstName = '';
    $userType = '';
    $avatarPath = 'default-avatar.png';
    $avatarFolder = 'Uploads/avatars/';
    $userAvatar = $avatarFolder . $username . '.png';

    if (file_exists($userAvatar)) {
        $avatarPath = $userAvatar;
    } else {
        $avatarPath = 'default-avatar.png';
    }

$uniqueActiveAssetNames = [];
$uniqueArchivedAssetNames = [];
$activeNamesQuery = "SELECT DISTINCT a_name FROM tbl_assets WHERE a_current_status != 'Archived' ORDER BY a_name";
$archivedNamesQuery = "SELECT DISTINCT a_name FROM tbl_assets WHERE a_current_status = 'Archived' ORDER BY a_name";

$activeNamesResult = $conn->query($activeNamesQuery);
if ($activeNamesResult && $activeNamesResult->num_rows > 0) {
    while ($row = $activeNamesResult->fetch_assoc()) {
        $uniqueActiveAssetNames[] = $row['a_name'];
    }
}
$activeNamesResult->close();

$archivedNamesResult = $conn->query($archivedNamesQuery);
if ($archivedNamesResult && $archivedNamesResult->num_rows > 0) {
    while ($row = $archivedNamesResult->fetch_assoc()) {
        $uniqueArchivedAssetNames[] = $row['a_name'];
    }
}
$archivedNamesResult->close();

if (isset($_GET['action']) && $_GET['action'] === 'get_asset_names') {
    $tab = $_GET['tab'] ?? 'active';
    $assetNames = [];
    
    if ($tab === 'archive') {
        $query = "SELECT DISTINCT a_name FROM tbl_assets WHERE a_current_status = 'Archived' ORDER BY a_name";
    } else {
        $query = "SELECT DISTINCT a_name FROM tbl_assets WHERE a_current_status != 'Archived' ORDER BY a_name";
    }
    
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $assetNames[] = $row['a_name'];
        }
    }
    $result->close();
    
    header('Content-Type: application/json');
    echo json_encode(['assetNames' => $assetNames]);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'export_data' && isset($_GET['tab'])) {
    $searchTerm = trim($_GET['search'] ?? '');
    $filterName = trim($_GET['filter_name'] ?? '');
    $filterStatus = trim($_GET['filter_status'] ?? '');
    $assetName = trim($_GET['asset_name'] ?? '');
    $techName = trim($_GET['tech_name'] ?? '');
    $tab = $_GET['tab'];

    $params = [];
    $types = '';
    $whereClauses = [];

    if ($tab === 'borrowed') {
        $whereClauses[] = "a_status = 'Borrowed'";
        if ($searchTerm !== '') {
            $whereClauses[] = "(a_name LIKE ? OR a_ref_no LIKE ? OR tech_name LIKE ? OR tech_id LIKE ? OR a_date LIKE ?)";
            $searchWildcard = "%$searchTerm%";
            $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
            $types .= 'sssss';
        }
        if ($assetName !== '') {
            $whereClauses[] = "a_name = ?";
            $params[] = $assetName;
            $types .= 's';
        }
        if ($techName !== '') {
            $whereClauses[] = "tech_name = ?";
            $params[] = $techName;
            $types .= 's';
        }

        $whereClause = implode(' AND ', $whereClauses);

        $sql = "SELECT a_ref_no, a_name, tech_name, tech_id, a_serial_no, a_date, a_status AS category
                FROM tbl_asset_status 
                WHERE $whereClause 
                ORDER BY a_ref_no ASC";
    } else {
        $statusCondition = $tab === 'active' ? "a_current_status != 'Archived'" : "a_current_status = 'Archived'";
        $whereClauses[] = $statusCondition;

        if ($searchTerm !== '') {
            $whereClauses[] = "(a_name LIKE ? OR a_status LIKE ? OR a_quantity LIKE ? OR a_date LIKE ? OR a_ref_no LIKE ?)";
            $searchWildcard = "%$searchTerm%";
            $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
            $types .= 'sssss';
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

        $sql = "SELECT a_ref_no, a_name, a_status AS category, a_current_status, a_date, a_serial_no 
                FROM tbl_assets 
                WHERE $whereClause 
                ORDER BY a_ref_no ASC";
    }

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $assets = [];
    if ($tab === 'borrowed') {
        while ($row = $result->fetch_assoc()) {
            $assets[] = [
                'Asset Ref No' => $row['a_ref_no'],
                'Asset Name' => $row['a_name'],
                'Category' => $row['category'],
                'Current Status' => 'Borrowed',
                'Technician Name' => $row['tech_name'],
                'Technician ID' => $row['tech_id'],
                'Serial No' => $row['a_serial_no'] ?? '',
                'Date Borrowed' => $row['a_date'],
            ];
        }
    } else {
        while ($row = $result->fetch_assoc()) {
            $assets[] = [
                'Asset Ref No' => $row['a_ref_no'],
                'Asset Name' => $row['a_name'],
                'Category' => $row['category'],
                'Current Status' => $row['a_current_status'],
                'Serial No' => $row['a_serial_no'] ?? '',
                'Date Registered' => $row['a_date'],
            ];
        }
    }
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode(['data' => $assets]);
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['ref_no']) && isset($_POST['tab'])) {
    $refNo = $_POST['ref_no'];
    $tab = $_POST['tab'];
    $status = $tab === 'borrowed' ? 'Borrowed' : 'Archive';
    $deleteSql = "DELETE FROM tbl_asset_status WHERE a_ref_no = ? AND a_status = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param("ss", $refNo, $status);
    
    if ($deleteStmt->execute()) {
        $updateSql = "UPDATE tbl_assets SET a_current_status = 'Available' WHERE a_ref_no = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("s", $refNo);
        $updateStmt->execute();
        $updateStmt->close();
        
        echo "Asset deleted successfully.";
    } else {
        http_response_code(500);
        echo "Error deleting asset: " . $deleteStmt->error;
    }
    $deleteStmt->close();
    exit();
}
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

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $hasError = false;

    if (isset($_POST['add_asset'])) {
    $errors = [];
    $assetname = trim($_POST['asset_name'] ?? '');
    $assetstatus = trim($_POST['asset_status'] ?? '');
    $assetquantity = trim($_POST['asset_quantity'] ?? '');
    $assetdate = trim($_POST['date'] ?? '');
    $serial_no = trim($_POST['serial_no'] ?? '');
    $asset_specs     = trim($_POST['asset_specs'] ?? '');
    $asset_cycle     = $_POST['asset_cycle'] ?? '';
    $asset_condition = $_POST['asset_condition'] ?? '';

    if (!preg_match("/^[a-zA-Z\s-]+$/", $assetname)) {
        $errors['asset_name'] = "Asset Name should not contain numbers.";
    }
    if (!in_array($assetstatus, ['Borrowing', 'Deployment'])) {
        $errors['asset_status'] = "Invalid asset status.";
    }
    if (!is_numeric($assetquantity) || $assetquantity < 0) {
        $errors['asset_quantity'] = "Quantity must be a non-negative number.";
    }
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $assetdate)) {
        $errors['date'] = "Invalid date format.";
    }
    if ($serial_no && $assetquantity > 1) {
        $errors['serial_no'] = "Cannot assign the same serial number to multiple assets.";
    }

    if (!empty($asset_specs) && !preg_match("/^[a-zA-Z\s\.,\-()\'\"&]+$/", $asset_specs)) {
            $errors['asset_specs'] = "Asset Specification can only contain letters, spaces, and basic punctuation. Numbers are not allowed.";
    }
    if (!in_array($asset_cycle, ['Reusable', 'Non-reusable'])) {
            $errors['asset_cycle'] = "Please select a valid Asset Lifecycle.";
    }
        $valid_conditions = ['Brand New', 'Good Condition', 'Slightly Used', 'For Repair', 'Damaged'];
    if (!in_array($asset_condition, $valid_conditions)) {
            $errors['asset_condition'] = "Please select a valid Asset Condition.";
    }

    if (empty($errors)) {
        $prefix = strtoupper(preg_replace('/\W+/', '', $assetname)) . '-';
        $sql_max = "SELECT MAX(CAST(SUBSTR(a_ref_no, LENGTH(?) + 1) AS UNSIGNED)) AS max_num FROM tbl_assets WHERE a_ref_no LIKE CONCAT(?, '%')";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->bind_param("ss", $prefix, $prefix);
        $stmt_max->execute();
        $result_max = $stmt_max->get_result();
        $max_num = $result_max->fetch_assoc()['max_num'] ?? 0;
        $stmt_max->close();

        $conn->begin_transaction();
        try {
            for ($i = 1; $i <= $assetquantity; $i++) {
                $num = $max_num + $i;
                $ref_no = $prefix . sprintf('%03d', $num);
                $sql = "INSERT INTO tbl_assets (a_name, a_status, a_quantity, a_date, a_ref_no, a_serial_no, a_current_status, a_specs, a_cycle, a_condition) VALUES (?, ?, 1, ?, ?, ?, 'Available', ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssss", $assetname, $assetstatus, $assetdate, $ref_no, $serial_no, $asset_specs, $asset_cycle, $asset_condition);
                $stmt->execute();
                $stmt->close();
            }
            $conn->commit();

            $logDescription = "Staff {$_SESSION['username']} added $assetquantity asset(s) named '$assetname'";
            $logType = "Staff {$_SESSION['username']}";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            $stmtLog->bind_param("ss", $logDescription, $logType);
            $stmtLog->execute();
            $stmtLog->close();
            
            // Clear session data on success
            unset($_SESSION['add_errors']);
            unset($_SESSION['add_form_data']);

            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Assets registered successfully.']);
                exit();
            }
            $_SESSION['message'] = "Assets registered successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $errors['general'] = "Failed to register assets: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }
        $_SESSION['add_errors'] = $errors;
        $_SESSION['add_form_data'] = [
            'asset_name' => $assetname,
            'asset_status' => $assetstatus,
            'asset_quantity' => $assetquantity,
            'date' => $assetdate,
            'serial_no' => $serial_no,
            'asset_specs' => $asset_specs,
            'asset_cycle' => $asset_cycle,
            'asset_condition' => $asset_condition
        ];
        $_SESSION['open_modal'] = 'addAsset';
    }

    $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
    $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
    header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
    exit();
}

    if (isset($_POST['edit_asset'])) {
    $errors = [];
    $assetRefNo = trim($_POST['a_ref_no'] ?? '');
    $assetname = trim($_POST['asset_name'] ?? '');
    $assetstatus = trim($_POST['asset_status'] ?? '');
    $assetcurrentstatus = trim($_POST['current_status'] ?? '');
    $serial_no = trim($_POST['serial_no'] ?? '');

    // Validate inputs
    if (empty($assetRefNo)) {
        $errors['a_ref_no'] = "Asset Reference Number is required.";
    }
    if (!preg_match("/^[a-zA-Z\s-]+$/", $assetname)) {
        $errors['asset_name'] = "Asset Name should not contain numbers.";
    }
    if (!in_array($assetstatus, ['Borrowing', 'Deployment', 'Archived'])) {
        $errors['asset_status'] = "Invalid asset status.";
    }
    if (!in_array($assetcurrentstatus, ['Available', 'Borrowed', 'Deployed', 'Archived'])) {
        $errors['current_status'] = "Invalid current status.";
    }

    // Check if asset is Borrowed or Deployed
    if (empty($errors)) {
        $sqlCheckStatus = "SELECT a_current_status FROM tbl_assets WHERE a_ref_no = ?";
        $stmtCheckStatus = $conn->prepare($sqlCheckStatus);
        $stmtCheckStatus->bind_param("s", $assetRefNo);
        $stmtCheckStatus->execute();
        $resultCheckStatus = $stmtCheckStatus->get_result();
        if ($resultCheckStatus->num_rows > 0) {
            $row = $resultCheckStatus->fetch_assoc();
            if (in_array($row['a_current_status'], ['Borrowed', 'Deployed'])) {
                $errors['general'] = "Cannot edit the borrowed and deployed asset.";
            }
        }
        $stmtCheckStatus->close();
    }

    if (empty($errors)) {
        $sql = "UPDATE tbl_assets SET a_name = ?, a_status = ?, a_current_status = ?, a_serial_no = ? WHERE a_ref_no = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssss", $assetname, $assetstatus, $assetcurrentstatus, $serial_no, $assetRefNo);
            if ($stmt->execute()) {
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Asset updated successfully!']);
                    exit();
                }
                $_SESSION['message'] = "Asset updated successfully.";
            } else {
                $errors['general'] = "Failed to update asset.";
            }
            $stmt->close();
        } else {
            $errors['general'] = "Database error.";
        }
    }

    if (!empty($errors)) {
        if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }
        $_SESSION['error'] = implode(" ", array_filter($errors));
        $_SESSION['edit_form_data'] = [
            'a_ref_no' => $assetRefNo,
            'asset_name' => $assetname,
            'asset_status' => $assetstatus,
            'current_status' => $assetcurrentstatus,
            'serial_no' => $serial_no,
        ];
        $_SESSION['open_modal'] = 'editAsset';
    }

    $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
    $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
    header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
    exit();
}

         ob_start();
    if (isset($_POST['borrow_asset'])) {
        $errors = [];
        $asset_ref_nos = $_POST['asset_ref_no'] ?? [];
        $borrow_techname = trim($_POST['tech_name'] ?? '');
        $borrow_techid = trim($_POST['tech_id'] ?? '');
        $borrowdate = trim($_POST['date'] ?? '');

        if (empty($asset_ref_nos) || !is_array($asset_ref_nos)) { $errors['asset_ref_no'] = "Please select at least one asset."; }
        if (empty($borrow_techname)) {
            $errors['tech_name'] = "Technician Name is required.";
        } elseif (!preg_match("/^[a-zA-Z\s-]+$/", $borrow_techname)) {
            $errors['tech_name'] = "Technician name should not contain numbers.";
        }
        if (empty($borrow_techid)) {
            $errors['tech_id'] = "Technician ID is required.";
        } elseif (!preg_match("/^[0-9]+$/", $borrow_techid)) {
            $errors['tech_id'] = "Technician ID should not contain letters.";
        }
        
        if (empty($errors)) {
            $sqlCheckTechnician = "SELECT u_fname, u_lname FROM tbl_user WHERE u_id = ?";
            $stmtCheckTechnician = $conn->prepare($sqlCheckTechnician);
            if (!$stmtCheckTechnician) {
                $errors['general'] = "Database error: Failed to prepare technician query: " . $conn->error;
            } else {
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
        }

        if (empty($errors)) {
            $sqlCheckBorrowed = "SELECT COUNT(*) AS total_borrowed FROM tbl_asset_status WHERE tech_id = ? AND a_status = 'Borrowed'";
            $stmtCheckBorrowed = $conn->prepare($sqlCheckBorrowed);
            if (!$stmtCheckBorrowed) {
                $errors['general'] = "Database error: Failed to prepare borrowed assets query: " . $conn->error;
            } else {
                $stmtCheckBorrowed->bind_param("s", $borrow_techid);
                $stmtCheckBorrowed->execute();
                $resultCheckBorrowed = $stmtCheckBorrowed->get_result();
                $row = $resultCheckBorrowed->fetch_assoc();
                $num_to_borrow = count($asset_ref_nos);
                if ($row['total_borrowed'] + $num_to_borrow > 5) {
                    $errors['asset_ref_no'] = "The borrowing of assets is limited to 5 per technician. Please return some assets to borrow more.";
                }
                $stmtCheckBorrowed->close();
            }
        }

        $asset_details = [];
        if (empty($errors)) {
            foreach ($asset_ref_nos as $ref_no) {
                $sqlCheckAsset = "SELECT a_name, a_serial_no FROM tbl_assets WHERE a_ref_no = ? AND a_current_status = 'Available' AND a_status = 'Borrowing'";
                $stmtCheckAsset = $conn->prepare($sqlCheckAsset);
                if (!$stmtCheckAsset) {
                    $errors['general'] = "Database error: Failed to prepare asset query: " . $conn->error;
                } else {
                    $stmtCheckAsset->bind_param("s", $ref_no);
                    $stmtCheckAsset->execute();
                    $resultCheckAsset = $stmtCheckAsset->get_result();
                    if ($resultCheckAsset->num_rows > 0) {
                        $row = $resultCheckAsset->fetch_assoc();
                        $asset_details[$ref_no] = [
                            'name' => $row['a_name'],
                            'serial_no' => $row['a_serial_no']
                        ];
                    } else {
                        $errors['asset_ref_no'] = "Asset with Ref No $ref_no is not available or not borrowable.";
                    }
                    $stmtCheckAsset->close();
                }
            }
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $sqlInsertStatus = "INSERT INTO tbl_asset_status (a_ref_no, a_name, tech_name, tech_id, a_serial_no, a_date, a_status) VALUES (?, ?, ?, ?, ?, ?, 'Borrowed')";
                $stmtInsertStatus = $conn->prepare($sqlInsertStatus);
                if (!$stmtInsertStatus) {
                    throw new Exception("Failed to prepare insert query for tbl_asset_status: " . $conn->error);
                }
                foreach ($asset_ref_nos as $ref_no) {
                    $asset_name = $asset_details[$ref_no]['name'];
                    $serial_no = $asset_details[$ref_no]['serial_no'];
                    $stmtInsertStatus->bind_param("ssssss", $ref_no, $asset_name, $borrow_techname, $borrow_techid, $serial_no, $borrowdate);
                    if (!$stmtInsertStatus->execute()) {
                        throw new Exception("Failed to insert into tbl_asset_status: " . $stmtInsertStatus->error);
                    }
                }
                $stmtInsertStatus->close();
                foreach ($asset_ref_nos as $ref_no) {
                    $sqlUpdate = "UPDATE tbl_assets SET a_current_status = 'Borrowed' WHERE a_ref_no = ?";
                    $stmtUpdate = $conn->prepare($sqlUpdate);
                    if (!$stmtUpdate) {
                        throw new Exception("Failed to prepare update query for tbl_assets: " . $conn->error);
                    }
                    $stmtUpdate->bind_param("s", $ref_no);
                    if (!$stmtUpdate->execute()) {
                        throw new Exception("Failed to update tbl_assets: " . $stmtUpdate->error);
                    }
                    $stmtUpdate->close();
                }

                $conn->commit();
                $logDescription = "Staff {$_SESSION['username']} borrowed " . count($asset_ref_nos) . " asset(s) to technician $borrow_techname (ID: $borrow_techid)";
                $logType = "Staff {$_SESSION['username']}";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
                $stmtLog = $conn->prepare($sqlLog);
                if ($stmtLog) {
                    $stmtLog->bind_param("ss", $logDescription, $logType);
                    $stmtLog->execute();
                    $stmtLog->close();
                } else {
                    error_log("Failed to prepare log query: " . $conn->error);
                }

                ob_clean();
                if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Assets borrowed successfully!']);
                    exit();
                }
                $_SESSION['message'] = "Assets borrowed successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $errors['general'] = "Error borrowing asset: " . $e->getMessage();
                error_log("Borrow error: " . $e->getMessage());
            }
        }

        if (!empty($errors)) {
            ob_clean();
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit();
            }
            $_SESSION['borrow_errors'] = $errors;
            $_SESSION['borrow_form_data'] = [
                'asset_ref_no' => $asset_ref_nos,
                'tech_name' => $borrow_techname,
                'tech_id' => $borrow_techid,
                'date' => $borrowdate,
            ];
            $_SESSION['open_modal'] = 'borrowAsset';
        }

        $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
        $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
        header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
        ob_end_flush();
        exit();
    }
 
if (isset($_POST['deploy_asset'])) {
    $errors = [];
    $asset_ref_nos = $_POST['asset_ref_no'] ?? [];
    $deploy_techname = trim($_POST['tech_name'] ?? '');
    $deploy_techid = trim($_POST['tech_id'] ?? '');
    $deploydate = trim($_POST['date'] ?? '');

    if (empty($asset_ref_nos) || !is_array($asset_ref_nos)) {
        $errors['asset_ref_no'] = "Please select at least one asset.";
    }
    if (empty($deploy_techname)) {
        $errors['tech_name'] = "Technician Name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s-]+$/", $deploy_techname)) {
        $errors['tech_name'] = "Technician name should not contain numbers.";
    }
    if (empty($deploy_techid)) {
        $errors['tech_id'] = "Technician ID is required.";
    } elseif (!preg_match("/^[0-9]+$/", $deploy_techid)) {
        $errors['tech_id'] = "Technician ID should not contain letters.";
    }
    if (empty($deploydate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $deploydate)) {
        $errors['date'] = "Invalid date format.";
    }

    if (empty($errors)) {
        $sqlCheckTechnician = "SELECT u_fname, u_lname FROM tbl_user WHERE u_id = ?";
        $stmtCheckTechnician = $conn->prepare($sqlCheckTechnician);
        if (!$stmtCheckTechnician) {
            $errors['general'] = "Database error: Failed to prepare technician query: " . $conn->error;
        } else {
            $stmtCheckTechnician->bind_param("s", $deploy_techid);
            $stmtCheckTechnician->execute();
            $resultCheckTechnician = $stmtCheckTechnician->get_result();
            if ($resultCheckTechnician->num_rows > 0) {
                $row = $resultCheckTechnician->fetch_assoc();
                $fullName = trim($row['u_fname'] . ' ' . $row['u_lname']);
                if (strcasecmp($fullName, $deploy_techname) !== 0) {
                    $errors['tech_name'] = "Technician name does not match the ID.";
                }
            } else {
                $errors['tech_id'] = "Technician ID does not exist.";
            }
            $stmtCheckTechnician->close();
        }
    }


    $asset_details = [];
    if (empty($errors)) {
        foreach ($asset_ref_nos as $ref_no) {
            $sqlCheckAsset = "SELECT a_name, a_serial_no FROM tbl_assets WHERE a_ref_no = ? AND a_current_status = 'Available' AND a_status = 'Deployment'";
            $stmtCheckAsset = $conn->prepare($sqlCheckAsset);
            if (!$stmtCheckAsset) {
                $errors['general'] = "Database error: Failed to prepare asset query: " . $conn->error;
            } else {
                $stmtCheckAsset->bind_param("s", $ref_no);
                $stmtCheckAsset->execute();
                $resultCheckAsset = $stmtCheckAsset->get_result();
                if ($resultCheckAsset->num_rows > 0) {
                    $row = $resultCheckAsset->fetch_assoc();
                    $asset_details[$ref_no] = [
                        'name' => $row['a_name'],
                        'serial_no' => $row['a_serial_no']
                    ];
                } else {
                    $errors['asset_ref_no'] = "Asset with Ref No $ref_no is not available or not deployable.";
                }
                $stmtCheckAsset->close();
            }
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try { $sqlInsertStatus = "INSERT INTO tbl_asset_status (a_ref_no, a_name, tech_name, tech_id, a_serial_no, a_date, a_status) VALUES (?, ?, ?, ?, ?, ?, 'Deployed')";
            $stmtInsertStatus = $conn->prepare($sqlInsertStatus);
            if (!$stmtInsertStatus) {
                throw new Exception("Failed to prepare insert query for tbl_asset_status: " . $conn->error);
            }
            foreach ($asset_ref_nos as $ref_no) {
                $asset_name = $asset_details[$ref_no]['name'];
                $serial_no = $asset_details[$ref_no]['serial_no'];
                $stmtInsertStatus->bind_param("ssssss", $ref_no, $asset_name, $deploy_techname, $deploy_techid, $serial_no, $deploydate);
                if (!$stmtInsertStatus->execute()) {
                    throw new Exception("Failed to insert into tbl_asset_status: " . $stmtInsertStatus->error);
                }
            }
            $stmtInsertStatus->close();

            foreach ($asset_ref_nos as $ref_no) {
                $sqlUpdate = "UPDATE tbl_assets SET a_current_status = 'Deployed' WHERE a_ref_no = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                if (!$stmtUpdate) {
                    throw new Exception("Failed to prepare update query for tbl_assets: " . $conn->error);
                }
                $stmtUpdate->bind_param("s", $ref_no);
                if (!$stmtUpdate->execute()) {
                    throw new Exception("Failed to update tbl_assets: " . $stmtUpdate->error);
                }
                $stmtUpdate->close();
            }

            $conn->commit();

            $logDescription = "Staff {$_SESSION['username']} deployed " . count($asset_ref_nos) . " asset(s) to technician $deploy_techname (ID: $deploy_techid)";
            $logType = "Staff {$_SESSION['username']}";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            if ($stmtLog) {
                $stmtLog->bind_param("ss", $logDescription, $logType);
                $stmtLog->execute();
                $stmtLog->close();
            } else {
                error_log("Failed to prepare log query: " . $conn->error);
            }

            ob_clean();
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Assets deployed successfully!']);
                exit();
            }
            $_SESSION['message'] = "Assets deployed successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $errors['general'] = "Error deploying asset: " . $e->getMessage();
            error_log("Deploy error: " . $e->getMessage());
        }
    }

    if (!empty($errors)) {
        ob_clean();
        if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }
        $_SESSION['deploy_errors'] = $errors;
        $_SESSION['deploy_form_data'] = [
            'asset_ref_no' => $asset_ref_nos,
            'tech_name' => $deploy_techname,
            'tech_id' => $deploy_techid,
            'date' => $deploydate,
        ];
        $_SESSION['open_modal'] = 'deployAsset';
    }

    $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
    $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
    header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
    ob_end_flush();
    exit();
}

if (isset($_POST['return_asset'])) {
    $errors = [];
    $asset_ref_nos = $_POST['asset_ref_no'] ?? [];
    $returndate = trim($_POST['date'] ?? '');

    if (empty($asset_ref_nos) || !is_array($asset_ref_nos)) {
        $errors['asset_ref_no'] = "Please select at least one asset.";
    }
    if (empty($returndate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $returndate)) {
        $errors['date'] = "Invalid date format.";
    }

    $asset_details = [];
    if (empty($errors)) {
        foreach ($asset_ref_nos as $ref_no) {
            $sqlCheckAsset = "SELECT a_name, a_serial_no, a_status, tech_name, tech_id FROM tbl_asset_status WHERE a_ref_no = ? AND a_status = 'Borrowed'";
            $stmtCheckAsset = $conn->prepare($sqlCheckAsset);
            if (!$stmtCheckAsset) {
                $errors['general'] = "Database error: Failed to prepare asset query: " . $conn->error;
            } else {
                $stmtCheckAsset->bind_param("s", $ref_no);
                $stmtCheckAsset->execute();
                $resultCheckAsset = $stmtCheckAsset->get_result();
                if ($resultCheckAsset->num_rows > 0) {
                    $row = $resultCheckAsset->fetch_assoc();
                    $asset_details[$ref_no] = [
                        'name' => $row['a_name'],
                        'serial_no' => $row['a_serial_no'],
                        'status' => $row['a_status'],
                        'tech_name' => $row['tech_name'],
                        'tech_id' => $row['tech_id']
                    ];
                } else {
                    $errors['asset_ref_no'] = "Asset with Ref No $ref_no is not borrowed.";
                }
                $stmtCheckAsset->close();
            }
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Update existing 'Borrowed' record to 'Returned' with return date
            $sqlUpdateStatus = "UPDATE tbl_asset_status SET a_status = 'Returned', a_return_date = ? WHERE a_ref_no = ? AND a_status = 'Borrowed'";
            $stmtUpdateStatus = $conn->prepare($sqlUpdateStatus);
            if (!$stmtUpdateStatus) {
                throw new Exception("Failed to prepare update query for tbl_asset_status: " . $conn->error);
            }

            // Update tbl_assets to 'Available'
            $sqlUpdate = "UPDATE tbl_assets SET a_current_status = 'Available' WHERE a_ref_no = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            if (!$stmtUpdate) {
                throw new Exception("Failed to prepare update query for tbl_assets: " . $conn->error);
            }

            foreach ($asset_ref_nos as $ref_no) {
                // Update status to Returned with return date
                $stmtUpdateStatus->bind_param("ss", $returndate, $ref_no);
                if (!$stmtUpdateStatus->execute()) {
                    throw new Exception("Failed to update tbl_asset_status: " . $stmtUpdateStatus->error);
                }

                // Update tbl_assets
                $stmtUpdate->bind_param("s", $ref_no);
                if (!$stmtUpdate->execute()) {
                    throw new Exception("Failed to update tbl_assets: " . $stmtUpdate->error);
                }
            }

            $stmtUpdateStatus->close();
            $stmtUpdate->close();

            $conn->commit();

            // Log the action
            $logDescription = "Staff {$_SESSION['username']} returned " . count($asset_ref_nos) . " asset(s)";
            $logType = "Staff {$_SESSION['username']}";
            $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description, l_type) VALUES (NOW(), ?, ?)";
            $stmtLog = $conn->prepare($sqlLog);
            if ($stmtLog) {
                $stmtLog->bind_param("ss", $logDescription, $logType);
                $stmtLog->execute();
                $stmtLog->close();
            } else {
                error_log("Failed to prepare log query: " . $conn->error);
            }

            ob_clean();
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Assets returned successfully!']);
                exit();
            }
            $_SESSION['message'] = "Assets returned successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $errors['general'] = "Error returning asset: " . $e->getMessage();
            error_log("Return error: " . $e->getMessage());
        }
    }

    if (!empty($errors)) {
        ob_clean();
        if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }
        $_SESSION['return_errors'] = $errors;
        $_SESSION['return_form_data'] = [
            'asset_ref_no' => $asset_ref_nos,
            'date' => $returndate,
        ];
        $_SESSION['open_modal'] = 'returnAsset';
    }

    $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
    $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
    header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
    ob_end_flush();
    exit();
}

    if (isset($_POST['archive_asset'])) {
        $assetRefNo = trim($_POST['a_ref_no'] ?? '');
        $sql = "UPDATE tbl_assets SET a_current_status = 'Archived' WHERE a_ref_no = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $assetRefNo);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Asset archived successfully!";
        } else {
            $_SESSION['error'] = "Error archiving asset.";
        }
        $stmt->close();
    } if (isset($_POST['unarchive_asset'])) {
    $assetRefNo = trim($_POST['a_ref_no'] ?? '');
    
    // Determine the previous status by checking tbl_asset_status
    $previousStatus = 'Available';
    $sqlCheck = "SELECT a_status FROM tbl_asset_status WHERE a_ref_no = ? AND a_status IN ('Borrowed', 'Deployed') LIMIT 1";
    $stmtCheck = $conn->prepare($sqlCheck);
    if ($stmtCheck) {
        $stmtCheck->bind_param("s", $assetRefNo);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        if ($resultCheck->num_rows > 0) {
            $row = $resultCheck->fetch_assoc();
            $previousStatus = $row['a_status'];
        }
        $stmtCheck->close();
    }
    
    // Unarchive by setting back to the previous status
    $sql = "UPDATE tbl_assets SET a_current_status = ? WHERE a_ref_no = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $previousStatus, $assetRefNo);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Asset unarchived successfully!";
        } else {
            $_SESSION['error'] = "Error unarchiving asset.";
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Database error.";
    }
    
    $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
    $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
    header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
    exit();
}
    }

   if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['tab'])) {
    error_log("Processing search for tab: " . $_GET['tab']); // Debug log
    $searchTerm = trim($_GET['search'] ?? '');
    $filterName = trim($_GET['filter_name'] ?? '');
    $filterStatus = trim($_GET['filter_status'] ?? '');
    $assetName = trim($_GET['asset_name'] ?? '');
    $techName = trim($_GET['tech_name'] ?? '');
    $tab = $_GET['tab'];
    $page = isset($_GET['search_page']) ? (int)$_GET['search_page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $output = '';

    if ($tab === 'borrowed') {
        error_log("Executing borrowed tab query with searchTerm: $searchTerm, assetName: $assetName, techName: $techName");
        $params = [];
        $types = '';
        $whereClauses = ["a_status = 'Borrowed'"];

        if ($searchTerm !== '') {
            $whereClauses[] = "(a_name LIKE ? OR a_ref_no LIKE ? OR tech_name LIKE ? OR tech_id LIKE ? OR a_date LIKE ?)";
            $searchWildcard = "%$searchTerm%";
            $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
            $types .= 'sssss';
        }
        if ($assetName !== '') {
            $whereClauses[] = "a_name = ?";
            $params[] = $assetName;
            $types .= 's';
        }
        if ($techName !== '') {
            $whereClauses[] = "tech_name = ?";
            $params[] = $techName;
            $types .= 's';
        }

        $whereClause = implode(' AND ', $whereClauses);

        $countSql = "SELECT COUNT(*) as total FROM tbl_asset_status WHERE $whereClause";
        error_log("Borrowed Count SQL: $countSql, Params: " . json_encode($params));
        $countStmt = $conn->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalRecords = $countResult->fetch_assoc()['total'];
        $countStmt->close();

        $totalPages = ceil($totalRecords / $limit);

        $sql = "SELECT a_ref_no, a_name, tech_name, tech_id, a_serial_no, a_date 
                FROM tbl_asset_status 
                WHERE $whereClause 
                ORDER BY a_ref_no ASC 
                LIMIT ?, ?";
        error_log("Borrowed Main SQL: $sql, Params: " . json_encode(array_merge($params, [$offset, $limit])));
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
                    <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['tech_name'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . htmlspecialchars($row['tech_id'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>" . ($row['a_serial_no'] ? htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8') : '') . "</td>
                    <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td>
                    <td>
                        <a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', 'Borrowed', 'Borrowed', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['tech_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['tech_id'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                        <a class='borrowdelete-btn' onclick=\"showBorrowedDeleteModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                    </td></tr>";
            }
        } else {
            $output .= "<tr><td colspan='7'>No borrowed assets found.</td></tr>";
        }
        $stmt->close();

        $output .= "<script>updatePagination($page, $totalPages, 'borrowed', '$searchTerm', 'borrowed-pagination');</script>";
        echo $output;
        exit();
    } elseif ($tab === 'active') {
    error_log("Executing active tab query with searchTerm: $searchTerm, filterName: $filterName, filterStatus: $filterStatus");
    $params = [];
    $types = '';
    $whereClauses = ["a_current_status != 'Archived'"]; // Use a_current_status instead of a_status

    if ($searchTerm !== '') {
        $whereClauses[] = "(a_name LIKE ? OR a_ref_no LIKE ? OR a_serial_no LIKE ? OR a_date LIKE ?)";
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
    error_log("Active Count SQL: $countSql, Params: " . json_encode($params));
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = ceil($totalRecords / $limit);

   $sql = "SELECT a_ref_no, a_name, a_status, a_current_status, a_date, a_quantity, a_serial_no, a_specs, a_cycle, a_condition 
        FROM tbl_assets 
        WHERE $whereClause 
        ORDER BY a_ref_no ASC 
        LIMIT ?, ?";
    error_log("Active Main SQL: $sql, Params: " . json_encode(array_merge($params, [$offset, $limit])));
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
                <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>
                    <a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                    <a class='edit-btn' onclick=\"showEditAssetModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                    <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>
                </td></tr>";
        }
    } else {
        $output .= "<tr><td colspan='8'>No active assets found.</td></tr>";
    }
    $stmt->close();

    $output .= "<script>updatePagination($page, $totalPages, 'active', '$searchTerm', 'active-pagination');</script>";
    echo $output;
    exit();
} elseif ($tab === 'archive') {
    error_log("Executing archive tab query with searchTerm: $searchTerm, filterName: $filterName, filterStatus: $filterStatus");
    $params = [];
    $types = '';
    $whereClauses = ["a_current_status = 'Archived'"]; // Use a_current_status instead of a_status

    if ($searchTerm !== '') {
        $whereClauses[] = "(a_name LIKE ? OR a_ref_no LIKE ? OR a_serial_no LIKE ? OR a_date LIKE ?)";
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
    error_log("Archive Count SQL: $countSql, Params: " . json_encode($params));
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = ceil($totalRecords / $limit);

    $sql = "SELECT a_ref_no, a_name, a_status, a_current_status, a_date, a_quantity, a_serial_no, a_specs, a_cycle, a_condition 
        FROM tbl_assets 
        WHERE $whereClause 
        ORDER BY a_ref_no ASC 
        LIMIT ?, ?";
    error_log("Archive Main SQL: $sql, Params: " . json_encode(array_merge($params, [$offset, $limit])));
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
                <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>
                    <a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                    <a class='unarchive-btn' onclick=\"showUnarchiveModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                    <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                </td></tr>";
        }
    } else {
        $output .= "<tr><td colspan='8'>No archived assets found.</td></tr>";
    }
    $stmt->close();

    $output .= "<script>updatePagination($page, $totalPages, 'archive', '$searchTerm', 'archived-pagination');</script>";
    echo $output;
    exit();
    } else {
        error_log("Invalid tab specified: $tab");
        echo "<tr><td colspan='8'>Invalid tab specified.</td></tr>";
        exit();
    }
}
    // Handle AJAX export data request
    if (isset($_GET['action']) && $_GET['action'] === 'export_data' && isset($_GET['tab'])) {
        $searchTerm = trim($_GET['search'] ?? '');
        $filterName = trim($_GET['filter_name'] ?? '');
        $filterStatus = trim($_GET['filter_status'] ?? '');
        $tab = $_GET['tab'] === 'archive' ? 'archive' : 'active';

        $statusCondition = $tab === 'active' ? "a_current_status != 'Archived'" : "a_current_status = 'Archived'";
        $params = [];
        $types = '';
        $whereClauses = [$statusCondition];

        if ($searchTerm !== '') {
            $whereClauses[] = "(a_name LIKE ? OR a_status LIKE ? OR a_quantity LIKE ? OR a_date LIKE ? OR a_ref_no LIKE ?)";
            $searchWildcard = "%$searchTerm%";
            $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
            $types .= 'sssss';
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
        $sql = "SELECT a_ref_no, a_name, a_status, a_current_status, a_date, a_quantity, a_serial_no 
                FROM tbl_assets 
                WHERE $whereClause 
                ORDER BY a_ref_no ASC";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $assets = [];
        while ($row = $result->fetch_assoc()) {
            $assets[] = [
        'Asset Ref No' => $row['a_ref_no'],
        'Asset Name' => $row['a_name'],
        'Category' => $row['a_status'],
        'Quantity' => $row['a_quantity'],
        'Current Status' => $row['a_current_status'],
        'Serial No' => $row['a_serial_no'] ?? '',
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
    $sqlAssets = "SELECT DISTINCT a_name, COUNT(*) as available FROM tbl_assets WHERE a_current_status = 'Available' AND a_status != 'Archived' GROUP BY a_name HAVING available > 0 ORDER BY a_name";
    $resultAssets = $conn->query($sqlAssets);

    // Fetch available deployment assets for deploy modal
    $sqlDeployAssets = "SELECT DISTINCT a_name, COUNT(*) as available FROM tbl_assets WHERE a_current_status = 'Available' AND a_status = 'Deployment' GROUP BY a_name HAVING available > 0 ORDER BY a_name";
    $resultDeployAssets = $conn->query($sqlDeployAssets);

    if ($conn) {
        $activeCountQuery = "SELECT COUNT(*) as total FROM tbl_assets WHERE a_current_status != 'Archived'";
        $activeCountResult = $conn->query($activeCountQuery);
        $totalActive = $activeCountResult ? $activeCountResult->fetch_assoc()['total'] : 0;
        $totalActivePages = ceil($totalActive / $limit);

        $sqlActive = "SELECT a_ref_no, a_name, a_status, a_current_status, a_date, a_quantity, a_serial_no, a_specs, a_cycle, a_condition FROM tbl_assets WHERE a_current_status != 'Archived' ORDER BY a_ref_no ASC LIMIT ?, ?";
        $stmtActive = $conn->prepare($sqlActive);
        $stmtActive->bind_param("ii", $activeOffset, $limit);
        $stmtActive->execute();
        $resultActive = $stmtActive->get_result();
        $stmtActive->close();

        $archivedCountQuery = "SELECT COUNT(*) as total FROM tbl_assets WHERE a_current_status = 'Archived'";
        $archivedCountResult = $conn->query($archivedCountQuery);
        $totalArchived = $archivedCountResult ? $archivedCountResult->fetch_assoc()['total'] : 0;
        $totalArchivedPages = ceil($totalArchived / $limit);

        $sqlArchived = "SELECT a_ref_no, a_name, a_status, a_current_status, a_date, a_quantity, a_serial_no, a_specs, a_cycle, a_condition FROM tbl_assets WHERE a_current_status = 'Archived' ORDER BY a_ref_no ASC LIMIT ?, ?";
        $stmtArchived = $conn->prepare($sqlArchived);
        $stmtArchived->bind_param("ii", $archivedOffset, $limit);
        $stmtArchived->execute();
        $resultArchived = $stmtArchived->get_result();
        $stmtArchived->close();
    } else {
        $_SESSION['error'] = "Database connection failed.";
    }

unset($_SESSION['add_errors']);
unset($_SESSION['add_form_data']);
unset($_SESSION['borrow_errors']);
unset($_SESSION['borrow_form_data']);
unset($_SESSION['deploy_errors']);
unset($_SESSION['deploy_form_data']);
unset($_SESSION['open_modal']);
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Asset Management</title>
        <link rel="stylesheet" href="assetsTT.css"> 
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
        <div class="sidebar">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
    <ul>
        <li><a href="staffD.php"><i class="fas fa-ticket-alt icon"></i> <span>Regular Tickets</span></a></li>
        <li><a href="assetsT.php" class="active"><i class="fas fa-boxes icon"></i> <span>Assets</span></a></li>
        <li><a href="AllCustomersT.php"><i class="fas fa-clipboard-check icon"></i> <span>Customer Ticket</span></a></li>
        <li><a href="customersT.php"><i class="fas fa-user-friends icon"></i> <span>Customers</span></a></li>
        <li><a href="AssignTech.php"><i class="fas fa-tools icon"></i> <span>Technicians</span></a></li>
        <li><a href="Payments.php"><i class="fas fa-credit-card icon"></i> <span>Transactions</span></a></li>
    </ul>
    <footer>
        <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </footer>
</div>

        <div class="container">
            <div class="upper"> 
                <h1>Asset Management</h1>
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
                    <a href="staffsettings.php" class="settings-link">
                        <i class="fas fa-cog"></i>
                     
                    </a>
                </div>
            </div>
                        
<div class="alert-container">
    <?php if (isset($_SESSION['message'])): ?>
        <div id="success-message" class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message']); ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['add_errors'])): ?>
        <div class="alert alert-error">Failed to add asset. Please check the form for errors.</div>
    <?php endif; ?>
    <?php if (isset($_SESSION['borrow_errors'])): ?>
        <div class="alert alert-error">Failed to borrow asset. Please check the form for errors.</div>
    <?php endif; ?>
    <?php if (isset($_SESSION['deploy_errors'])): ?>
        <div class="alert alert-error">Failed to deploy asset. Please check the form for errors.</div>
    <?php endif; ?>
</div>

            <div class="table-box glass-container">
                <div class="assets">
                    <h2>All Assets</h2>
                <div class="header-controls">
                   <div class="tab-buttons">
                   <button class="tab-btn active" onclick="showAssetTab('active')">Active (<?php echo $totalActive; ?>)</button>
                   <button class="tab-btn" onclick="showAssetTab('borrowed')">Borrowed (<?php 
                     $borrowedCountQuery = "SELECT COUNT(*) as total FROM tbl_asset_status WHERE a_status = 'Borrowed'";
                     $borrowedCountResult = $conn->query($borrowedCountQuery);
                     $totalBorrowed = $borrowedCountResult ? $borrowedCountResult->fetch_assoc()['total'] : 0;
                   echo $totalBorrowed;
                   ?>)</button>
                   <button class="tab-btn" onclick="showAssetTab('archive')">Archive 
                      <?php if ($totalArchived > 0): ?>
                        <span class="tab-badge"><?php echo $totalArchived; ?></span>
                      <?php endif; ?>
                   </button>
                </div>

                <div class="search-container">
                    <input type="text" class="search-bar" id="searchInput" placeholder="Search assets..." onkeyup="debouncedSearchAssets()">
                    <span class="search-icon"><i class="fas fa-search"></i></span>
                </div>

                 <div class="button-group">
                   <div class="action-container">
                      <button class="action-btn"><i class="fas fa-plus"></i> Actions</button>
                      <div class="action-dropdown">
                        <button onclick="showAddAssetModal()">Add Asset</button>
                        <button onclick="showBorrowAssetModal()">Borrow</button>
                        <button onclick="showDeployAssetModal()">Deploy</button>
                        <button onclick="showReturnAssetModal()">Return</button>
                      </div>
                   </div>
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
                            <th>Asset Ref No</th>
                            <th>Asset Name
                                    <button class="filter-btn" onclick="showAssetNameFilterModal('active')" title="Filter by Asset Name">
                                    <i class='bx bx-filter'></i>
                                    </button>
                            </th>
                            <th>Category
                                    <button class="filter-btn" onclick="showAssetStatusFilterModal('active')" title="Filter by Category">
                                    <i class='bx bx-filter'></i>
                                    </button>
                            </th>
                            <th>Quantity</th>
                            <th>Current Status</th>
                            <th>Date Registered</th>
                            <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody id="assets-table-body">
                                <?php 
                                if ($resultActive && $resultActive->num_rows > 0) { 
                                    while ($row = $resultActive->fetch_assoc()) { 
                                        echo "<tr> 
                                                <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>  
                                                <td>" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "</td> 
                                                <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                                                <td>
                                                   <a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "',  '" . htmlspecialchars($row['a_specs'] ?? '', ENT_QUOTES) . "', '" . htmlspecialchars($row['a_cycle'] ?? '', ENT_QUOTES) . "','" . htmlspecialchars($row['a_condition'] ?? '', ENT_QUOTES) . "')\" title='View'><i class='fas fa-eye'></i></a>
                                                   <a class='edit-btn' onclick=\"showEditAssetModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                                                   <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>
                                                </td></tr>"; 
                                    } 
                                } else { 
                                    echo "<tr><td colspan='8'>No active assets found.</td></tr>"; 
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
                                    <th>Asset Ref No</th>
                                    <th>
                                        Asset Name
                                        <button class="filter-btn" onclick="showAssetNameFilterModal('archive')" title="Filter by Asset Name">
                                            <i class='bx bx-filter'></i>
                                        </button>
                                    </th>
                                    <th>
                                        Category
                                        <button class="filter-btn" onclick="showAssetStatusFilterModal('archive')" title="Filter by Category">
                                            <i class='bx bx-filter'></i>
                                        </button>
                                    </th>
                                    <th>Quantity</th>
                                    <th>Current Status</th>
                                    <th>Date Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="archived-assets-table-body">
                                <?php 
                                if ($resultArchived && $resultArchived->num_rows > 0) { 
                                    while ($row = $resultArchived->fetch_assoc()) { 
                                        echo "<tr> 
                                                <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>  
                                                <td>" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['a_quantity'], ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                                                <td>
                                                    <a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "',  '" . htmlspecialchars($row['a_specs'] ?? '', ENT_QUOTES) . "', '" . htmlspecialchars($row['a_cycle'] ?? '', ENT_QUOTES) . "','" . htmlspecialchars($row['a_condition'] ?? '', ENT_QUOTES) . "')\" title='View'><i class='fas fa-eye'></i></a>
                                                    <a class='unarchive-btn' onclick=\"showUnarchiveModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                                                    <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                                                </td></tr>"; 
                                    } 
                                } else { 
                                    echo "<tr><td colspan='8'>No archived assets found.</td></tr>"; 
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

    <!-- In the borrowed assets table section (around line 1090) -->
    <div id="assets-borrowed" class="tab-content" style="display: none;">
    <table id="borrowed-assets-table">
        <thead>
            <tr>
                <th>Asset Ref No</th>
                <th>Asset Name
                    <button class="filter-btn" onclick="showBorrowedAssetNameFilterModal('borrowed')" title="Filter by Asset Name">
                        <i class='bx bx-filter'></i>
                    </button>
                </th>
                <th>Technician Name
                    <button class="filter-btn" onclick="showBorrowedTechNameFilterModal('borrowed')" title="Filter by Technician Name">
                        <i class='bx bx-filter'></i>
                    </button>
                </th>
                <th>Technician Id</th>
                <th>Asset Serial No</th>
                <th>Date Borrowed</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="borrowed-assets-table-body">
            <?php
            $borrowedPage = isset($_GET['borrowed_page']) ? (int)$_GET['borrowed_page'] : 1;
            $borrowedOffset = ($borrowedPage - 1) * $limit;
            $borrowedCountQuery = "SELECT COUNT(*) as total 
                       FROM tbl_asset_status s
                       INNER JOIN tbl_assets a ON s.a_ref_no = a.a_ref_no 
                       WHERE s.a_status = 'Borrowed' AND a.a_current_status != 'Archived'";
            $borrowedCountResult = $conn->query($borrowedCountQuery);
            $totalBorrowed = $borrowedCountResult ? $borrowedCountResult->fetch_assoc()['total'] : 0;
            $totalBorrowedPages = ceil($totalBorrowed / $limit);

            $sqlBorrowed = "SELECT s.a_ref_no, s.a_name, s.tech_name, s.tech_id, s.a_serial_no, s.a_date, a.a_specs, a.a_cycle, a.a_condition 
                FROM tbl_asset_status s
                INNER JOIN tbl_assets a ON s.a_ref_no = a.a_ref_no 
                WHERE s.a_status = 'Borrowed' AND a.a_current_status != 'Archived' 
                ORDER BY s.a_ref_no ASC 
                LIMIT ?, ?";
            $stmtBorrowed = $conn->prepare($sqlBorrowed);
            $stmtBorrowed->bind_param("ii", $borrowedOffset, $limit);
            $stmtBorrowed->execute();
            $resultBorrowed = $stmtBorrowed->get_result();

            if ($resultBorrowed && $resultBorrowed->num_rows > 0) {
                while ($row = $resultBorrowed->fetch_assoc()) {
                    echo "<tr>
                            <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . htmlspecialchars($row['tech_name'], ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . htmlspecialchars($row['tech_id'], ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . ($row['a_serial_no'] ? htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8') : '') . "</td>
                            <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td>
                            <td>
                                <a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', 'Borrowed', 'Borrowed', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['tech_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['tech_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_specs'] ?? '', ENT_QUOTES) . "', '" . htmlspecialchars($row['a_cycle'] ?? '', ENT_QUOTES) . "','" . htmlspecialchars($row['a_condition'] ?? '', ENT_QUOTES) . "')\"
                                <a class='borrowdelete-btn' onclick=\"showBorrowedDeleteModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                            </td></tr>";
                }   
            } else {
                echo "<tr><td colspan='7'>No borrowed assets found.</td></tr>";
            }
            $stmtBorrowed->close();
            ?>
        </tbody>
    </table>
    <div class="pagination" id="borrowed-pagination">
        <?php if ($borrowedPage > 1): ?>
            <a href="javascript:searchAssets(<?php echo $borrowedPage - 1; ?>, 'borrowed')" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
        <?php else: ?>
            <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
        <?php endif; ?>
        <span class="current-page">Page <?php echo $borrowedPage; ?> of <?php echo $totalBorrowedPages; ?></span>
        <?php if ($borrowedPage < $totalBorrowedPages): ?>
            <a href="javascript:searchAssets(<?php echo $borrowedPage + 1; ?>, 'borrowed')" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
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

<!-- Add Asset Modal - WITH INTERNAL SCROLL -->
<div id="addAssetModal" class="modal">
    <div class="modal-content scrollable-modal">
        <!-- Fixed Header -->
        <div class="modal-header">
            <h2>Add New Asset</h2>
        </div>

        <!-- Scrollable Body -->
        <div class="modal-body">
            <form method="POST" id="addAssetForm" class="modal-form">
                <input type="hidden" name="add_asset" value="1">
                <input type="hidden" name="ajax" value="true">

                <div class="form-group">
                    <label for="asset_name">Asset Name:</label>
                    <input type="text" id="asset_name" name="asset_name" placeholder="Asset Name" 
                           value="<?php echo isset($_SESSION['add_form_data']['asset_name']) ? htmlspecialchars($_SESSION['add_form_data']['asset_name']) : ''; ?>" required>
                    <span class="error" id="addAssetForm_asset_name_error"><?php echo isset($_SESSION['add_errors']['asset_name']) ? htmlspecialchars($_SESSION['add_errors']['asset_name']) : ''; ?></span>
                </div>

                <div class="form-group">
                    <label for="asset_quantity">Number of Assets to Register:</label>
                    <input type="text" id="asset_quantity" name="asset_quantity" placeholder="e.g. 5" 
                           value="<?php echo isset($_SESSION['add_form_data']['asset_quantity']) ? htmlspecialchars($_SESSION['add_form_data']['asset_quantity']) : ''; ?>" required>
                    <span class="error" id="addAssetForm_asset_quantity_error"><?php echo isset($_SESSION['add_errors']['asset_quantity']) ? htmlspecialchars($_SESSION['add_errors']['asset_quantity']) : ''; ?></span>
                </div>

                <div class="form-group">
                    <label for="serial_no">Serial Number:</label>
                    <input type="text" id="serial_no" name="serial_no" placeholder="Serial Number (optional)" 
                           value="<?php echo isset($_SESSION['add_form_data']['serial_no']) ? htmlspecialchars($_SESSION['add_form_data']['serial_no']) : ''; ?>">
                    <span class="error" id="addAssetForm_serial_no_error"><?php echo isset($_SESSION['add_errors']['serial_no']) ? htmlspecialchars($_SESSION['add_errors']['serial_no']) : ''; ?></span>
                </div>

                <div class="form-group">
                    <label for="asset_specs">Asset Specification:</label>
                    <textarea id="asset_specs" name="asset_specs" rows="3" 
                              placeholder="e.g. Wireless Optical Mouse with USB Receiver"><?php echo isset($_SESSION['add_form_data']['asset_specs']) ? htmlspecialchars($_SESSION['add_form_data']['asset_specs']) : ''; ?></textarea>
                    <span class="error" id="addAssetForm_asset_specs_error"><?php echo isset($_SESSION['add_errors']['asset_specs']) ? htmlspecialchars($_SESSION['add_errors']['asset_specs']) : ''; ?></span>
                </div>

                <div class="form-group">
                    <label for="asset_cycle">Asset Lifecycle:</label>
                    <select id="asset_cycle" name="asset_cycle" required>
                        <option value="">Select Lifecycle</option>
                        <option value="Reusable" <?php echo (isset($_SESSION['add_form_data']['asset_cycle']) && $_SESSION['add_form_data']['asset_cycle'] === 'Reusable') ? 'selected' : ''; ?>>Reusable</option>
                        <option value="Non-reusable" <?php echo (isset($_SESSION['add_form_data']['asset_cycle']) && $_SESSION['add_form_data']['asset_cycle'] === 'Non-reusable') ? 'selected' : ''; ?>>Non-reusable</option>
                    </select>
                    <span class="error" id="addAssetForm_asset_cycle_error"><?php echo isset($_SESSION['add_errors']['asset_cycle']) ? htmlspecialchars($_SESSION['add_errors']['asset_cycle']) : ''; ?></span>
                </div>

                <div class="form-group">
                    <label for="asset_condition">Asset Condition:</label>
                    <select id="asset_condition" name="asset_condition" required>
                        <option value="">Select Condition</option>
                        <option value="Brand New" <?php echo (isset($_SESSION['add_form_data']['asset_condition']) && $_SESSION['add_form_data']['asset_condition'] === 'Brand New') ? 'selected' : ''; ?>>Brand New</option>
                        <option value="Good Condition" <?php echo (isset($_SESSION['add_form_data']['asset_condition']) && $_SESSION['add_form_data']['asset_condition'] === 'Good Condition') ? 'selected' : ''; ?>>Good Condition</option>
                        <option value="Slightly Used" <?php echo (isset($_SESSION['add_form_data']['asset_condition']) && $_SESSION['add_form_data']['asset_condition'] === 'Slightly Used') ? 'selected' : ''; ?>>Slightly Used</option>
                        <option value="For Repair" <?php echo (isset($_SESSION['add_form_data']['asset_condition']) && $_SESSION['add_form_data']['asset_condition'] === 'For Repair') ? 'selected' : ''; ?>>For Repair</option>
                        <option value="Damaged" <?php echo (isset($_SESSION['add_form_data']['asset_condition']) && $_SESSION['add_form_data']['asset_condition'] === 'Damaged') ? 'selected' : ''; ?>>Damaged</option>
                    </select>
                    <span class="error" id="addAssetForm_asset_condition_error"><?php echo isset($_SESSION['add_errors']['asset_condition']) ? htmlspecialchars($_SESSION['add_errors']['asset_condition']) : ''; ?></span>
                </div>

                <div class="form-group">
                    <label for="asset_status">Asset Category:</label>
                    <select id="asset_status" name="asset_status" required>
                        <option value="">Select Category</option>
                        <option value="Borrowing" <?php echo (isset($_SESSION['add_form_data']['asset_status']) && $_SESSION['add_form_data']['asset_status'] === 'Borrowing') ? 'selected' : ''; ?>>Borrowing</option>
                        <option value="Deployment" <?php echo (isset($_SESSION['add_form_data']['asset_status']) && $_SESSION['add_form_data']['asset_status'] === 'Deployment') ? 'selected' : ''; ?>>Deployment</option>
                    </select>
                    <span class="error" id="addAssetForm_asset_status_error"><?php echo isset($_SESSION['add_errors']['asset_status']) ? htmlspecialchars($_SESSION['add_errors']['asset_status']) : ''; ?></span>
                </div>

                <div class="form-group">
                    <label for="date">Date Registered:</label>
                    <input type="date" id="date" name="date" 
                           value="<?php echo isset($_SESSION['add_form_data']['date']) ? htmlspecialchars($_SESSION['add_form_data']['date']) : date('Y-m-d'); ?>" required>
                    <span class="error" id="addAssetForm_date_error"><?php echo isset($_SESSION['add_errors']['date']) ? htmlspecialchars($_SESSION['add_errors']['date']) : ''; ?></span>
                </div>

                <div class="form-group">
                    <span class="error" id="addAssetForm_general_error" style="font-weight:600;">
                        <?php echo isset($_SESSION['add_errors']['general']) ? htmlspecialchars($_SESSION['add_errors']['general']) : ''; ?>
                    </span>
                </div>
            </form>
        </div>
        <!-- End .modal-body -->

        <!-- Fixed Footer -->
        <div class="modal-footer">
            <button type="button" class="modal-btn cancel" onclick="closeModal('addAssetModal')">Cancel</button>
            <button type="button" id="addAssetSubmitBtn" class="modal-btn confirm">Add Asset</button>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="borrowedDeleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Delete Borrowed Asset</h2>
        </div>
        <form id="borrowedDeleteForm" class="modal-form">
            <input type="hidden" id="borrowed_delete_ref_no" name="delete_ref_no">
            <p id="borrowed_delete_message">Are you sure you want to permanently delete "<span id="borrowed_delete_asset_name"></span>"? This action cannot be undone.</p>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('borrowedDeleteModal')">Cancel</button>
                <button type="button" class="modal-btn confirm" onclick="confirmBorrowedDelete()">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Borrowed Asset Name Filter Modal -->
<div id="borrowedAssetNameFilterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Filter by Asset Name</h2>
        </div>
        <form id="borrowedAssetNameFilterForm" class="modal-form">
            <input type="hidden" name="tab" id="borrowedAssetNameFilterTab" value="borrowed">
            <label for="borrowed_asset_name_filter">Select Asset Name</label>
            <select name="asset_name_filter" id="borrowed_asset_name_filter">
                <option value="">All Assets</option>
                <?php
                $assetNameQuery = "SELECT DISTINCT a_name FROM tbl_asset_status WHERE a_status = 'Borrowed' ORDER BY a_name";
                $assetNameResult = $conn->query($assetNameQuery);
                if ($assetNameResult && $assetNameResult->num_rows > 0) {
                    while ($row = $assetNameResult->fetch_assoc()) {
                        echo "<option value='" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                    }
                }
                $assetNameResult->close();
                ?>
            </select>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('borrowedAssetNameFilterModal')">Cancel</button>
                <button type="button" class="modal-btn confirm" onclick="applyBorrowedAssetNameFilter()">Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Borrowed Technician Name Filter Modal -->
<div id="borrowedTechNameFilterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Filter by Technician Name</h2>
        </div>
        <form id="borrowedTechNameFilterForm" class="modal-form">
            <input type="hidden" name="tab" id="borrowedTechNameFilterTab" value="borrowed">
            <label for="borrowed_tech_name_filter">Select Technician Name</label>
            <select name="tech_name_filter" id="borrowed_tech_name_filter">
                <option value="">All Technicians</option>
                <?php
                $techNameQuery = "SELECT DISTINCT tech_name FROM tbl_asset_status WHERE a_status = 'Borrowed' AND tech_name IS NOT NULL ORDER BY tech_name";
                $techNameResult = $conn->query($techNameQuery);
                if ($techNameResult && $techNameResult->num_rows > 0) {
                    while ($row = $techNameResult->fetch_assoc()) {
                        echo "<option value='" . htmlspecialchars($row['tech_name'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['tech_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                    }
                }
                $techNameResult->close();
                ?>
            </select>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('borrowedTechNameFilterModal')">Cancel</button>
                <button type="button" class="modal-btn confirm" onclick="applyBorrowedTechNameFilter()">Apply Filter</button>
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
            <input type="hidden" name="a_ref_no" id="edit_a_ref_no">
            <div class="form-group">
                <label for="edit_asset_name">Asset Name:</label>
                <input type="text" id="edit_asset_name" name="asset_name" required>
                <span class="error" id="edit_asset_name_error"></span>
            </div>
            <div class="form-group">
                <label for="edit_serial_no">Serial Number:</label>
                <input type="text" id="edit_serial_no" name="serial_no" placeholder="Serial Number (optional)">
                <span class="error" id="edit_serial_no_error"></span>
            </div>
            <div class="form-group">
                <label for="edit_asset_status">Category:</label>
                <select id="edit_asset_status" name="asset_status" required>
                    <option value="Borrowing">Borrowing</option>
                    <option value="Deployment">Deployment</option>
                    <option value="Archived">Archived</option>
                </select>
                <span class="error" id="edit_asset_status_error"></span>
            </div>
            <div class="form-group">
                <label for="edit_current_status">Current Status:</label>
                <select id="edit_current_status" name="current_status" required>
                    <option value="Available">Available</option>
                    <option value="Borrowed">Borrowed</option>
                    <option value="Deployed">Deployed</option>
                    <option value="Archived">Archived</option>
                </select>
                <span class="error" id="edit_current_status_error"></span>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('editAssetModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Update</button>
            </div>
        </form>
    </div>
  </div>

        <!-- Borrow Asset Modal - Updated with error spans -->
       <div id="borrowAssetModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Borrow Asset</h2>
        </div>
        <form method="POST" id="borrowAssetForm" class="modal-form">
            <input type="hidden" name="borrow_asset" value="1">
            <input type="hidden" name="ajax" value="true">
            <div class="form-group">
                <label for="asset_ref_no">Asset</label>
                <select id="asset_ref_no" name="asset_ref_no[]" multiple required>
                    <option value="">Select Asset(s)</option>
                    <?php
                    $sqlAssets = "SELECT a_ref_no, a_name, a_serial_no FROM tbl_assets WHERE a_current_status = 'Available' AND a_status = 'Borrowing' ORDER BY a_name, a_ref_no";
                    $resultAssets = $conn->query($sqlAssets);
                    while ($row = $resultAssets->fetch_assoc()):
                        $serial_no = $row['a_serial_no'] ? htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8') : 'None';
                        $selected = isset($_SESSION['borrow_form_data']['asset_ref_no']) && in_array($row['a_ref_no'], (array)$_SESSION['borrow_form_data']['asset_ref_no']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . " (Ref: " . $row['a_ref_no'] . ", Serial: " . $serial_no . ")"; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <span class="error" id="borrowAssetForm_asset_ref_no_error"><?php echo isset($_SESSION['borrow_errors']['asset_ref_no']) ? htmlspecialchars($_SESSION['borrow_errors']['asset_ref_no']) : ''; ?></span>
            </div>
            <div class="form-group">
                <label for="tech_name">Technician Name</label>
                <input type="text" id="tech_name" name="tech_name" placeholder="Technician Name" value="<?php echo isset($_SESSION['borrow_form_data']['tech_name']) ? htmlspecialchars($_SESSION['borrow_form_data']['tech_name']) : ''; ?>" required>
                <span class="error" id="borrowAssetForm_tech_name_error"><?php echo isset($_SESSION['borrow_errors']['tech_name']) ? htmlspecialchars($_SESSION['borrow_errors']['tech_name']) : ''; ?></span>
            </div>
            <div class="form-group">
                <label for="tech_id">Technician ID</label>
                <input type="text" id="tech_id" name="tech_id" placeholder="Technician ID" value="<?php echo isset($_SESSION['borrow_form_data']['tech_id']) ? htmlspecialchars($_SESSION['borrow_form_data']['tech_id']) : ''; ?>" required>
                <span class="error" id="borrowAssetForm_tech_id_error"><?php echo isset($_SESSION['borrow_errors']['tech_id']) ? htmlspecialchars($_SESSION['borrow_errors']['tech_id']) : ''; ?></span>
            </div>
            <div class="form-group">
                <label for="date">Date Borrowed</label>
                <input type="date" id="date" name="date" value="<?php echo isset($_SESSION['borrow_form_data']['date']) ? htmlspecialchars($_SESSION['borrow_form_data']['date']) : date('Y-m-d'); ?>" required>
                <span class="error" id="borrowAssetForm_date_error"><?php echo isset($_SESSION['borrow_errors']['date']) ? htmlspecialchars($_SESSION['borrow_errors']['date']) : ''; ?></span>
            </div>
            <div class="form-group">
                <span class="error" id="borrowAssetForm_general_error"><?php echo isset($_SESSION['borrow_errors']['general']) ? htmlspecialchars($_SESSION['borrow_errors']['general']) : ''; ?></span>
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
                <label for="deploy_asset_ref_no">Asset</label>
                <select id="deploy_asset_ref_no" name="asset_ref_no[]" multiple required>
                    <option value="">Select Asset(s)</option>
                    <?php
                    $sqlDeployAssets = "SELECT a_ref_no, a_name, a_serial_no FROM tbl_assets WHERE a_current_status = 'Available' AND a_status = 'Deployment' ORDER BY a_name, a_ref_no";
                    $resultDeployAssets = $conn->query($sqlDeployAssets);
                    while ($row = $resultDeployAssets->fetch_assoc()):
                        $serial_no = $row['a_serial_no'] ? htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8') : 'None';
                        $selected = isset($_SESSION['deploy_form_data']['asset_ref_no']) && in_array($row['a_ref_no'], (array)$_SESSION['deploy_form_data']['asset_ref_no']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . " (Ref: " . $row['a_ref_no'] . ", Serial: " . $serial_no . ")"; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <span class="error" id="deployAssetForm_asset_ref_no_error"><?php echo isset($_SESSION['deploy_errors']['asset_ref_no']) ? htmlspecialchars($_SESSION['deploy_errors']['asset_ref_no']) : ''; ?></span>
            </div>
            <div class="form-group">
                <label for="deploy_tech_name">Technician Name</label>
                <input type="text" id="deploy_tech_name" name="tech_name" placeholder="Technician Name" value="<?php echo isset($_SESSION['deploy_form_data']['tech_name']) ? htmlspecialchars($_SESSION['deploy_form_data']['tech_name']) : ''; ?>" required>
                <span class="error" id="deployAssetForm_tech_name_error"><?php echo isset($_SESSION['deploy_errors']['tech_name']) ? htmlspecialchars($_SESSION['deploy_errors']['tech_name']) : ''; ?></span>
            </div>
            <div class="form-group">
                <label for="deploy_tech_id">Technician ID</label>
                <input type="text" id="deploy_tech_id" name="tech_id" placeholder="Technician ID" value="<?php echo isset($_SESSION['deploy_form_data']['tech_id']) ? htmlspecialchars($_SESSION['deploy_form_data']['tech_id']) : ''; ?>" required>
                <span class="error" id="deployAssetForm_tech_id_error"><?php echo isset($_SESSION['deploy_errors']['tech_id']) ? htmlspecialchars($_SESSION['deploy_errors']['tech_id']) : ''; ?></span>
            </div>
            <div class="form-group">
                <label for="deploy_date">Date Deployed</label>
                <input type="date" id="deploy_date" name="date" value="<?php echo isset($_SESSION['deploy_form_data']['date']) ? htmlspecialchars($_SESSION['deploy_form_data']['date']) : date('Y-m-d'); ?>" required>
                <span class="error" id="deployAssetForm_date_error"><?php echo isset($_SESSION['deploy_errors']['date']) ? htmlspecialchars($_SESSION['deploy_errors']['date']) : ''; ?></span>
            </div>
            <div class="form-group">
                <span class="error" id="deployAssetForm_general_error"><?php echo isset($_SESSION['deploy_errors']['general']) ? htmlspecialchars($_SESSION['deploy_errors']['general']) : ''; ?></span>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('deployAssetModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Deploy Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Return Asset Modal -->
<div id="returnAssetModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Return Asset</h2>
        </div>
        <form method="POST" id="returnAssetForm" class="modal-form">
            <input type="hidden" name="return_asset" value="1">
            <input type="hidden" name="ajax" value="true">
            <div class="form-group">
                <label for="return_asset_ref_no">Asset</label>
                <select id="return_asset_ref_no" name="asset_ref_no[]" multiple required>
                    <option value="">Select Asset(s)</option>
                    <?php
                    $sqlReturnAssets = "SELECT a_ref_no, a_name, a_serial_no, tech_name, a_status FROM tbl_asset_status WHERE a_status = 'Borrowed' ORDER BY a_name, a_ref_no";
                    $resultReturnAssets = $conn->query($sqlReturnAssets);
                    while ($row = $resultReturnAssets->fetch_assoc()):
                        $serial_no = $row['a_serial_no'] ? htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8') : 'None';
                        $status = htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8');
                    ?>
                        <option value="<?php echo htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . " (Ref: " . $row['a_ref_no'] . ", Serial: " . $serial_no . ", Status: " . $status . ")"; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <span class="error" id="returnAssetForm_asset_ref_no_error"></span>
            </div>
            <div class="form-group">
                <label for="return_date">Date Returned</label>
                <input type="date" id="return_date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                <span class="error" id="returnAssetForm_date_error"></span>
            </div>
            <div class="form-group">
                <span class="error" id="returnAssetForm_general_error"></span>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('returnAssetModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Return Asset</button>
            </div>
        </form>
    </div>
</div>
       
        <div id="archiveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Archive Asset</h2>
            </div>
            <p>Are you sure you want to archive "<span id="archiveAssetName"></span>"?</p>
            <form method="POST" id="archiveForm">
                <input type="hidden" name="a_ref_no" id="archiveAssetRefNo">
                <input type="hidden" name="archive_asset" value="1">
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('archiveModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm">Archive</button>
                </div>
            </form>
        </div>
    </div>

            <div id="unarchiveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Unarchive Asset</h2>
            </div>
            <p>Are you sure you want to unarchive "<span id="unarchiveAssetName"></span>"?</p>
            <form method="POST" id="unarchiveForm">
                <input type="hidden" name="a_ref_no" id="unarchiveAssetRefNo">
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
                <input type="hidden" name="a_ref_no" id="deleteAssetRefNo">
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
                <?php
                // Dynamically select the asset names array based on the tab
                $assetNames = isset($_GET['tab']) && $_GET['tab'] === 'archive' ? $uniqueArchivedAssetNames : $uniqueActiveAssetNames;
                foreach ($assetNames as $name):
                ?>
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
                        <h2>Filter by Category</h2>
                    </div>
                    <form id="assetStatusFilterForm" class="modal-form">
                        <input type="hidden" name="tab" id="assetStatusFilterTab">
                        <label for="asset_status_filter">Select Category</label>
                        <select name="asset_status_filter" id="asset_status_filter">
                            <option value="">All Categories</option>
                            <?php foreach ($uniqueAssetStatuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="modal-footer">
                            <button type="button" class="modal-btn cancel" onclick="closeModal('assetStatusFilterModal')">Cancel</button> <button type="button" class="modal-btn confirm" onclick="applyAssetStatusFilter()">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>

    // Prevent form submission on Enter key
document.getElementById('addAssetForm').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('addAssetSubmitBtn').click();
    }
});

    document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.getElementById('success-message');
    if (successMessage) {
        setTimeout(() => {
            successMessage.classList.add('alert-hidden');
        }, 6000); // 6 seconds
    }
});

    window.onload = function() {
    <?php if (isset($_SESSION['open_modal'])): ?>
        <?php if ($_SESSION['open_modal'] === 'addAsset'): ?>
            showAddAssetModal();
        <?php elseif ($_SESSION['open_modal'] === 'borrowAsset'): ?>
            showBorrowAssetModal();
        <?php elseif ($_SESSION['open_modal'] === 'deployAsset'): ?>
            showDeployAssetModal();
        <?php endif; ?>
    <?php endif; ?>
    };

function displayModalErrors(formId, errors) {
    const errorSpans = document.querySelectorAll(`#${formId} .error`);
    errorSpans.forEach(span => span.textContent = '');

    for (const field in errors) {
        const errorSpan = document.getElementById(`${formId}_${field}_error`);
        if (errorSpan) {
            errorSpan.textContent = errors[field];
        }
    }
}

// Function to clear all error messages in a form
function clearModalErrors(formId) {
    const errorSpans = document.querySelectorAll(`#${formId} .error`);
    errorSpans.forEach(span => span.textContent = '');
}


// Handle Add Asset Form Submission
document.getElementById('addAssetSubmitBtn').addEventListener('click', function(e) {
    e.preventDefault();
    const form = document.getElementById('addAssetForm');
    const formData = new FormData(form);
    formData.append('ajax', 'true');

    // Client-side validation
    const errors = {};
    const assetName = document.getElementById('asset_name').value.trim();
    const quantity = document.getElementById('asset_quantity').value.trim();
    const serial = document.getElementById('serial_no').value.trim();
    const specs = document.getElementById('asset_specs').value.trim();
    const cycle = document.getElementById('asset_cycle').value;
    const condition = document.getElementById('asset_condition').value;
    const assetStatus = document.getElementById('asset_status').value;
    const date = document.getElementById('date').value;

    // Reset all errors first
    clearModalErrors('addAssetForm');

    if (!assetName || !/^[a-zA-Z\s-]+$/.test(assetName)) {
        errors['asset_name'] = 'Asset Name is required and should not contain numbers.';
    }
    if (!['Borrowing', 'Deployment'].includes(assetStatus)) {
        errors['asset_status'] = 'Invalid asset status.';
    }
    if (!quantity || isNaN(quantity) || quantity < 0) {
        errors['asset_quantity'] = 'Quantity must be a non-negative number.';
    }
    if (serial && parseInt(quantity) > 1) {
        errors['serial_no'] = 'Cannot assign the same serial number to multiple assets.';
    }
    if (specs && !/^[a-zA-Z\s\.,\-()'"&]+$/.test(specs)) {
        errors['asset_specs'] = 'Asset Specification can only contain letters, spaces, and basic punctuation. Numbers are not allowed.';
    }
    if (!cycle) {
        errors['asset_cycle'] = 'Please select Asset Lifecycle.';
    }
    if (!condition) {
        errors['asset_condition'] = 'Please select Asset Condition.';
    }
    if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        errors['date'] = 'Invalid date format.';
    }

    if (Object.keys(errors).length > 0) {
        displayModalErrors('addAssetForm', errors);
        return;
    }

    // Show loading state
    const submitBtn = document.getElementById('addAssetSubmitBtn');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Adding...';
    submitBtn.disabled = true;

    // AJAX submission
    fetch('assetsT.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.log('Response is not JSON:', text);
                throw new Error('Server returned an invalid response');
            }
        });
    })
    .then(data => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            showSuccessMessage(data.message);
            closeModal('addAssetModal');
            // Refresh the active tab
            const activeTab = document.querySelector('.tab-btn.active').getAttribute('onclick').match(/'([^']+)'/)[1];
            searchAssets(1, activeTab);
            // Also reset the form
            form.reset();
        } else {
            console.error('Server-side errors:', data.errors);
            if (data.errors) {
                displayModalErrors('addAssetForm', data.errors);
            } else {
                showErrorMessage(data.message || 'Failed to add asset. Please try again.');
            }
        }
    })
    .catch(error => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        console.error('Fetch error:', error);
        showErrorMessage('An error occurred while processing your request: ' + error.message);
    });
});


// Validate Borrow Asset Form
document.getElementById('borrowAssetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    formData.append('ajax', 'true');

    // Client-side validation
    const errors = {};
    const assetRefNos = Array.from(document.getElementById('asset_ref_no').selectedOptions).map(option => option.value);
    const techName = document.getElementById('tech_name').value.trim();
    const techId = document.getElementById('tech_id').value.trim();
    const date = document.getElementById('date').value;

    if (assetRefNos.length === 0) {
        errors['asset_ref_no'] = 'Please select at least one asset.';
    }
    if (!techName) {
        errors['tech_name'] = 'Technician Name is required.';
    } else if (!/^[a-zA-Z\s-]+$/.test(techName)) {
        errors['tech_name'] = 'Technician name should not contain numbers.';
    }
    if (!techId) {
        errors['tech_id'] = 'Technician ID is required.';
    } else if (!/^[0-9]+$/.test(techId)) {
        errors['tech_id'] = 'Technician ID should not contain letters.';
    }

    if (Object.keys(errors).length > 0) {
        displayModalErrors('borrowAssetForm', errors);
        return;
    }

    // Clear previous errors
    clearModalErrors('borrowAssetForm');

    // AJAX submission
    fetch('assetsT.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Log the raw response text for debugging
        response.text().then(text => {
            console.log('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON response: ' + text);
            }
        }).then(data => {
            if (data.success) {
                showSuccessMessage(data.message);
                closeModal('borrowAssetModal');
                searchAssets(1, 'active'); // Refresh the active assets table
            } else {
                console.error('Server-side errors:', data.errors);
                displayModalErrors('borrowAssetForm', data.errors || { general: 'Failed to borrow asset. Please check the form.' });
            }
        });
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showErrorMessage(`An error occurred while borrowing the asset: ${error.message}`);
    });
});

// Validate Deploy Asset Form
document.getElementById('deployAssetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    formData.append('ajax', 'true');

    // Client-side validation
    const errors = {};
    const assetRefNos = Array.from(document.getElementById('deploy_asset_ref_no').selectedOptions).map(option => option.value);
    const techName = document.getElementById('deploy_tech_name').value.trim();
    const techId = document.getElementById('deploy_tech_id').value.trim();
    const date = document.getElementById('deploy_date').value;

    if (assetRefNos.length === 0) {
        errors['asset_ref_no'] = 'Please select at least one asset.';
    }
    if (!techName) {
        errors['tech_name'] = 'Technician Name is required.';
    } else if (!/^[a-zA-Z\s-]+$/.test(techName)) {
        errors['tech_name'] = 'Technician name should not contain numbers.';
    }
    if (!techId) {
        errors['tech_id'] = 'Technician ID is required.';
    } else if (!/^[0-9]+$/.test(techId)) {
        errors['tech_id'] = 'Technician ID should not contain letters.';
    }
    if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        errors['date'] = 'Invalid date format.';
    }

    if (Object.keys(errors).length > 0) {
        displayModalErrors('deployAssetForm', errors);
        return;
    }

    // Clear previous errors
    clearModalErrors('deployAssetForm');

    // AJAX submission
    fetch('assetsT.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text().then(text => {
        console.log('Raw response:', text);
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Invalid JSON response: ' + text);
        }
    }))
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            closeModal('deployAssetModal');
            searchAssets(1, 'active'); // Refresh the active assets table
        } else {
            console.error('Server-side errors:', data.errors);
            displayModalErrors('deployAssetForm', data.errors || { general: 'Failed to deploy asset. Please check the form.' });
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showErrorMessage(`An error occurred while deploying the asset: ${error.message}`);
    });
});


function showReturnAssetModal() {
    document.getElementById('returnAssetForm').reset();
    clearModalErrors('returnAssetForm');
    document.getElementById('returnAssetForm').querySelector('#return_date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('return_asset_ref_no').selectedIndex = -1;
    document.getElementById('returnAssetModal').style.display = 'block';
    <?php if (isset($_SESSION['return_errors'])): ?>
        displayModalErrors('returnAssetForm', <?php echo json_encode($_SESSION['return_errors']); ?>);
        <?php if (isset($_SESSION['return_form_data']['asset_ref_no'])): ?>
            const assetRefNos = <?php echo json_encode((array)$_SESSION['return_form_data']['asset_ref_no']); ?>;
            const select = document.getElementById('return_asset_ref_no');
            Array.from(select.options).forEach(option => {
                option.selected = assetRefNos.includes(option.value);
            });
        <?php endif; ?>
    <?php endif; ?>
}

document.getElementById('returnAssetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    formData.append('ajax', 'true');

    // Client-side validation
    const errors = {};
    const assetRefNos = Array.from(document.getElementById('return_asset_ref_no').selectedOptions).map(option => option.value);
    const date = document.getElementById('return_date').value;

    if (assetRefNos.length === 0) {
        errors['asset_ref_no'] = 'Please select at least one asset.';
    }
    if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        errors['date'] = 'Invalid date format.';
    }

    if (Object.keys(errors).length > 0) {
        displayModalErrors('returnAssetForm', errors);
        return;
    }

    clearModalErrors('returnAssetForm');

    // AJAX submission
    fetch('assetsT.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text().then(text => {
        console.log('Raw response:', text);
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Invalid JSON response: ' + text);
        }
    }))
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            closeModal('returnAssetModal');
            searchAssets(1, 'borrowed'); // Refresh the borrowed assets table
            searchAssets(1, 'active'); // Refresh the active assets table
        } else {
            console.error('Server-side errors:', data.errors);
            displayModalErrors('returnAssetForm', data.errors || { general: 'Failed to return asset. Please check the form.' });
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showErrorMessage(`An error occurred while returning the asset: ${error.message}`);
    });
});


function clearModalErrors(formId) { document.querySelectorAll(`#${formId} .error`).forEach(span => span.textContent = ''); }

function displayModalErrors(formId, errors) {
    clearModalErrors(formId);
    for (const [field, error] of Object.entries(errors)) {
        const errorSpan = document.getElementById(`${formId}_${field}_error`);
        if (errorSpan) errorSpan.textContent = error;
    }
}


function showSuccessMessage(message) {
    const alertContainer = document.querySelector('.alert-container') || document.createElement('div');
    if (!alertContainer.classList.contains('alert-container')) {
        alertContainer.className = 'alert-container';
        document.querySelector('.container').prepend(alertContainer);
    }
    const successAlert = document.createElement('div');
    successAlert.className = 'alert alert-success';
    successAlert.textContent = message;
    alertContainer.appendChild(successAlert);
    setTimeout(() => {
        successAlert.classList.add('alert-hidden');
        setTimeout(() => successAlert.remove(), 500);
    }, 4000); 
}

function showErrorMessage(message) {
    const alertContainer = document.querySelector('.alert-container') || document.createElement('div');
    if (!alertContainer.classList.contains('alert-container')) {
        alertContainer.className = 'alert-container';
        document.querySelector('.container').prepend(alertContainer);
    }
    const errorAlert = document.createElement('div');
    errorAlert.className = 'alert alert-error';
    errorAlert.textContent = message;
    alertContainer.appendChild(errorAlert);
    setTimeout(() => {
        errorAlert.classList.add('alert-hidden');
        setTimeout(() => errorAlert.remove(), 500);
    }, 4000);
}

function showAddAssetModal() {
    const modal = document.getElementById('addAssetModal');
    const form = document.getElementById('addAssetForm');
    
    // Reset the form
    form.reset();
    
    // Set default date to today
    const dateField = document.getElementById('date');
    if (dateField) {
        const today = new Date().toISOString().split('T')[0];
        dateField.value = today;
    }
    
    // Reset lifecycle and condition dropdowns to default
    document.getElementById('asset_cycle').value = '';
    document.getElementById('asset_condition').value = '';
    
    // Clear all errors
    clearModalErrors('addAssetForm');
    
    // Reset submit button state
    const submitBtn = document.getElementById('addAssetSubmitBtn');
    submitBtn.textContent = 'Add Asset';
    submitBtn.disabled = false;
    
    // Show the modal
    modal.style.display = 'block';
    
    <?php if (isset($_SESSION['add_errors'])): ?>
        // Display any PHP session errors
        displayModalErrors('addAssetForm', <?php echo json_encode($_SESSION['add_errors']); ?>);
        <?php unset($_SESSION['add_errors']); ?>
        
        // Populate form with previous data if available
        <?php if (isset($_SESSION['add_form_data'])): ?>
            const formData = <?php echo json_encode($_SESSION['add_form_data']); ?>;
            Object.keys(formData).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = formData[key];
                }
            });
            <?php unset($_SESSION['add_form_data']); ?>
        <?php endif; ?>
    <?php endif; ?>
}

 function showBorrowAssetModal() {
    document.getElementById('borrowAssetForm').reset();
    clearModalErrors('borrowAssetForm');
    document.getElementById('borrowAssetForm').querySelector('#date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('asset_ref_no').selectedIndex = -1;
    document.getElementById('borrowAssetModal').style.display = 'block';
    <?php if (isset($_SESSION['borrow_errors'])): ?>
        displayModalErrors('borrowAssetForm', <?php echo json_encode($_SESSION['borrow_errors']); ?>);
        <?php if (isset($_SESSION['borrow_form_data']['asset_ref_no'])): ?>
            const assetRefNos = <?php echo json_encode((array)$_SESSION['borrow_form_data']['asset_ref_no']); ?>;
            const select = document.getElementById('asset_ref_no');
            Array.from(select.options).forEach(option => {
                option.selected = assetRefNos.includes(option.value);
            });
        <?php endif; ?>
    <?php endif; ?>
}

 function showAssetTab(tab) {
    const activeContent = document.getElementById('assets-active');
    const archiveContent = document.getElementById('assets-archive');
    const borrowedContent = document.getElementById('assets-borrowed');
    const buttons = document.querySelectorAll('.tab-buttons .tab-btn');
    const searchInput = document.getElementById('searchInput');

    searchInput.value = '';

    // Reset filters based on the tab
    if (tab === 'active' || tab === 'archive') {
        window.currentFilters.name = '';
        window.currentFilters.status = '';
    } else if (tab === 'borrowed') {
        window.currentFilters.borrowedAssetName = '';
        window.currentFilters.borrowedTechName = '';
    }

    // Update tab visibility
    activeContent.style.display = tab === 'active' ? 'block' : 'none';
    archiveContent.style.display = tab === 'archive' ? 'block' : 'none';
    borrowedContent.style.display = tab === 'borrowed' ? 'block' : 'none';

    // Update active button
    buttons.forEach(button => {
        button.classList.remove('active');
        if (button.getAttribute('onclick').includes(tab)) {
            button.classList.add('active');
        }
    });

    searchAssets(1, tab);
}

function showAssetViewModal(ref_no) {
    // Fetch complete asset details via AJAX
    fetch(`assetsT.php?action=get_asset_details&ref_no=${ref_no}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('assetViewContent').innerHTML = `
                <div class="asset-details">
                    <p><strong>Asset Ref No:</strong> ${data.ref_no}</p>
                    <p><strong>Asset Name:</strong> ${data.name}</p>
                    <p><strong>Category:</strong> ${data.status}</p>
                    <p><strong>Current Status:</strong> ${data.current_status}</p>
                    <p><strong>Serial No:</strong> ${data.serial_no || 'N/A'}</p>
                    <p><strong>Asset Specification:</strong> ${data.specs ? data.specs : '<em>Not specified</em>'}</p>
                    <p><strong>Asset Lifecycle:</strong> ${data.cycle || '<em>Not set</em>'}</p>
                    <p><strong>Asset Condition:</strong> <span style="font-weight:600;color:${
                        data.condition === 'Brand New' ? '#28a745' :
                        data.condition === 'Good Condition' ? '#007bff' :
                        data.condition === 'Slightly Used' ? '#ffc107' :
                        data.condition === 'For Repair' ? '#fd7e14' : '#dc3545'
                    };">${data.condition || '<em>Not set</em>'}</span></p>
                    <p><strong>Date ${data.current_status === 'Borrowed' ? 'Borrowed' : 'Registered'}:</strong> ${data.date}</p>
                    ${data.tech_name ? `<p><strong>Technician Name:</strong> ${data.tech_name}</p>` : ''}
                    ${data.tech_id ? `<p><strong>Technician ID:</strong> ${data.tech_id}</p>` : ''}
                </div>
            `;
            document.getElementById('assetViewModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error fetching asset details:', error);
            showErrorMessage('Failed to load asset details.');
        });
}
function showAssetViewModal(ref_no, name, status, current_status, date, serial_no, specs = '', cycle = '', condition = '') {
    document.getElementById('assetViewContent').innerHTML = `
        <div class="asset-details">
            <p><strong>Asset Ref No:</strong> ${ref_no}</p>
            <p><strong>Asset Name:</strong> ${name}</p>
            <p><strong>Category:</strong> ${status}</p>
            <p><strong>Current Status:</strong> ${current_status}</p>
            <p><strong>Serial No:</strong> ${serial_no || 'N/A'}</p>
            <p><strong>Asset Specification:</strong> ${specs ? specs : '<em>Not specified</em>'}</p>
            <p><strong>Asset Lifecycle:</strong> ${cycle || '<em>Not set</em>'}</p>
            <p><strong>Asset Condition:</strong> <span style="font-weight:600;color:${
                condition === 'Brand New' ? '#28a745' :
                condition === 'Good Condition' ? '#007bff' :
                condition === 'Slightly Used' ? '#ffc107' :
                condition === 'For Repair' ? '#fd7e14' : '#dc3545'
            };">${condition || '<em>Not set</em>'}</span></p>
            <p><strong>Date ${current_status === 'Borrowed' ? 'Borrowed' : 'Registered'}:</strong> ${date}</p>
        </div>
    `;
    document.getElementById('assetViewModal').style.display = 'block';
}

    function showEditAssetModal(ref_no, name, status, current_status, serial_no) {
    console.log('showEditAssetModal called with:', { ref_no, name, status, current_status, serial_no });
    if (current_status === 'Borrowed' || current_status === 'Deployed') {
        showErrorMessage('Cannot edit the borrowed and deployed asset.');
        return;
    }
    document.getElementById('edit_a_ref_no').value = ref_no;
    document.getElementById('edit_asset_name').value = name;
    document.getElementById('edit_asset_status').value = status;
    document.getElementById('edit_current_status').value = current_status;
    document.getElementById('edit_serial_no').value = serial_no || '';
    document.querySelectorAll('#editAssetForm .error').forEach(el => el.textContent = '');
    document.getElementById('editAssetModal').style.display = 'block';
}

    function showBorrowAssetModal() {
        document.getElementById('borrowAssetForm').reset();
        document.querySelectorAll('#borrowAssetForm .error').forEach(el => el.textContent = '');
        document.getElementById('borrowAssetForm').querySelector('#date').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('asset_ref_no').selectedIndex = -1; // Reset multiple select
        document.getElementById('borrowAssetModal').style.display = 'block';
    }

    function showDeployAssetModal() {
    console.log('showDeployAssetModal called');
    document.getElementById('deployAssetForm').reset();
    clearModalErrors('deployAssetForm');
    document.getElementById('deployAssetForm').querySelector('#deploy_date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('deploy_asset_ref_no').selectedIndex = -1;
    document.getElementById('deployAssetModal').style.display = 'block';
    <?php if (isset($_SESSION['deploy_errors'])): ?>
        displayModalErrors('deployAssetForm', <?php echo json_encode($_SESSION['deploy_errors']); ?>);
        <?php if (isset($_SESSION['deploy_form_data']['asset_ref_no'])): ?>
            const assetRefNos = <?php echo json_encode((array)$_SESSION['deploy_form_data']['asset_ref_no']); ?>;
            const select = document.getElementById('deploy_asset_ref_no');
            Array.from(select.options).forEach(option => {
                option.selected = assetRefNos.includes(option.value);
            });
        <?php endif; ?>
    <?php endif; ?>
}
   
    function showArchiveModal(ref_no, name) {
        document.getElementById('archiveAssetName').textContent = name;
        document.getElementById('archiveAssetRefNo').value = ref_no;
        document.getElementById('archiveModal').style.display = 'block';
    }

    function showUnarchiveModal(ref_no, name) {
        document.getElementById('unarchiveAssetName').textContent = name;
        document.getElementById('unarchiveAssetRefNo').value = ref_no;
        document.getElementById('unarchiveModal').style.display = 'block';
    }

    function showDeleteModal(ref_no, name) {
        document.getElementById('deleteAssetName').textContent = name;
        document.getElementById('deleteAssetRefNo').value = ref_no;
        document.getElementById('deleteModal').style.display = 'block';
    }

    function showAssetNameFilterModal(tab) {
    document.getElementById('assetNameFilterTab').value = tab;
    
    // Fetch asset names dynamically via AJAX
    const params = new URLSearchParams({ action: 'get_asset_names', tab: tab });
    fetch(`assetsT.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('asset_name_filter');
            select.innerHTML = '<option value="">All Assets</option>';
            data.assetNames.forEach(name => {
                const option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                select.appendChild(option);
            });
            document.getElementById('assetNameFilterModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error fetching asset names:', error);
            showErrorMessage('Failed to load asset names for filter.');
        });
}

    function showAssetStatusFilterModal(tab) {
        document.getElementById('assetStatusFilterTab').value = tab;
        document.getElementById('assetStatusFilterModal').style.display = 'block';
    }

    function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }

function showBorrowedAssetNameFilterModal(tab) {
    document.getElementById('borrowedAssetNameFilterTab').value = tab;
    document.getElementById('borrowedAssetNameFilterModal').style.display = 'block';
}

function showBorrowedTechNameFilterModal(tab) {
    document.getElementById('borrowedTechNameFilterTab').value = tab;
    document.getElementById('borrowedTechNameFilterModal').style.display = 'block';
}

function applyBorrowedAssetNameFilter() {
    const tab = document.getElementById('borrowedAssetNameFilterTab').value;
    const nameSelect = document.getElementById('borrowed_asset_name_filter');
    const selectedName = nameSelect.value;
    console.log('Applying Borrowed Asset Name Filter:', { tab, selectedName });
    searchAssets(1, tab, selectedName, null, null);
    closeModal('borrowedAssetNameFilterModal');
}

function applyBorrowedTechNameFilter() {
    const tab = document.getElementById('borrowedTechNameFilterTab').value;
    const techNameSelect = document.getElementById('borrowed_tech_name_filter');
    const selectedTechName = techNameSelect.value;
    console.log('Applying Borrowed Tech Name Filter:', { tab, selectedTechName });
    searchAssets(1, tab, null, null, selectedTechName);
    closeModal('borrowedTechNameFilterModal');
}

// In the script section (replace the deleteAsset function around line 1480)
function showBorrowedDeleteModal(refNo, assetName) {
    document.getElementById('borrowed_delete_ref_no').value = refNo;
    document.getElementById('borrowed_delete_asset_name').textContent = assetName;
    document.getElementById('borrowedDeleteModal').style.display = 'block';
}

function confirmBorrowedDelete() {
    const refNo = document.getElementById('borrowed_delete_ref_no').value;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('ref_no', refNo);
    formData.append('tab', 'borrowed');

    fetch('assetsT.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        showSuccessMessage(data);
        closeModal('borrowedDeleteModal');
        searchAssets(1, 'borrowed');
    })
    .catch(error => {
        console.error('Delete error:', error);
        showErrorMessage('Error deleting asset: ' + error.message);
    });
}

window.currentFilters = { name: '', status: '', borrowedAssetName: '', borrowedTechName: '' };

function searchAssets(page = 1, tab = 'active', filterName = null, filterStatus = null, filterTechName = null) {
    const searchTerm = document.getElementById('searchInput').value.trim();
    const tbody = document.getElementById(tab === 'active' ? 'assets-table-body' : tab === 'archive' ? 'archived-assets-table-body' : 'borrowed-assets-table-body');

    // Use filter values from arguments or currentFilters
    let assetName = filterName !== null ? filterName : (tab === 'borrowed' ? window.currentFilters.borrowedAssetName || '' : window.currentFilters.name || '');
    let techName = filterTechName !== null ? filterTechName : window.currentFilters.borrowedTechName || '';
    let status = filterStatus !== null ? filterStatus : window.currentFilters.status || '';

    // Update filter state
    if (tab === 'borrowed') {
        if (filterName !== null) window.currentFilters.borrowedAssetName = filterName;
        if (filterTechName !== null) window.currentFilters.borrowedTechName = filterTechName;
    } else {
        if (filterName !== null) window.currentFilters.name = filterName;
        if (filterStatus !== null) window.currentFilters.status = filterStatus;
    }

    console.log(`searchAssets: tab=${tab}, page=${page}, searchTerm=${searchTerm}, assetName=${assetName}, techName=${techName}, filterStatus=${status}`);

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                console.log('Search response received:', xhr.responseText);
                tbody.innerHTML = xhr.responseText.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
                const scripts = xhr.responseText.match(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi);
                if (scripts) {
                    scripts.forEach(script => {
                        const scriptContent = script.replace(/<\/?script>/g, '');
                        eval(scriptContent);
                    });
                }
            } else {
                console.error('Search request failed:', xhr.status, xhr.responseText);
                tbody.innerHTML = `<tr><td colspan="${tab === 'borrowed' ? 7 : 8}">Error loading assets. Please try again.</td></tr>`;
            }
        }
    };

    const params = new URLSearchParams();
    params.append('action', 'search');
    params.append('tab', tab);
    params.append('search', searchTerm);
    params.append('search_page', page);
    if (tab === 'borrowed') {
        if (assetName) params.append('asset_name', assetName);
        if (techName) params.append('tech_name', techName);
    } else {
        if (assetName) params.append('filter_name', assetName);
        if (status) params.append('filter_status', status);
    }

    const url = `assetsT.php?${params.toString()}`;
    console.log('AJAX URL:', url);
    xhr.open('GET', url, true);
    xhr.send();
}

function updatePagination(currentPage, totalPages, tab, searchTerm, paginationId) {
    const paginationContainer = document.getElementById(paginationId);
    let paginationHtml = '';

    let filterParams;
    if (tab === 'borrowed') {
        filterParams = `, '${encodeURIComponent(window.currentFilters.borrowedAssetName || '')}', null, '${encodeURIComponent(window.currentFilters.borrowedTechName || '')}'`;
    } else {
        filterParams = `, '${encodeURIComponent(window.currentFilters.name || '')}', '${encodeURIComponent(window.currentFilters.status || '')}'`;
    }

    if (currentPage > 1) {
        paginationHtml += `<a href="javascript:searchAssets(${currentPage - 1}, '${tab}'${filterParams})" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="javascript:searchAssets(${currentPage + 1}, '${tab}'${filterParams})" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

function deleteAsset(refNo, tab) {
    if (confirm('Are you sure you want to delete this asset? This action cannot be undone.')) {
        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    alert('Asset deleted successfully.');
                    searchAssets(1, tab);
                } else {
                    alert('Error deleting asset: ' + xhr.responseText);
                }
            }
        };
        xhr.open('POST', 'assetsT.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send(`action=delete&ref_no=${encodeURIComponent(refNo)}&tab=${tab}`);
    }
}

function applyAssetNameFilter() {
    const tab = document.getElementById('assetNameFilterTab').value;
    const nameSelect = document.getElementById('asset_name_filter');
    const selectedName = nameSelect.value;
    searchAssets(1, tab, selectedName, null);
    closeModal('assetNameFilterModal');
}

function applyAssetStatusFilter() {
    const tab = document.getElementById('assetStatusFilterTab').value;
    const statusSelect = document.getElementById('asset_status_filter');
    const selectedStatus = statusSelect.value;
    searchAssets(1, tab, null, selectedStatus);
    closeModal('assetStatusFilterModal');
}

function applyFilter(tab, selectedName = null, selectedStatus = null) { searchAssets(1, tab, selectedName, selectedStatus); }

 function exportTable(format) {
    const searchTerm = document.getElementById('searchInput').value;
    const filterName = window.currentFilters?.name || '';
    const filterStatus = window.currentFilters?.status || '';
    const filterBorrowedAssetName = window.currentFilters?.borrowedAssetName || '';
    const filterBorrowedTechName = window.currentFilters?.borrowedTechName || '';

    let tab;
    if (document.getElementById('assets-active').style.display !== 'none') {
        tab = 'active';
    } else if (document.getElementById('assets-archive').style.display !== 'none') {
        tab = 'archive';
    } else if (document.getElementById('assets-borrowed').style.display !== 'none') {
        tab = 'borrowed';
    } else {
        tab = 'active'; // Fallback to active if no tab is visible
    }

    const params = new URLSearchParams();
    params.append('action', 'export_data');
    params.append('tab', tab);
    if (searchTerm) params.append('search', searchTerm);
    if (tab === 'borrowed') {
        if (filterBorrowedAssetName) params.append('asset_name', filterBorrowedAssetName);
        if (filterBorrowedTechName) params.append('tech_name', filterBorrowedTechName);
    } else {
        if (filterName) params.append('filter_name', filterName);
        if (filterStatus) params.append('filter_status', filterStatus);
    }

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
                        const sheetName = tab === 'active' ? 'Active Assets' : tab === 'archive' ? 'Archived Assets' : 'Borrowed Assets';
                        XLSX.utils.book_append_sheet(wb, ws, sheetName);
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

if (typeof debounce === 'undefined') {
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
}

const debouncedSearchAssets = debounce(function() {
    let activeTab = 'active'; // Default to active
    if (document.getElementById('assets-active').style.display !== 'none') {
        activeTab = 'active';
    } else if (document.getElementById('assets-archive').style.display !== 'none') {
        activeTab = 'archive';
    } else if (document.getElementById('assets-borrowed').style.display !== 'none') {
        activeTab = 'borrowed';
    }
    console.log('debouncedSearchAssets: Triggering search for tab:', activeTab);
    searchAssets(1, activeTab);
}, 300);

document.getElementById('searchInput').removeEventListener('input', debouncedSearchAssets); // Prevent duplicate listeners
document.getElementById('searchInput').addEventListener('input', debouncedSearchAssets);

    </script>
    </body>
    </html>
