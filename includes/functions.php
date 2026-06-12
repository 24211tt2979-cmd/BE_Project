<?php
/**
 * NHK Mobile - Core Utility Functions
 */

/**
 * Định dạng tiền tệ Việt Nam (VNĐ)
 */
function format_price($price) {
    return number_format($price, 0, ',', '.') . '₫';
}

/**
 * Rút gọn văn bản (dùng cho mô tả sản phẩm/tin tức)
 */
function excerpt($text, $limit = 100) {
    if (mb_strlen($text) <= $limit) return $text;
    return mb_substr($text, 0, $limit) . '...';
}

/**
 * Hiển thị Badge trạng thái đơn hàng với CSS class tương ứng
 */
function get_order_status_badge($status) {
    $class = 'bg-warning text-dark';
    $s = mb_strtolower($status, 'UTF-8');
    
    if (str_contains($s, 'đã duyệt')) $class = 'bg-info text-white';
    elseif (str_contains($s, 'đang giao')) $class = 'bg-primary text-white';
    elseif (str_contains($s, 'hoàn thành')) $class = 'bg-success text-white';
    elseif (str_contains($s, 'hủy')) $class = 'bg-danger text-white';
    
    return "<span class=\"badge $class border-0 px-3 py-1 rounded-pill small\">$status</span>";
}

/**
 * Ghi lại lịch sử thao tác của Admin vào cơ sở dữ liệu
 * @param PDO $pdo Đối tượng kết nối CSDL
 * @param string $action_type Loại thao tác (ví dụ: LOGIN, UPDATE_USER)
 * @param string $details Chi tiết thao tác
 */
function log_admin_action($pdo, $action_type, $details = '') {
    if (!isset($_SESSION['admin_id'])) return;
    
    $admin_id = $_SESSION['admin_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action_type, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin_id, $action_type, $details, $ip]);
    } catch (PDOException $e) {
        // Ghi log ra file nếu lỗi DB
        error_log("[Admin Log] Failed to insert DB log: " . $e->getMessage());
    }
}

function get_system_setting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function get_system_settings($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        return [];
    }
}

function set_system_setting($pdo, $key, $value) {
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$key, $value]);
}


/**
 * Tạo giao diện HTML chuẩn cho email NHK Mobile
 */
function build_email_html(string $title, string $bodyHtml, string $extraFooter = ''): string {
    $storeName = 'NHK Mobile';
    $hotline = '0375 352 347';
    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">
<tr><td style="background:#007AFF;padding:28px 32px;text-align:center">
<h1 style="margin:0;color:#fff;font-size:22px;font-weight:700">$storeName</h1>
<p style="margin:4px 0 0;color:rgba(255,255,255,.8);font-size:13px">Hệ thống bán lẻ điện thoại chính hãng</p>
</td></tr>
<tr><td style="padding:32px">
<h2 style="margin:0 0 16px;font-size:18px;color:#1d1d1f">$title</h2>
$bodyHtml
</td></tr>
<tr><td style="background:#f8f8fa;padding:20px 32px;text-align:center;font-size:12px;color:#86868b">
<p style="margin:0 0 6px">$storeName — $hotline</p>
$extraFooter
<p style="margin:6px 0 0;opacity:.6">Email này được gửi tự động, vui lòng không trả lời.</p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>
HTML;
}

