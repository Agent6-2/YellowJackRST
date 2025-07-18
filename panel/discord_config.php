<?php
/**
 * Configuration du webhook Discord pour Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
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
            'notify_goals' => isset($_POST['notify_goals']),
            'notify_errors' => isset($_POST['notify_errors']),
            'notify_weekly_summary' => isset($_POST['notify_weekly_summary'])
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
                $message = 'Notification de vente de test envoyée avec succès !';
            } else {
                $error = 'Échec de l\'envoi de la notification de test.';
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fab fa-discord me-2 text-primary"></i>
                    Configuration Discord
                </h1>
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
                                <i class="fas fa-cog me-2"></i>
                                Configuration du Webhook
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="webhook_url" class="form-label">
                                        <i class="fas fa-link me-1"></i>
                                        URL du Webhook Discord
                                    </label>
                                    <input type="url" 
                                           class="form-control" 
                                           id="webhook_url" 
                                           name="webhook_url" 
                                           value="<?php echo htmlspecialchars($config['webhook_url'] ?? ''); ?>"
                                           placeholder="https://discord.com/api/webhooks/...">
                                    <div class="form-text">
                                        Pour obtenir cette URL :
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
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="notify_sales" 
                                                       name="notify_sales" 
                                                       <?php echo ($config['notify_sales'] ?? false) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="notify_sales">
                                                    💰 Nouvelles ventes
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="notify_goals" 
                                                       name="notify_goals" 
                                                       <?php echo ($config['notify_goals'] ?? false) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="notify_goals">
                                                    🎯 Objectifs atteints
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="notify_errors" 
                                                       name="notify_errors" 
                                                       <?php echo ($config['notify_errors'] ?? false) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="notify_errors">
                                                    ⚠️ Erreurs système
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="notify_weekly_summary" 
                                                       name="notify_weekly_summary" 
                                                       <?php echo ($config['notify_weekly_summary'] ?? false) ? 'checked' : ''; ?>>
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
                                Tests et Exemples
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Testez les différents types de notifications Discord :</p>
                            
                            <form method="POST" class="d-inline">
                                <button type="submit" name="send_test_sale" class="btn btn-outline-success me-2">
                                    <i class="fas fa-cash-register me-1"></i>
                                    Test Notification Vente
                                </button>
                            </form>
                            
                            <a href="../examples/discord_integration_example.php" 
                               class="btn btn-outline-info me-2" 
                               target="_blank">
                                <i class="fas fa-code me-1"></i>
                                Voir les Exemples
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Informations et Statut -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Statut Discord
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Configuration actuelle :</strong>
                                <br>
                                <?php if (!empty(DISCORD_WEBHOOK_URL)): ?>
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
                                <strong>Types de notifications :</strong>
                                <ul class="list-unstyled mt-2 small">
                                    <li><i class="fas fa-check text-success me-1"></i> Nouvelles ventes</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Objectifs atteints</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Erreurs système</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Résumés hebdomadaires</li>
                                </ul>
                            </div>
                            
                            <div class="alert alert-info small">
                                <i class="fas fa-lightbulb me-1"></i>
                                <strong>Conseil :</strong> Créez un canal dédié dans votre serveur Discord pour les notifications du Yellowjack.
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

<?php include __DIR__ . '/../includes/footer.php'; ?>