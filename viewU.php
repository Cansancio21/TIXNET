

<?php
session_start();

// Include PHPMailer dependencies
require 'PHPmailer-master/PHPmailer-master/src/Exception.php';
require 'PHPmailer-master/PHPmailer-master/src/PHPMailer.php';
require 'PHPmailer-master/PHPmailer-master/src/SMTP.php';

// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];

// Initialize user detail variables
$firstName = '';
$lastName = '';
$userType = '';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $username . '.png';

// Initialize avatarPath from session or default
$avatarPath = isset($_SESSION['avatarPath']) ? $_SESSION['avatarPath'] : 'default-avatar.png';

// Update session only if the file exists
$cleanAvatarPath = preg_replace('/\?\d+$/', '', $avatarPath);
if (file_exists($userAvatar) && $cleanAvatarPath !== $userAvatar) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} elseif (!file_exists($cleanAvatarPath) && $avatarPath !== 'default-avatar.png') {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$avatarPath = $_SESSION['avatarPath'];

// Fetch user details for display
if ($conn) {
    $sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sqlUser);
    if (!$stmt) {
        error_log("Prepare failed for user details: " . $conn->error);
    } else {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $resultUser = $stmt->get_result();
        if ($resultUser->num_rows > 0) {
            $row = $resultUser->fetch_assoc();
            $firstName = $row['u_fname'];
            $lastName = $row['u_lname'];
            $userType = $row['u_type'];
        }
        $stmt->close();
    }
}

// Check if tbl_user exists
$result = $conn->query("SHOW TABLES LIKE 'tbl_user'");
if ($result->num_rows == 0) {
    die("Error: Table 'tbl_user' does not exist in the 'task_management' database.");
}

// Handle AJAX request for tab counts
if (isset($_GET['action']) && $_GET['action'] === 'get_counts') {
    header('Content-Type: application/json');
    $activeCountQuery = "SELECT COUNT(*) as total FROM tbl_user WHERE u_status IN ('active', 'pending')";
    $activeCountResult = $conn->query($activeCountQuery);
    $activeCount = $activeCountResult->fetch_assoc()['total'];

    $archivedCountQuery = "SELECT COUNT(*) as total FROM tbl_user WHERE u_status = 'archived'";
    $archivedCountResult = $conn->query($archivedCountQuery);
    $archivedCount = $archivedCountResult->fetch_assoc()['total'];

    echo json_encode([
        'activeCount' => $activeCount,
        'archivedCount' => $archivedCount
    ]);
    exit();
}

// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['search'])) {
    header('Content-Type: application/json');
    $searchTerm = trim($_GET['search']);
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'active';
    $page = isset($_GET['search_page']) ? (int)$_GET['search_page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $typeFilter = isset($_GET['type']) ? trim($_GET['type']) : '';
    $output = '';
    $response = ['html' => '', 'currentPage' => $page, 'totalPages' => 0, 'tab' => $tab, 'searchTerm' => $searchTerm];

    if ($tab === 'active') {
        // Build the WHERE clause dynamically for active/pending users
        $whereClauses = ["u_status IN ('active', 'pending')"];
        $params = [];
        $paramTypes = '';

        if ($searchTerm !== '') {
            $whereClauses[] = "(u_fname LIKE ? OR u_lname LIKE ? OR u_email LIKE ? OR u_username LIKE ?)";
            $searchWildcard = "%$searchTerm%";
            $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
            $paramTypes .= 'ssss';
        }

        // FIXED: Properly handle status filter for active tab
        if ($statusFilter !== '' && in_array($statusFilter, ['active', 'pending'])) {
            $whereClauses[] = "u_status = ?";
            $params[] = $statusFilter;
            $paramTypes .= 's';
        }

        if ($typeFilter !== '' && in_array($typeFilter, ['admin', 'staff', 'technician'])) {
            $whereClauses[] = "u_type = ?";
            $params[] = $typeFilter;
            $paramTypes .= 's';
        }

        $whereClause = implode(' AND ', $whereClauses);

        // Count total users for pagination
        $countSql = "SELECT COUNT(*) as total FROM tbl_user WHERE $whereClause";
        $countStmt = $conn->prepare($countSql);
        if ($paramTypes !== '') {
            $countStmt->bind_param($paramTypes, ...$params);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalUsers = $countResult->fetch_assoc()['total'];
        $countStmt->close();

        $totalPages = ceil($totalUsers / $limit);

        // Fetch users with filters
        $sql = "SELECT u_id, u_fname, u_lname, u_email, u_username, u_type, u_status FROM tbl_user 
                WHERE $whereClause 
                LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        $params[] = $offset;
        $params[] = $limit;
        $paramTypes .= 'ii';
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $output .= "<tr> 
                            <td>{$row['u_id']}</td> 
                            <td>" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                            <td>" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                            <td>" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "</td> 
                            <td>" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "</td> 
                            <td>" . ucfirst(strtolower($row['u_type'])) . "</td> 
                            <td class='status-" . strtolower($row['u_status']) . "'>" . ucfirst(strtolower($row['u_status'])) . "</td>
                            <td class='action-buttons'>
                                <a class='view-btn' onclick=\"showViewModal('{$row['u_id']}', '" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_type'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_status'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                <a class='edit-btn' onclick=\"showEditUserModal('{$row['u_id']}', '" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_type'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_status'], ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                                <a class='archive-btn' onclick=\"showArchiveModal('{$row['u_id']}', '" . htmlspecialchars($row['u_fname'] . ' ' . $row['u_lname'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>
                            </td>
                            </tr>";
            }
        } else {
            $output = "<tr><td colspan='8' style='text-align: center;'>No active or pending users found.</td></tr>";
        }
        $stmt->close();

        $response['html'] = $output;
        $response['totalPages'] = $totalPages;
    } else {
        // Archived users
        $whereClauses = ["u_status = 'archived'"];
        $params = [];
        $paramTypes = '';
    
        if ($searchTerm !== '') {
            $whereClauses[] = "(u_fname LIKE ? OR u_lname LIKE ? OR u_email LIKE ? OR u_username LIKE ?)";
            $searchWildcard = "%$searchTerm%";
            $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
            $paramTypes .= 'ssss';
        }
    
        if ($typeFilter !== '' && in_array($typeFilter, ['admin', 'staff', 'technician'])) {
            $whereClauses[] = "u_type = ?";
            $params[] = $typeFilter;
            $paramTypes .= 's';
        }
    
        $whereClause = implode(' AND ', $whereClauses);
    
        // Count total archived users for pagination
        $countSql = "SELECT COUNT(*) as total FROM tbl_user WHERE $whereClause";
        $countStmt = $conn->prepare($countSql);
        if ($paramTypes !== '') {
            $countStmt->bind_param($paramTypes, ...$params);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalUsers = $countResult->fetch_assoc()['total'];
        $countStmt->close();
    
        $totalPages = ceil($totalUsers / $limit);
    
        // Fetch archived users
        $sql = "SELECT u_id, u_fname, u_lname, u_email, u_username, u_type, u_status 
                FROM tbl_user 
                WHERE $whereClause 
                LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        $params[] = $offset;
        $params[] = $limit;
        $paramTypes .= 'ii';
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Get original status from session
                $originalStatus = isset($_SESSION['original_status_' . $row['u_id']]) ? $_SESSION['original_status_' . $row['u_id']] : 'active';
                $output .= "<tr> 
                            <td>{$row['u_id']}</td> 
                            <td>" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                            <td>" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                            <td>" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "</td> 
                            <td>" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "</td> 
                            <td>" . ucfirst(strtolower($row['u_type'])) . "</td> 
                            <td class='status-" . strtolower($originalStatus) . "'>" . ucfirst(strtolower($originalStatus)) . "</td>
                            <td class='action-buttons'>
                                <a class='view-btn' onclick=\"showViewModal('{$row['u_id']}', '" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_type'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($originalStatus, ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                <a class='unarchive-btn' onclick=\"showRestoreModal('{$row['u_id']}', '" . htmlspecialchars($row['u_fname'] . ' ' . $row['u_lname'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                                <a class='delete-btn' onclick=\"showDeleteModal('{$row['u_id']}', '" . htmlspecialchars($row['u_fname'] . ' ' . $row['u_lname'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                            </td>
                            </tr>";
            }
        } else {
            $output = "<tr><td colspan='8' style='text-align: center;'>No archived users found.</td></tr>";
        }
        $stmt->close();
    
        $response['html'] = $output;
        $response['totalPages'] = $totalPages;
    }

    echo json_encode($response);
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'active';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $archivedPage = isset($_GET['archived_page']) ? (int)$_GET['archived_page'] : 1;

    if (isset($_POST['add_user'])) {
        // Get form data and sanitize
        $firstname = trim($_POST['firstname']);
        $lastname = trim($_POST['lastname']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $type = trim($_POST['type']);
        $status = trim($_POST['status']);

        // Validation
        $errors = [];
        if (empty($firstname) || !preg_match("/^[a-zA-Z\s-]+$/", $firstname)) {
            $errors['firstname'] = "Firstname is required and must not contain numbers.";
        }
        if (empty($lastname) || !preg_match("/^[a-zA-Z\s-]+$/", $lastname)) {
            $errors['lastname'] = "Lastname is required and must not contain numbers.";
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "A valid email is required.";
        }
        if (empty($username)) {
            $errors['username'] = "Username is required.";
        }
        if (!preg_match("/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $password)) {
            $errors['password'] = "Password must be at least 8 characters, with 1 uppercase letter, 1 number, and 1 special character.";
        }
        if (empty($type) || !in_array($type, ['staff', 'technician'])) {
            $errors['type'] = "Account type must be Staff or Technician.";
        }
        if (empty($status) || !in_array($status, ['active', 'pending'])) {
            $errors['status'] = "Account status must be Active or Pending.";
        }

        // Check for duplicate username
        $checkSql = "SELECT u_id FROM tbl_user WHERE u_username = ?";
        $checkStmt = $conn->prepare($checkSql);
        if (!$checkStmt) {
            error_log("Prepare failed for username check: " . $conn->error);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: Unable to check username.']);
            exit();
        }
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $errors['username'] = "Username already exists.";
        }
        $checkStmt->close();

        if (empty($errors)) {
            // Hash the password
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // Prepare and execute the INSERT query
            $sql = "INSERT INTO tbl_user (u_fname, u_lname, u_email, u_username, u_password, u_type, u_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Prepare failed for user insertion: " . $conn->error);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Database error: Unable to prepare statement.']);
                exit();
            }
            $stmt->bind_param("sssssss", $firstname, $lastname, $email, $username, $passwordHash, $type, $status);
            if ($stmt->execute()) {
                // Send email in background without waiting for response
                $emailSent = sendQuickEmail($firstname, $lastname, $email, $username, $password);
                
                $stmt->close();
                header('Content-Type: application/json');
                if ($emailSent) {
                    echo json_encode(['success' => true, 'message' => 'User has been registered successfully. A confirmation email has been sent.']);
                } else {
                    echo json_encode(['success' => true, 'message' => 'User registered successfully. Email notification may be delayed.']);
                }
                exit();
            } else {
                error_log("Execution failed: " . $stmt->error);
                $stmt->close();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Database error: Unable to register user.']);
                exit();
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }
    } elseif (isset($_POST['edit_user'])) {
        $user_id = (int)$_POST['u_id'];
        $firstname = trim($_POST['firstname']);
        $lastname = trim($_POST['lastname']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $type = trim($_POST['type']);
        $status = trim($_POST['status']);

        // Validation
        $errors = [];
        if (empty($firstname) || !preg_match("/^[a-zA-Z\s-]+$/", $firstname)) {
            $errors['firstname'] = "Firstname is required and must not contain numbers.";
        }
        if (empty($lastname) || !preg_match("/^[a-zA-Z\s-]+$/", $lastname)) {
            $errors['lastname'] = "Lastname is required and must not contain numbers.";
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "A valid email is required.";
        }
        if (empty($username)) {
            $errors['username'] = "Username is required.";
        }
        if (empty($type) || !in_array($type, ['admin', 'staff', 'technician'])) {
            $errors['type'] = "Account type must be Admin, Staff, or Technician.";
        }
        if (empty($status) || !in_array($status, ['active', 'pending', 'archived'])) {
            $errors['status'] = "Account status must be Active, Pending, or Archived.";
        }

        // Check for duplicate username (excluding current user)
        $checkSql = "SELECT u_id FROM tbl_user WHERE u_username = ? AND u_id != ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("si", $username, $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $errors['username'] = "Username already exists.";
        }
        $checkStmt->close();

        if (empty($errors)) {
            $update_sql = "UPDATE tbl_user SET u_fname=?, u_lname=?, u_email=?, u_username=?, u_type=?, u_status=? WHERE u_id=?";
            $update_stmt = $conn->prepare($update_sql);
            if (!$update_stmt) {
                error_log("Prepare failed for update: " . $conn->error);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
                exit();
            }
            $update_stmt->bind_param("ssssssi", $firstname, $lastname, $email, $username, $type, $status, $user_id);

            if ($update_stmt->execute()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'User updated successfully!']);
            } else {
                error_log("Update execution failed: " . $update_stmt->error);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $update_stmt->error]);
            }
            $update_stmt->close();
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
        }
        exit();
    } elseif (isset($_POST['archive_user'])) {
        $id = (int)$_POST['u_id'];
        
        // First get the current status
        $getStatusSql = "SELECT u_status FROM tbl_user WHERE u_id = ?";
        $getStmt = $conn->prepare($getStatusSql);
        $getStmt->bind_param("i", $id);
        $getStmt->execute();
        $result = $getStmt->get_result();
        $user = $result->fetch_assoc();
        $originalStatus = $user['u_status'];
        $getStmt->close();
        
        // Store original status in session
        $_SESSION['original_status_' . $id] = $originalStatus;
        
        // Now archive the user
        $sql = "UPDATE tbl_user SET u_status='archived' WHERE u_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'User archived successfully!']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to archive user.']);
        }
        $stmt->close();
        exit();
    } elseif (isset($_POST['restore_user'])) {
        $id = (int)$_POST['u_id'];
        
        // Get the original status from session
        $originalStatus = isset($_SESSION['original_status_' . $id]) ? $_SESSION['original_status_' . $id] : 'active';
        
        // Restore to original status
        $sql = "UPDATE tbl_user SET u_status=? WHERE u_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $originalStatus, $id);
        if ($stmt->execute()) {
            // Remove from session
            unset($_SESSION['original_status_' . $id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'User unarchived successfully!']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to unarchive user.']);
        }
        $stmt->close();
        exit();
    } elseif (isset($_POST['restore_all_users'])) {
        $sql = "UPDATE tbl_user SET u_status='active' WHERE u_status='archived'";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute()) {
            // Clear all original status sessions
            foreach ($_SESSION as $key => $value) {
                if (strpos($key, 'original_status_') === 0) {
                    unset($_SESSION[$key]);
                }
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'All users restored successfully!']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to restore all users.']);
        }
        $stmt->close();
        exit();
    } elseif (isset($_POST['delete_user'])) {
        $id = (int)$_POST['u_id'];
        $sql = "DELETE FROM tbl_user WHERE u_id=? AND u_status='archived'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            // Remove from session
            unset($_SESSION['original_status_' . $id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'User deleted from archive successfully!']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete user.']);
        }
        $stmt->close();
        exit();
    }
}

