(function () {

    function layoutTransferredOrders() {
        const container = document.querySelector('.mc-admin-transferred-orders');
        if (!container) return;

        const cards = Array.from(container.querySelectorAll('.mc-transferred-order-card'));

        // MOBILE: disable anchored layout
        if (window.innerWidth <= 900) {
            container.style.height = 'auto';
            cards.forEach(card => {
                card.style.position = 'static';
                card.style.transform = 'none';
                card.style.width = '100%';
            });
            return;
        }

        container.style.position = 'relative';

        const gap = 20;
        const containerWidth = container.clientWidth;
        const columnWidth = (containerWidth - gap) / 2;

        let colHeights = [0, 0];

        cards.forEach((card, index) => {
            const col = index % 2;
            const x = col === 0 ? 0 : (columnWidth + gap);
            const y = colHeights[col];

            card.style.position = 'absolute';
            card.style.width = columnWidth + 'px';
            card.style.transform = `translate(${x}px, ${y}px)`;

            const cardHeight = card.offsetHeight;
            colHeights[col] = y + cardHeight + gap;
        });

        container.style.height = Math.max(colHeights[0], colHeights[1]) + 'px';
    }

    function setupCollapse() {
        const container = document.querySelector('.mc-admin-transferred-orders');
        if (!container) return;

        const cards = Array.from(container.querySelectorAll('.mc-transferred-order-card'));

        cards.forEach(card => {
            const header = card.querySelector('.mc-order-collapse-header');
            const content = card.querySelector('.mc-order-collapse-content');
            if (!header || !content) return;

            // initial state
            card.classList.add('mc-order-collapsed');
            content.style.display = 'none';

            header.addEventListener('click', () => {
                const isOpen = content.style.display !== 'none';

                if (isOpen) {
                    // CLOSE JUST THIS CARD
                    card.classList.remove('mc-order-expanded');
                    card.classList.add('mc-order-collapsed');
                    content.style.display = 'none';
                } else {
                    // CLOSE ALL, THEN OPEN THIS ONE
                    cards.forEach(c => {
                        const cContent = c.querySelector('.mc-order-collapse-content');
                        c.classList.remove('mc-order-expanded');
                        c.classList.add('mc-order-collapsed');
                        if (cContent) cContent.style.display = 'none';
                    });

                    card.classList.add('mc-order-expanded');
                    card.classList.remove('mc-order-collapsed');
                    content.style.display = 'block';
                }

                setTimeout(layoutTransferredOrders, 10);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupCollapse();
        layoutTransferredOrders();
    });

    window.addEventListener('resize', () => {
        layoutTransferredOrders();
    });

})();
