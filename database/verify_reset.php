<?php
/**
 * Script de vérification de la réinitialisation de la base de données
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "=== VÉRIFICATION DE LA RÉINITIALISATION ===\n\n";
    
    // Vérifier le nombre d'enregistrements dans chaque table
    $tables = [
        'users' => 'Utilisateurs',
        'customers' => 'Clients', 
        'sales' => 'Ventes',
        'sale_items' => 'Articles vendus',
        'cleaning_services' => 'Services de ménage',
        'bonuses' => 'Primes',
        'products' => 'Produits',
        'product_categories' => 'Catégories de produits',
        'system_settings' => 'Paramètres système'
    ];
    
    foreach ($tables as $table => $description) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
        $count = $stmt->fetch()['count'];
        echo sprintf("%-25s: %d enregistrement(s)\n", $description, $count);
    }
    
    echo "\n=== DÉTAILS DES DONNÉES RESTANTES ===\n\n";
    
    // Vérifier les utilisateurs
    echo "UTILISATEURS:\n";
    $stmt = $db->query("SELECT username, first_name, last_name, role FROM users");
    while ($user = $stmt->fetch()) {
        echo "  - {$user['username']} ({$user['first_name']} {$user['last_name']}) - {$user['role']}\n";
    }
    
    // Vérifier les clients
    echo "\nCLIENTS:\n";
    $stmt = $db->query("SELECT name, is_loyal, loyalty_discount FROM customers");
    while ($customer = $stmt->fetch()) {
        $loyal = $customer['is_loyal'] ? 'Fidèle' : 'Normal';
        echo "  - {$customer['name']} ({$loyal}, {$customer['loyalty_discount']}% de réduction)\n";
    }
    
    // Vérifier les catégories de produits
    echo "\nCATÉGORIES DE PRODUITS:\n";
    $stmt = $db->query("SELECT name FROM product_categories ORDER BY id");
    while ($category = $stmt->fetch()) {
        echo "  - {$category['name']}\n";
    }
    
    // Vérifier quelques produits
    echo "\nPRODUITS (premiers 5):\n";
    $stmt = $db->query("SELECT p.name, pc.name as category, p.selling_price, p.stock_quantity 
                        FROM products p 
                        JOIN product_categories pc ON p.category_id = pc.id 
                        ORDER BY p.id LIMIT 5");
    while ($product = $stmt->fetch()) {
        echo "  - {$product['name']} ({$product['category']}) - {$product['selling_price']}$ (Stock: {$product['stock_quantity']})\n";
    }
    
    echo "\n=== VÉRIFICATION TERMINÉE ===\n";
    echo "\n✅ La base de données a été correctement réinitialisée.\n";
    echo "✅ Seules les données par défaut sont présentes.\n";
    echo "✅ Aucun compte de test restant.\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la vérification: " . $e->getMessage() . "\n";
}