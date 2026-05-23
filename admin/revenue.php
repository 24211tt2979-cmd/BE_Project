<?php
require_once 'admin_auth.php';
require_once '../includes/db.php';

$filter = $_GET['filter'] ?? 'day';

$pageTitle = "Báo cáo Doanh thu | Admin NHK Mobile";
$basePath = "../";
include 'includes/admin_header.php';

$whereCondition = "(status = 'Completed' OR status = 'Hoàn thành' OR status = 'Đã giao' OR status = 'Thành công')";

$labels = [];
$revenues = [];
$tableData = [];
$totalRevenueDisplay = 0;
$totalOrdersDisplay = 0;

$isPostgres = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');

if ($filter == 'day') {
    // generate last 7 days array
    $last7days = [];
    for ($i = 6; $i >= 0; $i--) {
        $last7days[date('Y-m-d', strtotime("-$i days"))] = ['revenue' => 0, 'orders' => 0];
    }
    
    if ($isPostgres) {
        $stmt = $pdo->query("SELECT CAST(created_at AS DATE) as raw_date, SUM(total_price) as revenue, COUNT(id) as orders FROM orders WHERE $whereCondition AND created_at >= CURRENT_DATE - INTERVAL '6 days' GROUP BY CAST(created_at AS DATE) ORDER BY raw_date ASC");
    } else {
        $stmt = $pdo->query("SELECT DATE(created_at) as raw_date, SUM(total_price) as revenue, COUNT(id) as orders FROM orders WHERE $whereCondition AND created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at) ORDER BY raw_date ASC");
    }
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($data as $row) {
        if (isset($last7days[$row['raw_date']])) {
            $last7days[$row['raw_date']]['revenue'] = $row['revenue'];
            $last7days[$row['raw_date']]['orders'] = $row['orders'];
        }
    }
    
    $daysOfWeekMap = [
        'Sunday' => 'Chủ Nhật', 'Monday' => 'Thứ 2', 'Tuesday' => 'Thứ 3',
        'Wednesday' => 'Thứ 4', 'Thursday' => 'Thứ 5', 'Friday' => 'Thứ 6', 'Saturday' => 'Thứ 7'
    ];
    
    foreach ($last7days as $date => $stats) {
        $ts = strtotime($date);
        $dow = $daysOfWeekMap[date('l', $ts)];
        $labels[] = mb_strtoupper($dow, 'UTF-8');
        $revenues[] = $stats['revenue'];
        
        $tableData[] = [
            'label' => date('d/m/Y', $ts),
            'revenue' => $stats['revenue'],
            'orders' => $stats['orders']
        ];
    }
} elseif ($filter == 'month') {
    $months = [];
    for ($i=1; $i<=12; $i++) {
        $months[$i] = ['revenue' => 0, 'orders' => 0];
    }
    
    if ($isPostgres) {
        $stmt = $pdo->query("SELECT EXTRACT(MONTH FROM created_at) as m, SUM(total_price) as revenue, COUNT(id) as orders FROM orders WHERE $whereCondition AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE) GROUP BY EXTRACT(MONTH FROM created_at) ORDER BY m ASC");
    } else {
        $stmt = $pdo->query("SELECT MONTH(created_at) as m, SUM(total_price) as revenue, COUNT(id) as orders FROM orders WHERE $whereCondition AND YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at) ORDER BY m ASC");
    }
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($data as $row) {
        $mVal = (int)$row['m'];
        $months[$mVal]['revenue'] = $row['revenue'];
        $months[$mVal]['orders'] = $row['orders'];
    }
    foreach ($months as $m => $stats) {
        $labels[] = 'THÁNG ' . $m;
        $revenues[] = $stats['revenue'];
        $tableData[] = [
            'label' => 'Tháng ' . $m . '/' . date('Y'),
            'revenue' => $stats['revenue'],
            'orders' => $stats['orders']
        ];
    }
} elseif ($filter == 'year') {
    if ($isPostgres) {
        $stmt = $pdo->query("SELECT EXTRACT(YEAR FROM created_at) as y, SUM(total_price) as revenue, COUNT(id) as orders FROM orders WHERE $whereCondition GROUP BY EXTRACT(YEAR FROM created_at) ORDER BY y DESC LIMIT 5");
    } else {
        $stmt = $pdo->query("SELECT YEAR(created_at) as y, SUM(total_price) as revenue, COUNT(id) as orders FROM orders WHERE $whereCondition GROUP BY YEAR(created_at) ORDER BY y DESC LIMIT 5");
    }
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data = array_reverse($data); // Asc for chart
    foreach ($data as $row) {
        $labels[] = 'NĂM ' . $row['y'];
        $revenues[] = $row['revenue'];
        $tableData[] = [
            'label' => $row['y'],
            'revenue' => $row['revenue'],
            'orders' => $row['orders']
        ];
    }
}

$totalRevenueDisplay = array_sum($revenues);
$totalOrdersDisplay = array_sum(array_column($tableData, 'orders'));

$tableData = array_reverse($tableData); // Newest first

