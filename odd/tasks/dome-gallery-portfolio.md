# DomeGallery en "Nuestro trabajo" (home)

- **Plan**: `~/.claude/plans/quiero-que-incorporar-el-wild-donut.md`
- **Branch**: `feat/dome-gallery-portfolio` (desde `main` @ 37204eb0)
- **TDD**: off — resuelto del proyecto: `borrega-astro/package.json` no tiene script `test` ni runner instalado. Checks aplicables = `npm run build` + verificacion en navegador.
- **Estrategia de delivery**: `ask-on-risk` (default). Forecast ~700 lineas autoradas (domo 3D + port del drag + 7 SVG). Supera el presupuesto de ~400, asi que se corta en 2 slices.
- **RDD**: off (decidido por el switch global). Sin ciclo de review; delivery por politica ordinaria del repo.

## Objetivo

Reemplazar la grilla plana de 9 proyectos del home por el domo 3D (DomeGallery de React Bits), con tres cambios funcionales sobre el original:

1. Mostrar TODOS los proyectos y autoajustarse cuando la clienta agrega/borra uno en WordPress.
2. Hover en un tile -> titulo + bandera + nombre del pais.
3. Click -> navegar al detalle `/YYYY/MM/DD/slug/` (NO el lightbox del original).

## Alcance autorizado

`borrega-astro/src/lib/wp.ts`, `borrega-astro/src/components/Portfolio.astro`,
`borrega-astro/src/components/DomeGallery.astro` (nuevo),
`borrega-astro/src/scripts/domeGallery.ts` (nuevo),
`borrega-astro/public/flags/*.svg` (nuevos), `borrega-astro/package.json`.

Fuera de alcance: `/nuestro-trabajo`, las 5 paginas estaticas de portfolio, sincronizacion en vivo con WP.

## Criterios de aceptacion

- Los 20 proyectos de WP aparecen en el domo; agregar/borrar en WP se refleja en el build siguiente sin tocar codigo.
- Hover muestra titulo + bandera correcta por pais (Hand Atelier -> BR, no AR).
- Click sin mover navega al detalle; arrastrar y soltar NO navega.
- Scroll vertical de la pagina sigue funcionando sobre el domo en mobile.
- `npm run build` pasa sin errores.

## Tareas

- [x] **T1 — `wp.ts`: pais + imagen de tile.** Ruta: inline (1 archivo). `parent` en `WPCategory`, `COUNTRY_PARENT_SLUG`/`COUNTRY_ISO`, `getCountryCategories()`, `countryOf()`, `tileImageOf()`. Check: `npm run build`.
- [x] **T2 — `public/flags/`: 7 SVG** (ar, br, us, es, fr, au, pr). Ruta: inline (assets). Check: existen y abren como SVG valido.
- [x] **T3 — `DomeGallery.astro`.** Markup 3D server-side (`buildItems` en frontmatter), tiles como `<a>`, caption oculta con titulo + bandera, CSS del domo con `touch-action: pan-y`. Sin lightbox.
- [x] **T4 — `domeGallery.ts`.** Port vanilla del drag/inercia/ResizeObserver + supresion de click post-drag + IntersectionObserver + cleanup en `pagehide`.
- [x] **T5 — `Portfolio.astro`.** `getWorkPosts()` sin limite, wiring del domo, borrar CSS huerfano de la grilla.
- [x] **T6 — `package.json`.** Quitar `@use-gesture/react` (instalada, sin usar, sin React en el proyecto).
- [x] **T7 — Verificacion.** `npm run build` + checks sobre `dist/index.html` + navegador (hover, click, drag-no-navega, mobile scroll, consola limpia).

### Nota de ruteo (trigger de delegacion)

El trigger de writer (2+ archivos no triviales) disparo en T3/T4. Se ejecuta **inline** a proposito: el source del componente original de React Bits vive en el contexto de esta conversacion y no en el repo, y el port del transform 3D + la matematica de `buildItems` depende de tenerlo a la vista. Un writer en frio tendria que recibirlo copiado a un scratch file y re-derivar el diseño. Queda registrado para que la decision sea observable.

