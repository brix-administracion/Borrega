import { defineConfig } from 'astro/config';
import { setGlobalDispatcher, EnvHttpProxyAgent } from 'undici';

// El sandbox de build sale a internet via proxy (HTTP_PROXY/HTTPS_PROXY).
// El fetch nativo de Node no lee esas variables de entorno por defecto,
// asi que forzamos un dispatcher que si las respeta. Esto solo afecta al
// entorno de build; en un CI/hosting normal con salida directa a internet
// esto es un no-op inofensivo.
setGlobalDispatcher(new EnvHttpProxyAgent());

// Sitio migrado desde WordPress (Elementor) a Astro.
// Output "static": genera HTML/CSS/JS puro, apto para subir a cualquier
// hosting (incluido el hosting compartido actual donde vive WordPress).
// El contenido (posts, paginas, medios) se consume en build-time desde
// la REST API publica de WordPress -> https://www.borregastudio.com/wp-json/
export default defineConfig({
  site: 'https://www.borregastudio.com',
  output: 'static',
  build: {
    format: 'directory',
  },
  image: {
    domains: ['www.borregastudio.com', 'borregastudio.com'],
  },
  // Astro cachea aca las imagenes remotas ya optimizadas. El default es
  // ./node_modules/.astro, pero en CI `npm ci` borra node_modules entero, asi
  // que la cache no sobrevivia y el runner volvia a bajar las imagenes de
  // WordPress en cada build. Fuera de node_modules, el workflow la persiste.
  cacheDir: './.astro-cache',
  // /formulario/ es una URL vieja/duplicada de WordPress que muestra el
  // mismo contenido que /sumate/ (mismo formulario, mismo texto). En vez
  // de duplicar la página, la redirigimos para no repetir contenido.
  redirects: {
    '/formulario': '/sumate',
  },
});
