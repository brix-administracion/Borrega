var bkReg = {};

document.addEventListener('DOMContentLoaded', function() {
    
    // Disable enter key inside inputs to prevent accidental submissions
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
        }
    });

    document.addEventListener('click', function(e) {
        // Toggle checkboxes
        var check = e.target.closest('.bk-check');
        // Prevent toggle if clicking inside the panel
        if (check && !e.target.closest('.bk-panel')) {
            var isOn = check.classList.contains('on');
            check.classList.toggle('on', !isOn);
        }

        // Trigger file input clicks from upload zones
        var uploadZone = e.target.closest('.bk-upload');
        if (uploadZone) {
            var inputId = uploadZone.getAttribute('data-target');
            if (inputId) {
                var fileInput = document.getElementById(inputId);
                if (fileInput) fileInput.click();
            }
        }

        // Remove file
        var rmBtn = e.target.closest('.bk-file-rm');
        if (rmBtn) {
            var key = rmBtn.getAttribute('data-key');
            var idx = parseInt(rmBtn.getAttribute('data-idx'), 10);
            var listId = rmBtn.getAttribute('data-list');
            if (key && !isNaN(idx) && listId) {
                bkRmFile(key, idx, listId);
            }
        }

        // Submit form
        if (e.target.closest('#bk-btn')) {
            bkSubmit();
        }
    });

    document.addEventListener('change', function(e) {
        // Toggle Guia
        if (e.target.name === 'tiene_guia') {
            bkToggleGuia();
        }

        // File inputs
        if (e.target.classList.contains('bk-file-input')) {
            var key = e.target.getAttribute('data-key');
            var listId = e.target.getAttribute('data-list');
            if (key && listId) {
                bkRegFiles(e.target, key, listId);
            }
        }
    });

    // drag-drop extra
    var extraZone = document.getElementById('bk-extra-zone');
    if (extraZone) {
      extraZone.addEventListener('dragover', function(e) { e.preventDefault(); extraZone.style.borderColor='#7C3AED'; });
      extraZone.addEventListener('dragleave', function() { extraZone.style.borderColor=''; });
      extraZone.addEventListener('drop', function(e) {
        e.preventDefault(); extraZone.style.borderColor='';
        if (!bkReg['extra']) bkReg['extra'] = [];
        Array.from(e.dataTransfer.files).forEach(function(f) {
          if (!bkReg['extra'].find(function(x) { return x.name===f.name && x.size===f.size; })) {
              if (bkCheckFileSize(f) && bkCheckFileLimit('extra')) bkReg['extra'].push(f);
          }
        });
        bkRenderList('extra','fl_extra');
      });
    }

});

function bkToggleGuia() {
  var el = document.querySelector('[name=tiene_guia]:checked');
  var v = el ? el.value : '';
  var guiaUpload = document.getElementById('bk-guia-upload');
  if(guiaUpload) {
    guiaUpload.style.display = (v === 'Sí, completa y en uso' || v === 'Sí, desactualizada') ? 'block' : 'none';
  }
}

function bkCheckFileSize(f) {
    var maxSize = 8 * 1024 * 1024; // 8MB
    if (f.size > maxSize) {
        bkShowError('El archivo ' + f.name + ' supera los 8MB.');
        return false;
    }
    return true;
}

function bkCheckFileLimit(key) {
    var limit = 5;
    if (bkReg[key] && bkReg[key].length >= limit) {
        bkShowError('No puedes adjuntar más de 5 archivos por campo.');
        return false;
    }
    return true;
}

function bkShowError(msg) {
    var banner = document.getElementById('bk-banner');
    if (banner) {
        document.getElementById('bk-banner-detail').textContent = ' ' + msg;
        banner.classList.add('show');
        banner.scrollIntoView({ behavior:'smooth', block:'start' });
    } else {
        alert(msg);
    }
}

