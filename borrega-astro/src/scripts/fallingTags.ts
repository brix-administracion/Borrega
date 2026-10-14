import Matter from 'matter-js';

// Hace caer con fisica real las etiquetas de la nube de servicios (port del
// concepto FallingText de React Bits, sin React). Diferencia clave con el
// original: la unidad que cae es cada [data-piece] del DOM y no cada palabra
// del texto, porque etiquetas como "Diseño gráfico" no se pueden partir. El
// aspecto de cada ficha (color, borde, tipografia) lo siguen dando las clases
// .tag / .tag-* del componente; aca solo se mueven.
//
// La configuracion llega por data-* en la raiz (el <script> de Astro se
// bundlea aparte y no ve el frontmatter del componente).
const STEP = 1000 / 120; // paso fijo: la simulacion no depende del framerate
const WALL = 400; // espesor de las paredes; grueso para que nada las atraviese
const MAX_DT = 0.05; // tope de dt (s) al volver de una pestaña en segundo plano
const REVEAL_FALLBACK_MS = 800; // la transicion de [data-reveal] dura 0.7s
const RESTORE_DURATION = 760;
const RESTORE_STAGGER = 14;
const RESTORE_STAGGER_MAX = 360;
const PUSH_RADIUS = 90;
const PUSH_MIN_SPEED = 0.8; // px/ms; por debajo es un movimiento normal, no un manotazo
const PUSH_THROTTLE_MS = 60;

type Order = 'sequence' | 'random' | 'center' | 'together';

type Piece = {
  el: HTMLElement;
  body: Matter.Body;
  x0: number;
  y0: number;
  w: number;
  h: number;
  releaseAt: number;
  released: boolean;
  knocked: boolean;
  px: number;
  py: number;
  pa: number;
  lastPush: number;
  lastTransform: string;
};

const num = (value: string | undefined, fallback: number) => {
  const n = parseFloat(value ?? '');
  return Number.isFinite(n) ? n : fallback;
};

const clamp = (v: number, min: number, max: number) => Math.min(max, Math.max(min, v));

// Curva de resorte para linear(): oscilacion amortiguada que pasa un poco de
// largo y asienta. Se genera en vez de pegar una tabla a mano.
const springEasing = () => {
  const n = 32;
  const pts = Array.from({ length: n + 1 }, (_, i) => {
    const t = i / n;
    return i === n ? 1 : 1 - Math.exp(-6 * t) * Math.cos(9 * t);
  });
  return `linear(${pts.map((v) => v.toFixed(3)).join(', ')})`;
};

