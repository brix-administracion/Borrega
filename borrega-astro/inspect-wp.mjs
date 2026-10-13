// inspect-wp.mjs — releva categorías y posts de WordPress para diseñar la
// sincronización de "Proyectos". Corre en tu Mac (que sí llega a WP).
// Uso:  node inspect-wp.mjs
const BASE = process.env.WP_BASE_URL || 'https://www.borregastudio.com';
const API = `${BASE}/wp-json/wp/v2`;

async function getAll(path) {
  let page = 1, out = [];
  while (true) {
    const sep = path.includes('?') ? '&' : '?';
    const res = await fetch(`${API}${path}${sep}per_page=100&page=${page}`);
    if (!res.ok) { if (res.status === 400) break; throw new Error(`${res.status} en ${path}`); }
    const batch = await res.json();
    out = out.concat(batch);
    const total = Number(res.headers.get('x-wp-totalpages') || '1');
    if (page >= total || batch.length === 0) break;
    page++;
  }
  return out;
}

const cats = await getAll('/categories?_fields=id,name,slug,count');
const catById = Object.fromEntries(cats.map((c) => [c.id, c]));

console.log('=== CATEGORIAS (slug | nombre | count) — ordenadas por count ===');
cats.sort((a, b) => b.count - a.count).forEach((c) => console.log(`${c.slug} | ${c.name} | ${c.count}`));

const posts = await getAll('/posts?_fields=id,slug,date,title,categories,featured_media&orderby=date&order=desc');
console.log(`\n=== POSTS (${posts.length}) — fecha | titulo | slug | [categorias] | mediaId ===`);
for (const p of posts) {
  const title = (p.title?.rendered || '').replace(/<[^>]+>/g, '').trim();
  const names = (p.categories || []).map((id) => catById[id]?.name || `#${id}`).join(', ');
  console.log(`${(p.date || '').slice(0, 10)} | ${title} | ${p.slug} | [${names}] | ${p.featured_media || '-'}`);
}
