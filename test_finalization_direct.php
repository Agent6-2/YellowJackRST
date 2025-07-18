<?php
/**
 * Test direct de finalisation de semaine
 * Pour diagnostiquer les problèmes spécifiques
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/week_functions.php';

try {
    $db = getDB();
    $auth = new Auth($db);
    
    echo "<h2>🧪 Test Direct de Finalisation</h2>";
    echo "<div style='font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px;'>";
    
    session_start();
    
    // Vérification préliminaire
    if (!$auth->isLoggedIn()) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>❌ Erreur de Connexion</h3>";
        echo "<p>Vous devez être connecté pour effectuer ce test.</p>";
        echo "<a href='panel/login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Se Connecter</a>";
        echo "</div>";
        echo "</div>";
        exit;
    }
    
    if (!$auth->hasPermission('Patron')) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>❌ Permissions Insuffisantes</h3>";
        echo "<p>Seul un Patron peut effectuer ce test.</p>";
        echo "<p>Votre rôle actuel : <strong>" . $auth->getCurrentUser()['role'] . "</strong></p>";
        echo "</div>";
        echo "</div>";
        exit;
    }
    
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>✅ Permissions Validées</h3>";
    echo "<p>Utilisateur connecté en tant que Patron. Test en cours...</p>";
    echo "</div>";
    
    // Test 1: Vérifier la semaine active
    echo "<h3>1. 📅 Vérification Semaine Active</h3>";
    $activeWeek = getActiveWeek();
    
    if (!$activeWeek) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<p>❌ Aucune semaine active trouvée. Impossible de finaliser.</p>";
        echo "</div>";
        echo "</div>";
        exit;
    }
    
    echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Semaine Active :</h4>";
    echo "<p><strong>ID:</strong> {$activeWeek['id']}</p>";
    echo "<p><strong>Numéro:</strong> {$activeWeek['week_number']}</p>";
    echo "<p><strong>Période:</strong> {$activeWeek['start_date']} → {$activeWeek['end_date']}</p>";
    echo "<p><strong>Finalisée:</strong> " . ($activeWeek['is_finalized'] ? 'Oui' : 'Non') . "</p>";
    echo "</div>";
    
    if ($activeWeek['is_finalized']) {
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<p>⚠️ Cette semaine est déjà finalisée. Vous ne pouvez pas la finaliser à nouveau.</p>";
        echo "</div>";
        echo "</div>";
        exit;
    }
    
    // Test 2: Simuler la finalisation
    echo "<h3>2. 🔧 Test de Finalisation</h3>";
    
    if (isset($_POST['test_finalize'])) {
        echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>🚀 Tentative de finalisation...</h4>";
        
        try {
            // Dates pour la nouvelle semaine
            $newStartDate = $_POST['new_start_date'] ?? date('Y-m-d', strtotime('+1 day', strtotime($activeWeek['end_date'])));
            $newEndDate = $_POST['new_end_date'] ?? date('Y-m-d', strtotime('+7 days', strtotime($newStartDate)));
            
            echo "<p>📊 Calcul des statistiques...</p>";
            $stats = calculateWeekStats($activeWeek['id']);
            echo "<p>✅ Statistiques calculées</p>";
            
            echo "<p>💾 Début de la transaction...</p>";
            $db->beginTransaction();
            
            // Mettre à jour la semaine active
            echo "<p>📝 Finalisation de la semaine {$activeWeek['week_number']}...</p>";
            $stmt = $db->prepare("
                UPDATE weeks 
                SET is_finalized = 1, 
                    finalized_at = NOW(), 
                    finalized_by = ?,
                    total_sales = ?,
                    total_cleaning_services = ?,
                    sales_count = ?,
                    cleaning_count = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $auth->getCurrentUser()['id'],
                $stats['total_sales'],
                $stats['total_cleaning_services'],
                $stats['sales_count'],
                $stats['cleaning_count'],
                $activeWeek['id']
            ]);
            echo "<p>✅ Semaine finalisée</p>";
            
            // Créer la nouvelle semaine
            echo "<p>🆕 Création de la nouvelle semaine...</p>";
            $newWeekNumber = $activeWeek['week_number'] + 1;
            
            $stmt = $db->prepare("
                INSERT INTO weeks (week_number, start_date, end_date, is_active, created_by) 
                VALUES (?, ?, ?, 1, ?)
            ");
            
            $stmt->execute([
                $newWeekNumber,
                $newStartDate,
                $newEndDate,
                $auth->getCurrentUser()['id']
            ]);
            echo "<p>✅ Nouvelle semaine créée (Semaine {$newWeekNumber})</p>";
            
            $db->commit();
            echo "<p>💾 Transaction validée</p>";
            
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>🎉 Finalisation Réussie !</h4>";
            echo "<p>La semaine {$activeWeek['week_number']} a été finalisée avec succès.</p>";
            echo "<p>La nouvelle semaine {$newWeekNumber} est maintenant active.</p>";
            echo "<a href='panel/week_management.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Voir la Gestion des Semaines</a>";
            echo "</div>";
            
        } catch (Exception $e) {
            $db->rollback();
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>❌ Erreur lors de la finalisation</h4>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>Fichier:</strong> " . $e->getFile() . " (ligne " . $e->getLine() . ")</p>";
            echo "<details><summary>Trace complète</summary><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></details>";
            echo "</div>";
        }
        
        echo "</div>";
    } else {
        // Formulaire de test
        echo "<form method='POST' style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>Paramètres de la nouvelle semaine :</h4>";
        
        $defaultStart = date('Y-m-d', strtotime('+1 day', strtotime($activeWeek['end_date'])));
        $defaultEnd = date('Y-m-d', strtotime('+7 days', strtotime($defaultStart)));
        
        echo "<div style='margin: 10px 0;'>";
        echo "<label for='new_start_date'>Date de début :</label><br>";
        echo "<input type='date' id='new_start_date' name='new_start_date' value='{$defaultStart}' required style='padding: 8px; border: 1px solid #ddd; border-radius: 4px;'>";
        echo "</div>";
        
        echo "<div style='margin: 10px 0;'>";
        echo "<label for='new_end_date'>Date de fin :</label><br>";
        echo "<input type='date' id='new_end_date' name='new_end_date' value='{$defaultEnd}' required style='padding: 8px; border: 1px solid #ddd; border-radius: 4px;'>";
        echo "</div>";
        
        echo "<div style='margin: 20px 0;'>";
        echo "<button type='submit' name='test_finalize' style='background: #dc3545; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;' onclick='return confirm(\"Êtes-vous sûr de vouloir finaliser la semaine {$activeWeek['week_number']} ?\");'>";
        echo "🧪 TESTER LA FINALISATION";
        echo "</button>";
        echo "</div>";
        
        echo "<p style='color: #856404; font-size: 14px;'>";
        echo "⚠️ <strong>Attention :</strong> Ce test va réellement finaliser la semaine active et créer une nouvelle semaine.";
        echo "</p>";
        
        echo "</form>";
    }
    
    echo "<h3>3. 🔗 Actions</h3>";
    echo "<p>";
    echo "<a href='debug_patron_issue.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🔍 Diagnostic Complet</a>";
    echo "<a href='panel/week_management.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>📅 Gestion Semaines</a>";
    echo "<a href='panel/dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Dashboard</a>";
    echo "</p>";
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px;'>";
    echo "<h3>❌ Erreur Critique</h3>";
    echo "<p>Erreur lors du test : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier :</strong> " . $e->getFile() . " (ligne " . $e->getLine() . ")</p>";
    echo "<details><summary>Trace complète</summary><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></details>";
    echo "</div>";
}
?>