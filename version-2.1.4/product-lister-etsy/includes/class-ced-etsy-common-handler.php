<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Cedhandler {

	// Directory name
	public $dir_name;
	// To get object of this class
	private static $_instance;
	/**
	 * Get instace of the class
	 *
	 * @since 1.0.0
	 */

	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
	/**
	 * Requrie imoprtant template.
	 *
	 * @since 1.0.0
	 */
	public function ced_require( $file_name = '' ) {
		if ( '' != $file_name && '' != $this->dir_name ) {
			if ( file_exists( CED_ETSY_DIRPATH . $this->dir_name . $file_name . '.php' ) ) {
				require_once CED_ETSY_DIRPATH . $this->dir_name . $file_name . '.php';
			}
		}
	}

	public static function ced_header() {
		require_once CED_ETSY_DIRPATH . 'admin/partials/header.php';
	}

	public static function show_notice_top( $shop_name = '' ) {

		$banner_url = CED_ETSY_URL . 'admin/images/offer--01.jpg';
		$current_user = wp_get_current_user();
		$current_user_name = $current_user->display_name;
	
		$shop_name = 'there';
		$content   = '
		<div class="ced_etsy_heading ced_etsy_upgrade_notice" >
		<h4>You are using the free version !</h4>
	<img src=' . esc_url( $banner_url ) . ' style="width:100%;"></img>
		<p> Hi ' . $shop_name . ' , With the free version you are restricted to sync max of 100 products from WooCommerce to Etsy and won\'t be able to process Etsy orders . Upgrade now to sync unlimited products and orders.</p>
		<hr>
		<p><a href="https://woocommerce.com/products/etsy-integration-for-woocommerce/" target="_blank"><button id="" type="submit" class="button-primary get_preminum">Upgrade Now</button></a></p>
		
		</div>
		<div class="ced_etsy_heading ced_etsy_upgrade_notice">
		<h3> Review Us ⭐⭐⭐⭐⭐</h3>
		<p> Hey <b>'.$current_user_name.'</b>, its Etsy Product lister from <b>CedCommerce</b>. You have used this free plugin for some time now, and I hope you like it!
Could you please give it a 5-star rating on WordPress? Your feedback will boost our motivation and help us promote and continue to improve this product. <a href ="https://wordpress.org/support/plugin/product-lister-etsy/reviews/">Here</a></p>
		</div>
		';
		
		return $content;
	}
}
