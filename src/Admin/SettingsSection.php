<?php

namespace ClientAccessPortalGoogleDrive\Admin;

use ClientAccessPortalGoogleDrive\Support\CoreBridge;
use ClientAccessPortalGoogleDrive\Support\Settings;

class SettingsSection {
	private CoreBridge $core_bridge;

	private Settings $settings_helper;

	public function __construct( CoreBridge $core_bridge, Settings $settings_helper ) {
		$this->core_bridge      = $core_bridge;
		$this->settings_helper  = $settings_helper;
	}

	public function register(): void {
		register_setting(
			'client_access_portal_settings',
			'client_access_portal_google_drive_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->defaults(),
			)
		);
	}

	public function sanitize( array $settings ): array {
		$defaults = $this->defaults();

		return array(
			'credentials_path'          => sanitize_text_field( $settings['credentials_path'] ?? $defaults['credentials_path'] ),
			'master_folder_id'          => sanitize_text_field( $settings['master_folder_id'] ?? $defaults['master_folder_id'] ),
			'review_folder_name'        => sanitize_text_field( $settings['review_folder_name'] ?? $defaults['review_folder_name'] ),
			'sync_interval_minutes'     => absint( $settings['sync_interval_minutes'] ?? $defaults['sync_interval_minutes'] ),
			'alert_email'               => sanitize_email( $settings['alert_email'] ?? $defaults['alert_email'] ),
			'notify_on_client_upload'   => ! empty( $settings['notify_on_client_upload'] ) ? 1 : 0,
			'notification_recipient'    => sanitize_email( $settings['notification_recipient'] ?? $defaults['notification_recipient'] ),
		);
	}

	public function render(): void {
		$settings = $this->settings_helper->all();
		$health   = $this->settings_helper->health_summary();
		?>
		<h2><?php esc_html_e( 'Google Drive Provider', 'client-access-portal-google-drive' ); ?></h2>
		<div class="notice notice-info inline">
			<p><strong><?php esc_html_e( 'This addon expects a Google service account JSON key.', 'client-access-portal-google-drive' ); ?></strong></p>
			<p><?php esc_html_e( 'Do not use an API key or an OAuth client ID here. You need the absolute server path to a downloaded service account JSON file plus a Drive folder ID that has been shared with that service account email.', 'client-access-portal-google-drive' ); ?></p>
			<p><strong><?php esc_html_e( 'Important:', 'client-access-portal-google-drive' ); ?></strong> <?php esc_html_e( 'Service accounts can read from shared folders in My Drive, but Google blocks them from owning uploaded files there. If you want uploads and file creation to work, use a Shared Drive as the master folder or switch to OAuth delegation.', 'client-access-portal-google-drive' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Detected core plugin', 'client-access-portal-google-drive' ); ?></th>
				<td>
					<?php if ( $this->core_bridge->is_available() ) : ?>
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: slug, 2: version */
									__( 'Active core: %1$s (version %2$s)', 'client-access-portal-google-drive' ),
									$this->core_bridge->plugin_slug(),
									$this->core_bridge->version()
								)
							);
							?>
						</p>
						<p class="description">
							<?php
							echo esc_html(
								$this->core_bridge->is_dev_install()
									? __( 'The active core install is a `-dev` build. This addon will bind to it without using a folder-specific dependency header.', 'client-access-portal-google-drive' )
									: __( 'The active core install is a release-style build. This addon will bind to it through the same runtime API.', 'client-access-portal-google-drive' )
							);
							?>
						</p>
					<?php else : ?>
						<p><?php esc_html_e( 'No compatible core plugin instance is active.', 'client-access-portal-google-drive' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Configuration health', 'client-access-portal-google-drive' ); ?></th>
				<td>
					<strong><?php echo esc_html( strtoupper( $health['status'] ) ); ?></strong>
					<p class="description"><?php echo esc_html( $health['message'] ); ?></p>
				</td>
			</tr>
		</table>
		<h3><?php esc_html_e( 'Setup Steps', 'client-access-portal-google-drive' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Create or choose a Google Cloud project.', 'client-access-portal-google-drive' ); ?></li>
			<li><?php esc_html_e( 'Enable the Google Drive API in that project.', 'client-access-portal-google-drive' ); ?></li>
			<li><?php esc_html_e( 'Create a service account in IAM & Admin > Service Accounts.', 'client-access-portal-google-drive' ); ?></li>
			<li><?php esc_html_e( 'Create and download a JSON key for that service account.', 'client-access-portal-google-drive' ); ?></li>
			<li><?php esc_html_e( 'Create or choose a Google Drive folder that will act as the master folder for client folders.', 'client-access-portal-google-drive' ); ?></li>
			<li><?php esc_html_e( 'Share that folder with the service account email as an Editor.', 'client-access-portal-google-drive' ); ?></li>
			<li><?php esc_html_e( 'Paste the absolute JSON file path into Credentials path and the Google Drive folder ID into Master folder ID.', 'client-access-portal-google-drive' ); ?></li>
		</ol>
		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: docs file path */
					__( 'Full setup notes are in %s.', 'client-access-portal-google-drive' ),
					'<code>' . esc_html( 'web/app/plugins/client-access-portal-google-drive/GOOGLE-DRIVE-SETUP.md' ) . '</code>'
				),
				array(
					'code' => array(),
				)
			);
			?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cap-gd-credentials-path"><?php esc_html_e( 'Credentials path', 'client-access-portal-google-drive' ); ?></label></th>
				<td>
					<input id="cap-gd-credentials-path" name="client_access_portal_google_drive_settings[credentials_path]" type="text" class="regular-text" value="<?php echo esc_attr( $settings['credentials_path'] ); ?>">
					<p class="description"><?php esc_html_e( 'Recommended: define the JSON key path outside the web root. You can also override this with the `CLIENT_ACCESS_PORTAL_GOOGLE_DRIVE_CREDENTIALS_PATH` constant for environment-specific setups.', 'client-access-portal-google-drive' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cap-gd-master-folder"><?php esc_html_e( 'Master folder ID', 'client-access-portal-google-drive' ); ?></label></th>
				<td>
					<input id="cap-gd-master-folder" name="client_access_portal_google_drive_settings[master_folder_id]" type="text" class="regular-text" value="<?php echo esc_attr( $settings['master_folder_id'] ); ?>">
					<p class="description"><?php esc_html_e( 'This is the long ID from a Google Drive folder URL such as https://drive.google.com/drive/folders/FOLDER_ID. That folder must be shared with the service account email.', 'client-access-portal-google-drive' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cap-gd-review-folder-name"><?php esc_html_e( 'Review folder name', 'client-access-portal-google-drive' ); ?></label></th>
				<td><input id="cap-gd-review-folder-name" name="client_access_portal_google_drive_settings[review_folder_name]" type="text" class="regular-text" value="<?php echo esc_attr( $settings['review_folder_name'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cap-gd-sync-interval"><?php esc_html_e( 'Sync interval (minutes)', 'client-access-portal-google-drive' ); ?></label></th>
				<td><input id="cap-gd-sync-interval" name="client_access_portal_google_drive_settings[sync_interval_minutes]" type="number" min="1" class="small-text" value="<?php echo esc_attr( (string) $settings['sync_interval_minutes'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cap-gd-alert-email"><?php esc_html_e( 'Alert email', 'client-access-portal-google-drive' ); ?></label></th>
				<td><input id="cap-gd-alert-email" name="client_access_portal_google_drive_settings[alert_email]" type="email" class="regular-text" value="<?php echo esc_attr( $settings['alert_email'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Client upload notifications', 'client-access-portal-google-drive' ); ?></th>
				<td>
					<label for="cap-gd-notify-upload">
						<input id="cap-gd-notify-upload" name="client_access_portal_google_drive_settings[notify_on_client_upload]" type="checkbox" value="1" <?php checked( ! empty( $settings['notify_on_client_upload'] ) ); ?>>
						<?php esc_html_e( 'Notify staff when a client upload reaches the review folder.', 'client-access-portal-google-drive' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cap-gd-notification-recipient"><?php esc_html_e( 'Notification recipient', 'client-access-portal-google-drive' ); ?></label></th>
				<td><input id="cap-gd-notification-recipient" name="client_access_portal_google_drive_settings[notification_recipient]" type="email" class="regular-text" value="<?php echo esc_attr( $settings['notification_recipient'] ); ?>"></td>
			</tr>
		</table>
		<h3><?php esc_html_e( 'Connection Test', 'client-access-portal-google-drive' ); ?></h3>
		<p class="description"><?php esc_html_e( 'This test uses the currently saved settings, not any unsaved values in the form above. Save settings first, then run the test.', 'client-access-portal-google-drive' ); ?></p>
		<p>
			<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=client_access_portal_google_drive_test_connection' ), 'client_access_portal_google_drive_test_connection' ) ); ?>">
				<?php esc_html_e( 'Test Google Drive Connection', 'client-access-portal-google-drive' ); ?>
			</a>
		</p>
		<?php
	}

	private function defaults(): array {
		return $this->settings_helper->defaults();
	}
}
