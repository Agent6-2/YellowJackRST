<?php
require_once 'config/database.php';
require_once 'includes/week_functions.php';

try {
    $db = getDB();
    
    echo "<h2>Diagnostic et correction des week_id NULL</h2>";
    
    // Vérifier les enregistrements avec week_id NULL dans cleaning_services
    $stmt = $db->query("SELECT COUNT(*) as count FROM cleaning_services WHERE week_id IS NULL");
    $nullCount = $stmt->fetch();
    
    echo "<h3>État actuel:</h3>";
    echo "<p>Nombre de ménages avec week_id = NULL: <strong>{$nullCount['count']}</strong></p>";
    
    // Vérifier le total des ménages
    $stmt = $db->query("SELECT COUNT(*) as total FROM cleaning_services WHERE status = 'completed'");
    $totalCount = $stmt->fetch();
    
    echo "<p>Total des ménages complétés: <strong>{$totalCount['total']}</strong></p>";
    
    // Obtenir la semaine active
    $activeWeek = getActiveWeekNew();
    
    if ($activeWeek) {
        echo "<p>Semaine active: {$activeWeek['week_number']} (ID: {$activeWeek['id']})</p>";
        
        // Vérifier combien de ménages sont assignés à la semaine active
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM cleaning_services WHERE week_id = ? AND status = 'completed'");
        $stmt->execute([$activeWeek['id']]);
        $activeWeekCount = $stmt->fetch();
        
        echo "<p>Ménages assignés à la semaine active: <strong>{$activeWeekCount['count']}</strong></p>";
        
        if ($nullCount['count'] > 0) {
            echo "<h3>Correction en cours...</h3>";
            
            // Assigner tous les ménages NULL à la semaine active
            $stmt = $db->prepare("UPDATE cleaning_services SET week_id = ? WHERE week_id IS NULL");
            $stmt->execute([$activeWeek['id']]);
            
            echo "<p style='color: green;'>✓ {$nullCount['count']} ménages assignés à la semaine active</p>";
            
            // Vérifier après correction
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM cleaning_services WHERE week_id = ? AND status = 'completed'");
            $stmt->execute([$activeWeek['id']]);
            $newActiveWeekCount = $stmt->fetch();
            
            echo "<p>Nouveaux ménages assignés à la semaine active: <strong>{$newActiveWeekCount['count']}</strong></p>";
            echo "<p>CA attendu: <strong>" . ($newActiveWeekCount['count'] * 60) . "€</strong></p>";
            
            // Mettre à jour les statistiques de la semaine
            $realStats = calculateWeekStats($activeWeek['id']);
            
            $stmt = $db->prepare("
                UPDATE weeks SET 
                    total_revenue = ?,
                    total_sales_count = ?,
                    total_cleaning_revenue = ?,
                    total_cleaning_count = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $realStats['total_revenue'],
                $realStats['total_sales_count'],
                $realStats['total_cleaning_revenue'],
                $realStats['total_cleaning_count'],
                $activeWeek['id']
            ]);
            
            echo "<p style='color: green;'>✓ Statistiques de la semaine mises à jour</p>";
            
            // Afficher les nouvelles statistiques
            echo "<h3>Nouvelles statistiques:</h3>";
            echo "<ul>";
            echo "<li>Nombre de ménages: {$realStats['total_cleaning_count']}</li>";
            echo "<li>CA ménages: {$realStats['total_cleaning_revenue']}€</li>";
            echo "<li>CA total: {$realStats['total_revenue']}€</li>";
            echo "</ul>";
            
        } else {
            echo "<p style='color: green;'>Aucune correction nécessaire - tous les ménages sont déjà assignés</p>";
        }
        
    } else {
        echo "<p style='color: red;'>Aucune semaine active trouvée!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
}
?>

<br><br>
<a href="panel/week_management.php">← Retour à la gestion des semaines</a>