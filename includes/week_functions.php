<?php
/**
 * Fonctions pour la gestion du système de semaines avec ID unique
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Obtenir la semaine active actuelle
 * @return array|null Données de la semaine active ou null si aucune semaine active
 */
function getActiveWeekNew() {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT * FROM weeks WHERE is_active = TRUE LIMIT 1");
        $stmt->execute();
        $activeWeek = $stmt->fetch();
        
        return $activeWeek ?: null;
        
    } catch (Exception $e) {
        error_log("Erreur getActiveWeekNew: " . $e->getMessage());
        return null;
    }
}

/**
 * Vérifier si une date est dans la semaine active
 * @param string $date Date à vérifier
 * @return bool True si la date est dans la semaine active
 */
function isDateInActiveWeekNew($date) {
    $activeWeek = getActiveWeekNew();
    if (!$activeWeek) {
        return false;
    }
    
    $checkDate = date('Y-m-d', strtotime($date));
    
    return $checkDate >= $activeWeek['week_start'] && $checkDate <= $activeWeek['week_end'];
}

/**
 * Obtenir toutes les semaines (pour les sélecteurs)
 * @return array Liste des semaines
 */
function getAllWeeks() {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT * FROM weeks ORDER BY week_number DESC");
        $stmt->execute();
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Erreur getAllWeeks: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir une semaine par son ID
 * @param int $week_id ID de la semaine
 * @return array|null Données de la semaine ou null
 */
function getWeekById($week_id) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT * FROM weeks WHERE id = ?");
        $stmt->execute([$week_id]);
        
        return $stmt->fetch() ?: null;
        
    } catch (Exception $e) {
        error_log("Erreur getWeekById: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtenir une semaine par son numéro
 * @param int $week_number Numéro de la semaine
 * @return array|null Données de la semaine ou null
 */
function getWeekByNumber($week_number) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT * FROM weeks WHERE week_number = ?");
        $stmt->execute([$week_number]);
        
        return $stmt->fetch() ?: null;
        
    } catch (Exception $e) {
        error_log("Erreur getWeekByNumber: " . $e->getMessage());
        return null;
    }
}

/**
 * Finaliser la semaine active et créer une nouvelle semaine
 * Seul le patron peut effectuer cette action
 * @param int $patron_id ID du patron qui finalise
 * @param string $new_week_start Date de début de la nouvelle semaine
 * @param string $new_week_end Date de fin de la nouvelle semaine
 * @return array Résultat de l'opération
 */
function finalizeWeekAndCreateNew($patron_id, $new_week_start, $new_week_end) {
    try {
        $db = getDB();
        
        // Vérifier que l'utilisateur est bien un patron
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$patron_id]);
        $user = $stmt->fetch();
        
        if (!$user || $user['role'] !== 'Patron') {
            return ['success' => false, 'message' => 'Seul un patron peut finaliser une semaine.'];
        }
        
        // Commencer une transaction
        $db->beginTransaction();
        
        // Obtenir la semaine active
        $activeWeek = getActiveWeekNew();
        if (!$activeWeek) {
            $db->rollback();
            return ['success' => false, 'message' => 'Aucune semaine active trouvée.'];
        }
        
        // Calculer les statistiques de la semaine active
        $stats = calculateWeekStats($activeWeek['id']);
        
        // Finaliser la semaine active
        $stmt = $db->prepare("
            UPDATE weeks 
            SET is_active = FALSE, 
                is_finalized = TRUE, 
                finalized_by = ?, 
                finalized_at = NOW(),
                total_revenue = ?,
                total_sales_count = ?,
                total_cleaning_revenue = ?,
                total_cleaning_count = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $patron_id,
            $stats['total_revenue'],
            $stats['total_sales_count'],
            $stats['total_cleaning_revenue'],
            $stats['total_cleaning_count'],
            $activeWeek['id']
        ]);
        
        // Créer la nouvelle semaine
        $next_week_number = $activeWeek['week_number'] + 1;
        
        $stmt = $db->prepare("
            INSERT INTO weeks (week_number, week_start, week_end, is_active, created_by) 
            VALUES (?, ?, ?, TRUE, ?)
        ");
        $stmt->execute([$next_week_number, $new_week_start, $new_week_end, $patron_id]);
        
        $new_week_id = $db->lastInsertId();
        
        // Valider la transaction
        $db->commit();
        
        return [
            'success' => true, 
            'message' => "Semaine {$activeWeek['week_number']} finalisée et semaine {$next_week_number} créée avec succès.",
            'old_week' => $activeWeek,
            'new_week_id' => $new_week_id,
            'new_week_number' => $next_week_number
        ];
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollback();
        }
        error_log("Erreur finalizeWeekAndCreateNew: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors de la finalisation: ' . $e->getMessage()];
    }
}

/**
 * Calculer les statistiques d'une semaine
 * @param int $week_id ID de la semaine
 * @return array Statistiques de la semaine
 */
