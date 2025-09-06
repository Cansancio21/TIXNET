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
    $errors = [];
    $assetname = trim($_POST['asset_name'] ?? '');
    $assetstatus = trim($_POST['asset_status'] ?? '');
    $assetquantity = trim($_POST['asset_quantity'] ?? '');
    $assetdate = trim($_POST['date'] ?? '');
    $serial_no = trim($_POST['serial_no'] ?? '');

    // Validation
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
                $sql = "INSERT INTO tbl_assets (a_name, a_status, a_quantity, a_date, a_ref_no, a_serial_no, a_current_status) VALUES (?, ?, 1, ?, ?, ?, 'Available')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssss", $assetname, $assetstatus, $assetdate, $ref_no, $serial_no);
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
        ];
        $_SESSION['open_modal'] = 'addAsset';
    }

    $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
    $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
    header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
    exit();
}

        // Edit Asset
        if (isset($_POST['edit_asset'])) {
        $assetRefNo = trim($_POST['a_ref_no'] ?? '');
        $assetname = trim($_POST['asset_name'] ?? '');
        $assetstatus = trim($_POST['asset_status'] ?? '');
        $assetcurrentstatus = trim($_POST['current_status'] ?? '');
        $assetdate = trim($_POST['date'] ?? '');
        $serial_no = trim($_POST['serial_no'] ?? '');
        $assetnameErr = $assetstatusErr = $assetquantityErr = $assetdateErr = "";

        if (empty($assetRefNo)) {
            $assetRefNoErr = "Asset Reference Number is required.";
            $hasError = true;
        }
        if (!preg_match("/^[a-zA-Z\s-]+$/", $assetname)) {
            $assetnameErr = "Asset Name should not contain numbers.";
            $hasError = true;
        }
        if (!in_array($assetstatus, ['Borrowing', 'Deployment', 'Archived'])) {
            $assetstatusErr = "Invalid asset status.";
            $hasError = true;
        }
        if (!in_array($assetcurrentstatus, ['Available', 'Borrowed', 'Deployed', 'Archived'])) {
            $assetstatusErr = "Invalid current status.";
            $hasError = true;
        }
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $assetdate)) {
            $assetdateErr = "Invalid date format.";
            $hasError = true;
        }

        if (!$hasError) {
            $sql = "UPDATE tbl_assets SET a_name = ?, a_status = ?, a_current_status = ?, a_date = ?, a_serial_no = ? WHERE a_ref_no = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sssiss", $assetname, $assetstatus, $assetcurrentstatus, $assetdate, $serial_no, $assetRefNo);
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
            $_SESSION['error'] = implode(" ", array_filter([$assetRefNoErr, $assetnameErr, $assetstatusErr, $assetdateErr]));
        }
        $activePage = isset($_GET['active_page']) ? $_GET['active_page'] : 1;
        $archivedPage = isset($_GET['archived_page']) ? $_GET['archived_page'] : 1;
        header("Location: assetsT.php?active_page=$activePage&archived_page=$archivedPage");
        exit();
    }

        
       
         // Start output buffering to prevent stray output
         ob_start();
         // Borrow Asset
    if (isset($_POST['borrow_asset'])) {
        $errors = [];
        $asset_ref_nos = $_POST['asset_ref_no'] ?? [];
        $borrow_techname = trim($_POST['tech_name'] ?? '');
        $borrow_techid = trim($_POST['tech_id'] ?? '');
        $borrowdate = trim($_POST['date'] ?? '');

        // Validate inputs
        if (empty($asset_ref_nos) || !is_array($asset_ref_nos)) {
            $errors['asset_ref_no'] = "Please select at least one asset.";
        }
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
        
        // Validate technician
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

        // Check if technician has outstanding borrows (limit to 5)
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

        // Validate assets
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

        // Process borrowing
        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // Insert into tbl_asset_status
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

                // Update tbl_assets
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

                // Log the action
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

                // Clear output buffer and send JSON response
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
            // Clear output buffer and send JSON response
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
 

// Deploy Asset
if (isset($_POST['deploy_asset'])) {
    $errors = [];
    $asset_ref_nos = $_POST['asset_ref_no'] ?? [];
    $deploy_techname = trim($_POST['tech_name'] ?? '');
    $deploy_techid = trim($_POST['tech_id'] ?? '');
    $deploydate = trim($_POST['date'] ?? '');

    // Validate inputs
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

    // Validate technician
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

    // Check if technician has outstanding deployments
    if (empty($errors)) {
        $sqlCheckDeployed = "SELECT COUNT(*) AS total_deployed FROM tbl_asset_status WHERE tech_id = ? AND a_status = 'Deployed'";
        $stmtCheckDeployed = $conn->prepare($sqlCheckDeployed);
        if (!$stmtCheckDeployed) {
            $errors['general'] = "Database error: Failed to prepare deployed assets query: " . $conn->error;
        } else {
            $stmtCheckDeployed->bind_param("s", $deploy_techid);
            $stmtCheckDeployed->execute();
            $resultCheckDeployed = $stmtCheckDeployed->get_result();
            $row = $resultCheckDeployed->fetch_assoc();
            if ($row['total_deployed'] > 0) {
                $errors['tech_id'] = "Technician must return deployed assets before deploying again.";
            }
            $stmtCheckDeployed->close();
        }
    }

    // Validate assets
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

    // Process deployment
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Insert into tbl_asset_status
            $sqlInsertStatus = "INSERT INTO tbl_asset_status (a_ref_no, a_name, tech_name, tech_id, a_serial_no, a_date, a_status) VALUES (?, ?, ?, ?, ?, ?, 'Deployed')";
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

            // Update tbl_assets
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

            // Log the action
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

            // Clear output buffer and send JSON response
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
        // Clear output buffer and send JSON response
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


        // Handle archive/unarchive/delete requests
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
    } elseif (isset($_POST['unarchive_asset'])) {
        $assetRefNo = trim($_POST['a_ref_no'] ?? '');
        $sql = "UPDATE tbl_assets SET a_current_status = 'Available' WHERE a_ref_no = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $assetRefNo);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Asset unarchived successfully!";
        } else {
            $_SESSION['error'] = "Error unarchiving asset.";
        }
        $stmt->close();
    } elseif (isset($_POST['delete_asset'])) {
        $assetRefNo = trim($_POST['a_ref_no'] ?? '');
        $sql = "DELETE FROM tbl_assets WHERE a_ref_no = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $assetRefNo);
        
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

        $sql = "SELECT a_ref_no, a_name, a_status, a_current_status, a_quantity, a_date, a_serial_no 
            FROM tbl_assets 
            WHERE $whereClause 
            ORDER BY a_ref_no ASC 
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
                <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "</td>  
                <td>" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . ($row['a_serial_no'] ? htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8') : '') . "</td>
                <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>";
            if ($tab === 'active') {
                $output .= "<a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                    <a class='edit-btn' onclick=\"showEditAssetModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                    <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>";
        } else {
                $output .= "<a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                    <a class='unarchive-btn' onclick=\"showUnarchiveModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                    <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>";
        }
    $output .= "</td></tr>";
            }
        } else {
            $output .= "<tr><td colspan='8'>No assets found.</td></tr>";
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
        $sql = "SELECT a_ref_no, a_name, a_status, a_current_status, a_date, a_serial_no 
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

        $sqlActive = "SELECT a_ref_no, a_name, a_status, a_current_status, a_date, a_serial_no FROM tbl_assets WHERE a_current_status != 'Archived' ORDER BY a_ref_no ASC LIMIT ?, ?";
        $stmtActive = $conn->prepare($sqlActive);
        $stmtActive->bind_param("ii", $activeOffset, $limit);
        $stmtActive->execute();
        $resultActive = $stmtActive->get_result();
        $stmtActive->close();

        $archivedCountQuery = "SELECT COUNT(*) as total FROM tbl_assets WHERE a_current_status = 'Archived'";
        $archivedCountResult = $conn->query($archivedCountQuery);
        $totalArchived = $archivedCountResult ? $archivedCountResult->fetch_assoc()['total'] : 0;
        $totalArchivedPages = ceil($totalArchived / $limit);

        $sqlArchived = "SELECT a_ref_no, a_name, a_status, a_current_status, a_date, a_serial_no FROM tbl_assets WHERE a_current_status = 'Archived' ORDER BY a_ref_no ASC LIMIT ?, ?";
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
        <link rel="stylesheet" href="assetsT.css"> 
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
        <li><a href="staffD.php"><i class="fas fa-ticket-alt icon"></i> <span>Regular Tickets</span></a></li>
        <li><a href="assetsT.php" class="active"><i class="fas fa-boxes icon"></i> <span>Assets</span></a></li>
        <li><a href="AllCustomersT.php"><i class="fas fa-clipboard-check icon"></i> <span>Customers Ticket</span></a></li>
        <li><a href="customersT.php"><i class="fas fa-user-friends icon"></i> <span>Customers</span></a></li>
        <li><a href="borrowedStaff.php"><i class="fas fa-hand-holding icon"></i> <span>Borrowed Assets</span></a></li>
        <li><a href="addC.php"><i class="fas fa-user-plus icon"></i> <span>Add Customer</span></a></li>
        <li><a href="AssignTech.php"><i class="fas fa-tools icon"></i> <span>Technicians</span></a></li>
        <li><a href="Payments.php"><i class="fas fa-credit-card icon"></i> <span>Payment Transactions</span></a></li>
    </ul>
    <footer>
        <a href="technician_staff.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                   <button class="tab-btn" onclick="showAssetTab('archive')">Archive 
                      <?php if ($totalArchived > 0): ?>
                        <span class="tab-badge"><?php echo $totalArchived; ?></span>
                      <?php endif; ?>
                    </button>
                </div>
                 <div class="button-group">
                   <div class="action-container">
                      <button class="action-btn"><i class="fas fa-plus"></i> Actions</button>
                      <div class="action-dropdown">
                        <button onclick="showAddAssetModal()">Add Asset</button>
                        <button onclick="showBorrowAssetModal()">Borrow</button>
                        <button onclick="showDeployAssetModal()">Deploy</button>
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
                            <th>Current Status</th>
                            <th>Serial No</th>
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
                                                <td>" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "</td> 
                                                <td>" . ($row['a_serial_no'] ? htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8') : '') . "</td>
                                                <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                                                <td>
                                                    <a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                                    <a class='edit-btn' onclick=\"showEditAssetModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
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
                                    <th>Current Status</th>
                                    <th>Serial No</th>
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
                                                <td>" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "</td>
                                                <td>" . ($row['a_serial_no'] ? htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8') : '') . "</td>
                                                <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                                                <td>
                                                    <a class='view-btn' onclick=\"showAssetViewModal('" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_current_status'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_serial_no'] ?? '', ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
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
                <input type="text" id="asset_name" name="asset_name" placeholder="Asset Name" value="<?php echo isset($_SESSION['add_form_data']['asset_name']) ? htmlspecialchars($_SESSION['add_form_data']['asset_name']) : ''; ?>" required>
                <span class="error" id="addAssetForm_asset_name_error"><?php echo isset($_SESSION['add_errors']['asset_name']) ? htmlspecialchars($_SESSION['add_errors']['asset_name']) : ''; ?></span>
            </div>
            <div class="form-group">
                <label for="asset_quantity">Number of Assets to Register:</label>
                <input type="text" id="asset_quantity" name="asset_quantity" placeholder="Number of assets" value="<?php echo isset($_SESSION['add_form_data']['asset_quantity']) ? htmlspecialchars($_SESSION['add_form_data']['asset_quantity']) : ''; ?>" required>
                <span class="error" id="addAssetForm_asset_quantity_error"><?php echo isset($_SESSION['add_errors']['asset_quantity']) ? htmlspecialchars($_SESSION['add_errors']['asset_quantity']) : ''; ?></span>
            </div>
            <div class="form-group">
                <label for="serial_no">Serial Number:</label>
                <input type="text" id="serial_no" name="serial_no" placeholder="Serial Number (optional)" value="<?php echo isset($_SESSION['add_form_data']['serial_no']) ? htmlspecialchars($_SESSION['add_form_data']['serial_no']) : ''; ?>">
                <span class="error" id="addAssetForm_serial_no_error"><?php echo isset($_SESSION['add_errors']['serial_no']) ? htmlspecialchars($_SESSION['add_errors']['serial_no']) : ''; ?></span>
            </div>
            <div class="form-group">
                <label for="asset_status">Asset Category:</label>
                <select id="asset_status" name="asset_status" required>
                    <option value="Borrowing" <?php echo isset($_SESSION['add_form_data']['asset_status']) && $_SESSION['add_form_data']['asset_status'] === 'Borrowing' ? 'selected' : ''; ?>>Borrowing</option>
                    <option value="Deployment" <?php echo isset($_SESSION['add_form_data']['asset_status']) && $_SESSION['add_form_data']['asset_status'] === 'Deployment' ? 'selected' : ''; ?>>Deployment</option>
                </select>
                <span class="error" id="addAssetForm_asset_status_error"><?php echo isset($_SESSION['add_errors']['asset_status']) ? htmlspecialchars($_SESSION['add_errors']['asset_status']) : ''; ?></span>
            </div>
            <div class="form-group">
                <label for="date">Date Registered:</label>
                <input type="date" id="date" name="date" value="<?php echo isset($_SESSION['add_form_data']['date']) ? htmlspecialchars($_SESSION['add_form_data']['date']) : ''; ?>" required>
                <span class="error" id="addAssetForm_date_error"><?php echo isset($_SESSION['add_errors']['date']) ? htmlspecialchars($_SESSION['add_errors']['date']) : ''; ?></span>
            </div>
            <div class="form-group">
                <span class="error" id="addAssetForm_general_error"><?php echo isset($_SESSION['add_errors']['general']) ? htmlspecialchars($_SESSION['add_errors']['general']) : ''; ?></span>
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
       
    
            <!-- Archive Confirmation Modal -->
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

            <!-- Unarchive Confirmation Modal -->
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
                            <button type="button" class="modal-btn cancel" onclick="closeModal('assetStatusFilterModal')">Cancel</button>
                            <button type="button" class="modal-btn confirm" onclick="applyAssetStatusFilter()">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        

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


    // Function to display validation errors in modals
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

