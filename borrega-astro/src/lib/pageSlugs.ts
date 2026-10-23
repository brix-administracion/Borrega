// Allowlist de slugs de WordPress que SI se generan con la plantilla
// generica (src/pages/[slug].astro). La usan tanto [slug].astro como
// sitemap.xml.ts, para no duplicarla ni que se desincronicen.
//
// Es una allowlist a proposito, no una lista de exclusiones. WordPress
// quedo como deposito de contenido de dos cosas y nada mas: el blog y los
// trabajos (ambos son posts, no paginas). El diseño del sitio vive en
// Astro. Con una blocklist, cualquier pagina nueva que la clienta cree en
// WP entraba sola al sitio y al sitemap, y entraba mal: la plantilla
// generica es una extraccion semantica (ver cleanElementorHtml) que
// descarta todo el layout de Elementor. Con una allowlist, una pagina
// nueva no aparece hasta que alguien la agrega aca a mano y le revisa como
// se ve.
//
// Agregar un slug aca implica aceptar que su contenido se publique tal
// como lo deje la extraccion semantica. Si una pagina necesita diseño
// propio, va como pagina a medida en src/pages/ y NO entra a esta lista.
export const ALLOWED_PAGE_SLUGS = [
  'politicas-de-privacidad', // texto legal corrido, la extraccion generica alcanza
];
