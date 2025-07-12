# Politique de Sécurité

## Versions Supportées

Nous prenons la sécurité au sérieux. Voici les versions actuellement supportées avec des mises à jour de sécurité :

| Version | Supportée          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Signaler une Vulnérabilité

Si vous découvrez une vulnérabilité de sécurité, veuillez **NE PAS** créer d'issue publique. Suivez plutôt cette procédure :

### 1. Contact Privé

Envoyez un email à : **security@yellowjack-project.com** (ou créez une issue privée)

### 2. Informations à Inclure

- **Description** détaillée de la vulnérabilité
- **Étapes** pour reproduire le problème
- **Impact** potentiel
- **Versions** affectées
- **Preuve de concept** (si applicable)

### 3. Processus de Traitement

1. **Accusé de réception** sous 48h
2. **Évaluation initiale** sous 7 jours
3. **Correction** selon la criticité :
   - Critique : 24-48h
   - Élevée : 7 jours
   - Moyenne : 30 jours
   - Faible : 90 jours
4. **Publication** du correctif
5. **Divulgation coordonnée** après correction

## Mesures de Sécurité Implémentées

### Authentification

- **Hashage sécurisé** des mots de passe (password_hash/password_verify)
- **Sessions sécurisées** avec regeneration d'ID
- **Timeout automatique** des sessions
- **Protection contre le brute force** (limitation des tentatives)

### Protection CSRF

- **Tokens CSRF** sur tous les formulaires
- **Validation côté serveur** obligatoire
- **Expiration** des tokens

### Validation des Données

- **Échappement** de toutes les sorties HTML
- **Validation stricte** des entrées utilisateur
- **Typage fort** PHP 8+
- **Requêtes préparées** pour la base de données

### Contrôle d'Accès

- **Système de rôles** hiérarchisé
- **Vérification des permissions** sur chaque action
- **Isolation des données** par rôle
- **Principe du moindre privilège**

### Configuration Sécurisée

- **Fichiers de configuration** exclus du versioning
- **Variables d'environnement** pour les données sensibles
- **Headers de sécurité** appropriés
- **Désactivation** des fonctions PHP dangereuses

## Bonnes Pratiques pour les Développeurs

### Code Sécurisé

```php
// ✅ Bon : Échappement des données
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// ❌ Mauvais : Sortie directe
echo $userInput;

// ✅ Bon : Requête préparée
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);

// ❌ Mauvais : Concaténation directe
$query = "SELECT * FROM users WHERE id = " . $userId;
```

### Validation des Entrées

```php
// ✅ Validation stricte
function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ✅ Nettoyage des données
function sanitizeString(string $input): string {
    return trim(strip_tags($input));
}
```

### Gestion des Erreurs

```php
// ✅ Bon : Logs sécurisés
error_log("Tentative de connexion échouée pour l'utilisateur: " . $username);

// ❌ Mauvais : Exposition d'informations sensibles
echo "Erreur SQL: " . $e->getMessage();
```

## Configuration Serveur Recommandée

### PHP (php.ini)

```ini
; Désactiver l'affichage des erreurs en production
display_errors = Off
log_errors = On

; Désactiver les fonctions dangereuses
disable_functions = exec,passthru,shell_exec,system,proc_open,popen

; Limiter les uploads
file_uploads = On
upload_max_filesize = 2M
max_file_uploads = 5

; Sessions sécurisées
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

### Apache (.htaccess)

```apache
# Désactiver l'indexation des répertoires
Options -Indexes

# Protéger les fichiers sensibles
<Files "config/*.php">
    Require all denied
</Files>

# Headers de sécurité
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

### MySQL

```sql
-- Utilisateur avec privilèges minimaux
CREATE USER 'yellowjack_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON yellowjack_db.* TO 'yellowjack_user'@'localhost';
FLUSH PRIVILEGES;
```

## Checklist de Sécurité

### Avant Déploiement

- [ ] Mots de passe par défaut changés
- [ ] Fichiers de configuration sécurisés
- [ ] Permissions fichiers correctes (644/755)
- [ ] Base de données avec utilisateur dédié
- [ ] HTTPS configuré
- [ ] Logs d'erreur activés
- [ ] Sauvegardes automatiques configurées

### Tests de Sécurité

- [ ] Test d'injection SQL
- [ ] Test XSS
- [ ] Test CSRF
- [ ] Test de contrôle d'accès
- [ ] Test de force brute
- [ ] Test d'upload de fichiers

## Incidents de Sécurité

### En Cas de Compromission

1. **Isoler** le système affecté
2. **Changer** tous les mots de passe
3. **Analyser** les logs
4. **Corriger** la vulnérabilité
5. **Restaurer** depuis une sauvegarde saine
6. **Documenter** l'incident
7. **Notifier** les utilisateurs si nécessaire

### Logs à Surveiller

- Tentatives de connexion échouées
- Accès aux pages d'administration
- Erreurs SQL
- Uploads de fichiers
- Modifications de configuration

## Ressources de Sécurité

### Documentation

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Guide](https://www.php.net/manual/en/security.php)
- [MySQL Security](https://dev.mysql.com/doc/refman/8.0/en/security.html)

### Outils de Test

- [OWASP ZAP](https://www.zaproxy.org/)
- [SQLMap](http://sqlmap.org/)
- [Burp Suite](https://portswigger.net/burp)

## Mises à Jour de Sécurité

Nous publions des mises à jour de sécurité selon ce calendrier :

- **Critique** : Immédiatement
- **Élevée** : Dans la semaine
- **Moyenne** : Prochaine version mineure
- **Faible** : Prochaine version majeure

### S'abonner aux Alertes

- Watch ce repository sur GitHub
- Suivre les releases
- S'abonner à la mailing list sécurité

## Contact

Pour toute question de sécurité :

- **Email** : security@yellowjack-project.com
- **PGP Key** : [Clé publique]
- **Response Time** : 48h maximum

---

**Note** : Cette politique de sécurité est mise à jour régulièrement. Dernière mise à jour : Décembre 2024