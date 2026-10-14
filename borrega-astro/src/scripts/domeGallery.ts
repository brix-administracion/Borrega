// Hace girar el domo de proyectos con el mouse / el dedo (port del drag de
// DomeGallery de React Bits, sin React ni @use-gesture: pointer events
// nativos). La grilla de tiles y todo el 3D los resuelve el CSS del
// componente; aca solo se escribe el transform de la esfera.
//
// Dos diferencias deliberadas con el original:
//
// 1. Los tiles son <a> a cada proyecto, asi que hay que SUPRIMIR el click
//    cuando el gesto fue un arrastre. Sin eso, cada vez que girás el domo
//    terminás navegando a un proyecto al azar.
// 2. En touch solo se toma el eje horizontal: el vertical lo necesita el
//    scroll de la pagina (ver touch-action: pan-y en el componente).
//
// La configuracion llega por data-* en la raiz (el <script> de Astro se
// bundlea aparte y no ve el frontmatter del componente).
const MOVE_THRESHOLD_SQ = 16; // (4px)^2: por debajo es un tap, no un arrastre
const CLICK_GUARD_MS = 80; // margen despues de soltar, por si el click llega tarde
const MAX_INERTIA_V = 1.4;
// Techo del radio en funcion del alto del contenedor. El original usa 1.35,
// pensado para un viewport a pantalla completa; en una seccion ancha y baja
// ese tope aprieta antes que `fit` y deja al domo con barras negras a los
// costados sin importar cuanto se suba `fit`.
const HEIGHT_GUARD = 1.6;

const num = (value: string | undefined, fallback: number) => {
  const n = parseFloat(value ?? '');
  return Number.isFinite(n) ? n : fallback;
};

const clamp = (v: number, min: number, max: number) => Math.min(Math.max(v, min), max);

// Lleva un angulo a (-180, 180] para que el giro horizontal pueda dar vueltas
// infinitas sin que el numero crezca sin control.
const wrapAngleSigned = (deg: number) => {
  const a = (((deg + 180) % 360) + 360) % 360;
  return a - 180;
};

