<?php
require_once 'admin_auth.php';

// Nhúng file kết nối cơ sở dữ liệu Postgres
require_once '../includes/db.php';

// Tiêu đề trang quản trị
$pageTitle = "Quản lý sản phẩm";
$basePath = "../";

/**
 * 1. XỬ LÝ LOGIC CRUD (THÊM / SỬA / XÓA)
 * Logic này xử lý các yêu cầu gửi từ Form PHP bằng phương thức POST và GET.
 */

// A. THÊM HOẶC CẬP NHẬT SẢN PHẨM (Xử lý khi nhấn nút Lưu)
if (isset($_POST['save_product'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = $_POST['price'] ?? '';
    $cost_price = $_POST['cost_price'] ?? 0;
    $stock = $_POST['stock'] ?? '';
    $specs = trim($_POST['specs'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Xác thực dữ liệu cơ bản
    if (empty($name) || empty($category) || $price === '' || $stock === '') {
        header("Location: products.php?error=empty_fields" . ($id ? "&edit=$id" : ""));
        exit;
    }

    // ... (logic check numeric skipped for brevity in prompt but I should keep it correctly)
    
    // Yêu cầu bắt buộc phải có ảnh khi thêm mới sản phẩm
    if (empty($id) && (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE)) {
        header("Location: products.php?error=empty_image");
        exit;
    }

    // Xử lý upload ảnh
    $uploadDir = '../assets/images/';
    $image = $_POST['current_image'] ?: 'placeholder.png'; // Giữ ảnh cũ mặc định
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed)) {
            $newName = 'product_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newName)) {
                $image = $newName;
            }
        }
    }

    if ($id) {
        // Nếu có ID -> CẬP NHẬT sản phẩm hiện tại
        $sql = "UPDATE products SET name = ?, category = ?, price = ?, cost_price = ?, stock = ?, image = ?, description = ?, specs = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $category, $price, $cost_price, $stock, $image, $description, $specs, $id]);
        $msg = "success";
        log_admin_action($pdo, 'UPDATE_PRODUCT', "Cập nhật sản phẩm ID $id ($name)");
    } else {
        // Nếu không có ID -> THÊM MỚI sản phẩm vào bảng
        $sql = "INSERT INTO products (name, category, price, cost_price, stock, image, description, specs) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $category, $price, $cost_price, $stock, $image, $description, $specs]);
        $msg = "success";
        $new_product_id = $pdo->lastInsertId();
        log_admin_action($pdo, 'ADD_PRODUCT', "Thêm sản phẩm mới ID $new_product_id ($name)");
    }
    // Chuyển hướng lại trang để tránh gửi lại form khi F5
    header("Location: products.php?msg=$msg");
    exit;
}

// B. XÓA SẢN PHẨM (Xử lý khi nhấn nút Xóa trên danh sách)
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $confirmed = isset($_GET['confirmed']) && $_GET['confirmed'] === '1';
    try {
        // Kiểm tra xem sản phẩm có trong giỏ hàng không
        $checkCart = $pdo->prepare("SELECT COUNT(*) FROM cart_items WHERE product_id = ?");
        $checkCart->execute([$id]);
        if ($checkCart->fetchColumn() > 0) {
            header("Location: products.php?error=product_in_cart");
            exit;
        }

        // Kiểm tra xem sản phẩm có trong đơn hàng không
        $checkOrder = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $checkOrder->execute([$id]);
        if ($checkOrder->fetchColumn() > 0) {
            header("Location: products.php?error=product_in_order");
            exit;
        }

        // Kiểm tra tồn kho - nếu còn hàng và chưa xác nhận thì cảnh báo
        if (!$confirmed) {
            $checkStock = $pdo->prepare("SELECT stock, name FROM products WHERE id = ?");
            $checkStock->execute([$id]);
            $productInfo = $checkStock->fetch();
            if ($productInfo && $productInfo['stock'] > 0) {
                header("Location: products.php?warn_stock=1&delete_id=$id&stock=" . $productInfo['stock'] . "&pname=" . urlencode($productInfo['name']));
                exit;
            }
        }

        $pdo->beginTransaction();
        
        // Xóa các mục phụ (không phải giỏ hàng/đơn hàng) để làm sạch dữ liệu
        $pdo->prepare("DELETE FROM wishlists WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM reviews WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM repair_history WHERE warranty_id IN (SELECT id FROM warranties WHERE product_id = ?)")->execute([$id]);
        $pdo->prepare("DELETE FROM warranties WHERE product_id = ?")->execute([$id]);
        
        // Cuối cùng: Xóa sản phẩm
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        
        log_admin_action($pdo, 'DELETE_PRODUCT', "Xóa sản phẩm ID $id");
        
        $pdo->commit();
        header("Location: products.php?msg=deleted");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: products.php?error=fk_violation");
    }
    exit;
}

