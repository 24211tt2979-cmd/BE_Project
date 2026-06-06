<?php
/**
 * NHK Mobile - Apply Coupon Code (AJAX Endpoint)
 *
 * Description: Validates a submitted promo/coupon code against the database.
 * Checks existence, active status, and expiry date.
 * On success: saves discount info to $_SESSION['applied_coupon'] and returns
 * the discounted total as JSON.
 *
 * Method: POST
 * Param:  coupon_code  (string)
 * Param:  cart_total   (float)   – Original cart total sent from frontend
 *
 * Response JSON:
 *   { success: true,  discount_percent, discount_amount, new_total, message }
 *   { success: false, message }
 *
 * Author: NguyenHuuKhanh (extended by AI assistant)
 * Version: 1.0
 * Date: 2026-06-05
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/auth_functions.php'; // session_start() inside
require_once __DIR__ . '/../includes/db.php';

// ── Force JSON response ────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// ── Handle coupon removal (GET ?remove=1) ─────────────────────────────────────
if (isset($_GET['remove'])) {
    unset($_SESSION['applied_coupon']);
    echo json_encode(['success' => true, 'message' => 'Đã xóa mã giảm giá.']);
    exit;
}

// ── Only allow POST requests ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được phép.']);
    exit;
}

// ── Input validation ──────────────────────────────────────────────────────────
$couponCode = trim(strtoupper($_POST['coupon_code'] ?? ''));
$cartTotal  = floatval($_POST['cart_total'] ?? 0);

if ($couponCode === '') {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã giảm giá.']);
    exit;
}

if ($cartTotal <= 0) {
    echo json_encode(['success' => false, 'message' => 'Giỏ hàng không hợp lệ.']);
    exit;
}

// ── Query the coupons table ────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT id, code, discount_percent, valid_until, is_active
        FROM   coupons
        WHERE  code = ?
        LIMIT  1
    ");
    $stmt->execute([$couponCode]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[Coupon] DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại.']);
    exit;
}

// ── Coupon not found ──────────────────────────────────────────────────────────
if (!$coupon) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá không tồn tại.']);
    exit;
}

// ── Coupon inactive ───────────────────────────────────────────────────────────
if (!$coupon['is_active']) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã bị vô hiệu hóa.']);
    exit;
}

// ── Coupon expired (compare server-side date) ──────────────────────────────────
$today    = new DateTime('today');
$validEnd = new DateTime($coupon['valid_until']);

if ($today > $validEnd) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã hết hạn sử dụng.']);
    exit;
}

// ── Calculate discount ────────────────────────────────────────────────────────
$discountPercent = (float) $coupon['discount_percent'];
$discountAmount  = round($cartTotal * $discountPercent / 100);
$newTotal        = max(0, $cartTotal - $discountAmount);

// ── Save to session ───────────────────────────────────────────────────────────
$_SESSION['applied_coupon'] = [
    'id'               => $coupon['id'],
    'code'             => $coupon['code'],
    'discount_percent' => $discountPercent,
    'discount_amount'  => $discountAmount,
    'original_total'   => $cartTotal,
    'new_total'        => $newTotal,
];

// ── Return success response ────────────────────────────────────────────────────
echo json_encode([
    'success'          => true,
    'message'          => "Áp dụng mã \"{$coupon['code']}\" thành công! Bạn được giảm {$discountPercent}%.",
    'discount_percent' => $discountPercent,
    'discount_amount'  => $discountAmount,
    'new_total'        => $newTotal,
]);
