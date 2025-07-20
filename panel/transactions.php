<?php
/**
 * Page de gestion des transactions financières
 * Entrées et sorties d'argent, historique, graphiques
 */

require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/week_functions.php';
require_once '../config/database.php';

$db = getDB();

// Vérifier les permissions (Responsable et plus)
if (!$auth->canManageEmployees()) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Gestion des Transactions';
$current_page = 'transactions.php';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_transaction':
                try {
                    $stmt = $db->prepare("
                        INSERT INTO financial_transactions (type, category, amount, description, created_by, transaction_date) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $_POST['type'],
                        $_POST['category'],
                        $_POST['amount'],
                        $_POST['description'],
                        $_SESSION['user_id'],
                        date('Y-m-d')
                    ]);
                    
                    $success_message = "Transaction ajoutée avec succès.";
                } catch (PDOException $e) {
                    $error_message = "Erreur lors de l'ajout de la transaction: " . $e->getMessage();
                }
                break;
                
            case 'delete_transaction':
                try {
                    $stmt = $db->prepare("DELETE FROM financial_transactions WHERE id = ?");
                    $stmt->execute([$_POST['transaction_id']]);
                    $success_message = "Transaction supprimée avec succès.";
                } catch (PDOException $e) {
                    $error_message = "Erreur lors de la suppression: " . $e->getMessage();
                }
                break;
        }
    }
}

// Filtres
$filter_type = $_GET['filter_type'] ?? 'all';
$filter_category = $_GET['filter_category'] ?? 'all';
$filter_period = $_GET['filter_period'] ?? '30';
$search = $_GET['search'] ?? '';

// Construire la requête avec filtres
$where_conditions = [];
$params = [];

if ($filter_type !== 'all') {
    $where_conditions[] = "t.type = ?";
    $params[] = $filter_type;
}

if ($filter_category !== 'all') {
    $where_conditions[] = "t.category = ?";
    $params[] = $filter_category;
}

if ($filter_period !== 'all') {
    $where_conditions[] = "t.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params[] = $filter_period;
}

