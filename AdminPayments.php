<?php
session_start();
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    error_log("No session username found, redirecting to index.php");
    header("Location: index.php");
    exit();
}

// Initialize user variables
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

// Fetch user data
$sqlUser = "SELECT u_fname, u_lname, u_type FROM tbl_user WHERE u_username = ?";
$stmt = $conn->prepare($sqlUser);
if (!$stmt) {
    error_log("Prepare failed for user query: " . $conn->error);
    $_SESSION['error'] = "Database error occurred.";
    header("Location: Payments.php?page_transaction=1");
    exit();
}
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$resultUser = $stmt->get_result();
if ($resultUser->num_rows > 0) {
    $row = $resultUser->fetch_assoc();
    $firstName = $row['u_fname'] ?: 'Unknown';
    $lastName = $row['u_lname'] ?: '';
    $userType = strtolower($row['u_type']) ?: 'staff';
    error_log("User fetched: username={$_SESSION['username']}, userType=$userType");
} else {
    error_log("User not found for username: {$_SESSION['username']}");
    $_SESSION['error'] = "User not found.";
    header("Location: Payments.php?page_transaction=1");
    exit();
}
$stmt->close();

// Fetch customers for filter dropdown
$sqlCustomers = "SELECT DISTINCT t_customer_name FROM tbl_transactions ORDER BY t_customer_name";
$resultCustomers = $conn->query($sqlCustomers);
$customers = [];
if ($resultCustomers && $resultCustomers->num_rows > 0) {
    while ($row = $resultCustomers->fetch_assoc()) {
        $customers[] = [
            'full_name' => $row['t_customer_name']
        ];
    }
} else {
    error_log("No customers found in tbl_transactions: " . ($resultCustomers ? "No rows" : $conn->error));
}

// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $accountFilter = isset($_GET['account_filter']) ? trim($_GET['account_filter']) : '';
    $page = isset($_GET['search_page']) ? max(1, (int)$_GET['search_page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $output = '';

    $whereClauses = [];
    $params = [];
    $paramTypes = '';

    if ($searchTerm !== '') {
        $whereClauses[] = "(t_date LIKE ? OR t_customer_name LIKE ? OR t_credit_date LIKE ? OR t_description LIKE ? OR t_amount LIKE ?)";
        $searchWildcard = "%$searchTerm%";
        $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
        $paramTypes .= 'sssss';
    }

    if ($accountFilter !== '') {
        $whereClauses[] = "t_customer_name = ?";
        $params[] = $accountFilter;
        $paramTypes .= 's';
    }

    $whereClause = empty($whereClauses) ? '1' : implode(' AND ', $whereClauses);

    $countSql = "SELECT COUNT(*) as total FROM tbl_transactions WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        error_log("Prepare failed for count query: " . $conn->error);
        echo json_encode(['error' => 'Database error']);
        exit();
    }
    if ($paramTypes) {
        $countStmt->bind_param($paramTypes, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    $totalPages = ceil($totalRecords / $limit);

    $sql = "SELECT t_id, t_date, t_customer_name, t_credit_date, t_description, t_amount, t_balance 
            FROM tbl_transactions 
            WHERE $whereClause 
            ORDER BY t_date DESC 
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for transaction query: " . $conn->error);
        echo json_encode(['error' => 'Database error']);
        exit();
    }
    if ($paramTypes) {
        $stmt->bind_param($paramTypes . 'ii', ...array_merge($params, [$offset, $limit]));
    } else {
        $stmt->bind_param("ii", $offset, $limit);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output .= "<tr> 
                <td>" . htmlspecialchars($row['t_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . htmlspecialchars($row['t_customer_name'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . htmlspecialchars($row['t_credit_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . htmlspecialchars($row['t_description'], ENT_QUOTES, 'UTF-8') . "</td> 
                <td>" . number_format($row['t_amount'], 2) . "</td> 
                <td>" . number_format($row['t_balance'], 2) . "</td> 
                <td class='action-buttons'>
                    <a class='view-btn' href='#' onclick=\"showTransactionViewModal('" . htmlspecialchars($row['t_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_customer_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_credit_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_description'], ENT_QUOTES, 'UTF-8') . "', '" . number_format($row['t_amount'], 2) . "', '" . number_format($row['t_balance'], 2) . "')\" title='View'><i class='fas fa-eye'></i></a>
                    <a class='edit-btn' href='#' onclick=\"showEditTransactionModal('" . htmlspecialchars($row['t_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_customer_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_credit_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_description'], ENT_QUOTES, 'UTF-8') . "', '" . number_format($row['t_amount'], 2) . "')\" title='Edit'><i class='fas fa-edit'></i></a>
                </td></tr>";
        }
    } else {
        $output = "<tr><td colspan='7' style='text-align: center;'>No transactions found.</td></tr>";
    }
    $stmt->close();

    $response = [
        'html' => $output,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'searchTerm' => $searchTerm,
        'accountFilter' => $accountFilter
    ];
    echo json_encode($response);
    exit();
}

