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

- [ ] `/servicios-multidisciplinarios/`: bloque "Borrega Core es nuestro servicio de
      comunicacion integral." con su parrafo; cierre "No entregamos piezas sueltas:
      construimos sistemas de comunicacion alineados al negocio."; encabezado "Es
      para vos?"; separar intro en encabezado + parrafo; "Que incluye" en grilla 3x2.
      **No portar** la 7a tarjeta duplicada del original (resto de edicion).
- [ ] `/auditoria-redes-sociales/`: parrafo bajo "Publicar sin estrategia clara";
      frase "No es una opinion: es un analisis profesional con criterio de marca y
      negocio."; encabezados "Valor del servicio" y "Es para vos?"; boton secundario
      "Que incluye" en el hero. El boton de la caja de precio apunta a `#` en el
      original (link sin terminar) -> apuntarlo a Calendly.
- [ ] `/auditoria-comunicacion/`: restaurar "...campanas o redes" y "...o solo de
      las redes sociales" en los dos items a los que Astro les comio texto.

- Ruta: delegada (3 archivos + componentes de servicio)
- Checks: build, ratio de las 3 paginas en 95-105%

### T4 — Layout de galerias y casos

- [ ] `/fotografia/`: masonry 4 col escritorio / 2 tablet / 1 movil, gap 10px,
      lightbox. Hoy: grilla fija de 3 con recorte 4:3 y pestana nueva.
- [ ] `/animacion-2d-y-3d/`: `justified` con fila ideal 200px (150px tablet),
      lightbox.
- [ ] Reconciliar conteo de imagenes: Astro 15 y 9 (locales en
      `src/assets/images/portfolio/`), original 14 y 8 segun el `data-settings` del
      widget de Elementor.
- [ ] `/portfolio-redes-sociales/`: imagen de fondo detras de cada mockup
      (slideshow de una sola imagen, fade, 5000ms); los posteos abren lightbox.
- [ ] `/branding/`: grilla de 2 columnas (50/50) por fila, imagenes **debajo** del
      texto, linea divisoria bajo cada titulo. Los 7 casos y sus 14 imagenes ya
      coinciden.

Lightbox con `<dialog>` nativo, sin libreria. **Medir en el navegador antes de
escribir** — el error de las dos columnas del trabajo salio de no hacerlo.

- Ruta: delegada (4 componentes + 4 paginas)
- Checks: `getComputedStyle` y `getBoundingClientRect()` contra el original

### T5 — Metadatos y links

- [ ] `Header.astro:15`: los sociales apuntan a `instagram.com` y `linkedin.com`
      genericos. Reales: `instagram.com/borregastudio`,
      `linkedin.com/company/borregastudio`.
- [ ] `index.astro:11`: el home no pasa description y cae al default generico.
- [ ] `BaseLayout.astro`: faltan `og:url`, `og:site_name`, `og:locale`,
      `twitter:title/description/image`, y no hay imagen por defecto — todas las
      paginas declaran `summary_large_image` pero solo blog y trabajos tienen
      `og:image`.
- [ ] `web3form.ts:59`: el error le dice "Ver src/lib/formConfig.ts" al visitante.
      Cambiarlo por un mensaje humano con el mail de contacto como salida.

- Ruta: inline (cambios mecanicos ya entendidos)
- Checks: build, inspeccion del `<head>` en dist

### T6 — Paridad de indexacion

- [ ] Quitar `noindex={!isPublic}` de `[year]/[month]/[day]/[slug].astro:68`.
- [ ] Sumar los trabajos al sitemap con `getWorkPosts()` (la misma funcion que usa
      `nuestro-trabajo.astro:8`, asi listado y sitemap no se desincronizan) y
      agregar `/nuestro-trabajo/` a `STATIC_ROUTES`. De 25 a 46 URLs.
- [ ] 301 de las 60 URLs viejas en `public/.htaccess`: 59 archivos de taxonomia
      (`/category/...`, `/author/...`) mas `/reserva/`. Cuatro reglas por patron.
      Mover `/formulario` aca tambien.
- [ ] `robots.txt`: portar la allowlist de crawlers de IA del original.

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

Nada cerrado todavia. Siguiente: T1.

## Siguiente paso

T1 — el scheduler, que es lo que mas se ve y lo que desbloquea los 10 CTAs.
