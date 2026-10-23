# Sincronizacion WordPress -> Astro, delimitada a blog y trabajos

- **Plan**: `~/.claude/plans/bien-recordas-lo-que-validated-valiant.md`
- **Branch**: `feat/wp-content-sync` (desde `origin/main` @ ff452d6f)
- **TDD**: off — resuelto del proyecto: `borrega-astro/package.json` no tiene script `test` ni runner instalado. Checks aplicables = `npm run build` + verificacion medida sobre `dist/` + navegador.
- **RDD**: off (switch global). Sin ciclo de review; delivery por politica ordinaria del repo.
- **Estrategia de delivery**: `ask-on-risk` (default). Forecast ~550 lineas autoradas (dos plantillas de detalle + allowlist + optimizacion de imagenes + cron). Supera el presupuesto de ~400, asi que se decide el corte en slices antes del commit que lo cruce.

## Objetivo

WordPress queda SOLO como deposito de contenido, y solo de dos cosas: **blog** y
**trabajos**. Todo el diseño vive en Astro. Concretamente:

1. Si la clienta toca el Home u otra pagina en WP, la web **no cambia**.
2. Blog y trabajos se sincronizan, cada uno en **su propia plantilla**, identicos a
   los que hay hoy en borregastudio.com.
3. Cuando la clienta carga un proyecto nuevo, aparecen **todas** sus fotos.
4. El contenido nuevo llega a la web sin que nadie apriete un boton.

Criterio prioritario del pedido: **seguridad y rendimiento**. Sin riesgo de
exposicion ni de bloqueo, ni nuestro ni de las plataformas involucradas.

## Alcance autorizado

`borrega-astro/src/lib/pageSlugs.ts`, `borrega-astro/src/lib/wp.ts`,
`borrega-astro/src/lib/cleanContent.ts`,
`borrega-astro/src/pages/[slug].astro`,
`borrega-astro/src/pages/[year]/[month]/[day]/[slug].astro`,
`borrega-astro/src/pages/borrega-blog.astro`,
`borrega-astro/src/pages/nuestro-trabajo.astro`,
`borrega-astro/src/pages/sitemap.xml.ts`,
`borrega-astro/src/components/` (componentes nuevos de plantilla),
`.github/workflows/deploy.yml`.

Fuera de alcance: las 10 paginas hardcodeadas (Home, 4 landings de servicio, 5 de
portfolio), el domo, el pipeline de fetch/retry/cache de `wp.ts` (ya resuelto en la
iteracion 3 del domo), y cualquier escritura en WordPress.

## Decisiones de seguridad tomadas antes de escribir codigo

**Descartado: exponer `_elementor_data` en la REST API.** Es meta privada (prefijo
`_`) y contiene la configuracion completa de cada widget del template. En estas
paginas hay un `ha-calendly.default`, y los templates de Elementor guardan ahi
settings de formularios, webhooks y claves de terceros. Publicarla sin
autenticacion es una fuga de datos real. No se hace, ni temporalmente.

**Descartado: un PAT de GitHub guardado en WordPress.** `repository_dispatch` exige
`contents: write`. El riesgo no es el token, es la cadena: comprometen el WP
(ACF Pro + Elementor + plugins, blanco habitual) -> push al repo -> el repo
auto-deploya a produccion. En su lugar va `schedule:` en el workflow que ya existe
mas el `workflow_dispatch` manual que ya esta: cero credenciales nuevas, cero
endpoint publico. Si algun dia hace falta instantaneo, el camino seguro es una
GitHub App con permiso minimo y secreto rotable.

## Criterios de aceptacion

- `/reserva/` NO se genera: ni en `dist/`, ni en el sitemap. Ninguna pagina nueva de
  WP entra al sitio sin que alguien la agregue a la allowlist.
- Los 20 trabajos salen con la plantilla de trabajo (galeria + pais + servicios) y
  los 10 posts de blog con la de blog. Las URLs `/YYYY/MM/DD/slug/` no cambian.
