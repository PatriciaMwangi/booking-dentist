<?php
require_once 'db.php';
$current_page = 'dentist';

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1>Direct access to this script is strictly prohibited.');
}

if (!isset($_SESSION['dentist_id'])) {
    header("Location: login.php");
    exit();
}

// Data for Modal Dropdowns
$patients_list = $pdo->query("SELECT patient_id, patient_name FROM patients ORDER BY patient_name ASC")->fetchAll();
$services_list = ["General Checkup", "Teeth Cleaning", "Tooth Extraction", "Dental Fillings"];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dentist Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
</head>
<body style="display: flex;">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <h2>Welcome, Dentist. <?= htmlspecialchars(preg_replace('/^Dr\.\s*/i', '', $_SESSION['dentist_name'] ?? 'Dentist')) ?> - Appointment Schedule</h2>
    <div id="calendar" class="card" style="padding: 20px; background: white;"></div>
</div>

<div id="appointmentModal" class="modal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
    <div class="registration-card" style="margin: 5% auto; max-width: 500px; background:white; padding:30px; border-radius:12px;">
        <h3>Add New Appointment</h3>
        <form action="add_appointment.php" method="POST">
            <div class="filter-group">
                <label>Date & Time</label>
                <input type="datetime-local" id="modal_date" name="appointment_datetime" required style="width:100%; padding:10px;">
            </div>
            
            <div class="filter-group" style="margin-top:15px;">
                <label>Patient</label>
                <select name="patient_id" required style="width:100%; padding:10px;">
                    <option value="">Select Patient</option>
                    <?php foreach($patients_list as $p): ?>
                        <option value="<?= $p['patient_id'] ?>"><?= htmlspecialchars($p['patient_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group" style="margin-top:15px;">
                <label>Service</label>
                <select name="service" required style="width:100%; padding:10px;">
                    <?php foreach($services_list as $s): ?>
                        <option value="<?= $s ?>"><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="hidden" name="dentist_id" value="<?= $_GET['dentist_id'] ?? $_SESSION['dentist_id'] ?>">

            <div class="filter-actions-group" style="margin-top:20px; display:flex; gap:10px;">
                <button type="submit" class="btn-primary">Save Appointment</button>
                <button type="button" class="btn-reset" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    if (!calendarEl) {
        console.error("Calendar element (#calendar) not found!");
        return;
    }

    // Store calendar globally for debugging
    window.calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        eventDisplay: 'block',      // Makes the event look like a solid blue bar/badge
    displayEventTime: false,    // Hides the "4:30a" text for a cleaner look
    height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        navLinks: false,
        selectable: true,
        
        select: function(info) {
            console.log("=== SELECTION EVENT ===");
            console.log("Current view:", calendar.view.type);
            console.log("Selection start:", info.startStr);
            console.log("Selection end:", info.endStr);
            
            const currentView = calendar.view.type;
            
            // Month or Week view: Go to 24-hour grid
            if (currentView === 'dayGridMonth' || currentView === 'timeGridWeek') {
                const targetDate = info.startStr.split('T')[0];
                console.log(`Navigating to 24-hour grid for: ${targetDate}`);
                
                try {
                    calendar.changeView('timeGridDay', targetDate);
                } catch (error) {
                    console.error("Failed to change view:", error);
                    // Fallback: alert user
                    alert(`Opening schedule for ${targetDate}`);
                }
            }
            
            // Day view: Book appointment
         else if (currentView === 'timeGridDay') {
    const start = new Date(info.startStr);
    const end = new Date(info.endStr);
    
    // Format times for display
    const startTimeString = start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const endTimeString = end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    // Populate Modal text
    document.getElementById('modalStartTime').innerText = startTimeString;
    document.getElementById('modalEndTime').innerText = endTimeString;
    
    // Show the modal
    const modal = document.getElementById('bookingModal');
    modal.style.display = 'flex';
    
    // Handle the "Proceed" button click
    document.getElementById('confirmBookingBtn').onclick = function() {
        const dentistId = "<?= htmlspecialchars($_GET['dentist_id'] ?? ($_SESSION['dentist_id'] ?? '')) ?>";
        const url = new URL('booking-dentist/index.php', window.location.origin);
        
        // Use clean ISO strings
        url.searchParams.set('start', info.startStr.slice(0, 16));
        url.searchParams.set('end', info.endStr.slice(0, 16));
        
        if (dentistId) {
            url.searchParams.set('dentist_id', dentistId);
        }
        
        window.location.href = url.toString();
    };
}

// Helper to close modal
 
            // Always clear selection
            calendar.unselect();
        },
        
        // Optional: Also handle simple clicks (not drag selections)
        dateClick: function(info) {
            console.log("Date clicked directly:", info.dateStr);
            
            if (calendar.view.type === 'dayGridMonth') {
                calendar.changeView('timeGridDay', info.dateStr);
            }
        },
        
        events: 'calendar.php<?= isset($_GET['dentist_id']) ? "?dentist_id=".urlencode($_GET['dentist_id']) : "" ?>'
    });

    calendar.render();
    console.log("Calendar ready! Try clicking empty spaces.");
    
    // Add debug helper
    console.log("Type 'calendar' in console to access the calendar object");
    console.log("Type 'calendar.changeView(\"timeGridDay\", \"2026-01-22\")' to test view switching");
});


function closeBookingModal() {
    document.getElementById('bookingModal').style.display = 'none';
    calendar.unselect();
}    
</script>
<div id="bookingModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div class="modal-content" style="background: white; padding: 25px; border-radius: 12px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
        <div style="font-size: 3rem; margin-bottom: 10px;">📅</div>
        <h3 style="margin-bottom: 15px; color: #2c3e50;">Confirm Appointment</h3>
        <p style="color: #7f8c8d; margin-bottom: 20px;">
            You are booking a slot from:<br>
            <strong id="modalStartTime" style="color: #3498db;"></strong> to <strong id="modalEndTime" style="color: #3498db;"></strong>
        </p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button onclick="closeBookingModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: #f8f9fa; border-radius: 6px; cursor: pointer;">Cancel</button>
            <button id="confirmBookingBtn" style="padding: 10px 20px; border: none; background: #2ecc71; color: white; border-radius: 6px; cursor: pointer; font-weight: bold;">Proceed to Book</button>
        </div>
    </div>
</div>
</body>
</html>