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
        <form action="book_appointment.php" method="POST">
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

// Global variables for appointment navigation
let allAppointments = [];
let currentAppointmentIndex = -1;

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    if (!calendarEl) {
        console.error("Calendar element (#calendar) not found!");
        return;
    }

    // Store calendar globally for debugging
    window.calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        eventDisplay: 'auto',      
        displayEventTime: true,
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        navLinks: false,
        selectable: true,
        unselectAuto: true,
        
        // --- 24-HOUR GRID SETTINGS ---
        slotMinTime: '00:00:00',
        slotMaxTime: '24:00:00',
        slotDuration: '00:10:00',
        slotLabelInterval: '01:00:00',
        
        selectMirror: true,
        
        // Event rendering
        eventContent: function(arg) {
            // Hide count events (badges are handled separately)
            if (arg.event.extendedProps.is_count_event) {
                return { html: '' };
            }
            
            // For appointment events, show in day/week view only
            const view = window.calendar.view.type;
            if (view === 'dayGridMonth') {
                return { html: '' }; // Hide in month view
            }
            
            // Show in day/week view
            return {
                html: `<div class="fc-event-main-frame" style="padding: 2px 4px;">
                    <div class="fc-event-time">${arg.timeText}</div>
                    <div class="fc-event-title">${arg.event.title}</div>
                </div>`
            };
        },
        
        // Customize day cell content (for month view badges)
        dayCellContent: function(arg) {
            const view = window.calendar.view.type;
            
            // Only show badges in month view
            if (view !== 'dayGridMonth') {
                return; // Use default rendering for other views
            }
            
            let container = document.createElement('div');
            container.className = 'fc-daygrid-day-frame';
            container.style.cssText = `
                display: flex;
                flex-direction: column;
                height: 100%;
                width: 100%;
                position: relative;
            `;
            
            // Add day number
            let dayNumber = document.createElement('div');
            dayNumber.className = 'fc-daygrid-day-number';
            dayNumber.innerHTML = arg.dayNumberText.replace(/th|st|nd|rd/g, '');
            dayNumber.style.cssText = `
                align-self: flex-start;
                padding: 2px;
                font-size: 12px;
                font-weight: bold;
            `;
            
            // Badge container
            let badgeContainer = document.createElement('div');
            badgeContainer.style.cssText = `
                flex-grow: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                margin-top: 2px;
            `;
            
            // Get date in local timezone
            const year = arg.date.getFullYear();
            const month = String(arg.date.getMonth() + 1).padStart(2, '0');
            const day = String(arg.date.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${day}`;
            
            // Find count event for this date
            const events = window.calendar.getEvents();
            const countEvent = events.find(e => {
                const eventStart = e.startStr ? e.startStr.split('T')[0] : '';
                return eventStart === dateStr && e.extendedProps && e.extendedProps.is_count_event;
            });
            
            if (countEvent) {
                const count = countEvent.extendedProps.appointment_count;
                const badgeColor = countEvent.extendedProps.badge_color || '#3498db';
                
                let badge = document.createElement('div');
                badge.className = 'count-badge';
                badge.innerText = count;
                badge.title = `${count} appointment${count > 1 ? 's' : ''}`;
                badge.style.cssText = `
                    background-color: ${badgeColor};
                    color: white;
                    font-size: 11px;
                    font-weight: bold;
                    padding: 4px 8px;
                    border-radius: 12px;
                    min-width: 24px;
                    text-align: center;
                    display: inline-block;
                    cursor: pointer;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                    transition: transform 0.2s;
                `;
                
                badge.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1)';
                });
                badge.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
                
                badgeContainer.appendChild(badge);
            }
            
            container.appendChild(dayNumber);
            container.appendChild(badgeContainer);
            
            return { domNodes: [container] };
        },
        
        // Handle event clicks (for appointments)
        eventClick: function(info) {
            // Don't handle count events
            if (info.event.extendedProps.is_count_event) {
                return;
            }
            
            // Show appointment details
            showAppointmentDetailsModal(info.event);
            
            // Prevent default navigation
            info.jsEvent.preventDefault();
        },
        
        datesSet: function(arg) {
            const activeDate = window.calendar.getDate(); 
            const year = activeDate.getFullYear();
            const month = String(activeDate.getMonth() + 1).padStart(2, '0');
            const day = String(activeDate.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${day}`;
            
            const sidebarHeading = document.querySelector('.sidebar-date-heading') || 
                                  document.querySelector('.card-body h6');
            if (sidebarHeading) {
                sidebarHeading.textContent = dateStr;
            }

            if (typeof loadDutyStatus === 'function') {
                loadDutyStatus(dateStr);
            }
            
            setTimeout(() => {
                window.calendar.render();
            }, 100);
        },
        
        eventSourceSuccess: function(content, response) {
            console.log('Events loaded:', content.length);
            setTimeout(() => {
                window.calendar.render();
            }, 50);
            return content;
        },

        selectAllow: function(selectInfo) {
            const now = new Date();
            if (selectInfo.start < now) return false;

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
            
            if (typeof loadDutyStatus === 'function') {
                loadDutyStatus(dateStr);
            }
            
            if (calendar.view.type === 'dayGridMonth') {
                calendar.changeView('timeGridDay', dateStr);
            }
        },
        
        select: function(info) {
            const currentView = calendar.view.type;
            
            if (currentView === 'dayGridMonth' || currentView === 'timeGridWeek') {
                const targetDate = info.startStr.split('T')[0];
                console.log(`Navigating to 24-hour grid for: ${targetDate}`);
                
                if (typeof loadDutyStatus === 'function') {
                    loadDutyStatus(targetDate);
                }
                
                try {
                    calendar.changeView('timeGridDay', targetDate);
                } catch (error) {
                    console.error("Failed to change view:", error);
                }
            } else if (currentView === 'timeGridDay') {
                const start = new Date(info.startStr);
                const end = new Date(info.endStr);
                
                const startTimeString = start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const endTimeString = end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                const modalStartEl = document.getElementById('modalStartTime');
                const modalEndEl = document.getElementById('modalEndTime');
                if (modalStartEl) modalStartEl.innerText = startTimeString;
                if (modalEndEl) modalEndEl.innerText = endTimeString;
                
                const modal = document.getElementById('bookingModal');
                if (modal) {
                    modal.style.display = 'flex';
                }
                
                const confirmBtn = document.getElementById('confirmBookingBtn');
                if (confirmBtn) {
                    confirmBtn.onclick = function() {
        const baseUrl = window.BASE_URL || '<?= BASE_URL ?>' || '';
                        const url = new URL(baseUrl + '/', window.location.origin);
                        
                        url.searchParams.set('start', info.startStr.slice(0, 16));
                        url.searchParams.set('end', info.endStr.slice(0, 16));
                        
                        const urlParams = new URLSearchParams(window.location.search);
                        const dentistIdFromUrl = urlParams.get('dentist_id');
                        const sessionDentistId = window.sessionDentistId || '';
                        
                        const finalDentistId = dentistIdFromUrl || sessionDentistId;
                        
                        if (finalDentistId) {
                            url.searchParams.set('dentist_id', finalDentistId);
                        }
                        
                        window.location.href = url.toString();
                    };
                }
            }
            
            calendar.unselect();
        },
        
        events: function(info, successCallback, failureCallback) {
                const baseUrl = window.BASE_URL || '<?= BASE_URL ?>' || '';
            const urlParams = new URLSearchParams(window.location.search);
            const dentistId = urlParams.get('dentist_id');
            
    let url = baseUrl + '/calendar-data';
            if (dentistId) {
                url += '?dentist_id=' + encodeURIComponent(dentistId);
            }
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    console.log('Fetched events:', data);
                    successCallback(data);
                })
                .catch(error => {
                    console.error('Error fetching events:', error);
                    failureCallback(error);
                });
        }
    });

    calendar.render();
    console.log("Calendar ready!");
});

