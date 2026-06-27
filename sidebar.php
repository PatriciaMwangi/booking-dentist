<?php
// 1. Fetch the total count of available services for the badge
$service_count_query = $pdo->query("SELECT COUNT(*) FROM available_services");
$total_services = $service_count_query->fetchColumn();
// Function to get profile picture (similar to your profile.php)
function getSidebarProfilePicture($user_id, $role) {
    $profile_dir = 'uploads/profiles/';
    
    // Check if directory exists
    if (!is_dir($profile_dir)) {
        // Return default avatar
        return "data:image/svg+xml;base64," . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"><circle cx="20" cy="20" r="18" fill="#4a90e2"/><text x="20" y="24" text-anchor="middle" fill="white" font-size="16">👨‍⚕️</text></svg>');
    }
    
    $extensions = ['jpg', 'jpeg', 'png', 'gif'];
    foreach ($extensions as $ext) {
        $filename = $role . '_' . $user_id . '.' . $ext;
        if (file_exists($profile_dir . $filename)) {
            return $profile_dir . $filename . '?t=' . filemtime($profile_dir . $filename);
        }
    }
    
    // Default avatar
    return "data:image/svg+xml;base64," . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"><circle cx="20" cy="20" r="18" fill="#4a90e2"/><text x="20" y="24" text-anchor="middle" fill="white" font-size="16">👨‍⚕️</text></svg>');
}

// Get user info for sidebar
$user_id = $_SESSION['dentist_id'] ?? null;
$role = $_SESSION['role'] ?? null;
$user_name = $_SESSION['dentist_name'] ?? 'User';

// Get total services count for badge (if needed)
$service_count = 0;
if (isset($pdo)) {
    try {
        $service_count_query = $pdo->query("SELECT COUNT(*) FROM available_services");
        $service_count = $service_count_query->fetchColumn();
    } catch (Exception $e) {
        $service_count = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Your head content -->
</head>
<body style="display: flex;">
    <div class="sidebar-container">
        <!-- Profile Header -->
        <div class="sidebar-profile-header">
            <a href="<?= BASE_URL ?>/profile" class="profile-link">
                <div class="profile-avatar-small">
                    <img src="<?= getSidebarProfilePicture($user_id, $role) ?>" 
                         alt="Profile Picture" 
                         class="profile-pic-small">
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?= htmlspecialchars($user_name) ?></div>
                    <div class="profile-role"><?= htmlspecialchars(ucfirst($role)) ?></div>
                </div>
            </a>
        </div>
        
        <!-- Clinic Menu Header -->
        <div class="sidebar-header">Clinic Menu</div>
        

<nav class="sidebar-nav">    
    <a href="<?= BASE_URL ?>/calendar" class="sidebar-item <?= ($current_page == 'calendar') ? 'active' : '' ?>">
        📅 My Calendar
    </a>
        <a href="<?= BASE_URL ?>/" class="sidebar-item <?= ($current_page == '') ? 'active' : '' ?>">
        ➕  Appointment
    </a>
        <a href="<?= BASE_URL ?>/profile" class="sidebar-item">
        👤 My Profile
    </a>
    <a href="<?= BASE_URL ?>/dentists" class="sidebar-item <?= ($current_page == 'view_dentists') ? 'active' : '' ?>">
        👩‍⚕️ View All Dentists
    </a>
    
    <a href="<?= BASE_URL ?>/schedule" class="sidebar-item <?= ($current_page == 'schedule') ? 'active' : '' ?>">
        📋 View All Schedule
    </a>

    
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent'): ?>
        <a href="<?= BASE_URL ?>/new-dentist" class="sidebar-item <?= ($current_page == 'register_dentists') ? 'active' : '' ?>">
            👤 Register New Dentist
        </a>
        
        <a href="<?= BASE_URL ?>/services" class="sidebar-item <?= ($current_page == 'manage_services') ? 'active' : '' ?>" style="display: flex; justify-content: space-between; align-items: center;">
            <span>⚙️ Manage Services</span>
            <?php if ($total_services > 0): ?>
                <span class="sidebar-badge"><?= $total_services ?></span>
            <?php endif; ?>
        </a>
                <a href="<?= BASE_URL ?>/patients" class="sidebar-item <?= ($current_page == 'patients') ? 'active' : '' ?>">
            👥 All Patients
        </a>
               <?php
        // Count recent logs (last 24 hours) for badge
        $recent_logs_count = 0;
        if (isset($pdo)) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= NOW() - INTERVAL 1 DAY");
                $recent_logs_count = $stmt->fetchColumn();
            } catch (Exception $e) {
                error_log("Error counting logs: " . $e->getMessage());
            }
        }
        ?>
        <a href="<?= BASE_URL ?>/logs" class="sidebar-item <?= ($current_page == 'manage_logs') ? 'active' : '' ?>" style="display: flex; justify-content: space-between; align-items: center;">
            <span>📊 Activity Logs</span>
            <?php if ($recent_logs_count > 0): ?>
                <span class="sidebar-badge" style="background: #e74c3c;"><?= $recent_logs_count ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>
