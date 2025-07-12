<?php
/**
 * Gestion des Primes - Panel Employé Le Yellowjack
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
            case 'add_bonus':
                $employee_id = intval($_POST['employee_id'] ?? 0);
                $amount = floatval($_POST['amount'] ?? 0);
                $reason = trim($_POST['reason'] ?? '');
                $bonus_date = $_POST['bonus_date'] ?? date('Y-m-d');
                
                if ($employee_id <= 0) {
                    $error = 'Veuillez sélectionner un employé.';
                } elseif ($amount <= 0) {
                    $error = 'Le montant de la prime doit être positif.';
                } elseif (empty($reason)) {
                    $error = 'La raison de la prime est obligatoire.';
                } else {
                    try {
                        // Vérifier que l'employé existe et est actif
                        $stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE id = ? AND status = 'active'");
                        $stmt->execute([$employee_id]);
                        $employee = $stmt->fetch();
                        
                        if (!$employee) {
                            $error = 'Employé introuvable ou inactif.';
                        } else {
                            // Ajouter la prime
                            $stmt = $db->prepare("
                                INSERT INTO bonuses (user_id, amount, reason, given_by) 
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$employee_id, $amount, $reason, $user['id']]);
                            
                            $message = 'Prime de ' . number_format($amount, 2) . '$ ajoutée avec succès pour ' . 
                                      $employee['first_name'] . ' ' . $employee['last_name'] . ' !';
                        }
                    } catch (Exception $e) {
                        $error = 'Erreur lors de l\'ajout de la prime.';
                    }
                }
                break;
                
            case 'delete_bonus':
                $bonus_id = intval($_POST['bonus_id'] ?? 0);
                
                if ($bonus_id <= 0) {
                    $error = 'Prime invalide.';
                } else {
                    try {
                        // Vérifier que la prime existe
                        $stmt = $db->prepare("SELECT id FROM bonuses WHERE id = ?");
                        $stmt->execute([$bonus_id]);
                        
                        if (!$stmt->fetch()) {
                            $error = 'Prime introuvable.';
                        } else {
                            // Supprimer la prime
                            $stmt = $db->prepare("DELETE FROM bonuses WHERE id = ?");
                            $stmt->execute([$bonus_id]);
                            
                            $message = 'Prime supprimée avec succès !';
                        }
                    } catch (Exception $e) {
                        $error = 'Erreur lors de la suppression de la prime.';
                    }
                }
                break;
        }
    }
}

// Paramètres de pagination et filtres
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search_employee = trim($_GET['search_employee'] ?? '');
$filter_month = $_GET['filter_month'] ?? '';
$filter_year = $_GET['filter_year'] ?? date('Y');

// Construction de la requête avec filtres
$where_conditions = [];
$params = [];

if (!empty($search_employee)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%$search_employee%";
    $params[] = "%$search_employee%";
}

if (!empty($filter_month)) {
    $where_conditions[] = "MONTH(b.created_at) = ?";
    $params[] = $filter_month;
}

if (!empty($filter_year)) {
    $where_conditions[] = "YEAR(b.created_at) = ?";
    $params[] = $filter_year;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Récupérer les primes avec pagination
$bonuses_query = "
    SELECT 
        b.*,
        u.first_name,
        u.last_name,
        u.role,
        creator.first_name as creator_first_name,
        creator.last_name as creator_last_name
    FROM bonuses b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN users creator ON b.given_by = creator.id
    $where_clause
    ORDER BY b.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($bonuses_query);
$stmt->execute($params);
$bonuses = $stmt->fetchAll();

// Compter le total pour la pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM bonuses b
    JOIN users u ON b.user_id = u.id
    $where_clause
";

$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_bonuses = $stmt->fetch()['total'];
$total_pages = ceil($total_bonuses / $per_page);

// Récupérer les employés actifs pour le formulaire
$employees_query = "SELECT id, first_name, last_name, role FROM users WHERE status = 'active' ORDER BY first_name, last_name";
$stmt = $db->prepare($employees_query);
$stmt->execute();
$employees = $stmt->fetchAll();

// Statistiques des primes
$stats_query = "
    SELECT 
        COUNT(*) as total_bonuses,
        COALESCE(SUM(amount), 0) as total_amount,
        COALESCE(AVG(amount), 0) as avg_amount,
        COUNT(DISTINCT user_id) as employees_with_bonuses
    FROM bonuses 
    WHERE YEAR(created_at) = ?
";
$stmt = $db->prepare($stats_query);
$stmt->execute([$filter_year]);
$stats = $stmt->fetch();

// Statistiques mensuelles pour le graphique
$monthly_stats_query = "
    SELECT 
        MONTH(created_at) as month,
        COUNT(*) as count,
        SUM(amount) as total
    FROM bonuses 
    WHERE YEAR(created_at) = ?
    GROUP BY MONTH(created_at)
    ORDER BY month
";
$stmt = $db->prepare($monthly_stats_query);
$stmt->execute([$filter_year]);
$monthly_stats = $stmt->fetchAll();

// Top employés avec le plus de primes
$top_employees_query = "
    SELECT 
        u.first_name,
        u.last_name,
        u.role,
        COUNT(*) as bonus_count,
        SUM(b.amount) as total_amount
    FROM bonuses b
    JOIN users u ON b.user_id = u.id
    WHERE YEAR(b.created_at) = ?
    GROUP BY u.id, u.first_name, u.last_name, u.role
    ORDER BY total_amount DESC
    LIMIT 5
";
$stmt = $db->prepare($top_employees_query);
$stmt->execute([$filter_year]);
$top_employees = $stmt->fetchAll();

$page_title = 'Gestion des Primes';
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
                        <i class="fas fa-gift me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBonusModal">
                                <i class="fas fa-plus me-1"></i>
                                Ajouter une prime
                            </button>
                        </div>
                        <div class="btn-group">
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
                
                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-gift fa-2x text-primary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['total_bonuses']); ?></h5>
                                <p class="card-text text-muted">Primes <?php echo $filter_year; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['total_amount'], 2); ?>$</h5>
                                <p class="card-text text-muted">Montant total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-chart-bar fa-2x text-info mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['avg_amount'], 2); ?>$</h5>
                                <p class="card-text text-muted">Prime moyenne</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x text-warning mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['employees_with_bonuses']); ?></h5>
                                <p class="card-text text-muted">Employés primés</p>
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
                                    Évolution des primes <?php echo $filter_year; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-trophy me-2"></i>
                                    Top Employés
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($top_employees)): ?>
                                    <p class="text-muted text-center">Aucune prime pour <?php echo $filter_year; ?></p>
                                <?php else: ?>
                                    <?php foreach ($top_employees as $index => $employee): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="badge bg-<?php echo $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'dark'); ?> me-2">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                                <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo $employee['bonus_count']; ?> prime(s)</small>
                                            </div>
                                            <div class="text-end">
                                                <strong class="text-success"><?php echo number_format($employee['total_amount'], 2); ?>$</strong>
                                            </div>
                                        </div>
                                        <?php if ($index < count($top_employees) - 1): ?>
                                            <hr class="my-2">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtres -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search_employee" class="form-label">Rechercher un employé</label>
                                <input type="text" class="form-control" id="search_employee" name="search_employee" 
                                       value="<?php echo htmlspecialchars($search_employee); ?>" 
                                       placeholder="Nom ou prénom...">
                            </div>
                            <div class="col-md-2">
                                <label for="filter_month" class="form-label">Mois</label>
                                <select class="form-select" id="filter_month" name="filter_month">
                                    <option value="">Tous les mois</option>
                                    <?php
                                    $months = [
                                        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                                        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                                        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
                                    ];
                                    foreach ($months as $num => $name) {
                                        $selected = ($filter_month == $num) ? 'selected' : '';
                                        echo "<option value='$num' $selected>$name</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="filter_year" class="form-label">Année</label>
                                <select class="form-select" id="filter_year" name="filter_year">
                                    <?php
                                    $current_year = date('Y');
                                    for ($year = $current_year; $year >= $current_year - 2; $year--) {
                                        $selected = ($filter_year == $year) ? 'selected' : '';
                                        echo "<option value='$year' $selected>$year</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        Filtrer
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <a href="bonuses.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        Réinitialiser
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Liste des primes -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Historique des primes
                            <span class="badge bg-primary ms-2"><?php echo number_format($total_bonuses); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bonuses)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-gift fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucune prime trouvée</h5>
                                <p class="text-muted">Aucune prime ne correspond aux critères de recherche.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Employé</th>
                                            <th>Rôle</th>
                                            <th>Montant</th>
                                            <th>Raison</th>
                                            <th>Créé par</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bonuses as $bonus): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo formatDate($bonus['bonus_date']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo formatDate($bonus['created_at']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($bonus['first_name'] . ' ' . $bonus['last_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match($bonus['role']) {
                                                            'CDD' => 'primary',
                                                            'CDI' => 'info',
                                                            'Responsable' => 'warning',
                                                            'Patron' => 'danger',
                                                            default => 'secondary'
                                                        };
                                                    ?>">
                                                        <?php echo htmlspecialchars($bonus['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong class="text-success"><?php echo number_format($bonus['amount'], 2); ?>$</strong>
                                                </td>
                                                <td>
                                                    <span class="text-truncate d-inline-block" style="max-width: 200px;" 
                                                          title="<?php echo htmlspecialchars($bonus['reason']); ?>">
                                                        <?php echo htmlspecialchars($bonus['reason']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($bonus['creator_first_name']): ?>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($bonus['creator_first_name'] . ' ' . $bonus['creator_last_name']); ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <small class="text-muted">Système</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                                            onclick="deleteBonus(<?php echo $bonus['id']; ?>, '<?php echo htmlspecialchars($bonus['first_name'] . ' ' . $bonus['last_name']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Navigation des primes">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search_employee=<?php echo urlencode($search_employee); ?>&filter_month=<?php echo $filter_month; ?>&filter_year=<?php echo $filter_year; ?>">
                                                    Précédent
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $start = max(1, $page - 2);
                                        $end = min($total_pages, $page + 2);
                                        
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search_employee=<?php echo urlencode($search_employee); ?>&filter_month=<?php echo $filter_month; ?>&filter_year=<?php echo $filter_year; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search_employee=<?php echo urlencode($search_employee); ?>&filter_month=<?php echo $filter_month; ?>&filter_year=<?php echo $filter_year; ?>">
                                                    Suivant
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal d'ajout de prime -->
    <div class="modal fade" id="addBonusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-gift me-2"></i>
                        Ajouter une prime
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                        <input type="hidden" name="action" value="add_bonus">
                        
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Employé *</label>
                            <select class="form-select" id="employee_id" name="employee_id" required>
                                <option value="">Sélectionner un employé...</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['role'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Montant ($) *</label>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           step="0.01" min="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bonus_date" class="form-label">Date de la prime *</label>
                                    <input type="date" class="form-control" id="bonus_date" name="bonus_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">Raison de la prime *</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" 
                                      placeholder="Ex: Excellent travail, performance exceptionnelle, etc." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Ajouter la prime
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Graphique mensuel
        const monthlyData = <?php echo json_encode($monthly_stats); ?>;
        const monthNames = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        
        // Préparer les données pour le graphique
        const chartData = new Array(12).fill(0);
        const chartAmounts = new Array(12).fill(0);
        
        monthlyData.forEach(item => {
            chartData[item.month - 1] = item.count;
            chartAmounts[item.month - 1] = parseFloat(item.total);
        });
        
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthNames,
                datasets: [{
                    label: 'Nombre de primes',
                    data: chartData,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Montant total ($)',
                    data: chartAmounts,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
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
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Nombre de primes'
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
                },
                plugins: {
                    legend: {
                        display: true
                    }
                }
            }
        });
        
        function deleteBonus(bonusId, employeeName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer cette prime pour ${employeeName} ?\n\nCette action est irréversible.`)) {
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
                actionInput.value = 'delete_bonus';
                
                const bonusIdInput = document.createElement('input');
                bonusIdInput.type = 'hidden';
                bonusIdInput.name = 'bonus_id';
                bonusIdInput.value = bonusId;
                
                form.appendChild(csrfInput);
                form.appendChild(actionInput);
                form.appendChild(bonusIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>