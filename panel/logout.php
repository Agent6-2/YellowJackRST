<?php
/**
 * Page de déconnexion - Panel Employé Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Déconnexion
logout();

// Redirection vers la page de connexion avec message
header('Location: login.php?message=disconnected');
exit;
?>