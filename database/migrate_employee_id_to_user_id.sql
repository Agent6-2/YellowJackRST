-- Script de migration pour corriger la colonne employee_id vers user_id
-- À exécuter sur la base de données distante

-- 1. Vérifier si la colonne employee_id existe dans la table sales
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'sales' 
AND COLUMN_NAME = 'employee_id';

-- 2. Si la colonne employee_id existe, la renommer en user_id
-- ATTENTION: Cette opération peut échouer s'il y a des contraintes de clé étrangère

-- Option A: Renommer directement la colonne (MySQL 8.0+)
ALTER TABLE sales RENAME COLUMN employee_id TO user_id;

-- Option B: Si l'option A ne fonctionne pas, utiliser cette méthode alternative
-- Étape 1: Ajouter la nouvelle colonne user_id
-- ALTER TABLE sales ADD COLUMN user_id INT;

-- Étape 2: Copier les données de employee_id vers user_id
-- UPDATE sales SET user_id = employee_id;

-- Étape 3: Supprimer les contraintes de clé étrangère sur employee_id (si elles existent)
-- ALTER TABLE sales DROP FOREIGN KEY fk_sales_employee_id; -- Remplacer par le nom réel de la contrainte

-- Étape 4: Supprimer la colonne employee_id
-- ALTER TABLE sales DROP COLUMN employee_id;

-- Étape 5: Ajouter la contrainte de clé étrangère sur user_id
-- ALTER TABLE sales ADD CONSTRAINT fk_sales_user_id FOREIGN KEY (user_id) REFERENCES users(id);

-- 3. Vérifier que la migration s'est bien passée
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'sales' 
AND COLUMN_NAME IN ('user_id', 'employee_id');

-- 4. Tester une requête simple pour s'assurer que tout fonctionne
SELECT COUNT(*) as total_sales, COUNT(DISTINCT user_id) as unique_users
FROM sales;