-- NHK MOBILE - INITIAL DATABASE SCHEMA 2026
-- Compatible with PostgreSQL (Render/Local)
-- CLEAN DATA: 1 Admin, 1 Test User, 5 Products, 3 News

-- 1. DROP EXISTING TABLES
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS repair_history;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS warranties;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS news;
SET FOREIGN_KEY_CHECKS = 1;

-- 2. CREATE TABLES

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(191) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    phone VARCHAR(20),
    address TEXT,
    username VARCHAR(50),
    last_password_reset TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    price DECIMAL(15,2) NOT NULL,
    stock INT DEFAULT 0,
    image VARCHAR(255),
    description TEXT,
    specs TEXT,
    is_featured BOOLEAN DEFAULT FALSE,
    rating DECIMAL(3,2) DEFAULT 0.00,
    review_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_address TEXT,
    total_price DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'COD',
    is_installment BOOLEAN DEFAULT FALSE,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT REFERENCES orders(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id) ON DELETE SET NULL,
    product_name VARCHAR(255),
    quantity INT NOT NULL,
    price DECIMAL(15,2) NOT NULL
);

CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id) ON DELETE CASCADE,
    quantity INT DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, product_id)
);

CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT REFERENCES products(id) ON DELETE CASCADE,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    reviewer_name VARCHAR(255),
    reviewer_email VARCHAR(255),
    rating INT CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255),
    content TEXT,
    verified_purchase INT DEFAULT 0,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE warranties (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT REFERENCES products(id) ON DELETE SET NULL,
    order_id    INT REFERENCES orders(id) ON DELETE SET NULL,
    imei        VARCHAR(20) UNIQUE NOT NULL,
    customer_name  VARCHAR(255),
    customer_phone VARCHAR(20),
    expires_at  DATE,
    status      VARCHAR(50) DEFAULT 'Active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE repair_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    warranty_id INT REFERENCES warranties(id) ON DELETE CASCADE,
    repair_date DATE NOT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    location    VARCHAR(255),
    repair_id   VARCHAR(50),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE wishlists (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, product_id)
);

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reset_token VARCHAR(191) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    image VARCHAR(255),
    tags VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. SEED DATA (CLEAN - Chỉ để lại 1 admin + 1 user test)

-- Default Admin: admin / admin123
INSERT INTO admins (username, password) VALUES ('admin', '$2y$10$OxpLzqVPHtjUl6j9rc7Fj.JxotEpbaT0bMmUMymJNVLBZ0tVgI49K');

-- Test User: test@test.com / Test123!
INSERT INTO users (fullname, email, password, status, phone, address) 
VALUES ('Test User', 'test@test.com', '$2y$10$a6Fbn90.iVoW2.0SigmIS.uMc6ya4vXC2/zV5i.n4eL1xcDP35f6i', 'active', '0901234567', '123 Đường Test, Quận 1, TP.HCM');

