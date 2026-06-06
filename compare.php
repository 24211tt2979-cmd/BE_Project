<?php
/**
 * NHK Mobile - Product Comparison Page
 *
 * Description: Fetches products stored in $_SESSION['compare_list'] and
 * renders them in a side-by-side comparison table. Specs are parsed from
 * the comma-separated `specs` column.
 *
 * Author: NguyenHuuKhanh
 * Version: 1.0
 * Date: 2026-06-05
 */

// ── 1. Bootstrap ──────────────────────────────────────────────────────────────
require_once 'includes/auth_functions.php'; // session_start() happens here
require_once 'includes/db.php';

// ── 2. Fetch products from the database ───────────────────────────────────────

// Get the list of product IDs stored in the session
$compare_ids = $_SESSION['compare_list'] ?? [];

$products = []; // will hold the full product rows

if (!empty($compare_ids)) {
    /*
     * Build a safe IN (?, ?, ?) placeholder dynamically.
     * We cast every ID to int first so no raw user input ever reaches SQL.
     */
    $safe_ids    = array_map('intval', $compare_ids);               // sanitise
    $placeholders = implode(',', array_fill(0, count($safe_ids), '?')); // "?,?,?"

    $stmt = $pdo->prepare(
        "SELECT id, name, category, price, image, description, specs, rating, stock
         FROM products
         WHERE id IN ($placeholders)"
    );
    $stmt->execute($safe_ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
 * ── 3. Spec parser helper ─────────────────────────────────────────────────────
 *
 * The `specs` column stores a comma-separated string like:
 *   "256GB, 12GB RAM, A18 Pro, Camera 48MP"
 *
 * We parse it into a keyed array by matching common labels.
 * If a field is not found, we return '—' to show a dash in the table.
 */
function parse_specs(string $specs_string): array {
    // Default "not available" for all attributes
    $parsed = [
        'storage'  => '—',
        'ram'      => '—',
        'cpu'      => '—',
        'camera'   => '—',
        'battery'  => '—',
        'screen'   => '—',
    ];

    if (empty(trim($specs_string))) {
        return $parsed;
    }

    // Split by comma and check each token for known keywords
    $tokens = array_map('trim', explode(',', $specs_string));

    foreach ($tokens as $token) {
        $lower = mb_strtolower($token, 'UTF-8');

        if (preg_match('/\d+\s*(gb|tb)\s*ram/i', $token)) {
            $parsed['ram'] = $token;
        } elseif (preg_match('/\d+\s*(gb|tb)$/i', $token) || preg_match('/^\d+\s*(gb|tb)\b/i', $token)) {
            $parsed['storage'] = $token;
        } elseif (str_contains($lower, 'mah') || str_contains($lower, 'pin')) {
            $parsed['battery'] = $token;
        } elseif (str_contains($lower, 'mp') || str_contains($lower, 'camera') || str_contains($lower, 'leica') || str_contains($lower, 'hasselblad') || str_contains($lower, 'zeiss')) {
            $parsed['camera'] = $token;
        } elseif (preg_match('/snapdragon|dimensity|a\d+\s*(bionic|pro|chip)|exynos|helio|kirin/i', $token)) {
            $parsed['cpu'] = $token;
        } elseif (str_contains($lower, 'inch') || str_contains($lower, '"') || preg_match('/\d+\.\d+/', $token)) {
            $parsed['screen'] = $token;
        }
    }

    return $parsed;
}

// ── 4. Page setup ─────────────────────────────────────────────────────────────
$pageTitle = 'So sánh sản phẩm | NHK Mobile';
$basePath  = '';
include 'includes/header.php';
?>

<style>
/* ── Page-level overrides (kept inline to avoid cache busting issues) ── */
.compare-page { padding-top: 100px; padding-bottom: 80px; }
</style>

<main class="compare-page">
    <div class="container-wide">

        <!-- Page header -->
        <div class="section-title-box text-start mb-5">
            <span class="section-subtitle">Đánh giá & lựa chọn</span>
            <h1 class="display-4 fw-bold">So sánh sản phẩm.</h1>
            <p class="text-muted mt-2">
                <?php echo count($products); ?> / 3 sản phẩm đang so sánh.
                <a href="#" id="clearAllBtn" class="text-danger fw-bold ms-3 small">
                    <i class="bi bi-trash me-1"></i>Xóa tất cả
                </a>
            </p>
        </div>

        <?php if (empty($products)): ?>
            <!-- Empty state ─────────────────────────────────────────── -->
            <div class="compare-empty-state">
                <div class="compare-empty-icon">
                    <i class="bi bi-phone"></i>
                </div>
                <h3>Chưa có sản phẩm nào để so sánh</h3>
                <p>Hãy chọn tối đa 3 sản phẩm từ trang danh sách và nhấn nút "So sánh".</p>
                <a href="product.php" class="btn-main btn-primary mt-4">
                    <i class="bi bi-grid me-2"></i>Xem tất cả sản phẩm
                </a>
            </div>

        <?php else: ?>
            <!-- Comparison table ────────────────────────────────────── -->
            <div class="compare-table-wrapper">
                <table class="compare-table" id="compareTable">
                    <thead>
                        <tr class="compare-header-row">
                            <!-- Label column -->
                            <th class="compare-label-col" scope="col">
                                <span class="compare-attr-label">Sản phẩm</span>
                            </th>

                            <?php foreach ($products as $p): ?>
                            <th class="compare-product-col" scope="col">
                                <!-- Product image -->
                                <div class="compare-product-img-wrap">
                                    <img src="assets/images/<?php echo htmlspecialchars($p['image']); ?>"
                                         alt="<?php echo htmlspecialchars($p['name']); ?>"
                                         onerror="this.src='https://placehold.co/200x260/f5f5f7/1d1d1f?text=Phone'">
                                </div>

                                <!-- Product name -->
                                <a href="product-detail.php?id=<?php echo $p['id']; ?>"
                                   class="compare-product-name">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </a>

                                <!-- Remove button -->
                                <button class="compare-remove-btn"
                                        onclick="removeFromCompare(<?php echo $p['id']; ?>)"
                                        title="Xóa khỏi so sánh"
                                        aria-label="Xóa <?php echo htmlspecialchars($p['name']); ?> khỏi so sánh">
                                    <i class="bi bi-x-lg"></i> Xóa
                                </button>
                            </th>
                            <?php endforeach; ?>

                            <!-- Empty slot placeholders (up to 3 total) -->
                            <?php for ($i = count($products); $i < 3; $i++): ?>
                            <th class="compare-product-col compare-empty-slot" scope="col">
                                <div class="compare-add-placeholder">
                                    <i class="bi bi-plus-circle"></i>
                                    <span>Thêm sản phẩm</span>
                                    <a href="product.php" class="btn-main btn-outline mt-3 py-2 px-4">
                                        Chọn sản phẩm
                                    </a>
                                </div>
                            </th>
                            <?php endfor; ?>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        /*
                         * Define the rows we want to display.
                         * Each row has:
                         *   key    → used to retrieve the value (either a product field or spec key)
                         *   label  → displayed in the first column
                         *   icon   → Bootstrap Icons class
                         *   type   → 'field' (direct DB column) | 'spec' (parsed from specs string) | 'price' | 'stock' | 'rating'
                         */
                        $rows = [
                            ['key' => 'price',    'label' => 'Giá bán',         'icon' => 'bi-tag',           'type' => 'price'],
                            ['key' => 'category', 'label' => 'Thương hiệu',     'icon' => 'bi-building',      'type' => 'field'],
                            ['key' => 'storage',  'label' => 'Bộ nhớ trong',    'icon' => 'bi-device-hdd',    'type' => 'spec'],
                            ['key' => 'ram',      'label' => 'RAM',              'icon' => 'bi-memory',        'type' => 'spec'],
                            ['key' => 'cpu',      'label' => 'Chip xử lý',      'icon' => 'bi-cpu',           'type' => 'spec'],
                            ['key' => 'camera',   'label' => 'Camera',           'icon' => 'bi-camera',        'type' => 'spec'],
                            ['key' => 'screen',   'label' => 'Màn hình',         'icon' => 'bi-display',       'type' => 'spec'],
                            ['key' => 'battery',  'label' => 'Pin',              'icon' => 'bi-battery-full',  'type' => 'spec'],
                            ['key' => 'rating',   'label' => 'Đánh giá',        'icon' => 'bi-star',          'type' => 'rating'],
                            ['key' => 'stock',    'label' => 'Tình trạng kho',  'icon' => 'bi-box-seam',      'type' => 'stock'],
                        ];

                        // Pre-parse the specs for every product once
                        $parsed_specs = [];
                        foreach ($products as $p) {
                            $parsed_specs[$p['id']] = parse_specs($p['specs'] ?? '');
                        }

                        foreach ($rows as $row):
                        ?>
                        <tr class="compare-data-row">
                            <!-- Row label (first column) -->
                            <td class="compare-label-col">
                                <span class="compare-attr-label">
                                    <i class="bi <?php echo $row['icon']; ?> me-2 text-primary"></i>
                                    <?php echo $row['label']; ?>
                                </span>
                            </td>

                            <!-- Values for each product -->
                            <?php foreach ($products as $p):
                                $type  = $row['type'];
                                $key   = $row['key'];
                                $value = '—'; // default fallback

                                if ($type === 'price') {
                                    // Format price with Vietnamese formatting
                                    $value = '<span class="compare-price">'
                                           . number_format((int)$p['price'], 0, ',', '.') . '₫'
                                           . '</span>';

                                } elseif ($type === 'field') {
                                    // Direct column from the products table
                                    $value = htmlspecialchars($p[$key] ?? '—');

                                } elseif ($type === 'spec') {
                                    // Value parsed from the comma-separated specs string
                                    $value = htmlspecialchars($parsed_specs[$p['id']][$key] ?? '—');

                                } elseif ($type === 'rating') {
                                    // Star rating display
                                    $r = round((float)($p['rating'] ?? 0), 1);
                                    $stars = '';
                                    for ($s = 1; $s <= 5; $s++) {
                                        $stars .= $s <= $r
                                            ? '<i class="bi bi-star-fill text-warning"></i>'
                                            : '<i class="bi bi-star text-muted"></i>';
                                    }
                                    $value = $stars . ' <small class="text-muted ms-1">(' . $r . ')</small>';

                                } elseif ($type === 'stock') {
                                    // In stock / out of stock badge
                                    $in_stock = (int)($p['stock'] ?? 0) > 0;
                                    $value = $in_stock
                                        ? '<span class="compare-badge compare-badge-success"><i class="bi bi-check-circle me-1"></i>Còn hàng</span>'
                                        : '<span class="compare-badge compare-badge-danger"><i class="bi bi-x-circle me-1"></i>Hết hàng</span>';
                                }
                            ?>
                            <td class="compare-data-cell"><?php echo $value; ?></td>
                            <?php endforeach; ?>

                            <!-- Empty slot cells (maintain column alignment) -->
                            <?php for ($i = count($products); $i < 3; $i++): ?>
                            <td class="compare-data-cell compare-empty-slot"></td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Action row: Add to Cart buttons -->
                        <tr class="compare-action-row">
                            <td class="compare-label-col"></td>
                            <?php foreach ($products as $p): ?>
                            <td class="compare-data-cell text-center">
                                <?php if ((int)($p['stock'] ?? 0) > 0): ?>
                                    <a href="cart.php?add=<?php echo $p['id']; ?>"
                                       class="btn-main btn-primary w-100">
                                        <i class="bi bi-cart-plus me-1"></i>Thêm giỏ hàng
                                    </a>
                                <?php else: ?>
                                    <button class="btn-main btn-outline w-100" disabled>Hết hàng</button>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                            <?php for ($i = count($products); $i < 3; $i++): ?>
                            <td class="compare-data-cell compare-empty-slot"></td>
                            <?php endfor; ?>
                        </tr>

                    </tbody>
                </table>
            </div><!-- /.compare-table-wrapper -->

        <?php endif; ?>
    </div><!-- /.container-wide -->
</main>

<!-- ════════════════════════════════════════════════════════════════
     JAVASCRIPT — AJAX Remove / Clear handlers
     ════════════════════════════════════════════════════════════════ -->
<script>
/**
 * Send a POST request to api/compare.php and reload the page
 * to reflect the updated comparison list.
 *
 * @param {string} action      - 'remove' | 'clear'
 * @param {number} productId   - required for 'remove', omit for 'clear'
 */
async function updateCompare(action, productId = null) {
    // Build form data
    const body = new URLSearchParams({ action });
    if (productId) body.append('product_id', productId);

    try {
        const res  = await fetch('api/compare.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : body.toString()
        });
        const data = await res.json();

        if (data.success) {
            // Reload to re-render the table with the updated list
            window.location.reload();
        } else {
            alert(data.message || 'Có lỗi xảy ra.');
        }
    } catch (err) {
        console.error('[Compare] Error:', err);
        alert('Không thể kết nối máy chủ. Vui lòng thử lại.');
    }
}

/** Remove a single product from the comparison list */
function removeFromCompare(productId) {
    updateCompare('remove', productId);
}

/** Clear all products from the comparison list */
document.getElementById('clearAllBtn')?.addEventListener('click', function (e) {
    e.preventDefault();
    if (confirm('Bạn có chắc muốn xóa toàn bộ danh sách so sánh?')) {
        updateCompare('clear');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
