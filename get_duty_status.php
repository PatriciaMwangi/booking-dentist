<?php
// get_duty_status.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header FIRST - before any output
header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');

try {
    // Include database connection
    require_once 'db.php';
    
    if (!isset($pdo) || !$pdo) {
        throw new Exception("Database connection not available");
    }
    
    // Simple test query - check if database is working
    $testStmt = $pdo->query("SELECT COUNT(*) as count FROM dentists");
    $testResult = $testStmt->fetch();
    
    // Now get duty status for the specific date
    $stmt = $pdo->prepare("
        SELECT 
            d.dentist_id,
            d.dentist_name as name,
            COUNT(a.id) as appointment_count 
        FROM dentists d
        LEFT JOIN appointments a ON d.dentist_id = a.dentist_id 
            AND DATE(a.start_time) = ?
            AND a.status != 'cancelled'
        GROUP BY d.dentist_id, d.dentist_name
        ORDER BY d.dentist_name
    ");
    
    // Execute with the date parameter
    $stmt->execute([$date]);
    $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Split into on-duty and off-duty
    $onDuty = [];
    $offDuty = [];
    
    foreach ($dentists as $dentist) {
        if ($dentist['appointment_count'] > 0) {
            $onDuty[] = [
                'id' => $dentist['dentist_id'],
                'name' => $dentist['name'],
                'appointments' => (int)$dentist['appointment_count']
            ];
        } else {
            $offDuty[] = [
                'id' => $dentist['dentist_id'],
                'name' => $dentist['name'],
                'appointments' => 0
            ];
        }
    }
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'date' => $date,
        'database_test' => 'Connection successful - ' . $testResult['count'] . ' total dentists',
        'onDuty' => $onDuty,
        'offDuty' => $offDuty,
        'totalOnDuty' => count($onDuty),
        'totalOffDuty' => count($offDuty),
        'message' => 'Duty status retrieved successfully'
    ]);
    
} catch (PDOException $e) {
    // Database error
    error_log("PDO Error in get_duty_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'date' => $date,
        'onDuty' => [],
        'offDuty' => []
    ]);
} catch (Exception $e) {
    // General error
    error_log("Error in get_duty_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred',
        'date' => $date,
        'onDuty' => [],
        'offDuty' => []
    ]);
}

exit;
?>