<?php
/**
 * Script de migration pour ajouter les catégories de clients et les entreprises
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "Début de la migration...\n";
    
    // 1. Ajouter les nouvelles colonnes à la table customers
    echo "Ajout des colonnes customer_type et company_id à la table customers...\n";
    
    $alterCustomers = "
        ALTER TABLE customers 
        ADD COLUMN customer_type ENUM('Client', 'Entreprise') DEFAULT 'Client' AFTER loyalty_discount,
        ADD COLUMN company_id INT NULL AFTER customer_type,
        ADD INDEX idx_customer_type (customer_type),
        ADD INDEX idx_company_id (company_id)
    ";
    
    $db->exec($alterCustomers);
    echo "✓ Colonnes ajoutées à la table customers\n";
    
    // 2. Créer la table companies
    echo "Création de la table companies...\n";
    
    $createCompanies = "
        CREATE TABLE IF NOT EXISTS companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            siret VARCHAR(14),
            address TEXT,
            phone VARCHAR(20),
            email VARCHAR(100),
            contact_person VARCHAR(100),
            business_discount DECIMAL(5,2) DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name),
            INDEX idx_siret (siret),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($createCompanies);
    echo "✓ Table companies créée\n";
    
    // 3. Ajouter la contrainte de clé étrangère
    echo "Ajout de la contrainte de clé étrangère...\n";
    
    $addForeignKey = "
        ALTER TABLE customers 
        ADD CONSTRAINT fk_customers_company 
        FOREIGN KEY (company_id) REFERENCES companies(id) 
        ON DELETE SET NULL ON UPDATE CASCADE
    ";
    
    $db->exec($addForeignKey);
    echo "✓ Contrainte de clé étrangère ajoutée\n";
    
    // Note: Aucune entreprise d'exemple n'est insérée.
    // Les entreprises doivent être créées manuellement via l'interface d'administration.
    echo "✓ Table companies prête pour la création d'entreprises\n";
    
    // 5. Mettre à jour les clients existants (tous en tant que 'Client' par défaut)
    echo "Mise à jour des clients existants...\n";
    
    $updateExistingCustomers = "
        UPDATE customers 
        SET customer_type = 'Client' 
        WHERE customer_type IS NULL
    ";
    
    $db->exec($updateExistingCustomers);
    echo "✓ Clients existants mis à jour\n";
    
    echo "\n=== Migration terminée avec succès ! ===\n";
    echo "Nouvelles fonctionnalités ajoutées :\n";
    echo "- Catégories de clients : 'Client' et 'Entreprise'\n";
    echo "- Table des entreprises avec réductions automatiques\n";
    echo "- Association optionnelle client-entreprise\n";
    echo "- Index pour optimiser les performances\n";
    
} catch (Exception $e) {
    echo "Erreur lors de la migration : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>