/**
 * SoftZor Smart Promotions Frontend Tracker
 * Tracks Impressions (via IntersectionObserver) and Clicks (via sendBeacon/fetch)
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof szPromoData === 'undefined') {
            return;
        }

        var wrappers = document.querySelectorAll('.sz-rec-wrapper');
        if (!wrappers.length) {
            return;
        }

        // 1. Impression Tracker (Честные показы в зоне видимости)
        if ('IntersectionObserver' in window) {
            var viewObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
                        var el = entry.target;
                        if (!el.dataset.szViewTracked) {
                            el.dataset.szViewTracked = 'true';
                            sendEvent('sz_track_view', el.dataset.promoId, el.dataset.placement);
                            viewObserver.unobserve(el);
                        }
                    }
                });
            }, {
                threshold: 0.5
            });

            wrappers.forEach(function(el) {
                viewObserver.observe(el);
            });
        } else {
            // Fallback for older browsers
            wrappers.forEach(function(el) {
                sendEvent('sz_track_view', el.dataset.promoId, el.dataset.placement);
            });
        }

        // 2. Click Tracker
        wrappers.forEach(function(wrapper) {
            var promoId = wrapper.dataset.promoId;
            var placement = wrapper.dataset.placement;
            var links = wrapper.querySelectorAll('.sz-rec-track-link');

            links.forEach(function(link) {
                link.addEventListener('click', function() {
                    sendEvent('sz_track_click', promoId, placement);
                });
            });
        });

        function sendEvent(action, promoId, placement) {
            if (!promoId || !placement) return;

            var formData = new FormData();
            formData.append('action', action);
            formData.append('promo_id', promoId);
            formData.append('placement', placement);
            formData.append('nonce', szPromoData.nonce);

            // sendBeacon ensures tracking even on rapid page navigation
            if (navigator.sendBeacon) {
                navigator.sendBeacon(szPromoData.ajax_url, formData);
            } else if (window.fetch) {
                fetch(szPromoData.ajax_url, {
                    method: 'POST',
                    body: formData,
                    keepalive: true
                }).catch(function(){});
            } else if (window.jQuery) {
                jQuery.post(szPromoData.ajax_url, {
                    action: action,
                    promo_id: promoId,
                    placement: placement,
                    nonce: szPromoData.nonce
                });
            }
        }
    });
})();
