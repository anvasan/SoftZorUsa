/**
 * SoftMir Click Tracker
 * Tracks clicks on CTA buttons (visit website / go to product page)
 * and updates the counter on the card in real time.
 */
(function () {
    'use strict';

    if (typeof softmirClickTracker === 'undefined') return;

    document.addEventListener('click', function (e) {
        // Find closest tracked link
        var link = e.target.closest('[data-track-click]');
        if (!link) return;

        var postId = link.getAttribute('data-track-click');
        if (!postId) return;

        // Send AJAX request (fire-and-forget)
        var xhr = new XMLHttpRequest();
        xhr.open('POST', softmirClickTracker.ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success && resp.data && resp.data.clicks) {
                        // Update all counters for this post on the page
                        var counters = document.querySelectorAll('.click-count[data-post-id="' + postId + '"]');
                        counters.forEach(function (el) {
                            el.textContent = resp.data.clicks;
                        });
                    }
                } catch (ex) { /* silent */ }
            }
        };
        xhr.send(
            'action=softmir_track_click' +
            '&nonce=' + encodeURIComponent(softmirClickTracker.nonce) +
            '&post_id=' + encodeURIComponent(postId)
        );
    });
})();
