// Cliente minimo para consumir la REST API publica de WordPress.
// El backend (WordPress + Elementor) se mantiene tal cual esta hoy;
// este frontend solo lee contenido, no escribe nada.
//
// Base URL configurable por variable de entorno para poder apuntar
// a un dominio/subdominio distinto el dia que WP se mueva de lugar
// (por ejemplo cms.borregastudio.com) sin tocar codigo.
const WP_BASE_URL = import.meta.env.WP_BASE_URL || 'https://www.borregastudio.com';
const API = `${WP_BASE_URL}/wp-json/wp/v2`;

// Algunos hostings (incluido el de borregastudio.com) responden 403 a los
// pedidos de /wp-json que llegan sin user-agent de navegador o desde IPs de
// datacenter (proteccion anti-bots). Mandamos headers de navegador para que
// el build desde CI (GitHub Actions) pueda leer la REST API igual que un
// navegador normal.
const WP_HEADERS: Record<string, string> = {
  'User-Agent':
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
  Accept: 'application/json',
};

// Ese mismo hosting, desde IPs de datacenter como las de GitHub Actions, a
// veces va mas lejos y rechaza la conexion de una (ECONNREFUSED) en vez de
// contestar un 403. Un build sin defensa se cae entero por un fallo pasajero,
// asi que cada pedido se reintenta con esperas crecientes.
const RETRY_DELAYS_MS = [1000, 3000, 8000, 20000];

// Solo lo que puede arreglarse esperando. El 400 NO entra: fetchAllPages lo
// usa como señal de "no hay mas paginas" y reintentarlo agregaria medio
// minuto por coleccion. El 403 y el 404 tampoco mejoran solos.
const RETRYABLE_STATUS = new Set([408, 425, 429, 500, 502, 503, 504]);

const wait = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

function describeError(error: unknown): string {
  if (!(error instanceof Error)) return String(error);
  const cause = error.cause;
  if (cause instanceof Error) return `${error.message}: ${cause.message}`;
  return error.message;
}

async function fetchWithRetry(url: string): Promise<Response> {
  let lastReason = 'sin detalle';
  for (let attempt = 0; attempt <= RETRY_DELAYS_MS.length; attempt += 1) {
    if (attempt > 0) await wait(RETRY_DELAYS_MS[attempt - 1]);
    try {
      const res = await fetch(url, { headers: WP_HEADERS });
      if (res.ok || !RETRYABLE_STATUS.has(res.status)) return res;
      lastReason = `HTTP ${res.status}`;
    } catch (error) {
      // Errores de red: ECONNREFUSED, ECONNRESET, ETIMEDOUT, EAI_AGAIN...
      // El fetch de Node los envuelve en un "fetch failed" generico y deja el
      // motivo real en `cause`, que es justo el dato que sirve en el log.
      lastReason = describeError(error);
    }
  }
  throw new Error(
    `WordPress no respondio tras ${RETRY_DELAYS_MS.length + 1} intentos (${lastReason}): ${url}`,
  );
}

// Un build de Astro corre en UN proceso y el contenido no cambia a mitad de
// camino, asi que alcanza con guardar la promesa de cada coleccion. Sin esto
// el build pedia /categories seis veces y /posts tres: 13 pedidos a la API
// para cuatro endpoints distintos.
const inFlight = new Map<string, Promise<unknown>>();

function once<T>(key: string, load: () => Promise<T>): Promise<T> {
  const cached = inFlight.get(key) as Promise<T> | undefined;
  if (cached) return cached;
  const pending = load().catch((error) => {
    // Si falla se saca del mapa, para que el proximo que la necesite vuelva a
    // intentar en vez de heredar el rechazo.
    inFlight.delete(key);
    throw error;
  });
  inFlight.set(key, pending);
  return pending;
}

async function wpFetch<T>(path: string): Promise<T> {
  const res = await fetchWithRetry(`${API}${path}`);
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
  parent: number;
}

