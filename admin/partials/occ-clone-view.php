<div class="occ-wrap">
	<div class="occ-card">
		
		<div class="occ-header">
			<h2>Cloning Tool</h2>
			<p>Provision a fresh Staging environment on RunCloud.</p>
		</div>

		<?php
		// Defaults
		$server_id = isset($discovery_data['server_id']) ? $discovery_data['server_id'] : '';
		$app_id    = isset($discovery_data['webapp_id']) ? $discovery_data['webapp_id'] : '';
		$db_id     = isset($discovery_data['database_id']) ? $discovery_data['database_id'] : '';
		$cf_status = get_option('occ_use_cloudflare') ? 'Enabled' : 'Disabled';
		?>

		<form id="occ-clone-form">
			<input type="hidden" id="server_id" value="<?php echo esc_attr($server_id); ?>">
			<input type="hidden" id="source_app_id" value="<?php echo esc_attr($app_id); ?>">
			<input type="hidden" id="source_db_id" value="<?php echo esc_attr($db_id); ?>">

			<div class="occ-field-group">
				<label for="app_name" class="occ-label">New Application Name</label>
				<input type="text" id="app_name" class="occ-input-full" placeholder="e.g. feature-test" required>
				<div class="occ-helper">Internal identifier. A timestamp will be appended automatically.</div>
			</div>

			<div class="occ-field-group">
				<label for="app_subdomain" class="occ-label">Subdomain</label>
				<div class="occ-input-group">
					<input type="text" id="app_subdomain" class="occ-input-full" placeholder="feature-test" required>
					<span class="occ-input-suffix">
						<?php echo esc_html( get_option( 'occ_domain_suffix', '-staging.example.com' ) ); ?>
					</span>
				</div>
			</div>

			<div class="occ-field-group">
				<label class="occ-label">System User Assignment</label>
				
				<div style="margin-bottom: 10px;">
					<label style="margin-right: 15px;">
						<input type="radio" name="sys_user_mode" value="existing" checked> 
						Select Existing
					</label>
					<label>
						<input type="radio" name="sys_user_mode" value="new"> 
						Create New User
					</label>
				</div>

				<div id="wrapper-user-existing">
					<select id="system_user_id" class="occ-input-full">
						<option value="">-- Select a User --</option>
						<?php if ( ! empty( $system_users ) ) : ?>
							<?php foreach ( $system_users as $user ) : ?>
								<option value="<?php echo esc_attr( $user['id'] ); ?>">
									<?php echo esc_html( $user['username'] ); ?>
								</option>
							<?php endforeach; ?>
						<?php else: ?>
							<option value="" disabled>No users found (or API error)</option>
						<?php endif; ?>
					</select>
					<div class="occ-helper">Select the owner of this web application.</div>
				</div>

				<div id="wrapper-user-new" style="display: none;">
					<div class="occ-input-group">
						<input type="text" id="new_sys_user_name" class="occ-input-full" placeholder="e.g. app-user">
						<span class="occ-input-suffix">-user</span> </div>
					<div class="occ-helper">New Linux user will be created. Password auto-generated.</div>
				</div>
			</div>

			<div class="occ-summary-box">
				<div class="occ-summary-grid">
					<div class="occ-stat-item">
						<span class="occ-stat-label">Source Server</span>
						<span class="occ-stat-value"><?php echo $server_id ?: '-'; ?></span>
					</div>
					<div class="occ-stat-item">
						<span class="occ-stat-label">Source App</span>
						<span class="occ-stat-value"><?php echo $app_id ?: '-'; ?></span>
					</div>
					<div class="occ-stat-item">
						<span class="occ-stat-label">Cloudflare</span>
						<span class="occ-stat-value"><?php echo $cf_status; ?></span>
					</div>
				</div>
			</div>

			<div id="occ-response-area"></div>
			
			<div class="occ-progress-container">
				<div class="occ-progress-bar" id="occ-progress-bar"></div>
			</div>

			<div class="occ-actions">
				<button type="submit" class="occ-btn-primary">Clone Site</button>
			</div>
		</form>
	</div>
	<div id="occ-confirm-modal" class="occ-modal-overlay">
		<div class="occ-modal">
			<div class="occ-modal-header">
				<h3>Confirm Cloning</h3>
			</div>
			<div class="occ-modal-body">
				<p>Are you sure you want to proceed?</p>
				<p>This will create a new Web Application, Database, and System User (if selected) on your RunCloud server.</p>
				<p><strong>Note:</strong> This process may take a few minutes.</p>
			</div>
			<div class="occ-modal-footer">
				<button type="button" class="occ-btn-secondary" id="occ-modal-cancel">Cancel</button>
				<button type="button" class="occ-btn-primary" id="occ-modal-confirm">Yes, Clone Site</button>
			</div>
		</div>
	</div>
</div>