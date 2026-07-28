<?php
/**
 * It is Main File to load all Notice, Upgrade Menu and all
 *
 * @link       https://posimyth.com/
 * @since      2.1.15
 *
 * @package    Ooohboi
 * @subpackage Ooohboi/Notices
 * */

/**
 * Exit if accessed directly.
 * */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ob_Wdkit_Preview_Popup' ) ) {

	/**
	 * This class used for Wdesign-kit releted
	 *
	 * @since 2.1.15
	 */
	class Ob_Wdkit_Preview_Popup {

		/**
		 * Instance
		 *
		 * @since 2.1.15
		 * @var instance of the class.
		 */
		private static $instance = null;

		/**
		 * Instance
		 *
		 * @since 2.1.15
		 * @var w_d_s_i_g_n_k_i_t_slug
		 */
		public $w_d_s_i_g_n_k_i_t_slug = 'wdesignkit/wdesignkit.php';

		/**
		 * It is store wp_options table with name Ooohboi_Wdkit_Preview_Popup
		 *
		 * @since 2.1.15
		 * @var db_preview_popup_key
		 */
		public $db_preview_popup_key = 'Ooohboi_Wdkit_Preview_Popup';

		/**
		 * Instance
		 *
		 * Ensures only one instance of the class is loaded or can be loaded.
		 *
		 * @since 2.1.15
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
		 * @since 2.1.15
		 */
		public function __construct() {

			if ( class_exists( '\Elementor\Plugin' ) ) {
				add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'wdkit_elementor_editor_sripts' ) );
				add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'wdkit_elementor_editor_style' ) );
			}

			add_action( 'elementor/preview/enqueue_styles', array( $this, 'wdkit_elementor_preview_style' ) );
			add_action( 'wp_ajax_ob_install_wdkit', array( $this, 'ob_install_wdkit' ) );

			add_action( 'wp_ajax_ob_dont_show_again', array( $this, 'ob_dont_show_again' ) );
			add_action( 'wp_ajax_nopriv_ob_dont_show_again', array( $this, 'ob_dont_show_again' ) );

			add_action( 'elementor/editor/footer', array( $this, 'tp_wdkit_preview_html_popup' ) );
		}

		/**
		 * Loded Wdesignkit Template Logo CSS
		 *
		 * @since 2.1.15
		 */
		public function wdkit_elementor_preview_style() {
			wp_enqueue_style( 'ooohboi-wdkit-elementor-editor-css', OoohBoi_URL . 'assets/wdesignkit/ooohboi-wdkit-logo.css', array(), OoohBoi_VERSION );
		}

		/**
		 * Loded Wdesignkit Template Js
		 *
		 * @since 2.1.15
		 */
		public function wdkit_elementor_editor_sripts() {

			wp_enqueue_script( 'ooohboi-wdkit-preview-popup', OoohBoi_URL . 'assets/wdesignkit/ooohboi-wdkit-preview-popup.js', array( 'jquery', 'wp-i18n' ), OoohBoi_VERSION, true );

			wp_localize_script(
				'ooohboi-wdkit-preview-popup',
				'Ooohboi_Wdkit_Preview_Popup',
				array(
					'nonce'    => wp_create_nonce( 'Ooohboi_Wdkit_Preview_Popup' ),
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				)
			);
		}

		/**
		 * Loded Wdesignkit Template CSS
		 *
		 * @since 2.1.15
		 */
		public function wdkit_elementor_editor_style() {
			wp_enqueue_style( 'ooohboi-wdkit-elementor-popup', OoohBoi_URL . 'assets/wdesignkit/ooohboi-wdkit-preview-popup.css', array(), OoohBoi_VERSION );
		}

		/**
		 * Install Wdesign kit
		 *
		 * @since 2.1.15
		 */
		public function ob_install_wdkit() {

			check_ajax_referer( 'Ooohboi_Wdkit_Preview_Popup', 'security' );

			if ( ! current_user_can( 'install_plugins' ) ) {
				$response = $this->tp_response('Invalid Permission.', 'Something went wrong.', false );

				wp_send_json( $response );
				wp_die();
			}

			$installed_plugins = get_plugins();

			include_once ABSPATH . 'wp-admin/includes/file.php';
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			include_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
			include_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

			$result   = array();
			$response = wp_remote_post(
				'http://api.wordpress.org/plugins/info/1.0/',
				array(
					'body' => array(
						'action'  => 'plugin_information',
						'request' => serialize(
							(object) array(
								'slug'   => 'wdesignkit',
								'fields' => array(
									'version' => false,
								),
							)
						),
					),
				)
			);

			$plugin_info = unserialize( wp_remote_retrieve_body( $response ) );

			if ( ! $plugin_info ) {
				wp_send_json_error( array( 'content' => __( 'Failed to retrieve plugin information.', 'ooohboi-steroids' ) ) );
			}

			$skin     = new \Automatic_Upgrader_Skin();
			$upgrader = new \Plugin_Upgrader( $skin );

			$plugin_basename = $this->w_d_s_i_g_n_k_i_t_slug;

			if ( ! isset( $installed_plugins[ $plugin_basename ] ) && empty( $installed_plugins[ $plugin_basename ] ) ) {

				$installed         = $upgrader->install( $plugin_info->download_link );
				$activation_result = activate_plugin( $plugin_basename );

				$success = null === $activation_result;
				$result  = $this->tp_response( 'Success Install WDesignKit', 'Success Install WDesignKit', $success, '' );

			} elseif ( isset( $installed_plugins[ $plugin_basename ] ) ) {

				$activation_result = activate_plugin( $plugin_basename );

				$success = null === $activation_result;
				$result  = $this->tp_response( 'Success Install WDesignKit', 'Success Install WDesignKit', $success, '' );

			}

			wp_send_json( $result );
		}

		/**
		 * Close Popup Permanently
		 *
		 * @since 2.1.15
		 */
		public function ob_dont_show_again() {

			check_ajax_referer( 'Ooohboi_Wdkit_Preview_Popup', 'security' );

			$option_value = get_option( $this->db_preview_popup_key );
			if ( ! empty( $option_value ) && 'yes' === $option_value ) {
				update_option( $this->db_preview_popup_key, 'yes' );
			} else {
				add_option( $this->db_preview_popup_key, 'yes' );
			}

			$result = $this->tp_response( 'Success Install WDesignKit', 'Success Install WDesignKit', true, '' );

			wp_send_json( $result );
		}

		/**
		 * Check plugin status
		 *
		 * @since 2.1.15
		 * @return array
		 */
		private function check_plugin_status() {

			$installed_plugins = get_plugins();

			$plugin_page_url = add_query_arg( array( 'page' => 'wdesign-kit' ), admin_url( 'admin.php' ) );

			$installed = false;
			if ( is_plugin_active( $this->w_d_s_i_g_n_k_i_t_slug ) || isset( $installed_plugins[ $this->w_d_s_i_g_n_k_i_t_slug ] ) ) {
				$installed = true;
			}

			return array(
				'installed'       => $installed,
				'plugin_page_url' => $plugin_page_url,
			);
		}

		/**
		 * It is WDesignKit Popup Design for Download and install
		 *
		 * @since 2.1.15
		 */
		public function tp_wdkit_preview_html_popup() {
			$plugin_status = $this->check_plugin_status(); ?>
			
			<div id="ob-wdkit-wrap" class="ob-main-container" style="display: none">
				<div class="ob-top-sections">
					<div class="ob-message">
						<a class="ob-not-show-again" href="#"><?php echo esc_html__( 'Don’t Show Again', 'ooohboi-steroids' ); ?></a>
					</div>
					<div class="ob-close-btn">
						<i class="eps-icon eicon-close" aria-hidden="true"></i>
					</div>
				</div>
				<div class="ob-middel-sections">
					<div class="ob-text-top">
						<?php echo esc_html__( 'Get 1000+ Predesigned ', 'ooohboi-steroids' ); ?>
						<br>
						<?php echo esc_html__( 'Elementor Templates & Sections', 'ooohboi-steroids' ); ?>
					</div>
					<div class="ob-text-bottom">
						<?php echo esc_html__( 'Uniquely designed Elementor Templates for every website type made ', 'ooohboi-steroids' ); ?>
						<br>
						<?php echo esc_html__( 'with Elementor & The Plus Addons for Elementor Widgets.', 'ooohboi-steroids' ); ?>
					</div>
					<div class="ob-learn-more-about">
						<?php if ( false === $plugin_status['installed'] ) { ?>
							<a class="ob-wdesign-install" href="#">
								<span class="ob-enable-text"><?php echo esc_html__( 'Enable Templates', 'ooohboi-steroids' ); ?></span>
								<div class="ob-wkit-publish-loader">
									<div class="ob-wb-loader-circle"></div>
								</div>
							</a>
						<?php } else { ?>
							<a class="ob-wdesign-install" href="#"><span class="ob-visit-plugin"><?php echo esc_html__( 'Visit Plugin', 'ooohboi-steroids' ); ?></span></a>
						<?php } ?>
							<a class="ob-wdesign-about" target="_blank" href="https://wdesignkit.com/templates?temp_type_req=pagetemplate&builder_req=1001"><?php echo esc_html__( 'Learn More', 'ooohboi-steroids' ); ?></a>
					</div>
				</div>
				<div class="ob-image-sections"></div>
			</div> 
			<?php
		}

		/**
		 * Response
		 *
		 * @param string  $message pass message.
		 * @param string  $description pass message.
		 * @param boolean $success pass message.
		 * @param string  $data pass message.
		 *
		 * @since 2.1.15
		 */
		public function tp_response( $message = '', $description = '', $success = false, $data = '' ) {
			return array(
				'message'     => $message,
				'description' => $description,
				'success'     => $success,
				'data'        => $data,
			);
		}
	}

	Ob_Wdkit_Preview_Popup::instance();
}
