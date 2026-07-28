// Lista compartida de slugs de WordPress que NO se generan con la
// plantilla generica (src/pages/[slug].astro) porque tienen tratamiento
// especial en otro lado, o no son contenido publico real.
// La usan tanto [slug].astro (para excluirlas del build generico) como
// sitemap.xml.ts (para no listarlas / no duplicarlas).
export const EXCLUDED_SLUGS = [
  'home-2', // version vieja de la home, ya reemplazada
  'borrega-blog', // tiene su propia pagina (listado de blog)
  'assist-testing-borrega', // pagina de pruebas interna, no es contenido publico
  'contacto', // pagina a medida
  'externalizacion-servicios', // landing de servicio a medida
  'auditoria-comunicacion', // landing de servicio a medida
  'auditoria-redes-sociales', // landing de servicio a medida
  'servicios-multidisciplinarios', // landing de servicio a medida
  'branding', // portfolio a medida
  'diseno-web', // portfolio a medida
  'fotografia', // portfolio a medida
  'animacion-2d-y-3d', // portfolio a medida
  'portfolio-redes-sociales', // portfolio a medida
  'reservar-consulta', // pagina a medida (CTAs)
  'sumate', // formulario a medida (Web3Forms)
  'form-kickoff', // herramienta interna a medida, noindex
];
