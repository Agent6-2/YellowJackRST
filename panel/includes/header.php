<?php
/**
 * En-tête du panel employé - Le Yellowjack
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
?>
<nav class="navbar navbar-dark sticky-top flex-md-nowrap p-0 shadow">
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="dashboard.php">
        <i class="fas fa-glass-whiskey me-2"></i>
        Le Yellowjack
    </a>
    
    <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
        <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="navbar-nav">
        <div class="nav-item text-nowrap">
            <div class="dropdown">
                <a class="nav-link px-3 dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user me-2"></i>
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    <span class="badge bg-warning text-dark ms-2"><?php echo htmlspecialchars($user['role']); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <h6 class="dropdown-header">
                            <i class="fas fa-user-circle me-2"></i>
                            Mon Compte
                        </h6>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user-edit me-2"></i>
                            Mon Profil
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="../index.php" target="_blank">
                            <i class="fas fa-external-link-alt me-2"></i>
                            Site Vitrine
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>