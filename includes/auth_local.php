<?php
/**
 * Authentification simplifiée pour tests locaux
 */

session_start();

/**
 * Simuler un utilisateur connecté pour les tests
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        // Simuler une connexion automatique pour les tests
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['role'] = 'Patron';
        $_SESSION['first_name'] = 'Admin';
        $_SESSION['last_name'] = 'Test';
    }
}

/**
 * Vérifier les permissions (simplifié)
 */
function requirePermission($permission) {
    requireLogin();
    // Pour les tests, on autorise tout
    return true;
}

/**
 * Obtenir l'objet auth simulé
 */
function getAuth() {
    return new class {
        public function getCurrentUser() {
            return [
                'id' => 1,
                'username' => 'admin',
                'first_name' => 'Admin',
                'last_name' => 'Test',
                'role' => 'Patron'
            ];
        }
        
        public function canAccessCashRegister() {
            return true;
        }
        
        public function canManageEmployees() {
            return true;
        }
    };
}

/**
 * Valider le token CSRF
 */
function validateCSRF($token) {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return isset($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Générer un token CSRF
 */
function generateCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
?>