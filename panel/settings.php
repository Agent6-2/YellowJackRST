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
                $notify_sales = isset($_POST['notify_sales']) ? '1' : '0';
                $notify_cleaning = isset($_POST['notify_cleaning']) ? '1' : '0';
                $notify_goals = isset($_POST['notify_goals']) ? '1' : '0';
                $notify_weekly = isset($_POST['notify_weekly']) ? '1' : '0';
                $notifications_enabled = isset($_POST['notifications_enabled']) ? '1' : '0';
                
                if (!empty($discord_webhook) && !filter_var($discord_webhook, FILTER_VALIDATE_URL)) {
                    $error = 'L\'URL du webhook Discord n\'est pas valide.';
                } else {
                    try {
                        $db->beginTransaction();
                        
                        // Configuration Discord complète
                        $discord_settings = [
                            'discord_webhook_url' => $discord_webhook,
                            'discord_notifications_enabled' => $notifications_enabled,
                            'discord_notify_sales' => $notify_sales,
                            'discord_notify_cleaning' => $notify_cleaning,
                            'discord_notify_goals' => $notify_goals,
                            'discord_notify_weekly' => $notify_weekly
                        ];
                        
                        $stmt = $db->prepare("
                            INSERT INTO system_settings (setting_key, setting_value) 
                            VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                        ");
                        
                        foreach ($discord_settings as $key => $value) {
                            $stmt->execute([$key, $value]);
                        }
                        
                        $db->commit();
                        $message = 'Configuration Discord mise à jour avec succès !';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Erreur lors de la mise à jour de la configuration Discord : ' . $e->getMessage();
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
                

                
            case 'update_commissions':
                // Récupérer tous les paramètres de commission
                $commission_settings = [
                    'commission_cdd_sales' => floatval($_POST['commission_cdd_sales'] ?? 0),
                    'commission_cdi_sales' => floatval($_POST['commission_cdi_sales'] ?? 15),
                    'commission_responsable_sales' => floatval($_POST['commission_responsable_sales'] ?? 20),
                    'commission_patron_sales' => floatval($_POST['commission_patron_sales'] ?? 25),
                    'cleaning_rate_cdd' => floatval($_POST['cleaning_rate_cdd'] ?? 50),
                    'cleaning_rate_cdi' => floatval($_POST['cleaning_rate_cdi'] ?? 60),
                    'cleaning_rate_responsable' => floatval($_POST['cleaning_rate_responsable'] ?? 70),
                    'cleaning_rate_patron' => floatval($_POST['cleaning_rate_patron'] ?? 80),
                    'bonus_weekend_rate' => floatval($_POST['bonus_weekend_rate'] ?? 10),
                    'bonus_night_rate' => floatval($_POST['bonus_night_rate'] ?? 15),
                    'enable_performance_bonus' => isset($_POST['enable_performance_bonus']) ? '1' : '0',
                    'enable_team_bonus' => isset($_POST['enable_team_bonus']) ? '1' : '0'
                ];
                
                // Validation des valeurs
                $validation_errors = [];
                foreach ($commission_settings as $key => $value) {
                    if (strpos($key, 'commission_') === 0 || strpos($key, 'bonus_') === 0) {
                        if ($value < 0 || $value > 100) {
                            $validation_errors[] = "Le taux pour {$key} doit être entre 0 et 100%.";
                        }
                    } elseif (strpos($key, 'cleaning_rate_') === 0) {
                        if ($value <= 0) {
                            $validation_errors[] = "Le taux de ménage pour {$key} doit être supérieur à 0.";
                        }
                    }
                }
                
                if (!empty($validation_errors)) {
                    $error = implode('<br>', $validation_errors);
                } else {
                    try {
                        $db->beginTransaction();
                        
                        // Mettre à jour ou insérer chaque paramètre
                        $stmt = $db->prepare("
                            INSERT INTO system_settings (setting_key, setting_value) 
                            VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                        ");
                        
                        foreach ($commission_settings as $key => $value) {
                            $stmt->execute([$key, $value]);
                        }
                        
                        $db->commit();
                        $message = 'Configuration des commissions mise à jour avec succès !';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Erreur lors de la mise à jour des commissions : ' . $e->getMessage();
                    }
                }
                break;
                
            case 'backup_database':
                // Cette fonctionnalité nécessiterait des permissions spéciales sur le serveur
                $message = 'Fonctionnalité de sauvegarde en cours de développement.';
                break;
                
            case 'update_vitrine':
                // Vérifier que l'utilisateur est un patron
                if (!$auth->hasPermission('Patron')) {
                    $error = 'Vous n\'avez pas les permissions nécessaires pour modifier la vitrine.';
                    break;
                }
                
                // Récupérer les données du formulaire
                $vitrine_bar_name = trim($_POST['vitrine_bar_name'] ?? '');
                $vitrine_bar_slogan = trim($_POST['vitrine_bar_slogan'] ?? '');
                $vitrine_bar_address = trim($_POST['vitrine_bar_address'] ?? '');
                $vitrine_bar_phone = trim($_POST['vitrine_bar_phone'] ?? '');
                $vitrine_menu_title = trim($_POST['vitrine_menu_title'] ?? '');
                $vitrine_menu_description = trim($_POST['vitrine_menu_description'] ?? '');
                $vitrine_team_title = trim($_POST['vitrine_team_title'] ?? '');
                $vitrine_team_description = trim($_POST['vitrine_team_description'] ?? '');
                $vitrine_contact_title = trim($_POST['vitrine_contact_title'] ?? '');
                $vitrine_contact_hours = trim($_POST['vitrine_contact_hours'] ?? '');
                
                // Récupérer les titres des catégories
                $vitrine_alcool_title = trim($_POST['vitrine_alcool_title'] ?? '');
                $vitrine_soft_title = trim($_POST['vitrine_soft_title'] ?? '');
                $vitrine_snacks_title = trim($_POST['vitrine_snacks_title'] ?? '');
                $vitrine_plats_title = trim($_POST['vitrine_plats_title'] ?? '');
                
                // Récupérer les produits du menu
                $alcool_items = [];
                $soft_items = [];
                $snacks_items = [];
                $plats_items = [];
                
                // Traitement des boissons alcoolisées
                if (isset($_POST['alcool_items']) && is_array($_POST['alcool_items'])) {
                    foreach ($_POST['alcool_items'] as $item) {
                        if (!empty($item['name'])) {
                            $alcool_items[] = [
                                'name' => trim($item['name']),
                                'price' => trim($item['price'] ?? '0'),
                                'description' => trim($item['description'] ?? '')
                            ];
                        }
                    }
                }
                
                // Traitement des boissons non-alcoolisées
                if (isset($_POST['soft_items']) && is_array($_POST['soft_items'])) {
                    foreach ($_POST['soft_items'] as $item) {
                        if (!empty($item['name'])) {
                            $soft_items[] = [
                                'name' => trim($item['name']),
                                'price' => trim($item['price'] ?? '0'),
                                'description' => trim($item['description'] ?? '')
                            ];
                        }
                    }
                }
                
                // Traitement des snacks
                if (isset($_POST['snacks_items']) && is_array($_POST['snacks_items'])) {
                    foreach ($_POST['snacks_items'] as $item) {
                        if (!empty($item['name'])) {
                            $snacks_items[] = [
                                'name' => trim($item['name']),
                                'price' => trim($item['price'] ?? '0'),
                                'description' => trim($item['description'] ?? '')
                            ];
                        }
                    }
                }
                
                // Traitement des plats
                if (isset($_POST['plats_items']) && is_array($_POST['plats_items'])) {
                    foreach ($_POST['plats_items'] as $item) {
                        if (!empty($item['name'])) {
                            $plats_items[] = [
                                'name' => trim($item['name']),
                                'price' => trim($item['price'] ?? '0'),
                                'description' => trim($item['description'] ?? '')
                            ];
                        }
                    }
                }
                
                // Traitement des membres de l'équipe
                $team_members = [];
                if (isset($_POST['team_members']) && is_array($_POST['team_members'])) {
                    foreach ($_POST['team_members'] as $member) {
                        if (!empty($member['title'])) {
                            $team_members[] = [
                                'title' => trim($member['title']),
                                'role' => trim($member['role'] ?? ''),
                                'description' => trim($member['description'] ?? ''),
                                'icon' => trim($member['icon'] ?? 'fa-user')
                            ];
                        }
                    }
                }
                
                if (empty($vitrine_bar_name)) {
                    $error = 'Le nom du bar est obligatoire pour la vitrine.';
                } else {
                    try {
                        $db->beginTransaction();
                        
                        // Mettre à jour les paramètres de la vitrine
                        $vitrine_settings = [
                            'bar_name' => $vitrine_bar_name,
                            'bar_slogan' => $vitrine_bar_slogan,
                            'bar_address' => $vitrine_bar_address,
                            'bar_phone' => $vitrine_bar_phone,
                            'menu_title' => $vitrine_menu_title,
                            'menu_description' => $vitrine_menu_description,
                            'team_title' => $vitrine_team_title,
                            'team_description' => $vitrine_team_description,
                            'contact_title' => $vitrine_contact_title,
                            'contact_hours' => $vitrine_contact_hours,
                            // Titres des catégories
                            'alcool_title' => $vitrine_alcool_title,
                            'soft_title' => $vitrine_soft_title,
                            'snacks_title' => $vitrine_snacks_title,
                            'plats_title' => $vitrine_plats_title,
                            // Produits du menu (encodés en JSON)
                            'alcool_items' => json_encode($alcool_items, JSON_UNESCAPED_UNICODE),
                            'soft_items' => json_encode($soft_items, JSON_UNESCAPED_UNICODE),
                            'snacks_items' => json_encode($snacks_items, JSON_UNESCAPED_UNICODE),
                            'plats_items' => json_encode($plats_items, JSON_UNESCAPED_UNICODE),
                            // Membres de l'équipe (encodés en JSON)
                            'team_members' => json_encode($team_members, JSON_UNESCAPED_UNICODE)
                        ];
                        
                        foreach ($vitrine_settings as $key => $value) {
                            // Vérifier si le paramètre existe déjà
                            $stmt = $db->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
                            $stmt->execute([$key]);
                            $exists = (int)$stmt->fetchColumn() > 0;
                            
                            if ($exists) {
                                // Mettre à jour le paramètre existant
                                $stmt = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                                $stmt->execute([$value, $key]);
                            } else {
                                // Créer un nouveau paramètre
                                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
                                $stmt->execute([$key, $value, 'Paramètre de la vitrine du site']);
                            }
                        }
                        
                        $db->commit();
                        $message = 'Configuration de la vitrine mise à jour avec succès !';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Erreur lors de la mise à jour de la vitrine: ' . $e->getMessage();
                    }
                }
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
    <!-- Tabs Fix CSS -->
    <link href="../assets/css/tabs-fix.css?v=<?php echo time(); ?>" rel="stylesheet">
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
                        <button class="nav-link" id="commissions-tab" data-bs-toggle="tab" data-bs-target="#commissions" type="button" role="tab">
                            <i class="fas fa-percentage me-2"></i>
                            Commissions
                        </button>
                    </li>
                    <?php if ($auth->hasPermission('Patron')): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="vitrine-tab" data-bs-toggle="tab" data-bs-target="#vitrine" type="button" role="tab">
                            <i class="fas fa-store me-2"></i>
                            Vitrine
                        </button>
                    </li>
                    <?php endif; ?>
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
                        <!-- Configuration Webhook -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fab fa-discord me-2"></i>
                                    Configuration Discord Webhook
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Information :</strong> Les webhooks Discord permettent d'envoyer automatiquement des notifications sur votre serveur Discord pour différents événements.
                                </div>
                                
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                    <input type="hidden" name="action" value="update_discord">
                                    
                                    <div class="mb-3">
                                        <label for="discord_webhook" class="form-label">URL du Webhook Discord</label>
                                        <input type="url" class="form-control" id="discord_webhook" name="discord_webhook" 
                                               value="<?php echo htmlspecialchars($settings['discord_webhook_url'] ?? ''); ?>" 
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
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notifications_enabled" name="notifications_enabled" 
                                                   <?php echo ($settings['discord_notifications_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notifications_enabled">
                                                <strong>Activer les notifications Discord</strong>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-bell me-1"></i>
                                            Types de notifications
                                        </label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="notify_sales" name="notify_sales" 
                                                           <?php echo ($settings['discord_notify_sales'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notify_sales">
                                                        <i class="fas fa-cash-register me-1 text-success"></i>
                                                        Nouvelles ventes
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="notify_cleaning" name="notify_cleaning" 
                                                           <?php echo ($settings['discord_notify_cleaning'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notify_cleaning">
                                                        <i class="fas fa-broom me-1 text-primary"></i>
                                                        Services de ménage
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="notify_goals" name="notify_goals" 
                                                           <?php echo ($settings['discord_notify_goals'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notify_goals">
                                                        <i class="fas fa-trophy me-1 text-warning"></i>
                                                        Objectifs atteints
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="notify_weekly" name="notify_weekly" 
                                                           <?php echo ($settings['discord_notify_weekly'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notify_weekly">
                                                        <i class="fas fa-calendar-week me-1 text-info"></i>
                                                        Résumés hebdomadaires
                                                    </label>
                                                </div>
                                            </div>
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
                        
                        <!-- Section Bot Discord supprimée - Utilisez les Webhooks Discord à la place -->
                                

                            </div>
                        </div>
                    </div>
                    
                    <!-- Onglet Système -->
                    <div class="tab-pane fade" id="system" role="tabpanel">
                        <div class="mt-3">
                            <!-- En-tête moderne -->
                            <div class="d-flex align-items-center mb-4">
                                <div class="bg-primary bg-gradient rounded-circle p-3 me-3">
                                    <i class="fas fa-server text-white fa-lg"></i>
                                </div>
                                <div>
                                    <h4 class="mb-1 text-primary">Informations Système</h4>
                                    <p class="text-muted mb-0">État et configuration du serveur</p>
                                </div>
                            </div>

                            <!-- Cartes d'informations modernes -->
                            <div class="row g-4 mb-4">
                                <!-- Carte PHP -->
                                <div class="col-lg-6">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-header bg-gradient-primary text-white border-0">
                                            <div class="d-flex align-items-center">
                                                <i class="fab fa-php me-2 fa-lg"></i>
                                                <h6 class="mb-0 fw-bold">Configuration PHP</h6>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-code text-primary me-2"></i>
                                                            <span class="fw-medium">Version PHP</span>
                                                        </div>
                                                        <span class="badge bg-primary fs-6"><?php echo PHP_VERSION; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-globe text-info me-2"></i>
                                                            <span class="fw-medium">Fuseau horaire</span>
                                                        </div>
                                                        <span class="text-muted"><?php echo date_default_timezone_get(); ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-clock text-warning me-2"></i>
                                                            <span class="fw-medium">Date/Heure serveur</span>
                                                        </div>
                                                        <span class="text-muted"><?php echo formatDate(getCurrentDateTime()); ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="p-3 bg-light rounded">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <i class="fas fa-puzzle-piece text-success me-2"></i>
                                                            <span class="fw-medium">Extensions PHP</span>
                                                        </div>
                                                        <div class="d-flex flex-wrap gap-1">
                                                            <?php
                                                            $extensions = ['pdo', 'pdo_mysql', 'curl', 'json'];
                                                            foreach ($extensions as $ext) {
                                                                $loaded = extension_loaded($ext);
                                                                echo '<span class="badge bg-' . ($loaded ? 'success' : 'danger') . ' me-1">' . $ext . '</span>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Carte Base de données -->
                                <div class="col-lg-6">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-header bg-gradient-success text-white border-0">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-database me-2 fa-lg"></i>
                                                <h6 class="mb-0 fw-bold">Base de Données</h6>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-server text-primary me-2"></i>
                                                            <span class="fw-medium">Serveur</span>
                                                        </div>
                                                        <span class="text-muted font-monospace"><?php echo DB_HOST . ':' . DB_PORT; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-database text-info me-2"></i>
                                                            <span class="fw-medium">Base de données</span>
                                                        </div>
                                                        <span class="text-muted font-monospace"><?php echo DB_NAME; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-heartbeat text-danger me-2"></i>
                                                            <span class="fw-medium">Statut de connexion</span>
                                                        </div>
                                                        <div>
                                                            <?php
                                                            try {
                                                                $db->query('SELECT 1');
                                                                echo '<span class="badge bg-success fs-6"><i class="fas fa-check-circle me-1"></i>Connecté</span>';
                                                            } catch (Exception $e) {
                                                                echo '<span class="badge bg-danger fs-6"><i class="fas fa-times-circle me-1"></i>Erreur</span>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section Actions Système -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-gradient-warning text-dark border-0">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-tools me-2 fa-lg"></i>
                                        <h6 class="mb-0 fw-bold">Actions Système</h6>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center p-4 bg-light rounded-3 h-100">
                                                <div class="bg-warning bg-gradient rounded-circle p-3 me-3">
                                                    <i class="fas fa-download text-white"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">Sauvegarde de la base de données</h6>
                                                    <p class="text-muted mb-3 small">Créer une copie de sécurité complète de toutes les données</p>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                                        <input type="hidden" name="action" value="backup_database">
                                                        <button type="submit" class="btn btn-warning btn-sm fw-medium" onclick="return confirm('Créer une sauvegarde de la base de données ?')">
                                                            <i class="fas fa-database me-2"></i>
                                                            Créer la sauvegarde
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center p-4 bg-light rounded-3 h-100">
                                                <div class="bg-info bg-gradient rounded-circle p-3 me-3">
                                                    <i class="fas fa-info-circle text-white"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">Informations système</h6>
                                                    <p class="text-muted mb-3 small">Système opérationnel et fonctionnel</p>
                                                    <span class="badge bg-success fs-6">
                                                        <i class="fas fa-check-circle me-1"></i>
                                                        Tout fonctionne correctement
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Onglet Commissions -->
                    <div class="tab-pane fade" id="commissions" role="tabpanel">
                        <div class="container-fluid px-0">
                            <!-- En-tête avec statistiques -->
                            <div class="row g-3 mb-4">
                                <div class="col-12">
                                    <div class="card border-0 shadow-sm bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <div class="card-body text-white">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <h4 class="mb-2"><i class="fas fa-percentage me-3"></i>Gestion des Commissions</h4>
                                                    <p class="mb-0 opacity-75">Configurez les taux de rémunération par grade et optimisez la motivation de vos équipes</p>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <div class="d-flex justify-content-end gap-3">
                                                        <div class="text-center">
                                                            <div class="h5 mb-0">4</div>
                                                            <small class="opacity-75">Grades</small>
                                                        </div>
                                                        <div class="text-center">
                                                            <div class="h5 mb-0">2</div>
                                                            <small class="opacity-75">Types</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                <input type="hidden" name="action" value="update_commissions">
                                
                                <!-- Commissions Ventes -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-white border-0 pb-0">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div>
                                                        <h5 class="mb-1"><i class="fas fa-chart-line text-success me-2"></i>Commissions sur Ventes</h5>
                                                        <p class="text-muted mb-0 small">Pourcentage de commission sur le chiffre d'affaires généré</p>
                                                    </div>
                                                    <div class="badge bg-success-subtle text-success px-3 py-2">
                                                        <i class="fas fa-percentage me-1"></i>Pourcentage
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-body pt-3">
                                                <div class="row g-4">
                                                    <div class="col-md-6 col-lg-3">
                                                        <div class="commission-card h-100">
                                                            <div class="d-flex align-items-center mb-3">
                                                                <div class="commission-icon bg-info-subtle text-info me-3">
                                                                    <i class="fas fa-user-clock"></i>
                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-0">CDD</h6>
                                                                    <small class="text-muted">Contrat Déterminé</small>
                                                                </div>
                                                            </div>
                                                            <div class="input-group input-group-lg">
                                                                <input type="number" class="form-control border-2" id="commission_cdd_sales" name="commission_cdd_sales" 
                                                                       value="<?php echo htmlspecialchars($settings['commission_cdd_sales'] ?? '0'); ?>" 
                                                                       step="0.01" min="0" max="100" style="font-weight: 600;">
                                                                <span class="input-group-text bg-info text-white border-2 border-info">%</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 col-lg-3">
                                                        <div class="commission-card h-100">
                                                            <div class="d-flex align-items-center mb-3">
                                                                <div class="commission-icon bg-primary-subtle text-primary me-3">
                                                                    <i class="fas fa-user-check"></i>
                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-0">CDI</h6>
                                                                    <small class="text-muted">Contrat Indéterminé</small>
                                                                </div>
                                                            </div>
                                                            <div class="input-group input-group-lg">
                                                                <input type="number" class="form-control border-2" id="commission_cdi_sales" name="commission_cdi_sales" 
                                                                       value="<?php echo htmlspecialchars($settings['commission_cdi_sales'] ?? '15'); ?>" 
                                                                       step="0.01" min="0" max="100" style="font-weight: 600;">
                                                                <span class="input-group-text bg-primary text-white border-2 border-primary">%</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 col-lg-3">
                                                        <div class="commission-card h-100">
                                                            <div class="d-flex align-items-center mb-3">
                                                                <div class="commission-icon bg-warning-subtle text-warning me-3">
                                                                    <i class="fas fa-user-tie"></i>
                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-0">Responsable</h6>
                                                                    <small class="text-muted">Chef d'équipe</small>
                                                                </div>
                                                            </div>
                                                            <div class="input-group input-group-lg">
                                                                <input type="number" class="form-control border-2" id="commission_responsable_sales" name="commission_responsable_sales" 
                                                                       value="<?php echo htmlspecialchars($settings['commission_responsable_sales'] ?? '20'); ?>" 
                                                                       step="0.01" min="0" max="100" style="font-weight: 600;">
                                                                <span class="input-group-text bg-warning text-white border-2 border-warning">%</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 col-lg-3">
                                                        <div class="commission-card h-100">
                                                            <div class="d-flex align-items-center mb-3">
                                                                <div class="commission-icon bg-danger-subtle text-danger me-3">
                                                                    <i class="fas fa-crown"></i>
                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-0">Patron</h6>
                                                                    <small class="text-muted">Propriétaire</small>
                                                                </div>
                                                            </div>
                                                            <div class="input-group input-group-lg">
                                                                <input type="number" class="form-control border-2" id="commission_patron_sales" name="commission_patron_sales" 
                                                                       value="<?php echo htmlspecialchars($settings['commission_patron_sales'] ?? '25'); ?>" 
                                                                       step="0.01" min="0" max="100" style="font-weight: 600;">
                                                                <span class="input-group-text bg-danger text-white border-2 border-danger">%</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Taux Ménages -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-white border-0 pb-0">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div>
                                                        <h5 class="mb-1"><i class="fas fa-home text-info me-2"></i>Rémunération Ménages</h5>
                                                        <p class="text-muted mb-0 small">Montant fixe par ménage effectué selon le grade</p>
                                                    </div>
                                                    <div class="badge bg-info-subtle text-info px-3 py-2">
                                                        <i class="fas fa-dollar-sign me-1"></i>Montant fixe
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-body pt-3">
                                                <div class="row g-4">
                                                    <div class="col-md-6 col-lg-3">
                                                        <div class="commission-card h-100">
                                                            <div class="d-flex align-items-center mb-3">
                                                                <div class="commission-icon bg-info-subtle text-info me-3">
                                                                    <i class="fas fa-broom"></i>
                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-0">CDD</h6>
                                                                    <small class="text-muted">Par ménage</small>
                                                                </div>
                                                            </div>
                                                            <div class="input-group input-group-lg">
                                                                <span class="input-group-text bg-info text-white border-2 border-info">$</span>
                                                                <input type="number" class="form-control border-2" id="cleaning_rate_cdd" name="cleaning_rate_cdd" 
                                                                       value="<?php echo htmlspecialchars($settings['cleaning_rate_cdd'] ?? '50'); ?>" 
                                                                       step="0.01" min="0.01" style="font-weight: 600;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 col-lg-3">
                                                        <div class="commission-card h-100">
                                                            <div class="d-flex align-items-center mb-3">
                                                                <div class="commission-icon bg-primary-subtle text-primary me-3">
                                                                    <i class="fas fa-broom"></i>
                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-0">CDI</h6>
                                                                    <small class="text-muted">Par ménage</small>
                                                                </div>
                                                            </div>
                                                            <div class="input-group input-group-lg">
                                                                <span class="input-group-text bg-primary text-white border-2 border-primary">$</span>
                                                                <input type="number" class="form-control border-2" id="cleaning_rate_cdi" name="cleaning_rate_cdi" 
                                                                       value="<?php echo htmlspecialchars($settings['cleaning_rate_cdi'] ?? '60'); ?>" 
                                                                       step="0.01" min="0.01" style="font-weight: 600;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 col-lg-3">
                                                        <div class="commission-card h-100">
                                                            <div class="d-flex align-items-center mb-3">
                                                                <div class="commission-icon bg-warning-subtle text-warning me-3">
                                                                    <i class="fas fa-broom"></i>
                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-0">Responsable</h6>
                                                                    <small class="text-muted">Par ménage</small>
                                                                </div>
                                                            </div>
                                                            <div class="input-group input-group-lg">
                                                                <span class="input-group-text bg-warning text-white border-2 border-warning">$</span>
                                                                <input type="number" class="form-control border-2" id="cleaning_rate_responsable" name="cleaning_rate_responsable" 
                                                                       value="<?php echo htmlspecialchars($settings['cleaning_rate_responsable'] ?? '70'); ?>" 
                                                                       step="0.01" min="0.01" style="font-weight: 600;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 col-lg-3">
                                                        <div class="commission-card h-100">
                                                            <div class="d-flex align-items-center mb-3">
                                                                <div class="commission-icon bg-danger-subtle text-danger me-3">
                                                                    <i class="fas fa-broom"></i>
                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-0">Patron</h6>
                                                                    <small class="text-muted">Par ménage</small>
                                                                </div>
                                                            </div>
                                                            <div class="input-group input-group-lg">
                                                                <span class="input-group-text bg-danger text-white border-2 border-danger">$</span>
                                                                <input type="number" class="form-control border-2" id="cleaning_rate_patron" name="cleaning_rate_patron" 
                                                                       value="<?php echo htmlspecialchars($settings['cleaning_rate_patron'] ?? '80'); ?>" 
                                                                       step="0.01" min="0.01" style="font-weight: 600;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Options Avancées -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-white border-0 pb-0">
                                                <h5 class="mb-1"><i class="fas fa-cogs text-secondary me-2"></i>Options Avancées</h5>
                                                <p class="text-muted mb-0 small">Bonus et paramètres supplémentaires pour optimiser la rémunération</p>
                                            </div>
                                            <div class="card-body pt-3">
                                                <div class="row g-4">
                                                    <!-- Bonus temporels -->
                                                    <div class="col-md-6">
                                                        <div class="card bg-light border-0 h-100">
                                                            <div class="card-body">
                                                                <h6 class="mb-3"><i class="fas fa-clock text-warning me-2"></i>Bonus Temporels</h6>
                                                                <div class="row g-3">
                                                                    <div class="col-12">
                                                                        <label for="bonus_weekend_rate" class="form-label fw-semibold">Bonus Week-end</label>
                                                                        <div class="input-group">
                                                                            <input type="number" class="form-control" id="bonus_weekend_rate" name="bonus_weekend_rate" 
                                                                                   value="<?php echo htmlspecialchars($settings['bonus_weekend_rate'] ?? '10'); ?>" 
                                                                                   step="0.01" min="0" max="100">
                                                                            <span class="input-group-text bg-warning text-white">%</span>
                                                                        </div>
                                                                        <div class="form-text"><i class="fas fa-calendar-weekend me-1"></i>Samedi et dimanche</div>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <label for="bonus_night_rate" class="form-label fw-semibold">Bonus Nuit</label>
                                                                        <div class="input-group">
                                                                            <input type="number" class="form-control" id="bonus_night_rate" name="bonus_night_rate" 
                                                                                   value="<?php echo htmlspecialchars($settings['bonus_night_rate'] ?? '15'); ?>" 
                                                                                   step="0.01" min="0" max="100">
                                                                            <span class="input-group-text bg-dark text-white">%</span>
                                                                        </div>
                                                                        <div class="form-text"><i class="fas fa-moon me-1"></i>22h00 à 06h00</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Bonus de performance -->
                                                    <div class="col-md-6">
                                                        <div class="card bg-light border-0 h-100">
                                                            <div class="card-body">
                                                                <h6 class="mb-3"><i class="fas fa-trophy text-success me-2"></i>Bonus de Performance</h6>
                                                                <div class="row g-3">
                                                                    <div class="col-12">
                                                                        <div class="form-check form-switch form-check-lg">
                                                                            <input class="form-check-input" type="checkbox" id="enable_performance_bonus" name="enable_performance_bonus" 
                                                                                   <?php echo ($settings['enable_performance_bonus'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label fw-semibold" for="enable_performance_bonus">
                                                                                Bonus Individuels
                                                                            </label>
                                                                        </div>
                                                                        <div class="form-text"><i class="fas fa-user-star me-1"></i>Basés sur les objectifs personnels</div>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <div class="form-check form-switch form-check-lg">
                                                                            <input class="form-check-input" type="checkbox" id="enable_team_bonus" name="enable_team_bonus" 
                                                                                   <?php echo ($settings['enable_team_bonus'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label fw-semibold" for="enable_team_bonus">
                                                                                Bonus d'Équipe
                                                                            </label>
                                                                        </div>
                                                                        <div class="form-text"><i class="fas fa-users me-1"></i>Partagés selon les performances globales</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Les modifications seront appliquées immédiatement</small>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                                            <i class="fas fa-undo me-2"></i>Annuler
                                                        </button>
                                                        <button type="submit" class="btn btn-primary btn-lg px-4">
                                                            <i class="fas fa-save me-2"></i>Sauvegarder les Commissions
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php if ($auth->hasPermission('Patron')): ?>
                    <!-- Onglet Vitrine -->
                    <div class="tab-pane fade" id="vitrine" role="tabpanel">
                        <div class="container-fluid py-4">
                            <!-- En-tête avec gradient -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card border-0 shadow-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <div class="card-body text-white py-4">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <h3 class="mb-2"><i class="fas fa-store-alt me-3"></i>Configuration de la Vitrine</h3>
                                                    <p class="mb-0 opacity-75">Personnalisez l'apparence et le contenu de votre site vitrine visible par les clients</p>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <div class="d-flex justify-content-end gap-2">
                                                        <a href="../index.php" target="_blank" class="btn btn-light btn-sm">
                                                            <i class="fas fa-external-link-alt me-2"></i>Aperçu
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                <input type="hidden" name="action" value="update_vitrine">
                                
                                <!-- Informations générales du bar -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-gradient-primary text-white border-0">
                                                <h5 class="mb-0"><i class="fas fa-building me-2"></i>Informations du Bar</h5>
                                            </div>
                                            <div class="card-body p-4">
                                                <div class="row g-4">
                                                    <div class="col-md-6">
                                                        <div class="form-floating">
                                                            <input type="text" class="form-control form-control-lg" id="vitrine_bar_name" name="vitrine_bar_name" 
                                                                   value="<?php echo htmlspecialchars($settings['bar_name'] ?? ''); ?>" placeholder="Nom du bar" required>
                                                            <label for="vitrine_bar_name"><i class="fas fa-tag me-2"></i>Nom du bar *</label>
                                                        </div>
                                                        <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i>Nom principal affiché sur la vitrine</div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-floating">
                                                            <input type="text" class="form-control form-control-lg" id="vitrine_bar_slogan" name="vitrine_bar_slogan" 
                                                                   value="<?php echo htmlspecialchars($settings['bar_slogan'] ?? 'L\'authentique bar western de Sandy Shore'); ?>" placeholder="Slogan">
                                                            <label for="vitrine_bar_slogan"><i class="fas fa-quote-left me-2"></i>Slogan</label>
                                                        </div>
                                                        <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i>Phrase d'accroche sous le nom</div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-floating">
                                                            <input type="text" class="form-control" id="vitrine_bar_address" name="vitrine_bar_address" 
                                                                   value="<?php echo htmlspecialchars($settings['bar_address'] ?? ''); ?>" placeholder="Adresse">
                                                            <label for="vitrine_bar_address"><i class="fas fa-map-marker-alt me-2"></i>Adresse</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-floating">
                                                            <input type="text" class="form-control" id="vitrine_bar_phone" name="vitrine_bar_phone" 
                                                                   value="<?php echo htmlspecialchars($settings['bar_phone'] ?? ''); ?>" placeholder="Téléphone">
                                                            <label for="vitrine_bar_phone"><i class="fas fa-phone me-2"></i>Téléphone</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                    
                                <!-- Configuration de la carte -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-gradient-success text-white border-0">
                                                <h5 class="mb-0"><i class="fas fa-utensils me-2"></i>Configuration de la Carte</h5>
                                            </div>
                                            <div class="card-body p-4">
                                                <div class="row g-4">
                                                    <div class="col-md-6">
                                                        <div class="form-floating">
                                                            <input type="text" class="form-control" id="vitrine_menu_title" name="vitrine_menu_title" 
                                                                   value="<?php echo htmlspecialchars($settings['menu_title'] ?? 'Notre Carte'); ?>" placeholder="Titre">
                                                            <label for="vitrine_menu_title"><i class="fas fa-heading me-2"></i>Titre de la section</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-floating">
                                                            <textarea class="form-control" id="vitrine_menu_description" name="vitrine_menu_description" 
                                                                      placeholder="Description" style="height: 60px;"><?php echo htmlspecialchars($settings['menu_description'] ?? 'Découvrez notre sélection de boissons et plats dans l\'esprit western'); ?></textarea>
                                                            <label for="vitrine_menu_description"><i class="fas fa-align-left me-2"></i>Description</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                     
                                <!-- Gestion des catégories de produits -->
                                <div class="row">
                                    <!-- Boissons Alcoolisées -->
                                    <div class="col-12 mb-4">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: white;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0"><i class="fas fa-glass-whiskey me-2"></i>Boissons Alcoolisées</h6>
                                                    <span class="badge bg-light text-dark">Premium</span>
                                                </div>
                                            </div>
                                            <div class="card-body p-4">
                                                <div class="row mb-4">
                                                    <div class="col-md-12">
                                                        <div class="form-floating">
                                                            <input type="text" class="form-control" id="vitrine_alcool_title" name="vitrine_alcool_title" 
                                                                   value="<?php echo htmlspecialchars($settings['alcool_title'] ?? 'Boissons Alcoolisées'); ?>" placeholder="Titre">
                                                            <label for="vitrine_alcool_title"><i class="fas fa-glass-whiskey me-2"></i>Titre de la catégorie</label>
                                                        </div>
                                                    </div>
                                                </div>
                                             
                                             <!-- Produits alcoolisés -->
                                             <div class="menu-items-editor">
                                                 <div class="row mb-2">
                                                     <div class="col-md-12 d-flex justify-content-between align-items-center">
                                                         <h6>Produits</h6>
                                                         <button type="button" class="btn btn-sm btn-outline-primary add-menu-item" data-category="alcool">
                                                             <i class="fas fa-plus me-1"></i> Ajouter un produit
                                                         </button>
                                                     </div>
                                                 </div>
                                                 
                                                 <div id="alcool-items-container">
                                                     <?php 
                                                     $alcool_items = json_decode($settings['alcool_items'] ?? '[]', true);
                                                     if (empty($alcool_items)) {
                                                         $alcool_items = [
                                                             ['name' => 'Bière Pression', 'price' => '5', 'description' => 'Bière locale fraîche à la pression'],
                                                             ['name' => 'Whiskey Premium', 'price' => '25', 'description' => 'Whiskey vieilli en fût de chêne'],
                                                             ['name' => 'Vin Rouge', 'price' => '15', 'description' => 'Vin rouge de la région']
                                                         ];
                                                     }
                                                     
                                                     foreach ($alcool_items as $index => $item): 
                                                     ?>
                                                     <div class="menu-item-row border rounded p-3 mb-3">
                                                         <div class="row">
                                                             <div class="col-md-5 mb-2">
                                                                 <label class="form-label">Nom du produit</label>
                                                                 <input type="text" class="form-control" name="alcool_items[<?php echo $index; ?>][name]" 
                                                                        value="<?php echo htmlspecialchars($item['name']); ?>">
                                                             </div>
                                                             <div class="col-md-2 mb-2">
                                                                 <label class="form-label">Prix ($)</label>
                                                                 <input type="text" class="form-control" name="alcool_items[<?php echo $index; ?>][price]" 
                                                                        value="<?php echo htmlspecialchars($item['price']); ?>">
                                                             </div>
                                                             <div class="col-md-5 mb-2 d-flex align-items-end justify-content-between">
                                                                 <div class="flex-grow-1 me-2">
                                                                     <label class="form-label">Description</label>
                                                                     <input type="text" class="form-control" name="alcool_items[<?php echo $index; ?>][description]" 
                                                                            value="<?php echo htmlspecialchars($item['description']); ?>">
                                                                 </div>
                                                                 <button type="button" class="btn btn-outline-danger remove-menu-item">
                                                                     <i class="fas fa-trash"></i>
                                                                 </button>
                                                             </div>
                                                         </div>
                                                     </div>
                                                     <?php endforeach; ?>
                                                 </div>
                                                 <input type="hidden" name="alcool_items_count" id="alcool_items_count" value="<?php echo count($alcool_items); ?>">
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Boissons Non-Alcoolisées -->
                                     <div class="col-12 mb-4">
                                         <div class="card border-0 shadow-sm">
                                             <div class="card-header" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%); color: white;">
                                                 <div class="d-flex justify-content-between align-items-center">
                                                     <h6 class="mb-0"><i class="fas fa-coffee me-2"></i>Boissons Non-Alcoolisées</h6>
                                                     <span class="badge bg-light text-dark">Rafraîchissant</span>
                                                 </div>
                                             </div>
                                             <div class="card-body p-4">
                                                 <div class="row mb-4">
                                                     <div class="col-md-12">
                                                         <div class="form-floating">
                                                             <input type="text" class="form-control" id="vitrine_soft_title" name="vitrine_soft_title" 
                                                                    value="<?php echo htmlspecialchars($settings['soft_title'] ?? 'Boissons Non-Alcoolisées'); ?>" placeholder="Titre">
                                                             <label for="vitrine_soft_title"><i class="fas fa-coffee me-2"></i>Titre de la catégorie</label>
                                                         </div>
                                                     </div>
                                                 </div>
                                             
                                             <!-- Produits non-alcoolisés -->
                                             <div class="menu-items-editor">
                                                 <div class="row mb-2">
                                                     <div class="col-md-12 d-flex justify-content-between align-items-center">
                                                         <h6>Produits</h6>
                                                         <button type="button" class="btn btn-sm btn-outline-primary add-menu-item" data-category="soft">
                                                             <i class="fas fa-plus me-1"></i> Ajouter un produit
                                                         </button>
                                                     </div>
                                                 </div>
                                                 
                                                 <div id="soft-items-container">
                                                     <?php 
                                                     $soft_items = json_decode($settings['soft_items'] ?? '[]', true);
                                                     if (empty($soft_items)) {
                                                         $soft_items = [
                                                             ['name' => 'Coca-Cola', 'price' => '3', 'description' => 'Soda classique bien frais'],
                                                             ['name' => 'Eau Minérale', 'price' => '2', 'description' => 'Eau plate ou gazeuse']
                                                         ];
                                                     }
                                                     
                                                     foreach ($soft_items as $index => $item): 
                                                     ?>
                                                     <div class="menu-item-row border rounded p-3 mb-3">
                                                         <div class="row">
                                                             <div class="col-md-5 mb-2">
                                                                 <label class="form-label">Nom du produit</label>
                                                                 <input type="text" class="form-control" name="soft_items[<?php echo $index; ?>][name]" 
                                                                        value="<?php echo htmlspecialchars($item['name']); ?>">
                                                             </div>
                                                             <div class="col-md-2 mb-2">
                                                                 <label class="form-label">Prix ($)</label>
                                                                 <input type="text" class="form-control" name="soft_items[<?php echo $index; ?>][price]" 
                                                                        value="<?php echo htmlspecialchars($item['price']); ?>">
                                                             </div>
                                                             <div class="col-md-5 mb-2 d-flex align-items-end justify-content-between">
                                                                 <div class="flex-grow-1 me-2">
                                                                     <label class="form-label">Description</label>
                                                                     <input type="text" class="form-control" name="soft_items[<?php echo $index; ?>][description]" 
                                                                            value="<?php echo htmlspecialchars($item['description']); ?>">
                                                                 </div>
                                                                 <button type="button" class="btn btn-outline-danger remove-menu-item">
                                                                     <i class="fas fa-trash"></i>
                                                                 </button>
                                                             </div>
                                                         </div>
                                                     </div>
                                                     <?php endforeach; ?>
                                                 </div>
                                                 <input type="hidden" name="soft_items_count" id="soft_items_count" value="<?php echo count($soft_items); ?>">
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Snacks -->
                                     <div class="col-12 mb-4">
                                         <div class="card border-0 shadow-sm">
                                             <div class="card-header" style="background: linear-gradient(135deg, #fdcb6e 0%, #e17055 100%); color: white;">
                                                 <div class="d-flex justify-content-between align-items-center">
                                                     <h6 class="mb-0"><i class="fas fa-cookie-bite me-2"></i>Snacks</h6>
                                                     <span class="badge bg-light text-dark">Gourmand</span>
                                                 </div>
                                             </div>
                                             <div class="card-body p-4">
                                                 <div class="row mb-4">
                                                     <div class="col-md-12">
                                                         <div class="form-floating">
                                                             <input type="text" class="form-control" id="vitrine_snacks_title" name="vitrine_snacks_title" 
                                                                    value="<?php echo htmlspecialchars($settings['snacks_title'] ?? 'Snacks'); ?>" placeholder="Titre">
                                                             <label for="vitrine_snacks_title"><i class="fas fa-cookie-bite me-2"></i>Titre de la catégorie</label>
                                                         </div>
                                                     </div>
                                                 </div>
                                             
                                             <!-- Produits snacks -->
                                             <div class="menu-items-editor">
                                                 <div class="row mb-2">
                                                     <div class="col-md-12 d-flex justify-content-between align-items-center">
                                                         <h6>Produits</h6>
                                                         <button type="button" class="btn btn-sm btn-outline-primary add-menu-item" data-category="snacks">
                                                             <i class="fas fa-plus me-1"></i> Ajouter un produit
                                                         </button>
                                                     </div>
                                                 </div>
                                                 
                                                 <div id="snacks-items-container">
                                                     <?php 
                                                     $snacks_items = json_decode($settings['snacks_items'] ?? '[]', true);
                                                     if (empty($snacks_items)) {
                                                         $snacks_items = [
                                                             ['name' => 'Cacahuètes Salées', 'price' => '4', 'description' => 'Parfait avec une bière']
                                                         ];
                                                     }
                                                     
                                                     foreach ($snacks_items as $index => $item): 
                                                     ?>
                                                     <div class="menu-item-row border rounded p-3 mb-3">
                                                         <div class="row">
                                                             <div class="col-md-5 mb-2">
                                                                 <label class="form-label">Nom du produit</label>
                                                                 <input type="text" class="form-control" name="snacks_items[<?php echo $index; ?>][name]" 
                                                                        value="<?php echo htmlspecialchars($item['name']); ?>">
                                                             </div>
                                                             <div class="col-md-2 mb-2">
                                                                 <label class="form-label">Prix ($)</label>
                                                                 <input type="text" class="form-control" name="snacks_items[<?php echo $index; ?>][price]" 
                                                                        value="<?php echo htmlspecialchars($item['price']); ?>">
                                                             </div>
                                                             <div class="col-md-5 mb-2 d-flex align-items-end justify-content-between">
                                                                 <div class="flex-grow-1 me-2">
                                                                     <label class="form-label">Description</label>
                                                                     <input type="text" class="form-control" name="snacks_items[<?php echo $index; ?>][description]" 
                                                                            value="<?php echo htmlspecialchars($item['description']); ?>">
                                                                 </div>
                                                                 <button type="button" class="btn btn-outline-danger remove-menu-item">
                                                                     <i class="fas fa-trash"></i>
                                                                 </button>
                                                             </div>
                                                         </div>
                                                     </div>
                                                     <?php endforeach; ?>
                                                 </div>
                                                 <input type="hidden" name="snacks_items_count" id="snacks_items_count" value="<?php echo count($snacks_items); ?>">
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Plats -->
                                     <div class="col-12 mb-4">
                                         <div class="card border-0 shadow-sm">
                                             <div class="card-header" style="background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%); color: white;">
                                                 <div class="d-flex justify-content-between align-items-center">
                                                     <h6 class="mb-0"><i class="fas fa-hamburger me-2"></i>Plats</h6>
                                                     <span class="badge bg-light text-dark">Savoureux</span>
                                                 </div>
                                             </div>
                                             <div class="card-body p-4">
                                                 <div class="row mb-4">
                                                     <div class="col-md-12">
                                                         <div class="form-floating">
                                                             <input type="text" class="form-control" id="vitrine_plats_title" name="vitrine_plats_title" 
                                                                    value="<?php echo htmlspecialchars($settings['plats_title'] ?? 'Plats'); ?>" placeholder="Titre">
                                                             <label for="vitrine_plats_title"><i class="fas fa-hamburger me-2"></i>Titre de la catégorie</label>
                                                         </div>
                                                     </div>
                                                 </div>
                                             
                                             <!-- Produits plats -->
                                             <div class="menu-items-editor">
                                                 <div class="row mb-2">
                                                     <div class="col-md-12 d-flex justify-content-between align-items-center">
                                                         <h6>Produits</h6>
                                                         <button type="button" class="btn btn-sm btn-outline-primary add-menu-item" data-category="plats">
                                                             <i class="fas fa-plus me-1"></i> Ajouter un produit
                                                         </button>
                                                     </div>
                                                 </div>
                                                 
                                                 <div id="plats-items-container">
                                                     <?php 
                                                     $plats_items = json_decode($settings['plats_items'] ?? '[]', true);
                                                     if (empty($plats_items)) {
                                                         $plats_items = [
                                                             ['name' => 'Burger Western', 'price' => '12', 'description' => 'Notre spécialité maison']
                                                         ];
                                                     }
                                                     
                                                     foreach ($plats_items as $index => $item): 
                                                     ?>
                                                     <div class="menu-item-row border rounded p-3 mb-3">
                                                         <div class="row">
                                                             <div class="col-md-5 mb-2">
                                                                 <label class="form-label">Nom du produit</label>
                                                                 <input type="text" class="form-control" name="plats_items[<?php echo $index; ?>][name]" 
                                                                        value="<?php echo htmlspecialchars($item['name']); ?>">
                                                             </div>
                                                             <div class="col-md-2 mb-2">
                                                                 <label class="form-label">Prix ($)</label>
                                                                 <input type="text" class="form-control" name="plats_items[<?php echo $index; ?>][price]" 
                                                                        value="<?php echo htmlspecialchars($item['price']); ?>">
                                                             </div>
                                                             <div class="col-md-5 mb-2 d-flex align-items-end justify-content-between">
                                                                 <div class="flex-grow-1 me-2">
                                                                     <label class="form-label">Description</label>
                                                                     <input type="text" class="form-control" name="plats_items[<?php echo $index; ?>][description]" 
                                                                            value="<?php echo htmlspecialchars($item['description']); ?>">
                                                                 </div>
                                                                 <button type="button" class="btn btn-outline-danger remove-menu-item">
                                                                     <i class="fas fa-trash"></i>
                                                                 </button>
                                                             </div>
                                                         </div>
                                                     </div>
                                                     <?php endforeach; ?>
                                                 </div>
                                                 <input type="hidden" name="plats_items_count" id="plats_items_count" value="<?php echo count($plats_items); ?>">
                                             </div>
                                         </div>
                                     </div>
                                    
                                    <!-- Section Équipe -->
                                    <div class="col-12 mb-4">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header" style="background: linear-gradient(135deg, #55a3ff 0%, #003d82 100%); color: white;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>Section Équipe</h6>
                                                    <span class="badge bg-light text-dark">Professionnel</span>
                                                </div>
                                            </div>
                                            <div class="card-body p-4">
                                                <div class="row mb-4">
                                                    <div class="col-md-12">
                                                        <div class="form-floating">
                                                            <input type="text" class="form-control" id="vitrine_team_title" name="vitrine_team_title" 
                                                                   value="<?php echo htmlspecialchars($settings['team_title'] ?? 'Notre Équipe'); ?>" placeholder="Titre">
                                                            <label for="vitrine_team_title"><i class="fas fa-users me-2"></i>Titre de la section Équipe</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row mb-4">
                                                    <div class="col-md-12">
                                                        <div class="form-floating">
                                                            <textarea class="form-control" id="vitrine_team_description" name="vitrine_team_description" 
                                                                     style="height: 80px;" placeholder="Description"><?php echo htmlspecialchars($settings['team_description'] ?? 'Rencontrez l\'équipe passionnée du Yellowjack'); ?></textarea>
                                                            <label for="vitrine_team_description"><i class="fas fa-align-left me-2"></i>Description de l'équipe</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Membres de l'équipe -->
                                    <div class="col-12 mb-4">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header" style="background: linear-gradient(135deg, #fd79a8 0%, #e84393 100%); color: white;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0"><i class="fas fa-user-friends me-2"></i>Membres de l'équipe</h6>
                                                    <span class="badge bg-light text-dark">Dynamique</span>
                                                </div>
                                            </div>
                                        <div class="card-body">
                                            <div class="team-members-container">
                                                <?php
                                                // Récupérer les membres de l'équipe
                                                $team_members = json_decode($settings['team_members'] ?? '[]', true);
                                                if (empty($team_members) || !is_array($team_members)) {
                                                    $team_members = [
                                                        ['title' => 'Direction', 'role' => 'Patron', 'description' => 'Gestion générale et vision stratégique du Yellowjack.', 'icon' => 'fa-user-tie'],
                                                        ['title' => 'Management', 'role' => 'Responsables', 'description' => 'Supervision des opérations quotidiennes et gestion d\'équipe.', 'icon' => 'fa-user-cog'],
                                                        ['title' => 'Service', 'role' => 'CDI', 'description' => 'Service client et gestion de la caisse enregistreuse.', 'icon' => 'fa-cocktail'],
                                                        ['title' => 'Entretien', 'role' => 'CDD', 'description' => 'Maintien de la propreté et de l\'ambiance du bar.', 'icon' => 'fa-broom']
                                                    ];
                                                }
                                                
                                                // Afficher les membres de l'équipe
                                                $team_members_count = count($team_members);
                                                for ($i = 0; $i < $team_members_count; $i++) {
                                                    $member = $team_members[$i];
                                                ?>
                                                <div class="team-member-item mb-3 p-3 border rounded">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <div class="mb-3">
                                                                <label class="form-label">Icône</label>
                                                                <select class="form-select" name="team_members[<?php echo $i; ?>][icon]">
                                                                    <option value="fa-user-tie" <?php echo ($member['icon'] === 'fa-user-tie') ? 'selected' : ''; ?>>Costume (Direction)</option>
                                                                    <option value="fa-user-cog" <?php echo ($member['icon'] === 'fa-user-cog') ? 'selected' : ''; ?>>Engrenage (Management)</option>
                                                                    <option value="fa-cocktail" <?php echo ($member['icon'] === 'fa-cocktail') ? 'selected' : ''; ?>>Cocktail (Service)</option>
                                                                    <option value="fa-broom" <?php echo ($member['icon'] === 'fa-broom') ? 'selected' : ''; ?>>Balai (Entretien)</option>
                                                                    <option value="fa-user" <?php echo ($member['icon'] === 'fa-user') ? 'selected' : ''; ?>>Utilisateur (Général)</option>
                                                                    <option value="fa-star" <?php echo ($member['icon'] === 'fa-star') ? 'selected' : ''; ?>>Étoile</option>
                                                                    <option value="fa-shield-alt" <?php echo ($member['icon'] === 'fa-shield-alt') ? 'selected' : ''; ?>>Bouclier (Sécurité)</option>
                                                                    <option value="fa-cash-register" <?php echo ($member['icon'] === 'fa-cash-register') ? 'selected' : ''; ?>>Caisse</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="mb-3">
                                                                <label class="form-label">Titre</label>
                                                                <input type="text" class="form-control" name="team_members[<?php echo $i; ?>][title]" value="<?php echo htmlspecialchars($member['title']); ?>" placeholder="Ex: Direction">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="mb-3">
                                                                <label class="form-label">Rôle</label>
                                                                <input type="text" class="form-control" name="team_members[<?php echo $i; ?>][role]" value="<?php echo htmlspecialchars($member['role']); ?>" placeholder="Ex: Patron">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="d-flex justify-content-end align-items-end h-100">
                                                                <button type="button" class="btn btn-danger btn-sm remove-team-member">
                                                                    <i class="fas fa-trash"></i> Supprimer
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control" name="team_members[<?php echo $i; ?>][description]" rows="2" placeholder="Description du rôle"><?php echo htmlspecialchars($member['description']); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php } ?>
                                                
                                                <input type="hidden" id="team_members_count" name="team_members_count" value="<?php echo $team_members_count; ?>">
                                                
                                                <div class="d-grid gap-2 mt-3">
                                                    <button type="button" class="btn btn-success btn-sm" id="add-team-member">
                                                        <i class="fas fa-plus me-2"></i>Ajouter un membre d'équipe
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Section Contact -->
                                    <div class="col-12 mb-4">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header" style="background: linear-gradient(135deg, #00b894 0%, #00a085 100%); color: white;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0"><i class="fas fa-envelope me-2"></i>Section Contact</h6>
                                                    <span class="badge bg-light text-dark">Accessible</span>
                                                </div>
                                            </div>
                                            <div class="card-body p-4">
                                                <div class="row mb-4">
                                                    <div class="col-md-6">
                                                        <div class="form-floating">
                                                            <input type="text" class="form-control" id="vitrine_contact_title" name="vitrine_contact_title" 
                                                                   value="<?php echo htmlspecialchars($settings['contact_title'] ?? 'Nous Contacter'); ?>" placeholder="Titre">
                                                            <label for="vitrine_contact_title"><i class="fas fa-envelope me-2"></i>Titre de la section Contact</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-floating">
                                                            <input type="text" class="form-control" id="vitrine_contact_hours" name="vitrine_contact_hours" 
                                                                   value="<?php echo htmlspecialchars($settings['contact_hours'] ?? 'Ouvert 24h/24, 7j/7'); ?>" placeholder="Horaires">
                                                            <label for="vitrine_contact_hours"><i class="fas fa-clock me-2"></i>Horaires d'ouverture</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="../index.php" target="_blank" class="btn btn-outline-primary me-2">
                                            <i class="fas fa-external-link-alt me-2"></i>
                                            Voir la vitrine
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            Sauvegarder les modifications
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Onglet À propos -->
                    <div class="tab-pane fade" id="about" role="tabpanel">
                        <div class="container-fluid mt-3">
                            <div class="row">
                                <!-- En-tête principal -->
                                <div class="col-12 mb-4">
                                    <div class="card border-0 shadow-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                        <div class="card-body text-center py-5">
                                            <div class="mb-4">
                                                <i class="fas fa-hat-cowboy fa-4x mb-3" style="color: #ffd700;"></i>
                                            </div>
                                            <h2 class="display-5 fw-bold mb-3">🤠 Le Yellowjack</h2>
                                            <h4 class="mb-3">Panel de Gestion</h4>
                                            <p class="lead fs-5">Système de gestion complet pour bar western dans l'univers GTA V / FiveM</p>
                                            <div class="mt-4">
                                                <span class="badge bg-light text-dark fs-6 px-3 py-2 me-2">Version 1.0.0</span>
                                                <span class="badge bg-warning text-dark fs-6 px-3 py-2">Production Ready</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Fonctionnalités principales -->
                                <div class="col-md-8 mb-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%); color: white;">
                                            <h5 class="mb-0"><i class="fas fa-star me-2"></i>Fonctionnalités Principales</h5>
                                        </div>
                                        <div class="card-body p-4">
                                        
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                                        <div class="flex-shrink-0">
                                                            <i class="fas fa-broom fa-2x text-primary"></i>
                                                        </div>
                                                        <div class="flex-grow-1 ms-3">
                                                            <h6 class="mb-1">Gestion des Ménages</h6>
                                                            <small class="text-muted">Calcul automatique des salaires</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                                        <div class="flex-shrink-0">
                                                            <i class="fas fa-cash-register fa-2x text-success"></i>
                                                        </div>
                                                        <div class="flex-grow-1 ms-3">
                                                            <h6 class="mb-1">Caisse Enregistreuse</h6>
                                                            <small class="text-muted">Gestion des stocks intégrée</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                                        <div class="flex-shrink-0">
                                                            <i class="fas fa-percentage fa-2x text-warning"></i>
                                                        </div>
                                                        <div class="flex-grow-1 ms-3">
                                                            <h6 class="mb-1">Système de Commissions</h6>
                                                            <small class="text-muted">Motivation des employés</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                                        <div class="flex-shrink-0">
                                                            <i class="fas fa-users fa-2x text-info"></i>
                                                        </div>
                                                        <div class="flex-grow-1 ms-3">
                                                            <h6 class="mb-1">Clients Fidèles</h6>
                                                            <small class="text-muted">Système de réductions</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                                        <div class="flex-shrink-0">
                                                            <i class="fas fa-chart-line fa-2x text-danger"></i>
                                                        </div>
                                                        <div class="flex-grow-1 ms-3">
                                                            <h6 class="mb-1">Rapports & Analyses</h6>
                                                            <small class="text-muted">Données détaillées</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                                        <div class="flex-shrink-0">
                                                            <i class="fab fa-discord fa-2x" style="color: #7289da;"></i>
                                                        </div>
                                                        <div class="flex-grow-1 ms-3">
                                                            <h6 class="mb-1">Intégration Discord</h6>
                                                            <small class="text-muted">Notifications en temps réel</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Informations techniques -->
                                <div class="col-md-4 mb-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header" style="background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%); color: white;">
                                            <h5 class="mb-0"><i class="fas fa-code me-2"></i>Informations Techniques</h5>
                                        </div>
                                        <div class="card-body p-4 text-center">
                                            <i class="fas fa-server fa-3x text-muted mb-4"></i>
                                            <div class="table-responsive">
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <td class="text-start"><strong>Version :</strong></td>
                                                        <td class="text-end"><span class="badge bg-primary">1.0.0</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-start"><strong>Framework :</strong></td>
                                                        <td class="text-end"><span class="badge bg-info">PHP 8+ / MySQL</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-start"><strong>Interface :</strong></td>
                                                        <td class="text-end"><span class="badge bg-success">Bootstrap 5</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-start"><strong>Sécurité :</strong></td>
                                                        <td class="text-end"><span class="badge bg-warning text-dark">CSRF Protection</span></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="mt-4">
                                                <a href="../index.php" class="btn btn-outline-primary" target="_blank">
                                                    <i class="fas fa-external-link-alt me-2"></i>Site vitrine
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                        
                                <!-- Rôles disponibles -->
                                <div class="col-12 mb-4">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header" style="background: linear-gradient(135deg, #fd79a8 0%, #e84393 100%); color: white;">
                                            <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Rôles Disponibles</h5>
                                        </div>
                                        <div class="card-body p-4">
                                            <div class="row g-4">
                                                <div class="col-md-3">
                                                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%); color: white;">
                                                        <div class="card-body text-center p-4">
                                                            <i class="fas fa-user-clock fa-3x mb-3"></i>
                                                            <h5 class="card-title">CDD</h5>
                                                            <p class="card-text">Accès aux ménages uniquement</p>
                                                            <div class="mt-3">
                                                                <span class="badge bg-light text-dark">Niveau 1</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #00b894 0%, #00a085 100%); color: white;">
                                                        <div class="card-body text-center p-4">
                                                            <i class="fas fa-user-check fa-3x mb-3"></i>
                                                            <h5 class="card-title">CDI</h5>
                                                            <p class="card-text">Ménages + Caisse enregistreuse</p>
                                                            <div class="mt-3">
                                                                <span class="badge bg-light text-dark">Niveau 2</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #fdcb6e 0%, #e17055 100%); color: white;">
                                                        <div class="card-body text-center p-4">
                                                            <i class="fas fa-user-cog fa-3x mb-3"></i>
                                                            <h5 class="card-title">Responsable</h5>
                                                            <p class="card-text">Gestion complète + Rapports</p>
                                                            <div class="mt-3">
                                                                <span class="badge bg-light text-dark">Niveau 3</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="card border-0 h-100" style="background: linear-gradient(135deg, #e17055 0%, #d63031 100%); color: white;">
                                                        <div class="card-body text-center p-4">
                                                            <i class="fas fa-user-tie fa-3x mb-3"></i>
                                                            <h5 class="card-title">Patron</h5>
                                                            <p class="card-text">Accès total + Configuration</p>
                                                            <div class="mt-3">
                                                                <span class="badge bg-light text-dark">Niveau 4</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
        
        // Gestion des produits du menu (ajout/suppression)
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion de l'ajout d'un produit
            const addButtons = document.querySelectorAll('.add-menu-item');
            addButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const category = this.getAttribute('data-category');
                    const container = document.getElementById(`${category}-items-container`);
                    const countInput = document.getElementById(`${category}_items_count`);
                    
                    // Récupérer l'index actuel
                    let currentIndex = parseInt(countInput.value);
                    
            // Gestion des membres de l'équipe
            const addTeamMemberButton = document.getElementById('add-team-member');
            if (addTeamMemberButton) {
                addTeamMemberButton.addEventListener('click', function() {
                    const container = document.querySelector('.team-members-container');
                    const countInput = document.getElementById('team_members_count');
                    
                    // Récupérer l'index actuel
                    let currentIndex = parseInt(countInput.value);
                    
                    // Créer un nouveau membre d'équipe
                    const newMember = document.createElement('div');
                    newMember.className = 'team-member-item mb-3 p-3 border rounded';
                    newMember.innerHTML = `
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Icône</label>
                                    <select class="form-select" name="team_members[${currentIndex}][icon]">
                                        <option value="fa-user-tie">Costume (Direction)</option>
                                        <option value="fa-user-cog">Engrenage (Management)</option>
                                        <option value="fa-cocktail">Cocktail (Service)</option>
                                        <option value="fa-broom">Balai (Entretien)</option>
                                        <option value="fa-user">Utilisateur (Général)</option>
                                        <option value="fa-star">Étoile</option>
                                        <option value="fa-shield-alt">Bouclier (Sécurité)</option>
                                        <option value="fa-cash-register">Caisse</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Titre</label>
                                    <input type="text" class="form-control" name="team_members[${currentIndex}][title]" value="" placeholder="Ex: Direction">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Rôle</label>
                                    <input type="text" class="form-control" name="team_members[${currentIndex}][role]" value="" placeholder="Ex: Patron">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex justify-content-end align-items-end h-100">
                                    <button type="button" class="btn btn-danger btn-sm remove-team-member">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="team_members[${currentIndex}][description]" rows="2" placeholder="Description du rôle"></textarea>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Insérer le nouveau membre avant le bouton d'ajout
                    container.insertBefore(newMember, addTeamMemberButton.parentNode.parentNode);
                    
                    // Mettre à jour le compteur
                    countInput.value = currentIndex + 1;
                    
                    // Ajouter l'événement de suppression au nouveau bouton
                    const removeButton = newMember.querySelector('.remove-team-member');
                    removeButton.addEventListener('click', function() {
                        newMember.remove();
                    });
                });
            }
            
            // Gestion de la suppression des membres de l'équipe existants
            const removeTeamMemberButtons = document.querySelectorAll('.remove-team-member');
            removeTeamMemberButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const memberItem = this.closest('.team-member-item');
                    memberItem.remove();
                });
            });
                    
                    
                    // Créer un nouveau produit
                    const newItem = document.createElement('div');
                    newItem.className = 'menu-item-row border rounded p-3 mb-3';
                    newItem.innerHTML = `
                        <div class="row">
                            <div class="col-md-5 mb-2">
                                <label class="form-label">Nom du produit</label>
                                <input type="text" class="form-control" name="${category}_items[${currentIndex}][name]" value="">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Prix ($)</label>
                                <input type="text" class="form-control" name="${category}_items[${currentIndex}][price]" value="">
                            </div>
                            <div class="col-md-5 mb-2 d-flex align-items-end justify-content-between">
                                <div class="flex-grow-1 me-2">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control" name="${category}_items[${currentIndex}][description]" value="">
                                </div>
                                <button type="button" class="btn btn-outline-danger remove-menu-item">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Ajouter le nouveau produit au conteneur
                    container.appendChild(newItem);
                    
                    // Mettre à jour le compteur
                    countInput.value = currentIndex + 1;
                    
                    // Ajouter l'événement de suppression au nouveau bouton
                    addRemoveEventListeners();
                });
            });
            
            // Fonction pour ajouter les écouteurs d'événements de suppression
            function addRemoveEventListeners() {
                const removeButtons = document.querySelectorAll('.remove-menu-item');
                removeButtons.forEach(button => {
                    // Supprimer l'écouteur existant pour éviter les doublons
                    button.removeEventListener('click', removeItemHandler);
                    // Ajouter le nouvel écouteur
                    button.addEventListener('click', removeItemHandler);
                });
            }
            
            // Fonction de gestion de la suppression d'un produit
            function removeItemHandler() {
                if (confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) {
                    const itemRow = this.closest('.menu-item-row');
                    const category = itemRow.querySelector('input[name*="_items"]').name.split('_items')[0];
                    
                    // Supprimer l'élément
                    itemRow.remove();
                    
                    // Réindexer les éléments restants
                    reindexItems(category);
                }
            }
            
            // Fonction pour réindexer les éléments après suppression
            function reindexItems(category) {
                const container = document.getElementById(`${category}-items-container`);
                const items = container.querySelectorAll('.menu-item-row');
                const countInput = document.getElementById(`${category}_items_count`);
                
                items.forEach((item, index) => {
                    const inputs = item.querySelectorAll(`input[name*="${category}_items"]`);
                    inputs.forEach(input => {
                        const fieldName = input.name.split(']')[1]; // Récupérer [name], [price] ou [description]
                        input.name = `${category}_items[${index}]${fieldName}`;
                    });
                });
                
                // Mettre à jour le compteur
                countInput.value = items.length;
            }
            
            // Initialiser les écouteurs de suppression pour les éléments existants
            addRemoveEventListeners();
        });
    </script>
</body>
</html>