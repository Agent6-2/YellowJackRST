<?php
/**
 * Script de création de la table des objectifs utilisateurs
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Création de la table user_goals...\n";
    
    // Créer la table des objectifs utilisateurs
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_goals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            goal_type ENUM('menages_mensuel', 'salaire_mensuel', 'ventes_mensuelles', 'commissions_mensuelles') NOT NULL,
            target_value DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_goal (user_id, goal_type)
        )
    ");
    
    echo "✅ Table user_goals créée avec succès !\n";
    
    // Insérer des objectifs par défaut pour tous les utilisateurs existants
    echo "\nInsertion des objectifs par défaut...\n";
    
    $default_goals = [
        'menages_mensuel' => 100,
        'salaire_mensuel' => 2000,
        'ventes_mensuelles' => 50,
        'commissions_mensuelles' => 500
    ];
    
    // Récupérer tous les utilisateurs
    $stmt = $db->query("SELECT id FROM users");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        foreach ($default_goals as $goal_type => $target_value) {
            // Vérifier si l'objectif existe déjà
            $check_stmt = $db->prepare("SELECT id FROM user_goals WHERE user_id = ? AND goal_type = ?");
            $check_stmt->execute([$user['id'], $goal_type]);
            
            if (!$check_stmt->fetch()) {
                // Insérer l'objectif par défaut
                $insert_stmt = $db->prepare("
                    INSERT INTO user_goals (user_id, goal_type, target_value) 
                    VALUES (?, ?, ?)
                ");
                $insert_stmt->execute([$user['id'], $goal_type, $target_value]);
                echo "  - Objectif {$goal_type} créé pour l'utilisateur {$user['id']}\n";
            }
        }
    }
    
    echo "\n🎉 Migration terminée avec succès !\n";
    echo "Les utilisateurs peuvent maintenant définir leurs objectifs personnalisés.\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la création: " . $e->getMessage() . "\n";
    echo "\nDétails de l'erreur:\n";
    echo $e->getTraceAsString() . "\n";
}