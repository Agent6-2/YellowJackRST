<?php
/**
 * Gestion des clients - Panel Employé Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../includes/auth.php';
require_once '../config/database.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePermission('cashier');

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
            case 'add_customer':
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $is_loyal = isset($_POST['is_loyal']) ? 1 : 0;
                $loyalty_discount = floatval($_POST['loyalty_discount'] ?? 0);
                $customer_type = 'Client';
                $company_id = !empty($_POST['company_id']) ? intval($_POST['company_id']) : null;
                
                if (empty($name)) {
                    $error = 'Le nom du client est obligatoire.';
                } else {
                    try {
                        // Vérifier si le client existe déjà
                        $stmt = $db->prepare("SELECT id FROM customers WHERE name = ?");
                        $stmt->execute([$name]);
                        if ($stmt->fetch()) {
                            $error = 'Un client avec ce nom existe déjà.';
                        } else {
                            $stmt = $db->prepare("
                                INSERT INTO customers (name, phone, email, is_loyal, loyalty_discount, customer_type, company_id, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$name, $phone, $email, $is_loyal, $loyalty_discount, $customer_type, $company_id, getCurrentDateTime()]);
                            $message = 'Client ajouté avec succès !';
                        }
                    } catch (Exception $e) {
                        $error = 'Erreur lors de l\'ajout du client.';
                    }
                }
                break;
                
            case 'edit_customer':
                $customer_id = intval($_POST['customer_id']);
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $is_loyal = isset($_POST['is_loyal']) ? 1 : 0;
                $loyalty_discount = floatval($_POST['loyalty_discount'] ?? 0);
                $customer_type = 'Client';
                $company_id = !empty($_POST['company_id']) ? intval($_POST['company_id']) : null;
                
                if (empty($name)) {
                    $error = 'Le nom du client est obligatoire.';
                } else {
                    try {
                        // Vérifier si un autre client a le même nom
                        $stmt = $db->prepare("SELECT id FROM customers WHERE name = ? AND id != ?");
                        $stmt->execute([$name, $customer_id]);
                        if ($stmt->fetch()) {
                            $error = 'Un autre client avec ce nom existe déjà.';
                        } else {
                            $stmt = $db->prepare("
                                UPDATE customers 
                                SET name = ?, phone = ?, email = ?, is_loyal = ?, loyalty_discount = ?, customer_type = ?, company_id = ? 
                                WHERE id = ?
                            ");
                            $stmt->execute([$name, $phone, $email, $is_loyal, $loyalty_discount, $customer_type, $company_id, $customer_id]);
                            $message = 'Client modifié avec succès !';
                        }
                    } catch (Exception $e) {
                        $error = 'Erreur lors de la modification du client.';
                    }
                }
                break;
                
            case 'toggle_loyalty':
                if ($auth->canManageEmployees()) {
                    $customer_id = intval($_POST['customer_id']);
                    try {
                        $stmt = $db->prepare("UPDATE customers SET is_loyal = NOT is_loyal WHERE id = ?");
                        $stmt->execute([$customer_id]);
                        $message = 'Statut de fidélité modifié avec succès !';
                    } catch (Exception $e) {
                        $error = 'Erreur lors de la modification du statut.';
                    }
                } else {
                    $error = 'Permission insuffisante.';
                }
                break;
        }
    }
}

// Paramètres de recherche et pagination
$search = trim($_GET['search'] ?? '');
$loyalty_filter = $_GET['loyalty'] ?? '';
$type_filter = $_GET['type'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Construction de la requête
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR comp.name LIKE ?)';
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($loyalty_filter === 'loyal') {
    $where_conditions[] = 'c.is_loyal = 1';
} elseif ($loyalty_filter === 'regular') {
    $where_conditions[] = 'c.is_loyal = 0';
}

if ($type_filter === 'Client') {
    $where_conditions[] = 'c.customer_type = "Client"';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Compter le total
$count_query = "SELECT COUNT(*) FROM customers c LEFT JOIN companies comp ON c.company_id = comp.id $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Récupérer les clients
$query = "
    SELECT 
        c.*,
        comp.name as company_name,
        comp.business_discount,
        COUNT(s.id) as total_purchases,
        COALESCE(SUM(s.final_amount), 0) as total_spent
    FROM customers c
    LEFT JOIN companies comp ON c.company_id = comp.id
    LEFT JOIN sales s ON c.id = s.customer_id
    $where_clause
    GROUP BY c.id
    ORDER BY c.name
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Récupérer la liste des entreprises pour les formulaires
$companies_query = "SELECT id, name, business_discount FROM companies WHERE is_active = 1 ORDER BY name";
$stmt = $db->prepare($companies_query);
$stmt->execute();
$companies = $stmt->fetchAll();

// Statistiques globales
$stats_query = "
    SELECT 
        COUNT(*) as total_customers,
        SUM(CASE WHEN is_loyal = 1 THEN 1 ELSE 0 END) as loyal_customers,
        SUM(CASE WHEN customer_type = 'Client' THEN 1 ELSE 0 END) as individual_customers,
        SUM(CASE WHEN company_id IS NOT NULL THEN 1 ELSE 0 END) as business_customers,
        AVG(loyalty_discount) as avg_discount
    FROM customers
";
$stmt = $db->prepare($stats_query);
$stmt->execute();
$stats = $stmt->fetch();

$page_title = 'Gestion des Clients';
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
                        <i class="fas fa-users me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                <i class="fas fa-plus me-1"></i>
                                Nouveau Client
                            </button>
                            <a href="cashier.php" class="btn btn-outline-success">
                                <i class="fas fa-cash-register me-1"></i>
                                Caisse
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
                                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['total_customers']); ?></h5>
                                <p class="card-text text-muted">Total clients</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-user fa-2x text-info mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['individual_customers']); ?></h5>
                                <p class="card-text text-muted">Clients particuliers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-building fa-2x text-success mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['business_customers']); ?></h5>
                                <p class="card-text text-muted">Clients entreprises</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-star fa-2x text-warning mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['loyal_customers']); ?></h5>
                                <p class="card-text text-muted">Clients fidèles</p>
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
                                       placeholder="Nom, téléphone, email ou entreprise..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label">Type de client</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">Tous les types</option>
                                    <option value="Client" <?php echo $type_filter === 'Client' ? 'selected' : ''; ?>>Clients particuliers</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="loyalty" class="form-label">Fidélité</label>
                                <select class="form-select" id="loyalty" name="loyalty">
                                    <option value="">Tous les clients</option>
                                    <option value="loyal" <?php echo $loyalty_filter === 'loyal' ? 'selected' : ''; ?>>Clients fidèles</option>
                                    <option value="regular" <?php echo $loyalty_filter === 'regular' ? 'selected' : ''; ?>>Clients réguliers</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        Rechercher
                                    </button>
                                    <a href="customers.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-times me-1"></i>
                                        Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Liste des clients -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            Clients (<?php echo number_format($total_records); ?> résultats)
                        </h5>
                        <small class="text-muted">
                            Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($customers)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Type</th>
                                            <th>Contact</th>
                                            <th>Entreprise</th>
                                            <th>Statut</th>
                                            <th>Réduction</th>
                                            <th>Achats</th>
                                            <th>Total dépensé</th>
                                            <th>Inscription</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customers as $customer): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($customer['name']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <i class="fas fa-user me-1"></i>
                                                        Client
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($customer['phone']): ?>
                                                        <div><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($customer['phone']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($customer['email']): ?>
                                                        <div><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($customer['email']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!$customer['phone'] && !$customer['email']): ?>
                                                        <span class="text-muted">Aucun contact</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($customer['company_name']): ?>
                                                        <span class="text-primary fw-bold">
                                                            <i class="fas fa-building me-1"></i>
                                                            <?php echo htmlspecialchars($customer['company_name']); ?>
                                                        </span>
                                                        <?php if ($customer['business_discount'] > 0): ?>
                                                            <br><small class="text-success">Réduction: <?php echo $customer['business_discount']; ?>%</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($customer['is_loyal']): ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="fas fa-star me-1"></i>
                                                            Fidèle
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Régulier</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $total_discount = 0;
                                                    if ($customer['is_loyal'] && $customer['loyalty_discount'] > 0) {
                                                        $total_discount += $customer['loyalty_discount'];
                                                    }
                                                    if ($customer['business_discount'] > 0) {
                                                        $total_discount += $customer['business_discount'];
                                                    }
                                                    ?>
                                                    <?php if ($total_discount > 0): ?>
                                                        <span class="text-success fw-bold"><?php echo number_format($total_discount, 1); ?>%</span>
                                                        <?php if ($customer['loyalty_discount'] > 0 && $customer['business_discount'] > 0): ?>
                                                            <br><small class="text-muted">(Fidélité + Entreprise)</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $customer['total_purchases']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-success fw-bold"><?php echo number_format($customer['total_spent'], 2); ?>$</span>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo formatDateTime($customer['created_at'], 'd/m/Y'); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editCustomer(<?php echo htmlspecialchars(json_encode($customer)); ?>)" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editCustomerModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                                        <?php if ($auth->canManageEmployees()): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Confirmer le changement de statut ?')">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                <input type="hidden" name="action" value="toggle_loyalty">
                                                                <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                                                <button type="submit" class="btn btn-outline-warning">
                                                                    <i class="fas fa-star"></i>
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
                                <h5 class="text-muted">Aucun client trouvé</h5>
                                <p class="text-muted">Aucun client ne correspond aux critères de recherche.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                    <i class="fas fa-plus me-2"></i>
                                    Ajouter un client
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal ajout client -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-plus me-2"></i>
                            Nouveau Client
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="add_customer">
                        
                        <div class="mb-3">
                            <label for="add_name" class="form-label">Nom *</label>
                            <input type="text" class="form-control" id="add_name" name="name" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_phone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="add_phone" name="phone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="add_email" name="email">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_company_id" class="form-label">Entreprise associée (optionnel)</label>
                            <select class="form-select" id="add_company_id" name="company_id">
                                <option value="">Aucune entreprise</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>">
                                        <?php echo htmlspecialchars($company['name']); ?>
                                        <?php if ($company['business_discount'] > 0): ?>
                                            (<?php echo $company['business_discount']; ?>% de réduction)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Associer ce client à une entreprise pour appliquer automatiquement la réduction entreprise.</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="add_is_loyal" name="is_loyal">
                                        <label class="form-check-label" for="add_is_loyal">
                                            Client fidèle
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_loyalty_discount" class="form-label">Réduction fidélité (%)</label>
                                    <input type="number" class="form-control" id="add_loyalty_discount" name="loyalty_discount" 
                                           min="0" max="100" step="0.1" value="0">
                                </div>
                            </div>
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
    
    <!-- Modal modification client -->
    <div class="modal fade" id="editCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-edit me-2"></i>
                            Modifier le Client
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="edit_customer">
                        <input type="hidden" name="customer_id" id="edit_customer_id">
                        
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Nom *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_phone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="edit_phone" name="phone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="edit_email" name="email">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_company_id" class="form-label">Entreprise associée (optionnel)</label>
                            <select class="form-select" id="edit_company_id" name="company_id">
                                <option value="">Aucune entreprise</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>">
                                        <?php echo htmlspecialchars($company['name']); ?>
                                        <?php if ($company['business_discount'] > 0): ?>
                                            (<?php echo $company['business_discount']; ?>% de réduction)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Associer ce client à une entreprise pour appliquer automatiquement la réduction entreprise.</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_is_loyal" name="is_loyal">
                                        <label class="form-check-label" for="edit_is_loyal">
                                            Client fidèle
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_loyalty_discount" class="form-label">Réduction fidélité (%)</label>
                                    <input type="number" class="form-control" id="edit_loyalty_discount" name="loyalty_discount" 
                                           min="0" max="100" step="0.1">
                                </div>
                            </div>
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
        function editCustomer(customer) {
            document.getElementById('edit_customer_id').value = customer.id;
            document.getElementById('edit_name').value = customer.name;
            document.getElementById('edit_phone').value = customer.phone || '';
            document.getElementById('edit_email').value = customer.email || '';
            document.getElementById('edit_is_loyal').checked = customer.is_loyal == 1;
            document.getElementById('edit_loyalty_discount').value = customer.loyalty_discount || 0;
            document.getElementById('edit_company_id').value = customer.company_id || '';
            
            new bootstrap.Modal(document.getElementById('editCustomerModal')).show();
        }
    </script>
</body>
</html>