# Börrega Studio — Frontend Astro (WordPress headless)

## Estado actual
- **Home** completa: Hero, Servicios, Nosotros, Portfolio ("Nuestro trabajo")
  y Testimonios, reconstruida con los tokens de diseño reales del sitio
  (colores, tipografías) y con las imágenes/logos/stickers reales
  descargados de la biblioteca de medios de WordPress.
- **Resto de páginas del sitio** (Contacto, Sumate, Políticas de
  Privacidad, etc.): se generan automáticamente en
  `src/pages/[slug].astro`, que en build-time trae cada página real desde
  la REST API de WordPress y limpia el HTML de Elementor (ver
  "Cómo se migró el contenido" abajo) para maquetarlo con nuestros
  estilos. **Importante:** estas páginas usan una plantilla genérica
  (título + contenido, bien tipografiado y responsive) — no son un calco
  pixel-a-pixel de cada diseño custom de Elementor. Si alguna de estas
  páginas tiene un layout muy particular que quieras replicar 1:1, decime
  cuáles priorizar y las paso a componentes a medida como se hizo con la
  Home.
- Quedó afuera intencionalmente `assist-testing-borrega` (parece una
  página de pruebas interna, no contenido público) y `home-2` (versión
  antigua de la home, ya reemplazada).
- Los formularios (Contacto, Formulario, Reservar Consulta, Form Kickoff)
  hoy en WordPress usan el widget de Formularios de Elementor Pro, que no
  tiene una API para "migrarse" tal cual a un formulario estático.
  **Contacto ya está resuelto**: en vez de dejar la página rota (sin
  formulario), armé `src/pages/contacto.astro` a medida con 3 vías
  directas y funcionales hoy mismo: mailto, WhatsApp (wa.me) y link a
  Reservar Consulta. **Formulario, Reservar Consulta y Form Kickoff**
  siguen con el mismo problema — hay que decidir si les damos el mismo
  tratamiento (CTAs directas) o si conectamos un formulario real (ver
  pendientes).

## Landing pages de servicio (Börrega Inside / Audit / Audit RRSS / Core)
Estas 4 páginas resultaron ser landing pages ricas y distintas entre sí
(hero, checklist, columnas de features, pasos numerados, pricing, FAQ
acordeón, CTA final — cada una con su propia combinación), no páginas de
texto simple. Arme un set de componentes reutilizables en
`src/components/service/` (ServiceHero, IntroStatement, Checklist,
FeatureColumns, ProcessSteps, PricingCard, FAQAccordion, ServiceFinalCTA)
y cada página (`src/pages/externalizacion-servicios.astro`,
`auditoria-comunicacion.astro`, `auditoria-redes-sociales.astro`,
`servicios-multidisciplinarios.astro`) los ensambla con el contenido real
de cada una (relevado en vivo del sitio).

**Importante — esto es contenido "congelado", no dinámico:** a diferencia
del resto del sitio, el texto de estas 4 páginas está escrito directo en
el código (no se lee de WordPress en build-time). Es la práctica habitual
para landing pages de venta armadas con page-builder: el contenido de
Elementor no tiene una forma limpia de "migrarse" a datos estructurados.
Si en el futuro quieren editar el texto de estas páginas, hay que
editarlo en estos archivos `.astro` (o definir un modelo de datos en WP
tipo ACF para que sí se pueda traer dinámicamente — lo puedo armar si les
interesa).

**Actualización:** ya recuperé los fondos reales de los 4 heroes (dejo la
nota de abajo como historial). Externalización de Servicios y Auditoría
Comunicación tienen cada una una foto real de la oveja mascota de Börrega
contra un cielo con nubes — las encontré vía la REST API de medios de WP
buscando el archivo original detrás del thumb que genera Elementor.
Auditoría RRSS y Börrega Core usan un degradé (violeta + naranja, no solo
azul) con 3 iconitos decorativos (varita, cursor, flecha) que también son
reales, no inventados.

