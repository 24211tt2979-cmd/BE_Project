<?php
/**
 * NHK Mobile - Compact Search Dropdown
 *
 * Description: Small dropdown search that appears below the search icon.
 * Takes minimal screen space.
 */
?>
<!-- Compact Search Dropdown -->
<div id="searchDropdown" class="search-dropdown d-none">
    <div class="search-dropdown-inner">
        <!-- Search Input -->
        <div class="search-input-wrap">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="searchInputMain"
                   class="search-input"
                   placeholder="Tìm kiếm..."
                   autocomplete="off">
            <button id="searchClear" class="search-clear d-none" title="Xóa">
                <i class="bi bi-x-circle-fill"></i>
            </button>
        </div>

        <!-- Quick Tags -->
        <div id="quickSuggestions" class="search-quick-tags">
            <a href="product.php?category=Apple" class="search-tag">iPhone 17 Pro</a>
            <a href="product.php?category=Samsung" class="search-tag">Samsung S25</a>
            <a href="product.php?category=Xiaomi" class="search-tag">Xiaomi Fold</a>
            <a href="product.php?category=Oppo" class="search-tag">Oppo Find</a>
        </div>

        <!-- Live Results -->
        <div id="searchResults" class="search-results d-none">
            <!-- Populated by JS -->
        </div>
    </div>
</div>

<style>
.search-wrapper {
    position: relative;
}

.search-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    width: 360px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    border: 1px solid var(--border-light, rgba(0,0,0,0.08));
    z-index: 1001;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
}

.search-dropdown.is-open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.search-dropdown-inner {
    padding: 16px;
}

.search-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.search-icon {
    position: absolute;
    left: 14px;
    font-size: 0.95rem;
    color: #8e8e93;
    pointer-events: none;
}

.search-input {
    width: 100%;
    padding: 10px 40px 10px 42px;
    background: rgba(118,118,128,0.1);
    border: 1.5px solid transparent;
    border-radius: 10px;
    font-size: 0.9rem;
    color: #1d1d1f;
    outline: none;
    transition: all 0.25s;
    font-family: inherit;
}

.search-input::placeholder { color: #8e8e93; }

.search-input:focus {
    background: #fff;
    border-color: var(--primary, #007AFF);
    box-shadow: 0 0 0 3px rgba(0,122,255,0.1);
}

.search-clear {
    position: absolute;
    right: 10px;
    background: none;
    border: none;
    color: #8e8e93;
    font-size: 0.9rem;
    cursor: pointer;
    padding: 4px;
    transition: color 0.2s;
}
.search-clear:hover { color: #1d1d1f; }

.search-quick-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(0,0,0,0.06);
}

.search-tag {
    display: inline-block;
    padding: 4px 12px;
    background: rgba(0,122,255,0.08);
    color: var(--primary, #007AFF);
    border: 1px solid rgba(0,122,255,0.18);
    border-radius: 16px;
    font-size: 0.78rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    white-space: nowrap;
}
.search-tag:hover {
    background: var(--primary, #007AFF);
    color: #fff;
    border-color: var(--primary, #007AFF);
}

.search-results {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid rgba(0,0,0,0.06);
    max-height: 280px;
    overflow-y: auto;
}

.search-results::-webkit-scrollbar { width: 4px; }
.search-results::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 10px; }

.search-result-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 10px;
    text-decoration: none !important;
    transition: all 0.2s;
}
.search-result-item:hover {
    background: rgba(0,122,255,0.06);
}

.search-result-img {
    width: 40px;
    height: 40px;
    object-fit: contain;
    background: #fff;
    padding: 4px;
    border-radius: 8px;
    border: 1px solid rgba(0,0,0,0.06);
    flex-shrink: 0;
}

.search-result-info { flex: 1; overflow: hidden; }
.search-result-name {
    color: #1d1d1f;
    font-weight: 600;
    font-size: 0.82rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.search-result-price {
    color: var(--primary, #007AFF);
    font-weight: 600;
    font-size: 0.78rem;
}

.search-no-result {
    text-align: center;
    padding: 16px;
    color: #8e8e93;
    font-size: 0.82rem;
}

/* Dark mode */
body.dark-mode .search-dropdown {
    background: #1a1a1a;
    border-color: rgba(255,255,255,0.08);
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
}
body.dark-mode .search-input {
    background: rgba(255,255,255,0.08);
    color: #f0f0f0;
}
body.dark-mode .search-input:focus {
    background: rgba(255,255,255,0.12);
}
body.dark-mode .search-tag:hover {
    background: var(--primary);
    color: #fff;
}
body.dark-mode .search-result-item:hover {
    background: rgba(0,122,255,0.12);
}
body.dark-mode .search-result-name { color: #f0f0f0; }
body.dark-mode .search-quick-tags,
body.dark-mode .search-results { border-top-color: rgba(255,255,255,0.07); }

/* Mobile optimization */
@media (max-width: 575.98px) {
    .search-dropdown {
        position: fixed;
        top: 70px;
        left: 50%;
        right: auto;
        width: 92vw;
        max-width: 400px;
        transform: translateX(-50%) translateY(-10px);
    }
    .search-dropdown.is-open {
        transform: translateX(-50%) translateY(0);
    }
}
</style>
