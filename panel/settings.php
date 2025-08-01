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
                
            case 'bot_action':
                $bot_action = $_POST['bot_action'] ?? '';
                
                switch ($bot_action) {
                    case 'test_webhook':
                        // Tester la connectivité du webhook Discord
                        $bot_url = 'https://yellow-jack.wstr.fr/bot/discord_bot.php';
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $bot_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($http_code === 405) {
                            $message = 'Bot Discord accessible ! (HTTP 405 - Méthode non autorisée est normal)';
                        } elseif ($http_code === 200) {
                            $message = 'Bot Discord accessible et fonctionnel !';
                        } else {
                            $error = 'Bot Discord non accessible. Code HTTP: ' . $http_code;
                        }
                        break;
                        
                    case 'register_commands':
                        // Enregistrer les commandes Discord
                        $register_path = realpath('../bot/register_commands.php');
                        if ($register_path && file_exists($register_path)) {
                            $output = shell_exec('php "' . $register_path . '" 2>&1');
                            if (strpos($output, 'success') !== false || strpos($output, 'Commands registered') !== false || strpos($output, 'succès') !== false) {
                                $message = 'Commandes Discord enregistrées avec succès !';
                            } else {
                                $error = 'Erreur lors de l\'enregistrement des commandes : ' . $output;
                            }
                        } else {
                            $error = 'Fichier d\'enregistrement des commandes introuvable.';
                        }
                        break;
                        
                    case 'check_config':
                        // Vérifier la configuration Discord
                        $config_issues = [];
                        
                        if (!defined('DISCORD_BOT_TOKEN') || empty(DISCORD_BOT_TOKEN)) {
                            $config_issues[] = 'Token du bot Discord manquant';
                        }
                        
                        if (!defined('DISCORD_APPLICATION_ID') || empty(DISCORD_APPLICATION_ID)) {
                            $config_issues[] = 'ID de l\'application Discord manquant';
                        }
                        
                        if (!defined('DISCORD_CLIENT_PUBLIC_KEY') || empty(DISCORD_CLIENT_PUBLIC_KEY)) {
                            $config_issues[] = 'Clé publique Discord manquante';
                        }
                        
                        if (empty($config_issues)) {
                            $message = 'Configuration Discord complète et valide !';
                        } else {
                            $error = 'Problèmes de configuration : ' . implode(', ', $config_issues);
                        }
                        break;
                        
                    default:
                        $error = 'Action de bot inconnue.';
                        break;
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
                        
                        <!-- Gestion du Bot Discord -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-robot me-2"></i>
                                    Gestion du Bot Discord
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Attention :</strong> Le bot Discord doit être configuré et les commandes enregistrées avant utilisation.
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-3">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Statut du Bot
                                        </h6>
                                        
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <?php
                                                // Vérifier le statut du bot via HTTP
                                                $bot_url = 'https://yellow-jack.wstr.fr/bot/discord_bot.php';
                                                $ch = curl_init();
                                                curl_setopt($ch, CURLOPT_URL, $bot_url);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                                                $response = curl_exec($ch);
                                                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                                curl_close($ch);
                                                
                                                $is_accessible = ($http_code === 405 || $http_code === 200);
                                                ?>
                                                
                                                <div class="mb-3">
                                                    <?php if ($is_accessible): ?>
                                                        <i class="fas fa-circle text-success fa-2x"></i>
                                                        <h6 class="text-success mt-2">Bot accessible</h6>
                                                        <small class="text-muted">Le webhook Discord est fonctionnel</small>
                                                    <?php else: ?>
                                                        <i class="fas fa-circle text-danger fa-2x"></i>
                                                        <h6 class="text-danger mt-2">Bot non accessible</h6>
                                                        <small class="text-muted">Le webhook Discord n'est pas accessible</small>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="small text-muted">
                                                    <strong>URL :</strong> <?php echo $bot_url; ?><br>
                                                    <strong>Code HTTP :</strong> <?php echo $http_code ?: 'Erreur'; ?><br>
                                                    <strong>Dernière vérification :</strong><br>
                                                    <?php echo date('d/m/Y H:i:s'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-3">
                                            <i class="fas fa-cogs me-2"></i>
                                            Actions du Bot
                                        </h6>
                                        
                                        <div class="d-grid gap-2">
                                            <!-- Tester le webhook -->
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                                <input type="hidden" name="action" value="bot_action">
                                                <input type="hidden" name="bot_action" value="test_webhook">
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="fas fa-globe me-2"></i>
                                                    Tester le Webhook
                                                </button>
                                            </form>
                                            
                                            <!-- Vérifier la configuration -->
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                                <input type="hidden" name="action" value="bot_action">
                                                <input type="hidden" name="bot_action" value="check_config">
                                                <button type="submit" class="btn btn-info w-100">
                                                    <i class="fas fa-cog me-2"></i>
                                                    Vérifier la Configuration
                                                </button>
                                            </form>
                                            
                                            <!-- Enregistrer les commandes -->
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                                <input type="hidden" name="action" value="bot_action">
                                                <input type="hidden" name="bot_action" value="register_commands">
                                                <button type="submit" class="btn btn-success w-100" 
                                                        onclick="return confirm('Enregistrer les commandes Discord ?')">
                                                    <i class="fas fa-terminal me-2"></i>
                                                    Enregistrer les Commandes
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <strong>Note :</strong> Enregistrez les commandes après chaque modification du bot.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Informations sur le bot -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <h6 class="text-muted mb-3">
                                            <i class="fas fa-file-alt me-2"></i>
                                            Informations du Bot
                                        </h6>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <tr>
                                                    <td><strong>Fichier principal :</strong></td>
                                                    <td>
                                                        <?php 
                                                        $bot_file = '../bot/discord_bot.php';
                                                        echo file_exists($bot_file) ? 
                                                            '<span class="badge bg-success">Présent</span> ' . realpath($bot_file) : 
                                                            '<span class="badge bg-danger">Manquant</span> ' . $bot_file;
                                                        ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Enregistrement des commandes :</strong></td>
                                                    <td>
                                                        <?php 
                                                        $register_file = '../bot/register_commands.php';
                                                        echo file_exists($register_file) ? 
                                                            '<span class="badge bg-success">Présent</span> ' . realpath($register_file) : 
                                                            '<span class="badge bg-danger">Manquant</span> ' . $register_file;
                                                        ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Configuration :</strong></td>
                                                    <td>
                                                        <?php 
                                                        $config_file = '../config/database.php';
                                                        echo file_exists($config_file) ? 
                                                            '<span class="badge bg-success">Présent</span>' : 
                                                            '<span class="badge bg-danger">Manquant</span>';
                                                        ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
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
                    
                    <!-- Onglet Commissions -->
                    <div class="tab-pane fade" id="commissions" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-percentage me-2"></i>
                                    Gestion des Commissions par Grade
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Information :</strong> Configurez les taux de commission pour les ventes et ménages selon le grade de l'employé.
                                </div>
                                
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                    <input type="hidden" name="action" value="update_commissions">
                                    
                                    <div class="row">
                                        <!-- Commissions Ventes -->
                                        <div class="col-md-6">
                                            <h6 class="text-primary mb-3">
                                                <i class="fas fa-shopping-cart me-2"></i>
                                                Commissions Ventes (%)
                                            </h6>
                                            
                                            <div class="mb-3">
                                                <label for="commission_cdd_sales" class="form-label">CDD</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="commission_cdd_sales" name="commission_cdd_sales" 
                                                           value="<?php echo htmlspecialchars($settings['commission_cdd_sales'] ?? '0'); ?>" 
                                                           step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="commission_cdi_sales" class="form-label">CDI</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="commission_cdi_sales" name="commission_cdi_sales" 
                                                           value="<?php echo htmlspecialchars($settings['commission_cdi_sales'] ?? '15'); ?>" 
                                                           step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="commission_responsable_sales" class="form-label">Responsable</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="commission_responsable_sales" name="commission_responsable_sales" 
                                                           value="<?php echo htmlspecialchars($settings['commission_responsable_sales'] ?? '20'); ?>" 
                                                           step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="commission_patron_sales" class="form-label">Patron</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="commission_patron_sales" name="commission_patron_sales" 
                                                           value="<?php echo htmlspecialchars($settings['commission_patron_sales'] ?? '25'); ?>" 
                                                           step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Commissions Ménages -->
                                        <div class="col-md-6">
                                            <h6 class="text-success mb-3">
                                                <i class="fas fa-broom me-2"></i>
                                                Taux Ménages ($/ménage)
                                            </h6>
                                            
                                            <div class="mb-3">
                                                <label for="cleaning_rate_cdd" class="form-label">CDD</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="cleaning_rate_cdd" name="cleaning_rate_cdd" 
                                                           value="<?php echo htmlspecialchars($settings['cleaning_rate_cdd'] ?? '50'); ?>" 
                                                           step="0.01" min="0.01">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="cleaning_rate_cdi" class="form-label">CDI</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="cleaning_rate_cdi" name="cleaning_rate_cdi" 
                                                           value="<?php echo htmlspecialchars($settings['cleaning_rate_cdi'] ?? '60'); ?>" 
                                                           step="0.01" min="0.01">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="cleaning_rate_responsable" class="form-label">Responsable</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="cleaning_rate_responsable" name="cleaning_rate_responsable" 
                                                           value="<?php echo htmlspecialchars($settings['cleaning_rate_responsable'] ?? '70'); ?>" 
                                                           step="0.01" min="0.01">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="cleaning_rate_patron" class="form-label">Patron</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="cleaning_rate_patron" name="cleaning_rate_patron" 
                                                           value="<?php echo htmlspecialchars($settings['cleaning_rate_patron'] ?? '80'); ?>" 
                                                           step="0.01" min="0.01">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Options supplémentaires -->
                                    <hr class="my-4">
                                    <h6 class="text-warning mb-3">
                                        <i class="fas fa-cogs me-2"></i>
                                        Options Supplémentaires
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="bonus_weekend_rate" class="form-label">Bonus Week-end (%)</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="bonus_weekend_rate" name="bonus_weekend_rate" 
                                                           value="<?php echo htmlspecialchars($settings['bonus_weekend_rate'] ?? '10'); ?>" 
                                                           step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                                <div class="form-text">Bonus appliqué les samedi et dimanche</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="bonus_night_rate" class="form-label">Bonus Nuit (%)</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="bonus_night_rate" name="bonus_night_rate" 
                                                           value="<?php echo htmlspecialchars($settings['bonus_night_rate'] ?? '15'); ?>" 
                                                           step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                                <div class="form-text">Bonus appliqué de 22h à 6h</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="enable_performance_bonus" name="enable_performance_bonus" 
                                                           <?php echo ($settings['enable_performance_bonus'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="enable_performance_bonus">
                                                        Activer les bonus de performance
                                                    </label>
                                                </div>
                                                <div class="form-text">Bonus automatiques basés sur les objectifs</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="enable_team_bonus" name="enable_team_bonus" 
                                                           <?php echo ($settings['enable_team_bonus'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="enable_team_bonus">
                                                        Activer les bonus d'équipe
                                                    </label>
                                                </div>
                                                <div class="form-text">Bonus partagés selon les performances globales</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            Sauvegarder les Commissions
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($auth->hasPermission('Patron')): ?>
                    <!-- Onglet Vitrine -->
                    <div class="tab-pane fade" id="vitrine" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-store me-2"></i>
                                    Configuration de la Vitrine
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                                    <input type="hidden" name="action" value="update_vitrine">
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Cette section vous permet de personnaliser la vitrine du site visible par les clients.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Informations générales -->
                                    <h6 class="mb-3 border-bottom pb-2"><i class="fas fa-building me-2"></i> Informations du Bar</h6>
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="vitrine_bar_name" class="form-label">Nom du bar *</label>
                                                <input type="text" class="form-control" id="vitrine_bar_name" name="vitrine_bar_name" 
                                                       value="<?php echo htmlspecialchars($settings['bar_name'] ?? ''); ?>" required>
                                                <div class="form-text">Nom affiché sur la vitrine</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="vitrine_bar_slogan" class="form-label">Slogan</label>
                                                <input type="text" class="form-control" id="vitrine_bar_slogan" name="vitrine_bar_slogan" 
                                                       value="<?php echo htmlspecialchars($settings['bar_slogan'] ?? 'L\'authentique bar western de Sandy Shore'); ?>">
                                                <div class="form-text">Court slogan affiché sous le nom du bar</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="vitrine_bar_address" class="form-label">Adresse</label>
                                                <input type="text" class="form-control" id="vitrine_bar_address" name="vitrine_bar_address" 
                                                       value="<?php echo htmlspecialchars($settings['bar_address'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="vitrine_bar_phone" class="form-label">Téléphone</label>
                                                <input type="text" class="form-control" id="vitrine_bar_phone" name="vitrine_bar_phone" 
                                                       value="<?php echo htmlspecialchars($settings['bar_phone'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Carte des boissons et plats -->
                                     <h6 class="mb-3 border-bottom pb-2"><i class="fas fa-utensils me-2"></i> Carte des Boissons et Plats</h6>
                                     
                                     <div class="row mb-4">
                                         <div class="col-md-12">
                                             <div class="mb-3">
                                                 <label for="vitrine_menu_title" class="form-label">Titre de la section Carte</label>
                                                 <input type="text" class="form-control" id="vitrine_menu_title" name="vitrine_menu_title" 
                                                        value="<?php echo htmlspecialchars($settings['menu_title'] ?? 'Notre Carte'); ?>">
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <div class="row mb-4">
                                         <div class="col-md-12">
                                             <div class="mb-3">
                                                 <label for="vitrine_menu_description" class="form-label">Description de la carte</label>
                                                 <textarea class="form-control" id="vitrine_menu_description" name="vitrine_menu_description" rows="2"><?php echo htmlspecialchars($settings['menu_description'] ?? 'Découvrez notre sélection de boissons et plats dans l\'esprit western'); ?></textarea>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Boissons Alcoolisées -->
                                     <div class="card mb-4">
                                         <div class="card-header bg-light">
                                             <h6 class="mb-0"><i class="fas fa-glass-whiskey me-2"></i> Boissons Alcoolisées</h6>
                                         </div>
                                         <div class="card-body">
                                             <div class="row mb-3">
                                                 <div class="col-md-12">
                                                     <label for="vitrine_alcool_title" class="form-label">Titre de la catégorie</label>
                                                     <div class="input-group">
                                                         <span class="input-group-text"><i class="fas fa-glass-whiskey"></i></span>
                                                         <input type="text" class="form-control" id="vitrine_alcool_title" name="vitrine_alcool_title" 
                                                                value="<?php echo htmlspecialchars($settings['alcool_title'] ?? 'Boissons Alcoolisées'); ?>">
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
                                     <div class="card mb-4">
                                         <div class="card-header bg-light">
                                             <h6 class="mb-0"><i class="fas fa-coffee me-2"></i> Boissons Non-Alcoolisées</h6>
                                         </div>
                                         <div class="card-body">
                                             <div class="row mb-3">
                                                 <div class="col-md-12">
                                                     <label for="vitrine_soft_title" class="form-label">Titre de la catégorie</label>
                                                     <div class="input-group">
                                                         <span class="input-group-text"><i class="fas fa-coffee"></i></span>
                                                         <input type="text" class="form-control" id="vitrine_soft_title" name="vitrine_soft_title" 
                                                                value="<?php echo htmlspecialchars($settings['soft_title'] ?? 'Boissons Non-Alcoolisées'); ?>">
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
                                     <div class="card mb-4">
                                         <div class="card-header bg-light">
                                             <h6 class="mb-0"><i class="fas fa-utensils me-2"></i> Snacks</h6>
                                         </div>
                                         <div class="card-body">
                                             <div class="row mb-3">
                                                 <div class="col-md-12">
                                                     <label for="vitrine_snacks_title" class="form-label">Titre de la catégorie</label>
                                                     <div class="input-group">
                                                         <span class="input-group-text"><i class="fas fa-utensils"></i></span>
                                                         <input type="text" class="form-control" id="vitrine_snacks_title" name="vitrine_snacks_title" 
                                                                value="<?php echo htmlspecialchars($settings['snacks_title'] ?? 'Snacks'); ?>">
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
                                     <div class="card mb-4">
                                         <div class="card-header bg-light">
                                             <h6 class="mb-0"><i class="fas fa-hamburger me-2"></i> Plats</h6>
                                         </div>
                                         <div class="card-body">
                                             <div class="row mb-3">
                                                 <div class="col-md-12">
                                                     <label for="vitrine_plats_title" class="form-label">Titre de la catégorie</label>
                                                     <div class="input-group">
                                                         <span class="input-group-text"><i class="fas fa-hamburger"></i></span>
                                                         <input type="text" class="form-control" id="vitrine_plats_title" name="vitrine_plats_title" 
                                                                value="<?php echo htmlspecialchars($settings['plats_title'] ?? 'Plats'); ?>">
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
                                    
                                    <!-- Équipe -->
                                    <h6 class="mb-3 border-bottom pb-2"><i class="fas fa-users me-2"></i> Section Équipe</h6>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="vitrine_team_title" class="form-label">Titre de la section Équipe</label>
                                                <input type="text" class="form-control" id="vitrine_team_title" name="vitrine_team_title" 
                                                       value="<?php echo htmlspecialchars($settings['team_title'] ?? 'Notre Équipe'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="vitrine_team_description" class="form-label">Description de l'équipe</label>
                                                <textarea class="form-control" id="vitrine_team_description" name="vitrine_team_description" rows="2"><?php echo htmlspecialchars($settings['team_description'] ?? 'Rencontrez l\'équipe passionnée du Yellowjack'); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Membres de l'équipe -->
                                    <div class="card mb-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-users me-2"></i> Membres de l'équipe</h6>
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
                                    
                                    <!-- Contact -->
                                    <h6 class="mb-3 border-bottom pb-2"><i class="fas fa-envelope me-2"></i> Section Contact</h6>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="vitrine_contact_title" class="form-label">Titre de la section Contact</label>
                                                <input type="text" class="form-control" id="vitrine_contact_title" name="vitrine_contact_title" 
                                                       value="<?php echo htmlspecialchars($settings['contact_title'] ?? 'Nous Contacter'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="vitrine_contact_hours" class="form-label">Horaires d'ouverture</label>
                                                <input type="text" class="form-control" id="vitrine_contact_hours" name="vitrine_contact_hours" 
                                                       value="<?php echo htmlspecialchars($settings['contact_hours'] ?? 'Ouvert 24h/24, 7j/7'); ?>">
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