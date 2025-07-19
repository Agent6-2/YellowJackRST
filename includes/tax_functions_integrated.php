<?php
/**
 * Fonctions d'impôts intégrées au système de gestion des semaines
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';
require_once 'week_functions.php';

/**
 * Calcule les impôts selon le système de tranches
 * 
 * @param float $revenue Revenus totaux
 * @return array Résultat du calcul avec détails
 */
function calculateTax($revenue) {
    $db = getDB();
    
    // Récupérer les tranches d'impôts
    $stmt = $db->prepare("SELECT * FROM tax_brackets ORDER BY min_revenue ASC");
    $stmt->execute();
    $brackets = $stmt->fetchAll();
    
    if (empty($brackets)) {
        return [
            'total_tax' => 0,
            'effective_rate' => 0,
            'breakdown' => []
        ];
    }
    
    // Système de taux fixe par tranche (non progressif)
    // Trouver la tranche correspondante au revenu total
    $applicableBracket = null;
    foreach ($brackets as $bracket) {
        $minRevenue = $bracket['min_revenue'];
        $maxRevenue = $bracket['max_revenue'];
        
        if ($revenue >= $minRevenue && ($maxRevenue === null || $revenue <= $maxRevenue)) {
            $applicableBracket = $bracket;
            break;
        }
    }
    
    if (!$applicableBracket) {
        return [
            'total_tax' => 0,
            'effective_rate' => 0,
            'breakdown' => []
        ];
    }
    
    // Calculer l'impôt avec le taux fixe de la tranche
    $taxRate = $applicableBracket['tax_rate'];
    $totalTax = $revenue * ($taxRate / 100);
    $effectiveRate = $taxRate;
    
    $breakdown = [[
        'min_revenue' => $applicableBracket['min_revenue'],
        'max_revenue' => $applicableBracket['max_revenue'],
        'tax_rate' => $taxRate,
        'taxable_amount' => $revenue,
        'tax_amount' => $totalTax
    ]];
    
    return [
        'total_tax' => $totalTax,
        'effective_rate' => $effectiveRate,
        'breakdown' => $breakdown
    ];
}

/**
 * Calcule et met à jour les impôts pour une semaine donnée
 * 
 * @param int $weekId ID de la semaine
 * @return array Résultat de l'opération
 */
function calculateAndUpdateWeekTax($weekId) {
    $db = getDB();
    
    try {
        // Récupérer les revenus de la semaine
        $weekStats = calculateWeekStats($weekId);
        $totalRevenue = $weekStats['total_revenue'] + $weekStats['total_cleaning_revenue'];
        
        // Calculer les impôts
        $taxResult = calculateTax($totalRevenue);
        
        // Mettre à jour la semaine avec les données d'impôts
        $stmt = $db->prepare("
            UPDATE weeks 
            SET tax_amount = ?, 
                effective_tax_rate = ?, 
                tax_breakdown = ?, 
                tax_calculated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $taxResult['total_tax'],
            $taxResult['effective_rate'],
            json_encode($taxResult['breakdown']),
            $weekId
        ]);
        
        return [
            'success' => true,
            'message' => 'Impôts calculés avec succès',
            'tax_data' => $taxResult
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erreur lors du calcul des impôts: ' . $e->getMessage()
        ];
    }
}

/**
 * Finalise les impôts d'une semaine
 * 
 * @param int $weekId ID de la semaine
 * @return array Résultat de l'opération
 */
function finalizeWeekTax($weekId) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("UPDATE weeks SET tax_finalized = 1 WHERE id = ?");
        $stmt->execute([$weekId]);
        
        return [
            'success' => true,
            'message' => 'Impôts finalisés avec succès'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erreur lors de la finalisation des impôts: ' . $e->getMessage()
        ];
    }
}

/**
 * Finalise une semaine et crée la suivante avec calcul automatique des impôts
 * 
 * @param int $userId ID de l'utilisateur qui finalise
 * @return array Résultat de l'opération
 */
function finalizeWeekAndCreateNewWithTax($userId) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Récupérer la semaine active
        $activeWeek = getActiveWeekNew();
        if (!$activeWeek) {
            throw new Exception('Aucune semaine active trouvée');
        }
        
        // Calculer les impôts avant la finalisation
        $taxResult = calculateAndUpdateWeekTax($activeWeek['id']);
        if (!$taxResult['success']) {
            throw new Exception($taxResult['message']);
        }
        
        // Finaliser les impôts
        $finalizeTaxResult = finalizeWeekTax($activeWeek['id']);
        if (!$finalizeTaxResult['success']) {
            throw new Exception($finalizeTaxResult['message']);
        }
        
        // Finaliser la semaine
        $stmt = $db->prepare("UPDATE weeks SET is_active = 0, finalized_at = NOW(), finalized_by = ? WHERE id = ?");
        $stmt->execute([$userId, $activeWeek['id']]);
        
        // Créer la nouvelle semaine
        $newWeekStart = date('Y-m-d', strtotime($activeWeek['week_end'] . ' +1 day'));
        $newWeekEnd = date('Y-m-d', strtotime($newWeekStart . ' +6 days'));
        $newWeekNumber = $activeWeek['week_number'] + 1;
        
        $stmt = $db->prepare("
            INSERT INTO weeks (week_number, week_start, week_end, is_active, created_by, created_at) 
            VALUES (?, ?, ?, 1, ?, NOW())
        ");
        $stmt->execute([$newWeekNumber, $newWeekStart, $newWeekEnd, $userId]);
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => "Semaine {$activeWeek['week_number']} finalisée avec succès. Nouvelle semaine {$newWeekNumber} créée."
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        return [
            'success' => false,
            'message' => 'Erreur lors de la finalisation: ' . $e->getMessage()
        ];
    }
}
?>