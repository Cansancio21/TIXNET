
<?php
session_start();
include 'db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
$firstName = '';
$lastName = '';
$userType = '';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $username . '.png';
$defaultAvatar = 'default-avatar.png';

// Fetch user details from database
if ($conn) {
    $sql = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($firstName, $lastName, $userType);
    $stmt->fetch();
    $stmt->close();
} else {
    $_SESSION['error'] = "Database connection failed.";
    header("Location: index.php");
    exit();
}

// Set avatar path
if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} else {
    $_SESSION['avatarPath'] = $defaultAvatar;
}
$avatarPath = $_SESSION['avatarPath'];

// Fetch unique asset names and technician names for filter modals
$uniqueAssetNames = [];
$uniqueTechnicianNames = [];

if ($conn) {
    $sqlNames = "SELECT DISTINCT b_assets_name FROM tbl_borrowed ORDER BY b_assets_name";
    $resultNames = $conn->query($sqlNames);
    if ($resultNames) {
        while ($row = $resultNames->fetch_assoc()) {
            $uniqueAssetNames[] = $row['b_assets_name'];
        }
    }

    $sqlTechNames = "SELECT DISTINCT b_technician_name FROM tbl_borrowed ORDER BY b_technician_name";
    $resultTechNames = $conn->query($sqlTechNames);
    if ($resultTechNames) {
        while ($row = $resultTechNames->fetch_assoc()) {
            $uniqueTechnicianNames[] = $row['b_technician_name'];
        }
    }
}

// Fetch borrowed assets for return modal dropdown
$sqlBorrowedDropdown = "SELECT b_id, b_assets_name, b_quantity, b_technician_name, b_technician_id 
                        FROM tbl_borrowed 
                        WHERE b_quantity > 0";
$resultBorrowedDropdown = $conn->query($sqlBorrowedDropdown);

