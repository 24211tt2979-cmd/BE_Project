# BÁO CÁO CHI TIẾT NHIỆM VỤ THỰC HIỆN - SINH VIÊN: KHÔI

## I. TỔNG QUAN PHẠM VI CÔNG VIỆC
Trong dự án website thương mại điện tử NHK Mobile, sinh viên Khôi đảm nhiệm các khối chức năng lõi liên quan đến **Xử lý dữ liệu danh mục**, **Hệ thống tìm kiếm**, **Quy trình đặt hàng** và **Quản trị người dùng**. Đây là các thành phần quan trọng quyết định đến luồng vận hành dữ liệu (Data Flow) từ lúc người dùng tìm kiếm sản phẩm cho đến khi hoàn tất giao dịch.

---

## II. CHI TIẾT CÁC CHỨC NĂNG ĐẢM NHIỆM

### 1. Hệ thống Danh sách & Bộ lọc sản phẩm (Catalog & Filtering)
*   **Mô tả**: Xây dựng trang danh sách sản phẩm với khả năng lọc động theo thương hiệu, giá cả và dung lượng.
*   **Kỹ thuật xử lý**:
    *   Sử dụng truy vấn SQL động (Dynamic SQL) để linh hoạt thay đổi điều kiện `WHERE` dựa trên lựa chọn của người dùng.
    *   **Phân trang (Pagination)**: Áp dụng kỹ thuật tính toán `OFFSET` giúp tối ưu tốc độ load trang khi danh mục sản phẩm lớn.

### 2. Công cụ Tìm kiếm thông minh (Smart Search)
*   **Mô tả**: Gồm tìm kiếm chính xác trên trang kết quả và tìm kiếm gợi ý (Autocomplete) trên thanh Header.
*   **Kỹ thuật xử lý**: 
    *   Sử dụng phương thức `LIKE %keyword%` để tìm kiếm tương đối trên cơ sở dữ liệu.
    *   Tích hợp công nghệ AJAX thông qua API `search_suggestions.php` để trả về kết quả ngay lập tức khi người dùng đang nhập liệu mà không cần tải lại trang.

### 3. Quy trình Đặt hàng (Checkout Flow)
*   **Mô tả**: Tiếp nhận thông tin khách hàng, kiểm tra giỏ hàng và lưu trữ hóa đơn vào hệ thống.
*   **Kỹ thuật xử lý**: Xử lý dữ liệu từ Form POST, thực hiện kiểm tra tính hợp lệ của dữ liệu đầu vào và thực hiện ghi dữ liệu đồng thời vào hai bảng `orders` và `order_items`.

### 4. Hệ thống Quản trị tài khoản (Authentication & RBAC)
*   **Mô tả**: Quản lý đăng ký, đăng nhập, bảo mật thông tin và lịch sử đơn hàng.
*   **Kỹ thuật xử lý**: 
    *   Bảo mật mật khẩu bằng thuật toán băm `BCRYPT` thông qua hàm `password_hash`.
    *   Quản lý phiên làm việc bằng `Session` để duy trì trạng thái đăng nhập và bảo vệ các trang yêu cầu quyền riêng tư.

---

## III. PHÂN TÍCH KỸ THUẬT & CODE MINH HỌA

### 1. Xây dựng Truy vấn SQL động (File: `product.php`)
Đây là phần quan trọng nhất để giảng viên hiểu cách hệ thống xử lý bộ lọc phức tạp.
*   **Vị trí**: `product.php` (Dòng 41 - 58)
*   **Giải thích**: Khởi tạo câu lệnh gốc, sau đó kiểm tra từng tham số `GET`. Nếu có tham số nào (như category), câu lệnh SQL sẽ được "nối" thêm điều kiện tương ứng. Điều này giúp code gọn gàng và không phải viết nhiều câu query rời rạc.

```php
// Khởi tạo câu truy vấn gốc
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

// Kiểm tra bộ lọc Danh mục (Dòng 46)
if ($category) {
    $sql .= " AND category = ?";
    $params[] = $category;
}

// Kiểm tra bộ lọc Tìm kiếm (Dòng 53)
if ($search) {
    $sql .= " AND name LIKE ?";
    $params[] = "%$search%";
}

// Thực thi với Prepared Statement để chống SQL Injection
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
```

### 2. Hệ thống Tìm kiếm gợi ý (File: `api/search_suggestions.php`)
Đây là tính năng quan trọng giúp nâng cao trải nghiệm người dùng (UX) bằng cách gợi ý sản phẩm ngay khi đang gõ phím.
*   **Vị trí**: `api/search_suggestions.php` (Toàn bộ file)
*   **Giải thích**: Nhận tham số `q` từ trình duyệt, truy vấn cơ sở dữ liệu và trả về kết quả dưới dạng JSON để JavaScript hiển thị lên giao diện.

```php
// File: api/search_suggestions.php (Dòng 18-25)
$q = $_GET['q'] ?? '';
$stmt = $pdo->prepare("SELECT id, name, image, price FROM products WHERE name LIKE ? LIMIT 5");
$stmt->execute(["%$q%"]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Trả về định dạng JSON để JS xử lý (Dòng 30)
echo json_encode($results);
```

### 3. Quy trình Ghi đè Đơn hàng (File: `checkout.php`)
*   **Vị trí**: `checkout.php` (Dòng 63 - 80)
*   **Giải thích**: Sử dụng `PDO::lastInsertId()` để lấy mã đơn hàng vừa tạo, sau đó dùng mã đó để liên kết và lưu các sản phẩm trong giỏ hàng vào bảng chi tiết hóa đơn.

```php
// Bước 1: Lưu thông tin đơn hàng tổng quát (Dòng 63)
$sqlOrder = "INSERT INTO orders (customer_name, customer_phone, total_price, ...) VALUES (?, ?, ?, ...)";
$stmtOrder = $pdo->prepare($sqlOrder);
$stmtOrder->execute([$name, $phone, $total, ...]);

// Bước 2: Lấy Order ID vừa tạo (Dòng 69)
$orderId = $pdo->lastInsertId();

// Bước 3: Duyệt giỏ hàng và lưu chi tiết (Dòng 78)
foreach ($cartItems as $pid => $item) {
    $stmtItem->execute([$orderId, $pid, $item['name'], $item['price'], $item['qty']]);
}
```

### 3. Cơ chế Bảo mật Đăng nhập (File: `login.php`)
*   **Vị trí**: `login.php` (Dòng 46 - 50)
*   **Giải thích**: Thay vì so sánh mật khẩu trực tiếp (không an toàn), hệ thống lấy chuỗi mã hóa (hash) từ DB và dùng hàm `password_verify` để đối chiếu.

```php
// Truy vấn lấy user theo Email hoặc Username
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR fullname = ?");
$stmt->execute([$email_or_user, $email_or_user]);
$user = $stmt->fetch();

// Xác thực mật khẩu bảo mật (Dòng 50)
if ($user && password_verify($password, $user['password'])) {
    // Nếu đúng, khởi tạo Session để định danh người dùng
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_fullname'] = $user['fullname'];
}
```

---

## IV. KẾT LUẬN
Phần việc của sinh viên Khôi tập trung vào xử lý logic nghiệp vụ phía Back-end (Business Logic) và quản trị cơ sở dữ liệu. Đảm bảo hệ thống hoạt động ổn định, chính xác và có tính bảo mật cao theo các tiêu chuẩn lập trình web hiện đại.
