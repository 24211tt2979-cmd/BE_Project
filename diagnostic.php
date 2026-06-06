<?php
/**
 * NHK Mobile - Quick System Diagnostic
 * Run this file to verify all components are working
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NHK Mobile - System Diagnostic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f7; padding: 40px 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #1d1d1f; margin-bottom: 10px; font-size: 2.5rem; }
        .subtitle { color: #6e6e73; margin-bottom: 40px; font-size: 1.1rem; }
        .test-card { background: #fff; border-radius: 16px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .test-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 12px; }
        .status { padding: 4px 12px; border-radius: 980px; font-size: 0.85rem; font-weight: 600; }
        .status.pass { background: #d4edda; color: #155724; }
        .status.fail { background: #f8d7da; color: #721c24; }
        .status.warn { background: #fff3cd; color: #856404; }
        .detail { color: #6e6e73; font-size: 0.9rem; line-height: 1.6; margin-top: 8px; }
        .detail code { background: #f5f5f7; padding: 2px 6px; border-radius: 4px; font-size: 0.85rem; }
        .summary { background: #fff; border-radius: 16px; padding: 32px; margin-top: 40px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
        .summary h2 { margin-bottom: 20px; }
        .progress-bar { background: #e9ecef; border-radius: 980px; height: 24px; overflow: hidden; margin: 16px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.5s ease; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 0.85rem; }
        .action-btn { display: inline-block; padding: 12px 24px; background: #007AFF; color: #fff; text-decoration: none; border-radius: 980px; font-weight: 600; margin-top: 16px; transition: all 0.3s; }
        .action-btn:hover { background: #0056b3; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 System Diagnostic</h1>
        <p class="subtitle">NHK Mobile - Comprehensive System Check</p>

        <?php
        $pass = 0;
        $fail = 0;
        $warn = 0;
        $total = 0;

        function testResult($name, $condition, $detail = '') {
            global $pass, $fail, $warn, $total;
            $total++;
            
            if ($condition === true) {
                $pass++;
                $status = 'pass';
                $label = '✓ PASS';
            } elseif ($condition === false) {
                $fail++;
                $status = 'fail';
                $label = '✗ FAIL';
            } else {
                $warn++;
                $status = 'warn';
                $label = '⚠ WARN';
            }

            echo "<div class='test-card'>";
            echo "<div class='test-title'>{$name} <span class='status {$status}'>{$label}</span></div>";
            if ($detail) {
                echo "<div class='detail'>{$detail}</div>";
            }
            echo "</div>";
        }

        // Test 1: PHP Version
        testResult(
            'PHP Version',
            version_compare(PHP_VERSION, '8.0.0', '>='),
            'Current: <code>' . PHP_VERSION . '</code> (Required: 8.0+)'
        );

        // Test 2: Database Connection
        try {
            require_once 'includes/db.php';
            if ($connected && $pdo) {
                $pdo->query("SELECT 1");
                testResult('Database Connection', true, 'MySQL connection successful');
            } else {
                testResult('Database Connection', false, 'Failed to connect to database');
            }
        } catch (Exception $e) {
            testResult('Database Connection', false, 'Error: ' . htmlspecialchars($e->getMessage()));
        }

        // Test 3: Database Tables
        if (isset($pdo) && $pdo) {
            $required_tables = ['users', 'admins', 'products', 'orders', 'order_items', 'cart_items', 'reviews', 'warranties', 'news', 'imeis'];
            $missing_tables = [];
            try {
                $stmtTable = $pdo->query("SHOW TABLES");
                $existing_tables = $stmtTable->fetchAll(PDO::FETCH_COLUMN);
                foreach ($required_tables as $table) {
                    if (!in_array($table, $existing_tables, true)) {
                        $missing_tables[] = $table;
                    }
                }
            } catch (Exception $e) {
                $missing_tables = $required_tables;
            }

            testResult(
                'Database Tables',
                empty($missing_tables),
                empty($missing_tables) 
                    ? 'All required tables exist' 
                    : 'Missing: <code>' . implode(', ', $missing_tables) . '</code>'
            );
        }

        // Test 4: Admin Account
        if (isset($pdo) && $pdo) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
                $adminCount = $stmt->fetchColumn();
                testResult(
                    'Admin Account',
                    $adminCount > 0,
                    $adminCount > 0 
                        ? "Found {$adminCount} admin account(s)" 
                        : 'No admin accounts found. Run init_db.sql'
                );
            } catch (Exception $e) {
                testResult('Admin Account', false, 'Error checking admins table');
            }
        }

        // Test 5: Products
        if (isset($pdo) && $pdo) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM products");
                $productCount = $stmt->fetchColumn();
                testResult(
                    'Product Catalog',
                    $productCount > 0,
                    $productCount > 0 
                        ? "{$productCount} products in catalog" 
                        : 'No products found. Run init_db.sql'
                );
            } catch (Exception $e) {
                testResult('Product Catalog', false, 'Error checking products table');
            }
        }

        // Test 6: Session Support
        testResult(
            'Session Support',
            function_exists('session_start'),
            'Session functions available: <code>' . (function_exists('session_start') ? 'Yes' : 'No') . '</code>'
        );

        // Test 7: PDO Extension
        testResult(
            'PDO MySQL Extension',
            extension_loaded('pdo_mysql'),
            'Extension loaded: <code>' . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . '</code>'
        );

        // Test 8: GD/Image Support
        $hasGd = extension_loaded('gd');
        $hasImagick = extension_loaded('imagick');
        testResult(
            'Image Processing',
            $hasGd || $hasImagick,
            ($hasGd ? 'GD Library ✓' : '') . ($hasImagick ? ' ImageMagick ✓' : '') . (!$hasGd && !$hasImagick ? 'No image library found' : '')
        );

        // Test 9: File Permissions
        $logDir = __DIR__ . '/logs';
        $logWritable = is_writable($logDir);
        testResult(
            'Log Directory Writable',
            $logWritable,
            'Path: <code>' . $logDir . '</code> - ' . ($logWritable ? 'Writable ✓' : 'Not writable ✗')
        );

        // Test 10: CSS File
        $cssExists = file_exists(__DIR__ . '/assets/css/style.css');
        testResult(
            'CSS Assets',
            $cssExists,
            'Style sheet: ' . ($cssExists ? 'Found ✓' : 'Missing ✗')
        );

        // Test 11: Authentication Functions
        require_once 'includes/auth_functions.php';
        testResult(
            'Authentication System',
            function_exists('require_login') && function_exists('require_admin'),
            'Auth functions loaded: <code>' . (function_exists('require_login') ? 'Yes' : 'No') . '</code>'
        );

        // Test 12: Cart Functions
        require_once 'includes/cart_functions.php';
        testResult(
            'Cart System',
            function_exists('syncCartWithDatabase'),
            'Cart functions: <code>' . (function_exists('syncCartWithDatabase') ? 'Loaded ✓' : 'Missing ✗') . '</code>'
        );

        // Test 13: PayOS Webhook Endpoint
        $payosWebhookExists = file_exists(__DIR__ . '/api/payos_webhook.php');
        testResult(
            'PayOS Webhook Endpoint',
            $payosWebhookExists,
            'Endpoint file: ' . ($payosWebhookExists ? 'Available ✓' : 'Missing ✗')
        );

        // Test 14: Installment Calculator Widget
        $detailPage = @file_get_contents(__DIR__ . '/product-detail.php');
        $hasInstallmentCalc = ($detailPage !== false && str_contains($detailPage, 'installment-calc-card'));
        testResult(
            'Installment Calculator Widget',
            $hasInstallmentCalc,
            'Calculator code integration on product-detail.php: ' . ($hasInstallmentCalc ? 'Detected ✓' : 'Not found ✗')
        );

        // Summary
        $percentage = round(($pass / $total) * 100);
        ?>

        <div class="summary">
            <h2>📊 Test Summary</h2>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $percentage; ?>%;">
                    <?php echo $percentage; ?>%
                </div>
            </div>
            <p style="margin-bottom: 8px;"><strong>Total Tests:</strong> <?php echo $total; ?></p>
            <p style="margin-bottom: 8px; color: #28a745;"><strong>✓ Passed:</strong> <?php echo $pass; ?></p>
            <p style="margin-bottom: 8px; color: #dc3545;"><strong>✗ Failed:</strong> <?php echo $fail; ?></p>
            <p style="margin-bottom: 16px; color: #ffc107;"><strong>⚠ Warnings:</strong> <?php echo $warn; ?></p>

            <?php if ($fail === 0): ?>
                <div style="background: #d4edda; padding: 20px; border-radius: 12px; margin-top: 20px;">
                    <h3 style="color: #155724; margin-bottom: 8px;">🎉 System Ready!</h3>
                    <p style="color: #155724;">All critical tests passed. Your NHK Mobile system is operational.</p>
                </div>

                <div style="margin-top: 24px;">
                    <h4 style="margin-bottom: 12px;">Quick Links:</h4>
                    <a href="index.php" class="action-btn" style="margin-right: 12px;">🏠 Homepage</a>
                    <a href="login.php" class="action-btn" style="background: #28a745;">🔐 Login (admin/admin123)</a>
                    <a href="product.php" class="action-btn" style="background: #6c757d;">📱 Products</a>
                    <a href="check.php" class="action-btn" style="background: #17a2b8;">🔍 Full System Check</a>
                </div>
            <?php else: ?>
                <div style="background: #f8d7da; padding: 20px; border-radius: 12px; margin-top: 20px;">
                    <h3 style="color: #721c24; margin-bottom: 8px;">⚠️ Action Required</h3>
                    <p style="color: #721c24;"><?php echo $fail; ?> test(s) failed. Please review the errors above and fix them before proceeding.</p>
                    <p style="color: #721c24; margin-top: 8px;">Common fixes:</p>
                    <ul style="color: #721c24; margin-left: 20px; margin-top: 8px;">
                        <li>Check database credentials in <code>includes/db.php</code></li>
                        <li>Run <code>php/config/init_db.sql</code> to initialize database</li>
                        <li>Verify PHP extensions are enabled</li>
                        <li>Check file permissions on logs directory</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 40px; color: #6e6e73; font-size: 0.9rem;">
            <p>NHK Mobile Diagnostic Tool v2.0 | <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>
