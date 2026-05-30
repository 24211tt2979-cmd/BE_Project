<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo $pageTitle ?? 'Admin Dashboard'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">
</head>
<body>

    <header class="app-top-header">
        <div class="d-flex align-items-center">
            <button class="app-menu-btn me-3" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="app-brand">
                <div class="brand-logo-box sm me-2">NHK</div>
                <span class="brand-text">ADMIN</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="<?php echo $basePath; ?>index.php" class="app-icon-btn" title="Xem Website"><i class="bi bi-globe"></i></a>
            <a href="<?php echo $basePath; ?>logout.php" class="app-icon-btn text-danger" title="Dang xuat"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </header>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebarMenu">
        <div class="sidebar-header">
            <div class="d-flex align-items-center">
                <div class="brand-logo-box md me-3">NHK</div>
                <div>
                    <div class="brand-text md">NHK ADMIN</div>
                    <small class="text-muted">Quan ly he thong</small>
                </div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link-admin <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="bi bi-speedometer2"></i> Tong quan</a>
            <a href="products.php" class="nav-link-admin <?php echo $current_page == 'products.php' ? 'active' : ''; ?>"><i class="bi bi-phone"></i> San pham</a>
            <a href="categories.php" class="nav-link-admin <?php echo $current_page == 'categories.php' ? 'active' : ''; ?>"><i class="bi bi-tags"></i> Danh muc</a>
            <a href="orders.php" class="nav-link-admin <?php echo $current_page == 'orders.php' ? 'active' : ''; ?>"><i class="bi bi-receipt"></i> Don hang</a>
            <a href="users.php" class="nav-link-admin <?php echo $current_page == 'users.php' ? 'active' : ''; ?>"><i class="bi bi-people"></i> Khach hang</a>
            <a href="revenue.php" class="nav-link-admin <?php echo $current_page == 'revenue.php' ? 'active' : ''; ?>"><i class="bi bi-graph-up"></i> Doanh thu</a>
            <a href="settings.php" class="nav-link-admin <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class="bi bi-gear"></i> Cau hinh</a>
        </nav>
        <div class="sidebar-footer"></div>
    </aside>

    <main class="main-content">
