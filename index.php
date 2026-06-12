<?php
/**
 * NHK Mobile - Home Page
 * 
 * Description: The main landing page featuring the hero section, 
 * featured products grid, and core value propositions.
 * 
 * Author: NguyenHuuKhanh
 * Version: 2.3
 * Date: 2026-04-08
 */
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

/** @var PDO $pdo */
$settings = get_system_settings($pdo);

// 1. Fetch featured products
$featuredCount = (int)($settings['home_featured_count'] ?? 8);
$stmt = $pdo->query("SELECT * FROM products ORDER BY is_featured DESC, created_at DESC LIMIT $featuredCount");
$featuredProducts = $stmt->fetchAll();

// 2. Fetch "Sản phẩm bán chạy"
$bestSellingCount = (int)($settings['home_best_selling_count'] ?? 4);
$bestSellingStmt = $pdo->query("
    SELECT p.*, COALESCE(SUM(oi.quantity), 0) as total_sold
    FROM products p
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON o.id = oi.order_id AND (o.status = 'Hoàn thành' OR o.status = 'Completed')
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT $bestSellingCount
");
$bestSellingProducts = $bestSellingStmt->fetchAll();

// 3. Fetch "Dành cho bạn"
$forYouCount = (int)($settings['home_for_you_count'] ?? 8);
$featuredIds = array_column($featuredProducts, 'id');
$excludeIds = !empty($featuredIds) ? implode(',', array_map('intval', $featuredIds)) : '0';
$forYouStmt = $pdo->query("SELECT * FROM products WHERE id NOT IN ($excludeIds) ORDER BY RAND() LIMIT $forYouCount");
$forYouProducts = $forYouStmt->fetchAll();

try {
    $bannerStmt = $pdo->query("SELECT * FROM homepage_banners WHERE is_active = 1 ORDER BY sort_order ASC, id DESC LIMIT 3");
    $homeBanners = $bannerStmt->fetchAll();
} catch (Exception $e) {
    $homeBanners = [];
}

$pageTitle = "NHK Mobile | Apple Authorized Reseller";
$basePath = "";

include 'includes/header.php';
?>

<main>
    <!-- ===== HERO SLIDER - CINEMATIC FULL-WIDTH ===== -->
    <section class="hero-slider-wrap">
        <?php 
        if (empty($homeBanners)) {
            $homeBanners = [
                ['image' => 'banner_1.png', 'link_url' => 'product.php', 'title' => 'Khuyến mãi đặc biệt'],
                ['image' => 'banner_2.png', 'link_url' => 'product.php', 'title' => 'Sản phẩm mới'],
                ['image' => 'banner_3.png', 'link_url' => 'product.php', 'title' => 'Flash Sale hôm nay']
            ];
            $isStatic = true;
        } else {
            $isStatic = false;
        }
        ?>
        <div class="hs-shell" id="heroSlider">
            <div class="hs-track">
                <?php foreach ($homeBanners as $idx => $b):
                    $imgSrc = 'assets/images/' . $b['image'];
                ?>
                <div class="hs-slide <?php echo $idx === 0 ? 'active' : ''; ?>">
                    <!-- Ảnh nền full -->
                    <a href="<?php echo htmlspecialchars($b['link_url'] ?: 'product.php'); ?>" class="hs-img-wrap">
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                             alt="<?php echo htmlspecialchars($b['title'] ?? 'Banner'); ?>"
                             class="hs-bg-img"
                             onerror="this.src='https://placehold.co/1400x500/0f0c29/ffffff?text=NHK+Mobile'">
                        <!-- Gradient overlay -->
                        <div class="hs-overlay"></div>
                    </a>
                    <!-- Text content overlay -->
                    <div class="hs-content">
                        <span class="hs-badge">🔥 Ưu đãi độc quyền</span>
                        <h2 class="hs-title"><?php echo htmlspecialchars($b['title'] ?? 'Banner'); ?></h2>
                        <p class="hs-desc">Khám phá ngay bộ sưu tập mới nhất tại NHK Mobile</p>
                        <a href="<?php echo htmlspecialchars($b['link_url'] ?: 'product.php'); ?>" class="hs-cta-btn">
                            Xem ngay <i class="bi bi-arrow-right-circle-fill"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Navigation arrows -->
            <button class="hs-arrow hs-prev" id="hsPrev" aria-label="Trước">
                <i class="bi bi-chevron-left"></i>
            </button>
            <button class="hs-arrow hs-next" id="hsNext" aria-label="Tiếp">
                <i class="bi bi-chevron-right"></i>
            </button>

            <!-- Progress dots -->
            <div class="hs-dots" id="hsDots">
                <?php foreach ($homeBanners as $i => $b): ?>
                <button class="hs-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>" aria-label="Slide <?php echo $i+1; ?>"></button>
                <?php endforeach; ?>
            </div>

            <!-- Slide counter -->
            <div class="hs-counter">
                <span id="hsCurrentNum">1</span> / <span><?php echo count($homeBanners); ?></span>
            </div>
        </div>
    </section>

    <!-- ===== PROMO BANNER GRID ===== -->
    <section class="promo-banner-section">
        <div class="container-wide">
            <!-- Row 1: Big + Side -->
            <div class="pb-grid-main">
                <!-- Big main banner -->
                <a href="product.php?category=Apple" class="pb-card pb-hero">
                    <div class="pb-hero-bg" style="background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);"></div>
                    <div class="pb-hero-content">
                        <span class="pb-badge-new">Mới nhất 2025</span>
                        <h2 class="pb-hero-title">iPhone 17<br><span>Pro Max</span></h2>
                        <p class="pb-hero-sub">Camera 200MP · Chip A19 Bionic · 5G Ultra</p>
                        <div class="pb-hero-price">
                            <span class="pb-price-now">33.990.000₫</span>
                            <span class="pb-price-old">39.990.000₫</span>
                        </div>
                        <span class="pb-cta-btn">Mua ngay <i class="bi bi-bag-check-fill"></i></span>
                    </div>
                    <img src="assets/images/apple-iphone-17-pro-max.png" alt="iPhone 17 Pro Max" class="pb-hero-img"
                         onerror="this.style.display='none'">
                    <div class="pb-shimmer"></div>
                </a>

                <!-- Side banners -->
                <div class="pb-side-col">
                    <a href="product.php?category=Samsung" class="pb-card pb-side pb-side-samsung">
                        <div class="pb-side-content">
                            <span class="pb-side-kicker">Flagship mới</span>
                            <h3>Galaxy S25 Ultra</h3>
                            <p>Trả góp 0% · 24 tháng</p>
                        </div>
                        <img src="assets/images/samsung-galaxy-s25-ultra.png" alt="S25 Ultra"
                             onerror="this.style.display='none'">
                    </a>
                    <a href="product.php?category=Xiaomi" class="pb-card pb-side pb-side-xiaomi">
                        <div class="pb-side-content">
                            <span class="pb-side-kicker">Leica Camera</span>
                            <h3>Xiaomi 17 Ultra</h3>
                            <p>6.000 mAh · Sạc 120W</p>
                        </div>
                        <img src="assets/images/xiaomi-17-ultra.png" alt="Xiaomi 17 Ultra"
                             onerror="this.style.display='none'">
                    </a>
                    <a href="product.php?category=OPPO" class="pb-card pb-side pb-side-oppo">
                        <div class="pb-side-content">
                            <span class="pb-side-kicker">Giảm 3 triệu</span>
                            <h3>OPPO Find X10</h3>
                            <p>Hasselblad · 100W SuperVOOC</p>
                        </div>
                        <img src="assets/images/oppo-find-x10.png" alt="OPPO Find X10"
                             onerror="this.style.display='none'">
                    </a>
                </div>
            </div>

            <!-- Row 2: 3 Promo strips -->
            <div class="pb-grid-promo">
                <a href="product.php" class="pb-promo pb-promo-flash">
                    <div class="pb-promo-icon"><i class="bi bi-lightning-charge-fill"></i></div>
                    <div>
                        <div class="pb-promo-label">Flash Sale mỗi ngày</div>
                        <div class="pb-promo-value">Giảm đến 40%</div>
                    </div>
                    <i class="bi bi-arrow-right-circle pb-promo-arrow"></i>
                </a>
                <a href="product.php?category=Apple" class="pb-promo pb-promo-trade">
                    <div class="pb-promo-icon"><i class="bi bi-arrow-left-right"></i></div>
                    <div>
                        <div class="pb-promo-label">Thu cũ đổi mới iPhone</div>
                        <div class="pb-promo-value">Cộng thêm 5.000.000₫</div>
                    </div>
                    <i class="bi bi-arrow-right-circle pb-promo-arrow"></i>
                </a>
                <a href="product.php" class="pb-promo pb-promo-credit">
                    <div class="pb-promo-icon"><i class="bi bi-credit-card-2-front-fill"></i></div>
                    <div>
                        <div class="pb-promo-label">Trả góp 0% lãi suất</div>
                        <div class="pb-promo-value">12 – 24 tháng</div>
                    </div>
                    <i class="bi bi-arrow-right-circle pb-promo-arrow"></i>
                </a>
            </div>
        </div>
    </section>

    <style>
    /* ==============================
       HERO SLIDER - CINEMATIC
    ============================== */
    .hero-slider-wrap {
        padding-top: 80px; /* tránh navbar */
        background: var(--bg-white, #fff);
    }
    .hs-shell {
        position: relative;
        overflow: hidden;
        height: 480px;
        background: #0a0a0a;
    }
    .hs-track {
        display: flex;
        height: 100%;
        position: relative;
    }
    .hs-slide {
        position: absolute;
        inset: 0;
        opacity: 0;
        transition: opacity 0.7s cubic-bezier(.4,0,.2,1);
        pointer-events: none;
    }
    .hs-slide.active {
        opacity: 1;
        pointer-events: auto;
    }
    .hs-img-wrap {
        display: block;
        position: absolute;
        inset: 0;
        overflow: hidden;
    }
    .hs-bg-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        display: block;
        transform: scale(1.03);
        transition: transform 6s ease;
    }
    .hs-slide.active .hs-bg-img {
        transform: scale(1);
    }
    /* Gradient overlay - từ trái sang phải */
    .hs-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(
            90deg,
            rgba(0,0,0,0.75) 0%,
            rgba(0,0,0,0.4) 50%,
            rgba(0,0,0,0.05) 100%
        );
    }
    /* Text nội dung bên trái */
    .hs-content {
        position: absolute;
        top: 50%;
        left: clamp(24px, 5%, 80px);
        transform: translateY(-50%);
        z-index: 10;
        max-width: 480px;
        color: #fff;
    }
    .hs-badge {
        display: inline-block;
        background: linear-gradient(90deg, #ff6b35, #f7c59f);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        padding: 5px 14px;
        border-radius: 30px;
        margin-bottom: 14px;
    }
    .hs-title {
        font-size: clamp(24px, 3.5vw, 44px);
        font-weight: 800;
        line-height: 1.15;
        margin: 0 0 12px;
        color: #fff;
        text-shadow: 0 2px 12px rgba(0,0,0,0.4);
        letter-spacing: -.02em;
    }
    .hs-desc {
        font-size: 15px;
        color: rgba(255,255,255,0.85);
        margin-bottom: 24px;
        line-height: 1.6;
    }
    .hs-cta-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(90deg, #007AFF, #5856D6);
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        padding: 13px 28px;
        border-radius: 50px;
        text-decoration: none;
        transition: all 0.25s;
        box-shadow: 0 6px 20px rgba(0,122,255,0.4);
    }
    .hs-cta-btn:hover {
        background: linear-gradient(90deg, #0063d0, #4240b0);
        transform: translateY(-2px);
        box-shadow: 0 10px 28px rgba(0,122,255,0.5);
        color: #fff;
    }
    /* Arrows */
    .hs-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 48px; height: 48px;
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        background: rgba(255,255,255,0.12);
        backdrop-filter: blur(8px);
        color: #fff;
        font-size: 20px;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        z-index: 20;
        transition: all 0.2s;
    }
    .hs-arrow:hover {
        background: rgba(255,255,255,0.25);
        border-color: rgba(255,255,255,0.6);
        transform: translateY(-50%) scale(1.08);
    }
    .hs-prev { left: 20px; }
    .hs-next { right: 20px; }
    /* Dots */
    .hs-dots {
        position: absolute;
        bottom: 20px;
        left: clamp(24px, 5%, 80px);
        display: flex;
        gap: 6px;
        z-index: 20;
    }
    .hs-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        border: none;
        background: rgba(255,255,255,0.35);
        cursor: pointer;
        padding: 0;
        transition: all 0.35s;
    }
    .hs-dot.active {
        width: 28px;
        border-radius: 10px;
        background: #fff;
    }
    /* Counter */
    .hs-counter {
        position: absolute;
        bottom: 18px;
        right: 24px;
        color: rgba(255,255,255,0.6);
        font-size: 13px;
        font-weight: 600;
        z-index: 20;
        letter-spacing: .05em;
    }
    .hs-counter #hsCurrentNum { color: #fff; font-size: 16px; }

    @media (max-width: 768px) {
        .hs-shell { height: 260px; }
        .hs-title { font-size: 20px; }
        .hs-desc { display: none; }
        .hs-cta-btn { font-size: 13px; padding: 10px 18px; }
        .hs-badge { font-size: 10px; }
    }

    /* ==============================
       PROMO BANNER GRID
    ============================== */
    .promo-banner-section {
        background: #f5f5f7;
        padding: 20px 0 0;
    }
    /* Big + Side layout */
    .pb-grid-main {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 12px;
        margin-bottom: 12px;
    }
    .pb-card {
        border-radius: 18px;
        overflow: hidden;
        display: block;
        text-decoration: none;
        position: relative;
        transition: transform 0.25s, box-shadow 0.25s;
    }
    .pb-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 16px 40px rgba(0,0,0,0.18);
        text-decoration: none;
    }
    /* Hero card */
    .pb-hero {
        min-height: 300px;
        background: #0f0c29;
        display: flex;
        align-items: flex-end;
    }
    .pb-hero-bg {
        position: absolute;
        inset: 0;
    }
    .pb-hero-content {
        position: relative;
        z-index: 5;
        padding: 32px 36px;
        flex: 1;
    }
    .pb-badge-new {
        display: inline-block;
        background: rgba(255,214,10,0.15);
        border: 1px solid rgba(255,214,10,0.4);
        color: #FFD60A;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        padding: 4px 12px;
        border-radius: 20px;
        margin-bottom: 14px;
    }
    .pb-hero-title {
        color: #fff;
        font-size: 36px;
        font-weight: 900;
        line-height: 1.1;
        margin: 0 0 10px;
        letter-spacing: -.02em;
    }
    .pb-hero-title span {
        background: linear-gradient(90deg, #007AFF, #AF52DE);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .pb-hero-sub {
        color: rgba(255,255,255,0.7);
        font-size: 13.5px;
        margin-bottom: 18px;
    }
    .pb-hero-price {
        margin-bottom: 22px;
    }
    .pb-price-now {
        color: #FFD60A;
        font-size: 22px;
        font-weight: 800;
        margin-right: 10px;
    }
    .pb-price-old {
        color: rgba(255,255,255,0.4);
        font-size: 14px;
        text-decoration: line-through;
    }
    .pb-cta-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #fff;
        color: #0f0c29;
        font-size: 14px;
        font-weight: 700;
        padding: 11px 24px;
        border-radius: 40px;
        transition: all 0.2s;
    }
    .pb-card:hover .pb-cta-btn {
        background: #007AFF;
        color: #fff;
    }
    .pb-hero-img {
        position: absolute;
        right: 0;
        bottom: 0;
        height: 90%;
        width: auto;
        object-fit: contain;
        z-index: 3;
        filter: drop-shadow(-10px 0 30px rgba(0,0,0,0.4));
    }
    /* Shimmer effect */
    .pb-shimmer {
        position: absolute;
        inset: 0;
        background: linear-gradient(105deg, transparent 40%, rgba(255,255,255,0.06) 50%, transparent 60%);
        z-index: 6;
        pointer-events: none;
    }

    /* Side column */
    .pb-side-col {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .pb-side {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 20px;
        min-height: 88px;
        position: relative;
        overflow: hidden;
    }
    .pb-side-content {
        position: relative;
        z-index: 2;
    }
    .pb-side-kicker {
        display: block;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        opacity: 0.7;
        margin-bottom: 4px;
    }
    .pb-side h3 {
        font-size: 16px;
        font-weight: 800;
        margin: 0 0 3px;
        color: #fff;
        line-height: 1.2;
    }
    .pb-side p {
        font-size: 11.5px;
        color: rgba(255,255,255,0.75);
        margin: 0;
    }
    .pb-side img {
        height: 70px;
        width: auto;
        object-fit: contain;
        position: relative;
        z-index: 2;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.25));
        transition: transform 0.3s;
    }
    .pb-card:hover .pb-side img { transform: scale(1.08) rotate(-2deg); }
    .pb-side-samsung {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
    }
    .pb-side-xiaomi {
        background: linear-gradient(135deg, #FF6B35 0%, #c0392b 100%);
    }
    .pb-side-oppo {
        background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 60%, #43a047 100%);
    }

    /* Promo strips */
    .pb-grid-promo {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        padding-bottom: 20px;
    }
    .pb-promo {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 18px 22px;
        border-radius: 16px;
        text-decoration: none;
        transition: all 0.22s;
        position: relative;
        overflow: hidden;
    }
    .pb-promo:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        text-decoration: none;
    }
    .pb-promo-icon {
        width: 48px; height: 48px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }
    .pb-promo-label {
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 3px;
    }
    .pb-promo-value {
        font-size: 17px;
        font-weight: 800;
    }
    .pb-promo-arrow {
        margin-left: auto;
        font-size: 22px;
        opacity: 0.5;
        flex-shrink: 0;
        transition: all 0.2s;
    }
    .pb-promo:hover .pb-promo-arrow { opacity: 1; transform: translateX(4px); }
    /* Flash */
    .pb-promo-flash {
        background: linear-gradient(135deg, #FF3B30 0%, #FF6B35 100%);
        color: #fff;
    }
    .pb-promo-flash .pb-promo-icon { background: rgba(255,255,255,0.2); color: #FFD60A; }
    /* Trade */
    .pb-promo-trade {
        background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%);
        color: #fff;
    }
    .pb-promo-trade .pb-promo-icon { background: rgba(255,255,255,0.2); color: #fff; }
    /* Credit */
    .pb-promo-credit {
        background: linear-gradient(135deg, #34C759 0%, #30b050 100%);
        color: #fff;
    }
    .pb-promo-credit .pb-promo-icon { background: rgba(255,255,255,0.2); color: #fff; }

    @media (max-width: 992px) {
        .pb-grid-main { grid-template-columns: 1fr; }
        .pb-side-col { flex-direction: row; }
        .pb-side { flex: 1; min-height: 80px; }
        .pb-hero-img { height: 70%; }
        .pb-hero-title { font-size: 28px; }
    }
    @media (max-width: 640px) {
        .pb-grid-promo { grid-template-columns: 1fr; }
        .pb-side-col { flex-direction: column; }
        .pb-hero-img { display: none; }
    }
    </style>

    <script>
    // ========= HERO SLIDER JS =========
    document.addEventListener('DOMContentLoaded', function() {
        const slides   = document.querySelectorAll('.hs-slide');
        const dots     = document.querySelectorAll('.hs-dot');
        const prevBtn  = document.getElementById('hsPrev');
        const nextBtn  = document.getElementById('hsNext');
        const counter  = document.getElementById('hsCurrentNum');
        if (!slides.length) return;

        let cur = 0, timer;

        function goTo(n) {
            slides[cur].classList.remove('active');
            dots[cur]?.classList.remove('active');
            cur = (n + slides.length) % slides.length;
            slides[cur].classList.add('active');
            dots[cur]?.classList.add('active');
            if (counter) counter.textContent = cur + 1;
        }

        function next() { goTo(cur + 1); }
        function prev() { goTo(cur - 1); }
        function autoplay() { timer = setInterval(next, 5000); }
        function reset()    { clearInterval(timer); autoplay(); }

        if (nextBtn) nextBtn.addEventListener('click', () => { next(); reset(); });
        if (prevBtn) prevBtn.addEventListener('click', () => { prev(); reset(); });
        dots.forEach((d, i) => d.addEventListener('click', () => { goTo(i); reset(); }));

        // Touch swipe
        let touchX = 0;
        const shell = document.getElementById('heroSlider');
        if (shell) {
            shell.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; }, { passive: true });
            shell.addEventListener('touchend', e => {
                const dx = e.changedTouches[0].clientX - touchX;
                if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); reset(); }
            }, { passive: true });
        }

        autoplay();
    });
    </script>



    <!-- CATEGORY SECTION -->
    <section class="category-section py-3" style="margin-bottom: 16px;">
        <div class="container-wide">
            <div class="d-flex align-items-center gap-2 overflow-auto hide-scrollbar">
                <span class="fw-bold text-nowrap me-1" style="font-size:14px; color:#374151;">Danh mục:</span>
                <a href="product.php?category=Apple" class="category-pill-sm"><i class="bi bi-apple"></i> Apple</a>
                <a href="product.php?category=Samsung" class="category-pill-sm"><i class="bi bi-phone"></i> Samsung</a>
                <a href="product.php?category=Xiaomi" class="category-pill-sm"><i class="bi bi-lightning-charge"></i> Xiaomi</a>
                <a href="product.php?category=OPPO" class="category-pill-sm"><i class="bi bi-camera"></i> OPPO</a>
                <a href="product.php?category=Vivo" class="category-pill-sm"><i class="bi bi-music-note-beamed"></i> Vivo</a>
                <a href="product.php?category=Realme" class="category-pill-sm"><i class="bi bi-bolt"></i> Realme</a>
                <a href="product.php" class="category-pill-sm"><i class="bi bi-grid-3x3-gap"></i> Tất cả</a>
            </div>
        </div>
    </section>



    <!-- FEATURED PRODUCTS -->
    <section class="products-section">
        <div class="container-wide">
            <div class="section-title-box reveal">
                <span class="section-subtitle">Sản phẩm nổi bật</span>
                <h2 class="display-5 fw-bold"><?php echo htmlspecialchars($settings['home_featured_title'] ?? 'Đỉnh phẩm công nghệ mới.'); ?></h2>
            </div>

            <div class="product-grid-new reveal-stagger">
                <?php foreach ($featuredProducts as $p): ?>
                    <div class="product-card-new">
                        <a href="product-detail.php?id=<?php echo $p['id']; ?>">
                            <div class="product-img-box">
                                <?php if($p['is_featured']): ?>
                                    <span class="badge-hot">Hot Deal</span>
                                <?php endif; ?>
                                <img src="assets/images/<?php echo $p['image']; ?>" alt="<?php echo $p['name']; ?>"
                                    onerror="this.src='https://placehold.co/300x400/f5f5f7/1d1d1f?text=Phone'">
                            </div>
                            <div class="product-info-new">
                                <span class="p-cat"><?php echo $p['category']; ?></span>
                                <h3 class="p-name"><?php echo $p['name']; ?></h3>
                                <div class="p-price-new"><?php echo number_format($p['price'], 0, ',', '.'); ?>₫</div>
                                <?php if(!empty($p['specs'])): ?>
                                <div class="p-specs">
                                    <?php
                                    $specsArr = array_map('trim', explode(',', $p['specs']));
                                    foreach(array_slice($specsArr, 0, 2) as $spec): ?>
                                    <span><?php echo htmlspecialchars($spec); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </a>
                        <div class="mt-auto w-100 pt-3">
                            <a href="cart.php?add=<?php echo $p['id']; ?>" class="btn btn-outline-danger w-100 rounded-1 fw-bold btn-buy-ecommerce">
                                Mua ngay
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5">
                <a href="product.php" class="btn-main btn-outline">Xem tất cả sản phẩm</a>
            </div>
        </div>
    </section>

    <!-- BEST SELLING SECTION -->
    <section class="flash-sale-section">
        <div class="container-wide">
            <div class="flash-sale-header reveal">
                <div class="flash-sale-title">
                    <i class="bi bi-fire flash-icon"></i>
                    <h2><?php echo htmlspecialchars($settings['home_best_selling_title'] ?? 'Sản phẩm bán chạy'); ?></h2>
                </div>
                <?php if (!empty($bestSellingProducts)): ?>
                <div class="best-selling-badge">
                    <i class="bi bi-star-fill"></i> Bán chạy nhất
                </div>
                <?php endif; ?>
            </div>
            <div class="product-grid-new">
                <?php foreach ($bestSellingProducts as $p):
                    $isHot = $p['total_sold'] > 0;
                ?>
                    <div class="product-card-new" style="background: #fff; border: none;">
                        <a href="product-detail.php?id=<?php echo $p['id']; ?>">
                            <div class="product-img-box">
                                <?php if($isHot): ?>
                                    <span class="badge-hot" style="background: #ef4444;">Đã bán <?php echo $p['total_sold']; ?></span>
                                <?php endif; ?>
                                <img src="assets/images/<?php echo $p['image']; ?>" alt="<?php echo $p['name']; ?>"
                                    onerror="this.src='https://placehold.co/300x400/f5f5f7/1d1d1f?text=Phone'">
                            </div>
                            <div class="product-info-new">
                                <span class="p-cat"><?php echo $p['category']; ?></span>
                                <h3 class="p-name"><?php echo $p['name']; ?></h3>
                                <div class="p-price-new"><?php echo number_format($p['price'], 0, ',', '.'); ?>₫</div>
                                <?php if(!empty($p['specs'])): ?>
                                <div class="p-specs">
                                    <?php
                                    $specsArr = array_map('trim', explode(',', $p['specs']));
                                    foreach(array_slice($specsArr, 0, 2) as $spec): ?>
                                    <span><?php echo htmlspecialchars($spec); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </a>
                        <div class="mt-auto w-100 pt-3">
                            <a href="cart.php?add=<?php echo $p['id']; ?>" class="btn btn-danger w-100 rounded-1 fw-bold btn-buy-ecommerce">
                                Mua ngay
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-5">
                <a href="product.php" class="btn-main btn-outline" style="background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.3);">Xem tất cả sản phẩm</a>
            </div>
        </div>
    </section>

    <!-- FOR YOU SECTION -->
    <section class="products-section" style="background: var(--bg-soft);">
        <div class="container-wide">
            <div class="section-title-box reveal">
                <span class="section-subtitle">Gợi ý cho bạn</span>
                <h2 class="display-5 fw-bold"><?php echo htmlspecialchars($settings['home_for_you_title'] ?? 'Dành cho bạn.'); ?></h2>
            </div>
            <div class="product-grid-new reveal-stagger">
                <?php foreach ($forYouProducts as $p): 
                    $discount = (int)($p['discount'] ?? 0);
                    $priceOld = $p['price'];
                    $priceActual = $discount > 0 ? $priceOld * (100 - $discount) / 100 : $priceOld;
                ?>
                    <div class="product-card-new">
                        <a href="product-detail.php?id=<?php echo $p['id']; ?>">
                            <div class="product-img-box">
                                <?php if ($discount > 0): ?>
                                    <span class="badge-hot" style="background: #e74c3c;">-<?php echo $discount; ?>%</span>
                                <?php endif; ?>
                                <img src="assets/images/<?php echo $p['image']; ?>" alt="<?php echo $p['name']; ?>"
                                    onerror="this.src='https://placehold.co/300x400/f5f5f7/1d1d1f?text=Phone'">
                            </div>
                            <div class="product-info-new">
                                <span class="p-cat"><?php echo $p['category']; ?></span>
                                <h3 class="p-name"><?php echo $p['name']; ?></h3>
                                <div class="p-price-new">
                                    <?php if ($discount > 0): ?>
                                        <span><?php echo number_format($priceActual, 0, ',', '.'); ?>₫</span>
                                        <span style="font-size: 13px; color: #86868b; text-decoration: line-through; margin-left: 8px; font-weight: normal;"><?php echo number_format($priceOld, 0, ',', '.'); ?>₫</span>
                                    <?php else: ?>
                                        <span><?php echo number_format($priceOld, 0, ',', '.'); ?>₫</span>
                                    <?php endif; ?>
                                </div>
                                <?php if(!empty($p['specs'])): ?>
                                <div class="p-specs">
                                    <?php
                                    $specsArr = array_map('trim', explode(',', $p['specs']));
                                    foreach(array_slice($specsArr, 0, 2) as $spec): ?>
                                    <span><?php echo htmlspecialchars($spec); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </a>
                        <div class="mt-auto w-100 pt-3">
                            <a href="cart.php?add=<?php echo $p['id']; ?>" class="btn btn-outline-danger w-100 rounded-1 fw-bold btn-buy-ecommerce">
                                Mua ngay
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-5">
                <a href="product.php" class="btn-main btn-outline">Xem tất cả sản phẩm</a>
            </div>
        </div>
    </section>

    <!-- WHY CHOOSE US -->
    <section class="features-new">        <div class="container-wide">
            <div class="section-title-box reveal">
                <span class="section-subtitle">Tại sao chọn NHK Mobile?</span>
                <h2 class="display-5 fw-bold">Trải nghiệm mua sắm chuẩn 5 sao.</h2>
            </div>

            <div class="feature-grid reveal-stagger">
                <div class="feature-item">
                    <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
                    <h3>Bảo hành chính hãng</h3>
                    <p class="text-muted">Cam kết 100% sản phẩm chính hãng, bảo hành 1 đổi 1 trong 30 ngày nếu có lỗi từ nhà sản xuất.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="bi bi-truck"></i></div>
                    <h3>Giao hàng siêu tốc</h3>
                    <p class="text-muted">Miễn phí giao hàng toàn quốc. Nhận hàng trong vòng 2h tại các thành phố lớn.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="bi bi-headset"></i></div>
                    <h3>Hỗ trợ 24/7</h3>
                    <p class="text-muted">Đội ngũ kỹ thuật viên chuyên nghiệp luôn sẵn sàng hỗ trợ bạn mọi lúc, mọi nơi.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- TESTIMONIAL SECTION -->
    <section class="testimonial-section">
        <div class="container-wide">
            <div class="section-title-box reveal">
                <span class="section-subtitle">Đánh giá</span>
                <h2 class="display-5 fw-bold">Khách hàng nói gì về chúng tôi?</h2>
            </div>
            <div class="testimonial-grid reveal-stagger">
                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                    </div>
                    <p class="testimonial-text">"Mua iPhone 17 Pro tại NHK Mobile, sản phẩm chính hãng, giao hàng siêu nhanh. Nhân viên tư vấn nhiệt tình, sẽ quay lại mua tiếp!"</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">NT</div>
                        <div class="testimonial-info">
                            <h4>Nguyễn Thanh Tùng</h4>
                            <p>Khách hàng tại Hà Nội</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                    </div>
                    <p class="testimonial-text">"Giá tốt nhất thị trường, chế độ bảo hành rõ ràng. Mình đã mua 3 chiếc điện thoại ở đây và rất hài lòng với chất lượng dịch vụ."</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar" style="background: linear-gradient(135deg, #ff6900, #ff9f43);">LH</div>
                        <div class="testimonial-info">
                            <h4>Lê Hoàng</h4>
                            <p>Khách hàng tại TP.HCM</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-half"></i>
                    </div>
                    <p class="testimonial-text">"Website dễ sử dụng, thanh toán nhanh gọn. Flash sale giá cực hợp lý, tiết kiệm được gần 2 triệu so với mua ở chỗ khác."</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar" style="background: linear-gradient(135deg, #1428a0, #3b5bdb);">PM</div>
                        <div class="testimonial-info">
                            <h4>Phạm Minh</h4>
                            <p>Khách hàng tại Đà Nẵng</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- NEWSLETTER SECTION -->
    <section class="newsletter-section">
        <div class="container-wide">
            <div class="newsletter-content reveal-scale">
                <h2>Đăng ký nhận tin</h2>
                <p>Nhận thông tin về sản phẩm mới, khuyến mãi đặc biệt và ưu đãi dành riêng cho thành viên.</p>
                <form class="newsletter-form" onsubmit="handleNewsletter(event)">
                    <input type="email" class="newsletter-input" placeholder="Nhập email của bạn" required>
                    <button type="submit" class="newsletter-btn">Đăng ký</button>
                </form>
            </div>
        </div>
    </section>
</main>

<!-- Carousel & Countdown Scripts -->
<script>


// Countdown Timer
function updateCountdown() {
    const now = new Date();
    const endOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59);
    const diff = endOfDay - now;

    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    document.getElementById('hours').textContent = String(hours).padStart(2, '0');
    document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
    document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
}

setInterval(updateCountdown, 1000);
updateCountdown();

// Newsletter Handler
function handleNewsletter(e) {
    e.preventDefault();
    const email = e.target.querySelector('input[type="email"]').value;
    // Gửi API đăng ký
    fetch('api/subscribe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'email=' + encodeURIComponent(email)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Cảm ơn bạn đã đăng ký! Chúng tôi sẽ gửi thông tin khuyến mãi đến email của bạn.');
            e.target.reset();
        } else {
            alert(data.message || 'Có lỗi xảy ra, vui lòng thử lại sau.');
        }
    })
    .catch(() => {
        alert('Cảm ơn bạn đã đăng ký!');
        e.target.reset();
    });
}
</script>

<!-- ===== GOOGLE MAP ===== -->
<section class="index-map-section">
    <div class="container-wide">
        <div class="section-title-box text-center mb-5">
            <span class="section-subtitle">Bản đồ</span>
            <h2 class="display-5 fw-bold">Tìm chúng tôi trên Google Maps</h2>
        </div>
        <div class="rounded-4 overflow-hidden border shadow-sm">
            <iframe
                src="<?php echo htmlspecialchars($settings['map_embed_url'] ?? 'https://www.google.com/maps?q=Ho%20Chi%20Minh%20City&output=embed'); ?>"
                width="100%"
                height="420"
                style="border:0; display:block;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                title="Bản đồ cửa hàng NHK Mobile"></iframe>
        </div>
    </div>
</section>

<style>
.index-map-section {
    padding: 60px 0;
    background: #f8f8fa;
}
body.dark-mode .index-map-section {
    background: #111;
}
</style>

<?php include 'includes/footer.php'; ?>
