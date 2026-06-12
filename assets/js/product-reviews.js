/**
 * NHK MOBILE - Product Reviews System (JS v2.0)
 *
 * Features:
 *  - 5-star clickable selector with hover preview (mouseover + mouseout + click)
 *  - AJAX GET to load reviews with pagination ("Xem thêm")
 *  - Rating breakdown bars (5★ → 1★) animated via CSS transition
 *  - AJAX POST to submit a new review
 *  - Login-gate: if server returns must_login=true, redirect to login page
 *  - Renders verified-purchase badge and optional review image
 *
 * SQL used in api/reviews.php (GET):
 *   SELECT COUNT(*) AS total,
 *          COALESCE(AVG(rating), 0) AS avg_rating,
 *          SUM(CASE WHEN rating=5 THEN 1 ELSE 0 END) AS r5,
 *          ... r4, r3, r2, r1
 *   FROM reviews WHERE product_id = ?
 *
 * SQL used in api/reviews.php (POST):
 *   INSERT INTO reviews
 *     (product_id, user_id, reviewer_name, reviewer_email,
 *      rating, title, content, verified_purchase, image)
 *   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
 *
 *   UPDATE products
 *   SET rating       = (SELECT COALESCE(AVG(rating),0) FROM reviews WHERE product_id = ?),
 *       review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ?)
 *   WHERE id = ?
 */

