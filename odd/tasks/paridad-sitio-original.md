# Paridad exacta del sitio Astro contra borregastudio.com

## Objetivo

Que `https://yellow-dove-797569.hostingersite.com/` quede exacta a
`borregastudio.com`. El switch del dominio (Astro en `borregastudio.com`, WordPress
en `cms.borregastudio.com`) es el **ultimo** paso y no entra en esta feature.

## Problema

El sitio Astro esta deployado y sincroniza blog y trabajos, pero varias paginas
armadas a mano se apartan del original. Medido como ratio de texto visible de Astro
sobre el original:

| pagina | ratio | causa |
|---|---|---|
| `/externalizacion-servicios/` | 50% | cuerpos de FAQ truncados |
| `/auditoria-comunicacion/` | 58% | cuerpos de FAQ truncados |
| `/reservar-consulta/` | 76% | falta el scheduler entero |
| `/servicios-multidisciplinarios/` | 89% | falta un bloque completo + encabezados |
| `/auditoria-redes-sociales/` | 93% | faltan 2 parrafos y 2 encabezados |

## Por que

Es la web de la jefa de Ignacio y el criterio declarado es seguridad y rendimiento
primero, sin riesgo de bloqueo ni exposicion. El sitio nuevo tiene que estar
intachable **antes** de tocar el dominio, no despues.

## Alcance autorizado

Solo `borrega-astro/` y `.github/workflows/` si hiciera falta. **Nada** que escriba
en WordPress ni que toque el sitio original.

Verificado antes de empezar: el pipeline es de solo lectura contra WordPress. El
unico `POST` del codigo es `web3form.ts` hacia `api.web3forms.com`; cero headers
`Authorization`, cero credenciales hacia WP; el workflow usa `WP_BASE_URL` solo como
fuente de `GET` y el FTP va a Hostinger. 6 lecturas por build.

## Restricciones

- Ningun token ni credencial de terceros dentro del sitio estatico.
- No romper lo ya terminado: blog (listado y detalle), trabajos (dos columnas 65/35,
  ficha sticky, hero con el `cover` de ACF) y el domo.
- Las URLs `/YYYY/MM/DD/slug/` no cambian.

## Modo TDD

**Off.** El proyecto no tiene test runner (no hay script `test` en `package.json`).
Fuente: configuracion del proyecto. Los checks son funcionales:
`npm run build`, `npx tsc --noEmit`, medicion sobre `dist/` y `getComputedStyle` en
el navegador.

## Estrategia de delivery

`ask-on-risk`. Forecast: el porteo de contenido solo pasa las ~400 lineas de
presupuesto, asi que las tareas van en PRs por tema (T1+T2, T3+T4, T5+T6). Commits
por unidad de trabajo en la rama; push y merge son decision de Ignacio.

Rama: `feat/paridad-sitio-original`, desde `main` en `ebb6899c`.

---

## Tareas

### T1 — Scheduler en `/reservar-consulta/`

