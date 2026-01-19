<?php
require_once 'db.php';
$current_page = 'schedule';

if (!isset($_SESSION['dentist_id'])) {
    header("Location: login.php");
    exit();
}

// Handle Status and Date Updates
if (isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['status'];
    
    // 1. Get the NEW keys from the modal form
    $new_start = $_POST['appointment_start']; 
    $new_end   = $_POST['appointment_end'];   
    
    $session_dentist_id = $_SESSION['dentist_id'] ?? null;
    $is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent');

    if ($is_super) {
        // 2. Update both start_time and end_time
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, start_time = ?, end_time = ? WHERE id = ?");
        $stmt->execute([$new_status, $new_start, $new_end, $appointment_id]);
    } else if ($session_dentist_id) {
        // 2. Securely update with dentist ownership check
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, start_time = ?, end_time = ? WHERE id = ? AND dentist_id = ?");
        $stmt->execute([$new_status, $new_start, $new_end, $appointment_id, $session_dentist_id]);
    }

    // 3. Redirect to see the fresh values immediately
    header("Location: schedule.php?updated_id=" . urlencode($appointment_id));
    exit();
}
// --- FILTER LOGIC ---
// --- CLEANED FILTER LOGIC ---
$filter_dentists = $_GET['dentists'] ?? [];
$show_all = isset($_GET['show_all']); 
$session_id = $_SESSION['dentist_id'];
$filter_services = $_GET['services'] ?? [];
$start_date = $_GET['start_date'] ?? ''; 
$end_date = $_GET['end_date'] ?? '';     
$search_query = $_GET['search'] ?? ''; 

$query_parts = ["1=1"]; 
$params = [];

$sort_order = "DESC"; 
if (isset($_GET['sort_order']) && $_GET['sort_order'] === 'ASC') {
    $sort_order = "ASC";
}
// 1. Dentist Logic: Default to ME, or use Checkboxes, or Show All
if (!$show_all && empty($filter_dentists)) {
    $query_parts[] = "a.dentist_id = ?";
    $params[] = $session_id;
} elseif (!empty($filter_dentists)) {
    $placeholders = str_repeat('?,', count($filter_dentists) - 1) . '?';
    $query_parts[] = "a.dentist_id IN ($placeholders)";
    foreach($filter_dentists as $id) $params[] = $id;
}

// 2. Search Filter
if (!empty($search_query)) {
    $query_parts[] = "(p.patient_name LIKE ? OR p.phone LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

// 3. Date Range Filter
if (!empty($start_date) && !empty($end_date)) {
    $query_parts[] = "DATE(a.start_time) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
} elseif (!empty($start_date)) {
    $query_parts[] = "DATE(a.start_time) = ?";
    $params[] = $start_date;
}

// 4. Service Filter
if (!empty($filter_services)) {
    $placeholders = str_repeat('?,', count($filter_services) - 1) . '?';
    $query_parts[] = "a.service IN ($placeholders)";
    foreach($filter_services as $service) $params[] = $service;
}

if (!empty($_GET['status'])) {
    $query_parts[] = "a.status = ?";
    $params[] = $_GET['status'];
}

$sql = "SELECT 
            a.id, 
            a.start_time, 
            a.end_time, 
            p.patient_name, 
            p.phone, 
            a.service, 
            d.dentist_name, 
            d.dentist_id, 
            a.status 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN dentists d ON a.dentist_id = d.dentist_id
        WHERE " . implode(' AND ', $query_parts) . "
        ORDER BY a.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$dentists_list = $pdo->query("SELECT dentist_id, dentist_name FROM dentists")->fetchAll();
?>
<head>
        <link rel="stylesheet" href="style.css">
</head>
    
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <h2>Clinic Appointment Schedule</h2>
    <div class="filter-section card">
    <form method="GET" action="schedule.php" class="date-filter-form">
    <div class="filter-group" style="flex: 1.5;">
        <label>Search Patient:</label>
        <input type="text" name="search" placeholder="Name or Phone number..." 
               value="<?= htmlspecialchars($search_query) ?>" 
               style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
    </div>
    
    <!-- <div class="filter-group">
        <label>Status:</label>
        <select name="status" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; background: white;">
            <option value="">All Statuses</option>
            <option value="Pending" <?= (isset($_GET['status']) && $_GET['status'] == 'Pending') ? 'selected' : '' ?>>Pending</option>
            <option value="Completed" <?= (isset($_GET['status']) && $_GET['status'] == 'Completed') ? 'selected' : '' ?>>Completed</option>
            <option value="Cancelled" <?= (isset($_GET['status']) && $_GET['status'] == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
        </select>
    </div> -->
    <div class="filter-group">
    <label>Sort By:</label>
    <select name="sort_order" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; background: white;">
        <option value="DESC" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] == 'DESC') ? 'selected' : '' ?>>Latest First</option>
        <option value="ASC" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] == 'ASC') ? 'selected' : '' ?>>Earliest First</option>
    </select>
</div>

    <div class="filter-group">
        <label>From:</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
    </div>
    <div class="filter-group">
        <label>To:</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn-primary" style="padding: 8px 20px; width: auto; font-size: 0.9rem;">
            APPLY FILTERS
        </button>
        <a href="schedule.php" class="btn-reset">Clear</a>
    </div>
</form>
</div>

