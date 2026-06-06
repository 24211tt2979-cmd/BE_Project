<?php
// Bắt đầu phiên làm việc
require_once 'admin_auth.php';

// Nhúng file kết nối CSDL MySQL
require_once '../includes/db.php';

/**
 * 1. XỬ LÝ LOGIC (THÊM / SỬA / XÓA BẢO HÀNH)
 */

if (isset($_POST['save_warranty'])) {
    $id = $_POST['id'] ?? null;
    $imei = trim($_POST['imei']);
    $product_id = $_POST['product_id'];
    $order_id = $_POST['order_id'] ? $_POST['order_id'] : null;
    $status = $_POST['status'];
    $expires_at = $_POST['expires_at'];
    $customer_name = $_POST['customer_name'] ?? null;
    $customer_phone = $_POST['customer_phone'] ?? null;

    try {
        if ($id) {
            // Cập nhật
            $sql = "UPDATE warranties SET imei = ?, product_id = ?, order_id = ?, status = ?, expires_at = ?, customer_name = ?, customer_phone = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$imei, $product_id, $order_id, $status, $expires_at, $customer_name, $customer_phone, $id]);
        } else {
            // Thêm mới
            $sql = "INSERT INTO warranties (imei, product_id, order_id, status, expires_at, customer_name, customer_phone) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$imei, $product_id, $order_id, $status, $expires_at, $customer_name, $customer_phone]);
        }
        header("Location: warranties.php?msg=success");
        exit;
    } catch (PDOException $e) {
        // Lỗi trùng IMEI hoặc khóa ngoại
        if (($e->errorInfo[1] ?? null) == 1062) {
            $error = "Lỗi: Số IMEI này đã được kích hoạt trước đó!";
        } elseif (($e->errorInfo[1] ?? null) == 1452) {
            $error = "Lỗi: Mã đơn hàng (Order ID) không tồn tại trong hệ thống. Vui lòng kiểm tra lại.";
        } else {
            $error = "Có lỗi xảy ra: " . $e->getMessage();
        }
    }
}

