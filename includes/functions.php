<?php
/**
 * Fonctions utilitaires pour le système YellowJack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Obtenir le vendredi de la semaine pour une date donnée (pour compatibilité)
 * @param string $date Date au format Y-m-d
 * @return string Date du vendredi au format Y-m-d
 */
function getFridayOfWeek($date) {
    $timestamp = strtotime($date);
    $dayOfWeek = date('N', $timestamp); // 1 = Lundi, 7 = Dimanche
    
    if ($dayOfWeek == 5) {
        // C'est déjà vendredi
        return date('Y-m-d', $timestamp);
    } elseif ($dayOfWeek < 5) {
        // Avant vendredi, aller au vendredi de cette semaine
        $daysToAdd = 5 - $dayOfWeek;
        return date('Y-m-d', strtotime("+$daysToAdd days", $timestamp));
    } else {
        // Weekend, aller au vendredi suivant
        $daysToAdd = 12 - $dayOfWeek; // 7 jours + (5 - dayOfWeek)
        return date('Y-m-d', strtotime("+$daysToAdd days", $timestamp));
    }
}

/**
 * Obtenir le vendredi suivant après un vendredi donné (pour compatibilité)
 * @param string $friday Date du vendredi au format Y-m-d
 * @return string Date du vendredi suivant au format Y-m-d
 */
function getFridayAfterFriday($friday) {
    return date('Y-m-d', strtotime('+7 days', strtotime($friday)));
}

/**
 * Obtenir la semaine active actuelle (du 27/03/2025 au 04/07/2025 par défaut)
 * @return array Tableau avec week_start et week_end
 */
function getCurrentWeekPeriod() {
    // Période fixe actuelle
    return [
        'week_start' => '2025-03-27',
        'week_end' => '2025-07-04'
    ];
}

/**
 * Obtenir la prochaine période de semaine après finalisation
 * @param string $current_end Date de fin de la période actuelle
 * @return array Tableau avec week_start et week_end
 */
function getNextWeekPeriod($current_end) {
    $start_date = new DateTime($current_end);
    $start_date->add(new DateInterval('P1D')); // Jour suivant
    
    $end_date = clone $start_date;
    $end_date->add(new DateInterval('P6D')); // Période de 7 jours (6 jours + le jour de début)
    
    return [
        'week_start' => $start_date->format('Y-m-d'),
        'week_end' => $end_date->format('Y-m-d')
    ];
}

/**
 * Obtenir la semaine active (non finalisée)
 * Ne crée plus automatiquement de nouvelles semaines - gestion manuelle uniquement
 * @return array|null Données de la semaine active ou null si aucune semaine active
 */
function getActiveWeek() {
    try {
        $db = getDB();
        $today = date('Y-m-d');
        
        // Chercher une semaine non finalisée qui couvre la date actuelle
        $stmt = $db->prepare("SELECT * FROM weekly_taxes WHERE is_finalized = FALSE AND ? >= week_start AND ? <= week_end ORDER BY week_start DESC LIMIT 1");
        $stmt->execute([$today, $today]);
        $activeWeek = $stmt->fetch();
        
        if ($activeWeek) {
            return $activeWeek;
        }
        
        // Si aucune semaine active ne couvre aujourd'hui, retourner la dernière semaine non finalisée
        $stmt = $db->prepare("SELECT * FROM weekly_taxes WHERE is_finalized = FALSE ORDER BY week_start DESC LIMIT 1");
        $stmt->execute();
        $lastActiveWeek = $stmt->fetch();
        
        if ($lastActiveWeek) {
            return $lastActiveWeek;
        }
        
        // Aucune semaine active trouvée - retourner null pour forcer la création manuelle
        return null;
        
    } catch (Exception $e) {
        error_log("Erreur getActiveWeek: " . $e->getMessage());
        return null;
    }
}

/**
 * Vérifie si une date est dans la période de la semaine active
 */
function isDateInActiveWeek($date) {
    $activeWeek = getActiveWeek();
    $checkDate = date('Y-m-d', strtotime($date));
    
    return $checkDate >= $activeWeek['week_start'] && $checkDate <= $activeWeek['week_end'];
}
?>