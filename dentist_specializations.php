<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only superintendents can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superintendent') {
    header("Location: login.php");
    exit();
}

// Get parameters
$dentist_id = isset($_GET['dentist_id']) ? (int)$_GET['dentist_id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : ''; // 'add' or 'remove'

if (!$dentist_id || !in_array($action, ['add', 'remove'])) {
    die("Invalid parameters.");
}

// Get dentist information
$stmt = $pdo->prepare("SELECT dentist_name FROM dentists WHERE dentist_id = ?");
$stmt->execute([$dentist_id]);
$dentist = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dentist) {
    die("Dentist not found.");
}

$page_title = ($action === 'add') ? "Add Specializations" : "Remove Specializations";
$current_page = 'dentist_specializations';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['services']) && is_array($_POST['services'])) {
        require_once 'logger.php';
        
        if ($action === 'add') {
            // Add new specializations
            $added_count = 0;
            foreach ($_POST['services'] as $service_name) {
                // Check if already exists
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM specializations WHERE dentist_id = ? AND service_name = ?");
                $check_stmt->execute([$dentist_id, $service_name]);
                
                if ($check_stmt->fetchColumn() == 0) {
                    // Insert new specialization
                    $insert_stmt = $pdo->prepare("INSERT INTO specializations (dentist_id, service_name) VALUES (?, ?)");
                    if ($insert_stmt->execute([$dentist_id, $service_name])) {
                        $added_count++;
                        // Log the addition
                        logService($_SESSION['dentist_id'], 'Added Specialization to Dentist', 
                                 "Service: {$service_name} for Dr. {$dentist['dentist_name']}");
                    }
                }
            }
            
            if ($added_count > 0) {
                $_SESSION['msg'] = "Successfully added {$added_count} specialization(s) to Dr. {$dentist['dentist_name']}.";
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['msg'] = "No new specializations were added (they may already exist).";
                $_SESSION['msg_type'] = 'info';
            }
            
        } elseif ($action === 'remove') {
            // Remove specializations
            $removed_count = 0;
            foreach ($_POST['services'] as $service_name) {
                $delete_stmt = $pdo->prepare("DELETE FROM specializations WHERE dentist_id = ? AND service_name = ?");
                if ($delete_stmt->execute([$dentist_id, $service_name])) {
                    if ($delete_stmt->rowCount() > 0) {
                        $removed_count++;
                        // Log the removal
                        logService($_SESSION['dentist_id'], 'Removed Specialization from Dentist', 
                                 "Service: {$service_name} from Dr. {$dentist['dentist_name']}");
                    }
                }
            }
            
            if ($removed_count > 0) {
                $_SESSION['msg'] = "Successfully removed {$removed_count} specialization(s) from Dr. {$dentist['dentist_name']}.";
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['msg'] = "No specializations were removed.";
                $_SESSION['msg_type'] = 'info';
            }
        }
        
        header("Location: " . BASE_URL . "/dentists");
        exit();
    }
}

// Get current dentist's specializations
$current_specializations_stmt = $pdo->prepare("SELECT service_name FROM specializations WHERE dentist_id = ? ORDER BY service_name");
$current_specializations_stmt->execute([$dentist_id]);
$current_specializations = $current_specializations_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all available services
$all_services_stmt = $pdo->query("SELECT service_name FROM available_services ORDER BY service_name");
$all_services = $all_services_stmt->fetchAll(PDO::FETCH_COLUMN);

// Filter services based on action
if ($action === 'add') {
    // Show services that the dentist doesn't already have
    $available_services = array_diff($all_services, $current_specializations);
} else {
    // Show only services that the dentist currently has
    $available_services = $current_specializations;
}
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
    <div class="app-container">
        <div class="sidebar-wrapper">
            <?php include 'sidebar.php'; ?>
        </div>
        
        <div class="content-wrapper">
            <div class="container">
                <!-- Back Button -->
                <a href="<?= BASE_URL ?>/dentists" class="btn btn-secondary" style="margin-bottom: 20px;">
                    ← Back to Dentists
                </a>
                
                <div class="page-header">
                    <h1>
                        <?= $action === 'add' ? '➕ Add Specializations' : '❌ Remove Specializations' ?>
                    </h1>
                    <p class="subtitle">
                        <?= $action === 'add' 
                            ? 'Select services to add to this dentist\'s specializations' 
                            : 'Select services to remove from this dentist\'s specializations' ?>
                    </p>
                </div>
                
                <!-- Dentist Information Card -->
                <div class="dentist-card">
                    <h3>
                        <span>Dr. <?= htmlspecialchars($dentist['dentist_name']) ?></span>
                        <span class="action-indicator <?= $action === 'add' ? 'add-action' : 'remove-action' ?>">
                            <?= $action === 'add' ? 'ADDING' : 'REMOVING' ?>
                        </span>
                    </h3>
                    <p style="color: #666; margin: 0;">
                        Dentist ID: #<?= $dentist_id ?>
                        <?php if (!empty($current_specializations)): ?>
                            <br>Current specializations: <?= count($current_specializations) ?>
                        <?php endif; ?>
                    </p>
                </div>
                
                <!-- Services Form -->
                <form method="POST" action="" class="services-form">
                    <?php if (empty($available_services)): ?>
                        <div class="empty-state">
                            <div class="icon">
                                <?= $action === 'add' ? '✅' : '📭' ?>
                            </div>
                            <h3>
                                <?= $action === 'add' 
                                    ? 'No Services Available to Add' 
                                    : 'No Specializations to Remove' ?>
                            </h3>
                            <p>
                                <?= $action === 'add'
                                    ? 'This dentist already has all available specializations.'
                                    : 'This dentist doesn\'t have any specializations assigned.' ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- Select All -->
                        <div class="select-all-container">
                            <input type="checkbox" id="select-all" class="select-all-checkbox">
                            <label for="select-all" class="select-all-label">Select All Services</label>
                            <span class="current-count"><?= count($available_services) ?> service(s) available</span>
                        </div>
                        
                        <!-- Services List -->
                        <div class="services-list">
                            <?php foreach ($available_services as $service): ?>
                                <div class="service-item">
                                    <input type="checkbox" name="services[]" value="<?= htmlspecialchars($service) ?>" id="service_<?= md5($service) ?>" class="service-checkbox">
                                    <label for="service_<?= md5($service) ?>" class="service-name">
                                        <?= htmlspecialchars($service) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <a href="<?= BASE_URL ?>/dentists" class="btn btn-secondary">
                                Cancel
                            </a>
                            
                            <button type="submit" class="btn <?= $action === 'add' ? 'btn-success' : 'btn-danger' ?>">
                                <?= $action === 'add' ? '➕ Add Selected' : '❌ Remove Selected' ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Select All functionality
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('select-all');
        const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                serviceCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
            
            // Update select all when individual checkboxes change
            serviceCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(serviceCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(serviceCheckboxes).some(cb => cb.checked);
                    
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = someChecked && !allChecked;
                });
            });
        }
        
        // Form submission confirmation for removal
        const form = document.querySelector('form');
        if (form && '<?= $action ?>' === 'remove') {
            form.addEventListener('submit', function(e) {
                const selectedCount = document.querySelectorAll('.service-checkbox:checked').length;
                if (selectedCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one service to remove.');
                    return;
                }
                
                if (!confirm(`Are you sure you want to remove ${selectedCount} specialization(s) from this dentist?`)) {
                    e.preventDefault();
                }
            });
        }
    });
    </script>
</body>
</html>