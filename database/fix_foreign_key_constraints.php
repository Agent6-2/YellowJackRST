<?php
/**
 * Script de correction des contraintes de clé étrangère
 * Résout l'erreur #1451 pour permettre la suppression/modification des clients
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Début de la correction des contraintes de clé étrangère...\n";
    
    // Vérifier l'existence de la contrainte actuelle
    $stmt = $db->query("
        SELECT 
            CONSTRAINT_NAME,
            DELETE_RULE,
            UPDATE_RULE
        FROM 
            INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
        WHERE 
            CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'sales'
            AND REFERENCED_TABLE_NAME = 'customers'
    ");
    
    $constraint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($constraint) {
        echo "Contrainte actuelle trouvée: {$constraint['CONSTRAINT_NAME']}\n";
        echo "DELETE_RULE: {$constraint['DELETE_RULE']}\n";
        echo "UPDATE_RULE: {$constraint['UPDATE_RULE']}\n\n";
        
        // Si la contrainte n'a pas les bonnes règles, la corriger
        if ($constraint['DELETE_RULE'] !== 'SET NULL' || $constraint['UPDATE_RULE'] !== 'CASCADE') {
            echo "Suppression de l'ancienne contrainte...\n";
            $db->exec("ALTER TABLE sales DROP FOREIGN KEY {$constraint['CONSTRAINT_NAME']}");
            
            echo "Création de la nouvelle contrainte avec ON DELETE SET NULL et ON UPDATE CASCADE...\n";
            $db->exec("
                ALTER TABLE sales 
                ADD CONSTRAINT sales_customer_fk 
                FOREIGN KEY (customer_id) REFERENCES customers(id) 
                ON DELETE SET NULL 
                ON UPDATE CASCADE
            ");
            
            echo "✅ Contrainte corrigée avec succès !\n";
        } else {
            echo "✅ La contrainte a déjà les bonnes règles.\n";
        }
    } else {
        echo "Aucune contrainte trouvée, création d'une nouvelle...\n";
        $db->exec("
            ALTER TABLE sales 
            ADD CONSTRAINT sales_customer_fk 
            FOREIGN KEY (customer_id) REFERENCES customers(id) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE
        ");
        echo "✅ Nouvelle contrainte créée avec succès !\n";
    }
    
    // Vérifier le résultat
    echo "\nVérification de la nouvelle contrainte...\n";
    $stmt = $db->query("
        SELECT 
            CONSTRAINT_NAME,
            DELETE_RULE,
            UPDATE_RULE
        FROM 
            INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
        WHERE 
            CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'sales'
            AND REFERENCED_TABLE_NAME = 'customers'
    ");
    
    $newConstraint = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($newConstraint) {
        echo "Nouvelle contrainte: {$newConstraint['CONSTRAINT_NAME']}\n";
        echo "DELETE_RULE: {$newConstraint['DELETE_RULE']}\n";
        echo "UPDATE_RULE: {$newConstraint['UPDATE_RULE']}\n";
    }
    
    echo "\n🎉 Migration terminée avec succès !\n";
    echo "\nVous pouvez maintenant supprimer ou modifier des clients.\n";
    echo "Les ventes associées auront leur customer_id mis à NULL au lieu d'empêcher la suppression.\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la migration: " . $e->getMessage() . "\n";
    echo "\nDétails de l'erreur:\n";
    echo $e->getTraceAsString() . "\n";
}