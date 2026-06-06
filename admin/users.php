<?php
// Bắt đầu phiên làm việc
require_once 'admin_auth.php';

// Nhúng file kết nối CSDL
require_once '../includes/db.php';

/**
 * 1. XỬ LÝ GỬI EMAIL YÊU CẦU CẬP NHẬT THÔNG TIN
 * Admin không được phép sửa thông tin khách trực tiếp.
 * Thay vào đó gửi email để khách tự cập nhật.
 */
if (isset($_POST['send_update_email'])) {
    $uid = (int)($_POST['user_id'] ?? 0);
    $stmtU = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtU->execute([$uid]);
    $targetUser = $stmtU->fetch();

    if ($targetUser && !empty($targetUser['email'])) {
        $toEmail  = $targetUser['email'];
        $toName   = $targetUser['fullname'] ?? 'Quý khách';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $loginUrl = $protocol . '://' . $host . '/profile.php';

        $subject = '[NHK Mobile] Yêu cầu cập nhật thông tin tài khoản';
        $body    = "<div style='font-family:Arial,sans-serif;max-width:560px;margin:auto;'>"
                 . "<div style='background:linear-gradient(135deg,#007AFF,#5856D6);padding:32px;border-radius:16px 16px 0 0;text-align:center;'>"
                 . "<h1 style='color:#fff;margin:0;font-size:24px;'>NHK Mobile</h1>"
                 . "<p style='color:rgba(255,255,255,0.8);margin:8px 0 0;'>Thông báo từ hệ thống</p>"
                 . "</div>"
                 . "<div style='background:#fff;padding:32px;border-radius:0 0 16px 16px;border:1px solid #e5e7eb;'>"
                 . "<h2 style='color:#1d1d1f;margin:0 0 16px;'>Xin chào {$toName}!</h2>"
                 . "<p style='color:#374151;line-height:1.6;'>Chúng tôi nhận thấy thông tin tài khoản của bạn cần được xác nhận lại để đảm bảo trải nghiệm mua sắm tốt nhất.</p>"
                 . "<p style='color:#374151;line-height:1.6;'>Vui lòng đăng nhập và cập nhật thông tin cá nhân của bạn (họ tên, số điện thoại, địa chỉ giao hàng):</p>"
                 . "<div style='text-align:center;margin:28px 0;'>"
                 . "<a href='{$loginUrl}' style='background:linear-gradient(135deg,#007AFF,#5856D6);color:#fff;padding:14px 32px;border-radius:50px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;'>Cập nhật thông tin ngay</a>"
                 . "</div>"
                 . "<p style='color:#9ca3af;font-size:13px;'>Nếu bạn không yêu cầu điều này, vui lòng bỏ qua email này.</p>"
                 . "<hr style='border:none;border-top:1px solid #e5e7eb;margin:20px 0;'>"
                 . "<p style='color:#9ca3af;font-size:12px;margin:0;'>NHK Mobile &mdash; Hệ thống bán điện thoại chính hãng</p>"
                 . "</div></div>";

        if (function_exists('send_store_mail')) {
            send_store_mail($toEmail, $subject, $body, $pdo);
        } else {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: NHK Mobile <no-reply@nhkmobile.com>\r\n";
            @mail($toEmail, $subject, $body, $headers);
        }

        log_admin_action($pdo, 'SEND_UPDATE_EMAIL', "Gửi email yêu cầu cập nhật TT tới user ID $uid ({$toEmail})");
        header("Location: users.php?msg=" . urlencode("✅ Email yêu cầu cập nhật đã gửi tới {$toEmail}!"));
    } else {
        header("Location: users.php?error=" . urlencode("Không tìm thấy email khách hàng!"));
    }
    exit;
}

/**
 * 2. CẬP NHẬT TRẠNG THÁI USER (Khóa / Mở khóa)
 */