if (isset($_GET['delete'])) {
    try {
        $id = $_GET['delete'];
        // Attempt to clean up related repair history first manually just in case cascade is missing
        $pdo->prepare("DELETE FROM repair_history WHERE warranty_id = ?")->execute([$id]);
        // Delete the warranty
        $stmt = $pdo->prepare("DELETE FROM warranties WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: warranties.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $error = "Không thể xóa dữ liệu bảo hành do lỗi CSDL: " . htmlspecialchars($e->getMessage());
    }
}

if (isset($_POST['save_repair'])) {
    $warranty_id = $_POST['warranty_id'];
    $service_type = trim($_POST['service_type']);
    $symptoms = trim($_POST['symptoms']);
    $action_taken = trim($_POST['action_taken']);
    $status = trim($_POST['repair_status']);
    $technician = trim($_POST['technician']);
    $completion_date = $_POST['completion_date'];

    $description = "Tình trạng lỗi: " . $symptoms . "\nThay thế / Xử lý: " . $action_taken . "\nTrạng thái: " . $status;
    $repair_id = "REP-" . rand(10000, 99999);

    // Create table if not exists
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
            CONSTRAINT repair_history_admin_warranty_fk FOREIGN KEY (warranty_id) REFERENCES warranties(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $sql = "INSERT INTO repair_history (warranty_id, repair_date, title, description, location, repair_id) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$warranty_id, $completion_date, $service_type, $description, $technician, $repair_id]);
    
    header("Location: warranties.php?msg=success");
    exit;
}

/**
 * 2. LẤY DỮ LIỆU HIỂN THỊ
 */

// Khởi tạo biến editData mặc định là null (chế độ thêm mới)
$editData = null;

// Nếu URL có ?edit=ID thì lấy dữ liệu bản ghi đó để điền vào form
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmtEdit = $pdo->prepare("SELECT * FROM warranties WHERE id = ?");
    $stmtEdit->execute([$editId]);
    $editData = $stmtEdit->fetch() ?: null;
}

// Cấu hình phân trang
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Lấy danh sách bảo hành kèm tên sản phẩm
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = "";
$params = [];

if ($search !== '') {
    $whereClause = " WHERE w.imei LIKE ?";
    $params[] = "%$search%";
}

// Đếm tổng số bản ghi
$sqlCount = "SELECT COUNT(*) FROM warranties w" . $whereClause;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "
    SELECT w.*, p.name as product_name 
    FROM warranties w 
    LEFT JOIN products p ON w.product_id = p.id 
" . $whereClause . " ORDER BY w.created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$warranties = $stmt->fetchAll();

// Lấy danh sách sản phẩm để cho vào dropdown
$stmtProd = $pdo->query("SELECT id, name FROM products ORDER BY name ASC");
$productsList = $stmtProd->fetchAll();


$pageTitle = "Quản lý Bảo hành | Admin NHK Mobile";
$basePath = "../";
include 'includes/admin_header.php';
?>
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                 <h2 class="fw-bold mb-1">Quản lý Bảo hành (IMEI)</h2>
                 <p class="text-secondary small mb-0">Quản lý mã định danh thiết bị bán ra và thời hạn bảo hành.</p>
            </div>
            <button class="btn btn-primary shadow-sm px-4 py-2 rounded-3" data-bs-toggle="modal" data-bs-target="#warrantyModal">
                <i class="bi bi-plus-lg me-2"></i> Kích hoạt IMEI mới
            </button>
        </header>

        <div class="d-flex mb-4">
            <form action="" method="GET" class="d-flex w-100" style="max-width: 400px;">
                <input type="text" name="search" class="form-control me-2 rounded-pill" placeholder="Tìm kiếm theo mã IMEI..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                <button type="submit" class="btn btn-primary rounded-pill px-3 shadow-sm"><i class="bi bi-search"></i></button>
                <?php if (!empty($search)): ?>
                    <a href="warranties.php" class="btn btn-outline-secondary rounded-pill px-3 ms-2 shadow-sm d-flex align-items-center">Xóa</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="content-card shadow-sm border-0 rounded-4 p-4 bg-white">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4 border-0 rounded-3" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> Thao tác thành công!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger mb-4 border-0 rounded-3"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr class="small text-uppercase text-secondary">
                            <th>Mã IMEI</th>
                            <th>Sản phẩm áp dụng</th>
                            <th>Đơn hàng</th>
                            <th>Ngày hết hạn</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($warranties as $w): ?>
                        <tr>
                            <td class="fw-bold fw-monospace text-primary">
                                <i class="bi bi-upc-scan me-1"></i> <?php echo htmlspecialchars($w['imei']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($w['product_name']); ?></td>
                            <td>
                                <?php if($w['order_id']): ?>
                                    <a href="invoice.php?order_id=<?php echo $w['order_id']; ?>" target="_blank" class="text-decoration-none">#ORD-<?php echo $w['order_id']; ?></a>
                                <?php else: ?>
                                    <span class="text-secondary small">Kích hoạt tay</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-secondary">
                                <?php echo date('d/m/Y', strtotime($w['expires_at'])); ?>
                                <?php if(strtotime($w['expires_at']) < time()): ?>
                                    <br><span class="badge bg-danger-subtle text-danger border px-2 mt-1">Hết hạn</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $badge = 'success';
                                    $statusVn = $w['status'];
                                    if ($w['status'] == 'Active') $statusVn = 'Hợp lệ';
                                    if ($w['status'] == 'Voided' || $w['status'] == 'Từ chối') {
                                        $badge = 'danger';
                                        $statusVn = 'Từ chối';
                                    }
                                    if ($w['status'] == 'Expired' || $w['status'] == 'Hết hạn') {
                                        $badge = 'secondary';
                                        $statusVn = 'Hết hạn';
                                    }
                                ?>
                                <span class="badge bg-<?php echo $badge; ?>-subtle text-dark border fw-normal px-2"><?php echo htmlspecialchars($statusVn); ?></span>
                            </td>
                            <td class="text-end" style="white-space: nowrap;">
                                <button type="button" class="btn btn-sm btn-light border p-2 text-info" onclick="openRepairModal(<?php echo $w['id']; ?>, '<?php echo htmlspecialchars($w['imei']); ?>')" title="Thêm phiếu sửa chữa"><i class="bi bi-tools"></i></button>
                                <a href="warranties.php?edit=<?php echo $w['id']; ?>" class="btn btn-sm btn-light border p-2 ms-1"><i class="bi bi-pencil text-primary"></i></a>
                                <a href="warranties.php?delete=<?php echo $w['id']; ?>" class="btn btn-sm btn-light border p-2 text-danger ms-1" onclick="return confirm('Xóa trạm bảo hành IMEI này?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($warranties) === 0): ?>
                        <tr><td colspan="6" class="text-center py-4 text-secondary">Chưa có mã IMEI nào được kích hoạt.</td></tr>
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

    <!-- MODAL THÔNG TIN BẢO HÀNH -->
    <div class="modal fade <?php echo $editData ? 'show' : ''; ?>" id="warrantyModal" tabindex="-1" <?php echo $editData ? 'style="display: block; background: rgba(0,0,0,0.5)"' : ''; ?>>
        <div class="modal-dialog border-0">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <form action="warranties.php" method="POST">
                    <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                        <h5 class="fw-bold mb-0"><?php echo $editData ? 'Sửa thông tin bảo hành' : 'Kích hoạt IMEI mới'; ?></h5>
                        <a href="warranties.php" class="btn-close"></a>
                    </div>
                    <div class="modal-body px-4 py-4">
                        <input type="hidden" name="id" value="<?php echo $editData['id'] ?? ''; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Số IMEI (Định danh 15 chữ số) *</label>
                            <input type="text" name="imei" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editData['imei'] ?? ''); ?>" required placeholder="VD: 351234567890123">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Mã Hóa đơn / Order ID (Tùy chọn)</label>
                            <div class="input-group">
                                <input type="number" name="order_id" id="order_id_input" class="form-control bg-light border-0" value="<?php echo $editData['order_id'] ?? ''; ?>" placeholder="Nhập ID đơn hàng">
                                <button type="button" class="btn btn-outline-primary" id="btn_fetch_order"><i class="bi bi-cloud-download"></i> Tải thông tin</button>
                            </div>
                            <small class="text-muted" id="order_info_msg"></small>
                        </div>

                        <div class="row mb-3" id="customer_info_block">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Tên khách hàng</label>
                                <input type="text" name="customer_name" id="customer_name_input" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editData['customer_name'] ?? ''); ?>" placeholder="Nhập tên KH (hoặc tự động tải)">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Số điện thoại</label>
                                <input type="text" name="customer_phone" id="customer_phone_input" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editData['customer_phone'] ?? ''); ?>" placeholder="Nhập SĐT (hoặc tự động tải)">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Sản phẩm áp dụng *</label>
                            <select name="product_id" id="product_id_select" class="form-select bg-light border-0" required>
                                <option value="">-- Chọn sản phẩm --</option>
                                <?php foreach($productsList as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo (isset($editData['product_id']) && $editData['product_id'] == $p['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-success d-none mt-1" id="product_hint_msg"><i class="bi bi-check-circle"></i> Đã lọc sản phẩm theo đơn hàng</small>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold">Trạng thái *</label>
                                <select name="status" class="form-select bg-light border-0">
                                    <option value="Active" <?php echo (isset($editData['status']) && $editData['status'] == 'Active') ? 'selected' : ''; ?>>Hợp lệ (Active)</option>
                                    <option value="Expired" <?php echo (isset($editData['status']) && $editData['status'] == 'Expired') ? 'selected' : ''; ?>>Hết hạn</option>
                                    <option value="Từ chối" <?php echo (isset($editData['status']) && $editData['status'] == 'Từ chối') ? 'selected' : ''; ?>>Từ chối bảo hành (Rơi vỡ)</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold">Ngày hết hạn *</label>
                                <input type="date" name="expires_at" class="form-control bg-light border-0" value="<?php echo isset($editData['expires_at']) ? date('Y-m-d', strtotime($editData['expires_at'])) : date('Y-m-d', strtotime('+12 months')); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 px-4 pb-4">
                        <a href="warranties.php" class="btn btn-light px-4 rounded-pill">Hủy bỏ</a>
                        <button type="submit" name="save_warranty" class="btn btn-primary px-4 rounded-pill shadow-sm">Lưu dữ liệu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL PHIẾU SỬA CHỮA -->
    <div class="modal fade" id="repairModal" tabindex="-1">
        <div class="modal-dialog border-0" style="max-width: 550px;">
            <div class="modal-content rounded-4 border-0 shadow-lg" style="border-left: 5px solid #0d6efd !important;">
                <form action="warranties.php" method="POST">
                    <div class="modal-header border-bottom-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.25rem;"><i class="bi bi-file-earmark-medical-fill text-primary me-2"></i> Phiếu Sửa Chữa Chi Tiết</h5>
                        <span class="badge bg-warning-subtle text-warning border px-3 py-2 rounded-pill fw-bold" id="displayRepairId">MÃ PHIẾU: #----</span>
                        <button type="button" class="btn-close d-none" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body px-4 py-4">
                        <input type="hidden" name="warranty_id" id="repair_warranty_id" value="">
                        
                        <div class="row mb-3 gx-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-secondary text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">Số IMEI</label>
                                <input type="text" id="repair_imei_display" class="form-control bg-light border-0" value="" readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-secondary text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">Loại dịch vụ</label>
                                <select name="service_type" class="form-select bg-light border-0" required>
                                    <option value="Sửa chữa ngoài bảo hành">Sửa ngoài bảo hành</option>
                                    <option value="Sửa chữa thiết bị (Bảo hành)">Sửa trong bảo hành</option>
                                    <option value="Thay pin chính hãng">Thay pin</option>
                                    <option value="Thay màn hình">Thay màn hình</option>
                                    <option value="Cập nhật phần mềm">Cập nhật phần mềm</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">Tình trạng lỗi (Triệu chứng)</label>
                            <textarea name="symptoms" class="form-control bg-light border-0" rows="3" placeholder="Mô tả các vấn đề của thiết bị..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">Thao tác xử lý / Linh kiện thay</label>
                            <textarea name="action_taken" class="form-control bg-light border-0" rows="3" placeholder="VD: Thay thế màn hình OLED, vệ sinh thiết bị..."></textarea>
                        </div>

                        <div class="row gx-3 mt-4">
                            <div class="col-4 mb-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">Trạng thái</label>
                                <select name="repair_status" class="form-select bg-light border-0 text-primary fw-medium" required>
                                    <option value="Đang xử lý">Đang xử lý</option>
                                    <option value="Đã hoàn thành">Đã hoàn thành</option>
                                    <option value="Tạm dừng">Tạm dừng</option>
                                </select>
                            </div>
                            <div class="col-4 mb-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">Kỹ thuật viên</label>
                                <input type="text" name="technician" class="form-control bg-light border-0" placeholder="Họ và tên" required>
                            </div>
                            <div class="col-4 mb-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">Ngày hoàn thành</label>
                                <input type="date" name="completion_date" class="form-control bg-light border-0" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 px-4 pb-4">
                        <button type="button" class="btn btn-light px-4 rounded-pill fw-medium" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" name="save_repair" class="btn btn-primary px-4 rounded-pill shadow-sm fw-medium" style="background-color: #005f99; border: none;">Lưu Phiếu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        <?php if ($editData): ?>
        // Mở sẵn Modal nền nếu có biến GET edit
        var myModal = new bootstrap.Modal(document.getElementById('warrantyModal'), {
            keyboard: false, backdrop: 'static'
        });
        myModal.show();
        <?php endif; ?>

        function openRepairModal(id, imei) {
            document.getElementById('repair_warranty_id').value = id;
            document.getElementById('repair_imei_display').value = imei;
            document.getElementById('displayRepairId').innerText = 'MÃ PHIẾU: #' + Math.floor(10000 + Math.random() * 90000);
            var repairModal = new bootstrap.Modal(document.getElementById('repairModal'), {
                keyboard: true
            });
            repairModal.show();
        }

        // Fetch order details via AJAX
        document.getElementById('btn_fetch_order').addEventListener('click', function() {
            var orderId = document.getElementById('order_id_input').value.trim();
            var msgEl = document.getElementById('order_info_msg');
            
            if (!orderId) {
                msgEl.innerHTML = '<span class="text-danger">Vui lòng nhập Order ID</span>';
                return;
            }

            msgEl.innerHTML = '<span class="text-primary"><i class="spinner-border spinner-border-sm"></i> Đang tải...</span>';
            
            fetch('../api/admin_get_order_info.php?order_id=' + orderId)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        msgEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Tải thông tin thành công!</span>';
                        document.getElementById('customer_name_input').value = data.data.customer_name;
                        document.getElementById('customer_phone_input').value = data.data.customer_phone;
                        
                        // Lọc sản phẩm
                        if (data.data.items && data.data.items.length > 0) {
                            var select = document.getElementById('product_id_select');
                            var hint = document.getElementById('product_hint_msg');
                            // Giữ lại option mặc định
                            select.innerHTML = '<option value="">-- Chọn sản phẩm --</option>';
                            data.data.items.forEach(function(item) {
                                var option = document.createElement('option');
                                option.value = item.product_id;
                                option.text = item.product_name;
                                select.appendChild(option);
                            });
                            // Nếu chỉ có 1 sản phẩm thì tự động chọn luôn
                            if (data.data.items.length === 1) {
                                select.value = data.data.items[0].product_id;
                            }
                            hint.classList.remove('d-none');
                        }
                    } else {
                        msgEl.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ' + data.message + '</span>';
                    }
                })
                .catch(e => {
                    msgEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Lỗi kết nối</span>';
                });
        });
    </script>

<?php include 'includes/admin_footer.php'; ?>
