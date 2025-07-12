<?php
/**
 * Gestion des produits - Panel Employé Le Yellowjack
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

// Vérifier s'il y a un message de succès en session
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'])) {
        $error = 'Token de sécurité invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_product':
                $category_id = intval($_POST['category_id']);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $supplier_price = floatval($_POST['supplier_price'] ?? 0);
                $selling_price = floatval($_POST['selling_price'] ?? 0);
                $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
                $min_stock_alert = intval($_POST['min_stock_alert'] ?? 5);
                
                if (empty($name) || $category_id <= 0 || $selling_price <= 0) {
                    $error = 'Tous les champs obligatoires doivent être remplis correctement.';
                } else {
                    try {
                        // Vérifier si le produit existe déjà
                        $stmt = $db->prepare("SELECT id FROM products WHERE name = ?");
                        $stmt->execute([$name]);
                        if ($stmt->fetch()) {
                            $error = 'Un produit avec ce nom existe déjà.';
                        } else {
                            $stmt = $db->prepare("
                                INSERT INTO products (category_id, name, description, supplier_price, selling_price, stock_quantity, min_stock_alert, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$category_id, $name, $description, $supplier_price, $selling_price, $stock_quantity, $min_stock_alert, getCurrentDateTime()]);
                            $_SESSION['success_message'] = 'Produit ajouté avec succès !';
                            header('Location: products.php');
                            exit;
                        }
                    } catch (Exception $e) {
                        $error = 'Erreur lors de l\'ajout du produit.';
                    }
                }
                break;
                
            case 'edit_product':
                $product_id = intval($_POST['product_id']);
                $category_id = intval($_POST['category_id']);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $supplier_price = floatval($_POST['supplier_price'] ?? 0);
                $selling_price = floatval($_POST['selling_price'] ?? 0);
                $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
                $min_stock_alert = intval($_POST['min_stock_alert'] ?? 5);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name) || $category_id <= 0 || $selling_price <= 0) {
                    $error = 'Tous les champs obligatoires doivent être remplis correctement.';
                } else {
                    try {
                        // Vérifier si un autre produit a le même nom
                        $stmt = $db->prepare("SELECT id FROM products WHERE name = ? AND id != ?");
                        $stmt->execute([$name, $product_id]);
                        if ($stmt->fetch()) {
                            $error = 'Un autre produit avec ce nom existe déjà.';
                        } else {
                            $stmt = $db->prepare("
                                UPDATE products 
                                SET category_id = ?, name = ?, description = ?, supplier_price = ?, selling_price = ?, stock_quantity = ?, min_stock_alert = ?, is_active = ?
                                WHERE id = ?
                            ");
                            $result = $stmt->execute([$category_id, $name, $description, $supplier_price, $selling_price, $stock_quantity, $min_stock_alert, $is_active, $product_id]);
                            if ($result && $stmt->rowCount() > 0) {
                                $_SESSION['success_message'] = 'Produit modifié avec succès !';
                                header('Location: products.php');
                                exit;
                            } else {
                                $error = 'Aucune modification effectuée. Vérifiez que le produit existe.';
                            }
                        }
                    } catch (Exception $e) {
                        $error = 'Erreur lors de la modification du produit.';
                    }
                }
                break;
                
            case 'delete_product':
                $product_id = intval($_POST['product_id']);
                
                if ($product_id <= 0) {
                    $error = 'ID de produit invalide.';
                } else {
                    try {
                        // Vérifier si le produit est utilisé dans des ventes
                        $stmt = $db->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id = ?");
                        $stmt->execute([$product_id]);
                        $sales_count = $stmt->fetchColumn();
                        
                        if ($sales_count > 0) {
                            // Ne pas supprimer, juste désactiver
                            $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
                            $stmt->execute([$product_id]);
                            $_SESSION['success_message'] = 'Produit désactivé avec succès (utilisé dans des ventes).';
                        } else {
                            // Supprimer complètement
                            $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                            $stmt->execute([$product_id]);
                            $_SESSION['success_message'] = 'Produit supprimé avec succès !';
                        }
                        
                        header('Location: products.php');
                        exit;
                    } catch (Exception $e) {
                        $error = 'Erreur lors de la suppression du produit.';
                    }
                }
                break;
                
            case 'adjust_stock':
                $product_id = intval($_POST['product_id']);
                $adjustment = intval($_POST['adjustment']);
                $reason = trim($_POST['reason'] ?? '');
                
                if ($adjustment == 0) {
                    $error = 'L\'ajustement ne peut pas être nul.';
                } elseif (empty($reason)) {
                    $error = 'La raison de l\'ajustement est obligatoire.';
                } else {
                    try {
                        // Récupérer le stock actuel
                        $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                        $stmt->execute([$product_id]);
                        $current_stock = $stmt->fetchColumn();
                        
                        if ($current_stock === false) {
                            $error = 'Produit introuvable.';
                        } else {
                            $new_stock = max(0, $current_stock + $adjustment);
                            
                            $stmt = $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                            $stmt->execute([$new_stock, $product_id]);
                            
                            $_SESSION['success_message'] = 'Stock ajusté avec succès !';
                            header('Location: products.php');
                            exit;
                        }
                    } catch (Exception $e) {
                        $error = 'Erreur lors de l\'ajustement du stock.';
                    }
                }
                break;
        }
    }
}

// Paramètres de pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Paramètres de recherche et filtrage
$search = trim($_GET['search'] ?? '');
$category_filter = intval($_GET['category'] ?? 0);
$stock_filter = $_GET['stock'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Construction de la requête
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if ($stock_filter === 'low') {
    $where_conditions[] = "p.stock_quantity <= p.min_stock_alert AND p.stock_quantity > 0";
} elseif ($stock_filter === 'out') {
    $where_conditions[] = "p.stock_quantity = 0";
}

if ($status_filter === 'active') {
    $where_conditions[] = "p.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "p.is_active = 0";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Compter le total des enregistrements
$count_query = "
    SELECT COUNT(*) 
    FROM products p 
    LEFT JOIN product_categories pc ON p.category_id = pc.id 
    $where_clause
";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Récupérer les produits avec statistiques de vente
$query = "
    SELECT 
        p.*,
        pc.name as category_name,
        COALESCE(SUM(si.quantity), 0) as total_sold,
        COALESCE(SUM(si.total_price), 0) as total_revenue
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    LEFT JOIN sale_items si ON p.id = si.product_id
    $where_clause
    GROUP BY p.id
    ORDER BY p.name ASC
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories pour les formulaires
$categories_query = "SELECT * FROM product_categories ORDER BY name ASC";
$categories = $db->query($categories_query)->fetchAll(PDO::FETCH_ASSOC);

// Statistiques générales
$stats_query = "
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN stock_quantity <= min_stock_alert AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_products,
        SUM(stock_quantity * supplier_price) as total_inventory_value
    FROM products
";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - Le Yellowjack</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- CSS personnalisé -->
    <link href="../assets/css/panel.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Contenu principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Header -->
                <?php include 'includes/header.php'; ?>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-box me-2"></i>
                        Gestion des Produits
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus me-1"></i>
                            Ajouter un produit
                        </button>
                    </div>
                </div>
                
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-box fa-2x text-primary mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['total_products']); ?></h5>
                                <p class="card-text text-muted">Total Produits</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['active_products']); ?></h5>
                                <p class="card-text text-muted">Produits Actifs</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['low_stock']); ?></h5>
                                <p class="card-text text-muted">Stock Faible</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                                <h5 class="card-title"><?php echo number_format($stats['out_of_stock']); ?></h5>
                                <p class="card-text text-muted">Rupture Stock</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtres et recherche -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Filtres et Recherche
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Recherche</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Nom ou description...">
                            </div>
                            <div class="col-md-2">
                                <label for="category" class="form-label">Catégorie</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">Toutes</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="stock" class="form-label">Stock</label>
                                <select class="form-select" id="stock" name="stock">
                                    <option value="">Tous</option>
                                    <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Stock faible</option>
                                    <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Rupture</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Statut</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tous</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actifs</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactifs</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-search me-1"></i>
                                        Rechercher
                                    </button>
                                    <a href="products.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-times me-1"></i>
                                        Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Liste des produits -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            Produits (<?php echo number_format($total_records); ?> résultats)
                        </h5>
                        <small class="text-muted">
                            Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($products)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Produit</th>
                                            <th>Catégorie</th>
                                            <th>Prix</th>
                                            <th>Marge</th>
                                            <th>Stock</th>
                                            <th>Vendus</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <?php
                                            $margin = $product['selling_price'] - $product['supplier_price'];
                                            $margin_percent = $product['supplier_price'] > 0 ? ($margin / $product['supplier_price']) * 100 : 0;
                                            $stock_status = '';
                                            $stock_class = '';
                                            
                                            if ($product['stock_quantity'] == 0) {
                                                $stock_status = 'Rupture';
                                                $stock_class = 'danger';
                                            } elseif ($product['stock_quantity'] <= $product['min_stock_alert']) {
                                                $stock_status = 'Stock faible';
                                                $stock_class = 'warning';
                                            } else {
                                                $stock_status = 'En stock';
                                                $stock_class = 'success';
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <?php if ($product['description']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($product['description']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong class="text-success"><?php echo number_format($product['selling_price'], 2); ?>$</strong>
                                                        <br><small class="text-muted">Achat: <?php echo number_format($product['supplier_price'], 2); ?>$</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="fw-bold <?php echo $margin > 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo number_format($margin, 2); ?>$
                                                        </span>
                                                        <br><small class="text-muted">(<?php echo number_format($margin_percent, 1); ?>%)</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-center">
                                                        <span class="fw-bold"><?php echo number_format($product['stock_quantity']); ?></span>
                                                        <br><small class="text-muted">Min: <?php echo $product['min_stock_alert']; ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-center">
                                                        <span class="badge bg-info"><?php echo number_format($product['total_sold']); ?></span>
                                                        <br><small class="text-muted"><?php echo number_format($product['total_revenue'], 2); ?>$</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-center">
                                                        <span class="badge bg-<?php echo $stock_class; ?>">
                                                            <?php echo $stock_status; ?>
                                                        </span>
                                                        <br>
                                                        <span class="badge bg-<?php echo $product['is_active'] ? 'success' : 'secondary'; ?>">
                                                            <?php echo $product['is_active'] ? 'Actif' : 'Inactif'; ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group-vertical btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                                onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-info btn-sm" 
                                                                onclick="adjustStock(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['stock_quantity']; ?>)">
                                                            <i class="fas fa-boxes"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                                onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Navigation des pages">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&stock=<?php echo $stock_filter; ?>&status=<?php echo $status_filter; ?>">
                                                    Précédent
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&stock=<?php echo $stock_filter; ?>&status=<?php echo $status_filter; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&stock=<?php echo $stock_filter; ?>&status=<?php echo $status_filter; ?>">
                                                    Suivant
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-box fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucun produit trouvé</h5>
                                <p class="text-muted">Aucun produit ne correspond à vos critères de recherche.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal Ajouter Produit -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        Ajouter un Produit
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_product">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_name" class="form-label">Nom du produit *</label>
                                    <input type="text" class="form-control" id="add_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_category_id" class="form-label">Catégorie *</label>
                                    <select class="form-select" id="add_category_id" name="category_id" required>
                                        <option value="">Sélectionner une catégorie</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_description" class="form-label">Description</label>
                            <textarea class="form-control" id="add_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_supplier_price" class="form-label">Prix d'achat ($)</label>
                                    <input type="number" class="form-control" id="add_supplier_price" name="supplier_price" 
                                           step="0.01" min="0" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_selling_price" class="form-label">Prix de vente ($) *</label>
                                    <input type="number" class="form-control" id="add_selling_price" name="selling_price" 
                                           step="0.01" min="0.01" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_stock_quantity" class="form-label">Quantité en stock</label>
                                    <input type="number" class="form-control" id="add_stock_quantity" name="stock_quantity" 
                                           min="0" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_min_stock_alert" class="form-label">Seuil d'alerte stock</label>
                                    <input type="number" class="form-control" id="add_min_stock_alert" name="min_stock_alert" 
                                           min="0" value="5">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Modifier Produit -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Modifier le Produit
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_product">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                        <input type="hidden" name="product_id" id="edit_product_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_name" class="form-label">Nom du produit *</label>
                                    <input type="text" class="form-control" id="edit_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_category_id" class="form-label">Catégorie *</label>
                                    <select class="form-select" id="edit_category_id" name="category_id" required>
                                        <option value="">Sélectionner une catégorie</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_supplier_price" class="form-label">Prix d'achat ($)</label>
                                    <input type="number" class="form-control" id="edit_supplier_price" name="supplier_price" 
                                           step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_selling_price" class="form-label">Prix de vente ($) *</label>
                                    <input type="number" class="form-control" id="edit_selling_price" name="selling_price" 
                                           step="0.01" min="0.01" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_stock_quantity" class="form-label">Quantité en stock</label>
                                    <input type="number" class="form-control" id="edit_stock_quantity" name="stock_quantity" 
                                           min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_min_stock_alert" class="form-label">Seuil d'alerte stock</label>
                                    <input type="number" class="form-control" id="edit_min_stock_alert" name="min_stock_alert" 
                                           min="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Produit actif
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Modifier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Ajustement Stock -->
    <div class="modal fade" id="adjustStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-boxes me-2"></i>
                        Ajuster le Stock
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="adjust_stock">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                        <input type="hidden" name="product_id" id="adjust_product_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Produit</label>
                            <p class="form-control-plaintext fw-bold" id="adjust_product_name"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Stock actuel</label>
                            <p class="form-control-plaintext" id="adjust_current_stock"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="adjustment" class="form-label">Ajustement *</label>
                            <input type="number" class="form-control" id="adjustment" name="adjustment" required>
                            <div class="form-text">Nombre positif pour ajouter, négatif pour retirer</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">Raison *</label>
                            <select class="form-select" id="reason" name="reason" required>
                                <option value="">Sélectionner une raison</option>
                                <option value="Réception marchandise">Réception marchandise</option>
                                <option value="Inventaire">Correction inventaire</option>
                                <option value="Produit défectueux">Produit défectueux</option>
                                <option value="Vol/Perte">Vol/Perte</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Ajuster
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Confirmation Suppression -->
    <div class="modal fade" id="deleteProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-trash me-2"></i>
                        Supprimer le Produit
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                        <input type="hidden" name="product_id" id="delete_product_id">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Êtes-vous sûr de vouloir supprimer le produit <strong id="delete_product_name"></strong> ?
                        </div>
                        
                        <p class="text-muted">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Si ce produit a été vendu, il sera désactivé au lieu d'être supprimé.
                            </small>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>
                            Supprimer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function editProduct(product) {
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_category_id').value = product.category_id;
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_supplier_price').value = product.supplier_price;
            document.getElementById('edit_selling_price').value = product.selling_price;
            document.getElementById('edit_stock_quantity').value = product.stock_quantity;
            document.getElementById('edit_min_stock_alert').value = product.min_stock_alert;
            document.getElementById('edit_is_active').checked = product.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('editProductModal')).show();
        }
        
        function adjustStock(productId, productName, currentStock) {
            document.getElementById('adjust_product_id').value = productId;
            document.getElementById('adjust_product_name').textContent = productName;
            document.getElementById('adjust_current_stock').textContent = currentStock + ' unités';
            document.getElementById('adjustment').value = '';
            document.getElementById('reason').value = '';
            
            new bootstrap.Modal(document.getElementById('adjustStockModal')).show();
        }
        
        function deleteProduct(productId, productName) {
            document.getElementById('delete_product_id').value = productId;
            document.getElementById('delete_product_name').textContent = productName;
            
            new bootstrap.Modal(document.getElementById('deleteProductModal')).show();
        }
    </script>
</body>
</html>