function bkRegFiles(input, key, listId) {
  if (!bkReg[key]) bkReg[key] = [];
  var files = Array.from(input.files);
  for (var i = 0; i < files.length; i++) {
    var f = files[i];
    if (!bkReg[key].find(function(x) { return x.name === f.name && x.size === f.size; })) {
      if (bkCheckFileSize(f) && bkCheckFileLimit(key)) {
          bkReg[key].push(f);
      }
    }
  }
  bkRenderList(key, listId);
  input.value = '';
}

function bkRmFile(key, idx, listId) {
  if(bkReg[key]) {
      bkReg[key].splice(idx, 1);
      bkRenderList(key, listId);
  }
}

function bkRenderList(key, listId) {
  var el = document.getElementById(listId);
  if (!el) return;
  var html = '';
  var files = bkReg[key] || [];
  for (var i = 0; i < files.length; i++) {
    var f = files[i];
    var s = f.size > 1048576 ? (f.size/1048576).toFixed(1)+' MB' : (f.size/1024).toFixed(0)+' KB';
    html += '<div class="bk-file-item">' +
      '<span>📄 ' + f.name + ' <small style="color:#aaa">(' + s + ')</small></span>' +
      '<button type="button" class="bk-file-rm" data-key="' + key + '" data-idx="' + i + '" data-list="' + listId + '">✕</button>' +
      '</div>';
  }
  el.innerHTML = html;
}

function bkGv(id) {
  var el = document.getElementById(id);
  return el && el.value.trim() ? el.value.trim() : '';
}
function bkGr(name) {
  var el = document.querySelector('[name="'+name+'"]:checked');
  return el ? el.value : '';
}
function bkGchecked(prefix, allowed) {
  var checks = document.querySelectorAll('.bk-check.on');
  var vals = [];
  for (var i=0;i<checks.length;i++) {
    var key = checks[i].getAttribute('data-key');
    if (!key) continue;
    if (allowed) {
      if (allowed.indexOf(key) >= 0) vals.push(key.replace('_',' '));
    } else if (prefix && key.indexOf(prefix) === 0) {
      vals.push(key.slice(prefix.length).replace('_',' '));
    }
  }
  return vals.join(', ');
}
function bkGetUrls() {
  var canales = ['web','instagram','facebook','twitter','tiktok','youtube','email','crm','paid','whatsapp'];
  var lines = [];
  for (var i=0;i<canales.length;i++) {
    var k = canales[i];
    var check = document.querySelector('.bk-check[data-key="' + k + '"].on');
    if (check) {
      var inp = document.getElementById('url_'+k);
      if (inp && inp.value.trim()) lines.push(k + ': ' + inp.value.trim());
    }
  }
  return lines.join(' | ');
}

