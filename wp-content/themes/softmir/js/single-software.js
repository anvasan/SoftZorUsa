/**
 * SoftMir — Single Software Page Scripts
 * Handles description toggle and review form opening
 */
(function () {
    'use strict';

    // Toggle collapsible description
    window.softmirToggleDesc = function () {
        var content = document.getElementById('softDescription');
        var fade = document.getElementById('softDescFade');
        var btn = document.getElementById('softDescToggle');
        if (!content || !btn) return;

        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            if (fade) fade.style.display = '';
            btn.textContent = softmirSingleL10n.showMore;
            content.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            content.classList.add('expanded');
            if (fade) fade.style.display = 'none';
            btn.textContent = softmirSingleL10n.showLess;
        }
    };

    // Open review form
    window.softmirOpenReviewForm = function () {
        var form = document.getElementById('review-form-wrapper');
        if (form) {
            form.classList.remove('hidden');
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    // Auto-hide toggle if content is short
    document.addEventListener('DOMContentLoaded', function () {
        var content = document.getElementById('softDescription');
        var fade = document.getElementById('softDescFade');
        var btn = document.getElementById('softDescToggle');
        if (content && content.scrollHeight <= 340) {
            content.classList.add('expanded');
            if (fade) fade.style.display = 'none';
            if (btn) btn.style.display = 'none';
        }

        // Email Send Form
        var mailForm = document.getElementById('send-sw-info-form');
        if (mailForm) {
            mailForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var email = document.getElementById('sw-info-email').value;
                var nameEl = document.getElementById('sw-info-name');
                var userName = nameEl ? nameEl.value : '';
                var postId = document.getElementById('sw-info-post-id').value;
                var btn = document.getElementById('sw-info-submit');
                var result = document.getElementById('sw-info-result');

                if (!softmirSingleL10n || !softmirSingleL10n.ajaxurl || !softmirSingleL10n.nonce) {
                    result.style.display = 'block';
                    result.innerHTML = '<span style="color:red;">Error: Configuration missing.</span>';
                    return;
                }

                btn.disabled = true;
                var textSpan = btn.querySelector('span');
                var oldText = btn.textContent;
                btn.textContent = 'Sending...';
                result.style.display = 'none';

                var params = new URLSearchParams();
                params.append('action', 'send_sw_info');
                params.append('nonce', softmirSingleL10n.nonce);
                params.append('email', email);
                params.append('name', userName);
                params.append('post_id', postId);

                fetch(softmirSingleL10n.ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        result.style.display = 'block';
                        if (data.success) {
                            result.innerHTML = '<span style="color:green;font-weight:bold;">\u2713 ' + data.data.message + '</span>';
                            mailForm.reset();
                        } else {
                            result.innerHTML = '<span style="color:red;">' + (data.data.message || 'Error') + '</span>';
                        }
                    })
                    .catch(function (err) {
                        result.style.display = 'block';
                        result.innerHTML = '<span style="color:red;">Network error (' + err.message + ')</span>';
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.textContent = oldText;
                    });
            });
        }
    });
})();
