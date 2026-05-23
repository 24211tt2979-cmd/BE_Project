<?php
/**
 * NHK Mobile - Trang Danh sách Yêu thích (Wishlist)
 * Hiển thị tất cả sản phẩm user đã lưu yêu thích.
 */
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

// Bắt buộc đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=wishlist.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Lấy danh sách sản phẩm yêu thích
$stmt = $pdo->prepare("
    SELECT p.*, w.created_at AS saved_at
    FROM wishlists w
    JOIN products p ON w.product_id = p.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$stmt->execute([$userId]);
$wishlistItems = $stmt->fetchAll();

$pageTitle = "Yêu thích của tôi | NHK Mobile";
$basePath  = "";
include 'includes/header.php';
?>

<style>
.wishlist-wrapper {
    background: #f4f6fb;
    min-height: 100vh;
    padding-top: 90px;
    padding-bottom: 60px;
}

.wl-header {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    border-radius: 1.5rem;
    padding: 2.5rem 2rem;
    color: #fff;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}
.wl-header::before {
    content: '\f590';
    font-family: 'bootstrap-icons';
    position: absolute;
    right: -20px;
    top: -20px;
    font-size: 9rem;
    opacity: 0.08;
    line-height: 1;
}
.wl-header h1 { font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem; }
.wl-header p  { opacity: 0.85; margin: 0; font-size: 0.95rem; }

/* Grid sản phẩm */
.wl-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.5rem;
}

.wl-card {
    background: #fff;
    border-radius: 1.25rem;
    overflow: hidden;
    box-shadow: 0 2px 20px rgba(0,0,0,0.06);
    transition: transform 0.3s, box-shadow 0.3s;
    position: relative;
    display: flex;
    flex-direction: column;
}
.wl-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 36px rgba(0,0,0,0.12);
}

.wl-img-box {
    background: #f5f5f7;
    height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    overflow: hidden;
}
.wl-img-box img {
    max-height: 100%;
    object-fit: contain;
    transition: transform 0.4s;
}
.wl-card:hover .wl-img-box img { transform: scale(1.07); }