- Las imagenes de blog y trabajos se sirven optimizadas y locales (`/_astro/`), no
  como original de WordPress.
- Los pedidos a borregastudio.com por build no suben respecto del baseline (4 con
  cache caliente).
- `npm run build` pasa sin errores.

## Tareas

- [x] **T1 — Confirmar el origen de la galeria.** La hipotesis se confirmo: el grupo ya existia y la clienta ya lo llenaba, solo tenia apagado el toggle de REST. Ignacio lo activo el 23/10. Sin codigo, sin migracion, sin cambio de flujo para la clienta. Detalle abajo.
- [x] **T2 — Allowlist de paginas.** Ruta: inline (3 archivos, mecanico, sin research). `EXCLUDED_SLUGS` -> `ALLOWED_PAGE_SLUGS` en `pageSlugs.ts`; ajustados `[slug].astro` y `sitemap.xml.ts`, que leen la misma lista. Commit `c7b161d8`.
- [x] **T3 — Dos plantillas de detalle.** Ruta: delegada. `WorkPost.astro`, `BlogPost.astro`, `PostBody.astro`, `RelatedPosts.astro`, `ShareLinks.astro` en `src/components/post/`. Commit `29fc17c4`.
- [x] **T4 — Galeria del trabajo en la plantilla.** Capa de datos en `wp.ts` (commit `7cae3d8d`) + `postImages.ts` y el markup (commit `29fc17c4`).
- [x] **T5 — Optimizar imagenes de blog y trabajos.** Ruta: delegada (4 archivos). `getImage()` + `tileImageOf()` en el detalle y los dos listados, replicando `Portfolio.astro`. `cleanContent` conserva `width`/`height`. Commit `1952db93`.
- [x] **T6 — `schedule:` en el workflow.** Ruta: inline (1 archivo, trivial). Cron de 6 h en `deploy.yml`, `repository_dispatch` queda declarado. Commit `5d17c3f7`.

## Progreso

T2 y T6 cerradas y commiteadas. T5 en curso (delegada). T1 bloqueada por acceso
al admin de WordPress; T4 espera a T1.

### Verificacion observada — T2 (allowlist)

`npm run build`: **47 paginas** (antes 49), 13,6s, sin errores.

Sobre `dist/`:

- `reserva/` **ausente** del HTML y del sitemap. Era el caso real: la clienta la
  creo/modifico el 23/10 14:39 y se generaba sola.
- `politicas-de-privacidad/` presente y en el sitemap (unico slug de la allowlist).
- `/formulario/` sigue redirigiendo a `/sumate/` (`http-equiv="refresh"` +
  canonical). Lo genera el `redirects` de `astro.config.mjs`, no la plantilla.
- 30 posts generados (10 de blog + 20 trabajos): sin regresion.
- 48 `index.html` en total contra 47 "pages built" — la diferencia es el redirect.

### Verificacion observada — T6 (cron)

- El YAML parsea y `gh workflow list` lo sigue dando `active`. Triggers:
  `schedule` (`0 */6 * * *`), `repository_dispatch`, `workflow_dispatch`, `push`.
- **Pendiente real:** GitHub solo corre `schedule:` desde la rama default, asi que
  el cron no empieza a disparar hasta que esto este en `main`.

### Desvio del plan — T6

`cancel-in-progress` paso de `true` a `false`, no estaba en el plan. El deploy es
un FTP archivo por archivo: cancelarlo a mitad de subida deja HTML nuevo apuntando
a assets hasheados que todavia no llegaron, o sea el sitio roto hasta el run
siguiente. La ventana ya existia con push + manual; el cron la abria mas seguido.
Encolar cuesta unos minutos de espera y no tiene esa ventana.

### Baseline medido antes de T5

- `dist/_astro`: **17 MB**.
- Imagenes originales de WordPress servidas sin optimizar: **10** en
  `dist/borrega-blog/index.html`, **20** en `dist/nuestro-trabajo/index.html`,
  **1** en `dist/2025/09/22/recursos-humanos-bfr/index.html`.
