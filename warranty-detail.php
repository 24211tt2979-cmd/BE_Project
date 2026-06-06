<?php
/**
 * NHK Mobile - Warranty Detail Page
 *
 * Displays full warranty information and repair history timeline for a device.
 *
 * Author: NguyenHuuKhanh
 * Version: 1.0
 * Date: 2026-04-14
 */
require_once 'includes/auth_functions.php';
require_once 'includes/db.php';

$imei = trim($_GET['imei'] ?? '');
$pageTitle = "Chi tiết Bảo hành | NHK Mobile";
$basePath = "";
include 'includes/header.php';
?>

<style>
/* ============================
   WARRANTY DETAIL PAGE STYLES
   ============================ */
.wd-hero {
    background: linear-gradient(135deg, #f7f8fa 0%, #eef1f6 100%);
    padding: 40px 0 28px;
    border-bottom: 1px solid #e5e8ed;
}
.wd-back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #555;
    font-size: 0.85rem;
    text-decoration: none;
    margin-bottom: 24px;
    transition: color .2s;
}
.wd-back-link:hover { color: #3b82f6; }

/* Device card */
.wd-device-card {
    background: #fff;
    border-radius: 20px;
    padding: 28px 32px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.07);
    display: flex;
    gap: 28px;
    align-items: center;
    margin-bottom: 24px;
}
.wd-device-img {
    width: 110px;
    height: 140px;
    object-fit: cover;
    border-radius: 14px;
    flex-shrink: 0;
    background: #f0f2f5;
}
.wd-device-img-placeholder {
    width: 110px;
    height: 140px;
    border-radius: 14px;
    background: linear-gradient(135deg, #e0e6ef 0%, #c8d3e6 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #aab4c8;
    font-size: 2.5rem;
}
.wd-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 600;
    margin-bottom: 10px;
}
.wd-status-badge.active {
    background: #e8f9ef;
    color: #16a34a;
    border: 1px solid #bbf0d1;
}
.wd-status-badge.expired {
    background: #fff0ee;
    color: #dc2626;
    border: 1px solid #ffc9c2;
}
.wd-device-name {
    font-size: 1.7rem;
    font-weight: 700;
    color: #1a1d23;
    margin-bottom: 4px;
    line-height: 1.25;
}
.wd-imei-label {
    font-size: 0.88rem;
    color: #777;
    margin-bottom: 0;
}
.wd-imei-value {
    color: #3b82f6;
    font-weight: 600;
}

/* Stats cards */
.wd-stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}
@media (max-width: 768px) {
    .wd-stats-row { grid-template-columns: 1fr; }
    .wd-device-card { flex-direction: column; text-align: center; }
}
.wd-stat-card {
    background: #fff;
    border-radius: 16px;
    padding: 22px 20px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    position: relative;
    overflow: hidden;
}
.wd-stat-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    margin-bottom: 10px;
}
.wd-stat-label {
    font-size: 0.82rem;
    color: #888;
    font-weight: 500;
    margin-bottom: 4px;
}
.wd-stat-value {
    font-size: 0.93rem;
    color: #222;
    font-weight: 600;
}
.wd-stat-sub {
    font-size: 1.8rem;
    font-weight: 800;
    line-height: 1;
    margin-top: 2px;
}
.wd-days-number { color: #3b82f6; }
.wd-stat-activated {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 600;
    background: #e8f9ef;
    color: #16a34a;
    margin-top: 4px;
}

/* Repair History */
.wd-history-section {
    background: #fff;
    border-radius: 20px;
    padding: 28px 32px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    margin-bottom: 40px;
}
.wd-history-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}
.wd-history-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a1d23;
    display: flex;
    align-items: center;
    gap: 10px;
}
.wd-history-title .bar {
    width: 4px;
    height: 20px;
    background: #3b82f6;
    border-radius: 2px;
}
.wd-repair-meta {
    font-size: 0.8rem;
    color: #999;
}
/* Timeline */
.wd-timeline { position: relative; padding-left: 28px; }
.wd-timeline::before {
    content: '';
    position: absolute;
    left: 7px;
    top: 6px;
    bottom: 6px;
    width: 2px;
    background: #e5e8ed;
    border-radius: 1px;
}
.wd-timeline-item {
    position: relative;
    margin-bottom: 28px;
    animation: fadeSlideUp .4s ease both;
}
.wd-timeline-item:last-child { margin-bottom: 0; }
.wd-timeline-dot {
    position: absolute;
    left: -28px;
    top: 4px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #3b82f6;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #3b82f6;
    flex-shrink: 0;
}
.wd-timeline-date {
    font-size: 0.78rem;
    color: #888;
    font-weight: 500;
    margin-bottom: 4px;
    letter-spacing: .3px;
}
.wd-timeline-card {
    background: #f7f9fc;
    border-radius: 12px;
    padding: 14px 18px;
    border-left: 3px solid #3b82f6;
}
.wd-timeline-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1a1d23;
    margin-bottom: 4px;
}
.wd-timeline-card-desc {
    font-size: 0.85rem;
    color: #555;
    margin-bottom: 8px;
    line-height: 1.55;
}
.wd-timeline-card-loc {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.78rem;
    color: #888;
}
.wd-repair-id {
    float: right;
    font-size: 0.75rem;
    color: #b0b8c9;
    font-family: monospace;
}
.wd-no-history {
    text-align: center;
    padding: 32px 0;
    color: #b0b8c9;
    font-size: 0.92rem;
}
.wd-no-history i { font-size: 2rem; display: block; margin-bottom: 10px; }

