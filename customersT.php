<?php
date_default_timezone_set('Asia/Manila');
session_start();
include 'db.php';


// Function to escape strings for JavaScript
function jsEscape($str) {
    return str_replace(
        ["\\", "'", "\"", "\n", "\r", "\t"],
        ["\\\\", "\\'", "\\\"", "\\n", "\\r", "\\t"],
        $str
    );
}

// Function to format date to YYYY/MMM/DD
function formatDateDisplay($date) {
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    return date('Y/M/d', strtotime($date));
}

function updateDueAndBillDates($conn, $account_no, $next_due) {
    date_default_timezone_set('Asia/Manila');
    $current_date = new DateTime();
    $next_due_date = new DateTime($next_due);

    // If current date is past the next due date, update dates
    if ($current_date > $next_due_date) {
        // Get customer details
        $sql = "SELECT c_advancedays, c_plan, c_balance FROM tbl_customer WHERE c_account_no = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $account_no);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $advance_days_str = $row['c_advancedays'] ?? '7 days';
        $plan_price = floatval(preg_replace('/[^0-9.]/', '', $row['c_plan']));
        $current_balance = floatval($row['c_balance']);
        $stmt->close();
        
        // Extract the numeric value from the string (e.g., "8 days" -> 8)
        $advance_days = (int)$advance_days_str;
        
        $last_due_date = $next_due_date->format('Y-m-d');
        $next_due_date->modify('+31 days');

        // Adjust for month-end
        $day = (int)$next_due_date->format('d');
        if ($day > 28) {
            $next_due_date->modify('first day of next month');
            if ($day == 31) {
                $next_due_date->modify('-1 day');
            }
        }

        $new_next_due = $next_due_date->format('Y-m-d');
        $next_bill = (clone $next_due_date)->modify("-{$advance_days} days");
        $new_next_bill = $next_bill->format('Y-m-d');

        // Check if today is the next bill date and update balance
        $today = date('Y-m-d');
        $new_balance = $current_balance;
        if ($today === $new_next_bill) {
            $new_balance = $plan_price; // Set balance to plan price on bill date
        }

        // Update the database
        $sql = "UPDATE tbl_customer SET c_nextdue = ?, c_lastdue = ?, c_nextbill = ?, c_balance = ? WHERE c_account_no = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssds", $new_next_due, $last_due_date, $new_next_bill, $new_balance, $account_no);
            $stmt->execute();
            $stmt->close();
        }
        return [$new_next_due, $last_due_date, $new_next_bill];
    }
    return [$next_due, null, null];
}

$firstName = '';
$lastName = '';
$userType = '';
$avatarPath = 'default-avatar.png';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $_SESSION['username'] . '.png';

