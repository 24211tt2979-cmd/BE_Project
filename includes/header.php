<?php
/**
 * NHK Mobile - Global Header System
 * 
 * Description: Handles site-wide meta tags, navigation building, 
 * session detection, and the premium mobile drawer system.
 * 
 * Author: NguyenHuuKhanh
 * Version: 2.5
 * Date: 2026-04-08
 */

// Kiểm tra trạng thái tài khoản nếu user đang đăng nhập
if (isset($_SESSION['user_id']) && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userStatus = $stmt->fetchColumn();
    
    if ($userStatus === 'banned') {
        session_unset();
        session_destroy();
        $bPath = isset($basePath) ? $basePath : '';
        header("Location: " . $bPath . "login.php?error=banned");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'NHK Mobile | Premium Tech Store'; ?></title>
    <meta name="description" content="NHK Mobile - Chuyên cung cấp iPhone, Samsung và các thiết bị công nghệ chính hãng. Trải nghiệm mua sắm 5 sao, bảo hành tin cậy tại NHK Mobile.">
    <meta name="keywords" content="nhk mobile, iphone 17, điện thoại chính hãng, apple authorized reseller, mua iphone trả góp">
    <meta name="author" content="NHK Mobile Team">
    
    <!-- Bootstrap 5 (Chỉ dùng Grid và một số Utility) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/hero-new.css?v=<?php echo time(); ?>">
    
    <!-- Auth Animation Styles + Toast Notification -->
    <style>
    /* ── Toast Notification ── */
    .toast-container {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
    }

    .toast {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        border-radius: 14px;
        font-size: 0.88rem;
        font-weight: 600;
        min-width: 280px;
        max-width: 360px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        animation: toastSlideIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        pointer-events: all;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }

    .toast.success {
        background: rgba(52, 199, 89, 0.95);
        color: #fff;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .toast.error {
        background: rgba(255, 59, 48, 0.95);
        color: #fff;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .toast.warning {
        background: rgba(255, 149, 0, 0.95);
        color: #fff;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .toast.info {
        background: rgba(0, 122, 255, 0.95);
        color: #fff;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .toast i {
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .toast span { line-height: 1.4; }

    .toast.hide {
        animation: toastSlideOut 0.3s ease forwards;
    }

    @keyframes toastSlideIn {
        from { opacity: 0; transform: translateX(120px) scale(0.9); }
        to   { opacity: 1; transform: translateX(0) scale(1); }
    }

    @keyframes toastSlideOut {
        from { opacity: 1; transform: translateX(0) scale(1); }
        to   { opacity: 0; transform: translateX(120px) scale(0.9); }
    }

    @media (max-width: 480px) {
        .toast-container { top: 70px; right: 10px; left: 10px; }
        .toast { min-width: auto; max-width: 100%; }
    }
    </style>

    <style>
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    .auth-card {
        animation: slideUp 0.6s ease-out forwards;
    }
    
    .auth-input {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
        color: #0056b3 !important;
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
    
    /* Icon animations */
    .nav-icon {
        transition: all 0.2s ease;
    }
    
    .nav-icon:hover {
        transform: scale(1.1);
    }
    
    .nav-icon:active {
        transform: scale(0.95);
    }
    
    /* Button improvements */
    .btn {
        transition: all 0.2s ease;
    }
    
    .btn:hover {
        transform: translateY(-1px);
    }
    
    .btn:active {
        transform: translateY(0);
    }
    
    /* Smooth scroll */
    html {
        scroll-behavior: smooth;
    }

    /* Mini Cart Dropdown */
    .mini-cart-wrapper {
        position: relative;
    }

    .mini-cart-dropdown {
        position: absolute;
        top: calc(100% + 12px);
        right: 0;
        width: 380px;
        background: #fff;
        border-radius: var(--radius-lg);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        border: 1px solid var(--border-light);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1001;
    }

    .mini-cart-wrapper:hover .mini-cart-dropdown,
    .mini-cart-dropdown.active {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    /* Responsive mini-cart: màn hình nhỏ hơn 576px */
    @media (max-width: 575.98px) {
        .mini-cart-dropdown {
            position: fixed;
            top: 64px;
            left: 50%;
            right: auto;
            transform: translateX(-50%) translateY(-10px);
            width: 92vw;
            max-width: 400px;
        }
        .mini-cart-wrapper:hover .mini-cart-dropdown,
        .mini-cart-dropdown.active {
            transform: translateX(-50%) translateY(0);
        }
    }

    .mini-cart-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .mini-cart-header h5 {
        font-size: 16px;
        font-weight: 700;
        margin: 0;
    }

    .mini-cart-items {
        max-height: 320px;
        overflow-y: auto;
        padding: 16px;
    }

    .mini-cart-item {
        display: flex;
        gap: 16px;
        padding: 12px;
        border-radius: var(--radius-md);
        transition: all 0.2s;
    }

    .mini-cart-item:hover {
        background: var(--bg-soft);
    }

    .mini-cart-item img {
        width: 64px;
        height: 64px;
        object-fit: cover;
        border-radius: var(--radius-sm);
        background: var(--bg-gray);
    }

    .mini-cart-item-info {
        flex: 1;
    }

    .mini-cart-item-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-main);
        margin-bottom: 4px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .mini-cart-item-price {
        font-size: 14px;
        font-weight: 700;
        color: var(--primary);
    }

    .mini-cart-item-qty {
        font-size: 12px;
        color: var(--text-muted);
    }

    .mini-cart-footer {
        padding: 20px 24px;
        border-top: 1px solid var(--border-light);
        background: var(--bg-soft);
        border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    }

    .mini-cart-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .mini-cart-total span {
        font-size: 14px;
        color: var(--text-secondary);
    }

    .mini-cart-total strong {
        font-size: 18px;
        color: var(--text-main);
    }

    .mini-cart-empty {
        text-align: center;
        padding: 40px 24px;
    }

    .mini-cart-empty i {
        font-size: 48px;
        color: var(--border-light);
        margin-bottom: 16px;
    }

    .mini-cart-empty p {
        color: var(--text-muted);
        margin: 0;
    }

    /* Dark Mode Toggle */
    .dark-mode-toggle {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: none;
        background: transparent;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: var(--text-main);
        transition: all 0.3s ease;
    }

    .dark-mode-toggle:hover {
        background: rgba(0, 122, 255, 0.1);
        color: var(--primary);
    }

    /* =============================================
       DARK MODE - FULL COVERAGE
       ============================================= */
    body.dark-mode {
        color-scheme: dark;
        --bg-white:      #0d0d0d;
        --bg-soft:       #161616;
        --bg-gray:       #252525;
        --text-main:     #f0f0f0;
        --text-secondary:#a0a0a0;
        --text-muted:    #6e6e73;
        --border-light:  rgba(255, 255, 255, 0.09);
        --shadow-sm:     0 4px 12px rgba(0,0,0,0.4);
        --shadow-md:     0 8px 30px rgba(0,0,0,0.5);
        --shadow-lg:     0 20px 60px rgba(0,0,0,0.6);
        --glass-bg:      rgba(20, 20, 20, 0.85);
        --glass-border:  rgba(255, 255, 255, 0.08);
    }

    /* Base */
    body.dark-mode { background-color: #0d0d0d; color: #f0f0f0; }

    /* Navbar */
    body.dark-mode .navbar-minimal {
        background: rgba(13, 13, 13, 0.88) !important;
        border-bottom-color: rgba(255, 255, 255, 0.08);
    }
    body.dark-mode .navbar-minimal.scrolled {
        background: rgba(13, 13, 13, 0.97) !important;
    }

    /* Breadcrumb */
    body.dark-mode .breadcrumb-section {
        background: #161616;
        border-bottom-color: rgba(255,255,255,0.08);
    }

    /* Product Cards */
    body.dark-mode .product-card-new {
        background: #1a1a1a;
        border-color: rgba(255,255,255,0.08);
    }
    body.dark-mode .product-img-box {
        background: #252525;
    }
    body.dark-mode .btn-quick-view {
        background: rgba(30,30,30,0.95);
        color: #f0f0f0;
    }
    body.dark-mode .btn-quick-view:hover {
        background: var(--primary);
        color: #fff;
    }

    /* Category Section */
    body.dark-mode .category-section { background: #161616; }
    body.dark-mode .category-item {
        background: #1a1a1a;
        border-color: rgba(255,255,255,0.08);
        color: #f0f0f0;
    }
    body.dark-mode .category-name { color: #f0f0f0; }

    /* Feature Section */
    body.dark-mode .features-new { background: #161616; }
    body.dark-mode .feature-item {
        background: #1a1a1a;
        border-color: rgba(255,255,255,0.08);
    }
    body.dark-mode .feature-icon { background: #252525; }

    /* Testimonials */
    body.dark-mode .testimonial-section { background: #0d0d0d; }
    body.dark-mode .testimonial-card {
        background: #1a1a1a;
        border-color: rgba(255,255,255,0.08);
    }

    /* Newsletter (already dark, keep coherent) */
    body.dark-mode .newsletter-section { background: #111; }

    /* Footer */
    body.dark-mode .footer-new {
        background: #111 !important;
        border-top-color: rgba(255,255,255,0.08) !important;
    }
    body.dark-mode .footer-bottom-new {
        border-top-color: rgba(255,255,255,0.08) !important;
    }
    body.dark-mode .footer-links a { color: #a0a0a0; }
    body.dark-mode .footer-links a:hover { color: var(--primary); }
    body.dark-mode .social-icon {
        background: #252525 !important;
        border-color: rgba(255,255,255,0.08) !important;
        color: #f0f0f0 !important;
    }
    body.dark-mode .contact-info { color: #a0a0a0; }

    /* Filters on product.php */
    body.dark-mode .btn-filter {
        background: #1a1a1a;
        border-color: rgba(255,255,255,0.08);
        color: #a0a0a0;
    }
    body.dark-mode .btn-filter:hover {
        background: #252525;
        border-color: var(--primary);
        color: var(--primary);
    }
    body.dark-mode .btn-filter.active {
        background: #f0f0f0;
        border-color: #f0f0f0;
        color: #0d0d0d;
    }
    body.dark-mode .advanced-filter { background: #161616; }
    body.dark-mode .filter-chip {
        background: #1a1a1a;
        border-color: rgba(255,255,255,0.08);
        color: #a0a0a0;
    }
    body.dark-mode .filter-chip:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    body.dark-mode .filter-chip.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }

    /* Pagination */
    body.dark-mode .pagination-btn {
        background: #1a1a1a;
        border-color: rgba(255,255,255,0.08);
        color: #a0a0a0;
    }
    body.dark-mode .pagination-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    body.dark-mode .pagination-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }

    /* Sort dropdown */
    body.dark-mode .form-select {
        background-color: #1a1a1a !important;
        color: #f0f0f0 !important;
        border-color: rgba(255,255,255,0.12) !important;
    }

    /* Cart Page */
    body.dark-mode .cart-item,
    body.dark-mode .cart-summary,
    body.dark-mode table,
    body.dark-mode .table,
    body.dark-mode .card {
        background: #1a1a1a !important;
        color: #f0f0f0 !important;
        border-color: rgba(255,255,255,0.08) !important;
    }
    body.dark-mode .table thead th {
        background: #252525 !important;
        color: #a0a0a0 !important;
        border-color: rgba(255,255,255,0.08) !important;
    }
    body.dark-mode .table td, body.dark-mode .table th {
        border-color: rgba(255,255,255,0.06) !important;
        color: #f0f0f0 !important;
    }
    body.dark-mode .table-striped > tbody > tr:nth-of-type(odd) {
        background-color: rgba(255,255,255,0.03) !important;
    }

    /* Bootstrap Inputs & Textareas */
    body.dark-mode input,
    body.dark-mode textarea,
    body.dark-mode .form-control,
    body.dark-mode .form-control:focus {
        background-color: #1a1a1a !important;
        color: #f0f0f0 !important;
        border-color: rgba(255,255,255,0.12) !important;
    }
    body.dark-mode input::placeholder,
    body.dark-mode textarea::placeholder { color: #6e6e73 !important; }
    body.dark-mode .hero-search-input {
        background: rgba(30,30,30,0.9) !important;
        color: #f0f0f0 !important;
        border-color: rgba(255,255,255,0.15) !important;
    }
    body.dark-mode .hero-search-input::placeholder { color: #6e6e73 !important; }

    /* Mini Cart Dropdown */
    body.dark-mode .mini-cart-dropdown {
        background: #1a1a1a;
        border-color: rgba(255,255,255,0.08);
        box-shadow: 0 20px 60px rgba(0,0,0,0.6);
    }
    body.dark-mode .mini-cart-header { border-bottom-color: rgba(255,255,255,0.08); }
    body.dark-mode .mini-cart-item:hover { background: #252525; }
    body.dark-mode .mini-cart-footer {
        background: #111;
        border-top-color: rgba(255,255,255,0.08);
    }
    body.dark-mode .mini-cart-item img { background: #252525; }

    /* Sticky Cart Bar */
    body.dark-mode .sticky-cart-bar {
        background: #1a1a1a;
        border-top-color: rgba(255,255,255,0.08);
    }

    /* Quick View Modal */
    body.dark-mode .quick-view-content { background: #1a1a1a; }
    body.dark-mode .quick-view-image { background: #252525; }
    body.dark-mode .quick-view-close {
        background: rgba(255,255,255,0.1);
        color: #f0f0f0;
    }
    body.dark-mode .quick-view-close:hover { background: var(--primary); color: #fff; }
    body.dark-mode .quick-view-quantity {
        border-color: rgba(255,255,255,0.12);
    }
    body.dark-mode .quick-view-quantity button { background: #252525; color: #f0f0f0; }
    body.dark-mode .quick-view-quantity input {
        background: #1a1a1a;
        color: #f0f0f0;
    }
    body.dark-mode .product-tabs-nav { border-bottom-color: rgba(255,255,255,0.08); }
    body.dark-mode .product-tab-btn { color: #a0a0a0; }
    body.dark-mode .product-tab-btn:hover { color: var(--primary); }
    body.dark-mode .product-tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
    body.dark-mode .specs-table tr { border-bottom-color: rgba(255,255,255,0.08); }

    /* Live Chat */
    body.dark-mode .live-chat-window { background: #1a1a1a; }
    body.dark-mode .live-chat-body { background: #161616; }
    body.dark-mode .chat-message:not(.user) .chat-bubble { background: #252525; color: #f0f0f0; }
    body.dark-mode .live-chat-footer {
        background: #1a1a1a;
        border-top-color: rgba(255,255,255,0.08);
    }
    body.dark-mode .live-chat-input {
        background: #252525 !important;
        border-color: rgba(255,255,255,0.1) !important;
        color: #f0f0f0 !important;
    }

    /* Back to top */
    body.dark-mode .back-to-top {
        background: #1a1a1a;
        border-color: rgba(255,255,255,0.1);
        color: #f0f0f0;
    }

    /* Carousel Arrows */
    body.dark-mode .carousel-arrow {
        background: rgba(30,30,30,0.9);
        border-color: rgba(255,255,255,0.1);
        color: #f0f0f0;
    }
    body.dark-mode .carousel-dot { background: rgba(255,255,255,0.2); }

    /* Mobile Offcanvas Drawers */
    body.dark-mode #mobileNav,
    body.dark-mode .offcanvas {
        background: #111 !important;
        color: #f0f0f0 !important;
    }
    body.dark-mode .offcanvas-header { border-bottom-color: rgba(255,255,255,0.08) !important; }
    body.dark-mode .offcanvas-body { color: #f0f0f0; }
    body.dark-mode #mobileNav .list-group-item {
        background: transparent !important;
        color: #f0f0f0 !important;
        border-color: transparent !important;
    }
    body.dark-mode #mobileNav .list-group-item:hover { background: #1a1a1a !important; }
    body.dark-mode .offcanvas .p-4.rounded-4 { background: #1a1a1a !important; }
    body.dark-mode .border-top { border-top-color: rgba(255,255,255,0.08) !important; }
    body.dark-mode .border-bottom { border-bottom-color: rgba(255,255,255,0.08) !important; }

    /* Account offcanvas user section */
    body.dark-mode .offcanvas [style*="background:#fff"] { background: #1a1a1a !important; }
    body.dark-mode .bg-light { background: #252525 !important; }
    body.dark-mode .text-muted { color: #a0a0a0 !important; }
    body.dark-mode .text-muted.small { color: #6e6e73 !important; }
    body.dark-mode .btn-close { filter: invert(1); }

    /* Bootstrap btn overrides */
    body.dark-mode .btn-outline-dark {
        color: #f0f0f0 !important;
        border-color: rgba(255,255,255,0.25) !important;
    }
    body.dark-mode .btn-outline-dark:hover {
        background-color: #f0f0f0 !important;
        color: #0d0d0d !important;
    }
    body.dark-mode .btn-dark {
        background-color: #f0f0f0 !important;
        border-color: #f0f0f0 !important;
        color: #0d0d0d !important;
    }
    body.dark-mode .btn-dark:hover {
        background-color: #d0d0d0 !important;
    }

    /* Product Detail */
    body.dark-mode .product-gallery-main { background: #252525; }
    body.dark-mode .gallery-thumb { background: #252525; border-color: rgba(255,255,255,0.1); }
    body.dark-mode .gallery-thumb:hover, body.dark-mode .gallery-thumb.active { border-color: var(--primary); }
    body.dark-mode .recently-viewed { background: #161616; }
    body.dark-mode .recently-viewed-item { background: #1a1a1a; border-color: rgba(255,255,255,0.08); }
    body.dark-mode .review-item { border-bottom-color: rgba(255,255,255,0.08); }

    /* Section backgrounds */
    body.dark-mode .products-section,
    body.dark-mode section { background-color: transparent; }
    body.dark-mode .hero-slide-bg.gradient-1 {
        background: radial-gradient(circle at 80% 20%, #0d2a45 0%, #0d0d0d 50%);
    }
    body.dark-mode .hero-slide-bg.gradient-2 {
        background: radial-gradient(circle at 20% 80%, #2a0d20 0%, #0d0d0d 50%);
    }
    body.dark-mode .hero-slide-bg.gradient-3 {
        background: radial-gradient(circle at 50% 50%, #1a2a0d 0%, #0d0d0d 50%);
    }

    /* Auth pages & other standalone pages */
    body.dark-mode .auth-card { background: #1a1a1a !important; border-color: rgba(255,255,255,0.08) !important; }
    body.dark-mode .auth-input { background: #252525 !important; color: #f0f0f0 !important; border-color: rgba(255,255,255,0.12) !important; }
    body.dark-mode .auth-input::placeholder { color: #6e6e73 !important; }
    body.dark-mode .strength-bar { background: #252525; }

    /* ── Logo / Brand Box ── */
    body.dark-mode .brand-logo-box {
        background: #1a1a1a !important;
        border-color: rgba(255,255,255,0.25) !important;
        color: #f0f0f0 !important;
    }
    body.dark-mode .brand-text { color: #f0f0f0 !important; }

    /* ── News / Warranty page specific ── */
    body.dark-mode .bg-premium-light { background: #0d0d0d !important; }
    body.dark-mode .hero-premium { background: #0d0d0d; }
    body.dark-mode .hero-bg-gradient { opacity: 0.4; }

    /* glass-card on warranty & news */
    body.dark-mode .glass-card,
    body.dark-mode .card-glass-product {
        background: #1a1a1a !important;
        border-color: rgba(255,255,255,0.08) !important;
        color: #f0f0f0 !important;
    }

    /* text-dark Bootstrap override in dark mode */
    body.dark-mode .text-dark { color: #f0f0f0 !important; }
    body.dark-mode h1, body.dark-mode h2,
    body.dark-mode h3, body.dark-mode h4,
    body.dark-mode h5, body.dark-mode h6 { color: var(--text-main); }

    /* tags badge bg-light on news */
    body.dark-mode .badge.bg-light {
        background: #252525 !important;
        color: #a0a0a0 !important;
        border-color: rgba(255,255,255,0.1) !important;
    }

    /* glass-badge pill */
    body.dark-mode .glass-badge {
        background: rgba(0,122,255,0.12) !important;
        border-color: rgba(0,122,255,0.2) !important;
    }

    /* Profile, checkout, cart page cards */
    body.dark-mode .card-body,
    body.dark-mode .card-header,
    body.dark-mode .card-footer {
        background: #1a1a1a !important;
        border-color: rgba(255,255,255,0.08) !important;
        color: #f0f0f0 !important;
    }

    /* Alert boxes */
    body.dark-mode .alert {
        border-color: rgba(255,255,255,0.1) !important;
    }
    body.dark-mode .alert-info {
        background: rgba(0,122,255,0.1) !important;
        color: #7db8ff !important;
    }
    body.dark-mode .alert-success {
        background: rgba(52,199,89,0.1) !important;
        color: #5de385 !important;
    }
    body.dark-mode .alert-danger {
        background: rgba(255,59,48,0.1) !important;
        color: #ff6b6b !important;
    }
    body.dark-mode .alert-warning {
        background: rgba(255,149,0,0.1) !important;
        color: #ffb340 !important;
    }

    /* View toggle buttons (product.php) */
    body.dark-mode .view-btn {
        background: #1a1a1a;
        border-color: rgba(255,255,255,0.08);
        color: #a0a0a0;
    }
    body.dark-mode .view-btn:hover,
    body.dark-mode .view-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }

    /* Wishlist button */
    body.dark-mode .btn-wishlist {
        background: rgba(30,30,30,0.9);
        color: #a0a0a0;
    }
    body.dark-mode .btn-wishlist:hover,
    body.dark-mode .btn-wishlist.active {
        background: #1a1a1a;
        color: #e74c3c;
    }

    /* Add to cart button */
    body.dark-mode .add-to-cart-btn {
        background: #e0e0e0 !important;
        color: #1d1d1f !important;
    }
    body.dark-mode .add-to-cart-btn:hover {
        background: var(--primary) !important;
        color: #fff !important;
    }
    body.dark-mode .product-card-new .p-specs span {
        background: #252525;
        color: #a0a0a0;
    }

    /* Flash Sale - keep blue tone in dark mode */
    body.dark-mode .flash-sale-section {
        background: linear-gradient(135deg, #0a2a5e, #007AFF) !important;
    }
    body.dark-mode .flash-sale-section .product-card-new {
        background: #1a1a1a !important;
        border-color: rgba(255,255,255,0.08) !important;
    }

    /* Newsletter input */
    body.dark-mode .newsletter-input {
        background: rgba(255,255,255,0.08) !important;
        border-color: rgba(255,255,255,0.15) !important;
        color: #f0f0f0 !important;
    }
    body.dark-mode .newsletter-input::placeholder {
        color: rgba(255,255,255,0.4) !important;
    }

    </style>

    <script>
        const BASE_PATH = "<?php echo $basePath; ?>";
        const SEARCH_API_URL = BASE_PATH + "api/search_suggestions.php";

        // Mini Cart Functions
        function loadMiniCart() {
            fetch(BASE_PATH + 'api/cart_count.php')
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('cartBadge');
                    const countEl = document.getElementById('miniCartCount');
                    const itemsEl = document.getElementById('miniCartItems');
                    const totalEl = document.getElementById('miniCartTotal');

                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline-flex';
                        if (countEl) countEl.textContent = data.count + ' sản phẩm';

                        // Load cart items
                        if (itemsEl && data.items) {
                            itemsEl.innerHTML = data.items.map(item => `
                                <div class="mini-cart-item">
                                    <img src="${BASE_PATH}assets/images/${item.image}" alt="${item.name}"
                                         onerror="this.src='https://placehold.co/100x100/f5f5f7/1d1d1f?text=Phone'">
                                    <div class="mini-cart-item-info">
                                        <div class="mini-cart-item-name">${item.name}</div>
                                        <div class="mini-cart-item-price">${new Intl.NumberFormat('vi-VN').format(item.price)}₫</div>
                                        <div class="mini-cart-item-qty">SL: ${item.quantity}</div>
                                    </div>
                                </div>
                            `).join('');
                        }

                        if (totalEl && data.total) {
                            totalEl.textContent = new Intl.NumberFormat('vi-VN').format(data.total) + '₫';
                        }
                    } else {
                        badge.style.display = 'none';
                        if (countEl) countEl.textContent = '0 sản phẩm';
                        if (itemsEl) {
                            itemsEl.innerHTML = `
                                <div class="mini-cart-empty">
                                    <i class="bi bi-bag-heart"></i>
                                    <p>Giỏ hàng của bạn đang trống</p>
                                </div>
                            `;
                        }
                        if (totalEl) totalEl.textContent = '0₫';
                    }
                })
                .catch(() => {});
        }

        // Load mini cart on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadMiniCart();

            // Mobile search trigger -> open search overlay
            var mobileSearch = document.getElementById('mobileSearchTrigger');
            if (mobileSearch) {
                mobileSearch.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Close mobile menu offcanvas first
                    var mobileNav = document.getElementById('mobileNav');
                    if (mobileNav) {
                        var closeBtn = mobileNav.querySelector('.btn-close');
                        if (closeBtn) {
                            closeBtn.click();
                        }
                    }
                    var searchTrigger = document.getElementById('searchTrigger');
                    if (searchTrigger) {
                        setTimeout(function() {
                            searchTrigger.click();
                        }, 350); // wait for offcanvas transition to finish
                    }
                });
            }
        });

        // Dark Mode Functions
        function toggleDarkMode() {
            const body = document.body;
            const icon = document.getElementById('darkModeIcon');
            const isDark = body.classList.toggle('dark-mode');

            // Save preference
            localStorage.setItem('darkMode', isDark ? '1' : '0');

            // Update icon
            if (isDark) {
                icon.className = 'bi bi-sun-fill';
            } else {
                icon.className = 'bi bi-moon-stars';
            }
        }

        // Load dark mode preference
        document.addEventListener('DOMContentLoaded', function() {
            const isDark = localStorage.getItem('darkMode') === '1';
            const icon = document.getElementById('darkModeIcon');
            if (isDark) {
                document.body.classList.add('dark-mode');
                if (icon) icon.className = 'bi bi-sun-fill';
            }
        });
    </script>
</head>
<body>
    <?php
    // Breadcrumb helper
    function getBreadcrumbs() {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $filename = basename($path, '.php');

        $breadcrumbs = [
            'index' => ['title' => 'Trang chủ', 'url' => 'index.php'],
            'product' => ['title' => 'Sản phẩm', 'url' => 'product.php'],
            'product-detail' => ['title' => 'Chi tiết sản phẩm', 'url' => '#'],
            'cart' => ['title' => 'Giỏ hàng', 'url' => 'cart.php'],
            'checkout' => ['title' => 'Thanh toán', 'url' => 'checkout.php'],
            'login' => ['title' => 'Đăng nhập', 'url' => 'login.php'],
            'register' => ['title' => 'Đăng ký', 'url' => 'register.php'],
            'profile' => ['title' => 'Hồ sơ', 'url' => 'profile.php'],
            'wishlist' => ['title' => 'Yêu thích', 'url' => 'wishlist.php'],
            'track_order' => ['title' => 'Đơn hàng', 'url' => 'track_order.php'],
            'warranty' => ['title' => 'Bảo hành', 'url' => 'warranty.php'],
            'news' => ['title' => 'Tin tức', 'url' => 'news.php'],
            'news-detail' => ['title' => 'Chi tiết tin', 'url' => '#'],
        ];

        return $breadcrumbs[$filename] ?? null;
    }

    $currentPage = getBreadcrumbs();
    $showBreadcrumb = $currentPage && $currentPage['title'] !== 'Trang chủ';
    ?>

    <nav class="navbar-minimal">
        <div class="container-wide nav-content">
            <a href="<?php echo $basePath; ?>index.php" class="nav-brand d-flex align-items-center">
                <div class="brand-logo-box sm me-2">NHK</div>
                <span class="brand-text sm d-none d-sm-block">MOBILE</span>
            </a>

            <ul class="nav-links mb-0 d-none d-lg-flex">
                <li><a href="<?php echo $basePath; ?>product.php" class="nav-link">Điện thoại</a></li>
                <li><a href="<?php echo $basePath; ?>warranty.php" class="nav-link">Bảo hành</a></li>
                <li><a href="<?php echo $basePath; ?>news.php" class="nav-link">Tin tức</a></li>
            </ul>

            <div class="nav-actions">
                <div class="search-wrapper">
                    <a href="#" id="searchTrigger" class="nav-icon d-none d-md-flex"><i class="bi bi-search"></i></a>
                    <?php include 'includes/search_overlay.php'; ?>
                </div>
                <!-- Mini Cart Dropdown -->
                <div class="mini-cart-wrapper">
                    <a href="<?php echo $basePath; ?>cart.php" class="nav-icon position-relative" id="cartNavIcon">
                        <i class="bi bi-bag-heart"></i>
                        <span id="cartBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary"
                              style="font-size: 0.65rem; padding: 0.35em 0.5em; display: none;">
                            0
                        </span>
                    </a>
                    <div class="mini-cart-dropdown" id="miniCartDropdown">
                        <div class="mini-cart-header">
                            <h5>Giỏ hàng của bạn</h5>
                            <span class="text-muted" id="miniCartCount">0 sản phẩm</span>
                        </div>
                        <div class="mini-cart-items" id="miniCartItems">
                            <!-- Items will be loaded via AJAX -->
                        </div>
                        <div class="mini-cart-footer">
                            <div class="mini-cart-total">
                                <span>Tổng cộng:</span>
                                <strong id="miniCartTotal">0₫</strong>
                            </div>
                            <a href="<?php echo $basePath; ?>cart.php" class="btn btn-dark w-100 rounded-pill py-2 fw-bold">Xem giỏ hàng</a>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['user_id'])): ?>
                <?php
                    // Đếm số lượng wishlist cho badge navbar (chỉ hiện với user thường)
                    $wlCount = 0;
                    try {
                        $wlNavStmt = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
                        $wlNavStmt->execute([$_SESSION['user_id']]);
                        $wlCount = (int)$wlNavStmt->fetchColumn();
                    } catch (Exception $e) { $wlCount = 0; }
                ?>
                <a href="<?php echo $basePath; ?>wishlist.php" class="nav-icon position-relative d-none d-md-flex" title="Yêu thích">
                    <i class="bi bi-heart"></i>
                    <span id="wishlistBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                          style="font-size: 0.65rem; padding: 0.35em 0.5em; display: <?php echo $wlCount > 0 ? 'inline-flex' : 'none'; ?>;">
                        <?php echo $wlCount; ?>
                    </span>
                </a>
                <?php else: ?>
                <!-- Admin không có wishlist cá nhân -->
                <span id="wishlistBadge" style="display:none;"></span>
                <?php endif; ?>
                
                <a href="#mobileNav" data-bs-toggle="offcanvas" class="nav-icon d-flex d-lg-none"><i class="bi bi-list"></i></a>
                
                <!-- Dark Mode Toggle -->
                <button class="dark-mode-toggle d-none d-md-flex" onclick="toggleDarkMode()" title="Chuyển đổi chế độ tối">
                    <i class="bi bi-moon-stars" id="darkModeIcon"></i>
                </button>

                <?php if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])): ?>
                    <a href="#accountOffcanvas" role="button" class="nav-icon d-none d-sm-flex" data-bs-toggle="offcanvas"><i class="bi bi-person-circle"></i></a>
                <?php else: ?>
                    <a href="<?php echo $basePath; ?>login.php" class="nav-icon d-none d-sm-flex"><i class="bi bi-person"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb Section -->
    <?php if ($showBreadcrumb): ?>
    <section class="breadcrumb-section">
        <div class="container-wide">
            <nav class="breadcrumb">
                <span class="breadcrumb-item"><a href="<?php echo $basePath; ?>index.php"><i class="bi bi-house-door"></i> Trang chủ</a></span>
                <span class="breadcrumb-separator"><i class="bi bi-chevron-right"></i></span>
                <span class="breadcrumb-item active"><?php echo $currentPage['title']; ?></span>
            </nav>
        </div>
    </section>
    <?php endif; ?>

    <!-- Mobile Menu Drawer (Premium Style) -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileNav" style="width: 280px; border-right: none;">
        <div class="offcanvas-header py-4 px-4 border-bottom">
            <div class="nav-brand">
                <div class="brand-logo-box sm me-2">NHK</div>
                <span class="brand-text sm">MOBILE</span>
            </div>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            <div class="py-4">
                <div class="list-group list-group-flush">
                    <a href="#" id="mobileSearchTrigger" class="list-group-item list-group-item-action py-3 px-4 border-0 fw-bold d-flex align-items-center text-primary">
                        <i class="bi bi-search me-3 fs-5"></i> Tìm kiếm
                    </a>
                    <a href="<?php echo $basePath; ?>index.php" class="list-group-item list-group-item-action py-3 px-4 border-0 fw-bold d-flex align-items-center">
                        <i class="bi bi-house me-3 fs-5"></i> Trang chủ
                    </a>
                    <a href="<?php echo $basePath; ?>product.php" class="list-group-item list-group-item-action py-3 px-4 border-0 fw-bold d-flex align-items-center">
                        <i class="bi bi-phone me-3 fs-5"></i> Sản phẩm
                    </a>
                    <a href="<?php echo $basePath; ?>track_order.php" class="list-group-item list-group-item-action py-3 px-4 border-0 fw-bold d-flex align-items-center">
                        <i class="bi bi-receipt-cutoff me-3 fs-5"></i> Lịch sử mua hàng
                    </a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo $basePath; ?>wishlist.php" class="list-group-item list-group-item-action py-3 px-4 border-0 fw-bold d-flex align-items-center">
                        <i class="bi bi-heart me-3 fs-5 text-danger"></i> Yêu thích
                    </a>
                    <a href="<?php echo $basePath; ?>profile.php" class="list-group-item list-group-item-action py-3 px-4 border-0 fw-bold d-flex align-items-center">
                        <i class="bi bi-person-vcard me-3 fs-5"></i> Hồ sơ của tôi
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo $basePath; ?>warranty.php" class="list-group-item list-group-item-action py-3 px-4 border-0 fw-bold d-flex align-items-center">
                        <i class="bi bi-shield-check me-3 fs-5"></i> Bảo hành
                    </a>
                    <a href="<?php echo $basePath; ?>news.php" class="list-group-item list-group-item-action py-3 px-4 border-0 fw-bold d-flex align-items-center">
                        <i class="bi bi-newspaper me-3 fs-5"></i> Tin tức
                    </a>
                    <a href="<?php echo $basePath; ?>check.php" class="list-group-item list-group-item-action py-3 px-4 border-0 fw-bold d-flex align-items-center">
                        <i class="bi bi-clipboard-check me-3 fs-5"></i> Kiểm tra hệ thống
                    </a>
                </div>
            </div>
            
            <div class="px-4 mt-2">
                <div class="p-4 rounded-4 bg-light text-center">
                    <p class="small text-muted mb-3 italic">Khám phá các siêu phẩm AI mới nhất tại NHK Mobile.</p>
                    <a href="<?php echo $basePath; ?>product.php" class="btn btn-dark w-100 rounded-pill py-2 small fw-bold">Mua sắm ngay</a>
                </div>
            </div>
            
            <div class="mt-auto p-4 border-top">
                <p class="small text-muted mb-3 fw-bold text-uppercase tracking-wider">Tài khoản</p>
                <?php if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])): ?>
                    <a href="#accountOffcanvas" data-bs-toggle="offcanvas" class="btn btn-outline-dark w-100 rounded-pill py-2 mb-2 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-person-circle"></i> Trang quản lý
                    </a>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <a href="<?php echo $basePath; ?>login.php" class="btn btn-primary rounded-pill py-2 fw-bold">Đăng nhập</a>
                        <a href="<?php echo $basePath; ?>register.php" class="btn btn-outline-dark rounded-pill py-2 fw-bold">Đăng ký</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Account Offcanvas (Vẫn giữ logic nhưng đổi style nhẹ) -->
    <?php if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])): ?>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="accountOffcanvas" style="width: 350px; border-left: 1px solid var(--border-light);">
        <div class="offcanvas-header border-bottom py-4">
            <h5 class="offcanvas-title fw-bold">Tài khoản</h5>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            <div class="py-5 px-4 text-center border-bottom" style="background:#fff;">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Avatar icon người trên nền tròn xám nhạt -->
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-4"
                         style="width:80px;height:80px;border:1.5px solid #e8eaf0;">
                        <i class="bi bi-person-fill text-primary" style="font-size:2.2rem;"></i>
                    </div>
                    <h6 class="fw-bold mb-1" style="font-size:1rem;">
                        <?php echo htmlspecialchars($_SESSION['user_fullname'] ?? 'Khách hàng'); ?>
                    </h6>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">Thành viên NHK Mobile</p>
                <?php else: ?>
                    <!-- Admin avatar -->
                    <div class="rounded-circle bg-dark d-flex align-items-center justify-content-center mx-auto mb-4"
                         style="width:80px;height:80px;">
                        <i class="bi bi-shield-check text-warning" style="font-size:2rem;"></i>
                    </div>
                    <h6 class="fw-bold mb-1" style="font-size:1rem;">
                        <?php echo htmlspecialchars($_SESSION['admin_user'] ?? 'Admin'); ?>
                    </h6>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">Quản trị viên NHK Mobile</p>
                <?php endif; ?>
            </div>
            
            <div class="px-4 py-4 d-grid gap-3">
                <?php if (isset($_SESSION['admin_id'])): ?>
                    <!-- Admin -->
                    <a href="<?php echo $basePath; ?>admin/dashboard.php" class="btn btn-dark w-100 rounded-pill py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-speedometer2 fs-5"></i> Bảng điều khiển Admin
                    </a>
                    <a href="<?php echo $basePath; ?>track_order.php" class="btn btn-outline-dark w-100 rounded-pill py-3 fw-bold d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-receipt-cutoff fs-5"></i> Lịch sử mua hàng
                    </a>
                <?php elseif (isset($_SESSION['user_id'])): ?>
                    <!-- User thường -->
                    <a href="<?php echo $basePath; ?>profile.php" class="btn btn-dark w-100 rounded-pill py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-person-vcard fs-5"></i> Hồ sơ của tôi
                    </a>
                    <a href="<?php echo $basePath; ?>wishlist.php" class="btn w-100 rounded-pill py-3 fw-bold d-flex align-items-center justify-content-center gap-2" style="border: 2px solid #e74c3c; color:#e74c3c;">
                        <i class="bi bi-heart-fill fs-5"></i> Yêu thích
                    </a>
                    <a href="<?php echo $basePath; ?>track_order.php" class="btn btn-outline-dark w-100 rounded-pill py-3 fw-bold d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-receipt-cutoff fs-5"></i> Đơn hàng của tôi
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="p-4 border-top">
            <a href="<?php echo $basePath; ?>logout.php" class="btn btn-outline-danger w-100 rounded-pill py-2 fw-bold">Đăng xuất</a>
        </div>
    </div>
    <?php endif; ?>
