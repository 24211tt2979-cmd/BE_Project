
use web_ban_dien_thoai;
-- ============================================================
-- BẢNG USERS - Thêm các cột bị thiếu
-- ============================================================
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20),
    ADD COLUMN IF NOT EXISTS address TEXT,
    ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user',
    ADD COLUMN IF NOT EXISTS username VARCHAR(50),
    ADD COLUMN IF NOT EXISTS last_password_reset TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Đảm bảo email là UNIQUE
ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email);

-- ============================================================
-- BẢNG REVIEWS - Thêm các cột bị thiếu
-- ============================================================
ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS user_id INT,
    ADD COLUMN IF NOT EXISTS reviewer_name VARCHAR(255),
    ADD COLUMN IF NOT EXISTS reviewer_email VARCHAR(255),
    ADD COLUMN IF NOT EXISTS title VARCHAR(255),
    ADD COLUMN IF NOT EXISTS content TEXT,
    ADD COLUMN IF NOT EXISTS verified_purchase INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS image VARCHAR(255),
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- ============================================================
-- BẢNG PRODUCTS - Thêm các cột bị thiếu
-- ============================================================
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS rating DECIMAL(3,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS review_count INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS specs TEXT;

-- ============================================================
-- BẢNG ORDERS - Thêm các cột bị thiếu
-- ============================================================
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS user_id INT,
    ADD COLUMN IF NOT EXISTS customer_phone VARCHAR(20),
    ADD COLUMN IF NOT EXISTS customer_address TEXT,
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'COD',
    ADD COLUMN IF NOT EXISTS is_installment BOOLEAN DEFAULT FALSE;

-- ============================================================
-- BẢNG CART_ITEMS - Thêm các cột bị thiếu
-- ============================================================
ALTER TABLE cart_items
    ADD COLUMN IF NOT EXISTS session_id VARCHAR(255);

-- ============================================================
-- BẢNG WARRANTIES - Thêm các cột bị thiếu
-- ============================================================
ALTER TABLE warranties
    ADD COLUMN IF NOT EXISTS customer_name VARCHAR(255),
    ADD COLUMN IF NOT EXISTS customer_phone VARCHAR(20),
    ADD COLUMN IF NOT EXISTS order_id INT,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- ============================================================
-- TẠO CÁC BẢNG CÒN THIẾU (nếu chưa có)
-- ============================================================

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reset_token VARCHAR(191) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, product_id)
);

CREATE TABLE IF NOT EXISTS repair_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warranty_id INT,
    repair_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(255),
    repair_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action_type VARCHAR(50) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS chatbot_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    keyword VARCHAR(255) NOT NULL,
    response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- DỮ LIỆU MẪU - ĐÁNH GIÁ / BÌNH LUẬN SẢN PHẨM
