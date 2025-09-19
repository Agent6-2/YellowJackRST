<?php
/**
 * Script pour ajouter les colonnes Discord à la table users
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "🗄️ Ajout des colonnes Discord à la table users...\n\n";
    
    // Vérifier les colonnes existantes
    $stmt = $db->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
    ");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Colonnes existantes dans la table users:\n";
    foreach ($columns as $column) {
        echo "   - $column\n";
    }
    echo "\n";
    
    // Ajouter la colonne discord_present si elle n'existe pas
    if (!in_array('discord_present', $columns)) {
        echo "➕ Ajout de la colonne 'discord_present'...\n";
        $db->exec("
            ALTER TABLE users 
            ADD COLUMN discord_present BOOLEAN DEFAULT FALSE 
            COMMENT 'Indique si l\'employé est présent sur le serveur Discord'
        ");
        echo "✅ Colonne 'discord_present' ajoutée avec succès.\n";
    } else {
        echo "ℹ️ La colonne 'discord_present' existe déjà.\n";
    }
    
    // Ajouter la colonne discord_last_checked si elle n'existe pas
    if (!in_array('discord_last_checked', $columns)) {
        echo "➕ Ajout de la colonne 'discord_last_checked'...\n";
        $db->exec("
            ALTER TABLE users 
            ADD COLUMN discord_last_checked TIMESTAMP NULL 
            COMMENT 'Dernière vérification de présence Discord'
        ");
        echo "✅ Colonne 'discord_last_checked' ajoutée avec succès.\n";
    } else {
        echo "ℹ️ La colonne 'discord_last_checked' existe déjà.\n";
    }
    
    // Ajouter un index sur discord_id si nécessaire
    echo "📊 Vérification des index...\n";
    $stmt = $db->prepare("
        SELECT INDEX_NAME 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'users' 
        AND COLUMN_NAME = 'discord_id'
    ");
    $stmt->execute();
    $indexes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($indexes)) {
        echo "➕ Ajout d'un index sur 'discord_id'...\n";
        $db->exec("
            ALTER TABLE users 
            ADD INDEX idx_discord_id (discord_id)
        ");
        echo "✅ Index sur 'discord_id' ajouté avec succès.\n";
    } else {
        echo "ℹ️ Un index sur 'discord_id' existe déjà.\n";
    }
    
    // Vérifier la structure finale
    echo "\n📋 Structure finale de la table users:\n";
    $stmt = $db->prepare("DESCRIBE users");
    $stmt->execute();
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($structure as $column) {
        $nullable = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $column['Default'] !== null ? "DEFAULT '{$column['Default']}'" : '';
        echo "   - {$column['Field']}: {$column['Type']} $nullable $default\n";
    }
    
    echo "\n🎉 Configuration de la base de données terminée avec succès!\n";
    echo "📊 Le bot Discord peut maintenant suivre la présence des employés.\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la configuration : " . $e->getMessage() . "\n";
    exit(1);
}
?>