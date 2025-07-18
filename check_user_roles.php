<?php
/**
 * Script pour vérifier les rôles des utilisateurs
 */

require_once 'config/database.php';
require_once 'includes/auth.php';

try {
    $db = getDB();
    
    echo "<h2>Vérification des Rôles Utilisateurs</h2>";
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px;'>";
    
    // Vérifier tous les utilisateurs et leurs rôles
    echo "<h3>1. Liste de tous les utilisateurs</h3>";
    $stmt = $db->query("SELECT id, username, first_name, last_name, role, status FROM users ORDER BY role, username");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<p style='color: red;'>✗ Aucun utilisateur trouvé dans la base de données.</p>";
    } else {
        echo "<table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>ID</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Username</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Nom</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Rôle</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Statut</th>";
        echo "</tr>";
        
        foreach ($users as $user) {
            $roleColor = '';
            switch ($user['role']) {
                case 'Patron':
                    $roleColor = 'color: #d4af37; font-weight: bold;';
                    break;
                case 'Responsable':
                    $roleColor = 'color: #ff6b35;';
                    break;
                case 'CDI':
                    $roleColor = 'color: #28a745;';
                    break;
                case 'CDD':
                    $roleColor = 'color: #6c757d;';
                    break;
            }
            
            $statusColor = $user['status'] === 'active' ? 'color: green;' : 'color: red;';
            
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['id']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['username']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['first_name']} {$user['last_name']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px; {$roleColor}'>{$user['role']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px; {$statusColor}'>{$user['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Compter les utilisateurs par rôle
    echo "<h3>2. Répartition par rôle</h3>";
    $stmt = $db->query("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role ORDER BY role");
    $roleStats = $stmt->fetchAll();
    
    foreach ($roleStats as $stat) {
        $color = $stat['role'] === 'Patron' ? 'color: #d4af37; font-weight: bold;' : '';
        echo "<p style='{$color}'>{$stat['role']}: <strong>{$stat['count']}</strong> utilisateur(s) actif(s)</p>";
    }
    
    // Vérifier l'utilisateur connecté
    echo "<h3>3. Utilisateur actuellement connecté</h3>";
    session_start();
    
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT id, username, first_name, last_name, role, status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch();
        
        if ($currentUser) {
            echo "<p><strong>ID:</strong> {$currentUser['id']}</p>";
            echo "<p><strong>Username:</strong> {$currentUser['username']}</p>";
            echo "<p><strong>Nom:</strong> {$currentUser['first_name']} {$currentUser['last_name']}</p>";
            
            $roleColor = $currentUser['role'] === 'Patron' ? 'color: #d4af37; font-weight: bold;' : '';
            echo "<p style='{$roleColor}'><strong>Rôle:</strong> {$currentUser['role']}</p>";
            
            $statusColor = $currentUser['status'] === 'active' ? 'color: green;' : 'color: red;';
            echo "<p style='{$statusColor}'><strong>Statut:</strong> {$currentUser['status']}</p>";
            
            // Vérifier les permissions
            echo "<h4>Permissions</h4>";
            if ($currentUser['role'] === 'Patron') {
                echo "<p style='color: green;'>✓ Peut finaliser les semaines</p>";
                echo "<p style='color: green;'>✓ Peut gérer les employés</p>";
                echo "<p style='color: green;'>✓ Peut accéder à toutes les fonctionnalités</p>";
            } else {
                echo "<p style='color: red;'>✗ Ne peut pas finaliser les semaines (rôle requis: Patron)</p>";
                if (in_array($currentUser['role'], ['Responsable', 'Patron'])) {
                    echo "<p style='color: green;'>✓ Peut gérer les employés</p>";
                } else {
                    echo "<p style='color: red;'>✗ Ne peut pas gérer les employés</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>✗ Utilisateur connecté non trouvé dans la base de données.</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠ Aucun utilisateur connecté.</p>";
    }
    
    // Suggestions
    echo "<h3>4. Suggestions</h3>";
    
    $patronCount = 0;
    foreach ($roleStats as $stat) {
        if ($stat['role'] === 'Patron') {
            $patronCount = $stat['count'];
            break;
        }
    }
    
    if ($patronCount === 0) {
        echo "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h5 style='color: #856404;'>⚠ Aucun patron trouvé</h5>";
        echo "<p>Pour pouvoir finaliser les semaines, vous devez :</p>";
        echo "<ol>";
        echo "<li>Créer un utilisateur avec le rôle 'Patron', ou</li>";
        echo "<li>Modifier le rôle d'un utilisateur existant en 'Patron'</li>";
        echo "</ol>";
        echo "<p><strong>Solution rapide :</strong> Allez dans la gestion des employés et modifiez votre rôle ou celui d'un autre utilisateur.</p>";
        echo "</div>";
    } else {
        echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h5 style='color: #155724;'>✓ Patron(s) disponible(s)</h5>";
        echo "<p>Il y a <strong>{$patronCount}</strong> patron(s) dans le système.</p>";
        if (isset($currentUser) && $currentUser['role'] !== 'Patron') {
            echo "<p><strong>Note :</strong> Vous devez vous connecter avec un compte Patron pour finaliser les semaines.</p>";
        }
        echo "</div>";
    }
    
    echo "<h3 style='color: green;'>✅ Vérification terminée</h3>";
    echo "<p>";
    echo "<a href='panel/dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Retour au Dashboard</a>";
    echo "<a href='panel/week_management.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Gestion des Semaines</a>";
    echo "<a href='panel/employees.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Gestion des Employés</a>";
    echo "</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px;'>";
    echo "<h3>❌ Erreur</h3>";
    echo "<p>Erreur lors de la vérification : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>