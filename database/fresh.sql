CREATE DATABASE IF NOT EXISTS kapebilidad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kapebilidad;

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    display_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    addons TEXT DEFAULT NULL,
    is_bestseller TINYINT(1) DEFAULT 0,
    is_available TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(20) NOT NULL UNIQUE,
    customer_name VARCHAR(100) NOT NULL,
    order_note VARCHAR(255) DEFAULT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending','preparing','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    product_name VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    item_note VARCHAR(255) DEFAULT NULL,
    addons_summary VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO categories (name, slug, display_order) VALUES
('Kape Muna', 'kape-muna', 1),
('Walang Kape', 'walang-kape', 2),
('Refresher', 'refresher', 3),
('Frappes', 'frappes', 4),
('Snacks', 'snacks', 5);

INSERT INTO products (category_id, name, description, price, image, is_bestseller) VALUES
((SELECT id FROM categories WHERE slug = 'kape-muna'), 'Cafe Kapebilidad', 'Our signature house blend espresso with steamed milk and a hint of caramel.', 120.00, 'kape-muna-1.jpg', 1),
((SELECT id FROM categories WHERE slug = 'kape-muna'), 'Spanish Latte', 'Rich espresso balanced with condensed milk for a smooth, sweet finish.', 130.00, 'kape-muna-2.jpg', 0),
((SELECT id FROM categories WHERE slug = 'kape-muna'), 'Iced Caramel Macchiato', 'Espresso layered with vanilla milk and caramel drizzle over ice.', 140.00, 'kape-muna-3.jpg', 1);

INSERT INTO products (category_id, name, description, price, image, is_bestseller) VALUES
((SELECT id FROM categories WHERE slug = 'walang-kape'), 'Matcha Latte', 'Premium Japanese matcha whisked with fresh steamed milk.', 135.00, 'walang-kape-1.jpg', 1),
((SELECT id FROM categories WHERE slug = 'walang-kape'), 'Chocolate Supreme', 'Velvety dark chocolate drink topped with whipped cream.', 125.00, 'walang-kape-2.jpg', 0),
((SELECT id FROM categories WHERE slug = 'walang-kape'), 'Taro Milk Tea', 'Creamy taro flavored milk tea with chewy tapioca pearls.', 130.00, 'walang-kape-3.jpg', 0);

INSERT INTO products (category_id, name, description, price, image, is_bestseller) VALUES
((SELECT id FROM categories WHERE slug = 'refresher'), 'Blue Lemonade Refresher', 'Sparkling blue lemonade with fresh calamansi and mint.', 110.00, 'refresher-1.jpg', 0),
((SELECT id FROM categories WHERE slug = 'refresher'), 'Strawberry Fizz', 'Fresh strawberry puree with soda water and lime.', 115.00, 'refresher-2.jpg', 1),
((SELECT id FROM categories WHERE slug = 'refresher'), 'Passionfruit Cooler', 'Tangy passionfruit juice blended with lychee and soda.', 115.00, 'refresher-3.jpg', 0);

INSERT INTO products (category_id, name, description, price, image, is_bestseller) VALUES
((SELECT id FROM categories WHERE slug = 'frappes'), 'Java Chip Frappe', 'Blended coffee frappe loaded with chocolate chips and whipped cream.', 150.00, 'frappe-1.jpg', 1),
((SELECT id FROM categories WHERE slug = 'frappes'), 'Caramel Frappe', 'Buttery caramel blended with espresso, milk, and ice, topped with drizzle.', 145.00, 'frappe-2.jpg', 0),
((SELECT id FROM categories WHERE slug = 'frappes'), 'Cookies and Cream Frappe', 'Crushed chocolate cookies blended with creamy milk and ice.', 145.00, 'frappe-3.jpg', 0);

INSERT INTO products (category_id, name, description, price, image, is_bestseller) VALUES
((SELECT id FROM categories WHERE slug = 'snacks'), 'Ensaymada Supreme', 'Soft buttery ensaymada topped with cheese and sugar.', 65.00, 'snacks-1.jpg', 1),
((SELECT id FROM categories WHERE slug = 'snacks'), 'Cheese Pandesal Sandwich', 'Warm pandesal filled with melted cheese and ham.', 75.00, 'snacks-2.jpg', 0),
((SELECT id FROM categories WHERE slug = 'snacks'), 'Chocolate Chip Cookie', 'Freshly baked cookie loaded with chocolate chips.', 55.00, 'snacks-3.jpg', 0);

UPDATE products SET addons = '[{"name":"Extra Shot","price":20},{"name":"Oat Milk","price":15},{"name":"Extra Foam","price":10}]'
WHERE name IN ('Cafe Kapebilidad', 'Spanish Latte', 'Iced Caramel Macchiato');

UPDATE products SET addons = '[{"name":"Extra Whipped Cream","price":10},{"name":"Extra Shot","price":20},{"name":"Choco Drizzle","price":10}]'
WHERE name IN ('Java Chip Frappe', 'Caramel Frappe');

UPDATE products SET addons = '[{"name":"Extra Whipped Cream","price":10},{"name":"Extra Cookie Crumbs","price":15}]'
WHERE name = 'Cookies and Cream Frappe';

UPDATE products SET addons = '[{"name":"Extra Cheese","price":15},{"name":"Extra Ham","price":20}]'
WHERE name = 'Cheese Pandesal Sandwich';
