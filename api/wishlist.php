<?php
/**
 * NHK Mobile - Wishlist API
 * POST { product_id } → toggle yêu thích, trả JSON { status, count, product_id }
 */
require_once '../includes/auth_functions.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Phải đăng nhập
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Chưa đăng nhập', 'redirect' => 'login.php']);
    exit;
}

$userId    = (int)$_SESSION['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);

if (!$productId) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu product_id']);
    exit;
}

// Kiểm tra sản phẩm có tồn tại
$chk = $pdo->prepare("SELECT id FROM products WHERE id = ?");
$chk->execute([$productId]);
if (!$chk->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Sản phẩm không tồn tại']);
    exit;
}

// Kiểm tra đã có trong wishlist chưa
$exists = $pdo->prepare("SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?");
$exists->execute([$userId, $productId]);

if ($exists->fetch()) {
    // Đã có → XÓA (toggle off)
    $pdo->prepare("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?")
        ->execute([$userId, $productId]);
    $status = 'removed';
} else {
    // Chưa có → THÊM (toggle on)
    $pdo->prepare("INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)")
        ->execute([$userId, $productId]);
    $status = 'added';
}

// Đếm tổng wishlist của user
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
$cntStmt->execute([$userId]);
$count = (int)$cntStmt->fetchColumn();

echo json_encode([
    'status'     => $status,
    'count'      => $count,
    'product_id' => $productId,
]);
