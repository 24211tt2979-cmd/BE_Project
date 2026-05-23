<?php
/**
 * NHK Mobile - Authentication Portal
 *
 * Description: Unified login gateway for customers and administrators.
 * Handles credential verification, hash validation, and session
 * lifecycle management with enhanced security features.
 *
 * Author: NguyenHuuKhanh
 * Version: 3.0
 * Date: 2026-04-15
 */
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

// Initialization of authentication variables
$error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'banned') {
        $error = "Tài khoản của bạn đã bị khóa bởi Quản trị viên.";
    } elseif ($_GET['error'] === 'no_admin') {
        $error = "Bạn không có quyền truy cập trang quản trị.";
    }
}
$redirect = $_GET['redirect'] ?? 'index.php';

// Check session timeout
check_session_timeout();

// Generate CSRF token
$csrf_token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_user = sanitize_input($_POST['email_or_user'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token_post = $_POST['csrf_token'] ?? '';

    // Validate CSRF token
    if (!validate_csrf_token($csrf_token_post)) {
        $error = "Token bảo mật không hợp lệ. Vui lòng thử lại.";
        log_auth_attempt('login', $email_or_user, false, 'Invalid CSRF token');
    }
    // Check rate limiting
    elseif (!check_rate_limit('login', 5, 300)) {
        $remaining = get_rate_limit_remaining('login', 300);
        $error = "Quá nhiều lần thử. Vui lòng đợi " . ceil($remaining / 60) . " phút nữa.";
        log_auth_attempt('login', $email_or_user, false, 'Rate limited');
    }
    elseif (empty($email_or_user) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ tài khoản và mật khẩu.";
    } else {
        // Try to find user by email OR username
        $stmt = $pdo->prepare("SELECT id, fullname, email, password, status FROM users WHERE email = ? OR fullname = ?");
        $stmt->execute([$email_or_user, $email_or_user]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'banned') {
                $error = "Tài khoản của bạn đã bị khóa.";
                log_auth_attempt('login', $email_or_user, false, 'Account banned');
            } else {
                // Clear rate limit on successful login
                clear_rate_limit('login');
                clear_csrf_token();
                
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                if (isset($user['role']) && $user['role'] === 'admin') {
                    // Cấp quyền Admin
                    unset($_SESSION['user_id'], $_SESSION['user_fullname'], $_SESSION['user_email']);
                    $_SESSION['admin_id']   = $user['id'];
                    $_SESSION['admin_user'] = $user['fullname'];
                    $_SESSION['last_activity'] = time();
                    
                    $adminUrl = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') === ''
                        ? '/admin/dashboard.php'
                        : 'admin/dashboard.php';
                    log_auth_attempt('login', $email_or_user, true, 'Admin login successful via role');
                    require_once 'includes/functions.php';
                    log_admin_action($pdo, 'LOGIN', "Đăng nhập hệ thống (User có quyền Admin)");
                    
                    header("Location: " . $adminUrl);
                    exit;
                } else {
                    // Xóa session admin nếu có (không để cả 2 vào cùng lúc)
                    unset($_SESSION['admin_id'], $_SESSION['admin_user']);
                    $_SESSION['user_id']       = $user['id'];
                    $_SESSION['user_fullname'] = $user['fullname'];
                    $_SESSION['user_email']    = $user['email'];
                    $_SESSION['last_activity'] = time();
                    
                    log_auth_attempt('login', $email_or_user, true, 'User login successful');
                    header("Location: " . $redirect);
                    exit;
                }
            }
        } else {
            $stmtAdmin = $pdo->prepare("SELECT id, username, password FROM admins WHERE username = ?");
            $stmtAdmin->execute([$email_or_user]);
            $admin = $stmtAdmin->fetch();

            if ($admin) {
                if ($password === $admin['password'] || password_verify($password, $admin['password'])) {
                    // Clear rate limit on successful login
                    clear_rate_limit('login');
                    clear_csrf_token();
                    
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);
                    
                    // Xóa session user nếu có (admin và user không được cùng session)
                    unset($_SESSION['user_id'], $_SESSION['user_fullname'], $_SESSION['user_email']);
                    $_SESSION['admin_id']   = $admin['id'];
                    $_SESSION['admin_user'] = $admin['username'];
                    $_SESSION['last_activity'] = time();
                    
                    // Redirect về admin/dashboard.php luôn — không phụ thuộc $redirect
                    $adminUrl = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') === ''
                        ? '/admin/dashboard.php'
                        : 'admin/dashboard.php';
                    
                    log_auth_attempt('login', $email_or_user, true, 'Admin login successful');
                    require_once 'includes/functions.php';
                    log_admin_action($pdo, 'LOGIN', "Đăng nhập hệ thống");
                    
                    header("Location: " . $adminUrl);
                    exit;
                }
            }
            
            $error = "Tài khoản hoặc mật khẩu không chính xác.";
            log_auth_attempt('login', $email_or_user, false, 'Invalid credentials');
        }
    }
}