## Páginas de portfolio (Branding / Diseño Web / Fotografía / Animación 2D y 3D / Portfolio Redes Sociales)
Al igual que las landing de servicio, estas 5 páginas no comparten un
layout único — cada una es un formato de contenido distinto en el sitio
real, así que armé componentes específicos en `src/components/portfolio/`:

- `PortfolioHero`: header oscuro con el título subrayado (común a las 5).
- `CaseStudyList` (Branding): 7 casos de estudio reales — Coltix, Jiro
  Sushi, Mejoral, Siandra, Gallium, Madero First y Telecentro — con su
  texto y sus imágenes originales, incluyendo la atribución "Trabajo
  realizado para Estudio Dandy" donde corresponde en el sitio real.
- `LinkGrid` (Diseño Web): columnas "WordPress" y "Tienda Nube" con los
  links reales a cada sitio (incluye el sub-listado "Próximamente").
- `PhotoGallery` (Fotografía y Animación 2D y 3D): grilla de imágenes
  reales — el sitio original no tiene texto ni captions en estas dos
  páginas, solo la galería.
- `SocialShowcase` (Portfolio Redes Sociales): las 6 tarjetas de cliente
  (mockup de celular + grilla de 3 posteos) tal como están hoy.

**Origen de las imágenes:** las 62 imágenes (61MB) se tomaron directo del
export de WordPress que compartiste (`wp-content/uploads/2021/12/`), no
son descargas nuevas. Se procesan con `astro:assets` igual que el resto
del sitio, así que se sirven en webp optimizado (algunas bajaron de
~1MB a ~10-40KB).

**Simplificación consciente (misma que en las landing de servicio):** el
header (`PortfolioHero`) usa un degradé oscuro en vez de la foto de fondo
real de cada página (en el sitio original cada portfolio tiene su propia
foto detrás del título). Si querés esas fotos exactas, las puedo sumar.

## Blog (listado + entrada)
Ya migrado, siguiendo el mismo criterio que el resto del sitio: se lee en
build-time desde WordPress, no hay contenido hardcodeado.

Hallazgo importante durante el relevamiento: WordPress tiene ~30 "posts",
pero el Blog real (`/borrega-blog/`) NO los muestra a todos — el post-type
"post" tambien se usa para fichas de portfolio/trabajos con otras
categorias (Branding, Argentina, etc). El listado real filtra por la
categoria "BlogPost" (10 entradas hoy). `getBlogPosts()` en `src/lib/wp.ts`
replica exactamente ese filtro, asi que si crean posts nuevos en WP para
el blog, hay que asignarles la categoria "BlogPost" para que aparezcan.

- Listado: `src/pages/borrega-blog.astro` -> `/borrega-blog/`, grid de
  tarjetas con imagen destacada, titulo, excerpt y "Leer más", igual al
  diseño real (banner con gradiente violeta-a-negro arriba).
- Entrada individual: `src/pages/[year]/[month]/[day]/[slug].astro`.
  Mantuvimos la misma estructura de URL con fecha
  (`/2026/05/11/slug-del-post/`) que usa WordPress hoy, para no romper
  enlaces existentes ni SEO. El contenido pasa por el mismo limpiador de
  HTML de Elementor (`cleanContent.ts`) que las paginas. Incluye la
  tarjeta lateral "Solicitar más información" y la firma, tal como en el
  original.
- Cada entrada nueva que publiquen en WordPress (con categoria BlogPost)
  va a aparecer automaticamente la proxima vez que se corra `npm run
  build` — no hace falta tocar código.

**Corrección: posts que la clienta comparte por fuera de la web.**
Ignacio aclaró que varios de los ~30 posts de WordPress NO son para el
blog público — la clienta los usa para compartir info por fuera de la web
(mail, redes, etc.), sin que aparezcan en el listado. Eso coincide
exactamente con lo que habíamos encontrado: esos posts no tienen la
categoría "BlogPost". Ajustamos el comportamiento para mantener esa
estructura:

- **Todos** los posts (tengan o no la categoría BlogPost) generan su
  página real en `/YYYY/MM/DD/slug/` — así cualquier link que la clienta
  ya haya compartido sigue funcionando.
