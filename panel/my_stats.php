<?php
/**
 * Page des statistiques personnelles - Panel Employé Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

// Traitement de la mise à jour des objectifs
if ($_POST && isset($_POST['update_goals'])) {
    try {
        $goals_to_update = [
            'menages_mensuel' => floatval($_POST['menages_mensuel'] ?? 0),
            'salaire_mensuel' => floatval($_POST['salaire_mensuel'] ?? 0),
            'ventes_mensuelles' => floatval($_POST['ventes_mensuelles'] ?? 0),
            'commissions_mensuelles' => floatval($_POST['commissions_mensuelles'] ?? 0)
        ];
        
        foreach ($goals_to_update as $goal_type => $target_value) {
            if ($target_value > 0) {
                $stmt = $db->prepare("
                    INSERT INTO user_goals (user_id, goal_type, target_value) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE target_value = VALUES(target_value), updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$user['id'], $goal_type, $target_value]);
            }
        }
        
        $success_message = "Objectifs mis à jour avec succès !";
    } catch (Exception $e) {
        $error_message = "Erreur lors de la mise à jour des objectifs : " . $e->getMessage();
    }
}

// Période par défaut (ce mois)
$period = $_GET['period'] ?? 'month';
$start_date = '';
$end_date = '';
$period_label = '';

// Calculer les dates selon la période
switch ($period) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        $period_label = "Aujourd'hui";
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        $period_label = "Cette semaine";
        break;
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        $period_label = "Ce mois";
        break;
    case 'quarter':
        $quarter = ceil(date('n') / 3);
        $start_date = date('Y-' . sprintf('%02d', ($quarter - 1) * 3 + 1) . '-01');
        $end_date = date('Y-m-t', strtotime($start_date . ' +2 months'));
        $period_label = "Ce trimestre";
        break;
    case 'year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        $period_label = "Cette année";
        break;
    case 'custom':
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        $period_label = "Période personnalisée";
        break;
}

// Statistiques de ménage
$cleaning_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_services,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as services_termines,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as services_en_cours,
            SUM(cleaning_count) as total_menages,
            SUM(total_salary) as total_salaire,
            AVG(total_salary) as salaire_moyen,
            SUM(duration_minutes) as total_minutes
        FROM cleaning_services 
        WHERE user_id = ? AND DATE(start_time) >= ? AND DATE(start_time) <= ?
    ");
    $stmt->execute([$user['id'], $start_date, $end_date]);
    $cleaning_stats = $stmt->fetch();
    
    // Convertir les minutes en heures
    $cleaning_stats['total_hours'] = round($cleaning_stats['total_minutes'] / 60, 1);
} catch (Exception $e) {
    $cleaning_stats = [
        'total_services' => 0,
        'services_termines' => 0,
        'services_en_cours' => 0,
        'total_menages' => 0,
        'total_salaire' => 0,
        'salaire_moyen' => 0,
        'total_minutes' => 0,
        'total_hours' => 0
    ];
}

// Statistiques de vente (si autorisé)
$sales_stats = [];
if ($auth->canAccessCashRegister()) {
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_ventes,
                SUM(final_amount) as ca_total,
                SUM(employee_commission) as commissions_total,
                AVG(final_amount) as panier_moyen,
                SUM(CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END) as ventes_clients_fideles
            FROM sales 
            WHERE user_id = ? AND DATE(created_at) >= ? AND DATE(created_at) <= ?
        ");
        $stmt->execute([$user['id'], $start_date, $end_date]);
        $sales_stats = $stmt->fetch();
    } catch (Exception $e) {
        $sales_stats = [
            'total_ventes' => 0,
            'ca_total' => 0,
            'commissions_total' => 0,
            'panier_moyen' => 0,
            'ventes_clients_fideles' => 0
        ];
    }
}

// Évolution des performances (derniers 7 jours)
$performance_data = [];
try {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        
        // Ménages du jour
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(cleaning_count), 0) as menages,
                COALESCE(SUM(total_salary), 0) as salaire
            FROM cleaning_services 
            WHERE user_id = ? AND DATE(start_time) = ?
        ");
        $stmt->execute([$user['id'], $date]);
        $cleaning_day = $stmt->fetch();
        
        $day_data = [
            'date' => $date,
            'date_label' => formatDate($date, 'd/m'),
            'menages' => $cleaning_day['menages'],
            'salaire' => $cleaning_day['salaire']
        ];
        
        // Ventes du jour (si autorisé)
        if ($auth->canAccessCashRegister()) {
            $stmt = $db->prepare("
                SELECT 
                    COALESCE(COUNT(*), 0) as ventes,
                    COALESCE(SUM(employee_commission), 0) as commissions
                FROM sales 
                WHERE user_id = ? AND DATE(created_at) = ?
            ");
            $stmt->execute([$user['id'], $date]);
            $sales_day = $stmt->fetch();
            
            $day_data['ventes'] = $sales_day['ventes'];
            $day_data['commissions'] = $sales_day['commissions'];
        }
        
        $performance_data[] = $day_data;
    }
} catch (Exception $e) {
    // En cas d'erreur, créer des données vides
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $performance_data[] = [
            'date' => $date,
            'date_label' => formatDate($date, 'd/m'),
            'menages' => 0,
            'salaire' => 0,
            'ventes' => 0,
            'commissions' => 0
        ];
    }
}

// Récupération des objectifs personnels de l'utilisateur
$objectifs = [];
try {
    $stmt = $db->prepare("SELECT goal_type, target_value FROM user_goals WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $user_goals = $stmt->fetchAll();
    
    // Convertir en tableau associatif
    foreach ($user_goals as $goal) {
        $objectifs[$goal['goal_type']] = $goal['target_value'];
    }
    
    // Valeurs par défaut si aucun objectif défini
    $default_goals = [
        'menages_mensuel' => 100,
        'salaire_mensuel' => 2000,
        'ventes_mensuelles' => 50,
        'commissions_mensuelles' => 500
    ];
    
    foreach ($default_goals as $goal_type => $default_value) {
        if (!isset($objectifs[$goal_type])) {
            $objectifs[$goal_type] = $default_value;
        }
    }
} catch (Exception $e) {
    // En cas d'erreur, utiliser les valeurs par défaut
    $objectifs = [
        'menages_mensuel' => 100,
        'salaire_mensuel' => 2000,
        'ventes_mensuelles' => 50,
        'commissions_mensuelles' => 500
    ];
}

// Calcul des pourcentages d'objectifs
$progress = [];
if ($period === 'month') {
    $progress['menages'] = $cleaning_stats['total_menages'] > 0 ? min(100, ($cleaning_stats['total_menages'] / $objectifs['menages_mensuel']) * 100) : 0;
    $progress['salaire'] = $cleaning_stats['total_salaire'] > 0 ? min(100, ($cleaning_stats['total_salaire'] / $objectifs['salaire_mensuel']) * 100) : 0;
    
    if ($auth->canAccessCashRegister()) {
        $progress['ventes'] = $sales_stats['total_ventes'] > 0 ? min(100, ($sales_stats['total_ventes'] / $objectifs['ventes_mensuelles']) * 100) : 0;
        $progress['commissions'] = $sales_stats['commissions_total'] > 0 ? min(100, ($sales_stats['commissions_total'] / $objectifs['commissions_mensuelles']) * 100) : 0;
    }
}

$page_title = "Mes Statistiques";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?> - Le Yellowjack</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- CSS personnalisé -->
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
                        <i class="fas fa-chart-user me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                </div>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filtres de période -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Période d'analyse
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="period" class="form-label">Période</label>
                                <select class="form-select" id="period" name="period" onchange="toggleCustomDates()">
                                    <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Cette semaine</option>
                                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Ce mois</option>
                                    <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Ce trimestre</option>
                                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Cette année</option>
                                    <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Période personnalisée</option>
                                </select>
                            </div>
                            <div class="col-md-3" id="start_date_group" style="display: <?php echo $period === 'custom' ? 'block' : 'none'; ?>">
                                <label for="start_date" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col-md-3" id="end_date_group" style="display: <?php echo $period === 'custom' ? 'block' : 'none'; ?>">
                                <label for="end_date" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        Analyser
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Période analysée -->
                <div class="alert alert-info">
                    <i class="fas fa-calendar me-2"></i>
                    <strong>Période analysée :</strong> <?php echo $period_label; ?>
                    (<?php echo formatDate($start_date); ?> - <?php echo formatDate($end_date); ?>)
                </div>
                
                <!-- Statistiques de ménage -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h3 class="h4 mb-3">
                            <i class="fas fa-broom me-2"></i>
                            Activité Ménage
                        </h3>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-tasks fa-2x text-primary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($cleaning_stats['total_services']); ?></h5>
                                <p class="card-text text-muted">Services total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-broom fa-2x text-success mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($cleaning_stats['total_menages']); ?></h5>
                                <p class="card-text text-muted">Ménages effectués</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                <h5 class="card-title"><?php echo $cleaning_stats['total_hours']; ?>h</h5>
                                <p class="card-text text-muted">Temps travaillé</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-dollar-sign fa-2x text-info mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($cleaning_stats['total_salaire'], 2); ?>$</h5>
                                <p class="card-text text-muted">Salaire gagné</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistiques de vente (si autorisé) -->
                <?php if ($auth->canAccessCashRegister()): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h3 class="h4 mb-3">
                            <i class="fas fa-cash-register me-2"></i>
                            Activité Vente
                        </h3>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-receipt fa-2x text-primary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($sales_stats['total_ventes']); ?></h5>
                                <p class="card-text text-muted">Ventes réalisées</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-chart-line fa-2x text-success mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($sales_stats['ca_total'], 2); ?>$</h5>
                                <p class="card-text text-muted">Chiffre d'affaires</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-percentage fa-2x text-warning mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($sales_stats['commissions_total'], 2); ?>$</h5>
                                <p class="card-text text-muted">Commissions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-shopping-cart fa-2x text-info mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($sales_stats['panier_moyen'], 2); ?>$</h5>
                                <p class="card-text text-muted">Panier moyen</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Objectifs mensuels -->
                <?php if ($period === 'month'): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-target me-2"></i>
                            Progression vers les objectifs mensuels
                        </h5>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#goalsModal">
                            <i class="fas fa-edit me-1"></i>
                            Modifier les objectifs
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Ménages</span>
                                        <span><?php echo number_format($cleaning_stats['total_menages']); ?> / <?php echo $objectifs['menages_mensuel']; ?></span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width: <?php echo $progress['menages']; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($progress['menages'], 1); ?>% de l'objectif</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Salaire ménage</span>
                                        <span><?php echo number_format($cleaning_stats['total_salaire'], 2); ?>$ / <?php echo $objectifs['salaire_mensuel']; ?>$</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-info" style="width: <?php echo $progress['salaire']; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($progress['salaire'], 1); ?>% de l'objectif</small>
                                </div>
                            </div>
                            
                            <?php if ($auth->canAccessCashRegister()): ?>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Ventes</span>
                                        <span><?php echo number_format($sales_stats['total_ventes']); ?> / <?php echo $objectifs['ventes_mensuelles']; ?></span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-warning" style="width: <?php echo $progress['ventes']; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($progress['ventes'], 1); ?>% de l'objectif</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Commissions</span>
                                        <span><?php echo number_format($sales_stats['commissions_total'], 2); ?>$ / <?php echo $objectifs['commissions_mensuelles']; ?>$</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-danger" style="width: <?php echo $progress['commissions']; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($progress['commissions'], 1); ?>% de l'objectif</small>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Graphique d'évolution (7 derniers jours) -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Évolution des performances (7 derniers jours)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Résumé et conseils -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-lightbulb me-2"></i>
                                    Résumé de performance
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($cleaning_stats['total_menages'] > 0): ?>
                                    <p><i class="fas fa-check-circle text-success me-2"></i>
                                    Vous avez effectué <strong><?php echo $cleaning_stats['total_menages']; ?> ménages</strong> sur cette période.</p>
                                    
                                    <p><i class="fas fa-clock text-info me-2"></i>
                                    Temps moyen par service : <strong><?php echo $cleaning_stats['total_services'] > 0 ? round($cleaning_stats['total_minutes'] / $cleaning_stats['total_services'], 0) : 0; ?> minutes</strong></p>
                                    
                                    <p><i class="fas fa-dollar-sign text-warning me-2"></i>
                                    Salaire moyen par ménage : <strong><?php echo $cleaning_stats['total_menages'] > 0 ? number_format($cleaning_stats['total_salaire'] / $cleaning_stats['total_menages'], 2) : 0; ?>$</strong></p>
                                <?php else: ?>
                                    <p class="text-muted">Aucune activité de ménage sur cette période.</p>
                                <?php endif; ?>
                                
                                <?php if ($auth->canAccessCashRegister() && $sales_stats['total_ventes'] > 0): ?>
                                    <hr>
                                    <p><i class="fas fa-shopping-cart text-primary me-2"></i>
                                    Vous avez réalisé <strong><?php echo $sales_stats['total_ventes']; ?> ventes</strong> pour un total de <strong><?php echo number_format($sales_stats['ca_total'], 2); ?>$</strong></p>
                                    
                                    <p><i class="fas fa-percentage text-success me-2"></i>
                                    Taux de fidélisation : <strong><?php echo $sales_stats['total_ventes'] > 0 ? number_format(($sales_stats['ventes_clients_fideles'] / $sales_stats['total_ventes']) * 100, 1) : 0; ?>%</strong></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-trophy me-2"></i>
                                    Conseils d'amélioration
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($period === 'month' && isset($progress)): ?>
                                    <?php if ($progress['menages'] < 50): ?>
                                        <div class="alert alert-warning alert-sm">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Vous êtes en retard sur votre objectif de ménages. Essayez d'augmenter votre rythme !
                                        </div>
                                    <?php elseif ($progress['menages'] >= 100): ?>
                                        <div class="alert alert-success alert-sm">
                                            <i class="fas fa-star me-2"></i>
                                            Félicitations ! Vous avez atteint votre objectif de ménages !
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($auth->canAccessCashRegister() && $progress['ventes'] < 50): ?>
                                        <div class="alert alert-info alert-sm">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Pensez à proposer plus de produits aux clients pour augmenter vos ventes.
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="alert alert-light alert-sm">
                                    <i class="fas fa-tips me-2"></i>
                                    <strong>Astuce :</strong> Consultez régulièrement vos statistiques pour suivre votre progression et identifier les axes d'amélioration.
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
    // Fonction pour afficher/masquer les champs de date personnalisée
    function toggleCustomDates() {
        const period = document.getElementById('period').value;
        const startDateGroup = document.getElementById('start_date_group');
        const endDateGroup = document.getElementById('end_date_group');
        
        if (period === 'custom') {
            startDateGroup.style.display = 'block';
            endDateGroup.style.display = 'block';
        } else {
            startDateGroup.style.display = 'none';
            endDateGroup.style.display = 'none';
        }
    }
    
    // Graphique d'évolution des performances
    const ctx = document.getElementById('performanceChart').getContext('2d');
    const performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($performance_data, 'date_label')); ?>,
            datasets: [{
                label: 'Ménages',
                data: <?php echo json_encode(array_column($performance_data, 'menages')); ?>,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1,
                yAxisID: 'y'
            }, {
                label: 'Salaire ($)',
                data: <?php echo json_encode(array_column($performance_data, 'salaire')); ?>,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                tension: 0.1,
                yAxisID: 'y1'
            }<?php if ($auth->canAccessCashRegister()): ?>, {
                label: 'Ventes',
                data: <?php echo json_encode(array_column($performance_data, 'ventes')); ?>,
                borderColor: 'rgb(255, 205, 86)',
                backgroundColor: 'rgba(255, 205, 86, 0.2)',
                tension: 0.1,
                yAxisID: 'y'
            }, {
                label: 'Commissions ($)',
                data: <?php echo json_encode(array_column($performance_data, 'commissions')); ?>,
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                tension: 0.1,
                yAxisID: 'y1'
            }<?php endif; ?>]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Jour'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Nombre'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Montant ($)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
    </script>
    
    <!-- Modal de gestion des objectifs -->
    <div class="modal fade" id="goalsModal" tabindex="-1" aria-labelledby="goalsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="goalsModalLabel">
                        <i class="fas fa-target me-2"></i>
                        Gestion des objectifs mensuels
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-broom me-2"></i>
                                    Objectifs Ménage
                                </h6>
                                <div class="mb-3">
                                    <label for="menages_mensuel" class="form-label">Nombre de ménages par mois</label>
                                    <input type="number" class="form-control" id="menages_mensuel" name="menages_mensuel" 
                                           value="<?php echo $objectifs['menages_mensuel']; ?>" min="0" step="1">
                                </div>
                                <div class="mb-3">
                                    <label for="salaire_mensuel" class="form-label">Salaire ménage mensuel ($)</label>
                                    <input type="number" class="form-control" id="salaire_mensuel" name="salaire_mensuel" 
                                           value="<?php echo $objectifs['salaire_mensuel']; ?>" min="0" step="0.01">
                                </div>
                            </div>
                            <?php if ($auth->canAccessCashRegister()): ?>
                            <div class="col-md-6">
                                <h6 class="text-warning mb-3">
                                    <i class="fas fa-shopping-cart me-2"></i>
                                    Objectifs Vente
                                </h6>
                                <div class="mb-3">
                                    <label for="ventes_mensuelles" class="form-label">Nombre de ventes par mois</label>
                                    <input type="number" class="form-control" id="ventes_mensuelles" name="ventes_mensuelles" 
                                           value="<?php echo $objectifs['ventes_mensuelles']; ?>" min="0" step="1">
                                </div>
                                <div class="mb-3">
                                    <label for="commissions_mensuelles" class="form-label">Commissions mensuelles ($)</label>
                                    <input type="number" class="form-control" id="commissions_mensuelles" name="commissions_mensuelles" 
                                           value="<?php echo $objectifs['commissions_mensuelles']; ?>" min="0" step="0.01">
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Conseil :</strong> Définissez des objectifs réalistes et motivants. Vous pourrez les ajuster à tout moment.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </button>
                        <button type="submit" name="update_goals" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Enregistrer les objectifs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
</body>
</html>