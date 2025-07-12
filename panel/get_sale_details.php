<?php
/**
 * API pour récupérer les détails d'une vente
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Vérifier les permissions
$auth = getAuth();
if (!$auth->hasPermission('CDI')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Accès refusé. Permissions insuffisantes.']);
    exit;
}
$user = $auth->getCurrentUser();
$db = getDB();

// Vérifier que l'ID de vente est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de vente invalide']);
    exit;
}

$sale_id = intval($_GET['id']);

// Récupérer les détails de la vente
$query = "
    SELECT 
        s.*,
        c.name as customer_name,
        c.is_loyal as customer_loyal,
        c.company_id,
        comp.name as company_name,
        comp.discount_percentage as company_discount,
        u.first_name,
        u.last_name
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    LEFT JOIN companies comp ON c.company_id = comp.id
    LEFT JOIN users u ON s.user_id = u.id 
    WHERE s.id = ?
";

// Restriction par utilisateur (sauf pour les responsables/patrons)
if (!$auth->canManageEmployees()) {
    $query .= " AND s.user_id = ?";
    $params = [$sale_id, $user['id']];
} else {
    $params = [$sale_id];
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$sale = $stmt->fetch();

if (!$sale) {
    http_response_code(404);
    echo json_encode(['error' => 'Vente non trouvée']);
    exit;
}

// Récupérer les articles de la vente
$stmt = $db->prepare("
    SELECT 
        si.*,
        p.name as product_name,
        p.price as product_price
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
    ORDER BY si.id
");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll();

// Calculer les détails de réduction
$loyal_discount = 0;
if ($sale['customer_loyal']) {
    $loyal_discount = 5; // 5% pour client fidèle
}

$company_discount = 0;
if ($sale['company_discount']) {
    $company_discount = floatval($sale['company_discount']);
}

// Déterminer la réduction appliquée (la plus élevée)
$applied_discount = max($loyal_discount, $company_discount);
$discount_type = '';
if ($applied_discount > 0) {
    if ($applied_discount == $loyal_discount && $applied_discount == $company_discount) {
        $discount_type = 'Client fidèle / Entreprise';
    } elseif ($applied_discount == $loyal_discount) {
        $discount_type = 'Client fidèle';
    } else {
        $discount_type = 'Entreprise';
    }
}

// Préparer la réponse
$response = [
    'sale' => $sale,
    'items' => $items,
    'discount_details' => [
        'loyal_discount' => $loyal_discount,
        'company_discount' => $company_discount,
        'applied_discount' => $applied_discount,
        'discount_type' => $discount_type
    ]
];

header('Content-Type: application/json');
echo json_encode($response);
?>