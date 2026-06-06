<?php
/**
 * NHK Mobile - Real-time AJAX Product Filter API
 *
 * Endpoint : GET api/filter_products.php
 * Returns  : application/json
 *
 * Accepted params:
 *   category  string   e.g. "Apple", "Samsung"
 *   price     string   "under15"|"15to20"|"20to25"|"25to30"|"over30"
 *   storage   string   "128GB"|"256GB"|"512GB"
 *   sort      string   "newest"|"price_asc"|"price_desc"|"name_asc"|"name_desc"
 *   q         string   free-text search
 *   page      int      page number (default 1)
 *   per_page  int      items per page (default 12, max 48)
 *
 * Author: NguyenHuuKhanh
 * Version: 1.0
 * Date: 2026-06-05
 */

// ── 1. Bootstrap ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../includes/db.php';

// Always respond with JSON
header('Content-Type: application/json; charset=utf-8');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// ── 2. Sanitise & read filter parameters ─────────────────────────────────────

/** Whitelist-validate a value against an allowed set. Returns null if invalid. */
function allowed(string $value, array $whitelist): ?string {
    return in_array($value, $whitelist, true) ? $value : null;
}

$category  = isset($_GET['category'])  ? trim($_GET['category'])  : null;
$priceSlot = isset($_GET['price'])     ? trim($_GET['price'])     : null;
$storage   = isset($_GET['storage'])   ? trim($_GET['storage'])   : null;
$search    = isset($_GET['q'])         ? trim($_GET['q'])         : null;
$page      = max(1, (int)($_GET['page']     ?? 1));
$perPage   = min(48, max(1, (int)($_GET['per_page'] ?? 12)));

// Whitelist sort to prevent any ORDER BY injection
$sort = allowed(
    trim($_GET['sort'] ?? 'newest'),
    ['newest', 'price_asc', 'price_desc', 'name_asc', 'name_desc', 'popularity']
) ?? 'newest';

// Whitelist price slots
$priceSlot = allowed($priceSlot ?? '', [
    'under15', '15to20', '20to25', '25to30', 'over30'
]);

// Validate category against values that actually exist in the DB
$validCategory = null;
if ($category) {
    $catCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category = ?");
    $catCheck->execute([$category]);
    if ($catCheck->fetchColumn() > 0) {
        $validCategory = $category;
    }
}

// ── 3. Build dynamic WHERE clauses ───────────────────────────────────────────
$conditions = ['1=1'];  // Base condition so we can always append AND safely
$params     = [];

// Filter: brand / category
if ($validCategory) {
    $conditions[] = 'category = ?';
    $params[]     = $validCategory;
}

// Filter: free-text search on product name
if ($search !== null && $search !== '') {
    $conditions[] = 'name LIKE ?';
    $params[]     = '%' . $search . '%';
}

// Filter: storage (searched in name OR specs column)
if ($storage) {
    $conditions[] = '(name LIKE ? OR specs LIKE ?)';
    $params[]     = '%' . $storage . '%';
    $params[]     = '%' . $storage . '%';
}

// Filter: price range — map named slots to numeric BETWEEN / comparison
if ($priceSlot) {
    switch ($priceSlot) {
        case 'under15':
            $conditions[] = 'price < ?';
            $params[]     = 15000000;
            break;
        case '15to20':
            $conditions[] = 'price BETWEEN ? AND ?';
            $params[]     = 15000000;
            $params[]     = 20000000;
            break;
        case '20to25':
            $conditions[] = 'price BETWEEN ? AND ?';
            $params[]     = 20000000;
            $params[]     = 25000000;
            break;
        case '25to30':
            $conditions[] = 'price BETWEEN ? AND ?';
            $params[]     = 25000000;
            $params[]     = 30000000;
            break;
        case 'over30':
            $conditions[] = 'price > ?';
            $params[]     = 30000000;
            break;
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

// ── 4. Whitelist-based ORDER BY (no user input ever touches SQL directly) ────
$orderByMap = [
    'newest'     => 'created_at DESC',
    'popularity' => 'review_count DESC, rating DESC, id DESC',
    'price_asc'  => 'price ASC',
    'price_desc' => 'price DESC',
    'name_asc'   => 'name ASC',
    'name_desc'  => 'name DESC',
];
$orderBy = $orderByMap[$sort];   // $sort is already whitelist-validated above

// ── 5. Count query (for pagination metadata) ─────────────────────────────────
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products {$whereClause}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log('[filter_products] Count query error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
$page       = min($page, $totalPages);   // clamp page to valid range
$offset     = ($page - 1) * $perPage;

// ── 6. Main data query ───────────────────────────────────────────────────────
try {
    $sql = "SELECT id, name, category, price, image, specs, rating, review_count, stock
            FROM products
            {$whereClause}
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?";

    $dataParams   = array_merge($params, [$perPage, $offset]);
    $productStmt  = $pdo->prepare($sql);
    $productStmt->execute($dataParams);
    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[filter_products] Data query error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

// ── 7. Format each product for the frontend ───────────────────────────────────
$formatted = array_map(function (array $p): array {
    return [
        'id'           => (int)$p['id'],
        'name'         => $p['name'],
        'category'     => $p['category'],
        'price'        => (int)$p['price'],
        'price_fmt'    => number_format((int)$p['price'], 0, ',', '.') . '₫',
        'image'        => $p['image'],
        'specs'        => $p['specs'] ?? '',
        'rating'       => (float)($p['rating'] ?? 0),
        'review_count' => (int)($p['review_count'] ?? 0),
        'in_stock'     => (int)($p['stock'] ?? 0) > 0,
    ];
}, $products);

// ── 8. Return JSON response ───────────────────────────────────────────────────
echo json_encode([
    'success'     => true,
    'total'       => $total,
    'page'        => $page,
    'per_page'    => $perPage,
    'total_pages' => $totalPages,
    'products'    => $formatted,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
