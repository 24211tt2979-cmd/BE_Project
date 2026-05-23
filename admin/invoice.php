<?php
require_once 'admin_auth.php';
require_once '../includes/db.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    die("Mã đơn hàng không hợp lệ.");
}

// 1. Lấy thông tin đơn hàng
$stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmtOrder->execute([$order_id]);
$order = $stmtOrder->fetch();

if (!$order) {
    die("Đơn hàng không tồn tại.");
}

// 2. Lấy thông tin chi tiết sản phẩm
$stmtItems = $pdo->prepare("SELECT order_items.*, products.image FROM order_items LEFT JOIN products ON order_items.product_id = products.id WHERE order_items.order_id = ?");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa đơn #ORD-<?php echo $order['id']; ?> | NHK Mobile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            background-color: #fff;
            padding: 40px;
        }
        .invoice-card {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #eaeaea;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }
        .logo-box {
            font-weight: 800;
            background-color: #000;
            color: #fff;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 1.25rem;
        }
        .invoice-title {
            font-size: 2rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }
        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: #6c757d;
            border-bottom: 2px solid #f1f3f5;
        }
        .table td {
            padding: 16px 8px;
            font-size: 0.9rem;
            border-bottom: 1px solid #f1f3f5;
        }
        @media print {
            body {
                padding: 0;
                background-color: #fff;
            }
            .invoice-card {
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .btn-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="invoice-card">
    <!-- Header -->
    <div class="row align-items-center mb-5">
        <div class="col-6">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="logo-box">NHK</div>
                <h4 class="fw-800 mb-0 tracking-tight">NHK Mobile</h4>
            </div>
            <p class="text-muted small mb-0">
                123 Đường 3/2, Quận 10, TP. Hồ Chí Minh<br>
                Hotline: 0375 352 347 | cskh@nhkmobile.com
            </p>
        </div>
        <div class="col-6 text-end">
            <h1 class="invoice-title text-primary mb-1">Hóa Đơn</h1>
            <p class="fw-bold mb-0">Mã đơn: #ORD-<?php echo $order['id']; ?></p>
            <p class="text-muted small mb-0">Ngày lập: <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
        </div>
    </div>

    <hr class="opacity-100 my-4" style="border-color: #eaeaea;">

    <!-- Info Section -->
    <div class="row mb-5">
        <div class="col-6">
            <h6 class="text-uppercase text-muted fw-bold small mb-3">Khách hàng nhận tin:</h6>
            <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($order['customer_name']); ?></h5>
            <p class="text-muted small mb-0">
                Số điện thoại: <?php echo htmlspecialchars($order['customer_phone']); ?><br>
                Địa chỉ: <?php echo htmlspecialchars($order['customer_address'] ?: 'Nhận tại cửa hàng'); ?>
            </p>
        </div>
        <div class="col-6 text-end">
            <h6 class="text-uppercase text-muted fw-bold small mb-3">Thông tin thanh toán:</h6>
            <h5 class="fw-bold text-primary mb-1"><?php echo number_format($order['total_price'], 0, ',', '.'); ?>₫</h5>
            <p class="text-muted small mb-0">
                Phương thức: <?php echo htmlspecialchars($order['payment_method']); ?><br>
                Trạng thái đơn: <strong><?php echo htmlspecialchars($order['status']); ?></strong>
            </p>
        </div>
    </div>

    <!-- Items Table -->
    <div class="table-responsive mb-5">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th class="text-center">Số lượng</th>
                    <th class="text-end">Đơn giá</th>
                    <th class="text-end">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $item): 
                    $subtotal = $item['price'] * $item['quantity'];
                ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                        <small class="text-muted">Mã số SP: #<?php echo $item['product_id']; ?></small>
                    </td>
                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                    <td class="text-end"><?php echo number_format($item['price'], 0, ',', '.'); ?>₫</td>
                    <td class="text-end fw-bold"><?php echo number_format($subtotal, 0, ',', '.'); ?>₫</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary -->
    <div class="row justify-content-end mb-4">
        <div class="col-md-5">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Tổng cộng hàng hóa:</span>
                <span><?php echo number_format($order['total_price'], 0, ',', '.'); ?>₫</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Phí giao hàng:</span>
                <span class="text-success">Miễn phí</span>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between fw-bold text-primary fs-5">
                <span>Tổng tiền thanh toán:</span>
                <span><?php echo number_format($order['total_price'], 0, ',', '.'); ?>₫</span>
            </div>
        </div>
    </div>

    <hr class="opacity-100 my-5" style="border-color: #eaeaea;">

    <!-- Footer -->
    <div class="text-center">
        <p class="small text-muted mb-4">Cảm ơn quý khách đã mua sắm tại NHK Mobile. Hẹn gặp lại quý khách!</p>
        <div class="d-flex justify-content-center gap-2 btn-print">
            <button onclick="window.print()" class="btn btn-primary px-4 py-2"><i class="bi bi-printer me-2"></i>In hóa đơn</button>
            <button onclick="window.close()" class="btn btn-outline-secondary px-4 py-2">Đóng trang</button>
        </div>
    </div>
</div>

<script>
    // Tự động kích hoạt in sau khi load trang
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.print();
        }, 500);
    });
</script>
</body>
</html>
