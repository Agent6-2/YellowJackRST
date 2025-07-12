<?php
/**
 * Barre latérale de navigation - Panel Employé Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

if (!isset($auth)) {
    require_once '../includes/auth.php';
    $auth = getAuth();
}

if (!isset($user)) {
    $user = $auth->getCurrentUser();
}

// Déterminer la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="sidebar-sticky pt-3">
        <!-- Navigation principale -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Tableau de Bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'my_stats.php' ? 'active' : ''; ?>" href="my_stats.php">
                    <i class="fas fa-chart-user"></i>
                    Mes Statistiques
                </a>
            </li>
        </ul>
        
        <!-- Section Ménages (Tous les rôles) -->
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Ménages</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'cleaning.php' ? 'active' : ''; ?>" href="cleaning.php">
                    <i class="fas fa-broom"></i>
                    Gestion Ménages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'cleaning_history.php' ? 'active' : ''; ?>" href="cleaning_history.php">
                    <i class="fas fa-history"></i>
                    Historique
                </a>
            </li>
        </ul>
        
        <!-- Section Caisse (CDI et plus) -->
        <?php if ($auth->canAccessCashRegister()): ?>
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Caisse Enregistreuse</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'cashier.php' ? 'active' : ''; ?>" href="cashier.php">
                    <i class="fas fa-cash-register"></i>
                    Nouvelle Vente
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'sales_history.php' ? 'active' : ''; ?>" href="sales_history.php">
                    <i class="fas fa-receipt"></i>
                    Historique Ventes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'customers.php' ? 'active' : ''; ?>" href="customers.php">
                    <i class="fas fa-users"></i>
                    Clients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'companies.php' ? 'active' : ''; ?>" href="companies.php">
                    <i class="fas fa-building"></i>
                    Entreprises
                </a>
            </li>
        </ul>
        <?php endif; ?>
        
        <!-- Section Gestion (Responsables et Patrons) -->
        <?php if ($auth->canManageEmployees()): ?>
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Gestion</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'employees.php' ? 'active' : ''; ?>" href="employees.php">
                    <i class="fas fa-user-tie"></i>
                    Employés
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'inventory.php' ? 'active' : ''; ?>" href="inventory.php">
                    <i class="fas fa-boxes"></i>
                    Inventaire
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'products.php' ? 'active' : ''; ?>" href="products.php">
                    <i class="fas fa-wine-bottle"></i>
                    Produits
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    Rapports
                </a>
            </li>
            <?php if ($auth->hasPermission('Patron')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'weekly_performance.php' ? 'active' : ''; ?>" href="weekly_performance.php">
                    <i class="fas fa-trophy"></i>
                    Performances Hebdo
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'taxes.php' ? 'active' : ''; ?>" href="taxes.php">
                    <i class="fas fa-calculator"></i>
                    Impôts
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>
        
        <!-- Section Statistiques -->
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Statistiques</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'my_stats.php' ? 'active' : ''; ?>" href="my_stats.php">
                    <i class="fas fa-chart-line"></i>
                    Mes Statistiques
                </a>
            </li>
            <?php if ($auth->canAccessCashRegister()): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'rankings.php' ? 'active' : ''; ?>" href="rankings.php">
                    <i class="fas fa-trophy"></i>
                    Classements
                </a>
            </li>
            <?php endif; ?>
            <?php if ($auth->canManageEmployees()): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                    <i class="fas fa-chart-pie"></i>
                    Analytics
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <!-- Section Paramètres (Responsables et Patrons) -->
        <?php if ($auth->canManageEmployees()): ?>
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Paramètres</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog"></i>
                    Configuration
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'bonuses.php' ? 'active' : ''; ?>" href="bonuses.php">
                    <i class="fas fa-gift"></i>
                    Primes
                </a>
            </li>
        </ul>
        <?php endif; ?>
        
        <!-- Section Aide -->
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Aide</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" href="../index.php" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                    Site Vitrine
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'help.php' ? 'active' : ''; ?>" href="help.php">
                    <i class="fas fa-question-circle"></i>
                    Documentation
                </a>
            </li>
        </ul>
        
        <!-- Informations utilisateur -->
        <div class="mt-4 p-3 bg-light rounded mx-3">
            <div class="text-center">
                <div class="mb-2">
                    <i class="fas fa-user-circle fa-2x text-warning"></i>
                </div>
                <h6 class="mb-1"><?php echo htmlspecialchars($user['first_name']); ?></h6>
                <small class="text-muted"><?php echo htmlspecialchars($user['role']); ?></small>
                <div class="mt-2">
                    <small class="text-muted d-block">
                        <i class="fas fa-clock me-1"></i>
                        Connecté depuis <?php echo formatDateTime($_SESSION['login_time'], 'H:i'); ?>
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Déconnexion -->
        <div class="mt-3 px-3">
            <a href="logout.php" class="btn btn-outline-danger w-100">
                <i class="fas fa-sign-out-alt me-2"></i>
                Déconnexion
            </a>
        </div>
    </div>
</nav>

<script>
// Gestion du menu mobile
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.querySelector('.navbar-toggler');
    const sidebar = document.querySelector('#sidebarMenu');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        
        // Fermer le menu mobile quand on clique sur un lien
        const sidebarLinks = sidebar.querySelectorAll('.nav-link');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    sidebar.classList.remove('show');
                }
            });
        });
    }
});
</script>