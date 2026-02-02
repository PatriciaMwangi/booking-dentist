<?php
// echo "PHP error log location: " . ini_get('error_log') . "<br>";
// echo "Current working dir: " . getcwd() . "<br>";
// echo "Current file: " . __FILE__ . "<br>";
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'db.php';

$current_page = 'schedule';

if (!isset($_SESSION['dentist_id'])) {
    header("Location: login.php");
    exit();
}

// ============================================
// CRITICAL: REASSIGNMENT HANDLER MUST BE FIRST
// ============================================
if (isset($_POST['reassign_appointment'])) {

    // Check if superintendent
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superintendent') {
        $_SESSION['error'] = "❌ Access denied. Only superintendents can reassign appointments.";
        header("Location: schedule.php");
        exit();
    }
    
    $appointment_id = $_POST['appointment_id'] ?? null;
    $new_dentist_id = $_POST['new_dentist_id'] ?? null;
    $old_dentist_id = $_POST['old_dentist_id'] ?? null;
    $reassign_reason = trim($_POST['reassign_reason'] ?? '');
    
    
    try {
        // Validate
        if (!$appointment_id || !$new_dentist_id || !$old_dentist_id) {
            throw new Exception("Missing required fields");
        }
        
        if ($new_dentist_id == $old_dentist_id) {
            throw new Exception("Cannot reassign to the same dentist");
        }
        
        // Check if appointment exists
        $stmt = $pdo->prepare("SELECT id, dentist_id FROM appointments WHERE id = ?");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt->execute([$appointment_id]);
        $appt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appt) {
            throw new Exception("Appointment not found");
        }
        

        
        if ($appt['dentist_id'] != $old_dentist_id) {
            throw new Exception("Appointment has been modified. Please refresh.");
        }
        
        // Check if new dentist exists and is active
        $stmt = $pdo->prepare("SELECT dentist_name, status FROM dentists WHERE dentist_id = ?");
        $stmt->execute([$new_dentist_id]);
        $new_dentist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$new_dentist) {
            throw new Exception("Selected dentist not found");
        }
        
        if ($new_dentist['status'] !== 'Active') {
            throw new Exception("Selected dentist is not available");
        }
        
        // Get old dentist name for logging
        $stmt->execute([$old_dentist_id]);
        $old_dentist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Perform reassignment with tracking
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET dentist_id = ?, 
                reassigned_from = ?, 
                reassigned_at = NOW(),
                reassign_reason = ?
            WHERE id = ?
        ");

        $result = $stmt->execute([
            $new_dentist_id, 
            $old_dentist_id, 
            $reassign_reason, 
            $appointment_id
        ]);
        
        $rows_affected = $stmt->rowCount();

        
        if ($rows_affected > 0) {
            // Verify the update
            $verifyStmt = $pdo->prepare("SELECT dentist_id, reassigned_from FROM appointments WHERE id = ?");
            $verifyStmt->execute([$appointment_id]);
            $updatedAppt = $verifyStmt->fetch(PDO::FETCH_ASSOC);
          
            $msg = "✅ Appointment #$appointment_id reassigned from Dr. {$old_dentist['dentist_name']} to Dr. {$new_dentist['dentist_name']}";
            if ($reassign_reason) {
                $msg .= "<br><small>Reason: " . htmlspecialchars($reassign_reason) . "</small>";
            }
            $_SESSION['success'] = $msg;
        } else {
            throw new Exception("Failed to update appointment. No rows were changed.");
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }
    
    header("Location: " . BASE_URL . "/schedule?updated_id=" . urlencode($appointment_id));
    exit();
}
// Handle Status Updates
if (isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['status'];
    $new_start = $_POST['appointment_start']; 
    $new_end = $_POST['appointment_end'];   
    
    $session_dentist_id = $_SESSION['dentist_id'] ?? null;
    $is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent');

    if ($is_super) {
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, start_time = ?, end_time = ? WHERE id = ?");
        $stmt->execute([$new_status, $new_start, $new_end, $appointment_id]);
    } else if ($session_dentist_id) {
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, start_time = ?, end_time = ? WHERE id = ? AND dentist_id = ?");
        $stmt->execute([$new_status, $new_start, $new_end, $appointment_id, $session_dentist_id]);
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'appointment_id' => $appointment_id]);
        exit();
    }

    ob_end_clean();
    header("Location: " . BASE_URL . "/schedule?updated_id=" . urlencode($appointment_id));
    exit();
}

// REST OF CODE... (pagination, filters, display)
$items_per_page = 15;
$current_page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page_num = max(1, $current_page_num);
$offset = ($current_page_num - 1) * $items_per_page;

