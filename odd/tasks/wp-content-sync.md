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

- [ ] **T1 — Confirmar el origen de la galeria.** *Bloqueada: necesita admin de WP (Ignacio).* Ver **Custom Fields -> Field Groups** y activar *Show in REST API* al grupo que alimenta los widgets de galeria del template 1596. Check: `curl .../posts?slug=recursos-humanos-bfr | jq '.[0].acf'` deja de ser `[]`. Fallback si no existe el grupo: crearlo y migrar los 20 trabajos una vez con un script server-side dentro de WP (sin exponer nada).
- [x] **T2 — Allowlist de paginas.** Ruta: inline (3 archivos, mecanico, sin research). `EXCLUDED_SLUGS` -> `ALLOWED_PAGE_SLUGS` en `pageSlugs.ts`; ajustados `[slug].astro` y `sitemap.xml.ts`, que leen la misma lista. Commit `c7b161d8`.
- [ ] **T3 — Dos plantillas de detalle.** Ruta: delegada (relevamiento en vivo + escritura juntos; dispara el trigger de preparacion y el de writer). Relevar los templates 1596 (trabajo) y 2399 (blog) en vivo y reproducirlos. Check: `npm run build` + comparacion lado a lado en navegador.
- [ ] **T4 — Galeria del trabajo en la plantilla.** *Depende de T1.* Consumir el campo de ACF, resolver cada imagen con `getImage()`, maquetar las dos galerias.
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

## Estado de delivery

Rama: **240 lineas autoradas** en 3 commits, por debajo del presupuesto de ~400.
T3 sumaria unas 350 y lo cruzaria, asi que antes de empezarla hay que elegir el
corte (`stacked-to-main` o `feature-branch-chain`) segun la estrategia
`ask-on-risk`.

## Proximo paso

T2, T5 y T6 cerradas, verificadas y commiteadas; son independientes entre si y se
pueden entregar ya. T3 y T4 quedan pendientes y las dos necesitan a Ignacio
primero: las dos secciones mas visibles de la plantilla de trabajo son galerias
(las imagenes del proyecto y los logos de SOFTWARE), y las dos estan bloqueadas
por T1. Construir esa plantilla antes de confirmar T1 implica hacerla dos veces.
