<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Initialize variables
$borrow_assetsname = "";
$borrowquantity = "";
$borrow_techname = "";
$borrow_techid = "";
$borrowdate = date('Y-m-d'); // Default to today
$errors = [];
$successMessage = "";

// Fetch available assets for dropdown
$sqlAssets = "SELECT a_id, a_name, a_quantity FROM tbl_assets WHERE a_status != 'Archived' AND a_quantity > 0";
$resultAssets = $conn->query($sqlAssets);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $asset_id = trim($_POST['asset_id']);
    $borrowquantity = trim($_POST['borrow_quantity']);
    $borrow_techname = trim($_POST['tech_name']);
    $borrow_techid = trim($_POST['tech_id']);
    $borrowdate = trim($_POST['date']);

    // Validate inputs
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
    if (empty($borrowdate)) {
        $errors[] = "Borrow date is required.";
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
                $errors[] = "Technician name does not match the ID.";
            }
        } else {
            $errors[] = "Technician ID does not exist.";
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
            $errors[] = "Technician must return borrowed assets before borrowing again.";
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
                $errors[] = "Requested quantity ($borrowquantity) exceeds available stock ($availableQuantity).";
            }
        } else {
            $errors[] = "Selected asset is not available.";
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
            $_SESSION['message'] = "Asset borrowed successfully!";
            header("Location: assetsT.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error borrowing asset: " . $e->getMessage();
            error_log("Borrow error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Asset</title>
    <link rel="stylesheet" href="borrowsA.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <a href="assetsT.php" class="back-icon">
                <i class='bx bx-arrow-back'></i>
            </a>
            <h1>Enter Details to Borrow</h1>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="form">
                <div class="form-row">
                    <label for="asset_id">Asset Name</label>
                    <select id="asset_id" name="asset_id" required>
                        <option value="">Select Asset</option>
                        <?php while ($row = $resultAssets->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . " (Available: " . $row['a_quantity'] . ")"; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="borrow_quantity">Quantity to Borrow</label>
                    <input type="text" id="borrow_quantity" name="borrow_quantity" placeholder="Quantity" required>
                </div>
                <div class="form-row">
                    <label for="tech_name">Technician Name</label>
                    <input type="text" id="tech_name" name="tech_name" placeholder="Technician Name" required>
                </div>
                <div class="form-row">
                    <label for="tech_id">Technician ID</label>
                    <input type="text" id="tech_id" name="tech_id" placeholder="Technician ID" required>
                </div>
                <div class="form-row">
                    <label for="date">Date Borrowed</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($borrowdate, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <button type="submit">Borrow</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>