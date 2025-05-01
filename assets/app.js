/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.scss in this case)
import './styles/app.scss';

import 'bootstrap';

document.addEventListener('DOMContentLoaded', function() {
    // Image loading animation
    const loadImagesProgressively = () => {
        const images = document.querySelectorAll('.masonry-item img');
        let loadedCount = 0;

        images.forEach(img => {
            const parent = img.closest('.masonry-item');

            // Add loading state
            parent.classList.add('loading');

            // Check if image is already loaded
            if (img.complete) {
                imageLoaded(img);
            } else {
                img.addEventListener('load', () => imageLoaded(img));
            }

            img.addEventListener('error', () => {
                parent.classList.remove('loading');
                parent.classList.add('error');
            });
        });

        function imageLoaded(img) {
            const parent = img.closest('.masonry-item');

            // Set a random delay for staggered animation
            setTimeout(() => {
                parent.classList.remove('loading');
                parent.classList.add('loaded');
            }, Math.random() * 500);

            loadedCount++;

            // Update progress if needed
            if (loadedCount === images.length) {
                console.log('All images loaded');
            }
        }
    };

    // Handle hover effects for touch devices
    const handleTouchInteraction = () => {
        const photoFrames = document.querySelectorAll('.photo-frame');

        photoFrames.forEach(frame => {
            frame.addEventListener('touchstart', function() {
                this.classList.add('touch-active');
            }, { passive: true });

            frame.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.classList.remove('touch-active');
                }, 300);
            }, { passive: true });
        });
    };

    // Mouse parallax effect for gallery header
    const handleParallaxEffect = () => {
        const galleryHeader = document.querySelector('.gallery-header');

        if (galleryHeader) {
            document.addEventListener('mousemove', (e) => {
                const x = e.clientX / window.innerWidth;
                const y = e.clientY / window.innerHeight;

                galleryHeader.style.transform = `translate(${x * 10 - 5}px, ${y * 10 - 5}px)`;
            });
        }
    };

    // Initialize all functions
    loadImagesProgressively();
    handleTouchInteraction();
    handleParallaxEffect();

    // Demo function for the "Load more" button
    const loadMoreBtn = document.querySelector('.pulse-btn');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // Add animation class
            this.classList.add('clicked');

            // Show loading state
            const originalText = this.textContent;
            this.textContent = 'Chargement...';

            // Simulate loading delay
            setTimeout(() => {
                this.textContent = originalText;
                this.classList.remove('clicked');

                // Here you would actually load more images
                // For demo purposes, we'll just show a message
                const gallery = document.querySelector('.masonry-grid');
                if (gallery) {
                    const toast = document.createElement('div');
                    toast.className = 'position-fixed bottom-0 end-0 p-3';
                    toast.style.zIndex = '5';
                    toast.innerHTML = `
                        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="toast-header">
                                <strong class="me-auto">Galerie Photo</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                            <div class="toast-body">
                                Toutes les images ont déjà été chargées ! 📸
                            </div>
                        </div>
                    `;
                    document.body.appendChild(toast);

                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                }
            }, 800);
        });
    }
});