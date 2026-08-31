document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.popular-tab-trigger');
    const contents = document.querySelectorAll('.popular-tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
            e.preventDefault();

            // Deactivate all
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));

            // Activate clicked
            this.classList.add('active');
            const targetId = this.dataset.target;
            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });
});
