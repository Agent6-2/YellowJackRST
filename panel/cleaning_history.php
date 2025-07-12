<?php
/**
 * Historique des ménages - Panel Employé Le Yellowjack
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

// Paramètres de pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Filtres
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$month = $_GET['month'] ?? '';

// Construction de la requête
$where_conditions = ['user_id = ?'];
$params = [$user['id']];

if ($date_from) {
    $where_conditions[] = 'DATE(start_time) >= ?';
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = 'DATE(start_time) <= ?';
    $params[] = $date_to;
}

if ($month) {
    $where_conditions[] = 'DATE_FORMAT(start_time, "%Y-%m") = ?';
    $params[] = $month;
}

$where_clause = implode(' AND ', $where_conditions);

// Compter le total
$count_query = "SELECT COUNT(*) FROM cleaning_services WHERE $where_clause AND end_time IS NOT NULL";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Récupérer les sessions
$query = "
    SELECT * FROM cleaning_services 
    WHERE $where_clause AND end_time IS NOT NULL
    ORDER BY start_time DESC 
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$sessions = $stmt->fetchAll();

// Statistiques globales
$stats_query = "
    SELECT 
        COUNT(*) as total_sessions,
        COALESCE(SUM(cleaning_count), 0) as total_cleaning,
        COALESCE(SUM(total_salary), 0) as total_salary,
        COALESCE(SUM(duration_minutes), 0) as total_duration,
        COALESCE(AVG(cleaning_count), 0) as avg_cleaning_per_session,
        COALESCE(AVG(duration_minutes), 0) as avg_duration
    FROM cleaning_services 
    WHERE $where_clause AND end_time IS NOT NULL
";
$stmt = $db->prepare($stats_query);
$stmt->execute($params);
$stats = $stmt->fetch();

// Statistiques par mois (pour le graphique)
$monthly_stats_query = "
    SELECT 
        DATE_FORMAT(start_time, '%Y-%m') as month,
        COUNT(*) as sessions,
        SUM(cleaning_count) as total_cleaning,
        SUM(total_salary) as total_salary
    FROM cleaning_services 
    WHERE user_id = ? AND end_time IS NOT NULL
    GROUP BY DATE_FORMAT(start_time, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";
$stmt = $db->prepare($monthly_stats_query);
$stmt->execute([$user['id']]);
$monthly_stats = $stmt->fetchAll();

$page_title = 'Historique des Ménages';
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-history me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="cleaning.php" class="btn btn-outline-primary">
                                <i class="fas fa-broom me-1"></i>
                                Gestion Ménages
                            </a>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="fas fa-download me-1"></i>
                                Exporter
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Statistiques globales -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['total_sessions']); ?></h5>
                                <p class="card-text text-muted">Sessions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-broom fa-2x text-success mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['total_cleaning']); ?></h5>
                                <p class="card-text text-muted">Ménages</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                <h5 class="card-title"><?php echo floor($stats['total_duration'] / 60); ?>h</h5>
                                <p class="card-text text-muted">Temps total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-dollar-sign fa-2x text-info mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['total_salary']); ?>$</h5>
                                <p class="card-text text-muted">Salaire total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-chart-line fa-2x text-secondary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['avg_cleaning_per_session'], 1); ?></h5>
                                <p class="card-text text-muted">Moy./session</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-stopwatch fa-2x text-dark mb-2"></i>
                                <h5 class="card-title"><?php echo floor($stats['avg_duration'] / 60); ?>h<?php echo floor($stats['avg_duration'] % 60); ?>m</h5>
                                <p class="card-text text-muted">Durée moy.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Graphique mensuel -->
                <?php if (!empty($monthly_stats)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Évolution mensuelle
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyChart" height="100"></canvas>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Filtres -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Filtres
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="month" class="form-label">Mois</label>
                                <input type="month" class="form-control" id="month" name="month" value="<?php echo htmlspecialchars($month); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2 d-md-flex">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        Filtrer
                                    </button>
                                    <a href="cleaning_history.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tableau des sessions -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            Sessions (<?php echo number_format($total_records); ?> résultats)
                        </h5>
                        <small class="text-muted">
                            Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($sessions)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Début</th>
                                            <th>Fin</th>
                                            <th>Durée</th>
                                            <th>Ménages</th>
                                            <th>Salaire</th>
                                            <th>Efficacité</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): ?>
                                            <?php 
                                                $efficiency = $session['duration_minutes'] > 0 ? ($session['cleaning_count'] / ($session['duration_minutes'] / 60)) : 0;
                                                $efficiency_class = $efficiency >= 2 ? 'success' : ($efficiency >= 1 ? 'warning' : 'danger');
                                            ?>
                                            <tr>
                                                <td><?php echo formatDateTime($session['start_time'], 'd/m/Y'); ?></td>
                                                <td><?php echo formatDateTime($session['start_time'], 'H:i'); ?></td>
                                                <td><?php echo formatDateTime($session['end_time'], 'H:i'); ?></td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo floor($session['duration_minutes'] / 60); ?>h <?php echo $session['duration_minutes'] % 60; ?>m
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary fs-6"><?php echo $session['cleaning_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-success fw-bold"><?php echo number_format($session['total_salary']); ?>$</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $efficiency_class; ?>">
                                                        <?php echo number_format($efficiency, 1); ?>/h
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Pagination">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)); ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)); ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucune session trouvée</h5>
                                <p class="text-muted">Aucune session de ménage ne correspond aux critères sélectionnés.</p>
                                <a href="cleaning.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>
                                    Démarrer un service
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal d'export -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-download me-2"></i>
                        Exporter les données
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Fonctionnalité d'export en cours de développement.</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Bientôt disponible : export CSV, PDF et Excel
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Graphique mensuel
        <?php if (!empty($monthly_stats)): ?>
        const monthlyData = <?php echo json_encode(array_reverse($monthly_stats)); ?>;
        
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const [year, month] = item.month.split('-');
                    return new Date(year, month - 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Ménages effectués',
                    data: monthlyData.map(item => item.total_cleaning),
                    borderColor: '#D4AF37',
                    backgroundColor: 'rgba(212, 175, 55, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Salaire ($)',
                    data: monthlyData.map(item => item.total_salary),
                    borderColor: '#8B4513',
                    backgroundColor: 'rgba(139, 69, 19, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
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
                            text: 'Mois'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Nombre de ménages'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Salaire ($)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>