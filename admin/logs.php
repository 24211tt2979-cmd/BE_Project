<?php
// Bắt đầu phiên làm việc
require_once 'admin_auth.php';

// Nhúng file kết nối CSDL MySQL
require_once '../includes/db.php';

$pageTitle = "Nhật ký hệ thống | Admin NHK Mobile";
$basePath = "../";

/**
 * TRUY VẤN DANH SÁCH LOGS
 */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$action_filter = isset($_GET['action_filter']) ? trim($_GET['action_filter']) : '';

// Cấu hình phân trang
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$whereClause = " WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereClause .= " AND (l.details LIKE ? OR a.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($action_filter !== '') {
    $whereClause .= " AND l.action_type = ?";
    $params[] = $action_filter;
}

// Đếm tổng số bản ghi
$sqlCount = "SELECT COUNT(*) FROM admin_logs l LEFT JOIN admins a ON l.admin_id = a.id" . $whereClause;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT l.*, a.username as admin_username FROM admin_logs l LEFT JOIN admins a ON l.admin_id = a.id" . $whereClause . " ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Chuỗi query string cho phân trang
$queryString = "";
if ($search) $queryString .= '&search='.urlencode($search);
if ($action_filter) $queryString .= '&action_filter='.urlencode($action_filter);

// Lấy danh sách các loại action để đưa vào filter
$actionStmt = $pdo->query("SELECT DISTINCT action_type FROM admin_logs ORDER BY action_type");
$actionTypes = $actionStmt->fetchAll(PDO::FETCH_COLUMN);

include 'includes/admin_header.php';
?>

        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                 <h2 class="fw-bold mb-1">Nhật ký hệ thống</h2>
                 <p class="text-secondary small mb-0">Theo dõi toàn bộ lịch sử thao tác của các Quản trị viên trên hệ thống.</p>
            </div>
        </header>

        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
            <form action="" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control bg-light border-0" placeholder="Tìm kiếm theo chi tiết thao tác hoặc tên Admin..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="action_filter" class="form-select bg-light border-0">
                        <option value="">Tất cả loại thao tác</option>
                        <?php foreach($actionTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($action_filter == $type) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-3 shadow-sm w-100"><i class="bi bi-funnel"></i> Lọc</button>
                    <?php if ($search || $action_filter): ?>
                        <a href="logs.php" class="btn btn-outline-secondary px-3 shadow-sm">Xóa</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="content-card shadow-sm border-0 rounded-4 p-4 bg-white">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr class="small text-uppercase text-secondary">
                            <th>Thời gian</th>
                            <th>Quản trị viên</th>
                            <th>Loại thao tác</th>
                            <th>Chi tiết</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $log): ?>
                        <tr>
                            <td class="small text-secondary text-nowrap">
                                <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-dark text-white d-flex justify-content-center align-items-center me-2" style="width: 32px; height: 32px; font-size: 14px;">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <span class="fw-bold"><?php echo htmlspecialchars($log['admin_username'] ?? 'Hệ thống/Đã xóa'); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-normal text-uppercase" style="font-size: 0.75rem;">
                                    <?php echo htmlspecialchars($log['action_type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-dark"><?php echo htmlspecialchars($log['details']); ?></span>
                            </td>
                            <td class="small text-secondary">
                                <?php echo htmlspecialchars($log['ip_address']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($logs) === 0): ?>
                        <tr><td colspan="5" class="text-center py-5 text-secondary">
                            <i class="bi bi-journal-x fs-1 text-light mb-3 d-block"></i>
                            Chưa có dữ liệu nhật ký nào.
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination UI -->
            <?php if (isset($totalPages) && $totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-end mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $queryString; ?>">Trước</a>
                    </li>
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1' . $queryString . '">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $queryString; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; 
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . $queryString . '">' . $totalPages . '</a></li>';
                    }
                    ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $queryString; ?>">Sau</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>

    </main>
</div>
</div>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
