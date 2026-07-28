<?php
/**
 * It is Main File to load all Notice and all
 *
 * @link       https://posimyth.com/
 * @since      2.1.15
 *
 * @package    OoohBoi Steroids for Elementor
 * */

/**
 * Exit if accessed directly.
 * */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ob_Notices_Main' ) ) {

	/**
	 * This class used for only load All Notice Files
	 *
	 * @since 2.1.15
	 */
	class Ob_Notices_Main {

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
		 * Ensures only one instance of the class is loaded or can be loaded.
		 *
		 * @since 2.1.15
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
			$this->ooohboi_notices_manage();
		}

		/**
		 * Initiate our hooks
		 *
		 * @since 2.1.15
		 */
		public function ooohboi_notices_manage() {

			$theplus_plugins = array(
				'name'        => 'the-plus-addons-for-elementor-page-builder',
				'status'      => '',
				'plugin_slug' => 'the-plus-addons-for-elementor-page-builder/theplus_elementor_addon.php',
			);

			$wdesignkit_plugins = array(
				'name'        => 'wdesignkit',
				'status'      => '',
				'plugin_slug' => 'wdesignkit/wdesignkit.php',
			);

            if ( current_user_can( 'install_plugins' ) ) {
				include OoohBoi_PATH . 'includes/notices/class-ob-wdkit-install-notice.php';

				$option_value = get_option( 'Ooohboi_Wdkit_Preview_Popup' );
				if ( empty( $option_value ) || 'yes' !== $option_value ) {	
					$theplus_details = $this->oohboi_check_plugins_depends( $theplus_plugins );
					$wdesignkit_details = $this->oohboi_check_plugins_depends( $wdesignkit_plugins );

					if( ( !empty( $theplus_details[0]['status'] ) && 'unavailable' == $theplus_details[0]['status'] ) && ( !empty( $wdesignkit_details[0]['status'] ) && 'unavailable' == $wdesignkit_details[0]['status'] ) ){
						include OoohBoi_PATH . 'includes/notices/class-ob-wdkit-preview-popup.php';
					}
				}
			}
		}

		/**
		 *
		 * It is Use for Check Plugin Dependency of template.
		 *
		 * @since 2.1.15
		 */
		public function oohboi_check_plugins_depends( $plugin ) {
			$update_plugin = array();

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			
			$all_plugins = get_plugins();

			$pluginslug = ! empty( $plugin['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $plugin['plugin_slug'] ) ) : '';

			if ( ! is_plugin_active( $pluginslug ) ) {
				if ( ! isset( $all_plugins[ $pluginslug ] ) ) {
						$plugin['status'] = 'unavailable';
				} else {
					$plugin['status'] = 'inactive';
				}

				$update_plugin[] = $plugin;
			} elseif ( is_plugin_active( $pluginslug ) ) {
				$plugin['status'] = 'active';
				$update_plugin[]  = $plugin;
			}

			return $update_plugin;
		}

	}

	Ob_Notices_Main::instance();
}