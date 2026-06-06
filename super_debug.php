<?php
/**
 * SUPER SIMPLE DEBUG - Tìm lỗi chính xác
 */
session_start();

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Super Debug</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; }
        .ok { color: green; font-weight: bold; }
        .err { color: red; font-weight: bold; }
        pre { background: #f9f9f9; padding: 10px; overflow: auto; }
        button { padding: 10px 20px; margin: 5px; background: #007AFF; color: white; border: none; border-radius: 6px; cursor: pointer; }
        a { display: inline-block; padding: 10px 20px; margin: 5px; background: #28a745; color: white; text-decoration: none; border-radius: 6px; }
    </style>
</head>
<body>
<h1>🔍 SUPER DEBUG - Tìm lỗi mua hàng</h1>";

// Step 1: Check session
echo "<div class='box'>";
echo "<h2>Step 1: Session</h2>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p class='ok'>✅ Session ACTIVE</p>";
    echo "<p>Session ID: " . session_id() . "</p>";
} else {
    echo "<p class='err'>❌ Session KHÔNG hoạt động</p>";
}
echo "</div>";

// Step 2: Check db.php
echo "<div class='box'>";
echo "<h2>Step 2: Database Connection</h2>";
try {
    require_once 'includes/db.php';
    if ($pdo) {
        echo "<p class='ok'>✅ DB Connected</p>";
        
        // Test query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
        $row = $stmt->fetch();
        echo "<p>Số sản phẩm: " . $row['count'] . "</p>";
        
        // Get first product
        $stmt = $pdo->query("SELECT id, name, price, stock FROM products LIMIT 1");
        $product = $stmt->fetch();
        if ($product) {
            echo "<p>Product test: <strong>" . $product['name'] . "</strong></p>";
            echo "<p>Giá: " . number_format($product['price']) . "₫</p>";
            echo "<p>Stock: " . $product['stock'] . "</p>";
            echo "<p><a href='?action=add&id=" . $product['id'] . "'>➕ THÊM VÀO GIỎ (Product ID: " . $product['id'] . ")</a></p>";
        }
    } else {
        echo "<p class='err'>❌ PDO = NULL</p>";
    }
} catch (Exception $e) {
    echo "<p class='err'>❌ DB ERROR: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Step 3: Check auth
echo "<div class='box'>";
echo "<h2>Step 3: Login Status</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p class='ok'>✅ ĐÃ LOGIN</p>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Fullname: " . ($_SESSION['user_fullname'] ?? 'N/A') . "</p>";
    echo "<p>Email: " . ($_SESSION['user_email'] ?? 'N/A') . "</p>";
    echo "<p><a href='logout.php' style='background:#dc3545;'>🚪 LOGOUT</a></p>";
} elseif (isset($_SESSION['admin_id'])) {
    echo "<p style='color:orange;'>⚠️ ADMIN LOGIN (không thể checkout)</p>";
    echo "<p>Admin ID: " . $_SESSION['admin_id'] . "</p>";
    echo "<p><a href='admin/logout.php' style='background:#dc3545;'>🚪 ADMIN LOGOUT</a></p>";
} else {
    echo "<p class='err'>❌ CHƯA LOGIN</p>";
    echo "<p><a href='login.php'>🔐 LOGIN NOW</a></p>";
    echo "<p><a href='register.php'>📝 REGISTER</a></p>";
}
echo "</div>";

// Step 4: Handle add to cart
if (isset($_GET['action']) && $_GET['action'] === 'add' && isset($_GET['id'])) {
    echo "<div class='box'>";
    echo "<h2>Step 4: Add to Cart</h2>";
    
    $productId = (int)$_GET['id'];
    echo "<p>Product ID: $productId</p>";
    
    // Check login
    if (!isset($_SESSION['user_id'])) {
        echo "<p class='err'>❌ CHƯA LOGIN - Không thể thêm vào giỏ</p>";
        echo "<p>👉 <a href='login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']) . "'>LOGIN TRƯỚC</a></p>";
    } else {
        echo "<p class='ok'>✅ ĐÃ LOGIN - Tiến hành thêm...</p>";
        
        // Check if product exists
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            echo "<p class='err'>❌ Product không tồn tại</p>";
        } elseif ($product['stock'] <= 0) {
            echo "<p class='err'>❌ Product hết hàng</p>";
        } else {
            echo "<p>✅ Product hợp lệ: " . $product['name'] . "</p>";
            
            // Add to session cart
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
                echo "<p>🆕 Tạo cart mới</p>";
            }
            
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId]['qty']++;
                echo "<p>📈 Tăng số lượng sản phẩm có sẵn</p>";
            } else {
                $_SESSION['cart'][$productId] = [
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'image' => $product['image'],
                    'qty' => 1
                ];
                echo "<p>➕ Thêm sản phẩm mới vào cart</p>";
            }
            
            echo "<p class='ok'>✅ ĐÃ THÊM VÀO SESSION CART</p>";
            
            // Sync to DB
            try {
                require_once 'includes/cart_functions.php';
                syncCartWithDatabase($pdo);
                echo "<p class='ok'>✅ SYNC DB THÀNH CÔNG</p>";
            } catch (Exception $e) {
                echo "<p class='err'>❌ SYNC DB LỖI: " . $e->getMessage() . "</p>";
            }
            
            echo "<p><a href='?action=viewcart'>🛒 XEM GIỎ HÀNG</a></p>";
        }
    }
    echo "</div>";
}

