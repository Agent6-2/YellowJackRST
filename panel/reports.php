<?php
/**
 * Rapports et Analyses - Panel Employé Le Yellowjack
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

// Paramètres de période
$period = $_GET['period'] ?? 'month';
$custom_start = $_GET['start_date'] ?? '';
$custom_end = $_GET['end_date'] ?? '';

// Calculer les dates selon la période
switch ($period) {
    case 'today':
        $start_date = date('Y-m-d 00:00:00');
        $end_date = date('Y-m-d 23:59:59');
        $period_label = 'Aujourd\'hui';
        break;
    case 'week':
        $start_date = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $end_date = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        $period_label = 'Cette semaine';
        break;
    case 'month':
        $start_date = date('Y-m-01 00:00:00');
        $end_date = date('Y-m-t 23:59:59');
        $period_label = 'Ce mois';
        break;
    case 'quarter':
        $quarter = ceil(date('n') / 3);
        $start_month = ($quarter - 1) * 3 + 1;
        $start_date = date('Y-' . sprintf('%02d', $start_month) . '-01 00:00:00');
        $end_date = date('Y-m-t 23:59:59', strtotime($start_date . ' +2 months'));
        $period_label = 'Ce trimestre';
        break;
    case 'year':
        $start_date = date('Y-01-01 00:00:00');
        $end_date = date('Y-12-31 23:59:59');
        $period_label = 'Cette année';
        break;
    case 'custom':
        if ($custom_start && $custom_end) {
            $start_date = $custom_start . ' 00:00:00';
            $end_date = $custom_end . ' 23:59:59';
            $period_label = 'Période personnalisée';
        } else {
            $start_date = date('Y-m-01 00:00:00');
            $end_date = date('Y-m-t 23:59:59');
            $period_label = 'Ce mois';
            $period = 'month';
        }
        break;
    default:
        $start_date = date('Y-m-01 00:00:00');
        $end_date = date('Y-m-t 23:59:59');
        $period_label = 'Ce mois';
        $period = 'month';
}

// Statistiques générales
$stats_query = "
    SELECT 
        COUNT(DISTINCT s.id) as total_sales,
        COALESCE(SUM(s.total_amount), 0) as total_revenue,
        COALESCE(SUM(s.employee_commission), 0) as total_commissions,
        COALESCE(AVG(s.total_amount), 0) as avg_sale_amount,
        COUNT(DISTINCT s.customer_id) as unique_customers,
        COUNT(DISTINCT s.user_id) as active_employees
    FROM sales s
    WHERE s.created_at >= ? AND s.created_at < ?
";
$stmt = $db->prepare($stats_query);
$stmt->execute([$start_date, $end_date]);
$general_stats = $stmt->fetch();

// Statistiques de ménage
$cleaning_stats_query = "
    SELECT 
        COUNT(*) as total_sessions,
        COALESCE(SUM(cleaning_count), 0) as total_cleanings,
        COALESCE(SUM(total_salary), 0) as total_cleaning_salaries,
        COALESCE(AVG(cleaning_count), 0) as avg_cleanings_per_session,
        COUNT(DISTINCT user_id) as cleaning_employees
    FROM cleaning_services
    WHERE end_time >= ? AND end_time < ?
";
$stmt = $db->prepare($cleaning_stats_query);
$stmt->execute([$start_date, $end_date]);
$cleaning_stats = $stmt->fetch();

// Top produits vendus
$top_products_query = "
    SELECT 
        p.name,
        p.selling_price,
        SUM(sd.quantity) as total_sold,
        SUM(sd.total_price) as total_revenue,
        COUNT(DISTINCT sd.sale_id) as sales_count
    FROM sale_items sd
    JOIN products p ON sd.product_id = p.id
    JOIN sales s ON sd.sale_id = s.id
    WHERE s.created_at >= ? AND s.created_at < ?
    GROUP BY p.id, p.name, p.selling_price
    ORDER BY total_sold DESC
    LIMIT 10
";
$stmt = $db->prepare($top_products_query);
$stmt->execute([$start_date, $end_date]);
$top_products = $stmt->fetchAll();

// Top employés (ventes)
$top_sales_employees_query = "
    SELECT 
        u.first_name,
        u.last_name,
        u.role,
        COUNT(s.id) as sales_count,
        COALESCE(SUM(s.total_amount), 0) as total_revenue,
        COALESCE(SUM(s.employee_commission), 0) as total_commissions
    FROM users u
    LEFT JOIN sales s ON u.id = s.user_id AND s.created_at >= ? AND s.created_at < ?
    WHERE u.role IN ('CDD', 'CDI', 'Responsable', 'Patron') AND u.status = 'active'
    GROUP BY u.id, u.first_name, u.last_name, u.role
    ORDER BY total_revenue DESC
    LIMIT 10
";
$stmt = $db->prepare($top_sales_employees_query);
$stmt->execute([$start_date, $end_date]);
$top_sales_employees = $stmt->fetchAll();

// Top employés (ménages)
$top_cleaning_employees_query = "
    SELECT 
        u.first_name,
        u.last_name,
        u.role,
        COUNT(cs.id) as sessions_count,
        COALESCE(SUM(cs.cleaning_count), 0) as total_cleanings,
        COALESCE(SUM(cs.total_salary), 0) as total_salary
    FROM users u
    LEFT JOIN cleaning_services cs ON u.id = cs.user_id AND cs.end_time >= ? AND cs.end_time < ?
    WHERE u.status = 'active'
    GROUP BY u.id, u.first_name, u.last_name, u.role
    ORDER BY total_cleanings DESC
    LIMIT 10
";
$stmt = $db->prepare($top_cleaning_employees_query);
$stmt->execute([$start_date, $end_date]);
$top_cleaning_employees = $stmt->fetchAll();

// Évolution des ventes (par jour)
$sales_evolution_query = "
    SELECT 
        DATE(s.created_at) as sale_day,
        COUNT(s.id) as sales_count,
        COALESCE(SUM(s.total_amount), 0) as daily_revenue
    FROM sales s
    WHERE s.created_at >= ? AND s.created_at < ?
    GROUP BY DATE(s.created_at)
    ORDER BY sale_day
";
$stmt = $db->prepare($sales_evolution_query);
$stmt->execute([$start_date, $end_date]);
$sales_evolution = $stmt->fetchAll();

// Répartition par catégorie
$category_stats_query = "
    SELECT 
        c.name as category_name,
        COUNT(DISTINCT p.id) as products_count,
        COALESCE(SUM(sd.quantity), 0) as total_sold,
        COALESCE(SUM(sd.total_price), 0) as total_revenue
    FROM product_categories c
    LEFT JOIN products p ON c.id = p.category_id
    LEFT JOIN sale_items sd ON p.id = sd.product_id
    LEFT JOIN sales s ON sd.sale_id = s.id AND s.created_at >= ? AND s.created_at < ?
    GROUP BY c.id, c.name
    ORDER BY total_revenue DESC
";
$stmt = $db->prepare($category_stats_query);
$stmt->execute([$start_date, $end_date]);
$category_stats = $stmt->fetchAll();

// Clients les plus fidèles
$top_customers_query = "
    SELECT 
        c.name,
        c.phone,
        COUNT(s.id) as visits_count,
        COALESCE(SUM(s.total_amount), 0) as total_spent,
        COALESCE(AVG(s.total_amount), 0) as avg_spent
    FROM customers c
    LEFT JOIN sales s ON c.id = s.customer_id AND s.created_at >= ? AND s.created_at < ?
    WHERE c.id > 1
    GROUP BY c.id, c.name, c.phone
    HAVING visits_count > 0
    ORDER BY total_spent DESC
    LIMIT 10
";
$stmt = $db->prepare($top_customers_query);
$stmt->execute([$start_date, $end_date]);
$top_customers = $stmt->fetchAll();

$page_title = 'Rapports et Analyses';
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <i class="fas fa-chart-bar me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>
                                Imprimer
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-success">
                                <i class="fas fa-chart-line me-1"></i>
                                Tableau de bord
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Sélecteur de période -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
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
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $custom_start; ?>">
                            </div>
                            <div class="col-md-3" id="end_date_group" style="display: <?php echo $period === 'custom' ? 'block' : 'none'; ?>">
                                <label for="end_date" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $custom_end; ?>">
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
                
                <!-- Période sélectionnée -->
                <div class="alert alert-info">
                    <i class="fas fa-calendar me-2"></i>
                    <strong>Période analysée :</strong> <?php echo $period_label; ?>
                    (<?php echo formatDate($start_date); ?> - <?php echo formatDate($end_date); ?>)
                </div>
                
                <!-- Statistiques générales -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($general_stats['total_sales']); ?></h5>
                                <p class="card-text text-muted">Ventes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($general_stats['total_revenue'], 2); ?>$</h5>
                                <p class="card-text text-muted">Chiffre d'affaires</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-percentage fa-2x text-warning mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($general_stats['total_commissions'], 2); ?>$</h5>
                                <p class="card-text text-muted">Commissions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($general_stats['avg_sale_amount'], 2); ?>$</h5>
                                <p class="card-text text-muted">Panier moyen</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x text-secondary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($general_stats['unique_customers']); ?></h5>
                                <p class="card-text text-muted">Clients uniques</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-broom fa-2x text-primary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($cleaning_stats['total_cleanings']); ?></h5>
                                <p class="card-text text-muted">Ménages</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Graphiques -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Évolution des ventes
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="salesChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    Répartition par catégorie
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="categoryChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tableaux de données -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-trophy me-2"></i>
                                    Top Produits
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($top_products)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Produit</th>
                                                    <th>Vendus</th>
                                                    <th>CA</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_products as $product): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                        <td><span class="badge bg-primary"><?php echo number_format($product['total_sold']); ?></span></td>
                                                        <td><span class="text-success fw-bold"><?php echo number_format($product['total_revenue'], 2); ?>$</span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">Aucune vente sur cette période</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-star me-2"></i>
                                    Top Employés (Ventes)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($top_sales_employees)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Employé</th>
                                                    <th>Ventes</th>
                                                    <th>CA</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_sales_employees as $employee): ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                                            <br><small class="text-muted"><?php echo $employee['role']; ?></small>
                                                        </td>
                                                        <td><span class="badge bg-info"><?php echo number_format($employee['sales_count']); ?></span></td>
                                                        <td><span class="text-success fw-bold"><?php echo number_format($employee['total_revenue'], 2); ?>$</span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">Aucune vente sur cette période</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-broom me-2"></i>
                                    Top Employés (Ménages)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($top_cleaning_employees)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Employé</th>
                                                    <th>Sessions</th>
                                                    <th>Ménages</th>
                                                    <th>Salaire</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_cleaning_employees as $employee): ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                                            <br><small class="text-muted"><?php echo $employee['role']; ?></small>
                                                        </td>
                                                        <td><span class="badge bg-secondary"><?php echo number_format($employee['sessions_count']); ?></span></td>
                                                        <td><span class="badge bg-primary"><?php echo number_format($employee['total_cleanings']); ?></span></td>
                                                        <td><span class="text-success fw-bold"><?php echo number_format($employee['total_salary'], 2); ?>$</span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">Aucune session de ménage sur cette période</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-heart me-2"></i>
                                    Clients Fidèles
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($top_customers)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Client</th>
                                                    <th>Visites</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_customers as $customer): ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo htmlspecialchars($customer['name']); ?>
                                                            <?php if ($customer['phone']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($customer['phone']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><span class="badge bg-warning"><?php echo number_format($customer['visits_count']); ?></span></td>
                                                        <td><span class="text-success fw-bold"><?php echo number_format($customer['total_spent'], 2); ?>$</span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">Aucun client fidèle sur cette période</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistiques détaillées par catégorie -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-tags me-2"></i>
                            Analyse par Catégorie
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($category_stats)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Catégorie</th>
                                            <th>Produits</th>
                                            <th>Quantité vendue</th>
                                            <th>Chiffre d'affaires</th>
                                            <th>Part du CA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_category_revenue = array_sum(array_column($category_stats, 'total_revenue'));
                                        foreach ($category_stats as $category): 
                                            $revenue_percent = $total_category_revenue > 0 ? ($category['total_revenue'] / $total_category_revenue) * 100 : 0;
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($category['category_name']); ?></strong></td>
                                                <td><span class="badge bg-secondary"><?php echo number_format($category['products_count']); ?></span></td>
                                                <td><span class="badge bg-primary"><?php echo number_format($category['total_sold']); ?></span></td>
                                                <td><span class="text-success fw-bold"><?php echo number_format($category['total_revenue'], 2); ?>$</span></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $revenue_percent; ?>%">
                                                            <?php echo number_format($revenue_percent, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">Aucune donnée de catégorie disponible</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleCustomDates() {
            const period = document.getElementById('period').value;
            const startGroup = document.getElementById('start_date_group');
            const endGroup = document.getElementById('end_date_group');
            
            if (period === 'custom') {
                startGroup.style.display = 'block';
                endGroup.style.display = 'block';
            } else {
                startGroup.style.display = 'none';
                endGroup.style.display = 'none';
            }
        }
        
        // Graphique d'évolution des ventes
        const salesData = <?php echo json_encode($sales_evolution); ?>;
        const salesLabels = salesData.map(item => {
            const date = new Date(item.sale_day);
            return date.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' });
        });
        const salesRevenue = salesData.map(item => parseFloat(item.daily_revenue));
        const salesCount = salesData.map(item => parseInt(item.sales_count));
        
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Chiffre d\'affaires ($)',
                    data: salesRevenue,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y'
                }, {
                    label: 'Nombre de ventes',
                    data: salesCount,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1,
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
                            text: 'Date'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Chiffre d\'affaires ($)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Nombre de ventes'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        
        // Graphique en secteurs des catégories
        const categoryData = <?php echo json_encode($category_stats); ?>;
        const categoryLabels = categoryData.map(item => item.category_name);
        const categoryRevenue = categoryData.map(item => parseFloat(item.total_revenue));
        
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryRevenue,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed.toFixed(2) + '$ (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>