-- Base de données pour Le Yellowjack
-- Fuseau horaire : Europe/Paris

SET time_zone = '+01:00';

-- Table des utilisateurs/employés
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('CDD', 'CDI', 'Responsable', 'Patron') NOT NULL,
    status ENUM('active', 'suspended') DEFAULT 'active',
    hire_date DATE NOT NULL,
    discord_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des services de ménage
CREATE TABLE IF NOT EXISTS cleaning_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NULL,
    cleaning_count INT DEFAULT 0,
    duration_minutes INT DEFAULT 0,
    base_salary DECIMAL(10,2) DEFAULT 0,
    bonus DECIMAL(10,2) DEFAULT 0,
    total_salary DECIMAL(10,2) DEFAULT 0,
    status ENUM('in_progress', 'completed') DEFAULT 'in_progress',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des catégories de produits
CREATE TABLE IF NOT EXISTS product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des produits
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    supplier_price DECIMAL(10,2) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    stock_quantity INT DEFAULT 0,
    min_stock_alert INT DEFAULT 5,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
);

-- Table des clients
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    discord_id VARCHAR(50),
    is_loyal BOOLEAN DEFAULT FALSE,
    loyalty_discount DECIMAL(5,2) DEFAULT 0,
    total_purchases DECIMAL(10,2) DEFAULT 0,
    visit_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des ventes
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    customer_id INT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    final_amount DECIMAL(10,2) NOT NULL,
    employee_commission DECIMAL(10,2) DEFAULT 0,
    payment_method ENUM('cash', 'card', 'other') DEFAULT 'cash',
    discord_webhook_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- Table des détails de vente
CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Table des primes
CREATE TABLE IF NOT EXISTS bonuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    given_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (given_by) REFERENCES users(id)
);

-- Table des paramètres système
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertion des données par défaut

-- Paramètres système
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('cleaning_rate', '60', 'Tarif par ménage en dollars'),
('commission_rate', '20', 'Pourcentage de commission pour les CDI, Responsable et Patron'),
('discord_webhook_url', '', 'URL du webhook Discord pour les tickets'),
('bar_name', 'Le Yellowjack', 'Nom du bar'),
('bar_address', 'Nord de Los Santos, près de Sandy Shore', 'Adresse du bar'),
('bar_phone', '+1-555-YELLOW', 'Téléphone du bar');

-- Catégories de produits par défaut
INSERT INTO product_categories (name, description) VALUES
('Boissons Alcoolisées', 'Bières, vins, spiritueux'),
('Boissons Non-Alcoolisées', 'Sodas, jus, eau'),
('Snacks', 'Chips, cacahuètes, etc.'),
('Plats', 'Burgers, sandwichs, etc.');

-- Produits par défaut
INSERT INTO products (category_id, name, description, supplier_price, selling_price, stock_quantity) VALUES
(1, 'Bière Pression', 'Bière locale à la pression', 2.50, 5.00, 100),
(1, 'Whiskey', 'Whiskey premium', 15.00, 25.00, 20),
(1, 'Vin Rouge', 'Vin rouge de la région', 8.00, 15.00, 15),
(2, 'Coca-Cola', 'Soda classique', 1.00, 3.00, 50),
(2, 'Eau Minérale', 'Eau plate ou gazeuse', 0.50, 2.00, 100),
(3, 'Cacahuètes', 'Cacahuètes salées', 1.50, 4.00, 30),
(4, 'Burger Western', 'Burger spécialité maison', 5.00, 12.00, 0);

-- Utilisateur admin par défaut (mot de passe: admin123)
INSERT INTO users (username, email, password_hash, first_name, last_name, role, hire_date) VALUES
('admin', 'admin@yellowjack.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Yellowjack', 'Patron', CURDATE());

-- Clients par défaut
INSERT INTO customers (name, is_loyal, loyalty_discount) VALUES
('Client Anonyme', FALSE, 0),
('Marcus Johnson', TRUE, 10),
('Sarah Williams', TRUE, 15),
('Mike Thompson', TRUE, 10);