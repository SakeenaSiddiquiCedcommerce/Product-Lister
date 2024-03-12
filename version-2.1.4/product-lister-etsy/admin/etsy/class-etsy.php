<?php
/**
 * Main class for handling reqests.
 *
 * @since      1.0.0
 *
 * @package    Woocommerce Etsy Integration
 * @subpackage Woocommerce Etsy Integration/marketplaces/etsy
 */

if ( ! class_exists( 'CED_ETSY_Manager' ) ) {

	/**
	 * Single product related functionality.
	 *
	 * Manage all single product related functionality required for listing product on marketplaces.
	 *
	 * @since      1.0.0
	 * @package    Woocommerce Etsy Integration
	 * @subpackage Woocommerce Etsy Integration/marketplaces/etsy
	 */
	class CED_ETSY_Manager {

		/**
		 * The Instace of CED_ETSY_etsy_Manager.
		 *
		 * @since    1.0.0
		 * @var      $_instance   The Instance of CED_ETSY_etsy_Manager class.
		 */
		private static $_instance;
		private static $authorization_obj;
		private static $client_obj;
		/**
		 * CED_ETSY_etsy_Manager Instance.
		 *
		 * Ensures only one instance of CED_ETSY_etsy_Manager is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 * @return CED_ETSY_etsy_Manager instance.
		 */
		public static function get_instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public $marketplaceID   = 'etsy';
		public $marketplaceName = 'Etsy';


		/**
		 * Constructor.
		 *
		 * Registering actions and hooks for etsy.
		 *
		 * @since 1.0.0
		 */

		public function __construct() {
			// ini_set('display_errors', '1');
			// ini_set('display_startup_errors', '1');
			// error_reporting(E_ALL);

			$this->loadDependency();
			add_action( 'admin_init', array( $this, 'ced_etsy_schedules' ) );
			add_filter( 'woocommerce_duplicate_product_exclude_meta', array( $this, 'woocommerce_duplicate_product_exclude_meta' ) );
			add_action( 'updated_post_meta', array( $this, 'ced_relatime_sync_inventory_to_etsy' ), 12, 4 );
			add_action( 'admin_init', array( $this, 'ced_etsy_get_token_and_shop_data' ), 999, 3 );
			add_action( 'ced_etsy_refresh_token', array( $this, 'ced_etsy_refresh_token_action' ) );
		}

		/**
		 * Refresh Etsy token
		 *
		 * @param string $shop_name
		 * @return void
		 */
		public function ced_etsy_refresh_token_action( $shop_name = '' ) {
			if ( ! $shop_name || get_transient( 'ced_etsy_token_' . $shop_name ) ) {
				return;
			}
			$user_details  = get_option( 'ced_etsy_details', array() );
			$refresh_token = isset( $user_details[ $shop_name ]['details']['token']['refresh_token'] ) ? $user_details[ $shop_name ]['details']['token']['refresh_token'] : '';

			$query_args = array(
				'grant_type'    => 'refresh_token',
				'client_id'     => etsy_request()->client_id,
				'refresh_token' => $refresh_token,
			);
			$parameters = $query_args;
			$action     = 'public/oauth/token';
			$response   = etsy_request()->ced_remote_request( $shop_name, $action, 'POST', $parameters, $query_args );
			if ( isset( $response['access_token'] ) && ! empty( $response['access_token'] ) ) {
				$user_details[ $shop_name ]['details']['token'] = $response;
				update_option( 'ced_etsy_details', $user_details );
				set_transient( 'ced_etsy_token_' . $shop_name, $response, (int) $response['expires_in'] );
			}

		}

		/**
		 * Get Etsy token and shop data
		 *
		 * @param string $shop_name
		 * @return void
		 */
		public function ced_etsy_get_token_and_shop_data() {
			if ( ! isset( $_GET['state'] ) && empty( $_GET['code'] ) ) {
				return;
			}
			$code       = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
			$verifier   = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
			$query_args = array(
				'grant_type'    => 'authorization_code',
				'client_id'     => 'ghvcvauxf2taqidkdx2sw4g4',
				'redirect_uri'  => 'https://woodemo.cedcommerce.com/woocommerce/authorize/etsy/authorize.php',
				'code'          => $code,
				'code_verifier' => $verifier,
			);
			$parameters = $query_args;
			$shop_name  = get_option( 'ced_etsy_shop_name', '' );
			$response   = etsy_request()->ced_remote_request( $shop_name, 'public/oauth/token', 'POST', $parameters, $query_args );

			if ( isset( $response['access_token'] ) && ! empty( $response['access_token'] ) ) {
				$query_args = array(
					'shop_name' => $shop_name,
				);
				$shop       = etsy_request()->ced_remote_request( '', 'application/shops', 'GET', $query_args );
				if ( isset( $shop['results'][0] ) ) {

					set_transient( 'ced_etsy_token_' . $shop_name, $response, (int) $response['expires_in'] );
					$user_details               = get_option( 'ced_etsy_details', array() );
					$user_id                    = isset( $shop['results'][0]['user_id'] ) ? $shop['results'][0]['user_id'] : '';
					$user_name                  = isset( $shop['results'][0]['login_name'] ) ? $shop['results'][0]['login_name'] : '';
					$shop_id                    = isset( $shop['results'][0]['shop_id'] ) ? $shop['results'][0]['shop_id'] : '';
					$info                       = array(
						'details' => array(
							'ced_etsy_shop_name'      => $shop_name,
							'user_id'                 => $user_id,
							'user_name'               => $user_name,
							'shop_id'                 => $shop_id,
							'ced_etsy_keystring'      => etsy_request()->client_id,
							'ced_etsy_shared_string'  => etsy_request()->client_secret,
							'ced_shop_account_status' => 'Active',
							'token'                   => $response,
							'shop_info'               => $shop,
						),
					);
					$user_details[ $shop_name ] = $info;

					if ( count( $user_details ) < 2 ) {
						update_option( 'ced_etsy_details', $user_details );
					}
				}
			}

			wp_redirect( admin_url( 'admin.php?page=ced_etsy_lister' ) );
			exit;

		}


		public function ced_etsy_display_banner() {
			display_banner();
		}

		public function woocommerce_duplicate_product_exclude_meta( $metakeys = array() ) {
			$shop_name  = get_option( 'ced_etsy_shop_name', '' );
			$metakeys[] = '_ced_etsy_listing_id_' . $shop_name;
			return $metakeys;
		}

		public function ced_etsy_schedules() {
			if ( isset( $_GET['shop_name'] ) && ! empty( $_GET['shop_name'] ) ) {
				$shop_name = sanitize_text_field( $_GET['shop_name'] );
				if ( ! wp_get_schedule( 'ced_etsy_sync_existing_products_job_' . $shop_name ) ) {
					wp_schedule_event( time(), 'ced_etsy_6min', 'ced_etsy_sync_existing_products_job_' . $shop_name );
				}

				$renderDataOnGlobalSettings   = get_option( 'ced_etsy_global_settings', array() );
				$update_tracking              = isset( $renderDataOnGlobalSettings[ $shop_name ]['update_tracking'] ) ? $renderDataOnGlobalSettings[ $shop_name ]['update_tracking'] : '';
				$ced_etsy_auto_upload_product = isset( $renderDataOnGlobalSettings[ $shop_name ]['ced_etsy_auto_upload_product'] ) ? $renderDataOnGlobalSettings[ $shop_name ]['ced_etsy_auto_upload_product'] : '';
				if ( ! wp_get_schedule( 'ced_etsy_auto_upload_products_' . $shop_name ) && 'on' == $ced_etsy_auto_upload_product ) {
					wp_schedule_event( time(), 'ced_etsy_20min', 'ced_etsy_auto_upload_products_' . $shop_name );
				} else {
					wp_clear_scheduled_hook( 'ced_etsy_auto_upload_products_' . $shop_name );
				}

				if ( ! wp_get_schedule( 'ced_etsy_auto_submit_shipment' ) && 'on' == $update_tracking ) {
					wp_schedule_event( time(), 'ced_etsy_30min', 'ced_etsy_auto_submit_shipment' );
				} else {
					wp_clear_scheduled_hook( 'ced_etsy_auto_submit_shipment' );
				}
			}
		}

		/**
		 * ******************************************************
		 * Real time Sync product form Wooocommerce to Etsy shop.
		 * ******************************************************
		 *
		 * @param $meta_id    Udpated product meta meta_id of the product.
		 * @param $product_id Updated meta value of the product id.
		 * @param $meta_key   Update products meta key.
		 * @param $mta_value  Udpated changed meta value of the post.
		 */
		public function ced_relatime_sync_inventory_to_etsy( $meta_id, $product_id, $meta_key, $meta_value ) {

			// If tha is changed by _stock only.
			if ( '_stock' == $meta_key || '_price' == $meta_key ) {
				// Active shop name
				$shop_name = get_option( 'ced_etsy_shop_name', '' );

				$_product = wc_get_product( $product_id );
				if ( ! wp_get_schedule( 'ced_etsy_inventory_scheduler_job_' . $shop_name ) || ! is_object( $_product ) ) {
					return;
				}
				// All products by product id
				// check if it has variations.
				if ( $_product->get_type() == 'variation' ) {
					$product_id = $_product->get_parent_id();
				}
				/**
				 * *******************************************
				 *   CALLING FUNCTION TO UDPATE THE INVENTORY
				 * *******************************************
				 */
				$this->prepareProductHtmlForUpdateInventory( array( $product_id ), $shop_name, $is_sync );
			}
		}

		/**
		 * Etsy Loading dependencies
		 *
		 * @since    1.0.0
		 */
		public function loadDependency() {
			$ced_h           = new Cedhandler();
			$ced_h->dir_name = 'admin/etsy/lib/';
			$ced_h->ced_require( 'etsyProducts' );
			$this->etsyProductsInstance = Class_Ced_Etsy_Products::get_instance();
		}

		/**
		 * Ced Etsy Fetch Categories
		 *
		 * @since    1.0.0
		 */
		public function ced_etsy_get_categories() {
			$ced_h           = new Cedhandler();
			$ced_h->dir_name = 'admin/etsy/lib/';
			$ced_h->ced_require( 'etsyCategory' );
			$etsyCategoryInstance = Class_Ced_Etsy_Category::get_instance();
			$fetchedCategories    = $etsyCategoryInstance->getEtsyCategories();
			$categories           = $this->StoreCategories( $fetchedCategories );

		}

		/**
		 * Etsy Storing Categories
		 *
		 * @since    1.0.0
		 */
		public function StoreCategories( $fetchedCategories, $ajax = '' ) {
			foreach ( $fetchedCategories['results'] as $key => $value ) {
				if ( count( $value['children_ids'] ) > 0 ) {
					$arr1[] = array(
						'id'       => $value['id'],
						'name'     => $value['name'],
						'path'     => $value['path'],
						'children' => count( $value['children_ids'] ),
					);
				} else {
					$arr1[] = array(
						'id'       => $value['id'],
						'name'     => $value['name'],
						'path'     => $value['path'],
						'children' => 0,
					);
				}
				foreach ( $value['children'] as $key1 => $value1 ) {
					if ( count( $value1['children_ids'] ) > 0 ) {
						$arr2[] = array(
							'parent_id' => $value['id'],
							'id'        => $value1['id'],
							'name'      => $value1['name'],
							'path'      => $value1['path'],
							'children'  => count( $value1['children_ids'] ),
						);
					} else {
						$arr2[] = array(
							'parent_id' => $value['id'],
							'id'        => $value1['id'],
							'name'      => $value1['name'],
							'path'      => $value1['path'],
							'children'  => 0,
						);
					}
					foreach ( $value1['children'] as $key2 => $value2 ) {
						if ( count( $value2['children_ids'] ) > 0 ) {
							$arr3[] = array(
								'parent_id' => $value1['id'],
								'id'        => $value2['id'],
								'name'      => $value2['name'],
								'path'      => $value2['path'],
								'children'  => count( $value2['children_ids'] ),
							);
						} else {
							$arr3[] = array(
								'parent_id' => $value1['id'],
								'id'        => $value2['id'],
								'name'      => $value2['name'],
								'path'      => $value2['path'],
								'children'  => 0,
							);
						}
						foreach ( $value2['children'] as $key3 => $value3 ) {
							if ( count( $value3['children_ids'] ) > 0 ) {
								$arr4[] = array(
									'parent_id' => $value2['id'],
									'id'        => $value3['id'],
									'name'      => $value3['name'],
									'path'      => $value3['path'],
									'children'  => count( $value3['children_ids'] ),
								);
							} else {
								$arr4[] = array(
									'parent_id' => $value2['id'],
									'id'        => $value3['id'],
									'name'      => $value3['name'],
									'path'      => $value3['path'],
									'children'  => 0,
								);
							}
							foreach ( $value3['children'] as $key4 => $value4 ) {
								if ( count( $value4['children_ids'] ) > 0 ) {
									$arr5[] = array(
										'parent_id' => $value3['id'],
										'id'        => $value4['id'],
										'name'      => $value4['name'],
										'path'      => $value4['path'],
										'children'  => count( $value4['children_ids'] ),
									);
								} else {
									$arr5[] = array(
										'parent_id' => $value3['id'],
										'id'        => $value4['id'],
										'name'      => $value4['name'],
										'path'      => $value4['path'],
										'children'  => 0,
									);
								}
								foreach ( $value4['children'] as $key5 => $value5 ) {
									if ( count( $value5['children_ids'] ) > 0 ) {
										$arr6[] = array(
											'parent_id' => $value4['id'],
											'id'        => $value5['id'],
											'name'      => $value5['name'],
											'path'      => $value5['path'],
											'children'  => count( $value5['children_ids'] ),
										);
									} else {
										$arr6[] = array(
											'parent_id' => $value4['id'],
											'id'        => $value5['id'],
											'name'      => $value5['name'],
											'path'      => $value5['path'],
											'children'  => 0,
										);
									}
									foreach ( $value5['children'] as $key6 => $value6 ) {
										if ( is_array( $value6['children_ids'] ) && ! empty( $value6['children_ids'] ) ) {

											$arr7[] = array(
												'parent_id' => $value5['id'],
												'id'       => $value6['id'],
												'name'     => $value6['name'],
												'path'     => $value6['path'],
												'children' => count( $value6['children_ids'] ),
											);

										} else {
											$arr7[] = array(
												'parent_id' => $value5['id'],
												'id'       => $value6['id'],
												'name'     => $value6['name'],
												'path'     => $value6['path'],
												'children' => 0,
											);
										}
									}
								}
							}
						}
					}
				}
			}

			$folderName = CED_ETSY_DIRPATH . 'admin/etsy/lib/json/';

			$catFirstLevelFile = $folderName . 'categoryLevel-1.json';
			file_put_contents( $catFirstLevelFile, json_encode( $arr1 ) );
			$catSecondLevelFile = $folderName . 'categoryLevel-2.json';
			file_put_contents( $catSecondLevelFile, json_encode( $arr2 ) );

			$catThirdLevelFile = $folderName . 'categoryLevel-3.json';
			file_put_contents( $catThirdLevelFile, json_encode( $arr3 ) );
			$catFourthLevelFile = $folderName . 'categoryLevel-4.json';
			file_put_contents( $catFourthLevelFile, json_encode( $arr4 ) );

			$catFifthLevelFile = $folderName . 'categoryLevel-5.json';
			file_put_contents( $catFifthLevelFile, json_encode( $arr5 ) );
			$catSixthLevelFile = $folderName . 'categoryLevel-6.json';
			file_put_contents( $catSixthLevelFile, json_encode( $arr6 ) );

			$catSeventhLevelFile = $folderName . 'categoryLevel-7.json';
			file_put_contents( $catSeventhLevelFile, json_encode( $arr7 ) );

			update_option( 'ced_etsy_categories_fetched', 'Yes' );
			if ( $ajax ) {
				return 'true';
				die;
			}
		}

		/**
		 * Etsy Create Auto Profiles
		 *
		 * @since    1.0.0
		 */
		public function ced_etsy_createAutoProfiles( $etsyMappedCategories = array(), $etsyMappedCategoriesName = array(), $etsyStoreId = '' ) {
			global $wpdb;

			$wooStoreCategories          = get_terms( 'product_cat' );
			$alreadyMappedCategories     = get_option( 'ced_woo_etsy_mapped_categories_' . $etsyStoreId, array() );
			$alreadyMappedCategoriesName = get_option( 'ced_woo_etsy_mapped_categories_name_' . $etsyStoreId, array() );

			if ( ! empty( $etsyMappedCategories ) ) {
				foreach ( $etsyMappedCategories as $key => $value ) {
					$profileAlreadyCreated = get_term_meta( $key, 'ced_etsy_profile_created_' . $etsyStoreId, true );
					$createdProfileId      = get_term_meta( $key, 'ced_etsy_profile_id_' . $etsyStoreId, true );
					if ( ! empty( $profileAlreadyCreated ) && 'yes' == $createdProfileId ) {

						$newProfileNeedToBeCreated = $this->checkIfNewProfileNeedToBeCreated( $key, $value, $etsyStoreId );

						if ( ! $newProfileNeedToBeCreated ) {
							continue;
						} else {
							$this->resetMappedCategoryData( $key, $value, $etsyStoreId );
						}
					}

					$wooCategories      = array();
					$categoryAttributes = array();

					$profileName = isset( $etsyMappedCategoriesName[ $value ] ) ? $etsyMappedCategoriesName[ $value ] : 'Profile for etsy - Category Id : ' . $value;

					$profile_id = $wpdb->get_results( $wpdb->prepare( "SELECT `id` FROM {$wpdb->prefix}ced_etsy_profiles WHERE `profile_name` = %s", $profileName ), 'ARRAY_A' );

					if ( ! isset( $profile_id[0]['id'] ) && empty( $profile_id[0]['id'] ) ) {
						$is_active       = 1;
						$marketplaceName = 'etsy';

						foreach ( $etsyMappedCategories as $key1 => $value1 ) {
							if ( $value1 == $value ) {
								$wooCategories[] = $key1;
							}
						}

						$profileData    = array();
						$profileData    = $this->prepareProfileData( $etsyStoreId, $value, $wooCategories );
						$profileDetails = array(
							'profile_name'   => $profileName,
							'profile_status' => 'active',
							'shop_name'      => $etsyStoreId,
							'profile_data'   => json_encode( $profileData ),
							'woo_categories' => json_encode( $wooCategories ),
						);
						$profileId      = $this->insertetsyProfile( $profileDetails );
					} else {
						$wooCategories      = array();
						$profileId          = $profile_id[0]['id'];
						$profile_categories = $wpdb->get_results( $wpdb->prepare( "SELECT `woo_categories` FROM {$wpdb->prefix}ced_etsy_profiles WHERE `id` = %d ", $profileId ), 'ARRAY_A' );
						$wooCategories      = json_decode( $profile_categories[0]['woo_categories'], true );
						$wooCategories[]    = $key;
						$table_name         = $wpdb->prefix . 'ced_etsy_profiles';
						$wpdb->update(
							$table_name,
							array(
								'woo_categories' => json_encode( array_unique( $wooCategories ) ),
							),
							array( 'id' => $profileId )
						);
					}
					foreach ( $wooCategories as $key12 => $value12 ) {
						update_term_meta( $value12, 'ced_etsy_profile_created_' . $etsyStoreId, 'yes' );
						update_term_meta( $value12, 'ced_etsy_profile_id_' . $etsyStoreId, $profileId );
						update_term_meta( $value12, 'ced_etsy_mapped_category_' . $etsyStoreId, $value );
					}
				}
			}
		}

		/**
		 * Etsy Insert Profiles In database
		 *
		 * @since    1.0.0
		 */
		public function insertetsyProfile( $profileDetails ) {

			global $wpdb;
			$profileTableName = $wpdb->prefix . 'ced_etsy_profiles';

			$wpdb->insert( $profileTableName, $profileDetails );

			$profileId = $wpdb->insert_id;
			return $profileId;
		}

		/**
		 * Etsy Check if Profile Need to be Created
		 *
		 * @since    1.0.0
		 */
		public function checkIfNewProfileNeedToBeCreated( $wooCategoryId = '', $etsyCategoryId = '', $etsyStoreId = '' ) {

			$oldetsyCategoryMapped = get_term_meta( $wooCategoryId, 'ced_etsy_mapped_category_' . $etsyStoreId, true );
			if ( $oldetsyCategoryMapped == $etsyCategoryId ) {
				return false;
			} else {
				return true;
			}
		}

		/**
		 * Etsy Update Mapped Category data
		 *
		 * @since    1.0.0
		 */
		public function resetMappedCategoryData( $wooCategoryId = '', $etsyCategoryId = '', $etsyStoreId = '' ) {

			update_term_meta( $wooCategoryId, 'ced_etsy_mapped_category_' . $etsyStoreId, $etsyCategoryId );

			delete_term_meta( $wooCategoryId, 'ced_etsy_profile_created_' . $etsyStoreId );

			$createdProfileId = get_term_meta( $wooCategoryId, 'ced_etsy_profile_id_' . $etsyStoreId, true );

			delete_term_meta( $wooCategoryId, 'ced_etsy_profile_id_' . $etsyStoreId );

			$this->removeCategoryMappingFromProfile( $createdProfileId, $wooCategoryId );
		}

		/**
		 * Etsy Remove previous mapped profile
		 *
		 * @since    1.0.0
		 */
		public function removeCategoryMappingFromProfile( $createdProfileId = '', $wooCategoryId = '' ) {

			global $wpdb;
			$profileTableName = $wpdb->prefix . 'ced_etsy_profiles';
			$profile_data     = $wpdb->get_results( $wpdb->prepare( "SELECT `woo_categories` FROM {$wpdb->prefix}ced_etsy_profiles WHERE `id`=%s ", $createdProfileId ), 'ARRAY_A' );

			if ( is_array( $profile_data ) ) {

				$profile_data  = isset( $profile_data[0] ) ? $profile_data[0] : $profile_data;
				$wooCategories = isset( $profile_data['woo_categories'] ) ? json_decode( $profile_data['woo_categories'], true ) : array();
				if ( is_array( $wooCategories ) && ! empty( $wooCategories ) ) {
					$categories = array();
					foreach ( $wooCategories as $key => $value ) {
						if ( $value != $wooCategoryId ) {
							$categories[] = $value;
						}
					}
					$categories = json_encode( $categories );
					$wpdb->update( $profileTableName, array( 'woo_categories' => $categories ), array( 'id' => $createdProfileId ) );
				}
			}
		}

		/**
		 * Etsy Prepare Profile data
		 *
		 * @since    1.0.0
		 */
		public function prepareProfileData( $etsyStoreId, $etsyCategoryId, $wooCategories = '' ) {

			$globalSettings     = get_option( 'ced_etsy_global_settings', array() );
			$shipping_templates = get_option( 'ced_etsy_details', array() );

			$etsyShopGlobalSettings     = isset( $globalSettings[ $etsyStoreId ] ) ? $globalSettings[ $etsyStoreId ] : array();
			$profileData                = array();
			$selected_shipping_template = isset( $shipping_templates[ $etsyStoreId ]['shippingTemplateId'] ) ? $shipping_templates[ $etsyStoreId ]['shippingTemplateId'] : null;

			$profileData['_umb_etsy_category']['default']         = $etsyCategoryId;
			$profileData['_umb_etsy_category']['metakey']         = null;
			$profileData['_ced_etsy_shipping_profile']['default'] = $selected_shipping_template;
			$profileData['_ced_etsy_shipping_profile']['metakey'] = null;

			foreach ( $etsyShopGlobalSettings['product_data'] as $key => $value ) {
				$profileData[ $key ]['default'] = isset( $value['default'] ) ? $value['default'] : '';
				$profileData[ $key ]['metakey'] = isset( $value['metakey'] ) ? $value['metakey'] : '';

			}

			return $profileData;
		}


		/**
		 * Etsy prepare data for uploading products
		 *
		 * @since    1.0.0
		 */
		public function prepareProductHtmlForUpload( $proIDs = array(), $shop_name, $is_sync = false ) {
			if ( ! is_array( $proIDs ) ) {
				$proIDs = array( $proIDs );
			}
			$response = $this->etsyProductsInstance->prepareDataForUploading( $proIDs, $shop_name, $is_sync );
			return $response;

		}

		/**
		 * Etsy prepare data for updating products
		 *
		 * @since    1.0.0
		 */
		public function prepareProductHtmlForUpdate( $proIDs = array(), $shop_name ) {
			if ( ! is_array( $proIDs ) ) {
				$proIDs = array( $proIDs );
			}
			$response = $this->etsyProductsInstance->prepareDataForUpdating( $proIDs, $shop_name );
			return $response;
		}

		/**
		 * Etsy prepare data for updating inventory of products
		 *
		 * @since    1.0.0
		 */
		public function prepareProductHtmlForUpdateInventory( $proIDs = array(), $shop_name = '', $is_sync = false ) {

			if ( ! is_array( $proIDs ) ) {
				$proIDs = array( $proIDs );
			}

			if ( empty( $shop_name ) ) {
				$shop_name = get_option( 'ced_etsy_shop_name', '' );
			}
			$response = $this->etsyProductsInstance->prepareDataForUpdatingInventory( $proIDs, $shop_name, $is_sync );
			return $response;
		}


		/**
		 * Etsy prepare data for deleting products
		 *
		 * @since    1.0.0
		 */
		public function prepareProductHtmlForDelete( $proIDs = array(), $shop_name ) {
			if ( ! is_array( $proIDs ) ) {
				$proIDs = array( $proIDs );
			}
			$response = $this->etsyProductsInstance->prepareDataForDelete( $proIDs, $shop_name );
			return $response;
		}

		/**
		 * Etsy prepare data for deactivating products
		 *
		 * @since    1.0.0
		 */
		public function prepareProductHtmlForDeactivate( $proIDs = array(), $shop_name ) {
			if ( ! is_array( $proIDs ) ) {
				$proIDs = array( $proIDs );
			}
			$response = $this->etsyProductsInstance->deactivate_products( $proIDs, $shop_name );
			return $response;
		}

		public function ced_update_images_on_etsy( $proIDs = array(), $shop_name ) {
			if ( ! is_array( $proIDs ) ) {
				$proIDs = array( $proIDs );
			}
			$response = $this->etsyProductsInstance->update_images_on_etsy( $proIDs, $shop_name );
			return $response;
		}

	}
}
