<?php
require_once 'admin_auth.php';
require_once '../includes/db.php';

$pageTitle = "Cấu hình hệ thống | Admin NHK Mobile";
$basePath = "../";
$uploadDir = '../assets/images/';

if (isset($_POST['save_settings'])) {
    $fields = ['store_name', 'hotline', 'store_email', 'store_address', 'map_embed_url'];
    foreach ($fields as $field) {
        set_system_setting($pdo, $field, trim($_POST[$field] ?? ''));
    }
    log_admin_action($pdo, 'UPDATE_SYSTEM_SETTINGS', 'Cập nhật hotline, địa chỉ, email và bản đồ');
    header("Location: settings.php?msg=settings");
    exit;
}

if (isset($_POST['save_banner'])) {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $linkUrl = trim($_POST['link_url'] ?? 'product.php');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $image = trim($_POST['current_image'] ?? '');

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $image = 'banner_' . time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $image);
        }
    }

    if ($title !== '') {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE homepage_banners SET title = ?, subtitle = ?, image = ?, link_url = ?, sort_order = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$title, $subtitle, $image, $linkUrl, $sortOrder, $isActive, $id]);
                log_admin_action($pdo, 'UPDATE_HOME_BANNER', "Cập nhật banner ID $id");
            } else {
                $stmt = $pdo->prepare("INSERT INTO homepage_banners (title, subtitle, image, link_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $subtitle, $image, $linkUrl, $sortOrder, $isActive]);
                log_admin_action($pdo, 'ADD_HOME_BANNER', "Thêm banner $title");
            }
            header("Location: settings.php?msg=banner");
            exit;
        } catch (PDOException $e) {
            error_log("[Settings Banner] Save error: " . $e->getMessage());
            header("Location: settings.php?error=" . urlencode("Lỗi CSDL: " . $e->getMessage()));
            exit;
        }
    }
}

if (isset($_GET['delete_banner'])) {
    $id = (int)$_GET['delete_banner'];
    $pdo->prepare("DELETE FROM homepage_banners WHERE id = ?")->execute([$id]);
    log_admin_action($pdo, 'DELETE_HOME_BANNER', "Xóa banner ID $id");
    header("Location: settings.php?msg=deleted");
    exit;
}

$settings = get_system_settings($pdo);
$banners = $pdo->query("SELECT * FROM homepage_banners ORDER BY sort_order ASC, id DESC")->fetchAll();
$editBanner = null;
if (isset($_GET['edit_banner'])) {
    $stmt = $pdo->prepare("SELECT * FROM homepage_banners WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_banner']]);
    $editBanner = $stmt->fetch();
}

include 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="fw-bold h2 mb-1">Cấu hình hệ thống</h1>
        <p class="text-secondary mb-0">Quản lý hotline, địa chỉ, bản đồ và banner trang chủ.</p>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success border-0 rounded-3 shadow-sm">Đã lưu thay đổi thành công.</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger border-0 rounded-3 shadow-sm"><?php echo htmlspecialchars($_GET['error']); ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">Thông tin cửa hàng</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tên cửa hàng</label>
                        <input name="store_name" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($settings['store_name'] ?? 'NHK Mobile'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Hotline</label>
                        <input name="hotline" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($settings['hotline'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email gửi thông báo</label>
                        <input type="email" name="store_email" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($settings['store_email'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Địa chỉ</label>
                        <textarea name="store_address" class="form-control bg-light border-0" rows="3"><?php echo htmlspecialchars($settings['store_address'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Google Maps embed URL</label>
                        <input name="map_embed_url" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($settings['map_embed_url'] ?? ''); ?>">
                    </div>
                    <button name="save_settings" class="btn btn-primary rounded-pill px-4 fw-bold">Lưu cấu hình</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><?php echo $editBanner ? 'Sửa banner' : 'Thêm banner trang chủ'; ?></h5>
                <form method="POST" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editBanner['id'] ?? ''); ?>">
                    <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($editBanner['image'] ?? ''); ?>">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Tiêu đề</label>
                        <input name="title" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editBanner['title'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Liên kết</label>
                        <input name="link_url" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editBanner['link_url'] ?? 'product.php'); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Mô tả ngắn</label>
                        <input name="subtitle" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editBanner['subtitle'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Ảnh banner</label>
                        <input type="file" name="image" class="form-control bg-light border-0" accept="image/png,image/jpeg,image/webp,image/gif">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Thứ tự</label>
                        <input type="number" name="sort_order" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editBanner['sort_order'] ?? 0); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <label class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_active" <?php echo (!$editBanner || $editBanner['is_active']) ? 'checked' : ''; ?>>
                            <span class="form-check-label">Hiển thị</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <button name="save_banner" class="btn btn-primary rounded-pill px-4 fw-bold">Lưu banner</button>
                        <?php if ($editBanner): ?><a href="settings.php" class="btn btn-light rounded-pill px-4">Hủy</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">Danh sách banner</h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Banner</th><th>Trạng thái</th><th class="text-end">Hành động</th></tr></thead>
                        <tbody>
                        <?php foreach ($banners as $banner): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="../assets/images/<?php echo htmlspecialchars($banner['image']); ?>" style="width:64px;height:44px;object-fit:contain" class="bg-light rounded border" onerror="this.src='https://placehold.co/64x44'">
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($banner['title']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($banner['link_url']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $banner['is_active'] ? '<span class="badge bg-success">Đang hiện</span>' : '<span class="badge bg-secondary">Ẩn</span>'; ?></td>
                                <td class="text-end">
                                    <a href="settings.php?edit_banner=<?php echo $banner['id']; ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a>
                                    <a href="settings.php?delete_banner=<?php echo $banner['id']; ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Xóa banner này?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
