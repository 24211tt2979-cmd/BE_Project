<?php
// Bắt đầu phiên làm việc
require_once 'admin_auth.php';

// Nhúng file kết nối CSDL
require_once '../includes/db.php';

/**
 * 1. XỬ LÝ CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG
 * Sử dụng Form với phương thức POST để thay đổi status (Completed / Cancelled)
 */
if (isset($_POST['update_status'])) {
    $id = $_POST['id'];
    $newStatus = $_POST['status'];
    
    try {
        $pdo->beginTransaction();
        
        // Lấy trạng thái hiện tại
        $stmtCurrent = $pdo->prepare("SELECT status FROM orders WHERE id = ? FOR UPDATE");
        $stmtCurrent->execute([$id]);
        $currentStatus = $stmtCurrent->fetchColumn();
        
        if ($currentStatus !== false && $currentStatus !== $newStatus) {
            $isOldCancelled = ($currentStatus === 'Đã hủy');
            $isNewCancelled = ($newStatus === 'Đã hủy');
            
            // Nếu đổi sang Đã hủy -> Hoàn trả tồn kho
            if (!$isOldCancelled && $isNewCancelled) {
                $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $stmtItems->execute([$id]);
                $items = $stmtItems->fetchAll();
                
                $stmtUpdateStock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                foreach ($items as $item) {
                    if ($item['product_id']) {
                        $stmtUpdateStock->execute([$item['quantity'], $item['product_id']]);
                    }
                }
            } 
            // Nếu từ Đã hủy đổi sang trạng thái khác (phục hồi đơn) -> Trừ kho lại
            else if ($isOldCancelled && !$isNewCancelled) {
                $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $stmtItems->execute([$id]);
                $items = $stmtItems->fetchAll();
                
                $stmtUpdateStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                foreach ($items as $item) {
                    if ($item['product_id']) {
                        $stmtUpdateStock->execute([$item['quantity'], $item['product_id']]);
                    }
                }
            }
            
            // Cập nhật trạng thái mới
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);
            
            log_admin_action($pdo, 'UPDATE_ORDER_STATUS', "Cập nhật trạng thái đơn hàng ID $id từ '$currentStatus' thành '$newStatus'");
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("[Admin Orders] Error updating status: " . $e->getMessage());
    }
    
    // Lưu thông báo vào URL và reload trang
    header("Location: orders.php?msg=updated");
    exit;
}

/**
 * 2. TRUY VẤN DANH SÁCH ĐƠN HÀNG
 */
// Lấy toàn bộ đơn hàng, ưu tiên những đơn mới nhất xếp trên (DESC)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Cấu hình phân trang
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$whereClause = " WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereClause .= " AND (customer_phone LIKE ? OR customer_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status_filter !== '') {
    $whereClause .= " AND status = ?";
    $params[] = $status_filter;
}
if ($start_date !== '') {
    $whereClause .= " AND DATE(created_at) >= ?";
    $params[] = $start_date;
}
if ($end_date !== '') {
    $whereClause .= " AND DATE(created_at) <= ?";
    $params[] = $end_date;
}

// Đếm tổng số bản ghi
$sqlCount = "SELECT COUNT(*) FROM orders" . $whereClause;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT * FROM orders" . $whereClause . " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Chuỗi query string cho phân trang
$queryString = "";
if ($search) $queryString .= '&search='.urlencode($search);
if ($status_filter) $queryString .= '&status_filter='.urlencode($status_filter);
if ($start_date) $queryString .= '&start_date='.urlencode($start_date);
if ($end_date) $queryString .= '&end_date='.urlencode($end_date);

