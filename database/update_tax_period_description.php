<?php
/**
 * Script pour mettre à jour la description de la période de calcul des impôts
 * Correction: vendredi à vendredi (exclu) = vendredi à jeudi
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Mise à jour de la description de la période de calcul des impôts...\n";
    
    // Mettre à jour ou insérer la configuration de la période
    $stmt = $db->prepare("
        INSERT INTO tax_brackets (min_revenue, max_revenue, tax_rate, description) 
        VALUES (0, 0, 0, 'Période de calcul: du vendredi inclus au vendredi suivant exclu (soit vendredi à jeudi)')
        ON DUPLICATE KEY UPDATE 
        description = VALUES(description)
    ");
    
    // Alternativement, créer une table de configuration si elle n'existe pas
    $db->exec("
        CREATE TABLE IF NOT EXISTS tax_config (
            id INT PRIMARY KEY AUTO_INCREMENT,
            config_key VARCHAR(100) UNIQUE NOT NULL,
            config_value TEXT NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Insérer ou mettre à jour la configuration de la période
    $stmt = $db->prepare("
        INSERT INTO tax_config (config_key, config_value, description) 
        VALUES ('calculation_period', 'friday_to_friday_excluded', 'Période de calcul des impôts: du vendredi inclus au vendredi suivant exclu (soit vendredi à jeudi)')
        ON DUPLICATE KEY UPDATE 
        config_value = VALUES(config_value),
        description = VALUES(description),
        updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute();
    
    echo "✅ Configuration de la période mise à jour avec succès.\n";
    echo "📅 Nouvelle période: Vendredi à Vendredi (exclu) = Vendredi à Jeudi\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la mise à jour: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Mise à jour terminée avec succès!\n";
?>