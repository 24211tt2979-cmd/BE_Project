<?php
// Bắt đầu phiên làm việc
require_once 'admin_auth.php';
require_once '../includes/db.php';

$pageTitle = "Quản lý Tin tức | Admin";
$basePath = "../";

/**
 * 1. XỬ LÝ LOGIC CRUD (THÊM / SỬA / XÓA)
 */

if (isset($_POST['save_news'])) {
    $id = $_POST['id'] ?? null;
    $title = $_POST['title'];
    $category = $_POST['category'];
    $excerpt = $_POST['excerpt'];
    $content = $_POST['content'];
    $tags = $_POST['tags'] ?? '';

    // Xử lý upload ảnh
    $uploadDir = '../assets/images/';
    $image = $_POST['current_image'] ?: 'placeholder.png'; // Giữ ảnh cũ mặc định
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed)) {
            $newName = 'news_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newName)) {
                $image = $newName;
            }
        }
    }

    if ($id) {
        $sql = "UPDATE news SET title = ?, category = ?, excerpt = ?, content = ?, image = ?, tags = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $category, $excerpt, $content, $image, $tags, $id]);
        log_admin_action($pdo, 'UPDATE_NEWS', "Cập nhật tin tức ID $id ($title)");
    } else {
        $sql = "INSERT INTO news (title, category, excerpt, content, image, tags) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $category, $excerpt, $content, $image, $tags]);
        $new_news_id = $pdo->lastInsertId();
        log_admin_action($pdo, 'ADD_NEWS', "Thêm bài tin tức mới ID $new_news_id ($title)");
    }
    header("Location: news.php?msg=success");
    exit;
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
    $stmt->execute([$id]);
    log_admin_action($pdo, 'DELETE_NEWS', "Xóa tin tức ID $id");
    header("Location: news.php?msg=deleted");
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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = "";
$params = [];
if ($search !== '') {
    $whereClause = " WHERE title LIKE ?";
    $params[] = "%$search%";
}

// Đếm tổng số bản ghi
$sqlCount = "SELECT COUNT(*) FROM news" . $whereClause;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT * FROM news" . $whereClause . " ORDER BY created_at DESC, id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$newsList = $stmt->fetchAll();

// Xử lý bật Modal Sửa tự động nếu có biến GET edit
$editData = null;
if (isset($_GET['edit'])) {
    $stmtEdit = $pdo->prepare("SELECT * FROM news WHERE id = ?");
    $stmtEdit->execute([$_GET['edit']]);
    $editData = $stmtEdit->fetch();
}