$pageTitle = "Quản lý đơn hàng | Admin NHK Mobile";
$basePath = "../";
include 'includes/admin_header.php';
?>

        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                 <h2 class="fw-bold mb-1">Duyệt Đơn Hàng</h2>
                 <p class="text-secondary small mb-0">Quản lý và cập nhật trạng thái giao dịch khách hàng.</p>
            </div>
        </header>

        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
            <form action="" method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control bg-light border-0" placeholder="Tên hoặc SĐT..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select name="status_filter" class="form-select bg-light border-0">
                        <option value="">Tất cả trạng thái</option>
                        <option value="Chờ duyệt" <?php echo ($status_filter == 'Chờ duyệt') ? 'selected' : ''; ?>>Chờ duyệt</option>
                        <option value="Đã duyệt" <?php echo ($status_filter == 'Đã duyệt') ? 'selected' : ''; ?>>Đã duyệt</option>
                        <option value="Đang giao" <?php echo ($status_filter == 'Đang giao') ? 'selected' : ''; ?>>Đang giao</option>
                        <option value="Hoàn thành" <?php echo ($status_filter == 'Hoàn thành') ? 'selected' : ''; ?>>Hoàn thành</option>
                        <option value="Đã hủy" <?php echo ($status_filter == 'Đã hủy') ? 'selected' : ''; ?>>Đã hủy</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="start_date" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($start_date); ?>" title="Từ ngày">
                </div>
                <div class="col-md-2">
                    <input type="date" name="end_date" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($end_date); ?>" title="Đến ngày">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-3 shadow-sm w-100"><i class="bi bi-funnel"></i> Lọc</button>
                    <?php if ($search || $status_filter || $start_date || $end_date): ?>
                        <a href="orders.php" class="btn btn-outline-secondary px-3 shadow-sm">Xóa</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="content-card shadow-sm border-0 rounded-4 p-4 bg-white">
            <!-- Hiển thị thông báo khi cập nhật thành công -->
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-primary alert-dismissible fade show mb-4 border-0 rounded-3" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i> Trạng thái đơn hàng đã được cập nhật!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr class="small text-uppercase text-secondary">
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Số tiền</th>
                            <th>Ngày đặt</th>
                            <th>Thanh toán</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Tiếp nhận</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Vòng lặp PHP Duyệt danh sách đơn hàng -->
                        <?php foreach($orders as $o): 
                            // Lấy chi tiết các sản phẩm trong đơn này kèm theo ảnh từ bảng products
                            $stmtItems = $pdo->prepare("SELECT order_items.*, products.image FROM order_items LEFT JOIN products ON order_items.product_id = products.id WHERE order_id = ?");
                            $stmtItems->execute([$o['id']]);
                            $items = $stmtItems->fetchAll();
                        ?>
                        <tr>
                            <td class="text-secondary fw-bold small">#ORD-<?php echo $o['id']; ?></td>
                            <td>
                                 <div class="fw-bold"><?php echo htmlspecialchars($o['customer_name']); ?></div>
                                 <div class="small text-secondary"><i class="bi bi-phone"></i> <?php echo htmlspecialchars($o['customer_phone']); ?></div>
                                 <?php if (!empty($o['customer_address'])): ?>
                                     <div class="small text-secondary"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($o['customer_address']); ?></div>
                                 <?php endif; ?>
                                 <!-- Hiển thị danh sách sản phẩm có kèm ảnh -->
                                 <div class="mt-2">
                                     <?php foreach($items as $item): ?>
                                         <div class="d-flex align-items-center gap-2 bg-light rounded px-2 py-1 mb-1 border" style="font-size: 0.75rem;">
                                             <img src="../assets/images/<?php echo $item['image']; ?>" class="rounded" style="width: 24px; height: 24px; object-fit: contain;" onerror="this.src='https://placehold.co/24'">
                                             <span class="fw-bold text-dark"><?php echo htmlspecialchars($item['product_name']); ?></span> 
                                             <span class="text-secondary">x<?php echo $item['quantity']; ?></span>
                                         </div>
                                     <?php endforeach; ?>
                                 </div>
                            </td>
                            <td class="fw-bold text-primary"><?php echo number_format($o['total_price'], 0, ',', '.'); ?>₫</td>
                            <td class="small text-secondary">
                                <?php echo date('d/m/Y', strtotime($o['created_at'])); ?>
                                <br><span class="text-muted" style="font-size: 0.75rem;"><?php echo date('H:i', strtotime($o['created_at'])); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-normal px-2"><?php echo htmlspecialchars($o['payment_method']); ?></span>
                                <?php if(isset($o['is_installment']) && $o['is_installment']): ?>
                                    <div class="mt-1"><span class="badge bg-primary-subtle text-primary border-0 fw-bold px-2 py-1" style="font-size: 0.65rem;">TRẢ GÓP 0%</span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- Màu sắc hiển thị badge theo trạng thái -->
                                <?php 
                                    $badgeClass = 'bg-warning text-dark';
                                    $s = mb_strtolower($o['status'], 'UTF-8');
                                    if (str_contains($s, 'đã duyệt')) $badgeClass = 'bg-info text-white';
                                    elseif (str_contains($s, 'đang giao')) $badgeClass = 'bg-primary text-white';
                                    elseif (str_contains($s, 'hoàn thành') || str_contains($s, 'completed')) $badgeClass = 'bg-success text-white';
                                    elseif (str_contains($s, 'hủy') || str_contains($s, 'cancel')) $badgeClass = 'bg-danger text-white';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?> border-0 px-3 py-2 rounded-pill small">
                                    <?php echo htmlspecialchars($o['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <!-- Nút In Hóa Đơn -->
                                <a href="invoice.php?order_id=<?php echo $o['id']; ?>" target="_blank" class="btn btn-sm btn-light border p-2 text-primary shadow-sm me-2" title="In Hóa Đơn">
                                    <i class="bi bi-printer"></i>
                                </a>
                                
                                <!-- Form Cập nhật trạng thái bằng Dropdown -->
                                <form action="orders.php" method="POST" class="d-inline-flex gap-2 align-items-center">
                                    <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                                    <select name="status" class="form-select form-select-sm border-secondary-subtle rounded-3" style="width: auto;" onchange="this.form.submit()">
                                        <option value="Chờ duyệt" <?php if($o['status'] == 'Chờ duyệt') echo 'selected'; ?>>Chờ duyệt</option>
                                        <option value="Đã duyệt" <?php if($o['status'] == 'Đã duyệt') echo 'selected'; ?>>Đã duyệt</option>
                                        <option value="Đang giao" <?php if($o['status'] == 'Đang giao') echo 'selected'; ?>>Đang giao</option>
                                        <option value="Hoàn thành" <?php if($o['status'] == 'Hoàn thành' || $o['status'] == 'Completed') echo 'selected'; ?>>Hoàn thành</option>
                                        <option value="Đã hủy" <?php if($o['status'] == 'Đã hủy') echo 'selected'; ?>>Đã hủy</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($orders) === 0): ?>
                        <tr><td colspan="7" class="text-center py-4 text-secondary">Không tìm thấy đơn hàng nào.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination UI -->
            <?php if (isset($totalPages) && $totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-end mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $queryString; ?>">Trước</a>
                    </li>
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1' . $queryString . '">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $queryString; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; 
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . $queryString . '">' . $totalPages . '</a></li>';
                    }
                    ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $queryString; ?>">Sau</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>

<?php include 'includes/admin_footer.php'; ?>