// Validate Add Asset Form
document.getElementById('addAssetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    formData.append('ajax', 'true');

    // Client-side validation
    const errors = {};
    const assetName = document.getElementById('asset_name').value.trim();
    const quantity = document.getElementById('asset_quantity').value.trim();
    const serial = document.getElementById('serial_no').value.trim();
    const assetStatus = document.getElementById('asset_status').value;
    const date = document.getElementById('date').value;

    if (!assetName || !/^[a-zA-Z\s-]+$/.test(assetName)) {
        errors['asset_name'] = 'Asset Name is required and should not contain numbers.';
    }
    if (!['Borrowing', 'Deployment'].includes(assetStatus)) {
        errors['asset_status'] = 'Invalid asset status.';
    }
    if (!quantity || isNaN(quantity) || quantity < 0) {
        errors['asset_quantity'] = 'Quantity must be a non-negative number.';
    }
    if (serial && quantity > 1) {
        errors['serial_no'] = 'Cannot assign the same serial number to multiple assets.';
    }
    if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        errors['date'] = 'Invalid date format.';
    }

    if (Object.keys(errors).length > 0) {
        displayModalErrors('addAssetForm', errors);
        return;
    }

    // Clear previous errors
    clearModalErrors('addAssetForm');

    // AJAX submission
    fetch('assetsT.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            closeModal('addAssetModal');
            searchAssets(1, 'active'); // Refresh table instead of reloading page
        } else {
            displayModalErrors('addAssetForm', data.errors);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showErrorMessage('An error occurred while processing your request.');
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



function clearModalErrors(formId) {
    document.querySelectorAll(`#${formId} .error`).forEach(span => span.textContent = '');
}

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
    }, 6000); 
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
    }, 10000);
}