// Quick email function with timeout to prevent delays
function sendQuickEmail($firstname, $lastname, $email, $username, $password) {
    try {
        $mail = new PHPMailer(true);
        
        // Set short timeout
        $mail->Timeout = 10; // 10 seconds timeout
        $mail->SMTPDebug = 0; // No debug output
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'jonwilyammayormita@gmail.com';
        $mail->Password = 'mqkcqkytlwurwlks';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('jonwilyammayormita@gmail.com', 'TIXNET System');
        $mail->addAddress($email, "$firstname $lastname");
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Account Has Been Created';
        $mail->Body = "
            <html>
            <head>
                <title>Your Account Details</title>
            </head>
            <body>
                <p>Dear $firstname $lastname,</p>
                <p>Your account has been successfully created. Here are your login credentials:</p>
                <p><strong>Username:</strong> $username</p>
                <p><strong>Password:</strong> $password</p>
                <p>Please use these credentials to log in to our system by clicking the link below:</p>
                <p><a href='http://localhost/TIXNET/index.php'>Login Page</a></p>
                <p>For security reasons, we recommend changing your password after first login.</p>
                <p>Best regards,<br>Your System Administrator</p>
            </body>
            </html>
        ";
        $mail->AltBody = "Dear $firstname $lastname,\n\nYour account has been successfully created. Here are your login credentials:\nUsername: $username\nPassword: $password\n\nPlease use these credentials to log in to our system at http://localhost/TIMSSS/index.php\n\nFor security reasons, we recommend changing your password after first login.\n\nBest regards,\nYour System Administrator";
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Quick email error: " . $e->getMessage());
        return false;
    }
}

// Pagination for active and pending users
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$countQuery = "SELECT COUNT(*) as total FROM tbl_user WHERE u_status IN ('active', 'pending')";
$countResult = $conn->query($countQuery);
$totalUsers = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $limit);

$sql = "SELECT u_id, u_fname, u_lname, u_email, u_username, u_type, u_status FROM tbl_user WHERE u_status IN ('active', 'pending') LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$activeAndPendingUsers = $stmt->get_result();
$stmt->close();

// Pagination for archived users
$archivedPage = isset($_GET['archived_page']) ? (int)$_GET['archived_page'] : 1;
$archivedOffset = ($archivedPage - 1) * $limit;

$archivedCountQuery = "SELECT COUNT(*) as total FROM tbl_user WHERE u_status = 'archived'";
$archivedCountResult = $conn->query($archivedCountQuery);
$totalArchived = $archivedCountResult->fetch_assoc()['total'];
$totalArchivedPages = ceil($totalArchived / $limit);

$archivedUsersQuery = "SELECT u_id, u_fname, u_lname, u_email, u_username, u_type, u_status 
                       FROM tbl_user WHERE u_status = 'archived' LIMIT ?, ?";
