<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    echo "Création de la table financial_transactions...\n";
    
    // Créer la table des transactions financières
    $sql = "
        CREATE TABLE IF NOT EXISTS financial_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('income', 'expense') NOT NULL,
            category VARCHAR(100) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description TEXT NOT NULL,
            transaction_date DATE NOT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id),
            INDEX idx_type (type),
            INDEX idx_category (category),
            INDEX idx_transaction_date (transaction_date),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql);
    echo "✓ Table financial_transactions créée avec succès.\n";
    
    // Vérifier s'il y a des utilisateurs dans la base
    $user_check = $db->query("SELECT id FROM users LIMIT 1")->fetch();
    
    if ($user_check) {
        $first_user_id = $user_check['id'];
        
        echo "\nInsertion de données d'exemple...\n";
        
        $sample_data = [
            [
                'type' => 'expense',
                'category' => 'Achats Marchandises',
                'amount' => 1500.00,
                'description' => 'Achat de stock de boissons et snacks pour le mois',
                'transaction_date' => date('Y-m-d', strtotime('-5 days')),
                'created_by' => $first_user_id
            ],
            [
                'type' => 'expense',
                'category' => 'Électricité',
                'amount' => 350.00,
                'description' => 'Facture d\'électricité du mois',
                'transaction_date' => date('Y-m-d', strtotime('-3 days')),
                'created_by' => $first_user_id
            ],
            [
                'type' => 'income',
                'category' => 'Autres Revenus',
                'amount' => 800.00,
                'description' => 'Location de l\'espace pour événement privé',
                'transaction_date' => date('Y-m-d', strtotime('-2 days')),
                'created_by' => $first_user_id
            ],
            [
                'type' => 'expense',
                'category' => 'Maintenance',
                'amount' => 200.00,
                'description' => 'Réparation du système de climatisation',
                'transaction_date' => date('Y-m-d', strtotime('-1 day')),
                'created_by' => $first_user_id
            ],
            [
                'type' => 'income',
                'category' => 'Pourboires',
                'amount' => 150.00,
                'description' => 'Pourboires collectifs de la semaine',
                'transaction_date' => date('Y-m-d'),
                'created_by' => $first_user_id
            ]
        ];
        
        $stmt = $db->prepare("
            INSERT INTO financial_transactions (type, category, amount, description, transaction_date, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($sample_data as $transaction) {
            $stmt->execute([
                $transaction['type'],
                $transaction['category'],
                $transaction['amount'],
                $transaction['description'],
                $transaction['transaction_date'],
                $transaction['created_by']
            ]);
        }
        
        echo "✓ " . count($sample_data) . " transactions d'exemple ajoutées.\n";
    } else {
        echo "⚠ Aucun utilisateur trouvé, pas de données d'exemple ajoutées.\n";
    }
    
    echo "\n=== MIGRATION TERMINÉE AVEC SUCCÈS ===\n";
    echo "La table financial_transactions a été créée et des données d'exemple ont été ajoutées.\n";
    echo "Vous pouvez maintenant accéder au module de gestion financière.\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la création de la table : " . $e->getMessage() . "\n";
    exit(1);
}
?>