if (!empty($search)) {
    $where_conditions[] = "(t.description LIKE ? OR t.category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Récupérer les transactions
$transactions_query = "
    SELECT 
        t.*,
        u.first_name,
        u.last_name
    FROM financial_transactions t
    LEFT JOIN users u ON t.created_by = u.id
    $where_clause
    ORDER BY t.created_at DESC
    LIMIT 100
";

$stmt = $db->prepare($transactions_query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Statistiques générales
$stats_query = "
    SELECT 
        type,
        COUNT(*) as count,
        SUM(amount) as total
    FROM financial_transactions t
    $where_clause
    GROUP BY type
";

$stmt = $db->prepare($stats_query);
$stmt->execute($params);
$stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$total_income = 0;
$total_expense = 0;
$income_count = 0;
$expense_count = 0;

foreach ($stats as $type => $data) {
    if ($type === 'income') {
        $total_income = $data['total'] ?? 0;
        $income_count = $data['count'] ?? 0;
    } else {
        $total_expense = $data['total'] ?? 0;
        $expense_count = $data['count'] ?? 0;
    }
}

$balance = $total_income - $total_expense;

// Données pour les graphiques
$chart_data_query = "
    SELECT 
        DATE(created_at) as date,
        type,
        SUM(amount) as total
    FROM financial_transactions t
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at), type
    ORDER BY date
";

$chart_data = $db->query($chart_data_query)->fetchAll();

// Données pour le graphique par catégorie
$category_data_query = "
    SELECT 
        category,
        type,
        SUM(amount) as total
    FROM financial_transactions t
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY category, type
    ORDER BY total DESC
";}]}

$category_data = $db->query($category_data_query)->fetchAll();

// Récupérer les catégories pour les formulaires
$categories = $db->query("
    SELECT * FROM transaction_categories 
    WHERE is_active = 1 
    ORDER BY type, name
")->fetchAll();

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Gestion des Transactions
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                        <i class="fas fa-plus me-1"></i>
                        Nouvelle Transaction
                    </button>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Statistiques principales -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center border-success">
                        <div class="card-body">
                            <i class="fas fa-arrow-up fa-2x text-success mb-2"></i>
                            <h5 class="card-title text-success"><?php echo number_format($total_income, 2); ?>$</h5>
                            <p class="card-text text-muted">Entrées d'argent</p>
                            <small class="text-muted"><?php echo $income_count; ?> transactions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-danger">
                        <div class="card-body">
                            <i class="fas fa-arrow-down fa-2x text-danger mb-2"></i>
                            <h5 class="card-title text-danger"><?php echo number_format($total_expense, 2); ?>$</h5>
                            <p class="card-text text-muted">Sorties d'argent</p>
                            <small class="text-muted"><?php echo $expense_count; ?> transactions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-<?php echo $balance >= 0 ? 'success' : 'danger'; ?>">
                        <div class="card-body">
                            <i class="fas fa-balance-scale fa-2x text-<?php echo $balance >= 0 ? 'success' : 'danger'; ?> mb-2"></i>
                            <h5 class="card-title text-<?php echo $balance >= 0 ? 'success' : 'danger'; ?>"><?php echo number_format($balance, 2); ?>$</h5>
                            <p class="card-text text-muted">Solde</p>
                            <small class="text-muted"><?php echo $balance >= 0 ? 'Bénéfice' : 'Déficit'; ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-info">
                        <div class="card-body">
                            <i class="fas fa-list fa-2x text-info mb-2"></i>
                            <h5 class="card-title text-info"><?php echo count($transactions); ?></h5>
                            <p class="card-text text-muted">Transactions</p>
                            <small class="text-muted">Période sélectionnée</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Graphiques -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                Évolution du Solde (30 derniers jours)
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="balanceChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                Répartition par Catégorie
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtres -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-filter me-2"></i>
                        Filtres et Recherche
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label for="filter_type" class="form-label">Type</label>
                            <select class="form-select" id="filter_type" name="filter_type">
                                <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>Tous</option>
                                <option value="income" <?php echo $filter_type === 'income' ? 'selected' : ''; ?>>Entrées</option>
                                <option value="expense" <?php echo $filter_type === 'expense' ? 'selected' : ''; ?>>Sorties</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filter_category" class="form-label">Catégorie</label>
                            <select class="form-select" id="filter_category" name="filter_category">
                                <option value="all">Toutes</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['name']); ?>" 
                                            <?php echo $filter_category === $category['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="filter_period" class="form-label">Période</label>
                            <select class="form-select" id="filter_period" name="filter_period">
                                <option value="7" <?php echo $filter_period === '7' ? 'selected' : ''; ?>>7 jours</option>
                                <option value="30" <?php echo $filter_period === '30' ? 'selected' : ''; ?>>30 jours</option>
                                <option value="90" <?php echo $filter_period === '90' ? 'selected' : ''; ?>>3 mois</option>
                                <option value="365" <?php echo $filter_period === '365' ? 'selected' : ''; ?>>1 an</option>
                                <option value="all" <?php echo $filter_period === 'all' ? 'selected' : ''; ?>>Tout</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="search" class="form-label">Recherche</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Description, catégorie...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filtrer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Historique des transactions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>
                        Historique des Transactions
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Aucune transaction trouvée pour les critères sélectionnés.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Catégorie</th>
                                        <th>Description</th>
                                        <th>Utilisateur</th>
                                        <th>Montant</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?></td>
                                            <td>
                                                <?php if ($transaction['type'] === 'income'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-arrow-up me-1"></i>Entrée
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-arrow-down me-1"></i>Sortie
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($transaction['category']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                            <td>
                                                <?php if ($transaction['first_name']): ?>
                                                    <?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Système</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-<?php echo $transaction['type'] === 'income' ? 'success' : 'danger'; ?>">
                                                    <?php echo $transaction['type'] === 'income' ? '+' : '-'; ?><?php echo number_format($transaction['amount'], 2); ?>$
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($transaction['reference_type'] === 'other'): ?>
                                                    <form method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette transaction ?')">
                                                        <input type="hidden" name="action" value="delete_transaction">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Auto</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Nouvelle Transaction -->
<div class="modal fade" id="addTransactionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>
                    Nouvelle Transaction
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_transaction">
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Type de transaction *</label>
                        <select class="form-select" id="type" name="type" required onchange="updateCategories()">
                            <option value="">Sélectionner...</option>
                            <option value="income">Entrée d'argent</option>
                            <option value="expense">Sortie d'argent</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Catégorie *</label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="">Sélectionner d'abord le type...</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Montant ($) *</label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="3" placeholder="Description de la transaction..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Données des catégories pour le formulaire
const categories = <?php echo json_encode($categories); ?>;

function updateCategories() {
    const typeSelect = document.getElementById('type');
    const categorySelect = document.getElementById('category');
    const selectedType = typeSelect.value;
    
    // Vider les options
    categorySelect.innerHTML = '<option value="">Sélectionner une catégorie...</option>';
    
    if (selectedType) {
        // Filtrer les catégories par type
        const filteredCategories = categories.filter(cat => cat.type === selectedType);
        
        filteredCategories.forEach(category => {
            const option = document.createElement('option');
            option.value = category.name;
            option.textContent = category.name;
            categorySelect.appendChild(option);
        });
    }
}

// Graphique d'évolution du solde
const chartData = <?php echo json_encode($chart_data); ?>;
const dates = [...new Set(chartData.map(item => item.date))].sort();
const incomeData = [];
const expenseData = [];
let cumulativeBalance = 0;
const balanceData = [];

dates.forEach(date => {
    const dayIncome = chartData.find(item => item.date === date && item.type === 'income')?.total || 0;
    const dayExpense = chartData.find(item => item.date === date && item.type === 'expense')?.total || 0;
    
    incomeData.push(dayIncome);
    expenseData.push(dayExpense);
    cumulativeBalance += (dayIncome - dayExpense);
    balanceData.push(cumulativeBalance);
});

const balanceCtx = document.getElementById('balanceChart').getContext('2d');
new Chart(balanceCtx, {
    type: 'line',
    data: {
        labels: dates.map(date => new Date(date).toLocaleDateString('fr-FR')),
        datasets: [{
            label: 'Solde Cumulé',
            data: balanceData,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.1,
            fill: true
        }, {
            label: 'Entrées',
            data: incomeData,
            borderColor: 'rgb(40, 167, 69)',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.1
        }, {
            label: 'Sorties',
            data: expenseData,
            borderColor: 'rgb(220, 53, 69)',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toFixed(2) + '$';
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + '$';
                    }
                }
            }
        }
    }
});

// Graphique de répartition par catégorie
const categoryData = <?php echo json_encode($category_data); ?>;
const categoryLabels = categoryData.map(item => item.category);
const categoryValues = categoryData.map(item => item.total);
const categoryColors = categoryData.map(item => item.color || '#6c757d');

const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: categoryLabels,
        datasets: [{
            data: categoryValues,
            backgroundColor: categoryColors,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed.toFixed(2) + '$ (' + percentage + '%)';
                    }
                }
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>