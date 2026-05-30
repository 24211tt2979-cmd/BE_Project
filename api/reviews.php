<?php
// Khởi động session trước khi include (auth_functions.php dùng session_status nên an toàn)
if (session_status() === PHP_SESSION_NONE)
    require_once '../includes/auth_functions.php';
require_once '../includes/db.php';
require_once '../includes/auth_functions.php';

// Cấu hình Header CORS & JSON
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

$hasVerifiedPurchase = false;
try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM reviews LIKE 'verified_purchase'");
    $hasVerifiedPurchase = (bool)$colStmt->fetch();
    if (!$hasVerifiedPurchase) {
        $pdo->exec("ALTER TABLE reviews ADD COLUMN verified_purchase INT DEFAULT 0");
        $hasVerifiedPurchase = true;
    }
} catch (PDOException $e) {
    $hasVerifiedPurchase = false;
}

$hasReviewImage = false;
try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM reviews LIKE 'image'");
    $hasReviewImage = (bool)$colStmt->fetch();
    if (!$hasReviewImage) {
        $pdo->exec("ALTER TABLE reviews ADD COLUMN image VARCHAR(255)");
        $hasReviewImage = true;
    }
} catch (PDOException $e) {
    $hasReviewImage = false;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($method === 'GET') {
    $product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;

    if (!$product_id) {
        echo json_encode(['error' => 'Thiếu product_id']);
        exit;
    }

    try {
        $stmtMeta = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                COALESCE(AVG(rating), 0) as avg_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as r5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as r4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as r3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as r2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as r1
            FROM reviews WHERE product_id = ?
        ");
        $stmtMeta->execute([$product_id]);
        $meta = $stmtMeta->fetch();

        $verifiedSelect = "0 AS verified_purchase";
        $imageSelect = $hasReviewImage ? "r.image" : "NULL AS image";
        $stmt = $pdo->prepare("
            SELECT r.id, r.rating, r.title, r.content, r.created_at,
                   r.reviewer_name, r.reviewer_email, $verifiedSelect, $imageSelect
            FROM reviews r
            WHERE r.product_id = ?
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$product_id, $limit, $offset]);
        $reviews = $stmt->fetchAll();

        foreach ($reviews as &$rev) {
            if ($rev['reviewer_email']) {
                $parts = explode('@', $rev['reviewer_email']);
                $rev['reviewer_email'] = mb_substr($parts[0], 0, 2) . '***@' . ($parts[1] ?? '');
            }
            if (!$rev['reviewer_name'])
                $rev['reviewer_name'] = 'Khách hàng ẩn danh';
            $rev['avatar_letter'] = mb_strtoupper(mb_substr($rev['reviewer_name'], 0, 1, 'UTF-8'), 'UTF-8');
            $rev['date_formatted'] = date('d/m/Y', strtotime($rev['created_at']));
        }

        echo json_encode([
            'success' => true,
            'meta' => [
                'total' => (int) $meta['total'],
                'avg_rating' => round((float) $meta['avg_rating'], 1),
                'page' => $page,
                'limit' => $limit,
                'total_pages' => max(1, ceil($meta['total'] / $limit)),
                'breakdown' => [
                    5 => (int) $meta['r5'],
                    4 => (int) $meta['r4'],
                    3 => (int) $meta['r3'],
                    2 => (int) $meta['r2'],
                    1 => (int) $meta['r1'],
                ]
            ],
            'reviews' => $reviews
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    // Hỗ trợ cả JSON và FormData
    if (isset($_SERVER["CONTENT_TYPE"]) && strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
    } else {
        $data = $_POST;
    }

    $product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
    $rating = isset($data['rating']) ? intval($data['rating']) : 0;
    $title = htmlspecialchars(trim($data['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $content = htmlspecialchars(trim($data['content'] ?? ''), ENT_QUOTES, 'UTF-8');

    $logged_in_user_id = get_logged_in_user_id(); // Chỉ trả về id nếu là user thường
    $is_logged_in = is_logged_in();               // Trả về true cho cả user lẫn admin
    $reviewer_name = $is_logged_in ? get_logged_in_name() : htmlspecialchars(trim($data['reviewer_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $reviewer_email = htmlspecialchars(trim($data['reviewer_email'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (!$product_id) {
        echo json_encode(['success' => false, 'error' => 'Thiếu product_id']);
        exit;
    }
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'error' => 'Rating phải từ 1 đến 5']);
        exit;
    }
    if (mb_strlen($content, 'UTF-8') < 5) {
        echo json_encode(['success' => false, 'error' => 'Nội dung đánh giá quá ngắn']);
        exit;
    }
    if (!$is_logged_in && empty($reviewer_name)) {
        echo json_encode(['success' => false, 'error' => 'Vui lòng nhập tên']);
        exit;
    }

    try {
        $verified = $is_logged_in ? 1 : 0;     // Cả user và admin đều là verified
        $user_id_val = $logged_in_user_id ?: null; // Chỉ lưu user_id nếu là user thường (admin = null)

        // Xử lý upload ảnh
        $imageFilename = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/images/reviews/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array($ext, $allowed)) {
                $newName = 'review_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newName)) {
                    $imageFilename = $newName;
                }
            }
        }

        // MYSQL SỬ DỤNG lastInsertId()
        if ($hasVerifiedPurchase && $hasReviewImage) {
            $stmt = $pdo->prepare("
                INSERT INTO reviews (product_id, user_id, reviewer_name, reviewer_email, rating, title, content, verified_purchase, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$product_id, $user_id_val, $reviewer_name, $reviewer_email, $rating, $title, $content, $verified, $imageFilename]);
        } elseif ($hasVerifiedPurchase) {
            $stmt = $pdo->prepare("
                INSERT INTO reviews (product_id, user_id, reviewer_name, reviewer_email, rating, title, content, verified_purchase)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$product_id, $user_id_val, $reviewer_name, $reviewer_email, $rating, $title, $content, $verified]);
        } elseif ($hasReviewImage) {
            $stmt = $pdo->prepare("
                INSERT INTO reviews (product_id, user_id, reviewer_name, reviewer_email, rating, title, content, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$product_id, $user_id_val, $reviewer_name, $reviewer_email, $rating, $title, $content, $imageFilename]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO reviews (product_id, user_id, reviewer_name, reviewer_email, rating, title, content)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$product_id, $user_id_val, $reviewer_name, $reviewer_email, $rating, $title, $content]);
        }
        $newId = $pdo->lastInsertId();

        // Cập nhật rating trung bình cho sản phẩm
        $pdo->prepare("
            UPDATE products 
            SET rating = (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = ?),
                review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ?)
            WHERE id = ?
        ")->execute([$product_id, $product_id, $product_id]);

        echo json_encode(['success' => true, 'message' => 'Cảm ơn bạn đã đánh giá!', 'review_id' => $newId]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Lỗi server: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
