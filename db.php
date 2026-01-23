<?php
require_once 'env_loader.php';

if (file_exists(__DIR__ . '/.env')) {
    loadEnv(__DIR__ . '/.env');
}

// Fetch values from Render Environment Variables
$host     = getenv('DB_HOST') ?: 'localhost';
$port     = getenv('DB_PORT') ?: '3306'; // Added for Aiven
$dbname   = getenv('DB_NAME') ?: 'booking_dentist';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

try {
    // We added port=$port to the connection string
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}