<?php
require_once 'db.php';

$current_page = 'view_dentists';

if (!isset($_SESSION['dentist_id'])) {
    header("Location: login.php");
    exit();
}

// 1. Fetch Master Services List (required for Sidebar checkboxes)
$services_list = $pdo->query("SELECT service_name FROM available_services")->fetchAll(PDO::FETCH_COLUMN);

// 2. Handle Sidebar checkboxes + Main Search + Role Filter
$sidebar_services = $_GET['services'] ?? [];
$search = $_GET['search_dentist'] ?? '';
$filter_role = $_GET['filter_role'] ?? '';

$query_parts = ["1=1"];
$params = [];

if (!empty($search)) {
    $query_parts[] = "d.dentist_name LIKE ?";
    $params[] = "%$search%";
}
if (!empty($filter_role)) {
    $query_parts[] = "d.role = ?";
    $params[] = $filter_role;
}

// 3. Fix the HAVING clause logic
$having_clause = "";
if (!empty($sidebar_services)) {
    $having_parts = [];
    foreach ($sidebar_services as $s) {
        $having_parts[] = "GROUP_CONCAT(s.service_name) LIKE ?";
        $params[] = "%$s%";
    }
    // Joining multiple service filters with OR (or AND depending on preference)
    $having_clause = "HAVING " . implode(' OR ', $having_parts);
}

// 4. SQL Query - Added dentist_id to GROUP BY to avoid strict mode errors
$sql = "SELECT d.dentist_id, d.dentist_name, d.role,
        GROUP_CONCAT(s.service_name SEPARATOR ', ') as services
        FROM dentists d
        LEFT JOIN specializations s ON d.dentist_id = s.dentist_id
        WHERE " . implode(' AND ', $query_parts) . "
        GROUP BY d.dentist_id, d.dentist_name, d.role
        $having_clause
        ORDER BY (d.dentist_id = ?) DESC, d.dentist_name ASC";

$params[] = $_SESSION['dentist_id'];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Our Dentists</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="display: flex;">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="registration-card" style="max-width: 1000px;">
        <h2>Clinic Dentists & Specializations</h2>
        
        <form method="GET" action="view_dentists.php" class="filter-row-container" style="display: flex; align-items: flex-end; gap: 15px; margin-bottom: 25px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
            <div class="filter-input-group" style="flex: 2; display: flex; flex-direction: column; gap: 5px;">
                <label style="font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">Search Dentist</label>
                <input type="text" name="search_dentist" placeholder="Enter name..." value="<?= htmlspecialchars($search) ?>" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <div class="filter-input-group" style="flex: 1; display: flex; flex-direction: column; gap: 5px;">
                <label style="font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">Role</label>
                <select name="filter_role" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    <option value="">All Roles</option>
                    <option value="dentist" <?= $filter_role == 'dentist' ? 'selected' : '' ?>>Dentist</option>
                    <option value="superintendent" <?= $filter_role == 'superintendent' ? 'selected' : '' ?>>Superintendent</option>
                </select>
            </div>
<div class="filter-actions-group" style="display: flex; gap: 10px; align-items: center;">
   <div class="filter-actions-group">
    <button type="submit" class="btn-primary">APPLY</button>
    
    <a href="view_dentists.php" class="btn-reset">CLEAR</a>
</div>
</div>
            
            <?php foreach($sidebar_services as $s): ?>
                <input type="hidden" name="services[]" value="<?= htmlspecialchars($s) ?>">
            <?php endforeach; ?>
        </form>

        <table class="dentist-table">
            <thead>
                <tr>
                    <th>Dentist Name</th>
                    <th>Associated Services</th>
                    <th style="text-align: center;">View Schedule</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $stmt->fetch()): ?>
                <tr style="<?= ($row['dentist_id'] == $_SESSION['dentist_id']) ? 'background-color: #f0fff4;' : '' ?>">
                    <td>
                        <strong>Dr. <?= htmlspecialchars($row['dentist_name']) ?></strong>
                        <?php if($row['dentist_id'] == $_SESSION['dentist_id']): ?>
                            <span style="background: #2ecc71; color: white; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; margin-left: 8px;">YOU</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($row['services']): ?>
                            <?php foreach(explode(', ', $row['services']) as $service): ?>
                                <span class="service-tag" style="background: #f4ebff; color: #8e44ad; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; margin-right: 5px;"><?= htmlspecialchars($service) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <small style="color: #999;">No specializations assigned</small>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <a href="dentist.php?dentist_id=<?= $row['dentist_id'] ?>" class="calendar-link" style="text-decoration: none; font-size: 1.2rem;">📅</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>