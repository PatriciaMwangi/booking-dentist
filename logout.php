<?php
// Start the session to gain access to it
session_start();
// Clear all session variables
$_SESSION = array();

if (isset($_SESSION['dentist_id'])) {
    require_once 'logger.php';
    logLogout($_SESSION['dentist_id'], $_SESSION['dentist_name'] ?? 'Unknown');
}

// If it's desired to kill the session, also delete the session cookie.
// This is more secure than just session_destroy()
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the login page
header("Location: login.php");
exit();
?>