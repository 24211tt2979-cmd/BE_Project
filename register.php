<?php
/**
 * NHK Mobile - User Registration
 *
 * Description: Enables new customers to create accounts. Handles
 * validation, secure password hashing, and duplicate account
 * prevention with enhanced security features.
 *
 * Author: NguyenHuuKhanh
 * Version: 3.0
 * Date: 2026-04-15
 */
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

// Initialization of registration state
$error = '';
$success = '';

// Generate CSRF token
$csrf_token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize_input($_POST['fullname'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = sanitize_input($_POST['phone'] ?? '');
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token_post = $_POST['csrf_token'] ?? '';

    // Validate CSRF token
    if (!validate_csrf_token($csrf_token_post)) {
        $error = "Token bảo mật không hợp lệ. Vui lòng thử lại.";
        log_auth_attempt('register', $email, false, 'Invalid CSRF token');
    }
    // Check rate limiting
    elseif (!check_rate_limit('register', 3, 600)) {
        $remaining = get_rate_limit_remaining('register', 600);
        $error = "Quá nhiều lần đăng ký. Vui lòng đợi " . ceil($remaining / 60) . " phút nữa.";
        log_auth_attempt('register', $email, false, 'Rate limited');
    }
    elseif (empty($fullname) || empty($email) || empty($password) || empty($phone)) {
        $error = "Vui lòng điền đầy đủ thông tin.";
    }
    elseif (!is_valid_phone($phone)) {
        $error = "Số điện thoại không hợp lệ! Vui lòng nhập đúng 10 chữ số.";
    }
    elseif (!is_valid_email($email)) {
        $error = "Email không hợp lệ.";
    }
    elseif ($password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp.";
    }
    else {
        // Validate password strength
        $passwordValidation = validate_password_strength($password);
        if (!$passwordValidation['valid']) {
            $error = "Mật khẩu không đủ mạnh: " . implode(', ', $passwordValidation['errors']);
        } else {
            // Dùng LOWER() để tìm email không phân biệt hoa/thường
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Email này đã được sử dụng.";
            } else {
                try {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (fullname, email, phone, password, status) VALUES (?, ?, ?, ?, 'active')");
                    if ($stmt->execute([$fullname, $email, $phone, $hashedPassword])) {
                        $success = "Đăng ký thành công! Đang chuyển hướng...";
                        clear_rate_limit('register');
                        clear_csrf_token();
                        log_auth_attempt('register', $email, true, 'Registration successful');
                        header("refresh:2;url=login.php");
                    } else {
                        $error = "Có lỗi xảy ra, vui lòng thử lại.";
                        log_auth_attempt('register', $email, false, 'Database error');
                    }
                } catch (PDOException $e) {
                    // Dùng stripos (case-insensitive) để bắt "Duplicate entry" của MySQL
                    $errMsg = $e->getMessage();
                    error_log("[REGISTER] PDOException: " . $errMsg);
                    if (stripos($errMsg, 'unique') !== false || stripos($errMsg, 'duplicate') !== false) {
                        $error = "Email này đã được đăng ký.";
                    } else {
                        $error = "Có lỗi xảy ra: " . htmlspecialchars($errMsg);
                    }
                    log_auth_attempt('register', $email, false, 'DB error: ' . substr($errMsg, 0, 100));
                }
            }
        }
    }
}

$pageTitle = "Đăng ký | NHK Mobile";
$basePath = "";
include 'includes/header.php';
?>

