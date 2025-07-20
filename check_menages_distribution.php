<?php
require_once 'config/database.php';
require_once 'includes/week_functions.php';

try {
    $db = getDB();
    
    echo "<h2>Distribution des ménages par semaine</h2>";
    
    // Obtenir la semaine active
    $activeWeek = getActiveWeekNew();
    
    if ($activeWeek) {
        echo "<p>Semaine active: {$activeWeek['week_number']} (ID: {$activeWeek['id']})</p>";
    }
    
    // Vérifier la distribution des ménages par semaine
    echo "<h3>Distribution par semaine:</h3>";
    $stmt = $db->query("
        SELECT 
            w.week_number,
            w.id as week_id,
            COUNT(cs.id) as total_menages,
            COUNT(CASE WHEN cs.status = 'completed' THEN 1 END) as menages_completed
        FROM weeks w
        LEFT JOIN cleaning_services cs ON w.id = cs.week_id
        GROUP BY w.id, w.week_number
        ORDER BY w.week_number DESC
        LIMIT 10
    ");
    
    echo "<table border='1'>";
    echo "<tr><th>Semaine</th><th>Week ID</th><th>Total Ménages</th><th>Ménages Complétés</th><th>CA Attendu</th></tr>";
    
    while($row = $stmt->fetch()) {
        $ca_attendu = $row['menages_completed'] * 60;
        echo "<tr>";
        echo "<td>" . $row['week_number'] . "</td>";
        echo "<td>" . $row['week_id'] . "</td>";
        echo "<td>" . $row['total_menages'] . "</td>";
        echo "<td>" . $row['menages_completed'] . "</td>";
        echo "<td>" . $ca_attendu . "€</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Vérifier les ménages sans week_id
    echo "<h3>Ménages sans assignation:</h3>";
    $stmt = $db->query("SELECT COUNT(*) as count FROM cleaning_services WHERE week_id IS NULL");
    $nullCount = $stmt->fetch();
    echo "<p>Ménages avec week_id = NULL: <strong>{$nullCount['count']}</strong></p>";
    
    // Total général
    echo "<h3>Statistiques générales:</h3>";
    $stmt = $db->query("SELECT COUNT(*) as total FROM cleaning_services");
    $totalAll = $stmt->fetch();
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM cleaning_services WHERE status = 'completed'");
    $totalCompleted = $stmt->fetch();
    
    echo "<p>Total de tous les ménages: <strong>{$totalAll['total']}</strong></p>";
    echo "<p>Total des ménages complétés: <strong>{$totalCompleted['total']}</strong></p>";
    
    // Vérifier les données de la table weeks pour la semaine active
    if ($activeWeek) {
        echo "<h3>Données stockées dans la table weeks (semaine active):</h3>";
        $stmt = $db->prepare("SELECT total_cleaning_count, total_cleaning_revenue, total_revenue FROM weeks WHERE id = ?");
        $stmt->execute([$activeWeek['id']]);
        $weekData = $stmt->fetch();
        
        echo "<ul>";
        echo "<li>Nombre de ménages stocké: {$weekData['total_cleaning_count']}</li>";
        echo "<li>CA ménages stocké: {$weekData['total_cleaning_revenue']}€</li>";
        echo "<li>CA total stocké: {$weekData['total_revenue']}€</li>";
        echo "</ul>";
        
        // Calculer en temps réel
        $realStats = calculateWeekStats($activeWeek['id']);
        
        echo "<h3>Calcul en temps réel (semaine active):</h3>";
        echo "<ul>";
        echo "<li>Nombre de ménages calculé: {$realStats['total_cleaning_count']}</li>";
        echo "<li>CA ménages calculé: {$realStats['total_cleaning_revenue']}€</li>";
        echo "<li>CA total calculé: {$realStats['total_revenue']}€</li>";
        echo "</ul>";
        
        // Proposer une correction si nécessaire
        if ($weekData['total_cleaning_count'] != $realStats['total_cleaning_count'] || 
            $weekData['total_cleaning_revenue'] != $realStats['total_cleaning_revenue']) {
            
            echo "<h3>Correction nécessaire!</h3>";
            echo "<form method='post'>";
            echo "<input type='hidden' name='update_stats' value='1'>";
            echo "<button type='submit' style='background: green; color: white; padding: 10px;'>Mettre à jour les statistiques</button>";
            echo "</form>";
        }
    }
    
    // Traitement de la mise à jour
    if (isset($_POST['update_stats']) && $activeWeek) {
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
        
        echo "<p style='color: green; font-weight: bold;'>✓ Statistiques mises à jour avec succès!</p>";
        echo "<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
}
?>

<br><br>
<a href="panel/week_management.php">← Retour à la gestion des semaines</a>