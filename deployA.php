
<?php
session_start();
include 'db.php';

// Initialize variables
$borrow_assetsname = "";
$borrowquantity = ""; 
$borrow_techname = "";
$borrow_techid = "";
$borrowdate = "";

$borrow_assetsnameErr = "";
$borrow_techidErr = "";
$borrow_technameErr = "";
$borrow_quantityErr = ""; 
$hasError = false; 
$successMessage = "";

// Check if the database connection is established
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// User Registration
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $borrow_assetsname = trim($_POST['asset_name']);
    $borrowquantity = trim($_POST['borrow_quantity']);
    $borrow_techname = trim($_POST['tech_name']);
    $borrow_techid = trim($_POST['tech_id']);
    $borrowdate = trim($_POST['date']);

    // Validate asset name
    if (!preg_match("/^[a-zA-Z\s-]+$/", $borrow_assetsname)) {
        $borrow_assetsnameErr = "Asset Name should not contain numbers.";
        $hasError = true;
    }

    // Validate technician name
    if (!preg_match("/^[a-zA-Z\s-]+$/", $borrow_techname)) {
        $borrow_technameErr = "Technician Name should not contain numbers.";
        $hasError = true;
    }

    // Validate borrow quantity
    if (!is_numeric($borrowquantity) || $borrowquantity <= 0) {
        $borrow_quantityErr = "Please enter a valid quantity.";
        $hasError = true;
    } 

    // Validate Technician ID
    if (!$hasError) {
        $sqlCheckTechnician = "SELECT u_id FROM tbl_user WHERE u_id = ?";
        $stmtCheckTechnician = $conn->prepare($sqlCheckTechnician);
        $stmtCheckTechnician->bind_param("s", $borrow_techid);
        $stmtCheckTechnician->execute();
        $resultCheckTechnician = $stmtCheckTechnician->get_result();

        if ($resultCheckTechnician->num_rows == 0) {
            $borrow_techidErr = "Technician ID does not exist.";
            $hasError = true;
        }
        $stmtCheckTechnician->close();
    }

    // Validate Technician Name
    if (!$hasError) {
        $sqlCheckTechName = "SELECT u_fname, u_lname FROM tbl_user WHERE u_id = ?";
        $stmtCheckTechName = $conn->prepare($sqlCheckTechName);
        $stmtCheckTechName->bind_param("s", $borrow_techid);
        $stmtCheckTechName->execute();
        $resultCheckTechName = $stmtCheckTechName->get_result();

        if ($resultCheckTechName->num_rows > 0) {
            $row = $resultCheckTechName->fetch_assoc();
            $fullName = trim($row['u_fname'] . ' ' . $row['u_lname']);
            
            if (strcasecmp($fullName, $borrow_techname) !== 0) {
                $borrow_technameErr = "Technician Name does not match the ID.";
                $hasError = true;
            }
        } else {
            $borrow_techidErr = "Technician ID does not exist.";
            $hasError = true;
        }
        $stmtCheckTechName->close();
    }

    // Check if asset exists
    if (!$hasError) {
        $sqlCheckAsset = "SELECT a_quantity FROM tbl_deployment_assets WHERE a_name = ?";
        $stmtCheckAsset = $conn->prepare($sqlCheckAsset);
        $stmtCheckAsset->bind_param("s", $borrow_assetsname);
        $stmtCheckAsset->execute();
        $resultCheckAsset = $stmtCheckAsset->get_result();

        if ($resultCheckAsset->num_rows > 0) {
            $row = $resultCheckAsset->fetch_assoc();
            $availableQuantity = $row['a_quantity'];

            
            if ($availableQuantity >= $borrowquantity) {
                
                $sqlInsert = "INSERT INTO tbl_deployed (d_assets_name, d_quantity, d_technician_name, d_technician_id, d_date) VALUES (?, ?, ?, ?, ?)";
                $stmtInsert = $conn->prepare($sqlInsert);
                $stmtInsert->bind_param("sisis", $borrow_assetsname, $borrowquantity, $borrow_techname, $borrow_techid, $borrowdate);

                if ($stmtInsert->execute()) {
                   
                    $newQuantity = $availableQuantity - $borrowquantity;
                    $sqlUpdate = "UPDATE tbl_deployment_assets SET a_quantity = ? WHERE a_name = ?";
                    $stmtUpdate = $conn->prepare($sqlUpdate);
                    $stmtUpdate->bind_param("is", $newQuantity, $borrow_assetsname);
                    $stmtUpdate->execute();

                    // Redirect after successful operation
                    header("Location: assetsT.php");
                    exit(); // Ensure no further code is executed
                } else {
                    die("Execution failed: " . $stmtInsert->error);
                }

                $stmtInsert->close();
            } else {
                $borrow_quantityErr = "Not enough quantity available to deploy.";
            }
        } else {
            $borrow_assetsnameErr = "Asset not found in the inventory.";
        }
        $stmtCheckAsset->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deploy Assets</title>
    <link rel="stylesheet" href="deployAS.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
     <div class="wrapper">
       <div class="container">
       <a href="assetsT.php" class="back-icon">
            <i class='bx bx-arrow-back'></i>
        </a>
        <h1>Enter Details to Deploy:</h1>

        <form method="POST" action="" class="form">
        <div class="form-row">
        <label for="asset_name">Asset Name:</label>
        <input type="text" id="asset_name" name="asset_name" placeholder="Asset Name" required>
        <span class="error"><?php echo $borrow_assetsnameErr; ?></span>
        </div>
        <div class="form-row">
        <label for="borrow_quantity">Enter Quantity to Deploy:</label>
        <input type="text" id="borrow_quantity" name="borrow_quantity" placeholder="Quantity" required>
        <span class="error"><?php echo $borrow_quantityErr; ?></span>
        </div>
        <div class="form-row">
        <label for="tech_name">Enter Technician Name:</label>
        <input type="text" id="tech_name" name="tech_name" placeholder="Technician Name" required>
        <span class="error"><?php echo $borrow_technameErr; ?></span>
        </div>
        <div class="form-row">
        <label for="tech_name">Enter Technician Id:</label>
        <input type="text" id="tech_id" name="tech_id" placeholder="Technician Id" required>
        <span class="error"><?php echo $borrow_techidErr; ?></span>
        </div>
        <div class="form-row">
        <label for="date">Date for Deployment:</label>
        <input type="date" id="date" name="date" required>
        </div>
    
     <button type="submit">Enter</button>
     </form>

     </div>
</body>
</html>
