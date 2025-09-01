<?php
/**
 * Script pour créer la configuration multi-webhook Discord
 * Ajoute les paramètres pour 4 webhooks séparés :
 * - Nouvelles ventes
 * - Services de ménage
 * - Objectifs atteints
 * - Résumés hebdomadaires
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "🔧 Configuration des webhooks Discord multiples...\n\n";
    
    // Définir les nouveaux paramètres de webhook
    $webhook_settings = [
        'discord_webhook_sales' => [
            'value' => '',
            'description' => 'URL du webhook Discord pour les nouvelles ventes'
        ],
        'discord_webhook_cleaning' => [
            'value' => '',
            'description' => 'URL du webhook Discord pour les services de ménage'
        ],
        'discord_webhook_goals' => [
            'value' => '',
            'description' => 'URL du webhook Discord pour les objectifs atteints'
        ],
        'discord_webhook_weekly' => [
            'value' => '',
            'description' => 'URL du webhook Discord pour les résumés hebdomadaires'
        ],
        'discord_multi_webhook_enabled' => [
            'value' => '0',
            'description' => 'Activer le système de webhooks multiples (0=webhook unique, 1=webhooks séparés)'
        ]
    ];
    
    $db->beginTransaction();
    
    echo "1. Ajout des nouveaux paramètres de webhook...\n";
    
    foreach ($webhook_settings as $key => $config) {
        // Vérifier si le paramètre existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $exists = $stmt->fetchColumn() > 0;
        
        if (!$exists) {
            // Ajouter le nouveau paramètre
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, description) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$key, $config['value'], $config['description']]);
            echo "   ✅ Ajouté: {$key}\n";
        } else {
            echo "   ⚠️  Existe déjà: {$key}\n";
        }
    }
    
    echo "\n2. Migration de l'ancien webhook vers le nouveau système...\n";
    
    // Récupérer l'ancien webhook s'il existe
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'discord_webhook_url'");
    $stmt->execute();
    $old_webhook = $stmt->fetchColumn();
    
    if ($old_webhook && !empty($old_webhook)) {
        echo "   📋 Ancien webhook trouvé: " . substr($old_webhook, 0, 50) . "...\n";
        
        // Copier l'ancien webhook vers tous les nouveaux webhooks
        $webhook_types = ['discord_webhook_sales', 'discord_webhook_cleaning', 'discord_webhook_goals', 'discord_webhook_weekly'];
        
        foreach ($webhook_types as $webhook_type) {
            $stmt = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$old_webhook, $webhook_type]);
            echo "   ✅ Copié vers: {$webhook_type}\n";
        }
        
        echo "   ℹ️  L'ancien webhook a été copié vers tous les nouveaux webhooks.\n";
        echo "   ℹ️  Vous pouvez maintenant configurer des webhooks différents pour chaque catégorie.\n";
    } else {
        echo "   ℹ️  Aucun ancien webhook trouvé, configuration vierge.\n";
    }
    
    $db->commit();
    echo "\n✅ Configuration multi-webhook créée avec succès !\n\n";
    
    echo "📋 Prochaines étapes :\n";
    echo "1. Allez dans Configuration Discord (/panel/discord_config.php)\n";
    echo "2. Activez le mode multi-webhook\n";
    echo "3. Configurez un webhook différent pour chaque catégorie :\n";
    echo "   - 💰 Nouvelles ventes\n";
    echo "   - 🧹 Services de ménage\n";
    echo "   - 🎯 Objectifs atteints\n";
    echo "   - 📊 Résumés hebdomadaires\n";
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo "\n❌ Erreur lors de la configuration: " . $e->getMessage() . "\n";
    exit(1);
}
?>