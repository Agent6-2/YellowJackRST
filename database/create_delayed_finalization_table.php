<?php
// Créer la table pour gérer les finalisations différées

require_once '../includes/auth.php';

try {
    $db = new PDO("sqlite:../database/yellowjack.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer la table delayed_finalizations
    $sql = "
        CREATE TABLE IF NOT EXISTS delayed_finalizations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            week_start TEXT NOT NULL,
            week_end TEXT NOT NULL,
            finalization_time TEXT NOT NULL,
            execution_time TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            executed_at TEXT NULL
        )
    ";
    
    $db->exec($sql);
    
    echo "Table delayed_finalizations créée avec succès.\n";
    
} catch (Exception $e) {
    echo "Erreur lors de la création de la table : " . $e->getMessage() . "\n";
}
?>