export function initFallingTags(selector = '[data-falling-tags]') {
  // Con movimiento reducido nunca se arma el motor: la nube queda estatica.
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  document.querySelectorAll<HTMLElement>(selector).forEach((root) => {
    const list = root.querySelector<HTMLElement>('ul');
    const elements = Array.from(root.querySelectorAll<HTMLElement>('[data-piece]'));
    if (!list || elements.length === 0) return;

    const stagger = num(root.dataset.stagger, 0.04);
    const order: Order = (['sequence', 'random', 'center', 'together'] as const).includes(root.dataset.order as Order)
      ? (root.dataset.order as Order)
      : 'sequence';
    const gravity = clamp(num(root.dataset.gravity, 1), -3, 4);
    const bounciness = clamp(num(root.dataset.bounciness, 0.15), 0, 1);
    const scatter = num(root.dataset.scatter, 1);
    const restoreDelayMs = num(root.dataset.restoreDelay, 2.5) * 1000;
    const dragStiffness = num(root.dataset.dragStiffness, 0.2);
    const cursorPush = root.dataset.cursorPush !== 'false';
    const pushStrength = num(root.dataset.pushStrength, 1);

    const { Engine, Bodies, Body, Composite, Constraint, Query, Events, Sleeping } = Matter;

    type State = 'idle' | 'waiting' | 'running' | 'restoring';
    let state: State = 'idle';
    let armed = true; // se rearma recien cuando la seccion sale de vista
    let ratio = 0;

    let engine: Matter.Engine | null = null;
    let walls: Matter.Body[] = [];
    let pieces: Piece[] = [];
    let bodyToPiece = new Map<Matter.Body, Piece>();
    let savedStyles: string[] = [];
    let savedMinHeight = '';
    let resizeObserver: ResizeObserver | null = null;
    let animations: Animation[] = [];

    let raf = 0;
    let last = 0;
    let accumulator = 0;
    let elapsed = 0;
    let settledAt: number | null = null;

    let drag: { constraint: Matter.Constraint; pointerId: number } | null = null;
    let pointerInside = false;
    let pointerLast: { x: number; y: number; t: number } | null = null;
    let pointerSpeed = { x: 0, y: 0 };

    // Coordenadas relativas al padding-box del contenedor, que es el origen
    // de los hijos con position:absolute.
    const toLocal = (clientX: number, clientY: number) => {
      const box = root.getBoundingClientRect();
      return { x: clientX - box.left - root.clientLeft, y: clientY - box.top - root.clientTop };
    };

    const buildWalls = () => {
      if (!engine) return;
      const w = root.clientWidth;
      const h = root.clientHeight;
      Composite.remove(engine.world, walls);
      const opts = { isStatic: true, friction: 0.4 };
      // Fuera de los limites visibles, con el cuerpo hacia afuera.
      walls = [
        Bodies.rectangle(w / 2, h + WALL / 2, w + WALL * 2, WALL, opts), // piso
        Bodies.rectangle(-WALL / 2, h / 2, WALL, h + WALL * 2, opts), // izquierda
        Bodies.rectangle(w + WALL / 2, h / 2, WALL, h + WALL * 2, opts), // derecha
        Bodies.rectangle(w / 2, -WALL / 2, w + WALL * 2, WALL, opts), // techo
      ];
      Composite.add(engine.world, walls);
    };

    const computeReleaseTimes = (list: Piece[]) => {
      if (order === 'together') {
        list.forEach((p) => (p.releaseAt = 0));
        return;
      }
      let ranked = list.map((p, i) => ({ p, i }));
      if (order === 'random') {
        ranked = ranked.sort(() => Math.random() - 0.5);
      } else if (order === 'center') {
        const cx = root.clientWidth / 2;
        const cy = root.clientHeight / 2;
        const dist = (p: Piece) => Math.hypot(p.x0 + p.w / 2 - cx, p.y0 + p.h / 2 - cy);
        ranked = ranked.sort((a, b) => dist(a.p) - dist(b.p));
      }
      ranked.forEach(({ p }, rank) => (p.releaseAt = rank * stagger));
    };

    const release = (p: Piece) => {
      if (p.released) return;
      p.released = true;
      Body.setStatic(p.body, false);
      // Empuje inicial chico para que no caigan en columna perfecta; las que
      // cayeron "por golpe" salen con menos energia.
      const k = (p.knocked ? 0.4 : 1) * scatter;
      Body.setVelocity(p.body, { x: (Math.random() - 0.5) * 2 * k, y: Math.random() * k });
      Body.setAngularVelocity(p.body, (Math.random() - 0.5) * 0.08 * k);
    };

    const draw = (alpha: number) => {
      for (const p of pieces) {
        const x = p.px + (p.body.position.x - p.px) * alpha;
        const y = p.py + (p.body.position.y - p.py) * alpha;
        const a = p.pa + (p.body.angle - p.pa) * alpha;
        p.lastTransform = `translate3d(${(x - p.w / 2).toFixed(2)}px, ${(y - p.h / 2).toFixed(2)}px, 0) rotate(${a.toFixed(4)}rad)`;
        p.el.style.transform = p.lastTransform;
      }
    };

    const frame = (now: number) => {
      raf = requestAnimationFrame(frame);
      if (!engine) return;
      const dt = Math.min((now - last) / 1000, MAX_DT);
      last = now;
      elapsed += dt;

      for (const p of pieces) {
        if (!p.released && (p.knocked || elapsed >= p.releaseAt)) release(p);
      }

      // Paso fijo con acumulador; el sobrante se usa para interpolar el render
      // entre el estado previo y el actual.
      accumulator += dt * 1000;
      while (accumulator >= STEP) {
        for (const p of pieces) {
          p.px = p.body.position.x;
          p.py = p.body.position.y;
          p.pa = p.body.angle;
        }
        Engine.update(engine, STEP);
        accumulator -= STEP;
      }
      draw(accumulator / STEP);

      checkRestore(now);
    };

    const startLoop = () => {
      if (raf || state !== 'running') return;
      last = performance.now();
      raf = requestAnimationFrame(frame);
    };
    const stopLoop = () => {
      cancelAnimationFrame(raf);
      raf = 0;
    };

    // La pila esta "asentada" cuando todas cayeron y ninguna se mueve de forma
    // apreciable. Recien ahi corre la cuenta de restoreDelay.
    const checkRestore = (now: number) => {
      const settled = pieces.every((p) => p.released && (p.body.isSleeping || p.body.speed < 0.05));
      if (!settled || pointerInside || drag) {
        settledAt = null;
        return;
      }
      if (settledAt === null) settledAt = now;
      else if (now - settledAt >= restoreDelayMs) restore();
    };

    const restore = () => {
      if (state !== 'running') return;
      state = 'restoring';
      stopLoop();
      const easing = CSS.supports('transition-timing-function', 'linear(0, 1)')
        ? springEasing()
        : 'cubic-bezier(0.34, 1.4, 0.64, 1)';
      const byHeight = [...pieces].sort((a, b) => a.body.position.y - b.body.position.y);
      animations = byHeight.map((p, i) =>
        p.el.animate(
          [{ transform: p.lastTransform }, { transform: `translate3d(${p.x0}px, ${p.y0}px, 0) rotate(0rad)` }],
          { duration: RESTORE_DURATION, delay: Math.min(i * RESTORE_STAGGER, RESTORE_STAGGER_MAX), easing, fill: 'both' },
        ),
      );
      Promise.all(animations.map((a) => a.finished)).then(
        () => {
          if (state === 'restoring') reset();
        },
        () => {
          // Animacion cancelada (teardown): no hay nada que resetear aca.
        },
      );
    };

    const onCollisionStart = (e: Matter.IEventCollision<Matter.Engine>) => {
      if (!engine) return;
      const sign = Math.sign(engine.gravity.y);
      for (const pair of e.pairs) {
        const a = bodyToPiece.get(pair.bodyA);
        const b = bodyToPiece.get(pair.bodyB);
        if (!a || !b || a.released === b.released) continue;
        const moving = a.released ? a : b;
        const still = a.released ? b : a;
        // Solo cuenta si la quieta esta claramente "debajo" de la que cae.
        if ((still.body.position.y - moving.body.position.y) * sign > still.h * 0.25) still.knocked = true;
      }
    };

    const onPointerDown = (e: PointerEvent) => {
      if (state !== 'running' || drag || !engine) return;
      const point = toLocal(e.clientX, e.clientY);
      const hit = Query.point(
        pieces.filter((p) => p.released).map((p) => p.body),
        point,
      )[0];
      if (!hit) return;
      root.setPointerCapture(e.pointerId);
      Sleeping.set(hit, false);
      const constraint = Constraint.create({
        pointA: point,
        bodyB: hit,
        pointB: { x: point.x - hit.position.x, y: point.y - hit.position.y },
        stiffness: dragStiffness,
        damping: 0.08,
        length: 0,
      });
      Composite.add(engine.world, constraint);
      drag = { constraint, pointerId: e.pointerId };
    };

    const pushNearby = (point: { x: number; y: number }, speed: number, now: number) => {
      for (const p of pieces) {
        if (!p.released || now - p.lastPush < PUSH_THROTTLE_MS) continue;
        const dx = p.body.position.x - point.x;
        const dy = p.body.position.y - point.y;
        const dist = Math.hypot(dx, dy);
        if (dist >= PUSH_RADIUS) continue;
        const falloff = 1 - dist / PUSH_RADIUS;
        const s = Math.min(speed, 4) * falloff * 2 * pushStrength;
        const nx = dist > 0 ? dx / dist : 0;
        const ny = dist > 0 ? dy / dist : -1;
        Sleeping.set(p.body, false);
        Body.setVelocity(p.body, { x: p.body.velocity.x + nx * s, y: p.body.velocity.y + ny * s });
        p.lastPush = now;
      }
    };

    const onPointerMove = (e: PointerEvent) => {
      if (state !== 'running') return;
      const point = toLocal(e.clientX, e.clientY);
      if (drag) {
        drag.constraint.pointA = point;
        return;
      }
      if (!cursorPush) return;
      const now = performance.now();
      if (pointerLast) {
        const dt = Math.max(1, now - pointerLast.t);
        // Se mezcla con la velocidad previa para que un solo evento ruidoso
        // no dispare el empuje.
        pointerSpeed.x = pointerSpeed.x * 0.6 + ((point.x - pointerLast.x) / dt) * 0.4;
        pointerSpeed.y = pointerSpeed.y * 0.6 + ((point.y - pointerLast.y) / dt) * 0.4;
        const speed = Math.hypot(pointerSpeed.x, pointerSpeed.y);
        if (speed > PUSH_MIN_SPEED) pushNearby(point, speed, now);
      }
      pointerLast = { x: point.x, y: point.y, t: now };
    };

    const endDrag = (e: PointerEvent) => {
      if (!drag || e.pointerId !== drag.pointerId) return;
      if (engine) Composite.remove(engine.world, drag.constraint);
      if (root.hasPointerCapture(e.pointerId)) root.releasePointerCapture(e.pointerId);
      drag = null;
    };
    const onEnter = () => {
      pointerInside = true;
    };
    const onLeave = () => {
      pointerInside = false;
      pointerLast = null;
      pointerSpeed = { x: 0, y: 0 };
    };

    const build = () => {
      state = 'running';
      elapsed = 0;
      accumulator = 0;
      settledAt = null;

      // Medir TODO antes de tocar el DOM: al sacar las piezas del flujo el
      // resto se reacomodaria y las medidas dejarian de ser las de origen.
      const originX = root.getBoundingClientRect();
      const left = originX.left + root.clientLeft;
      const top = originX.top + root.clientTop;
      const rects = elements.map((el) => {
        const r = el.getBoundingClientRect();
        return { x: r.left - left, y: r.top - top, w: r.width, h: r.height };
      });

      // Sin esto la lista colapsaria a altura cero y la seccion saltaria.
      savedMinHeight = list.style.minHeight;
      list.style.minHeight = `${list.offsetHeight}px`;
      savedStyles = elements.map((el) => el.getAttribute('style') ?? '');

      engine = Engine.create({ enableSleeping: true });
      engine.gravity.y = gravity * 1.4;
      buildWalls();

      pieces = elements.map((el, i) => {
        const r = rects[i];
        const bw = Math.max(2, r.w - 1);
        const bh = Math.max(2, r.h - 1);
        // Las fichas son pastillas, pero el chamfer NO puede acercarse a la
        // mitad del lado corto: ahi el tramo recto queda en ~0, Vertices.chamfer
        // divide por casi cero y devuelve vertices NaN. Eso envenena masa,
        // inercia y posiciones, y la simulacion entera queda congelada sin
        // tirar ningun error. 40% del lado corto deja margen de sobra y a esta
        // escala se ve igual de redondeado.
        const radius = Math.min(bw, bh) * 0.4;
        const body = Bodies.rectangle(r.x + r.w / 2, r.y + r.h / 2, bw, bh, {
          chamfer: { radius },
          restitution: bounciness,
          friction: 0.4,
          frictionStatic: 0.8,
          frictionAir: 0.012,
          density: 0.0015,
          slop: 0.02,
        });
        // setStatic va DESPUES de crear el cuerpo, nunca como isStatic en las
        // opciones: Body.setStatic(true) guarda la masa real en _original para
        // devolverla en setStatic(false), y desde las opciones ese guardado
        // ocurre sobre un cuerpo a medio inicializar. El round-trip se rompe,
        // la pieza queda dinamica con masa Infinity y la resolucion de
        // colisiones devuelve NaN: la simulacion se congela sin tirar error.
        Body.setStatic(body, true);
        el.style.position = 'absolute';
        el.style.left = '0';
        el.style.top = '0';
        el.style.width = `${r.w}px`;
        el.style.height = `${r.h}px`;
        el.style.margin = '0';
        el.style.boxSizing = 'border-box';
        el.style.whiteSpace = 'nowrap';
        el.style.userSelect = 'none';
        el.style.willChange = 'transform';
        const transform = `translate3d(${r.x}px, ${r.y}px, 0) rotate(0rad)`;
        el.style.transform = transform;
        return {
          el,
          body,
          x0: r.x,
          y0: r.y,
          w: r.w,
          h: r.h,
          releaseAt: 0,
          released: false,
          knocked: false,
          px: body.position.x,
          py: body.position.y,
          pa: 0,
          lastPush: 0,
          lastTransform: transform,
        };
      });
      bodyToPiece = new Map(pieces.map((p) => [p.body, p]));
      computeReleaseTimes(pieces);
      Composite.add(
        engine.world,
        pieces.map((p) => p.body),
      );
      Events.on(engine, 'collisionStart', onCollisionStart);

      resizeObserver = new ResizeObserver(() => {
        if (!engine) return;
        buildWalls();
        for (const p of pieces) Sleeping.set(p.body, false);
      });
      resizeObserver.observe(root);

      root.addEventListener('pointerdown', onPointerDown);
      root.addEventListener('pointermove', onPointerMove);
      root.addEventListener('pointerup', endDrag);
      root.addEventListener('pointercancel', endDrag);
      root.addEventListener('pointerenter', onEnter);
      root.addEventListener('pointerleave', onLeave);

      if (ratio > 0) startLoop();
    };

    // Deja todo como en el markup original y rearma el trigger.
    const reset = () => {
      stopLoop();
      animations.forEach((a) => a.cancel());
      animations = [];
      resizeObserver?.disconnect();
      resizeObserver = null;
      root.removeEventListener('pointerdown', onPointerDown);
      root.removeEventListener('pointermove', onPointerMove);
      root.removeEventListener('pointerup', endDrag);
      root.removeEventListener('pointercancel', endDrag);
      root.removeEventListener('pointerenter', onEnter);
      root.removeEventListener('pointerleave', onLeave);
      if (engine) {
        Events.off(engine, 'collisionStart', onCollisionStart);
        Composite.clear(engine.world, false);
        Engine.clear(engine);
      }
      engine = null;
      walls = [];
      pieces = [];
      bodyToPiece = new Map();
      drag = null;
      pointerLast = null;
      pointerSpeed = { x: 0, y: 0 };
      elements.forEach((el, i) => {
        if (savedStyles[i]) el.setAttribute('style', savedStyles[i]);
        else el.removeAttribute('style');
      });
      list.style.minHeight = savedMinHeight;
      state = 'idle';
      // Si la seccion sigue a la vista no vuelve a caer en loop: espera a que
      // salga y vuelva a entrar.
      armed = ratio < 0.3;
    };

    // [data-reveal] hace fade-in en 0.7s arrancando con un umbral mas bajo que
    // el de la caida. Si cayeran en el medio se verian semitransparentes, asi
    // que esperamos a .is-visible y al fin de la transicion.
    const waitForReveal = (done: () => void) => {
      let finished = false;
      let timer = 0;
      const mo = new MutationObserver(() => {
        if (root.classList.contains('is-visible')) {
          mo.disconnect();
          awaitTransition();
        }
      });
      const finish = () => {
        if (finished) return;
        finished = true;
        clearTimeout(timer);
        mo.disconnect();
        root.removeEventListener('transitionend', onEnd);
        done();
      };
      const onEnd = (e: TransitionEvent) => {
        if (e.target === root && e.propertyName === 'opacity') finish();
      };
      function awaitTransition() {
        // Si ya termino hace rato no hay nada que esperar.
        if (root.getAnimations().length === 0) return finish();
        root.addEventListener('transitionend', onEnd);
        // Por si el evento nunca llega (transicion cancelada, pestaña oculta).
        timer = window.setTimeout(finish, REVEAL_FALLBACK_MS);
      }
      if (root.classList.contains('is-visible')) awaitTransition();
      else mo.observe(root, { attributes: true, attributeFilter: ['class'] });
    };

    const trigger = () => {
      armed = false;
      state = 'waiting';
      waitForReveal(() => {
        if (state !== 'waiting') return;
        // Si el usuario ya se fue mientras esperabamos, no caemos en vacio.
        if (ratio < 0.3) {
          state = 'idle';
          armed = true;
          return;
        }
        build();
      });
    };

    // Un solo observer para dos cosas: disparar la caida y pausar el rAF
    // cuando la seccion esta fuera de pantalla.
    const io = new IntersectionObserver(
      (entries) => {
        const entry = entries[entries.length - 1];
        ratio = entry.isIntersecting ? entry.intersectionRatio : 0;
        if (ratio < 0.3 && state === 'idle') armed = true;
        if (ratio >= 0.3 && state === 'idle' && armed) trigger();
        if (state === 'running') {
          if (ratio > 0) startLoop();
          else stopLoop();
        }
      },
      { threshold: [0, 0.3] },
    );
    io.observe(root);

    // Limpieza si el elemento se desmonta (no aplica en Astro estatico,
    // pero es buena practica dejarlo preparado).
    window.addEventListener('pagehide', () => {
      io.disconnect();
      if (state !== 'idle') {
        state = 'idle';
        reset();
      }
    });
  });
}
