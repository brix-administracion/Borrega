<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wp_scheduler_email_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$message = '';

	if ( isset( $_POST['wp_scheduler_email_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_scheduler_email_settings_nonce'] ) ), 'wp_scheduler_save_email_settings' ) ) {
		
		if ( isset( $_POST['wp_scheduler_email_template_confirmation'] ) ) {
			// Using wp_kses_post to allow standard HTML formatting safely
			$template = wp_kses_post( wp_unslash( $_POST['wp_scheduler_email_template_confirmation'] ) );
			update_option( 'wp_scheduler_email_template_confirmation', $template );
		}
		
		$message = '<div class="updated notice is-dismissible"><p>Plantilla guardada correctamente.</p></div>';
	}

	$current_template = get_option( 'wp_scheduler_email_template_confirmation', '' );

	// Default template if empty
	if ( empty( $current_template ) ) {
		$current_template = '<p>Hola {clientName}, tu reserva ha sido confirmada.</p>
<div>
	<strong>Fecha:</strong> {date}<br>
	<strong>Hora:</strong> {startTime} - {endTime}<br>
	<strong>Servicio:</strong> {serviceName}<br>
	<strong>Monto:</strong> {price}
</div>';
	}

	?>
	<div class="wrap">
		<h1>Configuración de Plantilla de Correo</h1>
		<?php echo wp_kses_post( $message ); ?>
		
		<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<h2>Correo de Confirmación de Reserva</h2>
			<p>Este es el correo que se enviará automáticamente al usuario cuando se confirme su reunión. Puedes usar las siguientes variables dinámicas en el texto:</p>
			
			<ul style="background: #f9f9f9; padding: 15px 20px; border-left: 4px solid #7c3aed; margin-bottom: 20px;">
				<li><code>{clientName}</code> - Nombre completo del cliente</li>
				<li><code>{serviceName}</code> - Nombre del servicio agendado</li>
				<li><code>{date}</code> - Fecha de la reunión</li>
				<li><code>{startTime}</code> - Hora de inicio</li>
				<li><code>{endTime}</code> - Hora de fin</li>
				<li><code>{price}</code> - Precio del servicio</li>
				<li><code>{meetLink}</code> - Enlace de Google Meet (si aplica)</li>
				<li><code>{cancelLink}</code> - Enlace para que el cliente pueda cancelar la reunión</li>
			</ul>

			<form method="post" action="">
				<?php wp_nonce_field( 'wp_scheduler_save_email_settings', 'wp_scheduler_email_settings_nonce' ); ?>
				
				<?php 
				$editor_id = 'wp_scheduler_email_template_confirmation';
				$settings = array(
					'textarea_name' => 'wp_scheduler_email_template_confirmation',
					'textarea_rows' => 15,
					'media_buttons' => false,
					'tinymce'       => array(
						'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,undo,redo',
					),
				);
				wp_editor( $current_template, $editor_id, $settings );
				?>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Guardar Plantilla">
				</p>
			</form>
		</div>
	</div>
	<?php
}
