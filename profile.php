<?php
require_once 'db.php';

// Profile picture function (add at the top)
function getProfilePicture($user_id, $role, $db_path = null) {
    // First check database
    if ($db_path && file_exists($db_path)) {
        return $db_path . '?t=' . filemtime($db_path);
    }
    
    // Fallback to file system search
    $profile_dir = 'uploads/profiles/';
    $extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    foreach ($extensions as $ext) {
        $filename = $role . '_' . $user_id . '.' . $ext;
        if (file_exists($profile_dir . $filename)) {
            return $profile_dir . $filename . '?t=' . filemtime($profile_dir . $filename);
        }
    }
    
    // Default avatar
    return "data:image/svg+xml;base64," . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="45" fill="#4a90e2"/><text x="50" y="60" text-anchor="middle" fill="white" font-size="40">👨‍⚕️</text></svg>');
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['dentist_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['dentist_id'];
$role = $_SESSION['role'];
$message = "";
$message_type = "";

// Initialize user data
$user_data = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'specialization' => ''
];

// Get user data based on role
try {
    if ($role === 'dentist' || $role === 'superintendent') {
        // FIXED: Match column names from your screenshot
        $stmt = $pdo->prepare("SELECT dentist_name as name, email, phone_number as phone FROM dentists WHERE dentist_id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: $user_data;
        
        // Get specialization if table exists (adjust table/column names as needed)
        try {
            $stmt_spec = $pdo->prepare("SELECT service_name FROM specializations WHERE dentist_id = ?");
            $stmt_spec->execute([$user_id]);
            $specializations = $stmt_spec->fetchAll(PDO::FETCH_COLUMN);
            $user_data['specialization'] = implode(', ', $specializations);
        } catch (Exception $e) {
            // Specializations table might not exist
            $user_data['specialization'] = '';
        }
        
    } elseif ($role === 'patient') {
        $stmt = $pdo->prepare("SELECT patient_name as name, email, phone FROM patients WHERE patient_id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: $user_data;
    }
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update personal information
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validate inputs
        if (empty($name) || empty($email)) {
            $message = "Name and email are required.";
            $message_type = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $message_type = "error";
        } else {
            try {
                // Update based on role
                if ($role === 'dentist' || $role === 'superintendent') {
                    // FIXED: Match column names from your screenshot
                    $stmt = $pdo->prepare("UPDATE dentists SET dentist_name = ?, email = ?, phone_number = ? WHERE dentist_id = ?");
                    $stmt->execute([$name, $email, $phone, $user_id]);
                    
                    // Update session
                    $_SESSION['dentist_name'] = $name;
                    
                } elseif ($role === 'patient') {
                    $stmt = $pdo->prepare("UPDATE patients SET patient_name = ?, email = ?, phone = ? WHERE patient_id = ?");
                    $stmt->execute([$name, $email, $phone, $user_id]);
                }
                
                // Handle profile picture upload
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $target_dir = "uploads/profiles/";
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    
                    // Validate file
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 2 * 1024 * 1024; // 2MB
                    $file_type = $_FILES['profile_picture']['type'];
                    $file_size = $_FILES['profile_picture']['size'];
                    
                    if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                        // Delete old profile pictures
                        $old_files = glob($target_dir . $role . '_' . $user_id . '.*');
                        foreach ($old_files as $old_file) {
                            if (is_file($old_file)) {
                                unlink($old_file);
                            }
                        }
                        
                        // Upload new file
                        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                        $filename = $role . '_' . $user_id . '.' . $ext;
                        $target_file = $target_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                            // Success
                        }
                    }
                }
                
                $message = "Profile updated successfully!";
                $message_type = "success";
                
                // Refresh user data
                if ($role === 'dentist' || $role === 'superintendent') {
                    $stmt = $pdo->prepare("SELECT dentist_name as name, email, phone_number as phone FROM dentists WHERE dentist_id = ?");
                    $stmt->execute([$user_id]);
                    $user_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: $user_data;
                }
                
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Duplicate email
                    $message = "This email is already registered. Please use a different email.";
                } else {
                    $message = "Error updating profile: " . $e->getMessage();
                }
                $message_type = "error";
            }
        }
        
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = "All password fields are required.";
            $message_type = "error";
        } elseif ($new_password !== $confirm_password) {
            $message = "New passwords do not match.";
            $message_type = "error";
        } elseif (strlen($new_password) < 6) {
            $message = "New password must be at least 6 characters long.";
            $message_type = "error";
        } else {
            try {
                // Verify current password
                if ($role === 'dentist' || $role === 'superintendent') {
                    $stmt = $pdo->prepare("SELECT password FROM dentists WHERE dentist_id = ?");
                    $stmt->execute([$user_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result && password_verify($current_password, $result['password'])) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE dentists SET password = ? WHERE dentist_id = ?");
                        $stmt->execute([$hashed_password, $user_id]);
                        
                        $message = "Password changed successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Current password is incorrect.";
                        $message_type = "error";
                    }
                } else {
                    // Handle other roles
                    $message = "Password change not implemented for your role.";
                    $message_type = "error";
                }
                
            } catch (PDOException $e) {
                $message = "Error changing password: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>My Profile - Dental Clinic</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
            overflow: hidden;
        }

        /* Header Section */
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .profile-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 40px;
            background: white;
            border-radius: 100% 100% 0 0;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            position: relative;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .profile-name {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .profile-role {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        /* Main Content */
        .profile-content {
            padding: 40px 30px;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #e8f7ef;
            color: #0a7c3e;
            border-left: 4px solid #0a7c3e;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert::before {
            font-size: 20px;
        }

        .alert-success::before {
            content: '✓';
        }

        .alert-error::before {
            content: '!';
        }

        /* Sections */
        .section {
            background: #f8fafc;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::before {
            font-size: 24px;
        }

        /* Profile Picture Section */
        .profile-picture-section .section-title::before {
            content: '🖼️';
        }

        .profile-picture-container {
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .picture-preview {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            position: relative;
        }

        .picture-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .picture-preview:hover img {
            transform: scale(1.05);
        }

        .picture-upload {
            flex: 1;
            min-width: 250px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control[readonly] {
            background: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }

        /* Password Fields */
        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 42px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #64748b;
            padding: 5px;
            border-radius: 5px;
            transition: all 0.2s;
        }

        .password-toggle:hover {
            background: #f1f5f9;
            color: #333;
        }

        /* Password Strength Meter */
        .password-strength {
            margin-top: 12px;
        }

        .strength-meter {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        #strengthText {
            font-size: 13px;
            color: #64748b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #strengthLabel {
            font-weight: 600;
        }

        /* Buttons */
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        /* Specialization Note */
        .specialization-note {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Footer */
        .profile-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .nav-menu {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .nav-link:hover {
            background: #e2e8f0;
            color: #333;
        }

        .last-updated {
            font-size: 12px;
            color: #94a3b8;
        }

        /* File Upload */
        .file-upload-wrapper {
            position: relative;
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            display: block;
            padding: 12px 20px;
            background: white;
            border: 2px dashed #cbd5e0;
            border-radius: 10px;
            text-align: center;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload-label:hover {
            border-color: #667eea;
            background: #f8fafc;
        }

        .file-upload-label.drag-over {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .file-info {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 8px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .profile-header {
                padding: 30px 20px;
            }
            
            .profile-content {
                padding: 30px 20px;
            }
            
            .profile-footer {
                flex-direction: column;
                text-align: center;
            }
            
            .nav-menu {
                justify-content: center;
            }
            
            .profile-picture-container {
                flex-direction: column;
                text-align: center;
            }
            
            .picture-upload {
                width: 100%;
            }
            
            .section {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .profile-container {
                border-radius: 15px;
            }
            
            .nav-menu {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
            }
        }

        /* Icons for section titles */
        .personal-info-section .section-title::before {
            content: '👤';
        }

        .password-section .section-title::before {
            content: '🔐';
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Header with avatar and basic info -->
        <div class="profile-header">
            <div class="profile-avatar">
                <img id="profilePreview" src="<?= getProfilePicture($user_id, $role) ?>" 
                     alt="Profile Picture">
            </div>
            <h1 class="profile-name"><?= htmlspecialchars($user_data['name']) ?></h1>
            <div class="profile-role">
                <?= htmlspecialchars(ucfirst($role)) ?>
            </div>
        </div>

        <!-- Main content -->
        <div class="profile-content">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <!-- Profile Picture Upload -->
            <div class="section profile-picture-section">
                <h2 class="section-title">Profile Picture</h2>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <div class="profile-picture-container">
                            <div class="picture-preview">
                                <img id="profilePreview" src="<?= getProfilePicture($user_id, $role) ?>" 
                                     alt="Profile Picture">
                            </div>
                            <div class="picture-upload">
                                <div class="file-upload-wrapper">
                                    <input type="file" id="profile_picture" name="profile_picture" 
                                           accept="image/*" class="form-control">
                                    <label for="profile_picture" class="file-upload-label">
                                        📁 Click to upload or drag and drop
                                    </label>
                                </div>
                                <div class="file-info">
                                    Max size: 2MB. JPG, PNG, or GIF.
                                </div>
                            </div>
                        </div>
                    </div>
            
            <!-- Personal Information Section -->
            <div class="section personal-info-section">
                <h2 class="section-title">Personal Information</h2>
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" 
                           value="<?= htmlspecialchars($user_data['name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           value="<?= htmlspecialchars($user_data['phone'] ?? '') ?>">
                </div>
                
                <?php if ($role === 'dentist' && !empty($user_data['specialization'])): ?>
                <div class="form-group">
                    <label>Specializations</label>
                    <input type="text" class="form-control" 
                           value="<?= htmlspecialchars($user_data['specialization']) ?>" readonly>
                    <div class="specialization-note">
                        <span>ℹ️</span>
                        <span>Contact superintendent to update specializations </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <button type="submit" name="update_profile" class="btn btn-primary">
                    <span>💾</span>
                    <span>Save Changes</span>
                </button>
                </form>
            </div>
            
            <!-- Change Password Section -->
            <div class="section password-section">
                <h2 class="section-title">Change Password</h2>
                <form method="POST" action="" id="passwordForm">
                    <div class="form-group password-container">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                            👁️
                        </button>
                    </div>
                    
                    <div class="form-group password-container">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" 
                               oninput="checkPasswordStrength(this.value)" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                            👁️
                        </button>
                        <div class="password-strength">
                            <div id="strengthText">
                                <span>Password strength:</span>
                                <span id="strengthLabel">None</span>
                            </div>
                            <div class="strength-meter">
                                <div class="strength-fill" id="strengthMeter"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group password-container">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            👁️
                        </button>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <span>🔐</span>
                        <span>Change Password</span>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Footer with navigation -->
        <div class="profile-footer">
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link">
                    <span>🏠</span>
                    <span>Dashboard</span>
                </a>
                <?php if ($role === 'dentist' || $role === 'superintendent'): ?>
                    <a href="calendar.php" class="nav-link">
                        <span>📅</span>
                        <span>My Schedule</span>
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="nav-link">
                    <span>🚪</span>
                    <span>Logout</span>
                </a>
            </div>
            <div class="last-updated">
                Last updated: <?= date('F j, Y') ?>
            </div>
        </div>
    </div>

    <script>
        // Preview profile picture before upload
        const profilePictureInput = document.getElementById('profile_picture');
        const profilePreview = document.getElementById('profilePreview');
        const fileUploadLabel = document.querySelector('.file-upload-label');

        profilePictureInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profilePreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        // Drag and drop for file upload
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUploadLabel.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            fileUploadLabel.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileUploadLabel.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            fileUploadLabel.classList.add('drag-over');
        }

        function unhighlight() {
            fileUploadLabel.classList.remove('drag-over');
        }

        fileUploadLabel.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            profilePictureInput.files = files;
            
            // Trigger change event
            const event = new Event('change');
            profilePictureInput.dispatchEvent(event);
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            const strengthText = document.getElementById('strengthLabel');
            const strengthMeter = document.getElementById('strengthMeter');
            
            // Criteria
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            let strengthLabel = '';
            let color = '';
            let width = '0%';
            
            switch(strength) {
                case 0:
                case 1:
                    strengthLabel = 'Very Weak';
                    color = '#ef4444';
                    width = '20%';
                    break;
                case 2:
                    strengthLabel = 'Weak';
                    color = '#f97316';
                    width = '40%';
                    break;
                case 3:
                    strengthLabel = 'Fair';
                    color = '#eab308';
                    width = '60%';
                    break;
                case 4:
                    strengthLabel = 'Good';
                    color = '#22c55e';
                    width = '80%';
                    break;
                case 5:
                    strengthLabel = 'Excellent';
                    color = '#16a34a';
                    width = '100%';
                    break;
            }
            
            strengthText.textContent = strengthLabel;
            strengthText.style.color = color;
            strengthMeter.style.backgroundColor = color;
            strengthMeter.style.width = width;
        }
        
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggleBtn = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                toggleBtn.textContent = '🙈';
                toggleBtn.setAttribute('aria-label', 'Hide password');
            } else {
                field.type = 'password';
                toggleBtn.textContent = '👁️';
                toggleBtn.setAttribute('aria-label', 'Show password');
            }
        }
        
        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showNotification('New passwords do not match!', 'error');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                showNotification('Password must be at least 6 characters long!', 'error');
                return false;
            }
            
            return true;
        });

        // Notification function
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => notification.remove());
            
            // Create new notification
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 16px 24px;
                border-radius: 12px;
                color: white;
                font-weight: 500;
                z-index: 1000;
                animation: slideIn 0.3s ease;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            `;
            
            if (type === 'error') {
                notification.style.background = '#ef4444';
            } else {
                notification.style.background = '#22c55e';
            }
            
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Add CSS animations for notifications
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Auto-save indicators (optional enhancement)
        const autoSaveFields = ['name', 'email', 'phone'];
        autoSaveFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                let timeout;
                field.addEventListener('input', function() {
                    clearTimeout(timeout);
                    // You could add auto-save functionality here
                    // For now, just update the last updated time
                    timeout = setTimeout(() => {
                        const lastUpdated = document.querySelector('.last-updated');
                        if (lastUpdated) {
                            lastUpdated.textContent = `Last saved: ${new Date().toLocaleTimeString()}`;
                        }
                    }, 1000);
                });
            }
        });
    </script>
</body>
</html>