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
                        
                        <h1 class="display-4 fw-bold mb-3"><?php echo $product['name']; ?></h1>
                        <p class="h2 text-primary fw-bold mb-4"><?php echo number_format($product['price'], 0, ',', '.'); ?>₫</p>
                        
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
                            <p class="text-muted mb-0" id="total-reviews">0 đánh giá</p>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card border-0 bg-light rounded-4 mb-5 shadow-sm">
                            <div class="card-body p-4 p-md-5">
                                <h4 class="fw-bold mb-4">Viết đánh giá của bạn</h4>
                                <form id="review-form">
                                    <input type="hidden" id="product_id" value="<?php echo $product['id']; ?>">
                                    <div class="mb-4">
                                        <label class="form-label text-muted fw-bold small">MỨC ĐỘ HÀI LÒNG</label>
                                        <div class="rating-select text-warning fs-2" style="cursor: pointer;">
                                            <i class="bi bi-star rating-star" data-value="1"></i>
                                            <i class="bi bi-star rating-star" data-value="2"></i>
                                            <i class="bi bi-star rating-star" data-value="3"></i>
                                            <i class="bi bi-star rating-star" data-value="4"></i>
                                            <i class="bi bi-star rating-star" data-value="5"></i>
                                        </div>
                                        <input type="hidden" id="rating_val" value="5">
                                    </div>
                                    
                                    <div class="row g-3 mb-3">
                                        <?php if(!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])): ?>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control rounded-3 py-3" id="reviewer_name" placeholder="Tên của bạn *" required>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="email" class="form-control rounded-3 py-3" id="reviewer_email" placeholder="Email (không bắt buộc)">
                                            </div>
                                        <?php endif; ?>
                                        <div class="col-12">
                                            <input type="text" class="form-control rounded-3 py-3" id="review_title" placeholder="Tiêu đề đánh giá">
                                        </div>
                                        <div class="col-12">
                                            <textarea class="form-control rounded-3 py-3" id="review_content" rows="4" placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm này *" required></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label text-muted fw-bold small">HÌNH ẢNH MINH HỌA</label>
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
.text-warning, .rating-star.bi-star-fill, #star-rating .bi-star-fill, #star-rating .bi-star-half { color: #FF9500 !important; }
.breadcrumb-item + .breadcrumb-item::before { content: "•"; color: var(--text-muted); }

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

<script src="assets/js/product-reviews.js?v=1.0.1"></script>

<?php include 'includes/footer.php'; ?>
