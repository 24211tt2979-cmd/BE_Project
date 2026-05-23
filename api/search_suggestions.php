<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

try {
    $results = [];

    // Lấy sản phẩm (Đã tối ưu cho MySQL)
    $stmt = $pdo->prepare("SELECT id, name, price, image, category FROM products WHERE name LIKE ? OR category LIKE ? LIMIT 6");
    $stmt->execute(["%$q%", "%$q%"]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $item) {
        $item['formatted_price'] = number_format($item['price'], 0, ',', '.') . '₫';
        $item['url'] = "product-detail.php?id=" . $item['id'];
        $item['type'] = 'product';
        $results[] = $item;
    }

    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
