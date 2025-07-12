<?php
/**
 * Script de test pour vérifier que la correction des contraintes fonctionne
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "=== Test de la correction des contraintes de clé étrangère ===\n\n";
    
    // 1. Vérifier l'état actuel de la contrainte
    echo "1. Vérification de la contrainte actuelle...\n";
    $stmt = $db->query("
        SELECT 
            CONSTRAINT_NAME,
            DELETE_RULE,
            UPDATE_RULE
        FROM 
            INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
        WHERE 
            CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'sales'
            AND REFERENCED_TABLE_NAME = 'customers'
    ");
    
    $constraint = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($constraint) {
        echo "✅ Contrainte trouvée: {$constraint['CONSTRAINT_NAME']}\n";
        echo "   DELETE_RULE: {$constraint['DELETE_RULE']}\n";
        echo "   UPDATE_RULE: {$constraint['UPDATE_RULE']}\n";
        
        if ($constraint['DELETE_RULE'] === 'SET NULL') {
            echo "✅ La contrainte permet maintenant la suppression des clients !\n";
        } else {
            echo "❌ La contrainte n'a pas été corrigée correctement.\n";
        }
    } else {
        echo "❌ Aucune contrainte trouvée.\n";
    }
    
    echo "\n2. Test de création d'un client temporaire...\n";
    
    // 2. Créer un client de test
    $stmt = $db->prepare("INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)");
    $stmt->execute(['Test Client Temp', '0000000000', 'test@temp.com']);
    $testCustomerId = $db->lastInsertId();
    echo "✅ Client de test créé avec l'ID: $testCustomerId\n";
    
    // 3. Créer une vente associée à ce client
    echo "\n3. Test de création d'une vente associée...\n";
    $stmt = $db->prepare("
        INSERT INTO sales (user_id, customer_id, total_amount, final_amount) 
        VALUES (1, ?, 10.00, 10.00)
    ");
    $stmt->execute([$testCustomerId]);
    $testSaleId = $db->lastInsertId();
    echo "✅ Vente de test créée avec l'ID: $testSaleId\n";
    
    // 4. Vérifier que la vente est bien associée
    $stmt = $db->prepare("SELECT customer_id FROM sales WHERE id = ?");
    $stmt->execute([$testSaleId]);
    $sale = $stmt->fetch();
    echo "   Customer ID dans la vente: {$sale['customer_id']}\n";
    
    // 5. Tenter de supprimer le client
    echo "\n4. Test de suppression du client...\n";
    try {
        $stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$testCustomerId]);
        echo "✅ Client supprimé avec succès !\n";
        
        // 6. Vérifier que customer_id est maintenant NULL dans la vente
        $stmt = $db->prepare("SELECT customer_id FROM sales WHERE id = ?");
        $stmt->execute([$testSaleId]);
        $sale = $stmt->fetch();
        
        if ($sale['customer_id'] === null) {
            echo "✅ Customer ID dans la vente est maintenant NULL: " . var_export($sale['customer_id'], true) . "\n";
            echo "✅ La contrainte fonctionne parfaitement !\n";
        } else {
            echo "❌ Customer ID dans la vente n'est pas NULL: {$sale['customer_id']}\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Erreur lors de la suppression: " . $e->getMessage() . "\n";
        echo "La contrainte n'a probablement pas été corrigée.\n";
    }
    
    // 7. Nettoyer - supprimer la vente de test
    echo "\n5. Nettoyage...\n";
    $stmt = $db->prepare("DELETE FROM sales WHERE id = ?");
    $stmt->execute([$testSaleId]);
    echo "✅ Vente de test supprimée.\n";
    
    echo "\n=== Test terminé ===\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors du test: " . $e->getMessage() . "\n";
    echo "\nDétails de l'erreur:\n";
    echo $e->getTraceAsString() . "\n";
}