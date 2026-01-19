<?php
require_once 'db.php';

$current_page = 'manage_services';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superintendent') { 
    die("Unauthorized"); 
}

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1>Direct access to this script is strictly prohibited.');
}
// 1. Initial Check for Linked Dentists (Generates the Javascript Popup)
if (isset($_GET['delete_id']) && !isset($_GET['confirm_delete'])) {
    $id = $_GET['delete_id'];
    
    $stmt = $pdo->prepare("SELECT service_name FROM available_services WHERE id = ?");
    $stmt->execute([$id]);
    $service = $stmt->fetch();

    if ($service) {
        $service_name = $service['service_name'];
        
        // Count and list dentists performing this service
        $stmt = $pdo->prepare("SELECT d.dentist_name FROM dentists d 
                               JOIN specializations s ON d.dentist_id = s.dentist_id 
                               WHERE s.service_name = ?");
        $stmt->execute([$service_name]);
        $dentists = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $count = count($dentists);

        if ($count > 0) {
            $names = implode(', ', $dentists);
            echo "
            <script>
                if (confirm('Warning: There are $count dentist(s) ($names) capable of performing this service. Do you still want to proceed? If yes, the service will be removed and these dentists will be disassociated from it.')) {
                    window.location.href = 'manage_services.php?delete_id=$id&confirm_delete=1';
                } else {
                    window.location.href = 'manage_services.php';
                }
            </script>";
            exit;
        } else {
            // No dentists linked, jump to Step 2
            header("Location: manage_services.php?delete_id=$id&confirm_delete=1");
            exit;
        }
    }
}

// 2. The Actual Deletion & Disassociation Logic (Runs after 'Yes' is clicked)
if (isset($_GET['delete_id']) && isset($_GET['confirm_delete'])) {
    try {
        $pdo->beginTransaction();
        $id = $_GET['delete_id'];

        $stmt = $pdo->prepare("SELECT service_name FROM available_services WHERE id = ?");
        $stmt->execute([$id]);
        $service = $stmt->fetch();

        if ($service) {
            $service_name = $service['service_name'];

            // A: Disassociate dentists in specializations table
            $stmt = $pdo->prepare("DELETE FROM specializations WHERE service_name = ?");
            $stmt->execute([$service_name]);

            // B: Remove from the master list
            $stmt = $pdo->prepare("DELETE FROM available_services WHERE id = ?");
            $stmt->execute([$id]);
        }

        $pdo->commit();
        $_SESSION['msg'] = "Service removed and dentists disassociated.";
    $_SESSION['msg_type'] = "error"; // Using error color for deletion
        header("Location: manage_services.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error during deletion: " . $e->getMessage());
    }
}

// Handle Adding
if (isset($_POST['add_service']) && !empty($_POST['new_service'])) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO available_services (service_name) VALUES (?)");
    if($stmt->execute([$_POST['new_service']])) {
        $_SESSION['msg'] = "Service added successfully!";
        $_SESSION['msg_type'] = "success";
    }
}

// Fetch all for display
$services = $pdo->query("SELECT * FROM available_services ORDER BY service_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Services</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="display: flex;">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="toast-notification <?= $_SESSION['msg_type'] ?>" id="toast">
            <?= $_SESSION['msg'] ?>
        </div>
        <?php 
            unset($_SESSION['msg']); 
            unset($_SESSION['msg_type']); 
        ?>
        <script>
            setTimeout(() => {
                document.getElementById('toast').style.display = 'none';
            }, 3000); // Hides after 3 seconds
        </script>
    <?php endif; ?>
    <div class="registration-card">
        <h3>Manage Clinic Specializations</h3>
        <p style="font-size: 0.9em; color: #666; margin-bottom: 20px;">
            Services added here will appear as options when registering new dentists.
        </p>

        <form method="POST" style="display:flex; gap:10px; margin-bottom:20px;">
            <input type="text" name="new_service" placeholder="e.g. Orthodontics" required 
                   style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <button type="submit" name="add_service" class="btn-primary" 
                    style="width:auto; margin:0; padding: 10px 20px;">Add Service</button>
        </form>

        <div class="checkbox-grid">
            <?php foreach ($services as $s): ?>
                <div class="checkbox-card" style="justify-content: space-between; display: flex; align-items: center;">
                    <span><?= htmlspecialchars($s['service_name']) ?></span>
                    <a href="?delete_id=<?= $s['id'] ?>" 
                       onclick="return confirm('Are you sure? This will remove the option from the registration form.')"
                       style="color:#e74c3c; text-decoration:none; font-weight:bold; font-size: 1.2em; padding: 0 5px;">
                       &times;
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>