$filter_dentists = $_GET['dentists'] ?? [];
$show_all = isset($_GET['show_all']); 
$session_id = $_SESSION['dentist_id'];
$filter_services = $_GET['services'] ?? [];
$start_date = $_GET['start_date'] ?? ''; 
$end_date = $_GET['end_date'] ?? '';     
$search_query = $_GET['search'] ?? ''; 

$query_parts = ["1=1"]; 
$params = [];

$sort_order = "DESC"; 
if (isset($_GET['sort_order']) && $_GET['sort_order'] === 'ASC') {
    $sort_order = "ASC";
}

$role = $_SESSION['role'] ?? '';

if ($role === 'superintendent') {
    if (!empty($filter_dentists)) {
        $placeholders = str_repeat('?,', count($filter_dentists) - 1) . '?';
        $query_parts[] = "a.dentist_id IN ($placeholders)";
        foreach($filter_dentists as $id) $params[] = $id;
    }
} else {
    if (!$show_all && empty($filter_dentists)) {
        $query_parts[] = "a.dentist_id = ?";
        $params[] = $session_id;
    } elseif (!empty($filter_dentists)) {
        $placeholders = str_repeat('?,', count($filter_dentists) - 1) . '?';
        $query_parts[] = "a.dentist_id IN ($placeholders)";
        foreach($filter_dentists as $id) $params[] = $id;
    }
}

if (!empty($search_query)) {
    $query_parts[] = "(p.patient_name LIKE ? OR p.phone LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if (!empty($start_date) && !empty($end_date)) {
    $query_parts[] = "DATE(a.start_time) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
} elseif (!empty($start_date)) {
    $query_parts[] = "DATE(a.start_time) = ?";
    $params[] = $start_date;
}

if (!empty($filter_services)) {
    $placeholders = str_repeat('?,', count($filter_services) - 1) . '?';
    $query_parts[] = "a.service IN ($placeholders)";
    foreach($filter_services as $service) $params[] = $service;
}

if (!empty($_GET['status'])) {
    $query_parts[] = "a.status = ?";
    $params[] = $_GET['status'];
}

$count_sql = "SELECT COUNT(*) as total
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN dentists d ON a.dentist_id = d.dentist_id
        WHERE " . implode(' AND ', $query_parts);

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $items_per_page);

$sql = "SELECT 
            a.id, 
            a.start_time, 
            a.end_time, 
            a.service,
            p.patient_name, 
            p.phone, 
            d.dentist_name, 
            d.dentist_id, 
            a.status 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN dentists d ON a.dentist_id = d.dentist_id
        WHERE " . implode(' AND ', $query_parts) . "
        ORDER BY a.start_time $sort_order
        LIMIT $items_per_page OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $appointments = [];
}

$dentists_list = $pdo->query("SELECT dentist_id, dentist_name FROM dentists WHERE status = 'Active' ORDER BY dentist_name")->fetchAll();

