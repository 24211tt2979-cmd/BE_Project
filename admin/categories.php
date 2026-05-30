<?php
require_once 'admin_auth.php';
require_once '../includes/db.php';

$pageTitle = "Quan ly danh muc | Admin NHK Mobile";
$basePath = "../";

if (isset($_POST['save_category'])) {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name !== '') {
        if ($id > 0) {
            $oldStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
            $oldStmt->execute([$id]);
            $oldName = $oldStmt->fetchColumn();

            $stmt = $pdo->prepare("UPDATE categories SET name = ?, sort_order = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $sortOrder, $isActive, $id]);
            if ($oldName && $oldName !== $name) {
                $pdo->prepare("UPDATE products SET category = ? WHERE category = ?")->execute([$name, $oldName]);
            }
            log_admin_action($pdo, 'UPDATE_CATEGORY', "Cap nhat danh muc $name");
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, sort_order, is_active) VALUES (?, ?, ?)");
            $stmt->execute([$name, $sortOrder, $isActive]);
            log_admin_action($pdo, 'ADD_CATEGORY', "Them danh muc $name");
        }
    }

    header("Location: categories.php?msg=saved");
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn();

    $productCount = 0;
    if ($name) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category = ?");
        $countStmt->execute([$name]);
        $productCount = (int)$countStmt->fetchColumn();
    }

    if ($productCount > 0) {
        header("Location: categories.php?error=in_use");
        exit;
    }

    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    log_admin_action($pdo, 'DELETE_CATEGORY', "Xoa danh muc $name");
    header("Location: categories.php?msg=deleted");
    exit;
}

$editCategory = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editCategory = $stmt->fetch();
}

$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category = c.name
    GROUP BY c.id, c.name, c.sort_order, c.is_active, c.created_at
    ORDER BY c.sort_order ASC, c.name ASC
")->fetchAll();

include 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="fw-bold h2 mb-1">Quan ly danh muc</h1>
        <p class="text-secondary mb-0">Them, sua, an hien va sap xep hang san pham.</p>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success border-0 rounded-3 shadow-sm">Da cap nhat danh muc.</div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'in_use'): ?>
    <div class="alert alert-danger border-0 rounded-3 shadow-sm">Khong the xoa danh muc dang co san pham.</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><?php echo $editCategory ? 'Sua danh muc' : 'Them danh muc'; ?></h5>
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editCategory['id'] ?? ''); ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ten danh muc</label>
                        <input name="name" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editCategory['name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Thu tu hien thi</label>
                        <input type="number" name="sort_order" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($editCategory['sort_order'] ?? 0); ?>">
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?php echo (!$editCategory || $editCategory['is_active']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Hien thi tren bo loc</label>
                    </div>
                    <button name="save_category" class="btn btn-primary rounded-pill px-4 fw-bold">Luu danh muc</button>
                    <?php if ($editCategory): ?><a href="categories.php" class="btn btn-light rounded-pill px-4">Huy</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Danh muc</th><th>San pham</th><th>Thu tu</th><th>Trang thai</th><th class="text-end">Hanh dong</th></tr></thead>
                        <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($category['name']); ?></td>
                                <td><?php echo (int)$category['product_count']; ?></td>
                                <td><?php echo (int)$category['sort_order']; ?></td>
                                <td><?php echo $category['is_active'] ? '<span class="badge bg-success">Dang hien</span>' : '<span class="badge bg-secondary">An</span>'; ?></td>
                                <td class="text-end">
                                    <a href="categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a>
                                    <a href="categories.php?delete=<?php echo $category['id']; ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Xoa danh muc nay?')"><i class="bi bi-trash"></i></a>
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
