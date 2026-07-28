import type { APIRoute } from 'astro';
import { getAllPages, getBlogPosts, postPath } from '../lib/wp';
import { EXCLUDED_SLUGS } from '../lib/pageSlugs';

// Sitemap generado a mano (no @astrojs/sitemap) porque necesitamos la
// misma logica de "publico vs privado" que ya usamos en el blog: los
// posts que la clienta comparte por fuera de la web (sin la categoria
// "BlogPost") no deben aparecer aca, igual que no se listan en
// /borrega-blog/ ni se indexan (noindex en su propia pagina).
const SITE = 'https://www.borregastudio.com';

// Rutas que no vienen de la REST API de WP (paginas armadas a medida en
// Astro: home, landings de servicio, portfolio, contacto, etc).
const STATIC_ROUTES = [
  '/',
  '/borrega-blog/',
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
  const excluded = new Set(EXCLUDED_SLUGS);

  const [wpPages, publicPosts] = await Promise.all([getAllPages(), getBlogPosts()]);

  const wpPageUrls = wpPages.filter((p) => !excluded.has(p.slug)).map((p) => `/${p.slug}/`);

  const blogUrls = publicPosts.map((post) => postPath(post));

  const allUrls = Array.from(new Set([...STATIC_ROUTES, ...wpPageUrls, ...blogUrls]));

  const body = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${allUrls.map((url) => `  <url>\n    <loc>${SITE}${url}</loc>\n  </url>`).join('\n')}
</urlset>
`;

  return new Response(body, {
    headers: { 'Content-Type': 'application/xml; charset=utf-8' },
  });
};
