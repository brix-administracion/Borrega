// Reproduce el efecto real de Ooohboi Steroids / Elementor Motion Effects
// que tiene el sticker "Blob-Kiss" en el sitio original (mouse track +
// tilt 3D, solo desktop — en el sitio real trae la clase
// elementor-hidden-mobile). Relevado en vivo via data-settings:
//   motion_fx_mouseTrack_speed: 1px
//   motion_fx_tilt_speed: 2 (unidad "px", pero Elementor la usa como
//   multiplicador de grados de rotacion en el tilt)
//
// En vez de reimplementar la libreria completa de Elementor, armamos un
// efecto equivalente: al mover el mouse, el elemento se desplaza unos
// pocos px hacia el cursor (mouse track) y rota levemente en 3D (tilt),
// con inercia suave via lerp en requestAnimationFrame para que no se
// sienta brusco. Respeta prefers-reduced-motion.
export function initMouseTilt(selector: string) {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (window.matchMedia('(max-width: 899px)').matches) return; // solo desktop, igual que el original

  const el = document.querySelector<HTMLElement>(selector);
  if (!el) return;

  const TRACK_RANGE = 10; // px maximos de desplazamiento (mouseTrack_speed: 1 -> rango sutil)
  const TILT_RANGE = 12; // grados maximos de rotacion (tilt_speed: 2 -> rango sutil pero visible)

  let targetX = 0;
  let targetY = 0;
  let targetRotX = 0;
  let targetRotY = 0;
  let currentX = 0;
  let currentY = 0;
  let currentRotX = 0;
  let currentRotY = 0;
  let raf = 0;

  el.style.transformStyle = 'preserve-3d';
  el.style.willChange = 'transform';

  const onMove = (e: MouseEvent) => {
    const rect = el.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;
    // Normalizado -1..1 respecto al centro del viewport (asi el sticker
    // reacciona al mouse en toda la pantalla, no solo al pasar por encima,
    // igual que el mouse-track de Elementor).
    const nx = (e.clientX - window.innerWidth / 2) / (window.innerWidth / 2);
    const ny = (e.clientY - window.innerHeight / 2) / (window.innerHeight / 2);

    targetX = nx * TRACK_RANGE;
    targetY = ny * TRACK_RANGE;
    targetRotY = nx * TILT_RANGE;
    targetRotX = -ny * TILT_RANGE;
    void cx;
    void cy;
  };

  const tick = () => {
    const ease = 0.08;
    currentX += (targetX - currentX) * ease;
    currentY += (targetY - currentY) * ease;
    currentRotX += (targetRotX - currentRotX) * ease;
    currentRotY += (targetRotY - currentRotY) * ease;

    el.style.transform = `translate3d(${currentX.toFixed(2)}px, ${currentY.toFixed(2)}px, 0) rotateX(${currentRotX.toFixed(2)}deg) rotateY(${currentRotY.toFixed(2)}deg)`;

    raf = requestAnimationFrame(tick);
  };

  window.addEventListener('mousemove', onMove, { passive: true });
  raf = requestAnimationFrame(tick);

  // Limpieza si el elemento se desmonta (no aplica en Astro estatico,
  // pero es buena practica dejarlo preparado).
  window.addEventListener('pagehide', () => {
    window.removeEventListener('mousemove', onMove);
    cancelAnimationFrame(raf);
  });
}
