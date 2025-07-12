# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Versioning Sémantique](https://semver.org/lang/fr/).

## [1.0.0] - 2024-12-19

### Ajouté

#### Site Vitrine
- Page d'accueil avec présentation du bar western
- Section "À propos" avec histoire et ambiance
- Galerie d'images responsive
- Carte des boissons et tarifs
- Présentation de l'équipe avec photos et rôles
- Page de contact avec formulaire fonctionnel
- Design responsive adapté mobile/tablette/desktop
- Thème western moderne avec animations CSS
- Intégration Google Fonts (Cinzel, Roboto)
- Utilisation de Font Awesome pour les icônes

#### Panel Employé
- Système d'authentification sécurisé
- Gestion des rôles hiérarchisés (CDD, CDI, Responsable, Patron)
- Tableau de bord avec statistiques en temps réel

#### Gestion des Ménages
- Prise et fin de service avec calcul automatique
- Historique complet des sessions
- Calcul automatique des salaires (60$/ménage)
- Statistiques journalières et mensuelles
- Graphiques d'évolution avec Chart.js

#### Caisse Enregistreuse
- Interface de vente intuitive
- Gestion des produits par catégories
- Système de clients fidèles avec réductions
- Calcul automatique des commissions (25%)
- Envoi automatique de tickets via Discord webhook
- Gestion des stocks en temps réel

#### Gestion Avancée
- CRUD complet pour les employés
- CRUD complet pour les clients
- Gestion de l'inventaire et des produits
- Système de catégories de produits
- Ajustement des stocks
- Historique des ventes avec filtres

#### Rapports et Analyses
- Statistiques globales du bar
- Top des produits vendus
- Classement des employés
- Évolution des ventes par jour
- Répartition par catégories
- Graphiques interactifs Chart.js

#### Système de Primes
- Attribution de primes aux employés
- Historique des primes avec filtres
- Statistiques mensuelles
- Top des employés primés

#### Configuration
- Paramètres généraux du bar
- Configuration Discord webhook
- Test de connectivité
- Informations système
- Section "À propos" du système

### Sécurité
- Protection CSRF sur tous les formulaires
- Validation et échappement des données
- Gestion sécurisée des sessions
- Contrôle d'accès basé sur les rôles
- Hashage sécurisé des mots de passe
- Protection contre les injections SQL

### Technique
- Architecture MVC modulaire
- Code PHP 8+ avec typage strict
- Base de données MySQL optimisée
- Gestion du fuseau horaire Europe/Paris
- Interface responsive Bootstrap 5
- Intégration Chart.js pour les graphiques
- Système de pagination efficace
- Recherche et filtres avancés

### Base de Données
- 8 tables relationnelles optimisées
- Index pour les performances
- Contraintes d'intégrité référentielle
- Données de démonstration incluses
- Scripts de migration automatiques

### Documentation
- README complet avec instructions
- Documentation technique détaillée
- Guide d'installation pas à pas
- Exemples de configuration
- Guide de dépannage

## [Unreleased]

### Prévu
- Système de notifications en temps réel
- Export des rapports en PDF
- Sauvegarde automatique des données
- Thèmes personnalisables
- API REST pour intégrations externes
- Module de réservations
- Système de fidélité avancé
- Gestion des fournisseurs
- Planification des horaires
- Module de formation employés

---

### Types de changements
- `Ajouté` pour les nouvelles fonctionnalités
- `Modifié` pour les changements dans les fonctionnalités existantes
- `Déprécié` pour les fonctionnalités qui seront supprimées
- `Supprimé` pour les fonctionnalités supprimées
- `Corrigé` pour les corrections de bugs
- `Sécurité` pour les vulnérabilités corrigées