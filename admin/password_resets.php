<?php
/**
 * NHK Mobile - Admin Password Reset Management
 *
 * Description: Admin interface to view and manage password reset requests.
 * Admin can manually reset password for users after verification.
 *
 * Author: NguyenHuuKhanh
 * Version: 1.0
 * Date: 2026-04-15
 */
require_once 'admin_auth.php';
require_once '../includes/auth_functions.php';
require_once '../includes/db.php';

$error = '';
$success = '';

// Handle manual password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'reset_password') {
        $userId = $_POST['user_id'] ?? 0;
        $newPassword = $_POST['new_password'] ?? '';
        
        if (empty($userId) || empty($newPassword)) {
            $error = "Vui lòng điền đầy đủ thông tin.";
        } else {
            $passwordValidation = validate_password_strength($newPassword);
            if (!$passwordValidation['valid']) {
                $error = "Mật khẩu không đủ mạnh: " . implode(', ', $passwordValidation['errors']);
            } else {
                // Get user info
                $stmt = $pdo->prepare("SELECT fullname, email FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate new password hash
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    // Update password
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, last_password_reset = NOW() WHERE id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                    
                    // Invalidate all old reset tokens
                    $stmt = $pdo->prepare("UPDATE password_resets SET is_used = TRUE WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    $success = "Đã đặt lại mật khẩu thành công cho {$user['fullname']} ({$user['email']})";
                    
                    // Log the action
                    error_log("[ADMIN] Password reset for user ID: $userId by admin: " . ($_SESSION['admin_user'] ?? 'unknown') . "\n", 3, __DIR__ . '/../logs/auth.log');
                } else {
                    $error = "Không tìm thấy người dùng.";
                }
            }
        }
    } elseif ($action === 'verify_identity') {
        // Admin verifies user identity before allowing reset
        $userId = $_POST['user_id'] ?? 0;
        $verificationNote = $_POST['verification_note'] ?? '';
        
        if (empty($userId)) {
            $error = "Vui lòng chọn người dùng.";
        } else {
            // In production, this would trigger email sending or other verification
            $stmt = $pdo->prepare("SELECT fullname, email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                $success = "Đã xác minh danh tính cho {$user['fullname']}. Admin có thể đặt lại mật khẩu.";
                
                // Log the verification
                error_log("[ADMIN] Identity verified for user ID: $userId by admin: " . ($_SESSION['admin_user'] ?? 'unknown') . " - Note: $verificationNote\n", 3, __DIR__ . '/../logs/auth.log');
            } else {
                $error = "Không tìm thấy người dùng.";
            }
        }
    }
}

// Get all pending password reset requests
$stmt = $pdo->query("
    SELECT pr.*, u.fullname, u.email, u.last_password_reset
    FROM password_resets pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.is_used = FALSE AND pr.expires_at > NOW()
    ORDER BY pr.created_at DESC
");
$pendingResets = $stmt->fetchAll();

// Get all users for manual reset dropdown
$stmt = $pdo->query("SELECT id, fullname, email FROM users ORDER BY fullname ASC");
$dropdownUsers = $stmt->fetchAll();

// Cấu hình phân trang cho danh sách người dùng
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$totalRecords = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$stmt = $pdo->prepare("SELECT id, fullname, email, created_at, last_password_reset FROM users ORDER BY fullname ASC LIMIT $limit OFFSET $offset");
$stmt->execute();
$paginatedUsers = $stmt->fetchAll();

$pageTitle = "Quản lý đặt lại mật khẩu | Admin";
$basePath = "../";
include 'includes/admin_header.php';
?>

<main class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold mb-2">
                <i class="bi bi-key-fill me-2"></i>Quản lý đặt lại mật khẩu
            </h2>
            <p class="text-muted">Xem và xử lý các yêu cầu đặt lại mật khẩu từ người dùng</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Pending Password Resets -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-clock-history me-2 text-primary"></i>
                        Yêu cầu chờ xử lý (<?php echo count($pendingResets); ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingResets)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                            <p class="mt-3">Không có yêu cầu nào đang chờ</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Người dùng</th>
                                        <th>Thời gian yêu cầu</th>
                                        <th>Hết hạn</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingResets as $reset): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($reset['fullname']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($reset['email']); ?></small>
                                            </td>
                                            <td><small><?php echo date('d/m/Y H:i', strtotime($reset['created_at'])); ?></small></td>
                                            <td><small><?php echo date('d/m/Y H:i', strtotime($reset['expires_at'])); ?></small></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="document.getElementById('userId').value = '<?php echo $reset['user_id']; ?>'; document.getElementById('manualResetModal').querySelector('form').scrollIntoView();">
                                                    <i class="bi bi-key"></i> Đặt lại
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Manual Password Reset -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0" id="manualResetModal">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-person-gear me-2 text-warning"></i>
                        Đặt lại mật khẩu thủ công
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" id="userId">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Chọn người dùng</label>
                            <select name="user_id_select" class="form-select" onchange="document.getElementById('userId').value = this.value;">
                                <option value="">-- Chọn người dùng --</option>
                                <?php foreach ($dropdownUsers as $u): ?>
                                    <option value="<?php echo $u['id']; ?>">
                                        <?php echo htmlspecialchars($u['fullname']) . ' (' . htmlspecialchars($u['email']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mật khẩu mới</label>
                            <input type="password" name="new_password" class="form-control" required 
                                   placeholder="Nhập mật khẩu mới" minlength="8">
                            <small class="text-muted">Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Ghi chú xác minh</label>
                            <textarea name="verification_note" class="form-control" rows="3" 
                                      placeholder="Ghi chú về quá trình xác minh danh tính..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-key me-2"></i>Đặt lại mật khẩu
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- User List -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-people me-2 text-info"></i>
                        Danh sách người dùng
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Họ tên</th>
                                    <th>Email</th>
                                    <th>Ngày tạo</th>
                                    <th>Đặt lại mật khẩu gần nhất</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paginatedUsers as $user): ?>
                                    <tr>
                                        <td><code>#<?php echo $user['id']; ?></code></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($user['fullname']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><small><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></small></td>
                                        <td>
                                            <?php if ($user['last_password_reset']): ?>
                                                <small class="text-success"><?php echo date('d/m/Y H:i', strtotime($user['last_password_reset'])); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Chưa đặt lại</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-warning" 
                                                    onclick="document.getElementById('userId').value = '<?php echo $user['id']; ?>'; document.getElementById('manualResetModal').scrollIntoView({behavior: 'smooth'});">
                                                <i class="bi bi-key"></i> Đặt lại
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination UI -->
                    <?php if (isset($totalPages) && $totalPages > 1): ?>
                    <div class="px-4 pb-3">
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-end mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Trước</a>
                                </li>
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                if ($startPage > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                    if ($startPage > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; 
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                                }
                                ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Sau</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/admin_footer.php'; ?>
