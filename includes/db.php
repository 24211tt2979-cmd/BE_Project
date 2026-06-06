<?php
/**
 * NHK Mobile - Database Connection & Schema Management
 *
 * Description: Orchestrates the connection to PostgreSQL and implements
 * a "Self-Healing" schema layer that ensures all modern features
 * (reviews, installments, tagging) have required storage structures.
 *
 * Author: NguyenHuuKhanh
 * Version: 2.6
 * Date: 2026-04-16
 */

require_once __DIR__ . '/functions.php';

/**
 * Helper function to safely add a column to a table if it does not already exist.
 * This is a cross-compatible solution for MySQL/MariaDB.
 */
if (!function_exists('dbAddColumn')) {
    function dbAddColumn($pdo, $table, $column, $definition) {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            }
        } catch (\PDOException $e) {
            @error_log("[DB Migration Warning] Failed to add column '$column' to table '$table': " . $e->getMessage());
        }
    }
}

// 1. Cấu hình kết nối - ƯU TIÊN MYSQL_URL HOẶC DATABASE_URL TỪ RAILWAY/RENDER
// Railway sẽ tự động set biến MYSQL_URL hoặc các biến MYSQLHOST riêng lẻ.
$databaseUrl = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: ($_ENV['MYSQL_URL'] ?? $_ENV['DATABASE_URL'] ?? $_SERVER['MYSQL_URL'] ?? $_SERVER['DATABASE_URL'] ?? null);

$connected = false;
$pdo = null;

if ($databaseUrl) {
    $dbParts = parse_url($databaseUrl);
    $host = $dbParts['host'] ?? '';
    $port = $dbParts['port'] ?? '3306';
    $db = isset($dbParts['path']) ? ltrim($dbParts['path'], '/') : '';
    $user = isset($dbParts['user']) ? urldecode($dbParts['user']) : '';
    $pass = isset($dbParts['pass']) ? urldecode($dbParts['pass']) : '';

    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
        $connected = true;
    } catch (PDOException $e) {
        error_log("[DB] Primary connection failed: " . $e->getMessage());
    }
}

// 2. Nếu không kết nối được, thử dùng biến môi trường riêng lẻ (Hỗ trợ Railway MYSQLHOST và Render DB_HOST)
if (!$connected) {
    $host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? 'localhost');
    $port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? '3306');
    $db = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'web_ban_dien_thoai');
    $user = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root');
    $pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? '');

    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
        $connected = true;
    } catch (PDOException $e) {
        error_log("[DB] Failed to connect using individual env vars: " . $e->getMessage());
        $pdo = null;
    }
}

// 3. Nếu vẫn không kết nối được, thử kết nối local development
if (!$connected) {
    try {
        $dsn = "mysql:host=localhost;port=3306;dbname=web_ban_dien_thoai;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, 'root', '', $options);
        $connected = true;
        error_log("[DB] Connected using local fallback");
    } catch (PDOException $e) {
        error_log("[DB] Failed to connect using local fallback: " . $e->getMessage());
        $pdo = null;
    }
}

