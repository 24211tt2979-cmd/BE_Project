<?php
/**
 * NHK Mobile - Article Detailed View
 * 
 * Description: Displays the full content of a technology news article. 
 * Supports HTML rendering for rich content and tag-based navigation.
 * 
 * Author: NguyenHuuKhanh
 * Version: 2.1
 * Date: 2026-04-08
 */
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: news.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    die("Bài viết không tồn tại!");
}

$pageTitle = $article['title'] . " | NHK Mobile";
$basePath = "";
include 'includes/header.php';
?>

<main class="py-5 mt-5 bg-premium-light">
    <div class="container px-xl-5">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="news.php" class="text-decoration-none">Tin tức</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($article['category'] ?? ''); ?></li>
            </ol>
        </nav>
 
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                <article class="bg-white p-4 p-md-5 rounded-4 shadow-sm border-0">
                    <div class="mb-4 text-center">
                        <span class="badge bg-primary rounded-pill px-3 py-2 mb-3"><?php echo htmlspecialchars($article['category'] ?? ''); ?></span>
                        <h1 class="display-5 fw-bold mb-3"><?php echo htmlspecialchars($article['title'] ?? ''); ?></h1>
                        <div class="text-secondary small d-flex justify-content-center align-items-center mb-4">
                            <i class="bi bi-calendar3 me-2"></i> <?php echo date('d/m/Y H:i', strtotime($article['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div class="mb-5 text-center">
                        <img src="<?php echo !empty($article['image']) && $article['image'] !== 'placeholder.png' ? 'assets/images/' . htmlspecialchars($article['image'] ?? '') : 'https://placehold.co/1200x600/f5f5f7/1d1d1f?text=Tech+News'; ?>" class="img-fluid rounded-4 object-fit-cover w-100 shadow-sm" alt="<?php echo htmlspecialchars($article['title'] ?? ''); ?>" style="max-height: 500px;" onerror="this.src='https://placehold.co/1200x600/f5f5f7/1d1d1f?text=Tech+News'">
                    </div>
                    
                    <div class="news-content fs-5 leading-relaxed text-dark" style="line-height: 1.8;">
                        <?php 
                        // Hỗ trợ hiển thị nội dung HTML cơ bản hoặc tự thêm xuống dòng
                        $content = $article['content'] ?? '';
                        if (strip_tags($content) !== $content) {
                            echo $content;
                        } else {
                            echo nl2br(htmlspecialchars($content));
                        }
                        ?>
                    </div>
                    
                    <?php if (!empty($article['tags'])): ?>
                    <div class="mt-5 pt-4 border-top">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <i class="bi bi-tags-fill text-secondary me-2 fs-5"></i>
                            <?php 
                            $tags = explode(',', $article['tags']);
                            foreach($tags as $t) {
                                $t = trim($t);
                                if ($t) echo '<a href="news.php?tag=' . urlencode($t) . '" class="badge bg-light text-dark border px-3 py-2 text-decoration-none fw-normal hover-lift fs-6 transition-all">#' . htmlspecialchars($t) . '</a>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </article>
                
                <div class="mt-5 text-center mb-5">
                    <a href="news.php" class="btn btn-outline-dark rounded-pill px-5 py-3 fw-medium transition-all hover-lift">
                        <i class="bi bi-arrow-left me-2"></i> Quay lại trang Tin tức
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
/* Style riêng cho nội dung tin tức */
.news-content p {
    margin-bottom: 1.5rem;
}
.news-content img {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    margin: 2rem 0;
}
.news-content h2, .news-content h3 {
    font-weight: bold;
    margin-top: 2rem;
    margin-bottom: 1rem;
}
.news-content strong {
    font-weight: 700;
}
</style>

<?php include 'includes/footer.php'; ?>
