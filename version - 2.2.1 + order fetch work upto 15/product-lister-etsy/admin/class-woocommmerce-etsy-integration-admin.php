<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://cedcommerce.com
 * @since      1.0.0
 *
 * @package    Woocommmerce_Etsy_Integration
 * @subpackage Woocommmerce_Etsy_Integration/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Woocommmerce_Etsy_Integration
 * @subpackage Woocommmerce_Etsy_Integration/admin
 */
class Woocommmerce_Etsy_Integration_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->loadDependency();
		require_once CED_ETSY_DIRPATH . 'admin/etsy/lib/etsyOrders.php';
		$this->ced_etsy_order   = new Ced_Order_Get();
		add_action( 'ced_etsy_async_update_inventory_action', array( $this, 'ced_etsy_async_update_stock_callback' ) );
		add_action( 'wp_ajax_ced_etsy_load_more_logs', array( $this, 'ced_etsy_load_more_logs' ) );
	}
	public function ced_etsy_load_more_logs() {
		$check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			$sanitized_array = filter_input_array( INPUT_POST, FILTER_SANITIZE_STRING );
			$parent          = isset( $sanitized_array['parent'] ) ? $sanitized_array['parent'] : '';
			$offset          = isset( $sanitized_array['offset'] ) ? (int) $sanitized_array['offset'] : '';
			$total           = isset( $sanitized_array['total'] ) ? (int) $sanitized_array['total'] : '';

			$log_info = get_option( $parent, '' );
			if ( empty( $log_info ) ) {
				$log_info = array();
			} else {
				$log_info = json_decode( $log_info, true );
			}
			$log_info   = array_slice( $log_info, (int) $offset, 50 );
			$is_disable = 'no';
			$html       = '';
			if ( ! empty( $log_info ) ) {
				$offset += count( $log_info );
				foreach ( $log_info as $key => $info ) {

					$html .= "<tr class='ced_etsy_log_rows'>";
					$html .= "<td><span class='log_item_label log_details'><a>" . ( $info['post_title'] ) . "</a></span><span class='log_message' style='display:none;'><h3>Input payload for " . ( $info['post_title'] ) . '</h3><pre>' . ( ! empty( $info['input_payload'] ) ? json_encode( $info['input_payload'], JSON_PRETTY_PRINT ) : '' ) . '</pre></span></td>';
					$html .= "<td><span class=''>" . $info['action'] . '</span></td>';
					$html .= "<td><span class=''>" . $info['time'] . '</span></td>';
					$html .= "<td><span class=''>" . ( $info['is_auto'] ? 'Automatic' : 'Manual' ) . '</span></td>';
					$html .= '<td>';
					if ( isset( $info['response']['response']['results'] ) || isset( $info['response']['results'] ) || isset( $info['response']['listing_id'] ) || isset( $info['response']['response']['products'] ) || isset( $info['response']['products'] ) || isset( $info['response']['listing_id'] ) ) {
						$html .= "<span class='etsy_log_success log_details'>Success</span>";
					} else {
						$html .= "<span class='etsy_log_fail log_details'>Failed</span>";
					}
					$html .= "<span class='log_message' style='display:none;'><h3>Response payload for " . ( $info['post_title'] ) . '</h3><pre>' . ( ! empty( $info['response'] ) ? json_encode( $info['response'], JSON_PRETTY_PRINT ) : '' ) . '</pre></span>';
					$html .= '</td>';
					$html .= '</tr>';
				}
			}
			if ( $offset >= $total ) {
				$is_disable = 'yes';
			}
			echo json_encode(
				array(
					'html'       => $html,
					'offset'     => $offset,
					'is_disable' => $is_disable,
				)
			);
			wp_die();
		}
	}
	public function loadDependency() {
		$ced_h           = Cedhandler::get_instance();
		$ced_h->dir_name = 'admin/etsy/';
		$ced_h->ced_require( 'class-etsy' );
		$this->CED_ETSY_Manager = CED_ETSY_Manager::get_instance();

		require_once CED_ETSY_DIRPATH . 'admin/etsy/lib/etsyActivities.php';
					$activity            = new Etsy_Activities();
					$GLOBALS['activity'] = $activity;
	}


	public function ced_etsy_auto_upload_categories() {
		$check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			$sanitized_array        = filter_input_array( INPUT_POST, FILTER_SANITIZE_STRING );
			$woo_categories         = isset( $sanitized_array['categories'] ) ? json_decode( $sanitized_array['categories'], true ) : array();
			$shop_name              = isset( $sanitized_array['shop_name'] ) ? $sanitized_array['shop_name'] : '';
			$operation              = isset( $sanitized_array['operation'] ) ? sanitize_text_field( $sanitized_array['operation'] ) : 'save';
			$auto_upload_categories = get_option( 'ced_etsy_auto_upload_categories_' . $shop_name, array() );
			if ( 'save' == $operation ) {
				$auto_upload_categories = array_merge( $auto_upload_categories, $woo_categories );
				$message                = 'Category added in auto upload queue';
			} elseif ( 'remove' == $operation ) {
				$auto_upload_categories = array_diff( $auto_upload_categories, $woo_categories );
				$auto_upload_categories = array_values( $auto_upload_categories );
				$message                = 'Category removed from auto upload queue';
			}

			$auto_upload_categories = array_unique( $auto_upload_categories );
			update_option( 'ced_etsy_auto_upload_categories_' . $shop_name, $auto_upload_categories );
			echo json_encode(
				array(
					'status'  => 200,
					'message' => $message,
				)
			);
			wp_die();
		}
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		global $pagenow;
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Woocommmerce_Etsy_Integration_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Woocommmerce_Etsy_Integration_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		if ( isset( $_GET['page'] ) && ( 'ced_etsy_lister' == $_GET['page'] || 'cedcommerce-integrations' == $_GET['page'] ) ) {
			wp_enqueue_style( 'ced-boot-css', 'https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css', array(), '2.0.0', 'all' );

				wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/woocommmerce-etsy-integration-admin.css', array(), $this->version, 'all' );
		}

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		global $pagenow;
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Woocommmerce_Etsy_Integration_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Woocommmerce_Etsy_Integration_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		// woocommerce style //
		wp_register_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION );
		wp_enqueue_style( 'woocommerce_admin_menu_styles' );
		wp_enqueue_style( 'woocommerce_admin_styles' );

		$params = array(
			/* translators: (%s): wc_get_price_decimal_separator */
			'i18n_mon_decimal_error'           => sprintf( __( 'Please enter in monetary decimal (%s) format without thousand separators and currency symbols.', 'woocommerce' ), wc_get_price_decimal_separator() ),
			'i18n_country_iso_error'           => __( 'Please enter in country code with two capital letters.', 'woocommerce' ),
			'i18_sale_less_than_regular_error' => __( 'Please enter in a value less than the regular price.', 'woocommerce' ),

			'mon_decimal_point'                => wc_get_price_decimal_separator(),
			'strings'                          => array(
				'import_products' => __( 'Import', 'woocommerce' ),
				'export_products' => __( 'Export', 'woocommerce' ),
			),
			'urls'                             => array(
				'import_products' => esc_url_raw( admin_url( 'edit.php?post_type=product&page=product_importer' ) ),
				'export_products' => esc_url_raw( admin_url( 'edit.php?post_type=product&page=product_exporter' ) ),
			),
		);
		// woocommerce script //

		$suffix = '';
		wp_register_script( 'woocommerce_admin', WC()->plugin_url() . '/assets/js/admin/woocommerce_admin' . $suffix . '.js', array( 'jquery', 'jquery-blockui', 'jquery-ui-sortable', 'jquery-ui-widget', 'jquery-ui-core', 'jquery-tiptip' ), WC_VERSION );
		wp_localize_script( 'woocommerce_admin', 'woocommerce_admin', $params );

		wp_register_script( 'jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip' . $suffix . '.js', array( 'jquery' ), WC_VERSION, true );
		wp_enqueue_script( 'woocommerce_admin' );
		if ( isset( $_GET['page'] ) && ( 'ced_etsy_lister' == $_GET['page'] || 'cedcommerce-integrations' == $_GET['page'] ) ) {
			wp_enqueue_script( $this->plugin_name . '_hubspot', '//js-na1.hs-scripts.com/6086579.js', array( 'jquery' ), $this->version, false );
		}

		$shop_name = isset( $_GET['shop_name'] ) ? sanitize_text_field( wp_unslash( $_GET['shop_name'] ) ) : '';
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/woocommmerce-etsy-integration-admin.js', array( 'jquery' ), $this->version, false );
		$ajax_nonce     = wp_create_nonce( 'ced-etsy-ajax-seurity-string' );
		$localize_array = array(
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'ajax_nonce' => $ajax_nonce,
			'shop_name'  => $shop_name,
		);
		wp_localize_script( $this->plugin_name, 'ced_etsy_admin_obj', $localize_array );

	}

	/**
	 * Add admin menus and submenus
	 *
	 * @since    1.0.0
	 */
	public function ced_etsy_add_menus() {
		global $submenu;
		if ( empty( $GLOBALS['admin_page_hooks']['cedcommerce-integrations'] ) ) {
			add_menu_page( __( 'CedCommerce', 'product-lister-etsy' ), __( 'CedCommerce', 'product-lister-etsy' ), 'manage_woocommerce', 'cedcommerce-integrations', array( $this, 'ced_marketplace_listing_page' ), plugins_url( 'product-lister-etsy/admin/images/logo1.png' ), 12 );

			$menus = apply_filters( 'ced_add_marketplace_menus_array', array() );

			if ( is_array( $menus ) && ! empty( $menus ) ) {
				foreach ( $menus as $key => $value ) {
					add_submenu_page( 'cedcommerce-integrations', $value['name'], $value['name'], 'manage_woocommerce', $value['menu_link'], array( $value['instance'], $value['function'] ) );
				}
			}
			/*
			add_submenu_page( 'cedcommerce-integrations', "Additionals", "Additionals", 'manage_options', 'ced_additional', array( $this, 'ced_additional_page' ) );*/
		}
	}

	/**
	 * Woocommerce_Etsy_Integration_Admin ced_etsy_search_product_name.
	 *
	 * @since 1.0.0
	 */
	public function ced_etsy_search_product_name() {

		$check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			$keyword      = isset( $_POST['keyword'] ) ? sanitize_text_field( $_POST['keyword'] ) : '';
			$product_list = '';
			if ( ! empty( $keyword ) ) {
				$arguements = array(
					'numberposts' => -1,
					'post_type'   => array( 'product', 'product_variation' ),
					's'           => $keyword,
				);
				$post_data  = get_posts( $arguements );
				if ( ! empty( $post_data ) ) {
					foreach ( $post_data as $key => $data ) {
						$product_list .= '<li class="ced_etsy_searched_product" data-post-id="' . esc_attr( $data->ID ) . '">' . esc_html( __( $data->post_title, 'etsy-woocommerce-integration' ) ) . '</li>';
					}
				} else {
					$product_list .= '<li>No products found.</li>';
				}
			} else {
				$product_list .= '<li>No products found.</li>';
			}
			echo json_encode( array( 'html' => $product_list ) );
			wp_die();
		}
	}


		/**
		 * Woocommerce_Etsy_Integration_Admin ced_etsy_get_product_metakeys.
		 *
		 * @since 1.0.0
		 */
	public function ced_etsy_get_product_metakeys() {

		$check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			$product_id = isset( $_POST['post_id'] ) ? sanitize_text_field( $_POST['post_id'] ) : '';
			include_once CED_ETSY_DIRPATH . 'admin/partials/ced-etsy-metakeys-list.php';
		}
	}

	/**
	 * Woocommerce_Etsy_Integration_Admin ced_etsy_process_metakeys.
	 *
	 * @since 1.0.0
	 */
	public function ced_etsy_process_metakeys() {

		$check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			$metakey   = isset( $_POST['metakey'] ) ? sanitize_text_field( wp_unslash( $_POST['metakey'] ) ) : '';
			$operation = isset( $_POST['operation'] ) ? sanitize_text_field( wp_unslash( $_POST['operation'] ) ) : '';
			if ( ! empty( $metakey ) ) {
				$added_meta_keys = get_option( 'ced_etsy_selected_metakeys', array() );
				if ( 'store' == $operation ) {
					$added_meta_keys[ $metakey ] = $metakey;
				} elseif ( 'remove' == $operation ) {
					unset( $added_meta_keys[ $metakey ] );
				}
				update_option( 'ced_etsy_selected_metakeys', $added_meta_keys );
				echo json_encode( array( 'status' => 200 ) );
				die();
			} else {
				echo json_encode( array( 'status' => 400 ) );
				die();
			}
		}
	}

	/**
	 * Active Marketplace List
	 *
	 * @since    1.0.0
	 */

	public function ced_marketplace_listing_page() {
		$activeMarketplaces = apply_filters( 'ced_add_marketplace_menus_array', array() );
		if ( is_array( $activeMarketplaces ) && ! empty( $activeMarketplaces ) ) {
			require CED_ETSY_DIRPATH . 'admin/partials/marketplaces.php';
		}
	}

	public function ced_etsy_add_marketplace_menus_to_array( $menus = array() ) {
		$menus[] = array(
			'name'            => 'Etsy',
			'slug'            => 'product-lister-etsy',
			'menu_link'       => 'ced_etsy_lister',
			'instance'        => $this,
			'function'        => 'ced_etsy_accounts_page',
			'card_image_link' => CED_ETSY_URL . 'admin/images/etsy.png',
		);
		return $menus;
	}

	/**
	 * Ced Etsy Accounts Page
	 *
	 * @since    1.0.0
	 */
	public function ced_etsy_accounts_page() {

		$fileAccounts = CED_ETSY_DIRPATH . 'admin/partials/ced-etsy-accounts.php';
		if ( file_exists( $fileAccounts ) ) {
			echo "<div class='cedcommerce-etsy-wrap'>";
			require_once $fileAccounts;
			echo '</div>';
		}
	}


	/**
	 * Etsy Changing Account status
	 *
	 * @since    1.0.0
	 */
	public function ced_etsy_change_account_status() {
		$check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			$shop_name = isset( $_POST['shop_name'] ) ? sanitize_text_field( wp_unslash( $_POST['shop_name'] ) ) : '';
			$status    = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';
			$shops     = get_option( 'ced_etsy_details', array() );
			$shops[ $shop_name ]['details']['ced_shop_account_status'] = $status;
			update_option( 'ced_etsy_details', $shops );
			echo json_encode( array( 'status' => '200' ) );
			die;
		}
	}
	/**
	 * Marketplace
	 *
	 * @since    1.0.0
	 */
	public function ced_etsy_marketplace_to_be_logged( $marketplaces = array() ) {

		$marketplaces[] = array(
			'name'             => 'Etsy',
			'marketplace_slug' => 'etsy',
		);
		return $marketplaces;
	}

	/**
	 * Etsy Cron Schedules
	 *
	 * @since    1.0.0
	 */
	public function my_etsy_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['ced_etsy_6min'] ) ) {
			$schedules['ced_etsy_6min'] = array(
				'interval' => 6 * 60,
				'display'  => __( 'Once every 6 minutes' ),
			);
		}
		if ( ! isset( $schedules['ced_etsy_10min'] ) ) {
			$schedules['ced_etsy_10min'] = array(
				'interval' => 10 * 60,
				'display'  => __( 'Once every 10 minutes' ),
			);
		}
		if ( ! isset( $schedules['ced_etsy_15min'] ) ) {
			$schedules['ced_etsy_15min'] = array(
				'interval' => 15 * 60,
				'display'  => __( 'Once every 15 minutes' ),
			);
		}
		if ( ! isset( $schedules['ced_etsy_30min'] ) ) {
			$schedules['ced_etsy_30min'] = array(
				'interval' => 30 * 60,
				'display'  => __( 'Once every 30 minutes' ),
			);
		}
		if ( ! isset( $schedules['ced_etsy_20min'] ) ) {
			$schedules['ced_etsy_20min'] = array(
				'interval' => 20 * 60,
				'display'  => __( 'Once every 20 minutes' ),
			);
		}
		return $schedules;
	}


	/**
	 * Etsy Fetch Next Level Category
	 *
	 * @since    1.0.0
	 */
	public function ced_etsy_fetch_next_level_category() {
		$check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			global $wpdb;
			$store_category_id      = isset( $_POST['store_id'] ) ? sanitize_text_field( wp_unslash( $_POST['store_id'] ) ) : '';
			$etsy_category_name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$etsy_category_id       = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
			$level                  = isset( $_POST['level'] ) ? sanitize_text_field( wp_unslash( $_POST['level'] ) ) : '';
			$next_level             = intval( $level ) + 1;
			$etsyCategoryList       = file_get_contents( CED_ETSY_DIRPATH . 'admin/etsy/lib/json/categoryLevel-' . $next_level . '.json' );
			$etsyCategoryList       = json_decode( $etsyCategoryList, true );
			$select_html            = '';
			$nextLevelCategoryArray = array();
			if ( ! empty( $etsyCategoryList ) ) {
				foreach ( $etsyCategoryList as $key => $value ) {
					if ( isset( $value['parent_id'] ) && $value['parent_id'] == $etsy_category_id ) {
						$nextLevelCategoryArray[] = $value;
					}
				}
			}
			if ( is_array( $nextLevelCategoryArray ) && ! empty( $nextLevelCategoryArray ) ) {

				$select_html .= '<td data-catlevel="' . $next_level . '"><select class="ced_etsy_level' . $next_level . '_category ced_etsy_select_category select_boxes_cat_map" name="ced_etsy_level' . $next_level . '_category[]" data-level=' . $next_level . ' data-storeCategoryID="' . $store_category_id . '">';
				$select_html .= '<option value=""> --' . __( 'Select', 'product-lister-etsy' ) . '-- </option>';
				foreach ( $nextLevelCategoryArray as $key => $value ) {
					if ( ! empty( $value['name'] ) ) {
						$select_html .= '<option value="' . $value['id'] . ',' . $value['name'] . '">' . $value['name'] . '</option>';
					}
				}
				$select_html .= '</select></td>';
				echo json_encode( $select_html );
				die;
			}
		}
	}

	/*
	*
	*Function for Fetching child categories for custom profile
	*
	*
	*/

	public function ced_etsy_fetch_next_level_category_add_profile() {
		$check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			global $wpdb;
			$tableName              = $wpdb->prefix . 'ced_etsy_accounts';
			$etsy_store_id          = isset( $_POST['etsy_store_id'] ) ? sanitize_text_field( wp_unslash( $_POST['etsy_store_id'] ) ) : '';
			$etsy_category_name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$etsy_category_id       = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
			$level                  = isset( $_POST['level'] ) ? sanitize_text_field( wp_unslash( $_POST['level'] ) ) : '';
			$next_level             = intval( $level ) + 1;
			$etsyCategoryList       = file_get_contents( CED_ETSY_DIRPATH . 'admin/etsy/lib/json/categoryLevel-' . $next_level . '.json' );
			$etsyCategoryList       = json_decode( $etsyCategoryList, true );
			$select_html            = '';
			$nextLevelCategoryArray = array();
			if ( ! empty( $etsyCategoryList ) ) {
				foreach ( $etsyCategoryList as $key => $value ) {
					if ( isset( $value['parent_id'] ) && $value['parent_id'] == $etsy_category_id ) {
						$nextLevelCategoryArray[] = $value;
					}
				}
			}
			if ( is_array( $nextLevelCategoryArray ) && ! empty( $nextLevelCategoryArray ) ) {

				$select_html .= '<td data-catlevel="' . $next_level . '"><select class="ced_etsy_level' . $next_level . '_category ced_etsy_select_category_on_add_profile  select_boxes_cat_map" name="ced_etsy_level' . $next_level . '_category[]" data-level=' . $next_level . ' data-etsyStoreId="' . $etsy_store_id . '">';
				$select_html .= '<option value=""> --' . __( 'Select', 'product-lister-etsy' ) . '-- </option>';
				foreach ( $nextLevelCategoryArray as $key => $value ) {
					if ( ! empty( $value['name'] ) ) {
						$select_html .= '<option value="' . $value['id'] . ',' . $value['name'] . '">' . $value['name'] . '</option>';
					}
				}
				$select_html .= '</select></td>';
				echo json_encode( $select_html );
				die;
			}
		}
	}


	/**
	 * Etsy Mapping Categories to WooStore
	 *
	 * @since    1.0.0
	 */
	public function ced_etsy_map_categories_to_store() {

		$check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			$sanitized_array             = filter_input_array( INPUT_POST, FILTER_SANITIZE_STRING );
			$etsy_category_array         = isset( $sanitized_array['etsy_category_array'] ) ? $sanitized_array['etsy_category_array'] : '';
			$store_category_array        = isset( $sanitized_array['store_category_array'] ) ? $sanitized_array['store_category_array'] : '';
			$etsy_category_name          = isset( $sanitized_array['etsy_category_name'] ) ? $sanitized_array['etsy_category_name'] : '';
			$etsy_store_id               = isset( $_POST['storeName'] ) ? sanitize_text_field( wp_unslash( $_POST['storeName'] ) ) : '';
			$etsy_saved_category         = get_option( 'ced_etsy_saved_category', array() );
			$alreadyMappedCategories     = array();
			$alreadyMappedCategoriesName = array();
			$etsyMappedCategories        = array_combine( $store_category_array, $etsy_category_array );
			$etsyMappedCategories        = array_filter( $etsyMappedCategories );
			$alreadyMappedCategories     = get_option( 'ced_woo_etsy_mapped_categories_' . $etsy_store_id, array() );
			if ( is_array( $etsyMappedCategories ) && ! empty( $etsyMappedCategories ) ) {
				foreach ( $etsyMappedCategories as $key => $value ) {
					$alreadyMappedCategories[ $etsy_store_id ][ $key ] = $value;
				}
			}
			update_option( 'ced_woo_etsy_mapped_categories_' . $etsy_store_id, $alreadyMappedCategories );
			$etsyMappedCategoriesName    = array_combine( $etsy_category_array, $etsy_category_name );
			$etsyMappedCategoriesName    = array_filter( $etsyMappedCategoriesName );
			$alreadyMappedCategoriesName = get_option( 'ced_woo_etsy_mapped_categories_name_' . $etsy_store_id, array() );
			if ( is_array( $etsyMappedCategoriesName ) && ! empty( $etsyMappedCategoriesName ) ) {
				foreach ( $etsyMappedCategoriesName as $key => $value ) {
					$alreadyMappedCategoriesName[ $etsy_store_id ][ $key ] = $value;
				}
			}
			update_option( 'ced_woo_etsy_mapped_categories_name_' . $etsy_store_id, $alreadyMappedCategoriesName );
			$this->CED_ETSY_Manager->ced_etsy_createAutoProfiles( $etsyMappedCategories, $etsyMappedCategoriesName, $etsy_store_id );
			wp_die();
		}
	}

	/**
	 * Etsy Inventory Scheduler
	 *
	 * @since    1.0.0
	 */
	public function ced_etsy_inventory_schedule_manager() {

		$hook    = current_action();
		$shop_id = str_replace( 'ced_etsy_inventory_scheduler_job_', '', $hook );
		$shop_id = trim( $shop_id );

		if ( empty( $shop_id ) ) {
			$shop_id = get_option( 'ced_etsy_shop_name', '' );
		}
		$products_to_sync = get_option( 'ced_etsy_chunk_products', array() );
		if ( empty( $products_to_sync ) ) {

			$store_products   = get_posts(
				array(
					'numberposts' => -1,
					'post_type'   => 'product',
					'meta_query'  => array(
						array(
							'key'     => '_ced_etsy_listing_id_' . $shop_id,
							'compare' => 'EXISTS',
						),
					),
				)
			);
			$store_products   = wp_list_pluck( $store_products, 'ID' );
			$products_to_sync = array_chunk( $store_products, 10 );

		}

		if ( is_array( $products_to_sync[0] ) && ! empty( $products_to_sync[0] ) ) {
			$fileProducts = CED_ETSY_DIRPATH . 'admin/etsy/lib/etsyProducts.php';
			if ( file_exists( $fileProducts ) ) {
				require_once $fileProducts;
			}

			$etsyOrdersInstance = Class_Ced_Etsy_Products::get_instance();
			$getOrders          = $etsyOrdersInstance->prepareDataForUpdatingInventory( $products_to_sync[0], $shop_id, true );
			unset( $products_to_sync[0] );
			$products_to_sync = array_values( $products_to_sync );
			update_option( 'ced_etsy_chunk_products', $products_to_sync );
		}

	}

	public function ced_etsy_async_update_stock_callback( $stock_update_data ) {
		$context = array( 'source' => 'ced_etsy_async_update_stock_callback' );
		if ( ! empty( $stock_update_data ) && ! empty( $stock_update_data['product_id'] ) && ! empty( $stock_update_data['shop_name'] ) ) {
			$product_id = $stock_update_data['product_id'];
			$shop_name  = $stock_update_data['shop_name'];
			if ( ! empty( $product_id ) && ! empty( $shop_name ) ) {
				$ced_h           = Cedhandler::get_instance();
				$ced_h->dir_name = 'admin/etsy/lib/';
				$ced_h->ced_require( 'etsyProducts.php' );
				$etsyProductsInstance = Class_Ced_Etsy_Products::get_instance();
				$getProduct           = $etsyProductsInstance->prepareDataForUpdatingInventory( $product_id, $shop_name, true );
			}
		}
	}


	/**
	 * Etsy Sync existing products scheduler
	 *
	 * @since    1.0.5
	 */
	public function ced_etsy_sync_existing_products() {

		$hook      = current_action();
		$shop_name = str_replace( 'ced_etsy_sync_existing_products_job_', '', $hook );
		$shop_name = trim( $shop_name );

		if ( empty( $shop_name ) ) {
			$shop_name = get_option( 'ced_etsy_shop_name', '' );
		}
		$shop_id = get_etsy_shop_id( $shop_name );
		$offset  = get_option( 'ced_etsy_get_offset', '' );
		if ( empty( $offset ) ) {
			$offset = 0;
		}
		$query_args = array(
			'offset' => $offset,
			'limit'  => 25,
			'state'  => 'active',
		);
		$action     = "application/shops/{$shop_id}/listings";
		$response   = etsy_request()->get( $action, $shop_name, $query_args );
		if ( isset( $response['results'][0] ) ) {
			foreach ( $response['results'] as $key => $value ) {
				$sku = isset( $value['sku'][0] ) ? $value['sku'][0] : '';
				if ( ! empty( $sku ) ) {
					$product_id = wc_get_product_id_by_sku( $sku );
					if ( $product_id ) {
						$_product = wc_get_product( $product_id );
						if ( 'variation' == $_product->get_type() ) {
							update_post_meta( $_product->get_parent_id(), '_ced_etsy_url_' . $shop_name, $value['url'] );
							update_post_meta( $_product->get_parent_id(), '_ced_etsy_listing_id_' . $shop_name, $value['listing_id'] );
						} else {
							update_post_meta( $product_id, '_ced_etsy_url_' . $shop_name, $value['url'] );
							update_post_meta( $product_id, '_ced_etsy_listing_id_' . $shop_name, $value['listing_id'] );
						}
					}
				}
			}
			if ( isset( $response['pagination']['next_offset'] ) && ! empty( $response['pagination']['next_offset'] ) ) {
				$next_offset = $response['pagination']['next_offset'];
			} else {
				$next_offset = 0;
			}
			update_option( 'ced_etsy_get_offset', $next_offset );
		} else {
			update_option( 'ced_etsy_get_offset', 0 );
		}
	}

	/**
	 * Etsy Refreshing Categories
	 *
	 * @since    1.0.0
	 */
	public function ced_etsy_category_refresh() {
		$check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			$shop_name      = isset( $_POST['shop_name'] ) ? sanitize_text_field( wp_unslash( $_POST['shop_name'] ) ) : '';
			$isShopInActive = ced_etsy_inactive_shops( $shop_name );
			if ( $isShopInActive ) {
				echo json_encode(
					array(
						'status'  => 400,
						'message' => __(
							'Shop is Not Active',
							'product-lister-etsy'
						),
					)
				);
				die;
			}
			$file = CED_ETSY_DIRPATH . 'admin/etsy/lib/etsyCategory.php';
			if ( ! file_exists( $file ) ) {
				return;
			}
			require_once $file;
			$etsyCategoryInstance = Class_Ced_Etsy_Category::get_instance();
			$fetchedCategories    = $etsyCategoryInstance->getEtsyCategories( $shop_name );
			if ( $fetchedCategories ) {
				$categories = $this->CED_ETSY_Manager->StoreCategories( $fetchedCategories, true );
				echo json_encode( array( 'status' => 200 ) );
				wp_die();
			} else {
				echo json_encode( array( 'status' => 400 ) );
				wp_die();
			}
		}
	}

	/**
	 * Etsy Bulk Operations
	 *
	 * @since    1.0.0
	 */
	public function ced_etsy_process_bulk_action() {
		$check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			$CED_ETSY_Manager = $this->CED_ETSY_Manager;
			$shop_name        = isset( $_POST['shopname'] ) ? sanitize_text_field( wp_unslash( $_POST['shopname'] ) ) : '';
			$operation        = isset( $_POST['operation_to_be_performed'] ) ? sanitize_text_field( wp_unslash( $_POST['operation_to_be_performed'] ) ) : '';
			$product_id       = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
			$_title           = wc_get_product( $product_id )->get_title();
			$isShopInActive   = ced_etsy_inactive_shops( $shop_name );
			if ( $isShopInActive ) {
				echo json_encode(
					array(
						'status'  => 400,
						'message' => __(
							'Shop is Not Active',
							'product-lister-etsy'
						),
					)
				);
				die;
			}
			if ( 'upload_product' == $operation ) {

				$restricted = ced_etsy_check_if_limit_reached( $shop_name );
				if ( $restricted ) {
					echo json_encode(
						array(
							'status'  => 400,
							'message' => __(
								'You have reached the maximum limit of syncing 100 products from WooCommerce to Etsy . If you wish to sync more products and manage orders as well upgrade to premium version now ! <p><a href="https://woocommerce.com/products/etsy-integration-for-woocommerce/" target="_blank"><button id="" type="submit" class="button-primary get_preminum">Upgrade Now</button></a></p>',
								'product-lister-etsy'
							),
						)
					);
					die;
				}
				$prodIDs          = $product_id;
				$already_uploaded = get_post_meta( $prodIDs, '_ced_etsy_listing_id_' . $shop_name, true );
				if ( $already_uploaded ) {
					echo json_encode(
						array(
							'status'  => 400,
							'message' => __(
								'Product ' . $prodIDs . ' Already Uploaded',
								'product-lister-etsy'
							),
						)
					);
					die;
				} else {
					$get_product_detail = $CED_ETSY_Manager->prepareProductHtmlForUpload( $prodIDs, $shop_name );
					if ( isset( $get_product_detail['listing_id'] ) ) {
						echo json_encode(
							array(
								'status'  => 200,
								'message' => $get_product_detail['title'] . ' Uploaded Successfully',
								'prodid'  => $prodIDs,
							)
						);
						die;
					} else {
						echo json_encode(
							array(
								'status'  => 400,
								'message' => isset( $get_product_detail['msg'] ) ? $get_product_detail['msg'] : json_encode( $get_product_detail ),
								'prodid'  => $prodIDs,
							)
						);
						die;
					}
				}
			} elseif ( 'update_product' == $operation ) {
				$prodIDs          = $product_id;
				$already_uploaded = get_post_meta( $prodIDs, '_ced_etsy_listing_id_' . $shop_name, true );
				if ( $already_uploaded ) {
					$get_product_detail = $CED_ETSY_Manager->prepareProductHtmlForUpdate( $prodIDs, $shop_name );
					echo json_encode(
						array(
							'status'  => $get_product_detail['status'],
							'message' => $get_product_detail['message'],
							'prodid'  => $prodIDs,
						)
					);
					die;
				} else {
					echo json_encode(
						array(
							'status'  => 400,
							'message' => __(
								$_title . ' not uploaded on etsy . Please upload the product first .',
								'woocommerce-etsy-integration'
							),
						)
					);
					die;
				}
			} elseif ( 'remove_product' == $operation ) {
				$prodIDs          = $product_id;
				$already_uploaded = get_post_meta( $prodIDs, '_ced_etsy_listing_id_' . $shop_name, true );
				if ( $already_uploaded ) {
					$get_product_detail = $CED_ETSY_Manager->prepareProductHtmlForDelete( $prodIDs, $shop_name );
					echo json_encode(
						array(
							'status'  => $get_product_detail['status'],
							'message' => $get_product_detail['message'],
							'prodid'  => $prodIDs,
						)
					);
					die;
				} else {
					echo json_encode(
						array(
							'status'  => 400,
							'message' => __(
								$_title . ' not uploaded on etsy . Please upload the product first .',
								'woocommerce-etsy-integration'
							),
						)
					);
					die;
				}
			} elseif ( 'update_inventory' == $operation ) {
				$prodIDs          = $product_id;
				$already_uploaded = get_post_meta( $prodIDs, '_ced_etsy_listing_id_' . $shop_name, true );
				if ( $already_uploaded ) {
					$get_product_detail = $CED_ETSY_Manager->prepareProductHtmlForUpdateInventory( $prodIDs, $shop_name );
					echo json_encode(
						array(
							'status'  => $get_product_detail['status'],
							'message' => $get_product_detail['message'],
							'prodid'  => $prodIDs,
						)
					);
					die;
				} else {
					echo json_encode(
						array(
							'status'  => 400,
							'message' => __(
								$_title . ' not uploaded on etsy . Please upload the product first .',
								'woocommerce-etsy-integration'
							),
						)
					);
					die;
				}
			} elseif ( 'update_image' == $operation ) {
				$prodIDs          = $product_id;
				$already_uploaded = get_post_meta( $prodIDs, '_ced_etsy_listing_id_' . $shop_name, true );
				if ( $already_uploaded ) {
					$get_product_detail = $CED_ETSY_Manager->ced_update_images_on_etsy( $prodIDs, $shop_name );
					echo json_encode(
						array(
							'status'  => $get_product_detail['status'],
							'message' => $get_product_detail['message'],
							'prodid'  => $prodIDs,
						)
					);
					die;
				} else {
					echo json_encode(
						array(
							'status'  => 400,
							'message' => __(
								$_title . ' not uploaded on etsy . Please upload the product first .',
								'woocommerce-etsy-integration'
							),
						)
					);
					die;
				}
			} elseif ( 'unlink_product' == $operation ) {
				$already_uploaded = get_post_meta( $product_id, '_ced_etsy_listing_id_' . $shop_name, true );
				if ( $already_uploaded ) {
					delete_post_meta( $product_id, '_ced_etsy_listing_id_' . $shop_name );
					delete_post_meta( $product_id, '_ced_etsy_pro_state_' . $shop_name );
					delete_post_meta( $product_id, '_ced_etsy_url_' . $shop_name );
					delete_post_meta( $product_id, '_ced_etsy_listing_data_' . $shop_name );
					delete_post_meta( $product_id, '_ced_etsy_pro_state_' . $shop_name );
					$response['status']  = 200;
					$response['message'] = 'Unlinked successfully';
				}
				echo json_encode(
					array(
						'status'  => 200,
						'message' => __(
							$_title . ' Successfully Unlinked from Etsy.',
							'woocommerce-etsy-integration'
						),
					)
				);
				die;
			}
		}
	}



	/**
	 * ******************************************************************
	 * Function to Delete for mapped profiles in the profile-view page
	 * ******************************************************************
	 *
	 *  @since version 1.0.8.
	 */
	public function ced_esty_delete_mapped_profiles() {

		$check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			global $wpdb;
			$profile_id = isset( $_POST['profile_id'] ) ? sanitize_text_field( $_POST['profile_id'] ) : '';
			$shop_name  = isset( $_POST['shop_name'] ) ? sanitize_text_field( $_POST['shop_name'] ) : '';
			$tableName  = $wpdb->prefix . 'ced_etsy_profiles';
			$result     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM  {$wpdb->prefix}ced_etsy_profiles WHERE `shop_name`= %d ", $shop_name ), 'ARRAY_A' );
			foreach ( $result as $key => $value ) {
				if ( $value['id'] === $profile_id ) {
					$wpdb->query(
						$wpdb->prepare(
							" DELETE FROM {$wpdb->prefix}ced_etsy_profiles WHERE 
							`id` = %s AND shop_name = %s",
							$value['id'],
							$shop_name
						)
					);
					echo json_encode(
						array(
							'status'  => 200,
							'message' => __(
								'Profile Deleted Successfully !',
								'product-lister-etsy'
							),
						)
					);
				}
			}
			die;
		}
	}


	public function ced_add_admin_bar_items( $admin_bar ) {
		$svg_code       = CED_ETSY_URL . 'admin/images/etsy.png';
		$admin_php_path = admin_url( '/admin.php?page=ced_etsy_lister' );
		$admin_bar->add_menu(
			array(
				'id'    => 'product-lister-etsy',
				'title' => 'Etsy',
				'href'  => $admin_php_path,
				'meta'  => array(
					'class' => 'ced_etsy_menu_wrapper',
					'title' => 'Product Lister',
				),
			)
		);

	}



	/**
	 * Etsy Fetch Orders
	 *
	 * @since    1.0.0
	 */
	public function ced_etsy_get_orders() {
		// ini_set('display_errors', 1);
		// ini_set('display_startup_errors', 1);
		// error_reporting(E_ALL);
	    $check_ajax = check_ajax_referer( 'ced-etsy-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {
			$shop_id    = isset( $_POST['shopid'] ) ? sanitize_text_field( wp_unslash( $_POST['shopid'] ) ) : '';
			$get_orders = $this->ced_etsy_order->get_orders( $shop_id );
			if ( ! empty( $get_orders ) ) {
				$createOrder = $etsyOrdersInstance->createLocalOrder( $get_orders, $shop_id );
			}
		}
	}



}