// Handle form submissions for editing transactions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_transaction'])) {
    $pageTransaction = isset($_GET['page_transaction']) ? max(1, (int)$_GET['page_transaction']) : 1;
    $t_id = $_POST['t_id'];
    $t_credit_date = $_POST['t_credit_date'];
    $t_description = $_POST['t_description'];
    $t_amount = floatval($_POST['t_amount']);

    // Validate inputs
    if (empty($t_credit_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $t_credit_date)) {
        $_SESSION['error'] = "Invalid credit date format. Please use YYYY-MM-DD.";
    } elseif ($t_amount <= 0) {
        $_SESSION['error'] = "Transaction amount must be a positive number.";
    } else {
        // Update transaction
        $sql = "UPDATE tbl_transactions SET t_credit_date = ?, t_description = ?, t_amount = ? WHERE t_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssdi", $t_credit_date, $t_description, $t_amount, $t_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Transaction updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating transaction: " . $stmt->error;
                error_log("Error updating transaction t_id $t_id: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Prepare failed for transaction update: " . $conn->error;
            error_log("Prepare failed for transaction update: " . $conn->error);
        }
    }
    header("Location: Payments.php?page_transaction=$pageTransaction");
    exit();
}

// Pagination setup
$limit = 10;
$pageTransaction = isset($_GET['page_transaction']) ? max(1, (int)$_GET['page_transaction']) : 1;
$offsetTransaction = ($pageTransaction - 1) * $limit;
$totalTransactionQuery = "SELECT COUNT(*) AS total FROM tbl_transactions";
$totalTransactionResult = $conn->query($totalTransactionQuery);
if (!$totalTransactionResult) {
    error_log("Error in total transaction query: " . $conn->error);
    $_SESSION['error'] = "Database error occurred.";
    header("Location: Payments.php?page_transaction=$pageTransaction");
    exit();
}
$totalTransactionRow = $totalTransactionResult->fetch_assoc();
$totalTransaction = $totalTransactionRow['total'];
$totalTransactionPages = ceil($totalTransaction / $limit);

// Fetch transactions
$sqlTransaction = "SELECT t_id, t_date, t_customer_name, t_credit_date, t_description, t_amount, t_balance 
                   FROM tbl_transactions 
                   ORDER BY t_date DESC 
                   LIMIT ?, ?";
