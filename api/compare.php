<?php
/**
 * NHK Mobile - Compare Products API
 *
 * Endpoint : POST api/compare.php
 * Returns  : application/json
 *
 * Actions:
 *   action=add    &product_id=X  → Add a product to $_SESSION['compare_list']
 *   action=remove &product_id=X  → Remove a product from the list
 *   action=clear                 → Empty the entire compare list
 *
 * Rules:
 *   - Maximum 3 products can be compared at once.
 *   - Duplicate IDs are silently ignored.
 *
 * Author: NguyenHuuKhanh
 * Version: 1.0
 * Date: 2026-06-05
 */

// ── 1. Bootstrap session & DB ─────────────────────────────────────────────────
require_once __DIR__ . '/../includes/auth_functions.php'; // starts session securely
require_once __DIR__ . '/../includes/db.php';

// Always respond with JSON
header('Content-Type: application/json; charset=utf-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// ── 2. Read & validate inputs ─────────────────────────────────────────────────
$action     = trim($_POST['action']     ?? 'add');          // 'add' | 'remove' | 'clear'
$product_id = (int)($_POST['product_id'] ?? 0);             // cast to int for safety

// Ensure the compare list exists in session
if (!isset($_SESSION['compare_list']) || !is_array($_SESSION['compare_list'])) {
    $_SESSION['compare_list'] = [];
}

// ── 3. Handle actions ─────────────────────────────────────────────────────────
$MAX_COMPARE = 3; // Maximum number of products allowed in comparison

if ($action === 'status') {
    // ── STATUS: return current count without modifying anything ──────────────────
    // Used by the floating compare bar on product.php to show the right count on page load.
    echo json_encode([
        'success' => true,
        'message' => 'Tiêu đề hiện tại.',
        'count'   => count($_SESSION['compare_list']),
        'list'    => array_values($_SESSION['compare_list']),
    ], JSON_UNESCAPED_UNICODE);
    exit;

} elseif ($action === 'clear') {
    // ── CLEAR: empty the entire list
    $_SESSION['compare_list'] = [];

} elseif ($action === 'remove') {
    // ── REMOVE: delete this product from the list (if it exists)
    $_SESSION['compare_list'] = array_values(
        array_filter($_SESSION['compare_list'], fn($id) => $id !== $product_id)
    );

} elseif ($action === 'add') {
    // ── ADD: insert product if valid, not duplicate, and under the limit

    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID sản phẩm không hợp lệ.']);
        exit;
    }

    // Check if product actually exists in the database
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại.']);
        exit;
    }

    // Already in the list → do nothing (idempotent)
    if (in_array($product_id, $_SESSION['compare_list'], true)) {
        echo json_encode([
            'success' => true,
            'message' => 'Sản phẩm đã có trong danh sách so sánh.',
            'count'   => count($_SESSION['compare_list']),
            'list'    => $_SESSION['compare_list'],
        ]);
        exit;
    }

    // Limit check: cannot add more than MAX_COMPARE products
    if (count($_SESSION['compare_list']) >= $MAX_COMPARE) {
        echo json_encode(['success' => false, 'message' => "Chỉ có thể so sánh tối đa $MAX_COMPARE sản phẩm cùng lúc.", 'count' => count($_SESSION['compare_list'])]);
        exit;
    }

    // All good — add to list
    $_SESSION['compare_list'][] = $product_id;

} else {
    echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
    exit;
}

// ── 4. Return updated state ───────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'message' => 'Cập nhật danh sách so sánh thành công.',
    'count'   => count($_SESSION['compare_list']),
    'list'    => array_values($_SESSION['compare_list']),   // re-index for clean JSON
], JSON_UNESCAPED_UNICODE);