## Progreso

Las 7 tareas cerradas. `npm run build`: 49 paginas, sin errores.

### Verificacion observada

Sobre `dist/index.html`:

- 100 tiles, **20 href unicos** (los 20 proyectos de WP entran todos).
- 20 paradas de tabulacion y 80 `aria-hidden="true"`: cada proyecto se tabula una sola vez.
- Banderas: 90 `ar.svg` + 5 `br.svg` + 5 tiles sin pais (la campaña de PlayStation, que tiene "Por pais" pero ninguna hija). Hand Atelier sale con `br.svg`, no con la argentina como el sitio original.
- Script del domo: 3,0 KB inline (Astro no emite archivo aparte para chunks chicos).

En navegador (`npm run preview`, Chrome):

- Hover -> caption con titulo + bandera + pais ("Amos Puig / ARGENTINA").
- Click sin mover -> navega a `/2025/10/29/sommiercenter-campana-dia-del-padre/`.
- Drag de 227px sobre un tile -> la esfera gira a `rotateY(-28.8deg)` y la URL NO cambia.
- Scroll vertical de la pagina sigue funcionando sobre el domo; sin desborde horizontal.
- 0 imagenes rotas (tiles + banderas), consola sin mensajes.
- ResizeObserver: 775px de radio en desktop (7,8 tiles de ancho) y 400px en 390px (3,1 tiles), ida y vuelta.

### Dos desvios del plan, ambos por problemas encontrados al verificar

1. **Las imagenes de tile van por `astro:assets`, no por el size de WordPress.** `tileImageOf` sola no alcanzaba: la featured image de `playstation-arg` es un PNG de 13,3 MB y WP nunca le genero miniaturas (`media_details.sizes` vacio), asi que el fallback servia el original en el home. Hoy no pasa porque ese proyecto es el #16 y la grilla mostraba 9 — o sea, habria sido una regresion nueva. Con `<Image inferSize width={420} format="webp">` los 20 tiles pesan **260 KB en total** y ese PNG quedo en 16 KB. `tileImageOf` se mantiene porque le da a Astro una fuente mas chica de donde partir. Costo: el build pasa de 11s a 16s.
2. **`minRadius` 600 -> 400 y caption siempre visible en `(hover: none)`.** El default del original esta pensado para un demo a pantalla completa: en 390px forzaba el radio hacia arriba y entraban 2 tiles de ancho, perdiendo la curva. Y en touch el hover no dispara nunca, asi que el nombre y la bandera no se verian en telefono. En desktop no cambia nada (el radio calculado ronda 775).

---

## Iteracion 2 — reparto por fila y geometria mas grande

Pedido: que sea un poco mas grande, que ocupe mas ancho, y que "todas las imagenes se puedan ver bien porque hay algunas que solo se repiten abajo".

### Lo que estaba mal (dos causas, no una)

1. **Los 20 proyectos estaban clavados cada uno en UNA sola fila.** Los slots se enumeraban por columna (5 filas cada una) y el reparto era `items[i % 20]`. Como 5 divide a 20, `i % 20` determinaba `i % 5`: el proyecto *k* caia siempre en la fila *k mod 5*. `playstation-arg`, `sparty`, `cocoinnova` y `cinq-sport-bags` siempre en la fila de abajo, recortada. El des-duplicado de vecinos portado del original no lo detectaba: compara slots consecutivos, que estaban en la misma columna y eran proyectos distintos.
2. **El domo ocupaba 70% del ancho y 146% del alto de su contenedor.** El radio estaba topeado por `heightGuard = alto * 1.35`, asi que subir `fit` no servia de nada. Las barras negras eran geometria, no la viñeta.

### Tareas

