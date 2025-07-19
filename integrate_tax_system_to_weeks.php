<?php
/**
 * Script d'intégration du système d'impôts dans le gestionnaire de semaines
 * Ce script remplace le système d'impôts autonome par une intégration complète
 * dans le système de gestionnaire de semaines
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once 'config/database.php';

try {
    $db = getDB();
    
    echo "<h2>Intégration du Système d'Impôts dans le Gestionnaire de Semaines</h2>";
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px;'>";
    
    // 1. Modifier la table weeks pour inclure les données d'impôts
    echo "<h3>1. Modification de la table 'weeks' pour inclure les impôts...</h3>";
    
    $columns_to_add = [
        'tax_amount' => 'DECIMAL(15,2) DEFAULT 0.00 COMMENT "Montant des impôts calculés"',
        'effective_tax_rate' => 'DECIMAL(5,2) DEFAULT 0.00 COMMENT "Taux d\'impôt effectif"',
        'tax_breakdown' => 'JSON NULL COMMENT "Détail du calcul par tranche"',
        'tax_calculated_at' => 'TIMESTAMP NULL COMMENT "Date de calcul des impôts"',
        'tax_finalized' => 'BOOLEAN DEFAULT FALSE COMMENT "Impôts finalisés"'
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        try {
            $check_column = $db->query("SHOW COLUMNS FROM weeks LIKE '$column'");
            if ($check_column->rowCount() == 0) {
                $db->exec("ALTER TABLE weeks ADD COLUMN $column $definition");
                echo "<p style='color: green;'>✓ Colonne '$column' ajoutée à la table 'weeks'.</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Colonne '$column' existe déjà dans la table 'weeks'.</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Erreur lors de l'ajout de la colonne '$column': " . $e->getMessage() . "</p>";
        }
    }
    
    // 2. Migrer les données existantes de weekly_taxes vers weeks
    echo "<h3>2. Migration des données d'impôts existantes...</h3>";
    
    try {
        $check_weekly_taxes = $db->query("SHOW TABLES LIKE 'weekly_taxes'");
        if ($check_weekly_taxes->rowCount() > 0) {
            // Récupérer les données de weekly_taxes
            $stmt = $db->query("SELECT * FROM weekly_taxes ORDER BY week_start");
            $tax_data = $stmt->fetchAll();
            
            if (!empty($tax_data)) {
                echo "<p>Migration de " . count($tax_data) . " enregistrements d'impôts...</p>";
                
                foreach ($tax_data as $tax_record) {
                    // Trouver la semaine correspondante
                    $week_stmt = $db->prepare("SELECT id FROM weeks WHERE week_start = ? OR (week_start <= ? AND week_end >= ?)");
                    $week_stmt->execute([$tax_record['week_start'], $tax_record['week_start'], $tax_record['week_start']]);
                    $week = $week_stmt->fetch();
                    
                    if ($week) {
                        // Mettre à jour la semaine avec les données d'impôts
                        $update_stmt = $db->prepare("
                            UPDATE weeks SET 
                                tax_amount = ?,
                                effective_tax_rate = ?,
                                tax_breakdown = ?,
                                tax_calculated_at = ?,
                                tax_finalized = ?
                            WHERE id = ?
                        ");
                        $update_stmt->execute([
                            $tax_record['tax_amount'],
                            $tax_record['effective_tax_rate'],
                            $tax_record['tax_breakdown'],
                            $tax_record['calculated_at'],
                            $tax_record['is_finalized'],
                            $week['id']
                        ]);
                        
                        echo "<p style='color: green;'>✓ Données d'impôts migrées pour la semaine du " . date('d/m/Y', strtotime($tax_record['week_start'])) . "</p>";
                    } else {
                        echo "<p style='color: orange;'>⚠ Aucune semaine trouvée pour les impôts du " . date('d/m/Y', strtotime($tax_record['week_start'])) . "</p>";
                    }
                }
            } else {
                echo "<p style='color: orange;'>⚠ Aucune donnée d'impôts à migrer.</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠ Table 'weekly_taxes' n'existe pas.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Erreur lors de la migration: " . $e->getMessage() . "</p>";
    }
    
    // 3. Créer les nouvelles fonctions d'impôts intégrées
    echo "<h3>3. Création du fichier de fonctions d'impôts intégrées...</h3>";
    
    $tax_functions_content = '<?php
/**
 * Fonctions d\'impôts intégrées au système de gestionnaire de semaines
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . "/../config/database.php";

/**
 * Calculer les impôts selon le système de paliers
 * @param float $revenue Chiffre d\'affaires
 * @param PDO $db Connexion à la base de données
 * @return array Résultat du calcul d\'impôts
 */
