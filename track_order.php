<?php
/**
 * NHK Mobile - Order History
 * 
 * Description: Displays a direct list of orders purchased by the 
 * authenticated user. Lookup functionality removed as per user request.
 */
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

// Force login to view history (Allow both Users and Admins)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php?redirect=track_order.php");
    exit;
}

// Determine whose orders to show
$userId = $_SESSION['user_id'] ?? null;
$isAdmin = isset($_SESSION['admin_id']);
$order = null;
$items = [];

// 1. VIEW DETAILED ORDER
if (isset($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];
    
    // If admin, can view any order. If user, only own orders.
    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$orderId, $userId]);
    }
    
    $order = $stmt->fetch();
    
    if ($order) {
        $stmtItems = $pdo->prepare("SELECT order_items.*, products.image FROM order_items LEFT JOIN products ON order_items.product_id = products.id WHERE order_id = ?");
        $stmtItems->execute([$order['id']]);
        $items = $stmtItems->fetchAll();
    } else {
        header("Location: track_order.php");
        exit;
    }
} else {
    // 2. VIEW ORDER LIST
    if ($isAdmin) {
        // Admin xem được tất cả các đơn hàng để dễ quản lý/test
        $stmtUserOrders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC");
    } else {
        // User chỉ xem được đơn hàng của chính mình
        $stmtUserOrders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
        $stmtUserOrders->execute([$userId]);
    }
    $orders_list = $stmtUserOrders->fetchAll();
}

$pageTitle = "Lịch sử mua hàng | NHK Mobile";
$basePath = "";
include 'includes/header.php';
?>

<style>
.history-card {
    border: none;
    border-radius: 1.5rem;
    box-shadow: 0 1rem 3rem rgba(0,0,0,0.05);
    overflow: hidden;
    transition: transform 0.3s;
}
.order-item-img {
    width: 64px; height: 64px;
    object-fit: contain;
    background: #fff;
    border-radius: 12px;
    padding: 4px;
    border: 1px solid #eee;
}
.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 50rem;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
}

