<?php
require_once 'config/database.php';

try {
    $db = getDB();
    
    echo "<h2>Données de la table weeks</h2>";
    
    // Afficher les données des semaines
    $stmt = $db->query("SELECT id, week_number, total_cleaning_count, total_cleaning_revenue, total_revenue FROM weeks ORDER BY week_number DESC LIMIT 5");
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Semaine</th><th>Nb Ménages</th><th>CA Ménage</th><th>CA Total</th></tr>";
    
    while($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['week_number'] . "</td>";
        echo "<td>" . $row['total_cleaning_count'] . "</td>";
        echo "<td>" . $row['total_cleaning_revenue'] . "€</td>";
        echo "<td>" . $row['total_revenue'] . "€</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Vérifier le calcul en temps réel pour la semaine active
    echo "<h2>Calcul en temps réel</h2>";
    
    require_once 'includes/week_functions.php';
    $activeWeek = getActiveWeekNew();
    
    if ($activeWeek) {
        echo "<p>Semaine active: {$activeWeek['week_number']} (ID: {$activeWeek['id']})</p>";
        
        $stats = calculateWeekStats($activeWeek['id']);
        
        echo "<p>Calcul temps réel:</p>";
        echo "<ul>";
        echo "<li>Nombre de ménages: {$stats['total_cleaning_count']}</li>";
        echo "<li>CA ménages calculé: {$stats['total_cleaning_revenue']}€</li>";
        echo "<li>CA total calculé: {$stats['total_revenue']}€</li>";
        echo "</ul>";
        
        // Vérifier directement dans la base
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM cleaning_services WHERE week_id = ? AND status = 'completed'");
        $stmt->execute([$activeWeek['id']]);
        $directCount = $stmt->fetch();
        
        echo "<p>Vérification directe BDD:</p>";
        echo "<ul>";
        echo "<li>Nombre de ménages dans BDD: {$directCount['count']}</li>";
        echo "<li>CA attendu (x60): " . ($directCount['count'] * 60) . "€</li>";
        echo "</ul>";
    } else {
        echo "<p>Aucune semaine active trouvée</p>";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}
?>