<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superintendent') {
    header('Location: ' . BASE_URL . '/login');
    exit();
}

$dentist_id = $_GET['id'] ?? 0;
$target_status = $_GET['status'] ?? '';

// Validate inputs
if (!is_numeric($dentist_id) || $dentist_id <= 0) {
    $_SESSION['error'] = "Invalid dentist ID.";
    header('Location: ' . BASE_URL . '/dentists');
    exit();
}

if (!in_array($target_status, ['Active', 'Inactive'])) {
    $_SESSION['error'] = "Invalid status. Must be 'Active' or 'Inactive'.";
    header('Location: ' . BASE_URL . '/dentists');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Get dentist details
    $stmt = $pdo->prepare("SELECT dentist_id, dentist_name, status FROM dentists WHERE dentist_id = ?");
    $stmt->execute([$dentist_id]);
    $dentist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dentist) {
        throw new Exception("Dentist not found.");
    }
    
    // Check if status is already the same
    if ($dentist['status'] === $target_status) {
        $_SESSION['info'] = "Dentist '{$dentist['dentist_name']}' is already $target_status.";
        header('Location: ' . BASE_URL . '/dentists');
        exit();
    }
    
    // 1. Update dentist status
    $stmt = $pdo->prepare("UPDATE dentists SET status = ? WHERE dentist_id = ?");
    $stmt->execute([$target_status, $dentist_id]);
    
    $reassigned_count = 0;
    $cancelled_count = 0;
    $reassignment_details = [];
    
    // 2. Handle appointment reassignment if deactivating
    if ($target_status === 'Inactive') {
        // Get all future non-cancelled appointments for this dentist
        $stmt = $pdo->prepare("
            SELECT 
                a.id, 
                a.patient_id, 
                a.service, 
                a.start_time, 
                a.end_time,
                p.patient_name,
                p.phone
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            WHERE a.dentist_id = ? 
            AND a.start_time >= NOW()
            AND a.status != 'Cancelled'
            ORDER BY a.start_time ASC
        ");
        $stmt->execute([$dentist_id]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($appointments as $appointment) {
            $appointment_id = $appointment['id'];
            $service = $appointment['service'];
            $start_time = $appointment['start_time'];
            $end_time = $appointment['end_time'];
            $patient_name = $appointment['patient_name'];
            
            // Find the best alternative dentist for this service and time
            $alternative_dentist = findBestAlternativeDentist($pdo, $dentist_id, $service, $start_time, $end_time);
            
            if ($alternative_dentist) {
                // Reassign appointment to alternative dentist
                $stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET dentist_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$alternative_dentist['dentist_id'], $appointment_id]);
                
                $reassigned_count++;
                $reassignment_details[] = [
                    'appointment_id' => $appointment_id,
                    'patient' => $patient_name,
                    'service' => $service,
                    'date' => date('M j, Y', strtotime($start_time)),
                    'time' => date('h:i A', strtotime($start_time)),
                    'old_dentist' => $dentist['dentist_name'],
                    'new_dentist' => $alternative_dentist['dentist_name']
                ];
                
                // Log the reassignment
                logActivity(
                    $_SESSION['dentist_id'], 
                    'Appointment Reassigned', 
                    "Appointment #$appointment_id: {$dentist['dentist_name']} → Dr. {$alternative_dentist['dentist_name']}"
                );
                
            } else {
                // No suitable dentist found - cancel as last resort
                $stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET status = 'Cancelled'
                    WHERE id = ?
                ");
                $stmt->execute([$appointment_id]);
                $cancelled_count++;
                
                logActivity(
                    $_SESSION['dentist_id'], 
                    'Appointment Cancelled', 
                    "Appointment #$appointment_id cancelled - No alternative dentist for $service"
                );
            }
        }
    }
    
    $pdo->commit();
    
    // Log the status change
    logActivity(
        $_SESSION['dentist_id'], 
        'Dentist Status Changed', 
        "{$dentist['dentist_name']}: {$dentist['status']} → $target_status. Reassigned: $reassigned_count, Cancelled: $cancelled_count"
    );
    
    // Prepare success message with details
    $success_message = "✅ Dentist '{$dentist['dentist_name']}' status changed to $target_status.";
    
    if ($target_status === 'Inactive' && ($reassigned_count > 0 || $cancelled_count > 0)) {
        $success_message .= "<br><br><strong>Appointment Updates:</strong><br>";
        
        if ($reassigned_count > 0) {
            $success_message .= "✓ <span style='color: #27ae60;'>$reassigned_count appointment(s) reassigned to other dentists</span><br>";
        }
        if ($cancelled_count > 0) {
            $success_message .= "✗ <span style='color: #e74c3c;'>$cancelled_count appointment(s) cancelled (no suitable alternative)</span>";
        }
    }
    
    $_SESSION['success'] = $success_message;
    
    // Store reassignment details for display
    if (!empty($reassignment_details)) {
        $_SESSION['reassignment_details'] = $reassignment_details;
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "❌ Error: " . $e->getMessage();
    error_log("Toggle dentist status error: " . $e->getMessage());
}