$stmtTransaction = $conn->prepare($sqlTransaction);
if (!$stmtTransaction) {
    error_log("Prepare failed for transactions query: " . $conn->error);
    $_SESSION['error'] = "Database error occurred.";
    header("Location: Payments.php?page_transaction=$pageTransaction");
    exit();
}
$stmtTransaction->bind_param("ii", $offsetTransaction, $limit);
$stmtTransaction->execute();
$resultTransaction = $stmtTransaction->get_result();
$stmtTransaction->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Transactions</title>
    <link rel="stylesheet" href="AdminPayment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h2><img src="image/logo.png" alt="TixNet Icon" class="sidebar-icon">TixNet Pro</h2>
         <ul>
          <li><a href="adminD.php"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
          <li><a href="viewU.php"><i class="fas fa-users icon"></i> <span>View Users</span></a></li>
          <li><a href="regular_close.php"><i class="fas fa-ticket-alt icon"></i> <span>Ticket Record</span></a></li>
          <li><a href="logs.php"><i class="fas fa-file-alt icon"></i> <span>Logs</span></a></li>
          <li><a href="returnT.php"><i class="fas fa-box icon"></i> <span>Asset Record</span></a></li>
          <li><a href="AdminPayments.php" class="active"><i class="fas fa-credit-card icon"></i> <span>Transactions</span></a></li>
         </ul>
      <footer>
       <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </footer>
    </div>

    <div class="container">
        <div class="upper">
            <h1>Transactions History</h1>
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

        <div class="alert-container" id="alertContainer"></div>

        <div class="table-box glass-container">
            <h2>Customer Payment Transactions</h2>

            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search transactions..." onkeyup="debouncedSearchTransactions()">
                <span class="search-icon"><i class="fas fa-search"></i></span>
            </div>

            <div class="customer-transactions active">
                <table id="transactions-table">
                    <thead>
                        <tr>
                            <th>Transaction Date</th>
                            <th>Customer <button class="filter-btn" onclick="showAccountFilterModal('transaction')" title="Filter by Customer Name"><i class='bx bx-filter'></i></button></th>
                            <th>Credit Date</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Advance Balance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="transactions-table-body">
                        <?php
                        if ($resultTransaction->num_rows > 0) {
                            while ($row = $resultTransaction->fetch_assoc()) {
                                echo "<tr> 
                                        <td>" . htmlspecialchars($row['t_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['t_customer_name'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['t_credit_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . htmlspecialchars($row['t_description'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . number_format($row['t_amount'], 2) . "</td> 
                                        <td>" . number_format($row['t_balance'], 2) . "</td> 
                                        <td class='action-buttons'>
                                            <a class='view-btn' href='#' onclick=\"showTransactionViewModal('" . htmlspecialchars($row['t_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_customer_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_credit_date'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['t_description'], ENT_QUOTES, 'UTF-8') . "', '" . number_format($row['t_amount'], 2) . "', '" . number_format($row['t_balance'], 2) . "')\" title='View'><i class='fas fa-eye'></i></a>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' style='text-align: center;'>No transactions found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <div class="pagination" id="transaction-pagination">
                    <?php if ($pageTransaction > 1): ?>
                        <a href="?page_transaction=<?php echo $pageTransaction - 1; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <span class="current-page">Page <?php echo $pageTransaction; ?> of <?php echo $totalTransactionPages; ?></span>
                    <?php if ($pageTransaction < $totalTransactionPages): ?>
                        <a href="?page_transaction=<?php echo $pageTransaction + 1; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction View Modal -->
<div id="transactionViewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Transaction Details</h2>
        </div>
        <div id="transactionViewContent" class="view-details"></div>
        <div class="modal-footer">
            <button class="modal-btn cancel" onclick="closeModal('transactionViewModal')">Close</button>
        </div>
    </div>
</div>

<!-- Edit Transaction Modal -->
<div id="editTransactionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Transaction</h2>
        </div>
        <form method="POST" id="editTransactionForm" class="modal-form">
            <input type="hidden" name="t_id" id="editTransactionId">
            <label for="t_date">Transaction Date:</label>
            <input type="date" id="t_date" name="t_date" readonly>
            <label for="t_customer_name">Customer Name:</label>
            <input type="text" id="t_customer_name" name="t_customer_name" readonly>
            <label for="t_credit_date">Credit Date:</label>
            <input type="date" id="t_credit_date" name="t_credit_date" required>
            <label for="t_description">Description:</label>
            <select id="t_description" name="t_description" required>
                <option value="No description">No description</option>
                <option value="Custom description">Custom description</option>
                <option value="Stakeholder">Stakeholder</option>
                <option value="Plan 500">Plan 500</option>
                <option value="Plan 999">Plan 999</option>
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
            <label for="t_amount">Amount:</label>
            <input type="number" id="t_amount" name="t_amount" min="0" step="0.01" required>
            <input type="hidden" name="edit_transaction" value="1">
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('editTransactionModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Account Filter Modal -->
<div id="accountFilterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Filter by Customer Name</h2>
        </div>
        <form id="accountFilterForm" class="modal-form">
            <input type="hidden" name="tab" id="accountFilterTab" value="transaction">
            <label for="account_filter">Select Customer Name</label>
            <select name="account_filter" id="account_filter">
                <option value="">All Customers</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="modal-footer">
                <button type="button" class="modal-btn cancel" onclick="closeModal('accountFilterModal')">Cancel</button>
                <button type="submit" class="modal-btn confirm">Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<script>
function showNotification(message, type) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = '';
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    container.appendChild(notification);
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 500);
    }, 3000);
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function showTransactionViewModal(t_id, t_date, t_customer_name, t_credit_date, t_description, t_amount, t_balance) {
    const content = `
        <p><strong>Transaction ID:</strong> ${t_id}</p>
        <p><strong>Transaction Date:</strong> ${t_date}</p>
        <p><strong>Customer Name:</strong> ${t_customer_name}</p>
        <p><strong>Credit Date:</strong> ${t_credit_date}</p>
        <p><strong>Description:</strong> ${t_description}</p>
        <p><strong>Amount:</strong> ${t_amount}</p>
        <p><strong>Advance Balance:</strong> ${t_balance}</p>
    `;
    document.getElementById('transactionViewContent').innerHTML = content;
    document.getElementById('transactionViewModal').style.display = 'block';
}

function showEditTransactionModal(t_id, t_date, t_customer_name, t_credit_date, t_description, t_amount) {
    document.getElementById('editTransactionId').value = t_id;
    document.getElementById('t_date').value = t_date;
    document.getElementById('t_customer_name').value = t_customer_name;
    document.getElementById('t_credit_date').value = t_credit_date;
    document.getElementById('t_description').value = t_description;
    document.getElementById('t_amount').value = parseFloat(t_amount.replace(/,/g, '')).toFixed(2);
    document.getElementById('editTransactionModal').style.display = 'block';
}

function showAccountFilterModal(tab) {
    document.getElementById('accountFilterTab').value = tab;
    document.getElementById('accountFilterModal').style.display = 'block';
}

let searchTimeout;
function debouncedSearchTransactions() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const searchTerm = document.getElementById('searchInput').value;
        const accountFilter = document.getElementById('account_filter') ? document.getElementById('account_filter').value : '';
        fetchTransactions(1, searchTerm, accountFilter);
    }, 300);
}

