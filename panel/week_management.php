<?php
/**
 * Page de gestion des semaines avec ID unique
 * Seul le patron peut finaliser et créer de nouvelles semaines
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../includes/auth.php';
require_once '../includes/week_functions.php';
require_once '../includes/tax_functions_integrated.php';
require_once '../config/database.php';

$db = getDB();
$auth = new Auth($db);

// Vérifier que l'utilisateur est connecté et a les permissions
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$auth->hasPermission('Patron')) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$currentUser = $auth->getCurrentUser();

// Obtenir les données d'abord
$activeWeek = getActiveWeekNew();
$allWeeks = getAllWeeks();
$activeWeekStats = getActiveWeekStats();

// Messages
$success_message = '';
$error_message = '';

// Traitement des actions
if ($_POST) {
    if (isset($_POST['calculate_taxes'])) {
        // Calculer les impôts pour la semaine active
        if ($activeWeek) {
            $result = calculateAndUpdateWeekTax($activeWeek['id']);
            if ($result['success']) {
                $success_message = 'Impôts calculés avec succès pour la semaine ' . $activeWeek['week_number'];
                // Recharger les données après le calcul
                $activeWeek = getActiveWeekNew();
            } else {
                $error_message = $result['message'];
            }
        } else {
            $error_message = 'Aucune semaine active trouvée.';
        }
    } elseif (isset($_POST['finalize_week'])) {
        // Finaliser la semaine avec calcul automatique des impôts
        if ($activeWeek) {
            $result = finalizeWeekAndCreateNewWithTax($currentUser['id']);
            
            if ($result['success']) {
                $success_message = $result['message'];
                // Recharger les données après la finalisation
                $activeWeek = getActiveWeekNew();
                $allWeeks = getAllWeeks();
                $activeWeekStats = getActiveWeekStats();
            } else {
                $error_message = $result['message'];
            }
        } else {
            $error_message = 'Aucune semaine active trouvée.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Semaines - YellowJack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        .week-card {
            transition: transform 0.2s;
        }
        .week-card:hover {
            transform: translateY(-2px);
        }
        .active-week {
            border: 2px solid #28a745;
            box-shadow: 0 0 15px rgba(40, 167, 69, 0.3);
        }
        .finalized-week {
            background-color: #f8f9fa;
            opacity: 0.8;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
                        <i class="fas fa-calendar-week me-2"></i>
                        Gestion des Semaines
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-primary" id="refreshDataBtn">
                                <i class="fas fa-sync-alt me-2"></i>Actualiser les Données
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="autoRefreshToggle">
                                <i class="fas fa-clock me-2"></i>Auto: <span id="autoRefreshStatus">OFF</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Semaine Active -->
                <?php if ($activeWeek): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card active-week">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-play-circle me-2"></i>
                                    Semaine Active - Semaine <?php echo $activeWeek['week_number']; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-calendar me-2"></i>Période</h6>
                                        <p class="mb-2">
                                            Du <?php echo date('d/m/Y', strtotime($activeWeek['week_start'])); ?>
                                            au <?php echo date('d/m/Y', strtotime($activeWeek['week_end'])); ?>
                                        </p>
                                        
                                        <h6><i class="fas fa-user me-2"></i>Créée par</h6>
                                        <?php
                                        $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                                        $stmt->execute([$activeWeek['created_by']]);
                                        $creator = $stmt->fetch();
                                        ?>
                                        <p><?php echo $creator ? $creator['first_name'] . ' ' . $creator['last_name'] : 'Utilisateur inconnu'; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="stats-card p-3 rounded text-center">
                                                    <h4><?php echo number_format($activeWeekStats['total_revenue'], 2); ?>$</h4>
                                                    <small>Chiffre d'Affaires</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="stats-card p-3 rounded text-center">
                                                    <h4><?php echo $activeWeekStats['total_sales_count']; ?></h4>
                                                    <small>Ventes</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-6">
                                                <div class="stats-card p-3 rounded text-center">
                                                    <h4><?php echo number_format($activeWeekStats['total_cleaning_revenue'], 2); ?>$</h4>
                                                    <small>Revenus Ménage</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="stats-card p-3 rounded text-center">
                                                    <h4><?php echo $activeWeekStats['total_cleaning_count']; ?></h4>
                                                    <small>Ménages</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Section Impôts -->
                                <hr>
                                <h6><i class="fas fa-calculator me-2"></i>Gestion des Impôts</h6>
                                <?php
                                // Récupérer les données d'impôts de la semaine active
                                $stmt = $db->prepare("SELECT tax_amount, effective_tax_rate, tax_breakdown, tax_calculated_at, tax_finalized FROM weeks WHERE id = ?");
                                $stmt->execute([$activeWeek['id']]);
                                $taxData = $stmt->fetch();
                                ?>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h5 class="card-title text-primary"><?php echo number_format($taxData['tax_amount'] ?? 0, 2); ?>$</h5>
                                                <p class="card-text">Impôts Calculés</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h5 class="card-title text-info"><?php echo number_format($taxData['effective_tax_rate'] ?? 0, 2); ?>%</h5>
                                                <p class="card-text">Taux Effectif</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h5 class="card-title <?php echo ($taxData['tax_finalized'] ?? false) ? 'text-success' : 'text-warning'; ?>">
                                                    <?php echo ($taxData['tax_finalized'] ?? false) ? 'Finalisés' : 'En attente'; ?>
                                                </h5>
                                                <p class="card-text">Statut Impôts</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($taxData['tax_breakdown']): ?>
                                <div class="mb-3">
                                    <h6><i class="fas fa-list me-2"></i>Détail du calcul par tranche</h6>
                                    <?php 
                                    $breakdown = json_decode($taxData['tax_breakdown'], true);
                                    if ($breakdown):
                                        foreach ($breakdown as $bracket): 
                                    ?>
                                        <div class="alert alert-light">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <strong>Tranche :</strong> 
                                                    <?php echo number_format($bracket['min_revenue'], 0, ',', ' '); ?>$ - 
                                                    <?php echo $bracket['max_revenue'] ? number_format($bracket['max_revenue'], 0, ',', ' ') . '$' : '∞'; ?>
                                                </div>
                                                <div class="col-md-2">
                                                    <strong>Taux :</strong> <?php echo $bracket['tax_rate']; ?>%
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Montant imposable :</strong> <?php echo number_format($bracket['taxable_amount'], 2, ',', ' '); ?>$
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Impôt :</strong> <?php echo number_format($bracket['tax_amount'], 2, ',', ' '); ?>$
                                                </div>
                                            </div>
                                        </div>
                                    <?php 
                                        endforeach;
                                    endif;
                                    ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Actions Impôts -->
                                <div class="mb-3">
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="calculate_taxes" class="btn btn-info me-2">
                                            <i class="fas fa-calculator me-2"></i>Calculer les Impôts
                                        </button>
                                    </form>
                                    
                                    <?php if ($taxData['tax_calculated_at'] && !($taxData['tax_finalized'] ?? false)): ?>
                                        <span class="text-muted">Derniers impôts calculés le <?php echo date('d/m/Y H:i', strtotime($taxData['tax_calculated_at'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Formulaire de finalisation -->
                                <hr>
                                <h6><i class="fas fa-flag-checkered me-2"></i>Finaliser la semaine et créer la suivante</h6>
                                <p class="text-muted">La finalisation calculera automatiquement les impôts et créera la semaine suivante.</p>
                                <form method="POST" class="row g-3">
                                    <div class="col-md-4">
                                        <label for="new_week_start" class="form-label">Début nouvelle semaine</label>
                                        <input type="date" class="form-control" id="new_week_start" name="new_week_start" 
                                               value="<?php echo date('Y-m-d', strtotime($activeWeek['week_end'] . ' +1 day')); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="new_week_end" class="form-label">Fin nouvelle semaine</label>
                                        <input type="date" class="form-control" id="new_week_end" name="new_week_end" 
                                               value="<?php echo date('Y-m-d', strtotime($activeWeek['week_end'] . ' +7 days')); ?>" required>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" name="finalize_week" class="btn btn-warning w-100" 
                                                onclick="return confirm('Êtes-vous sûr de vouloir finaliser la semaine <?php echo $activeWeek['week_number']; ?> et créer la semaine <?php echo $activeWeek['week_number'] + 1; ?> ?')">
                                            <i class="fas fa-check me-2"></i>
                                            Finaliser et Créer Semaine <?php echo $activeWeek['week_number'] + 1; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Aucune semaine active trouvée. Veuillez créer une nouvelle semaine.
                </div>
                <?php endif; ?>
                
                <!-- Historique des semaines -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>
                                    Historique des Semaines
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($allWeeks)): ?>
                                    <p class="text-muted">Aucune semaine trouvée.</p>
                                <?php else: ?>
                                <div class="row">
                                    <?php foreach ($allWeeks as $week): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card week-card <?php echo $week['is_finalized'] ? 'finalized-week' : ''; ?>">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <strong>Semaine <?php echo $week['week_number']; ?></strong>
                                                <div>
                                                    <?php if ($week['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php elseif ($week['is_finalized']): ?>
                                                        <span class="badge bg-secondary">Finalisée</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">En attente</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text">
                                                    <i class="fas fa-calendar me-2"></i>
                                                    <?php echo date('d/m/Y', strtotime($week['week_start'])); ?> - 
                                                    <?php echo date('d/m/Y', strtotime($week['week_end'])); ?>
                                                </p>
                                                
                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <small class="text-muted">CA Total</small>
                                                        <div class="fw-bold"><?php echo number_format($week['total_revenue'], 2); ?>$</div>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted">Ventes</small>
                                                        <div class="fw-bold"><?php echo $week['total_sales_count']; ?></div>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($week['is_finalized']): ?>
                                                <hr>
                                                <small class="text-muted">
                                                    <i class="fas fa-check me-1"></i>
                                                    Finalisée le <?php echo date('d/m/Y H:i', strtotime($week['finalized_at'])); ?>
                                                    <?php
                                                    if ($week['finalized_by']) {
                                                        $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                                                        $stmt->execute([$week['finalized_by']]);
                                                        $finalizer = $stmt->fetch();
                                                        if ($finalizer) {
                                                            echo '<br>par ' . $finalizer['first_name'] . ' ' . $finalizer['last_name'];
                                                        }
                                                    }
                                                    ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Informations système -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Fonctionnement du Système
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-cogs me-2"></i>Règles de Gestion</h6>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success me-2"></i>Chaque semaine a un ID unique (1, 2, 3, etc.)</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Une seule semaine peut être active à la fois</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Toutes les ventes et ménages sont liés à la semaine active</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Les données de chaque semaine sont isolées</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-user-shield me-2"></i>Permissions</h6>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-crown text-warning me-2"></i>Seul le patron peut finaliser une semaine</li>
                                            <li><i class="fas fa-crown text-warning me-2"></i>Seul le patron peut créer une nouvelle semaine</li>
                                            <li><i class="fas fa-eye text-info me-2"></i>Tous les employés peuvent voir les statistiques</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script d'actualisation des données -->
    <script>
    let autoRefreshInterval = null;
    let isAutoRefreshEnabled = false;
    
    // Fonction d'actualisation des données
    async function refreshWeekData() {
        const refreshBtn = document.getElementById('refreshDataBtn');
        const originalText = refreshBtn.innerHTML;
        
        try {
            // Afficher l'état de chargement
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Actualisation...';
            refreshBtn.disabled = true;
            
            const response = await fetch('refresh_week_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Afficher un message de succès
                showNotification('success', result.message);
                
                // Recharger la page après un court délai pour voir les nouvelles données
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showNotification('error', result.message || 'Erreur lors de l\'actualisation');
            }
            
        } catch (error) {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur de connexion lors de l\'actualisation');
        } finally {
            // Restaurer le bouton
            refreshBtn.innerHTML = originalText;
            refreshBtn.disabled = false;
        }
    }
    
    // Fonction pour afficher les notifications
    function showNotification(type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        const notification = document.createElement('div');
        notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            <i class="fas ${icon} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Supprimer automatiquement après 5 secondes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
    
    // Fonction pour basculer l'actualisation automatique
    function toggleAutoRefresh() {
        const toggleBtn = document.getElementById('autoRefreshToggle');
        const statusSpan = document.getElementById('autoRefreshStatus');
        
        if (isAutoRefreshEnabled) {
            // Désactiver l'actualisation automatique
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
            isAutoRefreshEnabled = false;
            statusSpan.textContent = 'OFF';
            toggleBtn.classList.remove('btn-success');
            toggleBtn.classList.add('btn-outline-secondary');
            showNotification('success', 'Actualisation automatique désactivée');
        } else {
            // Activer l'actualisation automatique (toutes les 30 secondes)
            autoRefreshInterval = setInterval(refreshWeekData, 30000);
            isAutoRefreshEnabled = true;
            statusSpan.textContent = 'ON (30s)';
            toggleBtn.classList.remove('btn-outline-secondary');
            toggleBtn.classList.add('btn-success');
            showNotification('success', 'Actualisation automatique activée (toutes les 30 secondes)');
        }
    }
    
    // Événements
    document.addEventListener('DOMContentLoaded', function() {
        // Bouton d'actualisation manuelle
        document.getElementById('refreshDataBtn').addEventListener('click', refreshWeekData);
        
        // Bouton d'actualisation automatique
        document.getElementById('autoRefreshToggle').addEventListener('click', toggleAutoRefresh);
        
        // Nettoyer l'intervalle quand on quitte la page
        window.addEventListener('beforeunload', function() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        });
    });
    </script>
</body>
</html>