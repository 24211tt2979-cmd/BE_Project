<?php
/**
 * NHK Mobile - Global Footer System
 * 
 * Description: Contains the multi-column footer, social links, 
 * legal information, search overlay inclusion, and core JS logic.
 * 
 * Author: NguyenHuuKhanh
 * Version: 2.2
 * Date: 2026-04-08
 */
?>
<footer class="footer-new bg-light mt-5 border-top">
        <div class="container-wide py-5">
            <div class="footer-grid">
                <!-- Brand Section -->
                <div class="footer-brand">
                    <div class="d-flex align-items-center mb-4">
                        <div class="brand-logo-box md me-2">NHK</div>
                        <span class="brand-text md">MOBILE</span>
                    </div>
                    <p class="text-secondary small mb-4" style="max-width: 300px;">Đại lý ủy quyền chính thức của Apple tại Việt Nam. Chúng tôi mang đến những trải nghiệm công nghệ đỉnh cao cùng dịch vụ hậu mãi chuẩn 5 sao.</p>
                    <div class="d-flex justify-content-center justify-content-md-start gap-3">
                        <a href="#" class="social-icon" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="social-icon" title="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="social-icon" title="TikTok"><i class="bi bi-tiktok"></i></a>
                        <a href="#" class="social-icon" title="YouTube"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>
                
                <!-- Explorar Column -->
                <div class="footer-col accordion-item-mobile">
                    <div class="footer-title-wrapper d-flex justify-content-between align-items-center">
                        <h6 class="footer-title">Khám phá</h6>
                        <i class="bi bi-plus-lg d-md-none toggle-icon"></i>
                    </div>
                    <ul class="footer-links list-unstyled accordion-content-mobile">
                        <li><a href="product.php?category=Apple">iPhone Series</a></li>
                        <li><a href="product.php?category=Samsung">Samsung Galaxy</a></li>
                        <li><a href="product.php">Sản phẩm cao cấp</a></li>
                        <li><a href="news.php">Tin tức công nghệ</a></li>
                    </ul>
                </div>
                
                <!-- Services Column -->
                <div class="footer-col accordion-item-mobile">
                    <div class="footer-title-wrapper d-flex justify-content-between align-items-center">
                        <h6 class="footer-title">Dịch vụ</h6>
                        <i class="bi bi-plus-lg d-md-none toggle-icon"></i>
                    </div>
                    <ul class="footer-links list-unstyled accordion-content-mobile">
                        <li><a href="warranty.php">Trung tâm Bảo hành</a></li>
                        <li><a href="track_order.php"><?php echo isset($_SESSION['user_id']) ? 'Đơn hàng của tôi' : 'Tình trạng đơn hàng'; ?></a></li>
                        <li><a href="#">Giao hàng tận nơi</a></li>
                        <li><a href="#">Hình thức thanh toán</a></li>
                    </ul>
                </div>
                
                <!-- About Column -->
                <div class="footer-col accordion-item-mobile">
                    <div class="footer-title-wrapper d-flex justify-content-between align-items-center">
                        <h6 class="footer-title">Về NHK Mobile</h6>
                        <i class="bi bi-plus-lg d-md-none toggle-icon"></i>
                    </div>
                    <ul class="footer-links list-unstyled accordion-content-mobile">
                        <li><a href="#">Giới thiệu công ty</a></li>
                        <li><a href="#">Tuyển dụng</a></li>
                        <li><a href="#">Chính sách bảo mật</a></li>
                        <li><a href="#">Liên hệ hợp tác</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom-new pt-4 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <p class="mb-0 text-muted small">&copy; 2026 NHK Mobile. Bản quyền thiết kế bởi NHK Team.</p>
                <div class="d-flex gap-4">
                    <a href="#" class="text-decoration-none text-muted small hover-primary">Quyền riêng tư</a>
                    <a href="#" class="text-decoration-none text-muted small hover-primary">Điều khoản</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Footer Mobile Accordion Handler -->
    <script>
        document.querySelectorAll('.accordion-item-mobile').forEach(item => {
            const wrapper = item.querySelector('.footer-title-wrapper');
            wrapper.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    item.classList.toggle('active');
                    const icon = item.querySelector('.toggle-icon');
                    if (item.classList.contains('active')) {
                        icon.classList.replace('bi-plus-lg', 'bi-dash-lg');
                    } else {
                        icon.classList.replace('bi-dash-lg', 'bi-plus-lg');
                    }
                }
            });
        });
    </script>

    <!-- Cart Badge Realtime Updater -->
    <script>
        function updateCartBadge() {
            fetch(BASE_PATH + 'api/cart_count.php')
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('cartBadge');
                    if (!badge) return;
                    if (data.logged_in && data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline-flex';
                    } else {
                        badge.style.display = 'none';
                    }
                })
                .catch(() => {});
        }

        // Cập nhật ngay khi trang load
        updateCartBadge();

        // Cập nhật lại mỗi 30 giây
        setInterval(updateCartBadge, 30000);

        // Cập nhật ngay sau khi bấm nút thêm vào giỏ
        document.querySelectorAll('a[href*="add="]').forEach(btn => {
            btn.addEventListener('click', function() {
                setTimeout(updateCartBadge, 500);
            });
        });
    </script>

    <!-- Search Overlay -->
    <?php include 'includes/search_overlay.php'; ?>

    <!-- Scroll Progress Bar -->
    <div class="scroll-progress" id="scrollProgress"></div>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" onclick="scrollToTop()">
        <i class="bi bi-arrow-up"></i>
    </button>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Quick View Modal -->
    <div class="quick-view-modal" id="quickViewModal">
        <div class="quick-view-content">
            <button class="quick-view-close" onclick="closeQuickView()">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="quick-view-image">
                <img id="quickViewImage" src="" alt="">
            </div>
            <div class="quick-view-info">
                <div class="quick-view-category" id="quickViewCategory"></div>
                <h2 class="quick-view-name" id="quickViewName"></h2>
                <div class="quick-view-price" id="quickViewPrice"></div>
                <p class="quick-view-description" id="quickViewDesc"></p>
                <div class="quick-view-actions">
                    <div class="quick-view-quantity">
                        <button onclick="updateQty(-1)">-</button>
                        <input type="number" id="quickViewQty" value="1" min="1" max="10">
                        <button onclick="updateQty(1)">+</button>
                    </div>
                    <button class="quick-view-add-cart" onclick="addToCartFromQuickView()">
                        <i class="bi bi-cart-plus"></i>
                        Thêm vào giỏ
                    </button>
                </div>
                <div class="quick-view-links">
                    <a href="#" id="quickViewDetailLink">Xem chi tiết <i class="bi bi-arrow-right"></i></a>
                    <a href="#" onclick="addToWishlistFromQuickView()">
                        <i class="bi bi-heart"></i> Yêu thích
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Chat Widget -->
    <div class="live-chat-widget">
        <div class="live-chat-window" id="liveChatWindow">
            <div class="live-chat-header">
                <h5><span class="live-chat-status"></span> Hỗ trợ trực tuyến</h5>
                <button class="live-chat-close" onclick="toggleLiveChat()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="live-chat-body" id="chatBody">
                <div class="chat-message">
                    <div class="chat-avatar"><i class="bi bi-headset"></i></div>
                    <div>
                        <div class="chat-bubble">Xin chào! Em là trợ lý NHK Mobile 🤖<br>Em có thể tra giá, kiểm tra tồn kho, thông số sản phẩm theo dữ liệu thực tế nhé!</div>
                        <div class="chat-time">Vừa xong</div>
                    </div>
                </div>
                <!-- Gợi ý nhanh -->
                <div class="chat-quick-actions" id="chatQuickActions">
                    <button onclick="sendQuickMsg('Điện thoại nổi bật nhất')">🔥 Nổi bật</button>
                    <button onclick="sendQuickMsg('Điện thoại rẻ nhất')">💸 Rẻ nhất</button>
                    <button onclick="sendQuickMsg('Có những hãng nào')">📊 Danh mục</button>
                    <button onclick="sendQuickMsg('iPhone đang có')">📱 iPhone</button>
                    <button onclick="sendQuickMsg('Samsung đang bán')">📱 Samsung</button>
                </div>
            </div>
            <div class="live-chat-footer">
                <div class="live-chat-input-group">
                    <input type="text" class="live-chat-input" id="chatInput" placeholder="Nhập tin nhắn..." onkeypress="handleChatKeypress(event)">
                    <button class="live-chat-send" onclick="sendChatMessage()">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </div>
        </div>
        <button class="live-chat-button" id="liveChatBtn" onclick="toggleLiveChat()">
            <i class="bi bi-chat-dots-fill"></i>
        </button>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $basePath; ?>assets/js/search-overlay.js"></script>
    <script>
        // Navbar scroll effect
        (function() {
            const nav = document.querySelector('.navbar-minimal');
            if (nav) {
                window.addEventListener('scroll', () => {
                    if (window.scrollY > 50) {
                        nav.classList.add('scrolled');
                    } else {
                        nav.classList.remove('scrolled');
                    }
                });
            }
        })();

        // Scroll Reveal Animation
        (function() {
            const reveals = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale');

            const revealOnScroll = () => {
                reveals.forEach(el => {
                    const windowHeight = window.innerHeight;
                    const elementTop = el.getBoundingClientRect().top;
                    const revealPoint = 100;

                    if (elementTop < windowHeight - revealPoint) {
                        el.classList.add('active');
                    }
                });
            };

            window.addEventListener('scroll', revealOnScroll);
            window.addEventListener('load', revealOnScroll);
        })();

        // Scroll Progress Bar
        (function() {
            const progressBar = document.getElementById('scrollProgress');
            if (progressBar) {
                window.addEventListener('scroll', () => {
                    const scrollTop = window.scrollY;
                    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
                    const scrollPercent = (scrollTop / docHeight) * 100;
                    progressBar.style.width = scrollPercent + '%';
                });
            }
        })();

        // Back to Top Button
        (function() {
            const backToTop = document.getElementById('backToTop');
            if (backToTop) {
                window.addEventListener('scroll', () => {
                    if (window.scrollY > 500) {
                        backToTop.classList.add('visible');
                    } else {
                        backToTop.classList.remove('visible');
                    }
                });
            }
        })();

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Toast Notification Function
        function showToast(message, type = 'success', duration = 3000) {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            const icon = type === 'success' ? 'check-circle' :
                        type === 'error' ? 'x-circle' :
                        type === 'warning' ? 'exclamation-triangle' : 'info-circle';

            toast.innerHTML = `
                <i class="bi bi-${icon}-fill"></i>
                <span>${message}</span>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('hide');
                setTimeout(() => toast.remove(), 400);
            }, duration);
        }

        // Add to cart - kiểm tra login + chống spam
        const IS_LOGGED_IN = <?php echo (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) ? 'true' : 'false'; ?>;

        document.querySelectorAll('a[href*="cart.php?add="]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                // Chưa đăng nhập → redirect sang login
                if (!IS_LOGGED_IN) {
                    return; // PHP sẽ bắt đăng nhập
                }

                e.preventDefault();

                // Chống spam: nếu đang loading thì bỏ qua
                if (this.dataset.loading === 'true') return;

                const href = this.getAttribute('href');
                const btn = this;

                // Lưu nội dung gốc của nút
                const originalHTML = btn.innerHTML;

                // Hiển thị trạng thái loading
                btn.dataset.loading = 'true';
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.7';
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

                fetch(href)
                    .then(r => {
                        if (r.redirected && r.url.includes('login')) {
                            window.location.href = r.url;
                            return;
                        }

                        // Hiệu ứng thành công
                        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
                        btn.style.opacity = '1';

                        // Lấy tên sản phẩm từ card cha nếu có
                        const card = btn.closest('[data-name], .product-card, .product-item');
                        const productName = card?.querySelector('h3, h4, .product-name, [class*="name"]')?.textContent?.trim() || 'Sản phẩm';

                        showToast(`🛒 Đã thêm "<b>${productName}</b>" vào giỏ hàng!`, 'success', 3000);

                        if (typeof loadMiniCart === 'function') loadMiniCart();
                        updateCartBadge();

                        // Khôi phục nút sau 2 giây (chống spam)
                        setTimeout(() => {
                            btn.innerHTML = originalHTML;
                            btn.style.pointerEvents = '';
                            btn.style.opacity = '';
                            btn.dataset.loading = 'false';
                        }, 2000);
                    })
                    .catch(() => {
                        // Khôi phục nút khi lỗi
                        btn.innerHTML = originalHTML;
                        btn.style.pointerEvents = '';
                        btn.style.opacity = '';
                        btn.dataset.loading = 'false';
                        showToast('Có lỗi xảy ra, vui lòng thử lại.', 'error');
                    });
            });
        });


        // Quick View Functions
        let currentProductId = null;

        function openQuickView(productId) {
            currentProductId = productId;
            const modal = document.getElementById('quickViewModal');

            // Fetch product data
            fetch(`api/product_detail.php?id=${productId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        showToast('Không thể tải thông tin sản phẩm', 'error');
                        return;
                    }

                    document.getElementById('quickViewImage').src = `assets/images/${data.image}`;
                    document.getElementById('quickViewImage').alt = data.name;
                    document.getElementById('quickViewCategory').textContent = data.category;
                    document.getElementById('quickViewName').textContent = data.name;

                    const priceHtml = data.discount_price
                        ? `${new Intl.NumberFormat('vi-VN').format(data.discount_price)}₫ <span class="old-price">${new Intl.NumberFormat('vi-VN').format(data.price)}₫</span>`
                        : `${new Intl.NumberFormat('vi-VN').format(data.price)}₫`;
                    document.getElementById('quickViewPrice').innerHTML = priceHtml;

                    document.getElementById('quickViewDesc').textContent = data.description || 'Không có mô tả';
                    document.getElementById('quickViewDetailLink').href = `product-detail.php?id=${productId}`;
                    document.getElementById('quickViewQty').value = 1;

                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                })
                .catch(() => {
                    showToast('Có lỗi xảy ra', 'error');
                });
        }

        function closeQuickView() {
            const modal = document.getElementById('quickViewModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
            currentProductId = null;
        }

        function updateQty(change) {
            const input = document.getElementById('quickViewQty');
            let val = parseInt(input.value) + change;
            if (val < 1) val = 1;
            if (val > 10) val = 10;
            input.value = val;
        }

        function addToCartFromQuickView() {
            if (!currentProductId) return;
            const qty = document.getElementById('quickViewQty').value;

            fetch(`cart.php?add=${currentProductId}&qty=${qty}`)
                .then(() => {
                    showToast('Đã thêm vào giỏ hàng!', 'success');
                    closeQuickView();
                    if (typeof loadMiniCart === 'function') loadMiniCart();
                    updateCartBadge();
                })
                .catch(() => showToast('Có lỗi xảy ra', 'error'));
        }

        function addToWishlistFromQuickView() {
            if (!currentProductId) return;
            // Call wishlist API
            fetch('api/wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + currentProductId
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'added') {
                    showToast('Đã thêm vào yêu thích!', 'success');
                } else if (data.status === 'removed') {
                    showToast('Đã bỏ yêu thích!', 'success');
                }
            })
            .catch(() => showToast('Vui lòng đăng nhập để sử dụng tính năng này', 'warning'));
        }

        // Close modal on outside click
        document.getElementById('quickViewModal').addEventListener('click', function(e) {
            if (e.target === this) closeQuickView();
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeQuickView();
                document.getElementById('liveChatWindow').classList.remove('active');
            }
        });

        // Live Chat Functions
        let chatOpen = false;
        let chatHistory = []; // Lưu lịch sử để AI có ngữ cảnh
        let chatIsSending = false;

        function toggleLiveChat() {
            const win = document.getElementById('liveChatWindow');
            const btn = document.getElementById('liveChatBtn');
            chatOpen = !chatOpen;

            if (chatOpen) {
                win.classList.add('active');
                btn.classList.remove('has-new');
                setTimeout(() => document.getElementById('chatInput').focus(), 100);
            } else {
                win.classList.remove('active');
            }
        }

        function addTypingIndicator() {
            const body = document.getElementById('chatBody');
            const el = document.createElement('div');
            el.className = 'chat-message';
            el.id = 'typingIndicator';
            el.innerHTML = `
                <div class="chat-avatar"><i class="bi bi-robot"></i></div>
                <div>
                    <div class="chat-bubble" style="padding: 10px 16px;">
                        <span style="display:inline-flex;gap:4px;align-items:center;">
                            <span style="width:7px;height:7px;border-radius:50%;background:currentColor;animation:typingDot 1.2s infinite 0s;"></span>
                            <span style="width:7px;height:7px;border-radius:50%;background:currentColor;animation:typingDot 1.2s infinite 0.2s;"></span>
                            <span style="width:7px;height:7px;border-radius:50%;background:currentColor;animation:typingDot 1.2s infinite 0.4s;"></span>
                        </span>
                    </div>
                </div>
            `;
            body.appendChild(el);
            body.scrollTop = body.scrollHeight;
        }

        function removeTypingIndicator() {
            const el = document.getElementById('typingIndicator');
            if (el) el.remove();
        }

        async function sendChatMessage() {
            if (chatIsSending) return;
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message) return;

            chatIsSending = true;
            const body = document.getElementById('chatBody');
            const time = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });

            // Hiện tin nhắn của user
            body.innerHTML += `
                <div class="chat-message user">
                    <div class="chat-avatar user"><i class="bi bi-person"></i></div>
                    <div>
                        <div class="chat-bubble">${escapeHtml(message)}</div>
                        <div class="chat-time">${time}</div>
                    </div>
                </div>
            `;
            input.value = '';
            input.disabled = true;
            document.querySelector('.live-chat-send').disabled = true;
            body.scrollTop = body.scrollHeight;

            // Lưu vào history
            chatHistory.push({ role: 'user', content: message });

            // Hiện typing indicator
            addTypingIndicator();

            try {
                const res = await fetch(BASE_PATH + 'api/chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message, history: chatHistory.slice(-20) })
                });
                const data = await res.json();
                const reply = data.reply || 'Xin lỗi, em không hiểu ý bạn. Bạn có thể nói rõ hơn không ạ? 😊';

                removeTypingIndicator();
                const replyTime = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                // Dùng createElement để an toàn và render HTML (link, bold) từ bot
                const botMsg = document.createElement('div');
                botMsg.className = 'chat-message';
                // Chuyển \n thành <br> để xuống hàng đúng
                const replyHtml = reply.replace(/\n/g, '<br>');
                botMsg.innerHTML = `
                    <div class="chat-avatar"><i class="bi bi-robot"></i></div>
                    <div>
                        <div class="chat-bubble chat-bubble-html">${replyHtml}</div>
                        <div class="chat-time">${replyTime}</div>
                    </div>
                `;
                body.appendChild(botMsg);
                // Lưu reply vào history
                chatHistory.push({ role: 'assistant', content: reply });
            } catch (err) {
                removeTypingIndicator();
                body.innerHTML += `
                    <div class="chat-message">
                        <div class="chat-avatar"><i class="bi bi-robot"></i></div>
                        <div>
                            <div class="chat-bubble">Mất kết nối, vui lòng thử lại sau ạ! 🙏</div>
                            <div class="chat-time">Vừa xong</div>
                        </div>
                    </div>
                `;
                // Xóa tin nhắn lỗi khỏi history
                chatHistory.pop();
            } finally {
                chatIsSending = false;
                input.disabled = false;
                document.querySelector('.live-chat-send').disabled = false;
                body.scrollTop = body.scrollHeight;
                input.focus();
            }
        }

        function handleChatKeypress(e) {
            if (e.key === 'Enter' && !e.shiftKey) sendChatMessage();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Gửi tin nhắn gợi ý nhanh
        function sendQuickMsg(text) {
            // Ẩn chip sau khi dùng
            const chips = document.getElementById('chatQuickActions');
            if (chips) chips.style.display = 'none';
            document.getElementById('chatInput').value = text;
            sendChatMessage();
        }

        // CSS animation cho typing dots
        (function() {
            const style = document.createElement('style');
            style.textContent = `
                @keyframes typingDot {
                    0%, 60%, 100% { opacity: 0.2; transform: scale(0.8); }
                    30% { opacity: 1; transform: scale(1); }
                }
            `;
            document.head.appendChild(style);
        })();

        // Thông báo mới sau 20 giây
        setTimeout(() => {
            if (!chatOpen) {
                document.getElementById('liveChatBtn').classList.add('has-new');
            }
        }, 20000);

        // Recently Viewed Products
        function addToRecentlyViewed(product) {
            let recent = JSON.parse(localStorage.getItem('recentlyViewed') || '[]');
            recent = recent.filter(p => p.id !== product.id);
            recent.unshift(product);
            if (recent.length > 8) recent = recent.slice(0, 8);
            localStorage.setItem('recentlyViewed', JSON.stringify(recent));
        }

        function renderRecentlyViewed() {
            const container = document.getElementById('recentlyViewedContainer');
            if (!container) return;

            const recent = JSON.parse(localStorage.getItem('recentlyViewed') || '[]');
            if (recent.length === 0) {
                container.style.display = 'none';
                return;
            }

            const grid = container.querySelector('.recently-viewed-grid');
            grid.innerHTML = recent.map(p => `
                <a href="product-detail.php?id=${p.id}" class="recently-viewed-item">
                    <img src="assets/images/${p.image}" alt="${p.name}" onerror="this.src='https://placehold.co/200x150/f5f5f7/1d1d1f?text=Phone'">
                    <h4>${p.name}</h4>
                    <div class="price">${new Intl.NumberFormat('vi-VN').format(p.price)}₫</div>
                </a>
            `).join('');
        }

        // Render recently viewed on page load
        renderRecentlyViewed();
    </script>

    <!-- Debug Cart Script (Temporary) -->
    <script src="assets/js/debug-cart.js"></script>
</body>
</html>
