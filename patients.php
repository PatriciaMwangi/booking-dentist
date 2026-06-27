<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
$current_page = 'patients';


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['dentist_id'])) {
    header("Location: login.php");
    exit();
}
$dentist_id = $_SESSION['dentist_id'];
$page_title = "My Patients";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where_conditions = ["a.dentist_id = :dentist_id", "a.status IN ('Pending', 'Confirmed', 'Completed')"];
$params = [':dentist_id' => $dentist_id];

if (!empty($search)) {
    $where_conditions[] = "(p.patient_name LIKE :search OR p.email LIKE :search OR p.phone LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_sql = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "
    SELECT COUNT(DISTINCT p.patient_id) 
    FROM patients p
    INNER JOIN appointments a ON p.patient_id = a.patient_id
    WHERE $where_sql
";

$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_patients = $stmt_count->fetchColumn();
$total_pages = ceil($total_patients / $limit);

// Get patients with their last appointment
// Build the SQL with LIMIT/OFFSET directly (after validating they're integers)
$patients_sql = "
    SELECT 
        p.patient_id,
        p.patient_name,
        p.email,
        p.phone,
        MAX(a.start_time) as last_booked,
        (
            SELECT status 
            FROM appointments a2 
            WHERE a2.patient_id = p.patient_id 
            AND a2.dentist_id = :dentist_id
            ORDER BY a2.start_time DESC 
            LIMIT 1
        ) as last_status,
        COUNT(a.id) as total_appointments
    FROM patients p
    INNER JOIN appointments a ON p.patient_id = a.patient_id
    WHERE $where_sql
    GROUP BY p.patient_id, p.patient_name, p.email, p.phone
    ORDER BY last_booked DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($patients_sql);
// Don't add limit/offset to params anymore
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Calculate stats
$stats_sql = "
    SELECT 
        COUNT(DISTINCT p.patient_id) as total_unique_patients,
        COUNT(a.id) as total_appointments,
        AVG(TIMESTAMPDIFF(DAY, a.start_time, NOW())) as avg_days_since_last_visit
    FROM patients p
    INNER JOIN appointments a ON p.patient_id = a.patient_id
    WHERE a.dentist_id = :dentist_id 
    AND a.status IN ('Pending', 'Confirmed', 'Completed')
";

$stmt_stats = $pdo->prepare($stats_sql);
$stmt_stats->execute([':dentist_id' => $dentist_id]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="display: flex;">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <h1>👥 My Patients</h1>
        <p class="subtitle">Manage your patient list and view their history</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="font-size: 32px; color: #3498db;">👥</div>
            <div style="font-size: 24px; font-weight: bold;"><?= number_format($stats['total_unique_patients'] ?? 0) ?></div>
            <div style="color: #7f8c8d;">Total Patients</div>
        </div>
        
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="font-size: 32px; color: #2ecc71;">📅</div>
            <div style="font-size: 24px; font-weight: bold;"><?= number_format($stats['total_appointments'] ?? 0) ?></div>
            <div style="color: #7f8c8d;">Total Appointments</div>
        </div>
        
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="font-size: 32px; color: #e74c3c;">⏱️</div>
            <div style="font-size: 24px; font-weight: bold;"><?= number_format($stats['avg_days_since_last_visit'] ?? 0) ?> days</div>
            <div style="color: #7f8c8d;">Avg. Days Since Last Visit</div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-container" style="margin-bottom: 20px;">
        <form method="GET" action="" style="display: flex; gap: 10px;">
            <input type="text" 
                   name="search" 
                   placeholder="Search by name, email, or phone..." 
                   value="<?= htmlspecialchars($search) ?>"
                   style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px;">
            <button type="submit" style="padding: 12px 24px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer;">
                🔍 Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="?page=1" style="padding: 12px 24px; background: #95a5a6; color: white; text-decoration: none; border-radius: 5px;">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Patients Table -->
    <div class="table-container" style="background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #eee; font-weight: 600;">Patient</th>
                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #eee; font-weight: 600;">Contact</th>
                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #eee; font-weight: 600;">Last Appointment</th>
                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #eee; font-weight: 600;">Status</th>
                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #eee; font-weight: 600;">Total Visits</th>
                    <th style="padding: 15px; text-align: left; border-bottom: 2px solid #eee; font-weight: 600;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="6" style="padding: 40px; text-align: center; color: #7f8c8d;">
                            <div style="font-size: 48px; margin-bottom: 20px;">👥</div>
                            <h3>No patients found</h3>
                            <p>You haven't had any patients yet.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patients as $patient): ?>
                        <tr style="border-bottom: 1px solid #eee; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#f9f9f9'">
                            <td style="padding: 15px;">
                                <div style="font-weight: 500; color: #2c3e50;"><?= htmlspecialchars($patient['patient_name']) ?></div>
                                <small style="color: #7f8c8d;">ID: #<?= $patient['patient_id'] ?></small>
                            </td>
                            <td style="padding: 15px;">
                                <div style="margin-bottom: 5px;">
                                    <a href="mailto:<?= htmlspecialchars($patient['email']) ?>" style="color: #3498db; text-decoration: none;">
                                        ✉️ <?= htmlspecialchars($patient['email']) ?>
                                    </a>
                                </div>
                                <div>
                                    📞 <?= htmlspecialchars($patient['phone']) ?>
                                </div>
                            </td>
                            <td style="padding: 15px;">
                                <?php if ($patient['last_booked']): ?>
                                    <div style="font-weight: 500;">
                                        <?= date('M j, Y', strtotime($patient['last_booked'])) ?>
                                    </div>
                                    <small style="color: #7f8c8d;">
                                        <?= date('g:i a', strtotime($patient['last_booked'])) ?>
                                    </small>
                                <?php else: ?>
                                    <span style="color: #95a5a6;">No appointments yet</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px;">
                                <?php
                                $status = $patient['last_status'] ?? 'N/A';
                                $status_colors = [
                                    'Pending' => '#f39c12',
                                    'Confirmed' => '#3498db',
                                    'Completed' => '#2ecc71',
                                    'Cancelled' => '#e74c3c',
                                    'N/A' => '#95a5a6'
                                ];
                                $color = $status_colors[$status] ?? '#95a5a6';
                                ?>
                                <span style="background: <?= $color ?>20; color: <?= $color ?>; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500;">
                                    <?= $status ?>
                                </span>
                            </td>
                            <td style="padding: 15px; font-weight: 500;">
                                <span style="background: #3498db20; color: #3498db; padding: 5px 10px; border-radius: 20px;">
                                    <?= $patient['total_appointments'] ?> visit<?= $patient['total_appointments'] != 1 ? 's' : '' ?>
                                </span>
                            </td>
                            <td style="padding: 15px;">
                                <div style="display: flex; gap: 5px;">
<a href="<?= BASE_URL ?>/patient_history?patient_id=<?= $patient['patient_id'] ?>" 
   style="padding: 8px 12px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
    View History
</a>
<a href="<?= BASE_URL ?>/?dentist_id=<?= $dentist_id ?>&patient_id=<?= $patient['patient_id'] ?>" 
   style="padding: 8px 12px; background: #2ecc71; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
    Book Again
</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination" style="margin-top: 30px; display: flex; justify-content: center; gap: 10px;">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                   style="padding: 10px 15px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;">
                    ← Previous
                </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                    <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                       style="padding: 10px 15px; <?= $i == $page ? 'background: #2c3e50; color: white;' : 'background: #ecf0f1; color: #2c3e50;' ?> text-decoration: none; border-radius: 5px;">
                        <?= $i ?>
                    </a>
                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                    <span style="padding: 10px 15px;">...</span>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                   style="padding: 10px 15px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;">
                    Next →
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
<!-- <?php include 'includes/footer.php'; ?> -->