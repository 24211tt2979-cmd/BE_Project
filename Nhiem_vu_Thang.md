# BÁO CÁO CHI TIẾT NHIỆM VỤ THỰC HIỆN - SINH VIÊN: THẮNG

## I. TỔNG QUAN PHẠM VI CÔNG VIỆC
Trong dự án website thương mại điện tử NHK Mobile, sinh viên Thắng đảm nhiệm các thành phần thuộc **Giao diện người dùng (Front-end UI/UX)** và **Tương tác khách hàng trực tiếp**. Các công việc bao gồm thiết kế trải nghiệm người dùng tại trang chủ, tối ưu hóa hiển thị chi tiết sản phẩm và xây dựng hệ thống quản lý giỏ hàng linh hoạt.

---

## II. CHI TIẾT CÁC CHỨC NĂNG ĐẢM NHIỆM

### 1. Xây dựng diện mạo Trang chủ (Storefront Design)
*   **Mô tả**: Thiết kế và lập trình giao diện trang chủ chuyên nghiệp, bao gồm các khu vực Banner, sản phẩm nổi bật và gợi ý mua sắm.
*   **Kỹ thuật xử lý**:
    *   **Banner Quảng cáo**: Sử dụng Hero Section với hiệu ứng Gradient và hình ảnh sản phẩm độ phân giải cao để tạo ấn tượng thị giác mạnh (Apple Aesthetic).
    *   **Sản phẩm nổi bật/Bán chạy**: Sử dụng các truy vấn lọc theo thuộc tính `is_featured` hoặc sắp xếp theo doanh số/đánh giá để lấy dữ liệu.
    *   **Micro-interactions**: Tích hợp các hiệu ứng Hover và Scroll Reveal giúp giao diện sinh động và hiện đại hơn.

### 2. Trang Chi tiết sản phẩm & Tương tác (Product Engagement)
*   **Mô tả**: Cung cấp đầy đủ thông tin kỹ thuật, hình ảnh sắc nét, đánh giá từ khách hàng và gợi ý sản phẩm liên quan.
*   **Kỹ thuật xử lý**:
    *   **Xử lý Specs (Thông số kỹ thuật)**: Sử dụng hàm `explode()` để tách chuỗi dữ liệu thô từ database thành mảng các "Chip" đặc tính, giúp hiển thị trực quan và chuyên nghiệp.
    *   **Hệ thống Đánh giá (Reviews)**: Tích hợp module đánh giá từ bảng `reviews`, tính toán trung bình cộng số sao (Average Rating) để hiển thị trực quan trên giao diện.
    *   **Sản phẩm tương tự**: Thuật toán truy vấn các sản phẩm cùng danh mục (`category`) nhưng loại trừ ID của sản phẩm đang xem để tối ưu hóa gợi ý bán thêm (Upselling).

### 3. Hệ thống Giỏ hàng (Cart Management)
*   **Mô tả**: Cho phép khách hàng thêm, xóa, cập nhật số lượng và tự động tính toán tổng giá trị hàng hóa.
*   **Kỹ thuật xử lý**: 
    *   **Lưu trữ giỏ hàng**: Sử dụng mảng kết hợp (Associative Array) trong `$_SESSION['cart']` giúp quản lý dữ liệu linh hoạt mà không cần ghi xuống DB liên tục, tăng hiệu năng hệ thống.
    *   **Tính tổng tiền (Cart Calculation)**: Xây dựng hàm `get_cart_total()` duyệt qua toàn bộ giỏ hàng, nhân đơn giá với số lượng và áp dụng logic chiết khấu (nếu có) để trả về con số chính xác nhất.
    *   Xây dựng các hàm helper tính toán tổng tiền tự động tại Header và trang Giỏ hàng.

---

## III. PHÂN TÍCH KỸ THUẬT & CODE MINH HỌA

### 1. Cơ chế hiển thị Sản phẩm nổi bật (File: `index.php`)
*   **Vị trí**: `index.php` (Dòng 18 - 25)
*   **Giải thích**: Thay vì lấy toàn bộ sản phẩm gây chậm trang, hệ thống sử dụng mệnh đề `LIMIT` kết hợp với sắp xếp theo mức độ ưu tiên (`is_featured`) để hiển thị 8 siêu phẩm tốt nhất.

```php
// File: index.php (Dòng 18)
// Lấy 8 sản phẩm được đánh dấu nổi bật hoặc mới nhất
$stmt = $pdo->query("SELECT * FROM products ORDER BY is_featured DESC, created_at DESC LIMIT 8");
$featuredProducts = $stmt->fetchAll();

// Logic hiển thị tại Front-end (Dòng 118)
<?php foreach ($featuredProducts as $p): ?>
    <div class="product-card-new">
        <img src="assets/images/<?= $p['image'] ?>">
        <h3><?= $p['name'] ?></h3>
        <p><?= number_format($p['price']) ?>₫</p>
    </div>
<?php endforeach; ?>
```

### 2. Truy vấn Chi tiết & Sản phẩm liên quan (File: `product-detail.php`)
*   **Vị trí**: `product-detail.php` (Dòng 24 - 26)
*   **Giải thích**: Sử dụng `PDO::prepare` để đảm bảo tính an toàn dữ liệu khi nhận ID từ thanh địa chỉ (URL). Sau khi lấy được dữ liệu sản phẩm chính, hệ thống sẽ tiếp tục query các sản phẩm cùng loại để gợi ý thêm cho khách hàng.

```php
// File: product-detail.php (Dòng 24)
// Nhận ID sản phẩm an toàn từ URL
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

// Nếu không tìm thấy, điều hướng về trang danh sách (Dòng 28)
if (!$product) { header("Location: product.php"); exit; }
```

### 3. Logic Quản lý Giỏ hàng bằng Session (File: `cart.php`)
*   **Vị trí**: `cart.php` (Logic trung tâm)
*   **Giải thích**: Giỏ hàng được cấu trúc dưới dạng mảng đa chiều trong `$_SESSION`. Mỗi khi người dùng nhấn "Thêm", hệ thống kiểm tra sự tồn tại của sản phẩm để quyết định cộng dồn số lượng hay thêm mới bản ghi.

```php
// Logic thêm sản phẩm vào giỏ hàng (Dòng 130 - 140 trong file xử lý gốc)
$id = $_GET['add'];
if (!isset($_SESSION['cart'][$id])) {
    // Nếu chưa có, tạo mới với số lượng là 1
    $_SESSION['cart'][$id] = 1;
} else {
    // Nếu đã có, tăng thêm 1 đơn vị
    $_SESSION['cart'][$id]++;
}
```

---

## IV. KẾT LUẬN
Phần việc của sinh viên Thắng tập trung vào tối ưu hóa trải nghiệm người dùng (UX) và giao diện trực quan (UI). Sự kết hợp giữa các hiệu ứng CSS hiện đại và logic xử lý dữ liệu Front-end giúp website không chỉ đẹp mắt mà còn vận hành mượt mà, chuyên nghiệp, tạo cảm giác tin cậy cho khách hàng.
