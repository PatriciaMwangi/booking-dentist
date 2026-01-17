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
                        <h4>Filter by Dentist</h4>
                        <?php foreach($dentists_list as $d): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="dentists[]" value="<?= $d['dentist_id'] ?>" 
                                <?= in_array($d['dentist_id'], $filter_dentists ?? []) ? 'checked' : '' ?>> <?= htmlspecialchars($d['dentist_name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="filter-group">
                    <h4>Filter by Service</h4>
                    <?php foreach($services_list as $s): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="services[]" value="<?= $s ?>" 
                            <?php 
                                $active_filters = $filter_services ?? ($_GET['services'] ?? []);
                                echo in_array($s, $active_filters) ? 'checked' : ''; 
                            ?>> <?= htmlspecialchars($s) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn-apply">Apply Filters</button>
                <a href="<?= $current_page ?>.php" class="btn-clear">Clear All</a>
            </form>
        </div>
    <?php endif; ?>

    <a href="logout.php" class="sidebar-item logout-link">
        🚪 Logout
    </a>
</div>