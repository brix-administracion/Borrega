// Reemplazo liviano (sin jQuery, sin runtime de Elementor) de las
// "Entrance Animations" del sitio original. Cualquier elemento con
// [data-reveal] se anima al entrar en viewport.
const observer = new IntersectionObserver(
  (entries) => {
    for (const entry of entries) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    }
  },
  { threshold: 0.15, rootMargin: '0px 0px -10% 0px' }
);

document.querySelectorAll('[data-reveal]').forEach((el) => observer.observe(el));
