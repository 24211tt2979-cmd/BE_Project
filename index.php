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

// 1. Fetch featured products (limit 8)
$stmt = $pdo->query("SELECT * FROM products ORDER BY is_featured DESC, created_at DESC LIMIT 8");
$featuredProducts = $stmt->fetchAll();

// 2. Fetch "Dành cho bạn" - 8 sản phẩm khác, không trùng với featured
$featuredIds = array_column($featuredProducts, 'id');
$excludeIds = !empty($featuredIds) ? implode(',', array_map('intval', $featuredIds)) : '0';
$forYouStmt = $pdo->query("SELECT * FROM products WHERE id NOT IN ($excludeIds) ORDER BY RAND() LIMIT 8");
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
    <!-- ===== CUSTOM IMAGE BANNER SLIDER ===== -->
    <section class="home-custom-banners">
        <div class="container-wide">
            <?php 
            // Nếu không có banner động nào từ DB, dùng banner mặc định tĩnh
            if (empty($homeBanners)) {
                $homeBanners = [
                    ['image' => 'banner_1.png', 'link_url' => 'product.php', 'title' => 'Banner 1'],
                    ['image' => 'banner_2.png', 'link_url' => 'product.php', 'title' => 'Banner 2'],
                    ['image' => 'banner_3.png', 'link_url' => 'product.php', 'title' => 'Banner 3']
                ];
                $isStatic = true;
            } else {
                $isStatic = false;
            }
            ?>
            <div class="custom-banner-shell" id="customBannerCarousel">
                <div class="custom-banner-track">
                    <?php foreach ($homeBanners as $b): 
                        $imagePath = $isStatic ? 'assets/images/' . $b['image'] : 'assets/images/' . $b['image'];
                    ?>
                    <a href="<?php echo htmlspecialchars($b['link_url'] ?: 'product.php'); ?>" class="custom-banner-slide">
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($b['title'] ?? 'Banner'); ?>" onerror="this.src='https://placehold.co/1200x400/f5f5f7/1d1d1f?text=NHK+Mobile'">
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Nút điều hướng -->
                <button type="button" class="custom-banner-arrow custom-banner-prev" id="cbPrev">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button type="button" class="custom-banner-arrow custom-banner-next" id="cbNext">
                    <i class="bi bi-chevron-right"></i>
                </button>

                <!-- Dấu chấm (Dots) -->
                <div class="custom-banner-dots" id="cbDots">
                    <?php foreach ($homeBanners as $index => $b): ?>
                    <button type="button" class="<?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <style>
        .home-custom-banners {
            padding: 92px 0 16px; /* Tăng padding-top để không bị Navbar che mất */
            background: #fff;
        }
        .custom-banner-shell {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
        }
        .custom-banner-track {
            display: flex;
            transition: transform 0.5s ease-in-out;
            will-change: transform;
        }
        .custom-banner-slide {
            min-width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            background: #f8f9fa; /* Màu nền nhẹ cho viền ngoài (nếu ảnh nhỏ hơn khung) */
        }
        .custom-banner-slide img {
            max-width: 100%;
            height: auto;
            /* Giữ nguyên kích thước gốc, không kéo giãn làm vỡ nét */
            display: block;
            margin: 0 auto;
        }
        
        .custom-banner-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border: none;
            border-radius: 50%;
            background: rgba(255,255,255,0.7);
            color: #333;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            z-index: 10;
        }
        .custom-banner-arrow:hover {
            background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-50%) scale(1.05);
        }
        .custom-banner-prev { left: 16px; }
        .custom-banner-next { right: 16px; }

        .custom-banner-dots {
            position: absolute;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        .custom-banner-dots button {
            width: 10px;
            height: 10px;
            border: none;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: all 0.3s;
            padding: 0;
        }
        .custom-banner-dots button.active {
            width: 24px;
            border-radius: 12px;
            background: #fff;
        }

        @media (max-width: 768px) {
            .custom-banner-slide img {
                height: 200px;
            }
            .custom-banner-arrow {
                width: 32px; height: 32px; font-size: 16px;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const track = document.querySelector('.custom-banner-track');
            const slides = document.querySelectorAll('.custom-banner-slide');
            const dots = document.querySelectorAll('.custom-banner-dots button');
            const prevBtn = document.getElementById('cbPrev');
            const nextBtn = document.getElementById('cbNext');
            
            if (!track || slides.length === 0) return;

            let currentIndex = 0;
            const totalSlides = slides.length;
            let slideInterval;

            function updateCarousel() {
                track.style.transform = `translateX(-${currentIndex * 100}%)`;
                dots.forEach(dot => dot.classList.remove('active'));
                if (dots[currentIndex]) {
                    dots[currentIndex].classList.add('active');
                }
            }

            function nextSlide() {
                currentIndex = (currentIndex + 1) % totalSlides;
                updateCarousel();
            }

            function prevSlide() {
                currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
                updateCarousel();
            }

            function startAutoplay() {
                slideInterval = setInterval(nextSlide, 4000);
            }

            function resetAutoplay() {
                clearInterval(slideInterval);
                startAutoplay();
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    nextSlide();
                    resetAutoplay();
                });
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    prevSlide();
                    resetAutoplay();
                });
            }

            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    currentIndex = index;
                    updateCarousel();
                    resetAutoplay();
                });
            });

            startAutoplay();
        });
    </script>

    <!-- ===== HERO BANNER SECTION (E-COMMERCE STYLE) ===== -->
    <section class="hero-new mt-4" id="heroBanners">
        <div class="container-wide" style="padding-top: 10px; padding-bottom: 14px;">

            <!-- ROW 1: Main Big Banner + Side Banners -->
            <div class="banner-grid-main">
                <!-- === MAIN BANNER: iPhone 17 === -->
                <a href="product.php?category=Apple" class="banner-card banner-main" style="text-decoration:none;">
                    <div class="banner-content">
                        <span class="banner-kicker">⚡ Mở bán hôm nay</span>
                        <h2 class="banner-title">iPhone 17 Pro Max</h2>
                        <p class="banner-sub">Camera 200MP · Chip A19 Bionic · 5G</p>
                        <div class="banner-price">
                            33.990.000₫
                            <small>39.990.000₫</small>
                        </div>
                        <span class="banner-btn btn-white">Mua ngay <i class="bi bi-arrow-right"></i></span>
                    </div>
                    <img src="assets/images/apple-iphone-17-pro-max.png" alt="iPhone 17 Pro Max" class="banner-img">
                </a>

                <!-- === SIDE BANNERS (3 nhỏ) === -->
                <div class="banner-side-col">
                    <a href="product.php?category=Samsung" class="banner-card banner-side banner-side-1" style="text-decoration:none;">
                        <div style="z-index:2; position:relative;">
                            <h3 class="banner-title">Galaxy S25 Ultra</h3>
                            <p class="banner-sub">Trả góp 0% · 24 tháng</p>
                        </div>
                        <img src="assets/images/samsung-galaxy-s25-ultra.png" alt="S25 Ultra" class="banner-img">
                    </a>
                    <a href="product.php?category=Xiaomi" class="banner-card banner-side banner-side-2" style="text-decoration:none;">
                        <div style="z-index:2; position:relative;">
                            <h3 class="banner-title">Xiaomi 17 Ultra</h3>
                            <p class="banner-sub">Leica Camera · 6.000 mAh</p>
                        </div>
                        <img src="assets/images/xiaomi-17-ultra.png" alt="Xiaomi 17" class="banner-img">
                    </a>
                    <a href="product.php?category=OPPO" class="banner-card banner-side banner-side-3" style="text-decoration:none;">
                        <div style="z-index:2; position:relative;">
                            <h3 class="banner-title">OPPO Find X10</h3>
                            <p class="banner-sub">Sạc 100W · Giảm 3 triệu</p>
                        </div>
                        <img src="assets/images/oppo-find-x10.png" alt="OPPO Find X10" class="banner-img">
                    </a>
                </div>
            </div>

            <!-- ROW 2: 3 Promo Banners -->
            <div class="banner-grid-sub">
                <a href="product.php" class="banner-card banner-promo banner-promo-1" style="text-decoration:none;">
                    <div style="z-index:2; position:relative;">
                        <p class="banner-title">🔥 Flash Sale Cuối Tuần</p>
                        <div class="banner-highlight">Giảm đến 40%</div>
                    </div>
                    <i class="bi bi-lightning-charge-fill banner-icon"></i>
                </a>
                <a href="product.php?category=Apple" class="banner-card banner-promo banner-promo-2" style="text-decoration:none;">
                    <div style="z-index:2; position:relative;">
                        <p class="banner-title">Thu cũ đổi mới iPhone</p>
                        <div class="banner-highlight">+5.000.000₫</div>
                    </div>
                    <i class="bi bi-arrow-repeat banner-icon"></i>
                </a>
                <a href="product.php" class="banner-card banner-promo banner-promo-3" style="text-decoration:none;">
                    <div style="z-index:2; position:relative;">
                        <p class="banner-title">Trả góp 0% lãi suất</p>
                        <div class="banner-highlight">12–24 tháng</div>
                    </div>
                    <i class="bi bi-credit-card banner-icon"></i>
                </a>
            </div>

            <!-- ROW 3: Flash Sale + Brand Banners -->
            <div class="banner-grid-flash">
                <!-- Flash sale wide -->
                <a href="product.php" class="banner-card banner-flash banner-flash-main" style="text-decoration:none;">
                    <div style="z-index:2; position:relative;">
                        <div class="flash-sale-label"><i class="bi bi-lightning-fill"></i> Flash Sale 12:00 hôm nay</div>
                        <h4 class="banner-title">Điện thoại giảm đến 8.000.000₫</h4>
                        <p class="banner-sub">Samsung · Xiaomi · OPPO · Realme</p>
                    </div>
                    <img src="assets/images/samsung-galaxy-s24-ultra.png" alt="Flash Sale" class="banner-img" style="height:75%; right:20px;">
                </a>
                <!-- Vivo -->
                <a href="product.php?category=Vivo" class="banner-card banner-flash banner-flash-2" style="text-decoration:none;">
                    <div style="z-index:2; position:relative;">
                        <h4 class="banner-title">Vivo X300</h4>
                        <p class="banner-sub">Zeiss Optics · 200W SuperCharge</p>
                    </div>
                    <img src="assets/images/vivo-x300.png" alt="Vivo X300" class="banner-img">
                </a>
                <!-- Realme -->
                <a href="product.php?category=Realme" class="banner-card banner-flash banner-flash-3" style="text-decoration:none;">
                    <div style="z-index:2; position:relative;">
                        <h4 class="banner-title">Realme GT9</h4>
                        <p class="banner-sub">Snapdragon 8 Elite · Giảm 2tr</p>
                    </div>
                    <img src="assets/images/realme-gt9.png" alt="Realme GT9" class="banner-img">
                </a>
            </div>

        </div>
    </section>



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
                <h2 class="display-5 fw-bold">Đỉnh phẩm công nghệ mới.</h2>
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

    <!-- FLASH SALE SECTION -->
    <section class="flash-sale-section">
        <div class="container-wide">
            <div class="flash-sale-header reveal">
                <div class="flash-sale-title">
                    <i class="bi bi-lightning-charge-fill flash-icon"></i>
                    <h2>Flash Sale</h2>
                </div>
                <div class="countdown-timer">
                    <div class="countdown-item">
                        <div class="countdown-number" id="hours">02</div>
                        <div class="countdown-label">Giờ</div>
                    </div>
                    <span class="countdown-separator">:</span>
                    <div class="countdown-item">
                        <div class="countdown-number" id="minutes">45</div>
                        <div class="countdown-label">Phút</div>
                    </div>
                    <span class="countdown-separator">:</span>
                    <div class="countdown-item">
                        <div class="countdown-number" id="seconds">30</div>
                        <div class="countdown-label">Giây</div>
                    </div>
                </div>
            </div>
            <div class="product-grid-new">
                <?php
                // Lấy sản phẩm flash sale (ngẫu nhiên, giảm giá giả lập)
                $flashSaleStmt = $pdo->query("SELECT * FROM products ORDER BY RAND() LIMIT 4");
                $flashSaleProducts = $flashSaleStmt->fetchAll();
                foreach ($flashSaleProducts as $p):
                    $discountPercent = rand(10, 30);
                    $salePrice = $p['price'] * (100 - $discountPercent) / 100;
                ?>
                    <div class="product-card-new" style="background: #fff; border: none;">
                        <a href="product-detail.php?id=<?php echo $p['id']; ?>">
                            <div class="product-img-box">
                                <span class="badge-hot" style="background: #007AFF;">-<?php echo $discountPercent; ?>%</span>
                                <img src="assets/images/<?php echo $p['image']; ?>" alt="<?php echo $p['name']; ?>"
                                    onerror="this.src='https://placehold.co/300x400/f5f5f7/1d1d1f?text=Phone'">
                            </div>
                            <div class="product-info-new">
                                <span class="p-cat"><?php echo $p['category']; ?></span>
                                <h3 class="p-name"><?php echo $p['name']; ?></h3>
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <span style="font-size: 18px; font-weight: 800; color: #fff;"><?php echo number_format($salePrice, 0, ',', '.'); ?>₫</span>
                                    <span style="font-size: 14px; color: rgba(255,255,255,0.7); text-decoration: line-through;"><?php echo number_format($p['price'], 0, ',', '.'); ?>₫</span>
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
                            <a href="cart.php?add=<?php echo $p['id']; ?>" class="btn btn-danger w-100 rounded-1 fw-bold btn-buy-ecommerce">
                                Mua ngay
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-5">
                <a href="product.php" class="btn-main btn-outline" style="background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.3);">Xem tất cả Flash Sale</a>
            </div>
        </div>
    </section>

    <!-- FOR YOU SECTION -->
    <section class="products-section" style="background: var(--bg-soft);">
        <div class="container-wide">
            <div class="section-title-box reveal">
                <span class="section-subtitle">Gợi ý cho bạn</span>
                <h2 class="display-5 fw-bold">Dành cho bạn.</h2>
            </div>
            <div class="product-grid-new reveal-stagger">
                <?php foreach ($forYouProducts as $p): ?>
                    <div class="product-card-new">
                        <a href="product-detail.php?id=<?php echo $p['id']; ?>">
                            <div class="product-img-box">
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

<?php include 'includes/footer.php'; ?>