$stmt = $conn->prepare($archivedUsersQuery);
$stmt->bind_param("ii", $archivedOffset, $limit);
$stmt->execute();
$archivedResult = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | User Management</title>
    <link rel="stylesheet" href="viewU.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

    <style>
        .password-container {
            position: relative;
        }
        .password-container input {
            padding-right: 35px;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--primary);
        }
        .error {
            color: var(--danger);
            font-size: 12px;
            display: block;
        }
        .filter-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 15px;
            color: var(--light, #f5f8fc);
            margin-left: 5px;
            vertical-align: middle;
        }
        .filter-btn:hover {
            color: var(--primary-dark, hsl(211, 45.70%, 84.10%));
        }
        .btn-processing {
            opacity: 0.7;
            pointer-events: none;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
           <li><a href="adminD.php"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
           <li><a href="viewU.php" class="active"><i class="fas fa-users icon"></i> <span>View Users</span></a></li>
           <li><a href="regular_close.php"><i class="fas fa-ticket-alt icon"></i> <span>Ticket Record</span></a></li>
           <li><a href="logs.php"><i class="fas fa-file-alt icon"></i> <span>Logs</span></a></li>
           <li><a href="returnT.php"><i class="fas fa-box icon"></i> <span>Asset Record</span></a></li>
           <li><a href="AdminPayments.php"><i class="fas fa-credit-card icon"></i> <span>Transactions</span></a></li>
         </ul>
      <footer>
       <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </footer>
    </div>

    <div class="container">
        <div class="upper"> 
            <h1>Registered User</h1>
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
        
        <div class="alert-container"></div>

        <div class="table-box glass-container">
            <?php if ($userType === 'admin'): ?>
                <div class="username">
                    Welcome, <?php echo htmlspecialchars($firstName); ?>!
                    <i class="fas fa-user-shield admin-icon"></i>
                </div>
            <?php endif; ?>
            
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="showTab('active')">User (<?php echo $totalUsers; ?>)</button>
                <button class="tab-btn" onclick="showTab('archived')">
                    Archived
                    <?php if ($totalArchived > 0): ?>
                        <span class="tab-badge"><?php echo $totalArchived; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search users..." onkeyup="debouncedSearchUsers()">
                <span class="search-icon"><i class="fas fa-search"></i></span>
            </div>
            
            <div id="active-users-tab" class="active">
                <div>
                    <button class="add-user-btn" onclick="showAddUserModal()"><i class="fas fa-user-plus"></i> Add User</button>
                </div>
                <table id="active-users-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Firstname</th>
                            <th>Lastname</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Type <button class="filter-btn" onclick="showTypeFilterModal('active')" title="Filter by Type"><i class='bx bx-filter'></i></button></th>
                            <th>Status <button class="filter-btn" onclick="showStatusFilterModal('active')" title="Filter by Status"><i class='bx bx-filter'></i></button></th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="active-users-tbody">
                        <?php 
                        if ($activeAndPendingUsers->num_rows > 0) { 
                            while ($row = $activeAndPendingUsers->fetch_assoc()) { 
                                echo "<tr> 
                                        <td>{$row['u_id']}</td> 
                                        <td>" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . ucfirst(strtolower($row['u_type'])) . "</td> 
                                        <td class='status-" . strtolower($row['u_status']) . "'>" . ucfirst(strtolower($row['u_status'])) . "</td>
                                        <td class='action-buttons'>
                                            <a class='view-btn' onclick=\"showViewModal('{$row['u_id']}', '" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_type'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_status'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                            <a class='edit-btn' onclick=\"showEditUserModal('{$row['u_id']}', '" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_type'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_status'], ENT_QUOTES, 'UTF-8') . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                                            <a class='archive-btn' onclick=\"showArchiveModal('{$row['u_id']}', '" . htmlspecialchars($row['u_fname'] . ' ' . $row['u_lname'], ENT_QUOTES, 'UTF-8') . "')\" title='Archive'><i class='fas fa-archive'></i></a>
                                        </td>
                                    </tr>"; 
                            } 
                        } else { 
                            echo "<tr><td colspan='8' style='text-align: center;'>No active or pending users found.</td></tr>"; 
                        } 
                        ?>
                    </tbody>
                </table>

                <div class="pagination" id="active-users-pagination">
                    <?php if ($page > 1): ?>
                        <a href="javascript:searchUsers(<?php echo $page - 1; ?>)" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>

                    <span class="current-page">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

                    <?php if ($page < $totalPages): ?>
                        <a href="javascript:searchUsers(<?php echo $page + 1; ?>)" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div id="archived-users-tab">
                <div class="archive-header">
                    <?php if ($totalArchived > 0): ?>
                        <button class="restore-all-btn" onclick="showRestoreAllModal()"><i class="fas fa-box-open"></i> Restore All</button>
                    <?php endif; ?>
                </div>
                <table id="archived-users-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Firstname</th>
                            <th>Lastname</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Type <button class="filter-btn" onclick="showTypeFilterModal('archived')" title="Filter by Type"><i class='bx bx-filter'></i></button></th>
                            <th>Status <button class="filter-btn" onclick="showStatusFilterModal('archived')" title="Filter by Status"><i class='bx bx-filter'></i></button></th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="archived-users-tbody">
    <?php 
    if ($archivedResult->num_rows > 0) { 
        while ($row = $archivedResult->fetch_assoc()) { 
            // Get original status from session
            $originalStatus = isset($_SESSION['original_status_' . $row['u_id']]) ? $_SESSION['original_status_' . $row['u_id']] : 'active';
            echo "<tr> 
                    <td>{$row['u_id']}</td> 
                    <td>" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                    <td>" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                    <td>" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "</td> 
                    <td>" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "</td> 
                    <td>" . ucfirst(strtolower($row['u_type'])) . "</td> 
                    <td class='status-" . strtolower($originalStatus) . "'>" . ucfirst(strtolower($originalStatus)) . "</td>
                    <td class='action-buttons'>
                        <a class='view-btn' onclick=\"showViewModal('{$row['u_id']}', '" . htmlspecialchars($row['u_fname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_lname'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_email'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_username'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['u_type'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($originalStatus, ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                        <a class='unarchive-btn' onclick=\"showRestoreModal('{$row['u_id']}', '" . htmlspecialchars($row['u_fname'] . ' ' . $row['u_lname'], ENT_QUOTES, 'UTF-8') . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                        <a class='delete-btn' onclick=\"showDeleteModal('{$row['u_id']}', '" . htmlspecialchars($row['u_fname'] . ' ' . $row['u_lname'], ENT_QUOTES, 'UTF-8') . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                    </td>
                </tr>"; 
        } 
    } else { 
        echo "<tr><td colspan='8' style='text-align: center;'>No archived users found.</td></tr>"; 
    } 
    ?>
</tbody>
                </table>
                
                <?php if ($totalArchived > 0): ?>
                <div class="pagination" id="archived-users-pagination">
                    <?php if ($archivedPage > 1): ?>
                        <a href="javascript:searchUsers(<?php echo $archivedPage - 1; ?>)" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <span class="current-page">Page <?php echo $archivedPage; ?> of <?php echo $totalArchivedPages; ?></span>
                    <?php if ($archivedPage < $totalArchivedPages): ?>
                        <a href="javascript:searchUsers(<?php echo $archivedPage + 1; ?>)" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>User Details</h2>
            </div>
            <div id="viewContent"></div>
            <div class="modal-footer">
                <button class="modal-btn cancel" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Archive User Modal -->
    <div id="archiveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Archive User</h2>
            </div>
            <p>Are you sure you want to archive <span id="archiveUserName"></span>?</p>
            <form method="POST" id="archiveForm">
                <input type="hidden" name="u_id" id="archiveUserId">
                <input type="hidden" name="archive_user" value="1">
                <input type="hidden" name="ajax" value="true">
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('archiveModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm">Archive</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Restore User Modal -->
    <div id="restoreModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Unarchive User</h2>
            </div>
            <p>Are you sure you want to unarchive <span id="restoreUserName"></span>?</p>
            <form method="POST" id="restoreForm">
                <input type="hidden" name="u_id" id="restoreUserId">
                <input type="hidden" name="restore_user" value="1">
                <input type="hidden" name="ajax" value="true">
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('restoreModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm">Unarchive</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete User</h2>
            </div>
            <p>Are you sure you want to delete <span id="deleteUserName"></span> from the archive? This action cannot be undone.</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="u_id" id="deleteUserId">
                <input type="hidden" name="delete_user" value="1">
                <input type="hidden" name="ajax" value="true">
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Restore All Users Modal -->
    <div id="restoreAllModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Restore All Users</h2>
            </div>
            <p>Are you sure you want to restore all archived users?</p>
            <form method="POST" id="restoreAllForm">
                <input type="hidden" name="restore_all_users" value="1">
                <input type="hidden" name="ajax" value="true">
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('restoreAllModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm">Restore All</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New User</h2>
            </div>
            <form method="POST" id="addUserForm" class="modal-form">
                <input type="hidden" name="add_user" value="1">
                <input type="hidden" name="ajax" value="true">
                <div class="form-group">
                    <label for="add_firstname">First Name</label>
                    <input type="text" name="firstname" id="add_firstname" required>
                    <span class="error" id="add_firstname_error"></span>
                </div>
                <div class="form-group">
                    <label for="add_lastname">Last Name</label>
                    <input type="text" name="lastname" id="add_lastname" required>
                    <span class="error" id="add_lastname_error"></span>
                </div>
                <div class="form-group">
                    <label for="add_email">Email</label>
                    <input type="email" name="email" id="add_email" required>
                    <span class="error" id="add_email_error"></span>
                </div>
                <div class="form-group">
                    <label for="add_username">Username</label>
                    <input type="text" name="username" id="add_username" required>
                    <span class="error" id="add_username_error"></span>
                </div>
                <div class="form-group">
                    <label for="add_password">Password</label>
                    <div class="password-container">
                        <input type="password" name="password" id="add_password" required>
                        <i class="fas fa-eye password-toggle" id="toggleAddPassword"></i>
                    </div>
                    <span class="error" id="add_password_error"></span>
                </div>
                <div class="form-group">
                    <label for="add_type">Account Type</label>
                    <select name="type" id="add_type" required>
                        <option value="staff">Staff</option>
                        <option value="technician">Technician</option>
                    </select>
                    <span class="error" id="add_type_error"></span>
                </div>
                <div class="form-group">
                    <label for="add_status">Account Status</label>
                    <select name="status" id="add_status" required>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                    </select>
                    <span class="error" id="add_status_error"></span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('addUserModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm" id="addUserSubmit">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User</h2>
            </div>
            <form method="POST" id="editUserForm" class="modal-form">
                <input type="hidden" name="edit_user" value="1">
                <input type="hidden" name="ajax" value="true">
                <input type="hidden" name="u_id" id="edit_u_id">
                <div class="form-group">
                    <label for="edit_firstname">First Name</label>
                    <input type="text" name="firstname" id="edit_firstname" required>
                    <span class="error" id="edit_firstname_error"></span>
                </div>
                <div class="form-group">
                    <label for="edit_lastname">Last Name</label>
                    <input type="text" name="lastname" id="edit_lastname" required>
                    <span class="error" id="edit_lastname_error"></span>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" name="email" id="edit_email" required>
                    <span class="error" id="edit_email_error"></span>
                </div>
                <div class="form-group">
                    <label for="edit_username">Username</label>
                    <input type="text" name="username" id="edit_username" required>
                    <span class="error" id="edit_username_error"></span>
                </div>
                <div class="form-group">
                    <label for="edit_type">Type</label>
                    <select name="type" id="edit_type" required>
                        <option value="admin">Admin</option>
                        <option value="staff">Staff</option>
                        <option value="technician">Technician</option>
                    </select>
                    <span class="error" id="edit_type_error"></span>
                </div>
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select name="status" id="edit_status" required>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="archived">Archived</option>
                    </select>
                    <span class="error" id="edit_status_error"></span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('editUserModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm" id="editUserSubmit">Confirm User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Type Filter Modal -->
    <div id="typeFilterModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Filter by Type</h2>
            </div>
            <form id="typeFilterForm" class="modal-form">
                <input type="hidden" name="ajax" value="true">
                <input type="hidden" id="typeFilterTab" name="tab" value="">
                <div class="form-group">
                    <label for="filter_type">Type</label>
                    <select name="type" id="filter_type">
                        <option value="">All Types</option>
                        <option value="admin">Admin</option>
                        <option value="staff">Staff</option>
                        <option value="technician">Technician</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('typeFilterModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Status Filter Modal -->
    <div id="statusFilterModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Filter by Status</h2>
            </div>
            <form id="statusFilterForm" class="modal-form">
                <input type="hidden" name="ajax" value="true">
                <input type="hidden" id="statusFilterTab" name="tab" value="">
                <div class="form-group">
                    <label for="filter_status">Status</label>
                    <select name="status" id="filter_status">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('statusFilterModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentSearchPage = 1;
        let currentTab = '<?php echo isset($_GET['tab']) ? $_GET['tab'] : 'active'; ?>';
        let activePage = <?php echo $page; ?>;
        let archivedPage = <?php echo $archivedPage; ?>;
        let currentStatusFilter = '<?php echo isset($_GET['status']) ? $_GET['status'] : ''; ?>';
        let currentTypeFilter = '<?php echo isset($_GET['type']) ? $_GET['type'] : ''; ?>';

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

        function showTab(tabName) {
            const activeTab = document.getElementById('active-users-tab');
            const archivedTab = document.getElementById('archived-users-tab');
            const buttons = document.querySelectorAll('.tab-btn');

            activeTab.classList.remove('active');
            archivedTab.classList.remove('active');
            buttons.forEach(btn => btn.classList.remove('active'));

            if (tabName === 'active') {
                activeTab.classList.add('active');
                buttons[0].classList.add('active');
                currentTab = 'active';
                currentSearchPage = activePage;
            } else if (tabName === 'archived') {
                archivedTab.classList.add('active');
                buttons[1].classList.add('active');
                currentTab = 'archived';
                currentSearchPage = archivedPage;
            }
            searchUsers(currentSearchPage);
        }

        // Fixed showViewModal function with status colors
        function showViewModal(id, fname, lname, email, username, type, status) {
            function escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            const safeId = escapeHtml(String(id));
            const safeFname = escapeHtml(fname);
            const safeLname = escapeHtml(lname);
            const safeEmail = escapeHtml(email);
            const safeUsername = escapeHtml(username);
            const safeType = escapeHtml(type);
            const safeStatus = escapeHtml(status);

            const viewContent = document.getElementById('viewContent');
            if (viewContent) {
                viewContent.innerHTML = `
                    <p><strong>ID:</strong> ${safeId}</p>
                    <p><strong>First Name:</strong> ${safeFname}</p>
                    <p><strong>Last Name:</strong> ${safeLname}</p>
                    <p><strong>Email:</strong> ${safeEmail}</p>
                    <p><strong>Username:</strong> ${safeUsername}</p>
                    <p><strong>Type:</strong> ${safeType.charAt(0).toUpperCase() + safeType.slice(1).toLowerCase()}</p>
                    <p><strong>Status:</strong> <span class="status-${safeStatus.toLowerCase()}">${safeStatus.charAt(0).toUpperCase() + safeStatus.slice(1).toLowerCase()}</span></p>
                `;
                const modal = document.getElementById('viewModal');
                if (modal) {
                    modal.style.display = 'flex';
                }
            }
        }

        function showAddUserModal() {
            const modal = document.getElementById('addUserModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function showEditUserModal(id, fname, lname, email, username, type, status) {
            function escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            const safeId = escapeHtml(String(id));
            const safeFname = escapeHtml(fname);
            const safeLname = escapeHtml(lname);
            const safeEmail = escapeHtml(email);
            const safeUsername = escapeHtml(username);
            const safeType = escapeHtml(type);
            const safeStatus = escapeHtml(status);

            document.getElementById('edit_u_id').value = safeId;
            document.getElementById('edit_firstname').value = safeFname;
            document.getElementById('edit_lastname').value = safeLname;
            document.getElementById('edit_email').value = safeEmail;
            document.getElementById('edit_username').value = safeUsername;
            document.getElementById('edit_type').value = safeType.toLowerCase();
            document.getElementById('edit_status').value = safeStatus.toLowerCase();

            const modal = document.getElementById('editUserModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function showArchiveModal(id, name) {
            document.getElementById('archiveUserId').value = id;
            document.getElementById('archiveUserName').textContent = name;
            const modal = document.getElementById('archiveModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function showRestoreModal(id, name) {
            document.getElementById('restoreUserId').value = id;
            document.getElementById('restoreUserName').textContent = name;
            const modal = document.getElementById('restoreModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function showDeleteModal(id, name) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteUserName').textContent = name;
            const modal = document.getElementById('deleteModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function showRestoreAllModal() {
            const modal = document.getElementById('restoreAllModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function showTypeFilterModal(tab) {
            document.getElementById('typeFilterTab').value = tab;
            const modal = document.getElementById('typeFilterModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function showStatusFilterModal(tab) {
            document.getElementById('statusFilterTab').value = tab;
            const modal = document.getElementById('statusFilterModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
            if (modalId === 'addUserModal') {
                document.querySelectorAll('#addUserForm .error').forEach(el => el.textContent = '');
                document.getElementById('addUserForm').reset();
                const passwordInput = document.getElementById('add_password');
                const toggleIcon = document.getElementById('toggleAddPassword');
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
                // Reset submit button
                const submitBtn = document.getElementById('addUserSubmit');
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-processing');
                submitBtn.textContent = 'Add User';
            } else if (modalId === 'editUserModal') {
                document.querySelectorAll('#editUserForm .error').forEach(el => el.textContent = '');
                document.getElementById('editUserForm').reset();
                // Reset submit button
                const submitBtn = document.getElementById('editUserSubmit');
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-processing');
                submitBtn.textContent = 'Confirm User';
            } else if (modalId === 'typeFilterModal') {
                document.getElementById('typeFilterForm').reset();
            } else if (modalId === 'statusFilterModal') {
                document.getElementById('statusFilterForm').reset();
            }
        }

        function showAlert(type, message) {
            const alertContainer = document.querySelector('.alert-container');
            if (alertContainer) {
                const alert = document.createElement('div');
                alert.className = `alert alert-${type}`;
                alert.textContent = message;
                alertContainer.appendChild(alert);

                setTimeout(() => {
                    alert.classList.add('fade-out');
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 3000);
            }
        }

        function updateTabCounts() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'viewU.php?action=get_counts', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log('Tab Counts Response:', response);
                        const activeTabBtn = document.querySelector('.tab-btn[onclick="showTab(\'active\')"]');
                        const archivedTabBtn = document.querySelector('.tab-btn[onclick="showTab(\'archived\')"]');
                        activeTabBtn.textContent = `User (${response.activeCount})`;
                        archivedTabBtn.innerHTML = `Archived${response.archivedCount > 0 ? ` <span class="tab-badge">${response.archivedCount}</span>` : ''}`;
                        
                        // Show/hide Restore All button based on archived count
                        const restoreAllBtn = document.querySelector('.restore-all-btn');
                        if (restoreAllBtn) {
                            restoreAllBtn.style.display = response.archivedCount > 0 ? 'inline-block' : 'none';
                        }
                    } catch (e) {
                        console.error('Error parsing tab counts JSON:', e, xhr.responseText);
                        showAlert('error', 'Failed to update tab counts.');
                    }
                }
            };
            xhr.send();
        }

        const debouncedSearchUsers = debounce(function(page = 1) {
            const searchTerm = document.getElementById('searchInput').value.trim();
            currentSearchPage = page;
            if (currentTab === 'active') {
                activePage = page;
            } else {
                archivedPage = page;
            }
            const xhr = new XMLHttpRequest();
            const params = new URLSearchParams({
                action: 'search',
                search: searchTerm,
                tab: currentTab,
                search_page: page,
                status: currentStatusFilter,
                type: currentTypeFilter
            });
            xhr.open('GET', `viewU.php?${params.toString()}`, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log('Search Response:', response);
                        const tbody = document.getElementById(currentTab === 'active' ? 'active-users-tbody' : 'archived-users-tbody');
                        const pagination = document.getElementById(currentTab === 'active' ? 'active-users-pagination' : 'archived-users-pagination');
                        if (tbody && pagination) {
                            tbody.innerHTML = response.html;
                            let paginationHtml = '';
                            if (response.totalPages > 0) {
                                paginationHtml += response.currentPage > 1
                                    ? `<a href="javascript:searchUsers(${response.currentPage - 1})" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`
                                    : `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
                                paginationHtml += `<span class="current-page">Page ${response.currentPage} of ${response.totalPages}</span>`;
                                paginationHtml += response.currentPage < response.totalPages
                                    ? `<a href="javascript:searchUsers(${response.currentPage + 1})" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`
                                    : `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
                            }
                            pagination.innerHTML = paginationHtml;
                            // Update tab counts after search
                            updateTabCounts();
                        }
                    } catch (e) {
                        console.error('Error parsing search JSON:', e, xhr.responseText);
                        showAlert('error', 'Failed to process search response.');
                    }
                }
            };
            xhr.send();
        }, 300);

        function searchUsers(page) {
            debouncedSearchUsers(page);
        }

        document.addEventListener('DOMContentLoaded', () => {
            showTab(currentTab);

            // Password toggle functionality
            document.getElementById('toggleAddPassword').addEventListener('click', function() {
                const passwordInput = document.getElementById('add_password');
                const icon = this;
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });

            // Client-side validation for Add User Form
            document.getElementById('addUserForm').addEventListener('submit', function(e) {
                e.preventDefault();
                let isValid = true;
                const firstname = document.getElementById('add_firstname').value.trim();
                const lastname = document.getElementById('add_lastname').value.trim();
                const email = document.getElementById('add_email').value.trim();
                const username = document.getElementById('add_username').value.trim();
                const password = document.getElementById('add_password').value.trim();
                const firstnameError = document.getElementById('add_firstname_error');
                const lastnameError = document.getElementById('add_lastname_error');
                const emailError = document.getElementById('add_email_error');
                const usernameError = document.getElementById('add_username_error');
                const passwordError = document.getElementById('add_password_error');

                // Clear previous errors
                firstnameError.textContent = '';
                lastnameError.textContent = '';
                emailError.textContent = '';
                usernameError.textContent = '';
                passwordError.textContent = '';

                // Validate firstname (no numbers)
                if (!firstname.match(/^[a-zA-Z\s-]+$/)) {
                    firstnameError.textContent = 'Firstname must not contain numbers.';
                    isValid = false;
                }

                // Validate lastname (no numbers)
                if (!lastname.match(/^[a-zA-Z\s-]+$/)) {
                    lastnameError.textContent = 'Lastname must not contain numbers.';
                    isValid = false;
                }

                // Validate email
                if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    emailError.textContent = 'A valid email is required.';
                    isValid = false;
                }

                // Validate username
                if (!username) {
                    usernameError.textContent = 'Username is required.';
                    isValid = false;
                }

                // Validate password
                if (!password.match(/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/)) {
                    passwordError.textContent = 'Password must be at least 8 characters, with 1 uppercase letter, 1 number, and 1 special character.';
                    isValid = false;
                }

                if (isValid) {
                    const submitBtn = document.getElementById('addUserSubmit');
                    submitBtn.disabled = true;
                    submitBtn.classList.add('btn-processing');
                    submitBtn.textContent = 'Processing...';

                    const formData = new FormData(this);
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'viewU.php', true);
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            // Re-enable button regardless of response
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('btn-processing');
                            submitBtn.textContent = 'Add User';

                            if (xhr.status === 200) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    console.log('Add User Response:', response);
                                    if (response.success) {
                                        closeModal('addUserModal');
                                        searchUsers(activePage);
                                        showAlert('success', response.message);
                                    } else if (response.errors) {
                                        Object.keys(response.errors).forEach(key => {
                                            document.getElementById(`add_${key}_error`).textContent = response.errors[key];
                                        });
                                    } else {
                                        showAlert('error', response.message || 'An error occurred.');
                                    }
                                } catch (e) {
                                    console.error('Error parsing JSON:', e, xhr.responseText);
                                    showAlert('error', 'Failed to process response.');
                                }
                            } else {
                                console.error('AJAX Error:', xhr.status, xhr.statusText);
                                showAlert('error', 'Server error occurred.');
                            }
                        }
                    };
                    xhr.send(formData);
                }
            });

            // Client-side validation for Edit User Form
            document.getElementById('editUserForm').addEventListener('submit', function(e) {
                e.preventDefault();
                let isValid = true;
                const firstname = document.getElementById('edit_firstname').value.trim();
                const lastname = document.getElementById('edit_lastname').value.trim();
                const email = document.getElementById('edit_email').value.trim();
                const username = document.getElementById('edit_username').value.trim();
                const firstnameError = document.getElementById('edit_firstname_error');
                const lastnameError = document.getElementById('edit_lastname_error');
                const emailError = document.getElementById('edit_email_error');
                const usernameError = document.getElementById('edit_username_error');

                // Clear previous errors
                firstnameError.textContent = '';
                lastnameError.textContent = '';
                emailError.textContent = '';
                usernameError.textContent = '';

                // Validate firstname (no numbers)
                if (!firstname.match(/^[a-zA-Z\s-]+$/)) {
                    firstnameError.textContent = 'Firstname must not contain numbers.';
                    isValid = false;
                }

                // Validate lastname (no numbers)
                if (!lastname.match(/^[a-zA-Z\s-]+$/)) {
                    lastnameError.textContent = 'Lastname must not contain numbers.';
                    isValid = false;
                }

                // Validate email
                if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    emailError.textContent = 'A valid email is required.';
                    isValid = false;
                }

                // Validate username
                if (!username) {
                    usernameError.textContent = 'Username is required.';
                    isValid = false;
                }

                if (isValid) {
                    const submitBtn = document.getElementById('editUserSubmit');
                    submitBtn.disabled = true;
                    submitBtn.classList.add('btn-processing');
                    submitBtn.textContent = 'Processing...';

                    const formData = new FormData(this);
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'viewU.php', true);
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            // Re-enable button regardless of response
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('btn-processing');
                            submitBtn.textContent = 'Confirm User';

                            if (xhr.status === 200) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    console.log('Edit User Response:', response);
                                    if (response.success) {
                                        closeModal('editUserModal');
                                        searchUsers(activePage);
                                        showAlert('success', response.message);
                                    } else if (response.errors) {
                                        Object.keys(response.errors).forEach(key => {
                                            document.getElementById(`edit_${key}_error`).textContent = response.errors[key];
                                        });
                                    } else {
                                        showAlert('error', response.message || 'An error occurred.');
                                    }
                                } catch (e) {
                                    console.error('Error parsing JSON:', e, xhr.responseText);
                                    showAlert('error', 'Failed to process response.');
                                }
                            } else {
                                console.error('AJAX Error:', xhr.status, xhr.statusText);
                                showAlert('error', 'Server error occurred.');
                            }
                        }
                    };
                    xhr.send(formData);
                }
            });

            // Handle Type Filter Form submission
            document.getElementById('typeFilterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                currentTypeFilter = document.getElementById('filter_type').value;
                currentTab = document.getElementById('typeFilterTab').value;
                currentSearchPage = currentTab === 'active' ? activePage : archivedPage;
                closeModal('typeFilterModal');
                searchUsers(currentSearchPage);
            });

            // Handle Status Filter Form submission
            document.getElementById('statusFilterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                currentStatusFilter = document.getElementById('filter_status').value;
                currentTab = document.getElementById('statusFilterTab').value;
                currentSearchPage = currentTab === 'active' ? activePage : archivedPage;
                closeModal('statusFilterModal');
                searchUsers(currentSearchPage);
            });

            // Handle Archive Form submission
            document.getElementById('archiveForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const submitBtn = e.target.querySelector('.modal-btn.confirm');
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-processing');
                submitBtn.textContent = 'Processing...';

                const formData = new FormData(this);
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'viewU.php', true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        // Re-enable button
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('btn-processing');
                        submitBtn.textContent = 'Archive';

                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                console.log('Archive Response:', response);
                                if (response.success) {
                                    closeModal('archiveModal');
                                    debouncedSearchUsers(currentTab === 'active' ? activePage : archivedPage);
                                    showAlert('success', response.message);
                                } else {
                                    showAlert('error', response.message || 'Failed to archive user.');
                                }
                            } catch (e) {
                                console.error('Error parsing JSON:', e, xhr.responseText);
                                showAlert('error', 'Failed to process archive response.');
                            }
                        }
                    }
                };
                xhr.send(formData);
            });

            // Handle Restore Form submission
            document.getElementById('restoreForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const submitBtn = e.target.querySelector('.modal-btn.confirm');
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-processing');
                submitBtn.textContent = 'Processing...';

                const formData = new FormData(this);
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'viewU.php', true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        // Re-enable button
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('btn-processing');
                        submitBtn.textContent = 'Unarchive';

                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                console.log('Restore Response:', response);
                                if (response.success) {
                                    closeModal('restoreModal');
                                    debouncedSearchUsers(currentTab === 'active' ? activePage : archivedPage);
                                    showAlert('success', response.message);
                                } else {
                                    showAlert('error', response.message || 'Failed to unarchive user.');
                                }
                            } catch (e) {
                                console.error('Error parsing JSON:', e, xhr.responseText);
                                showAlert('error', 'Failed to process unarchive response.');
                            }
                        }
                    }
                };
                xhr.send(formData);
            });

            // Handle Delete Form submission
            document.getElementById('deleteForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const submitBtn = e.target.querySelector('.modal-btn.confirm');
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-processing');
                submitBtn.textContent = 'Processing...';

                const formData = new FormData(this);
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'viewU.php', true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        // Re-enable button
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('btn-processing');
                        submitBtn.textContent = 'Delete';

                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                console.log('Delete Response:', response);
                                if (response.success) {
                                    closeModal('deleteModal');
                                    debouncedSearchUsers(archivedPage);
                                    showAlert('success', response.message);
                                } else {
                                    showAlert('error', response.message || 'Failed to delete user.');
                                }
                            } catch (e) {
                                console.error('Error parsing JSON:', e, xhr.responseText);
                                showAlert('error', 'Failed to process delete response.');
                            }
                        }
                    }
                };
                xhr.send(formData);
            });

            // Handle Restore All Form submission
            document.getElementById('restoreAllForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const submitBtn = e.target.querySelector('.modal-btn.confirm');
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-processing');
                submitBtn.textContent = 'Processing...';

                const formData = new FormData(this);
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'viewU.php', true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        // Re-enable button
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('btn-processing');
                        submitBtn.textContent = 'Restore All';

                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                console.log('Restore All Response:', response);
                                if (response.success) {
                                    closeModal('restoreAllModal');
                                    debouncedSearchUsers(currentTab === 'active' ? activePage : archivedPage);
                                    showAlert('success', response.message);
                                } else {
                                    showAlert('error', response.message || 'Failed to restore all users.');
                                }
                            } catch (e) {
                                console.error('Error parsing JSON:', e, xhr.responseText);
                                showAlert('error', 'Failed to process restore all response.');
                            }
                        }
                    }
                };
                xhr.send(formData);
            });

            // Initialize tab counts on page load
            updateTabCounts();
        });
    </script>
</body>
</html>