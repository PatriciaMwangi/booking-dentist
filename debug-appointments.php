<?php
require_once 'db.php';
session_start();

// Get all appointments
$sql = "SELECT 
            a.id,
            a.start_time,
            a.end_time,
            a.status,
            p.patient_name,
            d.dentist_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN dentists d ON a.dentist_id = d.dentist_id
        WHERE a.status != 'Cancelled'
        ORDER BY a.start_time";

$stmt = $pdo->query($sql);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by date
$by_date = [];
foreach ($appointments as $appt) {
    $start = new DateTime($appt['start_time']);
    $date = $start->format('Y-m-d');
    
    if (!isset($by_date[$date])) {
        $by_date[$date] = [];
    }
    $by_date[$date][] = $appt;
}

// Sort dates
ksort($by_date);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Count Debug</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: #f5f6fa;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .date-group {
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .date-header {
            background: #3498db;
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .count-badge {
            background: rgba(255,255,255,0.3);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        .appointment-list {
            padding: 15px 20px;
            background: #f8f9fa;
        }
        .appointment-item {
            padding: 10px;
            margin-bottom: 10px;
            background: white;
            border-left: 4px solid #3498db;
            border-radius: 4px;
            display: grid;
            grid-template-columns: 80px 150px 1fr 150px 100px;
            gap: 15px;
            align-items: center;
        }
        .appt-id {
            font-weight: bold;
            color: #7f8c8d;
        }
        .appt-time {
            font-size: 13px;
            color: #34495e;
        }
        .appt-patient {
            font-weight: 500;
            color: #2c3e50;
        }
        .appt-dentist {
            color: #7f8c8d;
            font-size: 14px;
        }
        .appt-status {
            text-align: center;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .summary {
            background: #ecf0f1;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .summary-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-value {
            font-size: 32px;
            font-weight: bold;
            color: #3498db;
        }
        .summary-label {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Appointment Count Debug - By Date</h1>
        
        <div class="summary">
            <h3 style="margin-top: 0;">Summary</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-value"><?= count($appointments) ?></div>
                    <div class="summary-label">Total Appointments</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?= count($by_date) ?></div>
                    <div class="summary-label">Dates with Appointments</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">
                        <?php 
                        $counts = array_map('count', $by_date);
                        echo !empty($counts) ? round(array_sum($counts) / count($counts), 1) : 0;
                        ?>
                    </div>
                    <div class="summary-label">Avg per Day</div>
                </div>
            </div>
        </div>
        
        <?php foreach ($by_date as $date => $date_appointments): ?>
        <div class="date-group">
            <div class="date-header">
                <span><?= date('l, F j, Y', strtotime($date)) ?></span>
                <span class="count-badge"><?= count($date_appointments) ?> appointment<?= count($date_appointments) != 1 ? 's' : '' ?></span>
            </div>
            <div class="appointment-list">
                <?php foreach ($date_appointments as $appt): 
                    $start = new DateTime($appt['start_time']);
                    $end = new DateTime($appt['end_time']);
                ?>
                <div class="appointment-item">
                    <div class="appt-id">#<?= $appt['id'] ?></div>
                    <div class="appt-time">
                        <?= $start->format('h:i A') ?><br>
                        <small style="color: #95a5a6;">to <?= $end->format('h:i A') ?></small>
                    </div>
                    <div class="appt-patient"><?= htmlspecialchars($appt['patient_name']) ?></div>
                    <div class="appt-dentist">Dr. <?= htmlspecialchars($appt['dentist_name']) ?></div>
                    <div class="appt-status status-<?= strtolower($appt['status']) ?>">
                        <?= $appt['status'] ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($by_date)): ?>
        <div style="text-align: center; padding: 40px; color: #95a5a6;">
            <h3>No appointments found</h3>
            <p>There are no non-cancelled appointments in the database.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>