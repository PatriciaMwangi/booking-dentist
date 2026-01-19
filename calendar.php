<?php
require_once 'db.php';

header('Content-Type: application/json');
$events = []; 

$target_dentist = $_GET['dentist_id'] ?? null;
$is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent');
$session_dentist = $_SESSION['dentist_id'] ?? null;


try {
    if ($is_super && !$target_dentist) {
        // GLOBAL VIEW
        $sql = "SELECT a.start_time as start, a.end_time as end, a.status 
                FROM appointments a";
        $stmt = $pdo->query($sql);
    } else {
        // TARGETED VIEW
        $id_to_use = $target_dentist ? $target_dentist : $session_dentist;
        
        if (!$id_to_use) {
            echo json_encode([]);
            exit();
        }

        $sql = "SELECT a.start_time as start, a.end_time as end, a.status 
                FROM appointments a 
                WHERE a.dentist_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_to_use]);
    }

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Set title to empty to remove text from calendar dates
        $row['title'] = ""; 

        // Apply Logic: Blue for active/pending/completed, keep cancelled hidden or different
        if ($row['status'] === 'Cancelled') {
            // Option A: Hide cancelled completely (skip adding to array)
            continue; 
            // Option B: If you want to see them as red dots, uncomment the next line:
            // $row['color'] = '#e74c3c'; 
        } else {
            // Shade all other activities Blue
            $row['color'] = '#3498db'; 
        }

        $events[] = $row;
    }

    echo json_encode($events);

} catch (Exception $e) {
    echo json_encode([]);
}
exit();
?>