<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');
$events = []; 

$target_dentist = $_GET['dentist_id'] ?? null;
$is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent');
$session_dentist = $_SESSION['dentist_id'] ?? null;

// Temporary debug line - check your browser console/network tab
// ... after your variable definitions
if (isset($_GET['debug'])) {
    echo json_encode([
        'debug_info' => [
            'is_super_boolean' => $is_super,
            'role_session' => $_SESSION['role'] ?? 'not set',
            'target_dentist' => $target_dentist,
            'session_id' => session_id()
        ]
    ]);
    exit;
}
try {
    // 1. Ensure $is_super is strictly checked
    $is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent');
    
    // 2. Refined condition: If super AND no specific dentist is requested
    if ($is_super && (empty($target_dentist) || $target_dentist == 'all')) {
        
        // GLOBAL VIEW: Must JOIN dentists to see WHO the appointment is for
        $sql = "SELECT a.start_time as start, a.end_time as end, a.status, d.dentist_name as title 
                FROM appointments a
                JOIN dentists d ON a.dentist_id = d.dentist_id
                WHERE a.status != 'Cancelled'";
        $stmt = $pdo->query($sql);
        
    } else {
        // TARGETED VIEW: For a specific dentist
        $id_to_use = !empty($target_dentist) ? $target_dentist : ($_SESSION['dentist_id'] ?? null);
        
        if (!$id_to_use) {
            echo json_encode([]);
            exit();
        }

        $sql = "SELECT a.start_time as start, a.end_time as end, a.status 
                FROM appointments a 
                WHERE a.dentist_id = ? AND a.status != 'Cancelled'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_to_use]);
    }

    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Set a default title if the JOIN wasn't used
        $row['title'] = $row['title'] ?? "Booked Slot";
        $row['color'] = ($is_super && empty($target_dentist)) ? '#9b59b6' : '#3498db'; 
        $events[] = $row;
    }

    echo json_encode($events);

} catch (Exception $e) {
    echo json_encode([]);
} catch (Exception $e) {
    echo json_encode([]);
}
exit();
?>