<?php
/**
 * API pour récupérer les détails d'une vente
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Gestion d'erreur globale
set_error_handler(function($severity, $message, $file, $line) {
    error_log("PHP Error: $message in $file on line $line");
    if ($severity & error_reporting()) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de chargement des fichiers: ' . $e->getMessage()]);
    exit;
}

// Vérifier l'authentification
try {
    $auth = getAuth();
    if (!$auth->isLoggedIn()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Accès refusé. Veuillez vous connecter.']);
        exit;
    }
    $user = $auth->getCurrentUser();
    $db = getDB();
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Erreur d\'authentification ou de base de données: ' . $e->getMessage()]);
    exit;
}

// Vérifier que l'ID de vente est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || $_GET['id'] <= 0) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'ID de vente invalide ou manquant']);
    exit;
}

$sale_id = intval($_GET['id']);
if ($sale_id <= 0) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'ID de vente doit être un nombre positif']);
    exit;
}

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

// Tous les utilisateurs connectés peuvent voir les détails des ventes
$params = [$sale_id];

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $sale = $stmt->fetch();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
    exit;
}

if (!$sale) {
    http_response_code(404);
    echo json_encode(['error' => 'Vente non trouvée']);
    exit;
}

// Récupérer les articles de la vente
try {
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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des articles: ' . $e->getMessage()]);
    exit;
}

// Calculer les détails de réduction avec gestion d'erreur
try {
    $loyal_discount = 0;
    if (isset($sale['customer_loyal']) && $sale['customer_loyal']) {
        $loyal_discount = 5; // 5% pour client fidèle
    }
    
    $company_discount = 0;
    if (isset($sale['company_discount']) && $sale['company_discount'] !== null) {
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
} catch (Exception $e) {
    // En cas d'erreur, utiliser des valeurs par défaut
    $loyal_discount = 0;
    $company_discount = 0;
    $applied_discount = 0;
    $discount_type = '';
    error_log("Erreur calcul réduction: " . $e->getMessage());
}

// Préparer la réponse avec gestion d'erreur
try {
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
    
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    http_response_code(200);
    
    $json_response = json_encode($response, JSON_UNESCAPED_UNICODE);
    if ($json_response === false) {
        throw new Exception('Erreur lors de l\'encodage JSON: ' . json_last_error_msg());
    }
    
    echo $json_response;
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la génération de la réponse: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>