# Plan — Migración Börrega Studio (WordPress → Astro)

Estado actualizado por última vez: 2026-07-27. Este archivo se
actualiza a medida que avanzamos para no perder el orden. Cada ítem tiene
su tarea espejo en el tracker de la sesión de Cowork.

## Arquitectura (definida y en uso)

- Frontend: Astro, output estático (`output: 'static'`). Se puede subir a
  cualquier hosting, incluido el actual.
- Backend: WordPress + Elementor se mantiene tal cual está hoy. Astro lee
  el contenido en build-time desde la REST API pública de WP
  (`/wp-json/wp/v2/...`), sin necesidad de WPGraphQL ni plugins nuevos.
- Cada vez que se publique contenido nuevo en WP hay que volver a correr
  `npm run build` y resubir `dist/` (o automatizarlo con un webhook).

---

## ✅ Completado

- [x] Discovery del sitio en vivo (inventario de páginas, posts, stack
      real: Elementor Pro + Ooohboi Steroids + Happy Addons/anime.js).
- [x] Definición de arquitectura headless (Astro estático + REST API).
- [x] Scaffold del proyecto (`borrega-astro/`).
- [x] **Home** completa: Hero (carousel real de 3 slides + versión mobile
      con art-direction), Servicios, Nosotros, Portfolio, Testimonios —
      con imágenes/logos/stickers reales, no placeholders.
- [x] Plantilla genérica de páginas (`src/pages/[slug].astro`) conectada
      a WP para el resto de páginas de contenido simple.
- [x] Página de **Contacto** a medida (el formulario de Elementor Pro no
      migra a estático — reemplazado por CTAs funcionales: mailto,
      WhatsApp, agendar reunión).
- [x] **Blog**: listado (`/borrega-blog/`) + entrada individual
      (`/YYYY/MM/DD/slug/`, mismas URLs que hoy). Filtra por categoría
      "BlogPost" igual que el sitio real. Los posts que la clienta
      comparte por fuera de la web (sin esa categoría) tienen URL real
      pero no se listan ni se indexan (`noindex`).
- [x] **4 landing pages de servicio** (Börrega Inside, Börrega Audit,
      Börrega Audit RRSS, Börrega Core) con su contenido real: hero,
      checklist, columnas de features, pasos numerados, pricing (RRSS),
      FAQ acordeón, CTA final. Contenido "congelado" en el código (ver
      nota en README).
- [x] QA de performance: imágenes optimizadas a webp automáticamente
      (ej. 13MB → 22KB), responsive en todos los componentes construidos.
- [x] **5 páginas de portfolio** — cada una relevada en vivo (no se asumió
      nada, mismo criterio que las landing de servicio). Cada una tiene
      una plantilla distinta porque el contenido real es distinto:
  - Branding: 7 casos de estudio (Coltix, Jiro Sushi, Mejoral, Siandra,
    Gallium, Madero First, Telecentro) con texto + imágenes reales.
  - Diseño Web: columnas de links a los sitios reales (WordPress /
    Tienda Nube), incluye el sub-listado "Próximamente".
  - Fotografía y Animación 2D y 3D: galería de imágenes reales (15 y 9
    fotos respectivamente), sin texto adicional en el sitio original.
  - Portfolio Redes Sociales: 6 mockups de celular + grilla de posteos
    reales por cliente (Vision Box, Petit Goût, etc).
  Las 62 imágenes originales (61MB) se tomaron del export de WordPress
  del propio Ignacio y se optimizan a webp en el build (igual que el
  resto del sitio) en vez de servirse sin optimizar desde WP.
