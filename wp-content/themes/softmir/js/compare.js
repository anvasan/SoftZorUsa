/**
 * SoftMir - Comparison Feature JS
 * Stores up to 4 software IDs in localStorage and updates the UI
 */
(function($) {
    'use strict';

    const MAX_COMPARE_ITEMS = 4;
    const STORAGE_KEY = 'softmir_compare_ids';

    let compareIds = [];

    // Initialize list from localStorage
    function initCompare() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            try {
                compareIds = JSON.parse(stored);
                if (!Array.isArray(compareIds)) {
                    compareIds = [];
                }
            } catch (e) {
                compareIds = [];
            }
        }
        
        updateCompareUI();
    }

    // Save to localStorage
    function saveCompare() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(compareIds));
        updateCompareUI();
    }

    // Toggle item in comparison list
    function toggleCompareItem(id, btnElement) {
        id = parseInt(id, 10);
        if (isNaN(id) || id <= 0) return;

        const index = compareIds.indexOf(id);

        if (index > -1) {
            // Remove
            compareIds.splice(index, 1);
            showToast('Removed from comparison', 'info');
        } else {
            // Add
            if (compareIds.length >= MAX_COMPARE_ITEMS) {
                showToast(`Maximum ${MAX_COMPARE_ITEMS} программы для сравнения`, 'error');
                return;
            }
            compareIds.push(id);
            showToast('Added to comparison', 'success');
        }

        saveCompare();
    }

    // Render floating bar and update button states
    function updateCompareUI() {
        const count = compareIds.length;
        
        // 1. Update all compare buttons on page
        $('.btn-compare-toggle').each(function() {
            const btnId = parseInt($(this).data('id'), 10);
            if (compareIds.includes(btnId)) {
                $(this).addClass('added');
                $(this).text($(this).data('added-text'));
            } else {
                $(this).removeClass('added');
                $(this).text($(this).data('default-text'));
            }
        });

        // 2. Update floating bar visibility and counts
        const floatingBar = $('#compare-floating-bar');
        const countBadge = floatingBar.find('.compare-count');
        const compareLink = floatingBar.find('.compare-link');

        if (count > 0) {
            floatingBar.addClass('visible');
            countBadge.text(count);
            
            // Generate link with IDs
            let baseUrl = floatingBar.data('compare-url');
            if (baseUrl) {
                baseUrl += '?ids=' + compareIds.join(',');
                compareLink.attr('href', baseUrl);
            }
        } else {
            floatingBar.removeClass('visible');
        }
        
        // 3. Optional: Trigger AJAX to load names/logos into floating bar if needed 
        // For simplicity now, we just rely on the count, but let's fetch names to show them in the bar
        if (count > 0 && typeof softmirCompare !== 'undefined') {
             $.ajax({
                 url: softmirCompare.ajaxurl,
                 type: 'POST',
                 data: {
                     action: 'softmir_get_compare_titles',
                     nonce: softmirCompare.nonce,
                     ids: compareIds
                 },
                 success: function(response) {
                     if (response.success) {
                         $('#compare-items-list').html(response.data.html);
                     }
                 }
             });
        }
    }

    // Helper: Show brief toast notification
    function showToast(message, type = 'info') {
        let toast = $('<div class="softmir-toast softmir-toast-' + type + '">' + message + '</div>');
        $('body').append(toast);

        // Slide in
        setTimeout(() => toast.addClass('show'), 100);

        // Remove after 3s
        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Setup event listeners
    $(document).ready(function() {
        initCompare();

        // Delegate click for dynamically loaded cards (AJAX filters)
        $(document).on('click', '.btn-compare-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleCompareItem($(this).data('id'), this);
        });

        // Remove item from floating bar
        $(document).on('click', '.compare-item-remove', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            toggleCompareItem(id, null);
        });
        
        // Clear all button
        $(document).on('click', '.compare-clear-all', function(e) {
            e.preventDefault();
            compareIds = [];
            saveCompare();
        });
    });

})(jQuery);
