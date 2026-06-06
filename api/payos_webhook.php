<?php
/**
 * NHK Mobile - PayOS VietQR Webhook Handler
 * 
 * Handles incoming payment notifications from PayOS for instant checkout fulfillment.
 */
header('Content-Type: application/json');
require_once '../includes/db.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['event']) || $data['event'] !== 'payment.success') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid event payload']);
    exit;
}

$orderId = isset($data['data']['orderCode']) ? (int)$data['data']['orderCode'] : 0;
if (!$orderId && isset($data['data']['description'])) {
    // Tách ID đơn hàng từ nội dung chuyển khoản (Ví dụ: NHK_ORD_123)
    if (preg_match('/NHK_ORD_(\d+)/', $data['data']['description'], $matches)) {
        $orderId = (int)$matches[1];
    }
}

if (!$orderId) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID not found in payload']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Lấy thông tin đơn hàng hiện tại
    $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
    $stmtOrder->execute([$orderId]);
    $order = $stmtOrder->fetch();
    
    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        $pdo->rollBack();
        exit;
    }
    
    $newStatus = 'Đã duyệt';
    
    if ($order['status'] !== $newStatus) {
        $currentStatus = $order['status'];
        $activeStatuses = ['Đã duyệt', 'Đang giao', 'Hoàn thành'];
        $isOldActive = in_array($currentStatus, $activeStatuses);
        
        // 1. KÍCH HOẠT KHO, BẢO HÀNH, LỢI NHUẬN
        if (!$isOldActive) {
            $stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$orderId]);
            $items = $stmtItems->fetchAll();
            
            $totalCost = 0;
            
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $qty = (int)$item['quantity'];
                
                // Lấy giá vốn sản phẩm
                $stmtCost = $pdo->prepare("SELECT cost_price FROM products WHERE id = ?");
                $stmtCost->execute([$productId]);
                $costPrice = (float)$stmtCost->fetchColumn() ?: 0.00;
                $totalCost += $costPrice * $qty;
                
                // Kiểm tra sản phẩm có quản lý IMEI hay không
                $stmtHasImeis = $pdo->prepare("SELECT COUNT(*) FROM imeis WHERE product_id = ?");
                $stmtHasImeis->execute([$productId]);
                $hasImeis = $stmtHasImeis->fetchColumn() > 0;
                
                if ($hasImeis) {
                    // Phân bổ IMEI
                    $stmtGetImeis = $pdo->prepare("SELECT id, imei FROM imeis WHERE product_id = ? AND status = 'Available' ORDER BY id ASC LIMIT ?");
                    $stmtGetImeis->execute([$productId, $qty]);
                    $availableImeis = $stmtGetImeis->fetchAll();
                    
                    $allocatedImeis = [];
                    foreach ($availableImeis as $imeiRow) {
                        $imeiVal = $imeiRow['imei'];
                        $allocatedImeis[] = $imeiVal;
                        
                        // Đánh dấu IMEI là đã bán
                        $stmtMarkSold = $pdo->prepare("UPDATE imeis SET status = 'Sold' WHERE id = ?");
                        $stmtMarkSold->execute([$imeiRow['id']]);
                        
                        // Tạo bảo hành
                        $stmtCheckW = $pdo->prepare("SELECT COUNT(*) FROM warranties WHERE imei = ?");
                        $stmtCheckW->execute([$imeiVal]);
                        if ($stmtCheckW->fetchColumn() == 0) {
                            $stmtInsW = $pdo->prepare("
                                INSERT INTO warranties (imei, product_id, order_id, status, expires_at, customer_name, customer_phone) 
                                VALUES (?, ?, ?, 'Active', ?, ?, ?)
                            ");
                            $expiresAt = date('Y-m-d', strtotime('+12 months'));
                            $stmtInsW->execute([
                                $imeiVal, 
                                $productId, 
                                $orderId, 
                                $expiresAt, 
                                $order['customer_name'], 
                                $order['customer_phone']
                            ]);
                        }
                    }
                    
                    if (!empty($allocatedImeis)) {
                        $imeiString = implode(',', $allocatedImeis);
                        $stmtUpdateItemImei = $pdo->prepare("UPDATE order_items SET imei = ? WHERE id = ?");
                        $stmtUpdateItemImei->execute([$imeiString, $item['id']]);
                    }
                    
                    // Đồng bộ tồn kho
                    $stmtUpdateStock = $pdo->prepare("
                        UPDATE products 
                        SET stock = (SELECT COUNT(*) FROM imeis WHERE product_id = ? AND status = 'Available') 
                        WHERE id = ?
                    ");
                    $stmtUpdateStock->execute([$productId, $productId]);
                } else {
                    // Trừ kho truyền thống
                    $stmtUpdateStock = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?");
                    $stmtUpdateStock->execute([$qty, $productId]);
                }
            }
            
            // Cập nhật lợi nhuận, trạng thái thanh toán và trạng thái đơn hàng
            $profit = (float)$order['total_price'] - $totalCost;
            $stmtUpdateProfit = $pdo->prepare("UPDATE orders SET profit = ?, payment_status = 'Paid', status = ? WHERE id = ?");
            $stmtUpdateProfit->execute([$profit, $newStatus, $orderId]);
        } else {
            // Đơn hàng đã ở trạng thái hoạt động khác, chỉ cập nhật thanh toán và status
            $stmtUpdate = $pdo->prepare("UPDATE orders SET payment_status = 'Paid', status = ? WHERE id = ?");
            $stmtUpdate->execute([$newStatus, $orderId]);
        }
        
        // Ghi nhật ký hệ thống
        log_admin_action($pdo, 'PAYOS_WEBHOOK', "Thanh toán thành công qua PayOS cho đơn hàng ID $orderId. Số tiền: " . $data['data']['amount'] . " VND.");
    } else {
        // Đơn đã duyệt sẵn, chỉ đánh dấu đã thanh toán
        $stmtUpdate = $pdo->prepare("UPDATE orders SET payment_status = 'Paid' WHERE id = ?");
        $stmtUpdate->execute([$orderId]);
    }
    
    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Payment approved successfully']);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[PayOS Webhook] Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
