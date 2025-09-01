<?php
/**
 * Configuration des webhooks Discord pour Le Yellowjack
 * Notifications pour ventes et services uniquement
 * 
 * @author Développeur Web Professionnel
 * @version 2.0
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/discord_webhook.php';
require_once __DIR__ . '/../includes/discord_config.php';

// Vérifier l'authentification et les permissions
$auth = getAuth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$auth->canManageEmployees()) {
    header('Location: dashboard.php');
    exit;
}

$user = $auth->getCurrentUser();
$message = '';
$error = '';

// Charger la configuration Discord
$discordConfig = getDiscordConfig();
$config = $discordConfig->getConfig();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_webhook'])) {
        // Test du webhook
        $testResult = $discordConfig->testWebhook();
        if ($testResult['success']) {
            $message = "Test réussi ! Le webhook Discord fonctionne correctement.";
        } else {
            $error = "Erreur lors du test : " . $testResult['error'];
        }
    } elseif (isset($_POST['save_config'])) {
        // Sauvegarde de la configuration
        $newConfig = [
            'webhook_url' => $_POST['webhook_url'] ?? '',
            'notifications_enabled' => isset($_POST['notifications_enabled']),
            'notify_sales' => isset($_POST['notify_sales']),
            'notify_cleaning' => isset($_POST['notify_cleaning']),
            'notify_goals' => isset($_POST['notify_goals']),
            'notify_weekly_summary' => isset($_POST['notify_weekly_summary']),
            'multi_webhook_enabled' => isset($_POST['multi_webhook_enabled']),
            'webhook_sales' => $_POST['webhook_sales'] ?? '',
            'webhook_cleaning' => $_POST['webhook_cleaning'] ?? '',
            'webhook_goals' => $_POST['webhook_goals'] ?? '',
            'webhook_weekly' => $_POST['webhook_weekly'] ?? ''
        ];
        
        if ($discordConfig->saveConfig($newConfig)) {
            $message = "Configuration sauvegardée avec succès !";
            $config = $discordConfig->getConfig(); // Recharger la config
        } else {
            $error = "Erreur lors de la sauvegarde de la configuration.";
        }
    } elseif (isset($_POST['send_test_sale'])) {
        // Envoyer une notification de vente de test
        if (empty($discordConfig->getWebhookUrl())) {
            $error = 'Webhook Discord non configuré.';
        } else {
            $webhook = new DiscordWebhook($discordConfig->getWebhookUrl());
            $test_sale_data = [
                'id' => 9999,
                'employee_name' => $user['first_name'] . ' ' . $user['last_name'],
                'customer_name' => 'Client Test',
                'final_amount' => 125.50,
                'discount_amount' => 12.50,
                'employee_commission' => 31.38
            ];
            
            $result = $webhook->notifySale($test_sale_data);
            
            if ($result) {
                $message = 'Webhook de vente de test envoyé avec succès !';
            } else {
                $error = 'Échec de l\'envoi du webhook de test.';
            }
        }
    } elseif (isset($_POST['send_test_cleaning'])) {
        // Envoyer une notification de service de ménage de test
        if (empty($discordConfig->getWebhookUrl())) {
            $error = 'Webhook Discord non configuré.';
        } else {
            $webhook = new DiscordWebhook($discordConfig->getWebhookUrl());
            
            // Créer un embed pour le service de ménage
            $embed = [
                'title' => '🧹 Nouveau Service de Ménage',
                'color' => 3447003, // Bleu
                'fields' => [
                    [
                        'name' => 'Employé',
                        'value' => $user['first_name'] . ' ' . $user['last_name'],
                        'inline' => true
                    ],
                    [
                        'name' => 'Client',
                        'value' => 'Client Test Ménage',
                        'inline' => true
                    ],
                    [
                        'name' => 'Type de service',
                        'value' => 'Ménage complet',
                        'inline' => true
                    ],
                    [
                        'name' => 'Montant',
                        'value' => '85.00 €',
                        'inline' => true
                    ]
                ],
                'timestamp' => date('c'),
                'footer' => [
                    'text' => 'YellowJack - Test Webhook'
                ]
            ];
            
            $result = $webhook->sendEmbed('🧹 **Service de ménage de test**', $embed);
            
            if ($result) {
                $message = 'Webhook de service de ménage de test envoyé avec succès !';
            } else {
                $error = 'Échec de l\'envoi du webhook de test.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhooks Discord - Le Yellowjack</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/panel.css" rel="stylesheet">
    
    <style>
        .sidebar {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .navbar {
            background-color: #343a40 !important;
        }
        .nav-link.active {
            background-color: #007bff;
            color: white !important;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-webhook me-2 text-primary"></i>
                    Webhooks Discord
                </h1>
                <small class="text-muted">Notifications pour ventes et services</small>
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
            
            <div class="row">
                <!-- Configuration du Webhook -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-webhook me-2"></i>
                                Configuration des Webhooks
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <!-- Option Webhooks Multiples -->
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="multi_webhook_enabled" 
                                               name="multi_webhook_enabled" 
                                               <?php echo ($config['multi_webhook_enabled'] ?? false) ? 'checked' : ''; ?>
                                               onchange="toggleWebhookMode()">
                                        <label class="form-check-label" for="multi_webhook_enabled">
                                            <strong>🔀 Utiliser des webhooks séparés par catégorie</strong>
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Activez cette option pour utiliser un webhook différent pour chaque type de notification.
                                    </div>
                                </div>

                                <!-- Webhook Unique (Mode classique) -->
                                <div id="single_webhook_section" class="mb-3">
                                    <label for="webhook_url" class="form-label">
                                        <i class="fas fa-link me-1"></i>
                                        URL du Webhook Discord Principal
                                    </label>
                                    <input type="url" 
                                           class="form-control" 
                                           id="webhook_url" 
                                           name="webhook_url" 
                                           value="<?php echo htmlspecialchars($config['webhook_url'] ?? ''); ?>"
                                           placeholder="https://discord.com/api/webhooks/...">
                                    <div class="form-text">
                                        Ce webhook sera utilisé pour toutes les notifications.
                                    </div>
                                </div>

                                <!-- Webhooks Multiples -->
                                <div id="multi_webhook_section" class="mb-3" style="display: none;">
                                    <h6 class="mb-3">
                                        <i class="fas fa-sitemap me-2"></i>
                                        Configuration des Webhooks par Catégorie
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="webhook_sales" class="form-label">
                                                💰 Nouvelles Ventes
                                            </label>
                                            <input type="url" 
                                                   class="form-control" 
                                                   id="webhook_sales" 
                                                   name="webhook_sales" 
                                                   value="<?php echo htmlspecialchars($config['webhook_sales'] ?? ''); ?>"
                                                   placeholder="https://discord.com/api/webhooks/...">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="webhook_cleaning" class="form-label">
                                                🧹 Services de Ménage
                                            </label>
                                            <input type="url" 
                                                   class="form-control" 
                                                   id="webhook_cleaning" 
                                                   name="webhook_cleaning" 
                                                   value="<?php echo htmlspecialchars($config['webhook_cleaning'] ?? ''); ?>"
                                                   placeholder="https://discord.com/api/webhooks/...">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="webhook_goals" class="form-label">
                                                🎯 Objectifs Atteints
                                            </label>
                                            <input type="url" 
                                                   class="form-control" 
                                                   id="webhook_goals" 
                                                   name="webhook_goals" 
                                                   value="<?php echo htmlspecialchars($config['webhook_goals'] ?? ''); ?>"
                                                   placeholder="https://discord.com/api/webhooks/...">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="webhook_weekly" class="form-label">
                                                📊 Résumés Hebdomadaires
                                            </label>
                                            <input type="url" 
                                                   class="form-control" 
                                                   id="webhook_weekly" 
                                                   name="webhook_weekly" 
                                                   value="<?php echo htmlspecialchars($config['webhook_weekly'] ?? ''); ?>"
                                                   placeholder="https://discord.com/api/webhooks/...">
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Astuce :</strong> Vous pouvez créer des canaux Discord séparés pour chaque type de notification et configurer un webhook pour chacun.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-text">
                                        <strong>Pour obtenir une URL de webhook :</strong>
                                        <ol class="small mt-2">
                                            <li>Allez dans votre serveur Discord</li>
                                            <li>Paramètres du serveur → Intégrations → Webhooks</li>
                                            <li>Créer un webhook → Copier l'URL</li>
                                        </ol>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="notifications_enabled" 
                                               name="notifications_enabled" 
                                               <?php echo ($config['notifications_enabled'] ?? false) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notifications_enabled">
                                            <strong>Activer les webhooks Discord</strong>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-bell me-1"></i>
                                        Types de notifications webhook
                                    </label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="notify_sales" 
                                                       name="notify_sales" 
                                                       <?php echo ($config['notify_sales'] ?? true) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="notify_sales">
                                                    💰 Nouvelles ventes
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="notify_cleaning" 
                                                       name="notify_cleaning" 
                                                       <?php echo ($config['notify_cleaning'] ?? true) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="notify_cleaning">
                                                    🧹 Services de ménage
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="notify_goals" 
                                                       name="notify_goals" 
                                                       <?php echo ($config['notify_goals'] ?? true) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="notify_goals">
                                                    🎯 Objectifs atteints
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="notify_weekly_summary" 
                                                       name="notify_weekly_summary" 
                                                       <?php echo ($config['notify_weekly_summary'] ?? true) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="notify_weekly_summary">
                                                    📊 Résumés hebdomadaires
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex">
                                    <button type="submit" name="test_webhook" class="btn btn-outline-primary">
                                        <i class="fas fa-vial me-2"></i>
                                        Tester le Webhook
                                    </button>
                                    <button type="submit" name="save_config" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        Sauvegarder
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Tests et Exemples -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-flask me-2"></i>
                                Tests des Webhooks
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Testez les différents types de webhooks Discord :</p>
                            
                            <div class="d-flex flex-wrap gap-2">
                                <form method="POST" class="d-inline">
                                    <button type="submit" name="send_test_sale" class="btn btn-outline-success">
                                        <i class="fas fa-cash-register me-1"></i>
                                        Test Vente
                                    </button>
                                </form>
                                
                                <form method="POST" class="d-inline">
                                    <button type="submit" name="send_test_cleaning" class="btn btn-outline-primary">
                                        <i class="fas fa-broom me-1"></i>
                                        Test Ménage
                                    </button>
                                </form>
                                
                                <a href="../examples/discord_integration_example.php" 
                                   class="btn btn-outline-info" 
                                   target="_blank">
                                    <i class="fas fa-code me-1"></i>
                                    Exemples
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Informations et Statut -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Statut des Webhooks
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Configuration actuelle :</strong>
                                <br>
                                <?php if (!empty($config['webhook_url'])): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check me-1"></i>
                                        Configuré
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Non configuré
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Types de webhooks disponibles :</strong>
                                <ul class="list-unstyled mt-2 small">
                                    <li><i class="fas fa-check text-success me-1"></i> Nouvelles ventes</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Services de ménage</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Objectifs atteints</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Résumés hebdomadaires</li>
                                </ul>
                            </div>
                            
                            <div class="alert alert-info small">
                                <i class="fas fa-lightbulb me-1"></i>
                                <strong>Conseil :</strong> Créez un canal dédié dans votre serveur Discord pour recevoir les webhooks du Yellowjack.
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-book me-2"></i>
                                Documentation
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">Ressources utiles :</p>
                            <ul class="list-unstyled small">
                                <li>
                                    <a href="https://support.discord.com/hc/fr/articles/228383668" 
                                       target="_blank" 
                                       class="text-decoration-none">
                                        <i class="fas fa-external-link-alt me-1"></i>
                                        Guide Discord Webhooks
                                    </a>
                                </li>
                                <li class="mt-2">
                                    <a href="../examples/discord_integration_example.php" 
                                       target="_blank" 
                                       class="text-decoration-none">
                                        <i class="fas fa-code me-1"></i>
                                        Exemples d'intégration
                                    </a>
                                </li>
                            </ul>
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
/**
 * Gestion de l'affichage des sections webhook
 */
function toggleWebhookMode() {
    const multiEnabled = document.getElementById('multi_webhook_enabled').checked;
    const singleSection = document.getElementById('single_webhook_section');
    const multiSection = document.getElementById('multi_webhook_section');
    
    if (multiEnabled) {
        singleSection.style.display = 'none';
        multiSection.style.display = 'block';
    } else {
        singleSection.style.display = 'block';
        multiSection.style.display = 'none';
    }
}

// Initialiser l'affichage au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    toggleWebhookMode();
});
</script>

</body>
</html>