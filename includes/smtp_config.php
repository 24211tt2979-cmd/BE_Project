<?php
/**
 * CẤU HÌNH SMTP - NHK Mobile
 * ==============================
 * Chỉnh sửa thông tin bên dưới để email hoạt động.
 * 
 * HƯỚNG DẪN GMAIL:
 *  1. Vào myaccount.google.com > Bảo mật > Xác minh 2 bước (bật lên)
 *  2. Vào myaccount.google.com > Bảo mật > Mật khẩu ứng dụng
 *  3. Chọn "Ứng dụng: Thư" + "Thiết bị: Khác (đặt tên tùy ý)"
 *  4. Copy mật khẩu 16 ký tự vào SMTP_PASS bên dưới
 *  5. Điền email Gmail thật vào SMTP_USER và SMTP_FROM
 */

define('SMTP_HOST',       'smtp.gmail.com');    // Gmail SMTP server
define('SMTP_PORT',       587);                  // TLS port
define('SMTP_SECURE',     'tls');                // Mã hóa TLS
define('SMTP_AUTH',       true);                 // Bật xác thực

// ---- ĐỔI 2 DÒNG NÀY THÀNH GMAIL THẬT CỦA BẠN ----
define('SMTP_USER',  'your_gmail@gmail.com');    // ← Email Gmail gửi đi
define('SMTP_PASS',  'xxxx xxxx xxxx xxxx');     // ← Mật khẩu ứng dụng 16 ký tự
// ---------------------------------------------------

define('SMTP_FROM',       SMTP_USER);            // Email gửi (= tài khoản Gmail)
define('SMTP_FROM_NAME',  'NHK Mobile');         // Tên hiển thị trong hộp thư

/**
 * Đường dẫn đến thư viện PHPMailer
 */
define('PHPMAILER_PATH', __DIR__ . '/../php/phpmailer/src/');