-- (Chỉ chèn nếu bảng reviews đang trống)
-- ============================================================
INSERT INTO reviews (product_id, user_id, reviewer_name, reviewer_email, rating, title, content, verified_purchase, image)
SELECT * FROM (
    -- iPhone 17 Pro Max (product_id = 1)
    SELECT 1, 1, 'Nguyễn Văn An', 'an.nguyen@gmail.com', 5,
        'Xuất sắc, đáng đồng tiền!',
        'Máy chạy cực mượt, chip A19 Pro mạnh vô đối. Camera chụp ban đêm sắc nét không thua gì máy ảnh chuyên nghiệp. Pin trâu hơn hẳn iPhone cũ. Rất hài lòng!',
        1, NULL
    UNION ALL SELECT 1, NULL, 'Trần Thị Bích', 'bich.tran@yahoo.com', 4,
        'Đẹp nhưng hơi nóng khi chơi game',
        'Thiết kế cao cấp, màn hình đẹp. Tuy nhiên khi chơi game nặng máy hơi ấm. Nhìn chung vẫn rất tốt cho tầm giá này.',
        0, NULL
    UNION ALL SELECT 1, NULL, 'Lê Minh Tuấn', 'tuan.le@gmail.com', 5,
        'iPhone ngon nhất từ trước đến nay',
        'Đây là lần đầu tôi mua iPhone và không hối hận. Camera selfie rõ đẹp, Face ID nhanh, iOS mượt mà. Sẽ giới thiệu cho bạn bè.',
        1, NULL

    UNION ALL
    -- Samsung Galaxy S25 Ultra (product_id = 5)
    SELECT 5, 1, 'Phạm Quốc Hùng', 'hung.pham@gmail.com', 5,
        'Ông vua Android năm 2025',
        'S Pen viết cực mượt, màn hình sắc nét đến từng chi tiết. Galaxy AI thực sự hữu ích cho công việc. Xứng đáng 5 sao!',
        1, NULL
    UNION ALL SELECT 5, NULL, 'Võ Thị Thu', 'thu.vo@gmail.com', 4,
        'Tốt nhưng giá hơi cao',
        'Máy rất mạnh, camera 200MP chụp đẹp không chê vào đâu được. Chỉ tiếc là giá hơi cao so với túi tiền. Nhưng nếu có ngân sách thì đây là lựa chọn tốt nhất.',
        0, NULL

    UNION ALL
    -- Xiaomi 17 Ultra (product_id = 8)
    SELECT 8, NULL, 'Đặng Văn Khoa', 'khoa.dang@gmail.com', 5,
        'Camera Leica đỉnh của đỉnh',
        'Camera Leica chụp ảnh nghệ thuật cực đẹp, màu sắc tự nhiên. Sạc 120W siêu nhanh, từ 0 lên 100% chỉ 25 phút. Giá tốt hơn iPhone rất nhiều.',
        1, NULL
    UNION ALL SELECT 8, NULL, 'Nguyễn Thị Lan', 'lan.nt@gmail.com', 4,
        'Đáng mua trong tầm giá này',
        'Hiệu năng tốt, màn hình AMOLED đẹp. Camera ổn. Chỉ hơi tiếc MIUI đôi khi có quảng cáo. Nhưng tổng thể rất hài lòng.',
        0, NULL

    UNION ALL
    -- iPhone 16 Pro (product_id = 2)
    SELECT 2, NULL, 'Hoàng Đức Bình', 'binh.hoang@gmail.com', 5,
        'Nâng cấp hoàn hảo từ iPhone 14',
        'Cảm nhận rõ sự khác biệt so với máy cũ. Màn hình ProMotion 120Hz cực mượt, chip A18 Pro mạnh, camera chụp tối xuất sắc. Tiếc là không có sạc kèm hộp.',
        1, NULL

    UNION ALL
    -- OPPO Find X10 (product_id = 11)
    SELECT 11, NULL, 'Trương Minh Khải', 'khai.truong@gmail.com', 4,
        'Hasselblad camera thật sự ấn tượng',
        'Camera Hasselblad cho màu sắc rất chuẩn và tự nhiên. Sạc 100W siêu nhanh. Máy mỏng nhẹ dù pin lớn. Trừ 1 sao vì OPPO ColorOS còn hơi nặng.',
        1, NULL
    UNION ALL SELECT 11, NULL, 'Bùi Thị Hoa', 'hoa.bui@gmail.com', 5,
        'Mua cho chồng, ổng rất thích',
        'Đặt hàng giao nhanh, đóng gói cẩn thận. Máy dùng mượt, camera đẹp. Chồng tôi khen hoài. Cảm ơn NHK Mobile đã tư vấn nhiệt tình!',
        1, NULL
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM reviews LIMIT 1);

-- ============================================================
-- HOÀN THÀNH! Tất cả các cột bị thiếu đã được thêm vào.
-- ============================================================
SELECT 'FIX HOÀN THÀNH! Đã thêm cột và dữ liệu đánh giá mẫu.' AS KetQua;