function send_store_mail($to, $subject, $message, $pdo = null) {
    // Chức năng thông báo email đã được tắt theo yêu cầu
    error_log("[Mail] ℹ️ Chức năng gửi mail đã được tắt: To: $to | Subject: $subject");
    return true;

    // Validate email đầu vào
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("[Mail] Địa chỉ email không hợp lệ: $to");
        return false;
    }

    // Nạp cấu hình SMTP
    $smtpConfig = __DIR__ . '/smtp_config.php';
    if (file_exists($smtpConfig)) {
        require_once $smtpConfig;
    }

    $fromEmail = defined('SMTP_FROM')      ? SMTP_FROM      : ($pdo ? get_system_setting($pdo, 'store_email', 'no-reply@nhkmobile.local') : 'no-reply@nhkmobile.local');
    $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : ($pdo ? get_system_setting($pdo, 'store_name',  'NHK Mobile') : 'NHK Mobile');

    // ── PHƯƠNG THỨC 1: API Gửi Email Sendcorex (Ưu tiên nhất trên Cloud Hosting) ──
    if (defined('SENDCOREX_API_KEY') && SENDCOREX_API_KEY !== '') {
        try {
            $apiUrl = defined('SENDCOREX_API_URL') ? SENDCOREX_API_URL : 'https://graph.sendcorex.com/v3.0/mail/send';
            $fromEmail = defined('SENDCOREX_FROM') ? SENDCOREX_FROM : 'hello.user@sendcorex.com';
            $fromName  = defined('SENDCOREX_NAME') ? SENDCOREX_NAME : 'NHK Mobile';

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: ' . SENDCOREX_API_KEY,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS     => json_encode([
                    'to'         => $to,
                    'subject'    => $subject,
                    'body'       => $message,
                    'from'       => $fromEmail,
                    'senderName' => $fromName,
                ]),
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log("[Mail] ❌ Sendcorex cURL error: " . $curlError);
            } else {
                $resData = json_decode($response, true);
                if ($httpCode === 200 || $httpCode === 201 || (isset($resData['status']) && $resData['status'] === 'success')) {
                    error_log("[Mail] ✅ Gửi thành công (Sendcorex API) → $to | $subject");
                    return true;
                } else {
                    error_log("[Mail] ❌ Sendcorex API lỗi (HTTP $httpCode): " . $response);
                }
            }
        } catch (\Throwable $e) {
            error_log("[Mail] ❌ Sendcorex lỗi: " . $e->getMessage());
        }
    }

    // ── PHƯƠNG THỨC 2: PHPMailer + Gmail SMTP ──────────────────────────
    $phpMailerPath = defined('PHPMAILER_PATH') ? PHPMAILER_PATH : __DIR__ . '/../php/phpmailer/src/';

    if (
        file_exists($phpMailerPath . 'PHPMailer.php') &&
        file_exists($phpMailerPath . 'SMTP.php') &&
        file_exists($phpMailerPath . 'Exception.php') &&
        defined('SMTP_USER') && SMTP_USER !== 'your_gmail@gmail.com' &&
        defined('SMTP_PASS') && SMTP_PASS !== 'xxxx xxxx xxxx xxxx'
    ) {
        require_once $phpMailerPath . 'PHPMailer.php';
        require_once $phpMailerPath . 'SMTP.php';
        require_once $phpMailerPath . 'Exception.php';

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = defined('SMTP_HOST')   ? SMTP_HOST   : 'smtp.gmail.com';
            $mail->SMTPAuth   = defined('SMTP_AUTH')   ? SMTP_AUTH   : true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = defined('SMTP_PORT')   ? SMTP_PORT   : 587;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 10;                  // Timeout 10 giây để tránh treo script 30 giây nếu server chặn cổng

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $message));

            $mail->send();
            error_log("[Mail] ✅ Gửi thành công (SMTP) → $to | $subject");
            return true;
        } catch (\Throwable $e) {
            error_log("[Mail] ❌ PHPMailer lỗi: " . $e->getMessage() . " | To: $to | Subject: $subject");
            // Không fallback về mail() vì SMTP đã cấu hình nhưng lỗi
            return false;
        }
    }

    // ── PHƯƠNG THỨC 3: PHP mail() native (fallback - chỉ hoạt động trên server thật) ──
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>'
    ];
    $sent = @mail($to, $subject, $message, implode("\r\n", $headers));
    if (!$sent) {
        error_log("[Mail] ❌ mail() thất bại → $to | $subject");
        error_log("[Mail] ℹ️  Lý do: WAMP localhost không có SMTP server. Cấu hình SMTP trong includes/smtp_config.php");
    } else {
        error_log("[Mail] ✅ Gửi thành công (mail()) → $to");
    }
    return $sent;
}
?>

