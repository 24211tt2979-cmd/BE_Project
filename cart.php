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
                                <hr class="my-4">
                                <div class="d-flex justify-content-between mb-5">
                                    <h4 class="fw-bold">Tổng tiền</h4>
                                    <h4 class="fw-bold text-primary"><?php echo number_format($total, 0, ',', '.'); ?>₫</h4>
                                </div>
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

<?php include 'includes/footer.php'; ?>
