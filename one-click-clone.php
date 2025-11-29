<?php
/**
 * Plugin Name: One Click Clone (RunCloud)
 * Description: Automated WordPress staging and cloning via RunCloud API v3.
 * Version: 1.0.0
 * Author: Rafsun Jani
 * Text Domain: one-click-clone
 */

if (! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants
define( 'OCC_VERSION', '1.0.0' );
define( 'OCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OCC_API_BASE', 'https://manage.runcloud.io/api/v3' );

// Auto-load Classes (Manual require for simplicity as requested)
require_once OCC_PLUGIN_DIR. 'includes/class-occ-encryption.php';
require_once OCC_PLUGIN_DIR. 'includes/class-occ-api.php';
require_once OCC_PLUGIN_DIR. 'includes/class-occ-admin.php';

/**
 * Initialize the Plugin
 */
function run_one_click_clone() {
	$plugin_admin = new OCC_Admin();
	$plugin_admin->run();
}
add_action( 'plugins_loaded', 'run_one_click_clone' );

/**
 * Security Check Notice
 * Alert admin if the encryption key is not defined in wp-config.php
 */
function occ_admin_security_notice() {
	if (! defined( 'OCC_ENCRYPTION_KEY' ) ) {
		?>
		<div class="notice notice-warning is-dismissible">
			<p><strong>One Click Clone Security Warning:</strong> Please define the <code>OCC_ENCRYPTION_KEY</code> constant in your <code>wp-config.php</code> file for maximum security.</p>
			<p><code>define( 'OCC_ENCRYPTION_KEY', '<?php echo esc_html( wp_salt( 'auth' ) );?>' );</code></p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'occ_admin_security_notice' );