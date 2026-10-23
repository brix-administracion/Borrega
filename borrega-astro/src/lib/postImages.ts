import { getImage } from 'astro:assets';

export interface OptimizedImage {
  src: string;
  width: number;
  height: number;
}

// Una optimizacion por imagen DISTINTA y no una por uso: los relacionados de
// las 30 paginas son casi siempre las mismas 3 imagenes, y la galeria de un
// trabajo no se repite. Se guarda la promesa (no el resultado) para que dos
// paginas que piden la misma imagen a la vez compartan una sola llamada.
// getImage() y no <Image>, sin inferSize ni `fit`: ver DomeGallery.astro.
const cache = new Map<string, Promise<OptimizedImage>>();

export function optimizeImage(
  src: string,
  sourceWidth: number | undefined,
  sourceHeight: number | undefined,
  targetPx: number,
): Promise<OptimizedImage> {
  // Nunca agrandar: si el original es mas chico que el ancho pedido se usa el
  // suyo. Si WP no dio medidas se usa un aspecto de referencia (3:2) en vez de
  // inferSize, que ignora la cache de Astro y baja el original en cada build.
  const width = sourceWidth ? Math.min(targetPx, sourceWidth) : targetPx;
  const height = Math.round(((sourceHeight ?? 2) / (sourceWidth ?? 3)) * width);
  const key = `${src}|${width}`;
  let pending = cache.get(key);
  if (!pending) {
    pending = getImage({ src, width, height, format: 'webp', quality: 72 }).then((result) => ({
      src: result.src,
      width: Number(result.attributes.width ?? width),
      height: Number(result.attributes.height ?? height),
    }));
    cache.set(key, pending);
  }
  return pending;
}
