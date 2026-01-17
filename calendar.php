<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

$target_dentist = $_GET['dentist_id'] ?? null;
$is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent');
$session_dentist = $_SESSION['dentist_id'];

try {
    if ($is_super && !$target_dentist) {
        // GLOBAL VIEW: Show Dentist Names (Note the corrected dentist_id column)
        $sql = "SELECT a.appointment_date as start, d.dentist_name as title, a.status 
                FROM appointments a 
                JOIN dentists d ON a.dentist_id = d.dentist_id";
        $stmt = $pdo->query($sql);
    } else {
        // TARGETED VIEW: Show Appointment Times
        $id_to_use = $target_dentist ? $target_dentist : $session_dentist;
        $sql = "SELECT a.appointment_date as start, a.appointment_time as title, a.status 
                FROM appointments a 
                WHERE a.dentist_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_to_use]);
    }

    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Formatting: Only add "Dr." if it's the Global Name view
        if ($is_super && !$target_dentist) {
            if (stripos($row['title'], 'Dr. ') === false) {
                $row['title'] = 'Dr. ' . $row['title'];
            }
        }

        // Color Logic
        if ($target_dentist || !$is_super) {
            if ($row['status'] == 'Completed') $row['color'] = '#27ae60';
            elseif ($row['status'] == 'Cancelled') $row['color'] = '#e74c3c';
            else $row['color'] = '#3498db'; 
        } else {
            $row['color'] = '#9b59b6'; // Purple for global
        }
        $events[] = $row;
    }
    echo json_encode($events);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
exit();
?>