<?php
// 1. Fetch the total count of available services for the badge
$service_count_query = $pdo->query("SELECT COUNT(*) FROM available_services");
$total_services = $service_count_query->fetchColumn();
?>

<div class="sidebar">
    <div class="sidebar-header">Clinic Menu</div>
    
    <a href="dentist.php" class="sidebar-item <?= ($current_page == 'calendar') ? 'active' : '' ?>">
        📅 My Calendar
    </a>

    <a href="view_dentists.php" class="sidebar-item <?= ($current_page == 'view_dentists') ? 'active' : '' ?>">
        👩‍⚕️ View All Dentists
    </a>
    
    <a href="schedule.php" class="sidebar-item <?= ($current_page == 'schedule') ? 'active' : '' ?>">
        📋 View All Schedule
    </a>
    
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent'): ?>
        <a href="register_dentists.php" class="sidebar-item <?= ($current_page == 'register_dentists') ? 'active' : '' ?>">
            👤 Register New Dentist
        </a>
        
        <a href="manage_services.php" class="sidebar-item <?= ($current_page == 'manage_services') ? 'active' : '' ?>" style="display: flex; justify-content: space-between; align-items: center;">
            <span>⚙️ Manage Services</span>
            <?php if ($total_services > 0): ?>
                <span class="sidebar-badge"><?= $total_services ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>
<?php if ($current_page == 'schedule' || $current_page == 'view_dentists'): ?>
    <div class="sidebar-filter-container">
        <form method="GET" action="<?= $current_page ?>.php">
            
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
            <a href="<?= $current_page ?>.php" class="btn-clear">Clear All</a>
        </form>
    </div>
<?php endif; ?>
    <a href="logout.php" class="sidebar-item logout-link">
        🚪 Logout
    </a>
</div>
