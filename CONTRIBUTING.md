# Guide de Contribution

Merci de votre intérêt pour contribuer au projet **Le Yellowjack** ! Ce guide vous aidera à comprendre comment participer au développement de ce système de gestion de bar western pour GTA V / FiveM.

## Table des Matières

- [Code de Conduite](#code-de-conduite)
- [Comment Contribuer](#comment-contribuer)
- [Signaler des Bugs](#signaler-des-bugs)
- [Proposer des Fonctionnalités](#proposer-des-fonctionnalités)
- [Développement](#développement)
- [Standards de Code](#standards-de-code)
- [Tests](#tests)
- [Documentation](#documentation)

## Code de Conduite

En participant à ce projet, vous acceptez de respecter notre code de conduite :

- Soyez respectueux et inclusif
- Acceptez les critiques constructives
- Concentrez-vous sur ce qui est le mieux pour la communauté
- Montrez de l'empathie envers les autres membres

## Comment Contribuer

### 1. Fork du Projet

```bash
git clone https://github.com/votre-username/yellowjack.git
cd yellowjack
```

### 2. Créer une Branche

```bash
git checkout -b feature/nouvelle-fonctionnalite
# ou
git checkout -b fix/correction-bug
```

### 3. Faire vos Modifications

- Suivez les [standards de code](#standards-de-code)
- Ajoutez des tests si nécessaire
- Mettez à jour la documentation

### 4. Commit et Push

```bash
git add .
git commit -m "feat: ajouter nouvelle fonctionnalité"
git push origin feature/nouvelle-fonctionnalite
```

### 5. Créer une Pull Request

- Décrivez clairement vos modifications
- Référencez les issues liées
- Ajoutez des captures d'écran si pertinent

## Signaler des Bugs

Avant de signaler un bug :

1. Vérifiez que le bug n'a pas déjà été signalé
2. Assurez-vous d'utiliser la dernière version
3. Testez avec les données par défaut

### Template de Bug Report

```markdown
**Description du Bug**
Description claire et concise du problème.

**Étapes pour Reproduire**
1. Aller à '...'
2. Cliquer sur '....'
3. Faire défiler jusqu'à '....'
4. Voir l'erreur

**Comportement Attendu**
Description de ce qui devrait se passer.

**Captures d'Écran**
Si applicable, ajoutez des captures d'écran.

**Environnement**
- OS: [ex. Windows 10]
- Navigateur: [ex. Chrome 91]
- Version PHP: [ex. 8.1]
- Version MySQL: [ex. 8.0]

**Contexte Supplémentaire**
Toute autre information pertinente.
```

## Proposer des Fonctionnalités

### Template de Feature Request

```markdown
**Problème à Résoudre**
Description claire du problème que cette fonctionnalité résoudrait.

**Solution Proposée**
Description claire de ce que vous aimeriez voir implémenté.

**Alternatives Considérées**
Description des solutions alternatives que vous avez considérées.

**Contexte Supplémentaire**
Toute autre information ou capture d'écran pertinente.
```

## Développement

### Prérequis

- PHP 8.0+
- MySQL 8.0+
- Serveur web (Apache/Nginx)
- Git

### Installation pour le Développement

1. **Cloner le projet**
   ```bash
   git clone https://github.com/votre-username/yellowjack.git
   cd yellowjack
   ```

2. **Configurer la base de données**
   ```bash
   # Importer le schéma
   mysql -u username -p database_name < database/schema.sql
   
   # Importer les données de test
   mysql -u username -p database_name < database/sample_data.sql
   ```

3. **Configurer l'environnement**
   ```bash
   cp config/database.example.php config/database.php
   # Éditer config/database.php avec vos paramètres
   ```

4. **Démarrer le serveur de développement**
   ```bash
   php -S localhost:8000
   ```

### Structure du Projet

```
yellowjack/
├── assets/          # CSS, JS, images
├── config/          # Configuration
├── database/        # Scripts SQL
├── includes/        # Classes et fonctions
├── panel/           # Panel employé
├── public/          # Site vitrine
└── uploads/         # Fichiers uploadés
```

## Standards de Code

### PHP

- **PSR-12** pour le style de code
- **PHP 8.0+** avec typage strict
- **Camel Case** pour les variables et méthodes
- **Pascal Case** pour les classes
- **Documentation** avec PHPDoc

```php
<?php
declare(strict_types=1);

/**
 * Classe pour gérer les employés
 */
class EmployeeManager
{
    /**
     * Récupère un employé par son ID
     */
    public function getEmployeeById(int $employeeId): ?Employee
    {
        // Implementation
    }
}
```

### HTML/CSS

- **Indentation** : 2 espaces
- **Classes CSS** : kebab-case
- **IDs** : camelCase
- **Responsive** : Mobile-first

### JavaScript

- **ES6+** syntax
- **Camel Case** pour variables et fonctions
- **Const/Let** au lieu de var
- **Documentation** avec JSDoc

### Base de Données

- **Noms de tables** : snake_case
- **Noms de colonnes** : snake_case
- **Clés primaires** : id
- **Clés étrangères** : table_id

## Tests

### Tests Manuels

1. **Fonctionnalités de base**
   - Connexion/déconnexion
   - Navigation entre pages
   - CRUD operations

2. **Permissions**
   - Accès selon les rôles
   - Restrictions appropriées

3. **Responsive**
   - Mobile (320px+)
   - Tablette (768px+)
   - Desktop (1024px+)

### Tests Automatisés (Futur)

- PHPUnit pour les tests unitaires
- Selenium pour les tests d'interface
- Tests d'intégration API

## Documentation

### Code

- **Commentaires** en français
- **PHPDoc** pour toutes les méthodes publiques
- **README** à jour
- **CHANGELOG** pour chaque version

### Fonctionnalités

- **Captures d'écran** pour les nouvelles interfaces
- **Guide utilisateur** si nécessaire
- **Documentation technique** pour les APIs

## Conventions de Commit

Utilisez les préfixes suivants :

- `feat:` Nouvelle fonctionnalité
- `fix:` Correction de bug
- `docs:` Documentation
- `style:` Formatage, point-virgules manquants, etc.
- `refactor:` Refactoring du code
- `test:` Ajout de tests
- `chore:` Maintenance

Exemples :
```
feat: ajouter système de notifications
fix: corriger calcul des commissions
docs: mettre à jour le README
style: formater le code selon PSR-12
```

## Processus de Review

1. **Vérification automatique**
   - Code style
   - Tests passants
   - Pas de conflits

2. **Review manuelle**
   - Logique métier
   - Sécurité
   - Performance
   - Documentation

3. **Approbation**
   - Au moins 1 approbation requise
   - Tous les commentaires résolus

## Ressources

- [Documentation PHP](https://www.php.net/docs.php)
- [Bootstrap 5](https://getbootstrap.com/docs/5.0/)
- [Chart.js](https://www.chartjs.org/docs/)
- [Font Awesome](https://fontawesome.com/docs)

## Questions ?

N'hésitez pas à :

- Ouvrir une issue pour poser des questions
- Rejoindre les discussions
- Contacter les mainteneurs

---

Merci de contribuer au projet **Le Yellowjack** ! 🤠