- [x] **T8 — Reparto por fila.** Enumeracion por fila con rotacion de columna (`COLUMN_SHIFT`), reparto lineal `i % n` intacto para conservar la cobertura. Borrado el des-duplicado de vecinos: con `i % n` sobre slots consecutivos dos vecinos nunca pueden ser el mismo proyecto.
- [x] **T9 — 4 filas simetricas** `[-3,-1,1,3]` / `[-2,0,2,4]` en vez de 5.
- [x] **T10 — Geometria.** `segments` 20 -> 26, `segmentsY` (prop nueva) 30, `fit` 0.5 -> 0.66, `minRadius` 400 -> 560, `HEIGHT_GUARD` 1.35 -> 1.6.
- [x] **T11 — Alto del contenedor.** `clamp(520px, min(85vh, 56vw), 860px)` en desktop, `clamp(420px, 62vw, 560px)` en mobile.

### Verificacion observada

Sobre `dist/index.html` (104 tiles, 20 proyectos):

- **filas por proyecto: 4 de 4, todos** (antes: 1 de 5, todos). Ningun proyecto en una sola fila.
- separacion minima entre copias del mismo proyecto: **3 columnas** (antes 1, practicamente pegadas).
- Simulacion del reparto con 2 a 40 proyectos: 0 ausentes, 0 vecinos iguales, peor caso 2 filas de 4.

En navegador, medido con `getBoundingClientRect` sobre los tiles:

| contenedor | radio | ancho ocupado | alto ocupado | tile |
|---|---|---|---|---|
| 1905x745 | 1191 | 70% -> **89%** | 146% -> **137%** | 270x200 |
| 1366x745 | 902 | **94%** | **103%** | 199x147 |
| 1024x573 | 676 | **94%** | **100%** | 144x106 |
| 390x420 | 560 | 175% -> **205%** | **112%** | 116x85 |

Regresiones: hover con titulo + bandera OK, click navega a `/2025/09/22/recursos-humanos-bfr/`, drag de 239px gira el domo -107 grados y **no navega**, 20 paradas de tabulacion, 0 imagenes rotas, `touch-action: pan-y`, consola sin mensajes.

### Desvios del plan, todos por medicion

1. **`segmentsY` 30 como prop nueva** (no estaba en el plan). `--segments-y` ya existia en el CSS pero el componente lo ataba a `segments`. Separarlo junta las filas: baja el desborde vertical y deja el tile en aspecto 1.35, mucho mas cerca del 1.5 de las fotos de WP que un tile cuadrado, asi que el `object-fit` recorta menos.
2. **`HEIGHT_GUARD` 1.35 -> 1.6** (no estaba en el plan). Sin esto el `fit` nuevo no hacia nada: en una seccion ancha y baja el tope por altura aprieta primero.
3. **`COLUMN_SHIFT` 7 -> 3.** Con 7, las copias de un proyecto caian en columnas k, k+1, k+2, k+3 — pegadas y visibles juntas. La simulacion inicial media "columnas distintas" y no la *separacion*, asi que no lo detecto; se vio en la captura. Con 3 quedan a 3 columnas minimo sin perder las 4 filas.
4. **`minRadius` 480 -> 560** y **alto atado a `min(85vh, 56vw)`**: medido, a 1024px el domo llenaba apenas el 77% de su contenedor y en mobile el tile habia bajado a 97px.

### Limitacion conocida

A 1905px de ancho el domo sigue desbordando 137% en alto. El contenedor tiene aspecto 2.56 y el domo renderizado ~1.5: un objeto de 1.5 no puede llenar una caja de 2.56 en los dos ejes. Ya no esconde ningun proyecto porque cada uno esta en las 4 filas. Si molestara, la palanca es `segmentsY` (mas alto = filas mas juntas = menos desborde, tiles mas apaisados).

---

## Iteracion 3 — build resiliente contra el bloqueo de WordPress

El deploy fallo en GitHub Actions con `connect ECONNREFUSED 3.140.158.15:443` dentro de `fetchAllPages`, generando `/nuestro-trabajo`. Esa IP ES borregastudio.com: el host rechazo la conexion a nivel TCP, no devolvio un 403.

