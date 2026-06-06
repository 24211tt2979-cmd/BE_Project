<?php
/**
 * NHK Mobile - Database Auto-initializer
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Force the reset variable to true if called manually to reload schema
$_ENV['FORCE_DB_RESET'] = 'true';
putenv('FORCE_DB_RESET=true');

require_once 'includes/db.php';

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khởi tạo CSDL - NHK Mobile</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f7; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center; max-width: 500px; width: 90%; }
        h1 { color: #007aff; margin-bottom: 10px; font-size: 24px; }
        p { color: #1d1d1f; line-height: 1.6; margin-bottom: 25px; }
        .btn { display: inline-block; padding: 12px 30px; background: #007aff; color: white; text-decoration: none; border-radius: 980px; font-weight: 600; transition: background 0.2s; }
        .btn:hover { background: #0056b3; }
        .status { margin-top: 20px; font-size: 14px; color: #86868b; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🎉 Khởi tạo CSDL MySQL thành công!</h1>
        <p>Hệ thống NHK Mobile đã tự động cấu hình và nạp dữ liệu mẫu (1 Admin, 1 User, 27 Sản phẩm, 3 Tin tức) vào cơ sở dữ liệu MySQL của bạn.</p>
        <a href="index.php" class="btn">Đi đến Trang Chủ</a>
        <div class="status">Database: web_ban_dien_thoai</div>
    </div>
</body>
</html>