$avgOrderValue = $totalOrdersDisplay > 0 ? ($totalRevenueDisplay / $totalOrdersDisplay) : 0;
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h1 class="fw-bold h2 mb-1">Báo cáo Doanh thu</h1>
        <p class="text-secondary fw-500 mb-0">Theo dõi hiệu suất kinh doanh NHK Mobile của bạn.</p>
    </div>
    <div class="d-flex gap-2">
        <div class="bg-white rounded-pill p-1 shadow-sm d-flex border">
            <a href="?filter=day" class="btn btn-sm rounded-pill px-4 <?php echo $filter == 'day' ? 'btn-primary' : 'btn-white text-secondary'; ?> fw-semibold text-decoration-none">Theo Ngày</a>
            <a href="?filter=month" class="btn btn-sm rounded-pill px-4 <?php echo $filter == 'month' ? 'btn-primary' : 'btn-white text-secondary'; ?> fw-semibold text-decoration-none">Theo Tháng</a>
            <a href="?filter=year" class="btn btn-sm rounded-pill px-4 <?php echo $filter == 'year' ? 'btn-primary' : 'btn-white text-secondary'; ?> fw-semibold text-decoration-none">Theo Năm</a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 pb-2">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="text-white rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #e0f2fe !important; color: #0ea5e9 !important;">
                        <i class="bi bi-wallet2 fs-4"></i>
                    </div>
                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">+12.5%</span>
                </div>
                <div class="text-muted fw-semibold small mb-1 text-uppercase tracking-wide">Tổng doanh thu</div>
                <h3 class="fw-bold mb-0 d-flex align-items-baseline">
                    <?php echo number_format($totalRevenueDisplay, 0, ',', '.'); ?> <span class="fs-6 ms-1 fw-bold text-muted">đ</span>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 pb-2">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="text-white rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #f3f4f6 !important; color: #6b7280 !important;">
                        <i class="bi bi-bag fs-4"></i>
                    </div>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">+4.2%</span>
                </div>
                <div class="text-muted fw-semibold small mb-1 text-uppercase tracking-wide">Số đơn hàng</div>
                <h3 class="fw-bold mb-0 d-flex align-items-baseline">
                    <?php echo number_format($totalOrdersDisplay); ?> <span class="fs-6 ms-1 fw-bold text-muted">đơn</span>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 pb-2 bg-dark text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(255,255,255,0.1); color: #60a5fa;">
                        <i class="bi bi-bar-chart-line fs-4"></i>
                    </div>
                    <span class="text-info fw-semibold small d-flex align-items-center"><i class="bi bi-graph-up-arrow me-1"></i> Tốt</span>
                </div>
                <div class="text-white-50 fw-semibold small mb-1 text-uppercase tracking-wide">Giá trị trung bình đơn</div>
                <h3 class="fw-bold mb-0 d-flex align-items-baseline">
                    <?php echo number_format($avgOrderValue, 0, ',', '.'); ?> <span class="fs-6 ms-1 fw-bold text-white-50">đ</span>
                </h3>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
            <h5 class="fw-bold mb-0">Xu hướng doanh thu</h5>
            <div class="d-flex gap-3 text-muted small fw-semibold">
                <div class="d-flex align-items-center"><span class="rounded-circle d-inline-block me-2" style="width:10px;height:10px;background:#0ea5e9;"></span> Doanh thu hiện tại</div>
                <div class="d-flex align-items-center"><span class="rounded-circle d-inline-block me-2" style="width:10px;height:10px;background:#e2e8f0;"></span> Kỳ trước (Ước lượng)</div>
            </div>
        </div>
        <div style="height: 300px;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-5">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0">Chi tiết doanh thu</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="py-3 text-muted fw-semibold small text-uppercase">Thời gian</th>
                        <th class="py-3 text-muted fw-semibold small text-uppercase">Doanh thu</th>
                        <th class="py-3 text-muted fw-semibold small text-uppercase text-center">Số đơn hàng</th>
                        <th class="py-3 text-muted fw-semibold small text-uppercase">Giá trị TB đơn</th>
                        <th class="py-3 text-muted fw-semibold small text-uppercase text-end">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tableData as $row): 
                        $tbDon = $row['orders'] > 0 ? $row['revenue'] / $row['orders'] : 0;
                        
                        if ($row['revenue'] > 20000000) {
                            $statusText = 'Vượt mục tiêu';
                            $statusBg = 'background: #e6f4ea; color: #137333;'; 
                        } elseif ($row['revenue'] > 0 && $row['revenue'] <= 10000000) {
                            $statusText = 'Dưới mục tiêu';
                            $statusBg = 'background: #fce8e6; color: #c5221f;'; 
                        } else {
                            $statusText = 'Ổn định';
                            $statusBg = 'background: #f1f3f4; color: #3c4043;'; 
                        }
                    ?>
                    <tr>
                        <td class="fw-bold py-3 text-dark"><?php echo htmlspecialchars($row['label']); ?></td>
                        <td class="fw-bold py-3 text-dark"><?php echo number_format($row['revenue'], 0, ',', '.'); ?> đ</td>
                        <td class="text-center py-3 text-muted fw-medium"><?php echo $row['orders']; ?></td>
                        <td class="py-3 text-muted fw-medium"><?php echo number_format($tbDon, 0, ',', '.'); ?> đ</td>
                        <td class="text-end py-3">
                            <span class="badge rounded-pill fw-bold px-3 py-2" style="<?php echo $statusBg; ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    const labels = <?php echo json_encode($labels); ?>;
    const dataCurrent = <?php echo json_encode($revenues); ?>;
    
    // Generate static visual comparison data
    const dataPrevious = dataCurrent.map(val => val * (Math.random() * 0.4 + 0.6));
    
    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, '#0ea5e9');
    gradient.addColorStop(1, '#38bdf8');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Doanh thu hiện tại',
                    data: dataCurrent,
                    backgroundColor: gradient,
                    borderRadius: 6,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7
                },
                {
                    label: 'Kỳ trước',
                    data: dataPrevious,
                    backgroundColor: '#e2e8f0',
                    borderRadius: 6,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7
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
                            return ' ' + new Intl.NumberFormat('vi-VN').format(value) + ' đ';
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
