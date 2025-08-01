<?php
/**
 * Bot Discord persistant pour Le Yellowjack
 * 
 * Ce bot fonctionne en mode daemon et écoute les événements Discord
 * via WebSocket Gateway
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/week_functions.php';
require_once __DIR__ . '/../includes/discord_webhook.php';

/**
 * Classe du bot Discord persistant
 */
class YellowjackDiscordDaemon {
    private $bot_token;
    private $db;
    private $gateway_url;
    private $session_id;
    private $sequence;
    private $heartbeat_interval;
    private $websocket;
    private $running = true;
    
    public function __construct() {
        $this->bot_token = DISCORD_BOT_TOKEN ?? '';
        $this->db = getDB();
        $this->sequence = null;
        
        if (empty($this->bot_token)) {
            throw new Exception('DISCORD_BOT_TOKEN non configuré');
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Bot Discord démarré\n";
    }
    
    /**
     * Démarrer le bot
     */
    public function start() {
        try {
            $this->getGatewayUrl();
            $this->connectWebSocket();
            $this->listen();
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Erreur: " . $e->getMessage() . "\n";
            $this->stop();
        }
    }
    
    /**
     * Arrêter le bot
     */
    public function stop() {
        $this->running = false;
        if ($this->websocket) {
            fclose($this->websocket);
        }
        echo "[" . date('Y-m-d H:i:s') . "] Bot Discord arrêté\n";
    }
    
    /**
     * Obtenir l'URL du gateway Discord
     */
    private function getGatewayUrl() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://discord.com/api/v10/gateway/bot');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bot ' . $this->bot_token,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Impossible d\'obtenir l\'URL du gateway: ' . $response);
        }
        
        $data = json_decode($response, true);
        $this->gateway_url = $data['url'] . '/?v=10&encoding=json';
        