- Pedidos a borregastudio.com por build (medidos en la iteracion 3 del domo):
  4 a la API y 0 descargas de imagen con cache caliente.

### Verificacion observada — T5 (optimizacion de imagenes)

`npm run build`: 47 paginas en 14,1s, sin errores.

Peso real que baja un visitante, medido sobre `dist/` contra el `filesize` que
reporta la API de WP para los originales que se servian antes:

| | antes | despues | imagenes |
|---|---|---|---|
| listado de trabajos | **27,0 MB** | **0,41 MB** | 20 |
| listado de blog | **4,9 MB** | **0,35 MB** | 10 |

- Imagenes remotas de borregastudio.com en `dist/borrega-blog/`,
  `dist/nuestro-trabajo/` y el detalle de un trabajo: **0** (antes 10, 20 y 1).
- La mas pesada que se servia sin tocar: `Stopic-Logo-Mockup-2000x1500-1.png`,
  8,4 MB.
- `og:image` del detalle: `/_astro/Bruchou-RH_brand-4-copia-768x543_Z1M1uUe.jpeg`,
  absoluto y en JPEG.
- Pedidos a borregastudio.com con cache caliente: **4 a la API y 0 descargas de
  imagen nuevas**. Igual que el baseline: T5 no agrego pedidos.

### Desvio del plan — T5

`og:image` quedo en JPEG a 1200px y con su propia llamada a `getImage`, no
reusando la imagen webp del articulo como venia del writer. La leen scrapers y no
un navegador: el JPEG es el unico formato que aceptan todos, y LinkedIn — justo
donde se comparte el trabajo de una agencia — viene manejando mal el webp. 1200px
es el ancho que recomienda Open Graph; por debajo de 600 varias plataformas caen a
una miniatura.

### Correccion de un reporte del writer

El writer reporto "+17 pedidos de imagen por build contra un baseline de 0" y lo
atribuyo a T5. Es incorrecto: comparo contra el numero del documento del domo
(medido el 14/10 con cache recien llenada) en vez de medir el build de hoy.
Medido: de las 83 entradas de `.astro-cache`, las **63 tocadas hoy tienen 0
vencidas** y las **20 vencidas son todas de builds anteriores**, o sea imagenes de
las paginas hardcodeadas de portfolio, que ya se pedian antes de T5. **T5 agrego 0
pedidos.**

### El WARN de "request was redirected" es un bug de Astro, no del hosting

Investigado a fondo porque el criterio del pedido era no despertar al anti-bots.
No es el hosting. En `node_modules/astro/dist/assets/build/remote.js`,
`revalidateRemoteImage` hace:

```js
if (res.status >= 300 && res.status < 400) throw new Error(`... The request was redirected.`);
if (!res.ok && res.status !== 304) { ... }
```

**304 cae dentro de `>= 300 && < 400`**, asi que el chequeo de redirect dispara
antes del que perdona el 304 y esa segunda rama es codigo muerto. Toda
revalidacion exitosa se descarta como si fuera un redirect. Evidencia: con
`globalThis.fetch` instrumentado durante el build, la respuesta real es
`304 Not Modified` con `location = null`.

Impacto bajo: una imagen que cambia en WP devuelve 200 y se actualiza bien; solo
la que no cambio devuelve 304, y seguir con la cache vieja es justo lo correcto
para una imagen sin cambios. El costo es un pedido condicional desperdiciado por
entrada vencida por build, y que el `expires` nunca se refresca asi que se repite.
Es upstream; no se arregla desde el proyecto.

**Dos hipotesis propias que resultaron falsas** (anotadas para no repetirlas):

1. *"Es la rafaga concurrente y el anti-bots"*. Se probo un throttle de 3 en
   paralelo mas 5 reintentos con backoff envolviendo `fetch` en
   `astro.config.mjs`: los 17 WARN quedaron **iguales**, los pedidos subieron de
   47 a 85 y el build de 14s a 117s. **Revertido.** Los timestamps lo delataban:
   grupos de 3 separados por 16s, o sea los 17 agotaban los 5 intentos.
