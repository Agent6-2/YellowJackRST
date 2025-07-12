<?php
/**
 * Script de réinitialisation complète de la base de données
 * Supprime tous les comptes de test et remet la base à zéro
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "=== RÉINITIALISATION COMPLÈTE DE LA BASE DE DONNÉES ===\n\n";
    
    // Désactiver les vérifications de clés étrangères temporairement
    echo "1. Désactivation des contraintes de clés étrangères...\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Supprimer toutes les données des tables (en respectant l'ordre des dépendances)
    echo "2. Suppression de toutes les données...\n";
    
    // Tables dépendantes en premier
    $tables_to_clean = [
        'sale_items',
        'sales', 
        'bonuses',
        'cleaning_services',
        'customers',
        'products',
        'product_categories',
        'users',
        'system_settings'
    ];
    
    foreach ($tables_to_clean as $table) {
        echo "   - Vidage de la table {$table}...\n";
        $db->exec("DELETE FROM {$table}");
        $db->exec("ALTER TABLE {$table} AUTO_INCREMENT = 1");
    }
    
    // Réactiver les vérifications de clés étrangères
    echo "3. Réactivation des contraintes de clés étrangères...\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Réinsérer les données par défaut
    echo "4. Réinsertion des données par défaut...\n";
    
    // Paramètres système
    echo "   - Paramètres système...\n";
    $db->exec("
        INSERT INTO system_settings (setting_key, setting_value, description) VALUES
        ('cleaning_rate', '60', 'Tarif par ménage en dollars'),
        ('commission_rate', '20', 'Pourcentage de commission pour les CDI, Responsable et Patron'),
        ('discord_webhook_url', '', 'URL du webhook Discord pour les tickets'),
        ('bar_name', 'Le Yellowjack', 'Nom du bar'),
        ('bar_address', 'Nord de Los Santos, près de Sandy Shore', 'Adresse du bar'),
        ('bar_phone', '+1-555-YELLOW', 'Téléphone du bar')
    ");
    
    // Catégories de produits
    echo "   - Catégories de produits...\n";
    $db->exec("
        INSERT INTO product_categories (name, description) VALUES
        ('Boissons Alcoolisées', 'Bières, vins, spiritueux'),
        ('Boissons Non-Alcoolisées', 'Sodas, jus, eau'),
        ('Snacks', 'Chips, cacahuètes, etc.'),
        ('Plats', 'Burgers, sandwichs, etc.')
    ");
    
    // Produits par défaut
    echo "   - Produits par défaut...\n";
    $db->exec("
        INSERT INTO products (category_id, name, description, supplier_price, selling_price, stock_quantity) VALUES
        (1, 'Bière Pression', 'Bière locale à la pression', 2.50, 5.00, 100),
        (1, 'Whiskey', 'Whiskey premium', 15.00, 25.00, 20),
        (1, 'Vin Rouge', 'Vin rouge de la région', 8.00, 15.00, 15),
        (2, 'Coca-Cola', 'Soda classique', 1.00, 3.00, 50),
        (2, 'Eau Minérale', 'Eau plate ou gazeuse', 0.50, 2.00, 100),
        (3, 'Cacahuètes', 'Cacahuètes salées', 1.50, 4.00, 30),
        (4, 'Burger Western', 'Burger spécialité maison', 5.00, 12.00, 0)
    ");
    
    // Utilisateur admin par défaut (mot de passe: admin123)
    echo "   - Utilisateur administrateur...\n";
    $db->exec("
        INSERT INTO users (username, email, password_hash, first_name, last_name, role, hire_date) VALUES
        ('admin', 'admin@yellowjack.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Yellowjack', 'Patron', CURDATE())
    ");
    
    // Client anonyme uniquement (pas de clients de test)
    echo "   - Client anonyme...\n";
    $db->exec("
        INSERT INTO customers (name, is_loyal, loyalty_discount) VALUES
        ('Client Anonyme', FALSE, 0)
    ");
    
    echo "\n🎉 RÉINITIALISATION TERMINÉE AVEC SUCCÈS !\n\n";
    echo "Résumé des actions effectuées :\n";
    echo "✅ Toutes les données de test supprimées\n";
    echo "✅ Tous les comptes clients de test supprimés\n";
    echo "✅ Toutes les ventes supprimées\n";
    echo "✅ Tous les services de ménage supprimés\n";
    echo "✅ Toutes les primes supprimées\n";
    echo "✅ Données par défaut réinsérées\n";
    echo "✅ Seul le compte admin et le client anonyme restent\n\n";
    echo "Connexion admin :\n";
    echo "- Username: admin\n";
    echo "- Password: admin123\n\n";
    echo "La base de données est maintenant propre et prête pour la production.\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la réinitialisation: " . $e->getMessage() . "\n";
    echo "\nDétails de l'erreur:\n";
    echo $e->getTraceAsString() . "\n";
    
    // Réactiver les clés étrangères en cas d'erreur
    try {
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Exception $e2) {
        // Ignorer les erreurs de réactivation
    }
}