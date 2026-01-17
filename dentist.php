<?php
require_once 'db.php';
$current_page = 'dentist';

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
    <h2>Dentist Appointment Schedule</h2>
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
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        navLinks: true, 
        selectable: true,
        
        navLinkDayClick: function(date, jsEvent) {
            calendar.changeView('timeGridDay', date);
        },

        // Triggered when clicking a 24-hour slot
        select: function(info) {
            // Encode the date and time to pass in the URL
            const startStr = info.startStr; // Example: "2026-01-08T10:30:00"
            const dentistId = "<?= $_GET['dentist_id'] ?? $_SESSION['dentist_id'] ?>";
            
            // Redirect to your original booking page with parameters
            window.location.href = `index.php?start=${encodeURIComponent(startStr)}&dentist_id=${dentistId}`;
        },

        events: 'calendar.php<?= isset($_GET['dentist_id']) ? "?dentist_id=".$_GET['dentist_id'] : "" ?>'
    });
    calendar.render();
});

function openAppointmentModal(dateStr) {
    const modal = document.getElementById('appointmentModal');
    const dateInput = document.getElementById('modal_date');
    if(dateStr) {
        // Adjusts for local time to prevent date shifting
        let date = new Date(dateStr);
        let offset = date.getTimezoneOffset() * 60000;
        let localISOTime = (new Date(date - offset)).toISOString().slice(0, 16);
        dateInput.value = localISOTime;
    }
    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('appointmentModal').style.display = 'none';
}
</script>

</body>
</html>