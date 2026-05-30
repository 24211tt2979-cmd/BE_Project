<?php
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

$settings = get_system_settings($pdo);
$pageTitle = "Lien he va ban do | NHK Mobile";
$basePath = "";
include 'includes/header.php';
?>

<main class="py-5 mt-5">
    <div class="container-wide">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-5">
                <div class="bg-light rounded-4 p-5 h-100 border">
                    <span class="section-subtitle">Lien he</span>
                    <h1 class="display-5 fw-bold mb-4"><?php echo htmlspecialchars($settings['store_name'] ?? 'NHK Mobile'); ?></h1>
                    <div class="d-flex gap-3 mb-4">
                        <div class="nav-icon bg-white"><i class="bi bi-telephone text-primary"></i></div>
                        <div>
                            <div class="small text-muted fw-bold text-uppercase">Hotline</div>
                            <div class="h5 fw-bold mb-0"><?php echo htmlspecialchars($settings['hotline'] ?? ''); ?></div>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-4">
                        <div class="nav-icon bg-white"><i class="bi bi-envelope text-primary"></i></div>
                        <div>
                            <div class="small text-muted fw-bold text-uppercase">Email</div>
                            <div class="fw-bold"><?php echo htmlspecialchars($settings['store_email'] ?? ''); ?></div>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="nav-icon bg-white"><i class="bi bi-geo-alt text-primary"></i></div>
                        <div>
                            <div class="small text-muted fw-bold text-uppercase">Dia chi</div>
                            <div class="fw-bold"><?php echo nl2br(htmlspecialchars($settings['store_address'] ?? '')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="rounded-4 overflow-hidden border shadow-sm h-100" style="min-height:420px;">
                    <iframe
                        src="<?php echo htmlspecialchars($settings['map_embed_url'] ?? 'https://www.google.com/maps?q=Ho%20Chi%20Minh%20City&output=embed'); ?>"
                        width="100%"
                        height="100%"
                        style="border:0; min-height:420px;"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
