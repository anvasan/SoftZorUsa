/**
 * View Switcher for Software Archive
 * Toggles between grid, list, and table views
 * Uses event delegation and MutationObserver to survive AJAX pagination
 */
(function () {
    const STORAGE_KEY = 'softmir_view_mode';

    function setView(mode) {
        const wrapper = document.getElementById('catalog-cards-wrapper');
        const buttons = document.querySelectorAll('.view-switch-btn');

        if (!wrapper) return;

        // Update buttons
        buttons.forEach(function (b) {
            b.classList.toggle('active', b.getAttribute('data-view') === mode);
        });

        // Update wrapper classes
        wrapper.className = 'catalog-cards-wrapper view-' + mode;

        // Show/hide the correct card set
        var gridCards = wrapper.querySelector('.cards-grid');
        var listCards = wrapper.querySelector('.cards-list');
        var tableCards = wrapper.querySelector('.cards-table');

        if (gridCards) gridCards.style.display = mode === 'grid' ? '' : 'none';
        if (listCards) listCards.style.display = mode === 'list' ? '' : 'none';
        if (tableCards) tableCards.style.display = mode === 'table' ? '' : 'none';

        // Hide the results counter in list view
        var resultsCount = document.querySelector('.catalog-results-count');
        if (resultsCount) resultsCount.style.display = mode === 'list' ? 'none' : '';
    }

    // 1. Initial load
    document.addEventListener('DOMContentLoaded', function () {
        const saved = localStorage.getItem(STORAGE_KEY) || 'list';
        setView(saved);
    });

    // 2. Event Delegation for clicks (survives DOM replacement)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.view-switch-btn');
        if (!btn) return;

        var mode = btn.getAttribute('data-view');
        if (mode) {
            // Prevent default just in case it's an anchor or form button
            e.preventDefault();
            setView(mode);
            localStorage.setItem(STORAGE_KEY, mode);
        }
    });

    // 3. MutationObserver to re-apply the view after AJAX pagination
    const observer = new MutationObserver(function (mutations) {
        let shouldUpdate = false;
        for (let mutation of mutations) {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                for (let node of mutation.addedNodes) {
                    if (node.nodeType === 1) { // Element node
                        if (node.id === 'catalog-cards-wrapper' || node.id === 'catalog-results' || node.querySelector('#catalog-cards-wrapper')) {
                            shouldUpdate = true;
                            break;
                        }
                    }
                }
            }
            if (shouldUpdate) break;
        }

        if (shouldUpdate) {
            const saved = localStorage.getItem(STORAGE_KEY) || 'list';
            setView(saved);
        }
    });

    // Start observing when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        const resultsContainer = document.getElementById('catalog-results') || document.body;
        if (resultsContainer) {
            observer.observe(resultsContainer, { childList: true, subtree: true });
        }
    });

})();
