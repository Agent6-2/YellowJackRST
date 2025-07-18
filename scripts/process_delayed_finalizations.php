<?php
// Script pour traiter les finalisations différées après 1 heure

require_once '../includes/auth_local.php';

try {
    $db = new PDO("sqlite:../database/yellowjack.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer les finalisations en attente dont l'heure d'exécution est dépassée
    $stmt = $db->prepare("
        SELECT * FROM delayed_finalizations 
        WHERE status = 'pending' 
        AND execution_time <= datetime('now')
    ");
    $stmt->execute();
    $pending_finalizations = $stmt->fetchAll();
    
    foreach ($pending_finalizations as $finalization) {
        try {
            // Commencer une transaction
            $db->beginTransaction();
            
            // Calculer le chiffre d'affaires de la semaine finalisée
            $week_start = $finalization['week_start'];
            $week_end = $finalization['week_end'];
            
            // Calculer les ventes
            $stmt_sales = $db->prepare("
                SELECT COALESCE(SUM(final_amount), 0) as sales_revenue 
                FROM sales 
                WHERE created_at >= ? AND created_at <= ?
            ");
            $week_end_timestamp = date('Y-m-d H:i:s', strtotime($week_end . ' 23:59:59'));
            $stmt_sales->execute([$week_start, $week_end_timestamp]);
            $sales_result = $stmt_sales->fetch();
            
            // Calculer le salaire ménage
            $stmt_cleaning = $db->prepare("
                SELECT COALESCE(SUM(total_salary), 0) as cleaning_revenue 
                FROM cleaning_services 
                WHERE start_time >= ? AND start_time <= ? AND status = 'completed'
            ");
            $stmt_cleaning->execute([$week_start, $week_end_timestamp]);
            $cleaning_result = $stmt_cleaning->fetch();
            
            $total_revenue = $sales_result['sales_revenue'] + $cleaning_result['cleaning_revenue'];
            
            // Calculer les taxes (exemple simple - à adapter selon vos règles)
            $tax_rate = 0.20; // 20% par exemple
            $tax_amount = $total_revenue * $tax_rate;
            
            // Mettre à jour la semaine avec les données calculées
            $stmt_update = $db->prepare("
                UPDATE weekly_taxes 
                SET total_revenue = ?, tax_amount = ?, effective_tax_rate = ?, 
                    tax_breakdown = ?, is_finalized = TRUE, finalized_at = ?
                WHERE week_start = ?
            ");
            
            $tax_breakdown = json_encode([
                'sales_revenue' => $sales_result['sales_revenue'],
                'cleaning_revenue' => $cleaning_result['cleaning_revenue'],
                'total_revenue' => $total_revenue,
                'tax_rate' => $tax_rate,
                'tax_amount' => $tax_amount
            ]);
            
            $stmt_update->execute([
                $total_revenue,
                $tax_amount,
                $tax_rate,
                $tax_breakdown,
                $finalization['finalization_time'],
                $week_start
            ]);
            
            // Marquer la finalisation comme exécutée
            $stmt_mark = $db->prepare("
                UPDATE delayed_finalizations 
                SET status = 'executed', executed_at = datetime('now')
                WHERE id = ?
            ");
            $stmt_mark->execute([$finalization['id']]);
            
            // Valider la transaction
            $db->commit();
            
            echo "Finalisation exécutée pour la semaine du " . date('d/m/Y', strtotime($week_start)) . " au " . date('d/m/Y', strtotime($week_end)) . "\n";
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            $db->rollback();
            
            // Marquer la finalisation comme échouée
            $stmt_error = $db->prepare("
                UPDATE delayed_finalizations 
                SET status = 'failed', executed_at = datetime('now')
                WHERE id = ?
            ");
            $stmt_error->execute([$finalization['id']]);
            
            echo "Erreur lors de l'exécution de la finalisation ID " . $finalization['id'] . " : " . $e->getMessage() . "\n";
        }
    }
    
    if (empty($pending_finalizations)) {
        echo "Aucune finalisation en attente à traiter.\n";
    }
    
} catch (Exception $e) {
    echo "Erreur générale : " . $e->getMessage() . "\n";
}
?>