2. *"Es el proxy del sandbox"*. No hay variables de proxy en el entorno.

### T1 resuelta — el field group "Single Project"

El grupo es **"Single Project"** (`group_68b9d199bb8af`, ubicacion Entradas, 5
campos). Estaba activo y lleno; lo unico apagado era *Ajustes -> Ajustes de grupo
-> Mostrar en la API REST*. Un switch, nada mas.

Forma exacta de los 5 campos, verificada en vivo. **Todos los de imagen devuelven
IDs de media, no objetos ni URLs:**

| campo | tipo | ejemplo | cobertura |
|---|---|---|---|
| `galeria_proyecto` | `number[]` | `[609,610,...,621]` | **20/20**, de 3 a 12 imagenes |
| `software` | `number[]` | `[1763,1758,1917,1919]` | 19/20, de 0 a 11 (logos SVG) |
| `cover` | `number` | `608` | 20/20 |
| `pais` | `number` | `1716` (`Flag_of_Argentina-copy.jpg`) | 20/20 |
| `link` | `string` | `https://cocoinnova.com/` | 5/20 |

**154 ids de media distintos** en los 20 trabajos.

Validacion cruzada: `galeria_proyecto` de `recursos-humanos-bfr` resuelve
exactamente a las 9 imagenes que muestra la pagina en vivo (`Bruchou-RH_brand-1..4`
mas los `@3x` de pilares). El campo ES la fuente de la galeria que renderiza el
template 1596.

**Auditoria de exposicion** (se activo un acceso publico, habia que mirarlo): los 5
campos son ids de media mas un `link` que, en los 5 casos no vacios, es una URL
publica — un YouTube y 4 sitios de clientes. No se expuso nada interno.

Detalles de implementacion ya verificados:

1. **Resolver los ids en bloque.** `/media?include=<hasta 100 ids>&per_page=100`
   devuelve los 100 en UN pedido, con `media_details.width/height` en el 100% y
   `sizes.medium_large` en el 98%. Los 154 salen en **2 pedidos**: el build pasa de
   4 a 6. De a uno serian 154.
2. **Hay que preservar el orden del array de ACF.** El endpoint de media devuelve
   por id DESCENDENTE (621, 620, 619, 614...) y ACF guarda el orden que armo la
   clienta (609, 610, 611...). Reordenar por el array de ACF o las galerias salen
   al reves.
3. **Los logos de `software` son SVG** (ej. `adobe-dw.svg` 48x48). No pasarlos por
   `getImage()`/sharp: servirlos tal cual.
4. `cover` **NO** es la featured image. En `recursos-humanos-bfr`, `cover` = 608 =
   `conectamos@3x.png`, y la featured es `Bruchou-RH_brand-4-copia.jpg`.
5. `pais` es una bandera JPG en WP. Se sigue usando la SVG local de
   `public/flags/` via `countryOf`: mas chica y sin pedido extra.
6. ACF devuelve `""` (string vacio) para campos vacios, y los posts de blog traen
   `acf: []` en vez de un objeto. El tipado tiene que tolerar las tres formas.

### Verificacion observada — T3 y T4 (plantillas y galerias)

`npx tsc --noEmit`: exit 0. `npm run build`: 47 paginas en 12,5s, sin errores.

- **Pedidos a la API: 6** — los 4 de siempre mas los 2 de `/media`. El objetivo
  se cumplio: resolver los 152 ids de las galerias cuesta 2 pedidos en todo el
  build, no 2 por pagina de detalle (serian 40).
- 30 posts generados, las URLs `/YYYY/MM/DD/slug/` sin cambios.
- `recursos-humanos-bfr`: sus **9 imagenes** de galeria, con
  `Bruchou-RH_brand-1-copia` primera — o sea el orden es el de ACF y no el de la
  respuesta de la API. Tiene SOFTWARE, el CTA de consultoria y "Mas proyectos",
  y NO tiene el CTA de blog. Un solo `<h1>`.
