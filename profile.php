<?php
/**
 * NHK Mobile - Trang Hồ Sơ Người Dùng
 * 
 * Description: Cho phép người dùng xem & cập nhật thông tin cá nhân,
 * đổi mật khẩu và xem lại lịch sử đơn hàng tại một nơi duy nhất.
 * 
 * Author: NguyenHuuKhanh
 * Version: 1.0
 * Date: 2026-04-13
 */
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

// Bắt buộc phải đăng nhập mới vào được trang này
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=profile.php");
    exit;
}

$userId   = $_SESSION['user_id'];
$success  = '';
$error    = '';
$activeTab = $_GET['tab'] ?? 'info'; // info | password | orders | wishlist

// ─── LẤY THÔNG TIN USER HIỆN TẠI ────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT id, fullname, email, phone, address, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    // Không tìm thấy user -> đăng xuất
    session_destroy();
    header("Location: login.php");
    exit;
}

// ─── XỬ LÝ CẬP NHẬT THÔNG TIN CÁ NHÂN ──────────────────────────────────────
if (isset($_POST['update_info'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $address  = trim($_POST['address']  ?? '');

    if (empty($fullname)) {
        $error = "Họ tên không được để trống.";
        $activeTab = 'info';
    } elseif (!empty($phone) && !is_valid_phone($phone)) {
        $error = "Số điện thoại không hợp lệ! Vui lòng nhập đúng 10 chữ số.";
        $activeTab = 'info';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET fullname = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$fullname, $phone, $address, $userId]);

            // Cập nhật luôn session để hiển thị tên mới trên navbar
            $_SESSION['user_fullname'] = $fullname;
            $user['fullname'] = $fullname;
            $user['phone']    = $phone;
            $user['address']  = $address;

            $success   = "Cập nhật thông tin thành công!";
            $activeTab = 'info';
        } catch (PDOException $e) {
            $error     = "Có lỗi xảy ra: " . $e->getMessage();
            $activeTab = 'info';
        }
    }
}