@media print {
    body { background: #fff !important; }
    nav, footer, .btn, .no-print { display: none !important; }
    .container { max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
    .history-card { box-shadow: none !important; border: 2px solid #000 !important; width: 100% !important; margin: 0 !important; page-break-inside: avoid; }
    .order-items { background: #fff !important; }
}
</style>

<main class="bg-premium-light min-vh-100 pb-5" style="padding-top: 80px;">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                
                <?php if ($order): ?>
                    <!-- DETAILED ORDER VIEW -->
                    <div class="history-card bg-white p-4 p-md-5">
                        <div class="d-flex justify-content-between align-items-center mb-5 no-print">
                            <a href="track_order.php" class="btn btn-light rounded-pill px-4"><i class="bi bi-arrow-left me-2"></i> Quay lại</a>
                            <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow-sm"><i class="bi bi-printer me-2"></i> In hóa đơn</button>
                        </div>

                        <div class="row mb-5 align-items-center">
                            <div class="col-6">
                                <h2 class="fw-900 mb-0">HÓA ĐƠN</h2>
                                <p class="text-muted small">Mã đơn hàng: #<?php echo $order['id']; ?></p>
                            </div>
                            <div class="col-6 text-end">
                                <div class="d-flex flex-column align-items-end">
                                    <div class="brand-logo-box lg mb-2">NHK</div>
                                    <p class="small text-muted fw-bold mb-0">NHK Mobile Authorized</p>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-5 border-top pt-4">
                            <div class="col-md-6 border-end">
                                <p class="text-secondary small text-uppercase fw-bold mb-3">Thông tin người nhận</p>
                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($order['customer_name'] ?? ''); ?></h6>
                                <p class="text-muted small mb-1"><i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?></p>
                                <p class="text-muted small mb-0"><i class="bi bi-geo-alt me-2"></i><?php echo htmlspecialchars($order['customer_address'] ?? 'Tại cửa hàng'); ?></p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="text-secondary small text-uppercase fw-bold mb-3">Chi tiết thanh toán</p>
                                <h6 class="fw-bold mb-1">Ngày đặt: <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></h6>
                                <p class="text-muted small mb-1">Phương thức: <strong><?php echo htmlspecialchars($order['payment_method'] ?? 'COD'); ?></strong></p>
                                <p class="text-muted small mb-0">Trạng thái: <span class="text-primary fw-bold"><?php echo $order['status']; ?></span></p>
                            </div>
                        </div>

                        <div class="order-items mb-5">
                            <table class="table table-borderless align-middle">
                                <thead class="border-bottom">
                                    <tr class="text-muted small">
                                        <th class="pb-3">SẢN PHẨM</th>
                                        <th class="pb-3 text-center">SỐ LƯỢNG</th>
                                        <th class="pb-3 text-end">ĐƠN GIÁ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr class="border-bottom">
                                        <td class="py-3">
                                            <div class="d-flex align-items-center">
                                                <img src="assets/images/<?php echo $item['image']; ?>" class="order-item-img me-3 no-print" onerror="this.src='https://placehold.co/100'">
                                                <span class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3 text-center fw-bold"><?php echo $item['quantity']; ?></td>
                                        <td class="py-3 text-end fw-bold text-dark"><?php echo number_format($item['price'], 0, ',', '.'); ?>đ</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="pt-4 h5 fw-bold">Tổng thanh toán:</td>
                                        <td class="pt-4 h4 fw-900 text-danger text-end"><?php echo number_format($order['total_price'], 0, ',', '.'); ?>đ</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="text-center mt-5 pt-4 border-top small text-muted">
                            <p class="mb-1 fw-bold">Cảm ơn bạn đã tin tưởng lựa chọn NHK Mobile!</p>
                            <p class="mb-0 opacity-75">Hotline hỗ trợ: 1800 1234 | Website: nhkmobile.vn</p>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- ORDER LIST VIEW -->
                    <div class="d-flex justify-content-between align-items-end mb-4">
                        <div>
                            <h2 class="fw-900 mb-1">Đơn hàng đã mua</h2>
                            <p class="text-muted mb-0">Theo dõi và quản lý các siêu phẩm bạn đã sở hữu.</p>
                        </div>
                        <span class="badge bg-primary rounded-pill px-3 py-2 fw-bold"><?php echo count($orders_list); ?> ĐƠN HÀNG</span>
                    </div>

                    <?php if (empty($orders_list)): ?>
                        <div class="history-card bg-white p-5 text-center">
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 120px; height: 120px;">
                                <i class="bi bi-bag-x display-3 text-muted"></i>
                            </div>
                            <h4 class="fw-bold">Chưa có đơn hàng nào</h4>
                            <p class="text-muted mb-4">Có vẻ như bạn chưa thực hiện giao dịch nào tại NHK Mobile.</p>
                            <a href="product.php" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow-sm">KHÁM PHÁ SẢN PHẨM NGAY</a>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($orders_list as $o): ?>
                                <div class="col-12">
                                    <div class="history-card bg-white p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-primary bg-opacity-10 rounded-4 p-3"><i class="bi bi-receipt-cutoff text-primary fs-3"></i></div>
                                            <div>
                                                <h6 class="fw-bold mb-1">Đơn hàng #<?php echo $o['id']; ?></h6>
                                                <p class="text-muted small mb-0">
                                                    <span class="me-3"><i class="bi bi-calendar3 me-1"></i> <?php echo date('d/m/Y', strtotime($o['created_at'])); ?></span>
                                                    <span><i class="bi bi-currency-dollar me-1"></i> <?php echo number_format($o['total_price'], 0, ',', '.'); ?>đ</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php
                                                $s = mb_strtolower($o['status'], 'UTF-8');
                                                $badgeClass = 'bg-warning text-dark';
                                                if (str_contains($s, 'hoàn thành')) $badgeClass = 'bg-success text-white';
                                                elseif (str_contains($s, 'đang giao')) $badgeClass = 'bg-primary text-white';
                                                elseif (str_contains($s, 'hủy')) $badgeClass = 'bg-danger text-white';
                                            ?>
                                            <span class="status-badge <?php echo $badgeClass; ?>"><?php echo $o['status']; ?></span>
                                            <a href="track_order.php?order_id=<?php echo $o['id']; ?>" class="btn btn-dark rounded-pill btn-sm px-4 fw-bold shadow-sm">CHI TIẾT</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
