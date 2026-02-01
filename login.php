<?php
// session_start();
require_once 'db.php';
$error = "";

if (!defined('BASE_URL')) {
    // Detect base URL dynamically
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $base = ($script_dir === '/' || $script_dir === '\\') ? '' : $script_dir;
    $base = rtrim($base, '/');
    define('BASE_URL', $protocol . '://' . $host . $base);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];


    $stmt = $pdo->prepare("SELECT dentist_id, dentist_name, password, role FROM dentists WHERE username = ? LIMIT 1");
    $stmt->execute([$user]);
    $dentist = $stmt->fetch();

if ($dentist && password_verify($pass, $dentist['password'])) {
    $_SESSION['dentist_id'] = $dentist['dentist_id'];
    $_SESSION['dentist_name'] = $dentist['dentist_name'];
    $_SESSION['role'] = $dentist['role']; // This must be 'dentist' or 'superintendent'

    require_once 'logger.php';
    logLogin($dentist['dentist_id'], $dentist['dentist_name']);

    // Use your BASE_URL constant for the redirect
    header("Location: " . BASE_URL . "/dentist.php"); 
    exit();
}else {
        $error = "Invalid username or password.";
    }
}
if (!empty($_POST['website_verification_code'])) {
    die("Bot detected."); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dentist Portal - Login</title>
    <link rel="icon" href="data:,">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f4f8; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 350px; text-align: center; }
        .login-card h2 { color: #2c3e50; margin-bottom: 25px; border-bottom: 2px solid #3498db; display: inline-block; padding-bottom: 5px; }
        .input-group { margin-bottom: 15px; text-align: left; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
        input:focus { border-color: #3498db; outline: none; box-shadow: 0 0 5px rgba(52, 152, 219, 0.3); }
        button { width: 100%; padding: 12px; background-color: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; transition: background 0.3s; }
        button:hover { background-color: #2980b9; }
        .error-msg { background: #fde8e8; color: #c81e1e; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; border: 1px solid #f8b4b4; }
        .back-link { margin-top: 20px; display: block; color: #7f8c8d; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>

<div class="login-card">
    <h2>Dentist Portal</h2>

    <?php if ($error): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>

<form method="POST">
    <div style="display:none;">
        <input type="text" name="website_verification_code" value="">
    </div>

    <div class="input-group">
        <input type="text" name="username" placeholder="Username" value ="admin" required>
    </div>
    <div class="input-group">
        <input type="password" name="password" placeholder="Password" value ="admin123" required>
    </div>
    <button type="submit">Sign In</button>
</form>
    
<?php if (isset($_SESSION['dentist_id'])): ?>
    <a href="<?= BASE_URL ?>/schedule" class="back-link">← Back to Schedule</a>
<?php else: ?>
    <a href="<?= BASE_URL ?>/" class="back-link">← Book Appointments</a>
<?php endif; ?></div>

</body>
</html>