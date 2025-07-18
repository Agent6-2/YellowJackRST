<?php
/**
 * Script de création du système de gestion des semaines avec ID unique
 * Ce script doit être exécuté une seule fois pour initialiser le système
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once 'config/database.php';

try {
    $db = getDB();
    
    echo "<h2>Initialisation du Système de Gestion des Semaines</h2>";
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px;'>";
    
    // 1. Créer la table weeks si elle n'existe pas
    echo "<h3>1. Création de la table 'weeks'...</h3>";
    
    $sql_weeks = "
        CREATE TABLE IF NOT EXISTS weeks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            week_number INT NOT NULL UNIQUE,
            week_start DATE NOT NULL,
            week_end DATE NOT NULL,
            is_active BOOLEAN DEFAULT FALSE,
            is_finalized BOOLEAN DEFAULT FALSE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            finalized_by INT NULL,
            finalized_at TIMESTAMP NULL,
            total_revenue DECIMAL(10,2) DEFAULT 0.00,
            total_sales_count INT DEFAULT 0,
            total_cleaning_revenue DECIMAL(10,2) DEFAULT 0.00,
            total_cleaning_count INT DEFAULT 0,
            FOREIGN KEY (created_by) REFERENCES users(id),
            FOREIGN KEY (finalized_by) REFERENCES users(id)
        )
    ";
    
    $db->exec($sql_weeks);
    echo "<p style='color: green;'>✓ Table 'weeks' créée avec succès.</p>";
    
    // 2. Ajouter la colonne week_id aux tables existantes
    echo "<h3>2. Ajout des colonnes week_id...</h3>";
    
    $tables_to_update = ['sales', 'cleaning_services', 'weekly_performance', 'weekly_taxes'];
    
    foreach ($tables_to_update as $table) {
        try {
            // Vérifier si la colonne existe déjà
            $check_column = $db->query("SHOW COLUMNS FROM $table LIKE 'week_id'");
            if ($check_column->rowCount() == 0) {
                $db->exec("ALTER TABLE $table ADD COLUMN week_id INT NULL");
                echo "<p style='color: green;'>✓ Colonne 'week_id' ajoutée à la table '$table'.</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Colonne 'week_id' existe déjà dans la table '$table'.</p>";
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                echo "<p style='color: orange;'>⚠ Table '$table' n'existe pas, ignorée.</p>";
            } else {
                echo "<p style='color: red;'>✗ Erreur lors de la modification de la table '$table': " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // 3. Vérifier s'il y a déjà une semaine active
    echo "<h3>3. Vérification des semaines existantes...</h3>";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM weeks");
    $week_count = $stmt->fetch()['count'];
    
    if ($week_count == 0) {
        echo "<p>Aucune semaine trouvée. Création de la première semaine...</p>";
        
        // Trouver un patron pour créer la première semaine
        $stmt = $db->query("SELECT id FROM users WHERE role = 'Patron' LIMIT 1");
        $patron = $stmt->fetch();
        
        if (!$patron) {
            echo "<p style='color: red;'>✗ Aucun patron trouvé. Veuillez créer un utilisateur avec le rôle 'Patron' d'abord.</p>";
        } else {
            // Créer la première semaine (semaine courante)
            $week_start = date('Y-m-d', strtotime('monday this week'));
            $week_end = date('Y-m-d', strtotime('sunday this week'));
            
            $stmt = $db->prepare("
                INSERT INTO weeks (week_number, week_start, week_end, is_active, created_by) 
                VALUES (1, ?, ?, TRUE, ?)
            ");
            $stmt->execute([$week_start, $week_end, $patron['id']]);
            
            $first_week_id = $db->lastInsertId();
            
            echo "<p style='color: green;'>✓ Première semaine créée (ID: $first_week_id, du $week_start au $week_end).</p>";
            
            // 4. Assigner les données existantes à la première semaine
            echo "<h3>4. Assignation des données existantes...</h3>";
            
            foreach ($tables_to_update as $table) {
                try {
                    // Vérifier si la table existe et a des données
                    $check_table = $db->query("SHOW TABLES LIKE '$table'");
                    if ($check_table->rowCount() > 0) {
                        $stmt = $db->query("SELECT COUNT(*) as count FROM $table WHERE week_id IS NULL");
                        $unassigned_count = $stmt->fetch()['count'];
                        
                        if ($unassigned_count > 0) {
                            $db->exec("UPDATE $table SET week_id = $first_week_id WHERE week_id IS NULL");
                            echo "<p style='color: green;'>✓ $unassigned_count enregistrements de '$table' assignés à la semaine 1.</p>";
                        } else {
                            echo "<p style='color: blue;'>ℹ Aucun enregistrement non-assigné dans '$table'.</p>";
                        }
                    }
                } catch (PDOException $e) {
                    echo "<p style='color: orange;'>⚠ Erreur lors de l'assignation pour '$table': " . $e->getMessage() . "</p>";
                }
            }
        }
    } else {
        echo "<p style='color: blue;'>ℹ $week_count semaine(s) déjà existante(s).</p>";
        
        // Vérifier qu'il y a une semaine active
        $stmt = $db->query("SELECT COUNT(*) as count FROM weeks WHERE is_active = TRUE");
        $active_count = $stmt->fetch()['count'];
        
        if ($active_count == 0) {
            echo "<p style='color: orange;'>⚠ Aucune semaine active trouvée. Activation de la dernière semaine...</p>";
            $db->exec("UPDATE weeks SET is_active = TRUE WHERE id = (SELECT id FROM (SELECT id FROM weeks ORDER BY week_number DESC LIMIT 1) as temp)");
            echo "<p style='color: green;'>✓ Dernière semaine activée.</p>";
        } else {
            echo "<p style='color: green;'>✓ Semaine active trouvée.</p>";
        }
    }
    
    // 5. Ajouter les contraintes de clés étrangères
    echo "<h3>5. Ajout des contraintes de clés étrangères...</h3>";
    
    foreach ($tables_to_update as $table) {
        try {
            $check_table = $db->query("SHOW TABLES LIKE '$table'");
            if ($check_table->rowCount() > 0) {
                // Vérifier si la contrainte existe déjà
                $constraint_name = "fk_{$table}_week_id";
                $check_constraint = $db->query("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_NAME = '$table' 
                    AND COLUMN_NAME = 'week_id' 
                    AND CONSTRAINT_NAME = '$constraint_name'
                ");
                
                if ($check_constraint->rowCount() == 0) {
                    $db->exec("ALTER TABLE $table ADD CONSTRAINT $constraint_name FOREIGN KEY (week_id) REFERENCES weeks(id)");
                    echo "<p style='color: green;'>✓ Contrainte de clé étrangère ajoutée pour '$table'.</p>";
                } else {
                    echo "<p style='color: orange;'>⚠ Contrainte de clé étrangère existe déjà pour '$table'.</p>";
                }
            }
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠ Erreur lors de l'ajout de la contrainte pour '$table': " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h3 style='color: green;'>✅ Initialisation terminée avec succès !</h3>";
    echo "<p><strong>Le système de gestion des semaines est maintenant opérationnel.</strong></p>";
    echo "<p><a href='panel/week_management.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Accéder à la Gestion des Semaines</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Erreur lors de l'initialisation</h3>";
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
    echo "<p>Veuillez vérifier votre configuration de base de données.</p>";
}

echo "</div>";
?>