<?php
require_once 'admin_auth.php';
require_once '../includes/db.php';

// ── HELPERS ──────────────────────────────────────────────────────────────
$isPostgres = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
$completedCondition = "(status = 'Completed' OR status = 'Hoàn thành')";

function queryChart($pdo, $sql, $isPostgres) {
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// ── 1. STAT CARDS ────────────────────────────────────────────────────────
$totalRevenue = $pdo->query("SELECT SUM(total_price) FROM orders WHERE $completedCondition")->fetchColumn() ?: 0;
$totalOrders  = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending' OR status = 'Chờ xác nhận'")->fetchColumn();
$totalUsers   = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalProducts= $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// ── 2. DAILY REVENUE (7 ngày) ───────────────────────────────────────────
$daysOfWeekMap = ['Sunday'=>'CN','Monday'=>'T2','Tuesday'=>'T3','Wednesday'=>'T4','Thursday'=>'T5','Friday'=>'T6','Saturday'=>'T7'];
$dailyLabels = []; $dailyRev = [];
$last7 = [];
for ($i = 6; $i >= 0; $i--) $last7[date('Y-m-d', strtotime("-$i days"))] = 0;

$sql = $isPostgres
    ? "SELECT CAST(created_at AS DATE) d, SUM(total_price) r FROM orders WHERE $completedCondition AND created_at >= CURRENT_DATE - INTERVAL '6 days' GROUP BY d ORDER BY d"
    : "SELECT DATE(created_at) d, SUM(total_price) r FROM orders WHERE $completedCondition AND created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY d ORDER BY d";
foreach (queryChart($pdo, $sql, $isPostgres) as $row) if (isset($last7[$row['d']])) $last7[$row['d']] = $row['r'];
foreach ($last7 as $date => $rev) {
    $dailyLabels[] = $daysOfWeekMap[date('l', strtotime($date))];
    $dailyRev[]    = $rev;
}

// ── 3. MONTHLY REVENUE (12 tháng) ───────────────────────────────────────
$monthlyLabels = []; $monthlyRev = [];
$monthNames = ['1','2','3','4','5','6','7','8','9','10','11','12'];
$last12 = [];
for ($i = 5; $i >= 0; $i--) $last12[date('Y-m', strtotime("-$i months"))] = 0;

$sql = $isPostgres
    ? "SELECT TO_CHAR(created_at, 'YYYY-MM') m, SUM(total_price) r FROM orders WHERE $completedCondition AND created_at >= CURRENT_DATE - INTERVAL '5 months' GROUP BY m ORDER BY m"
    : "SELECT DATE_FORMAT(created_at, '%Y-%m') m, SUM(total_price) r FROM orders WHERE $completedCondition AND created_at >= CURDATE() - INTERVAL 5 MONTH GROUP BY m ORDER BY m";
foreach (queryChart($pdo, $sql, $isPostgres) as $row) if (isset($last12[$row['m']])) $last12[$row['m']] = $row['r'];
foreach ($last12 as $ym => $rev) {
    $monthlyLabels[] = 'T'.$monthNames[(int)substr($ym, 5)];
    $monthlyRev[]    = $rev;
}

// ── 4. PIE: Trạng thái đơn hàng ─────────────────────────────────────────
$statusLabels = []; $statusData = [];
$statusColors = ['#f59e0b','#3b82f6','#22c55e','#ef4444'];
$statusMap = ['Chờ xác nhận','Đang giao','Hoàn thành','Đã hủy'];
foreach ($statusMap as $s) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = ?");
    $c->execute([$s]);
    $statusLabels[] = $s;
    $statusData[]   = $c->fetchColumn();
}

// ── 5. PIE: Phương thức thanh toán ──────────────────────────────────────
$pmLabels = []; $pmData = [];
$pmColors = ['#10b981','#f59e0b','#6366f1'];
$pmMap = ['COD','TienMat','GiaLapOnline'];
$pmName = ['COD (Trả khi nhận)','Tiền mặt (Tại cửa hàng)','Giả lập Online'];
foreach ($pmMap as $i => $v) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE payment_method = ?");
    $c->execute([$v]);
    $pmLabels[] = $pmName[$i];
    $pmData[]   = $c->fetchColumn();
}

// ── 6. PIE: Top 5 sản phẩm bán chạy ────────────────────────────────────
$topProducts = [];
try {
    $tp = $pdo->query("SELECT oi.product_name, SUM(oi.quantity) q FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE $completedCondition GROUP BY oi.product_name ORDER BY q DESC LIMIT 5");
    $topProducts = $tp->fetchAll();
} catch (Exception $e) {}
$prodLabels = []; $prodData = [];
$prodColors = ['#ef4444','#f97316','#eab308','#22c55e','#3b82f6'];
foreach ($topProducts as $p) {
    $prodLabels[] = mb_strlen($p['product_name']) > 18 ? mb_substr($p['product_name'], 0, 16).'...' : $p['product_name'];
    $prodData[]   = $p['q'];
}

