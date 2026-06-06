<?php
/**
 * NHK Mobile - Warranty Detail API
 *
 * Returns full warranty info + repair history timeline for a given IMEI.
 *
 * Author: NguyenHuuKhanh
 * Version: 1.0
 * Date: 2026-04-14
 */
require_once '../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['imei'])) {
    echo json_encode(['status' => 'error', 'message' => 'Thiếu mã IMEI']);
    exit;
}

$imei = trim($_GET['imei']);

// === Ensure repair_history table exists ===
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS repair_history (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            warranty_id INT,
            repair_date DATE NOT NULL,
            title       VARCHAR(255) NOT NULL,
            description TEXT,
            location    VARCHAR(255),
            repair_id   VARCHAR(50),
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (warranty_id),
            CONSTRAINT repair_history_api_warranty_fk FOREIGN KEY (warranty_id) REFERENCES warranties(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (\PDOException $e) {}

// === Fetch warranty info ===
$sql = "
    SELECT w.*, p.name AS product_name, p.image, p.price,
           CURRENT_DATE AS today
    FROM warranties w
    LEFT JOIN products p ON w.product_id = p.id
    WHERE w.imei = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$imei]);
$warranty = $stmt->fetch();

if (!$warranty) {
    echo json_encode(['status' => 'not_found', 'message' => 'Không tìm thấy dữ liệu bảo hành cho IMEI này.']);
    exit;
}

// === Compute days remaining ===
$expiresTs   = strtotime($warranty['expires_at']);
$todayTs     = time();
$daysLeft    = (int) ceil(($expiresTs - $todayTs) / 86400);
$isExpired   = $daysLeft < 0;
$createdAt   = $warranty['created_at'] ? date('d/m/Y', strtotime($warranty['created_at'])) : null;

// === Fetch repair history ===
$historyStmt = $pdo->prepare("
    SELECT * FROM repair_history
    WHERE warranty_id = ?
    ORDER BY repair_date DESC
");
$historyStmt->execute([$warranty['id']]);
$history = $historyStmt->fetchAll();

$historyArr = array_map(fn($h) => [
    'id'          => $h['id'],
    'repair_date' => date('d/m/Y', strtotime($h['repair_date'])),
    'repair_date_raw' => $h['repair_date'],
    'title'       => $h['title'],
    'description' => $h['description'],
    'location'    => $h['location'],
    'repair_id'   => $h['repair_id'],
], $history);

echo json_encode([
    'status' => 'success',
    'data'   => [
        'id'             => $warranty['id'],
        'imei'           => $warranty['imei'],
        'product_name'   => $warranty['product_name'] ?? 'Không rõ sản phẩm',
        'product_image'  => $warranty['image'] ?? null,
        'warranty_status'=> $warranty['status'],
        'expires_at'     => date('d/m/Y', strtotime($warranty['expires_at'])),
        'expires_raw'    => $warranty['expires_at'],
        'created_at'     => $createdAt,
        'days_left'      => $daysLeft,
        'is_expired'     => $isExpired,
        'repair_history' => $historyArr,
    ]
]);