- Un post de blog tiene "Solicitar mas informacion" y "Mas articulos", y NO tiene
  SOFTWARE.
- El typo del original ("consutoria") no se replico.

### Desvios del plan — T3 y T4

1. **Hay un `.mp4` dentro de una galeria** (`Web-gif.mp4`). El primer build fallo
   con `CouldNotTransformImage`: sharp no procesa video. Se sirve con `<video
   autoplay muted loop playsinline>`. Los SVG y GIF tambien pasan de largo, porque
   sharp aplanaria un GIF animado.
2. **Sin lightbox.** El original usa el visor de Elementor. No existe equivalente
   en el proyecto y no se agregaron dependencias: cada imagen enlaza a su webp
   grande, igual que `PhotoGallery.astro`.
3. **`PhotoGallery.astro` no se reuso.** Es una grilla de 2-3 columnas con
   `aspect-ratio` 4/3 y recorte, pensada para `ImageMetadata` local. La galeria del
   trabajo va a una columna y a su aspecto real.
4. Se sumo "Volver a nuestro trabajo" en el pie del trabajo, que no estaba en la
   estructura relevada.

### Acoplamiento que queda con WordPress (medido, no resuelto)

En las paginas de detalle quedan **96 referencias remotas a borregastudio.com**:
**19 logos SVG distintos** de la seccion SOFTWARE mas **1 `.mp4`**. Son los unicos
assets que un visitante sigue bajando de WordPress; todo lo demas es local.

No se resolvio a proposito. Las opciones tienen contra:

- Bajarlos a `public/software/` rompe el "la clienta sube y aparece": un logo nuevo
  en WP quedaria roto hasta que alguien copie el archivo.
- Bajarlos en cada build con un prebuild script lo mantiene auto-reparable, pero
  agrega ~19 pedidos por build al host que ya devolvio 403 desde CI, mas un paso
  de build.
- Inlinear los SVG en el HTML es peor para seguridad: un `<img src=...svg>` no
  ejecuta scripts del SVG, un `<svg>` inline si.

Queda como decision del usuario. Riesgo si no se toca: si algun dia WordPress se
apaga o se migra, los logos de SOFTWARE y ese video desaparecen de las paginas de
trabajo. Las paginas siguen funcionando.

## Estado de delivery

**Las 6 tareas cerradas.** Rama `feat/wp-content-sync`: **1182 lineas autoradas**
en 6 commits, sobre un presupuesto de ~400 por slice.

Se ofrecio cortar en dos PRs (los 4 primeros commits son una unidad coherente que
no depende de las plantillas) y el usuario eligio **una sola rama**: hace el merge
el mismo. Queda sin cortar a pedido suyo.

| commit | tarea |
|---|---|
| `c7b161d8` | T2 allowlist de paginas |
| `5d17c3f7` | T6 cron de 6h |
| `1952db93` | T5 optimizacion de imagenes |
| `7c29b532` | documento ODD |
| `7cae3d8d` | T4 capa de datos ACF |
| `29fc17c4` | T3 y T4 plantillas y galerias |

## Proximo paso

Todo el plan esta implementado y verificado. Lo que queda es del usuario:

1. **Merge a `main`.** Importa mas de lo que parece: GitHub solo corre `schedule:`
   desde la rama default, asi que **el cron no empieza a disparar hasta que esto
   este en `main`**. Hasta entonces el contenido nuevo sigue necesitando el boton
   manual de Actions.
2. **Decidir sobre los 19 SVG y el video remotos** (ver arriba). No bloquea nada.
3. **Pendiente de antes, fuera de esta feature:** la Access Key de Web3Forms en
   `borrega-astro/src/lib/formConfig.ts` sigue con el placeholder, asi que Sumate y
   Form Kickoff no envian nada.
