<?php
/**
 * Exit if accessed directly.
 *
 * */

/**
 * Exit if accessed directly.
 * */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ob_Wdkit_Install_Notice' ) ) {

	/**
	 * This class used for only elementor widget load
	 *
	 * @since 2.1.15
	 */
	class Ob_Wdkit_Install_Notice {

		/**
		 * Instance
		 *
		 * @since 2.1.15
		 * @access private
		 * @static
		 * @var instance of the class.
		 */
		private static $instance = null;

		/**
		 * Instance
		 *
		 * @access public
		 * @var w_d_s_i_g_n_k_i_t_slug
		 */
		public $w_d_s_i_g_n_k_i_t_slug = 'wdesignkit/wdesignkit.php';

		/**
		 * Instance
		 *
		 * Ensures only one instance of the class is loaded or can be loaded.
		 *
		 * @access public
		 * @static
		 * @return instance of the class.
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * Perform some compatibility checks to make sure basic requirements are meet.
		 *
		 * @since 5.2.3
		 * @access public
		 */
		public function __construct() {			
			/**Install Notice*/
			add_action( 'admin_notices', array( $this, 'wdesignkit_notice_install_plugin' ) );

			/** Close Notice*/
			add_action( 'wp_ajax_wdesignkit_dismiss_noticee', array( $this, 'wdesignkit_dismiss_notice' ) );
		}

		/**
		 * Plugin Active Notice Installing Notice show
		 *
		 * @since 2.1.15
		 */

        public function wdesignkit_notice_install_plugin() {
			
            $installed_plugins = get_plugins();

			$file_path   = $this->w_d_s_i_g_n_k_i_t_slug;
            $screen      = get_current_screen();
			$nonce       = wp_create_nonce( 'ooohboi_plugin_notice' );
			$ob_ajaxurl  = admin_url('admin-ajax.php');
            $pt_exclude  = ! empty( $screen->post_type ) && in_array( $screen->post_type, array( 'elementor_library', 'product' ), true );
			$parent_base = ! empty( $screen->parent_base ) && in_array( $screen->parent_base, array( 'edit', 'plugins' , 'steroids_for_elementor' ), true );
			$get_action  = ! empty( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';	

            if ( ! $parent_base || $pt_exclude ) {
				return;
			}

            if ( is_plugin_active( $file_path ) || isset( $installed_plugins[ $file_path ] ) ) {
				return;
			}

            $install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=wdesignkit' ), 'install-plugin_wdesignkit' );

			if ( ! get_option( 'ooohboi_dismissed_notice' ) ) {
				echo '<div class="notice ob-wdesignkit-notice ob-notice-show is-dismissible" style="margin-left:0px;border-left: 4px solid #c22076;">
						<div class="inline ob-install-wkit" style="display: flex; column-gap: 12px; align-items: center; padding: 15px 10px; position: relative; margin-left: 0px;">
							<img style="max-width: 120px; max-height: 120px; border-radius: 10px;" src="' . esc_url( OoohBoi_URL . 'assets/img/products/wdeisngkit-notice-logo.png' ) . '" />
							<div style="margin: 0 10px; color: #000">  
								<h3 style="margin: 10px 0px 7px;">' . esc_html__( 'WDesignKit – Elementor Templates & Widget Builder', 'ooohboi-steroids' ) . '</h3>
								<p> ' . esc_html__( 'WDesignKit – The Ultimate Design & Collaboration Tool for WordPress! 🚀', 'ooohboi-steroids' ) . ' </p>
								<p style="display: flex;column-gap: 12px;">  <span> • ' . esc_html__( '1000+ Elementor-Compatible Templates', 'ooohboi-steroids' ) . '</span>  <span> • ' . esc_html__( 'Create Custom Widgets Without Coding', 'ooohboi-steroids' ) . '</span>  <span> • ' . esc_html__( 'Streamline Your Workflow Effortlessly', 'ooohboi-steroids' ) . '</span> </p>
								<a href="' . esc_url( $install_url ) . '" class="button button-primary">' . esc_html__( 'Install WdesignKit', 'ooohboi-steroids' ) . '</a>
							</div>
						</div>
					</div>';
			}

            ?>
			<script>
				jQuery(document).on('click', '.ob-wdesignkit-notice .notice-dismiss', function(e) {
					e.preventDefault();
					
					jQuery.ajax({
						url: "<?php echo esc_url( $ob_ajaxurl ); ?>",
						type: 'POST',
						data: {
							action: 'wdesignkit_dismiss_noticee',
							security: "<?php echo esc_html( $nonce ); ?>",
						},
						success: function(response) {
							jQuery('.ob-wdesignkit-notice').hide();
						}
					});
				});
			</script>
			<?php
        }
 
		/**
		 * It's is use for Save key in database.
		 *
		 * @since 2.1.15
		 */
        public function wdesignkit_dismiss_notice() {
			$get_security = ! empty( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		
			if ( ! $get_security || ! wp_verify_nonce( $get_security, 'ooohboi_plugin_notice' ) ) {
				wp_send_json_error( 'Security check failed!' );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ooohboi-steroids' ) );
			}

			update_option( 'ooohboi_dismissed_notice', true );
			
			wp_send_json_success();
		}
	}

	Ob_Wdkit_Install_Notice::instance();
}