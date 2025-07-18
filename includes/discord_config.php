<?php
/**
 * Gestionnaire de configuration Discord pour Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Classe pour gérer la configuration Discord
 */
class DiscordConfig {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = getDB();
        $this->loadConfig();
    }
    
    /**
     * Charger la configuration depuis la base de données
     */
    private function loadConfig() {
        try {
            $stmt = $this->db->query("SELECT * FROM discord_config ORDER BY id DESC LIMIT 1");
            $this->config = $stmt->fetch();
            
            if (!$this->config) {
                // Créer une configuration par défaut
                $this->createDefaultConfig();
            }
        } catch (Exception $e) {
            error_log("Erreur chargement config Discord: " . $e->getMessage());
            $this->config = $this->getDefaultConfig();
        }
    }
    
    /**
     * Créer une configuration par défaut
     */
    private function createDefaultConfig() {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO discord_config 
                (webhook_url, notifications_enabled, notify_sales, notify_goals, notify_errors, notify_weekly_summary) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(['', true, true, true, true, true]);
            $this->loadConfig();
        } catch (Exception $e) {
            error_log("Erreur création config Discord: " . $e->getMessage());
            $this->config = $this->getDefaultConfig();
        }
    }
    
    /**
     * Configuration par défaut en cas d'erreur
     */
    private function getDefaultConfig() {
        return [
            'webhook_url' => '',
            'notifications_enabled' => true,
            'notify_sales' => true,
            'notify_goals' => true,
            'notify_errors' => true,
            'notify_weekly_summary' => true
        ];
    }
    
    /**
     * Obtenir l'URL du webhook
     */
    public function getWebhookUrl() {
        return $this->config['webhook_url'] ?? '';
    }
    
    /**
     * Vérifier si les notifications sont activées
     */
    public function isNotificationsEnabled() {
        return (bool)($this->config['notifications_enabled'] ?? false);
    }
    
    /**
     * Vérifier si les notifications de vente sont activées
     */
    public function isNotifySalesEnabled() {
        return (bool)($this->config['notify_sales'] ?? false);
    }
    
    /**
     * Vérifier si les notifications d'objectifs sont activées
     */
    public function isNotifyGoalsEnabled() {
        return (bool)($this->config['notify_goals'] ?? false);
    }
    
    /**
     * Vérifier si les notifications d'erreurs sont activées
     */
    public function isNotifyErrorsEnabled() {
        return (bool)($this->config['notify_errors'] ?? false);
    }
    
    /**
     * Vérifier si les résumés hebdomadaires sont activés
     */
    public function isNotifyWeeklySummaryEnabled() {
        return (bool)($this->config['notify_weekly_summary'] ?? false);
    }
    
    /**
     * Obtenir toute la configuration
     */
    public function getConfig() {
        return $this->config;
    }
    
    /**
     * Sauvegarder la configuration
     */
    public function saveConfig($data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE discord_config SET 
                webhook_url = ?,
                notifications_enabled = ?,
                notify_sales = ?,
                notify_goals = ?,
                notify_errors = ?,
                notify_weekly_summary = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data['webhook_url'] ?? '',
                (bool)($data['notifications_enabled'] ?? false),
                (bool)($data['notify_sales'] ?? false),
                (bool)($data['notify_goals'] ?? false),
                (bool)($data['notify_errors'] ?? false),
                (bool)($data['notify_weekly_summary'] ?? false),
                $this->config['id']
            ]);
            
            if ($result) {
                $this->loadConfig(); // Recharger la configuration
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Erreur sauvegarde config Discord: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tester le webhook
     */
    public function testWebhook($webhook_url = null) {
        $url = $webhook_url ?: $this->getWebhookUrl();
        
        if (empty($url)) {
            return false;
        }
        
        try {
            $data = [
                'content' => '🧪 Test de connexion depuis Le Yellowjack ! Configuration réussie.',
                'username' => 'Le Yellowjack Bot'
            ];
            
            $json_data = json_encode($data);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_data)
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return ($http_code >= 200 && $http_code < 300);
        } catch (Exception $e) {
            error_log("Erreur test webhook Discord: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Fonction helper pour obtenir la configuration Discord
 */
function getDiscordConfig() {
    static $instance = null;
    if ($instance === null) {
        $instance = new DiscordConfig();
    }
    return $instance;
}

/**
 * Fonction helper pour vérifier si Discord est configuré
 */
function isDiscordConfigured() {
    $config = getDiscordConfig();
    return !empty($config->getWebhookUrl()) && $config->isNotificationsEnabled();
}
?>