// ─── XỬ LÝ ĐỔI MẬT KHẨU ────────────────────────────────────────────────────
if (isset($_POST['change_password'])) {
    $currentPw  = $_POST['current_password'] ?? '';
    $newPw      = $_POST['new_password']     ?? '';
    $confirmPw  = $_POST['confirm_password'] ?? '';
    $activeTab  = 'password';

    // Lấy hash hiện tại
    $stmtPw = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmtPw->execute([$userId]);
    $pwRow = $stmtPw->fetch();

    if (!password_verify($currentPw, $pwRow['password'])) {
        $error = "Mật khẩu hiện tại không đúng.";
    } elseif (strlen($newPw) < 6) {
        $error = "Mật khẩu mới phải có ít nhất 6 ký tự.";
    } elseif ($newPw !== $confirmPw) {
        $error = "Xác nhận mật khẩu không khớp.";
    } else {
        $hash = password_hash($newPw, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
        $success = "Đổi mật khẩu thành công!";
    }
}

// ─── LẤY LỊCH SỬ ĐƠN HÀNG ──────────────────────────────────────────────────
$stmtOrders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmtOrders->execute([$userId]);
$orders = $stmtOrders->fetchAll();

// ─── LẤY DANH SÁCH YÊU THÍCH ────────────────────────────────────────────────
$wishlistItems = [];
$wishlistCount = 0;
try {
    $stmtWl = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.image, p.category, p.stock
        FROM wishlists w JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ? ORDER BY w.created_at DESC
    ");
    $stmtWl->execute([$userId]);
    $wishlistItems = $stmtWl->fetchAll();
    $wishlistCount = count($wishlistItems);
} catch (PDOException $e) { /* Bảng chưa tạo thì bỏ qua */ }

// Thống kê nhanh
$totalOrders  = count($orders);
$totalSpent   = array_sum(array_column($orders, 'total_price'));
$pendingCount = count(array_filter($orders, fn($o) => stripos($o['status'], 'chờ') !== false || stripos($o['status'], 'pending') !== false));

$pageTitle = "Hồ sơ của tôi | NHK Mobile";
$basePath  = "";
include 'includes/header.php';
?>

<style>
/* ──────────────────────────────────────────────
   Profile Page – Premium Design
─────────────────────────────────────────────── */
.profile-wrapper {
    background: #f4f6fb;
    min-height: 100vh;
    padding-top: 90px;
    padding-bottom: 60px;
}

/* Sidebar card */
.profile-sidebar {
    background: #fff;
    border-radius: 1.5rem;
    box-shadow: 0 4px 30px rgba(0,0,0,0.07);
    overflow: hidden;
    position: sticky;
    top: 90px;
}
.profile-avatar {
    width: 90px; height: 90px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem; color: #fff;
    margin: 0 auto 1rem;
    box-shadow: 0 8px 24px rgba(102,126,234,0.35);
}
.sidebar-nav .nav-item a {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1.5rem;
    border-radius: 0;
    color: #555;
    font-weight: 600;
    font-size: 0.92rem;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
    border-left: 3px solid transparent;
}
.sidebar-nav .nav-item a:hover {
    background: #f0f3ff;
    color: #5a67d8;
}
.sidebar-nav .nav-item a.active {
    background: #eef0ff;
    color: #5a67d8;
    border-left-color: #5a67d8;
    font-weight: 700;
}
.sidebar-nav .nav-item a i { font-size: 1.1rem; }

/* Main content card */
.profile-card {
    background: #fff;
    border-radius: 1.5rem;
    box-shadow: 0 4px 30px rgba(0,0,0,0.07);
    padding: 2.5rem;
}
.profile-card .section-title {
    font-size: 1.2rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
    color: #1a1a2e;
}
.profile-card .section-sub {
    font-size: 0.85rem;
    color: #888;
    margin-bottom: 2rem;
}

/* Stat cards */
.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 1.25rem;
    padding: 1.5rem;
    color: #fff;
    text-align: center;
    transition: transform 0.3s;
}
.stat-card:hover { transform: translateY(-4px); }
.stat-card.green  { background: linear-gradient(135deg, #11998e, #38ef7d); }
.stat-card.orange { background: linear-gradient(135deg, #f7971e, #ffd200); color: #333; }
.stat-card .stat-num  { font-size: 2rem; font-weight: 800; line-height: 1; }
.stat-card .stat-lbl  { font-size: 0.8rem; font-weight: 600; opacity: 0.85; margin-top: 0.35rem; }

/* Form input */
.profile-input {
    background: #f7f8fc;
    border: 1.5px solid #e8eaf0;
    border-radius: 0.75rem;
    padding: 0.8rem 1rem;
    font-size: 0.92rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.profile-input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
    outline: none;
    background: #fff;
}
.profile-label {
    font-size: 0.8rem;
    font-weight: 700;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}

/* Order mini card */
.order-mini-card {
    border: 1.5px solid #eef0f6;
    border-radius: 1rem;
    padding: 1.1rem 1.25rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    text-decoration: none;
    color: inherit;
    display: block;
}
.order-mini-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 20px rgba(102,126,234,0.1);
    color: inherit;
}

/* Password strength bar */
.strength-bar {
    height: 5px;
    border-radius: 10px;
    transition: width 0.4s, background 0.4s;
    background: #eee;
    width: 0%;
}

/* Alert */
.profile-alert {
    border-radius: 0.75rem;
    border: none;
    font-weight: 600;
    font-size: 0.9rem;
    padding: 0.9rem 1.2rem;
}

/* btn */
.btn-profile {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    border: none;
    border-radius: 50rem;
    padding: 0.75rem 2rem;
    font-weight: 700;
    font-size: 0.92rem;
    box-shadow: 0 4px 15px rgba(102,126,234,0.35);
    transition: transform 0.2s, box-shadow 0.2s;
}
.btn-profile:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(102,126,234,0.45);
    color: #fff;
}
</style>

<main class="profile-wrapper">
    <div class="container">
        <div class="row g-4">

            <!-- ═══════════════════ SIDEBAR ═══════════════════ -->
            <div class="col-lg-3 col-md-4">
                <div class="profile-sidebar">
                    <!-- Avatar + tên -->
                    <div class="text-center py-4 px-3 border-bottom">
                        <div class="profile-avatar">
                            <?php echo mb_strtoupper(mb_substr($user['fullname'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                        </div>
                        <h6 class="fw-800 mb-0"><?php echo htmlspecialchars($user['fullname']); ?></h6>
                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                        <span class="badge bg-primary bg-opacity-10 text-primary mt-2 px-3 py-1 rounded-pill small fw-600">
                            Thành viên
                        </span>
                    </div>

                    <!-- Menu điều hướng -->
                    <nav class="sidebar-nav py-2">
                        <ul class="list-unstyled mb-0">
                            <li class="nav-item">
                                <a href="profile.php?tab=info" class="<?php echo $activeTab === 'info' ? 'active' : ''; ?>">
                                    <i class="bi bi-person-vcard"></i> Thông tin cá nhân
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="profile.php?tab=password" class="<?php echo $activeTab === 'password' ? 'active' : ''; ?>">
                                    <i class="bi bi-lock"></i> Đổi mật khẩu
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="profile.php?tab=orders" class="<?php echo $activeTab === 'orders' ? 'active' : ''; ?>">
                                    <i class="bi bi-receipt-cutoff"></i> Đơn hàng của tôi
                                    <?php if ($totalOrders > 0): ?>
                                        <span class="badge bg-primary rounded-pill ms-auto"><?php echo $totalOrders; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="profile.php?tab=wishlist" class="<?php echo $activeTab === 'wishlist' ? 'active' : ''; ?>">
                                    <i class="bi bi-heart"></i> Yêu thích
                                    <?php if ($wishlistCount > 0): ?>
                                        <span class="badge bg-danger rounded-pill ms-auto"><?php echo $wishlistCount; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        </ul>
                    </nav>

                    <!-- Đăng xuất -->
                    <div class="p-3 border-top">
                        <a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill py-2 fw-700 small">
                            <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                        </a>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════ MAIN CONTENT ═══════════════════ -->
            <div class="col-lg-9 col-md-8">

                <!-- Alert thành công / lỗi -->
                <?php if ($success): ?>
                    <div class="alert alert-success profile-alert mb-4 d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill fs-5"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger profile-alert mb-4 d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- ══════ TAB: THÔNG TIN CÁ NHÂN ══════ -->
                <?php if ($activeTab === 'info'): ?>

                <!-- Stat cards tổng quan -->
                <div class="row g-3 mb-4">
                    <div class="col-4">
                        <div class="stat-card">
                            <div class="stat-num"><?php echo $totalOrders; ?></div>
                            <div class="stat-lbl">Đơn hàng</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card green">
                            <div class="stat-num"><?php echo number_format($totalSpent / 1000000, 1); ?>M</div>
                            <div class="stat-lbl">Đã chi tiêu</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card orange">
                            <div class="stat-num"><?php echo $pendingCount; ?></div>
                            <div class="stat-lbl">Đang xử lý</div>
                        </div>
                    </div>
                </div>

                <!-- Form thông tin -->
                <div class="profile-card">
                    <h2 class="section-title"><i class="bi bi-person-vcard me-2 text-primary"></i>Thông tin cá nhân</h2>
                    <p class="section-sub">Cập nhật tên, số điện thoại và địa chỉ giao hàng của bạn.</p>

                    <form method="POST" action="profile.php?tab=info">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="profile-label">Họ và tên *</label>
                                <input type="text" name="fullname" class="form-control profile-input"
                                       value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="profile-label">Email</label>
                                <input type="email" class="form-control profile-input"
                                       value="<?php echo htmlspecialchars($user['email']); ?>" disabled title="Email không thể thay đổi">
                                <small class="text-muted">Email không thể thay đổi.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="profile-label">Số điện thoại</label>
                                <input type="tel" name="phone" class="form-control profile-input"
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       placeholder="VD: 0901234567"
                                       pattern="[0-9]{10}" title="Vui lòng nhập đúng 10 chữ số">
                            </div>
                            <div class="col-md-6">
                                <label class="profile-label">Ngày tham gia</label>
                                <input type="text" class="form-control profile-input" disabled
                                       value="<?php echo date('d/m/Y', strtotime($user['created_at'])); ?>">
                            </div>
                            <div class="col-12">
                                <label class="profile-label">Địa chỉ giao hàng mặc định</label>
                                <textarea name="address" class="form-control profile-input" rows="2"
                                          placeholder="VD: 123 Nguyễn Huệ, Q.1, TP.HCM"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" name="update_info" class="btn btn-profile">
                                <i class="bi bi-save me-2"></i>Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ══════ TAB: ĐỔI MẬT KHẨU ══════ -->
                <?php elseif ($activeTab === 'password'): ?>
                <div class="profile-card">
                    <h2 class="section-title"><i class="bi bi-lock me-2 text-primary"></i>Đổi mật khẩu</h2>
                    <p class="section-sub">Để bảo mật tài khoản, hãy dùng mật khẩu dài ít nhất 8 ký tự.</p>

                    <form method="POST" action="profile.php?tab=password" id="pwForm">
                        <div class="d-flex flex-column gap-4" style="max-width: 480px;">
                            <div>
                                <label class="profile-label">Mật khẩu hiện tại *</label>
                                <div class="position-relative">
                                    <input type="text" name="current_password" id="curPw"
                                           class="form-control profile-input pe-5" required placeholder="••••••••">
                                    <span class="position-absolute top-50 end-0 translate-middle-y pe-3 text-muted"
                                          style="cursor:pointer;" onclick="togglePw('curPw',this)">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="profile-label">Mật khẩu mới *</label>
                                <div class="position-relative">
                                    <input type="password" name="new_password" id="newPw"
                                           class="form-control profile-input pe-5" required placeholder="••••••••"
                                           oninput="checkStrength(this.value)">
                                    <span class="position-absolute top-50 end-0 translate-middle-y pe-3 text-muted"
                                          style="cursor:pointer;" onclick="togglePw('newPw',this)">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                                <!-- Thanh độ mạnh -->
                                <div class="mt-2 bg-light rounded" style="height:5px;">
                                    <div id="strengthBar" class="strength-bar"></div>
                                </div>
                                <small id="strengthText" class="text-muted"></small>
                            </div>
                            <div>
                                <label class="profile-label">Xác nhận mật khẩu mới *</label>
                                <input type="password" name="confirm_password" class="form-control profile-input" required placeholder="••••••••">
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" name="change_password" class="btn btn-profile">
                                <i class="bi bi-shield-lock me-2"></i>Đổi mật khẩu
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ══════ TAB: LỊCH SỬ ĐƠN HÀNG ══════ -->
                <?php elseif ($activeTab === 'orders'): ?>
                <div class="profile-card">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h2 class="section-title mb-0"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Đơn hàng của tôi</h2>
                        <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo $totalOrders; ?> ĐƠN</span>
                    </div>
                    <p class="section-sub">Theo dõi toàn bộ lịch sử giao dịch của bạn tại NHK Mobile.</p>

                    <?php if (empty($orders)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-bag-x display-3 text-muted opacity-50"></i>
                            <h5 class="fw-bold mt-4">Chưa có đơn hàng nào</h5>
                            <p class="text-muted">Hãy khám phá các sản phẩm tuyệt vời tại NHK Mobile!</p>
                            <a href="product.php" class="btn btn-profile mt-2">
                                <i class="bi bi-phone me-2"></i>Mua sắm ngay
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($orders as $o):
                                $s = mb_strtolower($o['status'], 'UTF-8');
                                if (str_contains($s, 'hoàn thành'))      { $bClass = 'bg-success'; $bText = 'Hoàn thành'; }
                                elseif (str_contains($s, 'đang giao'))   { $bClass = 'bg-primary'; $bText = 'Đang giao'; }
                                elseif (str_contains($s, 'hủy'))          { $bClass = 'bg-danger';  $bText = 'Đã hủy'; }
                                else                                       { $bClass = 'bg-warning text-dark'; $bText = $o['status']; }
                            ?>
                            <a href="track_order.php?order_id=<?php echo $o['id']; ?>" class="order-mini-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                                            <i class="bi bi-bag-check text-primary fs-4"></i>
                                        </div>
                                        <div>
                                            <p class="fw-800 mb-0">Đơn hàng #<?php echo $o['id']; ?></p>
                                            <p class="text-muted small mb-0">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($o['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-end d-flex flex-column align-items-end gap-2">
                                        <span class="badge <?php echo $bClass; ?> rounded-pill px-3 py-1"><?php echo $bText; ?></span>
                                        <span class="fw-800 text-danger">
                                            <?php echo number_format($o['total_price'], 0, ',', '.'); ?>đ
                                        </span>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ══════ TAB: YÊU THÍCH ══════ -->
                <?php elseif ($activeTab === 'wishlist'): ?>
                <div class="profile-card">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h2 class="section-title mb-0"><i class="bi bi-heart-fill me-2 text-danger"></i>Yêu thích</h2>
                        <span class="badge bg-danger rounded-pill px-3 py-2"><?php echo $wishlistCount; ?> SẢN PHẨM</span>
                    </div>
                    <p class="section-sub">Danh sách sản phẩm bạn đã lưu để mua sau.</p>

                    <?php if (empty($wishlistItems)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-heart display-3 text-muted opacity-50"></i>
                            <h5 class="fw-bold mt-4">Chưa có sản phẩm yêu thích</h5>
                            <p class="text-muted">Nhấn biểu tượng ♥ trên sản phẩm để lưu vào đây.</p>
                            <a href="product.php" class="btn btn-profile mt-2">
                                <i class="bi bi-phone me-2"></i>Khám phá ngay
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                        <?php foreach ($wishlistItems as $wi): ?>
                            <div class="col-sm-6 col-md-4">
                                <div class="border rounded-3 overflow-hidden h-100 d-flex flex-column">
                                    <a href="product-detail.php?id=<?php echo $wi['id']; ?>" class="d-block bg-light p-3 text-center">
                                        <img src="assets/images/<?php echo $wi['image']; ?>"
                                             alt="<?php echo htmlspecialchars($wi['name']); ?>"
                                             style="height:120px; object-fit:contain;"
                                             onerror="this.src='https://placehold.co/200x200/f5f5f7/333?text=NHK'">
                                    </a>
                                    <div class="p-3 flex-grow-1 d-flex flex-column">
                                        <p class="text-primary fw-800 text-uppercase mb-1" style="font-size:.7rem;letter-spacing:.1em"><?php echo htmlspecialchars($wi['category']); ?></p>
                                        <p class="fw-700 mb-1 small"><?php echo htmlspecialchars($wi['name']); ?></p>
                                        <p class="fw-800 text-dark mb-3"><?php echo number_format($wi['price'],0,',','.'); ?>₫</p>
                                        <div class="mt-auto d-flex gap-2">
                                            <?php if ($wi['stock'] > 0): ?>
                                            <a href="cart.php?add=<?php echo $wi['id']; ?>" class="btn btn-dark btn-sm rounded-pill flex-grow-1 fw-700">
                                                <i class="bi bi-bag-plus me-1"></i>Giỏ hàng
                                            </a>
                                            <?php else: ?>
                                            <span class="btn btn-secondary btn-sm rounded-pill flex-grow-1 fw-700 disabled">Hết hàng</span>
                                            <?php endif; ?>
                                            <a href="product-detail.php?id=<?php echo $wi['id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-4">
                            <a href="wishlist.php" class="btn btn-profile">
                                <i class="bi bi-heart me-2"></i>Xem trang yêu thích đầy đủ
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php endif; ?>

            </div>
        </div>
    </div>
</main>

<script>
// Toggle hiện/ẩn mật khẩu
function togglePw(id, el) {
    const inp = document.getElementById(id);
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    el.querySelector('i').className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
}

// Đo độ mạnh mật khẩu
function checkStrength(val) {
    const bar  = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { w: '20%', bg: '#e74c3c', label: 'Rất yếu' },
        { w: '40%', bg: '#e67e22', label: 'Yếu' },
        { w: '60%', bg: '#f1c40f', label: 'Trung bình' },
        { w: '80%', bg: '#2ecc71', label: 'Mạnh' },
        { w: '100%', bg: '#27ae60', label: 'Rất mạnh' },
    ];
    const lv = levels[Math.min(score - 1, 4)] || levels[0];
    bar.style.width = val.length ? lv.w : '0%';
    bar.style.background = lv.bg;
    text.textContent = val.length ? lv.label : '';
    text.style.color = lv.bg;
}
</script>

<?php include 'includes/footer.php'; ?>