$pageTitle = "Quản lý Tin tức | Admin NHK Mobile";
$basePath = "../";
include 'includes/admin_header.php';
?>
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                 <h2 class="fw-bold mb-1">Quản lý Tin tức</h2>
                 <p class="text-secondary small mb-0">Viết và cập nhật tin công nghệ hàng ngày.</p>
            </div>
            <button class="btn btn-primary shadow-sm px-4 py-2 rounded-3" data-bs-toggle="modal" data-bs-target="#newsModal">
                <i class="bi bi-pencil-square me-2"></i> Viết bài mới
            </button>
        </header>

        <div class="d-flex mb-4">
            <form action="" method="GET" class="d-flex w-100" style="max-width: 400px;">
                <input type="text" name="search" class="form-control me-2 rounded-pill" placeholder="Tìm kiếm theo tiêu đề bài viết..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                <button type="submit" class="btn btn-primary rounded-pill px-3 shadow-sm"><i class="bi bi-search"></i></button>
                <?php if (!empty($search)): ?>
                    <a href="news.php" class="btn btn-outline-secondary rounded-pill px-3 ms-2 shadow-sm d-flex align-items-center">Xóa</a>
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

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr class="small text-uppercase text-secondary">
                            <th>Bài viết</th>
                            <th>Danh mục</th>
                            <th>Ngày đăng</th>
                            <th class="text-end">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($newsList as $n): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo !empty($n['image']) && $n['image'] !== 'placeholder.png' ? '../assets/images/' . $n['image'] : 'https://placehold.co/600x400/f5f5f7/1d1d1f?text=News'; ?>" class="rounded-3 me-3" style="width: 60px; height: 60px; object-fit: cover;" onerror="this.src='https://placehold.co/60'">
                                    <div>
                                        <div class="fw-bold mb-1 text-dark" style="max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($n['title'] ?? ''); ?></div>
                                        <div class="small text-secondary" style="max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($n['excerpt'] ?? ''); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border fw-normal"><?php echo htmlspecialchars($n['category'] ?? ''); ?></span></td>
                            <td class="small text-secondary"><?php echo date('d/m/Y H:i', strtotime($n['created_at'])); ?></td>
                            <td class="text-end">
                                <a href="news.php?edit=<?php echo $n['id']; ?>" class="btn btn-sm btn-light border p-2"><i class="bi bi-pencil text-primary"></i></a>
                                <a href="news.php?delete=<?php echo $n['id']; ?>" class="btn btn-sm btn-light border p-2 text-danger ms-1" onclick="return confirm('Xóa bài viết này vĩnh viễn?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($newsList) === 0): ?>
                        <tr><td colspan="4" class="text-center py-4 text-secondary">Chưa có bài viết nào.</td></tr>
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

    <!-- MODAL THÊM / SỬA BÀI VIẾT -->
    <div class="modal fade <?php echo $editData ? 'show' : ''; ?>" id="newsModal" tabindex="-1" <?php echo $editData ? 'style="display: block; background: rgba(0,0,0,0.5)"' : ''; ?>>
        <div class="modal-dialog modal-lg border-0">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <form action="news.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                        <h5 class="fw-bold mb-0"><?php echo $editData ? 'Sửa bài viết' : 'Viết bài công nghệ mới'; ?></h5>
                        <a href="news.php" class="btn-close"></a>
                    </div>
                    <div class="modal-body px-4 py-4">
                        <input type="hidden" name="id" value="<?php echo $editData['id'] ?? ''; ?>">
                        <input type="hidden" name="current_image" value="<?php echo $editData['image'] ?? 'placeholder.png'; ?>">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label small fw-bold">Tiêu đề bài viết *</label>
                                <input type="text" name="title" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editData['title'] ?? ''); ?>" required placeholder="Nhập tiêu đề hấp dẫn...">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small fw-bold">Danh mục *</label>
                                <select name="category" class="form-select bg-light border-0" required>
                                    <option value="Technology" <?php echo (isset($editData['category']) && $editData['category'] == 'Technology') ? 'selected' : ''; ?>>Công nghệ chung</option>
                                    <option value="Apple" <?php echo (isset($editData['category']) && $editData['category'] == 'Apple') ? 'selected' : ''; ?>>Hệ sinh thái Apple</option>
                                    <option value="Samsung" <?php echo (isset($editData['category']) && $editData['category'] == 'Samsung') ? 'selected' : ''; ?>>Hệ sinh thái Samsung</option>
                                    <option value="Tips" <?php echo (isset($editData['category']) && $editData['category'] == 'Tips') ? 'selected' : ''; ?>>Mẹo & Thủ thuật</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Từ khóa (Tags) tùy chọn</label>
                            <input type="text" name="tags" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editData['tags'] ?? ''); ?>" placeholder="Công nghệ 2026, AI, Đánh giá...">
                            <div class="form-text small text-secondary">Phân cách các thẻ tag bằng dấu phẩy ( , )</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Đoạn trích tóm tắt (Excerpt) *</label>
                            <textarea name="excerpt" class="form-control bg-light border-0" rows="2" required placeholder="Tóm tắt nội dung chính trong 1-2 câu..."><?php echo htmlspecialchars($editData['excerpt'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Ảnh bài viết</label>
                            <?php if (!empty($editData['image']) && $editData['image'] !== 'placeholder.png'): ?>
                            <div class="mb-2 d-flex align-items-center gap-3">
                                <img src="../assets/images/<?php echo $editData['image']; ?>" style="width:80px;height:60px;object-fit:cover;" class="rounded-3 border" onerror="this.src='https://placehold.co/80x60'">
                                <span class="small text-secondary">Ảnh hiện tại. Chọn file mới để thay thế.</span>
                            </div>
                            <?php endif; ?>
                            <input type="file" name="image" class="form-control bg-light border-0" accept="image/png, image/jpeg, image/webp, image/gif">
                            <div class="form-text">Định dạng: JPG, PNG, WEBP. Để trống nếu không muốn thay ảnh.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nội dung chi tiết *</label>
                            <textarea name="content" class="form-control bg-light border-0" rows="10" required placeholder="Viết nội dung bài báo tại đây... Hỗ trợ Markdown/HTML cơ bản."><?php echo htmlspecialchars($editData['content'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 px-4 pb-4">
                        <a href="news.php" class="btn btn-light px-4 rounded-pill">Hủy bỏ</a>
                        <button type="submit" name="save_news" class="btn btn-primary px-4 rounded-pill shadow-sm">Đăng tải bài viết</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php include 'includes/admin_footer.php'; ?>
