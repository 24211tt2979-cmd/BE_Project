<?php
/**
 * NHK Mobile - Admin AJAX Get Order Info
 * 
 * Description: Backend endpoint for fetching order details (customer info and items) 
 * when admin creates a new warranty.
 */
require_once '../admin/admin_auth.php'; // Require admin login
require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['order_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Thiếu ID đơn hàng']);
    exit;
}

$orderId = (int)$_GET['order_id'];

// Get Order Info
$stmt = $pdo->prepare("SELECT id, customer_name, customer_phone FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['status' => 'not_found', 'message' => 'Không tìm thấy đơn hàng']);
    exit;
}

// Get Order Items
$stmtItems = $pdo->prepare("SELECT product_id, product_name FROM order_items WHERE order_id = ?");
$stmtItems->execute([$orderId]);
$items = $stmtItems->fetchAll();

echo json_encode([
    'status' => 'success',
    'data' => [
        'customer_name' => $order['customer_name'],
        'customer_phone' => $order['customer_phone'],
        'items' => $items
    ]
]);