function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return 'schedule.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Schedule</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            max-height: 100vh;
            box-sizing: border-box;
        }
        
        .main-content h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        /* Success/Error Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
        }
        
        .alert-success {
            background: #d5f4e6;
            color: #27ae60;
            border-color: #27ae60;
        }
        
        .alert-error {
            background: #fadbd8;
            color: #e74c3c;
            border-color: #e74c3c;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            padding: 20px 0;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #2c3e50;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .pagination .current-page {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            pointer-events: none;
            cursor: not-allowed;
        }
        
        .pagination-info {
            text-align: center;
            color: #7f8c8d;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        
        /* Modal styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 15px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        /* Reassign button */
        .btn-reassign {
            background: #9b59b6;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            margin-left: 5px;
            transition: background 0.3s;
        }
        
        .btn-reassign:hover {
            background: #8e44ad;
        }
        
        /* Filter section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .date-filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 150px;
        }
        
        .filter-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .btn-primary {
            padding: 10px 25px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-reset {
            padding: 10px 25px;
            background: #95a5a6;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.3s ease;
            display: inline-block;
        }
        
        .btn-reset:hover {
            background: #7f8c8d;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <h2>📋 Clinic Appointment Schedule</h2>
    
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['success'] ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?= $_SESSION['error'] ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <div class="filter-section card">
        <form method="GET" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="date-filter-form">
            <div class="filter-group" style="flex: 1.5;">
                <label>Search Patient:</label>
                <input type="text" name="search" placeholder="Name or Phone number..." 
                       value="<?= htmlspecialchars($search_query) ?>">
            </div>
            
            <div class="filter-group">
                <label>Sort By:</label>
                <select name="sort_order">
                    <option value="DESC" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] == 'DESC') ? 'selected' : '' ?>>Latest First</option>
                    <option value="ASC" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] == 'ASC') ? 'selected' : '' ?>>Earliest First</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>From:</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            
            <div class="filter-group">
                <label>To:</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            
            <div class="filter-group">
                <label>Status:</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?= (isset($_GET['status']) && $_GET['status'] == 'Pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="Completed" <?= (isset($_GET['status']) && $_GET['status'] == 'Completed') ? 'selected' : '' ?>>Completed</option>
                    <option value="Cancelled" <?= (isset($_GET['status']) && $_GET['status'] == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-primary">APPLY FILTERS</button>
                <a href="schedule.php" class="btn-reset">Clear</a>
            </div>
        </form>
    </div>

    <?php if (empty($appointments)): ?>
        <div style="background: white; padding: 40px; text-align: center; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h3 style="color: #95a5a6; margin-bottom: 10px;">📅 No appointments found</h3>
            <p style="color: #bdc3c7;">Try adjusting your filters or check if appointments exist in the database.</p>
        </div>
    <?php else: ?>
    <table class="appointments-table">
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Patient</th>
                <th>Service</th>
                <th>Dentist</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($appointments as $row): 
                $session_dentist_id = isset($_SESSION['dentist_id']) ? (int)$_SESSION['dentist_id'] : 0;
                $row_dentist_id = (int)$row['dentist_id'];
                $is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent');
                
                $is_updated = (isset($_GET['updated_id']) && $_GET['updated_id'] == $row['id']) ? 'updated-row' : '';

                $start_dt = new DateTime($row['start_time']);
                $end_dt   = new DateTime($row['end_time']);
                
                $start_input = $start_dt->format('Y-m-d\TH:i');
                $end_input   = $end_dt->format('Y-m-d\TH:i');
                
                $status_colors = ['Pending' => '#f39c12', 'Completed' => '#27ae60', 'Cancelled' => '#e74c3c'];
                $status_color = $status_colors[$row['status']] ?? '#95a5a6';
            ?>
            <tr class="<?= $is_updated ?>">
                <td class="datetime-cell">
                    <div class="date-display"><?= $start_dt->format('D, M j, Y') ?></div>
                    <div class="time-display">⏰ <?= $start_dt->format('h:i A') ?> - <?= $end_dt->format('h:i A') ?></div>
                </td>

                <td class="patient-cell">
                    <div class="patient-name"><?= htmlspecialchars($row['patient_name']) ?></div>
                    <div class="patient-phone">📞 <?= htmlspecialchars($row['phone']) ?></div>
                </td>
                
                <td><span class="service-badge"><?= htmlspecialchars($row['service']) ?></span></td>
                
                <td class="dentist-cell">
                    <div class="dentist-name">👩‍⚕️ <?= htmlspecialchars($row['dentist_name']) ?></div>
                </td>
                
                <td>
                    <span class="status-badge" style="background-color: <?= $status_color ?>20; color: <?= $status_color ?>; border-color: <?= $status_color ?>;">
                        <?= htmlspecialchars($row['status']) ?>
                    </span>
                </td>

                <td class="action-cell">
                    <?php if ($is_super || $row_dentist_id === $session_dentist_id): ?>
                        <button type="button" class="edit-trigger-btn" 
                                onclick="openEditModal('<?= $row['id'] ?>', '<?= $start_input ?>', '<?= $end_input ?>', '<?= $row['status'] ?>')">
                            ✏️ Edit
                        </button>
                        
                        <?php if ($is_super): ?>
                            <button type="button" class="btn-reassign" 
                                    onclick="openReassignModal('<?= $row['id'] ?>', '<?= $row['dentist_id'] ?>', '<?= addslashes($row['dentist_name']) ?>', '<?= addslashes($row['patient_name']) ?>', '<?= $row['service'] ?>')">
                                🔄 Reassign
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="view-only">👁️ View Only</div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($current_page_num > 1): ?>
            <a href="<?= buildPaginationUrl(1) ?>">« First</a>
            <a href="<?= buildPaginationUrl($current_page_num - 1) ?>">‹ Prev</a>
        <?php else: ?>
            <span class="disabled">« First</span>
            <span class="disabled">‹ Prev</span>
        <?php endif; ?>
        
        <?php
        $start_page = max(1, $current_page_num - 2);
        $end_page = min($total_pages, $current_page_num + 2);
        
        if ($start_page > 1) echo '<span>...</span>';
        
        for ($i = $start_page; $i <= $end_page; $i++):
            if ($i == $current_page_num):
        ?>
            <span class="current-page"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= buildPaginationUrl($i) ?>"><?= $i ?></a>
        <?php 
            endif;
        endfor;
        
        if ($end_page < $total_pages) echo '<span>...</span>';
        ?>
        
        <?php if ($current_page_num < $total_pages): ?>
            <a href="<?= buildPaginationUrl($current_page_num + 1) ?>">Next ›</a>
            <a href="<?= buildPaginationUrl($total_pages) ?>">Last »</a>
        <?php else: ?>
            <span class="disabled">Next ›</span>
            <span class="disabled">Last »</span>
        <?php endif; ?>
    </div>
    
    <div class="pagination-info">
        Showing <?= $offset + 1 ?> to <?= min($offset + $items_per_page, $total_records) ?> of <?= $total_records ?> appointments
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Edit Modal -->
    <div id="editAppointmentModal" class="modal-overlay">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <h3 style="margin: 0; color: #2c3e50;">✏️ Edit Appointment</h3>
                <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #95a5a6;">&times;</button>
            </div>
            
<form method="POST" id="modalEditForm" action="<?= BASE_URL ?>/schedule">
                <input type="hidden" name="appointment_id" id="modal_appt_id">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Start Date & Time</label>
                    <input type="datetime-local" name="appointment_start" id="modal_start" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">End Date & Time</label>
                    <input type="datetime-local" name="appointment_end" id="modal_end" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Status</label>
                    <select name="status" id="modal_status" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; background: white;">
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="closeEditModal()" style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px; background: #f8f9fa; cursor: pointer;">Cancel</button>
                    <input type="hidden" name="update_status" value="1">
                    <button type="submit" style="flex: 2; padding: 12px; border: none; border-radius: 8px; background: #2ecc71; color: white; font-weight: 600; cursor: pointer;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reassign Modal -->
<div id="reassignModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🔄 Reassign Appointment</h3>
            <button type="button" onclick="closeReassignModal()" class="modal-close">&times;</button>
        </div>
        
        <div class="modal-body">
            <div id="reassignInfo" class="appointment-info" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <!-- Dynamic info will go here -->
            </div>
            
<!-- In your reassign modal form -->
<form method="POST" action="<?= BASE_URL ?>/schedule" id="reassignForm">
    <input type="hidden" name="reassign_appointment" value="1">
            <input type="hidden" name="appointment_id" id="reassign_appt_id">
            <input type="hidden" name="old_dentist_id" id="old_dentist_id"> <!-- CHANGED: from reassign_old_dentist -->
    
    <div class="form-group">
        <label for="new_dentist_id">New Dentist *</label>
        <select name="new_dentist_id" id="new_dentist_id" required class="form-control">
            <option value="">-- Loading dentists --</option>
        </select>
        <small class="form-text">Only dentists who offer this service are shown</small>
    </div>
    
    <div class="form-group">
        <label for="reassign_reason">Reason for Reassignment (Optional)</label>
        <textarea name="reassign_reason" id="reassign_reason" class="form-control" rows="3" placeholder="e.g., Dentist unavailable, patient request..."></textarea>
    </div>
    
    <div class="modal-footer">
        <button type="button" onclick="closeReassignModal()" class="btn-secondary">Cancel</button>
        <button type="submit" name="reassign_appointment" value = "1" class="btn-primary">Reassign Appointment</button>
    </div>
</form>
        </div>
    </div>
</div>

    <script>
        function openEditModal(id, start, end, status) {
            document.getElementById('modal_appt_id').value = id;
            document.getElementById('modal_start').value = start;
            document.getElementById('modal_end').value = end;
            document.getElementById('modal_status').value = status;
            
            document.getElementById('editAppointmentModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editAppointmentModal').style.display = 'none';
        }

function openReassignModal(id, currentDentistId, currentDentistName, patientName, service) {
    console.log('=== DEBUG openReassignModal ===');
    
    // Set the hidden fields
    document.getElementById('reassign_appt_id').value = id;
    document.getElementById('old_dentist_id').value = currentDentistId;
    
    // Update info display
    const infoDiv = document.getElementById('reassignInfo');
    if (infoDiv) {
        infoDiv.innerHTML = `
            <div style="margin-bottom: 8px;"><strong>Patient:</strong> ${patientName}</div>
            <div style="margin-bottom: 8px;"><strong>Service:</strong> ${service}</div>
            <div><strong>Current Dentist:</strong> Dr. ${currentDentistName}</div>
        `;
    }
    
    // Get the select element
    const select = document.getElementById('new_dentist_id');
    if (!select) {
        console.error('new_dentist_id select element not found!');
        alert('Error: Form elements not loaded properly. Please refresh the page.');
        return;
    }
    
    // Reset and disable the select
    select.innerHTML = '<option value="">Loading dentists...</option>';
    select.disabled = true;
    
    // Enable submit button
    const submitBtn = document.querySelector('#reassignForm button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Reassign Appointment';
    }
    
    // Show the modal
    document.getElementById('reassignModal').style.display = 'flex';
    
    // Fetch dentists
    fetch(`<?= BASE_URL ?>/get-dentists-for-service?service=${encodeURIComponent(service)}&exclude=${currentDentistId}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
            return response.json();
        })
        .then(data => {
            console.log('Dentists fetch response:', data);
            select.innerHTML = '';
            
            if (data.success && data.dentists && data.dentists.length > 0) {
                // Add default option
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = '-- Select a new dentist --';
                select.appendChild(defaultOption);
                
                // Add available dentists
                data.dentists.forEach(dentist => {
                    const option = document.createElement('option');
                    option.value = dentist.id;
                    option.textContent = dentist.name;
                    select.appendChild(option);
                });
                
                select.disabled = false;
            } else {
                select.innerHTML = '<option value="">No dentists available for this service</option>';
                if (submitBtn) submitBtn.disabled = true;
            }
        })
        .catch(error => {
            console.error('Error loading dentists:', error);
            select.innerHTML = '<option value="">Error loading dentists. Please try again.</option>';
        });
}

        function closeReassignModal() {
            document.getElementById('reassignModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const editModal = document.getElementById('editAppointmentModal');
            const reassignModal = document.getElementById('reassignModal');
            
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == reassignModal) {
                closeReassignModal();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            
       const modalForm = document.getElementById('modalEditForm');
    
    if (modalForm) {
        const startInput = document.getElementById('modal_start');
        const endInput = document.getElementById('modal_end');

        modalForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default submission
            
            const start = new Date(startInput.value);
            const end = new Date(endInput.value);
            
            // Validate time
            if (start >= end) {
                alert('⚠️ Invalid Time: The end time must be after the start time.');
                endInput.style.borderColor = '#e74c3c';
                return false;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '⏳ Saving...';
            submitBtn.disabled = true;
            
            // Submit via fetch (AJAX)
            fetch('<?= BASE_URL ?>/schedule', {  // Add the action URL
                method: 'POST',
                body: new FormData(this),
                headers: {
        'X-Requested-With': 'XMLHttpRequest' // Mark as AJAX
                }
            })
            .then(response => response.text())
            .then(html => {
                console.log('Edit successful, closing modal and reloading...');
                
                // Close the modal
                closeEditModal();
                
                // Reload the page to see changes
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving changes: ' + error.message);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        [startInput, endInput].forEach(input => {
            input.addEventListener('change', () => {
                startInput.style.borderColor = '#ddd';
                endInput.style.borderColor = '#ddd';
            });
        });
    }
            
            // Handle reassign form submission
 const reassignForm = document.getElementById('reassignForm');
if (reassignForm) {
    reassignForm.addEventListener('submit', function(e) {
        e.preventDefault(); // STOP the normal form submission
        
        console.log('=== FORM SUBMISSION INTERCEPTED ===');
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '⏳ Reassigning...';
        submitBtn.disabled = true;
        
        // Close the modal first
        closeReassignModal();
        
        // Submit via fetch
        fetch(this.action, {
            method: this.method,
            body: formData,
            headers: {
                'Accept': 'text/html',
            }
        })
        .then(response => response.text())
        .then(html => {
            console.log('Reassignment successful!');
            
            // Show success message
            showNotification('✅ Appointment reassigned successfully!', 'success');
            
            // Reset button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            
            // Reload the appointments table after a delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showNotification('❌ Error reassigning appointment', 'error');
            
            // Reset button on error
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
}

// Add this notification function if you don't have one
function showNotification(message, type = 'success') {
    // Remove any existing notification
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#27ae60' : '#e74c3c'};
        color: white;
        border-radius: 8px;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
            
            // Remove the "updated_id" parameter from URL after highlighting
            if (window.location.search.includes('updated_id')) {
                setTimeout(() => {
                    const url = new URL(window.location);
                    url.searchParams.delete('updated_id');
                    window.history.replaceState({}, '', url);
                }, 3000);
            }
        });
    </script>
</div>
</body>
</html>