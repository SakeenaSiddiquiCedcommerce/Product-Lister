<?php
$ced_h           = new Cedhandler();
$ced_h->dir_name = '/admin/etsy/lib/';
$ced_h->ced_require( 'class-ced-etsy-request' );
if ( ! class_exists( 'Class_Ced_Etsy_Products' ) ) {
	class Class_Ced_Etsy_Products extends Ced_Etsy_Request {

		public static $_instance;
		private $renderDataOnGlobalSettings;
		private $saved_etsy_details;
		public $ced_product;
		private $l_id;
		private $data;
		private $upload_response;
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

		public function __construct() {
			$this->renderDataOnGlobalSettings = get_option( 'ced_etsy_global_settings', '' );
			$this->saved_etsy_details         = get_option( 'ced_etsy_details', array() );
			$this->product_log                = get_option( 'ced_etsy_product_logs', '' );
			if ( empty( $this->product_log ) ) {
				$this->product_log = array();
			} else {
				$this->product_log = json_decode( $this->product_log, true );
			}

			if ( ! is_array( $this->saved_etsy_details ) ) {
				$this->saved_etsy_details = array();
			}

		}


		/**
		 * ********************************************
		 * Function for products data to be uploaded.
		 * ********************************************
		 *
		 * @since 1.0.0
		 *
		 * @param array  $prodIDs Checked Product ids.
		 * @param string $shopName Active Shop Name.
		 */

		public function prepareDataForUploading( $proIDs = array(), $shop_name = '', $is_sync = false ) {
			if ( is_array( $proIDs ) && ! empty( $proIDs ) ) {
				$shop_name = trim( $shop_name );
				self::prepareItems( $proIDs, $shop_name, $is_sync );
				$response = $this->upload_response;
				return $response;

			}
		}

		/**
		 * *****************************************************
		 * Function for preparing product data to be uploaded.
		 * *****************************************************
		 *
		 * @since 1.0.0
		 *
		 * @param array  $prodIDs Checked Product ids
		 * @param string $shopName Active Shop Name
		 *
		 * @return Uploaded Ids
		 */
		private function prepareItems( $proIDs = array(), $shop_name = '', $is_sync ) {

			foreach ( $proIDs as $key => $value ) {
				$productData      = wc_get_product( $value );
				$image_id         = get_post_thumbnail_id( $value );
				$productType      = $productData->get_type();
				$alreadyUploaded  = false;
				$already_uploaded = get_post_meta( $value, '_ced_etsy_listing_id_' . $shop_name, true );
				if ( $already_uploaded ) {
					continue;
				}
				if ( 'variable' == $productType ) {
					$this->data = parent::get_formatted_data( $value, $shop_name );
					if ( isset( $this->data['has_error'] ) ) {
						$this->upload_response['msg'] = $this->data['error'];
						continue;
					}
					self::doupload( $value, $shop_name );
					$response = $this->upload_response;
					if ( isset( $response['listing_id'] ) ) {
						$listingID = isset( $response['listing_id'] ) ? $response['listing_id'] : '';
						update_post_meta( $value, '_ced_etsy_listing_id_' . $shop_name, $response['listing_id'] );
						update_post_meta( $value, '_ced_etsy_url_' . $shop_name, $response['url'] );
						update_post_meta( $value, '_ced_etsy_listing_data_' . $shop_name, json_encode( $response ) );
						$var_response = $this->update_variation_sku_to_etsy( $listingID, $value, $shop_name, false, false );
						if ( ! isset( $var_response['products'][0]['product_id'] ) ) {
							$response = $var_response;
							$this->prepareDataForDelete( array( $value ), $shop_name, false );
							foreach ( $var_response as $key => $_value ) {
								$error        = array();
								$error['msg'] = isset( $_value ) ? ucwords( str_replace( '_', ' ', $_value ) ) : '';

								$this->upload_response = $error;

							}
						}
						$this->ced_etsy_upload_attributes( $value, $listingID, $shop_name );
						$this->ced_etsy_prep_and_upload_img( $value, $shop_name, $listingID );
						if ( 'active' == parent::get_state() ) {
							$activate = $this->ced_etsy_activate_product( $value, $shop_name );
						}
					}

					global $activity;
					$activity->action        = 'Upload';
					$activity->type          = 'product';
					$activity->input_payload = $this->data;
					$activity->response      = $response;
					$activity->post_id       = $value;
					$activity->shop_name     = $shop_name;
					$activity->is_auto       = $is_sync;
					$activity->post_title    = $this->data['title'];
					$activity->execute();
				} elseif ( 'simple' == $productType ) {
					$this->data = parent::get_formatted_data( $value, $shop_name );
					if ( isset( $this->data['has_error'] ) ) {
						$this->upload_response['msg'] = $this->data['error'];
						return $this->upload_response;

					}
					self::doupload( $value, $shop_name );
					$response = $this->upload_response;
					if ( isset( $response['listing_id'] ) ) {
						$listingID = isset( $response['listing_id'] ) ? $response['listing_id'] : '';
						update_post_meta( $value, '_ced_etsy_listing_id_' . $shop_name, $response['listing_id'] );
						update_post_meta( $value, '_ced_etsy_url_' . $shop_name, $response['url'] );
						update_post_meta( $value, '_ced_etsy_listing_data_' . $shop_name, json_encode( $response ) );
						if ( 'active' == parent::get_state() ) {
							$activate = $this->ced_etsy_activate_product( $value, $shop_name );
						}
						if ( $this->is_downloadable ) {
							$digital_response = $this->ced_upload_downloadable( $value, $shop_name, $listingID );
						}
					}
					$this->ced_etsy_upload_attributes( $value, $listingID, $shop_name );
					$this->ced_etsy_prep_and_upload_img( $value, $shop_name, $listingID );
					global $activity;
					$activity->action        = 'Upload';
					$activity->type          = 'product';
					$activity->input_payload = $this->data;
					$activity->response      = $response;
					$activity->post_id       = $value;
					$activity->shop_name     = $shop_name;
					$activity->is_auto       = $is_sync;
					$activity->post_title    = $this->data['title'];
					$activity->execute();
				}
			}
			return $this->upload_response;
		}

		private function ced_etsy_upload_attributes( $productId, $listing_id, $shop_name ) {
			if ( isset( $productId ) ) {
				if ( isset( $listing_id ) ) {
					// $client = ced_etsy_getOauthClientObject( $shop_name );
					parent::get_formatted_data( $productId, $shop_name );
					$categoryId = (int) parent::fetch_meta_value( $productId, '_umb_etsy_category' );
					if ( isset( $categoryId ) ) {
						$params = array( 'taxonomy_id' => $categoryId );
						// $success                   = $client->CallAPI( "https://openapi.etsy.com/v2/taxonomy/seller/{$categoryId}/properties", 'GET', $params, array( 'FailOnAccessError' => true ), $getTaxonomyNodeProperties );
						$getTaxonomyNodeProperties = etsy_request()->ced_remote_request( $shop_name, "application/seller-taxonomy/nodes/{$categoryId}/properties" );
						// $getTaxonomyNodeProperties = json_decode( json_encode( $getTaxonomyNodeProperties ), true );
						$getTaxonomyNodeProperties = $getTaxonomyNodeProperties['results'];
						if ( isset( $getTaxonomyNodeProperties ) && is_array( $getTaxonomyNodeProperties ) && ! empty( $getTaxonomyNodeProperties ) ) {
							$attribute_meta_data = get_post_meta( $productId, 'ced_etsy_attribute_data', true );
							foreach ( $getTaxonomyNodeProperties as $key => $value ) {
								$property = ! empty( $attribute_meta_data[ ( '_ced_etsy_property_id_' . $value['property_id'] ) ] ) ? $attribute_meta_data[ ( '_ced_etsy_property_id_' . $value['property_id'] ) ] : 0;
								if ( empty( $property ) ) {
									$property = parent::fetch_meta_value( $productId, '_ced_etsy_property_id_' . $value['property_id'] );
								}
								foreach ( $value['possible_values'] as $tax_value ) {
									if ( $tax_value['name'] == $property ) {
										$property = $tax_value['value_id'];
										break;
									}
								}

								if ( isset( $property ) && ! empty( $property ) ) {
									$property_id[ $value['property_id'] ] = $property;
								}
							}
						}
						if ( isset( $property_id ) && ! empty( $property_id ) ) {
							foreach ( $property_id as $key => $value ) {

								$property_id_to_listing = (int) $key;
								$value_ids              = (int) $value;
								$params                 = array(

									'property_id' => (int) $property_id_to_listing,
									'value_ids'   => array( (int) $value_ids ),
									'values'      => array( (string) $value_ids ),
								);
								$shop_id                = get_etsy_shop_id( $shop_name );
								$reponse                = etsy_request()->ced_remote_request( $shop_name, "application/shops/{$shop_id}/listings/{$listing_id}/properties/{$property_id_to_listing}", 'PUT', array(), $params );
								// var_dump($reponse);
							}
						}
						update_post_meta( $productId, 'ced_etsy_attribute_uploaded', 'true' );
					}
				}
			}
		}



		/**
		 * ***************************
		 * Upload downloadable files
		 * ***************************
		 *
		 * @since 2.0.8
		 *
		 * @param array  $p_id Checked Product ids
		 * @param string $shopName Active Shop Name
		 *
		 * @return
		 */
		private function ced_upload_downloadable( $p_id = '', $shop_name = '', $l_id = '' ) {
			$listing_files_uploaded = get_post_meta( $p_id, '_ced_etsy_product_files_uploaded' . $l_id, true );
			if ( empty( $listing_files_uploaded ) ) {
				$listing_files_uploaded = array();
			}
			$downloadable_files = $this->downloadable_data;
			if ( ! empty( $downloadable_files ) ) {
				$count = 0;
				foreach ( $downloadable_files as $data ) {
					if ( $count > 4 ) {
						break;
					}
					$file_data = $data->get_data();
					if ( isset( $listing_files_uploaded[ $file_data['id'] ] ) ) {
						continue;
					}
					try {
						$file_path = str_replace( wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $file_data['file'] );
						do_action( 'ced_etsy_refresh_token', $shop_name );
						$shop_id  = get_etsy_shop_id( $shop_name );
						$response = parent::ced_etsy_upload_image_and_file( 'file', "application/shops/{$shop_id}/listings/{$l_id}/files", $file_path, $file_data['name'], $shop_name );

						if ( isset( $response['listing_file_id'] ) ) {
							$listing_files_uploaded[ $file_data['id'] ] = $response['listing_file_id'];
							update_post_meta( $p_id, '_ced_etsy_product_files_uploaded' . $l_id, $listing_files_uploaded );
						}
					} catch ( Exception $e ) {
						$this->error_msg['msg'] = 'Message:' . $e->getMessage();
						return $this->error_msg;
					}
				}
			}
		}


		/**
		 * *************************
		 * Update uploaded images.
		 * *************************
		 *
		 * @since 2.0.8
		 *
		 * @param array  $p_id Checked Product ids
		 * @param string $shopName Active Shop Name
		 *
		 * @return
		 */
		public function ced_etsy_prep_and_upload_img( $p_id = '', $shop_name = '', $listing_id = '' ) {
			if ( empty( $p_id ) || empty( $shop_name ) ) {
				return;
			}
			$this->ced_product = isset( $this->ced_product ) ? $this->ced_product : wc_get_product( $p_id );
			$prnt_img_id       = get_post_thumbnail_id( $p_id );
			if ( WC()->version < '3.0.0' ) {
				$attachment_ids = $this->ced_product->get_gallery_attachment_ids();
			} else {
				$attachment_ids = $this->ced_product->get_gallery_image_ids();
			}
			$previous_thum_ids = get_post_meta( $p_id, 'ced_etsy_previous_thumb_ids' . $listing_id, true );
			if ( empty( $previous_thum_ids ) || ! is_array( $previous_thum_ids ) ) {
				$previous_thum_ids = array();
			}
			$attachment_ids = array_slice( $attachment_ids, 0, 9 );
			if ( ! empty( $attachment_ids ) ) {
				foreach ( array_reverse( $attachment_ids ) as $attachment_id ) {
					if ( isset( $previous_thum_ids[ $attachment_id ] ) ) {
						continue;
					}

					/*
					|=======================
					| UPLOAD GALLERY IMAGES
					|=======================
					*/
					$image_result = self::do_image_upload( $listing_id, $p_id, $attachment_id, $shop_name );
					if ( isset( $image_result['listing_image_id'] ) ) {
						$previous_thum_ids[ $attachment_id ] = $image_result['listing_image_id'];
						update_post_meta( $p_id, 'ced_etsy_previous_thumb_ids' . $listing_id, $previous_thum_ids );
					}
				}
			}

			/*
			|===================
			| UPLOAD MAIN IMAGE
			|===================
			*/
			if ( ! isset( $previous_thum_ids[ $prnt_img_id ] ) ) {
				$image_result = self::do_image_upload( $listing_id, $p_id, $prnt_img_id, $shop_name );
				if ( isset( $image_result['listing_image_id'] ) ) {
					$previous_thum_ids[ $prnt_img_id ] = $image_result['listing_image_id'];
					update_post_meta( $p_id, 'ced_etsy_previous_thumb_ids' . $listing_id, $previous_thum_ids );
				}
			}
		}

		/**
		 * ************************************
		 * UPLOAD IMAGED ON THE ETSY SHOP ;)
		 * ************************************
		 *
		 * @since 1.0.0
		 *
		 * @param int    $l_id Product listing ids.
		 * @param int    $pr_id Product ids .
		 * @param int    $img_id Image Ids.
		 * @param string $shop_name Active Shop Name
		 *
		 * @return Nothing [Message]
		 */

		public function do_image_upload( $l_id, $pr_id, $img_id, $shop_name ) {
			$image_path = get_attached_file( $img_id );
			$image_name = basename( $image_path );

				do_action( 'ced_etsy_refresh_token', $shop_name );
				$shop_id  = get_etsy_shop_id( $shop_name );
				$response = parent::ced_etsy_upload_image_and_file( 'image', "application/shops/{$shop_id}/listings/{$l_id}/images", $image_path, $image_name, $shop_name );
				return $this->ced_etsy_parse_response( $response );

		}

		public function update_images_on_etsy( $product_ids = array(), $shop_name = '' ) {
			// ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
			if ( ! is_array( $product_ids ) ) {
				$product_ids = array( $product_ids );
			}
			$shop_id      = get_etsy_shop_id( $shop_name );
			$notification = array();
			if ( is_array( $product_ids ) && ! empty( $product_ids ) ) {
				foreach ( $product_ids as $pr_id ) {
					$listing_id = get_post_meta( $pr_id, '_ced_etsy_listing_id_' . $shop_name, true );
					update_post_meta( $pr_id, 'ced_etsy_previous_thumb_ids' . $listing_id, '' );
					$etsy_images = etsy_request()->ced_remote_request( $shop_name, "application/listings/{$listing_id}/images", 'GET' );
					$etsy_images = isset( $etsy_images['results'] ) ? $etsy_images['results'] : array();
					foreach ( $etsy_images as $key => $image_info ) {
						$main_image_id = isset( $image_info['listing_image_id'] ) ? $image_info['listing_image_id'] : '';
						/** Get all the listing Images form Etsy
						 * 
						 * @since 1.0.0
						 *  */
						do_action( 'ced_etsy_refresh_token', $shop_name );
						$action   = "application/shops/{$shop_id}/listings/{$listing_id}/images/{$main_image_id}";
						$response = etsy_request()->delete( $action, $shop_name, array(), 'DELETE' );
					}
					$this->ced_etsy_prep_and_upload_img( $pr_id, $shop_name, $listing_id );
					$notification['status']  = 200;
					$notification['message'] = 'Image updated successfully';
				}
			}
			return $notification;
		}

		public function ced_etsy_parse_response( $json ) {
			return json_decode( $json, true );
		}

		/**
		 * *********************************************
		 * PREPARE DATA FOR UPDATING DATA TO ETSY SHOP
		 * *********************************************
		 *
		 * @since 1.0.0
		 *
		 * @param array  $proIDs Product lsting  ids.
		 * @param string $shop_name Active shopName.
		 *
		 * @return Nothing[Updating only Uploaded attribute ids]
		 */

		public function prepareDataForUpdating( $product_ids = array(), $shop_name, $log = true ) {

			if ( ! is_array( $product_ids ) ) {
				$product_ids = array( $product_ids );
			}
			$notification = array();
			$shop_name    = empty( $shop_name ) ? $this->shop_name : $shop_name;
			$product_ids  = empty( $product_ids ) ? $this->product_id : $product_ids;
			foreach ( $product_ids as $product_id ) {
				if ( empty( $this->listing_id ) ) {
					$this->listing_id = get_post_meta( $product_id, '_ced_etsy_listing_id_' . $shop_name, true );
				}
				$arguements = parent::get_formatted_data( $product_id, $shop_name );
				if ( isset( $arguements['has_error'] ) ) {
					$notification['status']  = 400;
					$notification['message'] = $arguements['error'];
				} else {
					$arguements['state'] = parent::get_state();
					$shop_id             = get_etsy_shop_id( $shop_name );
					$action              = "application/shops/{$shop_id}/listings/{$this->listing_id}";
					do_action( 'ced_etsy_refresh_token', $shop_name );
					$response = etsy_request()->ced_remote_request( $shop_name, $action, 'PUT', array(), $arguements );
					if ( isset( $response['listing_id'] ) ) {
						update_post_meta( $product_id, '_ced_etsy_listing_data_' . $shop_name, json_encode( $response ) );
						$notification['status']  = 200;
						$notification['message'] = $arguements['title'] . ' updated successfully';
					} elseif ( isset( $response['error'] ) ) {
						$notification['status']  = 400;
						$notification['message'] = $response['error'];
					} else {
						$notification['status']  = 400;
						$notification['message'] = json_encode( $response );
					}
					global $activity;
						$activity->action        = 'Update';
						$activity->type          = 'product';
						$activity->input_payload = $arguements;
						$activity->response      = $response;
						$activity->post_id       = $product_id;
						$activity->shop_name     = $shop_name;
						$activity->post_title    = $arguements['title'];

							$activity->execute();
				}
			}
			return $notification;
		}




		 /**
		  * *****************************************************
		  * PREPARING DATA FOR UPDATING INVENTORY TO ETSY SHOP
		  * *****************************************************
		  *
		  * @since 1.0.0
		  *
		  * @param array   $proIDs Product lsting  ids.
		  * @param string  $shop_name Active shopName.
		  * @param boolean $is_sync condition for is sync.
		  *
		  * @return $response ,
		  */

		public function prepareDataForUpdatingInventory( $product_ids = array(), $shop_name, $is_sync = false ) {
			if ( ! is_array( $product_ids ) ) {
				$product_ids = array( $product_ids );
			}
			$notification = array();
			$shop_name    = empty( $shop_name ) ? $this->shop_name : $shop_name;
			$product_ids  = empty( $product_ids ) ? $this->product_id : $product_ids;
			foreach ( $product_ids as $product_id ) {
				$_product = wc_get_product( $product_id );
				if ( empty( $this->listing_id ) ) {
					$this->listing_id = get_post_meta( $product_id, '_ced_etsy_listing_id_' . $shop_name, true );
				}
				parent::get_formatted_data( $product_id, $shop_name );
				if ( 'variable' == $_product->get_type() ) {
					$response = $this->update_variation_sku_to_etsy( $this->listing_id, $product_id, $shop_name, false );
				} else {
					parent::get_formatted_data( $product_id, $shop_name );
					$sku      = get_post_meta( $product_id, '_sku', true );
					$response = etsy_request()->ced_remote_request( $shop_name, "application/listings/{$this->listing_id}/inventory", 'GET' );
					if ( isset( $response['products'][0] ) ) {
						if ( (int) parent::get_quantity() <= 0 ) {
							$response = $this->ced_etsy_deactivate_product( $product_id, $shop_name );
						} else {
							$product_payload = $response;
							$product_payload['products'][0]['offerings'][0]['quantity'] = (int) parent::get_quantity();
							$product_payload['products'][0]['offerings'][0]['price']    = (float) parent::get_price();
							$product_payload['products'][0]['sku']                      = (string) $sku;
							unset( $product_payload['products'][0]['is_deleted'] );
							unset( $product_payload['products'][0]['product_id'] );
							unset( $product_payload['products'][0]['offerings'][0]['is_deleted'] );
							unset( $product_payload['products'][0]['offerings'][0]['offering_id'] );
							do_action( 'ced_etsy_refresh_token', $shop_name );
							$input_payload = $product_payload;
							$response      = etsy_request()->ced_remote_request( $shop_name, "application/listings/{$this->listing_id}/inventory", 'PUT', array(), $product_payload );
						}
					}
				}

				global $activity;
				$activity->action        = 'Update';
				$activity->type          = 'product_inventory';
				$activity->input_payload = $input_payload;
				$activity->response      = $response;
				$activity->post_id       = $product_id;
				$activity->shop_name     = $shop_name;
				$activity->post_title    = $_product->get_title();
				$activity->is_auto       = $is_sync;
				$activity->execute();

				if ( isset( $response['products'][0] ) ) {
					$notification['status']  = 200;
					$notification['message'] = 'Product inventory updated successfully';
				} elseif ( isset( $response['error'] ) ) {
					$notification['status']  = 400;
					$notification['message'] = $response['error'];
				} else {
					$notification['status']  = 400;
					$notification['message'] = json_encode( $response );
				}
			}
			return $notification;

		}

		public function ced_etsy_activate_product( $product_ids = array(), $shop_name = '' ) {

			if ( ! is_array( $product_ids ) ) {
				$product_ids = array( $product_ids );
			}
			$shop_name   = empty( $shop_name ) ? $this->shop_name : $shop_name;
			$product_ids = empty( $product_ids ) ? $this->product_id : $product_ids;
			foreach ( $product_ids as $product_id ) {
				if ( empty( $this->listing_id ) ) {
					$this->listing_id = get_post_meta( $product_id, '_ced_etsy_listing_id_' . $shop_name, true );
				}
				$arguements['state'] = parent::get_state();
				$shop_id             = get_etsy_shop_id( $shop_name );
				$action              = "application/shops/{$shop_id}/listings/{$this->listing_id}";
				do_action( 'ced_etsy_refresh_token', $shop_name );
				$this->response = etsy_request()->ced_remote_request( $shop_name, $action, 'PUT', array(), $arguements );
				return $this->response;
			}
		}

		public function ced_etsy_deactivate_product( $product_ids = array(), $shop_name = '' ) {

			if ( ! is_array( $product_ids ) ) {
				$product_ids = array( $product_ids );
			}
			$shop_name   = empty( $shop_name ) ? $this->shop_name : $shop_name;
			$product_ids = empty( $product_ids ) ? $this->product_id : $product_ids;
			foreach ( $product_ids as $product_id ) {
				if ( empty( $this->listing_id ) ) {
					$this->listing_id = get_post_meta( $product_id, '_ced_etsy_listing_id_' . $shop_name, true );
				}
				$arguements['state'] = 'inactive';
				$shop_id             = get_etsy_shop_id( $shop_name );
				$action              = "application/shops/{$shop_id}/listings/{$this->listing_id}";
				do_action( 'ced_etsy_refresh_token', $shop_name );
				$this->response = etsy_request()->ced_remote_request( $shop_name, $action, 'PUT', array(), $arguements );
				return $this->response;
			}
		}

		 /**
		  * *******************************
		  * DELETE THE LISTINGS FORM ETSY
		  * *******************************
		  *
		  * @since 1.0.0
		  *
		  * @param array  $proIDs Product lsting  ids.
		  * @param string $shop_name Active shopName.
		  *
		  * @return $reponse deleted product ids ,
		  */

		public function prepareDataForDelete( $product_ids = array(), $shop_name, $log = true ) {
			if ( ! is_array( $product_ids ) ) {
				$product_ids = array( $product_ids );
			}
			$notification = array();
			foreach ( $product_ids as $product_id ) {
				$product    = wc_get_product( $product_id );
				$listing_id = get_post_meta( $product_id, '_ced_etsy_listing_id_' . $shop_name, true );
				if ( $listing_id ) {
					do_action( 'ced_etsy_refresh_token', $shop_name );
					$response = parent::ced_remote_request( $shop_name, "application/listings/{$listing_id}", 'DELETE' );
					if ( ! isset( $response['error'] ) ) {
						delete_post_meta( $product_id, '_ced_etsy_listing_id_' . $shop_name );
						delete_post_meta( $product_id, '_ced_etsy_url_' . $shop_name );
						delete_post_meta( $product_id, '_ced_etsy_product_files_uploaded' . $listing_id );
						delete_post_meta( $product_id, 'ced_etsy_previous_thumb_ids' . $listing_id );
						$notification['status']  = 200;
						$notification['message'] = 'Product removed successfully';
						$response['results']     = $notification;
					} elseif ( isset( $response['error'] ) ) {
						$notification['status']  = 400;
						$notification['message'] = $response['error'];
					} else {
						$notification['status']  = 400;
						$notification['message'] = json_encode( $response );
					}
				}

				if ( $log ) {
					global $activity;
					$activity->action        = 'Remove';
					$activity->type          = 'product';
					$activity->input_payload = $listing_id;
					$activity->response      = $response;
					$activity->post_id       = $product_id;
					$activity->shop_name     = $shop_name;
					$activity->post_title    = $product->get_title();
					$activity->execute();
				}
			}
			return $notification;
		}

		/**
		 * *****************************************
		 * UPDATE VARIATION SKU TO ETSY SHOP
		 * *****************************************
		 *
		 * @since 1.0.0
		 *
		 * @param array  $listing_id Product lsting  ids.
		 * @param array  $productId Product  ids.
		 * @param string $shopId Active shopName.
		 *
		 * @link  http://www.cedcommerce.com/
		 * @return $reponse
		 */

		private function update_variation_sku_to_etsy( $listing_id, $productId, $shop_name, $is_sync = false, $log = true ) {
			$offerings_payload = parent::ced_variation_details( $productId, $shop_name );
			do_action( 'ced_etsy_refresh_token', $shop_name );
			$response = parent::ced_remote_request( $shop_name, "application/listings/{$listing_id}/inventory", 'PUT', array(), $offerings_payload );
			if ( isset( $response['products'][0]['product_id'] ) ) {
				update_post_meta( $productId, 'ced_etsy_last_updated' . $shop_name, gmdate( 'l jS \of F Y h:i:s A' ) );
			}
			$_product = wc_get_product( $productId );
			if ( $log ) {
				global $activity;
				$activity->action        = 'Update';
				$activity->type          = 'product_inventory';
				$activity->input_payload = $final_attribute_variation_final;
				$activity->response      = $response;
				$activity->post_id       = $productId;
				$activity->shop_name     = $shop_name;
				$activity->post_title    = $_product->get_title();
				$activity->is_auto       = $is_sync;
				$activity->execute();
			}
			if ( ! $is_sync ) {
				return $response;
			}

		}

		/**
		 * ****************************************************
		 * UPLOADING THE VARIABLE AND SIMPLE PROUCT TO ETSY
		 * ****************************************************
		 *
		 * @since 1.0.0
		 *
		 * @param int    $product_id Product  ids.
		 * @param string $shop_name Active shop Name.
		 *
		 * @link  http://www.cedcommerce.com/
		 * @return Uploaded product Ids.
		 */

		public function doupload( $product_id, $shop_name ) {

			do_action( 'ced_etsy_refresh_token', $shop_name );
			$shop_id  = get_etsy_shop_id( $shop_name );
			$response = parent::ced_remote_request( $shop_name, "application/shops/{$shop_id}/listings", 'POST', array(), array_filter( $this->data ) );
			/**
			 * ************************************************
			 *  Update post meta after uploading the Products.
			 * ************************************************
			 *
			 * @since 2.0.8
			 */

			if ( isset( $response['listing_id'] ) ) {
				update_post_meta( $product_id, '_ced_etsy_listing_id_' . $shop_name, $response['listing_id'] );
				update_post_meta( $product_id, '_ced_etsy_url_' . $shop_name, $response['url'] );
			}

			if ( isset( $response['error'] ) ) {
				$error                 = array();
				$error['error']        = isset( $response['error'] ) ? $response['error'] : 'some error occured';
				$this->upload_response = $error;
			} else {
				$this->upload_response = $response;
			}
		}


		/**
		 * *****************************************
		 * GET ASSIGNED PRODUCT DATA FROM PROFILES
		 * *****************************************
		 *
		 * @since 1.0.0
		 *
		 * @param array  $proId Product lsting  ids.
		 * @param string $shopId Active shopName.
		 *
		 * @link  http://www.cedcommerce.com/
		 * @return $profile_data assigined profile data .
		 */

		public function getProfileAssignedData( $proId, $shopId ) {

			$data = wc_get_product( $proId );
			$type = $data->get_type();
			if ( 'variation' == $type ) {
				$proId = $data->get_parent_id();
			}

			global $wpdb;
			$productData = wc_get_product( $proId );
			$product     = $productData->get_data();
			$category_id = isset( $product['category_ids'] ) ? $product['category_ids'] : array();
			$profile_id  = get_post_meta( $proId, 'ced_etsy_profile_assigned' . $shopId, true );
			if ( ! empty( $profile_id ) ) {
				$profile_id = $profile_id;
			} else {
				foreach ( $category_id as $key => $value ) {
					$profile_id = get_term_meta( $value, 'ced_etsy_profile_id_' . $shopId, true );

					if ( ! empty( $profile_id ) ) {
						break;

					}
				}
			}
			$this->profile_id   = false;
			$this->profile_name = '';
			if ( isset( $profile_id ) && ! empty( $profile_id ) ) {
				$this->isProfileAssignedToProduct = true;
				$this->profile_id                 = $profile_id;
				$profile_data                     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_etsy_profiles WHERE `id`=%s ", $profile_id ), 'ARRAY_A' );

				if ( is_array( $profile_data ) ) {
					$profile_data       = isset( $profile_data[0] ) ? $profile_data[0] : $profile_data;
					$this->profile_name = isset( $profile_data['profile_name'] ) ? $profile_data['profile_name'] : '';
					$profile_data       = isset( $profile_data['profile_data'] ) ? json_decode( $profile_data['profile_data'], true ) : array();
				}
			} else {
				$this->isProfileAssignedToProduct = false;
				return 'false';
			}
			$this->profile_data = isset( $profile_data ) ? $profile_data : '';
			return $this->profile_data;
		}

	}

}
