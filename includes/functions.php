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

function send_store_mail($to, $subject, $message, $pdo = null) {
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $fromEmail = $pdo ? get_system_setting($pdo, 'store_email', 'no-reply@nhkmobile.local') : 'no-reply@nhkmobile.local';
    $fromName = $pdo ? get_system_setting($pdo, 'store_name', 'NHK Mobile') : 'NHK Mobile';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>'
    ];

    $sent = @mail($to, $subject, $message, implode("\r\n", $headers));
    if (!$sent) {
        error_log("[Mail] Could not send to $to | $subject | " . strip_tags($message));
    }
    return $sent;
}
?>
