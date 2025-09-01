<?php
/**
 * Système de webhook Discord pour Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Classe pour gérer les webhooks Discord
 */
class DiscordWebhook {
    private $webhook_url;
    
    public function __construct($webhook_url = null) {
        $this->webhook_url = $webhook_url ?: DISCORD_WEBHOOK_URL;
    }
    
    /**
     * Envoyer un message simple
     */
    public function sendMessage($content, $username = 'Le Yellowjack Bot') {
        if (empty($this->webhook_url)) {
            error_log('Discord webhook URL non configurée');
            return false;
        }
        
        $data = [
            'content' => $content,
            'username' => $username
        ];
        
        return $this->sendWebhook($data);
    }
    
    /**
     * Envoyer un embed riche
     */
    public function sendEmbed($title, $description, $color = 0x00ff00, $fields = []) {
        if (empty($this->webhook_url)) {
            error_log('Discord webhook URL non configurée');
            return false;
        }
        
        $embed = [
            'title' => $title,
            'description' => $description,
            'color' => $color,
            'timestamp' => date('c'),
            'footer' => [
                'text' => 'Le Yellowjack System'
            ]
        ];
        
        if (!empty($fields)) {
            $embed['fields'] = $fields;
        }
        
        $data = [
            'username' => 'Le Yellowjack Bot',
            'embeds' => [$embed]
        ];
        
        return $this->sendWebhook($data);
    }
    
    /**
     * Notifier une nouvelle vente
     */
    public function notifySale($sale_data) {
        $title = "💰 Nouvelle Vente";
        $description = "Une nouvelle vente a été enregistrée !";
        
        $fields = [
            [
                'name' => '🎫 Ticket',
                'value' => "#" . $sale_data['id'],
                'inline' => true
            ],
            [
                'name' => '👤 Vendeur',
                'value' => $sale_data['employee_name'],
                'inline' => true
            ],
            [
                'name' => '💵 Montant',
                'value' => number_format($sale_data['final_amount'], 2) . "$",
                'inline' => true
            ]
        ];
        
        if (!empty($sale_data['customer_name'])) {
            $fields[] = [
                'name' => '🛒 Client',
                'value' => $sale_data['customer_name'],
                'inline' => true
            ];
        }
        
        if ($sale_data['discount_amount'] > 0) {
            $fields[] = [
                'name' => '🎯 Réduction',
                'value' => "-" . number_format($sale_data['discount_amount'], 2) . "$",
                'inline' => true
            ];
        }
        
        $fields[] = [
            'name' => '💼 Commission',
            'value' => number_format($sale_data['employee_commission'], 2) . "$",
            'inline' => true
        ];
        
        return $this->sendEmbed($title, $description, 0x00ff00, $fields);
    }
    
    /**
     * Notifier un objectif atteint
     */
    public function notifyGoalAchieved($employee_name, $goal_type, $amount) {
        $title = "🎯 Objectif Atteint !";
        $description = "Félicitations ! Un objectif a été atteint.";
        
        $fields = [
            [
                'name' => '👤 Employé',
                'value' => $employee_name,
                'inline' => true
            ],
            [
                'name' => '🎯 Type d\'objectif',
                'value' => $goal_type,
                'inline' => true
            ],
            [
                'name' => '💰 Montant',
                'value' => number_format($amount, 2) . "$",
                'inline' => true
            ]
        ];
        
        return $this->sendEmbed($title, $description, 0xffd700, $fields);
    }
    
    /**
     * Notifier une erreur système
     */
    public function notifyError($error_message, $context = '') {
        $title = "⚠️ Erreur Système";
        $description = "Une erreur s'est produite dans le système.";
        
        $fields = [
            [
                'name' => '❌ Erreur',
                'value' => substr($error_message, 0, 1000),
                'inline' => false
            ]
        ];
        
        if (!empty($context)) {
            $fields[] = [
                'name' => '📍 Contexte',
                'value' => substr($context, 0, 1000),
                'inline' => false
            ];
        }
        
        return $this->sendEmbed($title, $description, 0xff0000, $fields);
    }
    
