<?php
/**
 * CẤU HÌNH SMTP - NHK Mobile
 * ==============================
 * Chỉnh sửa thông tin bên dưới để email hoạt động.
 *
 * HƯỚNG DẪN GMAIL (Khuyên dùng):
 *  1. Vào myaccount.google.com > Bảo mật > Xác minh 2 bước (bật lên)
 *  2. Vào myaccount.google.com > Bảo mật > Mật khẩu ứng dụng
 *  3. Chọn "Ứng dụng: Thư" + "Thiết bị: Khác (đặt tên tùy ý)"
 *  4. Copy mật khẩu 16 ký tự vào SMTP_PASS bên dưới
 *  5. Điền email Gmail thật vào SMTP_USER và SMTP_FROM
 *
 * HƯỚNG DẪN BREVO (SMTP miễn phí 300 email/ngày):
 *  1. Đăng ký tại https://brevo.com
 *  2. Vào Settings > SMTP & API > SMTP > tạo tài khoản
 *  3. Dùng host: smtp-relay.brevo.com, port: 587, user: email đăng ký, pass: SMTP key
 */

define('SMTP_HOST',       'smtp.gmail.com');    // smtp.gmail.com | smtp-relay.brevo.com
define('SMTP_PORT',       587);                  // 587 (TLS) | 465 (SSL)
define('SMTP_SECURE',     'tls');                // tls | ssl
define('SMTP_AUTH',       true);                 // Bật xác thực

// ---- ĐỔI 2 DÒNG NÀY THÀNH THÔNG TIN THẬT CỦA BẠN ----
define('SMTP_USER',  'cunkhoi56@gmail.com');    // ← Email Gmail / Brevo gửi đi
define('SMTP_PASS',  'unmt svdq nqts mygi');     // ← Mật khẩu ứng dụng 16 ký tự
// ---------------------------------------------------

define('SMTP_FROM',       SMTP_USER);            // Email gửi (= tài khoản SMTP)
define('SMTP_FROM_NAME',  'NHK Mobile');         // Tên hiển thị trong hộp thư

/**
 * Đường dẫn đến thư viện PHPMailer
 */
define('PHPMAILER_PATH', __DIR__ . '/../php/phpmailer/src/');