// Step 5: Check cart
echo "<div class='box'>";
echo "<h2>Step 5: Cart Contents</h2>";
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    echo "<p class='ok'>✅ CART CÓ " . count($_SESSION['cart']) . " SẢN PHẨM</p>";
    echo "<pre>" . print_r($_SESSION['cart'], true) . "</pre>";
    
    $total = 0;
    foreach ($_SESSION['cart'] as $id => $item) {
        $subtotal = $item['price'] * $item['qty'];
        $total += $subtotal;
        echo "<p>📱 {$item['name']} x {$item['qty']} = " . number_format($subtotal) . "₫</p>";
    }
    echo "<p><strong>Tổng: " . number_format($total) . "₫</strong></p>";
    
    if (isset($_SESSION['user_id'])) {
        echo "<p><a href='checkout.php'>💳 CHECKOUT NOW</a></p>";
    } else {
        echo "<p class='err'>❌ Phải login để checkout</p>";
        echo "<p><a href='login.php?redirect=checkout.php'>🔐 LOGIN ĐỂ CHECKOUT</a></p>";
    }
    
    echo "<p><a href='cart.php'>🛒 Xem trang cart.php</a></p>";
} else {
    echo "<p class='err'>❌ CART TRỐNG</p>";
    echo "<p>Thử click nút 'THÊM VÀO GIỎ' ở Step 2</p>";
}
echo "</div>";

// Step 6: Check DB cart_items
echo "<div class='box'>";
echo "<h2>Step 6: Database cart_items</h2>";
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    echo "<p>User ID: $userId</p>";
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM cart_items WHERE user_id = ?");
        $stmt->execute([$userId]);
        $cartItems = $stmt->fetchAll();
        
        if (!empty($cartItems)) {
            echo "<p class='ok'>✅ DB có " . count($cartItems) . " items</p>";
            echo "<pre>" . print_r($cartItems, true) . "</pre>";
        } else {
            echo "<p class='err'>❌ DB KHÔNG có items</p>";
        }
    } catch (Exception $e) {
        echo "<p class='err'>❌ Query lỗi: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>⚠️ Chưa login - Không check được DB cart</p>";
}
echo "</div>";

// Step 7: Quick Actions
echo "<div class='box'>";
echo "<h2>Step 7: Quick Actions</h2>";
echo "<a href='super_debug.php'>🔄 Refresh</a>";
echo "<a href='login.php'>🔐 Login</a>";
echo "<a href='register.php'>📝 Register</a>";
echo "<a href='product.php'>📱 Products</a>";
echo "<a href='cart.php'>🛒 Cart</a>";
echo "<a href='checkout.php'>💳 Checkout</a>";
echo "<a href='test_login.php'>🧪 Test Login</a>";
echo "<a href='test_cart.php'>🧪 Test Cart</a>";
echo "</div>";

echo "</body></html>";
?>
