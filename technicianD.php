<?php
session_start();
include 'db.php'; // Include your database connection file

$username = $_SESSION['username'] ?? '';
$userId = $_SESSION['userId'] ?? 0;

if (!$username || !$userId) {
    echo "Unauthorized access. Please log in.";
    exit();
}

// Initialize variables
$firstName = '';
$userType = '';
$avatarPath = 'default-avatar.png';
$avatarFolder = 'Uploads/avatars/';
$userAvatar = $avatarFolder . $username . '.png';

if (file_exists($userAvatar)) {
    $_SESSION['avatarPath'] = $userAvatar . '?' . time();
} else {
    $_SESSION['avatarPath'] = 'default-avatar.png';
}
$avatarPath = $_SESSION['avatarPath'];

// Pagination settings
$limit = 10; // 10 tickets per page
$regularActivePage = isset($_GET['regularActivePage']) ? max(1, (int)$_GET['regularActivePage']) : 1;
$supportActivePage = isset($_GET['supportActivePage']) ? max(1, (int)$_GET['supportActivePage']) : 1;
$regularArchivedPage = isset($_GET['regularArchivedPage']) ? max(1, (int)$_GET['regularArchivedPage']) : 1;
$supportArchivedPage = isset($_GET['supportArchivedPage']) ? max(1, (int)$_GET['supportArchivedPage']) : 1;
$regularActiveOffset = max(0, ($regularActivePage - 1) * $limit);
$supportActiveOffset = max(0, ($supportActivePage - 1) * $limit);
$regularArchivedOffset = max(0, ($regularArchivedPage - 1) * $limit);
$supportArchivedOffset = max(0, ($supportArchivedPage - 1) * $limit);
$tab = $_GET['tab'] ?? 'regular'; // Main tab: regular, support, regularArchived, supportArchived
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($conn) {
    // Fetch firstName and userType
    $sqlUser = "SELECT u_fname, u_type FROM tbl_user WHERE u_username = ?";
    $stmt = $conn->prepare($sqlUser);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $resultUser = $stmt->get_result();
    if ($resultUser->num_rows > 0) {
        $row = $resultUser->fetch_assoc();
        $firstName = $row['u_fname'] ?: '';
        $userType = $row['u_type'] ?: '';
    }
    $stmt->close();

    // Regular Ticket counts (exclude archived)
    $sqlOpenTickets = "SELECT COUNT(*) AS openTickets FROM tbl_ticket WHERE t_status = 'open' AND (t_details NOT LIKE 'ARCHIVED:%' OR t_details IS NULL) AND t_status != 'archived'";
    if ($searchTerm) {
        $sqlOpenTickets .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
    }
    $stmtOpenTickets = $conn->prepare($sqlOpenTickets);
    if ($searchTerm) {
        $searchLike = "%$searchTerm%";
        $stmtOpenTickets->bind_param("sss", $searchLike, $searchLike, $searchLike);
    }
    $stmtOpenTickets->execute();
    $resultOpenTickets = $stmtOpenTickets->get_result();
    $openTickets = $resultOpenTickets ? ($resultOpenTickets->fetch_assoc()['openTickets'] ?? 0) : 0;
    $stmtOpenTickets->close();

    $sqlClosedTickets = "SELECT COUNT(*) AS closedTickets FROM tbl_ticket WHERE t_status = 'closed' AND (t_details NOT LIKE 'ARCHIVED:%' OR t_details IS NULL) AND t_status != 'archived'";
    if ($searchTerm) {
        $sqlClosedTickets .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
    }
    $stmtClosedTickets = $conn->prepare($sqlClosedTickets);
    if ($searchTerm) {
        $searchLike = "%$searchTerm%";
        $stmtClosedTickets->bind_param("sss", $searchLike, $searchLike, $searchLike);
    }
    $stmtClosedTickets->execute();
    $resultClosedTickets = $stmtClosedTickets->get_result();
    $closedTickets = $resultClosedTickets ? ($resultClosedTickets->fetch_assoc()['closedTickets'] ?? 0) : 0;
    $stmtClosedTickets->close();

    $sqlArchivedRegular = "SELECT COUNT(*) AS archivedTickets FROM tbl_ticket WHERE t_details LIKE 'ARCHIVED:%' AND t_status != 'archived'";
    if ($searchTerm) {
        $sqlArchivedRegular .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
    }
    $stmtArchivedRegular = $conn->prepare($sqlArchivedRegular);
    if ($searchTerm) {
        $stmtArchivedRegular->bind_param("sss", $searchLike, $searchLike, $searchLike);
    }
    $stmtArchivedRegular->execute();
    $resultArchivedRegular = $stmtArchivedRegular->get_result();
    $archivedRegular = $resultArchivedRegular ? ($resultArchivedRegular->fetch_assoc()['archivedTickets'] ?? 0) : 0;
    $stmtArchivedRegular->close();

    // Support Tickets counts
    $sqlSupportOpen = "SELECT COUNT(*) AS supportOpen FROM tbl_supp_tickets WHERE s_status = 'Open' AND (s_message NOT LIKE 'ARCHIVED:%' OR s_message IS NULL)";
    if ($searchTerm) {
        $sqlSupportOpen .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
    }
    $stmtSupportOpen = $conn->prepare($sqlSupportOpen);
    if ($searchTerm) {
        $searchLike = "%$searchTerm%";
        $stmtSupportOpen->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtSupportOpen->execute();
    $resultSupportOpen = $stmtSupportOpen->get_result();
    $supportOpen = $resultSupportOpen ? ($resultSupportOpen->fetch_assoc()['supportOpen'] ?? 0) : 0;
    $stmtSupportOpen->close();

    $sqlSupportClosed = "SELECT COUNT(*) AS supportClosed FROM tbl_supp_tickets WHERE s_status = 'Closed' AND (s_message NOT LIKE 'ARCHIVED:%' OR s_message IS NULL)";
    if ($searchTerm) {
        $sqlSupportClosed .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
    }
    $stmtSupportClosed = $conn->prepare($sqlSupportClosed);
    if ($searchTerm) {
        $searchLike = "%$searchTerm%";
        $stmtSupportCloseduki->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtSupportClosed->execute();
    $resultSupportClosed = $stmtSupportClosed->get_result();
    $supportClosed = $resultSupportClosed ? ($resultSupportClosed->fetch_assoc()['supportClosed'] ?? 0) : 0;
    $stmtSupportClosed->close();

    $sqlArchivedSupport = "SELECT COUNT(*) AS archivedSupport FROM tbl_supp_tickets WHERE s_message LIKE 'ARCHIVED:%'";
    if ($searchTerm) {
        $sqlArchivedSupport .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
    }
    $stmtArchivedSupport = $conn->prepare($sqlArchivedSupport);
    if ($searchTerm) {
        $stmtArchivedSupport->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtArchivedSupport->execute();
    $resultArchivedSupport = $stmtArchivedSupport->get_result();
    $archivedSupport = $resultArchivedSupport ? ($resultArchivedSupport->fetch_assoc()['archivedSupport'] ?? 0) : 0;
    $stmtArchivedSupport->close();

    // Pending tasks
    $pendingTasks = $openTickets + $supportOpen;

    // Handle actions (close, archive, unarchive, delete)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'close' && isset($_POST['id']) && isset($_POST['type']) && isset($_POST['technicianFirstName'])) {
            $id = (int)$_POST['id'];
            $type = $_POST['type'];
            $technicianFirstName = trim($_POST['technicianFirstName']);
            $targetTab = ($type === 'regular') ? 'regular' : 'support';

            // Validate technician's first name
            $sql = "SELECT u_fname FROM tbl_user WHERE u_username = ? AND u_type = 'technician'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (strtolower($row['u_fname']) !== strtolower($technicianFirstName)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid first name.']);
                    $stmt->close();
                    exit;
                }
            }
            $stmt->close();

            if ($type === 'regular') {
                $sql = "UPDATE tbl_ticket SET t_status = 'closed' WHERE t_id = ? AND t_status = 'open' AND t_status != 'archived'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
            } else {
                $sql = "UPDATE tbl_supp_tickets SET s_status = 'Closed' WHERE id = ? AND s_status = 'Open'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
            }
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $logDescription = "Technician $firstName closed $type ticket ID $id";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description) VALUES (NOW(), ?)";
                $stmtLog = $conn->prepare($sqlLog);
                $stmtLog->bind_param("s", $logDescription);
                $stmtLog->execute();
                $stmtLog->close();

                $redirect_url = "technicianD.php?tab=" . urlencode($targetTab) .
                                "&regularActivePage=" . urlencode($regularActivePage) .
                                "&supportActivePage=" . urlencode($supportActivePage) .
                                "&regularArchivedPage=" . urlencode($regularArchivedPage) .
                                "&supportArchivedPage=" . urlencode($supportArchivedPage) .
                                ($searchTerm ? "&search=" . urlencode($searchTerm) : '');
                echo json_encode(['success' => true, 'redirect' => $redirect_url]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to close ticket.']);
            }
            $stmt->close();
            exit;
        } elseif (isset($_POST['action']) && isset($_POST['id']) && isset($_POST['type'])) {
            $id = (int)$_POST['id'];
            $type = trim($_POST['type']);
            $action = trim($_POST['action']);
            $targetTab = $tab; // Use current tab (regular, support, regularArchived, supportArchived)

            if ($action === 'archive') {
                $targetTab = ($type === 'regular') ? 'regular' : 'support';
                if ($type === 'regular') {
                    $sql = "UPDATE tbl_ticket SET t_details = CONCAT('ARCHIVED:', t_details) WHERE t_id = ? AND t_status != 'archived'";
                } else {
                    $sql = "UPDATE tbl_supp_tickets SET s_message = CONCAT('ARCHIVED:', s_message) WHERE id = ?";
                }
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
            } elseif ($action === 'unarchive') {
                $targetTab = ($type === 'regular') ? 'regular' : 'support';
                if ($type === 'regular') {
                    $sql = "UPDATE tbl_ticket SET t_details = REPLACE(t_details, 'ARCHIVED:', '') WHERE t_id = ?";
                } else {
                    $sql = "UPDATE tbl_supp_tickets SET s_message = REPLACE(s_message, 'ARCHIVED:', '') WHERE id = ?";
                }
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
            } elseif ($action === 'delete') {
                $targetTab = ($type === 'regular') ? 'regularArchived' : 'supportArchived';
                if ($type === 'regular') {
                    $sql = "DELETE FROM tbl_ticket WHERE t_id = ? AND t_details LIKE 'ARCHIVED:%'";
                } else {
                    $sql = "DELETE FROM tbl_supp_tickets WHERE id = ? AND s_message LIKE 'ARCHIVED:%'";
                }
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
            }

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $logDescription = "Technician $firstName performed $action on $type ticket ID $id";
                $sqlLog = "INSERT INTO tbl_logs (l_stamp, l_description) VALUES (NOW(), ?)";
                $stmtLog = $conn->prepare($sqlLog);
                $stmtLog->bind_param("s", $logDescription);
                $stmtLog->execute();
                $stmtLog->close();

                $redirect_url = "technicianD.php?tab=" . urlencode($targetTab) .
                                "&regularActivePage=" . urlencode($regularActivePage) .
                                "&supportActivePage=" . urlencode($supportActivePage) .
                                "&regularArchivedPage=" . urlencode($regularArchivedPage) .
                                "&supportArchivedPage=" . urlencode($supportArchivedPage) .
                                ($searchTerm ? "&search=" . urlencode($searchTerm) : '');
                header("Location: $redirect_url");
            } else {
                echo json_encode(['success' => false, 'message' => "Failed to $action ticket."]);
            }
            $stmt->close();
            exit;
        }
    }

    // Pagination for Regular Tickets - Active
    $sqlTotalRegularActive = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE (t_details NOT LIKE 'ARCHIVED:%' OR t_details IS NULL) AND t_status != 'archived'";
    if ($searchTerm) {
        $sqlTotalRegularActive .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
    }
    $stmtTotalRegularActive = $conn->prepare($sqlTotalRegularActive);
    if ($searchTerm) {
        $searchLike = "%$searchTerm%";
        $stmtTotalRegularActive->bind_param("sss", $searchLike, $searchLike, $searchLike);
    }
    $stmtTotalRegularActive->execute();
    $resultTotalRegularActive = $stmtTotalRegularActive->get_result();
    $totalRegularActive = $resultTotalRegularActive ? ($resultTotalRegularActive->fetch_assoc()['total'] ?? 0) : 0;
    $stmtTotalRegularActive->close();
    $totalRegularActivePages = ceil($totalRegularActive / $limit) ?: 1;
    $regularActivePage = min($regularActivePage, $totalRegularActivePages);
    $regularActiveOffset = max(0, ($regularActivePage - 1) * $limit);

    $sqlRegularActive = "SELECT t_id, t_aname, t_subject, t_details, t_status, t_date 
                        FROM tbl_ticket 
                        WHERE (t_details NOT LIKE 'ARCHIVED:%' OR t_details IS NULL) AND t_status != 'archived'";
    if ($searchTerm) {
        $sqlRegularActive .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
    }
    $sqlRegularActive .= " ORDER BY t_date ASC LIMIT ? OFFSET ?";
    $stmtRegularActive = $conn->prepare($sqlRegularActive);
    if ($searchTerm) {
        $stmtRegularActive->bind_param("sssii", $searchLike, $searchLike, $searchLike, $limit, $regularActiveOffset);
    } else {
        $stmtRegularActive->bind_param("ii", $limit, $regularActiveOffset);
    }
    $stmtRegularActive->execute();
    $resultRegularActive = $stmtRegularActive->get_result();
    $stmtRegularActive->close();

    // Pagination for Support Tickets - Active
    $sqlTotalSupportActive = "SELECT COUNT(*) AS total FROM tbl_supp_tickets WHERE (s_message NOT LIKE 'ARCHIVED:%' OR s_message IS NULL) AND s_status IN ('Open', 'Closed')";
    if ($searchTerm) {
        $sqlTotalSupportActive .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
    }
    $stmtTotalSupportActive = $conn->prepare($sqlTotalSupportActive);
    if ($searchTerm) {
        $searchLike = "%$searchTerm%";
        $stmtTotalSupportActive->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtTotalSupportActive->execute();
    $resultTotalSupportActive = $stmtTotalSupportActive->get_result();
    $totalSupportActive = $resultTotalSupportActive ? ($resultTotalSupportActive->fetch_assoc()['total'] ?? 0) : 0;
    $stmtTotalSupportActive->close();
    $totalSupportActivePages = ceil($totalSupportActive / $limit) ?: 1;
    $supportActivePage = min($supportActivePage, $totalSupportActivePages);
    $supportActiveOffset = max(0, ($supportActivePage - 1) * $limit);

    $sqlSupportActive = "SELECT id AS t_id, CONCAT(c_fname, ' ', c_lname) AS t_aname, s_subject, s_ref, s_message AS t_details, s_status AS t_status 
                        FROM tbl_supp_tickets 
                        WHERE (s_message NOT LIKE 'ARCHIVED:%' OR s_message IS NULL) AND s_status IN ('Open', 'Closed')";
    if ($searchTerm) {
        $sqlSupportActive .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
    }
    $sqlSupportActive .= " ORDER BY id ASC LIMIT ? OFFSET ?";
    $stmtSupportActive = $conn->prepare($sqlSupportActive);
    if ($searchTerm) {
        $stmtSupportActive->bind_param("ssssii", $searchLike, $searchLike, $searchLike, $searchLike, $limit, $supportActiveOffset);
    } else {
        $stmtSupportActive->bind_param("ii", $limit, $supportActiveOffset);
    }
    $stmtSupportActive->execute();
    $resultSupportActive = $stmtSupportActive->get_result();
    $stmtSupportActive->close();

    // Pagination for Regular Tickets - Archived
    $sqlTotalRegularArchived = "SELECT COUNT(*) AS total FROM tbl_ticket WHERE t_details LIKE 'ARCHIVED:%' AND t_status != 'archived'";
    if ($searchTerm) {
        $sqlTotalRegularArchived .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
    }
    $stmtTotalRegularArchived = $conn->prepare($sqlTotalRegularArchived);
    if ($searchTerm) {
        $searchLike = "%$searchTerm%";
        $stmtTotalRegularArchived->bind_param("sss", $searchLike, $searchLike, $searchLike);
    }
    $stmtTotalRegularArchived->execute();
    $resultTotalRegularArchived = $stmtTotalRegularArchived->get_result();
    $totalRegularArchived = $resultTotalRegularArchived ? ($resultTotalRegularArchived->fetch_assoc()['total'] ?? 0) : 0;
    $stmtTotalRegularArchived->close();
    $totalRegularArchivedPages = ceil($totalRegularArchived / $limit) ?: 1;
    $regularArchivedPage = min($regularArchivedPage, $totalRegularArchivedPages);
    $regularArchivedOffset = max(0, ($regularArchivedPage - 1) * $limit);

    $sqlRegularArchived = "SELECT t_id, t_aname, t_subject, t_details, t_status, t_date 
                          FROM tbl_ticket 
                          WHERE t_details LIKE 'ARCHIVED:%' AND t_status != 'archived'";
    if ($searchTerm) {
        $sqlRegularArchived .= " AND (t_aname LIKE ? OR t_subject LIKE ? OR t_details LIKE ?)";
    }
    $sqlRegularArchived .= " ORDER BY t_date ASC LIMIT ? OFFSET ?";
    $stmtRegularArchived = $conn->prepare($sqlRegularArchived);
    if ($searchTerm) {
        $stmtRegularArchived->bind_param("sssii", $searchLike, $searchLike, $searchLike, $limit, $regularArchivedOffset);
    } else {
        $stmtRegularArchived->bind_param("ii", $limit, $regularArchivedOffset);
    }
    $stmtRegularArchived->execute();
    $resultRegularArchived = $stmtRegularArchived->get_result();
    $stmtRegularArchived->close();

    // Pagination for Support Tickets - Archived
    $sqlTotalSupportArchived = "SELECT COUNT(*) AS total FROM tbl_supp_tickets WHERE s_message LIKE 'ARCHIVED:%'";
    if ($searchTerm) {
        $sqlTotalSupportArchived .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
    }
    $stmtTotalSupportArchived = $conn->prepare($sqlTotalSupportArchived);
    if ($searchTerm) {
        $searchLike = "%$searchTerm%";
        $stmtTotalSupportArchived->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $stmtTotalSupportArchived->execute();
    $resultTotalSupportArchived = $stmtTotalSupportArchived->get_result();
    $totalSupportArchived = $resultTotalSupportArchived ? ($resultTotalSupportArchived->fetch_assoc()['total'] ?? 0) : 0;
    $stmtTotalSupportArchived->close();
    $totalSupportArchivedPages = ceil($totalSupportArchived / $limit) ?: 1;
    $supportArchivedPage = min($supportArchivedPage, $totalSupportArchivedPages);
    $supportArchivedOffset = max(0, ($supportArchivedPage - 1) * $limit);

    $sqlSupportArchived = "SELECT id AS t_id, CONCAT(c_fname, ' ', c_lname) AS t_aname, s_subject, s_ref, s_message AS t_details, s_status AS t_status 
                          FROM tbl_supp_tickets 
                          WHERE s_message LIKE 'ARCHIVED:%'";
    if ($searchTerm) {
        $sqlSupportArchived .= " AND (CONCAT(c_fname, ' ', c_lname) LIKE ? OR s_subject LIKE ? OR s_message LIKE ? OR s_ref LIKE ?)";
    }
    $sqlSupportArchived .= " ORDER BY id ASC LIMIT ? OFFSET ?";
    $stmtSupportArchived = $conn->prepare($sqlSupportArchived);
    if ($searchTerm) {
        $stmtSupportArchived->bind_param("ssssii", $searchLike, $searchLike, $searchLike, $searchLike, $limit, $supportArchivedOffset);
    } else {
        $stmtSupportArchived->bind_param("ii", $limit, $supportArchivedOffset);
    }
    $stmtSupportArchived->execute();
    $resultSupportArchived = $stmtSupportArchived->get_result();
    $stmtSupportArchived->close();
} else {
    $firstName = '';
    $userType = '';
    $openTickets = $closedTickets = $pendingTasks = $supportOpen = $supportClosed = 0;
    $archivedRegular = $archivedSupport = 0;
    $totalRegularActive = $totalSupportActive = 0;
    $totalRegularArchived = $totalSupportArchived = 0;
    $totalRegularActivePages = $totalSupportActivePages = 1;
    $totalRegularArchivedPages = $totalSupportArchivedPages = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISP Technician Dashboard</title>
    <link rel="stylesheet" href="technicianD.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body> 
<div class="wrapper">
    <div class="sidebar glass-container">
        <h2>Task Management</h2>
        <ul>
            <li><a href="technicianD.php" class="active"><img src="https://img.icons8.com/parakeet/35/dashboard.png" alt="dashboard"/><span>Dashboard</span></a></li>
            <li><a href="assetsT.php"><img src="https://img.icons8.com/matisse/100/view.png" alt="view"/><span>View Assets</span></a></li>
            <li><a href="techBorrowed.php"><img src="https://img.icons8.com/cotton/35/documents--v1.png" alt="documents--v1"/><span>Borrowed Records</span></a></li>
        </ul>
        <footer>
            <a href="index.php" class="back-home"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </footer>
    </div>
    <div class="container">
        <div class="upper">
            <h1>Technician Dashboard</h1>
            <div class="search-container">
                <input type="text" class="search-bar" id="searchInput" placeholder="Search tickets..." value="<?php echo htmlspecialchars($searchTerm); ?>" onkeyup="debouncedSearchTickets()">
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

        <!-- Dashboard Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <i class="fas fa-tasks text-yellow-500"></i>
                <div class="card-content">
                    <h3>Pending Tasks</h3>
                    <p><strong><?php echo $pendingTasks; ?></strong></p>
                    <p>Regular Open: <?php echo $openTickets; ?> | Support Open: <?php echo $supportOpen; ?></p>
                </div>
            </div>
            <div class="card">
                <i class="fas fa-ticket-alt text-orange-500"></i>
                <div class="card-content">
                    <h3>Regular Tickets</h3>
                    <p>Open: <?php echo $openTickets; ?> | Closed: <?php echo $closedTickets; ?></p>
                    <p>Archived: <?php echo $archivedRegular; ?></p>
                </div>
            </div>
            <div class="card">
                <i class="fas fa-headset text-blue-500"></i>
                <div class="card-content">
                    <h3>Support Tickets</h3>
                    <p>Open: <?php echo $supportOpen; ?> | Closed: <?php echo $supportClosed; ?></p>
                    <p>Archived: <?php echo $archivedSupport; ?></p>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <div id="viewTicketModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Ticket Details</h2>
                </div>
                <div id="viewTicketContent"></div>
                <div class="modal-footer">
                    <button class="modal-btn cancel" onclick="closeModal('viewTicketModal')">Close</button>
                </div>
            </div>
        </div>

        <div id="actionModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"></div>
                <div class="modal-body"></div>
                <div class="modal-footer"></div>
            </div>
        </div>

        <div id="closeModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Close Ticket</h2>
                </div>
                <div class="modal-body">
                    <p>Confirm closing ticket <span id="closeTicketIdDisplay"></span> for <span id="closeTicketName"></span>.</p>
                    <label for="technicianFirstName">Enter Your First Name</label>
                    <input type="text" id="technicianFirstName" name="technicianFirstName" required>
                    <p id="closeError" style="color: red; display: none;"></p>
                </div>
                <div class="modal-footer">
                    <button class="modal-btn cancel" onclick="closeModal('closeModal')">Cancel</button>
                    <button class="modal-btn confirm" id="confirmCloseBtn" onclick="submitCloseAction()">Confirm</button>
                </div>
            </div>
        </div>

        <form id="actionForm" method="POST" style="display: none;">
            <input type="hidden" name="action" id="formAction">
            <input type="hidden" name="id" id="formId">
            <input type="hidden" name="type" id="formType">
        </form>

        <div class="tab-container">
            <!-- Main Tabs -->
            <div class="main-tab-buttons">
                <button class="tab-button <?php echo in_array($tab, ['regular', 'regularArchived']) ? 'active' : ''; ?>" onclick="openMainTab('regularTickets', '<?php echo $tab === 'regularArchived' ? 'regularArchived' : 'regular'; ?>')">Regular Tickets</button>
                <button class="tab-button <?php echo in_array($tab, ['support', 'supportArchived']) ? 'active' : ''; ?>" onclick="openMainTab('supportTickets', '<?php echo $tab === 'supportArchived' ? 'supportArchived' : 'support'; ?>')">Support Tickets</button>
            </div>

            <!-- Regular Tickets -->
            <div id="regularTickets" class="main-tab-content <?php echo in_array($tab, ['regular', 'regularArchived']) ? 'active' : ''; ?>">
                <div class="table-box">
                    <div class="sub-tab-buttons">
                        <button class="tab-button <?php echo $tab === 'regular' ? 'active' : ''; ?>" onclick="openSubTab('regularTicketsContent', 'regular')">Active (<?php echo $totalRegularActive; ?>)</button>
                        <button class="tab-button <?php echo $tab === 'regularArchived' ? 'active' : ''; ?>" onclick="openSubTab('regularArchivedTicketsContent', 'regularArchived')">
                            Archived 
                            <?php if ($totalRegularArchived > 0): ?>
                                <span class="tab-badge"><?php echo $totalRegularArchived; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- Active Regular Tickets -->
                    <div id="regularTicketsContent" class="sub-tab-content <?php echo $tab === 'regular' ? 'active' : ''; ?>">
                        <table class="tickets-table" id="regular-active-tickets">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Customer Name</th>
                                    <th>Subject</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($resultRegularActive && $resultRegularActive->num_rows > 0) {
                                    while ($row = $resultRegularActive->fetch_assoc()) {
                                        $display_details = preg_replace('/^ARCHIVED:/', '', $row['t_details'] ?? '');
                                        $ticketData = json_encode([
                                            'id' => $row['t_id'],
                                            'aname' => $row['t_aname'] ?? '',
                                            'subject' => $row['t_subject'] ?? '',
                                            'details' => $display_details,
                                            'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                                            'date' => $row['t_date'] ?? '-'
                                        ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                        echo "<tr>
                                                <td>" . htmlspecialchars($row['t_id']) . "</td>
                                                <td>" . htmlspecialchars($row['t_aname'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($row['t_subject'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($display_details) . "</td>
                                                <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . 
                                                (strtolower($row['t_status']) === 'open' ? " clickable' onclick='openCloseModal({$row['t_id']}, \"" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "\", \"regular\")'" : "'") . 
                                                ">" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                                <td>" . htmlspecialchars($row['t_date'] ?? '-') . "</td>
                                                <td class='action-buttons'>
                                                    <span class='view-btn' onclick='showViewModal(\"regular\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                                    <span class='archive-btn' onclick='openModal(\"archive\", \"regular\", {\"id\": {$row['t_id']}})' title='Archive'><i class='fas fa-archive'></i></span>
                                                </td>
                                              </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7'>No active regular tickets found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        <div class="pagination" id="regular-active-pagination">
                            <?php
                            $paginationParams = "&supportActivePage=" . urlencode($supportActivePage) . "&regularArchivedPage=" . urlencode($regularArchivedPage) . "&supportArchivedPage=" . urlencode($supportArchivedPage) . ($searchTerm ? "&search=" . urlencode($searchTerm) : '');
                            if ($regularActivePage > 1): ?>
                                <a href="?tab=regular&regularActivePage=<?php echo $regularActivePage - 1; ?><?php echo $paginationParams; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                            <?php else: ?>
                                <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                            <?php endif; ?>
                            <span class="current-page">Page <?php echo $regularActivePage; ?> of <?php echo $totalRegularActivePages; ?></span>
                            <?php if ($regularActivePage < $totalRegularActivePages): ?>
                                <a href="?tab=regular&regularActivePage=<?php echo $regularActivePage + 1; ?><?php echo $paginationParams; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                            <?php else: ?>
                                <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Archived Regular Tickets -->
                    <div id="regularArchivedTicketsContent" class="sub-tab-content <?php echo $tab === 'regularArchived' ? 'active' : ''; ?>">
                        <table class="tickets-table" id="regular-archived-tickets">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Customer Name</th>
                                    <th>Subject</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($resultRegularArchived && $resultRegularArchived->num_rows > 0) {
                                    while ($row = $resultRegularArchived->fetch_assoc()) {
                                        $display_details = preg_replace('/^ARCHIVED:/', '', $row['t_details'] ?? '');
                                        $ticketData = json_encode([
                                            'id' => $row['t_id'],
                                            'aname' => $row['t_aname'] ?? '',
                                            'subject' => $row['t_subject'] ?? '',
                                            'details' => $display_details,
                                            'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                                            'date' => $row['t_date'] ?? '-'
                                        ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                        echo "<tr>
                                                <td>" . htmlspecialchars($row['t_id']) . "</td>
                                                <td>" . htmlspecialchars($row['t_aname'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($row['t_subject'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($display_details) . "</td>
                                                <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . "'>" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                                <td>" . htmlspecialchars($row['t_date'] ?? '-') . "</td>
                                                <td class='action-buttons'>
                                                    <span class='view-btn' onclick='showViewModal(\"regular\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                                    <span class='unarchive-btn' onclick='openModal(\"unarchive\", \"regular\", {\"id\": {$row['t_id']}})' title='Unarchive'><i class='fas fa-box-open'></i></span>
                                                    <span class='delete-btn' onclick='openModal(\"delete\", \"regular\", {\"id\": {$row['t_id']}})' title='Delete'><i class='fas fa-trash'></i></span>
                                                </td>
                                              </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7'>No archived regular tickets found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        <div class="pagination" id="regular-archived-pagination">
                            <?php
                            $paginationParams = "&regularActivePage=" . urlencode($regularActivePage) . "&supportActivePage=" . urlencode($supportActivePage) . "&supportArchivedPage=" . urlencode($supportArchivedPage) . ($searchTerm ? "&search=" . urlencode($searchTerm) : '');
                            if ($regularArchivedPage > 1): ?>
                                <a href="?tab=regularArchived&regularArchivedPage=<?php echo $regularArchivedPage - 1; ?><?php echo $paginationParams; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                            <?php else: ?>
                                <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                            <?php endif; ?>
                            <span class="current-page">Page <?php echo $regularArchivedPage; ?> of <?php echo $totalRegularArchivedPages; ?></span>
                            <?php if ($regularArchivedPage < $totalRegularArchivedPages): ?>
                                <a href="?tab=regularArchived&regularArchivedPage=<?php echo $regularArchivedPage + 1; ?><?php echo $paginationParams; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                            <?php else: ?>
                                <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Support Tickets -->
            <div id="supportTickets" class="main-tab-content <?php echo in_array($tab, ['support', 'supportArchived']) ? 'active' : ''; ?>">
                <div class="table-box">
                    <div class="sub-tab-buttons">
                        <button class="tab-button <?php echo $tab === 'support' ? 'active' : ''; ?>" onclick="openSubTab('supportTicketsContent', 'support')">Active (<?php echo $totalSupportActive; ?>)</button>
                        <button class="tab-button <?php echo $tab === 'supportArchived' ? 'active' : ''; ?>" onclick="openSubTab('supportArchivedTicketsContent', 'supportArchived')">
                            Archived 
                            <?php if ($totalSupportArchived > 0): ?>
                                <span class="tab-badge"><?php echo $totalSupportArchived; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- Active Support Tickets -->
                    <div id="supportTicketsContent" class="sub-tab-content <?php echo $tab === 'support' ? 'active' : ''; ?>">
                        <table class="tickets-table" id="support-active-tickets">
                            <thead>
                                <tr>
                                    <th>Ticket No.</th>
                                    <th>Customer Name</th>
                                    <th>Subject</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($resultSupportActive && $resultSupportActive->num_rows > 0) {
                                    while ($row = $resultSupportActive->fetch_assoc()) {
                                        $display_details = preg_replace('/^ARCHIVED:/', '', $row['t_details'] ?? '');
                                        $ticketData = json_encode([
                                            'id' => $row['t_id'],
                                            'ref' => $row['s_ref'] ?? '',
                                            'aname' => $row['t_aname'] ?? '',
                                            'subject' => $row['s_subject'] ?? '',
                                            'details' => $display_details,
                                            'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                                            'date' => '-'
                                        ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                        echo "<tr>
                                                <td>" . htmlspecialchars($row['s_ref'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($row['t_aname'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($row['s_subject'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($display_details) . "</td>
                                                <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . 
                                                (strtolower($row['t_status']) === 'open' ? " clickable' onclick='openCloseModal({$row['t_id']}, \"" . htmlspecialchars($row['t_aname'], ENT_QUOTES, 'UTF-8') . "\", \"support\")'" : "'") . 
                                                ">" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                                <td class='action-buttons'>
                                                    <span class='view-btn' onclick='showViewModal(\"support\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                                    <span class='archive-btn' onclick='openModal(\"archive\", \"support\", {\"id\": {$row['t_id']}})' title='Archive'><i class='fas fa-archive'></i></span>
                                                </td>
                                              </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6'>No active support tickets found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        <div class="pagination" id="support-active-pagination">
                            <?php
                            $paginationParams = "&regularActivePage=" . urlencode($regularActivePage) . "&regularArchivedPage=" . urlencode($regularArchivedPage) . "&supportArchivedPage=" . urlencode($supportArchivedPage) . ($searchTerm ? "&search=" . urlencode($searchTerm) : '');
                            if ($supportActivePage > 1): ?>
                                <a href="?tab=support&supportActivePage=<?php echo $supportActivePage - 1; ?><?php echo $paginationParams; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                            <?php else: ?>
                                <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                            <?php endif; ?>
                            <span class="current-page">Page <?php echo $supportActivePage; ?> of <?php echo $totalSupportActivePages; ?></span>
                            <?php if ($supportActivePage < $totalSupportActivePages): ?>
                                <a href="?tab=support&supportActivePage=<?php echo $supportActivePage + 1; ?><?php echo $paginationParams; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                            <?php else: ?>
                                <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Archived Support Tickets -->
                    <div id="supportArchivedTicketsContent" class="sub-tab-content <?php echo $tab === 'supportArchived' ? 'active' : ''; ?>">
                        <table class="tickets-table" id="support-archived-tickets">
                            <thead>
                                <tr>
                                    <th>Ticket No.</th>
                                    <th>Customer Name</th>
                                    <th>Subject</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($resultSupportArchived && $resultSupportArchived->num_rows > 0) {
                                    while ($row = $resultSupportArchived->fetch_assoc()) {
                                        $display_details = preg_replace('/^ARCHIVED:/', '', $row['t_details'] ?? '');
                                        $ticketData = json_encode([
                                            'id' => $row['t_id'],
                                            'ref' => $row['s_ref'] ?? '',
                                            'aname' => $row['t_aname'] ?? '',
                                            'subject' => $row['s_subject'] ?? '',
                                            'details' => $display_details,
                                            'status' => ucfirst(strtolower($row['t_status'] ?? '')),
                                            'date' => '-'
                                        ], JSON_HEX_QUOT | JSON_HEX_TAG);
                                        echo "<tr>
                                                <td>" . htmlspecialchars($row['s_ref'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($row['t_aname'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($row['s_subject'] ?? '') . "</td>
                                                <td>" . htmlspecialchars($display_details) . "</td>
                                                <td class='status-" . strtolower(str_replace(' ', '-', $row['t_status'] ?? '')) . "'>" . ucfirst(strtolower($row['t_status'] ?? '')) . "</td>
                                                <td class='action-buttons'>
                                                    <span class='view-btn' onclick='showViewModal(\"support\", $ticketData)' title='View'><i class='fas fa-eye'></i></span>
                                                    <span class='unarchive-btn' onclick='openModal(\"unarchive\", \"support\", {\"id\": {$row['t_id']}})' title='Unarchive'><i class='fas fa-box-open'></i></span>
                                                    <span class='delete-btn' onclick='openModal(\"delete\", \"support\", {\"id\": {$row['t_id']}})' title='Delete'><i class='fas fa-trash'></i></span>
                                                </td>
                                              </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6'>No archived support tickets found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        <div class="pagination" id="support-archived-pagination">
                            <?php
                            $paginationParams = "&regularActivePage=" . urlencode($regularActivePage) . "&regularArchivedPage=" . urlencode($regularArchivedPage) . "&supportActivePage=" . urlencode($supportActivePage) . ($searchTerm ? "&search=" . urlencode($searchTerm) : '');
                            if ($supportArchivedPage > 1): ?>
                                <a href="?tab=supportArchived&supportArchivedPage=<?php echo $supportArchivedPage - 1; ?><?php echo $paginationParams; ?>" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                            <?php else: ?>
                                <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                            <?php endif; ?>
                            <span class="current-page">Page <?php echo $supportArchivedPage; ?> of <?php echo $totalSupportArchivedPages; ?></span>
                            <?php if ($supportArchivedPage < $totalSupportArchivedPages): ?>
                                <a href="?tab=supportArchived&supportArchivedPage=<?php echo $supportArchivedPage + 1; ?><?php echo $paginationParams; ?>" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                            <?php else: ?>
                                <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openMainTab(tabName, subTab) {
    const mainTabContents = document.getElementsByClassName('main-tab-content');
    for (let i = 0; i < mainTabContents.length; i++) {
        mainTabContents[i].classList.remove('active');
    }
    const mainTabButtons = document.getElementsByClassName('main-tab-buttons')[0].getElementsByClassName('tab-button');
    for (let i = 0; i < mainTabButtons.length; i++) {
        mainTabButtons[i].classList.remove('active');
    }
    document.getElementById(tabName).classList.add('active');
    const activeMainButton = document.querySelector(`[onclick="openMainTab('${tabName}', '${subTab}')"]`);
    if (activeMainButton) {
        activeMainButton.classList.add('active');
    }
    openSubTab(subTab === 'regular' ? 'regularTicketsContent' : 
               subTab === 'regularArchived' ? 'regularArchivedTicketsContent' : 
               subTab === 'support' ? 'supportTicketsContent' : 
               'supportArchivedTicketsContent', subTab);
}

function openSubTab(contentId, tabParam) {
    const mainTabId = contentId.startsWith('regular') ? 'regularTickets' : 'supportTickets';
    const mainTab = document.getElementById(mainTabId);
    const subTabContents = mainTab.getElementsByClassName('sub-tab-content');
    for (let i = 0; i < subTabContents.length; i++) {
        subTabContents[i].classList.remove('active');
    }
    const subTabButtons = mainTab.getElementsByClassName('sub-tab-buttons')[0].getElementsByClassName('tab-button');
    for (let i = 0; i < subTabButtons.length; i++) {
        subTabButtons[i].classList.remove('active');
    }
    const contentElement = document.getElementById(contentId);
    if (contentElement) {
        contentElement.classList.add('active');
    }
    const activeButton = mainTab.querySelector(`[onclick="openSubTab('${contentId}', '${tabParam}')"]`);
    if (activeButton) {
        activeButton.classList.add('active');
    }
    const url = new URL(window.location);
    url.searchParams.set('tab', tabParam);
    window.history.pushState({}, '', url);
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

function searchTickets(page = 1) {
    const searchTerm = document.getElementById('searchInput').value;
    const activeTab = document.querySelector('.sub-tab-content.active');
    if (!activeTab) return;
    const tab = activeTab.id.includes('regularTicketsContent') ? 'regular' :
                activeTab.id.includes('regularArchivedTicketsContent') ? 'regularArchived' :
                activeTab.id.includes('supportTicketsContent') ? 'support' : 'supportArchived';
    
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    if (searchTerm) {
        url.searchParams.set('search', searchTerm);
    } else {
        url.searchParams.delete('search');
    }
    if (tab === 'regular') {
        url.searchParams.set('regularActivePage', page);
    } else if (tab === 'regularArchived') {
        url.searchParams.set('regularArchivedPage', page);
    } else if (tab === 'support') {
        url.searchParams.set('supportActivePage', page);
    } else if (tab === 'supportArchived') {
        url.searchParams.set('supportArchivedPage', page);
    }
    window.location.href = url.toString();
}

const debouncedSearchTickets = debounce(searchTickets, 300);

function showViewModal(type, data) {
    const content = document.getElementById('viewTicketContent');
    const statusClass = `status-${data.status.toLowerCase().replace(' ', '-')}`;
    if (type === 'regular') {
        content.innerHTML = `
            <p><strong>Ticket ID:</strong> ${data.id}</p>
            <p><strong>Customer Name:</strong> ${data.aname}</p>
            <p><strong>Subject:</strong> ${data.subject}</p>
            <p><strong>Message:</strong> ${data.details}</p>
            <p><strong>Status:</strong> <span class="${statusClass}">${data.status}</span></p>
            <p><strong>Date:</strong> ${data.date}</p>
        `;
    } else {
        content.innerHTML = `
            <p><strong>Ticket No.:</strong> ${data.ref}</p>
            <p><strong>Customer Name:</strong> ${data.aname}</p>
            <p><strong>Subject:</strong> ${data.subject}</p>
            <p><strong>Message:</strong> ${data.details}</p>
            <p><strong>Status:</strong> <span class="${statusClass}">${data.status}</span></p>
        `;
    }
    document.getElementById('viewTicketModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function openModal(action, type, data) {
    const modal = document.getElementById('actionModal');
    const modalHeader = modal.querySelector('.modal-header');
    const modalBody = modal.querySelector('.modal-body');
    const modalFooter = modal.querySelector('.modal-footer');

    modalHeader.innerHTML = '';
    modalBody.innerHTML = '';
    modalFooter.innerHTML = '';

    let actionText = action.charAt(0).toUpperCase() + action.slice(1);
    modalHeader.textContent = `${actionText} Ticket #${data.id}`;
    modalBody.innerHTML = `<p>Are you sure you want to ${action} this ticket?</p>`;
    modalFooter.innerHTML = `
        <button class="modal-btn cancel" onclick="closeModal('actionModal')">Cancel</button>
        <button class="modal-btn confirm" onclick="submitAction('${action}', '${type}', ${data.id})">Confirm</button>
    `;

    modal.style.display = 'block';
    document.body.classList.add('modal-open');
}

function openCloseModal(id, aname, type) {
    document.getElementById('closeTicketIdDisplay').textContent = id;
    document.getElementById('closeTicketName').textContent = aname;
    document.getElementById('technicianFirstName').value = '';
    document.getElementById('closeError').style.display = 'none';
    document.getElementById('closeModal').dataset.ticketType = type;
    document.getElementById('closeModal').style.display = 'block';
    document.body.classList.add('modal-open');
}

function submitCloseAction() {
    const id = document.getElementById('closeTicketIdDisplay').textContent;
    const technicianFirstName = document.getElementById('technicianFirstName').value.trim();
    const type = document.getElementById('closeModal').dataset.ticketType;
    const errorElement = document.getElementById('closeError');
    const confirmBtn = document.getElementById('confirmCloseBtn');

    if (!technicianFirstName) {
        errorElement.textContent = 'Please enter your first name.';
        errorElement.style.display = 'block';
        return;
    }

    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Closing...';

    const formData = new FormData();
    formData.append('action', 'close');
    formData.append('id', id);
    formData.append('type', type);
    formData.append('technicianFirstName', technicianFirstName);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal('closeModal');
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 300);
        } else {
            errorElement.textContent = data.message;
            errorElement.style.display = 'block';
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirm';
        }
    })
    .catch(error => {
        errorElement.textContent = 'An error occurred.';
        errorElement.style.display = 'block';
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Confirm';
    });
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
}

function submitAction(action, type, id) {
    document.getElementById('formAction').value = action;
    document.getElementById('formId').value = id;
    document.getElementById('formType').value = type;
    document.getElementById('actionForm').submit();
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || 'regular';
    const mainTab = tab === 'regular' || tab === 'regularArchived' ? 'regularTickets' : 'supportTickets';
    openMainTab(mainTab, tab);
    const subTabContentId = tab === 'regular' ? 'regularTicketsContent' :
                           tab === 'regularArchived' ? 'regularArchivedTicketsContent' :
                           tab === 'support' ? 'supportTicketsContent' : 'supportArchivedTicketsContent';
    openSubTab(subTabContentId, tab);
});
</script>
</body>
</html>