// 4. Nếu tất cả đều thất bại, hiển thị lỗi thân thiện
if (!$connected || !$pdo) {
    http_response_code(503);
    $errorMsg = '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lỗi kết nối - NHK Mobile</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f7; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .error-container { text-align: center; padding: 40px; max-width: 500px; }
        .error-icon { font-size: 64px; margin-bottom: 20px; }
        h1 { color: #1d1d1f; margin-bottom: 16px; }
        p { color: #6e6e73; line-height: 1.6; margin-bottom: 24px; }
        .btn { display: inline-block; padding: 14px 28px; background: #007AFF; color: white; text-decoration: none; border-radius: 980px; font-weight: 600; }
        .btn:hover { background: #0056b3; }
        .retry-info { margin-top: 24px; padding: 16px; background: #fff; border-radius: 12px; font-size: 14px; color: #86868b; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">🔧</div>
        <h1>Đang bảo trì hệ thống</h1>
        <p>Chúng tôi đang nâng cấp cơ sở dữ liệu để phục vụ bạn tốt hơn. Vui lòng thử lại sau vài phút.</p>
        <a href="/" class="btn">Thử lại</a>
        <div class="retry-info">
            Nếu lỗi tiếp tục xảy ra, vui lòng liên hệ hotline: <strong>1900 xxxx</strong>
        </div>
    </div>
</body>
</html>';
    die($errorMsg);
}

// Kết nối thành công, tiếp tục với schema management
try {

    // CHECK FOR FORCE RESET (via environment variable)
    // Set FORCE_DB_RESET=true in Render environment to trigger full reset
    try {
        $forceReset = getenv('FORCE_DB_RESET') === 'true' || ($_ENV['FORCE_DB_RESET'] ?? '') === 'true';
        
        if ($forceReset) {
            @error_log("[DB] FORCE RESET TRIGGERED - Dropping and recreating all tables...");
            
             // Drop tất cả tables
            $tables = [
                'password_resets', 'repair_history', 'order_items', 'orders',
                'cart_items', 'reviews', 'wishlists', 'warranties',
                'return_requests', 'admin_logs', 'chatbot_rules', 'subscribers',
                'system_settings', 'categories', 'homepage_banners',
                'products', 'users', 'admins', 'news'
            ];
            foreach ($tables as $table) {
                try { $pdo->exec("SET FOREIGN_KEY_CHECKS=0; DROP TABLE IF EXISTS $table; SET FOREIGN_KEY_CHECKS=1;"); } catch (\PDOException $e) {}
            }
        }
    } catch (\Exception $e) {
        // Ignore FORCE_RESET errors
    }

    /**
     * KHỞI TẠO SCHEMA LẦN ĐẦU
     * Chỉ chạy init_db.sql (tạo bảng và chèn sản phẩm mẫu) khi bảng products còn trống
     * HOẶC khi FORCE_DB_RESET=true
     */
    $sqlFile = __DIR__ . '/../php/config/init_db.sql';
    if (file_exists($sqlFile)) {
        $productCount = 0;
        try {
            $productCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        } catch (\PDOException $e) {
            // Lỗi bảng không tồn tại -> productCount giữ nguyên là 0 để chạy khởi tạo
        }

        if ($productCount === 0) {
            $sql = file_get_contents($sqlFile);
            try { $pdo->exec($sql); } catch (\PDOException $e) { error_log("[DB] init_db.sql error: " . $e->getMessage()); }
            error_log("[DB] Initial schema created from init_db.sql");
        }
    }

    /**
     * MIGRATION FALLBACK (Cơ chế tự sửa lỗi)
     * Luôn chạy các lệnh sau để đảm bảo DB luôn có đủ bảng/cột mới nhất.
     */
    
    // Đảm bảo có bảng Đánh giá (Reviews)
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT REFERENCES products(id) ON DELETE CASCADE,
            user_id INT REFERENCES users(id) ON DELETE SET NULL,
            reviewer_name VARCHAR(255),
            reviewer_email VARCHAR(255),
            rating INT CHECK (rating >= 1 AND rating <= 5),
            title VARCHAR(255),
            content TEXT,
            verified_purchase INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    "); } catch (\PDOException $e) {}

    // Bổ sung các cột bị thiếu do nâng cấp hệ thống (is_installment, rating, vv...)
    dbAddColumn($pdo, 'reviews', 'verified_purchase', 'INT DEFAULT 0');
    dbAddColumn($pdo, 'reviews', 'image', 'VARCHAR(255)');
    dbAddColumn($pdo, 'news', 'tags', 'VARCHAR(255)');
    dbAddColumn($pdo, 'news', 'category', "VARCHAR(100) DEFAULT 'Technology'");
    dbAddColumn($pdo, 'news', 'excerpt', 'TEXT');
    dbAddColumn($pdo, 'products', 'rating', 'DECIMAL(3,2) DEFAULT 0.00');
    dbAddColumn($pdo, 'products', 'review_count', 'INT DEFAULT 0');
    dbAddColumn($pdo, 'products', 'specs', 'TEXT');
    dbAddColumn($pdo, 'products', 'cost_price', 'DECIMAL(15,2) DEFAULT 0.00');
    try { $pdo->exec("ALTER TABLE products ADD CONSTRAINT products_name_unique UNIQUE (name);"); } catch (\PDOException $e) {}
    
    // Cập nhật cấu trúc bảng Orders (Đơn hàng)
    dbAddColumn($pdo, 'orders', 'user_id', 'INT REFERENCES users(id)');
    dbAddColumn($pdo, 'orders', 'customer_phone', 'VARCHAR(20)');
    dbAddColumn($pdo, 'orders', 'customer_address', 'TEXT');
    dbAddColumn($pdo, 'orders', 'payment_method', 'VARCHAR(50)');
    dbAddColumn($pdo, 'orders', 'is_installment', 'BOOLEAN DEFAULT FALSE');
    dbAddColumn($pdo, 'orders', 'shipping_fee', 'DECIMAL(15,2) DEFAULT 0.00');
    dbAddColumn($pdo, 'orders', 'shipping_code', 'VARCHAR(50)');
    dbAddColumn($pdo, 'orders', 'payment_status', "VARCHAR(20) DEFAULT 'Unpaid'");
    dbAddColumn($pdo, 'orders', 'profit', 'DECIMAL(15,2) DEFAULT 0.00');
    
    // Cập nhật chi tiết mặt hàng đơn hàng
    dbAddColumn($pdo, 'order_items', 'imei', 'VARCHAR(255)');
    try {
        $pdo->exec("ALTER TABLE order_items MODIFY COLUMN imei VARCHAR(255)");
    } catch (\PDOException $e) {}

    // Thêm cột session_id cho bảng cart_items nếu chưa có (để tương thích với guest users)
    dbAddColumn($pdo, 'cart_items', 'session_id', 'VARCHAR(255)');
    
    // Đảm bảo có bảng Giỏ hàng (Cart Items) với cấu trúc đúng
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS cart_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT REFERENCES users(id) ON DELETE CASCADE,
            product_id INT REFERENCES products(id) ON DELETE CASCADE,
            quantity INT DEFAULT 1,
            session_id VARCHAR(255),
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (user_id, product_id)
        );
    "); } catch (\PDOException $e) {}
    
    // Xóa constraint cũ dựa trên (session_id, product_id) nếu tồn tại
    try { 
        $pdo->exec("ALTER TABLE cart_items DROP CONSTRAINT IF EXISTS cart_items_session_product_unique;"); 
    } catch (\PDOException $e) { /* Bỏ qua nếu không tồn tại */ }
    
    // Thêm ràng buộc duy nhất mới dựa trên (user_id, product_id) để ON CONFLICT hoạt động chính xác
    try { 
        $pdo->exec("ALTER TABLE cart_items ADD CONSTRAINT cart_items_user_product_unique UNIQUE (user_id, product_id);"); 
    } catch (\PDOException $e) { /* Bỏ qua nếu đã tồn tại */ }

    // Đảm bảo có bảng Bảo hành IMEI (Warranties)
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS warranties (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            imei        VARCHAR(20) NOT NULL UNIQUE,
            product_id  INT REFERENCES products(id) ON DELETE SET NULL,
            order_id    INT REFERENCES orders(id) ON DELETE SET NULL,
            status      VARCHAR(50) NOT NULL DEFAULT 'Active',
            expires_at  DATE NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    "); } catch (\PDOException $e) {}

    // Bổ sung cột bị thiếu trên bảng warranties legacy
    dbAddColumn($pdo, 'warranties', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    dbAddColumn($pdo, 'warranties', 'customer_name', 'VARCHAR(255)');
    dbAddColumn($pdo, 'warranties', 'customer_phone', 'VARCHAR(20)');
    dbAddColumn($pdo, 'warranties', 'order_id', 'INT REFERENCES orders(id) ON DELETE SET NULL');

    // Đảm bảo có bảng Lịch sử Sửa chữa (Repair History)
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS repair_history (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            warranty_id INT REFERENCES warranties(id) ON DELETE CASCADE,
            repair_date DATE NOT NULL,
            title       VARCHAR(255) NOT NULL,
            description TEXT,
            location    VARCHAR(255),
            repair_id   VARCHAR(50),
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    "); } catch (\PDOException $e) {}

    // Bổ sung cột hồ sơ người dùng (profile)
    dbAddColumn($pdo, 'users', 'phone', 'VARCHAR(20)');
    dbAddColumn($pdo, 'users', 'address', 'TEXT');
    dbAddColumn($pdo, 'users', 'role', "VARCHAR(20) DEFAULT 'user'");
    dbAddColumn($pdo, 'users', 'status', "VARCHAR(20) DEFAULT 'active'");
    dbAddColumn($pdo, 'users', 'loyalty_points', 'INT DEFAULT 0');
    dbAddColumn($pdo, 'users', 'membership_tier', "VARCHAR(20) DEFAULT 'Bronze'");

    // Đảm bảo có bảng Danh sách Yêu thích (Wishlists)
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS wishlists (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (user_id, product_id)
        );
    "); } catch (\PDOException $e) {}

    // Đảm bảo có bảng Subscribers (Đăng ký nhận tin)
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "); } catch (\PDOException $e) {}

    // Đảm bảo có bảng Return Requests (Yêu cầu trả hàng)
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS return_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            user_id INT NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(20),
            order_code VARCHAR(50),
            reason_type VARCHAR(100),
            reason TEXT NOT NULL,
            images TEXT,
            status VARCHAR(50) NOT NULL DEFAULT 'Cho duyet',
            admin_note TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (order_id),
            INDEX (user_id),
            CONSTRAINT return_requests_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT return_requests_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "); } catch (\PDOException $e) {}

    // Đảm bảo có bảng Quản lý IMEI (Imeis)
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS imeis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            imei VARCHAR(20) NOT NULL UNIQUE,
            status VARCHAR(20) NOT NULL DEFAULT 'Available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (product_id),
            CONSTRAINT imeis_product_fk FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "); } catch (\PDOException $e) {}

    // Đảm bảo email trong bảng users là UNIQUE (phòng trường hợp migration cũ)
    // Cau hinh he thong: hotline, dia chi, email, ban do va cac thiet lap hien thi.
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
    "); } catch (\PDOException $e) {}

    try { $pdo->exec("
        INSERT INTO system_settings (setting_key, setting_value) VALUES
        ('store_name', 'NHK Mobile'),
        ('hotline', '0375 352 347'),
        ('store_email', 'support@nhkmobile.local'),
        ('store_address', '123 Duong Cong Nghe, Quan 1, TP.HCM'),
        ('map_embed_url', 'https://www.google.com/maps?q=Ho%20Chi%20Minh%20City&output=embed')
        ON DUPLICATE KEY UPDATE setting_value = setting_value;
    "); } catch (\PDOException $e) {}

    // Bang danh muc rieng de admin co the quan ly thay vi sua code.
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            sort_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    "); } catch (\PDOException $e) {}

    try { $pdo->exec("
        INSERT INTO categories (name, sort_order, is_active) VALUES
        ('Apple', 1, TRUE),
        ('Samsung', 2, TRUE),
        ('Xiaomi', 3, TRUE),
        ('OPPO', 4, TRUE),
        ('Vivo', 5, TRUE),
        ('Realme', 6, TRUE)
        ON DUPLICATE KEY UPDATE name = name;
    "); } catch (\PDOException $e) {}

    // Banner trang chu co the cap nhat trong admin.
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS homepage_banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            subtitle VARCHAR(255),
            image VARCHAR(255),
            link_url VARCHAR(255) DEFAULT 'product.php',
            sort_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    "); } catch (\PDOException $e) {}

    try {
        $bannerCount = (int)$pdo->query("SELECT COUNT(*) FROM homepage_banners")->fetchColumn();
        if ($bannerCount === 0) {
            $pdo->exec("
                INSERT INTO homepage_banners (title, subtitle, image, link_url, sort_order, is_active) VALUES
                ('iPhone 17 Pro Max', 'Mở bán hôm nay - ưu đãi lớn', 'apple-iphone-17-pro-max.png', 'product.php?category=Apple', 1, TRUE),
                ('Galaxy S25 Ultra', 'Trả góp 0% trong 24 tháng', 'samsung-galaxy-s25-ultra.png', 'product.php?category=Samsung', 2, TRUE),
                ('Xiaomi 17 Ultra', 'Camera Leica và pin bền bỉ', 'xiaomi-17-ultra.png', 'product.php?category=Xiaomi', 3, TRUE);
            ");
        }
    } catch (\PDOException $e) {}

    try { $pdo->exec("
        UPDATE homepage_banners
        SET subtitle = CASE
            WHEN subtitle = 'Mo ban hom nay - uu dai lon' THEN 'Mở bán hôm nay - ưu đãi lớn'
            WHEN subtitle = 'Tra gop 0% trong 24 thang' THEN 'Trả góp 0% trong 24 tháng'
            WHEN subtitle = 'Camera Leica va pin ben bi' THEN 'Camera Leica và pin bền bỉ'
            ELSE subtitle
        END;
    "); } catch (\PDOException $e) {}

    try { $pdo->exec("ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email);"); } catch (\PDOException $e) {}

    // Đảm bảo có bảng Password Resets cho chức năng quên mật khẩu
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            reset_token VARCHAR(191) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            is_used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    "); } catch (\PDOException $e) {}

    // Thêm cột username cho bảng users nếu chưa có (để tương thích)
    dbAddColumn($pdo, 'users', 'username', 'VARCHAR(50)');
    try { $pdo->exec("ALTER TABLE users ADD CONSTRAINT users_username_unique UNIQUE (username);"); } catch (\PDOException $e) {}
 
    // Thêm cột reset_status cho bảng users để track password reset requests
    dbAddColumn($pdo, 'users', 'last_password_reset', 'TIMESTAMP NULL');

    // Đảm bảo có bảng Lưu trữ Lịch sử Thao tác Admin (Admin Logs)
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT REFERENCES admins(id) ON DELETE SET NULL,
            action_type VARCHAR(50) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    "); } catch (\PDOException $e) {}

    // Đảm bảo có bảng Chatbot Rules
    try { 
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS chatbot_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            keyword VARCHAR(255) NOT NULL,
            response TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        "); 
        
        // Chèn dữ liệu mẫu nếu bảng có ít hơn 15 rule
        $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_rules");
        if ($stmt->fetchColumn() < 15) {
            $pdo->exec("TRUNCATE TABLE chatbot_rules;");
            $pdo->exec("
            INSERT INTO chatbot_rules (keyword, response) VALUES 
            ('giá', 'Dạ, anh/chị có thể xem giá chi tiết của các sản phẩm ở trang chủ hoặc trang danh sách sản phẩm ạ. Giá luôn được cập nhật mới nhất!'),
            ('bảo hành', 'Bên em bảo hành chính hãng 12 tháng, 1 đổi 1 trong 30 ngày đầu nếu có lỗi phần cứng từ nhà sản xuất. Máy cũ bảo hành pin 6 tháng ạ.'),
            ('địa chỉ', 'Dạ cửa hàng NHK Mobile có hỗ trợ giao hàng toàn quốc và nhận hàng trực tiếp tại các chi nhánh. Anh/chị cứ đặt hàng trên web sẽ có nhân viên gọi xác nhận ạ.'),
            ('chào', 'Dạ NHK Mobile xin chào anh/chị! Em là trợ lý ảo, anh/chị cần hỗ trợ thông tin gì ạ?'),
            ('cảm ơn', 'Dạ không có gì ạ! Chúc anh/chị một ngày vui vẻ! 😊'),
            ('trả góp', 'Dạ cửa hàng có hỗ trợ trả góp 0% qua thẻ tín dụng và các công ty tài chính. Anh/chị để lại SĐT hoặc gọi hotline để được tư vấn thêm nhé.'),
            ('hotline', 'Dạ hotline hỗ trợ 24/7 của NHK Mobile là: 0375 352 347 ạ.'),
            ('mua hàng', 'Dạ để mua hàng, anh/chị chỉ cần chọn sản phẩm trên web, bấm \"Thêm vào giỏ\" rồi tiến hành thanh toán là được ạ. Nhân viên bên em sẽ gọi xác nhận ngay.'),
            ('ship', 'Bên em miễn phí giao hàng toàn quốc ạ. Nếu ở các thành phố lớn sẽ nhận hàng trong vòng 2 giờ, còn các tỉnh khác thì tầm 2-3 ngày ạ.'),
            ('đổi trả', 'Dạ nếu máy có lỗi từ nhà sản xuất, bên em hỗ trợ 1 đổi 1 trong 30 ngày đầu tiên ạ. Nếu anh/chị không ưng ý muốn đổi máy khác sẽ có thu phí chênh lệch theo quy định.'),
            ('iphone', 'Dạ NHK Mobile là đại lý uỷ quyền Apple, bên em có đầy đủ các mã iPhone mới nhất như iPhone 17 Series, 16 Series chính hãng VN/A ạ. Anh/chị xem thêm ở phần danh sách sản phẩm nhé.'),
            ('samsung', 'Dạ các mẫu điện thoại Samsung bên em đều là hàng chính hãng SSVN bảo hành tại mọi trung tâm Samsung trên toàn quốc ạ. Điển hình là Galaxy S25 Ultra đang có giá cực tốt.'),
            ('xiaomi', 'Dạ điện thoại Xiaomi bên em có đủ từ dòng giá rẻ đến cao cấp như Xiaomi 17 Ultra ạ. Máy chạy ROM Quốc tế sẵn tiếng Việt ạ.'),
            ('cũ', 'Dạ ngoài máy mới 100%, bên em cũng có dòng máy cũ Likenew 99% nguyên zin chưa qua sửa chữa, bảo hành dài hạn ạ. Anh/chị ghé xem trên web nhé.'),
            ('phụ kiện', 'Bên em có bán đầy đủ sạc, cáp, ốp lưng, kính cường lực, tai nghe chính hãng ạ. Anh/chị mua kèm máy sẽ được giảm giá thêm 20% - 30% ạ.'),
            ('imei', 'Dạ để kiểm tra bảo hành, anh/chị vào trang \"Bảo hành\" trên web, nhập số IMEI 15 số (bấm *#06# trên điện thoại) để tra cứu lịch sử sửa chữa và hạn bảo hành nhé.'),
            ('flash sale', 'Dạ chương trình Flash Sale diễn ra mỗi ngày trên trang chủ với giá cực sốc. Khuyến mãi sẽ kết thúc vào 23:59 mỗi ngày ạ, anh/chị tranh thủ săn deal nhé!');
            ");
        }
    } catch (\PDOException $e) {
        error_log("[DB] Chatbot Rules creation error: " . $e->getMessage());
    }

    // ── Bảng Mã Giảm Giá (Coupons / Promo Codes) ──────────────────────────────
    try { $pdo->exec("
        CREATE TABLE IF NOT EXISTS coupons (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            code             VARCHAR(50)  NOT NULL UNIQUE,
            discount_percent DECIMAL(5,2) NOT NULL,
            valid_until      DATE         NOT NULL,
            is_active        BOOLEAN      DEFAULT TRUE,
            created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        );
    "); } catch (\PDOException $e) {}

    // Chèn mã mẫu nếu bảng còn trống
    try {
        $couponCount = (int)$pdo->query("SELECT COUNT(*) FROM coupons")->fetchColumn();
        if ($couponCount === 0) {
            $pdo->exec("
                INSERT INTO coupons (code, discount_percent, valid_until, is_active) VALUES
                ('WELCOME10',  10.00, '2026-12-31', TRUE),
                ('SUMMER20',   20.00, '2026-08-31', TRUE),
                ('NHK50',      50.00, '2026-07-01', TRUE),
                ('SALE5',       5.00, '2026-09-30', TRUE)
                ON DUPLICATE KEY UPDATE code = code;
            ");
        }
    } catch (\PDOException $e) {}

} catch (\PDOException $e) {
    error_log("[DB] Schema management error: " . $e->getMessage());
    // Không die ở đây vì kết nối đã thành công, chỉ là lỗi migration
}
?>
