<?php
/**
 * Plugin Name: Borrega Kick-off Form
 * Description: Empaqueta el formulario de Kick-off mediante un shortcode [borrega_kickoff].
 * Version: 1.0.0
 * Author: Borrega Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// 1. Registro del Custom Post Type para respaldos
add_action('init', 'borrega_kickoff_register_cpt');
function borrega_kickoff_register_cpt() {
    register_post_type('kickoff_submission', array(
        'labels' => array(
            'name' => 'Kickoff Submissions',
            'singular_name' => 'Kickoff Submission'
        ),
        'public' => false,
        'show_ui' => true,
        'menu_position' => 20,
        'menu_icon' => 'dashicons-clipboard',
        'supports' => array('title', 'editor', 'custom-fields')
    ));
}

// 2. Shortcode HTML
add_shortcode('borrega_kickoff', 'borrega_kickoff_shortcode');
function borrega_kickoff_shortcode() {
    ob_start();
    ?>
<div class="bk-wrap">
  <div class="bk-intro">📋 Este formulario toma entre 10 y 15 minutos. Al enviarlo, la información llega a <strong>pilar@borregastudio.com</strong>.</div>

  <div id="bk-banner" class="bk-banner">
    <span>⚠️</span>
    <div class="bk-banner-text"><strong>Hay campos incompletos</strong><span id="bk-banner-detail"></span></div>
  </div>

  <div id="bk-form">

    <!-- S1 -->
    <div class="bk-section">
      <div class="bk-sec-title">🔍 <span>01 · Canales y contenido actual</span></div>

      <div class="bk-field">
        <label class="bk-label">Canales digitales activos <span class="bk-req">*</span></label>
        <span class="bk-hint">Seleccioná los que apliquen — podés ingresar la URL de cada uno</span>
        <div class="bk-checks">
          <div class="bk-check" data-key="web"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Sitio web / Landing pages</div></div><div class="bk-panel"><label class="bk-panel-label">URL</label><input type="url" id="url_web" class="bk-input" placeholder="https://"></div></div>
          <div class="bk-check" data-key="instagram"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Instagram</div></div><div class="bk-panel"><label class="bk-panel-label">URL del perfil</label><input type="url" id="url_instagram" class="bk-input" placeholder="https://instagram.com/"></div></div>
          <div class="bk-check" data-key="facebook"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Facebook</div></div><div class="bk-panel"><label class="bk-panel-label">URL de la página</label><input type="url" id="url_facebook" class="bk-input" placeholder="https://facebook.com/"></div></div>
          <div class="bk-check" data-key="twitter"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">X / Twitter</div></div><div class="bk-panel"><label class="bk-panel-label">URL del perfil</label><input type="url" id="url_twitter" class="bk-input" placeholder="https://x.com/"></div></div>
          <div class="bk-check" data-key="tiktok"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">TikTok</div></div><div class="bk-panel"><label class="bk-panel-label">URL del perfil</label><input type="url" id="url_tiktok" class="bk-input" placeholder="https://tiktok.com/@"></div></div>
          <div class="bk-check" data-key="youtube"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">YouTube</div></div><div class="bk-panel"><label class="bk-panel-label">URL del canal</label><input type="url" id="url_youtube" class="bk-input" placeholder="https://youtube.com/"></div></div>
          <div class="bk-check" data-key="email"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Email marketing / Newsletter</div></div><div class="bk-panel"><label class="bk-panel-label">Plataforma</label><input type="text" id="url_email" class="bk-input" placeholder="Ej: Klaviyo, Mailchimp"></div></div>
          <div class="bk-check" data-key="crm"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Comunicaciones CRM<small>Notificaciones, onboarding, retención</small></div></div><div class="bk-panel"><label class="bk-panel-label">Plataforma CRM</label><input type="text" id="url_crm" class="bk-input" placeholder="Ej: Salesforce, HubSpot"></div></div>
          <div class="bk-check" data-key="paid"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Paid media / Publicidad digital</div></div><div class="bk-panel"><label class="bk-panel-label">Plataformas</label><input type="text" id="url_paid" class="bk-input" placeholder="Ej: Meta Ads, Google Ads"></div></div>
          <div class="bk-check" data-key="whatsapp"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">WhatsApp / Mensajería directa</div></div><div class="bk-panel"><label class="bk-panel-label">Número o plataforma</label><input type="text" id="url_whatsapp" class="bk-input" placeholder="Ej: número / Twilio"></div></div>
        </div>
      </div>

      <div class="bk-field">
        <label class="bk-label">¿Quién maneja el contenido en cada canal?</label>
        <textarea id="quien_maneja" class="bk-textarea" placeholder="Ej: Instagram lo maneja una agencia. El email es interno..."></textarea>
      </div>

      <div class="bk-field">
        <label class="bk-label">¿Tienen alguna guía de marca existente? <span class="bk-req">*</span></label>
        <div class="bk-radios">
          <label class="bk-radio"><input type="radio" name="tiene_guia" value="Sí, completa y en uso"><span class="bk-pill">Sí, completa y en uso</span></label>
          <label class="bk-radio"><input type="radio" name="tiene_guia" value="Sí, desactualizada"><span class="bk-pill">Sí, pero desactualizada</span></label>
          <label class="bk-radio"><input type="radio" name="tiene_guia" value="Solo criterios informales"><span class="bk-pill">Solo criterios informales</span></label>
          <label class="bk-radio"><input type="radio" name="tiene_guia" value="No existe nada"><span class="bk-pill">No existe nada</span></label>
        </div>
        <div id="bk-guia-upload">
          <span class="bk-hint">Adjuntá el archivo — PDF, Word, PowerPoint, PNG, JPG</span>
          <div class="bk-upload" data-target="fi_guia">
            <input type="file" id="fi_guia" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.png,.jpg" style="display:none" class="bk-file-input" data-key="guia" data-list="fl_guia">
            <div class="bk-upload-text">📎 Click para adjuntar guía de marca</div>
          </div>
          <div class="bk-file-list" id="fl_guia"></div>
        </div>
      </div>

      <div class="bk-field">
        <label class="bk-label" id="lbl_problema">¿Cuál es el mayor problema con el contenido actual? <span class="bk-req">*</span></label>
        <textarea id="problema_contenido" class="bk-textarea" placeholder="Ej: Cada canal habla de una forma distinta..."></textarea>
      </div>

      <div class="bk-field">
        <label class="bk-label">¿Qué canal genera más inconsistencias?</label>
        <input type="text" id="canal_problema" class="bk-input" placeholder="Ej: Instagram vs. email son completamente distintos en tono">
      </div>
    </div>

    <!-- S2 -->
    <div class="bk-section">
      <div class="bk-sec-title">🗺️ <span>02 · Competidores y mercado</span></div>

      <div class="bk-field">
        <label class="bk-label" id="lbl_comp1">5 competidores más relevantes <span class="bk-req">*</span></label>
        <div class="bk-comp-rows">
          <div class="bk-comp-row"><span class="bk-comp-num">1</span><input type="text" id="comp1" class="bk-input" placeholder="Nombre + mercado principal"></div>
          <div class="bk-comp-row"><span class="bk-comp-num">2</span><input type="text" id="comp2" class="bk-input" placeholder="Nombre + mercado principal"></div>
          <div class="bk-comp-row"><span class="bk-comp-num">3</span><input type="text" id="comp3" class="bk-input" placeholder="Nombre + mercado principal"></div>
          <div class="bk-comp-row"><span class="bk-comp-num">4</span><input type="text" id="comp4" class="bk-input" placeholder="Nombre + mercado principal"></div>
          <div class="bk-comp-row"><span class="bk-comp-num">5</span><input type="text" id="comp5" class="bk-input" placeholder="Nombre + mercado principal"></div>
        </div>
      </div>

      <div class="bk-field">
        <label class="bk-label">¿Hay algún competidor referente?</label>
        <textarea id="comp_referente" class="bk-textarea" placeholder="Una marca que admiren en comunicación — ¿qué hacen bien?"></textarea>
      </div>

      <div class="bk-field">
        <label class="bk-label">¿Hay algún competidor que les preocupe?</label>
        <textarea id="comp_preocupa" class="bk-textarea" placeholder="El que está más cerca y más los afecta directamente..."></textarea>
      </div>

      <div class="bk-grid">
        <div class="bk-field">
          <label class="bk-label">Mercados actuales</label>
          <textarea id="mercados_actuales" class="bk-textarea" style="min-height:70px" placeholder="Ej: Chile, Argentina"></textarea>
        </div>
        <div class="bk-field">
          <label class="bk-label">Mercados objetivo (12 meses)</label>
          <textarea id="mercados_objetivo" class="bk-textarea" style="min-height:70px" placeholder="Ej: México, Colombia, Perú"></textarea>
        </div>
      </div>
    </div>

    <!-- S3 -->
    <div class="bk-section">
      <div class="bk-sec-title">👥 <span>03 · Audiencias y usuarios</span></div>

      <div class="bk-field">
        <label class="bk-label">¿Qué datos de usuarios tienen disponibles? <span class="bk-req">*</span></label>
        <span class="bk-hint">Seleccioná y adjuntá el archivo correspondiente</span>
        <div class="bk-checks">
          <div class="bk-check" data-key="dat_crm"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Base de datos CRM<small>Usuarios activos, inactivos, VIP</small></div></div><div class="bk-panel"><span class="bk-hint">Formatos: CSV, Excel (.xlsx, .xls), PDF</span><div class="bk-upload" data-target="fi_dat_crm"><input type="file" id="fi_dat_crm" multiple accept=".csv,.xlsx,.xls,.pdf" style="display:none" class="bk-file-input" data-key="dat_crm" data-list="fl_dat_crm"><div class="bk-upload-text">📊 Adjuntar export de CRM</div></div><div class="bk-file-list" id="fl_dat_crm"></div></div></div>
          <div class="bk-check" data-key="dat_analytics"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Analytics de plataforma<small>Google Analytics, Firebase, datos propios</small></div></div><div class="bk-panel"><span class="bk-hint">Formatos: CSV, Excel (.xlsx, .xls), PDF</span><div class="bk-upload" data-target="fi_dat_analytics"><input type="file" id="fi_dat_analytics" multiple accept=".csv,.xlsx,.xls,.pdf" style="display:none" class="bk-file-input" data-key="dat_analytics" data-list="fl_dat_analytics"><div class="bk-upload-text">📈 Adjuntar reporte de analytics</div></div><div class="bk-file-list" id="fl_dat_analytics"></div></div></div>
          <div class="bk-check" data-key="dat_enc"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Encuestas o estudios de usuario</div></div><div class="bk-panel"><span class="bk-hint">Formatos: PDF, Word (.docx), Excel (.xlsx), PowerPoint (.pptx)</span><div class="bk-upload" data-target="fi_dat_enc"><input type="file" id="fi_dat_enc" multiple accept=".pdf,.docx,.xlsx,.pptx" style="display:none" class="bk-file-input" data-key="dat_enc" data-list="fl_dat_enc"><div class="bk-upload-text">📋 Adjuntar encuestas o estudios</div></div><div class="bk-file-list" id="fl_dat_enc"></div></div></div>
          <div class="bk-check" data-key="dat_soporte"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Datos de soporte / atención al cliente<small>Motivos de consulta, quejas frecuentes</small></div></div><div class="bk-panel"><span class="bk-hint">Formatos: CSV, Excel (.xlsx, .xls), PDF, Word (.docx)</span><div class="bk-upload" data-target="fi_dat_soporte"><input type="file" id="fi_dat_soporte" multiple accept=".csv,.xlsx,.xls,.pdf,.docx" style="display:none" class="bk-file-input" data-key="dat_soporte" data-list="fl_dat_soporte"><div class="bk-upload-text">🎧 Adjuntar datos de soporte</div></div><div class="bk-file-list" id="fl_dat_soporte"></div></div></div>
          <div class="bk-check" data-key="dat_nada"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">No tenemos datos estructurados disponibles</div></div></div>
        </div>
      </div>

      <div class="bk-field">
        <label class="bk-label" id="lbl_segmentos">Descripción de cada segmento de usuario <span class="bk-req">*</span></label>
        <textarea id="descripcion_segmentos" class="bk-textarea" style="min-height:110px" placeholder="Ej: El segmento deportivo es mayormente hombre 25-40, activo en partidos del fin de semana..."></textarea>
      </div>

      <div class="bk-field">
        <label class="bk-label">¿Quién del equipo conoce mejor a cada segmento?</label>
        <textarea id="contacto_segmentos" class="bk-textarea" placeholder="Ej: Para deportivo hablar con Rodrigo (producto). Para VIP, con Sandra (CRM)..."></textarea>
      </div>
    </div>

    <!-- S4 -->
    <div class="bk-section">
      <div class="bk-sec-title">🔑 <span>04 · Accesos y materiales</span></div>

      <div class="bk-field">
        <label class="bk-label" id="lbl_acceso">¿Cómo nos dan acceso a sus canales? <span class="bk-req">*</span></label>
        <div class="bk-radios">
          <label class="bk-radio"><input type="radio" name="acceso_canales" value="Acceso de lectura directa"><span class="bk-pill">Acceso de lectura directa</span></label>
          <label class="bk-radio"><input type="radio" name="acceso_canales" value="Export / descarga"><span class="bk-pill">Export / descarga</span></label>
          <label class="bk-radio"><input type="radio" name="acceso_canales" value="Carpeta compartida (Drive/Dropbox)"><span class="bk-pill">Carpeta compartida</span></label>
          <label class="bk-radio"><input type="radio" name="acceso_canales" value="Lo coordinamos por separado"><span class="bk-pill">Lo coordinamos</span></label>
        </div>
      </div>

      <div class="bk-field">
        <label class="bk-label">¿Tienen materiales de marca para compartir?</label>
        <span class="bk-hint">Aunque estén incompletos — adjuntá lo que tengan</span>
        <div class="bk-checks">
          <div class="bk-check" data-key="mat_logo"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Logos y assets visuales</div></div><div class="bk-panel"><span class="bk-hint">Formatos: PNG, JPG, SVG, PDF, AI, ZIP</span><div class="bk-upload" data-target="fi_mat_logo"><input type="file" id="fi_mat_logo" multiple accept=".png,.jpg,.svg,.pdf,.ai,.zip" style="display:none" class="bk-file-input" data-key="mat_logo" data-list="fl_mat_logo"><div class="bk-upload-text">🎨 Adjuntar logos y assets</div></div><div class="bk-file-list" id="fl_mat_logo"></div></div></div>
          <div class="bk-check" data-key="mat_brand"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Brand book o manual de identidad</div></div><div class="bk-panel"><span class="bk-hint">Formatos: PDF, PowerPoint (.pptx), Word (.docx)</span><div class="bk-upload" data-target="fi_mat_brand"><input type="file" id="fi_mat_brand" multiple accept=".pdf,.pptx,.docx" style="display:none" class="bk-file-input" data-key="mat_brand" data-list="fl_mat_brand"><div class="bk-upload-text">📖 Adjuntar brand book</div></div><div class="bk-file-list" id="fl_mat_brand"></div></div></div>
          <div class="bk-check" data-key="mat_camp"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Campañas anteriores</div></div><div class="bk-panel"><span class="bk-hint">Formatos: PDF, PowerPoint (.pptx), Word (.docx), ZIP, JPG, PNG</span><div class="bk-upload" data-target="fi_mat_camp"><input type="file" id="fi_mat_camp" multiple accept=".pdf,.pptx,.docx,.zip,.jpg,.png" style="display:none" class="bk-file-input" data-key="mat_camp" data-list="fl_mat_camp"><div class="bk-upload-text">📣 Adjuntar campañas</div></div><div class="bk-file-list" id="fl_mat_camp"></div></div></div>
          <div class="bk-check" data-key="mat_est"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Documentos de estrategia</div></div><div class="bk-panel"><span class="bk-hint">Formatos: PDF, PowerPoint (.pptx), Word (.docx)</span><div class="bk-upload" data-target="fi_mat_est"><input type="file" id="fi_mat_est" multiple accept=".pdf,.pptx,.docx" style="display:none" class="bk-file-input" data-key="mat_est" data-list="fl_mat_est"><div class="bk-upload-text">🎯 Adjuntar estrategia</div></div><div class="bk-file-list" id="fl_mat_est"></div></div></div>
          <div class="bk-check" data-key="mat_comp"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">Análisis de competencia propio</div></div><div class="bk-panel"><span class="bk-hint">Formatos: PDF, PowerPoint (.pptx), Word (.docx), Excel (.xlsx)</span><div class="bk-upload" data-target="fi_mat_comp"><input type="file" id="fi_mat_comp" multiple accept=".pdf,.pptx,.docx,.xlsx" style="display:none" class="bk-file-input" data-key="mat_comp" data-list="fl_mat_comp"><div class="bk-upload-text">🗺️ Adjuntar análisis</div></div><div class="bk-file-list" id="fl_mat_comp"></div></div></div>
          <div class="bk-check" data-key="mat_nada"><div class="bk-check-head"><div class="bk-box"></div><div class="bk-check-label">No hay materiales organizados disponibles</div></div></div>
        </div>
      </div>

      <div class="bk-grid">
        <div class="bk-field">
          <label class="bk-label">Fecha límite para accesos</label>
          <input type="text" id="fecha_accesos" class="bk-input" value="Lunes 6 de julio">
        </div>
        <div class="bk-field">
          <label class="bk-label">Fecha límite para materiales</label>
          <input type="text" id="fecha_materiales" class="bk-input" value="Lunes 6 de julio">
        </div>
      </div>
    </div>

    <!-- S5 -->
    <div class="bk-section">
      <div class="bk-sec-title">⚖️ <span>05 · Contexto regulatorio</span></div>

      <div class="bk-field">
        <label class="bk-label">¿Tienen restricciones de comunicación frecuentes?</label>
        <textarea id="restricciones" class="bk-textarea" placeholder="Ej: No podemos usar 'ganá' ni 'ganancia garantizada'. Legal frena todo copy con probabilidades..."></textarea>
      </div>

      <div class="bk-field">
        <label class="bk-label" id="lbl_legal">¿El equipo legal revisa el contenido antes de publicar? <span class="bk-req">*</span></label>
        <div class="bk-radios">
          <label class="bk-radio"><input type="radio" name="revision_legal" value="Siempre, todo"><span class="bk-pill">Siempre, todo</span></label>
          <label class="bk-radio"><input type="radio" name="revision_legal" value="Solo campañas pagas"><span class="bk-pill">Solo campañas pagas</span></label>
          <label class="bk-radio"><input type="radio" name="revision_legal" value="Solo cuando hay dudas"><span class="bk-pill">Solo cuando hay dudas</span></label>
          <label class="bk-radio"><input type="radio" name="revision_legal" value="No hay proceso formal"><span class="bk-pill">No hay proceso formal</span></label>
        </div>
      </div>

      <div class="bk-field">
        <label class="bk-label">¿En qué mercados tienen restricciones más estrictas?</label>
        <input type="text" id="mercados_restricciones" class="bk-input" placeholder="Ej: Chile tiene más regulación. México permite más en publicidad de apuestas.">
      </div>
    </div>

    <!-- S6 -->
    <div class="bk-section">
      <div class="bk-sec-title">📋 <span>06 · Coordinación</span></div>

      <div class="bk-grid">
        <div class="bk-field">
          <label class="bk-label" id="lbl_nombre">Nombre del contacto principal <span class="bk-req">*</span></label>
          <input type="text" id="contacto_nombre" class="bk-input" placeholder="Nombre completo">
        </div>
        <div class="bk-field">
          <label class="bk-label">Cargo</label>
          <input type="text" id="contacto_cargo" class="bk-input" placeholder="Ej: Head of Marketing">
        </div>
      </div>
      <div class="bk-grid">
        <div class="bk-field">
          <label class="bk-label" id="lbl_email">Email <span class="bk-req">*</span></label>
          <input type="email" id="contacto_email" class="bk-input" placeholder="email@jugabet.com">
        </div>
        <div class="bk-field">
          <label class="bk-label">WhatsApp</label>
          <input type="text" id="contacto_whatsapp" class="bk-input" placeholder="+56 9 XXXX XXXX">
        </div>
      </div>

      <div class="bk-protocol">
        <div class="bk-protocol-title">Protocolo de comunicación del proyecto</div>
        <div class="bk-protocol-row"><span class="bk-protocol-ch">📧 Email</span> — Entrega y feedback de materiales</div>
        <div class="bk-protocol-row"><span class="bk-protocol-ch">💬 WhatsApp</span> — Consultas rápidas y coordinación del día a día</div>
        <div class="bk-protocol-row"><span class="bk-protocol-ch">📹 Google Meet</span> — Presentaciones y checkpoints</div>
      </div>

      <div class="bk-field">
        <label class="bk-label" id="lbl_asistentes">¿Quiénes estarán en la presentación ejecutiva de la semana 4? <span class="bk-req">*</span></label>
        <textarea id="presentacion_asistentes" class="bk-textarea" placeholder="Ej: Gabriel (Marketing), Sandra (CRM), el CEO..."></textarea>
      </div>

      <div class="bk-field">
        <label class="bk-label">¿Algo más que el equipo de Börrega deba saber?</label>
        <textarea id="contexto_adicional" class="bk-textarea" placeholder="Proyectos en paralelo, fechas críticas, campañas próximas..."></textarea>
      </div>

      <div class="bk-field">
        <label class="bk-label">Material adicional</label>
        <span class="bk-hint">Formatos: PDF, Word, PowerPoint, Excel, imágenes, ZIP</span>
        <div class="bk-upload" id="bk-extra-zone" data-target="fi_extra">
          <input type="file" id="fi_extra" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.csv" style="display:none" class="bk-file-input" data-key="extra" data-list="fl_extra">
          <div class="bk-upload-text">📁 Click para adjuntar o arrastrá archivos acá</div>
        </div>
        <div class="bk-file-list" id="fl_extra"></div>
      </div>
    </div>

    <button type="button" class="bk-submit" id="bk-btn">Enviar información de kick-off →</button>
    <p class="bk-sub-note">Se enviará a Börrega Studio · También se descarga un resumen en .txt</p>

  </div>

  <div id="bk-success" class="bk-success">
    <div class="bk-success-icon">✅</div>
    <div class="bk-success-title">¡Todo listo!</div>
    <p class="bk-success-desc">La información llegó a Börrega. El equipo arranca el trabajo esta semana.<br>Si tienen algo extra, escribinos.</p>
  </div>
</div>

<div class="bk-overlay" id="bk-overlay">
  <div class="bk-overlay-box">
    <div class="bk-spin"></div>
    <div class="bk-overlay-text">Enviando información…</div>
  </div>
</div>
    <?php
    return ob_get_clean();
}

// 3. Enqueue Assets Condicionalmente
add_action('wp_enqueue_scripts', 'borrega_kickoff_enqueue');
function borrega_kickoff_enqueue() {
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'borrega_kickoff') ) {
        wp_enqueue_style('borrega-kickoff-css', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), '1.0.0');
        wp_enqueue_script('borrega-kickoff-js', plugin_dir_url(__FILE__) . 'assets/js/script.js', array(), '1.0.0', true);
        
        wp_localize_script('borrega-kickoff-js', 'bkData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('borrega_kickoff_nonce')
        ));
    }
}

// 4. Recepción AJAX
add_action('wp_ajax_borrega_kickoff_send', 'borrega_kickoff_send');
add_action('wp_ajax_nopriv_borrega_kickoff_send', 'borrega_kickoff_send');

function borrega_kickoff_send() {
    // Verificar Nonce
    if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'borrega_kickoff_nonce') ) {
        wp_send_json_error('Invalid security token.');
    }

    // Verificar peso máximo global para evitar saturar el servidor (ej: 40MB total)
    $content_length = (int) $_SERVER['CONTENT_LENGTH'];
    if ($content_length > 40 * 1024 * 1024) {
        wp_send_json_error('El peso total de los archivos supera el límite permitido del servidor.');
    }

    $to = get_option('admin_email'); 
    $subject = sanitize_text_field($_POST['subject'] ?? 'Kick-off Submission');
    $message = wp_kses_post($_POST['message'] ?? '');
    $reply = sanitize_email($_POST['reply_to'] ?? '');

    $headers = array('Content-Type: text/plain; charset=UTF-8');
    if ($reply) {
        $headers[] = 'Reply-To: ' . $reply;
    }
// Manejar adjuntos
    $attachments = array();
    $nombres_adjuntos = array(); 
    $upload_dir = wp_upload_dir();
    $tmp_dir = $upload_dir['basedir'] . '/kickoff-tmp/';
    if (!file_exists($tmp_dir)) {
        wp_mkdir_p($tmp_dir);
    }

    if (!empty($_FILES['files']['name'][0])) {
        foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
            if (!empty($tmp) && is_uploaded_file($tmp)) {
                
                // Capturar y sanitizar el nombre original
                $nombre_original = sanitize_text_field($_FILES['files']['name'][$i]);
                $nombres_adjuntos[] = $nombre_original;

                $filename = sanitize_file_name($_FILES['files']['name'][$i]);
                $dest = $tmp_dir . uniqid() . '-' . $filename;
                if (move_uploaded_file($tmp, $dest)) {
                    $attachments[] = $dest;
                }
            }
        }
    }

    // Inyección en el cuerpo del correo
    if ( ! empty( $nombres_adjuntos ) ) {
        $message .= "\n\n================================================\n";
        $message .= "ARCHIVOS ADJUNTOS EN ESTE ENVÍO:\n";
        foreach ( $nombres_adjuntos as $nombre ) {
            $message .= "- " . $nombre . "\n";
        }
        $message .= "================================================\n";
    }

    // Persistencia: Guardar como Custom Post Type antes de enviar
    $post_id = wp_insert_post(array(
        'post_title' => $subject,
        'post_content' => $message,
        'post_status' => 'private',
        'post_type' => 'kickoff_submission'
    ));

    if ($post_id && $reply) {
        update_post_meta($post_id, '_reply_to', $reply);
    }

    // Enviar Email
    $sent = wp_mail($to, $subject, $message, $headers, $attachments);

    // Limpiar adjuntos temporales independientemente de si se envió o no
    foreach ($attachments as $file) {
        if(file_exists($file)){
            @unlink($file);
        }
    }

    if ($sent) {
        wp_send_json_success('Email enviado correctamente.');
    } else {
        wp_send_json_error('No se pudo enviar el email, pero se ha guardado una copia local.');
    }
}

// 5. Limpieza programada de adjuntos huérfanos (Cron Job)
if (!wp_next_scheduled('borrega_kickoff_daily_cleanup')) {
    wp_schedule_event(time(), 'daily', 'borrega_kickoff_daily_cleanup');
}
add_action('borrega_kickoff_daily_cleanup', 'borrega_kickoff_cleanup_tmp_dir');

function borrega_kickoff_cleanup_tmp_dir() {
    $upload_dir = wp_upload_dir();
    $tmp_dir = $upload_dir['basedir'] . '/kickoff-tmp/';
    if (is_dir($tmp_dir)) {
        $files = glob($tmp_dir . '*');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file)) {
                // Borrar archivos de más de 24 horas
                if ($now - filemtime($file) >= 60 * 60 * 24) {
                    @unlink($file);
                }
            }
        }
    }
}
