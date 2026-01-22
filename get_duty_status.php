<?php
// get_duty_status.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session to access roles/IDs as done in calendar.php
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


    // Main query combining your duty logic with calendar.php's filtering
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
        'message' => 'Duty status retrieved successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'onDuty' => [],
        'offDuty' => []
    ]);
}
exit;
?>