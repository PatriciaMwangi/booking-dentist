<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'logger.php'; // Include the logger functions

$current_page = 'manage_logs';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only superintendents can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superintendent') {
    header("Location: login.php");
    exit();
}

$page_title = "Activity Logs";

// Handle log clearing
if (isset($_POST['clear_logs']) && $_POST['clear_logs'] === '1') {
    try {
        $pdo->exec("TRUNCATE TABLE activity_logs");
        $message = "All logs have been cleared successfully.";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error clearing logs: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Timestamp', 'Dentist', 'Action', 'Description', 'IP Address']);
    
    $sql = "SELECT l.*, d.dentist_name 
            FROM activity_logs l 
            LEFT JOIN dentists d ON l.dentist_id = d.dentist_id 
            ORDER BY l.created_at DESC";
    $stmt = $pdo->query($sql);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['dentist_name'] ?? 'System',
            $row['action'],
            $row['description'],
            $row['ip_address']
        ]);
    }
    
    fclose($output);
    exit();
}

// Filter parameters
$filter_dentist = isset($_GET['dentist_id']) ? (int)$_GET['dentist_id'] : '';
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($filter_dentist)) {
    $where_conditions[] = "l.dentist_id = :dentist_id";
    $params[':dentist_id'] = $filter_dentist;
}

if (!empty($filter_action)) {
    $where_conditions[] = "l.action = :action";
    $params[':action'] = $filter_action;
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(l.created_at) >= :date_from";
    $params[':date_from'] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(l.created_at) <= :date_to";
    $params[':date_to'] = $filter_date_to;
}

