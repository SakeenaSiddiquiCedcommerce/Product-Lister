<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'Class_Ced_Etsy_Category' ) ) {

	class Class_Ced_Etsy_Category {


		public static $_instance;

		/**
		 * Ced_Etsy_Config Instance.
		 *
		 * Ensures only one instance of Ced_Etsy_Config is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 */
		public static function get_instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Etsy getting seller taxonomies
		 *
		 * @since    1.0.0
		 */
		public function getEtsyCategories( $shop_name = '' ) {
			$shop_name = ced_etsy_shop_name();
			do_action( 'ced_etsy_refresh_token', $shop_name );
			$categories = etsy_request()->ced_remote_request( $shop_name, 'application/seller-taxonomy/nodes', 'GET' );
			return $categories;
		}
	}
}
