<?php

class OCC_API {

    private $encryption;

    public function __construct() {
        $this->encryption = new OCC_Encryption();
    }

    /**
     * Retrieve and Decrypt Token
     */
    private function get_token() {
        $encrypted_token = get_option( 'occ_rc_api_token' );
        if ( empty( $encrypted_token ) ) {
            return '';
        }
        return $this->encryption->decrypt( $encrypted_token );
    }

    /**
     * General Request Wrapper
     */
    public function request( $endpoint, $method = 'GET', $body = null ) {
        $token = $this->get_token();

        if ( empty( $token ) ) {
            return new WP_Error( 'missing_token', 'RunCloud API Token is missing.' );
        }

        // FIX 1: Defined the array correctly (removed syntax error "$args =;")
        // FIX 2: Added the Authorization header with "Bearer"
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'method'  => $method,
            'timeout' => 30,
        ];

        if ( ! empty( $body ) ) {
            $args['body'] = json_encode( $body );
        }

        $response = wp_remote_request( OCC_API_BASE . $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body_decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        // RunCloud v3 returns 401/403 for auth issues
        if ( $code >= 400 ) {
            $message = isset( $body_decoded['message'] ) ? $body_decoded['message'] : 'Unknown API Error';
            return new WP_Error( 'api_error', $message . " (Status: $code)" );
        }

        return $body_decoded;
    }

    /**
     * Test Connection (/ping)
     */
    public function test_connection() {
        return $this->request( '/ping' );
    }

    /**
	 * SECURITY: Rate Limiter
	 * Prevents abuse by limiting "Write" operations (Clones) per hour.
	 */
	public function check_rate_limit() {
		$limit = 5; // Maximum 5 clones per hour
		$current_count = get_transient( 'occ_clone_count_hour' );

		if ( $current_count !== false && $current_count >= $limit ) {
			return new WP_Error( 'rate_limit', 'Hourly clone limit reached. Please wait.' );
		}
		return true;
	}

	public function increment_rate_limit() {
		$current_count = get_transient( 'occ_clone_count_hour' );
		if ( $current_count === false ) {
			set_transient( 'occ_clone_count_hour', 1, HOUR_IN_SECONDS );
		} else {
			set_transient( 'occ_clone_count_hour', $current_count + 1, HOUR_IN_SECONDS );
		}
	}

   /**
	 * --- STEP 2 & PHASE 2: DISCOVERY & LIST METHODS ---
	 */

	public function get_servers() {
		return $this->request( '/servers' );
	}

	/**
	 * Get Web Apps (Updated for v1.2.0 with Pagination)
	 * @param int $server_id
	 * @param string $search Optional search term
	 * @param int $page Page number (Default 1)
	 */
	public function get_webapps( $server_id, $search = '', $page = 1 ) {
		$endpoint = '/servers/' . intval( $server_id ) . '/webapps?page=' . intval( $page );
		
		// RunCloud allows perPage param (usually max 50), let's try to maximize it
		$endpoint .= '&perPage=50'; 

		if ( ! empty( $search ) ) {
			$endpoint .= '&search=' . urlencode( $search );
		}
		return $this->request( $endpoint );
	}

	/**
	 * Get Databases (Updated for v1.2.0 with Pagination)
	 */
	public function get_databases( $server_id, $page = 1 ) {
		return $this->request( 
			'/servers/' . intval( $server_id ) . '/databases?page=' . intval( $page ) . '&perPage=50' 
		);
	}
	
	/**
	 * Get Database Users (New for v1.2.0)
	 */
	public function get_database_users( $server_id, $page = 1 ) {
		return $this->request( 
			'/servers/' . intval( $server_id ) . '/databaseusers?page=' . intval( $page ) . '&perPage=50' 
		);
	}
    /**
	 * --- STEP 4: SYSTEM USER MANAGEMENT ---
	 */

	/**
	 * Get System Users (Cached)
	 * Uses Transients to respect rate limits.
	 */
	public function get_system_users( $server_id ) {
		$cache_key = 'occ_sys_users_' . $server_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Fetch from API
		$response = $this->request( '/servers/' . intval( $server_id ) . '/users' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Cache for 15 minutes
		if ( isset( $response['data'] ) ) {
			set_transient( $cache_key, $response['data'], 15 * MINUTE_IN_SECONDS );
			return $response['data'];
		}

		return [];
	}

	/**
	 * Clear System User Cache
	 * Call this after creating a new user so the list updates.
	 */
	public function clear_user_cache( $server_id ) {
		delete_transient( 'occ_sys_users_' . $server_id );
	}

	/**
	 * Create New System User
	 */
	public function create_system_user( $server_id, $username, $password ) {
		return $this->request( 
			'/servers/' . intval( $server_id ) . '/users', 
			'POST', 
			[
				'username' => $username,
				'password' => $password
			]
		);
	}

}