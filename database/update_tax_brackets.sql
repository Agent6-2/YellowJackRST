-- Mise à jour du barème progressif des impôts
-- Nouveau barème :
-- 0 à 199 999 $ : 0% d'impôt 
-- 200 000 à 399 999 $ : 4% d'impôt 
-- 400 000 à 599 999 $ : 8% d'impôt 
-- 600 000 à 799 999 $ : 12% d'impôt 
-- 800 000 à 999 999 $ : 18% d'impôt 
-- 1 000 000 $ et + : 23% d'impôt

-- Supprimer les anciennes tranches
DELETE FROM tax_brackets;

-- Insérer les nouvelles tranches
INSERT INTO tax_brackets (min_revenue, max_revenue, tax_rate) VALUES
(0, 199999, 0.00),
(200000, 399999, 4.00),
(400000, 599999, 8.00),
(600000, 799999, 12.00),
(800000, 999999, 18.00),
(1000000, NULL, 23.00); -- Dernière tranche sans limite max

-- Vérification
SELECT * FROM tax_brackets ORDER BY min_revenue ASC;