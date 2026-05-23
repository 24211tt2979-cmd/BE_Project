<?php
/**
 * NHK Mobile - Newsletter Subscription Handler
 * 
 * Description: Asynchronous endpoint for processing email subscriptions. 
 * Validates syntax, prevents duplicate entries, and returns JSON status 
 * for frontend feedback.
 * 
 * Author: NguyenHuuKhanh
 * Version: 2.1
 * Date: 2026-04-08
 */
require_once 'includes/db.php';

// Prepare response as JSON for AJAX requests
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Phương thức không được hỗ trợ.']);
    exit;
}

// Bắt dữ liệu JSON từ request body
$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
     echo json_encode(['status' => 'error', 'message' => 'Địa chỉ email không hợp lệ.']);
     exit;
}

try {
    // Thêm vào bảng subscribers
    $sql = "INSERT INTO subscribers (email) VALUES (?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    
    echo json_encode(['status' => 'success', 'message' => 'Cảm ơn bạn đã đăng ký nhận tin tức!']);
} catch (PDOException $e) {
    // Mã lỗi 23505 là Unique Violation trong PostgreSQL
    if ($e->getCode() == '23505') {
        echo json_encode(['status' => 'error', 'message' => 'Email này đã được đăng ký trước đó.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại sau.']);
    }
}
