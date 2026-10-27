import type { APIRoute } from 'astro';
import { getAllPages, getBlogPosts, getWorkPosts, postPath } from '../lib/wp';
import { ALLOWED_PAGE_SLUGS } from '../lib/pageSlugs';

// Sitemap generado a mano (no @astrojs/sitemap) porque la lista de rutas no
// sale sola: hay paginas armadas a medida en Astro, una allowlist de paginas
// de WordPress, y los posts, que vienen de dos colecciones distintas.
//
// Los 30 posts de WordPress entran: 10 del blog y 20 trabajos. Yoast publica
// los 30 en su post-sitemap.xml y acompañamos ese criterio.
const SITE = 'https://www.borregastudio.com';

// Rutas que no vienen de la REST API de WP (paginas armadas a medida en
// Astro: home, landings de servicio, portfolio, contacto, etc).
const STATIC_ROUTES = [
  '/',
  '/borrega-blog/',
  '/nuestro-trabajo/',
  '/contacto/',
  '/reservar-consulta/',
  '/sumate/',
  '/externalizacion-servicios/',
  '/auditoria-comunicacion/',
  '/auditoria-redes-sociales/',
  '/servicios-multidisciplinarios/',
  '/branding/',
  '/diseno-web/',
  '/fotografia/',
  '/animacion-2d-y-3d/',
  '/portfolio-redes-sociales/',
];
// NOTA: /form-kickoff/ se excluye a proposito (noindex — es una
// herramienta interna para un cliente puntual, no contenido publico).
// /formulario/ tampoco se lista: es un redirect a /sumate/, no una pagina.

export const GET: APIRoute = async () => {
  const allowed = new Set(ALLOWED_PAGE_SLUGS);

  const [wpPages, blogPosts, workPosts] = await Promise.all([
    getAllPages(),
    getBlogPosts(),
    // La MISMA funcion que usa /nuestro-trabajo/ para armar su listado, asi el
    // sitemap y la pagina no se pueden desincronizar: un proyecto nuevo que
    // suba la clienta entra a los dos o a ninguno.
    getWorkPosts(),
  ]);

  // Solo las paginas de WP que la allowlist habilita. Una pagina nueva en
  // WordPress no entra al sitemap hasta que alguien la agregue a mano.
  const wpPageUrls = wpPages.filter((p) => allowed.has(p.slug)).map((p) => `/${p.slug}/`);

  const postUrls = [...blogPosts, ...workPosts].map((post) => postPath(post));

  const allUrls = Array.from(new Set([...STATIC_ROUTES, ...wpPageUrls, ...postUrls]));

  const body = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${allUrls.map((url) => `  <url>\n    <loc>${SITE}${url}</loc>\n  </url>`).join('\n')}
</urlset>
`;

  return new Response(body, {
    headers: { 'Content-Type': 'application/xml; charset=utf-8' },
  });
};
