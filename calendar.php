<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

header('Content-Type: application/json');
$events = []; 

$target_dentist = $_GET['dentist_id'] ?? null;
$is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'superintendent');
$session_dentist = $_SESSION['dentist_id'] ?? null;
$viewing_specific_dentist = !empty($target_dentist) && $target_dentist !== 'all';
$dentist_id_in_url = $target_dentist;

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
    
    if ($is_super && (empty($target_dentist) || $target_dentist == 'all')) {
        // GLOBAL VIEW: Get ALL appointments with full details
        $sql = "SELECT 
                    a.id,
                    a.start_time,
                    a.end_time,
                    a.status,
                    a.service,
                    p.patient_name,
                    p.phone,
                    d.dentist_name,
                    d.dentist_id
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                JOIN dentists d ON a.dentist_id = d.dentist_id
                WHERE a.status != 'Cancelled'
                ORDER BY a.start_time";
        $stmt = $pdo->query($sql);
    } else {
        // TARGETED VIEW: Specific dentist
        $id_to_use = !empty($target_dentist) ? $target_dentist : ($_SESSION['dentist_id'] ?? null);
        
        if (!$id_to_use) {
            echo json_encode([]);
            exit();
        }

        $sql = "SELECT 
                    a.id,
                    a.start_time,
                    a.end_time,
                    a.status,
                    a.service,
                    p.patient_name,
                    p.phone,
                    d.dentist_name,
                    d.dentist_id
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                JOIN dentists d ON a.dentist_id = d.dentist_id
                WHERE a.dentist_id = ? AND a.status != 'Cancelled'
                ORDER BY a.start_time";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_to_use]);
    }

    // Fetch all appointments
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create two types of events:
    // 1. Individual appointment events (for day/week view)
    // 2. Count badges (for month view)
    
    $events = [];
    $date_counts = [];
    
    foreach ($appointments as $appt) {
        $start_datetime = new DateTime($appt['start_time']);
        $end_datetime = new DateTime($appt['end_time']);
        
        // Get the date for counting
        $date = $start_datetime->format('Y-m-d');
        
        // Count appointments per date
        if (!isset($date_counts[$date])) {
            $date_counts[$date] = 0;
        }
        $date_counts[$date]++;
        
        // Determine status color
        $status_colors = [
            'Pending' => '#f39c12',
            'Completed' => '#27ae60',
            'Cancelled' => '#e74c3c'
        ];
        $status_color = $status_colors[$appt['status']] ?? '#3498db';
        
        // Create title based on user role
// Get the dentist name from URL if viewing specific dentist
$viewed_dentist_name = '';
if ($viewing_specific_dentist) {
    $stmt = $pdo->prepare("SELECT dentist_name FROM dentists WHERE dentist_id = ?");
    $stmt->execute([$dentist_id_in_url]);
    $viewed_dentist = $stmt->fetch();
    $viewed_dentist_name = $viewed_dentist['dentist_name'] ?? 'Unknown Dentist';
}

if ($is_super) {
    if ($viewing_specific_dentist) {
        // Superintendent viewing Dr. X's schedule
        $title = "👤 {$appt['patient_name']}\n🦷 {$appt['service']}\n👨‍⚕️ Viewing Dr. {$viewed_dentist_name}'s Schedule";
        $display_title = "Dr. {$viewed_dentist_name}'s Patient - {$appt['phone']}[{$appt['service']}]";
    } else {
        // Superintendent viewing ALL appointments
        $title = "👤 {$appt['patient_name']}\n🦷 {$appt['service']}\n👨‍⚕️ Dr. {$appt['dentist_name']}";
        $display_title = "{$appt['dentist_name']} - {$appt['service']}";
    }
} else {
    // Regular dentist viewing their own schedule
    $title = "👤 {$appt['patient_name']}\n🦷 {$appt['service']}";
    $display_title = "{$appt['patient_name']}({$appt['phone']}) - {$appt['service']}";
}
        // Create individual appointment event (visible in day/week view)
        $appointment_event = [
            'id' => 'appt-' . $appt['id'],
            'title' => $display_title,
            'start' => $appt['start_time'],
            'end' => $appt['end_time'],
            'backgroundColor' => $status_color,
            'borderColor' => $status_color,
            'textColor' => '#ffffff',
            'classNames' => ['appointment-event'],
            'extendedProps' => [
                'appointment_id' => $appt['id'],
                'patient_name' => $appt['patient_name'],
                'phone' => $appt['phone'],
                'service' => $appt['service'],
                'status' => $appt['status'],
                'dentist_name' => $appt['dentist_name'],
                'dentist_id' => $appt['dentist_id'],
                'is_superintendent' => $is_super,
                'is_appointment' => true,
                'full_title' => $title
            ]
        ];
        
        $events[] = $appointment_event;
    }
    
    // Add count badge events (for month view)
    foreach ($date_counts as $date => $count) {
        // Determine color based on count
        if ($count >= 5) {
            $color = '#e74c3c'; // Red for high load
        } elseif ($count >= 3) {
            $color = '#f39c12'; // Orange for medium load
        } else {
            $color = ($is_super && empty($target_dentist)) ? '#9b59b6' : '#3498db';
        }
        
        // Create count badge event
        $count_event = [
            'id' => 'count-' . $date,
            'start' => $date,
            'end' => $date,
            'allDay' => true,
            'display' => 'background',
            'backgroundColor' => 'transparent',
            'classNames' => ['count-day-cell'],
            'extendedProps' => [
                'appointment_count' => $count,
                'badge_color' => $color,
                'is_count_event' => true,
                'date' => $date
            ]
        ];
        
        $events[] = $count_event;
    }
    
    // Debug logging
    error_log("Calendar Data - Total appointments: " . count($appointments));
    error_log("Calendar Data - Total events (appointments + badges): " . count($events));

    echo json_encode($events);

} catch (Exception $e) {
    error_log("Calendar data error: " . $e->getMessage());
    echo json_encode([]);
}
exit();
?>