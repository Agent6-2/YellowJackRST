<?php
require_once 'config/database.php';
require_once 'includes/week_functions.php';

try {
    $db = getDB();
    
    echo "<h2>Mise à jour forcée des statistiques de semaine</h2>";
    
    // Obtenir la semaine active
    $activeWeek = getActiveWeekNew();
    
    if (!$activeWeek) {
        echo "<p style='color: red;'>Aucune semaine active trouvée!</p>";
        exit;
    }
    
    echo "<p>Semaine active: {$activeWeek['week_number']} (ID: {$activeWeek['id']})</p>";
    
    // Afficher les données actuelles dans la table weeks
    $stmt = $db->prepare("SELECT total_cleaning_count, total_cleaning_revenue, total_revenue FROM weeks WHERE id = ?");
    $stmt->execute([$activeWeek['id']]);
    $currentData = $stmt->fetch();
    
    echo "<h3>Données actuelles dans la table weeks:</h3>";
    echo "<ul>";
    echo "<li>Nombre de ménages: {$currentData['total_cleaning_count']}</li>";
    echo "<li>CA ménages: {$currentData['total_cleaning_revenue']}€</li>";
    echo "<li>CA total: {$currentData['total_revenue']}€</li>";
    echo "</ul>";
    
    // Calculer les vraies statistiques
    $realStats = calculateWeekStats($activeWeek['id']);
    
    echo "<h3>Statistiques calculées en temps réel:</h3>";
    echo "<ul>";
    echo "<li>Nombre de ménages: {$realStats['total_cleaning_count']}</li>";
    echo "<li>CA ménages: {$realStats['total_cleaning_revenue']}€</li>";
    echo "<li>CA total: {$realStats['total_revenue']}€</li>";
    echo "</ul>";
    
    // Vérifier directement dans cleaning_services
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cleaning_services WHERE week_id = ? AND status = 'completed'");
    $stmt->execute([$activeWeek['id']]);
    $directCount = $stmt->fetch();
    
    echo "<h3>Vérification directe dans cleaning_services:</h3>";
    echo "<ul>";
    echo "<li>Nombre de ménages complétés: {$directCount['count']}</li>";
    echo "<li>CA attendu (x60): " . ($directCount['count'] * 60) . "€</li>";
    echo "</ul>";
    
    // Forcer la mise à jour
    if ($currentData['total_cleaning_count'] != $realStats['total_cleaning_count'] || 
        $currentData['total_cleaning_revenue'] != $realStats['total_cleaning_revenue']) {
        
        echo "<h3>Mise à jour nécessaire - Application en cours...</h3>";
        
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
        
        echo "<p style='color: green;'>✓ Mise à jour effectuée avec succès!</p>";
        
        // Vérifier après mise à jour
        $stmt = $db->prepare("SELECT total_cleaning_count, total_cleaning_revenue, total_revenue FROM weeks WHERE id = ?");
        $stmt->execute([$activeWeek['id']]);
        $updatedData = $stmt->fetch();
        
        echo "<h3>Données après mise à jour:</h3>";
        echo "<ul>";
        echo "<li>Nombre de ménages: {$updatedData['total_cleaning_count']}</li>";
        echo "<li>CA ménages: {$updatedData['total_cleaning_revenue']}€</li>";
        echo "<li>CA total: {$updatedData['total_revenue']}€</li>";
        echo "</ul>";
        
    } else {
        echo "<p style='color: green;'>Les données sont déjà à jour.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
}
?>

<br><br>
<a href="panel/week_management.php">← Retour à la gestion des semaines</a>