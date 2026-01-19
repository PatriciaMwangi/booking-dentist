<?php
require_once 'db.php';
$message = "";
$message_type = "";
// Clean the input to ensure it fits the datetime-local format (YYYY-MM-DDTHH:MM)
$pre_start = isset($_GET['start']) ? date('Y-m-d\TH:i', strtotime($_GET['start'])) : '';
$pre_end   = isset($_GET['end']) ? date('Y-m-d\TH:i', strtotime($_GET['end'])) : '';
$dentist_id_from_url = $_GET['dentist_id'] ?? '';

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
        $dentist_id = $_POST['dentist_id'] ?? $_SESSION['dentist_id'] ?? null;
        if (!$dentist_id) {
            throw new Exception("Please select a dentist.");
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
        $redirect = $is_dentist ? "dentist.php?booked=success" : "index.php?booked=success";
        header("Location: $redirect");
        exit();

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
        <label for="start_time">Start Date & Time *</label>
        <input type="datetime-local" id="start_time" name="start_time" 
               required value="<?= $pre_start ?>" 
               min="<?= date('Y-m-d\TH:i') ?>">
    </div>
    <div class="form-group" style="flex: 1;">
        <label for="end_time">End Date & Time *</label>
        <input type="datetime-local" id="end_time" name="end_time" 
               required value="<?= $pre_end ?>">
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
</script>
</body>
</html>