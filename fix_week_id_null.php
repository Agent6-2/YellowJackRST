<?php
require_once 'config/database.php';
require_once 'includes/week_functions.php';

try {
    $db = getDB();
    
    echo "<h2>Diagnostic complet du système de semaines</h2>";
    
    // Obtenir la semaine active
    $activeWeek = getActiveWeekNew();
    
    if ($activeWeek) {
        echo "<h3>Semaine active:</h3>";
        echo "<p>Semaine {$activeWeek['week_number']} (ID: {$activeWeek['id']})</p>";
        echo "<p>Période: {$activeWeek['week_start']} à {$activeWeek['week_end']}</p>";
        
        // Vérifier les données actuelles dans la table weeks
        $stmt = $db->prepare("SELECT total_cleaning_count, total_cleaning_revenue, total_revenue, total_sales_count, total_sales_revenue FROM weeks WHERE id = ?");
        $stmt->execute([$activeWeek['id']]);
        $weekData = $stmt->fetch();
        
        echo "<h3>Données stockées dans la table weeks:</h3>";
        echo "<ul>";
        echo "<li>Nombre de ménages: {$weekData['total_cleaning_count']}</li>";
        echo "<li>CA ménages: {$weekData['total_cleaning_revenue']}€</li>";
        echo "<li>Nombre de ventes: {$weekData['total_sales_count']}</li>";
        echo "<li>CA ventes: {$weekData['total_sales_revenue']}€</li>";
        echo "<li>CA total: {$weekData['total_revenue']}€</li>";
        echo "</ul>";
        
        // Vérifier directement dans cleaning_services
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM cleaning_services WHERE week_id = ? AND status = 'completed'");
        $stmt->execute([$activeWeek['id']]);
        $realCleaningCount = $stmt->fetch();
        
        echo "<h3>Données réelles dans cleaning_services:</h3>";
        echo "<ul>";
        echo "<li>Ménages complétés pour cette semaine: {$realCleaningCount['count']}</li>";
        echo "<li>CA attendu (x60): " . ($realCleaningCount['count'] * 60) . "€</li>";
        echo "</ul>";
        
        // Vérifier tous les ménages dans la base
        $stmt = $db->query("SELECT COUNT(*) as total FROM cleaning_services WHERE status = 'completed'");
        $totalCleaning = $stmt->fetch();
        
        echo "<h3>Total dans la base de données:</h3>";
        echo "<ul>";
        echo "<li>Total ménages complétés: {$totalCleaning['total']}</li>";
        echo "<li>CA total possible: " . ($totalCleaning['total'] * 60) . "€</li>";
        echo "</ul>";
        
        // Vérifier la répartition par semaine
        $stmt = $db->query("SELECT week_id, COUNT(*) as count FROM cleaning_services WHERE status = 'completed' GROUP BY week_id ORDER BY week_id");
        $distribution = $stmt->fetchAll();
        
        echo "<h3>Répartition des ménages par semaine:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Week ID</th><th>Nombre de ménages</th><th>CA (x60)</th></tr>";
        foreach ($distribution as $row) {
            $weekId = $row['week_id'] ?? 'NULL';
            $count = $row['count'];
            $ca = $count * 60;
            echo "<tr><td>{$weekId}</td><td>{$count}</td><td>{$ca}€</td></tr>";
        }
        echo "</table>";
        
        // Calculer les vraies statistiques
        $realStats = calculateWeekStats($activeWeek['id']);
        
        echo "<h3>Calcul en temps réel (fonction calculateWeekStats):</h3>";
        echo "<ul>";
        echo "<li>Nombre de ménages: {$realStats['total_cleaning_count']}</li>";
        echo "<li>CA ménages: {$realStats['total_cleaning_revenue']}€</li>";
        echo "<li>CA total: {$realStats['total_revenue']}€</li>";
        echo "</ul>";
        
        // Proposer une correction si nécessaire
        if ($weekData['total_cleaning_count'] != $realStats['total_cleaning_count'] || 
            $weekData['total_cleaning_revenue'] != $realStats['total_cleaning_revenue']) {
            
            echo "<h3 style='color: orange;'>Incohérence détectée - Correction proposée:</h3>";
            echo "<form method='post'>";
            echo "<p>Voulez-vous mettre à jour les statistiques de la semaine ?</p>";
            echo "<input type='submit' name='update_stats' value='Mettre à jour' style='background: green; color: white; padding: 10px;'>";
            echo "</form>";
            
            if (isset($_POST['update_stats'])) {
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
                
                echo "<p style='color: green; font-weight: bold;'>✓ Statistiques mises à jour avec succès!</p>";
                echo "<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
            }
        } else {
            echo "<p style='color: green; font-weight: bold;'>✓ Les données sont cohérentes</p>";
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