- [x] **Formularios**: los 4 quedaron resueltos, con tratamiento distinto
      según lo que necesitaba cada uno (relevado en vivo, no se asumió):
  - **Formulario** → resultó ser una URL vieja duplicada de **Sumate**
    (mismo contenido exacto) → ahora redirige a `/sumate`.
  - **Reservar Consulta** → el shortcode `[pilar_scheduler]` estaba roto
    en el sitio real → reemplazado por CTAs directas (WhatsApp + mail),
    mismo criterio que Contacto.
  - **Sumate** → formulario real y funcional con Web3Forms, incluye
    carga de archivo (CV/portfolio).
  - **Form Kickoff** → resultó ser una herramienta interna a medida
    (plugin propio de Börrega, no un formulario de Elementor roto), con
    6 secciones y carga de archivos, para el kick-off de auditorías de
    cliente. Reconstruida completa con Web3Forms, incluye la descarga
    del resumen en `.txt` que tenía el original.
  **Pendiente de tu lado:** estos 2 formularios necesitan que actives
  una Access Key gratis en [web3forms.com](https://web3forms.com/) y la
  pegues en `src/lib/formConfig.ts` — sin eso no envían nada. Instrucciones
  en el mismo archivo.
- [x] **`sitemap.xml`**: generado a mano en `src/pages/sitemap.xml.ts` (no
      usa `@astrojs/sitemap` porque necesitábamos la misma lógica de
      público/privado que ya tiene el blog: los posts que la clienta
      comparte por fuera de la web NO entran al sitemap, igual que no se
      listan ni se indexan). Incluye todas las páginas a medida + las
      páginas de WordPress + los posts públicos del blog. Excluye
      `/form-kickoff/` a propósito (herramienta interna, noindex).
      Agregado también `robots.txt` apuntando al sitemap.

---

## 🔲 Pendiente

### QA final (bloqueado por acceso a internet real desde este entorno)
- [ ] Correr `npm run build` con internet real (o en el hosting/Vercel) y
      repasar cada página visualmente contra el sitio real. Todo lo
      construido hasta ahora se probó con datos simulados porque el
      sandbox de esta sesión no tiene salida a `borregastudio.com`.

### Deploy — método definido, ejecución pendiente
- [x] **Método decidido:** mismo hosting cPanel donde vive WordPress hoy.
      Confirmado revisando el `.htaccess` real: la regla de WordPress ya
      prioriza archivos/carpetas existentes antes de enrutar a `index.php`,
      así que el `dist/` de Astro se copia directo adentro de `public_html/`
      sin tocar nada de WordPress. WordPress sigue funcionando igual para
      `/wp-admin/`, `/wp-json/` y `/wp-content/uploads/`.
- [ ] Desactivar WP Fastest Cache antes de subir (sus reglas de rewrite en
      el `.htaccess` ya no tienen sentido con el sitio estático y podrían
      interferir).
- [ ] Copiar `dist/` a `public_html/` (subida real — falta ejecutar).
- [ ] Definir si el rebuild + resubida se hace a mano cada vez que la
      clienta publica algo, o se automatiza con un webhook.

### Mejoras opcionales (no bloqueantes)
- [x] Reemplazar los gradientes de los heroes de las 4 landing de
      servicio por las fotos/assets reales. Relevado en vivo (no eran
      solo gradientes CSS — Externalización de Servicios y Auditoría
      Comunicación tienen cada una una foto real distinta de la oveja
      mascota de Börrega contra un cielo con nubes; usé la REST API de
      medios de WP para encontrar el archivo original detrás del thumb
      de Elementor). Auditoría RRSS y Börrega Core sí eran un degradé
      (violeta + naranja, no solo azul como tenía yo) con 3 iconitos
      decorativos (varita, cursor, flecha) — corregido el color del
      degradé y sumados los iconos reales.
- [x] Afinar animaciones específicas de Ooohboi Steroids: relevé en vivo
      qué efectos reales tiene el sitio (vía `data-settings` de
      Elementor) en vez de asumir. Encontré uno concreto y bien
      especificado: el sticker "Blob-Kiss" del banner de contacto (footer)
      tiene mouse-track + tilt 3D en desktop (`mouseTrack_speed: 1px`,
      `tilt_speed: 2`) — lo reproduje con JS vanilla en
      `src/scripts/mouseTilt.ts` (respeta `prefers-reduced-motion` y solo
      corre en desktop, igual que el original). También encontré un fade
      de opacidad en el header sticky, pero es de 10px de rango — tan sutil
      que no se nota y nuestro header ya es opaco siempre, así que no vale
      la pena replicarlo. Revisé Servicios Multidisciplinarios (los
      iconitos decorativos que sumamos) y no tienen motion effects propios
      en el sitio real. No relevé el resto de páginas exhaustivamente por
      tiempo — si aparece algo más avísame y lo reviso puntual.
- [ ] Evaluar WPGraphQL si en algún momento se necesitan queries más
      ricas (ACF, relaciones) — hoy la REST API alcanza.
- [ ] Evaluar conectar el contenido de las 4 landing de servicio a
      WordPress (vía ACF) en vez de tenerlo hardcodeado en el código,
      para que la clienta pueda editarlas sin tocar código.

---

## Decisiones que quedan en manos de Ignacio

1. Crear la Access Key de Web3Forms y pegarla en `src/lib/formConfig.ts`
   (necesario para que Sumate y Form Kickoff envíen de verdad).
2. Si "Form Kickoff" — que hoy está armado para un cliente puntual
   (Jugabet) — conviene generalizarlo/renombrarlo si lo van a reusar con
   otros clientes, o dejarlo como está si es un caso puntual.
3. Método y momento del deploy.
4. Si vale la pena conectar las landing de servicio y portfolio a WP
   (mejora opcional) o dejarlas como están (hardcodeadas en el código).