// Clear errors when modals are opened
function showAddAssetModal() {
    document.getElementById('addAssetForm').reset();
    clearModalErrors('addAssetForm');
    document.getElementById('addAssetModal').style.display = 'block';
    <?php if (isset($_SESSION['add_errors'])): ?>
        displayModalErrors('addAssetForm', <?php echo json_encode($_SESSION['add_errors']); ?>);
        <?php unset($_SESSION['add_errors']); ?>
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
    

    function showAssetViewModal(ref_no, name, status, current_status, date, serial_no) {
        document.getElementById('assetViewContent').innerHTML = `
            <div class="asset-details">
                <p><strong>Asset Ref No:</strong> ${ref_no}</p>
                <p><strong>Asset Name:</strong> ${name}</p>
                <p><strong>Category:</strong> ${status}</p>
                <p><strong>Current Status:</strong> ${current_status}</p>
                <p><strong>Serial No:</strong> ${serial_no || ''}</p>
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

    function showEditAssetModal(ref_no, name, status, current_status, date, serial_no) {
        document.getElementById('edit_a_ref_no').value = ref_no;
        document.getElementById('edit_asset_name').value = name;
        document.getElementById('edit_asset_status').value = status;
        document.getElementById('edit_current_status').value = current_status;
        document.getElementById('edit_date').value = date;
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
        document.getElementById('assetNameFilterModal').style.display = 'block';
    }

    function showAssetStatusFilterModal(tab) {
        document.getElementById('assetStatusFilterTab').value = tab;
        document.getElementById('assetStatusFilterModal').style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Initialize filter state
window.currentFilters = { name: '', status: '' };

function searchAssets(page = 1, tab = 'active', filterName = '', filterStatus = '') {
    const searchTerm = document.getElementById('searchInput').value;
    const tbody = document.getElementById(tab === 'active' ? 'assets-table-body' : 'archived-assets-table-body');

    // Update current filters only if new filter values are provided
    if (filterName !== null) window.currentFilters.name = filterName;
    if (filterStatus !== null) window.currentFilters.status = filterStatus;

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
                `&filter_name=${encodeURIComponent(window.currentFilters.name)}&filter_status=${encodeURIComponent(window.currentFilters.status)}`;
    xhr.open('GET', url, true);
    xhr.send();
}

function updatePagination(currentPage, totalPages, tab, searchTerm, paginationId) {
    const paginationContainer = document.getElementById(paginationId);
    let paginationHtml = '';

    // Include filter parameters in pagination links
    const filterParams = `, '${encodeURIComponent(window.currentFilters.name)}', '${encodeURIComponent(window.currentFilters.status)}'`;

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

function applyFilter(tab, selectedName = null, selectedStatus = null) {
    searchAssets(1, tab, selectedName, selectedStatus);
}

const debouncedSearchAssets = debounce((page) => searchAssets(page, document.getElementById('assets-active').style.display !== 'none' ? 'active' : 'archive'), 300);

   

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

    </script>
    </body>
    </html>