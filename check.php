<?php
/**
 * NHK Mobile - System Check Page
 * 
 * Trang kiểm tra toàn bộ chức năng của hệ thống
 */
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

$pageTitle = "Kiểm tra hệ thống | NHK Mobile";
$currentPage = 'check';
include 'includes/header.php';

// Kiểm tra các chức năng hệ thống
$checks = [];

// 1. Kiểm tra kết nối database
try {
    $pdo->query("SELECT 1");
    $checks['database'] = [
        'status' => 'success',
        'message' => 'Kết nối MySQL hoạt động bình thường',
        'details' => 'Database: ' . (defined('DB_NAME') ? DB_NAME : 'N/A')
    ];
} catch (Exception $e) {
    $checks['database'] = [
        'status' => 'error',
        'message' => 'Lỗi kết nối database',
        'details' => $e->getMessage()
    ];
}

// 2. Kiểm tra bảng users
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    $checks['users_table'] = [
        'status' => 'success',
        'message' => "Bảng users tồn tại ($userCount users)",
        'details' => 'OK'
    ];
} catch (Exception $e) {
    $checks['users_table'] = [
        'status' => 'error',
        'message' => 'Bảng users không tồn tại hoặc lỗi',
        'details' => $e->getMessage()
    ];
}

// 3. Kiểm tra bảng products
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $productCount = $stmt->fetchColumn();
    $checks['products_table'] = [
        'status' => 'success',
        'message' => "Bảng products tồn tại ($productCount products)",
        'details' => 'OK'
    ];
} catch (Exception $e) {
    $checks['products_table'] = [
        'status' => 'error',
        'message' => 'Bảng products không tồn tại hoặc lỗi',
        'details' => $e->getMessage()
    ];
}

// 4. Kiểm tra bảng orders
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $orderCount = $stmt->fetchColumn();
    $checks['orders_table'] = [
        'status' => 'success',
        'message' => "Bảng orders tồn tại ($orderCount orders)",
        'details' => 'OK'
    ];
} catch (Exception $e) {
    $checks['orders_table'] = [
        'status' => 'error',
        'message' => 'Bảng orders không tồn tại hoặc lỗi',
        'details' => $e->getMessage()
    ];
}

// 5. Kiểm tra bảng warranties
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM warranties");
    $warrantyCount = $stmt->fetchColumn();
    $checks['warranties_table'] = [
        'status' => 'success',
        'message' => "Bảng warranties tồn tại ($warrantyCount warranties)",
        'details' => 'OK'
    ];
} catch (Exception $e) {
    $checks['warranties_table'] = [
        'status' => 'error',
        'message' => 'Bảng warranties không tồn tại hoặc lỗi',
        'details' => $e->getMessage()
    ];
}

// 6. Kiểm tra bảng news/articles
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM news");
    $newsCount = $stmt->fetchColumn();
    $checks['news_table'] = [
        'status' => 'success',
        'message' => "Bảng news tồn tại ($newsCount articles)",
        'details' => 'OK'
    ];
} catch (Exception $e) {
    $checks['news_table'] = [
        'status' => 'error',
        'message' => 'Bảng news không tồn tại hoặc lỗi',
        'details' => $e->getMessage()
    ];
}

// 7. Kiểm tra phiên bản PHP
$phpVersion = phpversion();
$phpMinVersion = '8.0';
if (version_compare($phpVersion, $phpMinVersion, '>=')) {
    $checks['php_version'] = [
        'status' => 'success',
        'message' => "PHP phiên bản $phpVersion (>= $phpMinVersion)",
        'details' => 'OK'
    ];
} else {
    $checks['php_version'] = [
        'status' => 'warning',
        'message' => "PHP phiên bản $phpVersion (< $phpMinVersion)",
        'details' => 'Nên nâng cấp PHP'
    ];
}

// 8. Kiểm tra extensions cần thiết
$requiredExtensions = ['pdo', 'pdo_mysql', 'session', 'json', 'mbstring'];
$missingExtensions = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (empty($missingExtensions)) {
    $checks['extensions'] = [
        'status' => 'success',
        'message' => 'Tất cả extensions cần thiết đã được cài đặt',
        'details' => implode(', ', $requiredExtensions)
    ];
} else {
    $checks['extensions'] = [
        'status' => 'error',
        'message' => 'Thiếu extensions: ' . implode(', ', $missingExtensions),
        'details' => 'Cần cài đặt thêm'
    ];
}

// 9. Kiểm tra quyền ghi thư mục
$writableDirs = ['logs', 'uploads', 'assets'];
$dirIssues = [];
foreach ($writableDirs as $dir) {
    $fullPath = __DIR__ . '/' . $dir;
    if (is_dir($fullPath) && !is_writable($fullPath)) {
        $dirIssues[] = $dir;
    }
}

if (empty($dirIssues)) {
    $checks['permissions'] = [
        'status' => 'success',
        'message' => 'Quyền thư mục đúng',
        'details' => 'Tất cả thư mục có thể ghi được'
    ];
} else {
    $checks['permissions'] = [
        'status' => 'warning',
        'message' => 'Thư mục không ghi được: ' . implode(', ', $dirIssues),
        'details' => 'Chmod 755 hoặc 777'
    ];
}

// 10. Kiểm tra dung lượng đĩa
$freeSpace = @disk_free_space(__DIR__);
if ($freeSpace !== false) {
    $freeGB = round($freeSpace / (1024 * 1024 * 1024), 2);
    if ($freeGB > 1) {
        $checks['disk_space'] = [
            'status' => 'success',
            'message' => "Dung lượng trống: {$freeGB}GB",
            'details' => 'OK'
        ];
    } else {
        $checks['disk_space'] = [
            'status' => 'warning',
            'message' => "Dung lượng trống thấp: {$freeGB}GB",
            'details' => 'Nên giải phóng thêm'
        ];
    }
} else {
    $checks['disk_space'] = [
        'status' => 'error',
        'message' => 'Không thể kiểm tra dung lượng đĩa',
        'details' => 'Lỗi'
    ];
}

