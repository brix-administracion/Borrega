<?php
/**
 * It is Main File for the notices
 *
 * */

/**
 * Exit if accessed directly.
 * */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Class_Ob_More_Products' ) ) {

	/**
	 * This class used for only load All Notice Files
	 *
	 * @since 2.1.15
	 */
	class Class_Ob_More_Products {

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
			$this->more_products();
		}

        public function more_products() {
            $wkit_install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=wdesignkit' ), 'install-plugin_wdesignkit' );

			$tpae_slug = 'the-plus-addons-for-elementor-page-builder';
            $tpae_install_url = wp_nonce_url( self_admin_url( "update.php?action=install-plugin&plugin={$tpae_slug}" ), "install-plugin_{$tpae_slug}" );

            $output = ' <style> .exopite-sof-sections{background: #f7f7f7 !important;} .exopite-sof-section.exopite-sof-section-our_products{ height: 100vh; } </style>';
            
            $output .= '<div class="ob_products_box_cover_main" style="margin: 25px;">';
                $output .= '<div class="ooohboi_product_box" style="position: relative; width: 100%; display: flex; justify-content: space-between; box-sizing: border-box; padding: 30px; background-color: #ffffff; border-radius: 8px;">';
                    $output .= '<div class="ooohboi_product_logo_title_cover" style="display: flex; justify-content: flex-start; align-items: center; gap: 15px;">';
                        $output .= '<div class="ooohboi_product_logo" style="position: relative; width: 60px; height: 60px;">';
                            $output .= '<img src="' . esc_url( OoohBoi_URL . 'assets/img/products/wdesignkit-product-logo.png' ) . ' " style="position: relative; width: 100%; height: 100%; ">';
                        $output .= '</div>';
                        $output .= '<h4 class="ooohboi_product_name tpae-in-sec-heading" style="font-size: 16px;"> WDesignKit – Templates, Widgets, Cloud Storage & More </h4>';
                    $output .= '</div>';
                    $output .= '<div class="ooohboi-installation" style="display: flex; justify-content: flex-end; align-items: center; gap: 10px;">';
                    
                        $checkplugin = $this->check_plugin('wkit', 'wdesignkit/wdesignkit.php');

                        if ( $checkplugin ) {
                            $output .= '<button class="ob-install-plugin" style="color: #14c38e; border-color: #14c38e; pointer-events: none; display: flex; min-width: 160px; gap: 5px; justify-content: center; align-items: center; font-size: 14px; font-weight: 500; background-color: transparent; padding: 11px 25px; border-radius: 5px; white-space: nowrap;  transition: 0.3slinear; text-decoration: none;"> Activated </button>';
                        } else {
                            $output .= '<a href="' . esc_url( $wkit_install_url ) . '" class="ob-install-plugin" style="display: flex; min-width: 160px; gap: 5px; justify-content: center; align-items: center; font-size: 14px; font-weight: 500; color: #8072fc; background-color: transparent; padding: 11px 25px; border: 1px solid #8072fc; border-radius: 5px; white-space: nowrap; cursor: pointer; transition: 0.3slinear; text-decoration: none;"> Install & Activate </a>';
                        }

                        $output .= '<a href="https://wdesignkit.com/" target="_blank" style="font-family: -apple-system, BlinkMacSystemFont, `Segoe UI`, Roboto, `Helvetica Neue`, Arial, sans-serif; font-size: 14px; line-height: 17px; font-weight: 500; color: #1A1A1A; background-color: #ffffff; padding: 12px 25px; border: 1px solid rgba(114, 114, 114, 0.2509803922); border-radius: 5px; width: -moz-fit-content; width: fit-content; height: -moz-fit-content; height: fit-content; position: relative; text-align: center; text-decoration: none; box-sizing: border-box; transition: all 0.3sease-in-out; cursor: pointer;"> Learn More </a>';
                    $output .= '</div>';
                $output .= '</div>';
            $output .= '</div>';

			$output .= '<div class="ob_products_box_cover_main" style="margin: 25px;">';
                $output .= '<div class="ooohboi_product_box" style="position: relative; width: 100%; display: flex; justify-content: space-between; box-sizing: border-box; padding: 30px; background-color: #ffffff; border-radius: 8px;">';
                    $output .= '<div class="ooohboi_product_logo_title_cover" style="display: flex; justify-content: flex-start; align-items: center; gap: 15px;">';
                        $output .= '<div class="ooohboi_product_logo" style="position: relative; width: 60px; height: 60px;">';
                            $output .= '<img src="' . esc_url( OoohBoi_URL . 'assets/img/products/tpae.svg' ) . ' " style="position: relative; width: 100%; height: 100%; ">';
                        $output .= '</div>';
                        $output .= '<h4 class="ooohboi_product_name tpae-in-sec-heading" style="font-size: 16px;"> The Plus Addons for Elementor </h4>';
                    $output .= '</div>';
                    $output .= '<div class="ooohboi-installation" style="display: flex; justify-content: flex-end; align-items: center; gap: 10px;">';

						$checkplugin = $this->check_plugin('theplus', 'the-plus-addons-for-elementor-page-builder/theplus_elementor_addon.php');

                        if ( $checkplugin ) {
                            $output .= '<button class="ob-install-plugin" style="color: #14c38e; border-color: #14c38e; pointer-events: none; display: flex; min-width: 160px; gap: 5px; justify-content: center; align-items: center; font-size: 14px; font-weight: 500; background-color: transparent; padding: 11px 25px; border-radius: 5px; white-space: nowrap;  transition: 0.3slinear; text-decoration: none;"> Activated </button>';
                        } else {
                            $output .= '<a href="' . esc_url( $tpae_install_url ) . '" class="ob-install-plugin" style="display: flex; min-width: 160px; gap: 5px; justify-content: center; align-items: center; font-size: 14px; font-weight: 500; color: #8072fc; background-color: transparent; padding: 11px 25px; border: 1px solid #8072fc; border-radius: 5px; white-space: nowrap; cursor: pointer; transition: 0.3slinear; text-decoration: none;"> Install & Activate </a>';
                        }

                        $output .= '<a href="https://theplusaddons.com/" target="_blank" style="font-family: -apple-system, BlinkMacSystemFont, `Segoe UI`, Roboto, `Helvetica Neue`, Arial, sans-serif; font-size: 14px; line-height: 17px; font-weight: 500; color: #1A1A1A; background-color: #ffffff; padding: 12px 25px; border: 1px solid rgba(114, 114, 114, 0.2509803922); border-radius: 5px; width: -moz-fit-content; width: fit-content; height: -moz-fit-content; height: fit-content; position: relative; text-align: center; text-decoration: none; box-sizing: border-box; transition: all 0.3sease-in-out; cursor: pointer;"> Learn More </a>';
                    $output .= '</div>';
                $output .= '</div>';
            $output .= '</div>';
            
            echo $output;
        }
        
		/**
		 *
		 * It is Use for Check Plugin Dependency.
		 *
		 * @since 2.1.15
		 */
        public function check_plugin( $plugin, $plugin_slug ) {
            /* if ( defined( 'WDKIT_VERSION' ) ) {
				return true;
			} */

			// $installed_plugins = $this->get_plugins();
			$installed_plugins = get_plugins();

			if ( empty( $installed_plugins ) ) {
				return false;
			}

			if ( is_plugin_active( $plugin_slug ) || ( ! empty( $installed_plugins ) && isset( $installed_plugins[ $plugin_slug ] ) ) ) {
				return true;
			} else {
				return false;
			}

			// return false;
        }

		/**
		 *
		 * It is Use for get plugin list.
		 *
		 * @since 2.1.15
		 */
		private function get_plugins() {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once \ABSPATH . 'wp-admin/includes/plugin.php';

				return get_plugins();
			}
		}
	}

	Class_Ob_More_Products::instance();
}