// ── 7. Recent orders ────────────────────────────────────────────────────
$stmtRecent = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5");
$recentOrders = $stmtRecent->fetchAll();

$pageTitle = "Dashboard | Admin NHK Mobile";
$basePath = "../";
include 'includes/admin_header.php';
?>

<style>
.chart-tabs .btn-tab {
    padding: 6px 18px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    border: 1.5px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
}
.chart-tabs .btn-tab.active {
    background: #0ea5e9;
    color: #fff;
    border-color: #0ea5e9;
}
.chart-tabs .btn-tab:hover:not(.active) {
    border-color: #0ea5e9;
    color: #0ea5e9;
}
.pie-card {
    min-height: 280px;
}
</style>

<header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
     <div>
        <h1 class="fw-bold h2 mb-1">Tổng quan hệ thống</h1>
        <p class="text-secondary fw-500 mb-0">Chào mừng trở lại, Admin NHK Mobile</p>
     </div>
     <div class="d-flex gap-2">
         <a href="revenue.php" class="btn btn-primary rounded-3 fw-bold px-4 py-2 small shadow-sm text-decoration-none">
             <i class="bi bi-graph-up me-2"></i>Xem báo cáo chi tiết
         </a>
     </div>
</header>

<!-- STAT CARDS -->
<div class="row g-4 mb-5">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary-light text-primary"><i class="bi bi-currency-dollar"></i></div>
            <div class="stat-label">Tổng doanh thu</div>
            <div class="stat-value"><?php echo number_format($totalRevenue, 0, ',', '.'); ?>₫</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-receipt"></i></div>
            <div class="stat-label">Tổng số đơn hàng</div>
            <div class="stat-value"><?php echo $totalOrders; ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-people"></i></div>
            <div class="stat-label">Khách hàng</div>
            <div class="stat-value"><?php echo $totalUsers; ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-box-seam"></i></div>
            <div class="stat-label">Sản phẩm</div>
            <div class="stat-value"><?php echo $totalProducts; ?></div>
        </div>
    </div>
</div>

<!-- BIỂU ĐỒ DOANH THU (TABS: NGÀY / THÁNG) -->
<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
            <h5 class="fw-bold mb-0">Biểu đồ doanh thu</h5>
            <div class="chart-tabs d-flex gap-2">
                <button class="btn-tab active" data-tab="daily">Theo ngày</button>
                <button class="btn-tab" data-tab="monthly">Theo tháng</button>
            </div>
        </div>
        <div style="height: 280px;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
</div>

<!-- BIỂU ĐỒ TRÒN (PIE / DOUGHNUT) -->
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 pie-card">
            <div class="card-body p-4 d-flex flex-column">
                <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart me-2 text-primary"></i>Trạng thái đơn hàng</h6>
                <div class="flex-grow-1" style="min-height:200px;">
                    <canvas id="statusPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 pie-card">
            <div class="card-body p-4 d-flex flex-column">
                <h6 class="fw-bold mb-3"><i class="bi bi-credit-card me-2 text-success"></i>Phương thức thanh toán</h6>
                <div class="flex-grow-1" style="min-height:200px;">
                    <canvas id="paymentPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 pie-card">
            <div class="card-body p-4 d-flex flex-column">
                <h6 class="fw-bold mb-3"><i class="bi bi-box me-2 text-warning"></i>Top 5 sản phẩm bán chạy</h6>
                <div class="flex-grow-1" style="min-height:200px;">
                    <canvas id="productPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ĐƠN HÀNG GẦN ĐÂY -->
