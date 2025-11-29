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

		// 1. Security: Rate Limit
		$limit_check = $this->api->check_rate_limit();
		if ( is_wp_error( $limit_check ) ) {
			wp_send_json_error( $limit_check->get_error_message() );
		}

		// 2. Sanitize Inputs
		$input_name      = sanitize_text_field( $_POST['app_name'] ); // "staging"
		$input_subdomain = sanitize_text_field( $_POST['app_subdomain'] ); // "staging"
		$server_id       = intval( $_POST['server_id'] );
		$source_app_id   = intval( $_POST['source_app_id'] );
		$source_db_id    = intval( $_POST['source_db_id'] );
		
		// 3. Configuration & Hardcoding
		$suffix = get_option( 'occ_domain_suffix', '' );
		$use_cf = get_option( 'occ_use_cloudflare', 0 );
		$cf_id  = get_option( 'occ_cf_api_id', '' );

		// 4. Construct Calculated Values
		// Name: staging_TIMESTAMP (Unique)
		$dest_name = $input_name . '_' . date( 'Ymd_Hi' );
		
		// Domain: staging + suffix
		$dest_domain = $input_subdomain . $suffix;

		// DB Name: staging_db (Cleaned)
		$clean_sub = preg_replace( '/[^a-z0-9]/', '', strtolower( $input_subdomain ) );
		$dest_db   = substr( $clean_sub, 0, 16 ) . '_db'; // Max length safety
		$dest_user = substr( $clean_sub, 0, 10 ) . '_u' . rand(10,99); // Short user

		// 5. Build Payload
		$payload = [
			'destinationName'         => $dest_name,
			'domainName'              => $dest_domain,
			'serverDestinationId'     => $server_id,
			'databaseClone'           => true,
			'sourceDatabaseId'        => $source_db_id,
			'databaseDestinationName' => $dest_db,
			'newDatabaseUsername'     => $dest_user,
			// Hardcoded Defaults
			'user'                    => intval( $_POST['system_user_id'] ), // From hidden input
			'cloneNginxConfig'        => true,
			'cloneRuncloudHub'        => false,
			'cloneBackup'             => false,
			'cloneModsec'             => false,
			'proxy'                   => false, // Default off
		];

		// Cloudflare Logic
		if ( $use_cf && ! empty( $cf_id ) ) {
			$payload['dnsProvider'] = 'cloudflare';
			$payload['cfApiKeyId']  = intval( $cf_id );
			$payload['proxy']       = true; // Orange Cloud
			$payload['advancedSSL'] = true; // Use DNS-01 SSL
			$payload['autoSSL']     = true;
		} else {
			// No Cloudflare defaults
			$payload['dnsProvider'] = 'none'; // Or 'none'
		}

		// 6. Send Request
		$response = $this->api->request( 
			"/servers/{$server_id}/webapps/{$source_app_id}/clone", 
			'POST', 
			$payload 
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		// Success! Increment counter and return the unique name for polling
		$this->api->increment_rate_limit();
		
		wp_send_json_success( [
			'message' => 'Clone initiated successfully.',
			'app_name' => $dest_name, // Return this so JS can poll for it
			'domain'   => $dest_domain
		] );
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