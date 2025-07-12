<?php
/**
 * Page d'analytics - Panel Employé Le Yellowjack
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

$page_title = 'Analytics';

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

// Statistiques générales
$general_stats = [];
try {
    // Statistiques de ménage
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT cs.id) as total_services,
            SUM(cs.cleaning_count) as total_menages,
            SUM(cs.total_salary) as total_salaires,
            AVG(cs.total_salary) as salaire_moyen,
            SUM(cs.duration_minutes) as total_minutes,
            COUNT(DISTINCT cs.user_id) as employes_actifs
        FROM cleaning_services cs
        WHERE DATE(cs.start_time) >= ? AND DATE(cs.start_time) <= ?
        AND cs.status = 'completed'
    ");
    $stmt->execute([$start_date, $end_date]);
    $cleaning_stats = $stmt->fetch();
    
    // Statistiques de vente
    $stmt = $db->prepare("
        SELECT 
            COUNT(s.id) as total_ventes,
            SUM(s.final_amount) as ca_total,
            SUM(s.employee_commission) as commissions_total,
            AVG(s.final_amount) as panier_moyen,
            COUNT(DISTINCT s.customer_id) as clients_uniques
        FROM sales s
        WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) <= ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $sales_stats = $stmt->fetch();
    
    $general_stats = array_merge($cleaning_stats ?: [], $sales_stats ?: []);
} catch (Exception $e) {
    $general_stats = [];
}

// Données pour graphiques - Évolution quotidienne
$daily_data = [];
try {
    $stmt = $db->prepare("
        SELECT 
            DATE(cs.start_time) as date,
            COUNT(cs.id) as services,
            SUM(cs.cleaning_count) as menages,
            SUM(cs.total_salary) as salaires
        FROM cleaning_services cs
        WHERE DATE(cs.start_time) >= ? AND DATE(cs.start_time) <= ?
        AND cs.status = 'completed'
        GROUP BY DATE(cs.start_time)
        ORDER BY DATE(cs.start_time)
    ");
    $stmt->execute([$start_date, $end_date]);
    $cleaning_daily = $stmt->fetchAll();
    
    $stmt = $db->prepare("
        SELECT 
            DATE(s.created_at) as date,
            COUNT(s.id) as ventes,
            SUM(s.final_amount) as ca,
            SUM(s.employee_commission) as commissions
        FROM sales s
        WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) <= ?
        GROUP BY DATE(s.created_at)
        ORDER BY DATE(s.created_at)
    ");
    $stmt->execute([$start_date, $end_date]);
    $sales_daily = $stmt->fetchAll();
    
    // Fusionner les données
    $dates = [];
    foreach ($cleaning_daily as $row) {
        $dates[$row['date']] = $row;
    }
    foreach ($sales_daily as $row) {
        if (isset($dates[$row['date']])) {
            $dates[$row['date']] = array_merge($dates[$row['date']], $row);
        } else {
            $dates[$row['date']] = $row;
        }
    }
    $daily_data = array_values($dates);
} catch (Exception $e) {
    $daily_data = [];
}

// Performance par employé
$employee_performance = [];
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.role,
            COUNT(DISTINCT cs.id) as services_menage,
            SUM(cs.cleaning_count) as total_menages,
            SUM(cs.total_salary) as salaires_menage,
            COUNT(DISTINCT s.id) as total_ventes,
            SUM(s.final_amount) as ca_ventes,
            SUM(s.employee_commission) as commissions_ventes
        FROM users u
        LEFT JOIN cleaning_services cs ON u.id = cs.user_id 
            AND DATE(cs.start_time) >= ? AND DATE(cs.start_time) <= ?
            AND cs.status = 'completed'
        LEFT JOIN sales s ON u.id = s.user_id
            AND DATE(s.created_at) >= ? AND DATE(s.created_at) <= ?
        WHERE u.status = 'active'
        GROUP BY u.id, u.first_name, u.last_name, u.role
        ORDER BY (COALESCE(total_menages, 0) + COALESCE(total_ventes, 0)) DESC
    ");
    $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
    $employee_performance = $stmt->fetchAll();
} catch (Exception $e) {
    $employee_performance = [];
}

// Top clients
$top_customers = [];
try {
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.first_name,
            c.last_name,
            c.phone,
            COUNT(s.id) as total_achats,
            SUM(s.final_amount) as ca_total,
            AVG(s.final_amount) as panier_moyen,
            MAX(s.created_at) as derniere_visite
        FROM customers c
        INNER JOIN sales s ON c.id = s.customer_id
        WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) <= ?
        GROUP BY c.id, c.first_name, c.last_name, c.phone
        ORDER BY ca_total DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_customers = $stmt->fetchAll();
} catch (Exception $e) {
    $top_customers = [];
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
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-chart-line"></i> Analytics</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group mr-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData()">
                            <i class="fas fa-download"></i> Exporter
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

            <!-- Statistiques générales -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card global-stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-broom fa-2x text-primary mb-2"></i>
                            <h5 class="card-title"><?php echo number_format($general_stats['total_menages'] ?? 0); ?></h5>
                            <p class="card-text text-muted">Ménages effectués</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card global-stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-shopping-cart fa-2x text-success mb-2"></i>
                            <h5 class="card-title"><?php echo number_format($general_stats['total_ventes'] ?? 0); ?></h5>
                            <p class="card-text text-muted">Ventes réalisées</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card global-stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-dollar-sign fa-2x text-warning mb-2"></i>
                            <h5 class="card-title">$<?php echo number_format($general_stats['ca_total'] ?? 0, 2); ?></h5>
                            <p class="card-text text-muted">Chiffre d'affaires</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card global-stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-users fa-2x text-info mb-2"></i>
                            <h5 class="card-title"><?php echo number_format($general_stats['employes_actifs'] ?? 0); ?></h5>
                            <p class="card-text text-muted">Employés actifs</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphiques -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Évolution quotidienne - Ménages</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="cleaningChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Évolution quotidienne - Ventes</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Répartition des performances par employé</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="employeeChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance des employés -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-users"></i> Performance des employés</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped rankings-table">
                                    <thead>
                                        <tr>
                                            <th>Employé</th>
                                            <th>Rôle</th>
                                            <th>Services ménage</th>
                                            <th>Total ménages</th>
                                            <th>Salaires ménage</th>
                                            <th>Ventes</th>
                                            <th>CA Ventes</th>
                                            <th>Commissions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employee_performance as $employee): ?>
                                        <tr>
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
                                            <td><?php echo number_format($employee['services_menage'] ?? 0); ?></td>
                                            <td><?php echo number_format($employee['total_menages'] ?? 0); ?></td>
                                            <td>$<?php echo number_format($employee['salaires_menage'] ?? 0, 2); ?></td>
                                            <td><?php echo number_format($employee['total_ventes'] ?? 0); ?></td>
                                            <td>$<?php echo number_format($employee['ca_ventes'] ?? 0, 2); ?></td>
                                            <td>$<?php echo number_format($employee['commissions_ventes'] ?? 0, 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top clients -->
            <?php if (!empty($top_customers)): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-star"></i> Top 10 Clients</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped rankings-table">
                                    <thead>
                                        <tr>
                                            <th>Position</th>
                                            <th>Client</th>
                                            <th>Téléphone</th>
                                            <th>Achats</th>
                                            <th>CA Total</th>
                                            <th>Panier moyen</th>
                                            <th>Dernière visite</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_customers as $index => $customer): ?>
                                        <tr>
                                            <td>
                                                <div class="ranking-position">
                                                    <?php if ($index === 0): ?>
                                                        <i class="fas fa-trophy text-warning"></i> 1
                                                    <?php elseif ($index === 1): ?>
                                                        <i class="fas fa-medal text-secondary"></i> 2
                                                    <?php elseif ($index === 2): ?>
                                                        <i class="fas fa-medal text-warning"></i> 3
                                                    <?php else: ?>
                                                        <?php echo $index + 1; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                            <td><?php echo number_format($customer['total_achats']); ?></td>
                                            <td><strong>$<?php echo number_format($customer['ca_total'], 2); ?></strong></td>
                                            <td>$<?php echo number_format($customer['panier_moyen'], 2); ?></td>
                                            <td><?php echo formatDate($customer['derniere_visite'], 'd/m/Y H:i'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<script>
// Données pour les graphiques
const dailyData = <?php echo json_encode($daily_data); ?>;
const employeeData = <?php echo json_encode($employee_performance); ?>;

// Graphique des ménages
const cleaningCtx = document.getElementById('cleaningChart').getContext('2d');
const cleaningChart = new Chart(cleaningCtx, {
    type: 'line',
    data: {
        labels: dailyData.map(d => d.date),
        datasets: [{
            label: 'Nombre de ménages',
            data: dailyData.map(d => d.menages || 0),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }, {
            label: 'Salaires ($)',
            data: dailyData.map(d => d.salaires || 0),
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            tension: 0.1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});

// Graphique des ventes
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'bar',
    data: {
        labels: dailyData.map(d => d.date),
        datasets: [{
            label: 'Nombre de ventes',
            data: dailyData.map(d => d.ventes || 0),
            backgroundColor: 'rgba(54, 162, 235, 0.8)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }, {
            label: 'Chiffre d\'affaires ($)',
            data: dailyData.map(d => d.ca || 0),
            backgroundColor: 'rgba(255, 206, 86, 0.8)',
            borderColor: 'rgba(255, 206, 86, 1)',
            borderWidth: 1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});

// Graphique des employés (camembert)
const employeeCtx = document.getElementById('employeeChart').getContext('2d');
const employeeChart = new Chart(employeeCtx, {
    type: 'doughnut',
    data: {
        labels: employeeData.map(e => e.first_name + ' ' + e.last_name),
        datasets: [{
            label: 'Total activités',
            data: employeeData.map(e => (parseInt(e.total_menages || 0) + parseInt(e.total_ventes || 0))),
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 159, 64, 0.8)',
                'rgba(199, 199, 199, 0.8)',
                'rgba(83, 102, 255, 0.8)'
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)',
                'rgba(255, 159, 64, 1)',
                'rgba(199, 199, 199, 1)',
                'rgba(83, 102, 255, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

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

function exportData() {
    // Fonction d'export des données (à implémenter selon les besoins)
    alert('Fonctionnalité d\'export en cours de développement');
}
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>