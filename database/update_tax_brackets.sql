-- Mise à jour du barème progressif des impôts
-- Nouveau barème :
-- 0 à 200 000€ : 0% d'impôt 
-- 200 001 à 400 000€ : 6% d'impôt 
-- 400 001 à 600 000€ : 10% d'impôt 
-- 600 001 à 800 000€ : 15% d'impôt 
-- 800 001 à 1 000 000€ : 20% d'impôt 
-- 1 000 001 à 10 000 000€ : 25% d'impôt

-- Supprimer les anciennes tranches
DELETE FROM tax_brackets;

-- Insérer les nouvelles tranches
INSERT INTO tax_brackets (min_revenue, max_revenue, tax_rate) VALUES
(0, 200000, 0.00),
(200001, 400000, 6.00),
(400001, 600000, 10.00),
(600001, 800000, 15.00),
(800001, 1000000, 20.00),
(1000001, NULL, 25.00); -- Dernière tranche sans limite max

-- Vérification
SELECT * FROM tax_brackets ORDER BY min_revenue ASC;