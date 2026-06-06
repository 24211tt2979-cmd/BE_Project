<?php
/**
 * NHK Mobile - Admin Database Reset Tool
 * 
 * This page allows admin to reset database directly from the website.
 * PROTECTED - Only logged-in admins can use this.
 */
require_once 'admin_auth.php';
require_once '../includes/db.php';

$error = '';
$success = '';
$resetDone = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    try {
        // Drop tất cả tables
        $tables = [
            'password_resets', 'repair_history', 'order_items', 'orders',
            'cart_items', 'reviews', 'wishlists', 'warranties',
            'return_requests', 'admin_logs', 'chatbot_rules', 'subscribers',
            'products', 'users', 'admins', 'news'
        ];

        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        
        // Chạy lại init_db.sql
        $sqlFile = __DIR__ . '/../php/config/init_db.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
        }
        
        $resetDone = true;
        $success = "Database đã được reset thành công!";
        
        // Log
        error_log("[ADMIN DB RESET] Database reset by admin: " . ($_SESSION['admin_user'] ?? 'unknown'));
        
    } catch (Exception $e) {
        $error = "Lỗi: " . $e->getMessage();
    }
}

$pageTitle = "Reset Database | Admin";
$basePath = "../";
include 'includes/admin_header.php';
?>

<main class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <?php if (!$resetDone): ?>
                <div class="card shadow border-0">
                    <div class="card-header bg-danger text-white py-3">
                        <h4 class="mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Reset Database
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <div class="alert alert-warning">
                            <strong>⚠️ CẢNH BÁO:</strong> Hành động này sẽ XÓA TẤT CẢ dữ liệu hiện tại!
                        </div>
                        
                        <h5 class="mb-3">Sau khi reset, database sẽ có:</h5>
                        <ul class="list-group list-group-flush mb-4">
                            <li class="list-group-item">
                                <i class="bi bi-person-shield text-primary me-2"></i>
                                <strong>Admin:</strong> <code>admin</code> / <code>admin123</code>
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-person text-success me-2"></i>
                                <strong>User:</strong> <code>test@test.com</code> / <code>Test123!</code>
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-box text-info me-2"></i>
                                <strong>Products:</strong> 27 sản phẩm đầy đủ
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-newspaper text-warning me-2"></i>
                                <strong>News:</strong> 3 bài viết
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-check-circle text-danger me-2"></i>
                                <strong>Orders/Reviews/Warranties:</strong> 0 (SẠCH)
                            </li>
                        </ul>
                        
                        <form method="POST" onsubmit="return confirm('Bạn CÓ CHẮC CHẮN muốn reset database? Hành động này KHÔNG THỂ HOÀN TÁC!');">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Xác nhận:</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="confirm_reset" id="confirmReset" required>
                                    <label class="form-check-label" for="confirmReset">
                                        Tôi hiểu rõ và muốn tiếp tục reset database
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-danger btn-lg">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset Database Ngay
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Quay về Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="card shadow border-success">
                    <div class="card-header bg-success text-white py-3">
                        <h4 class="mb-0">
                            <i class="bi bi-check-circle me-2"></i>
                            Reset Thành Công!
                        </h4>
                    </div>
                    <div class="card-body p-4 text-center">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                        </div>
                        
                        <h3 class="mb-3">Database đã được làm sạch!</h3>
                        
                        <div class="alert alert-info text-start">
                            <h5>🔐 Tài khoản:</h5>
                            <p class="mb-1"><strong>Admin:</strong> <code>admin</code> / <code>admin123</code></p>
                            <p class="mb-0"><strong>User:</strong> <code>test@test.com</code> / <code>Test123!</code></p>
                        </div>
                        
                        <div class="row text-center mb-4">
                            <div class="col-3">
                                <div class="h3 mb-0 text-primary">27</div>
                                <small class="text-muted">Products</small>
                            </div>
                            <div class="col-3">
                                <div class="h3 mb-0 text-success">3</div>
                                <small class="text-muted">News</small>
                            </div>
                            <div class="col-3">
                                <div class="h3 mb-0 text-warning">0</div>
                                <small class="text-muted">Orders</small>
                            </div>
                            <div class="col-3">
                                <div class="h3 mb-0 text-danger">0</div>
                                <small class="text-muted">Reviews</small>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="dashboard.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-speedometer2 me-2"></i>Về Dashboard
                            </a>
                            <a href="../index.php" class="btn btn-outline-secondary btn-lg">
                                <i class="bi bi-house me-2"></i>Về Website
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</main>

<?php include 'includes/admin_footer.php'; ?>