### Lo que encontre midiendo

Instrumentando `globalThis.fetch` con `NODE_OPTIONS=--import` (sin tocar el proyecto), un build hacia **117 pedidos** a borregastudio.com:

- **13 a la API** para 4 endpoints distintos. `/categories` sola seis veces. `wp.ts` no tenia ni cache ni reintentos: cada llamada salia a la red y un solo fallo tumbaba el build entero.
- **104 descargas de imagen para 20 imagenes distintas.** Esto es culpa mia: `<Image inferSize>` resuelve la imagen una vez por cada instancia del componente, y con 104 tiles eran 104 descargas. Peor: **`inferSize` ignora la cache de Astro** — la cache guarda la salida optimizada, no ese pedido. El baseline tenia cache caliente y aun asi bajo las 104.

Antes del domo el build hacia 12 pedidos. Con el domo, 117.

### Tareas

- [x] **T12 — `wp.ts`: memorizar.** `once(key, load)` guarda la promesa de cada coleccion; un build corre en un proceso y el contenido no cambia a mitad de camino. Si falla se saca del mapa para no heredar el rechazo. **13 -> 4 pedidos.**
- [x] **T13 — `wp.ts`: reintentar.** `fetchWithRetry` con esperas 1/3/8/20s ante errores de red y 408/425/429/5xx. El 400 queda afuera a proposito: `fetchAllPages` lo usa como fin de paginacion. El error desenvuelve `error.cause`, que es donde Node esconde el `ECONNREFUSED`.
- [x] **T14 — Una optimizacion por proyecto, no por tile.** `getImage()` en el frontmatter (20 llamadas) y `<img>` plano en los 104 tiles.
- [x] **T15 — Matar `inferSize`.** `tileImageOf` devuelve el `width`/`height` que ya da WordPress (29 de 30 posts lo tienen). Para el que no, un aspecto de referencia: sin `fit`, sharp redimensiona solo por ancho y respeta el aspecto real.
- [x] **T16 — Cache de imagenes que sobreviva al CI.** `cacheDir: './.astro-cache'` fuera de `node_modules` (que `npm ci` borra entero) + `actions/cache@v4` con clave rodante en `deploy.yml`.

### Verificacion observada

| | antes | despues (cache fria) | (cache caliente) |
|---|---|---|---|
| pedidos a la API | 13 | **4** | **4** |
| descargas de imagen | 104 | 20 | **0** |
| **total** | **117** | **24** | **4** |
| tiempo de build | 13,2s | 7,3s | **3,0s** |

- Reintento probado contra un puerto cerrado: 5 intentos, 34s, y el mensaje sale `WordPress no respondio tras 5 intentos (fetch failed: connect ECONNREFUSED 127.0.0.1:49517): <url>` — la misma forma que el fallo del deploy.
- El PNG de 8334x6250 de playstation-arg sale 420x315, aspecto 1.333 = el del original. Sin deformacion.
- Build normal: 49 paginas, 104 tiles, 20 proyectos, 260 KB de tiles, 0 imagenes rotas, todas locales (`/_astro/`).
- Regresiones OK: hover con titulo + bandera, click navega a `/2025/09/22/recursos-humanos-bfr/`, drag gira -85 grados y no navega, 20 paradas de tabulacion, consola limpia, ancho 89% / alto 137% sin cambios.

### Nota

Si tras los reintentos WordPress sigue sin responder, el build falla a proposito: es preferible a deployar un sitio sin proyectos. Como el deploy es un FTP de `dist/`, un build fallido no toca nada y el sitio queda en la ultima version buena.

## Proximo paso

Listo para revisar. La rama acumula ~860 lineas autoradas, por encima del presupuesto de ~400: si se va a abrir PR, conviene decidir si va entera o en slices (`stacked-to-main` o `feature-branch-chain`). Push, PR y merge quedan a decision del usuario.
