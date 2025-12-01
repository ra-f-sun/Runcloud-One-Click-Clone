<div class="card" style="max-width: 600px;">
	<h2>Configuration</h2>
	
	<?php if ( isset( $_GET['status'] ) && 'success' === $_GET['status'] ) : ?>
		<div class="notice notice-success inline"><p><strong>Connection Established!</strong> API responded with "pong".</p></div>
	<?php elseif ( isset( $_GET['status'] ) && 'error' === $_GET['status'] ) : ?>
		<div class="notice notice-error inline"><p><strong>Connection Failed:</strong> <?php echo esc_html( urldecode( $_GET['msg'] ) ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
		<input type="hidden" name="action" value="occ_save_settings">
		<?php wp_nonce_field( 'occ_save_settings_action', 'occ_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="api_token">RunCloud API Token (v3)</label></th>
				<td>
					<input name="api_token" type="password" id="api_token" value="" class="regular-text" placeholder="Enter new token to update" />
					<?php if ( get_option( 'occ_rc_api_token' ) ) : ?>
						<p style="color: green; margin-top: 5px;">&#10003; Token is currently saved.</p>
					<?php endif; ?>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label for="domain_suffix">Domain Suffix</label></th>
				<td>
					<input name="domain_suffix" type="text" id="domain_suffix" 
						value="<?php echo esc_attr( get_option( 'occ_domain_suffix', '-staging.example.com' ) ); ?>" 
						class="regular-text" />
					<p class="description">Appended to user input. E.g., <code>-staging.example.com</code></p>
				</td>
			</tr>

			<tr>
				<th scope="row">Cloudflare Integration</th>
				<td>
					<label for="use_cloudflare">
						<input name="use_cloudflare" type="checkbox" id="use_cloudflare" value="1" 
						<?php checked( 1, get_option( 'occ_use_cloudflare', 0 ) ); ?> />
						Enable Cloudflare DNS & Proxy
					</label>
				</td>
			</tr>

			<tr id="cf_id_row" style="display: none;">
				<th scope="row"><label for="cf_api_id">Cloudflare Integration ID</label></th>
				<td>
					<input name="cf_api_id" type="text" id="cf_api_id" 
						value="<?php echo esc_attr( get_option( 'occ_cf_api_id' ) ); ?>" 
						class="regular-text" placeholder="e.g. 154" />
					<p class="description">The internal RunCloud ID for your Cloudflare account (Found in RunCloud > Settings > Integrations).</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="Save & Test Connection" />
		</p>
	</form>

	<?php if ( isset( $discovery_data ) ) : ?>
		<hr>
		<h3>Environment Discovery (Debug)</h3>
		<div style="background: #f0f0f1; padding: 15px; border: 1px solid #ccd0d4;">
			<p><strong>Detected Server ID:</strong> 
				<?php echo $discovery_data['server_id'] ? esc_html( $discovery_data['server_id'] ) : '<span style="color:red">Not Found (Check IP Match)</span>'; ?>
			</p>
			<p><strong>Detected WebApp ID:</strong> 
				<?php echo $discovery_data['webapp_id'] ? esc_html( $discovery_data['webapp_id'] ) : '<span style="color:red">Not Found (Check Domain Match)</span>'; ?>
			</p>
			<p><strong>Detected Database ID:</strong> 
				<?php echo $discovery_data['database_id'] ? esc_html( $discovery_data['database_id'] ) : '<span style="color:red">Not Found (Check DB Name)</span>'; ?>
			</p>
			
			<details>
				<summary style="cursor: pointer; color: #2271b1;">View Discovery Logs</summary>
				<ul style="margin-top: 10px; font-size: 12px;">
					<?php foreach ( $discovery_data['logs'] as $log ) : ?>
						<li><?php echo esc_html( $log ); ?></li>
					<?php endforeach; ?>
				</ul>
			</details>
		</div>
	<?php endif; ?>
</div>

<script>
jQuery(document).ready(function($){
	function toggleCF() {
		if( $('#use_cloudflare').is(':checked') ) {
			$('#cf_id_row').show();
		} else {
			$('#cf_id_row').hide();
		}
	}
	// Run on load and change
	toggleCF();
	$('#use_cloudflare').change(toggleCF);
});
</script>

<?php
// v1.2.0 Debug: Test List Fetching
if ( isset( $_GET['debug_lists'] ) && get_option('occ_rc_api_token') ) {
    $lists = new OCC_Lists();
    // Use the discovery ID if available, otherwise 0
    $sid = isset($discovery_data['server_id']) ? $discovery_data['server_id'] : 0;
    
    echo '<div class="card" style="background:#fff; margin-top:20px;"><h3>Debug: Unavailable Names</h3>';
    if($sid) {
        $names = $lists->get_unavailable_names($sid);
        echo '<pre>' . print_r($names, true) . '</pre>';
        echo '<p><em>Note: If you see this list, Caching & Pagination are working.</em></p>';
    } else {
        echo 'Run Discovery first to get Server ID.';
    }
    echo '</div>';
}
?>