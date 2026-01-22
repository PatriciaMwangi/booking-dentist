<?php
require_once 'db.php';

$message = "";
$message_type = "";
$booking_complete = false;
$dentist_id_from_url = $_GET['dentist_id'] ?? '';
$is_dentist = isset($_SESSION['role']) && $_SESSION['role'] === 'dentist';
$phone = "";
$patient_name = "";
$email = "";
// Set default values
$pre_start = '';
$pre_end = '';
$service = 'General Checkup';
$notes = '';

if (!empty($_POST['website_verification_code'])) {
    die("Bot detected."); // Silently stop the request
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Clean date data from GET parameters
if (isset($_GET['start'])) {
    $start_str = $_GET['start'];
    // Remove timezone offset if present
    $start_str = preg_replace('/[+-]\d{2}:\d{2}$/', '', $start_str);
    
try {
    $start_dt = new DateTime($start_str);
    $pre_start = $start_dt->format('Y-m-d\TH:i');
    
    // 1. If 'end' is explicitly provided in the URL, use it
    if (isset($_GET['end'])) {
        $end_str = preg_replace('/[+-]\d{2}:\d{2}$/', '', $_GET['end']);
        $end_dt = new DateTime($end_str);
        $pre_end = $end_dt->format('Y-m-d\TH:i');
    } 
    // 2. Otherwise, default to 1 hour after start (works for both patients and dentists)
    else {
        $end_dt = clone $start_dt;
        $end_dt->modify('+1 hour');
        $pre_end = $end_dt->format('Y-m-d\TH:i');
    }
} catch (Exception $e) {
        $message = "Invalid date format: " . $e->getMessage();
        $message_type = "error";
    }
}

// 1. AJAX ENDPOINT FOR AUTO-FETCH (Keep this at the top)
if (isset($_GET['fetch_phone'])) {
    header('Content-Type: application/json');
    $phone = trim($_GET['fetch_phone'] ?? '');
    
    // Clean phone number
    $phone = preg_replace('/\D/', '', $phone);
    
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone number required']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT patient_name, email FROM patients WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient) {
            echo json_encode(array_merge(['success' => true], $patient));
        } else {
            echo json_encode(['success' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Sanitize Inputs
        $name    = htmlspecialchars(strip_tags(trim($_POST['patient_name'] ?? '')));
        $email   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $phone   = htmlspecialchars(strip_tags(trim($_POST['phone'] ?? '')));
        $service = htmlspecialchars(strip_tags(trim($_POST['service'] ?? 'General Checkup')));
        $notes   = htmlspecialchars(strip_tags(trim($_POST['notes'] ?? '')));
        
        $start_input = $_POST['start_time'] ?? '';
        $end_input   = $_POST['end_time'] ?? '';
        
        if (empty($start_input) || empty($end_input)) {
            throw new Exception("Start and end times are required.");
        }
        
        $start = new DateTime($start_input);
        $end   = new DateTime($end_input);

        $is_staff = (isset($_SESSION['role']) && ($_SESSION['role'] === 'dentist' || $_SESSION['role'] === 'superintendent'));
        
        if (!$is_staff) {
            // Force end time to be exactly 1 hour after start for patients
            $end = clone $start;
            $end->modify('+1 hour');
        }

        // Logic Check: End after Start?
        if ($start >= $end) {
            throw new Exception("Error: The end time must be after the start time.");
        }

        // Handle Dentist ID
        $dentist_id = null;
        
        // If user is logged in as dentist or staff
        if (isset($_SESSION['dentist_id'])) {
            $dentist_id = $_SESSION['dentist_id'];
        } 
        // If dentist_id is passed in URL (from calendar click)
        elseif (!empty($dentist_id_from_url)) {
            $dentist_id = $dentist_id_from_url;
        }
        // If patient booking (auto-assign)
        else {
            // First, let's check if there are any dentists specializing in this service
            $sql_check_service = "SELECT COUNT(*) as count FROM specializations WHERE service_name = ?";
            $stmt_check = $pdo->prepare($sql_check_service);
            $stmt_check->execute([$service]);
            $service_count = $stmt_check->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($service_count == 0) {
                throw new Exception("No dentists available for this service. Please select a different service.");
            }
            
            // Find available dentist for this time slot
            $sql_find_dentist = "
                SELECT d.dentist_id, COUNT(a.id) as appointment_count
                FROM dentists d
                INNER JOIN specializations s ON d.dentist_id = s.dentist_id
                LEFT JOIN appointments a ON d.dentist_id = a.dentist_id 
                    AND a.status != 'Cancelled'
                    AND a.start_time < :end_time 
                    AND a.end_time > :start_time
                WHERE s.service_name = :service
                GROUP BY d.dentist_id
                HAVING COUNT(a.id) = 0
                ORDER BY appointment_count ASC
                LIMIT 1";
            
            $stmt_find = $pdo->prepare($sql_find_dentist);
            $stmt_find->execute([
                ':service' => $service,
                ':start_time' => $start->format('Y-m-d H:i:s'),
                ':end_time' => $end->format('Y-m-d H:i:s')
            ]);
            
            $available_dentist = $stmt_find->fetch(PDO::FETCH_ASSOC);
            
            if ($available_dentist) {
                $dentist_id = $available_dentist['dentist_id'];
            } else {
                // No dentist available at this time, find next available slot
                $sql_next_available = "
                    SELECT d.dentist_id, MIN(a.end_time) as next_available
                    FROM dentists d
                    INNER JOIN specializations s ON d.dentist_id = s.dentist_id
                    INNER JOIN appointments a ON d.dentist_id = a.dentist_id
                    WHERE s.service_name = :service
                    AND a.status != 'Cancelled'
                    AND a.end_time > :start_time
                    AND NOT EXISTS (
                        SELECT 1 FROM appointments a2 
                        WHERE a2.dentist_id = d.dentist_id
                        AND a2.status != 'Cancelled'
                        AND a2.start_time < DATE_ADD(a.end_time, INTERVAL 1 HOUR)
                        AND a2.end_time > a.end_time
                    )
                    GROUP BY d.dentist_id
                    ORDER BY next_available ASC
                    LIMIT 1";
                
                $stmt_next = $pdo->prepare($sql_next_available);
                $stmt_next->execute([
                    ':service' => $service,
                    ':start_time' => $start->format('Y-m-d H:i:s')
                ]);
                
                $next_slot = $stmt_next->fetch(PDO::FETCH_ASSOC);
                
                if ($next_slot) {
                    $suggested_time = new DateTime($next_slot['next_available']);
                    $formatted_time = $suggested_time->format('F j, g:i a');
                    
                    // Store suggestion for user
                    $_SESSION['suggestion'] = [
                        'dentist_id' => $next_slot['dentist_id'],
                        'suggested_time' => $suggested_time->format('Y-m-d H:i:s'),
                        'service' => $service
                    ];
                    
                    throw new Exception("No dentists available at your selected time. The next available slot is on $formatted_time. Would you like to book that instead?");
                } else {
                    throw new Exception("No availability found for this service. Please try a different time or service.");
                }
            }
        }
        
        if (!$dentist_id) {
            throw new Exception("Unable to assign a dentist. Please contact the clinic directly.");
        }

        // Final Overlap Check (just to be safe)
        $sql_check = "SELECT COUNT(*) FROM appointments 
                      WHERE dentist_id = :d_id 
                      AND status != 'Cancelled'
                      AND (start_time < :end AND end_time > :start)";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([
            'd_id'  => $dentist_id,
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s')
        ]);

        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("This time slot has just been booked by someone else. Please choose another time.");
        }

        // Patient Management
        $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->execute([$email, $phone]);
        $patient = $stmt->fetch();

        if ($patient) {
            $patient_id = $patient['patient_id'];
            // Update existing patient info if needed
            $updatePatient = $pdo->prepare("UPDATE patients SET patient_name = ?, email = ?, phone = ? WHERE patient_id = ?");
            $updatePatient->execute([$name, $email, $phone, $patient_id]);
        } else {
            $insPatient = $pdo->prepare("INSERT INTO patients (patient_name, email, phone) VALUES (?, ?, ?)");
            $insPatient->execute([$name, $email, $phone]);
            $patient_id = $pdo->lastInsertId();
        }

        // Insert Appointment
        $sql = "INSERT INTO appointments (patient_id, dentist_id, service, start_time, end_time, notes, status) 
                VALUES (:p_id, :d_id, :service, :start, :end, :notes, 'Pending')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':p_id'    => $patient_id,
            ':d_id'    => $dentist_id,
            ':service' => $service,
            ':start'   => $start->format('Y-m-d H:i:s'),
            ':end'     => $end->format('Y-m-d H:i:s'),
            ':notes'   => $notes
        ]);

        $appointment_id = $pdo->lastInsertId();
        
        // Handle redirection based on user type
        if (isset($_SESSION['role']) && ($_SESSION['role'] === 'dentist' || $_SESSION['role'] === 'superintendent')) {
            // Staff user - redirect to dashboard
            header("Location: dentist.php?booked=success&appointment_id=" . $appointment_id);
            exit();
        } else {
            // Patient user - show success message on same page
            $booking_complete = true;
            $message = "🎉 Appointment requested successfully! Your reference ID is #" . $appointment_id;
            $message_type = "success";
            
            // Generate Google Calendar link
            $g_start = $start->format('Ymd\THis');
            $g_end = $end->format('Ymd\THis');
            $g_title = urlencode("Dental Appointment: " . $service);
            $g_details = urlencode("Service: $service\nNotes: $notes\nReference ID: #$appointment_id");
            $google_cal_url = "https://www.google.com/calendar/render?action=TEMPLATE&text=$g_title&dates=$g_start/$g_end&details=$g_details&sf=true&output=xml";
        }

    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = "error";
        $booking_complete = false;
        
        // Preserve form values on error
        $pre_start = $_POST['start_time'] ?? $pre_start;
        $pre_end = $_POST['end_time'] ?? $pre_end;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Appointment Booking</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; color: #333; display: flex; justify-content: center; padding: 20px; }
        .form-container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h2 { color: #2c3e50; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        input:focus { border-color: #3498db; outline: none; }
        button { width: 100%; padding: 12px; background-color: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #2980b9; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="form-container">
    <?php if (!isset($_SESSION['dentist_id'])): ?>
        <div style="text-align: right; margin-bottom: 10px;">
            <a href="login.php" style="text-decoration: none; font-size: 14px; color: #3498db; border: 1px solid #3498db; padding: 5px 10px; border-radius: 4px;">
                Dentist Login
            </a>
        </div>
    <?php endif; ?>

    <h2>Book Your Dental Visit</h2>


    <?php if ($message): ?>
        <div class="alert <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

   <?php if (isset($booking_complete) && $booking_complete === true): ?>
        <div class="booking-success-card" style="text-align: center; padding: 40px 20px;">
            <div style="font-size: 50px; margin-bottom: 20px;">✅</div>
            <h3 style="color: #2c3e50;">Booking Confirmed!</h3>
            
            <a href="<?= $google_cal_url ?>" target="_blank" 
               style="display: inline-block; margin: 15px 0; padding: 10px 20px; background: #fff; color: #757575; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 10px;">
               <img src="https://upload.wikimedia.org/wikipedia/commons/a/a5/Google_Calendar_icon_%282020%29.svg" width="20" alt="GCal">
               Add to Google Calendar
            </a>

            <p style="color: #7f8c8d; margin-bottom: 30px;">Your appointment has been added to our schedule.</p>
            
            <a href="<?= BASE_URL ?>/" class="btn-primary" style="text-decoration: none; background: #3498db; color: white; padding: 12px 25px; border-radius: 6px; font-weight: bold;">
                + Book Another Appointment
            </a>
        </div>
    <?php else: ?>

    <form action="" method="POST">
        <input type="hidden" name="forced_dentist_id" value="<?= htmlspecialchars($dentist_id_from_url) ?>">
<div style="display:none;">
    <input type="text" name="website_verification_code" value="">
</div>
<div class="form-group">
    <label for="phone">Phone Number *</label>
    <input type="tel" id="phone" name="phone" required placeholder="(254) 712-345-678" autocomplete="off"value="<?= htmlspecialchars($_POST['phone'] ?? $phone) ?>">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <small id="status_msg" style="margin-top:5px; font-weight:bold;"></small>
        <a href="javascript:void(0)" id="reset_form" style="display:none; font-size: 12px; color: #e74c3c; text-decoration: none;">(Reset/Change Patient)</a>
    </div>
</div>

        <div class="form-group">          
            <label for="patient_name">Full Name *</label>
            <input type="text" id="patient_name" name="patient_name" required placeholder="John Doe"
            value="<?= htmlspecialchars($_POST['patient_name'] ?? $patient_name) ?>">
        </div>

        <div class="form-group">
            <label for="email">Email Address *</label>
            <input type="email" id="email" name="email" required placeholder="john@example.com"
            value="<?= htmlspecialchars($_POST['email'] ?? $email) ?>">
            
        </div>

<div class="form-group">
    <label for="service">Reason for Visit</label>
    <select id="service" name="service" required>
        <option value="" disabled selected>Select a service...</option>
        <?php
        try {
            // We fetch only services that actually have an assigned dentist
            $sql_services = "SELECT DISTINCT service_name FROM specializations ORDER BY service_name ASC";
            $stmt_services = $pdo->query($sql_services);
            
            while ($row = $stmt_services->fetch(PDO::FETCH_ASSOC)): 
                $s_name = htmlspecialchars($row['service_name']);
                // Maintain selection if the page reloads due to a validation error
                $is_selected = (isset($_POST['service']) && $_POST['service'] === $s_name) ? 'selected' : '';
        ?>
            <option value="<?= $s_name ?>" <?= $is_selected ?>><?= $s_name ?></option>
        <?php 
            endwhile; 
        } catch (PDOException $e) {
            echo '<option value="" disabled>Error loading services</option>';
        }
        ?>
    </select>
    
    <?php if (!isset($_SESSION['dentist_id'])): ?>
        <small style="color: #7f8c8d;">* We will automatically assign a specialist based on your selection.</small>
    <?php endif; ?>
</div>

<div id="time-error-message" style="display:none; color: #e74c3c; background: #fdeaea; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; width: 100%; box-sizing: border-box;">
    ⚠️ The end time must be after the start time.
</div>

<div style="display: flex; gap: 10px;">
    <div class="form-group" style="flex: 1;">
        <label for="start_time">Start Date & Time *</label>
        <input type="datetime-local" id="start_time" name="start_time" 
               required min="<?= date('Y-m-d\TH:i') ?>"
               value="<?= htmlspecialchars($_POST['start_time'] ?? $pre_start) ?>">
    </div>

    <div class="form-group" style="flex: 1;">
        <label for="end_time">End Date & Time *</label>
        <input type="datetime-local" id="end_time" name="end_time" 
               required value="<?= htmlspecialchars($_POST['end_time'] ?? $pre_end) ?>"
               <?= !isset($_SESSION['dentist_id']) ? 'readonly style="background-color: #f0f0f0; cursor: not-allowed;"' : '' ?>>
    </div>
</div>
<?php if ($message): ?>
    <div class="alert <?= $message_type ?>">
        <?= $message ?>
        <?php if (isset($suggestion_available)): ?>
            <div style="margin-top: 10px;">
                <button type="button" onclick="applySuggestion('<?= $suggestion_available ?>', '<?= $suggested_dentist_id ?>')" class="btn-suggestion">
                    ✅ Yes, book this time
                </button>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
        <div class="form-group">
            <label for="notes">Additional Notes</label>
            <textarea id="notes" name="notes" rows="3" placeholder="Any symptoms or specific concerns?"></textarea>
        </div>

        <button type="submit">Request Appointment</button>
    </form>
    <?php endif; ?>
</div>
<script>

document.getElementById('phone').addEventListener('blur', function() {
    const phone = this.value.trim();
    const statusMsg = document.getElementById('status_msg');
    const BASE_URL = "<?= BASE_URL ?>";
    
    if (phone.length >= 10) {
        statusMsg.innerText = "Checking...";
        statusMsg.style.color = "#666";
        
        // Clean the phone number
        const cleanPhone = phone.replace(/\D/g, '');
        
        // Create a more robust fetch with timeout and proper error handling
        const fetchWithTimeout = (url, options = {}, timeout = 5000) => {
            return Promise.race([
                fetch(url, options),
                new Promise((_, reject) => 
                    setTimeout(() => reject(new Error('Request timeout')), timeout)
                )
            ]);
        };
        
const directUrl = `${BASE_URL}/fetch-patient?fetch_phone=${encodeURIComponent(cleanPhone)}`;

        console.log('Attempting fetch to:', directUrl);
        
        fetchWithTimeout(directUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include',
            mode: 'cors'
        })
        .then(response => {
            console.log('Response status:', response.status, response.statusText);
            
            if (response.status === 0) {
                throw new Error('Network error or CORS blocked');
            }
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error(`Expected JSON but got: ${contentType}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Received data:', data);
            

if (data.success) {
    const nameInput = document.getElementById('patient_name');
    const emailInput = document.getElementById('email');

    nameInput.value = data.patient_name || '';
    emailInput.value = data.email || '';
    
    // Make fields non-editable
    nameInput.readOnly = true;
    emailInput.readOnly = true;
    
    // Visual feedback for non-editable state
    nameInput.style.backgroundColor = "#f0f0f0";
    emailInput.style.backgroundColor = "#f0f0f0";
    nameInput.style.cursor = "not-allowed";
    emailInput.style.cursor = "not-allowed";

statusMsg.innerText = "Found: " + data.patient_name + "\n(ID Fields are now non-editable)";
    statusMsg.style.color = "#ec1c73";
} else {
    const nameInput = document.getElementById('patient_name');
    const emailInput = document.getElementById('email');

    nameInput.value = '';
    emailInput.value = '';
    
    // Ensure fields are editable for new patients
    nameInput.readOnly = false;
    emailInput.readOnly = false;
    
    // Reset visual styles
    nameInput.style.backgroundColor = "";
    emailInput.style.backgroundColor = "";
    nameInput.style.cursor = "auto";
    emailInput.style.cursor = "auto";

    statusMsg.innerText = "New patient detected.";
    statusMsg.style.color = "#e67e22";
}
        })
        .catch(error => {
            console.error('Fetch error details:', error);
            console.error('Error name:', error.name);
            console.error('Error message:', error.message);
            
            // Try alternative approach using XMLHttpRequest (older, but sometimes works where fetch doesn't)
            statusMsg.innerText = "Trying alternative method...";
            
            const xhr = new XMLHttpRequest();
            const xhrUrl = '/booking-dentist/ajax_fetch_patient.php?fetch_phone=' + encodeURIComponent(cleanPhone);
            
            xhr.open('GET', xhrUrl, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 5000;
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            document.getElementById('patient_name').value = data.patient_name || '';
                            document.getElementById('email').value = data.email || '';
                            statusMsg.innerText = "Found: " + data.patient_name;
                            statusMsg.style.color = "#27ae60";
                        } else {
                            document.getElementById('patient_name').value = '';
                            document.getElementById('email').value = '';
                            statusMsg.innerText = "New patient detected.";
                            statusMsg.style.color = "#e67e22";
                        }
                    } catch (e) {
                        statusMsg.innerText = "Invalid response format";
                        statusMsg.style.color = "red";
                    }
                } else {
                    statusMsg.innerText = "Server error: " + xhr.status;
                    statusMsg.style.color = "red";
                }
            };
            
            xhr.onerror = function() {
                statusMsg.innerText = "Network error. Please type manually.";
                statusMsg.style.color = "red";
                document.getElementById('patient_name').value = '';
                document.getElementById('email').value = '';
            };
            
            xhr.ontimeout = function() {
                statusMsg.innerText = "Request timeout. Please type manually.";
                statusMsg.style.color = "red";
                document.getElementById('patient_name').value = '';
                document.getElementById('email').value = '';
            };
            
            xhr.send();
        });
    } else {
        statusMsg.innerText = "";
        document.getElementById('patient_name').value = '';
        document.getElementById('email').value = '';
    }
});
// document.querySelector('form').addEventListener('submit', function(e) {
//     const startVal = document.querySelector('input[name="start_time"]').value;
//     console.log(startVal)
//     const endVal = document.querySelector('input[name="end_time"]').value;
//     const errorDiv = document.getElementById('time-error-message');

//     if (startVal && endVal) {
//         const start = new Date(startVal);
//         const end = new Date(endVal);

//         if (start >= end) {
//             e.preventDefault(); // STOPS THE PAGE REFRESH
//             errorDiv.style.display = 'block';
//             errorDiv.innerText = "⚠️ Error: The end time must be after the start time.";
            
//             // Scroll to error so the user sees it
//             errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
//             return false;
//         }
//     }
//     console.log('startVal',startVal)
//     errorDiv.style.display = 'none'; // Hide if valid
// });
// Check if user is a dentist using a JS variable passed from PHP
const isDentist = <?= isset($_SESSION['dentist_id']) ? 'true' : 'false' ?>;

function applySuggestion(newTime, dentistId) {
    // 1. Update the time inputs
    const startInput = document.getElementById('start_time');
    const endInput = document.getElementById('end_time');
    
    startInput.value = newTime;
    
    // Calculate end time (1 hour later)
    const startDate = new Date(newTime);
    const endDate = new Date(startDate.getTime() + (60 * 60 * 1000));
    const formattedEnd = endDate.toISOString().slice(0, 16);
    endInput.value = formattedEnd;

    // 2. Ensure the correct dentist ID is sent
    let dentistHidden = document.querySelector('input[name="forced_dentist_id"]');
    if (dentistHidden) {
        dentistHidden.value = dentistId;
    }

    // 3. Submit the form automatically
    document.querySelector('form').submit();
}
class AppointmentTimeManager {
    constructor() {
        this.startInput = document.getElementById('start_time');
        this.endInput = document.getElementById('end_time');
        this.timeError = document.getElementById('time-error-message');
        
        this.init();
    }
    
init() {
    this.autoCalculateEndTime();
    
    // Use both 'input' and 'change' for maximum compatibility
    ['input', 'change'].forEach(eventType => {
        this.startInput.addEventListener(eventType, () => {
            this.handleStartTimeChange();
        });
    });

    this.endInput.addEventListener('change', () => this.validateTimes());
}
    
    handleStartTimeChange() {
        if (!isDentist && this.startInput.value) {
            this.autoCalculateEndTime();
        }
        this.validateTimes();
    }
    
autoCalculateEndTime() {
    const startVal = this.startInput.value;
    if (startVal) {
        const startDate = new Date(startVal);
        
        // Check if the date is valid before proceeding
        if (!isNaN(startDate.getTime())) {
            // Add exactly 60 minutes
            const endDate = new Date(startDate.getTime() + (60 * 60 * 1000));
            
            this.endInput.value = this.formatDateTimeLocal(endDate);
            
            // Explicitly trigger the 'input' and 'change' events 
            // so other validation logic knows the value changed
            this.endInput.dispatchEvent(new Event('input'));
            this.endInput.dispatchEvent(new Event('change'));
            
            this.showTimeHint("End time updated to 1 hour after start");
        }
    }
}
    formatDateTimeLocal(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }
    
    validateTimes() {
        const startVal = this.startInput.value;
        const endVal = this.endInput.value;
        
        if (startVal && endVal) {
            const start = new Date(startVal);
            const end = new Date(endVal);
            
            // Calculate duration in hours
            const durationHours = (end - start) / (1000 * 60 * 60);
            
            if (start >= end) {
                this.timeError.textContent = "⚠️ Error: End time must be after start time";
                this.timeError.style.display = 'block';
                this.timeError.style.backgroundColor = '#fdeaea';
                this.timeError.style.color = '#e74c3c';
                return false;
            } else if (!isDentist && Math.abs(durationHours - 1) > 0.01) {
                // For non-dentists, warn if duration isn't 1 hour
                this.timeError.textContent = `⚠️ Note: Patient appointments are fixed at 1 hour. Duration: ${durationHours.toFixed(2)} hours`;
                this.timeError.style.display = 'block';
                this.timeError.style.backgroundColor = '#fff3cd';
                this.timeError.style.color = '#856404';
                return true; // Still valid, just warning
            } else {
                this.timeError.style.display = 'none';
                return true;
            }
        }
        
        this.timeError.style.display = 'none';
        return true;
    }
    
    showTimeHint(message) {
        // Create or update hint element
        let hint = document.getElementById('time-hint');
        if (!hint) {
            hint = document.createElement('div');
            hint.id = 'time-hint';
            hint.style.cssText = 'margin-top: 5px; font-size: 12px; color: #27ae60; font-weight: 500;';
            this.endInput.parentNode.appendChild(hint);
        }
        
        hint.textContent = message;
        setTimeout(() => {
            hint.textContent = '';
        }, 3000);
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    new AppointmentTimeManager();
    
    // Add visual indication for dentists
    if (isDentist) {
        const endLabel = document.querySelector('label[for="end_time"]');
        if (endLabel) {
            endLabel.innerHTML = 'End Time * ';
        }
    } else {
        const endLabel = document.querySelector('label[for="end_time"]');
        if (endLabel) {
            endLabel.innerHTML = 'End Time * <span style="font-size: 11px; color: #e67e22;">(Auto-set to 1 hour)</span>';
        }
        
        // Prevent manual editing of end time for non-dentists
        const endInput = document.getElementById('end_time');
        endInput.addEventListener('focus', function(e) {
            e.preventDefault();
            this.blur();
            alert("For patient bookings, appointments are fixed at 1 hour duration. The end time is automatically calculated.");
        });
        
        endInput.readOnly = true;
        endInput.style.backgroundColor = '#f8f9fa';
        endInput.style.cursor = 'not-allowed';
    }
});
</script>
</body>
</html>