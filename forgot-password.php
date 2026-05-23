<?php
/**
 * NHK Mobile - Forgot Password System
 *
 * Description: Handles password reset requests with secure token generation,
 * email notification to admin, and user verification flow.
 *
 * Author: NguyenHuuKhanh
 * Version: 1.0
 * Date: 2026-04-15
 */
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

$error = '';
$success = '';
$step = $_GET['step'] ?? 'request'; // request, verify, reset
$token = $_GET['token'] ?? '';

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Handle password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
    $email = sanitize_input($_POST['email'] ?? '');
    $csrf_token_post = $_POST['csrf_token'] ?? '';

    if (!validate_csrf_token($csrf_token_post)) {
        $error = "Token bảo mật không hợp lệ.";
    } elseif (!check_rate_limit('forgot_password', 3, 600)) {
        $error = "Quá nhiều yêu cầu. Vui lòng thử lại sau.";
    } elseif (empty($email) || !is_valid_email($email)) {
        $error = "Email không hợp lệ.";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id, fullname, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate secure reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Insert into password_resets
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, reset_token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $resetToken, $expiresAt]);

            // Log the request
            log_auth_attempt('forgot_password', $email, true, 'Reset token generated');

            // In production, send email to user and admin
            // For now, show token for demo (in production, remove this)
            $resetUrl = "http://" . $_SERVER['HTTP_HOST'] . "/forgot-password.php?step=reset&token=" . $resetToken;
            
            $success = "Yêu cầu đặt lại mật khẩu đã được tạo!";
            $success .= "<br><small class='text-muted'>Vui lòng truy cập: <a href='{$resetUrl}'>Đặt lại mật khẩu</a></small>";
            
            clear_rate_limit('forgot_password');
        } else {
            // Don't reveal if email exists or not (security best practice)
            $success = "Nếu email tồn tại trong hệ thống, bạn sẽ nhận được hướng dẫn đặt lại mật khẩu.";
            log_auth_attempt('forgot_password', $email, false, 'Email not found');
        }
    }
}

// Handle new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    $token = $_POST['token'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrf_token_post = $_POST['csrf_token'] ?? '';

    if (!validate_csrf_token($csrf_token_post)) {
        $error = "Token bảo mật không hợp lệ.";
    } elseif (empty($token)) {
        $error = "Token không hợp lệ.";
    } elseif (empty($newPassword) || empty($confirmPassword)) {
        $error = "Vui lòng nhập đầy đủ mật khẩu.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Mật khẩu xác nhận không khớp.";
    } else {
        // Validate password strength
        $passwordValidation = validate_password_strength($newPassword);
        if (!$passwordValidation['valid']) {
            $error = "Mật khẩu không đủ mạnh: " . implode(', ', $passwordValidation['errors']);
        } else {
            // Verify token
            $stmt = $pdo->prepare("
                SELECT pr.*, u.id as user_id 
                FROM password_resets pr 
                JOIN users u ON pr.user_id = u.id 
                WHERE pr.reset_token = ? 
                AND pr.expires_at > NOW() 
                AND pr.is_used = FALSE
            ");
            $stmt->execute([$token]);
            $resetRecord = $stmt->fetch();

            if (!$resetRecord) {
                $error = "Token không hợp lệ hoặc đã hết hạn.";
            } else {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, last_password_reset = NOW() WHERE id = ?");
                $stmt->execute([$hashedPassword, $resetRecord['user_id']]);

                // Mark token as used
                $stmt = $pdo->prepare("UPDATE password_resets SET is_used = TRUE WHERE id = ?");
                $stmt->execute([$resetRecord['id']]);

                log_auth_attempt('password_reset', $resetRecord['user_id'], true, 'Password reset successful');
                $success = "Đặt lại mật khẩu thành công! Đang chuyển hướng...";
                
                // Clear old sessions for this user
                // In production, you might want to invalidate all sessions
                
                header("refresh:2;url=login.php");
            }
        }
    }
}

$pageTitle = "Quên mật khẩu | NHK Mobile";
$basePath = "";
include 'includes/header.php';
?>

