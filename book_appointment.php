<?php
require_once 'db.php';
$message = "";
$message_type = "";
// Clean the input to ensure it fits the datetime-local format (YYYY-MM-DDTHH:MM)
$pre_start = isset($_GET['start']) ? date('Y-m-d\TH:i', strtotime($_GET['start'])) : '';
$pre_end   = isset($_GET['end']) ? date('Y-m-d\TH:i', strtotime($_GET['end'])) : '';
$dentist_id_from_url = $_GET['dentist_id'] ?? '';
$phone = "";
$patient_name = ""; 
$email = "";

// 1. AJAX ENDPOINT FOR AUTO-FETCH (Keep this at the top)
if (isset($_GET['fetch_phone'])) {
    header('Content-Type: application/json');
    $phone = trim($_GET['fetch_phone']);
    $stmt = $pdo->prepare("SELECT patient_name, email FROM patients WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($patient ? array_merge(['success' => true], $patient) : ['success' => false]);
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Sanitize Inputs
        $name    = htmlspecialchars(strip_tags(trim($_POST['patient_name'])));
        $email   = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $phone   = htmlspecialchars(strip_tags(trim($_POST['phone'])));
        $service = htmlspecialchars(strip_tags(trim($_POST['service'])));
        $notes   = htmlspecialchars(strip_tags(trim($_POST['notes'])));
        
        $start_input = $_POST['start_time'];
        $end_input   = $_POST['end_time'];
        
        $start = new DateTime($start_input);
        $end   = new DateTime($end_input);

        // 2. Logic Check: End after Start?
        if ($start >= $end) {
            throw new Exception("Error: The end time must be after the start time.");
        }

        // 3. Forced 1-Hour Logic for Patients
        $is_dentist = (isset($_SESSION['role']) && $_SESSION['role'] === 'dentist');
        if (!$is_dentist) {
            $expected_end = clone $start;
            $expected_end->modify('+1 hour');
            if ($end != $expected_end) {
                $end = $expected_end; // Force it to 1 hour
            }
        }

        // 4. Handle Dentist ID
        // 1. Check if the user is a patient (no session)
if (!isset($_SESSION['dentist_id'])) {
    
    // This query finds dentists who:
    // A. Offer the selected service
    // B. Have NO overlapping appointments at the requested time
    // C. Orders them by the total number of appointments they already have
    // D. Uses RAND() to break ties if appointment counts are equal
    $sql_auto_assign = "
   SELECT d.dentist_id, COUNT(a.id) as total_bookings
        FROM dentists d
        JOIN specializations s ON d.dentist_id = s.dentist_id
        LEFT JOIN appointments a ON d.dentist_id = a.dentist_id
        WHERE s.service_name = :service
        AND d.dentist_id NOT IN (
            SELECT dentist_id FROM appointments 
            WHERE status != 'Cancelled'
            AND (start_time < :end AND end_time > :start)
        )
        GROUP BY d.dentist_id
        ORDER BY total_bookings ASC, RAND()
        LIMIT 1";
    $stmt_assign = $pdo->prepare($sql_auto_assign);
    $stmt_assign->execute([
        'service' => $service,
        'start'   => $start->format('Y-m-d H:i:s'),
        'end'     => $end->format('Y-m-d H:i:s')
    ]);

    $assigned_dentist = $stmt_assign->fetch(PDO::FETCH_ASSOC);

    if (!$assigned_dentist) {
    // 1. Find the nearest available 1-hour slot after the requested start time
    $sql_suggestion = "
        SELECT d.dentist_id, MIN(a2.end_time) as next_slot
        FROM dentists d
        JOIN specializations s ON d.dentist_id = s.dentist_id
        JOIN appointments a2 ON d.dentist_id = a2.dentist_id
        WHERE s.service_name = :service
        AND a2.end_time >= :start
        AND d.dentist_id NOT IN (
            SELECT dentist_id FROM appointments 
            WHERE status != 'Cancelled'
            AND (start_time < DATE_ADD(a2.end_time, INTERVAL 1 HOUR) AND end_time > a2.end_time)
        )
        GROUP BY d.dentist_id
        ORDER BY next_slot ASC
        LIMIT 1";

    $stmt_sug = $pdo->prepare($sql_suggestion);
    $stmt_sug->execute([
        'service' => $service,
        'start'   => $start->format('Y-m-d H:i:s')
    ]);
    
    $suggestion = $stmt_sug->fetch(PDO::FETCH_ASSOC);

    if ($suggestion) {
        $suggested_time = date('Y-m-d\TH:i', strtotime($suggestion['next_slot']));
        $message = "That slot is full. Would you like to book for <strong>" . date('M j, g:i a', strtotime($suggested_time)) . "</strong> instead?";
        $message_type = "info"; // Use a blue/info style for the suggestion
        
        // Store suggestion in a hidden variable for the "Yes" button
        $suggestion_available = $suggested_time;
        $suggested_dentist_id = $suggestion['dentist_id'];
    } else {
        throw new Exception("No availability found for this service in the near future.");
    }
}
    
    $dentist_id = $assigned_dentist['dentist_id'];
} else {
    // If logged in as dentist, use the session ID or form ID
    $dentist_id = $_POST['forced_dentist_id'] ?: $_SESSION['dentist_id'];
}

        // 5. Consolidated Overlap Check
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
            throw new Exception("This time slot is already booked. Please choose another time.");
        }

        // 6. Patient Management
        $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE email = ?");
        $stmt->execute([$email]);
        $patient = $stmt->fetch();

        if ($patient) {
            $patient_id = $patient['patient_id'];
        } else {
            $insPatient = $pdo->prepare("INSERT INTO patients (patient_name, email, phone) VALUES (?, ?, ?)");
            $insPatient->execute([$name, $email, $phone]);
            $patient_id = $pdo->lastInsertId();
        }

        // 7. Insert Appointment using new columns
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

        // Success Redirect
        $message = "🎉 Appointment requested successfully! We have assigned a specialist for your " . htmlspecialchars($service) . ".";
        $message_type = "success";
        $booking_complete = true; // Flag to hide the form
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = "error";
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

    <form action="" method="POST">
        <input type="hidden" name="forced_dentist_id" value="<?= htmlspecialchars($dentist_id_from_url) ?>">

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
        <option value="General Checkup">General Checkup</option>
        <option value="Teeth Cleaning">Emergency Care</option>
                <option value="Teeth Cleaning">Cosmetic Care</option>
        <option value="Teeth Cleaning">Dental Fillings</option>

        </select>
    <?php if (!isset($_SESSION['dentist_id'])): ?>
        <small style="color: #7f8c8d;">* We will automatically assign the best available specialist for you.</small>
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
</div>
<script>
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
document.querySelector('form').addEventListener('submit', function(e) {
    const startVal = document.querySelector('input[name="appointment_start"]').value;
    const endVal = document.querySelector('input[name="appointment_end"]').value;
    const errorDiv = document.getElementById('time-error-message');

    if (startVal && endVal) {
        const start = new Date(startVal);
        const end = new Date(endVal);

        if (start >= end) {
            e.preventDefault(); // STOPS THE PAGE REFRESH
            errorDiv.style.display = 'block';
            errorDiv.innerText = "⚠️ Error: The end time must be after the start time.";
            
            // Scroll to error so the user sees it
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
    }
    errorDiv.style.display = 'none'; // Hide if valid
});
// Check if user is a dentist using a JS variable passed from PHP
const isDentist = <?= isset($_SESSION['dentist_id']) ? 'true' : 'false' ?>;

document.getElementById('start_time').addEventListener('change', function() {
    const startTimeVal = this.value;
    
    if (startTimeVal && !isDentist) {
        const startDate = new Date(startTimeVal);
        
        // Add 1 hour (60 minutes * 60 seconds * 1000 milliseconds)
        const endDate = new Date(startDate.getTime() + (60 * 60 * 1000));
        
        // Format to YYYY-MM-DDTHH:mm for the input field
        const year = endDate.getFullYear();
        const month = String(endDate.getMonth() + 1).padStart(2, '0');
        const day = String(endDate.getDate()).padStart(2, '0');
        const hours = String(endDate.getHours()).padStart(2, '0');
        const minutes = String(endDate.getMinutes()).padStart(2, '0');
        
        const formattedEnd = `${year}-${month}-${day}T${hours}:${minutes}`;
        
        document.getElementById('end_time').value = formattedEnd;
        
        // Hide error message if it was showing
        document.getElementById('time-error-message').style.display = 'none';
    }
});
</script>
</body>
</html>