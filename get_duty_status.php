<?php
// get_duty_status.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
$date = $_GET['date'] ?? date('Y-m-d');
$target_dentist = $_GET['dentist_id'] ?? null;
$is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent');

try {
    require_once 'db.php';
    if (!isset($pdo)) {
        throw new Exception("Database connection not available");
    }

    // Logic adapted from calendar.php to determine which dentists to look at
    // If NOT super, we only care about the logged-in dentist
    $filter_sql = "";
    $params = [$date];
    
    if (!$is_super) {
        $id_to_use = !empty($target_dentist) ? $target_dentist : ($_SESSION['dentist_id'] ?? null);
        if ($id_to_use) {
            $filter_sql = " AND d.dentist_id = ? ";
            $params[] = $id_to_use;
        }
    }
    
    // First, get the total appointment count for this date
    $total_count_sql = "
        SELECT COUNT(*) as total_appointments
        FROM appointments a
        JOIN dentists d ON a.dentist_id = d.dentist_id
        WHERE DATE(a.start_time) = ?
        AND a.status != 'Cancelled'
        $filter_sql
    ";
    
    $stmt_total = $pdo->prepare($total_count_sql);
    $stmt_total->execute($params);
    $total_result = $stmt_total->fetch(PDO::FETCH_ASSOC);
    $total_appointments = $total_result['total_appointments'] ?? 0;
    
    // Then get individual dentist details
    $query = "
        SELECT 
            d.dentist_id,
            d.dentist_name as name,
            COALESCE(COUNT(a.id), 0) as appointment_count 
        FROM dentists d
        LEFT JOIN appointments a ON d.dentist_id = a.dentist_id 
            AND DATE(a.start_time) = ?
            AND a.status != 'Cancelled'
        WHERE 1=1 $filter_sql
        GROUP BY d.dentist_id, d.dentist_name
        ORDER BY d.dentist_name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $onDuty = [];
    $offDuty = [];
    foreach ($dentists as $dentist) {
        $dentistData = [
            'id' => (int)$dentist['dentist_id'],
            'name' => $dentist['name'],
            'appointments' => (int)$dentist['appointment_count']
        ];
        
        // Logical split based on whether they have appointments on the chosen date
        if ($dentist['appointment_count'] > 0) {
            $onDuty[] = $dentistData;
        } else {
            $offDuty[] = $dentistData;
        }
    }
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'onDuty' => $onDuty,
        'offDuty' => $offDuty,
        'totalOnDuty' => count($onDuty),
        'totalOffDuty' => count($offDuty),
        'totalAppointments' => (int)$total_appointments, // Add total appointments
        'message' => 'Duty status retrieved successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'onDuty' => [],
        'offDuty' => [],
        'totalAppointments' => 0
    ]);
}
exit;
?>