// Cliente minimo para consumir la REST API publica de WordPress.
// El backend (WordPress + Elementor) se mantiene tal cual esta hoy;
// este frontend solo lee contenido, no escribe nada.
//
// Base URL configurable por variable de entorno para poder apuntar
// a un dominio/subdominio distinto el dia que WP se mueva de lugar
// (por ejemplo cms.borregastudio.com) sin tocar codigo.
const WP_BASE_URL = import.meta.env.WP_BASE_URL || 'https://www.borregastudio.com';
const API = `${WP_BASE_URL}/wp-json/wp/v2`;

async function wpFetch<T>(path: string): Promise<T> {
  const res = await fetch(`${API}${path}`);
  if (!res.ok) {
    throw new Error(`WP API error ${res.status} on ${path}`);
  }
  return res.json() as Promise<T>;
}

export interface WPRendered {
  rendered: string;
}

export interface WPMediaDetails {
  width: number;
  height: number;
  sizes?: Record<string, { source_url: string; width: number; height: number }>;
}

export interface WPMedia {
  id: number;
  source_url: string;
  alt_text: string;
  media_details?: WPMediaDetails;
}

export interface WPPost {
  id: number;
  slug: string;
  date: string;
  title: WPRendered;
  content: WPRendered;
  excerpt: WPRendered;
  featured_media: number;
  categories: number[];
  _embedded?: {
    'wp:featuredmedia'?: WPMedia[];
    'wp:term'?: Array<Array<{ id: number; name: string; slug: string }>>;
  };
}

export interface WPPage {
  id: number;
  slug: string;
  title: WPRendered;
  content: WPRendered;
  parent: number;
}

export interface WPCategory {
  id: number;
  slug: string;
  name: string;
  count: number;
}

// Trae todas las paginas de una coleccion paginada de WP (per_page max 100).
async function fetchAllPages<T>(basePath: string, extraParams = ''): Promise<T[]> {
  const perPage = 100;
  let page = 1;
  let results: T[] = [];
  while (true) {
    const res = await fetch(`${API}${basePath}?per_page=${perPage}&page=${page}${extraParams}`);
    if (!res.ok) {
      if (res.status === 400) break; // no hay mas paginas
      throw new Error(`WP API error ${res.status} on ${basePath}`);
    }
    const batch = (await res.json()) as T[];
    results = results.concat(batch);
    const totalPages = Number(res.headers.get('x-wp-totalpages') || '1');
    if (page >= totalPages || batch.length === 0) break;
    page += 1;
  }
  return results;
}

export function getAllPosts() {
  return fetchAllPages<WPPost>('/posts', '&_embed=1&orderby=date&order=desc');
}

export function getPostBySlug(slug: string) {
  return wpFetch<WPPost[]>(`/posts?slug=${encodeURIComponent(slug)}&_embed=1`).then((r) => r[0]);
}

export function getAllPages() {
  return fetchAllPages<WPPage>('/pages');
}

export function getPageBySlug(slug: string) {
  return wpFetch<WPPage[]>(`/pages?slug=${encodeURIComponent(slug)}`).then((r) => r[0]);
}

export function getAllCategories() {
  return wpFetch<WPCategory[]>('/categories?per_page=100');
}

export function featuredImageOf(post: WPPost): WPMedia | undefined {
  return post._embedded?.['wp:featuredmedia']?.[0];
}

// El "Blog" real del sitio (borregastudio.com/borrega-blog/) NO muestra
// los ~30 posts que existen en WordPress: el post-type "post" tambien se
// usa para fichas de trabajos/portfolio con otras categorias (Branding,
// Argentina, etc). El listado de blog filtra por la categoria "BlogPost".
// Replicamos ese mismo filtro aca en vez de listar todos los posts.
const BLOG_CATEGORY_SLUG = 'blogpost';

export async function getBlogPosts(): Promise<WPPost[]> {
  const blogCategoryId = await getBlogCategoryId();
  if (blogCategoryId == null) return [];
  return fetchAllPages<WPPost>('/posts', `&_embed=1&orderby=date&order=desc&categories=${blogCategoryId}`);
}

export async function getBlogCategoryId(): Promise<number | null> {
  const categories = await getAllCategories();
  const blogCategory = categories.find((c) => c.slug === BLOG_CATEGORY_SLUG);
  return blogCategory ? blogCategory.id : null;
}

// La clienta usa WordPress tambien para posts que NO son del blog publico:
// los comparte por fuera de la web (mail, redes, etc) sin que aparezcan
// en el listado de /borrega-blog/. Esos posts NO tienen la categoria
// "BlogPost". Igual necesitan una URL real y funcional (para que el link
// que la clienta comparte no rompa), solo que no deben listarse ni
// indexarse publicamente. Esta funcion identifica esos casos.
export function isPublicBlogPost(post: WPPost, blogCategoryId: number | null): boolean {
  if (blogCategoryId == null) return false;
  return post.categories.includes(blogCategoryId);
}

// WordPress usa permalinks por fecha (/YYYY/MM/DD/slug/) para las entradas
// de blog. Mantenemos esa misma estructura de URL para no romper enlaces
// existentes / SEO al migrar.
export function postPath(post: WPPost): string {
  const d = new Date(post.date);
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `/${yyyy}/${mm}/${dd}/${post.slug}/`;
}
