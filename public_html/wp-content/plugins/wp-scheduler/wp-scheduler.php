<?php
/**
 * Plugin Name: WP Scheduler
 * Description: Native scheduling and booking plugin with Stripe, MercadoPago, Google Calendar and email notifications.
 * Version:     1.0.0
 * Author:      Octavio Mastrangelo
 * Text Domain: wp-scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define Plugin Constants
define( 'WP_SCHEDULER_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_SCHEDULER_URL', plugin_dir_url( __FILE__ ) );

// Load components
require_once WP_SCHEDULER_PATH . 'includes/database.php';
require_once WP_SCHEDULER_PATH . 'includes/google-calendar.php';
require_once WP_SCHEDULER_PATH . 'includes/email.php';
require_once WP_SCHEDULER_PATH . 'includes/api.php';
require_once WP_SCHEDULER_PATH . 'includes/admin-email-settings.php';

// Activation Hooks
register_activation_hook( __FILE__, 'wp_scheduler_activate_plugin' );
function wp_scheduler_activate_plugin() {
	wp_scheduler_create_tables();
}

// Register shortcode for frontend widget
add_shortcode( 'wp_scheduler_booking', 'wp_scheduler_booking_shortcode' );
function wp_scheduler_booking_shortcode() {
	wp_enqueue_script( 'wp-scheduler-frontend', WP_SCHEDULER_URL . 'assets/booking-widget-vanilla.js', array(), '1.0.0', true );
	wp_enqueue_style( 'wp-scheduler-frontend', WP_SCHEDULER_URL . 'assets/booking-widget-vanilla.css', array(), '1.0.0' );

	// Localize script to pass the REST URL and security nonce
	wp_localize_script( 'wp-scheduler-frontend', 'wpSchedulerSettings', array(
		'restUrl' => esc_url_raw( rest_url( 'scheduler/v1' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	) );

	ob_start();
	?>
    <div class="pilar-scheduler-container" id="wp-scheduler-booking-widget">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=SF+Pro+Display:wght@400;500;600&display=swap" rel="stylesheet">
        
        <div class="wrapper">
            <div class="progress-bar">
                <div class="progress-fill" id="progress"></div>
            </div>
            
            <div class="content">
                <div class="logo-container">
                    <svg viewBox="0 0 255.53 70.78" xmlns="http://www.w3.org/2000/svg">
                        <g>
                            <path fill="#ffffff" d="M8.42,1.29v18.84c2-3.52,7.59-5.45,11.18-5.45,9.94,0,17.32,6.07,17.32,17.8s-7.52,17.8-17.53,17.8c-4.14,0-8.35-1.38-10.97-5.45l-.55,4.69H0V1.29h8.42ZM8.97,32.48c0,6.14,4.55,10.01,9.87,10.01s9.66-4.07,9.66-10.01-4.28-9.94-9.66-9.94-9.87,4-9.87,9.94Z"/>
                            <path fill="#ffffff" d="M59.14,14.82c10.83,0,17.8,8,17.8,17.73s-6.69,17.73-17.73,17.73-17.67-7.93-17.67-17.73,6.76-17.73,17.6-17.73ZM47.27,4.81c0,6.35,9.66,6.35,9.66,0s-9.66-6.42-9.66,0ZM59.21,22.48c-6.07,0-9.25,4.97-9.25,10.08s3.11,10.01,9.25,10.01,9.25-4.83,9.25-10.01-3.59-10.08-9.25-10.08ZM61.48,4.81c0,6.35,9.66,6.35,9.66,0s-9.66-6.42-9.66,0Z"/>
                            <path fill="#ffffff" d="M90.53,15.51l.62,3.93c2.62-4.21,6.14-4.83,9.59-4.83s6.9,1.38,8.76,3.24l-3.79,7.32c-1.73-1.45-3.31-2.21-6.07-2.21-4.42,0-8.49,2.35-8.49,8.63v17.94h-8.42V15.51h7.8Z"/>
                            <path fill="#ffffff" d="M120.48,15.51l.62,3.93c2.62-4.21,6.14-4.83,9.59-4.83s6.9,1.38,8.76,3.24l-3.79,7.32c-1.72-1.45-3.31-2.21-6.07-2.21-4.42,0-8.49,2.35-8.49,8.63v17.94h-8.42V15.51h7.8Z"/>
                            <path fill="#ffffff" d="M149.81,35.52c.55,4.21,4.21,7.25,10.14,7.25,3.11,0,7.18-1.17,9.11-3.17l5.38,5.31c-3.59,3.73-9.45,5.52-14.63,5.52-11.73,0-18.7-7.25-18.7-18.15s7.04-17.8,18.08-17.8,18.49,7.04,17.18,21.05h-26.57ZM168.3,28.55c-.55-4.42-4-6.62-8.83-6.62-4.55,0-8.28,2.21-9.52,6.62h18.36Z"/>
                            <path fill="#ffffff" d="M209.57,12.06l6.21,4.69-3.8,4.83c2.62,2.97,3.59,6.35,3.59,10.07,0,4.21-1.59,10.14-7.18,12.7,5.66,2.83,7.04,6.9,7.04,11.25,0,9.38-7.18,15.18-17.04,15.18s-17.25-6-17.25-15.18h8.35c0,4.42,4.07,7.31,8.9,7.31s8.62-2.62,8.62-7.31-4.42-6.83-8.62-6.83c-10.63,0-17.25-6.49-17.25-17.11s7.73-17.25,17.25-17.25c2.69,0,5.45.35,7.8,1.93l3.38-4.28ZM189.49,31.66c0,5.93,4,9.45,8.9,9.45s8.83-3.59,8.83-9.45-4-9.59-8.83-9.59-8.9,3.66-8.9,9.59Z"/>
                            <path fill="#ffffff" d="M247.18,20.68l.28-4.69h8.07v34.02h-7.94l-.41-4.97c-1.93,4-7.25,5.93-11.04,6-10.08.07-17.53-6.14-17.53-18.08s7.8-17.87,17.73-17.8c4.55,0,8.9,2.14,10.83,5.52ZM237.1,22.69c-5.59,0-10.07,3.79-10.07,10.28s4.49,10.35,10.07,10.35c13.25,0,13.25-20.63,0-20.63Z"/>
                        </g>
                    </svg>
                </div>
                
                <div class="header">
                    <h1 id="service-title">Cargando...</h1>
                    <p class="subtitle" id="service-subtitle"></p>
                    <div class="description" id="service-description">
                        Sabemos que el éxito de tu empresa depende de decisiones estratégicas. En Börrega, estamos muy contentos de dar este primer paso contigo. Una reunión de 30 minutos puede marcar la diferencia para potenciar tu marca, producto o proyecto. Adelante, ¡reserva tu espacio!
                    </div>
                </div>
                
                <div class="step-indicator" id="steps">
                    <div class="step active" id="step1">
                        <div class="step-num">1</div>
                        <span>Calendario</span>
                    </div>
                    <div class="step" id="step2">
                        <div class="step-num">2</div>
                        <span>Horario</span>
                    </div>
                    <div class="step" id="step3">
                        <div class="step-num">3</div>
                        <span>Datos</span>
                    </div>
                </div>
                
                <div id="calendar-view">
                    <div class="calendar-header">
                        <button id="prev-month-btn" aria-label="Anterior">← Anterior</button>
                        <h2 id="month-year"></h2>
                        <button id="next-month-btn" aria-label="Siguiente">Siguiente →</button>
                    </div>
                    <div class="calendar-grid" id="calendar-grid"></div>
                </div>
                
                <div id="week-view" class="hidden">
                    <button class="btn-back" id="back-to-calendar-btn">← Volver a calendario</button>
                    <h2 style="margin-bottom: 20px;">Semana del <span id="week-label"></span></h2>
                    <div class="week-grid" id="week-grid"></div>
                </div>
                
                <div id="form-view" class="hidden">
                    <button class="btn-back" id="back-to-week-btn">← Volver</button>
                    <div class="selected-info">
                        <strong>📅 Reunión:</strong> <span id="slot-info"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="pilar-name">Tu nombre *</label>
                        <input type="text" id="pilar-name" placeholder="Juan García" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="pilar-email">Tu email *</label>
                        <input type="email" id="pilar-email" placeholder="juan@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="pilar-subject">Asunto de la reunión *</label>
                        <input type="text" id="pilar-subject" placeholder="Estrategia de branding" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="pilar-notes">Notas (opcional)</label>
                        <textarea id="pilar-notes" placeholder="Cuéntanos más sobre tu proyecto..."></textarea>
                    </div>
                    
                    <button class="btn btn-primary" id="confirm-btn">Confirmar reunión</button>
                </div>
                
                <div id="success-view" class="hidden">
                    <div class="success">
                        <div class="success-icon">✓</div>
                        <h2>¡Reunión agendada!</h2>
                        <p id="success-message">Te hemos redirigido a la confirmación de pago o éxito.</p>
                        <button class="btn btn-secondary" id="book-another-btn">Agendar otra</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
	<?php
	return ob_get_clean();
}

// Register Admin Menu Page
add_action( 'admin_menu', 'wp_scheduler_add_admin_menu' );
function wp_scheduler_add_admin_menu() {
	add_menu_page(
		__( 'Scheduler', 'wp-scheduler' ),
		__( 'Scheduler', 'wp-scheduler' ),
		'manage_options',
		'wp-scheduler',
		'wp_scheduler_admin_page',
		'dashicons-calendar-alt',
		30
	);

	add_submenu_page(
		'wp-scheduler',
		__( 'Plantillas de Correo', 'wp-scheduler' ),
		__( 'Plantillas de Correo', 'wp-scheduler' ),
		'manage_options',
		'wp-scheduler-email',
		'wp_scheduler_email_settings_page'
	);
}

function wp_scheduler_admin_page() {
	echo '<div id="wp-scheduler-admin-panel"></div>';
}

add_action( 'admin_enqueue_scripts', 'wp_scheduler_admin_assets' );
function wp_scheduler_admin_assets( $hook ) {
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wp-scheduler' ) {
		return;
	}

	$version = time();
	
	// Enqueue assets for React Admin Panel
	wp_enqueue_script( 'wp-scheduler-admin', WP_SCHEDULER_URL . 'assets/admin-panel.js', array(), $version, true );
	wp_enqueue_style( 'wp-scheduler-admin', WP_SCHEDULER_URL . 'assets/admin-panel.css', array(), $version );

	// Localize settings
	wp_localize_script( 'wp-scheduler-admin', 'wpSchedulerSettings', array(
		'restUrl' => esc_url_raw( rest_url( 'scheduler/v1' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	) );
}
