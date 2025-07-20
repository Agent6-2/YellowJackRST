<?php
/**
 * Script pour vérifier les tables de la base de données
 */

try {
    $db = new PDO(
        'mysql:host=185.207.226.14;port=3306;dbname=lnprqx_yellowja_db;charset=utf8mb4',
        'lnprqx_yellowja_db',
        'fE%M05Z_z9N1-*uq',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "Connexion à la base de données réussie.\n\n";
    
    // Lister toutes les tables
    $stmt = $db->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables disponibles dans la base de données:\n";
    echo "==========================================\n";
    
    foreach($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\nNombre total de tables: " . count($tables) . "\n";
    
    // Vérifier spécifiquement les tables mentionnées dans l'erreur
    $required_tables = ['menages', 'sales', 'users', 'bonuses'];
    
    echo "\nVérification des tables requises:\n";
    echo "=================================\n";
    
    foreach($required_tables as $table) {
        if (in_array($table, $tables)) {
            echo "✓ $table - EXISTE\n";
        } else {
            echo "✗ $table - MANQUANTE\n";
        }
    }
    
} catch(Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . "\n";
    echo "Ligne: " . $e->getLine() . "\n";
}
?>