function bkValidate() {
  var errors = [];
  document.querySelectorAll('.bk-input.error,.bk-textarea.error').forEach(function(el) { el.classList.remove('error'); });
  document.querySelectorAll('.bk-label.error').forEach(function(el) { el.classList.remove('error'); });
  document.getElementById('bk-banner').classList.remove('show');

  var canalKeys = ['web','instagram','facebook','twitter','tiktok','youtube','email','crm','paid','whatsapp'];
  var anyCanal = false;
  for (var i=0;i<canalKeys.length;i++) {
    if (document.querySelector('.bk-check[data-key="' + canalKeys[i] + '"].on')) { anyCanal=true; break; }
  }
  if (!anyCanal) errors.push('Seleccioná al menos un canal activo (01)');

  if (!bkGr('tiene_guia')) { document.getElementById('lbl_problema') && document.getElementById('lbl_problema').classList.add('error'); errors.push('Indicá si tienen guía de marca (01)'); }

  var probEl = document.getElementById('problema_contenido');
  if (!bkGv('problema_contenido')) { if(probEl) probEl.classList.add('error'); errors.push('Describí el mayor problema de contenido (01)'); }

  var c1 = document.getElementById('comp1');
  if (!bkGv('comp1')) { if(c1) c1.classList.add('error'); if(document.getElementById('lbl_comp1')) document.getElementById('lbl_comp1').classList.add('error'); errors.push('Indicá al menos el competidor principal (02)'); }

  var datKeys = ['dat_crm','dat_analytics','dat_enc','dat_soporte','dat_nada'];
  var anyDat = false;
  for (var i=0;i<datKeys.length;i++) {
    if (document.querySelector('.bk-check[data-key="' + datKeys[i] + '"].on')) { anyDat=true; break; }
  }
  if (!anyDat) errors.push('Indicá qué datos de usuarios tienen (03)');

  var segEl = document.getElementById('descripcion_segmentos');
  if (!bkGv('descripcion_segmentos')) { if(segEl) segEl.classList.add('error'); if(document.getElementById('lbl_segmentos')) document.getElementById('lbl_segmentos').classList.add('error'); errors.push('Describí los segmentos de usuario (03)'); }

  if (!bkGr('acceso_canales')) { if(document.getElementById('lbl_acceso')) document.getElementById('lbl_acceso').classList.add('error'); errors.push('Indicá cómo dar acceso a los canales (04)'); }

  if (!bkGr('revision_legal')) { if(document.getElementById('lbl_legal')) document.getElementById('lbl_legal').classList.add('error'); errors.push('Indicá si legal revisa el contenido (05)'); }

  var nomEl = document.getElementById('contacto_nombre');
  if (!bkGv('contacto_nombre')) { if(nomEl) nomEl.classList.add('error'); if(document.getElementById('lbl_nombre')) document.getElementById('lbl_nombre').classList.add('error'); errors.push('Nombre del punto de contacto (06)'); }

  var emailEl = document.getElementById('contacto_email');
  if (!bkGv('contacto_email')) { if(emailEl) emailEl.classList.add('error'); if(document.getElementById('lbl_email')) document.getElementById('lbl_email').classList.add('error'); errors.push('Email del punto de contacto (06)'); }

  var presEl = document.getElementById('presentacion_asistentes');
  if (!bkGv('presentacion_asistentes')) { if(presEl) presEl.classList.add('error'); if(document.getElementById('lbl_asistentes')) document.getElementById('lbl_asistentes').classList.add('error'); errors.push('Quiénes estarán en la presentación ejecutiva (06)'); }

  return errors;
}