function calculateWeekStats($week_id) {
    try {
        $db = getDB();
        
        // Vérifier si les tables existent et ont la colonne week_id
        $sales_stats = ['total_sales_count' => 0, 'total_sales_revenue' => 0];
        $cleaning_stats = ['total_cleaning_count' => 0, 'total_cleaning_revenue' => 0];
        
        // Statistiques des ventes
        try {
            $check_sales = $db->query("SHOW TABLES LIKE 'sales'");
            if ($check_sales->rowCount() > 0) {
                $check_column = $db->query("SHOW COLUMNS FROM sales LIKE 'week_id'");
                if ($check_column->rowCount() > 0) {
                    $stmt = $db->prepare("
                        SELECT 
                            COUNT(*) as total_sales_count,
                            COALESCE(SUM(final_amount), 0) as total_sales_revenue
                        FROM sales 
                        WHERE week_id = ?
                    ");
                    $stmt->execute([$week_id]);
                    $sales_stats = $stmt->fetch();
                }
            }
        } catch (Exception $e) {
            error_log("Erreur statistiques ventes: " . $e->getMessage());
        }
        
        // Statistiques du ménage
        try {
            $check_cleaning = $db->query("SHOW TABLES LIKE 'cleaning_services'");
            if ($check_cleaning->rowCount() > 0) {
                $check_column = $db->query("SHOW COLUMNS FROM cleaning_services LIKE 'week_id'");
                if ($check_column->rowCount() > 0) {
                    $stmt = $db->prepare("
                        SELECT 
                            COALESCE(SUM(cleaning_count), 0) as total_cleaning_count
                        FROM cleaning_services 
                        WHERE week_id = ? AND status = 'completed' AND user_id != 999
                    ");
                    $stmt->execute([$week_id]);
                    $cleaning_result = $stmt->fetch();
                    
                    // Calculer le CA des ménages : 60$ par ménage
                    $cleaning_stats = [
                        'total_cleaning_count' => $cleaning_result['total_cleaning_count'] ?? 0,
                        'total_cleaning_revenue' => ($cleaning_result['total_cleaning_count'] ?? 0) * 60
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Erreur statistiques ménage: " . $e->getMessage());
        }
        
        return [
            'total_sales_count' => $sales_stats['total_sales_count'] ?? 0,
            'total_sales_revenue' => $sales_stats['total_sales_revenue'] ?? 0,
            'total_cleaning_count' => $cleaning_stats['total_cleaning_count'] ?? 0,
            'total_cleaning_revenue' => $cleaning_stats['total_cleaning_revenue'] ?? 0,
            'total_revenue' => ($sales_stats['total_sales_revenue'] ?? 0) + ($cleaning_stats['total_cleaning_revenue'] ?? 0)
        ];
        
    } catch (Exception $e) {
        error_log("Erreur calculateWeekStats: " . $e->getMessage());
        return [
            'total_sales_count' => 0,
            'total_sales_revenue' => 0,
            'total_cleaning_count' => 0,
            'total_cleaning_revenue' => 0,
            'total_revenue' => 0
        ];
    }
}

/**
 * Assigner automatiquement les nouvelles ventes/ménages à la semaine active
 * @param string $table_name Nom de la table (sales ou cleaning_services)
 * @param int $record_id ID de l'enregistrement
 * @return bool True si l'assignation a réussi
 */
function assignToActiveWeek($table_name, $record_id) {
    try {
        $db = getDB();
        
        $activeWeek = getActiveWeekNew();
        if (!$activeWeek) {
            error_log("Aucune semaine active pour assigner l'enregistrement $record_id de $table_name");
            return false;
        }
        
        // Vérifier que la table est autorisée
        $allowed_tables = ['sales', 'cleaning_services'];
        if (!in_array($table_name, $allowed_tables)) {
            error_log("Table non autorisée: $table_name");
            return false;
        }
        
        $stmt = $db->prepare("UPDATE $table_name SET week_id = ? WHERE id = ?");
        $stmt->execute([$activeWeek['id'], $record_id]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur assignToActiveWeek: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir les statistiques en temps réel de la semaine active
 * @return array Statistiques de la semaine active
 */
function getActiveWeekStats() {
    $activeWeek = getActiveWeekNew();
    if (!$activeWeek) {
        return [
            'week_number' => 0,
            'week_start' => null,
            'week_end' => null,
            'total_sales_count' => 0,
            'total_sales_revenue' => 0,
            'total_cleaning_count' => 0,
            'total_cleaning_revenue' => 0,
            'total_revenue' => 0
        ];
    }
    
    $stats = calculateWeekStats($activeWeek['id']);
    $stats['week_number'] = $activeWeek['week_number'];
    $stats['week_start'] = $activeWeek['week_start'];
    $stats['week_end'] = $activeWeek['week_end'];
    
    return $stats;
}

/**
 * Vérifier si l'utilisateur peut finaliser une semaine
 * @param int $user_id ID de l'utilisateur
 * @return bool True si l'utilisateur peut finaliser
 */
function canFinalizeWeek($user_id) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        return $user && $user['role'] === 'Patron';
        
    } catch (Exception $e) {
        error_log("Erreur canFinalizeWeek: " . $e->getMessage());
        return false;
    }
}

?>