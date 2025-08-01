<?php
/**
 * Script d'installation pour le bot Discord
 * 
 * Ce script aide à configurer et installer le bot Discord
 */

require_once __DIR__ . '/../config/database.php';

echo "🤖 Installation du Bot Discord Le Yellowjack\n";
echo "=============================================\n\n";

// Vérifier les prérequis
echo "🔍 Vérification des prérequis...\n";

// Vérifier PHP
if (version_compare(PHP_VERSION, '7.4.0') < 0) {
    die("❌ PHP 7.4+ requis. Version actuelle: " . PHP_VERSION . "\n");
}
echo "✅ PHP " . PHP_VERSION . " détecté\n";

// Vérifier l'extension sodium
if (!extension_loaded('sodium')) {
    echo "⚠️  Extension PHP 'sodium' non disponible\n";
    echo "   Note: La vérification des signatures sera désactivée en mode développement\n";
    echo "   Pour la production, installez l'extension sodium\n";
} else {
    echo "✅ Extension sodium disponible\n";
}

// Vérifier cURL
if (!extension_loaded('curl')) {
    echo "⚠️  Extension PHP 'curl' non disponible\n";
    echo "   Note: Les tests de connectivité Discord seront ignorés\n";
    echo "   Pour la production, installez l'extension cURL\n";
    $curl_available = false;
} else {
    echo "✅ Extension cURL disponible\n";
    $curl_available = true;
}

// Vérifier PDO
if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
    die("❌ Extensions PHP 'pdo' et 'pdo_mysql' requises\n");
}
echo "✅ Extensions PDO disponibles\n";

// Vérifier la connexion à la base de données
try {
    $db = getDB();
    echo "✅ Connexion à la base de données réussie\n";
} catch (Exception $e) {
    die("❌ Erreur de connexion à la base de données: " . $e->getMessage() . "\n");
}

echo "\n📋 Vérification de la configuration Discord...\n";

// Vérifier les constantes Discord
$discord_configured = true;

if (!defined('DISCORD_BOT_TOKEN') || empty(DISCORD_BOT_TOKEN)) {
    echo "⚠️  DISCORD_BOT_TOKEN non configuré\n";
    $discord_configured = false;
}

if (!defined('DISCORD_APPLICATION_ID') || empty(DISCORD_APPLICATION_ID)) {
    echo "⚠️  DISCORD_APPLICATION_ID non configuré\n";
    $discord_configured = false;
}

if (!defined('DISCORD_CLIENT_PUBLIC_KEY') || empty(DISCORD_CLIENT_PUBLIC_KEY)) {
    echo "⚠️  DISCORD_CLIENT_PUBLIC_KEY non configuré\n";
    $discord_configured = false;
}

if ($discord_configured) {
    echo "✅ Configuration Discord complète\n";
} else {
    echo "\n📝 Pour configurer Discord:\n";
    echo "1. Éditez config/database.php\n";
    echo "2. Ajoutez vos clés Discord (voir bot/config_example.php)\n";
    echo "3. Relancez ce script\n\n";
}

echo "\n🔧 Vérification des fichiers du bot...\n";

$required_files = [
    'discord_bot.php' => 'Fichier principal du bot',
    'register_commands.php' => 'Script d\'enregistrement des commandes',
    'README_BOT.md' => 'Documentation du bot',
    '.htaccess' => 'Configuration de sécurité'
];

foreach ($required_files as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✅ {$file} - {$description}\n";
    } else {
        echo "❌ {$file} manquant - {$description}\n";
    }
}

echo "\n🌐 Test de connectivité Discord...\n";

if ($discord_configured && $curl_available) {
    // Tester la connexion à l'API Discord
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://discord.com/api/v10/applications/' . DISCORD_APPLICATION_ID);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bot ' . DISCORD_BOT_TOKEN,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $app_data = json_decode($response, true);
        echo "✅ Connexion à Discord réussie\n";
        echo "   Application: " . $app_data['name'] . "\n";
        echo "   ID: " . $app_data['id'] . "\n";
    } else {
        echo "❌ Erreur de connexion à Discord (Code: {$http_code})\n";
        if ($response) {
            $error = json_decode($response, true);
            echo "   Erreur: " . ($error['message'] ?? 'Inconnue') . "\n";
        }
    }
} else if (!$curl_available) {
    echo "⏭️  Test ignoré - Extension cURL non disponible\n";
} else {
    echo "⏭️  Test ignoré - Configuration Discord incomplète\n";
}

echo "\n📊 Résumé de l'installation:\n";
echo "============================\n";

if ($discord_configured) {
    echo "✅ Bot Discord prêt à être utilisé\n\n";
    
    echo "🚀 Prochaines étapes:\n";
    echo "1. Configurez l'URL d'interaction dans Discord:\n";
    echo "   https://votre-domaine.com/bot/discord_bot.php\n\n";
    echo "2. Enregistrez les commandes:\n";
    echo "   php bot/register_commands.php\n\n";
    echo "3. Invitez le bot sur votre serveur Discord\n\n";
    echo "4. Testez avec les commandes slash dans Discord\n\n";
    
    echo "📖 Documentation complète: bot/README_BOT.md\n";
} else {
    echo "⚠️  Configuration Discord requise\n\n";
    
    echo "🔧 Actions nécessaires:\n";
    echo "1. Créez une application Discord:\n";
    echo "   https://discord.com/developers/applications\n\n";
    echo "2. Configurez les constantes dans config/database.php\n";
    echo "   (Voir bot/config_example.php pour un exemple)\n\n";
    echo "3. Relancez ce script d'installation\n\n";
}

echo "💡 Pour tester en local: php bot/test_bot.php\n";
echo "🆘 Support: Consultez bot/README_BOT.md\n\n";

?>