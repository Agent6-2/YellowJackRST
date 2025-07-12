# 🤠 Le Yellowjack - Système de Gestion de Bar Western

## 📋 Description

Le Yellowjack est un système de gestion complet pour un bar western dans l'univers GTA V / FiveM. Il comprend un site vitrine public et un panel de gestion privé pour les employés avec des rôles hiérarchisés.

## 🌟 Fonctionnalités

### Site Vitrine Public
- **Design Western Moderne** : Interface responsive avec thème western authentique
- **Présentation du Bar** : Histoire, ambiance et localisation
- **Menu et Tarifs** : Affichage des boissons et snacks disponibles
- **Équipe** : Présentation des rôles et responsabilités
- **Contact** : Informations de localisation et contact

### Panel Employé (Accès Restreint)

#### 🔐 Système de Rôles Hiérarchisés
- **CDD** : Accès aux ménages uniquement
- **CDI** : Ménages + Caisse enregistreuse
- **Responsable** : Gestion complète + Rapports
- **Patron** : Accès total + Configuration système

#### 🧹 Gestion des Ménages (CDD/CDI)
- Prise et fin de service avec horodatage automatique
- Calcul automatique de la durée de service
- Déclaration du nombre de ménages effectués
- Calcul du salaire (60$/ménage par défaut)
- Historique complet des sessions
- Statistiques personnelles et graphiques

#### 💰 Caisse Enregistreuse (CDI+)
- Interface de vente intuitive par catégories
- Gestion des stocks en temps réel
- Système de clients fidèles avec réductions
- Calcul automatique des commissions (25% par défaut)
- Tickets de caisse envoyés via webhook Discord
- Historique des ventes avec filtres avancés

#### 👥 Gestion Avancée (Responsables/Patrons)
- **Employés** : Ajout, modification, suspension/réintégration
- **Clients** : Gestion de la base clients et fidélité
- **Inventaire** : Gestion des produits, catégories et stocks
- **Rapports** : Analyses détaillées avec graphiques
- **Primes** : Attribution et suivi des primes employés
- **Configuration** : Paramètres système et intégration Discord

## 🛠️ Technologies Utilisées

- **Backend** : PHP 8+ avec PDO
- **Base de données** : MySQL
- **Frontend** : Bootstrap 5, Font Awesome, Google Fonts
- **Graphiques** : Chart.js
- **Sécurité** : Protection CSRF, sessions sécurisées
- **Intégrations** : Webhook Discord pour notifications

## 📦 Installation

### Prérequis
- Serveur web (Apache/Nginx)
- PHP 8.0 ou supérieur
- MySQL 5.7 ou supérieur
- Extensions PHP : PDO, PDO_MySQL, cURL, JSON

### Étapes d'installation

1. **Cloner le repository**
   ```bash
   git clone https://github.com/votre-username/yellowjack.git
   cd yellowjack
   ```

2. **Configuration de la base de données**
   - Créer une base de données MySQL
   - Importer le fichier `database/schema.sql`
   - Modifier les paramètres dans `config/database.php`

3. **Configuration du serveur web**
   - Pointer le document root vers le dossier du projet
   - Activer la réécriture d'URL si nécessaire

4. **Configuration Discord (optionnel)**
   - Créer un webhook Discord
   - Configurer l'URL dans le panel d'administration

## 🔧 Configuration

### Base de données
Modifiez les paramètres dans `config/database.php` :
```php
define('DB_HOST', 'votre_host');
define('DB_PORT', '3306');
define('DB_NAME', 'votre_base');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');
```

### Paramètres par défaut
- **Taux de ménage** : 60$ par ménage
- **Commission** : 25% sur les ventes
- **Fuseau horaire** : Europe/Paris
- **Réduction fidélité** : 10%

## 👤 Comptes par défaut

### Administrateur
- **Email** : admin@yellowjack.com
- **Mot de passe** : admin123
- **Rôle** : Patron

### Clients par défaut
- **Client Anonyme** : Pour les ventes sans identification
- **Client Fidèle Test** : Pour tester les réductions fidélité

## 📊 Structure de la base de données

### Tables principales
- `users` : Employés et leurs informations
- `cleaning_sessions` : Sessions de ménage
- `products` : Produits et inventaire
- `categories` : Catégories de produits
- `customers` : Base clients
- `sales` : Ventes effectuées
- `sale_items` : Détails des ventes
- `bonuses` : Primes attribuées
- `settings` : Paramètres système

## 🔒 Sécurité

- **Authentification** : Sessions sécurisées avec timeout
- **Autorisation** : Système de permissions basé sur les rôles
- **Protection CSRF** : Tokens de sécurité sur tous les formulaires
- **Validation** : Validation côté serveur de toutes les données
- **Échappement** : Protection contre les injections XSS

## 🎨 Interface utilisateur

### Site vitrine
- Design responsive adapté mobile/desktop
- Thème western avec couleurs authentiques
- Navigation fluide avec animations
- Optimisé pour l'expérience utilisateur

### Panel employé
- Interface moderne et intuitive
- Tableaux de bord personnalisés par rôle
- Graphiques interactifs pour les statistiques
- Navigation latérale contextuelle

## 📈 Fonctionnalités avancées

### Rapports et analyses
- Évolution des ventes par période
- Classements des employés
- Analyses de performance
- Statistiques en temps réel

### Intégration Discord
- Notifications automatiques des ventes
- Tickets de caisse formatés
- Test de connexion intégré

### Gestion des stocks
- Alertes de stock faible
- Ajustements d'inventaire
- Historique des mouvements

## 🚀 Déploiement

### Hébergement recommandé
- **Webstrator** : Configuration testée et optimisée
- **Serveur dédié** : Pour de meilleures performances
- **VPS** : Alternative économique

### Optimisations
- Cache des requêtes fréquentes
- Compression des assets
- Optimisation des images

## 🐛 Dépannage

### Problèmes courants

**Erreur de connexion à la base de données**
- Vérifier les paramètres dans `config/database.php`
- Contrôler les permissions MySQL
- Tester la connectivité réseau

**Problème de permissions**
- Vérifier les rôles utilisateur en base
- Contrôler les sessions PHP
- Effacer le cache navigateur

**Webhook Discord non fonctionnel**
- Vérifier l'URL du webhook
- Tester la connectivité avec l'outil intégré
- Contrôler les permissions du bot Discord

## 📝 Changelog

### Version 1.0.0 (2024)
- ✅ Système complet de gestion des ménages
- ✅ Caisse enregistreuse avec gestion stocks
- ✅ Panel d'administration multi-rôles
- ✅ Site vitrine responsive
- ✅ Intégration Discord
- ✅ Système de primes
- ✅ Rapports et analyses

## 🤝 Contribution

Les contributions sont les bienvenues ! Pour contribuer :

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commit les changements (`git commit -am 'Ajout nouvelle fonctionnalité'`)
4. Push vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Créer une Pull Request

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 📞 Support

Pour toute question ou problème :
- Créer une issue sur GitHub
- Consulter la documentation
- Contacter l'équipe de développement

## 🙏 Remerciements

- **Bootstrap** pour le framework CSS
- **Font Awesome** pour les icônes
- **Chart.js** pour les graphiques
- **Google Fonts** pour les polices
- **Discord** pour l'API webhook

---

**🤠 Développé avec passion pour l'univers western de GTA V / FiveM**

*Le Yellowjack - Où l'ouest rencontre la modernité*