<?php
/**
 * NHK Mobile - AJAX Warranty Verification
 * 
 * Description: Backend endpoint for asynchronous IMEI verification. 
 * Cross-references provided IMEI against the hardware database 
 * and returns JSON status.
 * 
 * Author: NguyenHuuKhanh
 * Version: 2.1
 * Date: 2026-04-08
 */
require_once '../includes/db.php';

// Set response header to JSON format
header('Content-Type: application/json');

if (!isset($_GET['imei'])) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng cung cấp số IMEI']);
    exit;
}

$imei = trim($_GET['imei']);

if (strlen($imei) < 5) {
     echo json_encode(['status' => 'error', 'message' => 'Số IMEI không hợp lệ']);
     exit;
}

// Lấy thông tin bảo hành
$sql = "
    SELECT w.*, p.name as product_name, p.image 
    FROM warranties w 
    LEFT JOIN products p ON w.product_id = p.id 
    WHERE w.imei = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$imei]);
$warranty = $stmt->fetch();

if ($warranty) {
    echo json_encode([
        'status' => 'success',
        'data' => [
            'product_name' => $warranty['product_name'] ?? 'Không rõ sản phẩm',
            'imei' => $warranty['imei'],
            'expires_at' => date('d/m/Y', strtotime($warranty['expires_at'])),
            'warranty_status' => $warranty['status'],
            'is_expired' => (strtotime($warranty['expires_at']) < time())
        ]
    ]);
} else {
    echo json_encode(['status' => 'not_found', 'message' => 'Không tìm thấy dữ liệu bảo hành cho IMEI này. Vui lòng kiểm tra lại.']);
}
