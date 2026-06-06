<?php
/**
 * Xuất dữ liệu biểu đồ doanh thu sang Excel/CSV
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once 'admin_auth.php';
require_once '../includes/db.php';

$filter = $_GET['filter'] ?? 'day';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=BaoCaoDoanhThu_' . date('Ymd_His') . '.csv');

// UTF-8 BOM
echo "\xEF\xBB\xBF";
$output = fopen('php://output', 'w');

fputcsv($output, ['BÁO CÁO DOANH THU (' . strtoupper($filter) . ')']);
fputcsv($output, ['Thương hiệu:', 'NHK MOBILE']);
fputcsv($output, ['Thời gian xuất:', date('d/m/Y H:i:s')]);
fputcsv($output, []);

fputcsv($output, ['Thời gian', 'Doanh thu', 'Số đơn hàng', 'Giá trị trung bình đơn']);

$whereCondition = "(status = 'Completed' OR status = 'Hoàn thành' OR status = 'Đã giao' OR status = 'Thành công')";

if ($filter == 'day') {
    $last7days = [];
    for ($i = 6; $i >= 0; $i--) {
        $last7days[date('Y-m-d', strtotime("-$i days"))] = ['revenue' => 0, 'orders' => 0];
    }
    
    $stmt = $pdo->query("SELECT DATE(created_at) as raw_date, SUM(total_price) as revenue, COUNT(id) as orders FROM orders WHERE $whereCondition AND created_at >= CURRENT_DATE - INTERVAL 6 DAY GROUP BY DATE(created_at) ORDER BY raw_date ASC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($data as $row) {
        if (isset($last7days[$row['raw_date']])) {
            $last7days[$row['raw_date']]['revenue'] = $row['revenue'];
            $last7days[$row['raw_date']]['orders'] = $row['orders'];
        }
    }
    
    $tableData = [];
    foreach ($last7days as $date => $stats) {
        $ts = strtotime($date);
        $tableData[] = [
            'label' => date('d/m/Y', $ts),
            'revenue' => $stats['revenue'],
            'orders' => $stats['orders']
        ];
    }
} elseif ($filter == 'month') {
    $months = [];
    for ($i=1; $i<=12; $i++) {
        $months[$i] = ['revenue' => 0, 'orders' => 0];
    }
    $stmt = $pdo->query("SELECT MONTH(created_at) as m, SUM(total_price) as revenue, COUNT(id) as orders FROM orders WHERE $whereCondition AND YEAR(created_at) = YEAR(CURRENT_DATE) GROUP BY MONTH(created_at) ORDER BY m ASC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($data as $row) {
        $months[$row['m']]['revenue'] = $row['revenue'];
        $months[$row['m']]['orders'] = $row['orders'];
    }
    $tableData = [];
    foreach ($months as $m => $stats) {
        $tableData[] = [
            'label' => 'Tháng ' . $m . '/' . date('Y'),
            'revenue' => $stats['revenue'],
            'orders' => $stats['orders']
        ];
    }
} elseif ($filter == 'year') {
    $stmt = $pdo->query("SELECT YEAR(created_at) as y, SUM(total_price) as revenue, COUNT(id) as orders FROM orders WHERE $whereCondition GROUP BY YEAR(created_at) ORDER BY y DESC LIMIT 5");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data = array_reverse($data);
    $tableData = [];
    foreach ($data as $row) {
        $tableData[] = [
            'label' => 'Năm ' . $row['y'],
            'revenue' => $row['revenue'],
            'orders' => $row['orders']
        ];
    }
}

$tableData = array_reverse($tableData);

foreach ($tableData as $row) {
    if ($row['orders'] == 0 && $row['revenue'] == 0 && $filter == 'day') continue;
    $tbDon = $row['orders'] > 0 ? $row['revenue'] / $row['orders'] : 0;
    fputcsv($output, [
        $row['label'],
        number_format($row['revenue'], 0, ',', '.') . ' ₫',
        $row['orders'],
        number_format($tbDon, 0, ',', '.') . ' ₫'
    ]);
}

fputcsv($output, []);
fputcsv($output, ['--- Kết thúc báo cáo ---']);

fclose($output);
exit;
