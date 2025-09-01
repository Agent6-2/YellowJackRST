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
            // Charger depuis system_settings au lieu de discord_config
            $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'discord_%'");
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            $settings = [];
            foreach ($results as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $this->config = [
                'webhook_url' => $settings['discord_webhook_url'] ?? '',
                'notifications_enabled' => ($settings['discord_notifications_enabled'] ?? '0') === '1',
                'notify_sales' => ($settings['discord_notify_sales'] ?? '1') === '1',
                'notify_cleaning' => ($settings['discord_notify_cleaning'] ?? '1') === '1',
                'notify_goals' => ($settings['discord_notify_goals'] ?? '1') === '1',
                'notify_weekly_summary' => ($settings['discord_notify_weekly'] ?? '1') === '1'
            ];
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
            'notifications_enabled' => false,
            'notify_sales' => true,
            'notify_cleaning' => true,
            'notify_goals' => true,
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
     * Vérifier si les notifications de nettoyage sont activées
     */
    public function isNotifyCleaningEnabled() {
        return (bool)($this->config['notify_cleaning'] ?? false);
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
            $settings = [
                'discord_webhook_url' => $data['webhook_url'] ?? '',
                'discord_notifications_enabled' => ($data['notifications_enabled'] ?? false) ? '1' : '0',
                'discord_notify_sales' => ($data['notify_sales'] ?? false) ? '1' : '0',
                'discord_notify_cleaning' => ($data['notify_cleaning'] ?? false) ? '1' : '0',
                'discord_notify_goals' => ($data['notify_goals'] ?? false) ? '1' : '0',
                'discord_notify_weekly' => ($data['notify_weekly_summary'] ?? false) ? '1' : '0'
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $this->db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                    VALUES (?, ?, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$key, $value]);
            }
            
            $this->loadConfig(); // Recharger la configuration
            return true;
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
    return new DiscordConfig();
}

/**
 * Fonction helper pour vérifier si Discord est configuré
 */
function isDiscordConfigured() {
    $config = getDiscordConfig();
    return !empty($config->getWebhookUrl()) && $config->isNotificationsEnabled();
}
?>