<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "=== Vérification des entreprises ===\n\n";
    
    // Toutes les entreprises
    echo "Toutes les entreprises dans la base :\n";
    $stmt = $db->query("SELECT id, name, is_active, business_discount FROM companies ORDER BY id DESC");
    $all_companies = $stmt->fetchAll();
    
    if (empty($all_companies)) {
        echo "Aucune entreprise trouvée dans la base de données.\n";
    } else {
        foreach ($all_companies as $company) {
            $status = $company['is_active'] ? 'ACTIVE' : 'INACTIVE';
            echo "- ID: {$company['id']} | Nom: {$company['name']} | Statut: {$status} | Réduction: {$company['business_discount']}%\n";
        }
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // Entreprises actives (celles qui apparaissent dans le dropdown)
    echo "Entreprises ACTIVES (visibles dans le dropdown) :\n";
    $stmt = $db->query("SELECT id, name, business_discount FROM companies WHERE is_active = 1 ORDER BY name");
    $active_companies = $stmt->fetchAll();
    
    if (empty($active_companies)) {
        echo "Aucune entreprise active trouvée.\n";
        echo "C'est pourquoi le dropdown affiche 'Aucune entreprise'.\n";
    } else {
        foreach ($active_companies as $company) {
            echo "- ID: {$company['id']} | Nom: {$company['name']} | Réduction: {$company['business_discount']}%\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>