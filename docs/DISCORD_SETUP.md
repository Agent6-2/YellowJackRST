# 🤖 Configuration Discord Webhook - Le Yellowjack

## 📋 Vue d'ensemble

Le système de webhook Discord permet d'envoyer automatiquement des notifications dans votre serveur Discord pour :
- ✅ Nouvelles ventes
- 🎯 Objectifs atteints
- ⚠️ Erreurs système
- 📊 Résumés hebdomadaires
- 🎉 Événements spéciaux

## 🚀 Configuration rapide

### Étape 1 : Créer un webhook Discord

1. **Ouvrez votre serveur Discord**
2. **Paramètres du serveur** → **Intégrations** → **Webhooks**
3. **Créer un webhook**
4. **Configurez le webhook :**
   - Nom : `Le Yellowjack Bot`
   - Canal : Choisissez le canal pour les notifications
   - Avatar : Optionnel (logo du restaurant)
5. **Copiez l'URL du webhook**

### Étape 2 : Configurer l'application

#### Option A : Via l'interface web (Recommandé)
1. Connectez-vous en tant qu'administrateur
2. Allez dans **Configuration Discord** (`/panel/discord_config.php`)
3. Collez l'URL du webhook
4. Testez la connexion
5. Sauvegardez

#### Option B : Modification manuelle
1. Ouvrez `config/database.php`
2. Modifiez la ligne :
   ```php
   define('DISCORD_WEBHOOK_URL', 'VOTRE_URL_WEBHOOK_ICI');
   ```
3. Sauvegardez le fichier

### Étape 3 : Test

1. Utilisez l'interface de test dans **Configuration Discord**
2. Ou exécutez : `examples/discord_integration_example.php`
3. Vérifiez que le message apparaît dans Discord

## 📁 Structure des fichiers

```
YellowJack/
├── includes/
│   └── discord_webhook.php          # Classe principale
├── panel/
│   └── discord_config.php           # Interface de configuration
├── examples/
│   └── discord_integration_example.php # Exemples d'utilisation
└── docs/
    └── DISCORD_SETUP.md             # Ce fichier
```

## 🔧 Utilisation dans le code

### Notification de vente simple
```php
// Après l'enregistrement d'une vente
notifyDiscordSale($sale_id);
```

### Notification personnalisée
```php
$webhook = getDiscordWebhook();
$webhook->sendMessage("Message simple", "Bot Name");
```

### Notification avec embed riche
```php
$webhook = getDiscordWebhook();
$webhook->sendEmbed(
    "Titre",
    "Description",
    0x00ff00, // Couleur verte
    [
        [
            'name' => 'Champ 1',
            'value' => 'Valeur 1',
            'inline' => true
        ]
    ]
);
```

## 📊 Types de notifications

### 💰 Vente
- **Déclencheur :** Nouvelle vente enregistrée
- **Contenu :** Ticket #, vendeur, client, montant, commission
- **Couleur :** Vert (succès)

### 🎯 Objectif atteint
- **Déclencheur :** Employé atteint un objectif
- **Contenu :** Nom employé, type d'objectif, montant
- **Couleur :** Or (réussite)

### ⚠️ Erreur système
- **Déclencheur :** Erreur critique
- **Contenu :** Message d'erreur, contexte
- **Couleur :** Rouge (erreur)

### 📊 Résumé hebdomadaire
- **Déclencheur :** Fin de semaine
- **Contenu :** CA, nombre de ventes, top vendeur
- **Couleur :** Bleu (information)

## 🎨 Personnalisation

### Modifier les couleurs
```php
// Dans discord_webhook.php
$color = 0x00ff00; // Vert
$color = 0xff0000; // Rouge
$color = 0x0099ff; // Bleu
$color = 0xffd700; // Or
```

### Ajouter des champs personnalisés
```php
$fields[] = [
    'name' => 'Nom du champ',
    'value' => 'Valeur du champ',
    'inline' => true // ou false
];
```

### Modifier le nom du bot
```php
$webhook = new DiscordWebhook($url);
$webhook->sendMessage($message, "Nom personnalisé");
```

## 🔒 Sécurité

### Bonnes pratiques
- ✅ Ne partagez jamais l'URL du webhook
- ✅ Utilisez un canal privé pour les notifications sensibles
- ✅ Limitez les permissions du webhook
- ✅ Surveillez les logs d'erreur

### Gestion des erreurs
```php
try {
    $result = $webhook->sendMessage($message);
    if (!$result) {
        error_log("Échec envoi Discord");
    }
} catch (Exception $e) {
    error_log("Erreur Discord: " . $e->getMessage());
}
```

## 🐛 Dépannage

### Problèmes courants

#### ❌ "Webhook URL non configurée"
- **Solution :** Vérifiez `DISCORD_WEBHOOK_URL` dans `config/database.php`

#### ❌ "Test échoué"
- **Causes possibles :**
  - URL incorrecte
  - Webhook supprimé dans Discord
  - Problème de réseau
  - Extension cURL non installée

#### ❌ "Erreur 401/403"
- **Solution :** Vérifiez les permissions du webhook dans Discord

#### ❌ "Erreur 404"
- **Solution :** Le webhook a été supprimé, créez-en un nouveau

### Vérification de l'installation
```php
// Test rapide
if (function_exists('curl_init')) {
    echo "✅ cURL disponible";
} else {
    echo "❌ cURL requis";
}

if (!empty(DISCORD_WEBHOOK_URL)) {
    echo "✅ Webhook configuré";
} else {
    echo "❌ Webhook non configuré";
}
```

## 📈 Intégrations avancées

### Notification conditionnelle
```php
// Notifier seulement pour les grosses ventes
if ($sale_amount > 500) {
    notifyDiscordSale($sale_id);
}
```

### Notification avec mention
```php
$webhook->sendMessage(
    "🚨 Vente importante ! <@USER_ID>",
    "Le Yellowjack Alert"
);
```

### Webhook multiple
```php
// Webhook pour les ventes
$sales_webhook = new DiscordWebhook($sales_url);

// Webhook pour les erreurs
$error_webhook = new DiscordWebhook($error_url);
```

## 📞 Support

Pour toute question ou problème :
1. Consultez les logs d'erreur PHP
2. Testez avec `discord_integration_example.php`
3. Vérifiez la configuration dans Discord
4. Contactez l'équipe de développement

---

**Développé pour Le Yellowjack** 🟡
*Version 1.0 - Système de notification Discord*