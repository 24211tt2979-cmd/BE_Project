<?php
/**
 * NHK Mobile - Unified Authentication Services (Enhanced Security)
 *
 * Description: Manages security sessions, role-based access control (RBAC),
 * CSRF protection, rate limiting, and persistent login states.
 *
 * Author: NguyenHuuKhanh
 * Version: 3.0 (Security Enhanced)
 * Date: 2026-04-15
 */

// Đảm bảo session luôn được khởi tạo với cấu hình bảo mật cao
if (session_status() === PHP_SESSION_NONE) {
    // Cấu hình session.save_path: Chỉ dùng /tmp trên Linux (Render), không dùng trên Windows (WAMP)
    if (DIRECTORY_SEPARATOR === '/') {
        ini_set('session.save_path', '/tmp');
    }
    ini_set('session.gc_maxlifetime', 604800);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);

    session_set_cookie_params([
        'lifetime' => 604800,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax' // Thay đổi từ Strict sang Lax cho redirect
    ]);
    session_start();

    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Generate CSRF Token
 * @return string CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF Token
 * @param string $token Token to validate
 * @return bool True if valid
 */
function validate_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    // Token expires after 1 hour
    if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time'] > 3600)) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Clear CSRF Token after use
 */
function clear_csrf_token() {
    unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
}

/**
 * Rate Limiting Check
 * @param string $action Action name (e.g., 'login', 'register')
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $timeWindow Time window in seconds
 * @return bool True if allowed, false if rate limited
 */
function check_rate_limit($action, $maxAttempts = 5, $timeWindow = 300) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rate_limit_' . $action . '_' . md5($ip);

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 1,
            'first_attempt' => time()
        ];
        return true;
    }

    $data = $_SESSION[$key];

    // Reset if time window has passed
    if (time() - $data['first_attempt'] > $timeWindow) {
        $_SESSION[$key] = [
            'attempts' => 1,
            'first_attempt' => time()
        ];
        return true;
    }

    // Check if exceeded max attempts
    if ($data['attempts'] >= $maxAttempts) {
        return false;
    }

    // Increment attempts
    $_SESSION[$key]['attempts']++;
    return true;
}

/**
 * Get remaining time for rate limit
 * @param string $action Action name
 * @param int $timeWindow Time window in seconds
 * @return int Remaining seconds
 */
function get_rate_limit_remaining($action, $timeWindow = 300) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rate_limit_' . $action . '_' . md5($ip);

    if (!isset($_SESSION[$key])) {
        return 0;
    }

    $data = $_SESSION[$key];
    $elapsed = time() - $data['first_attempt'];
    return max(0, $timeWindow - $elapsed);
}

/**
 * Clear rate limit for an action
 * @param string $action Action name
 */
function clear_rate_limit($action) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rate_limit_' . $action . '_' . md5($ip);
    unset($_SESSION[$key]);
}

/**
 * Log authentication attempt
 * @param string $action Action performed
 * @param string $email Email/username
 * @param bool $success Whether the attempt was successful
 * @param string $details Additional details
 */
function log_auth_attempt($action, $email, $success, $details = '') {
    // Skip logging on production environments where filesystem may be read-only
    $logFile = __DIR__ . '/../logs/auth.log';
    $logDir = dirname($logFile);

    // Try to create logs directory, but don't fail if it can't be created
    try {
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        // Only log if directory is writable
        if (is_writable($logDir)) {
            $timestamp = date('Y-m-d H:i:s');
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $status = $success ? 'SUCCESS' : 'FAILED';

            $logEntry = sprintf(
                "[%s] [%s] Action: %s | Email: %s | IP: %s | Status: %s | Details: %s | UA: %s" . PHP_EOL,
                $timestamp,
                $status,
                $action,
                $email,
                $ip,
                $status,
                $details,
                substr($userAgent, 0, 100)
            );

            @error_log($logEntry, 3, $logFile);
        }
    } catch (\Exception $e) {
        // Silently fail - logging is not critical
        error_log("Auth attempt (no file logging): $action | $email | " . ($success ? 'SUCCESS' : 'FAILED'));
    }
}

/**
 * Validate email format
 * @param string $email Email to validate
 * @return bool True if valid
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'errors' => array]
 */
function validate_password_strength($password) {
    $errors = [];
    $minLength = 8;

    if (strlen($password) < $minLength) {
        $errors[] = "Mật khẩu phải có ít nhất {$minLength} ký tự";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Mật khẩu phải chứa ít nhất 1 chữ hoa";
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Mật khẩu phải chứa ít nhất 1 chữ thường";
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Mật khẩu phải chứa ít nhất 1 số";
    }

    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = "Mật khẩu phải chứa ít nhất 1 ký tự đặc biệt";
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Validate Phone Number (Exactly 10 digits)
 * @param string $phone
 * @return bool
 */
function is_valid_phone($phone) {
    // Xóa các ký tự không phải số
    $phone = preg_replace('/\D/', '', $phone);
    return preg_match('/^[0-9]{10}$/', $phone);
}

/**
 * Sanitize User Input
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitize_input($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Kiểm tra trạng thái đăng nhập tổng quát
 *
 * @return bool Trả về true nếu là Thành viên hoặc Admin đã đăng nhập
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
}

/**
 * Kiểm tra xem ngườii đang truy cập có quyền Admin hay không
 *
 * @return bool Trả về true nếu là Admin
 */
function is_admin() {
    return isset($_SESSION['admin_id']);
}

/**
 * Lấy ID của ngườii dùng (Thành viên) đang đăng nhập
 *
 * @return int|null Trả về ID ngườii dùng (số nguyên) hoặc null nếu chưa đăng nhập
 */
function get_logged_in_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Lấy tên hiển thị của ngườii đang đăng nhập
 * Ưu tiên hiển thị tên Admin nếu là Admin, ngược lại hiện tên Thành viên.
 *
 * @return string Tên ngườii dùng hoặc "Khách" nếu chưa đăng nhập
 */
function get_logged_in_name() {
    if (isset($_SESSION['admin_id'])) {
        return "Admin: " . ($_SESSION['admin_user'] ?? 'Quản trị viên');
    }
    return $_SESSION['user_fullname'] ?? 'Khách';
}

/**
 * Hàm bảo vệ trang cá nhân: Bắt buộc đăng nhập Thành viên hoặc Admin
 * Nếu chưa đăng nhập, tự động chuyển hướng sang trang login và lưu vị trí cũ.
 */
function require_login() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
        header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Hàm bảo vệ trang Admin: Chỉ cho phép tài khoản Admin truy cập
 * Nếu không có quyền Admin, chuyển hướng về login kèm thông báo lỗi.
 */
function require_admin() {
    if (!isset($_SESSION['admin_id'])) {
        // Xác định đường dẫn về login.php dựa trên vị trí file hiện tại
        // Nếu đang ở trong thư mục admin/ thì quay ra ngoài 1 cấp
        $is_in_admin = str_contains($_SERVER['REQUEST_URI'], '/admin/');
        $login_url = ($is_in_admin ? '../' : '') . 'login.php?error=no_admin';
        header("Location: " . $login_url);
        exit;
    }
}

/**
 * Set secure session data
 * @param string $key Session key
 * @param mixed $value Session value
 */
function set_secure_session($key, $value) {
    $_SESSION[$key] = $value;
    $_SESSION['last_activity'] = time();
}

/**
 * Check session timeout
 * @param int $timeout Timeout in seconds (default 30 minutes)
 * @return bool True if session is valid
 */
function check_session_timeout($timeout = 1800) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        // Session expired
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}
?>
