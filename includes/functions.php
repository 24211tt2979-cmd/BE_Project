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
?>