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
$technician_id = '';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $username . '.png';
$defaultAvatar = 'default-avatar.png';

// Fetch user details from database
if ($conn) {
    $sql = "SELECT u_fname, u_lname, u_type, u_id FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($firstName, $lastName, $userType, $technician_id);
    $stmt->fetch();
    $stmt->close();
} else {
    $_SESSION['error'] = "Database connection failed.";
    header("Location: index.php");
    exit();
}

// Fallback: If u_technician_id is not available, use username as tech_id
if (empty($technician_id)) {
    $technician_id = $username;
}

// Set avatar path
if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} else {
    $_SESSION['avatarPath'] = $defaultAvatar;
}
$avatarPath = $_SESSION['avatarPath'];

// Fetch unique asset names for filter modal
$uniqueAssetNames = [];

if ($conn) {
    $sqlNames = "SELECT DISTINCT a_name FROM tbl_asset_status WHERE a_status = 'Borrowed' AND tech_id = ? ORDER BY a_name";
    $stmtNames = $conn->prepare($sqlNames);
    $stmtNames->bind_param("s", $technician_id);
    $stmtNames->execute();
    $resultNames = $stmtNames->get_result();
    while ($row = $resultNames->fetch_assoc()) {
        $uniqueAssetNames[] = $row['a_name'];
    }
    $stmtNames->close();
}

// Handle AJAX request for view modal data
if (isset($_GET['id']) && !isset($_GET['page']) && !isset($_GET['action'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    $sql = "SELECT a_ref_no, a_name, a_serial_no, tech_name, tech_id, a_date 
            FROM tbl_asset_status 
            WHERE a_id = ? AND a_status = 'Borrowed' AND tech_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $id, $technician_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'refNo' => $row['a_ref_no'],
            'assetName' => $row['a_name'],
            'serialNo' => $row['a_serial_no'],
            'technicianName' => $row['tech_name'],
            'technicianId' => $row['tech_id'],
            'date' => $row['a_date']
        ]);
    } else {
        echo json_encode(['refNo' => null, 'assetName' => null, 'serialNo' => null, 'technicianName' => null, 'technicianId' => null, 'date' => null]);
    }
    
    $stmt->close();
    $conn->close();
    exit();
}

