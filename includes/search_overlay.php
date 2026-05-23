<?php
/**
 * NHK Mobile - Search Dropdown Bar
 * 
 * Description: Compact animated search bar that slides down from navbar.
 * Replaces the old full-screen overlay for a less intrusive UX.
 * 
 * Author: NguyenHuuKhanh
 * Version: 3.0
 * Date: 2026-05-16
 */
?>
<!-- Search Dropdown Bar -->
<div id="searchOverlay" class="search-dropdown-bar d-none">
    <div class="search-dropdown-inner">
        <div class="container-wide">
            <div class="search-bar-row">
                <!-- Search Input -->
                <div class="search-bar-input-wrap">
                    <i class="bi bi-search search-bar-icon"></i>
                    <input type="text" id="searchInputMain"
                           class="search-bar-input"
                           placeholder="Tìm kiếm điện thoại, thương hiệu..."
                           autocomplete="off">
                    <button id="searchBarClear" class="search-bar-clear d-none" title="Xóa">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
                <!-- Close -->
                <button id="closeSearch" class="search-bar-close" title="Đóng">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Quick Tags -->
            <div id="quickSuggestions" class="search-quick-tags">
                <span class="search-quick-label">Gợi ý nhanh:</span>
                <a href="product.php?category=Apple" class="search-tag">iPhone 17 Pro</a>
                <a href="product.php?category=Samsung" class="search-tag">Samsung S25</a>
                <a href="product.php?category=Xiaomi" class="search-tag">Xiaomi Fold</a>
                <a href="product.php?category=Oppo" class="search-tag">Oppo Find</a>
            </div>

            <!-- Live Results -->
            <div id="searchResults" class="search-results-panel d-none">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Backdrop (dim, không che toàn bộ) -->
<div id="searchBackdrop" class="search-backdrop d-none"></div>

<style>
/* ── Search Dropdown Bar ── */
.search-dropdown-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9000;
    transform: translateY(-110%);
    transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                opacity 0.35s ease;
    opacity: 0;
}

.search-dropdown-bar.is-open {
    transform: translateY(0);
    opacity: 1;
}

.search-dropdown-inner {
    background: rgba(255, 255, 255, 0.97);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    padding: 18px 0 14px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
}

/* Dark mode */
body.dark-mode .search-dropdown-inner {
    background: rgba(18, 18, 18, 0.97);
    border-bottom-color: rgba(255,255,255,0.08);
}

/* Row layout */
.search-bar-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Input wrap */
.search-bar-input-wrap {
    flex: 1;
    position: relative;
    display: flex;
    align-items: center;
}

.search-bar-icon {
    position: absolute;
    left: 16px;
    font-size: 1.05rem;
    color: #8e8e93;
    pointer-events: none;
    transition: color 0.2s;
}

.search-bar-input {
    width: 100%;
    padding: 13px 44px 13px 46px;
    background: rgba(118, 118, 128, 0.1);
    border: 1.5px solid transparent;
    border-radius: 14px;
    font-size: 1rem;
    color: #1d1d1f;
    outline: none;
    transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
    font-family: inherit;
}

.search-bar-input::placeholder { color: #8e8e93; }

.search-bar-input:focus {
    background: #fff;
    border-color: var(--primary, #007AFF);
    box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1);
}

.search-bar-input:focus + .search-bar-icon { color: var(--primary, #007AFF); }

body.dark-mode .search-bar-input {
    background: rgba(255,255,255,0.08);
    color: #f0f0f0;
}
body.dark-mode .search-bar-input:focus {
    background: rgba(255,255,255,0.12);
}

/* Clear btn */
.search-bar-clear {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    color: #8e8e93;
    font-size: 1rem;
    cursor: pointer;
    padding: 4px;
    transition: color 0.2s, transform 0.2s;
}
.search-bar-clear:hover { color: #1d1d1f; transform: scale(1.15); }
body.dark-mode .search-bar-clear:hover { color: #f0f0f0; }

/* Close button */
.search-bar-close {
    background: rgba(118,118,128,0.12);
    border: none;
    border-radius: 50%;
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    color: #3c3c43;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.2s;
}
.search-bar-close:hover {
    background: rgba(118,118,128,0.22);
    transform: rotate(90deg);
}
body.dark-mode .search-bar-close { color: #f0f0f0; background: rgba(255,255,255,0.1); }
body.dark-mode .search-bar-close:hover { background: rgba(255,255,255,0.18); }

/* Quick Tags */
.search-quick-tags {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(0,0,0,0.06);
}
body.dark-mode .search-quick-tags { border-top-color: rgba(255,255,255,0.07); }

.search-quick-label {
    font-size: 0.78rem;
    color: #8e8e93;
    font-weight: 500;
    white-space: nowrap;
}

.search-tag {
    display: inline-block;
    padding: 5px 14px;
    background: rgba(0,122,255,0.08);
    color: var(--primary, #007AFF);
    border: 1px solid rgba(0,122,255,0.18);
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    white-space: nowrap;
}
.search-tag:hover {
    background: var(--primary, #007AFF);
    color: #fff;
    border-color: var(--primary, #007AFF);
    transform: translateY(-1px);
}

/* Live Results Panel */
.search-results-panel {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(0,0,0,0.06);
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 10px;
    max-height: 320px;
    overflow-y: auto;
    padding-right: 4px;
}
body.dark-mode .search-results-panel { border-top-color: rgba(255,255,255,0.07); }

.search-results-panel::-webkit-scrollbar { width: 5px; }
.search-results-panel::-webkit-scrollbar-track { background: transparent; }
.search-results-panel::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 10px; }

/* Result Cards */
.suggestion-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: rgba(118,118,128,0.07);
    border: 1px solid rgba(0,0,0,0.06);
    border-radius: 14px;
    text-decoration: none !important;
    transition: all 0.2s cubic-bezier(0.4,0,0.2,1);
}
.suggestion-card:hover {
    background: rgba(0,122,255,0.06);
    border-color: rgba(0,122,255,0.2);
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}
body.dark-mode .suggestion-card {
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.08);
}
body.dark-mode .suggestion-card:hover {
    background: rgba(0,122,255,0.12);
    border-color: rgba(0,122,255,0.3);
}

.suggestion-img {
    width: 52px;
    height: 52px;
    object-fit: contain;
    background: #fff;
    padding: 6px;
    border-radius: 10px;
    flex-shrink: 0;
    border: 1px solid rgba(0,0,0,0.06);
}

.suggestion-info { flex: 1; overflow: hidden; }
.suggestion-name {
    color: #1d1d1f;
    font-weight: 600;
    font-size: 0.88rem;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
body.dark-mode .suggestion-name { color: #f0f0f0; }

.suggestion-price {
    color: var(--primary, #007AFF);
    font-weight: 600;
    font-size: 0.82rem;
}

/* No results */
.search-no-result {
    grid-column: 1 / -1;
    text-align: center;
    padding: 20px;
    color: #8e8e93;
    font-size: 0.9rem;
}

/* Backdrop */
.search-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.25);
    z-index: 8999;
    backdrop-filter: blur(2px);
    opacity: 0;
    transition: opacity 0.3s ease;
}
.search-backdrop.is-open { opacity: 1; }
</style>