if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} else {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$avatarPath = $_SESSION['avatarPath'];

// Handle AJAX requests
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'search' && isset($_GET['search'])) {
        $searchTerm = $_GET['search'];
        $tab = $_GET['tab'] ?? 'active';
        $page = isset($_GET['search_page']) ? (int)$_GET['search_page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $searchTerm = $conn->real_escape_string($searchTerm);
        $likeSearch = '%' . $searchTerm . '%';

        if ($tab === 'active') {
            $sqlCount = "SELECT COUNT(*) AS total FROM tbl_customer 
                         WHERE (c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL) 
                         AND (c_account_no LIKE ? OR c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ? OR c_coordinates LIKE ? OR c_plan LIKE ?)";
            $sql = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment, c_balance, c_startdate, c_nextdue, c_lastdue, c_nextbill, c_billstatus 
                    FROM tbl_customer 
                    WHERE (c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL) 
                    AND (c_account_no LIKE ? OR c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ? OR c_coordinates LIKE ? OR c_plan LIKE ?) 
                    LIMIT ?, ?";
        } else {
            $sqlCount = "SELECT COUNT(*) AS total FROM tbl_customer 
                         WHERE c_status LIKE 'ARCHIVED:%' 
                         AND (c_account_no LIKE ? OR c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ? OR c_coordinates LIKE ? OR c_plan LIKE ?)";
            $sql = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment, c_balance, c_startdate, c_nextdue, c_lastdue, c_nextbill, c_billstatus 
                    FROM tbl_customer 
                    WHERE c_status LIKE 'ARCHIVED:%' 
                    AND (c_account_no LIKE ? OR c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ? OR c_coordinates LIKE ? OR c_plan LIKE ?) 
                    LIMIT ?, ?";
        }

        // Get total count for pagination
        $stmtCount = $conn->prepare($sqlCount);
        $stmtCount->bind_param("sssssssss", $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch);
        $stmtCount->execute();
        $countResult = $stmtCount->get_result();
        $totalRow = $countResult->fetch_assoc();
        $total = $totalRow['total'];
        $totalPages = ceil($total / $limit);
        $stmtCount->close();

        // Fetch search results
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssii", $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $offset, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        ob_start();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Update due and bill dates if necessary
                if ($tab === 'active' && !empty($row['c_nextdue'])) {
                    list($updated_next_due, $updated_last_due, $updated_next_bill) = updateDueAndBillDates($conn, $row['c_account_no'], $row['c_nextdue']);
                    $row['c_nextdue'] = $updated_next_due;
                    if ($updated_last_due) {
                        $row['c_lastdue'] = $updated_last_due;
                    }
                    if ($updated_next_bill) {
                        $row['c_nextbill'] = $updated_next_bill;
                    }
                }

                $displayStatus = $tab === 'archived' ? preg_replace('/^ARCHIVED:/', '', $row['c_status']) : ($row['c_status'] ?? '');
                echo "<tr> 
                        <td>" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_purok'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_barangay'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_contact'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_coordinates'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td>" . htmlspecialchars($row['c_email'], ENT_QUOTES, 'UTF-8') . "</td> 
                        <td class='action-buttons'>";
                if ($tab === 'active') {
                    echo "
                        <a class='view-btn' onclick=\"showViewDetails('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_coordinates']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "', '" . jsEscape($row['c_balance'] ?? '0.00') . "', '" . jsEscape($row['c_startdate'] ?? '') . "', '" . jsEscape(formatDateDisplay($row['c_nextdue'] ?? '')) . "', '" . jsEscape(formatDateDisplay($row['c_lastdue'] ?? '')) . "', '" . jsEscape(formatDateDisplay($row['c_nextbill'] ?? '')) . "', '" . jsEscape($row['c_billstatus'] ?? 'Inactive') . "')\" title='View'><i class='fas fa-eye'></i></a>
                        <a class='edit-btn' href='editC.php?account_no=" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "' title='Edit'><i class='fas fa-edit'></i></a>
                        <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Archive'><i class='fas fa-archive'></i></a>";
                } else {
                    echo "
                        <a class='view-btn' onclick=\"showViewDetails('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_coordinates']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "', '" . jsEscape($row['c_balance'] ?? '0.00') . "', '" . jsEscape($row['c_startdate'] ?? '') . "', '" . jsEscape(formatDateDisplay($row['c_nextdue'] ?? '')) . "', '" . jsEscape(formatDateDisplay($row['c_lastdue'] ?? '')) . "', '" . jsEscape(formatDateDisplay($row['c_nextbill'] ?? '')) . "', '" . jsEscape($row['c_billstatus'] ?? 'Inactive') . "')\" title='View'><i class='fas fa-eye'></i></a>
                        <a class='unarchive-btn' onclick=\"showUnarchiveModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                        <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Delete'><i class='fas fa-trash'></i></a>";
                }
                echo "</td></tr>";
            }
        } else {
            echo "<tr><td colspan='10' style='text-align: center;'>No customers found.</td></tr>";
        }
        $tableRows = ob_get_clean();

        // Update pagination
        echo "<script>updatePagination($page, $totalPages, '$tab', '" . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . "');</script>";
        echo $tableRows;
        $stmt->close();
        $conn->close();
        exit();
    } elseif ($_GET['action'] === 'get_all_active_customers') {
        // Fetch all active customers
        $sql = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_plan, c_balance, c_startdate, c_nextdue, c_lastdue, c_nextbill, c_billstatus 
                FROM tbl_customer 
                WHERE c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL";
        $result = $conn->query($sql);
        $customers = [];

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Update due and bill dates if necessary
                if (!empty($row['c_nextdue'])) {
                    list($updated_next_due, $updated_last_due, $updated_next_bill) = updateDueAndBillDates($conn, $row['c_account_no'], $row['c_nextdue']);
                    $row['c_nextdue'] = $updated_next_due;
                    if ($updated_last_due) {
                        $row['c_lastdue'] = $updated_last_due;
                    }
                    if ($updated_next_bill) {
                        $row['c_nextbill'] = $updated_next_bill;
                    }
                }

                $customers[] = [
                    'c_account_no' => $row['c_account_no'],
                    'c_fname' => $row['c_fname'],
                    'c_lname' => $row['c_lname'],
                    'c_purok' => $row['c_purok'],
                    'c_barangay' => $row['c_barangay'],
                    'c_contact' => $row['c_contact'],
                    'c_email' => $row['c_email'],
                    'c_coordinates' => $row['c_coordinates'],
                    'c_plan' => $row['c_plan'],
                    'c_balance' => $row['c_balance'],
                    'c_startdate' => $row['c_startdate'],
                    'c_nextdue' => formatDateDisplay($row['c_nextdue']),
                    'c_lastdue' => formatDateDisplay($row['c_lastdue']),
                    'c_nextbill' => formatDateDisplay($row['c_nextbill']),
                    'c_billstatus' => $row['c_billstatus']
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode($customers);
        $conn->close();
        exit();
    }
} else {
    // Debug: Log when action is not set
    error_log("No 'action' parameter provided in request to customersT.php");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pageActive = isset($_GET['page_active']) ? (int)$_GET['page_active'] : 1;
    $pageArchived = isset($_GET['page_archived']) ? (int)$_GET['page_archived'] : 1;
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'customers_active';
    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === 'true';

    if (isset($_POST['archive_customer'])) {
        $account_no = $_POST['c_account_no'];
        $sql = "SELECT c_status FROM tbl_customer WHERE c_account_no=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $account_no);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $current_rem = $row['c_status'] ?? '';
        $stmt->close();

        $new_rem = 'ARCHIVED:' . $current_rem;
        $sql = "UPDATE tbl_customer SET c_status=? WHERE c_account_no=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for archive: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ss", $new_rem, $account_no);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Customer archived successfully!";
        } else {
            $_SESSION['error'] = "Error archiving customer: " . $stmt->error;
            error_log("Error archiving customer account_no $account_no: " . $stmt->error);
        }
        $stmt->close();
        $tab = 'customers_archived';
    } elseif (isset($_POST['unarchive_customer'])) {
        $account_no = $_POST['c_account_no'];
        $sql = "SELECT c_status FROM tbl_customer WHERE c_account_no=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $account_no);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $current_rem = $row['c_status'] ?? '';
        $stmt->close();

        $new_rem = preg_replace('/^ARCHIVED:/', '', $current_rem);
        $sql = "UPDATE tbl_customer SET c_status=? WHERE c_account_no=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for unarchive: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ss", $new_rem, $account_no);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Customer unarchived successfully!";
        } else {
            $_SESSION['error'] = "Error unarchiving customer: " . $stmt->error;
            error_log("Error unarchiving customer account_no $account_no: " . $stmt->error);
        }
        $stmt->close();
        $tab = 'customers_active';
    } elseif (isset($_POST['delete_customer'])) {
        $account_no = $_POST['c_account_no'];
        $sql = "DELETE FROM tbl_customer WHERE c_account_no=? AND c_status LIKE 'ARCHIVED:%'";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for delete: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $account_no);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Customer deleted permanently!";
        } else {
            $_SESSION['error'] = "Error deleting customer: " . $stmt->error;
            error_log("Error deleting customer account_no $account_no: " . $stmt->error);
        }
        $stmt->close();
        $tab = 'customers_archived';
    } elseif (isset($_POST['activate_billing'])) {
    $account_no = $_POST['c_account_no'];
    $due_date = $_POST['due_date'];
    $advance_days = (int)$_POST['advance_days'];
    $advance_days_with_suffix = $advance_days . ' days';

    // Set Philippines time zone
    date_default_timezone_set('Asia/Manila');

    // Validate inputs
    if (empty($due_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
        $_SESSION['error'] = "Invalid due date format. Please use YYYY-MM-DD.";
    } elseif ($advance_days <= 0) {
        $_SESSION['error'] = "Advance days must be a positive number.";
    } else {
        // Calculate dates
        $start_date = date('Y-m-d'); // Current date in Asia/Manila
        $due_date_obj = new DateTime($due_date);
        $next_due = (clone $due_date_obj)->modify('+31 days');

        // Adjust for month-end
        $day = $due_date_obj->format('d');
        if ($day > 28) {
            $next_due->modify('first day of next month');
            if ($day == 31) {
                $next_due->modify('-1 day');
            }
        }

        $next_due_date = $next_due->format('Y-m-d');
        $next_bill = (clone $next_due)->modify("-{$advance_days} days");
        $next_bill_date = $next_bill->format('Y-m-d');
        $last_due_date = null;
        $billing_status = 'Active';
        $balance = 0.00;

        // Update database with advance days
        $sql = "UPDATE tbl_customer 
                SET c_balance = ?, c_startdate = ?, c_nextdue = ?, c_lastdue = ?, 
                    c_nextbill = ?, c_billstatus = ?, c_advancedays = ? 
                WHERE c_account_no = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("dsssssis", $balance, $start_date, $next_due_date, 
                             $last_due_date, $next_bill_date, $billing_status, 
                             $advance_days_with_suffix, $account_no);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Billing activated successfully for account $account_no!";
            } else {
                $_SESSION['error'] = "Error activating billing: " . $stmt->error;
                error_log("Error activating billing for account_no $account_no: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Prepare failed: " . $conn->error;
            error_log("Prepare failed for activate billing: " . $conn->error);
        }
    }
} elseif (isset($_POST['record_payment'])) {
    $account_no = $_POST['c_account_no'];
    $transaction_date = $_POST['t_date'];
    $credit_date = $_POST['t_credit_date'];
    $transaction_description = $_POST['t_description'] === 'Custom description' ? $_POST['custom_description'] : $_POST['t_description'];
    $transaction_amount = floatval($_POST['t_amount']);

    // Set Philippines time zone
    date_default_timezone_set('Asia/Manila');

    // Validate inputs
    if (empty($credit_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $credit_date)) {
        $_SESSION['error'] = "Invalid credit date format. Please use YYYY-MM-DD.";
    } elseif ($transaction_amount <= 0) {
        $_SESSION['error'] = "Transaction amount must be a positive number.";
    } else {
        // Fetch customer details
        $sql = "SELECT c_fname, c_lname, c_plan, c_balance, c_nextbill FROM tbl_customer WHERE c_account_no = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $account_no);
            $stmt->execute();
            $result = $stmt->get_result();
            $customer = $result->fetch_assoc();
            $stmt->close();
            
            $customer_name = $customer['c_fname'] . ' ' . $customer['c_lname'];
            $plan_price = floatval(preg_replace('/[^0-9.]/', '', $customer['c_plan']));
            $current_balance = floatval($customer['c_balance']);
            $next_bill_date = $customer['c_nextbill'];
            
            // Calculate new balance (if payment is more than plan price)
            $new_balance = 0.00;
            if ($transaction_amount > $plan_price) {
                $new_balance = $transaction_amount - $plan_price;
            }
            
            // Check if today is the next bill date
            $today = date('Y-m-d');
            if ($today === $next_bill_date) {
                // If it's bill date, add plan price to balance (minus any advance payment)
                $new_balance = max(0, $plan_price - $transaction_amount);
            }
            
            // Insert transaction record
            $sql = "INSERT INTO tbl_transactions (t_date, t_balance, t_credit_date, t_description, t_amount, t_customer_name) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($sql);
            if ($insert_stmt) {
                $insert_stmt->bind_param("sdssds", $transaction_date, $new_balance, $credit_date, $transaction_description, $transaction_amount, $customer_name);
                if ($insert_stmt->execute()) {
                    // Update customer balance
                    $sql = "UPDATE tbl_customer SET c_balance = ? WHERE c_account_no = ?";
                    $update_stmt = $conn->prepare($sql);
                    if ($update_stmt) {
                        $update_stmt->bind_param("ds", $new_balance, $account_no);
                        if ($update_stmt->execute()) {
                            $_SESSION['message'] = "Payment recorded successfully for account $account_no!";
                        } else {
                            $_SESSION['error'] = "Error updating balance: " . $update_stmt->error;
                        }
                        $update_stmt->close();
                    }
                } else {
                    $_SESSION['error'] = "Error recording payment: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            } else {
                $_SESSION['error'] = "Prepare failed for payment: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "Prepare failed for fetching customer details: " . $conn->error;
        }
    }
    header("Location: customersT.php");
    exit();
}

}

if ($conn) {
    // Fetch user data
    $sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sqlUser);
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $resultUser = $stmt->get_result();
    if ($resultUser->num_rows > 0) {
        $row = $resultUser->fetch_assoc();
        $firstName = $row['u_fname'];
        $lastName = $row['u_lname'];
        $userType = $row['u_type'];
    }
    $stmt->close();

    // Pagination setup
    $limit = 10;
    // Active customers
    $pageActive = isset($_GET['page_active']) ? (int)$_GET['page_active'] : 1;
    $offsetActive = ($pageActive - 1) * $limit;
    $totalActiveQuery = "SELECT COUNT(*) AS total FROM tbl_customer WHERE c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL";
    $totalActiveResult = $conn->query($totalActiveQuery);
    $totalActiveRow = $totalActiveResult->fetch_assoc();
    $totalActive = $totalActiveRow['total'];
    $totalActivePages = ceil($totalActive / $limit);

    // Archived customers
    $pageArchived = isset($_GET['page_archived']) ? (int)$_GET['page_archived'] : 1;
    $offsetArchived = ($pageArchived - 1) * $limit;
    $totalArchivedQuery = "SELECT COUNT(*) AS total FROM tbl_customer WHERE c_status LIKE 'ARCHIVED:%'";
    $totalArchivedResult = $conn->query($totalArchivedQuery);
    $totalArchivedRow = $totalArchivedResult->fetch_assoc();
    $totalArchived = $totalArchivedRow['total'];
    $totalArchivedPages = ceil($totalArchived / $limit);

    // Fetch active customers
    $sqlActive = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment, c_balance, c_startdate, c_nextdue, c_lastdue, c_nextbill, c_billstatus 
                  FROM tbl_customer WHERE c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL LIMIT ?, ?";
    $stmtActive = $conn->prepare($sqlActive);
    $stmtActive->bind_param("ii", $offsetActive, $limit);
    $stmtActive->execute();
    $resultActive = $stmtActive->get_result();
    $stmtActive->close();

    // Fetch archived customers
    $sqlArchived = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment, c_balance, c_startdate, c_nextdue, c_lastdue, c_nextbill, c_billstatus 
                    FROM tbl_customer WHERE c_status LIKE 'ARCHIVED:%' LIMIT ?, ?";
    $stmtArchived = $conn->prepare($sqlArchived);
    $stmtArchived->bind_param("ii", $offsetArchived, $limit);
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
    <title>Registered Customers</title>
    <link rel="stylesheet" href="customersTT.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
</head>
<body>
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2><img src="image/logo.png" alt="TixNet Icon" class="sidebar-icon">TixNet Pro</h2>
    <ul>
        <li><a href="staffD.php"><i class="fas fa-ticket-alt icon"></i> <span>Regular Tickets</span></a></li>
        <li><a href="assetsT.php"><i class="fas fa-boxes icon"></i> <span>Assets</span></a></li>
        <li><a href="AllCustomersT.php"><i class="fas fa-clipboard-check icon"></i> <span>Customers Ticket</span></a></li>
        <li><a href="customersT.php" class="active"><i class="fas fa-user-friends icon"></i> <span>Customers</span></a></li>
       
        <li><a href="AssignTech.php"><i class="fas fa-tools icon"></i> <span>Technicians</span></a></li>
        <li><a href="Payments.php"><i class="fas fa-credit-card icon"></i> <span>Payment Transactions</span></a></li>
    </ul>
    <footer>
        <a href="technician_staff.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </footer>
    </div>

    <div class="container">
        <div class="upper"> 
            <h1>Customers Info</h1>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search customers..." onkeyup="debouncedSearchCustomers()">
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
            <h2>Connected Customers</h2>
            <div class="tab-buttons">
                <button class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'customers_active') || !isset($_GET['tab']) ? 'active' : ''; ?>" onclick="showTab('customers_active')">
                    Active (<?php echo $totalActive; ?>)
                </button>
                <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] === 'customers_archived' ? 'active' : ''; ?>" onclick="showTab('customers_archived')">
                    Archived
                    <?php if ($totalArchived > 0): ?>
                        <span class="tab-badge"><?php echo $totalArchived; ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="customer-actions">
                <form action="addC.php" method="get" style="display: inline;">
                    <button type="submit" class="add-user-btn"><i class="fas fa-user-plus"></i> Add Customer</button>
                </form>
                <div class="export-container">
                    <button class="action-btn export-btn"><i class="fas fa-download"></i> Export</button>
                    <div class="export-dropdown">
                        <button onclick="exportTable('excel')">Excel</button>
                        <button onclick="exportTable('csv')">CSV</button>
                    </div>
                </div>
            </div>
            <div class="active-customers" id="customers_active" <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'customers_archived') ? 'style="display: none;"' : ''; ?>>
                <table id="active-customers-table">
                    <thead>
                        <tr>
                            <th>Account No.</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Purok</th>
                            <th>Barangay</th>
                            <th>Contact</th>
                            <th>Coordinates</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="active-customers-tbody">
                        <?php
                        if ($resultActive->num_rows > 0) {
                            while ($row = $resultActive->fetch_assoc()) {
                                // Update due and bill dates if necessary
                                if (!empty($row['c_nextdue'])) {
                                    list($updated_next_due, $updated_last_due, $updated_next_bill) = updateDueAndBillDates($conn, $row['c_account_no'], $row['c_nextdue']);
                                    $row['c_nextdue'] = $updated_next_due;
                                    if ($updated_last_due) {
                                        $row['c_lastdue'] = $updated_last_due;
                                    }
                                    if ($updated_next_bill) {
                                        $row['c_nextbill'] = $updated_next_bill;
                                    }
                                }

                                $displayStatus = ($row['c_status'] ?? '');
                                echo "<tr> 
                                        <td>" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_purok'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_barangay'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_contact'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td>" . htmlspecialchars($row['c_coordinates'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_email'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td class='action-buttons'>
                                            <a class='view-btn' onclick=\"showViewDetails('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_coordinates']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "', '" . jsEscape($row['c_balance'] ?? '0.00') . "', '" . jsEscape(formatDateDisplay($row['c_startdate'] ?? '')) . "', '" . jsEscape(formatDateDisplay($row['c_nextdue'] ?? '')) . "', '" . jsEscape(formatDateDisplay($row['c_lastdue'] ?? '')) . "', '" . jsEscape(formatDateDisplay($row['c_nextbill'] ?? '')) . "', '" . jsEscape($row['c_billstatus'] ?? 'Inactive') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                            <a class='edit-btn' href='editC.php?account_no=" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "' title='Edit'><i class='fas fa-edit'></i></a>
                                            <a class='archive-btn' onclick=\"showArchiveModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Archive'><i class='fas fa-archive'></i></a>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='10' style='text-align: center;'>No active customers found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <div class="pagination" id="active-customers-pagination">
                    <?php if ($pageActive > 1): ?>
                        <a href="?tab=customers_active&page_active=<?php echo $pageActive - 1; ?>&page_archived=<?php echo $pageArchived; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <span class="current-page">Page <?php echo $pageActive; ?> of <?php echo $totalActivePages; ?></span>
                    <?php if ($pageActive < $totalActivePages): ?>
                        <a href="?tab=customers_active&page_active=<?php echo $pageActive + 1; ?>&page_archived=<?php echo $pageArchived; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <!-- Customer Details Section -->
                <div id="customerDetailsActive" class="customer-details-section" style="display: none;">
                    <div id="customerDetailsContentActive" class="customer-details"></div>
                </div>
            </div>

            <div class="archived-customers" id="customers_archived" <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'customers_active') || !isset($_GET['tab']) ? 'style="display: none;"' : ''; ?>>
                <table id="archived-customers-table">
                    <thead>
                        <tr>
                            <th>Account No.</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Purok</th>
                            <th>Barangay</th>
                            <th>Contact</th>
                            <th>Coordinates</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="archived-customers-tbody">
                        <?php
                        if ($resultArchived->num_rows > 0) {
                            while ($row = $resultArchived->fetch_assoc()) {
                                $displayStatus = preg_replace('/^ARCHIVED:/', '', $row['c_status']);
                                echo "<tr> 
                                        <td>" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_fname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_lname'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_purok'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_barangay'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_contact'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td>" . htmlspecialchars($row['c_coordinates'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['c_email'], ENT_QUOTES, 'UTF-8') . "</td>
                                        <td class='action-buttons'>
                                            <a class='view-btn' onclick=\"showViewDetails('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_coordinates']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "', '" . jsEscape($row['c_balance'] ?? '0.00') . "', '" . jsEscape(formatDateDisplay($row['c_startdate'] ?? '')) . "', '" . jsEscape(formatDateDisplay($row['c_nextdue'] ?? '')) . "', '" . jsEscape(formatDateDisplay($row['c_lastdue'] ?? '')) . "', '" . jsEscape(formatDateDisplay($row['c_nextbill'] ?? '')) . "', '" . jsEscape($row['c_billstatus'] ?? 'Inactive') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                            <a class='unarchive-btn' onclick=\"showUnarchiveModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Unarchive'><i class='fas fa-box-open'></i></a>
                                            <a class='delete-btn' onclick=\"showDeleteModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname'] . ' ' . $row['c_lname']) . "')\" title='Delete'><i class='fas fa-trash'></i></a>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='9' style='text-align: center;'>No archived customers found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <div class="pagination" id="archived-customers-pagination">
                    <?php if ($pageArchived > 1): ?>
                        <a href="?tab=customers_archived&page_active=<?php echo $pageActive; ?>&page_archived=<?php echo $pageArchived - 1; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <span class="current-page">Page <?php echo $pageArchived; ?> of <?php echo $totalArchivedPages; ?></span>
                    <?php if ($pageArchived < $totalArchivedPages): ?>
                        <a href="?tab=customers_archived&page_active=<?php echo $pageActive; ?>&page_archived=<?php echo $pageArchived + 1; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <!-- Customer Details Section -->
                <div id="customerDetailsArchived" class="customer-details-section" style="display: none;">
                    <div id="customerDetailsContentArchived" class="customer-details"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Archive Customer Modal -->
<div id="archiveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Archive Customer</h2>
        </div>
        <p>Are you sure you want to archive <span id="archiveCustomerName"></span>?</p>
        <form method="POST" id="archiveForm">
            <input type="hidden" name="c_account_no" id="archiveCustomerId">
            <input type="hidden" name="archive_customer" value="1">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('archiveModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Archive</button>
            </div>
        </form>
    </div>
</div>

<!-- Unarchive Customer Modal -->
<div id="unarchiveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Unarchive Customer</h2>
        </div>
        <p>Are you sure you want to unarchive <span id="unarchiveCustomerName"></span>?</p>
        <form method="POST" id="unarchiveForm">
            <input type="hidden" name="c_account_no" id="unarchiveCustomerId">
            <input type="hidden" name="unarchive_customer" value="1">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('unarchiveModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Unarchive</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Customer Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Delete Customer</h2>
        </div>
        <p>Are you sure you want to permanently delete <span id="deleteCustomerName"></span>? This action cannot be undone.</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="c_account_no" id="deleteCustomerId">
            <input type="hidden" name="delete_customer" value="1">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Activate Billing Modal -->
<div id="activateBillingModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Activate Billing</h2>
        </div>
        <form method="POST" id="activateBillingForm">
            <input type="hidden" name="c_account_no" id="activateBillingCustomerId">
            <p>Activate Billing Status for <span id="activateBillingCustomerName"></span></p>
            <div class="modal-form">
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" value="<?php 
                    date_default_timezone_set('Asia/Manila');
                    echo date('Y-m-d');
                ?>" readonly>
                <label for="due_date">Due Date:</label>
                <input type="date" id="due_date" name="due_date" required onchange="calculateNextDates()">
                <label for="advance_days">Advance Billing (days):</label>
                <input type="number" id="advance_days" name="advance_days" min="1" required placeholder="Enter number of days" onchange="calculateNextDates()">
                <p class="billing-note"><strong>Note:</strong> The next due date is calculated as 31 days from the due date.</p>
                <input type="hidden" name="activate_billing" value="1">
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('activateBillingModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm">Activate</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Payment Transaction Modal -->
<div id="paymentTransactionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Record Payment Transaction</h2>
        </div>
        <form method="POST" id="paymentTransactionForm">
            <input type="hidden" name="c_account_no" id="paymentCustomerId">
            <p>Record Payment for <span id="paymentCustomerName"></span></p>
            <div class="modal-form">
                <label for="t_date">Transaction Date:</label>
                <input type="date" id="t_date" name="t_date" class="highlight-field" value="<?php 
                    date_default_timezone_set('Asia/Manila');
                    echo date('Y-m-d');
                ?>" readonly>
                <label for="t_balance">Current Balance: <span class="advance-text">Advance</span></label>
                <input type="text" id="t_balance" name="t_balance" class="highlight-field" readonly>
                <label for="t_credit_date">Credit Date:</label>
                <input type="date" id="t_credit_date" name="t_credit_date" required value="<?php 
                date_default_timezone_set('Asia/Manila');
                echo date('Y-m-d');
                 ?>">
                <label for="t_description">Transaction Description:</label>
                <select id="t_description" name="t_description" required onchange="toggleCustomDescription()">     
                    <option value="Custom description">Custom description</option>
                    <option value="Plan 500">Plan 500</option>
                    <option value="Plan 799">Plan 799</option>
                    <option value="Plan 999">Plan 999</option>
                    <option value="Plan 1299">Plan 1299</option>
                    <option value="Plan 1499">Plan 1499</option>
                    <option value="Plan 1799">Plan 1799</option>
                    <option value="Plan 1999">Plan 1999</option>
                    <option value="Plan 2500">Plan 2500</option>
                    <option value="Plan 3000">Plan 3000</option>
                    <option value="Plan 3500">Plan 3500</option>
                    <option value="Plan 4000">Plan 4000</option>
                    <option value="Plan 4500">Plan 4500</option>
                    <option value="Plan 6000">Plan 6000</option>
                </select>
                <div id="customDescriptionContainer" style="display: none;">
                    <label for="custom_description">Custom Description:</label>
                    <input type="text" id="custom_description" name="custom_description" placeholder="Enter custom description">
                </div>
                <label for="t_amount">Transaction Amount:</label>
                <input type="number" id="t_amount" name="t_amount" min="0" step="0.01" required placeholder="Enter amount">
                <input type="hidden" name="record_payment" value="1">
                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeModal('paymentTransactionModal')">Cancel</button>
                    <button type="submit" class="modal-btn confirm">Record Payment</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let currentSearchPage = 1;
let updateInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || 'customers_active';
    showTab(tab);

    // Handle alert messages disappearing after 10 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 1s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 1000);
        }, 10000);
    });

    // Initialize search on page load if there's a search term
    const searchInput = document.getElementById('searchInput');
    if (searchInput.value) {
        searchCustomers();
    }

    // Start auto-update table
    updateInterval = setInterval(updateTable, 30000);
});

// Clear interval when leaving the page
window.addEventListener('beforeunload', () => {
    if (updateInterval) {
        clearInterval(updateInterval);
    }
});

function showTab(tab) {
    const activeSection = document.getElementById('customers_active');
    const archivedSection = document.getElementById('customers_archived');
    const tabButtons = document.querySelectorAll('.tab-buttons .tab-btn');

    // Remove active class from all buttons
    tabButtons.forEach(button => button.classList.remove('active'));

    // Add active class to the clicked button
    const targetButton = Array.from(tabButtons).find(button => button.getAttribute('onclick').includes(`showTab('${tab}')`));
    if (targetButton) {
        targetButton.classList.add('active');
    }

    // Show/hide sections
    if (tab === 'customers_active') {
        activeSection.style.display = 'block';
        archivedSection.style.display = 'none';
        document.getElementById('customerDetailsArchived').style.display = 'none';
    } else if (tab === 'customers_archived') {
        activeSection.style.display = 'none';
        archivedSection.style.display = 'block';
        document.getElementById('customerDetailsActive').style.display = 'none';
    }

    // Update URL
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('tab', tab);
    history.replaceState(null, '', '?' + urlParams.toString());

    // Refresh table content
    updateTable();
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

function searchCustomers(page = 1) {
    const searchTerm = document.getElementById('searchInput').value;
    const activeTab = document.querySelector('.tab-btn.active').textContent.toLowerCase();
    const tab = activeTab.includes('active') ? 'active' : 'archived';
    const tbody = tab === 'active' ? document.getElementById('active-customers-tbody') : document.getElementById('archived-customers-tbody');
    const defaultPageToUse = tab === 'active' ? <?php echo $pageActive; ?> : <?php echo $pageArchived; ?>;

    currentSearchPage = page;

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            tbody.innerHTML = xhr.responseText.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        }
    };
    xhr.open('GET', `customersT.php?action=search&search=${encodeURIComponent(searchTerm)}&tab=${tab}&search_page=${searchTerm ? page : defaultPageToUse}`, true);
    xhr.send();
}

function updatePagination(currentPage, totalPages, tab, searchTerm) {
    const paginationContainer = tab === 'active' ? document.getElementById('active-customers-pagination') : document.getElementById('archived-customers-pagination');
    let paginationHtml = '';

    if (currentPage > 1) {
        paginationHtml += `<a href="javascript:searchCustomers(${currentPage - 1})" class="pagination-link"><i class="fas fa-chevron-left"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    }

    paginationHtml += `<span class="current-page">Page ${currentPage} of ${totalPages}</span>`;

    if (currentPage < totalPages) {
        paginationHtml += `<a href="javascript:searchCustomers(${currentPage + 1})" class="pagination-link"><i class="fas fa-chevron-right"></i></a>`;
    } else {
        paginationHtml += `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    }

    paginationContainer.innerHTML = paginationHtml;
}

const debouncedSearchCustomers = debounce(searchCustomers, 300);

function calculateNextDates() {
    const dueDateInput = document.getElementById('due_date').value;
    const advanceDaysInput = document.getElementById('advance_days').value;
    const nextDueDateInput = document.getElementById('next_due_date');
    const nextBillDateInput = document.getElementById('next_bill_date');

    if (dueDateInput && advanceDaysInput) {
        const dueDate = new Date(dueDateInput);
        const nextDue = new Date(dueDate);
        nextDue.setDate(dueDate.getDate() + 31);

        // Adjust for month-end
        const day = dueDate.getDate();
        if (day > 28) {
            nextDue.setDate(1);
            nextDue.setMonth(nextDue.getMonth() + 1);
            if (day === 31) {
                nextDue.setDate(nextDue.getDate() - 1);
            }
        }

        const nextDueDate = nextDue.toISOString().split('T')[0];
        const nextBill = new Date(nextDue);
        nextBill.setDate(nextDue.getDate() - parseInt(advanceDaysInput));
        const nextBillDate = nextBill.toISOString().split('T')[0];

        nextDueDateInput.value = nextDueDate;
        nextBillDateInput.value = nextBillDate;
    } else {
        nextDueDateInput.value = '';
        nextBillDateInput.value = '';
    }
}

function showViewDetails(account_no, fname, lname, purok, barangay, contact, email, coordinates, date, napname, napport, macaddress, status, plan, equipment, balance, startdate, nextdue, lastdue, nextbill, billstatus) {
    const tab = document.getElementById('customers_active').style.display !== 'none' ? 'active' : 'archived';
    const detailsSection = document.getElementById(`customerDetails${tab === 'active' ? 'Active' : 'Archived'}`);
    const contentDiv = document.getElementById(`customerDetailsContent${tab === 'active' ? 'Active' : 'Archived'}`);
    const table = tab === 'active' ? document.getElementById('active-customers-table') : document.getElementById('archived-customers-table');
    const pagination = tab === 'active' ? document.getElementById('active-customers-pagination') : document.getElementById('archived-customers-pagination');
    const tabButtons = document.querySelector('.tab-buttons');
    const exportContainer = document.querySelector('.export-container');
    const addCustomerButton = document.querySelector('.add-user-btn');
    const tableBoxTitle = document.querySelector('.table-box h2');

    // Hide the table, pagination, tab buttons, export button, add customer button, and title
    table.style.display = 'none';
    pagination.style.display = 'none';
    tabButtons.style.display = 'none';
    exportContainer.style.display = 'none';
    addCustomerButton.style.display = 'none';
    tableBoxTitle.style.display = 'none';

    // Format dates consistently
    const formatDate = (dateStr) => {
        if (!dateStr || dateStr === '') return '';
        const dateObj = new Date(dateStr);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const year = dateObj.getFullYear();
        const month = months[dateObj.getMonth()];
        const day = dateObj.getDate().toString().padStart(2, '0');
        return `${year}/${month}/${day}`;
    };

    const formattedStartDate = formatDate(startdate);
    const formattedLastDue = formatDate(lastdue);

    // Populate and show the details section
    contentDiv.innerHTML = `
    <div class="customer-details-container">
        <div class="customer-details-inner">
            <div class="customer-details-column">
                <h3><i class="fas fa-user"></i> Account Details</h3>
                <h4 class="account-no-header">Account No.: <span class="account-no-value">${account_no}</span></h4>
                <div class="account-details-content">
                    <p><strong>Name:</strong> ${fname} ${lname}</p>
                    <p><strong>Purok:</strong> ${purok || 'N/A'}</p>
                    <p><strong>Barangay:</strong> ${barangay || 'N/A'}</p>
                    <p><strong>Contact:</strong> ${contact || 'N/A'}</p>
                    <p><strong>Email:</strong> ${email || 'N/A'}</p>
                    <p><strong>Coordinates:</strong> ${coordinates || 'N/A'}</p>
                    <p><strong>Customer Status:</strong> ${status || 'N/A'}</p>
                </div>
            </div>
            <div class="subscription-details-column">
                <h3><i class="fas fa-info-circle"></i> Subscription Details</h3>
                <p><strong>Subscription Date:</strong> ${date || 'N/A'}</p>
                <p><strong>Product Plan:</strong> ${plan || 'N/A'}</p>
                <p><strong>Equipment:</strong> ${equipment || 'N/A'}</p>
                <p><strong>NAP Name:</strong> ${napname || 'N/A'}</p>
                <p><strong>NAP Port:</strong> ${napport || 'N/A'}</p>
                <p><strong>MAC Address:</strong> ${macaddress || 'N/A'}</p>
            </div>
        </div>
        <div class="service-details-inner">
            <div class="customer-details-column">
                <h3><i class="fas fa-cogs"></i> Service Details</h3>
                <h4 class="balance-header">Balance: <span class="balance-value">${balance ? parseFloat(balance).toFixed(2) : '0.00'}</span></h4>
                <p><strong>Start Date:</strong> ${formattedStartDate || ''}</p>
                <p><strong>Next Due Date:</strong> ${nextdue || ''}</p>
                <p><strong>Last Due Date:</strong> ${formattedLastDue || ''}</p>
                <p><strong>Next Bill Date:</strong> ${nextbill || ''}</p>
                <p><strong>Billing Status:</strong> ${billstatus || 'Inactive'}</p>
                <div class="action-buttons-container">
                    <a class='activate-btn' onclick="showActivateBillingModal('${account_no}', '${fname} ${lname}')" title='Activate'><i class='fas fa-play'></i></a>
                    <a class='payment-btn' onclick="showPaymentTransactionModal('${account_no}', '${fname} ${lname}', '${balance ? parseFloat(balance).toFixed(2) : '0.00'}', '${plan || ''}')" title='Record Payment'><i class='fas fa-money-bill-wave'></i></a>
                </div>
            </div>
        </div>
        <button class="details-btn cancel" onclick="hideViewDetails('customerDetails${tab === 'active' ? 'Active' : 'Archived'}')">Cancel</button>
    </div>
    `;
    detailsSection.style.display = 'block';
}

function hideViewDetails(sectionId) {
    const tab = sectionId.includes('Active') ? 'active' : 'archived';
    const table = tab === 'active' ? document.getElementById('active-customers-table') : document.getElementById('archived-customers-table');
    const pagination = tab === 'active' ? document.getElementById('active-customers-pagination') : document.getElementById('archived-customers-pagination');
    const tabButtons = document.querySelector('.tab-buttons');
    const exportContainer = document.querySelector('.export-container');
    const addCustomerButton = document.querySelector('.add-user-btn');
    const tableBoxTitle = document.querySelector('.table-box h2');

    // Hide the details section and show the table, pagination, tab buttons, export button, add customer button, and title
    document.getElementById(sectionId).style.display = 'none';
    table.style.display = 'table';
    pagination.style.display = 'flex';
    tabButtons.style.display = 'flex';
    exportContainer.style.display = 'inline-block';
    addCustomerButton.style.display = 'inline-flex';
    tableBoxTitle.style.display = 'block';
}

function toggleCustomDescription() {
    const descriptionSelect = document.getElementById('t_description');
    const customDescContainer = document.getElementById('customDescriptionContainer');
    
    if (descriptionSelect.value === 'Custom description') {
        customDescContainer.style.display = 'block';
    } else {
        customDescContainer.style.display = 'none';
    }
}

function showArchiveModal(account_no, name) {
    document.getElementById('archiveCustomerId').value = account_no;
    document.getElementById('archiveCustomerName').innerText = name;
    document.getElementById('archiveModal').style.display = 'block';
}

function showUnarchiveModal(account_no, name) {
    document.getElementById('unarchiveCustomerId').value = account_no;
    document.getElementById('unarchiveCustomerName').innerText = name;
    document.getElementById('unarchiveModal').style.display = 'block';
}

function showDeleteModal(account_no, name) {
    document.getElementById('deleteCustomerId').value = account_no;
    document.getElementById('deleteCustomerName').innerText = name;
    document.getElementById('deleteModal').style.display = 'block';
}

function showActivateBillingModal(account_no, name) {
    document.getElementById('activateBillingCustomerId').value = account_no;
    document.getElementById('activateBillingCustomerName').innerText = name;
    document.getElementById('activateBillingModal').style.display = 'block';
}

function showPaymentTransactionModal(account_no, name, balance, plan) {
    document.getElementById('paymentCustomerId').value = account_no;
    document.getElementById('paymentCustomerName').innerText = name;
    document.getElementById('t_balance').value = parseFloat(balance).toFixed(2);
    
    // Get current date in Asia/Manila timezone
    const now = new Date();
    const timezoneOffset = 8 * 60; // Manila is UTC+8
    const manilaTime = new Date(now.getTime() + (timezoneOffset + now.getTimezoneOffset()) * 60000);
    
    // Format as YYYY-MM-DD
    const year = manilaTime.getFullYear();
    const month = String(manilaTime.getMonth() + 1).padStart(2, '0');
    const day = String(manilaTime.getDate()).padStart(2, '0');
    const today = `${year}-${month}-${day}`;
    
    document.getElementById('t_credit_date').value = today;
    
    // Set plan as default description if available
    const descriptionSelect = document.getElementById('t_description');
    if (plan && descriptionSelect.options) {
        for (let option of descriptionSelect.options) {
            if (option.value === plan) {
                option.selected = true;
                break;
            }
        }
    }
    
    document.getElementById('paymentTransactionModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function exportTable(format) {
    const activeTab = document.querySelector('.tab-btn.active').textContent.toLowerCase();
    const tab = activeTab.includes('active') ? 'active' : 'archived';
    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            const customers = JSON.parse(xhr.responseText);
            const data = customers.map(customer => ({
                'Account No.': customer.c_account_no,
                'First Name': customer.c_fname,
                'Last Name': customer.c_lname,
                'Purok': customer.c_purok,
                'Barangay': customer.c_barangay,
                'Contact': customer.c_contact,
                'Email': customer.c_email,
                'Coordinates': customer.c_coordinates,
                'Plan': customer.c_plan,
                'Balance': customer.c_balance,
                'Start Date': customer.c_startdate,
                'Next Due Date': customer.c_nextdue,
                'Last Due Date': customer.c_lastdue,
                'Next Bill Date': customer.c_nextbill,
                'Billing Status': customer.c_billstatus
            }));

            const ws = XLSX.utils.json_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Customers');
            
            if (format === 'excel') {
                XLSX.write_file(wb, 'customers.xlsx');
            } else if (format === 'csv') {
                XLSX.write_file(wb, 'customers.csv', { bookType: 'csv' });
            }
        }
    };
    xhr.open('GET', `customersT.php?action=get_all_active_customers`, true);
    xhr.send();
}

function updateTable() {
    const activeTab = document.querySelector('.tab-btn.active').textContent.toLowerCase();
    const tab = activeTab.includes('active') ? 'active' : 'archived';
    searchCustomers(currentSearchPage);
}

</script>
</body>
</html>