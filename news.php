<?php
/**
 * NHK Mobile - Tech News Feed
 * 
 * Description: Displays the latest technology articles, product reviews, 
 * and tips. Supports filtering articles by specific tags.
 * 
 * Author: NguyenHuuKhanh
 * Version: 2.2
 * Date: 2026-04-08
 */
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

// Fetch all news articles (with graceful error handling)
$articles = [];
$tagFilter = $_GET['tag'] ?? '';
try {
    $sql = "SELECT * FROM news";
    $params = [];
    if (!empty($tagFilter)) {
        $sql .= " WHERE tags LIKE ?";
        $params[] = "%" . trim($tagFilter) . "%";
    }
    $sql .= " ORDER BY created_at DESC, id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $articles = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table doesn't exist yet — will show "no articles" message
    $articles = [];
}

$pageTitle = "Tin tức Công nghệ | NHK Mobile";
$basePath = "";
include 'includes/header.php';
?>

<main>
    <!-- HERO SECTION: Premium Dark -->
    <section class="hero-premium position-relative overflow-hidden d-flex align-items-center pt-5 mt-5" style="min-height: 50vh;">
        <div class="hero-bg-gradient"></div>
        <div class="container position-relative z-2 text-center text-lg-start animate-fade-in">
            <div class="glass-badge d-inline-block px-4 py-2 mb-4 rounded-pill">
                <span class="text-primary-gradient fw-bold">NHK Tech News 2026</span>
            </div>
            <h1 class="display-2 fw-800 mb-4 tracking-tight hero-title-main">
                Cập nhật xu hướng.<br>
                <span class="text-gradient">Dẫn đầu công nghệ.</span>
            </h1>
            <p class="h4 text-secondary mb-0 fw-light max-w-600">
                Khám phá những bước tiến mới nhất trong thế giới di động và trí tuệ nhân tạo.
            </p>
        </div>
    </section>

    <!-- CONTENT SECTION: Light Body -->
    <section class="py-huge bg-premium-light">
        <div class="container px-xl-5">
            <div class="d-flex justify-content-between align-items-end mb-5 animate-reveal">
                <div>
                    <h2 class="display-4 fw-bold text-dark mb-2">Tin mới nhất.</h2>
                    <?php if (!empty($tagFilter)): ?>
                        <p class="text-secondary h5 fw-light mb-0 d-flex align-items-center">Đang lọc theo từ khóa: <span class="badge bg-primary rounded-pill ms-2 px-3 fw-normal" style="padding-bottom: 5px;">#<?php echo htmlspecialchars($tagFilter); ?></span> <a href="news.php" class="text-secondary ms-3 small text-decoration-none hover-lift"><i class="bi bi-x-circle-fill"></i> Bỏ lọc</a></p>
                    <?php else: ?>
                        <p class="text-secondary h5 fw-light">Những câu chuyện công nghệ đáng chú ý hôm nay.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-4 pt-4">
                <?php if (empty($articles)): ?>
                    <div class="col-12 text-center py-5">
                         <div class="glass-card p-5 rounded-5 border-dashed">
                              <i class="bi bi-newspaper display-1 mb-4 opacity-10"></i>
                              <h3>Đang cập nhật tin tức mới...</h3>
                              <p>Vui lòng quay lại sau ít phút hoặc nạp lại CSDL.</p>
                         </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($articles as $index => $a): ?>
                    <div class="col-md-6 col-lg-4 animate-reveal" style="animation-delay: <?php echo $index * 0.1; ?>s">
                        <article class="card-glass-product h-100 p-0 overflow-hidden border-0 shadow-sm transition-all hover-lift">
                                <img src="<?php echo !empty($a['image']) && $a['image'] !== 'placeholder.png' ? 'assets/images/' . $a['image'] : 'https://placehold.co/600x400/f5f5f7/1d1d1f?text=Tech+News'; ?>" class="card-img-top object-fit-cover" alt="<?php echo htmlspecialchars($a['title'] ?? ''); ?>" style="height: 240px;" onerror="this.src='https://placehold.co/600x400/f5f5f7/1d1d1f?text=Tech+News'">
                                <div class="position-absolute top-0 start-0 m-3">
                                    <?php if (!empty($a['category'] ?? '')): ?>
                                    <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo htmlspecialchars($a['category'] ?? ''); ?></span>
                                    <?php endif; ?>
                                </div>
                            <div class="p-4" style="background: var(--bg-white);">
                                <div class="text-secondary small mb-2">
                                    <i class="bi bi-calendar3 me-1"></i> <?php echo date('d/m/Y', strtotime($a['created_at'])); ?>
                                </div>
                                <h4 class="fw-bold text-dark mb-2 line-clamp-2"><?php echo htmlspecialchars($a['title'] ?? ''); ?></h4>
                                <?php if(!empty($a['tags'])): ?>
                                <div class="mb-3">
                                    <?php 
                                    $tags = explode(',', $a['tags']);
                                    foreach($tags as $t) {
                                        $t = trim($t);
                                        if($t) echo '<a href="news.php?tag=' . urlencode($t) . '" class="badge bg-light text-secondary border me-1 fw-normal text-decoration-none hover-lift" style="font-size: 11px;">#'.htmlspecialchars($t).'</a>';
                                    }
                                    ?>
                                </div>
                                <?php else: ?>
                                <div class="mb-3"></div> <!-- padding thay thế -->
                                <?php endif; ?>
                                <p class="text-secondary small mb-4 line-clamp-3"><?php echo htmlspecialchars($a['excerpt'] ?? ($a['content'] ? mb_strimwidth(strip_tags($a['content']), 0, 120, '...') : '')); ?></p>
                                <a href="news-detail.php?id=<?php echo $a['id']; ?>" class="btn btn-link text-primary p-0 text-decoration-none fw-bold">
                                    Đọc tiếp <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </article>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- NEWSLETTER: Glass Panel on White -->
    <section class="pb-huge bg-premium-light">
        <div class="container px-xl-5">
            <div class="glass-panel p-5 p-lg-8 rounded-5 text-center bg-dark text-white shadow-2xl overflow-hidden position-relative">
                <div class="position-relative z-2">
                    <h2 class="display-5 fw-bold mb-4" style="color: #ffffff !important;">Đừng bỏ lỡ bất kỳ nhịp đập nào.</h2>
                    <p class="h5 mb-5 fw-light" style="color: rgba(255,255,255,0.7) !important;">Đăng ký để nhận tin tức công nghệ mới nhất qua Email hàng tuần.</p>
                    <form id="newsletterForm" class="newsletter-form-premium d-flex flex-column flex-md-row gap-3 max-w-500 mx-auto">
                        <input type="email" id="newsletterEmail" class="form-control form-control-lg rounded-pill px-4" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #ffffff !important;" placeholder="Địa chỉ email của bạn..." required>
                        <button type="submit" id="btnSubscribe" class="btn btn-primary btn-lg px-5 rounded-pill shadow-lg fw-bold" style="background: #0071e3; border: none; color: #fff !important;">Đăng ký</button>
                    </form>
                    <div id="newsletterMsg" class="mt-3 small" style="display: none;"></div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.getElementById('newsletterForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const emailInput = document.getElementById('newsletterEmail');
    const btnSubscribe = document.getElementById('btnSubscribe');
    const msgDiv = document.getElementById('newsletterMsg');
    
    const email = emailInput.value.trim();
    if(!email) return;

    btnSubscribe.disabled = true;
    btnSubscribe.textContent = 'Đang gửi...';
    msgDiv.style.display = 'none';

    fetch('api/subscribe.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ email: email })
    })
    .then(res => res.json())
    .then(data => {
        btnSubscribe.disabled = false;
        btnSubscribe.textContent = 'Đăng ký';
        msgDiv.style.display = 'block';
        
        if(data.status === 'success') {
            msgDiv.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> ${data.message}</span>`;
            emailInput.value = ''; // Reset form
        } else {
            msgDiv.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> ${data.message}</span>`;
        }
    })
    .catch(err => {
        btnSubscribe.disabled = false;
        btnSubscribe.textContent = 'Đăng ký';
        msgDiv.style.display = 'block';
        msgDiv.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Lỗi kết nối máy chủ!</span>`;
    });
});
</script>

<?php include 'includes/footer.php'; ?>
