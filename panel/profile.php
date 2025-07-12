<?php
/**
 * Profil utilisateur - Panel Employé Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../includes/auth.php';
require_once '../config/database.php';

// Vérifier l'authentification
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

$message = '';
$error = '';

// Vérifier s'il y a un message de succès en session
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'])) {
        $error = 'Token de sécurité invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_profile':
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $discord_id = trim($_POST['discord_id'] ?? '');
                
                if (empty($first_name) || empty($last_name) || empty($email)) {
                    $error = 'Les champs prénom, nom et email sont obligatoires.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'L\'adresse email n\'est pas valide.';
                } else {
                    try {
                        // Vérifier si l'email est déjà utilisé par un autre utilisateur
                        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $stmt->execute([$email, $user['id']]);
                        if ($stmt->fetch()) {
                            $error = 'Cette adresse email est déjà utilisée par un autre utilisateur.';
                        } else {
                            $stmt = $db->prepare("
                                UPDATE users 
                                SET first_name = ?, last_name = ?, email = ?, discord_id = ?, updated_at = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$first_name, $last_name, $email, $discord_id, getCurrentDateTime(), $user['id']]);
                            
                            $_SESSION['success_message'] = 'Profil mis à jour avec succès !';
                            header('Location: profile.php');
                            exit;
                        }
                    } catch (Exception $e) {
                        $error = 'Erreur lors de la mise à jour du profil.';
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error = 'Tous les champs de mot de passe sont obligatoires.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'La confirmation du nouveau mot de passe ne correspond pas.';
                } elseif (strlen($new_password) < 6) {
                    $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
                } else {
                    try {
                        // Vérifier le mot de passe actuel
                        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        $stored_hash = $stmt->fetchColumn();
                        
                        if (!password_verify($current_password, $stored_hash)) {
                            $error = 'Le mot de passe actuel est incorrect.';
                        } else {
                            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?");
                            $stmt->execute([$new_hash, getCurrentDateTime(), $user['id']]);
                            
                            $_SESSION['success_message'] = 'Mot de passe modifié avec succès !';
                            header('Location: profile.php');
                            exit;
                        }
                    } catch (Exception $e) {
                        $error = 'Erreur lors de la modification du mot de passe.';
                    }
                }
                break;
        }
    }
}

// Récupérer les informations actuelles de l'utilisateur
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les statistiques de l'utilisateur
$stats_query = "
    SELECT 
        COUNT(DISTINCT s.id) as total_sales,
        COALESCE(SUM(s.final_amount), 0) as total_revenue,
        COALESCE(SUM(s.employee_commission), 0) as total_commission,
        COUNT(DISTINCT cs.id) as total_cleanings,
        COALESCE(SUM(cs.total_salary), 0) as total_cleaning_salary,
        COALESCE(SUM(b.amount), 0) as total_bonuses
    FROM users u
    LEFT JOIN sales s ON u.id = s.user_id
    LEFT JOIN cleaning_services cs ON u.id = cs.user_id AND cs.status = 'completed'
    LEFT JOIN bonuses b ON u.id = b.user_id
    WHERE u.id = ?
    GROUP BY u.id
";
$stmt = $db->prepare($stats_query);
$stmt->execute([$user['id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Si aucune statistique, initialiser avec des valeurs par défaut
if (!$stats) {
    $stats = [
        'total_sales' => 0,
        'total_revenue' => 0,
        'total_commission' => 0,
        'total_cleanings' => 0,
        'total_cleaning_salary' => 0,
        'total_bonuses' => 0
    ];
}

// Récupérer les dernières activités
$recent_sales_query = "
    SELECT s.*, c.name as customer_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
    LIMIT 5
";
$stmt = $db->prepare($recent_sales_query);
$stmt->execute([$user['id']]);
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recent_bonuses_query = "
    SELECT b.*, u.first_name, u.last_name
    FROM bonuses b
    LEFT JOIN users u ON b.given_by = u.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
    LIMIT 5
";
$stmt = $db->prepare($recent_bonuses_query);
$stmt->execute([$user['id']]);
$recent_bonuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Le Yellowjack</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- CSS personnalisé -->
    <link href="../assets/css/panel.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Contenu principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Header -->
                <?php include 'includes/header.php'; ?>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-circle me-2"></i>
                        Mon Profil
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-<?php echo $current_user['status'] === 'active' ? 'success' : 'danger'; ?> fs-6">
                            <i class="fas fa-circle me-1"></i>
                            <?php echo ucfirst($current_user['status']); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Informations personnelles -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user me-2"></i>
                                    Informations Personnelles
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="first_name" class="form-label">Prénom *</label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?php echo htmlspecialchars($current_user['first_name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="last_name" class="form-label">Nom *</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?php echo htmlspecialchars($current_user['last_name']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="discord_id" class="form-label">Discord ID</label>
                                        <input type="text" class="form-control" id="discord_id" name="discord_id" 
                                               value="<?php echo htmlspecialchars($current_user['discord_id'] ?? ''); ?>" 
                                               placeholder="Votre ID Discord (optionnel)">
                                        <div class="form-text">Utilisé pour les notifications Discord</div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Nom d'utilisateur</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['username']); ?>" readonly>
                                                <div class="form-text">Le nom d'utilisateur ne peut pas être modifié</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Rôle</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['role']); ?>" readonly>
                                                <div class="form-text">Seul un administrateur peut modifier votre rôle</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Date d'embauche</label>
                                                <input type="text" class="form-control" value="<?php echo date('d/m/Y', strtotime($current_user['hire_date'])); ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Membre depuis</label>
                                                <input type="text" class="form-control" value="<?php echo date('d/m/Y à H:i', strtotime($current_user['created_at'])); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>
                                            Mettre à jour le profil
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Changer le mot de passe -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-lock me-2"></i>
                                    Changer le Mot de Passe
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_password">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Mot de passe actuel *</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">Nouveau mot de passe *</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                                       minlength="6" required>
                                                <div class="form-text">Minimum 6 caractères</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe *</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                                       minlength="6" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-key me-1"></i>
                                            Changer le mot de passe
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistiques et activités -->
                    <div class="col-lg-4">
                        <!-- Statistiques personnelles -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Mes Statistiques
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <div class="border-end">
                                            <h4 class="text-primary mb-1"><?php echo number_format($stats['total_sales']); ?></h4>
                                            <small class="text-muted">Ventes</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h4 class="text-success mb-1"><?php echo number_format($stats['total_revenue'], 2); ?>$</h4>
                                        <small class="text-muted">Chiffre d'affaires</small>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border-end">
                                            <h4 class="text-info mb-1"><?php echo number_format($stats['total_commission'], 2); ?>$</h4>
                                            <small class="text-muted">Commissions</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h4 class="text-warning mb-1"><?php echo number_format($stats['total_bonuses'], 2); ?>$</h4>
                                        <small class="text-muted">Primes</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="border-end">
                                            <h4 class="text-secondary mb-1"><?php echo number_format($stats['total_cleanings']); ?></h4>
                                            <small class="text-muted">Ménages</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-dark mb-1"><?php echo number_format($stats['total_cleaning_salary'], 2); ?>$</h4>
                                        <small class="text-muted">Salaire ménage</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dernières ventes -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-shopping-cart me-2"></i>
                                    Dernières Ventes
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($recent_sales)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_sales as $sale): ?>
                                            <div class="list-group-item px-0 py-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <small class="text-muted"><?php echo date('d/m H:i', strtotime($sale['created_at'])); ?></small>
                                                        <br>
                                                        <span class="fw-bold"><?php echo number_format($sale['final_amount'], 2); ?>$</span>
                                                        <?php if ($sale['customer_name']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($sale['customer_name']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="badge bg-success"><?php echo number_format($sale['employee_commission'], 2); ?>$</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center mb-0">Aucune vente récente</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Dernières primes -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-gift me-2"></i>
                                    Dernières Primes
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($recent_bonuses)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_bonuses as $bonus): ?>
                                            <div class="list-group-item px-0 py-2">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <small class="text-muted"><?php echo date('d/m H:i', strtotime($bonus['created_at'])); ?></small>
                                                        <br>
                                                        <span class="fw-bold text-success"><?php echo number_format($bonus['amount'], 2); ?>$</span>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($bonus['reason']); ?></small>
                                                        <?php if ($bonus['first_name'] && $bonus['last_name']): ?>
                                                            <br><small class="text-muted">Par: <?php echo htmlspecialchars($bonus['first_name'] . ' ' . $bonus['last_name']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center mb-0">Aucune prime récente</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validation du formulaire de changement de mot de passe
        document.querySelector('form[action*="change_password"]').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('La confirmation du mot de passe ne correspond pas.');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 6 caractères.');
                return false;
            }
        });
    </script>
</body>
</html>