$pageTitle = "Đăng nhập | NHK Mobile";
$basePath = "";
include 'includes/header.php';
?>

<main class="min-vh-100 d-flex align-items-center justify-content-center bg-gray py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="auth-card bg-white p-5 rounded-4 shadow-lg border">
                    <div class="text-center mb-5">
                        <div class="nav-icon bg-primary-light text-primary mx-auto mb-4" style="width: 64px; height: 64px; font-size: 32px;">
                            <i class="bi bi-person-lock"></i>
                        </div>
                        <h2 class="fw-800 mb-2">Chào mừng trở lại</h2>
                        <p class="text-secondary small fw-500">Đăng nhập để tiếp tục trải nghiệm</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 rounded-3 small fw-600 mb-4 auth-error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form action="login.php?redirect=<?php echo urlencode($redirect); ?>" method="POST" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="mb-4">
                            <label class="form-label small fw-800 text-muted text-uppercase letter-spacing">Email / Username</label>
                            <input type="text" name="email_or_user" class="form-control bg-light border-0 py-3 px-4 rounded-3 auth-input" placeholder="Nhập tài khoản" required autocomplete="email">
                        </div>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-800 text-muted text-uppercase letter-spacing mb-0">Mật khẩu</label>
                                <a href="forgot-password.php" class="text-primary small fw-700 text-decoration-none">Quên?</a>
                            </div>
                            <input type="password" name="password" id="loginPassword" class="form-control bg-light border-0 py-3 px-4 rounded-3 auth-input" placeholder="••••••••" required autocomplete="current-password">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="showLoginPassword">
                                <label class="form-check-label small text-muted" for="showLoginPassword">
                                    Hiển thị mật khẩu
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn-main btn-primary w-100 py-3 mb-4 auth-btn">
                            <span class="btn-text">Đăng nhập</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Đang đăng nhập...
                            </span>
                        </button>
                        <div class="text-center">
                            <p class="text-secondary small fw-500 mb-0">Chưa có tài khoản? <a href="register.php" class="text-primary fw-800 auth-link">Đăng ký ngay</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.auth-card {
    opacity: 0;
    transform: translateY(30px);
    animation: slideUp 0.6s ease-out forwards;
}

@keyframes slideUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.auth-input {
    transition: all 0.3s ease;
}

.auth-input:focus {
    background: #fff !important;
    box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.15);
    transform: translateY(-2px);
}

.auth-btn {
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.auth-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 122, 255, 0.3);
}

.auth-btn:active {
    transform: translateY(0);
}

.auth-link {
    position: relative;
    transition: all 0.3s ease;
}

.auth-link:hover {
    transform: scale(1.05);
}

.auth-error {
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
    20%, 40%, 60%, 80% { transform: translateX(10px); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const showPasswordCheckbox = document.getElementById('showLoginPassword');
    const passwordInput = document.getElementById('loginPassword');
    const submitBtn = loginForm.querySelector('.auth-btn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');

    // Show/hide password toggle
    if (showPasswordCheckbox && passwordInput) {
        showPasswordCheckbox.addEventListener('change', function() {
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    }

    // Form submission with loading state
    loginForm.addEventListener('submit', function(e) {
        if (submitBtn.disabled) {
            e.preventDefault();
            return;
        }

        // Show loading state
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
    });

    // Add subtle focus animations
    const inputs = document.querySelectorAll('.auth-input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
