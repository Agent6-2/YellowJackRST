<?php
/**
 * Gestion des liens médicaux des employés - Panel Employé Le Yellowjack
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
$employee_id = intval($_GET['employee_id'] ?? 0);

// Vérifier que l'employé existe
if ($employee_id) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        header('Location: employees.php');
        exit;
    }
} else {
    header('Location: employees.php');
    exit;
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Token de sécurité invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_medical_link':
                $link_title = trim($_POST['link_title'] ?? '');
                $medical_url = trim($_POST['medical_url'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($link_title) || empty($medical_url)) {
                    $error = 'Le titre et l\'URL sont obligatoires.';
                } elseif (!filter_var($medical_url, FILTER_VALIDATE_URL)) {
                    $error = 'L\'URL fournie n\'est pas valide.';
                } else {
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO employee_medical_links (user_id, link_title, medical_url, description) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$employee_id, $link_title, $medical_url, $description]);
                        $message = 'Lien médical ajouté avec succès !';
                    } catch (Exception $e) {
                        $error = 'Erreur lors de l\'ajout du lien médical : ' . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_medical_link':
                $link_id = intval($_POST['link_id']);
                $link_title = trim($_POST['link_title'] ?? '');
                $medical_url = trim($_POST['medical_url'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($link_title) || empty($medical_url)) {
                    $error = 'Le titre et l\'URL sont obligatoires.';
                } elseif (!filter_var($medical_url, FILTER_VALIDATE_URL)) {
                    $error = 'L\'URL fournie n\'est pas valide.';
                } else {
                    try {
                        $stmt = $db->prepare("
                            UPDATE employee_medical_links 
                            SET link_title = ?, medical_url = ?, description = ?, is_active = ?
                            WHERE id = ? AND user_id = ?
                        ");
                        $stmt->execute([$link_title, $medical_url, $description, $is_active, $link_id, $employee_id]);
                        $message = 'Lien médical modifié avec succès !';
                    } catch (Exception $e) {
                        $error = 'Erreur lors de la modification du lien médical.';
                    }
                }
                break;
                
            case 'delete_medical_link':
                $link_id = intval($_POST['link_id']);
                try {
                    $stmt = $db->prepare("DELETE FROM employee_medical_links WHERE id = ? AND user_id = ?");
                    $stmt->execute([$link_id, $employee_id]);
                    $message = 'Lien médical supprimé avec succès !';
                } catch (Exception $e) {
                    $error = 'Erreur lors de la suppression du lien médical.';
                }
                break;
        }
    }
}

// Récupérer les liens médicaux de l'employé
$stmt = $db->prepare("
    SELECT * FROM employee_medical_links 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$employee_id]);
$medical_links = $stmt->fetchAll();

$page_title = 'Liens Médicaux - ' . $employee['first_name'] . ' ' . $employee['last_name'];
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
                        <i class="fas fa-user-md me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicalLinkModal">
                                <i class="fas fa-plus me-1"></i>
                                Nouveau Lien Médical
                            </button>
                            <a href="employees.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>
                                Retour aux Employés
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
                
                <!-- Informations employé -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <div class="avatar-circle-large">
                                    <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                                </div>
                            </div>
                            <div class="col-md-10">
                                <h4><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h4>
                                <p class="mb-1">
                                    <span class="badge bg-<?php echo $employee['role'] === 'Patron' ? 'danger' : ($employee['role'] === 'Responsable' ? 'secondary' : ($employee['role'] === 'CDI' ? 'info' : 'warning')); ?>">
                                        <?php echo htmlspecialchars($employee['role']); ?>
                                    </span>
                                    <span class="badge bg-<?php echo $employee['status'] === 'active' ? 'success' : 'danger'; ?> ms-2">
                                        <?php echo $employee['status'] === 'active' ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </p>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($employee['username']); ?>
                                    <?php if ($employee['email']): ?>
                                        <i class="fas fa-envelope ms-3 me-1"></i> <?php echo htmlspecialchars($employee['email']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Liste des liens médicaux -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-link me-2"></i>
                            Liens Médicaux (<?php echo count($medical_links); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($medical_links)): ?>
                            <div class="row">
                                <?php foreach ($medical_links as $link): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 <?php echo $link['is_active'] ? '' : 'border-secondary'; ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0">
                                                        <i class="fas fa-stethoscope me-2 text-primary"></i>
                                                        <?php echo htmlspecialchars($link['link_title']); ?>
                                                    </h6>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="editMedicalLink(<?php echo htmlspecialchars(json_encode($link)); ?>)" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editMedicalLinkModal">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Confirmer la suppression ?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="action" value="delete_medical_link">
                                                            <input type="hidden" name="link_id" value="<?php echo $link['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($link['description']): ?>
                                                    <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($link['description']); ?></p>
                                                <?php endif; ?>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <a href="<?php echo htmlspecialchars($link['medical_url']); ?>" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-<?php echo $link['is_active'] ? 'success' : 'secondary'; ?> <?php echo !$link['is_active'] ? 'disabled' : ''; ?>">
                                                        <i class="fas fa-external-link-alt me-1"></i>
                                                        Accéder au lien
                                                    </a>
                                                    <small class="text-muted">
                                                        <?php echo formatDateTime($link['created_at'], 'd/m/Y H:i'); ?>
                                                    </small>
                                                </div>
                                                
                                                <?php if (!$link['is_active']): ?>
                                                    <div class="mt-2">
                                                        <span class="badge bg-secondary">Inactif</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucun lien médical</h5>
                                <p class="text-muted">Aucun lien médical n'a été configuré pour cet employé.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicalLinkModal">
                                    <i class="fas fa-plus me-2"></i>
                                    Ajouter un lien médical
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal ajout lien médical -->
    <div class="modal fade" id="addMedicalLinkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus me-2"></i>
                            Nouveau Lien Médical
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="add_medical_link">
                        
                        <div class="mb-3">
                            <label for="add_link_title" class="form-label">Titre du lien *</label>
                            <input type="text" class="form-control" id="add_link_title" name="link_title" required 
                                   placeholder="Ex: Visite médicale annuelle">
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_medical_url" class="form-label">URL du lien médical *</label>
                            <input type="url" class="form-control" id="add_medical_url" name="medical_url" required 
                                   placeholder="https://example-medical-center.com/appointment">
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_description" class="form-label">Description (optionnel)</label>
                            <textarea class="form-control" id="add_description" name="description" rows="3" 
                                      placeholder="Description du lien médical..."></textarea>
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
    
    <!-- Modal modification lien médical -->
    <div class="modal fade" id="editMedicalLinkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit me-2"></i>
                            Modifier le Lien Médical
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="edit_medical_link">
                        <input type="hidden" name="link_id" id="edit_link_id">
                        
                        <div class="mb-3">
                            <label for="edit_link_title" class="form-label">Titre du lien *</label>
                            <input type="text" class="form-control" id="edit_link_title" name="link_title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_medical_url" class="form-label">URL du lien médical *</label>
                            <input type="url" class="form-control" id="edit_medical_url" name="medical_url" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description (optionnel)</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" checked>
                                <label class="form-check-label" for="edit_is_active">
                                    Lien actif
                                </label>
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
        function editMedicalLink(link) {
            document.getElementById('edit_link_id').value = link.id;
            document.getElementById('edit_link_title').value = link.link_title;
            document.getElementById('edit_medical_url').value = link.medical_url;
            document.getElementById('edit_description').value = link.description || '';
            document.getElementById('edit_is_active').checked = link.is_active == 1;
        }
    </script>
    
    <style>
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8B4513, #D2691E);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        
        .avatar-circle-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8B4513, #D2691E);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 24px;
            margin: 0 auto;
        }
    </style>
</body>
</html>