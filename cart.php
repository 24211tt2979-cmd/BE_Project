<?php
/**
 * NHK Mobile - Shopping Cart Management
 * 
 * Description: Handles shopping cart operations including adding, 
 * removing, and updating product quantities. Supports both standard 
 * purchase and installment plans.
 * 
 * Author: NguyenHuuKhanh
 * Version: 2.1
 * Date: 2026-04-08
 */
// QUAN TRỌNG: auth_functions.php phải load TRƯỚC để khởi tạo session
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';
require_once 'includes/cart_functions.php';

// LOGIC: Xem giỏ hàng KHÔNG cần login (dùng session)
// Nhưng thêm sản phẩm / checkout PHẢI login
syncCartWithDatabase($pdo);

if (isset($_GET['add'])) {
    require_login();
    
    $productId = (int)$_GET['add'];
    $installment = isset($_GET['installment']) ? (int)$_GET['installment'] : 0;
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if ($product && $product['stock'] > 0) {
        if ($installment === 1) {
            $_SESSION['cart'] = [];
            $_SESSION['is_installment'] = true;
        } else {
            $_SESSION['is_installment'] = false;
        }

        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId]['qty']++;
        } else {
            $_SESSION['cart'][$productId] = [
                'name' => $product['name'],
                'price' => $product['price'],
                'image' => $product['image'],
                'qty' => 1
            ];
        }
        syncCartWithDatabase($pdo);
    }

    if ($installment === 1) {
        header("Location: checkout.php");
    } else {
        header("Location: cart.php");
    }
    exit;
}

if (isset($_GET['remove'])) {
    $id = $_GET['remove'];
    unset($_SESSION['cart'][$id]);
    removeFromCartDB($pdo, $id);
    header("Location: cart.php");
    exit;
}

if (isset($_POST['update_cart'])) {
    foreach ($_POST['qty'] as $id => $qty) {
        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
            removeFromCartDB($pdo, $id);
        } else {
            $_SESSION['cart'][$id]['qty'] = $qty;
        }
    }
    syncCartWithDatabase($pdo);
    header("Location: cart.php");
    exit;
}

$pageTitle = "Giỏ hàng | NHK Mobile";
$basePath = "";
include 'includes/header.php';

$total = 0;
$cartItems = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
?>