    /**
     * Notifier la fin d'un service de ménage
     */
    public function notifyCleaningServiceCompleted($employee_name, $cleaning_count, $duration_minutes, $salary) {
        $hours = floor($duration_minutes / 60);
        $minutes = $duration_minutes % 60;
        $duration_text = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
        
        $title = "🧹 Service de Ménage Terminé";
        $description = "Un service de ménage vient d'être complété";
        
        $fields = [
            [
                'name' => '👤 Employé',
                'value' => $employee_name,
                'inline' => true
            ],
            [
                'name' => '🏠 Ménages effectués',
                'value' => $cleaning_count,
                'inline' => true
            ],
            [
                'name' => '⏱️ Durée',
                'value' => $duration_text,
                'inline' => true
            ],
            [
                'name' => '💰 Salaire gagné',
                'value' => number_format($salary, 2) . '$',
                'inline' => true
            ],
            [
                'name' => '📅 Date',
                'value' => date('d/m/Y H:i'),
                'inline' => true
            ]
        ];
        
        return $this->sendEmbed($title, $description, 0x28A745, $fields);
    }
    
    /**
     * Notifier un résumé hebdomadaire
     */
    public function notifyWeeklySummary($week_number, $week_start, $week_end, $stats) {
        $title = "📊 Résumé Hebdomadaire - Semaine " . $week_number;
        $description = "Récapitulatif des performances de la semaine finalisée";
        
        $fields = [
            [
                'name' => '📅 Période',
                'value' => date('d/m/Y', strtotime($week_start)) . ' - ' . date('d/m/Y', strtotime($week_end)),
                'inline' => false
            ],
            [
                'name' => '💰 Chiffre d\'affaires total',
                'value' => number_format($stats['total_revenue'], 2) . '$',
                'inline' => true
            ],
            [
                'name' => '🛒 Nombre de ventes',
                'value' => $stats['total_sales_count'],
                'inline' => true
            ],
            [
                'name' => '🧹 Services de ménage',
                'value' => $stats['total_cleaning_count'],
                'inline' => true
            ],
            [
                'name' => '💵 Revenus ménage',
                'value' => number_format($stats['total_cleaning_revenue'], 2) . '$',
                'inline' => true
            ],
            [
                'name' => '📈 Revenus ventes',
                'value' => number_format($stats['total_sales_revenue'], 2) . '$',
                'inline' => true
            ],
            [
                'name' => '🏆 Statut',
                'value' => 'Semaine finalisée',
                'inline' => true
            ]
        ];
        
        return $this->sendEmbed($title, $description, 0x007BFF, $fields);
    }
    
    /**
     * Envoyer le webhook
     */
    private function sendWebhook($data) {
        try {
            $json_data = json_encode($data);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->webhook_url);
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
            
            if ($http_code >= 200 && $http_code < 300) {
                return true;
            } else {
                error_log("Discord webhook failed: HTTP $http_code - $response");
                return false;
            }
        } catch (Exception $e) {
            error_log("Discord webhook error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Fonction helper pour obtenir une instance du webhook Discord
 */
function getDiscordWebhook($webhook_url = null) {
    return new DiscordWebhook($webhook_url);
}

/**
 * Fonction helper pour envoyer rapidement une notification de vente
 */
function notifyDiscordSale($sale_id) {
    try {
        $db = getDB();
        
        // Récupérer les détails de la vente
        $stmt = $db->prepare("
            SELECT 
                s.*,
                u.first_name,
                u.last_name,
                c.name as customer_name
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch();
        
        if ($sale) {
            $sale_data = [
                'id' => $sale['id'],
                'employee_name' => $sale['first_name'] . ' ' . $sale['last_name'],
                'customer_name' => $sale['customer_name'] ?: 'Client anonyme',
                'final_amount' => $sale['final_amount'],
                'discount_amount' => $sale['discount_amount'],
                'employee_commission' => $sale['employee_commission']
            ];
            
            $webhook = getDiscordWebhook();
            return $webhook->notifySale($sale_data);
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Erreur notification Discord: " . $e->getMessage());
        return false;
    }
}
?>