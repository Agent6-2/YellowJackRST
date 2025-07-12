<?php
/**
 * Historique des ventes - Panel Employé Le Yellowjack
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

// Paramètres de pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Filtres
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$customer_id = intval($_GET['customer_id'] ?? 0);
$min_amount = floatval($_GET['min_amount'] ?? 0);
$show_all = $_GET['show_all'] ?? '0'; // Pour les responsables/patrons

// Construction de la requête
$where_conditions = [];
$params = [];

// Restriction par utilisateur (sauf pour les responsables/patrons)
if (!$auth->canManageEmployees() || $show_all !== '1') {
    $where_conditions[] = 's.user_id = ?';
    $params[] = $user['id'];
}

if ($date_from) {
    $where_conditions[] = 'DATE(s.created_at) >= ?';
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = 'DATE(s.created_at) <= ?';
    $params[] = $date_to;
}

if ($customer_id > 0) {
    $where_conditions[] = 's.customer_id = ?';
    $params[] = $customer_id;
}

if ($min_amount > 0) {
    $where_conditions[] = 's.final_amount >= ?';
    $params[] = $min_amount;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Compter le total
$count_query = "
    SELECT COUNT(*) 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    LEFT JOIN users u ON s.user_id = u.id 
    $where_clause
";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Récupérer les ventes
$query = "
    SELECT 
        s.*,
        c.name as customer_name,
        c.is_loyal as customer_loyal,
        u.first_name,
        u.last_name
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    LEFT JOIN users u ON s.user_id = u.id 
    $where_clause
    ORDER BY s.created_at DESC 
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Statistiques
$stats_query = "
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(s.final_amount), 0) as total_revenue,
        COALESCE(SUM(s.employee_commission), 0) as total_commission,
        COALESCE(AVG(s.final_amount), 0) as avg_sale_amount
    FROM sales s 
    $where_clause
";
$stmt = $db->prepare($stats_query);
$stmt->execute($params);
$stats = $stmt->fetch();

// Récupérer la liste des clients pour le filtre
$stmt = $db->prepare("SELECT id, name FROM customers ORDER BY name");
$stmt->execute();
$customers = $stmt->fetchAll();

$page_title = 'Historique des Ventes';
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
                        <i class="fas fa-receipt me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="cashier.php" class="btn btn-primary">
                                <i class="fas fa-cash-register me-1"></i>
                                Nouvelle Vente
                            </a>
                            <a href="customers.php" class="btn btn-outline-info">
                                <i class="fas fa-users me-1"></i>
                                Clients
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['total_sales']); ?></h5>
                                <p class="card-text text-muted">Ventes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['total_revenue'], 2); ?>$</h5>
                                <p class="card-text text-muted">Chiffre d'affaires</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-percentage fa-2x text-warning mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['total_commission'], 2); ?>$</h5>
                                <p class="card-text text-muted">Commissions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['avg_sale_amount'], 2); ?>$</h5>
                                <p class="card-text text-muted">Panier moyen</p>
                            </div>
                        </div>
                    </div>
                </div>
                
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
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="customer_id" class="form-label">Client</label>
                                <select class="form-select" id="customer_id" name="customer_id">
                                    <option value="0">Tous les clients</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>" <?php echo $customer_id === $customer['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($customer['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="min_amount" class="form-label">Montant min.</label>
                                <input type="number" class="form-control" id="min_amount" name="min_amount" step="0.01" value="<?php echo $min_amount; ?>">
                            </div>
                            <?php if ($auth->canManageEmployees()): ?>
                            <div class="col-md-2">
                                <label for="show_all" class="form-label">Affichage</label>
                                <select class="form-select" id="show_all" name="show_all">
                                    <option value="0" <?php echo $show_all === '0' ? 'selected' : ''; ?>>Mes ventes</option>
                                    <option value="1" <?php echo $show_all === '1' ? 'selected' : ''; ?>>Toutes les ventes</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        Filtrer
                                    </button>
                                    <a href="sales_history.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-times me-1"></i>
                                        Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tableau des ventes -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            Ventes (<?php echo number_format($total_records); ?> résultats)
                        </h5>
                        <small class="text-muted">
                            Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($sales)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ticket #</th>
                                            <th>Date</th>
                                            <?php if ($auth->canManageEmployees() && $show_all === '1'): ?>
                                                <th>Vendeur</th>
                                            <?php endif; ?>
                                            <th>Client</th>
                                            <th>Montant</th>
                                            <th>Réduction</th>
                                            <th>Total</th>
                                            <th>Commission</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sales as $sale): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-primary">#<?php echo $sale['id']; ?></span>
                                                </td>
                                                <td><?php echo formatDateTime($sale['created_at']); ?></td>
                                                <?php if ($auth->canManageEmployees() && $show_all === '1'): ?>
                                                    <td>
                                                        <?php echo htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']); ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td>
                                                    <?php if ($sale['customer_name']): ?>
                                                        <?php echo htmlspecialchars($sale['customer_name']); ?>
                                                        <?php if ($sale['customer_loyal']): ?>
                                                            <i class="fas fa-star text-warning ms-1" title="Client fidèle"></i>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Client anonyme</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($sale['total_amount'], 2); ?>$</td>
                                                <td>
                                                    <?php if ($sale['discount_amount'] > 0): ?>
                                                        <span class="text-success">-<?php echo number_format($sale['discount_amount'], 2); ?>$</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-success"><?php echo number_format($sale['final_amount'], 2); ?>$</span>
                                                </td>
                                                <td>
                                                    <span class="text-warning fw-bold"><?php echo number_format($sale['employee_commission'], 2); ?>$</span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                                            onclick="viewSaleDetails(<?php echo $sale['id']; ?>)" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#saleDetailsModal">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
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
                                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucune vente trouvée</h5>
                                <p class="text-muted">Aucune vente ne correspond aux critères sélectionnés.</p>
                                <a href="cashier.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>
                                    Nouvelle vente
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal détails de vente -->
    <div class="modal fade" id="saleDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt me-2"></i>
                        Détails de la vente
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="saleDetailsContent">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">Chargement...</p>
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
    function viewSaleDetails(saleId) {
        const content = document.getElementById('saleDetailsContent');
        
        // Afficher le loader
        content.innerHTML = `
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p class="mt-2">Chargement des détails...</p>
            </div>
        `;
        
        // Récupérer les détails via AJAX
        fetch(`get_sale_details.php?id=${saleId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur lors du chargement');
                }
                return response.json();
            })
            .then(data => {
                displaySaleDetails(data);
            })
            .catch(error => {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Erreur lors du chargement des détails: ${error.message}
                    </div>
                `;
            });
    }
    
    function displaySaleDetails(data) {
        const { sale, items, discount_details } = data;
        const content = document.getElementById('saleDetailsContent');
        
        let itemsHtml = '';
        if (items && items.length > 0) {
            itemsHtml = items.map(item => `
                <tr>
                    <td>${item.product_name || 'Produit supprimé'}</td>
                    <td class="text-center">${item.quantity}</td>
                    <td class="text-end">${parseFloat(item.unit_price).toFixed(2)}$</td>
                    <td class="text-end fw-bold">${parseFloat(item.total_price).toFixed(2)}$</td>
                </tr>
            `).join('');
        } else {
            itemsHtml = '<tr><td colspan="4" class="text-center text-muted">Aucun article trouvé</td></tr>';
        }
        
        let discountHtml = '';
        if (discount_details.applied_discount > 0) {
            discountHtml = `
                <div class="alert alert-info">
                    <h6><i class="fas fa-percentage me-2"></i>Réduction appliquée</h6>
                    <p class="mb-1"><strong>Type:</strong> ${discount_details.discount_type}</p>
                    <p class="mb-1"><strong>Pourcentage:</strong> ${discount_details.applied_discount}%</p>
                    <p class="mb-0"><strong>Montant:</strong> ${parseFloat(sale.discount_amount).toFixed(2)}$</p>
                    ${discount_details.loyal_discount > 0 && discount_details.company_discount > 0 ? 
                        `<small class="text-muted">Note: Client fidèle (${discount_details.loyal_discount}%) et entreprise (${discount_details.company_discount}%) - La réduction la plus élevée a été appliquée.</small>` : ''}
                </div>
            `;
        }
        
        content.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-info-circle me-2"></i>Informations générales</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Ticket #:</strong></td><td>#${sale.id}</td></tr>
                        <tr><td><strong>Date:</strong></td><td>${new Date(sale.created_at).toLocaleString('fr-FR')}</td></tr>
                        <tr><td><strong>Vendeur:</strong></td><td>${sale.first_name} ${sale.last_name}</td></tr>
                        <tr><td><strong>Client:</strong></td><td>${sale.customer_name || 'Client anonyme'}${sale.customer_loyal ? ' <i class="fas fa-star text-warning" title="Client fidèle"></i>' : ''}</td></tr>
                        ${sale.company_name ? `<tr><td><strong>Entreprise:</strong></td><td>${sale.company_name}</td></tr>` : ''}
                    </table>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-calculator me-2"></i>Résumé financier</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Sous-total:</strong></td><td class="text-end">${parseFloat(sale.total_amount).toFixed(2)}$</td></tr>
                        <tr><td><strong>Réduction:</strong></td><td class="text-end text-success">-${parseFloat(sale.discount_amount).toFixed(2)}$</td></tr>
                        <tr class="table-success"><td><strong>Total final:</strong></td><td class="text-end fw-bold">${parseFloat(sale.final_amount).toFixed(2)}$</td></tr>
                        <tr class="table-warning"><td><strong>Commission employé:</strong></td><td class="text-end fw-bold">${parseFloat(sale.employee_commission).toFixed(2)}$</td></tr>
                    </table>
                </div>
            </div>
            
            ${discountHtml}
            
            <h6><i class="fas fa-shopping-cart me-2"></i>Articles vendus</h6>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th class="text-center">Quantité</th>
                            <th class="text-end">Prix unitaire</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                </table>
            </div>
        `;
    }
    </script>

</body>
</html>