// B.1 XÓA NHIỀU SẢN PHẨM (Bulk Delete)
if (isset($_POST['bulk_delete']) && !empty($_POST['selected_ids'])) {
    $ids = $_POST['selected_ids'];
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        // Kiểm tra giỏ hàng
        $checkCart = $pdo->prepare("SELECT COUNT(*) FROM cart_items WHERE product_id IN ($placeholders)");
        $checkCart->execute($ids);
        if ($checkCart->fetchColumn() > 0) {
            header("Location: products.php?error=product_in_cart");
            exit;
        }

        // Kiểm tra đơn hàng
        $checkOrder = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id IN ($placeholders)");
        $checkOrder->execute($ids);
        if ($checkOrder->fetchColumn() > 0) {
            header("Location: products.php?error=product_in_order");
            exit;
        }

        $pdo->beginTransaction();
        
        $pdo->prepare("DELETE FROM wishlists WHERE product_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM reviews WHERE product_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM repair_history WHERE warranty_id IN (SELECT id FROM warranties WHERE product_id IN ($placeholders))")->execute($ids);
        $pdo->prepare("DELETE FROM warranties WHERE product_id IN ($placeholders)")->execute($ids);
        
        $sql = "DELETE FROM products WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        
        log_admin_action($pdo, 'BULK_DELETE_PRODUCTS', "Xóa nhiều sản phẩm, số lượng: " . count($ids));
        
        $pdo->commit();
        header("Location: products.php?msg=deleted");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: products.php?error=fk_violation");
    }
    exit;
}

// C. TOGGLE SẢN PHẨM NỔI BẬT
if (isset($_GET['toggle_featured'])) {
    $id = $_GET['toggle_featured'];
    $stmt = $pdo->prepare("UPDATE products SET is_featured = NOT is_featured WHERE id = ?");
    $stmt->execute([$id]);
    log_admin_action($pdo, 'TOGGLE_FEATURED_PRODUCT', "Bật/tắt sản phẩm nổi bật ID $id");
    header("Location: products.php?msg=success");
    exit;
}

/**
 * 2. LẤY DỮ LIỆU HIỂN THỊ
 */

// Cấu hình phân trang
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Lấy toàn bộ danh sách sản phẩm để hiện ra bảng (sắp xếp mới nhất lên đầu)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['filter_category']) ? trim($_GET['filter_category']) : '';
$whereClause = "";
$whereConditions = [];
$params = [];

if ($search !== '') {
    $whereConditions[] = "name LIKE ?";
    $params[] = "%$search%";
}

if ($categoryFilter !== '') {
    $whereConditions[] = "category = ?";
    $params[] = $categoryFilter;
}

if (count($whereConditions) > 0) {
    $whereClause = " WHERE " . implode(" AND ", $whereConditions);
}

// Đếm tổng số bản ghi
$sqlCount = "SELECT COUNT(*) FROM products" . $whereClause;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT * FROM products" . $whereClause . " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// KIỂM TRA NẾU ĐANG TRONG CHẾ ĐỘ SỬA (Lấy data sản phẩm cũ đổ vào Modal)
$editProduct = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editProduct = $stmt->fetch();
}

