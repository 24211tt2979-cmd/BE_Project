<?php
/**
 * NHK Mobile - Trả Hàng / Hoàn Tiền (User)
 * S5.4: Form gửi yêu cầu + lịch sử yêu cầu
 */
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

define('RETURN_DAYS_LIMIT', 14);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=return_request.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$stmtUser = $pdo->prepare("SELECT fullname, phone FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$currentUser = $stmtUser->fetch();

// Lấy đơn hàng để hiển thị form
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$order   = null;
$items   = [];
$canReturn = false;
$returnMsg = '';

if ($orderId) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();

    if ($order) {
        // Lấy sản phẩm trong đơn
        $si = $pdo->prepare("SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $si->execute([$orderId]);
        $items = $si->fetchAll();

        // Kiểm tra điều kiện trả
        if (stripos($order['status'], 'hoàn thành') !== false) {
            $completedAt = strtotime($order['updated_at'] ?? $order['created_at']);
            $daysPassed  = floor((time() - $completedAt) / 86400);
            $daysLeft    = RETURN_DAYS_LIMIT - $daysPassed;

            if ($daysPassed <= RETURN_DAYS_LIMIT) {
                // Kiểm tra chưa có yêu cầu đang xử lý
                $sc = $pdo->prepare("SELECT id FROM return_requests WHERE order_id = ? AND status NOT IN ('Từ chối')");
                $sc->execute([$orderId]);
                if ($sc->fetch()) {
                    $returnMsg = 'pending';
                } else {
                    $canReturn = true;
                }
            } else {
                $returnMsg = 'expired';
                $daysLeft  = 0;
            }
        } else {
            $returnMsg = 'not_completed';
        }
    }
}

// Lấy lịch sử yêu cầu của user
$stmtHistory = $pdo->prepare("
    SELECT rr.*, o.total_price 
    FROM return_requests rr 
    LEFT JOIN orders o ON rr.order_id = o.id 
    WHERE rr.user_id = ? 
    ORDER BY rr.created_at DESC
");
$stmtHistory->execute([$userId]);
$history = $stmtHistory->fetchAll();

$pageTitle = "Trả hàng / Hoàn tiền | NHK Mobile";
$basePath  = "";
include 'includes/header.php';
?>

<style>
.return-wrapper { background:#f4f6fb; min-height:100vh; padding-top:90px; padding-bottom:60px; }
.return-card { background:#fff; border-radius:1.5rem; box-shadow:0 4px 30px rgba(0,0,0,0.07); padding:2rem; }
.return-card .section-title { font-size:1.2rem; font-weight:800; color:#1a1a2e; margin-bottom:.25rem; }
.return-card .section-sub { font-size:.85rem; color:#888; margin-bottom:1.5rem; }
.reason-card { border:2px solid #e8eaf0; border-radius:1rem; padding:1rem 1.25rem; cursor:pointer; transition:all .2s; }
.reason-card:hover, .reason-card.selected { border-color:#667eea; background:#f0f3ff; }
.reason-card input[type=radio] { display:none; }
.preview-img { width:80px; height:80px; object-fit:cover; border-radius:.75rem; border:2px solid #e8eaf0; position:relative; }
.preview-wrap { position:relative; display:inline-block; }
.preview-wrap .del-btn { position:absolute; top:-6px; right:-6px; background:#e74c3c; color:#fff; border:none; border-radius:50%; width:20px; height:20px; font-size:11px; cursor:pointer; line-height:1; }
.status-pill { padding:.35rem .9rem; border-radius:50rem; font-size:.75rem; font-weight:700; display:inline-block; }
.pill-waiting { background:#fff3cd; color:#856404; }
.pill-approved { background:#cff4fc; color:#055160; }
.pill-returned { background:#ffe0b2; color:#e65100; }
.pill-refunded { background:#d1e7dd; color:#0a3622; }
.pill-rejected { background:#f8d7da; color:#58151c; }
.upload-zone { border:2px dashed #c0c6d4; border-radius:1rem; padding:2rem; text-align:center; cursor:pointer; transition:.2s; background:#fafbff; }
.upload-zone:hover { border-color:#667eea; background:#f0f3ff; }
.btn-return { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border:none; border-radius:50rem; padding:.75rem 2rem; font-weight:700; transition:.2s; }
.btn-return:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(102,126,234,.4); color:#fff; }
.days-badge { background:linear-gradient(135deg,#f7971e,#ffd200); color:#333; border-radius:.75rem; padding:.5rem 1rem; font-weight:700; font-size:.85rem; }
.order-info-box { background:#f8f9ff; border:1.5px solid #e0e5ff; border-radius:1rem; padding:1rem 1.25rem; }
</style>

<main class="return-wrapper">
<div class="container">
<div class="row justify-content-center">
<div class="col-lg-8">

<?php if ($order && $orderId): ?>
<!-- ══════ FORM GỬI YÊU CẦU ══════ -->
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="return_request.php" class="btn btn-light rounded-pill px-3"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
    <div>
        <h2 class="fw-800 mb-0" style="font-size:1.4rem;">Yêu cầu trả hàng</h2>
        <p class="text-muted small mb-0">Đơn hàng #<?php echo $order['id']; ?></p>
    </div>
</div>

<?php if (!$canReturn): ?>
<div class="return-card text-center py-5">
    <?php if ($returnMsg === 'expired'): ?>
        <i class="bi bi-clock-history display-3 text-warning"></i>
        <h5 class="fw-bold mt-3">Đã hết thời hạn trả hàng</h5>
        <p class="text-muted">Chính sách trả hàng chỉ áp dụng trong <?= RETURN_DAYS_LIMIT ?> ngày kể từ khi nhận hàng.</p>
    <?php elseif ($returnMsg === 'pending'): ?>
        <i class="bi bi-hourglass-split display-3 text-primary"></i>
        <h5 class="fw-bold mt-3">Yêu cầu đang được xử lý</h5>
        <p class="text-muted">Đơn hàng này đã có yêu cầu trả hàng đang được xử lý.</p>
    <?php else: ?>
        <i class="bi bi-x-circle display-3 text-danger"></i>
        <h5 class="fw-bold mt-3">Không thể yêu cầu trả hàng</h5>
        <p class="text-muted">Chỉ có thể yêu cầu trả hàng với đơn hàng đã giao thành công.</p>
    <?php endif; ?>
    <a href="return_request.php" class="btn btn-return mt-3">Xem lịch sử yêu cầu</a>
</div>

<?php else: ?>
<!-- Thông tin đơn hàng -->
<div class="return-card mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
            <h6 class="fw-800 mb-1">Thông tin đơn hàng #<?= $order['id'] ?></h6>
            <p class="text-muted small mb-0"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($order['created_at'])) ?> &nbsp;·&nbsp; <?= number_format($order['total_price'],0,',','.') ?>đ</p>
        </div>
        <div class="days-badge"><i class="bi bi-clock me-1"></i>Còn <?= $daysLeft ?> ngày để trả hàng</div>
    </div>
    <div class="order-info-box">
        <?php foreach ($items as $it): ?>
        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
            <img src="assets/images/<?= $it['image'] ?>" style="width:48px;height:48px;object-fit:contain;background:#fff;border-radius:.5rem;" onerror="this.src='https://placehold.co/48'">
            <div class="flex-grow-1">
                <p class="fw-bold mb-0 small"><?= htmlspecialchars($it['product_name']) ?></p>
                <p class="text-muted mb-0" style="font-size:.8rem;">SL: <?= $it['quantity'] ?> &nbsp;·&nbsp; <?= number_format($it['price'],0,',','.') ?>đ</p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Form yêu cầu -->
<div class="return-card">
    <h2 class="section-title"><i class="bi bi-arrow-return-left me-2 text-primary"></i>Điền thông tin yêu cầu</h2>
    <p class="section-sub">Vui lòng cung cấp đầy đủ thông tin để chúng tôi xử lý nhanh nhất.</p>

    <form id="returnForm" enctype="multipart/form-data">
        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">

        <!-- Thông tin khách -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-700 small text-uppercase" style="color:#666;letter-spacing:.05em;">Tên khách hàng</label>
                <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($currentUser['fullname']) ?>" readonly style="background:#f7f8fc;border-radius:.75rem;border:1.5px solid #e8eaf0;">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-700 small text-uppercase" style="color:#666;letter-spacing:.05em;">Số điện thoại</label>
                <input type="tel" name="phone" pattern="[0-9]{10}" class="form-control" value="<?= htmlspecialchars($currentUser['phone'] ?? $order['customer_phone'] ?? '') ?>" style="background:#f7f8fc;border-radius:.75rem;border:1.5px solid #e8eaf0;" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-700 small text-uppercase" style="color:#666;letter-spacing:.05em;">Mã đơn hàng</label>
                <input type="text" class="form-control" value="#ORD-<?= $order['id'] ?>" readonly style="background:#f7f8fc;border-radius:.75rem;border:1.5px solid #e8eaf0;">
            </div>
        </div>

        <!-- Lý do -->
        <div class="mb-4">
            <label class="form-label fw-700 small text-uppercase" style="color:#666;letter-spacing:.05em;">Lý do trả hàng *</label>
            <div class="row g-2 mb-3" id="reasonCards">
                <?php
                $reasons = [
                    ['icon'=>'bi-bug','label'=>'Sản phẩm bị lỗi / hỏng'],
                    ['icon'=>'bi-arrow-left-right','label'=>'Sản phẩm không đúng mô tả'],
                    ['icon'=>'bi-box-seam','label'=>'Nhận sai sản phẩm'],
                    ['icon'=>'bi-emoji-frown','label'=>'Không hài lòng'],
                    ['icon'=>'bi-three-dots','label'=>'Lý do khác'],
                ];
                foreach ($reasons as $i => $r):
                ?>
                <div class="col-6 col-md-4">
                    <label class="reason-card w-100" id="rc<?= $i ?>">
                        <input type="radio" name="reason_type" value="<?= $r['label'] ?>" onchange="selectReason(<?= $i ?>, '<?= $r['label'] ?>')">
                        <i class="bi <?= $r['icon'] ?> text-primary me-2"></i>
                        <span class="small fw-600"><?= $r['label'] ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <textarea name="reason" id="reasonDetail" class="form-control" rows="3" placeholder="Mô tả chi tiết vấn đề của sản phẩm..." required style="border-radius:.75rem;border:1.5px solid #e8eaf0;"></textarea>
        </div>

        <!-- Upload ảnh -->
        <div class="mb-4">
            <label class="form-label fw-700 small text-uppercase" style="color:#666;letter-spacing:.05em;">Hình ảnh sản phẩm lỗi (tối đa 3 ảnh)</label>
            <div class="upload-zone" onclick="document.getElementById('imgInput').click()">
                <i class="bi bi-cloud-arrow-up fs-2 text-primary mb-2 d-block"></i>
                <p class="mb-1 fw-600">Nhấn để chọn ảnh</p>
                <small class="text-muted">JPG, PNG, WEBP – tối đa 5MB/ảnh</small>
            </div>
            <input type="file" id="imgInput" name="images[]" multiple accept="image/*" style="display:none;" onchange="previewImages(this)">
            <div id="previewContainer" class="d-flex flex-wrap gap-2 mt-3"></div>
        </div>

        <div id="formAlert" class="alert d-none mb-3"></div>

        <div class="d-flex justify-content-end gap-3">
            <a href="track_order.php?order_id=<?= $order['id'] ?>" class="btn btn-light rounded-pill px-4">Hủy</a>
            <button type="submit" class="btn btn-return" id="submitBtn">
                <i class="bi bi-send me-2"></i>Gửi yêu cầu
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ══════ DANH SÁCH LỊCH SỬ YÊU CẦU ══════ -->
<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-800 mb-1">Yêu cầu trả hàng của tôi</h2>
        <p class="text-muted mb-0">Theo dõi trạng thái xử lý yêu cầu trả hàng / hoàn tiền.</p>
    </div>
    <span class="badge bg-primary rounded-pill px-3 py-2 fw-bold"><?= count($history) ?> YÊU CẦU</span>
</div>

<?php if (empty($history)): ?>
<div class="return-card text-center py-5">
    <i class="bi bi-arrow-return-left display-3 text-muted opacity-50"></i>
    <h5 class="fw-bold mt-4">Chưa có yêu cầu nào</h5>
    <p class="text-muted">Bạn chưa gửi yêu cầu trả hàng nào.</p>
    <a href="track_order.php" class="btn btn-return mt-2"><i class="bi bi-receipt me-2"></i>Xem đơn hàng</a>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
<?php foreach ($history as $rr):
    $statusMap = [
        'Chờ duyệt'   => ['pill-waiting',  'bi-hourglass-split', 'Chờ duyệt'],
        'Đã duyệt'    => ['pill-approved',  'bi-check-circle',    'Đã duyệt'],
        'Đã trả hàng' => ['pill-returned',  'bi-box-arrow-in-left','Đã trả hàng'],
        'Đã hoàn tiền'=> ['pill-refunded',  'bi-cash-stack',      'Đã hoàn tiền'],
        'Từ chối'     => ['pill-rejected',  'bi-x-circle',        'Từ chối'],
    ];
    $sm = $statusMap[$rr['status']] ?? ['pill-waiting','bi-question-circle',$rr['status']];
?>
<div class="return-card">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h6 class="fw-800 mb-1">Yêu cầu #<?= $rr['id'] ?> – Đơn hàng <?= $rr['order_code'] ?></h6>
            <p class="text-muted small mb-0"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y H:i', strtotime($rr['created_at'])) ?></p>
        </div>
        <span class="status-pill <?= $sm[0] ?>"><i class="bi <?= $sm[1] ?> me-1"></i><?= $sm[2] ?></span>
    </div>
    <div class="order-info-box mb-3">
        <p class="small mb-1"><strong>Lý do:</strong> <?= $rr['reason_type'] ? htmlspecialchars($rr['reason_type']) . ' – ' : '' ?><?= htmlspecialchars($rr['reason']) ?></p>
        <?php if ($rr['total_price']): ?>
        <p class="small mb-0"><strong>Giá trị đơn:</strong> <?= number_format($rr['total_price'],0,',','.') ?>đ</p>
        <?php endif; ?>
    </div>
    <?php if ($rr['images']): $imgs = json_decode($rr['images'], true) ?? []; ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($imgs as $img): ?>
        <img src="<?= htmlspecialchars($img) ?>" class="preview-img" onerror="this.style.display='none'">
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($rr['admin_note']): ?>
    <div class="alert alert-info py-2 mb-0 small">
        <i class="bi bi-chat-left-text me-2"></i><strong>Phản hồi từ shop:</strong> <?= htmlspecialchars($rr['admin_note']) ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

</div>
</div>
</div>
</main>

<script>
// Chọn lý do
function selectReason(idx, label) {
    document.querySelectorAll('.reason-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('rc' + idx).classList.add('selected');
    const ta = document.getElementById('reasonDetail');
    if (!ta.value) ta.placeholder = label + ' – Mô tả thêm chi tiết...';
}

// Preview ảnh
let selectedFiles = [];
function previewImages(input) {
    const container = document.getElementById('previewContainer');
    const newFiles  = Array.from(input.files);
    if (selectedFiles.length + newFiles.length > 3) {
        alert('Chỉ được tải lên tối đa 3 ảnh.'); return;
    }
    newFiles.forEach(file => {
        selectedFiles.push(file);
        const wrap = document.createElement('div');
        wrap.className = 'preview-wrap';
        const idx = selectedFiles.length - 1;
        wrap.innerHTML = `<img src="${URL.createObjectURL(file)}" class="preview-img">
            <button type="button" class="del-btn" onclick="removeImg(${idx}, this.parentElement)">×</button>`;
        container.appendChild(wrap);
    });
    updateFileInput();
}

function removeImg(idx, el) {
    selectedFiles.splice(idx, 1);
    el.remove();
    updateFileInput();
    // Re-index remaining del buttons
    document.querySelectorAll('.del-btn').forEach((btn, i) => {
        btn.setAttribute('onclick', `removeImg(${i}, this.parentElement)`);
    });
}

function updateFileInput() {
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    document.getElementById('imgInput').files = dt.files;
}

// Submit form
const form = document.getElementById('returnForm');
if (form) {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        const alert = document.getElementById('formAlert');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang gửi...';

        const fd = new FormData(this);
        selectedFiles.forEach(f => fd.append('images[]', f));

        try {
            const res  = await fetch('api/return_request.php', { method: 'POST', body: fd });
            const data = await res.json();
            alert.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
            alert.textContent = data.message;
            alert.classList.remove('d-none');
            if (data.success) {
                setTimeout(() => window.location.href = 'return_request.php', 2000);
            }
        } catch {
            alert.className = 'alert alert-danger';
            alert.textContent = 'Có lỗi xảy ra, vui lòng thử lại.';
            alert.classList.remove('d-none');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-2"></i>Gửi yêu cầu';
    });
}
</script>

<?php include 'includes/footer.php'; ?>
