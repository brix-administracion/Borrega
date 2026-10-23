import { parse, type HTMLElement } from 'node-html-parser';

// El HTML que devuelve WordPress (content.rendered) viene envuelto en la
// estructura de contenedores de Elementor (secciones/columnas/widgets) que
// no tiene sentido migrar tal cual porque depende del runtime/CSS de
// Elementor. Esta funcion extrae el contenido semantico real (titulos,
// parrafos, listas, botones, imagenes) y descarta el "div soup" de layout,
// para poder re-maquetarlo con nuestros propios estilos.
const CONTENT_TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul', 'ol', 'blockquote', 'table', 'figure'];

function hasAncestorTag(el: HTMLElement, tags: string[]): boolean {
  let parent = el.parentNode as HTMLElement | null;
  while (parent) {
    const tag = parent.tagName?.toLowerCase();
    if (tag && tags.includes(tag)) return true;
    parent = parent.parentNode as HTMLElement | null;
  }
  return false;
}

function stripAttributesDeep(el: HTMLElement) {
  const clean = (node: HTMLElement) => {
    const href = node.getAttribute('href');
    const src = node.getAttribute('src');
    const alt = node.getAttribute('alt');
    const width = node.getAttribute('width');
    const height = node.getAttribute('height');
    const cls = node.getAttribute('class') || '';
    const isBtn = /\bbtn\b/.test(cls);
    const attrs = { ...node.attributes };
    for (const attr of Object.keys(attrs)) node.removeAttribute(attr);
    if (href) node.setAttribute('href', href);
    if (src) node.setAttribute('src', src);
    if (alt !== undefined && alt !== null) node.setAttribute('alt', alt);
    // width/height son inocuos y le dejan al navegador reservar el espacio de la
    // imagen antes de que cargue (sin salto de layout). srcset en cambio se sigue
    // descartando: apunta a archivos de WordPress sin optimizar.
    if (width) node.setAttribute('width', width);
    if (height) node.setAttribute('height', height);
    if (isBtn) node.setAttribute('class', 'btn');
    if (node.tagName?.toLowerCase() === 'a' && (href?.startsWith('http') ?? false)) {
      node.setAttribute('target', '_blank');
      node.setAttribute('rel', 'noopener');
    }
  };
  clean(el);
  el.querySelectorAll('*').forEach(clean);
}

export function cleanElementorHtml(html: string): string {
  const root = parse(html);

  // Botones de Elementor -> nuestra clase .btn (se detecta por texto de rol)
  root.querySelectorAll('a.elementor-button, a.elementor-button-link, .elementor-widget-button a').forEach((a) => {
    a.setAttribute('class', 'btn');
  });

  const textBlocks = root.querySelectorAll(CONTENT_TAGS.join(',')).filter((el) => !hasAncestorTag(el, CONTENT_TAGS));

  // Botones que quedaron sueltos (no dentro de un parrafo/heading ya capturado)
  const standaloneButtons = root
    .querySelectorAll('a.btn')
    .filter((el) => !hasAncestorTag(el, CONTENT_TAGS));

  // Imagenes de contenido que no estan dentro de un bloque ya capturado
  const looseImages = root.querySelectorAll('img').filter((img) => !hasAncestorTag(img, CONTENT_TAGS));

  // Mantenemos el orden original en el documento
  const all = [...textBlocks, ...standaloneButtons, ...looseImages];
  const seen = new Set<HTMLElement>();
  const ordered = all
    .filter((el) => (seen.has(el) ? false : (seen.add(el), true)))
    .sort((a, b) => (a.range?.[0] ?? 0) - (b.range?.[0] ?? 0));

  for (const block of ordered) stripAttributesDeep(block);

  return ordered.map((b) => b.outerHTML).join('\n');
}
