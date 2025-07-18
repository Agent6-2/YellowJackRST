<?php
/**
 * Script de correction pour résoudre le problème de permissions
 * dans la gestion des semaines
 */

require_once 'includes/auth.php';
require_once 'config/database.php';

echo "<h1>Correction du Système de Permissions</h1>";

// Étape 1: Vérifier l'état actuel
echo "<h2>1. Diagnostic de l'état actuel</h2>";

echo "<h3>Session actuelle:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Étape 2: Tester la classe Auth
echo "<h2>2. Test de la classe Auth</h2>";
$auth = getAuth();

if ($auth->isLoggedIn()) {
    echo "<p style='color: green;'>✓ Utilisateur connecté via la classe Auth</p>";
    
    $currentUser = $auth->getCurrentUser();
    echo "<h3>Utilisateur actuel (via Auth):</h3>";
    echo "<ul>";
    echo "<li>ID: " . $currentUser['id'] . "</li>";
    echo "<li>Nom: " . $currentUser['full_name'] . "</li>";
    echo "<li>Rôle: <strong>" . $currentUser['role'] . "</strong></li>";
    echo "</ul>";
    
    // Test des permissions
    echo "<h3>Test des permissions:</h3>";
    if ($auth->hasPermission('Patron')) {
        echo "<p style='color: green;'>✓ L'utilisateur a les permissions de Patron</p>";
    } else {
        echo "<p style='color: red;'>✗ L'utilisateur n'a PAS les permissions de Patron</p>";
    }
    
} else {
    echo "<p style='color: red;'>✗ Utilisateur non connecté via la classe Auth</p>";
}

// Étape 3: Corriger le fichier week_management.php
echo "<h2>3. Correction du fichier week_management.php</h2>";

$week_management_file = 'panel/week_management.php';
$content = file_get_contents($week_management_file);

if ($content) {
    // Sauvegarder l'original
    $backup_file = 'panel/week_management.php.backup.' . date('Y-m-d-H-i-s');
    file_put_contents($backup_file, $content);
    echo "<p>✓ Sauvegarde créée: $backup_file</p>";
    
    // Remplacer la vérification des permissions
    $old_permission_check = "// Vérifier les permissions (seul le patron peut accéder)\nif (\$user['role'] !== 'Patron') {\n    header('Location: dashboard.php?error=access_denied');\n    exit;\n}";
    
    $new_permission_check = "// Vérifier les permissions (seul le patron peut accéder)\n\$auth = getAuth();\nif (!\$auth->hasPermission('Patron')) {\n    header('Location: dashboard.php?error=access_denied');\n    exit;\n}\n\n// Obtenir l'utilisateur actuel\n\$currentUser = \$auth->getCurrentUser();";
    
    // Remplacer aussi l'utilisation de $user['id'] par $currentUser['id']
    $content = str_replace(
        "\$result = finalizeWeekAndCreateNew(\$user['id'], \$new_week_start, \$new_week_end);",
        "\$result = finalizeWeekAndCreateNew(\$currentUser['id'], \$new_week_start, \$new_week_end);",
        $content
    );
    
    // Remplacer la vérification des permissions
    $content = str_replace(
        "if (\$user['role'] !== 'Patron') {\n    header('Location: dashboard.php?error=access_denied');\n    exit;\n}",
        "\$auth = getAuth();\nif (!\$auth->hasPermission('Patron')) {\n    header('Location: dashboard.php?error=access_denied');\n    exit;\n}\n\n// Obtenir l'utilisateur actuel\n\$currentUser = \$auth->getCurrentUser();",
        $content
    );
    
    // Écrire le fichier corrigé
    if (file_put_contents($week_management_file, $content)) {
        echo "<p style='color: green;'>✓ Fichier week_management.php corrigé avec succès</p>";
    } else {
        echo "<p style='color: red;'>✗ Erreur lors de l'écriture du fichier corrigé</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Impossible de lire le fichier week_management.php</p>";
}

// Étape 4: Vérifier les autres fichiers qui pourraient avoir le même problème
echo "<h2>4. Vérification des autres fichiers</h2>";

$files_to_check = [
    'panel/dashboard.php',
    'panel/employees.php',
    'panel/settings.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $file_content = file_get_contents($file);
        if (strpos($file_content, "\$user['role']") !== false) {
            echo "<p style='color: orange;'>⚠ Le fichier $file utilise aussi \$user['role'] et pourrait nécessiter une correction</p>";
        } else {
            echo "<p style='color: green;'>✓ Le fichier $file semble correct</p>";
        }
    }
}

// Étape 5: Test final
echo "<h2>5. Test final</h2>";
echo "<p><a href='panel/week_management.php' target='_blank'>Tester la page de gestion des semaines</a></p>";
echo "<p><a href='debug_session.php' target='_blank'>Relancer le diagnostic de session</a></p>";

echo "<h2>Résumé des corrections</h2>";
echo "<ul>";
echo "<li>✓ Remplacement de la vérification directe de \$_SESSION par la classe Auth</li>";
echo "<li>✓ Utilisation de \$auth->hasPermission('Patron') au lieu de \$user['role'] !== 'Patron'</li>";
echo "<li>✓ Utilisation de \$currentUser = \$auth->getCurrentUser() pour obtenir les données utilisateur</li>";
echo "<li>✓ Sauvegarde de l'original créée</li>";
echo "</ul>";

?>