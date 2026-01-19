<?php
require_once 'db.php';

$current_page = 'view_dentists';
if (!defined('APP_RUNNING')) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1>Direct access to this script is strictly prohibited.');
}

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
// --- 3. STRICT "AND" FILTERING ---
// This ensures only dentists providing ALL selected services are shown.
if (!empty($sidebar_services)) {
    $placeholders = str_repeat('?,', count($sidebar_services) - 1) . '?';
    $service_count = count($sidebar_services);

    $query_parts[] = "d.dentist_id IN (
        SELECT dentist_id 
        FROM specializations 
        WHERE service_name IN ($placeholders)
        GROUP BY dentist_id 
        HAVING COUNT(DISTINCT service_name) = ?
    )";
    
    // Add each selected service to params
    foreach ($sidebar_services as $s) { 
        $params[] = $s; 
    }
    // Add the count for the HAVING check
    $params[] = $service_count;
}

// --- 4. FINAL SQL QUERY ---
// We use a subquery for services so we don't need a GROUP BY in the main query.
$sql = "SELECT d.dentist_id, d.dentist_name, d.role,
        (SELECT GROUP_CONCAT(service_name SEPARATOR ', ') 
         FROM specializations 
         WHERE dentist_id = d.dentist_id) as services
        FROM dentists d
        WHERE " . implode(' AND ', $query_parts) . "
        ORDER BY (d.dentist_id = ?) DESC, d.dentist_name ASC";

// Add the session ID for the custom "ORDER BY" (to put YOU first)
$params[] = $_SESSION['dentist_id'];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
// Fetch into an array so we can check if it's empty in the HTML
$dentist_results = $stmt->fetchAll();
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
        
        <form method="GET" action="<?= BASE_URL ?>/dentists" class="filter-row-container" style="display: flex; align-items: flex-end; gap: 15px; margin-bottom: 25px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
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
    
    <a href="<?= BASE_URL ?>/view_dentists" class="btn-reset">CLEAR</a>
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
    <?php if (empty($dentist_results)): ?>
        <tr>
            <td colspan="3" style="text-align: center; padding: 40px; background: #fff;">
                <div style="font-size: 3rem; margin-bottom: 10px;">🔍</div>
                <h3 style="color: #666;">🚫 No Dentists Found</h3>
                <p>No one provides every single service you selected: <br>
                <strong style="color: #3498db;"><?= htmlspecialchars(implode(', ', $sidebar_services)) ?></strong></p>
                <a href="<?= BASE_URL ?>/view_dentists" style="color: #3498db; text-decoration: underline;">Clear all filters</a>
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($dentist_results as $row): ?>
            <?php $is_me = ($row['dentist_id'] == $_SESSION['dentist_id']); ?>
            <tr style="<?= $is_me ? 'background-color: #f0fff4;' : '' ?>">
<td>
    <strong>
        <?php 
            $name = htmlspecialchars($row['dentist_name']);
            // Check if name already starts with Dr. (case-insensitive)
            if (stripos($name, 'Dr.') === 0) {
                echo $name;
            } else {
                echo 'Dr. ' . $name;
            }
        ?>
    </strong>
    <?php if($is_me): ?>
        <span style="background: #2ecc71; color: white; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; margin-left: 8px;">YOU</span>
    <?php endif; ?>
</td>
                <td>
                    <?php if($row['services']): ?>
                        <?php foreach(explode(', ', $row['services']) as $service): ?>
                            <span class="service-tag" style="background: #f4ebff; color: #8e44ad; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; margin-right: 5px; display: inline-block; margin-bottom: 5px;">
                                <?= htmlspecialchars($service) ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <small style="color: #999; font-style: italic;">No specializations assigned</small>
                    <?php endif; ?>
                </td>
               <td style="text-align: center;">
    <a href="<?= BASE_URL ?>/calendar?dentist_id=<?= $row['dentist_id'] ?>" class="calendar-link" style="text-decoration: none; font-size: 1.2rem;">📅</a>
</td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>
        </table>
    </div>
</div>

</body>
</html>