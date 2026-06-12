<?php
/**
 * NHK Mobile - Search Overlay
 *
 * Premium search with live suggestions, keyboard nav, and beautiful UI.
 */
?>
<!-- Search Overlay -->
<div id="searchOverlay" class="search-overlay">
  <div class="search-overlay-backdrop" id="searchBackdrop"></div>
  <div class="search-overlay-panel">
    <div class="container-wide">
      <!-- Search Bar -->
      <div class="so-bar">
        <div class="so-input-wrap">
          <i class="bi bi-search so-search-icon"></i>
          <input type="text" id="searchInputMain" class="so-input"
                 placeholder="Tìm kiếm điện thoại, thương hiệu, phụ kiện..."
                 autocomplete="off" autocapitalize="none">
          <button id="searchBarClear" class="so-clear d-none" title="Xóa">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <button id="closeSearch" class="so-close-btn" title="Đóng (Esc)">
          <i class="bi bi-arrow-left"></i>
          <span>Quay lại</span>
        </button>
      </div>

      <!-- Loading indicator -->
      <div id="searchLoading" class="so-loading d-none">
        <div class="so-loading-spinner"></div>
        <span>Đang tìm kiếm...</span>
      </div>

      <!-- Quick suggestions (default state) -->
      <div id="quickSuggestions" class="so-quick">
        <div class="so-quick-header">
          <i class="bi bi-lightning-charge-fill"></i>
          <span>Gợi ý nhanh</span>
        </div>
        <div class="so-quick-tags">
          <a href="product.php?category=Apple" class="so-tag"><i class="bi bi-apple"></i> iPhone 17 Pro</a>
          <a href="product.php?category=Samsung" class="so-tag"><i class="bi bi-phone"></i> Galaxy S25</a>
          <a href="product.php?category=Xiaomi" class="so-tag"><i class="bi bi-lightning-charge"></i> Xiaomi 17 Ultra</a>
          <a href="product.php?category=Oppo" class="so-tag"><i class="bi bi-camera"></i> Oppo Find N5</a>
          <a href="product.php" class="so-tag so-tag-all"><i class="bi bi-grid-3x3-gap"></i> Tất cả sản phẩm</a>
        </div>
      </div>

      <!-- Results panel -->
      <div id="searchResults" class="so-results d-none"></div>

      <!-- No results -->
      <div id="searchNoResult" class="so-no-result d-none">
        <div class="so-no-result-icon"><i class="bi bi-search"></i></div>
        <h4>Không tìm thấy kết quả</h4>
        <p>Thử thay đổi từ khóa hoặc duyệt danh mục bên dưới</p>
        <div class="so-no-result-tags">
          <a href="product.php?category=Apple" class="so-tag">iPhone</a>
          <a href="product.php?category=Samsung" class="so-tag">Samsung</a>
          <a href="product.php?category=Xiaomi" class="so-tag">Xiaomi</a>
          <a href="product.php" class="so-tag">Tất cả</a>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* ───── OVERLAY ───── */
