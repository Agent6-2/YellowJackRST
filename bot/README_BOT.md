# Bot Discord Le Yellowjack

Ce bot Discord permet d'interagir avec le système de gestion du restaurant Le Yellowjack directement depuis Discord.

## 🚀 Fonctionnalités

Le bot offre les commandes slash suivantes :

### `/ventes [periode]`
Affiche les statistiques de ventes pour une période donnée
- **Paramètres :** `periode` (optionnel) - today, week, month
- **Exemple :** `/ventes periode:week`

### `/stats employe`
Affiche les statistiques détaillées d'un employé
- **Paramètres :** `employe` (requis) - nom ou prénom de l'employé
- **Exemple :** `/stats employe:Jean`

### `/menages [action]`
Gestion des services de ménage
- **Paramètres :** `action` (optionnel) - voir
- **Exemple :** `/menages action:voir`

### `/objectifs`
Affiche les objectifs du restaurant
- **Exemple :** `/objectifs`

### `/rapport [periode]`
Génère un rapport rapide des activités
- **Paramètres :** `periode` (optionnel) - today, week
- **Exemple :** `/rapport periode:today`

## ⚙️ Configuration

### 1. Créer une application Discord

1. Allez sur https://discord.com/developers/applications
2. Cliquez sur "New Application"
3. Donnez un nom à votre application (ex: "Le Yellowjack Bot")
4. Dans l'onglet "Bot", créez un bot et copiez le token
5. Dans l'onglet "General Information", copiez l'Application ID et la Public Key

### 2. Configurer les constantes

Ajoutez ces constantes dans votre fichier `config/database.php` :

```php
// Configuration Discord Bot
define('DISCORD_BOT_TOKEN', 'votre_bot_token_ici');
define('DISCORD_APPLICATION_ID', 'votre_application_id_ici');
define('DISCORD_CLIENT_PUBLIC_KEY', 'votre_public_key_ici');
```

### 3. Configurer l'URL d'interaction

1. Dans l'onglet "General Information" de votre application Discord
2. Définissez l'"Interactions Endpoint URL" vers :
   ```
   https://votre-domaine.com/bot/discord_bot.php
   ```

### 4. Enregistrer les commandes

Exécutez le script d'enregistrement des commandes :

```bash
php bot/register_commands.php
```

### 5. Inviter le bot sur votre serveur

1. Dans l'onglet "OAuth2" > "URL Generator"
2. Sélectionnez les scopes : `bot` et `applications.commands`
3. Sélectionnez les permissions nécessaires
4. Utilisez l'URL générée pour inviter le bot

## 🔧 Structure du code

### `discord_bot.php`
Fichier principal du bot qui gère :
- Vérification des signatures Discord
- Routage des commandes
- Interaction avec la base de données
- Génération des réponses embed

### `register_commands.php`
Script utilitaire pour enregistrer les commandes slash auprès de l'API Discord

## 🛡️ Sécurité

- Le bot vérifie la signature de chaque requête Discord
- Toutes les interactions sont authentifiées
- Les données sensibles ne sont jamais exposées
- Les requêtes SQL utilisent des requêtes préparées

## 🐛 Dépannage

### Le bot ne répond pas
1. Vérifiez que l'URL d'interaction est correctement configurée
2. Vérifiez que les constantes Discord sont bien définies
3. Consultez les logs du serveur web

### Erreur "Signature invalide"
1. Vérifiez que `DISCORD_CLIENT_PUBLIC_KEY` est correcte
2. Assurez-vous que l'extension `sodium` est installée en PHP

### Commandes non reconnues
1. Exécutez à nouveau `register_commands.php`
2. Attendez quelques minutes pour la propagation

## 📝 Logs

Les erreurs sont enregistrées dans les logs PHP. Pour déboguer :

```php
error_log('Debug: ' . print_r($data, true));
```

## 🔄 Mise à jour

Pour ajouter de nouvelles commandes :

1. Modifiez le tableau `$commands` dans `register_commands.php`
2. Ajoutez la logique correspondante dans `discord_bot.php`
3. Exécutez `register_commands.php`

## 📞 Support

Pour toute question ou problème, consultez :
- La documentation Discord : https://discord.com/developers/docs
- Les logs du serveur web
- Le code source du bot