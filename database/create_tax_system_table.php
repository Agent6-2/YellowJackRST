<?php
/**
 * Script de création des tables pour le système de gestion des impôts
 * Système de calcul d'impôts hebdomadaires (vendredi à vendredi) pour les patrons
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Création des tables pour le système de gestion des impôts...\n";
    
    // Table de configuration des tranches d'impôts
    $sql_tax_brackets = "
        CREATE TABLE IF NOT EXISTS tax_brackets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            min_revenue DECIMAL(15,2) NOT NULL,
            max_revenue DECIMAL(15,2) NULL,
            tax_rate DECIMAL(5,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql_tax_brackets);
    echo "✓ Table 'tax_brackets' créée avec succès\n";
    
    // Table des calculs d'impôts hebdomadaires
    $sql_weekly_taxes = "
        CREATE TABLE IF NOT EXISTS weekly_taxes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            week_start DATE NOT NULL COMMENT 'Vendredi de début de semaine',
            week_end DATE NOT NULL COMMENT 'Jeudi de fin de semaine',
            total_revenue DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            effective_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            tax_breakdown JSON NULL COMMENT 'Détail du calcul par tranche',
            is_finalized BOOLEAN DEFAULT FALSE,
            calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            finalized_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_week (week_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql_weekly_taxes);
    echo "✓ Table 'weekly_taxes' créée avec succès\n";
    
    // Insertion des tranches d'impôts selon le barème fourni
    $tax_brackets = [
        [0, 200000, 0.00],
        [200001, 400000, 6.00],
        [400001, 600000, 10.00],
        [600001, 800000, 15.00],
        [800001, 1000000, 20.00],
        [1000001, 10000000, 25.00]
    ];
    
    // Vérifier si les tranches existent déjà
    $stmt = $db->query("SELECT COUNT(*) FROM tax_brackets");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "Insertion des tranches d'impôts...\n";
        
        $stmt = $db->prepare("
            INSERT INTO tax_brackets (min_revenue, max_revenue, tax_rate) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($tax_brackets as $bracket) {
            $max_revenue = ($bracket[1] == 10000000) ? null : $bracket[1]; // Dernière tranche sans limite max
            $stmt->execute([$bracket[0], $max_revenue, $bracket[2]]);
        }
        
        echo "✓ Tranches d'impôts insérées avec succès\n";
    } else {
        echo "✓ Tranches d'impôts déjà configurées\n";
    }
    
    echo "\n=== SYSTÈME DE GESTION DES IMPÔTS CONFIGURÉ ===\n";
    echo "Tables créées :\n";
    echo "- tax_brackets : Configuration des tranches d'impôts\n";
    echo "- weekly_taxes : Calculs d'impôts hebdomadaires\n";
    echo "\nBarème d'impôts configuré :\n";
    echo "- 0€ à 200 000€ : 0%\n";
    echo "- 200 001€ à 400 000€ : 6%\n";
    echo "- 400 001€ à 600 000€ : 10%\n";
    echo "- 600 001€ à 800 000€ : 15%\n";
    echo "- 800 001€ à 1 000 000€ : 20%\n";
    echo "- 1 000 001€ à 10 000 000€ : 25%\n";
    echo "\nPériode de calcul : Vendredi à Jeudi\n";
    
} catch (PDOException $e) {
    echo "Erreur lors de la création des tables : " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nScript terminé avec succès !\n";
?>