.search-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: none;
}
.search-overlay.is-open {
  display: flex;
}
.search-overlay-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}
.search-overlay-panel {
  position: relative;
  width: calc(100% - 32px);
  max-width: 780px;
  margin: 80px auto 0;
  background: #fff;
  border-radius: 24px;
  box-shadow: 0 32px 80px rgba(0, 0, 0, 0.2);
  padding: 28px 32px 32px;
  max-height: calc(100vh - 160px);
  overflow-y: auto;
  animation: so-slide-in 0.3s cubic-bezier(0.22, 1, 0.36, 1);
  align-self: flex-start;
}
@keyframes so-slide-in {
  from { opacity: 0; transform: translateY(-20px) scale(0.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}
body.dark-mode .search-overlay-panel {
  background: #1c1c1e;
  box-shadow: 0 32px 80px rgba(0, 0, 0, 0.5);
}

/* ───── SEARCH BAR ───── */
.so-bar {
  display: flex;
  align-items: center;
  gap: 12px;
}
.so-input-wrap {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
}
.so-search-icon {
  position: absolute;
  left: 16px;
  font-size: 1.1rem;
  color: #8e8e93;
  pointer-events: none;
  transition: color 0.2s;
}
.so-input {
  width: 100%;
  padding: 14px 48px 14px 48px;
  background: #f2f2f7;
  border: 2px solid transparent;
  border-radius: 16px;
  font-size: 1rem;
  color: #1d1d1f;
  outline: none;
  transition: all 0.25s;
  font-family: inherit;
}
.so-input::placeholder { color: #8e8e93; }
.so-input:focus {
  background: #fff;
  border-color: #007AFF;
  box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.12);
}
.so-input:focus ~ .so-search-icon,
.so-input:focus + .so-search-icon { color: #007AFF; }
body.dark-mode .so-input {
  background: #2c2c2e;
  color: #f0f0f0;
}
body.dark-mode .so-input:focus {
  background: #2c2c2e;
  border-color: #0a84ff;
  box-shadow: 0 0 0 4px rgba(10, 132, 255, 0.2);
}
.so-clear {
  position: absolute;
  right: 12px;
  background: #8e8e93;
  border: none;
  border-radius: 50%;
  width: 26px;
  height: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 0.7rem;
  cursor: pointer;
  transition: all 0.2s;
}
.so-clear:hover { background: #636366; transform: scale(1.1); }
body.dark-mode .so-clear { background: #48484a; }
body.dark-mode .so-clear:hover { background: #636366; }

.so-close-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: none;
  color: #007AFF;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  padding: 8px 12px;
  border-radius: 12px;
  transition: all 0.2s;
  white-space: nowrap;
}
.so-close-btn:hover { background: rgba(0,122,255,0.08); }
body.dark-mode .so-close-btn { color: #0a84ff; }
body.dark-mode .so-close-btn:hover { background: rgba(10,132,255,0.12); }

/* ───── LOADING ───── */
.so-loading {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 24px 0 8px;
  color: #8e8e93;
  font-size: 0.9rem;
}
.so-loading-spinner {
  width: 20px;
  height: 20px;
  border: 2.5px solid #e0e0e0;
  border-top-color: #007AFF;
  border-radius: 50%;
  animation: so-spin 0.7s linear infinite;
}
@keyframes so-spin { to { transform: rotate(360deg); } }
body.dark-mode .so-loading-spinner { border-color: #3a3a3c; border-top-color: #0a84ff; }

/* ───── QUICK SUGGESTIONS ───── */
.so-quick { margin-top: 20px; }
.so-quick-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #8e8e93;
  margin-bottom: 12px;
}
.so-quick-header i { color: #f59e0b; }
.so-quick-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.so-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  background: #f2f2f7;
  color: #1d1d1f;
  border: 1px solid transparent;
  border-radius: 40px;
  font-size: 0.85rem;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
  white-space: nowrap;
}
.so-tag:hover {
  background: #007AFF;
  color: #fff;
  border-color: #007AFF;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,122,255,0.25);
}
.so-tag i { font-size: 0.9rem; }
.so-tag-all { background: #e8f0fe; color: #007AFF; font-weight: 600; }
.so-tag-all:hover { background: #007AFF; color: #fff; }
body.dark-mode .so-tag { background: #2c2c2e; color: #f0f0f0; }
body.dark-mode .so-tag:hover { background: #0a84ff; color: #fff; }
body.dark-mode .so-tag-all { background: rgba(10,132,255,0.15); color: #0a84ff; }

/* ───── RESULTS ───── */
.so-results { margin-top: 16px; }
.so-results-group-label {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #8e8e93;
  margin: 16px 0 8px;
  padding-bottom: 6px;
  border-bottom: 1px solid #f0f0f0;
}
body.dark-mode .so-results-group-label { border-bottom-color: #2c2c2e; }

.so-result-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 10px 14px;
  border-radius: 14px;
  text-decoration: none;
  transition: all 0.15s;
  cursor: pointer;
}
.so-result-item:hover,
.so-result-item.highlighted {
  background: #f2f2f7;
}
body.dark-mode .so-result-item:hover,
body.dark-mode .so-result-item.highlighted {
  background: #2c2c2e;
}
.so-result-img {
  width: 52px;
  height: 52px;
  object-fit: contain;
  background: #fff;
  padding: 6px;
  border-radius: 10px;
  flex-shrink: 0;
  border: 1px solid #f0f0f0;
}
body.dark-mode .so-result-img { border-color: #3a3a3c; }
.so-result-info { flex: 1; min-width: 0; }
.so-result-name {
  font-weight: 600;
  font-size: 0.88rem;
  color: #1d1d1f;
  margin-bottom: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
body.dark-mode .so-result-name { color: #f0f0f0; }
.so-result-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.75rem;
}
.so-result-category {
  color: #8e8e93;
  background: #f0f0f0;
  padding: 2px 8px;
  border-radius: 6px;
}
body.dark-mode .so-result-category { background: #3a3a3c; color: #a0a0a0; }
.so-result-stock {
  color: #22c55e;
  font-weight: 500;
}
.so-result-stock.out {
  color: #ef4444;
}
.so-result-price {
  font-weight: 700;
  color: #007AFF;
  font-size: 0.9rem;
  white-space: nowrap;
}
body.dark-mode .so-result-price { color: #0a84ff; }

/* ───── NO RESULT ───── */
.so-no-result {
  text-align: center;
  padding: 40px 0 20px;
}
.so-no-result-icon {
  font-size: 2.5rem;
  color: #c7c7cc;
  margin-bottom: 12px;
}
.so-no-result h4 {
  font-weight: 700;
  color: #1d1d1f;
  margin-bottom: 6px;
}
body.dark-mode .so-no-result h4 { color: #f0f0f0; }
.so-no-result p {
  color: #8e8e93;
  font-size: 0.9rem;
  margin-bottom: 16px;
}
.so-no-result-tags {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 8px;
}

/* ───── SCROLLBAR ───── */
.search-overlay-panel::-webkit-scrollbar { width: 5px; }
.search-overlay-panel::-webkit-scrollbar-track { background: transparent; }
.search-overlay-panel::-webkit-scrollbar-thumb { background: #c7c7cc; border-radius: 10px; }
body.dark-mode .search-overlay-panel::-webkit-scrollbar-thumb { background: #3a3a3c; }

/* ───── RESPONSIVE ───── */
@media (max-width: 820px) {
  .search-overlay {
    align-items: flex-start;
    padding: 0 12px;
  }
  .search-overlay-panel {
    margin: 70px auto 16px;
    max-height: calc(100vh - 100px);
    border-radius: 20px;
    padding: 16px 20px 24px;
    width: 100%;
  }
  .so-close-btn span { display: none; }
}
@media (max-width: 480px) {
  .search-overlay-panel {
    margin: 60px auto 16px;
    max-height: calc(100vh - 90px);
    border-radius: 18px;
    padding: 14px 16px 20px;
  }
}
</style>
