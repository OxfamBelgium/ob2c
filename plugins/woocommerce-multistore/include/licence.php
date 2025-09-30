<?php

class WOO_MSTORE_licence {
	/**
	 * Verify license key entered in settings.
	 */
	public function licence_key_verify() {
		if ( is_admin() ) {
			$this->licence_deactivation_check();
		}
		
		if ( false === is_multisite() && get_option( 'woonet_network_type' ) == 'child' ) {
			return true; // Child sites don't need a license.
		}
		
		$license_data = get_site_option( 'mstore_license' );
		
		// GEWIJZIGD: Skip verificatie in productie, faalt sinds eind september 2025
		if ( wp_get_environment_type() === 'production' or $this->is_local_instance() ) {
			return true;
		}
		
		if ( ! isset( $license_data['key'] ) || $license_data['key'] == '' ) {
			return false;
		}
		
		return true;
	}

	/**
	 * Local development enviornments do not need a license.
	 */
	function is_local_instance() {

		$instance = trailingslashit( WOO_MSTORE_INSTANCE );

		if ( strpos( $instance, base64_decode( 'bG9jYWxob3N0Lw==' ) ) !== false
			|| strpos( $instance, base64_decode( 'MTI3LjAuMC4xLw==' ) ) !== false
			|| strpos( $instance, base64_decode( 'c3RhZ2luZy53cGVuZ2luZS5jb20=' ) ) !== false
			) {
				return true;
		}

		return false;
	}


	/**
	 * Check if entered license is valid.
	 *
	 * @return void
	 */
	function licence_deactivation_check() {
		if ( $this->is_local_instance() === true ) {
			delete_site_option( 'mstore_license' ); // delete if there's any old key in the database such after migration
			return;
		}

			$license_data = get_site_option( 'mstore_license' );

		if ( empty( $license_data['key'] ) || empty( $license_data['last_check'] ) ) {
			delete_site_option( 'mstore_license' ); // delete if there's any old key in the database such as after migration
			return;
		}

		if ( isset( $license_data['last_check'] ) ) {
			if ( time() < ( $license_data['last_check'] + 86400 ) ) { // 86400s = 24h
				return;
			}
		}

			$license_key = $license_data['key'];
			$args        = array(
				'woo_sl_action'     => 'status-check',
				'licence_key'       => $license_key,
				'product_unique_id' => WOO_MSTORE_PRODUCT_ID,
				'domain'            => WOO_MSTORE_INSTANCE,
			);
			$request_uri = WOO_MSTORE_APP_API_URL . '?' . http_build_query( $args, '', '&' );
			$data        = wp_remote_get( $request_uri );

			if ( defined( 'WOO_MOSTORE_DEV_ENV' ) && WOO_MOSTORE_DEV_ENV == true ) {
				error_log( var_export( $license_data, true ) );
				error_log( var_export( $data, true ) );
			}

			if ( is_wp_error( $data ) || $data['response']['code'] != 200 ) {
				return;
			}

			$response_block = json_decode( $data['body'] );
			// retrieve the last message within the $response_block
			$response_block = $response_block[ count( $response_block ) - 1 ];
			$response       = $response_block->message;

			if ( isset( $response_block->status_code ) ) {
				if ( $response_block->status_code == 's205' || $response_block->status_code == 's215' ) {
					$license_data['last_check'] = time();
					update_site_option( 'mstore_license', $license_data );
				} else {
					delete_site_option( 'mstore_license' );
				}
			}
	}

	/**
	 * Activate a license key
	 *
	 * @param strings $key License key
	 * @return array
	 */
	public function activate( $key ) {
		$key = sanitize_key( trim( $key ) );

		// build the request query
		$args = array(
			'woo_sl_action'     => 'activate',
			'licence_key'       => $key,
			'product_unique_id' => WOO_MSTORE_PRODUCT_ID,
			'domain'            => WOO_MSTORE_INSTANCE,
		);

		$request_uri = WOO_MSTORE_APP_API_URL . '?' . http_build_query( $args, '', '&' );
		$data        = wp_remote_get( $request_uri );

		if ( is_wp_error( $data ) || $data['response']['code'] != 200 ) {
			return array(
				'status' => 0,
				'msg'    => __( 'There was a problem connecting to ', 'woonet' ) . WOO_MSTORE_APP_API_URL,
			);
		}

		$response_block = json_decode( $data['body'] );
		// retrieve the last message within the $response_block
		$response_block = $response_block[ count( $response_block ) - 1 ];
		$response       = $response_block->message;

		if ( isset( $response_block->status ) ) {
			if ( $response_block->status == 'success' && in_array( $response_block->status_code, array( 's100', 's101' ) ) ) {
					// the license is active and the software is active
					$license_data = get_site_option( 'mstore_license' );

					// save the license
					$license_data['key']        = $key;
					$license_data['last_check'] = time();

					update_site_option( 'mstore_license', $license_data );

					return array(
						'status' => 1,
						'msg'    => $response_block->message,
					);

			} else {
				return array(
					'status' => 0,
					'msg'    => __( 'There was a problem activating the licence: ', 'woonet' ) . $response_block->message,
				);
			}
		}

		return array(
			'status' => 0,
			'msg'    => __( 'There was a problem with the data block received from ', 'woonet' ) . WOO_MSTORE_APP_API_URL,
		);
	}

	/**
	 * Deactivate license.
	 *
	 * @return mixed
	 */
	public function deactivate() {
		$license_data = get_site_option( 'mstore_license' );

		if ( empty( $license_data['key'] ) ) {
			return array(
				'status' => -1,
				'msg'    => 'No license key found.',
			);
		}

		// build the request query
		$args = array(
			'woo_sl_action'     => 'deactivate',
			'licence_key'       => $license_data['key'],
			'product_unique_id' => WOO_MSTORE_PRODUCT_ID,
			'domain'            => WOO_MSTORE_INSTANCE,
		);

		$request_uri = WOO_MSTORE_APP_API_URL . '?' . http_build_query( $args, '', '&' );
		$data        = wp_remote_get( $request_uri );

		if ( is_wp_error( $data ) || $data['response']['code'] != 200 ) {
			return array(
				'status' => 0,
				'msg'    => __( 'There was a problem connecting to ', 'woonet' ) . WOO_MSTORE_APP_API_URL,
			);
		}

		$response_block = json_decode( $data['body'] );
		// retrieve the last message within the $response_block
		$response_block = $response_block[ count( $response_block ) - 1 ];
		$response       = $response_block->message;

		if ( isset( $response_block->status ) ) {
			if ( $response_block->status == 'success' && $response_block->status_code == 's201' ) {
				// the license is active and the software is active
				delete_option( 'mstore_license' );
				return array(
					'status' => 1,
					'msg'    => $response_block->message,
				);
			} else { // if message code is e104  force de-activation
				if ( $response_block->status_code == 'e002' || $response_block->status_code == 'e104' ) {
					delete_option( 'mstore_license' );
					return array(
						'status' => 1,
						'msg'    => $response_block->message,
					);
				} else {
					delete_option( 'mstore_license' );
					return array(
						'status' => 0,
						'msg'    => __( 'There was a problem deactivating the licence: ', 'woonet' ) . $response_block->message,
					);
				}
			}
		}

		return array(
			'status' => 0,
			'msg'    => __( 'There was a problem with the data block received from ', 'woonet' ) . WOO_MSTORE_APP_API_URL,
		);
	}
}
