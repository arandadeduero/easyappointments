(function () {
    'use strict';

    const SLIDE_TIME = 5000;
    const TRANSITION_TIME = 900;

    const slides = [
        'https://www.arandadeduero.es/wp-content/uploads/2025/12/Diseno-sin-titulo-1.gif',
        'https://www.arandadeduero.es/wp-content/uploads/2025/12/GIF-Aranda-es.gif',
        'https://www.arandadeduero.es/wp-content/uploads/2025/12/GIF-historia.gif',
        'https://www.arandadeduero.es/wp-content/uploads/2025/12/GIF-gente.gif',
        'https://www.arandadeduero.es/wp-content/uploads/2025/12/GIF-vino.gif',
        'https://www.arandadeduero.es/wp-content/uploads/2025/12/GIF-vida.gif'
    ];

    const imageA = document.getElementById('queue-brand-image-a');
    const imageB = document.getElementById('queue-brand-image-b');

    if (!imageA || !imageB || slides.length < 2) {
        return;
    }

    let currentIndex = 0;
    let activeImage = imageA;
    let inactiveImage = imageB;
    let changing = false;

    function preloadSlides() {
        slides.forEach(function (src) {
            const image = new Image();
            image.src = src;

            if (typeof image.decode === 'function') {
                image.decode().catch(function () {
                    // La precarga tradicional seguirá funcionando.
                });
            }
        });
    }

    function waitForImage(image) {
        return new Promise(function (resolve) {
            if (image.complete && image.naturalWidth > 0) {
                resolve();
                return;
            }

            image.addEventListener('load', resolve, { once: true });
            image.addEventListener('error', resolve, { once: true });
        });
    }

    async function showNextSlide() {
        if (changing) {
            return;
        }

        changing = true;
        currentIndex = (currentIndex + 1) % slides.length;

        inactiveImage.src = slides[currentIndex];

        await waitForImage(inactiveImage);

        /*
         * Dos frames garantizan que el navegador registre primero
         * el estado oculto y después ejecute la transición.
         */
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                inactiveImage.classList.add('is-active');
                activeImage.classList.remove('is-active');

                const previousActive = activeImage;
                activeImage = inactiveImage;
                inactiveImage = previousActive;

                window.setTimeout(function () {
                    changing = false;
                }, TRANSITION_TIME);
            });
        });
    }

    preloadSlides();

    window.setInterval(showNextSlide, SLIDE_TIME);
})();