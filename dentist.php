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

<h2 id="calendar-title">
    Welcome, Dentist. <?= htmlspecialchars(preg_replace('/^Dr\.\s*/i', '', $_SESSION['dentist_name'] ?? 'Dentist')) ?> - Appointment Schedule
</h2>
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
       function updateWelcomeTitle() {
    const titleEl = document.getElementById('calendar-title');
    if (!titleEl) return;

    const urlParams = new URLSearchParams(window.location.search);
    const dentistId = urlParams.get('dentist_id');
    
    if (!dentistId) return;

    // Find by data attribute - much more reliable
    const matchingItem = document.querySelector(`.duty-item[data-dentist-id="${dentistId}"]`);
    
    if (matchingItem) {
        const nameElement = matchingItem.querySelector('.dentist-name');
        if (nameElement) {
            const foundName = nameElement.textContent.trim();
            const cleanName = foundName.replace(/^Dr\.?\s*/i, '');
            titleEl.textContent = `Welcome, Dentist. ${cleanName} - Appointment Schedule`;
            console.log("Title updated using data attribute:", titleEl.textContent);
            return;
        }
    }
    
    console.log("Could not find dentist name for ID:", dentistId);
    if (dentistId) {
        titleEl.textContent = "Welcome, Dentist - Appointment Schedule";
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    if (!calendarEl) {
        console.error("Calendar element (#calendar) not found!");
        return;
    }

    // Store calendar globally for debugging
    window.calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        eventDisplay: 'block',      
        displayEventTime: false,    
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        navLinks: false,
        selectable: true,
        unselectAuto: true,
        // --- 24-HOUR GRID DETAIL SETTINGS ---
        slotMinTime: '00:00:00',      // Start at midnight
        slotMaxTime: '24:00:00',      // End at midnight
        slotDuration: '00:10:00',     // Create 10-minute "slots" for booking
        slotLabelInterval: '00:30:00', // Only show text labels every hour to keep UI clean
        // ------------------------------------
        selectMirror: true,
        
        selectAllow: function(selectInfo) {
            const now = new Date();
            if (selectInfo.start < now) return false;

            // Dynamic Label Logic (Your existing code)
            setTimeout(() => {
                const mirrorEl = document.querySelector('.fc-timegrid-event-harness.fc-timegrid-mirror');
                if (mirrorEl) {
                    let timeLabel = mirrorEl.querySelector('.dynamic-time-label');
                    if (!timeLabel) {
                        timeLabel = document.createElement('small');
                        timeLabel.className = 'dynamic-time-label';
                        timeLabel.style = "display: block; background: rgba(0,0,0,0.8); color: white; padding: 4px 8px; border-radius: 4px; position: absolute; top: 5px; left: 5px; z-index: 100; pointer-events: none; font-weight: bold;";
                        mirrorEl.appendChild(timeLabel);
                    }
                    const start = new Date(selectInfo.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const end = new Date(selectInfo.end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    timeLabel.textContent = `${start} - ${end}`;
                }
            }, 0);
            return true;
        },
        
        dateClick: function(info) {
            const dateStr = info.dateStr;
            console.log("Date clicked:", dateStr);
            
            // Show duty status in sidebar
            loadDutyStatus(dateStr);
            
            // If in month view, switch to day view
            if (calendar.view.type === 'dayGridMonth') {
                calendar.changeView('timeGridDay', dateStr);
            }
        },
        
        datesSet: function(dateInfo) {
            // 1. Get the current active date from the calendar instance
            const activeDate = window.calendar.getDate(); 
            
            // 2. Format to YYYY-MM-DD manually to avoid UTC/timezone shifts
            const year = activeDate.getFullYear();
            const month = String(activeDate.getMonth() + 1).padStart(2, '0');
            const day = String(activeDate.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${day}`;
            
            console.log("Synchronizing Sidebar to Calendar Date:", dateStr);

            const sidebarHeading = document.querySelector('.sidebar-date-heading') || 
                                  document.querySelector('.card-body h6'); // Adjust selector to your sidebar's date ID
            if (sidebarHeading) {
                sidebarHeading.textContent = dateStr;
            }

            // 4. Trigger the AJAX fetch for the correct day
            if (typeof loadDutyStatus === 'function') {
                loadDutyStatus(dateStr);
            }
        },
        
        select: function(info) {
            const currentView = calendar.view.type;
            
            // Month or Week view: Go to 24-hour grid
            if (currentView === 'dayGridMonth' || currentView === 'timeGridWeek') {
                const targetDate = info.startStr.split('T')[0];
                console.log(`Navigating to 24-hour grid for: ${targetDate}`);
                
                // Load duty status for the selected date
                loadDutyStatus(targetDate);
                
                try {
                    calendar.changeView('timeGridDay', targetDate);
                } catch (error) {
                    console.error("Failed to change view:", error);
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
                const modalStartEl = document.getElementById('modalStartTime');
                const modalEndEl = document.getElementById('modalEndTime');
                if (modalStartEl) modalStartEl.innerText = startTimeString;
                if (modalEndEl) modalEndEl.innerText = endTimeString;
                
                // Show the modal
                const modal = document.getElementById('bookingModal');
                if (modal) {
                    modal.style.display = 'flex';
                }
                
                // Handle the "Proceed" button click
                const confirmBtn = document.getElementById('confirmBookingBtn');
                if (confirmBtn) {
                    confirmBtn.onclick = function() {
                        const baseUrl = "<?= BASE_URL ?>"; 
                        // Ensure path points to the booking script
                        const url = new URL(baseUrl + '/', window.location.origin);
                        
                        // 1. Pass the exact calendar selection (10-min slots supported)
                        url.searchParams.set('start', info.startStr.slice(0, 16));
                        url.searchParams.set('end', info.endStr.slice(0, 16));
                        
                        // 2. PRIORITIZE: URL dentist_id > Session dentist_id
                        const urlParams = new URLSearchParams(window.location.search);
                        const dentistIdFromUrl = urlParams.get('dentist_id');
                        const sessionDentistId = "<?= $_SESSION['dentist_id'] ?? '' ?>";
                        
                        const finalDentistId = dentistIdFromUrl || sessionDentistId;
                        
                        if (finalDentistId) {
                            url.searchParams.set('dentist_id', finalDentistId);
                        }
                        
                        window.location.href = url.toString();
                    };
                }
            }
            
            // Always clear selection
            calendar.unselect();
        },
        
        events: '<?= BASE_URL ?>/calendar-data<?= isset($_GET['dentist_id']) ? "?dentist_id=".urlencode($_GET['dentist_id']) : "" ?>'
    });

    calendar.render();
    
    // REMOVED: updateWelcomeTitle(); // Don't call here - duty lists aren't loaded yet
    
    console.log("Calendar ready! Click on any date to see duty status.");
});

// Function to load duty status via AJAX
function loadDutyStatus(dateStr) {
    console.log("Loading duty status for:", dateStr);
    
    // Show the container
    const container = document.getElementById('duty-status-container');
    const calendarContainer = document.getElementById('calendar-duty-container');
    
    if (container) {
        container.style.display = 'block';
        document.getElementById('selected-date').textContent = dateStr;
    }
    
    if (calendarContainer) {
        calendarContainer.style.display = 'block';
    }
    
    // Build the URL - IMPORTANT: Use the correct endpoint
    const url = `<?= BASE_URL ?>/get_duty_status.php?date=${dateStr}`;
    console.log("Fetching from:", url);
    
    // Fetch duty data via AJAX
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log("Received data:", data);
            updateDutyLists(data);
        })
        .catch(error => {
            console.error('Error fetching duty status:', error);
            
            // Fallback to using calendar events
            updateFromCalendarEvents(dateStr);
        });
}

// Function to update duty lists from AJAX data
function updateDutyLists(data) {
    const onDutyCountBadge = document.getElementById('on-duty-count');
    const offDutyCountBadge = document.getElementById('off-duty-count');
    const onDutyList = document.getElementById('on-duty-list');
    const offDutyList = document.getElementById('off-duty-list');
    const searchInput = document.getElementById('duty-search');
    
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.duty-item');

            items.forEach(item => {
                const name = item.querySelector('.dentist-name').textContent.toLowerCase();
                if (name.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
    
    // 1. Update Badge Counts
    if (onDutyCountBadge) {
        const val = data.totalOnDuty ?? (data.onDuty ? data.onDuty.length : 0);
        onDutyCountBadge.textContent = val;
    }

    if (offDutyCountBadge) {
        const val = data.totalOffDuty ?? (data.offDuty ? data.offDuty.length : 0);
        offDutyCountBadge.textContent = val;
    }

    // 2. Render On-Duty List with Dynamic Links
       if (onDutyList) {
        if (data.onDuty && data.onDuty.length > 0) {
            onDutyList.innerHTML = data.onDuty.map(d => `
                <div class="duty-item" data-dentist-id="${d.id}" style="padding: 8px 10px; margin-bottom: 5px; background: #f8f9fa; border-radius: 4px; border-left: 3px solid #27ae60; display: flex; justify-content: space-between; align-items: center;">
                    <a href="calendar?dentist_id=${d.id}" class="dentist-link" style="text-decoration: none; color: inherit; flex-grow: 1;">
                        <span class="dentist-name" style="font-weight: 500; color: #2c3e50; cursor: pointer; border-bottom: 1px dashed transparent;" 
                              onmouseover="this.style.borderBottom='1px dashed #27ae60'" 
                              onmouseout="this.style.borderBottom='1px dashed transparent'">
                            ${d.name}
                        </span>
                    </a>
                    <span style="font-size: 11px; color: #7f8c8d; margin-left: 10px;">${d.appointments} appt</span>
                </div>`).join('');
        } else {
            onDutyList.innerHTML = '<div class="no-duty" style="text-align:center; color:#95a5a6; padding:10px;">No one on duty</div>';
        }
    }

    // 3. Render Off-Duty List with Dynamic Links
    if (offDutyList) {
        if (data.offDuty && data.offDuty.length > 0) {
            offDutyList.innerHTML = data.offDuty.map(d => `
                <div class="duty-item" data-dentist-id="${d.id}" style="padding: 8px 10px; margin-bottom: 5px; background: #fff5f5; border-radius: 4px; border-left: 3px solid #e74c3c; opacity: 0.8; display: flex; align-items: center;">
                    <a href="calendar?dentist_id=${d.id}" style="text-decoration: none; color: inherit; width: 100%;">
                        <span class="dentist-name" style="font-weight: 500; color: #2c3e50; cursor: pointer;">
                            ${d.name}
                        </span>
                    </a>
                </div>`).join('');
        } else {
            offDutyList.innerHTML = '<div class="no-duty" style="text-align:center; color:#95a5a6; padding:10px;">Everyone is on duty</div>';
        }
    }
    
    if (searchInput) searchInput.value = '';
    
    // Now update the title AFTER duty lists are populated
    setTimeout(updateWelcomeTitle(), 100);
}

// Fallback function to extract duty from calendar events
function updateFromCalendarEvents(dateStr) {
    if (!window.calendar) return;
    
    const events = window.calendar.getEvents().filter(e => e.startStr.startsWith(dateStr));
    const onDutyNames = [...new Set(events.map(e => e.title.split(':')[0].trim()))];
    
    const onDutyList = document.getElementById('on-duty-list');
    const offDutyList = document.getElementById('off-duty-list');
    const onDutyCount = document.getElementById('on-duty-count');
    const offDutyCount = document.getElementById('off-duty-count');
    
    const searchInput = document.getElementById('duty-search');
    
    // Update title after fallback
    setTimeout(updateWelcomeTitle(), 100);
}

// Function to close booking modal
function closeBookingModal() {
    const modal = document.getElementById('bookingModal');
    if (modal) {
        modal.style.display = 'none';
    }
    if (window.calendar) {
        window.calendar.unselect();
    }
}

// Load initial duty status and update title
window.addEventListener('load', function() {
    // Initial load of duty status for today
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;
    
    // Load duty status and update title
    if (typeof loadDutyStatus === 'function') {
        loadDutyStatus(dateStr);
    } else {
        // Fallback: update title after a short delay
        setTimeout(updateWelcomeTitle(), 500);
    }
});
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