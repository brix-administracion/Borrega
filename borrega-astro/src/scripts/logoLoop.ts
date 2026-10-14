import { gsap } from 'gsap';

// Hace fluir los logos sobre una curva SVG (port del concepto TextLoop de
// React Bits). TextLoop usa <textPath>, que solo admite glifos de texto, asi
// que aca cada logo es un <image> ubicado a mano sobre el path con
// getPointAtLength() y rotado segun la tangente.
//
// El path nunca cambia, asi que se samplea UNA sola vez en una tabla (LUT).
// Por frame solo se indexa el array y se escribe el atributo transform: nada
// de getPointAtLength() en caliente ni lecturas que fuercen reflow.
//
// La configuracion llega por data-* en la raiz (el <script> de Astro se
// bundlea aparte y no ve el frontmatter del componente).
const SAMPLES = 600;

type Sample = { x: number; y: number; angle: number };

const num = (value: string | undefined, fallback: number) => {
  const n = parseFloat(value ?? '');
  return Number.isFinite(n) ? n : fallback;
};

export function initLogoLoop(selector = '[data-logo-loop]') {
  document.querySelectorAll<HTMLElement>(selector).forEach((root) => {
    const pathEl = root.querySelector<SVGPathElement>('[data-logo-loop-path]');
    const items = Array.from(root.querySelectorAll<SVGImageElement>('[data-logo-loop-item]'));
    if (!pathEl || items.length === 0) return;

    const speed = num(root.dataset.speed, 90);
    const direction = root.dataset.direction === 'reverse' ? 'reverse' : 'forward';
    const logoW = num(root.dataset.logoW, 90);
    const logoH = num(root.dataset.logoH, 56);
    const gap = num(root.dataset.gap, 40);
    const pauseOnHover = root.dataset.pauseHover !== 'false';

    const len = pathEl.getTotalLength();
    if (!len) return;

    // Poblar: el markup del servidor trae un <image> por cliente; clonamos el
    // set COMPLETO una cantidad entera de veces hasta cubrir el largo del path.
    // Repetir el set entero (y no rellenar con ceil hasta el proximo hueco) es
    // lo que evita que el ultimo clon sea el primer logo otra vez y quede
    // duplicado contra si mismo. Mismo criterio que usa TextLoop con su
    // Math.round(length / unitWidth).
    const reps = Math.max(1, Math.round(len / (logoW + gap) / items.length));
    const count = items.length * reps;
    const parent = items[0].parentNode as Element;
    const all: SVGImageElement[] = [...items];
    for (let i = items.length; i < count; i++) {
      const clone = items[i % items.length].cloneNode(true) as SVGImageElement;
      parent.appendChild(clone);
      all.push(clone);
    }

    // LUT: posicion y angulo (en grados) por muestra. El angulo sale de la
    // muestra siguiente; en la ultima se usa la anterior para no tener un
    // delta de largo cero.
    const points = Array.from({ length: SAMPLES }, (_, i) => pathEl.getPointAtLength((i / SAMPLES) * len));
    const lut: Sample[] = points.map((p, i) => {
      const last = i === SAMPLES - 1;
      const a = last ? points[i - 1] : p;
      const b = last ? p : points[(i + 1) % SAMPLES];
      return { x: p.x, y: p.y, angle: (Math.atan2(b.y - a.y, b.x - a.x) * 180) / Math.PI };
    });

    const step = len / count;
    const place = (base: number) => {
      for (let i = 0; i < all.length; i++) {
        let off = (base + i * step) % len;
        if (off < 0) off += len;
        const { x, y, angle } = lut[Math.floor((off / len) * SAMPLES) % SAMPLES];
        // El orden importa: mover al punto, rotar sobre ese punto y recien
        // ahi desplazar la imagen para que quede centrada en el punto.
        all[i].setAttribute('transform', `translate(${x} ${y}) rotate(${angle}) translate(${-logoW / 2} ${-logoH / 2})`);
      }
    };

    place(0);

    // Con movimiento reducido (o sin velocidad) los logos quedan quietos
    // distribuidos sobre la curva, sin tween.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || speed <= 0) return;

    const state = { offset: 0 };
    const target = direction === 'reverse' ? -len : len;
    const tween = gsap.to(state, {
      offset: target,
      duration: len / speed,
      ease: 'none',
      repeat: -1,
      onUpdate: () => place(state.offset),
    });

    const onEnter = () => tween.pause();
    const onLeave = () => tween.resume();
    if (pauseOnHover) {
      root.addEventListener('pointerenter', onEnter);
      root.addEventListener('pointerleave', onLeave);
    }

    // Limpieza si el elemento se desmonta (no aplica en Astro estatico,
    // pero es buena practica dejarlo preparado).
    window.addEventListener('pagehide', () => {
      tween.kill();
      root.removeEventListener('pointerenter', onEnter);
      root.removeEventListener('pointerleave', onLeave);
    });
  });
}
