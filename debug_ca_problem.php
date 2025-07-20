<?php
/**
 * Script de diagnostic complet pour le problème de CA
 */

require_once 'config/database.php';
require_once 'includes/week_functions.php';

try {
    $db = getDB();
    
    echo "<h2>🔍 Diagnostic Complet du Problème de CA</h2>";
    
    // 1. Vérifier la semaine active
    echo "<h3>1. Semaine Active</h3>";
    $activeWeek = getActiveWeekNew();
    if ($activeWeek) {
        echo "<p>✅ Semaine active trouvée: <strong>Semaine {$activeWeek['week_number']}</strong> (ID: {$activeWeek['id']})</p>";
        echo "<p>Période: {$activeWeek['week_start']} → {$activeWeek['week_end']}</p>";
    } else {
        echo "<p style='color: red;'>❌ Aucune semaine active trouvée!</p>";
        exit;
    }
    
    // 2. Compter tous les ménages dans cleaning_services
    echo "<h3>2. Analyse des Ménages dans cleaning_services</h3>";
    
    // Total des ménages
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM cleaning_services");
    $stmt->execute();
    $total_menages = $stmt->fetchColumn();
    echo "<p>Total ménages dans la table: <strong>$total_menages</strong></p>";
    
    // Ménages par statut
    $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM cleaning_services GROUP BY status");
    $stmt->execute();
    $status_counts = $stmt->fetchAll();
    echo "<p>Répartition par statut:</p><ul>";
    foreach ($status_counts as $status) {
        echo "<li>{$status['status']}: {$status['count']}</li>";
    }
    echo "</ul>";
    
    // Ménages par week_id
    $stmt = $db->prepare("SELECT week_id, COUNT(*) as count FROM cleaning_services GROUP BY week_id ORDER BY week_id");
    $stmt->execute();
    $week_counts = $stmt->fetchAll();
    echo "<p>Répartition par semaine:</p><ul>";
    foreach ($week_counts as $week) {
        $week_id = $week['week_id'] ?? 'NULL';
        echo "<li>Semaine ID {$week_id}: {$week['count']} ménages</li>";
    }
    echo "</ul>";
    
    // Ménages de test (user_id = 999)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cleaning_services WHERE user_id = 999");
    $stmt->execute();
    $test_menages = $stmt->fetchColumn();
    echo "<p>Ménages de test (user_id = 999): <strong style='color: orange;'>$test_menages</strong></p>";
    
    // 3. Ménages de la semaine active
    echo "<h3>3. Ménages de la Semaine Active</h3>";
    
    // Tous les ménages de la semaine active
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cleaning_services WHERE week_id = ?");
    $stmt->execute([$activeWeek['id']]);
    $week_total = $stmt->fetchColumn();
    echo "<p>Total ménages semaine active: <strong>$week_total</strong></p>";
    
    // Ménages complétés de la semaine active
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cleaning_services WHERE week_id = ? AND status = 'completed'");
    $stmt->execute([$activeWeek['id']]);
    $week_completed = $stmt->fetchColumn();
    echo "<p>Ménages complétés semaine active: <strong>$week_completed</strong></p>";
    
    // Ménages complétés RÉELS (sans les tests)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cleaning_services WHERE week_id = ? AND status = 'completed' AND user_id != 999");
    $stmt->execute([$activeWeek['id']]);
    $week_real_completed = $stmt->fetchColumn();
    echo "<p>Ménages complétés RÉELS (sans tests): <strong style='color: green;'>$week_real_completed</strong></p>";
    
    // 4. Test de la fonction calculateWeekStats
    echo "<h3>4. Test de calculateWeekStats</h3>";
    $stats = calculateWeekStats($activeWeek['id']);
    echo "<p>Résultat de calculateWeekStats:</p>";
    echo "<ul>";
    echo "<li>Ménages comptés: {$stats['total_cleaning_count']}</li>";
    echo "<li>CA ménages calculé: {$stats['total_cleaning_revenue']}$</li>";
    echo "<li>Ventes: {$stats['total_sales_count']} ({$stats['total_sales_revenue']}$)</li>";
    echo "<li><strong>CA total: {$stats['total_revenue']}$</strong></li>";
    echo "</ul>";
    
    // 5. Données stockées dans la table weeks
    echo "<h3>5. Données Stockées dans la Table weeks</h3>";
    $stmt = $db->prepare("SELECT * FROM weeks WHERE id = ?");
    $stmt->execute([$activeWeek['id']]);
    $stored_week = $stmt->fetch();
    
    echo "<p>Données stockées dans weeks:</p>";
    echo "<ul>";
    echo "<li>Ménages stockés: {$stored_week['total_cleaning_count']}</li>";
    echo "<li>CA ménages stocké: {$stored_week['total_cleaning_revenue']}$</li>";
    echo "<li>Ventes stockées: {$stored_week['total_sales_count']} ({$stored_week['total_sales_revenue']}$)</li>";
    echo "</ul>";
    
    // 6. Comparaison et diagnostic
    echo "<h3>6. Diagnostic</h3>";
    
    if ($stats['total_cleaning_count'] != $stored_week['total_cleaning_count']) {
        echo "<p style='color: red;'>⚠️ PROBLÈME: Désynchronisation entre les données calculées et stockées!</p>";
        echo "<p>Calculé: {$stats['total_cleaning_count']} ménages</p>";
        echo "<p>Stocké: {$stored_week['total_cleaning_count']} ménages</p>";
        
        echo "<form method='post' style='margin: 20px 0;'>";
        echo "<button type='submit' name='fix_sync' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>🔧 Corriger la Synchronisation</button>";
        echo "</form>";
    } else {
        echo "<p style='color: green;'>✅ Les données calculées et stockées sont synchronisées.</p>";
    }
    
    if ($week_real_completed == 0) {
        echo "<p style='color: orange;'>ℹ️ Il n'y a actuellement aucun ménage réel complété dans cette semaine.</p>";
        echo "<p>Pour tester le système, vous pouvez:</p>";
        echo "<ul>";
        echo "<li>Faire des ménages via l'interface utilisateur</li>";
        echo "<li>Ou utiliser le générateur de test temporairement</li>";
        echo "</ul>";
    }
    
    // Traitement de la correction
    if (isset($_POST['fix_sync'])) {
        echo "<h3>🔧 Correction en cours...</h3>";
        
        $stmt = $db->prepare("
            UPDATE weeks 
            SET total_cleaning_count = ?, 
                total_cleaning_revenue = ?,
                total_sales_count = ?,
                total_sales_revenue = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $stats['total_cleaning_count'],
            $stats['total_cleaning_revenue'],
            $stats['total_sales_count'],
            $stats['total_sales_revenue'],
            $activeWeek['id']
        ]);
        
        echo "<p style='color: green;'>✅ Synchronisation corrigée!</p>";
        echo "<p><a href='debug_ca_problem.php'>🔄 Recharger pour vérifier</a></p>";
    }
    
    echo "<p style='margin-top: 30px;'><a href='panel/week_management.php'>🔙 Retour à la gestion des semaines</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur: " . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 20px auto;
    padding: 20px;
    background-color: #f8f9fa;
}
h2, h3 {
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
}
p, li {
    margin: 8px 0;
    line-height: 1.5;
}
ul {
    background-color: white;
    padding: 15px;
    border-radius: 5px;
    border-left: 4px solid #007bff;
}
a {
    color: #007bff;
    text-decoration: none;
    padding: 8px 15px;
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    display: inline-block;
    margin: 5px;
}
a:hover {
    background-color: #f8f9fa;
}
</style>