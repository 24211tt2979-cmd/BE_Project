<?php
/**
 * NHK MOBILE - PREMIUM REPORT EXPORT (v2.0)
 * Báo cáo thống kê sạch, không lỗi, tương thích tốt với Excel.
 */

// Ngăn lỗi hiển thị trực tiếp vào file CSV
error_reporting(0);
ini_set('display_errors', 0);

require_once 'admin_auth.php';
require_once '../includes/db.php';

// Cấu hình Header cho file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=BaoCao_NHKMobile_' . date('Ymd_His') . '.csv');

// In UTF-8 BOM để Excel hiển thị đúng tiếng Việt
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// --- 1. TIÊU ĐỀ BÁO CÁO ---
fputcsv($output, ['BÁO CÁO CHI TIẾT DOANH THU & HOẠT ĐỘNG']);
fputcsv($output, ['Thương hiệu:', 'NHK MOBILE Premium Tech']);
fputcsv($output, ['Thời điểm xuất:', date('d/m/Y H:i:s')]);
fputcsv($output, []); // Dòng trống

// --- 2. THỐNG KÊ TỔNG QUAN ---
fputcsv($output, ['===== TỔNG QUAN HỆ THỐNG =====']);
$stmtU = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = (int)$stmtU->fetchColumn();

// Thống kê đơn hàng (Xử lý NULL bằng COALESCE hoặc ép kiểu)
$stmtO = $pdo->query("
    SELECT 
        COUNT(*) as total_orders, 
        SUM(total_price) as total_rev 
    FROM orders 
    WHERE status IN ('Completed', 'Hoàn thành', 'Đã duyệt')
");
$orderStats = $stmtO->fetch(PDO::FETCH_ASSOC);
$countOrders = (int)($orderStats['total_orders'] ?? 0);
$sumRevenue = (float)($orderStats['total_rev'] ?? 0);

fputcsv($output, ['Tổng số khách hàng:', $totalUsers]);
fputcsv($output, ['Tổng đơn hàng thành công:', $countOrders]);
fputcsv($output, ['Tổng doanh thu (VNĐ):', number_format($sumRevenue, 0, ',', '.') . ' ₫']);
fputcsv($output, []); // Dòng trống

// --- 3. DANH SÁCH ĐƠN HÀNG CHI TIẾT ---
fputcsv($output, ['===== DANH SÁCH 50 ĐƠN HÀNG GẦN ĐÂY =====']);
fputcsv($output, ['STT', 'Mã Đơn', 'Khách hàng', 'Số Điện Thoại', 'Tổng Tiền', 'Trạng Thái', 'Ngày Đặt']);

$stmtList = $pdo->query("
    SELECT id, customer_name, customer_phone, total_price, status, created_at 
    FROM orders 
    ORDER BY created_at DESC 
    LIMIT 50
");

$i = 1;
while($row = $stmtList->fetch(PDO::FETCH_ASSOC)) {
    // Việt hóa trạng thái
    $st = $row['status'];
    $statusMap = [
        'pending' => 'Chờ duyệt',
        'processing' => 'Đang xử lý',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy'
    ];
    $displayStatus = $statusMap[strtolower($st)] ?? $st;

    fputcsv($output, [
        $i++,
        '#ORD-' . $row['id'],
        $row['customer_name'],
        $row['customer_phone'],
        number_format((float)$row['total_price'], 0, ',', '.') . ' ₫',
        $displayStatus,
        date('d/m/Y H:i', strtotime($row['created_at']))
    ]);
}

fputcsv($output, []);
fputcsv($output, ['--- Kết thúc báo cáo ---']);

fclose($output);
exit;
