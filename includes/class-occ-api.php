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
	 * --- STEP 2: DISCOVERY METHODS ---
	 */

	public function get_servers() {
		return $this->request( '/servers' );
	}

	public function get_webapps( $server_id, $search = '' ) {
		$endpoint = '/servers/' . intval( $server_id ) . '/webapps';
		if ( ! empty( $search ) ) {
			$endpoint .= '?search=' . urlencode( $search );
		}
		return $this->request( $endpoint );
	}

	public function get_databases( $server_id ) {
		return $this->request( '/servers/' . intval( $server_id ) . '/databases' );
	}


}