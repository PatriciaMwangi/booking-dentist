<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

ob_clean();
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// Check authorization
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superintendent') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Validate dentist_id
$dentist_id = $_GET['dentist_id'] ?? 0;
if (!is_numeric($dentist_id) || $dentist_id <= 0) {
    echo json_encode(['error' => 'Invalid dentist ID']);
    exit();
}

try {
    // Check if dentist exists
    $stmt = $pdo->prepare("SELECT dentist_name FROM dentists WHERE dentist_id = ?");
    $stmt->execute([$dentist_id]);
    $dentist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dentist) {
        echo json_encode(['error' => 'Dentist not found']);
        exit();
    }

    // Get upcoming appointments with service details
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.service,
            a.start_time,
            a.end_time,
            p.patient_name,
            (
                SELECT COUNT(DISTINCT d2.dentist_id)
                FROM dentists d2
                INNER JOIN specializations s2 ON d2.dentist_id = s2.dentist_id
                WHERE d2.dentist_id != ?
                AND d2.status = 'Active'
                AND s2.service_name = a.service
            ) as available_alternatives
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        WHERE a.dentist_id = ? 
        AND a.start_time >= NOW()
        AND a.status != 'Cancelled'
        ORDER BY a.start_time ASC
    ");
    $stmt->execute([$dentist_id, $dentist_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $appointment_count = count($appointments);
    $can_reassign_all = true;
    $reassignment_preview = [];
    $services_without_alternatives = [];
    
    // Check each appointment for reassignment possibility
    foreach ($appointments as $appt) {
        $has_alternative = $appt['available_alternatives'] > 0;
        
        if (!$has_alternative) {
            $can_reassign_all = false;
            if (!in_array($appt['service'], $services_without_alternatives)) {
                $services_without_alternatives[] = $appt['service'];
            }
        }
        
        $reassignment_preview[] = [
            'id' => $appt['id'],
            'patient' => $appt['patient_name'],
            'service' => $appt['service'],
            'date' => date('M j, Y', strtotime($appt['start_time'])),
            'time' => date('h:i A', strtotime($appt['start_time'])),
            'can_reassign' => $has_alternative,
            'alternatives_count' => $appt['available_alternatives']
        ];
    }
    
    // Calculate statistics
    $reassignable_count = count(array_filter($reassignment_preview, function($a) {
        return $a['can_reassign'];
    }));
    $cancellation_count = $appointment_count - $reassignable_count;
    
    // Return detailed JSON response
    echo json_encode([
        'success' => true,
        'hasUpcomingAppointments' => $appointment_count > 0,
        'appointmentCount' => $appointment_count,
        'reassignableCount' => $reassignable_count,
        'cancellationCount' => $cancellation_count,
        'canReassignAll' => $can_reassign_all,
        'servicesWithoutAlternatives' => $services_without_alternatives,
        'reassignmentPreview' => $reassignment_preview,
        'dentistName' => $dentist['dentist_name']
    ]);
    
} catch (Exception $e) {
    error_log("Error checking dentist appointments: " . $e->getMessage());
    
    echo json_encode([
        'error' => 'Database error occurred',
        'message' => $e->getMessage()
    ]);
}
exit();
?>