<?php
// get-dentists-for-service.php
error_log("=== get-dentists-for-service.php called ===");
error_log("GET params: " . print_r($_GET, true));

// Turn on ALL error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['dentist_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$service = $_GET['service'] ?? '';
$exclude_dentist_id = $_GET['exclude'] ?? 0;

if (empty($service)) {
    echo json_encode(['error' => 'Service required']);
    exit();
}

try {
    // Get current appointment details to check for time conflicts
    $appointment_id = $_GET['appointment_id'] ?? 0;
    $appointment_time = null;
    
    if ($appointment_id) {
        $stmt = $pdo->prepare("SELECT start_time, end_time FROM appointments WHERE id = ?");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        if ($appointment) {
            $appointment_time = $appointment;
        }
    }
    
    // Find dentists who offer this service (excluding the current one)
    $sql = "
        SELECT 
            d.dentist_id as id,
            d.dentist_name as name,
            d.email,
            d.status,
            COUNT(a.id) as appointment_count,
            (
                SELECT COUNT(*) 
                FROM appointments a2 
                WHERE a2.dentist_id = d.dentist_id 
                AND DATE(a2.start_time) = CURDATE()
                AND a2.status != 'Cancelled'
            ) as today_appointments
    ";
    
    // Add time conflict check if we have appointment time
    if ($appointment_time) {
        $sql .= ",
            (
                SELECT COUNT(*) 
                FROM appointments a3 
                WHERE a3.dentist_id = d.dentist_id 
                AND a3.status != 'Cancelled'
                AND a3.id != ?
                AND (
                    (a3.start_time < ? AND a3.end_time > ?) OR
                    (a3.start_time >= ? AND a3.start_time < ?)
                )
            ) as upcoming_conflicts
        ";
    }
    
    $sql .= "
        FROM dentists d
        JOIN specializations s ON d.dentist_id = s.dentist_id
        LEFT JOIN appointments a ON d.dentist_id = a.dentist_id 
            AND DATE(a.start_time) = CURDATE()
            AND a.status != 'Cancelled'
        WHERE d.status = 'Active'
        AND s.service_name = ?
        AND d.dentist_id != ?
        GROUP BY d.dentist_id, d.dentist_name, d.email, d.status
        ORDER BY 
            appointment_count ASC,
            d.dentist_name ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    
    if ($appointment_time) {
        $stmt->execute([
            $appointment_id,
            $appointment_time['end_time'], $appointment_time['start_time'],
            $appointment_time['start_time'], $appointment_time['end_time'],
            $service,
            $exclude_dentist_id
        ]);
    } else {
        $stmt->execute([
            $service,
            $exclude_dentist_id
        ]);
    }
    
    $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find recommended dentist (no conflicts, fewest appointments)
    $recommended_dentist = null;
    foreach ($dentists as $dentist) {
        if (empty($dentist['upcoming_conflicts']) && 
            ($recommended_dentist === null || 
             $dentist['appointment_count'] < $recommended_dentist['appointment_count'])) {
            $recommended_dentist = $dentist;
        }
    }
    
    // If no dentist without conflicts, pick one with fewest appointments
    if (!$recommended_dentist && count($dentists) > 0) {
        usort($dentists, function($a, $b) {
            return $a['appointment_count'] - $b['appointment_count'];
        });
        $recommended_dentist = $dentists[0];
    }
    
    echo json_encode([
        'success' => true,
        'dentists' => $dentists,
        'recommended_dentist' => $recommended_dentist,
        'service' => $service,
        'total_available' => count($dentists)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>