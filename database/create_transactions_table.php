<?php
/**
 * Script de création de la table des transactions financières
 * Gestion des entrées et sorties d'argent du bar
 */

require_once '../config/database.php';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Création de la table des transactions</h2>";
    
    // Créer la table des transactions
    $sql = "
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('income', 'expense') NOT NULL,
            category VARCHAR(100) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            reference_type ENUM('sale', 'cleaning', 'bonus', 'salary', 'purchase', 'other') DEFAULT 'other',
            reference_id INT NULL,
            user_id INT NULL,
            week_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (week_id) REFERENCES weeks(id),
            INDEX idx_type (type),
            INDEX idx_category (category),
            INDEX idx_created_at (created_at),
            INDEX idx_week_id (week_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql);
    echo "<p style='color: green;'>✓ Table 'transactions' créée avec succès.</p>";
    
    // Créer la table des catégories de transactions
    $sql_categories = "
        CREATE TABLE IF NOT EXISTS transaction_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            type ENUM('income', 'expense') NOT NULL,
            description TEXT,
            icon VARCHAR(50) DEFAULT 'fas fa-circle',
            color VARCHAR(20) DEFAULT '#6c757d',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql_categories);
    echo "<p style='color: green;'>✓ Table 'transaction_categories' créée avec succès.</p>";
    
    // Insérer les catégories par défaut
    $default_categories = [
        // Entrées d'argent
        ['Ventes Bar', 'income', 'Chiffre d\'affaires des ventes au bar', 'fas fa-cash-register', '#28a745'],
        ['Services Ménage', 'income', 'Revenus des services de ménage', 'fas fa-broom', '#17a2b8'],
        ['Commissions', 'income', 'Commissions des employés', 'fas fa-percentage', '#ffc107'],
        ['Autres Revenus', 'income', 'Autres sources de revenus', 'fas fa-plus-circle', '#6f42c1'],
        
        // Sorties d'argent
        ['Achats Stock', 'expense', 'Achats de produits et stock', 'fas fa-shopping-cart', '#dc3545'],
        ['Salaires', 'expense', 'Salaires versés aux employés', 'fas fa-money-bill-wave', '#fd7e14'],
        ['Primes', 'expense', 'Primes accordées aux employés', 'fas fa-gift', '#e83e8c'],
        ['Charges', 'expense', 'Charges diverses (électricité, loyer, etc.)', 'fas fa-file-invoice-dollar', '#6c757d'],
        ['Maintenance', 'expense', 'Frais de maintenance et réparations', 'fas fa-tools', '#20c997'],
        ['Autres Dépenses', 'expense', 'Autres dépenses diverses', 'fas fa-minus-circle', '#343a40']
    ];
    
    $stmt = $db->prepare("
        INSERT IGNORE INTO transaction_categories (name, type, description, icon, color) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($default_categories as $category) {
        $stmt->execute($category);
    }
    
    echo "<p style='color: green;'>✓ Catégories par défaut insérées.</p>";
    
    // Créer des transactions d'exemple basées sur les données existantes
    echo "<h3>Création des transactions historiques</h3>";
    
    // Transactions des ventes
    $db->exec("
        INSERT INTO transactions (type, category, amount, description, reference_type, reference_id, user_id, week_id, created_at)
        SELECT 
            'income' as type,
            'Ventes Bar' as category,
            final_amount as amount,
            CONCAT('Vente #', id, ' - ', 
                CASE 
                    WHEN customer_id IS NOT NULL THEN (SELECT CONCAT(first_name, ' ', last_name) FROM customers WHERE id = customer_id)
                    ELSE 'Client anonyme'
                END
            ) as description,
            'sale' as reference_type,
            id as reference_id,
            user_id,
            week_id,
            created_at
        FROM sales
        WHERE id NOT IN (SELECT reference_id FROM transactions WHERE reference_type = 'sale' AND reference_id IS NOT NULL)
    ");
    
    echo "<p style='color: green;'>✓ Transactions des ventes importées.</p>";
    
    // Transactions des services de ménage
    $db->exec("
        INSERT INTO transactions (type, category, amount, description, reference_type, reference_id, user_id, week_id, created_at)
        SELECT 
            'income' as type,
            'Services Ménage' as category,
            total_salary as amount,
            CONCAT('Service ménage - ', cleaning_count, ' ménage(s)') as description,
            'cleaning' as reference_type,
            id as reference_id,
            user_id,
            week_id,
            start_time as created_at
        FROM cleaning_services
        WHERE status = 'completed'
        AND id NOT IN (SELECT reference_id FROM transactions WHERE reference_type = 'cleaning' AND reference_id IS NOT NULL)
    ");
    
    echo "<p style='color: green;'>✓ Transactions des services de ménage importées.</p>";
    
    // Transactions des primes
    $db->exec("
        INSERT INTO transactions (type, category, amount, description, reference_type, reference_id, user_id, created_at)
        SELECT 
            'expense' as type,
            'Primes' as category,
            amount,
            CONCAT('Prime: ', reason) as description,
            'bonus' as reference_type,
            id as reference_id,
            user_id,
            created_at
        FROM bonuses
        WHERE id NOT IN (SELECT reference_id FROM transactions WHERE reference_type = 'bonus' AND reference_id IS NOT NULL)
    ");
    
    echo "<p style='color: green;'>✓ Transactions des primes importées.</p>";
    
    echo "<h3>Résumé</h3>";
    
    // Afficher les statistiques
    $stats = $db->query("
        SELECT 
            type,
            COUNT(*) as count,
            SUM(amount) as total
        FROM transactions 
        GROUP BY type
    ")->fetchAll();
    
    foreach ($stats as $stat) {
        $type_label = $stat['type'] === 'income' ? 'Entrées' : 'Sorties';
        echo "<p><strong>{$type_label}:</strong> {$stat['count']} transactions, Total: " . number_format($stat['total'], 2) . "$</p>";
    }
    
    echo "<p style='color: green; font-weight: bold;'>✅ Système de transactions créé avec succès!</p>";
    echo "<p><a href='../panel/transactions.php'>→ Accéder à la gestion des transactions</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur: " . $e->getMessage() . "</p>";
}
?>