- **Solo** los posts con categoría BlogPost aparecen listados en
  `/borrega-blog/`.
- Los posts que NO son del blog público llevan `<meta name="robots"
  content="noindex, nofollow">` (no se indexan en Google) y no muestran el
  link "Volver a todos los Posts" (porque no pertenecen a ese listado).

Esto vive en `isPublicBlogPost()` (`src/lib/wp.ts`) y se probó con datos
simulados verificando ambos casos (post público vs. post "solo para
compartir").

## Correccion: Hero real (3 slides, no estatico)
El Hero no era texto+iconos armados en HTML como lo tenia el primer intento
de esta migracion: es un carousel de Elementor con 3 slides, cada una un
PNG pre-compuesto (texto + iconos + ilustracion, todo en la imagen), con
autoplay cada 5s y transicion "slide" de 1s, sin flechas ni bullets. Ademas
el sitio original sirve una composicion mas angosta para mobile (mismos
textos/iconos, recompuestos en un lienzo mas cuadrado) en vez de estirar o
recortar la version de escritorio. Ya esta migrado tal cual: carousel real
con las 3 imagenes reales (`src/assets/images/hero/`) + `<picture>` con la
variante mobile por media query, y un script liviano (sin libreria externa)
que replica el autoplay/loop original.

## Todavía falta (roadmap)
- Sistema de Blog conectado a los 30 posts reales de WordPress (queda
  para la siguiente etapa, según lo charlado).
- Resolver los formularios (opciones: Formspree/similar, un endpoint
  propio, o mantener esas páginas puntuales embebiendo el iframe del
  formulario de WP).
- Revisión visual página por página contra el sitio real una vez que se
  pueda ejecutar el build con acceso a internet normal (ver nota abajo).
- Afinar animaciones especificas de Ooohboi Steroids (parallax/tilt) que
  hoy tienen un reemplazo liviano con IntersectionObserver.

## Arquitectura
- **Astro en modo `static`** (SSG): genera HTML/CSS/JS puro. Se puede
  subir a cualquier hosting, incluido el hosting compartido actual donde
  vive WordPress.
- **WordPress se mantiene tal cual**, como backend/CMS puro. El contenido
  se lee en build-time desde la REST API pública de WP (`src/lib/wp.ts`).
- **`src/lib/cleanContent.ts`**: limpia el HTML que devuelve Elementor
  (quita los `div` de layout/columnas/widgets y se queda con el contenido
  real: títulos, párrafos, listas, botones, imágenes) para poder
  re-maquetarlo con estilos propios en vez de depender del CSS/JS de
  Elementor.
- Cada vez que se publique contenido nuevo en WordPress hay que volver a
  generar el sitio (`npm run build`) y resubir el `dist/`. Se puede
  automatizar con un webhook de WP que dispare un rebuild.

## Nota sobre esta entrega
El build de las páginas dinámicas (`[slug].astro`) necesita salir a
internet para consultar `borregastudio.com/wp-json`. Lo armé y lo probé
con un servidor local que simula esa API (para validar que la lógica de
limpieza de HTML y el ruteo funcionan bien), pero no pude correr el build
final contra el sitio real desde este entorno de trabajo por una
restricción de red del sandbox. Cuando lo corras vos (`npm run build`)
con conexión a internet normal, va a traer el contenido real sin cambios
de código. El `dist-preview/` de esta entrega es solo de la Home.

## Cómo correrlo
\`\`\`bash
npm install
npm run dev       # http://localhost:4321
npm run build     # genera dist/ (necesita internet para las paginas dinamicas)
npm run preview   # sirve dist/ para probarlo como en produccion
\`\`\`

## Pendiente de definir con Ignacio
- Dónde vivirá exactamente WordPress una vez que el dominio principal
  sirva el build estático de Astro (subdominio tipo `cms.borregastudio.com`,
  o ruta separada dentro del mismo hosting).
- Acceso al hosting/FTP para el deploy final.
- Cómo resolver los formularios (ver arriba).
- Prioridad de páginas para pasar de "plantilla genérica" a diseño a
  medida.