<main>
    <section class="mt-5">
        <div class="container-wide">
            <div class="section-title-box text-start mb-5">
                <span class="section-subtitle">Giỏ hàng của bạn</span>
                <h1 class="display-4 fw-bold">Kiểm tra đơn hàng.</h1>
            </div>

            <?php if (empty($cartItems)): ?>
                <div class="text-center py-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 120px; height: 120px;">
                        <i class="bi bi-cart-x display-4 text-muted"></i>
                    </div>
                    <h3>Giỏ hàng đang trống</h3>
                    <p class="text-muted">Hãy chọn cho mình những sản phẩm tuyệt vời nhất.</p>
                    <a href="product.php" class="btn-main btn-primary mt-4">Tiếp tục mua sắm</a>
                </div>
            <?php else: ?>
                <form action="cart.php" method="POST">
                    <div class="row g-5">
                        <div class="col-lg-8">
                            <div class="cart-items-list">
                                <?php foreach ($cartItems as $id => $item): 
                                    $subtotal = $item['price'] * $item['qty'];
                                    $total += $subtotal;
                                ?>
                                    <div class="d-flex align-items-center justify-content-between py-4 border-bottom">
                                         <div class="d-flex align-items-center gap-4">
                                              <div class="bg-gray rounded-4 p-3" style="width: 120px; background: var(--bg-gray);">
                                                   <img src="assets/images/<?php echo $item['image']; ?>" class="img-fluid" 
                                                        onerror="this.src='https://placehold.co/200x200/f5f5f7/1d1d1f?text=Phone'">
                                              </div>
                                              <div>
                                                   <h4 class="fw-bold mb-1"><?php echo $item['name']; ?></h4>
                                                   <p class="text-primary fw-bold mb-0"><?php echo number_format($item['price'], 0, ',', '.'); ?>₫</p>
                                                   <div class="mt-2 d-flex align-items-center gap-3">
                                                        <div class="input-group input-group-sm" style="width: 100px;">
                                                            <input type="number" name="qty[<?php echo $id; ?>]" value="<?php echo $item['qty']; ?>" 
                                                                   class="form-control text-center rounded-pill border-light">
                                                        </div>
                                                        <a href="cart.php?remove=<?php echo $id; ?>" class="text-danger small" 
                                                           onclick="return confirm('Xóa khỏi giỏ hàng?')"><i class="bi bi-trash me-1"></i> Xóa</a>
                                                   </div>
                                              </div>
                                         </div>
                                         <div class="text-end">
                                              <div class="fw-bold fs-5"><?php echo number_format($subtotal, 0, ',', '.'); ?>₫</div>
                                         </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" name="update_cart" class="btn-main btn-outline py-2 px-4">Cập nhật giỏ hàng</button>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="bg-light p-4 rounded-4 shadow-sm border" style="position: sticky; top: 100px;">
                                <h3 class="fw-bold mb-4">Tóm tắt đơn hàng</h3>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Tạm tính</span>
                                    <span class="fw-medium"><?php echo number_format($total, 0, ',', '.'); ?>₫</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Giao hàng</span>
                                    <span class="text-success fw-bold">Miễn phí</span>
                                </div>

                                <!-- ── Discount Row (toggled via CSS/JS) ──────────────── -->
                                <?php
                                $couponInfo = $_SESSION['applied_coupon'] ?? null;
                                $hasCoupon  = ($couponInfo !== null);
                                $displayTotal = $hasCoupon ? $couponInfo['new_total'] : $total;
                                ?>
                                <div class="d-flex justify-content-between mb-3" id="discount-row" <?php if (!$hasCoupon) echo 'style="display:none;"'; ?>>
                                    <span class="text-muted d-flex align-items-center">
                                        Giảm giá 
                                        <span class="badge bg-success ms-2" id="applied-code-badge">
                                            <?php echo $hasCoupon ? $couponInfo['discount_percent'] . '%' : ''; ?>
                                        </span>
                                    </span>
                                    <span class="text-success fw-bold" id="discount-amount-display">
                                        <?php echo $hasCoupon ? '-' . number_format($couponInfo['discount_amount'], 0, ',', '.') . '₫' : '0₫'; ?>
                                    </span>
                                </div>
                                <!-- ── End Discount Row ──────────────────────────────── -->

                                <hr class="my-4">
                                <div class="d-flex justify-content-between mb-4">
                                    <h4 class="fw-bold">Tổng tiền</h4>
                                    <h4 class="fw-bold text-primary" id="cart-total-display"><?php echo number_format($displayTotal, 0, ',', '.'); ?>₫</h4>
                                </div>

                                <!-- ── Coupon Section ─────────────────────────────────── -->
                                <div class="mb-4 bg-white p-3 rounded-3 border">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-2">Mã giảm giá</label>
                                    
                                    <!-- Input group (shown when no coupon applied) -->
                                    <div class="input-group input-group-sm <?php if ($hasCoupon) echo 'd-none'; ?>" id="coupon-input-group">
                                        <input type="text" id="coupon-input" class="form-control rounded-start-pill border-light ps-3" placeholder="Nhập mã (e.g. WELCOME10)">
                                        <button class="btn btn-dark rounded-end-pill px-3" type="button" id="apply-coupon-btn">
                                            <span id="apply-btn-text">Áp dụng</span>
                                            <span class="spinner-border spinner-border-sm d-none" id="apply-btn-spinner" role="status"></span>
                                        </button>
                                    </div>

                                    <!-- Applied coupon alert (shown when coupon applied) -->
                                    <div class="d-flex align-items-center justify-content-between <?php if (!$hasCoupon) echo 'd-none'; ?>" id="remove-coupon-wrap">
                                        <div class="small fw-bold text-success">
                                            <i class="bi bi-tag-fill me-1"></i>
                                            Mã: <span id="active-coupon-code" class="text-uppercase"><?php echo $hasCoupon ? htmlspecialchars($couponInfo['code']) : ''; ?></span>
                                        </div>
                                        <button class="btn btn-link btn-sm text-danger text-decoration-none fw-bold p-0" type="button" id="remove-coupon-btn">
                                            <i class="bi bi-x-circle-fill"></i> Gỡ
                                        </button>
                                    </div>

                                    <!-- Feedback message -->
                                    <div id="coupon-feedback" class="mt-2 small d-none"></div>
                                </div>
                                <!-- ── End Coupon Section ─────────────────────────────── -->

                                <a href="checkout.php" class="btn-main btn-primary w-100 py-3">Tiến hành đặt hàng</a>
                                <p class="text-center text-muted small mt-3">Đã bao gồm thuế GTGT (nếu có)</p>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- ══════════════════════════════════════════════════════════════
     COUPON AJAX JAVASCRIPT
     ══════════════════════════════════════════════════════════════ -->
