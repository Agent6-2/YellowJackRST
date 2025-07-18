<?php
/**
 * Script de correction des bugs du système de semaines
 * Ce script corrige les problèmes identifiés dans le système
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once 'config/database.php';

try {
    $db = getDB();
    
    echo "<h2>Correction des Bugs du Système de Semaines</h2>";
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px;'>";
    
    // 1. Vérifier et corriger la structure de la base de données
    echo "<h3>1. Vérification de la structure de la base de données...</h3>";
    
    // Vérifier si la table weeks existe
    $tables_check = $db->query("SHOW TABLES LIKE 'weeks'");
    if ($tables_check->rowCount() == 0) {
        echo "<p style='color: red;'>✗ Table 'weeks' manquante. Création en cours...</p>";
        
        $sql_weeks = "
            CREATE TABLE weeks (
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
    } else {
        echo "<p style='color: green;'>✓ Table 'weeks' existe.</p>";
    }
    
    // 2. Vérifier et ajouter les colonnes week_id manquantes
    echo "<h3>2. Vérification des colonnes week_id...</h3>";
    
    $tables_to_check = ['sales', 'cleaning_services'];
    
    foreach ($tables_to_check as $table) {
        try {
            // Vérifier si la table existe
            $table_exists = $db->query("SHOW TABLES LIKE '$table'");
            if ($table_exists->rowCount() > 0) {
                // Vérifier si la colonne week_id existe
                $column_check = $db->query("SHOW COLUMNS FROM $table LIKE 'week_id'");
                if ($column_check->rowCount() == 0) {
                    echo "<p style='color: orange;'>⚠ Colonne 'week_id' manquante dans '$table'. Ajout en cours...</p>";
                    $db->exec("ALTER TABLE $table ADD COLUMN week_id INT NULL");
                    echo "<p style='color: green;'>✓ Colonne 'week_id' ajoutée à '$table'.</p>";
                } else {
                    echo "<p style='color: green;'>✓ Colonne 'week_id' existe dans '$table'.</p>";
                }
            } else {
                echo "<p style='color: orange;'>⚠ Table '$table' n'existe pas.</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Erreur avec la table '$table': " . $e->getMessage() . "</p>";
        }
    }
    
    // 3. Vérifier qu'il y a une semaine active
    echo "<h3>3. Vérification de la semaine active...</h3>";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM weeks WHERE is_active = TRUE");
    $active_count = $stmt->fetch()['count'];
    
    if ($active_count == 0) {
        echo "<p style='color: orange;'>⚠ Aucune semaine active trouvée.</p>";
        
        // Vérifier s'il y a des semaines existantes
        $stmt = $db->query("SELECT COUNT(*) as count FROM weeks");
        $total_weeks = $stmt->fetch()['count'];
        
        if ($total_weeks > 0) {
            // Activer la dernière semaine
            echo "<p>Activation de la dernière semaine...</p>";
            $db->exec("UPDATE weeks SET is_active = TRUE WHERE id = (SELECT id FROM (SELECT id FROM weeks ORDER BY week_number DESC LIMIT 1) as temp)");
            echo "<p style='color: green;'>✓ Dernière semaine activée.</p>";
        } else {
            // Créer la première semaine
            echo "<p>Création de la première semaine...</p>";
            
            // Trouver un patron
            $stmt = $db->query("SELECT id FROM users WHERE role = 'Patron' LIMIT 1");
            $patron = $stmt->fetch();
            
            if ($patron) {
                $week_start = date('Y-m-d', strtotime('monday this week'));
                $week_end = date('Y-m-d', strtotime('sunday this week'));
                
                $stmt = $db->prepare("
                    INSERT INTO weeks (week_number, week_start, week_end, is_active, created_by) 
                    VALUES (1, ?, ?, TRUE, ?)
                ");
                $stmt->execute([$week_start, $week_end, $patron['id']]);
                
                $first_week_id = $db->lastInsertId();
                echo "<p style='color: green;'>✓ Première semaine créée (ID: $first_week_id).</p>";
                
                // Assigner les données existantes à cette semaine
                foreach ($tables_to_check as $table) {
                    try {
                        $table_exists = $db->query("SHOW TABLES LIKE '$table'");
                        if ($table_exists->rowCount() > 0) {
                            $stmt = $db->query("SELECT COUNT(*) as count FROM $table WHERE week_id IS NULL");
                            $unassigned = $stmt->fetch()['count'];
                            
                            if ($unassigned > 0) {
                                $db->exec("UPDATE $table SET week_id = $first_week_id WHERE week_id IS NULL");
                                echo "<p style='color: green;'>✓ $unassigned enregistrements de '$table' assignés à la semaine 1.</p>";
                            }
                        }
                    } catch (PDOException $e) {
                        echo "<p style='color: orange;'>⚠ Erreur lors de l'assignation pour '$table': " . $e->getMessage() . "</p>";
                    }
                }
            } else {
                echo "<p style='color: red;'>✗ Aucun patron trouvé pour créer la première semaine.</p>";
            }
        }
    } else {
        echo "<p style='color: green;'>✓ Semaine active trouvée.</p>";
    }
    
    // 4. Corriger les requêtes qui pourraient échouer
    echo "<h3>4. Vérification de l'intégrité des données...</h3>";
    
    // Vérifier les enregistrements sans week_id
    foreach ($tables_to_check as $table) {
        try {
            $table_exists = $db->query("SHOW TABLES LIKE '$table'");
            if ($table_exists->rowCount() > 0) {
                $stmt = $db->query("SELECT COUNT(*) as count FROM $table WHERE week_id IS NULL");
                $null_count = $stmt->fetch()['count'];
                
                if ($null_count > 0) {
                    echo "<p style='color: orange;'>⚠ $null_count enregistrements sans week_id dans '$table'.</p>";
                    
                    // Obtenir la semaine active
                    $stmt = $db->query("SELECT id FROM weeks WHERE is_active = TRUE LIMIT 1");
                    $active_week = $stmt->fetch();
                    
                    if ($active_week) {
                        $db->exec("UPDATE $table SET week_id = {$active_week['id']} WHERE week_id IS NULL");
                        echo "<p style='color: green;'>✓ Enregistrements assignés à la semaine active.</p>";
                    }
                } else {
                    echo "<p style='color: green;'>✓ Tous les enregistrements de '$table' ont un week_id.</p>";
                }
            }
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠ Erreur lors de la vérification de '$table': " . $e->getMessage() . "</p>";
        }
    }
    
    // 5. Vérifier les contraintes de clés étrangères
    echo "<h3>5. Vérification des contraintes de clés étrangères...</h3>";
    
    foreach ($tables_to_check as $table) {
        try {
            $table_exists = $db->query("SHOW TABLES LIKE '$table'");
            if ($table_exists->rowCount() > 0) {
                $constraint_name = "fk_{$table}_week_id";
                
                // Vérifier si la contrainte existe
                $check_constraint = $db->query("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '$table' 
                    AND COLUMN_NAME = 'week_id' 
                    AND CONSTRAINT_NAME = '$constraint_name'
                ");
                
                if ($check_constraint->rowCount() == 0) {
                    try {
                        $db->exec("ALTER TABLE $table ADD CONSTRAINT $constraint_name FOREIGN KEY (week_id) REFERENCES weeks(id)");
                        echo "<p style='color: green;'>✓ Contrainte de clé étrangère ajoutée pour '$table'.</p>";
                    } catch (PDOException $e) {
                        echo "<p style='color: orange;'>⚠ Impossible d'ajouter la contrainte pour '$table': " . $e->getMessage() . "</p>";
                    }
                } else {
                    echo "<p style='color: green;'>✓ Contrainte de clé étrangère existe pour '$table'.</p>";
                }
            }
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠ Erreur lors de la vérification des contraintes pour '$table': " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h3 style='color: green;'>✅ Correction des bugs terminée !</h3>";
    echo "<p><strong>Le système de gestion des semaines devrait maintenant fonctionner correctement.</strong></p>";
    echo "<p><a href='panel/dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Retour au Dashboard</a>";
    echo "<a href='panel/week_management.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Gestion des Semaines</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Erreur lors de la correction</h3>";
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
    echo "<p>Veuillez vérifier votre configuration de base de données.</p>";
}

echo "</div>";
?>