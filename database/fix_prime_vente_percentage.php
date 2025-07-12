<?php
/**
 * Script pour corriger la valeur de prime_vente_percentage à 5% (0.05)
 * Le calcul actuel montre 1926 au lieu de 2380, ce qui suggère que le pourcentage n'est pas correct
 */

require_once '../config/database.php';

try {
    $db = getDB();
    
    // Vérifier la valeur actuelle
    echo "🔍 Vérification de la configuration actuelle...\n";
    $stmt = $db->prepare("SELECT config_key, config_value, description FROM weekly_performance_config WHERE config_key = 'prime_vente_percentage'");
    $stmt->execute();
    $current_config = $stmt->fetch();
    
    if ($current_config) {
        echo "📊 Valeur actuelle de prime_vente_percentage: {$current_config['config_value']} ({$current_config['description']})\n";
        
        // Calculer le pourcentage actuel
        $current_percentage = $current_config['config_value'] * 100;
        echo "📈 Cela correspond à: {$current_percentage}%\n";
        
        // Si ce n'est pas 5%, corriger
        if ($current_config['config_value'] != 0.05) {
            echo "\n⚠️  La valeur n'est pas à 5% comme attendu. Correction en cours...\n";
            
            $stmt = $db->prepare("UPDATE weekly_performance_config SET config_value = 0.05, description = 'Pourcentage de prime sur le CA ventes (5%)' WHERE config_key = 'prime_vente_percentage'");
            $stmt->execute();
            
            echo "✅ Valeur corrigée à 0.05 (5%)\n";
        } else {
            echo "✅ La valeur est déjà correcte à 5%\n";
        }
    } else {
        echo "❌ Configuration prime_vente_percentage non trouvée. Création...\n";
        
        $stmt = $db->prepare("INSERT INTO weekly_performance_config (config_key, config_value, description) VALUES ('prime_vente_percentage', 0.05, 'Pourcentage de prime sur le CA ventes (5%)')");
        $stmt->execute();
        
        echo "✅ Configuration créée avec la valeur 0.05 (5%)\n";
    }
    
    // Afficher toutes les configurations pour vérification
    echo "\n📋 Configuration complète des primes:\n";
    $stmt = $db->query("SELECT config_key, config_value, description FROM weekly_performance_config ORDER BY config_key");
    while ($row = $stmt->fetch()) {
        $percentage = ($row['config_key'] == 'prime_vente_percentage' || $row['config_key'] == 'prime_vente_bonus_rate') ? ($row['config_value'] * 100) . '%' : $row['config_value'];
        echo "   - {$row['config_key']}: {$percentage} ({$row['description']})\n";
    }
    
    echo "\n🎯 Correction terminée ! La prime vente devrait maintenant être calculée à 5% du CA.\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur de base de données : " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Erreur générale : " . $e->getMessage() . "\n";
    exit(1);
}
?>