        echo "[" . date('Y-m-d H:i:s') . "] Gateway URL obtenue: {$this->gateway_url}\n";
    }
    
    /**
     * Se connecter au WebSocket Discord
     */
    private function connectWebSocket() {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $this->websocket = stream_socket_client(
            str_replace(['wss://', 'ws://'], ['ssl://', 'tcp://'], $this->gateway_url),
            $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context
        );
        
        if (!$this->websocket) {
            throw new Exception("Impossible de se connecter au WebSocket: $errstr ($errno)");
        }
        
        // Envoyer l'en-tête WebSocket
        $key = base64_encode(random_bytes(16));
        $headers = [
            "GET {$this->gateway_url} HTTP/1.1",
            "Host: gateway.discord.gg",
            "Upgrade: websocket",
            "Connection: Upgrade",
            "Sec-WebSocket-Key: $key",
            "Sec-WebSocket-Version: 13"
        ];
        
        fwrite($this->websocket, implode("\r\n", $headers) . "\r\n\r\n");
        
        // Lire la réponse d'upgrade
        $response = '';
        while (($line = fgets($this->websocket)) !== false) {
            $response .= $line;
            if (trim($line) === '') break;
        }
        
        if (strpos($response, '101 Switching Protocols') === false) {
            throw new Exception('Échec de l\'upgrade WebSocket: ' . $response);
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Connexion WebSocket établie\n";
    }
    
    /**
     * Écouter les événements Discord
     */
    private function listen() {
        while ($this->running) {
            $frame = $this->readWebSocketFrame();
            if ($frame === false) {
                echo "[" . date('Y-m-d H:i:s') . "] Connexion fermée\n";
                break;
            }
            
            $data = json_decode($frame, true);
            if ($data) {
                $this->handleEvent($data);
            }
        }
    }
    
    /**
     * Lire une frame WebSocket
     */
    private function readWebSocketFrame() {
        $header = fread($this->websocket, 2);
        if (strlen($header) < 2) return false;
        
        $firstByte = ord($header[0]);
        $secondByte = ord($header[1]);
        
        $opcode = $firstByte & 0x0F;
        $masked = ($secondByte & 0x80) === 0x80;
        $payloadLength = $secondByte & 0x7F;
        
        if ($payloadLength === 126) {
            $extendedLength = fread($this->websocket, 2);
            $payloadLength = unpack('n', $extendedLength)[1];
        } elseif ($payloadLength === 127) {
            $extendedLength = fread($this->websocket, 8);
            $payloadLength = unpack('J', $extendedLength)[1];
        }
        
        $maskingKey = $masked ? fread($this->websocket, 4) : '';
        $payload = fread($this->websocket, $payloadLength);
        
        if ($masked) {
            for ($i = 0; $i < $payloadLength; $i++) {
                $payload[$i] = chr(ord($payload[$i]) ^ ord($maskingKey[$i % 4]));
            }
        }
        
        return $payload;
    }
    
    /**
     * Envoyer une frame WebSocket
     */
    private function sendWebSocketFrame($payload) {
        $payloadLength = strlen($payload);
        $frame = chr(0x81); // FIN + opcode text
        
        if ($payloadLength < 126) {
            $frame .= chr($payloadLength | 0x80); // MASK bit set
        } elseif ($payloadLength < 65536) {
            $frame .= chr(126 | 0x80) . pack('n', $payloadLength);
        } else {
            $frame .= chr(127 | 0x80) . pack('J', $payloadLength);
        }
        
        $mask = random_bytes(4);
        $frame .= $mask;
        
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }
        
        return fwrite($this->websocket, $frame);
    }
    
    /**
     * Gérer les événements Discord
     */
    private function handleEvent($data) {
        $this->sequence = $data['s'] ?? $this->sequence;
        
        switch ($data['op']) {
            case 10: // Hello
                $this->heartbeat_interval = $data['d']['heartbeat_interval'];
                $this->identify();
                $this->startHeartbeat();
                echo "[" . date('Y-m-d H:i:s') . "] Bot identifié et heartbeat démarré\n";
                break;
                
            case 11: // Heartbeat ACK
                // Heartbeat acknowledgé
                break;
                
            case 0: // Dispatch
                $this->handleDispatchEvent($data['t'], $data['d']);
                break;
                
            case 1: // Heartbeat
                $this->sendHeartbeat();
                break;
                
            case 7: // Reconnect
                echo "[" . date('Y-m-d H:i:s') . "] Reconnexion demandée\n";
                $this->stop();
                sleep(1);
                $this->start();
                break;
                
            case 9: // Invalid Session
                echo "[" . date('Y-m-d H:i:s') . "] Session invalide, reconnexion...\n";
                $this->session_id = null;
                sleep(5);
                $this->identify();
                break;
        }
    }
    
    /**
     * S'identifier auprès de Discord
     */
    private function identify() {
        $payload = [
            'op' => 2,
            'd' => [
                'token' => $this->bot_token,
                'intents' => 513, // GUILDS + GUILD_MESSAGES
                'properties' => [
                    'os' => 'windows',
                    'browser' => 'yellowjack-bot',
                    'device' => 'yellowjack-bot'
                ]
            ]
        ];
        
        $this->sendWebSocketFrame(json_encode($payload));
    }
    
    /**
     * Envoyer un heartbeat
     */
    private function sendHeartbeat() {
        $payload = [
            'op' => 1,
            'd' => $this->sequence
        ];
        
        $this->sendWebSocketFrame(json_encode($payload));
    }
    
    /**
     * Démarrer le heartbeat en arrière-plan
     */
    private function startHeartbeat() {
        // Note: Dans un vrai bot, il faudrait utiliser des processus séparés
        // ou des threads pour le heartbeat. Ici on simplifie.
    }
    
    /**
     * Gérer les événements de dispatch
     */
    private function handleDispatchEvent($eventType, $data) {
        switch ($eventType) {
            case 'READY':
                $this->session_id = $data['session_id'];
                echo "[" . date('Y-m-d H:i:s') . "] Bot prêt! Session ID: {$this->session_id}\n";
                break;
                
            case 'INTERACTION_CREATE':
                $this->handleInteraction($data);
                break;
                
            case 'MESSAGE_CREATE':
                // Gérer les messages si nécessaire
                break;
        }
    }
    
    /**
     * Gérer une interaction (commande slash)
     */
    private function handleInteraction($interaction) {
        echo "[" . date('Y-m-d H:i:s') . "] Interaction reçue: {$interaction['data']['name']}\n";
        
        // Utiliser la logique existante du webhook
        $bot = new YellowjackDiscordBot();
        
        // Simuler l'environnement HTTP pour la compatibilité
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Capturer la réponse
        ob_start();
        $bot->handleInteraction();
        $response = ob_get_clean();
        
        // Envoyer la réponse via l'API Discord
        $this->sendInteractionResponse($interaction['id'], $interaction['token'], $response);
    }
    
    /**
     * Envoyer une réponse d'interaction
     */
    private function sendInteractionResponse($interactionId, $token, $response) {
        $url = "https://discord.com/api/v10/interactions/{$interactionId}/{$token}/callback";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 && $httpCode !== 204) {
            echo "[" . date('Y-m-d H:i:s') . "] Erreur envoi réponse: $httpCode - $result\n";
        }
    }
}

// Point d'entrée
if (php_sapi_name() === 'cli') {
    // Mode CLI - démarrer le daemon
    $daemon = new YellowjackDiscordDaemon();
    
    // Gérer l'arrêt propre
    pcntl_signal(SIGTERM, function() use ($daemon) {
        $daemon->stop();
    });
    
    pcntl_signal(SIGINT, function() use ($daemon) {
        $daemon->stop();
    });
    
    $daemon->start();
} else {
    echo "Ce script doit être exécuté en ligne de commande\n";
}
?>