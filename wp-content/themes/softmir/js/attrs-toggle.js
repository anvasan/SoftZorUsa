/**
 * SoftMir — Attributes Toggle (global)
 * Handles "Show more attributes" toggle on cards (catalog, archive, etc.)
 */
(function () {
    'use strict';

    // Toggle collapsible attribute blocks
    window.softmirToggleAttrs = function (id, btn) {
        var el = document.getElementById(id);
        if (!el) return;
        var isHidden = el.style.display === '' || el.style.display === 'none';
        el.style.display = isHidden ? 'block' : 'none';
        if (isHidden) {
            var hideText = (typeof softmirSingleL10n !== 'undefined' && softmirSingleL10n.hide)
                ? softmirSingleL10n.hide
                : 'Hide';
            btn.textContent = hideText + ' ▴';
            btn.classList.add('open');
        } else {
            btn.textContent = btn.getAttribute('data-label');
            btn.classList.remove('open');
        }
    };

    // Store original labels for toggle buttons
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.attrs-toggle-btn').forEach(function (btn) {
            btn.setAttribute('data-label', btn.textContent);
        });
    });
})();
