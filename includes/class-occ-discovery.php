<?php

class OCC_Discovery {

	private $api;

	public function __construct() {
		$this->api = new OCC_API();
	}

	public function discover_environment() {
		$discovery = [
			'server_id'   => null,
			'webapp_id'   => null,
			'database_id' => null,
			'logs'        => [],
		];

		// ----------------------------------------
		// 1. Identify Server (IP Match)
		// ----------------------------------------
		$public_ip = $this->get_public_ip();
		$discovery['logs'][] = "Detected Public IP: " . $public_ip;

		$servers_response = $this->api->get_servers();

		if ( is_wp_error( $servers_response ) ) {
			$discovery['logs'][] = "API Error: " . $servers_response->get_error_message();
			return $discovery;
		}

		if ( isset( $servers_response['data'] ) ) {
			foreach ( $servers_response['data'] as $server ) {
				if ( $server['ipAddress'] === $public_ip ) {
					$discovery['server_id'] = $server['id'];
					$discovery['logs'][] = "MATCH: Found Server ID " . $server['id'] . " (" . $server['name'] . ")";
					break;
				}
			}
		}

		if ( ! $discovery['server_id'] ) {
			$discovery['logs'][] = "FAIL: Could not find Server ID matching IP " . $public_ip;
			return $discovery;
		}

		// ----------------------------------------
		// 2. Identify WebApp (Path Match) - NEW METHOD
		// ----------------------------------------
		// RunCloud Path: /home/{user}/webapps/{app_name}/...
		$current_path = wp_normalize_path( ABSPATH ); // Ensure forward slashes
		$discovery['logs'][] = "Analyzing Path: " . $current_path;

		$app_name = $this->extract_app_name_from_path( $current_path );

		if ( $app_name ) {
			$discovery['logs'][] = "Extracted App Name: " . $app_name;

			// Use the API search parameter (Searching by Name IS supported)
			$apps_response = $this->api->get_webapps( $discovery['server_id'], $app_name );

			if ( ! is_wp_error( $apps_response ) && isset( $apps_response['data'] ) ) {
				foreach ( $apps_response['data'] as $app ) {
					// Strict match on the 'name' field (Folder Name)
					if ( $app['name'] === $app_name ) {
						$discovery['webapp_id'] = $app['id'];
						$discovery['logs'][] = "MATCH: Found WebApp ID " . $app['id'] . " (" . $app['name'] . ")";
						break;
					}
				}
			}
			if ( ! $discovery['webapp_id'] ) {
				$discovery['logs'][] = "FAIL: API found no apps named '" . $app_name . "'";
			}
		} else {
			$discovery['logs'][] = "FAIL: Could not detect 'webapps' folder structure in path.";
		}

		// ----------------------------------------
		// 3. Identify Database (DB_NAME Match)
		// ----------------------------------------
		$current_db_name = DB_NAME;
		$discovery['logs'][] = "Searching for DB: " . $current_db_name;

		$dbs_response = $this->api->get_databases( $discovery['server_id'] );

		if ( ! is_wp_error( $dbs_response ) && isset( $dbs_response['data'] ) ) {
			foreach ( $dbs_response['data'] as $db ) {
				if ( $db['name'] === $current_db_name ) {
					$discovery['database_id'] = $db['id'];
					$discovery['logs'][] = "MATCH: Found Database ID " . $db['id'];
					break;
				}
			}
		}

		return $discovery;
	}

	/**
	 * Helper: Get Public IP
	 */
	private function get_public_ip() {
		$response = wp_remote_get( 'https://api.ipify.org' );
		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			return trim( wp_remote_retrieve_body( $response ) );
		}
		return isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : '';
	}

	/**
	 * Helper: Parse ABSPATH to find RunCloud App Name
	 * Path looks like: /home/user/webapps/APP_NAME/public/
	 */
	private function extract_app_name_from_path( $path ) {
		// Explode path into segments
		$parts = explode( '/', $path );
		
		// Find the index of 'webapps'
		$index = array_search( 'webapps', $parts );

		// If found and there is a segment after it, that's the App Name
		if ( $index !== false && isset( $parts[ $index + 1 ] ) ) {
			return $parts[ $index + 1 ];
		}
		
		return false;
	}
}