export function initDomeGallery(selector = '[data-dome-gallery]') {
  document.querySelectorAll<HTMLElement>(selector).forEach((root) => {
    const main = root.querySelector<HTMLElement>('[data-dome-main]');
    const sphere = root.querySelector<HTMLElement>('[data-dome-sphere]');
    if (!main || !sphere) return;

    const fit = num(root.dataset.fit, 0.5);
    const fitBasis = root.dataset.fitBasis ?? 'auto';
    const minRadius = num(root.dataset.minRadius, 600);
    // parseFloat('Infinity') devuelve Infinity, pero Number.isFinite lo
    // rechaza, asi que el default del componente se resuelve a mano.
    const maxRadius = root.dataset.maxRadius === 'Infinity' ? Infinity : num(root.dataset.maxRadius, Infinity);
    const maxVerticalRotationDeg = num(root.dataset.maxVerticalRotation, 5);
    const dragSensitivity = num(root.dataset.dragSensitivity, 20);
    const dragDampening = num(root.dataset.dragDampening, 2);

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const rotation = { x: 0, y: 0 };
    const startRotation = { x: 0, y: 0 };
    let startPos: { x: number; y: number } | null = null;
    let activePointer: number | null = null;
    let horizontalOnly = false;
    let moved = false;
    let lastDragEndAt = 0;
    let inertiaRAF: number | null = null;
    let lastMove: { x: number; y: number; t: number } | null = null;
    let velocity = { x: 0, y: 0 };

    const applyTransform = () => {
      sphere.style.transform = `translateZ(calc(var(--radius) * -1)) rotateX(${rotation.x}deg) rotateY(${rotation.y}deg)`;
    };

    const stopInertia = () => {
      if (inertiaRAF !== null) {
        cancelAnimationFrame(inertiaRAF);
        inertiaRAF = null;
      }
    };

    // Misma friccion que el original: el damping decide cuanto corre el domo
    // despues de soltar y cuantos frames como maximo.
    const startInertia = (vx: number, vy: number) => {
      if (reduceMotion) return;
      let vX = clamp(vx, -MAX_INERTIA_V, MAX_INERTIA_V) * 80;
      let vY = clamp(vy, -MAX_INERTIA_V, MAX_INERTIA_V) * 80;
      const d = clamp(dragDampening, 0, 1);
      const frictionMul = 0.94 + 0.055 * d;
      const stopThreshold = 0.015 - 0.01 * d;
      const maxFrames = Math.round(90 + 270 * d);
      let frames = 0;

      const step = () => {
        vX *= frictionMul;
        vY *= frictionMul;
        if ((Math.abs(vX) < stopThreshold && Math.abs(vY) < stopThreshold) || ++frames > maxFrames) {
          inertiaRAF = null;
          return;
        }
        rotation.x = clamp(rotation.x - vY / 200, -maxVerticalRotationDeg, maxVerticalRotationDeg);
        rotation.y = wrapAngleSigned(rotation.y + vX / 200);
        applyTransform();
        inertiaRAF = requestAnimationFrame(step);
      };

      stopInertia();
      inertiaRAF = requestAnimationFrame(step);
    };

    const onPointerDown = (event: PointerEvent) => {
      if (activePointer !== null || event.button !== 0) return;
      stopInertia();
      activePointer = event.pointerId;
      horizontalOnly = event.pointerType === 'touch';
      moved = false;
      velocity = { x: 0, y: 0 };
      startRotation.x = rotation.x;
      startRotation.y = rotation.y;
      startPos = { x: event.clientX, y: event.clientY };
      lastMove = { x: event.clientX, y: event.clientY, t: event.timeStamp };
    };

    const onPointerMove = (event: PointerEvent) => {
      if (event.pointerId !== activePointer || !startPos) return;
      const dx = event.clientX - startPos.x;
      const dy = event.clientY - startPos.y;

      if (!moved && dx * dx + dy * dy > MOVE_THRESHOLD_SQ) {
        moved = true;
        main.dataset.dragging = 'true';
        // Recien ahora se captura el puntero: si se capturara en pointerdown,
        // un tap limpio dejaria de llegar al <a> y no navegaria nada.
        if (main.setPointerCapture) main.setPointerCapture(event.pointerId);
      }
      if (!moved) return;

      const nextX = horizontalOnly
        ? startRotation.x
        : clamp(startRotation.x - dy / dragSensitivity, -maxVerticalRotationDeg, maxVerticalRotationDeg);
      const nextY = wrapAngleSigned(startRotation.y + dx / dragSensitivity);

      if (lastMove) {
        const dt = event.timeStamp - lastMove.t;
        if (dt > 0) {
          velocity = {
            x: (event.clientX - lastMove.x) / dt,
            y: horizontalOnly ? 0 : (event.clientY - lastMove.y) / dt,
          };
        }
      }
      lastMove = { x: event.clientX, y: event.clientY, t: event.timeStamp };

      if (rotation.x !== nextX || rotation.y !== nextY) {
        rotation.x = nextX;
        rotation.y = nextY;
        applyTransform();
      }
    };

    const onPointerUp = (event: PointerEvent) => {
      if (event.pointerId !== activePointer) return;
      activePointer = null;
      startPos = null;
      lastMove = null;
      delete main.dataset.dragging;
      if (main.hasPointerCapture?.(event.pointerId)) main.releasePointerCapture(event.pointerId);
      if (!moved) return;
      lastDragEndAt = performance.now();
      if (Math.abs(velocity.x) > 0.005 || Math.abs(velocity.y) > 0.005) {
        startInertia(velocity.x, velocity.y);
      }
    };

    // La pieza que el original no necesitaba: los tiles son links, asi que un
    // arrastre que termina sobre un tile dispara un click que hay que comer.
    const onClick = (event: MouseEvent) => {
      if (!moved && performance.now() - lastDragEndAt >= CLICK_GUARD_MS) return;
      event.preventDefault();
      event.stopPropagation();
    };

    main.addEventListener('pointerdown', onPointerDown);
    main.addEventListener('pointermove', onPointerMove);
    main.addEventListener('pointerup', onPointerUp);
    main.addEventListener('pointercancel', onPointerUp);
    main.addEventListener('click', onClick);
    // Arrastrar una imagen es el gesto nativo del navegador y pelea con el giro.
    main.addEventListener('dragstart', (event) => event.preventDefault());

    // El radio del domo se recalcula con el tamaño real del contenedor: misma
    // formula que el original, incluido el techo por altura para que en
    // pantallas anchas y bajas el domo no se desborde por arriba y abajo.
    const resizeObserver = new ResizeObserver((entries) => {
      const rect = entries[0].contentRect;
      const w = Math.max(1, rect.width);
      const h = Math.max(1, rect.height);
      const minDim = Math.min(w, h);
      const maxDim = Math.max(w, h);
      const aspect = w / h;

      let basis: number;
      switch (fitBasis) {
        case 'min':
          basis = minDim;
          break;
        case 'max':
          basis = maxDim;
          break;
        case 'width':
          basis = w;
          break;
        case 'height':
          basis = h;
          break;
        default:
          basis = aspect >= 1.3 ? w : minDim;
      }

      const radius = clamp(Math.min(basis * fit, h * HEIGHT_GUARD), minRadius, maxRadius);
      root.style.setProperty('--radius', `${Math.round(radius)}px`);
      applyTransform();
    });
    resizeObserver.observe(root);

    // Fuera de pantalla no hay nada que animar.
    const intersectionObserver = new IntersectionObserver(
      ([entry]) => {
        if (!entry.isIntersecting) stopInertia();
      },
      { threshold: 0 },
    );
    intersectionObserver.observe(root);

    // Limpieza si el elemento se desmonta (no aplica en Astro estatico,
    // pero es buena practica dejarlo preparado).
    window.addEventListener('pagehide', () => {
      stopInertia();
      resizeObserver.disconnect();
      intersectionObserver.disconnect();
      main.removeEventListener('pointerdown', onPointerDown);
      main.removeEventListener('pointermove', onPointerMove);
      main.removeEventListener('pointerup', onPointerUp);
      main.removeEventListener('pointercancel', onPointerUp);
      main.removeEventListener('click', onClick);
    });

    applyTransform();
  });
}
