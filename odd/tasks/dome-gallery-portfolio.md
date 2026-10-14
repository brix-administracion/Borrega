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

## Proximo paso

Listo para revisar. La rama acumula ~810 lineas autoradas, por encima del presupuesto de ~400: si se va a abrir PR, conviene decidir si va entera o en slices (`stacked-to-main` o `feature-branch-chain`). Push, PR y merge quedan a decision del usuario.
