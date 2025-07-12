<?php
/**
 * Configuration et Paramètres - Panel Employé Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../includes/auth.php';
require_once '../config/database.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePermission('manage_employees');

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

$message = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'])) {
        $error = 'Token de sécurité invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_general':
                $bar_name = trim($_POST['bar_name'] ?? '');
                $bar_address = trim($_POST['bar_address'] ?? '');
                $cleaning_rate = floatval($_POST['cleaning_rate'] ?? 0);
                $commission_rate = floatval($_POST['commission_rate'] ?? 0);
                
                if (empty($bar_name)) {
                    $error = 'Le nom du bar est obligatoire.';
                } elseif ($cleaning_rate <= 0 || $commission_rate <= 0 || $commission_rate > 100) {
                    $error = 'Les taux doivent être valides (ménage > 0, commission entre 0 et 100).';
                } else {
                    try {
                        $db->beginTransaction();
                        
                        // Mettre à jour les paramètres
                        $settings = [
                            'bar_name' => $bar_name,
                            'bar_address' => $bar_address,
                            'cleaning_rate' => $cleaning_rate,
                            'commission_rate' => $commission_rate
                        ];
                        
                        foreach ($settings as $key => $value) {
                            $stmt = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                            $stmt->execute([$value, $key]);
                        }
                        
                        $db->commit();
                        $message = 'Paramètres généraux mis à jour avec succès !';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Erreur lors de la mise à jour des paramètres.';
                    }
                }
                break;
                
            case 'update_discord':
                $discord_webhook = trim($_POST['discord_webhook'] ?? '');
                
                if (!empty($discord_webhook) && !filter_var($discord_webhook, FILTER_VALIDATE_URL)) {
                    $error = 'L\'URL du webhook Discord n\'est pas valide.';
                } else {
                    try {
                        $stmt = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'discord_webhook_url'");
                        $stmt->execute([$discord_webhook]);
                        $message = 'Configuration Discord mise à jour avec succès !';
                    } catch (Exception $e) {
                        $error = 'Erreur lors de la mise à jour de la configuration Discord.';
                    }
                }
                break;
                
            case 'test_discord':
                $discord_webhook = trim($_POST['discord_webhook'] ?? '');
                
                if (empty($discord_webhook)) {
                    $error = 'Veuillez d\'abord configurer l\'URL du webhook Discord.';
                } else {
                    // Test du webhook Discord
                    $test_data = [
                        'content' => '🧪 **Test de connexion**',
                        'embeds' => [[
                            'title' => '🤠 Le Yellowjack - Test Système',
                            'description' => 'Test de connexion du webhook Discord depuis le panel d\'administration.',
                            'color' => 0xFFD700,
                            'fields' => [
                                [
                                    'name' => '👤 Testé par',
                                    'value' => $user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['role'] . ')',
                                    'inline' => true
                                ],
                                [
                                    'name' => '📅 Date',
                                    'value' => formatDate(getCurrentDateTime()),
                                    'inline' => true
                                ]
                            ],
                            'footer' => [
                                'text' => 'Le Yellowjack - Panel Administration'
                            ],
                            'timestamp' => date('c')
                        ]]
                    ];
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $discord_webhook);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($http_code >= 200 && $http_code < 300) {
                        $message = 'Test Discord réussi ! Le message a été envoyé.';
                    } else {
                        $error = 'Échec du test Discord. Vérifiez l\'URL du webhook.';
                    }
                }
                break;
                
            case 'backup_database':
                // Cette fonctionnalité nécessiterait des permissions spéciales sur le serveur
                $message = 'Fonctionnalité de sauvegarde en cours de développement.';
                break;
        }
    }
}

// Récupérer les paramètres actuels
$settings_query = "SELECT setting_key, setting_value FROM system_settings";
$stmt = $db->prepare($settings_query);
$stmt->execute();
$settings_raw = $stmt->fetchAll();

$settings = [];
foreach ($settings_raw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Statistiques système
$system_stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
        (SELECT COUNT(*) FROM products) as total_products,
        (SELECT COUNT(*) FROM product_categories) as total_categories,
        (SELECT COUNT(*) FROM customers WHERE id > 1) as total_customers,
        (SELECT COUNT(*) FROM sales WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as sales_last_30_days,
        (SELECT COUNT(*) FROM cleaning_services WHERE end_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as cleaning_last_30_days
";
$stmt = $db->prepare($system_stats_query);
$stmt->execute();
$system_stats = $stmt->fetch();

$page_title = 'Configuration et Paramètres';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Le Yellowjack</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/panel.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-cogs me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="dashboard.php" class="btn btn-outline-success">
                                <i class="fas fa-chart-line me-1"></i>
                                Tableau de bord
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistiques système -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($system_stats['active_users']); ?></h5>
                                <p class="card-text text-muted">Employés actifs</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-box fa-2x text-info mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($system_stats['total_products']); ?></h5>
                                <p class="card-text text-muted">Produits</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-tags fa-2x text-warning mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($system_stats['total_categories']); ?></h5>
                                <p class="card-text text-muted">Catégories</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-heart fa-2x text-danger mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($system_stats['total_customers']); ?></h5>
                                <p class="card-text text-muted">Clients</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-shopping-cart fa-2x text-success mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($system_stats['sales_last_30_days']); ?></h5>
                                <p class="card-text text-muted">Ventes (30j)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-broom fa-2x text-secondary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($system_stats['cleaning_last_30_days']); ?></h5>
                                <p class="card-text text-muted">Ménages (30j)</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Onglets de configuration -->
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                            <i class="fas fa-cog me-2"></i>
                            Général
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="discord-tab" data-bs-toggle="tab" data-bs-target="#discord" type="button" role="tab">
                            <i class="fab fa-discord me-2"></i>
                            Discord
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                            <i class="fas fa-server me-2"></i>
                            Système
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="about-tab" data-bs-toggle="tab" data-bs-target="#about" type="button" role="tab">
                            <i class="fas fa-info-circle me-2"></i>
                            À propos
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="settingsTabsContent">
                    <!-- Onglet Général -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-cog me-2"></i>
                                    Paramètres Généraux
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                    <input type="hidden" name="action" value="update_general">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="bar_name" class="form-label">Nom du bar *</label>
                                                <input type="text" class="form-control" id="bar_name" name="bar_name" 
                                                       value="<?php echo htmlspecialchars($settings['bar_name'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="bar_address" class="form-label">Adresse du bar</label>
                                                <input type="text" class="form-control" id="bar_address" name="bar_address" 
                                                       value="<?php echo htmlspecialchars($settings['bar_address'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="cleaning_rate" class="form-label">Taux de ménage ($/ménage) *</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="cleaning_rate" name="cleaning_rate" 
                                                           value="<?php echo htmlspecialchars($settings['cleaning_rate'] ?? '60'); ?>" 
                                                           step="0.01" min="0.01" required>
                                                    <span class="input-group-text">$</span>
                                                </div>
                                                <div class="form-text">Montant payé par ménage effectué</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="commission_rate" class="form-label">Taux de commission (%) *</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="commission_rate" name="commission_rate" 
                                                           value="<?php echo htmlspecialchars($settings['commission_rate'] ?? '25'); ?>" 
                                                           step="0.01" min="0.01" max="100" required>
                                                    <span class="input-group-text">%</span>
                                                </div>
                                                <div class="form-text">Pourcentage de commission sur les ventes</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            Sauvegarder
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Onglet Discord -->
                    <div class="tab-pane fade" id="discord" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fab fa-discord me-2"></i>
                                    Configuration Discord
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Information :</strong> Le webhook Discord permet d'envoyer automatiquement les tickets de caisse sur votre serveur Discord.
                                </div>
                                
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                    <input type="hidden" name="action" value="update_discord">
                                    
                                    <div class="mb-3">
                                        <label for="discord_webhook" class="form-label">URL du Webhook Discord</label>
                                        <input type="url" class="form-control" id="discord_webhook" name="discord_webhook" 
                                               value="<?php echo htmlspecialchars($settings['discord_webhook'] ?? ''); ?>" 
                                               placeholder="https://discord.com/api/webhooks/...">
                                        <div class="form-text">
                                            Pour obtenir l'URL du webhook :
                                            <ol class="mt-2">
                                                <li>Allez dans les paramètres de votre serveur Discord</li>
                                                <li>Cliquez sur "Intégrations" puis "Webhooks"</li>
                                                <li>Créez un nouveau webhook ou utilisez un existant</li>
                                                <li>Copiez l'URL du webhook</li>
                                            </ol>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="button" class="btn btn-outline-primary me-md-2" onclick="testDiscord()">
                                            <i class="fas fa-vial me-2"></i>
                                            Tester
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            Sauvegarder
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Onglet Système -->
                    <div class="tab-pane fade" id="system" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-server me-2"></i>
                                    Informations Système
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Informations PHP</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Version PHP :</strong></td>
                                                <td><?php echo PHP_VERSION; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Fuseau horaire :</strong></td>
                                                <td><?php echo date_default_timezone_get(); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Date/Heure serveur :</strong></td>
                                                <td><?php echo formatDate(getCurrentDateTime()); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Extensions requises :</strong></td>
                                                <td>
                                                    <?php
                                                    $extensions = ['pdo', 'pdo_mysql', 'curl', 'json'];
                                                    foreach ($extensions as $ext) {
                                                        $loaded = extension_loaded($ext);
                                                        echo '<span class="badge bg-' . ($loaded ? 'success' : 'danger') . ' me-1">' . $ext . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Base de données</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Serveur :</strong></td>
                                                <td><?php echo DB_HOST . ':' . DB_PORT; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Base de données :</strong></td>
                                                <td><?php echo DB_NAME; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Statut :</strong></td>
                                                <td>
                                                    <?php
                                                    try {
                                                        $db->query('SELECT 1');
                                                        echo '<span class="badge bg-success">Connecté</span>';
                                                    } catch (Exception $e) {
                                                        echo '<span class="badge bg-danger">Erreur</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <h6 class="text-muted mt-3">Actions système</h6>
                                        <div class="d-grid gap-2">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                                <input type="hidden" name="action" value="backup_database">
                                                <button type="submit" class="btn btn-outline-warning btn-sm w-100" 
                                                        onclick="return confirm('Créer une sauvegarde de la base de données ?')">
                                                    <i class="fas fa-download me-2"></i>
                                                    Sauvegarder la base
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Onglet À propos -->
                    <div class="tab-pane fade" id="about" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    À propos du système
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h4 class="text-primary">🤠 Le Yellowjack - Panel de Gestion</h4>
                                        <p class="lead">Système de gestion complet pour bar western dans l'univers GTA V / FiveM</p>
                                        
                                        <h6 class="text-muted">Fonctionnalités principales :</h6>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success me-2"></i> Gestion des ménages avec calcul automatique des salaires</li>
                                            <li><i class="fas fa-check text-success me-2"></i> Caisse enregistreuse avec gestion des stocks</li>
                                            <li><i class="fas fa-check text-success me-2"></i> Système de commissions pour les employés</li>
                                            <li><i class="fas fa-check text-success me-2"></i> Gestion des clients fidèles avec réductions</li>
                                            <li><i class="fas fa-check text-success me-2"></i> Rapports et analyses détaillés</li>
                                            <li><i class="fas fa-check text-success me-2"></i> Intégration Discord pour les notifications</li>
                                            <li><i class="fas fa-check text-success me-2"></i> Système de rôles hiérarchisés</li>
                                        </ul>
                                        
                                        <h6 class="text-muted">Rôles disponibles :</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="card border-primary">
                                                    <div class="card-body">
                                                        <h6 class="card-title text-primary">CDD</h6>
                                                        <p class="card-text small">Accès aux ménages uniquement</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card border-info">
                                                    <div class="card-body">
                                                        <h6 class="card-title text-info">CDI</h6>
                                                        <p class="card-text small">Ménages + Caisse enregistreuse</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card border-warning">
                                                    <div class="card-body">
                                                        <h6 class="card-title text-warning">Responsable</h6>
                                                        <p class="card-text small">Gestion complète + Rapports</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card border-danger">
                                                    <div class="card-body">
                                                        <h6 class="card-title text-danger">Patron</h6>
                                                        <p class="card-text small">Accès total + Configuration</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <i class="fas fa-code fa-3x text-muted mb-3"></i>
                                                <h6 class="text-muted">Informations techniques</h6>
                                                <table class="table table-sm table-borderless">
                                                    <tr>
                                                        <td><strong>Version :</strong></td>
                                                        <td>1.0.0</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Framework :</strong></td>
                                                        <td>PHP 8+ / MySQL</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Interface :</strong></td>
                                                        <td>Bootstrap 5</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Sécurité :</strong></td>
                                                        <td>CSRF Protection</td>
                                                    </tr>
                                                </table>
                                                
                                                <div class="mt-3">
                                                    <a href="../index.php" class="btn btn-outline-primary btn-sm" target="_blank">
                                                        <i class="fas fa-external-link-alt me-1"></i>
                                                        Site vitrine
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function testDiscord() {
            const webhookUrl = document.getElementById('discord_webhook').value;
            if (!webhookUrl) {
                alert('Veuillez d\'abord saisir l\'URL du webhook Discord.');
                return;
            }
            
            // Créer un formulaire temporaire pour le test
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo generateCSRF(); ?>';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'test_discord';
            
            const webhookInput = document.createElement('input');
            webhookInput.type = 'hidden';
            webhookInput.name = 'discord_webhook';
            webhookInput.value = webhookUrl;
            
            form.appendChild(csrfInput);
            form.appendChild(actionInput);
            form.appendChild(webhookInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>