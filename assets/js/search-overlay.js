document.addEventListener('DOMContentLoaded', function () {

  const overlay       = document.getElementById('searchOverlay');
  const backdrop      = document.getElementById('searchBackdrop');
  const trigger       = document.getElementById('searchTrigger');
  const closeBtn      = document.getElementById('closeSearch');
  const input         = document.getElementById('searchInputMain');
  const clearBtn      = document.getElementById('searchBarClear');
  const quickSuggest  = document.getElementById('quickSuggestions');
  const resultsPanel  = document.getElementById('searchResults');
  const noResultPanel = document.getElementById('searchNoResult');
  const loadingEl     = document.getElementById('searchLoading');

  if (!overlay || !trigger) return;

  let isOpen = false;
  let debounceTimer;
  let highlightIdx = -1;
  let currentResults = [];

  // ── Open ──────────────────────────────────────────────────────────
  function openSearch() {
    if (isOpen) return;
    isOpen = true;
    overlay.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    setTimeout(function () { input.focus(); }, 150);
  }

  // ── Close ─────────────────────────────────────────────────────────
  function closeSearch() {
    if (!isOpen) return;
    isOpen = false;
    overlay.classList.remove('is-open');
    document.body.style.overflow = '';
    resetUI();
  }

  // ── Reset ─────────────────────────────────────────────────────────
  function resetUI() {
    input.value = '';
    resultsPanel.classList.add('d-none');
    resultsPanel.innerHTML = '';
    noResultPanel.classList.add('d-none');
    quickSuggest.classList.remove('d-none');
    loadingEl.classList.add('d-none');
    clearBtn.classList.add('d-none');
    highlightIdx = -1;
    currentResults = [];
  }

  // ── Events ────────────────────────────────────────────────────────
  trigger.addEventListener('click', function (e) {
    e.preventDefault();
    openSearch();
  });
  if (closeBtn)  closeBtn.addEventListener('click', closeSearch);
  if (backdrop)  backdrop.addEventListener('click', closeSearch);

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      input.value = '';
      input.focus();
      clearBtn.classList.add('d-none');
      resultsPanel.classList.add('d-none');
      noResultPanel.classList.add('d-none');
      quickSuggest.classList.remove('d-none');
      highlightIdx = -1;
      currentResults = [];
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && isOpen) { closeSearch(); return; }

    if (!isOpen || resultsPanel.classList.contains('d-none')) return;

    // Arrow navigation
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      highlightIdx = Math.min(highlightIdx + 1, currentResults.length - 1);
      applyHighlight();
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      highlightIdx = Math.max(highlightIdx - 1, 0);
      applyHighlight();
    }
    if (e.key === 'Enter') {
      if (highlightIdx >= 0 && currentResults[highlightIdx]) {
        e.preventDefault();
        window.location.href = currentResults[highlightIdx].url;
      } else {
        e.preventDefault();
        window.location.href = BASE_PATH + 'product.php?q=' + encodeURIComponent(input.value.trim());
      }
    }
  });

  // ── Live search ───────────────────────────────────────────────────
  input.addEventListener('input', function () {
    const query = this.value.trim();
    clearBtn.classList.toggle('d-none', query.length === 0);

    clearTimeout(debounceTimer);

    if (query.length < 1) {
      resultsPanel.classList.add('d-none');
      noResultPanel.classList.add('d-none');
      quickSuggest.classList.remove('d-none');
      loadingEl.classList.add('d-none');
      highlightIdx = -1;
      currentResults = [];
      return;
    }

    quickSuggest.classList.add('d-none');
    loadingEl.classList.remove('d-none');
    resultsPanel.classList.add('d-none');
    noResultPanel.classList.add('d-none');

    debounceTimer = setTimeout(function () {
      fetch(BASE_PATH + 'api/search_suggestions.php?q=' + encodeURIComponent(query))
        .then(function (r) { return r.json(); })
        .then(function (data) { renderResults(data); })
        .catch(function (err) { console.error('Search error:', err); loadingEl.classList.add('d-none'); });
    }, 250);
  });

  // ── Render ────────────────────────────────────────────────────────
  function renderResults(data) {
    loadingEl.classList.add('d-none');

    if (!data || data.length === 0) {
      resultsPanel.classList.add('d-none');
      resultsPanel.innerHTML = '';
      noResultPanel.classList.remove('d-none');
      highlightIdx = -1;
      currentResults = [];
      return;
    }

    noResultPanel.classList.add('d-none');
    resultsPanel.classList.remove('d-none');
    highlightIdx = -1;
    currentResults = data;

    var html = '';
    for (var i = 0; i < data.length; i++) {
      var item = data[i];
      var stockText = item.in_stock
        ? '<span class="so-result-stock">Còn hàng</span>'
        : '<span class="so-result-stock out">Hết hàng</span>';
      html += '<a href="' + item.url + '" class="so-result-item" data-index="' + i + '">'
        + '<img src="' + BASE_PATH + 'assets/images/' + item.image + '" class="so-result-img"'
        + ' onerror="this.src=\'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22 fill=%22%23ddd%22%3E%3Crect width=%2260%22 height=%2260%22/%3E%3Ctext x=%2230%22 y=%2235%22 text-anchor=%22middle%22 font-size=%2224%22 fill=%22%23999%22%3E📱%3C/text%3E%3C/svg%3E\'" alt="">'
        + '<div class="so-result-info">'
        + '<div class="so-result-name">' + escapeHtml(item.name) + '</div>'
        + '<div class="so-result-meta">'
        + '<span class="so-result-category">' + escapeHtml(item.category) + '</span>'
        + stockText
        + '</div>'
        + '</div>'
        + '<div class="so-result-price">' + item.formatted_price + '</div>'
        + '</a>';
    }
    resultsPanel.innerHTML = html;
  }

  function applyHighlight() {
    var items = resultsPanel.querySelectorAll('.so-result-item');
    items.forEach(function (el, i) {
      el.classList.toggle('highlighted', i === highlightIdx);
    });
    if (highlightIdx >= 0 && items[highlightIdx]) {
      items[highlightIdx].scrollIntoView({ block: 'nearest' });
    }
  }

  function escapeHtml(text) {
    var d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
  }
});
