<?php

class OCC_Admin {

	private $api;
	private $encryption;
	private $discovery;
	private $ajax; // New property

	public function __construct() {
		$this->api = new OCC_API();
		$this->encryption = new OCC_Encryption();
		require_once OCC_PLUGIN_DIR . 'includes/class-occ-discovery.php';
		$this->discovery = new OCC_Discovery();
		
		// Load AJAX
		require_once OCC_PLUGIN_DIR . 'includes/class-occ-ajax.php';
		$this->ajax = new OCC_Ajax();
	}

	public function run() {
		add_action( 'admin_menu', [ $this, 'add_plugin_menu' ] );
		add_action( 'admin_post_occ_save_settings', [ $this, 'handle_save_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// Register AJAX hooks
		$this->ajax->register();
	}

	public function add_plugin_menu() {
		add_menu_page( 'RunCloud Clone', 'One Click Clone', 'manage_options', 'one-click-clone', [ $this, 'display_main_page' ], 'dashicons-cloud', 99 );
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_one-click-clone' !== $hook ) return;
		
		wp_enqueue_style( 'occ-admin-css', OCC_PLUGIN_URL . 'admin/css/occ-admin.css', [], OCC_VERSION );
		
		// Enqueue JS
		wp_enqueue_script( 'occ-admin-js', OCC_PLUGIN_URL . 'admin/js/occ-admin.js', ['jquery'], OCC_VERSION, true );
		
		// Pass Data to JS
		wp_localize_script( 'occ-admin-js', 'occVars', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'occ_clone_action' )
		]);
	}

	public function display_main_page() {
		$token_exists = get_option( 'occ_rc_api_token' );
		$view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'settings';
		if ( ! $token_exists ) $view = 'settings';

		echo '<div class="wrap"><h1>One Click Clone (RunCloud)</h1>';
		echo '<h2 class="nav-tab-wrapper">';
		echo '<a href="?page=one-click-clone&view=clone" class="nav-tab ' . ( $view === 'clone' ? 'nav-tab-active' : '' ) . '">Cloning Tool</a>';
		echo '<a href="?page=one-click-clone&view=settings" class="nav-tab ' . ( $view === 'settings' ? 'nav-tab-active' : '' ) . '">Settings & API</a>';
		echo '</h2><br>';

		if ( $view === 'settings' ) {
			// Run Discovery logic if we have a token
			$discovery_data = $token_exists ? $this->discovery->discover_environment() : null;
			require_once OCC_PLUGIN_DIR . 'admin/partials/occ-settings-view.php';
		} else {
			// Step 3: Load Clone Interface
			$discovery_data = $token_exists ? $this->discovery->discover_environment() : null;
			
			// NEW: Fetch System Users (if server ID is known)
			$system_users = [];
			if ( isset( $discovery_data['server_id'] ) ) {
				$system_users = $this->api->get_system_users( $discovery_data['server_id'] );
				// Handle API error gracefully (empty list)
				if ( is_wp_error( $system_users ) ) $system_users = [];
			}

			require_once OCC_PLUGIN_DIR . 'admin/partials/occ-clone-view.php';
		}
		
		echo '</div>';
	}

	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'occ_save_settings_action', 'occ_nonce' );

		// 1. Save Token
		if ( ! empty( $_POST['api_token'] ) ) {
			$encrypted = $this->encryption->encrypt( sanitize_text_field( $_POST['api_token'] ) );
			update_option( 'occ_rc_api_token', $encrypted );
		}

		// 2. Save Domain Suffix
		if ( isset( $_POST['domain_suffix'] ) ) {
			update_option( 'occ_domain_suffix', sanitize_text_field( $_POST['domain_suffix'] ) );
		}

		// 3. Save Cloudflare Settings (New)
		$use_cf = isset( $_POST['use_cloudflare'] ) ? 1 : 0;
		update_option( 'occ_use_cloudflare', $use_cf );

		if ( isset( $_POST['cf_api_id'] ) ) {
			// We can strip spaces to ensure it's just the ID
			update_option( 'occ_cf_api_id', sanitize_text_field( trim( $_POST['cf_api_id'] ) ) );
		}

		// Test Connection
		$test = $this->api->test_connection();
		if ( is_wp_error( $test ) ) {
			wp_redirect( admin_url( 'admin.php?page=one-click-clone&view=settings&status=error&msg=' . urlencode( $test->get_error_message() ) ) );
		} else {
			wp_redirect( admin_url( 'admin.php?page=one-click-clone&view=settings&status=success' ) );
		}
		exit;
	}
}