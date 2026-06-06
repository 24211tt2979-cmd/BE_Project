<?php 
/**
 * NHK Mobile - Product Details
 * 
 * Description: Detailed view of a specific product, including 
 * high-resolution images, full specifications, pricing, stock levels, 
 * and customer reviews.
 * 
 * Author: NguyenHuuKhanh
 * Version: 2.1
 * Date: 2026-04-08
 */
// auth_functions.php phải load TRƯỚC để session được khởi tạo bảo mật
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

// Retrieve product ID and fetch data from DB
$id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$id) {
    header("Location: product.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    die("Sản phẩm không tồn tại!");
}

// Kiểm tra user đã lưu yêu thích sản phẩm này chưa
$isWishlisted = false;
if (isset($_SESSION['user_id'])) {
    $wlChk = $pdo->prepare("SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?");
    $wlChk->execute([$_SESSION['user_id'], $product['id']]);
    $isWishlisted = (bool)$wlChk->fetchColumn();
}

// Fetch related products (same category, excluding current product)
$isPostgres = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql') !== false);
$orderBy = $isPostgres ? 'RANDOM()' : 'RAND()';
$stmtRelated = $pdo->prepare("SELECT * FROM products WHERE category = ? AND id <> ? ORDER BY $orderBy LIMIT 4");
$stmtRelated->execute([$product['category'], $product['id']]);
$relatedProducts = $stmtRelated->fetchAll();

if (empty($relatedProducts)) {
    $stmtRelated = $pdo->prepare("SELECT * FROM products WHERE id <> ? ORDER BY $orderBy LIMIT 4");
    $stmtRelated->execute([$product['id']]);
    $relatedProducts = $stmtRelated->fetchAll();
}

$pageTitle = "NHK Mobile | " . $product['name'];
$basePath = "";
include 'includes/header.php';
?>

<main>
    <section class="mt-5">
        <div class="container-wide">
            <div class="row g-5 align-items-center">
                <!-- Product Image -->
                <div class="col-lg-6">
                    <div class="bg-light p-5 rounded-4 text-center border">
                        <img src="assets/images/<?php echo $product['image']; ?>" class="img-fluid" 
                             alt="<?php echo $product['name']; ?>" 
                             style="max-height: 600px;"
                             onerror="this.src='https://placehold.co/600x800/f5f5f7/1d1d1f?text=Phone'">
                    </div>
                </div>

                <!-- Product Info -->
                <div class="col-lg-6">
                    <div class="ps-lg-5">
                        <nav aria-label="breadcrumb" class="mb-4">
                            <ol class="breadcrumb small">
                                <li class="breadcrumb-item"><a href="product.php" class="text-muted">Sản phẩm</a></li>
                                <li class="breadcrumb-item active text-primary fw-bold"><?php echo $product['category']; ?></li>
                            </ol>
                        </nav>
                        
                        <?php 
                        $discount = (int)($product['discount'] ?? 0);
                        $priceOld = $product['price'];
                        $priceActual = $discount > 0 ? $priceOld * (100 - $discount) / 100 : $priceOld;
                        ?>
                        <h1 class="display-4 fw-bold mb-3"><?php echo $product['name']; ?></h1>
                        <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
                            <span class="h2 text-primary fw-bold mb-0"><?php echo number_format($priceActual, 0, ',', '.'); ?>₫</span>
                            <?php if ($discount > 0): ?>
                                <span style="font-size: 18px; color: #86868b; text-decoration: line-through;"><?php echo number_format($priceOld, 0, ',', '.'); ?>₫</span>
                                <span class="badge bg-danger rounded-pill px-3 py-2 small fw-bold">-<?php echo $discount; ?>%</span>
                            <?php endif; ?>
                            <?php if ($product['stock'] > 0): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-2 small fw-bold">
                                    <i class="bi bi-check-circle-fill me-1"></i> Còn hàng (<?php echo $product['stock']; ?> chiếc)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-3 py-2 small fw-bold">
                                    <i class="bi bi-x-circle-fill me-1"></i> Tạm hết hàng
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($product['specs'])): ?>
                        <div class="product-detail-specs mb-4">
                            <?php
                            $specsArr = array_filter(array_map('trim', explode(',', $product['specs'])));
                            foreach ($specsArr as $spec):
                            ?>
                                <span><?php echo htmlspecialchars($spec); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="mb-5">
                            <h5 class="fw-bold mb-3 text-uppercase small letter-spacing text-muted">Mô tả sản phẩm</h5>
                            <p class="text-secondary leading-relaxed fs-5">
                                <?php echo nl2br($product['description'] ? $product['description'] : 'Trải nghiệm công nghệ đỉnh cao với thiết kế tinh tế và hiệu năng mạnh mẽ nhất hiện nay.'); ?>
                            </p>
                        </div>



                        <div class="d-flex flex-column gap-3">
                            <?php if ($product['stock'] > 0): ?>
                                <a href="cart.php?add=<?php echo $product['id']; ?>" class="btn-main btn-primary w-100 py-3 fs-5">Thêm vào giỏ hàng</a>
                                <a href="cart.php?add=<?php echo $product['id']; ?>&installment=1" class="btn-main btn-outline w-100 py-3 fs-5">Mua trả góp 0%</a>
                            <?php else: ?>
                                <button class="btn-main btn-outline w-100 py-3 fs-5" disabled>Hết hàng</button>
                            <?php endif; ?>

                            <!-- Nút yêu thích: chỉ user thường -->
                            <?php if (isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])): ?>
                            <button id="detailWishlistBtn"
                                    class="btn-detail-wishlist <?php echo $isWishlisted ? 'active' : ''; ?>"
                                    onclick="toggleWishlistDetail(<?php echo $product['id']; ?>, this)">
                                <i class="bi <?php echo $isWishlisted ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                                <span><?php echo $isWishlisted ? 'Đã lưu yêu thích' : 'Lưu yêu thích'; ?></span>
                            </button>
                            <?php elseif (!isset($_SESSION['admin_id'])): ?>
                            <a href="login.php?redirect=product-detail.php?id=<?php echo $product['id']; ?>" class="btn-detail-wishlist">
                                <i class="bi bi-heart"></i>
                                <span>Đăng nhập để lưu</span>
                            </a>
                            <?php endif; ?>
                        </div>

                        <!-- Trust badges -->
                        <div class="row g-4 mt-5">
                            <div class="col-6">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="nav-icon bg-light"><i class="bi bi-shield-check text-primary"></i></div>
                                    <span class="small fw-bold">Bảo hành 12 tháng</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="nav-icon bg-light"><i class="bi bi-truck text-primary"></i></div>
                                    <span class="small fw-bold">Giao hàng miễn phí</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Products Section -->
            <?php if (!empty($relatedProducts)): ?>
            <div class="mt-5 pt-5 border-top animate-reveal">
                <div class="section-title-box text-start mb-5">
                    <span class="section-subtitle">Gợi ý mua sắm</span>
                    <h2 class="display-5 fw-bold">Sản phẩm liên quan.</h2>
                </div>
                <div class="product-grid-new">
                    <?php foreach ($relatedProducts as $rp): ?>
                        <div class="product-card-new">
                            <a href="product-detail.php?id=<?php echo $rp['id']; ?>">
                                <div class="product-img-box">
                                    <?php if($rp['is_featured']): ?>
                                        <span class="badge-hot">Hot Deal</span>
                                    <?php endif; ?>
                                    <img src="assets/images/<?php echo $rp['image']; ?>" alt="<?php echo $rp['name']; ?>"
                                         onerror="this.src='https://placehold.co/300x400/f5f5f7/1d1d1f?text=Phone'">
                                </div>
                                <div class="product-info-new">
                                    <span class="p-cat"><?php echo $rp['category']; ?></span>
                                    <h3 class="p-name"><?php echo $rp['name']; ?></h3>
                                    <div class="p-price-new"><?php echo number_format($rp['price'], 0, ',', '.'); ?>₫</div>
                                    <?php if(!empty($rp['specs'])): ?>
                                    <div class="p-specs">
                                        <?php
                                        $specsArr = array_map('trim', explode(',', $rp['specs']));
                                        foreach(array_slice($specsArr, 0, 2) as $spec): ?>
                                        <span><?php echo htmlspecialchars($spec); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <a href="cart.php?add=<?php echo $rp['id']; ?>" class="add-to-cart-btn">
                                <i class="bi bi-plus-lg"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reviews Section -->
            <div class="mt-5 pt-5 border-top">
                <div class="section-title-box text-start mb-5">
                    <span class="section-subtitle">Phản hồi khách hàng</span>
                    <h2 class="display-5 fw-bold">Đánh giá & Nhận xét.</h2>
                </div>

                <div class="row g-5">
                    <div class="col-lg-4">
                        <div class="bg-light p-5 rounded-4 text-center border">
                            <h1 class="display-2 fw-bold mb-0" id="avg-rating">0.0</h1>
                            <div class="text-warning fs-3 my-3" id="star-rating">
                                <i class="bi bi-star"></i><i class="bi bi-star"></i><i class="bi bi-star"></i><i class="bi bi-star"></i><i class="bi bi-star"></i>
                            </div>
                            <p class="text-muted mb-4" id="total-reviews">0 đánh giá</p>

                            <!-- Rating Breakdown Bars (5★ → 1★) -->
                            <div id="rating-breakdown" class="text-start">
                                <?php foreach ([5,4,3,2,1] as $n): ?>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="small fw-bold text-nowrap" style="width:20px;"><?php echo $n; ?></span>
                                    <i class="bi bi-star-fill text-warning" style="font-size:.75rem;"></i>
                                    <div class="progress flex-grow-1" style="height:8px; border-radius:999px;">
                                        <div class="progress-bar bg-warning" role="progressbar"
                                             id="bar-<?php echo $n; ?>"
                                             style="width:0%; border-radius:999px;"
                                             aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <span class="small text-muted" id="count-<?php echo $n; ?>">0</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card border-0 bg-light rounded-4 mb-5 shadow-sm">
                            <div class="card-body p-4 p-md-5">
                                <h4 class="fw-bold mb-4">Viết đánh giá của bạn</h4>

                                <?php if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])): ?>
                                <!-- Login Gate: shown to guests -->
                                <div class="alert alert-warning d-flex align-items-center gap-3 rounded-3 mb-4">
                                    <i class="bi bi-lock-fill fs-4"></i>
                                    <div>
                                        <strong>Bạn cần đăng nhập để gửi đánh giá.</strong><br>
                                        <a href="login.php?redirect=<?php echo urlencode('product-detail.php?id=' . $product['id']); ?>" class="alert-link">
                                            Đăng nhập ngay &rarr;
                                        </a>
                                        &nbsp;hoặc
                                        <a href="register.php" class="alert-link">Tạo tài khoản</a>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <form id="review-form" <?php if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])): ?>style="opacity:.45; pointer-events:none; user-select:none;"<?php endif; ?>>
                                    <input type="hidden" id="product_id" value="<?php echo $product['id']; ?>">

                                    <!-- ── 5-Star Clickable Rating ─────────────────── -->
                                    <div class="mb-4">
                                        <label class="form-label text-muted fw-bold small">ĐÁNH GIÁ CỦA BẠN</label>
                                        <div id="star-selector" class="rating-select" style="cursor: pointer; display: inline-flex; gap: 6px;">
                                            <i class="bi bi-star-fill rating-star" data-value="1"></i>
                                            <i class="bi bi-star-fill rating-star" data-value="2"></i>
                                            <i class="bi bi-star-fill rating-star" data-value="3"></i>
                                            <i class="bi bi-star-fill rating-star" data-value="4"></i>
                                            <i class="bi bi-star-fill rating-star" data-value="5"></i>
                                        </div>
                                        <input type="hidden" id="rating_val" value="5">
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-12">
                                            <input type="text" class="form-control rounded-3 py-3" id="review_title" placeholder="Tiêu đề đánh giá">
                                        </div>
                                        <div class="col-12">
                                            <textarea class="form-control rounded-3 py-3" id="review_content" rows="4" placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm này *" required></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label text-muted fw-bold small">HÌNH ẢNH MINH HỌA (tùy chọn)</label>
                                            <input type="file" id="review_image" class="form-control rounded-3" accept="image/*">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn-main btn-primary px-5">Gửi đánh giá</button>
                                    <div id="review-msg" class="mt-3"></div>
                                </form>
                            </div>
                        </div>

                        <div id="reviews-list">
                            <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
                        </div>
                        
                        <div class="text-center mt-5">
                            <button id="load-more-btn" class="btn-main btn-outline d-none">Xem thêm đánh giá</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
