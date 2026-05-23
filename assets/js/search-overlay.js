document.addEventListener('DOMContentLoaded', function () {

    // ── Elements ──────────────────────────────────────────────────
    const searchTrigger   = document.getElementById('searchTrigger');
    const searchOverlay   = document.getElementById('searchOverlay');   // dropdown bar
    const searchBackdrop  = document.getElementById('searchBackdrop');
    const closeSearch     = document.getElementById('closeSearch');
    const searchInput     = document.getElementById('searchInputMain');
    const searchResults   = document.getElementById('searchResults');
    const quickSuggestions = document.getElementById('quickSuggestions');
    const searchBarClear  = document.getElementById('searchBarClear');

    if (!searchTrigger || !searchOverlay) return;

    // ── Helpers ───────────────────────────────────────────────────
    function openSearch() {
        searchOverlay.classList.remove('d-none');
        searchBackdrop.classList.remove('d-none');

        // Kích hoạt animation sau 1 frame để CSS transition hoạt động
        requestAnimationFrame(() => {
            searchOverlay.classList.add('is-open');
            searchBackdrop.classList.add('is-open');
        });

        document.body.style.overflow = 'hidden';
        setTimeout(() => searchInput.focus(), 200);
    }

    function closeOverlay() {
        searchOverlay.classList.remove('is-open');
        searchBackdrop.classList.remove('is-open');

        // Sau khi animation xong mới ẩn khỏi DOM
        setTimeout(() => {
            searchOverlay.classList.add('d-none');
            searchBackdrop.classList.add('d-none');
        }, 370);

        document.body.style.overflow = '';
        resetSearch();
    }

    function resetSearch() {
        searchInput.value = '';
        searchResults.classList.add('d-none');
        searchResults.innerHTML = '';
        quickSuggestions.classList.remove('d-none');
        searchBarClear.classList.add('d-none');
    }

    // ── Events ────────────────────────────────────────────────────
    searchTrigger.addEventListener('click', function (e) {
        e.preventDefault();
        openSearch();
    });

    if (closeSearch)    closeSearch.addEventListener('click', closeOverlay);
    if (searchBackdrop) searchBackdrop.addEventListener('click', closeOverlay);

    if (searchBarClear) {
        searchBarClear.addEventListener('click', function () {
            searchInput.value = '';
            searchInput.focus();
            searchBarClear.classList.add('d-none');
            searchResults.classList.add('d-none');
            quickSuggestions.classList.remove('d-none');
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && searchOverlay.classList.contains('is-open')) {
            closeOverlay();
        }
    });

    // ── Live Search (Debounce) ────────────────────────────────────
    let debounceTimer;
    searchInput.addEventListener('input', function () {
        const query = this.value.trim();

        // Hiện / ẩn nút xóa
        searchBarClear.classList.toggle('d-none', query.length === 0);

        clearTimeout(debounceTimer);

        if (query.length < 1) {
            searchResults.classList.add('d-none');
            quickSuggestions.classList.remove('d-none');
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`${SEARCH_API_URL}?q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => renderResults(data))
                .catch(err => console.error('Lỗi tìm kiếm:', err));
        }, 300);
    });

    // ── Render Results ────────────────────────────────────────────
    function renderResults(data) {
        quickSuggestions.classList.add('d-none');
        searchResults.classList.remove('d-none');

        if (!data || data.length === 0) {
            searchResults.innerHTML = '<div class="search-no-result"><i class="bi bi-search me-2"></i>Không tìm thấy kết quả nào...</div>';
            return;
        }

        searchResults.innerHTML = data.map(item => `
            <a href="${item.url}" class="suggestion-card">
                <img src="assets/images/${item.image}"
                     class="suggestion-img"
                     style="${item.type === 'news' ? 'object-fit:cover;' : 'object-fit:contain;'}"
                     onerror="this.src='https://placehold.co/60x60?text=?'">
                <div class="suggestion-info">
                    <div class="suggestion-name">${item.name}</div>
                    <div class="suggestion-price">${item.formatted_price}</div>
                </div>
            </a>
        `).join('');
    }

});
