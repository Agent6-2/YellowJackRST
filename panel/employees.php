<?php
/**
 * Gestion des employés - Panel Employé Le Yellowjack
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
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Token de sécurité invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_employee':
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $role = $_POST['role'] ?? '';
                // Note: phone column doesn't exist in users table
                $email = trim($_POST['email'] ?? '');
                
                if (empty($username) || empty($password) || empty($first_name) || empty($last_name) || empty($role)) {
                    $error = 'Tous les champs obligatoires doivent être remplis.';
                } elseif (!in_array($role, ['CDD', 'CDI', 'Responsable', 'Patron'])) {
                    $error = 'Rôle invalide.';
                } elseif (strlen($password) < 6) {
                    $error = 'Le mot de passe doit contenir au moins 6 caractères.';
                } else {
                    try {
                        // Vérifier si l'utilisateur existe déjà
                        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                        $stmt->execute([$username]);
                        if ($stmt->fetch()) {
                            $error = 'Un employé avec ce nom d\'utilisateur existe déjà.';
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare("
                                INSERT INTO users (username, password_hash, first_name, last_name, role, email, status, hire_date) 
                                VALUES (?, ?, ?, ?, ?, ?, 'active', CURDATE())
                            ");
                            $stmt->execute([$username, $hashed_password, $first_name, $last_name, $role, $email]);
                            $message = 'Employé ajouté avec succès !';
                        }
                    } catch (Exception $e) {
                        $error = 'Erreur lors de l\'ajout de l\'employé : ' . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_employee':
                $employee_id = intval($_POST['employee_id']);
                $username = trim($_POST['username'] ?? '');
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $role = $_POST['role'] ?? '';
                // Note: phone column doesn't exist in users table
                $email = trim($_POST['email'] ?? '');
                $new_password = $_POST['new_password'] ?? '';
                
                if (empty($username) || empty($first_name) || empty($last_name) || empty($role)) {
                    $error = 'Tous les champs obligatoires doivent être remplis.';
                } elseif (!in_array($role, ['CDD', 'CDI', 'Responsable', 'Patron'])) {
                    $error = 'Rôle invalide.';
                } elseif (!empty($new_password) && strlen($new_password) < 6) {
                    $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
                } else {
                    try {
                        // Vérifier si un autre utilisateur a le même nom d'utilisateur
                        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                        $stmt->execute([$username, $employee_id]);
                        if ($stmt->fetch()) {
                            $error = 'Un autre employé avec ce nom d\'utilisateur existe déjà.';
                        } else {
                            if (!empty($new_password)) {
                                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $stmt = $db->prepare("
                                    UPDATE users 
                                    SET username = ?, password = ?, first_name = ?, last_name = ?, role = ?, email = ? 
                                    WHERE id = ?
                                ");
                                $stmt->execute([$username, $hashed_password, $first_name, $last_name, $role, $email, $employee_id]);
                            } else {
                                $stmt = $db->prepare("
                                    UPDATE users 
                                    SET username = ?, first_name = ?, last_name = ?, role = ?, email = ? 
                                    WHERE id = ?
                                ");
                                $stmt->execute([$username, $first_name, $last_name, $role, $email, $employee_id]);
                            }
                            $message = 'Employé modifié avec succès !';
                        }
                    } catch (Exception $e) {
                        $error = 'Erreur lors de la modification de l\'employé.';
                    }
                }
                break;
                
            case 'toggle_status':
                $employee_id = intval($_POST['employee_id']);
                try {
                    // Ne pas permettre de désactiver son propre compte
                    if ($employee_id === $user['id']) {
                        $error = 'Vous ne pouvez pas désactiver votre propre compte.';
                    } else {
                        $stmt = $db->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'suspended' ELSE 'active' END WHERE id = ?");
                        $stmt->execute([$employee_id]);
                        $message = 'Statut de l\'employé modifié avec succès !';
                    }
                } catch (Exception $e) {
                    $error = 'Erreur lors de la modification du statut.';
                }
                break;
        }
    }
}

// Paramètres de recherche et pagination
$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Construction de la requête
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = '(username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($role_filter) {
    $where_conditions[] = 'role = ?';
    $params[] = $role_filter;
}

if ($status_filter === 'active') {
    $where_conditions[] = "status = 'active'";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "status = 'suspended'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Compter le total
$count_query = "SELECT COUNT(*) FROM users $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Récupérer les employés avec leurs statistiques
$query = "
    SELECT 
        u.*,
        COUNT(DISTINCT cs.id) as total_cleaning_sessions,
        COALESCE(SUM(cs.cleaning_count), 0) as total_cleanings,
        COALESCE(SUM(cs.total_salary), 0) as total_cleaning_salary,
        COUNT(DISTINCT s.id) as total_sales,
        COALESCE(SUM(s.employee_commission), 0) as total_commission
    FROM users u
    LEFT JOIN cleaning_services cs ON u.id = cs.user_id
    LEFT JOIN sales s ON u.id = s.user_id
    $where_clause
    GROUP BY u.id
    ORDER BY u.role DESC, u.first_name, u.last_name
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Statistiques globales
$stats_query = "
    SELECT 
        COUNT(*) as total_employees,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_employees,
        SUM(CASE WHEN role = 'CDD' THEN 1 ELSE 0 END) as cdd_count,
        SUM(CASE WHEN role = 'CDI' THEN 1 ELSE 0 END) as cdi_count,
        SUM(CASE WHEN role = 'Responsable' THEN 1 ELSE 0 END) as responsable_count,
        SUM(CASE WHEN role = 'Patron' THEN 1 ELSE 0 END) as patron_count
    FROM users
";
$stmt = $db->prepare($stats_query);
$stmt->execute();
$stats = $stmt->fetch();

$page_title = 'Gestion des Employés';
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
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-users-cog me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                <i class="fas fa-plus me-1"></i>
                                Nouvel Employé
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
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
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['total_employees']); ?></h5>
                                <p class="card-text text-muted">Total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-user-check fa-2x text-success mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['active_employees']); ?></h5>
                                <p class="card-text text-muted">Actifs</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-user-clock fa-2x text-warning mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['cdd_count']); ?></h5>
                                <p class="card-text text-muted">CDD</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-user-tie fa-2x text-info mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['cdi_count']); ?></h5>
                                <p class="card-text text-muted">CDI</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-user-shield fa-2x text-secondary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['responsable_count']); ?></h5>
                                <p class="card-text text-muted">Responsables</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-crown fa-2x text-danger mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['patron_count']); ?></h5>
                                <p class="card-text text-muted">Patrons</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recherche et filtres -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Rechercher</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Nom, prénom, username..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="role" class="form-label">Rôle</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="">Tous les rôles</option>
                                    <option value="CDD" <?php echo $role_filter === 'CDD' ? 'selected' : ''; ?>>CDD</option>
                                    <option value="CDI" <?php echo $role_filter === 'CDI' ? 'selected' : ''; ?>>CDI</option>
                                    <option value="Responsable" <?php echo $role_filter === 'Responsable' ? 'selected' : ''; ?>>Responsable</option>
                                    <option value="Patron" <?php echo $role_filter === 'Patron' ? 'selected' : ''; ?>>Patron</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Statut</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tous les statuts</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actifs</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactifs</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        Rechercher
                                    </button>
                                    <a href="employees.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-times me-1"></i>
                                        Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Liste des employés -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            Employés (<?php echo number_format($total_records); ?> résultats)
                        </h5>
                        <small class="text-muted">
                            Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($employees)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Employé</th>
                                            <th>Username</th>
                                            <th>Rôle</th>
                                            <th>Contact</th>
                                            <th>Statut</th>
                                            <th>Ménages</th>
                                            <th>Ventes</th>
                                            <th>Gains</th>
                                            <th>Inscription</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $employee): ?>
                                            <tr class="<?php echo $employee['status'] === 'suspended' ? 'table-secondary' : ''; ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle me-2">
                                                            <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                                                            <?php if ($employee['id'] === $user['id']): ?>
                                                                <span class="badge bg-info ms-1">Vous</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($employee['username']); ?></code>
                                                </td>
                                                <td>
                                                    <?php
                                                    $role_colors = [
                                                        'CDD' => 'warning',
                                                        'CDI' => 'info',
                                                        'Responsable' => 'secondary',
                                                        'Patron' => 'danger'
                                                    ];
                                                    $color = $role_colors[$employee['role']] ?? 'primary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo htmlspecialchars($employee['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                        <?php if ($employee['email']): ?>
                                            <div><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($employee['email']); ?></div>
                                        <?php else: ?>
                                            <span class="text-muted">Aucun contact</span>
                                        <?php endif; ?>
                                    </td>
                                                <td>
                                                    <?php if ($employee['status'] === 'active'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check me-1"></i>
                                                            Actif
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-times me-1"></i>
                                                            Inactif
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="text-center">
                                                        <div class="fw-bold"><?php echo number_format($employee['total_cleanings']); ?></div>
                                                        <small class="text-muted"><?php echo number_format($employee['total_cleaning_sessions']); ?> sessions</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-center">
                                                        <div class="fw-bold"><?php echo number_format($employee['total_sales']); ?></div>
                                                        <small class="text-muted">ventes</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-center">
                                                        <div class="text-success fw-bold"><?php echo number_format($employee['total_cleaning_salary'] + $employee['total_commission'], 2); ?>$</div>
                                                        <small class="text-muted">
                                                            <?php echo number_format($employee['total_cleaning_salary'], 2); ?>$ + <?php echo number_format($employee['total_commission'], 2); ?>$
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo formatDateTime($employee['created_at'], 'd/m/Y'); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="editEmployee(<?php echo htmlspecialchars(json_encode($employee)); ?>)" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editEmployeeModal">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($employee['id'] !== $user['id']): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Confirmer le changement de statut ?')">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                <button type="submit" class="btn btn-outline-<?php echo $employee['status'] === 'active' ? 'danger' : 'success'; ?>">
                                                                    <i class="fas fa-<?php echo $employee['status'] === 'active' ? 'user-slash' : 'user-check'; ?>"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
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
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucun employé trouvé</h5>
                                <p class="text-muted">Aucun employé ne correspond aux critères de recherche.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                    <i class="fas fa-plus me-2"></i>
                                    Ajouter un employé
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal ajout employé -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-plus me-2"></i>
                            Nouvel Employé
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="add_employee">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_username" class="form-label">Nom d'utilisateur *</label>
                                    <input type="text" class="form-control" id="add_username" name="username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_password" class="form-label">Mot de passe *</label>
                                    <input type="password" class="form-control" id="add_password" name="password" required minlength="6">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_first_name" class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" id="add_first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_last_name" class="form-label">Nom *</label>
                                    <input type="text" class="form-control" id="add_last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_role" class="form-label">Rôle *</label>
                                    <select class="form-select" id="add_role" name="role" required>
                                        <option value="">Sélectionner un rôle</option>
                                        <option value="CDD">CDD</option>
                                        <option value="CDI">CDI</option>
                                        <option value="Responsable">Responsable</option>
                                        <option value="Patron">Patron</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Phone field removed - column doesn't exist in database -->
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="add_email" name="email">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal modification employé -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-edit me-2"></i>
                            Modifier l'Employé
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="edit_employee">
                        <input type="hidden" name="employee_id" id="edit_employee_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_username" class="form-label">Nom d'utilisateur *</label>
                                    <input type="text" class="form-control" id="edit_username" name="username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_new_password" class="form-label">Nouveau mot de passe</label>
                                    <input type="password" class="form-control" id="edit_new_password" name="new_password" minlength="6">
                                    <div class="form-text">Laisser vide pour conserver le mot de passe actuel</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_first_name" class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_last_name" class="form-label">Nom *</label>
                                    <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_role" class="form-label">Rôle *</label>
                                    <select class="form-select" id="edit_role" name="role" required>
                                        <option value="CDD">CDD</option>
                                        <option value="CDI">CDI</option>
                                        <option value="Responsable">Responsable</option>
                                        <option value="Patron">Patron</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Phone field removed - column doesn't exist in database -->
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Modifier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function editEmployee(employee) {
            document.getElementById('edit_employee_id').value = employee.id;
            document.getElementById('edit_username').value = employee.username;
            document.getElementById('edit_first_name').value = employee.first_name;
            document.getElementById('edit_last_name').value = employee.last_name;
            document.getElementById('edit_role').value = employee.role;
            // Phone field removed - column doesn't exist in database
            document.getElementById('edit_email').value = employee.email || '';
            document.getElementById('edit_new_password').value = '';
        }
    </script>
    
    <style>
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8B4513, #D2691E);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
    </style>
</body>
</html>