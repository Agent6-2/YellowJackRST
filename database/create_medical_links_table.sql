-- Table pour les liens médicaux des employés
-- Permet d'associer des liens de visites médicales directes aux comptes employés

CREATE TABLE IF NOT EXISTS employee_medical_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    link_title VARCHAR(100) NOT NULL COMMENT 'Titre du lien (ex: "Visite médicale annuelle")',
    medical_url VARCHAR(500) NOT NULL COMMENT 'URL du lien médical',
    description TEXT COMMENT 'Description optionnelle du lien',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Statut actif/inactif du lien',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liens médicaux associés aux comptes employés';

-- Insertion d'un exemple de lien médical pour test
-- INSERT INTO employee_medical_links (user_id, link_title, medical_url, description) 
-- VALUES (1, 'Visite médicale annuelle', 'https://example-medical-center.com/appointment', 'Lien pour prendre rendez-vous pour la visite médicale annuelle obligatoire');

-- Vérification de la création de la table
SELECT 'Table employee_medical_links créée avec succès' as status;