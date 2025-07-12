<?php
/**
 * Script de réinitialisation hebdomadaire des performances
 * À exécuter chaque vendredi à 00h00 via cron job
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../config/database.php';

// Fonction pour obtenir le vendredi de début de la semaine courante (vendredi à vendredi exclu)
function getFridayOfWeek($date) {
    $timestamp = strtotime($date);
    $dayOfWeek = date('N', $timestamp); // 1 = Lundi, 5 = Vendredi
    
    if ($dayOfWeek == 5) {
        // Si c'est vendredi, c'est le début de la semaine
        return date('Y-m-d', $timestamp);
    } elseif ($dayOfWeek == 6 || $dayOfWeek == 7) {
        // Si c'est samedi ou dimanche, prendre le vendredi précédent
        $daysToSubtract = $dayOfWeek - 5;
        return date('Y-m-d', strtotime("-$daysToSubtract days", $timestamp));
    } else {
        // Si c'est lundi à jeudi, prendre le vendredi précédent
    $daysToSubtract = $dayOfWeek + 2; // lundi=3, mardi=4, mercredi=5, jeudi=6 (logique vendredi-vendredi exclu)
        return date('Y-m-d', strtotime("-$daysToSubtract days", $timestamp));
    }
}

// Fonction pour obtenir le vendredi suivant (fin de semaine)
function getFridayAfterFriday($friday) {
    return date('Y-m-d', strtotime('+7 days', strtotime($friday)));
}

try {
    $db = getDB();
    
    // Log du début du script
    error_log("[" . date('Y-m-d H:i:s') . "] Début du script de réinitialisation hebdomadaire");
    
    // Vérifier que nous sommes bien vendredi
    $today = date('Y-m-d');
    $dayOfWeek = date('N'); // 1 = Lundi, 5 = Vendredi
    
    if ($dayOfWeek != 5) {
        error_log("[" . date('Y-m-d H:i:s') . "] Erreur: Le script doit être exécuté un vendredi. Jour actuel: $dayOfWeek");
        exit(1);
    }
    
    // Note: La finalisation des performances doit être faite manuellement via l'interface
    // La finalisation automatique a été désactivée pour nécessiter une confirmation manuelle
    $previous_week_start = date('Y-m-d', strtotime('-7 days', strtotime($today)));
    $previous_week_end = getFridayAfterFriday($previous_week_start);
    
    // Vérifier s'il y a des performances non finalisées de la semaine précédente
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM weekly_performance 
        WHERE week_start = ? AND is_finalized = 0
    ");
    $stmt->execute([$previous_week_start]);
    $unfinalized_count = $stmt->fetch()['count'];
    
    if ($unfinalized_count > 0) {
        error_log("[" . date('Y-m-d H:i:s') . "] ATTENTION: $unfinalized_count performances non finalisées pour la semaine du $previous_week_start - Finalisation manuelle requise");
    }
    
    $finalized_count = 0; // Aucune finalisation automatique
    
    // Calculer automatiquement les performances pour la nouvelle semaine
    // (optionnel - peut être fait manuellement par les patrons)
    
    // Récupérer la configuration des primes
    $stmt = $db->prepare("SELECT config_key, config_value FROM weekly_performance_config");
    $stmt->execute();
    $config = [];
    while ($row = $stmt->fetch()) {
        $config[$row['config_key']] = $row['config_value'];
    }
    
    // Récupérer tous les utilisateurs actifs (CDD, CDI, Responsable, Patron)
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, role 
        FROM users 
        WHERE role IN ('CDD', 'CDI', 'Responsable', 'Patron') AND status = 'active'
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    $new_week_start = $today; // Vendredi actuel
    $new_week_end = getFridayAfterFriday($new_week_start);
    
    $processed_users = 0;
    
    foreach ($users as $employee) {
        // Initialiser les performances pour la nouvelle semaine avec des valeurs à zéro
        // Les vraies valeurs seront calculées plus tard quand il y aura des données
        
        $stmt = $db->prepare("
            INSERT IGNORE INTO weekly_performance 
            (user_id, week_start, week_end, total_menages, total_salary_menage, total_hours_menage, 
             total_ventes, total_revenue, total_commissions, prime_menage, prime_ventes, prime_totale, 
             calculated_at, is_finalized) 
            VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0, 0, 0, NOW(), 0)
        ");
        
        $stmt->execute([
            $employee['id'], $new_week_start, $new_week_end
        ]);
        
        if ($stmt->rowCount() > 0) {
            $processed_users++;
        }
    }
    
    error_log("[" . date('Y-m-d H:i:s') . "] Initialisé les performances pour $processed_users utilisateurs pour la semaine du $new_week_start");
    
    // Nettoyer les anciennes performances (garder seulement les 12 dernières semaines)
    $cutoff_date = date('Y-m-d', strtotime('-84 days')); // 12 semaines
    
    $stmt = $db->prepare("
        DELETE FROM weekly_performance 
        WHERE week_start < ? AND is_finalized = 1
    ");
    $stmt->execute([$cutoff_date]);
    $deleted_count = $stmt->rowCount();
    
    if ($deleted_count > 0) {
        error_log("[" . date('Y-m-d H:i:s') . "] Supprimé $deleted_count anciennes performances (plus de 12 semaines)");
    }
    
    // Créer un rapport de résumé
    $summary = [
        'date' => $today,
        'previous_week' => $previous_week_start . ' - ' . $previous_week_end,
        'new_week' => $new_week_start . ' - ' . $new_week_end,
        'finalized_performances' => $finalized_count,
        'initialized_users' => $processed_users,
        'deleted_old_records' => $deleted_count
    ];
    
    // Sauvegarder le rapport dans un fichier log
    $log_content = "=== RAPPORT DE RÉINITIALISATION HEBDOMADAIRE ===\n";
    $log_content .= "Date: " . $summary['date'] . "\n";
    $log_content .= "Semaine précédente finalisée: " . $summary['previous_week'] . "\n";
    $log_content .= "Nouvelle semaine initialisée: " . $summary['new_week'] . "\n";
    $log_content .= "Performances finalisées: " . $summary['finalized_performances'] . "\n";
    $log_content .= "Utilisateurs initialisés: " . $summary['initialized_users'] . "\n";
    $log_content .= "Anciens enregistrements supprimés: " . $summary['deleted_old_records'] . "\n";
    $log_content .= "================================================\n\n";
    
    $log_file = '../logs/weekly_reset_' . date('Y-m') . '.log';
    
    // Créer le dossier logs s'il n'existe pas
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_content, FILE_APPEND | LOCK_EX);
    
    error_log("[" . date('Y-m-d H:i:s') . "] Script de réinitialisation hebdomadaire terminé avec succès");
    
    echo "Réinitialisation hebdomadaire terminée avec succès\n";
    echo "Rapport sauvegardé dans: $log_file\n";
    
} catch (Exception $e) {
    $error_message = "Erreur lors de la réinitialisation hebdomadaire: " . $e->getMessage();
    error_log("[" . date('Y-m-d H:i:s') . "] $error_message");
    
    // Sauvegarder l'erreur dans le fichier log
    $log_file = '../logs/weekly_reset_errors_' . date('Y-m') . '.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $error_content = "[" . date('Y-m-d H:i:s') . "] ERREUR: $error_message\n";
    file_put_contents($log_file, $error_content, FILE_APPEND | LOCK_EX);
    
    echo "Erreur: $error_message\n";
    exit(1);
}
?>