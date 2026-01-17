<?php
// ajax_fetch_patient.php
require_once 'db.php';
// Clear any accidental output/whitespace to ensure clean JSON
if (ob_get_length()) ob_clean(); 

error_log("AJAX request received: " . $_SERVER['REQUEST_URI']);
error_log("GET params: " . print_r($_GET, true));
error_log("Headers: " . print_r(getallheaders(), true));

// Enable CORS headers for development
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // cache for 1 day
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

// Add these headers for all responses
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");


if (isset($_GET['fetch_phone'])) {
    $phone = trim($_GET['fetch_phone']);
    
    try {
        $stmt = $pdo->prepare("SELECT patient_name, email FROM patients WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($patient) {
            echo json_encode([
                'success' => true,
                'patient_name' => $patient['patient_name'],
                'email' => $patient['email']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No patient found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No phone parameter provided']);
}
exit();
?>