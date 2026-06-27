<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test Page</h1>";

if (!isset($_SESSION['dentist_id'])) {
    echo "Not logged in<br>";
    exit();
}

echo "Logged in as dentist ID: " . $_SESSION['dentist_id'] . "<br>";

require_once 'db.php';

// Simple test
$test = $pdo->query("SELECT 1 as test");
$result = $test->fetch();
echo "Database test: " . $result['test'] . "<br>";

// Try to count patients
$count = $pdo->query("SELECT COUNT(*) as total FROM patients")->fetchColumn();
echo "Total patients in database: " . $count . "<br>";

// Try your query with hardcoded dentist_id for testing
$dentist_id = $_SESSION['dentist_id'];
$sql = "SELECT p.patient_name FROM patients p 
        INNER JOIN appointments a ON p.patient_id = a.patient_id 
        WHERE a.dentist_id = ? LIMIT 5";
        
$stmt = $pdo->prepare($sql);
$stmt->execute([$dentist_id]);
$patients = $stmt->fetchAll();

echo "Patients for dentist $dentist_id: " . count($patients) . "<br>";
if (count($patients) > 0) {
    echo "<pre>" . print_r($patients, true) . "</pre>";
}
?>