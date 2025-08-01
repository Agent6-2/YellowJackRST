<?php
/**
 * Exemple de configuration pour le bot Discord
 * 
 * Copiez ce contenu dans votre fichier config/database.php
 * et remplacez les valeurs par vos vraies clés Discord
 */

// ========================================
// CONFIGURATION DISCORD BOT
// ========================================

// 1. Token du bot Discord
// Obtenez-le depuis https://discord.com/developers/applications
// Onglet "Bot" > Token
define('DISCORD_BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');

// 2. ID de l'application Discord
// Obtenez-le depuis https://discord.com/developers/applications
// Onglet "General Information" > Application ID
define('DISCORD_APPLICATION_ID', 'YOUR');

// 3. Clé publique de l'application
// Obtenez-la depuis https://discord.com/developers/applications
// Onglet "General Information" > Public Key
define('DISCORD_CLIENT_PUBLIC_KEY', 'YOUR');

// ========================================
// ÉTAPES DE CONFIGURATION
// ========================================

/*

1. CRÉER UNE APPLICATION DISCORD
   - Allez sur https://discord.com/developers/applications
   - Cliquez sur "New Application"
   - Donnez un nom (ex: "Le Yellowjack Bot")

2. CRÉER UN BOT
   - Dans votre application, allez à l'onglet "Bot"
   - Cliquez sur "Add Bot"
   - Copiez le Token et mettez-le dans DISCORD_BOT_TOKEN

3. CONFIGURER LES PERMISSIONS
   - Dans l'onglet "Bot", activez :
     * MESSAGE CONTENT INTENT (si nécessaire)
     * SERVER MEMBERS INTENT (si nécessaire)

4. CONFIGURER L'URL D'INTERACTION
   - Dans l'onglet "General Information"
   - Définissez "Interactions Endpoint URL" :
     https://votre-domaine.com/bot/discord_bot.php

5. ENREGISTRER LES COMMANDES
   - Exécutez : php bot/register_commands.php

6. INVITER LE BOT
   - Onglet "OAuth2" > "URL Generator"
   - Scopes : bot, applications.commands
   - Permissions : Send Messages, Use Slash Commands
   - Utilisez l'URL générée

7. TESTER
   - Utilisez les commandes slash dans Discord
   - Ou testez en local avec : php bot/test_bot.php

*/

// ========================================
// PERMISSIONS RECOMMANDÉES
// ========================================

/*
Permissions minimales requises :
- Send Messages (Envoyer des messages)
- Use Slash Commands (Utiliser les commandes slash)
- Embed Links (Intégrer des liens)
- Read Message History (Lire l'historique des messages)

Permissions optionnelles :
- Manage Messages (Gérer les messages)
- Add Reactions (Ajouter des réactions)
*/

// ========================================
// SÉCURITÉ
// ========================================

/*
IMPORTANT :
- Ne partagez JAMAIS votre token de bot
- Régénérez le token si il est compromis
- Utilisez HTTPS pour l'URL d'interaction
- Vérifiez toujours les signatures Discord
*/

?>