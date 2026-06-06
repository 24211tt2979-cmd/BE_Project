<?php
/**
 * NHK Mobile - API: Trả Hàng / Hoàn Tiền
 * Xử lý yêu cầu trả hàng từ người dùng (submit form, upload ảnh)
 */
require_once '../includes/auth_functions.php';
require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

define('RETURN_DAYS_LIMIT', 14); // Giới hạn 14 ngày

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để gửi yêu cầu.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$orderId  = (int)($_POST['order_id'] ?? 0);
$reasonType = trim($_POST['reason_type'] ?? '');
$reason   = trim($_POST['reason'] ?? '');

// Validate dữ liệu cơ bản
if (!$orderId || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin.']);
    exit;
}

// Kiểm tra đơn hàng thuộc về user và hợp lệ để trả
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng.']);
    exit;
}

// Kiểm tra trạng thái đơn hàng phải là "Hoàn thành"
if (stripos($order['status'], 'hoàn thành') === false) {
    echo json_encode(['success' => false, 'message' => 'Chỉ có thể yêu cầu trả hàng với đơn hàng đã hoàn thành.']);
    exit;
}

// Kiểm tra thời hạn 14 ngày
$completedAt = strtotime($order['updated_at'] ?? $order['created_at']);
$daysPassed  = (time() - $completedAt) / 86400;
if ($daysPassed > RETURN_DAYS_LIMIT) {
    echo json_encode(['success' => false, 'message' => 'Đã quá ' . RETURN_DAYS_LIMIT . ' ngày kể từ khi nhận hàng. Không thể yêu cầu trả hàng.']);
    exit;
}

// Kiểm tra đơn chưa có yêu cầu đang xử lý
$stmtCheck = $pdo->prepare("SELECT id FROM return_requests WHERE order_id = ? AND status NOT IN ('Từ chối')");
$stmtCheck->execute([$orderId]);
if ($stmtCheck->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Đơn hàng này đã có yêu cầu trả hàng đang được xử lý.']);
    exit;
}

// Lấy thông tin user
$stmtUser = $pdo->prepare("SELECT fullname, phone FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch();

// Xử lý upload ảnh (tối đa 3 ảnh)
$uploadedImages = [];
$uploadDir = '../assets/uploads/returns/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!empty($_FILES['images']['name'][0])) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    foreach ($_FILES['images']['tmp_name'] as $idx => $tmpName) {
        if (count($uploadedImages) >= 3) break;
        if (!is_uploaded_file($tmpName)) continue;

        $fileType = $_FILES['images']['type'][$idx];
        $fileSize = $_FILES['images']['size'][$idx];

        if (!in_array($fileType, $allowedTypes) || $fileSize > $maxSize) continue;

        $ext      = pathinfo($_FILES['images']['name'][$idx], PATHINFO_EXTENSION);
        $filename = 'return_' . $orderId . '_' . time() . '_' . $idx . '.' . strtolower($ext);
        $destPath = $uploadDir . $filename;

        if (move_uploaded_file($tmpName, $destPath)) {
            $uploadedImages[] = 'assets/uploads/returns/' . $filename;
        }
    }
}

$imagesJson = !empty($uploadedImages) ? json_encode($uploadedImages) : null;
$orderCode  = '#ORD-' . $order['id'];

// Lưu yêu cầu vào DB
try {
    $stmt = $pdo->prepare("
        INSERT INTO return_requests 
            (order_id, user_id, customer_name, customer_phone, order_code, reason_type, reason, images, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Chờ duyệt')
    ");
    $stmt->execute([
        $orderId,
        $userId,
        $user['fullname'],
        $user['phone'] ?? $order['customer_phone'],
        $orderCode,
        $reasonType,
        $reason,
        $imagesJson,
    ]);

    echo json_encode(['success' => true, 'message' => 'Yêu cầu trả hàng đã được gửi thành công! Chúng tôi sẽ phản hồi trong 1-2 ngày làm việc.']);
} catch (PDOException $e) {
    error_log("[Return] DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra, vui lòng thử lại sau.']);
}
