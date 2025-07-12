-- Script de correction des contraintes de clé étrangère
-- Résout l'erreur #1451 pour permettre la suppression/modification des clients

USE lnprqx_yellowja_db;

-- Supprimer l'ancienne contrainte de clé étrangère
ALTER TABLE sales DROP FOREIGN KEY sales_ibfk_2;

-- Recréer la contrainte avec ON DELETE SET NULL et ON UPDATE CASCADE
-- Cela permettra de supprimer un client en mettant customer_id à NULL dans sales
ALTER TABLE sales 
ADD CONSTRAINT sales_ibfk_2 
FOREIGN KEY (customer_id) REFERENCES customers(id) 
ON DELETE SET NULL 
ON UPDATE CASCADE;

-- Vérifier que la contrainte a été mise à jour
SHOW CREATE TABLE sales;

-- Optionnel : Ajouter une contrainte similaire pour les autres tables si nécessaire
-- (Vérifiez d'abord s'il y a d'autres références à customers)

-- Afficher toutes les contraintes de clé étrangère pour vérification
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME,
    DELETE_RULE,
    UPDATE_RULE
FROM 
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE 
    REFERENCED_TABLE_SCHEMA = 'lnprqx_yellowja_db' 
    AND REFERENCED_TABLE_NAME = 'customers';