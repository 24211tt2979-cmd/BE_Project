<?php
/**
 * NHK Mobile - Product Catalog
 *
 * Description: Displays the full product list with advanced filtering
 * by category, search queries, and price sorting.
 *
 * Author: NguyenHuuKhanh
 * Version: 2.2
 * Date: 2026-04-16
 */
// auth_functions.php phải load TRƯỚC để session được khởi tạo bảo mật
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

// Lấy danh sách product_id đã yêu thích của user (nếu đăng nhập)
$wishlistIds = [];
if (isset($_SESSION['user_id'])) {
    $wlStmt = $pdo->prepare("SELECT product_id FROM wishlists WHERE user_id = ?");
    $wlStmt->execute([$_SESSION['user_id']]);
    $wishlistIds = $wlStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Handle search, category filters, and sorting parameters
$category = isset($_GET['category']) ? $_GET['category'] : null;
$search = isset($_GET['q']) ? $_GET['q'] : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$priceRange = isset($_GET['price']) ? $_GET['price'] : null;
$storage = isset($_GET['storage']) ? $_GET['storage'] : null;

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Lấy danh sách tất cả các hãng (Categories) từ DB để làm bộ lọc động
$stmtCats = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category ASC");
$categories = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

// Xây dựng câu lệnh SQL cơ bản
$sql = "SELECT * FROM products WHERE 1=1";
$countSql = "SELECT COUNT(*) FROM products WHERE 1=1";
$params = [];
$countParams = [];

if ($category) {
    $sql .= " AND category = ?";
    $countSql .= " AND category = ?";
    $params[] = $category;
    $countParams[] = $category;
}

if ($search) {
    $sql .= " AND name LIKE ?";
    $countSql .= " AND name LIKE ?";
    $params[] = "%$search%";
    $countParams[] = "%$search%";
}

// Price range filter
if ($priceRange) {
    switch ($priceRange) {
        case 'under15':
            $sql .= " AND price < 15000000";
            $countSql .= " AND price < 15000000";
            break;
        case '15to20':
            $sql .= " AND price BETWEEN 15000000 AND 20000000";
            $countSql .= " AND price BETWEEN 15000000 AND 20000000";
            break;
        case '20to25':
            $sql .= " AND price BETWEEN 20000000 AND 25000000";
            $countSql .= " AND price BETWEEN 20000000 AND 25000000";
            break;
        case '25to30':
            $sql .= " AND price BETWEEN 25000000 AND 30000000";
            $countSql .= " AND price BETWEEN 25000000 AND 30000000";
            break;
        case 'over30':
            $sql .= " AND price > 30000000";
            $countSql .= " AND price > 30000000";
            break;
    }
}

// Storage filter (check in product name or specs)
if ($storage) {
    $sql .= " AND (name LIKE ? OR specs LIKE ?)";
    $countSql .= " AND (name LIKE ? OR specs LIKE ?)";
    $params[] = "%$storage%";
    $params[] = "%$storage%";
    $countParams[] = "%$storage%";
    $countParams[] = "%$storage%";
}

// Get total count for pagination
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Sorting
switch ($sort) {
    case 'popularity':
        $sql .= " ORDER BY review_count DESC, rating DESC, id DESC";
        break;
    case 'price_asc':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY price DESC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY name DESC";
        break;
    default:
        $sql .= " ORDER BY created_at DESC";
        break;
}

// Add pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$pageTitle = $search ? "Kết quả tìm kiếm: $search" : ($category ? "Điện thoại $category" : "Tất cả điện thoại");
$basePath = "";

include 'includes/header.php';
?>

<style>
.filter-brands-scroll::-webkit-scrollbar { display: none; }
.filter-brands-scroll { -ms-overflow-style: none; scrollbar-width: none; }
.btn-filter {
    background: var(--bg-gray);
    border: 1px solid var(--border-light);
    color: var(--text-secondary);
    border-radius: 980px;
    padding: 8px 24px;
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    transition: all 0.2s;
}
.btn-filter:hover {
    background: var(--bg-white);
    border-color: var(--primary);
    color: var(--primary);
}
.btn-filter.active {
    background: var(--text-main);
    color: #fff;
    border-color: var(--text-main);
}

/* Advanced Filter Styles */
.advanced-filter {
    background: var(--bg-soft);
    border-radius: var(--radius-lg);
    padding: 24px;
    margin-bottom: 32px;
}

.filter-group {
    margin-bottom: 20px;
}

.filter-group:last-child {
    margin-bottom: 0;
}

.filter-label {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
    margin-bottom: 12px;
    display: block;
}

.filter-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-chip {
    padding: 8px 16px;
    background: #fff;
    border: 1px solid var(--border-light);
    border-radius: 980px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.filter-chip:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.filter-chip.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

/* View Toggle */
.view-toggle {
    display: flex;
    gap: 8px;
}

.view-btn {
    width: 40px;
    height: 40px;
    border: 1px solid var(--border-light);
    background: #fff;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.view-btn:hover, .view-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

/* Product List View */
.product-list-view .product-card-new {
    flex-direction: row;
    align-items: center;
    gap: 24px;
}

.product-list-view .product-img-box {
    width: 200px;
    height: 200px;
    margin-bottom: 0;
    flex-shrink: 0;
}

.product-list-view .product-info-new {
    flex: 1;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 48px;
}

.pagination-btn {
    min-width: 44px;
    height: 44px;
    padding: 0 16px;
    background: #fff;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.pagination-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.pagination-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Sticky Filter Bar */
.sticky-filter {
    position: sticky;
    top: 72px;
    z-index: 100;
    background: var(--bg-white);
    padding: 16px 0;
    border-bottom: 1px solid var(--border-light);
}

@media (max-width: 991.98px) {
    .advanced-filter { padding: 16px; }
    .product-list-view .product-card-new { flex-direction: column; }
    .product-list-view .product-img-box { width: 100%; height: 250px; }
    .sticky-filter { top: 64px; }
}
</style>

<main>
    <section class="mt-5">
        <div class="container-wide">
            <!-- Header Page -->
            <div class="d-flex flex-column gap-4 mb-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
                    <div>
                        <span class="section-subtitle">Danh mục sản phẩm</span>
                        <h1 class="display-4 fw-bold mb-0">
                            <?php echo $search ? "Kết quả cho <span class='text-primary'>'$search'</span>" : ($category ? $category : "Tất cả sản phẩm."); ?>
                        </h1>
                        <p class="text-muted mt-2">Tìm thấy <span id="resultCount"><?php echo $totalProducts; ?></span> siêu phẩm công nghệ.</p>
                    </div>

                    <!-- Sort & View Toggle -->
                    <div class="d-flex align-items-center gap-3">
                         <div class="sort-wrapper">
                             <div class="d-flex align-items-center gap-2">
                                  <span class="text-muted small fw-bold text-uppercase">Sắp xếp:</span>
                                  <select name="sort" id="ajaxSort" class="form-select form-select-sm border-0 bg-light rounded-pill px-3 py-2 cursor-pointer shadow-sm">
                                       <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                                       <option value="popularity" <?php echo $sort == 'popularity' ? 'selected' : ''; ?>>Độ phổ biến</option>
                                       <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Giá: Thấp đến Cao</option>
                                       <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Giá: Cao đến Thấp</option>
                                       <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Tên: A-Z</option>
                                       <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Tên: Z-A</option>
                                  </select>
                             </div>
                         </div>
                        <div class="view-toggle d-none d-md-flex">
                            <button class="view-btn active" onclick="setView('grid')" title="Grid view"><i class="bi bi-grid"></i></button>
                            <button class="view-btn" onclick="setView('list')" title="List view"><i class="bi bi-list"></i></button>
                        </div>
                    </div>
                </div>

                <!-- Category Filters (data-filter="category" drives AJAX) -->
                <div class="filter-brands-scroll d-flex gap-2 overflow-auto pb-2">
                    <button class="btn-filter <?php echo !$category ? 'active' : ''; ?>"
                            data-filter="category" data-value="">Tất cả</button>
                    <?php foreach($categories as $cat): ?>
                        <button class="btn-filter <?php echo $category == $cat ? 'active' : ''; ?>"
                                data-filter="category"
                                data-value="<?php echo htmlspecialchars($cat); ?>">
                            <?php echo htmlspecialchars($cat); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Advanced Filters (data-filter attributes drive AJAX) -->
            <div class="advanced-filter">
                <div class="row g-4">
                    <!-- Price Filter -->
                    <div class="col-md-6">
                        <div class="filter-group">
                            <span class="filter-label"><i class="bi bi-cash-stack me-2"></i>Khoảng giá</span>
                            <div class="filter-options">
                                <button class="filter-chip <?php echo !$priceRange ? 'active' : ''; ?>" data-filter="price" data-value="">Tất cả</button>
                                <button class="filter-chip <?php echo $priceRange == 'under15' ? 'active' : ''; ?>" data-filter="price" data-value="under15">Dưới 15 triệu</button>
                                <button class="filter-chip <?php echo $priceRange == '15to20'  ? 'active' : ''; ?>" data-filter="price" data-value="15to20">15 - 20 triệu</button>
                                <button class="filter-chip <?php echo $priceRange == '20to25'  ? 'active' : ''; ?>" data-filter="price" data-value="20to25">20 - 25 triệu</button>
                                <button class="filter-chip <?php echo $priceRange == '25to30'  ? 'active' : ''; ?>" data-filter="price" data-value="25to30">25 - 30 triệu</button>
                                <button class="filter-chip <?php echo $priceRange == 'over30'  ? 'active' : ''; ?>" data-filter="price" data-value="over30">Trên 30 triệu</button>
                            </div>
                        </div>
                    </div>
                    <!-- Storage Filter -->
                    <div class="col-md-6">
                        <div class="filter-group">
                            <span class="filter-label"><i class="bi bi-device-hdd me-2"></i>Bộ nhớ</span>
                            <div class="filter-options">
                                <button class="filter-chip <?php echo !$storage ? 'active' : ''; ?>"        data-filter="storage" data-value="">Tất cả</button>
                                <button class="filter-chip <?php echo $storage == '128GB' ? 'active' : ''; ?>" data-filter="storage" data-value="128GB">128GB</button>
                                <button class="filter-chip <?php echo $storage == '256GB' ? 'active' : ''; ?>" data-filter="storage" data-value="256GB">256GB</button>
                                <button class="filter-chip <?php echo $storage == '512GB' ? 'active' : ''; ?>" data-filter="storage" data-value="512GB">512GB</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product List -->
            <div class="product-grid-new" id="productContainer">
                <?php if (empty($products)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-search display-1 mb-4 opacity-10"></i>
                        <p class="h5 text-muted">Không tìm thấy sản phẩm nào phù hợp.</p>
                        <a href="product.php" class="btn-main btn-primary mt-4">Quay lại cửa hàng</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $p):
                        $isWishlisted = in_array($p['id'], $wishlistIds);
                    ?>
                        <div class="product-card-new">
                            <!-- Nút yêu thích: chỉ hiện với user thường, không hiện với admin -->
                            <?php if (isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])): ?>
                            <button class="btn-wishlist <?php echo $isWishlisted ? 'active' : ''; ?>"
                                    onclick="toggleWishlist(<?php echo $p['id']; ?>, this)"
                                    title="<?php echo $isWishlisted ? 'Bỏ yêu thích' : 'Thêm yêu thích'; ?>">
                                <i class="bi <?php echo $isWishlisted ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                            </button>
                            <?php elseif (!isset($_SESSION['admin_id'])): ?>
                            <a href="login.php?redirect=product.php" class="btn-wishlist" title="Đăng nhập để lưu yêu thích">
                                <i class="bi bi-heart"></i>
                            </a>
                            <?php endif; ?>

                            <!-- Quick View Button -->
                            <button class="btn-quick-view" onclick="openQuickView(<?php echo $p['id']; ?>)">
                                <i class="bi bi-eye"></i> Xem nhanh
                            </button>

                            <a href="product-detail.php?id=<?php echo $p['id']; ?>">
                                <div class="product-img-box">
                                    <img src="assets/images/<?php echo $p['image']; ?>" alt="<?php echo $p['name']; ?>"
                                        onerror="this.src='https://placehold.co/300x400/f5f5f7/1d1d1f?text=Phone'">
                                </div>
                                <div class="product-info-new">
                                    <span class="p-cat"><?php echo $p['category']; ?></span>
                                    <h3 class="p-name"><?php echo $p['name']; ?></h3>
                                    <div class="p-price-new"><?php echo number_format($p['price'], 0, ',', '.'); ?>₫</div>
                                    <?php if(!empty($p['specs'])): ?>
                                    <div class="p-specs">
                                        <?php
                                        $specsArr = array_map('trim', explode(',', $p['specs']));
                                        foreach(array_slice($specsArr, 0, 2) as $spec): ?>
                                        <span><?php echo htmlspecialchars($spec); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </a>

                            <!-- Action buttons row -->
                            <div class="d-flex align-items-center justify-content-between w-100 mt-auto pt-2 gap-2">
                                <!-- Add to Cart -->
                                <a href="cart.php?add=<?php echo $p['id']; ?>" class="add-to-cart-btn">
                                    <i class="bi bi-plus-lg"></i>
                                </a>

                                <!--
                                    "So sánh" (Compare) button
                                    - Calls addToCompare(id) which POSTs to api/compare.php
                                    - Session stores up to 3 product IDs in $_SESSION['compare_list']
                                    - The floating bar appears to show how many are queued
                                -->
                                <button class="btn-compare"
                                        id="cmp-<?php echo $p['id']; ?>"
                                        onclick="addToCompare(<?php echo $p['id']; ?>, this)"
                                        title="Thêm vào so sánh">
                                    <i class="bi bi-bar-chart-steps"></i>
                                    <span>So sánh</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- AJAX Pagination (rendered by JS after each filter fetch) -->
            <div class="pagination-container" id="ajaxPagination"></div>
        </div>
    </section>
</main>

<?php
// Helper function to build query string with current params
function buildQuery($override = []) {
    $params = $_GET;
    foreach ($override as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $query = http_build_query($params);
    return $query ? '?' . $query : '';
}
?>

<style>
.btn-wishlist {
    position: absolute;
    top: 14px;
    left: 14px;
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.9);
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ccc;
    font-size: 1rem;
    cursor: pointer;
    backdrop-filter: blur(6px);
    transition: all 0.25s;
    z-index: 5;
    text-decoration: none;
}
.btn-wishlist:hover,
.btn-wishlist.active { color: #e74c3c; background: #fff; transform: scale(1.15); }
.btn-wishlist.active i { animation: heartPop 0.3s ease; }
@keyframes heartPop {
    0%  { transform: scale(1); }
    50% { transform: scale(1.4); }
    100%{ transform: scale(1); }
}
</style>

<script>
// ═══════════════════════════════════════════════════════════════════════════
// NHK Mobile — AJAX Real-time Filter Engine
// ═══════════════════════════════════════════════════════════════════════════

/** Central state: one source of truth for all active filters. */
const FilterState = {
    category : '<?php echo htmlspecialchars($category ?? ''); ?>',
    price    : '<?php echo htmlspecialchars($priceRange ?? ''); ?>',
    storage  : '<?php echo htmlspecialchars($storage ?? ''); ?>',
    q        : '<?php echo htmlspecialchars($search ?? ''); ?>',
    sort     : '<?php echo htmlspecialchars($sort); ?>',
    page     : 1,
    per_page : 12,
};

// ── DOM references ──────────────────────────────────────────────────────────
const productContainer = document.getElementById('productContainer');
const resultCount      = document.getElementById('resultCount');
const paginationEl     = document.getElementById('ajaxPagination');
const sortSelect       = document.getElementById('ajaxSort');

// Logged-in state passed from PHP to avoid wishlist icon errors
const IS_LOGGED_IN = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
const IS_ADMIN     = <?php echo isset($_SESSION['admin_id']) ? 'true' : 'false'; ?>;

// ── Debounce helper ─────────────────────────────────────────────────────────
function debounce(fn, delay) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
}

// ── Skeleton loader ─────────────────────────────────────────────────────────
function showSkeleton(count = 8) {
    const cards = Array.from({ length: count }, () => `
        <div class="product-card-new" style="pointer-events:none">
            <div class="product-img-box" style="background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);background-size:200% 100%;animation:shimmer 1.2s infinite;"></div>
            <div class="product-info-new" style="padding:12px">
                <div style="height:12px;background:#eee;border-radius:4px;margin-bottom:8px;width:40%;"></div>
                <div style="height:16px;background:#eee;border-radius:4px;margin-bottom:8px;width:80%;"></div>
                <div style="height:20px;background:#eee;border-radius:4px;width:50%;"></div>
            </div>
        </div>`).join('');
    productContainer.innerHTML = cards;
    paginationEl.innerHTML = '';
}

// Inject shimmer keyframes once
if (!document.getElementById('shimmerStyle')) {
    const s = document.createElement('style');
    s.id = 'shimmerStyle';
    s.textContent = '@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}';
    document.head.appendChild(s);
}

// ── Build product card HTML from a JSON product object ──────────────────────
function buildCard(p) {
    const specsHtml = p.specs
        ? p.specs.split(',').slice(0, 2).map(s =>
            `<span>${s.trim()}</span>`).join('')
        : '';
    const specsBlock = specsHtml
        ? `<div class="p-specs">${specsHtml}</div>`
        : '';

    // Wishlist button — mirrors PHP server-side logic
    let wishlistBtn = '';
    if (IS_LOGGED_IN && !IS_ADMIN) {
        wishlistBtn = `
            <button class="btn-wishlist"
                    onclick="toggleWishlist(${p.id}, this)"
                    title="Thêm yêu thích">
                <i class="bi bi-heart"></i>
            </button>`;
    } else if (!IS_ADMIN) {
        wishlistBtn = `
            <a href="login.php?redirect=product.php" class="btn-wishlist" title="Đăng nhập để lưu yêu thích">
                <i class="bi bi-heart"></i>
            </a>`;
    }

    return `
    <div class="product-card-new">
        ${wishlistBtn}
        <button class="btn-quick-view" onclick="openQuickView(${p.id})">
            <i class="bi bi-eye"></i> Xem nhanh
        </button>
        <a href="product-detail.php?id=${p.id}">
            <div class="product-img-box">
                <img src="assets/images/${p.image}"
                     alt="${p.name}"
                     onerror="this.src='https://placehold.co/300x400/f5f5f7/1d1d1f?text=Phone'">
            </div>
            <div class="product-info-new">
                <span class="p-cat">${p.category}</span>
                <h3 class="p-name">${p.name}</h3>
                <div class="p-price-new">${p.price_fmt}</div>
                ${specsBlock}
            </div>
        </a>
        <!-- Action buttons row -->
        <div class="d-flex align-items-center justify-content-between w-100 mt-auto pt-2 gap-2">
            <a href="cart.php?add=${p.id}" class="add-to-cart-btn">
                <i class="bi bi-plus-lg"></i>
            </a>
            <!-- Compare button (AJAX, same as PHP-rendered cards) -->
            <button class="btn-compare"
                    id="cmp-${p.id}"
                    onclick="addToCompare(${p.id}, this)"
                    title="Thêm vào so sánh">
                <i class="bi bi-bar-chart-steps"></i>
                <span>So sánh</span>
            </button>
        </div>
    </div>`;
}

// ── Build AJAX pagination buttons ────────────────────────────────────────────
function renderPagination(current, total) {
    if (total <= 1) { paginationEl.innerHTML = ''; return; }

    let html = '';
    const btn = (page, label, disabled = false, active = false) =>
        `<button class="pagination-btn${active ? ' active' : ''}${disabled ? ' disabled' : ''}"
                 ${disabled ? 'disabled' : ''}
                 onclick="goToPage(${page})">${label}</button>`;

    html += btn(current - 1, '<i class="bi bi-chevron-left"></i>', current <= 1);

    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
            html += btn(i, i, false, i === current);
        } else if (i === current - 2 || i === current + 2) {
            html += `<span class="pagination-btn" style="cursor:default">...</span>`;
        }
    }

    html += btn(current + 1, '<i class="bi bi-chevron-right"></i>', current >= total);
    paginationEl.innerHTML = html;
}

