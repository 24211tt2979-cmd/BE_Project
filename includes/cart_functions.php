<?php
/**
 * NHK Mobile - Cart Persistence Services
 * 
 * Description: Specialized logic for bidirectional synchronization 
 * between client-side PHP sessions and server-side database storage. 
 * Ensures cart contents persist across devices and sessions.
 * 
 * Author: NguyenHuuKhanh
 * Version: 2.2
 * Date: 2026-04-08
 */

/**
 * Đồng bộ giỏ hàng của người dùng với cơ sở dữ liệu.
 * - Nếu session rỗng: Tải dữ liệu giỏ hàng từ DB lên session.
 * - Nếu session có hàng: Lưu đè dữ liệu từ session xuống DB để đảm bảo tính đồng nhất.
 * 
 * @param PDO $pdo Đối tượng kết nối cơ sở dữ liệu
 */
function syncCartWithDatabase($pdo) {
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/auth_functions.php';
    }
    
    $userId = $_SESSION['user_id'] ?? null;
    $isAdmin = isset($_SESSION['admin_id']);

    // 1. Khởi tạo session rỗng nếu chưa có
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // 2. Nạp giỏ hàng từ DB nếu user đăng nhập và session đang rỗng
    if ($userId && !$isAdmin && empty($_SESSION['cart'])) {
        try {
            $sql = "SELECT ci.*, p.name, p.price, p.discount, p.image, p.stock FROM cart_items ci 
                    JOIN products p ON ci.product_id = p.id 
                    WHERE ci.user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $items = $stmt->fetchAll();
            
            foreach ($items as $item) {
                // Chỉ nạp vào session nếu hàng còn tồn kho
                if ($item['stock'] > 0) {
                    $qty = min((int)$item['quantity'], $item['stock']);
                    $actualPrice = $item['price'] - ($item['price'] * ($item['discount'] ?? 0) / 100);
                    $_SESSION['cart'][$item['product_id']] = [
                        'name'  => $item['name'],
                        'price' => $actualPrice,
                        'image' => $item['image'],
                        'qty'   => $qty
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("[Cart] Error loading cart from DB: " . $e->getMessage());
        }
    }

    // 3. XÁC THỰC GIỎ HÀNG VỚI DATABASE (Cho mọi đối tượng: Guest, Admin, User)
    if (!empty($_SESSION['cart'])) {
        try {
            $productIds = array_keys($_SESSION['cart']);
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare("SELECT id, name, price, discount, stock, image FROM products WHERE id IN ($placeholders)");
            $stmt->execute($productIds);
            
            $dbProducts = [];
            while ($row = $stmt->fetch()) {
                $dbProducts[$row['id']] = $row;
            }
            
            $cartModified = false;
            foreach ($_SESSION['cart'] as $pid => $item) {
                if (!isset($dbProducts[$pid]) || $dbProducts[$pid]['stock'] <= 0) {
                    // Sản phẩm bị xóa hoặc hết hàng -> tự động xóa khỏi session
                    unset($_SESSION['cart'][$pid]);
                    $cartModified = true;
                } else {
                    $dbProduct = $dbProducts[$pid];
                    $actualPrice = $dbProduct['price'] - ($dbProduct['price'] * ($dbProduct['discount'] ?? 0) / 100);
                    // Cập nhật giá và tên mới nhất từ DB
                    if ($_SESSION['cart'][$pid]['price'] != $actualPrice || $_SESSION['cart'][$pid]['name'] != $dbProduct['name']) {
                        $_SESSION['cart'][$pid]['price'] = $actualPrice;
                        $_SESSION['cart'][$pid]['name'] = $dbProduct['name'];
                        $cartModified = true;
                    }
                    // Đảm bảo số lượng mua không vượt quá tồn kho
                    if ($_SESSION['cart'][$pid]['qty'] > $dbProduct['stock']) {
                        $_SESSION['cart'][$pid]['qty'] = $dbProduct['stock'];
                        $cartModified = true;
                    }
                }
            }
            
            // Lưu thông báo nếu giỏ hàng bị hệ thống thay đổi tự động
            if ($cartModified) {
                $_SESSION['cart_notice'] = "Một số sản phẩm trong giỏ đã thay đổi giá hoặc hết hàng và được hệ thống tự động cập nhật.";
            }
        } catch (Exception $e) {
            error_log("[Cart] Error validating session cart: " . $e->getMessage());
        }
    }

    // 4. ĐỒNG BỘ LƯU LẠI XUỐNG DB CHO USER ĐÃ ĐĂNG NHẬP
    if ($userId && !$isAdmin) {
        try {
            $productIds = array_keys($_SESSION['cart']);
            if (!empty($productIds)) {
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id NOT IN ($placeholders)");
                $params = array_merge([$userId], $productIds);
                $stmt->execute($params);
            } else {
                // Giỏ hàng rỗng -> xóa sạch trong DB
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
                $stmt->execute([$userId]);
            }
            
            // Lưu/Update từng sản phẩm xuống DB
            foreach ($_SESSION['cart'] as $pid => $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO cart_items (user_id, product_id, quantity) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
                ");
                $stmt->execute([$userId, $pid, (int)$item['qty']]);
            }
        } catch (Exception $e) {
            error_log("[Cart] Error syncing cart to DB: " . $e->getMessage());
        }
    }
}

/**
 * Xóa một sản phẩm cụ thể khỏi giỏ hàng trong cơ sở dữ liệu.
 * 
 * @param PDO $pdo Đối tượng kết nối cơ sở dữ liệu
 * @param int $pid ID của sản phẩm cần xóa
 */
function removeFromCartDB($pdo, $pid) {
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/auth_functions.php';
    }
    $userId  = $_SESSION['user_id'] ?? null;
    $isAdmin = isset($_SESSION['admin_id']);

    // Admin cart chỉ tồn tại trong session → không cần xóa DB
    if ($isAdmin && !$userId) return;

    if ($userId) {
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $pid]);
    }
}
?>
