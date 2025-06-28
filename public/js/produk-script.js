document.addEventListener('DOMContentLoaded', function () {
    const imageViewer = document.getElementById('image-viewer');
    const modalImg = document.getElementById('full-image');
    const closeBtn = document.querySelector('#image-viewer .close');

    const productGrid = document.querySelector('.product-grid');
    productGrid.addEventListener('click', function (e) {
        if (e.target.classList.contains('showcase-img')) {
            imageViewer.style.display = 'flex';
            modalImg.src = e.target.src;
        }
    });

    function closeViewer() {
        imageViewer.style.display = 'none';
    }

    closeBtn.addEventListener('click', closeViewer);

    imageViewer.addEventListener('click', function (e) {
        if (e.target === imageViewer) {
            closeViewer();
        }
    });

    const filterContainer = document.querySelector('.category-filter');
    const productCards = document.querySelectorAll('.product-card');

    filterContainer.addEventListener('click', function (e) {
        if (!e.target.matches('.category-btn')) {
            return;
        }

        filterContainer.querySelector('.active').classList.remove('active');
        e.target.classList.add('active');

        const selectedCategory = e.target.dataset.category;

        productCards.forEach(card => {
            const cardCategory = card.dataset.category;

            card.style.animation = 'none';

            if (selectedCategory === 'all' || cardCategory === selectedCategory) {
                card.classList.remove('hidden');
                card.style.animation = 'scaleIn 0.4s ease-in-out forwards';
            } else {
                card.classList.add('hidden');
            }
        });
    });
});
