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
                        <p class="text-muted mt-2">Tìm thấy <?php echo $totalProducts; ?> siêu phẩm công nghệ.</p>
                    </div>

                    <!-- Sort & View Toggle -->
                    <div class="d-flex align-items-center gap-3">
                        <div class="sort-wrapper">
                             <form action="product.php" method="GET" id="sortForm" class="d-flex align-items-center gap-2">
                                  <?php if($category): ?><input type="hidden" name="category" value="<?php echo $category; ?>"><?php endif; ?>
                                  <?php if($search): ?><input type="hidden" name="q" value="<?php echo $search; ?>"><?php endif; ?>
                                  <?php if($priceRange): ?><input type="hidden" name="price" value="<?php echo $priceRange; ?>"><?php endif; ?>
                                  <?php if($storage): ?><input type="hidden" name="storage" value="<?php echo $storage; ?>"><?php endif; ?>
                                  <span class="text-muted small fw-bold text-uppercase">Sắp xếp:</span>
                                  <select name="sort" class="form-select form-select-sm border-0 bg-light rounded-pill px-3 py-2 cursor-pointer shadow-sm" onchange="this.form.submit()">
                                       <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                                       <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Giá: Thấp đến Cao</option>
                                       <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Giá: Cao đến Thấp</option>
                                       <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Tên: A-Z</option>
                                       <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Tên: Z-A</option>
                                  </select>
                             </form>
                        </div>
                        <div class="view-toggle d-none d-md-flex">
                            <button class="view-btn active" onclick="setView('grid')" title="Grid view"><i class="bi bi-grid"></i></button>
                            <button class="view-btn" onclick="setView('list')" title="List view"><i class="bi bi-list"></i></button>
                        </div>
                    </div>
                </div>

                <!-- Category Filters -->
                <div class="filter-brands-scroll d-flex gap-2 overflow-auto pb-2">
                    <a href="product.php<?php echo buildQuery(['category' => null]); ?>" class="btn-filter <?php echo !$category ? 'active' : ''; ?>">Tất cả</a>
                    <?php foreach($categories as $cat): ?>
                         <a href="product.php<?php echo buildQuery(['category' => $cat]); ?>"
                            class="btn-filter <?php echo $category == $cat ? 'active' : ''; ?>">
                            <?php echo $cat; ?>
                         </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Advanced Filters -->
            <div class="advanced-filter">
                <div class="row g-4">
                    <!-- Price Filter -->
                    <div class="col-md-6">
                        <div class="filter-group">
                            <span class="filter-label"><i class="bi bi-cash-stack me-2"></i>Khoảng giá</span>
                            <div class="filter-options">
                                <a href="product.php<?php echo buildQuery(['price' => null]); ?>" class="filter-chip <?php echo !$priceRange ? 'active' : ''; ?>">Tất cả</a>
                                <a href="product.php<?php echo buildQuery(['price' => 'under15']); ?>" class="filter-chip <?php echo $priceRange == 'under15' ? 'active' : ''; ?>">Dưới 15 triệu</a>
                                <a href="product.php<?php echo buildQuery(['price' => '15to20']); ?>" class="filter-chip <?php echo $priceRange == '15to20' ? 'active' : ''; ?>">15 - 20 triệu</a>
                                <a href="product.php<?php echo buildQuery(['price' => '20to25']); ?>" class="filter-chip <?php echo $priceRange == '20to25' ? 'active' : ''; ?>">20 - 25 triệu</a>
                                <a href="product.php<?php echo buildQuery(['price' => '25to30']); ?>" class="filter-chip <?php echo $priceRange == '25to30' ? 'active' : ''; ?>">25 - 30 triệu</a>
                                <a href="product.php<?php echo buildQuery(['price' => 'over30']); ?>" class="filter-chip <?php echo $priceRange == 'over30' ? 'active' : ''; ?>">Trên 30 triệu</a>
                            </div>
                        </div>
                    </div>
                    <!-- Storage Filter -->
                    <div class="col-md-6">
                        <div class="filter-group">
                            <span class="filter-label"><i class="bi bi-device-hdd me-2"></i>Bộ nhớ</span>
                            <div class="filter-options">
                                <a href="product.php<?php echo buildQuery(['storage' => null]); ?>" class="filter-chip <?php echo !$storage ? 'active' : ''; ?>">Tất cả</a>
                                <a href="product.php<?php echo buildQuery(['storage' => '128GB']); ?>" class="filter-chip <?php echo $storage == '128GB' ? 'active' : ''; ?>">128GB</a>
                                <a href="product.php<?php echo buildQuery(['storage' => '256GB']); ?>" class="filter-chip <?php echo $storage == '256GB' ? 'active' : ''; ?>">256GB</a>
                                <a href="product.php<?php echo buildQuery(['storage' => '512GB']); ?>" class="filter-chip <?php echo $storage == '512GB' ? 'active' : ''; ?>">512GB</a>
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
                            <a href="cart.php?add=<?php echo $p['id']; ?>" class="add-to-cart-btn">
                                <i class="bi bi-plus-lg"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <a href="product.php<?php echo buildQuery(['page' => max(1, $page - 1)]); ?>" class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                        <a href="product.php<?php echo buildQuery(['page' => $i]); ?>" class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                        <span class="pagination-btn" style="cursor: default;">...</span>
                    <?php endif; ?>
                <?php endfor; ?>

                <a href="product.php<?php echo buildQuery(['page' => min($totalPages, $page + 1)]); ?>" class="pagination-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
            <?php endif; ?>
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
        // Cập nhật badge wishlist trên navbar
        const badge = document.getElementById('wishlistBadge');
        if (badge) {
            badge.textContent = data.count;
            badge.style.display = data.count > 0 ? 'inline-flex' : 'none';
        }
        btn.disabled = false;
    })
    .catch(() => btn.disabled = false);
}

// View Toggle
function setView(view) {
    const container = document.getElementById('productContainer');
    const buttons = document.querySelectorAll('.view-btn');

    buttons.forEach(btn => btn.classList.remove('active'));
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
