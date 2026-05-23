<?php
// Bắt đầu phiên làm việc
require_once 'admin_auth.php';

// Nhúng file kết nối CSDL Postgres
require_once '../includes/db.php';

/**
 * 1. XỬ LÝ CẬP NHẬT TRẠNG THÁI USER VÀ CRUD
 */
if (isset($_POST['save_user'])) {
    $id = $_POST['id'] ?? null;
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $status = $_POST['status'];
    $password = $_POST['password'];

    if (!empty($phone) && !is_valid_phone($phone)) {
        header("Location: users.php?error=" . urlencode("Số điện thoại không hợp lệ! Vui lòng nhập đúng 10 chữ số."));
        exit;
    }

    try {
        if ($id) {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET fullname = ?, email = ?, phone = ?, address = ?, status = ?, password = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$fullname, $email, $phone, $address, $status, $hashed_password, $id]);
            } else {
                $sql = "UPDATE users SET fullname = ?, email = ?, phone = ?, address = ?, status = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$fullname, $email, $phone, $address, $status, $id]);
            }
            $msg = "Cập nhật thông tin khách hàng thành công!";
            log_admin_action($pdo, 'UPDATE_USER', "Cập nhật thông tin người dùng ID $id ($fullname, Status: $status)");
        } else {
            if (empty($password)) {
                $password = '123456';
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (fullname, email, phone, address, status, password) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fullname, $email, $phone, $address, $status, $hashed_password]);
            $msg = "Thêm khách hàng mới thành công!";
            $new_user_id = $pdo->lastInsertId();
            log_admin_action($pdo, 'ADD_USER', "Thêm người dùng mới ID $new_user_id ($fullname)");
        }
        header("Location: users.php?msg=" . urlencode($msg));
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() == '23505') {
            header("Location: users.php?error=" . urlencode("Email đã tồn tại trong hệ thống!"));
            exit;
        } else {
            header("Location: users.php?error=" . urlencode("Có lỗi xảy ra: " . $e->getMessage()));
            exit;
        }
    }
}

if (isset($_POST['update_status'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];
    
    // Câu lệnh SQL cập nhật trạng thái
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    
    log_admin_action($pdo, 'CHANGE_USER_STATUS', "Đổi trạng thái người dùng ID $id thành $status");
    
    // Lưu thông báo vào URL và reload trang
    header("Location: users.php?msg=" . urlencode("Đã cập nhật trạng thái người dùng thành công!"));
    exit;
}

/**
 * 2. TRUY VẤN DANH SÁCH USER
 */
// Cấu hình phân trang
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$whereClause = " WHERE 1=1";
$params = [];
if ($search !== '') {
    $whereClause .= " AND phone LIKE ?";
    $params[] = "%$search%";
}

// Đếm tổng số bản ghi
$sqlCount = "SELECT COUNT(*) FROM users" . $whereClause;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT * FROM users" . $whereClause . " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$editData = null;
if (isset($_GET['edit'])) {
    $stmtEdit = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtEdit->execute([$_GET['edit']]);
    $editData = $stmtEdit->fetch() ?: null;
}

$pageTitle = "Quản lý Người dùng | Admin NHK Mobile";
$basePath = "../";
include 'includes/admin_header.php';
?>

        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                 <h2 class="fw-bold mb-1">Quản lý Khách hàng</h2>
                 <p class="text-secondary small mb-0">Xem danh sách đăng ký và khóa tài khoản vi phạm.</p>
            </div>
            <button class="btn btn-primary shadow-sm px-4 py-2 rounded-3" data-bs-toggle="modal" data-bs-target="#userModal">
                <i class="bi bi-person-plus me-2"></i> Thêm Khách hàng
            </button>
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
            <!-- Hiển thị thông báo khi cập nhật thành công -->
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-primary alert-dismissible fade show mb-4 border-0 rounded-3" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
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
                        <!-- Vòng lặp PHP Duyệt danh sách User -->
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
                                <a href="users.php?edit=<?php echo $u['id']; ?>" class="btn btn-sm btn-light border p-2 ms-1 rounded-pill" title="Sửa thông tin"><i class="bi bi-pencil text-primary"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($users) === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-secondary">Chưa có người dùng nào.</td>
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
                    $endPage = min($totalPages, $page + 2);
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

    <!-- MODAL THÊM / SỬA KHÁCH HÀNG -->
    <div class="modal fade <?php echo $editData ? 'show' : ''; ?>" id="userModal" tabindex="-1" <?php echo $editData ? 'style="display: block; background: rgba(0,0,0,0.5)"' : ''; ?>>
        <div class="modal-dialog border-0">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <form action="users.php" method="POST">
                    <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                        <h5 class="fw-bold mb-0"><?php echo $editData ? 'Sửa thông tin Khách hàng' : 'Thêm Khách hàng mới'; ?></h5>
                        <a href="users.php" class="btn-close"></a>
                    </div>
                    <div class="modal-body px-4 py-4">
                        <input type="hidden" name="id" value="<?php echo $editData['id'] ?? ''; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Họ và tên *</label>
                            <input type="text" name="fullname" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editData['fullname'] ?? ''); ?>" required placeholder="Nguyễn Văn A">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Email *</label>
                            <input type="email" name="email" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editData['email'] ?? ''); ?>" required placeholder="email@example.com">
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold">Số điện thoại</label>
                                <input type="text" name="phone" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editData['phone'] ?? ''); ?>" placeholder="0901234567" pattern="[0-9]{10}" title="Vui lòng nhập đúng 10 chữ số">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold">Trạng thái *</label>
                                <select name="status" class="form-select bg-light border-0" required>
                                    <option value="active" <?php echo (isset($editData['status']) && $editData['status'] == 'active') ? 'selected' : ''; ?>>Hoạt động</option>
                                    <option value="banned" <?php echo (isset($editData['status']) && $editData['status'] == 'banned') ? 'selected' : ''; ?>>Đã khóa</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Địa chỉ</label>
                            <textarea name="address" class="form-control bg-light border-0" rows="2" placeholder="Địa chỉ giao hàng..."><?php echo htmlspecialchars($editData['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Mật khẩu <?php echo $editData ? '(Để trống nếu không đổi)' : '*'; ?></label>
                            <input type="password" name="password" class="form-control bg-light border-0" placeholder="Nhập mật khẩu..." <?php echo !$editData ? 'required' : ''; ?>>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 px-4 pb-4">
                        <a href="users.php" class="btn btn-light px-4 rounded-pill">Hủy bỏ</a>
                        <button type="submit" name="save_user" class="btn btn-primary px-4 rounded-pill shadow-sm">Lưu dữ liệu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php include 'includes/admin_footer.php'; ?>
