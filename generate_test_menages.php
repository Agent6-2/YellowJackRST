<?php
require_once 'config/database.php';
require_once 'includes/week_functions.php';

try {
    $db = getDB();
    
    echo "<h2>Génération de données de test pour les ménages</h2>";
    
    // Obtenir la semaine active
    $activeWeek = getActiveWeekNew();
    
    if (!$activeWeek) {
        echo "<p style='color: red;'>Aucune semaine active trouvée!</p>";
        exit;
    }
    
    echo "<p>Semaine active: {$activeWeek['week_number']} (ID: {$activeWeek['id']})</p>";
    
    // Vérifier le nombre actuel de ménages
    $stmt = $db->query("SELECT COUNT(*) as count FROM cleaning_services WHERE status = 'completed'");
    $currentCount = $stmt->fetch();
    
    echo "<p>Nombre actuel de ménages complétés: {$currentCount['count']}</p>";
    
    // Obtenir un utilisateur pour les tests (prendre le premier utilisateur actif)
    $stmt = $db->query("SELECT id FROM users WHERE status = 'active' LIMIT 1");
    $testUser = $stmt->fetch();
    
    if (!$testUser) {
        echo "<p style='color: red;'>Aucun utilisateur actif trouvé!</p>";
        exit;
    }
    
    echo "<p>Utilisateur de test: ID {$testUser['id']}</p>";
    
    if (isset($_POST['generate'])) {
        $menages_to_create = intval($_POST['menages_count'] ?? 1000);
        
        echo "<h3>Génération de {$menages_to_create} ménages...</h3>";
        
        $db->beginTransaction();
        
        try {
            for ($i = 0; $i < $menages_to_create; $i++) {
                // Générer des dates aléatoires dans la semaine active
                $start_date = new DateTime($activeWeek['week_start']);
                $end_date = new DateTime($activeWeek['week_end']);
                
                // Date de début aléatoire dans la semaine
                $random_days = rand(0, 6);
                $random_hours = rand(8, 18);
                $random_minutes = rand(0, 59);
                
                $start_time = clone $start_date;
                $start_time->add(new DateInterval("P{$random_days}D"));
                $start_time->setTime($random_hours, $random_minutes);
                
                // Durée aléatoire entre 30 et 120 minutes
                $duration = rand(30, 120);
                $end_time = clone $start_time;
                $end_time->add(new DateInterval("PT{$duration}M"));
                
                // Nombre de ménages aléatoire entre 1 et 5
                $cleaning_count = rand(1, 5);
                $total_salary = $cleaning_count * 60; // 60€ par ménage
                
                // Insérer le service de ménage
                $stmt = $db->prepare("
                    INSERT INTO cleaning_services (
                        user_id, 
                        start_time, 
                        end_time, 
                        duration_minutes, 
                        cleaning_count, 
                        total_salary, 
                        status, 
                        week_id
                    ) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?)
                ");
                
                $stmt->execute([
                    $testUser['id'],
                    $start_time->format('Y-m-d H:i:s'),
                    $end_time->format('Y-m-d H:i:s'),
                    $duration,
                    $cleaning_count,
                    $total_salary,
                    $activeWeek['id']
                ]);
                
                if (($i + 1) % 100 == 0) {
                    echo "<p>Créé " . ($i + 1) . " ménages...</p>";
                    flush();
                }
            }
            
            $db->commit();
            
            echo "<p style='color: green; font-weight: bold;'>✓ {$menages_to_create} ménages créés avec succès!</p>";
            
            // Recalculer les statistiques de la semaine
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
            
            echo "<p style='color: green;'>✓ Statistiques de la semaine mises à jour!</p>";
            echo "<p>Nouveau CA ménages: {$realStats['total_cleaning_revenue']}€</p>";
            echo "<p>Nouveau nombre de ménages: {$realStats['total_cleaning_count']}</p>";
            
        } catch (Exception $e) {
            $db->rollback();
            echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
        }
    }
    
    if (isset($_POST['delete_all'])) {
        echo "<h3>Suppression de tous les ménages de test...</h3>";
        
        $db->beginTransaction();
        
        try {
            // Supprimer tous les ménages
            $stmt = $db->query("DELETE FROM cleaning_services");
            $deleted = $stmt->rowCount();
            
            // Remettre à zéro les statistiques de la semaine
            $stmt = $db->prepare("
                UPDATE weeks SET 
                    total_revenue = 0,
                    total_sales_count = 0,
                    total_cleaning_revenue = 0,
                    total_cleaning_count = 0
                WHERE id = ?
            ");
            
            $stmt->execute([$activeWeek['id']]);
            
            $db->commit();
            
            echo "<p style='color: green; font-weight: bold;'>✓ {$deleted} ménages supprimés avec succès!</p>";
            echo "<p style='color: green;'>✓ Statistiques de la semaine remises à zéro!</p>";
            
        } catch (Exception $e) {
            $db->rollback();
            echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
}
?>

<h3>Actions disponibles:</h3>

<form method='post' style='margin: 20px 0;'>
    <h4>Générer des ménages de test:</h4>
    <label>Nombre de ménages à créer:</label>
    <input type='number' name='menages_count' value='1000' min='1' max='5000'>
    <br><br>
    <button type='submit' name='generate' style='background: green; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>
        Générer les ménages
    </button>
</form>

<form method='post' style='margin: 20px 0;'>
    <h4>Supprimer tous les ménages:</h4>
    <button type='submit' name='delete_all' style='background: red; color: white; padding: 10px 20px; border: none; border-radius: 5px;' 
            onclick='return confirm("Êtes-vous sûr de vouloir supprimer TOUS les ménages ?");'>
        Supprimer tous les ménages
    </button>
</form>

<br><br>
<a href='panel/week_management.php'>← Retour à la gestion des semaines</a>
<br>
<a href='fix_week_id_null.php'>→ Diagnostic des semaines</a>