// Handle return asset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_asset'])) {
    header('Content-Type: application/json');
    $borrow_id = trim($_POST['borrow_id'] ?? '');
    $return_quantity = trim($_POST['return_quantity'] ?? '');
    $return_techname = trim($_POST['tech_name'] ?? '');
    $return_techid = trim($_POST['tech_id'] ?? '');
    $returndate = trim($_POST['date'] ?? '');
    $errors = [];

    // Validate inputs
    if (empty($borrow_id)) {
        $errors['borrow_id'] = "Please select a borrowed asset.";
    }
    if (empty($return_quantity) || !is_numeric($return_quantity) || $return_quantity < 1) {
        $errors['return_quantity'] = "Please enter a valid quantity to return (positive number).";
    }
    if (empty($return_techname) || !preg_match("/^[a-zA-Z\s-]+$/", $return_techname)) {
        $errors['tech_name'] = "Technician name is required and should not contain numbers.";
    }
    if (empty($return_techid)) {
        $errors['tech_id'] = "Technician ID is required.";
    }
    if (empty($returndate)) {
        $errors['date'] = "Return date is required.";
    }

    // Validate technician ID and name against tbl_user
    if (empty($errors)) {
        $sqlCheckTechnician = "SELECT u_fname, u_lname FROM tbl_user WHERE u_id = ?";
        $stmtCheckTechnician = $conn->prepare($sqlCheckTechnician);
        $stmtCheckTechnician->bind_param("s", $return_techid);
        $stmtCheckTechnician->execute();
        $resultCheckTechnician = $stmtCheckTechnician->get_result();

        if ($resultCheckTechnician->num_rows > 0) {
            $row = $resultCheckTechnician->fetch_assoc();
            $fullName = trim($row['u_fname'] . ' ' . $row['u_lname']);
            if (strcasecmp($fullName, $return_techname) !== 0) {
                $errors['tech_name'] = "Technician name does not match the ID in tbl_user.";
                error_log("tbl_user mismatch: Input Name: '$return_techname', DB Name: '$fullName'");
            }
        } else {
            $errors['tech_id'] = "Technician ID does not exist in tbl_user.";
        }
        $stmtCheckTechnician->close();
    }

    // Validate borrowed asset and quantity
    if (empty($errors)) {
        $sqlCheckBorrowed = "SELECT b_assets_name, b_quantity, b_technician_name, b_technician_id 
                             FROM tbl_borrowed 
                             WHERE b_id = ? AND b_quantity > 0";
        $stmtCheckBorrowed = $conn->prepare($sqlCheckBorrowed);
        $stmtCheckBorrowed->bind_param("i", $borrow_id);
        $stmtCheckBorrowed->execute();
        $resultCheckBorrowed = $stmtCheckBorrowed->get_result();

        if ($resultCheckBorrowed->num_rows > 0) {
            $row = $resultCheckBorrowed->fetch_assoc();
            $return_assetsname = trim($row['b_assets_name']);
            $currentBorrowedQuantity = $row['b_quantity'];
            $borrowedTechName = trim($row['b_technician_name']);
            $borrowedTechId = trim($row['b_technician_id']);

            error_log("Input Tech Name: '$return_techname', DB Tech Name: '$borrowedTechName'");
            error_log("Input Tech ID: '$return_techid', DB Tech ID: '$borrowedTechId'");

            if ($return_quantity > $currentBorrowedQuantity) {
                $errors['return_quantity'] = "Return quantity ($return_quantity) exceeds borrowed quantity ($currentBorrowedQuantity).";
            }
        } else {
            $errors['borrow_id'] = "Selected borrowed asset is not valid or has no quantity to return.";
        }
        $stmtCheckBorrowed->close();
    }

    // Process return if no errors
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Insert into tbl_returned
            $sqlInsert = "INSERT INTO tbl_returned (r_assets_name, r_quantity, r_technician_name, r_technician_id, r_date) 
                          VALUES (?, ?, ?, ?, ?)";
            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->bind_param("sisis", $return_assetsname, $return_quantity, $return_techname, $return_techid, $returndate);
            $stmtInsert->execute();
            $stmtInsert->close();

            // Update tbl_borrowed
            $newBorrowedQuantity = $currentBorrowedQuantity - $return_quantity;
            $sqlUpdateBorrowed = "UPDATE tbl_borrowed SET b_quantity = ? WHERE b_id = ?";
            $stmtUpdateBorrowed = $conn->prepare($sqlUpdateBorrowed);
            $stmtUpdateBorrowed->bind_param("ii", $newBorrowedQuantity, $borrow_id);
            $stmtUpdateBorrowed->execute();
            $stmtUpdateBorrowed->close();

            // Update tbl_techborrowed
            $sqlCheckTechBorrowed = "SELECT b_quantity FROM tbl_techborrowed 
                                     WHERE LOWER(b_assets_name) = LOWER(?) AND LOWER(b_technician_name) = LOWER(?) AND b_technician_id = ? AND b_quantity > 0";
            $stmtCheckTechBorrowed = $conn->prepare($sqlCheckTechBorrowed);
            $stmtCheckTechBorrowed->bind_param("sss", $return_assetsname, $return_techname, $return_techid);
            $stmtCheckTechBorrowed->execute();
            $resultCheckTechBorrowed = $stmtCheckTechBorrowed->get_result();

            if ($resultCheckTechBorrowed->num_rows > 0) {
                $row = $resultCheckTechBorrowed->fetch_assoc();
                $currentTechBorrowedQuantity = $row['b_quantity'];
                $newTechBorrowedQuantity = $currentTechBorrowedQuantity - $return_quantity;

                if ($newTechBorrowedQuantity < 0) {
                    throw new Exception("Return quantity exceeds borrowed quantity in tbl_techborrowed.");
                }

                $sqlUpdateTechBorrowed = "UPDATE tbl_techborrowed SET b_quantity = ? 
                                          WHERE LOWER(b_assets_name) = LOWER(?) AND LOWER(b_technician_name) = LOWER(?) AND b_technician_id = ?";
                $stmtUpdateTechBorrowed = $conn->prepare($sqlUpdateTechBorrowed);
                $stmtUpdateTechBorrowed->bind_param("isss", $newTechBorrowedQuantity, $return_assetsname, $return_techname, $return_techid);
                $stmtUpdateTechBorrowed->execute();
                $stmtUpdateTechBorrowed->close();
            } else {
                throw new Exception("No matching borrow record found in tbl_techborrowed.");
            }
            $stmtCheckTechBorrowed->close();

            // Delete records if quantity is 0
            if ($newBorrowedQuantity == 0) {
                $sqlDeleteBorrowed = "DELETE FROM tbl_borrowed WHERE b_id = ?";
                $stmtDeleteBorrowed = $conn->prepare($sqlDeleteBorrowed);
                $stmtDeleteBorrowed->bind_param("i", $borrow_id);
                $stmtDeleteBorrowed->execute();
                $stmtDeleteBorrowed->close();
            }

            if ($newTechBorrowedQuantity == 0) {
                $sqlDeleteTechBorrowed = "DELETE FROM tbl_techborrowed 
                                          WHERE LOWER(b_assets_name) = LOWER(?) AND LOWER(b_technician_name) = LOWER(?) AND b_technician_id = ?";
                $stmtDeleteTechBorrowed = $conn->prepare($sqlDeleteTechBorrowed);
                $stmtDeleteTechBorrowed->bind_param("sss", $return_assetsname, $return_techname, $return_techid);
                $stmtDeleteTechBorrowed->execute();
                $stmtDeleteTechBorrowed->close();
            }

            // Update tbl_assets
            $sqlCheckAssets = "SELECT a_quantity FROM tbl_assets WHERE LOWER(a_name) = LOWER(?)";
            $stmtCheckAssets = $conn->prepare($sqlCheckAssets);
            $stmtCheckAssets->bind_param("s", $return_assetsname);
            $stmtCheckAssets->execute();
            $resultCheckAssets = $stmtCheckAssets->get_result();

            if ($resultCheckAssets->num_rows > 0) {
                $row = $resultCheckAssets->fetch_assoc();
                $currentAssetsQuantity = $row['a_quantity'];
                $updatedAssetsQuantity = $currentAssetsQuantity + $return_quantity;

                $sqlUpdateAssets = "UPDATE tbl_assets SET a_quantity = ? WHERE LOWER(a_name) = LOWER(?)";
                $stmtUpdateAssets = $conn->prepare($sqlUpdateAssets);
                $stmtUpdateAssets->bind_param("is", $updatedAssetsQuantity, $return_assetsname);
                $stmtUpdateAssets->execute();
                $stmtUpdateAssets->close();
            } else {
                throw new Exception("Asset not found in tbl_assets.");
            }
            $stmtCheckAssets->close();

            // Commit transaction
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Asset returned successfully!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'errors' => ['general' => "Error returning asset: " . $e->getMessage()]]);
            error_log("Return error: " . $e->getMessage());
        }
    } else {
        echo json_encode(['success' => false, 'errors' => $errors]);
    }
    exit();
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_asset']) && isset($_POST['b_id'])) {
    $id = (int)$_POST['b_id'];
    
    $sql = "DELETE FROM tbl_borrowed WHERE b_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Record deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting record: " . $conn->error;
    }
    
    $stmt->close();
    header("Location: borrowedStaff.php");
    exit();
}