<script>
(function () {
    'use strict';

    // ── Helpers ────────────────────────────────────────────────────────────────
    /**
     * Format a number to Vietnamese currency style: 1,234,567₫
     * @param {number} value
     * @returns {string}
     */
    function formatVND(value) {
        return Math.round(value).toLocaleString('vi-VN') + '₫';
    }

    // Original cart total from PHP (raw number, no formatting)
    const ORIGINAL_TOTAL = <?php echo json_encode($total); ?>;

    // ── DOM references ─────────────────────────────────────────────────────────
    const inputEl         = document.getElementById('coupon-input');
    const applyBtn        = document.getElementById('apply-coupon-btn');
    const applyBtnText    = document.getElementById('apply-btn-text');
    const applySpinner    = document.getElementById('apply-btn-spinner');
    const feedbackEl      = document.getElementById('coupon-feedback');
    const discountRow     = document.getElementById('discount-row');
    const discountDisplay = document.getElementById('discount-amount-display');
    const totalDisplay    = document.getElementById('cart-total-display');
    const removeCouponWrap= document.getElementById('remove-coupon-wrap');
    const removeBtn       = document.getElementById('remove-coupon-btn');
    const appliedBadge    = document.getElementById('applied-code-badge');

    if (!applyBtn) return; // Guard: only run when cart has items

    // ── Show feedback message ──────────────────────────────────────────────────
    function showFeedback(message, type) {
        // type: 'success' | 'danger' | 'warning'
        feedbackEl.className = 'mt-2 small alert alert-' + type + ' py-2 px-3 rounded-3';
        feedbackEl.textContent = message;
        feedbackEl.classList.remove('d-none');
    }

    function hideFeedback() {
        feedbackEl.classList.add('d-none');
    }

    // ── Set loading state ──────────────────────────────────────────────────────
    function setLoading(loading) {
        applyBtn.disabled  = loading;
        inputEl.disabled   = loading;
        applyBtnText.textContent = loading ? '' : 'Áp dụng';
        applySpinner.classList.toggle('d-none', !loading);
    }

    // ── Apply coupon via AJAX ──────────────────────────────────────────────────
    applyBtn.addEventListener('click', function () {
        const code = inputEl.value.trim().toUpperCase();

        if (!code) {
            showFeedback('Vui lòng nhập mã giảm giá.', 'warning');
            inputEl.focus();
            return;
        }

        hideFeedback();
        setLoading(true);

        const formData = new FormData();
        formData.append('coupon_code', code);
        formData.append('cart_total', ORIGINAL_TOTAL);

        fetch('php/apply_coupon.php', {
            method: 'POST',
            body: formData
        })
        .then(function (response) {
            if (!response.ok) throw new Error('Network error: ' + response.status);
            return response.json();
        })
        .then(function (data) {
            setLoading(false);

            if (data.success) {
                // ── Update UI with discount info ───────────────────────────────
                showFeedback(data.message, 'success');

                // Show discount row
                discountRow.style.removeProperty('display');
                discountDisplay.textContent  = '-' + formatVND(data.discount_amount);
                appliedBadge.textContent      = data.discount_percent + '%';

                // Update total
                totalDisplay.textContent = formatVND(data.new_total);

                // Animate total
                totalDisplay.classList.add('fw-bolder');
                totalDisplay.style.transition = 'color 0.4s';
                totalDisplay.style.color = '#198754'; // green flash
                setTimeout(function () {
                    totalDisplay.style.color = '';
                }, 1200);

                // Show remove button & hide input group
                removeCouponWrap.classList.remove('d-none');
                document.getElementById('coupon-input-group').classList.add('d-none');

            } else {
                // ── Show error ────────────────────────────────────────────────
                showFeedback(data.message, 'danger');
                inputEl.focus();
            }
        })
        .catch(function (err) {
            setLoading(false);
            showFeedback('Có lỗi xảy ra. Vui lòng thử lại.', 'danger');
            console.error('[Coupon AJAX]', err);
        });
    });

    // ── Allow pressing Enter in the input ─────────────────────────────────────
    inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') applyBtn.click();
    });

    // ── Remove coupon ──────────────────────────────────────────────────────────
    removeBtn.addEventListener('click', function () {
        // Reset UI
        discountRow.style.display    = 'none';
        totalDisplay.textContent     = formatVND(ORIGINAL_TOTAL);
        inputEl.value                = '';
        appliedBadge.textContent     = '';

        removeCouponWrap.classList.add('d-none');
        document.getElementById('coupon-input-group').classList.remove('d-none');
        hideFeedback();

        // Clear session via a lightweight GET request
        fetch('php/apply_coupon.php?remove=1').catch(function () {});
    });

    // ── Auto-uppercase while typing ────────────────────────────────────────────
    inputEl.addEventListener('input', function () {
        const pos = this.selectionStart;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(pos, pos);
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
