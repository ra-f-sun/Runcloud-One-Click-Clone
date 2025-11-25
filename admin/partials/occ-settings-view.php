<div class="card" style="max-width: 600px;">
	<h2>API Configuration</h2>
	
	<?php if ( isset( $_GET['status'] ) && 'success' === $_GET['status'] ) :?>
		<div class="notice notice-success inline"><p><strong>Connection Established!</strong> RunCloud API v3 responded with "pong".</p></div>
	<?php elseif ( isset( $_GET['status'] ) && 'error' === $_GET['status'] ) :?>
		<div class="notice notice-error inline"><p><strong>Connection Failed:</strong> <?php echo esc_html( urldecode( $_GET['msg'] ) );?></p></div>
	<?php endif;?>

	<form method="post" action="<?php echo admin_url( 'admin-post.php' );?>">
		<input type="hidden" name="action" value="occ_save_settings">
		<?php wp_nonce_field( 'occ_save_settings_action', 'occ_nonce' );?>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="api_token">RunCloud API Token</label></th>
				<td>
					<input name="api_token" type="password" id="api_token" value="" class="regular-text" placeholder="Enter new token to update" />
					<p class="description">Enter your <strong>API Token</strong> (Bearer Token) from RunCloud Settings > API Management.</p>
					<?php if ( get_option( 'occ_rc_api_token' ) ) :?>
						<p style="color: green;">&#10003; An API Token is currently saved.</p>
					<?php endif;?>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="Save & Test Connection" />
		</p>
	</form>
</div>