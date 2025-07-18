<?php
/**
 * Exemples d'intégration du webhook Discord dans Le Yellowjack
 * 
 * Ce fichier montre comment utiliser le système de webhook Discord
 * dans différentes parties de l'application.
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/discord_webhook.php';

/**
 * EXEMPLE 1: Notification de vente simple
 * À intégrer dans le processus de vente (cashier.php)
 */
function exemple_notification_vente($sale_id) {
    // Utiliser la fonction helper déjà créée
    $result = notifyDiscordSale($sale_id);
    
    if ($result) {
        echo "✅ Notification Discord envoyée pour la vente #$sale_id";
    } else {
        echo "❌ Échec de l'envoi de la notification Discord";
    }
}

/**
 * EXEMPLE 2: Notification d'objectif atteint
 * À intégrer dans le système de calcul des commissions
 */
function exemple_objectif_atteint($employee_id, $monthly_sales) {
    try {
        $db = getDB();
        
        // Récupérer les infos de l'employé
        $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch();
        
        if ($employee && $monthly_sales >= 5000) { // Objectif de 5000$ par mois
            $webhook = getDiscordWebhook();
            $employee_name = $employee['first_name'] . ' ' . $employee['last_name'];
            
            $webhook->notifyGoalAchieved(
                $employee_name,
                'Objectif mensuel',
                $monthly_sales
            );
            
            echo "🎯 Notification d'objectif envoyée pour $employee_name";
        }
    } catch (Exception $e) {
        error_log("Erreur notification objectif: " . $e->getMessage());
    }
}

/**
 * EXEMPLE 3: Notification d'erreur système
 * À intégrer dans les gestionnaires d'erreurs
 */
function exemple_notification_erreur($error_message, $context = '') {
    $webhook = getDiscordWebhook();
    $webhook->notifyError($error_message, $context);
    
    echo "⚠️ Notification d'erreur envoyée";
}

/**
 * EXEMPLE 4: Notification personnalisée avec embed
 * Pour des événements spéciaux
 */
function exemple_notification_personnalisee() {
    $webhook = getDiscordWebhook();
    
    $title = "🎉 Événement Spécial";
    $description = "Le Yellowjack a atteint un nouveau record de ventes !";
    $color = 0xffd700; // Couleur or
    
    $fields = [
        [
            'name' => '📊 Record',
            'value' => '10,000$ en une journée',
            'inline' => true
        ],
        [
            'name' => '📅 Date',
            'value' => date('d/m/Y'),
            'inline' => true
        ],
        [
            'name' => '🏆 Équipe',
            'value' => 'Félicitations à toute l\'équipe !',
            'inline' => false
        ]
    ];
    
    $result = $webhook->sendEmbed($title, $description, $color, $fields);
    
    if ($result) {
        echo "🎉 Notification spéciale envoyée";
    }
}

/**
 * EXEMPLE 5: Notification de fin de semaine avec résumé
 * À intégrer dans le système de finalisation hebdomadaire
 */
function exemple_resume_hebdomadaire() {
    try {
        $db = getDB();
        
        // Calculer les stats de la semaine
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        
        // Ventes de la semaine
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_sales,
                SUM(final_amount) as total_revenue,
                AVG(final_amount) as avg_sale
            FROM sales 
            WHERE DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$week_start, $week_end]);
        $stats = $stmt->fetch();
        
        // Top vendeur
        $stmt = $db->prepare("
            SELECT 
                u.first_name, 
                u.last_name,
                COUNT(s.id) as sales_count,
                SUM(s.final_amount) as total_sales
            FROM sales s
            JOIN users u ON s.user_id = u.id
            WHERE DATE(s.created_at) BETWEEN ? AND ?
            GROUP BY s.user_id
            ORDER BY total_sales DESC
            LIMIT 1
        ");
        $stmt->execute([$week_start, $week_end]);
        $top_seller = $stmt->fetch();
        
        $webhook = getDiscordWebhook();
        
        $title = "📊 Résumé Hebdomadaire";
        $description = "Voici le résumé de la semaine du $week_start au $week_end";
        
        $fields = [
            [
                'name' => '💰 Chiffre d\'affaires',
                'value' => number_format($stats['total_revenue'], 2) . '$',
                'inline' => true
            ],
            [
                'name' => '🛒 Nombre de ventes',
                'value' => $stats['total_sales'],
                'inline' => true
            ],
            [
                'name' => '📈 Vente moyenne',
                'value' => number_format($stats['avg_sale'], 2) . '$',
                'inline' => true
            ]
        ];
        
        if ($top_seller) {
            $fields[] = [
                'name' => '🏆 Top Vendeur',
                'value' => $top_seller['first_name'] . ' ' . $top_seller['last_name'] . 
                          ' (' . $top_seller['sales_count'] . ' ventes, ' . 
                          number_format($top_seller['total_sales'], 2) . '$)',
                'inline' => false
            ];
        }
        
        $webhook->sendEmbed($title, $description, 0x0099ff, $fields);
        
        echo "📊 Résumé hebdomadaire envoyé";
        
    } catch (Exception $e) {
        error_log("Erreur résumé hebdomadaire: " . $e->getMessage());
    }
}

/**
 * EXEMPLE 6: Test de configuration Discord
 * Pour vérifier que le webhook fonctionne
 */
function test_discord_webhook() {
    if (empty(DISCORD_WEBHOOK_URL)) {
        echo "❌ URL du webhook Discord non configurée dans database.php";
        return false;
    }
    
    $webhook = getDiscordWebhook();
    $result = $webhook->sendMessage(
        "🧪 Test de connexion depuis Le Yellowjack ! Le webhook Discord fonctionne correctement.",
        "Le Yellowjack Test Bot"
    );
    
    if ($result) {
        echo "✅ Test réussi ! Le webhook Discord fonctionne.";
        return true;
    } else {
        echo "❌ Test échoué. Vérifiez l'URL du webhook.";
        return false;
    }
}

// Si ce fichier est exécuté directement, lancer un test
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "<h2>Test du Webhook Discord</h2>";
    echo "<p>";
    test_discord_webhook();
    echo "</p>";
    
    echo "<h3>Exemples disponibles :</h3>";
    echo "<ul>";
    echo "<li>exemple_notification_vente(\$sale_id)</li>";
    echo "<li>exemple_objectif_atteint(\$employee_id, \$monthly_sales)</li>";
    echo "<li>exemple_notification_erreur(\$error_message, \$context)</li>";
    echo "<li>exemple_notification_personnalisee()</li>";
    echo "<li>exemple_resume_hebdomadaire()</li>";
    echo "</ul>";
}

/**
 * INTÉGRATION DANS LE CODE EXISTANT:
 * 
 * 1. Dans cashier.php, après l'enregistrement d'une vente :
 *    notifyDiscordSale($sale_id);
 * 
 * 2. Dans le système de calcul des commissions :
 *    exemple_objectif_atteint($user_id, $monthly_total);
 * 
 * 3. Dans les gestionnaires d'erreurs :
 *    exemple_notification_erreur($e->getMessage(), 'Contexte de l\'erreur');
 * 
 * 4. Dans le script de finalisation hebdomadaire :
 *    exemple_resume_hebdomadaire();
 * 
 * 5. Pour configurer le webhook :
 *    - Aller dans Discord > Paramètres du serveur > Intégrations > Webhooks
 *    - Créer un nouveau webhook
 *    - Copier l'URL
 *    - Modifier DISCORD_WEBHOOK_URL dans config/database.php
 */
?>