// Trae todas las paginas de una coleccion paginada de WP (per_page max 100).
async function fetchAllPages<T>(basePath: string, extraParams = ''): Promise<T[]> {
  const perPage = 100;
  let page = 1;
  let results: T[] = [];
  while (true) {
    const res = await fetchWithRetry(`${API}${basePath}?per_page=${perPage}&page=${page}${extraParams}`);
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
  return once('posts', () => fetchAllPages<WPPost>('/posts', '&_embed=1&orderby=date&order=desc'));
}

export function getPostBySlug(slug: string) {
  return wpFetch<WPPost[]>(`/posts?slug=${encodeURIComponent(slug)}&_embed=1`).then((r) => r[0]);
}

export function getAllPages() {
  return once('pages', () => fetchAllPages<WPPage>('/pages'));
}

export function getPageBySlug(slug: string) {
  return wpFetch<WPPage[]>(`/pages?slug=${encodeURIComponent(slug)}`).then((r) => r[0]);
}

export function getAllCategories() {
  return once('categories', () => wpFetch<WPCategory[]>('/categories?per_page=100'));
}

export function featuredImageOf(post: WPPost): WPMedia | undefined {
  return post._embedded?.['wp:featuredmedia']?.[0];
}

// Las featured images de WordPress llegan en tamaño original (2560px y mas).
// Para miniaturas eso es insostenible, sobre todo en el domo del home, que
// pinta 100 tiles. Elegimos el tamaño mas chico que siga viendose bien en
// retina y caemos para atras si WP no genero ese size.
const TILE_SIZE_PREFERENCE = ['medium_large', 'large', 'medium'] as const;

export interface TileImage {
  src: string;
  /** Faltan cuando WordPress nunca proceso la imagen (ver abajo). */
  width?: number;
  height?: number;
}

export function tileImageOf(media: WPMedia): TileImage {
  const sizes = media.media_details?.sizes;
  if (sizes) {
    for (const name of TILE_SIZE_PREFERENCE) {
      const size = sizes[name];
      if (size?.source_url) return { src: size.source_url, width: size.width, height: size.height };
    }
  }
  // Hay featured images que WordPress nunca proceso: la de playstation-arg es
  // un PNG de 13 MB sin miniaturas ni `media_details`, asi que de esa no
  // sabemos ni el tamaño. Quien la use tendra que averiguarlo bajandola.
  return {
    src: media.source_url,
    width: media.media_details?.width,
    height: media.media_details?.height,
  };
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
  return once(`posts:categoria:${blogCategoryId}`, () =>
    fetchAllPages<WPPost>('/posts', `&_embed=1&orderby=date&order=desc&categories=${blogCategoryId}`),
  );
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

// ---------------------------------------------------------------------------
// Trabajos / Proyectos ("Nuestro trabajo")
// Los proyectos del sitio son los posts que NO pertenecen al blog (no tienen
// la categoria "BlogPost"). Cada uno ademas ya genera su pagina de detalle en
// /YYYY/MM/DD/slug/. Listarlos desde aca hace que se sincronicen solos con
// WordPress en cada build: si la clienta agrega un proyecto nuevo en WP,
// aparece sin tocar codigo.
export async function getWorkPosts(limit?: number): Promise<WPPost[]> {
  const [blogCategoryId, all] = await Promise.all([getBlogCategoryId(), getAllPosts()]);
  const work = all.filter((p) => blogCategoryId == null || !p.categories.includes(blogCategoryId));
  return typeof limit === 'number' ? work.slice(0, limit) : work;
}

// ---------------------------------------------------------------------------
// Pais de cada proyecto
// El pais NO es un campo propio del post: en WordPress se modela como las
// categorias hijas de "Por pais" (slug `pais`). Buscamos la hija por `parent`
// en vez de hardcodear ids, asi la clienta puede agregar un pais nuevo en WP
// y solo hace falta sumar su ISO al mapa de abajo.
//
// La bandera si la ponemos nosotros: en WordPress no hay un asset por
// proyecto. La media library tiene UNA sola imagen de bandera (la argentina)
// y la plantilla de Elementor la hardcodea para todos los proyectos, incluso
// los que no son de Argentina. Servimos un SVG local por pais desde /flags/.
const COUNTRY_PARENT_SLUG = 'pais';

const COUNTRY_ISO: Record<string, string> = {
  argentina: 'ar',
  brasil: 'br',
  usa: 'us',
  espana: 'es',
  francia: 'fr',
  australia: 'au',
  'puerto-rico': 'pr',
};

export interface WPCountry {
  slug: string;
  name: string;
  iso: string;
}

export async function getCountryCategories(): Promise<WPCategory[]> {
  const categories = await getAllCategories();
  const parent = categories.find((c) => c.slug === COUNTRY_PARENT_SLUG);
  // Si la taxonomia de paises desaparece de WP, los proyectos siguen
  // renderizando sin bandera en vez de romper el build.
  if (!parent) return [];
  return categories.filter((c) => c.parent === parent.id);
}

// Hay proyectos que tienen la categoria padre "Por pais" pero ninguna hija
// (por ejemplo la campaña de PlayStation), asi que el pais es opcional y
// quien renderiza tiene que aguantar `undefined`.
export function countryOf(post: WPPost, countries: WPCategory[]): WPCountry | undefined {
  const match = countries.find((c) => post.categories.includes(c.id));
  if (!match) return undefined;
  const iso = COUNTRY_ISO[match.slug];
  if (!iso) return undefined;
  return { slug: match.slug, name: match.name, iso };
}
