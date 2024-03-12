<?php
/**
 * Wordpress-plugin
 * Plugin Name:       Product Lister For Etsy
 * Plugin URI:        https://cedcommerce.com
 * Description:       Product Lister for Etsy allows merchants to list their products on Etsy marketplace.
 * Version:           2.1.4
 * Author:            CedCommerce
 * Author URI:        https://cedcommerce.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:      woocommmerce-etsy-integration
 * Domain Path:       /languages
 *
 * WC requires at least: 3.0
 * WC tested up to: 5.7.1
 *
 * @package  Woocommmerce_Etsy_Integration
 * @version  1.0.0
 * @link     https://cedcommerce.com
 * @since    1.0.0
 */


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WOOCOMMMERCE_ETSY_INTEGRATION_VERSION', '2.1.4' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woocommmerce-etsy-integration-activator.php
 */
function activate_woocommmerce_etsy_integration() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woocommmerce-etsy-integration-activator.php';
	Woocommmerce_Etsy_Integration_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woocommmerce-etsy-integration-deactivator.php
 */
function deactivate_woocommmerce_etsy_integration() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woocommmerce-etsy-integration-deactivator.php';
	Woocommmerce_Etsy_Integration_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_woocommmerce_etsy_integration' );
register_deactivation_hook( __FILE__, 'deactivate_woocommmerce_etsy_integration' );

/* DEFINE CONSTANTS */
define( 'CED_ETSY_LOG_DIRECTORY', wp_upload_dir()['basedir'] . '/ced_etsy_log_directory' );
define( 'CED_ETSY_VERSION', '1.0.0' );
define( 'CED_ETSY_PREFIX', 'ced_etsy' );
define( 'CED_ETSY_DIRPATH', plugin_dir_path( __FILE__ ) );
define( 'CED_ETSY_URL', plugin_dir_url( __FILE__ ) );
define( 'CED_ETSY_ABSPATH', untrailingslashit( plugin_dir_path( dirname( __FILE__ ) ) ) );
define( 'CED_ETSY_LISTER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );



/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-woocommmerce-etsy-integration.php';
/**
* This file includes core functions to be used globally in plugin.
 *
* @link  http://www.cedcommerce.com/
*/
require_once plugin_dir_path( __FILE__ ) . 'includes/ced-etsy-core-functions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ced-etsy-common-handler.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_woocommmerce_etsy_lister() {

	$plugin = new Woocommmerce_Etsy_Integration();
	$plugin->run();

}

/* Register activation hook. */
register_activation_hook( __FILE__, 'ced_admin_notice_example_activation_hook_ced_etsy' );

/**
 * Runs only when the plugin is activated.
 *
 * @since 1.0.0
 */
function ced_admin_notice_example_activation_hook_ced_etsy() {

	/* Create transient data */
	set_transient( 'ced-etsy-admin-notice', true, 5 );
}


/**
 * Admin Notice on Activation.
 *
 * @since 0.1.0
 */


function ced_etsy_admin_notice_activation() {

	/* Check transient, if available display notice */
	if ( get_transient( 'ced-etsy-admin-notice' ) ) {?>
		<div class="updated notice is-dismissible">
			<p>Welcome to Product Lister For Etsy. Start listing your WooCommerce Products and sync Inventory.</p>
			<a href="admin.php?page=ced_etsy_lister" class ="ced_configuration_plugin_main">Connect to Etsy</a>
		</div>
		
		<?php
		/* Delete transient, only display this notice once. */
		delete_transient( 'ced-etsy-admin-notice' );
	}
}
if ( ced_etsy_check_woocommerce_active() ) {
	run_woocommmerce_etsy_lister();
	add_action( 'admin_notices', 'ced_etsy_admin_notice_activation' );
} else {

	add_action( 'admin_init', 'deactivate_ced_etsy_woo_missing' );
}

