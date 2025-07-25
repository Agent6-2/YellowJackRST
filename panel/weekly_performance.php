<?php
/**
 * Page des performances hebdomadaires - Panel Patron Le Yellowjack
 * Suivi des performances CDD/CDI de vendredi à vendredi avec calcul des primes
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/week_functions.php';
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

// Vérifier que l'utilisateur est Patron
if (!$auth->hasPermission('Patron')) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'Performances Hebdomadaires';

// Obtenir la semaine active ou sélectionnée
$selected_week_id = $_GET['week_id'] ?? null;
if ($selected_week_id) {
    $current_week = getWeekById($selected_week_id);
} else {
    $current_week = getActiveWeekNew();
}

if (!$current_week) {
    $error_message = "Aucune semaine trouvée. Veuillez créer une semaine active.";
    $current_week = ['id' => 0, 'week_number' => 0, 'week_start' => date('Y-m-d'), 'week_end' => date('Y-m-d')];
}

$week_start = $current_week['week_start'];
$week_end = $current_week['week_end'];
$week_id = $current_week['id'];

// Messages
$success_message = '';
$error_message = '';

// Gestion du message de succès après redirection
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Performances calculées avec succès pour la semaine " . $current_week['week_number'] . " (du " . date('d/m/Y', strtotime($week_start)) . " au " . date('d/m/Y', strtotime($week_end)) . ")";
}

// Action de calcul/recalcul des performances
if ($_POST && isset($_POST['calculate_performance'])) {
    try {
        // Récupérer la configuration des primes
        $stmt = $db->prepare("SELECT config_key, config_value FROM weekly_performance_config");
        $stmt->execute();
        $config = [];
        while ($row = $stmt->fetch()) {
            $config[$row['config_key']] = $row['config_value'];
        }
        
        // Récupérer tous les utilisateurs actifs (CDD, CDI, Responsable, Patron)
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, role 
            FROM users 
            WHERE role IN ('CDD', 'CDI', 'Responsable', 'Patron') AND status = 'active'
        ");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        foreach ($users as $employee) {
            // Calculer les statistiques ménage pour la semaine spécifique
            $stmt = $db->prepare("
                SELECT 
                    COUNT(cs.id) as total_services,
                    COALESCE(SUM(cs.cleaning_count), 0) as total_menages,
                    COALESCE(SUM(cs.total_salary), 0) as total_salary,
                    COALESCE(SUM(cs.duration_minutes), 0) / 60 as total_hours
                FROM cleaning_services cs
                WHERE cs.user_id = ? 
                    AND cs.week_id = ?
                    AND cs.status = 'completed'
            ");
            $stmt->execute([$employee['id'], $week_id]);
            $cleaning_stats = $stmt->fetch();
            
            // Calculer les statistiques ventes pour la semaine spécifique
            $stmt = $db->prepare("
                SELECT 
                    COUNT(s.id) as total_ventes,
                    COALESCE(SUM(s.final_amount), 0) as total_revenue,
                    COALESCE(SUM(s.employee_commission), 0) as total_commissions,
                    COALESCE(SUM(
                        (SELECT SUM((p.selling_price - p.supplier_price) * si.quantity)
                         FROM sale_items si 
                         JOIN products p ON si.product_id = p.id 
                         WHERE si.sale_id = s.id)
                    ), 0) as total_profit
                FROM sales s
                WHERE s.user_id = ? 
                    AND s.week_id = ?
            ");
            $stmt->execute([$employee['id'], $week_id]);
            $sales_stats = $stmt->fetch();
            
            // Calculer les primes
            $prime_menage = 0;
            $prime_ventes = 0;
            
            // Prime ménage (différenciée par type de contrat)
            if ($cleaning_stats['total_menages'] > 0 && $cleaning_stats['total_salary'] > 0) {
                // Récupérer le pourcentage de commission ménage depuis les paramètres système
                $cleaning_rate_setting_key = '';
                switch ($employee['role']) {
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
                
                // Récupérer le pourcentage depuis la base de données
                $stmt_rate = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
                $stmt_rate->execute([$cleaning_rate_setting_key]);
                $cleaning_percentage = floatval($stmt_rate->fetchColumn() ?: 25); // Pourcentage par défaut
                
                // Calculer la prime basée sur le nouveau système (pourcentage de 60$ par ménage)
                $company_revenue_per_cleaning = 60;
                $total_company_revenue = $cleaning_stats['total_menages'] * $company_revenue_per_cleaning;
                $prime_percentage = $cleaning_percentage / 100; // Convertir en décimal
                
                // Calcul de la prime basée sur le revenu de l'entreprise (60$ par ménage)
                $prime_menage = $total_company_revenue * $prime_percentage;
                
                // Note: $cleaning_stats['total_salary'] contient déjà le salaire calculé selon le nouveau système
            }
            
            // Prime ventes désactivée - les employés ne reçoivent que les commissions immédiates
            $prime_ventes = 0; // Pas de prime ventes hebdomadaire
            
            // Prime totale = Prime ménage + Commissions totales
            $prime_totale = $prime_menage + $sales_stats['total_commissions'];
            
            // Insérer ou mettre à jour les performances
            $stmt = $db->prepare("
                INSERT INTO weekly_performance 
                (user_id, week_id, week_start, week_end, total_menages, total_salary_menage, total_hours_menage, 
                 total_ventes, total_revenue, total_commissions, prime_menage, prime_ventes, prime_totale, 
                 calculated_at, is_finalized) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE
                    total_menages = VALUES(total_menages),
                    total_salary_menage = VALUES(total_salary_menage),
                    total_hours_menage = VALUES(total_hours_menage),
                    total_ventes = VALUES(total_ventes),
                    total_revenue = VALUES(total_revenue),
                    total_commissions = VALUES(total_commissions),
                    prime_menage = VALUES(prime_menage),
                    prime_ventes = VALUES(prime_ventes),
                    prime_totale = VALUES(prime_totale),
                    calculated_at = NOW()
            ");
            
            // La finalisation doit être faite manuellement - pas de finalisation automatique
            $is_finalized = 0;
            
            $stmt->execute([
                $employee['id'], $week_id, $week_start, $week_end,
                $cleaning_stats['total_menages'], $cleaning_stats['total_salary'], $cleaning_stats['total_hours'],
                $sales_stats['total_ventes'], $sales_stats['total_revenue'], $sales_stats['total_commissions'],
                $prime_menage, $prime_ventes, $prime_totale, $is_finalized
            ]);
        }
        
        // Redirection pour actualiser les données
        header("Location: weekly_performance.php?week_id=" . $week_id . "&success=1");
        exit();
        
    } catch (Exception $e) {
        $error_message = "Erreur lors du calcul des performances : " . $e->getMessage();
    }
}

// Récupérer les performances de la semaine sélectionnée
$performances = [];
try {
    $stmt = $db->prepare("
        SELECT 
            wp.*,
            u.first_name,
            u.last_name,
            u.role
        FROM weekly_performance wp
        JOIN users u ON wp.user_id = u.id
        WHERE wp.week_id = ?
        ORDER BY wp.prime_totale DESC, u.first_name ASC
    ");
    $stmt->execute([$week_id]);
    $performances = $stmt->fetchAll();
} catch (Exception $e) {
    $performances = [];
}

// Les semaines disponibles sont maintenant récupérées via getAllWeeks() dans le HTML

// Calculer les totaux
$totals = [
    'total_menages' => 0,
    'total_salary_menage' => 0,
    'total_ventes' => 0,
    'total_revenue' => 0,
    'prime_menage_total' => 0,
    'prime_ventes_total' => 0,
    'total_commissions_total' => 0,
    'prime_totale_total' => 0
];

foreach ($performances as $perf) {
    $totals['total_menages'] += $perf['total_menages'];
    $totals['total_salary_menage'] += $perf['total_salary_menage'];
    $totals['total_ventes'] += $perf['total_ventes'];
    $totals['total_revenue'] += $perf['total_revenue'];
    $totals['prime_menage_total'] += $perf['prime_menage'];
    $totals['prime_ventes_total'] += $perf['prime_ventes'];
    $totals['total_commissions_total'] += $perf['total_commissions'];
    $totals['prime_totale_total'] += $perf['prime_totale'];
}

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/panel.css">
    
    <style>
        :root {
             --primary-color: #8B4513; /* Brun western */
             --secondary-color: #DAA520; /* Or/Jaune */
             --accent-color: #CD853F; /* Beige sable */
             --dark-color: #2F1B14; /* Brun foncé */
             --light-color: #F5DEB3; /* Beige clair */
             --text-dark: #1a1a1a;
             --text-light: #ffffff;
             --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
             --border-radius: 8px;
             --transition: all 0.3s ease;
             --sidebar-width: 250px;
         }
        
        .navbar {
            background: linear-gradient(135deg, var(--dark-color), var(--primary-color)) !important;
            box-shadow: var(--shadow);
            padding: 0.75rem 0;
            border-bottom: 3px solid var(--secondary-color);
        }
        
        .navbar-brand {
            font-family: 'Rye', cursive;
            font-size: 1.5rem;
            color: var(--secondary-color) !important;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
            font-weight: 400;
        }
        
        .navbar-nav .nav-link {
            color: var(--light-color) !important;
            font-weight: 500;
            transition: var(--transition);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
        }
        
        .navbar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--secondary-color) !important;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 80px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, 0.1);
            background: linear-gradient(180deg, var(--light-color), #ffffff);
            width: var(--sidebar-width);
            transition: var(--transition);
            min-height: 100vh;
        }
        
        .sidebar .nav-link {
            font-weight: 500;
            color: var(--text-dark);
            padding: 0.75rem 1.5rem;
            margin: 0.25rem 0.5rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            position: relative;
        }
        
        .sidebar .nav-link:hover {
            color: var(--secondary-color);
            background: rgba(218, 165, 32, 0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            color: var(--dark-color);
            background: linear-gradient(45deg, var(--secondary-color), #FFD700);
            font-weight: 600;
            box-shadow: var(--shadow);
        }
        
        .sidebar .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease-in-out;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .btn {
            border-radius: 8px;
        }
        
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-trophy me-2"></i>
                    <?php echo $page_title; ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <form method="POST" class="d-inline">
                        <button type="submit" name="calculate_performance" class="btn btn-primary">
                            <i class="fas fa-calculator me-1"></i>
                            Calculer les performances
                        </button>
                    </form>
                </div>
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
            
            <!-- Sélection de semaine -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-week me-2"></i>
                        Sélection de semaine (Vendredi à Vendredi exclu)
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="week_id" class="form-label">Semaine (début vendredi)</label>
                            <select class="form-select" id="week_id" name="week_id" onchange="this.form.submit()">
                                <?php
                                // Récupérer toutes les semaines disponibles
                                $all_weeks = getAllWeeks();
                                foreach ($all_weeks as $week) {
                                    $selected = ($week['id'] == $week_id) ? 'selected' : '';
                                    echo "<option value='{$week['id']}' {$selected}>";
                                    echo "Semaine {$week['week_number']} (" . date('d/m/Y', strtotime($week['week_start'])) . " - " . date('d/m/Y', strtotime($week['week_end'])) . ")";
                                    if ($week['is_active']) echo " - ACTIVE";
                                    echo "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Période :</strong> Semaine <?php echo $current_week['week_number']; ?> (<?php echo date('d/m/Y', strtotime($week_start)); ?> - <?php echo date('d/m/Y', strtotime($week_end)); ?>)
                                <?php if (date('Y-m-d') <= $week_end): ?>
                                    <span class="badge bg-warning text-dark ms-2">En cours</span>
                                <?php else: ?>
                                    <span class="badge bg-success ms-2">Finalisée</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Résumé des totaux -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-broom fa-2x text-primary mb-2"></i>
                            <h5 class="card-title"><?php echo number_format($totals['total_menages']); ?></h5>
                            <p class="card-text text-muted">Ménages totaux</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-shopping-cart fa-2x text-success mb-2"></i>
                            <h5 class="card-title"><?php echo number_format($totals['total_ventes']); ?></h5>
                            <p class="card-text text-muted">Ventes totales</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-euro-sign fa-2x text-info mb-2"></i>
                            <h5 class="card-title"><?php echo number_format($totals['total_revenue'], 2); ?>€</h5>
                            <p class="card-text text-muted">CA total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-gift fa-2x text-warning mb-2"></i>
                            <h5 class="card-title"><?php echo number_format($totals['prime_totale_total'], 2); ?>€</h5>
                            <p class="card-text text-muted">Primes totales</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tableau des performances -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Performances individuelles CDD/CDI
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($performances)): ?>
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Aucune performance calculée pour cette semaine. Cliquez sur "Calculer les performances" pour générer les données.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Employé</th>
                                        <th>Rôle</th>
                                        <th class="text-center">Ménages</th>
                                        <th class="text-center">Salaire Ménage</th>
                                        <th class="text-center">Ventes</th>
                                        <th class="text-center">CA Ventes</th>
                                        <th class="text-center">Prime Ménage</th>
                                        <th class="text-center">Commissions Totales</th>
                                        <th class="text-center"><strong>Prime Totale</strong></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($performances as $perf): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($perf['first_name'] . ' ' . $perf['last_name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $perf['role'] === 'CDI' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo htmlspecialchars($perf['role']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?php echo number_format($perf['total_menages']); ?></td>
                                            <td class="text-center"><?php echo number_format($perf['total_salary_menage'], 2); ?>€</td>
                                            <td class="text-center"><?php echo number_format($perf['total_ventes']); ?></td>
                                            <td class="text-center"><?php echo number_format($perf['total_revenue'], 2); ?>€</td>
                                            <td class="text-center text-success">
                                                <strong><?php echo number_format($perf['prime_menage'], 2); ?>€</strong>
                                            </td>
                                            <td class="text-center text-info">
                                                <strong><?php echo number_format($perf['total_commissions'], 2); ?>€</strong>
                                            </td>
                                            <td class="text-center text-warning">
                                                <strong><?php echo number_format($perf['prime_totale'], 2); ?>€</strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <th colspan="2">TOTAUX</th>
                                        <th class="text-center"><?php echo number_format($totals['total_menages']); ?></th>
                                        <th class="text-center"><?php echo number_format($totals['total_salary_menage'], 2); ?>€</th>
                                        <th class="text-center"><?php echo number_format($totals['total_ventes']); ?></th>
                                        <th class="text-center"><?php echo number_format($totals['total_revenue'], 2); ?>€</th>
                                        <th class="text-center text-success">
                                            <strong><?php echo number_format($totals['prime_menage_total'], 2); ?>€</strong>
                                        </th>
                                        <th class="text-center text-info">
                                            <strong><?php echo number_format($totals['total_commissions_total'], 2); ?>€</strong>
                                        </th>
                                        <th class="text-center text-warning">
                                            <strong><?php echo number_format($totals['prime_totale_total'], 2); ?>€</strong>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>