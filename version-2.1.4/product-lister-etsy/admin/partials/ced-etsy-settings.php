<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

Cedhandler::ced_header();
/**
 ************************************************
 * SAVING VALUE OF THE SELECTED SHIPPING PROFILE
 ************************************************
 */
$activeShop = isset( $_GET['shop_name'] ) ? sanitize_text_field( wp_unslash( $_GET['shop_name'] ) ) : '';
if ( isset( $_POST['global_settings'] ) ) {
	if ( ! isset( $_POST['global_settings_submit'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['global_settings_submit'] ) ), 'global_settings' ) ) {
		return;
	}
	$sanitized_array          = filter_input_array( INPUT_POST, FILTER_SANITIZE_STRING );
	$settings                 = array();
	$settings                 = get_option( 'ced_etsy_global_settings', array() );
	$ced_etsy_global_settings = isset( $sanitized_array['ced_etsy_global_settings'] ) ? $sanitized_array['ced_etsy_global_settings'] : array();
	wp_clear_scheduled_hook( 'ced_etsy_inventory_scheduler_job_' . $activeShop );
	$inventory_schedule = isset( $sanitized_array['ced_etsy_global_settings']['ced_etsy_auto_update_inventory'] ) ? $sanitized_array['ced_etsy_global_settings']['ced_etsy_auto_update_inventory'] : '';
	if ( ! empty( $inventory_schedule ) ) {
		wp_schedule_event( time(), 'ced_etsy_10min', 'ced_etsy_inventory_scheduler_job_' . $activeShop );
		update_option( 'ced_etsy_inventory_scheduler_job_' . $activeShop, $activeShop );
	}

	$marketplace_name           = isset( $_POST['marketplaceName'] ) ? sanitize_text_field( wp_unslash( $_POST['marketplaceName'] ) ) : 'etsy';
	$offer_settings_information = array();
	$array_to_save              = array();
	if ( isset( $_POST['ced_etsy_required_common'] ) ) {
		foreach ( ( $sanitized_array['ced_etsy_required_common'] ) as $key ) {
			isset( $sanitized_array[ $key ][0] ) ? $array_to_save['default'] = $sanitized_array[ $key ][0] : $array_to_save['default'] = '';

			if ( '_umb_' . $marketplace_name . '_subcategory' == $key ) {
				isset( $sanitized_array[ $key ] ) ? $array_to_save['default'] = $sanitized_array[ $key ] : $array_to_save['default'] = '';
			}

			isset( $sanitized_array[ $key . '_attribute_meta' ] ) ? $array_to_save['metakey'] = $sanitized_array[ $key . '_attribute_meta' ] : $array_to_save['metakey'] = 'null';
			$offer_settings_information['product_data'][ $key ]                               = $array_to_save;
		}
	}
	$settings[ $activeShop ] = array_merge( $ced_etsy_global_settings, $offer_settings_information );
	$sanitized_array['ced_etsy_settings_category']['required'] = 'on';
	update_option( 'ced_etsy_settings_category', $sanitized_array['ced_etsy_settings_category'] );
	update_option( 'ced_etsy_global_settings', $settings );

	/**
	 ************************************************
	 * SAVING VALUE OF THE SELECTED SHIPPING PROFILE
	 ************************************************
	 */
	if ( isset( $_POST['ced_etsy_shipping_details']['ced_etsy_selected_shipping_template'] ) ) {
		$saved_etsy_details                                      = get_option( 'ced_etsy_details', array() );
		$saved_etsy_details[ $activeShop ]['shippingTemplateId'] = sanitize_text_field( wp_unslash( $_POST['ced_etsy_shipping_details']['ced_etsy_selected_shipping_template'] ) );
		update_option( 'ced_etsy_details', $saved_etsy_details );
	}
}
?>
<div class="ced_etsy_heading ">
	<?php
	echo esc_html_e( get_etsy_instuctions_html() );
	?>
	<div class="ced_etsy_child_element default_modal">
		<?php
				$activeShop   = isset( $_GET['shop_name'] ) ? sanitize_text_field( $_GET['shop_name'] ) : '';
				$instructions = array(
					'In this section all the configuration related to product sync are provided.',
					'The <a>Search Product Custom Fields and Attributes</a> section will help you to choose the required metakey or attribute on which the product information is stored.These metakeys or attributes will furthur be used in <a>Product Export Settings</a> for listing products on etsy from woocommerce.',
					'For selecting the required metakey or attribute expand the <a>Search Product Custom Fields and Attributes</a> section enter the product name/keywords and list will be displayed under that . Select the metakey or attribute as per requirement and save settings.',
					'Choose the Shiping profile in <a>Shipping Profiles</a> to be used while listing a product on etsy from woocommerce or you can also add new using <a>Create New</a> button.',
					'To automate the process related to inventory, enable the inventory sync in <a>Crons</a>.',
				);

				echo '<ul class="ced_etsy_instruction_list" type="disc">';
				foreach ( $instructions as $instruction ) {
					print_r( "<li>$instruction</li>" );
				}
				echo '</ul>';

				?>
	</div>
</div>
<?php

$ced_h           = new Cedhandler();
$ced_h->dir_name = '/admin/pages/setting-pages/';
$ced_h->ced_require( 'ced-etsy-metakeys-template' );
?>

<form method="post" action="">
		<?php
			wp_nonce_field( 'global_settings', 'global_settings_submit' );
			/**
			 *****************
			 * Requried Files
			 *****************
			 */

			$files_name = array(
				'ced-etsy-product-upload-settings',
				'ced-etsy-scheduler-settings',
			);
			foreach ( $files_name as $file_name ) {
				$ced_h->ced_require( $file_name );
			}
			?>
	<div class="left ced-button-wrapper" >
		<button id="" type="submit" name="global_settings" class="button-primary" ><?php esc_html_e( 'Save Settings', 'product-lister-etsy' ); ?></button>
	</div>
</form>