<main class="min-vh-100 d-flex align-items-center justify-content-center bg-gray py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="auth-card bg-white p-5 rounded-4 shadow-lg border">
                    
                    <?php if ($step === 'request'): ?>
                        <!-- Step 1: Request Password Reset -->
                        <div class="text-center mb-5">
                            <div class="nav-icon bg-primary-light text-primary mx-auto mb-4" style="width: 64px; height: 64px; font-size: 32px;">
                                <i class="bi bi-envelope-lock"></i>
                            </div>
                            <h2 class="fw-800 mb-2">Quên mật khẩu?</h2>
                            <p class="text-secondary small fw-500">Nhập email để đặt lại mật khẩu</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 rounded-3 small fw-600 mb-4 auth-error"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success border-0 rounded-3 small fw-600 mb-4 auth-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form action="forgot-password.php?step=request" method="POST" id="forgotForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <div class="mb-4">
                                <label class="form-label small fw-800 text-muted text-uppercase letter-spacing">Email</label>
                                <input type="email" name="email" class="form-control bg-light border-0 py-3 px-4 rounded-3 auth-input" placeholder="example@email.com" required autocomplete="email">
                            </div>
                            <button type="submit" class="btn-main btn-primary w-100 py-3 mb-4 auth-btn">
                                <span class="btn-text">Gửi yêu cầu</span>
                                <span class="btn-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Đang xử lý...
                                </span>
                            </button>
                            <div class="text-center">
                                <p class="text-secondary small fw-500 mb-0">Quay lại <a href="login.php" class="text-primary fw-800 auth-link">Đăng nhập</a></p>
                            </div>
                        </form>

                    <?php elseif ($step === 'reset'): ?>
                        <!-- Step 2: Reset Password with Token -->
                        <div class="text-center mb-5">
                            <div class="nav-icon bg-primary-light text-primary mx-auto mb-4" style="width: 64px; height: 64px; font-size: 32px;">
                                <i class="bi bi-key"></i>
                            </div>
                            <h2 class="fw-800 mb-2">Đặt lại mật khẩu</h2>
                            <p class="text-secondary small fw-500">Tạo mật khẩu mới cho tài khoản</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 rounded-3 small fw-600 mb-4 auth-error"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success border-0 rounded-3 small fw-600 mb-4 auth-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <?php if (!$success): ?>
                            <form action="forgot-password.php?step=reset" method="POST" id="resetForm">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                
                                <div class="mb-4">
                                    <label class="form-label small fw-800 text-muted text-uppercase letter-spacing">Mật khẩu mới</label>
                                    <input type="password" name="new_password" id="newPassword" class="form-control bg-light border-0 py-3 px-4 rounded-3 auth-input" placeholder="••••••••" required autocomplete="new-password">
                                    <div class="password-strength mt-2" id="passwordStrength">
                                        <div class="strength-bar">
                                            <div class="strength-fill" id="strengthFill"></div>
                                        </div>
                                        <small class="strength-text" id="strengthText">Chưa nhập mật khẩu</small>
                                    </div>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label small fw-800 text-muted text-uppercase letter-spacing">Xác nhận mật khẩu</label>
                                    <input type="password" name="confirm_password" id="confirmPassword" class="form-control bg-light border-0 py-3 px-4 rounded-3 auth-input" placeholder="••••••••" required autocomplete="new-password">
                                </div>
                                <button type="submit" class="btn-main btn-primary w-100 py-3 mb-4 auth-btn">
                                    <span class="btn-text">Đặt lại mật khẩu</span>
                                    <span class="btn-loading d-none">
                                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                        Đang xử lý...
                                    </span>
                                </button>
                                <div class="text-center">
                                    <p class="text-secondary small fw-500 mb-0">Quay lại <a href="login.php" class="text-primary fw-800 auth-link">Đăng nhập</a></p>
                                </div>
                            </form>
                        <?php endif; ?>

                    <?php endif; ?>
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
    const newPassword = document.getElementById('newPassword');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');
    const passwordStrength = document.getElementById('passwordStrength');

    if (newPassword) {
        newPassword.addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            
            passwordStrength.className = 'password-strength strength-' + strength.level;
            strengthText.textContent = strength.text;
        });
    }

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

    // Form submission with loading state
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('.auth-btn');
            if (submitBtn && submitBtn.disabled) {
                e.preventDefault();
                return;
            }

            const btnText = form.querySelector('.btn-text');
            const btnLoading = form.querySelector('.btn-loading');
            
            if (btnText && btnLoading) {
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.7';
                }
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
