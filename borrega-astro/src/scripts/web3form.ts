// Envio de formularios estaticos via Web3Forms (ver src/lib/formConfig.ts).
// Reutilizado por Sumate y Form Kickoff: intercepta el submit, arma un
// FormData (soporta archivos adjuntos tal cual el form original en WP),
// lo manda a la API de Web3Forms y muestra un estado de exito/error sin
// recargar la pagina.
import { WEB3FORMS_ACCESS_KEY, WEB3FORMS_ENDPOINT } from '../lib/formConfig';

function downloadSummaryTxt(form: HTMLFormElement) {
  const formData = new FormData(form);
  const lines: string[] = [`Resumen — ${document.title}`, `Fecha: ${new Date().toLocaleString('es-AR')}`, ''];
  const seen = new Set<string>();
  formData.forEach((value, key) => {
    if (key === 'access_key' || key === 'botcheck' || key === 'subject') return;
    if (value instanceof File) {
      if (value.name) lines.push(`${key}: [archivo adjunto] ${value.name}`);
      return;
    }
    if (!value) return;
    if (seen.has(key)) {
      // Campo repetido (ej. checkboxes con el mismo name) -> acumular en la misma linea
      const idx = lines.findIndex((l) => l.startsWith(`${key}:`));
      if (idx >= 0) {
        lines[idx] += `, ${value}`;
        return;
      }
    }
    seen.add(key);
    lines.push(`${key}: ${value}`);
  });

  const blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'resumen-kickoff.txt';
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

export function initWeb3Form(formId: string, options: { downloadSummary?: boolean } = {}) {
  const form = document.getElementById(formId) as HTMLFormElement | null;
  if (!form) return;

  const statusEl = form.querySelector<HTMLElement>('[data-form-status]');
  const submitBtn = form.querySelector<HTMLButtonElement>('[data-form-submit]');

  const setStatus = (kind: 'success' | 'error', message: string) => {
    if (!statusEl) return;
    statusEl.textContent = message;
    statusEl.classList.remove('success', 'error');
    statusEl.classList.add(kind, 'is-visible');
  };

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (WEB3FORMS_ACCESS_KEY.startsWith('REEMPLAZAR')) {
      setStatus('error', 'Este formulario todavia no tiene configurada la Access Key de Web3Forms. Ver src/lib/formConfig.ts.');
      return;
    }

    const formData = new FormData(form);
    formData.append('access_key', WEB3FORMS_ACCESS_KEY);

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.dataset.originalText = submitBtn.dataset.originalText || submitBtn.textContent || '';
      submitBtn.textContent = 'Enviando...';
    }

    try {
      const res = await fetch(WEB3FORMS_ENDPOINT, {
        method: 'POST',
        body: formData,
      });
      const data = await res.json();

      if (data.success) {
        setStatus('success', '¡Listo! Recibimos tu información, te contactamos a la brevedad.');
        if (options.downloadSummary) downloadSummaryTxt(form);
        form.reset();
      } else {
        setStatus('error', data.message || 'Hubo un problema al enviar el formulario. Probá de nuevo o escribinos por mail.');
      }
    } catch (err) {
      setStatus('error', 'No pudimos conectar con el servidor. Probá de nuevo o escribinos por mail.');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = submitBtn.dataset.originalText || 'Enviar';
      }
    }
  });
}
