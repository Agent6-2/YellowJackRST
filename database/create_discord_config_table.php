<?php
/**
 * Script pour créer la table de configuration Discord
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    // Créer la table de configuration Discord
    $sql = "
        CREATE TABLE IF NOT EXISTS discord_config (
            id INT PRIMARY KEY AUTO_INCREMENT,
            webhook_url TEXT,
            notifications_enabled BOOLEAN DEFAULT TRUE,
            notify_sales BOOLEAN DEFAULT TRUE,
            notify_goals BOOLEAN DEFAULT TRUE,
            notify_errors BOOLEAN DEFAULT TRUE,
            notify_weekly_summary BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";
    
    $db->exec($sql);
    
    // Insérer une configuration par défaut si la table est vide
    $stmt = $db->query("SELECT COUNT(*) FROM discord_config");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $stmt = $db->prepare("
            INSERT INTO discord_config 
            (webhook_url, notifications_enabled, notify_sales, notify_goals, notify_errors, notify_weekly_summary) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(['', true, true, true, true, true]);
        echo "✅ Configuration Discord par défaut créée.\n";
    }
    
    echo "✅ Table discord_config créée avec succès.\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la création de la table : " . $e->getMessage() . "\n";
}
?>