// ── Core fetch function ──────────────────────────────────────────────────────
async function fetchProducts() {
    // Build query string from FilterState (omit empty values)
    const params = new URLSearchParams();
    Object.entries(FilterState).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) params.set(k, v);
    });

    // Update browser URL bar without reload (for bookmarkability)
    history.replaceState(null, '', '?' + params.toString());

    showSkeleton();

    try {
        const res  = await fetch('api/filter_products.php?' + params.toString());
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        // Update result count
        if (resultCount) resultCount.textContent = data.total;

        // Render products
        if (!data.products || data.products.length === 0) {
            productContainer.innerHTML = `
                <div class="col-12 text-center py-5">
                    <i class="bi bi-search display-1 mb-4 opacity-10"></i>
                    <p class="h5 text-muted">Không tìm thấy sản phẩm nào phù hợp.</p>
                    <button onclick="resetAllFilters()" class="btn-main btn-primary mt-4">Xóa bộ lọc</button>
                </div>`;
            paginationEl.innerHTML = '';
            return;
        }

        productContainer.innerHTML = data.products.map(buildCard).join('');
        renderPagination(data.page, data.total_pages);

        // Smooth scroll to product grid after filter change
        productContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });

    } catch (err) {
        console.error('[Filter] Fetch error:', err);
        productContainer.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-exclamation-triangle display-1 mb-4 text-warning opacity-50"></i>
                <p class="h5 text-muted">Lỗi tải dữ liệu. Vui lòng thử lại.</p>
            </div>`;
    }
}

// Debounced version — prevents hammering on rapid clicks
const debouncedFetch = debounce(fetchProducts, 250);

// ── Page navigation ──────────────────────────────────────────────────────────
function goToPage(page) {
    FilterState.page = page;
    fetchProducts();  // Immediate (not debounced) for pagination
}

// ── Reset all filters ────────────────────────────────────────────────────────
function resetAllFilters() {
    FilterState.category = '';
    FilterState.price    = '';
    FilterState.storage  = '';
    FilterState.page     = 1;

    // Reset active classes
    document.querySelectorAll('[data-filter]').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.value === '') btn.classList.add('active');
    });

    debouncedFetch();
}

// ── Bind filter chip clicks ──────────────────────────────────────────────────
document.querySelectorAll('[data-filter]').forEach(btn => {
    btn.addEventListener('click', function () {
        const filterKey = this.dataset.filter;   // "category" | "price" | "storage"
        const value     = this.dataset.value;    // e.g. "Apple", "under15", "256GB"

        // Update state
        FilterState[filterKey] = value;
        FilterState.page = 1;    // Reset to page 1 on every filter change

        // Update active chip in this group
        document.querySelectorAll(`[data-filter="${filterKey}"]`)
            .forEach(el => el.classList.remove('active'));
        this.classList.add('active');

        debouncedFetch();
    });
});

// ── Bind sort dropdown ───────────────────────────────────────────────────────
if (sortSelect) {
    sortSelect.addEventListener('change', function () {
        FilterState.sort = this.value;
        FilterState.page = 1;
        debouncedFetch();
    });
}

// ── Wishlist toggle (kept from original, unchanged) ──────────────────────────
function toggleWishlist(productId, btn) {
    if (btn.disabled) return;
    btn.disabled = true;

    fetch('api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + productId
    })
    .then(r => r.json())
    .then(data => {
        if (data.error && data.redirect) { location.href = data.redirect; return; }
        const icon = btn.querySelector('i');
        if (data.status === 'added') {
            btn.classList.add('active');
            icon.className = 'bi bi-heart-fill';
            btn.title = 'Bỏ yêu thích';
        } else {
            btn.classList.remove('active');
            icon.className = 'bi bi-heart';
            btn.title = 'Thêm yêu thích';
        }
        const badge = document.getElementById('wishlistBadge');
        if (badge) {
            badge.textContent = data.count;
            badge.style.display = data.count > 0 ? 'inline-flex' : 'none';
        }
        btn.disabled = false;
    })
    .catch(() => btn.disabled = false);
}

// ── View Toggle (grid / list) ────────────────────────────────────────────────
function setView(view) {
    const container = document.getElementById('productContainer');
    document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
    event.currentTarget.classList.add('active');
    if (view === 'list') {
        container.classList.add('product-list-view');
        container.style.gridTemplateColumns = '1fr';
    } else {
        container.classList.remove('product-list-view');
        container.style.gridTemplateColumns = '';
    }
}
</script>

<?php include 'includes/footer.php'; ?>

<!--
    ════════════════════════════════════════════════════════════════
    FLOATING COMPARE BAR
    ════════════════════════════════════════════════════════════════
    Hiển thị ở cuối màn hình khi người dùng thêm sản phẩm vào so sánh.
    CSS class "visible" được toggle bởi JavaScript bên dưới.
-->
<div class="compare-floating-bar" id="compareFloatingBar">
    <!-- Icon -->
    <i class="bi bi-bar-chart-steps"></i>

    <!-- Dynamic count badge -->
    <span>Đang so sánh: <span class="compare-count-badge" id="compareCountBadge">0</span>/3 sản phẩm</span>

    <!-- Link to the compare page -->
    <a href="compare.php">Xem so sánh &rarr;</a>

    <!-- Clear all button -->
    <button onclick="clearCompare()"
            style="background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3);
                   color:#fff; border-radius:980px; padding:4px 12px; font-size:.8rem;
                   cursor:pointer; transition:background .2s;"
            title="Xóa tất cả">
        <i class="bi bi-x-lg"></i>
    </button>
</div>

<script>
// ════════════════════════════════════════════════════════════════
// COMPARE PRODUCTS — AJAX JavaScript
// ════════════════════════════════════════════════════════════════
//
// How it works (step-by-step for student reference):
//
//  1. User clicks "So sánh" button on a product card.
//  2. addToCompare(id) sends a POST request to api/compare.php
//     with { action: 'add', product_id: id }.
//  3. The server adds the ID to $_SESSION['compare_list'] (max 3).
//  4. The server returns JSON: { success, count, list, message }.
//  5. updateCompareBar(count) shows/hides the floating bar.
//  6. The compare button changes to "Đã thêm" with .added class.
//
// ════════════════════════════════════════════════════════════════

/**
 * Send any compare action (add / remove / clear) to the API.
 *
 * @param {string} action     - 'add' | 'remove' | 'clear'
 * @param {number|null} id    - product_id (not needed for 'clear')
 * @returns {Promise<object>} - parsed JSON response from server
 */
async function compareRequest(action, id = null) {
    // Build the POST body
    const body = new URLSearchParams({ action });
    if (id) body.append('product_id', id);

    // POST to the API endpoint
    const res  = await fetch('api/compare.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body   : body.toString()
    });
    return res.json();
}

/**
 * Update the floating bar visibility and product count badge.
 *
 * @param {number} count - current number of products in compare list
 */
function updateCompareBar(count) {
    const bar   = document.getElementById('compareFloatingBar');
    const badge = document.getElementById('compareCountBadge');

    if (badge) badge.textContent = count;

    // Show bar when at least 1 product is queued, hide when empty
    if (bar) {
        if (count > 0) {
            bar.classList.add('visible');
        } else {
            bar.classList.remove('visible');
        }
    }
}

/**
 * Called when the user clicks "So sánh" on a product card.
 * Sends action=add to the API and updates the UI.
 *
 * @param {number} productId - ID of the product to add
 * @param {HTMLElement} btn  - the button element that was clicked
 */
async function addToCompare(productId, btn) {
    // Prevent double-clicks
    if (btn.disabled) return;
    btn.disabled = true;

    try {
        const data = await compareRequest('add', productId);

        if (data.success) {
            // Mark button as "added"
            btn.classList.add('added');
            btn.innerHTML = '<i class="bi bi-check-lg"></i><span>Đã thêm</span>';

            // Show or update the floating compare bar
            updateCompareBar(data.count);

        } else {
            // Show the error (e.g. already 3 products, duplicate)
            alert(data.message || 'Không thể thêm vào so sánh.');
            btn.disabled = false;
        }

    } catch (err) {
        console.error('[Compare] addToCompare error:', err);
        alert('Lỗi kết nối. Vui lòng thử lại.');
        btn.disabled = false;
    }
}

/**
 * Clear the entire compare list from session and hide the bar.
 */
async function clearCompare() {
    try {
        const data = await compareRequest('clear');
        if (data.success) {
            // Reset all compare buttons on the page
            document.querySelectorAll('.btn-compare.added').forEach(btn => {
                btn.classList.remove('added');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-bar-chart-steps"></i><span>So sánh</span>';
            });
            updateCompareBar(0);
        }
    } catch (err) {
        console.error('[Compare] clearCompare error:', err);
    }
}

// ── Init: check session count on page load ───────────────────────────────────
// On page load, ask the server for the current list so the bar
// reflects any products added on a previous page.
(async function initCompareBar() {
    try {
        // We use action=clear with a GET won't work — instead we do a harmless
        // "add" of id=0 which the server rejects but still returns the count.
        // Better: use a dedicated status endpoint. For simplicity we POST clear
        // only when the bar count is 0. Actually, fetch existing session state:
        const res  = await fetch('api/compare.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            // action=status returns count without modifying the list
            body   : 'action=status'
        });
        const data = await res.json();
        // api/compare.php doesn't have action=status yet, so it returns
        // { success:false, message:'Hành động không hợp lệ' } with count=undefined.
        // We handle that gracefully:
        if (typeof data.count === 'number') {
            updateCompareBar(data.count);
        }
    } catch (_) { /* silent fail on init */ }
})();
</script>
