<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

function etsy_write_logs( $filename, $stringTowrite ) {
	$dirTowriteFile = CED_ETSY_LOG_DIRECTORY;
	if ( defined( 'CED_ETSY_LOG_DIRECTORY' ) ) {
		if ( ! is_dir( $dirTowriteFile ) ) {
			if ( ! mkdir( $dirTowriteFile, 0755 ) ) {
				return;
			}
		}
		$fileTowrite = $dirTowriteFile . "/$filename";
		$fp          = fopen( $fileTowrite, 'a' );
		if ( ! $fp ) {
			return;
		}
		$fr = fwrite( $fp, $stringTowrite . "\n" );
		fclose( $fp );
	} else {
		return;
	}
}

function ced_etsy_inactive_shops( $shop_name = '' ) {

	$shops = get_option( 'ced_etsy_details', '' );
	if ( isset( $shops[ $shop_name ]['details']['ced_shop_account_status'] ) && 'InActive' == $shops[ $shop_name ]['details']['ced_shop_account_status'] ) {
		return true;
	}
}

function ced_etsy_get_active_shop_name() {
	$saved_etsy_details = get_option( 'ced_etsy_details', array() );
	$shopName           = isset( $saved_etsy_details['details']['ced_etsy_shop_name'] ) ? $saved_etsy_details['details']['ced_etsy_shop_name'] : '';
	return $shopName;
}
function ced_etsy_check_woocommerce_active() {
	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		return true;
	}
	return false;
}
function deactivate_ced_etsy_woo_missing() {

	deactivate_plugins( CED_ETSY_LISTER_PLUGIN_BASENAME );
	add_action( 'admin_notices', 'ced_etsy_woo_missing_notice' );
	if ( isset( $_GET['activate'] ) ) {
		unset( $_GET['activate'] );
	}
}
function ced_etsy_woo_missing_notice() {

	// translators: %s: search term !!
	echo '<div class="notice notice-error is-dismissible"><p>' . sprintf( esc_html( __( 'Product Lister for Etsy requires WooCommerce to be installed and active. You can download %s from here.', 'etsy-integration-for-woocommerce' ) ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a>' ) . '</p></div>';
}

function ced_etsy_tool_tip( $tip = '' ) {
	// echo wc_help_tip( __( $tip, 'product-lister-etsy' ) );
	print_r( "</br><span class='cedcommerce-tip'>[ $tip ]</span>" );
}

/**
 * Callback function for display html.
 *
 * @since 1.0.0
 */
function ced_etsy_render_html( $meta_keys_to_be_displayed = array(), $added_meta_keys = array() ) {
	$html  = '';
	$html .= '<table class="wp-list-table widefat fixed striped ced_etsy_config_table">';

	if ( isset( $meta_keys_to_be_displayed ) && is_array( $meta_keys_to_be_displayed ) && ! empty( $meta_keys_to_be_displayed ) ) {
		$total_items  = count( $meta_keys_to_be_displayed );
		$pages        = ceil( $total_items / 10 );
		$current_page = 1;
		$counter      = 0;
		$break_point  = 1;

		foreach ( $meta_keys_to_be_displayed as $meta_key => $meta_data ) {
			$display = 'display : none';
			if ( 0 == $counter ) {
				if ( 1 == $break_point ) {
					$display = 'display : contents';
				}
				$html .= '<tbody style="' . esc_attr( $display ) . '" class="ced_etsy_metakey_list_' . $break_point . '  			ced_etsy_metakey_body">';
				$html .= '<tr><td colspan="3"><label>CHECK THE PRODUCT CUSTOM FIELDS</label></td>';
				$html .= '<td class="ced_etsy_pagination"><span>' . $total_items . ' items</span>';
				$html .= '<button class="button ced_etsy_navigation" data-page="1" ' . ( ( 1 == $break_point ) ? 'disabled' : '' ) . ' ><b><<</b></button>';
				$html .= '<button class="button ced_etsy_navigation" data-page="' . esc_attr( $break_point - 1 ) . '" ' . ( ( 1 == $break_point ) ? 'disabled' : '' ) . ' ><b><</b></button><span>' . $break_point . ' of ' . $pages;
				$html .= '</span><button class="button ced_etsy_navigation" data-page="' . esc_attr( $break_point + 1 ) . '" ' . ( ( $pages == $break_point ) ? 'disabled' : '' ) . ' ><b>></b></button>';
				$html .= '<button class="button ced_etsy_navigation" data-page="' . esc_attr( $pages ) . '" ' . ( ( $pages == $break_point ) ? 'disabled' : '' ) . ' ><b>>></b></button>';
				$html .= '</td>';
				$html .= '</tr>';
				$html .= '<tr><td><label>Select</label></td><td><label>Metakey / Attributes</label></td><td colspan="2"><label>Value</label></td>';

			}
			$checked    = ( in_array( $meta_key, $added_meta_keys ) ) ? 'checked=checked' : '';
			$html      .= '<tr>';
			$html      .= "<td><input type='checkbox' class='ced_etsy_meta_key' value='" . esc_attr( $meta_key ) . "' " . $checked . '></input></td>';
			$html      .= '<td>' . esc_attr( $meta_key ) . '</td>';
			$meta_value = ! empty( $meta_data[0] ) ? $meta_data[0] : '';
			$html      .= '<td colspan="2">' . esc_attr( $meta_value ) . '</td>';
			$html      .= '</tr>';
			++$counter;
			if ( 10 == $counter || $break_point == $pages ) {
				$counter = 0;
				++$break_point;
				$html .= '</tbody>';
			}
		}
	} else {
		$html .= '<tr><td colspan="4" class="etsy-error">No data found. Please search the metakeys.</td></tr>';
	}
	$html .= '</table>';
	return $html;
}

/**
 * Callback function for display html.
 *
 * @since 1.0.0
 */
function get_etsy_instuctions_html( $label = 'Instructions' ) {
	?>
	<div class="ced_etsy_parent_element">
		<h2>
			<label><?php echo esc_html_e( $label, 'etsy-woocommerce-integration' ); ?></label>
			<span class="dashicons dashicons-arrow-down-alt2 ced_etsy_instruction_icon"></span>
		</h2>
	</div>
	<?php
}


function ced_etsy_cedcommerce_logo() {
	?>
	<img src="<?php echo esc_url( CED_ETSY_URL . 'admin/images/ced-logo.png' ); ?> ">
	<?php
}
function get_etsy_shop_id( $shop_name = '' ) {
	$saved_etsy_details = get_option( 'ced_etsy_details', array() );
	$shopDetails        = $saved_etsy_details[ $shop_name ];
	$shop_id            = isset( $shopDetails['details']['shop_id'] ) ? $shopDetails['details']['shop_id'] : '';
	return $shop_id;
}

function etsy_request() {
	$req_file = CED_ETSY_DIRPATH . 'admin/etsy/lib/class-ced-etsy-request.php';
	if ( file_exists( $req_file ) ) {
		require $req_file;
		return new Ced_Etsy_Request();
	}
	return false;

}

function ced_etsy_check_if_limit_reached( $shop_name ) {
	$store_products = get_posts(
		array(
			'numberposts' => -1,
			'post_type'   => 'product',
			'meta_query'  => array(
				array(
					'key'     => '_ced_etsy_listing_id_' . $shop_name,
					'compare' => 'EXISTS',
				),
			),
			'fields'      => 'ids',
		)
	);
	$count          = count( $store_products );
	if ( $count < 100 ) {
		return false;
	}
	return true;

}
function display_banner() {
	$banner_url = CED_ETSY_URL . 'admin/images/bcfm-etsy-lg.jpg';
	echo "<div class='ced_etsy_banner_wrap'>";
	echo "<div><img src='" . esc_url( $banner_url ) . "''></img></div>";
	echo "<div class='ced_etsy_banner_content_wrap'><div class='ced_etsy_banner_content'><h2>Black Cyber Sale ! Save 40% on our Premium Version till 30/11 .</h2><span>For this year's Black Friday, we are offering a 40% discount on our Premium Version. Now is the time to get your Etsy store up and running with our Premium Version.
        Some of our premium offerings include <b>Automated and Instant Inventory Sync, Automated Etsy Listings Import, Bulk Product Upload and Automated Order Fulfillment.</b>
        All of these features are backed by our Industry Leading <b>Premium Seller Support.</b></span>
	<p><span><a href='https://woocommerce.com/products/etsy-integration-for-woocommerce/' target='_blank'><button id='' type='submit' class='button-primary get_preminum'>Upgrade Now</button></a></span></p></div>
	</div>";
	echo '</div>';
}

function ced_etsy_shop_name() {
	return isset( $_GET['shop_name'] ) ? sanitize_text_field( $_GET['shop_name'] ) : '';
}


if ( !function_exists( 'ced_etsy_get_auth' ) ) {
	function ced_etsy_get_auth() {
		$infrm = get_option('ced_etsy_auth_info', array());
		return openssl_decrypt( $infrm['scrt'], 'AES-128-CTR', $infrm['ky'] );
	}
}