<div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold h5 mb-0">Đơn hàng gần đây</h3>
        <a href="orders.php" class="text-primary fw-bold text-decoration-none small">Xem tất cả <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Khách hàng</th>
                    <th>Ngày đặt</th>
                    <th>Tổng tiền</th>
                    <th>Thanh toán</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recentOrders as $o): ?>
                <tr>
                    <td class="fw-bold text-muted small">#ORD-<?php echo $o['id']; ?></td>
                    <td>
                        <div class="fw-bold"><?php echo htmlspecialchars($o['customer_name']); ?></div>
                        <div class="text-muted small" style="font-size:11px;"><i class="bi bi-phone"></i> <?php echo htmlspecialchars($o['customer_phone']); ?></div>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($o['created_at'])); ?></td>
                    <td class="fw-bold text-primary"><?php echo number_format($o['total_price'], 0, ',', '.'); ?>₫</td>
                    <td><span class="badge bg-light text-dark border fw-normal px-2"><?php echo htmlspecialchars($o['payment_method']); ?></span></td>
                    <td>
                        <?php
                            $badgeClass = 'bg-warning text-dark';
                            $s = mb_strtolower($o['status'], 'UTF-8');
                            if (str_contains($s, 'đang giao')) $badgeClass = 'bg-primary text-white';
                            elseif (str_contains($s, 'hoàn thành') || str_contains($s, 'completed')) $badgeClass = 'bg-success text-white';
                            elseif (str_contains($s, 'hủy') || str_contains($s, 'cancel')) $badgeClass = 'bg-danger text-white';
                        ?>
                        <span class="badge <?php echo $badgeClass; ?> border-0 px-3 py-1 rounded-pill small"><?php echo htmlspecialchars($o['status']); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($recentOrders) === 0): ?>
                <tr><td colspan="6" class="text-center py-4 text-secondary">Chưa có đơn hàng nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── DATA FROM PHP ─────────────────────────────────────────────────────
    const dailyLabels  = <?php echo json_encode($dailyLabels); ?>;
    const dailyData    = <?php echo json_encode($dailyRev); ?>;
    const monthLabels  = <?php echo json_encode($monthlyLabels); ?>;
    const monthData    = <?php echo json_encode($monthlyRev); ?>;

    const statusLabels = <?php echo json_encode($statusLabels); ?>;
    const statusData   = <?php echo json_encode($statusData); ?>;
    const statusColors = <?php echo json_encode($statusColors); ?>;

    const pmLabels     = <?php echo json_encode($pmLabels); ?>;
    const pmData       = <?php echo json_encode($pmData); ?>;
    const pmColors     = <?php echo json_encode($pmColors); ?>;

    const prodLabels   = <?php echo json_encode($prodLabels); ?>;
    const prodData     = <?php echo json_encode($prodData); ?>;
    const prodColors   = <?php echo json_encode($prodColors); ?>;

    // ── 1. REVENUE CHART (tabs: daily / monthly) ─────────────────────────
    const ctx = document.getElementById('revenueChart').getContext('2d');
    let currentTab = 'daily';

    function buildGradient(ctx, color) {
        let g = ctx.createLinearGradient(0, 0, 0, 280);
        g.addColorStop(0, color);
        g.addColorStop(1, color + '1a');
        return g;
    }

    const revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dailyLabels,
            datasets: [{
                label: 'Doanh thu',
                data: dailyData,
                borderColor: '#0ea5e9',
                backgroundColor: buildGradient(ctx, '#0ea5e9'),
                borderWidth: 3,
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#0ea5e9',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    callbacks: {
                        label: function(ctx) {
                            return ' Doanh thu: ' + new Intl.NumberFormat('vi-VN').format(ctx.raw || 0) + '₫';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9', drawBorder: false },
                    ticks: {
                        color: '#94a3b8',
                        callback: function(v) {
                            if (v >= 1e6) return (v/1e6).toFixed(1) + 'M';
                            if (v >= 1e3) return (v/1e3).toFixed(0) + 'K';
                            return v;
                        }
                    }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { color: '#64748b', font: { weight: '600' } }
                }
            }
        }
    });

    // Tab switching
    document.querySelectorAll('.btn-tab').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.btn-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentTab = this.dataset.tab;

            if (currentTab === 'daily') {
                revenueChart.data.labels = dailyLabels;
                revenueChart.data.datasets[0].data = dailyData;
            } else {
                revenueChart.data.labels = monthLabels;
                revenueChart.data.datasets[0].data = monthData;
            }
            revenueChart.update();
        });
    });

    // ── 2. PIE: Order Status ─────────────────────────────────────────────
    new Chart(document.getElementById('statusPieChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: statusColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 12, usePointStyle: true, font: { size: 11, weight: '600' } }
                }
            }
        }
    });

    // ── 3. PIE: Payment Method ───────────────────────────────────────────
    new Chart(document.getElementById('paymentPieChart'), {
        type: 'doughnut',
        data: {
            labels: pmLabels,
            datasets: [{
                data: pmData,
                backgroundColor: pmColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 12, usePointStyle: true, font: { size: 11, weight: '600' } }
                }
            }
        }
    });

    // ── 4. PIE: Top Products ─────────────────────────────────────────────
    new Chart(document.getElementById('productPieChart'), {
        type: 'doughnut',
        data: {
            labels: prodLabels,
            datasets: [{
                data: prodData,
                backgroundColor: prodColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 12, usePointStyle: true, font: { size: 10, weight: '600' } }
                }
            }
        }
    });

});
</script>

<?php include 'includes/admin_footer.php'; ?>
