<?php
/**
 * Script pour enregistrer les commandes slash Discord
 * 
 * Ce script doit être exécuté une seule fois pour enregistrer
 * les commandes slash auprès de l'API Discord
 */

require_once __DIR__ . '/../config/database.php';

// Configuration Discord
$bot_token = DISCORD_BOT_TOKEN;
$application_id = DISCORD_APPLICATION_ID;

if (empty($bot_token) || empty($application_id)) {
    die("Erreur: DISCORD_BOT_TOKEN et DISCORD_APPLICATION_ID doivent être configurés dans config/database.php\n");
}

// Définition des commandes slash
$commands = [
    [
        'name' => 'ventes',
        'description' => 'Afficher les statistiques de ventes',
        'options' => [
            [
                'type' => 3, // STRING
                'name' => 'periode',
                'description' => 'Période à analyser',
                'required' => false,
                'choices' => [
                    ['name' => 'Aujourd\'hui', 'value' => 'today'],
                    ['name' => 'Cette semaine', 'value' => 'week'],
                    ['name' => 'Ce mois', 'value' => 'month']
                ]
            ]
        ]
    ],
    [
        'name' => 'stats',
        'description' => 'Afficher les statistiques d\'un employé',
        'options' => [
            [
                'type' => 3, // STRING
                'name' => 'employe',
                'description' => 'Nom ou prénom de l\'employé',
                'required' => true
            ]
        ]
    ],
    [
        'name' => 'menages',
        'description' => 'Gestion des services de ménage',
        'options' => [
            [
                'type' => 3, // STRING
                'name' => 'action',
                'description' => 'Action à effectuer',
                'required' => false,
                'choices' => [
                    ['name' => 'Voir les statistiques', 'value' => 'voir']
                ]
            ]
        ]
    ],
    [
        'name' => 'objectifs',
        'description' => 'Afficher les objectifs du restaurant'
    ],
    [
        'name' => 'rapport',
        'description' => 'Générer un rapport rapide',
        'options' => [
            [
                'type' => 3, // STRING
                'name' => 'periode',
                'description' => 'Période du rapport',
                'required' => false,
                'choices' => [
                    ['name' => 'Aujourd\'hui', 'value' => 'today'],
                    ['name' => 'Cette semaine', 'value' => 'week']
                ]
            ]
        ]
    ]
];

/**
 * Enregistrer les commandes auprès de Discord
 */
function registerCommands($application_id, $bot_token, $commands) {
    $url = "https://discord.com/api/v10/applications/{$application_id}/commands";
    
    $headers = [
        'Authorization: Bot ' . $bot_token,
        'Content-Type: application/json'
    ];
    
    foreach ($commands as $command) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($command));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 201 || $http_code === 200) {
            echo "✅ Commande '{$command['name']}' enregistrée avec succès\n";
        } else {
            echo "❌ Erreur lors de l'enregistrement de '{$command['name']}': {$response}\n";
        }
    }
}

// Exécuter l'enregistrement
echo "🤖 Enregistrement des commandes Discord...\n\n";
registerCommands($application_id, $bot_token, $commands);
echo "\n✨ Processus terminé!\n";

?>