// Handle AJAX request for asset name (for view modal)
if (isset($_GET['id']) && !isset($_GET['page']) && !isset($_GET['deleted']) && !isset($_GET['updated']) && !isset($_GET['action']) && !isset($_GET['edit'])) {
    error_log('AJAX handler triggered for id: ' . $_GET['id']);
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    $sql = "SELECT b_assets_name FROM tbl_borrowed WHERE b_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error);
        echo json_encode(['assetName' => null, 'error' => 'Prepare failed: ' . $conn->error]);
        $conn->close();
        exit();
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['assetName' => $row['b_assets_name']]);
    } else {
        echo json_encode(['assetName' => null]);
    }
    
    $stmt->close();
    $conn->close();
    exit();
}

// Handle AJAX request for edit modal data
if (isset($_GET['edit']) && $_GET['edit'] === 'true' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    $sql = "SELECT b_assets_name, b_quantity, b_technician_name, b_technician_id, b_date FROM tbl_borrowed WHERE b_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Asset not found']);
    }
    
    $stmt->close();
    $conn->close();
    exit();
}

// Handle AJAX edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_asset']) && isset($_POST['b_id'])) {
    header('Content-Type: application/json');
    $id = (int)$_POST['b_id'];
    $asset_name = trim($_POST['b_assets_name']);
    $quantity = trim($_POST['b_quantity']);
    $technician_name = trim($_POST['b_technician_name']);
    $technician_id = trim($_POST['b_technician_id']);
    $date = trim($_POST['b_date']);

    $errors = [];

    // Validate inputs
    if (empty($asset_name)) {
        $errors['b_assets_name'] = "Asset name is required.";
    } elseif (strlen($asset_name) > 100) {
        $errors['b_assets_name'] = "Asset name must be 100 characters or less.";
    }

    if (empty($quantity)) {
        $errors['b_quantity'] = "Quantity is required.";
    } elseif (!is_numeric($quantity) || $quantity <= 0) {
        $errors['b_quantity'] = "Quantity must be a positive number.";
    }

    if (empty($technician_name)) {
        $errors['b_technician_name'] = "Technician name is required.";
    } elseif (strlen($technician_name) > 100) {
        $errors['b_technician_name'] = "Technician name must be 100 characters or less.";
    }

    if (empty($technician_id)) {
        $errors['b_technician_id'] = "Technician ID is required.";
    } elseif (strlen($technician_id) > 50) {
        $errors['b_technician_id'] = "Technician ID must be 50 characters or less.";
    }

    if (empty($date)) {
        $errors['b_date'] = "Date is required.";
    } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
        $errors['b_date'] = "Invalid date format.";
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        $conn->close();
        exit();
    }

    // If no errors, update the record
    $sql = "UPDATE tbl_borrowed SET b_assets_name = ?, b_quantity = ?, b_technician_name = ?, b_technician_id = ?, b_date = ? WHERE b_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sissis", $asset_name, $quantity, $technician_name, $technician_id, $date, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Record updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'errors' => ['general' => 'Error updating record: ' . $conn->error]]);
    }

    $stmt->close();
    $conn->close();
    exit();
}

