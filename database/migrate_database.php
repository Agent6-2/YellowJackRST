<?php
/**
 * Script de migration pour corriger la colonne employee_id vers user_id
 * À exécuter pour mettre à jour la base de données distante
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Script de migration de la base de données ===\n";
echo "Correction: employee_id -> user_id dans la table sales\n\n";

try {
    $db = getDB();
    
    // 1. Vérifier si la colonne employee_id existe
    echo "1. Vérification de l'existence de la colonne employee_id...\n";
    $check_query = "SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'sales' 
                    AND COLUMN_NAME = 'employee_id'";
    
    $stmt = $db->query($check_query);
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        echo "✓ La colonne employee_id n'existe pas. Vérification si user_id existe...\n";
        
        $check_user_id = "SELECT COLUMN_NAME 
                         FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE TABLE_NAME = 'sales' 
                         AND COLUMN_NAME = 'user_id'";
        
        $stmt = $db->query($check_user_id);
        $user_id_exists = $stmt->fetch();
        
        if ($user_id_exists) {
            echo "✓ La colonne user_id existe déjà. Migration non nécessaire.\n";
        } else {
            echo "❌ Ni employee_id ni user_id n'existent. Problème de structure de base de données.\n";
        }
        exit;
    }
    
    echo "✓ Colonne employee_id trouvée. Migration nécessaire.\n\n";
    
    // 2. Commencer la transaction
    $db->beginTransaction();
    
    try {
        // 3. Méthode de migration selon la version de MySQL
        echo "2. Tentative de renommage direct de la colonne...\n";
        
        // Essayer d'abord le renommage direct (MySQL 8.0+)
        try {
            $rename_query = "ALTER TABLE sales RENAME COLUMN employee_id TO user_id";
            $db->exec($rename_query);
            echo "✓ Renommage direct réussi.\n";
        } catch (PDOException $e) {
            echo "⚠ Renommage direct échoué. Utilisation de la méthode alternative...\n";
            
            // Méthode alternative pour les versions plus anciennes
            echo "3. Ajout de la colonne user_id...\n";
            $db->exec("ALTER TABLE sales ADD COLUMN user_id INT");
            
            echo "4. Copie des données de employee_id vers user_id...\n";
            $db->exec("UPDATE sales SET user_id = employee_id");
            
            echo "5. Suppression de la colonne employee_id...\n";
            $db->exec("ALTER TABLE sales DROP COLUMN employee_id");
            
            echo "✓ Migration par méthode alternative réussie.\n";
        }
        
        // 4. Vérifier le résultat
        echo "\n6. Vérification de la migration...\n";
        $verify_query = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE\n                        FROM INFORMATION_SCHEMA.COLUMNS \n                        WHERE TABLE_NAME = 'sales' \n                        AND COLUMN_NAME IN ('user_id', 'employee_id')";
        
        $stmt = $db->query($verify_query);
        $columns = $stmt->fetchAll();
        
        foreach ($columns as $column) {
            echo "  - {$column['COLUMN_NAME']}: {$column['DATA_TYPE']} ({$column['IS_NULLABLE']})\n";
        }
        
        // 5. Test de fonctionnement
        echo "\n7. Test de fonctionnement...\n";
        $test_query = "SELECT COUNT(*) as total_sales, COUNT(DISTINCT user_id) as unique_users FROM sales";
        $stmt = $db->query($test_query);
        $result = $stmt->fetch();
        
        echo "  - Total des ventes: {$result['total_sales']}\n";
        echo "  - Utilisateurs uniques: {$result['unique_users']}\n";
        
        // Valider la transaction
        $db->commit();
        
        echo "\n✅ Migration terminée avec succès!\n";
        echo "\nLa base de données a été mise à jour. Vous pouvez maintenant utiliser reports.php sans erreur.\n";
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la migration: " . $e->getMessage() . "\n";
    echo "\nTrace de l'erreur:\n" . $e->getTraceAsString() . "\n";
    
    echo "\n=== Instructions manuelles ===\n";
    echo "Si la migration automatique échoue, exécutez manuellement ces commandes SQL:\n\n";
    echo "-- Vérifier la structure actuelle\n";
    echo "DESCRIBE sales;\n\n";
    echo "-- Renommer la colonne (MySQL 8.0+)\n";
    echo "ALTER TABLE sales RENAME COLUMN employee_id TO user_id;\n\n";
    echo "-- OU méthode alternative (versions antérieures)\n";
    echo "ALTER TABLE sales ADD COLUMN user_id INT;\n";
    echo "UPDATE sales SET user_id = employee_id;\n";
    echo "ALTER TABLE sales DROP COLUMN employee_id;\n";
}

echo "\n=== Fin du script de migration ===\n";
?>