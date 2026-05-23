<?php
/**
 * Tệp kiểm soát quyền Admin
 * Nhúng tệp này vào đầu tất cả các tệp trong thư mục admin/
 */
require_once __DIR__ . '/../includes/auth_functions.php';

// Yêu cầu quyền Admin
require_admin();
?>