/* Nút xóa yêu thích */
.btn-remove-wl {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.9);
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #e74c3c;
    font-size: 1.1rem;
    cursor: pointer;
    backdrop-filter: blur(6px);
    transition: background 0.2s, transform 0.2s;
    z-index: 5;
}
.btn-remove-wl:hover { background: #e74c3c; color: #fff; transform: scale(1.1); }

.wl-body {
    padding: 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.wl-cat  { font-size: 0.72rem; font-weight: 800; color: #007AFF; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.3rem; }
.wl-name { font-size: 1rem; font-weight: 700; color: #1d1d1f; margin-bottom: 0.5rem; line-height: 1.3; flex: 1; }
.wl-price { font-size: 1.1rem; font-weight: 800; color: #1d1d1f; margin-bottom: 1rem; }

.wl-actions { display: flex; gap: 0.6rem; }
.btn-wl-cart {
    flex: 1;
    background: #1d1d1f;
    color: #fff;
    border: none;
    border-radius: 50rem;
    padding: 0.6rem;
    font-size: 0.82rem;
    font-weight: 700;
    text-align: center;
    text-decoration: none;
    transition: background 0.2s, transform 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.btn-wl-cart:hover { background: #007AFF; color: #fff; transform: translateY(-2px); }
.btn-wl-detail {
    padding: 0.6rem 1rem;
    border: 1.5px solid #e0e0e0;
    border-radius: 50rem;
    color: #555;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    transition: border-color 0.2s, color 0.2s;
    display: flex;
    align-items: center;
}
.btn-wl-detail:hover { border-color: #007AFF; color: #007AFF; }

/* Empty state */
.wl-empty {
    background: #fff;
    border-radius: 1.5rem;
    padding: 5rem 2rem;
    text-align: center;
    box-shadow: 0 2px 20px rgba(0,0,0,0.06);
}
.wl-empty-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #ffe4e8, #fff0f0);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #ff416c;
    margin: 0 auto 1.5rem;
}
</style>

<main class="wishlist-wrapper">
    <div class="container">

        <!-- Header -->
        <div class="wl-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-heart-fill me-2"></i>Yêu thích của tôi</h1>
                    <p><?php echo count($wishlistItems); ?> sản phẩm đã lưu</p>
                </div>
                <?php if (!empty($wishlistItems)): ?>
                <a href="product.php" class="btn btn-light rounded-pill px-4 fw-700 text-dark text-decoration-none">
                    <i class="bi bi-plus-lg me-2"></i>Thêm sản phẩm
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($wishlistItems)): ?>
        <!-- Empty state -->
        <div class="wl-empty">
            <div class="wl-empty-icon">
                <i class="bi bi-heart"></i>
            </div>
            <h4 class="fw-800 mb-2">Chưa có sản phẩm yêu thích</h4>
            <p class="text-muted mb-4">Nhấn vào biểu tượng ♡ trên sản phẩm để lưu vào danh sách yêu thích của bạn.</p>
            <a href="product.php" class="btn btn-dark rounded-pill px-5 py-3 fw-700 shadow-sm text-decoration-none">
                <i class="bi bi-phone me-2"></i>Khám phá sản phẩm
            </a>
        </div>

        <?php else: ?>
        <!-- Grid sản phẩm -->
        <div class="wl-grid" id="wishlistGrid">
            <?php foreach ($wishlistItems as $item): ?>
            <div class="wl-card" id="wlCard<?php echo $item['id']; ?>">
                <!-- Nút xóa -->
                <button class="btn-remove-wl" 
                        onclick="removeFromWishlist(<?php echo $item['id']; ?>, this)"
                        title="Xóa khỏi yêu thích">
                    <i class="bi bi-heart-fill"></i>
                </button>

                <!-- Ảnh -->
                <a href="product-detail.php?id=<?php echo $item['id']; ?>">
                    <div class="wl-img-box">
                        <img src="assets/images/<?php echo $item['image']; ?>"
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             onerror="this.src='https://placehold.co/300x300/f5f5f7/1d1d1f?text=NHK'">
                    </div>
                </a>

                <!-- Info -->
                <div class="wl-body">
                    <div class="wl-cat"><?php echo htmlspecialchars($item['category']); ?></div>
                    <div class="wl-name"><?php echo htmlspecialchars($item['name']); ?></div>
                    <div class="wl-price">
                        <?php echo number_format($item['price'], 0, ',', '.'); ?>₫
                    </div>
                    <div class="wl-actions">
                        <?php if ($item['stock'] > 0): ?>
                        <a href="cart.php?add=<?php echo $item['id']; ?>" class="btn-wl-cart text-decoration-none">
                            <i class="bi bi-bag-plus"></i> Thêm giỏ
                        </a>
                        <?php else: ?>
                        <span class="btn-wl-cart" style="background:#ccc;cursor:default;">
                            <i class="bi bi-x-circle"></i> Hết hàng
                        </span>
                        <?php endif; ?>
                        <a href="product-detail.php?id=<?php echo $item['id']; ?>" class="btn-wl-detail text-decoration-none">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<script>
function removeFromWishlist(productId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + productId
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'removed') {
            const card = document.getElementById('wlCard' + productId);
            card.style.transition = 'opacity 0.4s, transform 0.4s';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.9)';
            setTimeout(() => {
                card.remove();
                // Cập nhật badge navbar
                updateWishlistBadge(data.count);
                // Nếu hết card, reload để show empty state
                if (document.querySelectorAll('.wl-card').length === 0) {
                    location.reload();
                }
            }, 400);
        }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-heart-fill"></i>'; });
}

function updateWishlistBadge(count) {
    const badge = document.getElementById('wishlistBadge');
    if (!badge) return;
    badge.textContent = count;
    badge.style.display = count > 0 ? 'inline-flex' : 'none';
}
</script>

<?php include 'includes/footer.php'; ?>
