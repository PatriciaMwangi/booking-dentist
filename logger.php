<?php
function logActivity($pdo, $action, $description, $dentist_id = null) {
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $sql = "INSERT INTO activity_logs (dentist_id, action, description, ip_address, user_agent) 
                VALUES (:dentist_id, :action, :description, :ip_address, :user_agent)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':dentist_id' => $dentist_id,
            ':action' => $action,
            ':description' => $description,
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Logging error: " . $e->getMessage());
        return false;
    }
}

// Common log actions
function logLogin($pdo, $dentist_id, $dentist_name) {
    return logActivity('LOGIN', "Dentist {$dentist_name} logged in", $dentist_id);
}

function logLogout($pdo, $dentist_id, $dentist_name) {
    return logActivity('LOGOUT', "Dentist {$dentist_name} logged out", $dentist_id);
}

function logAppointment($pdo, $dentist_id, $action, $appointment_id, $patient_name) {
    $description = "{$action} appointment #{$appointment_id} for patient {$patient_name}";
    return logActivity('APPOINTMENT', $description, $dentist_id);
}

function logPatient($pdo, $dentist_id, $action, $patient_name) {
    $description = "{$action} patient {$patient_name}";
    return logActivity('PATIENT', $description, $dentist_id);
}

function logDentist($pdo, $dentist_id, $action, $target_dentist_name) {
    $description = "{$action} dentist {$target_dentist_name}";
    return logActivity('DENTIST', $description, $dentist_id);
}

function logService($pdo, $dentist_id, $action, $service_name) {
    $description = "{$action} service: {$service_name}";
    return logActivity('SERVICE', $description, $dentist_id);
}
?>