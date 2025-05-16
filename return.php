<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Initialize variables
$borrow_id = "";
$return_quantity = "";
$return_techname = "";
$return_techid = "";
$returndate = date('Y-m-d'); // Default to today
$errors = [];
$successMessage = "";

// Fetch borrowed assets for dropdown (from tbl_borrowed only)
$sqlBorrowed = "SELECT b_id, b_assets_name, b_quantity, b_technician_name, b_technician_id 
                FROM tbl_borrowed 
                WHERE b_quantity > 0";
$resultBorrowed = $conn->query($sqlBorrowed);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $borrow_id = trim($_POST['borrow_id']);
    $return_quantity = trim($_POST['return_quantity']);
    $return_techname = trim($_POST['tech_name']);
    $return_techid = trim($_POST['tech_id']);
    $returndate = trim($_POST['date']);

    // Validate inputs
    if (empty($borrow_id)) {
        $errors[] = "Please select a borrowed asset.";
    }
    if (empty($return_quantity) || !is_numeric($return_quantity) || $return_quantity < 1) {
        $errors[] = "Please enter a valid quantity to return (positive number).";
    }
    if (empty($return_techname) || !preg_match("/^[a-zA-Z\s-]+$/", $return_techname)) {
        $errors[] = "Technician name is required and should not contain numbers.";
    }
    if (empty($return_techid)) {
        $errors[] = "Technician ID is required.";
    }
    if (empty($returndate)) {
        $errors[] = "Return date is required.";
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
                $errors[] = "Technician name does not match the ID in tbl_user.";
                error_log("tbl_user mismatch: Input Name: '$return_techname', DB Name: '$fullName'");
            }
        } else {
            $errors[] = "Technician ID does not exist in tbl_user.";
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

            // Log values for debugging
            error_log("Input Tech Name: '$return_techname', DB Tech Name: '$borrowedTechName'");
            error_log("Input Tech ID: '$return_techid', DB Tech ID: '$borrowedTechId'");

            // Check if technician matches the borrowed record (comment out to bypass)
            /*
            if (strcasecmp($return_techname, $borrowedTechName) !== 0 || $return_techid !== $borrowedTechId) {
                $errors[] = "Technician name or ID does not match the borrowed record.";
            }
            */

            // Check if return quantity is valid
            if ($return_quantity > $currentBorrowedQuantity) {
                $errors[] = "Return quantity ($return_quantity) exceeds borrowed quantity ($currentBorrowedQuantity).";
            }
        } else {
            $errors[] = "Selected borrowed asset is not valid or has no quantity to return.";
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
            $_SESSION['message'] = "Asset returned successfully!";
            header("Location: assetsT.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error returning asset: " . $e->getMessage();
            error_log("Return error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Asset</title>
    <link rel="stylesheet" href="returnA.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <a href="assetsT.php" class="back-icon">
                <i class='bx bx-arrow-back'></i>
            </a>
            <h1>Enter Details to Return</h1>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="form">
                <div class="form-row">
                    <label for="borrow_id">Borrowed Asset</label>
                    <select id="borrow_id" name="borrow_id" required>
                        <option value="">Select Borrowed Asset</option>
                        <?php while ($row = $resultBorrowed->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($row['b_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($row['b_assets_name'], ENT_QUOTES, 'UTF-8') . " (Qty: " . $row['b_quantity'] . ", Tech: " . $row['b_technician_name'] . ", ID: " . $row['b_technician_id'] . ")"; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="return_quantity">Quantity to Return</label>
                    <input type="text" id="return_quantity" name="return_quantity" placeholder="Quantity" required>
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
                    <label for="date">Date Returned</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($returndate, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <button type="submit">Return</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>