header('Location: ' . BASE_URL . '/dentists');
exit();

/**
 * Find the best alternative dentist for a given service and time slot
 * Priority:
 * 1. Same service specialization
 * 2. No time conflict
 * 3. Lowest workload on that day
 */
function findBestAlternativeDentist($pdo, $current_dentist_id, $service, $start_time, $end_time) {
    // Strategy 1: Find dentist with same service, no conflicts, lowest workload
    $stmt = $pdo->prepare("
        SELECT 
            d.dentist_id,
            d.dentist_name,
            COUNT(a.id) as daily_appointments
        FROM dentists d
        INNER JOIN specializations s ON d.dentist_id = s.dentist_id
        LEFT JOIN appointments a ON d.dentist_id = a.dentist_id 
            AND DATE(a.start_time) = DATE(?)
            AND a.status != 'Cancelled'
        WHERE d.dentist_id != ?
        AND d.status = 'Active'
        AND s.service_name = ?
        AND NOT EXISTS (
            SELECT 1 FROM appointments a2
            WHERE a2.dentist_id = d.dentist_id
            AND a2.status != 'Cancelled'
            AND (
                (a2.start_time < ? AND a2.end_time > ?) OR
                (a2.start_time < ? AND a2.end_time > ?) OR
                (a2.start_time >= ? AND a2.start_time < ?)
            )
        )
        GROUP BY d.dentist_id, d.dentist_name
        ORDER BY daily_appointments ASC, d.dentist_id ASC
        LIMIT 1
    ");
    
    $stmt->execute([
        $start_time,                // For DATE comparison
        $current_dentist_id,
        $service,
        $end_time, $start_time,    // Conflict check 1
        $end_time, $start_time,    // Conflict check 2
        $start_time, $end_time     // Conflict check 3
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no conflict-free dentist found, try to find anyone with the service
    // (accepting potential overlap - superintendent can manually adjust later)
    if (!$result) {
        $stmt = $pdo->prepare("
            SELECT 
                d.dentist_id,
                d.dentist_name,
                COUNT(a.id) as daily_appointments
            FROM dentists d
            INNER JOIN specializations s ON d.dentist_id = s.dentist_id
            LEFT JOIN appointments a ON d.dentist_id = a.dentist_id 
                AND DATE(a.start_time) = DATE(?)
                AND a.status != 'Cancelled'
            WHERE d.dentist_id != ?
            AND d.status = 'Active'
            AND s.service_name = ?
            GROUP BY d.dentist_id, d.dentist_name
            ORDER BY daily_appointments ASC, d.dentist_id ASC
            LIMIT 1
        ");
        
        $stmt->execute([$start_time, $current_dentist_id, $service]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $result;
}

/**
 * Log activity to activity_logs table
 */
function logActivity($user_id, $action, $details) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255)
        ]);
    } catch (Exception $e) {
        // If activity_logs table doesn't exist, just log to error log
        error_log("Activity log: $action - $details");
    }
}
?>