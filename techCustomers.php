<?php
session_start();
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Function to escape strings for JavaScript
function jsEscape($str) {
    return str_replace(
        ["\\", "'", "\"", "\n", "\r", "\t"],
        ["\\\\", "\\'", "\\\"", "\\n", "\\r", "\\t"],
        $str
    );
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

// Handle AJAX search requests
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['search'])) {
    $searchTerm = $_GET['search'];
    $page = isset($_GET['search_page']) ? (int)$_GET['search_page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $searchTerm = $conn->real_escape_string($searchTerm);
    $likeSearch = '%' . $searchTerm . '%';

    $sqlCount = "SELECT COUNT(*) AS total FROM tbl_customer 
                 WHERE (c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL) 
                 AND (c_account_no LIKE ? OR c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ? OR c_coordinates LIKE ?)";
    $sql = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment 
            FROM tbl_customer 
            WHERE (c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL) 
            AND (c_account_no LIKE ? OR c_fname LIKE ? OR c_lname LIKE ? OR c_purok LIKE ? OR c_barangay LIKE ? OR c_contact LIKE ? OR c_email LIKE ? OR c_coordinates LIKE ?) 
            LIMIT ?, ?";

    // Get total count for pagination
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param("ssssssss", $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch);
    $stmtCount->execute();
    $countResult = $stmtCount->get_result();
    $totalRow = $countResult->fetch_assoc();
    $total = $totalRow['total'];
    $totalPages = ceil($total / $limit);
    $stmtCount->close();

    // Fetch search results
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssii", $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
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
                        <a class='view-btn' onclick=\"showViewModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_coordinates']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "')\" title='View'><i class='fas fa-eye'></i></a>
                    </td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='9' style='text-align: center;'>No customers found.</td></tr>";
    }
    $tableRows = ob_get_clean();

    // Update pagination
    echo "<script>updatePagination($page, $totalPages, '" . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . "');</script>";
    echo $tableRows;
    $stmt->close();
    $conn->close();
    exit();
}

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

// Pagination setup for active customers
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$totalQuery = "SELECT COUNT(*) AS total FROM tbl_customer WHERE c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL";
$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();
$total = $totalRow['total'];
$totalPages = ceil($total / $limit);

// Fetch active customers
$sql = "SELECT c_account_no, c_fname, c_lname, c_purok, c_barangay, c_contact, c_email, c_coordinates, c_date, c_napname, c_napport, c_macaddress, c_status, c_plan, c_equipment 
        FROM tbl_customer WHERE c_status NOT LIKE 'ARCHIVED:%' OR c_status IS NULL LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Customers</title>
    <link rel="stylesheet" href="techCustomer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">

     <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h2><img src="image/logo.png" alt="Tix Net Icon" class="sidebar-icon">TixNet Pro</h2>
        <ul>
        <li><a href="technicianD.php"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
        <li><a href="techBorrowed.php"><i class="fas fa-hand-holding icon"></i> <span>Borrowed Assets</span></a></li>
        <li><a href="techCustomers.php" class="active"><i class="fas fa-user-friends icon"></i> <span>Customers</span></a></li>
        </ul>
        <footer>
            <a href="technician_staff.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>

    <div class="container">
        <div class="upper"> 
            <h1>Customers Info</h1>
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

        <div class="table-box glass-container">
            <h2>TIMS Customers</h2>
             <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search customers..." onkeyup="debouncedSearchCustomers()">
                <span class="search-icon"><i class="fas fa-search"></i></span>
            </div>
            <table id="customers-table">
                <thead>
                    <tr>
                        <th>Account No</th>
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
                <tbody id="customers-tbody">
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
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
                                        <a class='view-btn' onclick=\"showViewModal('" . htmlspecialchars($row['c_account_no'], ENT_QUOTES, 'UTF-8') . "', '" . jsEscape($row['c_fname']) . "', '" . jsEscape($row['c_lname']) . "', '" . jsEscape($row['c_purok']) . "', '" . jsEscape($row['c_barangay']) . "', '" . jsEscape($row['c_contact']) . "', '" . jsEscape($row['c_email']) . "', '" . jsEscape($row['c_coordinates']) . "', '" . jsEscape($row['c_date']) . "', '" . jsEscape($row['c_napname']) . "', '" . jsEscape($row['c_napport']) . "', '" . jsEscape($row['c_macaddress']) . "', '" . jsEscape($displayStatus) . "', '" . jsEscape($row['c_plan']) . "', '" . jsEscape($row['c_equipment']) . "')\" title='View'><i class='fas fa-eye'></i></a>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9' style='text-align: center;'>No active customers found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <div class="pagination" id="customers-pagination">
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
</div>