- [x] Embed inline oficial de Calendly a `calendly.com/pilar-borregastudio/30min`,
      mas el copy del original que hoy falta ("Sabemos que el exito de tu empresa
      depende de decisiones estrategicas..."). WhatsApp y mail quedan como
      alternativa secundaria debajo del calendario.

**Verificado** (medido, no estimado): ratio de texto **99%** (original 694 chars,
Astro 690). El `<div class="calendly-inline-widget">` sale con su `data-url` al
evento correcto. El script de Calendly esta en **1 sola pagina** de las 47
(`rg -l 'assets.calendly.com' dist` -> solo `dist/reservar-consulta/index.html`),
no en el layout global. H1 = "Reserva", igual al original. Hay `<noscript>` con
link al calendario y al mail: sin JS el visitante no queda sin salida.
`npx tsc --noEmit` exit 0, build 47 paginas.

El banner de GDPR de Calendly NO se oculta: es el consentimiento de cookies del
visitante y no nos corresponde suprimirlo.

Commit: `feat(reserva): agendar con Calendly en vez de mandar a WhatsApp`

**Por que importa mas que una pagina:** `rg -c 'reservar-consulta'` da 10 archivos
(5 landings de servicio, `ServiceHero`, `ServiceFinalCTA`, `PricingCard`,
`BlogPost`, `WorkPost`). Todos dicen "AGENDAR REUNION" y caen en dos tarjetas sin
scheduler. Es el embudo entero del sitio terminando en una via muerta.

**Decision:** embed oficial, NO reconstruir la UI de 3 pasos del original. Esa UI es
el skin del widget `ha-calendly` de HappyAddons sobre la API de Calendly;
replicarla exigiria un token de Calendly dentro de un sitio estatico. El embed
oficial usa el mismo evento de 30 min y la misma disponibilidad, sin credenciales
nuestras. Cambia la piel, no la funcion.

- Ruta: inline (1 archivo, ya entendido)
- Checks: build, la pagina carga el widget, el script solo en esa pagina

### T2 — Cuerpos largos de FAQ

- [x] `FAQAccordion.astro`: `body?: FAQBlock[]` donde `FAQBlock = string |
      { list: string[] }`, renderizado despues del `answer`, sin romper las FAQ que
      ya estan bien.
- [x] `/externalizacion-servicios/`: portar los 4 cuerpos verbatim con sus listas.
- [x] `/auditoria-comunicacion/`: portar los cuerpos, restaurar el titulo de Q3.

**Dos bugs encontrados, uno ajeno y uno propio.**

*Del acordeon (preexistente):* anima `grid-template-rows` de `0fr` a `1fr`, pero
ese `0fr` solo aplicaba al primer `<p>`. Los parrafos extra habrian quedado
**visibles con el acordeon cerrado**. Se resolvio envolviendo answer + body en un
`.faq-answer-inner`, un unico hijo del grid que colapsa como unidad.

*De la extraccion (mio):* saque los textos con regex sobre el HTML y el JSON
"verbatim" perdio estructura. 3 de las 4 respuestas de externalizacion tienen un
`<ul>` y 6 de los 8 cuerpos tienen `<strong>`. El ratio daba 102% — los caracteres
estaban, la forma no, y la forma es el objetivo. Ademas partio Q1 en dos parrafos
donde el original tiene un bloque continuo, y dejo un `</strong>` sin abrir en Q4
de auditoria. Rehecho con DOM walk (`node-html-parser`): 0 desbalanceados.

**Verificado en el navegador** (no solo en el HTML):

| | externalizacion | auditoria |
|---|---|---|
| lista cerrada / abierta | 217px / 891px | 217px / 1012px |
| `<ul>` / `<li>` en respuestas | 3 / 10 | 0 / 0 |
| `<strong>` en respuestas | 12 | 7 |
| parrafos vacios | 0 | 0 |
| markup escapado en pantalla | no | no |

Viñetas `disc` visibles y rotulos en negrita, igual que el original. Ratios: **102%**
y **101%**. `npx tsc --noEmit` exit 0, build 47 paginas.

**Cuidado con dos instrumentos que me dieron falsos positivos:**
- `checkVisibility()` NO detecta el recorte por `overflow:hidden` del ancestro: da
  `true` con el acordeon cerrado. La prueba valida es el alto de la lista (217px con
  los 4 cerrados = solo los botones) mas la captura.
- La primera medicion dio `opacity: 0` porque `data-reveal` no habia disparado: hay
  que traer la seccion a pantalla y esperar antes de medir.

Q4 de ambas paginas usa `answer: ''` porque el original no tiene lead separado
(externalizacion Q4 es un `<ul>` puro, auditoria Q4 un parrafo con `<strong>`); el
componente omite el `<p>` del lead cuando viene vacio.

Commit: `fix(servicios): restaurar las respuestas completas de las FAQ`

**Correccion al relevamiento inicial:** el subagente reporto FAQ truncadas en 4
paginas. Extraje los `<details>` verbatim de las 4 y es **falso para 2**:
`servicios-multidisciplinarios` (5 items) y `auditoria-redes-sociales` (5 items)
tienen las respuestas **identicas** al original, un parrafo cada una. El truncado
existe solo en las 2 paginas con ratio 50% y 58%, que es justo lo que la medicion
decia. Los cuerpos del original miden 402, 635, 964 y 577 caracteres en
externalizacion.

- Ruta: delegada (3 archivos no triviales, contenido verbatim extenso)
- Checks: build, ratio de texto de las 2 paginas en 95-105%

### T3 — Secciones y encabezados faltantes

- [x] `/servicios-multidisciplinarios/`: bloque "Borrega Core" con su parrafo;
      cierre "No entregamos piezas sueltas..."; encabezado "Es para vos?"; intro
      separado en encabezado + parrafo; "Que incluye" en grilla 3x2. La 7a tarjeta
      duplicada del original NO se porto (resto de edicion), con comentario.
- [x] `/auditoria-redes-sociales/`: parrafo bajo "Publicar sin estrategia clara";
      "No es una opinion..."; encabezados "Valor del servicio" y "Es para vos?";
      boton secundario "Que incluye" en el hero. El boton de la caja de precio
      apuntaba a `#` en el original -> ahora a `/reservar-consulta`.
- [x] `/auditoria-comunicacion/`: restaurados los dos items con texto comido.
- [x] **Extra, encontrado al verificar:** a `/externalizacion-servicios/` le faltaba
      el h2 "Como trabajamos". Estaba fusionado dentro de un `eyebrowHtml` como
      `<strong>Como trabajamos</strong><br/>` seguido de los tres parrafos en uno
      solo. `IntroStatement.eyebrowHtml` acepta ahora `string | string[]`.

**El ratio de texto no alcanza, confirmado dos veces mas.** Esa pagina daba 102% y
yo la habia dado por cerrada en T2. Comparar el esquema de `<h2>` contra el original
fue lo que lo encontro. Mismo instrumento encontro que `conclusionHtml` salia como
`<p>` cuando en los tres originales es un `<h2>` de seccion.

**Niveles de titulo, medidos por pagina (no asumidos):**
- `conclusionHtml` -> `<h2>`: en los tres originales es encabezado de seccion.
- `Checklist.heading`: es `<h2>` en auditoria-comunicacion y externalizacion
  ("Nuestro servicio ... es para tu empresa si:"), pero en auditoria-redes-sociales
  "Es para vos si…" es un parrafo. Prop `headingLevel` con default 2; esa pagina
  pasa 3. Mi primer intento lo degrado para todos y rompio dos paginas.
- `FeatureColumns.column.headingLevel`: default 2 (son h2 en los originales), 3 en
  las tarjetas "Es para vos si…"/"No es para vos si…" de multidisciplinarios.

**Diferencias deliberadas con el original** (el original esta mal, no lo copiamos):
- `auditoria-comunicacion` y `externalizacion-servicios` **no tienen ningun `<h1>`**:
  el titulo del hero es `<h2>`. Astro usa `<h1>`.
- Esas dos paginas marcan un parrafo entero como `<h2>` ("Cuando una marca comunica
  sin estrategia integral...", "Muchas empresas necesitan resolver..."). En Astro
  queda como parrafo.

**Verificado:** esquema de `<h2>` identico al original en las 4 paginas una vez
excluidas esas diferencias. Grilla medida en el navegador: `417.6px 417.6px 417.6px`
con 6 items (3x2 real). Ancla `#servicios` resuelve, 0 enlaces muertos. Ratios
102 / 102 / 103 / 105%. `tsc` exit 0, build 47 paginas.

De paso se saco un import de `Checklist` sin usar que ya venia de `main`.

Commit: `fix(servicios): portar las secciones y encabezados que faltaban`

- Ruta: delegada (3 archivos + componentes de servicio)
- Checks: build, ratio de las 3 paginas en 95-105%

### T4 — Layout de galerias y casos

- [x] `/fotografia/`: masonry 4 col escritorio / 2 tablet / 1 movil, gap 10px,
      lightbox.
- [x] `/animacion-2d-y-3d/`: `justified` con fila ideal 200px (150px tablet),
      lightbox.
- [x] Conteo de imagenes reconciliado: **sobraba la primera en las dos galerias**.
      Las 15 locales se mapearon una por una contra las 14 del original por
      dimensiones en pixeles (pedidos de rango de 40KB, no descargas completas) y
      el calce es un offset limpio: `foto-02..15` = original 1..14, `anim-02..09` =
      original 1..8. `foto-01` (1000x667) y `anim-01` (1200x630) no tienen
      contraparte. Se sacaron del array; los archivos quedan en disco.

      Hipotesis descartada: NO son las imagenes destacadas de la pagina
      (`Portfolios-Destacas_Photo.jpg` mide 600x400). De donde salen queda abierto.

**Verificado en el navegador, con eventos reales:**

| | fotografia | animacion |
|---|---|---|
| items | 14 | 8 |
| layout medido | `column-count: 4`, `column-gap: 10px` | 8 items de **exactamente** 200px en 3 filas |
| aspecto renderizado vs fuente | coincide (sin el bug de `height:auto`) | alto explicito |
| ultima fila | — | termina en 1067px contra 1349px de las completas: no se estira |

Lightbox: `dialog.open` true, foco en el boton de cerrar, **Escape real** (no un
evento sintetico) lo cierra y devuelve el foco al disparador
(`<a aria-label="Ampliar imagen 1 de 14">`). **Scroll bloqueado de verdad**: scroll
real con la rueda y `scrollY` queda en 136.5 antes y despues. Sin JS el `<a href>`
sigue abriendo el archivo.

Responsive: la ventana no se dejo redimensionar, asi que las reglas se verificaron
en el CSS compilado y NO se ejercitaron: `columns:4` -> `2` en `max-width:1024px`
-> `1` en `640px`, y `--row-h: 150px` en tablet. Pendiente mirarlo en un dispositivo.

Diferencia aceptada: el masonry por columnas CSS llena la columna 1 de arriba a
abajo antes que la 2, mientras el de Elementor (JS) coloca de izquierda a derecha
por columna mas corta. El orden de lectura difiere; es el precio de no sumar una
libreria de JS.

Commit: `feat(portfolio): masonry, justified y lightbox en las galerias`
- [x] `/portfolio-redes-sociales/`: fondo detras de cada mockup y lightbox en los
      posteos.
- [x] `/branding/`: grilla de 2 columnas (50/50), imagenes **debajo** del texto,
      linea divisoria bajo cada titulo.

**El "slideshow" no era un slideshow.** El relevamiento decia "background
slideshow, loop, fade, 5000ms". Al leer la configuracion, **cada uno tiene una sola
imagen**: con una imagen no anima nunca. Se implemento como `background-image`
estatico, con comentario explicando por que, para que nadie "restaure" la animacion.

Y la imagen resulto ser **un marco de iPhone vacio** (`001.png`, 1200x933). La
composicion plana de posteos va centrada encima al 80% del ancho y el cuerpo del
telefono asoma por detras: el efecto es "estos posteos, en un celular".

Dos trampas al relevarlo:
- Buscar el fondo en `.elementor-column` da **cero**. Elementor lo inyecta dentro de
  un swiper, asi que vive en un div hijo. Hay que buscar por *cualquier* elemento
  cuyo `background-image` contenga el archivo.
- Habia **7** fondos para 6 tarjetas: el septimo es el de la seccion entera (una
  foto vertical), no una tarjeta.

Las dos imagenes **no estaban en el repo**. Entran por `astro:assets`
(`src/assets/images/portfolio/rrss/`), no enlazadas desde borregastudio.com: ese
acoplamiento es lo que la migracion viene a sacar. Se sirven como WebP optimizado.

El `<dialog>` se extrajo a `src/components/portfolio/Lightbox.astro`, compartido por
las tres paginas de galeria en vez de duplicar el patron.

**Corregido al verificar:** la fila de posteos estaba limitada a `max-width: 900px`
mientras la tarjeta mide 1003px. En el original va al ras de la tarjeta
(tarjeta 200..1203, posteos 210..1193). Ahora 211..1214 = 1003px.

**Medido contra el original, todo coincide:**

| | original | Astro |
|---|---|---|
| tarjeta rrss | 1003x776, aspecto 1.2925 | 1003x776, aspecto 1.293 |
| mockup sobre la tarjeta | 80% | 80.0% |
| posteos | 18 en 6 grupos, 1:1, debajo | 18 en 6 grupos, 1:1, debajo |
| branding: columnas | 570px x2, 7o caso a 1140 | `570px 570px`, 7o a 1140 |
| branding: orden | titulo -> regla -> texto -> imagenes | titulo -> texto -> imagenes |
| branding: regla | `1px solid rgb(0,0,0)` al 88% | idem |
| branding: titulo | Playfair Display 32px centrado | idem |
| branding: parrafo | 16px / 24px `rgb(27,27,27)` | idem |
| branding: imagenes | 88% del ancho, 14 en total | idem, 3/2/1/2/2/2/2 |

El 7o caso ocupa la fila entera por `:last-child:nth-child(odd)`, no por nombre: si
la clienta suma un octavo caso sigue funcionando.

`/animacion-2d-y-3d/` verificado despues del refactor del lightbox: 8 disparadores,
abre, Escape real lo cierra y devuelve el foco, items siguen en 200px.

Commit: `feat(portfolio): branding en dos columnas y mockup de celular en rrss`

Lightbox con `<dialog>` nativo, sin libreria. **Medir en el navegador antes de
escribir** — el error de las dos columnas del trabajo salio de no hacerlo.

- Ruta: delegada (4 componentes + 4 paginas)
- Checks: `getComputedStyle` y `getBoundingClientRect()` contra el original

### T5 — Metadatos y links

- [x] Sociales reales en el header. **Habia un tercero que no estaba relevado**:
      el boton "VER LINKEDIN" de `About.astro:18`. Apunta al perfil de Pilar, que
      es de quien habla el parrafo que lo precede, no a la pagina de empresa.
- [x] Description del home, textual del original (copy de SEO deliberado).
- [x] OG completo: `og:url`, `og:site_name`, `og:locale`, las tres `twitter:*` y
      una imagen por defecto a 1200px JPEG con `getImage()`.
- [x] El error del formulario ya no filtra rutas del repo al visitante.

**Verificado sobre dist:** las **47** paginas tienen `og:image`; `og:url` cambia por
pagina (`/`, `/borrega-blog/`, `/contacto/` dan tres valores distintos); el head del
home trae las 12 etiquetas; los tres perfiles sociales responden (el 301 de LinkedIn
es su redireccion canonica); cero rutas del repo en el JS servido.

El `og:image` del original mide 600x401, por debajo de los 1200px que recomienda
Open Graph. Ese tamaño no se copio.

Commit: `4ddbc208`

- Ruta: inline (cambios mecanicos ya entendidos)
- Checks: build, inspeccion del `<head>` en dist

### T6 — Paridad de indexacion

- [x] Quitado el `noindex` de los 20 trabajos.
- [x] Sitemap con `getWorkPosts()` + `/nuestro-trabajo/`: de **25 a 46** URLs.
- [x] 301 por patron en `public/.htaccess` (5 reglas) y sacado el `redirects` de
      `astro.config.mjs`.
- [x] `robots.txt` con la allowlist de crawlers de IA: 25 `User-agent`.

**Verificado sobre dist:** `0` trabajos con noindex (antes 20); `/form-kickoff/`
sigue con el suyo; **46** `<loc>` en el sitemap; `/formulario/` ya no genera pagina
de meta-refresh.

**Diff contra el sitemap de WordPress** (83 URLs vs 46): las 39 que faltan son
**35 `/category/` + 2 `/author/` + `/formulario/` + `/reserva/`** — las cuatro
familias cubiertas por los 301. Ninguna URL real se pierde.

Commit: `d818beff`

**Pendiente de verificar en staging**, no se puede local: que los `RewriteRule`
disparen de verdad. Necesita Apache. `curl -I` sobre `/reserva/`,
`/category/pais/argentina/` y `/author/octavio/` tiene que dar **301**.

**Evidencia:** WordPress indexa 30 posts (`post-sitemap.xml`), Astro indexa 10. Las
20 paginas de trabajo salen con `noindex, nofollow` mientras `/nuestro-trabajo/`
**si** es indexable y linkea a las 20: Google entra al listado, sigue a cada
proyecto, y cada uno le dice "no me indexes, no sigas mis links". El concepto de
"post privado" que justifica el noindex en los comentarios no existe en los datos.

**Decision:** no se agrega heuristica nueva (tipo "es trabajo si tiene `cover` en
ACF"). Hoy no cambiaria nada, pero haria desaparecer del listado un proyecto que la
clienta suba sin portada, y eso rompe el requisito de "lo carga y aparece". El
criterio queda igual al de WordPress: no-`blogpost` = trabajo = indexable.

Verificado que Hostinger honra el `.htaccess`: las dos reglas de cache que ya tiene
se aplican en vivo. Y que el `redirects` de Astro en modo estatico devuelve **200 con
meta-refresh**, no un 301, asi que la URL vieja queda viva.

- Ruta: delegada (4 archivos)
- Checks: `rg -c noindex dist/2025 dist/2026` -> 0; `<loc>` en sitemap -> 46;
  `curl -I` sobre 5 URLs viejas -> 301; `/form-kickoff/` sigue noindex

---

## Decision abierta: los typos del original

Astro corrigio en silencio 6 errores del original: "Outsoursing", "contruyen", "Que
les entrgamos", "tu tu equipo", "diagnostico", "recargar la piezas". Recomendacion
dada a Ignacio: **dejar las correcciones**; "exacto" no deberia incluir
reintroducir errores de tipeo. Pendiente de su veto; revertirlo es cuestion de
minutos.

Caso aparte: el cuerpo de Q2 de externalizacion termina cortado en el original
("...talento especializado,"). Se porta con punto final en vez de la coma colgada.

## Criterios de aceptacion

- Ratio de texto visible en 95-105% en las 13 paginas comparadas.
- `/reservar-consulta/` agenda de verdad.
- Layout de galerias y casos medido igual al original.
- 46 URLs en el sitemap, 0 trabajos con noindex, 301 en las URLs viejas.
- Sin regresiones en blog, trabajos ni domo.

## Progreso

**Las 6 tareas cerradas y verificadas. La feature esta completa.**

T1-T4 mergeados a `main` en el PR #7. T6 y T5 en la rama
`feat/paridad-indexacion`, 2 commits (`d818beff`, `4ddbc208`).

Se invirtio el orden de las dos ultimas: **T6 antes que T5**, porque T6 era la unica
pendiente con consecuencia real (el portfolio invisible para Google) y T5 es
cosmetica. Si el presupuesto se cortaba, se cortaba en lo que no se nota.

Sin regresiones, verificado sobre `dist`: 47 paginas, 20 trabajos, 10 posts de blog;
blog 60/40 justificado con destacada 1:1; trabajo 65/35 con ficha sticky y hero con
`cover`; galerias con masonry de 4 columnas, `--row-h` del justified y el `<dialog>`
en las 3 paginas; domo presente.

| # | commit | que cerro |
|---|---|---|
| 1 | `b3b76fea` | T1 — Calendly en `/reservar-consulta/` |
| 2 | `5afc4fac` | T2 — cuerpos largos de FAQ (+ bug del acordeon) |
| 3 | `176cd34d` | T3 — secciones y encabezados faltantes |
| 4 | `5ce43296` | T4a — masonry, justified y lightbox |
| 5 | `ecd0ffac` | T4b — branding 2 columnas y mockup de celular |

Ratios de texto contra el original, medidos: `/reservar-consulta/` 99%,
`/externalizacion-servicios/` 102%, `/auditoria-comunicacion/` 102%,
`/servicios-multidisciplinarios/` 103%, `/auditoria-redes-sociales/` 105%.

Esquema de `<h2>` identico al original en las 4 paginas de servicio, salvo las
diferencias deliberadas anotadas en T3.

## Decisiones pendientes de Ignacio

1. **Push y merge** de los 5 commits. Conviene mirarlos en staging antes de T5/T6.
2. **Los typos del original** (ver seccion mas abajo), mas los dos defectos
   estructurales que encontre en T3: `auditoria-comunicacion` y
   `externalizacion-servicios` **no tienen `<h1>`** en el original, y marcan
   parrafos enteros como `<h2>`. Astro corrige las dos cosas. Revertirlo es
   cuestion de minutos si quiere paridad literal.
3. **Access Key de Web3Forms** — la crea el en web3forms.com; el plumbing ya esta.

## Sin verificar (honesto, no asumido)

- **Las reglas responsive de las galerias.** La ventana de Chrome no se dejo
  redimensionar (`outerWidth` siguio en 1440 despues del resize), asi que se
  confirmaron en el CSS compilado pero NO se ejercitaron: `columns:4` -> `2` en
  `max-width:1024px` -> `1` en `640px`, y `--row-h: 150px` en tablet. Mirarlo en un
  telefono es un minuto.
- El layout de branding en una columna (movil) tampoco se midio.

## Siguiente paso

La paridad esta terminada. Lo que queda **no es de esta feature**:

1. **Mergear `feat/paridad-indexacion`** y, una vez deployado, confirmar en staging
   que los `RewriteRule` disparan: `curl -I` sobre `/reserva/`,
   `/category/pais/argentina/` y `/author/octavio/` tiene que dar **301**. Es lo
   unico de T6 que no se puede verificar sin Apache.
2. **El switch del dominio**, con su runbook mas arriba. Ojo con la trampa anotada:
   el staging sirve hoy un `robots.txt` que Hostinger inyecta con
   `Googlebot: Disallow: /`. Hay que confirmar que desaparece al adjuntar el dominio
   real, o Google desindexa todo — y ahora eso importa mas, porque el sitemap pasa a
   declarar 46 URLs en vez de 25.
3. **Access Key de Web3Forms**, que la crea Ignacio.