<table class="appointments-table">
    <thead>
        <tr>
            <th>Date & Time</th>
            <th>Patient</th>
            <th>Service</th>
            <th>Dentist</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
<tbody>
    <?php while($row = $stmt->fetch()): 
        $session_dentist_id = isset($_SESSION['dentist_id']) ? (int)$_SESSION['dentist_id'] : 0;
        $row_dentist_id = (int)$row['dentist_id'];
        $is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent');
        
        $is_updated = (isset($_GET['updated_id']) && $_GET['updated_id'] == $row['id']) ? 'updated-row' : '';

        $start_dt = new DateTime($row['start_time']);
        $end_dt   = new DateTime($row['end_time']);
        
        $start_input = $start_dt->format('Y-m-d\TH:i');
        $end_input   = $end_dt->format('Y-m-d\TH:i');
        
        $status_colors = ['Pending' => '#f39c12', 'Completed' => '#27ae60', 'Cancelled' => '#e74c3c'];
        $status_color = $status_colors[$row['status']] ?? '#95a5a6';
    ?>
    <tr class="<?= $is_updated ?> appointment-row"> 
        <td class="datetime-cell">
            <div class="date-display"><?= $start_dt->format('D, M j, Y') ?></div>
            <div class="time-display">⏰ <?= $start_dt->format('h:i A') ?> - <?= $end_dt->format('h:i A') ?></div>
        </td>

        <td class="patient-cell">
            <div class="patient-name"><?= htmlspecialchars($row['patient_name']) ?></div>
            <div class="patient-phone">📞 <?= htmlspecialchars($row['phone']) ?></div>
        </td>
        
        <td><span class="service-badge"><?= htmlspecialchars($row['service']) ?></span></td>
        
        <td class="dentist-cell">
            <div class="dentist-name">👩‍⚕️ Dr. <?= htmlspecialchars(preg_replace('/^Dr\.\s*/i', '', $row['dentist_name'])) ?></div>
        </td>
        
        <td>
            <span class="status-badge" style="background-color: <?= $status_color ?>20; color: <?= $status_color ?>; border-color: <?= $status_color ?>;">
                <?= htmlspecialchars($row['status']) ?>
            </span>
        </td>

<td class="action-cell">
    <?php if ($is_super || $row_dentist_id === $session_dentist_id): ?>
        <button type="button" class="edit-trigger-btn" 
            onclick="openEditModal('<?= $row['id'] ?>', '<?= $start_input ?>', '<?= $end_input ?>', '<?= $row['status'] ?>')">
            ✏️ Edit
        </button>
    <?php else: ?>
        <div class="view-only">👁️ View Only</div>
    <?php endif; ?>
</td>
    </tr>
    <?php endwhile; ?>
    <div id="editAppointmentModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; justify-content: center; align-items: center;">
    <div class="modal-content" style="background: white; padding: 25px; border-radius: 15px; width: 90%; max-width: 450px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #2c3e50;">✏️ Edit Appointment</h3>
            <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #95a5a6;">&times;</button>
        </div>
        
        <form method="POST" id="modalEditForm">
            <input type="hidden" name="appointment_id" id="modal_appt_id">
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Start Date & Time</label>
                <input type="datetime-local" name="appointment_start" id="modal_start" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">End Date & Time</label>
                <input type="datetime-local" name="appointment_end" id="modal_end" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Status</label>
                <select name="status" id="modal_status" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; background: white;">
                    <option value="Pending">Pending</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="closeEditModal()" style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px; background: #f8f9fa; cursor: pointer;">Cancel</button>
                <button type="submit" name="update_status" style="flex: 2; padding: 12px; border: none; border-radius: 8px; background: #2ecc71; color: white; font-weight: 600; cursor: pointer;">Save Changes</button>
            </div>
        </form>
    </div>
</div>
</tbody>
</table>



<script>

// 1. GLOBAL SCOPE FUNCTIONS (Accessible by onclick attributes)
function openEditModal(id, start, end, status) {
    document.getElementById('modal_appt_id').value = id;
    document.getElementById('modal_start').value = start;
    document.getElementById('modal_end').value = end;
    document.getElementById('modal_status').value = status;
    
    document.getElementById('editAppointmentModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editAppointmentModal').style.display = 'none';
}

// Close modal if clicking outside the white box
window.onclick = function(event) {
    const modal = document.getElementById('editAppointmentModal');
    if (event.target == modal) {
        closeEditModal();
    }
}

// 2. DOM CONTENT LOADED (For validation and initialization)
document.addEventListener('DOMContentLoaded', function() {
    const modalForm = document.getElementById('modalEditForm');
    
    if (modalForm) {
        const startInput = document.getElementById('modal_start');
        const endInput = document.getElementById('modal_end');

        // Validate time order before submission
        modalForm.addEventListener('submit', function(e) {
            const start = new Date(startInput.value);
            const end = new Date(endInput.value);
            
            if (start >= end) {
                e.preventDefault();
                alert('⚠️ Invalid Time: The end time must be after the start time.');
                endInput.style.borderColor = '#e74c3c';
                return false;
            }

            // Show loading state on the submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '⏳ Saving...';
            submitBtn.disabled = true;
        });

        // Reset border color when user fixes the time
        [startInput, endInput].forEach(input => {
            input.addEventListener('change', () => {
                startInput.style.borderColor = '#ddd';
                endInput.style.borderColor = '#ddd';
            });
        });
    }
});
</script>
</div>