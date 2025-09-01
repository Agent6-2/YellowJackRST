<?php
/**
 * Test de la configuration Discord unifiée
 * Vérifie que les paramètres sont correctement synchronisés
 */

require_once 'includes/auth.php';
require_once 'config/database.php';
require_once 'includes/discord_config.php';

echo "=== Test de la Configuration Discord Unifiée ===\n\n";

$db = getDB();
$discordConfig = getDiscordConfig();

// 1. Vérifier les paramètres dans system_settings
echo "1. Paramètres Discord dans system_settings :\n";
$stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'discord_%' ORDER BY setting_key");
$stmt->execute();
$settings = $stmt->fetchAll();

if (empty($settings)) {
    echo "   ❌ Aucun paramètre Discord trouvé dans system_settings\n";
    echo "   💡 Conseil: Allez dans panel/settings.php onglet Discord pour configurer\n";
} else {
    foreach ($settings as $setting) {
        $value = $setting['setting_value'];
        $display = $value === '1' ? '✅ Activé' : ($value === '0' ? '❌ Désactivé' : $value);
        echo "   {$setting['setting_key']}: {$display}\n";
    }
}

echo "\n";

// 2. Vérifier la configuration via DiscordConfig
echo "2. Configuration via DiscordConfig :\n";
$config = $discordConfig->getConfig();

echo "   webhook_url: " . (!empty($config['webhook_url']) ? '✅ Configuré' : '❌ Non configuré') . "\n";
echo "   notifications_enabled: " . ($config['notifications_enabled'] ? '✅ Activé' : '❌ Désactivé') . "\n";
echo "   notify_sales: " . ($config['notify_sales'] ? '✅ Activé' : '❌ Désactivé') . "\n";
echo "   notify_cleaning: " . ($config['notify_cleaning'] ? '✅ Activé' : '❌ Désactivé') . "\n";
echo "   notify_goals: " . ($config['notify_goals'] ? '✅ Activé' : '❌ Désactivé') . "\n";
echo "   notify_weekly_summary: " . ($config['notify_weekly_summary'] ? '✅ Activé' : '❌ Désactivé') . "\n";

echo "\n";

// 3. Vérifier les méthodes de vérification
echo "3. Méthodes de vérification :\n";
echo "   isNotificationsEnabled(): " . ($discordConfig->isNotificationsEnabled() ? '✅ Oui' : '❌ Non') . "\n";
echo "   isNotifySalesEnabled(): " . ($discordConfig->isNotifySalesEnabled() ? '✅ Oui' : '❌ Non') . "\n";
echo "   isNotifyCleaningEnabled(): " . ($discordConfig->isNotifyCleaningEnabled() ? '✅ Oui' : '❌ Non') . "\n";
echo "   isNotifyGoalsEnabled(): " . ($discordConfig->isNotifyGoalsEnabled() ? '✅ Oui' : '❌ Non') . "\n";
echo "   isNotifyWeeklySummaryEnabled(): " . ($discordConfig->isNotifyWeeklySummaryEnabled() ? '✅ Oui' : '❌ Non') . "\n";

echo "\n";

// 4. Test de sauvegarde
echo "4. Test de sauvegarde (simulation) :\n";
$testConfig = [
    'webhook_url' => 'https://discord.com/api/webhooks/test',
    'notifications_enabled' => true,
    'notify_sales' => true,
    'notify_cleaning' => false,
    'notify_goals' => true,
    'notify_weekly_summary' => true
];

echo "   Configuration de test préparée ✅\n";
echo "   Pour tester la sauvegarde, décommentez les lignes suivantes dans ce script\n";

/*
// Décommentez ces lignes pour tester la sauvegarde
if ($discordConfig->saveConfig($testConfig)) {
    echo "   Sauvegarde réussie ✅\n";
    
    // Recharger et vérifier
    $newDiscordConfig = getDiscordConfig();
    $newConfig = $newDiscordConfig->getConfig();
    
    echo "   Vérification après sauvegarde :\n";
    echo "     webhook_url: " . ($newConfig['webhook_url'] === $testConfig['webhook_url'] ? '✅' : '❌') . "\n";
    echo "     notifications_enabled: " . ($newConfig['notifications_enabled'] === $testConfig['notifications_enabled'] ? '✅' : '❌') . "\n";
} else {
    echo "   Erreur lors de la sauvegarde ❌\n";
}
*/

echo "\n";

// 5. Vérifications finales
echo "5. Vérifications finales :\n";

// Vérifier que les fichiers existent
$files_to_check = [
    'panel/settings.php' => 'Interface de configuration principale',
    'panel/discord_config.php' => 'Interface de configuration avancée',
    'includes/discord_config.php' => 'Classe de configuration',
    'includes/discord_webhook.php' => 'Classe webhook'
];

foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        echo "   ✅ {$file} ({$description})\n";
    } else {
        echo "   ❌ {$file} manquant ({$description})\n";
    }
}

echo "\n";

// Résumé
echo "=== RÉSUMÉ ===\n";
if (!empty($config['webhook_url']) && $config['notifications_enabled']) {
    echo "✅ Configuration Discord opérationnelle\n";
    echo "📍 Vous pouvez configurer les webhooks dans :\n";
    echo "   - panel/settings.php (onglet Discord) - Interface simplifiée\n";
    echo "   - panel/discord_config.php - Interface avancée avec tests\n";
} else {
    echo "⚠️  Configuration Discord incomplète\n";
    if (empty($config['webhook_url'])) {
        echo "   - URL du webhook manquante\n";
    }
    if (!$config['notifications_enabled']) {
        echo "   - Notifications désactivées\n";
    }
    echo "📍 Allez dans panel/settings.php (onglet Discord) pour configurer\n";
}

echo "\n=== FIN DU TEST ===\n";
?>