/* Loading/Error */
.wd-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 320px;
    flex-direction: column;
    gap: 16px;
    color: #888;
}
.wd-error-box {
    background: #fff8f7;
    border: 1px solid #ffd0ca;
    border-radius: 16px;
    padding: 32px;
    text-align: center;
}

@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<main style="background: #f7f8fa; min-height: 80vh; padding-bottom: 60px;">
    <div class="wd-hero">
        <div class="container px-xl-5">
            <a href="warranty.php" class="wd-back-link">
                <i class="bi bi-arrow-left"></i> Quay lại Tra cứu Bảo hành
            </a>
            <h1 class="h4 fw-bold text-dark mb-0">Thông tin Bảo hành Chi tiết</h1>
            <p class="text-secondary small mb-0 mt-1">Xem trạng thái bảo hành và lịch sử sửa chữa thiết bị của bạn</p>
        </div>
    </div>

    <div class="container px-xl-5 mt-4">

        <!-- Loading State -->
        <div id="wdLoading" class="wd-loading">
            <div class="spinner-border text-primary" role="status"></div>
            <span>Đang tải thông tin bảo hành...</span>
        </div>

        <!-- Error state -->
        <div id="wdError" style="display:none;">
            <div class="wd-error-box">
                <i class="bi bi-shield-exclamation text-danger fs-1 d-block mb-3"></i>
                <h5 class="fw-bold text-dark mb-2">Không tìm thấy dữ liệu</h5>
                <p class="text-secondary small mb-4" id="wdErrorMsg">Không tìm thấy thông tin bảo hành cho IMEI này.</p>
                <a href="warranty.php" class="btn btn-primary rounded-pill px-4">Tra cứu lại</a>
            </div>
        </div>

        <!-- Content -->
        <div id="wdContent" style="display:none;">

            <!-- Device Card -->
            <div class="wd-device-card" id="wdDeviceCard">
                <div id="wdDeviceImgWrap"></div>
                <div class="flex-grow-1">
                    <div id="wdStatusBadge"></div>
                    <div class="wd-device-name" id="wdDeviceName">—</div>
                    <p class="wd-imei-label">Số IMEI: <span class="wd-imei-value" id="wdImeiVal">—</span></p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="wd-stats-row">
                <!-- Bảo hành tiêu chuẩn -->
                <div class="wd-stat-card">
                    <div class="wd-stat-icon" style="background:#eff6ff; color:#3b82f6;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div class="wd-stat-label">Bảo hành tiêu chuẩn</div>
                    <div class="wd-stat-value" id="wdExpireDate">—</div>
                    <div id="wdDaysBlock"></div>
                </div>
                <!-- Bảo hành rơi vỡ -->
                <div class="wd-stat-card">
                    <div class="wd-stat-icon" style="background:#fff7f0; color:#f97316;">
                        <i class="bi bi-heart"></i>
                    </div>
                    <div class="wd-stat-label">Bảo hành rơi vỡ</div>
                    <div class="wd-stat-value text-secondary small" style="font-size:.8rem; font-weight:400;">Gói dịch vụ cao cấp</div>
                    <span class="wd-stat-activated"><i class="bi bi-check-circle-fill"></i> Đang kích hoạt</span>
                </div>
                <!-- Bảo hành Pin -->
                <div class="wd-stat-card">
                    <div class="wd-stat-icon" style="background:#f0fdf8; color:#10b981;">
                        <i class="bi bi-battery-charging"></i>
                    </div>
                    <div class="wd-stat-label">Bảo hành Pin</div>
                    <div class="wd-stat-value text-secondary small" style="font-size:.8rem; font-weight:400;">Tình trạng bảo trì</div>
                    <div class="wd-stat-sub" style="color: #10b981;">92 <span style="font-size:1rem; font-weight:600;">%</span></div>
                </div>
            </div>

            <!-- Repair History -->
            <div class="wd-history-section">
                <div class="wd-history-header">
                    <div class="wd-history-title">
                        <span class="bar"></span>
                        Lịch sử sửa chữa
                    </div>
                    <span class="wd-repair-meta" id="wdLatestActivity"></span>
                </div>
                <div id="wdTimeline"></div>
            </div>

        </div><!-- /wdContent -->
    </div>
