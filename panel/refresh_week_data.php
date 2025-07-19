<?php
/**
 * Script d'actualisation des données des semaines
 * Met à jour les statistiques de toutes les semaines avec les données réelles
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../includes/auth.php';
require_once '../includes/week_functions.php';
require_once '../includes/tax_functions_integrated.php';
require_once '../config/database.php';

$db = getDB();
$auth = new Auth($db);

// Vérifier que l'utilisateur est connecté et a les permissions
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if (!$auth->hasPermission('Patron')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
    exit;
}

header('Content-Type: application/json');

// Ajouter des logs de débogage
error_log("[REFRESH] Script appelé - Méthode: " . $_SERVER['REQUEST_METHOD']);
error_log("[REFRESH] Utilisateur connecté: " . ($auth->isLoggedIn() ? 'Oui' : 'Non'));
if ($auth->isLoggedIn()) {
    $currentUser = $auth->getCurrentUser();
    error_log("[REFRESH] Utilisateur: " . $currentUser['first_name'] . ' ' . $currentUser['last_name']);
}

// Accepter les requêtes GET et POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $updatedWeeks = 0;
    $errors = [];
    
    // Récupérer toutes les semaines
    $stmt = $db->query("SELECT id, week_number, is_finalized FROM weeks ORDER BY week_number");
    $weeks = $stmt->fetchAll();
    
    foreach ($weeks as $week) {
        try {
            // Calculer les statistiques réelles
            $realStats = calculateWeekStats($week['id']);
            
            // Récupérer les données actuellement stockées
            $stmt = $db->prepare("SELECT total_revenue, total_sales_count, total_cleaning_revenue, total_cleaning_count FROM weeks WHERE id = ?");
            $stmt->execute([$week['id']]);
            $currentData = $stmt->fetch();
            
            // Vérifier si une mise à jour est nécessaire
            $needsUpdate = (
                abs($currentData['total_revenue'] - $realStats['total_revenue']) > 0.01 ||
                $currentData['total_sales_count'] != $realStats['total_sales_count'] ||
                abs($currentData['total_cleaning_revenue'] - $realStats['total_cleaning_revenue']) > 0.01 ||
                $currentData['total_cleaning_count'] != $realStats['total_cleaning_count']
            );
            
            if ($needsUpdate) {
                // Mettre à jour les données de la semaine
                $stmt = $db->prepare("
                    UPDATE weeks SET 
                        total_revenue = ?,
                        total_sales_count = ?,
                        total_cleaning_revenue = ?,
                        total_cleaning_count = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $realStats['total_revenue'],
                    $realStats['total_sales_count'],
                    $realStats['total_cleaning_revenue'],
                    $realStats['total_cleaning_count'],
                    $week['id']
                ]);
                
                // Recalculer les impôts si la semaine est finalisée
                if ($week['is_finalized']) {
                    $taxResult = calculateAndUpdateWeekTax($week['id']);
                    if (!$taxResult['success']) {
                        $errors[] = "Erreur calcul impôts semaine {$week['week_number']}: {$taxResult['message']}";
                    }
                }
                
                $updatedWeeks++;
            }
            
        } catch (Exception $e) {
            $errors[] = "Erreur semaine {$week['week_number']}: " . $e->getMessage();
        }
    }
    
    $response = [
        'success' => true,
        'message' => "Actualisation terminée. {$updatedWeeks} semaine(s) mise(s) à jour.",
        'updated_weeks' => $updatedWeeks,
        'total_weeks' => count($weeks),
        'errors' => $errors
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'actualisation: ' . $e->getMessage()
    ]);
}
?>