if (!empty($filter_search)) {
    $where_conditions[] = "(l.description LIKE :search OR d.dentist_name LIKE :search OR l.ip_address LIKE :search)";
    $params[':search'] = "%$filter_search%";
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Get total count
$count_sql = "SELECT COUNT(*) FROM activity_logs l LEFT JOIN dentists d ON l.dentist_id = d.dentist_id $where_sql";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_logs = $stmt_count->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Get logs with pagination
$logs_sql = "SELECT l.*, d.dentist_name 
             FROM activity_logs l 
             LEFT JOIN dentists d ON l.dentist_id = d.dentist_id 
             $where_sql 
             ORDER BY l.created_at DESC 
             LIMIT $limit OFFSET $offset";

$stmt_logs = $pdo->prepare($logs_sql);
$stmt_logs->execute($params);
$logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

// Get unique actions for filter dropdown
$actions_sql = "SELECT DISTINCT action FROM activity_logs ORDER BY action";
$actions = $pdo->query($actions_sql)->fetchAll(PDO::FETCH_COLUMN);

// Get all dentists for filter dropdown
$dentists_sql = "SELECT dentist_id, dentist_name FROM dentists ORDER BY dentist_name";
$dentists = $pdo->query($dentists_sql)->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_logs,
                COUNT(DISTINCT dentist_id) as unique_dentists,
                COUNT(DISTINCT DATE(created_at)) as unique_days,
                MIN(created_at) as first_log,
                MAX(created_at) as last_log
              FROM activity_logs";
$stats = $pdo->query($stats_sql)->fetch(PDO::FETCH_ASSOC);

// Get action distribution
$action_stats_sql = "SELECT action, COUNT(*) as count FROM activity_logs GROUP BY action ORDER BY count DESC";
$action_stats = $pdo->query($action_stats_sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title><?= $page_title ?></title>
   
</head>
<body>
                <?php include 'sidebar.php'; ?>
<div class="main-content">
        <div class="sidebar-wrapper">
        </div>
        
        <div class="content-wrapper">
            <div class="container">
                <div class="page-header">
                    <div>
                        <h1>📊 Activity Logs</h1>
                        <p style="color: #7f8c8d; margin-top: 5px;">Track all system activities and user actions</p>
                    </div>
                    <div class="action-buttons">
                        <a href="?export=csv<?= !empty($_GET) ? '&' . http_build_query($_GET) : '' ?>" class="btn btn-success">
                            📥 Export CSV
                        </a>
                        <button type="button" onclick="confirmClearLogs()" class="btn btn-danger">
                            🗑️ Clear All Logs
                        </button>
                    </div>
                </div>
                
                <?php if (isset($message)): ?>
                    <div class="message <?= $message_type ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?= number_format($stats['total_logs']) ?></div>
                        <div class="label">Total Log Entries</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?= number_format($stats['unique_dentists']) ?></div>
                        <div class="label">Unique Dentists</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?= number_format($stats['unique_days']) ?></div>
                        <div class="label">Days of Activity</div>
                    </div>
                    <?php if ($stats['first_log']): ?>
                    <div class="stat-card">
                        <div class="number" style="font-size: 16px;"><?= date('M j, Y', strtotime($stats['first_log'])) ?></div>
                        <div class="label">First Log</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($stats['last_log']): ?>
                    <div class="stat-card">
                        <div class="number" style="font-size: 16px;"><?= date('M j, Y', strtotime($stats['last_log'])) ?></div>
                        <div class="label">Last Log</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Action Distribution -->
                <?php if (!empty($action_stats)): ?>
                <div style="background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h4 style="margin-top: 0; color: #2c3e50;">Action Distribution</h4>
                    <div class="action-distribution">
                        <?php foreach ($action_stats as $action_stat): ?>
                            <div class="action-item">
                                <span><?= htmlspecialchars($action_stat['action']) ?></span>
                                <span class="action-count"><?= $action_stat['count'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="filters-container">
                    <form method="GET" action="" class="filters-form">
                        <div class="filter-group">
                            <label>Dentist</label>
                            <select name="dentist_id">
                                <option value="">All Dentists</option>
                                <?php foreach ($dentists as $dentist): ?>
                                    <option value="<?= $dentist['dentist_id'] ?>" <?= $filter_dentist == $dentist['dentist_id'] ? 'selected' : '' ?>>
                                        Dr. <?= htmlspecialchars($dentist['dentist_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Action Type</label>
                            <select name="action">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?= htmlspecialchars($action) ?>" <?= $filter_action == $action ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($action) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Date From</label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>Date To</label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>Search (Description/IP/Dentist)</label>
                            <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($filter_search) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary" style="height: 40px;">🔍 Apply Filters</button>
                        </div>
                        
                        <?php if (!empty($filter_dentist) || !empty($filter_action) || !empty($filter_date_from) || !empty($filter_date_to) || !empty($filter_search)): ?>
                        <div class="filter-group">
                            <a href="?" class="btn btn-secondary" style="height: 40px; display: flex; align-items: center; justify-content: center;">
                                ✕ Clear Filters
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Logs Table -->
                <?php if (empty($logs)): ?>
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <h3>No Activity Logs Found</h3>
                        <p><?= !empty($where_conditions) ? 'Try adjusting your filters.' : 'System activities will appear here.' ?></p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="logs-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Timestamp</th>
                                    <th>Dentist</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th>User Agent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>#<?= $log['id'] ?></td>
                                        <td>
                                            <div style="font-weight: 500;">
                                                <?= date('M j, Y', strtotime($log['created_at'])) ?>
                                            </div>
                                            <small style="color: #7f8c8d;">
                                                <?= date('g:i a', strtotime($log['created_at'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($log['dentist_name']): ?>
                                                <div style="font-weight: 500;">Dr. <?= htmlspecialchars($log['dentist_name']) ?></div>
                                                <small style="color: #7f8c8d;">ID: #<?= $log['dentist_id'] ?></small>
                                            <?php else: ?>
                                                <span style="color: #95a5a6; font-style: italic;">System</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $action_class = 'action-' . strtolower($log['action']);
                                            ?>
                                            <span class="action-badge <?= $action_class ?>">
                                                <?= htmlspecialchars($log['action']) ?>
                                            </span>
                                        </td>
                                        <td class="description-cell">
                                            <?= htmlspecialchars($log['description']) ?>
                                        </td>
                                        <td>
                                            <code style="font-size: 12px;"><?= htmlspecialchars($log['ip_address']) ?></code>
                                        </td>
                                        <td class="user-agent">
                                            <?= htmlspecialchars(substr($log['user_agent'], 0, 100)) ?>
                                            <?= strlen($log['user_agent']) > 100 ? '...' : '' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    ← Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                                       <?= $i == $page ? 'class="current"' : '' ?>>
                                        <?= $i ?>
                                    </a>
                                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <span>...</span>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    Next →
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    function confirmClearLogs() {
        if (confirm('⚠️ WARNING: This will permanently delete ALL activity logs. This action cannot be undone.\n\nAre you sure you want to clear all logs?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'clear_logs';
            input.value = '1';
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>