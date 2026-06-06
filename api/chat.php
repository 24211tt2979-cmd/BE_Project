<?php
/**
 * NHK Mobile - Rule-based Chat API Endpoint (v5.0 - DB Product Intelligence)
 *
 * Kết hợp rule-based từ CSDL + truy vấn sản phẩm thực tế:
 *   - Hỏi giá sản phẩm       → tra bảng products
 *   - Hỏi còn hàng không     → tra stock
 *   - Hỏi thông số / cấu hình → tra specs
 *   - Liệt kê hãng / loại    → tra category
 *   - Sản phẩm nổi bật / rẻ nhất / đắt nhất → truy vấn tổng hợp
 *   - Fallback → rule-based chatbot_rules
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($input['message'] ?? '');

if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message không được để trống']);
    exit;
}

if (mb_strlen($userMessage) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Tin nhắn quá dài (tối đa 1000 ký tự)']);
    exit;
}

require_once dirname(__DIR__) . '/includes/db.php';

if (!isset($pdo)) {
    echo json_encode(['reply' => 'Hệ thống đang bảo trì, vui lòng gọi hotline 0375 352 347 để được hỗ trợ ạ!']);
    exit;
}

// ============================================================
// HELPER: Format tiền
// ============================================================
function fmtPrice(float $price): string {
    return number_format($price, 0, ',', '.') . 'đ';
}

// ============================================================
// HELPER: Tìm sản phẩm gần đúng theo tên / hãng
// ============================================================
function findProducts(PDO $pdo, string $keyword, int $limit = 5): array {
    $kw = "%$keyword%";
    $stmt = $pdo->prepare(
        "SELECT id, name, category, price, stock, specs, rating, review_count
         FROM products
         WHERE name LIKE ? OR category LIKE ?
         ORDER BY is_featured DESC, name ASC
         LIMIT ?"
    );
    $stmt->execute([$kw, $kw, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// INTENT DETECTION
// ============================================================
$msg  = mb_strtolower($userMessage, 'UTF-8');
$reply = null;

// Các brand có trong DB
$brands = ['apple', 'iphone', 'samsung', 'xiaomi', 'oppo', 'oneplus', 'realme', 'vivo', 'honor', 'nubia'];

// --- Intent 1: Hỏi giá ---
// "giá iphone 16 pro", "iphone 15 bao nhiêu tiền", "s25 ultra giá bao nhiêu"
if (preg_match('/(?:gi[aá]|bao nhi[eê]u ti[eề]n|bao nhi[eê]u|m[aá]c kh[oô]ng|r[eẻ] kh[oô]ng)/u', $msg)) {
    // Lấy từ khóa tên sản phẩm (loại bỏ các từ "hỏi về giá")
    $cleanMsg = preg_replace('/(?:gi[aá]|bao nhi[eê]u ti[eề]n|bao nhi[eê]u|m[aá]c kh[oô]ng|r[eẻ] kh[oô]ng|c[ủu]a|l[àa]|nh[eé]|[aạ]nh|ch[ịi]|em|shop|c[oo]n kh[oô]ng|kh[oô]ng)/ui', '', $msg);
    $cleanMsg = trim(preg_replace('/\s+/', ' ', $cleanMsg));

    if (!empty($cleanMsg)) {
        $products = findProducts($pdo, $cleanMsg, 5);
        if (!empty($products)) {
            if (count($products) === 1) {
                $p = $products[0];
                $stockText = $p['stock'] > 0 ? "còn {$p['stock']} máy" : "⚠️ tạm hết hàng";
                $reply = "📱 <strong>{$p['name']}</strong>\n"
                       . "💰 Giá: <strong>" . fmtPrice($p['price']) . "</strong>\n"
                       . "📦 Tồn kho: $stockText\n"
                       . "⭐ Đánh giá: {$p['rating']}/5 ({$p['review_count']} đánh giá)\n"
                       . "👉 <a href='/nhkmobile_web-main/product-detail.php?id={$p['id']}' target='_blank'>Xem chi tiết sản phẩm</a>";
            } else {
                $reply = "Em tìm được " . count($products) . " sản phẩm phù hợp:\n";
                foreach ($products as $p) {
                    $stockIcon = $p['stock'] > 0 ? '✅' : '❌';
                    $reply .= "• $stockIcon <a href='/nhkmobile_web-main/product-detail.php?id={$p['id']}' target='_blank'><strong>{$p['name']}</strong></a> – " . fmtPrice($p['price']) . "\n";
                }
                $reply .= "\nAnh/chị muốn tư vấn chi tiết sản phẩm nào ạ?";
            }
        }
    }
}

// --- Intent 2: Hỏi tồn kho / còn hàng ---
if (!$reply && preg_match('/(?:c[oò]n h[àa]ng|c[oò]n m[áa]y|c[oò]n kh[oô]ng|h[àa]ng t[oồ]n|h[eế]t h[àa]ng)/u', $msg)) {
    $cleanMsg = preg_replace('/(?:c[oò]n h[àa]ng|c[oò]n m[áa]y|c[oò]n kh[oô]ng|h[àa]ng t[oồ]n|h[eế]t h[àa]ng|[aạ]nh|ch[ịi]|em|nh[eé]|shop)/ui', '', $msg);
    $cleanMsg = trim(preg_replace('/\s+/', ' ', $cleanMsg));

    if (!empty($cleanMsg)) {
        $products = findProducts($pdo, $cleanMsg, 5);
        if (!empty($products)) {
            if (count($products) === 1) {
                $p = $products[0];
                if ($p['stock'] > 0) {
                    $reply = "✅ <strong>{$p['name']}</strong> hiện còn <strong>{$p['stock']} máy</strong> trong kho ạ!\n"
                           . "💰 Giá: " . fmtPrice($p['price']) . "\n"
                           . "👉 <a href='/nhkmobile_web-main/product-detail.php?id={$p['id']}' target='_blank'>Đặt hàng ngay</a>";
                } else {
                    $reply = "⚠️ Rất tiếc, <strong>{$p['name']}</strong> hiện đã <strong>tạm hết hàng</strong>.\n"
                           . "Anh/chị để lại số điện thoại, em sẽ thông báo khi có hàng về nhé! 📞 0375 352 347";
                }
            } else {
                $reply = "Thông tin tồn kho các sản phẩm phù hợp:\n";
                foreach ($products as $p) {
                    $icon = $p['stock'] > 0 ? "✅ Còn {$p['stock']} máy" : "❌ Hết hàng";
                    $reply .= "• <strong>{$p['name']}</strong>: $icon – " . fmtPrice($p['price']) . "\n";
                }
            }
        }
    }
}

// --- Intent 3: Hỏi thông số / cấu hình / specs ---
if (!$reply && preg_match('/(?:th[oô]ng s[oố]|c[aấ]u h[iì]nh|ram|b[oộ] nh[oớ]|chip|snapdragon|dimensity|camera|pin|s[aạ]c|m[aà]n h[iì]nh)/u', $msg)) {
    // Thử match tên sản phẩm trong tin nhắn
    $stmt = $pdo->prepare(
        "SELECT id, name, category, price, stock, specs, rating
         FROM products
         ORDER BY LENGTH(name) DESC"
    );
    $stmt->execute();
    $allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allProducts as $p) {
        $pnameLower = mb_strtolower($p['name'], 'UTF-8');
        if (mb_strpos($msg, $pnameLower) !== false) {
            $specs = $p['specs'] ?: 'Chưa có thông số chi tiết';
            $reply = "📋 <strong>Cấu hình {$p['name']}</strong>\n"
                   . "🔧 $specs\n"
                   . "💰 Giá: " . fmtPrice($p['price']) . " | "
                   . ($p['stock'] > 0 ? "✅ Còn hàng" : "❌ Hết hàng") . "\n"
                   . "👉 <a href='/nhkmobile_web-main/product-detail.php?id={$p['id']}' target='_blank'>Xem chi tiết đầy đủ</a>";
            break;
        }
    }

    // Nếu không match tên cụ thể nhưng hỏi về hãng
    if (!$reply) {
        foreach ($brands as $brand) {
            if (mb_strpos($msg, $brand) !== false) {
                $bname = ucfirst($brand === 'iphone' ? 'Apple' : $brand);
                $products = findProducts($pdo, $bname, 5);
                if (!empty($products)) {
                    $reply = "📋 Cấu hình nổi bật các sản phẩm <strong>$bname</strong>:\n";
                    foreach ($products as $p) {
                        $reply .= "\n• <strong>{$p['name']}</strong> (" . fmtPrice($p['price']) . ")\n  ↳ {$p['specs']}\n";
                    }
                }
                break;
            }
        }
    }
}

// --- Intent 4: Liệt kê sản phẩm theo hãng ---
if (!$reply) {
    foreach ($brands as $brand) {
        if (mb_strpos($msg, $brand) !== false &&
            preg_match('/(?:c[oó]|b[aá]n|[dđ]i[eệ]n tho[aạ]i|s[aả]n ph[aả]m|m[aá]y|d[oò]ng|lo[aạ]i|danh s[aá]ch|list)/u', $msg)) {

            $bname = ucfirst($brand === 'iphone' ? 'Apple' : $brand);
            $stmt = $pdo->prepare(
                "SELECT id, name, price, stock, rating FROM products WHERE category = ? ORDER BY price DESC LIMIT 8"
            );
            $stmt->execute([$bname]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($products)) {
                $reply = "📱 Danh sách điện thoại <strong>$bname</strong> tại NHK Mobile (" . count($products) . " sản phẩm):\n";
                foreach ($products as $p) {
                    $stockIcon = $p['stock'] > 0 ? '✅' : '❌';
                    $reply .= "• $stockIcon <a href='/nhkmobile_web-main/product-detail.php?id={$p['id']}' target='_blank'><strong>{$p['name']}</strong></a> – " . fmtPrice($p['price']) . " ⭐{$p['rating']}\n";
                }
                $reply .= "\nAnh/chị muốn tư vấn thêm về sản phẩm nào không ạ?";
            }
            break;
        }
    }
}

// --- Intent 5: Sản phẩm rẻ nhất ---
if (!$reply && preg_match('/(?:r[eẻ] nh[aấ]t|gi[aá] th[aấ]p|ti[eề]t ki[eệ]m|ngân s[aá]ch|sinh vi[eê]n|gi[aá] [tT]ốt nh[aấ]t)/u', $msg)) {
    $limit = 5;
    $brandFilter = '';
    foreach ($brands as $brand) {
        if (mb_strpos($msg, $brand) !== false) {
            $brandFilter = ucfirst($brand === 'iphone' ? 'Apple' : $brand);
            break;
        }
    }
    if ($brandFilter) {
        $stmt = $pdo->prepare("SELECT id, name, price, stock, rating FROM products WHERE category = ? ORDER BY price ASC LIMIT ?");
        $stmt->execute([$brandFilter, $limit]);
        $label = "rẻ nhất của $brandFilter";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, category, price, stock, rating FROM products ORDER BY price ASC LIMIT ?");
        $stmt->execute([$limit]);
        $label = "rẻ nhất tại NHK Mobile";
    }
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($products)) {
        $reply = "💸 Top $limit sản phẩm $label:\n";
        $i = 1;
        foreach ($products as $p) {
            $catTag = $brandFilter ? '' : " [{$p['category']}]";
            $stockIcon = $p['stock'] > 0 ? '✅' : '❌';
            $reply .= "$i. $stockIcon <a href='/nhkmobile_web-main/product-detail.php?id={$p['id']}' target='_blank'><strong>{$p['name']}</strong></a>$catTag – " . fmtPrice($p['price']) . "\n";
            $i++;
        }
    }
}

// --- Intent 6: Sản phẩm đắt nhất / cao cấp nhất ---
if (!$reply && preg_match('/(?:[dđ][aắ]t nh[aấ]t|cao c[aấ]p nh[aấ]t|flagship|[xX][ịi]n nh[aấ]t|t[oố]t nh[aấ]t|[hH][aA][nN][gG] [xX][iI][nN])/u', $msg)) {
    $limit = 5;
    $brandFilter = '';
    foreach ($brands as $brand) {
        if (mb_strpos($msg, $brand) !== false) {
            $brandFilter = ucfirst($brand === 'iphone' ? 'Apple' : $brand);
            break;
        }
    }
    if ($brandFilter) {
        $stmt = $pdo->prepare("SELECT id, name, price, stock, rating FROM products WHERE category = ? ORDER BY price DESC LIMIT ?");
        $stmt->execute([$brandFilter, $limit]);
        $label = "cao cấp nhất của $brandFilter";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, category, price, stock, rating FROM products ORDER BY price DESC LIMIT ?");
        $stmt->execute([$limit]);
        $label = "cao cấp nhất tại NHK Mobile";
    }
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($products)) {
        $reply = "👑 Top $limit sản phẩm $label:\n";
        $i = 1;
        foreach ($products as $p) {
            $catTag = $brandFilter ? '' : " [{$p['category']}]";
            $stockIcon = $p['stock'] > 0 ? '✅' : '❌';
            $reply .= "$i. $stockIcon <a href='/nhkmobile_web-main/product-detail.php?id={$p['id']}' target='_blank'><strong>{$p['name']}</strong></a>$catTag – " . fmtPrice($p['price']) . "\n";
            $i++;
        }
    }
}

// --- Intent 7: Sản phẩm nổi bật / hot / bán chạy ---
if (!$reply && preg_match('/(?:n[oổ]i b[aậ]t|b[aá]n ch[aạ]y|hot|ph[oổ] bi[eế]n|[gG]i[oớ]i thi[eệ]u|[tT][uư] v[aấ]n)/u', $msg)) {
    $stmt = $pdo->query(
        "SELECT id, name, category, price, stock, rating, review_count
         FROM products WHERE is_featured = 1 ORDER BY rating DESC LIMIT 6"
    );
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($products)) {
        $reply = "🔥 Sản phẩm nổi bật tại NHK Mobile:\n";
        foreach ($products as $p) {
            $stockIcon = $p['stock'] > 0 ? '✅' : '❌';
            $reply .= "• $stockIcon <a href='/nhkmobile_web-main/product-detail.php?id={$p['id']}' target='_blank'><strong>{$p['name']}</strong></a> [{$p['category']}] – " . fmtPrice($p['price']) . " ⭐{$p['rating']}\n";
        }
        $reply .= "\nAnh/chị muốn tư vấn thêm không ạ?";
    }
}

// --- Intent 8: Tìm theo khoảng giá ---
// "điện thoại dưới 15 triệu", "máy tầm 20 triệu", "từ 10 đến 20 triệu"
if (!$reply && preg_match('/(?:\d+)\s*(?:tri[eệ]u|tr|000\.000)/u', $msg)) {
    preg_match_all('/(\d+(?:[.,]\d+)?)\s*(?:tri[eệ]u|tr)/u', $msg, $matches);
    $nums = array_map(fn($n) => (float)str_replace(',', '.', $n) * 1_000_000, $matches[1]);

    $minPrice = $maxPrice = null;
    if (count($nums) >= 2) {
        $minPrice = min($nums);
        $maxPrice = max($nums);
    } elseif (count($nums) === 1) {
        if (preg_match('/(?:d[uư][oớ]i|kh[oô]ng qu[aá]|d[uư][oớ]i|b[eê]n d[uư][oớ]i)/u', $msg)) {
            $maxPrice = $nums[0];
            $minPrice = 0;
        } else {
            // "tầm X triệu" → ±20%
            $minPrice = $nums[0] * 0.8;
            $maxPrice = $nums[0] * 1.2;
        }
    }

    if ($minPrice !== null && $maxPrice !== null) {
        $stmt = $pdo->prepare(
            "SELECT id, name, category, price, stock, rating
             FROM products WHERE price BETWEEN ? AND ? ORDER BY price ASC LIMIT 8"
        );
        $stmt->execute([$minPrice, $maxPrice]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($products)) {
            $rangeLabel = fmtPrice($minPrice) . " – " . fmtPrice($maxPrice);
            $reply = "🔍 Tìm thấy " . count($products) . " sản phẩm trong khoảng <strong>$rangeLabel</strong>:\n";
            foreach ($products as $p) {
                $stockIcon = $p['stock'] > 0 ? '✅' : '❌';
                $reply .= "• $stockIcon <a href='/nhkmobile_web-main/product-detail.php?id={$p['id']}' target='_blank'><strong>{$p['name']}</strong></a> [{$p['category']}] – " . fmtPrice($p['price']) . " ⭐{$p['rating']}\n";
            }
        } else {
            $reply = "Dạ hiện tại NHK Mobile chưa có sản phẩm trong khoảng giá này ạ. Anh/chị có thể xem các sản phẩm khác tại <a href='/nhkmobile_web-main/product.php'>trang sản phẩm</a> nhé!";
        }
    }
}

// --- Intent 9: Hỏi tổng số sản phẩm / danh mục ---
if (!$reply && preg_match('/(?:bao nhi[eê]u s[aả]n ph[aả]m|c[oó] nh[uữ]ng lo[aạ]i|c[oó] c[aá]c h[aã]ng|c[aá]c d[oò]ng|danh m[uụ]c)/u', $msg)) {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $catStmt = $pdo->query("SELECT category, COUNT(*) as cnt FROM products GROUP BY category ORDER BY cnt DESC");
    $cats = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    $reply = "📊 NHK Mobile hiện có tổng <strong>$total sản phẩm</strong> từ các hãng:\n";
    foreach ($cats as $c) {
        $reply .= "• <strong>{$c['category']}</strong>: {$c['cnt']} sản phẩm\n";
    }
    $reply .= "\nAnh/chị muốn xem sản phẩm hãng nào ạ?";
}

// ============================================================
// FALLBACK: Rule-based từ bảng chatbot_rules
// ============================================================
if (!$reply) {
    try {
        $stmt = $pdo->query("SELECT keyword, response FROM chatbot_rules ORDER BY LENGTH(keyword) DESC");
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rules as $rule) {
            $keyword = mb_strtolower($rule['keyword'], 'UTF-8');
            if (mb_strpos($msg, $keyword) !== false) {
                $reply = $rule['response'];
                break;
            }
        }
    } catch (Exception $e) {
        // Bảng chatbot_rules không tồn tại → bỏ qua
    }
}

// Mặc định cuối cùng
if (!$reply) {
    $reply = "Dạ, em chưa hiểu rõ ý của anh/chị 😊 Anh/chị có thể hỏi về:\n"
           . "• <strong>Giá</strong> sản phẩm cụ thể (VD: \"giá iPhone 16 Pro\")\n"
           . "• <strong>Tồn kho</strong> (VD: \"Samsung S25 còn hàng không\")\n"
           . "• <strong>Cấu hình</strong> (VD: \"thông số Xiaomi 17 Ultra\")\n"
           . "• <strong>Khoảng giá</strong> (VD: \"điện thoại từ 10 đến 15 triệu\")\n"
           . "Hoặc liên hệ hotline <strong>0375 352 347</strong> để được tư vấn trực tiếp ạ!";
}

sleep(1);
echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