<!-- View Customer Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Customer Details</h2>
        </div>
        <div id="viewContent"></div>
        <div class="modal-footer">
            <button class="modal-btn cancel" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<script>
let currentSearchPage = 1;
let updateInterval = null;

document.addEventListener('DOMContentLoaded', () => {
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
    const tbody = document.getElementById('customers-tbody');
    const defaultPageToUse = <?php echo $page; ?>;

    currentSearchPage = page;

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            tbody.innerHTML = xhr.responseText.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        }
    };
    xhr.open('GET', `TechCustomers.php?action=search&search=${encodeURIComponent(searchTerm)}&search_page=${searchTerm ? page : defaultPageToUse}`, true);
    xhr.send();
}

function updatePagination(currentPage, totalPages, searchTerm) {
    const paginationContainer = document.getElementById('customers-pagination');
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

function showViewModal(account_no, fname, lname, purok, barangay, contact, email, coordinates, date, napname, napport, macaddress, status, plan, equipment) {
    document.getElementById('viewContent').innerHTML = `
        <div class="customer-details">
            <h3>Customer Profile</h3>
            <p><strong>Account No.:</strong> ${account_no}</p>
            <p><strong>Name:</strong> ${fname} ${lname}</p>
            <p><strong>Purok:</strong> ${purok || 'N/A'}</p>
            <p><strong>Barangay:</strong> ${barangay || 'N/A'}</p>
            <p><strong>Contact:</strong> ${contact || 'N/A'}</p>
            <p><strong>Coordinates:</strong> ${coordinates || 'N/A'}</p>
            <p><strong>Email:</strong> ${email || 'N/A'}</p>
            <h3>Advance Profile</h3>
            <p><strong>Subscription Date:</strong> ${date || 'N/A'}</p>
            <p><strong>NAP Name:</strong> ${napname || 'N/A'}</p>
            <p><strong>NAP Port:</strong> ${napport || 'N/A'}</p>
            <p><strong>MAC Address:</strong> ${macaddress || 'N/A'}</p>
            <p><strong>Customer Status:</strong> ${status || 'N/A'}</p>
            <h3>Service Details</h3>
            <p><strong>Internet Plan:</strong> ${plan || 'N/A'}</p>
            <p><strong>Equipment:</strong> ${equipment || 'N/A'}</p>
        </div>
    `;
    document.getElementById('viewModal').style.display = 'block';
}

function updateTable() {
    const searchTerm = document.getElementById('searchInput').value;
    const tbody = document.getElementById('customers-tbody');
    const defaultPageToUse = <?php echo $page; ?>;

    if (searchTerm) {
        searchCustomers(currentSearchPage);
    } else {
        fetch(`TechCustomers.php?page=${defaultPageToUse}`)
            .then(response => response.text())
            .then(data => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const newTableBody = doc.querySelector('#customers-tbody');
                const currentTableBody = document.querySelector('#customers-tbody');
                currentTableBody.innerHTML = newTableBody.innerHTML;
            })
            .catch(error => console.error('Error updating table:', error));
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = 'none';
    }
}
</script>
</body>
</html>