<div class="sidebar-filter-container" id="calendar-duty-container">
    <div id="duty-status-container" style="display: none; margin-bottom: 20px; padding: 15px; background: #fff; border-radius: 8px; border-left: 4px solid #3498db; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
        <h4 id="duty-date-title" style="margin-top:0; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; font-size: 16px;">
            <i class="fas fa-calendar-alt" style="margin-right: 8px;"></i>
            <span id="selected-date">Select a date</span>
        </h4>
                <div style="margin-bottom: 15px;">
            <input type="text" id="duty-search" placeholder="🔍 Search dentist..." 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
        </div>
        <div style="margin-bottom: 20px;">
            <h5 style="color: #27ae60; margin-bottom: 8px; font-size: 14px; display: flex; align-items: center;">
                <i class="fas fa-user-md" style="margin-right: 8px;"></i>
                On Duty <span id="on-duty-count" style="margin-left: auto; background: #27ae60; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">0</span>
            </h5>
            <div id="on-duty-list" style="max-height: 200px; overflow-y: auto; padding-right: 5px;">
                <div style="text-align: center; padding: 10px; color: #95a5a6; font-style: italic;">
                    Click a date on the calendar
                </div>
            </div>
        </div>
    
        <div>
            <h5 style="color: #e74c3c; margin-bottom: 8px; font-size: 14px; display: flex; align-items: center;">
                <i class="fas fa-user-clock" style="margin-right: 8px;"></i>
                Off Duty <span id="off-duty-count" style="margin-left: auto; background: #e74c3c; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">0</span>
            </h5>
            <div id="off-duty-list" style="max-height: 200px; overflow-y: auto; padding-right: 5px; font-size: 13px;">
                <div style="text-align: center; padding: 10px; color: #95a5a6; font-style: italic;">
                    No date selected
                </div>
            </div>
        </div>
    </div>
</div>
<?php if ($current_page == 'schedule' || $current_page == 'view_dentists'): ?>
    <div class="sidebar-filter-container">
        <form method="GET" action="<?= $current_page ?>">
            
           <?php if ($current_page == 'schedule'): ?>
    <div class="filter-group">
        <h4>Schedule View</h4>
        <label class="checkbox-item" style="font-weight: bold; color: #3498db; background: #f0f7ff; padding: 8px; border-radius: 4px; margin-bottom: 15px;">
            <input type="checkbox" name="show_all" value="1" <?= isset($_GET['show_all']) ? 'checked' : '' ?>> 
            Show All Schedules
        </label>

        <h4>Filter by Dentist</h4>
        <?php 
        // Sort the list so the session dentist is always at index 0
        usort($dentists_list, function($a, $b) use ($session_id) {
            if ($a['dentist_id'] == $session_id) return -1;
            if ($b['dentist_id'] == $session_id) return 1;
            return 0;
        });

        foreach($dentists_list as $d): 
            $is_me = ($d['dentist_id'] == $session_id); // Check if this is the logged-in user
        ?>
            <label class="checkbox-item" style="<?= $is_me ? 'color: #2ecc71; font-weight: bold; background: #eafaf1; border-radius: 4px; padding: 2px 5px;' : '' ?>">
                <input type="checkbox" name="dentists[]" value="<?= $d['dentist_id'] ?>" 
                <?= in_array($d['dentist_id'], $filter_dentists) ? 'checked' : '' ?>> 
                
                <?= htmlspecialchars($d['dentist_name']) ?>
                
                <?php if ($is_me): ?>
                    <small style="background: #2ecc71; color: white; padding: 1px 4px; border-radius: 3px; margin-left: 5px; font-size: 0.7rem;">YOU</small>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($current_page == 'view_dentists'): ?>
    <div class="filter-group">
        <h4>Filter by Services</h4>
        
        <label class="checkbox-item" style="font-weight: bold; color: #3498db; border-bottom: 1px solid #eee; margin-bottom: 10px; padding-bottom: 8px;">
            <input type="checkbox" id="select-all-services"> 
            Select All Services
        </label>

        <div class="services-scroll-container" style="max-height: 250px; overflow-y: auto;">
            <?php 
            // Fetch all unique services for the filter
            $all_services = $pdo->query("SELECT service_name FROM available_services ORDER BY service_name ASC")->fetchAll(PDO::FETCH_COLUMN);
            $active_services = $_GET['services'] ?? [];
            
            foreach($all_services as $service): 
            ?>
                <label class="checkbox-item">
                    <input type="checkbox" name="services[]" class="service-checkbox" 
                           value="<?= htmlspecialchars($service) ?>" 
                           <?= in_array($service, $active_services) ? 'checked' : '' ?>> 
                    <?= htmlspecialchars($service) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
            <button type="submit" class="btn-apply">Apply Filters</button>
            <a href="<?= $current_page ?>" class="btn-clear">Clear All</a>
        </form>
    </div>
<?php endif; ?>
    <a href="logout" class="sidebar-item logout-link">
        🚪 Logout
    </a>
</div>
