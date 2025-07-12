<?php
/**
 * Page des classements - Panel Employé Le Yellowjack
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

$page_title = 'Classements';

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

// Classement des ménages
$cleaning_rankings = [];
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.role,
            COUNT(cs.id) as total_services,
            SUM(cs.cleaning_count) as total_menages,
            SUM(cs.total_salary) as total_salaire,
            AVG(cs.total_salary) as salaire_moyen,
            SUM(cs.duration_minutes) as total_minutes,
            ROUND(SUM(cs.duration_minutes) / 60, 1) as total_hours,
            ROUND(AVG(cs.duration_minutes), 0) as duree_moyenne
        FROM users u
        LEFT JOIN cleaning_services cs ON u.id = cs.user_id 
            AND DATE(cs.start_time) >= ? AND DATE(cs.start_time) <= ?
            AND cs.status = 'completed'
        WHERE u.status = 'active'
        GROUP BY u.id, u.first_name, u.last_name, u.role
        ORDER BY total_menages DESC, total_salaire DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $cleaning_rankings = $stmt->fetchAll();
} catch (Exception $e) {
    $cleaning_rankings = [];
}

// Classement des ventes (si l'utilisateur peut voir)
$sales_rankings = [];
if ($auth->canAccessCashRegister()) {
    try {
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                u.role,
                COUNT(s.id) as total_ventes,
                SUM(s.final_amount) as ca_total,
                SUM(s.employee_commission) as commissions_total,
                AVG(s.final_amount) as panier_moyen,
                SUM(CASE WHEN s.customer_id IS NOT NULL THEN 1 ELSE 0 END) as ventes_clients_fideles
            FROM users u
            LEFT JOIN sales s ON u.id = s.user_id 
                AND DATE(s.created_at) >= ? AND DATE(s.created_at) <= ?
            WHERE u.status = 'active'
            GROUP BY u.id, u.first_name, u.last_name, u.role
            ORDER BY total_ventes DESC, ca_total DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $sales_rankings = $stmt->fetchAll();
    } catch (Exception $e) {
        $sales_rankings = [];
    }
}

// Statistiques globales
$global_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT cs.user_id) as employes_actifs_menage,
            SUM(cs.cleaning_count) as total_menages_equipe,
            SUM(cs.total_salary) as total_salaires_equipe,
            AVG(cs.total_salary) as salaire_moyen_equipe
        FROM cleaning_services cs
        WHERE DATE(cs.start_time) >= ? AND DATE(cs.start_time) <= ?
            AND cs.status = 'completed'
    ");
    $stmt->execute([$start_date, $end_date]);
    $global_stats = $stmt->fetch();
    
    if ($auth->canAccessCashRegister()) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT s.user_id) as employes_actifs_vente,
                COUNT(s.id) as total_ventes_equipe,
                SUM(s.final_amount) as ca_total_equipe,
                SUM(s.employee_commission) as commissions_totales_equipe
            FROM sales s
            WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) <= ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $sales_global = $stmt->fetch();
        $global_stats = array_merge($global_stats, $sales_global);
    }
} catch (Exception $e) {
    $global_stats = [
        'employes_actifs_menage' => 0,
        'total_menages_equipe' => 0,
        'total_salaires_equipe' => 0,
        'salaire_moyen_equipe' => 0
    ];
}

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
    <!-- CSS personnalisé -->
    <link href="../assets/css/panel.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-trophy"></i> Classements</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group mr-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filtres de période -->
            <div class="card mb-4 period-filters">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filtres</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-md-4">
                            <label for="period" class="form-label">Période</label>
                            <select name="period" id="period" class="form-select" onchange="toggleCustomDates()">
                                <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                                <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Cette semaine</option>
                                <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Ce mois</option>
                                <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Ce trimestre</option>
                                <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Cette année</option>
                                <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Personnalisé</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="start-date-group" style="display: <?php echo $period === 'custom' ? 'block' : 'none'; ?>">
                            <label for="start_date" class="form-label">Date de début</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3" id="end-date-group" style="display: <?php echo $period === 'custom' ? 'block' : 'none'; ?>">
                            <label for="end_date" class="form-label">Date de fin</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistiques globales -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary global-stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo number_format($global_stats['employes_actifs_menage'] ?? 0); ?></h4>
                                    <p class="card-text">Employés actifs (ménage)</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success global-stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo number_format($global_stats['total_menages_equipe'] ?? 0); ?></h4>
                                    <p class="card-text">Total ménages équipe</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-broom fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info global-stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title">$<?php echo number_format($global_stats['total_salaires_equipe'] ?? 0, 2); ?></h4>
                                    <p class="card-text">Salaires totaux</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-dollar-sign fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($auth->canAccessCashRegister() && isset($global_stats['ca_total_equipe'])): ?>
                <div class="col-md-3">
                    <div class="card text-white bg-warning global-stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title">$<?php echo number_format($global_stats['ca_total_equipe'] ?? 0, 2); ?></h4>
                                    <p class="card-text">CA total équipe</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Classement des ménages -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-medal"></i> Classement Ménages - <?php echo $period_label; ?></h5>
                </div>
                <div class="card-body">
                    <?php if (empty($cleaning_rankings)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Aucune donnée de ménage pour cette période.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover rankings-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Rang</th>
                                        <th>Employé</th>
                                        <th>Rôle</th>
                                        <th>Services</th>
                                        <th>Ménages</th>
                                        <th>Salaire Total</th>
                                        <th>Salaire Moyen</th>
                                        <th>Heures</th>
                                        <th>Durée Moy.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cleaning_rankings as $index => $employee): ?>
                                        <tr class="<?php echo $employee['id'] == $user['id'] ? 'table-warning' : ''; ?>">
                                            <td>
                                                <div class="ranking-position">
                                                    <?php if ($index === 0): ?>
                                                        <i class="fas fa-trophy"></i> 1
                                                    <?php elseif ($index === 1): ?>
                                                        <i class="fas fa-medal text-secondary"></i> 2
                                                    <?php elseif ($index === 2): ?>
                                                        <i class="fas fa-medal text-warning"></i> 3
                                                    <?php else: ?>
                                                        <?php echo $index + 1; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                                                <?php if ($employee['id'] == $user['id']): ?>
                                                    <span class="badge user-badge ms-1">Vous</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge role-badge bg-<?php echo $employee['role'] === 'admin' ? 'danger' : ($employee['role'] === 'manager' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($employee['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($employee['total_services']); ?></td>
                                            <td><strong><?php echo number_format($employee['total_menages']); ?></strong></td>
                                            <td><strong>$<?php echo number_format($employee['total_salaire'], 2); ?></strong></td>
                                            <td>$<?php echo number_format($employee['salaire_moyen'], 2); ?></td>
                                            <td><?php echo $employee['total_hours']; ?>h</td>
                                            <td><?php echo $employee['duree_moyenne']; ?> min</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Classement des ventes -->
            <?php if ($auth->canAccessCashRegister()): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-line"></i> Classement Ventes - <?php echo $period_label; ?></h5>
                </div>
                <div class="card-body">
                    <?php if (empty($sales_rankings)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Aucune donnée de vente pour cette période.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover rankings-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Rang</th>
                                        <th>Employé</th>
                                        <th>Rôle</th>
                                        <th>Ventes</th>
                                        <th>CA Total</th>
                                        <th>Commissions</th>
                                        <th>Panier Moyen</th>
                                        <th>Clients Fidèles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sales_rankings as $index => $employee): ?>
                                        <tr class="<?php echo $employee['id'] == $user['id'] ? 'table-warning' : ''; ?>">
                                            <td>
                                                <div class="ranking-position">
                                                    <?php if ($index === 0): ?>
                                                        <i class="fas fa-trophy"></i> 1
                                                    <?php elseif ($index === 1): ?>
                                                        <i class="fas fa-medal text-secondary"></i> 2
                                                    <?php elseif ($index === 2): ?>
                                                        <i class="fas fa-medal text-warning"></i> 3
                                                    <?php else: ?>
                                                        <?php echo $index + 1; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                                                <?php if ($employee['id'] == $user['id']): ?>
                                                    <span class="badge user-badge ms-1">Vous</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge role-badge bg-<?php echo $employee['role'] === 'admin' ? 'danger' : ($employee['role'] === 'manager' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($employee['role']); ?>
                                                </span>
                                            </td>
                                            <td><strong><?php echo number_format($employee['total_ventes']); ?></strong></td>
                                            <td><strong>$<?php echo number_format($employee['ca_total'], 2); ?></strong></td>
                                            <td>$<?php echo number_format($employee['commissions_total'], 2); ?></td>
                                            <td>$<?php echo number_format($employee['panier_moyen'], 2); ?></td>
                                            <td><?php echo number_format($employee['ventes_clients_fideles']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<script>
function toggleCustomDates() {
    const period = document.getElementById('period').value;
    const startDateGroup = document.getElementById('start-date-group');
    const endDateGroup = document.getElementById('end-date-group');
    
    if (period === 'custom') {
        startDateGroup.style.display = 'block';
        endDateGroup.style.display = 'block';
    } else {
        startDateGroup.style.display = 'none';
        endDateGroup.style.display = 'none';
    }
}
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>