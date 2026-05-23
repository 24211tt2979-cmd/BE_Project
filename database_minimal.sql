use web_ban_dien_thoai;

-- DATABASE TỐI GIẢN CHO PROJECT_NEW (8 BẢNG CỐT LÕI)
-- Chức năng: Home, Catalog, Search, Detail, Cart, Checkout, Auth

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admins;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Bảng Admin
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- 2. Bảng Người dùng
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(191) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bảng Sản phẩm
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

-- 4. Bảng Đơn hàng
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_address TEXT,
    total_price DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'COD',
    is_installment BOOLEAN DEFAULT FALSE,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 5. Bảng Chi tiết đơn hàng
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    product_name VARCHAR(255),
    quantity INT NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- 6. Bảng Giỏ hàng
CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    product_id INT,
    quantity INT DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 7. Bảng Đánh giá
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    user_id INT,
    reviewer_name VARCHAR(255),
    reviewer_email VARCHAR(255),
    rating INT CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255),
    content TEXT,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 8. Bảng Yêu thích
CREATE TABLE wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- DỮ LIỆU MẪU CƠ BẢN
INSERT INTO admins (username, password) VALUES ('admin', '$2y$10$OxpLzqVPHtjUl6j9rc7Fj.JxotEpbaT0bMmUMymJNVLBZ0tVgI49K');

INSERT INTO users (fullname, email, password, phone, address) 
VALUES ('Người dùng thử', 'test@test.com', '$2y$10$a6Fbn90.iVoW2.0SigmIS.uMc6ya4vXC2/zV5i.n4eL1xcDP35f6i', '0901234567', '123 Đường Test, Quận 1, TP.HCM');

INSERT INTO products (name, category, price, stock, image, description, specs, is_featured) VALUES
('iPhone 16 Pro', 'Apple', 27990000, 40, 'apple-iphone-16-pro.png', 'iPhone 16 Pro với chip A18 Pro và màn hình ProMotion 120Hz.', '256GB, 8GB RAM, A18 Pro', TRUE),
('Samsung Galaxy S25 Ultra', 'Samsung', 29490000, 30, 'samsung-galaxy-s25-ultra.png', 'Đỉnh cao màn hình vô cực, bút S Pen tích hợp AI.', '512GB, 16GB RAM, Snapdragon 8 Elite', TRUE),
('Xiaomi 14 Ultra', 'Xiaomi', 24500000, 15, 'xiaomi-17-ultra.png', 'Camera Leica thế hệ mới, sạc nhanh HyperCharge.', '512GB, 16GB RAM, Leica Camera', TRUE);