// 11. Kiểm tra session
if (session_status() === PHP_SESSION_ACTIVE || session_start()) {
    $checks['session'] = [
        'status' => 'success',
        'message' => 'Session hoạt động bình thường',
        'details' => 'Session ID: ' . session_id()
    ];
} else {
    $checks['session'] = [
        'status' => 'error',
        'message' => 'Session không hoạt động',
        'details' => 'Lỗi'
    ];
}

// 12. Kiểm tra chức năng gửi mail (nếu có cấu hình)
if (function_exists('mail')) {
    $checks['mail'] = [
        'status' => 'success',
        'message' => 'Hàm mail() khả dụng',
        'details' => 'Cấu hình SMTP cần được kiểm tra riêng'
    ];
} else {
    $checks['mail'] = [
        'status' => 'warning',
        'message' => 'Hàm mail() không khả dụng',
        'details' => 'Cần cấu hình mail server'
    ];
}

// Đếm kết quả
$successCount = 0;
$errorCount = 0;
$warningCount = 0;
foreach ($checks as $check) {
    if ($check['status'] === 'success') $successCount++;
    elseif ($check['status'] === 'error') $errorCount++;
    elseif ($check['status'] === 'warning') $warningCount++;
}
?>

    <!-- Breadcrumb -->
    <div class="breadcrumb-section py-3 bg-light">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Kiểm tra hệ thống</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <section class="py-5">
        <div class="container">
            <div class="row mb-5">
                <div class="col-12 text-center">
                    <h1 class="fw-bold mb-3">Kiểm Tra Hệ Thống</h1>
                    <p class="text-muted">Trạng thái hoạt động của tất cả các chức năng trong hệ thống NHK Mobile</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="card border-success h-100">
                        <div class="card-body text-center">
                            <div class="display-4 text-success mb-2">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <h3 class="fw-bold text-success"><?php echo $successCount; ?></h3>
                            <p class="text-muted mb-0">Thành công</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-warning h-100">
                        <div class="card-body text-center">
                            <div class="display-4 text-warning mb-2">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </div>
                            <h3 class="fw-bold text-warning"><?php echo $warningCount; ?></h3>
                            <p class="text-muted mb-0">Cảnh báo</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-danger h-100">
                        <div class="card-body text-center">
                            <div class="display-4 text-danger mb-2">
                                <i class="bi bi-x-circle-fill"></i>
                            </div>
                            <h3 class="fw-bold text-danger"><?php echo $errorCount; ?></h3>
                            <p class="text-muted mb-0">Lỗi</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Check Results -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-clipboard-check me-2"></i>
                        Chi tiết kiểm tra
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="30%">Chức năng</th>
                                    <th width="40%">Trạng thái</th>
                                    <th width="30%">Chi tiết</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checks as $key => $check): ?>
                                <tr>
                                    <td class="fw-bold">
                                        <?php 
                                        $labels = [
                                            'database' => 'Database Connection',
                                            'users_table' => 'Bảng Users',
                                            'products_table' => 'Bảng Products',
                                            'orders_table' => 'Bảng Orders',
                                            'warranties_table' => 'Bảng Warranties',
                                            'news_table' => 'Bảng News',
                                            'php_version' => 'PHP Version',
                                            'extensions' => 'PHP Extensions',
                                            'permissions' => 'File Permissions',
                                            'disk_space' => 'Disk Space',
                                            'session' => 'Session',
                                            'mail' => 'Mail Function'
                                        ];
                                        echo $labels[$key] ?? $key;
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($check['status'] === 'success'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-lg me-1"></i> <?php echo $check['message']; ?>
                                            </span>
                                        <?php elseif ($check['status'] === 'error'): ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-lg me-1"></i> <?php echo $check['message']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $check['message']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?php echo $check['details']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row g-4 mt-4">
                <div class="col-12">
                    <h4 class="fw-bold mb-3">
                        <i class="bi bi-lightning me-2"></i>
                        Hành động nhanh
                    </h4>
                </div>
                <div class="col-md-3">
                    <a href="admin/dashboard.php" class="btn btn-outline-primary w-100 py-3">
                        <i class="bi bi-speedometer2 d-block mb-2 fs-4"></i>
                        Admin Dashboard
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="admin/reset_database.php" class="btn btn-outline-warning w-100 py-3">
                        <i class="bi bi-arrow-clockwise d-block mb-2 fs-4"></i>
                        Reset Database
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="admin/export_stats.php" class="btn btn-outline-info w-100 py-3">
                        <i class="bi bi-download d-block mb-2 fs-4"></i>
                        Xuất báo cáo
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="admin/products.php" class="btn btn-outline-success w-100 py-3">
                        <i class="bi bi-phone d-block mb-2 fs-4"></i>
                        Quản lý sản phẩm
                    </a>
                </div>
            </div>

            <!-- System Info -->
            <div class="card mt-5 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Thông tin hệ thống
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <td class="fw-bold">PHP Version</td>
                                    <td><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Server Software</td>
                                    <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Server OS</td>
                                    <td><?php echo PHP_OS; ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <td class="fw-bold">Document Root</td>
                                    <td class="small"><?php echo $_SERVER['DOCUMENT_ROOT'] ?? __DIR__; ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Max Execution Time</td>
                                    <td><?php echo ini_get('max_execution_time'); ?>s</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Memory Limit</td>
                                    <td><?php echo ini_get('memory_limit'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