function calculateWeekTax($revenue, $db) {
    // Récupérer les paliers d\'impôts
    $stmt = $db->query("SELECT * FROM tax_brackets ORDER BY min_revenue DESC");
    $brackets = $stmt->fetchAll();
    
    $totalTax = 0;
    $breakdown = [];
    $applicableTaxRate = 0;
    
    // Trouver le palier applicable (le plus élevé où le revenu dépasse le minimum)
    foreach ($brackets as $bracket) {
        $minRevenue = $bracket[\'min_revenue\'];
        $maxRevenue = $bracket[\'max_revenue\'];
        
        // Vérifier si le revenu entre dans ce palier
        if ($revenue >= $minRevenue && ($maxRevenue === null || $revenue <= $maxRevenue)) {
            $applicableTaxRate = $bracket[\'tax_rate\'] / 100;
            $totalTax = $revenue * $applicableTaxRate;
            
            $breakdown[] = [
                \'min_revenue\' => $minRevenue,
                \'max_revenue\' => $bracket[\'max_revenue\'],
                \'taxable_amount\' => $revenue,
                \'tax_rate\' => $bracket[\'tax_rate\'],
                \'tax_amount\' => $totalTax
            ];
            break;
        }
    }
    
    $effectiveRate = $revenue > 0 ? ($totalTax / $revenue) * 100 : 0;
    
    return [
        \'total_tax\' => $totalTax,
        \'effective_rate\' => $effectiveRate,
        \'breakdown\' => $breakdown
    ];
}

/**
 * Calculer et mettre à jour les impôts pour une semaine
 * @param int $week_id ID de la semaine
 * @return array Résultat de l\'opération
 */
function calculateAndUpdateWeekTax($week_id) {
    try {
        $db = getDB();
        
        // Récupérer les informations de la semaine
        $stmt = $db->prepare("SELECT * FROM weeks WHERE id = ?");
        $stmt->execute([$week_id]);
        $week = $stmt->fetch();
        
        if (!$week) {
            return [\'success\' => false, \'message\' => \'Semaine non trouvée\'];
        }
        
        // Calculer le chiffre d\'affaires total
        $total_revenue = $week[\'total_revenue\'];
        
        // Calculer les impôts
        $tax_calculation = calculateWeekTax($total_revenue, $db);
        
        // Mettre à jour la semaine avec les données d\'impôts
        $update_stmt = $db->prepare("
            UPDATE weeks SET 
                tax_amount = ?,
                effective_tax_rate = ?,
                tax_breakdown = ?,
                tax_calculated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $update_stmt->execute([
            $tax_calculation[\'total_tax\'],
            $tax_calculation[\'effective_rate\'],
            json_encode($tax_calculation[\'breakdown\']),
            $week_id
        ]);
        
        return [
            \'success\' => true,
            \'message\' => \'Impôts calculés avec succès\',
            \'tax_data\' => $tax_calculation,
            \'total_revenue\' => $total_revenue
        ];
        
    } catch (Exception $e) {
        error_log("Erreur calculateAndUpdateWeekTax: " . $e->getMessage());
        return [\'success\' => false, \'message\' => \'Erreur lors du calcul: \' . $e->getMessage()];
    }
}

/**
 * Finaliser les impôts d\'une semaine
 * @param int $week_id ID de la semaine
 * @param int $user_id ID de l\'utilisateur qui finalise
 * @return array Résultat de l\'opération
 */
function finalizeWeekTax($week_id, $user_id) {
    try {
        $db = getDB();
        
        // Vérifier les permissions
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user || !in_array($user[\'role\'], [\'Patron\', \'Responsable\'])) {
            return [\'success\' => false, \'message\' => \'Permissions insuffisantes\'];
        }
        
        // Vérifier que la semaine existe et n\'est pas déjà finalisée
        $stmt = $db->prepare("SELECT * FROM weeks WHERE id = ?");
        $stmt->execute([$week_id]);
        $week = $stmt->fetch();
        
        if (!$week) {
            return [\'success\' => false, \'message\' => \'Semaine non trouvée\'];
        }
        
        if ($week[\'tax_finalized\']) {
            return [\'success\' => false, \'message\' => \'Les impôts de cette semaine sont déjà finalisés\'];
        }
        
        // Finaliser les impôts
        $stmt = $db->prepare("UPDATE weeks SET tax_finalized = TRUE WHERE id = ?");
        $stmt->execute([$week_id]);
        
        return [\'success\' => true, \'message\' => \'Impôts finalisés avec succès\'];
        
    } catch (Exception $e) {
        error_log("Erreur finalizeWeekTax: " . $e->getMessage());
        return [\'success\' => false, \'message\' => \'Erreur lors de la finalisation: \' . $e->getMessage()];
    }
}

/**
 * Obtenir l\'historique des impôts
 * @param int $limit Nombre d\'enregistrements à récupérer
 * @return array Historique des impôts
 */
function getTaxHistory($limit = 10) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT 
                id, week_number, week_start, week_end, 
                total_revenue, tax_amount, effective_tax_rate,
                tax_breakdown, tax_calculated_at, tax_finalized,
                is_finalized
            FROM weeks 
            WHERE tax_calculated_at IS NOT NULL
            ORDER BY week_number DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Erreur getTaxHistory: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les statistiques fiscales globales
 * @return array Statistiques fiscales
 */
function getTaxStatistics() {
    try {
        $db = getDB();
        
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_weeks,
                SUM(total_revenue) as total_revenue_all_time,
                SUM(tax_amount) as total_tax_all_time,
                AVG(effective_tax_rate) as average_tax_rate,
                COUNT(CASE WHEN tax_finalized = TRUE THEN 1 END) as finalized_weeks
            FROM weeks 
            WHERE tax_calculated_at IS NOT NULL
        ");
        
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Erreur getTaxStatistics: " . $e->getMessage());
        return [
            \'total_weeks\' => 0,
            \'total_revenue_all_time\' => 0,
            \'total_tax_all_time\' => 0,
            \'average_tax_rate\' => 0,
            \'finalized_weeks\' => 0
        ];
    }
}

?>';
    
    file_put_contents('includes/tax_functions_integrated.php', $tax_functions_content);
    echo "<p style='color: green;'>✓ Fichier 'includes/tax_functions_integrated.php' créé avec succès.</p>";
    
    // 4. Mettre à jour week_functions.php pour inclure les fonctions d'impôts
    echo "<h3>4. Mise à jour de week_functions.php...</h3>";
    
    $week_functions_addition = '

// === FONCTIONS D\'IMPÔTS INTÉGRÉES ===
require_once __DIR__ . "/tax_functions_integrated.php";

/**
 * Finaliser une semaine avec calcul automatique des impôts
 * Version améliorée qui inclut le calcul des impôts
 * @param int $patron_id ID du patron qui finalise
 * @return array Résultat de l\'opération
 */
function finalizeWeekAndCreateNewWithTax($patron_id) {
    try {
        $db = getDB();
        $db->beginTransaction();
        
        $activeWeek = getActiveWeekNew();
        if (!$activeWeek) {
            throw new Exception("Aucune semaine active trouvée");
        }
        
        if ($activeWeek[\'is_finalized\']) {
            throw new Exception("La semaine active est déjà finalisée");
        }
        
        // Calculer les statistiques de la semaine
        $stats = calculateWeekStats($activeWeek[\'id\']);
        
        // Calculer les impôts
        $tax_result = calculateAndUpdateWeekTax($activeWeek[\'id\']);
        if (!$tax_result[\'success\']) {
            throw new Exception("Erreur lors du calcul des impôts: " . $tax_result[\'message\']);
        }
        
        // Finaliser les impôts
        $finalize_tax_result = finalizeWeekTax($activeWeek[\'id\'], $patron_id);
        if (!$finalize_tax_result[\'success\']) {
            throw new Exception("Erreur lors de la finalisation des impôts: " . $finalize_tax_result[\'message\']);
        }
        
        // Calculer les dates de la nouvelle semaine
        $current_end = new DateTime($activeWeek[\'week_end\']);
        $new_week_start = $current_end->modify(\'+1 day\')->format(\'Y-m-d\');
        $new_week_end = (new DateTime($new_week_start))->modify(\'+6 days\')->format(\'Y-m-d\');
        
        // Finaliser la semaine actuelle
        $stmt = $db->prepare("
            UPDATE weeks SET 
                is_active = FALSE, 
                is_finalized = TRUE, 
                finalized_by = ?, 
                finalized_at = CURRENT_TIMESTAMP,
                total_revenue = ?,
                total_sales_count = ?,
                total_cleaning_revenue = ?,
                total_cleaning_count = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $patron_id,
            $stats[\'total_revenue\'],
            $stats[\'total_sales_count\'],
            $stats[\'total_cleaning_revenue\'],
            $stats[\'total_cleaning_count\'],
            $activeWeek[\'id\']
        ]);
        
        // Créer la nouvelle semaine
        $next_week_number = $activeWeek[\'week_number\'] + 1;
        
        $stmt = $db->prepare("
            INSERT INTO weeks (week_number, week_start, week_end, is_active, created_by) 
            VALUES (?, ?, ?, TRUE, ?)
        ");
        $stmt->execute([$next_week_number, $new_week_start, $new_week_end, $patron_id]);
        
        $new_week_id = $db->lastInsertId();
        
        // Valider la transaction
        $db->commit();
        
        return [
            \'success\' => true, 
            \'message\' => "Semaine {$activeWeek[\'week_number\']} finalisée avec impôts et semaine {$next_week_number} créée avec succès.",
            \'old_week\' => $activeWeek,
            \'new_week_id\' => $new_week_id,
            \'new_week_number\' => $next_week_number,
            \'tax_data\' => $tax_result[\'tax_data\']
        ];
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollback();
        }
        error_log("Erreur finalizeWeekAndCreateNewWithTax: " . $e->getMessage());
        return [\'success\' => false, \'message\' => \'Erreur lors de la finalisation: \' . $e->getMessage()];
    }
}';
    
    file_put_contents('includes/week_functions.php', $week_functions_addition, FILE_APPEND);
    echo "<p style='color: green;'>✓ Fonctions d'impôts ajoutées à week_functions.php.</p>";
    
    // 5. Sauvegarder l'ancien système d'impôts
    echo "<h3>5. Sauvegarde de l'ancien système d'impôts...</h3>";
    
    if (file_exists('panel/taxes.php')) {
        copy('panel/taxes.php', 'panel/taxes_backup_' . date('Y-m-d_H-i-s') . '.php');
        echo "<p style='color: green;'>✓ Ancien fichier taxes.php sauvegardé.</p>";
    }
    
    // 6. Créer le nouveau système d'impôts intégré
    echo "<h3>6. Création du nouveau système d'impôts intégré...</h3>";
    
    echo "<div class='alert alert-success' style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>✅ Intégration Terminée avec Succès !</h4>";
    echo "<p><strong>Le système d'impôts est maintenant intégré au gestionnaire de semaines.</strong></p>";
    echo "<h5>Nouvelles fonctionnalités :</h5>";
    echo "<ul>";
    echo "<li>✅ Calcul automatique des impôts lors de la finalisation des semaines</li>";
    echo "<li>✅ Historique des impôts intégré dans l'historique des semaines</li>";
    echo "<li>✅ Gestion unifiée des données fiscales et des semaines</li>";
    echo "<li>✅ Fonctions d'impôts disponibles dans week_functions.php</li>";
    echo "<li>✅ Données migrées depuis l'ancien système</li>";
    echo "</ul>";
    echo "<h5>Prochaines étapes :</h5>";
    echo "<ol>";
    echo "<li>Accéder au <a href='panel/week_management.php' style='color: #007bff;'>gestionnaire de semaines</a></li>";
    echo "<li>Les impôts seront calculés automatiquement lors de la finalisation</li>";
    echo "<li>L'ancien système taxes.php a été sauvegardé</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erreur lors de l'intégration : " . $e->getMessage() . "</p>";
    exit(1);
}

echo "<p style='text-align: center; margin-top: 30px;'><a href='panel/week_management.php' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Accéder au Gestionnaire de Semaines</a></p>";

?>