<main class="min-vh-100 d-flex align-items-center justify-content-center bg-gray py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <div class="auth-card bg-white p-5 rounded-4 shadow-lg border">
                    <div class="text-center mb-5">
                        <div class="nav-icon bg-primary-light text-primary mx-auto mb-4" style="width: 64px; height: 64px; font-size: 32px;">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <h2 class="fw-800 mb-2">Tạo tài khoản mới</h2>
                        <p class="text-secondary small fw-500">Trở thành thành viên của NHK Mobile</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 rounded-3 small fw-600 mb-4 auth-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success border-0 rounded-3 small fw-600 mb-4 auth-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <form action="register.php" method="POST" id="registerForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="mb-4">
                            <label class="form-label small fw-800 text-muted text-uppercase letter-spacing">Họ và tên</label>
                            <input type="text" name="fullname" class="form-control bg-light border-0 py-3 px-4 rounded-3 auth-input" placeholder="Nguyễn Văn A" required autocomplete="name">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-800 text-muted text-uppercase letter-spacing">Email</label>
                            <input type="email" name="email" class="form-control bg-light border-0 py-3 px-4 rounded-3 auth-input" placeholder="example@email.com" required autocomplete="email">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-800 text-muted text-uppercase letter-spacing">Số điện thoại</label>
                            <input type="tel" name="phone" class="form-control bg-light border-0 py-3 px-4 rounded-3 auth-input" placeholder="0901234567" required pattern="[0-9]{10}" title="Vui lòng nhập đúng 10 chữ số">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-800 text-muted text-uppercase letter-spacing">Mật khẩu</label>
                            <input type="password" name="password" id="regPassword" class="form-control bg-light border-0 py-3 px-4 rounded-3 auth-input" placeholder="••••••••" required autocomplete="new-password">
                            <div class="password-strength mt-2" id="passwordStrength">
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <small class="strength-text" id="strengthText">Chưa nhập mật khẩu</small>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="showRegPassword">
                                <label class="form-check-label small text-muted" for="showRegPassword">
                                    Hiển thị mật khẩu
                                </label>
                            </div>
                        </div>
                        <div class="mb-5">
                            <label class="form-label small fw-800 text-muted text-uppercase letter-spacing">Xác nhận mật khẩu</label>
                            <input type="password" name="confirm_password" id="regConfirmPassword" class="form-control bg-light border-0 py-3 px-4 rounded-3 auth-input" placeholder="••••••••" required autocomplete="new-password">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="showRegConfirmPassword">
                                <label class="form-check-label small text-muted" for="showRegConfirmPassword">
                                    Hiển thị mật khẩu
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn-main btn-primary w-100 py-3 mb-4 auth-btn">
                            <span class="btn-text">Đăng ký tài khoản</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Đang đăng ký...
                            </span>
                        </button>
                        <div class="text-center">
                            <p class="text-secondary small fw-500 mb-0">Đã có tài khoản? <a href="login.php" class="text-primary fw-800 auth-link">Đăng nhập</a></p>
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

.auth-success {
    animation: fadeIn 0.5s ease-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
    20%, 40%, 60%, 80% { transform: translateX(10px); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.password-strength {
    margin-top: 8px;
}

.strength-bar {
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
}

.strength-fill {
    height: 100%;
    width: 0;
    transition: all 0.3s ease;
    border-radius: 2px;
}

.strength-text {
    display: block;
    margin-top: 4px;
    font-size: 0.75rem;
    color: #6c757d;
}

.strength-weak .strength-fill {
    width: 33%;
    background: #dc3545;
}

.strength-medium .strength-fill {
    width: 66%;
    background: #ffc107;
}

.strength-strong .strength-fill {
    width: 100%;
    background: #28a745;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const passwordInput = document.getElementById('regPassword');
    const confirmInput = document.getElementById('regConfirmPassword');
    const showRegPassword = document.getElementById('showRegPassword');
    const showRegConfirmPassword = document.getElementById('showRegConfirmPassword');
    const submitBtn = registerForm.querySelector('.auth-btn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');
    const passwordStrength = document.getElementById('passwordStrength');

    // Password strength checker
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strength = checkPasswordStrength(password);
        
        passwordStrength.className = 'password-strength strength-' + strength.level;
        strengthText.textContent = strength.text;
    });

    function checkPasswordStrength(password) {
        if (!password) return { level: 'none', text: 'Chưa nhập mật khẩu' };
        
        let score = 0;
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) score++;

        if (score <= 2) return { level: 'weak', text: 'Yếu' };
        if (score <= 4) return { level: 'medium', text: 'Trung bình' };
        return { level: 'strong', text: 'Mạnh' };
    }

    // Show/hide password toggles
    showRegPassword.addEventListener('change', function() {
        passwordInput.type = this.checked ? 'text' : 'password';
    });

    showRegConfirmPassword.addEventListener('change', function() {
        confirmInput.type = this.checked ? 'text' : 'password';
    });

    // Form submission with loading state
    registerForm.addEventListener('submit', function(e) {
        if (submitBtn.disabled) {
            e.preventDefault();
            return;
        }

        const strength = checkPasswordStrength(passwordInput.value);
        if (strength.level === 'weak') {
            e.preventDefault();
            alert('Mật khẩu không đủ mạnh. Vui lòng sử dụng mật khẩu có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt.');
            return;
        }

        if (passwordInput.value !== confirmInput.value) {
            e.preventDefault();
            alert('Mật khẩu xác nhận không khớp.');
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