document.addEventListener('DOMContentLoaded', () => {
    // ── Guard: only run on pages that have the product_id hidden input ─────────
    const productIdEl = document.getElementById('product_id');
    if (!productIdEl) return;

    const productId = productIdEl.value;
    const LIMIT     = 5;   // reviews per page
    let   currentPage = 1;

    // ═══════════════════════════════════════════════════════════════════════════
    // 1. FIVE-STAR CLICKABLE SELECTOR
    //    Stars are bi-star-fill by default (value=5). Hovering dims stars to the
    //    right of the cursor, clicking locks the selection.
    // ═══════════════════════════════════════════════════════════════════════════
    const stars      = document.querySelectorAll('.rating-star');
    const ratingInput = document.getElementById('rating_val');

    /**
     * Paint stars: stars 1..val are gold, stars val+1..5 are dimmed.
     * @param {number} val  1–5
     */
    function paintStars(val) {
        stars.forEach(star => {
            const starVal = parseInt(star.dataset.value, 10);
            if (starVal <= val) {
                star.classList.remove('dim');
                star.classList.add('bi-star-fill');
                star.classList.remove('bi-star');
            } else {
                star.classList.add('dim');
                star.classList.add('bi-star');
                star.classList.remove('bi-star-fill');
            }
        });
    }

    // Boot to the currently saved rating (default 5)
    if (ratingInput) paintStars(parseInt(ratingInput.value, 10) || 5);

    stars.forEach(star => {
        const val = parseInt(star.dataset.value, 10);

        // Hover preview
        star.addEventListener('mouseover', () => paintStars(val));

        // Restore saved value on mouse-out
        star.addEventListener('mouseout', () => {
            paintStars(parseInt(ratingInput?.value, 10) || 5);
        });

        // Click = lock rating
        star.addEventListener('click', () => {
            if (ratingInput) ratingInput.value = val;
            paintStars(val);
        });
    });


    // ═══════════════════════════════════════════════════════════════════════════
    // 2. UPDATE SUMMARY PANEL  (avg, total, stars display, breakdown bars)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Re-render the left summary panel with fresh meta from the API.
     * @param {object} meta  { avg_rating, total, breakdown: {5:n, 4:n, …} }
     */
    function updateMeta(meta) {
        // ── Average score ──────────────────────────────────────────────────────
        const avgEl   = document.getElementById('avg-rating');
        const totalEl = document.getElementById('total-reviews');
        if (avgEl)   avgEl.textContent   = meta.avg_rating.toFixed(1);
        if (totalEl) totalEl.textContent = `${meta.total} đánh giá`;

        // ── Display stars (whole + half) ───────────────────────────────────────
        const starRatingEl = document.getElementById('star-rating');
        if (starRatingEl) {
            const full = Math.floor(meta.avg_rating);
            const half = meta.avg_rating - full >= 0.5;
            let html = '';
            for (let i = 0; i < full; i++)  html += '<i class="bi bi-star-fill"></i> ';
            if (half)                        html += '<i class="bi bi-star-half"></i> ';
            const empty = 5 - full - (half ? 1 : 0);
            for (let i = 0; i < empty; i++) html += '<i class="bi bi-star"></i> ';
            starRatingEl.innerHTML = html;
        }

        // ── Breakdown bars (5★ → 1★) ───────────────────────────────────────────
        // breakdown = { "5": count, "4": count, … }
        if (meta.breakdown && meta.total > 0) {
            [5, 4, 3, 2, 1].forEach(n => {
                const count = meta.breakdown[n] || 0;
                const pct   = Math.round((count / meta.total) * 100);

                const bar   = document.getElementById(`bar-${n}`);
                const label = document.getElementById(`count-${n}`);
                if (bar)   { bar.style.width = pct + '%'; bar.setAttribute('aria-valuenow', pct); }
                if (label) label.textContent = count;
            });
        }
    }


    // ═══════════════════════════════════════════════════════════════════════════
    // 3. RENDER REVIEW CARDS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Append (or replace) review cards in #reviews-list.
     * @param {array}   reviews  Array of review objects from API
     * @param {boolean} clear    If true, wipe the list first (page 1 reload)
     */
    function renderReviews(reviews, clear = false) {
        const list = document.getElementById('reviews-list');
        if (!list) return;

        if (clear) list.innerHTML = '';

        if (reviews.length === 0 && clear) {
            list.innerHTML = `
                <div class="text-center py-5 border rounded-4 bg-light">
                    <i class="bi bi-chat-square-text display-4 text-muted mb-3 d-block"></i>
                    <p class="text-muted mb-0">Chưa có đánh giá nào. Hãy là người đầu tiên!</p>
                </div>`;
            return;
        }

        reviews.forEach(r => {
            // Build star icons for this review
            let starsHtml = '';
            for (let i = 0; i < 5; i++) {
                starsHtml += i < r.rating
                    ? '<i class="bi bi-star-fill text-warning"></i> '
                    : '<i class="bi bi-star text-muted" style="opacity:.3"></i> ';
            }

            const verifiedBadge = '';

            const imagePart = r.image
                ? `<div class="mt-3">
                       <img src="assets/images/reviews/${r.image}"
                            class="img-fluid rounded-3 border"
                            style="max-height:150px; cursor:pointer;"
                            onclick="window.open(this.src)"
                            alt="Review image">
                   </div>`
                : '';

            const titlePart = r.title
                ? `<h6 class="fw-bold mb-2">${r.title}</h6>`
                : '';

            list.insertAdjacentHTML('beforeend', `
                <div class="py-4 border-bottom">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-dark text-white rounded-circle d-flex align-items-center
                                    justify-content-center fw-bold me-3"
                             style="width:44px; height:44px; font-size:18px; flex-shrink:0;">
                            ${r.avatar_letter}
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">${r.reviewer_name}${verifiedBadge}</h6>
                            <div class="small text-muted">${starsHtml} <span class="ms-1">${r.date_formatted}</span></div>
                        </div>
                    </div>
                    ${titlePart}
                    <p class="mb-0 text-secondary" style="line-height:1.7;">${r.content}</p>
                    ${imagePart}
                </div>
            `);
        });
    }


    // ═══════════════════════════════════════════════════════════════════════════
    // 4. LOAD REVIEWS (AJAX GET)
    //    SQL: SELECT … FROM reviews WHERE product_id = ? ORDER BY created_at DESC
    //         LIMIT ? OFFSET ?
    //    Plus aggregate: COUNT(*), AVG(rating), SUM(CASE WHEN rating=N …)
    // ═══════════════════════════════════════════════════════════════════════════

    async function loadReviews(page = 1) {
        try {
            const res  = await fetch(`api/reviews.php?id=${productId}&page=${page}&limit=${LIMIT}`);
            const data = await res.json();

            if (!data.success) return;

            renderReviews(data.reviews, page === 1);
            updateMeta(data.meta);

            // Xem thêm button
            const loadMoreBtn = document.getElementById('load-more-btn');
            if (loadMoreBtn) {
                if (data.meta.page < data.meta.total_pages) {
                    loadMoreBtn.classList.remove('d-none');
                    loadMoreBtn.onclick = () => loadReviews(page + 1);
                } else {
                    loadMoreBtn.classList.add('d-none');
                }
            }
        } catch (err) {
            console.error('[Reviews] Lỗi tải đánh giá:', err);
        }
    }


    // ═══════════════════════════════════════════════════════════════════════════
    // 5. SUBMIT REVIEW FORM (AJAX POST)
    //    SQL: INSERT INTO reviews (product_id, user_id, reviewer_name,
    //         reviewer_email, rating, title, content, verified_purchase, image)
    //         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    //    Then: UPDATE products SET rating=AVG, review_count=COUNT WHERE id=?
    // ═══════════════════════════════════════════════════════════════════════════

    const reviewForm = document.getElementById('review-form');
    if (reviewForm) {
        reviewForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const msgEl = document.getElementById('review-msg');
            const btn   = reviewForm.querySelector('button[type="submit"]');

            if (btn)   btn.disabled = true;
            if (msgEl) msgEl.innerHTML = '<span class="text-primary small"><span class="spinner-border spinner-border-sm me-1"></span>Đang gửi đánh giá...</span>';

            // Collect FormData (supports file upload)
            const fd = new FormData();
            fd.append('product_id', productId);
            fd.append('rating',     ratingInput?.value || 5);
            fd.append('title',      document.getElementById('review_title')?.value   || '');
            fd.append('content',    document.getElementById('review_content')?.value || '');

            // Guest fields (still in DOM for non-logged-in pages, but form is disabled)
            const nameEl  = document.getElementById('reviewer_name');
            const emailEl = document.getElementById('reviewer_email');
            if (nameEl)  fd.append('reviewer_name',  nameEl.value);
            if (emailEl) fd.append('reviewer_email', emailEl.value);

            const fileInput = document.getElementById('review_image');
            if (fileInput?.files.length > 0) fd.append('image', fileInput.files[0]);

            try {
                const res  = await fetch('api/reviews.php', { method: 'POST', body: fd });
                const data = await res.json();

                // ── Login gate: server says the user is not authenticated ───────
                if (data.must_login) {
                    if (msgEl) msgEl.innerHTML = `
                        <div class="alert alert-warning small py-2 px-3 rounded-3 mt-2">
                            <i class="bi bi-lock-fill me-1"></i>
                            ${data.error}
                            <a href="${data.redirect}" class="alert-link ms-1">Đăng nhập →</a>
                        </div>`;
                    if (btn) btn.disabled = false;
                    return;
                }

                if (data.success) {
                    if (msgEl) msgEl.innerHTML = `
                        <div class="alert alert-success small py-2 px-3 rounded-3 mt-2">
                            <i class="bi bi-check-circle-fill me-1"></i>
                            Cảm ơn bạn! Đánh giá đã được ghi nhận.
                        </div>`;
                    reviewForm.reset();
                    paintStars(5);                  // reset selector to 5 stars
                    if (ratingInput) ratingInput.value = 5;
                    setTimeout(() => loadReviews(1), 800); // reload list
                } else {
                    if (msgEl) msgEl.innerHTML = `
                        <div class="alert alert-danger small py-2 px-3 rounded-3 mt-2">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            ${data.error || 'Có lỗi xảy ra.'}
                        </div>`;
                }
            } catch (err) {
                if (msgEl) msgEl.innerHTML = `
                    <div class="alert alert-danger small py-2 px-3 rounded-3 mt-2">
                        <i class="bi bi-wifi-off me-1"></i>Lỗi kết nối máy chủ.
                    </div>`;
                console.error('[Reviews] Submit error:', err);
            } finally {
                if (btn) btn.disabled = false;
            }
        });
    }


    // ── Initial load ───────────────────────────────────────────────────────────
    loadReviews();
});
