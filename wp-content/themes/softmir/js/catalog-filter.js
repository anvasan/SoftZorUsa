/**
 * SoftMir — Catalog AJAX Filter
 * Filters catalog without page reload, updates URL with pushState
 */
(function () {
    'use strict';

    var form = document.getElementById('catalog-filter-form');
    var resultsContainer = document.getElementById('catalog-results');
    var countEl = document.getElementById('catalog-count');

    if (!form || !resultsContainer) return;

    // Create loading overlay
    var overlay = document.createElement('div');
    overlay.className = 'catalog-loading-overlay';
    overlay.innerHTML = '<div class="catalog-spinner"></div>';
    overlay.style.display = 'none';
    resultsContainer.appendChild(overlay);

    /**
     * Serialize form data including checkboxes properly
     */
    function serializeForm(formEl) {
        var data = new FormData(formEl);
        var params = {};

        for (var pair of data.entries()) {
            var key = pair[0];
            var val = pair[1];
            var cleanKey = key;

            // Handle arrays (checkboxes with [] in name)
            if (key.endsWith('[]')) {
                if (!params[cleanKey]) {
                    params[cleanKey] = [];
                }
                params[cleanKey].push(val);
            } else {
                params[cleanKey] = val;
            }
        }
        return params;
    }

    /**
     * Build URL search string from params
     */
    function buildQueryString(params) {
        var parts = [];
        for (var key in params) {
            if (!params.hasOwnProperty(key)) continue;
            var val = params[key];
            if (Array.isArray(val)) {
                val.forEach(function (v) {
                    if (v !== '') parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(v));
                });
            } else {
                if (val !== '') parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(val));
            }
        }
        return parts.length > 0 ? '?' + parts.join('&') : '';
    }

    /**
     * Execute AJAX filter request
     */
    function doFilter(page) {
        page = page || 1;

        // Show loading
        overlay.style.display = 'flex';

        // Gather form data
        var formParams = serializeForm(form);

        // Build AJAX POST data
        var postData = new FormData();
        postData.append('action', 'softmir_filter_catalog');
        postData.append('nonce', softmirCatalog.nonce);
        postData.append('paged', page);

        // Add form fields to POST
        for (var key in formParams) {
            if (!formParams.hasOwnProperty(key)) continue;
            var val = formParams[key];
            if (Array.isArray(val)) {
                // Need to send as proper array for PHP
                var phpKey = key.endsWith('[]') ? key : key + '[]';
                val.forEach(function (v) {
                    postData.append(phpKey, v);
                });
            } else {
                // Strip [] from non-array keys
                postData.append(key.replace('[]', ''), val);
            }
        }

        // Stabilize layout height to prevent jump
        resultsContainer.style.minHeight = resultsContainer.offsetHeight + 'px';
        resultsContainer.style.opacity = '0.4';
        resultsContainer.style.transition = 'opacity 0.2s ease';

        fetch(softmirCatalog.ajaxurl, {
            method: 'POST',
            body: postData,
            credentials: 'same-origin',
        })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (result.success && result.data) {
                    // Update results HTML
                    resultsContainer.innerHTML = result.data.html;

                    // Fade in new content
                    resultsContainer.style.opacity = '1';
                    // Reset min-height after content renders
                    requestAnimationFrame(function () {
                        resultsContainer.style.minHeight = '';
                    });

                    // Re-append overlay (innerHTML cleared it)
                    overlay.style.display = 'none';
                    resultsContainer.appendChild(overlay);

                    // Update count
                    if (countEl) {
                        // Count might have been inside the old container; find it again
                        var newCount = document.getElementById('catalog-count');
                        if (!newCount) {
                            // The count is outside catalog-results, so it still exists
                            countEl.textContent = result.data.found_posts;
                        }
                    }

                    // Re-initialize view switcher if it exists
                    if (typeof initViewSwitcher === 'function') {
                        initViewSwitcher();
                    }

                    // Update URL without reload
                    var qs = buildQueryString(formParams);
                    // Strip /page/X/ from pathname so we have the base URL
                    var basePath = window.location.pathname.replace(/\/page\/\d+\/?$/, '/');
                    var newUrl = basePath + qs;
                    if (page > 1) {
                        newUrl += (qs ? '&' : '?') + 'paged=' + page;
                    }
                    history.pushState({ page: page }, '', newUrl);

                    // Scroll to results
                    resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            })
            .catch(function (err) {
                console.error('SoftMir filter error:', err);
                overlay.style.display = 'none';
                resultsContainer.style.opacity = '1';
                resultsContainer.style.minHeight = '';
            });
    }

    // Intercept form submit
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        doFilter(1);
    });

    // AJAX pagination — delegate click on pagination links
    resultsContainer.addEventListener('click', function (e) {
        var link = e.target.closest('.catalog-pagination a');
        if (link) {
            e.preventDefault();
            // Extract page number from URL (handles both ?paged=2 and /page/2/)
            var url = new URL(link.href);
            var page = url.searchParams.get('paged');
            if (!page) {
                var match = url.pathname.match(/\/page\/(\d+)/);
                page = match ? match[1] : 1;
            }
            doFilter(parseInt(page, 10));
        }
    });

    // Handle browser back/forward
    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.page) {
            doFilter(e.state.page);
        }
    });
})();
