<?php
$ced_h           = new Cedhandler();
$ced_h->dir_name = '/admin/etsy/lib/';
$ced_h->ced_require( 'class-ced-product-payload' );
if ( ! class_exists( 'Ced_Etsy_Request' ) ) {
	class Ced_Etsy_Request {
		use Ced_Product_Payload;
		/**
		 * Etsy Cleint ID variable
		 *
		 * @var int
		 */
		// public $client_id = ced_etsy_get_auth();
		/**
		 * Base URL for Etsy API.
		 *
		 * @var string
		 */
		public $base_url = 'https://api.etsy.com/v3/';
		/**
		 * Etsy API Key.
		 *
		 * @var string
		 */
		// public $client_secret = ced_etsy_get_auth();

		/**
		 * *********************************
		 *  UPDATE FILE AND IMAGED TO ETSY
		 * *********************************
		 *
		 * @param string $types
		 * @param string $action
		 * @param string $source_file
		 * @param string $file_name
		 * @param string $shop_name
		 * @return object
		 */


		public function delete( $action = '', $shop_name = '', $query_args = array(), $method = 'DELETE' ) {
			$api_url = $this->base_url . $action;
			if ( ! empty( $query_args ) ) {
				$api_url = $api_url . '?' . http_build_query( $query_args );
			}

			$header = array(
				'Content-Type: application/json',
				'Accept: application/json',
				'x-api-key: ' . ced_etsy_get_auth(),
			);

			$access_token = $this->get_access_token( $shop_name );
			if ( ! empty( $access_token ) ) {
				$header[] = 'Authorization: Bearer ' . $access_token;
			}
			$curl = curl_init();
			curl_setopt_array(
				$curl,
				array(
					CURLOPT_URL            => $api_url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST  => 'DELETE',
					CURLOPT_HTTPHEADER     => $header,
				)
			);

			$response = curl_exec( $curl );
			$response = $this->parse_reponse( $response );

			if ( isset( $response['error'] ) && 'invalid_token' == $response['error'] ) {
				update_option( 'ced_etsy_reauthorize_account', 'yes' );
			}

			curl_close( $curl );
			return $response;

		}

		public function get( $action = '', $shop_name = '', $query_args = array() ) {

			$api_url = $this->base_url . $action;
			if ( ! empty( $query_args ) ) {
				$api_url = $api_url . '?' . http_build_query( $query_args );
			}

			$header = array(
				'Content-Type: application/json',
				'Accept: application/json',
				'x-api-key: ' . ced_etsy_get_auth(),
			);

			$access_token = $this->get_access_token( $shop_name );
			if ( ! empty( $access_token ) ) {
				$header[] = 'Authorization: Bearer ' . $access_token;
			}

			$curl = curl_init();
			curl_setopt_array(
				$curl,
				array(
					CURLOPT_URL            => $api_url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST  => 'GET',
					CURLOPT_HTTPHEADER     => $header,
				)
			);

			$response = curl_exec( $curl );
			$response = $this->parse_reponse( $response );
			curl_close( $curl );
			return $response;

		}

		public function ced_etsy_upload_image_and_file( $types = '', $action, $source_file, $file_name, $shop_name ) {
			$access_token = $this->get_access_token( $shop_name );
			$mimetype     = mime_content_type( $source_file );
			$params       = array( '@' . $types => '@' . $source_file . ';type=' . $mimetype );
			$curl         = curl_init();
			curl_setopt_array(
				$curl,
				array(
					CURLOPT_URL            => 'https://openapi.etsy.com/v3/' . $action,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING       => '',
					CURLOPT_MAXREDIRS      => 10,
					CURLOPT_TIMEOUT        => 0,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST  => 'POST',
					CURLOPT_POSTFIELDS     => array(
						$types => new \CURLFile( $source_file ),
						'name' => $file_name,
					),
					CURLOPT_HTTPHEADER     => array(
						'Content-Type: multipart/form-data',
						'x-api-key: ' . ced_etsy_get_auth(),
						'Authorization: Bearer ' . $access_token,
					),
				)
			);
			$response = curl_exec( $curl );
			curl_close( $curl );
			return $response;
		}

		/**
		 * Get access token.
		 *
		 * @param string $shop_name
		 * @return string
		 */
		public function get_access_token( $shop_name ) {
			if ( empty( $shop_name ) ) {
				return false;
			}
			$user_details     = get_option( 'ced_etsy_details', array() );
				$access_token = isset( $user_details[ $shop_name ]['details']['token']['access_token'] ) ? $user_details[ $shop_name ]['details']['token']['access_token'] : '';
			return ! empty( $access_token ) ? $access_token : '';
		}

		public function parse_reponse( $response ) {
			return json_decode( $response, true );
		}


		public function ced_remote_request( $shop_name, $action, $method = 'GET', $url_args = array(), $payload_args = array() ) {
			$api_url = $this->base_url . $action;
			if ( ! empty( $url_args ) ) {
				$api_url = $api_url . '?' . http_build_query( $url_args );

			}
			// print_r($api_url);
			// die();
			$args = array();
			$args = array(
				'timeout'     => 5,
				'redirection' => 5,
				'sslverify'   => 0,
				'data_format' => 'body',
			);
			if ( $method ) {
				$args['method'] = $method;
			}
			$access_token = $this->get_access_token( $shop_name );
			if ( ! empty( $access_token ) && 'public/oauth/token' != $action ) {
				$args['headers'] = array(
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
					'x-api-key'     => ced_etsy_get_auth(),
					'Authorization' => 'Bearer ' . $access_token,
				);
			} else {
				$args['headers'] = array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'x-api-key'    => ced_etsy_get_auth(),
				);
			}

			if ( 'GET' !== $method ) {
				if ( '' !== $payload_args ) {
					$args['body'] = json_encode( $payload_args );
				}
			}
			$response = wp_remote_request( $api_url, $args );
			// print_r($response);
			$response_code    = wp_remote_retrieve_response_code( $response );
			$response_message = wp_remote_retrieve_response_message( $response );
			$response_body    = wp_remote_retrieve_body( $response );
			if ( $response_body ) {
				$b_res = json_decode( $response_body );
				if ( $b_res ) {
					$response_body = $b_res;
				}
			}

			$response = ! empty( $response_body ) ? json_decode( json_encode( $response_body ), 1 ) : array();
			return $response;
		}
	}
}
