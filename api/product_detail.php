<?php
/**
 * NHK Mobile - Product Detail API
 * Trả về thông tin chi tiết sản phẩm cho Quick View
 */
require_once '../includes/db.php';

header('Content-Type: application/json');

$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$productId) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['error' => 'Product not found']);
    exit;
}

echo json_encode($product);