// Handle AJAX search request with filters
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $filterAssetName = trim($_GET['filter_asset_name'] ?? '');
    $filterTechName = trim($_GET['filter_technician_name'] ?? '');
    $page = isset($_GET['search_page']) ? (int)$_GET['search_page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $output = '';

    $params = [];
    $types = '';
    $whereClauses = [];

    if ($searchTerm !== '') {
        $whereClauses[] = "(b_assets_name LIKE ? OR b_technician_name LIKE ? OR b_technician_id LIKE ? OR b_date LIKE ?)";
        $searchWildcard = "%$searchTerm%";
        $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
        $types .= 'ssss';
    }

    if ($filterAssetName !== '') {
        $whereClauses[] = "b_assets_name = ?";
        $params[] = $filterAssetName;
        $types .= 's';
    }

    if ($filterTechName !== '') {
        $whereClauses[] = "b_technician_name = ?";
        $params[] = $filterTechName;
        $types .= 's';
    }

    $whereClause = empty($whereClauses) ? '1' : implode(' AND ', $whereClauses);

    // Count total matching records for pagination
    $countSql = "SELECT COUNT(*) as total FROM tbl_borrowed WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = ceil($totalRecords / $limit);

    // Fetch paginated search results
    $sql = "SELECT b_id, b_assets_name, b_quantity, b_technician_name, b_technician_id, b_date 
            FROM tbl_borrowed 
            WHERE $whereClause 
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $params[] = $offset;
        $params[] = $limit;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param("ii", $offset, $limit);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output .= "<tr> 
                          <td>" . htmlspecialchars($row['b_id'], ENT_QUOTES, 'UTF-8') . "</td> 
                          <td>" . (isset($row['b_assets_name']) ? htmlspecialchars($row['b_assets_name'], ENT_QUOTES, 'UTF-8') : 'N/A') . "</td>  
                          <td>" . htmlspecialchars($row['b_quantity'], ENT_QUOTES, 'UTF-8') . "</td>
                          <td>" . htmlspecialchars($row['b_technician_name'], ENT_QUOTES, 'UTF-8') . "</td>
                          <td>" . htmlspecialchars($row['b_technician_id'], ENT_QUOTES, 'UTF-8') . "</td>    
                          <td>" . htmlspecialchars($row['b_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                          <td class='action-buttons'>
                              <a class='view-btn' onclick=\"showViewModal('" . htmlspecialchars($row['b_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['b_assets_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['b_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['b_technician_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['b_technician_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['b_date'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                              <a class='edit-btn' onclick=\"showEditModal('" . htmlspecialchars($row['b_id'], ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                              <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['b_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['b_assets_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                          </td>
                        </tr>";
        }
    } else {
        $output = "<tr><td colspan='7'>No borrowed assets found.</td></tr>";
    }
    $stmt->close();

    // Add pagination data
    $output .= "<script>
        updatePagination($page, $totalPages, '$searchTerm');
    </script>";

    echo $output;
    exit();
}

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch total number of borrowed assets
$countQuery = "SELECT COUNT(*) as total FROM tbl_borrowed";
$countResult = $conn->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Fetch borrowed assets with pagination
$sqlBorrowed = "SELECT b_id, b_assets_name, b_quantity, b_technician_name, b_technician_id, b_date 
                FROM tbl_borrowed 
                LIMIT ?, ?";
$stmt = $conn->prepare($sqlBorrowed);
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$resultBorrowed = $stmt->get_result();

// Check for deletion or update success
if (isset($_GET['deleted']) && $_GET['deleted'] == 'true') {
    $_SESSION['message'] = "Record deleted successfully!";
}
if (isset($_GET['updated']) && $_GET['updated'] == 'true') {
    $_SESSION['message'] = "Record updated successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowed Assets</title>
    <link rel="stylesheet" href="borrowedsT.css"> 
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
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
            <li><a href="assetsT.php"><img src="image/assets.png" alt="Assets" class="icon" /> <span>Assets</span></a></li>
            <li><a href="customersT.php"><img src="image/users.png" alt="Customers" class="icon" /> <span>Customers</span></a></li>
            <li><a href="borrowedStaff.php" class="active"><img src="image/borrowed.png" alt="Borrowed Assets" class="icon" /> <span>Borrowed Assets</span></a></li>
            <li><a href="addC.php"><img src="image/add.png" alt="Add Customer" class="icon" /> <span>Add Customer</span></a></li>
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>

    <div class="container">
        <div class="upper"> 
            <h1>Borrowed Assets</h1>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search borrowed assets..." onkeyup="debouncedSearchBorrowed()">
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
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
        </div>

        <div class="table-box glass-container">
            <?php if ($userType === 'admin'): ?>
                <div class="username">
                    Welcome, <?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>!
                    <i class="fas fa-user-shield admin-icon"></i>
                </div>
            <?php endif; ?>

            <div class="borrowed">
                <div class="button-container">
                    <a href="#" class="return-btn" onclick="showReturnAssetModal()"><i class="fas fa-undo"></i> Return</a>
                    <a href="createTickets.php" class="export-btn"><i class="fas fa-download"></i> Export</a>
                </div>
                <table id="borrowedTable">
                    <thead>
                        <tr>
                            <th>Borrowed ID</th>
                            <th>
                                Asset Name
                                <button class="filter-btn" onclick="showAssetNameFilterModal()" title="Filter by Asset Name">
                                    <i class='bx bx-filter'></i>
                                </button>
                            </th>
                            <th>Asset Quantity</th>
                            <th>
                                Technician Name
                                <button class="filter-btn" onclick="showTechnicianNameFilterModal()" title="Filter by Technician Name">
                                    <i class='bx bx-filter'></i>
                                </button>
                            </th>
                            <th>Technician ID</th>
                            <th>Borrowed Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                    <?php 
                    if ($resultBorrowed && $resultBorrowed->num_rows > 0) { 
                        while ($row = $resultBorrowed->fetch_assoc()) { 
                            echo "<tr> 
                                    <td>" . htmlspecialchars($row['b_id'], ENT_QUOTES, 'UTF-8') . "</td> 
                                    <td>" . (isset($row['b_assets_name']) ? htmlspecialchars($row['b_assets_name'], ENT_QUOTES, 'UTF-8') : 'N/A') . "</td>  
                                    <td>" . htmlspecialchars($row['b_quantity'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . htmlspecialchars($row['b_technician_name'], ENT_QUOTES, 'UTF-8') . "</td>
                                    <td>" . htmlspecialchars($row['b_technician_id'], ENT_QUOTES, 'UTF-8') . "</td>    
                                    <td>" . htmlspecialchars($row['b_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                                    <td class='action-buttons'>
                                        <a class='view-btn' onclick=\"showViewModal('" . htmlspecialchars($row['b_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['b_assets_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['b_quantity'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['b_technician_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['b_technician_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['b_date'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                        <a class='edit-btn' onclick=\"showEditModal('" . htmlspecialchars($row['b_id'], ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                                        <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['b_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['b_assets_name'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                                    </td>
                                  </tr>"; 
                        } 
                    } else { 
                        echo "<tr><td colspan='7'>No borrowed assets found.</td></tr>"; 
                    } 
                    ?>
                    </tbody>
                </table>

                <div class="pagination" id="borrowed-pagination">
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

        <!-- View Modal -->
        <div id="viewModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>View Borrowed Asset</h2>
                </div>
                <div id="viewModalContent" style="margin-top: 20px;"></div>
                <div class="modal-footer">
                    <button class="modal-btn cancel" onclick="closeModal('viewModal')">Close</button>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="editBorrowedModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Borrowed Asset</h2>
                </div>
                <form method="POST" id="editBorrowedForm" class="modal-form">
                    <input type="hidden" name="edit_asset" value="1">
                    <input type="hidden" name="ajax" value="true">
                    <input type="hidden" name="b_id" id="edit_b_id">
                    <div class="form-group">
                        <label for="edit_b_assets_name">Asset Name</label>
                        <input type="text" name="b_assets_name" id="edit_b_assets_name" required>
                        <span class="error-message" id="error_b_assets_name"></span>
                    </div>
                    <div class="form-group">
                        <label for="edit_b_quantity">Asset Quantity</label>
                        <input type="number" name="b_quantity" id="edit_b_quantity" min="1" required>
                        <span class="error-message" id="error_b_quantity"></span>
                    </div>
                    <div class="form-group">
                        <label for="edit_b_technician_name">Technician Name</label>
                        <input type="text" name="b_technician_name" id="edit_b_technician_name" required>
                        <span class="error-message" id="error_b_technician_name"></span>
                    </div>
                    <div class="form-group">
                        <label for="edit_b_technician_id">Technician ID</label>
                        <input type="text" name="b_technician_id" id="edit_b_technician_id" required>
                        <span class="error-message" id="error_b_technician_id"></span>
                    </div>
                    <div class="form-group">
                        <label for="edit_b_date">Borrowed Date</label>
                        <input type="date" name="b_date" id="edit_b_date" required>
                        <span class="error-message" id="error_b_date"></span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('editBorrowedModal')">Cancel</button>
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
                <p>Are you sure you want to delete <span id="deleteAssetName"></span> from the borrowed records? This action cannot be undone.</p>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="b_id" id="deleteAssetId">
                    <input type="hidden" name="delete_asset" value="1">
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('deleteModal')">Cancel</button>
                        <button type="submit" class="modal-btn confirm">Delete</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Return Asset Modal -->
        <div id="returnAssetModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Return Borrowed Asset</h2>
                </div>
                <form method="POST" id="returnAssetForm" class="modal-form">
                    <input type="hidden" name="return_asset" value="1">
                    <input type="hidden" name="ajax" value="true">
                    <div class="form-group">
                        <label for="borrow_id">Borrowed Asset</label>
                        <select id="borrow_id" name="borrow_id" required>
                            <option value="">Select Borrowed Asset</option>
                            <?php 
                            $resultBorrowedDropdown->data_seek(0);
                            while ($row = $resultBorrowedDropdown->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($row['b_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($row['b_assets_name'], ENT_QUOTES, 'UTF-8') . " (Qty: " . $row['b_quantity'] . ", Tech: " . $row['b_technician_name'] . ", ID: " . $row['b_technician_id'] . ")"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <span class="error-message" id="error_borrow_id"></span>
                    </div>
                    <div class="form-group">
                        <label for="return_quantity">Quantity to Return</label>
                        <input type="number" id="return_quantity" name="return_quantity" min="1" placeholder="Quantity" required>
                        <span class="error-message" id="error_return_quantity"></span>
                    </div>
                    <div class="form-group">
                        <label for="tech_name">Technician Name</label>
                        <input type="text" id="tech_name" name="tech_name" placeholder="Technician Name" required>
                        <span class="error-message" id="error_tech_name"></span>
                    </div>
                    <div class="form-group">
                        <label for="tech_id">Technician ID</label>
                        <input type="text" id="tech_id" name="tech_id" placeholder="Technician ID" required>
                        <span class="error-message" id="error_tech_id"></span>
                    </div>
                    <div class="form-group">
                        <label for="date">Date Returned</label>
                        <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        <span class="error-message" id="error_date"></span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('returnAssetModal')">Cancel</button>
                        <button type="submit" class="modal-btn confirm">Return Asset</button>
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

        <!-- Technician Name Filter Modal -->
        <div id="technicianNameFilterModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Filter by Technician Name</h2>
                </div>
                <form id="technicianNameFilterForm" class="modal-form">
                    <label for="technician_name_filter">Select Technician Name</label>
                    <select name="technician_name_filter" id="technician_name_filter">
                        <option value="">All Technicians</option>
                        <?php foreach ($uniqueTechnicianNames as $name): ?>
                            <option value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn cancel" onclick="closeModal('technicianNameFilterModal')">Cancel</button>
                        <button type="button" class="modal-btn confirm" onclick="applyTechnicianNameFilter()">Apply Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let currentSearchPage = 1;
let defaultPage = <?php echo json_encode($page); ?>;
let updateInterval = null;
window.currentFilters = { assetName: '', technicianName: '' };

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

function searchBorrowed(page = 1, filterAssetName = '', filterTechName = '') {
    const searchTerm = document.getElementById('searchInput').value;
    const tbody = document.getElementById('tableBody');
    const paginationContainer = document.getElementById('borrowed-pagination');

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
    const url = `borrowedStaff.php?action=search&search=${encodeURIComponent(searchTerm)}&search_page=${searchTerm ? page : defaultPage}` +
                `&filter_asset_name=${encodeURIComponent(filterAssetName)}&filter_technician_name=${encodeURIComponent(filterTechName)}`;
    xhr.open('GET', url, true);
    xhr.send();
}

function updatePagination(currentPage, totalPages, searchTerm) {
    const paginationContainer = document.getElementById('borrowed-pagination');
    let paginationHtml = '';

    if (currentPage > 1) {
        paginationHtml += `<a href="javascript:searchBorrowed(${currentPage - 1})" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="javascript:searchBorrowed(${currentPage + 1})" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

const debouncedSearchBorrowed = debounce((page) => searchBorrowed(page), 300);

function showViewModal(id, assetName, quantity, technicianName, technicianId, date) {
    const modalContent = `
        <p><strong>Asset Name:</strong> ${assetName || 'N/A'}</p>
        <p><strong>Asset Quantity:</strong> ${quantity}</p>
        <p><strong>Technician Name:</strong> ${technicianName}</p>
        <p><strong>Technician ID:</strong> ${technicianId}</p>
        <p><strong>Borrowed Date:</strong> ${date}</p>
    `;
    document.getElementById('viewModalContent').innerHTML = modalContent;
    document.getElementById('viewModal').style.display = 'flex';
}

function showEditModal(id) {
    fetch(`borrowedStaff.php?edit=true&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                return;
            }
            document.querySelectorAll('.error-message').forEach(span => span.textContent = '');
            document.getElementById('edit_b_id').value = id;
            document.getElementById('edit_b_assets_name').value = data.b_assets_name || '';
            document.getElementById('edit_b_quantity').value = data.b_quantity || 1;
            document.getElementById('edit_b_technician_name').value = data.b_technician_name || '';
            document.getElementById('edit_b_technician_id').value = data.b_technician_id || '';
            document.getElementById('edit_b_date').value = data.b_date || '';
            document.getElementById('editBorrowedModal').style.display = 'flex';
        })
        .catch(error => {
            console.error('Error fetching asset data:', error);
            alert('Failed to load asset data.');
        });
}

function showDeleteModal(id, assetName) {
    document.getElementById('deleteAssetName').textContent = assetName || 'Unknown Asset';
    document.getElementById('deleteAssetId').value = id;
    document.getElementById('deleteModal').style.display = 'flex';
}

function showReturnAssetModal() {
    document.querySelectorAll('#returnAssetForm .error-message').forEach(span => span.textContent = '');
    document.getElementById('returnAssetForm').reset();
    document.getElementById('date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('returnAssetModal').style.display = 'flex';
}

function showAssetNameFilterModal() {
    document.getElementById('assetNameFilterModal').style.display = 'flex';
}

function showTechnicianNameFilterModal() {
    document.getElementById('technicianNameFilterModal').style.display = 'flex';
}

function applyAssetNameFilter() {
    const nameSelect = document.getElementById('asset_name_filter');
    const selectedName = nameSelect.value;
    applyFilter(selectedName, null);
    closeModal('assetNameFilterModal');
}

function applyTechnicianNameFilter() {
    const techNameSelect = document.getElementById('technician_name_filter');
    const selectedTechName = techNameSelect.value;
    applyFilter(null, selectedTechName);
    closeModal('technicianNameFilterModal');
}

function applyFilter(selectedAssetName = null, selectedTechName = null) {
    const currentAssetName = selectedAssetName !== null ? selectedAssetName : (window.currentFilters?.assetName || '');
    const currentTechName = selectedTechName !== null ? selectedTechName : (window.currentFilters?.technicianName || '');
    window.currentFilters = { assetName: currentAssetName, technicianName: currentTechName };
    searchBorrowed(1, currentAssetName, currentTechName);
}

function updateTable() {
    const searchTerm = document.getElementById('searchInput').value;
    if (searchTerm) {
        searchBorrowed(currentSearchPage, window.currentFilters.assetName, window.currentFilters.technicianName);
    } else {
        fetch(`borrowedStaff.php?page=${defaultPage}`)
            .then(response => response.text())
            .then(data => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const newTableBody = doc.querySelector('#tableBody');
                const currentTableBody = document.querySelector('#tableBody');
                if (newTableBody) {
                    currentTableBody.innerHTML = newTableBody.innerHTML;
                }
            })
            .catch(error => console.error('Error updating table:', error));
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    if (modalId === 'editBorrowedModal' || modalId === 'returnAssetModal') {
        document.querySelectorAll(`#${modalId} .error-message`).forEach(span => span.textContent = '');
    }
}

window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal(event.target.id);
    }
});

// Handle edit form submission
document.getElementById('editBorrowedForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('borrowedStaff.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.querySelectorAll('#editBorrowedForm .error-message').forEach(span => span.textContent = '');
        
        if (data.success) {
            closeModal('editBorrowedModal');
            updateTable();
            const alertContainer = document.querySelector('.alert-container');
            alertContainer.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    alert.classList.add('alert-hidden');
                    setTimeout(() => alert.remove(), 500);
                }
            }, 2000);
        } else {
            if (data.errors) {
                for (const [field, error] of Object.entries(data.errors)) {
                    const errorSpan = document.getElementById(`error_${field}`);
                    if (errorSpan) {
                        errorSpan.textContent = error;
                    }
                }
            }
            if (data.errors?.general) {
                alert(data.errors.general);
            }
        }
    })
    .catch(error => {
        console.error('Error submitting form:', error);
        alert('Failed to update asset.');
    });
});

// Handle return form submission
document.getElementById('returnAssetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('borrowedStaff.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.querySelectorAll('#returnAssetForm .error-message').forEach(span => span.textContent = '');
        
        if (data.success) {
            closeModal('returnAssetModal');
            updateTable();
            const alertContainer = document.querySelector('.alert-container');
            alertContainer.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    alert.classList.add('alert-hidden');
                    setTimeout(() => alert.remove(), 500);
                }
            }, 2000);
        } else {
            if (data.errors) {
                for (const [field, error] of Object.entries(data.errors)) {
                    const errorSpan = document.getElementById(`error_${field}`);
                    if (errorSpan) {
                        errorSpan.textContent = error;
                    }
                }
            }
            if (data.errors?.general) {
                alert(data.errors.general);
            }
        }
    })
    .catch(error => {
        console.error('Error submitting return form:', error);
        alert('Failed to return asset.');
    });
});

// Initialize auto-update table and handle alerts
document.addEventListener('DOMContentLoaded', () => {
    updateInterval = setInterval(updateTable, 30000);

    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('alert-hidden');
            setTimeout(() => alert.remove(), 500);
        }, 2000);
    });

    const searchInput = document.getElementById('searchInput');
    if (searchInput.value) {
        searchBorrowed();
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