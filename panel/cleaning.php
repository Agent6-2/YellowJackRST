<?php
/**
 * Gestion des ménages - Panel Employé Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/week_functions.php';

// Vérifier l'authentification
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

// Récupérer le taux de ménage depuis les paramètres
$stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'cleaning_rate'");
$stmt->execute();
$cleaning_rate_setting = $stmt->fetchColumn();
if (!defined('CLEANING_RATE')) {
    define('CLEANING_RATE', floatval($cleaning_rate_setting ?: 60));
}

// Récupérer le taux de ménage spécifique à l'utilisateur
$user_cleaning_rate_key = '';
switch ($user['role']) {
    case 'CDD':
        $user_cleaning_rate_key = 'cleaning_rate_cdd';
        break;
    case 'CDI':
        $user_cleaning_rate_key = 'cleaning_rate_cdi';
        break;
    case 'Responsable':
        $user_cleaning_rate_key = 'cleaning_rate_responsable';
        break;
    case 'Patron':
    case 'Co-patron':
        $user_cleaning_rate_key = 'cleaning_rate_patron';
        break;
    default:
        $user_cleaning_rate_key = 'cleaning_rate_cdd';
}

$stmt_user_rate = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
$stmt_user_rate->execute([$user_cleaning_rate_key]);
$user_cleaning_rate = floatval($stmt_user_rate->fetchColumn() ?: CLEANING_RATE);

// Les fonctions getCurrentDateTime() et calculateDuration() sont maintenant définies dans config/database.php

$message = '';
$error = '';

// Gérer les messages de redirection
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

// Vérifier s'il y a un service en cours
$stmt = $db->prepare("SELECT * FROM cleaning_services WHERE user_id = ? AND status = 'in_progress' ORDER BY start_time DESC LIMIT 1");
$stmt->execute([$user['id']]);
$current_session = $stmt->fetch();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Token de sécurité invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'start_service':
                if (!$current_session) {
                    try {
                        // Vérifier qu'il y a une semaine active pour enregistrer le service
                        $activeWeek = getActiveWeekNew();
                        if (!$activeWeek) {
                            throw new Exception('Aucune semaine active trouvée. Veuillez contacter un administrateur.');
                        }
                        
                        $stmt = $db->prepare("INSERT INTO cleaning_services (user_id, start_time) VALUES (?, ?)");
                        $stmt->execute([$user['id'], getCurrentDateTime()]);
                        $service_id = $db->lastInsertId();
                        
                        // Assigner le service à la semaine active
                        assignToActiveWeek('cleaning_services', $service_id);
                        
                        $message = 'Service démarré avec succès !';
                        
                        // Rediriger pour éviter la resoumission du formulaire et forcer la mise à jour de l'affichage
                        header('Location: cleaning.php?success=1&message=' . urlencode($message));
                        exit;
                    } catch (Exception $e) {
                        $error = 'Erreur lors du démarrage du service: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Un service est déjà en cours.';
                }
                break;
                
            case 'end_service':
                if ($current_session) {
                    $cleaning_count = intval($_POST['cleaning_count'] ?? 0);
                    
                    if ($cleaning_count < 0) {
                        $error = 'Le nombre de ménages ne peut pas être négatif.';
                    } else {
                        try {
                            $end_time = getCurrentDateTime();
                            $duration_data = calculateDuration($current_session['start_time'], $end_time);
                            $duration = $duration_data['total_minutes'];
                            
                            // Chaque ménage rapporte 60$ à l'entreprise
                            $company_revenue_per_cleaning = 60;
                            $total_company_revenue = $cleaning_count * $company_revenue_per_cleaning;
                            
                            // Récupérer le pourcentage de commission selon le rôle depuis les paramètres
                            $cleaning_rate_setting_key = '';
                            
                            switch ($user['role']) {
                                case 'CDD':
                                    $cleaning_rate_setting_key = 'cleaning_rate_cdd';
                                    break;
                                case 'CDI':
                                    $cleaning_rate_setting_key = 'cleaning_rate_cdi';
                                    break;
                                case 'Responsable':
                                    $cleaning_rate_setting_key = 'cleaning_rate_responsable';
                                    break;
                                case 'Patron':
                                case 'Co-patron':
                                    $cleaning_rate_setting_key = 'cleaning_rate_patron';
                                    break;
                                default:
                                    $cleaning_rate_setting_key = 'cleaning_rate_cdd'; // Par défaut CDD
                            }
                            
                            // Récupérer le pourcentage depuis la base de données
                            $stmt_rate = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
                            $stmt_rate->execute([$cleaning_rate_setting_key]);
                            $user_cleaning_percentage = floatval($stmt_rate->fetchColumn() ?: 25); // 25% par défaut
                            
                            // Calculer le salaire de l'employé (pourcentage du revenu de l'entreprise)
                            $salary = ($total_company_revenue * $user_cleaning_percentage) / 100;
                            
                            $stmt = $db->prepare("
                                UPDATE cleaning_services 
                                SET end_time = ?, cleaning_count = ?, duration_minutes = ?, total_salary = ?, status = 'completed' 
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $end_time,
                                $cleaning_count,
                                $duration,
                                $salary,
                                $current_session['id']
                            ]);
                            
                            $message = "Service terminé ! Durée: {$duration} minutes, Ménages: {$cleaning_count}, Salaire: {$salary}$";
                            $current_session = null;
                            
                            // Rediriger pour éviter la resoumission du formulaire et forcer la mise à jour de l'affichage
                            header('Location: cleaning.php?success=1&message=' . urlencode($message));
                            exit;
                        } catch (Exception $e) {
                            $error = 'Erreur lors de la fin du service.';
                        }
                    }
                } else {
                    $error = 'Aucun service en cours.';
                }
                break;
        }
    }
}

// Récupérer les statistiques du jour
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as sessions_today,
        COALESCE(SUM(cleaning_count), 0) as total_cleaning_today,
        COALESCE(SUM(total_salary), 0) as total_salary_today,
        COALESCE(SUM(duration_minutes), 0) as total_duration_today
    FROM cleaning_services 
    WHERE user_id = ? AND DATE(start_time) = ?
");
$stmt->execute([$user['id'], $today]);
$today_stats = $stmt->fetch();

// Récupérer les dernières sessions
$stmt = $db->prepare("
    SELECT * FROM cleaning_services 
    WHERE user_id = ? AND end_time IS NOT NULL 
    ORDER BY start_time DESC 
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recent_sessions = $stmt->fetchAll();

// Récupérer le pourcentage de commission de l'utilisateur pour l'affichage
$cleaning_rate_setting_key = '';
switch ($user['role']) {
    case 'CDD':
        $cleaning_rate_setting_key = 'cleaning_rate_cdd';
        break;
    case 'CDI':
        $cleaning_rate_setting_key = 'cleaning_rate_cdi';
        break;
    case 'Responsable':
        $cleaning_rate_setting_key = 'cleaning_rate_responsable';
        break;
    case 'Patron':
    case 'Co-patron':
        $cleaning_rate_setting_key = 'cleaning_rate_patron';
        break;
    default:
        $cleaning_rate_setting_key = 'cleaning_rate_cdd';
}

$stmt_rate = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
$stmt_rate->execute([$cleaning_rate_setting_key]);
$user_cleaning_percentage = floatval($stmt_rate->fetchColumn() ?: 25);

$page_title = 'Gestion des Ménages';
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
                        <i class="fas fa-broom me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="cleaning_history.php" class="btn btn-outline-secondary">
                                <i class="fas fa-history me-1"></i>
                                Historique
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
                
                <!-- Statistiques du jour -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-calendar-day fa-2x text-primary mb-2"></i>
                                <h5 class="card-title"><?php echo $today_stats['sessions_today']; ?></h5>
                                <p class="card-text text-muted">Services aujourd'hui</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-broom fa-2x text-success mb-2"></i>
                                <h5 class="card-title"><?php echo $today_stats['total_cleaning_today']; ?></h5>
                                <p class="card-text text-muted">Ménages effectués</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                <h5 class="card-title"><?php echo floor($today_stats['total_duration_today'] / 60); ?>h <?php echo $today_stats['total_duration_today'] % 60; ?>m</h5>
                                <p class="card-text text-muted">Temps travaillé</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-dollar-sign fa-2x text-info mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($today_stats['total_salary_today'], 0); ?>$</h5>
                                <p class="card-text text-muted">Salaire du jour</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Service en cours -->
                <?php if ($current_session): ?>
                    <div class="card border-warning mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-play-circle me-2"></i>
                                Service en cours
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Début:</strong> <?php echo formatDateTime($current_session['start_time']); ?></p>
                                    <p><strong>Durée actuelle:</strong> <span id="current-duration"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#endServiceModal">
                                        <i class="fas fa-stop me-2"></i>
                                        Terminer le service
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Démarrer un service -->
                    <div class="card border-success mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-play me-2"></i>
                                Nouveau service
                            </h5>
                        </div>
                        <div class="card-body">
                            <p>Aucun service en cours. Cliquez sur le bouton ci-dessous pour démarrer votre service de ménage.</p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="start_service">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-play me-2"></i>
                                    Démarrer le service
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Dernières sessions -->
                <?php if (!empty($recent_sessions)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Dernières sessions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Début</th>
                                            <th>Fin</th>
                                            <th>Durée</th>
                                            <th>Ménages</th>
                                            <th>Salaire</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_sessions as $session): ?>
                                            <tr>
                                                <td><?php echo formatDateTime($session['start_time'], 'd/m/Y'); ?></td>
                                                <td><?php echo formatDateTime($session['start_time'], 'H:i'); ?></td>
                                                <td><?php echo formatDateTime($session['end_time'], 'H:i'); ?></td>
                                                <td><?php echo floor($session['duration_minutes'] / 60); ?>h <?php echo $session['duration_minutes'] % 60; ?>m</td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $session['cleaning_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-success fw-bold"><?php echo number_format($session['total_salary'], 0); ?>$</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Modal de fin de service -->
    <div class="modal fade" id="endServiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-stop-circle me-2"></i>
                            Terminer le service
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="end_service">
                        
                        <div class="mb-3">
                            <label for="cleaning_count" class="form-label">Nombre de ménages effectués</label>
                            <input type="number" class="form-control" id="cleaning_count" name="cleaning_count" min="0" required>
                            <div class="form-text">
                                Commission: <?php echo $user_cleaning_percentage; ?>% sur 60$ par ménage (<?php echo $user['role']; ?>)
                                <br><small class="text-muted">Salaire par ménage: <?php echo number_format((60 * $user_cleaning_percentage) / 100, 2); ?>$</small>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Le salaire sera calculé automatiquement en fonction du nombre de ménages déclarés.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-stop me-2"></i>
                            Terminer le service
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mise à jour de la durée en temps réel
        <?php if ($current_session): ?>
        function updateCurrentDuration() {
            const startTime = new Date('<?php echo $current_session['start_time']; ?>');
            const now = new Date();
            const diffMs = now - startTime;
            const diffMins = Math.floor(diffMs / 60000);
            const hours = Math.floor(diffMins / 60);
            const minutes = diffMins % 60;
            
            document.getElementById('current-duration').textContent = hours + 'h ' + minutes + 'm';
        }
        
        // Mettre à jour toutes les minutes
        updateCurrentDuration();
        setInterval(updateCurrentDuration, 60000);
        <?php endif; ?>
        
        // Calcul automatique du salaire dans le modal
        document.getElementById('cleaning_count').addEventListener('input', function() {
            const count = parseInt(this.value) || 0;
            const percentage = <?php echo $user_cleaning_percentage; ?>;
            const revenuePerCleaning = 60;
            const salaryPerCleaning = (revenuePerCleaning * percentage) / 100;
            const salary = count * salaryPerCleaning;
            
            // Mettre à jour l'affichage du salaire
            let salaryDisplay = document.getElementById('salary-display');
            if (!salaryDisplay) {
                salaryDisplay = document.createElement('div');
                salaryDisplay.id = 'salary-display';
                salaryDisplay.className = 'mt-2 fw-bold text-success';
                this.parentNode.appendChild(salaryDisplay);
            }
            salaryDisplay.textContent = 'Salaire calculé: ' + salary.toFixed(2) + '$';
        });
    </script>
</body>
</html>