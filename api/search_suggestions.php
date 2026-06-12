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

    $stmt = $pdo->prepare("
        SELECT id, name, price, image, category, stock, 
               CASE WHEN stock > 0 THEN 1 ELSE 0 END as in_stock
        FROM products 
        WHERE name LIKE ? OR category LIKE ? OR brand LIKE ?
        ORDER BY 
            CASE WHEN name LIKE ? THEN 0 ELSE 1 END,
            stock DESC,
            name ASC
        LIMIT 8
    ");
    $likeQ = "%$q%";
    $stmt->execute([$likeQ, $likeQ, $likeQ, $likeQ]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $item) {
        $results[] = [
            'id'             => $item['id'],
            'name'           => $item['name'],
            'price'          => $item['price'],
            'formatted_price' => number_format($item['price'], 0, ',', '.') . '₫',
            'image'          => $item['image'],
            'category'       => $item['category'],
            'stock'          => (int)$item['stock'],
            'in_stock'       => (bool)$item['in_stock'],
            'url'            => "product-detail.php?id=" . $item['id'],
            'type'           => 'product'
        ];
    }

    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
