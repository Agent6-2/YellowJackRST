<?php
/**
 * Page d'accueil - Site vitrine Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Récupérer les paramètres généraux de la vitrine
$db = getDB();
$stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('bar_name', 'bar_slogan', 'bar_address', 'bar_phone', 'team_title', 'team_description', 'contact_title', 'contact_hours')");
$stmt->execute();
$general_settings_raw = $stmt->fetchAll();

$general_settings = [];
foreach ($general_settings_raw as $setting) {
    $general_settings[$setting['setting_key']] = $setting['setting_value'];
}

// Valeurs par défaut si les paramètres n'existent pas
$bar_name = $general_settings['bar_name'] ?? 'Le Yellowjack';
$bar_slogan = $general_settings['bar_slogan'] ?? 'Bienvenue dans l\'authentique bar western de Sandy Shore';
$bar_address = $general_settings['bar_address'] ?? 'Nord de Los Santos<br>Près de Sandy Shore';
$bar_phone = $general_settings['bar_phone'] ?? '+1-555-YELLOW';
$team_title = $general_settings['team_title'] ?? 'Notre Équipe';
$team_description = $general_settings['team_description'] ?? 'Rencontrez l\'équipe passionnée du Yellowjack';
$contact_title = $general_settings['contact_title'] ?? 'Nous Contacter';
$contact_hours = $general_settings['contact_hours'] ?? 'Ouvert 24h/24<br>7j/7';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($bar_name); ?> - Bar Western à Sandy Shore</title>
    <meta name="description" content="<?php echo htmlspecialchars($bar_name); ?>, bar western authentique situé au nord de Los Santos près de Sandy Shore. Ambiance western, boissons de qualité et service exceptionnel.">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Rye&family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-glass-whiskey me-2"></i>
                <?php echo htmlspecialchars($bar_name); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#accueil">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#carte">Notre Carte</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#equipe">Notre Équipe</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-warning ms-2" href="panel/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Panel Employé
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="accueil" class="hero-section">
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1 class="display-3 mb-4 western-font"><?php echo htmlspecialchars($bar_name); ?></h1>
                        <p class="lead mb-4"><?php echo htmlspecialchars($bar_slogan); ?>. Une expérience unique dans l'univers de Los Santos, où tradition et modernité se rencontrent.</p>
                        <div class="hero-buttons">
                            <a href="#carte" class="btn btn-warning btn-lg me-3">
                                <i class="fas fa-utensils me-2"></i>Découvrir la Carte
                            </a>
                            <a href="#contact" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-map-marker-alt me-2"></i>Nous Trouver
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image">
                        <div class="western-badge">
                            <i class="fas fa-star"></i>
                            <span>Depuis 2024</span>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- À Propos -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="mb-4">L'Esprit du Far West à Sandy Shore</h2>
                    <p class="lead mb-4">Le Yellowjack n'est pas qu'un simple bar, c'est un véritable voyage dans le temps. Situé au cœur de Sandy Shore, notre établissement vous transporte dans l'ambiance authentique du Far West américain.</p>
                    <div class="row mt-5">
                        <div class="col-md-4 mb-4">
                            <div class="feature-card">
                                <i class="fas fa-glass-cheers fa-3x text-warning mb-3"></i>
                                <h4>Boissons Premium</h4>
                                <p>Une sélection de whiskeys, bières et cocktails de qualité supérieure.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="feature-card">
                                <i class="fas fa-music fa-3x text-warning mb-3"></i>
                                <h4>Ambiance Western</h4>
                                <p>Décoration authentique et musique country pour une immersion totale.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="feature-card">
                                <i class="fas fa-users fa-3x text-warning mb-3"></i>
                                <h4>Service Exceptionnel</h4>
                                <p>Une équipe passionnée à votre service pour une expérience mémorable.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Notre Carte -->
    <section id="carte" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center mb-5">
                    <?php
                    // Récupérer les paramètres de la vitrine depuis la base de données
                    $db = getDB();
                    $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('menu_title', 'menu_description', 'alcool_title', 'soft_title', 'snacks_title', 'plats_title', 'alcool_items', 'soft_items', 'snacks_items', 'plats_items')");
                    $stmt->execute();
                    $settings_raw = $stmt->fetchAll();
                    
                    $settings = [];
                    foreach ($settings_raw as $setting) {
                        $settings[$setting['setting_key']] = $setting['setting_value'];
                    }
                    
                    // Valeurs par défaut si les paramètres n'existent pas
                    $menu_title = $settings['menu_title'] ?? 'Notre Carte';
                    $menu_description = $settings['menu_description'] ?? 'Découvrez notre sélection de boissons et plats dans l\'esprit western';
                    $alcool_title = $settings['alcool_title'] ?? 'Boissons Alcoolisées';
                    $soft_title = $settings['soft_title'] ?? 'Boissons Non-Alcoolisées';
                    $snacks_title = $settings['snacks_title'] ?? 'Snacks';
                    $plats_title = $settings['plats_title'] ?? 'Plats';
                    
                    // Récupérer les produits
                    $alcool_items = json_decode($settings['alcool_items'] ?? '[]', true);
                    if (empty($alcool_items)) {
                        $alcool_items = [
                            ['name' => 'Bière Pression', 'price' => '5', 'description' => 'Bière locale fraîche à la pression'],
                            ['name' => 'Whiskey Premium', 'price' => '25', 'description' => 'Whiskey vieilli en fût de chêne'],
                            ['name' => 'Vin Rouge', 'price' => '15', 'description' => 'Vin rouge de la région']
                        ];
                    }
                    
                    $soft_items = json_decode($settings['soft_items'] ?? '[]', true);
                    if (empty($soft_items)) {
                        $soft_items = [
                            ['name' => 'Coca-Cola', 'price' => '3', 'description' => 'Soda classique bien frais'],
                            ['name' => 'Eau Minérale', 'price' => '2', 'description' => 'Eau plate ou gazeuse']
                        ];
                    }
                    
                    $snacks_items = json_decode($settings['snacks_items'] ?? '[]', true);
                    if (empty($snacks_items)) {
                        $snacks_items = [
                            ['name' => 'Cacahuètes Salées', 'price' => '4', 'description' => 'Parfait avec une bière']
                        ];
                    }
                    
                    $plats_items = json_decode($settings['plats_items'] ?? '[]', true);
                    if (empty($plats_items)) {
                        $plats_items = [
                            ['name' => 'Burger Western', 'price' => '12', 'description' => 'Notre spécialité maison']
                        ];
                    }
                    ?>
                    <h2 class="western-font"><?php echo htmlspecialchars($menu_title); ?></h2>
                    <p class="lead"><?php echo htmlspecialchars($menu_description); ?></p>
                </div>
            </div>
            
            <div class="row">
                <!-- Boissons Alcoolisées -->
                <div class="col-lg-6 mb-4">
                    <div class="menu-category">
                        <h3 class="text-warning mb-4">
                            <i class="fas fa-glass-whiskey me-2"></i>
                            <?php echo htmlspecialchars($alcool_title); ?>
                        </h3>
                        <div class="menu-items">
                            <?php foreach ($alcool_items as $item): ?>
                            <div class="menu-item">
                                <div class="d-flex justify-content-between">
                                    <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <span class="item-price"><?php echo htmlspecialchars($item['price']); ?>$</span>
                                </div>
                                <small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Boissons Non-Alcoolisées -->
                <div class="col-lg-6 mb-4">
                    <div class="menu-category">
                        <h3 class="text-warning mb-4">
                            <i class="fas fa-coffee me-2"></i>
                            <?php echo htmlspecialchars($soft_title); ?>
                        </h3>
                        <div class="menu-items">
                            <?php foreach ($soft_items as $item): ?>
                            <div class="menu-item">
                                <div class="d-flex justify-content-between">
                                    <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <span class="item-price"><?php echo htmlspecialchars($item['price']); ?>$</span>
                                </div>
                                <small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Snacks -->
                <div class="col-lg-6 mb-4">
                    <div class="menu-category">
                        <h3 class="text-warning mb-4">
                            <i class="fas fa-utensils me-2"></i>
                            <?php echo htmlspecialchars($snacks_title); ?>
                        </h3>
                        <div class="menu-items">
                            <?php foreach ($snacks_items as $item): ?>
                            <div class="menu-item">
                                <div class="d-flex justify-content-between">
                                    <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <span class="item-price"><?php echo htmlspecialchars($item['price']); ?>$</span>
                                </div>
                                <small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Plats -->
                <div class="col-lg-6 mb-4">
                    <div class="menu-category">
                        <h3 class="text-warning mb-4">
                            <i class="fas fa-hamburger me-2"></i>
                            <?php echo htmlspecialchars($plats_title); ?>
                        </h3>
                        <div class="menu-items">
                            <?php foreach ($plats_items as $item): ?>
                            <div class="menu-item">
                                <div class="d-flex justify-content-between">
                                    <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <span class="item-price"><?php echo htmlspecialchars($item['price']); ?>$</span>
                                </div>
                                <small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Notre Équipe -->
    <section id="equipe" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center mb-5">
                    <h2 class="western-font"><?php echo htmlspecialchars($team_title); ?></h2>
                    <p class="lead"><?php echo htmlspecialchars($team_description); ?></p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="team-card">
                        <div class="team-avatar">
                            <i class="fas fa-user-tie fa-4x"></i>
                        </div>
                        <h4>Direction</h4>
                        <p class="text-warning">Patron</p>
                        <p>Gestion générale et vision stratégique du Yellowjack.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="team-card">
                        <div class="team-avatar">
                            <i class="fas fa-user-cog fa-4x"></i>
                        </div>
                        <h4>Management</h4>
                        <p class="text-warning">Responsables</p>
                        <p>Supervision des opérations quotidiennes et gestion d'équipe.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="team-card">
                        <div class="team-avatar">
                            <i class="fas fa-cocktail fa-4x"></i>
                        </div>
                        <h4>Service</h4>
                        <p class="text-warning">CDI</p>
                        <p>Service client et gestion de la caisse enregistreuse.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="team-card">
                        <div class="team-avatar">
                            <i class="fas fa-broom fa-4x"></i>
                        </div>
                        <h4>Entretien</h4>
                        <p class="text-warning">CDD</p>
                        <p>Maintien de la propreté et de l'ambiance du bar.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact -->
    <section id="contact" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="western-font mb-4"><?php echo htmlspecialchars($contact_title); ?></h2>
                    <p class="lead mb-5">Venez nous rendre visite ou contactez-nous pour plus d'informations</p>
                    
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="contact-info">
                                <i class="fas fa-map-marker-alt fa-2x text-warning mb-3"></i>
                                <h4>Adresse</h4>
                                <p><?php echo nl2br(htmlspecialchars($bar_address)); ?></p>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <div class="contact-info">
                                <i class="fas fa-phone fa-2x text-warning mb-3"></i>
                                <h4>Téléphone</h4>
                                <p><?php echo htmlspecialchars($bar_phone); ?></p>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <div class="contact-info">
                                <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                                <h4>Horaires</h4>
                                <p><?php echo nl2br(htmlspecialchars($contact_hours)); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <h5>
                        <i class="fas fa-glass-whiskey me-2"></i>
                        Le Yellowjack
                    </h5>
                    <p>L'authentique expérience western de Sandy Shore.</p>
                </div>
                <div class="col-lg-6 text-lg-end">
                    <p>&copy; 2024 Le Yellowjack. Tous droits réservés.</p>
                    <a href="panel/login.php" class="text-warning">
                        <i class="fas fa-cog me-1"></i>Panel Employé
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>