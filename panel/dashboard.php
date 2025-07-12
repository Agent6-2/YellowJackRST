<?php
/**
 * Tableau de bord principal - Panel Employé Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../includes/auth.php';
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

// Statistiques pour le tableau de bord
$stats = [];

try {
    // Statistiques des ménages pour l'utilisateur actuel
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_services,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as services_en_cours,
            SUM(cleaning_count) as total_menages,
            SUM(total_salary) as total_salaire
        FROM cleaning_services 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $stats['cleaning'] = $stmt->fetch();
    
    // Statistiques des ventes si CDI ou plus
    if ($auth->canAccessCashRegister()) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_ventes,
                SUM(final_amount) as ca_total,
                SUM(employee_commission) as commissions_total
            FROM sales 
            WHERE user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $stats['sales'] = $stmt->fetch();
    }
    
    // Statistiques globales pour les responsables/patrons
    if ($auth->canManageEmployees()) {
        // Nombre d'employés actifs
        $stmt = $db->query("SELECT COUNT(*) as total_employees FROM users WHERE status = 'active'");
        $stats['employees'] = $stmt->fetch()['total_employees'];
        
        // CA du jour
        $stmt = $db->query("
            SELECT SUM(final_amount) as ca_jour 
            FROM sales 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stats['ca_jour'] = $stmt->fetch()['ca_jour'] ?? 0;
        
        // Produits en rupture
        $stmt = $db->query("
            SELECT COUNT(*) as produits_rupture 
            FROM products 
            WHERE stock_quantity <= min_stock_alert AND is_active = 1
        ");
        $stats['produits_rupture'] = $stmt->fetch()['produits_rupture'];
    }
    
} catch (PDOException $e) {
    error_log('Erreur statistiques dashboard : ' . $e->getMessage());
}

// Service en cours pour l'utilisateur
$service_en_cours = null;
try {
    $stmt = $db->prepare("
        SELECT * FROM cleaning_services 
        WHERE user_id = ? AND status = 'in_progress' 
        ORDER BY start_time DESC LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $service_en_cours = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Erreur service en cours : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Panel Employé | Le Yellowjack</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Rye&family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../assets/css/panel.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2 western-font">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        Tableau de Bord
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="badge bg-warning text-dark fs-6">
                                <i class="fas fa-user-tag me-1"></i>
                                <?php echo htmlspecialchars($user['role']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Message de bienvenue -->
                <div class="alert alert-info border-0 mb-4" style="background: linear-gradient(45deg, rgba(218, 165, 32, 0.1), rgba(139, 69, 19, 0.1));">
                    <h4 class="alert-heading">
                        <i class="fas fa-hand-wave me-2"></i>
                        Bienvenue, <?php echo htmlspecialchars($user['first_name']); ?> !
                    </h4>
                    <p class="mb-0">Vous êtes connecté(e) en tant que <strong><?php echo htmlspecialchars($user['role']); ?></strong>. Voici un aperçu de vos activités au Yellowjack.</p>
                </div>
                
                <!-- Service en cours -->
                <?php if ($service_en_cours): ?>
                <div class="alert alert-success border-0 mb-4">
                    <h5 class="alert-heading">
                        <i class="fas fa-clock me-2"></i>
                        Service en cours
                    </h5>
                    <p class="mb-2">
                        <strong>Début :</strong> <?php echo formatDateTime($service_en_cours['start_time'], 'd/m/Y H:i'); ?><br>
                        <strong>Durée actuelle :</strong> 
                        <span id="service-duration" data-start="<?php echo $service_en_cours['start_time']; ?>"></span>
                    </p>
                    <a href="cleaning.php" class="btn btn-success btn-sm">
                        <i class="fas fa-stop me-1"></i>
                        Terminer le service
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Statistiques principales -->
                <div class="row mb-4">
                    <!-- Statistiques ménages -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Mes Ménages
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['cleaning']['total_menages'] ?? 0); ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo number_format($stats['cleaning']['total_services'] ?? 0); ?> services
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-broom fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Salaire total -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Salaire Total
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['cleaning']['total_salaire'] ?? 0, 2); ?>$
                                        </div>
                                        <small class="text-muted">Ménages uniquement</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistiques ventes (CDI+) -->
                    <?php if ($auth->canAccessCashRegister() && isset($stats['sales'])): ?>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Mes Ventes
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['sales']['total_ventes'] ?? 0); ?>
                                        </div>
                                        <small class="text-muted">
                                            CA: <?php echo number_format($stats['sales']['ca_total'] ?? 0, 2); ?>$
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-cash-register fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Mes Commissions
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['sales']['commissions_total'] ?? 0, 2); ?>$
                                        </div>
                                        <small class="text-muted">25% du CA</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-percentage fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Statistiques globales (Responsables/Patrons) -->
                    <?php if ($auth->canManageEmployees()): ?>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Employés Actifs
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['employees'] ?? 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            CA Aujourd'hui
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['ca_jour'] ?? 0, 2); ?>$
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Alertes Stock
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['produits_rupture'] ?? 0); ?>
                                        </div>
                                        <small class="text-muted">Produits en rupture</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Actions rapides -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i>
                                    Actions Rapides
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Actions pour tous -->
                                    <div class="col-md-6 mb-3">
                                        <a href="cleaning.php" class="btn btn-outline-warning w-100 p-3">
                                            <i class="fas fa-broom fa-2x mb-2 d-block"></i>
                                            <strong>Gestion Ménages</strong><br>
                                            <small>Prendre/terminer un service</small>
                                        </a>
                                    </div>
                                    
                                    <!-- Actions CDI+ -->
                                    <?php if ($auth->canAccessCashRegister()): ?>
                                    <div class="col-md-6 mb-3">
                                        <a href="cashier.php" class="btn btn-outline-info w-100 p-3">
                                            <i class="fas fa-cash-register fa-2x mb-2 d-block"></i>
                                            <strong>Caisse Enregistreuse</strong><br>
                                            <small>Effectuer une vente</small>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Actions Responsables+ -->
                                    <?php if ($auth->canManageEmployees()): ?>
                                    <div class="col-md-6 mb-3">
                                        <a href="employees.php" class="btn btn-outline-primary w-100 p-3">
                                            <i class="fas fa-users fa-2x mb-2 d-block"></i>
                                            <strong>Gestion Employés</strong><br>
                                            <small>Gérer l'équipe</small>
                                        </a>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <a href="inventory.php" class="btn btn-outline-success w-100 p-3">
                                            <i class="fas fa-boxes fa-2x mb-2 d-block"></i>
                                            <strong>Gestion Stock</strong><br>
                                            <small>Inventaire et produits</small>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Informations
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Votre Rôle :</strong><br>
                                    <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($user['role']); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Dernière Connexion :</strong><br>
                                    <small class="text-muted"><?php echo formatDateTime($_SESSION['login_time'], 'd/m/Y H:i'); ?></small>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Permissions :</strong><br>
                                    <ul class="list-unstyled mb-0">
                                        <li><i class="fas fa-check text-success me-1"></i> Gestion ménages</li>
                                        <?php if ($auth->canAccessCashRegister()): ?>
                                        <li><i class="fas fa-check text-success me-1"></i> Caisse enregistreuse</li>
                                        <?php endif; ?>
                                        <?php if ($auth->canManageEmployees()): ?>
                                        <li><i class="fas fa-check text-success me-1"></i> Gestion employés</li>
                                        <li><i class="fas fa-check text-success me-1"></i> Gestion stock</li>
                                        <?php endif; ?>
                                    </ul>
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
        // Mise à jour de la durée du service en cours
        function updateServiceDuration() {
            const durationElement = document.getElementById('service-duration');
            if (durationElement) {
                const startTime = new Date(durationElement.dataset.start);
                const now = new Date();
                const diff = now - startTime;
                
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                
                durationElement.textContent = `${hours}h ${minutes}min`;
            }
        }
        
        // Mettre à jour toutes les minutes
        if (document.getElementById('service-duration')) {
            updateServiceDuration();
            setInterval(updateServiceDuration, 60000);
        }
    </script>
</body>
</html>