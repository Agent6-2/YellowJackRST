<?php
/**
 * Script de correction de la configuration Discord
 * Nettoie les doublons et configure les paramètres par défaut
 */

require_once 'includes/auth.php';
require_once 'config/database.php';

echo "=== Correction de la Configuration Discord ===\n\n";

$db = getDB();

try {
    $db->beginTransaction();
    
    echo "1. Nettoyage complet des paramètres Discord...\n";
    
    // Supprimer TOUS les paramètres Discord existants
    $stmt = $db->prepare("DELETE FROM system_settings WHERE setting_key LIKE 'discord_%'");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    echo "   Supprimé {$deleted} entrées existantes\n";
    
    echo "\n2. Création de la configuration Discord propre...\n";
    
    // Configuration par défaut - une seule entrée par paramètre
    $default_config = [
        'discord_webhook_url' => 'https://discord.com/api/webhooks/1395789021661233352/NjGvX54Qq-K4dLiEAhPg-70-GwMGoJA6rZP_pmwf1TricRqf4dt8zWGJ-k-Vcm95Ndbj',
        'discord_notifications_enabled' => '1',
        'discord_notify_sales' => '1',
        'discord_notify_cleaning' => '1',
        'discord_notify_goals' => '1',
        'discord_notify_weekly' => '1'
    ];
    
    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    
    foreach ($default_config as $key => $value) {
        $description = '';
        switch ($key) {
            case 'discord_webhook_url':
                $description = 'URL du webhook Discord pour les notifications';
                break;
            case 'discord_notifications_enabled':
                $description = 'Activer/désactiver toutes les notifications Discord';
                break;
            case 'discord_notify_sales':
                $description = 'Notifications Discord pour les nouvelles ventes';
                break;
            case 'discord_notify_cleaning':
                $description = 'Notifications Discord pour les services de ménage';
                break;
            case 'discord_notify_goals':
                $description = 'Notifications Discord pour les objectifs atteints';
                break;
            case 'discord_notify_weekly':
                $description = 'Notifications Discord pour les résumés hebdomadaires';
                break;
        }
        
        $stmt->execute([$key, $value, $description]);
        echo "   ✅ {$key}: {$value}\n";
    }
    
    $db->commit();
    echo "\n✅ Configuration Discord corrigée avec succès !\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "\n❌ Erreur lors de la correction: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n3. Vérification finale...\n";

// Vérifier qu'il n'y a qu'une seule entrée par paramètre
$stmt = $db->prepare("SELECT setting_key, COUNT(*) as count FROM system_settings WHERE setting_key LIKE 'discord_%' GROUP BY setting_key");
$stmt->execute();
$counts = $stmt->fetchAll();

foreach ($counts as $count) {
    if ($count['count'] > 1) {
        echo "   ⚠️  {$count['setting_key']}: {$count['count']} entrées (problème!)\n";
    } else {
        echo "   ✅ {$count['setting_key']}: {$count['count']} entrée\n";
    }
}

echo "\n4. Configuration actuelle...\n";
$stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'discord_%' ORDER BY setting_key");
$stmt->execute();
$settings = $stmt->fetchAll();

foreach ($settings as $setting) {
    $value = $setting['setting_value'];
    if (strlen($value) > 50) {
        $display = substr($value, 0, 50) . '...';
    } else {
        $display = $value === '1' ? '✅ Activé' : ($value === '0' ? '❌ Désactivé' : $value);
    }
    echo "   {$setting['setting_key']}: {$display}\n";
}

echo "\n=== RÉSUMÉ ===\n";
echo "✅ Configuration Discord nettoyée et unifiée\n";
echo "✅ Une seule entrée par paramètre\n";
echo "✅ Notifications activées par défaut\n";
echo "\n📍 Interface disponible dans panel/settings.php (onglet Discord)\n";
echo "\n=== FIN DE LA CORRECTION ===\n";
?>