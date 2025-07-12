<?php
/**
 * Création de la table pour le suivi des performances hebdomadaires
 * Système de primes automatique de vendredi à vendredi
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../config/database.php';

try {
    $db = getDB();
    
    // Créer la table weekly_performance pour stocker les performances hebdomadaires
    $sql = "
        CREATE TABLE IF NOT EXISTS weekly_performance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            week_start DATE NOT NULL COMMENT 'Vendredi de début de semaine',
            week_end DATE NOT NULL COMMENT 'Jeudi de fin de semaine',
            
            -- Statistiques ménage
            total_menages INT DEFAULT 0,
            total_salary_menage DECIMAL(10,2) DEFAULT 0.00,
            total_hours_menage DECIMAL(8,2) DEFAULT 0.00,
            
            -- Statistiques ventes
            total_ventes INT DEFAULT 0,
            total_revenue DECIMAL(10,2) DEFAULT 0.00,
            total_commissions DECIMAL(10,2) DEFAULT 0.00,
            
            -- Calculs de primes
            prime_menage DECIMAL(10,2) DEFAULT 0.00,
            prime_ventes DECIMAL(10,2) DEFAULT 0.00,
            prime_totale DECIMAL(10,2) DEFAULT 0.00,
            
            -- Métadonnées
            calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_finalized BOOLEAN DEFAULT FALSE COMMENT 'Semaine finalisée (après vendredi)',
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_week (user_id, week_start),
            INDEX idx_week_start (week_start),
            INDEX idx_user_week (user_id, week_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql);
    echo "✅ Table 'weekly_performance' créée avec succès.\n";
    
    // Créer la table weekly_performance_config pour les paramètres de calcul des primes
    $sql_config = "
        CREATE TABLE IF NOT EXISTS weekly_performance_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(50) NOT NULL UNIQUE,
            config_value DECIMAL(10,4) NOT NULL,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql_config);
    echo "✅ Table 'weekly_performance_config' créée avec succès.\n";
    
    // Insérer les paramètres par défaut pour le calcul des primes
    $default_configs = [
        ['prime_menage_per_unit', 2.00, 'Prime par ménage effectué (en €)'],
        ['prime_menage_bonus_threshold', 20, 'Seuil de ménages pour bonus (nombre)'],
        ['prime_menage_bonus_rate', 1.50, 'Bonus supplémentaire par ménage au-dessus du seuil (en €)'],
        ['prime_vente_percentage', 0.20, 'Pourcentage de prime sur le CA ventes (20%)'],
        ['prime_vente_bonus_threshold', 500.00, 'Seuil de CA pour bonus ventes (en €)'],
        ['prime_vente_bonus_rate', 0.02, 'Bonus supplémentaire au-dessus du seuil CA (2%)']
    ];
    
    $stmt = $db->prepare("
        INSERT IGNORE INTO weekly_performance_config (config_key, config_value, description) 
        VALUES (?, ?, ?)
    ");
    
    foreach ($default_configs as $config) {
        $stmt->execute($config);
    }
    
    echo "✅ Configuration par défaut des primes insérée.\n";
    echo "\n📊 Système de performances hebdomadaires configuré !\n";
    echo "\n🔧 Paramètres de primes par défaut :\n";
    echo "   - Prime ménage : 2€ par ménage\n";
    echo "   - Bonus ménage : +1.50€ par ménage au-dessus de 20\n";
    echo "   - Prime ventes : 5% du CA\n";
    echo "   - Bonus ventes : +2% au-dessus de 500€ de CA\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la création des tables : " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Erreur générale : " . $e->getMessage() . "\n";
    exit(1);
}
?>