if (isset($_POST['update_status'])) {
    $id     = $_POST['id'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    log_admin_action($pdo, 'CHANGE_USER_STATUS', "Đổi trạng thái người dùng ID $id thành $status");
    header("Location: users.php?msg=" . urlencode("Đã cập nhật trạng thái người dùng thành công!"));
    exit;
}

/**
 * 3. TRUY VẤN DANH SÁCH USER
 */
$limit  = 5;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search      = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = " WHERE 1=1";
$params      = [];

if ($search !== '') {
    $whereClause .= " AND phone LIKE ?";
    $params[]     = "%$search%";
}

$sqlCount    = "SELECT COUNT(*) FROM users" . $whereClause;
$stmtCount   = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages   = ceil($totalRecords / $limit);

$sql   = "SELECT * FROM users" . $whereClause . " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt  = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = "Quản lý Người dùng | Admin NHK Mobile";
$basePath  = "../";
include 'includes/admin_header.php';
?>

        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                 <h2 class="fw-bold mb-1">Quản lý Khách hàng</h2>
                 <p class="text-secondary small mb-0">Xem danh sách đăng ký, khóa tài khoản vi phạm và xem chi tiết thông tin khách hàng.</p>
            </div>
        </header>

        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
            <form action="" method="GET" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control bg-light border-0" placeholder="Tìm theo số điện thoại..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
                <div class="col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-funnel"></i> Tìm kiếm</button>
                    <?php if ($search): ?>
                        <a href="users.php" class="btn btn-outline-secondary px-3 shadow-sm">Xóa</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="content-card shadow-sm border-0 rounded-4 p-4 bg-white">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4 border-0 rounded-3" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4 border-0 rounded-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>


            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr class="small text-uppercase text-secondary">
                            <th>ID</th>
                            <th>Người dùng</th>
                            <th>Liên hệ</th>
                            <th>Ngày tham gia</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td class="text-secondary fw-bold small">#USR-<?php echo $u['id']; ?></td>
                            <td>
                                 <div class="fw-bold"><?php echo htmlspecialchars($u['fullname'] ?? ''); ?></div>
                                 <div class="small text-secondary"
                                      style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                                      title="<?php echo htmlspecialchars($u['address'] ?? ''); ?>">
                                     <i class="bi bi-geo-alt"></i>
                                     <?php
                                         $addr = $u['address'] ?? '';
                                         echo $addr !== ''
                                             ? htmlspecialchars($addr)
                                             : '<span class="text-muted fst-italic">Chưa cập nhật</span>';
                                     ?>
                                 </div>
                            </td>
                            <td>
                                <div class="small">
                                    <i class="bi bi-envelope"></i>
                                    <?php echo htmlspecialchars($u['email'] ?? ''); ?>
                                </div>
                                <div class="small text-secondary">
                                    <i class="bi bi-telephone"></i>
                                    <?php
                                        $phone = $u['phone'] ?? '';
                                        echo $phone !== ''
                                            ? htmlspecialchars($phone)
                                            : '<span class="text-muted fst-italic">Chưa cập nhật</span>';
                                    ?>
                                </div>
                            </td>
                            <td class="small text-secondary"><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></td>
                            <td>
                                <?php if ($u['status'] === 'active' || empty($u['status'])): ?>
                                    <span class="badge bg-success-subtle text-success border fw-normal px-2 rounded-pill">Hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border fw-normal px-2 rounded-pill">Đã khóa</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <!-- Nút Khóa / Mở khóa -->
                                <form action="users.php" method="POST" style="display: inline-block;">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <?php if ($u['status'] === 'active' || empty($u['status'])): ?>
                                        <button type="submit" name="update_status" value="1"
                                                class="btn btn-sm btn-outline-danger shadow-sm px-3 rounded-pill"
                                                title="Khóa tài khoản này">
                                            <i class="bi bi-lock me-1"></i> Khóa
                                            <input type="hidden" name="status" value="banned">
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="update_status" value="1"
                                                class="btn btn-sm btn-outline-success shadow-sm px-3 rounded-pill"
                                                title="Mở khóa tài khoản">
                                            <i class="bi bi-unlock me-1"></i> Mở khóa
                                            <input type="hidden" name="status" value="active">
                                        </button>
                                    <?php endif; ?>
                                </form>
                                <!-- Nút Xem chi tiết thông tin khách hàng -->
                                <button type="button" 
                                        class="btn btn-sm btn-light border p-2 ms-1 rounded-pill" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#userDetailModal<?php echo $u['id']; ?>"
                                        title="Xem thông tin chi tiết">
                                    <i class="bi bi-eye text-primary"></i>
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Modal Chi tiết khách hàng -->
                        <div class="modal fade" id="userDetailModal<?php echo $u['id']; ?>" tabindex="-1" aria-labelledby="userDetailModalLabel<?php echo $u['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow rounded-4">
                                    <div class="modal-header border-0 bg-light rounded-top-4 py-3">
                                        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="userDetailModalLabel<?php echo $u['id']; ?>">
                                            <i class="bi bi-person-badge text-primary"></i> Chi tiết khách hàng
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4 text-start">
                                        <div class="text-center mb-4">
                                            <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 72px; height: 72px;">
                                                <i class="bi bi-person-fill fs-1"></i>
                                            </div>
                                            <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($u['fullname'] ?? ''); ?></h4>
                                            <span class="badge <?php echo ($u['status'] === 'active' || empty($u['status'])) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?> px-3 py-2 rounded-pill fw-normal">
                                                <?php echo ($u['status'] === 'active' || empty($u['status'])) ? 'Đang hoạt động' : 'Đã khóa'; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="p-3 bg-light rounded-3 d-flex align-items-center gap-3">
                                                    <div class="bg-white rounded-3 p-2 text-primary shadow-sm">
                                                        <i class="bi bi-hash fs-5"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-secondary d-block">Mã khách hàng</small>
                                                        <span class="fw-bold text-dark">#USR-<?php echo $u['id']; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-12">
                                                <div class="p-3 bg-light rounded-3 d-flex align-items-center gap-3">
                                                    <div class="bg-white rounded-3 p-2 text-primary shadow-sm">
                                                        <i class="bi bi-envelope fs-5"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-secondary d-block">Email</small>
                                                        <a href="mailto:<?php echo htmlspecialchars($u['email'] ?? ''); ?>" class="fw-medium text-decoration-none text-dark"><?php echo htmlspecialchars($u['email'] ?? ''); ?></a>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-12">
                                                <div class="p-3 bg-light rounded-3 d-flex align-items-center gap-3">
                                                    <div class="bg-white rounded-3 p-2 text-primary shadow-sm">
                                                        <i class="bi bi-telephone fs-5"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-secondary d-block">Số điện thoại</small>
                                                        <?php if (!empty($u['phone'])): ?>
                                                            <a href="tel:<?php echo htmlspecialchars($u['phone']); ?>" class="fw-medium text-decoration-none text-dark"><?php echo htmlspecialchars($u['phone']); ?></a>
                                                        <?php else: ?>
                                                            <span class="text-muted fst-italic">Chưa cập nhật</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="p-3 bg-light rounded-3 d-flex align-items-start gap-3">
                                                    <div class="bg-white rounded-3 p-2 text-primary shadow-sm mt-1">
                                                        <i class="bi bi-geo-alt fs-5"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <small class="text-secondary d-block">Địa chỉ giao hàng</small>
                                                        <span class="fw-medium text-dark"><?php echo !empty($u['address']) ? htmlspecialchars($u['address']) : '<span class="text-muted fst-italic">Chưa cập nhật</span>'; ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="p-3 bg-light rounded-3 d-flex align-items-center gap-3">
                                                    <div class="bg-white rounded-3 p-2 text-primary shadow-sm">
                                                        <i class="bi bi-calendar-check fs-5"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-secondary d-block">Ngày tham gia</small>
                                                        <span class="fw-medium text-dark"><?php echo date('d/m/Y H:i:s', strtotime($u['created_at'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0 p-3 bg-light rounded-bottom-4">
                                        <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Đóng</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if (count($users) === 0): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-secondary">Chưa có người dùng nào.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination UI -->
            <?php if (isset($totalPages) && $totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-end mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">Trước</a>
                    </li>
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage   = min($totalPages, $page + 2);
                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1' . ($search ? '&search='.urlencode($search) : '') . '">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor;
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . ($search ? '&search='.urlencode($search) : '') . '">' . $totalPages . '</a></li>';
                    }
                    ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">Sau</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </main>

<?php include 'includes/admin_footer.php'; ?>
