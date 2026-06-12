document.addEventListener('DOMContentLoaded', function () {

    // ── Elements ──────────────────────────────────────────────────
    const searchTrigger   = document.getElementById('searchTrigger');
    const searchDropdown  = document.getElementById('searchDropdown');
    const searchInput     = document.getElementById('searchInputMain');
    const searchResults   = document.getElementById('searchResults');
    const quickSuggestions = document.getElementById('quickSuggestions');
    const searchClear     = document.getElementById('searchClear');

    if (!searchTrigger || !searchDropdown) return;

    // ── Helpers ───────────────────────────────────────────────────
    function openSearch() {
        searchDropdown.classList.remove('d-none');
        requestAnimationFrame(() => {
            searchDropdown.classList.add('is-open');
        });
        setTimeout(() => searchInput.focus(), 100);
    }

    // Close search dropdown
    function closeSearch() {
        searchDropdown.classList.remove('is-open');
        setTimeout(() => {
            searchDropdown.classList.add('d-none');
        }, 300);
        resetSearch();
    }

    function resetSearch() {
        searchInput.value = '';
        searchResults.classList.add('d-none');
        searchResults.innerHTML = '';
        quickSuggestions.classList.remove('d-none');
        searchClear.classList.add('d-none');
    }

    // ── Events ────────────────────────────────────────────────────
    searchTrigger.addEventListener('click', function (e) {
        e.preventDefault();
        if (searchDropdown.classList.contains('is-open')) {
            closeSearch();
        } else {
            openSearch();
        }
    });

    if (searchClear) {
        searchClear.addEventListener('click', function () {
            searchInput.value = '';
            searchInput.focus();
            searchClear.classList.add('d-none');
            searchResults.classList.add('d-none');
            quickSuggestions.classList.remove('d-none');
        });
    }

    // Close on outside click
    document.addEventListener('click', function (e) {
        const wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchDropdown.classList.contains('is-open')) {
            closeSearch();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && searchDropdown.classList.contains('is-open')) {
            closeSearch();
        }
    });

    // ── Live Search (Debounce) ────────────────────────────────────
    let debounceTimer;
    searchInput.addEventListener('input', function () {
        const query = this.value.trim();

        searchClear.classList.toggle('d-none', query.length === 0);

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
            searchResults.innerHTML = '<div class="search-no-result"><i class="bi bi-search me-2"></i>Không tìm thấy kết quả...</div>';
            return;
        }

        searchResults.innerHTML = data.map(item => `
            <a href="${item.url}" class="search-result-item">
                <img src="assets/images/${item.image}"
                     class="search-result-img"
                     onerror="this.src='https://placehold.co/40x40?text=?'">
                <div class="search-result-info">
                    <div class="search-result-name">${item.name}</div>
                    <div class="search-result-price">${item.formatted_price}</div>
                </div>
            </a>
        `).join('');
    }

});
