<?php
/**
 * NHK Mobile - Cart Count API
 * Trả về số lượng sản phẩm trong giỏ hàng của user đang đăng nhập
 */
require_once '../includes/auth_functions.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Chưa đăng nhập -> giỏ hàng rỗng
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0, 'logged_in' => false, 'items' => [], 'total' => 0]);
    exit;
}

$userId = $_SESSION['user_id'];

// Lấy chi tiết giỏ hàng từ DB
$stmt = $pdo->prepare("
    SELECT ci.product_id, ci.quantity, p.name, p.price, p.image
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.user_id = ?
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tính tổng
$count = 0;
$total = 0;
foreach ($items as $item) {
    $count += (int)$item['quantity'];
    $total += (float)$item['price'] * (int)$item['quantity'];
}

echo json_encode([
    'count' => $count,
    'logged_in' => true,
    'items' => $items,
    'total' => $total
]);
