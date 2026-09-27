<?php
/**
 * index.php
 *
 * Main entry point / router for the Booking Dentist application.
 */

// ============================================================
// 1. LOAD CONFIGURATION
// ============================================================

require_once __DIR__ . '/config.php';


// ============================================================
// 2. GET REQUESTED ROUTE
// ============================================================

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove leading/trailing slashes.
$route = trim($requestUri, '/');


// ============================================================
// 3. HANDLE ROOT ROUTE
// ============================================================

if ($route === '') {
    $route = '';
}


// ============================================================
// 4. CHECK PUBLIC ROUTES
// ============================================================

if (array_key_exists($route, $public_routes)) {

    $file = __DIR__ . '/' . $public_routes[$route];

    if (file_exists($file)) {
        require $file;
        exit;
    }

    http_response_code(500);
    echo "Route file not found.";
    exit;
}


// ============================================================
// 5. CHECK PROTECTED ROUTES
// ============================================================

if (array_key_exists($route, $protected_routes)) {

    // --------------------------------------------------------
    // Require authentication
    // --------------------------------------------------------

    if (!isset($_SESSION['dentist_id'])) {

        header('Location: ' . BASE_URL . '/login');
        exit;
    }


    // --------------------------------------------------------
    // Route to requested PHP file
    // --------------------------------------------------------

    $file = __DIR__ . '/' . $protected_routes[$route];

    if (file_exists($file)) {
        require $file;
        exit;
    }

    http_response_code(500);
    echo "Protected route file not found.";
    exit;
}


// ============================================================
// 6. ROUTE NOT FOUND
// ============================================================

http_response_code(404);

echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 60px 20px;
        }

        h1 {
            font-size: 48px;
            margin-bottom: 10px;
        }

        p {
            color: #666;
        }

        a {
            text-decoration: none;
        }
    </style>
</head>
<body>

    <h1>404</h1>

    <h2>Page Not Found</h2>

    <p>
        The page you requested does not exist.
    </p>

    <a href="/">
        Return to homepage
    </a>

</body>
</html>
HTML;

exit;