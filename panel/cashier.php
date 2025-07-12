<?php
/**
 * Caisse Enregistreuse - Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePermission('cashier');

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

$message = '';
$error = '';

// Traitement de la vente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Token de sécurité invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'process_sale') {
            $customer_id = intval($_POST['customer_id'] ?? 0);
            $items = $_POST['items'] ?? [];
            
            if (empty($items)) {
                $error = 'Aucun produit sélectionné.';
            } else {
                try {
                    // Vérifier qu'il y a une semaine active pour enregistrer la vente
                    $activeWeek = getActiveWeek();
                    if (!$activeWeek) {
                        throw new Exception('Aucune semaine active trouvée. Veuillez contacter un administrateur.');
                    }
                    
                    $db->beginTransaction();
                    
                    // Calculer le total et vérifier le stock
                    $total_amount = 0;
                    $total_profit = 0;
                    $sale_items = [];
                    
                    foreach ($items as $item) {
                        $product_id = intval($item['product_id']);
                        $quantity = intval($item['quantity']);
                        
                        if ($quantity <= 0) continue;
                        
                        // Récupérer les informations du produit
                        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
                        $stmt->execute([$product_id]);
                        $product = $stmt->fetch();
                        
                        if (!$product) {
                            throw new Exception("Produit introuvable: ID $product_id");
                        }
                        
                        if ($product['stock_quantity'] < $quantity) {
                            throw new Exception("Stock insuffisant pour {$product['name']} (disponible: {$product['stock_quantity']}, demandé: $quantity)");
                        }
                        
                        $item_total = $product['selling_price'] * $quantity;
                        $item_profit = ($product['selling_price'] - $product['supplier_price']) * $quantity;
                        $total_amount += $item_total;
                        $total_profit += $item_profit;
                        
                        $sale_items[] = [
                            'product' => $product,
                            'quantity' => $quantity,
                            'unit_price' => $product['selling_price'],
                            'total_price' => $item_total
                        ];
                    }
                    
                    if (empty($sale_items)) {
                        throw new Exception('Aucun produit valide dans la commande.');
                    }
                    
                    // Récupérer les informations du client et de son entreprise
                    $customer = null;
                    $company = null;
                    if ($customer_id > 0) {
                        $stmt = $db->prepare("
                            SELECT c.*, comp.business_discount 
                            FROM customers c 
                            LEFT JOIN companies comp ON c.company_id = comp.id AND comp.is_active = 1
                            WHERE c.id = ?
                        ");
                        $stmt->execute([$customer_id]);
                        $customer = $stmt->fetch();
                    }
                    
                    // Appliquer les réductions (fidélité + entreprise)
                    $discount_amount = 0;
                    $loyalty_discount = 0;
                    $business_discount = 0;
                    
                    if ($customer) {
                        // Réduction fidélité
                        if ($customer['is_loyal']) {
                            $loyalty_discount = $total_amount * ($customer['loyalty_discount'] / 100);
                        }
                        
                        // Réduction entreprise
                        if ($customer['business_discount'] > 0) {
                            $business_discount = $total_amount * ($customer['business_discount'] / 100);
                        }
                        
                        // Prendre la réduction la plus élevée (non cumulable)
                    $discount_amount = max($loyalty_discount, $business_discount);
                    }
                    
                    $final_amount = $total_amount - $discount_amount;
                    // Calcul de la commission selon le rôle de l'utilisateur
        $commission_rate = 0;
        if ($user['role'] === 'CDI' || $user['role'] === 'Responsable' || $user['role'] === 'Patron') {
            $commission_rate = 20; // 20% pour CDI, Responsable et Patron
        } else {
            $commission_rate = 10; // 10% pour les autres rôles (CDD)
        }
        
        $commission = $total_profit * ($commission_rate / 100);
                    
                    // Créer la vente
                    $stmt = $db->prepare("
                        INSERT INTO sales (user_id, customer_id, total_amount, discount_amount, final_amount, employee_commission) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user['id'],
                        $customer_id ?: null,
                        $total_amount,
                        $discount_amount,
                        $final_amount,
                        $commission
                    ]);
                    
                    $sale_id = $db->lastInsertId();
                    
                    // Ajouter les détails de la vente et mettre à jour le stock
                    foreach ($sale_items as $item) {
                        // Ajouter le détail de vente
                        $stmt = $db->prepare("
                            INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $sale_id,
                            $item['product']['id'],
                            $item['quantity'],
                            $item['unit_price'],
                            $item['total_price']
                        ]);
                        
                        // Mettre à jour le stock
                        $stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                        $stmt->execute([$item['quantity'], $item['product']['id']]);
                    }
                    
                    $db->commit();
                    
                    // Envoyer le ticket Discord (si webhook configuré)
                    sendDiscordTicket($sale_id, $sale_items, $customer, $total_amount, $loyalty_discount, $business_discount, $final_amount, $commission, $user);
                    
                    $message = "Vente enregistrée avec succès ! Ticket #$sale_id - Total: {$final_amount}$ - Commission: {$commission}$";
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// Fonction pour normaliser les noms de catégories en IDs valides
function normalizeCategoryId($category) {
    // Remplacer les caractères accentués
    $category = iconv('UTF-8', 'ASCII//TRANSLIT', $category);
    // Convertir en minuscules et remplacer les caractères non alphanumériques par des tirets
    $category = strtolower($category);
    $category = preg_replace('/[^a-zA-Z0-9]/', '-', $category);
    // Supprimer les tirets multiples et en début/fin
    $category = preg_replace('/-+/', '-', $category);
    $category = trim($category, '-');
    return $category;
}

// Récupérer les produits disponibles
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN product_categories c ON p.category_id = c.id 
    WHERE p.is_active = 1 AND p.stock_quantity > 0
    ORDER BY c.name, p.name
");
$stmt->execute();
$products = $stmt->fetchAll();

// Grouper les produits par catégorie
$products_by_category = [];
foreach ($products as $product) {
    $category = $product['category_name'] ?: 'Sans catégorie';
    $products_by_category[$category][] = $product;
}

// Récupérer les clients avec leurs informations d'entreprise
$stmt = $db->prepare("
    SELECT c.*, comp.business_discount 
    FROM customers c 
    LEFT JOIN companies comp ON c.company_id = comp.id AND comp.is_active = 1
    ORDER BY c.name
");
$stmt->execute();
$customers = $stmt->fetchAll();

// Fonction pour envoyer le ticket Discord
function sendDiscordTicket($sale_id, $items, $customer, $total, $loyalty_discount, $business_discount, $final, $commission, $user) {
    if (!DISCORD_WEBHOOK_URL) return;
    
    $customer_name = $customer ? $customer['name'] : 'Client anonyme';
    $discount_text = '';
    if ($loyalty_discount > 0) {
        $discount_text .= "\nRéduction fidélité: -{$loyalty_discount}$";
    }
    if ($business_discount > 0) {
        $discount_text .= "\nRéduction entreprise: -{$business_discount}$";
    }
    
    $items_text = "";
    foreach ($items as $item) {
        $items_text .= "• {$item['product']['name']} x{$item['quantity']} = {$item['total_price']}$\n";
    }
    
    $embed = [
        'title' => "🧾 Ticket de Caisse #$sale_id",
        'description' => "Nouvelle vente au Yellowjack",
        'color' => 0xD4AF37,
        'fields' => [
            ['name' => '👤 Client', 'value' => $customer_name, 'inline' => true],
            ['name' => '🧑‍💼 Vendeur', 'value' => $user['first_name'] . ' ' . $user['last_name'], 'inline' => true],
            ['name' => '📅 Date', 'value' => formatDateTime(getCurrentDateTime()), 'inline' => true],
            ['name' => '🛒 Produits', 'value' => $items_text, 'inline' => false],
            ['name' => '💰 Montant', 'value' => "Sous-total: {$total}${discount_text}\n**Total: {$final}$**", 'inline' => true],
            ['name' => '💵 Commission', 'value' => "{$commission}$", 'inline' => true]
        ],
        'footer' => ['text' => 'Le Yellowjack - Système de caisse'],
        'timestamp' => date('c')
    ];
    
    $payload = ['embeds' => [$embed]];
    
    $ch = curl_init(DISCORD_WEBHOOK_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

$page_title = 'Caisse Enregistreuse';
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
    <style>
        .product-card {
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-2px);
        }
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .quantity-controls input {
            width: 60px;
            text-align: center;
        }
        .category-toggle {
            border: none;
            background: transparent;
            color: #6c757d;
            transition: all 0.2s ease;
        }
        .category-toggle:hover {
            background: #f8f9fa;
            color: #495057;
            transform: scale(1.1);
        }
        .category-products {
            transition: all 0.3s ease;
        }
        
        /* Styles pour le dropdown des clients */
        #customer_dropdown {
            position: fixed;
            z-index: 99999;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.35);
            background-color: white;
            min-width: 300px;
            max-width: 400px;
            margin-top: 2px;
        }
        
        /* Assurer que le conteneur parent a un z-index approprié */
        .customer-search-container {
            position: relative;
        }
        
        /* Améliorer la visibilité du dropdown */
        #customer_dropdown.show {
            animation: fadeIn 0.15s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        #customer_dropdown .dropdown-item {
            cursor: pointer;
            padding: 0.5rem 1rem;
            border: none;
            background: none;
        }
        
        #customer_dropdown .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        
        #customer_dropdown .dropdown-item:active,
        #customer_dropdown .dropdown-item.active {
            background-color: #0d6efd;
            color: white;
        }
        
        #customer_search:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .input-group .btn:hover {
            background-color: #e9ecef;
        }
        
        /* Désactiver l'effet hover sur la carte client pour éviter les interférences avec le dropdown */
        .card:has(.customer-search-container):hover {
            transform: none !important;
            box-shadow: var(--shadow-md) !important;
        }
        
        /* Fallback pour les navigateurs qui ne supportent pas :has() */
        .card .customer-search-container {
            pointer-events: auto;
        }
        
        .card:hover .customer-search-container {
            transform: none;
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
                        <i class="fas fa-cash-register me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="sales_history.php" class="btn btn-outline-secondary">
                                <i class="fas fa-history me-1"></i>
                                Historique
                            </a>
                            <a href="customers.php" class="btn btn-outline-info">
                                <i class="fas fa-users me-1"></i>
                                Clients
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
                
                <form method="POST" id="saleForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="process_sale">
                    
                    <div class="row">
                        <!-- Sélection des produits -->
                        <div class="col-lg-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-shopping-cart me-2"></i>
                                        Sélection des produits
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($products_by_category)): ?>
                                        <div class="row">
                                            <?php foreach ($products_by_category as $category => $category_products): ?>
                                                <div class="col-12 mb-4">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="text-muted mb-0">
                                                            <i class="fas fa-tag me-2"></i>
                                                            <?php echo htmlspecialchars($category); ?>
                                                            <span class="badge bg-secondary ms-2"><?php echo count($category_products); ?></span>
                                                        </h6>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary category-toggle" 
                                                                data-category="<?php echo htmlspecialchars($category); ?>" 
                                                                onclick="toggleCategory('<?php echo addslashes($category); ?>')">
                                                            <i class="fas fa-chevron-up"></i>
                                                        </button>
                                                    </div>
                                                    <div class="row category-products" id="category-<?php echo normalizeCategoryId($category); ?>">
                                                        <?php foreach ($category_products as $product): ?>
                                                            <div class="col-md-6 col-lg-4 mb-3">
                                                                <div class="card product-card h-100" data-product-id="<?php echo $product['id']; ?>">
                                                                    <div class="card-body">
                                                                        <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                                        <p class="card-text">
                                                                            <span class="text-success fw-bold"><?php echo number_format($product['selling_price'], 2); ?>$</span>
                                                                            <br>
                                                                            <small class="text-muted">
                                                                                Stock: <?php echo $product['stock_quantity']; ?>
                                                                                <br>
                                                                                Marge: <?php echo number_format($product['selling_price'] - $product['supplier_price'], 2); ?>$
                                                                            </small>
                                                                        </p>
                                                                        <div class="d-flex align-items-center">
                                                                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="decreaseQuantity(<?php echo $product['id']; ?>)">
                                                                                <i class="fas fa-minus"></i>
                                                                            </button>
                                                                            <input type="number" class="form-control form-control-sm text-center" 
                                                                                   id="qty_<?php echo $product['id']; ?>" 
                                                                                   name="items[<?php echo $product['id']; ?>][quantity]" 
                                                                                   value="0" min="0" max="<?php echo $product['stock_quantity']; ?>" 
                                                                                   style="width: 60px;" 
                                                                                   onchange="updateCart()">
                                                                            <input type="hidden" name="items[<?php echo $product['id']; ?>][product_id]" value="<?php echo $product['id']; ?>">
                                                                            <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="increaseQuantity(<?php echo $product['id']; ?>, <?php echo $product['stock_quantity']; ?>)">
                                                                                <i class="fas fa-plus"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">Aucun produit disponible</h5>
                                            <p class="text-muted">Aucun produit en stock pour le moment.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Panier et finalisation -->
                        <div class="col-lg-4">
                            <!-- Sélection du client -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-user me-2"></i>
                                        Client
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <!-- Sélection du client avec recherche -->
                                    <div class="position-relative customer-search-container">
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-search"></i>
                                            </span>
                                            <input type="text" class="form-control" id="customer_search" 
                                   placeholder="Tapez pour rechercher un client ou sélectionnez..." 
                                   autocomplete="off"
                                   onclick="showCustomerDropdown()" 
                                   onfocus="showCustomerDropdown()" 
                                   onkeyup="filterCustomers()"
                                   onblur="setTimeout(function() { if (!document.getElementById('customer_dropdown').matches(':hover')) hideCustomerDropdown(); }, 200)">
                                            <button class="btn btn-outline-secondary" type="button" onclick="clearCustomerSelection()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Liste déroulante des clients -->
                                        <div id="customer_dropdown" class="dropdown-menu w-100" style="display: none; max-height: 200px; overflow-y: auto; position: fixed; z-index: 9999; background: white; border: 1px solid #ccc; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                                            <div class="dropdown-item" data-customer-id="0" onclick="selectCustomer(0, 'Client anonyme', false, 0)">
                                                <i class="fas fa-user-secret me-2"></i>
                                                Client anonyme
                                            </div>
                                            <?php foreach ($customers as $customer): ?>
                                                <div class="dropdown-item customer-option" 
                                                     data-customer-id="<?php echo $customer['id']; ?>"
                                                     data-customer-name="<?php echo strtolower(htmlspecialchars($customer['name'])); ?>"
                                                     onclick="selectCustomer(<?php echo $customer['id']; ?>, '<?php echo addslashes(htmlspecialchars($customer['name'])); ?>', <?php echo $customer['is_loyal'] ? 'true' : 'false'; ?>, <?php echo $customer['loyalty_discount']; ?>, <?php echo $customer['business_discount'] ?: 0; ?>)">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span>
                                                            <i class="fas fa-user me-2"></i>
                                                            <?php echo htmlspecialchars($customer['name']); ?>
                                                        </span>
                                                        <div>
                                                            <?php if ($customer['is_loyal']): ?>
                                                                <span class="badge bg-warning text-dark">
                                                                    ⭐ -<?php echo $customer['loyalty_discount']; ?>%
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if ($customer['business_discount'] > 0): ?>
                                                                <span class="badge bg-success">
                                                                    🏢 -<?php echo $customer['business_discount']; ?>%
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Champ caché pour le formulaire -->
                                    <input type="hidden" name="customer_id" id="customer_id" value="0">
                                    
                                    <!-- Affichage du client sélectionné -->
                                    <div id="selected_customer" class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-user-secret me-1"></i>
                                            Client sélectionné: <span id="selected_customer_name">Client anonyme</span>
                                            <span id="selected_customer_badge"></span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Panier -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-shopping-basket me-2"></i>
                                        Panier
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div id="cart-items">
                                        <p class="text-muted text-center">Panier vide</p>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div id="cart-summary">
                                        <div class="d-flex justify-content-between">
                                            <span>Sous-total:</span>
                                            <span id="subtotal">0.00$</span>
                                        </div>
                                        <div class="d-flex justify-content-between" id="discount-row" style="display: none;">
                                            <span>Réduction:</span>
                                            <span id="discount" class="text-success">-0.00$</span>
                                        </div>
                                        <div class="d-flex justify-content-between fw-bold border-top pt-2">
                                            <span>Total:</span>
                                            <span id="total">0.00$</span>
                                        </div>
                                        <div class="d-flex justify-content-between text-muted">
                                            <span>Commission (<?php echo ($user['role'] === 'CDI' || $user['role'] === 'Responsable' || $user['role'] === 'Patron') ? '20' : '10'; ?>%):</span>
                                            <span id="commission">0.00$</span>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success w-100 mt-3" id="processBtn" disabled>
                                        <i class="fas fa-credit-card me-2"></i>
                                        Encaisser
                                    </button>
                                    
                                    <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="clearCart()">
                                        <i class="fas fa-trash me-2"></i>
                                        Vider le panier
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Données des produits pour JavaScript
        const products = <?php echo json_encode($products); ?>;
        const productsById = {};
        products.forEach(product => {
            productsById[product.id] = product;
        });
        
        function increaseQuantity(productId, maxStock) {
            const input = document.getElementById('qty_' + productId);
            const currentValue = parseInt(input.value) || 0;
            if (currentValue < maxStock) {
                input.value = currentValue + 1;
                updateCart();
            }
        }
        
        function decreaseQuantity(productId) {
            const input = document.getElementById('qty_' + productId);
            const currentValue = parseInt(input.value) || 0;
            if (currentValue > 0) {
                input.value = currentValue - 1;
                updateCart();
            }
        }
        
        // Variables globales pour le client sélectionné
        let selectedCustomerId = 0;
        let selectedCustomerIsLoyal = false;
        let selectedCustomerDiscount = 0;
        let selectedCustomerBusinessDiscount = 0;
        
        function updateCart() {
            const cartItems = document.getElementById('cart-items');
            const customerIdInput = document.getElementById('customer_id');
            
            // Récupérer les informations du client sélectionné
            selectedCustomerId = parseInt(customerIdInput.value) || 0;
            
            let cartHTML = '';
            let subtotal = 0;
            let hasItems = false;
            
            // Parcourir tous les produits
            products.forEach(product => {
                const qtyInput = document.getElementById('qty_' + product.id);
                const quantity = parseInt(qtyInput.value) || 0;
                
                if (quantity > 0) {
                    hasItems = true;
                    const itemTotal = quantity * parseFloat(product.selling_price);
                    subtotal += itemTotal;
                    
                    cartHTML += `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <small class="fw-bold">${product.name}</small><br>
                                <small class="text-muted">${quantity} x ${parseFloat(product.selling_price).toFixed(2)}$</small>
                            </div>
                            <span class="fw-bold">${itemTotal.toFixed(2)}$</span>
                        </div>
                    `;
                }
            });
            
            if (!hasItems) {
                cartHTML = '<p class="text-muted text-center">Panier vide</p>';
            }
            
            cartItems.innerHTML = cartHTML;
            
            // Calculer les réductions
            let loyaltyDiscount = 0;
            let businessDiscount = 0;
            
            if (selectedCustomerId !== 0 && subtotal > 0) {
                // Réduction fidélité
                if (selectedCustomerIsLoyal) {
                    loyaltyDiscount = subtotal * (selectedCustomerDiscount / 100);
                }
                
                // Réduction entreprise
                if (selectedCustomerBusinessDiscount > 0) {
                    businessDiscount = subtotal * (selectedCustomerBusinessDiscount / 100);
                }
            }
            
            // Prendre la réduction la plus élevée (non cumulable)
            const totalDiscount = Math.max(loyaltyDiscount, businessDiscount);
            const total = subtotal - totalDiscount;
            const commissionRate = <?php echo ($user['role'] === 'CDI' || $user['role'] === 'Responsable' || $user['role'] === 'Patron') ? '20' : '10'; ?>;
            
            // Calculer le bénéfice total
            let totalProfit = 0;
            products.forEach(product => {
                const qtyInput = document.getElementById('qty_' + product.id);
                const quantity = parseInt(qtyInput.value) || 0;
                if (quantity > 0) {
                    const profit = (parseFloat(product.selling_price) - parseFloat(product.supplier_price)) * quantity;
                    totalProfit += profit;
                }
            });
            
            const commission = totalProfit * (commissionRate / 100);
            
            // Mettre à jour l'affichage
            document.getElementById('subtotal').textContent = subtotal.toFixed(2) + '$';
            document.getElementById('total').textContent = total.toFixed(2) + '$';
            document.getElementById('commission').textContent = commission.toFixed(2) + '$';
            
            const discountRow = document.getElementById('discount-row');
            if (totalDiscount > 0) {
                let discountText = '';
                if (loyaltyDiscount > 0) {
                    discountText += 'Fidélité: -' + loyaltyDiscount.toFixed(2) + '$';
                }
                if (businessDiscount > 0) {
                    if (discountText) discountText += ' | ';
                    discountText += 'Entreprise: -' + businessDiscount.toFixed(2) + '$';
                }
                document.getElementById('discount').innerHTML = discountText;
                discountRow.style.display = 'flex';
            } else {
                discountRow.style.display = 'none';
            }
            
            // Activer/désactiver le bouton
            document.getElementById('processBtn').disabled = !hasItems;
        }
        
        function clearCart() {
            products.forEach(product => {
                document.getElementById('qty_' + product.id).value = 0;
            });
            updateCart();
        }
        
        // Fonction pour normaliser les noms de catégories (côté JavaScript)
        function normalizeCategoryId(category) {
            // Remplacer les caractères accentués
            category = category.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            // Convertir en minuscules et remplacer les caractères non alphanumériques par des tirets
            category = category.toLowerCase();
            category = category.replace(/[^a-zA-Z0-9]/g, '-');
            // Supprimer les tirets multiples et en début/fin
            category = category.replace(/-+/g, '-');
            category = category.replace(/^-+|-+$/g, '');
            return category;
        }
        
        // Fonction pour réduire/développer les catégories
        function toggleCategory(categoryName) {
            const categoryId = 'category-' + normalizeCategoryId(categoryName);
            const categoryDiv = document.getElementById(categoryId);
            const toggleBtn = document.querySelector(`[data-category="${categoryName}"] i`);
            
            // Vérifier que les éléments existent
            if (!categoryDiv) {
                console.error('Élément de catégorie non trouvé:', categoryId);
                return;
            }
            if (!toggleBtn) {
                console.error('Bouton de toggle non trouvé pour:', categoryName);
                return;
            }
            
            // Vérifier l'état actuel (visible ou caché)
            const isHidden = categoryDiv.style.display === 'none' || 
                           window.getComputedStyle(categoryDiv).display === 'none';
            
            if (isHidden) {
                categoryDiv.style.display = 'flex';
                toggleBtn.className = 'fas fa-chevron-up';
            } else {
                categoryDiv.style.display = 'none';
                toggleBtn.className = 'fas fa-chevron-down';
            }
        }
        
        // Initialiser les catégories (toutes visibles par défaut)
        function initializeCategories() {
            const categoryDivs = document.querySelectorAll('.category-products');
            categoryDivs.forEach(div => {
                div.style.display = 'flex';
            });
            
            const toggleBtns = document.querySelectorAll('.category-toggle i');
            toggleBtns.forEach(btn => {
                btn.className = 'fas fa-chevron-up';
            });
        }
        
        // Variables globales pour la gestion du dropdown
        let dropdownTimeout;
        
        // Fonction pour afficher le dropdown des clients
        function showCustomerDropdown() {
            clearTimeout(dropdownTimeout);
            const dropdown = document.getElementById('customer_dropdown');
            const searchInput = document.getElementById('customer_search');
            
            if (!dropdown || !searchInput) {
                return;
            }
            
            // Calculer la position de l'input
            const inputRect = searchInput.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            const dropdownHeight = 200; // Hauteur max du dropdown
            
            // Positionner le dropdown avec position fixed
            dropdown.style.left = inputRect.left + 'px';
            dropdown.style.width = inputRect.width + 'px';
            dropdown.style.zIndex = '9999';
            dropdown.style.position = 'fixed';
            
            // Vérifier s'il y a assez d'espace en bas
            if (inputRect.bottom + dropdownHeight > viewportHeight) {
                // Afficher au-dessus
                dropdown.style.top = (inputRect.top - dropdownHeight - 5) + 'px';
                dropdown.style.bottom = 'auto';
            } else {
                // Afficher en dessous
                dropdown.style.top = (inputRect.bottom + 2) + 'px';
                dropdown.style.bottom = 'auto';
            }
            
            dropdown.style.display = 'block';
            dropdown.classList.add('show');
        }
        
        // Fonction pour masquer le dropdown des clients
        function hideCustomerDropdown() {
            const dropdown = document.getElementById('customer_dropdown');
            if (dropdown) {
                dropdown.style.display = 'none';
                dropdown.classList.remove('show');
            }
        }
        
        // Fonction pour filtrer les clients
        function filterCustomers() {
            const searchInput = document.getElementById('customer_search');
            const searchTerm = searchInput.value.toLowerCase().trim();
            const customerOptions = document.querySelectorAll('.customer-option');
            const anonymousOption = document.querySelector('[data-customer-id="0"]');
            
            // Toujours afficher l'option anonyme
            if (anonymousOption) {
                anonymousOption.style.display = 'block';
            }
            
            // Filtrer les autres options
            customerOptions.forEach(option => {
                const customerName = option.getAttribute('data-customer-name') || '';
                if (searchTerm === '' || customerName.includes(searchTerm)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Toujours afficher le dropdown quand on filtre
            showCustomerDropdown();
        }
        
        // Fonction pour sélectionner un client
        function selectCustomer(customerId, customerName, isLoyal, loyaltyDiscount, businessDiscount) {
            // Mettre à jour le champ caché
            document.getElementById('customer_id').value = customerId;
            
            // Mettre à jour les variables globales
            selectedCustomerId = customerId;
            selectedCustomerIsLoyal = isLoyal;
            selectedCustomerDiscount = loyaltyDiscount;
            selectedCustomerBusinessDiscount = businessDiscount || 0;
            
            // Mettre à jour l'affichage
            document.getElementById('customer_search').value = customerName;
            document.getElementById('selected_customer_name').textContent = customerName;
            
            // Mettre à jour les badges
            const badge = document.getElementById('selected_customer_badge');
            let badgeHtml = '';
            
            if (isLoyal && customerId !== 0) {
                badgeHtml += '<span class="badge bg-warning text-dark ms-1">⭐ -' + loyaltyDiscount + '%</span>';
            }
            
            if (businessDiscount > 0 && customerId !== 0) {
                badgeHtml += '<span class="badge bg-success ms-1">🏢 -' + businessDiscount + '%</span>';
            }
            
            badge.innerHTML = badgeHtml;
            
            // Masquer le dropdown
            hideCustomerDropdown();
            
            // Mettre à jour le panier
            updateCart();
        }
        
        // Fonction pour effacer la sélection
        function clearCustomerSelection() {
            selectCustomer(0, 'Client anonyme', false, 0, 0);
            document.getElementById('customer_search').value = '';
        }
        
        // Gestionnaire pour repositionner le dropdown lors du scroll/resize
        function repositionDropdown() {
            const dropdown = document.getElementById('customer_dropdown');
            if (dropdown.style.display === 'block') {
                showCustomerDropdown();
            }
        }
        
        // Ajouter les gestionnaires d'événements
        window.addEventListener('scroll', repositionDropdown);
        window.addEventListener('resize', repositionDropdown);
        
        // Fermer le dropdown si on clique ailleurs
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('customer_dropdown');
            const searchInput = document.getElementById('customer_search');
            const container = document.querySelector('.customer-search-container');
            
            if (!container.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.style.display = 'none';
                dropdown.classList.remove('show');
            }
        });
        
        // Initialiser le panier et les catégories
        updateCart();
        initializeCategories();
    </script>
</body>
</html>