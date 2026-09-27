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
    'calendar-data' => 'Home/dentists/calendar.php',
    'schedule'        => 'Home/dentists/dentist_schedule.php',
    'services' => 'Home/manage_services.php',
    'calendar'        => 'Home/dentists/dentist.php',
    'dentists'        => 'Home/dentists/view_dentists.php',
    'new-dentist'     =>'Home/dentists/register_dentists.php',
    'patients'    => 'Home/patients/patients.php',
    'test-patients' => 'test_patients.php',
    'patient_history' => 'Home/patients/patient_history.php',
    'logs' =>'Home/logsAndDebugs/manage_logs.php',
    'dentist_specializations' => 'Home/dentists/dentist_specializations.php',
    'profile' => 'Home/profile.php',
    'debug-appointments' => 'Home/logsAndDebug/debug-appointments.php',
    'check-dentist-appointments' => 'Home/dentists/check_dentist_appointments.php',
    'delete-dentist' =>'Home/dentists/delete_dentist.php',
    'toggle-dentist-status' => 'Home/dentists/toggle_dentist_status.php',
    'get-dentists-for-service' => 'Home/dentists/get_dentists_for_service.php',
    'logger' => 'Home/logsAndDebugs/logger.php',

];


    $public_routes = [
    'login' => 'Auth/login.php',
    'logout' => 'Auth/logout.php',

    'bookappointment' => 'Home/appointments/book_appointment.php',

    'fetch-patient' => 'Home/patients/ajax_fetch_patient.php',
    'get_duty_status' => 'Home/logsAndDebugs/get_duty_status.php',
    'dental-assistant' => 'rag/chatbot.php',
    '' => 'Landing/landingpage.php'
    ];


// 5. ROUTING LOGIC
// if (array_key_exists($path, $protected_routes)) {
//     if (!isset($_SESSION['dentist_id'])) {
//         header("Location: " . BASE_URL . "/login");
//         exit();
//     }
//     include $protected_routes[$path];
// } 
// elseif (array_key_exists($path, $public_routes)) {
//     include $public_routes[$path];
// } 
// else {
//     // Default Fallback
//     header("Location: " . BASE_URL . (isset($_SESSION['dentist_id']) ? "/calendar" : "/login"));
//     exit();
// }