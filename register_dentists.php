<?php
require_once 'config.php';
session_start();

// SECURITY: Only allow existing superintendents to access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superintendent') {
    die("Access Denied: Only a Superintendent can register new users.");
}

$current_page = 'register';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['dentist_name'];
    $user = $_POST['username'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role']; // New: Captured from dropdown
    $services = $_POST['services'] ?? [];

    try {
        $pdo->beginTransaction();

        // Optional: Enforcement - Check if we are exceeding a limit for Superintendents
        if ($role === 'superintendent') {
            $checkStmt = $pdo->query("SELECT COUNT(*) FROM dentists WHERE role = 'superintendent'");
            if ($checkStmt->fetchColumn() >= 5) {
                throw new Exception("Maximum number of Superintendents (5) reached.");
            }
        }

        // 1. Insert into dentists table
        $stmt = $pdo->prepare("INSERT INTO dentists (dentist_name, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $user, $pass, $role]);
        $new_id = $pdo->lastInsertId();

        // 2. Insert specializations into specializations table
        if (!empty($services)) {
            $specStmt = $pdo->prepare("INSERT INTO specializations (dentist_id, service_name) VALUES (?, ?)");
            foreach ($services as $service) {
                $specStmt->execute([$new_id, $service]);
            }
        }

        $pdo->commit();
        $success = "User registered successfully as: " . ucfirst($role);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Registration failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register New User</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="display: flex;">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="registration-card">
        <h2>Register New Staff Member</h2>
        <hr>
        
        <?php if(isset($success)) echo "<p class='msg success'>$success</p>"; ?>
        <?php if(isset($error)) echo "<p class='msg error'>$error</p>"; ?>

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="dentist_name" placeholder="Dr. Jane Doe" required>
            </div>

            <div class="form-group">
                <label>Login Username</label>
                <input type="text" name="username" placeholder="jdoe_dentist" required>
            </div>

            <div class="form-group">
                <label>Temporary Password</label>
                <input type="password" name="password" required>
            </div>

            <div class="form-group">
                <label>System Role</label>
                <select name="role" required>
                    <option value="dentist">Standard Dentist</option>
                    <option value="superintendent">Superintendent (Admin)</option>
                </select>
                <small>Superintendents can register other users and view all schedules.</small>
            </div>

       <div class="form-group">
    <label>Specializations</label>
    <div class="checkbox-grid">
        <?php
        $available = $pdo->query("SELECT service_name FROM available_services ORDER BY service_name ASC");
        while ($row = $available->fetch()) {
            echo '
            <label class="checkbox-card">
                <input type="checkbox" name="services[]" value="'.htmlspecialchars($row['service_name']).'">
                <span>'.htmlspecialchars($row['service_name']).'</span>
            </label>';
        }
        ?>
    </div>
</div>

            <button type="submit" class="btn-primary">Register Staff Member</button>
        </form>
    </div>
</div>

</body>
</html>