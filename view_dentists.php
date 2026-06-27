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

// Display success message with reassignment details
if (isset($_SESSION['success']) && isset($_SESSION['reassignment_details'])) {
    $details = $_SESSION['reassignment_details'];
    unset($_SESSION['reassignment_details']);
}

// 1. Fetch Master Services List
$services_list = $pdo->query("SELECT service_name FROM available_services")->fetchAll(PDO::FETCH_COLUMN);

// 2. Handle filters
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

// 3. STRICT "AND" FILTERING
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
    
    foreach ($sidebar_services as $s) { 
        $params[] = $s; 
    }
    $params[] = $service_count;
}

// 4. FINAL SQL QUERY
$sql = "SELECT 
            d.dentist_id, 
            d.dentist_name, 
            d.role,
            d.status,
            (SELECT GROUP_CONCAT(service_name SEPARATOR ', ') 
             FROM specializations 
             WHERE dentist_id = d.dentist_id) as services
        FROM dentists d
        WHERE " . implode(' AND ', $query_parts) . "
        ORDER BY (d.dentist_id = ?) DESC, d.dentist_name ASC";

$params[] = $_SESSION['dentist_id'];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dentist_results = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Our Dentists</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .reassignment-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .reassignment-content {
            background: white;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .reassignment-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 15px 15px 0 0;
        }
        
        .reassignment-body {
            padding: 25px;
        }
        
        .appointment-item {
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .appointment-item.reassignable {
            background: #d5f4e6;
            border-left: 4px solid #27ae60;
        }
        
        .appointment-item.cancellable {
            background: #fadbd8;
            border-left: 4px solid #e74c3c;
        }
        
        .stats-box {
            display: flex;
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-item {
            flex: 1;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-reassign {
            background: #d5f4e6;
            color: #27ae60;
        }
        
        .stat-cancel {
            background: #fadbd8;
            color: #e74c3c;
        }
    </style>
</head>
<body style="display: flex;">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <?php if (isset($_SESSION['success']) && isset($details)): ?>
    <div style="background: #d5f4e6; color: #27ae60; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #27ae60;">
        <?= $_SESSION['success'] ?>
        <?php unset($_SESSION['success']); ?>
        
        <?php if (!empty($details)): ?>
        <details style="margin-top: 10px;">
            <summary style="cursor: pointer; font-weight: 600;">View Reassignment Details</summary>
            <div style="margin-top: 10px; padding: 10px; background: white; border-radius: 6px;">
                <?php foreach ($details as $detail): ?>
                <div style="padding: 8px; border-bottom: 1px solid #eee;">
                    <strong>Appointment #<?= $detail['appointment_id'] ?></strong><br>
                    Patient: <?= $detail['patient'] ?><br>
                    Service: <?= $detail['service'] ?><br>
                    Date: <?= $detail['date'] ?> at <?= $detail['time'] ?><br>
                    <span style="color: #3498db;">Dr. <?= $detail['old_dentist'] ?> → Dr. <?= $detail['new_dentist'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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
                <button type="submit" class="btn-primary">APPLY</button>
                <a href="<?= BASE_URL ?>/dentists" class="btn-reset">CLEAR</a>
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
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dentist_results)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 40px; background: #fff;">
                            <div style="font-size: 3rem; margin-bottom: 10px;">🔍</div>
                            <h3 style="color: #666;">🚫 No Dentists Found</h3>
                            <?php if (!empty($sidebar_services)): ?>
                                <p>No one provides every single service you selected: <br>
                                <strong style="color: #3498db;"><?= htmlspecialchars(implode(', ', $sidebar_services)) ?></strong></p>
                            <?php else: ?>
                                <p>No dentists match your search criteria.</p>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/dentists" style="color: #3498db; text-decoration: underline;">Clear all filters</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($dentist_results as $row): ?>
                        <?php 
                            $is_me = ($row['dentist_id'] == $_SESSION['dentist_id']); 
                            $is_active = ($row['status'] === 'Active');
                        ?>
                        <tr style="<?= $is_me ? 'background-color: #f0fff4;' : '' ?><?= !$is_active ? ' opacity: 0.6;' : '' ?>">
                            <td>
                                <strong>
                                    <?php 
                                        $name = htmlspecialchars($row['dentist_name']);
                                        echo (stripos($name, 'Dr.') === 0) ? $name : 'Dr. ' . $name;
                                    ?>
                                </strong>
                                <?php if($is_me): ?>
                                    <span style="background: #2ecc71; color: white; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; margin-left: 8px;">YOU</span>
                                <?php endif; ?>
                                <?php if(!$is_active): ?>
                                    <span style="background: #e74c3c; color: white; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; margin-left: 8px;">INACTIVE</span>
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
                                <a href="<?= BASE_URL ?>/calendar?dentist_id=<?= $row['dentist_id'] ?>" 
                                   style="text-decoration: none; font-size: 1.2rem; <?= !$is_active ? 'opacity: 0.4; pointer-events: none;' : '' ?>"
                                   title="View Calendar">📅</a>
                                
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent'): ?>
                                    <a href="javascript:void(0)" 
                                       onclick="openServicesPopup(<?= $row['dentist_id'] ?>, '<?= htmlspecialchars(addslashes($row['dentist_name'])) ?>')" 
                                       style="text-decoration: none; font-size: 1.2rem; margin-left: 10px; color: #e74c3c;" 
                                       title="Manage Specializations">⚙️</a>

                                    <a href="javascript:void(0)" 
                                       onclick="toggleDentistStatus(<?= $row['dentist_id'] ?>, '<?= addslashes($row['dentist_name']) ?>', '<?= $row['status'] ?>')" 
                                       style="text-decoration: none; font-size: 1.2rem; margin-left: 10px;" 
                                       title="<?= $is_active ? 'Deactivate' : 'Reactivate' ?>">
                                        <?= $is_active ? '🟢' : '🔴' ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reassignment Preview Modal -->
<div id="reassignmentModal" class="reassignment-modal">
    <div class="reassignment-content">
        <div class="reassignment-header">
            <h3 style="margin: 0;">🔄 Appointment Reassignment Preview</h3>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 0.9rem;" id="modalSubtitle"></p>
        </div>
        <div class="reassignment-body" id="modalBody"></div>
    </div>
</div>

<script>
function toggleDentistStatus(dentistId, dentistName, currentStatus) {
    const isDeactivating = (currentStatus === 'Active');
    const action = isDeactivating ? 'deactivate' : 'reactivate';
    
    if (isDeactivating) {
        // Show loading indicator
        const loadingMsg = 'Checking appointments...';
        console.log(loadingMsg);
        
        // Fetch detailed appointment information
        fetch('<?= BASE_URL ?>/check-dentist-appointments?dentist_id=' + dentistId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                if (!data.hasUpcomingAppointments) {
                    // No appointments - simple confirmation
                    if (confirm(`Deactivate dentist "${dentistName}"?\n\nNo upcoming appointments will be affected.`)) {
                        window.location.href = '<?= BASE_URL ?>/toggle_dentist_status?id=' + dentistId + '&status=Inactive';
                    }
                } else {
                    // Show detailed reassignment modal
                    showReassignmentModal(data, dentistId);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (confirm(`Deactivate dentist "${dentistName}"?\n\nWarning: Could not check appointments. Proceed anyway?`)) {
                    window.location.href = '<?= BASE_URL ?>/toggle_dentist_status?id=' + dentistId + '&status=Inactive';
                }
            });
    } else {
        // Reactivation - simple confirmation
        if (confirm(`Reactivate dentist "${dentistName}"?`)) {
            window.location.href = '<?= BASE_URL ?>/toggle_dentist_status?id=' + dentistId + '&status=Active';
        }
    }
}

function showReassignmentModal(data, dentistId) {
    const modal = document.getElementById('reassignmentModal');
    const subtitle = document.getElementById('modalSubtitle');
    const body = document.getElementById('modalBody');
    
    subtitle.textContent = `Deactivating Dr. ${data.dentistName}`;
    
    let html = `
        <div class="stats-box">
            <div class="stat-item stat-reassign">
                <div style="font-size: 2rem; font-weight: bold;">${data.reassignableCount}</div>
                <div style="font-size: 0.85rem;">Will be Reassigned</div>
            </div>
            <div class="stat-item stat-cancel">
                <div style="font-size: 2rem; font-weight: bold;">${data.cancellationCount}</div>
                <div style="font-size: 0.85rem;">Will be Cancelled</div>
            </div>
        </div>
    `;
    
    if (data.servicesWithoutAlternatives.length > 0) {
        html += `
            <div style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #ffc107;">
                <strong>⚠️ Warning:</strong> No alternative dentists found for: <strong>${data.servicesWithoutAlternatives.join(', ')}</strong>
            </div>
        `;
    }
    
    html += '<div style="max-height: 300px; overflow-y: auto;">';
    
    data.reassignmentPreview.forEach(appt => {
        const cssClass = appt.can_reassign ? 'reassignable' : 'cancellable';
        const icon = appt.can_reassign ? '✓' : '✗';
        const status = appt.can_reassign 
            ? `Will reassign (${appt.alternatives_count} option${appt.alternatives_count > 1 ? 's' : ''})`
            : 'Will cancel';
        
        html += `
            <div class="appointment-item ${cssClass}">
                <div>
                    <strong>${icon} ${appt.patient}</strong><br>
                    <small>${appt.service} | ${appt.date} at ${appt.time}</small>
                </div>
                <div style="text-align: right; font-size: 0.85rem;">
                    ${status}
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    html += `
        <div style="margin-top: 25px; display: flex; gap: 10px; justify-content: flex-end;">
            <button onclick="closeReassignmentModal()" 
                    style="padding: 12px 25px; background: #95a5a6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                Cancel
            </button>
            <button onclick="confirmDeactivation(${dentistId})" 
                    style="padding: 12px 25px; background: #e74c3c; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                Proceed with Deactivation
            </button>
        </div>
    `;
    
    body.innerHTML = html;
    modal.style.display = 'flex';
}

function closeReassignmentModal() {
    document.getElementById('reassignmentModal').style.display = 'none';
}

function confirmDeactivation(dentistId) {
    window.location.href = '<?= BASE_URL ?>/toggle_dentist_status?id=' + dentistId + '&status=Inactive';
}

// Close modal on outside click
document.getElementById('reassignmentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReassignmentModal();
    }
});

// Services management popup
function openServicesPopup(dentistId, dentistName) {
    const overlay = document.createElement('div');
    overlay.id = 'services-popup-overlay';
    overlay.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); display: flex;
        justify-content: center; align-items: center; z-index: 1000;
    `;
    
    const closePopup = () => {
        const el = document.getElementById('services-popup-overlay');
        if (el) document.body.removeChild(el);
    };

    overlay.onclick = (e) => { if (e.target === overlay) closePopup(); };

    const popup = document.createElement('div');
    popup.style.cssText = `
        background: white; padding: 30px; border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2); width: 90%;
        max-width: 500px;
    `;
    
    popup.innerHTML = `
        <h3 style="margin-top: 0; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            Manage Specializations for Dr. ${dentistName}
        </h3>
        
        <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px;">
            <button onclick="location.href='<?= BASE_URL ?>/dentist_specializations?dentist_id=${dentistId}&action=add'" 
                    style="padding: 12px 25px; background: #2ecc71; color: white; border: none; border-radius: 5px; cursor: pointer;">
                ➕ Add
            </button>
            <button onclick="location.href='<?= BASE_URL ?>/dentist_specializations?dentist_id=${dentistId}&action=remove'" 
                    style="padding: 12px 25px; background: #e74c3c; color: white; border: none; border-radius: 5px; cursor: pointer;">
                ❌ Remove
            </button>
        </div>
        
        <div style="margin-top: 25px; text-align: center;">
            <button onclick="closePopup()" 
                    style="padding: 8px 20px; background: #95a5a6; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Cancel
            </button>
        </div>
    `;
    
    overlay.appendChild(popup);
    document.body.appendChild(overlay);
}
</script>
</body>
</html>