/* ─ Star colours ──────────────────────────────────────────────────────────────────────── */
.text-warning,
.rating-star.bi-star-fill,
#star-rating .bi-star-fill,
#star-rating .bi-star-half { color: #FF9500 !important; }

/* ─ 5-Star Clickable Selector ─────────────────────────────────────────────────────── */
#star-selector {
    /* Reverse the star order so CSS sibling trick works left-to-right */
}
.rating-star {
    transition: transform 0.15s ease, color 0.15s ease;
    font-size: 2rem;          /* fs-2 equivalent */
    color: #FF9500;           /* filled gold by default (value=5) */
}
.rating-star:hover,
.rating-star.hovered {
    transform: scale(1.25);
    color: #FF9500;
}
.rating-star.dim {
    color: #ddd;              /* un-hovered stars to the right go grey */
}

/* ─ Rating breakdown bars ─────────────────────────────────────────────────────────── */
#rating-breakdown .progress { background: #e9e9e9; }
#rating-breakdown .progress-bar { transition: width .6s cubic-bezier(.4,0,.2,1); }

.breadcrumb-item + .breadcrumb-item::before { content: "•"; color: var(--text-muted); }

.product-detail-specs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.product-detail-specs span {
    display: inline-flex;
    align-items: center;
    min-height: 34px;
    padding: 7px 12px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    background: var(--bg-soft);
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 600;
}