-- Full Products (30 sản phẩm - tên ảnh mới brand-model-slug.png)
INSERT INTO products (name, category, price, stock, image, description, specs, is_featured) VALUES
-- Apple (4 sản phẩm)
('iPhone 17 Pro Max', 'Apple', 32990000, 50, 'apple-iphone-17-pro-max.png', 'Siêu phẩm AI thế hệ mới với chip A19 Pro và camera đỉnh cao.', '256GB, 12GB RAM, A19 Pro, Camera 48MP', TRUE),
('iPhone 16 Pro', 'Apple', 27990000, 40, 'apple-iphone-16-pro.png', 'iPhone 16 Pro với chip A18 Pro và màn hình ProMotion 120Hz.', '256GB, 8GB RAM, A18 Pro, Camera 48MP', TRUE),
('iPhone 16e', 'Apple', 19990000, 35, 'apple-iphone-16e.png', 'iPhone nhỏ gọn thế hệ mới, hiệu năng mạnh mẽ.', '128GB, 8GB RAM, A16 Bionic', FALSE),
('iPhone 15 Pro Max', 'Apple', 24990000, 25, 'apple-iphone-15-pro-max.png', 'Titan Design, Action Button, USB-C Pro.', '256GB, 8GB RAM, A17 Pro, Camera 48MP', FALSE),
-- Samsung (3 sản phẩm)
('Samsung Galaxy S25 Ultra', 'Samsung', 29490000, 30, 'samsung-galaxy-s25-ultra.png', 'Đỉnh cao màn hình vô cực, bút S Pen tích hợp AI.', '512GB, 16GB RAM, Snapdragon 8 Elite, S Pen', TRUE),
('Samsung Galaxy S24 Ultra', 'Samsung', 22990000, 20, 'samsung-galaxy-s24-ultra.png', 'Galaxy AI đột phá, camera 200MP siêu nét.', '256GB, 12GB RAM, Snapdragon 8 Gen 3', TRUE),
('Samsung Galaxy S23', 'Samsung', 14990000, 25, 'samsung-galaxy-s23.png', 'Hiệu năng ổn định, màn hình Dynamic AMOLED 120Hz.', '128GB, 8GB RAM, Snapdragon 8 Gen 2', FALSE),
-- Xiaomi (3 sản phẩm)
('Xiaomi 17 Ultra', 'Xiaomi', 24500000, 15, 'xiaomi-17-ultra.png', 'Camera Leica thế hệ 4, sạc nhanh HyperCharge 120W.', '512GB, 16GB RAM, Snapdragon 8 Elite, Leica Camera', TRUE),
('Xiaomi 15T', 'Xiaomi', 15990000, 20, 'xiaomi-15t.png', 'Snapdragon 8s Gen 4, màn hình AMOLED 144Hz.', '256GB, 12GB RAM, Snapdragon 8s Gen 4', FALSE),
('Xiaomi Mix Flip', 'Xiaomi', 21990000, 10, 'xiaomi-mix-flip.png', 'Điện thoại gập thời thượng, camera Leica, màn hình LTPO AMOLED.', '512GB, 12GB RAM, Snapdragon 8 Gen 3', FALSE),
-- OPPO (3 sản phẩm)
('OPPO Find X10', 'OPPO', 23990000, 12, 'oppo-find-x10.png', 'Camera Hasselblad thế hệ mới, sạc nhanh 100W SUPERVOOC.', '512GB, 16GB RAM, Dimensity 9400, Hasselblad', TRUE),
('OPPO K300', 'OPPO', 11990000, 22, 'oppo-k300.png', 'Hiệu năng mạnh mẽ tầm trung, pin 6000mAh.', '256GB, 12GB RAM, Snapdragon 7s Gen 3', FALSE),
('OPPO Mix Flip 5090', 'OPPO', 26990000, 8, 'oppo-mix-flip-5090.png', 'Điện thoại gập cao cấp với chip Snapdragon 8 Elite.', '512GB, 16GB RAM, Snapdragon 8 Elite, Gập đôi', FALSE),
-- OnePlus (3 sản phẩm)
('OnePlus 13', 'OnePlus', 15500000, 20, 'oneplus-13.png', 'Sạc siêu nhanh 100W, Hasselblad Camera, Snapdragon 8 Gen 3.', '256GB, 12GB RAM, Snapdragon 8 Gen 3, Hasselblad', FALSE),
('OnePlus 15', 'OnePlus', 19990000, 15, 'oneplus-15.png', 'OnePlus 15 với chip Snapdragon 8 Elite, màn hình ProXDR.', '256GB, 12GB RAM, Snapdragon 8 Elite', FALSE),
('OnePlus 15R', 'OnePlus', 12990000, 18, 'oneplus-15r.png', 'Hiệu năng cao tầm trung, sạc nhanh 80W SuperVOOC.', '128GB, 8GB RAM, Snapdragon 7+ Gen 3', FALSE),
-- Realme (4 sản phẩm)
('Realme GT 9', 'Realme', 13990000, 18, 'realme-gt9.png', 'Gaming phone mạnh mẽ, màn hình 144Hz, sạc 120W.', '256GB, 12GB RAM, Snapdragon 8s Gen 3', FALSE),
('Realme GT 8 Pro', 'Realme', 17990000, 12, 'realme-gt8-pro.png', 'Camera 50MP Sony IMX906, chip Snapdragon 8 Gen 3.', '512GB, 16GB RAM, Snapdragon 8 Gen 3', FALSE),
('Realme GT 8 Pro Blue', 'Realme', 17490000, 10, 'realme-gt8-pro-blue.png', 'Phiên bản màu xanh Ocean đặc biệt, camera Sony IMX906.', '256GB, 12GB RAM, Snapdragon 8 Gen 3', FALSE),
('Realme GT 7', 'Realme', 11490000, 20, 'realme-gt7.png', 'Pin 6000mAh, sạc 120W, màn hình 144Hz sắc nét.', '256GB, 8GB RAM, Dimensity 9300+', FALSE),
-- Vivo (2 sản phẩm)
('Vivo X300 Pro', 'Vivo', 20990000, 10, 'vivo-x300.png', 'Camera periscope 200MP, chip Dimensity 9400, sạc 90W.', '512GB, 16GB RAM, Dimensity 9400, 200MP Periscope', FALSE),
('Vivo X200', 'Vivo', 18490000, 14, 'vivo-x200-black.png', 'Camera Zeiss 50MP, chip Dimensity 9300, pin 5800mAh.', '256GB, 16GB RAM, Dimensity 9300, Zeiss Camera', FALSE),
-- Honor (2 sản phẩm)
('Honor Magic 10', 'Honor', 19490000, 12, 'honor-magic-10.png', 'AI Camera thông minh, Snapdragon 8 Gen 3, màn hình OLED 120Hz.', '512GB, 16GB RAM, Snapdragon 8 Gen 3', FALSE),
('Honor Magic 9', 'Honor', 16490000, 16, 'honor-magic-9.png', 'Snapdragon 8 Gen 2, camera 200MP, sạc nhanh 66W.', '256GB, 12GB RAM, Snapdragon 8 Gen 2, 200MP', FALSE),
-- Nubia (3 sản phẩm)
('Nubia Magic 15', 'Nubia', 17990000, 8, 'nubia-magic-15.png', 'Gaming phone chuyên dụng, tản nhiệt ICE 6.0, 165Hz UltraTouch.', '512GB, 16GB RAM, Snapdragon 8 Gen 3, Gaming', FALSE),
('Nubia V1000', 'Nubia', 22990000, 6, 'nubia-v1000.png', 'Pin siêu khủng 10000mAh, sạc nhanh 100W, màn hình 120Hz.', '256GB, 12GB RAM, Snapdragon 7 Gen 3, 10000mAh', FALSE),
('Nubia V90', 'Nubia', 9990000, 20, 'nubia-v90.png', 'Pin 6000mAh bền bỉ, màn hình 90Hz, giá tầm trung hợp lý.', '128GB, 8GB RAM, Snapdragon 4 Gen 2, 6000mAh', FALSE);

-- Default News (3 bài viết)
INSERT INTO news (title, content, tags) VALUES
('Chào mừng bạn đến với NHK Mobile', 'Cửa hàng chuyên cung cấp các sản phẩm công nghệ cao cấp...', 'Apple, Samsung, Event'),
('iPhone 17 Pro Max - Siêu phẩm AI', 'Trải nghiệm AI đỉnh cao với camera thông minh...', 'iPhone, Apple, AI'),
('Samsung S25 Ultra - Màn hình vô cực', 'Màn hình AMOLED 120Hz tuyệt đẹp...', 'Samsung, Android');

-- NO orders, NO warranties, NO reviews - CLEAN DATA!
