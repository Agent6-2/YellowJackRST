<?php
// Vérifier le statut des finalisations différées

require_once '../includes/auth.php';

try {
    $db = new PDO("sqlite:../database/yellowjack.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer toutes les finalisations différées
    $stmt = $db->prepare("
        SELECT * FROM delayed_finalizations 
        ORDER BY finalization_time DESC
        LIMIT 10
    ");
    $stmt->execute();
    $delayed_finalizations = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    
    $response = [
        'success' => true,
        'finalizations' => []
    ];
    
    foreach ($delayed_finalizations as $finalization) {
        $time_remaining = null;
        $can_execute_now = false;
        
        if ($finalization['status'] === 'pending') {
            $execution_timestamp = strtotime($finalization['execution_time']);
            $current_timestamp = time();
            
            if ($execution_timestamp <= $current_timestamp) {
                $can_execute_now = true;
                $time_remaining = 0;
            } else {
                $time_remaining = $execution_timestamp - $current_timestamp;
            }
        }
        
        $response['finalizations'][] = [
            'id' => $finalization['id'],
            'week_start' => $finalization['week_start'],
            'week_end' => $finalization['week_end'],
            'finalization_time' => $finalization['finalization_time'],
            'execution_time' => $finalization['execution_time'],
            'status' => $finalization['status'],
            'time_remaining' => $time_remaining,
            'can_execute_now' => $can_execute_now,
            'executed_at' => $finalization['executed_at']
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>