.btn-detail-wishlist {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 16px;
    border-radius: 980px;
    border: 2px solid #e0e0e0;
    background: #fff;
    color: #888;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    text-decoration: none;
}
.btn-detail-wishlist:hover {
    border-color: #e74c3c;
    color: #e74c3c;
    transform: translateY(-2px);
}
.btn-detail-wishlist.active {
    border-color: #e74c3c;
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    color: #fff;
}
.btn-detail-wishlist.active:hover {
    opacity: 0.9;
    color: #fff;
}
.btn-detail-wishlist i { font-size: 1.2rem; }
</style>

<script>
function toggleWishlistDetail(productId, btn) {
    if (btn.disabled) return;
    btn.disabled = true;
    const icon = btn.querySelector('i');
    const text = btn.querySelector('span');

    fetch('api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + productId
    })
    .then(r => r.json())
    .then(data => {
        if (data.error && data.redirect) { location.href = data.redirect; return; }
        if (data.status === 'added') {
            btn.classList.add('active');
            icon.className = 'bi bi-heart-fill';
            text.textContent = 'Đã lưu yêu thích';
        } else {
            btn.classList.remove('active');
            icon.className = 'bi bi-heart';
            text.textContent = 'Lưu yêu thích';
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
</script>

<script src="assets/js/product-reviews.js?v=2.0"></script>

<?php include 'includes/footer.php'; ?>