// Handle AJAX search request with filters
if (isset($_GET['action']) && $_GET['action'] === 'search' && isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $filterAssetName = trim($_GET['filter_asset_name'] ?? '');
    $page = isset($_GET['search_page']) ? (int)$_GET['search_page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $output = '';

    $params = [$technician_id];
    $types = 's';
    $whereClauses = ['a_status = ?', 'tech_id = ?'];
    $params[] = 'Borrowed';
    $types .= 's';

    if ($searchTerm !== '') {
        $whereClauses[] = "(a_ref_no LIKE ? OR a_name LIKE ? OR a_serial_no LIKE ? OR tech_name LIKE ? OR tech_id LIKE ? OR a_date LIKE ?)";
        $searchWildcard = "%$searchTerm%";
        $params = array_merge($params, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
        $types .= 'ssssss';
    }

    if ($filterAssetName !== '') {
        $whereClauses[] = "a_name = ?";
        $params[] = $filterAssetName;
        $types .= 's';
    }

    $whereClause = implode(' AND ', $whereClauses);

    // Count total matching records for pagination
    $countSql = "SELECT COUNT(*) as total FROM tbl_asset_status WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    $totalPages = ceil($totalRecords / $limit);

    // Fetch paginated search results
    $sql = "SELECT a_id, a_ref_no, a_name, a_serial_no, tech_name, tech_id, a_date 
            FROM tbl_asset_status 
            WHERE $whereClause 
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
            $serialNo = ($row['a_serial_no'] === '0' || empty($row['a_serial_no'])) ? '' : htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8');
            $output .= "<tr> 
                          <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td> 
                          <td>" . (isset($row['a_name']) ? htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') : 'N/A') . "</td>  
                          <td>" . $serialNo . "</td>
                          <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                          <td class='action-buttons'>
                              <a class='view-btn' onclick=\"showViewModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['tech_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['tech_id'], ENT_QUOTES, 'UTF-8') . "', '" . $serialNo . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                          </td>
                        </tr>";
        }
    } else {
        $output = "<tr><td colspan='5'>You have no borrowed records.</td></tr>";
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

// Fetch total number of borrowed assets for the technician
$countQuery = "SELECT COUNT(*) as total FROM tbl_asset_status WHERE a_status = 'Borrowed' AND tech_id = ?";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param("s", $technician_id);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = $countResult->fetch_assoc()['total'];
$countStmt->close();
$totalPages = ceil($totalRecords / $limit);

// Fetch borrowed assets with pagination
$sqlBorrowed = "SELECT a_id, a_ref_no, a_name, a_serial_no, tech_name, tech_id, a_date 
                FROM tbl_asset_status 
                WHERE a_status = 'Borrowed' AND tech_id = ? 
                LIMIT ?, ?";
$stmt = $conn->prepare($sqlBorrowed);
$stmt->bind_param("sii", $technician_id, $offset, $limit);
$stmt->execute();
$resultBorrowed = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Borrowed Assets</title>
    <link rel="stylesheet" href="techborrowedTB.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
        <li><a href="technicianD.php"><i class="fas fa-tachometer-alt icon"></i> <span>Dashboard</span></a></li>
        <li><a href="techBorrowed.php" class="active"><i class="fas fa-hand-holding icon"></i> <span>Borrowed Assets</span></a></li>
        <li><a href="TechCustomers.php"><i class="fas fa-user-friends icon"></i> <span>Customers</span></a></li>
        </ul>
        <footer>
            <a href="technician_staff.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
            <h2>Borrowed List</h2>
            <?php if ($userType === 'admin'): ?>
                <div class="username">
                    Welcome, <?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>!
                    <i class="fas fa-user-shield admin-icon"></i>
                </div>
            <?php endif; ?>

            <div class="borrowed">
                <table id="borrowedTable">
                    <thead>
                        <tr>
                            <th>Asset Ref No.</th>
                            <th>
                                Asset Name
                                <button class="filter-btn" onclick="showAssetNameFilterModal()" title="Filter by Asset Name">
                                    <i class='bx bx-filter'></i>
                                </button>
                            </th>
                            <th>Asset Serial No.</th>
                            <th>Date Borrowed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php 
                        if ($resultBorrowed && $resultBorrowed->num_rows > 0) { 
                            while ($row = $resultBorrowed->fetch_assoc()) { 
                                $serialNo = ($row['a_serial_no'] === '0' || empty($row['a_serial_no'])) ? '' : htmlspecialchars($row['a_serial_no'], ENT_QUOTES, 'UTF-8');
                                echo "<tr> 
                                        <td>" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td>" . (isset($row['a_name']) ? htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') : 'N/A') . "</td>  
                                        <td>" . $serialNo . "</td>
                                        <td>" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "</td> 
                                        <td class='action-buttons'>
                                            <a class='view-btn' onclick=\"showViewModal('" . htmlspecialchars($row['a_id'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_ref_no'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['a_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['tech_name'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($row['tech_id'], ENT_QUOTES, 'UTF-8') . "', '" . $serialNo . "', '" . htmlspecialchars($row['a_date'], ENT_QUOTES, 'UTF-8') . "')\" title='View'><i class='fas fa-eye'></i></a>
                                        </td>
                                      </tr>"; 
                            } 
                        } else { 
                            echo "<tr><td colspan='5'>You have no borrowed records.</td></tr>"; 
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
    </div>
</div>

<script>
let currentSearchPage = 1;
let defaultPage = <?php echo json_encode($page); ?>;
let updateInterval = null;
window.currentFilters = { assetName: '' };

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

function searchBorrowed(page = 1, filterAssetName = '') {
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
    const url = `techBorrowed.php?action=search&search=${encodeURIComponent(searchTerm)}&search_page=${searchTerm ? page : defaultPage}` +
                `&filter_asset_name=${encodeURIComponent(filterAssetName)}`;
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

function showViewModal(id, refNo, assetName, technicianName, technicianId, serialNo, date) {
    const displaySerialNo = (serialNo === '0' || !serialNo) ? '' : serialNo;
    const modalContent = `
        <p><strong>Asset Ref No.:</strong> ${refNo || 'N/A'}</p>
        <p><strong>Asset Name:</strong> ${assetName || 'N/A'}</p>
        <p><strong>Technician Name:</strong> ${technicianName}</p>
        <p><strong>Technician ID:</strong> ${technicianId}</p>
        <p><strong>Asset Serial No.:</strong> ${displaySerialNo}</p>
        <p><strong>Date Borrowed:</strong> ${date}</p>
    `;
    document.getElementById('viewModalContent').innerHTML = modalContent;
    document.getElementById('viewModal').style.display = 'flex';
}

function showAssetNameFilterModal() {
    document.getElementById('assetNameFilterModal').style.display = 'flex';
}

function applyAssetNameFilter() {
    const nameSelect = document.getElementById('asset_name_filter');
    const selectedName = nameSelect.value;
    window.currentFilters.assetName = selectedName;
    searchBorrowed(1, selectedName);
    closeModal('assetNameFilterModal');
}

function updateTable() {
    const searchTerm = document.getElementById('searchInput').value;
    if (searchTerm) {
        searchBorrowed(currentSearchPage, window.currentFilters.assetName);
    } else {
        fetch(`techBorrowed.php?page=${defaultPage}`)
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
}

window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal(event.target.id);
    }
});

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