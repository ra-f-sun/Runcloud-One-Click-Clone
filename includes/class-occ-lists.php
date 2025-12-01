<?php

class OCC_Lists {

	private $api;

	public function __construct() {
		// Ensure API is loaded
		if ( ! class_exists( 'OCC_API' ) ) {
			require_once OCC_PLUGIN_DIR . 'includes/class-occ-api.php';
		}
		$this->api = new OCC_API();
	}

	/**
	 * Get All Unavailable Names (Apps, DBs, DB Users)
	 * Returns a structured array of names that are already taken.
	 */
	public function get_unavailable_names( $server_id ) {
		$server_id = intval( $server_id );
		if ( ! $server_id ) return [];

		// 1. Check Cache
		$cache_key = 'occ_unavailable_names_' . $server_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// 2. Fetch Fresh Data (Heavy Operation)
		$apps     = $this->fetch_all_resource_names( 'get_webapps', $server_id, 'name' );
		$dbs      = $this->fetch_all_resource_names( 'get_databases', $server_id, 'name' );
		$db_users = $this->fetch_all_resource_names( 'get_database_users', $server_id, 'username' );

		$data = [
			'apps'     => $apps,
			'dbs'      => $dbs,
			'db_users' => $db_users,
			'updated'  => time()
		];

		// 3. Set Cache (15 Minutes)
		set_transient( $cache_key, $data, 15 * MINUTE_IN_SECONDS );

		return $data;
	}

	/**
	 * Helper: Loop through API Pagination
	 */
	private function fetch_all_resource_names( $method_name, $server_id, $name_field ) {
		$names = [];
		$page = 1;
		$finished = false;

		// Safety break to prevent infinite loops (max 10 pages = 500 items)
		$max_pages = 10; 

		while ( ! $finished && $page <= $max_pages ) {
			
			// Call dynamic method (e.g., $this->api->get_webapps($id, '', $page))
			if ( 'get_webapps' === $method_name ) {
				$response = $this->api->$method_name( $server_id, '', $page );
			} else {
				$response = $this->api->$method_name( $server_id, $page );
			}

			if ( is_wp_error( $response ) || ! isset( $response['data'] ) ) {
				$finished = true; // Stop on error
				break;
			}

			// Collect Names
			foreach ( $response['data'] as $item ) {
				if ( isset( $item[ $name_field ] ) ) {
					$names[] = $item[ $name_field ];
				}
			}

			// Check Pagination Meta
			if ( isset( $response['meta']['pagination'] ) ) {
				$meta = $response['meta']['pagination'];
				
				// RunCloud API v3 Logic: current_page < total_pages
				if ( $meta['current_page'] < $meta['total_pages'] ) {
					$page++;
				} else {
					$finished = true;
				}
			} else {
				// No pagination meta? Assume single page
				$finished = true;
			}
			
			// Rate Limit Protection (Sleep 0.2s between pages)
			usleep( 200000 ); 
		}

		return $names;
	}

	/**
	 * Force Clear Cache (Call this after a successful clone)
	 */
	public function clear_cache( $server_id ) {
		delete_transient( 'occ_unavailable_names_' . intval( $server_id ) );
	}
}