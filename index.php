<?php
require_once 'config.php';

$message = "";
$message_type = "";
// 1. CATCH CALENDAR DATA
// FullCalendar sends a string like "2026-01-08T10:30:00"
$pre_date = "";
$pre_time = "";
$target_dentist_id = $_GET['dentist_id'] ?? null;

if (isset($_GET['start'])) {
    try {
        $dt = new DateTime($_GET['start']);
        $pre_date = $dt->format('Y-m-d');
        $pre_time = $dt->format('H:i');
    } catch (Exception $e) {
        // Fallback if date parsing fails
    }
}

// 2. AJAX ENDPOINT FOR AUTO-FETCH
// If this script is called with ?fetch_phone=..., return JSON and exit
if (isset($_GET['fetch_phone'])) {
    header('Content-Type: application/json');
    $phone = trim($_GET['fetch_phone']);
    
    $stmt = $pdo->prepare("SELECT patient_name, email FROM patients WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($patient) {
        // Found a patient: return their data
        echo json_encode([
            'success' => true,
            'patient_name' => $patient['patient_name'],
            'email' => $patient['email']
        ]);
    } else {
        // No patient found: return a fail state
        echo json_encode(['success' => false]);
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitize
    $name    = htmlspecialchars(strip_tags(trim($_POST['patient_name'])));
    $email   = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $phone   = htmlspecialchars(strip_tags(trim($_POST['phone'])));
    $service = htmlspecialchars(strip_tags(trim($_POST['service'])));
    $date    = $_POST['appointment_date'];
    $time    = $_POST['appointment_time'];
    $notes   = htmlspecialchars(strip_tags(trim($_POST['notes'])));
    $today   = date('Y-m-d');

   try {
    // 2. Handle Patient (Get ID or Create New)
    $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE email = ?");
    $stmt->execute([$email]);
    $patient = $stmt->fetch();

  if ($patient) {
    $patient_id = $patient['patient_id'];
    
    // 3. CHECK FOR CONFLICT: Same Patient, Same Date, Same Time, Same Dentist
    // We only throw an error if all these factors match exactly.
    $checkStmt = $pdo->prepare("
        SELECT id FROM appointments 
        WHERE patient_id = ? 
        AND appointment_date = ? 
        AND appointment_time = ? 
        AND dentist_id = ?
        AND status != 'Cancelled'
    ");
    $checkStmt->execute([$patient_id, $date, $time, $dentist_id]);
    
    if ($checkStmt->fetch()) {
        throw new Exception("This patient already has an appointment with this dentist at $time on $date.");
    }
} else {
    // New Patient: Insert into patients table
    $insPatient = $pdo->prepare("INSERT INTO patients (patient_name, email, phone) VALUES (?, ?, ?)");
    $insPatient->execute([$name, $email, $phone]);
    $patient_id = $pdo->lastInsertId();
}
   // 4. FIND A DENTIST
// 4. FIND A DENTIST (Session Priority)
    // Check session first, then forced hidden field, then fallback to random
    if (!empty($_SESSION['dentist_id'])) {
        $dentist_id = $_SESSION['dentist_id'];
    } elseif (!empty($_POST['forced_dentist_id'])) {
        $dentist_id = $_POST['forced_dentist_id'];
    }

    if (isset($dentist_id)) {
        // Fetch the name for the success message
        $dStmt = $pdo->prepare("SELECT dentist_name FROM dentists WHERE dentist_id = ?");
        $dStmt->execute([$dentist_id]);
        $dentist_name = $dStmt->fetchColumn();
    } else {
        // Fallback: Random assignment based on service
        $dentistStmt = $pdo->prepare("
            SELECT d.dentist_id, d.dentist_name 
            FROM dentists d 
            JOIN specializations s ON d.dentist_id = s.dentist_id 
            WHERE s.service_name = ? 
            ORDER BY RAND() LIMIT 1
        ");
        $dentistStmt->execute([$service]);
        $assigned_dentist = $dentistStmt->fetch();
        
        if (!$assigned_dentist) {
            throw new Exception("No dentist found for the selected service.");
        }
        
        $dentist_id = $assigned_dentist['dentist_id'];
        $dentist_name = $assigned_dentist['dentist_name'];
    }

    // 5. Insert the Appointment
    $sql = "INSERT INTO appointments (patient_id, dentist_id, service, appointment_date, appointment_time, notes) 
            VALUES (:p_id, :d_id, :service, :date, :time, :notes)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':p_id'    => $patient_id,
        ':d_id'    => $dentist_id,
        ':service' => $service,
        ':date'    => $date,
        ':time'    => $time,
        ':notes'   => $notes
    ]);

    // 6. REDIRECT BACK TO CALENDAR
    // If a dentist is in session, we return them to their specific dashboard
    if (!empty($_SESSION['dentist_id'])) {
        header("Location: dentist.php?dentist_id=" . $_SESSION['dentist_id'] . "&booked=success");
        exit();
    }

    $message = "Appointment successfully booked with $dentist_name for $date!";
    $message_type = "success";
} catch (Exception $e) {
    $message = $e->getMessage();
    $message_type = "error";
}}
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
    <div style="text-align: right; margin-bottom: 10px;">
    <a href="login.php" style="text-decoration: none; font-size: 14px; color: #3498db; border: 1px solid #3498db; padding: 5px 10px; border-radius: 4px;">
        Dentist Login
    </a>
</div>
    <h2>Book Your Dental Visit</h2>


    <?php if ($message): ?>
        <div class="alert <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <input type="hidden" name="forced_dentist_id" value="<?php echo htmlspecialchars($target_dentist_id); ?>">

<div class="form-group">
    <label for="phone">Phone Number *</label>
    <input type="tel" id="phone" name="phone" required placeholder="(254) 712-345-678" autocomplete="off">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <small id="status_msg" style="margin-top:5px; font-weight:bold;"></small>
        <a href="javascript:void(0)" id="reset_form" style="display:none; font-size: 12px; color: #e74c3c; text-decoration: none;">(Reset/Change Patient)</a>
    </div>
</div>

        <div class="form-group">          
            <label for="patient_name">Full Name *</label>
            <input type="text" id="patient_name" name="patient_name" required placeholder="John Doe">
        </div>

        <div class="form-group">
            <label for="email">Email Address *</label>
            <input type="email" id="email" name="email" required placeholder="john@example.com">
        </div>


        <div class="form-group">
            <label for="service">Reason for Visit</label>
            <select id="service" name="service">
                <option value="General Checkup">General Checkup</option>
                <option value="Teeth Cleaning">Teeth Cleaning</option>
                <option value="Tooth Extraction">Tooth Extraction</option>
                <option value="Dental Fillings">Dental Fillings</option>
                <option value="Emergency Care">Emergency Care</option>
            </select>
        </div>

        <div style="display: flex; gap: 10px;">
            <div class="form-group" style="flex: 1;">
                <label for="appointment_date">Preferred Date *</label>
                <input type="date" id="appointment_date" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="appointment_time">Preferred Time *</label>
                <input type="time" id="appointment_time" name="appointment_time" required>
            </div>
        </div>

        <div class="form-group">
            <label for="notes">Additional Notes</label>
            <textarea id="notes" name="notes" rows="3" placeholder="Any symptoms or specific concerns?"></textarea>
        </div>

        <button type="submit">Request Appointment</button>
    </form>
</div>
<script>
document.getElementById('phone').addEventListener('blur', function() {
    const phone = this.value.trim();
    const statusMsg = document.getElementById('status_msg');
    
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
        
        // Try direct URL first (since you said path 3 works manually)
        const directUrl = '/booking-dentist/ajax_fetch_patient.php?fetch_phone=' + encodeURIComponent(cleanPhone);
        
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
</script>
</body>
</html>