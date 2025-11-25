<?php

class OCC_Encryption {

	private $cipher_method = 'AES-256-CBC';

	/**
	 * Get the secret key from wp-config or fallback to wp_salt
	 */
	private function get_key() {
		if ( defined( 'OCC_ENCRYPTION_KEY' ) ) {
			return OCC_ENCRYPTION_KEY;
		}
		// Fallback to WP Salt (Less secure if DB is leaked, but functional)
		return wp_salt( 'auth' );
	}

	public function encrypt( $string ) {
		if ( empty( $string ) ) return '';
		
		$key = $this->get_key();
		$iv_length = openssl_cipher_iv_length( $this->cipher_method );
		$iv = openssl_random_pseudo_bytes( $iv_length );
		
		$encrypted = openssl_encrypt( $string, $this->cipher_method, $key, 0, $iv );
		
		// Store IV and encrypted string together, base64 encoded
		return base64_encode( $iv. $encrypted );
	}

	public function decrypt( $string ) {
		if ( empty( $string ) ) return '';

		$key = $this->get_key();
		$data = base64_decode( $string );
		$iv_length = openssl_cipher_iv_length( $this->cipher_method );
		
		// Extract IV and Encrypted data
		$iv = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );
		
		return openssl_decrypt( $encrypted, $this->cipher_method, $key, 0, $iv );
	}
}