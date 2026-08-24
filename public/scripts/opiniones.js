document.addEventListener('DOMContentLoaded', () => {
    const carousel = document.querySelector('[data-carousel]');

    if (!carousel) return;

    const slides = [...carousel.querySelectorAll('[data-slide]')];
    const dots = [...document.querySelectorAll('[data-slide-to]')];
    let activeIndex = 0;

    function showSlide(index) {
        activeIndex = (index + slides.length) % slides.length;

        slides.forEach((slide, slideIndex) => {
            const isActive = slideIndex === activeIndex;
            slide.hidden = !isActive;
            slide.classList.toggle('is-active', isActive);
        });

        dots.forEach((dot, dotIndex) => {
            const isActive = dotIndex === activeIndex;
            dot.classList.toggle('is-active', isActive);
            dot.toggleAttribute('aria-current', isActive);
        });
    }

    carousel.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;

        if (button.hasAttribute('data-next')) showSlide(activeIndex + 1);
        if (button.hasAttribute('data-prev')) showSlide(activeIndex - 1);
    });

    dots.forEach((dot) => {
        dot.addEventListener('click', () => showSlide(Number(dot.dataset.slideTo)));
    });

    carousel.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') showSlide(activeIndex + 1);
        if (event.key === 'ArrowLeft') showSlide(activeIndex - 1);
    });

    showSlide(0);
});