function fetchTransactions(page, searchTerm = '', accountFilter = '') {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `Payments.php?action=search&search_page=${page}&search=${encodeURIComponent(searchTerm)}&account_filter=${encodeURIComponent(accountFilter)}`, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.error) {
                showNotification(response.error, 'error');
            } else {
                document.getElementById('transactions-table-body').innerHTML = response.html;
                updatePagination(response.currentPage, response.totalPages, searchTerm, accountFilter);
            }
        }
    };
    xhr.send();
}

function updatePagination(currentPage, totalPages, searchTerm, accountFilter) {
    const pagination = document.getElementById('transaction-pagination');
    pagination.innerHTML = '';
    
    const prevLink = currentPage > 1 
        ? `<a href="#" class="pagination-link" onclick="fetchTransactions(${currentPage - 1}, '${searchTerm}', '${accountFilter}')"><i class="fas fa-chevron-left"></i></a>`
        : `<span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>`;
    
    const nextLink = currentPage < totalPages 
        ? `<a href="#" class="pagination-link" onclick="fetchTransactions(${currentPage + 1}, '${searchTerm}', '${accountFilter}')"><i class="fas fa-chevron-right"></i></a>`
        : `<span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>`;
    
    pagination.innerHTML = `
        ${prevLink}
        <span class="current-page">Page ${currentPage} of ${totalPages}</span>
        ${nextLink}
    `;
}

document.getElementById('accountFilterForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const accountFilter = document.getElementById('account_filter').value;
    fetchTransactions(1, document.getElementById('searchInput').value, accountFilter);
    closeModal('accountFilterModal');
});

// Handle session messages
<?php if (isset($_SESSION['message'])): ?>
    showNotification("<?php echo htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); ?>", 'success');
    setTimeout(() => {
        fetchTransactions(<?php echo $pageTransaction; ?>, document.getElementById('searchInput').value, document.getElementById('account_filter')?.value || '');
    }, 3000);
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    showNotification("<?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>", 'error');
    setTimeout(() => {
        fetchTransactions(<?php echo $pageTransaction; ?>, document.getElementById('searchInput').value, document.getElementById('account_filter')?.value || '');
    }, 3000);
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>

</body>
</html>
