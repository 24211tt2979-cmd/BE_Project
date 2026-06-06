<?php
/**
 * NHK Mobile - Admin: Quản lý Yêu cầu Trả hàng / Hoàn tiền (S5.5)
 */
require_once 'admin_auth.php';
require_once '../includes/db.php';

// Cập nhật trạng thái
if (isset($_POST['update_status'])) {
    $id        = (int)$_POST['id'];
    $newStatus = $_POST['status'];
    $adminNote = trim($_POST['admin_note'] ?? '');

    // Lấy trạng thái hiện tại
    $cur = $pdo->prepare("SELECT status FROM return_requests WHERE id = ?");
    $cur->execute([$id]);
    $currentStatus = $cur->fetchColumn();

    // Thứ tự tuần tự bắt buộc (không kể Từ chối)
    $statusOrder = ['Chờ duyệt' => 0, 'Đã duyệt' => 1, 'Đã trả hàng' => 2, 'Đã hoàn tiền' => 3];
    $validStatuses = ['Chờ duyệt', 'Đã duyệt', 'Đã trả hàng', 'Đã hoàn tiền', 'Từ chối'];

    $transitionError = '';
    if (in_array($newStatus, $validStatuses) && $currentStatus) {
        $isRefuse = ($newStatus === 'Từ chối');
        $alreadyRefused = ($currentStatus === 'Từ chối');
        $alreadyDone = ($currentStatus === 'Đã hoàn tiền');

        if ($alreadyRefused || $alreadyDone) {
            // Trạng thái cuối, không cho cập nhật
            $transitionError = 'Yêu cầu đã ở trạng thái cuối, không thể cập nhật thêm.';
        } elseif (!$isRefuse && isset($statusOrder[$currentStatus], $statusOrder[$newStatus])) {
            $currentIdx = $statusOrder[$currentStatus];
            $newIdx     = $statusOrder[$newStatus];
            // Chỉ cho phép tiến 1 bước hoặc giữ nguyên
            if ($newIdx - $currentIdx > 1) {
                $allowedNext = array_search($currentIdx + 1, array_flip($statusOrder));
                $transitionError = "Không thể chuyển thẳng sang \"$newStatus\". Phải xử lý bước \"$allowedNext\" trước.";
            }
        }
    }

    if ($transitionError) {
        header("Location: return_requests.php?msg=error&err=" . urlencode($transitionError));
        exit;
    }

    if (in_array($newStatus, $validStatuses)) {
        $stmt = $pdo->prepare("UPDATE return_requests SET status = ?, admin_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newStatus, $adminNote ?: null, $id]);
        log_admin_action($pdo, 'UPDATE_RETURN_STATUS', "Cập nhật yêu cầu trả hàng ID $id: $currentStatus → $newStatus");
    }
    header("Location: return_requests.php?msg=updated");
    exit;
}

// Filter
$statusFilter = trim($_GET['status_filter'] ?? '');
$search       = trim($_GET['search'] ?? '');

// Cấu hình phân trang
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$whereClause = " WHERE 1=1";
$params = [];

if ($statusFilter !== '') { 
    $whereClause .= " AND rr.status = ?"; 
    $params[] = $statusFilter; 
}
if ($search !== '') { 
    $whereClause .= " AND (rr.customer_name LIKE ? OR rr.customer_phone LIKE ? OR rr.order_code LIKE ?)"; 
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; 
}

// Đếm tổng số bản ghi
$sqlCount = "SELECT COUNT(*) FROM return_requests rr LEFT JOIN orders o ON rr.order_id = o.id" . $whereClause;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT rr.*, o.total_price FROM return_requests rr LEFT JOIN orders o ON rr.order_id = o.id" . $whereClause . " ORDER BY rr.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Chuỗi query string cho phân trang
$queryString = "";
if ($search) $queryString .= '&search='.urlencode($search);
if ($statusFilter) $queryString .= '&status_filter='.urlencode($statusFilter);

// Đếm chờ duyệt
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM return_requests WHERE status = 'Chờ duyệt'")->fetchColumn();

$pageTitle = "Quản lý Trả hàng | Admin NHK Mobile";
$basePath  = "../";
include 'includes/admin_header.php';
?>

<style>
.status-flow { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
.flow-step { padding:.3rem .8rem; border-radius:50rem; font-size:.72rem; font-weight:700; border:2px solid transparent; }
.flow-step.active { border-color:#333; }
.detail-modal .img-preview { width:100px; height:100px; object-fit:cover; border-radius:.75rem; border:2px solid #eee; cursor:pointer; }
</style>

<header class="d-flex justify-content-between align-items-center mb-5">
    <div>
        <h2 class="fw-bold mb-1">Trả hàng / Hoàn tiền</h2>
        <p class="text-secondary small mb-0">Xem xét và xử lý yêu cầu trả hàng của khách hàng.</p>
    </div>
    <?php if ($pendingCount > 0): ?>
    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold fs-6">
        <i class="bi bi-hourglass-split me-1"></i><?= $pendingCount ?> chờ duyệt
    </span>
    <?php endif; ?>
</header>

<!-- Bộ lọc -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
    <form action="" method="GET" class="row g-2 align-items-center">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control bg-light border-0" placeholder="Tên / SĐT / Mã đơn..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="status_filter" class="form-select bg-light border-0">
                <option value="">Tất cả trạng thái</option>
                <?php foreach (['Chờ duyệt','Đã duyệt','Đã trả hàng','Đã hoàn tiền','Từ chối'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary px-3 shadow-sm w-100"><i class="bi bi-funnel"></i> Lọc</button>
            <?php if ($search || $statusFilter): ?>
            <a href="return_requests.php" class="btn btn-outline-secondary px-3">Xóa</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Thông báo -->
<?php if (isset($_GET['msg'])): ?>
<?php if ($_GET['msg'] === 'updated'): ?>
<div class="alert alert-success alert-dismissible fade show border-0 rounded-3 mb-4">
    <i class="bi bi-check-circle-fill me-2"></i>Đã cập nhật trạng thái thành công!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($_GET['msg'] === 'error'): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 mb-4">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Không thể cập nhật:</strong> <?= htmlspecialchars(urldecode($_GET['err'] ?? 'Lỗi không xác định')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Bảng dữ liệu -->
<div class="content-card shadow-sm border-0 rounded-4 p-4 bg-white">
<?php if (empty($requests)): ?>
<div class="text-center py-5">
    <i class="bi bi-arrow-return-left display-3 text-muted opacity-50"></i>
    <h5 class="fw-bold mt-3">Chưa có yêu cầu nào</h5>
</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-hover align-middle">
    <thead>
        <tr class="small text-uppercase text-secondary">
            <th>#</th>
            <th>Khách hàng</th>
            <th>Đơn hàng</th>
            <th>Lý do</th>
            <th>Ngày gửi</th>
            <th>Trạng thái</th>
            <th class="text-end">Xử lý</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($requests as $rr):
        $statusClass = match($rr['status']) {
            'Chờ duyệt'    => 'bg-warning text-dark',
            'Đã duyệt'     => 'bg-info text-white',
            'Đã trả hàng'  => 'bg-primary text-white',
            'Đã hoàn tiền' => 'bg-success text-white',
            'Từ chối'      => 'bg-danger text-white',
            default        => 'bg-secondary text-white',
        };

        // Lấy sản phẩm trong đơn
        $si = $pdo->prepare("SELECT oi.product_name, oi.quantity, oi.price, p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $si->execute([$rr['order_id']]);
        $orderItems = $si->fetchAll();

        $images = $rr['images'] ? (json_decode($rr['images'], true) ?? []) : [];
    ?>
    <tr>
        <td class="text-secondary fw-bold small">#<?= $rr['id'] ?></td>
        <td>
            <div class="fw-bold"><?= htmlspecialchars($rr['customer_name']) ?></div>
            <div class="small text-secondary"><i class="bi bi-phone"></i> <?= htmlspecialchars($rr['customer_phone'] ?? '') ?></div>
        </td>
        <td>
            <div class="fw-bold text-primary"><?= htmlspecialchars($rr['order_code']) ?></div>
            <div class="small text-secondary"><?= number_format($rr['total_price'] ?? 0, 0, ',', '.') ?>đ</div>
            <!-- Sản phẩm trong đơn -->
            <div class="mt-1">
                <?php foreach ($orderItems as $it): ?>
                <div class="d-flex align-items-center gap-2 bg-light rounded px-2 py-1 mb-1 border" style="font-size:.72rem;">
                    <img src="../assets/images/<?= $it['image'] ?>" style="width:22px;height:22px;object-fit:contain;" onerror="this.src='https://placehold.co/22'">
                    <span class="fw-bold"><?= htmlspecialchars($it['product_name']) ?></span>
                    <span class="text-secondary">x<?= $it['quantity'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </td>
        <td style="max-width:200px;">
            <?php if ($rr['reason_type']): ?>
            <span class="badge bg-light text-dark border mb-1"><?= htmlspecialchars($rr['reason_type']) ?></span><br>
            <?php endif; ?>
            <small class="text-muted"><?= htmlspecialchars(mb_substr($rr['reason'], 0, 80)) ?><?= mb_strlen($rr['reason']) > 80 ? '...' : '' ?></small>
            <?php if (!empty($images)): ?>
            <div class="d-flex flex-wrap gap-1 mt-2">
                <?php foreach ($images as $img): ?>
                <img src="../<?= htmlspecialchars($img) ?>" class="detail-modal img-preview" style="width:44px;height:44px;object-fit:cover;border-radius:.5rem;" onclick="showImg('../<?= htmlspecialchars($img) ?>')" onerror="this.style.display='none'">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </td>
        <td class="small text-secondary">
            <?= date('d/m/Y', strtotime($rr['created_at'])) ?>
            <br><span style="font-size:.7rem;"><?= date('H:i', strtotime($rr['created_at'])) ?></span>
        </td>
        <td>
            <span class="badge <?= $statusClass ?> rounded-pill px-3 py-2 small"><?= $rr['status'] ?></span>
        </td>
        <td class="text-end">
            <button class="btn btn-sm btn-primary rounded-pill px-3" onclick="openModal(<?= $rr['id'] ?>, '<?= addslashes($rr['status']) ?>', '<?= addslashes(htmlspecialchars($rr['admin_note'] ?? '')) ?>')">
                <i class="bi bi-pencil-square me-1"></i>Xử lý
            </button>
        </td>
    </tr>
    <?php endforeach; ?>
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

<?php endif; ?>
</div>

<!-- Modal cập nhật trạng thái -->
<div class="modal fade" id="updateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Cập nhật yêu cầu trả hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="return_requests.php">
                <div class="modal-body pt-3">
                    <input type="hidden" name="id" id="modal_id">
                    <input type="hidden" name="update_status" value="1">

                    <!-- Luồng trạng thái trực quan -->
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase text-secondary">Luồng xử lý</label>
                        <div class="status-flow">
                            <span class="flow-step bg-warning text-dark">Chờ duyệt</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="flow-step bg-info text-white">Đã duyệt</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="flow-step bg-primary text-white">Đã trả hàng</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="flow-step bg-success text-white">Đã hoàn tiền</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Cập nhật trạng thái</label>
                        <select name="status" id="modal_status" class="form-select rounded-3">
                            <option value="Chờ duyệt">⏳ Chờ duyệt</option>
                            <option value="Đã duyệt">✅ Đã duyệt – Yêu cầu khách gửi hàng về</option>
                            <option value="Đã trả hàng">📦 Đã trả hàng – Shop nhận được hàng</option>
                            <option value="Đã hoàn tiền">💰 Đã hoàn tiền – Đã chuyển tiền cho khách</option>
                            <option value="Từ chối">❌ Từ chối yêu cầu</option>
                        </select>
                        <div id="status_terminal_warn" class="alert alert-warning border-0 rounded-3 mt-2 py-2 px-3 small" style="display:none;">
                            <i class="bi bi-lock-fill me-1"></i>Yêu cầu này đã ở trạng thái cuối, không thể thay đổi thêm.
                        </div>
                        <div class="text-muted small mt-1">
                            <i class="bi bi-info-circle me-1"></i>Chỉ có thể chuyển sang bước kế tiếp trong luồng xử lý.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ghi chú phản hồi cho khách <small class="text-muted fw-normal">(không bắt buộc)</small></label>
                        <textarea name="admin_note" id="modal_note" class="form-control rounded-3" rows="3" placeholder="VD: Vui lòng gửi hàng về địa chỉ: 123 ABC, Q.1..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-check-lg me-1"></i>Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal xem ảnh lớn -->
<div class="modal fade" id="imgModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 text-center">
                <img id="bigImg" src="" style="max-width:100%;max-height:80vh;border-radius:1rem;">
            </div>
        </div>
    </div>
</div>

<script>
// Thứ tự tuần tự các trạng thái
const STATUS_ORDER = ['Chờ duyệt', 'Đã duyệt', 'Đã trả hàng', 'Đã hoàn tiền'];

function openModal(id, status, note) {
    document.getElementById('modal_id').value   = id;
    document.getElementById('modal_note').value = note;
    updateStatusOptions(status);
    new bootstrap.Modal(document.getElementById('updateModal')).show();
}

function updateStatusOptions(currentStatus) {
    const select = document.getElementById('modal_status');
    const currentIdx = STATUS_ORDER.indexOf(currentStatus);
    const isTerminal = (currentStatus === 'Từ chối' || currentStatus === 'Đã hoàn tiền');

    Array.from(select.options).forEach(opt => {
        const optIdx = STATUS_ORDER.indexOf(opt.value);

        if (isTerminal) {
            // Trạng thái cuối: disable tất cả, chỉ show trạng thái hiện tại
            opt.disabled = (opt.value !== currentStatus);
            opt.style.color = opt.disabled ? '#aaa' : '';
        } else if (opt.value === 'Từ chối') {
            // Từ chối luôn cho phép nếu chưa ở trạng thái cuối
            opt.disabled = false;
            opt.style.color = '';
        } else if (optIdx === -1) {
            opt.disabled = true;
        } else {
            // Chỉ cho phép: trạng thái hiện tại hoặc bước tiếp theo (currentIdx + 1)
            const allowed = (optIdx === currentIdx || optIdx === currentIdx + 1);
            opt.disabled = !allowed;
            opt.style.color = !allowed ? '#aaa' : '';
            if (optIdx < currentIdx) {
                opt.text = opt.text.replace(/^\s*[✅📦💰⏳]\s*/, match => match) // giữ emoji
                    .replace(/ \(đã qua\)$/, '') + ' (đã qua)';
            } else if (optIdx > currentIdx + 1) {
                opt.text = opt.text.replace(/ \(chưa tới\)$/, '') + ' (chưa tới)';
            }
        }
    });

    // Set giá trị mặc định: bước tiếp theo hoặc giữ nguyên
    if (!isTerminal && currentIdx !== -1 && currentIdx + 1 < STATUS_ORDER.length) {
        select.value = STATUS_ORDER[currentIdx + 1]; // tự chọn bước kế tiếp
    } else {
        select.value = currentStatus;
    }

    // Cảnh báo nếu terminal
    const warn = document.getElementById('status_terminal_warn');
    if (warn) warn.style.display = isTerminal ? 'block' : 'none';
}

function showImg(src) {
    document.getElementById('bigImg').src = src;
    new bootstrap.Modal(document.getElementById('imgModal')).show();
}
</script>

<?php include 'includes/admin_footer.php'; ?>
