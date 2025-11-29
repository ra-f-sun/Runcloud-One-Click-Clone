<div class="card" style="max-width: 600px;">
	<h2>Cloning Tool</h2>
	<p>Clone this site to a new Staging environment.</p>

	<?php
	// Defaults from Discovery
	$server_id = isset($discovery_data['server_id']) ? $discovery_data['server_id'] : '';
	$app_id    = isset($discovery_data['webapp_id']) ? $discovery_data['webapp_id'] : '';
	$db_id     = isset($discovery_data['database_id']) ? $discovery_data['database_id'] : '';
	// System user is usually hard to detect via API without looking at file owner. 
	// For now, we leave it blank or let user input. 
	// Or we can try to "guess" it if we had user list logic.
	$sys_user  = ''; 
	?>

	<div id="occ-response-area" class="notice" style="display:none; margin-left: 0;"></div>

	<form id="occ-clone-form">
		
		<input type="hidden" id="server_id" value="<?php echo esc_attr($server_id); ?>">
		<input type="hidden" id="source_app_id" value="<?php echo esc_attr($app_id); ?>">
		<input type="hidden" id="source_db_id" value="<?php echo esc_attr($db_id); ?>">

		<table class="form-table">
			<tr>
				<th scope="row"><label for="app_name">New App Name</label></th>
				<td>
					<input type="text" id="app_name" class="regular-text" placeholder="e.g. staging" required>
					<p class="description">Internal name. Date/Time will be appended automatically.</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="app_subdomain">Subdomain</label></th>
				<td>
					<div style="display: flex; align-items: center;">
						<input type="text" id="app_subdomain" class="regular-text" required>
						<span style="margin-left: 5px; color: #666;">
							<?php echo esc_html( get_option( 'occ_domain_suffix', '-staging.example.com' ) ); ?>
						</span>
					</div>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label for="system_user_id">System User ID</label></th>
				<td>
					<input type="number" id="system_user_id" class="small-text" required>
					<p class="description">The RunCloud System User ID to own this app.</p>
				</td>
			</tr>

			<tr>
				<th scope="row">Summary</th>
				<td>
					<ul>
						<li><strong>Source Server ID:</strong> <?php echo $server_id ? $server_id : '<span style="color:red">Unknown</span>'; ?></li>
						<li><strong>Source App ID:</strong> <?php echo $app_id ? $app_id : '<span style="color:red">Unknown</span>'; ?></li>
						<li><strong>Cloudflare:</strong> <?php echo get_option('occ_use_cloudflare') ? 'Enabled' : 'Disabled'; ?></li>
					</ul>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary button-large">Clone Site</button>
			<span class="spinner" style="float: none; margin-top: 5px;"></span>
		</p>
	</form>
</div>