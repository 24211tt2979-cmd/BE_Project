-- NHK MOBILE - INITIAL DATABASE SCHEMA 2026
-- Compatible with MySQL/MariaDB (WAMP/Local)
-- CLEAN DATA: 1 Admin, 1 Test User, 5 Products, 3 News

-- 1. DROP EXISTING TABLES
SET FOREIGN_KEY_CHECKS=0;
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
SET FOREIGN_KEY_CHECKS=1;

-- 2. CREATE TABLES

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    phone VARCHAR(20),
    address TEXT,
    username VARCHAR(50),
    last_password_reset TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT REFERENCES orders(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id) ON DELETE SET NULL,
    product_name VARCHAR(255),
    quantity INT NOT NULL,
    price DECIMAL(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id) ON DELETE CASCADE,
    quantity INT DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE repair_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    warranty_id INT REFERENCES warranties(id) ON DELETE CASCADE,
    repair_date DATE NOT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    location    VARCHAR(255),
    repair_id   VARCHAR(50),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlists (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reset_token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    image VARCHAR(255),
    tags VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. SEED DATA (CLEAN - Chỉ để lại 1 admin + 1 user test)

-- Default Admin: admin / admin123
INSERT INTO admins (username, password) VALUES ('admin', '$2y$10$HHrwIfRbuuGrzddNG5/P6.xZmk0AZO8EUGHBqnirhw2ZwmMIHsTJm');

-- Test User: test@test.com / Test123!
INSERT INTO users (fullname, email, password, status, phone, address) 
VALUES ('Test User', 'test@test.com', '$2y$10$a6Fbn90.iVoW2.0SigmIS.uMc6ya4vXC2/zV5i.n4eL1xcDP35f6i', 'active', '0901234567', '123 Đường Test, Quận 1, TP.HCM');

-- Full Products (30 sản phẩm - tên ảnh mới brand-model-slug.png)
INSERT INTO products (name, category, price, stock, image, description, specs, is_featured) VALUES
-- Apple (4 sản phẩm)
('iPhone 17 Pro Max', 'Apple', 32990000, 50, 'apple-iphone-17-pro-max.png', 'iPhone 17 Pro Max đại diện cho đỉnh cao công nghệ di động thế hệ mới của Apple. Sở hữu khung viền Titan Grade 5 siêu nhẹ và bền bỉ, máy trang bị chip xử lý A19 Pro được xây dựng trên tiến trình 2nm siêu tiết kiệm điện và tối ưu hiệu năng vượt trội.

Hệ thống camera được nâng cấp mạnh mẽ với cảm biến chính 48MP thế hệ mới, ống kính telephoto tàu ngầm hỗ trợ zoom quang học 10x và khả năng quay video không gian (Spatial Video) chuẩn điện ảnh. Màn hình Super Retina XDR với công nghệ ProMotion 120Hz mang lại trải nghiệm vuốt chạm vô cùng mượt mà. Tích hợp sâu hệ thống trí tuệ nhân tạo Apple Intelligence giúp tối ưu hóa mọi tác vụ từ dịch thuật, chỉnh ảnh chuyên nghiệp đến trợ lý ảo thông minh.', '256GB, 12GB RAM, A19 Pro, Camera 48MP', TRUE),

('iPhone 16 Pro', 'Apple', 27990000, 40, 'apple-iphone-16-pro.png', 'iPhone 16 Pro là sự kết hợp hoàn hảo giữa kích thước nhỏ gọn và sức mạnh đỉnh cao. Máy sở hữu màn hình OLED Super Retina XDR 6.3 inch viền siêu mỏng, hỗ trợ tần số quét ProMotion 120Hz cho hiển thị mượt mà cực kỳ sắc nét.

Được cung cấp sức mạnh bởi vi xử lý A18 Pro mạnh mẽ, iPhone 16 Pro dễ dàng xử lý các tác vụ đồ họa nặng và thuật toán AI tiên tiến. Hệ thống camera ba ống kính chuyên nghiệp gồm camera chính 48MP, camera góc siêu rộng 48MP và camera tele 5x mang lại khả năng zoom xuất sắc. Nút điều khiển camera Camera Control hoàn toàn mới giúp bạn chụp ảnh và quay phim nhanh chóng chỉ với một cú chạm.', '256GB, 8GB RAM, A18 Pro, Camera 48MP', TRUE),

('iPhone 16e', 'Apple', 19990000, 35, 'apple-iphone-16e.png', 'iPhone 16e là phiên bản tối giản và tinh tế dành cho người dùng yêu thích sự tiện dụng và bền bỉ từ hệ sinh thái Apple. Trang bị chip xử lý A16 Bionic tối ưu hiệu năng tốt, đi kèm cụm camera kép góc rộng sắc nét.

Màn hình Liquid Retina chất lượng hiển thị rực rỡ dưới nắng cùng thời lượng pin kéo dài cả ngày giúp bạn thoải mái giải trí và làm việc không gián đoạn. Thiết kế kính nhôm bền bỉ cùng khả năng kháng nước bụi tiêu chuẩn cao bảo vệ máy an toàn trước mọi tác động hàng ngày.', '128GB, 8GB RAM, A16 Bionic', FALSE),

('iPhone 15 Pro Max', 'Apple', 24990000, 25, 'apple-iphone-15-pro-max.png', 'iPhone 15 Pro Max nổi bật với thiết kế khung viền làm bằng chất liệu Titan chuẩn hàng không vũ trụ cực kỳ sang trọng và bền bỉ. Nút Tác vụ (Action Button) thay thế thanh gạt rung truyền thống mang lại khả năng tùy biến phím tắt nhanh linh hoạt.

Được trang bị chip A17 Pro mạnh mẽ nhất thời điểm ra mắt, máy hỗ trợ chơi các tựa game console mượt mà. Hệ thống camera cao cấp zoom quang học 5x sắc nét và cổng kết nối USB-C chuẩn USB 3 cho tốc độ truyền dữ liệu nhanh chóng gấp nhiều lần thế hệ cũ.', '256GB, 8GB RAM, A17 Pro, Camera 48MP', FALSE),

-- Samsung (3 sản phẩm)
('Samsung Galaxy S25 Ultra', 'Samsung', 29490000, 30, 'samsung-galaxy-s25-ultra.png', 'Samsung Galaxy S25 Ultra tái định nghĩa trải nghiệm smartphone cao cấp với hệ thống Galaxy AI thế hệ thứ hai thông minh vượt trội. Được cung cấp sức mạnh bởi chip xử lý Snapdragon 8 Elite tối ưu riêng, máy xử lý mượt mà mọi tựa game đồ họa nặng nhất.

Màn hình Dynamic AMOLED 2X 6.8 inch phẳng hoàn toàn với độ sáng lên đến 3000 nits, được bảo vệ bởi kính Gorilla Armor chống phản chiếu hiệu quả. Bút S Pen tích hợp cho phép bạn viết vẽ, ghi chú nhanh và điều khiển từ xa linh hoạt. Cụm camera 200MP zoom quang học kép 5x và 10x mang đến chất lượng chụp ảnh thu phóng vượt trội bất kể ngày đêm.', '512GB, 16GB RAM, Snapdragon 8 Elite, S Pen', TRUE),

('Samsung Galaxy S24 Ultra', 'Samsung', 22990000, 20, 'samsung-galaxy-s24-ultra.png', 'Samsung Galaxy S24 Ultra là flagship mở đầu cho kỷ nguyên di động tích hợp trí tuệ nhân tạo Galaxy AI. Thừa hưởng thiết kế khung viền Titan đẳng cấp và bút S Pen tiện lợi đem đến khả năng xử lý công việc và vẽ viết chuyên nghiệp.

Hệ thống bốn camera sau nổi bật với ống kính chính 200MP và ống kính tele 50.0MP hỗ trợ zoom quang học 5x, giúp zoom AI 100x cực kỳ sắc nét. Chip xử lý Snapdragon 8 Gen 3 for Galaxy đem lại hiệu năng xử lý đồ họa Ray Tracing đỉnh cao cùng khả năng tản nhiệt buồng hơi lớn hơn 1.9 lần.', '256GB, 12GB RAM, Snapdragon 8 Gen 3', TRUE),

('Samsung Galaxy S23', 'Samsung', 14990000, 25, 'samsung-galaxy-s23.png', 'Samsung Galaxy S23 mang trên mình ngôn ngữ thiết kế tối giản sang trọng với mặt lưng kính nhám cùng cụm camera xếp dọc thanh lịch. Màn hình Dynamic AMOLED 2X 120Hz mang đến không gian giải trí vô cực đầy sống động.

Trang bị vi xử lý Snapdragon 8 Gen 2 hiệu năng tối ưu mát mẻ cùng viên pin được nâng cấp thời lượng, Galaxy S23 sẵn sàng đồng hành cùng bạn suốt cả ngày dài. Camera 50MP tích hợp công nghệ Nightography chụp đêm sắc nét mang lại những bức hình lung linh đầy chi tiết.', '128GB, 8GB RAM, Snapdragon 8 Gen 2', FALSE),

-- Xiaomi (3 sản phẩm)
('Xiaomi 17 Ultra', 'Xiaomi', 24500000, 15, 'xiaomi-17-ultra.png', 'Xiaomi 17 Ultra là siêu phẩm nhiếp ảnh di động hàng đầu, đồng sáng tạo cùng thương hiệu máy ảnh huyền thoại Leica. Máy sở hữu cụm 4 camera Leica chuyên nghiệp với cảm biến 1 inch thế hệ mới nhất cùng khẩu độ thay đổi linh hoạt.

Được trang bị chip xử lý Snapdragon 8 Elite cực khủng cùng hệ thống tản nhiệt chất lỏng thế hệ mới, máy cho hiệu năng chiến game tuyệt đối ổn định. Màn hình cong tràn bốn cạnh 2K AMOLED màu sắc chân thực cùng công nghệ sạc siêu nhanh HyperCharge 120W hoàn thành 100% pin chỉ trong nháy mắt.', '512GB, 16GB RAM, Snapdragon 8 Elite, Leica Camera', TRUE),

('Xiaomi 15T', 'Xiaomi', 15990000, 20, 'xiaomi-15t.png', 'Xiaomi 15T mang đến trải nghiệm cận cao cấp vượt trội với chip Snapdragon 8s Gen 4 mạnh mẽ và màn hình AMOLED phẳng 144Hz siêu mượt. Phù hợp cho game thủ và người yêu thích các tác vụ đa nhiệm mượt mà.

Hệ thống camera đồng chế tác với Leica cho chất lượng màu sắc đặc trưng, chân thực. Thiết kế khung viền kim loại cứng cáp cùng viên pin dung lượng lớn hỗ trợ sạc nhanh giúp thiết bị hoạt động liên tục bền bỉ.', '256GB, 12GB RAM, Snapdragon 8s Gen 4', FALSE),

('Xiaomi Mix Flip', 'Xiaomi', 21990000, 10, 'xiaomi-mix-flip.png', 'Xiaomi Mix Flip là chiếc điện thoại gập dạng vỏ sò thời thượng đầu tiên của Xiaomi. Điểm nhấn lớn nhất là màn hình phụ kích thước cực lớn ở mặt ngoài, hỗ trợ đầy đủ các ứng dụng thông thường mà không cần mở máy.

Bản lề giọt nước thế hệ mới giúp giảm thiểu nếp gấp tối đa, đóng gập khít hoàn toàn. Bên trong là màn hình chính LTPO AMOLED tần số quét cao sống động cùng hiệu năng mạnh mẽ từ Snapdragon 8 Gen 3 và camera chụp chân dung Leica ấn tượng.', '512GB, 12GB RAM, Snapdragon 8 Gen 3', FALSE),

-- OPPO (3 sản phẩm)
('OPPO Find X10', 'OPPO', 23990000, 12, 'oppo-find-x10.png', 'OPPO Find X10 mở ra thế giới màu sắc chân thực với sự hợp tác từ Hasselblad. Camera góc rộng và camera tele tiềm vọng siêu nét mang đến khả năng chụp chân dung xóa phông tự nhiên và chiều sâu nghệ thuật.

Được cung cấp năng lượng bởi chip Dimensity 9400 tiến trình mới siêu tiết kiệm điện cùng sạc nhanh 100W SUPERVOOC độc quyền giúp sạc đầy pin chỉ trong 30 phút. Thiết kế mặt lưng da sinh thái sang trọng mang lại cảm giác cầm nắm chắc chắn và đẳng cấp.', '512GB, 16GB RAM, Dimensity 9400, Hasselblad', TRUE),

('OPPO K300', 'OPPO', 11990000, 22, 'oppo-k300.png', 'OPPO K300 là mẫu máy tầm trung tập trung vào độ bền bỉ và thời lượng pin cực khủng 6000mAh. Sẵn sàng đáp ứng nhu cầu sử dụng lên đến 2 ngày mà không lo hết pin giữa chừng.

Hiệu năng mượt mà từ Snapdragon 7s Gen 3 xử lý tốt các tác vụ hàng ngày và chơi game nhẹ nhàng. Màn hình tần số quét 120Hz sắc nét cùng khả năng sạc nhanh an toàn là người bạn đồng hành tin cậy cho mọi người dùng.', '256GB, 12GB RAM, Snapdragon 7s Gen 3', FALSE),

('OPPO Mix Flip 5090', 'OPPO', 26990000, 8, 'oppo-mix-flip-5090.png', 'OPPO Mix Flip 5090 đại diện cho thế hệ điện thoại màn hình gập vỏ sò cao cấp tiếp theo. Thiết kế nhỏ gọn như một hộp phấn trang điểm thời trang khi gập lại nhưng mở ra là màn hình sắc nét không nếp gấp.

Được trang bị vi xử lý hàng đầu Snapdragon 8 Elite cùng cụm camera kép cao cấp tối ưu thuật toán AI làm đẹp da độc quyền từ OPPO, mang lại những bức ảnh selfie tự nhiên lung linh nhất.', '512GB, 16GB RAM, Snapdragon 8 Elite, Gập đôi', FALSE),

-- OnePlus (3 sản phẩm)
('OnePlus 13', 'OnePlus', 15500000, 20, 'oneplus-13.png', 'OnePlus 13 được mệnh danh là ''Kẻ hủy diệt Flagship'' thế hệ mới với cấu hình phần cứng cực khủng và sạc siêu nhanh 100W. Thiết kế cụm camera tròn độc đáo kết hợp cùng Hasselblad mang lại chất lượng ảnh chụp giàu chi tiết.

Sở hữu vi xử lý Snapdragon 8 Gen 3 mạnh mẽ cùng dung lượng RAM lớn, máy tối ưu hóa tuyệt đối cho game thủ với chế độ HyperBoost độc quyền giúp giữ khung hình ổn định cao.', '256GB, 12GB RAM, Snapdragon 8 Gen 3, Hasselblad', FALSE),

('OnePlus 15', 'OnePlus', 19990000, 15, 'oneplus-15.png', 'OnePlus 15 mang đến hiệu năng không giới hạn nhờ sự tối ưu hóa phần mềm xuất sắc của hệ điều hành OxygenOS cùng chip Snapdragon 8 Elite đỉnh cao. Màn hình ProXDR AMOLED hiển thị ngoài trời cực sáng và sắc nét.

Công nghệ sạc nhanh SuperVOOC độc quyền giúp pin sạc siêu nhanh và kéo dài tuổi thọ pin. Hệ thống camera Hasselblad thế hệ mới cho màu sắc ảnh chụp tự nhiên, chân thực đầy nghệ thuật.', '256GB, 12GB RAM, Snapdragon 8 Elite', FALSE),

('OnePlus 15R', 'OnePlus', 12990000, 18, 'oneplus-15r.png', 'OnePlus 15R là phiên bản rút gọn hiệu năng cao có mức giá cực kỳ dễ tiếp cận. Sử dụng chip Snapdragon 7+ Gen 3 mát mẻ hiệu quả cao kết hợp cùng màn hình 1.5K AMOLED sắc nét tiết kiệm điện.

Sạc nhanh 80W đi kèm sạc đầy viên pin lớn chỉ trong chưa đầy 35 phút, giúp bạn yên tâm sử dụng liên tục trong ngày mà không gặp bất kỳ trở ngại nào.', '128GB, 8GB RAM, Snapdragon 7+ Gen 3', FALSE),

-- Realme (4 sản phẩm)
('Realme GT 9', 'Realme', 13990000, 18, 'realme-gt9.png', 'Realme GT 9 là chiếc điện thoại chuyên game (Gaming Phone) sở hữu màn hình OLED 144Hz siêu nhạy và chip xử lý Snapdragon 8s Gen 3 mạnh mẽ. Tối ưu hóa phản hồi vuốt chạm tức thì trong các trận đấu game gay cấn.

Hệ thống tản nhiệt 3D buồng hơi kép giữ cho máy luôn mát mẻ khi chơi game thời gian dài. Công nghệ sạc siêu nhanh 120W cho phép nạp đầy pin thần tốc chỉ trong khoảng 20 phút.', '256GB, 12GB RAM, Snapdragon 8s Gen 3', FALSE),

('Realme GT 8 Pro', 'Realme', 17990000, 12, 'realme-gt8-pro.png', 'Realme GT 8 Pro nổi bật với mặt lưng kính thiết kế vân đá độc đáo sang trọng. Cụm camera 50MP sử dụng cảm biến cao cấp Sony IMX906 hỗ trợ chống rung quang học OIS chụp ảnh cực nét bất kể chuyển động.

Sức mạnh xử lý tối thượng từ Snapdragon 8 Gen 3 xử lý mượt mà các tác vụ render video và chơi game đồ họa cao. Dung lượng pin lớn cùng sạc nhanh SuperVOOC giúp trải nghiệm không gián đoạn.', '512GB, 16GB RAM, Snapdragon 8 Gen 3', FALSE),

('Realme GT 8 Pro Blue', 'Realme', 17490000, 10, 'realme-gt8-pro-blue.png', 'Realme GT 8 Pro Blue là phiên bản màu xanh Ocean đặc biệt với mặt lưng hiệu ứng gợn sóng biển tuyệt đẹp dưới ánh sáng. Máy sở hữu cấu hình mạnh mẽ hàng đầu với chip Snapdragon 8 Gen 3 và RAM lớn.

Cụm camera Sony IMX906 OIS cho khả năng bắt nét nhanh và chụp đêm xuất sắc. Phiên bản màu sắc này rất phù hợp với người dùng yêu thích sự cá tính và thời trang khác biệt.', '256GB, 12GB RAM, Snapdragon 8 Gen 3', FALSE),

('Realme GT 7', 'Realme', 11490000, 20, 'realme-gt7.png', 'Realme GT 7 là mẫu máy tầm trung cận cao cấp tập trung vào hiệu năng thực tế và thời lượng sử dụng. Viên pin lớn 6000mAh kết hợp cùng chip Dimensity 9300+ mát mẻ cho thời gian onscreen vô cùng ấn tượng.

Màn hình AMOLED 144Hz sắc nét hiển thị rực rỡ, thích hợp cho cả nhu cầu xem phim giải trí chất lượng cao lẫn chơi game fps cao mượt mà.', '256GB, 8GB RAM, Dimensity 9300+', FALSE),

-- Vivo (2 sản phẩm)
('Vivo X300 Pro', 'Vivo', 20990000, 10, 'vivo-x300.png', 'Vivo X300 Pro dẫn đầu xu hướng nhiếp ảnh chân dung di động chuyên nghiệp nhờ camera Periscope 200MP đột phá và ống kính Zeiss T* chống lóa quang học hàng đầu thế giới.

Sở hữu con chip mạnh mẽ bậc nhất Dimensity 9400 cùng pin dung lượng lớn công nghệ bán dẫn silicon-carbon siêu mỏng nhẹ, thiết bị vừa mỏng nhẹ sang trọng vừa hoạt động cực kỳ bền bỉ cả ngày dài.', '512GB, 16GB RAM, Dimensity 9400, 200MP Periscope', FALSE),

('Vivo X200', 'Vivo', 18490000, 14, 'vivo-x200-black.png', 'Vivo X200 sở hữu thiết kế bo cong mềm mại tinh tế, nổi bật với hệ thống camera hợp tác cùng hãng ống kính Zeiss danh tiếng. Cảm biến ảnh lớn giúp thu sáng tốt hơn mang lại ảnh chụp đêm rực rỡ.

Hiệu năng mượt mà vượt trội từ chip Dimensity 9300 kết hợp viên pin 5800mAh công nghệ mới đem lại thời lượng sử dụng vô cùng thoải mái cho mọi nhu cầu kết nối.', '256GB, 16GB RAM, Dimensity 9300, Zeiss Camera', FALSE),

-- Honor (2 sản phẩm)
('Honor Magic 10', 'Honor', 19490000, 12, 'honor-magic-10.png', 'Honor Magic 10 thu hút mọi ánh nhìn với thiết kế cụm camera đối xứng ''Eye of Muse'' độc đáo và sang trọng. Màn hình LTPO OLED cong tràn cạnh bốn góc hiển thị 1 tỷ màu siêu thực cùng tần số quét 120Hz mượt mà.

Được trang bị chip Snapdragon 8 Gen 3 cùng hệ thống camera Falcon siêu nhạy, máy bắt trọn mọi khoảnh khắc chuyển động nhanh một cách sắc nét. Công nghệ bảo vệ mắt AI giúp giảm mỏi mắt tối đa khi dùng ban đêm.', '512GB, 16GB RAM, Snapdragon 8 Gen 3', FALSE),

('Honor Magic 9', 'Honor', 16490000, 16, 'honor-magic-9.png', 'Honor Magic 9 mang đến thiết kế tối giản tinh tế cùng hiệu năng ổn định bền bỉ từ vi xử lý Snapdragon 8 Gen 2. Màn hình bảo vệ mắt độ phân giải cao hiển thị sắc nét chân thực.

Camera chính độ phân giải siêu cao 200MP kết hợp thuật toán chụp ảnh đêm đêm độc quyền giúp tạo nên những bức hình phong cảnh sống động nhiều chi tiết dù chụp trong điều kiện thiếu sáng.', '256GB, 12GB RAM, Snapdragon 8 Gen 2, 200MP', FALSE),

-- Nubia (3 sản phẩm)
('Nubia Magic 15', 'Nubia', 17990000, 8, 'nubia-magic-15.png', 'Nubia Magic 15 là cỗ máy chiến game tối thượng dành cho game thủ hardcore. Điểm nổi bật nhất là quạt tản nhiệt vật lý tích hợp bên trong máy quay với tốc độ cực cao giúp giảm nhiệt độ CPU tức thì.

Màn hình phẳng hoàn toàn AMOLED 165Hz siêu nhạy mang lại lợi thế phản xạ nhanh trong các game bắn súng. Chip Snapdragon 8 Gen 3 kết hợp phím trigger cảm ứng ở cạnh bên cho trải nghiệm chơi game chuẩn console.', '512GB, 16GB RAM, Snapdragon 8 Gen 3, Gaming', FALSE),

('Nubia V1000', 'Nubia', 22990000, 6, 'nubia-v1000.png', 'Nubia V1000 sở hữu dung lượng pin khổng lồ 10000mAh chưa từng có trên các dòng máy mỏng nhẹ. Đây là giải pháp hoàn hảo cho những chuyến đi dài ngày hoặc người dùng cần kết nối liên tục không nguồn sạc.

Hỗ trợ sạc nhanh 100W giúp sạc đầy viên pin khổng lồ chỉ trong thời gian ngắn. Hiệu năng mượt mà ổn định từ Snapdragon 7 Gen 3 cùng màn hình 120Hz mang lại trải nghiệm toàn diện.', '256GB, 12GB RAM, Snapdragon 7 Gen 3, 10000mAh', FALSE),

('Nubia V90', 'Nubia', 9990000, 20, 'nubia-v90.png', 'Nubia V90 là chiếc điện thoại phổ thông có mức giá cực tốt nhưng vẫn sở hữu dung lượng pin 6000mAh bền bỉ cả ngày. Thiết kế mặt lưng vân nhám chống bám vân tay và mồ hôi hiệu quả.

Màn hình hiển thị 90Hz mượt mà đủ đáp ứng tốt nhu cầu đọc báo, xem youtube và lướt mạng xã hội hàng ngày. Máy chạy ổn định, mát mẻ thích hợp làm máy phụ hoặc tặng người thân.', '128GB, 8GB RAM, Snapdragon 4 Gen 2, 6000mAh', FALSE);nh 120Hz.', '256GB, 12GB RAM, Snapdragon 7 Gen 3, 10000mAh', FALSE),
('Nubia V90', 'Nubia', 9990000, 20, 'nubia-v90.png', 'Pin 6000mAh bền bỉ, màn hình 90Hz, giá tầm trung hợp lý.', '128GB, 8GB RAM, Snapdragon 4 Gen 2, 6000mAh', FALSE);

-- Default News (3 bài viết)
INSERT INTO news (title, content, tags) VALUES
('Chào mừng bạn đến với NHK Mobile', 'Cửa hàng chuyên cung cấp các sản phẩm công nghệ cao cấp...', 'Apple, Samsung, Event'),
('iPhone 17 Pro Max - Siêu phẩm AI', 'Trải nghiệm AI đỉnh cao với camera thông minh...', 'iPhone, Apple, AI'),
('Samsung S25 Ultra - Màn hình vô cực', 'Màn hình AMOLED 120Hz tuyệt đẹp...', 'Samsung, Android');

-- NO orders, NO warranties, NO reviews - CLEAN DATA!
