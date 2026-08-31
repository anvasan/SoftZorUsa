/**
 * SoftMir — AI Translation AJAX Handler
 * Handles translate buttons in the post edit screen meta box
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        var config = window.softmirTranslate || {};
        var $box = $('#softmir-translate-box');
        var $progress = $('#softmir-translate-progress');
        var $progressFill = $('#softmir-progress-fill');
        var $progressText = $('#softmir-progress-text');
        var $result = $('#softmir-translate-result');
        var postId = $box.data('post-id');
        var isTranslating = false;

        if (!$box.length || !postId || !config.ajaxUrl || !config.nonce) {
            return;
        }

        // ======================== Single Language ========================
        $box.on('click', '.softmir-translate-single', function () {
            if (isTranslating) return;

            var $btn = $(this);
            var lang = $btn.data('lang');
            var $statusRow = $btn.closest('.softmir-lang-status');

            isTranslating = true;
            $btn.prop('disabled', true).text('⏳');
            $result.hide();

            // Show progress for single translation too
            $progress.show();
            $progressFill.css('width', '30%');
            $progressText.html('⏳ Переводится на <b>' + lang + '</b>... (~1 min)');
            startTimer();

            $.ajax({
                url: config.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'softmir_translate_post',
                    nonce: config.nonce,
                    post_id: postId,
                    target_lang: lang
                },
                timeout: 300000
            })
                .done(function (response) {
                    if (response.success) {
                        var statusHtml = '<span class="softmir-status-ok">✅ ' + new Date().toLocaleString() + '</span>';
                        $statusRow.find('[class^="softmir-status"]').replaceWith(statusHtml);
                        showResult('success', '✅ Translated!');
                    } else {
                        showResult('error', response.data?.message || config.strings.error);
                    }
                })
                .fail(function (xhr, status, error) {
                    showResult('error', 'Error: ' + error + ' (HTTP ' + xhr.status + ')');
                })
                .always(function () {
                    isTranslating = false;
                    stopTimer();
                    $btn.prop('disabled', false).text('↻');
                    $progressFill.css('width', '100%');
                    setTimeout(function () { $progress.fadeOut(); }, 2000);
                });
        });

        // ======================== All Languages ========================
        $('#softmir-translate-all-btn').on('click', function () {
            if (isTranslating) return;
            if (!confirm(config.strings.confirm)) return;

            var $btn = $(this);
            isTranslating = true;
            $btn.prop('disabled', true).html('⏳ Translating...');
            $result.hide();

            var langsToTranslate = [];
            $box.find('.softmir-translate-single').each(function () {
                langsToTranslate.push($(this).data('lang'));
            });

            if (langsToTranslate.length === 0) {
                isTranslating = false;
                $btn.prop('disabled', false).html('🌐 ' + config.strings.confirm);
                return;
            }

            // Show progress
            $progress.show();
            $progressFill.css('width', '5%');
            startTimer();

            var currentIndex = 0;
            var successCount = 0;
            var errorCount = 0;

            function processNext() {
                if (currentIndex >= langsToTranslate.length) {
                    $progressFill.css('width', '100%');
                    var doneMsg = '✅ Done! Translated: ' + successCount;
                    if (errorCount > 0) {
                        doneMsg += ', errors: ' + errorCount;
                    }
                    $progressText.text(doneMsg);
                    showResult(errorCount === 0 ? 'success' : 'error', doneMsg);

                    isTranslating = false;
                    stopTimer();
                    $btn.prop('disabled', false).html('🌐 Translate to all languages');
                    setTimeout(function () { $progress.fadeOut(); }, 3000);
                    return;
                }

                var lang = langsToTranslate[currentIndex];
                var $row = $box.find('.softmir-lang-status[data-lang="' + lang + '"]');
                var langName = $row.find('.softmir-lang-name').text().replace(':', '').trim();

                $progressText.html('⏳ <b>' + langName + '</b> (' + (currentIndex + 1) + '/' + langsToTranslate.length + ')... ~1 min');
                $btn.html('⏳ ' + langName + '...');

                var progressPercent = 5 + (currentIndex / langsToTranslate.length) * 90;
                $progressFill.css('width', progressPercent + '%');

                var $singleBtn = $row.find('.softmir-translate-single');
                $singleBtn.prop('disabled', true).text('⏳');

                $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'softmir_translate_post',
                        nonce: config.nonce,
                        post_id: postId,
                        target_lang: lang
                    },
                    timeout: 300000
                })
                    .done(function (response) {
                        if (response.success) {
                            successCount++;
                            var statusHtml = '<span class="softmir-status-ok">✅ ' + new Date().toLocaleString() + '</span>';
                            $row.find('[class^="softmir-status"]').replaceWith(statusHtml);
                        } else {
                            errorCount++;
                            var errorHtml = '<span class="softmir-status-none">❌ ' + (response.data?.message || 'Error') + '</span>';
                            $row.find('[class^="softmir-status"]').replaceWith(errorHtml);
                        }
                    })
                    .fail(function (xhr, status, error) {
                        errorCount++;
                        var errorHtml = '<span class="softmir-status-none">❌ ' + error + '</span>';
                        $row.find('[class^="softmir-status"]').replaceWith(errorHtml);
                    })
                    .always(function () {
                        $singleBtn.prop('disabled', false).text('↻');
                        currentIndex++;
                        processNext();
                    });
            }

            processNext();
        });

        // ======================== Timer ========================
        var timerInterval = null;
        var timerSeconds = 0;
        var $timerEl = $('<span id="softmir-timer" style="font-weight:bold;color:#2271b1;"></span>');
        $progress.append($timerEl);

        function startTimer() {
            timerSeconds = 0;
            $timerEl.show();
            updateTimerDisplay();
            timerInterval = setInterval(function () {
                timerSeconds++;
                updateTimerDisplay();
            }, 1000);
        }

        function stopTimer() {
            clearInterval(timerInterval);
            timerInterval = null;
            $timerEl.hide();
        }

        function updateTimerDisplay() {
            var m = Math.floor(timerSeconds / 60);
            var s = timerSeconds % 60;
            $timerEl.text('⏱ ' + (m > 0 ? m + ' min ' : '') + s + ' sec');
        }

        // ======================== Helpers ========================
        function showResult(type, message) {
            $result.removeClass('success error').addClass(type).text(message).show();
            if (type === 'success') {
                setTimeout(function () { $result.fadeOut(); }, 5000);
            }
        }
    });

})(jQuery);