// ============================================
// APPOINTMENT MODAL WITH NAVIGATION
// ============================================

function showAppointmentDetailsModal(event) {
    const props = event.extendedProps;
    
    // Get all appointment events (not count events)
    const calendar = window.calendar;
    if (calendar) {
        allAppointments = calendar.getEvents().filter(e => 
            e.extendedProps && e.extendedProps.is_appointment
        );
        
        // Sort by start time
        allAppointments.sort((a, b) => {
            return new Date(a.start) - new Date(b.start);
        });
        
        // Find current appointment index
        currentAppointmentIndex = allAppointments.findIndex(e => 
            e.extendedProps.appointment_id === props.appointment_id
        );
    }
    
    // Create modal if it doesn't exist
    let modal = document.getElementById('appointmentDetailsModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'appointmentDetailsModal';
        modal.className = 'modal-overlay';
        modal.style.cssText = `
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        `;
        document.body.appendChild(modal);
    }
    
    // Render the modal content
    renderAppointmentModal(event);
    modal.style.display = 'flex';
}

function renderAppointmentModal(event) {
    const modal = document.getElementById('appointmentDetailsModal');
    if (!modal) return;
    
    const props = event.extendedProps;
    
    // Format times
    const startTime = new Date(event.start);
    const endTime = new Date(event.end);
    const timeString = `${startTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})} - ${endTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}`;
    const dateString = startTime.toLocaleDateString('en-US', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'});
    
    // Status badge color
    const statusColors = {
        'Pending': '#f39c12',
        'Completed': '#27ae60',
        'Cancelled': '#e74c3c'
    };
    const statusColor = statusColors[props.status] || '#3498db';
    
    // Build dentist section
    let dentistSection = '';
    if (props.is_superintendent) {
        dentistSection = `
            <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                <span style="font-size: 24px;">👨‍⚕️</span>
                <div style="flex: 1;">
                    <div style="font-size: 12px; color: #7f8c8d; font-weight: 600;">DENTIST</div>
                    <div style="font-size: 16px; color: #2c3e50; font-weight: 600;">Dr. ${props.dentist_name}</div>
                </div>
            </div>
        `;
    }
    
    // Navigation buttons state
    const hasPrevious = currentAppointmentIndex > 0;
    const hasNext = currentAppointmentIndex < allAppointments.length - 1;
    
    const prevButtonStyle = hasPrevious 
        ? 'background: rgba(255,255,255,0.2); cursor: pointer; opacity: 1;' 
        : 'background: rgba(255,255,255,0.1); cursor: not-allowed; opacity: 0.4;';
    
    const nextButtonStyle = hasNext 
        ? 'background: rgba(255,255,255,0.2); cursor: pointer; opacity: 1;' 
        : 'background: rgba(255,255,255,0.1); cursor: not-allowed; opacity: 0.4;';
    
    modal.innerHTML = `
        <div style="background: white; padding: 0; border-radius: 15px; width: 90%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); overflow: hidden;">
            <!-- Header with Navigation -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; color: white;">
                <div style="display: flex; justify-content: space-between; align-items: start; gap: 15px;">
                    <!-- Previous Button -->
                    <button 
                        onclick="navigateToPreviousAppointment()" 
                        ${!hasPrevious ? 'disabled' : ''}
                        style="${prevButtonStyle} border: none; color: white; font-size: 20px; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s; flex-shrink: 0;"
                        title="Previous Appointment">
                        ‹
                    </button>
                    
                    <!-- Title -->
                    <div style="flex: 1; text-align: center;">
                        <h3 style="margin: 0 0 5px 0; font-size: 24px;">📋 Appointment Details</h3>
                        <div style="font-size: 14px; opacity: 0.9;">#${props.appointment_id}</div>
                        <div style="font-size: 12px; opacity: 0.7; margin-top: 5px;">
                            ${currentAppointmentIndex + 1} of ${allAppointments.length}
                        </div>
                    </div>
                    
                    <!-- Next Button -->
                    <button 
                        onclick="navigateToNextAppointment()" 
                        ${!hasNext ? 'disabled' : ''}
                        style="${nextButtonStyle} border: none; color: white; font-size: 20px; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s; flex-shrink: 0;"
                        title="Next Appointment">
                        ›
                    </button>
                    
                    <!-- Close Button -->
                    <button 
                        onclick="closeAppointmentDetailsModal()" 
                        style="background: rgba(255,255,255,0.2); border: none; color: white; font-size: 24px; cursor: pointer; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background 0.3s; flex-shrink: 0;"
                        title="Close">
                        ×
                    </button>
                </div>
            </div>
            
            <!-- Content -->
            <div style="padding: 25px;">
                <!-- Date & Time -->
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <span style="font-size: 24px;">📅</span>
                        <div style="flex: 1;">
                            <div style="font-size: 12px; color: #7f8c8d; font-weight: 600;">DATE & TIME</div>
                            <div style="font-size: 16px; color: #2c3e50; font-weight: 600;">${dateString}</div>
                            <div style="font-size: 14px; color: #7f8c8d; margin-top: 2px;">⏰ ${timeString}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Patient -->
                <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px;">
                    <span style="font-size: 24px;">👤</span>
                    <div style="flex: 1;">
                        <div style="font-size: 12px; color: #7f8c8d; font-weight: 600;">PATIENT</div>
                        <div style="font-size: 16px; color: #2c3e50; font-weight: 600;">${props.patient_name}</div>
                        <div style="font-size: 14px; color: #7f8c8d;">📞 ${props.phone}</div>
                    </div>
                </div>
                
                ${dentistSection}
                
                <!-- Service -->
                <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px; ${dentistSection ? 'margin-top: 15px;' : ''}">
                    <span style="font-size: 24px;">🦷</span>
                    <div style="flex: 1;">
                        <div style="font-size: 12px; color: #7f8c8d; font-weight: 600;">SERVICE</div>
                        <div style="font-size: 16px; color: #2c3e50; font-weight: 600;">${props.service}</div>
                    </div>
                </div>
                
                <!-- Status -->
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">📊</span>
                    <div style="flex: 1;">
                        <div style="font-size: 12px; color: #7f8c8d; font-weight: 600; margin-bottom: 5px;">STATUS</div>
                        <span style="background-color: ${statusColor}20; color: ${statusColor}; padding: 6px 16px; border-radius: 20px; font-weight: 600; font-size: 14px; border: 2px solid ${statusColor}; display: inline-block;">
                            ${props.status}
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div style="padding: 20px 25px; background: #f8f9fa; border-top: 1px solid #e0e0e0; display: flex; gap: 10px; justify-content: space-between; align-items: center;">
                <!-- Keyboard Hint -->
                <div style="font-size: 12px; color: #95a5a6;">
                    💡 Use arrow keys to navigate
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 10px;">
                    <button onclick="closeAppointmentDetailsModal()" style="padding: 10px 20px; border: 1px solid #ddd; border-radius: 8px; background: white; cursor: pointer; font-weight: 600; color: #7f8c8d; transition: all 0.3s;">
                        Close
                    </button>
                    <button onclick="window.location.href='schedule.php?updated_id=${props.appointment_id}'" style="padding: 10px 20px; border: none; border-radius: 8px; background: #3498db; color: white; cursor: pointer; font-weight: 600; transition: all 0.3s;">
                        View in Schedule
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Add hover effects
    const buttons = modal.querySelectorAll('button');
    buttons.forEach(button => {
        if (!button.disabled) {
            button.addEventListener('mouseenter', function() {
                if (this.style.background.includes('rgba(255,255,255,0.2)')) {
                    this.style.background = 'rgba(255,255,255,0.3)';
                }
            });
            button.addEventListener('mouseleave', function() {
                if (this.style.background.includes('rgba(255,255,255,0.3)')) {
                    this.style.background = 'rgba(255,255,255,0.2)';
                }
            });
        }
    });
}

function navigateToPreviousAppointment() {
    if (currentAppointmentIndex > 0) {
        currentAppointmentIndex--;
        const prevEvent = allAppointments[currentAppointmentIndex];
        renderAppointmentModal(prevEvent);
    }
}

function navigateToNextAppointment() {
    if (currentAppointmentIndex < allAppointments.length - 1) {
        currentAppointmentIndex++;
        const nextEvent = allAppointments[currentAppointmentIndex];
        renderAppointmentModal(nextEvent);
    }
}

function closeAppointmentDetailsModal() {
    const modal = document.getElementById('appointmentDetailsModal');
    if (modal) {
        modal.style.display = 'none';
    }
    
    // Reset navigation state
    allAppointments = [];
    currentAppointmentIndex = -1;
}

// Keyboard navigation support
document.addEventListener('keydown', function(event) {
    const modal = document.getElementById('appointmentDetailsModal');
    if (modal && modal.style.display === 'flex') {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
            event.preventDefault();
            navigateToPreviousAppointment();
        } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
            event.preventDefault();
            navigateToNextAppointment();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            closeAppointmentDetailsModal();
        }
    }
});

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('appointmentDetailsModal');
    if (modal && event.target === modal) {
        closeAppointmentDetailsModal();
    }
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
    const url = `<?= BASE_URL ?>/get_duty_status?date=${dateStr}`;
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