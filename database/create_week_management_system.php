<?php
/**
 * Script de création du système de gestion des semaines avec ID unique
 * Chaque semaine a un ID unique et seul le patron peut valider le passage à une nouvelle semaine
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Création du système de gestion des semaines avec ID unique...\n";
    
    // Table principale pour la gestion des semaines
    $sql_weeks = "
        CREATE TABLE IF NOT EXISTS weeks (
            id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID unique de la semaine',
            week_number INT NOT NULL COMMENT 'Numéro de semaine (1, 2, 3, etc.)',
            week_start DATE NOT NULL COMMENT 'Date de début de la semaine',
            week_end DATE NOT NULL COMMENT 'Date de fin de la semaine',
            is_active BOOLEAN DEFAULT FALSE COMMENT 'Semaine actuellement active',
            is_finalized BOOLEAN DEFAULT FALSE COMMENT 'Semaine finalisée par le patron',
            total_revenue DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Chiffre d\'affaires total de la semaine',
            total_sales_count INT DEFAULT 0 COMMENT 'Nombre total de ventes',
            total_cleaning_revenue DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Revenus ménage total',
            total_cleaning_count INT DEFAULT 0 COMMENT 'Nombre total de ménages',
            created_by INT NOT NULL COMMENT 'ID de l\'utilisateur qui a créé la semaine',
            finalized_by INT NULL COMMENT 'ID du patron qui a finalisé la semaine',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            finalized_at TIMESTAMP NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (created_by) REFERENCES users(id),
            FOREIGN KEY (finalized_by) REFERENCES users(id),
            UNIQUE KEY unique_week_number (week_number),
            UNIQUE KEY unique_active_week (is_active) -- Assure qu'une seule semaine peut être active
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql_weeks);
    echo "✓ Table 'weeks' créée avec succès\n";
    
    // Ajouter une colonne week_id aux tables existantes pour lier les données aux semaines
    
    // Modifier la table sales
    $sql_alter_sales = "
        ALTER TABLE sales 
        ADD COLUMN week_id INT NULL COMMENT 'ID de la semaine associée',
        ADD FOREIGN KEY (week_id) REFERENCES weeks(id)
    ";
    
    try {
        $db->exec($sql_alter_sales);
        echo "✓ Colonne 'week_id' ajoutée à la table 'sales'\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "✓ Colonne 'week_id' déjà présente dans la table 'sales'\n";
        } else {
            throw $e;
        }
    }
    
    // Modifier la table cleaning_services
    $sql_alter_cleaning = "
        ALTER TABLE cleaning_services 
        ADD COLUMN week_id INT NULL COMMENT 'ID de la semaine associée',
        ADD FOREIGN KEY (week_id) REFERENCES weeks(id)
    ";
    
    try {
        $db->exec($sql_alter_cleaning);
        echo "✓ Colonne 'week_id' ajoutée à la table 'cleaning_services'\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "✓ Colonne 'week_id' déjà présente dans la table 'cleaning_services'\n";
        } else {
            throw $e;
        }
    }
    
    // Modifier la table weekly_performance
    $sql_alter_performance = "
        ALTER TABLE weekly_performance 
        ADD COLUMN week_id INT NULL COMMENT 'ID de la semaine associée',
        ADD FOREIGN KEY (week_id) REFERENCES weeks(id)
    ";
    
    try {
        $db->exec($sql_alter_performance);
        echo "✓ Colonne 'week_id' ajoutée à la table 'weekly_performance'\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "✓ Colonne 'week_id' déjà présente dans la table 'weekly_performance'\n";
        } else {
            throw $e;
        }
    }
    
    // Modifier la table weekly_taxes
    $sql_alter_taxes = "
        ALTER TABLE weekly_taxes 
        ADD COLUMN week_id INT NULL COMMENT 'ID de la semaine associée',
        ADD FOREIGN KEY (week_id) REFERENCES weeks(id)
    ";
    
    try {
        $db->exec($sql_alter_taxes);
        echo "✓ Colonne 'week_id' ajoutée à la table 'weekly_taxes'\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "✓ Colonne 'week_id' déjà présente dans la table 'weekly_taxes'\n";
        } else {
            throw $e;
        }
    }
    
    // Créer la première semaine si aucune semaine n'existe
    $stmt = $db->query("SELECT COUNT(*) FROM weeks");
    $week_count = $stmt->fetchColumn();
    
    if ($week_count == 0) {
        echo "\nCréation de la première semaine...\n";
        
        // Trouver un utilisateur Patron pour créer la première semaine
        $stmt = $db->query("SELECT id FROM users WHERE role = 'Patron' LIMIT 1");
        $patron = $stmt->fetch();
        
        if (!$patron) {
            echo "⚠️ Aucun utilisateur avec le rôle 'Patron' trouvé. Création de la semaine avec created_by = 1\n";
            $created_by = 1;
        } else {
            $created_by = $patron['id'];
        }
        
        // Calculer les dates de la semaine courante (lundi à dimanche)
        $today = new DateTime();
        $week_start = clone $today;
        $week_start->modify('monday this week');
        $week_end = clone $week_start;
        $week_end->modify('+6 days'); // Dimanche
        
        $stmt = $db->prepare("
            INSERT INTO weeks (week_number, week_start, week_end, is_active, created_by) 
            VALUES (1, ?, ?, TRUE, ?)
        ");
        $stmt->execute([
            $week_start->format('Y-m-d'),
            $week_end->format('Y-m-d'),
            $created_by
        ]);
        
        echo "✓ Semaine 1 créée et activée (" . $week_start->format('d/m/Y') . " au " . $week_end->format('d/m/Y') . ")\n";
    }
    
    echo "\n=== SYSTÈME DE GESTION DES SEMAINES CONFIGURÉ ===\n";
    echo "Fonctionnalités :\n";
    echo "- Chaque semaine a un ID unique (1, 2, 3, etc.)\n";
    echo "- Une seule semaine peut être active à la fois\n";
    echo "- Seul le patron peut valider le passage à une nouvelle semaine\n";
    echo "- Toutes les ventes et ménages sont liés à la semaine active\n";
    echo "- Les données de chaque semaine sont isolées\n";
    echo "\nTables modifiées :\n";
    echo "- weeks : Table principale de gestion des semaines\n";
    echo "- sales : Ajout de week_id\n";
    echo "- cleaning_services : Ajout de week_id\n";
    echo "- weekly_performance : Ajout de week_id\n";
    echo "- weekly_taxes : Ajout de week_id\n";
    
} catch (PDOException $e) {
    echo "Erreur lors de la création du système : " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nScript terminé avec succès !\n";
?>