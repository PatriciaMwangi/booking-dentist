<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['dentist_id'])) {
    header("Location: login.php");
    exit();
}

// Get patient_id from URL
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if (!$patient_id) {
    die("Patient ID is required.");
}

$dentist_id = $_SESSION['dentist_id'];
$current_page = 'patient_history';
$page_title = "Patient History";

// Get patient information
try {
    $stmt_patient = $pdo->prepare("SELECT patient_name, email, phone FROM patients WHERE patient_id = ?");
    $stmt_patient->execute([$patient_id]);
    $patient_info = $stmt_patient->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient_info) {
        die("Patient not found.");
    }
} catch (PDOException $e) {
    die("Error fetching patient information: " . $e->getMessage());
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Get total count of appointments for this patient
try {
    if ($_SESSION['role'] === 'superintendent') {
        // Superintendent can see all appointments for this patient
        $count_sql = "SELECT COUNT(*) FROM appointments WHERE patient_id = ?";
        $stmt_count = $pdo->prepare($count_sql);
        $stmt_count->execute([$patient_id]);
    } else {
        // Dentist can only see their own appointments with this patient
        $count_sql = "SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND dentist_id = ?";
        $stmt_count = $pdo->prepare($count_sql);
        $stmt_count->execute([$patient_id, $dentist_id]);
    }
    $total_appointments = $stmt_count->fetchColumn();
    $total_pages = ceil($total_appointments / $limit);
} catch (PDOException $e) {
    die("Error counting appointments: " . $e->getMessage());
}

// Get appointment history with dentist names
try {
    if ($_SESSION['role'] === 'superintendent') {
        // Superintendent gets all appointments with dentist names
        $history_sql = "
            SELECT 
                a.id,
                a.service,
                a.start_time,
                a.end_time,
                a.status,
                a.notes,
                d.dentist_name,
                d.dentist_id
            FROM appointments a
            LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
            WHERE a.patient_id = :patient_id
            ORDER BY a.start_time DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt_history = $pdo->prepare($history_sql);
        $stmt_history->bindValue(':patient_id', $patient_id, PDO::PARAM_INT);
        $stmt_history->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt_history->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt_history->execute();
    } else {
        // Dentist gets only their appointments
        $history_sql = "
            SELECT 
                a.id,
                a.service,
                a.start_time,
                a.end_time,
                a.status,
                a.notes,
                d.dentist_name,
                d.dentist_id
            FROM appointments a
            LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
            WHERE a.patient_id = :patient_id 
            AND a.dentist_id = :dentist_id
            ORDER BY a.start_time DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt_history = $pdo->prepare($history_sql);
        $stmt_history->bindValue(':patient_id', $patient_id, PDO::PARAM_INT);
        $stmt_history->bindValue(':dentist_id', $dentist_id, PDO::PARAM_INT);
        $stmt_history->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt_history->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt_history->execute();
    }
    
    $appointments = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching appointment history: " . $e->getMessage());
}

// Calculate statistics
try {
    if ($_SESSION['role'] === 'superintendent') {
        $stats_sql = "
            SELECT 
                COUNT(*) as total_visits,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_visits,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_visits,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_visits,
                MIN(start_time) as first_visit,
                MAX(start_time) as last_visit
            FROM appointments 
            WHERE patient_id = ?
        ";
        $stmt_stats = $pdo->prepare($stats_sql);
        $stmt_stats->execute([$patient_id]);
    } else {
        $stats_sql = "
            SELECT 
                COUNT(*) as total_visits,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_visits,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_visits,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_visits,
                MIN(start_time) as first_visit,
                MAX(start_time) as last_visit
            FROM appointments 
            WHERE patient_id = ? AND dentist_id = ?
        ";
        $stmt_stats = $pdo->prepare($stats_sql);
        $stmt_stats->execute([$patient_id, $dentist_id]);
    }
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="style.css">
    <title><?= $page_title ?> - <?= htmlspecialchars($patient_info['patient_name']) ?></title>

</head>
<body>
<div class="main-content">
        <div class="sidebar-wrapper">
            <?php include 'sidebar.php'; ?>
        </div>
        
        <div class="content-wrapper">
            <div class="container">
                <!-- Back Button -->
                <a href="patients" class="back-button">
                    ← Back to Patients List
                </a>
                
                <div class="page-header">
                    <h1>📋 Medical History</h1>
                    <p class="subtitle">Complete appointment history for <?= htmlspecialchars($patient_info['patient_name']) ?></p>
                </div>
                
                <!-- Patient Information Card -->
                <div class="patient-card">
                    <div class="patient-info">
                        <h3><?= htmlspecialchars($patient_info['patient_name']) ?></h3>
                        <p><strong>Email:</strong> <?= htmlspecialchars($patient_info['email']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($patient_info['phone']) ?></p>
                    </div>
       
                </div>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?= $stats['total_visits'] ?? 0 ?></div>
                        <div class="label">Total Visits</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?= $stats['completed_visits'] ?? 0 ?></div>
                        <div class="label">Completed</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?= $stats['pending_visits'] ?? 0 ?></div>
                        <div class="label">Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?= $stats['cancelled_visits'] ?? 0 ?></div>
                        <div class="label">Cancelled</div>
                    </div>
                    <?php if ($stats['first_visit'] ?? ''): ?>
                    <div class="stat-card">
                        <div class="number" style="font-size: 16px;"><?= date('M j, Y', strtotime($stats['first_visit'])) ?></div>
                        <div class="label">First Visit</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($stats['last_visit'] ?? ''): ?>
                    <div class="stat-card">
                        <div class="number" style="font-size: 16px;"><?= date('M j, Y', strtotime($stats['last_visit'])) ?></div>
                        <div class="label">Last Visit</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Appointment History Table -->
                <h3 style="color: #2c3e50; margin-bottom: 15px;">Appointment History</h3>
                
                <?php if (empty($appointments)): ?>
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <h3>No Appointment History</h3>
                        <p>This patient hasn't had any appointments yet.</p>
                    </div>
                <?php else: ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Service</th>
                                <th>Dentist</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 500;">
                                            <?= date('M j, Y', strtotime($appointment['start_time'])) ?>
                                        </div>
                                        <small style="color: #7f8c8d;">
                                            <?= date('g:i a', strtotime($appointment['start_time'])) ?> - 
                                            <?= date('g:i a', strtotime($appointment['end_time'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="service-name"><?= htmlspecialchars($appointment['service']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($appointment['dentist_name']): ?>
                                            <span class="dentist-name">Dr. <?= htmlspecialchars($appointment['dentist_name']) ?></span>
                                            <?php if ($_SESSION['role'] === 'superintendent'): ?>
                                                <br><small style="color: #7f8c8d;">ID: #<?= $appointment['dentist_id'] ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #95a5a6; font-style: italic;">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'status-' . strtolower($appointment['status']);
                                        ?>
                                        <span class="status-badge <?= $status_class ?>">
                                            <?= $appointment['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $start = new DateTime($appointment['start_time']);
                                        $end = new DateTime($appointment['end_time']);
                                        $interval = $start->diff($end);
                                        $hours = $interval->h;
                                        $minutes = $interval->i;
                                        
                                        if ($hours > 0) {
                                            echo $hours . ' hr' . ($hours > 1 ? 's' : '');
                                            if ($minutes > 0) echo ' ' . $minutes . ' min';
                                        } else {
                                            echo $minutes . ' min';
                                        }
                                        ?>
                                    </td>
                                    <td class="notes-cell">
                                        <?= !empty($appointment['notes']) ? htmlspecialchars($appointment['notes']) : '<span style="color: #95a5a6; font-style: italic;">No notes</span>' ?>
                                    </td>
                                 
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?patient_id=<?= $patient_id ?>&page=<?= $page - 1 ?>">
                                    ← Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="?patient_id=<?= $patient_id ?>&page=<?= $i ?>"
                                       <?= $i == $page ? 'class="current"' : '' ?>>
                                        <?= $i ?>
                                    </a>
                                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <span>...</span>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?patient_id=<?= $patient_id ?>&page=<?= $page + 1 ?>">
                                    Next →
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>