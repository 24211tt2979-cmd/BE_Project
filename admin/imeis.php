<?php
/**
 * NHK Mobile - Admin IMEI Management
 */
require_once 'admin_auth.php';
require_once '../includes/db.php';

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if (!$productId) {
    header("Location: products.php");
    exit;
}

// Lấy thông tin sản phẩm
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: products.php?error=product_not_found");
    exit;
}

// 1. XỬ LÝ LƯU IMEI MỚI (BULK IMPORT)
if (isset($_POST['import_imeis'])) {
    $imeiInput = trim($_POST['imei_list'] ?? '');
    if (!empty($imeiInput)) {
        // Tách danh sách theo dòng
        $lines = explode("\n", $imeiInput);
        $addedCount = 0;
        $dupCount = 0;
        
        $pdo->beginTransaction();
        try {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM imeis WHERE imei = ?");
            $stmtInsert = $pdo->prepare("INSERT INTO imeis (product_id, imei, status) VALUES (?, ?, 'Available')");
            
            foreach ($lines as $line) {
                $imei = trim($line);
                if (empty($imei)) continue;
                
                // Chỉ nhận IMEI độ dài từ 14 đến 18 ký tự
                if (strlen($imei) < 14 || strlen($imei) > 18) {
                    continue;
                }
                
                // Kiểm tra trùng lặp
                $stmtCheck->execute([$imei]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $dupCount++;
                    continue;
                }
                
                // Chèn mới
                $stmtInsert->execute([$productId, $imei]);
                $addedCount++;
            }
            
            // Cập nhật tồn kho tự động
            $stmtUpdateStock = $pdo->prepare("
                UPDATE products 
                SET stock = (SELECT COUNT(*) FROM imeis WHERE product_id = ? AND status = 'Available') 
                WHERE id = ?
            ");
            $stmtUpdateStock->execute([$productId, $productId]);
            
            $pdo->commit();
            log_admin_action($pdo, 'IMPORT_IMEIS', "Nhập thành công $addedCount IMEI cho sản phẩm ID $productId ($dupCount trùng)");
            header("Location: imeis.php?product_id=$productId&msg=imported&added=$addedCount&dup=$dupCount");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: imeis.php?product_id=$productId&error=db_error");
            exit;
        }
    } else {
        header("Location: imeis.php?product_id=$productId&error=empty_list");
        exit;
    }
}

// 2. XỬ LÝ XÓA IMEI (CHỈ XÓA KHI CHƯA BÁN)
if (isset($_GET['delete_imei'])) {
    $imeiId = (int)$_GET['delete_imei'];
    
    // Kiểm tra trạng thái IMEI
    $stmtCheck = $pdo->prepare("SELECT * FROM imeis WHERE id = ? AND product_id = ?");
    $stmtCheck->execute([$imeiId, $productId]);
    $imeiRecord = $stmtCheck->fetch();
    
    if ($imeiRecord) {
        if ($imeiRecord['status'] === 'Available') {
            $pdo->beginTransaction();
            try {
                // Xóa IMEI
                $stmtDel = $pdo->prepare("DELETE FROM imeis WHERE id = ?");
                $stmtDel->execute([$imeiId]);
                
                // Cập nhật tồn kho
                $stmtUpdateStock = $pdo->prepare("
                    UPDATE products 
                    SET stock = (SELECT COUNT(*) FROM imeis WHERE product_id = ? AND status = 'Available') 
                    WHERE id = ?
                ");
                $stmtUpdateStock->execute([$productId, $productId]);
                
                $pdo->commit();
                log_admin_action($pdo, 'DELETE_IMEI', "Xóa mã IMEI '{$imeiRecord['imei']}' của sản phẩm ID $productId");
                header("Location: imeis.php?product_id=$productId&msg=deleted");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                header("Location: imeis.php?product_id=$productId&error=db_error");
                exit;
            }
        } else {
            header("Location: imeis.php?product_id=$productId&error=imei_sold");
            exit;
        }
    } else {
        header("Location: imeis.php?product_id=$productId&error=not_found");
        exit;
    }
}

// 3. LẤY DANH SÁCH IMEI ĐỂ HIỂN THỊ
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Đếm tổng số IMEI
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM imeis WHERE product_id = ?");
$stmtCount->execute([$productId]);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Lấy danh sách phân trang
$stmtList = $pdo->prepare("
    SELECT * FROM imeis 
    WHERE product_id = ? 
    ORDER BY status ASC, created_at DESC 
    LIMIT ? OFFSET ?
");
$stmtList->bindValue(1, $productId, PDO::PARAM_INT);
$stmtList->bindValue(2, $limit, PDO::PARAM_INT);
$stmtList->bindValue(3, $offset, PDO::PARAM_INT);
$stmtList->execute();
$imeis = $stmtList->fetchAll();

$pageTitle = "Quản lý IMEI - " . $product['name'];
$basePath = "../";
include 'includes/admin_header.php';
?>
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                 <nav aria-label="breadcrumb">
                     <ol class="breadcrumb mb-1">
                         <li class="breadcrumb-item"><a href="products.php" class="text-decoration-none">Kho sản phẩm</a></li>
                         <li class="breadcrumb-item active" aria-current="page">Quản lý IMEI</li>
                     </ol>
                 </nav>
                 <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($product['name']); ?></h2>
                 <p class="text-secondary small mb-0">Quản lý mã định danh máy (IMEI) và trạng thái hàng trong kho.</p>
            </div>
            <div>
                 <span class="badge bg-primary px-3 py-2 rounded-pill fs-6 shadow-sm">
                     Tồn kho khả dụng: <?php echo $product['stock']; ?> máy
                 </span>
            </div>
        </header>

        <div class="row g-4">
            <!-- CỘT TRÁI: NHẬP IMEI HÀNG LOẠT -->
            <div class="col-lg-4">
                <div class="content-card shadow-sm border-0 rounded-4 p-4 bg-white h-100">
                    <h5 class="fw-bold mb-3"><i class="bi bi-file-earmark-plus me-2 text-primary"></i>Nhập mã IMEI mới</h5>
                    <form action="imeis.php?product_id=<?php echo $productId; ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label small text-secondary">Nhập danh sách mã IMEI (Mỗi mã nằm trên 1 dòng)</label>
                            <textarea name="imei_list" class="form-control bg-light border-0 rounded-3" rows="12" placeholder="Ví dụ:&#10;358976123456789&#10;358976123456790&#10;358976123456791" required style="font-family: monospace; font-size: 14px;"></textarea>
                        </div>
                        <button type="submit" name="import_imeis" class="btn btn-primary w-100 rounded-pill py-2 shadow-sm fw-bold">
                            <i class="bi bi-download me-2"></i> Xác nhận nạp kho
                        </button>
                    </form>
                </div>
            </div>

            <!-- CỘT PHẢI: BẢNG DANH SÁCH IMEI HIỆN TẠI -->
            <div class="col-lg-8">
                <div class="content-card shadow-sm border-0 rounded-4 p-4 bg-white h-100">
                    <h5 class="fw-bold mb-4"><i class="bi bi-table me-2 text-primary"></i>Danh sách mã định danh trong hệ thống</h5>

                    <!-- Thống báo trạng thái -->
                    <?php if (isset($_GET['msg'])): ?>
                        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?php 
                                if ($_GET['msg'] == 'imported') {
                                    echo "Đã nạp thành công <strong>" . (int)($_GET['added'] ?? 0) . "</strong> mã IMEI vào kho. Trùng lặp bỏ qua: <strong>" . (int)($_GET['dup'] ?? 0) . "</strong> mã.";
                                } elseif ($_GET['msg'] == 'deleted') {
                                    echo "Đã xóa mã định danh khỏi kho thành công!";
                                } else {
                                    echo "Thao tác thành công!";
                                }
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php 
                                if ($_GET['error'] == 'empty_list') echo 'Vui lòng nhập ít nhất một mã IMEI hợp lệ!';
                                elseif ($_GET['error'] == 'imei_sold') echo 'Không thể xóa! Máy sở hữu IMEI này đã được bán cho khách hàng và đang kích hoạt bảo hành.';
                                elseif ($_GET['error'] == 'not_found') echo 'Mã định danh yêu cầu không tồn tại trong hệ thống!';
                                elseif ($_GET['error'] == 'db_error') echo 'Lỗi kết nối cơ sở dữ liệu! Vui lòng thử lại sau.';
                                else echo 'Có lỗi xảy ra!';
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr class="small text-uppercase text-secondary">
                                    <th>Mã IMEI</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày nạp kho</th>
                                    <th class="text-end">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($imeis as $item): ?>
                                <tr>
                                    <td class="fw-bold text-dark" style="font-family: monospace; font-size: 15px;"><?php echo htmlspecialchars($item['imei']); ?></td>
                                    <td>
                                        <?php if ($item['status'] === 'Available'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">Khả dụng (Trong kho)</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1">Đã bán</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-secondary small"><?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?></td>
                                    <td class="text-end">
                                        <?php if ($item['status'] === 'Available'): ?>
                                            <a href="imeis.php?product_id=<?php echo $productId; ?>&delete_imei=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-danger p-2 rounded-3" onclick="return confirm('Bạn có chắc chắn muốn xóa mã định danh này khỏi kho?')" title="Xóa IMEI">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-light border p-2 rounded-3 text-muted" disabled title="Không thể xóa máy đã bán">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (count($imeis) === 0): ?>
                                <tr><td colspan="4" class="text-center py-4 text-secondary">Chưa có mã định danh IMEI nào cho dòng sản phẩm này.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Phân trang -->
                    <?php if (isset($totalPages) && $totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-end mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?product_id=<?php echo $productId; ?>&page=<?php echo $page - 1; ?>">Trước</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?product_id=<?php echo $productId; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?product_id=<?php echo $productId; ?>&page=<?php echo $page + 1; ?>">Sau</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php include 'includes/admin_footer.php'; ?>
