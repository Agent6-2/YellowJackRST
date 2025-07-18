<?php
/**
 * Script de vérification de l'état du système de semaines
 * Ce script affiche l'état actuel des tables et des données
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once 'config/database.php';

try {
    $db = getDB();
    
    echo "<h2>État du Système de Gestion des Semaines</h2>";
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px;'>";
    
    // 1. Vérifier la structure des tables
    echo "<h3>1. Structure des tables</h3>";
    
    // Vérifier la table weeks
    $tables_check = $db->query("SHOW TABLES LIKE 'weeks'");
    if ($tables_check->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Table 'weeks' existe.</p>";
        
        // Afficher la structure
        echo "<details>";
        echo "<summary>Structure de la table 'weeks'</summary>";
        echo "<pre>";
        $columns = $db->query("SHOW COLUMNS FROM weeks");
        while ($column = $columns->fetch()) {
            echo "{$column['Field']} - {$column['Type']} - {$column['Null']} - {$column['Key']}\n";
        }
        echo "</pre>";
        echo "</details>";
    } else {
        echo "<p style='color: red;'>✗ Table 'weeks' n'existe pas.</p>";
    }
    
    // Vérifier les colonnes week_id dans les autres tables
    $tables_to_check = ['sales', 'cleaning_services', 'weekly_performance', 'weekly_taxes'];
    
    foreach ($tables_to_check as $table) {
        $table_exists = $db->query("SHOW TABLES LIKE '$table'");
        if ($table_exists->rowCount() > 0) {
            echo "<p style='color: green;'>✓ Table '$table' existe.</p>";
            
            // Vérifier la colonne week_id
            $column_check = $db->query("SHOW COLUMNS FROM $table LIKE 'week_id'");
            if ($column_check->rowCount() > 0) {
                echo "<p style='margin-left: 20px; color: green;'>✓ Colonne 'week_id' existe dans '$table'.</p>";
                
                // Vérifier la contrainte de clé étrangère
                $constraint_name = "fk_{$table}_week_id";
                $check_constraint = $db->query("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '$table' 
                    AND COLUMN_NAME = 'week_id' 
                    AND CONSTRAINT_NAME = '$constraint_name'
                ");
                
                if ($check_constraint->rowCount() > 0) {
                    echo "<p style='margin-left: 40px; color: green;'>✓ Contrainte de clé étrangère existe.</p>";
                } else {
                    echo "<p style='margin-left: 40px; color: orange;'>⚠ Contrainte de clé étrangère manquante.</p>";
                }
            } else {
                echo "<p style='margin-left: 20px; color: red;'>✗ Colonne 'week_id' n'existe pas dans '$table'.</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠ Table '$table' n'existe pas.</p>";
        }
    }
    
    // 2. Vérifier les données des semaines
    echo "<h3>2. Données des semaines</h3>";
    
    $weeks_check = $db->query("SHOW TABLES LIKE 'weeks'");
    if ($weeks_check->rowCount() > 0) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM weeks");
        $weeks_count = $stmt->fetch()['count'];
        
        echo "<p>Nombre total de semaines: <strong>$weeks_count</strong></p>";
        
        if ($weeks_count > 0) {
            // Vérifier la semaine active
            $stmt = $db->query("SELECT COUNT(*) as count FROM weeks WHERE is_active = TRUE");
            $active_count = $stmt->fetch()['count'];
            
            if ($active_count == 1) {
                echo "<p style='color: green;'>✓ Une semaine active trouvée.</p>";
                
                // Afficher les détails de la semaine active
                $stmt = $db->query("SELECT * FROM weeks WHERE is_active = TRUE");
                $active_week = $stmt->fetch();
                
                echo "<div style='background-color: #f8f9fa; padding: 10px; border-radius: 5px;'>";
                echo "<h4>Semaine Active</h4>";
                echo "<p>";
                echo "ID: <strong>{$active_week['id']}</strong><br>";
                echo "Numéro: <strong>{$active_week['week_number']}</strong><br>";
                echo "Période: <strong>{$active_week['week_start']}</strong> au <strong>{$active_week['week_end']}</strong><br>";
                echo "Finalisée: <strong>" . ($active_week['is_finalized'] ? 'Oui' : 'Non') . "</strong>";
                echo "</p>";
                echo "</div>";
                
                // Vérifier les enregistrements liés à la semaine active
                echo "<h4>Enregistrements liés à la semaine active</h4>";
                
                foreach ($tables_to_check as $table) {
                    $table_exists = $db->query("SHOW TABLES LIKE '$table'");
                    if ($table_exists->rowCount() > 0) {
                        $column_check = $db->query("SHOW COLUMNS FROM $table LIKE 'week_id'");
                        if ($column_check->rowCount() > 0) {
                            $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table WHERE week_id = ?");
                            $stmt->execute([$active_week['id']]);
                            $linked_count = $stmt->fetch()['count'];
                            
                            echo "<p>$table: <strong>$linked_count</strong> enregistrements</p>";
                        }
                    }
                }
            } elseif ($active_count > 1) {
                echo "<p style='color: red;'>✗ Plusieurs semaines actives trouvées ($active_count). Cela devrait être corrigé.</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Aucune semaine active trouvée.</p>";
            }
            
            // Afficher un résumé des semaines
            $stmt = $db->query("SELECT * FROM weeks ORDER BY week_number DESC");
            $all_weeks = $stmt->fetchAll();
            
            echo "<h4>Résumé des semaines</h4>";
            echo "<table style='width: 100%; border-collapse: collapse;'>";
            echo "<tr style='background-color: #f2f2f2;'>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>ID</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Numéro</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Période</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Statut</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>CA Total</th>";
            echo "</tr>";
            
            foreach ($all_weeks as $week) {
                $status = $week['is_active'] ? 'Active' : ($week['is_finalized'] ? 'Finalisée' : 'En attente');
                $status_color = $week['is_active'] ? 'green' : ($week['is_finalized'] ? 'blue' : 'orange');
                
                echo "<tr>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$week['id']}</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$week['week_number']}</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$week['week_start']} au {$week['week_end']}</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px; color: $status_color;'>$status</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$week['total_revenue']}$</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠ Aucune semaine n'existe dans la base de données.</p>";
        }
    }
    
    // 3. Vérifier les enregistrements sans week_id
    echo "<h3>3. Enregistrements sans week_id</h3>";
    
    foreach ($tables_to_check as $table) {
        $table_exists = $db->query("SHOW TABLES LIKE '$table'");
        if ($table_exists->rowCount() > 0) {
            $column_check = $db->query("SHOW COLUMNS FROM $table LIKE 'week_id'");
            if ($column_check->rowCount() > 0) {
                $stmt = $db->query("SELECT COUNT(*) as count FROM $table WHERE week_id IS NULL");
                $null_count = $stmt->fetch()['count'];
                
                if ($null_count > 0) {
                    echo "<p style='color: orange;'>⚠ $null_count enregistrements sans week_id dans '$table'.</p>";
                } else {
                    echo "<p style='color: green;'>✓ Tous les enregistrements de '$table' ont un week_id.</p>";
                }
            }
        }
    }
    
    echo "<h3 style='color: green;'>✅ Vérification terminée</h3>";
    echo "<p><a href='panel/dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Retour au Dashboard</a>";
    echo "<a href='panel/week_management.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Gestion des Semaines</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Erreur lors de la vérification</h3>";
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
    echo "<p>Veuillez vérifier votre configuration de base de données.</p>";
}

echo "</div>";
?>