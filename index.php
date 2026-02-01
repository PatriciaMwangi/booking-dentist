<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
define('APP_RUNNING', true);

// 1. DYNAMIC BASE PATH DETECTION
// This detects "/booking-dentist" on XAMPP and "" (empty) on Render
$base_dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); 

// 2. DEFINE A GLOBAL BASE URL
// Detect if HTTPS is used directly or via Render's proxy header
$is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
             (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

$protocol = $is_https ? 'https' : 'http';
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $base_dir;
define('BASE_URL', $base_url);

// 3. STRIP BASE DIR FROM REQUEST
$request_uri = $_SERVER['REQUEST_URI'];
if ($base_dir !== '' && strpos($request_uri, $base_dir) === 0) {
    $request_uri = substr($request_uri, strlen($base_dir));
}
$path = trim(explode('?', $request_uri)[0], '/');

// 4. ROUTE DEFINITIONS
$protected_routes = [
    'calendar-data' => 'calendar.php',
    'schedule'        => 'schedule.php',
    'services' => 'manage_services.php',
    'calendar'        => 'dentist.php',
    'dentists'        => 'view_dentists.php',
    'new-dentist'     =>'register_dentists.php',
    'patients'    => 'patients.php',
    'test_patients' => 'test_patients.php',
    'patient_history' => 'patient_history.php',
    'logs' =>'manage_logs.php',
    'dentist_specializations' => 'dentist_specializations.php',
    'profile' => 'profile.php',
    'debug-appointments' => 'debug-appointments.php',
    'check-dentist-appointments' => 'check_dentist_appointments.php',
    'delete_dentist' =>'delete_dentist.php',
    'toggle_dentist_status' => 'toggle_dentist_status.php',
    'get-dentists-for-service' => 'get_dentists_for_service.php',

];

$public_routes = [
    'login' => 'login.php',
    'logout' => 'logout.php',
    ''      => 'book_appointment.php',
    'fetch-patient' => 'ajax_fetch_patient.php', 
    'get_duty_status' => 'get_duty_status.php'
];

// 5. ROUTING LOGIC
if (array_key_exists($path, $protected_routes)) {
    if (!isset($_SESSION['dentist_id'])) {
        header("Location: " . BASE_URL . "/login");
        exit();
    }
    include $protected_routes[$path];
} 
elseif (array_key_exists($path, $public_routes)) {
    include $public_routes[$path];
} 
else {
    // Default Fallback
    header("Location: " . BASE_URL . (isset($_SESSION['dentist_id']) ? "/calendar" : "/login"));
    exit();
}