function bkSubmit() {
  if (document.getElementById('bk-btn').disabled) return;

  var errors = bkValidate();
  if (errors.length > 0) {
    var banner = document.getElementById('bk-banner');
    document.getElementById('bk-banner-detail').textContent = 'Falta completar: ' + errors.join(' · ');
    banner.classList.add('show');
    banner.scrollIntoView({ behavior:'smooth', block:'start' });
    return;
  }

  var nombre = bkGv('contacto_nombre');
  var email = bkGv('contacto_email');
  var now = new Date().toLocaleDateString('es-AR',{day:'2-digit',month:'long',year:'numeric'});
  var SEP = '================================================';

  var lines = [
    'BORREGA STUDIO - KICK-OFF ETAPA 1 - JUGABET',
    SEP,
    'Completado el ' + now,
    'Contacto: ' + nombre + ' <' + email + '>',
    '',
    SEP, '01 - AUDITORIA DE CONTENIDO', SEP,
    'Canales: ' + bkGchecked(null,['web','instagram','facebook','twitter','tiktok','youtube','email','crm','paid','whatsapp']),
    'URLs: ' + bkGetUrls(),
    'Quien maneja: ' + (bkGv('quien_maneja')||'—'),
    'Guia de marca: ' + (bkGr('tiene_guia')||'—'),
    'Mayor problema: ' + (bkGv('problema_contenido')||'—'),
    'Canal con mas inconsistencias: ' + (bkGv('canal_problema')||'—'),
    '',
    SEP, '02 - MARKET MAPPING', SEP,
    'Comp 1: '+(bkGv('comp1')||'—'), 'Comp 2: '+(bkGv('comp2')||'—'), 'Comp 3: '+(bkGv('comp3')||'—'),
    'Comp 4: '+(bkGv('comp4')||'—'), 'Comp 5: '+(bkGv('comp5')||'—'),
    'Referente: '+(bkGv('comp_referente')||'—'),
    'Preocupa: '+(bkGv('comp_preocupa')||'—'),
    'Mercados actuales: '+(bkGv('mercados_actuales')||'—'),
    'Mercados objetivo: '+(bkGv('mercados_objetivo')||'—'),
    '',
    SEP, '03 - BUYER PERSONAS', SEP,
    'Datos: ' + bkGchecked('dat_'),
    'Segmentos: '+(bkGv('descripcion_segmentos')||'—'),
    'Contacto por segmento: '+(bkGv('contacto_segmentos')||'—'),
    '',
    SEP, '04 - ACCESOS Y MATERIALES', SEP,
    'Acceso: '+(bkGr('acceso_canales')||'—'),
    'Materiales: ' + bkGchecked('mat_'),
    'Fecha accesos: '+(bkGv('fecha_accesos')||'—'),
    'Fecha materiales: '+(bkGv('fecha_materiales')||'—'),
    '',
    SEP, '05 - CONTEXTO REGULATORIO', SEP,
    'Restricciones: '+(bkGv('restricciones')||'—'),
    'Revision legal: '+(bkGr('revision_legal')||'—'),
    'Mercados con mas restricciones: '+(bkGv('mercados_restricciones')||'—'),
    '',
    SEP, '06 - COORDINACION', SEP,
    'Nombre: '+nombre, 'Cargo: '+(bkGv('contacto_cargo')||'—'),
    'Email: '+email, 'WhatsApp: '+(bkGv('contacto_whatsapp')||'—'),
    'Asistentes presentacion: '+(bkGv('presentacion_asistentes')||'—'),
    'Contexto adicional: '+(bkGv('contexto_adicional')||'—'),
    '',
    SEP,
    'Borrega Studio'
  ];
  var txt = lines.join('\n');

  // Download TXT
  var blob = new Blob([txt], {type:'text/plain;charset=utf-8'});
  var burl = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = burl; a.download = 'Kickoff-JugaBet-' + nombre.split(' ').join('-') + '.txt';
  document.body.appendChild(a); a.click(); document.body.removeChild(a);
  URL.revokeObjectURL(burl);

  // Send via WordPress AJAX 
  document.getElementById('bk-overlay').classList.add('show');
  document.getElementById('bk-btn').disabled = true;

  var fd = new FormData();
  fd.append('action', 'borrega_kickoff_send');
  fd.append('security', bkData.nonce); // Security nonce
  fd.append('subject', 'Kick-off Etapa 1 - JugaBet - ' + nombre);
  fd.append('message', txt);
  fd.append('reply_to', email);

  // Attach files
  var rk = Object.keys(bkReg);
  for (var ri=0; ri<rk.length; ri++) {
    var rfiles = bkReg[rk[ri]];
    for (var rfi=0; rfi<rfiles.length; rfi++) {
      fd.append('files[]', rfiles[rfi]);
    }
  }

  fetch(bkData.ajaxUrl, { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      document.getElementById('bk-overlay').classList.remove('show');
      if (j.success) {
        document.getElementById('bk-form').style.display='none';
        document.getElementById('bk-success').classList.add('show');
      } else {
        alert('Error al enviar: ' + (j.data||'desconocido') + '. El .txt fue descargado.');
        document.getElementById('bk-btn').disabled = false;
      }
    })
    .catch(function(err) {
      document.getElementById('bk-overlay').classList.remove('show');
      alert('Error de conexión.');
      document.getElementById('bk-btn').disabled = false;
    });
}