</main>

<script>
(function () {
    const imei = <?= json_encode($imei) ?>;

    if (!imei) {
        showError('Không có mã IMEI trong yêu cầu.');
        return;
    }

    fetch('api/warranty_detail.php?imei=' + encodeURIComponent(imei))
        .then(r => r.json())
        .then(res => {
            document.getElementById('wdLoading').style.display = 'none';
            if (res.status !== 'success') {
                showError(res.message || 'Không tìm thấy dữ liệu bảo hành.');
                return;
            }
            renderDetail(res.data);
        })
        .catch(() => showError('Lỗi kết nối máy chủ. Vui lòng thử lại sau.'));

    function showError(msg) {
        document.getElementById('wdLoading').style.display = 'none';
        document.getElementById('wdErrorMsg').textContent = msg;
        document.getElementById('wdError').style.display = 'block';
    }

    function renderDetail(d) {
        const isActive = !d.is_expired && d.warranty_status === 'Active';

        // Device image
        const imgWrap = document.getElementById('wdDeviceImgWrap');
        if (d.product_image) {
            imgWrap.innerHTML = `<img src="assets/images/${escHtml(d.product_image)}" class="wd-device-img" alt="${escHtml(d.product_name)}" onerror="this.src='https://placehold.co/110x140'">`;
        } else {
            imgWrap.innerHTML = `<div class="wd-device-img-placeholder"><i class="bi bi-phone"></i></div>`;
        }

        // Status badge
        const badge = document.getElementById('wdStatusBadge');
        if (isActive) {
            badge.innerHTML = `<span class="wd-status-badge active"><i class="bi bi-check-circle-fill"></i> Đang trong thời hạn bảo hành</span>`;
        } else {
            badge.innerHTML = `<span class="wd-status-badge expired"><i class="bi bi-x-circle-fill"></i> Hết hạn bảo hành</span>`;
        }

        document.getElementById('wdDeviceName').textContent = d.product_name;
        document.getElementById('wdImeiVal').textContent = d.imei;

        // Expire date & days
        document.getElementById('wdExpireDate').textContent = 'Hết hạn: ' + d.expires_at;
        const daysBlock = document.getElementById('wdDaysBlock');
        if (isActive) {
            daysBlock.innerHTML = `<div class="wd-stat-sub wd-days-number">${d.days_left} <span style="font-size:0.9rem; color:#888; font-weight:500;">ngày còn lại</span></div>`;
        } else {
            daysBlock.innerHTML = `<div style="color:#dc2626; font-size:0.82rem; font-weight:600; margin-top:4px;">Đã hết hạn</div>`;
        }

        // Latest activity label
        const latestLabel = document.getElementById('wdLatestActivity');
        if (d.repair_history && d.repair_history.length > 0) {
            const latestId = d.repair_history[0].repair_id;
            latestLabel.innerHTML = `<i class="bi bi-clock-history me-1"></i> Hoạt động gần nhất &nbsp;&nbsp; <span style="color:#3b82f6; font-weight:600; font-family:monospace;">ID: ${escHtml(latestId || '#—')}</span>`;
        } else {
            latestLabel.textContent = '';
        }

        // Timeline
        renderTimeline(d.repair_history || []);

        document.getElementById('wdContent').style.display = 'block';
    }

    function renderTimeline(history) {
        const container = document.getElementById('wdTimeline');
        if (!history.length) {
            container.innerHTML = `
                <div class="wd-no-history">
                    <i class="bi bi-clipboard-x"></i>
                    Chưa có lịch sử sửa chữa nào được ghi nhận.
                </div>`;
            return;
        }

        let html = '<div class="wd-timeline">';
        history.forEach((item, idx) => {
            html += `
            <div class="wd-timeline-item" style="animation-delay:${idx * 0.07}s;">
                <div class="wd-timeline-dot"></div>
                <div class="wd-timeline-date">${escHtml(item.repair_date)}</div>
                <div class="wd-timeline-card">
                    ${item.repair_id ? `<span class="wd-repair-id">ID: ${escHtml(item.repair_id)}</span>` : ''}
                    <div class="wd-timeline-card-title">${escHtml(item.title)}</div>
                    ${item.description ? `<div class="wd-timeline-card-desc">${escHtml(item.description)}</div>` : ''}
                    ${item.location ? `<div class="wd-timeline-card-loc"><i class="bi bi-geo-alt-fill text-primary"></i> ${escHtml(item.location)}</div>` : ''}
                </div>
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
</script>

<?php include 'includes/footer.php'; ?>