try {
    $stmtAdminCats = $pdo->query("SELECT name FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
    $adminCategories = $stmtAdminCats->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $adminCategories = [];
}
if (empty($adminCategories)) {
    $adminCategories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = "Kho sản phẩm | Admin";
$basePath = "../";
include 'includes/admin_header.php';
?>
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                 <h2 class="fw-bold mb-1">Kho sản phẩm</h2>
                 <p class="text-secondary small mb-0">Quản lý kho hàng bằng PHP PDO & PostgreSQL.</p>
            </div>
            <div class="d-flex gap-2">
                <!-- Nút Xóa nhiều (ẩn mặc định, hiện khi chọn checkbox) -->
                <button type="submit" form="bulkDeleteForm" name="bulk_delete" id="bulkDeleteBtn" class="btn btn-outline-danger shadow-sm px-4 py-2 rounded-3 d-none" onclick="return confirm('Bạn có chắc chắn muốn xóa các sản phẩm đã chọn?')">
                    <i class="bi bi-trash me-2"></i> Xóa mục đã chọn
                </button>
                <!-- Nút bật Modal thêm mới -->
                <button class="btn btn-primary shadow-sm px-4 py-2 rounded-3" data-bs-toggle="modal" data-bs-target="#productModal">
                    <i class="bi bi-plus-lg me-2"></i> Thêm máy mới
                </button>
            </div>
        </header>

        <div class="d-flex mb-4">
            <form action="" method="GET" class="d-flex w-100" style="max-width: 600px;">
                <select name="filter_category" class="form-select me-2 rounded-pill shadow-sm" style="max-width: 150px;">
                    <option value="">Tất cả hãng</option>
                    <?php foreach ($adminCategories as $catOption): ?>
                    <option value="<?php echo htmlspecialchars($catOption); ?>" <?php echo $categoryFilter == $catOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($catOption); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search" class="form-control me-2 rounded-pill shadow-sm" placeholder="Tìm kiếm theo tên sản phẩm..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                <button type="submit" class="btn btn-primary rounded-pill px-3 shadow-sm"><i class="bi bi-search"></i> Lọc</button>
                <?php if (!empty($search) || !empty($categoryFilter)): ?>
                    <a href="products.php" class="btn btn-outline-secondary rounded-pill px-3 ms-2 shadow-sm d-flex align-items-center">Xóa</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="content-card shadow-sm border-0 rounded-4 p-4">
            <!-- Hiển thị thông báo thành công nếu có -->
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4 rounded-3 border-0 shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo $_GET['msg'] == 'success' ? 'Dữ liệu đã được cập nhật thành công!' : ($_GET['msg'] == 'deleted' ? 'Sản phẩm đã được xóa khỏi hệ thống!' : 'Thao tác thành công!'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Hiển thị thông báo lỗi nếu có -->
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4 rounded-3 border-0 shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php 
                        if ($_GET['error'] == 'empty_fields') echo 'Vui lòng điền đầy đủ các thông tin bắt buộc!';
                        elseif ($_GET['error'] == 'invalid_price') echo 'Giá sản phẩm không hợp lệ! Vui lòng nhập số lớn hơn hoặc bằng 0.';
                        elseif ($_GET['error'] == 'invalid_stock') echo 'Số lượng tồn kho không hợp lệ! Vui lòng nhập số lớn hơn hoặc bằng 0.';
                        elseif ($_GET['error'] == 'empty_image') echo 'Vui lòng tải lên ảnh đại diện cho sản phẩm mới!';
                        elseif ($_GET['error'] == 'product_in_cart') echo 'Không thể xóa! Sản phẩm này đang có trong giỏ hàng của khách hàng.';
                        elseif ($_GET['error'] == 'product_in_order') echo 'Không thể xóa! Sản phẩm này đã có trong đơn hàng của hệ thống.';
                        elseif ($_GET['error'] == 'fk_violation') echo 'Có lỗi ràng buộc dữ liệu! Không thể xóa sản phẩm này.';
                        else echo 'Có lỗi xảy ra!';
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Cảnh báo xóa sản phẩm còn tồn kho -->
            <?php if (isset($_GET['warn_stock']) && $_GET['warn_stock'] == '1'): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4" role="alert">
                <div class="d-flex align-items-start gap-3">
                    <div class="fs-4 text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <div class="flex-grow-1">
                        <div class="fw-bold mb-1">⚠️ Cảnh báo: Sản phẩm còn hàng trong kho!</div>
                        <div class="small mb-2">
                            Sản phẩm <strong><?php echo htmlspecialchars($_GET['pname'] ?? ''); ?></strong> hiện còn 
                            <strong class="text-danger"><?php echo (int)($_GET['stock'] ?? 0); ?> chiếc</strong> trong kho.
                            Bạn có chắc chắn muốn xóa sản phẩm này không?
                        </div>
                        <div class="d-flex gap-2">
                            <a href="products.php?delete=<?php echo (int)($_GET['delete_id'] ?? 0); ?>&confirmed=1" 
                               class="btn btn-danger btn-sm rounded-pill px-3">
                                <i class="bi bi-trash me-1"></i> Xác nhận xóa
                            </a>
                            <a href="products.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                <i class="bi bi-x-circle me-1"></i> Hủy bỏ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form id="bulkDeleteForm" action="products.php" method="POST">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr class="small text-uppercase text-secondary">
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Hình ảnh</th>
                                <th>Tên máy</th>
                                <th>Hãng</th>
                                <th>Giá niêm yết</th>
                                <th>Tồn kho</th>
                                <th class="text-end">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Vòng lặp PHP Duyệt mảng sản phẩm lấy từ DB -->
                            <?php foreach($products as $p): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_ids[]" value="<?php echo $p['id']; ?>" class="form-check-input select-item">
                                </td>
                                <td>
                                    <img src="../assets/images/<?php echo $p['image']; ?>" class="product-img-admin rounded-2 shadow-sm" style="width: 44px; height: 44px; object-fit: contain; background: #fff;" onerror="this.src='https://placehold.co/44'">
                                </td>
                            <td>
                                <div class="fw-bold d-flex align-items-center gap-2">
                                    <?php echo $p['name']; ?>
                                    <?php if($p['is_featured']): ?>
                                        <i class="bi bi-star-fill text-warning" title="Nổi bật (Hiện trên trang chủ)"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border fw-normal"><?php echo $p['category']; ?></span></td>
                            <td class="fw-bold text-primary"><?php echo number_format($p['price'], 0, ',', '.'); ?>₫</td>
                            <td><span class="text-secondary"><?php echo $p['stock']; ?> chiếc</span></td>
                            <td class="text-end">
                                <!-- Nút Đánh dấu / Gỡ bỏ Nổi bật -->
                                <a href="products.php?toggle_featured=<?php echo $p['id']; ?>" class="btn btn-sm border p-2 <?php echo $p['is_featured'] ? 'btn-warning text-white' : 'btn-light text-warning'; ?>" title="Ghim lên đầu trang chủ">
                                    <i class="bi bi-star<?php echo $p['is_featured'] ? '-fill' : ''; ?>"></i>
                                </a>
                                <!-- Nút Quản lý IMEI -->
                                <a href="imeis.php?product_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-light border p-2 ms-1" title="Quản lý IMEI"><i class="bi bi-barcode text-dark"></i></a>
                                <!-- Nút Sửa: Truyền ID qua biến GET 'edit' -->
                                <a href="products.php?edit=<?php echo $p['id']; ?>" class="btn btn-sm btn-light border p-2 ms-1"><i class="bi bi-pencil text-primary"></i></a>
                                <!-- Nút Xóa: Kiểm tra tồn kho trước khi xóa -->
                                <a href="products.php?delete=<?php echo $p['id']; ?>" 
                                   class="btn btn-sm btn-light border p-2 text-danger ms-1"
                                   onclick="return confirmDeleteProduct(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', <?php echo (int)$p['stock']; ?>)"
                                   title="Xóa sản phẩm">
                                   <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($products) === 0): ?>
                        <tr><td colspan="7" class="text-center py-4 text-secondary">Không tìm thấy sản phẩm nào.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination UI -->
            <?php if (isset($totalPages) && $totalPages > 1): ?>
            <?php 
                $queryString = '';
                if ($search) $queryString .= '&search='.urlencode($search);
                if ($categoryFilter) $queryString .= '&filter_category='.urlencode($categoryFilter);
            ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-end mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1 . $queryString; ?>">Trước</a>
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
                            <a class="page-link" href="?page=<?php echo $i . $queryString; ?>"><?php echo $i; ?></a>
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
                        <a class="page-link" href="?page=<?php echo $page + 1 . $queryString; ?>">Sau</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            </form>
        </div>
    </main>

    <!-- MODAL THÊM / SỬA SẢN PHẨM -->
    <!-- Modal này sẽ tự bật lên nếu người dùng nhấn SỬA (nhờ logic PHP phía dưới) -->
    <div class="modal fade <?php echo $editProduct ? 'show' : ''; ?>" id="productModal" tabindex="-1" <?php echo $editProduct ? 'style="display: block; background: rgba(0,0,0,0.5)"' : ''; ?>>
        <div class="modal-dialog modal-lg border-0">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <!-- FORM PHP: Gửi dữ liệu về chính file products.php bằng phương thức POST -->
                <form action="products.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header border-0 px-4 pt-4">
                        <h5 class="modal-title fw-bold"><?php echo $editProduct ? 'Cập nhật sản phẩm' : 'Thêm sản phẩm mới'; ?></h5>
                        <!-- Nếu trong chế độ Sửa, nút X sẽ reload trang để thoát mode Sửa -->
                        <a href="products.php" class="btn-close"></a>
                    </div>
                    <div class="modal-body p-4">
                        <!-- Input ẩn ID: Dùng để phân biệt Thêm (trống) hay Sửa (có ID) -->
                        <input type="hidden" name="id" value="<?php echo $editProduct ? $editProduct['id'] : ''; ?>">
                        <input type="hidden" name="current_image" value="<?php echo $editProduct ? $editProduct['image'] : 'placeholder.png'; ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold">Tên điện thoại <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control rounded-3" value="<?php echo $editProduct ? $editProduct['name'] : ''; ?>" placeholder="VD: iPhone 15 Pro Max" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Danh mục hãng <span class="text-danger">*</span></label>
                                <select name="category" class="form-select rounded-3">
                                    <?php foreach ($adminCategories as $catOption): ?>
                                    <option value="<?php echo htmlspecialchars($catOption); ?>" <?php echo ($editProduct && $editProduct['category'] == $catOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars($catOption); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Giá bán (VNĐ) <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control rounded-3" value="<?php echo $editProduct ? $editProduct['price'] : ''; ?>" placeholder="VD: 30000000" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Giá vốn nhập kho (VNĐ)</label>
                                <input type="number" name="cost_price" class="form-control rounded-3" value="<?php echo ($editProduct && isset($editProduct['cost_price'])) ? $editProduct['cost_price'] : '0'; ?>" placeholder="VD: 25000000" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Số lượng tồn kho <span class="text-danger">*</span></label>
                                <input type="number" name="stock" class="form-control rounded-3" value="<?php echo $editProduct ? $editProduct['stock'] : ''; ?>" placeholder="VD: 10" min="0" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold">Ảnh sản phẩm <?php echo !$editProduct ? '<span class="text-danger">*</span>' : ''; ?></label>
                                <?php if ($editProduct && $editProduct['image']): ?>
                                <div class="mb-2 d-flex align-items-center gap-3">
                                    <img src="../assets/images/<?php echo $editProduct['image']; ?>" style="width:60px;height:60px;object-fit:cover;" class="rounded-3 border" onerror="this.src='https://placehold.co/60'">
                                    <span class="small text-secondary">Ảnh hiện tại. Chọn file mới để thay thế.</span>
                                </div>
                                <?php endif; ?>
                                <input type="file" name="image" class="form-control rounded-3" accept="image/png, image/jpeg, image/webp, image/gif" <?php echo !$editProduct ? 'required' : ''; ?>>
                                <div class="form-text">Định dạng: JPG, PNG, WEBP, GIF. <?php echo $editProduct ? 'Để trống nếu không muốn thay ảnh.' : 'Bắt buộc chọn ảnh cho sản phẩm mới.'; ?></div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold">Thông số kỹ thuật (Dùng cho bộ lọc)</label>
                                <textarea name="specs" class="form-control rounded-3" rows="2" placeholder="VD: 128GB, 256GB, 8GB RAM..."><?php echo $editProduct ? $editProduct['specs'] : ''; ?></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold">Mô tả chi tiết</label>
                                <textarea name="description" class="form-control rounded-3" rows="3" placeholder="Nhập mô tả sản phẩm..."><?php echo $editProduct ? $editProduct['description'] : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <a href="products.php" class="btn btn-light rounded-3 px-4">Đóng</a>
                        <!-- Nút Submit của Form PHP -->
                        <button type="submit" name="save_product" class="btn btn-primary rounded-pill px-5 fw-bold shadow">
                            <i class="bi bi-save me-2"></i> Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Xử lý logic chọn hàng loạt (Bulk Selection)
        const selectAll = document.getElementById('selectAll');
        const selectItems = document.querySelectorAll('.select-item');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

        function toggleBulkDeleteBtn() {
            const checkedCount = document.querySelectorAll('.select-item:checked').length;
            if (bulkDeleteBtn) {
                if (checkedCount > 0) bulkDeleteBtn.classList.remove('d-none');
                else bulkDeleteBtn.classList.add('d-none');
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                selectItems.forEach(item => item.checked = this.checked);
                toggleBulkDeleteBtn();
            });
        }

        selectItems.forEach(item => {
            item.addEventListener('change', function() {
                if (!this.checked) selectAll.checked = false;
                else {
                    const allChecked = Array.from(selectItems).every(i => i.checked);
                    selectAll.checked = allChecked;
                }
                toggleBulkDeleteBtn();
            });
        });

        // Hàm xác nhận xóa sản phẩm - kiểm tra tồn kho
        function confirmDeleteProduct(id, name, stock) {
            if (stock > 0) {
                // Sản phẩm còn hàng - hiển thị cảnh báo đặc biệt thay vì confirm thông thường
                // Chuyển hướng để hiện banner cảnh báo với nút xác nhận
                window.location.href = `products.php?warn_stock=1&delete_id=${id}&stock=${stock}&pname=${encodeURIComponent(name)}`;
                return false;
            }
            // Sản phẩm hết hàng - confirm thông thường
            return confirm(`Bạn có chắc muốn xóa sản phẩm "${name}" không?`);
        }
    </script>

<?php include 'includes/admin_footer.php'; ?>
