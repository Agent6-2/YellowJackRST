<?php
/**
 * Script de test pour le bot Discord
 * 
 * Ce script permet de tester les fonctionnalités du bot en local
 * sans avoir besoin de Discord
 */

require_once __DIR__ . '/discord_bot.php';

/**
 * Simuler une interaction Discord
 */
function simulateDiscordInteraction($command, $options = []) {
    echo "\n🧪 Test de la commande: /{$command}\n";
    echo str_repeat("-", 50) . "\n";
    
    // Créer une fausse interaction Discord
    $interaction_data = [
        'type' => 2, // APPLICATION_COMMAND
        'data' => [
            'name' => $command,
            'options' => $options
        ],
        'member' => [
            'user' => [
                'id' => '123456789'
            ]
        ]
    ];
    
    // Créer une instance du bot
    $bot = new YellowjackDiscordBot();
    
    // Utiliser la réflexion pour accéder à la méthode privée
    $reflection = new ReflectionClass($bot);
    $method = $reflection->getMethod('handleCommand');
    $method->setAccessible(true);
    
    // Capturer la sortie sans les headers
    ob_start();
    try {
        // Désactiver temporairement les headers
        $original_headers_sent = headers_sent();
        $method->invoke($bot, $interaction_data);
    } catch (Exception $e) {
        echo "Erreur: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        // Capturer les erreurs de headers
        if (strpos($e->getMessage(), 'headers already sent') === false) {
            echo "Erreur: " . $e->getMessage() . "\n";
        }
    }
    $output = ob_get_clean();
    
    // Décoder et afficher la réponse
    if ($output) {
        $response = json_decode($output, true);
        if ($response && isset($response['data'])) {
            if (isset($response['data']['embeds'])) {
                $embed = $response['data']['embeds'][0];
                echo "📋 Titre: " . $embed['title'] . "\n";
                if (isset($embed['fields'])) {
                    foreach ($embed['fields'] as $field) {
                        echo "\n📌 " . $field['name'] . ":\n";
                        echo $field['value'] . "\n";
                    }
                }
            } elseif (isset($response['data']['content'])) {
                echo $response['data']['content'] . "\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
}

// Tests des différentes commandes
echo "🤖 Test du Bot Discord Le Yellowjack\n";
echo "======================================\n";

// Test 1: Commande /ventes
simulateDiscordInteraction('ventes', [
    ['name' => 'periode', 'value' => 'today']
]);

// Test 2: Commande /ventes pour la semaine
simulateDiscordInteraction('ventes', [
    ['name' => 'periode', 'value' => 'week']
]);

// Test 3: Commande /stats (nécessite un employé existant)
simulateDiscordInteraction('stats', [
    ['name' => 'employe', 'value' => 'Admin']
]);

// Test 4: Commande /menages
simulateDiscordInteraction('menages', [
    ['name' => 'action', 'value' => 'voir']
]);

// Test 5: Commande /objectifs
simulateDiscordInteraction('objectifs');

// Test 6: Commande /rapport
simulateDiscordInteraction('rapport', [
    ['name' => 'periode', 'value' => 'today']
]);

// Test 7: Commande inexistante
simulateDiscordInteraction('commande_inexistante');

echo "\n✅ Tests terminés!\n";
echo "\n💡 Pour tester en conditions réelles:\n";
echo "1. Configurez les constantes Discord dans config/database.php\n";
echo "2. Exécutez register_commands.php\n";
echo "3. Configurez l'URL d'interaction dans Discord\n";
echo "4. Invitez le bot sur votre serveur Discord\n";

?>