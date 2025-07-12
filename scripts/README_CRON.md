# Configuration du Cron Job pour la Réinitialisation Hebdomadaire

## Description
Le script `weekly_reset.php` doit être exécuté automatiquement chaque vendredi à 00h00 pour :
- Finaliser les performances de la semaine précédente
- Initialiser les performances pour la nouvelle semaine
- Nettoyer les anciennes données (plus de 12 semaines)

## Configuration sur Linux/Unix

### 1. Ouvrir le crontab
```bash
crontab -e
```

### 2. Ajouter la ligne suivante
```bash
# Réinitialisation hebdomadaire des performances - Chaque vendredi à 00h00
0 0 * * 5 /usr/bin/php /chemin/vers/YellowJack/scripts/weekly_reset.php
```

### 3. Vérifier la configuration
```bash
crontab -l
```

## Configuration sur Windows

### 1. Utiliser le Planificateur de tâches Windows
1. Ouvrir "Planificateur de tâches" (Task Scheduler)
2. Cliquer sur "Créer une tâche de base"
3. Nom : "YellowJack Weekly Reset"
4. Déclencheur : "Hebdomadaire"
5. Jour : "Vendredi"
6. Heure : "00:00:00"
7. Action : "Démarrer un programme"
8. Programme : `php.exe`
9. Arguments : `C:\chemin\vers\YellowJack\scripts\weekly_reset.php`

### 2. Alternative avec PowerShell
Créer un script PowerShell `weekly_reset.ps1` :
```powershell
# weekly_reset.ps1
cd "C:\chemin\vers\YellowJack\scripts"
php weekly_reset.php
```

Puis programmer ce script dans le Planificateur de tâches.

## Configuration avec XAMPP/WAMP

### Pour XAMPP
```bash
# Chemin typique vers PHP
0 0 * * 5 C:\xampp\php\php.exe C:\xampp\htdocs\YellowJack\scripts\weekly_reset.php
```

### Pour WAMP
```bash
# Chemin typique vers PHP
0 0 * * 5 C:\wamp64\bin\php\php8.x.x\php.exe C:\wamp64\www\YellowJack\scripts\weekly_reset.php
```

## Vérification et Logs

### Fichiers de logs créés
- `../logs/weekly_reset_YYYY-MM.log` : Rapports mensuels des réinitialisations
- `../logs/weekly_reset_errors_YYYY-MM.log` : Erreurs rencontrées

### Test manuel
Pour tester le script manuellement :
```bash
php /chemin/vers/YellowJack/scripts/weekly_reset.php
```

**Note :** Le script vérifie qu'il est exécuté un vendredi. Pour tester un autre jour, commentez temporairement cette vérification.

## Surveillance

### Vérifier que le cron fonctionne
1. Consulter les logs système : `/var/log/cron` (Linux)
2. Vérifier les fichiers de logs de l'application
3. Contrôler dans la base de données que les performances sont bien finalisées

### Notifications (optionnel)
Pour recevoir des notifications par email en cas d'erreur, modifier le script pour inclure :
```php
// En cas d'erreur
mail('admin@yellowjack.com', 'Erreur Weekly Reset', $error_message);
```

## Dépannage

### Problèmes courants
1. **Permissions** : S'assurer que le script a les droits d'écriture sur le dossier `logs`
2. **Chemin PHP** : Vérifier que le chemin vers PHP est correct
3. **Base de données** : S'assurer que la connexion à la base fonctionne
4. **Timezone** : Vérifier que le serveur est configuré sur le bon fuseau horaire

### Commandes utiles
```bash
# Voir les tâches cron actives
crontab -l

# Voir les logs cron (Linux)
tail -f /var/log/cron

# Tester la connexion PHP
php -r "echo 'PHP fonctionne';";
```

## Sécurité

- Le script ne doit être accessible que par les administrateurs système
- Protéger le dossier `scripts` avec un `.htaccess` si accessible via web
- Surveiller les logs pour détecter les tentatives d'accès non autorisées

## Maintenance

- Vérifier mensuellement que les logs ne deviennent pas trop volumineux
- Archiver ou supprimer les anciens logs si nécessaire
- Tester périodiquement l'exécution manuelle du script