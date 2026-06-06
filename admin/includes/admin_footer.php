    </main>

    <!-- BOTTOM NAVIGATION (App-style) -->
    <nav class="app-bottom-nav">
        <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Tổng quan</span>
        </a>
        <a href="products.php" class="nav-item <?php echo $current_page == 'products.php' ? 'active' : ''; ?>">
            <i class="bi bi-phone"></i>
            <span>Sản phẩm</span>
        </a>
        <a href="orders.php" class="nav-item <?php echo $current_page == 'orders.php' ? 'active' : ''; ?>">
            <i class="bi bi-receipt"></i>
            <span>Đơn hàng</span>
        </a>
        <a href="users.php" class="nav-item <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
            <i class="bi bi-people"></i>
            <span>Khách hàng</span>
        </a>
        <a href="revenue.php" class="nav-item <?php echo $current_page == 'revenue.php' ? 'active' : ''; ?>">
            <i class="bi bi-graph-up"></i>
            <span>Doanh thu</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebarMenu = document.getElementById('sidebarMenu');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarClose = document.getElementById('sidebarClose');

        function toggleSidebar() {
            if (sidebarMenu) sidebarMenu.classList.toggle('show');
            if (sidebarOverlay) sidebarOverlay.classList.toggle('show');
            document.body.classList.toggle('sidebar-open');
        }

        if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);
        if (sidebarClose) sidebarClose.addEventListener('click', toggleSidebar);

        // Swipe to close sidebar
        let touchStartX = 0;
        if (sidebarMenu) {
            sidebarMenu.addEventListener('touchstart', e => {
                touchStartX = e.touches[0].clientX;
            }, { passive: true });
            
            sidebarMenu.addEventListener('touchmove', e => {
                const diff = touchStartX - e.touches[0].clientX;
                if (diff > 80) toggleSidebar();
            }, { passive: true });
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 992 && sidebarMenu?.classList.contains('show')) {
                toggleSidebar();
            }
        });
    </script>
</body>
</html>
