<?php
// Créer la table pour gérer les finalisations différées

require_once '../config/database.php';

try {
    $db = getDB();
    
    // Créer la table delayed_finalizations
    $sql = "
        CREATE TABLE IF NOT EXISTS delayed_finalizations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            week_start DATETIME NOT NULL,
            week_end DATETIME NOT NULL,
            finalization_time DATETIME NOT NULL,
            execution_time DATETIME NOT NULL,
            status ENUM('pending', 'executed', 'failed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            executed_at TIMESTAMP NULL,
            INDEX idx_status (status),
            INDEX idx_execution_time (execution_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql);
    
    echo "Table delayed_finalizations créée avec succès dans MySQL.\n";
    
} catch (Exception $e) {
    echo "Erreur lors de la création de la table : " . $e->getMessage() . "\n";
}
?>