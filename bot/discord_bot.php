<?php
/**
 * Bot Discord interactif pour Le Yellowjack
 * 
 * Ce bot permet d'interagir avec le système via des commandes Discord
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/week_functions.php';
require_once __DIR__ . '/../includes/discord_webhook.php';

/**
 * Classe principale du bot Discord
 */
class YellowjackDiscordBot {
    private $client_public_key;
    private $bot_token;
    private $db;
    
    public function __construct() {
        $this->client_public_key = DISCORD_CLIENT_PUBLIC_KEY ?? '';
        $this->bot_token = DISCORD_BOT_TOKEN ?? '';
        $this->db = getDB();
    }
    
    /**
     * Point d'entrée principal pour les interactions Discord
     */
    public function handleInteraction() {
        // Lire les données de la requête
        $postData = file_get_contents('php://input');
        
        // Vérifier la signature
        if (!$this->verifySignature($postData)) {
            http_response_code(401);
            die('Signature invalide');
        }
        
        $data = json_decode($postData, true);
        
        // Gérer les différents types d'interactions
        switch ($data['type']) {
            case 1: // PING
                $this->respondPong();
                break;
                
            case 2: // APPLICATION_COMMAND
                $this->handleCommand($data);
                break;
                
            default:
                http_response_code(400);
                die('Type d\'interaction non supporté');
        }
    }
    
    /**
     * Vérifier la signature de la requête Discord
     */
    private function verifySignature($postData) {
        // En mode développement ou si sodium n'est pas disponible, ignorer la vérification
        if (defined('APP_DEBUG') && APP_DEBUG) {
            return true;
        }
        
        if (!extension_loaded('sodium')) {
            error_log('Extension sodium non disponible - vérification des signatures désactivée');
            return true; // Permettre en développement
        }
        
        if (empty($this->client_public_key)) {
            error_log('Clé publique Discord non configurée');
            return false;
        }
        
        $signature = $_SERVER['HTTP_X_SIGNATURE_ED25519'] ?? '';
        $timestamp = $_SERVER['HTTP_X_SIGNATURE_TIMESTAMP'] ?? '';
        
        if (empty($signature) || empty($timestamp)) {
            return false;
        }
        
        return sodium_crypto_sign_verify_detached(
            hex2bin($signature),
            $timestamp . $postData,
            hex2bin($this->client_public_key)
        );
    }
    
