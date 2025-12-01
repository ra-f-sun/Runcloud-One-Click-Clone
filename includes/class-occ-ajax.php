<?php

class OCC_Ajax {

	private $api;

	public function __construct() {
		$this->api = new OCC_API();
	}

	public function register() {
		add_action( 'wp_ajax_occ_clone_app', [ $this, 'handle_clone_app' ] );
		add_action( 'wp_ajax_occ_check_status', [ $this, 'handle_check_status' ] );
	}

	/**
	 * HANDLE CLONE REQUEST
	 */
	public function handle_clone_app() {
		check_ajax_referer( 'occ_clone_action', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		// 1. Rate Limit
		if ( is_wp_error( $this->api->check_rate_limit() ) ) {
			wp_send_json_error( 'Rate limit reached.' );
		}

		// 2. Basic Inputs
		$server_id       = intval( $_POST['server_id'] );
		$input_name      = sanitize_text_field( $_POST['app_name'] );
		$input_subdomain = sanitize_text_field( $_POST['app_subdomain'] );
		$source_app_id   = intval( $_POST['source_app_id'] );
		$source_db_id    = intval( $_POST['source_db_id'] );
		
		// 3. System User Logic (The New Part)
		$user_mode = sanitize_text_field( $_POST['sys_user_mode'] ); // 'existing' or 'new'
		$final_user_id = 0;

		if ( 'new' === $user_mode ) {
			// A. Create New User
			$new_username = sanitize_text_field( $_POST['new_sys_user_name'] );
			
			// Validate username format (RunCloud strict rules: lowercase, numbers, no spaces)
			if ( ! preg_match( '/^[a-z0-9]+$/', $new_username ) ) {
				wp_send_json_error( 'System Username must be lowercase letters and numbers only.' );
			}

			// Generate strong password automatically
			$password = wp_generate_password( 20, true, true );

			$user_response = $this->api->create_system_user( $server_id, $new_username, $password );

			if ( is_wp_error( $user_response ) ) {
				wp_send_json_error( 'Failed to create User: ' . $user_response->get_error_message() );
			}

			// Success? Get the ID
			if ( isset( $user_response['id'] ) ) {
				$final_user_id = $user_response['id'];
				// Invalidate cache so next load shows this user
				$this->api->clear_user_cache( $server_id );
			} else {
				// Sometimes successful response structure varies, check message
				wp_send_json_error( 'User creation failed. API response invalid.' );
			}

		} else {
			// B. Use Existing
			$final_user_id = intval( $_POST['system_user_id'] );
		}

		if ( empty( $final_user_id ) ) {
			wp_send_json_error( 'Invalid System User ID.' );
		}

		// 4. Construct Clone Payload
		$suffix      = get_option( 'occ_domain_suffix', '' );
		$dest_name   = $input_name . '_' . date( 'Ymd_Hi' );
		$dest_domain = $input_subdomain . $suffix;
		
		// NEW v1.2.0: Manual DB Inputs
        // Note: We append the hardcoded suffixes here to match the frontend visual
        $input_db_name = sanitize_text_field( $_POST['db_name_custom'] );
        $input_db_user = sanitize_text_field( $_POST['db_user_custom'] );
        
		$dest_db   = $input_db_name . '_db';
		$dest_user = $input_db_user . '_u'; // Append suffix

		$payload = [
			'destinationName'         => $dest_name,
			'domainName'              => $dest_domain,
			'serverDestinationId'     => $server_id,
			'databaseClone'           => true,
			'sourceDatabaseId'        => $source_db_id,
			'databaseDestinationName' => $dest_db,
			'newDatabaseUsername'     => $dest_user,
			'user'                    => $final_user_id, // <--- Using the decided ID
			'cloneNginxConfig'        => true,
			'cloneRuncloudHub'        => false,
			'cloneBackup'             => false,
			'cloneModsec'             => false,
			'proxy'                   => false,
		];

		// Cloudflare Logic
		$use_cf = get_option( 'occ_use_cloudflare', 0 );
		$cf_id  = get_option( 'occ_cf_api_id', '' );
		
		if ( $use_cf && ! empty( $cf_id ) ) {
			$payload['dnsProvider'] = 'cloudflare';
			$payload['cfApiKeyId']  = intval( $cf_id );
			$payload['proxy']       = true;
			$payload['advancedSSL'] = true;
			$payload['autoSSL']     = true;
		} else {
			$payload['dnsProvider'] = 'none';
			$payload['proxy']       = false;
			$payload['autoSSL']     = true; 
		}

		// 5. Send Request
		$response = $this->api->request( 
			"/servers/{$server_id}/webapps/{$source_app_id}/clone", 
			'POST', 
			$payload 
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$this->api->increment_rate_limit();
		
		// Prepare Data for Frontend
		$success_data = [
			'message'  => 'Clone initiated.',
			'app_name' => $dest_name,
			'domain'   => $dest_domain
		];

		// IF we created a new user, pass the credentials back
		if ( 'new' === $user_mode && isset( $new_username ) && isset( $password ) ) {
			$success_data['new_sys_user'] = $new_username;
			$success_data['new_sys_pass'] = $password;
		}
		
		wp_send_json_success( $success_data );
	}

	/**
	 * POLL STATUS
	 * Checks if the app name exists on the server yet
	 */
	public function handle_check_status() {
		check_ajax_referer( 'occ_clone_action', 'nonce' );
		
		$server_id = intval( $_POST['server_id'] );
		$app_name  = sanitize_text_field( $_POST['app_name'] );

		// Use our API "search" (now that we know the exact name)
		$response = $this->api->get_webapps( $server_id, $app_name );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'API Error' );
		}

		$found = false;
		$app_id = 0;

		if ( isset( $response['data'] ) ) {
			foreach ( $response['data'] as $app ) {
				if ( $app['name'] === $app_name ) {
					$found = true;
					$app_id = $app['id'];
					break;
				}
			}
		}

		if ( $found ) {
			wp_send_json_success( [ 'status' => 'ready', 'app_id' => $app_id ] );
		} else {
			wp_send_json_success( [ 'status' => 'pending' ] );
		}
	}
}