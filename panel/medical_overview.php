<?php
/**
 * Vue d'ensemble des visites médicales - Accessible uniquement aux patrons
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();

// Vérifier que l'utilisateur est connecté et est un patron
if (!$auth->isLoggedIn() || !$auth->hasPermission('Patron')) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$current_user = $auth->getCurrentUser();

// Récupérer tous les employés avec leurs liens médicaux
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.username,
            u.first_name,
            u.last_name,
            u.email,
            u.role,
            u.status,
            COUNT(eml.id) as medical_links_count,
            GROUP_CONCAT(
                CONCAT(eml.link_title, '|', eml.medical_url, '|', eml.description) 
                SEPARATOR '###'
            ) as medical_links
        FROM users u
        LEFT JOIN employee_medical_links eml ON u.id = eml.user_id AND eml.is_active = 1
        WHERE u.role IN ('CDD', 'CDI', 'Responsable')
        GROUP BY u.id
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur lors de la récupération des données : " . $e->getMessage();
    $employees = [];
}

// Fonction pour générer les initiales
function getInitials($firstName, $lastName) {
    return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
}

// Fonction pour obtenir la couleur de l'avatar selon le rôle
function getAvatarColor($role) {
    $colors = [
        'CDD' => '#3498db',
        'CDI' => '#2ecc71',
        'Responsable' => '#e74c3c',
        'Patron' => '#9b59b6'
    ];
    return $colors[$role] ?? '#95a5a6';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visites Médicales - Le Yellowjack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/panel.css" rel="stylesheet">
    <style>
        .medical-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .medical-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .employee-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .employee-info {
            flex-grow: 1;
        }
        
        .employee-name {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .employee-role {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .medical-links {
            margin-top: 15px;
        }
        
        .medical-link {
            display: inline-block;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 8px 12px;
            margin: 5px 5px 5px 0;
            text-decoration: none;
            color: #495057;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .medical-link:hover {
            background: #e9ecef;
            color: #495057;
            text-decoration: none;
            transform: translateY(-1px);
        }
        
        .medical-link i {
            margin-right: 5px;
            color: #28a745;
        }
        
        .no-links {
            color: #6c757d;
            font-style: italic;
            font-size: 14px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 0 0 20px 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="page-header">
                    <div class="container">
                        <h1 class="mb-0">
                            <i class="fas fa-user-md me-3"></i>
                            Visites Médicales
                        </h1>
                        <p class="mb-0 mt-2">Vue d'ensemble des documents médicaux de tous les employés</p>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-card text-center">
                            <div class="stats-number"><?php echo count($employees); ?></div>
                            <div>Employés Total</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card text-center">
                            <div class="stats-number">
                                <?php echo count(array_filter($employees, function($emp) { return $emp['medical_links_count'] > 0; })); ?>
                            </div>
                            <div>Avec Documents</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card text-center">
                            <div class="stats-number">
                                <?php echo array_sum(array_column($employees, 'medical_links_count')); ?>
                            </div>
                            <div>Documents Total</div>
                        </div>
                    </div>
                </div>
                
                <!-- Liste des employés -->
                <div class="row">
                    <?php if (empty($employees)): ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                Aucun employé trouvé.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($employees as $employee): ?>
                            <div class="col-lg-6 col-xl-4">
                                <div class="medical-card">
                                    <div class="d-flex align-items-start">
                                        <div class="employee-avatar" style="background-color: <?php echo getAvatarColor($employee['role']); ?>">
                                            <?php echo getInitials($employee['first_name'], $employee['last_name']); ?>
                                        </div>
                                        
                                        <div class="employee-info">
                                            <div class="employee-name">
                                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                            </div>
                                            <div class="employee-role">
                                                <i class="fas fa-user-tag me-1"></i>
                                                <?php echo htmlspecialchars($employee['role']); ?>
                                                <span class="ms-2">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($employee['username']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="medical-links">
                                                <?php if ($employee['medical_links_count'] > 0 && $employee['medical_links']): ?>
                                                    <?php 
                                                    $links = explode('###', $employee['medical_links']);
                                                    foreach ($links as $link_data): 
                                                        if (empty($link_data)) continue;
                                                        $parts = explode('|', $link_data);
                                                        if (count($parts) >= 2):
                                                            $title = $parts[0];
                                                            $url = $parts[1];
                                                            $description = $parts[2] ?? '';
                                                    ?>
                                                        <a href="<?php echo htmlspecialchars($url); ?>" 
                                                           target="_blank" 
                                                           class="medical-link"
                                                           title="<?php echo htmlspecialchars($description); ?>">
                                                            <i class="fas fa-file-medical"></i>
                                                            <?php echo htmlspecialchars($title); ?>
                                                        </a>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                <?php else: ?>
                                                    <div class="no-links">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Aucun document médical
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 pt-3 border-top">
                                        <small class="text-muted">
                                            <i class="fas fa-link me-1"></i>
                                            <?php echo $employee['medical_links_count']; ?> document(s)
                                            <span class="ms-3">
                                                <i class="fas fa-circle me-1" style="color: <?php echo $employee['status'] === 'active' ? '#28a745' : '#dc3545'; ?>"></i>
                                                <?php echo ucfirst($employee['status']); ?>
                                            </span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation d'entrée pour les cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.medical-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>