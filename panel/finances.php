<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/week_functions.php';
require_once '../config/database.php';

$db = getDB();
$auth = new Auth();

// Vérifier l'authentification
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Seuls les responsables et patrons peuvent accéder à la gestion financière
if (!$auth->canManageEmployees()) {
    header('Location: dashboard.php');
    exit;
}

$user = $auth->getCurrentUser();
$current_page = 'finances.php';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_transaction'])) {
        $type = $_POST['type'];
        $category = $_POST['category'];
        $amount = floatval($_POST['amount']);
        $description = trim($_POST['description']);
        $transaction_date = $_POST['transaction_date'];
        
        try {
            $stmt = $db->prepare("
                INSERT INTO financial_transactions (type, category, amount, description, transaction_date, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$type, $category, $amount, $description, $transaction_date, $user['id']]);
            
            $success_message = "Transaction ajoutée avec succès !";
        } catch (PDOException $e) {
            $error_message = "Erreur lors de l'ajout de la transaction : " . $e->getMessage();
        }
    }
}

// Récupération des données pour les graphiques et statistiques
$period = $_GET['period'] ?? 'month';
$start_date = '';
$end_date = '';

switch ($period) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        break;
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
}

// Calcul du solde actuel
$current_balance = 0;
try {
    // Entrées d'argent
    $stmt = $db->query("
        SELECT 
            COALESCE(SUM(final_amount), 0) as sales_revenue,
            COALESCE(SUM(total_salary), 0) as cleaning_revenue
        FROM (
            SELECT final_amount, 0 as total_salary FROM sales
            UNION ALL
            SELECT 0 as final_amount, total_salary FROM cleaning_services WHERE status = 'completed'
        ) as revenues
    ");
    $revenues = $stmt->fetch();
    $total_revenues = $revenues['sales_revenue'] + $revenues['cleaning_revenue'];
    
    // Sorties d'argent (transactions négatives + salaires à verser + primes)
    $stmt = $db->query("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses,
            COALESCE(SUM(amount), 0) as bonuses
        FROM (
            SELECT amount, 'expense' as type FROM financial_transactions WHERE type = 'expense'
            UNION ALL
            SELECT amount, 'bonus' as type FROM bonuses
        ) as expenses_data
    ");
    $expenses_data = $stmt->fetch();
    $total_expenses = $expenses_data['expenses'] + $expenses_data['bonuses'];
    
    $current_balance = $total_revenues - $total_expenses;
} catch (PDOException $e) {
    $current_balance = 0;
}

// Statistiques de la période
$period_stats = [
    'income' => 0,
    'expenses' => 0,
    'net' => 0
];

try {
    // Revenus de la période
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(final_amount), 0) as sales_revenue
        FROM sales 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $sales_revenue = $stmt->fetch()['sales_revenue'];
    
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(total_salary), 0) as cleaning_revenue
        FROM cleaning_services 
        WHERE status = 'completed' AND DATE(start_time) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $cleaning_revenue = $stmt->fetch()['cleaning_revenue'];
    
    // Transactions d'entrée personnalisées
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as custom_income
        FROM financial_transactions 
        WHERE type = 'income' AND DATE(transaction_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $custom_income = $stmt->fetch()['custom_income'];
    
    $period_stats['income'] = $sales_revenue + $cleaning_revenue + $custom_income;
    
    // Dépenses de la période
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as expenses
        FROM financial_transactions 
        WHERE type = 'expense' AND DATE(transaction_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $expenses = $stmt->fetch()['expenses'];
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as bonuses
        FROM bonuses 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $bonuses = $stmt->fetch()['bonuses'];
    
    $period_stats['expenses'] = $expenses + $bonuses;
    $period_stats['net'] = $period_stats['income'] - $period_stats['expenses'];
} catch (PDOException $e) {
    // Garder les valeurs par défaut
}

// Récupération des transactions récentes
$recent_transactions = [];
try {
    $stmt = $db->prepare("
        SELECT 
            ft.id,
            ft.type,
            ft.category,
            ft.amount,
            ft.description,
            ft.transaction_date,
            ft.created_at,
            u.first_name,
            u.last_name
        FROM financial_transactions ft
        LEFT JOIN users u ON ft.created_by = u.id
        ORDER BY ft.transaction_date DESC, ft.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    $recent_transactions = [];
}

// Données pour le graphique d'évolution
$chart_data = [];
try {
    $stmt = $db->prepare("
        SELECT 
            DATE(transaction_date) as date,
            SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as daily_change
        FROM financial_transactions
        WHERE DATE(transaction_date) BETWEEN ? AND ?
        GROUP BY DATE(transaction_date)
        ORDER BY date
    ");
    $stmt->execute([$start_date, $end_date]);
    $chart_data = $stmt->fetchAll();
} catch (PDOException $e) {
    $chart_data = [];
}

$page_title = 'Gestion Financière';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-chart-line me-2"></i>
                    Gestion Financière
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="?period=week" class="btn btn-sm <?php echo $period === 'week' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Semaine</a>
                        <a href="?period=month" class="btn btn-sm <?php echo $period === 'month' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Mois</a>
                        <a href="?period=year" class="btn btn-sm <?php echo $period === 'year' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Année</a>
                    </div>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
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
            
            <!-- Tableau de bord financier -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body">
                            <i class="fas fa-wallet fa-2x text-primary mb-2"></i>
                            <h5 class="card-title"><?php echo number_format($current_balance, 2); ?>$</h5>
                            <p class="card-text text-muted">Solde Actuel</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body">
                            <i class="fas fa-arrow-up fa-2x text-success mb-2"></i>
                            <h5 class="card-title"><?php echo number_format($period_stats['income'], 2); ?>$</h5>
                            <p class="card-text text-muted">Entrées (<?php echo ucfirst($period); ?>)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body">
                            <i class="fas fa-arrow-down fa-2x text-danger mb-2"></i>
                            <h5 class="card-title"><?php echo number_format($period_stats['expenses'], 2); ?>$</h5>
                            <p class="card-text text-muted">Sorties (<?php echo ucfirst($period); ?>)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body">
                            <i class="fas fa-chart-line fa-2x <?php echo $period_stats['net'] >= 0 ? 'text-success' : 'text-danger'; ?> mb-2"></i>
                            <h5 class="card-title"><?php echo number_format($period_stats['net'], 2); ?>$</h5>
                            <p class="card-text text-muted">Résultat Net</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Graphique d'évolution -->
                <div class="col-lg-8 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-area me-2"></i>
                                Évolution du Solde
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="balanceChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Répartition des entrées/sorties -->
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                Répartition
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="distributionChart" width="300" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Historique des transactions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>
                        Historique des Transactions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Catégorie</th>
                                    <th>Description</th>
                                    <th>Montant</th>
                                    <th>Créé par</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?></td>
                                        <td>
                                            <?php if ($transaction['type'] === 'income'): ?>
                                                <span class="badge bg-success">Entrée</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Sortie</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                        <td class="<?php echo $transaction['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $transaction['type'] === 'income' ? '+' : '-'; ?><?php echo number_format($transaction['amount'], 2); ?>$
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editTransaction(<?php echo $transaction['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTransaction(<?php echo $transaction['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($recent_transactions)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            Aucune transaction trouvée
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">Type *</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="income">💰 Entrée d'argent</option>
                                    <option value="expense">🧾 Sortie d'argent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Catégorie *</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Sélectionner...</option>
                                    <!-- Options dynamiques selon le type -->
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Montant ($) *</label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="transaction_date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required placeholder="Décrivez la transaction..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_transaction" class="btn btn-success">
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
// Gestion des catégories dynamiques
document.getElementById('type').addEventListener('change', function() {
    const categorySelect = document.getElementById('category');
    const type = this.value;
    
    categorySelect.innerHTML = '<option value="">Sélectionner...</option>';
    
    if (type === 'income') {
        const incomeCategories = [
            'Ventes Bar',
            'Services Ménage',
            'Pourboires',
            'Subventions',
            'Autres Revenus'
        ];
        
        incomeCategories.forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            categorySelect.appendChild(option);
        });
    } else if (type === 'expense') {
        const expenseCategories = [
            'Achats Marchandises',
            'Salaires',
            'Primes',
            'Loyer',
            'Électricité',
            'Eau',
            'Internet',
            'Assurances',
            'Maintenance',
            'Marketing',
            'Autres Dépenses'
        ];
        
        expenseCategories.forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            categorySelect.appendChild(option);
        });
    }
});

// Graphique d'évolution du solde
const balanceCtx = document.getElementById('balanceChart').getContext('2d');
const balanceChart = new Chart(balanceCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($chart_data, 'date')); ?>,
        datasets: [{
            label: 'Évolution du Solde',
            data: <?php echo json_encode(array_column($chart_data, 'daily_change')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value + '$';
                    }
                }
            }
        }
    }
});

// Graphique de répartition
const distributionCtx = document.getElementById('distributionChart').getContext('2d');
const distributionChart = new Chart(distributionCtx, {
    type: 'doughnut',
    data: {
        labels: ['Entrées', 'Sorties'],
        datasets: [{
            data: [<?php echo $period_stats['income']; ?>, <?php echo $period_stats['expenses']; ?>],
            backgroundColor: [
                'rgba(75, 192, 192, 0.8)',
                'rgba(255, 99, 132, 0.8)'
            ],
            borderColor: [
                'rgb(75, 192, 192)',
                'rgb(255, 99, 132)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Fonctions pour les actions sur les transactions
function editTransaction(id) {
    // TODO: Implémenter l'édition
    alert('Fonctionnalité d\'édition à venir');
}

function deleteTransaction(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette transaction ?')) {
        // TODO: Implémenter la suppression
        alert('Fonctionnalité de suppression à venir');
    }
}
</script>

<?php include 'includes/footer.php'; ?>