<?php
/**
 * Système d'authentification pour Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Classe de gestion de l'authentification
 */
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
        $this->startSession();
    }
    
    /**
     * Démarrer la session sécurisée
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }
    
    /**
     * Connexion utilisateur
     */
    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, password_hash, first_name, last_name, role, status 
                FROM users 
                WHERE (username = ? OR email = ?) AND status = 'active'
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Régénérer l'ID de session pour la sécurité
                session_regenerate_id(true);
                
                // Stocker les informations utilisateur en session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log('Erreur de connexion : ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Déconnexion utilisateur
     */
    public function logout() {
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Vérifier si l'utilisateur est connecté
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        // Vérifier l'expiration de la session
        if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
            $this->logout();
            return false;
        }
        
        // Mettre à jour l'activité
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Obtenir l'utilisateur actuel
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'first_name' => $_SESSION['first_name'],
            'last_name' => $_SESSION['last_name'],
            'role' => $_SESSION['role'],
            'full_name' => $_SESSION['first_name'] . ' ' . $_SESSION['last_name']
        ];
    }
    
    /**
     * Vérifier les permissions selon le rôle
     */
    public function hasPermission($required_role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $user_role = $_SESSION['role'];
        $roles_hierarchy = [
            'CDD' => 1,
            'CDI' => 2,
            'Responsable' => 3,
            'Patron' => 4
        ];
        
        $user_level = $roles_hierarchy[$user_role] ?? 0;
        $required_level = $roles_hierarchy[$required_role] ?? 0;
        
        return $user_level >= $required_level;
    }
    
    /**
     * Vérifier si l'utilisateur peut accéder à la caisse
     */
    public function canAccessCashRegister() {
        return $this->hasPermission('CDI');
    }
    
    /**
     * Vérifier si l'utilisateur peut gérer les employés
     */
    public function canManageEmployees() {
        return $this->hasPermission('Responsable');
    }
    
    /**
     * Vérifier si l'utilisateur peut gérer les stocks
     */
    public function canManageStock() {
        return $this->hasPermission('Responsable');
    }
    
    /**
     * Créer un nouvel utilisateur (hash du mot de passe)
     */
    public function createUser($data) {
        if (!$this->hasPermission('Responsable')) {
            return false;
        }
        
        try {
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, first_name, last_name, role, hire_date, discord_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $data['username'],
                $data['email'],
                $password_hash,
                $data['first_name'],
                $data['last_name'],
                $data['role'],
                $data['hire_date'],
                $data['discord_id'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log('Erreur création utilisateur : ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Suspendre/réactiver un utilisateur
     */
    public function toggleUserStatus($user_id, $status) {
        if (!$this->hasPermission('Responsable')) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $user_id]);
        } catch (PDOException $e) {
            error_log('Erreur modification statut : ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Fonction helper pour obtenir l'instance d'authentification
 */
function getAuth() {
    static $auth = null;
    if ($auth === null) {
        $auth = new Auth();
    }
    return $auth;
}

/**
 * Middleware de protection des pages
 */
function requireLogin() {
    $auth = getAuth();
    if (!$auth->isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Middleware de vérification des permissions
 */
function requirePermission($role) {
    $auth = getAuth();
    if (!$auth->hasPermission($role)) {
        header('HTTP/1.1 403 Forbidden');
        die('Accès refusé. Permissions insuffisantes.');
    }
}

/**
 * Protection CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Fonctions wrapper pour compatibilité
function generateCSRF() {
    return generateCSRFToken();
}

function validateCSRF($token) {
    return validateCSRFToken($token);
}

// Instance globale de Auth
$auth = new Auth();

// Fonctions wrapper globales
function isLoggedIn() {
    global $auth;
    return $auth->isLoggedIn();
}

function logout() {
    global $auth;
    return $auth->logout();
}
?>