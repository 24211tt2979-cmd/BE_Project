<?php
require_once 'admin_auth.php';
require_once '../includes/db.php';

// 1. Thu thập dữ liệu thống kê
// Tổng doanh thu (Các đơn thành công/hoàn thành)
$stmtRevenue = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status = 'Completed' OR status = 'Hoàn thành' OR status = 'Đã giao' OR status = 'Thành công'");
$totalRevenue = $stmtRevenue->fetchColumn() ?: 0;

// Tổng số đơn hàng
$stmtOrders = $pdo->query("SELECT COUNT(*) FROM orders");
$totalOrders = $stmtOrders->fetchColumn();

// Đơn hàng chờ duyệt (Pending)
$stmtPending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending' OR status = 'Chờ duyệt'");
$pendingOrdersCount = $stmtPending->fetchColumn();

$stmtUsers = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmtUsers->fetchColumn();

$stmtProducts = $pdo->query("SELECT COUNT(*) FROM products");
$totalProducts = $stmtProducts->fetchColumn();

// 2. Lấy danh sách 5 đơn hàng gần nhất
$stmtRecent = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5");
$recentOrders = $stmtRecent->fetchAll();

// 3. Thu thập dữ liệu vẽ biểu đồ doanh thu 7 ngày gần nhất
$labels = [];
$revenues = [];
$daysOfWeekMap = [
    'Sunday' => 'Chủ Nhật', 'Monday' => 'Thứ 2', 'Tuesday' => 'Thứ 3',
    'Wednesday' => 'Thứ 4', 'Thursday' => 'Thứ 5', 'Friday' => 'Thứ 6', 'Saturday' => 'Thứ 7'
];
$last7days = [];
for ($i = 6; $i >= 0; $i--) {
    $last7days[date('Y-m-d', strtotime("-$i days"))] = 0;
}

$isPostgres = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
$whereCondition = "(status = 'Completed' OR status = 'Hoàn thành' OR status = 'Đã giao' OR status = 'Thành công')";

if ($isPostgres) {
    $stmtChart = $pdo->query("SELECT CAST(created_at AS DATE) as raw_date, SUM(total_price) as revenue FROM orders WHERE $whereCondition AND created_at >= CURRENT_DATE - INTERVAL '6 days' GROUP BY CAST(created_at AS DATE) ORDER BY raw_date ASC");
} else {
    $stmtChart = $pdo->query("SELECT DATE(created_at) as raw_date, SUM(total_price) as revenue FROM orders WHERE $whereCondition AND created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at) ORDER BY raw_date ASC");
}
$chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);

foreach ($chartData as $row) {
    if (isset($last7days[$row['raw_date']])) {
        $last7days[$row['raw_date']] = $row['revenue'];
    }
}

foreach ($last7days as $date => $revenue) {
    $ts = strtotime($date);
    $dow = $daysOfWeekMap[date('l', $ts)];
    $labels[] = mb_strtoupper($dow, 'UTF-8');
    $revenues[] = $revenue;
}

$pageTitle = "Dashboard | Admin NHK Mobile";
$basePath = "../";
include 'includes/admin_header.php';
?>

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

        <!-- Thống kê tổng quan (Dashboard cards) -->
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

        <!-- Biểu đồ doanh thu doanh thu tổng quan theo ngày/tháng -->
        <div class="card border-0 shadow-sm rounded-4 mb-5">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="fw-bold mb-0">Biểu đồ thống kê tổng quan doanh thu (7 ngày gần nhất)</h5>
                    <a href="revenue.php" class="text-primary fw-bold text-decoration-none small">Xem chi tiết <i class="bi bi-chevron-right ms-1"></i></a>
                </div>
                <div style="height: 250px;">
                    <canvas id="dashboardChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Đơn hàng gần đây -->
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
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recentOrders as $o): ?>
                        <tr>
                            <td class="fw-bold text-muted small">#ORD-<?php echo $o['id']; ?></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($o['customer_name']); ?></div>
                                <div class="text-muted small" style="font-size: 11px;"><i class="bi bi-phone"></i> <?php echo htmlspecialchars($o['customer_phone']); ?></div>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($o['created_at'])); ?></td>
                            <td class="fw-bold text-primary"><?php echo number_format($o['total_price'], 0, ',', '.'); ?>₫</td>
                            <td>
                                <?php 
                                    $badgeClass = 'bg-warning text-dark';
                                    $s = mb_strtolower($o['status'], 'UTF-8');
                                    if (str_contains($s, 'đã duyệt')) $badgeClass = 'bg-info text-white';
                                    elseif (str_contains($s, 'đang giao')) $badgeClass = 'bg-primary text-white';
                                    elseif (str_contains($s, 'hoàn thành') || str_contains($s, 'completed')) $badgeClass = 'bg-success text-white';
                                    elseif (str_contains($s, 'hủy') || str_contains($s, 'cancel')) $badgeClass = 'bg-danger text-white';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?> border-0 px-3 py-1 rounded-pill small">
                                    <?php echo htmlspecialchars($o['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="orders.php" class="btn btn-light btn-sm rounded-3 px-3"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(count($recentOrders) === 0): ?>
                        <tr><td colspan="6" class="text-center py-4 text-secondary">Chưa có đơn hàng nào trong hệ thống.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('dashboardChart').getContext('2d');
    
    const labels = <?php echo json_encode($labels); ?>;
    const dataCurrent = <?php echo json_encode($revenues); ?>;
    
    let gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, '#0ea5e9');
    gradient.addColorStop(1, 'rgba(14, 165, 233, 0.1)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Doanh thu',
                    data: dataCurrent,
                    borderColor: '#0ea5e9',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#0ea5e9',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            let value = context.raw || 0;
                            return ' Doanh thu: ' + new Intl.NumberFormat('vi-VN').format(value) + 'đ';
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
                        callback: function(value) {
                            if (value >= 1000000) return (value / 1000000) + 'M';
                            if (value >= 1000) return (value / 1000) + 'K';
                            return value;
                        }
                    }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: {
                        color: '#64748b',
                        font: { weight: '600' }
                    }
                }
            }
        }
    });
});
</script>

<?php include 'includes/admin_footer.php'; ?>
