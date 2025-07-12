<?php
/**
 * Script pour nettoyer les entreprises génériques
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "=== Nettoyage des entreprises génériques ===\n";
    
    // 1. Afficher les entreprises actuelles
    echo "\nEntreprises actuelles dans la base de données :\n";
    $stmt = $db->query("SELECT id, name, business_discount FROM companies ORDER BY id");
    $companies = $stmt->fetchAll();
    
    if (empty($companies)) {
        echo "Aucune entreprise trouvée.\n";
    } else {
        foreach ($companies as $company) {
            echo "- ID: {$company['id']} | Nom: {$company['name']} | Réduction: {$company['business_discount']}%\n";
        }
    }
    
    // 2. Supprimer toutes les entreprises existantes (génériques)
    echo "\nSuppression de toutes les entreprises existantes...\n";
    
    // D'abord, mettre à NULL les références dans la table customers
    $updateCustomers = "UPDATE customers SET company_id = NULL WHERE company_id IS NOT NULL";
    $db->exec($updateCustomers);
    echo "✓ Références clients mises à NULL\n";
    
    // Ensuite, supprimer toutes les entreprises
    $deleteCompanies = "DELETE FROM companies";
    $db->exec($deleteCompanies);
    echo "✓ Toutes les entreprises supprimées\n";
    
    // 3. Réinitialiser l'auto-increment
    $resetAutoIncrement = "ALTER TABLE companies AUTO_INCREMENT = 1";
    $db->exec($resetAutoIncrement);
    echo "✓ Auto-increment réinitialisé\n";
    
    echo "\n=== Nettoyage terminé avec succès ===\n";
    echo "Vous pouvez maintenant créer de vraies entreprises via l'interface d'administration.\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>