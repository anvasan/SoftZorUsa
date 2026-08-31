document.addEventListener('DOMContentLoaded', function() {
    
    // Setup Accordions
    const accordionHeaders = document.querySelectorAll('.accordion-header');
    accordionHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const item = this.closest('.accordion-item');
            item.classList.toggle('active');
            
            // Optional: Close other open accordions in the same card
            /*
            const container = item.closest('.card-accordion-section');
            const allItems = container.querySelectorAll('.accordion-item');
            allItems.forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.classList.remove('active');
                }
            });
            */
        });
    });

    // Setup "Show More" functionality for verdict
    const verdictButtons = document.querySelectorAll('.verdict-show-more');
    verdictButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const content = this.previousElementSibling;
            if (content) {
                // If it's clamped, the CSS removes the clamp if we add a class or modify style
                if (content.style.webkitLineClamp !== 'initial' && content.style.webkitLineClamp !== 'unset' && content.style.webkitLineClamp !== 'none') {
                    // It is clamped, so expand it
                    content.dataset.originalClamp = window.getComputedStyle(content).webkitLineClamp;
                    content.style.webkitLineClamp = 'unset';
                    content.style.display = 'block';
                    this.textContent = 'Hide';
                } else {
                    // It's expanded, so restore it
                    content.style.webkitLineClamp = content.dataset.originalClamp || '2';
                    content.style.display = '-webkit-box';
                    this.textContent = 'Show more...';
                }
            }
        });
    });

});
