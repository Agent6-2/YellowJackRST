<?php
/**
 * Script de diagnostic spécialisé pour les problèmes de finalisation de semaine
 * Analyse approfondie pour les utilisateurs Patron
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/week_functions.php';

try {
    $db = getDB();
    $auth = new Auth($db);
    
    echo "<h2>🔍 Diagnostic Spécialisé - Problème de Finalisation Patron</h2>";
    echo "<div style='font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px;'>";
    
    // 1. Vérification de la session actuelle
    echo "<h3>1. 📋 État de la Session Actuelle</h3>";
    session_start();
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Variables de session :</h4>";
    if (empty($_SESSION)) {
        echo "<p style='color: red;'>❌ Aucune session active</p>";
    } else {
        foreach ($_SESSION as $key => $value) {
            if (is_array($value)) {
                echo "<p><strong>{$key}:</strong> " . json_encode($value) . "</p>";
            } else {
                echo "<p><strong>{$key}:</strong> {$value}</p>";
            }
        }
    }
    echo "</div>";
    
    // 2. Vérification avec la classe Auth
    echo "<h3>2. 🔐 Vérification Auth Class</h3>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    
    if ($auth->isLoggedIn()) {
        echo "<p style='color: green;'>✅ Utilisateur connecté selon Auth::isLoggedIn()</p>";
        
        $currentUser = $auth->getCurrentUser();
        if ($currentUser) {
            echo "<h4>Données utilisateur via Auth::getCurrentUser() :</h4>";
            foreach ($currentUser as $key => $value) {
                if ($key !== 'password') {
                    echo "<p><strong>{$key}:</strong> {$value}</p>";
                }
            }
            
            // Test des permissions
            echo "<h4>Tests de permissions :</h4>";
            $permissions = ['CDD', 'CDI', 'Responsable', 'Patron'];
            foreach ($permissions as $perm) {
                $hasPermission = $auth->hasPermission($perm);
                $color = $hasPermission ? 'green' : 'red';
                $icon = $hasPermission ? '✅' : '❌';
                echo "<p style='color: {$color};'>{$icon} hasPermission('{$perm}'): " . ($hasPermission ? 'true' : 'false') . "</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ getCurrentUser() retourne null</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Utilisateur non connecté selon Auth::isLoggedIn()</p>";
    }
    echo "</div>";
    
    // 3. Test de la fonction canFinalizeWeek
    echo "<h3>3. 📅 Test de canFinalizeWeek()</h3>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    
    if (function_exists('canFinalizeWeek')) {
        $canFinalize = canFinalizeWeek();
        $color = $canFinalize ? 'green' : 'red';
        $icon = $canFinalize ? '✅' : '❌';
        echo "<p style='color: {$color};'>{$icon} canFinalizeWeek(): " . ($canFinalize ? 'true' : 'false') . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Fonction canFinalizeWeek() non trouvée</p>";
    }
    echo "</div>";
    
    // 4. Vérification de la semaine active
    echo "<h3>4. 📊 État de la Semaine Active</h3>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    
    if (function_exists('getActiveWeek')) {
        $activeWeek = getActiveWeek();
        if ($activeWeek) {
            echo "<h4>Semaine active trouvée :</h4>";
            foreach ($activeWeek as $key => $value) {
                echo "<p><strong>{$key}:</strong> {$value}</p>";
            }
            
            // Vérifier si elle peut être finalisée
            if ($activeWeek['is_finalized'] == 1) {
                echo "<p style='color: orange;'>⚠️ Cette semaine est déjà finalisée</p>";
            } else {
                echo "<p style='color: green;'>✅ Cette semaine peut être finalisée</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Aucune semaine active trouvée</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Fonction getActiveWeek() non trouvée</p>";
    }
    echo "</div>";
    
    // 5. Test direct de la base de données
    echo "<h3>5. 🗄️ Vérification Directe Base de Données</h3>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $dbUser = $stmt->fetch();
        
        if ($dbUser) {
            echo "<h4>Utilisateur en base de données :</h4>";
            foreach ($dbUser as $key => $value) {
                if ($key !== 'password') {
                    echo "<p><strong>{$key}:</strong> {$value}</p>";
                }
            }
            
            if ($dbUser['role'] === 'Patron') {
                echo "<p style='color: green;'>✅ Rôle 'Patron' confirmé en base</p>";
            } else {
                echo "<p style='color: red;'>❌ Rôle en base: '{$dbUser['role']}' (pas 'Patron')</p>";
            }
            
            if ($dbUser['status'] === 'active') {
                echo "<p style='color: green;'>✅ Statut 'active' confirmé</p>";
            } else {
                echo "<p style='color: red;'>❌ Statut: '{$dbUser['status']}' (pas 'active')</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Utilisateur non trouvé en base avec l'ID: {$_SESSION['user_id']}</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Pas d'user_id en session</p>";
    }
    echo "</div>";
    
    // 6. Test de la logique de week_management.php
    echo "<h3>6. 🔧 Simulation de la Logique week_management.php</h3>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    
    // Simuler la vérification de week_management.php
    if ($auth->isLoggedIn() && $auth->hasPermission('Patron')) {
        echo "<p style='color: green;'>✅ Accès autorisé selon la logique corrigée</p>";
        
        // Test de finalizeWeekAndCreateNew si elle existe
        if (function_exists('finalizeWeekAndCreateNew')) {
            echo "<p style='color: green;'>✅ Fonction finalizeWeekAndCreateNew() disponible</p>";
        } else {
            echo "<p style='color: red;'>❌ Fonction finalizeWeekAndCreateNew() non trouvée</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Accès refusé selon la logique corrigée</p>";
        
        if (!$auth->isLoggedIn()) {
            echo "<p style='color: red;'>  - Raison: Utilisateur non connecté</p>";
        }
        if (!$auth->hasPermission('Patron')) {
            echo "<p style='color: red;'>  - Raison: Permission 'Patron' refusée</p>";
        }
    }
    echo "</div>";
    
    // 7. Recommandations
    echo "<h3>7. 💡 Recommandations</h3>";
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    
    if (!$auth->isLoggedIn()) {
        echo "<p><strong>🔑 Problème de connexion :</strong></p>";
        echo "<ul>";
        echo "<li>Déconnectez-vous complètement</li>";
        echo "<li>Videz le cache du navigateur</li>";
        echo "<li>Reconnectez-vous avec vos identifiants Patron</li>";
        echo "</ul>";
    } elseif (!$auth->hasPermission('Patron')) {
        echo "<p><strong>🚫 Problème de permissions :</strong></p>";
        echo "<ul>";
        echo "<li>Vérifiez que votre rôle est bien 'Patron' en base</li>";
        echo "<li>Vérifiez que votre statut est 'active'</li>";
        echo "<li>Contactez un administrateur si nécessaire</li>";
        echo "</ul>";
    } else {
        echo "<p><strong>✅ Permissions OK - Problème technique possible :</strong></p>";
        echo "<ul>";
        echo "<li>Vérifiez les logs d'erreur PHP</li>";
        echo "<li>Testez la finalisation avec les outils de développement ouverts</li>";
        echo "<li>Vérifiez la console JavaScript pour des erreurs</li>";
        echo "</ul>";
    }
    echo "</div>";
    
    // 8. Actions rapides
    echo "<h3>8. ⚡ Actions Rapides</h3>";
    echo "<p>";
    echo "<a href='panel/logout.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🚪 Se Déconnecter</a>";
    echo "<a href='panel/login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🔑 Se Reconnecter</a>";
    echo "<a href='panel/week_management.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>📅 Gestion Semaines</a>";
    echo "<a href='panel/employees.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>👥 Gestion Employés</a>";
    echo "</p>";
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px;'>";
    echo "<h3>❌ Erreur Critique</h3>";
    echo "<p>Erreur lors du diagnostic : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Trace :</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>