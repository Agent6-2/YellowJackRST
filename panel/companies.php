<?php
/**
 * Gestion des entreprises - Panel Employé Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../includes/auth.php';
require_once '../config/database.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePermission('manager'); // Seuls les managers peuvent gérer les entreprises

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

$message = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals(generateCSRFToken(), $_POST['csrf_token'])) {
        $error = 'Token CSRF invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_company':
                $name = trim($_POST['name'] ?? '');
                $siret = trim($_POST['siret'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $contact_person = trim($_POST['contact_person'] ?? '');
                $business_discount = floatval($_POST['business_discount'] ?? 0);
                
                if (empty($name)) {
                    $error = 'Le nom de l\'entreprise est obligatoire.';
                } else {
                    // Vérifier si l'entreprise existe déjà
                    $stmt = $db->prepare("SELECT id FROM companies WHERE name = ?");
                    $stmt->execute([$name]);
                    
                    if ($stmt->fetch()) {
                        $error = 'Une entreprise avec ce nom existe déjà.';
                    } else {
                        // Ajouter l'entreprise
                        $stmt = $db->prepare("
                            INSERT INTO companies (name, siret, address, phone, email, contact_person, business_discount, is_active, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                        ");
                        
                        if ($stmt->execute([$name, $siret, $address, $phone, $email, $contact_person, $business_discount])) {
                            $message = 'Entreprise ajoutée avec succès.';
                        } else {
                            $error = 'Erreur lors de l\'ajout de l\'entreprise.';
                        }
                    }
                }
                break;
                
            case 'edit_company':
                $company_id = intval($_POST['company_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $siret = trim($_POST['siret'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $contact_person = trim($_POST['contact_person'] ?? '');
                $business_discount = floatval($_POST['business_discount'] ?? 0);
                
                if (empty($name)) {
                    $error = 'Le nom de l\'entreprise est obligatoire.';
                } else {
                    // Vérifier si une autre entreprise a le même nom
                    $stmt = $db->prepare("SELECT id FROM companies WHERE name = ? AND id != ?");
                    $stmt->execute([$name, $company_id]);
                    
                    if ($stmt->fetch()) {
                        $error = 'Une autre entreprise avec ce nom existe déjà.';
                    } else {
                        // Modifier l'entreprise
                        $stmt = $db->prepare("
                            UPDATE companies 
                            SET name = ?, siret = ?, address = ?, phone = ?, email = ?, contact_person = ?, business_discount = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        
                        if ($stmt->execute([$name, $siret, $address, $phone, $email, $contact_person, $business_discount, $company_id])) {
                            $message = 'Entreprise modifiée avec succès.';
                        } else {
                            $error = 'Erreur lors de la modification de l\'entreprise.';
                        }
                    }
                }
                break;
                
            case 'toggle_status':
                $company_id = intval($_POST['company_id'] ?? 0);
                
                $stmt = $db->prepare("UPDATE companies SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$company_id])) {
                    $message = 'Statut de l\'entreprise modifié avec succès.';
                } else {
                    $error = 'Erreur lors de la modification du statut.';
                }
                break;
        }
    }
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Recherche et filtres
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = '(name LIKE ? OR siret LIKE ? OR contact_person LIKE ? OR email LIKE ?)';
    $search_param = '%' . $search . '%';
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter === 'active') {
    $where_conditions[] = 'is_active = 1';
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = 'is_active = 0';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Compter le total
$count_query = "SELECT COUNT(*) FROM companies $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Récupérer les entreprises
$query = "
    SELECT 
        c.*,
        COUNT(cust.id) as customer_count
    FROM companies c
    LEFT JOIN customers cust ON c.id = cust.company_id
    $where_clause
    GROUP BY c.id
    ORDER BY c.name
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$companies = $stmt->fetchAll();

// Statistiques globales
$stats_query = "
    SELECT 
        COUNT(*) as total_companies,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_companies,
        AVG(business_discount) as avg_discount
    FROM companies
";
$stats = $db->query($stats_query)->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Entreprises - Le Yellowjack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/panel.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-building me-2"></i>Gestion des Entreprises</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                            <i class="fas fa-plus me-1"></i>Nouvelle Entreprise
                        </button>
                        <a href="customers.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-users me-1"></i>Retour aux Clients
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-primary"><?php echo $stats['total_companies']; ?></h5>
                                <p class="card-text">Total Entreprises</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-success"><?php echo $stats['active_companies']; ?></h5>
                                <p class="card-text">Entreprises Actives</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-info"><?php echo number_format($stats['avg_discount'], 1); ?>%</h5>
                                <p class="card-text">Réduction Moyenne</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recherche et filtres -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Rechercher</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Nom, SIRET, contact, email...">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Statut</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tous les statuts</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actives</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactives</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-primary me-2">
                                    <i class="fas fa-search"></i> Rechercher
                                </button>
                                <a href="companies.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Liste des entreprises -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Liste des Entreprises</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($companies) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Entreprise</th>
                                            <th>Contact</th>
                                            <th>SIRET</th>
                                            <th>Réduction</th>
                                            <th>Clients</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($companies as $company): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($company['name']); ?></strong>
                                                        <?php if (!empty($company['address'])): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($company['address']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($company['contact_person'])): ?>
                                                        <div><strong><?php echo htmlspecialchars($company['contact_person']); ?></strong></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($company['phone'])): ?>
                                                        <div><small><?php echo htmlspecialchars($company['phone']); ?></small></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($company['email'])): ?>
                                                        <div><small><?php echo htmlspecialchars($company['email']); ?></small></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($company['siret'])): ?>
                                                        <code><?php echo htmlspecialchars($company['siret']); ?></code>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo number_format($company['business_discount'], 1); ?>%</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $company['customer_count']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($company['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="editCompany(<?php echo htmlspecialchars(json_encode($company)); ?>)" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editCompanyModal">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Confirmer le changement de statut ?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-warning">
                                                                <i class="fas fa-toggle-<?php echo $company['is_active'] ? 'on' : 'off'; ?>"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                <h5>Aucune entreprise trouvée</h5>
                                <p class="text-muted">Commencez par ajouter votre première entreprise.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modal Ajouter Entreprise -->
    <div class="modal fade" id="addCompanyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une Entreprise</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="add_company">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="add_name" class="form-label">Nom de l'entreprise *</label>
                                    <input type="text" class="form-control" id="add_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="add_business_discount" class="form-label">Réduction (%)</label>
                                    <input type="number" class="form-control" id="add_business_discount" name="business_discount" 
                                           min="0" max="100" step="0.1" value="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_siret" class="form-label">SIRET</label>
                            <input type="text" class="form-control" id="add_siret" name="siret" maxlength="14">
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_address" class="form-label">Adresse</label>
                            <textarea class="form-control" id="add_address" name="address" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_contact_person" class="form-label">Personne de contact</label>
                                    <input type="text" class="form-control" id="add_contact_person" name="contact_person">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_phone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="add_phone" name="phone">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="add_email" name="email">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Modifier Entreprise -->
    <div class="modal fade" id="editCompanyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier l'Entreprise</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="edit_company">
                        <input type="hidden" name="company_id" id="edit_company_id">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="edit_name" class="form-label">Nom de l'entreprise *</label>
                                    <input type="text" class="form-control" id="edit_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_business_discount" class="form-label">Réduction (%)</label>
                                    <input type="number" class="form-control" id="edit_business_discount" name="business_discount" 
                                           min="0" max="100" step="0.1">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_siret" class="form-label">SIRET</label>
                            <input type="text" class="form-control" id="edit_siret" name="siret" maxlength="14">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_address" class="form-label">Adresse</label>
                            <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_contact_person" class="form-label">Personne de contact</label>
                                    <input type="text" class="form-control" id="edit_contact_person" name="contact_person">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_phone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="edit_phone" name="phone">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Modifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCompany(company) {
            document.getElementById('edit_company_id').value = company.id;
            document.getElementById('edit_name').value = company.name;
            document.getElementById('edit_siret').value = company.siret || '';
            document.getElementById('edit_address').value = company.address || '';
            document.getElementById('edit_contact_person').value = company.contact_person || '';
            document.getElementById('edit_phone').value = company.phone || '';
            document.getElementById('edit_email').value = company.email || '';
            document.getElementById('edit_business_discount').value = company.business_discount || 0;
            
            new bootstrap.Modal(document.getElementById('editCompanyModal')).show();
        }
    </script>
</body>
</html>