<?php
require_once 'config.php';
session_start();
$current_page = 'schedule';

if (!isset($_SESSION['dentist_id'])) {
    header("Location: login.php");
    exit();
}

// Handle Status Updates
if (isset($_POST['update_status'])) {
    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $_POST['appointment_id']]);
}

// --- FILTER LOGIC ---
// --- FILTER LOGIC ---
$filter_dentists = $_GET['dentists'] ?? [];
$filter_services = $_GET['services'] ?? [];
$start_date = $_GET['start_date'] ?? ''; // New
$end_date = $_GET['end_date'] ?? '';     // New
$search_query = $_GET['search'] ?? ''; // New search variable

$query_parts = ["1=1"]; 
$params = [];

if (!empty($search_query)) {
    $query_parts[] = "(p.patient_name LIKE ? OR p.phone LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}
//Date Range Filter
if (!empty($start_date) && !empty($end_date)) {
    $query_parts[] = "a.appointment_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
} elseif (!empty($start_date)) {
    $query_parts[] = "a.appointment_date = ?";
    $params[] = $start_date;
}

if (!empty($filter_dentists)) {
    $placeholders = str_repeat('?,', count($filter_dentists) - 1) . '?';
    $query_parts[] = "d.dentist_id IN ($placeholders)";
    $params = array_merge($params, $filter_dentists);
}

if (!empty($filter_services)) {
    $placeholders = str_repeat('?,', count($filter_services) - 1) . '?';
    $query_parts[] = "a.service IN ($placeholders)";
    $params = array_merge($params, $filter_services);
}

$sql = "SELECT a.id, a.appointment_date, a.appointment_time, p.patient_name, p.phone, a.service, d.dentist_name, d.dentist_id, a.status 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN dentists d ON a.dentist_id = d.dentist_id
        WHERE " . implode(' AND ', $query_parts) . "
        ORDER BY a.appointment_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Fetch all dentists for the filter list
$dentists_list = $pdo->query("SELECT dentist_id, dentist_name FROM dentists")->fetchAll();
$services_list = ["General Checkup", "Teeth Cleaning", "Tooth Extraction", "Dental Fillings", "Emergency Care"];
?>
<head>
        <link rel="stylesheet" href="style.css">
</head>
    
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <h2>Clinic Appointment Schedule</h2>
    <div class="filter-section card">
    <form method="GET" action="schedule.php" class="date-filter-form">
        <div class="filter-group" style="flex: 1.5;">
            <label>Search Patient:</label>
            <input type="text" name="search" placeholder="Name or Phone number..." 
                   value="<?= htmlspecialchars($search_query) ?>" 
                   style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
        </div></br>
        <div class="filter-group">
            <label>From:</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="filter-group">
            <label>To:</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div class="filter-actions">
<button type="submit" class="btn-primary" style="padding: 8px 20px; width: auto; font-size: 0.9rem;">
    APPLY DATE FILTER
</button>
            <a href="schedule.php" class="btn-reset">Clear</a>
        </div>
    </form>
</div>

    <table>
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
    <?php while($row = $stmt->fetch()): ?>
    <tr>
        <td><strong><?= $row['appointment_date'] ?></strong><br><?= $row['appointment_time'] ?></td>
        <td><?= $row['patient_name'] ?><br><small style="color:#888"><?= $row['phone'] ?></small></td>
        <td><span class="status-badge" style="background: #eee;"><?= $row['service'] ?></span></td>
        <td>Dr. <?= explode('Dr. ', $row['dentist_name'])[1] ?? $row['dentist_name'] ?></td>
        <td><strong><?= $row['status'] ?></strong></td>
        <td>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                <select name="status" style="padding: 5px; border-radius: 4px; border: 1px solid #ddd;">
                    <option value="Pending" <?= $row['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Completed" <?= $row['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="Cancelled" <?= $row['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <button type="submit" name="update_status" style="padding: 6px 10px; background: #2ecc71; color: white; border: none; border-radius: 4px; cursor: pointer;">Update</button>
            </form>
        </td>
      
    </tr>
    <?php endwhile; ?>
</tbody>
    </table>
</div>