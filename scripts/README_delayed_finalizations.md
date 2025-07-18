# Système de Finalisations Différées

## Description

Ce système permet de différer l'application des effets de finalisation d'une semaine d'une heure après la demande de finalisation. Pendant cette période d'attente, les ventes et le ménage ne sont pas encore pris en compte dans les calculs finaux.

## Fonctionnement

1. **Finalisation demandée** : L'utilisateur clique sur "Finaliser la semaine"
2. **Programmation** : La finalisation est programmée pour s'exécuter dans 1 heure
3. **Attente** : Pendant 1 heure, aucun effet n'est appliqué
4. **Exécution automatique** : Après 1 heure, les calculs sont appliqués automatiquement

## Fichiers du système

### Scripts principaux
- `process_delayed_finalizations.php` : Script qui traite les finalisations en attente
- `cron_delayed_finalizations.bat` : Script batch pour l'exécution automatique

### Interface utilisateur
- `check_delayed_finalizations.php` : API pour vérifier le statut des finalisations
- Modifications dans `taxes.php` : Affichage en temps réel du statut

### Base de données
- Table `delayed_finalizations` : Stocke les finalisations programmées
- `create_delayed_finalization_table.php` : Script de création de la table

## Configuration du planificateur de tâches Windows

### Étape 1 : Ouvrir le Planificateur de tâches
1. Appuyez sur `Win + R`
2. Tapez `taskschd.msc` et appuyez sur Entrée

### Étape 2 : Créer une nouvelle tâche
1. Clic droit sur "Bibliothèque du Planificateur de tâches"
2. Sélectionnez "Créer une tâche de base..."

### Étape 3 : Configuration de la tâche
- **Nom** : "YellowJack - Finalisations Différées"
- **Description** : "Traitement automatique des finalisations différées toutes les minutes"

### Étape 4 : Déclencheur
- **Type** : "Quotidienne"
- **Heure de début** : 00:00:00
- **Répéter la tâche toutes les** : 1 minute
- **Pendant** : 1 jour

### Étape 5 : Action
- **Action** : "Démarrer un programme"
- **Programme/script** : `d:\trea project\YellowJack\scripts\cron_delayed_finalizations.bat`
- **Commencer dans** : `d:\trea project\YellowJack\scripts`

### Étape 6 : Conditions
- Décochez "Démarrer la tâche seulement si l'ordinateur est alimenté sur secteur"
- Décochez "Arrêter si l'ordinateur bascule sur l'alimentation par batterie"

### Étape 7 : Paramètres
- Cochez "Autoriser l'exécution de la tâche à la demande"
- Cochez "Exécuter la tâche dès que possible après un démarrage planifié manqué"
- **Si la tâche est déjà en cours d'exécution** : "Ne pas démarrer une nouvelle instance"

## Vérification du fonctionnement

### Logs
Les logs sont stockés dans `d:\trea project\YellowJack\scripts\delayed_finalizations.log`

### Interface web
La page `taxes.php` affiche automatiquement :
- Les finalisations en attente
- Le temps restant avant exécution
- Le statut en temps réel

### Test manuel
Pour tester manuellement le script :
```bash
cd "d:\trea project\YellowJack\scripts"
php process_delayed_finalizations.php
```

## Dépannage

### Problèmes courants
1. **PHP non trouvé** : Vérifiez que PHP est dans le PATH système
2. **Permissions** : Assurez-vous que le script a les droits d'écriture
3. **Base de données** : Vérifiez que la table `delayed_finalizations` existe

### Vérification de la configuration
1. Testez le script manuellement
2. Vérifiez les logs pour les erreurs
3. Consultez l'historique du Planificateur de tâches

## Sécurité

- Les finalisations ne peuvent pas être annulées une fois programmées
- Seules les semaines non finalisées peuvent être programmées
- Le système évite les doublons de finalisations