    /**
     * Répondre à un ping Discord
     */
    private function respondPong() {
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['type' => 1]);
        exit;
    }
    
    /**
     * Gérer les commandes slash
     */
    private function handleCommand($data) {
        $command = $data['data']['name'];
        $options = $data['data']['options'] ?? [];
        $user_id = $data['member']['user']['id'] ?? $data['user']['id'];
        
        switch ($command) {
            case 'ventes':
                $this->handleVentesCommand($options);
                break;
                
            case 'stats':
                $this->handleStatsCommand($options);
                break;
                
            case 'menages':
                $this->handleMenagesCommand($options);
                break;
                
            case 'objectifs':
                $this->handleObjectifsCommand();
                break;
                
            case 'rapport':
                $this->handleRapportCommand($options);
                break;
                
            default:
                $this->respondError('Commande non reconnue');
        }
    }
    
    /**
     * Commande /ventes - Afficher les ventes
     */
    private function handleVentesCommand($options) {
        $periode = 'today';
        foreach ($options as $option) {
            if ($option['name'] === 'periode') {
                $periode = $option['value'];
            }
        }
        
        // Calculer les dates selon la période
        switch ($periode) {
            case 'today':
                $start_date = date('Y-m-d 00:00:00');
                $end_date = date('Y-m-d 23:59:59');
                $label = 'Aujourd\'hui';
                break;
            case 'week':
                $start_date = date('Y-m-d 00:00:00', strtotime('monday this week'));
                $end_date = date('Y-m-d 23:59:59', strtotime('sunday this week'));
                $label = 'Cette semaine';
                break;
            case 'month':
                $start_date = date('Y-m-01 00:00:00');
                $end_date = date('Y-m-t 23:59:59');
                $label = 'Ce mois';
                break;
            default:
                $start_date = date('Y-m-d 00:00:00');
                $end_date = date('Y-m-d 23:59:59');
                $label = 'Aujourd\'hui';
        }
        
        // Récupérer les statistiques de ventes
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_ventes,
                COALESCE(SUM(total_amount), 0) as chiffre_affaires,
                COALESCE(AVG(total_amount), 0) as panier_moyen
            FROM sales 
            WHERE created_at >= ? AND created_at <= ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $stats = $stmt->fetch();
        
        // Récupérer les dernières ventes
        $stmt = $this->db->prepare("
            SELECT 
                s.id,
                s.total_amount,
                u.first_name,
                u.last_name,
                c.name as customer_name,
                s.created_at
            FROM sales s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.created_at >= ? AND s.created_at <= ?
            ORDER BY s.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$start_date, $end_date]);
        $recent_sales = $stmt->fetchAll();
        
        $embed = [
            'title' => '💰 Ventes - ' . $label,
            'color' => 0x00ff00,
            'fields' => [
                [
                    'name' => '📊 Statistiques',
                    'value' => sprintf(
                        "**Nombre de ventes:** %d\n**Chiffre d'affaires:** %.2f$\n**Panier moyen:** %.2f$",
                        $stats['total_ventes'],
                        $stats['chiffre_affaires'],
                        $stats['panier_moyen']
                    ),
                    'inline' => false
                ]
            ],
            'timestamp' => date('c')
        ];
        
        // Ajouter les ventes récentes
        if (!empty($recent_sales)) {
            $recent_text = '';
            foreach ($recent_sales as $sale) {
                $customer = $sale['customer_name'] ?: 'Client anonyme';
                $recent_text .= sprintf(
                    "**#%d** - %.2f$ - %s %s - %s\n",
                    $sale['id'],
                    $sale['total_amount'],
                    $sale['first_name'],
                    $sale['last_name'],
                    $customer
                );
            }
            
            $embed['fields'][] = [
                'name' => '🕒 Ventes récentes',
                'value' => $recent_text,
                'inline' => false
            ];
        }
        
        $this->respondEmbed($embed);
    }
    
    /**
     * Commande /stats - Statistiques d'un employé
     */
    private function handleStatsCommand($options) {
        $employee_name = null;
        foreach ($options as $option) {
            if ($option['name'] === 'employe') {
                $employee_name = $option['value'];
            }
        }
        
        if (!$employee_name) {
            $this->respondError('Veuillez spécifier le nom de l\'employé');
            return;
        }
        
        // Rechercher l'employé
        $stmt = $this->db->prepare("
            SELECT id, first_name, last_name, role 
            FROM users 
            WHERE (first_name LIKE ? OR last_name LIKE ?) 
            AND status = 'active'
            LIMIT 1
        ");
        $search_term = '%' . $employee_name . '%';
        $stmt->execute([$search_term, $search_term]);
        $employee = $stmt->fetch();
        
        if (!$employee) {
            $this->respondError('Employé non trouvé');
            return;
        }
        
        // Statistiques de ventes (ce mois)
        $start_month = date('Y-m-01 00:00:00');
        $end_month = date('Y-m-t 23:59:59');
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as ventes_count,
                COALESCE(SUM(total_amount), 0) as chiffre_affaires,
                COALESCE(SUM(employee_commission), 0) as commissions
            FROM sales 
            WHERE user_id = ? AND created_at >= ? AND created_at <= ?
        ");
        $stmt->execute([$employee['id'], $start_month, $end_month]);
        $sales_stats = $stmt->fetch();
        
        // Statistiques de ménages (semaine active)
        $active_week = getActiveWeekNew();
        if ($active_week) {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as sessions_count,
                    COALESCE(SUM(cleaning_count), 0) as total_menages,
                    COALESCE(SUM(total_salary), 0) as salaire_menages
                FROM cleaning_services 
                WHERE user_id = ? AND week_id = ? AND status = 'completed'
            ");
            $stmt->execute([$employee['id'], $active_week['id']]);
            $cleaning_stats = $stmt->fetch();
        } else {
            $cleaning_stats = ['sessions_count' => 0, 'total_menages' => 0, 'salaire_menages' => 0];
        }
        
        $embed = [
            'title' => '👤 Statistiques - ' . $employee['first_name'] . ' ' . $employee['last_name'],
            'color' => 0x0099ff,
            'fields' => [
                [
                    'name' => '💼 Informations',
                    'value' => sprintf("**Rôle:** %s", $employee['role']),
                    'inline' => true
                ],
                [
                    'name' => '💰 Ventes (ce mois)',
                    'value' => sprintf(
                        "**Nombre:** %d\n**CA:** %.2f$\n**Commissions:** %.2f$",
                        $sales_stats['ventes_count'],
                        $sales_stats['chiffre_affaires'],
                        $sales_stats['commissions']
                    ),
                    'inline' => true
                ],
                [
                    'name' => '🧹 Ménages (semaine active)',
                    'value' => sprintf(
                        "**Sessions:** %d\n**Ménages:** %d\n**Salaire:** %.2f$",
                        $cleaning_stats['sessions_count'],
                        $cleaning_stats['total_menages'],
                        $cleaning_stats['salaire_menages']
                    ),
                    'inline' => true
                ]
            ],
            'timestamp' => date('c')
        ];
        
        $this->respondEmbed($embed);
    }
    
    /**
     * Commande /menages - Gestion des ménages
     */
    private function handleMenagesCommand($options) {
        $action = 'voir';
        foreach ($options as $option) {
            if ($option['name'] === 'action') {
                $action = $option['value'];
            }
        }
        
        if ($action === 'voir') {
            $this->showMenagesStats();
        } else {
            $this->respondError('Action non supportée via Discord. Utilisez l\'interface web pour ajouter des ménages.');
        }
    }
    
    /**
     * Afficher les statistiques de ménages
     */
    private function showMenagesStats() {
        $active_week = getActiveWeekNew();
        if (!$active_week) {
            $this->respondError('Aucune semaine active trouvée');
            return;
        }
        
        // Top employés ménages
        $stmt = $this->db->prepare("
            SELECT 
                u.first_name,
                u.last_name,
                COUNT(cs.id) as sessions,
                COALESCE(SUM(cs.cleaning_count), 0) as total_menages,
                COALESCE(SUM(cs.total_salary), 0) as salaire
            FROM users u
            LEFT JOIN cleaning_services cs ON u.id = cs.user_id 
                AND cs.week_id = ? AND cs.status = 'completed'
            WHERE u.status = 'active'
            GROUP BY u.id, u.first_name, u.last_name
            HAVING total_menages > 0
            ORDER BY total_menages DESC
            LIMIT 5
        ");
        $stmt->execute([$active_week['id']]);
        $top_employees = $stmt->fetchAll();
        
        $embed = [
            'title' => '🧹 Ménages - Semaine ' . $active_week['week_number'],
            'color' => 0xff9900,
            'fields' => [],
            'timestamp' => date('c')
        ];
        
        if (!empty($top_employees)) {
            $top_text = '';
            foreach ($top_employees as $emp) {
                $top_text .= sprintf(
                    "**%s %s** - %d ménages (%.2f$)\n",
                    $emp['first_name'],
                    $emp['last_name'],
                    $emp['total_menages'],
                    $emp['salaire']
                );
            }
            
            $embed['fields'][] = [
                'name' => '🏆 Top Employés',
                'value' => $top_text,
                'inline' => false
            ];
        } else {
            $embed['fields'][] = [
                'name' => '📝 Information',
                'value' => 'Aucun ménage enregistré cette semaine',
                'inline' => false
            ];
        }
        
        $this->respondEmbed($embed);
    }
    
    /**
     * Commande /objectifs - Voir les objectifs
     */
    private function handleObjectifsCommand() {
        // Récupérer les objectifs depuis les paramètres système
        $stmt = $this->db->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key LIKE '%_goal%' OR setting_key LIKE '%_target%'
        ");
        $stmt->execute();
        $settings = $stmt->fetchAll();
        
        $embed = [
            'title' => '🎯 Objectifs du Restaurant',
            'color' => 0xffd700,
            'fields' => [
                [
                    'name' => '📊 Objectifs Mensuels',
                    'value' => 'Consultez l\'interface web pour voir les objectifs détaillés',
                    'inline' => false
                ]
            ],
            'timestamp' => date('c')
        ];
        
        $this->respondEmbed($embed);
    }
    
    /**
     * Commande /rapport - Générer un rapport rapide
     */
    private function handleRapportCommand($options) {
        $periode = 'today';
        foreach ($options as $option) {
            if ($option['name'] === 'periode') {
                $periode = $option['value'];
            }
        }
        
        // Calculer les dates
        switch ($periode) {
            case 'today':
                $start_date = date('Y-m-d 00:00:00');
                $end_date = date('Y-m-d 23:59:59');
                $label = 'Aujourd\'hui';
                break;
            case 'week':
                $start_date = date('Y-m-d 00:00:00', strtotime('monday this week'));
                $end_date = date('Y-m-d 23:59:59', strtotime('sunday this week'));
                $label = 'Cette semaine';
                break;
            default:
                $start_date = date('Y-m-d 00:00:00');
                $end_date = date('Y-m-d 23:59:59');
                $label = 'Aujourd\'hui';
        }
        
        // Statistiques générales
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT s.id) as ventes,
                COALESCE(SUM(s.total_amount), 0) as ca,
                COUNT(DISTINCT s.user_id) as vendeurs_actifs,
                COUNT(DISTINCT s.customer_id) as clients
            FROM sales s
            WHERE s.created_at >= ? AND s.created_at <= ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $stats = $stmt->fetch();
        
        $embed = [
            'title' => '📈 Rapport - ' . $label,
            'color' => 0x9932cc,
            'fields' => [
                [
                    'name' => '💰 Ventes',
                    'value' => sprintf(
                        "**Nombre:** %d\n**CA:** %.2f$\n**Vendeurs actifs:** %d\n**Clients:** %d",
                        $stats['ventes'],
                        $stats['ca'],
                        $stats['vendeurs_actifs'],
                        $stats['clients']
                    ),
                    'inline' => true
                ]
            ],
            'footer' => [
                'text' => 'Le Yellowjack Bot • Rapport généré automatiquement'
            ],
            'timestamp' => date('c')
        ];
        
        $this->respondEmbed($embed);
    }
    
    /**
     * Répondre avec un embed
     */
    private function respondEmbed($embed) {
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'type' => 4,
            'data' => [
                'embeds' => [$embed]
            ]
        ]);
        exit;
    }
    
    /**
     * Répondre avec un message d'erreur
     */
    private function respondError($message) {
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'type' => 4,
            'data' => [
                'content' => '❌ ' . $message,
                'flags' => 64 // EPHEMERAL
            ]
        ]);
        exit;
    }
}

// Point d'entrée
// Vérifier si on est en mode CLI ou web
if (php_sapi_name() === 'cli') {
    // Mode CLI - ne pas traiter les requêtes HTTP
    return;
} elseif (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bot = new YellowjackDiscordBot();
    $bot->handleInteraction();
} else {
    if (!headers_sent()) {
        http_response_code(405);
    }
    echo 'Méthode non autorisée';
}
?>