<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>Test de finalisation et création automatique de semaines</h2>";

try {
    $db = getDB();
    
    echo "<h3>1. État actuel des semaines</h3>";
    $stmt = $db->query("SELECT * FROM weekly_taxes ORDER BY week_start DESC LIMIT 5");
    $weeks = $stmt->fetchAll();
    
    echo "<table border='1'>";
    echo "<tr><th>Début</th><th>Fin</th><th>Finalisée</th><th>CA</th></tr>";
    foreach ($weeks as $week) {
        echo "<tr>";
        echo "<td>" . $week['week_start'] . "</td>";
        echo "<td>" . $week['week_end'] . "</td>";
        echo "<td>" . ($week['is_finalized'] ? 'OUI' : 'NON') . "</td>";
        echo "<td>" . $week['total_revenue'] . "€</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>2. Semaine active actuelle</h3>";
    $activeWeek = getActiveWeek();
    if ($activeWeek) {
        echo "Semaine active: " . $activeWeek['week_start'] . " à " . $activeWeek['week_end'] . " (Finalisée: " . ($activeWeek['is_finalized'] ? 'OUI' : 'NON') . ")";
    } else {
        echo "Aucune semaine active trouvée";
    }
    
    echo "<h3>3. Ventes récentes (dernières 10)</h3>";
    $stmt = $db->query("SELECT id, created_at, final_amount FROM sales ORDER BY created_at DESC LIMIT 10");
    $sales = $stmt->fetchAll();
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Date création</th><th>Montant</th><th>Dans semaine active?</th></tr>";
    foreach ($sales as $sale) {
        $inActiveWeek = isDateInActiveWeek($sale['created_at']);
        echo "<tr>";
        echo "<td>" . $sale['id'] . "</td>";
        echo "<td>" . $sale['created_at'] . "</td>";
        echo "<td>" . $sale['final_amount'] . "€</td>";
        echo "<td>" . ($inActiveWeek ? 'OUI' : 'NON') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>4. Services de ménage récents (derniers 10)</h3>";
    $stmt = $db->query("SELECT id, start_time, total_salary, status FROM cleaning_services ORDER BY start_time DESC LIMIT 10");
    $cleanings = $stmt->fetchAll();
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Date début</th><th>Salaire</th><th>Statut</th><th>Dans semaine active?</th></tr>";
    foreach ($cleanings as $cleaning) {
        $inActiveWeek = isDateInActiveWeek($cleaning['start_time']);
        echo "<tr>";
        echo "<td>" . $cleaning['id'] . "</td>";
        echo "<td>" . $cleaning['start_time'] . "</td>";
        echo "<td>" . $cleaning['total_salary'] . "€</td>";
        echo "<td>" . $cleaning['status'] . "</td>";
        echo "<td>" . ($inActiveWeek ? 'OUI' : 'NON') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>5. CA de la semaine active</h3>";
    if ($activeWeek) {
        // Calculer les ventes
        $stmt_sales = $db->prepare("SELECT COALESCE(SUM(final_amount), 0) as sales_revenue FROM sales WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?");
        $stmt_sales->execute([$activeWeek['week_start'], $activeWeek['week_end']]);
        $sales_result = $stmt_sales->fetch();
        
        // Calculer le salaire ménage
        $stmt_cleaning = $db->prepare("SELECT COALESCE(SUM(total_salary), 0) as cleaning_revenue FROM cleaning_services WHERE DATE(start_time) >= ? AND DATE(start_time) <= ? AND status = 'completed'");
        $stmt_cleaning->execute([$activeWeek['week_start'], $activeWeek['week_end']]);
        $cleaning_result = $stmt_cleaning->fetch();
        
        $current_revenue = $sales_result['sales_revenue'] + $cleaning_result['cleaning_revenue'];
        
        echo "Ventes: " . $sales_result['sales_revenue'] . "€<br>";
        echo "Ménages: " . $cleaning_result['cleaning_revenue'] . "€<br>";
        echo "<strong>Total CA: " . $current_revenue . "€</strong>";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}
?>